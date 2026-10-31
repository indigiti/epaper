<?php
declare(strict_types=1);
namespace EpaperSaas\Services;
use EpaperSaas\Core\View;

/** DB-free role-aware feature launcher, shared with the sidebar catalog. */
final class AdminWorkspace
{
    /** @param array<string,list<array{0:string,1:string,2:string,3:string}>> $groups */
    public static function render(array $groups,string $scope): string
    {
        $e=static fn(mixed $x):string=>View::e($x);
        $all=AdminFeatureCatalog::flatten($groups);
        $preferred=['/admin/editions','/admin/processing','/admin/ai-review',
            '/admin/navigation','/admin/system/testing','/admin/system/health'];
        $quick=[];
        foreach($preferred as $target)foreach($all as $item)if($item[1]===$target){$quick[]=$item;break;}
        if(!$quick)$quick=array_slice($all,0,5);
        $h='<section class="admin-launchpad" data-admin-launchpad data-storage-scope="'.$e($scope).'">'
            .'<div class="launchpad-head"><div><span class="eyebrow">ALL PUBLISHER TOOLS</span>'
            .'<h2>Tools &amp; shortcuts</h2><p>Find, pin, or search tools without crowding your Publishing Desk.</p></div>'
            .'<input type="search" data-admin-feature-search placeholder="Find editions, clipping, settings…" aria-label="Search publisher tools" autocomplete="off"></div>'
            .'<nav class="launchpad-quick" aria-label="Frequent publisher tasks">';
        foreach(array_slice($quick,0,6) as $item)
            $h.='<a href="'.$e($item[1]).'"><span class="launchpad-icon" aria-hidden="true">'.AdminIcons::svg($item[3]).'</span>'
                .'<span>'.$e($item[0]).'</span><span aria-hidden="true">→</span></a>';
        $h.='</nav><div class="launchpad-pinned" data-admin-pinned-area hidden>'
            .'<strong>Pinned tools <small>Saved on this device</small></strong>'
            .'<div class="launchpad-pinned-links" data-admin-pinned-links></div></div>'
            .'<details class="launchpad-directory" data-feature-directory><summary>'
            .'Browse all available features <small>'.count($all).' role-accessible tools</small></summary>'
            .'<div class="launchpad-groups">';
        foreach($groups as $name=>$items){
            $h.='<section class="launchpad-group" data-feature-group><h3>'.$e(ucwords(strtolower($name))).'</h3><div class="launchpad-items">';
            foreach($items as [$label,$url,$hint,$icon])
                $h.='<div class="launchpad-item" data-feature-item data-search-text="'.$e($name.' '.$label.' '.$hint).'">'
                    .'<a href="'.$e($url).'"><span class="launchpad-icon" aria-hidden="true">'.AdminIcons::svg($icon).'</span>'
                    .'<span class="launchpad-item-text"><strong>'.$e($label).'</strong><small>'.$e($hint).'</small></span></a>'
                    .'<button type="button" class="launchpad-pin" data-feature-pin="'.$e($url).'"'
                    .' aria-label="Pin '.$e($label).'" aria-pressed="false" title="Pin this tool">☆</button></div>';
            $h.='</div></section>';
        }
        return $h.'</div><p data-feature-empty hidden>No matching features available to your role.</p></details></section>';
    }

    /** @param array<string,list<array{0:string,1:string,2:string,3:string}>> $groups */
    public static function shortcuts(array $groups): string
    {
        $all=AdminFeatureCatalog::flatten($groups);
        $html='';
        foreach(['/admin/publications','/admin/navigation','/admin/settings',
                 '/admin/processing','/admin/system/testing','/admin/system/health'] as $target)
            foreach($all as $item)if($item[1]===$target){
                $html.='<a href="'.View::e($target).'">'.View::e($item[0]).' →</a>';break;
            }
        return $html;
    }
}
