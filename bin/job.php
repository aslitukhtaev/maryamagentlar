<?php
/**
 * Bot topshirgan bitta uzoq ishni bajaradi (bot o'zi ishga tushiradi):  php bin/job.php <job_id>
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Maryam\BotJobs;
use Maryam\Env;

set_time_limit(0);
['ai' => $ai, 'store' => $store, 'brand' => $brand, 'tones' => $tones] = appVertex();
(new BotJobs(Env::get('TELEGRAM_BOT_TOKEN', '') ?? '', $store, $ai, $brand, $tones))->run((int) ($argv[1] ?? 0));
