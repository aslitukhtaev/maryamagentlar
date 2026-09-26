<?php
declare(strict_types=1);

use Maryam\Marketing;

$brandFilled = trim((string) ($brand['phone'] ?? '')) !== '';
$steps = [
    [is_file(Maryam\BrandAssets::dir() . '/logo-white.png') && count(Maryam\BrandAssets::refs()) >= 3, "Brend va grid: logoni va 3+ dizayn namunasini yuklang — tayyor rasmlar sizning uslubingizda chiqadi", 'brend'],
    [$brandFilled && $stats['knowledge'] + count($brand['facts'] ?? []) >= 3, 'Bilimlar: kompaniya faktlarini kiriting (tajriba, ofislar, afzalliklar)', 'bilimlar'],
    [count(Marketing::products()) > 0, "Katalog: sotuvdagi turlarni narx va sanasi bilan kiriting — agentlar [NARX] qoldirmaydi", 'katalog'],
    [$stats['examples'] >= 5, "Oltin namunalar: eng yaxshi 5-20 ta postingizni qo'shing (hozir {$stats['examples']} ta)", 'namunalar'],
    [$stats['templates'] >= 3, "Shablonlar: sahifangiz uchun doimiy post tuzilmalari (hozir {$stats['templates']} ta faol)", 'shablonlar'],
    [$stats['rated'] >= 10, "Studiyada kamida 10 ta natijani baholang va izoh yozing (hozir {$stats['rated']} ta)", 'studio'],
    [$stats['rules'] >= 8, "Qoidalar: O'qituvchidan baholaringiz asosida qoida taklif qilishni so'rang", 'qoidalar'],
];
$done = count(array_filter($steps, static fn ($s) => $s[0]));
?>
<h1>Marketing bo'limi</h1>
<p class="lead">Agentlar siz o'rgatgan narsa bilan ishlaydi: <b>shablonlar</b> (post tuzilmasi), <b>qoidalar</b> (buyruqlar),
  <b>oltin namunalar</b> (uslubingiz), <b>katalog</b> va <b>bilimlar</b> (faktlar). Har bahoyingiz va izohingiz ularni kuchaytiradi.</p>

<div class="grid">
  <div class="card stat"><b><?= $stats['templates'] ?></b><span>faol shablon</span></div>
  <div class="card stat"><b><?= $stats['rules'] ?></b><span>faol qoida<?= $stats['proposed'] ? " · {$stats['proposed']} ta taklif kutmoqda" : '' ?></span></div>
  <div class="card stat"><b><?= $stats['examples'] ?></b><span>oltin namuna</span></div>
  <div class="card stat"><b><?= $stats['rated'] ? number_format($stats['avg_rating'], 1) . '/5' : '—' ?></b><span>oxirgi 20 ta natijaning o'rtacha bahosi</span></div>
</div>

<h2>O'qitish darajasi: <?= $done ?>/<?= count($steps) ?></h2>
<div class="card">
  <ol class="steps">
    <?php foreach ($steps as [$ok, $text, $link]): ?>
      <li class="<?= $ok ? 'done' : '' ?>"><a href="<?= e(url(['p' => $link])) ?>"><?= e($text) ?></a></li>
    <?php endforeach; ?>
  </ol>
</div>

<h2>Agentlar qanday o'rganadi</h2>
<div class="grid2">
  <div class="card"><h3>1. Siz o'rgatasiz</h3><p class="small muted">Shablon, qoida, namuna, katalog va bilimlarni kiritasiz. Promptlarni ham tahrirlash mumkin — har versiya saqlanadi.</p></div>
  <div class="card"><h3>2. Agentlar yozadi</h3><p class="small muted">Studiyada yoki haftalik rejada: strateg → copywriter → muharrir. Muharrir qoidalar va shablonga mosligini tekshiradi.</p></div>
  <div class="card"><h3>3. Siz baholaysiz</h3><p class="small muted">1-5 baho va izoh. Yaxshilari "oltin namuna" bo'ladi, yomonlari "bunday yozma" misoli sifatida ishlatiladi.</p></div>
  <div class="card"><h3>4. O'qituvchi xulosa chiqaradi</h3><p class="small muted">Izohlaringizdan yangi qoidalar taklif qiladi — siz bir tugma bilan tasdiqlaysiz.</p></div>
</div>

<?php if ($stats['unrated']): ?>
  <p class="muted small"><?= $stats['unrated'] ?> ta natija hali baholanmagan — <a href="<?= e(url(['p' => 'studio'])) ?>">Studiya</a>.</p>
<?php endif; ?>
