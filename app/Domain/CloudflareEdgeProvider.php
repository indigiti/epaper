<?php
namespace Digiti\Domain;
use Digiti\Core\Config;
final class CloudflareEdgeProvider implements EdgeProviderInterface
{
    public function key():string{return'cloudflare';}
    private function request(string $method,string $path,?array $body=null):array
    {
        $token=(string)Config::get('edge.cloudflare_api_token','');$zone=(string)Config::get('edge.cloudflare_zone_id','');if(!$token||!$zone)throw new \RuntimeException('Cloudflare edge provider is not configured.');if(!function_exists('curl_init'))throw new \RuntimeException('cURL extension required.');$url='https://api.cloudflare.com/client/v4/zones/'.$zone.$path;$ch=curl_init($url);$headers=['Authorization: Bearer '.$token,'Content-Type: application/json'];curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers]);if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body));$raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);curl_close($ch);if($raw===false)throw new \RuntimeException('Cloudflare request failed: '.$err);$json=json_decode($raw,true);if($status<200||$status>=300||empty($json['success']))throw new \RuntimeException('Cloudflare API rejected the custom hostname request.');return$json['result']??[];
    }
    public function provision(string $hostname,array $existing=[]):array
    {
        if(!empty($existing['provider_id']))return$this->status($hostname,$existing);$r=$this->request('POST','/custom_hostnames',['hostname'=>$hostname,'ssl'=>['method'=>'txt','type'=>'dv']]);return['provider'=>'cloudflare','provider_id'=>$r['id']??null,'status'=>'SSL_PROVISIONING','hostname'=>$hostname,'edge_status'=>$r['status']??null,'ssl_status'=>$r['ssl']['status']??null,'ownership_verification'=>$r['ownership_verification']??null,'ssl_validation_records'=>$r['ssl']['validation_records']??null];
    }
    public function status(string $hostname,array $metadata=[]):array
    {
        $id=$metadata['provider_id']??null;if(!$id)return$this->provision($hostname,$metadata);$r=$this->request('GET','/custom_hostnames/'.rawurlencode($id));$active=($r['status']??'')==='active'&&in_array(($r['ssl']['status']??''),['active','deployed'],true);return array_merge($metadata,['provider'=>'cloudflare','provider_id'=>$id,'status'=>$active?'ACTIVE':'SSL_PROVISIONING','edge_status'=>$r['status']??null,'ssl_status'=>$r['ssl']['status']??null,'ownership_verification'=>$r['ownership_verification']??($metadata['ownership_verification']??null),'ssl_validation_records'=>$r['ssl']['validation_records']??($metadata['ssl_validation_records']??null)]);
    }
}
