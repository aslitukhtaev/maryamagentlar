<?php

declare(strict_types=1);

namespace Maryam;

/**
 * Agent promptlari: web'da tahrirlangan eng oxirgi versiya, bo'lmasa prompts/<nom>.md fayli.
 * Shunday qilib promptni kod/faylga tegmasdan o'zgartirasiz va xohlagan payt eskisiga qaytasiz.
 */
final class Prompts
{
    /** Tahrirlanadigan promptlar va ularning vazifasi (web'da ko'rinadi). */
    public const ALL = [
        'copywriter/strategy' => 'Copywriter · 1-bosqich: strategiya (auditoriya, burchaklar)',
        'copywriter/write' => 'Copywriter · 2-bosqich: yozish',
        'copywriter/edit' => 'Copywriter · 3-bosqich: muharrir tahriri',
        'planner/plan' => 'Kontent-strateg: haftalik reja',
        'designer/prompt' => 'Dizayner: vizual va rasm prompti',
        'manager/orchestrate' => "Manager: Telegram suhbat va topshiriq berish",
        'trainer/rules' => "O'qituvchi: baholardan qoida chiqarish",
        'trainer/template' => "O'qituvchi: namunadan shablon yasash",
    ];

    public static function get(Store $store, string $name): string
    {
        return $store->latestPrompt($name)['content'] ?? self::original($name);
    }

    public static function original(string $name): string
    {
        if (!isset(self::ALL[$name])) {
            throw new \InvalidArgumentException("Noma'lum prompt: $name");
        }
        return (string) file_get_contents(ROOT . "/prompts/$name.md");
    }

    /** Bitta AI so'rovi: prompt + kontekst (JSON) -> ai->json() natijasi. */
    public static function ask(object $ai, Store $store, string $name, array $context, float $temperature, bool $smart, ?int $briefId = null, string $agent = '', string $step = '', array $images = []): array
    {
        $user = "Kontekst (JSON):\n" . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
              . "\n\nVazifani bajar va faqat ko'rsatilgan formatdagi JSON qaytar.";
        $result = $images ? $ai->json(self::get($store, $name), $user, $temperature, $smart, $images)
                          : $ai->json(self::get($store, $name), $user, $temperature, $smart);
        $store->logRun($briefId, $agent ?: explode('/', $name)[0], $step ?: explode('/', $name)[1], $user, $result);
        return $result['data'] ?? [];
    }
}
