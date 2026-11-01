<?php
declare(strict_types=1);
namespace EpaperSaas\Repositories;

use EpaperSaas\Contracts\AcceptanceMetricsRepository;
use EpaperSaas\Core\Store;

/**
 * Read-only, tenant-bound aggregate queries. A missing optional module reports
 * unavailable; it must never become a successful QA result.
 */
final class SqlAcceptanceMetricsRepository implements AcceptanceMetricsRepository
{
    public function collect(string $tenantBinary,bool $aiEnabled): array
    {
        $queries=[
            'editions'=>'SELECT COUNT(*) total, COALESCE(SUM(status="PUBLISHED"),0) published, COALESCE(SUM(status="NEEDS_ATTENTION"),0) needs_attention FROM ep_editions WHERE tenant_guid=?',
            'pages'=>'SELECT COUNT(*) total FROM ep_pages WHERE tenant_guid=?',
            'clips'=>'SELECT COUNT(*) total, COALESCE(SUM(visibility="PUBLIC"),0) public_clips FROM ep_clips WHERE tenant_guid=?',
            'jobs'=>'SELECT COUNT(*) total, COALESCE(SUM(status IN ("QUEUED","RUNNING","RETRY_WAIT")),0) active, COALESCE(SUM(status="FAILED"),0) failed FROM ep_processing_jobs WHERE tenant_guid=?',
        ];
        if($aiEnabled){
            $queries['ai']='SELECT COUNT(*) total, COALESCE(SUM(status="PENDING"),0) pending FROM ep_ai_page_candidates WHERE tenant_guid=?';
        }
        $out=['editions'=>null,'pages'=>null,'clips'=>null,'jobs'=>null,'ai'=>null];
        foreach($queries as $type=>$sql){
            try{
                $row=Store::one($sql,[$tenantBinary]);
                if(!is_array($row))continue;
                $values=[];
                foreach($row as $name=>$value){
                    if(is_string($name) && ($value===null || is_numeric($value)))$values[$name]=(int)$value;
                }
                $out[$type]=$values;
            }catch(\Throwable){
                // A missing tenant-owned table or unavailable DB is not a PASS.
                $out[$type]=null;
            }
        }
        return $out;
    }
}
