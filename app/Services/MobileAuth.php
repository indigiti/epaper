<?php
namespace EpaperSaas\Services;

use EpaperSaas\Core\{Auth,Config,RateLimiter,RequestContext,Store,Uuid};

final class MobileAuth
{
    public const CHALLENGE_TTL = 600;

    public static function enabled(): bool
    {
        return (bool)Config::get('auth.mobile.enabled', false) && self::channels(true)!==[];
    }

    /** @return string[] Effective tenant methods, bounded by server/provider capability. */
    public static function channels(bool $includeSilent = true): array
    {
        if (!(bool)Config::get('auth.mobile.enabled', false)) return [];
        $configured = Config::get('auth.mobile.channels', ['sms']);
        if (!is_array($configured)) $configured = ['sms'];
        $configured=array_values(array_unique(array_map(static fn($v)=>strtolower(trim((string)$v)),$configured)));
        $allowed = [];
        foreach (['sna','whatsapp','sms'] as $channel) {
            if (!in_array($channel,$configured,true)) continue;
            if (!$includeSilent && $channel === 'sna') continue;
            $default=$channel==='sms'?'on':'off';
            if(strtolower((string)TenantData::setting('reader_auth.'.$channel,$default))!=='on')continue;
            $allowed[] = $channel;
        }
        return $allowed;
    }

    public static function normalizePhone(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') throw new \InvalidArgumentException('Enter your mobile number.');
        $raw = preg_replace('/[\s().-]+/', '', $raw) ?? '';
        if (str_starts_with($raw, '00')) $raw = '+' . substr($raw, 2);
        if (!str_starts_with($raw, '+')) {
            $cc = preg_replace('/\D+/', '', (string)Config::get('auth.mobile.default_country_code', '+91')) ?? '91';
            $digits = preg_replace('/\D+/', '', $raw) ?? '';
            $digits = ltrim($digits, '0');
            // Accept a country code typed without "+" (for example 919876543210)
            // while keeping ordinary national numbers on the configured default country.
            $looksCountryPrefixed = $cc !== '' && strlen($digits) >= 11 && str_starts_with($digits, $cc);
            $raw = '+' . ($looksCountryPrefixed ? $digits : $cc . $digits);
        }
        if (!preg_match('/^\+[1-9][0-9]{7,14}$/', $raw)) {
            throw new \InvalidArgumentException('Enter a valid mobile number with country code.');
        }
        return $raw;
    }

    public static function start(string $rawPhone, string $rawChannel): array
    {
        if (!self::enabled()) throw new \RuntimeException('Mobile sign-in is not configured.');
        $phone = self::normalizePhone($rawPhone);
        $channel = strtolower(trim($rawChannel));
        if (!in_array($channel, self::channels(true), true)) throw new \InvalidArgumentException('This verification method is not available.');

        $tenant = RequestContext::tenantGuid();
        $tenantBin = Uuid::toBinary($tenant);
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $ipHash = hash_hmac('sha256', $ip, (string)Config::get('app.key', 'epdi-mobile-auth'));
        $phoneBucket = 'mobile-auth-phone:' . $tenant . ':' . hash('sha256', $phone);
        $ipBucket = 'mobile-auth-ip:' . $tenant . ':' . $ipHash;
        if (!RateLimiter::allow($phoneBucket, 5, 900) || !RateLimiter::allow($ipBucket, 20, 900)) {
            throw new \RuntimeException('Too many verification attempts. Please try again later.');
        }
        $recentPhone=(int)Store::value('SELECT COUNT(*) FROM ep_mobile_auth_challenges WHERE tenant_guid=? AND phone_e164=? AND created_at>=DATE_SUB(NOW(6),INTERVAL 15 MINUTE)',[$tenantBin,$phone]);
        $recentIp=(int)Store::value('SELECT COUNT(*) FROM ep_mobile_auth_challenges WHERE tenant_guid=? AND request_ip_hash=? AND created_at>=DATE_SUB(NOW(6),INTERVAL 15 MINUTE)',[$tenantBin,$ipHash]);
        if($recentPhone>=5||$recentIp>=20)throw new \RuntimeException('Too many verification attempts. Please try again later.');

        $provider = strtolower((string)Config::get('auth.mobile.provider', 'twilio_verify'));
        if ($provider !== 'twilio_verify') throw new \RuntimeException('Unsupported mobile verification provider.');
        $remote = self::twilioStart($phone, $channel);
        $providerSid = trim((string)($remote['sid'] ?? ''));
        if ($providerSid === '') throw new \RuntimeException('Verification provider did not return a challenge.');

        $challenge = Uuid::v7();
        $actualChannel = strtolower((string)($remote['channel'] ?? $channel));
        if (!in_array($actualChannel, ['sms','whatsapp','sna'], true)) $actualChannel = $channel;
        $snaUrl = is_array($remote['sna'] ?? null) ? trim((string)($remote['sna']['url'] ?? '')) : '';
        $expires = date('Y-m-d H:i:s.u', time() + self::CHALLENGE_TTL);
        Store::exec(
            'INSERT INTO ep_mobile_auth_challenges(challenge_guid,tenant_guid,phone_e164,channel,provider,provider_sid,request_ip_hash,status,attempts,expires_at,created_at,updated_at) VALUES(?,?,?,?,?,?,?,"PENDING",0,?,NOW(6),NOW(6))',
            [Uuid::toBinary($challenge), $tenantBin, $phone, $actualChannel, $provider, $providerSid, $ipHash, $expires]
        );

        return [
            'challenge_guid' => $challenge,
            'phone_e164' => $phone,
            'phone_masked' => self::maskPhone($phone),
            'channel' => $actualChannel,
            'expires_in' => self::CHALLENGE_TTL,
            'sna_url' => $snaUrl !== '' ? $snaUrl : null,
        ];
    }

    public static function verify(string $challengeGuid, string $code = '', string $installationId = '', string $sessionKind = ReaderSession::DESKTOP): array
    {
        if (!self::enabled()) throw new \RuntimeException('Mobile sign-in is not configured.');
        if (!Uuid::isValid($challengeGuid)) throw new \InvalidArgumentException('Invalid verification challenge.');
        $tenantBin = Uuid::toBinary(RequestContext::tenantGuid());
        $row = Store::one(
            'SELECT challenge_guid,phone_e164,channel,provider,provider_sid,status,attempts,expires_at FROM ep_mobile_auth_challenges WHERE tenant_guid=? AND challenge_guid=? LIMIT 1',
            [$tenantBin, Uuid::toBinary($challengeGuid)]
        );
        if (!$row || (string)$row['status'] !== 'PENDING') throw new \RuntimeException('This verification request is no longer active.');
        if (strtotime((string)$row['expires_at']) < time()) {
            Store::exec('UPDATE ep_mobile_auth_challenges SET status="EXPIRED",updated_at=NOW(6) WHERE tenant_guid=? AND challenge_guid=?', [$tenantBin, Uuid::toBinary($challengeGuid)]);
            throw new \RuntimeException('The verification code expired. Please request a new one.');
        }
        $attempts = (int)$row['attempts'];
        if ($attempts >= 6) {
            Store::exec('UPDATE ep_mobile_auth_challenges SET status="LOCKED",updated_at=NOW(6) WHERE tenant_guid=? AND challenge_guid=?', [$tenantBin, Uuid::toBinary($challengeGuid)]);
            throw new \RuntimeException('Too many incorrect attempts. Please request a new code.');
        }

        $channel = strtolower((string)$row['channel']);
        $code = trim($code);
        if ($channel !== 'sna' && !preg_match('/^[0-9]{4,10}$/', $code)) throw new \InvalidArgumentException('Enter the verification code.');
        // OTP checks count immediately. A transient SNA 60519/pending response does
        // not consume an incorrect-attempt slot; it is expected to be retried briefly.
        if ($channel !== 'sna') {
            Store::exec('UPDATE ep_mobile_auth_challenges SET attempts=attempts+1,updated_at=NOW(6) WHERE tenant_guid=? AND challenge_guid=?', [$tenantBin, Uuid::toBinary($challengeGuid)]);
        }

        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (!RateLimiter::allow('mobile-auth-check:' . RequestContext::tenantGuid() . ':' . hash('sha256', $ip), 30, 900)) {
            throw new \RuntimeException('Too many verification attempts. Please try again later.');
        }

        $provider = strtolower((string)$row['provider']);
        if ($provider !== 'twilio_verify') throw new \RuntimeException('Unsupported mobile verification provider.');
        try {
            $remote = self::twilioCheck((string)$row['provider_sid'], $code, $channel);
        } catch (MobileAuthPendingException $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($channel === 'sna') {
                Store::exec('UPDATE ep_mobile_auth_challenges SET attempts=attempts+1,updated_at=NOW(6) WHERE tenant_guid=? AND challenge_guid=?', [$tenantBin, Uuid::toBinary($challengeGuid)]);
            }
            throw $e;
        }
        if ($channel === 'sna') {
            Store::exec('UPDATE ep_mobile_auth_challenges SET attempts=attempts+1,updated_at=NOW(6) WHERE tenant_guid=? AND challenge_guid=?', [$tenantBin, Uuid::toBinary($challengeGuid)]);
        }
        $approved = strtolower((string)($remote['status'] ?? '')) === 'approved' && (($remote['valid'] ?? true) !== false);
        if (!$approved) throw new \RuntimeException($channel === 'sna' ? 'Automatic verification could not confirm this number. Choose WhatsApp or SMS.' : 'The verification code was not accepted.');

        $phone = (string)$row['phone_e164'];
        $user = self::completeLocalAccount($phone, $provider);
        if(trim($installationId)!=='')DeviceAuth::mergeIntoMobile($installationId,(string)$user['user_guid']);
        Store::exec('UPDATE ep_mobile_auth_challenges SET status="APPROVED",updated_at=NOW(6) WHERE tenant_guid=? AND challenge_guid=?', [$tenantBin, Uuid::toBinary($challengeGuid)]);
        Auth::establishLocalMobile($user, $phone, $sessionKind);
        return ['user' => $user, 'phone_e164' => $phone];
    }

    /** @return array{expired:int,deleted:int} */
    public static function pruneChallenges(int $retentionDays = 14): array
    {
        $retentionDays=max(1,min(90,$retentionDays));
        $expired=Store::exec('UPDATE ep_mobile_auth_challenges SET status="EXPIRED",updated_at=NOW(6) WHERE status="PENDING" AND expires_at<NOW(6)');
        $deleted=Store::exec('DELETE FROM ep_mobile_auth_challenges WHERE status IN ("APPROVED","EXPIRED","LOCKED") AND updated_at<DATE_SUB(NOW(6),INTERVAL '.$retentionDays.' DAY)');
        return ['expired'=>(int)$expired,'deleted'=>(int)$deleted];
    }

    private static function completeLocalAccount(string $phone, string $provider): array
    {
        $tenant = RequestContext::tenantGuid();
        $tb = Uuid::toBinary($tenant);
        return Store::tx(function() use ($phone, $provider, $tb) {
            $existing = Store::one('SELECT account_guid,user_guid,status FROM ep_local_mobile_accounts WHERE tenant_guid=? AND phone_e164=? LIMIT 1 FOR UPDATE', [$tb, $phone]);
            if ($existing) {
                if (strtoupper((string)$existing['status']) !== 'ACTIVE') throw new \RuntimeException('This reader account is disabled.');
                $userGuid = Uuid::fromBinary($existing['user_guid']);
                if (!$userGuid) throw new \RuntimeException('Local account is invalid.');
                $subscriber = Store::one('SELECT status FROM ep_subscribers WHERE tenant_guid=? AND platform_user_guid=? LIMIT 1', [$tb, $existing['user_guid']]);
                if ($subscriber && strtoupper((string)$subscriber['status']) !== 'ACTIVE') throw new \RuntimeException('This reader account is disabled.');
                Store::exec('UPDATE ep_local_mobile_accounts SET phone_verified_at=NOW(6),last_login_at=NOW(6),updated_at=NOW(6) WHERE tenant_guid=? AND phone_e164=?', [$tb, $phone]);
                return ['user_guid'=>$userGuid,'email'=>null,'display_name'=>self::displayName($phone),'status'=>'ACTIVE'];
            }

            $accountGuid = Uuid::v7();
            $userGuid = Uuid::v7();
            $ub = Uuid::toBinary($userGuid);
            Store::exec('INSERT INTO ep_local_mobile_accounts(account_guid,tenant_guid,user_guid,phone_e164,phone_verified_at,auth_provider,status,last_login_at,created_at,updated_at) VALUES(?,?,?,?,NOW(6),?,"ACTIVE",NOW(6),NOW(6),NOW(6))', [Uuid::toBinary($accountGuid), $tb, $ub, $phone, $provider]);
            Store::exec('INSERT INTO ep_identities(platform_user_guid,email,display_name,identity_source,status,last_seen_at,created_at,updated_at) VALUES(?,NULL,?,"LOCAL_MOBILE","ACTIVE",NOW(6),NOW(6),NOW(6))', [$ub, self::displayName($phone)]);
            Store::exec('INSERT INTO ep_subscribers(subscriber_guid,tenant_guid,platform_user_guid,status,created_at,updated_at) VALUES(?,?,?,"ACTIVE",NOW(6),NOW(6))', [Uuid::toBinary(Uuid::v7()), $tb, $ub]);
            return ['user_guid'=>$userGuid,'email'=>null,'display_name'=>self::displayName($phone),'status'=>'ACTIVE'];
        });
    }

    private static function displayName(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        return 'Reader ••••' . substr($digits, -4);
    }

    public static function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) <= 4) return $phone;
        return '+' . substr($digits, 0, max(1, strlen($digits) - 8)) . '••••' . substr($digits, -4);
    }

    private static function twilioStart(string $phone, string $channel): array
    {
        $serviceSid = trim((string)Config::get('auth.mobile.twilio.service_sid', ''));
        if ($serviceSid === '') self::providerFailure('Twilio Verify service SID is not configured.', ['stage'=>'config']);
        $url = 'https://verify.twilio.com/v2/Services/' . rawurlencode($serviceSid) . '/Verifications';
        $form = ['To'=>$phone, 'Channel'=>$channel];
        if ($channel === 'sna') $form['EnableSnaClientToken'] = 'false';
        return self::twilioRequest($url, $form);
    }

    private static function twilioCheck(string $verificationSid, string $code, string $channel): array
    {
        $serviceSid = trim((string)Config::get('auth.mobile.twilio.service_sid', ''));
        if ($serviceSid === '') self::providerFailure('Twilio Verify service SID is not configured.', ['stage'=>'config']);
        $url = 'https://verify.twilio.com/v2/Services/' . rawurlencode($serviceSid) . '/VerificationCheck';
        $form = ['VerificationSid'=>$verificationSid];
        if ($channel !== 'sna') $form['Code'] = $code;
        return self::twilioRequest($url, $form);
    }

    private static function twilioRequest(string $url, array $form): array
    {
        $key = trim((string)Config::get('auth.mobile.twilio.api_key_sid', ''));
        $secret = (string)Config::get('auth.mobile.twilio.api_key_secret', '');
        if ($key === '' || $secret === '') {
            $key = trim((string)Config::get('auth.mobile.twilio.account_sid', ''));
            $secret = (string)Config::get('auth.mobile.twilio.auth_token', '');
        }
        if ($key === '' || $secret === '') self::providerFailure('Twilio API credentials are not configured.', ['stage'=>'config']);
        if (!function_exists('curl_init')) self::providerFailure('PHP cURL is unavailable for mobile verification.', ['stage'=>'runtime']);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($form, '', '&', PHP_QUERY_RFC3986),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $key . ':' . $secret,
            CURLOPT_HTTPHEADER => ['Accept: application/json','Content-Type: application/x-www-form-urlencoded'],
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false || $err !== '') {
            self::providerFailure('Twilio transport failure: ' . ($err !== '' ? $err : 'empty response'), ['stage'=>'transport']);
        }
        $data = json_decode((string)$body, true);
        if (!is_array($data)) $data = [];
        if ($status < 200 || $status >= 300) {
            $providerCode=(int)($data['code'] ?? 0);
            if ($providerCode === 60519) throw new MobileAuthPendingException('Automatic verification is still processing.');
            $message = trim((string)($data['message'] ?? ''));
            self::providerFailure(
                'Twilio Verify HTTP '.$status.($providerCode?' code '.$providerCode:'').($message!==''?': '.$message:''),
                ['stage'=>'http','http_status'=>$status,'provider_code'=>$providerCode ?: null]
            );
        }
        return $data;
    }

    private static function providerFailure(string $internalMessage, array $meta = []): never
    {
        try { ErrorCenter::record(new \RuntimeException($internalMessage), ['component'=>'mobile_auth','provider'=>'twilio_verify']+$meta); } catch (\Throwable) {}
        throw new \RuntimeException('Mobile verification is temporarily unavailable. Please try again.');
    }
}
