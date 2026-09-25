<?php

declare(strict_types=1);

namespace Maryam;

use DateTimeImmutable;

/**
 * Marketing bo'limining UMUMIY BILIMI — barcha agentlar bir xil manbadan foydalanadi:
 * brend faktlari, mahsulotlar katalogi, mavsumiy kalendar va kompaniyaning o'z uslubidagi
 * namuna postlar. Bitta joyda yangilasangiz — hamma agent darhol biladi.
 */
final class Marketing
{
    private static ?array $fileProducts = null;
    private static ?Store $store = null;
    private static ?array $settings = null;

    /** Bazadagi (web'da kiritilgan) katalogni ham o'qish uchun — bootstrap chaqiradi. */
    public static function useStore(Store $store): void
    {
        self::$store = $store;
    }

    public static function settings(): array
    {
        return self::$settings ??= require ROOT . '/config/marketing.php';
    }

    /** Sotuvdagi turlar: web katalogi + config/products.php (ixtiyoriy: bitta turizm turi bo'yicha). */
    public static function products(?string $type = null): array
    {
        self::$fileProducts ??= is_file(ROOT . '/config/products.php') ? require ROOT . '/config/products.php' : [];
        $dbProducts = array_map(static function (array $p) {
            $p['id'] = 'p' . $p['id'];
            $p['active'] = (bool) $p['active'];
            $p['selling_points'] = array_values(array_filter(array_map('trim', explode("\n", $p['selling_points']))));
            unset($p['created_at']);
            return $p;
        }, self::$store ? self::$store->rows('products') : []);

        return array_values(array_filter(
            [...$dbProducts, ...self::$fileProducts],
            static fn (array $p) => ($p['active'] ?? true) && ($type === null || ($p['type'] ?? '') === $type)
        ));
    }

    /** Agent kontekstiga beriladigan qisqa katalog: bo'sh maydonlarsiz. */
    public static function productsForPrompt(?string $type = null): array
    {
        return array_map(
            static fn (array $p) => array_filter($p, static fn ($v, $k) => $k !== 'active' && $v !== '' && $v !== [], ARRAY_FILTER_USE_BOTH),
            self::products($type)
        );
    }

    /**
     * Agentga o'rgatilgan hamma narsa: faol qoidalar va (berilsa) tanlangan shablon.
     * Barcha agentlar shu orqali oladi — O'qitish markazida o'zgartirsangiz, darhol ta'sir qiladi.
     */
    public static function training(Store $store, string $agent, ?int $templateId = null): array
    {
        $out = ['company_rules' => $store->activeRules($agent)];
        $t = $templateId ? $store->row('templates', $templateId) : null;
        if ($t) {
            $out['template'] = array_filter([
                'nomi' => $t['name'],
                'format' => $t['format'],
                'tuzilma' => $t['structure'],
                'namuna' => $t['example'],
                'qoidalar' => $t['rules'],
                'dizayn' => $t['design'],
            ], static fn ($v) => $v !== '');
        }
        return $out;
    }

    /** config/brand.php + Telegram orqali o'rgatilgan faktlar. */
    public static function brand(array $brand, Store $store): array
    {
        $brand['facts'] = array_values(array_unique(array_merge($brand['facts'] ?? [], $store->knowledgeFacts())));
        return $brand;
    }

    /**
     * Reklamasi hozir boshlanishi kerak bo'lgan voqealar: voqea sanasigacha 'lead_days'
     * dan kam qolgan (yoki keyingi $horizonDays ichida shunday bo'ladigan) va hali o'tmagan.
     */
    public static function upcomingEvents(DateTimeImmutable $today, int $horizonDays = 7): array
    {
        $out = [];
        foreach (self::settings()['events'] ?? [] as $e) {
            foreach (self::occurrences($e, $today) as $date) {
                $daysUntil = (int) $today->diff($date)->format('%r%a');
                if ($daysUntil < 0 || $daysUntil - ($e['lead_days'] ?? 30) > $horizonDays) {
                    continue;
                }
                $out[] = [
                    'name' => $e['name'],
                    'date' => $date->format('Y-m-d'),
                    'days_until' => $daysUntil,
                    'types' => $e['types'] ?? [],
                    'note' => $e['note'] ?? '',
                ];
            }
        }
        usort($out, static fn ($a, $b) => $a['days_until'] <=> $b['days_until']);
        return $out;
    }

    /** @return DateTimeImmutable[] */
    private static function occurrences(array $event, DateTimeImmutable $today): array
    {
        if (isset($event['date'])) {
            return [new DateTimeImmutable($event['date'])];
        }
        $year = (int) $today->format('Y');
        return [
            new DateTimeImmutable($year . '-' . $event['every_year']),
            new DateTimeImmutable(($year + 1) . '-' . $event['every_year']),
        ];
    }
}
