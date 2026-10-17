<?php
namespace EpaperSaas\Core;
final class Uuid
{
    public static function v7(): string
    {
        $ms=(int)floor(microtime(true)*1000);$timeHex=str_pad(dechex($ms),12,'0',STR_PAD_LEFT);$hex=$timeHex.bin2hex(random_bytes(10));
        $hex=substr($hex,0,12).'7'.substr($hex,13);$n=(hexdec($hex[16])&0x3)|0x8;$hex[16]=dechex($n);
        return sprintf('%s-%s-%s-%s-%s',substr($hex,0,8),substr($hex,8,4),substr($hex,12,4),substr($hex,16,4),substr($hex,20,12));
    }
    public static function isValid(string $uuid): bool{return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[89ab0-9a-f][0-9a-f]{3}-[0-9a-f]{12}$/i',trim($uuid));}
    public static function toBinary(string $uuid): string
    {
        $hex=str_replace('-','',strtolower(trim($uuid)));if(!preg_match('/^[0-9a-f]{32}$/',$hex))throw new \InvalidArgumentException('Invalid UUID');return hex2bin($hex);
    }
    public static function fromBinary(?string $binary): ?string
    {
        if($binary===null)return null;if(strlen($binary)!==16)return $binary;$h=bin2hex($binary);return substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20,12);
    }
}
