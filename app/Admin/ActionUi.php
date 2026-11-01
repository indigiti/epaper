<?php
declare(strict_types=1);

namespace Digiti\Admin;

use Digiti\Core\{Http,View};

/**
 * One accessible control vocabulary for existing administration records.
 * Navigation is GET. Every mutating command is a CSRF-protected POST.
 */
final class ActionUi
{
    private const ICONS=[
        'view'=>'<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
        'edit'=>'<path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L9 17l-4 1 1-4Z"/>',
        'plus'=>'<path d="M12 5v14M5 12h14"/>',
        'save'=>'<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8M7 3v5h8"/>',
        'pause'=>'<circle cx="12" cy="12" r="9"/><path d="M10 9v6M14 9v6"/>',
        'play'=>'<circle cx="12" cy="12" r="9"/><path d="m10 8 6 4-6 4Z"/>',
        'archive'=>'<rect x="3" y="4" width="18" height="4" rx="1"/><path d="M5 8v12h14V8M10 12h4"/>',
        'restore'=>'<path d="M3 11a9 9 0 1 1 2.6 6.4M3 4v7h7"/>',
        'more'=>'<circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/>',
        'rotate'=>'<path d="M2 12a10 10 0 0 1 17-7l2 2M21 2v5h-5M22 12a10 10 0 0 1-17 7l-2-2M3 22v-5h5"/>',
        'check'=>'<path d="m5 12 4 4L19 6"/>',
        'trash'=>'<path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14M10 10v6M14 10v6"/>',
    ];

    public static function icon(string $name): string
    {
        return '<svg class="platform-action-svg" width="17" height="17" viewBox="0 0 24 24"'
            .' fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"'
            .' aria-hidden="true">'.(self::ICONS[$name]??self::ICONS['more']).'</svg>';
    }

    public static function link(string $url,string $label,string $icon='edit'): string
    {
        return '<a class="platform-action-button" href="'.View::esc($url).'"'
            .' aria-label="'.View::esc($label).'" title="'.View::esc($label).'">'
            .self::icon($icon).'<span class="platform-action-mobile">'.View::esc($label).'</span></a>';
    }

    public static function form(string $url,string $label,string $icon,array $extra=[],bool $danger=false): string
    {
        $html='<form method="post" action="'.View::esc($url).'" class="platform-action-form">'
            .'<input type="hidden" name="_csrf" value="'.View::esc(Http::csrf()).'">';
        foreach($extra as $key=>$value)
            $html.='<input type="hidden" name="'.View::esc($key).'" value="'.View::esc($value).'">';
        return $html.'<button type="submit" class="platform-action-button'.($danger?' danger':'').'"'
            .' aria-label="'.View::esc($label).'" title="'.View::esc($label).'"'
            .($danger?' data-confirm="This archives the record and may interrupt connected users or websites. Continue?"':'').'>'
            .self::icon($icon).'<span class="platform-action-mobile">'.View::esc($label).'</span>'
            .'</button></form>';
    }

    public static function row(string $entity,string $guid,string $status,string $base): string
    {
        $url=$base.'/'.rawurlencode($guid);
        $html='<div class="platform-action-bar">';
        if($status==='REMOVED') {
            return $html.self::link($url.'/restore','Review & restore','restore')
                .'</div>';
        }
        if($entity==='domain') {
            $html.=self::link('/admin/onboarding?domain='.rawurlencode($guid).'#domain-review','View onboarding','view');
            $html.=self::link($url.'/edit','Edit domain','edit');
            $html.='<details class="platform-action-more"><summary aria-label="More domain actions" title="More domain actions">'.self::icon('more').'</summary>'
                .'<div class="platform-action-more-menu">'
                .self::form($url.'/verify','Verify DNS','check')
                .self::form($url.'/remove','Archive domain','archive',[],true)
                .'</div></details>';
            return $html.'</div>';
        }
        $html.=self::link($url.'/edit','Edit','edit');
        if($entity==='service-account') {
            if($status==='ACTIVE') $html.=self::form($url.'/status','Revoke','pause',['status'=>'REVOKED']);
            $html.='<details class="platform-action-more"><summary title="More actions" aria-label="More actions">'.self::icon('more').'</summary><div class="platform-action-more-menu">'
                .self::form($url.'/rotate','Rotate token','rotate')
                .self::form($url.'/remove','Archive','archive',[],true).'</div></details>';
            return $html.'</div>';
        }
        if($status==='ACTIVE')$html.=self::form($url.'/status','Deactivate','pause',['status'=>'SUSPENDED']);
        elseif($status==='SUSPENDED')$html.=self::form($url.'/status','Activate','play',['status'=>'ACTIVE']);
        $html.='<details class="platform-action-more"><summary title="More actions" aria-label="More actions">'.self::icon('more').'</summary><div class="platform-action-more-menu">'
            .self::form($url.'/remove','Archive','archive',[],true).'</div></details>';
        return $html.'</div>';
    }

    public static function add(string $url,string $label): string
    {
        return '<a class="button primary platform-create-button" href="'.View::esc($url).'">'
            .self::icon('plus').' '.View::esc($label).'</a>';
    }
}
