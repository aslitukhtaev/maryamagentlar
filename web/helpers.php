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

/** "Tuzatish" formasi: AI rasmni aytilgancha o'zgartiradi ($target: v — variant, s — karusel slaydi). */
function fix_form(int $id, int $index, ?array $check, string $return, string $target = 'v'): string
{
    $bad = $check && !$check['ok'];
    return '<details class="fix"' . ($bad ? ' open' : '') . '><summary>✏️ Tuzatish</summary><form method="post" ' . busy_attr() . '>' . csrf_field()
        . '<input type="hidden" name="action" value="design_fix"><input type="hidden" name="id" value="' . $id . '">'
        . '<input type="hidden" name="i" value="' . $index . '"><input type="hidden" name="target" value="' . $target . '"><input type="hidden" name="return" value="' . e($return) . '">'
        . '<textarea name="instruction" rows="2" placeholder="Masalan: narxni kattaroq qil · fonni yorqinroq qil · ISTANBUL so\'zini to\'g\'rila">'
        . e($bad && $check['issues'] ? 'Yozuvni to\'g\'rila: ' . $check['issues'] : '') . '</textarea>'
        . '<button type="submit" class="ghost">Tuzatish</button><span class="busy muted small" hidden>AI tuzatmoqda (20-60 soniya)…</span></form></details>';
}

/**
 * Dizayner natijasi — barcha formatlar uchun bir xil ko'rinish:
 * 4 ta variant (karuselda — muqova variantlari), karusel bo'lsa slaydlar lentasi, yuborish/yuklab olish.
 */
function design_view(array $design): void
{
    $id = (int) $design['id'];
    $return = here_url();
    $carousel = !empty($design['slides']);
    $variants = $design['variants'] ?? [];
    $chosen = isset($design['chosen']) ? (int) $design['chosen'] : null;
    $files = design_files($design);

    if (($design['engine'] ?? '') === 'failed' || (($design['engine'] ?? '') === 'template' && !empty($design['ai_error']))) {
        echo '<p class="warn">⚠ AI rasm chiza olmadi. ' . e(ai_error_text((string) $design['ai_error'])) . '</p>';
        echo '<form method="post" ' . busy_attr() . '>' . csrf_field() . '<input type="hidden" name="action" value="design_retry"><input type="hidden" name="id" value="' . $id . '">'
           . '<button type="submit" class="primary-btn">🔁 Qayta urinish</button><span class="busy muted small" hidden>Boshlanmoqda…</span></form>';
        if (($design['engine'] ?? '') === 'failed') {
            return;
        }
    }
    if ($variants) {
        echo '<p class="small muted">' . ($carousel
            ? count($variants) . " ta muqova varianti. Boshqasini tanlasangiz, qolgan slaydlar shu uslubda qayta chiziladi."
            : count($variants) . " ta variant. Eng yoqqanini <b>Tanlang</b> — keyin Telegramga yuboring. Kichik narsani o'zgartirish uchun <b>Tuzatish</b>.") . '</p>';
        echo '<div class="variants' . (($design['format'] ?? '') === 'reels' ? ' tall' : '') . '">';
        foreach ($variants as $i => $v) {
            $isChosen = $chosen === $i;
            $src = e(url(['d' => $id, 'v' => $i]));
            echo '<div class="vcard' . ($isChosen ? ' chosen' : '') . '"><a href="' . $src . '" target="_blank"><img src="' . $src . '" alt="' . e($v['concept']) . '" loading="lazy"></a>'
               . '<div class="vmeta"><b>' . ($i + 1) . '. ' . e($v['concept']) . '</b>' . check_badge($v['check'] ?? null) . '</div><div class="vact">';
            if ($isChosen) {
                echo '<span class="chk ok">★ ' . ($carousel ? 'Muqova' : 'Tanlangan') . '</span>';
            } else {
                echo '<form method="post" ' . ($carousel ? busy_attr() : '') . '>' . csrf_field() . '<input type="hidden" name="action" value="design_pick"><input type="hidden" name="id" value="' . $id . '">'
                   . '<input type="hidden" name="i" value="' . $i . '"><input type="hidden" name="return" value="' . e($return) . '">'
                   . '<button type="submit" class="ghost">' . ($carousel ? '✅ Shu muqova bilan' : '✅ Tanlash') . '</button>'
                   . ($carousel ? '<span class="busy muted small" hidden>Slaydlar qayta chizilmoqda (1-2 daqiqa)…</span>' : '') . '</form>';
            }
            echo '<a class="ghost-link" href="' . e(url(['d' => $id, 'v' => $i, 'dl' => 1])) . '">⬇ Yuklab olish</a></div>'
               . fix_form($id, $i, $v['check'] ?? null, $return, 'v') . '</div>';
        }
        echo '</div>';
    }
    if ($carousel) {
        echo '<h3 style="margin-top:16px">Karusel — ' . count($design['slides']) . ' ta slayd</h3><div class="slides carousel">';
        foreach ($design['slides'] as $i => $f) {
            $meta = $design['slide_meta'][$i] ?? null;
            if (!empty($meta['failed'])) {
                echo '<div class="slide"><div class="slide-missing">' . ($i + 1) . '-slayd hali tayyor emas</div></div>';
                continue;
            }
            echo '<div class="slide"><a href="' . e(url(['d' => $id, 's' => $i, 'dl' => 1])) . '" title="Yuklab olish"><img src="' . e(url(['d' => $id, 's' => $i])) . '" alt="' . ($i + 1) . '-slayd" loading="lazy"></a>'
               . '<div class="vmeta"><b>' . ($i + 1) . '-slayd</b>' . check_badge($meta['check'] ?? null) . '</div>'
               . ($meta && $i > 0 ? fix_form($id, $i, $meta['check'] ?? null, $return, 's') : ($i === 0 ? '<p class="small muted">Muqova — yuqorida tuzatiladi</p>' : '')) . '</div>';
        }
        echo '</div>';
        if (!empty($design['missing'])) {
            echo '<p class="small muted">' . count($design['missing']) . ' ta slayd hali tayyor emas. Tugmani bosing — tizim ularni navbat bilan, kerak bo\'lsa kutib chizadi.</p>'
               . '<form method="post" ' . busy_attr() . '>' . csrf_field() . '<input type="hidden" name="action" value="design_redraw"><input type="hidden" name="id" value="' . $id . '">'
               . '<input type="hidden" name="return" value="' . e($return) . '"><button type="submit" class="primary-btn">🎨 Qolgan slaydlarni chizish</button>'
               . '<span class="busy muted small" hidden>Chizilmoqda…</span></form>';
        }
    } elseif (!$variants && $files) { // eski natijalar (shablon)
        echo '<div class="slides"><div class="slide"><a href="' . e(url(['d' => $id, 's' => 0, 'dl' => 1])) . '"><img src="' . e(url(['d' => $id, 's' => 0])) . '" alt="Tayyor rasm"></a></div></div>';
    }
    if ($files) {
        $files = array_values(array_filter($files, static fn ($f) => is_string($f) && is_file($f)));
        $what = $carousel ? count($files) . ' ta slaydni' : ($variants ? ($chosen !== null ? 'tanlanganini' : 'hammasini') : 'rasmni');
        echo '<div class="actions"><form method="post" ' . busy_attr() . '>' . csrf_field() . '<input type="hidden" name="action" value="send_tg">'
           . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="return" value="' . e($return) . '">'
           . '<button type="submit" class="primary-btn">📲 ' . ucfirst($what) . ' Telegramga yuborish</button><span class="busy muted small" hidden>Yuborilmoqda…</span></form>';
        if ($carousel && !empty($design['zip_path'])) {
            echo '<a class="upload-btn" href="' . e(url(['d' => $id, 'zip' => 1])) . '">⬇ ZIP (' . count($files) . ' ta slayd)</a>';
        }
        echo '</div>';
    }
    if (!empty($design['layout'])) {
        $models = array_values(array_unique(array_filter(array_column(array_merge($variants, $design['slide_meta'] ?? []), 'model'))));
        echo '<details class="small" style="margin-top:8px"><summary class="muted">Art-direktor konseptlari</summary><ul>'
           . implode('', array_map(static fn ($l) => '<li>' . e($l) . '</li>', (array) $design['layout'])) . '</ul>'
           . ($models ? '<p class="muted">Rasm modeli: ' . e(implode(', ', $models)) . '</p>' : '') . '</details>';
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

/** Texnik AI xatosini egasiga tushunarli qilib yozadi. */
function ai_error_text(string $raw): string
{
    $tried = preg_match('/Urinilgan modellar: (.+)$/u', $raw, $m) ? ' (Urinilgan modellar: ' . $m[1] . ')' : '';
    return match (true) {
        str_contains($raw, '(429)') => "Google'ning rasm chizish limiti vaqtincha tugadi (bir daqiqada juda ko'p so'rov). 1-2 daqiqadan keyin qayta urining.",
        (bool) preg_match('/\((401|403)\)/', $raw) => "Google Cloud'da rasm modeliga ruxsat yo'q — administrator Vertex AI ruxsatini tekshirsin.",
        str_contains($raw, '(404)') => "Rasm modeli bu Google Cloud loyihasida topilmadi.",
        (bool) preg_match('/SAFETY|blockReason/i', $raw) => "AI bu mavzudagi rasmni xavfsizlik qoidasi sabab chizmadi — matnni biroz o'zgartirib ko'ring.",
        default => 'Sabab: ' . mb_strimwidth($raw, 0, 160, '…'),
    } . $tried;
}

/**
 * Uzoq ishni fonda boshlaydi va darhol qaytadi (sahifa qotmaydi). Bir vaqtda ko'pi bilan 3 ta web ishi.
 * $return — qaytiladigan sahifa (u yerda holat lentasi ko'rinadi, tugagach natijaga o'tiladi).
 */
function start_job(string $type, array $payload, string $return, string $label = ''): never
{
    global $store;
    $running = count(array_filter(Maryam\WebJobs::active($store), static fn ($id) => in_array(Maryam\WebJobs::status($store, $id)['status'], ['queued', 'running'], true)));
    if ($running >= 3) {
        throw new RuntimeException("Hozir $running ta ish bajarilmoqda — ulardan biri tugagach qayta bosing.");
    }
    $id = Maryam\WebJobs::start($store, $type, $payload, $label);
    [$path, $hash] = array_pad(explode('#', $return, 2), 2, '');
    $path = (string) preg_replace('/([?&])(job|jobdone)=\d+&?/', '$1', $path); // eski ish belgilari qolmasin
    $path = rtrim($path, '&?') ?: '?';
    redirect($path . (str_contains($path, '?') ? '&' : '?') . 'job=' . $id . ($hash !== '' ? '#' . $hash : ''));
}

/** Joriy sahifa manzili (fon ishi belgilari job/jobdone'siz) — formalarning "return" qiymati. */
function here_url(): string
{
    return '?' . http_build_query(array_diff_key($_GET, ['job' => 1, 'jobdone' => 1]));
}
