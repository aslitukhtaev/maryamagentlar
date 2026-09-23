<?php
/**
 * Turizm turlari bo'yicha TON PROFILLARI.
 *
 * Har bir yo'nalish o'z auditoriyasi va o'z "ovozi"ga ega. Copywriter shu profilga qat'iy amal qiladi.
 * Keyinchalik Marketing Manager agenti bu profilni brifga qarab o'zgartirib yuborishi mumkin.
 */

return [
    'umra' => [
        'label'    => 'Umra / Haj',
        'language' => 'uz',
        'audience' => "35-65 yosh, oilali, dindor yoki ma'naviy safarga intilgan insonlar; "
                    . "ko'pincha qaror farzandlar bilan birga qabul qilinadi (farzand ota-onasini jo'natadi).",
        'voice'    => "Hurmat, ishonch, xotirjamlik va ma'naviy iliqlik. Sokin, salmoqli, samimiy.",
        'do' => [
            "Ishonch omillarini birinchi o'ringa qo'ying: tajriba, litsenziya, gid, mehmonxona masofasi, tibbiy yordam",
            "Qarindoshlarni (ota-onasini yuboradigan farzandlarni) ham hisobga oling",
            "Tashvishlarni yeching: 'yo'lda qiynalmaymanmi', 'yolg'iz qolmaymanmi', 'tilni bilmayman'",
            "Diniy atamalarni to'g'ri va hurmat bilan ishlating (Umra, ziyorat, Haramayn, ehrom)",
        ],
        'dont' => [
            "Agressiv FOMO, 'shoshiling!!!', ko'p undov belgilari",
            "Oyat yoki hadisni aniq manbasiz keltirish — umuman to'qib chiqarmang",
            "Ziyoratni 'arzon tovar' kabi sotish, hazil-mutoyiba",
            "Ortiqcha emoji (maksimum 2-3 ta, sokin: 🕋 🤲 ✨)",
        ],
        'emoji' => 'kam',
    ],

    'ichki' => [
        'label'    => 'Ichki turizm (O\'zbekiston bo\'ylab)',
        'language' => 'uz',
        'audience' => "20-45 yosh, shahar aholisi, oilalar va do'stlar guruhi; dam olish kunlari qisqa safar izlovchilar.",
        'voice'    => "Iliq, jonli, vatanparvarlik ohangida; 'o'z yurtingni kashf et' kayfiyati.",
        'do' => [
            "Sensor tafsilotlar: tog' havosi, osh hidi, qadimiy ko'chalar",
            "Qulaylikni ta'kidlang: transport, ovqat, gid — hammasi tayyor",
            "Oila va do'stlar bilan birga o'tkaziladigan vaqt qadrini ko'rsating",
        ],
        'dont' => [
            "Chet el bilan kamsituvchi taqqoslash",
            "Umumiy bo'sh iboralar ('ajoyib sayohat', 'unutilmas taassurot') — aniq tafsilot bering",
        ],
        'emoji' => "o'rtacha",
    ],

    'inbound' => [
        'label'    => 'Inbound (xorijliklar uchun O\'zbekiston)',
        'language' => 'en',
        'audience' => "Xorijlik sayyohlar (Yevropa, Osiyo, MDH), 25-60 yosh, madaniyat va tarixga qiziquvchilar.",
        'voice'    => "Ilhomlantiruvchi, ekspert va mehmondo'st; Buyuk Ipak yo'li sirlari.",
        'do' => [
            "Xavfsizlik, vizasizlik/soddalashgan viza, qulaylik — xorijlik uchun muhim savollarga javob",
            "Samarqand, Buxoro, Xiva — o'ziga xos, ko'rish mumkin bo'lgan tafsilotlar",
            "Mahalliy mehmondo'stlik va oshxona",
        ],
        'dont' => [
            "O'zbekcha iboralarni tarjimasiz ishlatish",
            "Ekzotiklashtiruvchi, stereotip iboralar",
        ],
        'emoji' => "o'rtacha",
    ],

    'outbound' => [
        'label'    => 'Outbound (xorijga turlar)',
        'language' => 'uz',
        'audience' => "22-35 yosh, yoshlar, juftliklar, yangi taassurot va kontent izlovchilar.",
        'voice'    => "Hayajonli, energik, FOMO uslubida, lekin halol; do'stona 'sen-siz' orasidagi yengil ohang.",
        'do' => [
            "Kuchli hook: savol, qarama-qarshilik yoki kutilmagan fakt",
            "Cheklangan joylar/muddat — faqat brifda berilgan bo'lsa",
            "Tajriba va emotsiyani soting (quyosh botishi, dengiz, kechki shahar), shunchaki mehmonxonani emas",
            "Narx-qiymatni solishtiring: 'o'zing uyushtirsang qancha, biz bilan qancha'",
        ],
        'dont' => [
            "Soxta taqchillik (brifda bo'lmagan 'faqat 3 joy qoldi')",
            "Uzun, zerikarli kirish",
        ],
        'emoji' => "ko'p",
    ],
];
