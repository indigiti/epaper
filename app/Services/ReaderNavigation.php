<?php
namespace EpaperSaas\Services;

use EpaperSaas\Core\{RedisStore,RequestContext,Store,Uuid};

/**
 * Versioned, scoped Reader Navigation compiler.
 *
 * Public reads never require MariaDB: active snapshots are read from Redis,
 * then an atomically-written filesystem copy, then a safe built-in default.
 * MariaDB is used only in the publisher/admin control plane.
 */
final class ReaderNavigation
{
    public const ROWS = ['PRIMARY','QUICK','TRENDING','BREAKING'];

    public static function defaultSnapshot(): array
    {
        return [
            'schema' => 2,
            'scope_key' => 'global',
            'scope_name' => 'Global Reader Navigation',
            'version' => 0,
            'published_at' => null,
            'header' => ['style'=>'inherit','sticky'=>'hide_down_reveal_up'],
            'primary' => [
                ['label'=>"Today's ePaper",'url'=>'/','badge'=>null,'visibility'=>'ALL','open'=>'SAME_TAB'],
                ['label'=>'Archive','url'=>'/archive','badge'=>null,'visibility'=>'ALL','open'=>'SAME_TAB'],
                ['label'=>'Search','url'=>'/search','badge'=>null,'visibility'=>'ALL','open'=>'SAME_TAB'],
                ['label'=>'Highlights','url'=>'/#highlights','badge'=>null,'visibility'=>'ALL','open'=>'SAME_TAB'],
                ['label'=>'Plans','url'=>'/subscribe','badge'=>null,'visibility'=>'ALL','open'=>'SAME_TAB'],
            ],
            'quick' => [
                ['label'=>'Latest Edition','url'=>'/','badge'=>'LATEST','visibility'=>'ALL','open'=>'SAME_TAB'],
                ['label'=>'ePaper Archive','url'=>'/archive','badge'=>null,'visibility'=>'ALL','open'=>'SAME_TAB'],
            ],
            'trending' => [],
            'breaking' => [],
        ];
    }

    public static function publicSnapshot(array $scope = []): array
    {
        $tenant = RequestContext::tenantGuid();
        foreach (self::scopeCandidates($scope) as $scopeKey) {
            $cacheKey = self::activeCacheKey($tenant, $scopeKey);
            $cached = RedisStore::getJson($cacheKey);
            if (self::validSnapshot($cached)) {
                return self::applySchedule($cached);
            }
            $file = self::readCompiledFile($tenant, $scopeKey);
            if (self::validSnapshot($file)) {
                RedisStore::setJson($cacheKey, $file, 0);
                return self::applySchedule($file);
            }
        }
        return self::defaultSnapshot();
    }

    public static function listScopes(): array
    {
        $rows = Store::all(
            'SELECT s.*,p.name publication_name,c.name city_name,l.name language_name '
            .'FROM ep_navigation_scopes s '
            .'LEFT JOIN ep_publications p ON p.tenant_guid=s.tenant_guid AND p.publication_guid=s.publication_guid '
            .'LEFT JOIN ep_cities c ON c.tenant_guid=s.tenant_guid AND c.city_guid=s.city_guid '
            .'LEFT JOIN ep_languages l ON l.tenant_guid=s.tenant_guid AND l.language_guid=s.language_guid '
            .'WHERE s.tenant_guid=? ORDER BY (s.scope_key="global") DESC,s.name',
            [TenantData::tenantBin()]
        );
        return TenantData::toGuidRows($rows,['nav_scope_guid','tenant_guid','publication_guid','city_guid','language_guid','published_by_user_guid']);
    }

    public static function ensureGlobalScope(): array
    {
        $row = Store::one('SELECT * FROM ep_navigation_scopes WHERE tenant_guid=? AND scope_key="global" LIMIT 1',[TenantData::tenantBin()]);
        if (!$row) {
            $guid = Uuid::v7();
            Store::exec(
                'INSERT INTO ep_navigation_scopes(nav_scope_guid,tenant_guid,scope_key,name,header_style,sticky_behavior,status,draft_revision,published_revision,created_at,updated_at) '
                .'VALUES(?,?,"global","Global Reader Navigation","inherit","hide_down_reveal_up","DRAFT",1,0,NOW(6),NOW(6))',
                [Uuid::toBinary($guid),TenantData::tenantBin()]
            );
            self::seedDefaultItems($guid);
            $row = Store::one('SELECT * FROM ep_navigation_scopes WHERE tenant_guid=? AND nav_scope_guid=?',[TenantData::tenantBin(),Uuid::toBinary($guid)]);
        }
        return TenantData::guidRow($row,['nav_scope_guid','tenant_guid','publication_guid','city_guid','language_guid','published_by_user_guid']);
    }

    public static function scope(string $guid): ?array
    {
        if (!Uuid::isValid($guid)) return null;
        $row = Store::one('SELECT * FROM ep_navigation_scopes WHERE tenant_guid=? AND nav_scope_guid=?',[TenantData::tenantBin(),Uuid::toBinary($guid)]);
        return $row ? TenantData::guidRow($row,['nav_scope_guid','tenant_guid','publication_guid','city_guid','language_guid','published_by_user_guid']) : null;
    }

    public static function createScope(string $name, ?string $publicationGuid, ?string $cityGuid, ?string $languageGuid): string
    {
        $name = trim($name) ?: 'Reader Navigation';
        foreach ([$publicationGuid,$cityGuid,$languageGuid] as $g) {
            if ($g !== null && $g !== '' && !Uuid::isValid($g)) throw new \InvalidArgumentException('Invalid navigation scope identifier');
        }
        $publicationGuid = $publicationGuid ?: null;
        $cityGuid = $cityGuid ?: null;
        $languageGuid = $languageGuid ?: null;
        $scopeKey = self::scopeKey($publicationGuid,$cityGuid,$languageGuid);
        if ($scopeKey === 'global') throw new \InvalidArgumentException('Global navigation already exists');
        if (Store::one('SELECT nav_scope_guid FROM ep_navigation_scopes WHERE tenant_guid=? AND scope_key=?',[TenantData::tenantBin(),$scopeKey])) {
            throw new \InvalidArgumentException('A navigation scope already exists for this publication/city/language combination');
        }
        $guid = Uuid::v7();
        Store::exec(
            'INSERT INTO ep_navigation_scopes(nav_scope_guid,tenant_guid,scope_key,name,publication_guid,city_guid,language_guid,header_style,sticky_behavior,status,draft_revision,published_revision,created_at,updated_at) '
            .'VALUES(?,?,?,?,?,?,?,"inherit","hide_down_reveal_up","DRAFT",1,0,NOW(6),NOW(6))',
            [Uuid::toBinary($guid),TenantData::tenantBin(),$scopeKey,$name,$publicationGuid?Uuid::toBinary($publicationGuid):null,$cityGuid?Uuid::toBinary($cityGuid):null,$languageGuid?Uuid::toBinary($languageGuid):null]
        );
        return $guid;
    }

    public static function saveHeader(string $scopeGuid, string $name, string $headerStyle, string $sticky): void
    {
        self::requireScope($scopeGuid);
        $headerStyle = in_array($headerStyle,['inherit','editorial','compact','minimal'],true) ? $headerStyle : 'inherit';
        $sticky = in_array($sticky,['always','hide_down_reveal_up','static'],true) ? $sticky : 'hide_down_reveal_up';
        Store::exec(
            'UPDATE ep_navigation_scopes SET name=?,header_style=?,sticky_behavior=?,status="DRAFT",draft_revision=draft_revision+1,updated_at=NOW(6) WHERE tenant_guid=? AND nav_scope_guid=?',
            [trim($name) ?: 'Reader Navigation',$headerStyle,$sticky,TenantData::tenantBin(),Uuid::toBinary($scopeGuid)]
        );
    }

    public static function items(string $scopeGuid): array
    {
        self::requireScope($scopeGuid);
        $rows = Store::all('SELECT * FROM ep_navigation_items WHERE tenant_guid=? AND nav_scope_guid=? ORDER BY row_type,sort_order,created_at',[TenantData::tenantBin(),Uuid::toBinary($scopeGuid)]);
        return TenantData::toGuidRows($rows,['nav_item_guid','tenant_guid','nav_scope_guid']);
    }

    public static function addItem(string $scopeGuid, array $input): string
    {
        self::requireScope($scopeGuid);
        $rowType = strtoupper(trim((string)($input['row_type'] ?? 'PRIMARY')));
        if (!in_array($rowType,self::ROWS,true)) throw new \InvalidArgumentException('Invalid navigation row');
        $label = trim((string)($input['label'] ?? ''));
        if ($label === '') throw new \InvalidArgumentException('Navigation label is required');
        $destination = strtoupper(trim((string)($input['destination_type'] ?? 'CUSTOM_URL')));
        if (!in_array($destination,['HOME','ARCHIVE','SEARCH','HIGHLIGHTS','PLANS','CUSTOM_URL'],true)) $destination='CUSTOM_URL';
        $custom = trim((string)($input['custom_url'] ?? ''));
        if ($destination === 'CUSTOM_URL' && ($custom === '' || !self::safeUrl($custom))) throw new \InvalidArgumentException('Enter a safe internal or HTTPS URL');
        $visibility = strtoupper(trim((string)($input['visibility'] ?? 'ALL')));
        if (!in_array($visibility,['ALL','DESKTOP','MOBILE'],true)) $visibility='ALL';
        $open = strtoupper(trim((string)($input['open_mode'] ?? 'SAME_TAB')));
        if (!in_array($open,['SAME_TAB','NEW_TAB'],true)) $open='SAME_TAB';
        $sort = (int)Store::value('SELECT COALESCE(MAX(sort_order),-1)+1 FROM ep_navigation_items WHERE tenant_guid=? AND nav_scope_guid=? AND row_type=?',[TenantData::tenantBin(),Uuid::toBinary($scopeGuid),$rowType]);
        $guid = Uuid::v7();
        Store::exec(
            'INSERT INTO ep_navigation_items(nav_item_guid,tenant_guid,nav_scope_guid,row_type,label,destination_type,custom_url,badge,visibility,open_mode,enabled,sort_order,starts_at,expires_at,created_at,updated_at) '
            .'VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(6),NOW(6))',
            [Uuid::toBinary($guid),TenantData::tenantBin(),Uuid::toBinary($scopeGuid),$rowType,$label,$destination,$custom?:null,trim((string)($input['badge']??''))?:null,$visibility,$open,!empty($input['enabled'])?1:0,$sort,self::dateOrNull($input['starts_at']??null),self::dateOrNull($input['expires_at']??null)]
        );
        self::touchDraft($scopeGuid);
        return $guid;
    }

    public static function deleteItem(string $scopeGuid,string $itemGuid): void
    {
        self::requireScope($scopeGuid);
        Store::exec('DELETE FROM ep_navigation_items WHERE tenant_guid=? AND nav_scope_guid=? AND nav_item_guid=?',[TenantData::tenantBin(),Uuid::toBinary($scopeGuid),Uuid::toBinary($itemGuid)]);
        self::touchDraft($scopeGuid);
    }

    public static function setItemEnabled(string $scopeGuid,string $itemGuid,bool $enabled): void
    {
        self::requireScope($scopeGuid);
        Store::exec('UPDATE ep_navigation_items SET enabled=?,updated_at=NOW(6) WHERE tenant_guid=? AND nav_scope_guid=? AND nav_item_guid=?',[$enabled?1:0,TenantData::tenantBin(),Uuid::toBinary($scopeGuid),Uuid::toBinary($itemGuid)]);
        self::touchDraft($scopeGuid);
    }

    public static function moveItem(string $scopeGuid,string $itemGuid,string $direction): void
    {
        $scope = self::requireScope($scopeGuid);
        $item = Store::one('SELECT nav_item_guid,row_type,sort_order FROM ep_navigation_items WHERE tenant_guid=? AND nav_scope_guid=? AND nav_item_guid=?',[TenantData::tenantBin(),Uuid::toBinary($scopeGuid),Uuid::toBinary($itemGuid)]);
        if (!$item) throw new \RuntimeException('Navigation item not found');
        $cmp = $direction === 'up' ? '<' : '>';
        $order = $direction === 'up' ? 'DESC' : 'ASC';
        $other = Store::one('SELECT nav_item_guid,sort_order FROM ep_navigation_items WHERE tenant_guid=? AND nav_scope_guid=? AND row_type=? AND sort_order '.$cmp.' ? ORDER BY sort_order '.$order.' LIMIT 1',[TenantData::tenantBin(),Uuid::toBinary($scopeGuid),$item['row_type'],(int)$item['sort_order']]);
        if (!$other) return;
        Store::tx(function() use ($scopeGuid,$item,$other) {
            Store::exec('UPDATE ep_navigation_items SET sort_order=? WHERE tenant_guid=? AND nav_item_guid=?',[(int)$other['sort_order'],TenantData::tenantBin(),$item['nav_item_guid']]);
            Store::exec('UPDATE ep_navigation_items SET sort_order=? WHERE tenant_guid=? AND nav_item_guid=?',[(int)$item['sort_order'],TenantData::tenantBin(),$other['nav_item_guid']]);
        });
        self::touchDraft($scopeGuid);
    }

    public static function preview(string $scopeGuid): array
    {
        $scope = self::requireScope($scopeGuid);
        return self::compile($scope,self::items($scopeGuid),(int)$scope['draft_revision']);
    }

    public static function publish(string $scopeGuid, ?string $userGuid = null): array
    {
        $compiled = Store::tx(function() use ($scopeGuid,$userGuid) {
            $row = Store::one('SELECT * FROM ep_navigation_scopes WHERE tenant_guid=? AND nav_scope_guid=? FOR UPDATE',[TenantData::tenantBin(),Uuid::toBinary($scopeGuid)]);
            if (!$row) throw new \RuntimeException('Navigation scope not found');
            $scope = TenantData::guidRow($row,['nav_scope_guid','tenant_guid','publication_guid','city_guid','language_guid','published_by_user_guid']);
            $items = self::items($scopeGuid);
            $next = max((int)$scope['published_revision'],0) + 1;
            $snapshot = self::compile($scope,$items,$next);
            $versionGuid = Uuid::v7();
            Store::exec('INSERT INTO ep_navigation_versions(nav_version_guid,tenant_guid,nav_scope_guid,version_number,compiled_json,published_by_user_guid,published_at) VALUES(?,?,?,?,?,?,NOW(6))',[Uuid::toBinary($versionGuid),TenantData::tenantBin(),Uuid::toBinary($scopeGuid),$next,json_encode($snapshot,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$userGuid?Uuid::toBinary($userGuid):null]);
            Store::exec('UPDATE ep_navigation_scopes SET status="PUBLISHED",published_revision=?,published_by_user_guid=?,published_at=NOW(6),updated_at=NOW(6) WHERE tenant_guid=? AND nav_scope_guid=?',[$next,$userGuid?Uuid::toBinary($userGuid):null,TenantData::tenantBin(),Uuid::toBinary($scopeGuid)]);
            return $snapshot;
        });
        self::activate($compiled);
        return $compiled;
    }

    public static function versions(string $scopeGuid,int $limit=20): array
    {
        self::requireScope($scopeGuid);
        $rows = Store::all('SELECT nav_version_guid,version_number,published_by_user_guid,published_at FROM ep_navigation_versions WHERE tenant_guid=? AND nav_scope_guid=? ORDER BY version_number DESC LIMIT '.max(1,min(50,$limit)),[TenantData::tenantBin(),Uuid::toBinary($scopeGuid)]);
        return TenantData::toGuidRows($rows,['nav_version_guid','published_by_user_guid']);
    }

    public static function restore(string $scopeGuid,int $version,?string $userGuid=null): array
    {
        $scope = self::requireScope($scopeGuid);
        $row = Store::one('SELECT compiled_json FROM ep_navigation_versions WHERE tenant_guid=? AND nav_scope_guid=? AND version_number=?',[TenantData::tenantBin(),Uuid::toBinary($scopeGuid),$version]);
        if (!$row) throw new \RuntimeException('Navigation version not found');
        $old = json_decode((string)$row['compiled_json'],true);
        if (!is_array($old)) throw new \RuntimeException('Navigation version is corrupt');
        $next = max((int)$scope['published_revision'],0)+1;
        $old['version']=$next;$old['restored_from']=$version;$old['published_at']=gmdate('c');
        Store::tx(function() use ($scopeGuid,$next,$old,$userGuid) {
            Store::exec('INSERT INTO ep_navigation_versions(nav_version_guid,tenant_guid,nav_scope_guid,version_number,compiled_json,published_by_user_guid,published_at) VALUES(?,?,?,?,?,?,NOW(6))',[Uuid::toBinary(Uuid::v7()),TenantData::tenantBin(),Uuid::toBinary($scopeGuid),$next,json_encode($old,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$userGuid?Uuid::toBinary($userGuid):null]);
            Store::exec('UPDATE ep_navigation_scopes SET status="PUBLISHED",published_revision=?,published_by_user_guid=?,published_at=NOW(6),updated_at=NOW(6) WHERE tenant_guid=? AND nav_scope_guid=?',[$next,$userGuid?Uuid::toBinary($userGuid):null,TenantData::tenantBin(),Uuid::toBinary($scopeGuid)]);
        });
        self::activate($old);
        return $old;
    }

    public static function publishDefaultsIfUnpublished(): void
    {
        $scope = self::ensureGlobalScope();
        if ((int)($scope['published_revision']??0) === 0) self::publish((string)$scope['nav_scope_guid'],null);
    }

    private static function compile(array $scope,array $items,int $version): array
    {
        $out = [
            'schema'=>2,'scope_key'=>(string)$scope['scope_key'],'scope_name'=>(string)$scope['name'],'version'=>$version,
            'published_at'=>gmdate('c'),'header'=>['style'=>(string)$scope['header_style'],'sticky'=>(string)$scope['sticky_behavior']],
            'primary'=>[],'quick'=>[],'trending'=>[],'breaking'=>[],
        ];
        foreach ($items as $item) {
            if (!(int)$item['enabled']) continue;
            $section = match((string)$item['row_type']) {'QUICK'=>'quick','TRENDING'=>'trending','BREAKING'=>'breaking',default=>'primary'};
            $out[$section][] = [
                'label'=>(string)$item['label'],'url'=>self::resolveUrl((string)$item['destination_type'],(string)($item['custom_url']??'')),
                'badge'=>$item['badge']?:null,'visibility'=>(string)$item['visibility'],'open'=>(string)$item['open_mode'],
                'starts_at'=>$item['starts_at']?:null,'expires_at'=>$item['expires_at']?:null,
            ];
        }
        return $out;
    }

    /** Apply start/expiry windows at read time without touching MariaDB.
     *  This lets scheduled Breaking/Live and menu items activate/expire automatically
     *  while the published version remains immutable in Redis/filesystem storage.
     */
    private static function applySchedule(array $snapshot): array
    {
        $now = time();
        foreach (['primary','quick','trending','breaking'] as $section) {
            $items = is_array($snapshot[$section] ?? null) ? $snapshot[$section] : [];
            $snapshot[$section] = array_values(array_filter($items, static function(array $item) use ($now): bool {
                $starts = trim((string)($item['starts_at'] ?? ''));
                $expires = trim((string)($item['expires_at'] ?? ''));
                if ($starts !== '') { $ts = strtotime($starts); if ($ts !== false && $ts > $now) return false; }
                if ($expires !== '') { $ts = strtotime($expires); if ($ts !== false && $ts <= $now) return false; }
                return true;
            }));
        }
        return $snapshot;
    }

    private static function activate(array $snapshot): void
    {
        $tenant = RequestContext::tenantGuid();
        $scopeKey = (string)($snapshot['scope_key']??'global');
        RedisStore::setJson(self::activeCacheKey($tenant,$scopeKey),$snapshot,0);
        self::writeCompiledFile($tenant,$scopeKey,$snapshot);
        RuntimeCache::bumpGeneration($tenant,'navigation');
    }

    private static function seedDefaultItems(string $scopeGuid): void
    {
        foreach (self::defaultSnapshot()['primary'] as $i=>$item) self::seedItem($scopeGuid,'PRIMARY',$item,$i);
        foreach (self::defaultSnapshot()['quick'] as $i=>$item) self::seedItem($scopeGuid,'QUICK',$item,$i);
    }

    private static function seedItem(string $scopeGuid,string $row,array $item,int $sort): void
    {
        $dest = match($item['url']) {'/'=>'HOME','/archive'=>'ARCHIVE','/search'=>'SEARCH','/#highlights'=>'HIGHLIGHTS','/subscribe'=>'PLANS',default=>'CUSTOM_URL'};
        Store::exec('INSERT INTO ep_navigation_items(nav_item_guid,tenant_guid,nav_scope_guid,row_type,label,destination_type,custom_url,badge,visibility,open_mode,enabled,sort_order,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,"ALL","SAME_TAB",1,?,NOW(6),NOW(6))',[Uuid::toBinary(Uuid::v7()),TenantData::tenantBin(),Uuid::toBinary($scopeGuid),$row,$item['label'],$dest,$dest==='CUSTOM_URL'?$item['url']:null,$item['badge']??null,$sort]);
    }

    private static function touchDraft(string $scopeGuid): void
    {
        Store::exec('UPDATE ep_navigation_scopes SET status=IF(published_revision>0,"PUBLISHED_WITH_DRAFT","DRAFT"),draft_revision=draft_revision+1,updated_at=NOW(6) WHERE tenant_guid=? AND nav_scope_guid=?',[TenantData::tenantBin(),Uuid::toBinary($scopeGuid)]);
    }

    private static function requireScope(string $scopeGuid): array
    {
        $scope = self::scope($scopeGuid);
        if (!$scope) throw new \RuntimeException('Navigation scope not found');
        return $scope;
    }

    private static function resolveUrl(string $destination,string $custom): string
    {
        return match($destination) {
            'HOME'=>'/','ARCHIVE'=>'/archive','SEARCH'=>'/search','HIGHLIGHTS'=>'/#highlights','PLANS'=>'/subscribe',
            default=>self::safeUrl($custom)?$custom:'/',
        };
    }

    private static function safeUrl(string $url): bool
    {
        if (str_starts_with($url,'/')) return !str_starts_with($url,'//');
        $scheme = strtolower((string)parse_url($url,PHP_URL_SCHEME));
        return $scheme === 'https';
    }

    private static function dateOrNull(mixed $value): ?string
    {
        $value = trim((string)$value); if ($value==='') return null;
        $ts = strtotime($value); return $ts ? date('Y-m-d H:i:s',$ts) : null;
    }

    private static function scopeKey(?string $publicationGuid,?string $cityGuid,?string $languageGuid): string
    {
        $parts=[];
        if($publicationGuid)$parts[]='p='.$publicationGuid;
        if($cityGuid)$parts[]='c='.$cityGuid;
        if($languageGuid)$parts[]='l='.$languageGuid;
        return $parts ? implode('|',$parts) : 'global';
    }

    private static function scopeCandidates(array $scope): array
    {
        $p = isset($scope['publication_guid']) && Uuid::isValid((string)$scope['publication_guid']) ? (string)$scope['publication_guid'] : null;
        $c = isset($scope['city_guid']) && Uuid::isValid((string)$scope['city_guid']) ? (string)$scope['city_guid'] : null;
        $l = isset($scope['language_guid']) && Uuid::isValid((string)$scope['language_guid']) ? (string)$scope['language_guid'] : null;
        $raw = [
            self::scopeKey($p,$c,$l),self::scopeKey($p,$c,null),self::scopeKey($p,null,$l),self::scopeKey($p,null,null),
            self::scopeKey(null,$c,$l),self::scopeKey(null,$c,null),self::scopeKey(null,null,$l),'global'
        ];
        return array_values(array_unique($raw));
    }

    private static function activeCacheKey(string $tenant,string $scopeKey): string
    {
        return 'navigation:v2:active:'.$tenant.':'.hash('sha256',$scopeKey);
    }

    private static function compiledPath(string $tenant,string $scopeKey): string
    {
        $root = dirname(__DIR__,2).'/storage/cache/navigation/'.preg_replace('/[^a-zA-Z0-9_-]/','_',$tenant);
        return $root.'/'.hash('sha256',$scopeKey).'.json';
    }

    private static function writeCompiledFile(string $tenant,string $scopeKey,array $snapshot): void
    {
        $path=self::compiledPath($tenant,$scopeKey);$dir=dirname($path);
        if(!is_dir($dir) && !@mkdir($dir,0770,true) && !is_dir($dir)) return;
        $json=json_encode($snapshot,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        if($json===false)return;$tmp=$path.'.tmp.'.getmypid();
        if(@file_put_contents($tmp,$json,LOCK_EX)===false)return;@chmod($tmp,0660);@rename($tmp,$path);
    }

    private static function readCompiledFile(string $tenant,string $scopeKey): ?array
    {
        $path=self::compiledPath($tenant,$scopeKey);if(!is_file($path))return null;
        $raw=@file_get_contents($path);if($raw===false)return null;$d=json_decode($raw,true);return is_array($d)?$d:null;
    }

    private static function validSnapshot(?array $snapshot): bool
    {
        return is_array($snapshot) && isset($snapshot['schema'],$snapshot['primary']) && (int)$snapshot['schema']===2 && is_array($snapshot['primary']);
    }
}
