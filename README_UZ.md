# Maryam Travel — Marketing AI Jamoasi (O'zbek)

Turizm marketing uchun **Copywriter AI agenti** — 3-bosqichli, muharrir bilan, sizning baholaringizdan o'rganadi.

## Tez Boshlash (5 daqiqa)

### 1. Google Cloud SDK o'rnatish (Windows)

`SETUP_WINDOWS.md` ni oching va qadam-qadam amal qiling.

**Qisqasi:**
```powershell
# PowerShell (Admin)
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser

$url = "https://dl.google.com/dl/cloudsdk/channels/rapid/google-cloud-sdk-windows-x86_64-bundled-python.zip"
Invoke-WebRequest -Uri $url -OutFile "$env:TEMP\gcloud.zip"
Expand-Archive -Path "$env:TEMP\gcloud.zip" -DestinationPath "C:\Program Files\Google" -Force
& "C:\Program Files\Google\cloud-sdk\install.bat" --quiet --path-update=true

# PowerShell'ni qaytadan oching (Admin emas)
gcloud auth application-default login
```

### 2. Test qiling

```bash
cd path/to/maryamagentlar
php bin/test-vertex.php
```

Agar "✓ OK!" chiqsa — **$300 trial'dan ishlayapti!**

### 3. Copywriter ishga tushiring

```bash
php bin/copywriter.php
```

Brif so'raladi. Javoblar bergsangiz, AI 3 bosqichda:
1. **Strategiya:** auditoriya, og'riqlar, burchaklar
2. **Yozish:** 5 hook + 2 post + 2 reklama
3. **Tahrir:** muharrir har variantni baholaydi va yaxshilaydi

## Marketing bo'limi — kontentni avtomatlashtirish

Agentlar bitta marketing bo'limi bo'lib ishlaydi va umumiy bilimdan foydalanadi:

| Kim | Nima qiladi |
|---|---|
| **Manager** (bo'lim boshlig'i) | Telegram'da siz bilan gaplashadi, topshiriqni kerakli mutaxassisga beradi |
| **Kontent-strateg** | Har hafta mavsum, katalog va oldingi haftalarga qarab kontent-reja tuzadi |
| **Copywriter** | Har band uchun tayyor matn: post, Reels ssenariysi, karusel slaydlari, reklama |
| **Dizayner** | Matnga rasm tayyorlaydi |

### Sifatni belgilaydigan 3 ta narsa (albatta to'ldiring)

1. **`config/brand.php`** — telefon, Telegram, litsenziya, faktlar, afzalliklar (USP)
2. **`config/products.php`** — sotuvdagi turlar: narx, sana, mehmonxona, aksiya.
   Agentlar shu yerdan oladi — sizdan so'ramaydi va matnda `[NARX]` qolmaydi.
3. **Sizning uslubingiz** — kanalingizdagi eng yaxshi 10-20 ta postni botga **forward** qiling.
   Agentlar shu ohangda yozishni o'rganadi.

### Haftalik kontent paketi

- Har **dushanba 09:00** da bot o'zi reja + tayyor matnlarni yuboradi (`config/marketing.php`)
- Yoki botga: `/reja` yoki `/reja Ramazon Umrasiga urg'u ber`
- Yoki oddiy so'z bilan: "shu haftaga nima joylaymiz?"
- Terminal: `php bin/reja.php` (sinov: `php bin/reja.php --mock`)

`config/marketing.php` da: haftasiga nechta kontent, formatlar, yo'nalishlar ulushi va
mavsumiy kalendar (Ramazon, hayitlar, Navro'z, ta'tillar — har biri necha kun oldin reklama
boshlanishi bilan). Hijriy sanalar taxminiy — kerak bo'lsa tuzating.

## Arxitektura

```
bin/
  bot.php              Telegram bot — marketing bo'limi bilan suhbat
  reja.php             Haftalik kontent-reja + tayyor matnlar
  copywriter.php       CLI — haqiqiy Gemini bilan
  demo.php             Demo — mock AI bilan (tez)
  test-vertex.php      Vertex AI test

src/
  Agents/
    Copywriter.php     Matn yozuvchi (3 bosqich)
    ContentPlanner.php Kontent-strateg (haftalik reja)
    Manager.php        Bo'lim boshlig'i (Telegram suhbat)
    GraphicDesigner.php Rasm
  Marketing.php        Umumiy bilim: brend, katalog, kalendar
  Gemini.php           Developer API (Free Tier limitli)
  GeminiVertex.php     Vertex AI (Cloud trial bilan)
  GeminiMock.php       Mock AI — sinov va demo
  Store.php            SQLite bazaga yozish/o'qish
  Database.php         Schema va migrations
  Brief.php            Brif tekshirish
  Output.php           Natijalarni papkaga saqlash

prompts/copywriter/
  strategy.md          Strategist yo'riqlonmasi
  write.md             Copywriter yo'riqlonmasi
  edit.md              Muharrir yo'riqlonmasi

config/
  brand.php            Maryam Travel faktlari
  products.php         Sotuvdagi turlar katalogi
  marketing.php        Kontent-reja sozlamalari + mavsumiy kalendar
  tones.php            Har turizm turi uchun ton profili

public/
  index.php            Web UI (brauzer)

data/
  maryam.db            SQLite (avtomatik yaratiladi)
```

## Copywriter Nima Qiladi?

**Bitta brif → 4 ta readyy marketing matni:**

1. **5 ta Hook** (Reels/karusel boshlanishi)
2. **2 ta Post** (Instagram/Telegram)
3. **2 ta Target Reklama** (Facebook/Instagram Ads)

**Har biri boshqa burchak va freymvork'da**, muharrir tarafidan 1-10 ball bilan baholangan.

### Misal Brif
```
Mavzu: Umra 2027 — erta bron aksiyasi
Turi: Umra / Haj
Maqsad: Lid yig'ish
Tafsilotlar: Jo'nash fevral/mart, 14 kun, o'zbek gid, $200 chegirma
```

### Natija
```
KATTA G'OYA: Siz faqat yolni yo'qotmasdan, ruhingizni topasiz

HOOKLAR (5 ta)
— Ota-onangiz Haramda birinchi marta...
— Tilni bilmasdan ham...
— [...]

VARIANTLAR (4 ta):
1. POST [Ishonch burchagi | PAS freymvork] — 9/10 ⭐
   Hook + Body + CTA + Hashtags

2. AD [Urg'onjillik | 4U freymvork] — 9/10 ⭐
   Sarlavha (≤40 belgi) + Tavsif (≤30) + CTA Tugma
```

## Sozlash (Hozir)

### 1. Vertex AI (Google Cloud $300 trial)

**Qo'llaniladigan joylar:**
- `bin/copywriter.php`
- `php -S localhost:8000 -t public` (web)

**O'rnatish:** `SETUP_WINDOWS.md` → `gcloud auth application-default login`

### 2. Mock AI (Sinov va demo)

**Qo'llaniladigan joylar:**
- `bin/demo.php` — darhol natijalar, xech kutmay

```bash
php bin/demo.php
```

### 3. Developer API (eski, Free Tier limitli — tavsiya qilinmadi)

```bash
GEMINI_API_KEY=your_key php bin/copywriter.php
```

## Variantlarni Baholash

Copywriter yakuniy variantlarni sizga ko'rsatadi. Siz 1-5 baho bergsiz:

```bash
php bin/copywriter.php rate 123 5 "zo'r hook, aniq CTA"
```

Keyingi safar shu turizm turi bo'yicha yuqori baholanganlari "namuna" bo'lib beriladi.

## Web UI

```bash
php -S localhost:8000 -t public
```

Browser'da: http://localhost:8000
- Yangi brif yarating
- Variantlarni ko'ring
- Baholang va izoh yozing

## Ma'lumotlar Bazasi

**SQLite** (`data/maryam.db`), avtomatik yaratiladi:

| Jadval | Nima saqlaydi |
|---|---|
| `briefs` | Kiritilgan briflar |
| `agent_runs` | AI chaqiruvlari, tokenlar, vaqt |
| `agent_results` | Agentlar natijalari (JSON) |
| `copy_variants` | Copywriter variantlari + sizning baholar |

```bash
sqlite3 data/maryam.db "select id, topic, created_at from briefs;"
```

## Keyingi Agentlar

**Hozir:** Copywriter ✅
**Keyingi:**
- Scriptwriter — Reels ssenariy (hook-first, freymvork)
- Carousel Agent — Instagram karusel (slayd-slayd)
- Graphic Designer — Rasm generatsiya (Gemini Vision)
- Marketing Manager — Barcha agentlarni boshqaradi

## Muammoni Tuzatish

**"gcloud: command not found"**
→ PowerShell'ni qaytadan oching (Admin emas)

**"Vertex AI xatosi (401)"**
→ `gcloud auth application-default login` qayta qiling

**"Free Tier limit"**
→ Vertex AI'ga o'ting (`SETUP_WINDOWS.md`)

**"Boshqa xato"**
→ Skrin-shot yuboring, tuzataman

## Foydalanish Yo'llari

### CLI (Terminal)
```bash
php bin/copywriter.php              # Brif so'rash + yozish
php bin/copywriter.php rate 12 5 "izoh"  # Baholash
php bin/demo.php                    # Mock AI demo
php bin/test-vertex.php             # Vertex AI test
```

### Web (Browser)
```bash
php -S localhost:8000 -t public     # http://localhost:8000
```

### Telegram Bot (keyinchi)
```php
require 'src/bootstrap.php';
$brief = [...];
$result = (new Copywriter($ai, $store, $brand, $tones))->run($brief);
// Matnlarni Telegram'ga yuborasiz
```

## Kod Tuzilishi

Har bir fayl **mustaqil va qo'llanishi oson**:
- Copywriter → Manager/Bot'dan chaqirilishi mumkin
- GeminiVertex ↔ GeminiMock — o'rniga almashasiz
- Store → asosiy DB operatsiyalar

Birinchi muhammiy: **Brand faktlari** (`config/brand.php`) — agent sifati shunga bog'liq.

## Litsenziya & Attribution

Kod o'zbek tilida, qayta foydalanish uchun tayyortir.

---

**Boshlang:** `SETUP_WINDOWS.md` → `gcloud auth application-default login` → `php bin/copywriter.php`

**Savol?** Repo'da issue oching yoki menga yuboring.
