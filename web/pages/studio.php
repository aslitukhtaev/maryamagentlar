<?php
declare(strict_types=1);

use Maryam\Agents\Copywriter;
use Maryam\Brief;

$briefId = (int) ($_GET['brief'] ?? 0);
$current = $briefId ? $store->brief($briefId) : null;
$result = $current ? $store->result($briefId, Copywriter::NAME, 'final') : null;
$saved = $current ? array_column($store->variants($briefId), null, 'db_id') : [];
$templates = $store->activeTemplates();
$preTemplate = (string) ($old['template_id'] ?? $_GET['template'] ?? '');
$here = url(['p' => 'studio', 'brief' => $briefId]);
?>
<?php if (!$current): ?>
<?php page_header('✍️', 'Copywriter', "Mavzuni yozing va shablonni tanlang — 1-3 daqiqada tayyor matn. Muharrir tekshirib, baho qo'yadi."); ?>

<form method="post" class="card" <?= busy_attr() ?>>
  <?= csrf_field() ?><input type="hidden" name="action" value="run">
  <label>Nima haqida yozamiz?</label>
  <input name="topic" required class="big-input" placeholder="Masalan: Vyetnam, Fukuok — oktabr qaynoq tur" value="<?= e($old['topic'] ?? '') ?>">

  <label>Qanday post?</label>
  <div class="choice-grid">
    <label class="choice"><input type="radio" name="template_id" value="" <?= $preTemplate === '' ? 'checked' : '' ?>>
      <span><b>Erkin</b><small>2 post + 2 reklama</small></span></label>
    <?php foreach ($templates as $t): ?>
      <label class="choice"><input type="radio" name="template_id" value="<?= $t['id'] ?>" <?= $preTemplate === (string) $t['id'] ? 'checked' : '' ?>>
        <span><b><?= e($t['name']) ?></b><small><?= e(FORMATS[$t['format']] ?? $t['format']) ?></small></span></label>
    <?php endforeach; ?>
  </div>

  <label>Tafsilotlar <span class="muted">(ixtiyoriy) — narx, sana, aksiya. Kompaniya → Turlar bo'limidagi turlar uchun shart emas.</span></label>
  <textarea name="details" rows="3" style="min-height:80px" placeholder="820$ dan, 12 va 19 oktabr, nonushta va transfer kiradi"><?= e($old['details'] ?? '') ?></textarea>

  <details class="more">
    <summary>Qo'shimcha sozlamalar</summary>
    <div class="row">
      <div><label>Yo'nalish</label><select name="tourism_type"><?= options(['' => 'Avtomatik'] + type_options($tones, false), (string) ($old['tourism_type'] ?? '')) ?></select></div>
      <div><label>Maqsad</label><select name="goal"><?= options(['' => 'Avtomatik'] + Brief::GOALS, (string) ($old['goal'] ?? '')) ?></select></div>
      <div><label>Til</label><select name="language"><?= options(['' => 'Avtomatik'] + Brief::LANGUAGES, (string) ($old['language'] ?? '')) ?></select></div>
    </div>
    <label>Kimga? <span class="muted">(ixtiyoriy)</span></label>
    <input name="audience" placeholder="Masalan: asal oyiga chiqayotgan juftliklar" value="<?= e($old['audience'] ?? '') ?>">
  </details>

  <div class="actions"><button type="submit" class="primary-btn">Yozdirish</button><span class="busy muted" hidden>Yozilmoqda: strategiya → matn → muharrir (1-3 daqiqa)…</span></div>
</form>

<?php $recent = $store->recentBriefs(12); if ($recent): ?>
<h2>Oxirgi ishlar</h2>
<div class="card list">
  <?php foreach ($recent as $b): ?>
    <a class="list-row" href="<?= e(url(['p' => 'studio', 'brief' => $b['id']])) ?>">
      <b><?= e($b['topic']) ?></b>
      <span class="muted small"><?= e($tones[$b['tourism_type']]['label'] ?? $b['tourism_type']) ?> · <?= e(substr($b['created_at'], 0, 16)) ?></span>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php elseif (!$result): ?>
  <h1>#<?= $current['id'] ?> <?= e($current['topic']) ?></h1>
  <div class="card">Bu ish uchun natija yo'q (xato bilan tugagan bo'lishi mumkin).</div>

<?php else: $s = $result['strategy']; $tpl = !empty($result['template_id']) ? $store->row('templates', (int) $result['template_id']) : null; ?>
  <p class="small"><a href="<?= e(url(['p' => 'studio'])) ?>">← Yangi post</a></p>
  <h1>#<?= $current['id'] ?> <?= e($current['topic']) ?></h1>
  <p>
    <span class="badge"><?= e($tones[$current['tourism_type']]['label'] ?? '') ?></span>
    <span class="badge"><?= e(Brief::GOALS[$current['goal']] ?? '') ?></span>
    <?php if ($tpl): ?><span class="badge gold">Shablon: <?= e($tpl['name']) ?></span><?php endif; ?>
  </p>

  <div class="card">
    <h3>Strategiya</h3>
    <p class="small"><b>Katta g'oya:</b> <?= e($s['big_idea'] ?? '') ?><br>
      <b>Asosiy xabar:</b> <?= e($s['key_message'] ?? '') ?><br>
      <b>Auditoriya:</b> <?= e($s['audience']['portrait'] ?? '') ?></p>
    <details><summary class="small">Og'riqlar, e'tirozlar, burchaklar</summary>
      <?php foreach (['pains' => "Og'riqlar", 'desires' => 'Istaklar', 'objections' => "E'tirozlar"] as $k => $label): ?>
        <p class="small"><b><?= $label ?>:</b> <?= e(implode(' · ', $s['audience'][$k] ?? [])) ?></p>
      <?php endforeach; ?>
      <ul class="small"><?php foreach ($s['angles'] ?? [] as $a): ?><li><b><?= e($a['name'] ?? '') ?></b> — <?= e($a['idea'] ?? '') ?></li><?php endforeach; ?></ul>
    </details>
    <?php if ($result['placeholders']): ?><p class="warn">Qo'lda to'ldiring: <?= e(implode(', ', $result['placeholders'])) ?> — yoki Kompaniya bo'limiga kiriting, keyingi safar o'zi yozadi.</p><?php endif; ?>
    <?php if (!empty($s['missing_facts'])): ?><p class="muted small">Matn kuchliroq bo'lishi uchun: <?= e(implode('; ', $s['missing_facts'])) ?></p><?php endif; ?>
  </div>

  <?php if ($result['hooks']): ?>
    <h2>Birinchi qatorlar <span class="muted small">(hook — o'quvchini to'xtatadigan jumla)</span></h2>
    <div class="card"><ol class="small"><?php foreach ($result['hooks'] as $h): ?><li><?= e($h) ?></li><?php endforeach; ?></ol></div>
  <?php endif; ?>

  <h2>Tayyor matn<?= count($result['variants']) > 1 ? 'lar' : '' ?></h2>
  <?php foreach ($result['variants'] as $v) { variant_card($v, $saved[$v['db_id']] ?? [], $here, $briefId); } ?>

<?php endif; ?>
