<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$failures = array();

$contracts = array(
    'AGENTS.md' => array(
        'Menüinhalte und installationsbezogene Menü-Templates sind Kundendateien',
        'Modulimplementierungen und ihre System-Sourcen sind Produktdateien',
    ),
    '12_KI_Verbindliche_Regeln.md' => array(
        'Kundeninhalt und Systemquelle unterscheiden',
        'Eine Kundenänderung darf nicht durch einen nachfolgenden Produktabgleich oder',
    ),
    '27_Installation_Updates_DD_Serverbindungen.md' => array(
        'Menü-Templates sind Kundendateien',
        'PHP, DD, FD, JavaScript oder CSS',
    ),
    'dbx/modules/dbxKi/KI-INSTRUCTIONS.md' => array(
        'Vor jeder Änderung die Zieldatei klassifizieren',
        'nicht durch einen Update-Abgleich überschreiben',
    ),
    'dbx/modules/dbxKi/include/dbxKiModuleBriefingService.class.php' => array(
        'Kundendatei und Systemquelle unterscheiden',
        'nicht durch Updates ueberschreiben',
    ),
);

foreach ($contracts as $relative => $needles) {
    $file = $root . '/' . $relative;
    $content = is_file($file) ? file_get_contents($file) : false;
    if (!is_string($content)) {
        $failures[] = 'Vertragsdatei fehlt: ' . $relative;
        continue;
    }
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            $failures[] = $relative . ' enthält die verbindliche Grenze nicht: ' . $needle;
        }
    }
}

if ($failures !== array()) {
    fwrite(STDERR, "FAIL customer/system file boundary\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK customer menu content and system source boundary is documented and enforced.\n";
