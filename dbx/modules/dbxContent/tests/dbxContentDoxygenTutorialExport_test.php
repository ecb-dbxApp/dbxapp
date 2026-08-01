<?php

$appRoot = dirname(__DIR__, 4);
$exportFile = $appRoot
    . '/dbx/modules/dbxContent/tools/export_doxygen_tutorials_de.php';

if (!is_file($exportFile)) {
    fwrite(STDERR, "FAIL: Doxygen-Tutorial-Export fehlt.\n");
    exit(1);
}

$contentDatabase = $appRoot . '/dbx/modules/dbx/db/dbxContent.db3';
if (!is_file($contentDatabase) || filesize($contentDatabase) === 0) {
    $source = (string)file_get_contents($exportFile);
    foreach (array("'--write'", "'--check'", "get_system_obj('dbxDB')") as $required) {
        if (!str_contains($source, $required)) {
            fwrite(STDERR, "FAIL: Portabler Tutorial-Exportvertrag ist unvollständig.\n");
            exit(2);
        }
    }
    echo "OK portable Doxygen tutorial export contract; local content fixtures are not part of the public checkout.\n";
    exit(0);
}

$originalArgv = $argv ?? array();
$argv = array($exportFile, '--check');

ob_start();
require $exportFile;
$json = (string)ob_get_clean();
$argv = $originalArgv;

$result = json_decode($json, true);
if (!is_array($result)) {
    fwrite(STDERR, "FAIL: Export liefert kein gültiges JSON.\n");
    exit(2);
}

$expected = array(
    'mode' => 'check',
    'language' => 'de',
    'source_dd' => 'content_de',
    'tutorial_pages' => 19,
);
foreach ($expected as $key => $value) {
    if (($result[$key] ?? null) !== $value) {
        fwrite(
            STDERR,
            'FAIL: Unerwarteter Exportwert für ' . $key . ': '
            . json_encode($result[$key] ?? null, JSON_UNESCAPED_UNICODE)
            . "\n"
        );
        exit(3);
    }
}

$mediaLinks = (int)($result['media_usage_links'] ?? 0);
if ($mediaLinks <= 0
    || (int)($result['unique_page_media_links'] ?? -1) !== $mediaLinks
    || (int)($result['covered_page_media_links'] ?? -1) !== $mediaLinks
    || (int)($result['unique_media_assets'] ?? 0) <= 0
    || (int)($result['unique_media_assets'] ?? 0) > $mediaLinks) {
    fwrite(STDERR, "FAIL: Medienabdeckung des Tutorial-Exports ist inkonsistent.\n");
    exit(3);
}

if (($result['missing_media'] ?? null) !== array()) {
    fwrite(STDERR, "FAIL: Dem Tutorial-Export fehlen Medien.\n");
    exit(4);
}
if (($result['stale_files'] ?? null) !== array()) {
    fwrite(STDERR, "FAIL: Der Doxygen-Tutorialbestand ist nicht aktuell.\n");
    exit(5);
}
if ((int)($result['written_pages'] ?? -1) !== 0
    || (int)($result['copied_assets'] ?? -1) !== 0) {
    fwrite(STDERR, "FAIL: --check hat Dateien geschrieben.\n");
    exit(6);
}

echo "OK dbxContent Doxygen tutorial export: "
    . $result['tutorial_pages'] . ' pages, '
    . $result['unique_media_assets'] . " media assets\n";
