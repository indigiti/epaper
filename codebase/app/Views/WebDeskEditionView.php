<?php
declare(strict_types=1);
namespace EpaperSaas\Views;

use EpaperSaas\Core\{Auth,Http,RequestContext,Store,Uuid,View};
use EpaperSaas\Services\TenantData;

/** Safe, dedicated publisher workstation: upload, publish READY, mark page outlines. */
final class WebDeskEditionView
{
    public static function index(): never
    {
        $tb=TenantData::tenantBin();
        $publications=TenantData::toGuidRows(Store::all(
            'SELECT publication_guid,name FROM ep_publications WHERE tenant_guid=? AND status="ACTIVE" ORDER BY name',
            [$tb]),['publication_guid']);
        $editions=TenantData::editionsForAdmin(
            'e.status NOT IN ("ARCHIVED","TRASHED")',[],50,0);
        $e=static fn(mixed $text):string=>View::e((string)$text);
        $csrf='<input type="hidden" name="_csrf" value="'.$e(Http::csrf()).'">';
        $options='<option value="">Choose publication</option>';
        foreach($publications as $pub)
            $options.='<option value="'.$e($pub['publication_guid']).'">'.$e($pub['name']).'</option>';
        $html='<section class="admin-head editions-head"><div><span class="eyebrow">WEBDESK</span>'
            .'<h1>Edition publishing</h1><p>Upload a PDF, follow processing and publish when ready. '
            .'Article outlines are available after pages finish processing.</p></div></section>'
            .'<section class="panel"><h2>Upload new edition</h2>'
            .'<form method="post" action="/admin/editions" enctype="multipart/form-data" class="form-stack">'
            .$csrf.'<label>Publication<select name="publication_guid" required>'.$options.'</select></label>'
            .'<label>Edition date<input type="date" name="edition_date" value="'.date('Y-m-d').'" required></label>'
            .'<label>Edition title (optional)<input name="title" maxlength="190" placeholder="Publication and date"></label>'
            .'<label>Newspaper PDF (up to 300 MB)<input type="file" name="pdf" accept=".pdf,application/pdf" required></label>'
            .'<input type="hidden" name="publish_mode" value="MANUAL">'
            .'<button class="btn primary" type="submit">Upload and process edition</button>'
            .'<p class="muted">Upload creates a draft and queues processing. Publication is manual after status becomes READY.</p>'
            .'</form></section><section class="panel"><h2>Recent editions</h2>'
            .'<div class="table-wrap editions-table-wrap"><table class="editions-table"><thead>'
            .'<tr><th>Edition</th><th>Date</th><th>Status</th><th>Allowed actions</th></tr></thead><tbody>';
        foreach($editions as $raw){
            $row=TenantData::guidRow($raw,['edition_guid']);
            $guid=$e($row['edition_guid']??'');
            $status=strtoupper((string)($row['status']??''));
            $title=$e($row['title']??$row['publication_name']??'Edition');
            $name=$e($row['publication_name']??'');
            $date=$e($row['edition_date']??'');
            $html.='<tr><td><strong>'.$title.'</strong><small style="display:block">'.$name.'</small></td>'
                .'<td>'.$date.'</td><td>'.$e($status).'</td><td>'
                .'<div class="edition-actions"><a class="btn ghost" href="/admin/manual-clipping?edition='
                .$guid.'">Article outlines</a>';
            if($status==='READY')
                $html.='<form method="post" action="/admin/editions/'.$guid.'/publish">'
                    .$csrf.'<button type="submit" class="btn primary">Publish now</button></form>';
            elseif($status==='PROCESSING'||$status==='DRAFT')
                $html.='<span class="muted">Processing pending</span>';
            elseif($status==='NEEDS_ATTENTION')
                $html.='<span class="muted">Ask a publisher manager to review processing</span>';
            $html.='</div></td></tr>';
        }
        if(!$editions)$html.='<tr><td colspan="4">No editions uploaded yet.</td></tr>';
        $html.='</tbody></table></div><p class="muted">Recent 50 active editions. Archive, trash,'
            .' removal, scheduling and administrative recovery are not permitted.</p>'
            .'<a class="btn ghost" href="/admin/manual-clipping">Open article outline editor</a></section>';
        View::layout('WebDesk · Editions',$html,[
            'brand'=>(string)RequestContext::get('tenant_name','ePaper SaaS'),'admin'=>true
        ]);
    }
}
