<?php
declare(strict_types=1);
namespace EpaperSaas\Services;

use EpaperSaas\Core\{Auth,Http,RequestContext,Store,Uuid,View};

/**
 * Feature-flagged publisher editorial review. Controller called only under
 * App::runAdmin (current authenticated session and POST CSRF required).
 */
final class AiEditorialReview
{
    private static function allow(): void
    {
        if(!AiCandidatePipeline::enabled()){http_response_code(404);exit('Not found');}
        $membership=Auth::currentMembership();
        $role=strtoupper((string)($membership['role_key']??''));
        if(!in_array($role,['OWNER','ADMIN','PUBLISHER_ADMIN','EDITOR'],true)){
            http_response_code(403);exit('Editorial review permission required');
        }
    }

    public static function route(string $path,string $method): never
    {
        self::allow();
        Http::privateNoStore();
        if($path==='/admin/ai-review'&&$method==='GET')self::index();
        if(preg_match('#^/admin/ai-review/([0-9a-f-]{36})/preview$#i',$path,$m)&&$method==='GET')self::preview($m[1]);
        if(preg_match('#^/admin/ai-review/([0-9a-f-]{36})$#i',$path,$m)){
            if($method==='GET')self::detail($m[1]);
            if($method==='POST')self::mutate($m[1]);
        }
        http_response_code(404);exit('Not found');
    }

    private static function row(string $pageGuid,bool $lock=false): ?array
    {
        if(!Uuid::isValid($pageGuid))return null;
        return Store::one('SELECT c.*,p.width,p.height,p.page_number FROM ep_ai_page_candidates c JOIN ep_pages p ON p.tenant_guid=c.tenant_guid AND p.page_guid=c.page_guid AND p.edition_guid=c.edition_guid WHERE c.tenant_guid=? AND c.page_guid=?'.($lock?' FOR UPDATE':''),[
            Uuid::toBinary(RequestContext::tenantGuid()),Uuid::toBinary($pageGuid)
        ]);
    }

    private static function index(): never
    {
        $rows=Store::all('SELECT c.page_guid,c.status,c.version,c.updated_at,p.page_number FROM ep_ai_page_candidates c JOIN ep_pages p ON p.tenant_guid=c.tenant_guid AND p.page_guid=c.page_guid AND p.edition_guid=c.edition_guid WHERE c.tenant_guid=? ORDER BY c.updated_at DESC LIMIT 50',[
            Uuid::toBinary(RequestContext::tenantGuid())
        ]);
        $html='<section class="admin-section"><span class="eyebrow">EDITORIAL WORKSPACE</span><h1>AI page review</h1><p class="muted">Private OCR candidates only. Nothing here publishes an article or creates a clip.</p><div class="data-list">';
        foreach($rows as$r){
            $page=Uuid::fromBinary($r['page_guid']);
            $html.='<a class="btn ghost" style="margin:5px" href="/admin/ai-review/'.View::e($page).'">Page '.(int)$r['page_number'].' · '.View::e((string)$r['status']).' · revision '.(int)$r['version'].'</a>';
        }
        if(!$rows)$html.='<p class="muted">No AI candidates yet. Run OCR in isolated staging with the feature flag enabled and the migration applied.</p>';
        $html.='</div></section>';
        View::layout('AI page review',$html,['brand'=>(string)RequestContext::get('tenant_name','ePaper SaaS'),'admin'=>true]);
    }

    private static function candidates(array $row): array
    {
        $json=json_decode((string)$row['candidate_json'],true,64);
        if(!is_array($json)||($json['schema_version']??'')!=='epdi.segmentation.v1'
            ||($json['status']??'')!=='REVIEW_REQUIRED')throw new \RuntimeException('Invalid AI candidate record');
        if(($json['page']['tenant_guid']??null)!==RequestContext::tenantGuid())throw new \RuntimeException('Cross-tenant candidate record');
        return $json;
    }

    private static function editor(array $row): array
    {
        $ed=$row['editor_json']?json_decode((string)$row['editor_json'],true,64):[];
        return is_array($ed)&&is_array($ed['regions']??null)?$ed:['regions'=>[]];
    }

    private static function detail(string $pageGuid): never
    {
        $row=self::row($pageGuid);
        if(!$row){http_response_code(404);exit('Page not found');}
        $doc=self::candidates($row);$ed=self::editor($row);
        $w=(int)$row['width'];$h=(int)$row['height'];
        $csrf=View::e(Http::csrf());$page=View::e($pageGuid);$version=(int)$row['version'];
        $html='<style>.ai-review-grid{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(290px,.85fr);gap:18px;align-items:start}.ai-paper{position:relative;border:1px solid #64748b;border-radius:12px;overflow:hidden;background:#eceff1}.ai-paper img{width:100%;height:auto;display:block}.ai-box{position:absolute;border:2px solid #2563eb;background:rgba(37,99,235,.11);color:#fff;font-size:10px;text-shadow:0 1px 3px #000;overflow:hidden;cursor:pointer}.ai-box.image{border-color:#f97316;background:rgba(249,115,22,.12)}.ai-edit{padding:12px;border:1px solid #cbd5e1;border-radius:12px;margin-bottom:12px}.ai-fields{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:7px}.ai-fields label{font-size:11px}.ai-fields input{width:100%;min-width:0;padding:6px}.ai-edit textarea{width:100%;min-height:55px}.ai-forms{max-height:76vh;overflow:auto}.ai-actions{display:flex;flex-wrap:wrap;gap:10px;margin:12px 0}@media(max-width:850px){.ai-review-grid{grid-template-columns:1fr}.ai-forms{max-height:none}}</style>';
        $html.='<section class="admin-section"><p><a href="/admin/ai-review">← All AI pages</a></p><span class="eyebrow">REVIEW ONLY — PRIVATE</span><h1>Page '.(int)$row['page_number'].' · candidate review</h1><p class="muted">Edit classification, OCR text, or pixel boundary. The blue/orange boxes show machine suggestions; none are verified articles.</p><p>Status: <strong>'.View::e((string)$row['status']).'</strong> · revision '.$version.' · '.count($doc['regions']).' regions. Coordinates are in the original '.$w.' × '.$h.' pixel grid.</p>';
        $html.='<div class="ai-actions"><form method="post"><input type="hidden" name="_csrf" value="'.$csrf.'"><input type="hidden" name="version" value="'.$version.'"><input type="hidden" name="mode" value="approve"><label><input required type="checkbox" name="confirm" value="yes"> I reviewed the candidate regions</label> <button class="btn primary" type="submit">Mark reviewed (no publishing)</button></form><form method="post"><input type="hidden" name="_csrf" value="'.$csrf.'"><input type="hidden" name="version" value="'.$version.'"><input type="hidden" name="mode" value="reject"><button class="btn ghost" type="submit">Reject proposals</button></form></div>';
        $html.='<div class="ai-review-grid"><div><div class="ai-paper" data-page-width="'.$w.'" data-page-height="'.$h.'"><img src="/admin/ai-review/'.$page.'/preview" alt="Private newspaper page preview with candidate region overlay">';
        $count=0;
        foreach($doc['regions'] as$reg){
            if(!is_array($reg)||!isset($reg['id'],$reg['bbox_px']))continue;
            $id=(string)$reg['id'];$saved=$ed['regions'][$id]??[];$b=$saved['bbox_px']??$reg['bbox_px'];
            if(!is_array($b))continue;
            $x=max(0,min(100,(float)($b['x1']??0)*100/max(1,$w)));
            $y=max(0,min(100,(float)($b['y1']??0)*100/max(1,$h)));
            $width=max(0,min(100,(float)(($b['x2']??0)-($b['x1']??0))*100/max(1,$w)));
            $height=max(0,min(100,(float)(($b['y2']??0)-($b['y1']??0))*100/max(1,$h)));
            $class=str_contains((string)($reg['suggested_kind']??''),'IMAGE')?' image':'';
            $html.='<a class="ai-box'.$class.'" href="#ai-region-'.$count.'" aria-label="Edit region '.($count+1).'" style="left:'.$x.'%;top:'.$y.'%;width:'.$width.'%;height:'.$height.'%;">'.($count+1).'</a>';
            $count++;
        }
        $html.='</div><p class="muted">Click any box to edit. Drag to reposition, or Shift-drag to resize; then save the correction. Image detection may find advertisements or artwork.</p></div><div class="ai-forms">';
        $kinds=['HEADLINE_CANDIDATE','BODY_CANDIDATE','CAPTION_CANDIDATE','IMAGE_CANDIDATE','ADVERTISEMENT_CANDIDATE','IGNORE'];
        $count=0;
        foreach($doc['regions'] as$reg){
            if(!is_array($reg)||!isset($reg['id'],$reg['bbox_px']))continue;
            $id=(string)$reg['id'];$saved=$ed['regions'][$id]??[];$b=$saved['bbox_px']??$reg['bbox_px'];
            $kind=(string)($saved['kind']??$reg['suggested_kind']??'BODY_CANDIDATE');
            $text=(string)($saved['text']??$reg['text']??'');
            $html.='<form id="ai-region-'.$count.'" class="ai-edit" method="post"><strong>Region '.($count+1).'</strong> <span class="muted">· '.View::e((string)($reg['role_provenance']??'hocr')).'</span><input type="hidden" name="_csrf" value="'.$csrf.'"><input type="hidden" name="mode" value="correct"><input type="hidden" name="region_id" value="'.View::e($id).'"><input type="hidden" name="version" value="'.$version.'"><label>Classification<select name="kind">';
            foreach($kinds as$option)$html.='<option value="'.$option.'"'.($option===$kind?' selected':'').'>'.str_replace('_',' ',View::e($option)).'</option>';
            $html.='</select></label><label>OCR text<textarea name="text" maxlength="5000">'.View::e($text).'</textarea></label><div class="ai-fields">';
            foreach(['x1','y1','x2','y2'] as$coord)$html.='<label>'.strtoupper($coord).'<input type="number" name="'.$coord.'" min="0" max="'.(str_starts_with($coord,'x')?$w:$h).'" required value="'.(int)($b[$coord]??0).'"></label>';
            $group=(string)($saved['article_group']??'');
            $readingOrder=(int)($saved['reading_order']??($count+1));
            $html.='</div><p><label>Article group (same key links columns)<input name="article_group" maxlength="40" pattern="[A-Za-z0-9_-]*" value="'.View::e($group).'" placeholder="e.g. story-1"></label> <label>Reading position<input type="number" name="reading_order" min="1" max="4096" value="'.$readingOrder.'"></label></p><button class="btn ghost" type="submit">Save correction</button></form>';
            $count++;
        }
        $html.='</div></div></section>';
        $html.=<<<'HTML'
<script>
(function(){
  'use strict';
  const paper=document.querySelector('.ai-paper');
  if(!paper)return;
  const image=paper.querySelector('img');
  const pageW=Number(paper.dataset.pageWidth),pageH=Number(paper.dataset.pageHeight);
  paper.querySelectorAll('.ai-box').forEach(box=>{
    const index=Number((box.getAttribute('href')||'').replace('#ai-region-',''));
    const form=document.getElementById('ai-region-'+index);
    if(!form)return;
    const input=k=>form.querySelector('[name="'+k+'"]');
    let drag=null;
    function repaint(){
      const x1=Number(input('x1').value),y1=Number(input('y1').value);
      const x2=Number(input('x2').value),y2=Number(input('y2').value);
      box.style.left=(x1/pageW*100)+'%';
      box.style.top=(y1/pageH*100)+'%';
      box.style.width=((x2-x1)/pageW*100)+'%';
      box.style.height=((y2-y1)/pageH*100)+'%';
    }
    ['x1','y1','x2','y2'].forEach(k=>input(k).addEventListener('input',repaint));
    box.addEventListener('pointerdown',event=>{
      if(event.button!==0)return;
      drag={x:event.clientX,y:event.clientY,resize:event.shiftKey,moved:false,
            values:['x1','y1','x2','y2'].map(k=>Number(input(k).value))};
      box.setPointerCapture(event.pointerId);
    });
    box.addEventListener('pointermove',event=>{
      if(!drag||!image.clientWidth||!image.clientHeight)return;
      const dx=Math.round((event.clientX-drag.x)/image.clientWidth*pageW);
      const dy=Math.round((event.clientY-drag.y)/image.clientHeight*pageH);
      if(Math.abs(dx)+Math.abs(dy)<2&&!drag.moved)return;
      drag.moved=true;
      const [a,b,c,d]=drag.values;
      let x1,y1,x2,y2;
      if(drag.resize){
        x1=a;y1=b;x2=Math.min(pageW,Math.max(a+1,c+dx));y2=Math.min(pageH,Math.max(b+1,d+dy));
      }else{
        const offX=Math.max(-a,Math.min(pageW-c,dx));
        const offY=Math.max(-b,Math.min(pageH-d,dy));
        x1=a+offX;x2=c+offX;y1=b+offY;y2=d+offY;
      }
      for(const [key,value] of Object.entries({x1,y1,x2,y2}))input(key).value=String(value);
      repaint();
    });
    box.addEventListener('pointerup',event=>{
      if(!drag)return;
      if(drag.moved)box.dataset.dragged='1';
      drag=null;
      if(box.hasPointerCapture(event.pointerId))box.releasePointerCapture(event.pointerId);
    });
    box.addEventListener('click',event=>{
      if(box.dataset.dragged==='1'){event.preventDefault();delete box.dataset.dragged;}
    });
  });
})();
</script>
HTML;
        View::layout('AI editorial review',$html,['brand'=>(string)RequestContext::get('tenant_name','ePaper SaaS'),'admin'=>true]);
    }

    private static function preview(string $pageGuid): never
    {
        $row=self::row($pageGuid);
        if(!$row){http_response_code(404);exit;}
        $tenant=RequestContext::tenantGuid();
        $asset=Store::one('SELECT a.storage_path,a.mime_type,a.byte_size FROM ep_page_assets a WHERE a.tenant_guid=? AND a.page_guid=? AND a.asset_type="PREVIEW"',[
            Uuid::toBinary($tenant),Uuid::toBinary($pageGuid)
        ]);
        if(!$asset||$asset['mime_type']!=='image/webp'){http_response_code(404);exit;}
        $root=dirname(__DIR__,2);
        $edition=Uuid::fromBinary($row['edition_guid']);
        $safe=realpath($root.'/storage/media/'.$tenant.'/'.$edition);
        $file=realpath($root.'/storage/'.ltrim((string)$asset['storage_path'],'/'));
        if(!$safe||!$file||!str_starts_with($file,$safe.DIRECTORY_SEPARATOR)||!is_file($file)||filesize($file)>25*1024*1024){
            http_response_code(404);exit;
        }
        header('Content-Type: image/webp');header('Content-Length: '.filesize($file));readfile($file);exit;
    }

    private static function mutate(string $pageGuid): never
    {
        $mode=(string)($_POST['mode']??'');
        $version=filter_var($_POST['version']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if($version===false)Http::json(['ok'=>false,'code'=>'VERSION_INVALID'],422);
        if(!in_array($mode,['correct','approve','reject'],true))Http::json(['ok'=>false,'code'=>'MODE_INVALID'],422);
        $actor=(string)(Auth::user()['user_guid']??'');
        if(!Uuid::isValid($actor))Http::json(['ok'=>false,'code'=>'EDITOR_IDENTITY_INVALID'],403);
        $result=Store::tx(function()use($pageGuid,$version,$mode,$actor){
            $row=self::row($pageGuid,true);
            if(!$row)return 'NOT_FOUND';
            if((int)$row['version']!==$version)return 'STALE_VERSION';
            if($mode==='correct'){
                $doc=self::candidates($row);$edit=self::editor($row);
                $id=(string)($_POST['region_id']??'');
                $matching=null;
                foreach($doc['regions'] as$r){if(is_array($r)&&($r['id']??null)===$id){$matching=$r;break;}}
                if(!$matching)return 'REGION_NOT_FOUND';
                $kind=(string)($_POST['kind']??'');
                if(!in_array($kind,['HEADLINE_CANDIDATE','BODY_CANDIDATE','CAPTION_CANDIDATE','IMAGE_CANDIDATE','ADVERTISEMENT_CANDIDATE','IGNORE'],true))return 'INVALID_CLASSIFICATION';
                $content=trim((string)($_POST['text']??''));
                if(strlen($content)>5000)return 'TEXT_TOO_LONG';
                $bbox=[];
                foreach(['x1','y1','x2','y2'] as$key){
                    $number=filter_var($_POST[$key]??null,FILTER_VALIDATE_INT);
                    if($number===false)return 'INVALID_BOUNDARY';
                    $bbox[$key]=$number;
                }
                if(!($bbox['x1']>=0&&$bbox['y1']>=0&&$bbox['x2']<=(int)$row['width']&&$bbox['y2']<=(int)$row['height']
                    &&$bbox['x1']<$bbox['x2']&&$bbox['y1']<$bbox['y2']))return 'INVALID_BOUNDARY';
                $group=trim((string)($_POST['article_group']??''));
                if(strlen($group)>40||($group!==''&&!preg_match('/^[A-Za-z0-9_-]+$/',$group)))return 'INVALID_STORY_GROUP';
                $order=filter_var($_POST['reading_order']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>4096]]);
                if($order===false)return 'INVALID_READING_ORDER';
                $edit['regions'][$id]=['kind'=>$kind,'text'=>$content,'bbox_px'=>$bbox,'article_group'=>$group,'reading_order'=>$order];
                $count=Store::exec('UPDATE ep_ai_page_candidates SET editor_json=?,status="PENDING",reviewed_by_user_guid=NULL,reviewed_at=NULL,version=version+1,updated_at=NOW(6) WHERE tenant_guid=? AND page_guid=? AND version=?',[
                    json_encode($edit,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
                    Uuid::toBinary(RequestContext::tenantGuid()),Uuid::toBinary($pageGuid),$version
                ]);
                return $count===1?'corrected':'STALE_VERSION';
            }
            if($mode==='approve'&&($_POST['confirm']??'')!=='yes')return 'CONFIRMATION_REQUIRED';
            $status=$mode==='approve'?'REVIEWED':'REJECTED';
            $count=Store::exec('UPDATE ep_ai_page_candidates SET status=?,reviewed_by_user_guid=?,reviewed_at=NOW(6),version=version+1,updated_at=NOW(6) WHERE tenant_guid=? AND page_guid=? AND version=?',[
                $status,Uuid::toBinary($actor),Uuid::toBinary(RequestContext::tenantGuid()),Uuid::toBinary($pageGuid),$version
            ]);
            return $count===1?$mode:'STALE_VERSION';
        });
        if(!in_array($result,['approve','reject','corrected'],true)){
            Http::json(['ok'=>false,'code'=>$result,'message'=>'Refresh this page to continue editing.'],in_array($result,['NOT_FOUND'],true)?404:($result==='STALE_VERSION'?409:422));
        }
        Audit::log('ai.review.'.$result,'page',$pageGuid,'SUCCESS',['action'=>$result,'no_publish'=>true]);
        Http::redirect('/admin/ai-review/'.rawurlencode($pageGuid).'?saved=1');
    }
}
