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
$open_win = $read($base . '/js/lib/openWin.js');
$cms_files = array(
    $base . '/modules/dbxContent_admin/js/cms.js',
    $base . '/modules/dbxContent_admin/js/cms-page.js',
    $base . '/modules/dbxContent_admin/js/cms-tree.js',
    $base . '/modules/dbxContent_admin/js/cms-media.js',
    $base . '/modules/dbxContent_admin/js/cms-language.js',
    $base . '/modules/dbxContent_admin/js/cms-jodit-image.js'
);
$cms = implode("\n", array_map($read, $cms_files));

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
    str_contains($open_win, 'data-dbx-layer="openwin"')
        && str_contains($open_win, 'data-dbx-escape-owner="openwin"')
        && str_contains($open_win, 'data-dbx-layer="openwin-overlay"')
        && str_contains($open_win, 'dbx.uiLayer.top({ selector: "[data-dbx-escape-owner]" })')
        && str_contains($open_win, 'this.dispatchLifecycle("dbx:openwin-before-close", windowData);')
        && str_contains($open_win, 'const inlineContent = cfg && Object.prototype.hasOwnProperty.call(cfg, "content")')
        && str_contains($open_win, 'if (inlineContent !== undefined) cfg.content = inlineContent;')
        && str_contains($open_win, 'dbx.ajax.request(requestConfig)')
        && !str_contains($open_win, '$.ajax(')
        && !str_contains($open_win, 'fetch(')
        && !str_contains($open_win, 'new XMLHttpRequest'),
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

foreach ($cms_files as $file) {
    $source = $read($file);
    $assert(
        !str_contains($source, 'fetch(')
            && !str_contains($source, '$.ajax(')
            && !str_contains($source, 'new XMLHttpRequest')
            && !str_contains($source, 'window.open('),
        basename($file) . ' umgeht openWin.js oder ajax.js.'
    );
}

foreach (array('dbxapp', 'steal') as $design) {
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
