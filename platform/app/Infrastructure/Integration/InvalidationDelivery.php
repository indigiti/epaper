<?php
declare(strict_types=1);
namespace Digiti\Infrastructure\Integration;

use Digiti\Core\{Config,Uuid};

/** Single fixed private-config destination; no tenant-controlled URL or HTTP redirects. */
final class InvalidationDelivery
{
    public static function send(array $payload): void
    {
        $url=(string)Config::get('integrations.epaper_invalidation_url','');
        $secret=(string)Config::get('integrations.epaper_invalidation_secret','');
        $parts=parse_url($url);
        if(!is_array($parts)||($parts['scheme']??'')!=='https'||empty($parts['host'])
            ||($parts['path']??'')!=='/internal/v1/platform/cache-invalidate'||(isset($parts['user'])||isset($parts['pass']))
            ||isset($parts['query'])||isset($parts['fragment']))
            throw new \RuntimeException('Fixed ePaper HTTPS invalidation destination is not configured');
        if(strlen($secret)<32)throw new \RuntimeException('ePaper invalidation signing secret is not configured');
        if(($payload['kind']??null)!=='control_plane.changed'||!Uuid::isValid((string)($payload['event_id']??'')))
            throw new \InvalidArgumentException('Invalid cache event');
        $raw=json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $time=(string)time();
        $sig=hash_hmac('sha256',$time."\n".$raw,$secret);
        if(!function_exists('curl_init'))throw new \RuntimeException('cURL extension required for delivery');
        $curl=curl_init($url);
        curl_setopt_array($curl,[
            CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$raw,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json','X-Digiti-Timestamp: '.$time,'X-Digiti-Signature: v1='.$sig],
            CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_TIMEOUT=>6,
            CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2
        ]);
        $reply=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);
        $error=curl_error($curl);curl_close($curl);
        $response=is_string($reply)?json_decode($reply,true):null;
        if($status!==200||!is_array($response)||($response['ok']??false)!==true
            ||($response['event_id']??null)!==$payload['event_id'])
            throw new \RuntimeException('ePaper invalidation not acknowledged (HTTP '.$status.'): '.substr($error,0,180));
    }
}
