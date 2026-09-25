<?php
declare(strict_types=1);

$edit = null;
if (isset($_GET['id'])) {
    $edit = $_GET['id'] === 'new'
        ? ['id' => 0, 'name' => '', 'format' => 'post', 'tourism_type' => '', 'stage' => 'sotuv', 'structure' => '', 'example' => '', 'rules' => '', 'design' => '', 'active' => 1]
        : $store->row('templates', (int) $_GET['id']);
}
$templates = $store->rows('templates');
?>
<?php if ($edit): ?>
  <p class="small"><a href="<?= e(url(['p' => 'shablonlar'])) ?>">← Shablonlar</a></p>
  <h1><?= $edit['id'] ? e($edit['name']) : 'Yangi shablon' ?></h1>
  <p class="lead">Tuzilmada o'zgaruvchan joylarni <code>{KATTA_HARF}</code> bilan belgilang — agent ularni katalog va brif faktlari bilan
    to'ldiradi. <code>{HOOK — ko'rsatma}</code> ko'rinishida agentga nima yozishni aytish mumkin.</p>
  <form method="post" class="card">
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="table" value="templates">
    <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><input type="hidden" name="return" value="<?= e(url(['p' => 'shablonlar'])) ?>">
    <label>Nomi</label><input name="name" required value="<?= e($edit['name']) ?>">
    <div class="row">
      <div><label>Format</label><select name="format"><?= options(FORMATS, $edit['format']) ?></select></div>
      <div><label>Yo'nalish</label><select name="tourism_type"><?= options(type_options($tones), $edit['tourism_type']) ?></select></div>
      <div><label>Voronka bosqichi</label><select name="stage"><?= options(STAGES, $edit['stage']) ?></select></div>
    </div>
    <label>Tuzilma <span class="muted">— post qanday ko'rinishda bo'lishi (qatorlar tartibi, emoji markerlari, slotlar)</span></label>
    <textarea name="structure" class="code" style="min-height:260px" required><?= e($edit['structure']) ?></textarea>
    <label>Yozish qoidalari <span class="muted">— shu shablonga xos (ohang, uzunlik, CTA)</span></label>
    <textarea name="rules" style="min-height:80px"><?= e($edit['rules']) ?></textarea>
    <label>Dizayn ko'rsatmasi <span class="muted">— dizayner uchun: o'lcham, fon, sarlavha, narx plashkasi, ranglar</span></label>
    <textarea name="design" style="min-height:80px"><?= e($edit['design']) ?></textarea>
    <label>Namuna post <span class="muted">(ixtiyoriy) — shu shablon bo'yicha yozilgan eng yaxshi post</span></label>
    <textarea name="example"><?= e($edit['example']) ?></textarea>
    <label class="check"><input type="checkbox" name="active" <?= $edit['active'] ? 'checked' : '' ?>> Faol (agentlar ishlatadi)</label>
    <div class="actions"><button type="submit">Saqlash</button>
      <?php if ($edit['id']): ?><a href="<?= e(url(['p' => 'studio', 'template' => $edit['id']])) ?>">Studiyada sinab ko'rish →</a><?php endif; ?></div>
  </form>
  <?php if ($edit['id']): ?>
    <form method="post" onsubmit="return confirm('Shablon o\'chirilsinmi?')"><?= csrf_field() ?>
      <input type="hidden" name="action" value="delete"><input type="hidden" name="table" value="templates"><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
      <input type="hidden" name="return" value="<?= e(url(['p' => 'shablonlar'])) ?>"><button class="danger">Shablonni o'chirish</button></form>
  <?php endif; ?>

<?php else: ?>
  <h1>Shablonlar</h1>
  <p class="lead">Shablon — sahifangizdagi har bir post turining doimiy tuzilmasi va dizayni. Agentlar ularga qat'iy amal qiladi,
    shuning uchun sahifa bir xil va professional ko'rinadi. Kontent-strateg har bandga mos shablonni o'zi tanlaydi.</p>

  <div class="grid2">
    <div class="card">
      <h3>Eng yaxshi postingizdan shablon yasash</h3>
      <p class="small muted">Ko'p mijoz olib kelgan postni qo'ying — O'qituvchi agent uning tuzilmasini ajratib, qayta ishlatiladigan shablon yasaydi.</p>
      <form method="post" <?= busy_attr() ?>><?= csrf_field() ?><input type="hidden" name="action" value="template_from_example">
        <textarea name="post" required placeholder="Post matnini shu yerga joylang"><?= e($old['post'] ?? '') ?></textarea>
        <label>Rasmi qanday edi? <span class="muted">(ixtiyoriy)</span></label>
        <input name="image_note" placeholder="Masalan: dengiz fotosi, tepada katta sarlavha, pastda sariq narx" value="<?= e($old['image_note'] ?? '') ?>">
        <div class="actions"><button type="submit">Shablon yasash</button><span class="busy muted" hidden>O'qituvchi tahlil qilmoqda…</span></div>
      </form>
    </div>
    <div class="card">
      <h3>Qo'lda yaratish</h3>
      <p class="small muted">Tuzilma, qoidalar va dizayn ko'rsatmasini o'zingiz yozasiz.</p>
      <a href="<?= e(url(['p' => 'shablonlar', 'id' => 'new'])) ?>"><button type="button">+ Yangi shablon</button></a>
    </div>
  </div>

  <h2>Barcha shablonlar (<?= count($templates) ?>)</h2>
  <?php foreach ($templates as $t): ?>
    <div class="card list-item">
      <div>
        <h3><a href="<?= e(url(['p' => 'shablonlar', 'id' => $t['id']])) ?>"><?= e($t['name']) ?></a></h3>
        <span class="badge"><?= e(FORMATS[$t['format']] ?? $t['format']) ?></span>
        <span class="badge"><?= e(STAGES[$t['stage']] ?? $t['stage']) ?></span>
        <span class="badge"><?= e($t['tourism_type'] ? ($tones[$t['tourism_type']]['label'] ?? $t['tourism_type']) : "Barcha yo'nalishlar") ?></span>
        <?php if (!$t['active']): ?><span class="badge off">o'chirilgan</span><?php endif; ?>
        <details><summary class="small muted">Tuzilma</summary><pre class="block"><?= e($t['structure']) ?></pre></details>
      </div>
      <a href="<?= e(url(['p' => 'studio', 'template' => $t['id']])) ?>" class="small">Sinash</a>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
