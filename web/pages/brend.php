<?php
declare(strict_types=1);

use Maryam\Marketing;
use Maryam\PostRenderer;

$colors = PostRenderer::colors($store);
$samples = PostRenderer::gridSamples(Marketing::products());
$dir = ROOT . PostRenderer::BRAND_DIR;
$logos = ['logo-white' => ['Oq logo', 'Rasmlardagi yashil lentada shu turadi'], 'logo' => ['Rangli logo', 'Och fonlar uchun']];
$v = static fn () => substr(md5(@filemtime("$dir/logo.png") . '|' . @filemtime("$dir/logo-white.png") . json_encode($colors)), 0, 8);
?>
<?php page_header('🎨', 'Dizayner', "Har bir postga brend uslubida tayyor rasm chizadi. Logo va dizayn namunalaringizni yuklang — rasmlar sizning uslubingizda chiqadi."); ?>

<?php $designs = $store->recentDesigns(); if ($designs): ?>
<h2>Oxirgi tayyor rasmlar</h2>
<div class="card" style="padding:10px">
  <div class="ig-grid wide">
    <?php foreach ($designs as $d): ?>
      <a href="<?= e(url(['img' => $d['brief_id'], 'v' => $d['variant_id'], 'card' => 1])) ?>" download="post-<?= $d['variant_id'] ?>.png" title="<?= e($d['topic']) ?> — yuklab olish">
        <img src="<?= e(url(['img' => $d['brief_id'], 'v' => $d['variant_id'], 'card' => 1])) ?>" alt="<?= e($d['topic']) ?>" loading="lazy">
        <span><?= e($d['topic']) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php else: ?>
<div class="card small muted">Hali tayyor rasm yo'q. Copywriter natijasi ostidagi <b>"Dizayn tayyorlash"</b> tugmasini bosing (yoki botda 🎨 Dizayn) — rasm shu yerda paydo bo'ladi.</div>
<?php endif; ?>

<div class="grid2">
  <div class="card">
    <h3>1. Logo</h3>
    <p class="small muted">PNG, shaffof fonli bo'lsa eng yaxshi. <b>Oq logo</b> yashil lenta va fotolar ustida ishlatiladi.</p>
    <?php foreach ($logos as $key => [$label, $hint]): $has = is_file("$dir/$key.png"); ?>
      <form method="post" enctype="multipart/form-data" class="upload-row" <?= busy_attr() ?>><?= csrf_field() ?>
        <input type="hidden" name="action" value="brand_upload">
        <div class="logo-prev <?= $key === 'logo-white' ? 'dark' : '' ?>">
          <?php if ($has): ?><img src="<?= e(url(['asset' => $key, 'v' => $v()])) ?>" alt="<?= e($label) ?>">
          <?php else: ?><span class="muted small"><?= $key === 'logo-white' ? '<span style="color:#cfe0da">Yuklanmagan</span>' : 'Yuklanmagan' ?></span><?php endif; ?>
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
    <h3>2. Dizayn namunalari (grid)</h3>
    <p class="small muted">Instagram gridingiz skrinshoti, yoqqan postlar yoki dizayneringiz ishlari. Dizayner agent eng yangi 4 tasiga
      qarab uslub, kompozitsiya va ranglarni moslaydi. Bir nechtasini birdan tanlash mumkin (<?= Maryam\BrandAssets::MAX_REFS ?> tagacha).</p>
    <form method="post" enctype="multipart/form-data" <?= busy_attr() ?>><?= csrf_field() ?>
      <input type="hidden" name="action" value="ref_upload">
      <label class="upload-btn big">📷 Rasmlarni tanlash
        <input type="file" name="refs[]" accept="image/*" multiple hidden onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()"></label>
      <button type="submit" hidden></button><span class="busy muted small" hidden>Yuklanmoqda…</span>
    </form>
    <?php $refs = Maryam\BrandAssets::refs(); if ($refs): ?>
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
    <?php else: ?>
      <p class="small muted" style="margin-top:10px">Hali namuna yo'q.</p>
    <?php endif; ?>
  </div>
</div>

<h2>Sahifangiz qanday ko'rinadi</h2>
<?php if (!function_exists('imagecreatetruecolor')): ?>
  <div class="flash error">Serverda rasm chizish moduli (php-gd) o'rnatilmagan. Serverda o'rnatish buyrug'ini bir marta qayta
    ishga tushiring (SERVER_UZ.md) — keyin grid va tayyor rasmlar ishlaydi.</div>
<?php endif; ?>
<p class="small muted">Agentlar chiqaradigan tayyor rasmlar sahifangizda shunday ko'rinadi: sotuv, ishonch va qamrov postlari almashinadi,
  hammasi bir xil ranglar va pastki lentada (logongiz bilan). Rasmni bosing — to'liq o'lchamda yuklab olasiz.</p>
<div class="card" style="padding:10px">
  <div class="ig-grid">
    <?php foreach ($samples as $i => [$layout, , $note]): ?>
      <a href="<?= e(url(['render' => 'grid', 'i' => $i, 'dl' => 1])) ?>" title="<?= e($note) ?> — yuklab olish">
        <img src="<?= e(url(['render' => 'grid', 'i' => $i, 'v' => $v()])) ?>" alt="<?= e($note) ?>" loading="lazy">
        <span><?= e($note) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<details class="card more-card"><summary><b>Ranglar</b> <span class="muted small">— logotipingiz ranglariga moslash</span></summary>
<form method="post"><?= csrf_field() ?>
  <input type="hidden" name="action" value="brand_colors">
  <p class="small muted">Logotipdagi ranglar. O'zgartirsangiz, grid va barcha yangi rasmlar darhol yangilanadi.</p>
  <div class="row">
  <?php foreach (['primary' => 'Asosiy (fon, lenta)', 'accent' => 'Urg\'u (narx, belgilar)', 'dark' => "To'q (gradient)"] as $k => $label): ?>
    <label class="color-row"><input type="color" name="<?= $k ?>" value="<?= e($colors[$k]) ?>"> <?= e($label) ?></label>
  <?php endforeach; ?>
  </div>
  <div class="actions"><button type="submit">Saqlash</button></div>
</form>
</details>

<details class="card more-card small"><summary><b>Dizayneringiz uchun qo'llanma</b> <span class="muted">— o'lcham, shrift, ranglar, qoidalar</span></summary>
  <table>
    <tr><td><b>O'lcham</b></td><td>Post va karusel: 1080×1350 (4:5). Reels/Stories muqovasi: 1080×1920. Chetdan bo'sh joy: 72 px.</td></tr>
    <tr><td><b>Shrift</b></td><td>Montserrat — sarlavha ExtraBold, matn SemiBold/Medium (Google Fonts, bepul). Boshqa shrift ishlatilmaydi.</td></tr>
    <tr><td><b>Ranglar</b></td><td>
      <?php foreach ($colors as $k => $hex): ?><span class="swatch" style="background:<?= e($hex) ?>"></span><code><?= e($hex) ?></code> &nbsp;<?php endforeach; ?>
    </td></tr>
    <tr><td><b>Pastki lenta</b></td><td>Har rasmda: to'q yashil lenta (150 px), tepasida ingichka oltin chiziq; chapda telefon, o'ngda oq logo.</td></tr>
    <tr><td><b>Narx</b></td><td>Oltin plashkada, katta: "820$ dan". Narx doim sana va "narxga kiradi" bilan birga.</td></tr>
    <tr><td><b>Sarlavha</b></td><td>2-5 so'z, oq, qalin. Baqiruvchi clickbait yo'q. Matn rasmning 20% idan oshmasin (Meta reklamasi uchun).</td></tr>
    <tr><td><b>Foto</b></td><td>Manzilning yorqin, haqiqiy fotosi; tepasi va pastki qismi qoraytiriladi (matn o'qilishi uchun). Odamlar yuzi yaqindan emas.</td></tr>
  </table>
  <p class="muted" style="margin-bottom:0">Shrift fayllari: <code>resources/fonts/</code> (SIL Open Font License).</p>
</details>
