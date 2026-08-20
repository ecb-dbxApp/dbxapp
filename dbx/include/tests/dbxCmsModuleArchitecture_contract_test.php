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

$core_file = $base . '/modules/dbxContent_admin/js/cms.js';
$marker_file = $base . '/modules/dbxContent_admin/js/cms-marker.js';
$context_file = $base . '/modules/dbxContent_admin/js/cms-context.js';
$components_file = $base . '/modules/dbxContent_admin/js/cms-components.js';
$editor_file = $base . '/modules/dbxContent_admin/js/cms-editor.js';
$page_file = $base . '/modules/dbxContent_admin/js/cms-page.js';
$tree_file = $base . '/modules/dbxContent_admin/js/cms-tree.js';
$media_file = $base . '/modules/dbxContent_admin/js/cms-media.js';
$language_file = $base . '/modules/dbxContent_admin/js/cms-language.js';
$jodit_image_file = $base . '/modules/dbxContent_admin/js/cms-jodit-image.js';
$template_file = $base . '/modules/dbxContent_admin/tpl/htm/cms-admin.htm';

$core = $read($core_file);
$marker = $read($marker_file);
$context = $read($context_file);
$components = $read($components_file);
$editor = $read($editor_file);
$page = $read($page_file);
$tree = $read($tree_file);
$media = $read($media_file);
$language = $read($language_file);
$jodit_image = $read($jodit_image_file);
$template = $read($template_file);

foreach (array($core_file, $marker_file, $context_file, $components_file, $editor_file, $page_file, $tree_file, $media_file, $language_file, $jodit_image_file) as $file) {
    $assert(is_file($file) && filesize($file) > 0, 'CMS-JavaScriptdatei fehlt oder ist leer: ' . basename($file));
}

$assert(
    strlen($core) < 330000
        && str_contains($core, 'Content CMS coordination and feature runtime.')
        && str_contains($core, 'const CMS_MODULE_ASSETS = Object.freeze({')
        && str_contains($core, 'dbx.cmsRuntime = Object.freeze({'),
    'cms.js ist kein kompakter, expliziter Runtime-/Editor-Kern mehr.'
);
$assert(
    str_contains($editor, 'runtime.register("editor"')
        && str_contains($editor, 'function setEditorHtml(root, html)')
        && str_contains($editor, 'function cleanEditorRuntimeNodes(container)')
        && str_contains($editor, 'function refreshEditorCaretHint(root, explicitRange)')
        && str_contains($core, '["js", "module", "dbxContent_admin/cms-editor.js"]')
        && str_contains($core, 'return callCmsModuleSync("editor", "setEditorHtml"'),
    'Editor-DOM, Caret und HTML-Zustand sind nicht eigenstaendig gekapselt.'
);
$assert(
    str_contains($components, 'runtime.register("components"')
        && str_contains($components, 'function bootstrapComponentItems(root)')
        && str_contains($components, 'function normalizeBootstrapComponents(surface)')
        && str_contains($components, 'function bindEditableBadgeEditing(surface)')
        && str_contains($core, '["js", "module", "dbxContent_admin/cms-components.js"]')
        && str_contains($core, 'return callCmsModuleSync("components", "normalizeBootstrapComponents"'),
    'Bootstrap-Komponenten und ihre Editor-Runtime sind nicht eigenstaendig gekapselt.'
);
$assert(
    str_contains($context, 'runtime.register("context"')
        && str_contains($context, 'function showEditorContextMenu(root, e)')
        && str_contains($context, 'function copyEditorContext(root, target)')
        && str_contains($context, 'function pasteEditorContext(root, target)')
        && str_contains($core, '["js", "module", "dbxContent_admin/cms-context.js"]')
        && str_contains($core, 'return callCmsModuleSync("context", "clipboardWriteText"')
        && !str_contains($core, 'navigator.clipboard.writeText'),
    'Editor-Kontextmenue und Zwischenablage sind nicht eigenstaendig gekapselt.'
);
$assert(
    str_contains($marker, 'window.dbx.cmsMarker = Object.freeze({')
        && str_contains($marker, 'function normalizeComments(container)')
        && str_contains($marker, 'function normalizePlainText(container)')
        && str_contains($marker, 'function serialize(sourceHtml, cleanRuntimeNodes)')
        && str_contains($core, '["js", "module", "dbxContent_admin/cms-marker.js"]')
        && !str_contains($core, 'function ignorableMarkerSibling('),
    'Marker-Erzeugung und -Normalisierung sind nicht eigenstaendig gekapselt.'
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
    str_contains($jodit_image, 'runtime.register("joditImage"')
        && str_contains($jodit_image, 'function compactJoditImageDialog(root, panel)')
        && str_contains($jodit_image, 'function openJoditImageDialogMediaBrowser(root, cfg, panel)')
        && str_contains($core, 'ensureCmsModule("joditImage")')
        && !str_contains($core, 'function compactJoditImageDialog(')
        && !str_contains($core, 'function applyMediaToJoditImageDialog('),
    'Die erweiterte Jodit-Bildintegration ist nicht bedarfsgerecht gekapselt.'
);

$feature_start = strpos($core, 'const cmsFeature = {');
$feature_source = $feature_start === false ? '' : substr($core, $feature_start);
$assert(
    str_contains($feature_source, '["js", "module", "dbxContent_admin/cms-page.js"]')
        && str_contains($feature_source, '["js", "module", "dbxContent_admin/cms-context.js"]')
        && str_contains($feature_source, '["js", "module", "dbxContent_admin/cms-marker.js"]')
        && !str_contains($feature_source, '["js", "module", "dbxContent_admin/cms-tree.js"]')
        && !str_contains($feature_source, '["js", "module", "dbxContent_admin/cms-media.js"]')
        && !str_contains($feature_source, '["js", "module", "dbxContent_admin/cms-language.js"]')
        && !str_contains($feature_source, '["js", "module", "dbxContent_admin/cms-jodit-image.js"]')
        && str_contains($template, 'preload" href="dbx/modules/dbxContent_admin/js/cms-page.js?v={dbx:asset_version}')
        && !str_contains($template, 'preload" href="dbx/modules/dbxContent_admin/js/cms-tree.js')
        && !str_contains($template, 'preload" href="dbx/modules/dbxContent_admin/js/cms-media.js')
        && !str_contains($template, 'preload" href="dbx/modules/dbxContent_admin/js/cms-language.js')
        && !str_contains($template, 'preload" href="dbx/modules/dbxContent_admin/js/cms-jodit-image.js'),
    'Eager Seitensteuerung und lazy Featuremodule sind nicht eindeutig getrennt.'
);

$asset_start = strpos($core, 'const CMS_MODULE_ASSETS = Object.freeze({');
$asset_end = $asset_start === false ? false : strpos($core, '});', $asset_start);
$asset_source = ($asset_start === false || $asset_end === false) ? '' : substr($core, $asset_start, $asset_end - $asset_start + 3);
$assert(
    str_contains($asset_source, 'page: [["js", "module", "dbxContent_admin/cms-page.js"]]')
        && str_contains($asset_source, 'tree: [["js", "module", "dbxContent_admin/cms-tree.js"]]')
        && str_contains($asset_source, 'media: [["js", "module", "dbxContent_admin/cms-media.js"]]')
        && str_contains($asset_source, 'language: [["js", "module", "dbxContent_admin/cms-language.js"]]')
        && str_contains($asset_source, 'joditImage: [["js", "module", "dbxContent_admin/cms-jodit-image.js"]]')
        && str_contains($asset_source, 'context: [["js", "module", "dbxContent_admin/cms-context.js"]]')
        && str_contains($asset_source, 'components: [["js", "module", "dbxContent_admin/cms-components.js"]]')
        && str_contains($asset_source, 'editor: [["js", "module", "dbxContent_admin/cms-editor.js"]]'),
    'Der zentrale CMS-Modulkatalog ist unvollstaendig.'
);

$assert(
    str_contains($editor, 'function refreshEditorCaretHint(root, explicitRange)')
        && str_contains($core, 'function setEditorCaretBesideElement(root, element, side)')
        && str_contains($components, 'function normalizeBootstrapComponents(surface)')
        && str_contains($core, 'function mediaOriginLabel(row)')
        && str_contains($media, 'mediaOriginLabel,')
        && !str_contains($media, 'function mediaOriginLabel(row)')
        && !str_contains($page . $tree . $media . $language . $jodit_image . $context . $components, 'function refreshEditorCaretHint(')
        && !str_contains($page . $tree . $media . $language . $jodit_image, 'function setEditorCaretBesideElement(')
        && !str_contains($page . $tree . $media . $language . $jodit_image . $context, 'function normalizeBootstrapComponents('),
    'Der stabile Cursor-/Editor-Kern wurde bei der Modultrennung verschoben.'
);

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK CMS module architecture keeps page eager and feature modules lazy.\n";
