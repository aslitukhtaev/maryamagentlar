# ⚡ QUICK START — 5 DAQIQADA ISHGA TUSHIRING

## Windows (Tavsiya Qilinadi)

### 1️⃣ Skriptni Yuklab Oling

Loyija papkasidan `setup-complete.ps1` faylini topasiz.

### 2️⃣ PowerShell (Admin) Oching

- Windows tugmasini bosing
- "PowerShell" yozasiz
- **"Windows PowerShell"** → o'ng-click
- **"Run as administrator"** → "Yes"

### 3️⃣ Skriptni Ishga Tushiring

PowerShell'da yozing (copy-paste qilib o'tkazing):

```powershell
powershell.exe -ExecutionPolicy Bypass -File "C:\Users\YourUsername\path\to\setup-complete.ps1"
```

Yoki — agar yo'l bilan muammo bo'lsa:
```powershell
cd C:\Users\YourUsername\Documents\maryamagentlar
powershell.exe -ExecutionPolicy Bypass -File "setup-complete.ps1"
```

### 4️⃣ Skript Ishlamoqda

Skript:
- ✅ Google Cloud SDK o'rnatadi (2-3 daqiqa)
- ✅ Brauzer ochnadi → Google login
- ✅ TOKEN fayli yaratiladi (`~/.config/gcloud/...`)
- ✅ Loyija sozlanadi

**Tugagach:**
```
✓ SETUP TUGADI — ISHGA TUSHIRING
```

### 5️⃣ PowerShell'ni Qaytadan Oching

Avvalgisini yoping, **yangi PowerShell** oching (Admin emas).

### 6️⃣ Test Qiling

```powershell
cd C:\Users\YourUsername\Documents\maryamagentlar
php bin/test-vertex.php
```

Agar chiqsa:
```
✓ Vertex AI initialized (gemini-2.5-flash)
Tekshirayotgan...
✓ OK! Model: gemini-3.8-flash
  Tokens: 100 -> 200
  Vaqt: 150ms
```

**BARAKA! $300 trial ishlamoqda!** ✅

### 7️⃣ Copywriter Ishga Tushiring

```powershell
php bin/copywriter.php
```

So'ralar chiqadi:
```
Mavzu: Umra 2027 — erta bron aksiyasi
Turizm turi:
  1) Umra / Haj
  2) Ichki turizm
  ...
```

Javoblar bergsiz → **AI yozadi** → natijalar ko'rsatiladi.

---

## macOS / Linux

```bash
# Agar gcloud o'rnatilmagan bo'lsa
brew install google-cloud-sdk        # macOS
# yoki
curl https://sdk.cloud.google.com | bash  # Linux

# Login
gcloud auth application-default login

# Test
cd path/to/maryamagentlar
php bin/test-vertex.php

# Ishga tushiring
php bin/copywriter.php
```

---

## Mock AI (Kutmasdan Demo)

Gemini'ni kutmay, darhol natijalarni ko'rish uchun:

```powershell
php bin/demo.php
```

**Natija:**
```
✓ TAYYOR!
5 hook, 4 variant
O'rtacha ball: 9/10

=== COPYWRITER NATIJASI ===
KATTA G'OYA: ...
...
```

---

## Web UI (Brauzer)

```powershell
cd path/to\maryamagentlar
php -S localhost:8000 -t public
```

Browser: **http://localhost:8000**

Shu yerda:
- Yangi brif yarating
- Variantlarni ko'ring
- Baholang

---

## Muammolar & Yechimlar

| Muammo | Yechim |
|---|---|
| `gcloud: command not found` | PowerShell qaytadan oching |
| `Vertex xatosi (401)` | `gcloud auth application-default login` qayta qiling |
| `Invoke-WebRequest xatosi` | Internet tekshiring, yoki [linki](https://dl.google.com/dl/cloudsdk/channels/rapid/google-cloud-sdk-windows-x86_64-bundled-python.zip) qo'lda yuklab oling |
| `php: command not found` | PHP o'rnatilgan ekanligini tekshiring (`php -v`) |
| Boshqa xato | Skrin-shot yuboring |

---

## Keyingi Qadam

✅ **Copywriter bilan 5-10 ta brif qo'llab ko'ring**
- Variantlarni baholang (rate buyrug'i)
- AI o'rganadi, keyingi safar yaxshiroq yozadi

✅ **Keyingi agentlar** (2 hafta keyin):
- Scriptwriter (Reels ssenariy)
- Carousel (Instagram karusel)
- Manager (hammasini boshqaradi)

✅ **Telegram Bot** — Copywriter'ni bot ichiga ulang

---

**Tugadi!** 🎉 Ishga tushiruvda muammo bo'lsa — yuboring!
