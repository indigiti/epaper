<?php
namespace EpaperSaas;
use EpaperSaas\Core\{Auth,Build,Config,Http,RateLimiter,RedisStore,RequestContext,Store,Uuid,View};
use EpaperSaas\Services\{AnalyticsCatalog,Audit,CacheInspector,ClipService,DomainResolver,DirectRecovery,EditionOperations,EditionManifest,EditionPageSequence,EditionRemoval,AiEditorialReview,Entitlements,InfrastructureConfig,Media,DesktopAuth,DeviceAuth,MobileAuth,MobileAuthPendingException,MultiLayerCache,OfflineVault,PlatformClient,Processing,ProcessingRecovery,ProductionReadiness,PublicReadCache,PublishingRuntime,PublishingScheduler,QueueService,RecoveryCenter,RabbitMq,ReaderNavigation,ReaderSession,RuntimeCache,SchemaDoctor,SemanticNews,SeoDiscovery,SettingsCatalog,SupervisorRuntime,TenantData,TrustedAppAuth,PerformanceTelemetry};
final class App
{
    use \EpaperSaas\Controllers\ApiActions;
    use \EpaperSaas\Controllers\ClippingActions;
    use \EpaperSaas\Controllers\ReaderActions;
    use \EpaperSaas\Controllers\AccountActions;
    use \EpaperSaas\Controllers\PublishingActions;
    use \EpaperSaas\Controllers\AdministrationActions;
    use \EpaperSaas\Controllers\OperationsActions;
    use \EpaperSaas\Controllers\SupportActions;

    public function run(): never
    {
        $method=$_SERVER['REQUEST_METHOD']??'GET';$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';if($path==='/admin/')$path='/admin';
        if($path==='/login'||$path==='/mobile-login'||$path==='/desktop-login'||$path==='/auth/callback'||$path==='/logout'||str_starts_with($path,'/admin')||str_starts_with($path,'/account')||(str_starts_with($path,'/api/v1/')||str_starts_with($path,'/api/v2/')))Http::privateNoStore();
        if($path==='/health/live')Http::json(['ok'=>true,'status'=>'LIVE','product'=>'ePaper SaaS','version'=>$this->version(),'request_guid'=>Http::requestGuid()]);
        if($path==='/health/ready')$this->healthReady();
        if($path==='/health/platform')$this->platformHealth();
        if($path==='/health/queue')$this->queueHealth();
        try{DomainResolver::resolve();}catch(\Throwable$e){if(DomainResolver::isCanonicalHost())$this->canonicalSetupRequired($e);throw$e;}
        // Signed newspaper media is tenant-bound by the token itself. Dispatch it before
        // opening/revalidating any reader session so parallel image requests do not
        // serialize on PHP session locks or run subscriber validation queries.
        if(preg_match('#^/media/([A-Za-z0-9_.-]+)$#',$path,$m))Media::send($m[1]);
        // Signed newspaper media is tenant-bound by the token itself. Dispatch it before
        // opening/revalidating any reader session so parallel image requests do not
        // serialize on PHP session locks or run subscriber validation queries.
        if($path==='/api/v1/session/csrf'&&$method==='GET')Http::json(['ok'=>true,'csrf'=>Http::csrf(),'request_guid'=>Http::requestGuid()]);
        if($path==='/api/v1/performance/vitals'&&$method==='POST')$this->apiPerformanceVitals();
        // Revalidate only when an EP-DI session cookie actually exists. Anonymous public
        // traffic remains session-free and therefore cache/CDN friendly.
        if(Auth::user())Auth::sessionAccessActive();
        $this->hydrateIdentityContext();
        $this->claimGuestClipAfterLogin();
        if($method==='GET'&&Auth::user()&&!str_starts_with($path,'/admin')&&!str_starts_with($path,'/account')&&!str_starts_with($path,'/api/')&&!in_array($path,['/login','/mobile-login','/desktop-login','/auth/callback','/logout'],true))Auth::close();
        if($path==='/mobile-login')Auth::mobileLogin($method);
        if($path==='/desktop-login')Auth::desktopLogin($method);
        if($path==='/login')Auth::login($method);
        if($path==='/auth/callback')Auth::callback();
        if($path==='/logout'){Auth::logout();Http::redirect('/');}
        if(preg_match('#^/social/edition/([^/]+)/page/(\d+)\.webp$#',$path,$m))$this->socialPageThumb($m[1],(int)$m[2]);
        if(preg_match('#^/c/([a-zA-Z0-9_-]+)/download/([a-z0-9-]+)$#',$path,$m))$this->clipDownload($m[1],$m[2]);
        if(preg_match('#^/c/([a-zA-Z0-9_-]+)/image\.webp$#',$path,$m))$this->publicClipImage($m[1]);
        if(preg_match('#^/c/([a-zA-Z0-9_-]+)$#',$path,$m))$this->publicClip($m[1]);
        if($path==='/api/v1/auth/mobile/bootstrap'&&$method==='GET')$this->apiMobileAuthBootstrap();
        if($path==='/api/v1/auth/mobile/start'&&$method==='POST')$this->apiMobileAuthStart();
        if($path==='/api/v1/auth/mobile/verify'&&$method==='POST')$this->apiMobileAuthVerify();
        if($path==='/api/v1/auth/device'&&$method==='POST')$this->apiDeviceAuth();
        if($path==='/api/v1/auth/trusted/register'&&$method==='POST')$this->apiTrustedRegister();
        if($path==='/api/v1/auth/trusted/push-token'&&$method==='POST')$this->apiTrustedPushToken();
        if($path==='/api/v1/auth/trusted/pending'&&$method==='POST')$this->apiTrustedPending();
        if($path==='/api/v1/auth/trusted/decision'&&$method==='POST')$this->apiTrustedDecision();
        if($path==='/api/v1/auth/desktop/start'&&$method==='POST')$this->apiDesktopAuthStart();
        if($path==='/api/v1/auth/desktop/status'&&$method==='GET')$this->apiDesktopAuthStatus();
        if($path==='/api/v1/analytics/events'&&$method==='POST')$this->analytics();
        if($path==='/api/v1/clips'&&$method==='POST')$this->apiClip();
        if(preg_match('#^/api/v1/clips/([a-zA-Z0-9_-]+)/formats/([a-z0-9-]+)$#',$path,$m)&&$method==='POST')$this->clipFormatPrepare($m[1],$m[2]);
        if(preg_match('#^/api/v1/clips/([a-zA-Z0-9_-]+)/share$#',$path,$m)&&$method==='POST')$this->clipShareTrack($m[1]);
        if($path==='/api/v1/bookmarks'&&$method==='POST')$this->apiBookmark();if($path==='/api/v1/saved-articles'&&$method==='POST')$this->apiSavedArticle();if($path==='/api/v1/offline'&&$method==='POST')$this->apiOfflineLegacy();
        if($path==='/api/v2/offline/devices'&&$method==='POST')$this->apiOfflineDevice();
        if($path==='/api/v2/offline/licenses'&&$method==='POST')$this->apiOfflineLicense();
        if(preg_match('#^/api/v2/offline/licenses/([0-9a-f-]{36})/pages/(\d+)$#i',$path,$m)&&$method==='GET')$this->apiOfflinePage($m[1],(int)$m[2]);
        if(preg_match('#^/api/v2/offline/licenses/([0-9a-f-]{36})/complete$#i',$path,$m)&&$method==='POST')$this->apiOfflineComplete($m[1]);
        if($path==='/api/v1/progress'&&$method==='POST')$this->apiProgress();
        if(preg_match('#^/api/v1/reader/([^/]+)/page/(\d+)/state$#',$path,$m))$this->readerState($m[1],(int)$m[2]);
        if(preg_match('#^/api/v1/pages/([0-9a-f-]{36})/tiles$#i',$path,$m))$this->tileManifest($m[1]);
        if(preg_match('#^/api/v1/pages/([0-9a-f-]{36})/tiles/(\d+)/(\d+)/(\d+)\.webp$#i',$path,$m))$this->tile($m[1],(int)$m[2],(int)$m[3],(int)$m[4]);
        if(preg_match('#^/api/v1/pages/([0-9a-f-]{36})/article-at$#i',$path,$m))$this->articleAt($m[1]);
        if($path==='/api/v1/search')$this->apiSearch();
        if($path==='/robots.txt')$this->robotsTxt();
        if($path==='/sitemap.xml')$this->sitemap('index');
        if($path==='/sitemap-static.xml')$this->sitemap('static');
        if($path==='/sitemap-articles.xml')$this->sitemap('articles');
        if($path==='/sitemap-authors.xml')$this->sitemap('authors');
        if($path==='/sitemap-topics.xml')$this->sitemap('topics');
        if($path==='/sitemap-entities.xml')$this->sitemap('entities');
        if($path==='/sitemap-editions.xml')$this->sitemap('editions');
        if($path==='/news-sitemap.xml')$this->sitemap('news');
        if($path==='/trust')$this->trustCenter();
        if(in_array($path,['/masthead','/editorial-standards','/corrections-policy','/fact-checking-policy','/ethics-policy','/ai-policy','/advertising-policy','/ownership','/contact-newsroom'],true))$this->trustPolicy($path);
        if(preg_match('#^/article/([^/]+)/?$#',$path,$m))$this->article($m[1]);
        if(preg_match('#^/author/([^/]+)/?$#',$path,$m))$this->author($m[1]);
        if(preg_match('#^/topic/([^/]+)/?$#',$path,$m))$this->topic($m[1]);
        if(preg_match('#^/entity/([^/]+)/?$#',$path,$m))$this->entity($m[1]);
        if($path==='/subscribe')$this->publicSubscribe();
        if(str_starts_with($path,'/admin')){$this->runAdmin($path,$method);}
        if(str_starts_with($path,'/account')){$this->runAccount($path,$method);}
        if($path==='/')$this->home();
        if($path==='/archive')$this->archive();
        if($path==='/search')$this->search();
        if(preg_match('#^/epaper/([^/]+)/?$#',$path,$m))$this->publication($m[1]);
        if(preg_match('#^/epaper/([^/]+)/(\d{4}-\d{2}-\d{2})/?$#',$path,$m))$this->publicationDate($m[1],$m[2]);
        if(preg_match('#^/edition/([^/]+)/page/(\d+)/?$#',$path,$m))$this->reader($m[1],(int)$m[2]);
        http_response_code(404);View::layout('Not Found','<section class="empty-state"><h1>Page not found</h1><p>The requested ePaper page does not exist.</p><a class="btn primary" href="/">Go to today’s edition</a></section>',['brand'=>$this->brandName()]);
    }

}
