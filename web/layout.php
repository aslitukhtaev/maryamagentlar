<?php
declare(strict_types=1);

use Maryam\Env;

$stats = $store->stats();
$flash = flash();
$old = $_SESSION['old'] ?? [];
unset($_SESSION['old']);
$nav = [
    'Ish' => ['home', 'studio', 'reja'],
    "O'qitish" => ['shablonlar', 'qoidalar', 'namunalar'],
    'Bilim' => ['brend', 'katalog', 'bilimlar'],
    'Kengaytirilgan' => ['promptlar'],
];
?>
<!doctype html>
<html lang="uz">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(PAGES[$page]) ?> · Maryam Travel marketing</title>
<style>
  :root {
    --bg:#f5f4ef; --card:#fff; --ink:#1b1f1d; --muted:#6a706c; --line:#e3e1d8;
    --brand:#0c5a47; --brand-2:#0a4638; --gold:#c9982f; --soft:#e8f1ee; --warn:#9a5b00; --err:#a32020;
  }
  * { box-sizing:border-box; }
  body { margin:0; font:15px/1.55 system-ui, -apple-system, "Segoe UI", sans-serif; background:var(--bg); color:var(--ink); }
  a { color:var(--brand); }
  .wrap { display:grid; grid-template-columns:230px minmax(0,1fr); min-height:100vh; background:linear-gradient(to right, var(--brand-2) 230px, transparent 230px); }
  aside { background:var(--brand-2); color:#dfe9e5; padding:18px 14px; position:sticky; top:0; height:100vh; overflow:auto; }
  .logo { font-weight:800; letter-spacing:.5px; color:#fff; margin:2px 8px 18px; }
  .logo small { display:block; font-weight:500; color:var(--gold); letter-spacing:0; font-size:12px; }
  .nav-group { font-size:11px; text-transform:uppercase; letter-spacing:1px; color:#8fb3a8; margin:16px 8px 6px; }
  aside a { display:flex; justify-content:space-between; align-items:center; padding:7px 10px; border-radius:7px; color:#e6efec; text-decoration:none; }
  aside a:hover { background:rgba(255,255,255,.07); }
  aside a.on { background:#fff; color:var(--brand-2); font-weight:600; }
  .pill { background:var(--gold); color:#1b1f1d; border-radius:99px; font-size:11px; padding:1px 7px; font-weight:700; }
  main { padding:26px 30px 60px; max-width:1060px; width:100%; }
  h1 { font-size:22px; margin:0 0 4px; } h2 { font-size:17px; margin:26px 0 10px; } h3 { font-size:15px; margin:0 0 6px; }
  .lead { color:var(--muted); margin:0 0 20px; max-width:720px; }
  .card { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:18px; margin-bottom:14px; }
  .grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(230px, 1fr)); gap:12px; }
  .grid2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
  .stat b { display:block; font-size:26px; color:var(--brand); }
  .stat span { color:var(--muted); font-size:13px; }
  label { display:block; font-weight:600; margin:12px 0 4px; font-size:14px; }
  label .muted { font-weight:400; }
  input, select, textarea { width:100%; padding:9px 10px; border:1px solid var(--line); border-radius:8px; font:inherit; background:#fff; color:var(--ink); }
  textarea { min-height:110px; resize:vertical; }
  textarea.code { font:13px/1.5 ui-monospace, Menlo, Consolas, monospace; min-height:460px; }
  input[type=checkbox] { width:auto; }
  .row { display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:12px; }
  button { background:var(--brand); color:#fff; border:0; border-radius:8px; padding:9px 16px; font:inherit; font-weight:600; cursor:pointer; }
  button:hover { background:var(--brand-2); }
  button:disabled { opacity:.6; cursor:wait; }
  button.ghost { background:none; color:var(--brand); border:1px solid #bcd3cb; padding:5px 11px; font-weight:500; }
  button.ghost:hover { background:var(--soft); }
  button.danger { background:none; color:var(--err); border:1px solid #e6c2c2; padding:5px 11px; font-weight:500; }
  .actions { display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-top:10px; }
  .actions form { margin:0; }
  .muted { color:var(--muted); } .small { font-size:13px; }
  .warn { color:var(--warn); font-size:13px; margin:6px 0 0; }
  .flash { border-radius:10px; padding:12px 16px; margin-bottom:16px; background:var(--soft); border:1px solid #bcd3cb; }
  .flash.error { background:#fbeaea; border-color:#eab9b9; color:var(--err); }
  .badge { display:inline-block; background:var(--soft); color:var(--brand); border-radius:99px; padding:1px 9px; font-size:12px; margin-right:4px; white-space:nowrap; }
  .badge.gold { background:#f7eed8; color:#7a5a12; } .badge.off { background:#eee; color:#777; } .badge.new { background:#fff3cd; color:#7a5a12; }
  pre.text, pre.block { white-space:pre-wrap; word-wrap:break-word; background:#fbfaf6; border:1px solid var(--line); border-radius:8px; padding:12px 14px; font:14px/1.55 inherit; font-family:inherit; margin:8px 0 0; }
  pre.block { font-size:13px; max-height:260px; overflow:auto; }
  .variant-head { display:flex; gap:8px; align-items:baseline; }
  .score { margin-left:auto; background:var(--soft); color:var(--brand); border-radius:6px; padding:1px 8px; font-weight:700; font-size:13px; }
  .rate { display:flex; gap:8px; margin-top:12px; flex-wrap:wrap; }
  .rate select { width:auto; } .rate input { flex:1; min-width:200px; }
  .list-item { display:flex; gap:14px; align-items:flex-start; justify-content:space-between; }
  .list-item > div:first-child { min-width:0; flex:1; }
  table { width:100%; border-collapse:collapse; } td, th { text-align:left; padding:8px 6px; border-bottom:1px solid var(--line); vertical-align:top; font-size:14px; }
  details summary { cursor:pointer; }
  .check { display:flex; align-items:center; gap:8px; font-weight:500; margin-top:14px; }
  .steps li { margin:6px 0; }
  .done { color:var(--muted); text-decoration:line-through; }
  .busy { margin-left:6px; }
  .ig-grid { display:grid; grid-template-columns:repeat(3, 1fr); gap:4px; max-width:620px; margin:0 auto; }
  .ig-grid a { position:relative; display:block; aspect-ratio:4/5; overflow:hidden; background:#ddd; }
  .ig-grid img { width:100%; height:100%; object-fit:cover; display:block; }
  .ig-grid span { position:absolute; left:0; right:0; bottom:0; background:rgba(0,0,0,.55); color:#fff; font-size:11px; padding:3px 6px; opacity:0; transition:opacity .15s; }
  .ig-grid a:hover span, .ig-grid a:focus span { opacity:1; }
  .upload-row { display:flex; gap:14px; align-items:center; margin:12px 0; }
  .logo-prev { background:#f3f2ec; border:1px solid var(--line); border-radius:10px; width:150px; height:84px; flex:none; display:grid; place-items:center; padding:8px; }
  .logo-prev.dark { background:var(--brand); }
  .logo-prev img { max-height:66px; max-width:130px; display:block; }
  .upload-btn { display:inline-block; margin-top:6px; background:var(--brand); color:#fff; border-radius:8px; padding:7px 14px; font-weight:600; font-size:14px; cursor:pointer; }
  .upload-btn.big { display:block; text-align:center; padding:14px; font-size:15px; margin:4px 0 0; }
  .ref-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(92px, 1fr)); gap:8px; margin-top:12px; }
  .ref { position:relative; aspect-ratio:1; border-radius:8px; overflow:hidden; background:#eee; }
  .ref img { width:100%; height:100%; object-fit:cover; display:block; }
  .ref form { position:absolute; top:4px; right:4px; margin:0; }
  .ref-del { background:rgba(0,0,0,.6); color:#fff; border-radius:99px; width:26px; height:26px; padding:0; font-size:17px; line-height:26px; }
  .ref-tag { position:absolute; left:4px; bottom:4px; background:var(--gold); color:#1b1f1d; font-size:10px; font-weight:700; border-radius:99px; padding:1px 7px; }
  .color-row { display:flex; align-items:center; gap:10px; font-weight:500; }
  .color-row input { width:48px; height:36px; padding:2px; }
  .swatch { display:inline-block; width:18px; height:18px; border-radius:4px; vertical-align:middle; border:1px solid var(--line); margin-right:4px; }
  @media (max-width: 820px) {
    .wrap { grid-template-columns:1fr; background:none; }
    aside { position:sticky; top:0; z-index:5; height:auto; display:flex; flex-wrap:nowrap; overflow-x:auto; gap:4px; padding:8px 10px; scrollbar-width:none; }
    aside::-webkit-scrollbar { display:none; }
    .logo, .nav-group { display:none; }
    aside a { padding:7px 11px; font-size:14px; white-space:nowrap; flex:none; }
    main { padding:18px 16px 50px; } .grid2 { grid-template-columns:1fr; }
  }
</style>
<?php if (!empty($_SESSION['tg'])): ?>
<script src="https://telegram.org/js/telegram-web-app.js"></script>
<script>if (window.Telegram && Telegram.WebApp) { Telegram.WebApp.ready(); Telegram.WebApp.expand(); try { Telegram.WebApp.setHeaderColor('#0a4638'); } catch (e) {} }</script>
<?php endif; ?>
<script>
  // Nusxalash: Telegram ichida clipboard API ishlamasa — eski usul
  function copyText(el, btn) {
    const text = el.innerText;
    const done = () => { btn.textContent = 'Nusxalandi ✓'; };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(done, () => fallback());
    } else { fallback(); }
    function fallback() {
      const t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select();
      try { document.execCommand('copy'); done(); } catch (e) { btn.textContent = 'Belgilab nusxalang'; }
      t.remove();
    }
  }
</script>
</head>
<body>
<div class="wrap">
<aside>
  <div class="logo">MARYAM TRAVEL<small>Marketing bo'limi<?= $ai instanceof Maryam\GeminiMock ? ' · sinov rejimi' : '' ?></small></div>
  <?php foreach ($nav as $group => $pages): ?>
    <div class="nav-group"><?= e($group) ?></div>
    <?php foreach ($pages as $p): ?>
      <a href="<?= e(url(['p' => $p])) ?>" class="<?= $p === $page ? 'on' : '' ?>"><?= e(PAGES[$p]) ?>
        <?php if ($p === 'qoidalar' && $stats['proposed']): ?><span class="pill"><?= $stats['proposed'] ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  <?php endforeach; ?>
</aside>
<main>
  <?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
  <?php require ROOT . "/web/pages/$page.php"; ?>
</main>
</div>
</body>
</html>
