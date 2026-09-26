<?php

declare(strict_types=1);

namespace Maryam;

use PDO;

/**
 * SQLite ma'lumotlar bazasi — loyiha ichidagi bitta .db fayl.
 * Jadvallar birinchi ishga tushishda avtomatik yaratiladi (alohida o'rnatish shart emas).
 */
final class Database
{
    public static function connect(string $path): PDO
    {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL'); // web va CLI bir vaqtda ishlasa ham bloklanmasin
        self::migrate($pdo);
        return $pdo;
    }

    private static function migrate(PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            -- Har bir kiritilgan brif (mavzu + tafsilotlar)
            CREATE TABLE IF NOT EXISTS briefs (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                topic         TEXT NOT NULL,
                tourism_type  TEXT NOT NULL,
                goal          TEXT NOT NULL,
                details       TEXT NOT NULL DEFAULT '',
                audience      TEXT NOT NULL DEFAULT '',
                language      TEXT NOT NULL DEFAULT 'uz',
                created_at    TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
            );

            -- Agentlarning har bir AI chaqiruvi (xatolarni topish va xarajatni kuzatish uchun)
            CREATE TABLE IF NOT EXISTS agent_runs (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                brief_id      INTEGER REFERENCES briefs(id) ON DELETE CASCADE,
                agent         TEXT NOT NULL,
                step          TEXT NOT NULL,
                model         TEXT NOT NULL,
                input         TEXT NOT NULL,
                output        TEXT NOT NULL,
                tokens_in     INTEGER NOT NULL DEFAULT 0,
                tokens_out    INTEGER NOT NULL DEFAULT 0,
                duration_ms   INTEGER NOT NULL DEFAULT 0,
                created_at    TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
            );

            -- Agentlar natijalari (strategiya, tayyor matnlar va h.k.) JSON ko'rinishida
            CREATE TABLE IF NOT EXISTS agent_results (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                brief_id      INTEGER NOT NULL REFERENCES briefs(id) ON DELETE CASCADE,
                agent         TEXT NOT NULL,
                kind          TEXT NOT NULL,
                data          TEXT NOT NULL,
                created_at    TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
            );

            -- Copywriter yozgan har bir variant + sizning bahoingiz.
            -- Yuqori baholangan variantlar keyingi safar agentga "namuna" bo'lib beriladi.
            CREATE TABLE IF NOT EXISTS copy_variants (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                brief_id      INTEGER NOT NULL REFERENCES briefs(id) ON DELETE CASCADE,
                kind          TEXT NOT NULL,          -- social_post | ad
                angle         TEXT NOT NULL DEFAULT '',
                framework     TEXT NOT NULL DEFAULT '',
                data          TEXT NOT NULL,          -- to'liq variant (JSON)
                score         REAL NOT NULL DEFAULT 0, -- muharrir-agent bahosi (1-10)
                rating        INTEGER,                -- inson bahosi (1-5)
                feedback      TEXT NOT NULL DEFAULT '',
                created_at    TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
            );
            CREATE INDEX IF NOT EXISTS idx_variants_rating ON copy_variants(rating);

            -- Kompaniya haqidagi faktlar — Telegram orqali "o'rgatiladi",
            -- barcha agentlar (Copywriter va h.k.) bularni brend faktlariga qo'shib ishlatadi.
            CREATE TABLE IF NOT EXISTS knowledge (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                category      TEXT NOT NULL DEFAULT 'umumiy',
                content       TEXT NOT NULL,
                created_at    TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
            );

            -- Har bir Telegram chat uchun suhbat tarixi — orchestrator (Manager)
            -- shu tarixga qarab kontekstni tushunadi.
            CREATE TABLE IF NOT EXISTS chat_messages (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                chat_id       TEXT NOT NULL,
                role          TEXT NOT NULL,          -- user | bot
                content       TEXT NOT NULL,
                created_at    TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
            );
            CREATE INDEX IF NOT EXISTS idx_chat_messages_chat ON chat_messages(chat_id, id);

            -- Har bir chat uchun hali TO'LIQ bo'lmagan brif (masalan, mavzu bor,
            -- lekin turizm turi hali aytilmagan) — keyingi xabarda davom etadi.
            CREATE TABLE IF NOT EXISTS conversation_state (
                chat_id       TEXT PRIMARY KEY,
                brief_json    TEXT NOT NULL DEFAULT '{}',
                updated_at    TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
            );

            -- Kompaniyaning o'z eng yaxshi postlari (kanaldan botga forward qilinadi) —
            -- Copywriter shu uslubda yozishni o'rganadi.
            CREATE TABLE IF NOT EXISTS house_examples (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                tourism_type  TEXT NOT NULL DEFAULT '',   -- '' = barcha yo'nalishlar uchun
                content       TEXT NOT NULL,
                created_at    TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
            );

            -- Kontent-strateg tuzgan haftalik rejalar (bir hafta — bitta reja)
            CREATE TABLE IF NOT EXISTS content_plans (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                week          TEXT NOT NULL UNIQUE,       -- masalan 2026-W39
                data          TEXT NOT NULL,              -- reja + har band uchun tayyor matn (JSON)
                created_at    TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
            );

            -- ===== O'QITISH MARKAZI =====

            -- Post shablonlari: tuzilma (slotlar bilan), namuna, yozish qoidalari, dizayn ko'rsatmasi
            CREATE TABLE IF NOT EXISTS templates (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                name          TEXT NOT NULL,
                format        TEXT NOT NULL DEFAULT 'post',   -- post | reels | karusel | reklama
                tourism_type  TEXT NOT NULL DEFAULT '',       -- '' = barcha yo'nalishlar
                stage         TEXT NOT NULL DEFAULT 'sotuv',  -- qamrov | ishonch | sotuv
                structure     TEXT NOT NULL DEFAULT '',
                example       TEXT NOT NULL DEFAULT '',
                rules         TEXT NOT NULL DEFAULT '',
                design        TEXT NOT NULL DEFAULT '',
                active        INTEGER NOT NULL DEFAULT 1,
                created_at    TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
            );

            -- Agentlarga buyruqlar/qoidalar ("har doim...", "hech qachon...")
            CREATE TABLE IF NOT EXISTS rules (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                agent         TEXT NOT NULL DEFAULT 'all',    -- all | copywriter | planner | designer | manager
                content       TEXT NOT NULL,
                status        TEXT NOT NULL DEFAULT 'active', -- active | off | proposed (O'qituvchi taklifi)
                source        TEXT NOT NULL DEFAULT 'manual', -- manual | trainer | seed
                created_at    TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
            );

            -- Agent promptlarining versiyalari (eng oxirgisi — amaldagi; bo'lmasa prompts/*.md fayli)
            CREATE TABLE IF NOT EXISTS prompt_versions (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                name          TEXT NOT NULL,                  -- masalan copywriter/write
                content       TEXT NOT NULL,
                note          TEXT NOT NULL DEFAULT '',
                created_at    TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
            );
            CREATE INDEX IF NOT EXISTS idx_prompt_versions_name ON prompt_versions(name, id);

            -- Mahsulotlar katalogi (web'dan tahrirlanadi; config/products.php bilan birlashtiriladi)
            CREATE TABLE IF NOT EXISTS products (
                id             INTEGER PRIMARY KEY AUTOINCREMENT,
                type           TEXT NOT NULL DEFAULT 'outbound',
                active         INTEGER NOT NULL DEFAULT 1,
                name           TEXT NOT NULL,
                price          TEXT NOT NULL DEFAULT '',
                dates          TEXT NOT NULL DEFAULT '',
                duration       TEXT NOT NULL DEFAULT '',
                hotels         TEXT NOT NULL DEFAULT '',
                includes       TEXT NOT NULL DEFAULT '',
                excludes       TEXT NOT NULL DEFAULT '',
                seats          TEXT NOT NULL DEFAULT '',
                offer          TEXT NOT NULL DEFAULT '',
                selling_points TEXT NOT NULL DEFAULT '',      -- har qatorda bittadan
                created_at     TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
            );

            -- Telegram bot uzoq ishlarni (copywriter, reja, dizayn) alohida jarayonda bajaradi
            CREATE TABLE IF NOT EXISTS jobs (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                chat_id       TEXT NOT NULL,
                type          TEXT NOT NULL,                  -- post | again | plan | design
                payload       TEXT NOT NULL DEFAULT '{}',
                status        TEXT NOT NULL DEFAULT 'queued', -- queued | running | done | failed
                error         TEXT NOT NULL DEFAULT '',
                created_at    TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
                finished_at   TEXT
            );

            CREATE TABLE IF NOT EXISTS meta (
                key           TEXT PRIMARY KEY,
                value         TEXT NOT NULL
            );
        SQL);

        self::addColumn($pdo, 'house_examples', 'note', "TEXT NOT NULL DEFAULT ''");
        self::seed($pdo);
    }

    private static function addColumn(PDO $pdo, string $table, string $column, string $definition): void
    {
        $columns = array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(), 'name');
        if (!in_array($column, $columns, true)) {
            $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
        }
    }

    /** Boshlang'ich shablon va qoidalar — faqat bir marta (keyin o'chirsangiz qaytib kelmaydi). */
    private static function seed(PDO $pdo): void
    {
        if ($pdo->query("SELECT 1 FROM meta WHERE key = 'seeded'")->fetch() || !is_file(ROOT . '/config/seed.php')) {
            return;
        }
        $seed = require ROOT . '/config/seed.php';
        $pdo->beginTransaction();
        $tpl = $pdo->prepare('INSERT INTO templates (name, format, tourism_type, stage, structure, example, rules, design)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($seed['templates'] as $t) {
            $tpl->execute([$t['name'], $t['format'], $t['tourism_type'], $t['stage'], $t['structure'],
                           $t['example'] ?? '', $t['rules'] ?? '', $t['design'] ?? '']);
        }
        $rule = $pdo->prepare("INSERT INTO rules (agent, content, source) VALUES (?, ?, 'seed')");
        foreach ($seed['rules'] as [$agent, $content]) {
            $rule->execute([$agent, $content]);
        }
        $pdo->exec("INSERT INTO meta (key, value) VALUES ('seeded', datetime('now'))");
        $pdo->commit();
    }
}
