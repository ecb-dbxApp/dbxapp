<?php
declare(strict_types=1);

$base = dirname(__DIR__, 4);
$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$cmsJs = (string)file_get_contents($base . '/dbx/js/lib/cms.js')
    . (string)file_get_contents($base . '/dbx/js/lib/cms-page.js');
foreach (array(
    'editorMediaNodeById',
    'inlineMediaRowsFromEditor',
    'focusInlineMediaInEditor',
    'removeInlineMediaFromEditor',
    'openCmsImageEditor',
    'removeEditorImage',
) as $function) {
    $assert(str_contains($cmsJs, 'function ' . $function . '('), 'CMS editor function is missing: ' . $function);
}
$assert(
    str_contains($cmsJs, 'boxSlot === "inline"')
        && str_contains($cmsJs, 'inlineMediaRowsFromEditor(root, rows)'),
    'The inline media sidebar is not derived from the current editor DOM.'
);
foreach (array('data-cms-inline-focus', 'data-cms-media-edit-one', 'data-cms-inline-remove') as $attribute) {
    $assert(str_contains($cmsJs, $attribute), 'Inline media action is missing: ' . $attribute);
}
$assert(
    str_contains($cmsJs, 'title: getField(root, "title"),')
        && str_contains($cmsJs, 'menu_title: getField(root, "menu_title"),')
        && str_contains($cmsJs, '"title", "menu_title", "permalink"'),
    'menu_title is not loaded and saved by the CMS editor.'
);

$template = (string)file_get_contents(
    $base . '/dbx/modules/dbxContent_admin/tpl/htm/cms-admin-page-form.htm'
);
$titlePosition = strpos($template, '{obj:title}');
$menuTitlePosition = strpos($template, '{obj:menu_title}');
$editorPosition = strpos($template, 'data-cms-editor');
$assert(
    $titlePosition !== false
        && $menuTitlePosition !== false
        && $menuTitlePosition > $titlePosition
        && $editorPosition !== false
        && $menuTitlePosition < $editorPosition,
    'menu_title is not part of the shared page section.'
);

foreach (array('' => 'de', '_en' => 'en', '_es' => 'es') as $suffix => $language) {
    $messages = array();
    require $base . '/dbx/modules/dbxContent_admin/fd/cms-page' . $suffix . '.fd.php';
    foreach (array(
        'editor_image_edit',
        'editor_image_remove',
        'media_inline_focus',
        'media_inline_edit',
        'media_inline_remove',
        'media_inline_removed',
    ) as $key) {
        $assert(trim((string)($messages[$key] ?? '')) !== '', 'FD message is missing for ' . $language . ': ' . $key);
    }
}

foreach (array('dbxapp', 'dbxdocs', 'flowers', 'steal') as $design) {
    $css = (string)file_get_contents($base . '/dbx/design/' . $design . '/css/c-cms.css');
    $assert(
        str_contains($css, '.dbx-cms-page-panel > .dbx-form-grid')
            && str_contains($css, '.is-dbx-cms-selected'),
        'CMS page layout or inline media focus is missing in design ' . $design . '.'
    );
}

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK CMS inline media and menu title use one live editor workflow.\n";
