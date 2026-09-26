#!/usr/bin/env bash
# MARYAM TRAVEL — serverga bir buyruq bilan o'rnatish (Ubuntu 22.04/24.04, Google Cloud VM uchun moslangan).
#
#   curl -fsSL https://raw.githubusercontent.com/aslitukhtaev/maryamagentlar/claude/quirky-bardeen-xnme2p/deploy/setup.sh | sudo bash
#
# Qayta ishga tushirish xavfsiz: mavjud .env va baza saqlanib qoladi.
set -euo pipefail

REPO="${REPO:-https://github.com/aslitukhtaev/maryamagentlar.git}"
BRANCH="${BRANCH:-claude/quirky-bardeen-xnme2p}"
APP=/opt/maryamagentlar
APP_USER=maryam

[ "$(id -u)" -eq 0 ] || { echo "sudo bilan ishga tushiring"; exit 1; }
say() { printf '\n\033[1;32m==> %s\033[0m\n' "$*"; }

meta() { curl -fs -m 2 -H 'Metadata-Flavor: Google' "http://metadata.google.internal/computeMetadata/v1/$1" || true; }

say "1/7 Tizim paketlari (PHP, Caddy, git)"
if [ ! -f /swapfile ] && [ "$(free -m | awk '/Mem:/ {print $2}')" -lt 1500 ]; then
    fallocate -l 1G /swapfile && chmod 600 /swapfile && mkswap /swapfile >/dev/null && swapon /swapfile
    echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq git curl openssl php-fpm php-cli php-sqlite3 php-curl php-mbstring caddy >/dev/null
PHP_VER=$(ls -d /etc/php/*/fpm | sort -V | tail -1 | cut -d/ -f4)   # masalan 8.3

say "2/7 Loyiha kodi ($BRANCH)"
id "$APP_USER" >/dev/null 2>&1 || useradd --system --create-home --home-dir /home/$APP_USER --shell /usr/sbin/nologin "$APP_USER"
if [ ! -d "$APP/.git" ]; then
    git clone -q --branch "$BRANCH" "$REPO" "$APP"
else
    git -C "$APP" pull -q --ff-only
fi
chown -R "$APP_USER:$APP_USER" "$APP"
git config --system --get-all safe.directory 2>/dev/null | grep -qx "$APP" || git config --system --add safe.directory "$APP"

say "3/7 Sozlamalar (.env)"
ENV="$APP/.env"
setenv() { # setenv KEY VALUE — .env da bor bo'lsa almashtiradi, bo'lmasa qo'shadi
    if grep -q "^$1=" "$ENV"; then sed -i "s|^$1=.*|$1=$2|" "$ENV"; else echo "$1=$2" >> "$ENV"; fi
}
if [ ! -f "$ENV" ]; then
    cp "$APP/.env.example" "$ENV"
    PASSWORD=$(openssl rand -base64 12 | tr -dc 'A-Za-z0-9' | head -c 12)
    setenv WEB_PASSWORD "$PASSWORD"
    setenv TIMEZONE Asia/Tashkent
    setenv VERTEX_MODEL gemini-2.5-flash   # shu loyihada ishlashi tasdiqlangan model
    PROJECT=$(meta project/project-id)
    [ -n "$PROJECT" ] && setenv GOOGLE_CLOUD_PROJECT_ID "$PROJECT"
    if [ -r /dev/tty ]; then
        echo "Telegram bot (ixtiyoriy — bo'sh qoldirsangiz, keyin .env ga yozasiz):"
        read -r -p "  TELEGRAM_BOT_TOKEN: " TG_TOKEN < /dev/tty || true
        read -r -p "  TELEGRAM_CHAT_ID:   " TG_CHAT < /dev/tty || true
        [ -n "${TG_TOKEN:-}" ] && setenv TELEGRAM_BOT_TOKEN "$TG_TOKEN"
        [ -n "${TG_CHAT:-}" ] && setenv TELEGRAM_CHAT_ID "$TG_CHAT"
    fi
fi
chown "$APP_USER:$APP_USER" "$ENV" && chmod 600 "$ENV"
mkdir -p "$APP/data" "$APP/output" && chown -R "$APP_USER:$APP_USER" "$APP/data" "$APP/output"

say "4/7 PHP-FPM"
cat > "/etc/php/$PHP_VER/fpm/pool.d/maryam.conf" <<EOF
[maryam]
user = $APP_USER
group = $APP_USER
listen = /run/php/maryam.sock
listen.owner = caddy
listen.group = caddy
pm = ondemand
pm.max_children = 4
pm.process_idle_timeout = 60s
request_terminate_timeout = 1800
php_admin_value[max_execution_time] = 1800
php_admin_value[memory_limit] = 256M
php_admin_value[upload_max_filesize] = 10M
EOF
systemctl restart "php$PHP_VER-fpm"

say "5/7 Veb-server (Caddy, HTTPS)"
IP=$(meta instance/network-interfaces/0/access-configs/0/external-ip)
[ -n "$IP" ] || IP=$(curl -fs -m 5 https://api.ipify.org || true)
[ -n "$IP" ] || { echo "Serverning tashqi IP manzili topilmadi — VM'da External IP borligini tekshiring."; exit 1; }
HOST="${HOST:-${IP//./-}.sslip.io}"   # domen bo'lmasa: 34-1-2-3.sslip.io -> shu IP (bepul HTTPS uchun)
cat > /etc/caddy/Caddyfile <<EOF
$HOST {
    root * $APP/public
    encode gzip
    php_fastcgi unix//run/php/maryam.sock
    file_server
}
http://$IP {
    redir https://$HOST{uri}
}
EOF
systemctl restart caddy
setenv WEBAPP_URL "https://$HOST"   # Telegram bot ichidagi Mini App shu manzilni ochadi
chown "$APP_USER:$APP_USER" "$ENV" && chmod 600 "$ENV"   # sed -i faylni root nomiga qayta yaratadi

say "6/7 Telegram bot xizmati"
cat > /etc/systemd/system/maryam-bot.service <<EOF
[Unit]
Description=Maryam Travel Telegram bot
After=network-online.target
Wants=network-online.target

[Service]
User=$APP_USER
WorkingDirectory=$APP
ExecStart=/usr/bin/php bin/bot.php
Restart=always
RestartSec=10
# Bot qayta ishga tushganda (yangilanish) fon ishlari (bin/job.php) uzilmasin
KillMode=process

[Install]
WantedBy=multi-user.target
EOF
systemctl daemon-reload
if grep -q '^TELEGRAM_BOT_TOKEN=.\+' "$ENV"; then
    systemctl enable --now maryam-bot >/dev/null 2>&1 && systemctl restart maryam-bot
    BOT="ishlayapti (journalctl -u maryam-bot -f)"
else
    BOT="o'chiq — .env ga TELEGRAM_BOT_TOKEN yozib: sudo systemctl enable --now maryam-bot"
fi

say "7/7 GitHub'dan avtomatik yangilanish (har 5 daqiqada)"
cat > /etc/systemd/system/maryam-update.service <<EOF
[Unit]
Description=Maryam Travel: GitHub'dan yangilash

[Service]
Type=oneshot
ExecStart=/bin/bash $APP/deploy/update.sh
EOF
cat > /etc/systemd/system/maryam-update.timer <<EOF
[Unit]
Description=Maryam Travel: har 5 daqiqada yangilash

[Timer]
OnBootSec=2min
OnUnitActiveSec=5min

[Install]
WantedBy=timers.target
EOF
systemctl daemon-reload
systemctl enable --now maryam-update.timer >/dev/null 2>&1

PASSWORD=$(grep '^WEB_PASSWORD=' "$ENV" | cut -d= -f2-)
cat <<EOF

============================================================
  TAYYOR!
  Manzil:  https://$HOST
  Login:   istalgan so'z (masalan: maryam)
  Parol:   $PASSWORD

  Telegram bot: $BOT
  Sozlamalar:   sudo nano $APP/.env
  AI tekshiruv: sudo -u $APP_USER php $APP/bin/test-vertex.php
============================================================
(HTTPS sertifikati birinchi ochishda 10-60 soniya olishi mumkin.)
EOF
