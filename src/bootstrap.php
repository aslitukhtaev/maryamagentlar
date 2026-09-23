<?php
/**
 * Loyihaning "yuragi": har bir kirish nuqtasi (CLI yoki web) shu faylni ulaydi.
 * U: 1) klasslarni avtomatik yuklaydi, 2) .env ni o'qiydi, 3) tayyor servislarni beradi.
 * Composer ishlatilmaydi — loyiha sodda bo'lishi uchun.
 */

declare(strict_types=1);

define('ROOT', dirname(__DIR__));

// "Maryam\Agents\Copywriter" -> src/Agents/Copywriter.php
spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'Maryam\\')) {
        $file = ROOT . '/src/' . str_replace('\\', '/', substr($class, 7)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

Maryam\Env::load(ROOT . '/.env');

/** Barcha agentlar uchun umumiy "qutilar": AI mijoz, baza, brend va ton sozlamalari. */
function app(): array
{
    static $app = null;
    if ($app === null) {
        $model = Maryam\Env::get('GEMINI_MODEL', 'gemini-3.8-flash');
        $app = [
            'ai'    => new Maryam\Gemini(
                Maryam\Env::get('GEMINI_API_KEY', ''),
                $model,
                Maryam\Env::get('GEMINI_MODEL_SMART') ?: $model,
                array_filter(array_map('trim', explode(',', Maryam\Env::get('GEMINI_MODEL_FALLBACK', '')))),
            ),
            'store' => new Maryam\Store(Maryam\Database::connect(ROOT . '/' . Maryam\Env::get('DB_PATH', 'data/maryam.db'))),
            'brand' => require ROOT . '/config/brand.php',
            'tones' => require ROOT . '/config/tones.php',
        ];
    }
    return $app;
}

/**
 * Vertex AI (Google Cloud billing va trial krediti bilan).
 * Developer API o'rniga buni ishlatsangiz — $300 trial krediti ishlatiladi.
 */
function appVertex(): array
{
    static $app = null;
    if ($app === null) {
        // Zaxira qiymat: .env eskirgan/bo'sh bo'lsa ham loyiha ishlab tursin
        $projectId = Maryam\Env::get('GOOGLE_CLOUD_PROJECT_ID', '') ?: 'project-990aebdb-5252-4043-862';
        $location = Maryam\Env::get('GOOGLE_CLOUD_LOCATION', 'us-central1');

        // MUHIM: Vertex AI'ning "publisher model" katalogi Developer API
        // (generativelanguage.googleapis.com) bilan bir xil model nomlarini
        // qo'llab-quvvatlamaydi — masalan "gemini-3.8-flash" Developer API'da bor,
        // lekin Vertex'da yo'q bo'lishi mumkin. Shuning uchun Vertex uchun ALOHIDA,
        // Vertex'da uzoq vaqtdan beri barqaror turgan nomlar ishlatiladi, va agar
        // biri topilmasa (404), kod avtomatik keyingisiga o'tadi (generate() ichida).
        $fallback = Maryam\Env::get('VERTEX_MODEL_FALLBACK', '');
        $fallbackModels = $fallback !== ''
            ? array_filter(array_map('trim', explode(',', $fallback)))
            : ['gemini-2.5-flash', 'gemini-2.0-flash-001', 'gemini-1.5-flash-002', 'gemini-1.5-flash-001'];

        $app = [
            'ai'    => new Maryam\GeminiVertex(
                $projectId,
                $location,
                Maryam\Env::get('VERTEX_MODEL', 'gemini-2.0-flash-001'),
                Maryam\Env::get('VERTEX_MODEL_SMART', 'gemini-2.5-pro'),
                $fallbackModels,
            ),
            'store' => new Maryam\Store(Maryam\Database::connect(ROOT . '/' . Maryam\Env::get('DB_PATH', 'data/maryam.db'))),
            'brand' => require ROOT . '/config/brand.php',
            'tones' => require ROOT . '/config/tones.php',
        ];
    }
    return $app;
}
