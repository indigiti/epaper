<?php
namespace EpaperSaas\Core;
use EpaperSaas\Services\{DesktopAuth,DeviceAuth,MobileAuth,PlatformClient,ReaderSession};
final class Auth
{
    private static bool $sessionConfigured=false;
    private static bool $sessionLoaded=false;
    private static function configureSession(): void
    {
        if(self::$sessionConfigured)return;
        @ini_set('session.use_strict_mode','1');@ini_set('session.use_only_cookies','1');
        session_name('epaper_saas');
        session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Lax','path'=>'/']);
        self::$sessionConfigured=true;
    }
    public static function hasSessionCookie(): bool
    {
        self::configureSession();
        return session_status()===PHP_SESSION_ACTIVE||trim((string)($_COOKIE[session_name()]??''))!=='';
    }
    public static function start(): void
    {
        if(session_status()===PHP_SESSION_ACTIVE)return;
        self::configureSession();session_start();self::$sessionLoaded=true;
    }
    public static function startIfPresent(): bool
    {
        if(session_status()===PHP_SESSION_ACTIVE||self::$sessionLoaded)return true;
        if(!self::hasSessionCookie())return false;
        self::start();return true;
    }
    public static function close(): void
    {
        if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
    }
    public static function user(): ?array
    {
        if(!self::startIfPresent())return null;
        return $_SESSION['identity']['user']??null;
    }
    public static function identity(): ?array
    {
        if(!self::startIfPresent())return null;
        return $_SESSION['identity']??null;
    }
    public static function login(string $method): never
    {
        self::start();Http::privateNoStore();
        $return=self::safeReturn((string)(($method==='POST'?($_POST['return']??''):($_GET['return']??''))?:'/account'));
        if(self::user()&&self::sessionAccessActive())Http::redirect($return);
        $error='';$email='';
        if($method==='POST'){
            if(!Http::csrfValid()){$error='Your sign-in page expired. Please refresh and try again.';Http::rotateCsrf();$email=strtolower(trim((string)($_POST['email']??'')));$password='';$totp='';}else{$email=strtolower(trim((string)($_POST['email']??'')));$password=(string)($_POST['password']??'');$totp=trim((string)($_POST['totp']??''));$ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');$bucket='login:'.$ip.':'.hash('sha256',$email);
            if(!RateLimiter::allow($bucket,10,300)){$error='Too many sign-in attempts. Please try again in a few minutes.';}
            elseif($email===''||$password===''){$error='Enter your email address and password.';}
            else{try{$identity=PlatformClient::instance()->directAuth(RequestContext::tenantGuid(),$email,$password,$totp);if(empty($identity['user']['user_guid']))throw new \RuntimeException('IDENTITY_INVALID');self::establish($identity);Http::redirect($return);}catch(\Throwable $e){$error=in_array($e->getMessage(),['Too many sign-in attempts. Try again later.'],true)?$e->getMessage():'Unable to sign in with those credentials.';}}
            $password='';$totp='';}
        }
        $brand=(string)(RequestContext::get('tenant_name')?:'ePaper SaaS');$body='<section class="auth-shell"><div class="auth-panel"><div class="auth-brand"><span class="brand-mark">E</span><span>'.$brand.'</span></div><span class="eyebrow">SIGN IN</span><h1>Welcome back</h1><p class="muted">Readers can continue with a mobile number. Publisher and admin identities use Digiti credentials.</p><p><a class="btn primary auth-submit" href="/desktop-login?return='.rawurlencode($return).'">Approve with EP-DI app</a></p><p><a class="btn ghost auth-submit" href="/mobile-login?return='.rawurlencode($return).'">Use mobile OTP</a></p><div class="auth-divider"><span>Publisher / admin</span></div>'.($error?'<div class="alert auth-error">'.View::e($error).'</div>':'').'<form method="post" action="/login" class="auth-form">'.'<input type="hidden" name="_csrf" value="'.View::e(Http::csrf()).'"><input type="hidden" name="return" value="'.View::e($return).'"><label>Email address<input type="email" name="email" value="'.View::e($email).'" autocomplete="username" inputmode="email" required autofocus></label><label>Password<input type="password" name="password" autocomplete="current-password" required></label><label>Authenticator code <span class="muted">(if enabled)</span><input name="totp" autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]*"></label><button class="btn ghost auth-submit" type="submit">Publisher / admin sign in</button></form><p class="auth-security">Reader mobile accounts stay local to this ePaper. Publisher/admin authentication remains verified by Digiti Platform.</p></div></section>';View::layout('Sign in',$body,['brand'=>$brand,'auth'=>true]);
    }
    public static function mobileLogin(string $method): never
    {
        self::start();Http::privateNoStore();
        $return=self::safeReturn((string)(($method==='POST'?($_POST['return']??''):($_GET['return']??''))?:'/'));
        if(self::user()&&self::sessionAccessActive())Http::redirect($return);
        $error=((string)($_GET['disabled']??'')==='1'?'This reader account is disabled. Contact the publisher for access.':'');$phone=trim((string)($_POST['phone']??($_SESSION['desktop_login_phone']??'')));$challenge=trim((string)($_POST['challenge_guid']??''));$stage='start';$masked='';$channel='';
        if($method==='POST'){
            if(!Http::csrfValid()){$error='Your verification page expired. Please refresh and try again.';Http::rotateCsrf();}
            else{
                $action=(string)($_POST['action']??'start');
                try{
                    if($action==='verify'){
                        if($challenge===''||!isset($_SESSION['mobile_auth_challenges'][$challenge]))throw new \RuntimeException('This verification request is no longer active.');
                        $meta=$_SESSION['mobile_auth_challenges'][$challenge];$stage='verify';if(is_array($meta)){$masked=(string)($meta['masked']??'');$channel=(string)($meta['channel']??'');}
                        MobileAuth::verify($challenge,(string)($_POST['code']??''),'',ReaderSession::DESKTOP);unset($_SESSION['mobile_auth_challenges'][$challenge]);Http::redirect($return);
                    }
                    $channel=strtolower((string)($_POST['channel']??'sms'));
                    if($channel==='sna')throw new \RuntimeException('Automatic carrier verification is available in the Android app. Choose WhatsApp or SMS in the browser.');
                    $started=MobileAuth::start($phone,$channel);$challenge=(string)$started['challenge_guid'];$masked=(string)$started['phone_masked'];$channel=(string)$started['channel'];$_SESSION['mobile_auth_challenges'][$challenge]=['expires'=>time()+MobileAuth::CHALLENGE_TTL,'masked'=>$masked,'channel'=>$channel];$stage='verify';
                }catch(\Throwable $e){$error=$e->getMessage();}
            }
        }
        $brand=(string)(RequestContext::get('tenant_name')?:'ePaper SaaS');
        if(!MobileAuth::enabled()){$body='<section class="auth-shell"><div class="auth-panel"><div class="auth-brand"><span class="brand-mark">E</span><span>'.View::e($brand).'</span></div><span class="eyebrow">MOBILE SIGN IN</span><h1>Mobile sign-in is not configured</h1><p class="muted">The publisher must enable a mobile verification provider before local reader signup can be used.</p><a class="btn ghost" href="/login?return='.rawurlencode($return).'">Publisher / admin sign in</a></div></section>';View::layout('Mobile sign in',$body,['brand'=>$brand,'auth'=>true]);}
        if($stage==='verify'){
            $body='<section class="auth-shell"><div class="auth-panel"><div class="auth-brand"><span class="brand-mark">E</span><span>'.View::e($brand).'</span></div><span class="eyebrow">VERIFY MOBILE</span><h1>Enter the code</h1><p class="muted">We sent a code to '.View::e($masked).' by '.View::e(strtoupper($channel)).'.</p>'.($error?'<div class="alert auth-error">'.View::e($error).'</div>':'').'<form method="post" action="/mobile-login" class="auth-form"><input type="hidden" name="_csrf" value="'.View::e(Http::csrf()).'"><input type="hidden" name="action" value="verify"><input type="hidden" name="challenge_guid" value="'.View::e($challenge).'"><input type="hidden" name="return" value="'.View::e($return).'"><label>Verification code<input name="code" autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]*" required autofocus></label><button class="btn primary auth-submit" type="submit">Verify & continue</button></form><p><a href="/mobile-login?return='.rawurlencode($return).'">Use another number</a></p></div></section>';View::layout('Verify mobile',$body,['brand'=>$brand,'auth'=>true]);
        }
        $channels=MobileAuth::channels(false);$buttons='';if(in_array('whatsapp',$channels,true))$buttons.='<button class="btn primary auth-submit" name="channel" value="whatsapp" type="submit">Continue with WhatsApp</button>';if(in_array('sms',$channels,true))$buttons.='<button class="btn ghost auth-submit" name="channel" value="sms" type="submit">Send code by SMS</button>';
        $body='<section class="auth-shell"><div class="auth-panel"><div class="auth-brand"><span class="brand-mark">E</span><span>'.View::e($brand).'</span></div><span class="eyebrow">FAST READER SIGN IN</span><h1>Continue with mobile</h1><p class="muted">New numbers create a local reader account automatically. Existing numbers sign in to the same account.</p>'.($error?'<div class="alert auth-error">'.View::e($error).'</div>':'').'<form method="post" action="/mobile-login" class="auth-form"><input type="hidden" name="_csrf" value="'.View::e(Http::csrf()).'"><input type="hidden" name="action" value="start"><input type="hidden" name="return" value="'.View::e($return).'"><label>Mobile number<input type="tel" name="phone" value="'.View::e($phone).'" autocomplete="tel" inputmode="tel" placeholder="+91 98765 43210" required autofocus></label>'.$buttons.'</form><p class="auth-security">Your ePaper reader account is stored locally. Publisher and admin access remains controlled separately by Digiti Platform.</p><p><a href="/login?return='.rawurlencode($return).'">Publisher / admin sign in</a></p></div></section>';View::layout('Mobile sign in',$body,['brand'=>$brand,'auth'=>true]);
    }

    public static function desktopLogin(string $method): never
    {
        self::start();Http::privateNoStore();$return=self::safeReturn((string)(($method==='POST'?($_POST['return']??''):($_GET['return']??''))?:'/'));
        if(self::user()&&self::sessionAccessActive())Http::redirect($return);
        $brand=(string)(RequestContext::get('tenant_name')?:'ePaper SaaS');$error='';$waiting=null;$phone=trim((string)($_POST['phone']??($_SESSION['desktop_login_phone']??'')));
        if(!DesktopAuth::enabled()){$body='<section class="auth-shell"><div class="auth-panel"><div class="auth-brand"><span class="brand-mark">E</span><span>'.View::e($brand).'</span></div><span class="eyebrow">DESKTOP SIGN IN</span><h1>Desktop reader login is disabled</h1><p class="muted">Use the available reader sign-in method instead.</p><a class="btn primary" href="/mobile-login?return='.rawurlencode($return).'">Continue with mobile</a></div></section>';View::layout('Desktop sign in',$body,['brand'=>$brand,'auth'=>true]);}
        if($method==='POST'){
            if(!Http::csrfValid()){$error='Your sign-in page expired. Please refresh and try again.';Http::rotateCsrf();}
            else{try{$waiting=DesktopAuth::start($phone);}catch(\Throwable $e){$error=$e->getMessage();}}
        }
        if(is_array($waiting)&&($waiting['status']??'')==='WAITING_FOR_APP'){
            $challenge=(string)$waiting['challenge_guid'];$code=(string)$waiting['display_code'];$fallback=(array)($waiting['fallback_channels']??[]);$fallbackHtml='';
            if(in_array('whatsapp',$fallback,true))$fallbackHtml.='<a class="btn ghost" href="/mobile-login?return='.rawurlencode($return).'">Use WhatsApp instead</a>';
            if(in_array('sms',$fallback,true))$fallbackHtml.='<a class="btn ghost" href="/mobile-login?return='.rawurlencode($return).'">Use SMS instead</a>';
            $body='<section class="auth-shell"><div class="auth-panel"><div class="auth-brand"><span class="brand-mark">E</span><span>'.View::e($brand).'</span></div><span class="eyebrow">APP APPROVAL</span><h1>Check your EP-DI app</h1><p class="muted">Approve the desktop login request on your trusted phone. Confirm that both screens show this code.</p><div style="font-size:34px;font-weight:800;letter-spacing:.18em;text-align:center;margin:20px 0">'.View::e($code).'</div><p class="muted">'.View::e((string)($waiting['client_summary']??'Desktop browser')).' · expires in '.(int)ceil(((int)$waiting['expires_in'])/60).' minutes</p><div id="desktop-login-state" class="alert">Waiting for app approval…</div>'.$fallbackHtml.'<a class="btn ghost" href="/desktop-login?return='.rawurlencode($return).'">Cancel</a></div></section><script>(function(){const id='.json_encode($challenge).',csrf='.json_encode(Http::csrf()).',ret='.json_encode($return).';let stopped=false;async function poll(){if(stopped)return;try{const r=await fetch("/api/v1/auth/desktop/status?challenge="+encodeURIComponent(id),{headers:{"X-CSRF-Token":csrf,"Accept":"application/json"},credentials:"same-origin"});const d=await r.json();const el=document.getElementById("desktop-login-state");if(d.status==="AUTHENTICATED"){stopped=true;el.textContent="Approved. Signing you in…";location.href=ret;return;}if(["DENIED","EXPIRED","INVALID","CANCELLED"].includes(d.status)){stopped=true;el.textContent=d.status==="DENIED"?"Login denied in the app.":"This login request expired or is no longer active.";return;}el.textContent="Waiting for app approval…";}catch(e){}if(!stopped)setTimeout(poll,1800);}poll();})();</script>';
            View::layout('Approve desktop login',$body,['brand'=>$brand,'auth'=>true]);
        }
        if(is_array($waiting)&&($waiting['status']??'')==='FALLBACK_AVAILABLE'){
            $fallback=(array)($waiting['fallback_channels']??[]);$links='';if(in_array('whatsapp',$fallback,true))$links.='<a class="btn primary" href="/mobile-login?return='.rawurlencode($return).'">Verify by WhatsApp</a>';if(in_array('sms',$fallback,true))$links.='<a class="btn ghost" href="/mobile-login?return='.rawurlencode($return).'">Verify by SMS</a>';
            $error='No trusted EP-DI app is registered for this number.'.($links===''?' Contact the publisher or sign in from the mobile app first.':'');if($links!=='')$error.=' '.$links;
        }
        $body='<section class="auth-shell"><div class="auth-panel"><div class="auth-brand"><span class="brand-mark">E</span><span>'.View::e($brand).'</span></div><span class="eyebrow">DESKTOP SIGN IN</span><h1>Sign in with your mobile</h1><p class="muted">If your verified EP-DI app is registered, a secure Approve/Deny request will appear on your phone. Only one desktop session can remain active.</p>'.($error?'<div class="alert auth-error">'.$error.'</div>':'').'<form method="post" action="/desktop-login" class="auth-form"><input type="hidden" name="_csrf" value="'.View::e(Http::csrf()).'"><input type="hidden" name="return" value="'.View::e($return).'"><label>Mobile number<input type="tel" name="phone" value="'.View::e($phone).'" autocomplete="tel" inputmode="tel" placeholder="+91 98765 43210" required autofocus></label><button class="btn primary auth-submit" type="submit">Send request to EP-DI app</button></form><p><a href="/mobile-login?return='.rawurlencode($return).'">Use mobile OTP instead</a></p><p><a href="/login?return='.rawurlencode($return).'">Publisher / admin sign in</a></p></div></section>';View::layout('Desktop sign in',$body,['brand'=>$brand,'auth'=>true]);
    }

    public static function loginRedirect(): never
    {
        self::start();$verifier=rtrim(strtr(base64_encode(random_bytes(48)),'+/','-_'),'=');$challenge=rtrim(strtr(base64_encode(hash('sha256',$verifier,true)),'+/','-_'),'=');$state=bin2hex(random_bytes(20));$_SESSION['oauth_verifier']=$verifier;$_SESSION['oauth_state']=$state;$_SESSION['oauth_return']=self::safeReturn((string)($_GET['return']??'/account'));$redirect=Http::origin().'/auth/callback';$url=rtrim((string)Config::get('platform.url','https://platform.digiti.in'),'/').'/auth/authorize?'.http_build_query(['client_id'=>Config::get('platform.oauth_client_id'),'redirect_uri'=>$redirect,'state'=>$state,'code_challenge'=>$challenge,'code_challenge_method'=>'S256']);Http::redirect($url);
    }
    public static function callback(): never
    {
        self::start();if(!hash_equals((string)($_SESSION['oauth_state']??''),(string)($_GET['state']??'')))Http::json(['ok'=>false,'code'=>'OAUTH_STATE_INVALID'],400);$identity=PlatformClient::instance()->exchangeAuth((string)($_GET['code']??''),(string)($_SESSION['oauth_verifier']??''));if(empty($identity['user']['user_guid']))Http::json(['ok'=>false,'code'=>'IDENTITY_INVALID'],400);self::establish($identity);unset($_SESSION['oauth_state'],$_SESSION['oauth_verifier']);$ret=self::safeReturn((string)($_SESSION['oauth_return']??'/account'));unset($_SESSION['oauth_return']);Http::redirect($ret);
    }

    public static function establishLocalMobile(array $user,string $phone,string $sessionKind=ReaderSession::DESKTOP,?string $deviceGuid=null): void
    {
        self::establish(['user'=>$user,'memberships'=>[],'auth_source'=>'LOCAL_MOBILE','verified_phone'=>$phone]);
        ReaderSession::bind((string)$user['user_guid'],$sessionKind,$deviceGuid);
    }
    public static function establishLocalDevice(array $user,string $deviceHash): void
    {
        self::establish(['user'=>$user,'memberships'=>[],'auth_source'=>'LOCAL_DEVICE','device_hash_suffix'=>substr($deviceHash,-8),'temporary'=>true]);
    }

    private static function establish(array $identity): void
    {
        self::start();session_regenerate_id(true);Http::rotateCsrf();$_SESSION['identity']=$identity;$_SESSION['identity_authenticated_at']=time();self::syncIdentity($identity['user']);self::syncSubscriber($identity['user']);
    }
    private static function safeReturn(string $return): string
    {
        $return=trim($return);if($return===''||!str_starts_with($return,'/')||str_starts_with($return,'//')||str_contains($return,"\r")||str_contains($return,"\n"))return'/account';return$return;
    }
    private static function syncSubscriber(array $u): void
    {
        try{$tenant=RequestContext::get('tenant_guid');if(!$tenant)return;$tb=Uuid::toBinary($tenant);$ub=Uuid::toBinary($u['user_guid']);$row=Store::one('SELECT subscriber_guid FROM ep_subscribers WHERE tenant_guid=? AND platform_user_guid=?',[$tb,$ub]);if(!$row)Store::exec('INSERT INTO ep_subscribers(subscriber_guid,tenant_guid,platform_user_guid,status,created_at,updated_at) VALUES(?,?,?,"ACTIVE",NOW(6),NOW(6))',[Uuid::toBinary(Uuid::v7()),$tb,$ub]);}catch(\Throwable){}
    }
    public static function logout(): void{self::start();try{ReaderSession::revokeCurrent();}catch(\Throwable){}$_SESSION=[];if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);}session_destroy();unset($_COOKIE[session_name()]);self::$sessionLoaded=false;}
    private static function syncIdentity(array $u): void
    {
        try{$bin=Uuid::toBinary($u['user_guid']);$row=Store::one('SELECT platform_user_guid FROM ep_identities WHERE platform_user_guid=?',[$bin]);if($row)Store::exec('UPDATE ep_identities SET email=?,display_name=?,status=?,last_seen_at=NOW(6),updated_at=NOW(6) WHERE platform_user_guid=?',[$u['email']??null,$u['display_name']??null,$u['status']??'ACTIVE',$bin]);else Store::exec('INSERT INTO ep_identities(platform_user_guid,email,display_name,identity_source,status,last_seen_at,created_at,updated_at) VALUES(?,?,?,"PLATFORM",?,NOW(6),NOW(6),NOW(6))',[$bin,$u['email']??null,$u['display_name']??null,$u['status']??'ACTIVE']);}catch(\Throwable){}
    }
    public static function sessionAccessActive(): bool
    {
        $identity=self::identity();$u=$identity['user']??null;if(!$u)return false;
        $source=strtoupper((string)($identity['auth_source']??'PLATFORM'));
        try{
            $tenant=RequestContext::get('tenant_guid');
            if(!$tenant||empty($u['user_guid'])){
                if(in_array($source,['LOCAL_MOBILE','LOCAL_DEVICE'],true)){self::logout();return false;}
                return true;
            }
            $tb=Uuid::toBinary((string)$tenant);$ub=Uuid::toBinary((string)$u['user_guid']);
            if($source==='LOCAL_MOBILE'){
                $row=Store::one(
                    'SELECT a.status account_status,s.status subscriber_status,i.status identity_status FROM ep_local_mobile_accounts a LEFT JOIN ep_subscribers s ON s.tenant_guid=a.tenant_guid AND s.platform_user_guid=a.user_guid LEFT JOIN ep_identities i ON i.platform_user_guid=a.user_guid WHERE a.tenant_guid=? AND a.user_guid=? LIMIT 1',
                    [$tb,$ub]
                );
                $active=$row
                    && strtoupper((string)($row['account_status']??''))==='ACTIVE'
                    && strtoupper((string)($row['subscriber_status']??''))==='ACTIVE'
                    && strtoupper((string)($row['identity_status']??''))==='ACTIVE';
                if(!$active||!ReaderSession::validateOrBind((string)$u['user_guid'])){self::logout();return false;}
                return true;
            }
            if($source==='LOCAL_DEVICE'){
                $row=Store::one(
                    'SELECT a.status account_status,a.expires_at,s.status subscriber_status,i.status identity_status FROM ep_local_device_accounts a LEFT JOIN ep_subscribers s ON s.tenant_guid=a.tenant_guid AND s.platform_user_guid=a.user_guid LEFT JOIN ep_identities i ON i.platform_user_guid=a.user_guid WHERE a.tenant_guid=? AND a.user_guid=? LIMIT 1',
                    [$tb,$ub]
                );
                $active=$row
                    && strtoupper((string)($row['account_status']??''))==='ACTIVE'
                    && strtotime((string)($row['expires_at']??''))>=time()
                    && strtoupper((string)($row['subscriber_status']??''))==='ACTIVE'
                    && strtoupper((string)($row['identity_status']??''))==='ACTIVE';
                if(!$active){self::logout();return false;}
                return true;
            }
            $row=Store::one('SELECT status FROM ep_subscribers WHERE tenant_guid=? AND platform_user_guid=? LIMIT 1',[$tb,$ub]);
            if($row&&strtoupper((string)$row['status'])!=='ACTIVE'){self::logout();return false;}
        }catch(\Throwable){
            // Local mobile access is fail-closed. Platform identities remain usable
            // during a local subscriber-store fault so publisher/admin access is not lost.
            if(in_array($source,['LOCAL_MOBILE','LOCAL_DEVICE'],true)){self::logout();return false;}
        }
        return true;
    }
    public static function requireUser(): array{$u=self::user();if(!$u){Http::redirect('/login?return='.rawurlencode($_SERVER['REQUEST_URI']??'/account'));}if(!self::sessionAccessActive())Http::redirect('/mobile-login?disabled=1');return $u;}
    public static function currentMembership(): ?array
    {
        $id=self::identity();if(!$id)return null;$org=RequestContext::organisationGuid();foreach(($id['memberships']??[]) as$m)if(($m['organisation_guid']??'')===$org&&($m['status']??'ACTIVE')==='ACTIVE')return$m;return null;
    }
    public static function requireAdmin(): array
    {
        self::requireUser();$m=self::currentMembership();$roles=Config::get('auth.admin_roles',['OWNER','ADMIN','PUBLISHER_ADMIN','EDITOR','OPERATOR']);if(!$m||!in_array(strtoupper((string)($m['role_key']??'')),$roles,true)){http_response_code(403);View::layout('Access denied','<section class="empty-state"><h1>Access denied</h1><p>Your Digiti membership does not grant access to this publisher dashboard.</p></section>');}RequestContext::set(RequestContext::all()+['membership_guid'=>$m['membership_guid']??null,'role_key'=>$m['role_key']??null,'user_guid'=>self::user()['user_guid']??null]);return$m;
    }
    public static function canInfrastructureAdmin(): bool
    {
        $m=self::currentMembership();if(!$m)return false;$canonical=parse_url((string)Config::get('app.url',''),PHP_URL_HOST);if(!$canonical||strcasecmp((string)$canonical,Http::host())!==0)return false;$roles=Config::get('auth.infrastructure_roles',['OWNER','ADMIN']);return in_array(strtoupper((string)($m['role_key']??'')),$roles,true);
    }

    public static function requireInfrastructureAdmin(): array
    {
        $m=self::requireAdmin();
        $canonical=parse_url((string)Config::get('app.url',''),PHP_URL_HOST);
        if(!$canonical||strcasecmp((string)$canonical,Http::host())!==0){http_response_code(403);View::layout('Access denied','<section class="empty-state"><h1>Infrastructure access denied</h1><p>Application infrastructure can only be managed from the canonical ePaper SaaS hostname.</p></section>',['brand'=>RequestContext::get('tenant_name')?:'ePaper SaaS','admin'=>true]);}
        $roles=Config::get('auth.infrastructure_roles',['OWNER','ADMIN']);
        if(!in_array(strtoupper((string)($m['role_key']??'')),$roles,true)){http_response_code(403);View::layout('Access denied','<section class="empty-state"><h1>Infrastructure access denied</h1><p>Your publisher role cannot modify shared application infrastructure.</p></section>',['brand'=>RequestContext::get('tenant_name')?:'ePaper SaaS','admin'=>true]);}
        return $m;
    }

}
