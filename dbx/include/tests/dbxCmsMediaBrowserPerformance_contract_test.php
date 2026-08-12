<?php

$base = dirname(__DIR__, 2);
require_once __DIR__ . '/dbxModuleSourceBundle.php';
$failures = array();

$assert = static function(bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$cmsJs = (string)file_get_contents($base . '/js/lib/cms.js')
    . (string)file_get_contents($base . '/js/lib/cms-media.js');
$cmsPhp = dbx_test_module_source_bundle($base . '/modules/dbxContent_admin/include/dbxContent_cms.class.php');
$cmsCss = (string)file_get_contents($base . '/design/dbxapp/css/c-cms.css');

$assert(
    str_contains($cmsJs, 'mediaParams.sync = 0;')
        && str_contains($cmsJs, 'mediaParams.limit = 28;')
        && str_contains($cmsJs, 'const folderRefresh = new Promise(resolve => window.setTimeout(resolve, 250))')
        && str_contains($cmsJs, '.then(() => refreshMediaFolderControls(root, cfg, modal))')
        && str_contains($cmsJs, 'fetchJson(apiUrl(mediaUrl, mediaParams)'),
    'The initial media request is still blocked by a full filesystem sync or folder scan.'
);
$assert(
    str_contains($cmsJs, 'const loadRemaining = () => {')
        && str_contains($cmsJs, 'limit: 84,')
        && str_contains($cmsJs, 'rows.push(row);'),
    'Remaining media are not loaded dynamically after the visible first page.'
);
$assert(
    str_contains($cmsPhp, '$select_limit = $limit > 0 ? $limit + 1 : 0;')
        && str_contains($cmsPhp, "'has_more' => \$has_more ? 1 : 0")
        && str_contains($cmsPhp, "'next_offset' => \$limit > 0"),
    'The media API does not support fast paginated responses.'
);
$assert(
    str_contains($cmsPhp, "' AND media_id IN (' . implode(',', \$page_media_ids) . ')'")
        && str_contains($cmsPhp, 'if ($limit <= 0) $rows = $this->filter_existing_media($db, $rows);'),
    'Paginated media requests still scan every usage row or every media file.'
);
$assert(
    str_contains($cmsJs, 'modal.__dbxCmsFilteredRows = filtered;')
        && str_contains($cmsJs, 'const sourceRows = Array.isArray(browserModal.__dbxCmsFilteredRows)')
        && str_contains($cmsJs, '<span>Alle angezeigten resizen</span>'),
    'Batch resize still renders or processes the complete media library instead of the currently displayed filter.'
);
$assert(
    str_contains($cmsJs, 'data-cms-media-edit-status aria-live="polite"')
        && str_contains($cmsJs, 'function reportMediaEditStatus(root, modal, message, type)')
        && str_contains($cmsCss, '.dbx-cms-media-edit-status.is-success'),
    'Image resize and crop feedback is still hidden behind the active editor dialog.'
);
$assert(
    str_contains($cmsJs, 'Array.from(qs(modal, "[data-cms-upload-folder]")?.options || [])')
        && str_contains($cmsJs, '.filter(folder => folder.indexOf("img/") === 0);'),
    'Media maintenance has no immediate folder fallback while the asynchronous folder refresh is running.'
);

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo 'OK media browser renders the first page quickly and loads the rest dynamically.' . PHP_EOL;
