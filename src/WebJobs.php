<?php

declare(strict_types=1);

namespace Maryam;

use DateTimeImmutable;
use Maryam\Agents\ContentPlanner;
use Maryam\Agents\Copywriter;
use Maryam\Agents\GraphicDesigner;
use Throwable;

/**
 * Web ilovaning UZOQ ISHLARI (rasm chizish, post yozish, haftalik reja) — alohida fon jarayonida (bin/job.php).
 * Sahifa darhol qaytadi, ish holati yuqoridagi lentada ko'rinadi; shu vaqtda ilovaning boshqa sahifalari ishlayveradi.
 *
 * Holat meta'da: job:<id>:label (nima qilinyapti), :progress (hozirgi bosqich), :url (natija sahifasi), :flash (xabar).
 */
final class WebJobs
{
    public const CHAT = 'web';

    private const LABELS = [
        'web_run' => 'Copywriter yozmoqda',
        'web_plan' => 'Kontent-strateg haftalik reja tuzmoqda',
        'web_design' => 'Dizayner chizmoqda',
        'web_design_free' => 'Dizayner chizmoqda',
        'web_design_pick' => 'Dizayner slaydlarni yangi muqova uslubida chizmoqda',
        'web_design_fix' => 'Dizayner tuzatmoqda',
        'web_design_retry' => 'Dizayner qayta chizmoqda',
        'web_design_redraw' => 'Dizayner chizilmagan slaydlarni chizmoqda',
    ];

    public function __construct(private Store $store, private object $ai, private array $brand, private array $tones)
    {
    }

    /** Ishni navbatga qo'yib, fon jarayonini ishga tushiradi. */
    public static function start(Store $store, string $type, array $payload, string $label = ''): int
    {
        $id = $store->createJob(self::CHAT, $type, $payload);
        $store->setMeta("job:$id:label", $label ?: (self::LABELS[$type] ?? 'Ishlamoqda'));
        $store->setMeta("job:$id:progress", 'Navbatda...');
        BotJobs::spawn($id);
        return $id;
    }

    /** Sahifa uchun holat: {status, label, progress, url, flash, error}. */
    public static function status(Store $store, int $id): array
    {
        $job = $store->job($id);
        if (!$job || $job['chat_id'] !== self::CHAT) {
            return ['status' => 'missing'];
        }
        $status = $job['status'];
        // Fon jarayoni o'lib qolgan bo'lsa (60 daqiqadan oshdi) — osilib qolmasin
        $stale = $store->db->prepare("SELECT created_at < datetime('now', 'localtime', '-60 minutes') FROM jobs WHERE id = ?");
        $stale->execute([$id]);
        if (in_array($status, ['queued', 'running'], true) && (int) $stale->fetchColumn() === 1) { // vaqtni SQLite o'zi solishtiradi (vaqt zonasi farqi yo'q)
            $status = 'failed';
            $job['error'] = "Ish juda uzoq davom etdi va to'xtadi.";
        }
        return [
            'status' => $status,
            'label' => (string) $store->meta("job:$id:label"),
            'progress' => (string) $store->meta("job:$id:progress"),
            'url' => (string) $store->meta("job:$id:url"),
            'error' => (string) $job['error'],
        ];
    }

    /** Hozir bajarilayotgan va yaqinda tugagan web ishlari (lenta uchun). */
    public static function active(Store $store): array
    {
        $st = $store->db->prepare("SELECT id FROM jobs WHERE chat_id = ? AND (status IN ('queued', 'running')
                                   OR finished_at > datetime('now', 'localtime', '-3 minutes')) ORDER BY id DESC LIMIT 5");
        $st->execute([self::CHAT]);
        return array_map('intval', array_column($st->fetchAll(), 'id'));
    }

    public function run(int $id): void
    {
        $job = $this->store->job($id);
        if (!$job || $job['status'] !== 'queued') {
            return;
        }
        $this->store->startJob($id);
        $p = $job['payload'];
        $progress = fn (string $m) => $this->store->setMeta("job:$id:progress", $m);
        self::patient($this->ai, $progress);
        try {
            [$url, $flash] = match ($job['type']) {
                'web_run' => $this->copywriter($p, $progress),
                'web_plan' => $this->plan($p, $progress),
                'web_design' => $this->designVariant($p, $progress),
                'web_design_free' => $this->designFree($p, $progress),
                'web_design_pick' => $this->pick($p, $progress),
                'web_design_fix' => $this->fix($p),
                'web_design_retry' => $this->retry($p, $progress),
                'web_design_redraw' => $this->redraw($p),
                default => throw new \InvalidArgumentException("Noma'lum ish: {$job['type']}"),
            };
            $this->store->setMeta("job:$id:url", $url);
            $this->store->setMeta("job:$id:flash", $flash);
            $this->store->setMeta("job:$id:progress", 'Tayyor');
            $this->store->finishJob($id, 'done');
        } catch (Throwable $e) {
            $this->store->setMeta("job:$id:url", (string) ($p['return'] ?? ''));
            $this->store->finishJob($id, 'failed', $e->getMessage());
        }
    }

    /**
     * Fon ishida xato o'rniga KUTISH: Google rasm limiti to'lsa, 25 daqiqagacha kutib davom etiladi,
     * egasi esa lentada nima bo'layotganini ko'radi.
     */
    public static function patient(object $ai, callable $progress): void
    {
        if (property_exists($ai, 'waitBudget')) {
            $ai->waitBudget = 1500.0;
            $ai->onWait = static function (float $sec, int $done, int $total) use ($progress): void {
                $progress("Google rasm chizish navbati band — " . max(1, (int) round($sec)) . " soniya kutib davom etamiz"
                    . ($total > 1 ? " ($done/$total tayyor)" : '') . '. Hech narsa qilish shart emas.');
            };
        }
    }

    private function designer(): GraphicDesigner
    {
        return new GraphicDesigner($this->ai, $this->store, $this->brand, $this->tones);
    }

    private static function back(array $p, string $fallback): string
    {
        $r = (string) ($p['return'] ?? '');
        return str_starts_with($r, '?') ? $r : $fallback;
    }

    private function copywriter(array $p, callable $progress): array
    {
        $templateId = (int) ($p['template_id'] ?? 0);
        $tpl = $templateId ? $this->store->row('templates', $templateId) : null;
        $p['tourism_type'] = ($p['tourism_type'] ?? '') ?: (($tpl['tourism_type'] ?? '') ?: 'outbound');
        $p['goal'] = ($p['goal'] ?? '') ?: (['qamrov' => 'jalb', 'ishonch' => 'brend', 'sotuv' => 'lid'][$tpl['stage'] ?? ''] ?? 'lid');
        $brief = Brief::normalize($p, $this->tones);
        $brief['id'] = $this->store->saveBrief($brief);
        $result = (new Copywriter($this->ai, $this->store, $this->brand, $this->tones))
            ->run($brief, ['progress' => $progress] + ($templateId ? ['template_id' => $templateId] : []));
        Output::save($brief, 'copywriter.txt', Copywriter::toText($result));
        return ['?p=studio&brief=' . $brief['id'], 'Tayyor: ' . $brief['topic']];
    }

    private function plan(array $p, callable $progress): array
    {
        $plan = (new ContentPlanner($this->ai, $this->store, $this->brand, $this->tones))
            ->run(new DateTimeImmutable('today'), ['wishes' => (string) ($p['wishes'] ?? ''), 'progress' => $progress]);
        Output::save(['topic' => 'haftalik-reja-' . $plan['week'], 'id' => 0], 'kontent-paket.txt', ContentPlanner::toText($plan));
        return ['?p=reja&week=' . urlencode($plan['week']), 'Haftalik reja tayyor.'];
    }

    private function designVariant(array $p, callable $progress): array
    {
        $briefId = (int) $p['brief_id'];
        $brief = $this->store->brief($briefId) ?? throw new \RuntimeException('Brif topilmadi.');
        $result = $this->store->result($briefId, Copywriter::NAME, 'final') ?? [];
        $d = $this->designer()->run($brief, $result['strategy'] ?? [], [
            'template_id' => $result['template_id'] ?? null,
            'variant' => $this->store->variant((int) $p['variant_id']),
            'progress' => $progress,
        ]);
        return [self::back($p, '?p=studio&brief=' . $briefId) . '#v' . (int) $p['variant_id'], self::designFlash($d)];
    }

    private function designFree(array $p, callable $progress): array
    {
        $text = (string) $p['text'];
        $firstLine = trim((string) strtok($text, "\n"));
        $type = preg_match('/\b(umra|haj|makka|madinaga|madinada|ziyorat)/iu', $text) ? 'umra' : 'outbound';
        $brief = Brief::normalize(['topic' => mb_strimwidth($firstLine, 0, 80, '…'), 'tourism_type' => $type, 'details' => $text], $this->tones);
        $brief['id'] = $this->store->saveBrief($brief);
        $d = $this->designer()->run($brief, [], [
            'format' => (string) $p['format'], 'text' => $text, 'kind' => 'free', 'photos' => (array) ($p['photos'] ?? []), 'progress' => $progress,
        ]);
        return ['?p=brend&show=' . $d['result_id'] . '#natija', self::designFlash($d)];
    }

    private function pick(array $p, callable $progress): array
    {
        $this->designer()->choose((int) $p['id'], (int) $p['i'], $progress);
        return [self::back($p, '?p=brend&show=' . (int) $p['id']) . '#natija', 'Muqova almashtirildi — slaydlar shu uslubda qayta chizildi.'];
    }

    private function fix(array $p): array
    {
        $target = ($p['target'] ?? 'v') === 's' ? 's' : 'v';
        $this->designer()->fix((int) $p['id'], (int) $p['i'], (string) ($p['instruction'] ?? ''), $target);
        return [self::back($p, '?p=brend&show=' . (int) $p['id']) . '#natija',
            $target === 's' ? ((int) $p['i'] + 1) . '-slayd tuzatildi.' : "Tuzatildi — yangi variant qo'shildi va tanlandi."];
    }

    private function retry(array $p, callable $progress): array
    {
        $d = $this->designer()->retry((int) $p['id'], ['progress' => $progress]);
        return ['?p=brend&show=' . $d['result_id'] . '#natija', self::designFlash($d)];
    }

    private function redraw(array $p): array
    {
        $d = $this->designer()->redrawMissing((int) $p['id']);
        return [self::back($p, '?p=brend&show=' . (int) $p['id']) . '#natija',
            empty($d['missing']) ? 'Barcha slaydlar tayyor.' : count($d['missing']) . " ta slayd hali chizilmadi — birozdan keyin yana urinib ko'ring."];
    }

    private static function designFlash(array $d): string
    {
        return match (true) {
            ($d['engine'] ?? '') === 'failed' => "AI rasm chiza olmadi — sababini ko'ring.",
            !empty($d['slides']) => 'Karusel tayyor: ' . count($d['slides']) . ' ta slayd, muqovaning ' . count($d['variants']) . ' ta varianti.',
            !empty($d['variants']) => count($d['variants']) . ' ta variant tayyor — eng yoqqanini tanlang.',
            default => 'Tayyor.',
        };
    }
}
