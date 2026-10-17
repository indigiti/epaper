<?php
namespace Digiti\Core;

final class Uuid
{
    public static function v7(): string
    {
        $ms = (int) floor(microtime(true) * 1000);
        $timeHex = str_pad(dechex($ms), 12, '0', STR_PAD_LEFT);
        $rand = random_bytes(10);
        $hex = $timeHex . bin2hex($rand);
        $hex = substr($hex, 0, 12) . '7' . substr($hex, 13);
        $variantNibble = hexdec($hex[16]);
        $variantNibble = ($variantNibble & 0x3) | 0x8;
        $hex[16] = dechex($variantNibble);
        return sprintf('%s-%s-%s-%s-%s', substr($hex,0,8), substr($hex,8,4), substr($hex,12,4), substr($hex,16,4), substr($hex,20,12));
    }

    public static function toBinary(string $uuid): string
    {
        $hex = str_replace('-', '', strtolower(trim($uuid)));
        if (!preg_match('/^[0-9a-f]{32}$/', $hex)) throw new \InvalidArgumentException('Invalid UUID');
        return hex2bin($hex);
    }

    public static function fromBinary(?string $binary): ?string
    {
        if ($binary === null) return null;
        if (strlen($binary) !== 16) return $binary;
        $hex = bin2hex($binary);
        return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20,12);
    }
}
