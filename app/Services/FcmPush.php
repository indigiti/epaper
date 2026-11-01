<?php
namespace EpaperSaas\Services;

use EpaperSaas\Core\Config;

final class FcmPush
{
    public static function configured(): bool
    {
        $project=trim((string)Config::get('auth.desktop.firebase.project_id',''));
        $file=trim((string)Config::get('auth.desktop.firebase.service_account_file',''));
        return $project!==''&&$file!==''&&is_file($file)&&function_exists('openssl_sign')&&function_exists('curl_init');
    }

    public static function sendDesktopChallenge(string $encryptedToken,array $challenge): string
    {
        if(!self::configured())return 'NOT_CONFIGURED';
        $token=self::decryptToken($encryptedToken);if($token==='')return 'TOKEN_MISSING';
        try{
            $project=trim((string)Config::get('auth.desktop.firebase.project_id',''));
            $access=self::accessToken();
            $url='https://fcm.googleapis.com/v1/projects/'.rawurlencode($project).'/messages:send';
            $code=(string)($challenge['display_code']??'');$client=(string)($challenge['client_summary']??'Desktop browser');
            $payload=['message'=>[
                'token'=>$token,
                'data'=>[
                    'type'=>'desktop_login','challenge_guid'=>(string)($challenge['challenge_guid']??''),'display_code'=>$code,
                    'client_summary'=>$client,'expires_at'=>(string)($challenge['expires_at']??''),
                ],
                'android'=>['priority'=>'high','notification'=>['channel_id'=>'epdi_login','tag'=>'epdi-desktop-login']],
            ]];
            $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>8,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$access,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_SLASHES)]);
            $body=(string)curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=(string)curl_error($ch);curl_close($ch);
            if($status>=200&&$status<300)return 'SENT';
            throw new \RuntimeException('FCM send failed HTTP '.$status.($err!==''?' '.$err:'').' '.substr($body,0,200));
        }catch(\Throwable $e){try{ErrorCenter::record($e,['component'=>'desktop_auth','provider'=>'fcm']);}catch(\Throwable){}return 'FAILED';}
    }

    public static function encryptToken(string $token): string
    {
        $token=trim($token);if($token==='')return'';if(!function_exists('openssl_encrypt'))return'';
        $key=hash('sha256',(string)Config::get('app.key','epdi-fcm'),true);$iv=random_bytes(12);$tag='';
        $cipher=openssl_encrypt($token,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,'EPDI-FCM',16);if($cipher===false)return'';
        return rtrim(strtr(base64_encode($iv.$tag.$cipher),'+/','-_'),'=');
    }

    public static function decryptToken(string $encoded): string
    {
        if($encoded===''||!function_exists('openssl_decrypt'))return'';$pad=strlen($encoded)%4;if($pad)$encoded.=str_repeat('=',4-$pad);
        $raw=base64_decode(strtr($encoded,'-_','+/'),true);if($raw===false||strlen($raw)<29)return'';
        $iv=substr($raw,0,12);$tag=substr($raw,12,16);$cipher=substr($raw,28);$key=hash('sha256',(string)Config::get('app.key','epdi-fcm'),true);
        $plain=openssl_decrypt($cipher,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,'EPDI-FCM');return is_string($plain)?$plain:'';
    }

    private static function accessToken(): string
    {
        $file=trim((string)Config::get('auth.desktop.firebase.service_account_file',''));$json=json_decode((string)file_get_contents($file),true);
        if(!is_array($json)||empty($json['client_email'])||empty($json['private_key']))throw new \RuntimeException('Firebase service account is invalid.');
        $now=time();$tokenUri=(string)($json['token_uri']??'https://oauth2.googleapis.com/token');
        $header=self::b64(json_encode(['alg'=>'RS256','typ'=>'JWT']));
        $claims=self::b64(json_encode(['iss'=>$json['client_email'],'scope'=>'https://www.googleapis.com/auth/firebase.messaging','aud'=>$tokenUri,'iat'=>$now,'exp'=>$now+3300],JSON_UNESCAPED_SLASHES));
        $input=$header.'.'.$claims;$signature='';if(!openssl_sign($input,$signature,$json['private_key'],OPENSSL_ALGO_SHA256))throw new \RuntimeException('Firebase JWT signing failed.');
        $assertion=$input.'.'.self::b64($signature);
        $ch=curl_init($tokenUri);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>8,CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'],CURLOPT_POSTFIELDS=>http_build_query(['grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$assertion])]);
        $body=(string)curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);$data=json_decode($body,true);
        if($status<200||$status>=300||!is_array($data)||empty($data['access_token']))throw new \RuntimeException('Firebase OAuth token request failed.');
        return (string)$data['access_token'];
    }

    private static function b64(string $value): string{return rtrim(strtr(base64_encode($value),'+/','-_'),'=');}
}
