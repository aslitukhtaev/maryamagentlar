<?php

declare(strict_types=1);

namespace Maryam;

use PDO;

/**
 * Bazaga yozish/o'qishning hammasi shu yerda — agentlar SQL bilan ovora bo'lmaydi.
 */
final class Store
{
    public function __construct(public readonly PDO $db)
    {
    }

    public function saveBrief(array $b): int
    {
        $this->db->prepare('INSERT INTO briefs (topic, tourism_type, goal, details, audience, language)
                            VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$b['topic'], $b['tourism_type'], $b['goal'], $b['details'], $b['audience'], $b['language']]);
        return (int) $this->db->lastInsertId();
    }

    public function brief(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM briefs WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function recentBriefs(int $limit = 30): array
    {
        return $this->db->query('SELECT * FROM briefs ORDER BY id DESC LIMIT ' . $limit)->fetchAll();
    }

    /** AI chaqiruvini jurnalga yozadi. */
    public function logRun(?int $briefId, string $agent, string $step, string $input, array $result): void
    {
        $this->db->prepare('INSERT INTO agent_runs (brief_id, agent, step, model, input, output, tokens_in, tokens_out, duration_ms)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$briefId, $agent, $step, $result['model'], $input, $result['text'],
                       $result['tokens_in'], $result['tokens_out'], $result['ms']]);
    }

    public function saveResult(int $briefId, string $agent, string $kind, array $data): void
    {
        $this->db->prepare('INSERT INTO agent_results (brief_id, agent, kind, data) VALUES (?, ?, ?, ?)')
            ->execute([$briefId, $agent, $kind, json_encode($data, JSON_UNESCAPED_UNICODE)]);
    }

    public function result(int $briefId, string $agent, string $kind): ?array
    {
        $st = $this->db->prepare('SELECT data FROM agent_results WHERE brief_id = ? AND agent = ? AND kind = ?
                                  ORDER BY id DESC LIMIT 1');
        $st->execute([$briefId, $agent, $kind]);
        $row = $st->fetch();
        return $row ? json_decode($row['data'], true) : null;
    }

    public function saveVariant(int $briefId, array $v): int
    {
        $this->db->prepare('INSERT INTO copy_variants (brief_id, kind, angle, framework, data, score)
                            VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$briefId, $v['kind'], $v['angle'], $v['framework'],
                       json_encode($v, JSON_UNESCAPED_UNICODE), $v['score'] ?? 0]);
        return (int) $this->db->lastInsertId();
    }

    public function variants(int $briefId): array
    {
        $st = $this->db->prepare('SELECT * FROM copy_variants WHERE brief_id = ? ORDER BY id');
        $st->execute([$briefId]);
        return array_map(fn ($r) => ['db_id' => (int) $r['id'], 'rating' => $r['rating'], 'feedback' => $r['feedback']]
            + json_decode($r['data'], true), $st->fetchAll());
    }

    /** Inson bahosi: 1 (yomon) .. 5 (a'lo) + ixtiyoriy izoh. */
    public function rate(int $variantId, int $rating, string $feedback = ''): bool
    {
        $st = $this->db->prepare('UPDATE copy_variants SET rating = ?, feedback = ? WHERE id = ?');
        $st->execute([max(1, min(5, $rating)), $feedback, $variantId]);
        return $st->rowCount() > 0;
    }

    /**
     * Shu turizm turi bo'yicha oldin baholangan variantlar.
     * Copywriter ularni "yaxshi namuna" / "yomon namuna" sifatida o'rganadi.
     */
    public function ratedExamples(string $tourismType, bool $good, int $limit = 3): array
    {
        $cond = $good ? 'v.rating >= 4' : 'v.rating <= 2';
        $st = $this->db->prepare("SELECT v.data, v.rating, v.feedback FROM copy_variants v
                                  JOIN briefs b ON b.id = v.brief_id
                                  WHERE b.tourism_type = ? AND $cond
                                  ORDER BY v.rating " . ($good ? 'DESC' : 'ASC') . ", v.id DESC LIMIT $limit");
        $st->execute([$tourismType]);
        return array_map(fn ($r) => [
            'rating' => (int) $r['rating'],
            'feedback' => $r['feedback'],
            'variant' => array_diff_key(json_decode($r['data'], true), array_flip(['scores', 'issues', 'changes', 'score', 'warnings', 'db_id'])),
        ], $st->fetchAll());
    }

    // ==================== BILIMLAR BAZASI (kompaniya faktlari) ====================

    /** Yangi fakt qo'shadi (Telegram orqali "o'rgatilganda"). */
    public function addKnowledge(string $content, string $category = 'umumiy'): void
    {
        $this->db->prepare('INSERT INTO knowledge (category, content) VALUES (?, ?)')
            ->execute([$category, $content]);
    }

    /** Barcha faktlarni oddiy matn ro'yxati sifatida qaytaradi — agentlar shu bilan ishlaydi. */
    public function knowledgeFacts(int $limit = 200): array
    {
        $st = $this->db->prepare('SELECT content FROM knowledge ORDER BY id DESC LIMIT ?');
        $st->bindValue(1, $limit, PDO::PARAM_INT);
        $st->execute();
        return array_column($st->fetchAll(), 'content');
    }

    // ==================== UY USLUBI (kompaniyaning o'z namuna postlari) ====================

    public function addHouseExample(string $content, string $tourismType = '', string $note = ''): void
    {
        $this->db->prepare('INSERT INTO house_examples (tourism_type, content, note) VALUES (?, ?, ?)')
            ->execute([$tourismType, $content, $note]);
    }

    /** Shu yo'nalish bo'yicha va umumiy namunalar, eng yangilari birinchi. */
    public function houseExamples(string $tourismType, int $limit = 5): array
    {
        $st = $this->db->prepare("SELECT content, note FROM house_examples WHERE tourism_type IN (?, '')
                                  ORDER BY (tourism_type = ?) DESC, id DESC LIMIT ?");
        $st->bindValue(1, $tourismType);
        $st->bindValue(2, $tourismType);
        $st->bindValue(3, $limit, PDO::PARAM_INT);
        $st->execute();
        return array_map(
            static fn ($r) => $r['note'] !== '' ? ['post' => $r['content'], 'nega_yaxshi' => $r['note']] : $r['content'],
            $st->fetchAll()
        );
    }

    // ==================== HAFTALIK KONTENT-REJALAR ====================

    public function savePlan(string $week, array $data): void
    {
        $this->db->prepare('INSERT INTO content_plans (week, data) VALUES (?, ?)
                            ON CONFLICT(week) DO UPDATE SET data = excluded.data')
            ->execute([$week, json_encode($data, JSON_UNESCAPED_UNICODE)]);
    }

    public function plan(string $week): ?array
    {
        $st = $this->db->prepare('SELECT data FROM content_plans WHERE week = ?');
        $st->execute([$week]);
        $row = $st->fetch();
        return $row ? json_decode($row['data'], true) : null;
    }

    /** Oldingi haftalardagi mavzular — reja takrorlanmasligi uchun. */
    public function recentPlanTopics(int $weeks = 4): array
    {
        $st = $this->db->prepare('SELECT data FROM content_plans ORDER BY week DESC LIMIT ?');
        $st->bindValue(1, $weeks, PDO::PARAM_INT);
        $st->execute();
        $topics = [];
        foreach ($st->fetchAll() as $row) {
            foreach (json_decode($row['data'], true)['items'] ?? [] as $item) {
                $topics[] = ($item['format'] ?? '') . ': ' . ($item['topic'] ?? '');
            }
        }
        return $topics;
    }

    // ==================== O'QITISH MARKAZI: umumiy CRUD ====================

    /** Web'dan tahrirlanadigan jadvallar va ularning ustunlari (boshqasiga yozib bo'lmaydi). */
    public const EDITABLE = [
        'templates' => ['name', 'format', 'tourism_type', 'stage', 'structure', 'example', 'rules', 'design', 'active'],
        'rules' => ['agent', 'content', 'status', 'source'],
        'products' => ['type', 'active', 'name', 'price', 'dates', 'duration', 'hotels', 'includes', 'excludes', 'seats', 'offer', 'selling_points'],
        'house_examples' => ['tourism_type', 'content', 'note'],
        'knowledge' => ['category', 'content'],
    ];

    public function rows(string $table): array
    {
        $this->assertTable($table);
        return $this->db->query("SELECT * FROM $table ORDER BY id DESC")->fetchAll();
    }

    public function row(string $table, int $id): ?array
    {
        $this->assertTable($table);
        $st = $this->db->prepare("SELECT * FROM $table WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function insertRow(string $table, array $data): int
    {
        $data = $this->onlyEditable($table, $data);
        $cols = implode(', ', array_keys($data));
        $marks = implode(', ', array_fill(0, count($data), '?'));
        $this->db->prepare("INSERT INTO $table ($cols) VALUES ($marks)")->execute(array_values($data));
        return (int) $this->db->lastInsertId();
    }

    public function updateRow(string $table, int $id, array $data): void
    {
        $data = $this->onlyEditable($table, $data);
        if (!$data) {
            return;
        }
        $set = implode(', ', array_map(static fn ($c) => "$c = ?", array_keys($data)));
        $this->db->prepare("UPDATE $table SET $set WHERE id = ?")->execute([...array_values($data), $id]);
    }

    public function deleteRow(string $table, int $id): void
    {
        $this->assertTable($table);
        $this->db->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
    }

    private function onlyEditable(string $table, array $data): array
    {
        $this->assertTable($table);
        return array_intersect_key($data, array_flip(self::EDITABLE[$table]));
    }

    private function assertTable(string $table): void
    {
        if (!isset(self::EDITABLE[$table])) {
            throw new \InvalidArgumentException("Noma'lum jadval: $table");
        }
    }

    // ==================== QOIDALAR, SHABLONLAR, PROMPTLAR ====================

    /** Agentga tegishli faol qoidalar ('all' + shu agent). */
    public function activeRules(string $agent): array
    {
        $st = $this->db->prepare("SELECT content FROM rules WHERE status = 'active' AND agent IN ('all', ?) ORDER BY id");
        $st->execute([$agent]);
        return array_column($st->fetchAll(), 'content');
    }

    /** Faol shablonlar; format/yo'nalish berilsa — shunga moslari ('' = hammasiga mos). */
    public function activeTemplates(?string $format = null, ?string $tourismType = null): array
    {
        $sql = 'SELECT * FROM templates WHERE active = 1';
        $args = [];
        if ($format !== null) {
            $sql .= ' AND format = ?';
            $args[] = $format;
        }
        if ($tourismType !== null) {
            $sql .= " AND tourism_type IN ('', ?)";
            $args[] = $tourismType;
        }
        $st = $this->db->prepare($sql . ' ORDER BY id');
        $st->execute($args);
        return $st->fetchAll();
    }

    public function latestPrompt(string $name): ?array
    {
        $st = $this->db->prepare('SELECT * FROM prompt_versions WHERE name = ? ORDER BY id DESC LIMIT 1');
        $st->execute([$name]);
        return $st->fetch() ?: null;
    }

    public function promptVersions(string $name): array
    {
        $st = $this->db->prepare('SELECT id, note, created_at, length(content) AS size FROM prompt_versions WHERE name = ? ORDER BY id DESC');
        $st->execute([$name]);
        return $st->fetchAll();
    }

    public function promptVersion(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM prompt_versions WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function savePromptVersion(string $name, string $content, string $note = ''): void
    {
        $this->db->prepare('INSERT INTO prompt_versions (name, content, note) VALUES (?, ?, ?)')
            ->execute([$name, $content, $note]);
    }

    /** O'qituvchi agent uchun: izoh yozilgan baholar (eng yangilari). */
    public function ratedWithFeedback(int $limit = 40): array
    {
        $st = $this->db->prepare("SELECT v.id, v.data, v.rating, v.feedback, b.topic, b.tourism_type FROM copy_variants v
                                  JOIN briefs b ON b.id = v.brief_id
                                  WHERE v.rating IS NOT NULL ORDER BY v.id DESC LIMIT ?");
        $st->bindValue(1, $limit, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }

    public function variant(int $id): ?array
    {
        $st = $this->db->prepare('SELECT v.*, b.tourism_type FROM copy_variants v JOIN briefs b ON b.id = v.brief_id WHERE v.id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ? ['db_id' => (int) $row['id'], 'rating' => $row['rating'], 'feedback' => $row['feedback'],
                       'tourism_type' => $row['tourism_type']] + json_decode($row['data'], true) : null;
    }

    public function recentPlans(int $limit = 12): array
    {
        $st = $this->db->prepare('SELECT week, created_at FROM content_plans ORDER BY week DESC LIMIT ?');
        $st->bindValue(1, $limit, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }

    /** Bosh sahifa uchun raqamlar. */
    public function stats(): array
    {
        $one = fn (string $sql) => $this->db->query($sql)->fetchColumn();
        return [
            'templates' => (int) $one('SELECT COUNT(*) FROM templates WHERE active = 1'),
            'rules' => (int) $one("SELECT COUNT(*) FROM rules WHERE status = 'active'"),
            'proposed' => (int) $one("SELECT COUNT(*) FROM rules WHERE status = 'proposed'"),
            'examples' => (int) $one('SELECT COUNT(*) FROM house_examples'),
            'products' => (int) $one('SELECT COUNT(*) FROM products WHERE active = 1'),
            'knowledge' => (int) $one('SELECT COUNT(*) FROM knowledge'),
            'briefs' => (int) $one('SELECT COUNT(*) FROM briefs'),
            'rated' => (int) $one('SELECT COUNT(*) FROM copy_variants WHERE rating IS NOT NULL'),
            'avg_rating' => (float) $one('SELECT AVG(rating) FROM (SELECT rating FROM copy_variants WHERE rating IS NOT NULL ORDER BY id DESC LIMIT 20)'),
            'unrated' => (int) $one('SELECT COUNT(*) FROM copy_variants WHERE rating IS NULL'),
        ];
    }

    // ==================== SUHBAT HOLATI (Telegram orchestrator uchun) ====================

    public function addChatMessage(string $chatId, string $role, string $content): void
    {
        $this->db->prepare('INSERT INTO chat_messages (chat_id, role, content) VALUES (?, ?, ?)')
            ->execute([$chatId, $role, $content]);
    }

    /** Oxirgi N ta xabar, eskisidan yangisiga qarab tartiblangan. */
    public function recentChatMessages(string $chatId, int $limit = 12): array
    {
        $st = $this->db->prepare('SELECT role, content FROM chat_messages WHERE chat_id = ? ORDER BY id DESC LIMIT ?');
        $st->bindValue(1, $chatId);
        $st->bindValue(2, $limit, PDO::PARAM_INT);
        $st->execute();
        return array_reverse($st->fetchAll());
    }

    /** Hali tugallanmagan brifning to'plangan qismi (masalan faqat mavzu ma'lum). */
    public function conversationBrief(string $chatId): array
    {
        $st = $this->db->prepare('SELECT brief_json FROM conversation_state WHERE chat_id = ?');
        $st->execute([$chatId]);
        $row = $st->fetch();
        return $row ? (json_decode($row['brief_json'], true) ?: []) : [];
    }

    public function saveConversationBrief(string $chatId, array $brief): void
    {
        $this->db->prepare(
            'INSERT INTO conversation_state (chat_id, brief_json, updated_at) VALUES (?, ?, datetime("now", "localtime"))
             ON CONFLICT(chat_id) DO UPDATE SET brief_json = excluded.brief_json, updated_at = excluded.updated_at'
        )->execute([$chatId, json_encode($brief, JSON_UNESCAPED_UNICODE)]);
    }

    public function clearConversationBrief(string $chatId): void
    {
        $this->db->prepare('DELETE FROM conversation_state WHERE chat_id = ?')->execute([$chatId]);
    }
}
