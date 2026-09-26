<?php

declare(strict_types=1);

namespace Maryam;

use InvalidArgumentException;
use RuntimeException;

/**
 * Web ilovada yuklanadigan brend fayllari: logolar va dizayn namunalari (grid skrinshoti,
 * yoqqan postlar). Dizayner agent namunalarni rasm sifatida ko'radi va uslubni ularga moslaydi.
 */
final class BrandAssets
{
    public const MAX_REFS = 12;
    private const REF_MAX_PX = 1400;
    private const AI_MAX_PX = 768;

    public static function dir(): string
    {
        return ROOT . PostRenderer::BRAND_DIR;
    }

    /** Yuklangan rasmni tekshirib, qayta kodlab saqlaydi (faqat haqiqiy rasm, shaffoflik saqlanadi). */
    public static function saveLogo(string $tmpPath, string $which): void
    {
        $im = self::load($tmpPath);
        @mkdir(self::dir(), 0775, true);
        imagesavealpha($im, true);
        imagepng($im, self::dir() . '/' . ($which === 'logo-white' ? 'logo-white.png' : 'logo.png'));
    }

    public static function addRef(string $tmpPath): string
    {
        if (count(self::refs()) >= self::MAX_REFS) {
            throw new InvalidArgumentException('Ko\'pi bilan ' . self::MAX_REFS . " ta namuna. Eskilarini o'chirib, keyin yuklang.");
        }
        $im = self::load($tmpPath);
        @mkdir(self::dir() . '/refs', 0775, true);
        $name = date('Ymd-His') . '-' . bin2hex(random_bytes(3));
        imagejpeg(self::fit($im, self::REF_MAX_PX), self::dir() . "/refs/$name.jpg", 86);
        imagejpeg(self::fit($im, self::AI_MAX_PX), self::dir() . "/refs/$name.ai.jpg", 80);
        return $name;
    }

    /** @return string[] namuna nomlari (eng yangisi birinchi) */
    public static function refs(): array
    {
        $files = glob(self::dir() . '/refs/*.jpg') ?: [];
        $names = array_map(static fn ($f) => basename($f, '.jpg'), array_filter($files, static fn ($f) => !str_ends_with($f, '.ai.jpg')));
        rsort($names);
        return array_values($names);
    }

    public static function refPath(string $name, bool $small = false): ?string
    {
        if (!preg_match('/^[0-9]{8}-[0-9]{6}-[0-9a-f]{6}$/', $name)) {
            return null; // faqat biz yaratgan nomlar — yo'l bilan o'ynab bo'lmaydi
        }
        $path = self::dir() . "/refs/$name" . ($small ? '.ai' : '') . '.jpg';
        return is_file($path) ? $path : null;
    }

    public static function deleteRef(string $name): void
    {
        foreach ([false, true] as $small) {
            if ($p = self::refPath($name, $small)) {
                unlink($p);
            }
        }
    }

    /** Dizayner agentga beriladigan namunalar (kichik nusxalar, base64). */
    public static function refsForAi(int $limit = 4): array
    {
        $out = [];
        foreach (array_slice(self::refs(), 0, $limit) as $name) {
            if ($p = self::refPath($name, true)) {
                $out[] = ['mime' => 'image/jpeg', 'data' => base64_encode((string) file_get_contents($p))];
            }
        }
        return $out;
    }

    private static function load(string $path): \GdImage
    {
        if (!function_exists('imagecreatefromstring')) {
            throw new RuntimeException("Serverda rasm moduli (php-gd) yo'q — o'rnatish skriptini qayta ishga tushiring.");
        }
        $im = @imagecreatefromstring((string) file_get_contents($path));
        if (!$im) {
            throw new InvalidArgumentException('Bu rasm fayli emas. PNG, JPG yoki WEBP yuklang.');
        }
        return $im;
    }

    private static function fit(\GdImage $im, int $max): \GdImage
    {
        $w = imagesx($im);
        $h = imagesy($im);
        $scale = min(1, $max / max($w, $h));
        $out = imagecreatetruecolor((int) round($w * $scale), (int) round($h * $scale));
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255)); // shaffof PNG → oq fon
        imagecopyresampled($out, $im, 0, 0, 0, 0, imagesx($out), imagesy($out), $w, $h);
        return $out;
    }
}
