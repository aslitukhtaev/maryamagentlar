<?php

declare(strict_types=1);

namespace Maryam;

use RuntimeException;

/**
 * Telegram bot orqali xabar yuborish — Copywriter natijalarini
 * telefoningizga to'g'ridan-to'g'ri yetkazib berish uchun.
 *
 * Botga ulanish: Telegram'da botingizni topib /start bosing, keyin
 * getUpdates orqali chat_id'ingizni oling (bir marta qilinadi).
 */
final class Telegram
{
    public function __construct(
        private string $token,
        private string $chatId,
    ) {
    }

    public static function isConfigured(): bool
    {
        return Env::get('TELEGRAM_BOT_TOKEN', '') !== '' && Env::get('TELEGRAM_CHAT_ID', '') !== '';
    }

    public static function fromEnv(): self
    {
        return new self(
            Env::get('TELEGRAM_BOT_TOKEN', ''),
            Env::get('TELEGRAM_CHAT_ID', ''),
        );
    }

    /** Oddiy matnli xabar yuboradi. Telegram xabar limiti — 4096 belgi, uzunini avtomatik bo'lib yuboradi. */
    public function send(string $text): void
    {
        foreach (self::splitLong($text) as $chunk) {
            $this->call('sendMessage', [
                'chat_id' => $this->chatId,
                'text' => $chunk,
            ]);
        }
    }

    /** Faylni (masalan copywriter.txt) hujjat sifatida yuboradi. */
    public function sendDocument(string $filePath, string $caption = ''): void
    {
        if (!is_file($filePath)) {
            throw new RuntimeException("Fayl topilmadi: $filePath");
        }
        $this->call('sendDocument', [
            'chat_id' => $this->chatId,
            'caption' => mb_substr($caption, 0, 1024),
            'document' => new \CURLFile($filePath),
        ], multipart: true);
    }

    /**
     * Manager "ask_field" qaytarsa (tourism_type/goal/language), shu maydon uchun
     * tugmalar (inline keyboard) quradi — foydalanuvchi variantni qo'lda yozmasdan bossa bo'ladi.
     * Noma'lum maydon uchun null qaytaradi (tugmasiz oddiy xabar yuboriladi).
     */
    /** Tugma matni uzun bo'lmasin uchun "goal"ning qisqa nomlari (Brief::GOALS to'liq tavsif beradi). */
    private const GOAL_SHORT_LABELS = [
        'lid' => "Lid yig'ish",
        'sotuv' => 'Sotuv/bron',
        'brend' => 'Brend tanitish',
        'jalb' => 'Jalb qilish',
    ];

    public static function fieldKeyboard(string $field, array $tones): ?string
    {
        $rows = match ($field) {
            'tourism_type' => array_map(
                static fn ($key, $t) => [['text' => $t['label'], 'callback_data' => "tourism_type:$key"]],
                array_keys($tones),
                $tones
            ),
            'goal' => array_map(
                static fn ($key) => [['text' => self::GOAL_SHORT_LABELS[$key] ?? $key, 'callback_data' => "goal:$key"]],
                array_keys(Brief::GOALS)
            ),
            'language' => array_map(
                static fn ($key, $label) => [['text' => $label, 'callback_data' => "language:$key"]],
                array_keys(Brief::LANGUAGES),
                Brief::LANGUAGES
            ),
            default => null,
        };
        return $rows ? json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE) : null;
    }

    /** 4096 belgidan uzun matnni bir necha xabarga bo'ladi (so'z chegarasidan sindiradi). */
    private static function splitLong(string $text, int $limit = 4000): array
    {
        if (mb_strlen($text) <= $limit) {
            return [$text];
        }
        $chunks = [];
        while (mb_strlen($text) > $limit) {
            $cut = mb_strrpos(mb_substr($text, 0, $limit), "\n") ?: $limit;
            $chunks[] = mb_substr($text, 0, $cut);
            $text = mb_substr($text, $cut);
        }
        $chunks[] = $text;
        return $chunks;
    }

    private function call(string $method, array $params, bool $multipart = false): array
    {
        $ch = curl_init("https://api.telegram.org/bot{$this->token}/{$method}");
        Http::applyCaBundle($ch);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POSTFIELDS => $multipart ? $params : http_build_query($params),
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $response = json_decode((string) $body, true) ?? [];
        if ($status !== 200 || !($response['ok'] ?? false)) {
            throw new RuntimeException('Telegram xatosi: ' . ($response['description'] ?? $body));
        }
        return $response;
    }
}
