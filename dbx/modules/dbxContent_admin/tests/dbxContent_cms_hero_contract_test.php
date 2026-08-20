<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/dbxContent/include/dbxContentRenderer.class.php';
require_once dirname(__DIR__, 3) . '/include/tests/dbxCssTestReader.php';

use dbx\dbxContent\dbxContentRenderer;

$base = dirname(__DIR__, 4);
$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$cms_js = (string)file_get_contents($base . '/dbx/modules/dbxContent_admin/js/cms.js');
$cms_marker_js = (string)file_get_contents($base . '/dbx/modules/dbxContent_admin/js/cms-marker.js');
$cms_page_js = (string)file_get_contents($base . '/dbx/modules/dbxContent_admin/js/cms-page.js');
$settings_template = (string)file_get_contents($base . '/dbx/modules/dbxContent_admin/tpl/htm/cms-admin-settings-panels.htm');
$form_service = (string)file_get_contents($base . '/dbx/modules/dbxContent_admin/include/dbxContentCmsFormService.trait.php');
$assert(
    str_contains($cms_js, '{ label: "Hero", marker: "dbx:hero" }'),
    'Im Editor-Menü fehlt der kanonische Hero-Marker.'
);
$assert(
    !str_contains($cms_js, '"dbx:hero": "Hero-Text"')
        && !str_contains($cms_js, '{ label: "Hero-Text", marker: "dbx:hero" }'),
    'Der Editor bietet Hero noch als reinen Textbereich an.'
);
$assert(
    str_contains($cms_js, 'function dedupeSingletonMarkers(')
        && str_contains($cms_marker_js, 'function dedupeSingleton(')
        && str_contains($cms_marker_js, 'if (markerName !== "hero") return;'),
    'Mehrere Hero-Marker werden im Editor nicht auf einen Marker normalisiert.'
);
$assert(
    str_contains($settings_template, 'data-cms-action="clear-hero-media"')
        && str_contains($settings_template, '{hero_remove_label}')
        && str_contains($form_service, "'hero_remove_title'")
        && str_contains($form_service, "'hero_remove_label'"),
    'Die Hero-Einstellungen bieten keinen klaren, übersetzbaren Entfernen-Weg.'
);
$assert(
    str_contains($cms_page_js, 'if (name === "clear-hero-media")')
        && str_contains($cms_page_js, 'setField(root, "hero_image_id", "0")')
        && str_contains($cms_page_js, 'setField(root, "hero_template", "none")')
        && str_contains($cms_js, 'clearButton.disabled = !canClear;')
        && str_contains($cms_page_js, 'function handleCmsAction(root, cfg, action, event)'),
    'Hero entfernen setzt Zustand, Vorschau und Bedienbarkeit nicht konsistent.'
);

$hero_templates = glob($base . '/dbx/modules/dbxContent/tpl/htm/c-*hero*.htm') ?: array();
$assert(count($hero_templates) >= 4, 'Die erwarteten c-*-Hero-Templates fehlen.');
foreach ($hero_templates as $template_file) {
    $html = (string)file_get_contents($template_file);
    $assert(str_contains($html, '{cms:hero}'), basename($template_file) . ' enthält keinen Hero-Slot.');
    $assert(!str_contains($html, 'hero_text') && !str_contains($html, 'hero-text'), basename($template_file) . ' enthält noch einen getrennten Hero-Text-Slot.');
    $assert(substr_count($html, '{cms:hero}') === 1, basename($template_file) . ' muss genau einen Hero-Slot enthalten.');
}

$sections = (string)file_get_contents($base . '/dbx/modules/dbxContent_admin/include/dbxContent_sections.class.php');
$assert(!str_contains($sections, '{cms:hero_text}'), 'Neue Content-Templates erzeugen noch cms:hero_text.');

$reflection = new ReflectionClass(dbxContentRenderer::class);
$renderer = $reflection->newInstanceWithoutConstructor();
$detect = $reflection->getMethod('detect_template_slots');
$detect->setAccessible(true);
$parse = $reflection->getMethod('parse_content');
$parse->setAccessible(true);

$slots = $detect->invoke($renderer, '<section class="cms-hero"><div class="hero">{cms:hero}</div></section><main>{cms:col1}</main>');
$assert(!empty($slots['media']['hero']), 'cms:hero lädt das zugeordnete Hero-Medium nicht.');
$assert(!empty($slots['content']['hero']), 'cms:hero nimmt keinen freien Hero-Inhalt auf.');

$content = '<h2>Freier Hero</h2><figure><video src="hero.mp4"></video></figure>'
    . '<hr class="dbx-cms-marker dbx-cms-marker-hero_text" data-dbx-marker="dbx:hero_text">'
    . '<p>Seiteninhalt</p>';
$parsed = $parse->invoke($renderer, $content, $slots);
$assert(str_contains((string)($parsed['hero'] ?? ''), 'Freier Hero'), 'Hero-Überschrift wurde nicht dem Hero zugeordnet.');
$assert(str_contains((string)($parsed['hero'] ?? ''), '<video'), 'Ein Video kann nicht als freier Hero-Inhalt verwendet werden.');
$assert(str_contains((string)($parsed['body'] ?? ''), 'Seiteninhalt'), 'Inhalt hinter dem Hero-Marker fehlt im Body.');
$assert(!array_key_exists('hero_text', $parsed), 'Der Renderer liefert weiterhin einen getrennten hero_text-Bereich.');

foreach (array('dbxapp', 'steal') as $design) {
    $css = dbx_test_read_css($base . '/dbx/design/' . $design . '/css/c-content-frame.css');
    $assert(str_contains($css, '.hero-content'), 'Universelles Hero-Content-Styling fehlt in ' . $design . '.');
    $assert(!str_contains($css, '.hero-text') && !str_contains($css, 'cms-hero-text'), 'Veraltetes Hero-Text-Styling in ' . $design . '.');
}

foreach (array('dbxapp', 'flowers', 'steal') as $design) {
    $css = dbx_test_read_css($base . '/dbx/design/' . $design . '/css/c-cms.css');
    $assert(
        str_contains($css, '.dbx-cms-hero-actions .dbx-cms-hero-save'),
        'Die dreiteilige Hero-Aktion ist in ' . $design . ' nicht stabil angeordnet.'
    );
}

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK CMS uses one universal Hero marker for text, image and video content.\n";
