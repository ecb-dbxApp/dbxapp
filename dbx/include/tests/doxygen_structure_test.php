<?php

$root = dirname(__DIR__, 3);
$doxyfile = $root . '/Doxyfile';
$mainFile = $root . '/00_Doxygen_Mainpage.md';
$referenceFile = $root . '/25_Verbindliches_Modulhandbuch.md';
$referenceModule = $root . '/dbx/modules/myInvoices';

$fail = static function (string $message, int $code): void {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit($code);
};

foreach ([$doxyfile, $mainFile, $referenceFile] as $requiredFile) {
    if (!is_file($requiredFile)) {
        $fail('Erforderliche Doxygen-Datei fehlt: ' . $requiredFile, 1);
    }
}

foreach (array(
    'myInvoices.class.php',
    'cfg/config.php',
    'dd/invoice.dd.php',
    'dd/invoiceItem.dd.php',
    'fd/invoice-form.fd.php',
    'fd/rpt-invoice-selection.fd.php',
    'include/myInvoicesFixtures.class.php',
    'include/myInvoicesService.class.php',
    'tpl/htm/invoice-form.htm',
    'tpl/htm/invoice-report.htm',
    'tpl/htm/invoice-items-report.htm',
    'tpl/htm/invoice-row-action.htm',
    'tpl/htm/invoice-install.htm',
    'tools/install_demo.php',
    'tests/myInvoices_contract_test.php',
    'tests/myInvoices_integration_test.php',
    'README.md',
) as $relative) {
    if (!is_file($referenceModule . '/' . $relative)) {
        $fail('Ausführbares Referenzmodul ist unvollständig: ' . $relative, 16);
    }
}

$docs = glob($root . '/[0-9][0-9]_*.md');
if (!is_array($docs) || count($docs) < 25) {
    $fail('Die nummerierte Doxygen-Dokumentation ist unvollstaendig.', 2);
}
sort($docs);

$anchors = array();
$references = array();
$duplicateAnchors = array();

foreach ($docs as $doc) {
    $content = (string)file_get_contents($doc);

    if (preg_match_all('/\{#(dbxapp_[A-Za-z0-9_]+)\}/', $content, $matches)) {
        foreach ($matches[1] as $anchor) {
            if (isset($anchors[$anchor])) {
                $duplicateAnchors[$anchor] = array($anchors[$anchor], basename($doc));
                continue;
            }
            $anchors[$anchor] = basename($doc);
        }
    }

    if (preg_match_all('/@ref\s+(dbxapp_[A-Za-z0-9_]+)/', $content, $matches)) {
        foreach ($matches[1] as $reference) {
            $references[$reference][] = basename($doc);
        }
    }
}

if ($duplicateAnchors) {
    $fail(
        'Doppelte Doxygen-Anker: ' . implode(', ', array_keys($duplicateAnchors)),
        3
    );
}

$missingReferences = array_diff(array_keys($references), array_keys($anchors));
if ($missingReferences) {
    $fail(
        'Unbekannte Doxygen-Referenzen: ' . implode(', ', $missingReferences),
        4
    );
}

$doxyContent = (string)file_get_contents($doxyfile);
foreach ($docs as $doc) {
    $name = basename($doc);
    if (strpos($doxyContent, $name) === false) {
        $fail('Doxygen INPUT enthaelt die Seite nicht: ' . $name, 5);
    }
}

$expectedOrder = array(
    '00_Doxygen_Mainpage.md',
    '25_Verbindliches_Modulhandbuch.md',
    '08_Modulaufbau_Patterns.md',
    '04_dbxTPL_Leitfaden.md',
    '05_dbxDB_DD_FD_Leitfaden.md',
    '06_dbxForm_Leitfaden.md',
    '07_dbxReport_Leitfaden.md',
    '09_JavaScript_Libs.md',
    '23_Sicherheit_Integritaet_Performance.md',
    '22_Aktueller_Stand_Betrieb.md',
    '24_DB3_MySQL_Roundtrip.md',
);
$lastPosition = -1;
foreach ($expectedOrder as $name) {
    $position = strpos($doxyContent, $name);
    if ($position === false || $position <= $lastPosition) {
        $fail('Doxygen INPUT-Reihenfolge ist inkonsistent bei: ' . $name, 6);
    }
    $lastPosition = $position;
}

$mainContent = (string)file_get_contents($mainFile);
foreach (array(
    '@ref dbxapp_module_reference',
    '## Lesepfade',
    '## Dokumentationslandkarte',
    '## Normative Reihenfolge',
    '## Systemweiter Entwicklungsablauf',
) as $needle) {
    if (strpos($mainContent, $needle) === false) {
        $fail('Mainpage-Vertrag fehlt: ' . $needle, 7);
    }
}

$referenceContent = (string)file_get_contents($referenceFile);
foreach (array(
    'dbxTPL',
    'dbxDB',
    'dbxDD',
    'dbxForm',
    'dbxReport',
    'DD',
    'FD',
    'dbxAjax',
    'dbxConfirm',
    'Kombination aus `delete` und `rid` automatisch',
    'action_url',
    'invoice_report_next_record',
    'invoice_items_report_next_record',
    '{rpt:colspan}',
    'add_rep()',
    "\$report->_mode = 'table'",
    "'sum' => 'Summe'",
    '{report_total}',
    '[modul=myInvoices]dbx_run1=positions&invoice_id=',
    '{rpt:col_count}',
    '<tfoot>',
    '[dbx:form]',
    'dbx_split',
    'Verbindliche Arbeitsanweisung',
    'Mindesttestmatrix',
    'myInvoices_contract_test.php',
    'myInvoices_integration_test.php',
    'include/myInvoicesFixtures.class.php',
    'Browserkonsole',
    '@include dbx/modules/myInvoices/dd/invoice.dd.php',
    '@include dbx/modules/myInvoices/dd/invoiceItem.dd.php',
    'dbxapp-Exportformat',
) as $needle) {
    if (strpos($referenceContent, $needle) === false) {
        $fail('Referenzmodul ist unvollstaendig: ' . $needle, 8);
    }
}

$actualService = (string)file_get_contents(
    $referenceModule . '/include/myInvoicesService.class.php'
);
$actualFixtures = (string)file_get_contents(
    $referenceModule . '/include/myInvoicesFixtures.class.php'
);
foreach (array(
    '/\bPDO\b/',
    '/\bmysqli?_/',
    '/->(?:query|exec|prepare)\s*\(/',
    '/private function (?:db|tpl|h)\s*\(/',
) as $forbiddenPattern) {
    if (preg_match($forbiddenPattern, $actualService . "\n" . $actualFixtures)) {
        $fail(
            'Ausführbares Referenzmodul verletzt den Daten-/Wrappervertrag: '
            . $forbiddenPattern,
            17
        );
    }
}
foreach (array(
    'create_date',
    'create_uid',
    'update_date',
    'update_uid',
    'owner',
) as $systemField) {
    if (preg_match(
        '/[\'"]' . preg_quote($systemField, '/') . '[\'"]\s*=>/',
        $actualFixtures
    )) {
        $fail(
            'Fixture setzt automatisches dbxDB-Systemfeld: ' . $systemField,
            18
        );
    }
}

if (!preg_match_all('/```php\s*(<\?php.*?)(?=```)/s', $referenceContent, $phpBlocks)
    || count($phpBlocks[1]) < 5
) {
    $fail('Zu wenige vollstaendige PHP-Dateibeispiele im Referenzmodul.', 9);
}

foreach ($phpBlocks[1] as $index => $phpBlock) {
    try {
        token_get_all($phpBlock, TOKEN_PARSE);
    } catch (ParseError $e) {
        $fail(
            'PHP-Beispiel ' . ($index + 1) . ' ist syntaktisch ungueltig: '
            . $e->getMessage(),
            10
        );
    }
}

if (!preg_match('/class myInvoicesService.*?\?>/s', $referenceContent, $serviceMatch)) {
    $fail('Servicebeispiel im Referenzmodul nicht gefunden.', 11);
}

$serviceExample = $serviceMatch[0];
foreach (array(
    '/private function (?:db|tpl|h)\s*\(/',
    '/(?:private|protected|public) function \w+\s*\([^)]*\)[^{]*\{\s*return\s+dbx\(\)->get_system_obj\s*\(/s',
    '/dbx\(\)->esc\s*\(/',
    '/htmlspecialchars\s*\(/',
    '/[\'"](?:create_date|create_uid|update_date|update_uid|owner)[\'"]\s*=>\s*(?:date|dbx\(\)->user)/',
) as $forbiddenPattern) {
    if (preg_match($forbiddenPattern, $serviceExample)) {
        $fail(
            'Servicebeispiel enthaelt ein unzulaessiges Wrapper-, Escape- '
            . 'oder Systemfeldmuster: ' . $forbiddenPattern,
            12
        );
    }
}

foreach (array(
    'automatisch von `dbxDB` gesetzt',
    'Kernel-Funktionen nicht durch eigene Methoden nachbauen',
    'Werte nicht pauschal escapen',
    'Endsumme der Positionen',
    'geschützte Modulvariablen',
) as $requiredContract) {
    if (strpos($referenceContent, $requiredContract) === false) {
        $fail('Vereinfachungsvertrag fehlt: ' . $requiredContract, 13);
    }
}

$modulePatternContent = (string)file_get_contents(
    $root . '/08_Modulaufbau_Patterns.md'
);
$formGuideContent = (string)file_get_contents(
    $root . '/06_dbxForm_Leitfaden.md'
);

foreach (array(
    '/private function (?:db|tpl|h)\s*\(/',
    '/dbx\(\)->esc\s*\(/',
    '/htmlspecialchars\s*\(/',
) as $forbiddenPattern) {
    if (preg_match($forbiddenPattern, $modulePatternContent)) {
        $fail(
            'Modulpattern enthaelt einen unzulaessigen Alias oder '
            . 'pauschales Escaping: ' . $forbiddenPattern,
            14
        );
    }
}

if (preg_match(
    '/set_post\s*\(\s*[\'"](?:create_date|create_uid|update_date|update_uid|owner)[\'"]/',
    $formGuideContent
)) {
    $fail('Formleitfaden setzt automatische dbxDB-Systemfelder manuell.', 15);
}

echo 'OK Doxygen structure: '
    . count($docs) . ' pages, '
    . count($anchors) . ' anchors, '
    . count($references) . ' references, '
    . count($phpBlocks[1]) . " PHP examples\n";
