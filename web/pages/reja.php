<?php
declare(strict_types=1);

use Maryam\Agents\ContentPlanner;
use Maryam\Marketing;

$week = (string) ($_GET['week'] ?? ContentPlanner::weekKey(new DateTimeImmutable('today')));
$plan = $store->plan($week);
$events = Marketing::upcomingEvents(new DateTimeImmutable('today'), 30);
$here = url(['p' => 'reja', 'week' => $week]);
?>
<h1>Haftalik reja</h1>
<p class="lead">Kontent-strateg katalog, mavsum, shablonlar va qoidalarga qarab haftalik reja tuzadi, copywriter har band uchun tayyor
  material yozadi. Bot ishlab tursa, har dushanba o'zi Telegram'ga yuboradi.</p>

<form method="post" class="card" <?= busy_attr() ?>>
  <?= csrf_field() ?><input type="hidden" name="action" value="plan">
  <label>Shu hafta uchun istak <span class="muted">(ixtiyoriy)</span></label>
  <input name="wishes" placeholder="Masalan: Vyetnam va Sharmga urg'u, bitta mijoz sharhi bo'lsin" value="<?= e($old['wishes'] ?? '') ?>">
  <div class="actions"><button type="submit"><?= $plan && $week === ContentPlanner::weekKey(new DateTimeImmutable('today')) ? 'Shu haftani qayta tuzish' : 'Shu hafta uchun reja tuzish' ?></button>
    <span class="busy muted" hidden>Reja tuzilmoqda va har band yozilmoqda — bir necha daqiqa…</span></div>
</form>

<?php if ($events): ?>
  <p class="small muted">Yaqin mavsum/bayramlar: <?= e(implode(' · ', array_map(static fn ($ev) => "{$ev['name']} ({$ev['days_until']} kun)", $events))) ?></p>
<?php endif; ?>

<?php if ($plan): ?>
  <h2><?= e($plan['week']) ?><?= $plan['week_focus'] ? ' — ' . e($plan['week_focus']) : '' ?></h2>
  <?php foreach ($plan['items'] as $i => $item): ?>
    <div class="card">
      <h3><?= $i + 1 ?>. <?= e($item['day']) ?> · <?= e(FORMATS[$item['format']] ?? $item['format']) ?> · <?= e($item['topic']) ?></h3>
      <p class="small">
        <span class="badge"><?= e($tones[$item['tourism_type']]['label'] ?? $item['tourism_type']) ?></span>
        <?php if (!empty($item['template_name'])): ?><span class="badge gold"><?= e($item['template_name']) ?></span><?php endif; ?>
        <span class="muted"><?= e($item['why']) ?></span>
      </p>
      <?php if (isset($item['error'])): ?>
        <p class="warn">Tayyorlanmadi: <?= e($item['error']) ?></p>
      <?php elseif (!empty($item['content'])):
          $v = $item['content'];
          $saved = $store->variant((int) $v['db_id']) ?? [];
          variant_card($v, $saved, $here, $item['brief_id'] ?? null);
          if (!empty($item['placeholders'])): ?><p class="warn">Qo'lda to'ldiring: <?= e(implode(', ', $item['placeholders'])) ?></p><?php endif;
      endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if ($plan['missing_info']): ?><p class="muted small">Reja kuchliroq bo'lishi uchun: <?= e(implode('; ', $plan['missing_info'])) ?></p><?php endif; ?>
<?php else: ?>
  <div class="card muted">Bu hafta uchun reja hali tuzilmagan.</div>
<?php endif; ?>

<?php $plans = $store->recentPlans(); if ($plans): ?>
  <h2>Oldingi rejalar</h2>
  <p><?php foreach ($plans as $p): ?><a class="badge" href="<?= e(url(['p' => 'reja', 'week' => $p['week']])) ?>"><?= e($p['week']) ?></a> <?php endforeach; ?></p>
<?php endif; ?>
