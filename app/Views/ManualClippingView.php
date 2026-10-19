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
          .'<span class="eyebrow">EDITORIAL WORKSPACE</span><h1>Clip Marking</h1>'
          .'<p>Choose an edition, then pre-mark article areas. Readers can generate a clip when they choose an outline.</p></div>'
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
          .'<div class="manual-filter-actions"><button class="btn primary manual-icon-action" type="submit" title="Apply edition filters" aria-label="Apply edition filters">'.View::icon('search').'</button>'
          .'<a class="btn ghost manual-icon-action" href="/admin/manual-clipping" title="Clear edition filters" aria-label="Clear edition filters">'.View::icon('reset').'</a></div></form>'
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

    public static function detail(array $page,string $guid,array $clips,string $saved='',?string $error=null,array $marks=[],bool $schemaReady=true,string $actor='',string $role='EDITOR',array $navigation=[]): string
    {
        $ready=!empty($page['preview_path'])&&!empty($page['highres_path']);
        $h='<section class="manual-clipping-page admin-section" data-manual-clipping>'
          .'<div class="manual-titlebar"><div><a href="/admin/manual-clipping?edition='.View::e(Uuid::fromBinary($page['edition_guid'])).'">← Edition collection</a>'
          .'<span class="eyebrow">EDITORIAL CLIP MARKING</span>'
          .'<h1>Page '.(int)$page['page_number'].'</h1><p>'
          .View::e($page['publication_name']).' · '.View::e($page['edition_date'])
          .' · '.View::e((string)($page['edition_title']??'')).'</p></div>'
          .'<div class="manual-page-navigation" aria-label="Adjacent pages">';
        foreach(['previous'=>'← Previous','next'=>'Next →'] as $dir=>$label){
            $dest=$navigation[$dir]??null;
            $h.=$dest
              ?'<a class="btn ghost" href="/admin/manual-clipping/'.View::e($dest['guid']).'">'
                .View::e($label).' · '.(int)$dest['number'].'</a>'
              :'<span class="manual-nav-disabled" aria-disabled="true">'.View::e($label).'</span>';
        }
        $h.='</div></div>';
        if($error!==null)$h.='<div class="alert warning" role="alert">'.View::e($error).'</div>';
        if($saved!=='')$h.='<div class="alert success" role="status">Clipping saved privately. '
          .'<a href="'.View::e($saved).'">Open saved clipping →</a></div>';
        if(!$ready)$h.='<div class="alert warning">Both preview and high-resolution media are required. Check Processing Center.</div>';
        if(!$schemaReady)$h.='<div class="alert warning" role="alert">Clip Marking storage is not ready. Go to <a href="/admin/system/upgrade">Upgrade Center</a> and use the safe schema repair.</div>';
        $marked=(string)($_GET['mark']??'');
        if(in_array($marked,['saved','hidden','activated','imported','updated','removed'],true))
            $h.='<div class="alert success" role="status">Article marking '.View::e($marked).'.</div>';
        $h.='<div class="manual-editor-grid"><section class="panel manual-canvas-panel">'
          .'<div class="section-head"><h2>Map article outlines</h2></div>'
          .'<div class="manual-map-toolbar"><button type="button" class="btn ghost manual-icon-action" data-manual-start title="Create a new article outline" aria-label="Create a new article outline">'
          .View::icon('plus').'</button>'
          .'<span>Drag to draw; then move the box or resize using its corners. Select a blue outline to edit.</span>'
          .'</div>';
        if($ready){
            $h.='<div class="manual-image-stage" data-manual-stage tabindex="0" role="group" aria-label="Newspaper article region editor. Draw with pointer or use arrow keys to adjust.">'
              .'<img data-manual-image draggable="false" alt="Newspaper page '.(int)$page['page_number']
              .'" src="/admin/manual-clipping/'.View::e($guid).'/preview">'
              .'<div class="manual-selection" data-manual-selection hidden>'
              .'<span class="manual-selection-label" data-manual-selection-label>New outline</span>'
              .'<span class="manual-resize-handle nw" data-manual-handle="nw" aria-hidden="true"></span>'
              .'<span class="manual-resize-handle ne" data-manual-handle="ne" aria-hidden="true"></span>'
              .'<span class="manual-resize-handle sw" data-manual-handle="sw" aria-hidden="true"></span>'
              .'<span class="manual-resize-handle se" data-manual-handle="se" aria-hidden="true"></span>'
              .'<button type="button" data-manual-quick-save class="manual-quick-save" title="Save article outline" aria-label="Save article outline" disabled>'.View::icon('save').'</button>'
              .'<button type="button" data-manual-discard class="manual-selection-dismiss" title="Discard selection (Esc)" aria-label="Discard selection">'.View::icon('close').'</button>'
              .'</div>';
            foreach($marks as $mark){
                if(strtoupper((string)$mark['status'])!=='ACTIVE')continue;
                $id=(string)$mark['mark_guid'];
                $editable=$schemaReady&&
                    (in_array(strtoupper($role),['OWNER','ADMIN','PUBLISHER_ADMIN'],true)||
                     ($actor!==''&&Uuid::isValid($actor)&&
                     hash_equals((string)$mark['platform_user_guid'],Uuid::toBinary($actor))));
                $tag=$editable?'button':'div';
                $h.='<'.$tag.($editable?' type="button" data-manual-open-mark="'.$id.'"':'')
                    .' class="manual-existing-mark'.($editable?' is-editable':'').'"'
                    .' title="'.View::e($mark['title']).'"'
                    .' aria-label="'.View::e(($editable?'Edit outline: ':'Outline: ').$mark['title']).'"'
                    .' data-mark-id="'.View::e($id).'"'
                    .' data-mark-title="'.View::e($mark['title']).'"'
                    .' data-mark-x="'.(float)$mark['x'].'" data-mark-y="'.(float)$mark['y'].'"'
                    .' data-mark-w="'.(float)$mark['width'].'" data-mark-h="'.(float)$mark['height'].'"'
                    .' style="left:'.max(0,min(100,(float)$mark['x']*100)).'%;'
                    .'top:'.max(0,min(100,(float)$mark['y']*100)).'%;'
                    .'width:'.max(0,min(100,(float)$mark['width']*100)).'%;'
                    .'height:'.max(0,min(100,(float)$mark['height']*100)).'%;"></'.$tag.'>';
                if($editable){
                    // Sibling form, not a nested button: keyboard and pointer removal work independently of edit.
                    $closeX=max(0,min(100,((float)$mark['x']+(float)$mark['width'])*100));
                    $closeY=max(0,min(100,(float)$mark['y']*100));
                    $h.='<form class="manual-overlay-remove" data-manual-remove-overlay method="post"'
                       .' action="/admin/manual-clipping/'.View::e($guid).'/marks/'.View::e($id).'/remove"'
                       .' data-confirm="Remove this marked article? The Reader outline will disappear. Existing clips stay saved.">'
                       .'<input type="hidden" name="_csrf" value="'.View::e(Http::csrf()).'">'
                       .'<button type="submit" class="manual-overlay-remove-btn"'
                       .' title="Remove marked article: '.View::e($mark['title']).'"'
                       .' aria-label="Remove marked article: '.View::e($mark['title']).'">'
                       .View::icon('close').'</button></form>';
                    // Position is measured in newspaper-page coordinates; clamp near page edges.
                    $h=str_replace('class="manual-overlay-remove" data-manual-remove-overlay',
                        'class="manual-overlay-remove" data-manual-remove-overlay'
                        .' style="left:'.$closeX.'%;top:'.$closeY.'%;"',$h);
                }
            }
            $h.='</div>';
        }else $h.='<div class="manual-empty">Page preview unavailable.</div>';
        $h.='<p class="manual-help">Blue outlines are pre-marked articles. Save geometry without generating an image. '
          .'Outlines become selectable on the reader only after this edition is published.</p></section>'
          .'<aside class="manual-right-column"><section class="panel manual-form-panel">'
          .'<h2 data-manual-form-heading>New article outline</h2>'
          .'<form method="post" action="/admin/manual-clipping/'.View::e($guid).'"'
          .' data-manual-form data-manual-page-url="/admin/manual-clipping/'.View::e($guid).'"'
          .' data-marking-ready="'.($schemaReady?'1':'0').'">'
          .'<input type="hidden" name="_csrf" value="'.View::e(Http::csrf()).'">'
          .'<label>Headline (optional)<input name="title" maxlength="180" placeholder="Leave blank for Untitled article"></label>'
          .'<div class="manual-coords">';
        foreach(['x'=>'Left','y'=>'Top','w'=>'Width','h'=>'Height'] as $key=>$name){
            $h.='<label>'.View::e($name).' (0–1)<input type="number" name="'.$key
              .'" data-manual-coord="'.$key.'" min="0" max="1" step="0.000001"'
              .' required placeholder="0.000000"></label>';
        }
        $h.='</div><p class="manual-validation" data-manual-feedback aria-live="polite">'
          .'Draw a clipping rectangle to begin.</p>'
          .'<canvas data-manual-crop-preview hidden aria-label="Selected clipping preview"></canvas>'
          .'<div class="manual-form-actions"><button class="btn ghost manual-icon-action" type="button" data-manual-reset title="Clear current selection" aria-label="Clear current selection">'.View::icon('reset').'</button>'
          .'<button class="btn primary manual-icon-action" type="submit" name="action" value="mark" data-manual-submit title="Save article outline (headline optional)" aria-label="Save article outline" disabled>'.View::icon('save').'</button>'
          .'<button class="btn ghost manual-icon-action" type="submit" name="action" value="private" data-manual-save-private title="Create private clipping now" aria-label="Create private clipping now" disabled>'.View::icon('clip').'</button></div>'
          .'<p class="manual-map-shortcut">Keyboard: Ctrl/⌘ + S to save, Escape to clear. Use arrow keys to nudge the selection when the newspaper is focused.</p>'
          .'</form></section><section class="panel"><h2>Marked articles</h2>'
          .'<p class="muted">Only active outlines appear publicly on published editions. Hide to disable temporarily, or Remove to delete from this marking list.</p>'
          .'<div class="manual-mark-list">';
        $full=in_array(strtoupper($role),['OWNER','ADMIN','PUBLISHER_ADMIN'],true);
        foreach($marks as $mark){
            $active=strtoupper((string)$mark['status'])==='ACTIVE';
            $id=(string)$mark['mark_guid'];
            $mine=$actor!==''&&Uuid::isValid($actor)&&
                hash_equals((string)$mark['platform_user_guid'],Uuid::toBinary($actor));
            $h.='<article class="manual-mark-row"><div><strong>'.View::e($mark['title']).'</strong>'
              .'<small>'.($active?'Active outline':'Hidden outline').'</small></div>';
            if($full||$mine){
                $h.='<div class="manual-mark-actions">'
                  .'<button class="btn ghost manual-icon-action" type="button" title="Edit marked article" aria-label="Edit marked article: '.View::e($mark['title']).'" data-manual-open-mark="'.View::e($id).'"'
                  .' data-mark-id="'.View::e($id).'" data-mark-title="'.View::e($mark['title']).'"'
                  .' data-mark-x="'.(float)$mark['x'].'" data-mark-y="'.(float)$mark['y'].'"'
                  .' data-mark-w="'.(float)$mark['width'].'" data-mark-h="'.(float)$mark['height'].'">'
                  .View::icon('edit').'</button>';
                $action=$active?'hide':'activate';
                $h.='<form method="post" action="/admin/manual-clipping/'.View::e($guid).'/marks/'
                 .View::e($id).'/'.$action.'">'
                 .'<input type="hidden" name="_csrf" value="'.View::e(Http::csrf()).'">'
                 .'<button class="btn ghost manual-icon-action" type="submit"'
                 .' title="'.($active?'Hide outline from Reader':'Show outline in Reader').'"'
                 .' aria-label="'.($active?'Hide':'Show').' marked article: '.View::e($mark['title']).'">'
                 .View::icon($active?'power':'eye').'</button></form>';
                $h.='<form method="post" action="/admin/manual-clipping/'.View::e($guid).'/marks/'
                 .View::e($id).'/remove"'
                 .' data-confirm="Remove this marked article? Its outline will disappear from the Reader. Use Hide instead if you might need it again.">'
                 .'<input type="hidden" name="_csrf" value="'.View::e(Http::csrf()).'">'
                 .'<button class="btn ghost manual-icon-action manual-mark-remove" type="submit"'
                 .' title="Remove marked article" aria-label="Remove marked article: '
                 .View::e($mark['title']).'">'.View::icon('trash').'</button></form></div>';
            }
            $h.='</article>';
        }
        if(!$marks)$h.='<p class="muted">No article outlines marked yet.</p>';
        $h.='</div></section><section class="panel"><h2>Previously created private clips</h2>'
          .'<p class="muted">Convert an earlier manual clip to a selectable article outline.</p>'
          .'<div class="manual-saved-list">';
        foreach($clips as $clip){
            $slug=(string)$clip['clip_slug'];
            $h.='<div class="manual-legacy-clip"><a href="/c/'.View::e($slug).'"><span><strong>'
             .View::e($clip['title']?:'News clipping').'</strong><small>'
             .View::e($clip['created_at']).'</small></span>→</a>';
            if($schemaReady){
                $h.='<form method="post" action="/admin/manual-clipping/'.View::e($guid)
                  .'/import/'.View::e($slug).'">'
                  .'<input type="hidden" name="_csrf" value="'.View::e(Http::csrf()).'">'
                  .'<button type="submit" class="btn ghost manual-icon-action" title="Convert private clip to reader outline" aria-label="Convert private clip to reader outline">'.View::icon('scan-text').'</button></form>';
            }
            $h.='</div>';
        }
        if(!$clips)$h.='<p class="muted">No manual clips saved by you for this page.</p>';
        return $h.'</div></section></aside></div></section>';
    }
}
