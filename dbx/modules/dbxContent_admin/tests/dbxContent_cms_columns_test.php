<?php
declare(strict_types=1);

$base = dirname(__DIR__, 4);
$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$cms_js = (string)file_get_contents($base . '/dbx/modules/dbxContent_admin/js/cms.js');
foreach (array(
    'bootstrapRowColumns',
    'setBootstrapColumnLayout',
    'addBootstrapColumn',
    'dissolveBootstrapColumns',
) as $function) {
    $assert(
        str_contains($cms_js, 'function ' . $function . '('),
        'CMS-Spaltenfunktion fehlt: ' . $function
    );
}

$keys = array(
    'editor_columns_two',
    'editor_columns_three',
    'editor_columns_first',
    'editor_columns_second',
    'editor_columns_third',
    'editor_columns_new',
    'editor_columns_stacked',
    'editor_columns_responsive',
    'editor_column_add',
    'editor_columns_dissolve',
    'editor_context_menu',
    'editor_context_undo',
    'editor_context_redo',
    'editor_context_select_all',
    'editor_context_block_up',
    'editor_context_block_down',
    'editor_context_module',
    'editor_context_video',
    'editor_context_copy',
    'editor_context_cut',
    'editor_context_paste',
    'editor_context_delete',
);
$messages_by_language = array();
foreach (array('de' => '', 'en' => '_en', 'es' => '_es') as $language => $suffix) {
    $messages = array();
    require $base . '/dbx/modules/dbxContent_admin/fd/cms-page' . $suffix . '.fd.php';
    $messages_by_language[$language] = $messages;
    foreach ($keys as $key) {
        $assert(
            trim((string)($messages[$key] ?? '')) !== '',
            'FD-Meldung fehlt für ' . $language . ': ' . $key
        );
    }
}

foreach (array('dbxapp', 'flowers', 'steal') as $design) {
    $css = (string)file_get_contents($base . '/dbx/design/' . $design . '/css/c-cms.css');
    $assert(
        str_contains($css, '.jodit-wysiwyg .row:has(> .col, > [class*="col-"]) > .col:hover')
            && !str_contains($css, 'border: 1px dashed transparent;'),
        'Bearbeitbare Spalten brauchen eine lokale, layoutneutrale Markierung: ' . $design
    );
}

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK dbxContent CMS columns\n";
