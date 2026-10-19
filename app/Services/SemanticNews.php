<?php
namespace EpaperSaas\Services;

use EpaperSaas\Core\{Http,RequestContext,Store,Uuid};

/**
 * Semantic publishing read-models for crawlable news/article discovery.
 * MariaDB remains authoritative. Redis/filesystem caching is revisioned by the SEO generation.
 */
final class SemanticNews
{
    public static function articleBySlug(string $slug): ?array
    {
        $slug=self::slug($slug);if($slug==='')return null;
        $rev=RuntimeCache::generation(RequestContext::tenantGuid(),'seo');
        $row=MultiLayerCache::remember('seo:article:r'.$rev.':'.hash('sha256',$slug),300,3600,function()use($slug){
            $r=Store::one('SELECT a.*,p.name publication_name,p.slug publication_slug,c.name city_name,l.name language_name,l.code language_code,l.locale language_locale,s.name section_name,e.slug edition_slug,e.edition_date,pg.page_number FROM ep_articles a JOIN ep_publications p ON p.tenant_guid=a.tenant_guid AND p.publication_guid=a.publication_guid LEFT JOIN ep_cities c ON c.tenant_guid=a.tenant_guid AND c.city_guid=a.city_guid LEFT JOIN ep_languages l ON l.tenant_guid=a.tenant_guid AND l.language_guid=a.language_guid LEFT JOIN ep_sections s ON s.tenant_guid=a.tenant_guid AND s.section_guid=a.section_guid LEFT JOIN ep_editions e ON e.tenant_guid=a.tenant_guid AND e.edition_guid=a.edition_guid LEFT JOIN ep_pages pg ON pg.tenant_guid=a.tenant_guid AND pg.page_guid=a.page_guid WHERE a.tenant_guid=? AND a.slug=? AND a.status="PUBLISHED" LIMIT 1',[TenantData::tenantBin(),$slug]);
            if(!$r)return null;$r=TenantData::guidRow($r,['article_guid','publication_guid','city_guid','language_guid','section_guid','edition_guid','page_guid','created_by_user_guid','updated_by_user_guid']);$r['authors']=self::authorsForArticle((string)$r['article_guid']);$r['topics']=self::topicsForArticle((string)$r['article_guid']);$r['entities']=self::entitiesForArticle((string)$r['article_guid']);return$r;
        });
        return is_array($row)?$row:null;
    }

    public static function authorBySlug(string $slug): ?array
    {
        $slug=self::slug($slug);if($slug==='')return null;$rev=RuntimeCache::generation(RequestContext::tenantGuid(),'seo');
        $row=MultiLayerCache::remember('seo:author:r'.$rev.':'.hash('sha256',$slug),600,7200,function()use($slug){$r=Store::one('SELECT * FROM ep_authors WHERE tenant_guid=? AND slug=? AND status="ACTIVE" LIMIT 1',[TenantData::tenantBin(),$slug]);return $r?TenantData::guidRow($r,['author_guid']):null;});
        return is_array($row)?$row:null;
    }

    public static function topicBySlug(string $slug): ?array
    {
        $slug=self::slug($slug);if($slug==='')return null;$rev=RuntimeCache::generation(RequestContext::tenantGuid(),'seo');
        $row=MultiLayerCache::remember('seo:topic:r'.$rev.':'.hash('sha256',$slug),600,7200,function()use($slug){$r=Store::one('SELECT t.*,p.name publication_name,p.slug publication_slug FROM ep_topics t LEFT JOIN ep_publications p ON p.tenant_guid=t.tenant_guid AND p.publication_guid=t.publication_guid WHERE t.tenant_guid=? AND t.slug=? AND t.status="ACTIVE" LIMIT 1',[TenantData::tenantBin(),$slug]);return $r?TenantData::guidRow($r,['topic_guid','publication_guid']):null;});
        return is_array($row)?$row:null;
    }

    public static function entityBySlug(string $slug): ?array
    {
        $slug=self::slug($slug);if($slug==='')return null;$rev=RuntimeCache::generation(RequestContext::tenantGuid(),'seo');
        $row=MultiLayerCache::remember('seo:entity:r'.$rev.':'.hash('sha256',$slug),600,7200,function()use($slug){$r=Store::one('SELECT * FROM ep_entities WHERE tenant_guid=? AND slug=? AND status="ACTIVE" LIMIT 1',[TenantData::tenantBin(),$slug]);return $r?TenantData::guidRow($r,['entity_guid']):null;});
        return is_array($row)?$row:null;
    }

    public static function articlesForAuthor(string $authorGuid,int $limit=30): array
    {
        $rows=Store::all('SELECT a.article_guid,a.title,a.slug,a.deck,a.published_at,a.updated_at,p.name publication_name,l.code language_code,l.locale language_locale FROM ep_article_authors aa JOIN ep_articles a ON a.tenant_guid=aa.tenant_guid AND a.article_guid=aa.article_guid JOIN ep_publications p ON p.tenant_guid=a.tenant_guid AND p.publication_guid=a.publication_guid LEFT JOIN ep_languages l ON l.tenant_guid=a.tenant_guid AND l.language_guid=a.language_guid WHERE aa.tenant_guid=? AND aa.author_guid=? AND a.status="PUBLISHED" ORDER BY a.published_at DESC LIMIT '.max(1,min(100,$limit)),[TenantData::tenantBin(),Uuid::toBinary($authorGuid)]);return TenantData::toGuidRows($rows,['article_guid']);
    }

    public static function articlesForTopic(string $topicGuid,int $limit=50): array
    {
        $rows=Store::all('SELECT a.article_guid,a.title,a.slug,a.deck,a.published_at,a.updated_at,p.name publication_name,l.code language_code,l.locale language_locale FROM ep_article_topics atp JOIN ep_articles a ON a.tenant_guid=atp.tenant_guid AND a.article_guid=atp.article_guid JOIN ep_publications p ON p.tenant_guid=a.tenant_guid AND p.publication_guid=a.publication_guid LEFT JOIN ep_languages l ON l.tenant_guid=a.tenant_guid AND l.language_guid=a.language_guid WHERE atp.tenant_guid=? AND atp.topic_guid=? AND a.status="PUBLISHED" ORDER BY a.published_at DESC LIMIT '.max(1,min(100,$limit)),[TenantData::tenantBin(),Uuid::toBinary($topicGuid)]);return TenantData::toGuidRows($rows,['article_guid']);
    }

    public static function articlesForEntity(string $entityGuid,int $limit=50): array
    {
        $rows=Store::all('SELECT a.article_guid,a.title,a.slug,a.deck,a.published_at,a.updated_at,p.name publication_name,l.code language_code,l.locale language_locale FROM ep_article_entities ae JOIN ep_articles a ON a.tenant_guid=ae.tenant_guid AND a.article_guid=ae.article_guid JOIN ep_publications p ON p.tenant_guid=a.tenant_guid AND p.publication_guid=a.publication_guid LEFT JOIN ep_languages l ON l.tenant_guid=a.tenant_guid AND l.language_guid=a.language_guid WHERE ae.tenant_guid=? AND ae.entity_guid=? AND a.status="PUBLISHED" ORDER BY a.published_at DESC LIMIT '.max(1,min(100,$limit)),[TenantData::tenantBin(),Uuid::toBinary($entityGuid)]);return TenantData::toGuidRows($rows,['article_guid']);
    }

    public static function recentArticles(int $limit=30): array
    {
        $rev=RuntimeCache::generation(RequestContext::tenantGuid(),'seo');
        $rows=MultiLayerCache::remember('seo:recent:r'.$rev.':'.max(1,min(100,$limit)),300,1800,function()use($limit){return TenantData::toGuidRows(Store::all('SELECT a.article_guid,a.title,a.slug,a.deck,a.primary_image_url,a.published_at,a.updated_at,p.name publication_name,l.code language_code,l.locale language_locale FROM ep_articles a JOIN ep_publications p ON p.tenant_guid=a.tenant_guid AND p.publication_guid=a.publication_guid LEFT JOIN ep_languages l ON l.tenant_guid=a.tenant_guid AND l.language_guid=a.language_guid WHERE a.tenant_guid=? AND a.status="PUBLISHED" ORDER BY a.published_at DESC LIMIT '.max(1,min(100,$limit)),[TenantData::tenantBin()]),['article_guid']);});
        return is_array($rows)?$rows:[];
    }

    /**
     * Bounded newsroom list projection: article bodies are LONGTEXT and belong
     * only in the single-article edit query, never the listing result.
     */
    public static function adminArticles(int $limit=150): array
    {
        return TenantData::toGuidRows(Store::all('SELECT a.article_guid,a.publication_guid,a.city_guid,a.language_guid,a.section_guid,
            a.edition_guid,a.page_guid,a.created_by_user_guid,a.updated_by_user_guid,
            a.title,a.slug,a.status,a.published_at,a.created_at,
            p.name publication_name,l.name language_name,e.edition_date,pg.page_number,(SELECT GROUP_CONCAT(au.name ORDER BY aa.sort_order SEPARATOR ", ") FROM ep_article_authors aa JOIN ep_authors au ON au.tenant_guid=aa.tenant_guid AND au.author_guid=aa.author_guid WHERE aa.tenant_guid=a.tenant_guid AND aa.article_guid=a.article_guid) author_names FROM ep_articles a JOIN ep_publications p ON p.tenant_guid=a.tenant_guid AND p.publication_guid=a.publication_guid LEFT JOIN ep_languages l ON l.tenant_guid=a.tenant_guid AND l.language_guid=a.language_guid LEFT JOIN ep_editions e ON e.tenant_guid=a.tenant_guid AND e.edition_guid=a.edition_guid LEFT JOIN ep_pages pg ON pg.tenant_guid=a.tenant_guid AND pg.page_guid=a.page_guid WHERE a.tenant_guid=? ORDER BY COALESCE(a.published_at,a.created_at) DESC LIMIT '.max(1,min(300,$limit)),[TenantData::tenantBin()]),['article_guid','publication_guid','city_guid','language_guid','section_guid','edition_guid','page_guid','created_by_user_guid','updated_by_user_guid']);
    }

    public static function adminAuthors(): array{return TenantData::toGuidRows(Store::all('SELECT * FROM ep_authors WHERE tenant_guid=? ORDER BY status DESC,name',[TenantData::tenantBin()]),['author_guid']);}
    public static function adminTopics(): array{return TenantData::toGuidRows(Store::all('SELECT t.*,p.name publication_name FROM ep_topics t LEFT JOIN ep_publications p ON p.tenant_guid=t.tenant_guid AND p.publication_guid=t.publication_guid WHERE t.tenant_guid=? ORDER BY status DESC,name',[TenantData::tenantBin()]),['topic_guid','publication_guid']);}
    public static function adminEntities(): array{return TenantData::toGuidRows(Store::all('SELECT * FROM ep_entities WHERE tenant_guid=? ORDER BY status DESC,name',[TenantData::tenantBin()]),['entity_guid']);}

    public static function authorsForArticle(string $articleGuid): array
    {
        $rows=Store::all('SELECT au.* FROM ep_article_authors aa JOIN ep_authors au ON au.tenant_guid=aa.tenant_guid AND au.author_guid=aa.author_guid WHERE aa.tenant_guid=? AND aa.article_guid=? AND au.status="ACTIVE" ORDER BY aa.sort_order,au.name',[TenantData::tenantBin(),Uuid::toBinary($articleGuid)]);return TenantData::toGuidRows($rows,['author_guid']);
    }
    public static function topicsForArticle(string $articleGuid): array
    {
        $rows=Store::all('SELECT t.* FROM ep_article_topics atp JOIN ep_topics t ON t.tenant_guid=atp.tenant_guid AND t.topic_guid=atp.topic_guid WHERE atp.tenant_guid=? AND atp.article_guid=? AND t.status="ACTIVE" ORDER BY t.name',[TenantData::tenantBin(),Uuid::toBinary($articleGuid)]);return TenantData::toGuidRows($rows,['topic_guid','publication_guid']);
    }
    public static function entitiesForArticle(string $articleGuid): array
    {
        $rows=Store::all('SELECT en.* FROM ep_article_entities ae JOIN ep_entities en ON en.tenant_guid=ae.tenant_guid AND en.entity_guid=ae.entity_guid WHERE ae.tenant_guid=? AND ae.article_guid=? AND en.status="ACTIVE" ORDER BY en.name',[TenantData::tenantBin(),Uuid::toBinary($articleGuid)]);return TenantData::toGuidRows($rows,['entity_guid']);
    }

    public static function translationAlternates(array $article): array
    {
        $key=trim((string)($article['translation_key']??''));if($key==='')return[];$rows=Store::all('SELECT a.slug,l.code,l.locale FROM ep_articles a LEFT JOIN ep_languages l ON l.tenant_guid=a.tenant_guid AND l.language_guid=a.language_guid WHERE a.tenant_guid=? AND a.translation_key=? AND a.status="PUBLISHED" ORDER BY a.published_at',[TenantData::tenantBin(),$key]);$out=[];foreach($rows as$r){$lang=self::locale((string)($r['locale']??''),(string)($r['code']??''));if($lang==='')continue;$out[]=['hreflang'=>$lang,'href'=>Http::origin().'/article/'.rawurlencode((string)$r['slug'])];}if(count($out)>1)$out[]=['hreflang'=>'x-default','href'=>$out[0]['href']];return$out;
    }

    public static function organization(): array
    {
        $name=trim((string)TenantData::setting('seo.organization_name',''));if($name==='')$name=(string)TenantData::setting('brand.site_name',RequestContext::get('tenant_name','ePaper SaaS'));
        $description=trim((string)TenantData::setting('seo.organization_description',TenantData::setting('seo.description','')));$logo=trim((string)TenantData::setting('seo.organization_logo',TenantData::setting('brand.logo_path','')));$same=self::lines((string)TenantData::setting('seo.same_as',''));
        $o=['@context'=>'https://schema.org','@type'=>'Organization','@id'=>Http::origin().'/#organization','name'=>$name,'url'=>Http::origin().'/'];if($description!=='')$o['description']=$description;if($logo!=='')$o['logo']=self::absolute($logo);if($same)$o['sameAs']=$same;
        $email=trim((string)TenantData::setting('seo.organization_email',''));$phone=trim((string)TenantData::setting('seo.organization_phone',''));if($email!=='')$o['email']=$email;if($phone!=='')$o['telephone']=$phone;
        $street=trim((string)TenantData::setting('seo.organization_street',''));$locality=trim((string)TenantData::setting('seo.organization_locality',''));$region=trim((string)TenantData::setting('seo.organization_region',''));$postal=trim((string)TenantData::setting('seo.organization_postal_code',''));$country=trim((string)TenantData::setting('seo.organization_country','IN'));
        if($street!==''||$locality!==''||$region!==''||$postal!==''){$addr=['@type'=>'PostalAddress'];if($street!=='')$addr['streetAddress']=$street;if($locality!=='')$addr['addressLocality']=$locality;if($region!=='')$addr['addressRegion']=$region;if($postal!=='')$addr['postalCode']=$postal;if($country!=='')$addr['addressCountry']=$country;$o['address']=$addr;}
        return$o;
    }

    public static function articleStructuredData(array $a): array
    {
        $url=Http::origin().'/article/'.rawurlencode((string)$a['slug']);$node=['@context'=>'https://schema.org','@type'=>'NewsArticle','@id'=>$url.'#article','mainEntityOfPage'=>['@type'=>'WebPage','@id'=>$url],'headline'=>(string)$a['title'],'datePublished'=>self::iso((string)($a['published_at']??'')),'dateModified'=>self::iso((string)($a['updated_at']??$a['published_at']??'')),'publisher'=>self::organization(),'isAccessibleForFree'=>true];
        $lang=self::locale((string)($a['language_locale']??''),(string)($a['language_code']??''));if($lang!=='')$node['inLanguage']=$lang;if(!empty($a['section_name']))$node['articleSection']=(string)$a['section_name'];if(!empty($a['deck']))$node['description']=(string)$a['deck'];
        $authors=[];foreach(($a['authors']??[])as$au){$n=['@type'=>'Person','name'=>(string)$au['name'],'url'=>Http::origin().'/author/'.rawurlencode((string)$au['slug'])];if(!empty($au['job_title']))$n['jobTitle']=(string)$au['job_title'];$authors[]=$n;}if(!$authors)$authors[]=['@type'=>'Organization','name'=>(string)($a['publication_name']??self::organization()['name']),'url'=>Http::origin().'/'];$node['author']=$authors;
        $img=trim((string)($a['primary_image_url']??''));if($img!=='')$node['image']=[self::absolute($img)];
        $topics=[];foreach(($a['topics']??[])as$t)$topics[]=(string)$t['name'];if($topics)$node['keywords']=implode(', ',$topics);
        return$node;
    }

    public static function authorStructuredData(array $author): array
    {
        $url=Http::origin().'/author/'.rawurlencode((string)$author['slug']);$person=['@type'=>'Person','@id'=>$url.'#person','name'=>(string)$author['name'],'url'=>$url];if(!empty($author['job_title']))$person['jobTitle']=(string)$author['job_title'];if(!empty($author['bio']))$person['description']=(string)$author['bio'];if(!empty($author['photo_url']))$person['image']=self::absolute((string)$author['photo_url']);$same=self::jsonList($author['same_as_json']??null);if($same)$person['sameAs']=$same;return['@context'=>'https://schema.org','@type'=>'ProfilePage','@id'=>$url.'#profile','url'=>$url,'mainEntity'=>$person];
    }

    public static function topicStructuredData(array $topic,array $articles): array
    {
        $url=Http::origin().'/topic/'.rawurlencode((string)$topic['slug']);$items=[];foreach($articles as$i=>$a)$items[]=['@type'=>'ListItem','position'=>$i+1,'url'=>Http::origin().'/article/'.rawurlencode((string)$a['slug']),'name'=>(string)$a['title']];$node=['@context'=>'https://schema.org','@type'=>'CollectionPage','@id'=>$url.'#collection','url'=>$url,'name'=>(string)$topic['name']];if(!empty($topic['description']))$node['description']=(string)$topic['description'];if($items)$node['mainEntity']=['@type'=>'ItemList','itemListElement'=>$items];return$node;
    }

    public static function entityStructuredData(array $entity): array
    {
        $url=Http::origin().'/entity/'.rawurlencode((string)$entity['slug']);$map=['PERSON'=>'Person','ORGANIZATION'=>'Organization','PLACE'=>'Place','EVENT'=>'Event'];$type=$map[strtoupper((string)($entity['entity_type']??''))]??'Thing';$node=['@context'=>'https://schema.org','@type'=>$type,'@id'=>$url.'#entity','name'=>(string)$entity['name'],'url'=>$url];if(!empty($entity['description']))$node['description']=(string)$entity['description'];$same=self::jsonList($entity['same_as_json']??null);if($same)$node['sameAs']=$same;return$node;
    }

    public static function sourceOcr(string $pageGuid): ?array
    {
        $r=Store::one('SELECT pg.page_guid,pg.page_number,o.text_content,e.edition_guid,e.edition_date,e.slug edition_slug,e.publication_guid,e.city_guid,e.language_guid,pg.section_guid,p.name publication_name FROM ep_pages pg JOIN ep_editions e ON e.tenant_guid=pg.tenant_guid AND e.edition_guid=pg.edition_guid JOIN ep_publications p ON p.tenant_guid=e.tenant_guid AND p.publication_guid=e.publication_guid LEFT JOIN ep_page_ocr o ON o.tenant_guid=pg.tenant_guid AND o.page_guid=pg.page_guid WHERE pg.tenant_guid=? AND pg.page_guid=? LIMIT 1',[TenantData::tenantBin(),Uuid::toBinary($pageGuid)]);return $r?TenantData::guidRow($r,['page_guid','edition_guid','publication_guid','city_guid','language_guid','section_guid']):null;
    }

    public static function recentOcrPages(int $limit=80): array
    {
        return TenantData::toGuidRows(Store::all('SELECT pg.page_guid,pg.page_number,e.edition_guid,e.edition_date,e.title edition_title,p.name publication_name,LEFT(o.text_content,120) ocr_preview FROM ep_pages pg JOIN ep_editions e ON e.tenant_guid=pg.tenant_guid AND e.edition_guid=pg.edition_guid JOIN ep_publications p ON p.tenant_guid=e.tenant_guid AND p.publication_guid=e.publication_guid JOIN ep_page_ocr o ON o.tenant_guid=pg.tenant_guid AND o.page_guid=pg.page_guid WHERE pg.tenant_guid=? AND LENGTH(TRIM(o.text_content))>20 ORDER BY e.edition_date DESC,pg.page_number LIMIT '.max(1,min(200,$limit)),[TenantData::tenantBin()]),['page_guid','edition_guid']);
    }

    public static function syncLinks(string $articleGuid,array $authors,array $topics,array $entities): void
    {
        $tb=TenantData::tenantBin();$ab=Uuid::toBinary($articleGuid);
        $authors=self::tenantOwnedGuids('ep_authors','author_guid',$authors);
        $topics=self::tenantOwnedGuids('ep_topics','topic_guid',$topics);
        $entities=self::tenantOwnedGuids('ep_entities','entity_guid',$entities);
        Store::exec('DELETE FROM ep_article_authors WHERE tenant_guid=? AND article_guid=?',[$tb,$ab]);
        Store::exec('DELETE FROM ep_article_topics WHERE tenant_guid=? AND article_guid=?',[$tb,$ab]);
        Store::exec('DELETE FROM ep_article_entities WHERE tenant_guid=? AND article_guid=?',[$tb,$ab]);
        $i=0;foreach($authors as$g)Store::exec('INSERT INTO ep_article_authors(tenant_guid,article_guid,author_guid,sort_order) VALUES(?,?,?,?)',[$tb,$ab,Uuid::toBinary($g),$i++]);
        foreach($topics as$g)Store::exec('INSERT INTO ep_article_topics(tenant_guid,article_guid,topic_guid) VALUES(?,?,?)',[$tb,$ab,Uuid::toBinary($g)]);
        foreach($entities as$g)Store::exec('INSERT INTO ep_article_entities(tenant_guid,article_guid,entity_guid) VALUES(?,?,?)',[$tb,$ab,Uuid::toBinary($g)]);
    }

    private static function tenantOwnedGuids(string $table,string $column,array $values): array
    {
        $allowed=['ep_authors'=>'author_guid','ep_topics'=>'topic_guid','ep_entities'=>'entity_guid'];
        if(($allowed[$table]??null)!==$column)return[];
        $out=[];foreach(array_values(array_unique(array_map('strval',$values)))as$g){
            if(!Uuid::isValid($g))continue;
            $exists=(int)Store::value('SELECT COUNT(*) FROM '.$table.' WHERE tenant_guid=? AND '.$column.'=?',[TenantData::tenantBin(),Uuid::toBinary($g)]);
            if($exists>0)$out[]=$g;
        }
        return$out;
    }

    public static function invalidate(): void{RuntimeCache::broadcast(RequestContext::tenantGuid(),['seo']);RuntimeCache::queueWarm(RequestContext::tenantGuid(),null,['seo'=>true]);}

    public static function slug(string $v): string{$v=trim(mb_strtolower($v));$v=preg_replace('/[^\pL\pN]+/u','-',$v);$v=trim((string)$v,'-');return substr($v,0,220);}
    public static function locale(string $locale,string $code): string{$v=trim($locale!==''?$locale:$code);if($v==='')return'';$v=str_replace('_','-',$v);return $v;}
    public static function lines(string $value): array{$out=[];foreach(preg_split('/\R+/',$value)?:[]as$v){$v=trim($v);if($v!==''&&preg_match('#^https?://#i',$v))$out[]=$v;}return array_values(array_unique($out));}
    private static function absolute(string $url): string{$url=trim($url);if($url==='')return'';return preg_match('#^https?://#i',$url)?$url:Http::origin().'/'.ltrim($url,'/');}
    private static function iso(string $v): string{if($v==='')return'';$t=strtotime($v);return$t?gmdate('c',$t):$v;}
    private static function jsonList(mixed $raw): array{if(is_array($raw))$v=$raw;else{$v=json_decode((string)$raw,true);if(!is_array($v))$v=self::lines((string)$raw);}return array_values(array_filter(array_map('strval',$v),fn($x)=>$x!==''));}
}
