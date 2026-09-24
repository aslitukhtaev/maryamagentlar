<?php
/**
 * Telegram bot — orchestrator (Manager) orqali TABIIY SUHBAT.
 *
 * Ishga tushirish:  php bin/bot.php
 * Doimiy ishlab turishi kerak — terminalni yopmang (yoki fon rejimida ishga tushiring).
 * To'xtatish: Ctrl+C
 *
 * Ishlash tartibi: Telegram'dan yangi xabar keladi -> Manager (AI) uni tahlil qiladi ->
 * yoki oddiy javob beradi, yoki kompaniya faktini saqlaydi, yoki Copywriter'ni chaqirib
 * tayyor matnlarni fayl qilib yuboradi.
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Maryam\Agents\Copywriter;
use Maryam\Agents\Manager;
use Maryam\Env;
use Maryam\Http;
use Maryam\Output;
use Maryam\Telegram;

$token = Env::get('TELEGRAM_BOT_TOKEN', '');
if ($token === '') {
    exit("Xato: TELEGRAM_BOT_TOKEN .env faylida yo'q.\n");
}

// Xavfsizlik: faqat ruxsat berilgan chat(lar) botni ishlata oladi (aks holda har kim
// tokenni bilib qolsa, sizning Google Cloud kreditingizni sarflab yuborishi mumkin).
$ownerChatId = Env::get('TELEGRAM_CHAT_ID', '');
$allowedRaw = Env::get('TELEGRAM_ALLOWED_CHAT_IDS', '') ?: $ownerChatId;
$allowed = array_filter(array_map('trim', explode(',', $allowedRaw)));

['ai' => $ai, 'store' => $store, 'brand' => $brand, 'tones' => $tones] = appVertex();
$manager = new Manager($ai, $store, $brand, $tones);

/** Telegram Bot API'ga oddiy so'rov. */
function tgCall(string $token, string $method, array $params = []): array
{
    $ch = curl_init("https://api.telegram.org/bot{$token}/{$method}");
    Http::applyCaBundle($ch);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 40,
        CURLOPT_POSTFIELDS => $params,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    return json_decode((string) $body, true) ?? [];
}

echo "🤖 Bot ishga tushdi (@ orqali Telegram'da yozing). To'xtatish: Ctrl+C\n";
if ($allowed) {
    echo "   Ruxsat berilgan chat(lar): " . implode(', ', $allowed) . "\n";
} else {
    echo "   ⚠ TELEGRAM_CHAT_ID/TELEGRAM_ALLOWED_CHAT_IDS bo'sh — HAR KIM botga yoza oladi!\n";
}

$offset = 0;
while (true) {
    $updates = tgCall($token, 'getUpdates', ['offset' => $offset, 'timeout' => 30]);

    if (!($updates['ok'] ?? false)) {
        echo "⚠ getUpdates xatosi: " . ($updates['description'] ?? 'nomaʼlum') . " — 5 soniyadan keyin qayta urinamiz\n";
        sleep(5);
        continue;
    }

    foreach ($updates['result'] ?? [] as $update) {
        $offset = $update['update_id'] + 1;
        $message = $update['message'] ?? null;
        if ($message === null || !isset($message['text'])) {
            continue; // rasm, ovoz va h.k. hozircha e'tiborsiz qoldiriladi
        }

        $chatId = (string) $message['chat']['id'];
        if ($allowed && !in_array($chatId, $allowed, true)) {
            echo "⛔ Ruxsatsiz chat'dan xabar e'tiborsiz qoldirildi: $chatId\n";
            continue;
        }

        $text = trim($message['text']);
        echo "→ [$chatId] $text\n";

        if ($text === '/start') {
            tgCall($token, 'sendMessage', [
                'chat_id' => $chatId,
                'text' => "Salom! Men Maryam Travel'ning marketing yordamchisiman. Menga oddiy tilda ehtiyojingizni yozing — masalan \"Umra 2027 uchun post kerak\" yoki kompaniya haqida yangi fakt ayting, men eslab qolaman.",
            ]);
            $store->addChatMessage($chatId, 'bot', '/start javobi');
            continue;
        }

        tgCall($token, 'sendChatAction', ['chat_id' => $chatId, 'action' => 'typing']);

        try {
            $result = $manager->handle($chatId, $text);
            tgCall($token, 'sendMessage', ['chat_id' => $chatId, 'text' => $result['reply']]);

            if ($result['ran_copywriter'] && $result['copywriter_result']) {
                $cw = $result['copywriter_result'];
                $brief = $store->brief($cw['brief_id']);
                $path = Output::save($brief, 'copywriter.txt', Copywriter::toText($cw));
                Output::save($brief, 'copywriter.json', json_encode($cw, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

                $tg = new Telegram($token, $chatId);
                $tg->sendDocument($path, "📋 Tayyor — brif #{$brief['id']}: {$brief['topic']}");
            }
        } catch (Throwable $e) {
            echo "❌ Xato: {$e->getMessage()}\n";
            tgCall($token, 'sendMessage', ['chat_id' => $chatId, 'text' => "⚠ Xato yuz berdi: {$e->getMessage()}"]);
        }
    }
}
