<?php
declare(strict_types=1);

use Maryam\Env;

$stats = $store->stats();
$flash = flash();
$old = $_SESSION['old'] ?? [];
unset($_SESSION['old']);
// 5 ta bo'lim: 3 ta agent + o'qitish + kompaniya. Bo'lim ichidagi sahifalar — tablar.
$sections = [
    'studio' => ['✍️', 'Copywriter', 'Post va reklama yozadi', 'Copywriter', ['studio' => 'Yozdirish']],
    'reja' => ['🗓', 'Kontent-strateg', 'Haftalik reja tuzadi', 'Reja', ['reja' => 'Haftalik reja']],
    'brend' => ['🎨', 'Dizayner', 'Logo, uslub, tayyor rasmlar', 'Dizayner', ['brend' => 'Dizayner']],
    'oqitish' => ['🎓', "O'qitish studiyasi", "Agentlarni o'rgatish", "O'qitish", ['oqitish' => 'Umumiy', 'shablonlar' => 'Shablonlar', 'qoidalar' => 'Qoidalar', 'namunalar' => 'Namunalar', 'promptlar' => 'Kengaytirilgan']],
    'kompaniya' => ['🏢', 'Kompaniya', 'Turlar va faktlar', 'Kompaniya', ['katalog' => 'Turlar', 'bilimlar' => 'Faktlar']],
];
$currentSection = 'studio';
foreach ($sections as $key => $sec) {
    if (isset($sec[4][$page])) {
        $currentSection = $key;
    }
}
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
  .sections a { display:flex; gap:12px; align-items:center; padding:11px 12px; border-radius:10px; color:#e6efec; text-decoration:none; margin-bottom:4px; }
  .sections a:hover { background:rgba(255,255,255,.07); }
  .sections a.on { background:#fff; color:var(--brand-2); }
  .sections .ic { font-size:22px; width:28px; text-align:center; }
  .sections .tx { display:flex; flex-direction:column; line-height:1.25; flex:1; }
  .sections .tx b { font-size:15px; }
  .sections .tx small { font-size:12px; opacity:.7; }
  .sections .tx em { display:none; }
  .sections .pill { margin-left:auto; }
  .tabs { display:flex; gap:6px; margin:-6px 0 20px; overflow-x:auto; scrollbar-width:none; border-bottom:1px solid var(--line); }
  .tabs::-webkit-scrollbar { display:none; }
  .tabs a { padding:9px 14px; color:var(--muted); text-decoration:none; border-bottom:3px solid transparent; white-space:nowrap; font-weight:600; font-size:14px; }
  .tabs a.on { color:var(--brand); border-bottom-color:var(--gold); }
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
  .badge { display:inline-block; background:var(--soft); color:var(--brand); border-radius:99px; padding:1px 9px; font-size:12px; margin-right:4px; max-width:100%; overflow-wrap:anywhere; }
  .badge.gold { background:#f7eed8; color:#7a5a12; } .badge.off { background:#eee; color:#777; } .badge.new { background:#fff3cd; color:#7a5a12; }
  pre.text, pre.block { white-space:pre-wrap; word-wrap:break-word; background:#fbfaf6; border:1px solid var(--line); border-radius:8px; padding:12px 14px; font:14px/1.55 inherit; font-family:inherit; margin:8px 0 0; }
  pre.block { font-size:13px; max-height:260px; overflow:auto; }
  .variant-head { display:flex; gap:8px; align-items:baseline; }
  .score { margin-left:auto; background:var(--soft); color:var(--brand); border-radius:6px; padding:1px 8px; font-weight:700; font-size:13px; }
  .rate { display:flex; gap:8px; margin-top:12px; flex-wrap:wrap; }
  .rate select { width:auto; } .rate input { flex:1; min-width:200px; }
  .list-item { display:flex; gap:14px; align-items:flex-start; justify-content:space-between; }
  .list-item > div:first-child { min-width:0; flex:1; overflow-wrap:anywhere; }
  pre, .text { overflow-wrap:anywhere; }
  table { width:100%; border-collapse:collapse; } td, th { text-align:left; padding:8px 6px; border-bottom:1px solid var(--line); vertical-align:top; font-size:14px; }
  details summary { cursor:pointer; }
  .check { display:flex; align-items:center; gap:8px; font-weight:500; margin-top:14px; }
  .steps li { margin:6px 0; }
  .done { color:var(--muted); text-decoration:line-through; }
  .busy { margin-left:6px; }
  .page-head { display:flex; gap:14px; align-items:center; margin-bottom:18px; }
  .page-ic { font-size:34px; width:58px; height:58px; display:grid; place-items:center; background:var(--card); border:1px solid var(--line); border-radius:16px; flex:none; }
  .page-head h1 { margin:0; } .page-head p { margin:2px 0 0; color:var(--muted); }
  .big-input { font-size:17px; padding:13px 14px; }
  .choice-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(210px, 1fr)); gap:8px; }
  .choice { margin:0; font-weight:400; cursor:pointer; position:relative; }
  .choice input { position:absolute; top:0; left:0; width:1px; height:1px; opacity:0; pointer-events:none; }
  .choice span { display:block; height:100%; border:1.5px solid var(--line); border-radius:10px; padding:10px 12px; background:#fff; }
  .choice b { display:block; font-size:14px; } .choice small { color:var(--muted); font-size:12px; }
  .choice input:checked + span { border-color:var(--brand); background:var(--soft); box-shadow:0 0 0 1px var(--brand) inset; }
  .choice input:focus-visible + span { outline:2px solid var(--gold); }
  details.more { margin-top:14px; } details.more summary { color:var(--brand); font-weight:600; font-size:14px; }
  .primary-btn { padding:12px 26px; font-size:16px; }
  .list { padding:6px 0; }
  .list-row { display:flex; flex-direction:column; padding:10px 18px; text-decoration:none; color:var(--ink); border-bottom:1px solid var(--line); }
  .list-row:last-child { border-bottom:0; } .list-row:hover { background:#faf9f5; }
  .ig-grid { display:grid; grid-template-columns:repeat(3, 1fr); gap:4px; max-width:620px; margin:0 auto; }
  .choice-grid.three { grid-template-columns:repeat(3, 1fr); }
  .slides { display:flex; gap:8px; margin-top:6px; }
  .slides a { position:relative; flex:none; display:block; }
  .slides img { width:280px; max-width:100%; border-radius:8px; display:block; box-shadow:0 1px 3px rgba(0,0,0,.12); }
  .slides.carousel { overflow-x:auto; scroll-snap-type:x mandatory; padding-bottom:6px; }
  .slides.carousel a { scroll-snap-align:start; }
  .slides.carousel img { width:220px; }
  .slides span { position:absolute; top:6px; right:6px; background:rgba(0,0,0,.6); color:#fff; font-size:11px; border-radius:99px; padding:1px 7px; }
  .badge-slides { position:absolute; top:5px; right:5px; background:rgba(0,0,0,.6); color:#fff; font-style:normal; font-size:11px; border-radius:6px; padding:1px 6px; }
  .swatch.big { width:26px; height:26px; border-radius:6px; }
  .ig-grid.wide { max-width:none; grid-template-columns:repeat(auto-fill, minmax(150px, 1fr)); }
  details.more-card > summary { cursor:pointer; list-style:none; }
  details.more-card > summary::-webkit-details-marker { display:none; }
  details.more-card > summary::before { content:'▸ '; color:var(--brand); }
  details.more-card[open] > summary::before { content:'▾ '; }
  details.more-card[open] > summary { margin-bottom:12px; }
  h2.sub { margin-top:4px; }
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
    .list-item { flex-direction:column; gap:8px; }
    .list-item > div:last-child { display:flex; flex-wrap:wrap; gap:6px; }
    .wrap { grid-template-columns:minmax(0, 1fr); background:none; }
    /* Telefon: pastki panelda 5 ta bo'lim */
    aside { position:fixed; bottom:0; left:0; right:0; top:auto; z-index:20; height:auto; padding:4px 4px calc(4px + env(safe-area-inset-bottom)); }
    .logo { display:none; }
    .sections { display:flex; }
    .sections a { flex:1; flex-direction:column; gap:1px; padding:6px 2px; margin:0; border-radius:8px; }
    .sections .ic { font-size:20px; width:auto; }
    .sections .tx { align-items:center; }
    .sections .tx b, .sections .tx small { display:none; }
    .sections .tx em { display:block; font-style:normal; font-size:11px; font-weight:600; white-space:nowrap; }
    .sections .pill { position:absolute; margin:0 0 0 28px; font-size:10px; }
    .sections a { position:relative; }
    table { display:block; overflow-x:auto; }
    .choice-grid { grid-template-columns:1fr 1fr; gap:6px; }
    .choice span { padding:8px 9px; } .choice b { font-size:13px; } .choice small { font-size:11px; }
    .page-ic { width:46px; height:46px; font-size:26px; border-radius:12px; }
    main { padding:16px 16px 96px; } .grid2 { grid-template-columns:1fr; }
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
  <nav class="sections">
  <?php foreach ($sections as $key => [$icon, $label, $desc, $short, $pages]): ?>
    <a href="<?= e(url(['p' => array_key_first($pages)])) ?>" class="<?= $key === $currentSection ? 'on' : '' ?>">
      <span class="ic"><?= $icon ?></span>
      <span class="tx"><b><?= e($label) ?></b><em><?= e($short) ?></em><small><?= e($desc) ?></small></span>
      <?php if ($key === 'oqitish' && $stats['proposed']): ?><span class="pill"><?= $stats['proposed'] ?></span><?php endif; ?>
    </a>
  <?php endforeach; ?>
  </nav>
</aside>
<main>
  <?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
  <?php if (count($sections[$currentSection][4]) > 1): [$sIc, $sLabel, $sDesc] = $sections[$currentSection]; ?>
    <?php page_header($sIc, $sLabel, $currentSection === 'oqitish'
        ? "Agentlar siz o'rgatgan narsa bilan ishlaydi: shablon, qoida va namunalar. Har bahoyingiz ularni kuchaytiradi."
        : "Agentlar narx, sana va faktlarni shu yerdan oladi — sizdan so'ramaydi."); ?>
    <nav class="tabs">
      <?php foreach ($sections[$currentSection][4] as $p => $label): ?>
        <a href="<?= e(url(['p' => $p])) ?>" class="<?= $p === $page ? 'on' : '' ?>"><?= e($label) ?>
          <?php if ($p === 'qoidalar' && $stats['proposed']): ?><span class="pill"><?= $stats['proposed'] ?></span><?php endif; ?></a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>
  <?php require ROOT . "/web/pages/$page.php"; ?>
</main>
</div>
</body>
</html>
