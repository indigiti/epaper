<?php
namespace EpaperSaas\Core;
use EpaperSaas\Services\{ReaderNavigation,TenantData};

final class View
{
    public static function e(mixed $v): string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
    public static function icon(string $name): string
    {
        $p=['play'=>'<path d="m8 4 12 8-12 8Z"/>','stop'=>'<rect x="5" y="5" width="14" height="14" rx="2"/>',
            'menu'=>'<path d="M4 7h16M4 12h16M4 17h16"/>','search'=>'<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>','user'=>'<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-5 14.5-5 16 0"/>','login'=>'<path d="M10 5H5v14h5"/><path d="M13 8l4 4-4 4M8 12h9"/>','dashboard'=>'<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>','chev-left'=>'<path d="m15 18-6-6 6-6"/>','chev-right'=>'<path d="m9 18 6-6-6-6"/>','plus'=>'<path d="M12 5v14M5 12h14"/>','minus'=>'<path d="M5 12h14"/>','fit'=>'<path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/>','width'=>'<path d="M4 12h16M8 8l-4 4 4 4M16 8l4 4-4 4"/>','reset'=>'<path d="M4 4v6h6M5.5 15a7 7 0 1 0 .5-7"/>','clip'=>'<path d="M6 3v12a3 3 0 1 0 3 3V9a3 3 0 1 0-3-3Zm12-3v12a3 3 0 1 1-3 3V6a3 3 0 1 1 3-3Z"/>','save'=>'<path d="M5 3h12l2 2v16H5V3Z"/><path d="M8 3v6h8V3M8 21v-7h8v7"/>','close'=>'<path d="M6 6l12 12M18 6 6 18"/>','bookmark'=>'<path d="M6 3h12v18l-6-4-6 4V3Z"/>','share'=>'<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 10.5 6.8-4M8.6 13.5l6.8 4"/>','pages'=>'<path d="M4 5h12v16H4zM8 2h12v16"/>','signal'=>'<path d="M4 18h2M8 14h2v4H8zM12 10h2v8h-2zM16 6h2v12h-2zM20 3v15"/>','moon'=>'<path d="M20 15a8 8 0 1 1-11-11 7 7 0 0 0 11 11Z"/>','sun'=>'<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>','download'=>'<path d="M12 3v12M7 10l5 5 5-5M5 21h14"/>','fullscreen'=>'<path d="M4 9V4h5M15 4h5v5M20 15v5h-5M9 20H4v-5"/>','settings'=>'<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6V21h-4v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H3v-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3A1.7 1.7 0 0 0 10 3V3h4v.1a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9A1.7 1.7 0 0 0 21 10h.1v4H21a1.7 1.7 0 0 0-1.6 1Z"/>','eye'=>'<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/>','edit'=>'<path d="M4 20h4l11-11-4-4L4 16v4Z"/><path d="m13.5 6.5 4 4"/>','refresh'=>'<path d="M20 6v5h-5"/><path d="M19 11a7 7 0 1 0 1 5"/>','calendar'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>','clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>','activity'=>'<path d="M3 12h4l2-5 4 10 2-5h6"/>','archive'=>'<path d="M4 7h16v13H4z"/><path d="M3 4h18v3H3zM9 11h6"/>','trash'=>'<path d="M4 7h16"/><path d="M9 7V4h6v3"/><path d="m7 7 1 13h8l1-13"/><path d="M10 11v5M14 11v5"/>','restore'=>'<path d="M4 4v6h6"/><path d="M5.5 9A7.5 7.5 0 1 1 7 17.5"/>','more'=>'<circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/>','external'=>'<path d="M14 4h6v6M20 4l-9 9"/><path d="M19 13v6H5V5h6"/>','power'=>'<path d="M12 2v10"/><path d="M6.2 5.8a8 8 0 1 0 11.6 0"/>','send'=>'<path d="m22 2-7 20-4-9-9-4 20-7Z"/><path d="M22 2 11 13"/>','pause'=>'<path d="M8 5v14M16 5v14"/>','chev-up'=>'<path d="m6 15 6-6 6 6"/>','chev-down'=>'<path d="m6 9 6 6 6-6"/>'];
        $p['list-ordered']='<path d="M9 6h12M9 12h12M9 18h12"/><path d="M4 5h1v4M3 15l2-2v4H3"/>';
        $p['arrow-up']='<path d="M12 19V5M5 12l7-7 7 7"/>';
        $p['arrow-down']='<path d="M12 5v14M19 12l-7 7-7-7"/>';
        $p['newspaper']='<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 8h6v5H7zM16 8h2M16 11h2M7 16h11"/>';
        $p['scan-text']='<path d="M4 8V4h4M16 4h4v4M20 16v4h-4M8 20H4v-4M8 9h8M8 12h8M8 15h5"/>';
        $path=$p[$name]??'<circle cx="12" cy="12" r="8"/>';
        return '<svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'.$path.'</svg>';
    }
    private static function currentPath(): string
    {
        return (string)(parse_url((string)($_SERVER['REQUEST_URI']??'/'),PHP_URL_PATH)?:'/');
    }
    private static function navLink(string $name,string $href,string $current,string $hint=''): string
    {
        $rootExact=in_array($href,['/','/admin','/account'],true);$active=$rootExact?$current===$href:($current===$href||str_starts_with($current,rtrim($href,'/').'/'));
        return '<a class="app-nav-link'.($active?' active':'').'" href="'.self::e($href).'"'.($active?' aria-current="page"':'').'><span>'.self::e($name).'</span>'.($hint!==''?'<small>'.self::e($hint).'</small>':'').'</a>';
    }
    private static function publicNavigation(array $snapshot): string
    {
        $items=is_array($snapshot['primary']??null)?$snapshot['primary']:[];$html='<nav class="site-nav-links" aria-label="Reader navigation">';
        foreach($items as$item){
            if(!is_array($item))continue;$label=trim((string)($item['label']??''));$url=trim((string)($item['url']??''));if($label===''||$url==='')continue;
            $vis=strtolower((string)($item['visibility']??'all'));$target=strtoupper((string)($item['open']??'SAME_TAB'))==='NEW_TAB'?' target="_blank" rel="noopener"':'';
            $badge=trim((string)($item['badge']??''));$html.='<a class="nav-visibility-'.$vis.'" href="'.self::e($url).'"'.$target.'><span>'.self::e($label).'</span>'.($badge!==''?'<small class="nav-badge">'.self::e($badge).'</small>':'').'</a>';
        }
        return $html.'</nav>';
    }

    private static function publicFocusBar(array $snapshot): string
    {
        $quick=is_array($snapshot['quick']??null)?$snapshot['quick']:[];$breaking=is_array($snapshot['breaking']??null)?$snapshot['breaking']:[];if(!$quick&&!$breaking)return '';
        $html='<div class="reader-focus-bar" data-reader-nav-version="'.self::e((string)($snapshot['version']??0)).'">';
        if($breaking){$b=$breaking[0];$html.='<a class="reader-breaking" href="'.self::e((string)($b['url']??'/')).'"><strong>'.self::e((string)($b['badge']??'BREAKING')).'</strong><span>'.self::e((string)($b['label']??'')).'</span></a>';}
        if($quick){$html.='<nav class="reader-quick-links" aria-label="Quick links"><span class="eyebrow">FOCUS</span>';foreach($quick as$item){if(!is_array($item))continue;$label=trim((string)($item['label']??''));$url=trim((string)($item['url']??''));if($label===''||$url==='')continue;$target=strtoupper((string)($item['open']??'SAME_TAB'))==='NEW_TAB'?' target="_blank" rel="noopener"':'';$html.='<a href="'.self::e($url).'"'.$target.'>'.self::e($label).'</a>';}$html.='</nav>';}
        return $html.'</div>';
    }

    private static function adminNav(): string
    {
        $current=self::currentPath();$membership=Auth::currentMembership();
        $role=strtoupper((string)($membership['role_key']??'VIEWER'));
        $canonical=parse_url((string)Config::get('app.url',''),PHP_URL_HOST);
        $infraRoles=Config::get('auth.infrastructure_roles',['OWNER','ADMIN']);
        $canInfra=(bool)($canonical && strcasecmp((string)$canonical,Http::host())===0
            && $membership && in_array($role,$infraRoles,true));
        $groups=\EpaperSaas\Services\AdminFeatureCatalog::groups(
            $role,\EpaperSaas\Services\AiCandidatePipeline::enabled(),$canInfra
        );
        $tenant=(string)(RequestContext::get('tenant_name')?:'ePaper SaaS');
        $user=Auth::user()??[];
        $displayName=trim((string)($user['display_name']??$user['full_name']??$user['name']??''));
        $email=trim((string)($user['email']??''));
        if($displayName==='')$displayName=$email!==''?$email:'My account';
        $sectionIcons=['OVERVIEW'=>'dashboard','PUBLISHING'=>'newspaper','EDITORIAL'=>'scan-text',
            'DISCOVERY'=>'search','AUDIENCE'=>'user','SETTINGS'=>'settings',
            'OPERATIONS'=>'activity','ADVANCED'=>'archive'];
        $html='<div class="sidebar-context admin-sidebar-identity">'
            .'<span class="admin-sidebar-emblem" aria-hidden="true">'.self::icon('newspaper').'</span>'
            .'<span class="admin-sidebar-brand-copy"><strong>'.self::e($tenant).'</strong>'
            .'<small>Publishing Studio</small></span>'
            .'<button type="button" class="admin-sidebar-collapse" data-admin-sidebar-collapse'
            .' aria-label="Collapse navigation" aria-expanded="true" aria-controls="admin-side-sections"'
            .' title="Collapse sidebar">'.self::icon('chev-left').'</button></div>'
            .'<div class="admin-sidebar-search">'
            .'<label class="sr-only" for="admin-nav-search">Search publisher modules</label>'
            .'<span class="admin-sidebar-search-icon" aria-hidden="true">'.self::icon('search').'</span>'
            .'<input type="search" id="admin-nav-search" data-admin-nav-search'
            .' placeholder="Search modules" autocomplete="off" spellcheck="false">'
            .'<kbd aria-hidden="true">/</kbd>'
            .'<button type="button" class="admin-sidebar-search-expand" data-admin-nav-search-expand'
            .' aria-label="Expand sidebar and search modules" title="Search modules">'
            .self::icon('search').'</button></div>'
            .'<nav id="admin-side-sections" class="app-nav grouped-nav grouped-nav-compact admin-sidebar-nav"'
            .' aria-label="Publisher administration" data-admin-sidebar-nav data-admin-nav-role="'.self::e($role).'">';
        foreach($groups as $group=>$items){
            if($group==='ACCOUNT')continue; // Account remains accessible in the pinned sidebar footer.
            $active=false;
            foreach($items as $item){
                $url=$item[1];
                if($current===$url || ($url!=='/admin'&&str_starts_with($current,$url.'/'))){
                    $active=true;break;
                }
            }
            $html.='<details class="nav-section'.($active?' is-active':'').'"'
                .' data-admin-nav-section="'.self::e($group).'"'.(($group==='PUBLISHING'||$active)?' open':'').'>'
                .'<summary class="nav-section-title" title="Open '.self::e(ucwords(strtolower($group))).' navigation">'
                .'<span class="nav-section-label"><span class="nav-section-mark" aria-hidden="true">'
                .self::icon($sectionIcons[$group]??'pages').'</span>'
                .'<span class="nav-section-heading">'.self::e($group).'</span></span>'
                .'<span class="nav-section-chevron" aria-hidden="true">'.self::icon('chev-down').'</span></summary>'
                .'<div class="nav-group">';
            foreach($items as [$label,$url,$hint,$icon]){
                $selected=$current===$url||($url!=='/admin'&&str_starts_with($current,$url.'/'));
                $html.='<a class="app-nav-link'.($selected?' active':'').'" href="'.self::e($url).'"'
                    .($selected?' aria-current="page"':'')
                    .' data-admin-search-terms="'.self::e($group.' '.$label.' '.$hint).'"'
                    .' title="'.self::e($hint).'">'
                    .'<span class="admin-nav-icon" aria-hidden="true">'.self::icon($icon).'</span>'
                    .'<span class="admin-nav-text">'.self::e($label).'</span></a>';
            }
            $html.='</div></details>';
        }
        $html.='</nav><p class="admin-sidebar-empty" data-admin-sidebar-empty hidden>'
            .'No matching modules for your role.</p>'
            .'<a class="admin-sidebar-account" href="/admin/account" title="My account">'
            .'<span class="admin-sidebar-avatar" aria-hidden="true">'.self::icon('user').'</span>'
            .'<span class="admin-sidebar-account-copy"><strong>'.self::e($displayName).'</strong>'
            .'<small>'.self::e($email!==''?$email:str_replace('_',' ',$role)).'</small></span>'
            .'<span class="admin-sidebar-account-arrow" aria-hidden="true">'.self::icon('chev-right').'</span></a>';
        return $html;
    }
    private static function accountNav(): string
    {
        $current=self::currentPath();$items=[
            ['Reader OS','/account','Reading home'],['Library','/account/library','Saved content'],['Saved Articles','/account/saved-articles','Smart Article saves'],['Bookmarks','/account/bookmarks','Saved pages'],['Clips','/account/clips','Clipping gallery'],['History','/account/history','Recent reading'],['Offline','/account/offline','Encrypted editions'],['Notifications','/account/notifications','Updates'],['Subscription','/account/subscription','Plan & access'],['Devices','/account/devices','Offline devices']
        ];
        $html='<div class="sidebar-context"><span class="eyebrow">MY EPAPER</span><strong>Reader OS</strong></div><nav class="app-nav account-nav" aria-label="Reader OS navigation">';foreach($items as[$name,$href,$hint])$html.=self::navLink($name,$href,$current,$hint);$html.=self::navLink('Sign out','/logout',$current,'End this session');return$html.'</nav>';
    }
    private static function adminColorMode(?array $user): string
    {
        if(!$user||!RequestContext::tenantGuid()||empty($user['user_guid']))return '';
        try{
            $row=Store::one('SELECT preferences_json FROM ep_subscribers WHERE tenant_guid=? AND platform_user_guid=? LIMIT 1',[Uuid::toBinary(RequestContext::tenantGuid()),Uuid::toBinary((string)$user['user_guid'])]);
            $prefs=$row&&$row['preferences_json']?json_decode((string)$row['preferences_json'],true):[];
            $mode=is_array($prefs)?strtolower((string)($prefs['admin_theme_mode']??'')):'';
            return in_array($mode,['light','dark'],true)?$mode:'';
        }catch(\Throwable){return '';}
    }
    private static function opsHud(): string
    {
        $db=Store::metrics();$cache=RedisStore::metrics();$rate=$cache['hit_rate'];
        $cacheText=$rate===null?'Cache —':'Cache '.(int)$rate.'%';
        return '<div class="ops-hud" data-ops-hud title="Request performance"><span><b>DB</b> '.(int)$db['queries'].'</span><span>'.number_format((float)$db['query_ms'],2).' ms</span><span>'.$cacheText.'</span><span>'.self::e((string)$cache['driver']).'</span></div>';
    }
    private static function authQuote(): array
    {
        $quotes=[
            ['The press is the immune system of democracy.','Roy Greenslade'],
            ['Comment is free, but facts are sacred.','C. P. Scott'],
            ['A good newspaper, I suppose, is a nation talking to itself.','Arthur Miller'],
            ['The newspaper is the first rough draft of history.','Philip L. Graham'],
            ['Freedom of the press is not just important to democracy, it is democracy.','Walter Cronkite'],
            ['The press was to serve the governed, not the governors.','Hugo Black'],
            ['Our Republic and its press will rise or fall together.','Joseph Pulitzer'],
            ['The only security of all is in a free press.','Thomas Jefferson'],
            ['A critical, independent and investigative press is the lifeblood of any democracy.','Nelson Mandela'],
            ['There can be no higher law in journalism than to tell the truth and to shame the devil.','Walter Lippmann'],
            ['A free press can be good or bad, but without freedom a press will never be anything but bad.','Albert Camus'],
            ['The job of the newspaper is to comfort the afflicted and afflict the comfortable.','Finley Peter Dunne']
        ];
        $count=count($quotes);if($count<2)return $quotes[0]??['',''];
        $last=null;if(session_status()===PHP_SESSION_ACTIVE&&isset($_SESSION['epaper_auth_quote_index']))$last=(int)$_SESSION['epaper_auth_quote_index'];
        try{$idx=random_int(0,$count-1);}catch(\Throwable){$idx=(int)(microtime(true)*1000)%$count;}
        if($last!==null&&$idx===$last)$idx=($idx+1)%$count;
        if(session_status()===PHP_SESSION_ACTIVE)$_SESSION['epaper_auth_quote_index']=$idx;
        return $quotes[$idx];
    }
    private static function authFormIsolated(string $html): string
    {
        // Authentication markup originally uses global auth classes from app.css.
        // Rename them for this surface so legacy v1.1.9.x rules cannot change
        // the card geometry in real browsers or at non-100% browser zoom.
        return str_replace(
            [
                'class="btn primary auth-submit"',
                'class="auth-panel"',
                'class="auth-brand"',
                'class="eyebrow"',
                'class="muted"',
                'class="auth-form"',
                'class="auth-security"',
                'class="auth-error"'
            ],
            [
                'class="auth-fit-submit"',
                'class="auth-fit-panel"',
                'class="auth-fit-legacy-brand"',
                'class="auth-fit-eyebrow"',
                'class="auth-fit-muted"',
                'class="auth-fit-fields"',
                'class="auth-fit-security"',
                'class="auth-fit-error"'
            ],
            $html
        );
    }
    private static function authExperience(string $formBody,string $brand,bool $hasTenant,string $logo,string $logoDark): string
    {
        $__epxForm=(string)$formBody;
        $__epxBrand=(string)$brand;
        // Runtime safety: tolerate either historical parameter order.
        if(!str_contains($__epxForm,'<form') && str_contains($__epxBrand,'<form')){[$__epxForm,$__epxBrand]=[$__epxBrand,$__epxForm];}
        if(!str_contains($__epxForm,'<form'))return $__epxForm;
        $__epxBrand=trim(strip_tags($__epxBrand));if($__epxBrand===''||strlen($__epxBrand)>120)$__epxBrand='EPAPER';
        $__epxSafe=htmlspecialchars($__epxBrand,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $__epxInitial=function_exists('mb_substr')?mb_strtoupper(mb_substr($__epxBrand,0,1)):strtoupper(substr($__epxBrand,0,1));
        $__epxInitial=htmlspecialchars($__epxInitial?:'E',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $__epxClean=str_replace(
            ['class="auth-shell"','class="auth-panel"','class="auth-brand"','class="brand-mark"','class="eyebrow"','class="muted"','class="auth-form"','class="btn primary auth-submit"','class="auth-submit"','class="auth-security"','class="auth-error"'],
            ['class="epx-form-shell"','class="epx-panel"','class="epx-legacy-brand"','class="epx-legacy-mark"','class="epx-eyebrow"','class="epx-muted"','class="epx-form"','class="epx-submit"','class="epx-submit"','class="epx-security"','class="epx-error"'],
            $__epxForm
        );
        [$__epxQuote,$__epxAuthor]=method_exists(self::class,'authQuote')?self::authQuote():['The press is the immune system of democracy.','Roy Greenslade'];
        $__epxQuote=htmlspecialchars((string)$__epxQuote,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $__epxAuthor=htmlspecialchars((string)$__epxAuthor,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $__epxIcon=method_exists(self::class,'icon')?self::icon('user'):'&#9675;';
        return '<style id="epx-login-v5-style">'
          .'#epx-login-v5{position:fixed;inset:0;z-index:2147483000;overflow:auto;box-sizing:border-box;background:linear-gradient(180deg,#a8def4 0%,#d9f1fb 58%,#f8fbfc 100%);font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#171a1e;padding:72px 20px 34px}'
          .'#epx-login-v5 *{box-sizing:border-box}'
          .'#epx-login-v5 .epx-brand{position:absolute;left:34px;top:24px;display:flex;align-items:center;gap:9px;color:#111820;text-decoration:none;font-weight:800;font-size:14px;letter-spacing:.015em}'
          .'#epx-login-v5 .epx-brand-mark{display:grid;place-items:center;width:28px;height:28px;border-radius:8px;background:#171b20;color:#fff;font-size:13px;box-shadow:0 5px 16px #1d273122}'
          .'#epx-login-v5 .epx-card{position:relative;width:min(100%,430px);height:auto!important;min-height:0!important;max-height:none!important;margin:0 auto;padding:48px 30px 22px;border:1px solid rgba(67,92,106,.16);border-radius:25px;background:rgba(250,253,254,.93);box-shadow:0 24px 70px rgba(45,77,94,.15);backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px)}'
          .'#epx-login-v5 .epx-icon{position:absolute;left:50%;top:-25px;transform:translateX(-50%);display:grid;place-items:center;width:52px;height:52px;border-radius:16px;background:#fff;color:#1c242a;box-shadow:0 8px 22px rgba(25,47,59,.13)}'
          .'#epx-login-v5 .epx-icon svg{width:21px;height:21px}'
          .'#epx-login-v5 .epx-form-shell,#epx-login-v5 .epx-panel{display:contents!important;height:auto!important;min-height:0!important;max-height:none!important;margin:0!important;padding:0!important}'
          .'#epx-login-v5 .epx-legacy-brand{display:none!important}'
          .'#epx-login-v5 .epx-eyebrow{display:block;margin:0 0 7px;text-align:center;font-size:10px;line-height:1.2;font-weight:800;letter-spacing:.16em;color:#6a7780}'
          .'#epx-login-v5 h1{margin:0;text-align:center;font-size:30px!important;line-height:1.08!important;letter-spacing:-.035em;color:#15181c}'
          .'#epx-login-v5 .epx-panel>p.epx-muted{margin:7px 0 0!important;text-align:center;color:#75818a!important;font-size:12px!important;line-height:1.45!important}'
          .'#epx-login-v5 .epx-form{display:grid!important;gap:11px!important;height:auto!important;min-height:0!important;max-height:none!important;margin:21px 0 0!important;padding:0!important}'
          .'#epx-login-v5 .epx-form label{display:grid!important;gap:6px!important;margin:0!important;font-size:11px!important;line-height:1.25!important;font-weight:750!important;color:#323a40!important}'
          .'#epx-login-v5 .epx-form label .epx-muted{font-weight:500!important;color:#7d8991!important}'
          .'#epx-login-v5 input{display:block!important;width:100%!important;height:46px!important;min-height:46px!important;max-height:46px!important;margin:0!important;padding:0 13px!important;border:1px solid #d4dde2!important;border-radius:12px!important;background:#eef3f5!important;color:#161a1d!important;font:500 14px/1.2 inherit!important;box-shadow:none!important;outline:none!important}'
          .'#epx-login-v5 input:focus{border-color:#4a80ff!important;background:#fff!important;box-shadow:0 0 0 3px rgba(74,128,255,.13)!important}'
          .'#epx-login-v5 .epx-submit{display:flex!important;align-items:center!important;justify-content:center!important;width:100%!important;height:46px!important;min-height:46px!important;max-height:46px!important;margin:2px 0 0!important;padding:0 16px!important;border:0!important;border-radius:12px!important;background:linear-gradient(180deg,#23262a,#15171a)!important;color:#fff!important;font:750 14px/1 inherit!important;cursor:pointer!important;box-shadow:0 8px 22px rgba(22,24,27,.13)!important}'
          .'#epx-login-v5 .epx-security{margin:11px auto 0!important;max-width:340px!important;text-align:center!important;color:#7b858d!important;font-size:10px!important;line-height:1.45!important}'
          .'#epx-login-v5 .epx-error{margin:0 0 13px!important;padding:10px 12px!important;border:1px solid #f0c6c2!important;border-radius:10px!important;background:#fff4f3!important;color:#9d2118!important;font-size:11px!important}'
          .'#epx-login-v5 .epx-quote{display:grid;grid-template-columns:24px minmax(0,1fr);grid-template-areas:"mark text" ". author";column-gap:9px;row-gap:3px;width:100%;height:auto!important;min-height:0!important;max-height:none!important;margin:12px 0 0!important;padding:12px 13px 11px;border:1px solid rgba(76,99,113,.12);border-radius:14px;background:rgba(255,255,255,.62);box-shadow:inset 0 1px 0 rgba(255,255,255,.75)}'
          .'#epx-login-v5 .epx-quote-mark{grid-area:mark;color:#4b76cf;font:700 28px/.9 Georgia,serif}'
          .'#epx-login-v5 blockquote{grid-area:text;margin:0!important;padding:0!important;color:#2c363d;font:650 11px/1.45 inherit!important}'
          .'#epx-login-v5 figcaption{grid-area:author;margin:0!important;color:#7d8991;font:600 9px/1.35 inherit!important}'
          .'@media(max-width:560px){#epx-login-v5{padding:68px 14px 24px}#epx-login-v5 .epx-brand{left:18px;top:18px}#epx-login-v5 .epx-card{width:min(100%,400px);padding:45px 20px 18px;border-radius:22px}#epx-login-v5 h1{font-size:27px!important}#epx-login-v5 .epx-form{margin-top:18px!important;gap:10px!important}#epx-login-v5 input,#epx-login-v5 .epx-submit{height:44px!important;min-height:44px!important;max-height:44px!important}}'
          .'</style>'
          .'<section id="epx-login-v5" data-auth-version="clean-renderer-v5">'
          .'<a class="epx-brand" href="/" aria-label="'.$__epxSafe.' home"><span class="epx-brand-mark">'.$__epxInitial.'</span><span>'.$__epxSafe.'</span></a>'
          .'<div class="epx-card"><div class="epx-icon" aria-hidden="true">'.$__epxIcon.'</div>'
          .$__epxClean
          .'<figure class="epx-quote" aria-label="Editorial quote"><span class="epx-quote-mark" aria-hidden="true">“</span><blockquote>'.$__epxQuote.'</blockquote><figcaption>— '.$__epxAuthor.'</figcaption></figure>'
          .'</div></section>';
    }
    public static function layout(string $title,string $body,array $opts=[]): never
    {
        $ctx=RequestContext::all();$brand=$opts['brand']??($ctx['tenant_name']??'ePaper SaaS');$admin=(bool)($opts['admin']??false);$account=(bool)($opts['account']??false);$reader=(bool)($opts['reader']??false);$auth=(bool)($opts['auth']??false);$readerEditionStrip=$reader?trim((string)($opts['reader_edition_strip']??'')):'';$readerHasEditionStrip=$readerEditionStrip!=='';
        $manualAdminAssets=$admin && (bool)preg_match('#^/admin/manual-clipping(?:/|$)#',parse_url((string)($_SERVER['REQUEST_URI']??'/'),PHP_URL_PATH)?:'/');
        $workspace=$admin||$account;$publicSnapshot=(!$workspace&&!$auth)?ReaderNavigation::publicSnapshot(is_array($opts['navigation_scope']??null)?$opts['navigation_scope']:[]):null;$nav=$admin?self::adminNav():($account?self::accountNav():self::publicNavigation($publicSnapshot??ReaderNavigation::defaultSnapshot()));
        $user=Auth::user();$adminColorMode=$admin?self::adminColorMode($user):'';$membership=$user?Auth::currentMembership():null;$role=strtoupper((string)($membership['role_key']??''));$adminRoles=array_map('strtoupper',(array)Config::get('auth.admin_roles',['OWNER','ADMIN','PUBLISHER_ADMIN','EDITOR','OPERATOR']));$canAdmin=$user&&$membership&&in_array($role,$adminRoles,true);$sessionActions=$user?'<a class="icon-link" href="/account" aria-label="My account" title="My account">'.self::icon('user').'</a>'.((!$admin&&$canAdmin)?'<a class="icon-link" href="/admin" aria-label="Publisher administration" title="Publisher administration">'.self::icon('dashboard').'</a>':'').($admin?'<a class="text-link" href="/logout">Sign out</a>':''):'<a class="icon-link" href="/login" aria-label="Sign in" title="Sign in">'.self::icon('login').'</a>';
        $hasTenant=(bool)RequestContext::get('tenant_guid');$adminTheme=$hasTenant&&class_exists(TenantData::class)?(string)TenantData::setting('appearance.admin_theme','Publisher Pro'):'Publisher Pro';
        $readerTheme=$hasTenant&&class_exists(TenantData::class)?(string)TenantData::setting('appearance.reader_theme',TenantData::setting('reader.theme','Newsstand Replica')):'Newsstand Replica';
        $authTheme=$hasTenant&&class_exists(TenantData::class)?(string)TenantData::setting('appearance.auth_theme','Split Editorial'):'Split Editorial';$publicColorMode=$hasTenant&&class_exists(TenantData::class)?strtolower((string)TenantData::setting('appearance.public_color_mode','light')):'light';if(!in_array($publicColorMode,['light','system','dark'],true))$publicColorMode='light';$publicThemeSwitcher=$hasTenant&&class_exists(TenantData::class)?strtolower((string)TenantData::setting('appearance.public_color_switcher','on'))!=='off':true;$publicThemeButton=(!$admin&&!$auth&&$publicThemeSwitcher)?'<button class="icon-btn public-theme-toggle" type="button" data-theme-toggle aria-label="Toggle light and dark mode" title="Toggle appearance"><span class="theme-icon theme-icon-moon">'.self::icon('moon').'</span><span class="theme-icon theme-icon-sun">'.self::icon('sun').'</span></button>':'';
        $theme=$reader?$readerTheme:($admin?$adminTheme:($auth?$authTheme:'Default'));$themeClass='theme-'.strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',$theme),'-'));
        $logo=$hasTenant&&class_exists(TenantData::class)?trim((string)TenantData::setting('brand.logo_path','')):'';$logoDark=$hasTenant&&class_exists(TenantData::class)?trim((string)TenantData::setting('brand.logo_dark_path','')):'';$logoAlt=trim((string)$brand)!==''?(string)$brand:'ePaper';$mark=$logo?'<span class="brand-logo-stack"><img class="brand-logo brand-logo-light" src="'.self::e($logo).'" alt="'.self::e($logoAlt).'" decoding="async">'.($logoDark?'<img class="brand-logo brand-logo-dark" src="'.self::e($logoDark).'" alt="'.self::e($logoAlt).'" decoding="async">':'').'</span>':'<span class="brand-name brand-name-fallback">'.self::e($brand).'</span>';$brandText='';
        $accent=$hasTenant&&class_exists(TenantData::class)?(string)TenantData::setting('brand.accent','#2563eb'):'#2563eb';$footer=$hasTenant&&class_exists(TenantData::class)?(string)TenantData::setting('brand.footer_text',''):'';
        $defaultSeo=$hasTenant&&class_exists(TenantData::class)?(string)TenantData::setting('seo.description',''):'';$seo=trim((string)($opts['description']??$defaultSeo));$titleSuffix=$hasTenant&&class_exists(TenantData::class)?trim((string)TenantData::setting('seo.title_suffix','ePaper')):'ePaper';$robots=trim((string)($opts['robots']??''));if($robots==='')$robots=$hasTenant&&class_exists(TenantData::class)?(string)TenantData::setting('seo.robots','index,follow,max-image-preview:large'):'index,follow';$defaultSocialImage=$hasTenant&&class_exists(TenantData::class)?trim((string)TenantData::setting('seo.social_image','')):'';$socialImage=trim((string)($opts['social_image']??$defaultSocialImage));$ogTitle=trim((string)($opts['og_title']??$title));$ogDescription=trim((string)($opts['og_description']??$seo));$canonicalUrl=trim((string)($opts['canonical_url']??''));$socialImageAlt=trim((string)($opts['social_image_alt']??($ogTitle!==''?$ogTitle:$brand)));$ogType=trim((string)($opts['og_type']??'website'))?:'website';$lang=trim((string)($opts['lang']??'en'))?:'en';$alternates=is_array($opts['alternates']??null)?$opts['alternates']:[];$alternateHtml='';foreach($alternates as$alt){if(!is_array($alt))continue;$hreflang=trim((string)($alt['hreflang']??''));$href=trim((string)($alt['href']??''));if($hreflang!==''&&$href!=='')$alternateHtml.='<link rel="alternate" hreflang="'.self::e($hreflang).'" href="'.self::e($href).'">';}$articleMeta='';if($ogType==='article'){foreach(['published_time','modified_time','section'] as$k){$v=trim((string)($opts['article_'.$k]??''));if($v!=='')$articleMeta.='<meta property="article:'.$k.'" content="'.self::e($v).'">';}foreach((array)($opts['article_authors']??[]) as$authorUrl){$authorUrl=trim((string)$authorUrl);if($authorUrl!=='')$articleMeta.='<meta property="article:author" content="'.self::e($authorUrl).'">';}}$structuredData=$opts['structured_data']??[];if(isset($structuredData['@context'])||isset($structuredData['@type']))$structuredData=[$structuredData];if(!is_array($structuredData))$structuredData=[];$jsonLd='';foreach($structuredData as$node){if(!is_array($node)||!$node)continue;$json=json_encode($node,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);if($json!==false)$jsonLd.='<script type="application/ld+json">'.$json.'</script>';}$favicon=$hasTenant&&class_exists(TenantData::class)?trim((string)TenantData::setting('brand.favicon_path','')):'';$appleTouch=$hasTenant&&class_exists(TenantData::class)?trim((string)TenantData::setting('brand.apple_touch_icon_path','')):'';$density=$hasTenant&&class_exists(TenantData::class)?(string)TenantData::setting('appearance.density','comfortable'):'comfortable';
        header('Content-Type: text/html; charset=utf-8');
        // Anonymous public pages stay session-free. CSRF state is created lazily only
        // when a public interaction actually performs a state-changing API request.
        $bootCsrf=($workspace||$auth)?Http::csrf():(string)($_SESSION['_csrf']??'');
        echo '<!doctype html><html lang="'.self::e($lang).'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="color-scheme" content="light dark"><meta name="theme-color" content="'.self::e($accent).'"><title>'.self::e($title).' · '.self::e($brand).($titleSuffix!==''?' · '.self::e($titleSuffix):'').'</title>'.($seo?'<meta name="description" content="'.self::e($seo).'">':'').'<meta name="robots" content="'.self::e($robots).'"><meta property="og:type" content="'.self::e($ogType).'"><meta property="og:title" content="'.self::e($ogTitle).'">'.($ogDescription!==''?'<meta property="og:description" content="'.self::e($ogDescription).'"><meta name="twitter:description" content="'.self::e($ogDescription).'">':'').($canonicalUrl!==''?'<link rel="canonical" href="'.self::e($canonicalUrl).'"><meta property="og:url" content="'.self::e($canonicalUrl).'">':'').$alternateHtml.$articleMeta.($socialImage?'<meta property="og:image" content="'.self::e($socialImage).'"><meta property="og:image:alt" content="'.self::e($socialImageAlt).'"><meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="'.self::e($ogTitle).'"><meta name="twitter:image" content="'.self::e($socialImage).'"><meta name="twitter:image:alt" content="'.self::e($socialImageAlt).'">':'').($favicon?'<link rel="icon" href="'.self::e($favicon).'">':'').($appleTouch?'<link rel="apple-touch-icon" href="'.self::e($appleTouch).'">':'').$jsonLd.'<link rel="manifest" href="/manifest.webmanifest?v='.self::e(Build::version()).'"><link rel="stylesheet" href="'.self::e(Build::asset('/assets/app.css')).'">'.($admin?'<link rel="stylesheet" href="'.self::e(Build::asset('/assets/admin-beta.css')).'"><link rel="stylesheet" href="'.self::e(Build::asset('/assets/admin-operations.css')).'"><link rel="stylesheet" href="'.self::e(Build::asset('/assets/staging-qa.css')).'"><link rel="stylesheet" href="'.self::e(Build::asset('/assets/admin-workspace.css')).'"><link rel="stylesheet" href="'.self::e(Build::asset('/assets/editorial-actions.css')).'"><link rel="stylesheet" href="'.self::e(Build::asset('/assets/admin-studio.css')).'"><link rel="stylesheet" href="'.self::e(Build::asset('/assets/admin-sidebar.css')).'"><link rel="stylesheet" href="'.self::e(Build::asset('/assets/admin-typography.css')).'">'.($manualAdminAssets?'<link rel="stylesheet" href="'.self::e(Build::asset('/assets/manual-clipping.css').'&clip-ui=20261019-icons-v1').'">':''):'').($account?'<link rel="stylesheet" href="'.self::e(Build::asset('/assets/account-beta.css')).'">':'').($auth?'<link rel="stylesheet" href="'.self::e(Build::asset('/assets/auth-clean-final-v2.css')).'">':'').($reader?'<link rel="stylesheet" href="'.self::e(Build::asset('/assets/reader.css')).'"><link rel="stylesheet" href="'.self::e(Build::asset('/assets/reader-editorial-clips.css')).'">':'').'</head><body class="'.($admin?'admin-shell':($account?'account-shell':($reader?'reader-page':($auth?'auth-shell':'public-shell')))).' '.self::e($themeClass).' density-'.self::e(preg_replace('/[^a-z0-9_-]+/i','-',strtolower($density))).($adminColorMode==='dark'?' dark':'').($readerHasEditionStrip?' reader-has-edition-switcher':'').'" style="--brand-accent:'.self::e($accent).'" data-theme-name="'.self::e($theme).'" data-admin-color-mode="'.self::e($adminColorMode).'" data-public-color-mode="'.self::e($publicColorMode).'" data-public-theme-switcher="'.($publicThemeSwitcher?'on':'off').'" data-tenant-guid="'.self::e((string)(RequestContext::get('tenant_guid')??'')).'" data-user-guid="'.self::e((string)($user['user_guid']??'guest')).'">';
        echo '<script>(function(){try{var b=document.body;if(!b||b.classList.contains("admin-shell"))return;var allow=b.dataset.publicThemeSwitcher!=="off";var base=b.dataset.publicColorMode||"light";var tenant=b.dataset.tenantGuid||"tenant";var user=b.dataset.userGuid||"guest";var key="epaper.theme."+tenant+"."+user;var legacyUser="epaper.theme."+user;var saved=allow?(localStorage.getItem(key)||localStorage.getItem(legacyUser)||localStorage.getItem("epaper.theme")||""):"";var mode=saved||base;if(mode==="system")mode=matchMedia("(prefers-color-scheme:dark)").matches?"dark":"light";b.classList.toggle("dark",mode==="dark");}catch(e){}})();</script><a class="skip" href="#main">Skip to content</a>';
        if(!$auth)echo '<header class="site-header"><div class="site-header-inner"><a class="brand'.($logo?' brand-with-logo':'').'" href="/" aria-label="'.self::e($brand).' home">'.$mark.$brandText.'</a>'.(!$workspace?$nav:'').'<div class="header-actions">'.($workspace?'<button class="icon-btn app-nav-toggle" type="button" data-app-nav-toggle aria-label="Open navigation">'.self::icon('menu').'</button>':'').($admin?'<a class="console-health-link" href="/admin/system/health">Diagnostics</a><a class="btn primary console-create" href="/admin/editions">'.self::icon('plus').'<span>New edition</span></a><button class="icon-btn command-trigger" type="button" data-command-trigger aria-label="Open command palette" title="Command palette (Ctrl/Cmd+K)">'.self::icon('search').'</button><button class="icon-btn admin-theme-toggle" type="button" data-theme-toggle aria-label="Toggle light and dark mode" title="Toggle appearance"><span class="theme-icon theme-icon-moon">'.self::icon('moon').'</span><span class="theme-icon theme-icon-sun">'.self::icon('sun').'</span></button>':'').$publicThemeButton.$sessionActions.'</div></div></header>';
        if($readerHasEditionStrip)echo $readerEditionStrip;
        if(!$workspace&&!$auth&&!$reader&&$publicSnapshot){$focus=self::publicFocusBar($publicSnapshot);if($focus!=='')echo $focus;}
        if($workspace){echo '<div class="app-workspace"><aside class="app-sidebar" data-app-sidebar>'.$nav.'</aside><div class="sidebar-scrim" data-sidebar-scrim></div><main id="main" class="main-container workspace-main">'.$body.'</main></div>';}elseif($auth)echo '<main id="main" class="auth-main">'.self::authExperience($body,(string)$brand,$hasTenant,$logo,$logoDark).'</main>';else echo '<main id="main" class="'.($reader?'reader-main':'main-container').'">'.$body.'</main>';
        if(!$reader&&!$auth&&$footer!=='')echo '<footer class="site-footer">'.self::e($footer).'</footer>';
        echo '<div id="toast-region" class="toast-region" aria-live="polite"></div>';
        if($admin)echo self::opsHud();
        if($admin)echo '<div class="command-palette hidden" data-command-palette role="dialog" aria-modal="true" aria-label="Command palette"><div class="command-card"><div class="command-input-wrap">'.self::icon('search').'<input data-command-input placeholder="Search publisher actions…" autocomplete="off"><kbd>Esc</kbd></div><div class="command-results" data-command-results></div></div></div>';
        $scripts='<script src="'.self::e(Build::asset('/assets/app.js')).'" defer></script>';
        if($admin)$scripts.='<script src="'.self::e(Build::asset('/assets/crud.js')).'" defer></script><script src="'.self::e(Build::asset('/assets/admin-beta.js')).'" defer></script><script src="'.self::e(Build::asset('/assets/admin-workspace.js')).'" defer></script><script src="'.self::e(Build::asset('/assets/admin-sidebar.js')).'" defer></script>';
        if($manualAdminAssets)$scripts.='<script src="'.self::e(Build::asset('/assets/manual-clipping.js').'&clip-ui=20261019-icons-v1').'" defer></script>';
        if($user||$account||$reader)$scripts.='<script src="'.self::e(Build::asset('/assets/offline-vault.js')).'" defer></script>';
        if($reader)$scripts.='<script src="'.self::e(Build::asset('/assets/guest-clipping.js')).'" defer></script><script src="'.self::e(Build::asset('/assets/reader-prefetch.js')).'" defer></script><script src="'.self::e(Build::asset('/assets/reader.js')).'" defer></script><script src="'.self::e(Build::asset('/assets/reader-flip.js')).'" defer></script><script src="'.self::e(Build::asset('/assets/reader-editorial-clips.js')).'" defer></script>';
        echo '<script>window.EpaperSaas={csrf:'.json_encode($bootCsrf).',requestGuid:'.json_encode(Http::requestGuid()).',version:'.json_encode(Build::version()).',tenantGuid:'.json_encode(RequestContext::get('tenant_guid')).',userGuid:'.json_encode($user['user_guid']??null).',adminThemeMode:'.json_encode($adminColorMode).',publicThemeMode:'.json_encode($publicColorMode).',publicThemeSwitcher:'.json_encode($publicThemeSwitcher?'on':'off').',rumSampleRate:'.json_encode((float)Config::get('observability.rum_sample_rate',0.10)).'}</script>'.$scripts.'</body></html>';exit;
    }
}
