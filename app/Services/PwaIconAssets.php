<?php
declare(strict_types=1);
namespace EpaperSaas\Services;

use EpaperSaas\Core\Uuid;

/** Dedicated publisher PWA icon derivatives; isolated from website/mark assets. */
final class PwaIconAssets
{
    public const PREFIX = 'pwa-custom-';

    /** Generate four immutable, content-addressed icons. Never overwrite Square brand mark assets. */
    public static function generate(string $uploadedFile, string $tenantGuid, string $webRoot): array
    {
        if (!Uuid::isValid($tenantGuid) || !is_file($uploadedFile)) {
            throw new \RuntimeException('Invalid publisher icon upload');
        }
        $size = filesize($uploadedFile);
        if ($size === false || $size < 1 || $size > 8 * 1024 * 1024) {
            throw new \RuntimeException('PWA logo must be under 8 MB');
        }
        $info = @getimagesize($uploadedFile);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($uploadedFile);
        if (!is_array($info) || !in_array($mime, ['image/png','image/jpeg','image/webp'], true)) {
            throw new \RuntimeException('Upload a PNG, JPEG or WebP PWA icon');
        }
        $width = (int)($info[0] ?? 0);$height = (int)($info[1] ?? 0);
        if (min($width, $height) < 512 || max($width, $height) > 4096 || $width * $height > 12_000_000) {
            throw new \RuntimeException('PWA icon requires at least 512 pixels on each side (maximum 12 megapixels)');
        }
        if (!class_exists('Imagick') && !function_exists('imagecreatefromstring')) {
            throw new \RuntimeException('Install Imagick or PHP GD to generate valid PWA icons');
        }
        $digest = substr(hash_file('sha256', $uploadedFile), 0, 16);
        $tenant = strtolower($tenantGuid);
        $directory = rtrim($webRoot, '/').'/uploads/branding/'.$tenant;
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Publisher branding storage is unavailable');
        }
        $prefix = self::PREFIX.$digest.'-';
        $variants = ['192' => 192, '512' => 512, 'maskable-512' => 512, 'apple-touch' => 180];
        $temps = [];
        try {
            if (class_exists('Imagick')) {
                $source = new \Imagick($uploadedFile);
                $source->setIteratorIndex(0);
                if (method_exists($source,'autoOrient')) $source->autoOrient();
                $source->setImageColorspace(\Imagick::COLORSPACE_SRGB);
                foreach ($variants as $variant=>$canvasSize) {
                    $image = self::imagickCanvas($source, $canvasSize, $variant === 'maskable-512');
                    $image->setImageFormat('png');$image->stripImage();
                    $temporary = $directory.'/.'.$prefix.$variant.'.'.bin2hex(random_bytes(5)).'.tmp';
                    if (!$image->writeImage($temporary)) throw new \RuntimeException('Could not encode app icon');
                    $image->clear();$temps[$variant] = $temporary;
                }
                $source->clear();
            } else {
                $bytes = file_get_contents($uploadedFile);
                $source = $bytes === false ? false : @imagecreatefromstring($bytes);
                if (!$source) throw new \RuntimeException('Uploaded image could not be decoded');
                try {
                    foreach ($variants as $variant=>$canvasSize) {
                        $temporary = $directory.'/.'.$prefix.$variant.'.'.bin2hex(random_bytes(5)).'.tmp';
                        if (!self::gdCanvas($source, $canvasSize, $variant === 'maskable-512', $temporary))
                            throw new \RuntimeException('Could not encode app icon');
                        $temps[$variant] = $temporary;
                    }
                } finally { imagedestroy($source); }
            }
            foreach ($variants as $variant=>$canvasSize) {
                $target = $directory.'/'.$prefix.$variant.'.png';
                if (!@rename($temps[$variant], $target))
                    throw new \RuntimeException('Could not save generated app icon');
                unset($temps[$variant]);
            }
        } finally {foreach ($temps as $temp) @unlink($temp);}
        $base = '/uploads/branding/'.$tenant.'/'.$prefix;
        return [
            'brand.pwa_logo_path' => $base.'512.png',
            'brand.pwa_icon_192_path' => $base.'192.png',
            'brand.pwa_icon_512_path' => $base.'512.png',
            'brand.pwa_maskable_512_path' => $base.'maskable-512.png',
            'brand.apple_touch_icon_path' => $base.'apple-touch.png',
        ];
    }

    private static function imagickCanvas(\Imagick $source, int $size, bool $maskable): \Imagick
    {
        $inner = $maskable ? 320 : $size;
        $image = clone $source;
        $image->thumbnailImage($inner, $inner, true, true);
        $canvas = new \Imagick();
        $canvas->newImage($size, $size, new \ImagickPixel($maskable ? '#f5f5f7' : 'transparent'), 'png');
        $canvas->compositeImage($image, \Imagick::COMPOSITE_OVER,
            (int)(($size - $image->getImageWidth()) / 2),
            (int)(($size - $image->getImageHeight()) / 2));
        $image->clear();
        return $canvas;
    }

    private static function gdCanvas(\GdImage $source, int $size, bool $maskable, string $path): bool
    {
        $canvas = imagecreatetruecolor($size, $size);
        if (!$canvas) return false;
        try {
            imagealphablending($canvas, false);imagesavealpha($canvas, true);
            $color = $maskable ? imagecolorallocate($canvas, 245, 245, 247)
                : imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $size, $size, $color);
            $width = imagesx($source);$height = imagesy($source);
            $max = $maskable ? 320 : $size;
            $scale = min($max / max(1, $width), $max / max(1, $height));
            $destWidth = max(1, (int)round($width * $scale));
            $destHeight = max(1, (int)round($height * $scale));
            imagealphablending($canvas, true);
            imagecopyresampled($canvas, $source, (int)(($size-$destWidth)/2), (int)(($size-$destHeight)/2),
                0, 0, $destWidth, $destHeight, $width, $height);
            return imagepng($canvas, $path, 7);
        } finally { imagedestroy($canvas); }
    }

    public static function validate(string $url, string $tenant, string $variant, string $webRoot): bool
    {
        if (!in_array($variant, ['192','512','maskable-512','apple-touch'], true) || !Uuid::isValid($tenant)) return false;
        $regex = '~^/uploads/branding/'.preg_quote(strtolower($tenant),'~').'/pwa-custom-[0-9a-f]{16}-'.preg_quote($variant,'~').'\.png$~D';
        if (!preg_match($regex, $url)) return false;
        return is_file(rtrim($webRoot,'/').$url);
    }
}
