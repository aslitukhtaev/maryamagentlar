<?php

declare(strict_types=1);

namespace Maryam;

/**
 * Natijalarni output/<sana>-<mavzu>/ papkasiga fayl qilib saqlaydi.
 * Bitta brifning barcha agentlari (copywriter, ssenarist, ...) bitta papkaga yozadi.
 */
final class Output
{
    public static function dir(array $brief): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', self::latin($brief['topic'])), '-'));
        $dir = ROOT . '/output/' . date('Y-m-d') . '-' . (substr($slug, 0, 40) ?: 'brif') . '-' . ($brief['id'] ?? 0);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir;
    }

    public static function save(array $brief, string $filename, string $content): string
    {
        $path = self::dir($brief) . '/' . $filename;
        file_put_contents($path, $content);
        return $path;
    }

    /** Papka nomi uchun: o'zbekcha/ruscha harflarni lotinga yaqinlashtiradi. */
    private static function latin(string $s): string
    {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        return $t !== false ? $t : $s;
    }
}
