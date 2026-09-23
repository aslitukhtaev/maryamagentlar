<?php
/**
 * Vertex AI'da SIZNING loyihangiz/regioningiz uchun haqiqatda mavjud
 * bo'lgan Gemini modellarini ko'rsatadi. Model nomi bilan xato chiqsa
 * (masalan "was not found or your project does not have access"),
 * shu skriptni ishga tushiring va ro'yxatdan to'g'ri nomni oling.
 *
 * Ishlatish: php bin/list-vertex-models.php
 */

require __DIR__ . '/../src/bootstrap.php';

echo "=== VERTEX AI: MAVJUD GEMINI MODELLARI ===\n\n";

try {
    $ai = appVertex()['ai'];
    echo "Loyiha/region: {$ai->fastModel} so'ralmoqda... (bir necha soniya)\n\n";

    $models = $ai->listModels();

    if (!$models) {
        echo "Hech qanday 'gemini' modeli topilmadi. Ehtimol:\n";
        echo "  - Vertex AI API loyihada yoqilmagan (aiplatform.googleapis.com)\n";
        echo "  - Hisobda Vertex AI'ga ruxsat yo'q\n";
        exit(1);
    }

    echo "Topildi: " . count($models) . " ta model\n\n";
    foreach ($models as $m) {
        $mark = $m['generateContent'] ? '✓' : ' ';
        echo "  [$mark] {$m['name']}\n";
    }

    echo "\n>>> .env faylidagi GEMINI_MODEL va GEMINI_MODEL_FALLBACK'ga\n";
    echo ">>> yuqoridagi ro'yxatdan (faqat oxirgi qismini, masalan 'gemini-1.5-flash-002')\n";
    echo ">>> nom qo'ying.\n";
} catch (Throwable $e) {
    echo "✗ Xato: " . $e->getMessage() . "\n";
    exit(1);
}
