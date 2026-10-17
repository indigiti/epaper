<?php
namespace Digiti\Core;

final class View
{
    public static function esc(mixed $v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
    public static function layout(string $title,string $content,array $opts=[]): never
    {
        $admin=Auth::admin(); $csrf=Http::csrf(); $version=trim((string)@file_get_contents(dirname(__DIR__,2).'/VERSION'));
        $notice=$_SESSION['_flash']??null; unset($_SESSION['_flash']);
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light dark"><title><?=self::esc($title)?> · Digiti Platform</title><link rel="stylesheet" href="/assets/app.css?v=<?=self::esc($version)?>"></head><body>
        <header class="topbar"><a class="brand" href="/admin"><span class="mark">D</span><span>Digiti Platform</span></a><?php if($admin):?><nav><a href="/admin">Overview</a><a href="/admin/organisations">Organisations</a><a href="/admin/products">Products</a><a href="/admin/domains">Domains</a><a href="/admin/commercial">Commercial</a><a href="/admin/operations">Operations</a><a href="/admin/security">Security</a><a href="/admin/impersonation">Support</a></nav><div class="user"><?=self::esc($admin['display_name'])?> · <form method="post" action="/logout" class="inline-form"><input type="hidden" name="_csrf" value="<?=self::esc($csrf)?>"><button class="small">Sign out</button></form></div><?php endif;?></header>
        <?php if($admin):?><nav class="subnav" aria-label="Platform management"><a href="/admin/users">Users</a><a href="/admin/applications">Applications</a><a href="/admin/product-tenants">Product Tenants</a><a href="/admin/service-accounts">Service Accounts</a><a href="/admin/subscriptions">Subscriptions</a><a href="/admin/billing">Billing</a><a href="/admin/audit">Audit</a><a href="/admin/errors">Errors</a><a href="/admin/health">Health</a></nav><?php endif;?>
        <?php if($notice):?><div class="toast"><?=self::esc($notice)?></div><?php endif;?>
        <main class="shell"><div class="page-head"><div><h1><?=self::esc($title)?></h1><?php if(!empty($opts['subtitle'])):?><p><?=self::esc($opts['subtitle'])?></p><?php endif;?></div><?php if(!empty($opts['action'])):?><?=$opts['action']?><?php endif;?></div><?=$content?></main><footer>Digiti Platform v<?=self::esc($version)?> · <?=self::esc(strtoupper((string)Config::get('app.env','production')))?></footer><script>window.Digiti={csrf:<?=json_encode($csrf)?>};document.addEventListener("submit",function(e){if(e.target.querySelector("button.danger")&&!window.confirm("Confirm this action? Historical records may be preserved but active access can be disabled."))e.preventDefault();});</script></body></html><?php exit;
    }
    public static function flash(string $message): void { Auth::start(); $_SESSION['_flash']=$message; }
}
