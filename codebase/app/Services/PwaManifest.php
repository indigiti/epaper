<?php
declare(strict_types=1);
namespace EpaperSaas\Services;
use EpaperSaas\Core\RequestContext;

/** Host-resolved white-label web app identity. No query-param tenant selection. */
final class PwaManifest
{
    private static function clip(mixed $value,int $max):string
    {
        $text=trim(strip_tags((string)$value));
        $text=(string)(preg_replace('/[\x00-\x1f\x7f]/u',' ',$text)??'');
        if(function_exists('mb_substr'))return mb_substr($text,0,$max,'UTF-8');
        return implode('',array_slice(preg_split('//u',$text,-1,PREG_SPLIT_NO_EMPTY)?:[],0,$max));
    }
    public static function name(array $settings,string $tenantName):string
    {
        $appName=self::clip($settings['brand.pwa_name']??'',60);
        return BrandIdentity::generic($appName)
            ? self::clip(BrandIdentity::websiteName($settings,$tenantName),60)
            : $appName;
    }
    private static function markIcon(string $tenant,string $file,string $webRoot):string
    {
        $url='/uploads/branding/'.strtolower($tenant).'/'.$file;
        $path=rtrim($webRoot,'/').$url;
        return is_file($path)?$url.'?v='.(string)(filemtime($path)?:0):'';
    }
    /** Identify which logo source actually serves valid tenant-owned PWA icons. */
    public static function source(array $s,string $tenant,string $webRoot):string
    {
        $logo=trim((string)($s['brand.pwa_logo_path']??''));
        $prefix=substr($logo,0,-strlen('512.png'));
        if(PwaIconAssets::validate($logo,$tenant,'512',$webRoot)
            && PwaIconAssets::validate($prefix.'192.png',$tenant,'192',$webRoot)
            && $prefix.'192.png'===($s['brand.pwa_icon_192_path']??'')
            && $logo===($s['brand.pwa_icon_512_path']??''))return 'CUSTOM';
        if(self::markIcon($tenant,'pwa-192.png',$webRoot)!==''
            &&self::markIcon($tenant,'pwa-512.png',$webRoot)!=='')return 'MARK';
        return 'DEFAULT';
    }
    public static function build(array $s,string $tenantName,string $tenant,string $webRoot):array
    {
        $name=self::name($s,$tenantName);
        $short=self::clip($s['brand.pwa_short_name']??'',18)?:self::clip($name,18);
        $description=self::clip($s['brand.pwa_description']??'',180)?:'Read the latest newspaper editions, browse the archive and save articles.';
        $accent=trim((string)($s['brand.accent']??'#2563eb'));
        if(!preg_match('/^#[0-9a-f]{6}$/iD',$accent))$accent='#2563eb';
        $source=self::source($s,$tenant,$webRoot);
        $icon192='/assets/icon-192.png';$icon512='/assets/icon-512.png';$mask='';
        if($source==='CUSTOM'){
            $logo=(string)$s['brand.pwa_logo_path'];
            $prefix=substr($logo,0,-strlen('512.png'));
            $icon192=$prefix.'192.png';$icon512=$logo;
            $mask=PwaIconAssets::validate($prefix.'maskable-512.png',$tenant,'maskable-512',$webRoot)
                ?$prefix.'maskable-512.png':'';
        }elseif($source==='MARK'){
            $icon192=self::markIcon($tenant,'pwa-192.png',$webRoot);
            $icon512=self::markIcon($tenant,'pwa-512.png',$webRoot);
            $mask=self::markIcon($tenant,'pwa-maskable-512.png',$webRoot);
        }
        $icons=[
            ['src'=>$icon192,'sizes'=>'192x192','type'=>'image/png','purpose'=>'any'],
            ['src'=>$icon512,'sizes'=>'512x512','type'=>'image/png','purpose'=>'any']
        ];
        if($mask!=='')$icons[]=['src'=>$mask,'sizes'=>'512x512','type'=>'image/png','purpose'=>'maskable'];
        return [
          'id'=>'/','name'=>$name,'short_name'=>$short,'description'=>$description,
          'start_url'=>'/','scope'=>'/','display'=>'standalone','orientation'=>'any',
          'background_color'=>'#f5f5f7','theme_color'=>$accent,
          'categories'=>['news','magazines','productivity'],
          'shortcuts'=>[
            ['name'=>"Today's ePaper",'short_name'=>'Today','url'=>'/'],
            ['name'=>'Archive','short_name'=>'Archive','url'=>'/archive'],
            ['name'=>'Library','short_name'=>'Library','url'=>'/account/library'],
            ['name'=>'Offline Vault','short_name'=>'Offline','url'=>'/offline-library.html']
          ],
          'icons'=>$icons,
          'display_override'=>['window-controls-overlay','standalone','minimal-ui']
        ];
    }
    public static function revision(array $s,string $tenantName,string $tenant):string
    {
        $keys=['brand.site_name','brand.pwa_name','brand.pwa_short_name','brand.pwa_description',
             'brand.accent','brand.pwa_logo_path','brand.pwa_icon_192_path','brand.pwa_icon_512_path','brand.pwa_maskable_512_path'];
        $values=[$tenant,$tenantName];
        foreach($keys as $key)$values[]=$s[$key]??null;
        return substr(hash('sha256',(string)json_encode($values,JSON_UNESCAPED_UNICODE)),0,16);
    }
    public static function send():never
    {
        $tenant=RequestContext::tenantGuid(); // Only after verified DomainResolver binding
        $json=self::build(TenantData::scopedSettings(),(string)RequestContext::get('tenant_name',''),
          $tenant,dirname(__DIR__,2).'/public_html');
        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Surrogate-Control: no-store');
        header('Vary: Host');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($json,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP
          |JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR);
        exit;
    }
}
