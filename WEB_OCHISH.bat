@echo off
chcp 65001 >nul
cd /d %~dp0

echo ============================================
echo   MARYAM TRAVEL - MARKETING BOLIMI (WEB)
echo ============================================
echo.
echo Eng oxirgi kod yuklab olinmoqda...
git pull origin claude/quirky-bardeen-xnme2p
echo.
echo Brauzerda oching: http://localhost:8000
echo Toxtatish: shu oynani yoping (yoki Ctrl+C)
echo.
start "" http://localhost:8000
php -S localhost:8000 -t public
