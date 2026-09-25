<?php
/**
 * MARYAM TRAVEL · MARKETING BO'LIMI — O'QITISH MARKAZI (web)
 *
 *   php -S localhost:8000 -t public   ->  http://localhost:8000
 *
 * Tarmoqda ochsangiz, .env'ga WEB_PASSWORD=... yozing (login: istalgan, parol: shu).
 * Sinov rejimi (real AI'siz): .env'da WEB_MOCK=1.
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require ROOT . '/web/helpers.php';

use Maryam\Env;
use Maryam\GeminiMock;

$password = Env::get('WEB_PASSWORD', '');
if ($password !== '' && !hash_equals($password, (string) ($_SERVER['PHP_AUTH_PW'] ?? ''))) {
    header('WWW-Authenticate: Basic realm="Maryam Travel"');
    http_response_code(401);
    exit('Parol kerak.');
}

session_start();
set_time_limit(1800); // haftalik reja bir necha daqiqa davom etadi

['ai' => $ai, 'store' => $store, 'brand' => $brand, 'tones' => $tones] = appVertex();
if (Env::get('WEB_MOCK', '') === '1') {
    $ai = new GeminiMock();
}

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
    $design = $store->result((int) $_GET['img'], 'designer', 'final');
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
