<?php

declare(strict_types=1);

namespace Maryam;

use InvalidArgumentException;

/**
 * Brif — barcha agentlar uchun umumiy kirish ma'lumoti.
 * Bu yerda u tekshiriladi va standart ko'rinishga keltiriladi.
 */
final class Brief
{
    public const GOALS = [
        'lid'     => "Lid yig'ish (Direct/Telegram'ga yozishsin, raqam qoldirishsin)",
        'sotuv'   => 'Sotuv / bron qilish',
        'brend'   => 'Brendni tanitish, ishonch oshirish',
        'jalb'    => "Jalb qilish (layk, saqlash, ulashish)",
    ];

    public const LANGUAGES = ['uz' => "O'zbekcha (lotin)", 'ru' => 'Ruscha', 'en' => 'Inglizcha'];

    public static function normalize(array $input, array $tones): array
    {
        $topic = trim((string) ($input['topic'] ?? ''));
        if ($topic === '') {
            throw new InvalidArgumentException('Mavzu (topic) kiritilmagan.');
        }
        $type = (string) ($input['tourism_type'] ?? '');
        if (!isset($tones[$type])) {
            throw new InvalidArgumentException("Noma'lum turizm turi: '$type'. Mavjudlari: " . implode(', ', array_keys($tones)));
        }
        $goal = (string) ($input['goal'] ?? 'lid');
        $language = (string) ($input['language'] ?? '') ?: $tones[$type]['language'];

        return [
            'topic'        => $topic,
            'tourism_type' => $type,
            'goal'         => isset(self::GOALS[$goal]) ? $goal : 'lid',
            'details'      => trim((string) ($input['details'] ?? '')),
            'audience'     => trim((string) ($input['audience'] ?? '')),
            'language'     => isset(self::LANGUAGES[$language]) ? $language : 'uz',
        ];
    }

    /** AI uchun o'qiladigan ko'rinish (kalitlar o'rniga tushunarli matnlar). */
    public static function forPrompt(array $brief, array $tones): array
    {
        return [
            'mavzu'          => $brief['topic'],
            'turizm_turi'    => $tones[$brief['tourism_type']]['label'],
            'maqsad'         => self::GOALS[$brief['goal']],
            'tafsilotlar'    => $brief['details'] ?: "(berilmagan — faktlarni to'qima, joy-belgi qo'y)",
            'auditoriya'     => $brief['audience'] ?: '(ton profilidagi standart auditoriya)',
            'language'       => $brief['language'],
        ];
    }
}
