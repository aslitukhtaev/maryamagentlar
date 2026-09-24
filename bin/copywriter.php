<?php
/**
 * Copywriter agentini terminaldan ishga tushirish.
 *
 *   php bin/copywriter.php                 — brif so'raladi va matnlar yoziladi
 *   php bin/copywriter.php rate 12 5 "zo'r hook"   — 12-variantga 5 baho berish
 *   php bin/copywriter.php show 3          — 3-brif natijasini qayta ko'rsatish
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Maryam\Agents\Copywriter;
use Maryam\Brief;
use Maryam\Output;

// Vertex AI ishlatiladi (Google Cloud $300 trial krediti bilan).
// Agar Developer API kalitingiz (.env'dagi GEMINI_API_KEY) ishlaydigan bo'lsa,
// buni app()'ga almashtirishingiz mumkin — lekin Vertex tasdiqlangan yo'l.
['ai' => $ai, 'store' => $store, 'brand' => $brand, 'tones' => $tones] = appVertex();
$command = $argv[1] ?? 'run';

// --- Baholash: agent sizning didingizni shu orqali o'rganadi ---
if ($command === 'rate') {
    [$id, $rating, $feedback] = [(int) ($argv[2] ?? 0), (int) ($argv[3] ?? 0), $argv[4] ?? ''];
    if ($id < 1 || $rating < 1 || $rating > 5) {
        exit("Foydalanish: php bin/copywriter.php rate <variant_id> <1-5> \"izoh\"\n");
    }
    echo $store->rate($id, $rating, $feedback) ? "Baho saqlandi. Rahmat!\n" : "Bunday variant topilmadi.\n";
    exit;
}

if ($command === 'show') {
    $result = $store->result((int) ($argv[2] ?? 0), Copywriter::NAME, 'final');
    exit($result ? Copywriter::toText($result) : "Natija topilmadi.\n");
}

// --- Asosiy ish: brif so'rash ---
function ask(string $question, string $default = ''): string
{
    $answer = trim((string) readline($question . ($default !== '' ? " [$default]" : '') . ': '));
    return $answer !== '' ? $answer : $default;
}

function choose(string $question, array $options, string $default): string
{
    echo "\n$question\n";
    $keys = array_keys($options);
    foreach ($keys as $i => $key) {
        echo '  ' . ($i + 1) . ") {$options[$key]}\n";
    }
    $n = (int) ask('Raqamni tanlang', (string) (array_search($default, $keys) + 1));
    return $keys[$n - 1] ?? $default;
}

echo "\n=== MARYAM TRAVEL · COPYWRITER AGENT ===\n\n";
$input = [];
$input['topic'] = ask('Mavzu (masalan: Umra 2027 — erta bron aksiyasi)');
$input['tourism_type'] = choose('Turizm turi:', array_map(fn ($t) => $t['label'], $tones), 'umra');
$input['goal'] = choose('Maqsad:', Brief::GOALS, 'lid');
echo "\nTafsilotlar — narx, sanalar, nima kiradi, bonuslar, chegirma muddati va h.k.\n";
echo "(Qancha aniq bo'lsa, matn shuncha kuchli. Agent faktlarni o'zi to'qimaydi.)\n";
$input['details'] = ask('Tafsilotlar');
$input['audience'] = ask("Maxsus auditoriya (bo'sh qoldirsangiz — standart)");
$input['language'] = choose('Til:', Brief::LANGUAGES, $tones[$input['tourism_type']]['language']);

try {
    $brief = Brief::normalize($input, $tones);
    $brief['id'] = $store->saveBrief($brief);
    echo "\n";

    $agent = new Copywriter($ai, $store, $brand, $tones);
    $started = microtime(true);
    $result = $agent->run($brief, ['progress' => fn ($m) => print("  → $m\n")]);

    $text = Copywriter::toText($result);
    $path = Output::save($brief, 'copywriter.txt', $text);
    Output::save($brief, 'copywriter.json', json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    echo "\n$text\n";
    printf("Tayyor! (%.0f soniya) Saqlandi: %s\n", microtime(true) - $started, $path);
    echo "Variantlarni baholang — agent keyingi safar sizning didingizga moslashadi:\n";
    echo "  php bin/copywriter.php rate <ID> <1-5> \"izoh\"\n";

    // Telegram sozlangan bo'lsa (.env'da TELEGRAM_BOT_TOKEN/CHAT_ID) — natijani darhol yuboramiz
    if (Maryam\Telegram::isConfigured()) {
        try {
            $tg = Maryam\Telegram::fromEnv();
            $tg->sendDocument($path, "📋 Copywriter natijasi — brif #{$brief['id']}: {$brief['topic']}");
            echo "📨 Telegram'ga yuborildi.\n";
        } catch (Throwable $tgError) {
            echo "⚠ Telegram'ga yuborilmadi: {$tgError->getMessage()}\n";
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, "\nXato: {$e->getMessage()}\n");
    exit(1);
}
