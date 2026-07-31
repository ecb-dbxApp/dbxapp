<?php

$base = dirname(__DIR__, 2);
$failures = array();

$assert = static function(bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$cmsJs = (string)file_get_contents($base . '/js/lib/cms.js');
$cmsPhp = (string)file_get_contents($base . '/modules/dbxContent_admin/include/dbxContent_cms.class.php');
$cmsCss = (string)file_get_contents($base . '/design/dbxapp/css/c-cms.css');

$assert(
    str_contains($cmsJs, 'function reconcileMediaBrowserUsageWithEditor(root, rows)')
        && str_contains($cmsJs, 'collectInlineMediaIdsFromEditor(root).forEach(id => add(id, "inline"));')
        && str_contains($cmsJs, 'const rows = reconcileMediaBrowserUsageWithEditor(root,'),
    'The media browser usage list is not reconciled with the current editor content.'
);
$assert(
    str_contains($cmsJs, 'function mediaUsageSlots(row)')
        && str_contains($cmsJs, 'mediaUsageSlots(row).includes(slotFilter)')
        && str_contains($cmsJs, 'page.slots'),
    'The media browser still filters or labels usage by one arbitrary usage slot.'
);
$assert(
    !str_contains($cmsJs, '.sort((a, b) => a - b)\n            .join(",");'),
    'The used-media signature still discards the actual editor order.'
);
$assert(
    str_contains($cmsPhp, '$has_usage_context = $content_id > 0 || $folder_id > 0 || $slot !== \'\';')
        && str_contains($cmsPhp, 'if ($has_usage_context'),
    'The media API still exposes an arbitrary current usage without a usage context.'
);
$assert(
    str_contains($cmsJs, 'if (modal && modal.classList.contains("is-batch-open")) return true;')
        && str_contains($cmsJs, 'return mode === "editor" || mode === "pick" || (mode === "assign"'),
    'Batch selection in the editor media browser still replaces the previous image instead of collecting multiple images.'
);
$assert(
    str_contains($cmsCss, '@media (min-width: 1181px)')
        && str_contains($cmsCss, '.dbx-cms:not(.dbx-cms-view) .dbx-cms-media-panel')
        && str_contains($cmsCss, 'position: sticky;')
        && str_contains($cmsCss, '.dbx-cms:not(.dbx-cms-view) .dbx-cms-right-panel-content')
        && str_contains($cmsCss, 'overflow-y: auto;'),
    'The used-media sidebar can still scroll completely out of view on long pages.'
);

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo 'OK media browser usage reflects the current editor and all persisted usage slots.' . PHP_EOL;
