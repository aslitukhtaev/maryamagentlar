<?php

declare(strict_types=1);

namespace Maryam\Agents;

use DateTimeImmutable;
use Maryam\Brief;
use Maryam\Marketing;
use Maryam\Prompts;
use Maryam\Store;
use Throwable;

/**
 * KONTENT-STRATEG — marketing bo'limining rejalashtiruvchisi.
 *
 *   1. Haftalik reja: katalog, mavsumiy kalendar va oldingi haftalarni hisobga olib
 *      N ta band (kun, format, yo'nalish, mavzu, g'oya) tuzadi
 *   2. Ishlab chiqarish: har band uchun Copywriter bitta tayyor material yozadi
 *      (strategiya -> yozish -> muharrir tahriri)
 *
 * Natija — e'lon qilishga tayyor haftalik kontent paketi.
 */
final class ContentPlanner
{
    public const NAME = 'planner';

    public function __construct(
        private object $ai,
        private Store $store,
        private array $brand,
        private array $tones,
    ) {
    }

    public static function weekKey(DateTimeImmutable $day): string
    {
        return $day->format('o-\WW');
    }

    /**
     * @param array $options 'wishes' => rahbarning shu hafta uchun istagi (matn),
     *                       'progress' => fn(string $xabar)
     */
    public function run(DateTimeImmutable $today, array $options = []): array
    {
        $say = $options['progress'] ?? static fn (string $m) => null;

        $say('Haftalik reja tuzilmoqda...');
        $plan = $this->plan($today, (string) ($options['wishes'] ?? ''));

        foreach ($plan['items'] as $i => &$item) {
            $say('Tayyorlanmoqda ' . ($i + 1) . '/' . count($plan['items']) . ": {$item['topic']}");
            try {
                $brief = Brief::normalize([
                    'topic' => $item['topic'],
                    'tourism_type' => $item['tourism_type'],
                    'goal' => $item['goal'],
                    'details' => $item['idea'],
                ], $this->tones);
                $result = (new Copywriter($this->ai, $this->store, $this->brand, $this->tones))
                    ->run($brief, ['format' => $item['format'], 'template_id' => $item['template_id'] ?: null, 'progress' => $say]);
                $item['brief_id'] = $result['brief_id'];
                $item['content'] = $result['variants'][0] ?? null;
                $item['placeholders'] = $result['placeholders'];
            } catch (Throwable $e) {
                // Bitta band xatosi butun haftalik paketni to'xtatmasin
                $item['error'] = $e->getMessage();
            }
        }
        unset($item);

        $this->store->savePlan($plan['week'], $plan);
        return $plan;
    }

    /** Faqat reja (matnlarsiz) — tez, bitta AI chaqiruvi. */
    public function plan(DateTimeImmutable $today, string $wishes = ''): array
    {
        $settings = Marketing::settings();
        $monday = $today->modify('monday this week');

        $context = [
            'week' => $monday->format('Y-m-d') . ' — ' . $monday->modify('+6 days')->format('Y-m-d'),
            'today' => $today->format('Y-m-d'),
            'posting_days' => $settings['posting_days'],
            'posts_per_week' => $settings['posts_per_week'],
            'formats' => $settings['formats'],
            'mix' => $settings['mix'],
            'tourism_types' => array_map(static fn ($t) => $t['label'], $this->tones),
            'products' => Marketing::productsForPrompt(),
            'upcoming_events' => Marketing::upcomingEvents($today),
            'brand' => Marketing::brand($this->brand, $this->store),
            'recent_topics' => $this->store->recentPlanTopics(),
            'wishes' => $wishes,
            'templates' => array_map(static fn (array $t) => [
                'id' => (int) $t['id'], 'name' => $t['name'], 'format' => $t['format'],
                'tourism_type' => $t['tourism_type'] ?: 'hammasi', 'stage' => $t['stage'],
            ], $this->store->activeTemplates()),
        ] + Marketing::training($this->store, self::NAME);

        $data = Prompts::ask($this->ai, $this->store, 'planner/plan', $context, 0.7, true, null, self::NAME, 'plan');

        $items = [];
        foreach ((array) ($data['items'] ?? []) as $raw) {
            $item = $this->normalizeItem((array) $raw, $settings);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return [
            'week' => self::weekKey($today),
            'week_focus' => (string) ($data['week_focus'] ?? ''),
            'items' => array_slice($items, 0, (int) $settings['posts_per_week']),
            'missing_info' => array_values(array_map('strval', (array) ($data['missing_info'] ?? []))),
        ];
    }

    private function normalizeItem(array $raw, array $settings): ?array
    {
        $topic = trim((string) ($raw['topic'] ?? ''));
        $type = (string) ($raw['tourism_type'] ?? '');
        if ($topic === '' || !isset($this->tones[$type])) {
            return null;
        }
        $format = (string) ($raw['format'] ?? '');
        $goal = (string) ($raw['goal'] ?? '');
        $template = !empty($raw['template_id']) ? $this->store->row('templates', (int) $raw['template_id']) : null;
        if ($template && $template['active']) {
            $format = $template['format']; // shablon formatni belgilaydi
        }
        return [
            'day' => (string) ($raw['day'] ?? ''),
            'format' => isset($settings['formats'][$format]) ? $format : 'post',
            'tourism_type' => $type,
            'goal' => isset(Brief::GOALS[$goal]) ? $goal : 'lid',
            'topic' => $topic,
            'idea' => trim((string) ($raw['idea'] ?? '')),
            'product_id' => (string) ($raw['product_id'] ?? ''),
            'why' => (string) ($raw['why'] ?? ''),
            'template_id' => $template && $template['active'] ? (int) $template['id'] : 0,
            'template_name' => $template && $template['active'] ? $template['name'] : '',
        ];
    }

    /** Rahbar tasdiqlashi uchun qisqa reja (Telegram xabari). */
    public static function planSummary(array $plan): string
    {
        $out = "🗓 Haftalik kontent-reja ({$plan['week']})\n";
        if ($plan['week_focus'] !== '') {
            $out .= "Fokus: {$plan['week_focus']}\n";
        }
        foreach ($plan['items'] as $i => $item) {
            $status = isset($item['error']) ? ' ⚠ tayyorlanmadi' : '';
            $out .= "\n" . ($i + 1) . ". {$item['day']} · " . mb_strtoupper($item['format']) . " · {$item['topic']}$status\n";
            if (($item['template_name'] ?? '') !== '') {
                $out .= "   shablon: {$item['template_name']}\n";
            }
            if ($item['why'] !== '') {
                $out .= "   ↳ {$item['why']}\n";
            }
        }
        if ($plan['missing_info']) {
            $out .= "\n💡 Reja kuchliroq bo'lishi uchun kiriting: " . implode('; ', $plan['missing_info']) . "\n";
        }
        return $out;
    }

    /** To'liq paket: har band uchun e'lon qilishga tayyor matn. */
    public static function toText(array $plan): string
    {
        $out = self::planSummary($plan);
        foreach ($plan['items'] as $i => $item) {
            $out .= "\n" . str_repeat('=', 60) . "\n";
            $out .= ($i + 1) . ". {$item['day']} — " . mb_strtoupper($item['format']) . ": {$item['topic']}\n";
            $out .= str_repeat('=', 60) . "\n";
            if (isset($item['error'])) {
                $out .= "⚠ Tayyorlanmadi: {$item['error']}\n";
                continue;
            }
            $v = $item['content'] ?? null;
            if ($v === null) {
                $out .= "⚠ Copywriter natija qaytarmadi.\n";
                continue;
            }
            if ($v['kind'] === 'ad') {
                $out .= "Sarlavha: {$v['headline']}\nTavsif: {$v['description']}\nTugma: {$v['cta_button']}\n\n";
            }
            $out .= Copywriter::variantText($v);
            $out .= "\n(muharrir bahosi: {$v['score']}/10";
            $out .= isset($v['db_id']) ? " · baholash ID: {$v['db_id']})\n" : ")\n";
            if (!empty($item['placeholders'])) {
                $out .= "✏ Qo'lda to'ldiring: " . implode(', ', $item['placeholders']) . "\n";
            }
        }
        return $out;
    }
}
