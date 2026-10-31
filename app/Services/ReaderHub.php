<?php
declare(strict_types=1);

namespace EpaperSaas\Services;

/**
 * Reader-first personal dashboard. Presentation only: no database queries,
 * mutations, external calls or per-request asset generation.
 */
final class ReaderHub
{
    private const SHELVES = [
        ['saved_articles','Saved stories','file-text','/account/saved-articles','Articles to return to'],
        ['bookmarks','Bookmarks','bookmark','/account/bookmarks','Pages worth keeping'],
        ['clips','Clippings','scan-text','/account/clips','Your newspaper extracts'],
        ['offline','Offline editions','hard-drive','/account/offline','Saved for offline reading'],
    ];

    public static function render(array $metrics,?array $resume,array $recent,array $capabilities): string
    {
        $unread=max(0,(int)($metrics['notifications']??0));
        $hasResume=is_array($resume)&&trim((string)($resume['slug']??''))!=='';
        $h='<div class="reader-hub"><header class="rh-intro"><div><span class="rh-eyebrow">'
            .self::icon('book-open').' YOUR PERSONAL NEWSPAPER DESK</span>'
            .'<h1>Good to have you back.</h1>'
            .'<p>Pick up where you left off, revisit your favourites, and explore what matters to you.</p></div>'
            .'<div class="rh-intro-actions"><a class="rh-quiet-link" href="/archive">'.self::icon('newspaper').' Browse editions</a>'
            .($unread>0?'<a class="rh-alert-link" href="/account/notifications">'.self::icon('activity')
                .$unread.' unread '.($unread===1?'update':'updates').'</a>':'')
            .'<button class="rh-install hidden" type="button" data-pwa-install>'.self::icon('hard-drive').' Install app</button></div></header>';

        $h.='<section class="rh-continue" aria-labelledby="rh-continue-title">'
            .'<div class="rh-continue-art">';
        if($hasResume){
            $name=self::editionName($resume);
            $cover=self::imageUrl((string)($resume['thumb_url']??''));
            $h.=$cover!==''?'<img src="'.self::e($cover).'" alt="Page thumbnail from '.self::e($name).'" loading="eager" decoding="async">'
                :'<div class="rh-cover-placeholder" aria-hidden="true">'.self::icon('newspaper').'<strong>ePaper</strong><span>Daily reading</span></div>';
        }else{
            $h.='<div class="rh-cover-placeholder" aria-hidden="true">'.self::icon('newspaper').'<strong>ePaper</strong><span>Discover today</span></div>';
        }
        $h.='</div><div class="rh-continue-details"><span class="rh-kicker">'
            .($hasResume?'CONTINUE WHERE YOU LEFT OFF':'A FRESH PAGE AWAITS').'</span>';
        if($hasResume){
            $page=max(1,(int)($resume['page_number']??1));
            $total=max(1,(int)($resume['page_count']??1));
            $page=min($page,$total);
            $percent=max(0,min(100,(int)round(($page/$total)*100)));
            $when=self::date((string)($resume['last_viewed_at']??''),'d M · H:i');
            $h.='<h2 id="rh-continue-title">'.self::e(self::editionName($resume)).'</h2>'
                .'<p>'.self::e((string)($resume['publication_name']??'Newspaper'))
                .($when!==''?' <span class="rh-dot">·</span> Last opened '.self::e($when):'').'</p>'
                .'<div class="rh-progress-meta"><span>Page '.$page.' of '.$total.'</span><span>'.$percent.'% through</span></div>'
                .'<div class="rh-progress" role="progressbar" aria-label="Edition reading progress"'
                .' aria-valuemin="0" aria-valuemax="100" aria-valuenow="'.$percent.'">'
                .'<span style="width:'.$percent.'%"></span></div>';
            $href=self::editionHref($resume);
            $h.='<div class="rh-continue-actions"><a class="rh-primary" href="'.self::e($href).'">'
                .'Resume reading '.self::icon('chevron-right').'</a>'
                .'<a class="rh-secondary" href="/account/history">Your reading history</a></div>';
        }else{
            $h.='<h2 id="rh-continue-title">Your next story starts here.</h2>'
                .'<p>Choose an edition. We’ll remember your place when you come back.</p>'
                .'<div class="rh-continue-actions"><a class="rh-primary" href="/">Start reading '
                .self::icon('chevron-right').'</a>'
                .'<a class="rh-secondary" href="/archive">Browse the archive</a></div>';
        }
        $h.='</div><div class="rh-continue-decoration" aria-hidden="true"></div></section>';

        $h.='<section class="rh-shelves" aria-labelledby="rh-shelves-title"><div class="rh-section-heading">'
            .'<div><span class="rh-section-kicker">MADE YOURS</span><h2 id="rh-shelves-title">Your library, one tap away</h2></div>'
            .'<a href="/account/library">Open library '.self::icon('chevron-right').'</a></div><div class="rh-shelf-grid">';
        foreach(self::SHELVES as [$key,$label,$icon,$href,$description]){
            $count=max(0,(int)($metrics[$key]??0));
            $h.='<a class="rh-shelf-card rh-shelf-'.self::e($key).'" href="'.self::e($href).'">'
                .'<span class="rh-shelf-icon">'.self::icon($icon).'</span>'
                .'<span class="rh-shelf-value">'.$count.'</span>'
                .'<strong>'.self::e($label).'</strong>'
                .'<small>'.self::e($description).'</small>'
                .'<span class="rh-shelf-arrow" aria-hidden="true">'.self::icon('chevron-right').'</span></a>';
        }
        $h.='</div></section>';

        $h.='<div class="rh-content-grid"><section class="rh-panel rh-recent" aria-labelledby="rh-recent-title">'
            .'<div class="rh-section-heading"><div><span class="rh-section-kicker">PICK UP ANYTIME</span>'
            .'<h2 id="rh-recent-title">Recently opened</h2></div>'
            .'<a href="/account/history">View history '.self::icon('chevron-right').'</a></div>';
        if($recent){
            $h.='<div class="rh-recent-list">';
            foreach(array_slice($recent,0,4) as $row){
                if(!is_array($row)||trim((string)($row['slug']??''))==='')continue;
                $name=self::editionName($row);
                $date=self::date((string)($row['edition_date']??''),'d M Y');
                $page=max(1,(int)($row['page_number']??1));
                $thumb=self::imageUrl((string)($row['thumb_url']??''));
                $h.='<a class="rh-recent-row" href="'.self::e(self::editionHref($row)).'">'
                    .'<span class="rh-recent-cover">'.($thumb!==''?'<img src="'.self::e($thumb).'" alt="" loading="lazy" decoding="async">':self::icon('newspaper')).'</span>'
                    .'<span class="rh-recent-text"><strong>'.self::e($name).'</strong>'
                    .'<small>'.self::e((string)($row['publication_name']??''))
                    .($date!==''?' · '.self::e($date):'').' · Page '.$page.'</small></span>'
                    .'<span class="rh-recent-arrow">'.self::icon('chevron-right').'</span></a>';
            }
            $h.='</div>';
        }else{
            $h.='<div class="rh-empty">'.self::icon('book-open')
                .'<strong>Your reading history will appear here.</strong>'
                .'<p>Open an edition to start building your reading list.</p>'
                .'<a href="/archive">Explore editions</a></div>';
        }
        $h.='</section><aside class="rh-panel rh-tools" aria-labelledby="rh-tools-title">'
            .'<div class="rh-section-heading"><div><span class="rh-section-kicker">READ YOUR WAY</span>'
            .'<h2 id="rh-tools-title">Your reading tools</h2></div></div>';
        $available=0;
        foreach($capabilities as $cap)if(!empty($cap[1]))$available++;
        $h.='<p class="rh-tools-lead">Your reader includes <strong>'.$available.'</strong> active reading tools.</p>'
            .'<div class="rh-tools-shortcuts">'
            .'<a href="/account/bookmarks">'.self::icon('bookmark').'<span>Bookmarked pages</span>'.self::icon('chevron-right').'</a>'
            .'<a href="/account/clips">'.self::icon('scan-text').'<span>Clipping gallery</span>'.self::icon('chevron-right').'</a>'
            .'<a href="/account/offline">'.self::icon('hard-drive').'<span>Offline library</span>'.self::icon('chevron-right').'</a>'
            .'</div><details class="rh-tool-details"><summary>View included features '.self::icon('chevron-right').'</summary>'
            .'<ul class="rh-tool-list">';
        foreach($capabilities as [$name,$enabled]){
            $h.='<li class="'.($enabled?'is-enabled':'is-unavailable').'">'
                .'<span>'.($enabled?'✓':'–').'</span><strong>'.self::e((string)$name).'</strong>'
                .'<small>'.($enabled?'Included':'Not in your current plan').'</small></li>';
        }
        $h.='</ul></details><a class="rh-manage-plan" href="/account/subscription">'
            .self::icon('credit-card').' Manage your plan '.self::icon('chevron-right').'</a></aside></div>';

        $h.='<section class="rh-explore" aria-labelledby="rh-explore-title"><div>'
            .'<span class="rh-section-kicker">KEEP EXPLORING</span>'
            .'<h2 id="rh-explore-title">There’s always another edition.</h2>'
            .'<p>Find today’s newspaper or revisit stories from the archive.</p></div>'
            .'<a class="rh-explore-link" href="/archive">Browse the archive '.self::icon('chevron-right').'</a></section>'
            .'</div>';
        return $h;
    }

    private static function icon(string $name): string
    {
        // Reuse the tested local SVG catalogue; never fetch remote icon assets.
        return AdminIcons::svg($name);
    }

    private static function editionName(array $row): string
    {
        $title=trim((string)($row['title']??''));
        if($title!=='')return $title;
        $publication=trim((string)($row['publication_name']??''));
        $date=self::date((string)($row['edition_date']??''),'d M Y');
        return trim(($publication!==''?$publication:'Newspaper').($date!==''?' · '.$date:''));
    }

    private static function editionHref(array $row): string
    {
        $slug=trim((string)($row['slug']??''));
        $page=max(1,(int)($row['page_number']??1));
        return '/edition/'.rawurlencode($slug).'/page/'.$page;
    }

    private static function imageUrl(string $url): string
    {
        $url=trim($url);
        if($url===''||preg_match('/[\x00-\x1f]/',$url))return '';
        if(str_starts_with($url,'/')&&!str_starts_with($url,'//'))return $url;
        if((bool)preg_match('#^https?://[^/\s]+#i',$url))return $url;
        return '';
    }

    private static function date(string $raw,string $format): string
    {
        $time=$raw!==''?strtotime($raw):false;
        return $time!==false?date($format,$time):'';
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    }
}
