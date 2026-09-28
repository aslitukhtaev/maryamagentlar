<?php
/**
 * Barcha POST amallari. public/index.php ichidan chaqiriladi ($ai, $store, $brand, $tones, $page mavjud).
 */

declare(strict_types=1);

use Maryam\Agents\ContentPlanner;
use Maryam\Agents\Copywriter;
use Maryam\Agents\GraphicDesigner;
use Maryam\Agents\Trainer;
use Maryam\Brief;
use Maryam\Output;
use Maryam\Prompts;
use Maryam\Store;

$action = (string) ($_POST['action'] ?? '');
$back = static function (string $fallback): string {
    $r = (string) ($_POST['return'] ?? '');
    return str_starts_with($r, '?') ? $r : $fallback;
};

switch ($action) {
    // ---------- Studiya ----------
    case 'run':
        // Fon ishi: sahifa darhol qaytadi, holat yuqoridagi lentada
        if (trim((string) ($_POST['topic'] ?? '')) === '') {
            throw new InvalidArgumentException('Mavzuni yozing.');
        }
        start_job('web_run', array_diff_key($_POST, ['csrf' => 1, 'action' => 1]), url(['p' => 'studio']));

    case 'rate':
        $store->rate((int) $_POST['variant_id'], (int) $_POST['rating'], trim((string) ($_POST['feedback'] ?? '')));
        flash("Baho saqlandi. Izohlar ko'paygach, Qoidalar sahifasida O'qituvchidan yangi qoidalar so'rang.");
        redirect($back('?') . '#v' . (int) $_POST['variant_id']);

    case 'golden':
        $v = $store->variant((int) $_POST['variant_id']);
        if ($v) {
            $text = trim(Copywriter::variantText($v));
            if ($store->hasHouseExample($text)) {
                flash('Bu post allaqachon oltin namunalarda.');
            } elseif ((int) ($v['rating'] ?? 0) > 0 && (int) $v['rating'] < 4) {
                flash("Bu postga {$v['rating']}★ qo'yilgan — oltin namuna faqat 4-5★ postlardan bo'ladi (agentlar yomon postdan o'rganmasin).", 'error');
            } else {
                // Izoh — egasi bahoga yozgan fikr (4-5★ da bu maqtov); bo'lmasa bo'sh
                $store->addHouseExample($text, $v['tourism_type'], (int) ($v['rating'] ?? 0) >= 4 ? (string) ($v['feedback'] ?? '') : '');
                flash("Oltin namunalarga qo'shildi — agentlar endi shu uslubni o'rganadi.");
            }
        }
        redirect($back('?') . '#v' . (int) $_POST['variant_id']);

    case 'design':
        start_job('web_design', ['brief_id' => (int) $_POST['brief_id'], 'variant_id' => (int) $_POST['variant_id'], 'return' => (string) ($_POST['return'] ?? '')],
            $back(url(['p' => 'studio', 'brief' => (int) $_POST['brief_id']])) . '#v' . (int) $_POST['variant_id']);

    // ---------- Dizayner (mustaqil) ----------

    case 'design_free':
        $text = trim((string) ($_POST['text'] ?? ''));
        if ($text === '') {
            throw new InvalidArgumentException('Matn yoki g\'oyani yozing.');
        }
        $format = in_array($_POST['format'] ?? '', ['post', 'karusel', 'reels'], true) ? $_POST['format'] : 'post';
        // Jamoa fotolari: yangi yuklanganlar shu yerda kutubxonaga saqlanadi (vaqtinchalik fayl fon ishigacha yashamaydi)
        $photos = array_values(array_filter(array_map('strval', (array) ($_POST['photo_pick'] ?? []))));
        foreach (uploaded_images('photos') as $tmp) {
            $photos[] = Maryam\BrandAssets::addPhoto($tmp);
        }
        start_job('web_design_free', ['text' => $text, 'format' => $format, 'photos' => array_slice(array_values(array_unique($photos)), 0, 3)], url(['p' => 'brend']),
            'Dizayner ' . ['post' => 'post', 'karusel' => 'karusel', 'reels' => 'Stories'][$format] . ' chizmoqda');

    // ---------- Haftalik reja ----------

    case 'plan':
        start_job('web_plan', ['wishes' => trim((string) ($_POST['wishes'] ?? ''))], url(['p' => 'reja']));

    // ---------- O'qituvchi ----------

    case 'propose_rules':
        $r = (new Trainer($ai, $store))->proposeRules();
        flash(($r['proposed'] ? "O'qituvchi {$r['proposed']} ta yangi qoida taklif qildi — tasdiqlang yoki o'chiring. " : "Yangi qoida taklif qilinmadi. ") . $r['summary']);
        redirect(url(['p' => 'qoidalar']));

    case 'template_from_example':
        $post = trim((string) ($_POST['post'] ?? ''));
        if ($post === '') {
            throw new InvalidArgumentException("Post matnini kiriting.");
        }
        $id = (new Trainer($ai, $store))->templateFromExample($post, trim((string) ($_POST['image_note'] ?? '')));
        flash("Shablon yaratildi — tekshirib, kerak bo'lsa tuzating va saqlang.");
        redirect(url(['p' => 'shablonlar', 'id' => $id]));

    case 'rule_status':
        $rule = $store->row('rules', (int) $_POST['id']);
        $status = in_array($_POST['status'] ?? '', ['active', 'off'], true) ? $_POST['status'] : 'off';
        if ($rule) {
            // Tasdiqlanganda O'qituvchi yozgan "sabab" izohi qoidadan olib tashlanadi
            $content = $status === 'active' ? preg_replace("/\n— sabab:.*$/s", '', $rule['content']) : $rule['content'];
            $store->updateRow('rules', (int) $rule['id'], ['status' => $status, 'content' => $content]);
            flash($status === 'active' ? 'Qoida tasdiqlandi — agentlar endi unga amal qiladi.' : "Qoida o'chirildi.");
        }
        redirect(url(['p' => 'qoidalar']));

    // ---------- Promptlar ----------
    case 'prompt_save':
        $name = (string) $_POST['name'];
        Prompts::original($name); // noma'lum nom bo'lsa xato beradi
        $store->savePromptVersion($name, str_replace("\r\n", "\n", (string) $_POST['content']), trim((string) ($_POST['note'] ?? '')));
        flash("Yangi versiya saqlandi — agent keyingi ishdan boshlab shu prompt bilan ishlaydi.");
        redirect(url(['p' => 'promptlar', 'name' => $name]));

    case 'prompt_restore':
        $version = $store->promptVersion((int) $_POST['version_id']);
        if ($version) {
            $store->savePromptVersion($version['name'], $version['content'], "#{$version['id']} versiyaga qaytarildi");
            flash('Tanlangan versiya tiklandi.');
        }
        redirect(url(['p' => 'promptlar', 'name' => $version['name'] ?? '']));

    case 'prompt_reset':
        $name = (string) $_POST['name'];
        $store->savePromptVersion($name, Prompts::original($name), 'Asl holatga qaytarildi');
        flash('Prompt asl holatiga qaytarildi.');
        redirect(url(['p' => 'promptlar', 'name' => $name]));

    // ---------- Brend: logo va ranglar ----------
    case 'brand_upload':
        $saved = 0;
        foreach (['logo' => 'logo', 'logo_white' => 'logo-white'] as $field => $which) {
            $f = $_FILES[$field] ?? null;
            if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 10 * 1024 * 1024) {
                throw new InvalidArgumentException("Fayl yuklanmadi (10 MB dan kichik rasm bo'lsin).");
            }
            Maryam\BrandAssets::saveLogo($f['tmp_name'], $which);
            $saved++;
        }
        flash($saved ? "Logo saqlandi — barcha rasmlarda endi shu logo turadi." : "Fayl tanlanmadi.");
        redirect(url(['p' => 'brend']));

    case 'ref_upload':
        $files = $_FILES['refs'] ?? null;
        $added = 0;
        foreach ((array) ($files['tmp_name'] ?? []) as $i => $tmp) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || $files['size'][$i] > 10 * 1024 * 1024) {
                continue;
            }
            Maryam\BrandAssets::addRef($tmp);
            $added++;
        }
        $styleError = $added ? refresh_brand_style($ai, $store) : null;
        if (!$added) {
            flash("Rasm tanlanmadi yoki fayl juda katta (har biri 10 MB gacha).", 'error');
        } else {
            flash("$added ta namuna qo'shildi. Ranglar va uslub namunalardan avtomatik aniqlandi." . ($styleError ? " $styleError" : ''), $styleError ? 'error' : 'ok');
        }
        redirect(url(['p' => 'brend']));

    case 'ref_delete':
        Maryam\BrandAssets::deleteRef((string) ($_POST['name'] ?? ''));
        refresh_brand_style($ai, $store);
        flash("Namuna o'chirildi.");
        redirect(url(['p' => 'brend']));

    case 'brand_logo_delete':
        @unlink(ROOT . Maryam\PostRenderer::BRAND_DIR . '/' . (($_POST['which'] ?? '') === 'logo-white' ? 'logo-white.png' : 'logo.png'));
        flash("Logo o'chirildi.");
        redirect(url(['p' => 'brend']));

    case 'style_refresh':
        $styleError = refresh_brand_style($ai, $store);
        flash($styleError ?? 'Uslub qayta tahlil qilindi.', $styleError ? 'error' : 'ok');
        redirect(url(['p' => 'brend']));

    case 'design_pick':
        $d = $store->resultById((int) $_POST['id']);
        if (count($d['frames'] ?? []) > 1 && (int) ($d['chosen'] ?? -1) !== (int) $_POST['i']) {
            // Karusel: boshqa muqova — slaydlar qayta chiziladi (uzoq), fonda
            start_job('web_design_pick', ['id' => (int) $_POST['id'], 'i' => (int) $_POST['i'], 'return' => (string) ($_POST['return'] ?? '')],
                $back(url(['p' => 'brend', 'show' => (int) $_POST['id']])));
        }
        (new GraphicDesigner($ai, $store, $brand, $tones))->choose((int) $_POST['id'], (int) $_POST['i']);
        flash('Tanlandi — endi "Telegramga yuborish" ni bosing.');
        redirect($back(url(['p' => 'brend', 'show' => (int) $_POST['id']])) . '#natija');

    case 'design_fix':
        start_job('web_design_fix', ['id' => (int) $_POST['id'], 'i' => (int) $_POST['i'], 'target' => ($_POST['target'] ?? 'v') === 's' ? 's' : 'v',
            'instruction' => trim((string) ($_POST['instruction'] ?? '')), 'return' => (string) ($_POST['return'] ?? '')],
            $back(url(['p' => 'brend', 'show' => (int) $_POST['id']])));

    case 'design_redraw':
        start_job('web_design_redraw', ['id' => (int) $_POST['id'], 'return' => (string) ($_POST['return'] ?? '')], $back(url(['p' => 'brend', 'show' => (int) $_POST['id']])));

    case 'design_retry':
        start_job('web_design_retry', ['id' => (int) $_POST['id']], $back(url(['p' => 'brend'])));

    case 'inspo_upload':
        $added = 0;
        foreach (uploaded_images('inspo') as $tmp) {
            Maryam\BrandAssets::addInspo($tmp);
            $added++;
        }
        $err = $added ? Maryam\DesignSystem::analyze($ai, $store) : null;
        flash(!$added ? 'Rasm tanlanmadi yoki juda katta (10 MB gacha).' : ($err ?? "Uslub namunasi qo'shildi — AI dizayn tizimini chiqardi, keyingi postlar shu uslubda."), !$added || $err ? 'error' : 'ok');
        redirect(url(['p' => 'brend']) . '#uslub');

    case 'inspo_delete':
        Maryam\BrandAssets::deleteInspo((string) ($_POST['name'] ?? ''));
        Maryam\DesignSystem::analyze($ai, $store);
        flash("Namuna o'chirildi.");
        redirect(url(['p' => 'brend']) . '#uslub');

    case 'design_palette':
        $store->setMeta('design_palette', ($_POST['mode'] ?? '') === 'inspo' ? 'inspo' : 'brand');
        flash('Ranglar saqlandi.');
        redirect(url(['p' => 'brend']) . '#uslub');

    case 'system_refresh':
        $err = Maryam\DesignSystem::analyze($ai, $store);
        flash($err ?? 'Dizayn tizimi qayta tahlil qilindi.', $err ? 'error' : 'ok');
        redirect(url(['p' => 'brend']) . '#uslub');

    case 'photo_upload':
        $added = 0;
        foreach (uploaded_images('photos') as $tmp) {
            Maryam\BrandAssets::addPhoto($tmp);
            $added++;
        }
        flash($added ? "$added ta foto qo'shildi — dizayn buyurtmasida belgilab ishlating." : 'Foto tanlanmadi yoki juda katta (10 MB gacha).', $added ? 'ok' : 'error');
        redirect(url(['p' => 'brend']) . '#fotolar');

    case 'photo_delete':
        Maryam\BrandAssets::deletePhoto((string) ($_POST['name'] ?? ''));
        flash("Foto o'chirildi.");
        redirect(url(['p' => 'brend']) . '#fotolar');

    case 'send_tg':
        // Rasm(lar)ni bot chatiga yuborish: Telegram ichida brauzer orqali yuklab olish ishonchsiz
        $design = $store->resultById((int) $_POST['id']);
        // Karusel — barcha slaydlar; AI variantlar — tanlangani (tanlanmagan bo'lsa hammasi); aks holda bitta rasm
        $files = design_files($design ?? []);
        if (!empty($design['variants']) && empty($design['slides']) && isset($design['chosen'])) {
            $files = [$design['variants'][(int) $design['chosen']]['path'] ?? ''];
        }
        $files = array_values(array_filter($files, static fn ($f) => is_string($f) && is_file($f)));
        $files = array_values(array_filter($files, 'is_file'));
        $token = (string) Maryam\Env::get('TELEGRAM_BOT_TOKEN', '');
        $auth = (string) ($_SESSION['auth'] ?? '');
        $chat = str_starts_with($auth, 'telegram:') ? substr($auth, 9) : (Maryam\TelegramAuth::allowedIds()[0] ?? '');
        if (!$files || $token === '' || $chat === '') {
            throw new RuntimeException($files ? 'Telegram bot sozlanmagan.' : 'Rasm topilmadi.');
        }
        $tg = new Maryam\Telegram($token, (string) $chat);
        if (count($files) >= 2) {
            empty($design['slides'])
                ? $tg->sendMediaGroup(array_slice($files, 0, 10), '🎨 ' . count($files) . " ta variant — eng yoqqanini Instagram'ga joylang.", 'photo')
                : $tg->sendMediaGroup(array_slice($files, 0, 10), '🎨 Karusel: ' . count($files) . " ta slayd. Instagram'ga shu tartibda joylang.");
        } else {
            $tg->sendDocument($files[0], '🎨 Tayyor rasm — Instagram\'ga joylang');
        }
        flash("Botga yuborildi — Telegram chatini oching.");
        redirect($back(url(['p' => 'brend'])));

    // ---------- Umumiy saqlash / o'chirish ----------
    case 'save':
        $table = (string) $_POST['table'];
        if (!isset(Store::EDITABLE[$table])) {
            throw new InvalidArgumentException("Noma'lum jadval.");
        }
        $data = array_map(static fn ($v) => is_string($v) ? str_replace("\r\n", "\n", trim($v)) : $v, $_POST);
        foreach ($data as $k => $v) {
            if (is_string($v) && mb_strlen($v) > 8000) {
                throw new InvalidArgumentException('Matn juda uzun (8000 belgigacha) — qisqartiring yoki bir necha qismga bo\'ling.');
            }
        }
        if (in_array('active', Store::EDITABLE[$table], true)) {
            $data['active'] = isset($_POST['active']) ? 1 : 0;
        }
        $id = (int) ($_POST['id'] ?? 0);
        $id ? $store->updateRow($table, $id, $data) : $store->insertRow($table, $data);
        flash('Saqlandi.');
        redirect($back(url(['p' => $page])));

    case 'delete':
        $store->deleteRow((string) $_POST['table'], (int) $_POST['id']);
        flash("O'chirildi.");
        redirect($back(url(['p' => $page])));
}

throw new InvalidArgumentException("Noma'lum amal: $action");
