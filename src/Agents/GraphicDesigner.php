<?php

declare(strict_types=1);

namespace Maryam\Agents;

use Maryam\BrandAssets;
use Maryam\Brief;
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
        $refs = BrandAssets::refsForAi(3);
        $photos = BrandAssets::photosForAi((array) ($options['photos'] ?? []));
        $context['reference_images'] = count($refs);
        $context['user_photos'] = count($photos);
        if ($style = self::style($this->store)) {
            $context['brand_style'] = $style;
        }

        $say('1/3 Art-direktor: yozuvlar va konseptlar tanlanmoqda...');
        $plan = self::normalizePlan(Prompts::ask($this->ai, $this->store, 'designer/poster', $context, 0.9, true, $briefId, self::NAME, 'poster', [...$refs, ...$photos]));
        if ($plan['headline'] === '') {
            $plan['headline'] = mb_strimwidth((string) (($options['variant']['hook'] ?? '') ?: $brief['topic']), 0, 40, '');
        }

        $variantId = (int) ($options['variant']['db_id'] ?? 0);
        $kind = (string) ($options['kind'] ?? ($variantId ? "variant_$variantId" : 'final'));
        $result = [
            'brief_id' => $briefId, 'format' => $format, 'engine' => 'ai',
            'headline' => $plan['headline'], 'subline' => $plan['subline'], 'price' => $plan['price'],
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
            $role = $i === 0 ? 'COVER slide: a strong hook that makes people swipe; add a small "swipe →" hint'
                : ($i === $n - 1 ? 'LAST slide: call to action' : 'content slide: one idea, clean and readable');
            $lock = $extraImages ? ' The LAST attached image is slide 1 of this same carousel: copy its exact visual system — fonts, colours, text treatment, graphic elements — so all slides look like one series.' : '';
            return ['prompt' => $this->posterPrompt(['Headline' => $s['headline'], 'Sub-line' => $s['subline']],
                        trim("Slide " . ($i + 1) . " of $n of one Instagram carousel. $role. Overall style: $style. This slide: {$s['prompt']}") . $lock,
                        'karusel', count($refs), $i === 0 ? count($photos) : 0),
                    'images' => [...$refs, ...($i === 0 ? $photos : []), ...$extraImages], 'aspect' => '4:5'];
        };

        $say("2/3 AI karusel muqovasini chizmoqda (1/$n)...");
        $cover = $this->ai->generateImages([$job(0, [])])[0];
        if (is_string($cover)) {
            throw new RuntimeException($cover);
        }
        $items = [0 => $this->saveImage($cover, $result, '1-slayd', self::texts($slides[0]), $briefId)];
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
            $items[$i] = $this->saveImage($img, $result, ($i + 1) . '-slayd', self::texts($slides[$i]), $briefId);
        }
        ksort($items);
        $say('3/3 Slaydlardagi yozuvlar tekshirilmoqda...');
        $items = $this->checkTexts(array_values($items), $briefId);
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
        $colors = PostRenderer::colors($this->store);
        $style = self::style($this->store);
        $canvas = $format === 'reels' ? 'vertical 9:16 Instagram Stories/Reels cover' : 'vertical 4:5 Instagram feed post';
        $p = "Design a finished, scroll-stopping $canvas for \"Maryam Travel\", a travel agency in Uzbekistan. It must look like a professional designer made it for this brand's feed.\n";
        if ($nRefs) {
            $p .= "STYLE: The first $nRefs attached image(s) are screenshots of the agency's own Instagram grid. Match that visual language closely — bold condensed uppercase headlines in white or yellow with strong outline/shadow, vivid real photography, people with genuine emotion, sticker-like badges, flags and icons, energetic but clean composition. Do NOT copy any text, faces or logos from these screenshots.\n";
        }
        if ($nPhotos) {
            $p .= "PEOPLE: The next $nPhotos attached photo(s) show real people from our team/clients. Use these exact people as the main subject — keep face, identity, skin tone and body realistic and unchanged; cut them out and compose them naturally into the scene with matching light.\n";
        }
        $p .= "Brand colours for accents: deep green {$colors['primary']} and gold {$colors['accent']}.";
        if (!empty($style['mood'])) {
            $p .= " Mood: {$style['mood']}.";
        }
        $p .= "\nART DIRECTION: $concept\n";
        $lines = array_filter($texts, static fn ($t) => trim((string) $t) !== '');
        $p .= "TEXT ON THE IMAGE — render exactly these texts, spelled letter-for-letter in Uzbek Latin script, and no other words:\n";
        foreach ($lines as $label => $t) {
            $p .= "- $label: \"" . trim((string) $t) . "\"\n";
        }
        $p .= "RULES: text large, sharp, high-contrast and fully inside the frame; no extra words, no fake letters, no phone numbers, no website, no logo, no watermark, no Instagram interface. Keep the top 9% and the bottom 7% of the canvas free of text (the real logo and contacts are added there later).";
        return $p;
    }

    /** @return array{path: string, raw: string, concept: string, texts: array, model: string, check: ?array} */
    private function saveImage(array $img, array $result, string $name, array $texts, int $briefId): array
    {
        $dir = Output::dir(['id' => $briefId, 'topic' => $this->topic($briefId)]);
        $id = bin2hex(random_bytes(4));
        $bytes = (string) base64_decode($img['base64']);
        $raw = "$dir/ai-$id-raw." . (str_contains($img['mime_type'], 'png') ? 'png' : 'jpg');
        file_put_contents($raw, $bytes);
        $path = "$dir/ai-$id.jpg";
        $renderer = PostRenderer::forBrand($this->brand, self::style($this->store));
        file_put_contents($path, $renderer->finishPoster($bytes, $result['format'], $this->bottomText($result), PostRenderer::colors($this->store)));
        return ['path' => $path, 'raw' => $raw, 'concept' => $name, 'texts' => $texts, 'model' => (string) ($img['model_used'] ?? ''), 'check' => null];
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
        $texts = array_filter($item['texts'] ?? []);
        $prompt = "Edit the attached image, a finished Instagram post. Apply ONLY this change (the request may be in Uzbek): \"$instruction\". "
            . "Keep everything else identical — composition, people and faces, colours, fonts and all other text. "
            . ($texts ? 'All text on the image must stay spelled exactly: ' . implode(' | ', array_map(static fn ($t) => "\"$t\"", $texts)) . '. ' : '')
            . 'Do not add a logo, phone number, website or watermark.';
        $raw = (string) file_get_contents($item['raw']);
        $img = $this->ai->generateImages([['prompt' => $prompt, 'aspect' => $d['format'] === 'reels' ? '9:16' : '4:5',
            'images' => [['mime' => str_ends_with($item['raw'], '.png') ? 'image/png' : 'image/jpeg', 'data' => base64_encode($raw)]]]])[0];
        if (is_string($img)) {
            throw new RuntimeException('AI tuzata olmadi: ' . $img);
        }
        $new = $this->saveImage($img, $d, 'Tuzatilgan: ' . mb_strimwidth($instruction, 0, 40, '…'), $item['texts'] ?? [], (int) $d['brief_id']);
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
                $slides[] = ['headline' => $str($s['headline']), 'subline' => $str($s['subline'] ?? ''), 'prompt' => trim((string) ($s['prompt'] ?? ''))];
            }
        }
        return [
            'headline' => $str($data['headline'] ?? ''), 'subline' => $str($data['subline'] ?? ''),
            'price' => $str($data['price'] ?? ''), 'badge' => $str($data['badge'] ?? ''),
            'concepts' => $concepts, 'slides' => $slides, 'alt_text' => trim((string) ($data['alt_text'] ?? '')),
        ];
    }

    /** @return array<string, string> rasmga yoziladigan matnlar */
    private static function texts(array $p): array
    {
        return array_filter([
            'Headline' => $p['headline'] ?? '', 'Sub-line' => $p['subline'] ?? '',
            'Price badge' => $p['price'] ?? '', 'Small badge' => $p['badge'] ?? '',
        ], static fn ($t) => $t !== '');
    }

    /** Pastki yozuv: sotuv posti (narx bor) — telefon, qolganlari — Instagram manzili. */
    private function bottomText(array $result): string
    {
        $phone = trim((string) preg_replace('/\s*\(.*?\)/u', '', (string) ($this->brand['phone'] ?? '')));
        $sales = ($result['price'] ?? '') !== '' || ($result['format'] ?? '') === 'reklama';
        return $sales && $phone !== '' ? $phone : (string) ($this->brand['instagram'] ?? $phone);
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
