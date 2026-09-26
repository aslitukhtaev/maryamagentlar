<?php

declare(strict_types=1);

namespace Maryam;

use DateTimeImmutable;
use Maryam\Agents\ContentPlanner;
use Maryam\Agents\Copywriter;
use Maryam\Agents\Manager;
use Throwable;

/**
 * TELEGRAM BOT — tezkor qism: menyu, tugmali oqimlar, baholash. Uzoq ishlarni (AI yozishi)
 * BotJobs'ga topshiradi, shuning uchun bot hech qachon "qotib" qolmaydi.
 *
 * Oqimlar:
 *   ✍️ Post yozish  → tur/mavzu tanlash → shablon tanlash → (kerak bo'lsa yo'nalish) → fon ishi
 *   🗓 Haftalik reja → hozir tuzish / istak bilan / shu haftaniki
 *   📂 Oxirgi ishlar → oldingi natijani qayta ochish
 *   Erkin matn → Manager (AI) tushunib, kerakli ishni boshlaydi
 */
final class TelegramBot
{
    private const MAX_PARALLEL_JOBS = 2;
    private const FIELDS = ['tourism_type', 'goal', 'language'];

    /** @var array<string, array> chat bo'yicha joriy holat (qaysi savolga javob kutilmoqda) */
    private array $state = [];
    private array $allowed;
    private Manager $manager;

    public function __construct(
        private string $token,
        private Store $store,
        private object $ai,
        private array $brand,
        private array $tones,
        private string $ownerChatId,
        string $allowedRaw,
    ) {
        $this->allowed = array_values(array_filter(array_map('trim', explode(',', $allowedRaw ?: $ownerChatId))));
        $this->manager = new Manager($ai, $store, $brand, $tones);
    }

    /** Buyruqlar ro'yxati va "Ilova" menyu tugmasi (bir marta, ishga tushganda). */
    public function setup(): void
    {
        $tg = new Telegram($this->token, $this->ownerChatId);
        try {
            $tg->api('setMyCommands', ['commands' => [
                ['command' => 'start', 'description' => 'Bosh menyu'],
                ['command' => 'post', 'description' => 'Post yozish'],
                ['command' => 'reja', 'description' => 'Haftalik reja'],
                ['command' => 'bekor', 'description' => 'Boshlangan ishni bekor qilish'],
                ['command' => 'yordam', 'description' => 'Bot qanday ishlaydi'],
            ]]);
            $app = BotUi::webAppUrl();
            $tg->api('setChatMenuButton', ['menu_button' => $app !== ''
                ? ['type' => 'web_app', 'text' => 'Ilova', 'web_app' => ['url' => $app]]
                : ['type' => 'commands']]);
        } catch (Throwable $e) {
            echo "⚠ Menyu sozlanmadi: {$e->getMessage()}\n";
        }
    }

    public function run(): never
    {
        $this->setup();
        echo "🤖 Bot ishga tushdi. To'xtatish: Ctrl+C\n";
        echo $this->allowed ? '   Ruxsat berilgan: ' . implode(', ', $this->allowed) . "\n" : "   ⚠ TELEGRAM_CHAT_ID bo'sh — har kim yoza oladi!\n";
        $api = new Telegram($this->token, '');
        $offset = 0;
        while (true) {
            $this->weeklyTick();
            try {
                $updates = $api->api('getUpdates', ['offset' => $offset, 'timeout' => 25])['result'] ?? [];
            } catch (Throwable $e) {
                echo "⚠ getUpdates: {$e->getMessage()}\n";
                sleep(5);
                continue;
            }
            foreach ($updates as $update) {
                $offset = $update['update_id'] + 1;
                try {
                    $this->handle($update);
                } catch (Throwable $e) {
                    echo "❌ {$e->getMessage()}\n";
                    $chatId = (string) ($update['message']['chat']['id'] ?? $update['callback_query']['message']['chat']['id'] ?? '');
                    if ($chatId !== '') {
                        (new Telegram($this->token, $chatId))->message('⚠ ' . BotUi::friendlyError($e));
                    }
                }
            }
        }
    }

    /** Haftalik reja: faqat belgilangan kunda, haftada BIR marta (holat bazada — qayta ishga tushirsa ham takrorlanmaydi). */
    private function weeklyTick(): void
    {
        $cfg = Marketing::settings()['weekly_plan'] ?? [];
        $now = new DateTimeImmutable();
        $week = ContentPlanner::weekKey($now);
        if (!($cfg['enabled'] ?? false) || $this->ownerChatId === ''
            || (int) $now->format('N') !== (int) ($cfg['weekday'] ?? 1)
            || (int) $now->format('G') < (int) ($cfg['hour'] ?? 9)
            || $this->store->meta('auto_plan_week') === $week
            || $this->store->plan($week) !== null) {
            return;
        }
        $this->store->setMeta('auto_plan_week', $week);
        $tg = new Telegram($this->token, $this->ownerChatId);
        $msg = $tg->message("🗓 Yangi hafta! Kontent-reja va tayyor materiallarni tayyorlayapman (5-10 daqiqa)...");
        $this->startJob($this->ownerChatId, 'plan', ['wishes' => '', 'progress_message_id' => $msg['message_id'] ?? 0], silent: true);
    }

    public function handle(array $update): void
    {
        if (isset($update['callback_query'])) {
            $this->onCallback($update['callback_query']);
        } elseif (isset($update['message'])) {
            $this->onMessage($update['message']);
        }
    }

    private function allowedChat(string $chatId, Telegram $tg): bool
    {
        if (!$this->allowed || in_array($chatId, $this->allowed, true)) {
            return true;
        }
        $tg->message("⛔ Bu bot faqat Maryam Travel jamoasi uchun.\nSizning Telegram ID: $chatId (administratorga yuboring).");
        return false;
    }

    // ==================== XABARLAR ====================

    private function onMessage(array $m): void
    {
        $chatId = (string) $m['chat']['id'];
        $tg = new Telegram($this->token, $chatId);
        if (!$this->allowedChat($chatId, $tg)) {
            return;
        }
        $text = trim((string) ($m['text'] ?? $m['caption'] ?? ''));
        if ($text === '') {
            $tg->message("Hozircha faqat matnni tushunaman. Menyudan tanlang yoki vazifani yozib yuboring.", BotUi::mainKeyboard());
            return;
        }
        echo "→ [$chatId] " . mb_substr($text, 0, 80) . "\n";

        // Kanaldan forward — avval so'raymiz (raqobatchi posti bo'lishi ham mumkin)
        if (isset($m['forward_origin']) || isset($m['forward_date'])) {
            $this->state[$chatId] = ['await' => 'example_confirm', 'text' => $text];
            $tg->message("Bu postni uslub namunasi qilib saqlaymi? Agentlar shu ohangda yozishni o'rganadi.", BotUi::inline([
                [["✅ Ha, saqla", 'fwd:yes'], ["❌ Yo'q", 'fwd:no']],
            ]));
            return;
        }

        $command = strtolower(explode(' ', explode('@', $text)[0])[0]);
        switch ($command) {
            case '/start':
            case '/menu':
                $this->reset($chatId);
                $tg->message("Assalomu alaykum! Men — Maryam Travel marketing bo'limi.\nQuyidagi menyudan tanlang 👇"
                    . ($this->allowed ? '' : "\n\n⚠ Sozlash: .env faylida TELEGRAM_CHAT_ID=$chatId deb yozing, shunda bot faqat sizga javob beradi."), BotUi::mainKeyboard());
                return;
            case '/bekor':
                $this->reset($chatId);
                $tg->message('Bekor qilindi. Menyudan tanlang 👇', BotUi::mainKeyboard());
                return;
            case '/yordam':
            case '/help':
                $tg->message(BotUi::helpText(), BotUi::mainKeyboard());
                return;
            case '/post':
                $this->askTopic($tg, $chatId);
                return;
            case '/reja':
                $this->planMenu($tg);
                return;
        }

        switch ($text) {
            case BotUi::BTN_POST:
                $this->askTopic($tg, $chatId);
                return;
            case BotUi::BTN_PLAN:
                $this->planMenu($tg);
                return;
            case BotUi::BTN_RECENT:
                $this->recent($tg);
                return;
            case BotUi::BTN_HELP:
            case BotUi::BTN_APP:
                $tg->message(BotUi::helpText(), BotUi::mainKeyboard());
                return;
        }

        $state = $this->state[$chatId] ?? [];
        switch ($state['await'] ?? '') {
            case 'topic':
                [$topic, $details] = array_pad(array_map('trim', explode("\n", $text, 2)), 2, '');
                $this->state[$chatId] = ['flow' => 'post', 'topic' => $topic, 'details' => $details, 'type' => '', 'await' => ''];
                $this->askTemplate($tg, $chatId);
                return;
            case 'feedback':
                $v = $this->store->variant((int) $state['variant_id']);
                if ($v) {
                    $this->store->rate($v['db_id'], (int) ($v['rating'] ?? 3), $text);
                }
                unset($this->state[$chatId]);
                $tg->message("Rahmat! Izohingiz saqlandi — O'qituvchi agent shundan qoida chiqaradi.", BotUi::mainKeyboard());
                return;
            case 'plan_wish':
                unset($this->state[$chatId]);
                $this->startJob($chatId, 'plan', ['wishes' => $text]);
                return;
        }

        $this->freeText($tg, $chatId, $text);
    }

    /** Erkin matn → Manager (AI) nima kerakligini tushunadi. */
    private function freeText(Telegram $tg, string $chatId, string $text): void
    {
        $tg->action();
        $d = $this->manager->decide($chatId, $text);
        $markup = null;
        if ($d['ask_field'] !== '' && ($kb = Telegram::fieldKeyboard($d['ask_field'], $this->tones)) !== null) {
            $markup = json_decode($kb, true);
        } elseif (!empty($d['knowledge_id'])) {
            $markup = BotUi::inline([[['↩️ Bekor qilish', "unk:{$d['knowledge_id']}"]]]);
        }
        if ($d['action'] === 'run_copywriter' && $d['brief']) {
            $this->startJob($chatId, 'post', ['brief' => $d['brief'], 'template_id' => 0], $d['reply']);
            return;
        }
        if ($d['action'] === 'run_plan') {
            $this->startJob($chatId, 'plan', ['wishes' => $d['plan_wishes']], $d['reply']);
            return;
        }
        $tg->message($d['reply'], $markup);
    }

    // ==================== POST YOZISH OQIMI ====================

    private function askTopic(Telegram $tg, string $chatId): void
    {
        $this->state[$chatId] = ['flow' => 'post', 'await' => 'topic'];
        $rows = [];
        foreach (array_slice(Marketing::products(), 0, 8) as $p) {
            $rows[] = [['🔥 ' . mb_strimwidth((string) $p['name'], 0, 40, '…'), 'prod:' . $p['id']]];
        }
        $hint = "Mavzuni yozing. Ikkinchi qatorga tafsilot qo'shsangiz bo'ladi, masalan:\nVyetnam, Fukuok — oktabr\n820\$ dan, 12 va 19 oktabr, nonushta kiradi";
        $tg->message($rows ? "Qaysi tur haqida? Katalogdan tanlang yoki mavzuni o'zingiz yozing.\n\n$hint" : "✍️ $hint", $rows ? BotUi::inline($rows) : null);
    }

    private function askTemplate(Telegram $tg, string $chatId): void
    {
        $type = $this->state[$chatId]['type'] ?? '';
        $rows = [];
        foreach ($this->store->activeTemplates(null, $type !== '' ? $type : null) as $t) {
            $rows[] = [[mb_strimwidth($t['name'], 0, 45, '…'), "tpl:{$t['id']}"]];
        }
        $rows[] = [['📋 Shablonsiz — 2 post + 2 reklama', 'tpl:0']];
        $tg->message("Qanday formatda? Shablon — sahifangizning tasdiqlangan post turi.", BotUi::inline(array_slice($rows, -10)));
    }

    private function launchPost(Telegram $tg, string $chatId): void
    {
        $s = $this->state[$chatId] ?? [];
        $template = !empty($s['template_id']) ? $this->store->row('templates', (int) $s['template_id']) : null;
        $type = $s['type'] ?: ($template['tourism_type'] ?? '');
        if ($type === '') {
            $rows = array_map(static fn ($k, $t) => [[$t['label'], "ptype:$k"]], array_keys($this->tones), $this->tones);
            $tg->message("Qaysi yo'nalish?", BotUi::inline($rows));
            return;
        }
        $goal = ['qamrov' => 'jalb', 'ishonch' => 'brend', 'sotuv' => 'lid'][$template['stage'] ?? ''] ?? 'lid';
        $brief = Brief::normalize(['topic' => $s['topic'], 'details' => $s['details'] ?? '', 'tourism_type' => $type, 'goal' => $goal], $this->tones);
        unset($this->state[$chatId]);
        $this->startJob($chatId, 'post', ['brief' => $brief, 'template_id' => (int) ($template['id'] ?? 0)],
            "✍️ Yozyapman: {$brief['topic']}" . ($template ? " ({$template['name']})" : ''));
    }

    // ==================== REJA, OXIRGI ISHLAR ====================

    private function planMenu(Telegram $tg): void
    {
        $tg->message("🗓 Haftalik reja: kontent-strateg reja tuzadi, copywriter har kun uchun tayyor material yozadi (5-10 daqiqa).", BotUi::inline([
            [['▶️ Hozir tuzish', 'plan:go'], ["✏️ Istak bilan", 'plan:wish']],
            [["📄 Shu haftaning rejasi", 'plan:show']],
        ]));
    }

    private function recent(Telegram $tg): void
    {
        $rows = [];
        foreach ($this->store->recentBriefs(8) as $b) {
            $rows[] = [["#{$b['id']} " . mb_strimwidth($b['topic'], 0, 40, '…'), "show:{$b['id']}"]];
        }
        $tg->message($rows ? "📂 Oxirgi ishlar — qaysi birini ochay?" : "Hali ish yo'q. \"" . BotUi::BTN_POST . '" dan boshlang.', $rows ? BotUi::inline($rows) : null);
    }

    // ==================== TUGMALAR ====================

    private function onCallback(array $cb): void
    {
        $chatId = (string) ($cb['message']['chat']['id'] ?? '');
        $messageId = (int) ($cb['message']['message_id'] ?? 0);
        $tg = new Telegram($this->token, $chatId);
        [$cmd, $arg, $arg2] = array_pad(explode(':', (string) ($cb['data'] ?? ''), 3), 3, '');
        $answer = '';
        if (!$this->allowedChat($chatId, $tg)) {
            $tg->api('answerCallbackQuery', ['callback_query_id' => $cb['id']]);
            return;
        }
        echo "→ [$chatId] (tugma) {$cb['data']}\n";

        switch ($cmd) {
            case 'rate':
                $v = $this->store->variant((int) $arg);
                if ($v) {
                    $n = max(1, min(5, (int) $arg2));
                    $this->store->rate($v['db_id'], $n, (string) ($v['feedback'] ?? ''));
                    $tg->edit($messageId, null, BotUi::variantKeyboard($v['db_id'], $n));
                    $answer = "Baho: $n ✓";
                    if ($n <= 3) {
                        $this->state[$chatId] = ['await' => 'feedback', 'variant_id' => $v['db_id']];
                        $tg->message("Nima yoqmadi? Bir gap bilan yozing (masalan: \"juda uzun\", \"narx yo'q\") — agentlar shundan o'rganadi.\nO'tkazib yuborish: /bekor");
                    }
                }
                break;
            case 'gold':
                $v = $this->store->variant((int) $arg);
                if ($v) {
                    $this->store->addHouseExample(trim(Copywriter::variantText($v)), $v['tourism_type'], (string) ($v['feedback'] ?? ''));
                    $answer = "🏅 Oltin namunalarga qo'shildi";
                }
                break;
            case 'design':
                $this->startJob($chatId, 'design', ['variant_id' => (int) $arg], '🎨 Dizayner maket tayyorlayapti...');
                break;
            case 'again':
                $this->startJob($chatId, 'again', ['brief_id' => (int) $arg], '🔁 Qayta yozyapman...');
                break;
            case 'retry':
                $job = $this->store->job((int) $arg);
                if ($job && $job['status'] === 'failed') {
                    $tg->edit($messageId, '🔁 Qayta urinilmoqda...');
                    $this->startJob($chatId, $job['type'], ['progress_message_id' => $messageId] + $job['payload'], silent: true);
                }
                break;
            case 'newpost':
                $this->askTopic($tg, $chatId);
                break;
            case 'menu':
                $tg->message('Menyudan tanlang 👇', BotUi::mainKeyboard());
                break;
            case 'prod':
                $p = current(array_filter(Marketing::products(), static fn ($x) => (string) $x['id'] === $arg));
                if ($p) {
                    $this->state[$chatId] = ['flow' => 'post', 'topic' => (string) $p['name'], 'details' => "Katalogdagi tur: {$p['name']}", 'type' => (string) $p['type'], 'await' => ''];
                    $this->askTemplate($tg, $chatId);
                }
                break;
            case 'tpl':
                if (($this->state[$chatId]['flow'] ?? '') === 'post' && !empty($this->state[$chatId]['topic'])) {
                    $this->state[$chatId]['template_id'] = (int) $arg;
                    $this->launchPost($tg, $chatId);
                } else {
                    $answer = 'Avval mavzuni tanlang';
                    $this->askTopic($tg, $chatId);
                }
                break;
            case 'ptype':
                if (isset($this->tones[$arg]) && !empty($this->state[$chatId]['topic'])) {
                    $this->state[$chatId]['type'] = $arg;
                    $this->launchPost($tg, $chatId);
                }
                break;
            case 'plan':
                if ($arg === 'go') {
                    $this->startJob($chatId, 'plan', ['wishes' => '']);
                } elseif ($arg === 'wish') {
                    $this->state[$chatId] = ['await' => 'plan_wish'];
                    $tg->message("Shu hafta nimaga urg'u beramiz? Masalan: \"Vyetnam va Sharm, bitta mijoz sharhi bo'lsin\"");
                } else {
                    $plan = $this->store->plan(ContentPlanner::weekKey(new DateTimeImmutable('today')));
                    $plan ? BotUi::deliverPlan($tg, $plan) : $tg->message("Bu hafta uchun reja hali tuzilmagan.", BotUi::inline([[['▶️ Hozir tuzish', 'plan:go']]]));
                }
                break;
            case 'show':
                $this->showBrief($tg, (int) $arg);
                break;
            case 'fwd':
                $text = $this->state[$chatId]['text'] ?? '';
                unset($this->state[$chatId]);
                if ($arg === 'yes' && $text !== '') {
                    $this->store->addHouseExample($text);
                    $tg->edit($messageId, "✅ Uslub namunasi qilib saqlandi. Eng yaxshi 10-20 ta postingizni shunday forward qiling.");
                } else {
                    $tg->edit($messageId, 'Saqlanmadi.');
                }
                break;
            case 'unk':
                $this->store->deleteRow('knowledge', (int) $arg);
                $tg->edit($messageId, "↩️ Bekor qilindi — bu fakt eslab qolinmadi.");
                break;
            default:
                if (in_array($cmd, self::FIELDS, true)) {
                    // Manager so'ragan maydon: qiymatni to'g'ridan-to'g'ri brifga yozamiz, AI'ga esa tushunarli matn
                    $brief = $this->store->conversationBrief($chatId);
                    $brief[$cmd] = $arg;
                    $this->store->saveConversationBrief($chatId, $brief);
                    $tg->edit($messageId, null, ['inline_keyboard' => []]);
                    $label = match ($cmd) {
                        'tourism_type' => $this->tones[$arg]['label'] ?? $arg,
                        'goal' => Brief::GOALS[$arg] ?? $arg,
                        default => Brief::LANGUAGES[$arg] ?? $arg,
                    };
                    $this->freeText($tg, $chatId, $label);
                }
        }
        $tg->api('answerCallbackQuery', ['callback_query_id' => $cb['id'], 'text' => $answer]);
    }

    private function showBrief(Telegram $tg, int $briefId): void
    {
        $brief = $this->store->brief($briefId);
        $result = $brief ? $this->store->result($briefId, Copywriter::NAME, 'final') : null;
        if (!$brief || !$result) {
            $tg->message("Bu ish uchun natija topilmadi.");
            return;
        }
        $saved = array_column($this->store->variants($briefId), null, 'db_id');
        foreach ($result['variants'] as $v) {
            $rating = isset($saved[$v['db_id']]['rating']) ? (int) $saved[$v['db_id']]['rating'] : null;
            $tg->message(BotUi::variantMessage($v, "#{$brief['id']} {$brief['topic']}"), BotUi::variantKeyboard((int) $v['db_id'], $rating));
        }
        $tg->message("Shu mavzuda yana?", BotUi::inline([[['🔁 Qayta yozish', "again:$briefId"], ['✍️ Yangi post', 'newpost']]]));
    }

    // ==================== YORDAMCHI ====================

    private function reset(string $chatId): void
    {
        unset($this->state[$chatId]);
        $this->store->clearConversationBrief($chatId);
    }

    /** Uzoq ishni fon jarayonida boshlaydi; foydalanuvchiga darhol holat xabari ketadi. */
    private function startJob(string $chatId, string $type, array $payload, string $intro = '', bool $silent = false): void
    {
        $tg = new Telegram($this->token, $chatId);
        if ($this->store->runningJobs() >= self::MAX_PARALLEL_JOBS) {
            $tg->message("⏳ Hozir " . self::MAX_PARALLEL_JOBS . " ta ish bajarilmoqda. Ular tugagach qayta bosing.");
            return;
        }
        if (!$silent || empty($payload['progress_message_id'])) {
            $intro = $intro !== '' ? $intro : ($type === 'plan' ? '🗓 Haftalik reja tuzilmoqda (5-10 daqiqa)...' : '⏳ Ishlayapman...');
            $payload['progress_message_id'] = (int) ($tg->message($intro . "\nBu vaqtda botdan foydalanishda davom etishingiz mumkin.")['message_id'] ?? 0);
        }
        BotJobs::spawn($this->store->createJob($chatId, $type, $payload));
    }
}
