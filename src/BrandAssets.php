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
        // Atrofdagi bo'sh shaffof joy kesiladi — aks holda logo rasmda juda kichik chiqadi
        $im = self::trimTransparent($im);
        imagealphablending($im, false);
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

    /**
     * Grid namunalaridan brend ranglarini avtomatik aniqlaydi (qo'lda kiritish shart emas):
     * primary — eng ko'p to'yingan to'q rang (fon/lenta), accent — yorqin, tusi farqli rang (narx),
     * dark — primary'ning to'q varianti. Oq matn o'qilishi uchun primary yetarlicha to'q qilinadi.
     * @return array{primary: string, accent: string, dark: string}|null
     */
    public static function extractPalette(array $paths): ?array
    {
        $buckets = [];
        foreach ($paths as $path) {
            $src = @imagecreatefromstring((string) @file_get_contents($path));
            if (!$src) {
                continue;
            }
            $im = imagecreatetruecolor(60, 75);
            imagecopyresampled($im, $src, 0, 0, 0, 0, 60, 75, imagesx($src), imagesy($src));
            for ($x = 0; $x < 60; $x++) {
                for ($y = 0; $y < 75; $y++) {
                    $c = imagecolorat($im, $x, $y);
                    $rgb = [($c >> 16) & 255, ($c >> 8) & 255, $c & 255];
                    [$h, $sat, $l] = self::hsl(...$rgb);
                    if ($sat < 0.18 || $l < 0.05 || $l > 0.93) {
                        continue; // kulrang, oq, qora — brend rangi emas
                    }
                    $key = intdiv((int) $h, 20) . ':' . min(4, (int) ($l * 5));
                    $b = &$buckets[$key];
                    $b['n'] = ($b['n'] ?? 0) + 1;
                    $b['s'] = ($b['s'] ?? 0) + $sat;
                    foreach ([0, 1, 2] as $i) {
                        $b['c'][$i] = ($b['c'][$i] ?? 0) + $rgb[$i];
                    }
                    unset($b);
                }
            }
        }
        if (!$buckets) {
            return null;
        }
        $cands = [];
        foreach ($buckets as $b) {
            $rgb = array_map(static fn ($v) => (int) round($v / $b['n']), $b['c']);
            [$h, $sat, $l] = self::hsl(...$rgb);
            $cands[] = ['rgb' => $rgb, 'n' => $b['n'], 'h' => $h, 's' => $sat, 'l' => $l];
        }
        $total = array_sum(array_column($cands, 'n'));

        // Fotodagi teri ranglari va dengiz/osmon/Instagram tugmasi ko'ki brend rangi emas — ularga og'irlik kam
        $skin = static fn ($c) => ($c['h'] <= 45 || $c['h'] >= 350) && $c['s'] < 0.66 && $c['l'] > 0.28 && $c['l'] < 0.82;
        $blue = static fn ($c) => $c['h'] >= 185 && $c['h'] <= 235;

        // Primary: ko'p uchraydigan, to'q-o'rta rang (to'q fonlar ustunlik qiladi)
        $pScore = static fn ($c) => $c['n'] * (1.3 - $c['l']) * ($skin($c) ? 0.1 : 1) * ($blue($c) ? 0.4 : 1);
        usort($cands, static fn ($a, $b) => $pScore($b) <=> $pScore($a));
        $primary = $cands[0];
        if ($primary['n'] / $total < 0.08 || $primary['s'] < 0.3) {
            // Gridda bir xil brend foni yo'q (faqat fotolar) — kompaniyaning yashil rangi
            $primary = ['h' => 166.0, 's' => 0.75, 'l' => 0.16, 'n' => 0];
        }
        // Accent: yorqin va to'yingan, tusi primary'dan uzoq; oltin/sariq tuslar afzal (narx plashkasi)
        $accent = null;
        $best = 0.0;
        foreach ($cands as $c) {
            $dh = abs($c['h'] - $primary['h']);
            $dh = min($dh, 360 - $dh);
            if ($dh < 35 || $c['l'] < 0.3 || $c['s'] < 0.3 || $skin($c)) {
                continue;
            }
            $gold = $c['h'] >= 36 && $c['h'] <= 58;
            $score = ($c['n'] / $total) * $c['s'] * ($gold ? 3 : 1) * ($blue($c) ? 0.25 : 1);
            if ($score > $best) {
                [$best, $accent] = [$score, $c];
            }
        }
        // Juda kam uchragan rang tasodifiy (bitta tugma, bitta foto) — unda brendning oltin rangi
        $accentRgb = $accent && $accent['n'] / $total >= ($blue($accent) ? 0.05 : 0.004) ? $accent['rgb'] : [233, 196, 106];
        [$ah, $as, $al] = self::hsl(...$accentRgb);
        $accentRgb = self::fromHsl($ah, max($as, 0.6), min(max($al, 0.6), 0.72)); // to'q fonda yorqin ko'rinsin

        [$ph, $ps] = [$primary['h'], max($primary['s'], 0.35)];
        return [
            'primary' => self::hex(self::fromHsl($ph, $ps, min(max($primary['l'], 0.18), 0.27))),
            'accent' => self::hex($accentRgb),
            'dark' => self::hex(self::fromHsl($ph, $ps, 0.07)),
        ];
    }

    /** Namunalar o'zgarganda ranglarni qayta hisoblab saqlaydi (renderer shularni ishlatadi). */
    public static function refreshPalette(Store $store): void
    {
        $paths = array_filter(array_map(static fn ($n) => self::refPath($n, true), self::refs()));
        $palette = $paths ? self::extractPalette(array_values($paths)) : null;
        $store->setMeta('brand_colors_auto', $palette ? (string) json_encode($palette) : '');
    }

    private static function hsl(int $r, int $g, int $b): array
    {
        [$r, $g, $b] = [$r / 255, $g / 255, $b / 255];
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        if ($max === $min) {
            return [0.0, 0.0, $l];
        }
        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        $h = match ($max) {
            $r => fmod((($g - $b) / $d) + 6, 6),
            $g => (($b - $r) / $d) + 2,
            default => (($r - $g) / $d) + 4,
        } * 60;
        return [$h, $s, $l];
    }

    private static function fromHsl(float $h, float $s, float $l): array
    {
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;
        [$r, $g, $b] = match (true) {
            $h < 60 => [$c, $x, 0], $h < 120 => [$x, $c, 0], $h < 180 => [0, $c, $x],
            $h < 240 => [0, $x, $c], $h < 300 => [$x, 0, $c], default => [$c, 0, $x],
        };
        return array_map(static fn ($v) => (int) round(($v + $m) * 255), [$r, $g, $b]);
    }

    private static function hex(array $rgb): string
    {
        return sprintf('#%02x%02x%02x', ...$rgb);
    }

    /** Shaffof chetlarni kesadi (ko'rinadigan piksellar chegarasi bo'yicha). */
    private static function trimTransparent(\GdImage $im): \GdImage
    {
        if (!imageistruecolor($im)) {
            imagepalettetotruecolor($im);
        }
        $w = imagesx($im);
        $h = imagesy($im);
        $step = max(1, (int) (max($w, $h) / 600)); // katta rasmda tezroq
        [$x1, $y1, $x2, $y2] = [$w, $h, -1, -1];
        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                if (((imagecolorat($im, $x, $y) >> 24) & 0x7F) < 120) {
                    [$x1, $y1, $x2, $y2] = [min($x1, $x), min($y1, $y), max($x2, $x), max($y2, $y)];
                }
            }
        }
        if ($x2 < 0 || ($x2 - $x1) < 8 || ($y2 - $y1) < 8) {
            return $im; // shaffof emas yoki bo'm-bo'sh
        }
        [$x1, $y1] = [max(0, $x1 - $step), max(0, $y1 - $step)];
        [$x2, $y2] = [min($w - 1, $x2 + $step), min($h - 1, $y2 + $step)];
        $out = imagecrop($im, ['x' => $x1, 'y' => $y1, 'width' => $x2 - $x1 + 1, 'height' => $y2 - $y1 + 1]);
        return $out ?: $im;
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
