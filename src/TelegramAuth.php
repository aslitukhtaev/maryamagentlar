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

    /**
     * Pastki menyu (klaviatura) tugmasi Mini App'ga initData bermaydi — shuning uchun bot tugma
     * manziliga shu foydalanuvchi uchun imzolangan kalit qo'shadi (bot tokeni bilan, 30 kun).
     */
    public static function linkToken(string $chatId, string $botToken, int $days = 30): string
    {
        $exp = time() + $days * 86400;
        return "$chatId.$exp." . substr(hash_hmac('sha256', "$chatId.$exp", 'link:' . $botToken), 0, 32);
    }

    /** @return string|null kalit to'g'ri, muddati o'tmagan va foydalanuvchi ruxsat etilgan bo'lsa — uning ID si */
    public static function verifyLinkToken(string $key, string $botToken, array $allowedIds): ?string
    {
        [$id, $exp, $sig] = array_pad(explode('.', $key, 3), 3, '');
        if ($botToken === '' || !preg_match('/^-?\d+$/', $id) || !ctype_digit($exp) || (int) $exp < time()) {
            return null;
        }
        if (!hash_equals(substr(hash_hmac('sha256', "$id.$exp", 'link:' . $botToken), 0, 32), $sig)) {
            return null;
        }
        return !$allowedIds || in_array($id, $allowedIds, true) ? $id : null;
    }

    /** .env dagi ruxsat etilgan Telegram ID'lar (bot bilan bir xil). */
    public static function allowedIds(): array
    {
        $raw = Env::get('TELEGRAM_ALLOWED_CHAT_IDS', '') ?: Env::get('TELEGRAM_CHAT_ID', '');
        return array_values(array_filter(array_map('trim', explode(',', (string) $raw))));
    }
}
