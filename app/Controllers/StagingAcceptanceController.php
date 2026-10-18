<?php
declare(strict_types=1);
namespace EpaperSaas\Controllers;

use EpaperSaas\Core\{Config,Http,RequestContext,View};
use EpaperSaas\Repositories\SqlAcceptanceMetricsRepository;
use EpaperSaas\Services\{ProductionReadiness,StagingAcceptance};

/**
 * Auth/CSRF are enforced by App::runAdmin BEFORE dispatch. HTTP UI never
 * launches CLI workers, migrates databases, runs browser automation or
 * exposes uploaded media. Tenant's last snapshot is private server-side JSON.
 */
final class StagingAcceptanceController
{
    public static function dispatch(string $path,string $method): never
    {
        Http::privateNoStore();
        $service=new StagingAcceptance(new SqlAcceptanceMetricsRepository());
        if($path==='/admin/system/testing/run' && $method==='POST'){
            try{
                $readiness=ProductionReadiness::snapshot(true);
            }catch(\Throwable){
                $readiness=[
                    'status'=>'ACTION_REQUIRED',
                    'checks'=>[['category'=>'APPLICATION','name'=>'Readiness','status'=>'ACTION_REQUIRED',
                        'detail'=>'Readiness probes failed. See System Health.','action'=>'Open System Health.']]
                ];
            }
            $snapshot=$service->capture(
                \EpaperSaas\Services\TenantData::tenantBin(),
                (bool)Config::get('ai_clipping.enabled',false),
                $readiness
            );
            try{self::save($snapshot);Http::redirect('/admin/system/testing?updated=1');}
            catch(\Throwable){Http::redirect('/admin/system/testing?storage=unavailable');}
        }
        if($method!=='GET'){http_response_code(405);exit;}
        $snapshot=self::last();
        if($path==='/admin/system/testing.json'){
            Http::json(['ok'=>true,'snapshot'=>$snapshot]);
        }
        if($path==='/admin/system/testing'){
            self::page($snapshot);
        }
        http_response_code(404);exit;
    }

    /** @param array<string,mixed>|null $snapshot */
    private static function page(?array $snapshot): never
    {
        $e=static fn(mixed $v):string=>View::e($v);
        $h='<section class="admin-head readiness-head"><div><span class="eyebrow">STAGING QUALITY ASSURANCE</span>'
            .'<h1>Testing & QA</h1><p class="muted">Read-only production-like checks from the authenticated admin dashboard. '
            .'No console commands and no destructive test actions.</p></div></section>';
        $h.='<section class="panel"><div class="section-head"><div><h2>Latest validation</h2>'
            .'<p class="muted">Run Checks records a tenant-scoped snapshot of current database, queue, cache and Platform readiness.</p>'
            .'</div></div><form method="post" action="/admin/system/testing/run"><input type="hidden" name="_csrf" value="'
            .$e(Http::csrf()).'"><button class="btn primary" type="submit">Run safe checks</button></form>'
            .'<p class="muted"><a href="/admin/system/health">Full system health</a> · '
            .'<a href="/admin/system/testing.json">JSON report</a> · '
            .'<a href="/admin/processing">Processing Center</a></p></section>';
        if(isset($_GET['storage']))$h.='<p class="alert danger">Report could not be saved to private storage. Verify application storage permissions.</p>';
        if($snapshot===null){
            $h.='<section class="panel"><h2>No report recorded</h2>'
                .'<p class="muted">Select Run safe checks to create the first test report.</p></section>';
        }else{
            $status=(string)($snapshot['automated_status']??'ACTION_REQUIRED');
            $time=(string)($snapshot['captured_at']??'unknown');
            $currentVersion=\EpaperSaas\Core\Build::version();
            $capturedAt=strtotime($time);
            $stale=$capturedAt===false || (time()-$capturedAt)>3600 ||
                (string)($snapshot['app_version']??'')!==$currentVersion;
            $h.=$stale?'<p class="alert danger"><strong>STALE REPORT.</strong> The snapshot is over one hour old or from a different application build. Run safe checks again.</p>':'';
            $h.='<section class="panel"><h2>Automated readiness: '.$e(str_replace('_',' ',$status)).'</h2>'
                .'<p>Checked at <strong>'.$e($time).'</strong> (UTC).</p>'
                .'<p class="muted">Readiness is not full application acceptance. OBSERVED means source data exists, not that a user workflow passed.</p>'
                .'</section>';
            $h.='<section class="readiness-group"><h2>Functional acceptance</h2><div class="readiness-list">';
            foreach(($snapshot['functional']??[]) as $item){
                $state=(string)($item['status']??'NOT_TESTED');
                $url=(string)($item['url']??'/admin/system/health');
                if(!str_starts_with($url,'/admin/'))$url='/admin/system/health';
                $h.='<article class="readiness-row"><div class="readiness-copy">'
                    .'<strong>'.$e($item['name']??'').'</strong>'
                    .'<p>'.$e($item['detail']??'').'</p>'
                    .'<a href="'.$e($url).'">Open module →</a></div>'
                    .'<span class="readiness-badge">'.$e(str_replace('_',' ',$state)).'</span></article>';
            }
            $h.='</div></section>';
            $h.='<section class="readiness-group"><h2>Live subsystem checks</h2><div class="readiness-list">';
            foreach(($snapshot['checks']??[]) as $check){
                $state=(string)($check['status']??'REVIEW');
                $h.='<article class="readiness-row"><div class="readiness-copy">'
                    .'<strong>'.$e($check['name']??'').'</strong>'
                    .'<p>'.$e($check['detail']??'').'</p>'
                    .(!empty($check['action'])?'<small>Next: '.$e($check['action']).'</small>':'')
                    .'</div><span class="readiness-badge">'.$e(str_replace('_',' ',$state)).'</span></article>';
            }
            $h.='</div></section>';
        }
        View::layout('Testing & QA',$h,['brand'=>(string)(\EpaperSaas\Services\TenantData::brand()['site_name']??'ePaper'),'admin'=>true]);
    }

    private static function path(): string
    {
        $root=dirname(__DIR__,2).'/storage/diagnostics/qa';
        return $root.'/'.hash('sha256',RequestContext::tenantGuid()).'.json';
    }

    /** @param array<string,mixed> $snapshot */
    private static function save(array $snapshot): void
    {
        $path=self::path();$dir=dirname($path);
        if(!is_dir($dir) && !@mkdir($dir,0700,true) && !is_dir($dir)){
            throw new \RuntimeException('Private QA storage unavailable');
        }
        $json=json_encode($snapshot,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $temp=tempnam($dir,'.qa-');
        if(!$temp)throw new \RuntimeException('QA snapshot tempfile unavailable');
        try{
            if(file_put_contents($temp,$json,LOCK_EX)===false)throw new \RuntimeException('QA snapshot write failed');
            @chmod($temp,0600);
            if(!rename($temp,$path))throw new \RuntimeException('QA snapshot update failed');
        }finally{
            if(is_file($temp))@unlink($temp);
        }
    }

    /** @return array<string,mixed>|null */
    private static function last(): ?array
    {
        $path=self::path();
        if(!is_file($path))return null;
        $content=@file_get_contents($path);
        if($content===false)return null;
        $decoded=json_decode($content,true);
        return is_array($decoded)?$decoded:null;
    }
}
