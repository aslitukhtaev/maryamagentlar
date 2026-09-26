<?php
declare(strict_types=1);

$return = url(['p' => 'bilimlar']);
$categories = ['umumiy' => 'Umumiy', 'ishonch' => 'Ishonch (tajriba, mijozlar, litsenziya)', 'xizmat' => 'Xizmatlar', 'afzallik' => 'Raqobatchilardan farqimiz', 'aloqa' => 'Aloqa', 'faq' => "Ko'p so'raladigan savollar"];
?>
<h2 class="sub">Kompaniya faktlari</h2>
<p class="lead">Kompaniya haqidagi faktlar — barcha agentlar biladi va matnlarda ishonch uchun ishlatadi. Telegram botga aytilgan faktlar ham shu
  yerga tushadi. Faqat haqiqiy va tekshirilgan narsalarni yozing.</p>

<div class="card">
  <h3>Kompaniya haqida asosiy ma'lumotlar</h3>
  <p class="small">
    <b>Telefon:</b> <?= e($brand['phone'] ?: '—') ?> · <b>Telegram:</b> <?= e($brand['telegram'] ?: '—') ?> ·
    <b>Instagram:</b> <?= e($brand['instagram'] ?: '—') ?><br><b>Manzil:</b> <?= e($brand['address'] ?: '—') ?><br>
    <b>Brend ovozi:</b> <?= e($brand['voice']) ?><br>
    <b>Hech qachon demaymiz:</b> <?= e(implode(', ', $brand['never_say'] ?? [])) ?>
  </p>
  <?php if ($brand['facts'] ?? []): ?><ul class="small"><?php foreach ($brand['facts'] as $f): ?><li><?= e($f) ?></li><?php endforeach; ?></ul><?php endif; ?>
</div>

<form method="post" class="card"><?= csrf_field() ?>
  <input type="hidden" name="action" value="save"><input type="hidden" name="table" value="knowledge"><input type="hidden" name="return" value="<?= e($return) ?>">
  <h3>Yangi fakt</h3>
  <div class="row">
    <div><label>Toifa</label><select name="category"><?= options($categories, 'umumiy') ?></select></div>
  </div>
  <label>Fakt</label>
  <textarea name="content" required style="min-height:70px" placeholder="Masalan: Vyetnamga yiliga 1500+ turist jo'natamiz"></textarea>
  <div class="actions"><button type="submit">Qo'shish</button></div>
</form>

<h2>Faktlar</h2>
<?php foreach ($store->rows('knowledge') as $k): ?>
  <div class="card list-item">
    <div><span class="badge"><?= e($categories[$k['category']] ?? $k['category']) ?></span> <?= e($k['content']) ?></div>
    <?= delete_form('knowledge', (int) $k['id'], $return) ?>
  </div>
<?php endforeach; ?>
