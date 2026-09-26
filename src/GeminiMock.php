<?php
declare(strict_types=1);

namespace Maryam;

/**
 * Mock Gemini — test va demo uchun. Real AI chaqirmaydi, yaqobon javob beradi.
 * Copywriter agenti to'liq jarayonini ko'rish uchun ishlatilamiz.
 */
class GeminiMock
{
    public function __construct(
        public readonly string $fastModel = 'mock-flash',
        public readonly string $smartModel = 'mock-pro',
        public readonly array $fallbackModels = [],
    ) {
    }

    /** Oxirgi so'rovga ilova qilingan rasmlar soni (testlar uchun). */
    public static int $lastImages = 0;

    public function json(string $system, string $user, float $temperature = 0.8, bool $smart = false, array $images = []): array
    {
        $started = microtime(true);
        self::$lastImages = count($images);
        
        // System prompt'dan agentni aniqlang
        if (str_contains($system, 'uslub tahlilchisisan')) {
            $data = ['photo_background' => true, 'uppercase_titles' => true, 'title_position' => 'past', 'text_density' => 'kam',
                     'mood' => 'yorqin, ishonchli', 'preferred_layouts' => ['hot_tour', 'price_list', 'review'],
                     'recurring_elements' => ['pastda yashil lenta', 'oltin narx plashkasi'], 'notes' => 'Foto fon, qisqa katta sarlavha, narx doim oltin plashkada.'];
        } elseif (str_contains($system, 'QAYTA ISHLATILADIGAN SHABLON')) {
            $data = $this->mockTemplate();
        } elseif (str_contains($system, "O'QITUVCHISI")) {
            $data = $this->mockRules();
        } elseif (str_contains($system, 'grafik dizaynerisan')) {
            $data = [
                'layout' => ['1080x1350', "Sarlavha (yuqori 1/3, oq, qalin): ISTANBUL", "Narx plashkasi (pastki chap, oltin): 775$ dan", "Pastki lenta (to'q yashil): 55-303-22-22 · logotip"],
                'image_prompt' => 'Galata tower at golden hour, Istanbul rooftops, clean negative space at the top third, no text, no watermark, no typography',
                'card' => str_contains($user, '"deliverable_format": "karusel"')
                    ? ['layout' => 'carousel', 'slides' => [
                        ['layout' => 'slide_cover', 'title' => 'Istanbulga borishdan oldin bilishingiz kerak bo‘lgan 4 narsa', 'label' => 'ISTANBUL'],
                        ['layout' => 'tips', 'title' => 'Viza kerak emas', 'text' => "O‘zbekiston fuqarolari 30 kungacha vizasiz."],
                        ['layout' => 'tips', 'title' => 'Istanbulkart oling', 'text' => 'Metro, tramvay va paromlarda bitta karta.'],
                        ['layout' => 'hot_tour', 'title' => 'Istanbul', 'subtitle' => 'Har kuni uchish · 5 kun', 'price' => '775$ dan'],
                        ['layout' => 'slide_cta', 'title' => 'Tur tanlashda yordam kerakmi?', 'text' => "Direct'ga yozing — 10 daqiqada javob", 'button' => "Direct'ga ISTANBUL deb yozing"],
                    ]]
                    : ['layout' => str_contains($user, '"deliverable_format": "reels"') ? 'cover' : 'hot_tour', 'title' => 'Istanbul', 'subtitle' => 'Har kuni uchish · 5 kun · nonushta', 'price' => '775$ dan', 'label' => 'QAYNOQ TUR', 'cta' => "Direct'ga ISTANBUL deb yozing"],
                'alt_text' => "Istanbul, Galata minorasi oqshom yorug'ida",
            ];
        } elseif (str_contains($system, "bo'limining boshlig'isan")) {
            $data = ['action' => 'chat', 'reply' => 'Mock javob', 'ask_field' => '', 'brief' => [], 'knowledge' => []];
        } elseif (str_contains($system, 'kontent-strategisan')) {
            $data = $this->mockPlan();
        } elseif (str_contains($system, 'strategisan')) {
            $data = $this->mockStrategy();
        } elseif (str_contains($system, 'copywriter\'isan')) {
            $data = $this->mockWrite();
        } else {
            $data = $this->mockEdit();
        }

        return [
            'text' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'data' => $data,
            'model' => $smart ? $this->smartModel : $this->fastModel,
            'model_used' => $smart ? $this->smartModel : $this->fastModel,
            'tokens_in' => 100,
            'tokens_out' => 200,
            'ms' => (int) ((microtime(true) - $started) * 1000 + mt_rand(50, 200)),
        ];
    }

    private function mockRules(): array
    {
        return [
            'rules' => [
                ['agent' => 'copywriter', 'content' => "Hookda manzil nomi birinchi 5 so'z ichida bo'lsin.", 'reason' => "Past baholangan postlarda manzil kech tilga olingan"],
                ['agent' => 'copywriter', 'content' => "Postda 6 tadan ortiq emoji ishlatma.", 'reason' => "Izoh: 'emoji juda ko'p'"],
            ],
            'summary' => "Agentlar ishonch omillarini yaxshi yozadi, lekin hook ko'pincha uzun.",
        ];
    }

    private function mockTemplate(): array
    {
        return [
            'name' => 'Qaynoq tur — narx va sana bilan',
            'format' => 'post', 'stage' => 'sotuv', 'tourism_type' => 'outbound',
            'structure' => "🔥 {JOY} — {NARX} dan\n📅 {SANALAR}\n✅ {KIRADI}\n📩 {CTA}",
            'rules' => "Narx va sana majburiy. Bitta CTA.",
            'design' => "1080x1350, manzil fotosi, oltin narx plashkasi, yashil lenta.",
        ];
    }

    private function mockPlan(): array
    {
        return [
            'week_focus' => "Ramazon Umrasi erta bronini boshlash va ishonchni mustahkamlash",
            'items' => [
                ['day' => 'Dushanba', 'format' => 'reels', 'tourism_type' => 'umra', 'goal' => 'lid',
                 'topic' => "Ramazonda Umra: nega hozirdan bron qilish kerak", 'idea' => "Ramazon guruhlari tez to'lishini ko'rsatamiz", 'product_id' => '', 'why' => "Ramazonga 4 oy qoldi", 'template_id' => 3],
                ['day' => 'Chorshanba', 'format' => 'karusel', 'tourism_type' => 'umra', 'goal' => 'brend',
                 'topic' => "Umraga tayyorgarlik: 7 ta maslahat", 'idea' => "Foydali karusel — saqlanadi va ulashiladi", 'product_id' => '', 'why' => "Ishonch va saqlashlar"],
                ['day' => 'Juma', 'format' => 'post', 'tourism_type' => 'ichki', 'goal' => 'jalb',
                 'topic' => "Kuzgi Chimyon: dam olish kunlari uchun", 'idea' => "Oilaviy qisqa safar", 'product_id' => '', 'why' => "Kuzgi dam olish mavsumi"],
            ],
            'missing_info' => ["Ramazon Umrasi narxi va sanalari (config/products.php)"],
        ];
    }

    private function mockStrategy(): array
    {
        return [
            'audience' => [
                'portrait' => '35-60 yosh, diniy qiymatlarni hurmat qiluvchi, oilasiga e\'tibar beradigan insonlar',
                'pains' => ['Yo\'lda qiynalish', 'Tilni bilmash', 'Yolg\'on gid', 'Notehnik mehmonxona'],
                'desires' => ['Xotirjamlik', 'Ishonch', 'Ma\'naviy iliqlik', 'Oilasini himoya qilish'],
                'objections' => ['Qancha to\'g\'ri narx?', 'Haqiqiymi gid o\'zbekcha gapiradi?', 'Mehmonxona naqadar yaxshi?'],
                'triggers' => ['Erta bron chegirmasi', 'Kelgan yil hajj/umra muddati', 'Do\'stning manzurasi'],
            ],
            'big_idea' => 'Siz faqat yolni yo\'qotmasdan, o\'zingizning ruhingizni topasiz',
            'key_message' => 'Har bir qadam bilan biz yoningizdamiz — ota-onangiz emas, faqat Siz',
            'proof_points' => ['15 yildan beri 10,000+ ziyoratchini olib borgan tajriba', 'Makka-Madinada o\'zbekcha gid 24/7'],
            'missing_facts' => ['Narx (paket 2027 qancha?)', 'Jo\'nash sanalari (fevral/mart aniq sanalar)'],
            'tone' => [
                'voice' => 'Samimiy, hurmatli, g\'amxo\'r',
                'do' => ['Ishonch omillarini birinchi', 'Tashvishlarni hal qilish', 'Ma\'naviy qiymatlarni berish'],
                'dont' => ['Agressiv FOMO', 'Oyat yoki hadis bilan to\'qima', 'Hazil-mutoyiba'],
            ],
            'angles' => [
                ['name' => 'Ishonch', 'type' => 'social_proof', 'idea' => '15 yillik tajriba va litsenziya', 'hook_idea' => 'Ota-onangiz Haramda ilk marta...'],
                ['name' => 'E\'tiroz yechish', 'type' => 'objection', 'idea' => 'Yo\'lda qiynalmaysiz, til muammosi yo\'q', 'hook_idea' => 'Til muammosi, gid muammosi?'],
                ['name' => 'Urg\'onjillik', 'type' => 'urgency', 'idea' => 'Erta bron chegirmasi 31-dekabrgacha', 'hook_idea' => '31-dekabrgacha erta bron chegirmasi...'],
            ],
            'cta' => ['primary' => "Direct'ga yozing", 'channel' => 'Direct'],
        ];
    }

    private function mockWrite(): array
    {
        return [
            'hooks' => [
                'Ota-onangiz Haramda birinchi marta yig\'lagan kunni tasavvur qiling',
                'Tilni bilmasdan ham, qo\'niqishdan iborat umra? Yo\'q — biz yoningizdamiz',
                'Makka-Madinada 300 meter masofada, o\'zbek tilida gid, 14 kun — 2027 fevral',
                'Erta bron qilganlarga chegirma: 31-dekabrgacha — eng arzon qo\'ng\'iroq qil',
                'Siz — faqat o\'zingiz uchun, oilangiz uchun, Alloh uchun',
            ],
            'variants' => [
                [
                    'id' => 'post_1',
                    'kind' => 'social_post',
                    'angle' => 'Ishonch',
                    'framework' => 'PAS',
                    'hook' => 'Ota-onangiz Haramda birinchi marta yig\'lagan kunni tasavvur qiling',
                    'body' => "Tilni bilmasdan ham tashvishlanmang — o'zbek tilida gid bor.\n\nMakka-Madinada eng yaqin mehmonxonalardan 300 metr masofada.\n\nEr-xotiningiz yoki farzandlaringiz bilan 14 kunni o'tkazing — qolgan umrni tiniqlika o'tkazasiz.",
                    'cta' => "Direct'ga yozing, [TELEFON] qo'ng'iroq qiling",
                    'hashtags' => ['#umra2027', '#ertabron', '#makka', '#madinah'],
                    'headline' => '',
                    'description' => '',
                    'cta_button' => '',
                    'scores' => ['hook' => 9, 'clarity' => 8, 'tone_fit' => 9, 'persuasion' => 8, 'cta' => 8, 'language' => 9],
                    'issues' => [],
                    'changes' => [],
                ],
                [
                    'id' => 'post_2',
                    'kind' => 'social_post',
                    'angle' => "E'tiroz yechish",
                    'framework' => 'BAB',
                    'hook' => "Til muammosi, gid muammosi — yo'q bunday?",
                    'body' => "OLDIN: Yo\'lda yolg\'on gid, til tushunmayotgan, mehmonxona xumoyali\n\nKEYIN: 14 kun tiniqlika o\'tkazasiz, ruh tinch, qalbingiz toza\n\nBIZ: O'zbek tilida gid, Makka-Madinada o'z mehmonxonalarimiz, 15 yillik tajriba",
                    'cta' => "Erta bron chegirmasi 31-dekabrgacha — hozir qo'ng'iroq qiling",
                    'hashtags' => ['#umra', '#2027', '#ertabron'],
                    'headline' => '',
                    'description' => '',
                    'cta_button' => '',
                    'scores' => ['hook' => 8, 'clarity' => 9, 'tone_fit' => 8, 'persuasion' => 9, 'cta' => 9, 'language' => 8],
                    'issues' => [],
                    'changes' => [],
                ],
                [
                    'id' => 'ad_1',
                    'kind' => 'ad',
                    'angle' => 'Urg\'onjillik',
                    'framework' => '4U',
                    'hook' => "2027 Umra — erta bron chegirmasi 31-dekabrgacha",
                    'body' => "Aniq qo'ng'iroq: 24 soat ichida 2,000 som chegirma qaytadi\n\nNoyob: Makka-Madinada o'z mehmonxonalarimiz, 14 kunlik to'liq paket\n\nFoydali: O'zbek tilida gid, transfer, mehmonxona, aviabilet — hammasi kiritilgan\n\nShoshilinch: Erta bron chegirmasi 31-dekabrgacha!",
                    'cta' => "Direct'ga yozing: @maryam_travel yoki [TELEFON]",
                    'hashtags' => [],
                    'headline' => 'Umra 2027 — erta bron chegirmasi',
                    'description' => 'O\'zbek tilida gid + 14 kun. 31-dekabrgacha chegirma',
                    'cta_button' => 'Send Message',
                    'scores' => ['hook' => 8, 'clarity' => 9, 'tone_fit' => 8, 'persuasion' => 9, 'cta' => 8, 'language' => 9],
                    'issues' => [],
                    'changes' => [],
                ],
                [
                    'id' => 'ad_2',
                    'kind' => 'ad',
                    'angle' => 'Ishonch',
                    'framework' => 'PAS',
                    'hook' => 'Haramga 300 metr, o\'zbek gid, 15 yillik tajriba',
                    'body' => "Muammo: Til tushunmayotganda, mehmonxona xumoyalida tashvish\n\nYechim: Makka-Madinada o'z mehmonxonalarimiz, 24/7 o'zbek gid\n\nNatija: Xotirjam, tinik, ma\'naviy umra\n\nHozir qo'ng'iroq — erta bron chegirmasi tugaymog'i iloji yo'q!",
                    'cta' => '[TELEFON] qo\'ng\'iroq qiling',
                    'hashtags' => [],
                    'headline' => 'Umra 2027 Maryam Travel bilan',
                    'description' => '15 yillik tajriba, o\'z mehmonxonasi, o\'zbek gid',
                    'cta_button' => 'Contact Us',
                    'scores' => ['hook' => 9, 'clarity' => 9, 'tone_fit' => 9, 'persuasion' => 9, 'cta' => 9, 'language' => 9],
                    'issues' => [],
                    'changes' => [],
                ],
            ],
        ];
    }

    private function mockEdit(): array
    {
        return [
            'variants' => [
                [
                    'id' => 'post_1',
                    'kind' => 'social_post',
                    'angle' => 'Ishonch',
                    'framework' => 'PAS',
                    'hook' => 'Ota-onangiz Haramda birinchi marta yig\'lagan kunni tasavvur qiling',
                    'body' => "Tilni bilmasdan ham tashvishlanmang — o'zbek tilida gid bor.\n\nMakka-Madinada eng yaqin mehmonxonalardan 300 metr masofada.\n\nEr-xotiningiz yoki farzandlaringiz bilan 14 kunni o'tkazing — qolgan umrni tiniqlika o'tkazasiz.",
                    'cta' => "Direct'ga yozing, [TELEFON] qo'ng'iroq qiling",
                    'hashtags' => ['#umra2027', '#ertabron', '#makka', '#madinah'],
                    'headline' => '',
                    'description' => '',
                    'cta_button' => '',
                    'scores' => ['hook' => 9, 'clarity' => 9, 'tone_fit' => 9, 'persuasion' => 9, 'cta' => 9, 'language' => 9],
                    'issues' => [],
                    'changes' => [],
                ],
                [
                    'id' => 'post_2',
                    'kind' => 'social_post',
                    'angle' => "E'tiroz yechish",
                    'framework' => 'BAB',
                    'hook' => "Til muammosi — yo'q! Mehmonxona — yaxshi!",
                    'body' => "OLDIN: Yo\'lda yolg\'on gid, til tushunmayotgan, mehmonxona xumoyali\n\nKEYIN: 14 kun tiniqlika o\'tkazasiz, ruh tinch, qalbingiz toza\n\nBIZ: O'zbek tilida gid, Makka-Madinada o'z mehmonxonalarimiz, 15 yillik tajriba",
                    'cta' => "Erta bron chegirmasi 31-dekabrgacha — hozir qo'ng'iroq qiling",
                    'hashtags' => ['#umra', '#2027', '#ertabron'],
                    'headline' => '',
                    'description' => '',
                    'cta_button' => '',
                    'scores' => ['hook' => 9, 'clarity' => 9, 'tone_fit' => 9, 'persuasion' => 9, 'cta' => 9, 'language' => 9],
                    'issues' => [],
                    'changes' => ['Hook aniqlashtirish'],
                ],
                [
                    'id' => 'ad_1',
                    'kind' => 'ad',
                    'angle' => 'Urg\'onjillik',
                    'framework' => '4U',
                    'hook' => "2027 Umra — erta bron chegirmasi 31-dekabrgacha",
                    'body' => "Aniq qo'ng'iroq: 24 soat ichida 2,000 som chegirma qaytadi\n\nNoyob: Makka-Madinada o'z mehmonxonalarimiz, 14 kunlik to'liq paket\n\nFoydali: O'zbek tilida gid, transfer, mehmonxona, aviabilet — hammasi kiritilgan\n\nShoshilinch: Erta bron chegirmasi 31-dekabrgacha!",
                    'cta' => "Direct'ga yozing: @maryam_travel yoki [TELEFON]",
                    'hashtags' => [],
                    'headline' => 'Umra 2027 erta bron chegirmasi',
                    'description' => 'O\'zbek gid + 14 kun paket. 31-dekabrgacha',
                    'cta_button' => 'Send Message',
                    'scores' => ['hook' => 9, 'clarity' => 9, 'tone_fit' => 9, 'persuasion' => 9, 'cta' => 9, 'language' => 9],
                    'issues' => [],
                    'changes' => ['Sarlavha qisqartiring (30 belgiga)', 'Tavsif o\'zgartirildi'],
                ],
                [
                    'id' => 'ad_2',
                    'kind' => 'ad',
                    'angle' => 'Ishonch',
                    'framework' => 'PAS',
                    'hook' => 'Haramga 300 metr, o\'zbek gid, 15 yillik tajriba',
                    'body' => "Muammo: Til tushunmayotganda, mehmonxona xumoyalida tashvish\n\nYechim: Makka-Madinada o'z mehmonxonalarimiz, 24/7 o'zbek gid\n\nNatija: Xotirjam, tinik, ma\'naviy umra\n\nHozir qo'ng'iroq — erta bron chegirmasi tugaymog'i iloji yo'q!",
                    'cta' => '[TELEFON] qo\'ng\'iroq qiling',
                    'hashtags' => [],
                    'headline' => 'Umra 2027 Maryam Travel',
                    'description' => '15 yil tajriba, o\'zbek gid, o\'z mehmonxonasi',
                    'cta_button' => 'Contact Us',
                    'scores' => ['hook' => 9, 'clarity' => 9, 'tone_fit' => 9, 'persuasion' => 9, 'cta' => 9, 'language' => 9],
                    'issues' => [],
                    'changes' => [],
                ],
            ],
        ];
    }
}
