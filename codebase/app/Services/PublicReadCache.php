<?php
namespace EpaperSaas\Services;

use EpaperSaas\Core\{RequestContext,Store,Uuid};

/** Public SSR read models cached independently of private/account state. */
final class PublicReadCache
{
    public static function editionsWithCovers(string $where,array $params,int $limit): array
    {
        $rev=RuntimeCache::generation(RequestContext::tenantGuid(),'public-editions');$key='public:editions:r'.$rev.':'.hash('sha256',$where.'|'.serialize($params).'|'.$limit);
        return MultiLayerCache::remember($key,300,1800,fn()=>TenantData::editionsWithCoversUncached($where,$params,$limit));
    }
    public static function publications(): array
    {
        $rev=RuntimeCache::generation(RequestContext::tenantGuid(),'master-data');return MultiLayerCache::remember('public:publications:r'.$rev,1800,21600,function(){return TenantData::toGuidRows(Store::all('SELECT publication_guid,name,slug FROM ep_publications WHERE tenant_guid=? AND status="ACTIVE" ORDER BY is_default DESC,name',[TenantData::tenantBin()]),['publication_guid']);});
    }
    public static function archiveFilters(): array
    {
        $rev=RuntimeCache::generation(RequestContext::tenantGuid(),'master-data');return MultiLayerCache::remember('public:archive-filters:r'.$rev,1800,21600,function(){return[
            'publications'=>TenantData::toGuidRows(Store::all('SELECT publication_guid,name FROM ep_publications WHERE tenant_guid=? AND status="ACTIVE" ORDER BY name',[TenantData::tenantBin()]),['publication_guid']),
            'cities'=>TenantData::toGuidRows(Store::all('SELECT city_guid,name FROM ep_cities WHERE tenant_guid=? AND status="ACTIVE" ORDER BY name',[TenantData::tenantBin()]),['city_guid']),
            'languages'=>TenantData::toGuidRows(Store::all('SELECT language_guid,name FROM ep_languages WHERE tenant_guid=? AND status="ACTIVE" ORDER BY name',[TenantData::tenantBin()]),['language_guid']),
        ];});
    }
    public static function archiveCalendar(string $monthStart,string $monthEnd,string $publication='',string $city='',string $language=''): array
    {
        $rev=RuntimeCache::generation(RequestContext::tenantGuid(),'public-editions');$filterKey=implode('|',[$monthStart,$monthEnd,$publication,$city,$language]);$key='public:archive-calendar:r'.$rev.':'.hash('sha256',$filterKey);
        return MultiLayerCache::remember($key,300,1800,function()use($monthStart,$monthEnd,$publication,$city,$language){$extra=[];$params=[TenantData::tenantBin(),$monthStart,$monthEnd];if($publication!==''&&Uuid::isValid($publication)){$extra[]='publication_guid=?';$params[]=Uuid::toBinary($publication);}if($city!==''&&Uuid::isValid($city)){$extra[]='city_guid=?';$params[]=Uuid::toBinary($city);}if($language!==''&&Uuid::isValid($language)){$extra[]='language_guid=?';$params[]=Uuid::toBinary($language);}$sql='SELECT DATE_FORMAT(edition_date,"%Y-%m-%d") edition_date,COUNT(*) edition_count FROM ep_editions WHERE tenant_guid=? AND status="PUBLISHED" AND edition_date>=? AND edition_date<?'.($extra?' AND '.implode(' AND ',$extra):'').' GROUP BY edition_date ORDER BY edition_date';$rows=Store::all($sql,$params);$out=[];foreach($rows as$r)$out[(string)$r['edition_date']]=(int)$r['edition_count'];return$out;});
    }
    /**
     * Compact Reader edition index. Returns only published editions for the
     * current publication + city + language variant, with no media joins.
     */
    public static function readerEditionCalendar(string $editionDate,string $publicationGuid,?string $cityGuid=null,?string $languageGuid=null,int $windowDays=45): array
    {
        if(!Uuid::isValid($publicationGuid))return[];$windowDays=max(7,min(120,$windowDays));
        try{$center=new \DateTimeImmutable($editionDate);}catch(\Throwable){$center=new \DateTimeImmutable('today');}
        $start=$center->modify('-'.$windowDays.' days')->format('Y-m-d');$end=$center->modify('+'.($windowDays+1).' days')->format('Y-m-d');
        $rev=RuntimeCache::generation(RequestContext::tenantGuid(),'public-editions');$identity=implode('|',[$start,$end,$publicationGuid,$cityGuid??'NULL',$languageGuid??'NULL']);$key='public:reader-edition-calendar:r'.$rev.':'.hash('sha256',$identity);
        return MultiLayerCache::remember($key,300,1800,function()use($start,$end,$publicationGuid,$cityGuid,$languageGuid){
            $sql='SELECT DATE_FORMAT(edition_date,"%Y-%m-%d") edition_date,slug,page_count,created_at FROM ep_editions WHERE tenant_guid=? AND status="PUBLISHED" AND publication_guid=? AND edition_date>=? AND edition_date<?';$params=[TenantData::tenantBin(),Uuid::toBinary($publicationGuid),$start,$end];
            if($cityGuid!==null&&Uuid::isValid($cityGuid)){$sql.=' AND city_guid=?';$params[]=Uuid::toBinary($cityGuid);}else $sql.=' AND city_guid IS NULL';
            if($languageGuid!==null&&Uuid::isValid($languageGuid)){$sql.=' AND language_guid=?';$params[]=Uuid::toBinary($languageGuid);}else $sql.=' AND language_guid IS NULL';
            $rows=Store::all($sql.' ORDER BY edition_date ASC,created_at DESC',$params);
            $seen=[];$out=[];foreach($rows as$row){$date=(string)($row['edition_date']??'');if($date===''||isset($seen[$date]))continue;$seen[$date]=true;$out[]=['edition_date'=>$date,'slug'=>(string)($row['slug']??''),'page_count'=>(int)($row['page_count']??0)];}return$out;
        });
    }

    /** Cached back-edition list for the exact publication + city + language variant. */
    public static function readerEditionHistory(string $publicationGuid,?string $cityGuid=null,?string $languageGuid=null,int $limit=365): array
    {
        if(!Uuid::isValid($publicationGuid))return[];$limit=max(30,min(1000,$limit));
        $rev=RuntimeCache::generation(RequestContext::tenantGuid(),'public-editions');$identity=implode('|',[$publicationGuid,$cityGuid??'NULL',$languageGuid??'NULL',(string)$limit]);$key='public:reader-edition-history:r'.$rev.':'.hash('sha256',$identity);
        return MultiLayerCache::remember($key,300,1800,function()use($publicationGuid,$cityGuid,$languageGuid,$limit){
            $sql='SELECT DATE_FORMAT(edition_date,"%Y-%m-%d") edition_date,slug,page_count,created_at FROM ep_editions WHERE tenant_guid=? AND status="PUBLISHED" AND publication_guid=?';$params=[TenantData::tenantBin(),Uuid::toBinary($publicationGuid)];
            if($cityGuid!==null&&Uuid::isValid($cityGuid)){$sql.=' AND city_guid=?';$params[]=Uuid::toBinary($cityGuid);}else $sql.=' AND city_guid IS NULL';
            if($languageGuid!==null&&Uuid::isValid($languageGuid)){$sql.=' AND language_guid=?';$params[]=Uuid::toBinary($languageGuid);}else $sql.=' AND language_guid IS NULL';
            $rows=Store::all($sql.' ORDER BY edition_date DESC,created_at DESC LIMIT '.($limit*2),$params);
            $seen=[];$out=[];foreach($rows as$row){$date=(string)($row['edition_date']??'');if($date===''||isset($seen[$date]))continue;$seen[$date]=true;$out[]=['edition_date'=>$date,'slug'=>(string)($row['slug']??''),'page_count'=>(int)($row['page_count']??0)];if(count($out)>=$limit)break;}return$out;
        });
    }

    public static function highlights(int $limit=8): array
    {
        $rev=RuntimeCache::generation(RequestContext::tenantGuid(),'highlights');return MultiLayerCache::remember('public:highlights:r'.$rev.':'.$limit,120,900,fn()=>Store::all('SELECT title,summary,target_url FROM ep_highlights WHERE tenant_guid=? AND status="ACTIVE" AND (starts_at IS NULL OR starts_at<=NOW()) AND (ends_at IS NULL OR ends_at>=NOW()) ORDER BY sort_order,created_at DESC LIMIT '.max(1,min(20,$limit)),[TenantData::tenantBin()]));
    }
    public static function warm(): array
    {
        $out=[];$out['home_editions']=count(self::editionsWithCovers('e.status="PUBLISHED"',[],12));$out['publications']=count(self::publications());$out['highlights']=count(self::highlights());$out['archive_filters']=array_sum(array_map('count',self::archiveFilters()));$m=new \DateTimeImmutable('first day of this month');$out['archive_calendar']=array_sum(self::archiveCalendar($m->format('Y-m-d'),$m->modify('first day of next month')->format('Y-m-d')));return$out;
    }
}
