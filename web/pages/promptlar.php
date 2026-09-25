<?php
declare(strict_types=1);

use Maryam\Prompts;

$name = isset(Prompts::ALL[$_GET['name'] ?? '']) ? $_GET['name'] : null;
?>
<?php if (!$name): ?>
  <h1>Promptlar</h1>
  <p class="lead">Prompt — agentning asosiy yo'riqnomasi (u kim, qanday fikrlaydi, nima qaytaradi). Odatda qoidalar va shablonlar yetarli;
    promptni agentning ishlash uslubini tubdan o'zgartirmoqchi bo'lsangiz tahrirlang. Har saqlash yangi versiya — xohlagan payt qaytasiz.</p>
  <div class="card">
    <table>
      <?php foreach (Prompts::ALL as $key => $label): $latest = $store->latestPrompt($key); ?>
        <tr><td><a href="<?= e(url(['p' => 'promptlar', 'name' => $key])) ?>"><?= e($label) ?></a></td>
          <td class="small muted"><?= $latest ? 'tahrirlangan · ' . e($latest['created_at']) : 'asl holatda' ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>

<?php else: $versions = $store->promptVersions($name); ?>
  <p class="small"><a href="<?= e(url(['p' => 'promptlar'])) ?>">← Promptlar</a></p>
  <h1><?= e(Prompts::ALL[$name]) ?></h1>
  <p class="lead">Javob formati (JSON) qismini o'zgartirmang — kod aynan shu maydonlarni kutadi. Qoidalar, shablon va faktlar promptga
    avtomatik qo'shiladi, ularni bu yerga yozish shart emas.</p>
  <form method="post" class="card"><?= csrf_field() ?>
    <input type="hidden" name="action" value="prompt_save"><input type="hidden" name="name" value="<?= e($name) ?>">
    <textarea name="content" class="code" required><?= e(Prompts::get($store, $name)) ?></textarea>
    <label>Nima o'zgardi? <span class="muted">(versiyalar ro'yxatida ko'rinadi)</span></label>
    <input name="note" placeholder="Masalan: hookni qisqaroq yozishni kuchaytirdim">
    <div class="actions"><button type="submit">Yangi versiya sifatida saqlash</button></div>
  </form>

  <h2>Versiyalar</h2>
  <div class="card">
    <table>
      <tr><td><b>Asl holat</b> <span class="muted small">(prompts/<?= e($name) ?>.md)</span></td><td></td>
        <td><form method="post" onsubmit="return confirm('Asl holatga qaytarilsinmi?')"><?= csrf_field() ?><input type="hidden" name="action" value="prompt_reset">
          <input type="hidden" name="name" value="<?= e($name) ?>"><button class="ghost">Asl holatga qaytarish</button></form></td></tr>
      <?php foreach ($versions as $i => $v): ?>
        <tr><td>#<?= $v['id'] ?> <?= $i === 0 ? '<span class="badge">amalda</span>' : '' ?> <?= e($v['note']) ?></td>
          <td class="small muted"><?= e($v['created_at']) ?></td>
          <td><?php if ($i > 0): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="prompt_restore">
            <input type="hidden" name="version_id" value="<?= (int) $v['id'] ?>"><button class="ghost">Shu versiyaga qaytish</button></form><?php endif; ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
<?php endif; ?>
