<?php
declare(strict_types=1);

/**
 * Architekturvertrag des Content-CMS:
 * - dbxTPL rendert eine sprachneutrale Struktur.
 * - dbxForm rendert Felder und Formulare.
 * - data-cms-* beschreibt Verhalten, CSS nur Struktur/Zustand.
 * - Datenzugriffe laufen über dbxDB.
 */

$base = dirname(__DIR__, 4);
require_once $base . '/dbx/include/tests/dbxModuleSourceBundle.php';
$module = $base . '/dbx/modules/dbxContent_admin';
$templateDir = $module . '/tpl/htm';
$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};
$read = static function (string $file): string {
    return is_file($file) ? (string)file_get_contents($file) : '';
};

$fieldTemplates = array(
    'cms-field-text',
    'cms-field-number',
    'cms-field-select',
    'cms-field-textarea',
    'cms-field-content-template-select',
    'cms-field-folder-text',
    'cms-field-folder-number',
    'cms-field-folder-select',
    'cms-field-folder-rights',
    'cms-field-folder-content-template-select',
);
foreach ($fieldTemplates as $name) {
    $source = $read($templateDir . '/' . $name . '.htm');
    $assert($source !== '', 'Feldtemplate fehlt: ' . $name);
    $assert(str_contains($source, 'dbx-form-field'), 'dbxForm-Feldklasse fehlt: ' . $name);
    $assert(str_contains($source, 'data-cms-field='), 'Verhaltens-Hook fehlt: ' . $name);
    $assert(str_contains($source, 'data-cms-field-scope='), 'Feld-Scope fehlt: ' . $name);
    $assert(!str_contains($source, 'style='), 'Inline-CSS im Feldtemplate: ' . $name);
}

$legacyTokens = array(
    'dbx-cms-form-grid',
    'dbx-cms-settings-grid',
    'dbx-cms-system-field',
    'dbx-cms-field-labelbar',
    'dbx-form-grid-6',
    'dbx-form-grid-4',
    'data-cms-folder-field',
);
$contractFiles = array_merge(
    glob($templateDir . '/cms-admin-*.htm') ?: array(),
    glob($templateDir . '/cms-field-*.htm') ?: array(),
    array(
        $templateDir . '/seo-admin.htm',
        $templateDir . '/seo-admin-form.htm',
        $base . '/dbx/js/lib/cms.js',
        $base . '/dbx/js/lib/cms-page.js',
        $base . '/dbx/js/lib/cms-tree.js',
        $base . '/dbx/js/lib/cms-media.js',
    )
);
foreach ($contractFiles as $file) {
    $source = $read($file);
    foreach ($legacyTokens as $token) {
        $assert(!str_contains($source, $token), basename($file) . ' enthält Legacy-Token ' . $token . '.');
    }
}

$sharedTemplates = array(
    'cms-admin-page-form',
    'cms-admin-folder-form',
    'cms-admin-settings-panels',
    'cms-field-content-template-select',
    'cms-field-folder-content-template-select',
    'cms-admin-left',
    'cms-admin-toolbar',
    'cms-admin-media-panel',
    'cms-external-video-form',
    'cms-media-upload-form',
    'cms-admin-right',
    'seo-admin',
);
foreach ($sharedTemplates as $name) {
    $assert(is_file($templateDir . '/' . $name . '.htm'), 'Basistemplate fehlt: ' . $name);
    foreach (array('_en', '_es') as $suffix) {
        $assert(
            !is_file($templateDir . '/' . $name . $suffix . '.htm'),
            'Sprachabhängiges Strukturduplikat vorhanden: ' . $name . $suffix . '.htm'
        );
    }
}

$requiredMessages = array(
    'page_section_title', 'folder_form_title', 'folder_form_subtitle',
    'hero_panel_title', 'hero_preview_empty', 'hero_preview_loading',
    'hero_preview_not_image', 'hero_image_alt', 'gallery_panel_title',
    'content_template_edit_title', 'content_template_edit_aria',
    'content_template_select_first', 'content_template_confirm_question',
    'content_template_confirm_hint', 'content_template_confirm_yes',
    'content_template_open_error', 'cancel_label', 'tree_loading',
    'media_filter_label', 'media_not_loaded', 'upload_drop_label',
    'external_video_placeholder',
);
foreach (array('' => 'de', '_en' => 'en', '_es' => 'es') as $suffix => $language) {
    $messages = array();
    require $module . '/fd/cms-page' . $suffix . '.fd.php';
    foreach ($requiredMessages as $key) {
        $assert(trim((string)($messages[$key] ?? '')) !== '', 'FD-Text fehlt für ' . $language . ': ' . $key);
    }
}

$cmsClass = dbx_test_module_source_bundle($module . '/include/dbxContent_cms.class.php');
$mediaForms = $read($module . '/include/dbxContentMediaForms.class.php');
$seoClass = $read($module . '/include/dbxContent_seo.class.php');
$contentList = $read($module . '/include/dbxContent_list.class.php');
$assert(str_contains($cmsClass, "get_system_obj('dbxTPL')"), 'CMS rendert nicht über dbxTPL.');
$assert(str_contains($cmsClass, "get_system_obj('dbxDB')"), 'CMS greift nicht über dbxDB zu.');
$assert(substr_count($cmsClass, "get_system_obj('dbxForm')") >= 3, 'CMS-Formulare laufen nicht einheitlich über dbxForm.');
$assert(str_contains($mediaForms, "get_system_obj('dbxForm'"), 'Medienformulare laufen nicht über dbxForm.');
$assert(str_contains($mediaForms, "get_system_obj('dbxTPL')"), 'Medienformulare rendern nicht über dbxTPL.');
$assert(str_contains($seoClass, "get_system_obj('dbxForm')"), 'SEO-Formular läuft nicht über dbxForm.');
$assert(
    str_contains($contentList, "get_system_obj('dbxReport'")
        && str_contains($contentList, 'extends \\dbxReport'),
    'Tabellarische Content-Listen laufen nicht über dbxReport.'
);
foreach (array($cmsClass, $mediaForms, $seoClass) as $source) {
    $assert(!preg_match('/\b(?:new\s+PDO|mysqli_|mysql_)\b/i', $source), 'Direkter Datenbankzugriff umgeht dbxDB.');
}
$assert(!str_contains($cmsClass, "['cache']['tpl']"), 'CMS manipuliert noch den alten Session-Templatecache.');

$cmsJs = $read($base . '/dbx/js/lib/cms.js')
    . $read($base . '/dbx/js/lib/cms-page.js')
    . $read($base . '/dbx/js/lib/cms-tree.js')
    . $read($base . '/dbx/js/lib/cms-media.js');
$assert(str_contains($cmsJs, 'function cmsFieldSelector(name, scope)'), 'Einheitlicher CMS-Feldselektor fehlt.');
$assert(str_contains($cmsJs, 'sourceEditor: "area"'), 'Jodit-Quelltextmodus ist nicht lokal/stabil konfiguriert.');
$assert(str_contains($cmsJs, 'beautifyHTML: false'), 'Jodit kann weiterhin externe Beautifier nachladen.');

foreach (array('dbxapp', 'dbxdocs', 'flowers', 'steal') as $design) {
    $cmsCss = $read($base . '/dbx/design/' . $design . '/css/c-cms.css');
    $formCss = $read($base . '/dbx/design/' . $design . '/css/c-form.css');
    foreach ($legacyTokens as $token) {
        $assert(!str_contains($cmsCss, $token), $design . '/c-cms.css enthält Legacy-Token ' . $token . '.');
    }
    foreach (array('.dbx-form-grid', '.dbx-form-grid--6', '.dbx-form-grid__item', '.dbx-form-field', '.dbx-form-field__control') as $token) {
        $assert(str_contains($formCss, $token), $design . '/c-form.css fehlt ' . $token . '.');
    }
}

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK CMS dbxTPL/dbxForm/dbxDB structure contract\n";
