<?php
/**
 * MAHSULOTLAR KATALOGI — hozir sotuvda bo'lgan turlar.
 *
 * Marketing bo'limidagi barcha agentlar (Kontent-strateg, Copywriter, Manager) shu yerdan
 * narx, sana, mehmonxona va boshqa faktlarni oladi. Shuning uchun sizdan har safar
 * "narxi qancha, qachon jo'naydi" deb so'ramaydi va matnda [NARX] kabi bo'sh joylar qolmaydi.
 *
 * Qoidalar:
 *   - 'id' — qisqa, lotincha, takrorlanmas (masalan umra-fevral-2027)
 *   - 'type' — umra | ichki | inbound | outbound (config/tones.php'dagi kalitlar)
 *   - 'active' => false — sotuvdan chiqqan tur (agentlar uni reklama qilmaydi)
 *   - Faqat HAQIQIY ma'lumot yozing. Bilmagan maydonni '' qoldiring — agent to'qimaydi.
 *   - 'selling_points' — shu turning eng kuchli tomonlari (mijoz nima uchun aynan shuni tanlaydi)
 */

return [
    // Namuna — o'zingiznikiga almashtiring va izohdan chiqaring:
    //
    // [
    //     'id'         => 'umra-fevral-2027',
    //     'type'       => 'umra',
    //     'active'     => true,
    //     'name'       => 'Umra — fevral 2027 (erta bron)',
    //     'price'      => '1 450 $ dan',
    //     'dates'      => "Jo'nash: 10-fevral, 24-fevral 2027",
    //     'duration'   => '14 kun (Makka 7 + Madina 7)',
    //     'hotels'     => "Makka: Hilton Convention, Haramgacha 400 m; Madina: 3*, Haramgacha 200 m",
    //     'includes'   => "Aviabilet Toshkent–Jidda, viza, 2 mahal ovqat, transfer, o'zbek tilida gid-ustoz",
    //     'excludes'   => "Shaxsiy xarajatlar",
    //     'seats'      => '45 joy',
    //     'offer'      => "31-dekabrgacha to'lov qilganlarga 100 $ chegirma",
    //     'selling_points' => [
    //         "Butun safar davomida o'zbek tilida gid-ustoz",
    //         'Keksalar uchun nogironlik aravachasi bepul',
    //     ],
    // ],
];
