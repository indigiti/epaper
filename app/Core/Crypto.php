<?php
namespace Digiti\Core;
final class Crypto
{
    private static function key(): string
    {
        $raw=(string)Config::get('app.key','');if(preg_match('/^[0-9a-f]{64}$/i',$raw))return hex2bin($raw);return hash('sha256',$raw,true);
    }
    public static function encrypt(string $plain): string
    {
        $iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($plain,'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,$iv,$tag,'digiti-platform');if($cipher===false)throw new \RuntimeException('Encryption failed');return base64_encode($iv.$tag.$cipher);
    }
    public static function decrypt(string $encoded): string
    {
        $raw=base64_decode($encoded,true);if($raw===false||strlen($raw)<29)throw new \RuntimeException('Invalid encrypted value');$iv=substr($raw,0,12);$tag=substr($raw,12,16);$cipher=substr($raw,28);$plain=openssl_decrypt($cipher,'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,$iv,$tag,'digiti-platform');if($plain===false)throw new \RuntimeException('Decryption failed');return$plain;
    }
}
