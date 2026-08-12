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

$core = $read($base . '/js/lib/core.js');
$confirm = $read($base . '/js/lib/confirm.js');
$openWin = $read($base . '/js/lib/openWin.js');
$cmsFiles = array(
    $base . '/js/lib/cms.js',
    $base . '/js/lib/cms-page.js',
    $base . '/js/lib/cms-tree.js',
    $base . '/js/lib/cms-media.js',
    $base . '/js/lib/cms-language.js',
    $base . '/js/lib/cms-jodit-image.js'
);
$cms = implode("\n", array_map($read, $cmsFiles));

$assert(
    str_contains($core, 'dbx.uiLayer = {')
        && str_contains($core, 'isRendered: uiLayerIsRendered')
        && str_contains($core, 'ancestorZIndex: uiLayerAncestorZIndex')
        && str_contains($core, 'return el.getClientRects().length > 0;')
        && str_contains($core, 'next(options)')
        && str_contains($core, 'top(options)'),
    'Core stellt kein gemeinsames, auch fuer position:fixed geeignetes UI-Layer-System bereit.'
);

$assert(
    str_contains($confirm, 'dbx.uiLayer.next({')
        && !str_contains($confirm, 'el.offsetParent !== null')
        && str_contains($confirm, 'data-dbx-layer", "confirm-overlay')
        && str_contains($confirm, 'data-dbx-escape-owner", "confirm')
        && str_contains($confirm, 'role", "alertdialog')
        && str_contains($confirm, 'aria-modal", "true')
        && str_contains($confirm, 'function focusDialog(entry)')
        && str_contains($confirm, 'function dialogFocusableElements(dialog)')
        && str_contains($confirm, 'e.stopImmediatePropagation'),
    'Bestaetigungen sind nicht sichtbar, fokussiert und exklusiv in die zentrale Ebenen-/Escape-Steuerung integriert.'
);

$assert(
    str_contains($openWin, 'data-dbx-layer="openwin"')
        && str_contains($openWin, 'data-dbx-escape-owner="openwin"')
        && str_contains($openWin, 'data-dbx-layer="openwin-overlay"')
        && str_contains($openWin, 'dbx.uiLayer.top({ selector: "[data-dbx-escape-owner]" })')
        && str_contains($openWin, 'this.dispatchLifecycle("dbx:openwin-before-close", windowData);')
        && str_contains($openWin, 'const inlineContent = cfg && Object.prototype.hasOwnProperty.call(cfg, "content")')
        && str_contains($openWin, 'if (inlineContent !== undefined) cfg.content = inlineContent;')
        && str_contains($openWin, 'dbx.ajax.request(requestConfig)')
        && !str_contains($openWin, '$.ajax(')
        && !str_contains($openWin, 'fetch(')
        && !str_contains($openWin, 'new XMLHttpRequest'),
    'openWin nutzt nicht durchgaengig die zentrale Ebenensteuerung, erhaelt DOM-Instanzen nicht oder umgeht ajax.js.'
);

$assert(
    str_contains($cms, 'function bindJoditFullscreenLayer(root)')
        && str_contains($cms, 'dbx-cms-editor-fullsize-portal')
        && str_contains($cms, 'data-dbx-layer", "editor-fullscreen')
        && str_contains($cms, 'dbx.uiLayer.next({ floor: 5000, step: 20')
        && str_contains($cms, 'attributeFilter: ["class"]')
        && str_contains($cms, 'if (e.isTrusted === false) return;')
        && str_contains($cms, 'dbx:openwin-before-close')
        && !str_contains($cms, '250000'),
    'Jodit-Vollbild ist nicht als performanter, dynamischer UI-Layer ohne feste Sonder-Zahl umgesetzt.'
);

foreach ($cmsFiles as $file) {
    $source = $read($file);
    $assert(
        !str_contains($source, 'fetch(')
            && !str_contains($source, '$.ajax(')
            && !str_contains($source, 'new XMLHttpRequest')
            && !str_contains($source, 'window.open('),
        basename($file) . ' umgeht openWin.js oder ajax.js.'
    );
}

foreach (array('dbxapp', 'steal', 'dbxdocs') as $design) {
    $css = $read($base . '/design/' . $design . '/css/c-cms.css');
    $assert(
        str_contains($css, '.dbx-cms-editor-group .jodit-container.jodit_fullsize')
            && str_contains($css, 'position: fixed !important;')
            && str_contains($css, 'height: 100dvh !important;'),
        'Das eigenstaendige Design ' . $design . ' unterstuetzt den portierten Editor-Vollbildlayer nicht.'
    );
}

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK UI layers, Escape ownership, openWin and ajax routing are centralized.\n";
