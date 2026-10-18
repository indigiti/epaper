<?php
declare(strict_types=1);
namespace EpaperSaas\Repositories;
use EpaperSaas\Contracts\PageArticleReader;
use EpaperSaas\Core\{Store,Uuid};
use EpaperSaas\Services\TenantData;
/** Both lookups are ALWAYS scoped to the resolved tenant. */
final class SqlPageArticleReader implements PageArticleReader
{
    public function dimensions(string $pageGuid): ?array
    {
        return Store::one('SELECT width,height FROM ep_pages WHERE tenant_guid=? AND page_guid=?',
            [TenantData::tenantBin(),Uuid::toBinary($pageGuid)]);
    }
    public function coveringBlocks(string $pageGuid,int $x,int $y): array
    {
        return Store::all('SELECT block_guid,text_content,x1,y1,x2,y2 FROM ep_ocr_blocks WHERE tenant_guid=? AND page_guid=? AND x1<=? AND x2>=? AND y1<=? AND y2>=? ORDER BY (x2-x1)*(y2-y1) LIMIT 12',
            [TenantData::tenantBin(),Uuid::toBinary($pageGuid),$x,$x,$y,$y]);
    }
}
