<?php
namespace EpaperSaas\Services;

use EpaperSaas\Core\{RedisStore, RequestContext, Store, Uuid};

final class TenantData
{
    /** @var array<string,array<string,mixed>> */
    private static array $settingsLocal = [];

    public static function tenantBin(): string
    {
        return Uuid::toBinary(RequestContext::tenantGuid());
    }

    public static function setting(string $key, mixed $default = null): mixed
    {
        return self::scopedSetting($key, 'TENANT', null, $default);
    }

    /**
     * Load an entire settings scope once per request and keep a Redis copy.
     * This turns layouts with many setting() calls into at most one SQL query per scope.
     */
    public static function scopedSettings(string $scopeType = 'TENANT', ?string $scopeGuid = null): array
    {
        $scopeType = strtoupper($scopeType);
        $localKey = self::settingsLocalKey($scopeType, $scopeGuid);
        if (array_key_exists($localKey, self::$settingsLocal)) {
            return self::$settingsLocal[$localKey];
        }

        $generation = RuntimeCache::generation(RequestContext::tenantGuid(), 'settings');
        $cacheKey = 'settings:r'.$generation.':'.strtolower($scopeType).':'.($scopeGuid ?? 'root');
        $values = MultiLayerCache::remember($cacheKey, 3600, 21600, function() use ($scopeType,$scopeGuid): array {
            $params = [self::tenantBin(), $scopeType];
            if ($scopeGuid !== null) {
                $sql = 'SELECT setting_key,setting_value FROM ep_settings WHERE tenant_guid=? AND scope_type=? AND scope_guid=?';
                $params[] = Uuid::toBinary($scopeGuid);
            } else {
                $sql = 'SELECT setting_key,setting_value FROM ep_settings WHERE tenant_guid=? AND scope_type=? AND scope_guid IS NULL';
            }
            $out=[];
            foreach (Store::all($sql, $params) as $row) {
                $raw=(string)$row['setting_value'];$decoded=json_decode($raw,true);
                $out[(string)$row['setting_key']]=json_last_error()===JSON_ERROR_NONE?$decoded:$raw;
            }
            return $out;
        });
        return self::$settingsLocal[$localKey] = is_array($values) ? $values : [];
    }

    public static function scopedSetting(string $key, string $scopeType = 'TENANT', ?string $scopeGuid = null, mixed $default = null): mixed
    {
        $values = self::scopedSettings($scopeType, $scopeGuid);
        return array_key_exists($key, $values) ? $values[$key] : $default;
    }

    public static function effectiveSetting(string $key, mixed $default = null, ?string $publicationGuid = null, ?string $userGuid = null): mixed
    {
        if ($userGuid !== null) {
            $values = self::scopedSettings('USER', $userGuid);
            if (array_key_exists($key, $values)) return $values[$key];
        }
        if ($publicationGuid !== null) {
            $values = self::scopedSettings('PUBLICATION', $publicationGuid);
            if (array_key_exists($key, $values)) return $values[$key];
        }
        return self::setting($key, $default);
    }

    public static function setSetting(string $key, mixed $value, ?string $user = null): void
    {
        self::setScopedSetting($key, $value, 'TENANT', null, $user);
    }

    public static function setScopedSetting(string $key, mixed $value, string $scopeType = 'TENANT', ?string $scopeGuid = null, ?string $user = null): void
    {
        $scopeType = strtoupper($scopeType);
        $params = [self::tenantBin(), $key, $scopeType];
        if ($scopeGuid !== null) {
            $sql = 'SELECT setting_guid FROM ep_settings WHERE tenant_guid=? AND setting_key=? AND scope_type=? AND scope_guid=?';
            $params[] = Uuid::toBinary($scopeGuid);
        } else {
            $sql = 'SELECT setting_guid FROM ep_settings WHERE tenant_guid=? AND setting_key=? AND scope_type=? AND scope_guid IS NULL';
        }

        $row = Store::one($sql, $params);
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $scopeBin = $scopeGuid !== null ? Uuid::toBinary($scopeGuid) : null;
        if ($row) {
            Store::exec(
                'UPDATE ep_settings SET setting_value=?,updated_by_user_guid=?,updated_at=NOW(6) WHERE tenant_guid=? AND setting_guid=?',
                [$json, $user ? Uuid::toBinary($user) : null, self::tenantBin(), $row['setting_guid']]
            );
        } else {
            Store::exec(
                'INSERT INTO ep_settings(setting_guid,tenant_guid,setting_key,setting_value,scope_type,scope_guid,updated_by_user_guid,created_at,updated_at) VALUES(?,?,?,?,?,?,?,NOW(6),NOW(6))',
                [Uuid::toBinary(Uuid::v7()), self::tenantBin(), $key, $json, $scopeType, $scopeBin, $user ? Uuid::toBinary($user) : null]
            );
        }
        self::invalidateSettingsScope($scopeType, $scopeGuid);
    }

    public static function invalidateSettingsScope(string $scopeType = 'TENANT', ?string $scopeGuid = null): void
    {
        $scopeType = strtoupper($scopeType);
        unset(self::$settingsLocal[self::settingsLocalKey($scopeType, $scopeGuid)]);
        RedisStore::del(self::settingsRedisKey($scopeType, $scopeGuid));
        RuntimeCache::bumpGeneration(RequestContext::tenantGuid(), 'settings');
    }

    private static function settingsLocalKey(string $scopeType, ?string $scopeGuid): string
    {
        return RequestContext::tenantGuid() . '|' . $scopeType . '|' . ($scopeGuid ?? 'ROOT');
    }

    private static function settingsRedisKey(string $scopeType, ?string $scopeGuid): string
    {
        return 'settings:v2:' . RequestContext::tenantGuid() . ':' . strtolower($scopeType) . ':' . ($scopeGuid ?? 'root');
    }

    public static function brand(): array
    {
        return [
            'site_name' => self::setting('brand.site_name', RequestContext::get('tenant_name', 'ePaper SaaS')),
            'tagline' => self::setting('brand.tagline', 'Digital newspaper'),
            'accent' => self::setting('brand.accent', '#2563eb'),
        ];
    }

    public static function publicationBySlug(string $slug): ?array
    {
        $rev=RuntimeCache::generation(RequestContext::tenantGuid(),'master-data');
        $row=MultiLayerCache::remember('master:publication:r'.$rev.':'.hash('sha256',strtolower($slug)),1800,21600,fn()=>Store::one('SELECT * FROM ep_publications WHERE tenant_guid=? AND slug=? AND status="ACTIVE" LIMIT 1',[self::tenantBin(),$slug]));
        return is_array($row)?$row:null;
    }

    public static function editions(string $where = '1=1', array $params = [], int $limit = 50): array
    {
        return Store::all(
            'SELECT e.*,p.name publication_name,p.slug publication_slug,c.name city_name,l.name language_name '
            . 'FROM ep_editions e '
            . 'JOIN ep_publications p ON p.tenant_guid=e.tenant_guid AND p.publication_guid=e.publication_guid '
            . 'LEFT JOIN ep_cities c ON c.tenant_guid=e.tenant_guid AND c.city_guid=e.city_guid '
            . 'LEFT JOIN ep_languages l ON l.tenant_guid=e.tenant_guid AND l.language_guid=e.language_guid '
            . 'WHERE e.tenant_guid=? AND ' . $where . ' '
            . 'ORDER BY e.edition_date DESC,e.created_at DESC LIMIT ' . max(1, min(200, $limit)),
            array_merge([self::tenantBin()], $params)
        );
    }

    /**
     * Publisher edition library with the latest root processing attempt joined
     * in the same SQL request. Keeps the Editions screen scan-friendly without
     * introducing one processing query per row.
     */
    public static function countEditionsForAdmin(string $where,array $params): int
    {
        return (int)Store::value('SELECT COUNT(*) FROM ep_editions e JOIN ep_publications p ON p.tenant_guid=e.tenant_guid AND p.publication_guid=e.publication_guid WHERE e.tenant_guid=? AND '.$where,array_merge([self::tenantBin()],$params));
    }

    public static function editionsForAdmin(string $where = '1=1', array $params = [], int $limit = 100, int $offset = 0): array
    {
        $sql='SELECT e.*,p.name publication_name,p.slug publication_slug,c.name city_name,l.name language_name,'
            .'j.job_guid processing_root_guid,j.status processing_status,j.progress processing_progress,j.attempt_count processing_attempt_count,'
            .'j.error_message processing_error,j.payload_json processing_payload,j.created_at processing_started_at,'
            .'js.processing_updated_at,COALESCE(js.processing_active_count,0) processing_active_count,COALESCE(js.processing_run_count,0) processing_run_count,js.latest_processing_error '
            .'FROM ep_editions e '
            .'JOIN ep_publications p ON p.tenant_guid=e.tenant_guid AND p.publication_guid=e.publication_guid '
            .'LEFT JOIN ep_cities c ON c.tenant_guid=e.tenant_guid AND c.city_guid=e.city_guid '
            .'LEFT JOIN ep_languages l ON l.tenant_guid=e.tenant_guid AND l.language_guid=e.language_guid '
            .'LEFT JOIN ep_processing_jobs j ON j.tenant_guid=e.tenant_guid AND j.edition_guid=e.edition_guid AND j.job_type="EDITION_RENDER" '
            .'AND j.job_guid=(SELECT j2.job_guid FROM ep_processing_jobs j2 WHERE j2.tenant_guid=e.tenant_guid AND j2.edition_guid=e.edition_guid AND j2.job_type="EDITION_RENDER" ORDER BY j2.created_at DESC,j2.job_guid DESC LIMIT 1) '
            .'LEFT JOIN (SELECT tenant_guid,edition_guid,MAX(updated_at) processing_updated_at,'
            .'SUM(CASE WHEN status IN ("QUEUED","RUNNING","RETRY_WAIT") THEN 1 ELSE 0 END) processing_active_count,'
            .'SUM(CASE WHEN job_type="EDITION_RENDER" THEN 1 ELSE 0 END) processing_run_count,'
            .'SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN error_message IS NOT NULL AND error_message<>"" THEN error_message END ORDER BY updated_at DESC SEPARATOR "|||"),"|||",1) latest_processing_error '
            .'FROM ep_processing_jobs GROUP BY tenant_guid,edition_guid) js ON js.tenant_guid=e.tenant_guid AND js.edition_guid=e.edition_guid '
            .'WHERE e.tenant_guid=? AND '.$where.' '
            .'ORDER BY e.edition_date DESC,e.created_at DESC LIMIT '.max(1,min(200,$limit)).' OFFSET '.max(0,$offset);
        return ProcessingSummary::attach(self::toGuidRows(Store::all($sql,array_merge([self::tenantBin()],$params)),[
            'edition_guid','tenant_guid','publication_guid','city_guid','language_guid'
        ]));
    }

    /**
     * Edition list plus first-page cover assets in a single query.
     * Replaces the former pageVisual() N+1 pattern on home/archive/publication screens.
     */
    public static function editionsWithCovers(string $where = '1=1', array $params = [], int $limit = 50): array
    {
        return PublicReadCache::editionsWithCovers($where,$params,$limit);
    }

    public static function editionsWithCoversUncached(string $where = '1=1', array $params = [], int $limit = 50): array
    {
        $sql = 'SELECT e.*,p.name publication_name,p.slug publication_slug,c.name city_name,l.name language_name,'
            . 'fp.page_guid first_page_guid,'
            . 'ta.asset_guid thumb_asset_guid,ta.byte_size thumb_byte_size,ta.width thumb_width,ta.height thumb_height,'
            . 'pa.asset_guid preview_asset_guid,pa.byte_size preview_byte_size,pa.width preview_width,pa.height preview_height,'
            . 'ha.asset_guid highres_asset_guid,ha.byte_size highres_byte_size,ha.width highres_width,ha.height highres_height '
            . 'FROM ep_editions e '
            . 'JOIN ep_publications p ON p.tenant_guid=e.tenant_guid AND p.publication_guid=e.publication_guid '
            . 'LEFT JOIN ep_cities c ON c.tenant_guid=e.tenant_guid AND c.city_guid=e.city_guid '
            . 'LEFT JOIN ep_languages l ON l.tenant_guid=e.tenant_guid AND l.language_guid=e.language_guid '
            . 'LEFT JOIN ep_pages fp ON fp.tenant_guid=e.tenant_guid AND fp.edition_guid=e.edition_guid AND fp.page_number=1 '
            . 'LEFT JOIN ep_page_assets ta ON ta.tenant_guid=e.tenant_guid AND ta.page_guid=fp.page_guid AND ta.asset_type="THUMB" '
            . 'LEFT JOIN ep_page_assets pa ON pa.tenant_guid=e.tenant_guid AND pa.page_guid=fp.page_guid AND pa.asset_type="PREVIEW" '
            . 'LEFT JOIN ep_page_assets ha ON ha.tenant_guid=e.tenant_guid AND ha.page_guid=fp.page_guid AND ha.asset_type="HIGHRES" '
            . 'WHERE e.tenant_guid=? AND ' . $where . ' '
            . 'ORDER BY e.edition_date DESC,e.created_at DESC LIMIT ' . max(1, min(200, $limit));

        $rows = Store::all($sql, array_merge([self::tenantBin()], $params));
        return self::toGuidRows($rows, [
            'edition_guid', 'tenant_guid', 'publication_guid', 'city_guid', 'language_guid',
            'first_page_guid', 'thumb_asset_guid', 'preview_asset_guid', 'highres_asset_guid',
        ]);
    }

    public static function editionBySlug(string $slug): ?array
    {
        return Store::one(
            'SELECT e.*,p.name publication_name,p.slug publication_slug,c.name city_name,l.name language_name '
            . 'FROM ep_editions e '
            . 'JOIN ep_publications p ON p.tenant_guid=e.tenant_guid AND p.publication_guid=e.publication_guid '
            . 'LEFT JOIN ep_cities c ON c.tenant_guid=e.tenant_guid AND c.city_guid=e.city_guid '
            . 'LEFT JOIN ep_languages l ON l.tenant_guid=e.tenant_guid AND l.language_guid=e.language_guid '
            . 'WHERE e.tenant_guid=? AND e.slug=? LIMIT 1',
            [self::tenantBin(), $slug]
        );
    }

    public static function pages(string $editionGuid): array
    {
        return Store::all('SELECT * FROM ep_pages WHERE tenant_guid=? AND edition_guid=? ORDER BY page_number', [self::tenantBin(), Uuid::toBinary($editionGuid)]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function pagesWithAssets(string $editionGuid): array
    {
        $rows = Store::all(self::pageAssetsSql('p.edition_guid=?', 'ORDER BY p.page_number'), [self::tenantBin(), Uuid::toBinary($editionGuid)]);
        return self::normalizePageAssetRows($rows);
    }

    public static function page(string $editionGuid, int $number): ?array
    {
        return Store::one('SELECT * FROM ep_pages WHERE tenant_guid=? AND edition_guid=? AND page_number=? LIMIT 1', [self::tenantBin(), Uuid::toBinary($editionGuid), $number]);
    }

    public static function pageWithAssets(string $editionGuid, int $number): ?array
    {
        $row = Store::one(self::pageAssetsSql('p.edition_guid=? AND p.page_number=?', 'LIMIT 1'), [self::tenantBin(), Uuid::toBinary($editionGuid), $number]);
        if (!$row) return null;
        return self::normalizePageAssetRows([$row])[0] ?? null;
    }

    private static function pageAssetsSql(string $predicate, string $suffix): string
    {
        return 'SELECT p.*,'
            . 'ta.asset_guid thumb_asset_guid,ta.storage_path thumb_storage_path,ta.byte_size thumb_byte_size,ta.mime_type thumb_mime_type,'
            . 'pa.asset_guid preview_asset_guid,pa.storage_path preview_storage_path,pa.byte_size preview_byte_size,pa.mime_type preview_mime_type,'
            . 'ha.asset_guid highres_asset_guid,ha.storage_path highres_storage_path,ha.byte_size highres_byte_size,ha.mime_type highres_mime_type,'
            . 'ma.asset_guid tile_manifest_asset_guid,ma.storage_path tile_manifest_storage_path,ma.byte_size tile_manifest_byte_size '
            . 'FROM ep_pages p '
            . 'LEFT JOIN ep_page_assets ta ON ta.tenant_guid=p.tenant_guid AND ta.page_guid=p.page_guid AND ta.asset_type="THUMB" '
            . 'LEFT JOIN ep_page_assets pa ON pa.tenant_guid=p.tenant_guid AND pa.page_guid=p.page_guid AND pa.asset_type="PREVIEW" '
            . 'LEFT JOIN ep_page_assets ha ON ha.tenant_guid=p.tenant_guid AND ha.page_guid=p.page_guid AND ha.asset_type="HIGHRES" '
            . 'LEFT JOIN ep_page_assets ma ON ma.tenant_guid=p.tenant_guid AND ma.page_guid=p.page_guid AND ma.asset_type="TILE_MANIFEST" '
            . 'WHERE p.tenant_guid=? AND ' . $predicate . ' ' . $suffix;
    }

    private static function normalizePageAssetRows(array $rows): array
    {
        return self::toGuidRows($rows, [
            'page_guid', 'tenant_guid', 'edition_guid', 'section_guid',
            'thumb_asset_guid', 'preview_asset_guid', 'highres_asset_guid', 'tile_manifest_asset_guid',
        ]);
    }

    public static function pageAssets(string $pageGuid): array
    {
        $rows = Store::all('SELECT * FROM ep_page_assets WHERE tenant_guid=? AND page_guid=?', [self::tenantBin(), Uuid::toBinary($pageGuid)]);
        $out = [];
        foreach ($rows as $r) {
            foreach (['asset_guid', 'tenant_guid', 'page_guid'] as $k) {
                if (isset($r[$k]) && is_string($r[$k]) && strlen($r[$k]) === 16) $r[$k] = Uuid::fromBinary($r[$k]);
            }
            $out[$r['asset_type']] = $r;
        }
        return $out;
    }

    public static function toGuidRows(array $rows, array $keys): array
    {
        foreach ($rows as &$r) {
            foreach ($keys as $k) {
                if (isset($r[$k]) && is_string($r[$k]) && strlen($r[$k]) === 16) $r[$k] = Uuid::fromBinary($r[$k]);
            }
        }
        unset($r);
        return $rows;
    }

    public static function guidRow(?array $r, array $keys): ?array
    {
        if (!$r) return null;
        foreach ($keys as $k) {
            if (isset($r[$k]) && is_string($r[$k]) && strlen($r[$k]) === 16) $r[$k] = Uuid::fromBinary($r[$k]);
        }
        return $r;
    }
}
