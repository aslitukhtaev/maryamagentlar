<?php
declare(strict_types=1);

use Maryam\Agents\GraphicDesigner;
use Maryam\BrandAssets;
use Maryam\PostRenderer;

$colors = PostRenderer::colors($store);
$style = GraphicDesigner::style($store);
$dir = BrandAssets::dir();
$refs = BrandAssets::refs();
$photos = BrandAssets::photos();
$autoColors = (string) $store->meta('brand_colors_auto') !== '';
$current = isset($_GET['show']) ? $store->resultById((int) $_GET['show']) : null;
$designs = $store->recentDesigns(18);
$logos = ['logo-white' => ['Oq logo', 'Har rasmning tepasida turadi'], 'logo' => ['Rangli logo', 'Och fonlar uchun']];
$v = static fn () => substr(md5(@filemtime("$dir/logo.png") . '|' . @filemtime("$dir/logo-white.png")), 0, 8);
$formats = ['post' => ['Post', '4 ta variant, 1080×1350'], 'karusel' => ['Karusel', '4-7 slayd, bir uslubda'], 'reels' => ['Stories / Reels', '4 ta variant, 1080×1920']];
$fmt = (string) ($old['format'] ?? $_GET['format'] ?? 'post');
$picked = array_map('strval', (array) ($old['photo_pick'] ?? []));
?>
<?php page_header('🎨', 'Dizayner', "AI gridingiz uslubida tayyor post chizadi — yozuvlari bilan. Har safar 4 xil variant: eng yoqqanini tanlaysiz yoki bir so'z bilan tuzattirasiz."); ?>

<?php if (!function_exists('imagecreatetruecolor')): ?>
  <div class="flash error">Serverda rasm moduli hali o'rnatilmagan — server bir necha daqiqada o'zi o'rnatadi (avtomatik yangilanish). Keyin sahifani yangilang.</div>
<?php endif; ?>
<?php if (!$refs): ?>
  <div class="flash">Avval pastdagi <b>"Brend uslubi"</b> bo'limiga Instagram gridingiz skrinshotlarini yuklang — AI uslubni shulardan o'rganadi.</div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card" <?= busy_attr() ?>><?= csrf_field() ?>
  <input type="hidden" name="action" value="design_free">
  <label>Nima chizamiz?</label>
  <div class="choice-grid three">
    <?php foreach ($formats as $k => [$label, $hint]): ?>
      <label class="choice"><input type="radio" name="format" value="<?= $k ?>" <?= $fmt === $k ? 'checked' : '' ?>>
        <span><b><?= e($label) ?></b><small><?= e($hint) ?></small></span></label>
    <?php endforeach; ?>
  </div>
  <label>Matn yoki g'oya</label>
  <textarea name="text" required rows="4" style="min-height:110px" placeholder="Masalan: Vyetnam, Fukuok — 820$ dan, 12 oktabr, 7 kun, nonushta&#10;Karusel uchun: 1-slayd: ..., 2-slayd: ... (yoki shunchaki mavzu)"><?= e($old['text'] ?? '') ?></textarea>

  <label>Postda odam bo'lsinmi? <span class="muted">(ixtiyoriy — 3 tagacha foto)</span></label>
  <p class="small muted" style="margin:0 0 6px">Siz, jamoa yoki mijoz fotosi — AI shu odamni dizaynga qo'yadi, yuzi o'zgarmaydi. Gridingizdagi eng kuchli postlar shunday.</p>
  <?php if ($photos): ?>
    <div class="photo-pick">
      <?php foreach (array_slice($photos, 0, 12) as $name): ?>
        <label><input type="checkbox" name="photo_pick[]" value="<?= e($name) ?>" <?= in_array($name, $picked, true) ? 'checked' : '' ?> onchange="limitPhotos(this)">
          <img src="<?= e(url(['asset' => 'photo', 'n' => $name, 'sm' => 1])) ?>" alt="Foto" loading="lazy"><i>✓</i></label>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <label class="upload-btn">📷 <?= $photos ? 'Yangi foto qo\'shish' : 'Foto tanlash' ?>
    <input type="file" name="photos[]" accept="image/*" multiple hidden onchange="this.parentNode.lastChild.textContent = this.files.length ? ' — ' + this.files.length + ' ta tanlandi' : ''"><span></span></label>

  <div class="actions"><button type="submit" class="primary-btn">🎨 Chizish</button><span class="busy muted" hidden>AI chizmoqda — 1-2 daqiqa. Sahifani yopmang…</span></div>
</form>
<script>
function limitPhotos(el) {
  const on = el.closest('.photo-pick').querySelectorAll('input:checked');
  if (on.length > 3) { el.checked = false; alert('Ko\'pi bilan 3 ta foto.'); }
}
</script>

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

<h2 id="fotolar">Jamoa fotolari</h2>
<div class="card">
  <p class="small muted">Siz, menejerlar, mijozlar (roziligi bilan). Yuzi aniq ko'ringan, yorug' foto eng yaxshi natija beradi.
    Dizayn buyurtmasida belgilab ishlatasiz (<?= BrandAssets::MAX_PHOTOS ?> tagacha).</p>
  <form method="post" enctype="multipart/form-data" <?= busy_attr() ?>><?= csrf_field() ?>
    <input type="hidden" name="action" value="photo_upload">
    <label class="upload-btn big">🧑 Foto yuklash
      <input type="file" name="photos[]" accept="image/*" multiple hidden onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()"></label>
    <button type="submit" hidden></button><span class="busy muted small" hidden>Yuklanmoqda…</span>
  </form>
  <?php if ($photos): ?>
    <div class="ref-grid">
      <?php foreach ($photos as $name): ?>
        <div class="ref">
          <a href="<?= e(url(['asset' => 'photo', 'n' => $name])) ?>" target="_blank"><img src="<?= e(url(['asset' => 'photo', 'n' => $name, 'sm' => 1])) ?>" alt="Foto" loading="lazy"></a>
          <form method="post" onsubmit="return confirm('Foto o\'chirilsinmi?')"><?= csrf_field() ?>
            <input type="hidden" name="action" value="photo_delete"><input type="hidden" name="name" value="<?= e($name) ?>">
            <button class="ref-del" title="O'chirish">×</button></form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<h2>Brend uslubi</h2>
<div class="grid2">
  <div class="card">
    <h3>Grid namunalari</h3>
    <p class="small muted">Instagram gridingiz skrinshotlari yoki yoqqan postlar. AI har dizaynda eng yangi 3 tasini ko'rib, shu uslubda chizadi
      (<?= BrandAssets::MAX_REFS ?> tagacha). Eng yaxshi postlaringiz ko'ringan skrinshot — eng yaxshi natija.</p>
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
            <?php if ($i < 3): ?><span class="ref-tag">AI ko'radi</span><?php endif; ?>
            <form method="post" onsubmit="return confirm('Namuna o\'chirilsinmi?')"><?= csrf_field() ?>
              <input type="hidden" name="action" value="ref_delete"><input type="hidden" name="name" value="<?= e($name) ?>">
              <button class="ref-del" title="O'chirish">×</button></form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3>Logo</h3>
    <p class="small muted">PNG, shaffof fonli bo'lsa eng yaxshi. AI logoni buzib chizadi — shuning uchun haqiqiy logoni tizim o'zi tepaga qo'yadi.</p>
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
    <p class="small" style="margin-top:12px"><b>Ranglar</b> <span class="muted">(namunalardan avtomatik)</span><br>
      <?php foreach (['primary' => 'Asosiy', 'accent' => "Urg'u"] as $k => $label): ?>
        <span class="swatch big" style="background:<?= e($colors[$k]) ?>"></span><?= e($label) ?> &nbsp;
      <?php endforeach; ?>
      <?php if (!$autoColors): ?><span class="muted">(namuna yuklanmagan — standart)</span><?php endif; ?></p>
    <?php if ($style && ($style['notes'] ?? '') !== ''): ?><p class="small muted"><?= e($style['notes']) ?></p><?php endif; ?>
    <?php if ($refs): ?>
      <form method="post" <?= busy_attr() ?>><?= csrf_field() ?><input type="hidden" name="action" value="style_refresh">
        <button type="submit" class="ghost">Uslubni qayta tahlil qilish</button><span class="busy muted small" hidden>Tahlil qilinmoqda…</span></form>
    <?php endif; ?>
  </div>
</div>

<details class="card more-card small"><summary><b>Dizayner qanday ishlaydi</b></summary>
  <ol>
    <li><b>Art-direktor</b> (AI) g'oyangiz, grid namunalari va fotolarni ko'rib, rasmga yoziladigan qisqa matnni va 4 xil konseptni tanlaydi: odamli, manzara, kollaj, tipografik.</li>
    <li><b>Rasm modeli</b> 4 ta variantni bir vaqtda chizadi — yozuvlari bilan, gridingiz uslubida.</li>
    <li>Haqiqiy <b>logo</b> tepaga, <b>telefon</b> (narxli postda) yoki Instagram manzili pastga tizim tomonidan qo'yiladi.</li>
    <li><b>Tekshiruvchi</b> har rasmdagi yozuvni o'qiydi: xato bo'lsa "⚠" belgisi va tayyor tuzatish taklifi chiqadi.</li>
    <li>Siz <b>tanlaysiz</b> yoki <b>tuzattirasiz</b> ("narxni kattaroq qil", "fonni kechki qil") va Telegramga yuborasiz.</li>
  </ol>
  <p class="muted">Karuselda avval muqova chiziladi, qolgan slaydlar aynan shu uslubda. AI rasm chiza olmasa, oddiy shablon chiziladi (sababi ko'rsatiladi).</p>
</details>
