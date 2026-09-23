<?php
/**
 * MARYAM TRAVEL — brend haqidagi faktlar.
 *
 * MUHIM: agentlar faqat shu yerdagi va brifdagi faktlardan foydalanadi, o'zidan to'qimaydi.
 * Bo'sh qoldirilgan maydon matnda [QAVS ICHIDA] joy-belgi sifatida chiqadi — keyin qo'lda to'ldirasiz.
 * Shuning uchun bu faylni imkon qadar to'liq va aniq to'ldiring.
 */

return [
    'name' => 'Maryam Travel',

    // Aloqa — CTA (harakatga chaqiruv)da ishlatiladi
    'phone'     => '',   // masalan: +998 90 123 45 67
    'telegram'  => '',   // masalan: @maryamtravel
    'instagram' => '',   // masalan: @maryam.travel
    'address'   => '',   // masalan: Toshkent, Chilonzor tumani, ...

    // Ishonch uyg'otadigan faktlar (faqat haqiqiylarini yozing!)
    'founded_year' => '', // masalan: 2015
    'license'      => '', // masalan: Turizm qo'mitasi litsenziyasi № ...
    'facts' => [
        // 'Har yili 2000+ ziyoratchini Umraga olib boramiz',
        // 'Makka va Madinada Haramga 300 m masofadagi mehmonxonalar',
    ],

    // Raqobatchilardan farqimiz (USP)
    'usp' => [
        // 'Butun safar davomida o'zbek tilida gid-ustoz',
    ],

    // Brend ovozi — barcha matnlarda umumiy
    'voice' => "Samimiy, g'amxo'r, ishonchli. Mijozni 'Siz' deb hurmat bilan chaqiramiz. "
             . "Bo'rttirilgan va'dalar bermaymiz, aniq faktlar bilan gapiramiz.",

    // Hech qachon ishlatilmaydigan so'z/iboralar
    'never_say' => [
        'eng arzon',       // narx bilan emas, qiymat bilan sotamiz
        '100% kafolat',
        'shoshiling, aks holda afsuslanasiz',
    ],
];
