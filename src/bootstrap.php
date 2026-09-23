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
        $model = Maryam\Env::get('GEMINI_MODEL', 'gemini-2.5-flash');
        $app = [
            'ai'    => new Maryam\Gemini(
                Maryam\Env::get('GEMINI_API_KEY', ''),
                $model,
                Maryam\Env::get('GEMINI_MODEL_SMART') ?: $model,
            ),
            'store' => new Maryam\Store(Maryam\Database::connect(ROOT . '/' . Maryam\Env::get('DB_PATH', 'data/maryam.db'))),
            'brand' => require ROOT . '/config/brand.php',
            'tones' => require ROOT . '/config/tones.php',
        ];
    }
    return $app;
}
