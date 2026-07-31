<?php

$base = dirname(__DIR__, 2);
$source = (string)file_get_contents($base . '/js/lib/cms.js');
$template = (string)file_get_contents($base . '/modules/dbxContent_admin/tpl/htm/cms-admin.htm');
$failures = array();

$assert = static function(bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$assert(
    str_contains($source, '__dbxCmsEditorHeightTimers')
        && str_contains($source, 'root.__dbxCmsEditorHeightTimers.forEach(timer => window.clearTimeout(timer))'),
    'Editor height reflow timers are not deduplicated.'
);
$assert(
    !str_contains($source, '[80, 250, 800].forEach(delay => window.setTimeout(() => syncEditorHeight(root), delay))'),
    'Every editor input still creates three uncancelled layout timers.'
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
    str_contains($template, 'rel="preload" href="dbx/js/lib/cms.js?v=90" as="script" fetchpriority="low"')
        && str_contains($template, 'rel="preload" href="dbx/vendor/jodit/jodit.fat.min.js?v=90" as="script" fetchpriority="low"')
        && str_contains($template, 'rel="preload" href="dbx/vendor/jodit/jodit.fat.min.css?v=90" as="style" fetchpriority="low"')
        && str_contains($template, 'rel="preload" href="dbx/design/{dbx:design}/css/c-cms.css?v=90" as="style" fetchpriority="low"'),
    'Large CMS/editor assets are not requested early on the CMS page.'
);

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo 'OK CMS editor layout and media refresh work is deduplicated.' . PHP_EOL;
