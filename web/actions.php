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
        // "Avtomatik": yo'nalish va maqsad shablondan (yo'q bo'lsa — xorijga turlar, lid)
        $templateId = (int) ($_POST['template_id'] ?? 0);
        $tpl = $templateId ? $store->row('templates', $templateId) : null;
        $input = $_POST;
        $input['tourism_type'] = ($input['tourism_type'] ?? '') ?: (($tpl['tourism_type'] ?? '') ?: 'outbound');
        $input['goal'] = ($input['goal'] ?? '') ?: (['qamrov' => 'jalb', 'ishonch' => 'brend', 'sotuv' => 'lid'][$tpl['stage'] ?? ''] ?? 'lid');
        $brief = Brief::normalize($input, $tones);
        $brief['id'] = $store->saveBrief($brief);
        $result = (new Copywriter($ai, $store, $brand, $tones))->run($brief, $templateId ? ['template_id' => $templateId] : []);
        Output::save($brief, 'copywriter.txt', Copywriter::toText($result));
        redirect(url(['p' => 'studio', 'brief' => $brief['id']]));

    case 'rate':
        $store->rate((int) $_POST['variant_id'], (int) $_POST['rating'], trim((string) ($_POST['feedback'] ?? '')));
        flash("Baho saqlandi. Izohlar ko'paygach, Qoidalar sahifasida O'qituvchidan yangi qoidalar so'rang.");
        redirect($back('?') . '#v' . (int) $_POST['variant_id']);

    case 'golden':
        $v = $store->variant((int) $_POST['variant_id']);
        if ($v) {
            $store->addHouseExample(trim(Copywriter::variantText($v)), $v['tourism_type'], (string) ($v['feedback'] ?? ''));
            flash("Oltin namunalarga qo'shildi — agentlar endi shu uslubni o'rganadi.");
        }
        redirect($back('?') . '#v' . (int) $_POST['variant_id']);

    case 'design':
        $briefId = (int) $_POST['brief_id'];
        $brief = $store->brief($briefId);
        $result = $store->result($briefId, Copywriter::NAME, 'final') ?? [];
        $variant = $store->variant((int) $_POST['variant_id']);
        (new GraphicDesigner($ai, $store, $brand, $tones))->run($brief, $result['strategy'] ?? [], [
            'template_id' => $result['template_id'] ?? null,
            'variant' => $variant,
        ]);
        flash('Dizayn tayyor — variant ostida.');
        redirect(url(['p' => 'studio', 'brief' => $briefId]) . '#v' . (int) $_POST['variant_id']);

    // ---------- Dizayner (mustaqil) ----------
    case 'design_free':
        $text = trim((string) ($_POST['text'] ?? ''));
        if ($text === '') {
            throw new InvalidArgumentException('Matn yoki g\'oyani yozing.');
        }
        $format = in_array($_POST['format'] ?? '', ['post', 'karusel', 'reels'], true) ? $_POST['format'] : 'post';
        $firstLine = trim((string) strtok($text, "\n"));
        $type = preg_match('/umra|haj|makka|madina/iu', $text) ? 'umra' : 'outbound';
        $brief = Brief::normalize(['topic' => mb_strimwidth($firstLine, 0, 80, '…'), 'tourism_type' => $type, 'details' => $text], $tones);
        $brief['id'] = $store->saveBrief($brief);
        $design = (new GraphicDesigner($ai, $store, $brand, $tones))->run($brief, [], ['format' => $format, 'text' => $text, 'kind' => 'free']);
        flash(!empty($design['card_path']) ? 'Tayyor! Rasmni bosing — yuklab olinadi.' : "Rasm chizilmadi — tafsilotlarni pastda ko'ring.", !empty($design['card_path']) ? 'ok' : 'error');
        redirect(url(['p' => 'brend', 'show' => $design['result_id']]) . '#natija');

    // ---------- Haftalik reja ----------
    case 'plan':
        $plan = (new ContentPlanner($ai, $store, $brand, $tones))->run(new DateTimeImmutable('today'), [
            'wishes' => trim((string) ($_POST['wishes'] ?? '')),
        ]);
        Output::save(['topic' => 'haftalik-reja-' . $plan['week'], 'id' => 0], 'kontent-paket.txt', ContentPlanner::toText($plan));
        flash('Haftalik reja tayyor.');
        redirect(url(['p' => 'reja', 'week' => $plan['week']]));

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

    case 'brand_colors':
        $colors = [];
        foreach (['primary', 'accent', 'dark'] as $k) {
            $v = (string) ($_POST[$k] ?? '');
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $v)) {
                $colors[$k] = strtolower($v);
            }
        }
        $store->setMeta('brand_colors', json_encode($colors));
        flash('Ranglar saqlandi.');
        redirect(url(['p' => 'brend']));

    // ---------- Umumiy saqlash / o'chirish ----------
    case 'save':
        $table = (string) $_POST['table'];
        if (!isset(Store::EDITABLE[$table])) {
            throw new InvalidArgumentException("Noma'lum jadval.");
        }
        $data = array_map(static fn ($v) => is_string($v) ? str_replace("\r\n", "\n", trim($v)) : $v, $_POST);
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
