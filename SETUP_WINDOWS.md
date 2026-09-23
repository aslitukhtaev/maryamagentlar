# Windows Sozlash — Google Cloud SDK + Vertex AI

## Qadam 1: PowerShell'ni Administrator sifatida oching

- Windows tugmasini bosing
- "PowerShell" qidirasiz
- **"Windows PowerShell"** ga o'ng-click
- **"Run as administrator"** tanlang
- "Yes" bosing

## Qadam 2: Execution Policy o'zgarting

PowerShell'da yozing:
```powershell
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
```

"Y" bosing agar so'rasa.

## Qadam 3: Google Cloud SDK o'rnatish

PowerShell'da yozing:

```powershell
# 1. Temp papkaga yuklab oling
$url = "https://dl.google.com/dl/cloudsdk/channels/rapid/google-cloud-sdk-windows-x86_64-bundled-python.zip"
$out = "$env:TEMP\gcloud.zip"
$installDir = "C:\Program Files\Google\Cloud SDK"

Write-Host "Yuklab olinmoqda..." -ForegroundColor Green
Invoke-WebRequest -Uri $url -OutFile $out -UseBasicParsing

# 2. Ochib oling
Write-Host "Ochilmoqda..." -ForegroundColor Green
Expand-Archive -Path $out -DestinationPath "C:\Program Files\Google" -Force

# 3. O'rnatish skriptini ishga tushiring
Write-Host "O'rnatilmoqda..." -ForegroundColor Green
& "C:\Program Files\Google\cloud-sdk\install.bat" --quiet --path-update=true

Write-Host "`nO'rnatish tugadi! PowerShell'ni qaytadan oching." -ForegroundColor Green
```

Bu 2-3 daqiqa davom etadi.

## Qadam 4: PowerShell'ni qaytadan oching

Avvalgisini yoping, **yangi PowerShell** oching (Admin bo'lishi shart emas).

## Qadam 5: Google login

PowerShell'da yozing:

```powershell
gcloud auth application-default login
```

**Nima bo'ladi:**
- Brauzer avtomatik ochnadi
- Google login sahifasi chiqadi
- Email'ingizga kiring
- **"Allow"** tugmasini bosing
- Terminal'ga qaytasiz

## Qadam 6: Test qiling

PowerShell'da yozing:

```powershell
cd C:\Users\YourUsername\Documents\maryamagentlar
php bin/test-vertex.php
```

**Agar chiqsa:**
```
✓ Vertex AI initialized
Tekshirayotgan...
✓ OK! Model: ...
```

**BARAKA! $300 trial ishlamoqda!** ✅

## Qadam 7: Copywriter ishga tushiring

```powershell
php bin/copywriter.php
```

Brif so'raladi:
- Mavzu: "Umra 2027 — erta bron aksiyasi"
- Turi: "umra"
- Maqsad: "lid"
- Keyin **tafsilotlar** qo'shing
- Matnlar yoziladi

---

## Agar xato chiqsa

**"gcloud: command not found"** → PowerShell'ni qaytadan oching

**"Invoke-WebRequest xatosi"** → Internet tekshiring yoki shu link'ni qo'lda brauzerdan yuklab oling:
https://dl.google.com/dl/cloudsdk/channels/rapid/google-cloud-sdk-windows-x86_64-bundled-python.zip

Keyin `C:\Program Files\Google` papkasiga chiqarib oling va:
```powershell
& "C:\Program Files\Google\cloud-sdk\install.bat" --quiet
```

**Boshqa xato** → skrin-shot bilan yuboring, tuzataman.

---

## Qisqasi

1. ✅ PowerShell Admin
2. ✅ Set-ExecutionPolicy
3. ✅ Google Cloud SDK yukla + o'rnat
4. ✅ PowerShell qaytadan oching
5. ✅ `gcloud auth application-default login`
6. ✅ `php bin/test-vertex.php`
7. ✅ `php bin/copywriter.php`

**Tugadi! $300 trial'dan Copywriter ishlayapti!**
