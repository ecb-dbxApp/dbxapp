<?php
declare(strict_types=1);

/**
 * Vertrag: Persistierte, minimierte Fenster bleiben beim Seitenstart leicht
 * und laden ihren AJAX-Inhalt erst bei der sichtbaren Wiederherstellung.
 */

$root = dirname(__DIR__, 2);
$open_win = (string)file_get_contents($root . '/js/lib/openWin.js');
$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$assert(
    str_contains($open_win, 'const deferContent = !isMobileViewport && restoreMinimized && !hasInlineContent;')
        && str_contains($open_win, 'contentState: deferContent ? "deferred" : "idle"')
        && str_contains($open_win, '} else if (!deferContent) {')
        && str_contains($open_win, 'this.ensureWindowContent(windowData);'),
    'Minimierte Wiederherstellungen laden ihren Inhalt noch beim Seitenstart.'
);
$assert(
    str_contains($open_win, 'ensureAjaxReady(timeout = 5000)')
        && str_contains($open_win, 'this.ajaxReadyPromise')
        && str_contains($open_win, 'dbx.add_js("lib", "ajax.js")')
        && str_contains($open_win, 'return this.ensureAjaxReady().then(ready => {'),
    'openWin wartet nicht zentral und dedupliziert auf seine ajax.js-Abhaengigkeit.'
);
$assert(
    str_contains($open_win, 'isCurrentContentRequest(windowData, requestToken)')
        && str_contains($open_win, 'windowData.contentRequestToken = (windowData.contentRequestToken || 0) + 1;'),
    'Veraltete AJAX-Antworten koennen geschlossene oder neu geladene Fenster noch veraendern.'
);
$assert(
    str_contains($open_win, 'removeCacheBust(url)')
        && str_contains($open_win, 'params.delete("_")'),
    'Wiederholtes Neuladen darf Cache-Buster nicht dauerhaft an die Fenster-URL anhaengen.'
);
$assert(
    str_contains($open_win, 'class="dbx-window-loading" role="status" aria-live="polite"')
        && str_contains($open_win, 'class="dbx-window-error" role="alert"')
        && str_contains($open_win, 'this.escapeHtml(message)'),
    'Lade- und Fehlerzustand sind nicht einheitlich, zugaenglich und sicher gerendert.'
);

foreach (array('dbxapp', 'steal') as $design) {
    $css = (string)file_get_contents($root . '/design/' . $design . '/css/c-openWin.css');
    $assert(
        str_contains($css, '.dbx-window-loading-spinner')
            && str_contains($css, '@keyframes dbx-window-loading-spin')
            && str_contains($css, '.dbx-window-error'),
        $design . ': Einheitlicher Lade- oder Fehlerzustand fehlt.'
    );
}
$flowers = (string)file_get_contents($root . '/design/flowers/css/components.css');
$assert(
    str_contains($flowers, '.dbx-window-loading-spinner')
        && str_contains($flowers, '@keyframes dbx-window-loading-spin')
        && str_contains($flowers, '.dbx-window-error'),
    'flowers: Einheitlicher Lade- oder Fehlerzustand fehlt.'
);

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK lazy openWin restore, AJAX readiness, request guards and unified loading UI.\n";
