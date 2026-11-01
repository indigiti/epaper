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
    /**
     * Admin input may be a bare hostname or an http(s) URL copied from a browser.
     * Accept only the hostname; reject paths, ports, credentials and private IPs.
     */
    public static function publicHostname(string $input): string
    {
        $raw=trim($input);
        if($raw==='' || strlen($raw)>2048) throw new \InvalidArgumentException('Enter a public newspaper domain such as epaper.example.com.');
        if(str_contains($raw,'://')) {
            $url=parse_url($raw);
            if(!is_array($url)||!in_array(strtolower((string)($url['scheme']??'')),['http','https'],true)
                ||empty($url['host'])||isset($url['user'])||isset($url['pass'])
                ||isset($url['port'])||isset($url['query'])||isset($url['fragment'])
                ||!in_array((string)($url['path']??''),['','/'],true)) {
                throw new \InvalidArgumentException('Use only the newspaper website address, without page paths or query parameters.');
            }
            $raw=(string)$url['host'];
        }
        $host=self::normalize($raw);
        if(!str_contains($host,'.')||filter_var($host,FILTER_VALIDATE_IP)
            ||str_ends_with($host,'.local')||str_ends_with($host,'.localhost')
            ||str_ends_with($host,'.internal')||str_ends_with($host,'.test')) {
            throw new \InvalidArgumentException('Enter a public domain name (for example, epaper.example.com).');
        }
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
