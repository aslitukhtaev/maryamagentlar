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

/** Dizayn natijasining rasm fayllari tartib bilan: karusel slaydlari, AI variantlari yoki bitta shablon rasmi. */
function design_files(array $design): array
{
    if (!empty($design['slides'])) {
        return array_values($design['slides']);
    }
    if (!empty($design['variants'])) {
        return array_values(array_column($design['variants'], 'path'));
    }
    return !empty($design['card_path']) ? [$design['card_path']] : [];
}

/** Yozuv tekshiruvi belgisi. */
function check_badge(?array $check): string
{
    if ($check === null) {
        return '';
    }
    return $check['ok']
        ? '<span class="chk ok">✓ Yozuv to\'g\'ri</span>'
        : '<span class="chk bad" title="' . e($check['found'] ?? '') . '">⚠ ' . e($check['issues'] ?: 'Yozuvda xato bo\'lishi mumkin') . '</span>';
}

/** "Tuzatish" formasi: AI rasmni aytilgancha o'zgartiradi. */
function fix_form(int $id, int $index, ?array $check, string $return): string
{
    $open = $check && !$check['ok'] ? ' open' : '';
    return '<details class="fix"' . $open . '><summary>✏️ Tuzatish</summary><form method="post" ' . busy_attr() . '>' . csrf_field()
        . '<input type="hidden" name="action" value="design_fix"><input type="hidden" name="id" value="' . $id . '">'
        . '<input type="hidden" name="i" value="' . $index . '"><input type="hidden" name="return" value="' . e($return) . '">'
        . '<textarea name="instruction" rows="2" placeholder="Masalan: narxni kattaroq qil · fonni kechki qil · ISTANBUL so\'zini to\'g\'rila">'
        . e($check && !$check['ok'] ? ($check['issues'] ? 'Yozuvni to\'g\'rila: ' . $check['issues'] : '') : '') . '</textarea>'
        . '<button type="submit" class="ghost">Tuzatish</button><span class="busy muted small" hidden>AI tuzatmoqda (20-60 soniya)…</span></form></details>';
}

/** Dizayner natijasi: 4 ta AI variant (tanlash/tuzatish), karusel slaydlari yoki zaxira shablon rasmi. */
function design_view(array $design): void
{
    $id = (int) $design['id'];
    $files = design_files($design);
    $return = '?' . (string) ($_SERVER['QUERY_STRING'] ?? '');
    $carousel = !empty($design['slides']);
    $variants = $design['variants'] ?? [];
    $chosen = isset($design['chosen']) ? (int) $design['chosen'] : null;
    $size = ($design['format'] ?? '') === 'reels' ? '1080×1920' : '1080×1350';
    if (($design['engine'] ?? '') === 'template' && !empty($design['ai_error'])) {
        echo '<p class="warn">⚠ AI rasm chiza olmadi, shuning uchun oddiy shablon chizildi. Sabab: ' . e(mb_strimwidth((string) $design['ai_error'], 0, 160, '…'))
           . '<br>Server administratori Vertex AI\'da rasm modeliga ruxsatni tekshirsin.</p>';
    }
    if (!$files) {
        echo '<p class="warn">Tayyor rasm chizilmadi.</p>';
    } elseif ($variants && !$carousel) {
        echo '<p class="small muted">' . count($variants) . " ta variant. Eng yoqqanini <b>Tanlang</b> — keyin Telegramga yuboring. Kichik narsani o'zgartirish uchun <b>Tuzatish</b>.</p>";
        echo '<div class="variants' . (($design['format'] ?? '') === 'reels' ? ' tall' : '') . '">';
        foreach ($variants as $i => $v) {
            $isChosen = $chosen === $i;
            echo '<div class="vcard' . ($isChosen ? ' chosen' : '') . '">'
               . '<a href="' . e(url(['d' => $id, 's' => $i])) . '" target="_blank"><img src="' . e(url(['d' => $id, 's' => $i])) . '" alt="' . e($v['concept']) . '" loading="lazy"></a>'
               . '<div class="vmeta"><b>' . ($i + 1) . '. ' . e($v['concept']) . '</b>' . check_badge($v['check'] ?? null) . '</div><div class="vact">';
            echo $isChosen
                ? '<span class="chk ok">★ Tanlangan</span>'
                : '<form method="post">' . csrf_field() . '<input type="hidden" name="action" value="design_pick"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="i" value="' . $i . '"><input type="hidden" name="return" value="' . e($return) . '"><button class="ghost">✅ Tanlash</button></form>';
            echo '<a class="ghost-link" href="' . e(url(['d' => $id, 's' => $i, 'dl' => 1])) . '">⬇ Yuklab olish</a></div>'
               . fix_form($id, $i, $v['check'] ?? null, $return) . '</div>';
        }
        echo '</div>';
    } else {
        echo '<div class="slides' . ($carousel ? ' carousel' : '') . '">';
        foreach ($files as $i => $f) {
            $meta = $design['slide_meta'][$i] ?? null;
            echo '<div class="slide"><a href="' . e(url(['d' => $id, 's' => $i, 'dl' => 1])) . '" title="Yuklab olish"><img src="' . e(url(['d' => $id, 's' => $i])) . '" alt="' . ($carousel ? ($i + 1) . '-slayd' : 'Tayyor rasm') . '" loading="lazy"></a>';
            if ($meta) {
                echo '<div class="vmeta"><b>' . ($i + 1) . '-slayd</b>' . check_badge($meta['check'] ?? null) . '</div>' . fix_form($id, $i, $meta['check'] ?? null, $return);
            }
            echo '</div>';
        }
        echo '</div>';
    }
    if ($files) {
        $what = $carousel ? count($files) . ' ta slaydni' : ($variants ? ($chosen !== null ? 'tanlanganini' : 'hammasini') : 'rasmni');
        echo '<div class="actions">';
        echo '<form method="post" ' . busy_attr() . '>' . csrf_field() . '<input type="hidden" name="action" value="send_tg">'
           . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="return" value="' . e($return) . '">'
           . '<button type="submit" class="primary-btn">📲 ' . ucfirst($what) . ' Telegramga yuborish</button><span class="busy muted small" hidden>Yuborilmoqda…</span></form>';
        if ($carousel && !empty($design['zip_path'])) {
            echo '<a class="upload-btn" href="' . e(url(['d' => $id, 'zip' => 1])) . '">⬇ ZIP (' . count($files) . ' ta slayd)</a>';
        } elseif (!$variants) {
            echo '<a class="upload-btn" href="' . e(url(['d' => $id, 's' => 0, 'dl' => 1])) . "\">⬇ Yuklab olish ($size)</a>";
        }
        echo '</div>';
        if ($carousel) {
            echo '<p class="small muted">Telegramga slaydlar tartib bilan albom bo\'lib keladi — Instagram\'ga shu tartibda joylang.</p>';
        }
    }
    if (!empty($design['layout'])) {
        echo '<details class="small" style="margin-top:8px"><summary class="muted">Art-direktor konseptlari</summary><ul>'
           . implode('', array_map(static fn ($l) => '<li>' . e($l) . '</li>', (array) $design['layout'])) . '</ul></details>';
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
    global $store;
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
          <?php if ($store->hasHouseExample(trim($text))): ?><button class="ghost" disabled>★ Oltin namunada</button>
          <?php else: ?><button class="ghost" title="Agentlar shu uslubda yozishni o'rganadi">Oltin namuna qilish</button><?php endif; ?></form>
        <?php if ($briefId): ?>
        <form method="post" <?= busy_attr() ?>><?= csrf_field() ?><input type="hidden" name="action" value="design">
          <input type="hidden" name="return" value="<?= e($return) ?>">
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

/** "2026-W39" → "21–27 sentabr" (egasi uchun tushunarli hafta nomi). */
function week_label(string $week): string
{
    if (!preg_match('/^(\d{4})-W(\d{2})$/', $week, $m)) {
        return $week;
    }
    $months = ['yanvar', 'fevral', 'mart', 'aprel', 'may', 'iyun', 'iyul', 'avgust', 'sentabr', 'oktabr', 'noyabr', 'dekabr'];
    $from = (new DateTimeImmutable())->setISODate((int) $m[1], (int) $m[2]);
    $to = $from->modify('+6 days');
    $f = static fn (DateTimeImmutable $d) => $months[(int) $d->format('n') - 1];
    return $from->format('n') === $to->format('n')
        ? $from->format('j') . '–' . $to->format('j') . ' ' . $f($to)
        : $from->format('j') . ' ' . $f($from) . ' – ' . $to->format('j') . ' ' . $f($to);
}

/** Yuklangan rasm fayllari (input name="$field[]"), 10 MB gacha. @return string[] vaqtinchalik yo'llar */
function uploaded_images(string $field): array
{
    $f = $_FILES[$field] ?? null;
    $out = [];
    foreach ((array) ($f['tmp_name'] ?? []) as $i => $tmp) {
        if ((($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) && ($f['size'][$i] ?? 0) <= 10 * 1024 * 1024 && is_uploaded_file($tmp)) {
            $out[] = $tmp;
        }
    }
    return $out;
}
