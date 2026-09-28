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

        $say('1/3 Art-direktor: yozuvlar va konseptlar tanlanmoqda...');
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
            'image_path' => null, 'image_generated' => false, 'ai_error' => '',
        ];

        try {
            if (!method_exists($this->ai, 'generateImages')) {
                throw new RuntimeException('AI rasm chizish ulanmagan');
            }
            $result = $format === 'karusel'
                ? $this->aiCarousel($result, $plan, $options, $refs, $photos, $briefId, $say)
                : $this->aiVariants($result, $plan, $refs, $photos, $briefId, $say);
        } catch (Throwable $e) {
            $say("AI rasm chiza olmadi — zaxira shablon ishlatiladi ({$e->getMessage()})");
            $result = $this->templateFallback($result, $plan, $options, $brief);
            $result['ai_error'] = $e->getMessage();
        }

        $result['result_id'] = $this->store->saveResult($briefId, self::NAME, $kind, $result);
        return $result;
    }

    // ==================== AI CHIZISH ====================

    private function aiVariants(array $result, array $plan, array $refs, array $photos, int $briefId, callable $say): array
    {
        $n = max(1, min(4, (int) Env::get('DESIGN_VARIANTS', '4')));
        $concepts = $plan['concepts'] ?: [['name' => 'Asosiy', 'prompt' => 'Bold travel poster in the style of the reference grid']];
        $texts = self::texts($plan);
        $jobs = [];
        for ($i = 0; $i < $n; $i++) {
            $c = $concepts[$i % count($concepts)];
            $jobs[] = ['prompt' => $this->posterPrompt($texts, $c['prompt'], $result['format'], count($refs), count($photos)),
                       'images' => [...$refs, ...$photos], 'aspect' => $result['format'] === 'reels' ? '9:16' : '4:5', 'name' => $c['name']];
        }
        $say("2/3 AI $n ta variant chizmoqda (odatda 30-90 soniya)...");
        $errors = [];
        foreach ($this->ai->generateImages($jobs) as $i => $img) {
            if (is_string($img)) {
                $errors[] = $img;
                continue;
            }
            $result['variants'][] = $this->saveImage($img, $result, $jobs[$i]['name'], $texts, $briefId);
        }
        if (!$result['variants']) {
            throw new RuntimeException($errors[0] ?? "rasm qaytmadi");
        }
        $say('3/3 Rasmlardagi yozuvlar tekshirilmoqda...');
        $result['variants'] = $this->checkTexts($result['variants'], $briefId);
        $result['variants'] = $this->autoFix($result['variants'], $result, $briefId, $say);
        // Yozuvi to'g'ri chiqqanlar birinchi
        usort($result['variants'], static fn ($a, $b) => (int) (($b['check']['ok'] ?? true) === true) <=> (int) (($a['check']['ok'] ?? true) === true));
        $result['card_path'] = $result['variants'][0]['path'];
        $result['card_layout'] = 'ai';
        $result['image_generated'] = true;
        return $result;
    }

    private function aiCarousel(array $result, array $plan, array $options, array $refs, array $photos, int $briefId, callable $say): array
    {
        $slides = $plan['slides'];
        // Egasi o'zi "1-slayd: ..." deb yozgan bo'lsa — aynan uning matni (AI konseptidan foydalanib)
        $own = PostRenderer::slidesFromText((string) (($options['text'] ?? '') ?: ($options['variant']['visual'] ?? '')));
        if (count($own) >= 2 && (!empty($options['text']) || count($slides) < 2)) {
            $slides = array_map(static fn ($s, $i) => [
                'headline' => (string) $s['title'], 'subline' => (string) ($s['text'] ?? ''),
                'prompt' => $plan['slides'][$i]['prompt'] ?? '',
            ], $own, array_keys($own));
        }
        if (count($slides) < 2) {
            throw new RuntimeException('karusel slaydlari aniqlanmadi');
        }
        $slides = array_slice($slides, 0, 10);
        $n = count($slides);
        $style = $plan['concepts'][0]['prompt'] ?? '';
        $job = function (int $i, array $extraImages) use ($slides, $n, $style, $refs, $photos, $result) {
            $s = $slides[$i];
            $role = $i === 0 ? 'COVER slide: a strong hook that makes people swipe'
                : ($i === $n - 1 ? 'LAST slide: call to action' : 'content slide: one idea, clean and readable');
            $lock = $extraImages ? ' The LAST attached image is slide 1 of this same carousel: copy its exact visual system — fonts, colours, text treatment, graphic elements — so all slides look like one series.' : '';
            return ['prompt' => $this->posterPrompt(self::texts($s),
                        trim("Slide " . ($i + 1) . " of $n of one Instagram carousel. $role. Overall style: $style. This slide: {$s['prompt']}") . $lock,
                        'karusel', count($refs), $i === 0 ? count($photos) : 0),
                    'images' => [...$refs, ...($i === 0 ? $photos : []), ...$extraImages], 'aspect' => '4:5'];
        };

        $say("2/3 AI karusel muqovasini chizmoqda (1/$n)...");
        $cover = $this->ai->generateImages([$job(0, [])])[0];
        if (is_string($cover)) {
            throw new RuntimeException($cover);
        }
        $items = [0 => $this->saveImage($cover, $result, '1-slayd', self::texts($slides[0]), $briefId, 'Surib ko‘ring →')];
        $coverRef = ['mime' => $cover['mime_type'], 'data' => $cover['base64']];
        $say("2/3 Qolgan " . ($n - 1) . " ta slayd shu uslubda chizilmoqda...");
        $jobs = [];
        for ($i = 1; $i < $n; $i++) {
            $jobs[$i] = $job($i, [$coverRef]);
        }
        foreach ($this->ai->generateImages($jobs) as $i => $img) {
            if (is_string($img)) {
                throw new RuntimeException(($i + 1) . "-slayd chizilmadi: $img");
            }
            $items[$i] = $this->saveImage($img, $result, ($i + 1) . '-slayd', self::texts($slides[$i]), $briefId,
                $i === $n - 1 ? ($this->phone() ?: "Direct'ga yozing") : '');
        }
        ksort($items);
        $say('3/3 Slaydlardagi yozuvlar tekshirilmoqda...');
        $items = $this->checkTexts(array_values($items), $briefId);
        $items = $this->autoFix($items, $result, $briefId, $say);
        $result['slide_meta'] = $items;
        $result['slides'] = array_column($items, 'path');
        $result['card_path'] = $result['slides'][0];
        $result['card_layout'] = 'carousel';
        $result['zip_path'] = self::zip($result['slides'], Output::dir(['id' => $briefId, 'topic' => $this->topic($briefId)]) . '/karusel-' . bin2hex(random_bytes(3)) . '.zip');
        $result['image_generated'] = true;
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
        $say('3/3 ' . count($bad) . " ta rasmda yozuv xatosi — AI o'zi tuzatmoqda...");
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

    public function choose(int $resultId, int $index): array
    {
        $d = $this->store->resultById($resultId) ?? throw new RuntimeException('Dizayn topilmadi.');
        if (!isset($d['variants'][$index])) {
            throw new RuntimeException('Variant topilmadi.');
        }
        $d['chosen'] = $index;
        $d['card_path'] = $d['variants'][$index]['path'];
        $this->store->updateResult($resultId, $d);
        return $d;
    }

    /**
     * Rasm modeli tanlangan rasmni tahrirlaydi ("narxni kattaroq qil", "ISTANBUL so'zini to'g'rila").
     * Post: yangi variant qo'shiladi va tanlanadi. Karusel: slayd almashtiriladi.
     */
    public function fix(int $resultId, int $index, string $instruction): array
    {
        $d = $this->store->resultById($resultId) ?? throw new RuntimeException('Dizayn topilmadi.');
        $carousel = !empty($d['slide_meta']);
        $item = $carousel ? ($d['slide_meta'][$index] ?? null) : ($d['variants'][$index] ?? null);
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
        $new = $this->saveImage($img, $d, 'Tuzatilgan: ' . mb_strimwidth($instruction, 0, 40, '…'), $item['texts'] ?? [], (int) $d['brief_id'], $item['cta'] ?? null);
        $new = $this->checkTexts([$new], (int) $d['brief_id'])[0];
        if ($carousel) {
            $d['slide_meta'][$index] = $new;
            $d['slides'][$index] = $new['path'];
            $d['card_path'] = $d['slides'][0];
            if (!empty($d['zip_path'])) {
                $d['zip_path'] = self::zip($d['slides'], preg_replace('/\.zip$/', '', $d['zip_path']) . '-2.zip') ?? $d['zip_path'];
            }
        } else {
            $d['variants'][] = $new;
            $d['chosen'] = count($d['variants']) - 1;
            $d['card_path'] = $new['path'];
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
