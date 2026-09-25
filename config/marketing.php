<?php
/**
 * MARKETING BO'LIMI SOZLAMALARI — kontent-reja qanday tuziladi.
 *
 * Kontent-strateg agenti har hafta shu sozlamalar, mahsulotlar katalogi va quyidagi
 * mavsumiy kalendar asosida haftalik reja tuzadi, Copywriter esa har bir band uchun
 * tayyor matn yozadi. Natija avtomatik Telegram'ga keladi.
 */

return [
    // Haftasiga nechta kontent chiqaramiz va qaysi kunlari
    'posts_per_week' => 5,
    'posting_days'   => ['Dushanba', 'Seshanba', 'Chorshanba', 'Payshanba', 'Juma'],

    // Qaysi formatlar ishlatiladi (reja shu formatlar orasida taqsimlanadi)
    'formats' => [
        'post'     => 'Instagram/Telegram posti (rasm + matn)',
        'reels'    => 'Reels / qisqa video (hook + ssenariy g\'oyasi + caption)',
        'karusel'  => 'Karusel (5-8 slayd + caption)',
        'reklama'  => 'Meta target reklama',
    ],

    // Yo'nalishlar ulushi (taxminan). Kompaniyangiz asosiy daromadiga moslang.
    'mix' => [
        'umra'     => 50,
        'outbound' => 25,
        'ichki'    => 20,
        'inbound'  => 5,
    ],

    // Haftalik reja qachon avtomatik tuzilib, Telegram'ga yuboriladi (bot ishlab turgan bo'lsa)
    'weekly_plan' => [
        'enabled' => true,
        'weekday' => 1,   // 1 = dushanba ... 7 = yakshanba
        'hour'    => 9,   // soat 09:00 dan keyin
    ],

    /*
     * MAVSUMIY KALENDAR. Turizmda mahsulot voqeadan OLDIN sotiladi, shuning uchun
     * 'lead_days' — voqeadan necha kun oldin reklamani boshlash kerakligi.
     *   'date'       => aniq sana (Y-m-d) — hijriy sanalar har yili siljiydi, shuning uchun aniq yil bilan
     *   'every_year' => har yili takrorlanadi (m-d)
     * Hijriy sanalar TAXMINIY (oy ko'rinishiga qarab ±1 kun) — kerak bo'lsa tuzating.
     */
    'events' => [
        ['every_year' => '01-01', 'name' => 'Yangi yil', 'types' => ['outbound', 'ichki'], 'lead_days' => 50,
         'note' => "Yangi yil turlari noyabrdan sotiladi; qishki ta'til bilan birga"],
        ['every_year' => '12-28', 'name' => "Qishki maktab ta'tili (taxminan 28-dek – 10-yan)", 'types' => ['ichki', 'outbound'], 'lead_days' => 40,
         'note' => "Oilaviy qisqa safarlar, tog'-chang'i (Amirsoy, Chimyon)"],
        ['every_year' => '03-08', 'name' => '8-mart', 'types' => ['outbound', 'ichki'], 'lead_days' => 30,
         'note' => "Sovg'a sifatida sayohat: onaga, turmush o'rtog'iga"],
        ['every_year' => '03-21', 'name' => "Navro'z (va bahorgi ta'til)", 'types' => ['ichki', 'inbound', 'outbound'], 'lead_days' => 35,
         'note' => "Uzun dam olish kunlari — qisqa turlar"],
        ['every_year' => '06-01', 'name' => "Yozgi ta'til boshlanishi", 'types' => ['outbound', 'ichki'], 'lead_days' => 70,
         'note' => "Yozgi dengiz turlari aprel-maydan sotiladi"],
        ['every_year' => '09-01', 'name' => 'Mustaqillik kuni', 'types' => ['ichki'], 'lead_days' => 20,
         'note' => "Vatanparvarlik ohangi, O'zbekiston bo'ylab turlar"],
        ['every_year' => '04-15', 'name' => "Inbound mavsum cho'qqisi (aprel–may)", 'types' => ['inbound'], 'lead_days' => 90,
         'note' => 'Xorijlik sayyohlar bahorda keladi — oldindan bron'],
        ['every_year' => '09-20', 'name' => "Inbound kuzgi mavsum (sentabr–oktabr)", 'types' => ['inbound'], 'lead_days' => 60,
         'note' => ''],

        // Hijriy sanalar (taxminiy)
        ['date' => '2026-12-10', 'name' => "Rajab oyi (Umra uchun fazilatli oylar boshlanishi)", 'types' => ['umra'], 'lead_days' => 45,
         'note' => 'Rajab–Sha\'bon–Ramazon: Umraga talab eng yuqori davr'],
        ['date' => '2027-02-08', 'name' => 'Ramazon 2027 boshlanishi', 'types' => ['umra'], 'lead_days' => 90,
         'note' => "Ramazonda Umra — eng qimmat va eng talabgir mavsum, joylar tez tugaydi"],
        ['date' => '2027-03-10', 'name' => "Ramazon hayiti 2027", 'types' => ['umra', 'ichki'], 'lead_days' => 30,
         'note' => 'Tabrik posti + hayitdan keyingi Umra guruhlari'],
        ['date' => '2027-05-16', 'name' => "Qurbon hayiti 2027 / Haj mavsumi", 'types' => ['umra'], 'lead_days' => 60,
         'note' => "Haj davrida Umra yopiq bo'ladi — undan keyingi guruhlarni oldindan soting"],
        ['date' => '2027-06-06', 'name' => 'Yangi hijriy yil / Umra mavsumi qayta ochilishi', 'types' => ['umra'], 'lead_days' => 45,
         'note' => 'Hajdan keyingi birinchi Umra guruhlari — odatda arzonroq'],
        ['date' => '2028-01-28', 'name' => 'Ramazon 2028 boshlanishi', 'types' => ['umra'], 'lead_days' => 90,
         'note' => ''],
    ],
];
