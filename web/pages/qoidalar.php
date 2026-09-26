<?php
declare(strict_types=1);

$rules = $store->rows('rules');
$proposed = array_filter($rules, static fn ($r) => $r['status'] === 'proposed');
$byAgent = [];
foreach (array_reverse($rules) as $r) {
    if ($r['status'] !== 'proposed') {
        $byAgent[$r['agent']][] = $r;
    }
}
$return = url(['p' => 'qoidalar']);
$statusForm = static function (array $r, string $status, string $label, string $class = 'ghost') {
    ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="rule_status">
      <input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="status" value="<?= $status ?>">
      <button class="<?= $class ?>"><?= e($label) ?></button></form><?php
};
$deleteForm = static function (array $r, string $label = "O'chirish") use ($return) {
    ?><form method="post" onsubmit="return confirm('Rostdan o\'chirilsinmi?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="table" value="rules">
      <input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="return" value="<?= e($return) ?>">
      <button class="danger"><?= e($label) ?></button></form><?php
};
?>
<h2 class="sub">Qoidalar</h2>
<p class="lead">Qoida — agentga bergan qat'iy buyrug'ingiz ("har doim...", "hech qachon..."). Har bir yozishda va muharrir tekshiruvida
  ishlatiladi. O'qituvchi agent baholaringiz va izohlaringizdan yangi qoidalar taklif qiladi.</p>

<div class="grid2">
  <form method="post" class="card"><?= csrf_field() ?>
    <input type="hidden" name="action" value="save"><input type="hidden" name="table" value="rules"><input type="hidden" name="return" value="<?= e($return) ?>">
    <h3>Yangi qoida</h3>
    <select name="agent"><?= options(AGENTS, 'all') ?></select>
    <textarea name="content" required style="min-height:80px;margin-top:8px" placeholder="Masalan: Narxni doim '$ dan' shaklida va sanasi bilan yoz."></textarea>
    <div class="actions"><button type="submit">Qo'shish</button></div>
  </form>
  <form method="post" class="card" <?= busy_attr() ?>><?= csrf_field() ?><input type="hidden" name="action" value="propose_rules">
    <h3>O'qituvchidan so'rash</h3>
    <p class="small muted">Oxirgi baholar va izohlaringizni tahlil qilib, agentlarni kuchaytiradigan qoidalar taklif qiladi.
      Hozir <?= $stats['rated'] ?> ta baholangan matn bor<?= $stats['rated'] < 5 ? " — kamida 5-10 tasini baholang" : '' ?>.</p>
    <div class="actions"><button type="submit">Baholardan qoida chiqarish</button><span class="busy muted" hidden>Tahlil qilinmoqda…</span></div>
  </form>
</div>

<?php if ($proposed): ?>
  <h2>O'qituvchi takliflari — tasdiqlang</h2>
  <?php foreach ($proposed as $r): ?>
    <div class="card list-item">
      <div><span class="badge new"><?= e(AGENTS[$r['agent']] ?? $r['agent']) ?></span><pre class="text"><?= e($r['content']) ?></pre></div>
      <div class="actions"><?php $statusForm($r, 'active', 'Tasdiqlash', ''); $deleteForm($r, 'Rad etish'); ?></div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php foreach (AGENTS as $agent => $label): if (empty($byAgent[$agent])) { continue; } ?>
  <h2><?= e($label) ?></h2>
  <?php foreach ($byAgent[$agent] as $r): ?>
    <div class="card list-item">
      <div>
        <?= $r['status'] === 'off' ? '<span class="badge off">o\'chirilgan</span>' : '' ?>
        <?= $r['source'] === 'trainer' ? '<span class="badge gold">O\'qituvchidan</span>' : '' ?>
        <span class="<?= $r['status'] === 'off' ? 'muted' : '' ?>"><?= e($r['content']) ?></span>
        <details><summary class="small muted">Tahrirlash</summary>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="table" value="rules">
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="return" value="<?= e($return) ?>">
            <select name="agent"><?= options(AGENTS, $r['agent']) ?></select>
            <textarea name="content" style="min-height:70px;margin-top:6px"><?= e($r['content']) ?></textarea>
            <div class="actions"><button type="submit">Saqlash</button></div></form>
        </details>
      </div>
      <div class="actions">
        <?php $r['status'] === 'active' ? $statusForm($r, 'off', "O'chirib qo'yish") : $statusForm($r, 'active', 'Yoqish'); $deleteForm($r); ?>
      </div>
    </div>
  <?php endforeach; ?>
<?php endforeach; ?>
