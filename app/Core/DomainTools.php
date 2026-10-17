<?php
namespace Digiti\Core;

final class DomainTools
{
    public static function normalize(string $host): string
    {
        $host=strtolower(trim($host)); $host=preg_replace('/:\d+$/','',$host); $host=rtrim($host,'.');
        if(function_exists('idn_to_ascii')) $host=idn_to_ascii($host,IDNA_DEFAULT,INTL_IDNA_VARIANT_UTS46)?:$host;
        if(strlen($host)>253||!preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/',$host)) throw new \InvalidArgumentException('Invalid hostname');
        return $host;
    }
    public static function verifyDns(string $host,string $token): array
    {
        $target=(string)Config::get('app.domains_target','domains.digiti.in'); $txtOk=false;$cnameOk=false;$txt=[];$cname=[];
        foreach(dns_get_record('_digiti-verification.'.$host,DNS_TXT)?:[] as$r){$v=$r['txt']??'';$txt[]=$v;if(hash_equals($token,$v))$txtOk=true;}
        foreach(dns_get_record($host,DNS_CNAME)?:[] as$r){$v=rtrim(strtolower($r['target']??''),'.');$cname[]=$v;if($v===rtrim(strtolower($target),'.'))$cnameOk=true;}
        return ['verified'=>$txtOk||$cnameOk,'txt_ok'=>$txtOk,'cname_ok'=>$cnameOk,'txt_records'=>$txt,'cname_records'=>$cname,'expected_cname'=>$target,'expected_txt'=>$token];
    }
}
