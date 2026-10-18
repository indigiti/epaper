<?php
/**
 * Compatibility-preserving controller action seam. The ePaper front controller
 * retains route dispatch; actions are composed into it until typed controllers
 * can be introduced behind the same route contract.
 */
namespace EpaperSaas\Controllers;
use EpaperSaas\Core\{Auth,Build,Config,Http,RateLimiter,RedisStore,RequestContext,Store,Uuid,View};
use EpaperSaas\Services\{AnalyticsCatalog,Audit,CacheInspector,ClipService,DomainResolver,DirectRecovery,EditionOperations,EditionManifest,Entitlements,InfrastructureConfig,Media,DesktopAuth,DeviceAuth,MobileAuth,MobileAuthPendingException,MultiLayerCache,OfflineVault,PlatformClient,Processing,ProcessingRecovery,ProductionReadiness,PublicReadCache,PublishingRuntime,PublishingScheduler,QueueService,RecoveryCenter,RabbitMq,ReaderNavigation,ReaderSession,RuntimeCache,SchemaDoctor,SemanticNews,SeoDiscovery,SettingsCatalog,SupervisorRuntime,TenantData,TrustedAppAuth,PerformanceTelemetry};

trait ApiActions
{
    private function apiMobileAuthBootstrap(): never
    {
        Http::json([
            'ok'=>true,
            'enabled'=>MobileAuth::enabled(),
            'channels'=>MobileAuth::channels(true),
            'device_fallback'=>DeviceAuth::enabled(),
            'device_ttl_days'=>DeviceAuth::ttlDays(),
            'default_country_code'=>(string)Config::get('auth.mobile.default_country_code','+91'),
            'csrf'=>Http::csrf(),
            'authenticated'=>Auth::user()!==null&&Auth::sessionAccessActive(),
            'auth_source'=>(string)(Auth::identity()['auth_source']??''),
            'desktop_login_enabled'=>DesktopAuth::enabled(),
            'trusted_app_approval'=>DesktopAuth::appApprovalEnabled(),
            'request_guid'=>Http::requestGuid(),
        ]);
    }

    private function apiPerformanceVitals(): never
    {
        $origin=trim((string)($_SERVER['HTTP_ORIGIN']??''));
        if($origin!==''){$host=parse_url($origin,PHP_URL_HOST);if(!is_string($host)||strcasecmp($host,Http::host())!==0)Http::json(['ok'=>false,'code'=>'ORIGIN_DENIED'],403);}
        if((int)($_SERVER['CONTENT_LENGTH']??0)>4096)Http::json(['ok'=>false,'code'=>'PAYLOAD_TOO_LARGE'],413);
        PerformanceTelemetry::recordVitals(Http::bodyJson());Http::json(['ok'=>true],202);
    }

    private function apiMobileAuthStart(): never
    {
        Http::verifyCsrf();$d=Http::bodyJson();
        try{
            $started=MobileAuth::start((string)($d['phone']??''),(string)($d['channel']??'sms'));
            $_SESSION['mobile_auth_challenges'][(string)$started['challenge_guid']]=['expires'=>time()+MobileAuth::CHALLENGE_TTL,'masked'=>$started['phone_masked']??'','channel'=>$started['channel']??''];
            Http::json(['ok'=>true]+$started+['request_guid'=>Http::requestGuid()]);
        }catch(\InvalidArgumentException $e){Http::json(['ok'=>false,'code'=>'MOBILE_AUTH_INVALID','message'=>$e->getMessage(),'request_guid'=>Http::requestGuid()],422);}
        catch(\Throwable $e){$m=$e->getMessage();$status=str_contains(strtolower($m),'too many')?429:503;Http::json(['ok'=>false,'code'=>'MOBILE_AUTH_START_FAILED','message'=>$m,'request_guid'=>Http::requestGuid()],$status);}
    }

    private function apiMobileAuthVerify(): never
    {
        Http::verifyCsrf();$d=Http::bodyJson();$challenge=trim((string)($d['challenge_guid']??''));
        if($challenge===''||!isset($_SESSION['mobile_auth_challenges'][$challenge]))Http::json(['ok'=>false,'code'=>'MOBILE_AUTH_CHALLENGE_INVALID','message'=>'This verification request is no longer active.','request_guid'=>Http::requestGuid()],410);
        try{
            $isAndroid=str_contains((string)($_SERVER['HTTP_USER_AGENT']??''),'EPDI-Android/');$sessionKind=$isAndroid?ReaderSession::MOBILE:ReaderSession::DESKTOP;
            $result=MobileAuth::verify($challenge,(string)($d['code']??''),(string)($d['installation_id']??''),$sessionKind);unset($_SESSION['mobile_auth_challenges'][$challenge]);
            $trusted=null;if($isAndroid&&!empty($d['installation_id'])&&!empty($d['device_secret'])){$trusted=TrustedAppAuth::registerCurrent((string)$d['installation_id'],(string)$d['device_secret'],(string)($d['push_token']??''),(string)($d['device_label']??'Android device'),'ANDROID');}
            Http::json(['ok'=>true,'authenticated'=>true,'redirect'=>'/','user'=>$result['user']??null,'trusted_device'=>$trusted,'request_guid'=>Http::requestGuid()]);
        }catch(\InvalidArgumentException $e){Http::json(['ok'=>false,'code'=>'MOBILE_AUTH_INVALID','message'=>$e->getMessage(),'request_guid'=>Http::requestGuid()],422);}
        catch(MobileAuthPendingException $e){Http::json(['ok'=>false,'code'=>'MOBILE_AUTH_PENDING','message'=>'Automatic verification is still processing.','retry_after_ms'=>900,'request_guid'=>Http::requestGuid()],409);}
        catch(\Throwable $e){$m=$e->getMessage();$status=str_contains(strtolower($m),'too many')?429:401;Http::json(['ok'=>false,'code'=>'MOBILE_AUTH_VERIFY_FAILED','message'=>$m,'request_guid'=>Http::requestGuid()],$status);}
    }

    private function apiDeviceAuth(): never
    {
        Http::verifyCsrf();$d=Http::bodyJson();
        try{
            $result=DeviceAuth::authenticate((string)($d['installation_id']??''));
            Http::json(['ok'=>true,'authenticated'=>true,'temporary'=>true,'expires_at'=>$result['expires_at']??null,'created'=>(bool)($result['created']??false),'user'=>$result['user']??null,'redirect'=>'/','request_guid'=>Http::requestGuid()]);
        }catch(\InvalidArgumentException $e){Http::json(['ok'=>false,'code'=>'DEVICE_AUTH_INVALID','message'=>$e->getMessage(),'request_guid'=>Http::requestGuid()],422);}
        catch(\Throwable $e){$m=$e->getMessage();$status=str_contains(strtolower($m),'too many')?429:403;Http::json(['ok'=>false,'code'=>'DEVICE_AUTH_FAILED','message'=>$m,'request_guid'=>Http::requestGuid()],$status);}
    }

    private function apiTrustedRegister(): never
    {
        Http::verifyCsrf();$d=Http::bodyJson();
        try{$result=TrustedAppAuth::registerCurrent((string)($d['installation_id']??''),(string)($d['device_secret']??''),(string)($d['push_token']??''),(string)($d['device_label']??'Android device'),'ANDROID');Http::json(['ok'=>true]+$result+['request_guid'=>Http::requestGuid()]);}
        catch(\InvalidArgumentException $e){Http::json(['ok'=>false,'code'=>'TRUSTED_DEVICE_INVALID','message'=>$e->getMessage(),'request_guid'=>Http::requestGuid()],422);}catch(\Throwable $e){Http::json(['ok'=>false,'code'=>'TRUSTED_DEVICE_FAILED','message'=>$e->getMessage(),'request_guid'=>Http::requestGuid()],403);}
    }

    private function apiTrustedPushToken(): never
    {
        $d=Http::bodyJson();try{$ok=TrustedAppAuth::updatePushToken((string)($d['device_guid']??''),(string)($d['device_secret']??''),(string)($d['push_token']??''));Http::json(['ok'=>$ok,'request_guid'=>Http::requestGuid()],$ok?200:422);}catch(\Throwable $e){Http::json(['ok'=>false,'code'=>'TRUSTED_DEVICE_FAILED','message'=>'Trusted device update failed.','request_guid'=>Http::requestGuid()],403);}
    }

    private function apiTrustedPending(): never
    {
        $d=Http::bodyJson();try{$challenge=DesktopAuth::pendingForDevice((string)($d['device_guid']??''),(string)($d['device_secret']??''));Http::json(['ok'=>true,'challenge'=>$challenge,'request_guid'=>Http::requestGuid()]);}catch(\Throwable $e){Http::json(['ok'=>false,'code'=>'TRUSTED_DEVICE_FAILED','message'=>'Trusted device authentication failed.','request_guid'=>Http::requestGuid()],403);}
    }

    private function apiTrustedDecision(): never
    {
        $d=Http::bodyJson();try{$result=DesktopAuth::decision((string)($d['device_guid']??''),(string)($d['device_secret']??''),(string)($d['challenge_guid']??''),(string)($d['decision']??''));Http::json(['ok'=>true]+$result+['request_guid'=>Http::requestGuid()]);}catch(\InvalidArgumentException $e){Http::json(['ok'=>false,'code'=>'DESKTOP_AUTH_INVALID','message'=>$e->getMessage(),'request_guid'=>Http::requestGuid()],422);}catch(\Throwable $e){Http::json(['ok'=>false,'code'=>'DESKTOP_AUTH_FAILED','message'=>'Desktop login approval failed.','request_guid'=>Http::requestGuid()],403);}
    }

    private function apiDesktopAuthStart(): never
    {
        Http::verifyCsrf();$d=Http::bodyJson();try{Http::json(['ok'=>true]+DesktopAuth::start((string)($d['phone']??''))+['request_guid'=>Http::requestGuid()]);}catch(\InvalidArgumentException $e){Http::json(['ok'=>false,'code'=>'DESKTOP_AUTH_INVALID','message'=>$e->getMessage(),'request_guid'=>Http::requestGuid()],422);}catch(\Throwable $e){$m=$e->getMessage();Http::json(['ok'=>false,'code'=>'DESKTOP_AUTH_START_FAILED','message'=>$m,'request_guid'=>Http::requestGuid()],str_contains(strtolower($m),'too many')?429:503);}
    }

    private function apiDesktopAuthStatus(): never
    {
        Http::verifyCsrf();$challenge=(string)($_GET['challenge']??'');$result=DesktopAuth::browserStatus($challenge);Http::json(['ok'=>true]+$result+['request_guid'=>Http::requestGuid()]);
    }

    private function analytics(): never
    {
        $ip=hash('sha256',(string)($_SERVER['REMOTE_ADDR']??'unknown'));if(!RateLimiter::allow('analytics:'.RequestContext::tenantGuid().':'.$ip,300,300))Http::json(['ok'=>false,'code'=>'RATE_LIMITED'],429);$d=Http::bodyJson();$event=(string)($d['event']??'');if(!AnalyticsCatalog::valid($event))Http::json(['ok'=>false,'code'=>'ANALYTICS_EVENT_UNKNOWN','message'=>'Unknown analytics event'],422);$u=Auth::user();$m=$d['meta']??[];$edition=$m['edition_guid']??null;$page=$m['page_guid']??null;Store::exec('INSERT INTO ep_analytics_events(analytics_event_guid,tenant_guid,platform_user_guid,edition_guid,page_guid,event_name,request_guid,metadata_json,occurred_at) VALUES(?,?,?,?,?,?,?,?,NOW(6))',[Uuid::toBinary(Uuid::v7()),TenantData::tenantBin(),$u?Uuid::toBinary($u['user_guid']):null,$edition?Uuid::toBinary($edition):null,$page?Uuid::toBinary($page):null,$event,Uuid::toBinary(Http::requestGuid()),json_encode($m,JSON_UNESCAPED_SLASHES)]);Http::json(['ok'=>true]);
    }

    private function apiClip(): never
    {
        Http::verifyCsrf();if((string)TenantData::setting('clipping.enabled','on')==='off')Http::json(['ok'=>false,'code'=>'CLIPPING_DISABLED','message'=>'Clipping is not available for this publication'],403);$d=Http::bodyJson();$u=Auth::user();if(!$u){if($this->guestClipUsed()){Audit::log('guest_clip.limit_reached','clip',null,'DENIED',['reason'=>'guest_quota_used']);Http::json(['ok'=>false,'code'=>'GUEST_CLIP_LIMIT','message'=>'You have already created your free clipping. Sign in to create more.','login_url'=>$this->guestClipLoginUrl(),'subscribe_url'=>$this->clippingSubscribeUrl()],403);} $ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');if(!RateLimiter::allow('guest-clip-ip:'.RequestContext::tenantGuid().':'.hash('sha256',$ip),3,86400))Http::json(['ok'=>false,'code'=>'GUEST_CLIP_RATE_LIMIT','message'=>'Guest clipping is temporarily limited on this network. Sign in to continue.','login_url'=>$this->guestClipLoginUrl()],429);try{$c=ClipService::create((string)($d['page_guid']??''),(float)($d['x']??0),(float)($d['y']??0),(float)($d['width']??0),(float)($d['height']??0),isset($d['title'])?(string)$d['title']:null,'PUBLIC');$this->guestClipMarkUsed((string)$c['slug']);Audit::log('guest_clip.created','clip',(string)$c['clip_guid'],'SUCCESS',['expires_at'=>$c['expires_at']??null,'quota_remaining'=>0]);Http::json(['ok'=>true,'guest'=>true,'quota_remaining'=>0]+$c);}catch(\Throwable$e){Http::json(['ok'=>false,'code'=>'CLIP_INVALID','message'=>$e->getMessage()],422);}}
        if(!Entitlements::has('EPAPER.CLIPPING',true)){Audit::log('clip.entitlement.required','clip',null,'DENIED',['platform_user_guid'=>$u['user_guid']??null]);Http::json(['ok'=>false,'code'=>'ENTITLEMENT_REQUIRED','message'=>'Clipping is not included in your current subscription.','subscribe_url'=>$this->clippingSubscribeUrl()],403);}try{$c=ClipService::create((string)($d['page_guid']??''),(float)($d['x']??0),(float)($d['y']??0),(float)($d['width']??0),(float)($d['height']??0),isset($d['title'])?(string)$d['title']:null,(string)($d['visibility']??'PRIVATE'));Http::json(['ok'=>true]+$c);}catch(\Throwable$e){Http::json(['ok'=>false,'code'=>'CLIP_INVALID','message'=>$e->getMessage()],422);}
    }

    private function apiBookmark(): never
    {
        Http::verifyCsrf();$u=Auth::requireUser();$d=Http::bodyJson();$type=(string)($d['type']??'page');$target=(string)($d['target_guid']??'');if(!preg_match('/^[0-9a-f-]{36}$/i',$target))Http::json(['ok'=>false,'code'=>'TARGET_INVALID'],422);$existing=Store::one('SELECT bookmark_guid FROM ep_bookmarks WHERE tenant_guid=? AND platform_user_guid=? AND bookmark_type=? AND target_guid=?',[TenantData::tenantBin(),Uuid::toBinary($u['user_guid']),$type,Uuid::toBinary($target)]);if($existing){Store::exec('DELETE FROM ep_bookmarks WHERE tenant_guid=? AND bookmark_guid=?',[TenantData::tenantBin(),$existing['bookmark_guid']]);Audit::log('bookmark.removed','bookmark',$this->guid($existing['bookmark_guid']));Http::json(['ok'=>true,'bookmarked'=>false]);}$g=Uuid::v7();Store::exec('INSERT INTO ep_bookmarks(bookmark_guid,tenant_guid,platform_user_guid,bookmark_type,target_guid,created_at) VALUES(?,?,?,?,?,NOW(6))',[Uuid::toBinary($g),TenantData::tenantBin(),Uuid::toBinary($u['user_guid']),$type,Uuid::toBinary($target)]);Audit::log('bookmark.created','bookmark',$g);Http::json(['ok'=>true,'bookmarked'=>true,'bookmark_guid'=>$g]);
    }

    private function apiSavedArticle(): never
    {
        Http::verifyCsrf();$u=Auth::requireUser();$d=Http::bodyJson();$page=(string)($d['page_guid']??'');$text=trim((string)($d['text']??''));if(!$page||!$text)Http::json(['ok'=>false,'code'=>'ARTICLE_INVALID'],422);$exists=Store::one('SELECT page_guid FROM ep_pages WHERE tenant_guid=? AND page_guid=?',[TenantData::tenantBin(),Uuid::toBinary($page)]);if(!$exists)Http::json(['ok'=>false,'code'=>'PAGE_NOT_FOUND'],404);$g=Uuid::v7();Store::exec('INSERT INTO ep_saved_articles(saved_article_guid,tenant_guid,platform_user_guid,page_guid,title,text_snapshot,created_at) VALUES(?,?,?,?,?,?,NOW(6))',[Uuid::toBinary($g),TenantData::tenantBin(),Uuid::toBinary($u['user_guid']),Uuid::toBinary($page),substr(trim((string)($d['title']??'Saved Article')),0,255),$text]);Audit::log('article.saved','saved_article',$g);Http::json(['ok'=>true,'saved_article_guid'=>$g]);
    }

    private function apiOfflineLegacy(): never
    {
        Http::verifyCsrf();Auth::requireUser();Http::json(['ok'=>false,'code'=>'OFFLINE_VAULT_REQUIRED','message'=>'Encrypted Offline Vault is required. Refresh the Reader and try again.'],409);
    }

    private function apiOfflineDevice(): never
    {
        Http::verifyCsrf();$u=Auth::requireUser();if(!RateLimiter::allow('offline-device:'.RequestContext::tenantGuid().':'.$u['user_guid'],10,300))Http::json(['ok'=>false,'code'=>'RATE_LIMITED'],429);try{$device=OfflineVault::registerDevice($u,Http::bodyJson());Audit::log('offline.device.registered','offline_device',$device['device_guid'],'SUCCESS',['label'=>$device['label']]);Http::json(['ok'=>true,'device'=>$device]);}catch(\InvalidArgumentException $e){Http::json(['ok'=>false,'code'=>'DEVICE_INVALID','message'=>$e->getMessage()],422);}catch(\Throwable $e){Http::json(['ok'=>false,'code'=>'DEVICE_REGISTER_FAILED','message'=>$e->getMessage()],400);}
    }

    private function apiOfflineLicense(): never
    {
        Http::verifyCsrf();$u=Auth::requireUser();if(!RateLimiter::allow('offline-license:'.RequestContext::tenantGuid().':'.$u['user_guid'],30,300))Http::json(['ok'=>false,'code'=>'RATE_LIMITED'],429);$d=Http::bodyJson();try{$result=OfflineVault::createLicense($u,(string)($d['edition_guid']??''),(string)($d['device_guid']??''));Audit::log('offline.license.issued','edition',(string)($d['edition_guid']??''),'SUCCESS',['device_guid'=>$d['device_guid']??null,'license_guid'=>$result['license']['license_guid']??null]);PlatformClient::instance()->usage(RequestContext::tenantGuid(),'EPAPER.OFFLINE',1);Http::json($result);}catch(\InvalidArgumentException $e){Http::json(['ok'=>false,'code'=>'OFFLINE_INVALID','message'=>$e->getMessage()],422);}catch(\Throwable $e){Http::json(['ok'=>false,'code'=>'OFFLINE_LICENSE_FAILED','message'=>$e->getMessage()],400);}
    }

    private function apiOfflinePage(string $licenseGuid,int $pageNumber): never
    {
        $u=Auth::requireUser();if(!RateLimiter::allow('offline-page:'.RequestContext::tenantGuid().':'.$u['user_guid'],600,300))Http::json(['ok'=>false,'code'=>'RATE_LIMITED'],429);$device=trim((string)($_SERVER['HTTP_X_EPDI_DEVICE']??''));try{$page=OfflineVault::encryptedPage($u,$licenseGuid,$pageNumber,$device);header('Content-Type: application/octet-stream');header('Content-Length: '.strlen($page['cipher']));header('Cache-Control: private, no-store, max-age=0');header('Pragma: no-cache');header('X-EPDI-IV: '.$page['iv']);header('X-EPDI-Tag: '.$page['tag']);header('X-EPDI-Mime: '.$page['mime']);header('X-EPDI-Plain-Bytes: '.$page['plain_bytes']);header('X-EPDI-Expires: '.$page['expires_at']);echo $page['cipher'];exit;}catch(\Throwable $e){Http::json(['ok'=>false,'code'=>'OFFLINE_PAGE_FAILED','message'=>$e->getMessage()],403);}
    }

    private function apiOfflineComplete(string $licenseGuid): never
    {
        Http::verifyCsrf();$u=Auth::requireUser();if(!RateLimiter::allow('offline-complete:'.RequestContext::tenantGuid().':'.$u['user_guid'],60,300))Http::json(['ok'=>false,'code'=>'RATE_LIMITED'],429);$d=Http::bodyJson();try{$r=OfflineVault::complete($u,$licenseGuid,(string)($d['device_guid']??''),(int)($d['downloaded_pages']??0),(int)($d['byte_size']??0));Audit::log('offline.download.completed','offline_license',$licenseGuid,'SUCCESS',$r);Http::json($r);}catch(\Throwable $e){Http::json(['ok'=>false,'code'=>'OFFLINE_COMPLETE_FAILED','message'=>$e->getMessage()],400);}
    }

    private function apiProgress(): never
    {
        $u=Auth::user();if(!$u)Http::json(['ok'=>true,'guest'=>true]);if(!RateLimiter::allow('progress:'.RequestContext::tenantGuid().':'.$u['user_guid'],300,300))Http::json(['ok'=>false,'code'=>'RATE_LIMITED'],429);$d=Http::bodyJson();$ed=(string)($d['edition_guid']??'');$pg=(string)($d['page_guid']??'');$n=(int)($d['page_number']??0);if(!$ed||!$pg||$n<1)Http::json(['ok'=>false,'code'=>'PROGRESS_INVALID'],422);Store::exec('INSERT INTO ep_history(history_guid,tenant_guid,platform_user_guid,edition_guid,page_guid,page_number,last_viewed_at,created_at) VALUES(?,?,?,?,?,?,NOW(6),NOW(6)) ON DUPLICATE KEY UPDATE page_guid=VALUES(page_guid),page_number=VALUES(page_number),last_viewed_at=NOW(6)',[Uuid::toBinary(Uuid::v7()),TenantData::tenantBin(),Uuid::toBinary($u['user_guid']),Uuid::toBinary($ed),Uuid::toBinary($pg),$n]);RedisStore::setJson('reader:resume:v2:'.RequestContext::tenantGuid().':'.$u['user_guid'],['edition_guid'=>$ed,'page_guid'=>$pg,'page_number'=>$n,'updated_at'=>gmdate('c')],RedisStore::ttlWithJitter(300,30));Http::json(['ok'=>true]);
    }

    private function apiSearch(): never{$ip=hash('sha256',(string)($_SERVER['REMOTE_ADDR']??'unknown'));if(!RateLimiter::allow('search:'.RequestContext::tenantGuid().':'.$ip,120,60))Http::json(['ok'=>false,'code'=>'RATE_LIMITED'],429);$q=trim((string)($_GET['q']??''));Http::json(['ok'=>true,'results'=>$q?$this->searchRows($q,30):[]]);}

    private function healthReady(): never
    {
        $checks=$this->healthChecks(false);$ok=!in_array(false,array_column($checks,'ok'),true);Http::json(['ok'=>$ok,'status'=>$ok?'READY':'DEGRADED','version'=>$this->version(),'checks'=>$checks,'request_guid'=>Http::requestGuid()],$ok?200:503);
    }

    private function healthChecks(bool $platformTenant=true): array
    {
        $c=[];try{Store::value('SELECT 1');$c['MariaDB']=['ok'=>true,'detail'=>'Connected'];}catch(\Throwable$e){$c['MariaDB']=['ok'=>false,'detail'=>$e->getMessage()];}$r=\EpaperSaas\Core\RedisStore::enabled();try{$ping=$r?\EpaperSaas\Core\RedisStore::client()?->ping():null;$c['Redis']=['ok'=>!$r||$ping!==false,'detail'=>$r?'Connected':'Disabled'];}catch(\Throwable$e){$c['Redis']=['ok'=>false,'detail'=>$e->getMessage()];}try{$ph=PlatformClient::instance()->health();$c['Digiti Platform']=['ok'=>(bool)($ph['ok']??false),'detail'=>$ph['status']??'Unavailable'];}catch(\Throwable$e){$c['Digiti Platform']=['ok'=>false,'detail'=>$e->getMessage()];}$root=dirname(__DIR__,2);$c['Storage']=['ok'=>is_writable($root.'/storage'),'detail'=>is_writable($root.'/storage')?'Writable':'Not writable'];foreach(['pdftoppm','tesseract'] as$cmd)$c[$cmd]=$this->commandCheck($cmd);$c['Imagick']=['ok'=>class_exists('Imagick'),'detail'=>class_exists('Imagick')?'Available':'PHP Imagick unavailable'];if(QueueService::rabbitEnabled()){$q=RabbitMq::instance()->ping();$c['RabbitMQ']=$q;if($platformTenant){$o=QueueService::outboxBacklog();$queueOk=(int)($o['failed_actionable']??0)===0&&(int)($o['missing_dispatch_receipt']??0)===0&&(int)($o['oldest_pending_seconds']??0)<=300;$c['Queue Outbox']=['ok'=>$queueOk,'detail'=>(int)$o['pending'].' pending · '.(int)($o['failed_actionable']??0).' actionable failed · '.(int)($o['failed_closeable']??0).' historical · '.(int)($o['missing_dispatch_receipt']??0).' missing receipt'];}else{$last=Store::value('SELECT state_value FROM ep_system_state WHERE state_key="queue.outbox_last_run"');$lr=$last?json_decode((string)$last,true):null;$c['Queue Outbox']=['ok'=>true,'detail'=>is_array($lr)?((int)($lr['published']??0).' published · '.(int)($lr['failed']??0).' failed on last run'):'Awaiting first publisher heartbeat'];}}return$c;
    }

    private function commandCheck(string $cmd): array
    {
        if(!function_exists('exec'))return['ok'=>false,'detail'=>'PHP exec() is disabled'];
        $out=[];$code=127;
        try{exec('command -v '.escapeshellarg($cmd).' 2>/dev/null',$out,$code);}catch(\Throwable$e){return['ok'=>false,'detail'=>'Command check unavailable: '.$e->getMessage()];}
        return['ok'=>$code===0,'detail'=>$code===0?'Available':'Not installed'];
    }
}
