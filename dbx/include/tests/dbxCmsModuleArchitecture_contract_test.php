<?php
declare(strict_types=1);

$base = dirname(__DIR__, 2);
$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};
$read = static function (string $file): string {
    return is_file($file) ? (string)file_get_contents($file) : '';
};

$coreFile = $base . '/js/lib/cms.js';
$pageFile = $base . '/js/lib/cms-page.js';
$treeFile = $base . '/js/lib/cms-tree.js';
$mediaFile = $base . '/js/lib/cms-media.js';
$languageFile = $base . '/js/lib/cms-language.js';
$joditImageFile = $base . '/js/lib/cms-jodit-image.js';
$templateFile = $base . '/modules/dbxContent_admin/tpl/htm/cms-admin.htm';

$core = $read($coreFile);
$page = $read($pageFile);
$tree = $read($treeFile);
$media = $read($mediaFile);
$language = $read($languageFile);
$joditImage = $read($joditImageFile);
$template = $read($templateFile);

foreach (array($coreFile, $pageFile, $treeFile, $mediaFile, $languageFile, $joditImageFile) as $file) {
    $assert(is_file($file) && filesize($file) > 0, 'CMS-JavaScriptdatei fehlt oder ist leer: ' . basename($file));
}

$assert(
    strlen($core) < 330000
        && str_contains($core, 'Content CMS runtime and stable editor core.')
        && str_contains($core, 'const CMS_MODULE_ASSETS = Object.freeze({')
        && str_contains($core, 'dbx.cmsRuntime = Object.freeze({'),
    'cms.js ist kein kompakter, expliziter Runtime-/Editor-Kern mehr.'
);
$assert(
    str_contains($page, 'runtime.register("page"')
        && str_contains($page, 'function loadPage(root, cfg, id)')
        && str_contains($page, 'function savePage(root, cfg)')
        && str_contains($page, 'function bind(root, cfg)'),
    'Seiten-, Formular- oder Aktionssteuerung ist nicht in cms-page.js gekapselt.'
);
$assert(
    str_contains($tree, 'runtime.register("tree"')
        && str_contains($tree, 'function renderTree(root)')
        && str_contains($tree, 'function ensureTreeLoaded(root, cfg)'),
    'Der Content-Baum ist nicht in cms-tree.js gekapselt.'
);
$assert(
    str_contains($media, 'runtime.register("media"')
        && str_contains($media, 'function openMediaBrowser(root, cfg, options)')
        && str_contains($media, 'function openMediaEdit(root, cfg, row)')
        && str_contains($media, 'function uploadMedia(root, cfg, form, options)'),
    'Medienbrowser und Medienbearbeitung sind nicht in cms-media.js gekapselt.'
);
$assert(
    str_contains($language, 'runtime.register("language"')
        && str_contains($language, 'function openLngProvisionDialog(root, cfg)')
        && str_contains($language, 'function openLngDeleteDialog(root, cfg, type, id, label)')
        && !str_contains($core, 'function showLngSyncResultModal(')
        && !str_contains($core, 'function showLngDeleteModal('),
    'Sprachabgleich und Sprachdialoge sind nicht vollstaendig lazy gekapselt.'
);
$assert(
    str_contains($joditImage, 'runtime.register("joditImage"')
        && str_contains($joditImage, 'function compactJoditImageDialog(root, panel)')
        && str_contains($joditImage, 'function openJoditImageDialogMediaBrowser(root, cfg, panel)')
        && str_contains($core, 'ensureCmsModule("joditImage")')
        && !str_contains($core, 'function compactJoditImageDialog(')
        && !str_contains($core, 'function applyMediaToJoditImageDialog('),
    'Die erweiterte Jodit-Bildintegration ist nicht bedarfsgerecht gekapselt.'
);

$featureStart = strpos($core, 'const cmsFeature = {');
$featureSource = $featureStart === false ? '' : substr($core, $featureStart);
$assert(
    str_contains($featureSource, '["js", "lib", "cms-page.js"]')
        && !str_contains($featureSource, '["js", "lib", "cms-tree.js"]')
        && !str_contains($featureSource, '["js", "lib", "cms-media.js"]')
        && !str_contains($featureSource, '["js", "lib", "cms-language.js"]')
        && !str_contains($featureSource, '["js", "lib", "cms-jodit-image.js"]')
        && str_contains($template, 'preload" href="dbx/js/lib/cms-page.js?v={dbx:asset_version}')
        && !str_contains($template, 'preload" href="dbx/js/lib/cms-tree.js')
        && !str_contains($template, 'preload" href="dbx/js/lib/cms-media.js')
        && !str_contains($template, 'preload" href="dbx/js/lib/cms-language.js')
        && !str_contains($template, 'preload" href="dbx/js/lib/cms-jodit-image.js'),
    'Eager Seitensteuerung und lazy Featuremodule sind nicht eindeutig getrennt.'
);

$assetStart = strpos($core, 'const CMS_MODULE_ASSETS = Object.freeze({');
$assetEnd = $assetStart === false ? false : strpos($core, '});', $assetStart);
$assetSource = ($assetStart === false || $assetEnd === false) ? '' : substr($core, $assetStart, $assetEnd - $assetStart + 3);
$assert(
    str_contains($assetSource, 'page: [["js", "lib", "cms-page.js"]]')
        && str_contains($assetSource, 'tree: [["js", "lib", "cms-tree.js"]]')
        && str_contains($assetSource, 'media: [["js", "lib", "cms-media.js"]]')
        && str_contains($assetSource, 'language: [["js", "lib", "cms-language.js"]]')
        && str_contains($assetSource, 'joditImage: [["js", "lib", "cms-jodit-image.js"]]'),
    'Der zentrale CMS-Modulkatalog ist unvollstaendig.'
);

$assert(
    str_contains($core, 'function refreshEditorCaretHint(root, explicitRange)')
        && str_contains($core, 'function setEditorCaretBesideElement(root, element, side)')
        && str_contains($core, 'function normalizeBootstrapComponents(surface)')
        && str_contains($core, 'function mediaOriginLabel(row)')
        && str_contains($media, 'mediaOriginLabel,')
        && !str_contains($media, 'function mediaOriginLabel(row)')
        && !str_contains($page . $tree . $media . $language . $joditImage, 'function refreshEditorCaretHint(')
        && !str_contains($page . $tree . $media . $language . $joditImage, 'function setEditorCaretBesideElement(')
        && !str_contains($page . $tree . $media . $language . $joditImage, 'function normalizeBootstrapComponents('),
    'Der stabile Cursor-/Editor-Kern wurde bei der Modultrennung verschoben.'
);

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK CMS module architecture keeps page eager and feature modules lazy.\n";
