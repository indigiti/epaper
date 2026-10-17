<?php
namespace Digiti\Core;
final class Mfa
{
    private const ALPH='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    public static function secret(int $bytes=20):string{return self::base32Encode(random_bytes($bytes));}
    public static function uri(string $secret,string $label,string $issuer='Digiti Platform'):string{return'otpauth://totp/'.rawurlencode($issuer.':'.$label).'?'.http_build_query(['secret'=>$secret,'issuer'=>$issuer,'algorithm'=>'SHA1','digits'=>6,'period'=>30]);}
    public static function verify(string $secret,string $code,int $window=1):bool
    {
        $code=preg_replace('/\D/','',$code);if(strlen($code)!==6)return false;$time=(int)floor(time()/30);for($i=-$window;$i<=$window;$i++)if(hash_equals(self::totp($secret,$time+$i),$code))return true;return false;
    }
    private static function totp(string $secret,int $counter):string
    {
        $key=self::base32Decode($secret);$bin=pack('N*',0).pack('N*',$counter);$hash=hash_hmac('sha1',$bin,$key,true);$offset=ord($hash[19])&0xf;$n=((ord($hash[$offset])&0x7f)<<24)|((ord($hash[$offset+1])&0xff)<<16)|((ord($hash[$offset+2])&0xff)<<8)|(ord($hash[$offset+3])&0xff);return str_pad((string)($n%1000000),6,'0',STR_PAD_LEFT);
    }
    private static function base32Encode(string $data):string{$bits='';foreach(str_split($data)as$c)$bits.=str_pad(decbin(ord($c)),8,'0',STR_PAD_LEFT);$out='';foreach(str_split($bits,5)as$chunk){$chunk=str_pad($chunk,5,'0');$out.=self::ALPH[bindec($chunk)];}return$out;}
    private static function base32Decode(string $s):string{$s=strtoupper(preg_replace('/[^A-Z2-7]/','',$s));$bits='';foreach(str_split($s)as$c){$p=strpos(self::ALPH,$c);if($p===false)throw new \InvalidArgumentException('Invalid Base32');$bits.=str_pad(decbin($p),5,'0',STR_PAD_LEFT);}$out='';foreach(str_split($bits,8)as$b)if(strlen($b)===8)$out.=chr(bindec($b));return$out;}
}
