<?php
declare(strict_types=1);
namespace EpaperSaas\Repositories;

use EpaperSaas\Core\{Store,Uuid};

/** Read-only, tenant-scoped AI candidate storage diagnostics. */
final class SqlAiClippingReadinessRepository
{
    public static function inspect(string $tenantGuid): array
    {
        try {
            $present=(int)Store::value(
                "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ep_ai_page_candidates'"
            );
            if($present<1)return ['state'=>'MISSING','counts'=>[]];
            $rows=Store::all(
                'SELECT status,COUNT(*) total FROM ep_ai_page_candidates WHERE tenant_guid=? GROUP BY status',
                [Uuid::toBinary($tenantGuid)]
            );
            $counts=[];
            foreach($rows as $row){
                $status=(string)($row['status']??'');
                if(in_array($status,['PENDING','REVIEWED','REJECTED'],true))$counts[$status]=max(0,(int)($row['total']??0));
            }
            return ['state'=>'AVAILABLE','counts'=>$counts];
        }catch(\Throwable){
            // Do not expose schema, tenant identifiers or database error details.
            return ['state'=>'UNAVAILABLE','counts'=>[]];
        }
    }
}
