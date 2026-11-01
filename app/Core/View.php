<?php
namespace Digiti\Core;

final class View
{
    public static function esc(mixed $v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }

    private static function menuLink(string $path,string $url,string $label): string
    {
        $active=$path===$url;
        $current=$active?' aria-current="page"':'';
        return '<a class="platform-menu-link'.($active?' active':'').'" href="'.self::esc($url).'"'.$current.'>'
            .'<span class="platform-menu-marker" aria-hidden="true"></span>'
            .'<span>'.self::esc($label).'</span></a>';
    }

    private static function menu(string $path,string $mobile=''): string
    {
        $main=[
            ['/admin','Dashboard'],
            ['/admin/onboarding','Publishers & domains'],
            ['/admin/users','Team & access'],
            ['/admin/subscriptions','Subscriptions'],
            ['/admin/help','Help centre'],
        ];
        $advanced=[
            ['/admin/organisations','Organisations'],
            ['/admin/product-tenants','Product tenants'],
            ['/admin/domains','Domain registry'],
            ['/admin/products','Products'],
            ['/admin/applications','Applications'],
            ['/admin/service-accounts','Service accounts'],
            ['/admin/commercial','Plans & pricing'],
            ['/admin/billing','Billing & payments'],
            ['/admin/operations','Operations'],
            ['/admin/health','System health'],
            ['/admin/security','Platform security'],
            ['/admin/audit','Audit log'],
            ['/admin/errors','Errors'],
            ['/admin/impersonation','Support tools'],
        ];
        if(\Digiti\Admin\StagingReset::enabled())
            $advanced[]=['/admin/staging-reset','Staging reset'];
        $common='<span class="platform-menu-label">EVERYDAY TASKS</span>';
        foreach($main as [$url,$label]) $common.=self::menuLink($path,$url,$label);
        $detailOpen=false;
        foreach($advanced as [$url]) if($url===$path) $detailOpen=true;
        $common.='<details class="platform-advanced"'.($detailOpen?' open':'').'>'
            .'<summary>Advanced administration <span aria-hidden="true">⌄</span></summary>'
            .'<div class="platform-advanced-links"><span class="platform-menu-label">SYSTEM SETTINGS</span>';
        foreach($advanced as [$url,$label]) $common.=self::menuLink($path,$url,$label);
        $common.='</div></details>';
        return '<nav class="platform-sidebar-nav" aria-label="'.($mobile==='mobile'?'Mobile navigation':'Primary navigation').'">'.$common.'</nav>';
    }

    public static function layout(string $title,string $content,array $opts=[]): never
    {
        $admin=Auth::admin();
        $csrf=Http::csrf();
        $version=trim((string)@file_get_contents(dirname(__DIR__,2).'/VERSION'));
        $notice=$_SESSION['_flash']??null;
        unset($_SESSION['_flash']);
        $assetRoot=dirname(__DIR__,2).'/public_html/assets/';
        $fingerprint=static function(string $name,string $fallback)use($assetRoot):string{
            $file=$assetRoot.$name;
            return is_file($file)?substr(hash_file('sha256',$file),0,12):$fallback;
        };
        $cssFingerprint=$fingerprint('app.css',$version);
        $onboarding=isset($_SERVER['REQUEST_URI'])&&str_starts_with((string)$_SERVER['REQUEST_URI'],'/admin/onboarding');
        $onboardingFingerprint=$onboarding?$fingerprint('onboarding.css','missing'):'';
        $uiFingerprint=$fingerprint('platform-ui.css','missing');
        $path=parse_url($_SERVER['REQUEST_URI']??'/admin',PHP_URL_PATH)?:'/admin';
        if($admin) header('Cache-Control: private, no-store, max-age=0');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
        <meta name="color-scheme" content="light dark"><title><?=self::esc($title)?> · Digiti Platform</title>
        <link rel="stylesheet" href="/assets/app.css?v=<?=self::esc($cssFingerprint)?>">
        <?php if($onboarding):?><link rel="stylesheet" href="/assets/onboarding.css?v=<?=self::esc($onboardingFingerprint)?>"><?php endif;?>
        <link rel="stylesheet" href="/assets/platform-ui.css?v=<?=self::esc($uiFingerprint)?>">
        </head><body class="<?=$admin?'platform-authenticated':'platform-guest'?>">
        <a class="platform-skip" href="#platform-content">Skip to content</a>
        <header class="platform-topbar"><a class="platform-brand" href="/admin"><span class="platform-brand-mark" aria-hidden="true">D</span>
        <span class="platform-brand-title">Digiti <strong>Platform</strong></span></a>
        <?php if($admin):?>
        <span class="platform-topbar-context">Publisher management</span>
        <div class="platform-user">
        <a class="platform-help-link" href="/admin/help">Help</a>
        <span class="platform-user-name"><?=self::esc($admin['display_name'])?></span>
        <a class="platform-signout" href="/logout">Sign out</a>
        </div>
        <?php endif;?></header>
        <?php if($admin):?>
        <div class="platform-app-shell">
            <aside class="platform-sidebar"><div class="platform-sidebar-intro"><span>WORKSPACE</span><strong>Control centre</strong>
            <small>Publishers, websites and operations</small></div>
            <?=self::menu($path)?>
            <p class="platform-sidebar-footer">Technical tools are under Advanced administration.</p></aside>
            <div class="platform-main-column">
                <details class="platform-mobile-menu"><summary>Menu & navigation <span aria-hidden="true">⌄</span></summary>
                <?=self::menu($path,'mobile')?></details>
        <?php endif;?>
        <?php if($notice):?><div class="toast" role="status" aria-live="polite"><?=self::esc($notice)?></div><?php endif;?>
        <main id="platform-content" class="shell<?= $onboarding ? ' onboarding-shell' : '' ?>">
        <div class="page-head"><div><h1><?=self::esc($title)?></h1>
        <?php if(!empty($opts['subtitle'])):?><p><?=self::esc($opts['subtitle'])?></p><?php endif;?></div>
        <?php if(!empty($opts['action'])):?><?=$opts['action']?><?php endif;?></div>
        <?=$content?></main>
        <footer>Digiti Platform v<?=self::esc($version)?> · <?=self::esc(strtoupper((string)Config::get('app.env','production')))?></footer>
        <?php if($admin):?></div></div><?php endif;?>
        <script>window.Digiti={csrf:<?=json_encode($csrf)?>};document.addEventListener("submit",function(e){if(e.target.querySelector("button.danger")&&!window.confirm("Confirm this action? Historical records may be preserved but active access can be disabled."))e.preventDefault();});</script>
        </body></html><?php exit;
    }

    public static function flash(string $message): void { Auth::start(); $_SESSION['_flash']=$message; }
}
