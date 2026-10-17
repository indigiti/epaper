<?php
namespace EpaperSaas\Services;

use EpaperSaas\Core\{Config,RequestContext,Store,Uuid};

/**
 * RabbitMQ-native processing pipeline.
 * Each job is short-scoped: edition render, page assets, page OCR, or finalization.
 * MariaDB is authoritative; jobs are idempotent and may be delivered more than once.
 */
final class ProcessingPipeline
{
    private string $root;
    private string $jobGuid = '';
    private array $job = [];

    public function __construct()
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function handle(array $job): array
    {
        EditionOperations::assertJob($job);
        $this->job = $job;
        $this->jobGuid = Uuid::fromBinary($job['job_guid']);
        return match ((string)$job['job_type']) {
            'EDITION_RENDER' => $this->renderEdition(),
            'PAGE_ASSETS' => $this->pageAssets(),
            'PAGE_OCR' => $this->pageOcr(),
            'EDITION_FINALIZE' => $this->finalizeEdition(),
            default => throw new PermanentJobException('Unsupported RabbitMQ processing job type: ' . (string)$job['job_type']),
        };
    }

    private function renderEdition(): array
    {
        $tenant = RequestContext::tenantGuid();
        $editionGuid = $this->editionGuid();
        $edition = Store::one(
            'SELECT edition_guid,source_pdf_path,status FROM ep_editions WHERE tenant_guid=? AND edition_guid=?',
            [Uuid::toBinary($tenant), Uuid::toBinary($editionGuid)]
        );
        if (!$edition) throw new PermanentJobException('Edition not found');
        $pdf = $this->root . '/storage/' . ltrim((string)$edition['source_pdf_path'], '/');
        if (!is_file($pdf)) throw new PermanentJobException('Source PDF missing');

        $lock = $this->editionLock($editionGuid);
        if ((int)Store::value('SELECT GET_LOCK(?,0)', [$lock]) !== 1) {
            throw new RetryableJobException('Edition processor lock is busy');
        }
        try {
            // A partial fan-out must be cancelled before a fresh render can remove files.
            $children=(int)Store::value('SELECT COUNT(*) FROM ep_processing_jobs WHERE tenant_guid=? AND edition_guid=? AND job_type IN ("PAGE_ASSETS","PAGE_OCR","EDITION_FINALIZE") AND JSON_UNQUOTE(JSON_EXTRACT(payload_json,"$.pipeline_guid"))=?',[Uuid::toBinary($tenant),Uuid::toBinary($editionGuid),$this->jobGuid]);
            if($children>0&&(QueueService::payload($this->job)['stage']??'')==='RENDER_COMPLETE'){
                $pages=(int)Store::value('SELECT page_count FROM ep_editions WHERE tenant_guid=? AND edition_guid=?',[Uuid::toBinary($tenant),Uuid::toBinary($editionGuid)]);
                $stored=(int)Store::value('SELECT COUNT(*) FROM ep_pages WHERE tenant_guid=? AND edition_guid=?',[Uuid::toBinary($tenant),Uuid::toBinary($editionGuid)]);
                if($pages>0&&$stored===$pages&&$children>=2*$pages)return ['ok'=>true,'pages'=>$pages,'resumed_render_handoff'=>true];
            }
            if($children>0)throw new PermanentJobException('Partial render already dispatched page jobs. Cancel processing, then Reprocess the edition.');
            $this->report(2, 'PREPARING', 'Preparing edition');
            Store::exec('UPDATE ep_editions SET status="PROCESSING",processing_completed_at=NULL,updated_at=NOW(6) WHERE tenant_guid=? AND edition_guid=?', [Uuid::toBinary($tenant), Uuid::toBinary($editionGuid)]);

            $dir = $this->root . '/storage/media/' . $tenant . '/' . $editionGuid;
            $this->cleanupExistingEditionPages($editionGuid, $dir);
            @mkdir($dir, 0775, true);

            $this->report(5, 'RENDERING_PDF', 'Rendering PDF pages');
            $pageFiles = $this->renderPdf($pdf, $dir);
            if (!$pageFiles) throw new RetryableJobException('No PDF pages were generated');
            $total = count($pageFiles);
            $this->report(55, 'PDF_RENDERED', $total . ' pages rendered');

            foreach ($pageFiles as $i => $jpg) {
                $pageNo = $i + 1;
                $size = @getimagesize($jpg);
                $w = (int)($size[0] ?? 0); $h = (int)($size[1] ?? 0);
                if ($w < 1 || $h < 1) throw new RetryableJobException('Rendered PDF page has invalid image dimensions on page ' . $pageNo);
                $pageGuid = Uuid::v7();
                Store::exec(
                    'INSERT INTO ep_pages(page_guid,tenant_guid,edition_guid,page_number,width,height,status,created_at,updated_at) VALUES(?,?,?,?,?, ?,"PROCESSING",NOW(6),NOW(6))',
                    [Uuid::toBinary($pageGuid), Uuid::toBinary($tenant), Uuid::toBinary($editionGuid), $pageNo, $w, $h]
                );
                // Keep the renderer JPEG private as a short-lived processing source. Reader-delivery HIGHRES is WebP.
                $sourceRel = str_replace($this->root . '/storage/', '', $jpg);
                $highres = $dir . '/highres-' . $pageNo . '.webp';
                $highresQuality=$this->processingInt('highres_quality',55,100,90);
                $this->resize($jpg, $highres, $w, $h, $highresQuality);
                $hs = @getimagesize($highres); $hw=(int)($hs[0]??0); $hh=(int)($hs[1]??0);
                if($hw<1||$hh<1)throw new RetryableJobException('HIGHRES WebP has invalid geometry on page ' . $pageNo);
                $this->upsertAsset($pageGuid, 'HIGHRES', 'image/webp', str_replace($this->root . '/storage/', '', $highres), $hw, $hh);
                $rootPayload=QueueService::payload($this->job);
                $sourcePayload=['source_render_path'=>$sourceRel];
                foreach(['processing_overrides','processing_profile','reprocess_reason'] as$key){
                    if(array_key_exists($key,$rootPayload))$sourcePayload[$key]=$rootPayload[$key];
                }
                QueueService::enqueuePageJob('PAGE_ASSETS', $editionGuid, $pageGuid, $pageNo, $this->jobGuid, $sourcePayload);
                QueueService::enqueuePageJob('PAGE_OCR', $editionGuid, $pageGuid, $pageNo, $this->jobGuid, $sourcePayload);
                $this->report(55 + (40 * ($pageNo / max(1,$total))), 'QUEUING_PAGE_JOBS', 'Queued page ' . $pageNo . '/' . $total . ' asset and OCR jobs');
            }
            Store::exec('UPDATE ep_editions SET page_count=?,updated_at=NOW(6) WHERE tenant_guid=? AND edition_guid=?', [$total, Uuid::toBinary($tenant), Uuid::toBinary($editionGuid)]);
            $this->report(100, 'RENDER_COMPLETE', 'PDF render complete; page workers are running');
            return ['ok'=>true,'pages'=>$total];
        } finally {
            try { Store::value('SELECT RELEASE_LOCK(?)', [$lock]); } catch (\Throwable) {}
        }
    }

    private function pageAssets(): array
    {
        $payload = QueueService::payload($this->job);
        $editionGuid = $this->editionGuid();
        $pageGuid = (string)($payload['page_guid'] ?? '');
        $pageNo = (int)($payload['page_number'] ?? 0);
        if (!$pageGuid || $pageNo < 1) throw new PermanentJobException('PAGE_ASSETS payload is incomplete');
        $tenant = RequestContext::tenantGuid();
        $row = Store::one(
            'SELECT p.width,p.height,a.storage_path FROM ep_pages p JOIN ep_page_assets a ON a.tenant_guid=p.tenant_guid AND a.page_guid=p.page_guid AND a.asset_type="HIGHRES" WHERE p.tenant_guid=? AND p.edition_guid=? AND p.page_guid=?',
            [Uuid::toBinary($tenant), Uuid::toBinary($editionGuid), Uuid::toBinary($pageGuid)]
        );
        if (!$row) throw new RetryableJobException('High-resolution page asset is not ready');
        $src = $this->processingSource($payload, $editionGuid, (string)$row['storage_path']);
        if (!is_file($src)) throw new RetryableJobException('Page processing source file is missing');
        $dir = dirname($this->root . '/storage/' . ltrim((string)$row['storage_path'], '/'));

        $this->report(5, 'PAGE_ASSETS', 'Page ' . $pageNo . ' · preparing assets');
        $thumb = $dir . '/thumb-' . $pageNo . '.webp';
        $thumbWidth=$this->processingInt('thumbnail_width',120,1200,360);
        $thumbQuality=$this->processingInt('thumbnail_quality',45,100,78);
        $this->resize($src, $thumb, $thumbWidth, 0, $thumbQuality);
        $ts = @getimagesize($thumb); $tw=(int)($ts[0]??0);$th=(int)($ts[1]??0);
        if($tw<1||$th<1)throw new RetryableJobException('Thumbnail has invalid geometry');
        $this->upsertAsset($pageGuid,'THUMB','image/webp',str_replace($this->root.'/storage/','',$thumb),$tw,$th);
        $this->report(25, 'THUMBNAIL', 'Page ' . $pageNo . ' · thumbnail ready');

        $preview = $dir . '/preview-' . $pageNo . '.webp';
        $previewWidth=$this->processingInt('preview_width',640,3000,1280);
        $previewQuality=$this->processingInt('preview_quality',55,100,82);
        $this->resize($src, $preview, $previewWidth, 0, $previewQuality);
        $ps = @getimagesize($preview); $pw=(int)($ps[0]??0);$ph=(int)($ps[1]??0);
        if($pw<1||$ph<1)throw new RetryableJobException('Preview has invalid geometry');
        $this->upsertAsset($pageGuid,'PREVIEW','image/webp',str_replace($this->root.'/storage/','',$preview),$pw,$ph);
        $this->report(40, 'PREVIEW', 'Page ' . $pageNo . ' · reader preview ready');

        $tileDir = $dir . '/tiles-' . $pageNo;
        $this->tiles($pageGuid,$src,$tileDir,$pageNo);
        Store::exec('UPDATE ep_pages SET status="READY",updated_at=NOW(6) WHERE tenant_guid=? AND page_guid=?',[Uuid::toBinary($tenant),Uuid::toBinary($pageGuid)]);
        $this->report(100, 'ASSETS_COMPLETE', 'Page ' . $pageNo . ' · assets complete');
        return ['ok'=>true,'page'=>$pageNo];
    }

    private function pageOcr(): array
    {
        $payload = QueueService::payload($this->job);
        $editionGuid = $this->editionGuid();
        $pageGuid = (string)($payload['page_guid'] ?? '');
        $pageNo = (int)($payload['page_number'] ?? 0);
        if (!$pageGuid || $pageNo < 1) throw new PermanentJobException('PAGE_OCR payload is incomplete');
        $tenant = RequestContext::tenantGuid();
        $row = Store::one(
            'SELECT a.storage_path FROM ep_pages p JOIN ep_page_assets a ON a.tenant_guid=p.tenant_guid AND a.page_guid=p.page_guid AND a.asset_type="HIGHRES" WHERE p.tenant_guid=? AND p.edition_guid=? AND p.page_guid=?',
            [Uuid::toBinary($tenant), Uuid::toBinary($editionGuid), Uuid::toBinary($pageGuid)]
        );
        if (!$row) throw new RetryableJobException('OCR source page is not ready');
        $src=$this->processingSource($payload,$editionGuid,(string)$row['storage_path']);
        if(!is_file($src))throw new RetryableJobException('OCR source page file is missing');

        $langs = $this->ocrLanguages($editionGuid);
        $timeout=$this->processingInt('ocr_timeout_seconds',30,900,(int)Config::get('processing.ocr_timeout_seconds',300));
        $tmpDir=$this->root.'/storage/tmp/ocr';@mkdir($tmpDir,0775,true);
        $base=$tmpDir.'/ocr-'.str_replace('-','',$pageGuid).'-'.substr(bin2hex(random_bytes(6)),0,12);
        $hocrFile=$base.'.hocr';
        $this->report(5,'OCR','Page '.$pageNo.' · starting OCR ('.$langs.')');
        $result=$this->runTesseract($src,$base,$langs,$pageNo,$timeout);
        if(!$result['ok']){
            @unlink($hocrFile);
            throw new RetryableJobException($result['message'] ?: 'OCR failed');
        }
        $hocr=is_file($hocrFile)?(string)file_get_contents($hocrFile):'';@unlink($hocrFile);
        if(trim($hocr)==='')throw new RetryableJobException('Tesseract produced no OCR output');
        $text=trim(strip_tags(preg_replace('/<br\s*\/?>/i',"\n",$hocr)));
        Store::tx(function()use($tenant,$pageGuid,$langs,$text,$hocr){
            Store::exec('DELETE FROM ep_ocr_blocks WHERE tenant_guid=? AND page_guid=?',[Uuid::toBinary($tenant),Uuid::toBinary($pageGuid)]);
            Store::exec(
                'INSERT INTO ep_page_ocr(ocr_guid,tenant_guid,page_guid,language,text_content,hocr_content,confidence,status,created_at,updated_at) VALUES(?,?,?,?,?,?,NULL,"READY",NOW(6),NOW(6)) ON DUPLICATE KEY UPDATE language=VALUES(language),text_content=VALUES(text_content),hocr_content=VALUES(hocr_content),status="READY",updated_at=NOW(6)',
                [Uuid::toBinary(Uuid::v7()),Uuid::toBinary($tenant),Uuid::toBinary($pageGuid),$langs,$text,$hocr]
            );
            $this->parseBlocks($pageGuid,$hocr);
        });
        // Optional, post-commit and fail-isolated. No AI candidate is ever
        // treated as a verified/published article or clipping.
        if(AiCandidatePipeline::enabled()){
            try{
                AiCandidatePipeline::stage($editionGuid,$pageGuid,$langs,$hocr,$src);
            }catch(\\Throwable $aiError){
                // Avoid leaking document text and private media paths to logs.
                error_log('AI_CANDIDATE_STAGE_FAILED page='.substr(hash('sha256',$pageGuid),0,12).' type='.get_class($aiError));
            }
        }
        $this->report(100,'OCR_COMPLETE','Page '.$pageNo.' · OCR complete');
        return ['ok'=>true,'page'=>$pageNo,'characters'=>strlen($text)];
    }

    /**
     * Resolve the private renderer source for asset/OCR workers. New jobs carry an internal JPEG path;
     * older jobs safely fall back to the tenant-scoped HIGHRES asset.
     */
    private function processingSource(array $payload, string $editionGuid, string $fallbackStoragePath): string
    {
        $rel = ltrim((string)($payload['source_render_path'] ?? ''), '/');
        if ($rel !== '') {
            $expected = 'media/' . RequestContext::tenantGuid() . '/' . $editionGuid . '/';
            if (!str_starts_with($rel, $expected) || str_contains($rel, '..')) {
                throw new PermanentJobException('Invalid internal page processing source path');
            }
            $candidate = $this->root . '/storage/' . $rel;
            if (is_file($candidate)) return $candidate;
        }
        return $this->root . '/storage/' . ltrim($fallbackStoragePath, '/');
    }

    private function finalizeEdition(): array
    {
        $tenant=RequestContext::tenantGuid();$editionGuid=$this->editionGuid();$payload=QueueService::payload($this->job);$pipelineGuid=(string)($payload['pipeline_guid']??'');if(!$pipelineGuid)throw new PermanentJobException('Finalize job has no pipeline GUID');
        $rootState=Store::value('SELECT status FROM ep_processing_jobs WHERE tenant_guid=? AND job_guid=?',[Uuid::toBinary($tenant),Uuid::toBinary($pipelineGuid)]);
        if(in_array($rootState,['FAILED','CANCELLED'],true))throw new PermanentJobException('Render did not complete. Cancel remaining processing, then Reprocess.');
        if($rootState!=='COMPLETED')throw new RetryableJobException('Waiting for render completion');
        $jobs=Store::all('SELECT job_type,status,error_message,payload_json FROM ep_processing_jobs WHERE tenant_guid=? AND edition_guid=? AND job_type IN ("PAGE_ASSETS","PAGE_OCR")',[Uuid::toBinary($tenant),Uuid::toBinary($editionGuid)]);$active=0;$assetFailed=0;$ocrFailed=0;$failureMessages=[];
        foreach($jobs as$j){$p=QueueService::payload($j);if((string)($p['pipeline_guid']??'')!==$pipelineGuid)continue;if(in_array((string)$j['status'],['QUEUED','RUNNING','RETRY_WAIT'],true))$active++;if($j['job_type']==='PAGE_ASSETS'&&$j['status']==='FAILED'){$assetFailed++;$msg=trim((string)($j['error_message']??''));if($msg!=='')$failureMessages[]=$msg;}if($j['job_type']==='PAGE_OCR'&&$j['status']==='FAILED')$ocrFailed++;}
        if($active>0)throw new RetryableJobException('Page processing is still active');
        $this->report(20,'FINALIZING','Validating page processing results');
        $pages=(int)Store::value('SELECT COUNT(*) FROM ep_pages WHERE tenant_guid=? AND edition_guid=?',[Uuid::toBinary($tenant),Uuid::toBinary($editionGuid)]);
        $readyAssets=(int)Store::value('SELECT COUNT(DISTINCT page_guid) FROM ep_page_assets WHERE tenant_guid=? AND page_guid IN (SELECT page_guid FROM ep_pages WHERE tenant_guid=? AND edition_guid=?) AND asset_type="PREVIEW"',[Uuid::toBinary($tenant),Uuid::toBinary($tenant),Uuid::toBinary($editionGuid)]);
        if($pages<1)throw new PermanentJobException('Edition contains no rendered pages');
        if($assetFailed>0||$readyAssets<$pages){
            $root=Store::one('SELECT payload_json FROM ep_processing_jobs WHERE tenant_guid=? AND edition_guid=? AND job_type="EDITION_RENDER" AND JSON_UNQUOTE(JSON_EXTRACT(payload_json,"$.pipeline_guid"))=? ORDER BY created_at DESC LIMIT 1',[Uuid::toBinary($tenant),Uuid::toBinary($editionGuid),$pipelineGuid]);
            $rootPayload=$root?QueueService::payload($root):[];$rescueAttempt=max(0,(int)($rootPayload['auto_rescue_attempt']??0));$rescueLimit=ProcessingRecovery::automaticRescueLimit();
            if(ProcessingRecovery::automaticRescueEnabled()&&$rescueAttempt<$rescueLimit){
                $reason=implode(' | ',array_slice(array_unique($failureMessages),0,3));if($reason==='')$reason='Page asset validation failed or preview assets were incomplete.';
                $recommended=ProcessingRecovery::recommended($reason);$next=$rescueAttempt+1;
                $jobGuid=QueueService::enqueueEditionRender($editionGuid,'high',['processing_profile'=>'auto_recovery','processing_overrides'=>$recommended['overrides'],'reprocess_reason'=>substr($reason,0,500),'auto_rescue_attempt'=>$next,'auto_rescue_parent_pipeline'=>$pipelineGuid]);
                Store::exec('UPDATE ep_editions SET status="PROCESSING",page_count=?,processing_completed_at=NULL,updated_at=NOW(6) WHERE tenant_guid=? AND edition_guid=?',[$pages,Uuid::toBinary($tenant),Uuid::toBinary($editionGuid)]);
                // The new root now owns the edition; do not heartbeat the superseded finalizer.
                return['ok'=>false,'status'=>'PROCESSING','auto_recovery'=>true,'rescue_attempt'=>$next,'rescue_limit'=>$rescueLimit,'job_guid'=>$jobGuid,'asset_failed'=>$assetFailed,'ocr_failed'=>$ocrFailed];
            }
            Store::exec('UPDATE ep_editions SET status="NEEDS_ATTENTION",page_count=?,processing_completed_at=NOW(6),updated_at=NOW(6) WHERE tenant_guid=? AND edition_guid=?',[$pages,Uuid::toBinary($tenant),Uuid::toBinary($editionGuid)]);$this->report(100,'FINALIZED_WITH_ERRORS','Assets failed on '.$assetFailed.' page(s); edition needs attention');return['ok'=>false,'status'=>'NEEDS_ATTENTION','asset_failed'=>$assetFailed,'ocr_failed'=>$ocrFailed];
        }
        Store::exec('UPDATE ep_editions SET status="READY",page_count=?,processing_completed_at=NOW(6),updated_at=NOW(6) WHERE tenant_guid=? AND edition_guid=?',[$pages,Uuid::toBinary($tenant),Uuid::toBinary($editionGuid)]);$msg=$ocrFailed>0?'Edition ready with '.$ocrFailed.' OCR warning(s)':'Edition ready';$this->report(100,$ocrFailed>0?'READY_WITH_OCR_WARNINGS':'READY',$msg);try{PlatformClient::instance()->usage($tenant,'EPAPER.OCR_PAGES',max(0,$pages-$ocrFailed));}catch(\Throwable){}return['ok'=>true,'status'=>'READY','pages'=>$pages,'ocr_failed'=>$ocrFailed];
    }

    private function cleanupExistingEditionPages(string $editionGuid,string $dir): void
    {
        $tenant=RequestContext::tenantGuid();
        $pages=Store::all('SELECT page_guid FROM ep_pages WHERE tenant_guid=? AND edition_guid=?',[Uuid::toBinary($tenant),Uuid::toBinary($editionGuid)]);
        foreach($pages as$p){
            Store::exec('DELETE FROM ep_ocr_blocks WHERE tenant_guid=? AND page_guid=?',[Uuid::toBinary($tenant),$p['page_guid']]);
            Store::exec('DELETE FROM ep_page_ocr WHERE tenant_guid=? AND page_guid=?',[Uuid::toBinary($tenant),$p['page_guid']]);
            Store::exec('DELETE FROM ep_page_assets WHERE tenant_guid=? AND page_guid=?',[Uuid::toBinary($tenant),$p['page_guid']]);
        }
        Store::exec('DELETE FROM ep_pages WHERE tenant_guid=? AND edition_guid=?',[Uuid::toBinary($tenant),Uuid::toBinary($editionGuid)]);
        if(is_dir($dir))$this->removeTree($dir);
    }

    private function renderPdf(string $pdf,string $dir): array
    {
        $prefix=$dir.'/page';$dpi=$this->processingInt('pdf_dpi',96,400,180);
        if(function_exists('proc_open')){
            $cmd=['pdftoppm','-jpeg','-jpegopt','quality=88','-r',(string)$dpi,$pdf,$prefix];
            $r=$this->runProcess($cmd,900,function(int $elapsed){$this->report(min(50,8+($elapsed*.35)),'RENDERING_PDF','Rendering PDF · '.$elapsed.'s');});
            if($r['ok']){
                $files=glob($prefix.'-*.jpg')?:[];usort($files,fn($a,$b)=>strnatcmp($a,$b));if($files)return$files;
            }
        }elseif(function_exists('exec')){
            $out=[];$code=127;
            try{exec('pdftoppm -jpeg -jpegopt quality=88 -r '.(int)$dpi.' '.escapeshellarg($pdf).' '.escapeshellarg($prefix).' 2>&1',$out,$code);}catch(\Throwable){$code=127;}
            if($code===0){$files=glob($prefix.'-*.jpg')?:[];usort($files,fn($a,$b)=>strnatcmp($a,$b));if($files)return$files;}
        }
        if(class_exists('Imagick')){
            try{
                $im=new \Imagick();$im->setResolution($dpi,$dpi);$im->readImage($pdf);$files=[];$i=0;
                foreach($im as$page){$i++;$page->setImageFormat('jpeg');$page->setImageCompressionQuality(88);$f=sprintf('%s-%d.jpg',$prefix,$i);$page->writeImage($f);$files[]=$f;$this->report(min(50,10+$i),'RENDERING_PDF','Imagick rendered page '.$i);}
                $im->clear();if($files)return$files;
            }catch(\Throwable$e){throw new RetryableJobException('PDF rendering failed: '.$e->getMessage());}
        }
        throw new RetryableJobException('PDF renderer unavailable or failed');
    }

    private function resize(string $src,string $dst,int $width,int $height,int $quality): void
    {
        if(!class_exists('Imagick'))throw new PermanentJobException('PHP Imagick is required for page asset processing');
        $im=new \Imagick($src);$sw=(int)$im->getImageWidth();$sh=(int)$im->getImageHeight();
        if($sw<1||$sh<1){$im->clear();throw new RetryableJobException('Image resize source has invalid dimensions');}
        if($width<1&&$height<1){$im->clear();throw new PermanentJobException('Image resize target dimensions are invalid');}
        if($height<1)$height=max(1,(int)round($sh*($width/$sw)));if($width<1)$width=max(1,(int)round($sw*($height/$sh)));
        $im->setImagePage(0,0,0,0);$im->setImageFormat('webp');$im->setImageCompressionQuality($quality);$im->thumbnailImage($width,$height,true);$im->setImagePage(0,0,0,0);
        if(!$im->writeImage($dst)){$im->clear();throw new RetryableJobException('Image resize could not write output');}$im->clear();
        if(!is_file($dst)||((@filesize($dst)?:0)<1))throw new RetryableJobException('Image resize output is empty');
    }

    private function tiles(string $pageGuid,string $src,string $tileDir,int $pageNo): void
    {
        if(!class_exists('Imagick'))throw new PermanentJobException('PHP Imagick is required for Deep Zoom');
        if(is_dir($tileDir))$this->removeTree($tileDir);@mkdir($tileDir,0775,true);
        $orig=new \Imagick($src);$ow=(int)$orig->getImageWidth();$oh=(int)$orig->getImageHeight();if($ow<1||$oh<1){$orig->clear();throw new RetryableJobException('Deep Zoom source has invalid dimensions');}
        $tile=$this->processingInt('tile_size',256,1024,(int)Config::get('processing.tile_size',512));$scales=[];$scale=1;while(max($ow/$scale,$oh/$scale)>$tile)$scale*=2;$scale=max(1,(int)($scale/2));while($scale>=1){$scales[]=$scale;$scale=(int)($scale/2);}if(!$scales)$scales=[1];
        $levels=[];$levelCount=count($scales);
        foreach($scales as$level=>$scale){$img=clone$orig;$nw=max(1,(int)ceil($ow/$scale));$nh=max(1,(int)ceil($oh/$scale));if($scale>1)$img->resizeImage($nw,$nh,\Imagick::FILTER_LANCZOS,1);$ld=$tileDir.'/level-'.$level;@mkdir($ld,0775,true);$cols=(int)ceil($nw/$tile);$rows=(int)ceil($nh/$tile);
            for($y=0;$y<$rows;$y++){for($x=0;$x<$cols;$x++){$cw=min($tile,$nw-$x*$tile);$ch=min($tile,$nh-$y*$tile);if($cw<1||$ch<1)continue;$crop=clone$img;$crop->cropImage($cw,$ch,$x*$tile,$y*$tile);$crop->setImagePage(0,0,0,0);$crop->setImageFormat('webp');$crop->setImageCompressionQuality($this->processingInt('tile_quality',45,100,82));if(!$crop->writeImage($ld.'/'.$x.'_'.$y.'.webp')){$crop->clear();$img->clear();$orig->clear();throw new RetryableJobException('Deep Zoom tile write failed');}$crop->clear();}
                $frac=(($level+ (($y+1)/max(1,$rows)))/max(1,$levelCount));$this->report(45+($frac*50),'DEEP_ZOOM','Page '.$pageNo.' · Deep Zoom '.($level+1).'/'.$levelCount);
            }$levels[]=['level'=>$level,'width'=>$nw,'height'=>$nh,'cols'=>$cols,'rows'=>$rows];$img->clear();
        }
        file_put_contents($tileDir.'/manifest.json',json_encode(['ok'=>true,'page_guid'=>$pageGuid,'width'=>$ow,'height'=>$oh,'tile_size'=>$tile,'levels'=>$levels],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
        $this->upsertAsset($pageGuid,'TILE_MANIFEST','application/json',str_replace($this->root.'/storage/','',$tileDir.'/manifest.json'),$ow,$oh);$orig->clear();
    }

    private function runTesseract(string $src,string $outputBase,string $langs,int $pageNo,int $timeout): array
    {
        if(function_exists('proc_open')){
            $r=$this->runProcess(['tesseract',$src,$outputBase,'-l',$langs,'--dpi','170','hocr'],$timeout,function(int$elapsed)use($pageNo,$timeout){$this->report(min(95,10+(85*min(1,$elapsed/max(1,$timeout)))),'OCR','Page '.$pageNo.' · OCR '.$elapsed.'s / '.$timeout.'s');});
            if($r['timeout'])return['ok'=>false,'message'=>'OCR timed out after '.$timeout.' seconds'];
            if(!$r['ok']||!is_file($outputBase.'.hocr'))return['ok'=>false,'message'=>'OCR failed: '.substr(trim($r['stderr']?:'Tesseract exited with code '.$r['exit_code']),0,500)];
            return['ok'=>true,'message'=>''];
        }
        if(function_exists('exec')){
            $which=[];$wc=127;try{exec('command -v timeout 2>/dev/null',$which,$wc);}catch(\Throwable){$wc=127;}
            if($wc===0){$out=[];$code=127;$cmd='timeout --signal=TERM --kill-after=5s '.(int)$timeout.'s tesseract '.escapeshellarg($src).' '.escapeshellarg($outputBase).' -l '.escapeshellarg($langs).' --dpi 170 hocr 2>&1';try{exec($cmd,$out,$code);}catch(\Throwable$e){return['ok'=>false,'message'=>$e->getMessage()];}if(in_array($code,[124,137,143],true))return['ok'=>false,'message'=>'OCR timed out after '.$timeout.' seconds'];if($code!==0||!is_file($outputBase.'.hocr'))return['ok'=>false,'message'=>'OCR failed: '.substr(implode(' ',array_slice($out,-5)),0,500)];return['ok'=>true,'message'=>''];}
        }
        return['ok'=>false,'message'=>'Supervised OCR requires proc_open or GNU timeout'];
    }

    private function runProcess(array $command,int $timeout,?callable $heartbeat=null): array
    {
        $desc=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];$pipes=[];
        try{$proc=proc_open($command,$desc,$pipes);}catch(\Throwable$e){return['ok'=>false,'timeout'=>false,'exit_code'=>-1,'stderr'=>$e->getMessage()];}
        if(!is_resource($proc))return['ok'=>false,'timeout'=>false,'exit_code'=>-1,'stderr'=>'Could not start process'];
        @fclose($pipes[0]);@stream_set_blocking($pipes[1],false);@stream_set_blocking($pipes[2],false);$start=microtime(true);$last=-10;$stdout='';$stderr='';$exit=null;$timedOut=false;
        try {
        while(true){$stdout.=(string)@stream_get_contents($pipes[1]);$stderr.=(string)@stream_get_contents($pipes[2]);if(strlen($stdout)>65536)$stdout=substr($stdout,-65536);if(strlen($stderr)>65536)$stderr=substr($stderr,-65536);$st=@proc_get_status($proc);$elapsed=(int)floor(microtime(true)-$start);if($heartbeat&&($elapsed-$last)>=2){$last=$elapsed;$heartbeat($elapsed);}if(!$st||!$st['running']){$exit=$st?(int)$st['exitcode']:-1;break;}if((microtime(true)-$start)>=$timeout){$timedOut=true;@proc_terminate($proc,15);usleep(500000);$after=@proc_get_status($proc);if($after&&$after['running'])@proc_terminate($proc,9);break;}usleep(250000);}
        } catch (\Throwable $e) {
            @proc_terminate($proc,15);usleep(200000);$state=@proc_get_status($proc);
            if($state&&$state['running'])@proc_terminate($proc,9);
            foreach($pipes as$pipe)if(is_resource($pipe))@fclose($pipe);
            @proc_close($proc);throw $e;
        }
        $stdout.=(string)@stream_get_contents($pipes[1]);$stderr.=(string)@stream_get_contents($pipes[2]);@fclose($pipes[1]);@fclose($pipes[2]);$close=@proc_close($proc);if($exit===null||$exit<0)$exit=is_int($close)?$close:-1;
        return['ok'=>!$timedOut&&$exit===0,'timeout'=>$timedOut,'exit_code'=>$exit,'stdout'=>$stdout,'stderr'=>$stderr];
    }

    private function parseBlocks(string $pageGuid,string $hocr): void
    {
        if(!class_exists('DOMDocument'))return;$dom=new \DOMDocument();@$dom->loadHTML('<?xml encoding="utf-8" ?>'.$hocr);$xp=new \DOMXPath($dom);$nodes=$xp->query('//*[contains(@class,"ocr_line") or contains(@class,"ocrx_word")]');$i=0;
        foreach($nodes as$n){$title=$n->attributes?->getNamedItem('title')?->nodeValue??'';if(!preg_match('/bbox\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/',$title,$m))continue;$txt=trim($n->textContent);if($txt==='')continue;Store::exec('INSERT INTO ep_ocr_blocks(block_guid,tenant_guid,page_guid,block_order,block_type,text_content,x1,y1,x2,y2,created_at) VALUES(?,?,?,? ,"TEXT",?,?,?,?,?,NOW(6))',[Uuid::toBinary(Uuid::v7()),Uuid::toBinary(RequestContext::tenantGuid()),Uuid::toBinary($pageGuid),++$i,$txt,(int)$m[1],(int)$m[2],(int)$m[3],(int)$m[4]]);if($i>4000)break;}
    }

    private function upsertAsset(string $pageGuid,string $type,string $mime,string $rel,int $w,int $h): void
    {
        Store::exec('INSERT INTO ep_page_assets(asset_guid,tenant_guid,page_guid,asset_type,mime_type,storage_path,width,height,byte_size,checksum_sha256,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,NOW(6)) ON DUPLICATE KEY UPDATE mime_type=VALUES(mime_type),storage_path=VALUES(storage_path),width=VALUES(width),height=VALUES(height),byte_size=VALUES(byte_size),checksum_sha256=VALUES(checksum_sha256)',[Uuid::toBinary(Uuid::v7()),Uuid::toBinary(RequestContext::tenantGuid()),Uuid::toBinary($pageGuid),$type,$mime,$rel,$w,$h,@filesize($this->root.'/storage/'.$rel)?:0,is_file($this->root.'/storage/'.$rel)?hash_file('sha256',$this->root.'/storage/'.$rel):null]);
    }

    private function ocrLanguages(string $editionGuid): string
    {
        $configured=trim((string)$this->processingValue('ocr_languages',(string)Config::get('processing.ocr_languages','auto')));$auto=(string)$this->processingValue('auto_language','on')!=='off';if(!$auto&&($configured===''||strtolower($configured)==='auto'))return'eng';if($configured!==''&&strtolower($configured)!=='auto')return$configured;
        $r=Store::one('SELECT l.code,l.name FROM ep_editions e LEFT JOIN ep_languages l ON l.tenant_guid=e.tenant_guid AND l.language_guid=e.language_guid WHERE e.tenant_guid=? AND e.edition_guid=?',[Uuid::toBinary(RequestContext::tenantGuid()),Uuid::toBinary($editionGuid)]);$code=strtolower((string)($r['code']??''));$name=strtolower((string)($r['name']??''));
        if(in_array($code,['mr','mar'],true)||str_contains($name,'marathi'))return'mar+eng';if(in_array($code,['hi','hin'],true)||str_contains($name,'hindi'))return'hin+eng';return'eng';
    }

    private function processingValue(string $key,mixed $default=null): mixed
    {
        $payload=QueueService::payload($this->job);
        $overrides=$payload['processing_overrides']??null;
        if(is_array($overrides)&&array_key_exists($key,$overrides))return$overrides[$key];
        return TenantData::setting('processing.'.$key,$default);
    }

    private function processingInt(string $key,int $min,int $max,int $default): int
    {
        $value=$this->processingValue($key,(string)$default);
        if(!is_numeric($value))$value=$default;
        return max($min,min($max,(int)$value));
    }

    private function report(float $progress,string $stage,string $message): void
    {
        EditionOperations::assertJob($this->job);
        $payload=QueueService::payload($this->job);
        if(($payload['stage']??'')!==$stage){$trail=(array)($payload['trace']??[]);$trail[]=['at'=>gmdate('c'),'event'=>$stage,'detail'=>substr($message,0,500)];$payload['trace']=array_slice($trail,-16);}
        $payload['stage']=$stage;$payload['message']=$message;$payload['heartbeat_at']=gmdate('c');
        $this->job['payload_json']=json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        Store::exec('UPDATE ep_processing_jobs SET progress=?,payload_json=?,locked_at=NOW(6),updated_at=NOW(6) WHERE tenant_guid=? AND job_guid=?',[round(max(0,min(100,$progress)),2),json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$this->job['tenant_guid'],$this->job['job_guid']]);
    }

    private function editionGuid(): string
    {
        if(!$this->job['edition_guid'])throw new PermanentJobException('Processing job has no edition GUID');return Uuid::fromBinary($this->job['edition_guid']);
    }
    private function editionLock(string $editionGuid): string{return'epaper_proc_'.substr(hash('sha256',RequestContext::tenantGuid().'|'.$editionGuid),0,40);}
    private function removeTree(string $dir): void{if(!is_dir($dir))return;$real=realpath($dir);$media=realpath($this->root.'/storage/media');if(!$real||!$media||!str_starts_with($real,$media.DIRECTORY_SEPARATOR))return;$it=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($real,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);foreach($it as$f){$f->isDir()?@rmdir($f->getPathname()):@unlink($f->getPathname());}@rmdir($real);}
}
