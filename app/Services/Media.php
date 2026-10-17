<?php
namespace EpaperSaas\Services;
use EpaperSaas\Core\{Config,RequestContext,Store,Uuid};
final class Media
{
    public static function token(string $assetGuid,int $ttl=3600): string
    {
        $bucket=max(60,(int)Config::get('media.token_bucket_seconds',300));$expires=(int)(ceil((time()+max(1,$ttl))/$bucket)*$bucket);$payload=['a'=>$assetGuid,'t'=>RequestContext::tenantGuid(),'e'=>$expires];$raw=rtrim(strtr(base64_encode(json_encode($payload)),'+/','-_'),'=');$sig=rtrim(strtr(base64_encode(hash_hmac('sha256',$raw,(string)Config::get('app.key'),true)),'+/','-_'),'=');return$raw.'.'.$sig;
    }
    public static function verify(string $token): array
    {
        [$raw,$sig]=array_pad(explode('.',$token,2),2,'');$calc=rtrim(strtr(base64_encode(hash_hmac('sha256',$raw,(string)Config::get('app.key'),true)),'+/','-_'),'=');if(!$sig||!hash_equals($calc,$sig))throw new \RuntimeException('Invalid media token');$data=json_decode(base64_decode(strtr($raw,'-_','+/')),true);if(!is_array($data)||($data['e']??0)<time())throw new \RuntimeException('Expired media token');if(($data['t']??'')!==RequestContext::tenantGuid())throw new \RuntimeException('Media tenant mismatch');return$data;
    }
    public static function asset(string $guid): ?array
    {
        if(!Uuid::isValid($guid))return null;
        $key='media-asset:'.strtolower($guid);
        $row=MultiLayerCache::remember($key,21600,86400,function()use($guid){
            $r=Store::one('SELECT asset_guid,page_guid,asset_type,mime_type,storage_path,width,height,byte_size FROM ep_page_assets WHERE tenant_guid=? AND asset_guid=?',[Uuid::toBinary(RequestContext::tenantGuid()),Uuid::toBinary($guid)]);
            if(!$r)return null;foreach(['asset_guid','page_guid']as$k)if(isset($r[$k])&&is_string($r[$k])&&strlen($r[$k])===16)$r[$k]=Uuid::fromBinary($r[$k]);return$r;
        });
        return is_array($row)?$row:null;
    }
    public static function send(string $token): never
    {
        $d=self::verify($token);$a=self::asset($d['a']);if(!$a)throw new \RuntimeException('Media asset not found');$relative=ltrim((string)$a['storage_path'],'/');$path=dirname(__DIR__,2).'/storage/'.$relative;if(!is_file($path))throw new \RuntimeException('Media file missing');header('Content-Type: '.$a['mime_type']);header('Content-Length: '.filesize($path));$remaining=max(0,(int)($d['e']??time())-time());header('Cache-Control: public, max-age='.min($remaining,21600).', immutable');header('Vary: Accept-Encoding');header('Accept-Ranges: bytes');$xAccel=trim((string)Config::get('media.x_accel_prefix',''));if($xAccel!==''){PerformanceTelemetry::mediaDelivery('x-accel');header('X-EPDI-Media-Delivery: x-accel');header('X-Accel-Redirect: '.rtrim($xAccel,'/').'/'.$relative);exit;}PerformanceTelemetry::mediaDelivery('php');header('X-EPDI-Media-Delivery: php');readfile($path);exit;
    }
}
