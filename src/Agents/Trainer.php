<?php

declare(strict_types=1);

namespace Maryam\Agents;

use Maryam\Prompts;
use Maryam\Store;

/**
 * O'QITUVCHI (TRENER) — agentlarni rahbar didiga moslab o'qitadi:
 *   - proposeRules():       baholar va izohlardan yangi qoidalar taklif qiladi (rahbar tasdiqlaydi)
 *   - templateFromExample(): muvaffaqiyatli postdan qayta ishlatiladigan shablon yasaydi
 */
final class Trainer
{
    public const NAME = 'trainer';
    private const AGENTS = ['all', 'copywriter', 'planner', 'designer', 'manager'];

    public function __construct(private object $ai, private Store $store)
    {
    }

    /** @return array{proposed: int, summary: string} */
    public function proposeRules(): array
    {
        $ratings = array_map(static function (array $r) {
            $v = json_decode($r['data'], true);
            return [
                'baho' => (int) $r['rating'],
                'izoh' => $r['feedback'],
                'yonalish' => $r['tourism_type'],
                'mavzu' => $r['topic'],
                'matn' => mb_substr(trim(($v['hook'] ?? '') . "\n" . ($v['body'] ?? '') . "\n" . ($v['cta'] ?? '')), 0, 600),
            ];
        }, $this->store->ratedWithFeedback());

        if (!$ratings) {
            return ['proposed' => 0, 'summary' => "Hali baholangan matn yo'q — avval Studiyada bir nechta natijani baholang."];
        }

        $existing = array_map(
            static fn (array $r) => "[{$r['agent']}] {$r['content']}",
            array_filter($this->store->rows('rules'), static fn ($r) => $r['status'] !== 'off')
        );
        $data = Prompts::ask($this->ai, $this->store, 'trainer/rules',
            ['ratings' => $ratings, 'existing_rules' => array_values($existing)], 0.3, true, null, self::NAME, 'rules');

        // Takror taklif qilinmasin: mavjud (o'chirilganlari ham) qoidalar matni bilan solishtiriladi
        $norm = static fn (string $t) => mb_strtolower(preg_replace('/\W+/u', '', preg_replace("/\n— sabab:.*$/su", '', $t)));
        $seen = array_flip(array_map(static fn ($r) => $norm((string) $r['content']), $this->store->rows('rules')));
        $count = 0;
        foreach ((array) ($data['rules'] ?? []) as $rule) {
            $content = trim((string) ($rule['content'] ?? ''));
            if ($content === '' || isset($seen[$norm($content)])) {
                continue;
            }
            $seen[$norm($content)] = true;
            $agent = in_array($rule['agent'] ?? '', self::AGENTS, true) ? $rule['agent'] : 'all';
            $reason = trim((string) ($rule['reason'] ?? ''));
            $this->store->insertRow('rules', [
                'agent' => $agent,
                'content' => $content . ($reason !== '' ? "\n— sabab: $reason" : ''),
                'status' => 'proposed',
                'source' => 'trainer',
            ]);
            $count++;
        }
        return ['proposed' => $count, 'summary' => trim((string) ($data['summary'] ?? ''))];
    }

    /** Namuna postdan shablon yasaydi va bazaga (faol) qo'shadi. @return int shablon id */
    public function templateFromExample(string $post, string $imageNote = ''): int
    {
        $data = Prompts::ask($this->ai, $this->store, 'trainer/template',
            ['post' => $post, 'image_note' => $imageNote], 0.3, true, null, self::NAME, 'template');

        $pick = static fn (string $v, array $allowed, string $default) => in_array($v, $allowed, true) ? $v : $default;
        return $this->store->insertRow('templates', [
            'name' => trim((string) ($data['name'] ?? '')) ?: 'Yangi shablon',
            'format' => $pick((string) ($data['format'] ?? ''), ['post', 'reels', 'karusel', 'reklama'], 'post'),
            'stage' => $pick((string) ($data['stage'] ?? ''), ['qamrov', 'ishonch', 'sotuv'], 'sotuv'),
            'tourism_type' => $pick((string) ($data['tourism_type'] ?? ''), ['umra', 'ichki', 'inbound', 'outbound'], ''),
            'structure' => trim((string) ($data['structure'] ?? '')),
            'example' => $post,
            'rules' => trim((string) ($data['rules'] ?? '')),
            'design' => trim((string) ($data['design'] ?? '')),
            'active' => 1,
        ]);
    }
}
