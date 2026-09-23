<?php
/**
 * Vertex AI bilan test.
 * Qadam 1: https://console.cloud.google.com/apis/credentials > Service Account > JSON key download
 * Qadam 2: key.json faylini loyihaya qo'ying
 * Qadam 3: GOOGLE_APPLICATION_CREDENTIALS=/path/to/key.json php bin/test-vertex.php
 */

require 'src/bootstrap.php';

echo "=== VERTEX AI TEST ===\n\n";

// Agar .env'da key yo'lni qo'ygan bo'lsak
if (is_file('service-account-key.json')) {
    putenv('GOOGLE_APPLICATION_CREDENTIALS=' . realpath('service-account-key.json'));
}

try {
    $ai = appVertex()['ai'];
    echo "✓ Vertex AI initialized ({$ai->fastModel})\n";
    echo "Tekshirayotgan...\n";
    
    $result = $ai->json("JSON qaytar: {\"ok\":true}", "test", 0.2);
    echo "✓ OK! Model: " . ($result['model_used'] ?? $result['model']) . "\n";
    echo "  Tokens: {$result['tokens_in']} -> {$result['tokens_out']}\n";
    echo "  Vaqt: {$result['ms']}ms\n";
} catch (Throwable $e) {
    echo "✗ Xato: " . $e->getMessage() . "\n\n";
    echo "QANDAY TUZATISH:\n";
    echo "  1. Service account JSON key'ni loihaya qo'ying (service-account-key.json nomi bilan)\n";
    echo "  2. Yoki: GOOGLE_APPLICATION_CREDENTIALS=/path/to/key.json php bin/test-vertex.php\n";
    echo "  3. Yoki: gcloud auth application-default login qilip, keyin\n";
    echo "          ~/.config/gcloud/application_default_credentials.json avtomatik ishlatiladi\n";
}
