<?php
declare(strict_types=1);
namespace EpaperSaas\Repositories;
use EpaperSaas\Contracts\EditorialActionRepository;
use EpaperSaas\Core\{Store,Uuid};

/** All reads are tenant-bound, bounded and side-effect free. */
final class SqlEditorialActionRepository implements EditorialActionRepository
{
    public function editions(string $tenantBinary,string $status,int $limit): array
    {
        if(!in_array($status,['READY','NEEDS_ATTENTION'],true))throw new \InvalidArgumentException('Invalid status');
        $query='SELECT e.edition_guid,e.title,e.edition_date,e.status,p.name publication_name
          FROM ep_editions e JOIN ep_publications p ON p.tenant_guid=e.tenant_guid AND p.publication_guid=e.publication_guid
          WHERE e.tenant_guid=? AND e.status=? '
          .($status==='READY'?'AND e.publish_mode="MANUAL" ':'')
          .'ORDER BY e.edition_date DESC,e.updated_at DESC LIMIT '.self::limit($limit);
        return self::ids(Store::all($query,[$tenantBinary,$status]),['edition_guid']);
    }
    public function failedJobs(string $tenantBinary,int $limit): array
    {
        $query='SELECT j.job_guid,j.edition_guid,j.job_type,j.updated_at,e.title,e.edition_date,p.name publication_name
          FROM ep_processing_jobs j JOIN ep_editions e ON e.tenant_guid=j.tenant_guid AND e.edition_guid=j.edition_guid
          JOIN ep_publications p ON p.tenant_guid=e.tenant_guid AND p.publication_guid=e.publication_guid
          WHERE j.tenant_guid=? AND j.status="FAILED" AND e.status NOT IN ("ARCHIVED","TRASHED")
          ORDER BY j.updated_at DESC LIMIT '.self::limit($limit);
        return self::ids(Store::all($query,[$tenantBinary]),['job_guid','edition_guid']);
    }
    public function pendingAi(string $tenantBinary,int $limit): array
    {
        $total=(int)Store::value('SELECT COUNT(*) FROM ep_ai_page_candidates WHERE tenant_guid=? AND status="PENDING"',[$tenantBinary]);
        $query='SELECT c.page_guid,p.page_number,e.title,e.edition_date,pub.name publication_name
          FROM ep_ai_page_candidates c JOIN ep_pages p ON p.tenant_guid=c.tenant_guid
           AND p.page_guid=c.page_guid AND p.edition_guid=c.edition_guid
          JOIN ep_editions e ON e.tenant_guid=c.tenant_guid AND e.edition_guid=c.edition_guid
          JOIN ep_publications pub ON pub.tenant_guid=e.tenant_guid AND pub.publication_guid=e.publication_guid
          WHERE c.tenant_guid=? AND c.status="PENDING"
          ORDER BY c.updated_at DESC LIMIT '.self::limit($limit);
        return ['total'=>$total,'rows'=>self::ids(Store::all($query,[$tenantBinary]),['page_guid'])];
    }
    private static function limit(int $limit): int {return max(1,min(5,$limit));}
    private static function ids(array $rows,array $columns): array
    {
        foreach($rows as &$row)foreach($columns as $column)
            if(isset($row[$column]))$row[$column]=Uuid::fromBinary($row[$column]);
        unset($row);
        return $rows;
    }
}
