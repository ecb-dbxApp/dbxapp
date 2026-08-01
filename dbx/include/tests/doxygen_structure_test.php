<?php

$root = dirname(__DIR__, 3);
$doxyfile = $root . '/Doxyfile';
$mainFile = $root . '/docs/doxygen-generated-main.dox';
$referenceFile = $root . '/25_Verbindliches_Modulhandbuch.md';
$referenceModule = $root . '/dbx/modules/myInvoices';
$navigationFile = $root . '/docs/doxygen-navigation.dox';
$updateUserFile = $root . '/docs/dbxapp-system-update-user.dox';
$kiAreasFile = $root . '/28_KI_Bereiche.md';
$tutorialExport = $root . '/dbx/modules/dbxContent/tools/export_doxygen_tutorials_de.php';
$tutorialDir = $root . '/docs/generated/tutorials';
$brandingHeader = $root . '/docs/doxygen-awesome/header.html';
$brandingCss = $root . '/docs/doxygen-awesome/dbxapp-doxygen.css';
$utf8Filter = $root . '/docs/tools/doxygen_php_utf8_filter.php';
$releaseVersion = trim((string)file_get_contents($root . '/VERSION'));

$fail = static function (string $message, int $code): void {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit($code);
};

foreach ([
    $doxyfile,
    $mainFile,
    $referenceFile,
    $navigationFile,
    $updateUserFile,
    $kiAreasFile,
    $tutorialExport,
    $brandingHeader,
    $brandingCss,
    $utf8Filter,
] as $requiredFile) {
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

foreach (array($navigationFile, $updateUserFile) as $doxygenPageFile) {
    $content = (string)file_get_contents($doxygenPageFile);
    if (preg_match_all('/@page\s+(dbxapp_[A-Za-z0-9_]+)/', $content, $matches)) {
        foreach ($matches[1] as $anchor) {
            if (isset($anchors[$anchor])) {
                $duplicateAnchors[$anchor] = array(
                    $anchors[$anchor],
                    basename($doxygenPageFile),
                );
                continue;
            }
            $anchors[$anchor] = basename($doxygenPageFile);
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
foreach (array(
    'docs/doxygen-navigation.dox',
    'docs/dbxapp-system-update-user.dox',
    'docs/generated/tutorials',
    '25_Verbindliches_Modulhandbuch.md',
    'files/doku',
) as $editorialInput) {
    if (strpos($doxyContent, $editorialInput) !== false) {
        $fail('Redaktioneller CMS-Inhalt darf nicht mehr Doxygen-INPUT sein: ' . $editorialInput, 5);
    }
}

$mainContent = (string)file_get_contents($mainFile);
foreach (array(
    '@mainpage dbxapp Quellcode-Referenz',
    '@ref annotated "Klassen"',
    '@ref namespaces "Namespaces"',
    '@ref files "Dateien"',
    '@ref examples "Beispiele"',
    'dbxapp-Dokumentationsportal öffnen',
) as $needle) {
    if (strpos($mainContent, $needle) === false) {
        $fail('Mainpage-Vertrag fehlt: ' . $needle, 7);
    }
}

$navigationContent = (string)file_get_contents($navigationFile);
foreach (array(
    '@page dbxapp_user_docs Anwenderdokumentation',
    '@page dbxapp_user_start Einstieg und Bedienung',
    '@page dbxapp_user_admin Administration und Systemstatus',
    '@subpage dbxapp_user_system_update',
    '@page dbxapp_user_content Inhalte, CMS und Medien',
    '@page dbxapp_user_shop Shop bedienen und administrieren',
    '@page dbxapp_user_workflows Workflows erstellen und benutzen',
    '@page dbxapp_user_design Designs manuell und mit KI erstellen',
    '@page dbxapp_user_ki_content KI-Bereich 1: Content',
    '@page dbxapp_user_ki_design KI-Bereich 2: Design',
    '@page dbxapp_user_ki_modules KI-Bereich 3: Module',
    '@page dbxapp_developer_docs Entwicklerdokumentation',
    '@page dbxapp_dev_orientation Architektur und Laufzeit',
    '@page dbxapp_dev_modules Verbindliche Modulentwicklung',
    '@page dbxapp_dev_pipelines Kernpipelines und Bibliotheken',
    '@page dbxapp_dev_content_design CMS, KI, Design und Fachmodule',
    '@page dbxapp_dev_operations Installation, Updates und Betrieb',
) as $needle) {
    if (strpos($navigationContent, $needle) === false) {
        $fail('Doxygen-Navigation ist unvollständig: ' . $needle, 19);
    }
}

$updateUserContent = (string)file_get_contents($updateUserFile);
foreach (array(
    '@page dbxapp_user_system_update System-Update sicher durchführen',
    'Update automatisch vorbereiten',
    'Update stoppen',
    'Jetzt sicher installieren',
    'Letztes Update zurückrollen',
    'DB3, MySQL und gemischte Installationen',
    'System → System-Selbsttest',
    'dokumentation-selbsttest',
    'files/dbxError.log',
    'dbxapp_install_update_dd_bindings.html',
) as $needle) {
    if (strpos($updateUserContent, $needle) === false) {
        $fail('Anwender-Updateanleitung ist unvollständig: ' . $needle, 32);
    }
}

$kiAreasContent = (string)file_get_contents($kiAreasFile);
foreach (array(
    '<span class="dbx-area-number">1</span>',
    '<h2>Content</h2>',
    '<span class="dbx-area-number">2</span>',
    '<h2>Design</h2>',
    '<span class="dbx-area-number">3</span>',
    '<h2>Module</h2>',
    '@subpage dbxapp_user_ki_content',
    '@subpage dbxapp_user_ki_design',
    '@subpage dbxapp_user_ki_modules',
) as $needle) {
    if (strpos($kiAreasContent, $needle) === false) {
        $fail('Trennung der drei KI-Bereiche fehlt: ' . $needle, 20);
    }
}

$tutorialFiles = glob($tutorialDir . '/*.dox');
if (!is_array($tutorialFiles) || count($tutorialFiles) !== 18) {
    $fail('Der generierte dbxContent-Tutorialbestand muss 18 Seiten enthalten.', 21);
}

$tutorialLabels = array();
foreach ($tutorialFiles as $tutorialFile) {
    $tutorialContent = (string)file_get_contents($tutorialFile);
    if (!preg_match('/^\/\*\*\s+@page\s+(dbxcontent_tutorial_[a-z0-9_]+)\s+/s', $tutorialContent, $match)) {
        $fail('Generierte Tutorialseite besitzt keinen stabilen @page-Anker: '
            . basename($tutorialFile), 22);
    }
    if (strpos($tutorialContent, 'dbxContent #') === false
        || strpos($tutorialContent, '@note Quelle: deutsche dbxContent-Seite') === false) {
        $fail('Quellnachweis der Tutorialseite fehlt: ' . basename($tutorialFile), 23);
    }
    $tutorialLabels[$match[1]] = true;
}
if (count($tutorialLabels) !== 18) {
    $fail('Generierte Tutorial-Anker sind nicht eindeutig.', 24);
}

$tutorialOverview = (string)file_get_contents(
    $tutorialDir . '/0010-tutorials-dbxapp.dox'
);
preg_match_all('/@image html (dbxcontent-media-[^\s"]+)/', $tutorialOverview, $overviewMedia);
if (count($overviewMedia[1] ?? array()) === 0) {
    $fail('Die Tutorialübersicht enthält keine zugeordneten Medien.', 31);
}
foreach (array_unique($overviewMedia[1]) as $asset) {
    if (!is_file($tutorialDir . '/assets/' . $asset)) {
        $fail('Der Tutorialübersicht fehlt die exportierte Mediendatei: ' . $asset, 31);
    }
}

foreach (array_keys($tutorialLabels) as $tutorialLabel) {
    if (strpos($navigationContent, $tutorialLabel) === false) {
        $fail('Tutorial ist keiner Doxygen-Navigation zugeordnet: ' . $tutorialLabel, 25);
    }
}

$tutorialExportContent = (string)file_get_contents($tutorialExport);
foreach (array(
    "get_system_obj('dbxDB')",
    "dbxContentLng::ddContent('de')",
    "'folder = 15 AND activ = 1'",
    "'dbxMediaUsage'",
    "'dbxMedia'",
    "'--write'",
    "'--check'",
) as $needle) {
    if (strpos($tutorialExportContent, $needle) === false) {
        $fail('Tutorial-Exportvertrag ist unvollständig: ' . $needle, 26);
    }
}
foreach (array('/\bPDO\b/', '/\bmysqli?\b/i', '/\bSQLite3\b/') as $forbiddenPattern) {
    if (preg_match($forbiddenPattern, $tutorialExportContent)) {
        $fail('Tutorial-Export umgeht dbxDB: ' . $forbiddenPattern, 27);
    }
}

foreach (array(
    'PROJECT_NAME           = "dbxapp"',
    'PROJECT_NUMBER         = "' . $releaseVersion . '"',
    'PROJECT_LOGO           = dbXapp-Logo.jpeg',
    'FULL_SIDEBAR           = YES',
    'FULL_PATH_NAMES        = NO',
    'PAGE_OUTLINE_PANEL     = NO',
    'STRIP_FROM_PATH        = .',
    'FILTER_PATTERNS        = *dbxApi.php="php -n docs/tools/doxygen_php_utf8_filter.php"',
    '*dbxUpload.class.php="php -n docs/tools/doxygen_php_utf8_filter.php"',
    'FILTER_SOURCE_FILES    = YES',
    'docs/doxygen-generated-main.dox',
    'docs/doxygen-awesome/header.html',
    'docs/doxygen-awesome/dbxapp-doxygen.css',
) as $needle) {
    if (strpos($doxyContent, $needle) === false) {
        $fail('Doxygen-Branding- oder Strukturkonfiguration fehlt: ' . $needle, 28);
    }
}

$headerContent = (string)file_get_contents($brandingHeader);
$cssContent = (string)file_get_contents($brandingCss);
foreach (array(
    'Anwender',
    'Entwickler',
    'KI-Bereiche',
    'data-dbx-section="user"',
    'dbx-doc-nav-icon',
    'dbx-doc-tools',
    'dbx-doc-embedded',
) as $needle) {
    if (strpos($headerContent, $needle) === false) {
        $fail('Zielgruppen-Navigation im dbxapp-Header fehlt: ' . $needle, 29);
    }
}
foreach (array(
    '--dbx-brand-navy',
    '--dbx-brand-red',
    '.dbx-doc-hero',
    '.dbx-audience-grid',
    '#dbx-doc-audience a.is-active',
    '.dbx-doc-nav-icon',
    '.dbx-doc-tools',
    '.dbx-user-nav-grid',
    '.dbx-update-steps',
    '.dbx-update-state-grid',
    'html.dbx-doc-embedded #doc-content',
) as $needle) {
    if (strpos($cssContent, $needle) === false) {
        $fail('dbxapp-Branding-CSS ist unvollständig: ' . $needle, 30);
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
