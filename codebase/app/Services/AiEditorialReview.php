<?php
declare(strict_types=1);
namespace EpaperSaas\Services;

use EpaperSaas\Core\{Auth,Http,RateLimiter,RequestContext,Store,Uuid,View};

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
        if(in_array($path,['/admin/article-review','/admin/ai-review'],true)&&$method==='GET')self::index();
        if(preg_match('#^/admin/(?:article-review|ai-review)/([0-9a-f-]{36})/(preview|thumb)$#i',$path,$m)&&$method==='GET')self::preview($m[1],$m[2]==='thumb');
        if(preg_match('#^/admin/(?:article-review|ai-review)/([0-9a-f-]{36})$#i',$path,$m)){
            if($method==='GET')self::detail($m[1]);
            if($method==='POST')self::mutate($m[1]);
        }
        http_response_code(404);exit('Not found');
    }

    private static function row(string $pageGuid,bool $lock=false): ?array
    {
        if(!Uuid::isValid($pageGuid))return null;
        return Store::one('SELECT c.*,p.width,p.height,p.page_number,p.section_guid,e.edition_date,e.publication_guid,e.city_guid,e.language_guid,pub.name publication_name FROM ep_ai_page_candidates c JOIN ep_pages p ON p.tenant_guid=c.tenant_guid AND p.page_guid=c.page_guid AND p.edition_guid=c.edition_guid JOIN ep_editions e ON e.tenant_guid=c.tenant_guid AND e.edition_guid=c.edition_guid JOIN ep_publications pub ON pub.tenant_guid=e.tenant_guid AND pub.publication_guid=e.publication_guid WHERE c.tenant_guid=? AND c.page_guid=?'.($lock?' FOR UPDATE':''),[
            Uuid::toBinary(RequestContext::tenantGuid()),Uuid::toBinary($pageGuid)
        ]);
    }

    private static function index(): never
    {
        $tenant=TenantData::tenantBin();
        $status=strtoupper(trim((string)($_GET['status']??'PENDING')));
        if(!in_array($status,['ALL','PENDING','REVIEWED','REJECTED'],true))$status='PENDING';
        $edition=trim((string)($_GET['edition']??''));
        if($edition!==''&&!Uuid::isValid($edition))$edition='';
        $page=max(1,min(10000,(int)($_GET['page']??1)));$perPage=24;
        $where='c.tenant_guid=?';$params=[$tenant];
        if($edition!==''){$where.=' AND c.edition_guid=?';$params[]=Uuid::toBinary($edition);}
        $counts=['PENDING'=>0,'REVIEWED'=>0,'REJECTED'=>0];
        foreach(Store::all('SELECT c.status,COUNT(*) total FROM ep_ai_page_candidates c WHERE '.$where.' GROUP BY c.status',$params) as $r){
            $s=(string)$r['status'];if(isset($counts[$s]))$counts[$s]=(int)$r['total'];
        }
        $total=$status==='ALL'?array_sum($counts):($counts[$status]??0);
        $pages=max(1,(int)ceil($total/$perPage));$page=min($page,$pages);
        $query=$where.($status==='ALL'?'':' AND c.status=?');
        $searchParams=$params;if($status!=='ALL')$searchParams[]=$status;
        $rows=Store::all('SELECT c.page_guid,c.edition_guid,c.status,c.version,p.page_number,e.edition_date,pub.name publication_name FROM ep_ai_page_candidates c JOIN ep_pages p ON p.tenant_guid=c.tenant_guid AND p.page_guid=c.page_guid AND p.edition_guid=c.edition_guid JOIN ep_editions e ON e.tenant_guid=c.tenant_guid AND e.edition_guid=c.edition_guid JOIN ep_publications pub ON pub.tenant_guid=e.tenant_guid AND pub.publication_guid=e.publication_guid WHERE '.$query.' ORDER BY e.edition_date DESC,c.edition_guid DESC,p.page_number ASC LIMIT '.(int)$perPage.' OFFSET '.(int)(($page-1)*$perPage),$searchParams);
        $editions=Store::all('SELECT e.edition_guid,e.edition_date,pub.name publication_name FROM ep_editions e JOIN ep_publications pub ON pub.tenant_guid=e.tenant_guid AND pub.publication_guid=e.publication_guid WHERE e.tenant_guid=? AND EXISTS (SELECT 1 FROM ep_ai_page_candidates c WHERE c.tenant_guid=e.tenant_guid AND c.edition_guid=e.edition_guid) ORDER BY e.edition_date DESC LIMIT 150',[$tenant]);
        $url=static function(array $more=[])use($status,$edition):string{
            $params=array_merge(['status'=>$status,'edition'=>$edition],$more);
            return '/admin/article-review?'.http_build_query(array_filter($params,static fn($v)=>$v!==null&&$v!==''),'','&',PHP_QUERY_RFC3986);
        };
        $html=<<<'HTML'
<style>
.article-review{max-width:1480px;margin:0 auto;color:var(--console-text,#1b2a40);min-width:0}
.article-review h1{font-size:clamp(28px,3vw,38px);letter-spacing:-.035em;margin:6px 0 10px}
.article-review-head{display:flex;align-items:end;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:22px}
.article-review-head p{max-width:720px;line-height:1.5}
.article-review-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:18px}
.article-review-stat{display:flex;flex-direction:column;gap:5px;padding:18px;background:var(--console-surface,#fff);border:1px solid var(--console-border,#dce5ee);border-radius:15px;color:inherit!important;text-decoration:none!important}
.article-review-stat strong{font-size:28px;line-height:1.1}.article-review-stat span{color:var(--console-muted,#607087);font-size:12px}
.article-review-stat.active{outline:2px solid var(--console-accent,#16857e);outline-offset:-2px}
.article-review-controls{display:flex;gap:12px;flex-wrap:wrap;align-items:end;border:1px solid var(--console-border,#dce5ee);background:var(--console-surface,#fff);border-radius:14px;padding:15px}
.article-review-controls label{font-size:12px;font-weight:700;min-width:180px;flex:1}
.article-review-controls select{display:block;margin-top:6px;width:100%;height:42px}
.article-review-edition{margin:20px 0}.article-review-edition h2{font-size:17px;margin:0 0 12px}
.article-review-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:13px}
.article-review-card{display:flex;align-items:center;gap:13px;border:1px solid var(--console-border,#dce5ee);background:var(--console-surface,#fff);padding:13px;border-radius:14px;text-decoration:none!important;color:inherit!important}
.article-review-card:is(:hover,:focus-visible){border-color:var(--console-accent,#11887e);box-shadow:0 7px 25px #17273b18}
.article-review-card img{width:65px;height:86px;object-fit:cover;object-position:top;border-radius:5px;background:var(--console-surface-soft,#edf3f9);border:1px solid var(--console-border,#dce5ee);flex-shrink:0}
.article-review-card span{display:grid;gap:6px}.article-review-card strong{font-size:15px}
.article-review-card small{color:var(--console-muted,#607087);font-size:12px}
.article-review-state{font-size:10px;font-weight:800;letter-spacing:.07em;color:var(--console-accent,#16857e)}
.article-review-state.pending{color:#a36b20}.article-review-state.rejected{color:#bf5050}
.article-review-pages{display:flex;align-items:center;justify-content:center;gap:15px;margin-top:28px}
@media(max-width:680px){.article-review-stats{gap:6px}.article-review-stat{padding:12px}.article-review-stat strong{font-size:22px}.article-review-controls label{min-width:100%}}
</style>
<section class="article-review"><div class="article-review-head"><div><span class="eyebrow">EDITORIAL WORKSPACE</span><h1>Article Review</h1>
<p class="muted">Review OCR article regions by newspaper and edition. All corrections remain private; nothing here publishes articles or clippings.</p></div>
<a class="btn ghost" href="/admin/articles">Open Articles →</a></div>
HTML;
        $html.='<div class="article-review-stats">';
        foreach(['PENDING'=>'Pending review','REVIEWED'=>'Reviewed','REJECTED'=>'Rejected'] as $key=>$label){
            $html.='<a class="article-review-stat'.($status===$key?' active':'').'" href="'.View::e($url(['status'=>$key])).'"><strong>'.(int)$counts[$key].'</strong><span>'.View::e($label).'</span></a>';
        }
        $html.='</div><form class="article-review-controls" method="get" action="/admin/article-review"><label>Review status<select name="status">';
        foreach(['PENDING'=>'Pending','ALL'=>'All statuses','REVIEWED'=>'Reviewed','REJECTED'=>'Rejected'] as $key=>$label)$html.='<option value="'.$key.'"'.($status===$key?' selected':'').'>'.$label.'</option>';
        $html.='</select></label><label>Edition<select name="edition"><option value="">All editions</option>';
        foreach($editions as $entry){
            $id=Uuid::fromBinary($entry['edition_guid']);
            $title=(string)$entry['publication_name'].' · '.date('d M Y',strtotime((string)$entry['edition_date']));
            $html.='<option value="'.View::e($id).'"'.($edition===$id?' selected':'').'>'.View::e($title).'</option>';
        }
        $html.='</select></label><button type="submit" class="btn primary">Apply filters</button><a class="btn ghost" href="/admin/article-review">Reset</a></form>';
        $html.='<p class="muted" style="margin-top:18px">'.(int)$total.' matching pages · Page '.$page.' of '.$pages.'</p>';
        $last=null;
        foreach($rows as $entry){
            $edGuid=Uuid::fromBinary($entry['edition_guid']);$guid=Uuid::fromBinary($entry['page_guid']);
            if($last!==$edGuid){
                if($last!==null)$html.='</div></section>';
                $last=$edGuid;
                $html.='<section class="article-review-edition"><h2>'.View::e((string)$entry['publication_name']).' · '.View::e(date('d M Y',strtotime((string)$entry['edition_date']))).'</h2><div class="article-review-cards">';
            }
            $st=strtoupper((string)$entry['status']);
            $html.='<a class="article-review-card" href="/admin/article-review/'.View::e($guid).'"><img loading="lazy" decoding="async" src="/admin/article-review/'.View::e($guid).'/thumb" alt="Page '.(int)$entry['page_number'].' thumbnail"><span><strong>Page '.(int)$entry['page_number'].'</strong><b class="article-review-state '.View::e(strtolower($st)).'">'.View::e($st).'</b><small>Revision '.(int)$entry['version'].' · Open review →</small></span></a>';
        }
        if($last!==null)$html.='</div></section>';
        if(!$rows)$html.='<section class="panel"><h2>No matching review pages</h2><p class="muted">Try another edition/status. AI candidates appear only when OCR and optional AI processing have produced them.</p></section>';
        if($pages>1){
            $html.='<nav class="article-review-pages" aria-label="Review pagination">';
            if($page>1)$html.='<a class="btn ghost" href="'.View::e($url(['page'=>$page-1])).'">← Previous</a>';
            $html.='<span>Page '.$page.' of '.$pages.'</span>';
            if($page<$pages)$html.='<a class="btn ghost" href="'.View::e($url(['page'=>$page+1])).'">Next →</a>';
            $html.='</nav>';
        }
        $html.='</section>';
        View::layout('Article Review',$html,['brand'=>(string)TenantData::brand()['site_name'],'admin'=>true]);
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
        $html.='<section class="admin-section"><p><a href="/admin/article-review">← Article Review queue</a></p><span class="eyebrow">PRIVATE EDITORIAL REVIEW</span><h1>'.View::e((string)$row['publication_name']).' · Page '.(int)$row['page_number'].'</h1><p class="muted">'.View::e(date('d M Y',strtotime((string)$row['edition_date']))).'</p><p class="muted">Edit classification, OCR text, or pixel boundary. The blue/orange boxes show machine suggestions; none are verified articles.</p><p>Status: <strong>'.View::e((string)$row['status']).'</strong> · revision '.$version.' · '.count($doc['regions']).' regions. Coordinates are in the original '.$w.' × '.$h.' pixel grid.</p>';
        $html.='<div class="ai-actions"><form method="post"><input type="hidden" name="_csrf" value="'.$csrf.'"><input type="hidden" name="version" value="'.$version.'"><input type="hidden" name="mode" value="approve"><label><input required type="checkbox" name="confirm" value="yes"> I reviewed the candidate regions</label> <button class="btn primary" type="submit">Approve reviewed regions (no publishing)</button></form><form method="post"><input type="hidden" name="_csrf" value="'.$csrf.'"><input type="hidden" name="version" value="'.$version.'"><input type="hidden" name="mode" value="reject"><button class="btn ghost" type="submit">Reject proposals</button></form></div>';
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
        View::layout('Article Review',$html,['brand'=>(string)TenantData::brand()['site_name'],'admin'=>true]);
    }

    private static function preview(string $pageGuid,bool $thumb=false): never
    {
        $row=self::row($pageGuid);
        if(!$row){http_response_code(404);exit;}
        $tenant=RequestContext::tenantGuid();
        $asset=Store::one('SELECT a.storage_path,a.mime_type,a.byte_size FROM ep_page_assets a WHERE a.tenant_guid=? AND a.page_guid=? AND a.asset_type IN ("THUMB","PREVIEW") '.($thumb?'ORDER BY FIELD(a.asset_type,"THUMB","PREVIEW")':'AND a.asset_type="PREVIEW"').' LIMIT 1',[
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
        if(!RateLimiter::allow('ai-review:'.RequestContext::tenantGuid().':'.$actor,40,60))Http::json(['ok'=>false,'code'=>'REVIEW_RATE_LIMIT'],429);
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
                $encoded=json_encode($edit,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
                if(strlen($encoded)>1024*1024)return 'REVIEW_SIZE_LIMIT';
                $count=Store::exec('UPDATE ep_ai_page_candidates SET editor_json=?,status="PENDING",reviewed_by_user_guid=NULL,reviewed_at=NULL,version=version+1,updated_at=NOW(6) WHERE tenant_guid=? AND page_guid=? AND version=?',[
                    $encoded,
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
        Http::redirect('/admin/article-review/'.rawurlencode($pageGuid).'?saved=1');
    }
}
