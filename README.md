# Maryam Travel — Marketing AI jamoasi

Maryam Travel marketing bo'limi uchun AI agentlar jamoasi. To'liq **PHP** da yozilgan,
ma'lumotlar bazasi — loyiha ichidagi bitta **SQLite** fayl (`data/maryam.db`).
AI sifatida **Google Gemini** ishlatiladi.

## Agentlar

| Agent | Holat | Vazifasi |
|---|---|---|
| ✍️ Copywriter | **tayyor** | Post matnlari va target reklama matnlari |
| 🎬 Scriptwriter | keyingi | Reels ssenariysi |
| 🗂 Carousel | keyingi | Instagram karusel slaydlari |
| 🧠 Marketing Manager | keyingi | Brifni tahlil qilib, barcha agentlarni boshqaradi |
| 🎨 Graphic designer | keyingi | Tayyor rasm generatsiya qiladi |

## O'rnatish

Kerak: **PHP 8.1+** (`pdo_sqlite`, `curl`, `mbstring` kengaytmalari bilan). Composer shart emas.

```bash
cp .env.example .env
# .env faylini ochib, GEMINI_API_KEY= ga kalitingizni yozing
# (kalit: https://aistudio.google.com/apikey)
```

Keyin `config/brand.php` faylini oching va Maryam Travel haqidagi **haqiqiy** faktlarni to'ldiring
(telefon, Telegram, litsenziya, tajriba, afzalliklar). Agentlar faktlarni o'zidan to'qimaydi —
bo'sh joylar matnda `[TELEFON]` kabi joy-belgi bo'lib chiqadi.

## Ishga tushirish

**Terminalda:**
```bash
php bin/copywriter.php                        # brif so'raladi, matnlar yoziladi
php bin/copywriter.php rate 12 5 "hook zo'r"  # 12-variantga 5 baho berish
php bin/copywriter.php show 3                 # 3-brif natijasini qayta ko'rish
```

**Brauzerda (qulayroq):**
```bash
php -S localhost:8000 -t public
```
va `http://localhost:8000` ni oching. (Faqat o'z kompyuteringiz uchun — parol himoyasi yo'q.)

Natijalar `output/<sana>-<mavzu>-<id>/copywriter.txt` ga ham saqlanadi.

## Copywriter qanday ishlaydi

Bitta "yozib ber" so'rovi emas — tajribali jamoadek **3 bosqichda**:

1. **Strategiya** — auditoriya portreti, og'riqlar, istaklar, e'tirozlar, "katta g'oya", 3 ta turli burchak.
2. **Yozish** — 5 ta hook, 2 ta post (Instagram/Telegram), 2 ta Meta reklama (sarlavha ≤40, tavsif ≤30 belgi).
   Har bir variant boshqa burchak va freymvorkda (PAS, AIDA, BAB, 4U, hikoya).
3. **Tahrir** — "bosh muharrir" har variantni 6 mezon (hook, aniqlik, tonga moslik, ishontirish, CTA, til)
   bo'yicha 1-10 baholaydi va yaxshilaydi. Bali 8 dan past yoki qoida buzgan variant yana bir marta tahrirlanadi.

Qo'shimcha himoyalar:
- **Kod tekshiruvi** — belgilar limiti, taqiqlangan iboralar (`never_say`), `!!` kabi narsalarni PHP o'zi aniq tekshiradi.
- **To'qima faktlarga qarshi** — narx, sana, joylar soni berilmagan bo'lsa `[NARX]` kabi joy-belgi qo'yiladi.
- **Meta reklama siyosati** — "Siz musulmonmisiz?" kabi shaxsiy xususiyatni da'vo qiluvchi gaplar taqiqlangan.
- **Sizdan o'rganadi** — variantlarga bergan baholaringiz (1-5) va izohlaringiz saqlanadi. Keyingi safar shu
  turizm turi bo'yicha yuqori baholanganlar "yaxshi namuna", past baholanganlar "yomon namuna" sifatida agentga beriladi.

## Sozlash (kod yozmasdan)

| Fayl | Nima o'zgartiriladi |
|---|---|
| `config/brand.php` | Brend faktlari, aloqa, afzalliklar, taqiqlangan iboralar |
| `config/tones.php` | Har turizm turi (Umra, ichki, inbound, outbound) uchun auditoriya, ohang, nima qilish/qilmaslik |
| `prompts/copywriter/*.md` | Agentning "miyasi" — strategiya, yozish va tahrir yo'riqnomalari (o'zbek tilida) |
| `.env` | API kalit va modellar (`GEMINI_MODEL`, `GEMINI_MODEL_SMART`) |

## Loyiha tuzilishi

```
bin/copywriter.php        terminal orqali ishga tushirish
public/index.php          web interfeys
src/bootstrap.php         hammasini ulaydigan fayl
src/Agents/Copywriter.php copywriter agenti
src/Gemini.php            Gemini API mijozi
src/Store.php             bazaga yozish/o'qish
src/Database.php          SQLite jadvallari (avtomatik yaratiladi)
src/Brief.php             brifni tekshirish
src/Output.php            natijalarni papkaga saqlash
config/                   brend va ton sozlamalari
prompts/                  agentlarning yo'riqnomalari
data/maryam.db            ma'lumotlar bazasi (avtomatik yaratiladi)
output/                   tayyor natijalar
```

Har bir agent mustaqil klass — keyinchalik Telegram bot yoki ERP'dan ham shunday chaqiriladi:

```php
require 'src/bootstrap.php';
['ai' => $ai, 'store' => $store, 'brand' => $brand, 'tones' => $tones] = app();
$brief = Maryam\Brief::normalize(['topic' => '...', 'tourism_type' => 'umra', 'goal' => 'lid'], $tones);
$result = (new Maryam\Agents\Copywriter($ai, $store, $brand, $tones))->run($brief);
```

Bazadagi `agent_runs` jadvalida har bir AI so'rovi, javobi, token soni va vaqti saqlanadi —
natija g'alati chiqsa, sababini shu yerdan topish mumkin.
