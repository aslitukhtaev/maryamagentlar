<?php
/**
 * Telegram bot — orchestrator (Manager) orqali TABIIY SUHBAT + tugmalar.
 *
 * Ishga tushirish:  php bin/bot.php
 * Doimiy ishlab turishi kerak — terminalni yopmang (yoki fon rejimida ishga tushiring).
 * To'xtatish: Ctrl+C
 *
 * Ishlash tartibi: Telegram'dan yangi xabar (yoki tugma bosilishi) keladi -> Manager (AI)
 * uni tahlil qiladi -> yoki oddiy javob beradi (kerak bo'lsa tugmalar bilan), yoki
 * kompaniya faktini saqlaydi, yoki Copywriter'ni chaqirib tayyor matnlarni fayl qilib
 * yuboradi.
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Maryam\Agents\ContentPlanner;
use Maryam\Agents\Copywriter;
use Maryam\Agents\GraphicDesigner;
use Maryam\Agents\Manager;
use Maryam\Brief;
use Maryam\Env;
use Maryam\Http;
use Maryam\Marketing;
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
$planner = new ContentPlanner($ai, $store, $brand, $tones);

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

/**
 * Haftalik kontent paketi: Kontent-strateg reja tuzadi, Copywriter har band uchun tayyor
 * matn yozadi. Avval qisqa reja xabar bo'lib, keyin to'liq paket fayl bo'lib keladi.
 */
function sendWeeklyPack(string $token, ContentPlanner $planner, string $chatId, string $wishes = ''): void
{
    $lastPing = 0;
    $plan = $planner->run(new DateTimeImmutable('today'), [
        'wishes' => $wishes,
        'progress' => function () use ($token, $chatId, &$lastPing) {
            if (time() - $lastPing >= 4) {
                tgCall($token, 'sendChatAction', ['chat_id' => $chatId, 'action' => 'typing']);
                $lastPing = time();
            }
        },
    ]);

    $tg = new Telegram($token, $chatId);
    $meta = ['topic' => 'haftalik-reja-' . $plan['week'], 'id' => 0];
    $path = Output::save($meta, 'kontent-paket.txt', ContentPlanner::toText($plan));
    Output::save($meta, 'kontent-paket.json', json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $tg->send(ContentPlanner::planSummary($plan));
    $tg->sendDocument($path, "📦 Haftalik kontent paketi — hammasi e'lon qilishga tayyor. Yoqmaganini ayting, qayta yozamiz.");
}

/**
 * Bitta "foydalanuvchi xabari" (matn yoki tugma bosilishi natijasida hosil bo'lgan matn)ni
 * to'liq qayta ishlaydi: Manager'dan tezkor qaror oladi, javob yuboradi (kerak bo'lsa
 * tugmalar bilan), va agar brif tayyor bo'lsa — Copywriter'ni fon jarayonida ishga tushirib,
 * natijani fayl qilib yuboradi.
 */
function processTurn(string $token, Manager $manager, ContentPlanner $planner, $store, $ai, array $brand, string $chatId, string $text, array $tones): void
{
    tgCall($token, 'sendChatAction', ['chat_id' => $chatId, 'action' => 'typing']);

    $decision = $manager->decide($chatId, $text);
    $params = ['chat_id' => $chatId, 'text' => $decision['reply']];
    if ($decision['ask_field'] !== '') {
        $keyboard = Telegram::fieldKeyboard($decision['ask_field'], $tones);
        if ($keyboard !== null) {
            $params['reply_markup'] = $keyboard;
        }
    }
    tgCall($token, 'sendMessage', $params);
    echo "   qaror: {$decision['action']}" . ($decision['ask_field'] ? " (so'ralmoqda: {$decision['ask_field']})" : '') . "\n";

    if ($decision['action'] === 'run_plan') {
        sendWeeklyPack($token, $planner, $chatId, $decision['plan_wishes']);
        return;
    }

    // Agar brif to'liq bo'lsa — Copywriter fon jarayonida ishlaydi (bir necha daqiqa
    // davom etishi mumkin, shuning uchun tezkor javobdan KEYIN, alohida ishga tushadi).
    if ($decision['action'] === 'run_copywriter' && $decision['brief']) {
        $lastPing = 0;
        $cw = $manager->runCopywriter($decision['brief'], [
            // Har bosqichda Telegram'ga "yozyapti..." signalini yangilab turamiz,
            // aks holda Telegram 5 soniyadan keyin uni o'chirib qo'yadi
            'progress' => function () use ($token, $chatId, &$lastPing) {
                if (time() - $lastPing >= 4) {
                    tgCall($token, 'sendChatAction', ['chat_id' => $chatId, 'action' => 'upload_document']);
                    $lastPing = time();
                }
            },
        ]);
        $brief = $store->brief($cw['brief_id']);
        $path = Output::save($brief, 'copywriter.txt', Copywriter::toText($cw));
        Output::save($brief, 'copywriter.json', json_encode($cw, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $tg = new Telegram($token, $chatId);
        $tg->sendDocument($path, "📋 Tayyor — brif #{$brief['id']}: {$brief['topic']}");

        // Grafik dizayner: matn bilan bir vaqtda rasm ham tayyorlanadi
        tgCall($token, 'sendChatAction', ['chat_id' => $chatId, 'action' => 'upload_photo']);
        $designer = new GraphicDesigner($ai, $store, $brand, $tones);
        $design = $designer->run($brief, $cw['strategy'] ?? [], ['progress' => static fn () => null]);

        if ($design['image_generated'] && $design['image_path']) {
            $tg->sendPhoto($design['image_path'], "🎨 " . ($design['alt_text'] ?: 'Post uchun rasm'));
        } elseif ($design['image_prompt'] !== '') {
            // Rasm generatsiya ishlamadi — o'rniga tayyor prompt matnini beramiz (loyihaviy talab bo'yicha)
            $promptPath = Output::dir($brief) . '/image-prompt.txt';
            file_put_contents($promptPath, $design['image_prompt']);
            $tg->sendDocument($promptPath, "🎨 Rasm generatsiya ishlamadi, lekin tayyor prompt matni (boshqa vositada ishlatish uchun):");
        }
    }
}

echo "🤖 Bot ishga tushdi (Telegram'da yozing yoki tugmalarni bosing). To'xtatish: Ctrl+C\n";
if ($allowed) {
    echo "   Ruxsat berilgan chat(lar): " . implode(', ', $allowed) . "\n";
} else {
    echo "   ⚠ TELEGRAM_CHAT_ID/TELEGRAM_ALLOWED_CHAT_IDS bo'sh — HAR KIM botga yoza oladi!\n";
}

$weekly = Marketing::settings()['weekly_plan'] ?? [];
$autoTriedWeek = '';

$offset = 0;
while (true) {
    // Har hafta belgilangan kun va soatda reja avtomatik tuziladi (shu hafta hali tuzilmagan bo'lsa)
    $now = new DateTimeImmutable();
    $week = ContentPlanner::weekKey($now);
    if (($weekly['enabled'] ?? false) && $ownerChatId !== '' && $autoTriedWeek !== $week
        && (int) $now->format('N') >= (int) ($weekly['weekday'] ?? 1)
        && (int) $now->format('G') >= (int) ($weekly['hour'] ?? 9)
        && $store->plan($week) === null) {
        $autoTriedWeek = $week; // xato bo'lsa ham shu hafta qayta-qayta urinmaymiz
        echo "🗓 Haftalik reja avtomatik tuzilmoqda ($week)...\n";
        try {
            tgCall($token, 'sendMessage', ['chat_id' => $ownerChatId, 'text' => "🗓 Yangi hafta! Kontent-reja va tayyor matnlarni tayyorlayapman, bir necha daqiqa..."]);
            sendWeeklyPack($token, $planner, $ownerChatId);
        } catch (Throwable $e) {
            echo "❌ Haftalik reja xatosi: {$e->getMessage()}\n";
            tgCall($token, 'sendMessage', ['chat_id' => $ownerChatId, 'text' => "⚠ Haftalik rejani tuzib bo'lmadi: {$e->getMessage()}\nQayta urinish uchun /reja yozing."]);
        }
    }

    $updates = tgCall($token, 'getUpdates', ['offset' => $offset, 'timeout' => 30]);

    if (!($updates['ok'] ?? false)) {
        echo "⚠ getUpdates xatosi: " . ($updates['description'] ?? 'nomaʼlum') . " — 5 soniyadan keyin qayta urinamiz\n";
        sleep(5);
        continue;
    }

    foreach ($updates['result'] ?? [] as $update) {
        $offset = $update['update_id'] + 1;

        // --- Tugma bosilishi (callback_query) ---
        $callback = $update['callback_query'] ?? null;
        if ($callback !== null) {
            $chatId = (string) ($callback['message']['chat']['id'] ?? '');
            // Telegram'ga "bosildi" deb darhol javob beramiz (aks holda tugmada "soat" aylanib turadi)
            tgCall($token, 'answerCallbackQuery', ['callback_query_id' => $callback['id']]);

            if ($allowed && !in_array($chatId, $allowed, true)) {
                continue;
            }

            [$field, $value] = array_pad(explode(':', (string) ($callback['data'] ?? ''), 2), 2, '');
            $label = match ($field) {
                'tourism_type' => $tones[$value]['label'] ?? $value,
                'goal' => Brief::GOALS[$value] ?? $value,
                'language' => Brief::LANGUAGES[$value] ?? $value,
                default => $value,
            };
            echo "→ [$chatId] (tugma) $label\n";

            try {
                // Tugma bosilishi ham xuddi oddiy xabardek Manager'ga boradi —
                // shunda suhbat tarixi va mantiq bitta joyda qoladi.
                processTurn($token, $manager, $planner, $store, $ai, $brand, $chatId, $label, $tones);
            } catch (Throwable $e) {
                echo "❌ Xato: {$e->getMessage()}\n";
                tgCall($token, 'sendMessage', ['chat_id' => $chatId, 'text' => "⚠ Xato yuz berdi: {$e->getMessage()}"]);
            }
            continue;
        }

        // --- Oddiy matnli xabar ---
        $message = $update['message'] ?? null;
        $text = trim((string) ($message['text'] ?? $message['caption'] ?? ''));
        if ($message === null || $text === '') {
            continue; // matnsiz rasm, ovoz va h.k. hozircha e'tiborsiz qoldiriladi
        }

        $chatId = (string) $message['chat']['id'];
        if ($allowed && !in_array($chatId, $allowed, true)) {
            echo "⛔ Ruxsatsiz chat'dan xabar e'tiborsiz qoldirildi: $chatId\n";
            continue;
        }

        // Kanaldan forward qilingan post — kompaniya uslubi namunasi sifatida saqlanadi
        if (isset($message['forward_origin']) || isset($message['forward_date'])) {
            $store->addHouseExample($text);
            echo "→ [$chatId] (namuna saqlandi)\n";
            tgCall($token, 'sendMessage', [
                'chat_id' => $chatId,
                'text' => "✅ Namuna sifatida saqlandi. Agentlar endi shu uslubda yozishga harakat qiladi. Eng yaxshi 10-20 ta postingizni shunday forward qiling.",
            ]);
            continue;
        }

        if ($text === '/reja' || str_starts_with($text, '/reja ')) {
            echo "→ [$chatId] /reja\n";
            try {
                tgCall($token, 'sendMessage', ['chat_id' => $chatId, 'text' => "🗓 Haftalik reja va tayyor matnlar tayyorlanmoqda, bir necha daqiqa..."]);
                sendWeeklyPack($token, $planner, $chatId, trim(substr($text, 5)));
            } catch (Throwable $e) {
                echo "❌ Xato: {$e->getMessage()}\n";
                tgCall($token, 'sendMessage', ['chat_id' => $chatId, 'text' => "⚠ Xato yuz berdi: {$e->getMessage()}"]);
            }
            continue;
        }
        echo "→ [$chatId] $text\n";

        if ($text === '/start') {
            tgCall($token, 'sendMessage', [
                'chat_id' => $chatId,
                'text' => "Salom! Men Maryam Travel marketing bo'limining boshlig'iman. Jamoam: kontent-strateg, copywriter, dizayner.\n\n"
                    . "• Oddiy tilda yozing: \"Umra 2027 uchun post kerak\"\n"
                    . "• /reja — haftalik kontent-reja + tayyor matnlar (har dushanba o'zim ham yuboraman)\n"
                    . "• /reja Ramazon Umrasiga urg'u — istak bilan reja\n"
                    . "• Kanalingizdagi eng yaxshi postlarni menga forward qiling — shu uslubda yozishni o'rganamiz\n"
                    . "• Kompaniya haqida fakt ayting — eslab qolaman",
            ]);
            $store->addChatMessage($chatId, 'bot', '/start javobi');
            continue;
        }

        try {
            processTurn($token, $manager, $planner, $store, $ai, $brand, $chatId, $text, $tones);
        } catch (Throwable $e) {
            echo "❌ Xato: {$e->getMessage()}\n";
            tgCall($token, 'sendMessage', ['chat_id' => $chatId, 'text' => "⚠ Xato yuz berdi: {$e->getMessage()}"]);
        }
    }
}
