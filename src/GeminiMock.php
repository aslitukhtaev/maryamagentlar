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

    /** @var array<int, array> sinov uchun: oxirgi rasm so'rovlari (prompt, rasm soni, format) */
    public static array $imageJobs = [];

    /**
     * Soxta rasm chizish: so'ralgan matn yozilgan oddiy afisha (haqiqiy AI'siz butun jarayonni sinash uchun).
     * AI_MOCK_IMAGES=0 bo'lsa — rasm modeli yo'qdek xato qaytaradi (zaxira shablonni sinash).
     */
    public function generateImages(array $jobs): array
    {
        usleep((int) ((float) getenv('AI_MOCK_DELAY') * 1e6));
        $out = [];
        foreach ($jobs as $i => $job) {
            self::$imageJobs[] = ['prompt' => $job['prompt'], 'images' => count($job['images'] ?? []), 'aspect' => $job['aspect'] ?? '4:5'];
            if (getenv('AI_MOCK_IMAGES') === '0') {
                $out[$i] = 'Vertex AI xatosi (404) [mock]: model not found';
                continue;
            }
            [$w, $h] = ($job['aspect'] ?? '4:5') === '9:16' ? [768, 1365] : [928, 1160];
            $im = imagecreatetruecolor($w, $h);
            $hue = crc32($job['prompt'] . $i) % 360;
            for ($y = 0; $y < $h; $y++) {
                $t = $y / $h;
                imageline($im, 0, $y, $w, $y, imagecolorallocate($im, (int) (20 + 60 * $t), (int) (60 + ($hue % 120) * (1 - $t)), (int) (50 + ($hue % 90))));
            }
            $font = ROOT . '/resources/fonts/Oswald-Bold.ttf';
            preg_match('/Headline: "([^"]*)"/u', $job['prompt'], $m);
            $head = mb_strtoupper($m[1] ?? 'MOCK');
            $y = (int) ($h * 0.45);
            foreach (explode("\n", wordwrap($head, 14, "\n", true)) as $line) {
                imagettftext($im, 64, 0, 50, $y, imagecolorallocate($im, 255, 255, 255), $font, $line);
                $y += 90;
            }
            if (preg_match('/Price badge: "([^"]+)"/u', $job['prompt'], $pm)) {
                imagefilledrectangle($im, 50, $y + 10, 50 + 40 * mb_strlen($pm[1]), $y + 100, imagecolorallocate($im, 233, 196, 106));
                imagettftext($im, 50, 0, 70, $y + 80, imagecolorallocate($im, 10, 40, 30), $font, $pm[1]);
            }
            $tag = (str_starts_with($job['prompt'], 'Edit') ? 'TUZATILGAN · ' : '') . 'MOCK #' . ($i + 1) . ' · ' . count($job['images'] ?? []) . ' rasm';
            imagettftext($im, 26, 0, 50, $h - 160, imagecolorallocate($im, 255, 230, 150), $font, $tag);
            ob_start();
            imagepng($im);
            $out[$i] = ['base64' => base64_encode((string) ob_get_clean()), 'mime_type' => 'image/png', 'model_used' => 'mock-image'];
        }
        return $out;
    }

    public function json(string $system, string $user, float $temperature = 0.8, bool $smart = false, array $images = []): array
    {
        $started = microtime(true);
        self::$lastImages = count($images);
        usleep((int) ((float) getenv('AI_MOCK_DELAY') * 1e6)); // sinov: sekin AI'ni taqlid qilish
        
        // System prompt'dan agentni aniqlang
        if (str_contains($system, 'uslub tahlilchisisan')) {
            $data = ['photo_background' => true, 'uppercase_titles' => true, 'bottom_strip' => false, 'title_position' => 'past', 'text_density' => 'kam',
                     'mood' => 'yorqin, ishonchli', 'preferred_layouts' => ['hot_tour', 'price_list', 'review'],
                     'recurring_elements' => ['pastda yashil lenta', 'oltin narx plashkasi'], 'notes' => 'Foto fon, qisqa katta sarlavha, narx doim oltin plashkada.'];
        } elseif (str_contains($system, 'QAYTA ISHLATILADIGAN SHABLON')) {
            $data = $this->mockTemplate();
        } elseif (str_contains($system, "O'QITUVCHISI")) {
            $data = $this->mockRules();
        } elseif (str_contains($system, 'afisha dizaynerisan')) {
            $concepts = [
                ['name' => 'Sayohatchi va Galata', 'prompt' => 'Happy traveller in the foreground, Galata tower at golden hour behind, huge white condensed headline'],
                ['name' => 'Bosfor manzarasi', 'prompt' => 'Full-bleed Bosphorus photo, bold headline, gold price badge bottom-left'],
                ['name' => 'Kollaj', 'prompt' => 'Collage of three Istanbul photos with flag stickers and arrows'],
                ['name' => 'Tipografik', 'prompt' => 'Deep green background, giant gold typography, minimal icons'],
            ];
            $data = str_contains($user, '"deliverable_format": "karusel"')
                ? ['headline' => 'ISTANBULGA BORISHDAN OLDIN', 'subline' => '4 ta maslahat', 'price' => '', 'badge' => '', 'concepts' => [$concepts[0]],
                   'slides' => [
                       ['headline' => 'Istanbulga borishdan oldin 4 narsa', 'subline' => 'Surib ko‘ring', 'prompt' => 'Cover with traveller and Galata tower'],
                       ['headline' => 'Viza kerak emas', 'subline' => '30 kungacha vizasiz', 'prompt' => 'Passport and boarding pass close-up'],
                       ['headline' => 'Istanbulkart oling', 'subline' => 'Metro, tramvay, parom', 'prompt' => 'Tram on Istiklal street'],
                       ['headline' => "Direct'ga ISTANBUL deb yozing", 'subline' => '10 daqiqada javob', 'prompt' => 'Smiling manager with phone'],
                   ], 'alt_text' => 'Istanbul haqida karusel']
                : ['headline' => 'ISTANBUL 775$ DAN', 'subline' => 'Har kuni uchish · 5 kun', 'price' => '775$ dan', 'badge' => 'QAYNOQ TUR',
                   'concepts' => $concepts, 'slides' => [], 'alt_text' => "Istanbul, Galata minorasi oqshom yorug'ida"];
        } elseif (str_contains($system, 'matn tekshiruvchisisan')) {
            // Sinov: ikkinchi rasmda xato bor deb ko'rsatamiz (UI dagi ogohlantirishni tekshirish uchun)
            $data = ['results' => array_map(static fn ($i) => $i === 1
                ? ['index' => 1, 'ok' => false, 'found' => 'ISTANBLU 775$ DAN', 'issues' => "ISTANBUL o'rniga ISTANBLU yozilgan", 'fix' => 'Change the headline to exactly "ISTANBUL 775$ DAN"']
                : ['index' => $i, 'ok' => true, 'found' => 'ISTANBUL 775$ DAN', 'issues' => '', 'fix' => ''], range(0, max(0, count($images) - 1)))];
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
                    'body' => "OLDIN: Yo'lda yolg'on gid, til tushunmayotgan, mehmonxona xumoyali\n\nKEYIN: 14 kun tiniqlika o'tkazasiz, ruh tinch, qalbingiz toza\n\nBIZ: O'zbek tilida gid, Makka-Madinada o'z mehmonxonalarimiz, 15 yillik tajriba",
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
                    'body' => "OLDIN: Yo'lda yolg'on gid, til tushunmayotgan, mehmonxona xumoyali\n\nKEYIN: 14 kun tiniqlika o'tkazasiz, ruh tinch, qalbingiz toza\n\nBIZ: O'zbek tilida gid, Makka-Madinada o'z mehmonxonalarimiz, 15 yillik tajriba",
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
