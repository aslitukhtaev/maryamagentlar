<?php

declare(strict_types=1);

namespace Maryam;

use Maryam\Agents\ContentPlanner;
use Maryam\Agents\Copywriter;
use Throwable;

/**
 * Telegram botning ko'rinishi: menyu, tugmalar, natija xabarlari, tushunarli xato matnlari.
 * Bot (tezkor javoblar) ham, fon ishlari (bin/job.php) ham shu yerdan foydalanadi.
 */
final class BotUi
{
    public const BTN_POST = '✍️ Post yozish';
    public const BTN_PLAN = '🗓 Haftalik reja';
    public const BTN_RECENT = '📂 Oxirgi ishlar';
    public const BTN_APP = "📱 Ilovani ochish";
    public const BTN_HELP = '❓ Yordam';

    private const FORMAT_LABELS = ['post' => 'POST', 'reels' => 'REELS', 'karusel' => 'KARUSEL', 'reklama' => 'REKLAMA'];

    public static function webAppUrl(): string
    {
        $url = trim((string) Env::get('WEBAPP_URL', ''));
        return str_starts_with($url, 'https://') ? rtrim($url, '/') . '/?tg=1' : '';
    }

    public static function mainKeyboard(): array
    {
        $app = self::webAppUrl();
        return [
            'keyboard' => [
                [['text' => self::BTN_POST], ['text' => self::BTN_PLAN]],
                // Klaviatura tugmasi Mini App'ga imzo (initData) bermaydi — shuning uchun oddiy tugma,
                // bosilganda xabar ichidagi "Ochish" (web_app) tugmasi yuboriladi
                [['text' => self::BTN_RECENT], ['text' => $app !== '' ? self::BTN_APP : self::BTN_HELP]],
                ...($app !== '' ? [[['text' => self::BTN_HELP]]] : []),
            ],
            'resize_keyboard' => true,
            'is_persistent' => true,
            'input_field_placeholder' => 'Menyudan tanlang yoki vazifani yozing',
        ];
    }

    /** Mini App'ni ochadigan xabar-ichi tugma (imzo bilan ochiladi). */
    public static function webAppButton(string $text = "📱 Ilovani ochish"): ?array
    {
        $app = self::webAppUrl();
        return $app !== '' ? ['inline_keyboard' => [[['text' => $text, 'web_app' => ['url' => $app]]]]] : null;
    }

    public static function inline(array $rows): array
    {
        return ['inline_keyboard' => array_map(
            static fn (array $row) => array_map(static fn (array $b) => ['text' => $b[0], 'callback_data' => $b[1]], $row),
            $rows
        )];
    }

    /** Bitta tayyor material: sarlavha + e'lon qilishga tayyor matn. */
    public static function variantMessage(array $v, string $title = ''): string
    {
        $kind = $v['kind'] === 'ad' ? 'REKLAMA' : (self::FORMAT_LABELS[$v['format'] ?? ''] ?? 'POST');
        $head = ($title !== '' ? "$title\n" : '') . "📝 $kind" . ($v['angle'] ? " · {$v['angle']}" : '') . " · muharrir bahosi {$v['score']}/10";
        $body = '';
        if ($v['kind'] === 'ad') {
            $body .= "Sarlavha: {$v['headline']}\nTavsif: {$v['description']}\nTugma: {$v['cta_button']}\n\n";
        }
        $body .= Copywriter::variantText($v);
        foreach ($v['warnings'] ?? [] as $w) {
            $body .= "\n⚠ $w";
        }
        return "$head\n━━━━━━━━━━\n" . trim($body);
    }

    public static function variantKeyboard(int $variantId, ?int $rating = null): array
    {
        $mark = static fn (int $n, string $label) => [($rating === $n ? '✅ ' : '') . $label, "rate:$variantId:$n"];
        return self::inline([
            [$mark(5, "5 A'lo"), $mark(4, '4 Yaxshi'), $mark(3, "3 O'rta"), $mark(2, '2 Yomon')],
            [['🏅 Oltin namuna', "gold:$variantId"], ['🎨 Dizayn', "design:$variantId"]],
        ]);
    }

    /** Copywriter natijasini xabarlar qilib yuboradi: har variant alohida, ostida tugmalar. */
    public static function deliverResult(Telegram $tg, array $result, array $brief): void
    {
        foreach ($result['variants'] as $v) {
            $tg->message(self::variantMessage($v), self::variantKeyboard((int) $v['db_id']));
        }
        $note = '';
        if (!empty($result['placeholders'])) {
            $note = "\n✏ Qo'lda to'ldiring: " . implode(', ', $result['placeholders'])
                  . "\n(Ilovadagi Kompaniya bo'limiga kiritsangiz, keyingi safar o'zi yozadi.)";
        }
        if (count($result['hooks'] ?? []) > 1) {
            $note .= "\n\n💡 Boshqa birinchi qatorlar:\n— " . implode("\n— ", array_slice($result['hooks'], 0, 5));
        }
        $tg->message("✅ Tayyor: {$brief['topic']}\nBaholang — agentlar didingizni o'rganadi.$note", self::inline([
            [['🔁 Qayta yozish', "again:{$brief['id']}"], ['✍️ Yangi post', 'newpost']],
        ]));
    }

    public static function deliverPlan(Telegram $tg, array $plan): void
    {
        $tg->message(ContentPlanner::planSummary($plan));
        foreach ($plan['items'] as $i => $item) {
            $title = ($i + 1) . ". {$item['day']} — {$item['topic']}";
            if (isset($item['error'])) {
                $tg->message("$title\n⚠ Tayyorlanmadi: " . self::friendlyError($item['error']));
            } elseif (!empty($item['content'])) {
                $tg->message(self::variantMessage($item['content'], $title), self::variantKeyboard((int) $item['content']['db_id']));
            }
        }
        $tg->message("✅ Haftalik reja tayyor ({$plan['week']}). Baholang yoki ilovada ko'ring.", self::inline([
            [['🔁 Qayta tuzish', 'plan:go'], ['🏠 Menyu', 'menu']],
        ]));
    }

    /** Texnik xatoni egasiga tushunarli tilga o'giradi (qisqa texnik izoh bilan — yordam so'rash uchun). */
    public static function friendlyError(Throwable|string $e): string
    {
        $raw = $e instanceof Throwable ? $e->getMessage() : $e;
        $human = match (true) {
            str_contains($raw, '429') => "AI limiti vaqtincha tugadi. 1-2 daqiqadan keyin qayta urinib ko'ring.",
            (bool) preg_match('/40[13]|Autentifikatsiya|Access token/i', $raw) => "Google Cloud'ga ulanishda ruxsat muammosi. Administrator serverda test-vertex.php ni tekshirsin.",
            (bool) preg_match('/503|band/i', $raw) => "AI hozir band. Bir ozdan keyin qayta urinib ko'ring.",
            (bool) preg_match('/aloqa|timed out|resolve|connect/i', $raw) => "AI bilan aloqa uzildi. Qayta urinib ko'ring.",
            str_contains($raw, 'JSON') => "AI javobi chala keldi. Qayta urinib ko'ring.",
            default => 'Kutilmagan xato yuz berdi.',
        };
        return $human . "\n(texnik: " . mb_substr($raw, 0, 120) . ')';
    }

    public static function helpText(): string
    {
        return "Men — Maryam Travel marketing bo'limi. Jamoam: kontent-strateg, copywriter, muharrir, dizayner.\n\n"
            . self::BTN_POST . " — tur yoki mavzu tanlaysiz, shablon tanlaysiz, 1-3 daqiqada tayyor matn keladi.\n"
            . self::BTN_PLAN . " — haftalik kontent-reja va har kun uchun tayyor material (har dushanba 09:00 da o'zim ham yuboraman).\n"
            . self::BTN_RECENT . " — oldingi natijalarni qayta ochish.\n"
            . self::BTN_APP . " — shablonlar, qoidalar, katalog, namunalar (agentlarni o'rgatish).\n\n"
            . "Boshqa usullar:\n"
            . "• Oddiy so'z bilan yozing: \"Vyetnam turi uchun reels kerak, 820\$ dan\"\n"
            . "• Kanalingizdagi eng yaxshi postni forward qiling — uslub namunasi qilib saqlayman\n"
            . "• Natijani baholang (5-2) — agentlar didingizni o'rganadi\n"
            . "• /bekor — boshlangan ishni bekor qilish";
    }
}
