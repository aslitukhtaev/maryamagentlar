# ============================================================
# MARYAM TRAVEL — TO'LIQ SETUP SKRIPTI (Windows PowerShell)
# ============================================================
# Ishga tushirish: powershell.exe -ExecutionPolicy Bypass -File setup-complete.ps1
# ============================================================

Write-Host "
╔════════════════════════════════════════════════════════════╗
║   MARYAM TRAVEL — GOOGLE CLOUD SDK + VERTEX AI SETUP      ║
║                     (TO'LIQ AVTOMATIK)                     ║
╚════════════════════════════════════════════════════════════╝
" -ForegroundColor Cyan

# ============================================================
# QADAM 1: EXECUTION POLICY
# ============================================================
Write-Host "`n[1/5] Execution Policy o'zgartirilmoqda..." -ForegroundColor Yellow
try {
    Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser -Force -ErrorAction Stop
    Write-Host "✓ Execution Policy o'zgartirildi" -ForegroundColor Green
} catch {
    Write-Host "✗ Xato: $_" -ForegroundColor Red
    exit 1
}

# ============================================================
# QADAM 2: GOOGLE CLOUD SDK O'RNATISH
# ============================================================
Write-Host "`n[2/5] Google Cloud SDK yuklab olinmoqda..." -ForegroundColor Yellow

$tempDir = "$env:TEMP\gcloud-install-$(Get-Random)"
$installDir = "C:\Program Files\Google\cloud-sdk"
$url = "https://dl.google.com/dl/cloudsdk/channels/rapid/google-cloud-sdk-windows-x86_64-bundled-python.zip"

try {
    # Papka yaratish
    New-Item -ItemType Directory -Force -Path $tempDir | Out-Null
    
    # Yuklab olish
    $zipPath = "$tempDir\gcloud.zip"
    Write-Host "  Downloading from Google..." -ForegroundColor Gray
    Invoke-WebRequest -Uri $url -OutFile $zipPath -UseBasicParsing -ErrorAction Stop
    Write-Host "  ✓ Yuklab olindi" -ForegroundColor Green
    
    # Archiv ochish
    Write-Host "  Extracting..." -ForegroundColor Gray
    Expand-Archive -Path $zipPath -DestinationPath "C:\Program Files\Google" -Force -ErrorAction Stop
    Write-Host "  ✓ Ochildi" -ForegroundColor Green
    
    # O'rnatish
    Write-Host "  Installing..." -ForegroundColor Gray
    $installScript = "C:\Program Files\Google\cloud-sdk\install.bat"
    if (Test-Path $installScript) {
        & $installScript --quiet --path-update=true --usage-reporting=false 2>&1 | Out-Null
        Write-Host "  ✓ O'rnatildi" -ForegroundColor Green
    } else {
        throw "install.bat topilmadi"
    }
    
    # Cleanup
    Remove-Item -Path $tempDir -Recurse -Force -ErrorAction SilentlyContinue
} catch {
    Write-Host "✗ Google Cloud SDK o'rnatishda xato: $_" -ForegroundColor Red
    exit 1
}

# ============================================================
# QADAM 3: PATH'GA QOSH (agar kerak bo'lsa)
# ============================================================
Write-Host "`n[3/5] Environment PATH tekshirilmoqda..." -ForegroundColor Yellow

$gcloudBin = "C:\Program Files\Google\cloud-sdk\bin"
if ($gcloudBin -notin $env:Path.Split(';')) {
    [Environment]::SetEnvironmentVariable(
        "Path",
        "$env:Path;$gcloudBin",
        "User"
    )
    Write-Host "✓ PATH o'zgartirildi" -ForegroundColor Green
} else {
    Write-Host "✓ PATH allaqachon mavjud" -ForegroundColor Green
}

# ============================================================
# QADAM 4: GCLOUD LOGIN (BRAUZER ORQALI)
# ============================================================
Write-Host "`n[4/5] Google login boshlanmoqda..." -ForegroundColor Yellow
Write-Host "  Brauzer ochiladi — email'ngizga kiring va 'Allow' bosing" -ForegroundColor Cyan

# Yangi process'da gcloud qo'llash (PATH'dan foydalanish uchun)
$process = Start-Process -FilePath "cmd.exe" -ArgumentList "/c gcloud auth application-default login" -PassThru -Wait

if ($process.ExitCode -eq 0) {
    Write-Host "✓ Google login muvaffaqiyatli" -ForegroundColor Green
} else {
    Write-Host "⚠ Login tekshirindan o'tishi mumkin (xato kod: $($process.ExitCode))" -ForegroundColor Yellow
    Write-Host "  Baribir keyingi qadam sinab ko'ring" -ForegroundColor Yellow
}

# ============================================================
# QADAM 5: LOYIJANI SETUP QILISH
# ============================================================
Write-Host "`n[5/5] Loyija sozlanmoqda..." -ForegroundColor Yellow

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
if (-not (Test-Path "$scriptDir\bin\test-vertex.php")) {
    Write-Host "⚠ Loyija topilmadi: $scriptDir" -ForegroundColor Yellow
    Write-Host "  Loyija papkasiga o'ting va qayta qo'llang" -ForegroundColor Yellow
} else {
    Write-Host "✓ Loyija topildi: $scriptDir" -ForegroundColor Green
    
    # Database yaratish
    if (-not (Test-Path "$scriptDir\data\maryam.db")) {
        Write-Host "  Database yaratilmoqda..." -ForegroundColor Gray
        $phpCheck = & cmd.exe /c "cd $scriptDir && php -r `"require 'src/bootstrap.php'; echo 'OK';`" 2>&1"
        if ($phpCheck -like "*OK*") {
            Write-Host "  ✓ Database yaratildi" -ForegroundColor Green
        } else {
            Write-Host "  ⚠ Database yaratishda muammo bo'ladi (PHP tekshirindan keyin)" -ForegroundColor Yellow
        }
    }
}

# ============================================================
# TUGALLASH
# ============================================================
Write-Host "`n╔════════════════════════════════════════════════════════════╗" -ForegroundColor Green
Write-Host "║              ✓ SETUP TUGADI — ISHGA TUSHIRING              ║" -ForegroundColor Green
Write-Host "╚════════════════════════════════════════════════════════════╝`n" -ForegroundColor Green

Write-Host "KEYINGI QADAMLAR:" -ForegroundColor Cyan
Write-Host "
1. PowerShell'ni QAYTADAN oching (yangi terminal)

2. Test qiling:
   cd $scriptDir
   php bin/test-vertex.php

   Agar ko'rinsa:
   ✓ Vertex AI initialized
   ✓ OK! Model: ...
   
   ⟹ BARAKA! \$300 trial ishlamoqda! 🎉

3. Copywriter ishga tushiring:
   php bin/copywriter.php

4. Brif bering:
   - Mavzu: Umra 2027 — erta bron
   - Turi: umra
   - Maqsad: lid
   - Tafsilotlar: (qo'shing)
   
   ⟹ AI matnlarni yozadi!

5. Variantlarni baholang:
   php bin/copywriter.php rate 1 5 'zo''r hook'

6. Web UI (brauzer):
   php -S localhost:8000 -t public
   ⟹ http://localhost:8000

MUAMMOLAR:
- 'gcloud: command not found' → PowerShell qaytadan oching
- 'Vertex xatosi (401)' → gcloud auth application-default login qayta qiling
- Boshqa xato → yuboring, tuzataman

" -ForegroundColor Yellow

Write-Host "📚 Dokumentatsiya: README_UZ.md va SETUP_WINDOWS.md" -ForegroundColor Gray
Write-Host "📧 Savol/muammo? → Yuboring`n" -ForegroundColor Gray

# PowerShell'ni ochiqligi uchun
Read-Host "Enter bosing, PowerShell'ni qaytadan oching va test qiling"
