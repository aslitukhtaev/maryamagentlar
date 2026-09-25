<?php
/**
 * BOSHLANG'ICH SHABLONLAR VA QOIDALAR — bazaga faqat birinchi ishga tushishda yoziladi.
 * Keyin hammasini web'dagi "O'qitish markazi"dan tahrirlaysiz (bu faylni emas).
 *
 * Shablon tuzilmasidagi {KATTA_HARF} — slot: agent uni katalog/brif faktlari bilan to'ldiradi.
 */

return [
    'templates' => [
        [
            'name' => 'Qaynoq tur — sotuv posti',
            'format' => 'post', 'tourism_type' => 'outbound', 'stage' => 'sotuv',
            'structure' => <<<TXT
{HOOK — manzil haqida his-tuyg'u yoki savol, 1 qator, 125 belgigacha}

🔥 {JOY} — {NARX} dan
📅 {SANALAR} · {DAVOMIYLIK}
🏨 {MEHMONXONA}

✅ Narxga kiradi:
— {KIRADI_1}
— {KIRADI_2}
— {KIRADI_3}

{1-2 gap: kimga mos va nega aynan hozir}

📩 Direct'ga "{KALIT_SO'Z}" deb yozing — menejer 10 daqiqada narx va bo'sh joylarni yuboradi.
📞 55-303-22-22
TXT,
            'example' => '',
            'rules' => "Narx, sana, mehmonxona va 'narxga kiradi' — faqat katalogdan. Bitta asosiy CTA (kalit so'z). 3-6 emoji, faqat qator boshida.",
            'design' => "1080x1350. Fon: manzilning eng taniqli, yorqin fotosi. Yuqorida katta oq sarlavha — JOY nomi (qalin sans-serif). "
                      . "Pastki chapda oltin rangli narx belgisi: '{NARX} dan'. Eng pastda to'q yashil lenta: sanalar · 55-303-22-22 · logotip.",
        ],
        [
            'name' => "Haftaning qaynoq narxlari — ro'yxat",
            'format' => 'post', 'tourism_type' => 'outbound', 'stage' => 'sotuv',
            'structure' => <<<TXT
{HOOK — masalan: "Bu hafta qayerga uchish arzon? 👇"}

🇹🇷 {JOY_1} — {NARX_1} · {SANA_1}
🇪🇬 {JOY_2} — {NARX_2} · {SANA_2}
🇻🇳 {JOY_3} — {NARX_3} · {SANA_3}
{... katalogdagi faol turlar, 5-9 ta}

Narxlarga: {UMUMIY_KIRADI}

⏳ Narxlar joylar tugaguncha amal qiladi.
📩 Qaysi biri qiziq? Direct'ga manzil nomini yozing.
TXT,
            'rules' => "Faqat katalogdagi faol turlar. Har qatorda bayroq emoji + joy + narx + sana. Sanasi yo'q turni ro'yxatga kiritma.",
            'design' => "1080x1350. Bitta umumiy fon (samolyot oynasidan ko'rinish yoki dengiz). Markazda oq karta — ro'yxat: bayroq, shahar, narx oltin rangda. "
                      . "Sarlavha: 'QAYNOQ NARXLAR · {HAFTA}'. Pastda yashil lenta + telefon + logotip.",
        ],
        [
            'name' => 'Mijoz sharhi — Reels',
            'format' => 'reels', 'tourism_type' => '', 'stage' => 'ishonch',
            'structure' => <<<TXT
SSENARIY (20-35 s):
0-3 s: mijozning eng yorqin gapi (subtitr bilan) — hook
3-15 s: qayerga bordi, nima yoqdi — 2-3 aniq detal (mehmonxona, gid, ovqat)
15-25 s: Maryam Travel bilan nima oson bo'ldi (hujjat, transfer, qo'llab-quvvatlash)
25-30 s: mijoz tavsiyasi + ekranda CTA

CAPTION:
{HOOK — mijoz gapidan iqtibos}
{2-3 gap: mijoz kim, qayerdan qaytdi, nima qoldi yodida}
{CTA: "Siz ham {JOY}ga bormoqchimisiz? Direct'ga '{KALIT_SO'Z}' deb yozing"}
TXT,
            'rules' => "Mijoz gaplarini to'qima — brifda berilgan haqiqiy gapdan foydalan, bo'lmasa [MIJOZ GAPI] joy-belgi qo'y.",
            'design' => "Muqova 1080x1920: mijozning manzildagi fotosi. Yuqori uchdan birda sarlavha: '{JOY}: mijozimiz gapiradi'. Pastda yashil-oltin plashka + logotip.",
        ],
        [
            'name' => 'Taqqoslash: X yoki Y — karusel',
            'format' => 'karusel', 'tourism_type' => 'outbound', 'stage' => 'qamrov',
            'structure' => <<<TXT
1-slayd: "{X} yoki {Y}: {BUDJET} ga qaysi biri?" (hook)
2-slayd: Narx — {X}: {NARX_X} / {Y}: {NARX_Y}
3-slayd: Qachon borish yaxshi (mavsum, ob-havo)
4-slayd: Kimga mos (oila / juftlik / do'stlar)
5-slayd: Viza va parvoz vaqti
6-slayd: Xulosa: "{X} — agar ..., {Y} — agar ..."
7-slayd: CTA: "Izohda yozing: {X} yoki {Y}? Batafsil narx — Direct'da"

CAPTION: {qisqa kirish + savol + CTA}
TXT,
            'rules' => "Faktlarni (narx, viza) faqat katalog/bilimlardan ol. Izoh yozdiradigan savol bilan tugat.",
            'design' => "Split-ekran: chapda X, o'ngda Y fotosi, o'rtada oltin 'VS'. Barcha slaydlar bir xil panjara: yuqorida sarlavha, pastda yashil lenta.",
        ],
        [
            'name' => "Foydali maslahatlar — karusel",
            'format' => 'karusel', 'tourism_type' => '', 'stage' => 'ishonch',
            'structure' => <<<TXT
1-slayd: "{JOY}ga borishdan oldin bilishingiz kerak bo'lgan {N} narsa" (hook)
2-6 slaydlar: har birida bitta maslahat — sarlavha + 1-2 gap tushuntirish
Oxirgi slayd: "Saqlab qo'ying 📌 · Tur tanlashda yordam kerakmi? Direct'ga yozing"

CAPTION: {qisqa kirish, "saqlab qo'ying" chaqiruvi, CTA}
TXT,
            'rules' => "Maslahatlar amaliy va aniq bo'lsin (valyuta, ob-havo, kiyim, viza, xavfsizlik). Umumiy gaplar yo'q.",
            'design' => "Oq yoki och fon, har slaydda katta raqam (oltin), qisqa sarlavha, kichik ikonka. Birinchi slayd — manzil fotosi.",
        ],
        [
            'name' => 'Menejer tavsiya qiladi — Reels',
            'format' => 'reels', 'tourism_type' => 'outbound', 'stage' => 'qamrov',
            'structure' => <<<TXT
SSENARIY (menejer kameraga gapiradi, 20-40 s):
0-3 s: hook — kutilmagan fakt yoki savol (ekranda katta yozuv)
3-25 s: 3 ta aniq fakt/sabab (har biri ekranda qisqa yozuv bilan)
25-35 s: taklif: {JOY} — {NARX} dan, {SANA}
oxiri: "Direct'ga '{KALIT_SO'Z}' deb yozing"

CAPTION: {hook takrori + 2-3 gap + CTA}
TXT,
            'rules' => "Clickbait va baqiruvchi sarlavhalar yo'q. Menejer oddiy, ishonchli tilda gapiradi.",
            'design' => "Muqova 1080x1920: menejer portreti, yuqori uchdan birda 2-4 so'zli sarlavha (oq, qalin), bir xil shrift va yashil plashka.",
        ],
        [
            'name' => 'Target reklama — qaynoq tur',
            'format' => 'reklama', 'tourism_type' => 'outbound', 'stage' => 'sotuv',
            'structure' => <<<TXT
ASOSIY MATN:
{HOOK — og'riq yoki orzu, 1 qator}
{JOY} — {NARX} dan · {SANALAR}
✅ {KIRADI_1} ✅ {KIRADI_2} ✅ {KIRADI_3}
{Ishonch: 7 yil, 23 000 mijoz, 4 ofis}
{CTA: "Xabar yuboring — narx va bo'sh joylarni yuboramiz"}

HEADLINE (≤40): {JOY} — {NARX} dan
DESCRIPTION (≤30): {qisqa foyda}
TUGMA: Send Message
TXT,
            'rules' => "Meta siyosati: shaxsiy xususiyatga murojaat yo'q. Soxta taqchillik yo'q.",
            'design' => "1080x1080 va 1080x1920. Manzil fotosi, katta narx oltin plashkada, pastda logotip. Matn rasmning 20% idan oshmasin.",
        ],
        [
            'name' => 'Umra paketi — ishonch va sotuv',
            'format' => 'post', 'tourism_type' => 'umra', 'stage' => 'sotuv',
            'structure' => <<<TXT
{HOOK — sokin, hurmatli: ota-onani yoki o'zini ziyoratga tasavvur qilish}

🕋 {PAKET_NOMI} — {NARX} dan
📅 {SANALAR} · {DAVOMIYLIK}
🏨 Makka: {MEHMONXONA_MAKKA} · Madina: {MEHMONXONA_MADINA}

Siz uchun tayyor: {KIRADI}

{Ishonch: tajriba, gid, tashvishni yechish}

📞 55-303-22-22 · Direct'ga "UMRA" deb yozing
TXT,
            'rules' => "Sokin ton, maksimum 2-3 emoji. Oyat/hadis to'qima.",
            'design' => "Sokin, iliq ranglar: Madina masjidi tong yorug'ida (odamlar uzoqdan). Oltin ingichka ramka, pastda yashil lenta + telefon.",
        ],
    ],

    // [agent, qoida]  agent: all | copywriter | planner | designer | manager
    'rules' => [
        ['all', "Har bir sotuv kontentida 5 element bo'lsin: joy, sana, narx ('... dan'), narxga nima kiradi, bitta aniq CTA."],
        ['all', "Clickbait va baqiruvchi sarlavhalar ishlatma ('SHU VIDEONI KO'RMASDAN BORMANG!' kabi) — biz professional, ishonchli agentlikmiz."],
        ['copywriter', "CTA — bitta harakat: Direct'ga kalit so'z yozish (masalan 'VIETNAM') yoki call-markaz 55-303-22-22."],
        ['copywriter', "Yalang'och narx yozma: narx yonida doim sana va narxga nima kirishi bo'lsin."],
        ['copywriter', "Ishonch omillarini (7 yil, 23 000 mijoz, 4 ofis) sotuv matnlarida qisqa eslat, lekin har gapda emas."],
        ['planner', "Haftada kamida 1 ta ishonch kontenti bo'lsin: mijoz sharhi, ofis/jamoa, sahna ortidan."],
        ['planner', "Har band uchun mos shablonni tanla (template_id) — sahifa bir xil, professional ko'rinsin."],
        ['designer', "Brend uslubi: to'q yashil va oltin ranglar, oq matn, bitta qalin sans-serif shrift. Har vizualda pastda yashil lenta + logotip."],
    ],
];
