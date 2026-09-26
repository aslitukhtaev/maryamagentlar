<?php
declare(strict_types=1);

use Maryam\Marketing;

$fields = [
    'name' => ['Nomi', "Vyetnam, Fukuok — oktabr"],
    'price' => ['Narx', '820$ dan (1 kishi, 2 kishilik xonada)'],
    'dates' => ['Sanalar', "Jo'nash: 12, 19, 26 oktabr"],
    'duration' => ['Davomiyligi', '7 kun / 6 kecha'],
    'hotels' => ['Mehmonxona', 'Rixos Phu Quoc 5*, dengiz bo\'yida'],
    'includes' => ['Narxga kiradi', 'Parvoz, mehmonxona, nonushta, transfer, sug\'urta'],
    'excludes' => ['Kirmaydi', 'Ekskursiyalar, shaxsiy xarajatlar'],
    'seats' => ['Joylar', '20 joy'],
    'offer' => ['Aksiya', '1-oktabrgacha bron qilganlarga 50$ chegirma'],
];
$edit = isset($_GET['id']) ? $store->row('products', (int) $_GET['id']) : null;
$item = $edit ?? array_fill_keys([...array_keys($fields), 'selling_points'], '') + ['id' => 0, 'type' => 'outbound', 'active' => 1];
$return = url(['p' => 'katalog']);
$fileCount = count(array_filter(Marketing::products(), static fn ($p) => !str_starts_with((string) ($p['id'] ?? ''), 'p')));
?>
<h2 class="sub">Sotuvdagi turlar</h2>
<p class="lead">Sotuvdagi turlar. Agentlar narx, sana, mehmonxona va aksiyani shu yerdan oladi — sizdan so'ramaydi va matnda [NARX]
  qoldirmaydi. Faqat haqiqiy ma'lumot yozing; bilmagan maydonni bo'sh qoldiring. Sotuvdan chiqqan turni "faol emas" qiling.</p>

<form method="post" class="card"><?= csrf_field() ?>
  <input type="hidden" name="action" value="save"><input type="hidden" name="table" value="products">
  <input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><input type="hidden" name="return" value="<?= e($return) ?>">
  <h3><?= $edit ? 'Turni tahrirlash' : 'Yangi tur' ?></h3>
  <div class="row">
    <div><label>Yo'nalish</label><select name="type"><?= options(type_options($tones, false), $item['type']) ?></select></div>
    <?php foreach (['name', 'price', 'dates'] as $f): ?>
      <div><label><?= e($fields[$f][0]) ?></label><input name="<?= $f ?>" <?= $f === 'name' ? 'required' : '' ?> placeholder="<?= e($fields[$f][1]) ?>" value="<?= e($item[$f]) ?>"></div>
    <?php endforeach; ?>
  </div>
  <div class="row">
    <?php foreach (['duration', 'hotels', 'seats', 'offer'] as $f): ?>
      <div><label><?= e($fields[$f][0]) ?></label><input name="<?= $f ?>" placeholder="<?= e($fields[$f][1]) ?>" value="<?= e($item[$f]) ?>"></div>
    <?php endforeach; ?>
  </div>
  <div class="row">
    <?php foreach (['includes', 'excludes'] as $f): ?>
      <div><label><?= e($fields[$f][0]) ?></label><input name="<?= $f ?>" placeholder="<?= e($fields[$f][1]) ?>" value="<?= e($item[$f]) ?>"></div>
    <?php endforeach; ?>
  </div>
  <label>Kuchli tomonlari <span class="muted">— har qatorda bittadan: mijoz nega aynan shuni tanlaydi</span></label>
  <textarea name="selling_points" style="min-height:80px" placeholder="Mehmonxona to'g'ridan-to'g'ri dengiz bo'yida&#10;O'zbek tilida gid"><?= e($item['selling_points']) ?></textarea>
  <label class="check"><input type="checkbox" name="active" <?= $item['active'] ? 'checked' : '' ?>> Sotuvda (faol)</label>
  <div class="actions"><button type="submit">Saqlash</button><?php if ($edit): ?><a href="<?= e($return) ?>">Bekor qilish</a><?php endif; ?></div>
</form>

<h2>Turlar</h2>
<div class="card">
  <table>
    <tr><th>Tur</th><th>Narx</th><th>Sanalar</th><th></th></tr>
    <?php foreach ($store->rows('products') as $p): ?>
      <tr>
        <td><a href="<?= e(url(['p' => 'katalog', 'id' => $p['id']])) ?>"><?= e($p['name']) ?></a><br>
          <span class="badge"><?= e($tones[$p['type']]['label'] ?? $p['type']) ?></span><?= $p['active'] ? '' : '<span class="badge off">faol emas</span>' ?></td>
        <td><?= e($p['price']) ?></td><td class="small"><?= e($p['dates']) ?></td>
        <td><form method="post" onsubmit="return confirm('O\'chirilsinmi?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="table" value="products">
          <input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="return" value="<?= e($return) ?>"><button class="danger">O'chirish</button></form></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php if ($fileCount): ?><p class="muted small">Yana <?= $fileCount ?> ta tur config/products.php faylida.</p><?php endif; ?>
</div>
