<?php
/**
 * DEMO — Mock AI bilan Copywriter agenti to'liq jarayoni.
 * Haqiqiy Gemini'ni kutmay, darhol natijalarni ko'rasiz.
 */

require 'src/bootstrap.php';

use Maryam\{Brief, Output, GeminiMock};
use Maryam\Agents\Copywriter;

echo "\n=== MARYAM TRAVEL · COPYWRITER AGENT (DEMO) ===\n\n";

// Mock AI'ni ishlat
$ai = new GeminiMock();
['store' => $store, 'brand' => $brand, 'tones' => $tones] = app();

// Brif
$brief = Brief::normalize([
    'topic' => 'Umra 2027 — erta bron aksiyasi',
    'tourism_type' => 'umra',
    'goal' => 'lid',
    'details' => "Jo'nash: 2027 yil fevral va mart. Erta bron qilganlarga chegirma — 31-dekabrgacha. Paketga kiradi: aviabilet, viza, mehmonxona (Makka va Madina), transfer, o'zbek tilida gid. Muddati: 14 kun.",
    'language' => 'uz',
], $tones);

$brief['id'] = $store->saveBrief($brief);
echo "📋 Brif #{$brief['id']}: {$brief['topic']}\n";
echo "   Turi: {$tones[$brief['tourism_type']]['label']} | Maqsad: " . Brief::GOALS[$brief['goal']] . "\n\n";

$started = microtime(true);
try {
    echo "⏳ Copywriter ishlamoqda (3 bosqich)...\n";
    $result = (new Copywriter($ai, $store, $brand, $tones))->run($brief, [
        'progress' => fn($m) => print("   $m\n"),
    ]);

    $took = microtime(true) - $started;
   printf("   %.1f s\n", $took);
    echo "   " . count($result['hooks']) . " hook, " . count($result['variants']) . " variant\n";
    echo "   O'rtacha ball: " . round(array_sum(array_column($result['variants'], 'score')) / count($result['variants']), 1) . "/10\n\n";

    // Natijani ko'rsat
    $txt = Copywriter::toText($result);
    Output::save($brief, 'copywriter.txt', $txt);
    Output::save($brief, 'copywriter.json', json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    echo $txt;
    echo "\n✅ Saqlandi: " . Output::dir($brief) . "/copywriter.txt\n";
    echo "\n--- BAHOLASH ---\n";
    echo "Variantlarni baholang:\n";
    foreach ($result['variants'] as $v) {
        echo "   php bin/copywriter.php rate {$v['db_id']} <1-5> \"izoh\"\n";
    }
} catch (Throwable $e) {
    echo "❌ Xato: " . $e->getMessage() . "\n";
    exit(1);
}
