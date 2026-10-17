<?php
namespace EpaperSaas\Services;

use EpaperSaas\Core\{Http,RequestContext,Store};

/** Search/news discovery documents compiled through the v2 multi-layer cache. */
final class SeoDiscovery
{
    public static function sitemapIndex(): string
    {
        return self::cached('index',300,3600,function(){
            $base=Http::origin();$names=['sitemap-static.xml','sitemap-articles.xml','sitemap-authors.xml','sitemap-topics.xml','sitemap-entities.xml','sitemap-editions.xml','news-sitemap.xml'];$x='<?xml version="1.0" encoding="UTF-8"?>'."\n".'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';foreach($names as$n)$x.='<sitemap><loc>'.self::x($base.'/'.$n).'</loc></sitemap>';$x.='</sitemapindex>';return$x;
        });
    }

    public static function staticPages(): string
    {
        return self::cached('static',900,7200,function(){
            $paths=['/','/archive','/trust'];
            $trust=['/masthead'=>'trust.masthead','/editorial-standards'=>'trust.editorial_standards','/corrections-policy'=>'trust.corrections_policy','/fact-checking-policy'=>'trust.fact_checking_policy','/ethics-policy'=>'trust.ethics_policy','/ai-policy'=>'trust.ai_policy','/advertising-policy'=>'trust.advertising_policy','/ownership'=>'trust.ownership','/contact-newsroom'=>'trust.contact_newsroom'];
            foreach($trust as$path=>$key)if(trim((string)TenantData::setting($key,''))!=='')$paths[]=$path;
            $rows=array_map(fn($p)=>['path'=>$p,'updated_at'=>gmdate('c')],$paths);
            return self::urlset($rows,fn($r)=>(string)$r['path']);
        });
    }

    public static function articles(): string
    {
        return self::cached('articles',300,3600,function(){
            $rows=Store::all('SELECT slug,updated_at,published_at FROM ep_articles WHERE tenant_guid=? AND status="PUBLISHED" ORDER BY published_at DESC LIMIT 50000',[TenantData::tenantBin()]);return self::urlset($rows,fn($r)=>'/article/'.rawurlencode((string)$r['slug']));
        });
    }
    public static function authors(): string{return self::cached('authors',900,7200,function(){$rows=Store::all('SELECT slug,updated_at FROM ep_authors WHERE tenant_guid=? AND status="ACTIVE" ORDER BY updated_at DESC LIMIT 50000',[TenantData::tenantBin()]);return self::urlset($rows,fn($r)=>'/author/'.rawurlencode((string)$r['slug']));});}
    public static function topics(): string{return self::cached('topics',900,7200,function(){$rows=Store::all('SELECT slug,updated_at FROM ep_topics WHERE tenant_guid=? AND status="ACTIVE" ORDER BY updated_at DESC LIMIT 50000',[TenantData::tenantBin()]);return self::urlset($rows,fn($r)=>'/topic/'.rawurlencode((string)$r['slug']));});}
    public static function entities(): string{return self::cached('entities',900,7200,function(){$rows=Store::all('SELECT slug,updated_at FROM ep_entities WHERE tenant_guid=? AND status="ACTIVE" ORDER BY updated_at DESC LIMIT 50000',[TenantData::tenantBin()]);return self::urlset($rows,fn($r)=>'/entity/'.rawurlencode((string)$r['slug']));});}
    public static function editions(): string{return self::cached('editions',900,7200,function(){$rows=Store::all('SELECT slug,updated_at,published_at FROM ep_editions WHERE tenant_guid=? AND status="PUBLISHED" ORDER BY edition_date DESC LIMIT 50000',[TenantData::tenantBin()]);return self::urlset($rows,fn($r)=>'/edition/'.rawurlencode((string)$r['slug']).'/page/1');});}

    public static function news(): string
    {
        return self::cached('news',120,900,function(){
            $rows=Store::all('SELECT a.slug,a.title,a.published_at,p.name publication_name,l.code language_code,l.locale language_locale FROM ep_articles a JOIN ep_publications p ON p.tenant_guid=a.tenant_guid AND p.publication_guid=a.publication_guid LEFT JOIN ep_languages l ON l.tenant_guid=a.tenant_guid AND l.language_guid=a.language_guid WHERE a.tenant_guid=? AND a.status="PUBLISHED" AND a.published_at>=DATE_SUB(NOW(6),INTERVAL 2 DAY) ORDER BY a.published_at DESC LIMIT 1000',[TenantData::tenantBin()]);$x='<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">';foreach($rows as$r){$lang=SemanticNews::locale((string)($r['language_locale']??''),(string)($r['language_code']??''));$lang=strtolower(explode('-',$lang?:'en')[0]);$configured=trim((string)TenantData::setting('seo.news_publication_name',''));$publication=$configured!==''?$configured:(string)$r['publication_name'];$x.='<url><loc>'.self::x(Http::origin().'/article/'.rawurlencode((string)$r['slug'])).'</loc><news:news><news:publication><news:name>'.self::x($publication).'</news:name><news:language>'.self::x($lang).'</news:language></news:publication><news:publication_date>'.self::x(gmdate('c',strtotime((string)$r['published_at']))).'</news:publication_date><news:title>'.self::x((string)$r['title']).'</news:title></news:news></url>';}$x.='</urlset>';return$x;
        });
    }

    public static function robots(): string
    {
        $base=Http::origin();return "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /account/\nDisallow: /api/\nDisallow: /media/\nDisallow: /offline.html\nDisallow: /offline-library.html\nDisallow: /offline-reader.html\n\nSitemap: {$base}/sitemap.xml\nSitemap: {$base}/news-sitemap.xml\n";
    }

    public static function warm(): array
    {
        $docs=['index'=>'sitemapIndex','static'=>'staticPages','articles'=>'articles','authors'=>'authors','topics'=>'topics','entities'=>'entities','editions'=>'editions','news'=>'news'];$out=[];foreach($docs as$k=>$m){try{$out[$k]=strlen(self::$m());}catch(\Throwable$e){$out[$k]='ERROR: '.$e->getMessage();}}return$out;
    }

    private static function cached(string $name,int $fresh,int $stale,callable $loader): string
    {
        $rev=RuntimeCache::generation(RequestContext::tenantGuid(),'seo');$v=MultiLayerCache::remember('seo:xml:r'.$rev.':'.$name,$fresh,$stale,$loader);return is_string($v)?$v:'';
    }
    private static function urlset(array $rows,callable $path): string{$x='<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';foreach($rows as$r){$x.='<url><loc>'.self::x(Http::origin().$path($r)).'</loc>';if(!empty($r['updated_at'])||!empty($r['published_at']))$x.='<lastmod>'.self::x(gmdate('c',strtotime((string)($r['updated_at']??$r['published_at'])))).'</lastmod>';$x.='</url>';}$x.='</urlset>';return$x;}
    private static function x(string $v): string{return htmlspecialchars($v,ENT_XML1|ENT_QUOTES,'UTF-8');}
}
