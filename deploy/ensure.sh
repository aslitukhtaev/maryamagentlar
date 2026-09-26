#!/usr/bin/env bash
# Server holatini loyiha talabiga keltiradi — update.sh har safar chaqiradi (takroran xavfsiz).
# Shu tufayli yangi modul yoki sozlama kerak bo'lsa, qo'lda buyruq yozish shart emas.
set -uo pipefail
APP=/opt/maryamagentlar
fpm_changed=0

need=()
for pkg in php-gd php-zip php-mbstring php-curl php-sqlite3; do
    dpkg -s "$pkg" >/dev/null 2>&1 || need+=("$pkg")
done
if [ ${#need[@]} -gt 0 ]; then
    export DEBIAN_FRONTEND=noninteractive
    if ! apt-get install -y -qq "${need[@]}" >/dev/null 2>&1; then
        apt-get update -qq >/dev/null 2>&1 && apt-get install -y -qq "${need[@]}" >/dev/null 2>&1
    fi
    echo "O'rnatildi: ${need[*]}"
    fpm_changed=1
fi

PHP_VER=$(ls -d /etc/php/*/fpm 2>/dev/null | sort -V | tail -1 | cut -d/ -f4)
POOL="/etc/php/$PHP_VER/fpm/pool.d/maryam.conf"
if [ -f "$POOL" ] && ! grep -q 'post_max_size' "$POOL"; then
    printf 'php_admin_value[post_max_size] = 60M\nphp_admin_value[max_file_uploads] = 20\n' >> "$POOL"
    fpm_changed=1
fi
if [ "$fpm_changed" = 1 ] && [ -n "$PHP_VER" ]; then
    systemctl restart "php$PHP_VER-fpm"
fi

# Bot qayta ishga tushganda fon ishlari (bin/job.php) uzilmasin
UNIT=/etc/systemd/system/maryam-bot.service
if [ -f "$UNIT" ] && ! grep -q '^KillMode=process' "$UNIT"; then
    sed -i 's/^RestartSec=10$/RestartSec=10\nKillMode=process/' "$UNIT"
    systemctl daemon-reload
fi
exit 0
