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

$password = Env::get('WEB_PASSWORD', '');
if (empty($_SESSION['auth']) && $password !== '') {
    if (hash_equals($password, (string) ($_SERVER['PHP_AUTH_PW'] ?? ''))) {
        $_SESSION['auth'] = 'password';
    } elseif (isset($_GET['tg'])) {
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

const PAGES = [
    'home' => 'Bosh sahifa',
    'studio' => 'Studiya',
    'reja' => 'Haftalik reja',
    'shablonlar' => 'Shablonlar',
    'qoidalar' => 'Qoidalar',
    'namunalar' => 'Oltin namunalar',
    'promptlar' => 'Promptlar',
    'katalog' => 'Katalog',
    'bilimlar' => 'Bilimlar',
];
$page = isset(PAGES[$_GET['p'] ?? '']) ? $_GET['p'] : 'home';

if ($page === 'home' && ($_GET['img'] ?? '') !== '') {
    // Dizayner yaratgan rasm (faqat output/ papkasidan)
    $design = $store->result((int) $_GET['img'], 'designer', isset($_GET['v']) ? 'variant_' . (int) $_GET['v'] : 'final');
    $path = $design['image_path'] ?? null;
    $real = $path ? realpath($path) : false;
    if ($real && str_starts_with($real, realpath(ROOT . '/output') . DIRECTORY_SEPARATOR)) {
        header('Content-Type: ' . (str_ends_with($real, '.png') ? 'image/png' : 'image/jpeg'));
        readfile($real);
    } else {
        http_response_code(404);
    }
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
