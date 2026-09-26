<?php

declare(strict_types=1);

namespace Maryam\Agents;

use Maryam\BrandAssets;
use Maryam\Brief;
use Maryam\Marketing;
use Maryam\Output;
use Maryam\PostRenderer;
use Maryam\Prompts;
use Maryam\Store;
use Throwable;

/**
 * GRAPHIC DESIGNER AGENT — post uchun rasm generatsiya qiladi.
 *
 * 2 bosqichda ishlaydi:
 *   1. Kontseptsiya — brif va ton profiliga qarab BATAFSIL image-generation prompt yozadi
 *   2. Generatsiya — shu prompt bilan Gemini'dan haqiqiy rasm so'raydi
 *
 * Agar rasm generatsiyasi ishlamasa (model mavjud emas, cheklov va h.k.) — bu XATO
 * hisoblanmaydi: original loyihaviy talab bo'yicha, shunday holatda agent tayyor,
 * batafsil prompt matnini beradi — buni boshqa vositada (masalan qo'lda) ishlatish mumkin.
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
     * Natija meta'da saqlanadi va har bir dizaynda agentga beriladi; renderer ham undan foydalanadi.
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
            'uppercase_titles' => (bool) ($data['uppercase_titles'] ?? false),
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
     * @param array $options         'progress' => fn(string $xabar),
     *                               'template_id' => shablon (dizayn ko'rsatmasi shundan olinadi),
     *                               'variant' => Copywriter varianti (sarlavha/narx/CTA maketga tushadi)
     * @return array{brief_id: int, image_prompt: string, alt_text: string, layout: array, image_path: ?string, image_generated: bool}
     */
    public function run(array $brief, array $strategyContext = [], array $options = []): array
    {
        $say = $options['progress'] ?? static fn (string $m) => null;
        $tone = $this->tones[$brief['tourism_type']];
        $briefId = $brief['id'] ?? $this->store->saveBrief($brief);

        $context = [
            'brief' => Brief::forPrompt($brief, $this->tones),
            'brand' => Marketing::brand($this->brand, $this->store),
            'tone_profile' => $tone,
            'big_idea' => $strategyContext['big_idea'] ?? '',
            'key_message' => $strategyContext['key_message'] ?? '',
        ] + Marketing::training($this->store, self::NAME, isset($options['template_id']) ? (int) $options['template_id'] : null);
        if (!empty($options['variant'])) {
            $v = $options['variant'];
            $context['copy'] = array_filter([
                'hook' => $v['hook'] ?? '', 'body' => mb_substr((string) ($v['body'] ?? ''), 0, 900), 'cta' => $v['cta'] ?? '',
                'headline' => $v['headline'] ?? '', 'visual_idea' => $v['visual'] ?? '', 'format' => $v['format'] ?? '',
            ]);
        }
        if (!empty($options['text'])) {
            $context['copy'] = ['text' => (string) $options['text']]; // Dizayner sahifasidan: foydalanuvchi matni/g'oyasi
        }
        // Nima chizamiz: post | karusel | reels (stories/Reels muqovasi) | reklama
        $template = isset($options['template_id']) ? $this->store->row('templates', (int) $options['template_id']) : null;
        $format = (string) (($options['format'] ?? '') ?: ($options['variant']['format'] ?? '') ?: ($template['format'] ?? '') ?: 'post');
        $context['deliverable_format'] = $format;

        $say('1/2 Kontseptsiya: maket va rasm prompti tayyorlanmoqda...');
        // Kompaniyaning dizayn namunalari (web'da yuklangan grid/postlar) — agent ularni rasm sifatida ko'radi
        $refs = BrandAssets::refsForAi();
        $context['reference_images'] = $refs ? count($refs) . " ta kompaniya dizayn namunasi ilova qilingan" : "yo'q";
        $style = self::style($this->store);
        if ($style) {
            $context['brand_style'] = $style; // namunalardan chiqarilgan uslub profili — asosiy qonun
        }
        $data = Prompts::ask($this->ai, $this->store, 'designer/prompt', $context, 0.8, true, $briefId, self::NAME, 'prompt', $refs);
        $layout = array_values(array_map('strval', (array) ($data['layout'] ?? [])));
        // Har variantning dizayni alohida saqlanadi (biri ikkinchisini o'chirmasin)
        $variantId = (int) ($options['variant']['db_id'] ?? 0);
        $kind = (string) ($options['kind'] ?? ($variantId ? "variant_$variantId" : 'final'));
        $suffix = $variantId ? "-$variantId" : ($kind === 'final' ? '' : "-$kind");

        $imagePrompt = trim((string) ($data['image_prompt'] ?? ''));
        $altText = trim((string) ($data['alt_text'] ?? ''));

        $imagePath = null;
        $generated = false;

        // Kompaniya gridida foto fon ishlatilmasa — generatsiya qilmaymiz, brend foni ishlatiladi
        $wantPhoto = ($style['photo_background'] ?? true) !== false;
        if ($wantPhoto && $imagePrompt !== '' && method_exists($this->ai, 'generateImage')) {
            $say('2/2 Rasm generatsiya qilinmoqda...');
            try {
                $image = $refs
                    ? $this->ai->generateImage($imagePrompt . ' Match the photographic style, colour grading and composition of the attached reference images, but do not copy any text or logos from them.', images: array_slice($refs, 0, 2))
                    : $this->ai->generateImage($imagePrompt);
                $ext = str_contains($image['mime_type'], 'png') ? 'png' : 'jpg';
                $imagePath = Output::dir($brief) . "/designer$suffix.$ext";
                file_put_contents($imagePath, base64_decode($image['base64']));
                $generated = true;
                $say("Rasm tayyor ({$image['model_used']}).");
            } catch (Throwable $e) {
                // Loyihaviy talab: rasm generatsiya ishlamasa, faqat prompt matni bilan qanoatlanamiz
                $say("Rasm generatsiya qilib bo'lmadi ({$e->getMessage()}) — faqat prompt matni beriladi.");
            }
        }

        // Brend uslubidagi TAYYOR rasm(lar): fon (generatsiya bo'lsa) + sarlavha, narx, lenta, logo
        $cardPath = null;
        $slidePaths = [];
        $zipPath = null;
        $card = (array) ($data['card'] ?? []);
        $cardLayout = (string) ($card['layout'] ?? '');
        $renderer = PostRenderer::forBrand($this->brand, $style);
        $colors = PostRenderer::colors($this->store);
        $dir = Output::dir($brief);
        try {
            $slides = $format === 'karusel' ? $this->carouselSlides($card, $options) : [];
            if (count($slides) >= 2) {
                $slides[0]['bg'] ??= $imagePath; // foto faqat muqovada — qolganlari bir xil brend fonida
                foreach ($renderer->renderCarousel($slides, $colors) as $i => $png) {
                    $slidePaths[] = $path = "$dir/post$suffix-s" . ($i + 1) . '.png';
                    file_put_contents($path, $png);
                }
                $cardPath = $slidePaths[0];
                $cardLayout = 'carousel';
                $zipPath = self::zip($slidePaths, "$dir/karusel$suffix.zip");
                $say(count($slidePaths) . ' ta slayd chizildi.');
            } else {
                if (!isset(PostRenderer::LAYOUTS[$cardLayout]) || in_array($cardLayout, ['slide_cover', 'slide_cta'], true) && $format !== 'karusel') {
                    // AI tartib bermadi — formatga mos zaxira tartib
                    $cardLayout = $format === 'reels' ? 'cover' : (!empty($card['price']) ? 'hot_tour' : 'slide_cover');
                }
                $card['title'] = ($card['title'] ?? '') ?: ($options['variant']['hook'] ?? '') ?: $brief['topic'];
                $cardPath = "$dir/post$suffix.png";
                file_put_contents($cardPath, $renderer->render($cardLayout, $card + ['bg' => $imagePath], $colors));
                $say('Tayyor rasm chizildi.');
            }
        } catch (Throwable $e) {
            $say("Tayyor rasmni chizib bo'lmadi: {$e->getMessage()}");
        }

        $result = [
            'brief_id' => $briefId,
            'card_path' => $cardPath,
            'card_layout' => $cardPath ? $cardLayout : '',
            'format' => $format,
            'slides' => $slidePaths,
            'zip_path' => $zipPath,
            'image_prompt' => $imagePrompt,
            'alt_text' => $altText,
            'layout' => $layout,
            'image_path' => $imagePath,
            'image_generated' => $generated,
        ];
        $result['result_id'] = $this->store->saveResult($briefId, self::NAME, $kind, $result);
        return $result;
    }

    /** Karusel slaydlari: AI bergan "slides", bo'lmasa copywriter/foydalanuvchi matnidagi "1-slayd: ..." qatorlari. */
    private function carouselSlides(array $card, array $options): array
    {
        $slides = array_values(array_filter((array) ($card['slides'] ?? []), static fn ($x) => is_array($x) && !empty($x['title'])));
        if (count($slides) >= 2) {
            return array_slice($slides, 0, 10);
        }
        $text = (string) (($options['variant']['visual'] ?? '') ?: ($options['text'] ?? ''));
        return PostRenderer::slidesFromText($text, (string) ($options['variant']['cta'] ?? $card['cta'] ?? ''));
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
            $zip->addFile($f, sprintf('slayd-%02d.png', $i + 1));
        }
        $zip->close();
        return $zipPath;
    }
}
