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
<h1>Studiya</h1>
<p class="lead">Mavzu bering — agentlar strategiya tuzadi, yozadi va muharrir tekshiradi. Shablon tanlasangiz, bitta tayyor material
  shu tuzilmada chiqadi; tanlamasangiz — 2 ta post va 2 ta reklama (turli burchaklardan).</p>

<form method="post" class="card" <?= busy_attr() ?>>
  <?= csrf_field() ?><input type="hidden" name="action" value="run">
  <label>Mavzu</label>
  <input name="topic" required placeholder="Vyetnam, Fukuok — oktabr qaynoq tur" value="<?= e($old['topic'] ?? '') ?>">
  <div class="row">
    <div><label>Shablon</label><select name="template_id">
      <option value="">Shablonsiz — 2 post + 2 reklama</option>
      <?php foreach ($templates as $t): ?>
        <option value="<?= $t['id'] ?>" <?= $preTemplate === (string) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?> (<?= e(FORMATS[$t['format']] ?? $t['format']) ?>)</option>
      <?php endforeach; ?>
    </select></div>
    <div><label>Yo'nalish</label><select name="tourism_type"><?= options(type_options($tones, false), (string) ($old['tourism_type'] ?? 'outbound')) ?></select></div>
    <div><label>Maqsad</label><select name="goal"><?= options(Brief::GOALS, (string) ($old['goal'] ?? 'lid')) ?></select></div>
    <div><label>Til</label><select name="language"><?= options(['' => "Yo'nalishga qarab"] + Brief::LANGUAGES, (string) ($old['language'] ?? '')) ?></select></div>
  </div>
  <label>Tafsilotlar <span class="muted">— katalogda bo'lmagan narx, sana, aksiya, mijoz gapi. Agent faktlarni to'qimaydi.</span></label>
  <textarea name="details" placeholder="Narx: 820$ dan | Jo'nash: 12 va 19 oktabr | Kiradi: parvoz, mehmonxona 4*, nonushta, transfer"><?= e($old['details'] ?? '') ?></textarea>
  <label>Maxsus auditoriya <span class="muted">(ixtiyoriy)</span></label>
  <input name="audience" placeholder="Masalan: asal oyiga chiqayotgan juftliklar" value="<?= e($old['audience'] ?? '') ?>">
  <div class="actions"><button type="submit">Yozdirish</button><span class="busy muted" hidden>Agentlar ishlamoqda: strategiya → yozish → tahrir (1-3 daqiqa)…</span></div>
</form>

<h2>Oxirgi ishlar</h2>
<div class="card">
  <table>
    <?php foreach ($store->recentBriefs(20) as $b): ?>
      <tr><td><a href="<?= e(url(['p' => 'studio', 'brief' => $b['id']])) ?>">#<?= $b['id'] ?> <?= e($b['topic']) ?></a></td>
        <td class="muted small"><?= e($tones[$b['tourism_type']]['label'] ?? $b['tourism_type']) ?></td>
        <td class="muted small"><?= e($b['created_at']) ?></td></tr>
    <?php endforeach; ?>
  </table>
</div>

<?php elseif (!$result): ?>
  <h1>#<?= $current['id'] ?> <?= e($current['topic']) ?></h1>
  <div class="card">Bu ish uchun natija yo'q (xato bilan tugagan bo'lishi mumkin).</div>

<?php else: $s = $result['strategy']; $tpl = !empty($result['template_id']) ? $store->row('templates', (int) $result['template_id']) : null; ?>
  <p class="small"><a href="<?= e(url(['p' => 'studio'])) ?>">← Studiya</a></p>
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
    <?php if ($result['placeholders']): ?><p class="warn">Qo'lda to'ldiring: <?= e(implode(', ', $result['placeholders'])) ?> — yoki Katalog/Bilimlarga kiriting, keyingi safar o'zi yozadi.</p><?php endif; ?>
    <?php if (!empty($s['missing_facts'])): ?><p class="muted small">Matn kuchliroq bo'lishi uchun: <?= e(implode('; ', $s['missing_facts'])) ?></p><?php endif; ?>
  </div>

  <?php if ($result['hooks']): ?>
    <h2>Birinchi qatorlar <span class="muted small">(hook — o'quvchini to'xtatadigan jumla)</span></h2>
    <div class="card"><ol class="small"><?php foreach ($result['hooks'] as $h): ?><li><?= e($h) ?></li><?php endforeach; ?></ol></div>
  <?php endif; ?>

  <h2>Tayyor matn<?= count($result['variants']) > 1 ? 'lar' : '' ?></h2>
  <?php foreach ($result['variants'] as $v) { variant_card($v, $saved[$v['db_id']] ?? [], $here, $briefId); } ?>

<?php endif; ?>
