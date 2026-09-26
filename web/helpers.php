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

/** Namunalar o'zgarganda: ranglar (darhol, kod bilan) va uslub profili (AI) qayta hisoblanadi. */
function refresh_brand_style(object $ai, Maryam\Store $store): ?string
{
    Maryam\BrandAssets::refreshPalette($store);
    try {
        Maryam\Agents\GraphicDesigner::analyzeStyle($ai, $store);
        return null;
    } catch (Throwable $e) {
        return "AI uslubni tahlil qila olmadi (ranglar baribir aniqlandi): " . $e->getMessage();
    }
}

/** Bo'lim sarlavhasi: ikonka + nom + bir gapli tushuntirish. */
function page_header(string $icon, string $title, string $desc): void
{
    echo '<div class="page-head"><span class="page-ic">' . $icon . '</span><div><h1>' . e($title) . '</h1><p>' . e($desc) . '</p></div></div>';
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

/** Shu variant uchun dizayner natijasi (tayyor rasm/slaydlar) — bo'lsa. */
function design_block(int $briefId, int $variantId): void
{
    global $store;
    $design = $store->latestResultRow($briefId, 'designer', "variant_$variantId");
    if ($design) {
        echo '<div class="design">';
        design_view($design);
        echo '</div>';
    }
}

/** Dizayner natijasi: bitta rasm yoki karusel lentasi + yuklab olish tugmalari. */
function design_view(array $design): void
{
    $id = (int) $design['id'];
    $slides = $design['slides'] ?? [];
    $count = $slides ? count($slides) : (!empty($design['card_path']) ? 1 : 0);
    if (!$count) {
        echo '<p class="warn">Tayyor rasm chizilmadi.</p>';
    } else {
        echo '<div class="slides' . ($slides ? ' carousel' : '') . '">';
        for ($i = 0; $i < $count; $i++) {
            $src = e(url(['d' => $id, 's' => $i]));
            $dl = e(url(['d' => $id, 's' => $i, 'dl' => 1]));
            echo "<a href=\"$dl\" title=\"Yuklab olish\"><img src=\"$src\" alt=\"" . ($slides ? ($i + 1) . '-slayd' : 'Tayyor rasm') . "\" loading=\"lazy\">"
               . ($slides ? '<span>' . ($i + 1) . '/' . $count . '</span>' : '') . '</a>';
        }
        echo '</div><div class="actions">';
        if ($slides && !empty($design['zip_path'])) {
            echo '<a class="upload-btn" href="' . e(url(['d' => $id, 'zip' => 1])) . "\">⬇ Hammasini yuklab olish ($count ta slayd, ZIP)</a>";
        } elseif (!$slides) {
            echo '<a class="upload-btn" href="' . e(url(['d' => $id, 's' => 0, 'dl' => 1])) . '">⬇ Yuklab olish (1080×1350)</a>';
        }
        echo '</div>';
    }
    if (!empty($design['layout']) || !empty($design['image_prompt'])) {
        echo '<details class="small" style="margin-top:8px"><summary class="muted">Dizayner uchun tafsilotlar (maket, fon tavsifi)</summary>';
        if (!empty($design['layout'])) {
            echo '<ul>' . implode('', array_map(static fn ($l) => '<li>' . e($l) . '</li>', (array) $design['layout'])) . '</ul>';
        }
        if (!empty($design['image_prompt'])) {
            echo '<pre class="block">' . e($design['image_prompt']) . '</pre>';
        }
        echo '</details>';
    }
}

/** O'chirish formasi — tasdiq bilan. */
function delete_form(string $table, int $id, string $return, string $label = "O'chirish"): string
{
    return '<form method="post" onsubmit="return confirm(\'Rostdan o\\\'chirilsinmi?\')">' . csrf_field()
        . '<input type="hidden" name="action" value="delete"><input type="hidden" name="table" value="' . e($table) . '">'
        . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="return" value="' . e($return) . '">'
        . '<button class="danger">' . e($label) . '</button></form>';
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
        <button type="button" class="ghost" onclick="copyText(this.closest('.card').querySelector('.text'), this)">Nusxalash</button>
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
      <?php if ($briefId) { design_block($briefId, (int) $v['db_id']); } ?>
      <form method="post" class="rate"><?= csrf_field() ?>
        <input type="hidden" name="action" value="rate"><input type="hidden" name="variant_id" value="<?= (int) $v['db_id'] ?>">
        <input type="hidden" name="return" value="<?= e($return) ?>">
        <select name="rating" aria-label="Baho" required><?= options(['' => 'Baho tanlang', 5 => "5 — a'lo", 4 => '4 — yaxshi', 3 => "3 — o'rtacha", 2 => '2 — kuchsiz', 1 => '1 — yaroqsiz'], (string) ($saved['rating'] ?? '')) ?></select>
        <input type="text" name="feedback" placeholder="Nima yoqdi / yoqmadi? O'qituvchi agent shundan qoida chiqaradi" value="<?= e($saved['feedback'] ?? '') ?>">
        <button><?= isset($saved['rating']) ? 'Yangilash' : 'Baholash' ?></button>
      </form>
    </div>
    <?php
}
