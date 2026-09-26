<?php

declare(strict_types=1);

namespace Maryam;

use DateTimeImmutable;
use Maryam\Agents\ContentPlanner;
use Maryam\Agents\Copywriter;
use Maryam\Agents\GraphicDesigner;
use Throwable;

/**
 * Bot uchun UZOQ ISHLAR (copywriter, haftalik reja, dizayn) — alohida jarayonda (bin/job.php)
 * bajariladi, shuning uchun bot shu paytda ham tugmalarga javob berishda davom etadi.
 * Jarayon holati bitta xabarda yangilanib boradi: "⏳ 2/3 Yozish...".
 */
final class BotJobs
{
    public function __construct(
        private string $token,
        private Store $store,
        private object $ai,
        private array $brand,
        private array $tones,
    ) {
        BotUi::useStore($store);
    }

    /** Ishni fon jarayonida ishga tushiradi (Linux va Windows). */
    public static function spawn(int $jobId): void
    {
        $php = PHP_BINARY;
        $script = ROOT . '/bin/job.php';
        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen('start /B "" ' . escapeshellarg($php) . ' ' . escapeshellarg($script) . " $jobId", 'r'));
        } else {
            // Xatolar izsiz yo'qolmasin: data/jobs.log
            exec(escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . $jobId . ' >> ' . escapeshellarg(ROOT . '/data/jobs.log') . ' 2>&1 &');
        }
    }

    public function run(int $jobId): void
    {
        $job = $this->store->job($jobId);
        if (!$job || $job['status'] !== 'queued') {
            return;
        }
        $this->store->startJob($jobId);
        $tg = new Telegram($this->token, $job['chat_id']);
        $p = $job['payload'];
        $progressId = (int) ($p['progress_message_id'] ?? 0) ?: (int) ($tg->message('⏳ Boshlanmoqda...')['message_id'] ?? 0);

        $last = 0.0;
        $this->jobId = $jobId;
        $progress = function (string $text) use ($tg, $progressId, &$last) {
            $this->assertActive();
            if (microtime(true) - $last < 2.5) {
                return; // Telegram tahrirlash limitiga tushmaslik uchun
            }
            $last = microtime(true);
            try {
                $tg->edit($progressId, "⏳ $text");
            } catch (Throwable) {
                // holat xabari muhim emas
            }
        };

        try {
            $done = match ($job['type']) {
                'post' => $this->post($tg, $p['brief'], (int) ($p['template_id'] ?? 0), $progress),
                'again' => $this->again($tg, (int) $p['brief_id'], $progress),
                'plan' => $this->plan($tg, (string) ($p['wishes'] ?? ''), $progress),
                'design' => $this->design($tg, (int) $p['variant_id'], $progress),
                default => throw new \InvalidArgumentException("Noma'lum ish turi: {$job['type']}"),
            };
            $this->store->finishJob($jobId, 'done');
            $tg->edit($progressId, "✅ $done");
        } catch (JobCancelled) {
            $tg->edit($progressId, '⏹ Bekor qilindi.');
        } catch (Throwable $e) {
            $this->store->finishJob($jobId, 'failed', $e->getMessage());
            // "topilmadi" — qayta urinish yordam bermaydi, tugma ko'rsatilmaydi
            $retry = str_contains($e->getMessage(), 'topilmadi') ? null : BotUi::inline([[['🔁 Qayta urinish', "retry:$jobId"]]]);
            $tg->edit($progressId, '⚠ ' . BotUi::friendlyError($e), $retry);
        }
    }

    private int $jobId = 0;

    /** /bekor bosilgan bo'lsa ish shu joyda to'xtaydi va natija yuborilmaydi. */
    private function assertActive(): void
    {
        if ($this->jobId && ($this->store->job($this->jobId)['status'] ?? '') === 'cancelled') {
            throw new JobCancelled();
        }
    }

    private function post(Telegram $tg, array $brief, int $templateId, callable $progress): string
    {
        $brief['id'] = $this->store->saveBrief($brief);
        $result = (new Copywriter($this->ai, $this->store, $this->brand, $this->tones))
            ->run($brief, ['progress' => $progress] + ($templateId ? ['template_id' => $templateId] : []));
        $this->assertActive();
        BotUi::deliverResult($tg, $result, $brief);
        return "Yozildi: {$brief['topic']}";
    }

    private function again(Telegram $tg, int $briefId, callable $progress): string
    {
        $brief = $this->store->brief($briefId) ?? throw new \RuntimeException('Brif topilmadi.');
        $prev = $this->store->result($briefId, Copywriter::NAME, 'final') ?? [];
        $result = (new Copywriter($this->ai, $this->store, $this->brand, $this->tones))
            ->run($brief, ['progress' => $progress] + (!empty($prev['template_id']) ? ['template_id' => (int) $prev['template_id']] : []));
        $this->assertActive();
        BotUi::deliverResult($tg, $result, $brief);
        return "Qayta yozildi: {$brief['topic']}";
    }

    private function plan(Telegram $tg, string $wishes, callable $progress): string
    {
        $plan = (new ContentPlanner($this->ai, $this->store, $this->brand, $this->tones))
            ->run(new DateTimeImmutable('today'), ['wishes' => $wishes, 'progress' => $progress]);
        $this->assertActive();
        $meta = ['topic' => 'haftalik-reja-' . $plan['week'], 'id' => 0];
        $path = Output::save($meta, 'kontent-paket.txt', ContentPlanner::toText($plan));
        $tg->sendDocument($path, '📦 Butun paket bitta faylda (arxiv uchun)');
        BotUi::deliverPlan($tg, $plan); // tugmali yakuniy xabar eng pastda qolsin
        return "Haftalik reja tayyor ({$plan['week']})";
    }

    private function design(Telegram $tg, int $variantId, callable $progress): string
    {
        $variant = $this->store->variant($variantId) ?? throw new \RuntimeException('Variant topilmadi.');
        $brief = $this->store->brief($variant['brief_id']);
        $result = $this->store->result($variant['brief_id'], Copywriter::NAME, 'final') ?? [];
        $design = (new GraphicDesigner($this->ai, $this->store, $this->brand, $this->tones))->run($brief, $result['strategy'] ?? [], [
            'progress' => $progress,
            'template_id' => $result['template_id'] ?? null,
            'variant' => $variant,
        ]);
        $this->assertActive();
        if (!empty($design['slides']) && count($design['slides']) >= 2) {
            $tg->sendMediaGroup($design['slides'], '🎨 Karusel: ' . count($design['slides']) . " ta slayd (1080×1350). Instagram'ga shu tartibda joylang.");
        } elseif (!empty($design['card_path'])) {
            // Brend uslubidagi tayyor rasm — to'g'ridan-to'g'ri Instagram'ga
            $tg->sendDocument($design['card_path'], "🎨 Tayyor rasm (1080×1350, sifat yo'qolmasligi uchun fayl sifatida)");
        } elseif ($design['image_generated'] && $design['image_path']) {
            $tg->sendPhoto($design['image_path'], '🎨 ' . ($design['alt_text'] ?: 'Post uchun rasm'));
        }
        if (!empty($design['card_path'])) {
            return !empty($design['slides']) ? 'Karusel tayyor' : 'Dizayn tayyor';
        }
        $text = "🎨 Tayyor rasm chizilmadi, lekin dizayner tavsifi: {$brief['topic']}\n";
        if ($design['layout']) {
            $text .= "\nMaket (dizayner yoki Canva uchun):\n— " . implode("\n— ", $design['layout']) . "\n";
        }
        $text .= "\nFon rasmi uchun prompt" . ($design['image_generated'] ? '' : " (rasm generatsiya bo'lmadi — boshqa vositada ishlating)") . ":\n{$design['image_prompt']}";
        $tg->message($text);
        return 'Dizayn tayyor';
    }
}

/** /bekor bilan to'xtatilgan ish. */
final class JobCancelled extends \RuntimeException
{
}
