<?php
namespace EpaperSaas\Services;

use EpaperSaas\Core\Config;

/**
 * Safe per-pipeline recovery profiles for edition reprocessing.
 *
 * Overrides live only in processing-job payloads; publisher/global processing
 * settings are never mutated by a one-off recovery attempt.
 */
final class ProcessingRecovery
{
    public static function defaults(): array
    {
        return [
            'pdf_dpi' => self::clampInt(TenantData::setting('processing.pdf_dpi', '180'), 96, 400, 180),
            'preview_width' => self::clampInt(TenantData::setting('processing.preview_width', '1280'), 640, 3000, 1280),
            'thumbnail_width' => self::clampInt(TenantData::setting('processing.thumbnail_width', '360'), 120, 1200, 360),
            'tile_size' => self::clampInt(TenantData::setting('processing.tile_size', (string)Config::get('processing.tile_size', 512)), 256, 1024, 512),
            'highres_quality' => self::clampInt(TenantData::setting('processing.highres_quality', '90'), 55, 100, 90),
            'preview_quality' => self::clampInt(TenantData::setting('processing.preview_quality', '82'), 55, 100, 82),
            'thumbnail_quality' => self::clampInt(TenantData::setting('processing.thumbnail_quality', '78'), 45, 100, 78),
            'tile_quality' => self::clampInt(TenantData::setting('processing.tile_quality', '82'), 45, 100, 82),
            'ocr_timeout_seconds' => self::clampInt(TenantData::setting('processing.ocr_timeout_seconds', (string)Config::get('processing.ocr_timeout_seconds', 300)), 30, 900, 300),
            'ocr_languages' => self::cleanLanguages((string)TenantData::setting('processing.ocr_languages', (string)Config::get('processing.ocr_languages', 'auto'))),
            'auto_language' => (string)TenantData::setting('processing.auto_language', 'on') === 'off' ? 'off' : 'on',
        ];
    }

    public static function sanitize(array $input): array
    {
        $out=[];
        if(array_key_exists('pdf_dpi',$input))$out['pdf_dpi']=self::clampInt($input['pdf_dpi'],96,400,180);
        if(array_key_exists('preview_width',$input))$out['preview_width']=self::clampInt($input['preview_width'],640,3000,1280);
        if(array_key_exists('thumbnail_width',$input))$out['thumbnail_width']=self::clampInt($input['thumbnail_width'],120,1200,360);
        if(array_key_exists('tile_size',$input)){
            $tile=self::clampInt($input['tile_size'],256,1024,512);
            // Keep tile dimensions predictable for clients and memory planning.
            $allowed=[256,384,512,768,1024];
            usort($allowed,fn($a,$b)=>abs($tile-$a)<=>abs($tile-$b));
            $out['tile_size']=$allowed[0];
        }
        if(array_key_exists('highres_quality',$input))$out['highres_quality']=self::clampInt($input['highres_quality'],55,100,90);
        if(array_key_exists('preview_quality',$input))$out['preview_quality']=self::clampInt($input['preview_quality'],55,100,82);
        if(array_key_exists('thumbnail_quality',$input))$out['thumbnail_quality']=self::clampInt($input['thumbnail_quality'],45,100,78);
        if(array_key_exists('tile_quality',$input))$out['tile_quality']=self::clampInt($input['tile_quality'],45,100,82);
        if(array_key_exists('ocr_timeout_seconds',$input))$out['ocr_timeout_seconds']=self::clampInt($input['ocr_timeout_seconds'],30,900,300);
        if(array_key_exists('ocr_languages',$input))$out['ocr_languages']=self::cleanLanguages((string)$input['ocr_languages']);
        if(array_key_exists('auto_language',$input))$out['auto_language']=(string)$input['auto_language']==='off'?'off':'on';
        return$out;
    }

    /**
     * Conservative, error-aware recovery profile. These values favor reliable
     * completion over maximum raster size and never alter global defaults.
     */
    public static function recommended(?string $lastError): array
    {
        $d=self::defaults();$error=mb_strtolower(trim((string)$lastError));$o=[];$reasons=[];
        $geometry=str_contains($error,'geometry')||str_contains($error,'dimension')||str_contains($error,'resize')||str_contains($error,'tile');
        $memory=str_contains($error,'memory')||str_contains($error,'resource')||str_contains($error,'killed')||str_contains($error,'write failed');
        $ocr=str_contains($error,'ocr')||str_contains($error,'tesseract')||str_contains($error,'timeout');
        $render=str_contains($error,'pdf')||str_contains($error,'renderer')||str_contains($error,'pdftoppm')||str_contains($error,'imagick');

        $recoveryDpi=self::clampInt(TenantData::setting('processing.recovery_dpi_ceiling','160'),120,220,160);
        $recoveryTile=self::clampInt(TenantData::setting('processing.recovery_tile_size','384'),256,768,384);
        $allowedTiles=[256,384,512,768];usort($allowedTiles,fn($a,$b)=>abs($recoveryTile-$a)<=>abs($recoveryTile-$b));$recoveryTile=$allowedTiles[0];
        $recoveryOcr=self::clampInt(TenantData::setting('processing.recovery_ocr_timeout_seconds','450'),120,900,450);
        if($geometry||$memory||$render){
            $o['pdf_dpi']=max(120,min((int)$d['pdf_dpi'],$recoveryDpi));
            $o['preview_width']=max(960,min((int)$d['preview_width'],1200));
            $o['tile_size']=min((int)$d['tile_size'],$recoveryTile);
            $o['highres_quality']=min((int)$d['highres_quality'],88);
            $o['preview_quality']=min((int)$d['preview_quality'],80);
            $o['tile_quality']=min((int)$d['tile_quality'],78);
            $reasons[]='Uses the configured recovery raster/tile ceiling and a lower-memory WebP profile for safer page geometry.';
        }
        if($ocr){
            $o['ocr_timeout_seconds']=max((int)$d['ocr_timeout_seconds'],$recoveryOcr);
            $reasons[]='Uses the configured recovery OCR timeout for slow or complex newspaper pages.';
        }
        if(!$o){
            $o['pdf_dpi']=max(144,min((int)$d['pdf_dpi'],max(144,$recoveryDpi)));
            $o['ocr_timeout_seconds']=max((int)$d['ocr_timeout_seconds'],min(900,max(360,$recoveryOcr)));
            $reasons[]='Applies the configured conservative recovery profile because no specific failure signature was detected.';
        }
        return['overrides'=>self::sanitize($o),'reasons'=>$reasons];
    }

    public static function manualSmartDefault(): bool
    {
        return (string)TenantData::setting('processing.recovery_manual_smart','on') !== 'off';
    }

    public static function automaticRescueEnabled(): bool
    {
        return (string)TenantData::setting('processing.recovery_auto_rescue','off') === 'on';
    }

    public static function automaticRescueLimit(): int
    {
        return self::clampInt(TenantData::setting('processing.recovery_max_rescues','1'),0,2,1);
    }

    public static function canAutoRescue(?string $error): bool
    {
        $error=mb_strtolower(trim((string)$error));if($error==='')return false;
        foreach(['geometry','dimension','resize','tile','memory','resource','killed','write','renderer','pdftoppm','imagick','timeout','ocr','tesseract','processing source','invalid image'] as$signal){
            if(str_contains($error,$signal))return true;
        }
        return false;
    }

    public static function lastIssue(string $editionGuid): ?array
    {
        $row=\EpaperSaas\Core\Store::one(
            'SELECT job_type,status,attempt_count,error_message,payload_json,created_at,updated_at FROM ep_processing_jobs WHERE tenant_guid=? AND edition_guid=? AND (status="FAILED" OR error_message IS NOT NULL) ORDER BY updated_at DESC,created_at DESC LIMIT 1',
            [TenantData::tenantBin(),\EpaperSaas\Core\Uuid::toBinary($editionGuid)]
        );
        if(!$row)return null;
        $payload=json_decode((string)($row['payload_json']??''),true);if(!is_array($payload))$payload=[];
        return[
            'job_type'=>(string)$row['job_type'],
            'status'=>(string)$row['status'],
            'attempt_count'=>(int)$row['attempt_count'],
            'error'=>(string)($row['error_message']??($payload['last_error']??'')),
            'stage'=>(string)($payload['stage']??''),
            'updated_at'=>(string)$row['updated_at'],
        ];
    }

    private static function clampInt(mixed $value,int $min,int $max,int $fallback): int
    {
        if(!is_numeric($value))return$fallback;return max($min,min($max,(int)$value));
    }
    private static function cleanLanguages(string $value): string
    {
        $value=trim(strtolower($value));if($value===''||$value==='auto')return'auto';
        if(!preg_match('/^[a-z0-9_+-]{2,80}$/',$value))return'auto';
        return$value;
    }
}
