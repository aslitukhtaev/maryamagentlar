<?php
declare(strict_types=1);

use Maryam\Agents\GraphicDesigner;
use Maryam\BrandAssets;
use Maryam\Marketing;
use Maryam\PostRenderer;

$colors = PostRenderer::colors($store);
$style = GraphicDesigner::style($store);
$dir = BrandAssets::dir();
$refs = BrandAssets::refs();
$autoColors = (string) $store->meta('brand_colors_auto') !== '';
$current = isset($_GET['show']) ? $store->resultById((int) $_GET['show']) : null;
$designs = $store->recentDesigns(18);
$logos = ['logo-white' => ['Oq logo', 'Har rasmning tepasida, foto ustida turadi'], 'logo' => ['Rangli logo', 'Och fonlar uchun']];
$v = static fn () => substr(md5(@filemtime("$dir/logo.png") . '|' . @filemtime("$dir/logo-white.png") . json_encode($colors) . json_encode($style)), 0, 8);
$formats = ['post' => ['Post', 'Bitta rasm 1080×1350'], 'karusel' => ['Karusel', '5-8 slayd, har biri alohida rasm'], 'reels' => ['Stories / Reels', 'Muqova 1080×1920']];
$fmt = (string) ($old['format'] ?? $_GET['format'] ?? 'post');
?>
<?php page_header('🎨', 'Dizayner', "Brendingiz uslubida tayyor rasm chizadi: post, karusel yoki stories. Uslubni siz yuklagan grid namunalaridan o'rganadi."); ?>

<?php if (!function_exists('imagecreatetruecolor')): ?>
  <div class="flash error">Serverda rasm moduli hali o'rnatilmagan — server bir necha daqiqada o'zi o'rnatadi (avtomatik yangilanish). Keyin sahifani yangilang.</div>
<?php endif; ?>
<?php if (!$refs): ?>
  <div class="flash">Avval pastdagi <b>"Brend uslubi"</b> bo'limiga logo va Instagram gridingiz skrinshotlarini yuklang — dizayner ranglar va uslubni shulardan oladi.</div>
<?php endif; ?>

<form method="post" class="card" <?= busy_attr() ?>><?= csrf_field() ?>
  <input type="hidden" name="action" value="design_free">
  <label>Nima chizamiz?</label>
  <div class="choice-grid three">
    <?php foreach ($formats as $k => [$label, $hint]): ?>
      <label class="choice"><input type="radio" name="format" value="<?= $k ?>" <?= $fmt === $k ? 'checked' : '' ?>>
        <span><b><?= e($label) ?></b><small><?= e($hint) ?></small></span></label>
    <?php endforeach; ?>
  </div>
  <label>Matn yoki g'oya</label>
  <textarea name="text" required rows="4" style="min-height:110px" placeholder="Masalan: Vyetnam, Fukuok — 820$ dan, 12 oktabr, 7 kun, nonushta&#10;Karusel uchun: Vyetnamga borishdan oldin bilish kerak bo'lgan 5 narsa (yoki 1-slayd: ..., 2-slayd: ...)"><?= e($old['text'] ?? '') ?></textarea>
  <div class="actions"><button type="submit" class="primary-btn">Chizish</button><span class="busy muted" hidden>Dizayner ishlamoqda (30-90 soniya)…</span></div>
</form>

<?php if ($current): ?>
  <h2 id="natija">Natija<?= !empty($current['slides']) ? ' — karusel, ' . count($current['slides']) . ' ta slayd' : '' ?></h2>
  <div class="card"><?php design_view($current); ?></div>
<?php endif; ?>

<?php if ($designs): ?>
<h2>Oxirgi dizaynlar</h2>
<div class="card" style="padding:10px">
  <div class="ig-grid wide">
    <?php foreach ($designs as $d): ?>
      <a href="<?= e(url(['p' => 'brend', 'show' => $d['id']])) ?>#natija" title="<?= e($d['topic']) ?>">
        <img src="<?= e(url(['d' => $d['id'], 's' => 0])) ?>" alt="<?= e($d['topic']) ?>" loading="lazy">
        <?php if ($d['slides']): ?><em class="badge-slides">▦ <?= $d['slides'] ?></em><?php endif; ?>
        <span><?= e($d['topic']) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<h2>Brend uslubi</h2>
<div class="grid2">
  <div class="card">
    <h3>Logo</h3>
    <p class="small muted">PNG, shaffof fonli bo'lsa eng yaxshi. <b>Oq logo</b> rasmlarning tepasida (foto ustida) ishlatiladi.</p>
    <?php foreach ($logos as $key => [$label, $hint]): $has = is_file("$dir/$key.png"); ?>
      <form method="post" enctype="multipart/form-data" class="upload-row" <?= busy_attr() ?>><?= csrf_field() ?>
        <input type="hidden" name="action" value="brand_upload">
        <div class="logo-prev <?= $key === 'logo-white' ? 'dark' : '' ?>">
          <?php if ($has): ?><img src="<?= e(url(['asset' => $key, 'v' => $v()])) ?>" alt="<?= e($label) ?>">
          <?php else: ?><span class="small" style="color:<?= $key === 'logo-white' ? '#cfe0da' : 'var(--muted)' ?>">Yuklanmagan</span><?php endif; ?>
        </div>
        <div>
          <b class="small"><?= e($label) ?></b><br><span class="small muted"><?= e($hint) ?></span><br>
          <label class="upload-btn"><?= $has ? 'Almashtirish' : 'Yuklash' ?>
            <input type="file" name="<?= $key === 'logo-white' ? 'logo_white' : 'logo' ?>" accept="image/*" hidden onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()"></label>
          <button type="submit" hidden></button><span class="busy muted small" hidden>Yuklanmoqda…</span>
        </div>
      </form>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <h3>Grid namunalari</h3>
    <p class="small muted">Instagram gridingiz skrinshoti, yoqqan postlar yoki dizayneringiz ishlari. Ranglar va uslub shulardan
      <b>avtomatik</b> aniqlanadi; dizayner eng yangi 4 tasini ko'rib ishlaydi (<?= BrandAssets::MAX_REFS ?> tagacha).</p>
    <form method="post" enctype="multipart/form-data" <?= busy_attr() ?>><?= csrf_field() ?>
      <input type="hidden" name="action" value="ref_upload">
      <label class="upload-btn big">📷 Rasmlarni tanlash
        <input type="file" name="refs[]" accept="image/*" multiple hidden onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()"></label>
      <button type="submit" hidden></button><span class="busy muted small" hidden>Yuklanmoqda va uslub tahlil qilinmoqda…</span>
    </form>
    <?php if ($refs): ?>
      <div class="ref-grid">
        <?php foreach ($refs as $i => $name): ?>
          <div class="ref">
            <a href="<?= e(url(['asset' => 'ref', 'n' => $name])) ?>" target="_blank"><img src="<?= e(url(['asset' => 'ref', 'n' => $name, 'sm' => 1])) ?>" alt="Namuna" loading="lazy"></a>
            <?php if ($i < 4): ?><span class="ref-tag">agent ko'radi</span><?php endif; ?>
            <form method="post" onsubmit="return confirm('Namuna o\'chirilsinmi?')"><?= csrf_field() ?>
              <input type="hidden" name="action" value="ref_delete"><input type="hidden" name="name" value="<?= e($name) ?>">
              <button class="ref-del" title="O'chirish">×</button></form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <h3>Aniqlangan uslub <span class="muted small">— namunalardan avtomatik</span></h3>
  <p class="small"><?php foreach (['primary' => 'Asosiy', 'accent' => "Urg'u", 'dark' => "To'q"] as $k => $label): ?>
    <span class="swatch big" style="background:<?= e($colors[$k]) ?>"></span><?= e($label) ?> &nbsp;
  <?php endforeach; ?>
  <?php if (!$autoColors): ?><span class="muted">(namuna yuklanmagan — standart ranglar)</span><?php endif; ?></p>
  <?php if ($style): ?>
    <p class="small"><b>Fon:</b> <?= !empty($style['photo_background']) ? 'haqiqiy foto' : 'rangli fon' ?> ·
      <b>Sarlavha:</b> <?= ($style['uppercase_titles'] ?? true) !== false ? 'KATTA HARFLAR' : 'oddiy' ?> ·
      <b>Pastki lenta:</b> <?= !empty($style['bottom_strip']) ? 'bor' : "yo'q (logo tepada)" ?> ·
      <b>Matn:</b> <?= e($style['text_density'] ?: '—') ?> · <b>Kayfiyat:</b> <?= e($style['mood'] ?: '—') ?></p>
    <?php if ($style['notes'] !== ''): ?><p class="small muted"><?= e($style['notes']) ?></p><?php endif; ?>
  <?php endif; ?>
  <?php if ($refs): ?>
    <form method="post" <?= busy_attr() ?>><?= csrf_field() ?><input type="hidden" name="action" value="style_refresh">
      <button type="submit" class="ghost">Qayta tahlil qilish</button><span class="busy muted small" hidden>Tahlil qilinmoqda…</span></form>
  <?php endif; ?>
</div>

<details class="card more-card"><summary><b>Sahifangiz qanday ko'rinadi</b> <span class="muted small">— tayyor rasmlar gridda (namuna)</span></summary>
  <div class="ig-grid">
    <?php foreach (PostRenderer::gridSamples(Marketing::products()) as $i => [$layout, , $note]): ?>
      <a href="<?= e(url(['render' => 'grid', 'i' => $i, 'dl' => 1])) ?>" title="<?= e($note) ?> — yuklab olish">
        <img src="<?= e(url(['render' => 'grid', 'i' => $i, 'v' => $v()])) ?>" alt="<?= e($note) ?>" loading="lazy">
        <span><?= e($note) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</details>

<details class="card more-card small"><summary><b>Dizayneringiz uchun qo'llanma</b> <span class="muted">— o'lcham, shrift, ranglar</span></summary>
  <table>
    <tr><td><b>O'lcham</b></td><td>Post va karusel: 1080×1350 (4:5). Reels/Stories muqovasi: 1080×1920. Chetdan bo'sh joy: 72 px.</td></tr>
    <tr><td><b>Shrift</b></td><td>Sarlavha va narx — Oswald Bold, KATTA HARFLAR (tor, qalin). Matn — Montserrat SemiBold/Medium. Ikkalasi Google Fonts'da bepul.</td></tr>
    <tr><td><b>Ranglar</b></td><td><?php foreach ($colors as $hex): ?><span class="swatch" style="background:<?= e($hex) ?>"></span><code><?= e($hex) ?></code> &nbsp;<?php endforeach; ?></td></tr>
    <tr><td><b>Kompozitsiya</b></td><td>Foto butun rasm bo'ylab, pasti qoraytirilgan; sarlavha pastda chapda, narx urg'u rangli plashkada. Tepada markazda kichik oq logo; pastda — sotuv postlarida telefon, qolganlarida Instagram manzili.</td></tr>
    <tr><td><b>Karusel</b></td><td>1-slayd — hook va "Surib ko'ring →"; o'rtada raqamli maslahatlar; oxirgi — CTA tugmasi. O'ng yuqorida "2/7". Barcha slaydlarda bitta foto (qoraytirilgan) — karusel yaxlit ko'rinadi.</td></tr>
  </table>
</details>
