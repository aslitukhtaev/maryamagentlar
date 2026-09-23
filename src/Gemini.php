<?php

declare(strict_types=1);

namespace Maryam;

use RuntimeException;

/**
 * Gemini API bilan gaplashadigan kichik mijoz (to'g'ridan-to'g'ri REST, kutubxonasiz).
 *
 * Ikki xil model bor:
 *  - "fast"  — oddiy yozish ishlari uchun
 *  - "smart" — o'ylash talab qiladigan ishlar (strategiya, tahrir) uchun
 */
class Gemini
{
    private const URL = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    public function __construct(
        private string $apiKey,
        public readonly string $fastModel,
        public readonly string $smartModel,
        /** Asosiy model band bo'lsa navbat bilan sinaladigan zaxira modellar */
        public readonly array $fallbackModels = [],
    ) {
    }

    /**
     * AI'dan JSON javob so'raydi va uni PHP massiviga aylantiradi.
     *
     * @return array{data: array, text: string, model: string, tokens_in: int, tokens_out: int, ms: int}
     */
    public function json(string $system, string $user, float $temperature = 0.8, bool $smart = false): array
    {
        $main = $smart ? $this->smartModel : $this->fastModel;
        $models = array_values(array_unique([$main, ...$this->fallbackModels]));
        $lastError = null;
        foreach ($models as $i => $model) {
            try {
                $result = $this->generate($system, $user, $temperature, $model);
                $result['data'] = self::decodeJson($result['text']);
                $result['model_used'] = $model; // Qaysi model ishlandi — log uchun
                return $result;
            } catch (RuntimeException $e) {
                $lastError = $e;
                $isLast = $i === count($models) - 1;
                if ($isLast || !preg_match('/\((429|503)\)/', $e->getMessage())) {
                    throw $e;
                }
            }
        }
        throw $lastError ?? new RuntimeException('Hech bir model javob bermadi.');
    }

    /** Bitta so'rov. Vaqtinchalik xatolarda (429, 5xx) 3 martagacha qayta urinadi. */
    protected function generate(string $system, string $user, float $temperature, string $model): array
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('GEMINI_API_KEY topilmadi. .env faylini tekshiring (namuna: .env.example).');
        }

        $payload = [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $user]]]],
            'generationConfig' => [
                'temperature' => $temperature,
                'responseMimeType' => 'application/json',
            ],
        ];

        $started = microtime(true);
        $attempt = 0;
        while (true) {
            $attempt++;
            $ch = curl_init(sprintf(self::URL, rawurlencode($model)));
            Http::applyCaBundle($ch);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 180,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $this->apiKey],
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);
            $body = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            $retryable = $body === false || $status === 429 || $status >= 500;
            if ($retryable && $attempt < 4) {
                sleep(2 ** $attempt); // 2, 4, 8 soniya kutamiz
                continue;
            }
            if ($body === false) {
                throw new RuntimeException("Gemini bilan aloqa yo'q: $error");
            }
            $response = json_decode($body, true) ?? [];
            if ($status !== 200) {
                $message = $response['error']['message'] ?? $body;
                // Eng ko'p uchraydigan xatolarni o'zbekcha tushuntiramiz
                if ($status === 402 || str_contains($message, 'credits are depleted')) {
                    $message = "Gemini hisobidagi mablag' (kredit) tugagan. https://aistudio.google.com da billingni to'ldiring. ($message)";
                } elseif (str_contains($message, 'no longer available')) {
                    $message = ".env dagi model eskirgan, yangisini qo'ying (masalan gemini-3.8-flash). ($message)";
                }
                throw new RuntimeException("Gemini xatosi ($status): $message");
            }
            break;
        }

        $candidate = $response['candidates'][0] ?? null;
        if ($candidate === null) {
            $reason = $response['promptFeedback']['blockReason'] ?? "noma'lum";
            throw new RuntimeException("Gemini javob bermadi (sabab: $reason)");
        }

        // "thought" qismlar — modelning ichki o'ylashi, ularni tashlab yuboramiz
        $text = '';
        foreach ($candidate['content']['parts'] ?? [] as $part) {
            if (empty($part['thought'])) {
                $text .= $part['text'] ?? '';
            }
        }
        if (trim($text) === '') {
            throw new RuntimeException("Gemini bo'sh javob qaytardi (finishReason: " . ($candidate['finishReason'] ?? '?') . ')');
        }

        return [
            'text' => $text,
            'model' => $model,
            'tokens_in' => (int) ($response['usageMetadata']['promptTokenCount'] ?? 0),
            'tokens_out' => (int) ($response['usageMetadata']['candidatesTokenCount'] ?? 0),
            'ms' => (int) ((microtime(true) - $started) * 1000),
        ];
    }

    /** Model ba'zan JSON'ni ```json ... ``` ichiga o'rab yuboradi — shuni tozalaymiz. */
    public static function decodeJson(string $text): array
    {
        $clean = trim($text);
        if (preg_match('/```(?:json)?\s*(.*?)```/s', $clean, $m)) {
            $clean = trim($m[1]);
        }
        $data = json_decode($clean, true);
        if (!is_array($data)) {
            throw new RuntimeException("AI javobi JSON emas: " . mb_substr($text, 0, 300));
        }
        return $data;
    }
}
