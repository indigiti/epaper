<?php
namespace EpaperSaas\Services;
use EpaperSaas\Core\{Store,Uuid};
final class ProcessingSummary
{
    /** Batch only current-pipeline jobs for the visible editions. */
    public static function attach(array $editions): array
    {
        $roots=[];$editionBins=[];foreach($editions as$e)if(!empty($e['processing_root_guid'])){$roots[]=Uuid::fromBinary($e['processing_root_guid']);$editionBins[]=Uuid::toBinary($e['edition_guid']);}
        if(!$roots)return $editions;
        $ph=implode(',',array_fill(0,count($roots),'?'));
        $jobs=Store::all('SELECT edition_guid,job_type,status,progress,updated_at,TIMESTAMPDIFF(SECOND,updated_at,NOW(6)) age_seconds FROM ep_processing_jobs WHERE tenant_guid=? AND edition_guid IN ('.$ph.') AND JSON_UNQUOTE(JSON_EXTRACT(payload_json,"$.pipeline_guid")) IN ('.$ph.')',array_merge([TenantData::tenantBin()],$editionBins,$roots));
        $group=[];foreach($jobs as$j)$group[Uuid::fromBinary($j['edition_guid'])][]=$j;
        foreach($editions as&$e){$rows=$group[$e['edition_guid']]??[];$counts=[];$averages=[];$stalled=false;
            foreach($rows as$j){$type=$j['job_type'];$counts[$type]['total']=($counts[$type]['total']??0)+1;$counts[$type]['done']=($counts[$type]['done']??0)+($j['status']==='COMPLETED'?1:0);$averages[$type][]=(float)$j['progress'];if(in_array($j['status'],['QUEUED','RUNNING','RETRY_WAIT'],true)&&(int)$j['age_seconds']>EditionOperations::STALE_SECONDS)$stalled=true;}
            $pct=0;foreach(['EDITION_RENDER'=>.1,'PAGE_ASSETS'=>.5,'PAGE_OCR'=>.35,'EDITION_FINALIZE'=>.05] as$type=>$w){$a=$averages[$type]??[];if($a)$pct+=array_sum($a)/count($a)*$w;}
            $parts=[];foreach(['EDITION_RENDER'=>'Render','PAGE_ASSETS'=>'Assets','PAGE_OCR'=>'OCR'] as$type=>$label){$c=$counts[$type]??['done'=>0,'total'=>0];$parts[]=$label.' '.$c['done'].'/'.$c['total'];}
            $e['overall_progress']=round($pct,1);$e['stage_summary']=implode(' · ',$parts);$e['processing_stalled']=$stalled;
        }unset($e);return $editions;
    }
}
