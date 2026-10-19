<?php
declare(strict_types=1);
namespace EpaperSaas\Views;

use EpaperSaas\Core\{Http,Uuid,View};

/** Semantic, progressive-enhancement interface for manual editorial clipping. */
final class ManualClippingView
{
    public static function index(array $pages): string
    {
        $h='<section class="manual-clipping-page admin-section"><div class="manual-titlebar"><div>'
          .'<span class="eyebrow">EDITORIAL WORKSPACE</span><h1>Manual clipping</h1>'
          .'<p>Select a processed page to mark an article. Works without AI detection.</p></div>'
          .'<a class="btn ghost" href="/admin/editions">All editions →</a></div>'
          .'<section class="panel"><div class="section-head"><div><h2>Ready pages</h2>'
          .'<p class="muted">Latest 80 pages with preview and high-resolution media.</p></div></div>'
          .'<div class="manual-pages">';
        foreach($pages as $row){
            $guid=Uuid::fromBinary($row['page_guid']);
            $title=trim((string)($row['edition_title']??''))?:$row['publication_name'];
            $h.='<a class="manual-page-card" href="/admin/manual-clipping/'.View::e($guid).'">'
              .'<span class="manual-page-icon">'.View::icon('newspaper').'</span>'
              .'<span class="manual-page-copy"><strong>'.View::e($title).'</strong>'
              .'<small>'.View::e($row['publication_name']).' · '.View::e($row['edition_date'])
              .' · Page '.(int)$row['page_number'].'</small></span>'
              .View::icon('chev-right').'</a>';
        }
        if(!$pages)$h.='<div class="manual-empty">No page is ready. Upload an edition and finish processing first.</div>';
        return $h.'</div></section></section>';
    }

    public static function detail(array $page,string $guid,array $clips,string $saved='',?string $error=null): string
    {
        $ready=!empty($page['preview_path'])&&!empty($page['highres_path']);
        $h='<section class="manual-clipping-page admin-section" data-manual-clipping>'
          .'<div class="manual-titlebar"><div><a href="/admin/manual-clipping">← All pages</a>'
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
