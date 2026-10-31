<?php
declare(strict_types=1);

namespace EpaperSaas\Services;

/** DB-free, tenant-safe dashboard visualisations using already fetched counts. */
final class AdminDashboardVisuals
{
    private const STATES = [
        'PUBLISHED'       => ['Published','bar-published'],
        'READY'           => ['Ready','bar-ready'],
        'SCHEDULED'       => ['Scheduled','bar-scheduled'],
        'PROCESSING'      => ['Processing','bar-processing'],
        'NEEDS_ATTENTION' => ['Needs attention','bar-attention'],
    ];

    public static function pipeline(array $counts): string
    {
        $total=0;
        foreach($counts as $count)$total+=max(0,(int)$count);
        $max=max(1,...array_map(static fn(string $state):int=>max(0,(int)($counts[$state]??0)),array_keys(self::STATES)));
        $h='<section class="panel studio-pipeline-card" aria-labelledby="studio-pipeline-heading">'
            .'<div class="section-head"><div><span class="studio-section-kicker">PUBLISHING OVERVIEW</span>'
            .'<h2 id="studio-pipeline-heading">Edition pipeline</h2>'
            .'<p class="muted">Current edition counts by workflow status, not a historical trend.</p></div>'
            .'<span class="studio-pipeline-total"><strong>'.$total.'</strong> total editions</span></div>'
            .'<div class="studio-pipeline-bars" role="list">';
        foreach(self::STATES as $state=>[$label,$class]){
            $number=max(0,(int)($counts[$state]??0));
            $percentage=(int)round($number*100/$max);
            $h.='<a role="listitem" href="/admin/editions?status='.$state.'"'
                .' class="studio-pipeline-row" aria-label="'.$label.': '.$number.' editions">'
                .'<span class="studio-pipeline-label">'.$label.'</span>'
                .'<span class="studio-pipeline-track"><span class="'.$class.'"'
                .' style="width:'.$percentage.'%"></span></span>'
                .'<strong>'.$number.'</strong></a>';
        }
        return $h.'</div></section>';
    }
}
