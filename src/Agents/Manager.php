<?php

declare(strict_types=1);

namespace Maryam\Agents;

use InvalidArgumentException;
use Maryam\Brief;
use Maryam\Marketing;
use Maryam\Store;

/**
 * MANAGER (ORCHESTRATOR) — Telegram orqali foydalanuvchi bilan tabiiy suhbat qiladi,
 * ehtiyojni aniqlab, tegishli agentga (hozircha Copywriter) topshiriq beradi.
 *
 * Har xabarda 3 xil harakatdan birini tanlaydi:
 *   - "chat"           — oddiy javob yoki yetishmagan ma'lumotni so'rash
 *   - "save_knowledge" — kompaniya haqidagi yangi faktni bilimlar bazasiga saqlash
 *   - "run_copywriter" — brif to'liq bo'lsa, Copywriter agentini chaqirish
 *   - "run_plan"       — haftalik kontent-reja + tayyor matnlar (Kontent-strateg)
 *
 * Suhbat holati (to'plangan qisman brif, xabarlar tarixi) bazada saqlanadi —
 * shuning uchun foydalanuvchi bir necha xabarda bosqichma-bosqich ma'lumot bersa ham
 * hech narsa yo'qolmaydi.
 */
final class Manager
{
    public const NAME = 'manager';

    public function __construct(
        private object $ai,
        private Store $store,
        private array $brand,
        private array $tones,
    ) {
    }

    /**
     * TEZKOR bosqich — faqat qaror qabul qiladi va DARHOL javob qaytaradi.
     * Copywriter'ni ISHGA TUSHIRMAYDI (u bir necha daqiqa davom etishi mumkin —
     * shuning uchun foydalanuvchi "jim qolib ketdi" deb o'ylamasin, avval tezkor
     * javob boradi, keyin bot alohida runCopywriter()ni chaqiradi).
     *
     * @return array{action: string, reply: string, ask_field: string, brief: ?array, plan_wishes: string}
     */
    public function decide(string $chatId, string $userText): array
    {
        $this->store->addChatMessage($chatId, 'user', $userText);

        $partialBrief = $this->store->conversationBrief($chatId);
        $context = [
            'partial_brief' => $partialBrief,
            'history' => $this->store->recentChatMessages($chatId, 12),
            'company_facts_count' => count($this->store->knowledgeFacts()),
            'products' => array_map(
                static fn (array $p) => ($p['name'] ?? $p['id'] ?? '') . ' [' . ($p['type'] ?? '') . ']',
                Marketing::products()
            ),
            'tourism_types' => array_keys($this->tones),
            'goals' => array_keys(Brief::GOALS),
            'languages' => array_keys(Brief::LANGUAGES),
        ];

        $system = file_get_contents(ROOT . '/prompts/manager/orchestrate.md');
        $user = "Kontekst (JSON):\n" . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
              . "\n\nVazifani bajar va faqat ko'rsatilgan formatdagi JSON qaytar.";

        $result = $this->ai->json($system, $user, 0.4, true);
        $this->store->logRun(null, self::NAME, 'orchestrate', $user, $result);
        $data = $result['data'] ?? [];

        $action = (string) ($data['action'] ?? 'chat');
        $reply = trim((string) ($data['reply'] ?? "Kechirasiz, tushunmadim, boshqacha ayting."));
        $askField = in_array($data['ask_field'] ?? '', ['tourism_type', 'goal', 'language'], true)
            ? $data['ask_field'] : '';
        $newBrief = array_filter(
            (array) ($data['brief'] ?? []),
            static fn ($v) => is_string($v) && trim($v) !== ''
        );
        $mergedBrief = array_merge($partialBrief, $newBrief);

        if ($action === 'save_knowledge' && !empty(trim((string) ($data['knowledge']['content'] ?? '')))) {
            $this->store->addKnowledge(
                trim((string) $data['knowledge']['content']),
                trim((string) ($data['knowledge']['category'] ?? '')) ?: 'umumiy'
            );
        }

        $briefReady = null;
        if ($action === 'run_copywriter') {
            try {
                $briefReady = Brief::normalize($mergedBrief, $this->tones);
                $this->store->clearConversationBrief($chatId); // yangi so'rov uchun toza boshlanadi
            } catch (InvalidArgumentException $e) {
                // Brif hali to'liq emas ekan — foydalanuvchidan so'rab, holatni saqlaymiz
                $action = 'chat';
                $reply = "Buni tayyorlash uchun yana bir narsa kerak: " . $e->getMessage();
                $this->store->saveConversationBrief($chatId, $mergedBrief);
            }
        } else {
            $this->store->saveConversationBrief($chatId, $mergedBrief);
        }

        $this->store->addChatMessage($chatId, 'bot', $reply);

        return [
            'action' => $action,
            'reply' => $reply,
            'ask_field' => $askField,
            'brief' => $briefReady,
            'plan_wishes' => trim((string) ($data['plan_wishes'] ?? '')),
        ];
    }

    /** SEKIN bosqich — decide()'dan "run_copywriter" chiqsa, shu alohida chaqiriladi. */
    public function runCopywriter(array $brief, array $options = []): array
    {
        $brief['id'] = $this->store->saveBrief($brief);
        $agent = new Copywriter($this->ai, $this->store, $this->brand, $this->tones);
        return $agent->run($brief, $options);
    }
}
