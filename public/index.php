<?php
/**
 * Oddiy web interfeys:  php -S localhost:8000 -t public   ->  http://localhost:8000
 * Faqat o'z kompyuteringizda ishlatish uchun (parol yo'q).
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Maryam\Agents\Copywriter;
use Maryam\Brief;
use Maryam\Output;

set_time_limit(600); // AI bir necha bosqichda ishlaydi — 1-3 daqiqa ketishi mumkin

// Vertex AI ishlatiladi (Google Cloud $300 trial krediti bilan)
['ai' => $ai, 'store' => $store, 'brand' => $brand, 'tones' => $tones] = appVertex();
$e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$error = null;

// Baho berish
if (($_POST['action'] ?? '') === 'rate') {
    $store->rate((int) $_POST['variant_id'], (int) $_POST['rating'], trim($_POST['feedback'] ?? ''));
    header('Location: ?brief=' . (int) $_POST['brief_id'] . '#v' . (int) $_POST['variant_id']);
    exit;
}

// Yangi brif -> Copywriter
if (($_POST['action'] ?? '') === 'run') {
    try {
        $brief = Brief::normalize($_POST, $tones);
        $brief['id'] = $store->saveBrief($brief);
        $result = (new Copywriter($ai, $store, $brand, $tones))->run($brief);
        Output::save($brief, 'copywriter.txt', Copywriter::toText($result));
        header('Location: ?brief=' . $brief['id']);
        exit;
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}

$briefId = (int) ($_GET['brief'] ?? 0);
$current = $briefId ? $store->brief($briefId) : null;
$result = $current ? $store->result($briefId, Copywriter::NAME, 'final') : null;
$variants = $current ? array_column($store->variants($briefId), null, 'db_id') : [];
?>
<!doctype html>
<html lang="uz">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Maryam Travel · Copywriter</title>
<style>
  :root { --bg:#f6f4ef; --card:#fff; --ink:#1d1d1b; --muted:#6b6b66; --accent:#0f6b5c; --line:#e4e0d6; --warn:#a15c00; }
  * { box-sizing: border-box; }
  body { margin:0; font:15px/1.55 system-ui, sans-serif; background:var(--bg); color:var(--ink); }
  .wrap { display:grid; grid-template-columns: 260px 1fr; min-height:100vh; }
  aside { background:#fff; border-right:1px solid var(--line); padding:20px; }
  aside a { display:block; padding:6px 0; color:var(--ink); text-decoration:none; border-bottom:1px solid var(--line); font-size:14px; }
  aside a small { color:var(--muted); display:block; }
  main { padding:24px; max-width:980px; }
  h1 { font-size:20px; margin:0 0 16px; } h2 { font-size:17px; margin:24px 0 10px; }
  .card { background:var(--card); border:1px solid var(--line); border-radius:10px; padding:18px; margin-bottom:16px; }
  label { display:block; font-weight:600; margin:10px 0 4px; }
  input, select, textarea { width:100%; padding:9px; border:1px solid var(--line); border-radius:6px; font:inherit; }
  textarea { min-height:90px; }
  .row { display:grid; grid-template-columns: repeat(3, 1fr); gap:12px; }
  button { background:var(--accent); color:#fff; border:0; border-radius:6px; padding:10px 18px; font:inherit; cursor:pointer; margin-top:14px; }
  button.ghost { background:none; color:var(--accent); border:1px solid var(--accent); padding:4px 10px; margin:0; }
  .text { white-space:pre-wrap; background:#fbfaf7; border:1px solid var(--line); border-radius:6px; padding:14px; }
  .meta { color:var(--muted); font-size:13px; }
  .badge { display:inline-block; background:#e7f2ef; color:var(--accent); border-radius:99px; padding:2px 10px; font-size:13px; margin-right:6px; }
  .warn { color:var(--warn); font-size:13px; }
  .error { background:#fde8e8; border-color:#f3b4b4; }
  .rate { display:flex; gap:8px; align-items:center; margin-top:12px; flex-wrap:wrap; }
  .rate input[type=text] { flex:1; min-width:180px; }
  .rate button { margin:0; }
  ul { margin:6px 0; padding-left:20px; }
  #loading { display:none; }
  @media (max-width: 760px) { .wrap { grid-template-columns:1fr; } aside { border-right:0; } .row { grid-template-columns:1fr; } main { padding:16px; } }
</style>
</head>
<body>
<div class="wrap">
<aside>
  <a href="?"><b>+ Yangi brif</b></a>
  <?php foreach ($store->recentBriefs() as $b): ?>
    <a href="?brief=<?= $b['id'] ?>">#<?= $b['id'] ?> <?= $e(mb_strimwidth($b['topic'], 0, 40, '…')) ?>
      <small><?= $e($tones[$b['tourism_type']]['label'] ?? $b['tourism_type']) ?> · <?= $e($b['created_at']) ?></small></a>
  <?php endforeach; ?>
</aside>
<main>
<?php if ($error): ?><div class="card error"><b>Xato:</b> <?= $e($error) ?></div><?php endif; ?>

<?php if (!$current): ?>
  <h1>Copywriter agent — yangi brif</h1>
  <form method="post" class="card" onsubmit="document.getElementById('loading').style.display='block'; this.querySelector('button').disabled=true;">
    <input type="hidden" name="action" value="run">
    <label>Mavzu</label>
    <input name="topic" required placeholder="Umra 2027 — erta bron aksiyasi" value="<?= $e($_POST['topic'] ?? '') ?>">
    <div class="row">
      <div><label>Turizm turi</label><select name="tourism_type">
        <?php foreach ($tones as $k => $t): ?><option value="<?= $k ?>"><?= $e($t['label']) ?></option><?php endforeach; ?>
      </select></div>
      <div><label>Maqsad</label><select name="goal">
        <?php foreach (Brief::GOALS as $k => $g): ?><option value="<?= $k ?>"><?= $e($g) ?></option><?php endforeach; ?>
      </select></div>
      <div><label>Til</label><select name="language">
        <option value="">Turizm turiga qarab</option>
        <?php foreach (Brief::LANGUAGES as $k => $l): ?><option value="<?= $k ?>"><?= $e($l) ?></option><?php endforeach; ?>
      </select></div>
    </div>
    <label>Tafsilotlar <span class="meta">— narx, sanalar, nima kiradi, bonuslar, aksiya muddati. Agent faktlarni o'zi to'qimaydi.</span></label>
    <textarea name="details" placeholder="Narx: ... | Jo'nash: ... | Kiradi: aviabilet, viza, mehmonxona ... | Erta bron chegirmasi: ... gacha"><?= $e($_POST['details'] ?? '') ?></textarea>
    <label>Maxsus auditoriya <span class="meta">(ixtiyoriy)</span></label>
    <input name="audience" placeholder="Masalan: ota-onasini Umraga yubormoqchi bo'lgan 30-40 yoshli farzandlar" value="<?= $e($_POST['audience'] ?? '') ?>">
    <button>Matnlarni yozish</button>
    <p id="loading" class="meta">Agent ishlamoqda: strategiya → yozish → tahrir. Bu 1-3 daqiqa davom etadi…</p>
  </form>

<?php elseif (!$result): ?>
  <div class="card">Bu brif uchun natija yo'q (ehtimol xato bilan tugagan).</div>

<?php else: $s = $result['strategy']; ?>
  <h1>#<?= $current['id'] ?> <?= $e($current['topic']) ?></h1>
  <div class="card">
    <span class="badge"><?= $e($tones[$current['tourism_type']]['label'] ?? '') ?></span>
    <span class="badge"><?= $e(Brief::GOALS[$current['goal']] ?? '') ?></span>
    <h2>Strategiya</h2>
    <p><b>Katta g'oya:</b> <?= $e($s['big_idea'] ?? '') ?><br>
       <b>Asosiy xabar:</b> <?= $e($s['key_message'] ?? '') ?><br>
       <b>Auditoriya:</b> <?= $e($s['audience']['portrait'] ?? '') ?></p>
    <details><summary>Og'riqlar, istaklar, e'tirozlar, burchaklar</summary>
      <?php foreach (['pains' => "Og'riqlar", 'desires' => 'Istaklar', 'objections' => "E'tirozlar", 'triggers' => 'Undovchi omillar'] as $k => $label): ?>
        <b><?= $label ?>:</b><ul><?php foreach ($s['audience'][$k] ?? [] as $x): ?><li><?= $e($x) ?></li><?php endforeach; ?></ul>
      <?php endforeach; ?>
      <b>Burchaklar:</b><ul><?php foreach ($s['angles'] ?? [] as $a): ?><li><b><?= $e($a['name'] ?? '') ?></b> — <?= $e($a['idea'] ?? '') ?></li><?php endforeach; ?></ul>
    </details>
    <?php if ($result['placeholders']): ?><p class="warn">Qo'lda to'ldiring: <?= $e(implode(', ', $result['placeholders'])) ?></p><?php endif; ?>
    <?php if (!empty($s['missing_facts'])): ?><p class="meta">Keyingi safar brifga qo'shing: <?= $e(implode('; ', $s['missing_facts'])) ?></p><?php endif; ?>
  </div>

  <h2>Hooklar</h2>
  <div class="card"><ol><?php foreach ($result['hooks'] as $h): ?><li><?= $e($h) ?></li><?php endforeach; ?></ol></div>

  <?php foreach ($result['variants'] as $v): $saved = $variants[$v['db_id']] ?? []; ?>
    <?php $text = $v['hook'] . "\n\n" . $v['body'] . "\n\n" . $v['cta'] . ($v['hashtags'] ? "\n\n" . implode(' ', $v['hashtags']) : ''); ?>
    <div class="card" id="v<?= $v['db_id'] ?>">
      <h2 style="margin-top:0"><?= $v['kind'] === 'ad' ? 'Target reklama' : 'Post' ?> · <?= $e($v['angle']) ?></h2>
      <p class="meta"><?= $e($v['framework']) ?> · muharrir bahosi <b><?= $v['score'] ?>/10</b>
        <?php foreach ($v['scores'] as $k => $sc): ?> · <?= $e($k) ?> <?= $sc ?><?php endforeach; ?></p>
      <?php if ($v['kind'] === 'ad'): ?>
        <p><b>Sarlavha:</b> <?= $e($v['headline']) ?> <span class="meta">(<?= mb_strlen($v['headline']) ?>/40)</span><br>
           <b>Tavsif:</b> <?= $e($v['description']) ?> <span class="meta">(<?= mb_strlen($v['description']) ?>/30)</span><br>
           <b>Tugma:</b> <?= $e($v['cta_button']) ?></p>
      <?php endif; ?>
      <div class="text"><?= $e($text) ?></div>
      <button class="ghost" type="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.innerText); this.textContent='Nusxalandi ✓'">Nusxalash</button>
      <?php foreach ($v['warnings'] ?? [] as $w): ?><p class="warn">⚠ <?= $e($w) ?></p><?php endforeach; ?>
      <?php if ($v['changes']): ?><details><summary class="meta">Muharrir nimani tuzatdi</summary><ul>
        <?php foreach ($v['changes'] as $c): ?><li><?= $e($c) ?></li><?php endforeach; ?></ul></details><?php endif; ?>
      <form method="post" class="rate">
        <input type="hidden" name="action" value="rate"><input type="hidden" name="brief_id" value="<?= $current['id'] ?>">
        <input type="hidden" name="variant_id" value="<?= $v['db_id'] ?>">
        <select name="rating" style="width:auto">
          <?php foreach ([5 => "5 — a'lo", 4 => '4 — yaxshi', 3 => '3 — o\'rtacha', 2 => '2 — kuchsiz', 1 => '1 — yaroqsiz'] as $n => $l): ?>
            <option value="<?= $n ?>" <?= ($saved['rating'] ?? null) == $n ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="feedback" placeholder="Nima yoqdi / yoqmadi? (agent shundan o'rganadi)" value="<?= $e($saved['feedback'] ?? '') ?>">
        <button><?= isset($saved['rating']) ? 'Bahoni yangilash' : 'Baholash' ?></button>
      </form>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
</main>
</div>
</body>
</html>
