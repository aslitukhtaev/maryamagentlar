<?php
/**
 * MARYAM TRAVEL · MARKETING BO'LIMI — O'QITISH MARKAZI (web)
 *
 *   php -S localhost:8000 -t public   ->  http://localhost:8000
 *
 * Kirish: brauzerda WEB_PASSWORD (login istalgan), Telegram bot ichida (Mini App) — parolsiz,
 * Telegram imzosi orqali. Sinov rejimi (real AI'siz): .env'da AI_MOCK=1.
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require ROOT . '/web/helpers.php';

use Maryam\Env;
use Maryam\TelegramAuth;

// Sessiya 30 kun (Telegram ichida har safar qayta kirmaslik uchun). Tizim cron'i standart
// papkadagi sessiyalarni 24 daqiqada o'chiradi — shuning uchun o'z papkamizda saqlaymiz.
$https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
@mkdir(ROOT . '/data/sessions', 0700, true);
ini_set('session.save_path', ROOT . '/data/sessions');
ini_set('session.gc_maxlifetime', (string) (86400 * 30));
session_set_cookie_params(['lifetime' => 86400 * 30, 'httponly' => true, 'secure' => $https, 'samesite' => $https ? 'None' : 'Lax']);
session_start();

// Telegram Mini App: imzo tekshiriladi, parol so'ralmaydi
if (isset($_GET['tglogin'])) {
    header('Content-Type: application/json');
    $user = TelegramAuth::verify((string) ($_POST['init_data'] ?? ''), (string) Env::get('TELEGRAM_BOT_TOKEN', ''), TelegramAuth::allowedIds());
    if (!$user) {
        session_destroy();
        http_response_code(403);
        exit('{"ok":false}');
    }
    session_regenerate_id(true);
    $_SESSION['auth'] = 'telegram:' . $user['id'];
    $_SESSION['tg'] = true;
    exit('{"ok":true}');
}

// Botning pastki menyu tugmasi: manzildagi imzolangan kalit bilan kirish (initData berilmaydi)
if (empty($_SESSION['auth']) && isset($_GET['k'])) {
    $id = TelegramAuth::verifyLinkToken((string) $_GET['k'], (string) Env::get('TELEGRAM_BOT_TOKEN', ''), TelegramAuth::allowedIds());
    if ($id !== null) {
        session_regenerate_id(true);
        $_SESSION['auth'] = "telegram:$id";
        $_SESSION['tg'] = true;
    }
}

$password = Env::get('WEB_PASSWORD', '');
if (empty($_SESSION['auth']) && $password !== '') {
    if (hash_equals($password, (string) ($_SERVER['PHP_AUTH_PW'] ?? ''))) {
        $_SESSION['auth'] = 'password';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['login']) && !isset($_SERVER['PHP_AUTH_PW'])) {
        // Avval Telegram imzosi bilan kirishga urinamiz (Mini App); Telegram tashqarisida sahifa ?login=1 ga o'tadi
        session_destroy();
        require ROOT . '/web/tg-login.php';
        exit;
    } else {
        session_destroy(); // kirmagan so'rovlar sessiya fayli qoldirmasin
        header('WWW-Authenticate: Basic realm="Maryam Travel"');
        http_response_code(401);
        exit('Parol kerak.');
    }
}
if (isset($_GET['tg'])) {
    $_SESSION['tg'] = true;
}
set_time_limit(1800); // haftalik reja bir necha daqiqa davom etadi

['ai' => $ai, 'store' => $store, 'brand' => $brand, 'tones' => $tones] = appVertex();

// Ilova o'z manzilini eslab qoladi — bot shu manzil bilan "Ilovani ochish" tugmasini yasaydi (.env shart emas)
$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
if ($https && !empty($_SESSION['auth']) && preg_match('/^[a-z0-9.-]+$/i', $host) && $store->meta('webapp_url') !== "https://$host") {
    $store->setMeta('webapp_url', "https://$host");
}

const PAGES = [
    'studio' => 'Copywriter',
    'reja' => 'Kontent-strateg',
    'brend' => 'Dizayner',
    'oqitish' => "O'qitish studiyasi",
    'shablonlar' => 'Shablonlar',
    'qoidalar' => 'Qoidalar',
    'namunalar' => 'Namunalar',
    'promptlar' => 'Kengaytirilgan',
    'katalog' => 'Turlar',
    'bilimlar' => 'Faktlar',
];
$page = ($_GET['p'] ?? '') === 'home' ? 'oqitish' : (isset(PAGES[$_GET['p'] ?? '']) ? $_GET['p'] : 'studio');

if (($_GET['img'] ?? '') !== '') {
    // Dizayner yaratgan rasm (faqat output/ papkasidan)
    $design = $store->result((int) $_GET['img'], 'designer', isset($_GET['v']) ? 'variant_' . (int) $_GET['v'] : 'final');
    $path = $design[isset($_GET['card']) ? 'card_path' : 'image_path'] ?? null;
    $real = $path ? realpath($path) : false;
    if ($real && str_starts_with($real, realpath(ROOT . '/output') . DIRECTORY_SEPARATOR)) {
        header('Content-Type: ' . (str_ends_with($real, '.png') ? 'image/png' : 'image/jpeg'));
        readfile($real);
    } else {
        http_response_code(404);
    }
    exit;
}

// Grid namunasi va logolar (rasm sifatida)
if (isset($_GET['render']) || isset($_GET['asset'])) {
    if (isset($_GET['asset'])) {
        $file = $_GET['asset'] === 'ref'
            ? Maryam\BrandAssets::refPath((string) ($_GET['n'] ?? ''), isset($_GET['sm']))
            : ROOT . Maryam\PostRenderer::BRAND_DIR . '/' . ($_GET['asset'] === 'logo-white' ? 'logo-white.png' : 'logo.png');
        if (!$file || !is_file($file)) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: ' . (str_ends_with($file, '.jpg') ? 'image/jpeg' : 'image/png'));
        header('Cache-Control: no-cache');
        readfile($file);
        exit;
    }
    if (!function_exists('imagecreatetruecolor')) {
        http_response_code(503); // serverda php-gd yo'q
        exit;
    }
    $samples = Maryam\PostRenderer::gridSamples(Maryam\Marketing::products());
    [$layout, $data] = $samples[(int) ($_GET['i'] ?? 0)] ?? $samples[0];
    header('Content-Type: image/png');
    if (isset($_GET['dl'])) {
        header('Content-Disposition: attachment; filename="maryam-' . $layout . '-' . (int) ($_GET['i'] ?? 0) . '.png"');
    }
    echo Maryam\PostRenderer::forBrand($brand)->render($layout, $data, Maryam\PostRenderer::colors($store));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit("Sahifa eskirgan — orqaga qaytib, qayta urinib ko'ring.");
    }
    try {
        require ROOT . '/web/actions.php';
    } catch (Throwable $ex) {
        flash('Xato: ' . $ex->getMessage(), 'error');
        $_SESSION['old'] = $_POST; // forma qayta to'ldirilishi uchun
        redirect(url(['p' => $page] + array_intersect_key($_GET, ['id' => 1, 'name' => 1, 'brief' => 1])));
    }
}

require ROOT . '/web/layout.php';
