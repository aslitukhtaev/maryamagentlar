<?php

declare(strict_types=1);

namespace Maryam\Agents;

use Maryam\Brief;
use Maryam\Output;
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
     * @param array $options         'progress' => fn(string $xabar)
     * @return array{brief_id: int, image_prompt: string, alt_text: string, image_path: ?string, image_generated: bool}
     */
    public function run(array $brief, array $strategyContext = [], array $options = []): array
    {
        $say = $options['progress'] ?? static fn (string $m) => null;
        $tone = $this->tones[$brief['tourism_type']];
        $briefId = $brief['id'] ?? $this->store->saveBrief($brief);

        $context = [
            'brief' => Brief::forPrompt($brief, $this->tones),
            'brand' => $this->brand,
            'tone_profile' => $tone,
            'big_idea' => $strategyContext['big_idea'] ?? '',
            'key_message' => $strategyContext['key_message'] ?? '',
        ];

        $say('1/2 Kontseptsiya: rasm uchun batafsil prompt yozilmoqda...');
        $system = file_get_contents(ROOT . '/prompts/designer/prompt.md');
        $user = "Kontekst (JSON):\n" . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
              . "\n\nVazifani bajar va faqat ko'rsatilgan formatdagi JSON qaytar.";

        $result = $this->ai->json($system, $user, 0.8, true);
        $this->store->logRun($briefId, self::NAME, 'prompt', $user, $result);
        $data = $result['data'] ?? [];

        $imagePrompt = trim((string) ($data['image_prompt'] ?? ''));
        $altText = trim((string) ($data['alt_text'] ?? ''));

        $imagePath = null;
        $generated = false;

        if ($imagePrompt !== '' && method_exists($this->ai, 'generateImage')) {
            $say('2/2 Rasm generatsiya qilinmoqda...');
            try {
                $image = $this->ai->generateImage($imagePrompt);
                $ext = str_contains($image['mime_type'], 'png') ? 'png' : 'jpg';
                $imagePath = Output::dir($brief) . "/designer.$ext";
                file_put_contents($imagePath, base64_decode($image['base64']));
                $generated = true;
                $say("Rasm tayyor ({$image['model_used']}).");
            } catch (Throwable $e) {
                // Loyihaviy talab: rasm generatsiya ishlamasa, faqat prompt matni bilan qanoatlanamiz
                $say("Rasm generatsiya qilib bo'lmadi ({$e->getMessage()}) — faqat prompt matni beriladi.");
            }
        }

        $result = [
            'brief_id' => $briefId,
            'image_prompt' => $imagePrompt,
            'alt_text' => $altText,
            'image_path' => $imagePath,
            'image_generated' => $generated,
        ];
        $this->store->saveResult($briefId, self::NAME, 'final', $result);
        return $result;
    }
}
