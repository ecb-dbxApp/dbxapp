<?php

declare(strict_types=1);

/**
 * Zentrale Ausführungsregeln für automatisch entdeckte Tests.
 * Die Einstufung hängt damit nicht mehr von versteckter Runner-Heuristik ab.
 * Die erste passende Regel gewinnt; alle übrigen Tests sind schnelle,
 * prozessisolierte Verträge.
 */
return array(
    'defaults' => array(
        'tier' => 'quick', 'timeout' => 90, 'isolation' => 'process',
        'resources' => array(),
    ),
    'rules' => array(
        array(
            'pattern' => '~dbxDocumentationQuality_test\.php$~',
            'tier' => 'full', 'timeout' => 300, 'isolation' => 'process',
            'resources' => array('filesystem', 'database', 'network'),
            'description' => 'Prüft die externe Dokumentation vollständig auf Links, Metadaten, H1, Version, Sprach-URLs, Legacy-Regeln und Doxygen-Konsistenz.',
        ),
        array(
            'pattern' => '~dbxOpenWinLazyRestorePerformance_contract_test\.php$~',
            'tier' => 'quick', 'timeout' => 30, 'isolation' => 'process',
            'resources' => array('filesystem'),
            'description' => 'Prüft, dass minimierte Fenster ohne Hintergrund-AJAX wiederhergestellt, erst beim Öffnen geladen und gegen Start-Rennen sowie veraltete Antworten geschützt werden.',
        ),
        array(
            'pattern' => '~dbxReportSelectionApi_test\.php$~',
            'tier' => 'quick', 'timeout' => 30, 'isolation' => 'process',
            'resources' => array('filesystem'),
            'description' => 'Prüft, dass Reportfilter, Sortierung und Pagination nur über die vorhandene validierende get_fld_val()-Schnittstelle gelesen werden.',
        ),
        array(
            'pattern' => '~dbxContentReadOnlyUid_test\.php$~',
            'tier' => 'quick', 'timeout' => 30, 'isolation' => 'process',
            'resources' => array('filesystem'),
            'description' => 'Prüft, dass das Anzeigen des Content-Trees, die Sprachabdeckung und die Startseitenauflösung keine Datensätze verändern.',
        ),
        array(
            'pattern' => '~dbxPackage(?:Contract|Manager|Architecture)_.*test\.php$~',
            'tier' => 'full', 'timeout' => 180, 'isolation' => 'process',
            'resources' => array('filesystem'),
            'description' => 'Prüft Paketgrenzen, Marktplatzvertrag, transaktionale Installation, Rollback und die bewusst legacyfreie 4.3-Architektur.',
        ),
        array(
            'pattern' => '~dbxUiRegressionMatrix_browser_test\.js$~',
            'tier' => 'full', 'timeout' => 180, 'isolation' => 'browser',
            'resources' => array('browser', 'layout'),
            'description' => 'Prüft /home, Content 1 und dbxReport auf Desktop, Tablet und Mobil sowie Editor-Caret, Fokus, HTML-Tooltips, openWin/AJAX, Medienbrowser, Uploadformular, Wartungsdialog, Layer und Laufzeitbudgets.',
        ),
        array(
            'pattern' => '~cms_shop_gallery_test\.js$~',
            'tier' => 'full', 'timeout' => 120, 'isolation' => 'browser',
            'resources' => array('browser'),
            'description' => 'Prüft im CMS-Editor das Einfügen, Auswählen und Bearbeiten einer Shop-Galerie mit realen DOM-Ereignissen.',
        ),
        array(
            'pattern' => '~cms_zero_field_value_test\.js$~',
            'tier' => 'full', 'timeout' => 120, 'isolation' => 'browser',
            'resources' => array('browser'),
            'description' => 'Prüft, dass der CMS-Editor den gültigen Feldwert 0 beim Laden, Bearbeiten und Speichern nicht als leer verwirft.',
        ),
        array(
            'pattern' => '~(?:integration|roundtrip|performance|install|update|doxygen|maintenance)_test\.(?:php|js)$~i',
            'tier' => 'full', 'timeout' => 180, 'isolation' => 'process',
            'resources' => array('filesystem', 'database'),
        ),
        array(
            'pattern' => '~(?:browser|viewport|ui)_test\.js$~i',
            'tier' => 'full', 'timeout' => 120, 'isolation' => 'browser',
            'resources' => array('browser'),
        ),
        array(
            'pattern' => '~dbxCoreFunctional_test\.php$~',
            'tier' => 'quick', 'timeout' => 90, 'isolation' => 'process',
            'resources' => array('filesystem', 'database'),
        ),
    ),
);
