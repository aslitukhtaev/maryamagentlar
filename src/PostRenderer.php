<?php

declare(strict_types=1);

namespace Maryam;

use GdImage;
use InvalidArgumentException;

/**
 * Brend uslubidagi TAYYOR Instagram rasmlari (PHP GD) — dizayner Canva'da qo'lda yig'masin.
 *
 * Bitta dizayn tizimi: to'q yashil + oltin, Montserrat shrifti, pastda yashil lenta (telefon + logo).
 * Tartiblar (layout): hot_tour, price_list, review, tips, compare, cover.
 * Fon: foto (berilsa, qoraytirilgan) yoki brend gradienti.
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
                $slide['label'] ??= ''; // karusel ichida har slaydda belgi takrorlanmasin
            }
            $out[] = $this->render($layout, $slide, $colors);
        }
        return $out;
    }

    /**
     * Copywriter yozgan "1-slayd: ..., 2-slayd: ..." matnidan slaydlar (AI slaydlar bermagan holat uchun).
     * @return array<int, array>
     */
    public static function slidesFromText(string $visual, string $cta = ''): array
    {
        preg_match_all('/(?:^|\n)\s*(\d+)\s*[-–.]?\s*slayd\s*[:.\-–]\s*(.+?)(?=\n\s*\d+\s*[-–.]?\s*slayd|\z)/isu', $visual, $m, PREG_SET_ORDER);
        $texts = array_map(static fn ($x) => trim(preg_replace('/\s+/u', ' ', $x[2])), $m);
        if (count($texts) < 2) {
            return [];
        }
        $slides = [];
        $last = count($texts) - 1;
        foreach ($texts as $i => $t) {
            $t = trim($t, " \"'“”");
            // "Sarlavha — tushuntirish" yoki birinchi gap sarlavha bo'ladi
            $parts = preg_split('/\s+[—–-]\s+|(?<=[.!?:])\s+/u', $t, 2);
            [$title, $text] = [trim($parts[0], ' .:'), trim($parts[1] ?? '')];
            $slides[] = match (true) {
                $i === 0 => ['layout' => 'slide_cover', 'title' => $t],
                $i === $last => ['layout' => 'slide_cta', 'title' => $title, 'text' => $text !== '' ? $text : $cta],
                default => ['layout' => 'tips', 'title' => $title, 'text' => $text],
            };
        }
        return $slides;
    }

    private const M = 72; // chetdan bo'sh joy
    private const STRIP = 150;

    private GdImage $img;
    private int $w;
    private int $h;
    private array $c = [];

    public function __construct(private array $brand, private ?string $logoPath = null, private ?string $logoWhitePath = null, private array $style = [])
    {
    }

    public const BRAND_DIR = '/data/brand';

    /** Yuklangan logolar bilan tayyor renderer (logo bo'lmasa — so'z-belgi chiziladi). */
    public static function forBrand(array $brand, array $style = []): self
    {
        $dir = ROOT . self::BRAND_DIR;
        return new self($brand, is_file("$dir/logo.png") ? "$dir/logo.png" : null, is_file("$dir/logo-white.png") ? "$dir/logo-white.png" : null, $style);
    }

    /**
     * Grid namunasi: 9 ta rasm — sahifa ritmi (sotuv / ishonch / qamrov almashinib turadi).
     * Katalogda turlar bo'lsa — ular ishlatiladi, bo'lmasa namunaviy narxlar.
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

    public static function colors(Store $store): array
    {
        // Ranglar grid namunalaridan avtomatik aniqlanadi (BrandAssets::refreshPalette)
        $saved = json_decode((string) $store->meta('brand_colors_auto'), true) ?: [];
        return $saved + ['primary' => '#0a4638', 'accent' => '#c9982f', 'dark' => '#06261f', 'light' => '#ffffff'];
    }

    /** @param array $d title, subtitle, price, label, lines[], quote, author, number, text, cta, left/right, bg (foto yo'li) */
    public function render(string $layout, array $d, array $colors): string
    {
        if (!isset(self::LAYOUTS[$layout])) {
            throw new InvalidArgumentException("Noma'lum tartib: $layout");
        }
        [, $this->w, $this->h] = self::LAYOUTS[$layout];
        $this->img = imagecreatetruecolor($this->w, $this->h);
        imagealphablending($this->img, true);
        imagesavealpha($this->img, true);
        foreach ($colors as $k => $hex) {
            $this->c[$k] = self::rgb($hex);
        }

        if (!empty($this->style['uppercase_titles']) && isset($d['title'])) {
            $d['title'] = mb_strtoupper((string) $d['title']); // kompaniya gridida sarlavhalar katta harfda
        }
        $this->background($d['bg'] ?? null, $layout);
        $this->{'layout' . str_replace('_', '', ucwords($layout, '_'))}($d);
        if (!empty($d['slide']) && !in_array($layout, ['tips', 'slide_cover', 'slide_cta'], true)) {
            $this->slideCounter((string) $d['slide']); // karuselga qo'shilgan boshqa tartiblar
        }
        $this->strip($d['cta'] ?? '');

        ob_start();
        imagepng($this->img, null, 6);
        return (string) ob_get_clean();
    }

    // ==================== TARTIBLAR ====================

    private function layoutHotTour(array $d): void
    {
        $this->pill($d['label'] ?? 'QAYNOQ TUR', self::M, self::M + 10);
        // Pastdan yuqoriga: narx plashkasi → tavsif → sarlavha (hech biri ustma-ust tushmaydi)
        $badgeTop = $this->h - self::STRIP - 200;
        $bottom = !empty($d['price']) ? $badgeTop - 36 : $this->h - self::STRIP - 70;
        if (!empty($d['subtitle'])) {
            $sub = $this->fit($d['subtitle'], 'SemiBold', 40, 28, $this->w - 2 * self::M, 2);
            $bottom -= $sub['height'];
            $this->lines($sub, self::M, $bottom, $this->c['light']);
            $bottom -= 18;
        }
        $title = $this->fit($d['title'] ?? '', 'ExtraBold', 128, 64, $this->w - 2 * self::M, 3);
        $this->lines($title, self::M, max(self::M + 110, $bottom - $title['height']), $this->c['light']);
        if (!empty($d['price'])) {
            $this->priceBadge($d['price'], self::M, $badgeTop);
        }
    }

    private function layoutPriceList(array $d): void
    {
        $this->pill($d['label'] ?? 'QAYNOQ NARXLAR', self::M, self::M + 10);
        $title = $this->fit($d['title'] ?? 'Bu hafta qayerga uchamiz?', 'ExtraBold', 76, 48, $this->w - 2 * self::M, 2);
        $y = $this->lines($title, self::M, self::M + 130, $this->c['light']);

        $rows = array_slice($d['lines'] ?? [], 0, 9);
        $top = $y + 50;
        $rowH = min(92, (int) (($this->h - self::STRIP - 80 - $top) / max(1, count($rows))));
        $this->roundRect(self::M - 24, $top - 20, $this->w - self::M + 24, $top + $rowH * count($rows) + 10, 28, $this->alpha('light', 12));
        foreach ($rows as $i => $row) {
            [$place, $price] = array_pad(array_map('trim', explode('—', (string) $row, 2)), 2, '');
            $base = $top + $rowH * $i + (int) ($rowH * 0.62);
            $this->text($this->clean($place), 'SemiBold', 40, self::M + 10, $base, $this->c['light']);
            if ($price !== '') {
                $pw = $this->width($this->clean($price), 'ExtraBold', 42);
                $this->text($this->clean($price), 'ExtraBold', 42, $this->w - self::M - 10 - $pw, $base, $this->c['accent']);
            }
            if ($i < count($rows) - 1) {
                imagefilledrectangle($this->img, self::M + 10, $top + $rowH * ($i + 1), $this->w - self::M - 10, $top + $rowH * ($i + 1) + 1, $this->alpha('light', 30));
            }
        }
    }

    private function layoutReview(array $d): void
    {
        $this->pill($d['label'] ?? 'MIJOZIMIZ GAPIRADI', self::M, self::M + 10);
        $this->text('“', 'ExtraBold', 260, self::M - 10, 470, $this->c['accent']);
        $quote = $this->fit($d['quote'] ?? '', 'Bold', 58, 36, $this->w - 2 * self::M, 7);
        $y = $this->lines($quote, self::M, 520, $this->c['light']);
        if (!empty($d['author'])) {
            imagefilledrectangle($this->img, self::M, $y + 40, self::M + 70, $y + 46, $this->c['accent']);
            $this->text($this->clean($d['author']), 'SemiBold', 36, self::M + 90, $y + 58, $this->c['light']);
        }
    }

    private function layoutTips(array $d): void
    {
        $label = $d['label'] ?? 'SAQLAB QOʻYING';
        if ($label !== '') {
            $this->pill($label, self::M, self::M + 10);
        }
        $this->text((string) ($d['number'] ?? '1'), 'ExtraBold', 300, self::M - 12, 640, $this->c['accent']);
        $title = $this->fit($d['title'] ?? '', 'ExtraBold', 72, 44, $this->w - 2 * self::M, 3);
        $y = $this->lines($title, self::M, 720, $this->c['light']);
        if (!empty($d['text'])) {
            $body = $this->fit($d['text'], 'Medium', 40, 30, $this->w - 2 * self::M, 6);
            $room = $this->h - self::STRIP - 40 - ($y + 30); // pastki lentaga tegmasin
            while ($body['lines'] && $body['height'] > $room) {
                array_pop($body['lines']);
                $body['height'] = $body['lh'] * count($body['lines']);
            }
            $this->lines($body, self::M, $y + 30, $this->alpha('light', 110));
        }
        if (!empty($d['slide'])) {
            $this->slideCounter($d['slide']);
        }
    }

    private function layoutCompare(array $d): void
    {
        $mid = (int) ($this->w / 2);
        imagefilledrectangle($this->img, $mid - 2, 170, $mid + 1, $this->h - self::STRIP - 40, $this->alpha('light', 70));
        foreach ([['left', self::M], ['right', $mid + 40]] as [$side, $x]) {
            $name = $this->fit($d[$side] ?? '', 'ExtraBold', 84, 48, $mid - self::M - 40, 2);
            $this->lines($name, $x, 520, $this->c['light']);
            if (!empty($d[$side . '_price'])) {
                $this->text($this->clean($d[$side . '_price']), 'ExtraBold', 56, $x, 780, $this->c['accent']);
            }
            if (!empty($d[$side . '_note'])) {
                $note = $this->fit($d[$side . '_note'], 'Medium', 34, 26, $mid - self::M - 40, 4);
                $this->lines($note, $x, 830, $this->c['light']);
            }
        }
        imagefilledellipse($this->img, $mid, 360, 150, 150, $this->c['accent']);
        $this->text('VS', 'ExtraBold', 58, $mid - (int) ($this->width('VS', 'ExtraBold', 58) / 2), 385, $this->c['dark']);
        $q = $this->fit($d['title'] ?? 'Qaysi biri sizga mos?', 'Bold', 48, 34, $this->w - 2 * self::M, 2);
        $this->lines($q, self::M, self::M + 20, $this->c['light']);
    }

    private function layoutCover(array $d): void
    {
        $title = $this->fit($d['title'] ?? '', 'ExtraBold', 132, 70, $this->w - 2 * self::M, 4);
        $y = $this->lines($title, self::M, 300, $this->c['light']);
        if (!empty($d['subtitle'])) {
            // Plashka matnga moslanadi: sig'masa shrift kichrayadi, keyin 2 qatorga bo'linadi
            $sub = $this->fit($d['subtitle'], 'Bold', 44, 30, $this->w - 2 * self::M - 56, 2);
            $wide = max(array_map(fn ($l) => $this->width($l, 'Bold', $sub['size']), $sub['lines']));
            $this->roundRect(self::M, $y + 30, self::M + $wide + 56, $y + 30 + $sub['height'] + 34, 20, $this->c['accent']);
            $this->lines($sub, self::M + 28, $y + 47, $this->c['dark']);
        }
        if (!empty($d['price'])) {
            $this->priceBadge($d['price'], self::M, $this->h - self::STRIP - 230);
        }
    }

    private function layoutSlideCover(array $d): void
    {
        $this->pill($d['label'] ?? 'KARUSEL', self::M, self::M + 10);
        if (!empty($d['slide'])) {
            $this->slideCounter($d['slide']);
        }
        $title = $this->fit($d['title'] ?? '', 'ExtraBold', 110, 56, $this->w - 2 * self::M, 5);
        $y = max(self::M + 140, (int) (($this->h - self::STRIP) / 2 - $title['height'] / 2) - 40);
        $y = $this->lines($title, self::M, $y, $this->c['light']);
        if (!empty($d['subtitle'])) {
            $sub = $this->fit($d['subtitle'], 'SemiBold', 42, 28, $this->w - 2 * self::M, 2);
            $this->lines($sub, self::M, $y + 20, $this->c['accent']);
        }
        $swipe = 'Surib ko‘ring  →';
        $this->text($swipe, 'Bold', 36, $this->w - self::M - $this->width($swipe, 'Bold', 36), $this->h - self::STRIP - 60, $this->c['light']);
    }

    private function layoutSlideCta(array $d): void
    {
        if (!empty($d['slide'])) {
            $this->slideCounter($d['slide']);
        }
        $title = $this->fit($d['title'] ?? 'Tur tanlashda yordam kerakmi?', 'ExtraBold', 92, 52, $this->w - 2 * self::M, 4);
        $y = $this->lines($title, self::M, 300, $this->c['light']);
        if (!empty($d['text'])) {
            $body = $this->fit($d['text'], 'Medium', 42, 30, $this->w - 2 * self::M, 4);
            $y = $this->lines($body, self::M, $y + 30, $this->alpha('light', 115));
        }
        $button = $this->clean((string) ($d['button'] ?? '')) ?: (trim((string) preg_replace('/\s*\(.*?\)/u', '', (string) ($this->brand['phone'] ?? ''))) ?: "Direct'ga yozing");
        $bw = min($this->width($button, 'ExtraBold', 44) + 80, $this->w - 2 * self::M);
        $by = min($y + 70, $this->h - self::STRIP - 180);
        $this->roundRect(self::M, $by, self::M + $bw, $by + 110, 55, $this->c['accent']);
        $this->text($button, 'ExtraBold', 44, self::M + 40, $by + 72, $this->c['dark']);
        $save = 'Saqlab qo‘ying — kerak bo‘ladi';
        $this->text($save, 'SemiBold', 30, self::M, $this->h - self::STRIP - 60, $this->alpha('light', 100));
    }

    private function slideCounter(string $label): void
    {
        $label = $this->clean($label);
        $w = $this->width($label, 'Bold', 28);
        $this->roundRect($this->w - self::M - $w - 40, self::M + 10, $this->w - self::M, self::M + 74, 32, $this->alpha('light', 30));
        $this->text($label, 'Bold', 28, $this->w - self::M - $w - 20, self::M + 54, $this->c['light']);
    }

    // ==================== UMUMIY ELEMENTLAR ====================

    private function background(?string $photo, string $layout): void
    {
        $src = $photo && is_file($photo) ? @imagecreatefromstring((string) file_get_contents($photo)) : false;
        if ($src) {
            $sw = imagesx($src);
            $sh = imagesy($src);
            $scale = max($this->w / $sw, $this->h / $sh);
            $cw = (int) ($this->w / $scale);
            $ch = (int) ($this->h / $scale);
            imagecopyresampled($this->img, $src, 0, 0, (int) (($sw - $cw) / 2), (int) (($sh - $ch) / 2), $this->w, $this->h, $cw, $ch);
            // O'qilishi uchun: tepa va past qoraytiriladi
            $this->gradient(0, (int) ($this->h * 0.25), $this->c['dark'], 60, 127);
            $this->gradient((int) ($this->h * 0.35), $this->h, $this->c['dark'], 127, 25);
            return;
        }
        [$r1, $g1, $b1] = $this->hex($this->c['primary']);
        [$r2, $g2, $b2] = $this->hex($this->c['dark']);
        for ($y = 0; $y < $this->h; $y++) {
            $t = $y / $this->h;
            $col = imagecolorallocate($this->img, (int) ($r1 + ($r2 - $r1) * $t), (int) ($g1 + ($g2 - $g1) * $t), (int) ($b1 + ($b2 - $b1) * $t));
            imageline($this->img, 0, $y, $this->w, $y, $col);
        }
        // Brend naqshi: yumshoq doiralar (har tartibda boshqa joyda — grid bir xil, lekin zerikarli emas)
        $seed = crc32($layout);
        imagefilledellipse($this->img, (int) ($this->w * (0.75 + ($seed % 20) / 100)), (int) ($this->h * 0.18), 760, 760, $this->alpha('accent', 18));
        imagefilledellipse($this->img, (int) ($this->w * 0.05), (int) ($this->h * 0.72), 520, 520, $this->alpha('light', 8));
    }

    /** Pastki brend lentasi: telefon/CTA chapda, logo o'ngda — har rasmda bir xil. */
    private function strip(string $cta): void
    {
        $top = $this->h - self::STRIP;
        imagefilledrectangle($this->img, 0, $top, $this->w, $this->h, $this->c['primary']);
        imagefilledrectangle($this->img, 0, $top, $this->w, $top + 5, $this->c['accent']);
        // Telefondan "(call-markaz)" kabi izohni olib tashlaymiz; matn logoga urilmasligi uchun sig'diriladi
        $phone = trim((string) preg_replace('/\s*\(.*?\)/u', '', (string) ($this->brand['phone'] ?? '')));
        $main = $this->fit($this->clean($cta) ?: $phone, 'Bold', 34, 22, $this->w - 2 * self::M - 360, 1);
        $this->text($main['lines'][0], 'Bold', $main['size'], self::M, $top + 70, $this->c['light']);
        $sub = $this->clean((string) ($this->brand['instagram'] ?? ''));
        if ($sub !== '') {
            $this->text($sub, 'Medium', 26, self::M, $top + 115, $this->alpha('light', 90));
        }
        $this->logo($this->w - self::M, $top + (int) (self::STRIP / 2));
    }

    private function logo(int $right, int $centerY): void
    {
        $path = $this->logoWhitePath && is_file($this->logoWhitePath) ? $this->logoWhitePath : ($this->logoPath && is_file($this->logoPath) ? $this->logoPath : null);
        $logo = $path ? @imagecreatefromstring((string) file_get_contents($path)) : false;
        if ($logo) {
            $maxH = 96;
            $maxW = 320;
            $scale = min($maxH / imagesy($logo), $maxW / imagesx($logo));
            $lw = (int) (imagesx($logo) * $scale);
            $lh = (int) (imagesy($logo) * $scale);
            imagecopyresampled($this->img, $logo, $right - $lw, $centerY - (int) ($lh / 2), 0, 0, $lw, $lh, imagesx($logo), imagesy($logo));
            return;
        }
        // Logo yuklanmagan — so'z-belgi
        $name = mb_strtoupper(explode(' ', (string) ($this->brand['name'] ?? 'MARYAM'))[0]);
        $nw = $this->width($name, 'ExtraBold', 44);
        $this->text($name, 'ExtraBold', 44, $right - $nw, $centerY + 8, $this->c['accent']);
        $tag = 'TRAVEL AGENCY';
        $this->text($tag, 'SemiBold', 17, $right - $this->width($tag, 'SemiBold', 17), $centerY + 40, $this->c['light']);
    }

    private function pill(string $label, int $x, int $y): void
    {
        $label = mb_strtoupper($this->clean($label));
        $w = $this->width($label, 'Bold', 28);
        $this->roundRect($x, $y, $x + $w + 52, $y + 64, 32, $this->c['accent']);
        $this->text($label, 'Bold', 28, $x + 26, $y + 44, $this->c['dark']);
    }

    private function priceBadge(string $price, int $x, int $y): void
    {
        $price = $this->clean($price);
        $suffix = '';
        if (preg_match('/^(.*?)\s*(dan)$/iu', $price, $m)) {
            [$price, $suffix] = [$m[1], $m[2]];
        }
        $pw = $this->width($price, 'ExtraBold', 80);
        $sw = $suffix !== '' ? $this->width($suffix, 'Bold', 36) + 14 : 0;
        $this->roundRect($x, $y, $x + $pw + $sw + 64, $y + 132, 30, $this->c['accent']);
        $this->text($price, 'ExtraBold', 80, $x + 32, $y + 100, $this->c['dark']);
        if ($suffix !== '') {
            $this->text($suffix, 'Bold', 36, $x + 32 + $pw + 14, $y + 98, $this->c['dark']);
        }
    }

    // ==================== MATN ====================

    private function font(string $weight): string
    {
        return ROOT . "/resources/fonts/Montserrat-$weight.ttf";
    }

    /** Emoji shriftda yo'q; o'zbekcha ʻ ʼ — shriftdagi ‘ ’ belgilariga. */
    private function clean(string $s): string
    {
        $s = preg_replace('/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}]/u', '', $s) ?? $s;
        return trim(strtr($s, ['ʻ' => '‘', 'ʼ' => '’', "'" => '’']));
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

    /** Matnni kenglikka sig'dirib, eng katta mos shrift o'lchamini tanlaydi. */
    private function fit(string $s, string $weight, int $max, int $min, int $maxWidth, int $maxLines): array
    {
        $s = $this->clean($s);
        for ($size = $max; $size >= $min; $size -= 4) {
            $lines = $this->wrap($s, $weight, $size, $maxWidth);
            $widest = max(array_map(fn ($l) => $this->width($l, $weight, $size), $lines));
            if (count($lines) <= $maxLines && $widest <= $maxWidth) {
                break; // bitta uzun so'z ham chetdan chiqmasin
            }
        }
        $size = max($size, $min);
        $lines = array_slice($lines ?? [$s], 0, $maxLines);
        $lh = (int) round($size * 1.28);
        return ['lines' => $lines, 'size' => $size, 'weight' => $weight, 'lh' => $lh, 'height' => $lh * count($lines)];
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
        foreach ($block['lines'] as $i => $line) {
            $this->text($line, $block['weight'], $block['size'], $x, $y + $block['lh'] * ($i + 1) - (int) ($block['lh'] * 0.22), $color);
        }
        return $y + $block['height'];
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
        imagefilledrectangle($this->img, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($this->img, $x1, $y1 + $r, $x1 + $r - 1, $y2 - $r, $color);
        imagefilledrectangle($this->img, $x2 - $r + 1, $y1 + $r, $x2, $y2 - $r, $color);
        foreach ([[$x1 + $r - 1, $y1 + $r - 1, 180, 270], [$x2 - $r + 1, $y1 + $r - 1, 270, 360],
                  [$x1 + $r - 1, $y2 - $r + 1, 90, 180], [$x2 - $r + 1, $y2 - $r + 1, 0, 90]] as [$cx, $cy, $a1, $a2]) {
            imagefilledarc($this->img, $cx, $cy, $r * 2, $r * 2, $a1, $a2, $color, IMG_ARC_PIE);
        }
    }
}
