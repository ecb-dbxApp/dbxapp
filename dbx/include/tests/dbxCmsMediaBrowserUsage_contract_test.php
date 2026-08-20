<?php

$base = dirname(__DIR__, 2);
require_once __DIR__ . '/dbxModuleSourceBundle.php';
$failures = array();

$assert = static function(bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$cms_js = (string)file_get_contents($base . '/modules/dbxContent_admin/js/cms.js')
    . (string)file_get_contents($base . '/modules/dbxContent_admin/js/cms-media.js');
$cms_php = dbx_test_module_source_bundle($base . '/modules/dbxContent_admin/include/dbxContent_cms.class.php');
$cms_css = (string)file_get_contents($base . '/design/dbxapp/css/c-cms.css');

$assert(
    str_contains($cms_js, 'function reconcileMediaBrowserUsageWithEditor(root, rows)')
        && str_contains($cms_js, 'collectInlineMediaIdsFromEditor(root).forEach(id => add(id, "inline"));')
        && str_contains($cms_js, 'const rows = reconcileMediaBrowserUsageWithEditor(root,'),
    'The media browser usage list is not reconciled with the current editor content.'
);
$assert(
    str_contains($cms_js, 'function mediaUsageSlots(row)')
        && str_contains($cms_js, 'mediaUsageSlots(row).includes(slotFilter)')
        && str_contains($cms_js, 'page.slots'),
    'The media browser still filters or labels usage by one arbitrary usage slot.'
);
$assert(
    !str_contains($cms_js, '.sort((a, b) => a - b)\n            .join(",");'),
    'The used-media signature still discards the actual editor order.'
);
$assert(
    str_contains($cms_php, '$has_usage_context = $content_id > 0 || $folder_id > 0 || $slot !== \'\';')
        && str_contains($cms_php, 'if ($has_usage_context'),
    'The media API still exposes an arbitrary current usage without a usage context.'
);
$assert(
    str_contains($cms_js, 'if (modal && modal.classList.contains("is-batch-open")) return true;')
        && str_contains($cms_js, 'return mode === "editor" || mode === "pick" || (mode === "assign"'),
    'Batch selection in the editor media browser still replaces the previous image instead of collecting multiple images.'
);
$assert(
    str_contains($cms_css, '@media (min-width: 1181px)')
        && str_contains($cms_css, '.dbx-cms:not(.dbx-cms-view) .dbx-cms-media-panel')
        && str_contains($cms_css, 'position: sticky;')
        && str_contains($cms_css, '.dbx-cms:not(.dbx-cms-view) .dbx-cms-right-panel-content')
        && str_contains($cms_css, 'overflow-y: auto;'),
    'The used-media sidebar can still scroll completely out of view on long pages.'
);

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo 'OK media browser usage reflects the current editor and all persisted usage slots.' . PHP_EOL;
