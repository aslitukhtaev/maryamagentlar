<?php

declare(strict_types=1);

namespace Maryam\Agents;

use Maryam\BrandAssets;
use Maryam\Brief;
use Maryam\DesignSystem;
use Maryam\Env;
use Maryam\Marketing;
use Maryam\Output;
use Maryam\PostRenderer;
use Maryam\Prompts;
use Maryam\Store;
use RuntimeException;
use Throwable;

/**
 * GRAPHIC DESIGNER AGENT — tayyor Instagram postini AI bilan chizadi.
 *
 *   1. Art-direktor (matn modeli) kompaniya gridini va fotolarni ko'rib, yozuvlarni va 4 xil konseptni tanlaydi
 *   2. Rasm modeli 4 ta variantni PARALLEL chizadi — grid skrinshotlari uslub namunasi, jamoa fotolari
 *      (tanlangan bo'lsa) haqiqiy odamlar sifatida beriladi; yozuvlar rasmning o'zida
 *   3. Kod haqiqiy logoni tepaga, telefon/Instagram'ni pastga qo'yadi (AI ularni buzib yozadi)
 *   4. Tekshiruvchi (ko'ra oladigan model) har rasmdagi yozuvni o'qib, xato bor-yo'qligini belgilaydi
 * Egasi eng yaxshisini tanlaydi yoki "Tuzatish" bilan o'zgartiradi (rasm modeli tahrirlaydi).
 * AI rasm chiza olmasa — eski shablon chizgich (PostRenderer) zaxira sifatida ishlaydi.
 */
final class GraphicDesigner
{
    public const NAME = 'designer';

    public function __construct(
        private object $ai,
        private Store $store,
        private array $brand,
        private array $tones,
    ) {
    }

    /**
     * Grid namunalarini ko'rib, kompaniya uslubi profilini chiqaradi (namuna yuklanganda chaqiriladi).
     * Natija meta'da saqlanadi va har bir dizaynda agentga beriladi.
     */
    public static function analyzeStyle(object $ai, Store $store): array
    {
        $refs = BrandAssets::refsForAi(6);
        if (!$refs) {
            $store->setMeta('brand_style', '');
            return [];
        }
        $data = Prompts::ask($ai, $store, 'designer/style', ['namunalar_soni' => count($refs)], 0.2, true, null, self::NAME, 'style', $refs);
        $style = [
            'photo_background' => (bool) ($data['photo_background'] ?? true),
            'uppercase_titles' => (bool) ($data['uppercase_titles'] ?? true),
            'bottom_strip' => (bool) ($data['bottom_strip'] ?? false),
            'title_position' => (string) ($data['title_position'] ?? ''),
            'text_density' => (string) ($data['text_density'] ?? ''),
            'mood' => (string) ($data['mood'] ?? ''),
            'preferred_layouts' => array_values(array_intersect((array) ($data['preferred_layouts'] ?? []), array_keys(PostRenderer::LAYOUTS))),
            'recurring_elements' => array_values(array_map('strval', (array) ($data['recurring_elements'] ?? []))),
            'notes' => (string) ($data['notes'] ?? ''),
        ];
        $store->setMeta('brand_style', (string) json_encode($style, JSON_UNESCAPED_UNICODE));
        return $style;
    }

    public static function style(Store $store): array
    {
        return json_decode((string) $store->meta('brand_style'), true) ?: [];
    }

    /**
     * @param array $brief           Brief::normalize() natijasi ('id' bilan)
     * @param array $strategyContext ixtiyoriy: Copywriter strategiyasidan big_idea/key_message
     * @param array $options         progress, template_id, variant (Copywriter varianti), text (egasining g'oyasi),
     *                               format (post|karusel|reels|reklama), photos (jamoa fotolari nomlari), kind
     */
    public function run(array $brief, array $strategyContext = [], array $options = []): array
    {
        $say = $options['progress'] ?? static fn (string $m) => null;
        $briefId = $brief['id'] ?? $this->store->saveBrief($brief);
        $template = isset($options['template_id']) ? $this->store->row('templates', (int) $options['template_id']) : null;
        $format = (string) (($options['format'] ?? '') ?: ($options['variant']['format'] ?? '') ?: ($template['format'] ?? '') ?: 'post');

        $context = [
            'brief' => Brief::forPrompt($brief, $this->tones),
            'brand' => Marketing::brand($this->brand, $this->store),
            'tone_profile' => $this->tones[$brief['tourism_type']] ?? [],
            'big_idea' => $strategyContext['big_idea'] ?? '',
            'key_message' => $strategyContext['key_message'] ?? '',
            'deliverable_format' => $format,
        ] + Marketing::training($this->store, self::NAME, $template ? (int) $template['id'] : null);
        if (!empty($options['variant'])) {
            $v = $options['variant'];
            $context['copy'] = array_filter([
                'hook' => $v['hook'] ?? '', 'body' => mb_substr((string) ($v['body'] ?? ''), 0, 900), 'cta' => $v['cta'] ?? '',
                'headline' => $v['headline'] ?? '', 'visual_idea' => $v['visual'] ?? '', 'format' => $v['format'] ?? '',
            ]);
        }
        if (!empty($options['text'])) {
            $context['copy'] = ['text' => (string) $options['text']];
        }
        // Rasm modeliga: uslub namunalari (qanday dizayn kerak) + jamoa fotolari. O'z gridi faqat art-direktorga
        // (mazmunni tushunish uchun) — sifati past bo'lsa, uslubni buzmasin.
        $refs = BrandAssets::insposForAi(2);
        $grid = BrandAssets::refsForAi(2);
        $photos = BrandAssets::photosForAi((array) ($options['photos'] ?? []));
        $system = DesignSystem::get($this->store);
        $context['design_system'] = ['qisqacha' => $system['summary'], 'tizim' => DesignSystem::artDirection($this->store)];
        $context['style_samples'] = count($refs);
        $context['company_grid_images'] = count($grid);
        $context['user_photos'] = count($photos);
        if ($style = self::style($this->store)) {
            $context['brand_style'] = $style;
        }

        $say('1/4 Art-direktor: yozuvlar va konseptlar tanlanmoqda...');
        $plan = self::normalizePlan(Prompts::ask($this->ai, $this->store, 'designer/poster', $context, 0.9, true, $briefId, self::NAME, 'poster', [...$refs, ...$grid, ...$photos]));
        if ($plan['headline'] === '') {
            $plan['headline'] = mb_strimwidth((string) (($options['variant']['hook'] ?? '') ?: $brief['topic']), 0, 40, '');
        }

        $variantId = (int) ($options['variant']['db_id'] ?? 0);
        $kind = (string) ($options['kind'] ?? ($variantId ? "variant_$variantId" : 'final'));
        $result = [
            'brief_id' => $briefId, 'format' => $format, 'engine' => 'ai',
            'headline' => $plan['headline'], 'subline' => $plan['subline'], 'price' => $plan['price'], 'design_system' => $system['source'],
            'variants' => [], 'chosen' => null, 'slides' => [], 'slide_meta' => [], 'zip_path' => null,
            'card_path' => null, 'card_layout' => '', 'alt_text' => $plan['alt_text'],
            'image_prompt' => $plan['concepts'][0]['prompt'] ?? '',
            'layout' => array_map(static fn ($c) => $c['name'] . ': ' . $c['prompt'], $plan['concepts']),
            'photos' => array_values((array) ($options['photos'] ?? [])),
            'options' => array_filter(['format' => $format, 'text' => (string) ($options['text'] ?? ''), 'photos' => array_values((array) ($options['photos'] ?? [])),
                'template_id' => $options['template_id'] ?? null, 'variant_id' => $variantId ?: null, 'kind' => $kind]),
            'image_path' => null, 'image_generated' => false, 'ai_error' => '',
        ];
        // Kadrlar: post/stories/reklama — 1 ta, karusel — N ta slayd. Hammasi bir xil jarayondan o'tadi.
        $result['frames'] = $this->frames($plan, $result, $options, $context, [...$refs, ...$grid, ...$photos], $briefId);

        try {
            if (!method_exists($this->ai, 'generateImages')) {
                throw new RuntimeException('AI rasm chizish ulanmagan');
            }
            $result = $this->designLead($result, $plan, $refs, $photos, $briefId, $say);
            if (count($result['frames']) > 1) {
                $result = $this->designRest($result, $refs, $photos, $briefId, $say);
            }
        } catch (Throwable $e) {
            $result['ai_error'] = $e->getMessage();
            if (Env::get('DESIGN_TEMPLATE_FALLBACK', '0') === '1') {
                $result = $this->templateFallback($result, $plan, $options, $brief);
            } else {
                $result['engine'] = 'failed'; // oddiy shablon chizilmaydi — "Qayta urinish" tugmasi ko'rsatiladi
                $say("AI rasm chiza olmadi: {$e->getMessage()}");
            }
        }

        $result['result_id'] = $this->store->saveResult($briefId, self::NAME, $kind, $result);
        return $result;
    }

    /** Oldingi buyurtmani xuddi shu sozlamalar bilan qayta ishga tushiradi ("Qayta urinish"). */
    public function retry(int $resultId, array $options = []): array
    {
        $d = $this->store->resultById($resultId) ?? throw new RuntimeException('Dizayn topilmadi.');
        $brief = $this->store->brief((int) $d['brief_id']) ?? throw new RuntimeException('Brif topilmadi.');
        $o = (array) ($d['options'] ?? []);
        if (!empty($o['variant_id'])) {
            $o['variant'] = $this->store->variant((int) $o['variant_id']);
        }
        return $this->run($brief, [], $options + $o + ['format' => $d['format'] ?? 'post']);
    }

    // ==================== YAGONA JARAYON: KADRLAR → 4 VARIANT → TEKSHIRUV → TUZATISH ====================

    /**
     * Har format kadrlar ro'yxatiga aylanadi: {texts, scene, cta, role}.
     * Karuselda slaydlar: egasining "1-slayd: ..." matni → art-direktor slaydlari → (bo'lmasa) qayta so'raladi.
     */
    private function frames(array $plan, array $result, array $options, array $context, array $images, int $briefId): array
    {
        if ($result['format'] !== 'karusel') {
            return [['texts' => self::texts($plan), 'scene' => '', 'cta' => $this->ctaText($result), 'role' => '']];
        }
        $slides = $plan['slides'];
        $own = PostRenderer::slidesFromText((string) (($options['text'] ?? '') ?: ($options['variant']['visual'] ?? '')));
        if (count($own) >= 2 && (!empty($options['text']) || count($slides) < 2)) {
            $slides = array_map(static fn ($s, $i) => [
                'headline' => (string) $s['title'], 'subline' => self::shortLine((string) ($s['text'] ?? '')),
                'kicker' => $plan['slides'][$i]['kicker'] ?? '', 'accent' => '', 'prompt' => $plan['slides'][$i]['prompt'] ?? '',
            ], $own, array_keys($own));
        }
        if (count($slides) < 2) {
            // Art-direktor slaydlarni bermadi — aniq talab bilan qayta so'raymiz (oddiy shablonga tushmaslik uchun)
            $again = self::normalizePlan(Prompts::ask($this->ai, $this->store, 'designer/poster',
                $context + ['majburiy' => "Bu KARUSEL: \"slides\" massivida 4-7 ta slayd bo'lishi SHART."], 0.7, true, $briefId, self::NAME, 'poster_slides', $images));
            $slides = $again['slides'];
        }
        if (count($slides) < 2) {
            $slides = [
                ['headline' => $plan['headline'], 'subline' => $plan['subline'], 'kicker' => $plan['kicker'], 'accent' => $plan['accent'], 'prompt' => ''],
                ['headline' => "DIRECT'GA YOZING", 'subline' => $plan['price'] !== '' ? $plan['price'] : '', 'kicker' => '', 'accent' => 'DIRECT', 'prompt' => 'call to action'],
            ];
        }
        $slides = array_slice($slides, 0, 10);
        $n = count($slides);
        $frames = [];
        foreach ($slides as $i => $s) {
            $frames[] = [
                'texts' => self::texts($s),
                'scene' => (string) ($s['prompt'] ?? ''),
                'cta' => $i === 0 ? 'Surib ko‘ring →' : ($i === $n - 1 ? ($this->phone() ?: "Direct'ga yozing") : ''),
                'role' => 'Slide ' . ($i + 1) . " of $n of one Instagram carousel — "
                    . ($i === 0 ? 'the COVER: a strong hook that makes people swipe' : ($i === $n - 1 ? 'the LAST slide: call to action' : 'a content slide: one idea, clear and bold')) . '.',
            ];
        }
        return $frames;
    }

    /** 1-kadr (post yoki karusel muqovasi) — 4 xil konseptda parallel, tekshiruv va avtomatik tuzatish bilan. */
    private function designLead(array $result, array $plan, array $refs, array $photos, int $briefId, callable $say): array
    {
        $frame = $result['frames'][0];
        $n = max(1, min(4, (int) Env::get('DESIGN_VARIANTS', '4')));
        $concepts = $plan['concepts'] ?: [['name' => 'Asosiy', 'prompt' => 'Bold hero composition following the design system']];
        $jobs = [];
        for ($i = 0; $i < $n; $i++) {
            $c = $concepts[$i % count($concepts)];
            $scene = trim($frame['role'] . ' ' . $c['prompt'] . ($frame['scene'] !== '' ? ' This frame: ' . $frame['scene'] : ''));
            $jobs[] = ['prompt' => $this->posterPrompt($frame['texts'], $scene, $result['format'], count($refs), count($photos)),
                       'images' => [...$refs, ...$photos], 'aspect' => $result['format'] === 'reels' ? '9:16' : '4:5', 'name' => $c['name']];
        }
        $what = count($result['frames']) > 1 ? 'muqova' : 'variant';
        $say("2/4 AI $n ta $what chizmoqda (odatda 30-90 soniya)...");
        $errors = [];
        foreach ($this->ai->generateImages($jobs) as $i => $img) {
            if (is_string($img)) {
                $errors[] = $img;
                continue;
            }
            $result['variants'][] = $this->saveImage($img, $result, $jobs[$i]['name'], $frame['texts'], $briefId, $frame['cta']);
        }
        if (!$result['variants']) {
            throw new RuntimeException($errors[0] ?? 'rasm qaytmadi');
        }
        $say('3/4 Yozuvlar tekshirilmoqda...');
        $result['variants'] = $this->autoFix($this->checkTexts($result['variants'], $briefId), $result, $briefId, $say);
        // Yozuvi to'g'ri chiqqanlar birinchi
        usort($result['variants'], static fn ($a, $b) => (int) (($b['check']['ok'] ?? true) === true) <=> (int) (($a['check']['ok'] ?? true) === true));
        $result['card_path'] = $result['variants'][0]['path'];
        $result['card_layout'] = count($result['frames']) > 1 ? 'carousel' : 'ai';
        if (count($result['frames']) > 1) {
            $result['chosen'] = 0; // karuselda eng yaxshi muqova avtomatik tanlanadi (egasi boshqasini tanlasa — slaydlar qayta chiziladi)
        }
        $result['image_generated'] = true;
        return $result;
    }

    /**
     * Karuselning qolgan slaydlari — tanlangan muqova uslubida (muqova rasmi namuna sifatida beriladi),
     * xuddi shu dizayn tizimi, tekshiruv va avtomatik tuzatish bilan.
     */
    private function designRest(array $result, array $refs, array $photos, int $briefId, callable $say): array
    {
        $cover = $result['variants'][(int) ($result['chosen'] ?? 0)];
        $anchor = ['mime' => str_ends_with($cover['raw'], '.png') ? 'image/png' : 'image/jpeg', 'data' => base64_encode((string) file_get_contents($cover['raw']))];
        $frames = $result['frames'];
        $jobs = [];
        foreach (array_slice($frames, 1, null, true) as $i => $f) {
            $scene = trim($f['role'] . ($f['scene'] !== '' ? ' This slide: ' . $f['scene'] : '')
                . ' The LAST attached image is the cover of this same carousel: keep its exact visual system — typography, colours, hero treatment, graphic accents, lighting — so every slide looks like one series, while the composition fits this slide.');
            $jobs[$i] = ['prompt' => $this->posterPrompt($f['texts'], $scene, 'karusel', count($refs), count($photos)),
                         'images' => [...$refs, ...$photos, $anchor], 'aspect' => '4:5'];
        }
        $say('4/4 Qolgan ' . count($jobs) . ' ta slayd muqova uslubida chizilmoqda...');
        $items = [0 => $cover];
        foreach ($this->ai->generateImages($jobs) as $i => $img) {
            if (is_string($img)) {
                throw new RuntimeException(($i + 1) . "-slayd chizilmadi: $img");
            }
            $items[$i] = $this->saveImage($img, $result, ($i + 1) . '-slayd', $frames[$i]['texts'], $briefId, $frames[$i]['cta']);
        }
        ksort($items);
        $rest = $this->autoFix($this->checkTexts(array_slice($items, 1, null, true), $briefId), $result, $briefId, $say);
        $items = [0 => $cover] + $rest;
        ksort($items);
        $result['slide_meta'] = array_values($items);
        $result['slides'] = array_column($result['slide_meta'], 'path');
        $result['card_path'] = $result['slides'][0];
        $result['zip_path'] = self::zip($result['slides'], Output::dir(['id' => $briefId, 'topic' => $this->topic($briefId)]) . '/karusel-' . bin2hex(random_bytes(3)) . '.zip');
        return $result;
    }

    /**
     * Rasm modeli uchun yakuniy topshiriq (ingliz tilida — rasm modellari shunda eng yaxshi ishlaydi).
     * @param array<string, string> $texts rasmga yoziladigan matnlar (bo'shlari tashlanadi)
     */
    private function posterPrompt(array $texts, string $concept, string $format, int $nRefs, int $nPhotos): string
    {
        $pal = DesignSystem::palette($this->store);
        $canvas = $format === 'reels' ? 'vertical 9:16 Instagram Stories/Reels cover' : 'vertical 4:5 Instagram feed post';
        $p = "Design a finished $canvas for \"Maryam Travel\", a travel agency in Uzbekistan, at the level of a top creative agency's portfolio — bold, precise, strongly arranged, premium.\n";
        if ($nRefs) {
            $p .= "STYLE REFERENCE: the first $nRefs attached image(s) show the exact design level and system we want. Replicate their art direction — typography scale and hierarchy, layering of the cut-out subject with the headline, graphic accents, lighting, composition and finish — but with our own topic, texts and colours. Do NOT copy their words, logos or people.\n";
        }
        if ($nPhotos) {
            $p .= "PEOPLE: the next $nPhotos attached photo(s) show real people from our team/clients. Use these exact people as the hero cut-out — keep face, identity and skin tone unchanged, realistic, with matching light.\n";
        }
        $p .= "DESIGN SYSTEM (follow strictly — every post of this brand must look like part of one series):\n" . DesignSystem::artDirection($this->store) . "\n";
        $p .= "Palette: dark {$pal['dark']}, accent {$pal['accent']}, white. No other colours in graphics or type.\n";
        $p .= "THIS POST: $concept\n";
        $p .= "TEXT — render exactly these texts, spelled letter-for-letter in Uzbek Latin script (keep the apostrophe in O‘ and G‘), and no other words:\n";
        foreach ($texts as $label => $t) {
            if ($label !== 'Accent word') {
                $p .= "- $label: \"" . trim((string) $t) . "\"\n";
            }
        }
        if (!empty($texts['Accent word'])) {
            $p .= "The key word to set enormous in the accent colour: \"{$texts['Accent word']}\".\n";
        }
        $p .= "RULES: text perfectly sharp and correctly spelled; nothing cut off by the frame. Do NOT write the brand name, a logo, Instagram handle, phone number, website, buttons or \"swipe\". "
            . "Leave the top-centre area (about 30% of the width × 9% of the height) and a bottom-centre strip (about 50% × 9%) empty of text and faces — our logo and a call-to-action button are placed there afterwards, matching the style.";
        return $p;
    }

    /** @return array{path: string, raw: string, concept: string, texts: array, model: string, check: ?array} */
    private function saveImage(array $img, array $result, string $name, array $texts, int $briefId, ?string $cta = null): array
    {
        $dir = Output::dir(['id' => $briefId, 'topic' => $this->topic($briefId)]);
        $id = bin2hex(random_bytes(4));
        $bytes = (string) base64_decode($img['base64']);
        $raw = "$dir/ai-$id-raw." . (str_contains($img['mime_type'], 'png') ? 'png' : 'jpg');
        file_put_contents($raw, $bytes);
        $path = "$dir/ai-$id.jpg";
        $renderer = PostRenderer::forBrand($this->brand, self::style($this->store));
        $cta ??= $this->ctaText($result);
        $pal = DesignSystem::palette($this->store);
        file_put_contents($path, $renderer->finishPoster($bytes, $result['format'], $cta, ['primary' => $pal['dark'], 'dark' => $pal['dark'], 'accent' => $pal['accent']]));
        return ['path' => $path, 'raw' => $raw, 'concept' => $name, 'texts' => $texts, 'cta' => $cta, 'model' => (string) ($img['model_used'] ?? ''), 'check' => null];
    }

    /** Ko'ra oladigan model har rasmdagi yozuvni o'qiydi va kutilgan matn bilan solishtiradi. */
    private function checkTexts(array $items, int $briefId): array
    {
        $images = [];
        $expected = [];
        foreach ($items as $i => $item) {
            $im = @imagecreatefromjpeg($item['path']);
            if (!$im) {
                continue;
            }
            $small = imagescale($im, 640);
            ob_start();
            imagejpeg($small, null, 82);
            $images[] = ['mime' => 'image/jpeg', 'data' => base64_encode((string) ob_get_clean())];
            $expected[] = ['index' => count($images) - 1, 'item' => $i, 'expected' => array_values(array_filter($item['texts']))];
        }
        try {
            $data = Prompts::ask($this->ai, $this->store, 'designer/check',
                ['expected' => array_map(static fn ($e) => ['index' => $e['index'], 'expected' => $e['expected']], $expected)],
                0.1, true, $briefId, self::NAME, 'check', $images);
        } catch (Throwable) {
            return $items; // tekshiruv ishlamasa — rasmlar baribir ko'rsatiladi
        }
        foreach ((array) ($data['results'] ?? []) as $r) {
            $e = $expected[(int) ($r['index'] ?? -1)] ?? null;
            if ($e !== null) {
                $items[$e['item']]['check'] = [
                    'ok' => (bool) ($r['ok'] ?? true), 'found' => (string) ($r['found'] ?? ''),
                    'issues' => (string) ($r['issues'] ?? ''), 'fix' => (string) ($r['fix'] ?? ''),
                ];
            }
        }
        return $items;
    }

    /**
     * Yozuvida xato topilgan rasmlar egasiga ko'rsatilishidan oldin bir marta avtomatik tuzatiladi
     * (rasm modeli faqat yozuvni to'g'rilaydi). Tuzatilgani ham xato bo'lsa — yaxshirog'i qoladi.
     */
    private function autoFix(array $items, array $result, int $briefId, callable $say): array
    {
        $bad = array_filter($items, static fn ($it) => ($it['check']['ok'] ?? true) === false && is_file((string) ($it['raw'] ?? '')));
        if (!$bad || Env::get('DESIGN_AUTOFIX', '1') === '0') {
            return $items;
        }
        $say('3/4 ' . count($bad) . " ta rasmda yozuv xatosi — AI o'zi tuzatmoqda...");
        $jobs = [];
        foreach ($bad as $i => $it) {
            $jobs[$i] = $this->editJob($it, (string) (($it['check']['fix'] ?? '') ?: 'Fix the spelling of all text on the image'), $result['format']);
        }
        $fixed = [];
        foreach ($this->ai->generateImages($jobs) as $i => $img) {
            if (is_array($img)) {
                $fixed[$i] = $this->saveImage($img, $result, $items[$i]['concept'], $items[$i]['texts'], $briefId, $items[$i]['cta'] ?? null);
            }
        }
        if (!$fixed) {
            return $items;
        }
        $checked = $this->checkTexts(array_values($fixed), $briefId);
        foreach (array_keys($fixed) as $n => $i) {
            $items[$i] = $checked[$n] + ['autofixed' => true];
        }
        return $items;
    }

    /** Rasmni tahrirlash topshirig'i (tuzatish uchun): asl rasm + faqat aytilgan o'zgarish. */
    private function editJob(array $item, string $instruction, string $format): array
    {
        $texts = array_filter($item['texts'] ?? []);
        $prompt = "Edit the attached image, a finished Instagram post. Apply ONLY this change (the request may be in Uzbek): \"$instruction\". "
            . "Keep everything else identical — composition, people and faces, colours, fonts and all other text. "
            . ($texts ? 'All text on the image must be spelled exactly: ' . implode(' | ', array_map(static fn ($t) => "\"$t\"", $texts)) . '. ' : '')
            . 'Do not add a logo, brand name, phone number, website or watermark.';
        $raw = (string) file_get_contents($item['raw']);
        return ['prompt' => $prompt, 'aspect' => $format === 'reels' ? '9:16' : '4:5',
                'images' => [['mime' => str_ends_with($item['raw'], '.png') ? 'image/png' : 'image/jpeg', 'data' => base64_encode($raw)]]];
    }

    // ==================== TANLASH VA TUZATISH ====================

    /** Variantni tanlash. Karuselda boshqa muqova tanlansa — qolgan slaydlar shu muqova uslubida qayta chiziladi. */
    public function choose(int $resultId, int $index, ?callable $say = null): array
    {
        $d = $this->store->resultById($resultId) ?? throw new RuntimeException('Dizayn topilmadi.');
        if (!isset($d['variants'][$index])) {
            throw new RuntimeException('Variant topilmadi.');
        }
        $changed = (int) ($d['chosen'] ?? -1) !== $index;
        $d['chosen'] = $index;
        $d['card_path'] = $d['variants'][$index]['path'];
        if ($changed && count($d['frames'] ?? []) > 1) {
            $d = $this->designRest($d, BrandAssets::insposForAi(2), BrandAssets::photosForAi((array) ($d['photos'] ?? [])),
                (int) $d['brief_id'], $say ?? static fn (string $m) => null);
        }
        $this->store->updateResult($resultId, $d);
        return $d;
    }

    /**
     * Rasm modeli tanlangan rasmni tahrirlaydi ("narxni kattaroq qil", "ISTANBUL so'zini to'g'rila").
     * $target: 'v' — variant (post yoki karusel muqovasi), 's' — karusel slaydi.
     * Variant: yangi variant qo'shiladi va tanlanadi (karuselda muqova ham almashadi). Slayd: o'rnida almashadi.
     */
    public function fix(int $resultId, int $index, string $instruction, string $target = 'v'): array
    {
        $d = $this->store->resultById($resultId) ?? throw new RuntimeException('Dizayn topilmadi.');
        $slide = $target === 's' && !empty($d['slide_meta']);
        $item = $slide ? ($d['slide_meta'][$index] ?? null) : ($d['variants'][$index] ?? null);
        if (!$item || !is_file((string) ($item['raw'] ?? ''))) {
            throw new RuntimeException('Bu rasmni tuzatib bo\'lmaydi (asl nusxasi yo\'q).');
        }
        $instruction = trim($instruction) ?: (string) ($item['check']['fix'] ?? '');
        if ($instruction === '') {
            throw new \InvalidArgumentException('Nimani o\'zgartirish kerakligini yozing.');
        }
        $img = $this->ai->generateImages([$this->editJob($item, $instruction, $d['format'])])[0];
        if (is_string($img)) {
            throw new RuntimeException('AI tuzata olmadi: ' . $img);
        }
        $name = $slide ? $item['concept'] : 'Tuzatilgan: ' . mb_strimwidth($instruction, 0, 40, '…');
        $new = $this->checkTexts([$this->saveImage($img, $d, $name, $item['texts'] ?? [], (int) $d['brief_id'], $item['cta'] ?? null)], (int) $d['brief_id'])[0];
        if ($slide) {
            $d['slide_meta'][$index] = $new;
            $d['slides'][$index] = $new['path'];
        } else {
            $wasCover = !empty($d['slide_meta']) && (int) ($d['chosen'] ?? 0) === $index;
            $d['variants'][] = $new;
            $d['chosen'] = count($d['variants']) - 1;
            $d['card_path'] = $new['path'];
            if ($wasCover) { // tuzatilgan muqova — uslub o'sha, qolgan slaydlar qayta chizilmaydi
                $d['slide_meta'][0] = $new;
                $d['slides'][0] = $new['path'];
            } elseif (!empty($d['slide_meta'])) {
                $d = $this->designRest($d, BrandAssets::insposForAi(2), BrandAssets::photosForAi((array) ($d['photos'] ?? [])), (int) $d['brief_id'], static fn (string $m) => null);
            }
        }
        if (!empty($d['slides'])) {
            $d['card_path'] = $d['slides'][0];
            $d['zip_path'] = self::zip($d['slides'], Output::dir(['id' => $d['brief_id'], 'topic' => $this->topic((int) $d['brief_id'])]) . '/karusel-' . bin2hex(random_bytes(3)) . '.zip') ?? ($d['zip_path'] ?? null);
        }
        $this->store->updateResult($resultId, $d);
        return $d;
    }

    // ==================== ZAXIRA: SHABLON CHIZGICH ====================

    /** AI rasm chiza olmasa — yozuvlar bilan brend shabloni (jamoa fotosi bo'lsa — fon sifatida). */
    private function templateFallback(array $result, array $plan, array $options, array $brief): array
    {
        $renderer = PostRenderer::forBrand($this->brand, self::style($this->store));
        $colors = PostRenderer::colors($this->store);
        $dir = Output::dir($brief);
        $id = bin2hex(random_bytes(3));
        $bg = null;
        foreach ((array) ($options['photos'] ?? []) as $name) {
            $bg ??= BrandAssets::photoPath((string) $name);
        }
        try {
            if ($result['format'] === 'karusel') {
                $own = PostRenderer::slidesFromText((string) ($options['text'] ?? ''), '');
                $slides = count($own) >= 2 ? $own : [];
                if (!$slides) {
                    $last = count($plan['slides']) - 1;
                    foreach ($plan['slides'] as $i => $s) {
                        $slides[] = match (true) {
                            $i === 0 => ['layout' => 'slide_cover', 'title' => $s['headline'], 'subtitle' => $s['subline']],
                            $i === $last => ['layout' => 'slide_cta', 'title' => $s['headline'], 'text' => $s['subline']],
                            default => ['layout' => 'tips', 'title' => $s['headline'], 'text' => $s['subline']],
                        };
                    }
                }
                if (count($slides) >= 2) {
                    foreach ($slides as $k => $slide) {
                        $slides[$k]['bg'] ??= $bg;
                    }
                    foreach ($renderer->renderCarousel(array_slice($slides, 0, 10), $colors) as $i => $png) {
                        $result['slides'][] = $path = "$dir/post-$id-s" . ($i + 1) . '.png';
                        file_put_contents($path, $png);
                    }
                    $result['card_path'] = $result['slides'][0];
                    $result['card_layout'] = 'carousel';
                    $result['zip_path'] = self::zip($result['slides'], "$dir/karusel-$id.zip");
                }
            }
            if (!$result['card_path']) {
                $layout = $result['format'] === 'reels' ? 'cover' : ($plan['price'] !== '' ? 'hot_tour' : 'slide_cover');
                $card = ['title' => $plan['headline'], 'subtitle' => $plan['subline'], 'price' => $plan['price'], 'label' => $plan['badge'], 'bg' => $bg];
                $result['card_path'] = "$dir/post-$id.png";
                file_put_contents($result['card_path'], $renderer->render($layout, $card, $colors));
                $result['card_layout'] = $layout;
            }
        } catch (Throwable) {
            // chizib bo'lmadi — tafsilotlar (konseptlar) baribir ko'rsatiladi
        }
        $result['engine'] = 'template';
        return $result;
    }

    // ==================== YORDAMCHI ====================

    private static function normalizePlan(array $data): array
    {
        $str = static fn ($v) => trim(preg_replace('/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}\x{FE0F}]/u', '', (string) $v));
        $concepts = [];
        foreach ((array) ($data['concepts'] ?? []) as $c) {
            if (is_array($c) && trim((string) ($c['prompt'] ?? '')) !== '') {
                $concepts[] = ['name' => $str($c['name'] ?? '') ?: 'Variant', 'prompt' => trim((string) $c['prompt'])];
            }
        }
        $slides = [];
        foreach ((array) ($data['slides'] ?? []) as $s) {
            if (is_array($s) && $str($s['headline'] ?? '') !== '') {
                $slides[] = ['headline' => $str($s['headline']), 'subline' => $str($s['subline'] ?? ''), 'kicker' => self::shortLine($str($s['kicker'] ?? '')),
                             'accent' => $str($s['accent'] ?? ''), 'prompt' => trim((string) ($s['prompt'] ?? ''))];
            }
        }
        foreach ($slides as $k => $sl) {
            $slides[$k]['subline'] = self::shortLine($sl['subline']);
        }
        return [
            'headline' => $str($data['headline'] ?? ''), 'subline' => self::shortLine($str($data['subline'] ?? '')),
            'price' => $str($data['price'] ?? ''), 'badge' => $str($data['badge'] ?? ''),
            'kicker' => self::shortLine($str($data['kicker'] ?? '')), 'accent' => $str($data['accent'] ?? ''),
            'concepts' => $concepts, 'slides' => $slides, 'alt_text' => trim((string) ($data['alt_text'] ?? '')),
        ];
    }

    /** AI uzun matnni buzib yozadi: qo'shimcha qator 7 so'zdan oshmasin (birinchi bo'lagi olinadi, bo'lmasa tashlanadi). */
    private static function shortLine(string $line): string
    {
        $words = static fn ($t) => count(preg_split('/\s+/u', trim($t), -1, PREG_SPLIT_NO_EMPTY));
        if ($words($line) <= 7) {
            return $line;
        }
        $first = trim((string) preg_split('/\s*[·—–,.;:!?]\s*|\s+vs\s+/u', $line)[0]);
        return $words($first) <= 7 && $words($first) >= 2 ? $first : '';
    }

    /** @return array<string, string> rasmga yoziladigan matnlar */
    private static function texts(array $p): array
    {
        $accent = (string) ($p['accent'] ?? '');
        // Professional postda yozuv kam: sarlavha + ko'pi bilan 2 ta qo'shimcha (narx muhimroq)
        $extra = array_slice(array_filter([
            'Price badge' => $p['price'] ?? '', 'Kicker (small line above the headline)' => $p['kicker'] ?? '',
            'Sub-line' => $p['subline'] ?? '', 'Small badge' => $p['badge'] ?? '',
        ], static fn ($t) => $t !== ''), 0, 2, true);
        return array_filter([
            'Headline' => $p['headline'] ?? '',
            // Urg'u so'zi sarlavhaning ichida bo'lsagina (aks holda ortiqcha so'z yozilib qoladi)
            'Accent word' => $accent !== '' && mb_stripos((string) ($p['headline'] ?? ''), $accent) !== false ? $accent : '',
        ] + $extra, static fn ($t) => $t !== '');
    }

    /** Pastki tugma matni: sotuv posti — telefon, boshqasi — "Batafsil izohda" (seriya bir xil ko'rinsin). */
    private function ctaText(array $result): string
    {
        $sales = ($result['price'] ?? '') !== '' || ($result['format'] ?? '') === 'reklama';
        return $sales && $this->phone() !== '' ? $this->phone() : 'Batafsil izohda';
    }

    private function phone(): string
    {
        return trim((string) preg_replace('/\s*\(.*?\)/u', '', (string) ($this->brand['phone'] ?? '')));
    }

    private function topic(int $briefId): string
    {
        return (string) ($this->store->brief($briefId)['topic'] ?? 'dizayn');
    }

    /** Slaydlarni bitta ZIP ga (bir bosishda yuklab olish uchun). */
    private static function zip(array $files, string $zipPath): ?string
    {
        if (!class_exists('ZipArchive')) {
            return null;
        }
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return null;
        }
        foreach ($files as $i => $f) {
            $zip->addFile($f, sprintf('slayd-%02d.', $i + 1) . pathinfo($f, PATHINFO_EXTENSION));
        }
        $zip->close();
        return $zipPath;
    }
}
