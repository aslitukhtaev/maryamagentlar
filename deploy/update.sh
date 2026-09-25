#!/usr/bin/env bash
# GitHub'dagi yangi kodni serverga tortadi (maryam-update.timer har 5 daqiqada chaqiradi).
# Baza (data/) va .env gitda yo'q — ular hech qachon o'zgarmaydi.
set -euo pipefail
APP=/opt/maryamagentlar

before=$(git -C "$APP" rev-parse HEAD)
runuser -u maryam -- git -C "$APP" pull -q --ff-only
after=$(git -C "$APP" rev-parse HEAD)

if [ "$before" != "$after" ]; then
    echo "Yangilandi: ${before:0:7} -> ${after:0:7}"
    # Web o'zgarishni darhol ko'radi; bot esa uzoq ishlaydigan jarayon — qayta ishga tushiramiz
    if systemctl is-enabled --quiet maryam-bot 2>/dev/null; then
        systemctl restart maryam-bot
    fi
fi
