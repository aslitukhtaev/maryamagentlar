<?php
/**
 * Telegram bot — Maryam Travel marketing bo'limi.
 *
 *   php bin/bot.php      (serverda systemd xizmati sifatida doimiy ishlaydi)
 *
 * Menyu, tugmali oqimlar va baholash — src/TelegramBot.php; AI yozadigan uzoq ishlar
 * alohida jarayonda — src/BotJobs.php (bin/job.php), shuning uchun bot hech qachon qotmaydi.
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Maryam\Env;
use Maryam\TelegramBot;

$token = Env::get('TELEGRAM_BOT_TOKEN', '');
if ($token === '') {
    exit("Xato: TELEGRAM_BOT_TOKEN .env faylida yo'q.\n");
}

['ai' => $ai, 'store' => $store, 'brand' => $brand, 'tones' => $tones] = appVertex();
$owner = Env::get('TELEGRAM_CHAT_ID', '') ?? '';

(new TelegramBot($token, $store, $ai, $brand, $tones, $owner, Env::get('TELEGRAM_ALLOWED_CHAT_IDS', '') ?? ''))->run();
