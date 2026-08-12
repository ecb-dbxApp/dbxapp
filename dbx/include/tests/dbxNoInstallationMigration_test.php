<?php

declare(strict_types=1);

/**
 * Architekturvertrag fuer dbxApp 4.2 ohne Altinstallations-Migration.
 *
 * Konfigurationen gelten exakt so, wie sie in config.php und
 * config.local.php stehen. Action-Tokens verwenden nur noch den aktuellen
 * HMAC-Mechanismus; alte Session-Token-Tabellen werden nicht interpretiert.
 */

$sourceFile = dirname(__DIR__) . '/dbxApi.php';
$source = (string)file_get_contents($sourceFile);
$root = dirname(__DIR__, 3);

$forbidden = array(
    'normalize_legacy_install_config' => 'Die alte Installationsstatus-Migration ist wieder aktiv.',
    "get_session_var('action_tokens'" => 'Die alte Action-Token-Tabelle wird wieder gelesen.',
    "delete_session_var('action_tokens'" => 'Die alte Action-Token-Tabelle ist wieder Teil des Laufzeitmodells.',
);

foreach ($forbidden as $needle => $message) {
    if (str_contains($source, $needle)) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}

$version = trim((string)file_get_contents($root . '/VERSION'));
$baseline = trim((string)file_get_contents($root . '/UPDATE_BASELINE'));
if ($version !== '4.2.0' || $baseline !== '4.2.0') {
    fwrite(STDERR, "FAIL: VERSION und UPDATE_BASELINE muessen mit 4.2.0 beginnen.\n");
    exit(1);
}

$updater = (string)file_get_contents(
    $root . '/dbx/modules/dbxAdmin/include/dbxUpdateService.class.php'
);
foreach (array(
    'private const RELEASE_SCHEMA = 2;',
    "'dbxapp' => \$dbxRequirement",
    "version_compare(\$stable, \$this->updateBaseline(), '<')",
) as $contract) {
    if (!str_contains($updater, $contract)) {
        fwrite(STDERR, "FAIL: 4.2-Updatevertrag fehlt: $contract\n");
        exit(1);
    }
}

foreach (array(
    'dbx/modules/dbxContent/tools/migrate_flat_permalinks.php',
    'dbx/modules/dbxContent_admin/tools/update_homepage_20260728.php',
    'dbx/modules/dbxDocs/tools/update_docs_portal_media_20260728.php',
) as $migrationTool) {
    if (is_file($root . '/' . $migrationTool)) {
        fwrite(STDERR, "FAIL: Einmaliges Altversionswerkzeug ist noch vorhanden: $migrationTool\n");
        exit(1);
    }
}

echo "OK dbxApp 4.2 baseline without pre-version migration paths\n";
