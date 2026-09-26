<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="uz">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Maryam Travel</title>
<script src="https://telegram.org/js/telegram-web-app.js"></script>
<style>
  body { margin:0; min-height:100vh; display:grid; place-items:center; font:15px/1.5 system-ui, sans-serif; background:#0a4638; color:#fff; text-align:center; padding:16px; }
  b { color:#c9982f; } .err { max-width:320px; }
</style>
</head>
<body>
<div id="box"><b>MARYAM TRAVEL</b><br>Kirilmoqda…</div>
<script>
  const tg = window.Telegram && window.Telegram.WebApp;
  const box = document.getElementById('box');
  if (!tg || !tg.initData) {
    box.innerHTML = '<div class="err">Bu sahifani Telegram bot ichidagi <b>Ilova</b> tugmasi orqali oching.</div>';
  } else {
    tg.ready(); tg.expand();
    fetch('?tglogin=1', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'init_data=' + encodeURIComponent(tg.initData)
    }).then(r => {
      if (r.ok) { location.replace('?tg=1&p=home'); return; }
      const id = tg.initDataUnsafe && tg.initDataUnsafe.user ? tg.initDataUnsafe.user.id : '?';
      box.innerHTML = '<div class="err">Kirish rad etildi — ilova faqat Maryam Travel jamoasi uchun.<br><br>Sizning Telegram ID: <b>' + id + '</b><br>Administrator uni .env faylidagi TELEGRAM_ALLOWED_CHAT_IDS ga qo\'shsin.</div>';
    }).catch(() => { box.innerHTML = '<div class="err">Internet bilan aloqa yo\'q. Qayta oching.</div>'; });
  }
</script>
</body>
</html>
