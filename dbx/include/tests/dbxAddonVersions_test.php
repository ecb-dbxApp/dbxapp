<?php
declare(strict_types=1);

$dbxDir = dirname(__DIR__, 2);
$addonDir = $dbxDir . '/add_ons';
$manifestFile = $addonDir . '/versions.json';
$manifest = json_decode((string) file_get_contents($manifestFile), true);
$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$assets = array(
    'ace-builds' => array('ace/ace.js', '1.44.0'),
    'glightbox' => array('glightbox/js/glightbox.min.js', '3.3.1'),
    'purecounterjs' => array('purecounter/js/purecounter.js', '1.5.0'),
    'remixicon' => array('remixicon/remixicon.css', '4.9.1'),
    'tabulator-tables' => array('tabulator/dist/js/tabulator.min.js', '6.5.2'),
    'jspdf' => array('tabulator-deps/jspdf.umd.min.js', '4.2.1'),
    'jspdf-autotable' => array('tabulator-deps/jspdf.plugin.autotable.min.js', '5.0.8'),
    'sheetjs' => array('tabulator-deps/xlsx.full.min.js', '0.20.3'),
);

$assert(is_array($manifest), 'add_ons/versions.json ist kein gueltiges JSON.');
foreach ($assets as $package => [$relativeFile, $expectedVersion]) {
    $file = $addonDir . '/' . $relativeFile;
    $configured = is_array($manifest) ? (string)($manifest[$package]['version'] ?? '') : '';
    $assert($configured === $expectedVersion, $package . ': Manifest-Version ist falsch.');
    $assert(is_file($file) && filesize($file) > 0, $package . ': Distributionsdatei fehlt oder ist leer.');
    $content = is_file($file) ? (string) file_get_contents($file) : '';
    $assert(str_contains($content, $expectedVersion), $package . ': Versionsmarker fehlt in der Distributionsdatei.');
}

$assert(
    !is_file($addonDir . '/ace/theme-kr.js'),
    'Das in aktuellen Ace-Builds entfernte Theme theme-kr.js ist noch vorhanden.'
);
$assert(
    str_contains((string) file_get_contents($addonDir . '/purecounter/js/purecounter.js'), '__dbxPureCounter'),
    'PureCounter 1.5 wird nicht kompatibel zum bisherigen Auto-Start initialisiert.'
);

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK add_on assets match their recorded current versions.\n";
