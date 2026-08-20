<?php

declare(strict_types=1);

/** Stellt sicher, dass die reale UI-Matrix und ihr Layout-faehiger Browser-Runner nicht unbemerkt reduziert werden. */
$module = dirname(__DIR__);
$matrix = (string)file_get_contents($module . '/tests/dbxUiRegressionMatrix_browser_test.js');
$runner = (string)file_get_contents($module . '/js/selftest.js');
$metadata = (string)file_get_contents($module . '/cfg/test-metadata.php');
$confirm = (string)file_get_contents(dirname($module, 2) . '/js/lib/confirm.js');
$failures = array();
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

foreach (array('desktop', 'tablet', 'mobile', '/home', 'cid=1', 'dbx_run1=flat') as $token) {
    $check(str_contains($matrix, $token), 'Matrixziel fehlt: ' . $token);
}
foreach (array('checkCmsEditor', 'checkMediaBrowser', 'checkOpenWinAjax', 'checkTooltip',
    'data-cms-browser-upload', 'data-cms-media-maintenance', 'maxLoadMs', 'maxResources', 'maxNodes') as $token) {
    $check(str_contains($matrix, $token), 'UI-Vertrag fehlt: ' . $token);
}
$check(str_contains($runner, 'left: "-20000px"') && !str_contains($runner, 'frame.hidden = true'),
    'Browser-Tests besitzen keinen gerenderten Layout-Viewport.');
$check(str_contains($runner, 'Math.min(240000'), 'Der Matrixlauf wird vor seinem deklarierten Zeitlimit abgebrochen.');
$check(str_contains($metadata, 'dbxUiRegressionMatrix_browser_test')
    && str_contains($metadata, "array('browser', 'layout')"), 'Explizite Browser-/Layout-Metadaten fehlen.');
$check(
    str_contains($confirm, 'source: rawOptions.source || null')
        && str_contains($confirm, 'callerEl: rawOptions.callerEl || rawOptions.caller || rawOptions.source || null'),
    'Programmatische Confirm-Aufrufer sind nicht von deklarativen Originalaktionen getrennt.'
);

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK reale UI-Matrix fuer Home, CMS, Report, openWin, Tooltips und Medien.\n";
