<?php
declare(strict_types=1);

use Maryam\Env;

$stats = $store->stats();
$flash = flash();
$old = $_SESSION['old'] ?? [];
unset($_SESSION['old']);
$nav = [
    'Ish' => ['home', 'studio', 'reja'],
    "O'qitish" => ['shablonlar', 'qoidalar', 'namunalar', 'promptlar'],
    'Bilim' => ['katalog', 'bilimlar'],
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
  @media (max-width: 820px) {
    .wrap { grid-template-columns:1fr; background:none; }
    aside { position:static; height:auto; display:flex; flex-wrap:wrap; gap:4px; padding:12px; }
    .logo { width:100%; margin:0 4px 6px; } .nav-group { display:none; }
    aside a { padding:6px 9px; font-size:14px; }
    main { padding:18px 16px 50px; } .grid2 { grid-template-columns:1fr; }
  }
</style>
</head>
<body>
<div class="wrap">
<aside>
  <div class="logo">MARYAM TRAVEL<small>Marketing bo'limi<?= Env::get('WEB_MOCK', '') === '1' ? ' · sinov rejimi' : '' ?></small></div>
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
