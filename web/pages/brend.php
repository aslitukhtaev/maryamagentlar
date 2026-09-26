<?php
declare(strict_types=1);

use Maryam\Marketing;
use Maryam\PostRenderer;

$colors = PostRenderer::colors($store);
$samples = PostRenderer::gridSamples(Marketing::products());
$dir = ROOT . PostRenderer::BRAND_DIR;
$logos = ['logo' => ['Asosiy logo (rangli)', 'Oq yoki och fonlar uchun'], 'logo-white' => ['Oq logo', "To'q yashil lenta va fotolar ustida — rasmlarda shu ishlatiladi"]];
$v = static fn () => (string) @filemtime("$dir/logo.png") . @filemtime("$dir/logo-white.png") . md5((string) json_encode($colors));
?>
<h1>Brend va grid</h1>
<p class="lead">Dizayner agent va sizning dizayneringiz uchun yagona tizim: logo, ranglar, shrift va 6 ta tayyor rasm tartibi.
  Agentlar har bir postga shu uslubda tayyor 1080×1350 rasm chiqaradi — sahifa bir xil va professional ko'rinadi.</p>

<?php if (!function_exists('imagecreatetruecolor')): ?>
  <div class="flash error">Serverda rasm chizish moduli (php-gd) o'rnatilmagan. Serverda o'rnatish buyrug'ini bir marta qayta
    ishga tushiring (SERVER_UZ.md) — keyin grid va tayyor rasmlar ishlaydi.</div>
<?php endif; ?>
<h2>Instagram grid namunasi</h2>
<p class="small muted">Sahifangiz shunday ko'rinishi kerak: sotuv, ishonch va qamrov postlari almashinib turadi, hammasi bir xil ranglar va pastki
  lentada. Rasmni bosing — to'liq o'lchamda yuklab olasiz (dizayner uchun namuna).</p>
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

<div class="grid2">
  <form method="post" enctype="multipart/form-data" class="card"><?= csrf_field() ?>
    <input type="hidden" name="action" value="brand_upload">
    <h3>Logo</h3>
    <p class="small muted">PNG, shaffof fonli bo'lsa eng yaxshi. Logo yuklanmaguncha rasmlarda "MARYAM" yozuvi turadi.</p>
    <?php foreach ($logos as $key => [$label, $hint]): $has = is_file("$dir/$key.png"); ?>
      <label><?= e($label) ?> <span class="muted">— <?= e($hint) ?></span></label>
      <?php if ($has): ?>
        <div class="logo-prev <?= $key === 'logo-white' ? 'dark' : '' ?>"><img src="<?= e(url(['asset' => $key, 'v' => $v()])) ?>" alt="<?= e($label) ?>"></div>
      <?php endif; ?>
      <input type="file" name="<?= $key === 'logo-white' ? 'logo_white' : 'logo' ?>" accept="image/png,image/jpeg,image/webp">
    <?php endforeach; ?>
    <div class="actions"><button type="submit">Yuklash</button></div>
  </form>

  <form method="post" class="card"><?= csrf_field() ?>
    <input type="hidden" name="action" value="brand_colors">
    <h3>Ranglar</h3>
    <p class="small muted">Logotipdagi ranglar. O'zgartirsangiz, grid va barcha yangi rasmlar darhol yangilanadi.</p>
    <?php foreach (['primary' => 'Asosiy (fon, lenta)', 'accent' => 'Urg\'u (narx, belgilar)', 'dark' => "To'q (gradient, matn)"] as $k => $label): ?>
      <label class="color-row"><input type="color" name="<?= $k ?>" value="<?= e($colors[$k]) ?>"> <?= e($label) ?> <code><?= e($colors[$k]) ?></code></label>
    <?php endforeach; ?>
    <div class="actions"><button type="submit">Saqlash</button></div>
  </form>
</div>

<h2>Dizayner uchun qo'llanma</h2>
<div class="card small">
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
</div>
