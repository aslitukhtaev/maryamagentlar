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
                'hook' => $v['hook'] ?? '', 'cta' => $v['cta'] ?? '', 'headline' => $v['headline'] ?? '',
                'visual_idea' => $v['visual'] ?? '', 'format' => $v['format'] ?? '',
            ]);
        }

        $say('1/2 Kontseptsiya: maket va rasm prompti tayyorlanmoqda...');
        // Kompaniyaning dizayn namunalari (web'da yuklangan grid/postlar) — agent ularni rasm sifatida ko'radi
        $refs = BrandAssets::refsForAi();
        $context['reference_images'] = $refs ? count($refs) . " ta kompaniya dizayn namunasi ilova qilingan" : "yo'q";
        $data = Prompts::ask($this->ai, $this->store, 'designer/prompt', $context, 0.8, true, $briefId, self::NAME, 'prompt', $refs);
        $layout = array_values(array_map('strval', (array) ($data['layout'] ?? [])));
        // Har variantning dizayni alohida saqlanadi (biri ikkinchisini o'chirmasin)
        $variantId = (int) ($options['variant']['db_id'] ?? 0);
        $suffix = $variantId ? "-$variantId" : '';

        $imagePrompt = trim((string) ($data['image_prompt'] ?? ''));
        $altText = trim((string) ($data['alt_text'] ?? ''));

        $imagePath = null;
        $generated = false;

        if ($imagePrompt !== '' && method_exists($this->ai, 'generateImage')) {
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

        // Brend uslubidagi TAYYOR rasm: fon (generatsiya bo'lsa) + sarlavha, narx, lenta, logo
        $cardPath = null;
        $card = (array) ($data['card'] ?? []);
        $layout = (string) ($card['layout'] ?? '');
        if (isset(PostRenderer::LAYOUTS[$layout])) {
            try {
                $png = PostRenderer::forBrand($this->brand)->render($layout, $card + ['bg' => $imagePath], PostRenderer::colors($this->store));
                $cardPath = Output::dir($brief) . "/post$suffix.png";
                file_put_contents($cardPath, $png);
                $say('Tayyor rasm chizildi.');
            } catch (Throwable $e) {
                $say("Tayyor rasmni chizib bo'lmadi: {$e->getMessage()}");
            }
        }

        $result = [
            'brief_id' => $briefId,
            'card_path' => $cardPath,
            'card_layout' => $cardPath ? $layout : '',
            'image_prompt' => $imagePrompt,
            'alt_text' => $altText,
            'layout' => $layout,
            'image_path' => $imagePath,
            'image_generated' => $generated,
        ];
        $this->store->saveResult($briefId, self::NAME, $variantId ? "variant_$variantId" : 'final', $result);
        return $result;
    }
}
