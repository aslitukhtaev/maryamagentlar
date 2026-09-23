@echo off
chcp 65001 >nul
cd /d %~dp0

echo ============================================
echo   MARYAM TRAVEL - AVTOMATIK YANGILASH VA TEST
echo ============================================
echo.

echo [1/3] Eng oxirgi kod yuklab olinmoqda...
git pull origin claude/quirky-bardeen-xnme2p
echo.

echo [2/3] .env fayl tayyorlanmoqda...
if not exist .env (
    copy .env.example .env >nul
    echo .env yaratildi
) else (
    echo .env allaqachon mavjud
)
echo.

echo [3/3] Vertex AI tekshirilmoqda (bir necha model nomzodi sinaladi)...
echo.
php bin\test-vertex.php

echo.
echo ============================================
echo   Agar yuqorida "OK!" ko'rinsa - tayyor!
echo   Endi shuni yozing:  php bin\copywriter.php
echo ============================================
echo.
pause
