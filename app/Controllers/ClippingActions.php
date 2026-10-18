<?php
/** Route-action compatibility seam, based on cw-epaper AI-enabled source. */
namespace EpaperSaas\Controllers;
use EpaperSaas\Core\{Auth,Build,Config,Http,RateLimiter,RedisStore,RequestContext,Store,Uuid,View};
use EpaperSaas\Services\{AnalyticsCatalog,Audit,CacheInspector,ClipService,DomainResolver,DirectRecovery,EditionOperations,EditionManifest,EditionPageSequence,EditionRemoval,AiEditorialReview,Entitlements,InfrastructureConfig,Media,DesktopAuth,DeviceAuth,MobileAuth,MobileAuthPendingException,MultiLayerCache,OfflineVault,PlatformClient,Processing,ProcessingRecovery,ProductionReadiness,PublicReadCache,PublishingRuntime,PublishingScheduler,QueueService,RecoveryCenter,RabbitMq,ReaderNavigation,ReaderSession,RuntimeCache,SchemaDoctor,SemanticNews,SeoDiscovery,SettingsCatalog,SupervisorRuntime,TenantData,TrustedAppAuth,PerformanceTelemetry};
trait ClippingActions
{
    private function guestClipSession(): void
    {
        Auth::start();
    }

    private function guestClipUsed(): bool
    {
        if((string)($_COOKIE['epaper_guest_clip_used']??'')==='1')return true;
        if(!Auth::hasSessionCookie())return false;
        $this->guestClipSession();return !empty($_SESSION['epaper_guest_clip_used']);
    }

    private function guestClipMarkUsed(string $slug): void
    {
        $this->guestClipSession();$_SESSION['epaper_guest_clip_used']=1;$_SESSION['epaper_guest_clip_slug']=$slug;$_SESSION['epaper_guest_clip_created_at']=time();$secure=!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off';setcookie('epaper_guest_clip_used','1',['expires'=>time()+ClipService::EXPIRY_DAYS*86400,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
    }

    private function guestClipLoginUrl(string $return=''): string
    {
        $return=$return!==''?$return:(string)(parse_url((string)($_SERVER['REQUEST_URI']??'/'),PHP_URL_PATH)?:'/');return '/login?return='.rawurlencode($return);
    }

    private function clippingSubscribeUrl(): string
    {
        // Digiti Platform is a background control-plane only. Never expose its UI/login to readers.
        $configured=trim((string)TenantData::setting('clipping.subscribe_url',''));
        if($configured==='')return '/subscribe';
        if(str_starts_with($configured,'/'))return $configured;
        $host=(string)parse_url($configured,PHP_URL_HOST);$path=(string)(parse_url($configured,PHP_URL_PATH)?:'/subscribe');
        if($host!==''&&strcasecmp($host,Http::host())===0)return $path;
        return '/subscribe';
    }

    private function claimGuestClipAfterLogin(): void
    {
        $u=Auth::user();if(!$u||empty($u['user_guid']))return;$this->guestClipSession();$slug=preg_replace('/[^a-zA-Z0-9_-]+/','',(string)($_SESSION['epaper_guest_clip_slug']??''));if($slug==='')return;try{$clip=ClipService::bySlug($slug);if(!$clip||!empty($clip['platform_user_guid'])||(!empty($clip['expires_at'])&&strtotime((string)$clip['expires_at'])<time())){unset($_SESSION['epaper_guest_clip_slug']);return;}$changed=Store::exec('UPDATE ep_clips SET platform_user_guid=?,updated_at=NOW(6) WHERE tenant_guid=? AND clip_guid=? AND platform_user_guid IS NULL',[Uuid::toBinary((string)$u['user_guid']),TenantData::tenantBin(),Uuid::toBinary((string)$clip['clip_guid'])]);if($changed){Audit::log('guest_clip.claimed','clip',(string)$clip['clip_guid'],'SUCCESS',['guest_created'=>true,'platform_user_guid'=>$u['user_guid']]);unset($_SESSION['epaper_guest_clip_slug']);}}catch(\Throwable $e){Audit::log('guest_clip.claim.failed','clip',null,'FAILED',['clip_slug'=>$slug,'message'=>$e->getMessage()]);}
    }

    private function clipOr404(string $slug): array
    {
        $clip=(new \EpaperSaas\Services\ClipVisibilityService(new \EpaperSaas\Services\ClipServiceReader()))->visible($slug);
        if(!$clip)$this->notFound('Clip unavailable');
        return $clip;
    }

        private function publicClipImage(string $slug): never
    {
        $c=$this->clipOr404($slug);if(strtoupper((string)$c['visibility'])==='PRIVATE')Http::privateNoStore();$full=ClipService::previewPath($c);if(!is_file($full))$this->notFound('Clip image unavailable');header('Content-Type: image/webp');header('Content-Length: '.filesize($full));header('Content-Disposition: inline; filename="epaper-clip-'.$this->e($slug).'.webp"');header('Cache-Control: '.(strtoupper((string)$c['visibility'])==='PRIVATE'?'private, no-store':'public, max-age=3600'));readfile($full);exit;
    }

    private function clipDownload(string $slug,string $format): never
    {
        $c=$this->clipOr404($slug);if(strtoupper((string)$c['visibility'])==='PRIVATE')Http::privateNoStore();try{$file=ClipService::download($c,$format);}catch(\Throwable $e){$this->notFound($e->getMessage());}ClipService::track($c,'clip_download',['format'=>$format]);header('Content-Type: '.$file['mime']);header('Content-Length: '.filesize($file['path']));header('Content-Disposition: attachment; filename="'.$file['filename'].'"');header('Cache-Control: private, no-store');readfile($file['path']);exit;
    }

    private function publicClip(string $slug): never
    {
        $c=$this->clipOr404($slug);$private=strtoupper((string)$c['visibility'])==='PRIVATE';$guestClip=empty($c['platform_user_guid']);Http::privateNoStore();Store::exec('UPDATE ep_clips SET view_count=view_count+1 WHERE tenant_guid=? AND clip_guid=?',[TenantData::tenantBin(),Uuid::toBinary($c['clip_guid'])]);$c['view_count']=(int)$c['view_count']+1;
        $stats=ClipService::stats($c);$formats=ClipService::formatStatus($c);$manage=ClipService::canManage($c);$url=Http::origin().'/c/'.$slug;$encodedUrl=rawurlencode($url);$shareText=rawurlencode(($c['title']?:'Newspaper clipping').' · '.$c['publication_name']);$ready=0;$supported=0;foreach($formats as$f){if($f['supported']){$supported++;if($f['ready'])$ready++;}}$pct=$supported?round($ready*100/$supported):100;
        $logo=trim((string)TenantData::setting('brand.logo_path',''));$brand=$this->brandName();$sourceUrl=ClipService::sourcePageUrl($c);$visibility=$private?'PRIVATE':'PUBLIC';
        $formatIcons=['original'=>'▧','pdf'=>'PDF','instagram-portrait'=>'IG','instagram-landscape'=>'IG','facebook'=>'f','x'=>'X','whatsapp'=>'WA','webp'=>'W','avif'=>'A'];$downloadCards='';foreach($formats as$key=>$f){$cls=$f['ready']?'ready':($f['supported']?'preparing':'unsupported');$downloadCards.='<a class="clip-format-card '.$cls.'" data-clip-format="'.$this->e($key).'" href="'.($f['ready']?$this->e($f['download']):'#').'"'.($f['ready']?' download':' aria-disabled="true"').'><span class="clip-format-icon">'.$this->e($formatIcons[$key]??'↓').'</span><strong>'.$this->e($f['label']).'</strong><small data-format-state>'.($f['ready']?'Ready':($f['supported']?'Preparing…':'Unavailable')).'</small><span class="clip-format-download">'.View::icon('download').'</span></a>';}
        $formatsJson=htmlspecialchars(json_encode($formats,JSON_UNESCAPED_SLASHES),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $guestClaim=$guestClip?'<section class="guest-claim-banner"><div><span class="eyebrow">YOUR FREE CLIPPING</span><strong>Sign in to keep this clipping in your Library</strong><p>This guest clipping remains available for 7 days. After sign-in it will be attached to your account automatically.</p></div><a class="btn primary" href="'.$this->e($this->guestClipLoginUrl('/c/'.$slug)).'">Sign in &amp; save</a></section>':'';$html=$guestClaim.'<section class="clip-showcase" data-clip-page data-clip-slug="'.$this->e($slug).'" data-clip-url="'.$this->e($url).'" data-can-manage="'.($manage?'1':'0').'" data-clip-formats="'.$formatsJson.'">'
        .'<div class="clip-preview-column"><div class="clip-preview-card"><div class="clip-preview-brand">'.($logo?'<img src="'.$this->e($logo).'" alt="">':'<span class="clip-brand-mark">'.strtoupper($this->e(mb_substr($brand,0,1))).'</span>').'<div><strong>'.$this->e($brand).'</strong><span>'.$this->e($c['publication_name']).'</span></div><div class="clip-preview-meta"><b>'.$this->e(date('d M Y',strtotime((string)$c['edition_date']))).'</b><span>Page '.(int)$c['page_number'].'</span></div></div><div class="clip-preview-image"><img src="/c/'.$this->e($slug).'/image.webp" alt="'.$this->e($c['title']?:'Newspaper clipping').'" decoding="async"></div><div class="clip-preview-footer"><div><span class="eyebrow">SOURCE EDITION</span><strong>'.$this->e($c['publication_name']).'</strong></div><span>'.$this->e(date('Y-m-d',strtotime((string)$c['edition_date']))).' · Page '.(int)$c['page_number'].'</span></div></div></div>'
        .'<aside class="clip-control-panel"><div class="clip-control-head"><div class="clip-badges"><span class="clip-visibility-badge '.strtolower($visibility).'">'.$visibility.' CLIP</span><span class="clip-ready-badge"><i></i>Ready</span></div><h1>'.$this->e($c['title']?:'News clipping').'</h1><a class="clip-source-context" href="'.$this->e($sourceUrl).'"><span>Source page</span><strong>'.$this->e($c['publication_name']).' · '.$this->e(date('d M Y',strtotime((string)$c['edition_date']))).' · Page '.(int)$c['page_number'].'</strong></a></div>'
        .'<div class="clip-stat-grid"><div><span class="stat-icon">◉</span><strong data-clip-views>'.(int)$stats['views'].'</strong><small>Views</small></div><div><span class="stat-icon">↗</span><strong data-clip-shares>'.(int)$stats['shares'].'</strong><small>Shares</small></div><div><span class="stat-icon">↓</span><strong data-clip-downloads>'.(int)$stats['downloads'].'</strong><small>Downloads</small></div></div>'
        .'<section class="clip-panel-section"><span class="eyebrow">SHARE</span><h2>Share this clipping</h2><div class="clip-share-grid"><a data-clip-share="whatsapp" target="_blank" rel="noopener" href="https://wa.me/?text='.$shareText.'%20'.$encodedUrl.'"><span class="share-icon whatsapp">WA</span><strong>WhatsApp</strong></a><a data-clip-share="facebook" target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u='.$encodedUrl.'"><span class="share-icon facebook">f</span><strong>Facebook</strong></a><a data-clip-share="x" target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?url='.$encodedUrl.'&text='.$shareText.'"><span class="share-icon x">X</span><strong>X</strong></a><a data-clip-share="telegram" target="_blank" rel="noopener" href="https://t.me/share/url?url='.$encodedUrl.'&text='.$shareText.'"><span class="share-icon telegram">➤</span><strong>Telegram</strong></a><button type="button" data-clip-copy><span class="share-icon copy">▣</span><strong>Copy link</strong></button></div></section>'
        .'<section class="clip-format-progress" data-format-progress><div class="format-progress-head"><div><span class="format-progress-icon">'.View::icon('download').'</span><div><strong>Share formats ready</strong><small data-format-summary>'.$ready.' of '.$supported.' formats ready</small></div></div><b data-format-percent>'.$pct.'%</b></div><div class="format-progress-bar"><span data-format-bar style="width:'.$pct.'%"></span></div><div class="format-progress-foot"><span data-format-message>'.($pct>=100?'Ready to share':'Preparing optimized formats…').'</span><span>'.($manage?'Keep this page open while preparation finishes':($guestClip?'Sign in to unlock all prepared formats':'Prepared by the clipping owner')).'</span></div></section>'
        .'<section class="clip-panel-section clip-download-section"><span class="eyebrow">DOWNLOAD</span><div class="clip-section-title"><h2>Choose a format</h2><small>Optimized for each platform</small></div><div class="clip-format-grid">'.$downloadCards.'</div></section>'
        .'<section class="clip-qr-section"><div><span class="qr-icon">⌗</span><div><strong>Scan to open</strong><p>'.($private?'Sign in with the clipping owner account on another device.':'Share this clipping from any device.').'</p></div></div><div class="clip-qr" data-clip-qr data-url="'.$this->e($url).'" aria-label="QR code for clipping"></div></section>'
        .'<div class="clip-panel-footer"><a href="/edition/'.$this->e($c['edition_slug']).'/page/'.(int)$c['page_number'].'">→ Open original page</a><span>'.$this->e($url).'</span></div></aside></section>';
        $clipSocial=$private?'':Http::origin().'/c/'.rawurlencode($slug).'/image.webp';View::layout('Clipping · '.$c['title'],$html,['brand'=>$brand,'og_type'=>'article','og_title'=>$c['title']?:'News clipping','og_description'=>$c['publication_name'].' · '.date('d M Y',strtotime((string)$c['edition_date'])).' · Page '.(int)$c['page_number'],'canonical_url'=>$url,'social_image'=>$clipSocial,'social_image_alt'=>($c['title']?:'News clipping').' · '.$c['publication_name'],'robots'=>$guestClip?'noindex,nofollow':null]);
    }

    private function clipFormatPrepare(string $slug,string $format): never
    {
        Http::verifyCsrf();$c=ClipService::bySlug($slug);if(!$c||!ClipService::canManage($c))Http::json(['ok'=>false,'code'=>'CLIP_FORBIDDEN'],403);try{$one=ClipService::prepareFormat($c,$format);Http::json(['ok'=>true,'format'=>$one,'formats'=>ClipService::formatStatus($c)]);}catch(\Throwable $e){Http::json(['ok'=>false,'code'=>'CLIP_FORMAT_FAILED','message'=>$e->getMessage()],422);}
    }

    private function clipShareTrack(string $slug): never
    {
        Http::verifyCsrf();$c=$this->clipOr404($slug);$d=Http::bodyJson();$platform=preg_replace('/[^a-z0-9_-]+/i','',strtolower((string)($d['platform']??'share')));if(RateLimiter::allow('clip-share:'.RequestContext::tenantGuid().':'.$slug.':'.($_SERVER['REMOTE_ADDR']??'unknown'),30,3600))ClipService::track($c,'clip_share',['platform'=>$platform]);Http::json(['ok'=>true,'stats'=>ClipService::stats($c)]);
    }
}
