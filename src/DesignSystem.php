<?php

declare(strict_types=1);

namespace Maryam;

/**
 * DIZAYN TIZIMI — barcha postlar bitta professional seriyaga o'xshashi uchun qat'iy qoidalar:
 * rang juftligi, tipografika ierarxiyasi, kompozitsiya, qahramon obyekt, grafik elementlar.
 *
 * Manba: egasi yuklagan uslub namunalari (AI tahlil qiladi) yoki standart "Bold editorial" tizimi.
 * Ranglar: brend ranglari (yashil + yorqin oltin) yoki namunadagi ranglar — egasi tanlaydi.
 */
final class DesignSystem
{
    /** Standart tizim — kuchli agentlik lentalari uslubi (katta tor shrift, qirqilgan qahramon, ikki rang). */
    private const DEFAULT_EN = <<<'TXT'
BOLD EDITORIAL TWO-TONE SYSTEM — the look of a top creative agency's Instagram feed; every post is part of one consistent series.
- COLOUR: strictly two-tone. Deep dark base {DARK} (rich, with soft radial glow in {ACCENT}, fine film grain and faint thin grid or perspective lines) — or, on some posts for rhythm, a solid bright {ACCENT} background with {DARK} text. White is the only extra colour.
- TYPOGRAPHY IS THE HERO: ultra-bold condensed sans-serif (Anton / Bebas Neue style), ALL CAPS, very tight leading, set HUGE so the headline fills 70–90% of the width. Clear hierarchy: the key word is enormous in {ACCENT}, the other words white; tiny kicker line above the headline in a thin clean sans; small connector words set small in a light italic, tucked between the big words. Sometimes one word sits inside a solid {ACCENT} highlight box with {DARK} letters.
- HERO SUBJECT: one striking photographic cut-out that tells the story (a traveller, a hand holding a passport or boarding pass, a suitcase, an airplane, a landmark, a sun lounger), realistic and sharp, with dramatic {ACCENT} rim light, placed close to the headline for depth — it may only touch the edge of the letters and must never hide any part of a word; every word stays fully readable.
- ACCENTS: few and precise — small arrows, underline strokes, thin outline frames, glowing rings, geometric shapes in {ACCENT}. Generous negative space, perfect alignment on a clear grid (centred or strong left axis). No clutter, no random stickers, no flat clip-art.
- FINISH: high contrast, crisp, premium, magazine quality; lighting and colour grading identical across the series.
TXT;

    public const DEFAULT_SUMMARY = "Standart uslub — \"Bold editorial\": ikki rang (to'q yashil + yorqin oltin), juda katta tor KATTA HARFLI sarlavha, "
        . "asosiy so'z oltin rangda, qirqilgan qahramon (odam, pasport, chamadon, samolyot) harflarning oldiga chiqib turadi, "
        . "tepada logo, pastda bir xil tugma.";

    public static function get(Store $store): array
    {
        $saved = json_decode((string) $store->meta('design_system'), true) ?: [];
        return [
            'source' => $saved ? 'inspo' : 'default',
            'summary' => (string) ($saved['summary_uz'] ?? self::DEFAULT_SUMMARY),
            'art' => (string) (($saved['art_direction_en'] ?? '') ?: self::DEFAULT_EN),
            'inspo_palette' => (array) ($saved['palette'] ?? []),
            'palette_mode' => $store->meta('design_palette') === 'inspo' && $saved ? 'inspo' : 'brand',
        ];
    }

    /** Dizaynda ishlatiladigan rang juftligi. Brend oltini kuchaytiriladi — yorqin urg'u professional ko'rinadi. */
    public static function palette(Store $store): array
    {
        $sys = self::get($store);
        $p = $sys['inspo_palette'];
        if ($sys['palette_mode'] === 'inspo' && self::hex($p['dark'] ?? '') && self::hex($p['accent'] ?? '')) {
            return ['dark' => strtolower($p['dark']), 'accent' => strtolower($p['accent'])];
        }
        $brand = PostRenderer::colors($store);
        return ['dark' => self::shade($brand['primary'], null, 0.11), 'accent' => self::shade($brand['accent'], 0.9, 0.56)];
    }

    /** Rasm modeli uchun tayyor inglizcha tizim matni (ranglar qo'yilgan). */
    public static function artDirection(Store $store): string
    {
        $p = self::palette($store);
        return strtr(self::get($store)['art'], ['{DARK}' => $p['dark'], '{ACCENT}' => $p['accent']]);
    }

    /** Uslub namunalarini ko'rib, dizayn tizimini chiqaradi (namuna yuklanganda/o'chirilganda). */
    public static function analyze(object $ai, Store $store): ?string
    {
        $images = BrandAssets::insposForAi(3);
        if (!$images) {
            $store->setMeta('design_system', '');
            return null;
        }
        try {
            $d = Prompts::ask($ai, $store, 'designer/system', ['namunalar_soni' => count($images)], 0.2, true, null, 'designer', 'system', $images);
        } catch (\Throwable $e) {
            return "Uslubni tahlil qilib bo'lmadi: " . $e->getMessage();
        }
        $art = trim((string) ($d['art_direction_en'] ?? ''));
        if ($art === '') {
            return "Uslub tahlili bo'sh qaytdi — \"Qayta tahlil\" ni bosing.";
        }
        $store->setMeta('design_system', (string) json_encode([
            'summary_uz' => trim((string) ($d['summary_uz'] ?? '')),
            'art_direction_en' => $art,
            'palette' => array_filter(['dark' => (string) ($d['palette']['dark'] ?? ''), 'accent' => (string) ($d['palette']['accent'] ?? '')], [self::class, 'hex']),
        ], JSON_UNESCAPED_UNICODE));
        return null;
    }

    public static function hex(string $v): bool
    {
        return (bool) preg_match('/^#[0-9a-f]{6}$/i', $v);
    }

    /** Rangni to'yinganlik/yorqinlik bo'yicha sozlaydi ($s = null — o'zgarmaydi). */
    private static function shade(string $hex, ?float $s, float $l): string
    {
        [$r, $g, $b] = array_map(static fn ($x) => hexdec($x) / 255, str_split(ltrim($hex, '#'), 2));
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $h = 0.0;
        $sat = 0.0;
        if ($max !== $min) {
            $d = $max - $min;
            $sat = ($max + $min) / 2 > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
            $h = match ($max) {
                $r => fmod(($g - $b) / $d + 6, 6),
                $g => ($b - $r) / $d + 2,
                default => ($r - $g) / $d + 4,
            } * 60;
        }
        $sat = $s === null ? $sat : max($sat, $s);
        $c = (1 - abs(2 * $l - 1)) * $sat;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;
        [$r, $g, $b] = match (true) {
            $h < 60 => [$c, $x, 0], $h < 120 => [$x, $c, 0], $h < 180 => [0, $c, $x],
            $h < 240 => [0, $x, $c], $h < 300 => [$x, 0, $c], default => [$c, 0, $x],
        };
        return sprintf('#%02x%02x%02x', ...array_map(static fn ($v) => (int) round(($v + $m) * 255), [$r, $g, $b]));
    }
}
