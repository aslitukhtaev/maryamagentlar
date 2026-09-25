<?php
/**
 * Haftalik kontent-reja + tayyor matnlar (Kontent-strateg + Copywriter).
 *
 *   php bin/reja.php                               — shu hafta uchun reja va tayyor matnlar
 *   php bin/reja.php "Ramazon Umrasiga urg'u ber"  — istak bilan
 *   php bin/reja.php --mock                        — real AI'siz sinov
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Maryam\Agents\ContentPlanner;
use Maryam\GeminiMock;
use Maryam\Output;

$args = array_slice($argv, 1);
$mock = in_array('--mock', $args, true);
$wishes = implode(' ', array_filter($args, static fn ($a) => $a !== '--mock'));

['ai' => $ai, 'store' => $store, 'brand' => $brand, 'tones' => $tones] = $mock ? app() : appVertex();
if ($mock) {
    $ai = new GeminiMock();
}

$plan = (new ContentPlanner($ai, $store, $brand, $tones))->run(new DateTimeImmutable('today'), [
    'wishes' => $wishes,
    'progress' => static fn (string $m) => print("… $m\n"),
]);

$text = ContentPlanner::toText($plan);
$path = Output::save(['topic' => 'haftalik-reja-' . $plan['week'], 'id' => 0], 'kontent-paket.txt', $text);
echo "\n$text\nSaqlandi: $path\n";
