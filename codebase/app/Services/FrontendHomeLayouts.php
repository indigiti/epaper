<?php
declare(strict_types=1);

namespace EpaperSaas\Services;

/**
 * Four deliberately different, data-backed home page structures.
 *
 * The original Default home page is kept in App::home() without modification.
 * All cards link only to actual published content, never demo headlines.
 */
final class FrontendHomeLayouts
{
    public static function render(string $theme, array $data): string
    {
        $editions = is_array($data['editions'] ?? null) ? $data['editions'] : [];
        $publications = is_array($data['publications'] ?? null) ? $data['publications'] : [];
        $articles = is_array($data['articles'] ?? null) ? $data['articles'] : [];
        $highlights = is_array($data['highlights'] ?? null) ? $data['highlights'] : [];
        $brand = trim((string)($data['brand'] ?? 'ePaper'));
        $tagline = trim((string)($data['tagline'] ?? ''));
        $latest = $editions[0] ?? null;
        $intro = '<div class="fth-identity"><span class="fth-kicker">DIGITAL NEWSSTAND</span><span>'
            .self::e($brand).'</span></div>';
        if (!$latest) {
            return '<div class="fth-home fth-empty">'.$intro
                .'<section class="fth-empty-state"><h1>New editions are on the way.</h1>'
                .'<p>Browse our publications or return when the next issue is published.</p>'
                .'<a class="fth-action" href="/archive">Explore the archive</a></section>'
                .self::publicationSection($publications,$editions).'</div>';
        }

        return match ($theme) {
            'Newsstand Modern' => self::newsstand($latest,$editions,$publications,$articles,$intro,$tagline),
            'Editorial Portal' => self::editorial($latest,$editions,$publications,$articles,$highlights,$intro),
            'Classic Newspaper' => self::newspaper($latest,$editions,$publications,$articles,$intro),
            'Digital Magazine' => self::magazine($latest,$editions,$publications,$articles,$intro),
            default => '',
        };
    }

    private static function newsstand(array $latest,array $editions,array $publications,array $articles,string $intro,string $tagline): string
    {
        $image = self::articleImage($articles[0] ?? []);
        $h = '<div class="fth-home fth-newsstand">'.$intro.'<section class="fth-hero fth-newsstand-hero">'
            .($image !== ''?'<img class="fth-hero-backdrop" src="'.self::e($image).'" alt="" loading="eager">':'')
            .'<div class="fth-hero-overlay"></div><div class="fth-hero-body"><span class="fth-kicker">YOUR DAILY NEWSSTAND</span>'
            .'<h1>Today’s news at your fingertips</h1><p>'.self::e($tagline!==''?$tagline:'Read the latest newspapers, anytime and anywhere.').'</p>'
            .'<form action="/search" method="get" class="fth-search" role="search"><label class="sr-only" for="fth-search-q">Search articles and editions</label>'
            .'<input id="fth-search-q" name="q" type="search" placeholder="Search articles or editions…" required>'
            .'<button type="submit" aria-label="Search">Search</button></form>'
            .'<div class="fth-hero-actions"><a class="fth-action" href="'.self::editionHref($latest).'">Read latest edition</a>'
            .'<a class="fth-link-light" href="/archive">Browse archive →</a></div></div></section>';
        $h.=self::editionSection('Latest editions',$editions,4,'fth-cover-grid');
        $h.=self::publicationSection($publications,$editions);
        $h.=self::articleSection('Latest stories',$articles,4);
        return $h.self::archiveFooter().'</div>';
    }

    private static function editorial(array $latest,array $editions,array $publications,array $articles,array $highlights,string $intro): string
    {
        $lead = $articles[0] ?? [];
        $side = array_slice($articles,1,4);
        $leadTitle = trim((string)($lead['title']??'')) ?: (string)($latest['publication_name']??'Latest edition');
        $leadHref = self::articleHref($lead) ?: self::editionHref($latest);
        $leadImage = self::articleImage($lead) ?: self::cover($latest);
        $h='<div class="fth-home fth-editorial">'.$intro.'<div class="fth-ticker"><strong>THE LATEST</strong><span>'
            .self::e($leadTitle).'</span><a href="'.self::e($leadHref).'">Explore →</a></div>'
            .'<section class="fth-editorial-lead"><a class="fth-feature-story" href="'.self::e($leadHref).'">'
            .self::image($leadImage,$leadTitle,'fth-feature-image',false)
            .'<div class="fth-feature-copy"><span class="fth-kicker">FEATURED STORY</span><h1>'.self::e($leadTitle).'</h1>'
            .(!empty($lead['deck'])?'<p>'.self::e((string)$lead['deck']).'</p>':'')
            .'<span class="fth-read-more">Read story →</span></div></a>'
            .'<div class="fth-story-sidebar"><h2>Top stories</h2>';
        if($side){
            foreach($side as $article){$h.=self::storyRow($article);}
        }else{
            $h.='<p class="fth-muted">Explore the latest newspaper edition.</p>'.self::editionCard($latest);
        }
        $h.='</div></section><div class="fth-editorial-bottom"><section><div class="fth-section-title"><h2>Today’s edition</h2><a href="/archive">Archive →</a></div>'
            .'<div class="fth-editorial-edition">'.self::editionCard($latest).'</div></section><section>';
        $h.=self::articleSection('From the newsroom',array_slice($articles,1),4,'fth-article-list');
        if(!$articles && $highlights){
            $h.='<div class="fth-highlight-list">';
            foreach(array_slice($highlights,0,4) as $item){
                $target=self::safeInternalHref((string)($item['target_url']??''),'/archive');
                $h.='<a href="'.self::e($target).'"><strong>'.self::e((string)($item['title']??'')).'</strong></a>';
            }
            $h.='</div>';
        }
        $h.='</section></div>'.self::editionSection('More editions',$editions,4,'fth-cover-grid');
        return $h.self::publicationSection($publications,$editions).self::archiveFooter().'</div>';
    }

    private static function newspaper(array $latest,array $editions,array $publications,array $articles,string $intro): string
    {
        $lead=$articles[0]??[];
        $headline=trim((string)($lead['title']??'')) ?: 'Your newspaper, beautifully presented';
        $href=self::articleHref($lead) ?: self::editionHref($latest);
        $leadImage=self::articleImage($lead) ?: self::cover($latest);
        $h='<div class="fth-home fth-newspaper">'
            .'<header class="fth-masthead"><div class="fth-masthead-meta"><span>'.self::e(date('l, d F Y',strtotime((string)($latest['edition_date']??'now')))).'</span>'
            .'<span>Digital Edition</span></div><h1>'.self::e((string)($latest['publication_name']??'Newspaper')).'</h1>'
            .'<div class="fth-masthead-nav"><a href="/">Home</a><a href="/archive">Editions</a><a href="/search">Search</a></div></header>'
            .'<section class="fth-paper-lead"><div class="fth-paper-copy"><span class="fth-kicker">LEADING STORY</span>'
            .'<h2><a href="'.self::e($href).'">'.self::e($headline).'</a></h2>'
            .'<p>'.self::e(trim((string)($lead['deck']??'')) ?: 'Explore today’s front page and the stories shaping our community.').'</p>'
            .'<a class="fth-text-link" href="'.self::e($href).'">Read full story →</a></div>'
            .'<a class="fth-paper-picture" href="'.self::e($href).'">'.self::image($leadImage,$headline,'',false).'</a></section>'
            .self::articleSection('More headlines',array_slice($articles,1),3,'fth-paper-headlines')
            .self::editionSection('Today’s editions',$editions,4,'fth-cover-grid')
            .self::publicationSection($publications,$editions);
        return $h.self::archiveFooter().'</div>';
    }

    private static function magazine(array $latest,array $editions,array $publications,array $articles,string $intro): string
    {
        $lead=$articles[0]??[];
        $image=self::articleImage($lead);
        $headline=trim((string)($lead['title']??'')) ?: 'Stories that matter';
        $href=self::articleHref($lead) ?: self::editionHref($latest);
        $h='<div class="fth-home fth-magazine">'.$intro
            .'<section class="fth-hero fth-magazine-hero">'
            .($image!==''?'<img class="fth-hero-backdrop" src="'.self::e($image).'" alt="" loading="eager">':'')
            .'<div class="fth-hero-overlay"></div><div class="fth-hero-body"><span class="fth-kicker">THE DAILY EDIT</span>'
            .'<h1>'.self::e($headline).'</h1><p>Explore the latest editions and stories from your trusted publications.</p>'
            .'<a class="fth-action" href="'.self::e($href).'">'.($lead?'Explore story':'Explore edition').' →</a></div></section>';
        $h.='<section class="fth-mag-channels"><div class="fth-section-title"><h2>Explore publications</h2></div><div class="fth-channel-grid">';
        foreach(array_slice($publications,0,8) as $idx=>$p){
            $slug=(string)($p['slug']??'');if($slug==='')continue;
            $name=(string)($p['name']??'Publication');
            $h.='<a class="fth-channel fth-channel-'.($idx%6).'" href="/epaper/'.rawurlencode($slug).'">'
                .'<span class="fth-channel-symbol" aria-hidden="true">'.self::e(mb_substr($name,0,1)).'</span><span>'.self::e($name).'</span><span aria-hidden="true">→</span></a>';
        }
        $h.='</div></section>'
            .self::editionSection('Featured editions',$editions,6,'fth-cover-grid')
            .self::articleSection('Latest stories',$articles,6,'fth-mag-stories')
            .self::publicationSection($publications,$editions);
        return $h.self::archiveFooter().'</div>';
    }

    private static function editionSection(string $title,array $editions,int $limit,string $css): string
    {
        if(!$editions)return '';
        $h='<section class="fth-section"><div class="fth-section-title"><h2>'.self::e($title).'</h2><a href="/archive">View all →</a></div><div class="'.self::e($css).'">';
        foreach(array_slice($editions,0,$limit) as $ed){$h.=self::editionCard($ed);}
        return $h.'</div></section>';
    }

    private static function editionCard(array $edition): string
    {
        $name=(string)($edition['publication_name']??'Newspaper');
        $date=(string)($edition['edition_date']??'');
        $image=self::cover($edition);
        return '<a class="fth-edition-card" href="'.self::editionHref($edition).'"><span class="fth-edition-cover">'
            .self::image($image,'Front page of '.$name,'',true).'</span><strong>'.self::e($name).'</strong>'
            .($date!==''?'<small>'.self::e(date('d M Y',strtotime($date))).'</small>':'')
            .'</a>';
    }

    private static function publicationSection(array $publications,array $editions): string
    {
        if(!$publications)return '';
        $covers=[];
        foreach($editions as $edition){
            $slug=(string)($edition['publication_slug']??'');
            if($slug!==''&&!isset($covers[$slug]))$covers[$slug]=self::cover($edition);
        }
        $h='<section class="fth-section fth-publications"><div class="fth-section-title"><h2>Our publications</h2></div><div class="fth-publications-grid">';
        foreach(array_slice($publications,0,8) as $p){
            $slug=(string)($p['slug']??'');if($slug==='')continue;
            $name=(string)($p['name']??'Publication');
            $h.='<a class="fth-publication-card" href="/epaper/'.rawurlencode($slug).'">'
                .'<span class="fth-publication-art">'.self::image($covers[$slug]??'',$name.' front page','',true).'</span>'
                .'<strong>'.self::e($name).'</strong><small>Browse editions →</small></a>';
        }
        return $h.'</div></section>';
    }

    private static function articleSection(string $title,array $articles,int $limit,string $css='fth-articles-grid'): string
    {
        if(!$articles)return '';
        $h='<section class="fth-section fth-articles"><div class="fth-section-title"><h2>'.self::e($title).'</h2><a href="/search">Search stories →</a></div><div class="'.self::e($css).'">';
        foreach(array_slice($articles,0,$limit) as $article){
            $href=self::articleHref($article);
            if($href==='')continue;
            $title=(string)($article['title']??'Untitled article');
            $h.='<a class="fth-article-card" href="'.self::e($href).'">'
                .self::image(self::articleImage($article),$title,'',true)
                .'<span class="fth-article-info"><small>'.self::e((string)($article['publication_name']??'News')).'</small>'
                .'<strong>'.self::e($title).'</strong></span></a>';
        }
        return $h.'</div></section>';
    }

    private static function storyRow(array $article): string
    {
        $href=self::articleHref($article);
        if($href==='')return '';
        return '<a class="fth-story-row" href="'.self::e($href).'">'
            .self::image(self::articleImage($article),(string)($article['title']??''),'',true)
            .'<span><strong>'.self::e((string)($article['title']??'Story')).'</strong>'
            .'<small>'.self::e((string)($article['publication_name']??'News')).'</small></span></a>';
    }

    private static function archiveFooter(): string
    {
        return '<section class="fth-archive-end"><div><span class="fth-kicker">ARCHIVE</span><h2>Find every front page.</h2>'
            .'<p>Explore previous editions by publication and date.</p></div>'
            .'<a href="/archive" class="fth-action">Browse the archive →</a></section>';
    }

    private static function image(string $src,string $alt,string $class='',bool $lazy=true): string
    {
        if($src==='')return '<span class="fth-photo-placeholder" aria-hidden="true"><span>ePaper</span></span>';
        return '<img'.($class!==''?' class="'.self::e($class).'"':'').' src="'.self::e($src).'" alt="'.self::e($alt).'" loading="'.($lazy?'lazy':'eager').'" decoding="async">';
    }

    private static function articleImage(array $a): string
    {
        $url=trim((string)($a['primary_image_url']??''));
        return self::isSafeImageUrl($url)?$url:'';
    }

    private static function isSafeImageUrl(string $url): bool
    {
        if($url===''||preg_match('/[\x00-\x1f]/',$url))return false;
        return (str_starts_with($url,'/')&&!str_starts_with($url,'//'))
            || (bool)preg_match('#^https?://[^/\s]+#i',$url);
    }

    private static function cover(array $ed): string
    {
        $url=trim((string)($ed['cover_url']??''));
        return self::isSafeImageUrl($url)?$url:'';
    }

    private static function editionHref(array $ed): string
    {
        return '/edition/'.rawurlencode((string)($ed['slug']??'')).'/page/1';
    }

    private static function articleHref(array $article): string
    {
        $slug=trim((string)($article['slug']??''));
        return $slug!==''?'/article/'.rawurlencode($slug):'';
    }

    private static function safeInternalHref(string $href,string $fallback): string
    {
        return str_starts_with($href,'/')&&!str_starts_with($href,'//')?$href:$fallback;
    }

    private static function e(string $v): string
    {
        return htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    }
}
