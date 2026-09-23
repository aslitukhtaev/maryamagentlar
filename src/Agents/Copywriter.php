<?php

declare(strict_types=1);

namespace Maryam\Agents;

use Maryam\Brief;
use Maryam\Gemini;
use Maryam\Store;

/**
 * COPYWRITER AGENT — post matni va target reklama matnlarini yozadi.
 *
 * Bitta "yozib ber" so'rovi emas, balki tajribali jamoadek 3 bosqichda ishlaydi:
 *
 *   1. STRATEGIYA  — auditoriya, og'riqlar, e'tirozlar, katta g'oya, 3 ta burchak (smart model)
 *   2. YOZISH      — 5 ta hook + 2 ta post + 2 ta reklama, har biri boshqa burchak/freymvorkda
 *   3. TAHRIR      — muharrir har variantni 6 mezon bo'yicha baholaydi va yaxshilaydi;
 *                    ball past bo'lsa yana bir marta tahrir qiladi (smart model)
 *
 * Qo'shimcha "kuch" manbalari:
 *   - Kod o'zi ham tekshiradi (lint): belgilar limiti, taqiqlangan iboralar, joy-belgilar
 *   - Siz bergan baholar (1-5) bazada saqlanadi va keyingi safar namuna sifatida o'rgatiladi
 *
 * Mustaqil: boshqa joydan (Manager, Telegram bot, ERP) ham chaqirish mumkin:
 *     $result = (new Copywriter(...))->run($brief, ['tone' => $managerTone]);
 */
final class Copywriter
{
    public const NAME = 'copywriter';

    /** Muharrir bahosi shundan past bo'lsa — variant qayta tahrirlanadi. */
    private const MIN_SCORE = 8.0;
    private const MAX_EDIT_ROUNDS = 2;

    public function __construct(
        private Gemini $ai,
        private Store $store,
        private array $brand,
        private array $tones,
    ) {
    }

    /**
     * @param array $brief   Brief::normalize() natijasi (ichida 'id' bo'lsa — mavjud brif ishlatiladi)
     * @param array $options 'tone' => Manager bergan ton profili (ixtiyoriy),
     *                       'progress' => fn(string $xabar) — jarayon haqida xabar berish uchun
     * @return array{brief_id: int, strategy: array, hooks: array, variants: array, placeholders: array}
     */
    public function run(array $brief, array $options = []): array
    {
        $say = $options['progress'] ?? static fn (string $m) => null;
        $tone = $options['tone'] ?? $this->tones[$brief['tourism_type']];
        $briefId = $brief['id'] ?? $this->store->saveBrief($brief);

        // Barcha bosqichlarga beriladigan umumiy kontekst
        $context = [
            'brief' => Brief::forPrompt($brief, $this->tones),
            'brand' => $this->brand,
            'tone_profile' => $tone,
        ];

        $say('1/3 Strategiya: auditoriya va burchaklar tahlil qilinmoqda...');
        $strategy = $this->ask($briefId, 'strategy', $context, 0.7, true);
        $this->store->saveResult($briefId, self::NAME, 'strategy', $strategy);

        $say("2/3 Yozish: hooklar, postlar va reklama matnlari...");
        $draft = $this->ask($briefId, 'write', $context + [
            'strategy' => $strategy,
            'good_examples' => $this->store->ratedExamples($brief['tourism_type'], true),
            'bad_examples' => $this->store->ratedExamples($brief['tourism_type'], false),
        ], 1.0);
        $variants = array_map([$this, 'normalizeVariant'], $draft['variants'] ?? []);

        // 3-bosqich: tahrir. Birinchi raundda hammasi, keyingisida faqat muammolilari.
        $toEdit = array_keys($variants);
        for ($round = 1; $round <= self::MAX_EDIT_ROUNDS && $toEdit; $round++) {
            $say("3/3 Tahrir ($round-raund): " . count($toEdit) . ' ta variant tekshirilmoqda...');
            $batch = array_map(fn ($i) => $variants[$i] + ['lint_warnings' => $this->lint($variants[$i])], $toEdit);
            $edited = $this->ask($briefId, "edit_$round", $context + [
                'strategy' => $strategy,
                'variants_to_review' => array_values($batch),
            ], 0.4, true);

            // Tahrirlangan variantlarni id bo'yicha o'z joyiga qaytaramiz
            $byId = array_column(array_map([$this, 'normalizeVariant'], $edited['variants'] ?? []), null, 'id');
            foreach ($toEdit as $i) {
                if (isset($byId[$variants[$i]['id']])) {
                    $new = $byId[$variants[$i]['id']];
                    $new['previous_review'] = ['score' => $new['score'], 'issues' => $new['issues']];
                    $variants[$i] = $new;
                }
            }
            // Bali past yoki texnik qoidani buzgan variantlar yana tahrirga qaytadi
            $toEdit = array_keys(array_filter($variants, fn ($v) => $v['score'] < self::MIN_SCORE || $this->lint($v)));
        }

        // Saqlash
        foreach ($variants as &$v) {
            unset($v['previous_review']);
            $v['warnings'] = $this->lint($v);
            $v['db_id'] = $this->store->saveVariant($briefId, $v);
        }
        unset($v);

        $result = [
            'brief_id' => $briefId,
            'strategy' => $strategy,
            'hooks' => array_values(array_filter(array_map('strval', $draft['hooks'] ?? []))),
            'variants' => $variants,
            'placeholders' => $this->placeholders($variants),
        ];
        $this->store->saveResult($briefId, self::NAME, 'final', $result);
        return $result;
    }

    /** Bitta AI so'rovi: prompts/copywriter/<step>.md + kontekst (JSON) -> javob (massiv). */
    private function ask(int $briefId, string $step, array $context, float $temperature, bool $smart = false): array
    {
        $promptFile = preg_replace('/_\d+$/', '', $step); // edit_2 -> edit
        $system = file_get_contents(ROOT . "/prompts/copywriter/$promptFile.md");
        $user = "Kontekst (JSON):\n" . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
              . "\n\nVazifani bajar va faqat ko'rsatilgan formatdagi JSON qaytar.";

        $result = $this->ai->json($system, $user, $temperature, $smart);
        $this->store->logRun($briefId, self::NAME, $step, $user, $result);
        return $result['data'];
    }

    /** AI qaytargan variantni bir xil shaklga keltiradi (yetishmagan maydonlar bo'sh bo'ladi). */
    private function normalizeVariant(array $v): array
    {
        $scores = array_map('floatval', array_filter((array) ($v['scores'] ?? []), 'is_numeric'));
        return [
            'id' => (string) ($v['id'] ?? ''),
            'kind' => ($v['kind'] ?? '') === 'ad' ? 'ad' : 'social_post',
            'angle' => (string) ($v['angle'] ?? ''),
            'framework' => (string) ($v['framework'] ?? ''),
            'hook' => trim((string) ($v['hook'] ?? '')),
            'body' => trim((string) ($v['body'] ?? '')),
            'cta' => trim((string) ($v['cta'] ?? '')),
            'hashtags' => array_values(array_map('strval', (array) ($v['hashtags'] ?? []))),
            'headline' => trim((string) ($v['headline'] ?? '')),
            'description' => trim((string) ($v['description'] ?? '')),
            'cta_button' => (string) ($v['cta_button'] ?? ''),
            'scores' => $scores,
            // O'rtacha ballni o'zimiz hisoblaymiz — AI arifmetikasiga ishonmaymiz
            'score' => $scores ? round(array_sum($scores) / count($scores), 1) : 0.0,
            'issues' => array_values(array_map('strval', (array) ($v['issues'] ?? []))),
            'changes' => array_values(array_map('strval', (array) ($v['changes'] ?? []))),
        ];
    }

    /**
     * Kod darajasidagi tekshiruv — AI ko'pincha belgilar sonini noto'g'ri sanaydi,
     * shuning uchun buni aniq o'zimiz tekshiramiz va muharrirga aytamiz.
     */
    public function lint(array $v): array
    {
        $w = [];
        $len = fn (string $s) => mb_strlen($s);

        if ($len($v['hook']) > 125) {
            $w[] = "Hook {$len($v['hook'])} belgi — Instagram'da 125 belgidan keyin kesiladi, qisqartiring.";
        }
        if ($v['kind'] === 'ad') {
            if ($v['headline'] === '' || $len($v['headline']) > 40) {
                $w[] = "Reklama sarlavhasi (headline) 1-40 belgi bo'lishi kerak, hozir: {$len($v['headline'])}.";
            }
            if ($len($v['description']) > 30) {
                $w[] = "Reklama tavsifi (description) 30 belgidan oshmasin, hozir: {$len($v['description'])}.";
            }
        }
        if (count($v['hashtags']) > 8) {
            $w[] = 'Hashtaglar juda ko\'p (' . count($v['hashtags']) . ' ta) — 3-6 ta yetarli.';
        }
        if ($v['cta'] === '') {
            $w[] = "CTA (harakatga chaqiruv) yo'q.";
        }
        $full = mb_strtolower($v['hook'] . ' ' . $v['body'] . ' ' . $v['cta']);
        foreach ($this->brand['never_say'] ?? [] as $phrase) {
            if ($phrase !== '' && str_contains($full, mb_strtolower($phrase))) {
                $w[] = "Taqiqlangan ibora ishlatilgan: \"$phrase\".";
            }
        }
        if (preg_match('/!{2,}/', $full)) {
            $w[] = "Ketma-ket undov belgilari (!!) — arzon ko'rinadi.";
        }
        return $w;
    }

    /** Matnlardagi [NARX], [SANA] kabi joy-belgilar — siz qo'lda to'ldirishingiz kerak bo'lganlar. */
    private function placeholders(array $variants): array
    {
        $found = [];
        foreach ($variants as $v) {
            preg_match_all('/\[([^\[\]]{2,40})\]/u', implode(' ', [$v['hook'], $v['body'], $v['cta'], $v['headline'], $v['description']]), $m);
            foreach ($m[0] as $p) {
                $found[$p] = true;
            }
        }
        return array_keys($found);
    }

    /** Natijani o'qish uchun qulay matnga aylantiradi (terminal va .txt fayl uchun). */
    public static function toText(array $result): string
    {
        $s = $result['strategy'];
        $out = "=== COPYWRITER NATIJASI (brif #{$result['brief_id']}) ===\n\n";
        $out .= "KATTA G'OYA: " . ($s['big_idea'] ?? '') . "\n";
        $out .= "ASOSIY XABAR: " . ($s['key_message'] ?? '') . "\n";
        $out .= "AUDITORIYA: " . ($s['audience']['portrait'] ?? '') . "\n\n";

        $out .= "--- HOOKLAR (Reels/karusel uchun ham) ---\n";
        foreach ($result['hooks'] as $i => $h) {
            $out .= ($i + 1) . ". $h\n";
        }

        foreach ($result['variants'] as $v) {
            $title = $v['kind'] === 'ad' ? 'TARGET REKLAMA' : 'POST';
            $out .= "\n" . str_repeat('=', 60) . "\n";
            $out .= "$title [{$v['id']}] — burchak: {$v['angle']} | {$v['framework']} | muharrir bahosi: {$v['score']}/10";
            $out .= isset($v['db_id']) ? " | baholash uchun ID: {$v['db_id']}\n" : "\n";
            $out .= str_repeat('=', 60) . "\n";
            if ($v['kind'] === 'ad') {
                $out .= "Sarlavha: {$v['headline']}\nTavsif: {$v['description']}\nTugma: {$v['cta_button']}\n\nAsosiy matn:\n";
            }
            $out .= "{$v['hook']}\n\n{$v['body']}\n\n{$v['cta']}\n";
            if ($v['hashtags']) {
                $out .= "\n" . implode(' ', $v['hashtags']) . "\n";
            }
            foreach ($v['warnings'] ?? [] as $w) {
                $out .= "⚠ $w\n";
            }
        }

        if ($result['placeholders']) {
            $out .= "\n>>> Qo'lda to'ldiring: " . implode(', ', $result['placeholders']) . "\n";
        }
        if (!empty($s['missing_facts'])) {
            $out .= ">>> Keyingi safar brifga qo'shsangiz matn kuchliroq bo'ladi: " . implode('; ', $s['missing_facts']) . "\n";
        }
        return $out;
    }
}
