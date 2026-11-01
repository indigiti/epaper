<?php
declare(strict_types=1);
namespace EpaperSaas\Controllers;
use EpaperSaas\Core\{Auth,Http,RequestContext,View};
use EpaperSaas\Services\{Audit,MultiLayerCache,RuntimeCache,TenantResourceReport};

/** Read-only tenant usage and non-destructive cache controls. */
final class TenantStorageController
{
    private static function e(mixed $v):string{return View::e((string)$v);}
    private static function size(int $bytes):string{
        if($bytes<1024)return $bytes.' B';
        $n=$bytes/1024;
        foreach(['KB','MB','GB','TB','PB'] as $unit){
            if($n<1024)return number_format($n,$n<10?1:0).' '.$unit;
            $n/=1024;
        }
        return number_format($n,1).' PB';
    }
    public static function index():never
    {
        $report=TenantResourceReport::snapshot();
        if(($_GET['view']??'')==='json')
            Http::json(['ok'=>true,'status'=>'TENANT_USAGE','report'=>$report,
                'note'=>'InnoDB physical bytes are shared; local scans can be incomplete.']);
        $counts=(array)($report['counts']??[]);
        $parts=(array)($report['local_disk']??[]);
        $shared=$report['shared_db_physical_bytes']??null;
        $total=(int)($report['local_disk_bytes']??0);
        $complete=!empty($report['local_disk_complete']);
        $free=@disk_free_space(dirname(__DIR__,2));
        $capacity=@disk_total_space(dirname(__DIR__,2));
        $h='<section class="admin-head"><div><span class="eyebrow">PUBLISHER OPERATIONS</span>'
          .'<h1>Tenant usage &amp; storage</h1>'
          .'<p class="muted">This publisher’s files and database records. Measurements are cached five minutes to avoid repeated disk scans.</p></div>'
          .'<a class="btn ghost" href="/admin/storage?view=json">Detailed JSON</a></section>';
        if(($_GET['cache']??'')==='refreshed')
            $h.='<div class="alert success">Publisher cache refreshed. No media or records were removed.</div>';
        $items=[
            ['Tenant local media',self::size($total),$complete?'Measured tenant folders':'Partial scan · minimum observed'],
            ['Page asset metadata',self::size((int)($report['referenced_asset_bytes']??0)),'Recorded file sizes; overlaps with local media'],
            ['Database records',number_format(array_sum(array_map('intval',$counts))),'Tenant-scoped row counts'],
            ['Shared MariaDB physical space',$shared!==null?self::size((int)$shared):'Unavailable','ALL publishers; NOT this tenant’s size']
        ];
        $h.='<div class="health-grid">';
        foreach($items as [$label,$value,$note])
            $h.='<div class="health-card"><span>'.self::e($label).'</span><strong>'.self::e($value)
                .'</strong><small>'.self::e($note).'</small></div>';
        $h.='</div><section class="panel"><h2>Database usage by tenant</h2>'
          .'<p class="muted">InnoDB tablespaces and indexes are shared. Exact physical bytes per tenant cannot be calculated without separate physical accounting. Counts below are tenant-filtered.</p>'
          .'<div class="health-grid">';
        foreach(['editions'=>'Editions','pages'=>'Pages','assets'=>'Page assets',
            'ocr_pages'=>'OCR pages','clips'=>'Reader clips','jobs'=>'Processing jobs',
            'mobile_readers'=>'Local reader accounts'] as $key=>$label)
            $h.='<div class="health-card"><span>'.self::e($label).'</span><strong>'
                .number_format((int)($counts[$key]??0)).'</strong></div>';
        $h.='</div></section><section class="panel"><h2>Tenant disk allocation</h2>'
          .'<div class="table-wrap"><table><thead><tr><th>Folder</th><th>Observed size</th>'
          .'<th>Files</th><th>Scan coverage</th></tr></thead><tbody>';
        foreach($parts as $label=>$stats)
            $h.='<tr><td>'.self::e($label).'</td><td>'.self::e(self::size((int)($stats['bytes']??0)))
               .'</td><td>'.number_format((int)($stats['files']??0)).'</td><td>'
               .(!empty($stats['complete'])?'Complete':'Partial · lower bound').'</td></tr>';
        $h.='</tbody></table></div><p class="muted">Each tenant folder scan is bounded to 2,500 files or 0.2 seconds. Shared caches, externally stored assets and reader devices are excluded. Values must not be added to asset metadata totals.</p>'
          .'<details><summary>Page asset sizes by type</summary><div class="table-wrap"><table>'
          .'<thead><tr><th>Type</th><th>Items</th><th>Recorded file sizes</th></tr></thead><tbody>';
        foreach((array)($report['asset_types']??[]) as $asset)
            $h.='<tr><td>'.self::e($asset['asset_type']??'Unknown').'</td><td>'
              .number_format((int)($asset['asset_count']??0)).'</td><td>'
              .self::e(self::size((int)($asset['asset_bytes']??0))).'</td></tr>';
        $h.='</tbody></table></div></details></section>';
        $h.='<section class="panel"><h2>Shared Cloudways capacity</h2><p>'
          .($free!==false&&$capacity!==false&&$capacity>0
            ?self::e(self::size((int)$free)).' free of '.self::e(self::size((int)$capacity))
            :'Capacity information unavailable')
          .'. This is the shared server, not a per-publisher quota.</p></section>';
        $h.='<section class="panel"><h2>Safe optimization</h2>'
          .'<p>Refresh only this tenant’s public/settings cache generations. This fixes stale views but does not claim to reclaim disk or database bytes.</p>'
          .'<form method="post" action="/admin/storage/refresh-cache">'
          .'<input type="hidden" name="_csrf" value="'.self::e(Http::csrf()).'">'
          .'<label class="check"><input type="checkbox" name="confirm" value="1" required>'
          .' Refresh the current tenant’s cached views without deleting records</label>'
          .'<button class="btn primary" type="submit">Refresh tenant cache</button></form>'
          .'<p><a class="btn ghost" href="/admin/performance">Shared expired-cache maintenance</a></p>'
          .'<p class="muted">Shared cache cleanup requires infrastructure authorization. Never remove source PDFs, edition assets or another publisher’s files to reclaim space without verified backups and reference checks.</p></section>';
        $h.='<section class="panel"><h2>Backup and data retention</h2>'
          .'<p>Cloudways backups, restore readiness, and physical database allocation require hosting-level verification. This page is a read-only snapshot, not a retention or deletion tool.</p>'
          .'<small class="muted">Snapshot '.self::e((string)($report['generated_at']??'Unavailable')).'</small></section>';
        View::layout('Tenant Usage & Storage',$h,['brand'=>(string)RequestContext::get('tenant_name','ePaper'),'admin'=>true]);
    }
    public static function refresh():never
    {
        $role=strtoupper((string)(Auth::currentMembership()['role_key']??''));
        if(!in_array($role,['OWNER','ADMIN','PUBLISHER_ADMIN'],true)){
            http_response_code(403);
            View::layout('Access denied','<section class="empty-state"><h1>Insufficient publisher permissions</h1></section>',
                ['brand'=>(string)RequestContext::get('tenant_name','ePaper'),'admin'=>true]);
        }
        if(($_POST['confirm']??'')!=='1')throw new \InvalidArgumentException('Confirm tenant cache refresh.');
        $tenant=RequestContext::tenantGuid();
        RuntimeCache::broadcast($tenant,['settings','public-editions','navigation','highlights','master-data']);
        MultiLayerCache::forget('tenant-utilization:v1');
        Audit::log('tenant.cache.refreshed','system',null,'SUCCESS',
            ['tenant_guid'=>$tenant,'scope'=>'tenant','deleted_records'=>0]);
        Http::redirect('/admin/storage?cache=refreshed');
    }
}
