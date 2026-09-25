<?php

declare(strict_types=1);

use Maryam\Agents\Copywriter;

function e(mixed $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES);
}

function url(array $params): string
{
    return '?' . http_build_query($params);
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function flash(?string $message = null, string $kind = 'ok'): ?array
{
    if ($message !== null) {
        $_SESSION['flash'] = [$kind, $message];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function options(array $items, string $selected = ''): string
{
    $out = '';
    foreach ($items as $value => $label) {
        $out .= '<option value="' . e($value) . '"' . ((string) $value === $selected ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    return $out;
}

/** Uzoq ishlaydigan (AI) forma: bosilganda tugma o'chadi va "ishlamoqda" yozuvi chiqadi. */
function busy_attr(): string
{
    return 'onsubmit="this.querySelector(\'button[type=submit]\').disabled=true; this.querySelector(\'.busy\').hidden=false;"';
}

const FORMATS = ['post' => 'Post', 'reels' => 'Reels', 'karusel' => 'Karusel', 'reklama' => 'Target reklama'];
const STAGES = ['qamrov' => 'Qamrov (yangi odamlar)', 'ishonch' => 'Ishonch', 'sotuv' => 'Sotuv'];
const AGENTS = ['all' => 'Barcha agentlar', 'copywriter' => 'Copywriter', 'planner' => 'Kontent-strateg', 'designer' => 'Dizayner', 'manager' => 'Manager (Telegram)'];

function type_options(array $tones, bool $withAll = true): array
{
    return ($withAll ? ['' => 'Barcha yo\'nalishlar'] : []) + array_map(static fn ($t) => $t['label'], $tones);
}

/** Bitta tayyor matn kartochkasi: matn, vizual, ogohlantirishlar, baholash, oltin namuna, dizayn. */
function variant_card(array $v, array $saved, string $return, ?int $briefId = null): void
{
    $text = Copywriter::variantText($v);
    ?>
    <div class="card variant" id="v<?= (int) $v['db_id'] ?>">
      <div class="variant-head">
        <b><?= $v['kind'] === 'ad' ? 'Target reklama' : e(FORMATS[$v['format'] ?? ''] ?? 'Post') ?></b>
        <?php if ($v['angle']): ?><span class="muted">· <?= e($v['angle']) ?></span><?php endif; ?>
        <span class="score" title="Muharrir-agent bahosi"><?= e($v['score']) ?>/10</span>
      </div>
      <?php if ($v['kind'] === 'ad'): ?>
        <p class="small"><b>Sarlavha:</b> <?= e($v['headline']) ?> <span class="muted">(<?= mb_strlen($v['headline']) ?>/40)</span> ·
          <b>Tavsif:</b> <?= e($v['description']) ?> <span class="muted">(<?= mb_strlen($v['description']) ?>/30)</span> ·
          <b>Tugma:</b> <?= e($v['cta_button']) ?></p>
      <?php endif; ?>
      <pre class="text"><?= e($text) ?></pre>
      <div class="actions">
        <button type="button" class="ghost" onclick="navigator.clipboard.writeText(this.closest('.card').querySelector('.text').innerText); this.textContent='Nusxalandi'">Nusxalash</button>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="golden">
          <input type="hidden" name="variant_id" value="<?= (int) $v['db_id'] ?>"><input type="hidden" name="return" value="<?= e($return) ?>">
          <button class="ghost" title="Agentlar shu uslubda yozishni o'rganadi">Oltin namuna qilish</button></form>
        <?php if ($briefId): ?>
        <form method="post" <?= busy_attr() ?>><?= csrf_field() ?><input type="hidden" name="action" value="design">
          <input type="hidden" name="brief_id" value="<?= $briefId ?>"><input type="hidden" name="variant_id" value="<?= (int) $v['db_id'] ?>">
          <button type="submit" class="ghost">Dizayn tayyorlash</button><span class="busy muted" hidden>Dizayner ishlamoqda…</span></form>
        <?php endif; ?>
      </div>
      <?php foreach ($v['warnings'] ?? [] as $w): ?><p class="warn">⚠ <?= e($w) ?></p><?php endforeach; ?>
      <?php if (!empty($v['changes'])): ?><details><summary class="muted">Muharrir nimani tuzatdi</summary><ul>
        <?php foreach ($v['changes'] as $c): ?><li><?= e($c) ?></li><?php endforeach; ?></ul></details><?php endif; ?>
      <form method="post" class="rate"><?= csrf_field() ?>
        <input type="hidden" name="action" value="rate"><input type="hidden" name="variant_id" value="<?= (int) $v['db_id'] ?>">
        <input type="hidden" name="return" value="<?= e($return) ?>">
        <select name="rating" aria-label="Baho"><?= options([5 => "5 — a'lo", 4 => '4 — yaxshi', 3 => "3 — o'rtacha", 2 => '2 — kuchsiz', 1 => '1 — yaroqsiz'], (string) ($saved['rating'] ?? '')) ?></select>
        <input type="text" name="feedback" placeholder="Nima yoqdi / yoqmadi? O'qituvchi agent shundan qoida chiqaradi" value="<?= e($saved['feedback'] ?? '') ?>">
        <button><?= isset($saved['rating']) ? 'Yangilash' : 'Baholash' ?></button>
      </form>
    </div>
    <?php
}
