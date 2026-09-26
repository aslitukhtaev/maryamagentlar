<?php
declare(strict_types=1);

$examples = $store->rows('house_examples');
$return = url(['p' => 'namunalar']);
?>
<h2 class="sub">Oltin namunalar</h2>
<p class="lead">Kompaniyangizning eng yaxshi postlari. Copywriter ularning ohangi, uzunligi va uslubini o'zlashtiradi (faktlarini emas).
  Qo'shish yo'llari: shu yerga joylash, Copywriter natijasidagi "Oltin namuna qilish" tugmasi yoki Telegram botga kanal postini forward qilish.</p>

<form method="post" class="card"><?= csrf_field() ?>
  <input type="hidden" name="action" value="save"><input type="hidden" name="table" value="house_examples"><input type="hidden" name="return" value="<?= e($return) ?>">
  <div class="row">
    <div><label>Yo'nalish</label><select name="tourism_type"><?= options(type_options($tones), 'outbound') ?></select></div>
    <div><label>Nega yaxshi? <span class="muted">(agent shunga e'tibor beradi)</span></label><input name="note" placeholder="Masalan: 40 ta lid keldi, hook va narx aniq"></div>
  </div>
  <label>Post matni</label>
  <textarea name="content" required></textarea>
  <div class="actions"><button type="submit">Qo'shish</button></div>
</form>

<h2>Namunalar (<?= count($examples) ?>)</h2>
<?php foreach ($examples as $x): ?>
  <div class="card list-item">
    <div>
      <span class="badge"><?= e($x['tourism_type'] ? ($tones[$x['tourism_type']]['label'] ?? $x['tourism_type']) : "Barcha yo'nalishlar") ?></span>
      <?php if ($x['note'] !== ''): ?><span class="small muted"><?= e($x['note']) ?></span><?php endif; ?>
      <pre class="text"><?= e($x['content']) ?></pre>
    </div>
    <?= delete_form('house_examples', (int) $x['id'], $return) ?>
  </div>
<?php endforeach; ?>
