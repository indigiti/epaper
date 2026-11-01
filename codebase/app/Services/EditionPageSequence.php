<?php
declare(strict_types=1);
namespace EpaperSaas\Services;

use EpaperSaas\Core\{Auth,Http,RequestContext,Store,Uuid,View};

/**
 * Tenant-scoped, version-checked Reader page order editor.
 *
 * Page GUIDs and page assets remain immutable. Reordering only changes the
 * visible edition page sequence; the uploaded source PDF is not modified.
 */
final class EditionPageSequence
{
    private static function deny(string $message,int $code=422): never
    {
        http_response_code($code);
        View::layout('Page sequence','<section class="panel"><h1>Page sequence not updated</h1><p>'.View::e($message).'</p><a class="btn ghost" href="/admin/editions">Return to Editions</a></section>',['admin'=>true]);
    }

    public static function route(string $guid,string $method): never
    {
        $role=strtoupper((string)(Auth::currentMembership()['role_key']??''));
        if(!in_array($role,['OWNER','ADMIN','PUBLISHER_ADMIN','EDITOR'],true))self::deny('Editorial permissions are required.',403);
        if(!Uuid::isValid($guid))self::deny('Edition not found.',404);
        Http::privateNoStore();
        if($method==='POST')self::save($guid);
        if($method!=='GET')self::deny('Unsupported action.',405);

        $tenant=TenantData::tenantBin();$bin=Uuid::toBinary($guid);
        $edition=Store::one('SELECT title,slug,status,version,page_count FROM ep_editions WHERE tenant_guid=? AND edition_guid=?',[$tenant,$bin]);
        if(!$edition)self::deny('Edition not found.',404);
        $pages=Store::all('SELECT page_guid,page_number,label FROM ep_pages WHERE tenant_guid=? AND edition_guid=? ORDER BY page_number,page_guid',[$tenant,$bin]);
        $title=(string)($edition['title']?:$edition['slug']);
        $status=(string)$edition['status'];
        $html='<section class="admin-head"><div><span class="eyebrow">PUBLISHING / PAGE EDITOR</span><h1>Page sequence</h1><p class="muted">'.View::e($title).' · '.View::e($status).' · '.count($pages).' pages</p></div><a class="btn ghost" href="/admin/editions">Back to editions</a></section>';
        if(!$pages){
            $html.='<section class="panel"><h2>No processed pages yet</h2><p>Process the PDF before arranging the Reader pages.</p></section>';
        }else{
            $html.='<section class="panel"><h2>Arrange Reader pages</h2><p class="muted">Use the arrows or position fields to set the desired reading order. Media, OCR, clippings and original PDF stay attached to their page IDs. Published changes immediately change numbered Reader links.</p>';
            if($status==='PUBLISHED')$html.='<p class="muted">For safety, reordering is blocked while active offline licenses exist; readers must download a new offline edition after changes.</p>';
            $html.='<form method="post" action="/admin/editions/'.View::e($guid).'/pages" id="page-sequence-form"><input type="hidden" name="_csrf" value="'.View::e(Http::csrf()).'"><input type="hidden" name="edition_version" value="'.(int)$edition['version'].'"><div class="table-wrap"><table class="editions-table"><thead><tr><th>Current page</th><th>Page / section</th><th>New position</th><th>Move</th></tr></thead><tbody id="edition-sequence-rows">';
            foreach($pages as$index=>$page){
                $pageGuid=Uuid::fromBinary($page['page_guid']);
                $number=(int)$page['page_number'];
                $label=trim((string)($page['label']??''))?:'Newspaper page '.$number;
                $html.='<tr><td data-label="Current">Page '.$number.'</td><td data-label="Page">'.View::e($label).'<small class="muted"> · '.View::e(substr($pageGuid,0,8)).'</small></td><td data-label="Position"><label><span class="sr-only">Position for '.View::e($label).'</span><input aria-label="Position for '.View::e($label).'" type="number" name="positions['.View::e($pageGuid).']" min="1" max="'.count($pages).'" value="'.($index+1).'" required style="max-width:90px"></label></td><td data-label="Move"><button type="button" class="btn ghost small" data-page-move="up" title="Move earlier" aria-label="Move '.View::e($label).' earlier">'.View::icon('arrow-up').'</button> <button type="button" class="btn ghost small" data-page-move="down" title="Move later" aria-label="Move '.View::e($label).' later">'.View::icon('arrow-down').'</button></td></tr>';
            }
            $html.='</tbody></table></div><div class="drawer-actions"><a class="btn ghost" href="/admin/editions">Cancel</a><button class="btn primary" type="submit">Save page sequence</button></div></form></section>';
            $html.=<<<'HTML'
<script>
(()=>{const rows=document.getElementById('edition-sequence-rows');if(!rows)return;
rows.addEventListener('click',e=>{const button=e.target.closest('button[data-page-move]');if(!button)return;
const row=button.closest('tr');const other=button.dataset.pageMove==='up'?row.previousElementSibling:row.nextElementSibling;
if(!other)return;
if(button.dataset.pageMove==='up')rows.insertBefore(row,other);
else rows.insertBefore(other,row);
[...rows.querySelectorAll('tr')].forEach((tr,i)=>tr.querySelector('input[type=number]').value=String(i+1));
});})();
</script>
HTML;
        }
        View::layout('Page sequence',$html,['admin'=>true]);
    }

    private static function save(string $guid): never
    {
        $input=$_POST['positions']??null;
        $expected=filter_var($_POST['edition_version']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if(!is_array($input)||$expected===false||count($input)>1000)self::deny('Invalid page sequence submission.');
        try{
            $result=EditionOperations::locked($guid,static function()use($guid,$input,$expected): array{
                return Store::tx(static function()use($guid,$input,$expected): array{
                    $tenant=TenantData::tenantBin();$bin=Uuid::toBinary($guid);
                    $edition=Store::one('SELECT status,slug,version FROM ep_editions WHERE tenant_guid=? AND edition_guid=? FOR UPDATE',[$tenant,$bin]);
                    if(!$edition)throw new \DomainException('Edition not found.');
                    if((int)$edition['version']!==$expected)throw new \DomainException('Edition changed in another session. Reload the editor before saving.');
                    if(in_array((string)$edition['status'],['PROCESSING','TRASHED'],true)||EditionOperations::active($guid))throw new \DomainException('Wait until processing completes, or restore this edition, before editing pages.');
                    $pages=Store::all('SELECT page_guid,page_number FROM ep_pages WHERE tenant_guid=? AND edition_guid=? ORDER BY page_number,page_guid FOR UPDATE',[$tenant,$bin]);
                    $count=count($pages);
                    if(!$count||$count>1000||count($input)!==$count)throw new \DomainException('Page count changed. Reload the page editor.');
                    $known=[];$previous=[];
                    foreach($pages as$row){$id=Uuid::fromBinary($row['page_guid']);$known[$id]=true;$previous[$id]=(int)$row['page_number'];}
                    $ordered=[];
                    foreach($input as$id=>$value){
                        if(!is_string($id)||!Uuid::isValid($id)||!isset($known[strtolower($id)]))throw new \DomainException('Page reference is not part of this edition.');
                        $position=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>$count]]);
                        if($position===false||isset($ordered[$position]))throw new \DomainException('Each page needs a unique position from 1 to '.$count.'.');
                        $ordered[$position]=strtolower($id);
                    }
                    ksort($ordered,SORT_NUMERIC);
                    if(array_keys($ordered)!==range(1,$count))throw new \DomainException('All page positions must be present.');
                    $changed=false;foreach($ordered as$position=>$id)if($previous[$id]!==$position){$changed=true;break;}
                    if(!$changed)return ['changed'=>false,'status'=>$edition['status']];
                    if($edition['status']==='PUBLISHED'){
                        $licenses=(int)Store::value('SELECT COUNT(*) FROM ep_offline_licenses WHERE tenant_guid=? AND edition_guid=? AND status IN ("ISSUED","ACTIVE")',[$tenant,$bin]);
                        if($licenses>0)throw new \DomainException('Active offline licenses exist. Revoke or expire these licenses before changing a published edition page order.');
                    }
                    // Two phases preserve UNIQUE (tenant_guid, edition_guid, page_number).
                    foreach(array_keys($previous) as$index=>$id){
                        Store::exec('UPDATE ep_pages SET page_number=?,version=version+1,updated_at=NOW(6) WHERE tenant_guid=? AND edition_guid=? AND page_guid=?',[-($index+1),$tenant,$bin,Uuid::toBinary($id)]);
                    }
                    foreach($ordered as$position=>$id){
                        Store::exec('UPDATE ep_pages SET page_number=?,updated_at=NOW(6) WHERE tenant_guid=? AND edition_guid=? AND page_guid=?',[$position,$tenant,$bin,Uuid::toBinary($id)]);
                    }
                    Store::exec('UPDATE ep_history h JOIN ep_pages p ON p.tenant_guid=h.tenant_guid AND p.page_guid=h.page_guid SET h.page_number=p.page_number WHERE h.tenant_guid=? AND h.edition_guid=?',[$tenant,$bin]);
                    Store::exec('UPDATE ep_editions SET version=version+1,updated_at=NOW(6) WHERE tenant_guid=? AND edition_guid=?',[$tenant,$bin]);
                    return ['changed'=>true,'status'=>$edition['status'],'previous'=>$previous,'ordered'=>$ordered];
                });
            });
        }catch(\DomainException $e){self::deny($e->getMessage());}
        catch(\RuntimeException $e){self::deny('Page editing is busy or unavailable. Please reload and retry.');}

        if($result['changed']){
            Audit::log('edition.pages.reordered','edition',$guid,'SUCCESS',['before'=>$result['previous'],'after'=>$result['ordered']]);
            EditionOperations::invalidate($guid);
            if($result['status']==='PUBLISHED')RuntimeCache::queueWarm(RequestContext::tenantGuid(),$guid);
        }
        Http::redirect('/admin/editions/'.$guid.'/pages?updated=1');
    }
}
