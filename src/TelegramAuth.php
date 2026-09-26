<?php

declare(strict_types=1);

namespace Maryam;

/**
 * Telegram Mini App kirishi: Telegram yuborgan initData imzosini bot tokeni bilan tekshiradi.
 * https://core.telegram.org/bots/webapps#validating-data-received-via-the-mini-app
 */
final class TelegramAuth
{
    /** @return array|null imzo to'g'ri, yangi va foydalanuvchi ruxsat etilgan bo'lsa — Telegram user ma'lumoti */
    public static function verify(string $initData, string $botToken, array $allowedIds, int $maxAgeSeconds = 86400): ?array
    {
        if ($initData === '' || $botToken === '') {
            return null;
        }
        parse_str($initData, $fields);
        $hash = (string) ($fields['hash'] ?? '');
        unset($fields['hash']);
        ksort($fields);
        $check = implode("\n", array_map(static fn ($k, $v) => "$k=$v", array_keys($fields), $fields));
        $secret = hash_hmac('sha256', $botToken, 'WebAppData', true);
        if ($hash === '' || !hash_equals(hash_hmac('sha256', $check, $secret), $hash)) {
            return null;
        }
        if (time() - (int) ($fields['auth_date'] ?? 0) > $maxAgeSeconds) {
            return null;
        }
        $user = json_decode((string) ($fields['user'] ?? ''), true);
        if (!is_array($user) || !isset($user['id'])) {
            return null;
        }
        return !$allowedIds || in_array((string) $user['id'], $allowedIds, true) ? $user : null;
    }

    /** .env dagi ruxsat etilgan Telegram ID'lar (bot bilan bir xil). */
    public static function allowedIds(): array
    {
        $raw = Env::get('TELEGRAM_ALLOWED_CHAT_IDS', '') ?: Env::get('TELEGRAM_CHAT_ID', '');
        return array_values(array_filter(array_map('trim', explode(',', (string) $raw))));
    }
}
