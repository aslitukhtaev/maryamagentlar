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
        SQL);
    }
}
