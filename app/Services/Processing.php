<?php
namespace EpaperSaas\Services;

use EpaperSaas\Core\{RequestContext,Store,Uuid};

final class Processing
{
    private string $root;
    private ?string $jobGuid = null;
    private int $totalPages = 0;
    private string $editionGuid = '';
    private string $lockName = '';
    private array $warnings = [];

    public function __construct()
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function processEdition(string $editionGuid, ?string $existingJobGuid = null): array
    {
        if(QueueService::rabbitEnabled())throw new \RuntimeException('Use the RabbitMQ pipeline for this installation.');
        if(!$existingJobGuid)return ['ok'=>true,'queued'=>true,'job_guid'=>QueueService::enqueueEditionRender($editionGuid)];
        $tenant = RequestContext::tenantGuid();
        $edition = Store::one(
            'SELECT edition_guid,source_pdf_path,status FROM ep_editions WHERE tenant_guid=? AND edition_guid=?',
            [Uuid::toBinary($tenant), Uuid::toBinary($editionGuid)]
        );
        if (!$edition) {
            throw new \RuntimeException('Edition not found');
        }

        EditionOperations::assertStartState((string)$edition['status']);
        $other=EditionOperations::active($editionGuid);
        foreach($other as$activeJob){if(!$existingJobGuid||Uuid::fromBinary($activeJob['job_guid'])!==$existingJobGuid)throw new \RuntimeException('Another pipeline is active');}
        $pdf = $this->root . '/storage/' . ltrim((string)$edition['source_pdf_path'], '/');
        if (!is_file($pdf)) {
            throw new \RuntimeException('Source PDF missing');
        }

        $this->editionGuid = $editionGuid;
        $this->jobGuid = $existingJobGuid ?: Uuid::v7();
        $this->lockName = 'epaper_proc_' . substr(hash('sha256', $tenant . '|' . $editionGuid), 0, 40);

        $gotLock = (int)Store::value('SELECT GET_LOCK(?,0)', [$this->lockName]);
        if ($gotLock !== 1) {
            if ($existingJobGuid) {
                Store::exec(
                    'UPDATE ep_processing_jobs SET status="QUEUED",locked_at=NULL,worker_id=NULL,available_at=DATE_ADD(NOW(6),INTERVAL 30 SECOND),payload_json=?,updated_at=NOW(6) WHERE tenant_guid=? AND job_guid=?',
                    [json_encode(['stage'=>'WAITING_FOR_LOCK','message'=>'Another processor already owns this edition'], JSON_UNESCAPED_SLASHES), Uuid::toBinary($tenant), Uuid::toBinary($existingJobGuid)]
                );
            }
            return ['ok'=>false,'already_running'=>true,'job_guid'=>$existingJobGuid];
        }

        try {
            if ($existingJobGuid) {
                Store::exec(
                    'UPDATE ep_processing_jobs SET status="RUNNING",attempt_count=attempt_count+1,progress=0,error_message=NULL,completed_at=NULL,locked_at=NOW(6),worker_id=?,updated_at=NOW(6) WHERE tenant_guid=? AND job_guid=?',
                    [$this->workerId(), Uuid::toBinary($tenant), Uuid::toBinary($this->jobGuid)]
                );
            } else {
                Store::exec(
                    'INSERT INTO ep_processing_jobs(job_guid,tenant_guid,edition_guid,job_type,status,progress,attempt_count,locked_at,worker_id,created_at,updated_at) VALUES(?,?,?,"EDITION_PROCESS","RUNNING",0,1,NOW(6),?,NOW(6),NOW(6))',
                    [Uuid::toBinary($this->jobGuid), Uuid::toBinary($tenant), Uuid::toBinary($editionGuid), $this->workerId()]
                );
            }

            Store::exec(
                'UPDATE ep_editions SET status="PROCESSING",updated_at=NOW(6) WHERE tenant_guid=? AND edition_guid=?',
                [Uuid::toBinary($tenant), Uuid::toBinary($editionGuid)]
            );

            $this->report(1, 'PREPARING', 0, 0, 'Preparing edition');
            $dir = $this->root . '/storage/media/' . $tenant . '/' . $editionGuid;
            @mkdir($dir, 0775, true);

            $oldPages = Store::all(
                'SELECT page_guid FROM ep_pages WHERE tenant_guid=? AND edition_guid=?',
                [Uuid::toBinary($tenant), Uuid::toBinary($editionGuid)]
            );
            foreach ($oldPages as $op) {
                Store::exec('DELETE FROM ep_ocr_blocks WHERE tenant_guid=? AND page_guid=?', [Uuid::toBinary($tenant), $op['page_guid']]);
                Store::exec('DELETE FROM ep_page_ocr WHERE tenant_guid=? AND page_guid=?', [Uuid::toBinary($tenant), $op['page_guid']]);
                Store::exec('DELETE FROM ep_page_assets WHERE tenant_guid=? AND page_guid=?', [Uuid::toBinary($tenant), $op['page_guid']]);
            }
            Store::exec('DELETE FROM ep_pages WHERE tenant_guid=? AND edition_guid=?', [Uuid::toBinary($tenant), Uuid::toBinary($editionGuid)]);

            $this->report(2, 'RENDERING_PDF', 0, 0, 'Rendering PDF pages');
            $pageFiles = $this->renderPdf($pdf, $dir);
            if (!$pageFiles) {
                throw new \RuntimeException('No PDF pages were generated');
            }

            $this->totalPages = count($pageFiles);
            $this->report(5, 'PDF_RENDERED', 0, $this->totalPages, $this->totalPages . ' pages detected');

            foreach ($pageFiles as $index => $file) {
                $pageNo = $index + 1;
                $this->processPage($editionGuid, $pageNo, $file, $dir);
            }

            Store::exec(
                'UPDATE ep_editions SET page_count=?,status="READY",processing_completed_at=NOW(6),updated_at=NOW(6) WHERE tenant_guid=? AND edition_guid=?',
                [$this->totalPages, Uuid::toBinary($tenant), Uuid::toBinary($editionGuid)]
            );
            $warningCount = count($this->warnings);
            $completionMessage = $warningCount ? 'Edition processing completed with ' . $warningCount . ' OCR warning' . ($warningCount === 1 ? '' : 's') : 'Edition processing completed';
            $this->report(100, 'COMPLETED', $this->totalPages, $this->totalPages, $completionMessage);
            Store::exec(
                'UPDATE ep_processing_jobs SET status="COMPLETED",progress=100,error_message=?,completed_at=NOW(6),locked_at=NULL,updated_at=NOW(6) WHERE tenant_guid=? AND job_guid=?',
                [$warningCount ? substr(implode(' | ', $this->warnings), 0, 1000) : null, Uuid::toBinary($tenant), Uuid::toBinary($this->jobGuid)]
            );

            PlatformClient::instance()->usage($tenant, 'EPAPER.OCR_PAGES', $this->totalPages);
            return ['ok'=>true,'pages'=>$this->totalPages,'job_guid'=>$this->jobGuid];
        } catch (CancelledJobException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Store::exec(
                'UPDATE ep_editions SET status="NEEDS_ATTENTION",updated_at=NOW(6) WHERE tenant_guid=? AND edition_guid=?',
                [Uuid::toBinary($tenant), Uuid::toBinary($editionGuid)]
            );
            Store::exec(
                'UPDATE ep_processing_jobs SET status="FAILED",error_message=?,completed_at=NOW(6),locked_at=NULL,payload_json=?,updated_at=NOW(6) WHERE tenant_guid=? AND job_guid=?',
                [
                    substr($e->getMessage(), 0, 1000),
                    json_encode(['stage'=>'FAILED','page'=>$this->currentPageFromPayload(),'total_pages'=>$this->totalPages,'message'=>$e->getMessage(),'heartbeat_at'=>gmdate('c')], JSON_UNESCAPED_SLASHES),
                    Uuid::toBinary($tenant),
                    Uuid::toBinary($this->jobGuid)
                ]
            );
            throw $e;
        } finally {
            try { Store::value('SELECT RELEASE_LOCK(?)', [$this->lockName]); } catch (\Throwable) {}
        }
    }

    private function renderPdf(string $pdf, string $dir): array
    {
        $prefix = $dir . '/page';
        $dpi=max(96,min(400,(int)TenantData::setting('processing.pdf_dpi','180')));
        $out = [];
        $code = 127;

        if (function_exists('exec')) {
            try {
                $cmd = 'pdftoppm -jpeg -jpegopt quality=88 -r '.(int)$dpi.' ' . escapeshellarg($pdf) . ' ' . escapeshellarg($prefix) . ' 2>&1';
                exec($cmd, $out, $code);
            } catch (\Throwable $e) {
                $out[] = $e->getMessage();
                $code = 127;
            }
        }

        if ($code !== 0) {
            if (class_exists('Imagick')) {
                $im = new \Imagick();
                $im->setResolution($dpi, $dpi);
                $im->readImage($pdf);
                $files = [];
                $i = 0;
                foreach ($im as $page) {
                    $i++;
                    $page->setImageFormat('jpeg');
                    $page->setImageCompressionQuality(88);
                    $f = sprintf('%s-%d.jpg', $prefix, $i);
                    $page->writeImage($f);
                    $files[] = $f;
                }
                $im->clear();
                return $files;
            }
            $detail = function_exists('exec') ? implode(' ', array_slice($out, -3)) : 'PHP exec() is disabled';
            throw new \RuntimeException('PDF renderer unavailable: ' . $detail . '. Install/enable pdftoppm or PHP Imagick.');
        }

        $files = glob($prefix . '-*.jpg') ?: [];
        usort($files, fn($a, $b) => strnatcmp($a, $b));
        return $files;
    }

    private function processPage(string $editionGuid, int $pageNo, string $jpg, string $dir): void
    {
        $tenant = RequestContext::tenantGuid();
        $pageGuid = Uuid::v7();
        $size = @getimagesize($jpg);
        $w = (int)($size[0] ?? 0);
        $h = (int)($size[1] ?? 0);
        if ($w < 1 || $h < 1) {
            throw new \RuntimeException('Rendered PDF page has invalid image dimensions on page ' . $pageNo);
        }

        $this->pageReport($pageNo, .04, 'PAGE_SETUP', 'Page ' . $pageNo . ' of ' . $this->totalPages);
        Store::exec(
            'INSERT INTO ep_pages(page_guid,tenant_guid,edition_guid,page_number,width,height,status,created_at,updated_at) VALUES(?,?,?,?,?, ?,"READY",NOW(6),NOW(6))',
            [Uuid::toBinary($pageGuid), Uuid::toBinary($tenant), Uuid::toBinary($editionGuid), $pageNo, $w, $h]
        );
        // The PDF renderer may use JPEG as an internal working image, but all reader-delivery page assets are WebP.
        $highres = $dir . '/highres-' . $pageNo . '.webp';
        $highresQuality=max(55,min(100,(int)TenantData::setting('processing.highres_quality','90')));
        $this->resize($jpg, $highres, $w, $h, $highresQuality);
        $highSize = @getimagesize($highres);
        $hw = (int)($highSize[0] ?? 0);
        $hh = (int)($highSize[1] ?? 0);
        if ($hw < 1 || $hh < 1) {
            throw new \RuntimeException('HIGHRES WebP asset has invalid image dimensions on page ' . $pageNo);
        }
        $this->asset($pageGuid, 'HIGHRES', 'image/webp', str_replace($this->root . '/storage/', '', $highres), $hw, $hh);

        $this->pageReport($pageNo, .12, 'THUMBNAIL', 'Generating thumbnail');
        $thumb = $dir . '/thumb-' . $pageNo . '.webp';
        $thumbWidth=max(120,min(1200,(int)TenantData::setting('processing.thumbnail_width','360')));
        $thumbQuality=max(45,min(100,(int)TenantData::setting('processing.thumbnail_quality','78')));
        $this->resize($jpg, $thumb, $thumbWidth, 0, $thumbQuality);

        $this->pageReport($pageNo, .22, 'PREVIEW', 'Generating reader preview');
        $preview = $dir . '/preview-' . $pageNo . '.webp';
        $previewWidth=max(640,min(3000,(int)TenantData::setting('processing.preview_width','1280')));
        $previewQuality=max(55,min(100,(int)TenantData::setting('processing.preview_quality','82')));
        $this->resize($jpg, $preview, $previewWidth, 0, $previewQuality);

        foreach ([['THUMB', $thumb], ['PREVIEW', $preview]] as [$type, $f]) {
            if (!is_file($f)) continue;
            $assetSize = @getimagesize($f);
            $iw = (int)($assetSize[0] ?? 0);
            $ih = (int)($assetSize[1] ?? 0);
            if ($iw < 1 || $ih < 1) {
                throw new \RuntimeException($type . ' asset has invalid image dimensions on page ' . $pageNo);
            }
            $this->asset($pageGuid, $type, 'image/webp', str_replace($this->root . '/storage/', '', $f), $iw, $ih);
        }

        $this->pageReport($pageNo, .28, 'DEEP_ZOOM', 'Generating Deep Zoom tiles');
        $this->tiles($pageGuid, $jpg, $dir . '/tiles-' . $pageNo, $pageNo);

        $this->pageReport($pageNo, .78, 'OCR', 'Running OCR');
        $this->ocr($pageGuid, $jpg, $pageNo);

        $this->pageReport($pageNo, 1.0, 'PAGE_COMPLETE', 'Page ' . $pageNo . ' completed');
    }

    private function asset(string $pageGuid, string $type, string $mime, string $rel, int $w, int $h): void
    {
        Store::exec(
            'INSERT INTO ep_page_assets(asset_guid,tenant_guid,page_guid,asset_type,mime_type,storage_path,width,height,byte_size,created_at) VALUES(?,?,?,?,?,?,?,?,?,NOW(6))',
            [Uuid::toBinary(Uuid::v7()), Uuid::toBinary(RequestContext::tenantGuid()), Uuid::toBinary($pageGuid), $type, $mime, $rel, $w, $h, @filesize($this->root . '/storage/' . $rel) ?: 0]
        );
    }

    private function resize(string $src, string $dst, int $width, int $height, int $quality): void
    {
        if (class_exists('Imagick')) {
            $im = new \Imagick($src);
            $srcW = (int)$im->getImageWidth();
            $srcH = (int)$im->getImageHeight();
            if ($srcW < 1 || $srcH < 1) {
                $im->clear();
                throw new \RuntimeException('Image resize source has invalid dimensions');
            }
            if ($width < 1 && $height < 1) {
                $im->clear();
                throw new \RuntimeException('Image resize target dimensions are invalid');
            }
            if ($height < 1) $height = max(1, (int)round($srcH * ($width / $srcW)));
            if ($width < 1) $width = max(1, (int)round($srcW * ($height / $srcH)));
            $im->setImagePage(0, 0, 0, 0);
            $im->setImageFormat('webp');
            $im->setImageCompressionQuality($quality);
            $im->thumbnailImage($width, $height, true);
            $im->setImagePage(0, 0, 0, 0);
            if (!$im->writeImage($dst)) {
                $im->clear();
                throw new \RuntimeException('Image resize could not write output');
            }
            $im->clear();
            if (!is_file($dst) || (@filesize($dst) ?: 0) < 1) {
                throw new \RuntimeException('Image resize output is empty');
            }
            return;
        }

        if (!function_exists('exec')) {
            throw new \RuntimeException('Image conversion unavailable: PHP Imagick is missing and exec() is disabled.');
        }
        $geometry = $width . 'x' . ($height > 0 ? $height : '');
        $cmd = 'convert ' . escapeshellarg($src) . ' -resize ' . escapeshellarg($geometry) . ' -quality ' . (int)$quality . ' ' . escapeshellarg($dst) . ' 2>&1';
        $out = [];
        $code = 127;
        exec($cmd, $out, $code);
        if ($code !== 0 || !is_file($dst)) {
            throw new \RuntimeException('Image conversion failed: ' . implode(' ', array_slice($out, -3)));
        }
    }

    private function tiles(string $pageGuid, string $src, string $tileDir, int $pageNo): void
    {
        if (!class_exists('Imagick')) return;
        @mkdir($tileDir, 0775, true);
        $orig = new \Imagick($src);
        $ow = (int)$orig->getImageWidth();
        $oh = (int)$orig->getImageHeight();
        if ($ow < 1 || $oh < 1) {
            $orig->clear();
            throw new \RuntimeException('Deep Zoom source has invalid dimensions on page ' . $pageNo);
        }

        $tile=max(256,min(1024,(int)TenantData::setting('processing.tile_size','512')));
        $levels = [];
        $scales = [];
        $scale = 1;
        while (max($ow / $scale, $oh / $scale) > $tile) $scale *= 2;
        $scale = max(1, (int)($scale / 2));
        while ($scale >= 1) {
            $scales[] = $scale;
            $scale = (int)($scale / 2);
        }

        $levelCount = max(1, count($scales));
        foreach ($scales as $level => $scale) {
            $img = clone $orig;
            $nw = max(1, (int)ceil($ow / $scale));
            $nh = max(1, (int)ceil($oh / $scale));
            if ($scale > 1) $img->resizeImage($nw, $nh, \Imagick::FILTER_LANCZOS, 1);
            $ld = $tileDir . '/level-' . $level;
            @mkdir($ld, 0775, true);
            $cols = (int)ceil($nw / $tile);
            $rows = (int)ceil($nh / $tile);

            for ($y = 0; $y < $rows; $y++) {
                for ($x = 0; $x < $cols; $x++) {
                    $cw = min($tile, $nw - $x * $tile);
                    $ch = min($tile, $nh - $y * $tile);
                    if ($cw < 1 || $ch < 1) continue;
                    $crop = clone $img;
                    $crop->cropImage($cw, $ch, $x * $tile, $y * $tile);
                    $crop->setImagePage(0, 0, 0, 0);
                    $crop->setImageFormat('webp');
                    $tileQuality=max(45,min(100,(int)TenantData::setting('processing.tile_quality','82')));
                    $crop->setImageCompressionQuality($tileQuality);
                    if (!$crop->writeImage($ld . '/' . $x . '_' . $y . '.webp')) {
                        $crop->clear();
                        $img->clear();
                        $orig->clear();
                        throw new \RuntimeException('Deep Zoom tile write failed on page ' . $pageNo);
                    }
                    $crop->clear();
                }
                // Heartbeat while large tile levels are being generated.
                $fraction = .30 + .40 * (($level + (($y + 1) / max(1, $rows))) / $levelCount);
                $this->pageReport($pageNo, min(.72, $fraction), 'DEEP_ZOOM', 'Deep Zoom level ' . ($level + 1) . '/' . $levelCount);
            }

            $levels[] = ['level'=>$level,'width'=>$nw,'height'=>$nh,'cols'=>$cols,'rows'=>$rows];
            $img->clear();
        }

        file_put_contents($tileDir . '/manifest.json', json_encode([
            'ok'=>true,'page_guid'=>$pageGuid,'width'=>$ow,'height'=>$oh,'tile_size'=>$tile,'levels'=>$levels
        ], JSON_PRETTY_PRINT));
        $rel = str_replace($this->root . '/storage/', '', $tileDir . '/manifest.json');
        $this->asset($pageGuid, 'TILE_MANIFEST', 'application/json', $rel, $ow, $oh);
        $orig->clear();
    }

    private function ocr(string $pageGuid, string $src, int $pageNo): void
    {
        $langs = $this->ocrLanguagesForPage($pageGuid);
        $timeout = (int)TenantData::setting('processing.ocr_timeout_seconds', '300');
        $timeout = max(30, min(900, $timeout));
        $tmpDir = $this->root . '/storage/tmp/ocr';
        @mkdir($tmpDir, 0775, true);
        $base = $tmpDir . '/ocr-' . str_replace('-', '', $pageGuid) . '-' . substr(bin2hex(random_bytes(6)), 0, 12);
        $hocrFile = $base . '.hocr';

        $result = $this->runTesseract($src, $base, $langs, $pageNo, $timeout);
        if (!$result['ok']) {
            @unlink($hocrFile);
            $status = $result['timeout'] ? 'TIMEOUT' : 'FAILED';
            $detail = $result['message'] ?: ($status === 'TIMEOUT' ? 'OCR timed out' : 'OCR failed');
            $this->recordOcrFailure($pageGuid, $langs, $status, $detail);
            $this->warnings[] = 'Page ' . $pageNo . ': ' . $detail;
            $this->pageReport($pageNo, .97, 'OCR_WARNING', $detail . ' · continuing without OCR for this page');
            return;
        }

        $hocr = is_file($hocrFile) ? (string)file_get_contents($hocrFile) : '';
        @unlink($hocrFile);
        if (trim($hocr) === '') {
            $detail = 'Tesseract produced no OCR output';
            $this->recordOcrFailure($pageGuid, $langs, 'EMPTY', $detail);
            $this->warnings[] = 'Page ' . $pageNo . ': ' . $detail;
            $this->pageReport($pageNo, .97, 'OCR_WARNING', $detail . ' · continuing');
            return;
        }

        $text = trim(strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $hocr)));
        Store::exec(
            'INSERT INTO ep_page_ocr(ocr_guid,tenant_guid,page_guid,language,text_content,hocr_content,confidence,status,created_at,updated_at) VALUES(?,?,?,?,?,?,NULL,"READY",NOW(6),NOW(6))',
            [Uuid::toBinary(Uuid::v7()), Uuid::toBinary(RequestContext::tenantGuid()), Uuid::toBinary($pageGuid), $langs, $text, $hocr]
        );
        $this->parseBlocks($pageGuid, $hocr);
    }

    private function runTesseract(string $src, string $outputBase, string $langs, int $pageNo, int $timeout): array
    {
        // Preferred path: proc_open lets the worker heartbeat while OCR is running and
        // gives us a hard per-page deadline. Use an argv array so page paths/languages
        // never pass through a shell.
        if (function_exists('proc_open')) {
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $pipes = [];
            try {
                $proc = proc_open(['tesseract', $src, $outputBase, '-l', $langs, '--dpi', '170', 'hocr'], $descriptors, $pipes);
            } catch (\Throwable $e) {
                $proc = false;
                $openError = $e->getMessage();
            }
            if (is_resource($proc)) {
                @fclose($pipes[0]);
                @stream_set_blocking($pipes[1], false);
                @stream_set_blocking($pipes[2], false);
                $started = microtime(true);
                $lastBeat = -10.0;
                $stderr = '';
                $stdout = '';
                $exitCode = null;
                $timedOut = false;

                while (true) {
                    $stdout .= (string)@stream_get_contents($pipes[1]);
                    $stderr .= (string)@stream_get_contents($pipes[2]);
                    if (strlen($stdout) > 65536) $stdout = substr($stdout, -65536);
                    if (strlen($stderr) > 65536) $stderr = substr($stderr, -65536);

                    $status = @proc_get_status($proc);
                    $elapsed = microtime(true) - $started;
                    if (($elapsed - $lastBeat) >= 2.0) {
                        $lastBeat = $elapsed;
                        $ocrFraction = min(.95, .78 + (.17 * min(1, $elapsed / max(1, $timeout))));
                        $this->pageReport($pageNo, $ocrFraction, 'OCR', 'Running OCR · ' . (int)floor($elapsed) . 's / ' . $timeout . 's');
                    }

                    if (!$status || !$status['running']) {
                        $exitCode = $status ? (int)$status['exitcode'] : -1;
                        break;
                    }
                    if ($elapsed >= $timeout) {
                        $timedOut = true;
                        @proc_terminate($proc, 15);
                        usleep(500000);
                        $after = @proc_get_status($proc);
                        if ($after && $after['running']) @proc_terminate($proc, 9);
                        break;
                    }
                    usleep(250000);
                }

                $stdout .= (string)@stream_get_contents($pipes[1]);
                $stderr .= (string)@stream_get_contents($pipes[2]);
                @fclose($pipes[1]);
                @fclose($pipes[2]);
                $closeCode = @proc_close($proc);
                if ($exitCode === null || $exitCode < 0) $exitCode = is_int($closeCode) ? $closeCode : -1;

                if ($timedOut) {
                    return ['ok'=>false,'timeout'=>true,'message'=>'OCR timed out after ' . $timeout . ' seconds'];
                }
                $hocrReady = is_file($outputBase . '.hocr') && ((@filesize($outputBase . '.hocr') ?: 0) > 0);
                if (!$hocrReady || $exitCode > 0) {
                    $detail = trim(preg_replace('/\s+/', ' ', $stderr));
                    if ($detail === '') $detail = 'Tesseract exited with code ' . $exitCode;
                    return ['ok'=>false,'timeout'=>false,'message'=>'OCR failed: ' . substr($detail, 0, 500)];
                }
                return ['ok'=>true,'timeout'=>false,'message'=>''];
            }

            if (!empty($openError)) {
                $this->pageReport($pageNo, .80, 'OCR', 'proc_open unavailable for OCR; checking bounded fallback');
            }
        }

        // Safe fallback for hosts that disable proc_open: only use exec when GNU timeout
        // is available, so OCR can never block the edition indefinitely.
        if (function_exists('exec')) {
            $which = []; $whichCode = 127;
            try { exec('command -v timeout 2>/dev/null', $which, $whichCode); } catch (\Throwable) { $whichCode = 127; }
            if ($whichCode === 0) {
                $cmd = 'timeout --signal=TERM --kill-after=5s ' . (int)$timeout . 's tesseract ' .
                    escapeshellarg($src) . ' ' . escapeshellarg($outputBase) . ' -l ' . escapeshellarg($langs) .
                    ' --dpi 170 hocr 2>&1';
                $out = []; $code = 127;
                $this->pageReport($pageNo, .80, 'OCR', 'Running bounded OCR · maximum ' . $timeout . 's');
                try { exec($cmd, $out, $code); } catch (\Throwable $e) { return ['ok'=>false,'timeout'=>false,'message'=>'OCR launcher failed: '.$e->getMessage()]; }
                if (in_array($code, [124, 137, 143], true)) return ['ok'=>false,'timeout'=>true,'message'=>'OCR timed out after ' . $timeout . ' seconds'];
                if ($code !== 0 || !is_file($outputBase . '.hocr')) {
                    $detail = trim(implode(' ', array_slice($out, -5)));
                    return ['ok'=>false,'timeout'=>false,'message'=>'OCR failed: ' . ($detail ?: 'Tesseract exited with code ' . $code)];
                }
                return ['ok'=>true,'timeout'=>false,'message'=>''];
            }
        }

        return ['ok'=>false,'timeout'=>false,'message'=>'Supervised OCR is unavailable because proc_open and the bounded timeout fallback are unavailable'];
    }

    private function ocrLanguagesForPage(string $pageGuid): string
    {
        $configured=trim((string)TenantData::setting('processing.ocr_languages','auto'));
        $auto=(string)TenantData::setting('processing.auto_language','on')!=='off';
        if(!$auto&&($configured===''||strtolower($configured)==='auto'))return'eng';
        if($configured!==''&&strtolower($configured)!=='auto')return$configured;
        $r=Store::one('SELECT l.code,l.name FROM ep_pages p JOIN ep_editions e ON e.tenant_guid=p.tenant_guid AND e.edition_guid=p.edition_guid LEFT JOIN ep_languages l ON l.tenant_guid=e.tenant_guid AND l.language_guid=e.language_guid WHERE p.tenant_guid=? AND p.page_guid=?',[Uuid::toBinary(RequestContext::tenantGuid()),Uuid::toBinary($pageGuid)]);
        $code=strtolower((string)($r['code']??''));$name=strtolower((string)($r['name']??''));
        if(in_array($code,['mr','mar'],true)||str_contains($name,'marathi'))return'mar+eng';
        if(in_array($code,['hi','hin'],true)||str_contains($name,'hindi'))return'hin+eng';
        return'eng';
    }

    private function recordOcrFailure(string $pageGuid, string $langs, string $status, string $detail): void
    {
        Store::exec(
            'INSERT INTO ep_page_ocr(ocr_guid,tenant_guid,page_guid,language,text_content,hocr_content,confidence,status,created_at,updated_at) VALUES(?,?,?,?,"",NULL,NULL,?,NOW(6),NOW(6)) ON DUPLICATE KEY UPDATE language=VALUES(language),text_content="",hocr_content=NULL,confidence=NULL,status=VALUES(status),updated_at=NOW(6)',
            [Uuid::toBinary(Uuid::v7()), Uuid::toBinary(RequestContext::tenantGuid()), Uuid::toBinary($pageGuid), $langs, substr($status, 0, 32)]
        );
    }

    private function parseBlocks(string $pageGuid, string $hocr): void
    {
        if (!class_exists('DOMDocument')) return;
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $hocr);
        $xp = new \DOMXPath($dom);
        $nodes = $xp->query('//*[contains(@class,"ocr_line") or contains(@class,"ocrx_word")]');
        $i = 0;
        foreach ($nodes as $n) {
            $title = $n->attributes?->getNamedItem('title')?->nodeValue ?? '';
            if (!preg_match('/bbox\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $title, $m)) continue;
            $txt = trim($n->textContent);
            if ($txt === '') continue;
            Store::exec(
                'INSERT INTO ep_ocr_blocks(block_guid,tenant_guid,page_guid,block_order,block_type,text_content,x1,y1,x2,y2,created_at) VALUES(?,?,?,?,"TEXT",?,?,?,?,?,NOW(6))',
                [Uuid::toBinary(Uuid::v7()), Uuid::toBinary(RequestContext::tenantGuid()), Uuid::toBinary($pageGuid), ++$i, $txt, (int)$m[1], (int)$m[2], (int)$m[3], (int)$m[4]]
            );
            if ($i > 4000) break;
        }
    }

    private function pageReport(int $pageNo, float $fraction, string $stage, string $message): void
    {
        $total = max(1, $this->totalPages);
        $fraction = max(0, min(1, $fraction));
        $progress = 5 + (94 * (($pageNo - 1 + $fraction) / $total));
        $this->report(min(99, round($progress, 2)), $stage, $pageNo, $this->totalPages, $message);
    }

    private function report(float $progress, string $stage, int $page, int $total, string $message): void
    {
        if(EditionOperations::stopIntent($this->editionGuid))throw new CancelledJobException("Cancellation requested");
        if (!$this->jobGuid) return;
        $payload = json_encode([
            'stage'=>$stage,
            'page'=>$page,
            'total_pages'=>$total,
            'message'=>$message,
            'heartbeat_at'=>gmdate('c')
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        Store::exec(
            'UPDATE ep_processing_jobs SET progress=?,payload_json=?,locked_at=NOW(6),updated_at=NOW(6) WHERE tenant_guid=? AND job_guid=?',
            [$progress, $payload, Uuid::toBinary(RequestContext::tenantGuid()), Uuid::toBinary($this->jobGuid)]
        );
    }

    private function currentPageFromPayload(): int
    {
        if (!$this->jobGuid) return 0;
        $raw = Store::value(
            'SELECT payload_json FROM ep_processing_jobs WHERE tenant_guid=? AND job_guid=?',
            [Uuid::toBinary(RequestContext::tenantGuid()), Uuid::toBinary($this->jobGuid)]
        );
        $d = $raw ? json_decode((string)$raw, true) : null;
        return (int)($d['page'] ?? 0);
    }

    private function workerId(): string
    {
        return (gethostname() ?: 'host') . ':' . getmypid();
    }
}
