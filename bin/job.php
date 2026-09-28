<?php
/**
 * Bitta uzoq ishni fonda bajaradi (bot yoki web ilova o'zi ishga tushiradi):  php bin/job.php <job_id>
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Maryam\BotJobs;
use Maryam\Env;

set_time_limit(0);
['ai' => $ai, 'store' => $store, 'brand' => $brand, 'tones' => $tones] = appVertex();
$id = (int) ($argv[1] ?? 0);
if (str_starts_with((string) ($store->job($id)['type'] ?? ''), 'web_')) {
    (new Maryam\WebJobs($store, $ai, $brand, $tones))->run($id);
} else {
    (new BotJobs(Env::get('TELEGRAM_BOT_TOKEN', '') ?? '', $store, $ai, $brand, $tones))->run($id);
}
