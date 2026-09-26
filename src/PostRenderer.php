<?php

declare(strict_types=1);

namespace Maryam;

use GdImage;
use InvalidArgumentException;

/**
 * Brend uslubidagi TAYYOR Instagram rasmlari (PHP GD).
 *
 * Dizayn tizimi kompaniyaning o'z gridiga moslangan: foto fon (o'qilishi uchun qoraytirilgan),
 * tor qalin KATTA HARFLI sarlavha (Oswald), narx urg'u rangidagi plashkada, tepada kichik logo,
 * pastda Instagram manzili yoki CTA. Pastki yashil lenta — faqat uslub profilida so'ralsa.
 * Tartiblar: hot_tour, price_list, review, tips, compare, cover (1080×1920), slide_cover, slide_cta.
 */
final class PostRenderer
{
    public const LAYOUTS = [
        'hot_tour' => ['Qaynoq tur', 1080, 1350],
        'price_list' => ["Narxlar ro'yxati", 1080, 1350],
        'review' => ['Mijoz sharhi', 1080, 1350],
        'tips' => ['Maslahat (karusel slaydi)', 1080, 1350],
        'compare' => ['Taqqoslash: X yoki Y', 1080, 1350],
        'cover' => ['Reels / Stories muqovasi', 1080, 1920],
        'slide_cover' => ['Karusel: 1-slayd (muqova)', 1080, 1350],
        'slide_cta' => ['Karusel: oxirgi slayd (CTA)', 1080, 1350],
    ];

    /** Karusel slaydlarida ishlatiladigan tartiblar. */
    public const SLIDE_LAYOUTS = ['slide_cover', 'tips', 'slide_cta', 'hot_tour', 'price_list', 'review', 'compare'];

    public const BRAND_DIR = '/data/brand';

    /** Sotuv tartiblari: pastda CTA/telefon ko'rsatiladi (boshqalarida — Instagram manzili). */
    private const SALES = ['hot_tour', 'price_list', 'slide_cta', 'cover'];

    private const DEFAULT_TITLES = [
        'price_list' => 'Bu hafta qayerga uchamiz?',
        'compare' => 'Qaysi biri sizga mos?',
        'slide_cta' => 'Tur tanlashda yordam kerakmi?',
    ];

    private const M = 72; // chetdan bo'sh joy

    private GdImage $img;
    private int $w;
    private int $h;
    private int $bottom; // pastda band joy (lenta yoki manzil)
    private bool $photo = false;
    private string $layout = '';
    private array $c = [];

    public function __construct(private array $brand, private ?string $logoPath = null, private ?string $logoWhitePath = null, private array $style = [])
    {
    }

    /** Yuklangan logolar bilan tayyor renderer (logo bo'lmasa — so'z-belgi chiziladi). */
    public static function forBrand(array $brand, array $style = []): self
    {
        $dir = ROOT . self::BRAND_DIR;
        return new self($brand, is_file("$dir/logo.png") ? "$dir/logo.png" : null, is_file("$dir/logo-white.png") ? "$dir/logo-white.png" : null, $style);
    }

    public static function colors(Store $store): array
    {
        // Ranglar grid namunalaridan avtomatik aniqlanadi (BrandAssets::refreshPalette)
        $saved = json_decode((string) $store->meta('brand_colors_auto'), true) ?: [];
        return $saved + ['primary' => '#0a4638', 'accent' => '#e9c46a', 'dark' => '#05241c', 'light' => '#ffffff'];
    }

    // ==================== KARUSEL ====================

    /**
     * Karusel: har slayd alohida PNG. Slayd raqami ("2/7") va maslahat raqami avtomatik qo'yiladi.
     * @param array<int, array> $slides har biri: layout + maydonlar
     * @return string[] PNG baytlari
     */
    public function renderCarousel(array $slides, array $colors): array
    {
        $slides = array_values(array_filter($slides, static fn ($x) => is_array($x) && $x !== []));
        $n = count($slides);
        $out = [];
        $tip = 0;
        foreach ($slides as $i => $slide) {
            $layout = in_array($slide['layout'] ?? '', self::SLIDE_LAYOUTS, true) ? $slide['layout']
                : ($i === 0 ? 'slide_cover' : ($i === $n - 1 ? 'slide_cta' : 'tips'));
            $slide['slide'] = ($i + 1) . '/' . $n;
            if ($layout === 'tips') {
                $slide['number'] = (string) ($slide['number'] ?? ++$tip);
            }
            $out[] = $this->render($layout, $slide, $colors);
        }
        return $out;
    }

    /**
     * Copywriter yozgan "1-slayd: ..." rejasidan slaydlar. Qabul qiladi: "1-slayd:", "1 - slayd", "Slayd 1:",
     * "**1-slayd:**", "1-slayd (muqova):". CAPTION / CTA / hashtaglar bo'limida to'xtaydi.
     * @return array<int, array>
     */
    public static function slidesFromText(string $visual, string $cta = ''): array
    {
        $texts = [];
        $cur = null;
        foreach (preg_split('/\R/u', str_replace(['**', '__'], '', $visual)) as $line) {
            $line = trim($line);
            if (preg_match('/^(caption|izoh|cta|hashtag|#)/iu', $line)) {
                break; // slaydlar tugadi — keyin post matni keladi
            }
            if (preg_match('/^(?:(\d{1,2})\s*[-–.]?\s*slayd|slayd\s*(\d{1,2}))\s*(?:\([^)]*\))?\s*[:.\-–—]?\s*(.*)$/iu', $line, $m)) {
                if ($cur !== null && trim($cur) !== '') {
                    $texts[] = $cur;
                }
                $cur = $m[3];
            } elseif ($cur !== null && $line !== '') {
                $cur .= ' ' . $line; // slayd matni keyingi qatorda davom etsa
            }
        }
        if ($cur !== null && trim($cur) !== '') {
            $texts[] = $cur;
        }
        if (count($texts) < 2) {
            return [];
        }
        $slides = [];
        $last = count($texts) - 1;
        foreach ($texts as $i => $t) {
            $t = trim(preg_replace('/\s+/u', ' ', $t), " \"'“”");
            $t = (string) preg_replace('/^\d{1,2}[.)]\s+/u', '', $t); // "1. Valyuta — ..." → "Valyuta — ..."
            // Sarlavha: "Sarlavha — izoh" yoki "Sarlavha: izoh" yoki birinchi gap
            $parts = preg_split('/\s+[—–-]\s+|:\s+|(?<=[.!?])\s+/u', $t, 2);
            [$title, $text] = [trim($parts[0], ' .:'), trim($parts[1] ?? '')];
            if (mb_strlen($title) < 3) {
                [$title, $text] = [$t, ''];
            }
            $slides[] = match (true) {
                $i === 0 => ['layout' => 'slide_cover', 'title' => $t],
                $i === $last => ['layout' => 'slide_cta', 'title' => $title, 'text' => $text !== '' ? $text : $cta],
                default => ['layout' => 'tips', 'title' => $title, 'text' => $text],
            };
        }
        return $slides;
    }

    // ==================== GRID NAMUNASI ====================

    /**
     * Grid namunasi: 9 ta rasm — sahifa ritmi (sotuv / ishonch / qamrov almashinib turadi).
     * @return array<int, array{0: string, 1: array, 2: string}> [tartib, ma'lumot, izoh]
     */
    public static function gridSamples(array $products): array
    {
        $tours = array_values(array_filter(array_map(static fn ($p) => [
            'title' => (string) ($p['name'] ?? ''),
            'subtitle' => trim(implode(' · ', array_filter([(string) ($p['dates'] ?? ''), (string) ($p['duration'] ?? '')]))),
            'price' => (string) ($p['price'] ?? ''),
        ], $products), static fn ($t) => $t['title'] !== '' && $t['price'] !== ''));
        $demo = [
            ['title' => 'Vyetnam, Fukuok', 'subtitle' => 'Oktabr · 7 kun · nonushta', 'price' => '820$ dan'],
            ['title' => 'Sharm el-Sheyx', 'subtitle' => 'Har hafta · 8 kun · hammasi kiritilgan', 'price' => '818$ dan'],
            ['title' => 'Istanbul', 'subtitle' => 'Har kuni uchish · 5 kun', 'price' => '775$ dan'],
        ];
        $t = array_merge($tours, $demo);
        $list = array_map(static fn ($x) => $x['title'] . ' — ' . preg_replace('/\s*dan$/u', '', $x['price']), array_slice($t, 0, 7));
        if (count($list) < 5) {
            $list = ['Istanbul — 775$', 'Sharm — 818$', 'Vyetnam — 820$', 'Xaynan — 740$', 'Dubay — 555$', 'Boku — 498$', 'Batumi — 640$'];
        }
        return [
            ['hot_tour', $t[0], 'Sotuv: qaynoq tur'],
            ['review', ['quote' => 'Gid butun safar davomida yonimizda bo‘ldi. Mehmonxona rasmdagidan ham chiroyli chiqdi!', 'author' => 'Dilnoza, Sharmdan qaytdi'], 'Ishonch: mijoz sharhi'],
            ['price_list', ['lines' => $list], 'Sotuv: haftaning narxlari'],
            ['tips', ['number' => '3', 'title' => 'Valyutani oldindan almashtiring', 'text' => 'Aeroportda kurs past. Dollarni Toshkentda olib, joyida almashtirish arzonroq.', 'slide' => '3/7'], 'Ishonch: foydali karusel'],
            ['hot_tour', $t[1], 'Sotuv: qaynoq tur'],
            ['compare', ['title' => '1800$ ga qaysi biri?', 'left' => 'Maldiv', 'left_price' => '1800$', 'left_note' => 'Juftliklar, sokin dam, okean', 'right' => 'Singapur + Malayziya', 'right_price' => '1800$', 'right_note' => '3 davlat, shahar va sarguzasht'], 'Qamrov: taqqoslash'],
            ['cover', ['title' => 'Sharmga bormasdan oldin bilib oling', 'subtitle' => '5 ta maslahat'], 'Qamrov: Reels muqovasi'],
            ['hot_tour', $t[2], 'Sotuv: qaynoq tur'],
            ['review', ['quote' => 'Hujjatdan tortib transfergacha hammasini o‘zlari hal qilishdi. Keyingi safar ham faqat Maryam bilan.', 'author' => 'Jamshid oilasi, Istanbul'], 'Ishonch: mijoz sharhi'],
        ];
    }

    // ==================== CHIZISH ====================

    /** @param array $d title, subtitle, price, label, lines[], quote, author, number, text, button, cta, left/right, slide, bg (foto yo'li) */
    public function render(string $layout, array $d, array $colors): string
    {
        if (!isset(self::LAYOUTS[$layout])) {
            throw new InvalidArgumentException("Noma'lum tartib: $layout");
        }
        [, $this->w, $this->h] = self::LAYOUTS[$layout];
        $this->layout = $layout;
        $this->img = imagecreatetruecolor($this->w, $this->h);
        imagealphablending($this->img, true);
        imagesavealpha($this->img, true);
        foreach ($colors + ['light' => '#ffffff'] as $k => $hex) {
            $this->c[$k] = self::rgb($hex);
        }
        $this->bottom = $this->strip() ? 150 : 96;
        $d['title'] = ($d['title'] ?? '') !== '' ? $d['title'] : (self::DEFAULT_TITLES[$layout] ?? '');

        $this->background($d['bg'] ?? null);
        $this->{'layout' . str_replace('_', '', ucwords($layout, '_'))}($d);
        if (!empty($d['slide'])) {
            $this->slideCounter((string) $d['slide']);
        }
        $this->brandMark(in_array($layout, self::SALES, true) ? (string) ($d['cta'] ?? '') : '');

        ob_start();
        imagepng($this->img, null, 6);
        return (string) ob_get_clean();
    }

    /**
     * AI chizgan tayyor rasmni yakunlaydi: aniq o'lcham (1080×1350 yoki 1080×1920), tepada haqiqiy logo,
     * pastda telefon/Instagram (AI bularni buzib yozadi — shuning uchun kod qo'yadi). JPEG qaytaradi.
     */
    public function finishPoster(string $imageBytes, string $format, string $bottomText, array $colors): string
    {
        $src = @imagecreatefromstring($imageBytes);
        if (!$src) {
            throw new InvalidArgumentException('AI rasmi o‘qilmadi.');
        }
        [$this->w, $this->h] = $format === 'reels' ? [1080, 1920] : [1080, 1350];
        $this->layout = 'poster';
        $this->img = imagecreatetruecolor($this->w, $this->h);
        imagealphablending($this->img, true);
        foreach ($colors + ['light' => '#ffffff'] as $k => $hex) {
            $this->c[$k] = self::rgb($hex);
        }
        $sw = imagesx($src);
        $sh = imagesy($src);
        $scale = max($this->w / $sw, $this->h / $sh);
        $cw = (int) ($this->w / $scale);
        $ch = (int) ($this->h / $scale);
        imagecopyresampled($this->img, $src, 0, 0, (int) (($sw - $cw) / 2), (int) (($sh - $ch) / 2), $this->w, $this->h, $cw, $ch);
        // Logo va yozuv har qanday fonda o'qilsin — yengil soya
        $this->gradient(0, 150, $this->c['dark'], 60, 127);
        $this->gradient($this->h - 130, $this->h, $this->c['dark'], 127, 40);
        $this->logo((int) ($this->w / 2), 58, 'center', 60, 180);
        $line = $this->clean($bottomText);
        if ($line !== '') {
            $f = $this->fit($line, 'SemiBold', 30, 22, $this->w - 2 * self::M, 1);
            $lw = $this->width($f['lines'][0], 'SemiBold', $f['size']);
            $this->text($f['lines'][0], 'SemiBold', $f['size'], (int) (($this->w - $lw) / 2), $this->h - 40, $this->c['light']);
        }
        ob_start();
        imagejpeg($this->img, null, 92);
        return (string) ob_get_clean();
    }

    // ==================== TARTIBLAR ====================

    private function layoutHotTour(array $d): void
    {
        if (($d['label'] ?? 'QAYNOQ TUR') !== '') {
            $this->pill($d['label'] ?? 'QAYNOQ TUR', self::M, self::M + 96);
        }
        // Pastdan yuqoriga: narx → tavsif → sarlavha (ustma-ust tushmaydi)
        $y = $this->h - $this->bottom - 30;
        if (!empty($d['price'])) {
            $y -= 118;
            $this->priceBadge($d['price'], self::M, $y);
            $y -= 28;
        }
        if (!empty($d['subtitle'])) {
            $sub = $this->fit($d['subtitle'], 'SemiBold', 38, 28, $this->w - 2 * self::M, 2);
            $y -= $sub['height'];
            $this->lines($sub, self::M, $y, $this->c['light']);
            $y -= 16;
        }
        $title = $this->fit($d['title'], 'Display', 170, 84, $this->w - 2 * self::M, 3);
        $this->lines($title, self::M, max(self::M + 190, $y - $title['height']), $this->c['light']);
    }

    private function layoutCover(array $d): void
    {
        // Instagram gridda 1080×1920 ning o'rtadagi 4:5 qismi ko'rinadi (y≈285..1635) — matn shu ichida
        $title = $this->fit($d['title'], 'Display', 190, 96, $this->w - 2 * self::M, 4);
        $y = $this->lines($title, self::M, 430, $this->c['light']);
        if (!empty($d['subtitle'])) {
            $sub = $this->fit($d['subtitle'], 'Bold', 44, 30, $this->w - 2 * self::M - 56, 2);
            $wide = max(array_map(fn ($l) => $this->width($l, 'Bold', $sub['size']), $sub['lines']));
            $this->roundRect(self::M, $y + 34, self::M + $wide + 56, $y + 34 + $sub['height'] + 30, 22, $this->c['accent']);
            $this->lines($sub, self::M + 28, $y + 49, $this->c['dark']);
        }
        if (!empty($d['price'])) {
            $this->priceBadge($d['price'], self::M, 1460);
        }
    }

    private function layoutSlideCover(array $d): void
    {
        if (!empty($d['label'])) {
            $this->pill($d['label'], self::M, self::M + 96);
        }
        $title = $this->fit($d['title'], 'Display', 150, 76, $this->w - 2 * self::M, 5);
        $sub = !empty($d['subtitle']) ? $this->fit($d['subtitle'], 'SemiBold', 40, 28, $this->w - 2 * self::M, 2) : null;
        $blockH = $title['height'] + ($sub ? $sub['height'] + 24 : 0);
        $y = $this->centerY($blockH, self::M + 180, $this->h - $this->bottom - 110);
        $y = $this->lines($title, self::M, $y, $this->c['light']);
        if ($sub) {
            $this->lines($sub, self::M, $y + 24, $this->c['accent']);
        }
        $swipe = 'Surib ko‘ring  →';
        $this->text($swipe, 'Bold', 34, $this->w - self::M - $this->width($swipe, 'Bold', 34), $this->h - $this->bottom - 50, $this->c['accent']);
    }

    private function layoutTips(array $d): void
    {
        if (!empty($d['label'])) {
            $this->pill($d['label'], self::M, self::M + 96);
        }
        $title = $this->fit($d['title'], 'Display', 104, 60, $this->w - 2 * self::M, 3);
        $body = !empty($d['text']) ? $this->fit($d['text'], 'Medium', 42, 32, $this->w - 2 * self::M, 6) : null;
        $circle = 150;
        $top = self::M + 180;
        $bottomLimit = $this->h - $this->bottom - 40;
        $blockH = $circle + 44 + $title['height'] + ($body ? $body['height'] + 26 : 0);
        if ($blockH > $bottomLimit - $top && $body) { // joy yetmasa — matn qisqaradi ("…" bilan)
            $body = $this->shrinkTo($body, $bottomLimit - $top - ($circle + 44 + $title['height'] + 26));
            $blockH = $circle + 44 + $title['height'] + ($body ? $body['height'] + 26 : 0);
        }
        $y = $this->centerY($blockH, $top, $bottomLimit);
        $num = $this->clean((string) ($d['number'] ?? '1'));
        imagefilledellipse($this->img, self::M + (int) ($circle / 2), $y + (int) ($circle / 2), $circle, $circle, $this->c['accent']);
        $ns = mb_strlen($num) > 1 ? 70 : 88;
        $this->text($num, 'Display', $ns, self::M + (int) (($circle - $this->width($num, 'Display', $ns)) / 2), $y + (int) ($circle / 2) + (int) ($ns * 0.52), $this->c['dark']);
        $y = $this->lines($title, self::M, $y + $circle + 44, $this->c['light']);
        if ($body) {
            $this->lines($body, self::M, $y + 26, $this->alpha('light', 118));
        }
    }

    private function layoutSlideCta(array $d): void
    {
        $body = !empty($d['text']) ? $this->fit($d['text'], 'Medium', 42, 30, $this->w - 2 * self::M, 3) : null;
        $button = $this->clean((string) ($d['button'] ?? '')) ?: "Direct'ga yozing";
        if ($body && mb_strtolower(implode(' ', $body['lines'])) === mb_strtolower($button)) {
            $body = null; // bir xil gap ikki marta yozilmasin
        }
        $btn = $this->fit($button, 'Bold', 44, 28, $this->w - 2 * self::M - 80, 1);
        [$top, $bottom] = [self::M + 180, $this->h - $this->bottom - 40];
        $rest = ($body ? $body['height'] + 28 : 0) + 60 + 116;
        // Sarlavha joyga sig'guncha kichrayadi — tugma pastdagi yozuvga tegmasin
        for ($max = 124; $max >= 64; $max -= 10) {
            $title = $this->fit($d['title'], 'Display', $max, min($max, 64), $this->w - 2 * self::M, 4);
            if ($title['height'] + $rest <= $bottom - $top) {
                break;
            }
        }
        $blockH = $title['height'] + $rest;
        $y = $this->centerY($blockH, $top, $bottom);
        $y = $this->lines($title, self::M, $y, $this->c['light']);
        if ($body) {
            $y = $this->lines($body, self::M, $y + 28, $this->alpha('light', 118));
        }
        $bw = $this->width($btn['lines'][0], 'Bold', $btn['size']) + 80;
        $this->roundRect(self::M, $y + 60, self::M + $bw, $y + 176, 58, $this->c['accent']);
        $this->text($btn['lines'][0], 'Bold', $btn['size'], self::M + 40, $y + 118 + (int) ($btn['size'] * 0.5), $this->c['dark']);
    }

    private function layoutReview(array $d): void
    {
        $this->pill($d['label'] ?? 'MIJOZIMIZ GAPIRADI', self::M, self::M + 96);
        $quote = $this->fit($d['quote'] ?? '', 'Bold', 56, 36, $this->w - 2 * self::M, 7);
        $author = !empty($d['author']) ? $this->fit((string) $d['author'], 'SemiBold', 36, 26, $this->w - 2 * self::M - 90, 1) : null;
        $blockH = 170 + $quote['height'] + ($author ? 90 : 0);
        $y = $this->centerY($blockH, self::M + 180, $this->h - $this->bottom - 40);
        $this->text('“', 'Display', 240, self::M - 6, $y + 250, $this->c['accent']);
        $y = $this->lines($quote, self::M, $y + 170, $this->c['light']);
        if ($author) {
            imagefilledrectangle($this->img, self::M, $y + 52, self::M + 64, $y + 58, $this->c['accent']);
            $this->text($author['lines'][0], 'SemiBold', $author['size'], self::M + 86, $y + 68, $this->c['light']);
        }
    }

    private function layoutPriceList(array $d): void
    {
        $this->pill($d['label'] ?? 'QAYNOQ NARXLAR', self::M, self::M + 96);
        $title = $this->fit($d['title'], 'Display', 112, 64, $this->w - 2 * self::M, 2);
        $rows = [];
        foreach (array_slice((array) ($d['lines'] ?? []), 0, 9) as $row) {
            // "Joy — narx", "Joy - narx", "Joy: narx", "Joy – narx"
            $parts = preg_split('/\s+[—–-]\s+|:\s+/u', trim((string) $row), 2);
            $rows[] = [$this->clean($parts[0]), $this->clean($parts[1] ?? '')];
        }
        $n = max(1, count($rows));
        $rowH = min(104, max(72, (int) ((($this->h - $this->bottom - 60) - (self::M + 200) - $title['height'] - 60) / $n)));
        $blockH = $title['height'] + 50 + $rowH * $n;
        $y = $this->centerY($blockH, self::M + 180, $this->h - $this->bottom - 40);
        $y = $this->lines($title, self::M, $y, $this->c['light']);
        $top = $y + 50;
        $this->roundRect(self::M - 24, $top - 12, $this->w - self::M + 24, $top + $rowH * $n + 12, 28, $this->alpha('dark', 70));
        $size = $rowH >= 90 ? 42 : 36;
        foreach ($rows as $i => [$place, $price]) {
            $base = $top + $rowH * $i + (int) ($rowH / 2 + $size * 0.5);
            $pw = $price !== '' ? $this->width($price, 'Display', $size + 6) : 0;
            $name = $this->fit($place, 'SemiBold', $size, $size - 6, $this->w - 2 * self::M - 20 - $pw - 40, 1);
            $this->text($name['lines'][0], 'SemiBold', $name['size'], self::M + 10, $base, $this->c['light']);
            if ($price !== '') {
                $this->text($price, 'Display', $size + 6, $this->w - self::M - 10 - $pw, $base + 2, $this->c['accent']);
            }
            if ($i < $n - 1) {
                imagefilledrectangle($this->img, self::M + 10, $top + $rowH * ($i + 1), $this->w - self::M - 10, $top + $rowH * ($i + 1) + 1, $this->alpha('light', 34));
            }
        }
    }

    private function layoutCompare(array $d): void
    {
        $q = $this->fit($d['title'], 'Display', 84, 52, $this->w - 2 * self::M, 2);
        $y0 = $this->lines($q, self::M, self::M + 150, $this->c['light']);
        $mid = (int) ($this->w / 2);
        $colW = $mid - self::M - 40;
        $vsY = $y0 + 110;
        imagefilledrectangle($this->img, $mid - 2, $vsY + 80, $mid + 1, $this->h - $this->bottom - 40, $this->alpha('light', 70));
        foreach ([['left', self::M], ['right', $mid + 40]] as [$side, $x]) {
            $name = $this->fit($d[$side] ?? '', 'Display', 96, 52, $colW, 3);
            $y = $this->lines($name, $x, $vsY + 110, $this->c['light']);
            if (!empty($d[$side . '_price'])) {
                $p = $this->fit((string) $d[$side . '_price'], 'Display', 70, 40, $colW, 1);
                $this->text($p['lines'][0], 'Display', $p['size'], $x, $y + 20 + (int) ($p['size'] * 1.1), $this->c['accent']);
                $y += 30 + (int) ($p['size'] * 1.4);
            }
            if (!empty($d[$side . '_note'])) {
                $note = $this->fit($d[$side . '_note'], 'Medium', 34, 26, $colW, 4);
                $this->lines($note, $x, $y + 16, $this->alpha('light', 118));
            }
        }
        imagefilledellipse($this->img, $mid, $vsY + 20, 150, 150, $this->c['accent']);
        $this->text('VS', 'Display', 60, $mid - (int) ($this->width('VS', 'Display', 60) / 2), $vsY + 52, $this->c['dark']);
    }

    // ==================== UMUMIY ELEMENTLAR ====================

    private function strip(): bool
    {
        return !empty($this->style['bottom_strip']);
    }

    /** Foto (o'qilishi uchun qoraytiriladi) yoki brend gradienti. */
    private function background(?string $photo): void
    {
        $src = $photo && is_file($photo) ? @imagecreatefromstring((string) file_get_contents($photo)) : false;
        $this->photo = (bool) $src;
        if ($src) {
            $sw = imagesx($src);
            $sh = imagesy($src);
            $scale = max($this->w / $sw, $this->h / $sh);
            $cw = (int) ($this->w / $scale);
            $ch = (int) ($this->h / $scale);
            imagecopyresampled($this->img, $src, 0, 0, (int) (($sw - $cw) / 2), (int) (($sh - $ch) / 2), $this->w, $this->h, $cw, $ch);
            // Sarlavhasi pastda bo'lgan tartiblar: tepa + kuchli pastki soya; matnli tartiblar: butun rasm qoraytiriladi
            $textHeavy = !in_array($this->layout, ['hot_tour', 'cover'], true);
            if ($textHeavy) {
                imagefilledrectangle($this->img, 0, 0, $this->w, $this->h, $this->alpha('dark', 80));
            }
            $this->gradient(0, (int) ($this->h * 0.3), $this->c['dark'], 70, 127);
            $this->gradient((int) ($this->h * 0.38), $this->h, $this->c['dark'], 127, $textHeavy ? 30 : 12);
            return;
        }
        [$r1, $g1, $b1] = $this->hex($this->c['primary']);
        [$r2, $g2, $b2] = $this->hex($this->c['dark']);
        for ($y = 0; $y < $this->h; $y++) {
            $t = $y / $this->h;
            $col = imagecolorallocate($this->img, (int) ($r1 + ($r2 - $r1) * $t), (int) ($g1 + ($g2 - $g1) * $t), (int) ($b1 + ($b2 - $b1) * $t));
            imageline($this->img, 0, $y, $this->w, $y, $col);
        }
        imagefilledellipse($this->img, (int) ($this->w * 0.85), (int) ($this->h * 0.16), 700, 700, $this->alpha('accent', 12));
    }

    /** Tepada kichik logo; pastda CTA (sotuv) yoki Instagram manzili. Uslubda so'ralsa — to'liq pastki lenta. */
    private function brandMark(string $cta): void
    {
        $phone = trim((string) preg_replace('/\s*\(.*?\)/u', '', (string) ($this->brand['phone'] ?? '')));
        $handle = $this->clean((string) ($this->brand['instagram'] ?? ''));
        $sales = in_array($this->layout, self::SALES, true);
        if ($this->strip()) {
            $top = $this->h - 150;
            imagefilledrectangle($this->img, 0, $top, $this->w, $this->h, $this->c['primary']);
            imagefilledrectangle($this->img, 0, $top, $this->w, $top + 5, $this->c['accent']);
            $main = $this->fit($this->clean($cta) ?: $phone, 'Bold', 34, 22, $this->w - 2 * self::M - 360, 1);
            $this->text($main['lines'][0], 'Bold', $main['size'], self::M, $top + 70, $this->c['light']);
            if ($handle !== '') {
                $this->text($handle, 'Medium', 26, self::M, $top + 115, $this->alpha('light', 95));
            }
            $this->logo($this->w - self::M, $top + 75, 'right', 96, 320);
            return;
        }
        $this->logo((int) ($this->w / 2), self::M + 26, 'center', 64, 190);
        $line = $sales ? ($this->clean($cta) ?: $phone) : $handle;
        if ($line !== '') {
            $f = $this->fit($line, 'SemiBold', 30, 22, $this->w - 2 * self::M, 1);
            $lw = $this->width($f['lines'][0], 'SemiBold', $f['size']);
            $this->text($f['lines'][0], 'SemiBold', $f['size'], (int) (($this->w - $lw) / 2), $this->h - 42, $this->alpha('light', $sales ? 127 : 100));
        }
    }

    private function logo(int $x, int $centerY, string $align, int $maxH, int $maxW): void
    {
        $path = $this->logoWhitePath && is_file($this->logoWhitePath) ? $this->logoWhitePath : ($this->logoPath && is_file($this->logoPath) ? $this->logoPath : null);
        $logo = $path ? @imagecreatefromstring((string) file_get_contents($path)) : false;
        if ($logo) {
            $scale = min($maxH / imagesy($logo), $maxW / imagesx($logo));
            $lw = (int) (imagesx($logo) * $scale);
            $lh = (int) (imagesy($logo) * $scale);
            $left = $align === 'center' ? $x - (int) ($lw / 2) : $x - $lw;
            imagecopyresampled($this->img, $logo, $left, $centerY - (int) ($lh / 2), 0, 0, $lw, $lh, imagesx($logo), imagesy($logo));
            return;
        }
        // Logo yuklanmagan — so'z-belgi
        $name = mb_strtoupper(explode(' ', (string) ($this->brand['name'] ?? 'MARYAM'))[0]);
        $size = $maxH >= 90 ? 44 : 36;
        $nw = $this->width($name, 'Display', $size);
        $tag = 'TRAVEL AGENCY';
        $tw = $this->width($tag, 'SemiBold', 14);
        $left = $align === 'center' ? $x - (int) ($nw / 2) : $x - $nw;
        $this->text($name, 'Display', $size, $left, $centerY + 8, $this->c['accent']);
        $this->text($tag, 'SemiBold', 14, $align === 'center' ? $x - (int) ($tw / 2) : $x - $tw, $centerY + 34, $this->c['light']);
    }

    private function pill(string $label, int $x, int $y): void
    {
        $f = $this->fit(mb_strtoupper($label), 'Bold', 26, 18, $this->w - 2 * self::M - 260, 1);
        $w = $this->width($f['lines'][0], 'Bold', $f['size']);
        $this->roundRect($x, $y, $x + $w + 48, $y + 58, 29, $this->c['accent']);
        $this->text($f['lines'][0], 'Bold', $f['size'], $x + 24, $y + 29 + (int) ($f['size'] * 0.5), $this->c['dark']);
    }

    private function slideCounter(string $label): void
    {
        $label = $this->clean($label);
        $w = $this->width($label, 'Bold', 26);
        $y = self::M + 96;
        $this->roundRect($this->w - self::M - $w - 40, $y, $this->w - self::M, $y + 58, 29, $this->alpha('dark', 96));
        $this->text($label, 'Bold', 26, $this->w - self::M - $w - 20, $y + 42, $this->c['light']);
    }

    /** Narx plashkasi: "820$ dan" → katta raqam + kichik "dan"; kenglikka sig'diriladi. */
    private function priceBadge(string $price, int $x, int $y): void
    {
        $price = $this->clean($price);
        $suffix = '';
        if (preg_match('/^(.*?)\s*((?:dan|-dan)(?:\s+boshlab)?)$/iu', $price, $m) && $m[1] !== '') {
            [$price, $suffix] = [$m[1], $m[2]];
        }
        $max = $this->w - 2 * self::M - 64;
        for ($size = 84; $size >= 40; $size -= 4) {
            $pw = $this->width($price, 'Display', $size);
            $ss = (int) max(24, $size * 0.42);
            $sw = $suffix !== '' ? $this->width($suffix, 'Bold', $ss) + 14 : 0;
            if ($pw + $sw <= $max) {
                break;
            }
        }
        $h = (int) ($size * 1.4) + 4;
        $this->roundRect($x, $y, $x + $pw + $sw + 64, $y + $h, 26, $this->c['accent']);
        $base = $y + (int) ($h / 2 + $size * 0.52);
        $this->text($price, 'Display', $size, $x + 32, $base, $this->c['dark']);
        if ($suffix !== '') {
            $this->text($suffix, 'Bold', $ss, $x + 32 + $pw + 14, $base, $this->c['dark']);
        }
    }

    // ==================== MATN ====================

    private function font(string $weight): string
    {
        return ROOT . '/resources/fonts/' . ($weight === 'Display' ? 'Oswald-Bold' : "Montserrat-$weight") . '.ttf';
    }

    /** Sarlavhalar katta harfda (kompaniya gridi kabi) — uslub profilida boshqacha bo'lmasa. */
    private function upper(): bool
    {
        return ($this->style['uppercase_titles'] ?? true) !== false;
    }

    /** Shriftda bor belgilar qoladi (emoji va boshqalar quti bo'lib chiqmasin); ʻ ʼ → ‘ ’. */
    private function clean(string $s): string
    {
        $s = strtr($s, ['ʻ' => '‘', 'ʼ' => '’', "'" => '’', '`' => '’']);
        $s = (string) preg_replace('/[^\x{0009}\x{000A}\x{0020}-\x{007E}\x{00A0}-\x{024F}\x{0300}-\x{036F}\x{0400}-\x{04FF}\x{2010}-\x{2027}\x{2030}-\x{203A}\x{20A0}-\x{20BF}\x{2116}\x{2122}\x{2190}-\x{2193}]/u', '', $s);
        return trim((string) preg_replace('/[ \t]{2,}/u', ' ', $s));
    }

    private function width(string $s, string $weight, int $size): int
    {
        $b = imagettfbbox($size, 0, $this->font($weight), $s);
        return (int) ($b[2] - $b[0]);
    }

    private function text(string $s, string $weight, int $size, int $x, int $y, int $color): void
    {
        imagettftext($this->img, $size, 0, $x, $y, $color, $this->font($weight), $s);
    }

    /** Matnni kenglikka sig'dirib, eng katta mos shriftni tanlaydi; baribir sig'masa — oxirida "…". */
    private function fit(string $s, string $weight, int $max, int $min, int $maxWidth, int $maxLines): array
    {
        $s = $this->clean($s);
        if ($weight === 'Display' && $this->upper()) {
            $s = mb_strtoupper($s);
        }
        $lines = [$s];
        for ($size = $max; $size >= $min; $size -= 4) {
            $lines = $this->wrap($s, $weight, $size, $maxWidth);
            $widest = max(array_map(fn ($l) => $this->width($l, $weight, $size), $lines));
            if (count($lines) <= $maxLines && $widest <= $maxWidth) {
                break;
            }
        }
        $size = max($size, $min);
        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lines[$maxLines - 1] = $this->ellipsis($lines[$maxLines - 1] . ' …', $weight, $size, $maxWidth);
        }
        foreach ($lines as $i => $l) {
            if ($this->width($l, $weight, $size) > $maxWidth) {
                $lines[$i] = $this->ellipsis($l, $weight, $size, $maxWidth); // bitta juda uzun so'z
            }
        }
        $lh = (int) round($size * ($weight === 'Display' ? 1.38 : 1.68));
        return ['lines' => $lines, 'size' => $size, 'weight' => $weight, 'lh' => $lh, 'height' => $lh * count($lines)];
    }

    private function ellipsis(string $line, string $weight, int $size, int $maxWidth): string
    {
        $line = rtrim((string) preg_replace('/\s*…$/u', '', $line), ' ,.;:—–-');
        while ($line !== '' && $this->width($line . '…', $weight, $size) > $maxWidth) {
            $line = mb_substr($line, 0, -1);
        }
        return rtrim($line, ' ,.;:—–-') . '…';
    }

    /** Blokni balandlikka sig'guncha qisqartiradi (oxirgi qator "…" bilan). */
    private function shrinkTo(array $block, int $room): ?array
    {
        $keep = (int) floor($room / max(1, $block['lh']));
        if ($keep < 1) {
            return null;
        }
        if ($keep < count($block['lines'])) {
            $block['lines'] = array_slice($block['lines'], 0, $keep);
            $block['lines'][$keep - 1] = $this->ellipsis($block['lines'][$keep - 1] . ' …', $block['weight'], $block['size'], $this->w - 2 * self::M);
            $block['height'] = $block['lh'] * $keep;
        }
        return $block;
    }

    private function wrap(string $s, string $weight, int $size, int $maxWidth): array
    {
        $lines = [];
        foreach (preg_split('/\R/u', $s) as $para) {
            $line = '';
            foreach (preg_split('/\s+/u', trim($para)) as $word) {
                $try = $line === '' ? $word : "$line $word";
                if ($line !== '' && $this->width($try, $weight, $size) > $maxWidth) {
                    $lines[] = $line;
                    $line = $word;
                } else {
                    $line = $try;
                }
            }
            if ($line !== '') {
                $lines[] = $line;
            }
        }
        return $lines ?: [''];
    }

    /** Qatorlarni chizadi; keyingi bo'sh y ni qaytaradi. */
    private function lines(array $block, int $x, int $y, int $color): int
    {
        $offset = (int) round($block['lh'] * 0.5 + $block['size'] * 0.45);
        foreach ($block['lines'] as $i => $line) {
            $this->text($line, $block['weight'], $block['size'], $x, $y + $block['lh'] * $i + $offset, $color);
        }
        return $y + $block['height'];
    }

    private function centerY(int $blockH, int $top, int $bottom): int
    {
        return max($top, (int) ($top + ($bottom - $top - $blockH) / 2));
    }

    // ==================== RANG VA SHAKL ====================

    private static function rgb(string $hex): int
    {
        return (int) hexdec(ltrim($hex, '#'));
    }

    private function hex(int $c): array
    {
        return [($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF];
    }

    /** Yarim shaffof rang; $visible: 0 (ko'rinmas) .. 127 (to'liq ko'rinadi). */
    private function alpha(string $name, int $visible): int
    {
        [$r, $g, $b] = $this->hex($this->c[$name]);
        return imagecolorallocatealpha($this->img, $r, $g, $b, 127 - max(0, min(127, $visible)));
    }

    private function gradient(int $from, int $to, int $color, int $alphaFrom, int $alphaTo): void
    {
        [$r, $g, $b] = $this->hex($color);
        for ($y = $from; $y < $to; $y++) {
            $a = (int) ($alphaFrom + ($alphaTo - $alphaFrom) * (($y - $from) / max(1, $to - $from)));
            imageline($this->img, 0, $y, $this->w, $y, imagecolorallocatealpha($this->img, $r, $g, $b, $a));
        }
    }

    /** Yumaloq burchakli to'rtburchak; qismlar ustma-ust tushmaydi (yarim shaffof rangda dog' qolmasin). */
    private function roundRect(int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
    {
        $r = min($r, (int) (($y2 - $y1) / 2), (int) (($x2 - $x1) / 2));
        imagefilledrectangle($this->img, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($this->img, $x1, $y1 + $r, $x1 + $r - 1, $y2 - $r, $color);
        imagefilledrectangle($this->img, $x2 - $r + 1, $y1 + $r, $x2, $y2 - $r, $color);
        foreach ([[$x1 + $r - 1, $y1 + $r - 1, 180, 270], [$x2 - $r + 1, $y1 + $r - 1, 270, 360],
                  [$x1 + $r - 1, $y2 - $r + 1, 90, 180], [$x2 - $r + 1, $y2 - $r + 1, 0, 90]] as [$cx, $cy, $a1, $a2]) {
            imagefilledarc($this->img, $cx, $cy, $r * 2, $r * 2, $a1, $a2, $color, IMG_ARC_PIE);
        }
    }
}
