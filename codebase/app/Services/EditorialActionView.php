<?php
declare(strict_types=1);
namespace EpaperSaas\Services;
use EpaperSaas\Core\View;

/** HTML renderer only: all actions are links to existing protected screens. */
final class EditorialActionView
{
    public static function render(array $snapshot): string
    {
        if(empty($snapshot['groups']))return '';
        $e=static fn(mixed $v):string=>View::e($v);
        $h='<section class="editorial-actions" aria-labelledby="editorial-actions-heading">'
          .'<div class="editorial-actions-heading"><div><span class="eyebrow">TODAY’S WORK</span>'
          .'<h2 id="editorial-actions-heading">Editorial Action Center</h2>'
          .'<p>Find editions, failed processing and pending AI clipping reviews in one place.</p></div>'
          .'<span class="editorial-action-caption">Tenant-scoped · Read only</span></div>'
          .'<div class="editorial-action-grid">';
        foreach($snapshot['groups'] as $g){
            $status=(string)$g['status'];
            $h.='<article class="editorial-action-card"><div class="editorial-action-head">'
                .'<h3>'.$e($g['title']).'</h3>'
                .($g['count']!==null?'<span class="editorial-action-badge">'.$e($g['count']).' pending</span>':
                  (in_array($status,['DISABLED','UNAVAILABLE'],true)
                     ?'<span class="editorial-action-badge">'.$e($status).'</span>':''))
                .'</div><p class="editorial-action-description">'.$e($g['description']).'</p>';
            if($g['items']){
                $h.='<ul class="editorial-action-list">';
                foreach($g['items'] as $item){
                    $url=(string)($item['url']??'');
                    if(!str_starts_with($url,'/admin/')||str_contains($url,'//'))continue;
                    $h.='<li><a href="'.$e($url).'"><span><strong>'.$e($item['title']).'</strong>'
                       .'<small>'.$e($item['detail']).'</small></span><span aria-hidden="true">↗</span></a></li>';
                }
                $h.='</ul>';
            }else{
                $h.='<p class="editorial-action-empty">'.$e($status==='DISABLED'?'Feature not enabled':
                    ($status==='UNAVAILABLE'?'Unable to read this queue':'No items in the latest sample')).'</p>';
            }
            $url=(string)($g['url']??'');
            if(str_starts_with($url,'/admin/')&&!str_contains($url,'//'))
                $h.='<a class="editorial-action-open" href="'.$e($url).'">Open module →</a>';
            $h.='</article>';
        }
        return $h.'</div><p class="editorial-action-footnote">Failed-job events may be historical. No processing job is retried or edition published automatically.</p></section>';
    }
}
