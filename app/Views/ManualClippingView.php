<?php
declare(strict_types=1);
namespace EpaperSaas\Views;

use EpaperSaas\Core\{Http,Uuid,View};

/** Semantic, progressive-enhancement interface for manual editorial clipping. */
final class ManualClippingView
{
    /**
     * Edition collections; only pages for the chosen edition are rendered.
     * All links are tenant-authorized through the existing controller.
     */
    public static function index(
        array $editions,array $pages,array $publications,?array $chosen,array $filters
    ): string
    {
        $publication=(string)($filters['publication']??'');
        $date=(string)($filters['date']??'');
        $pageNo=(int)($filters['page']??1);
        $params=[];
        if($publication!=='')$params['publication']=$publication;
        if($date!=='')$params['date']=$date;
        $currentQuery=http_build_query($params,'','&',PHP_QUERY_RFC3986);
        $queryPrefix='/admin/manual-clipping?';
        $h='<section class="manual-clipping-page manual-editions-page admin-section">'
          .'<div class="manual-titlebar"><div>'
          .'<span class="eyebrow">EDITORIAL WORKSPACE</span><h1>Manual clipping</h1>'
          .'<p>Choose an edition, then select the newspaper page you want to clip. No AI required.</p></div>'
          .'<a class="btn ghost" href="/admin/editions">All editions →</a></div>'
          .'<section class="panel manual-browser-panel"><div class="section-head">'
          .'<div><h2>Edition collections</h2><p class="muted">Organized by publication and edition date, rather than individual pages.</p></div></div>'
          .'<form method="get" action="/admin/manual-clipping" class="manual-edition-filters" aria-label="Filter editions">'
          .'<label>Publication<select name="publication"><option value="">All publications</option>';
        foreach($publications as $pub){
            $id=(string)Uuid::fromBinary($pub['publication_guid']);
            $h.='<option value="'.View::e($id).'"'.($id===$publication?' selected':'').'>'
                .View::e($pub['name']).'</option>';
        }
        $h.='</select></label><label>Edition date<input type="date" name="date" value="'.View::e($date).'"></label>'
          .'<div class="manual-filter-actions"><button class="btn primary" type="submit">Apply filters</button>'
          .'<a class="btn ghost" href="/admin/manual-clipping">Clear</a></div></form>'
          .'<div class="manual-collection-layout"><div class="manual-edition-list" aria-label="Edition collections">';
        foreach($editions as $edition){
            $id=(string)Uuid::fromBinary($edition['edition_guid']);
            $active=$chosen!==null&&$chosen['edition_guid']===$edition['edition_guid'];
            $name=trim((string)($edition['edition_title']??''))?:$edition['publication_name'];
            $query=$params+['p'=>$pageNo,'edition'=>$id];
            $url=$queryPrefix.http_build_query($query,'','&',PHP_QUERY_RFC3986);
            $ready=(int)($edition['ready_pages']??0);
            $h.='<a class="manual-edition-card'.($active?' active':'').'" href="'.View::e($url).'"'
                .($active?' aria-current="true"':'').'>'
                .'<span class="manual-page-icon">'.View::icon('newspaper').'</span>'
                .'<span class="manual-page-copy"><strong>'.View::e($name).'</strong>'
                .'<small>'.View::e($edition['publication_name']).' · '.View::e($edition['edition_date']).'</small>'
                .'<span class="manual-edition-count">'.$ready.' '.($ready===1?'page':'pages').' ready</span></span>'
                .View::icon('chev-right').'</a>';
        }
        if(!$editions)$h.='<div class="manual-empty">No processed editions match these filters. Check your publication, date or processing status.</div>';
        $h.='</div><section class="manual-edition-pages" aria-labelledby="manual-chosen-heading">';
        if($chosen!==null){
            $name=trim((string)($chosen['edition_title']??''))?:$chosen['publication_name'];
            $h.='<div class="manual-selected-heading"><span class="eyebrow">SELECTED EDITION</span>'
               .'<h3 id="manual-chosen-heading">'.View::e($name).'</h3>'
               .'<p>'.View::e($chosen['publication_name']).' · '.View::e($chosen['edition_date'])
               .' · '.(int)$chosen['ready_pages'].' pages ready</p></div>'
               .'<div class="manual-page-picker">';
            foreach($pages as $row){
                $id=(string)Uuid::fromBinary($row['page_guid']);
                $number=(int)$row['page_number'];
                $label=trim((string)($row['label']??''));
                $h.='<a class="manual-page-pick" href="/admin/manual-clipping/'.View::e($id).'">'
                  .'<span class="manual-pick-icon">'.View::icon('pages').'</span>'
                  .'<strong>Page '.$number.'</strong>'
                  .($label!==''?'<small>'.View::e($label).'</small>':'')
                  .'</a>';
            }
            if(!$pages)$h.='<p class="muted">This edition has no ready pages.</p>';
            if((int)$chosen['ready_pages']>count($pages))$h.='<p class="muted">Showing the first 500 ready pages of this edition.</p>';
            $h.='</div>';
        }else{
            $h.='<div class="manual-empty" id="manual-chosen-heading">Select a different date or publication to find available editions.</div>';
        }
        $h.='</section></div>';
        if($pageNo>1||!empty($filters['has_next'])){
            $h.='<nav class="manual-pagination" aria-label="Edition collection pagination">';
            if($pageNo>1){
                $prev=$queryPrefix.http_build_query($params+['p'=>$pageNo-1],'','&',PHP_QUERY_RFC3986);
                $h.='<a class="btn ghost" href="'.View::e($prev).'">← Newer editions</a>';
            }
            $h.='<span>Collection page '.$pageNo.'</span>';
            if(!empty($filters['has_next'])){
                $next=$queryPrefix.http_build_query($params+['p'=>$pageNo+1],'','&',PHP_QUERY_RFC3986);
                $h.='<a class="btn ghost" href="'.View::e($next).'">Older editions →</a>';
            }
            $h.='</nav>';
        }
        return $h.'</section></section>';
    }

    public static function detail(array $page,string $guid,array $clips,string $saved='',?string $error=null): string
    {
        $ready=!empty($page['preview_path'])&&!empty($page['highres_path']);
        $h='<section class="manual-clipping-page admin-section" data-manual-clipping>'
          .'<div class="manual-titlebar"><div><a href="/admin/manual-clipping?edition='.View::e(Uuid::fromBinary($page['edition_guid'])).'">← Edition collection</a>'
          .'<span class="eyebrow">MANUAL EDITORIAL CLIPPING</span>'
          .'<h1>Page '.(int)$page['page_number'].'</h1><p>'
          .View::e($page['publication_name']).' · '.View::e($page['edition_date'])
          .' · '.View::e((string)($page['edition_title']??'')).'</p></div>'
          .'<span class="manual-private-label">'.View::icon('user').' Private editorial clip</span></div>';
        if($error!==null)$h.='<div class="alert warning" role="alert">'.View::e($error).'</div>';
        if($saved!=='')$h.='<div class="alert success" role="status">Clipping saved privately. '
          .'<a href="'.View::e($saved).'">Open saved clipping →</a></div>';
        if(!$ready)$h.='<div class="alert warning">Both preview and high-resolution media are required. Check Processing Center.</div>';
        $h.='<div class="manual-editor-grid"><section class="panel manual-canvas-panel">'
          .'<div class="section-head"><h2>Mark the clipping area</h2></div>'
          .'<p class="muted">Draw a rectangle over the article. You can fine-tune its coordinates.</p>';
        if($ready){
            $h.='<div class="manual-image-stage" data-manual-stage>'
              .'<img data-manual-image draggable="false" alt="Newspaper page '.(int)$page['page_number']
              .'" src="/admin/manual-clipping/'.View::e($guid).'/preview">'
              .'<div class="manual-selection" data-manual-selection hidden>Selected clipping</div></div>';
        }else $h.='<div class="manual-empty">Page preview unavailable.</div>';
        $h.='<p class="manual-help">Each saved clip uses the original high-resolution page. '
          .'You may create multiple private clips from the same page.</p></section>'
          .'<aside class="manual-right-column"><section class="panel manual-form-panel">'
          .'<h2>Clipping details</h2><form method="post" action="/admin/manual-clipping/'.View::e($guid).'" data-manual-form>'
          .'<input type="hidden" name="_csrf" value="'.View::e(Http::csrf()).'">'
          .'<label>Title<input name="title" maxlength="180" placeholder="Headline or description"></label>'
          .'<div class="manual-coords">';
        foreach(['x'=>'Left','y'=>'Top','w'=>'Width','h'=>'Height'] as $key=>$name){
            $h.='<label>'.View::e($name).' (0–1)<input type="number" name="'.$key
              .'" data-manual-coord="'.$key.'" min="0" max="1" step="0.000001"'
              .' required placeholder="0.000000"></label>';
        }
        $h.='</div><p class="manual-validation" data-manual-feedback aria-live="polite">'
          .'Draw a clipping rectangle to begin.</p>'
          .'<canvas data-manual-crop-preview hidden aria-label="Selected clipping preview"></canvas>'
          .'<div class="manual-form-actions"><button class="btn ghost" type="button" data-manual-reset>Clear</button>'
          .'<button class="btn primary" type="submit" data-manual-submit disabled>Save private clip</button></div>'
          .'</form></section><section class="panel"><h2>Your saved clips</h2><div class="manual-saved-list">';
        foreach($clips as $clip){
            $h.='<a href="/c/'.View::e($clip['clip_slug']).'"><span><strong>'
             .View::e($clip['title']?:'News clipping').'</strong><small>'
             .View::e($clip['created_at']).'</small></span>→</a>';
        }
        if(!$clips)$h.='<p class="muted">No manual clips saved by you for this page.</p>';
        return $h.'</div></section></aside></div></section>';
    }
}
