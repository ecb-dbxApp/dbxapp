<?php

$base = dirname(__DIR__, 2);
$source = (string)file_get_contents($base . '/modules/dbxContent_admin/js/cms.js')
    . (string)file_get_contents($base . '/modules/dbxContent_admin/js/cms-context.js')
    . (string)file_get_contents($base . '/modules/dbxContent_admin/js/cms-components.js')
    . (string)file_get_contents($base . '/modules/dbxContent_admin/js/cms-editor.js');
$template = (string)file_get_contents($base . '/modules/dbxContent_admin/tpl/htm/cms-admin.htm');
$failures = array();

$assert = static function(bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$assert(
    str_contains($source, '__dbxCmsEditorHeightFrame')
        && str_contains($source, 'window.cancelAnimationFrame(root.__dbxCmsEditorHeightFrame)')
        && str_contains($source, 'const surfaceRect = surface.getBoundingClientRect')
        && !str_contains($source, '__dbxCmsEditorHeightTimers'),
    'Editor height reflow is not frame-deduplicated or still schedules delayed layout changes.'
);
$assert(
    !str_contains($source, '[80, 250, 800].forEach(delay => window.setTimeout(() => syncEditorHeight(root), delay))'),
    'Every editor input still creates three uncancelled layout timers.'
);
$assert(
    str_contains($source, 'Jodit meldet jedoch auch beim')
        && str_contains($source, 'blossen Fokussieren nachtraeglich ein change-Event'),
    'A focus-only Jodit change can still trigger a delayed editor-height reflow.'
);
$assert(
    str_contains($source, 'function editorHtmlSnapshot(surface)')
        && str_contains($source, 'const snapshot = surface.cloneNode(true);')
        && !str_contains($source, 'cleanEditorRuntimeNodes(surface)')
        && !str_contains($source, 'instance.value = repairedHtml')
        && !str_contains($source, 'ensureLeadingEditorParagraph(surface);')
        && str_contains($source, 'function setEditorCaretBesideElement(root, element, side)'),
    'Loading or saving still cleans and rebuilds the live editor DOM or inserts an automatic blank paragraph.'
);
$assert(
    str_contains($source, 'root.__dbxCmsApplyingEditorHtml = true;')
        && str_contains($source, 'if (root.__dbxCmsApplyingEditorHtml) return;')
        && str_contains($source, 'if (!root.__dbxCmsUserEditPending) return;')
        && str_contains($source, 'root.__dbxCmsUserEditPending = true;')
        && str_contains($source, 'root.__dbxCmsApplyingEditorHtml = false;'),
    'Jodit runtime normalization can still mark an unchanged page dirty while loading.'
);
$assert(
    str_contains($source, 'const incoming = document.createElement("div");')
        && str_contains($source, 'cleanEditorRuntimeNodes(incoming);')
        && !str_contains($source, 'const cleanHtml = surface ? editorHtmlSnapshot(surface) : html;'),
    'Initial cleanup is not detached from the visible editor or the input path still clones the full DOM.'
);
$assert(
    str_contains($source, 'function editorInlineMediaSignature(root, html)')
        && str_contains($source, 'if (signature === root.__dbxCmsMediaRenderSignature) return;'),
    'The media sidebar is still rebuilt without checking whether media changed.'
);
$assert(
    str_contains($source, '__dbxCmsBootstrapNormalizeTimer')
        && str_contains($source, 'window.clearTimeout(surface.__dbxCmsBootstrapNormalizeTimer)'),
    'Bootstrap component normalization is not debounced.'
);
$assert(
    !str_contains($source, 'jodit.e.fire("togglePopup", "dbxMarkerMenu")')
        && !str_contains($source, 'jodit.e.fire("togglePopup", "dbxBootstrapComponents")')
        && !str_contains($source, 'jodit.e.fire("togglePopup", "dbxTextStyle")'),
    'Custom Jodit popup controls still override Jodit popup handling with a competing exec callback.'
);
$assert(
    substr_count($source, 'popup: function (jodit, current, close)') >= 3
        && !str_contains($source, 'popup: function (jodit, current, control, close)'),
    'Custom Jodit popup callbacks do not use the installed Jodit close-callback argument order.'
);
$assert(
    str_contains($source, 'if (!node.parentNode || !surface.contains(node))')
        && str_contains($source, 'nodes.slice().reverse().find(node => node.parentNode && surface.contains(node))'),
    'The editor caret can still be positioned after a component node removed during normalization.'
);
$assert(
    str_contains($source, 'const inlineMedia = closestElement(target, ".dbx-cms-inline-media")')
        && str_contains($source, 'if (inlineMedia && surface.contains(inlineMedia)) return inlineMedia;'),
    'Move actions still target the image element instead of its inline-media block.'
);
$assert(
    str_contains($source, 'bootstrapRowColumns(row).length || qs(row, ".card")')
        && str_contains($source, 'tabsChildren.some(child => child.classList?.contains("nav-tabs"))')
        && str_contains($source, 'tabsChildren.some(child => child.classList?.contains("tab-content"))'),
    'Multi-column, card-row, or tab components are not selected as one movable component.'
);
$assert(
    str_contains($source, 'const el = editorContextBlock(root, target);'),
    'Ordinary component blocks such as CTA and openWin paragraphs are not movable or removable.'
);
$assert(
    str_contains($source, 'const inWindow = !!closestElement(root, ".dbx-window-body");')
        && str_contains($source, 'const height = inWindow')
        && str_contains($source, 'root.__dbxCmsStickyResizeObserver = new ResizeObserver'),
    'CMS windows still inherit the unrelated app-header sticky offset.'
);
$cms_css = (string)file_get_contents($base . '/design/dbxapp/css/c-cms.css');
$assert(
    str_contains($cms_css, '.dbx-window-body .dbx-panel.dbx-cms.dbxReport')
        && str_contains($cms_css, 'overflow-x: clip'),
    'CMS windows can still create an unnecessary horizontal scrollbar.'
);
$assert(
    str_contains($cms_css, '.dbx-cms-media-browser-window:not(.dbx-window-mobile-fullscreen)')
        && str_contains($cms_css, 'position: fixed !important')
        && str_contains($cms_css, 'transform: translate(-50%, -50%) !important'),
    'The media browser can still be positioned outside the viewport after editor scrolling.'
);
$assert(
    str_contains($source, 'Promise.resolve(loadInitialSelection(el, cfg || {})).finally(() => {')
        && str_contains($source, 'function settleStickyHeaderOffset(root)')
        && str_contains($source, 'settleStickyHeaderOffset(el);'),
    'Sticky offsets are not recalculated after the initial CMS page AJAX load.'
);
$assert(
    str_contains($source, 'function waitForCmsCriticalStyles(root, done)')
        && str_contains($source, 'root.classList.remove("dbx-cms-booting")')
        && str_contains($template, 'dbx-cms-booting')
        && str_contains($template, 'data-dbx-cms-critical="{i}"'),
    'CMS markup can still become visible before its critical styles are ready.'
);
$asset_version = '{dbx:asset_version}';
$assert(
    str_contains($template, 'rel="preload" href="dbx/modules/dbxContent_admin/js/cms.js?v=' . $asset_version . '" as="script" fetchpriority="high"')
        && str_contains($template, 'rel="preload" href="dbx/modules/dbxContent_admin/js/cms-page.js?v=' . $asset_version . '" as="script" fetchpriority="high"')
        && str_contains($template, 'rel="preload" href="dbx/vendor/jodit/jodit.fat.min.js?v=' . $asset_version . '" as="script" fetchpriority="high"')
        && str_contains($template, 'rel="stylesheet" href="dbx/vendor/jodit/jodit.fat.min.css?v=' . $asset_version . '" fetchpriority="high" data-dbx-cms-critical="{i}"')
        && str_contains($template, 'rel="stylesheet" href="dbx/design/{dbx:design}/css/c-cms.css?v=' . $asset_version . '" fetchpriority="high" data-dbx-cms-critical="{i}"')
        && str_contains($template, 'rel="stylesheet" href="dbx/design/{dbx:design}/css/c-form.css?v=' . $asset_version . '" fetchpriority="high" data-dbx-cms-critical="{i}"')
        && str_contains($template, 'rel="stylesheet" href="dbx/design/{dbx:design}/css/c-grid.css?v=' . $asset_version . '" fetchpriority="high" data-dbx-cms-critical="{i}"'),
    'CMS/editor styles are not render-blocking high-priority stylesheets.'
);
$assert(
    str_contains($source, 'const editorAssetReady = isViewMode(cfg || {})')
        && str_contains($source, ': ensureJodit();')
        && str_contains($source, 'waitForCmsCriticalStyles(el, () => {')
        && str_contains($source, 'editorAssetReady.then(ok => {'),
    'Jodit wird weiterhin erst nach den kritischen CMS-Styles geladen.'
);
$assert(
    str_contains($template, 'class="dbx-panel dbx-cms dbxReport is-tree-collapsed dbx-cms-booting"'),
    'The initially closed content tree is still visible before CMS initialization.'
);

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo 'OK CMS editor layout and media refresh work is deduplicated.' . PHP_EOL;
