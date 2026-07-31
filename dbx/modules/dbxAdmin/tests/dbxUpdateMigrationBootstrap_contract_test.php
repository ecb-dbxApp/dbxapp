<?php

declare(strict_types=1);

$root = dirname(__DIR__, 4);
$errors = array();
$checked = 0;

foreach (glob($root . '/dbx/modules/*/migrations/*.migration.php') ?: array() as $migrationFile) {
    $migration = include $migrationFile;
    if (!is_array($migration)) {
        $errors[] = 'Ungültige Migration: ' . $migrationFile;
        continue;
    }
    foreach ((array)($migration['affected_dd'] ?? array()) as $ddRef) {
        $parts = explode('|', (string)$ddRef, 2);
        if (count($parts) !== 2) {
            $errors[] = 'Ungültige DD-Referenz: ' . (string)$ddRef;
            continue;
        }
        [$module, $dd] = $parts;
        $ddFile = $root . '/dbx/modules/' . $module . '/dd/' . $dd . '.dd.php';
        if (!is_file($ddFile)) {
            continue;
        }
        $table = (static function (string $file): array {
            $table = array();
            $fields = array();
            $indexes = array();
            include $file;
            return is_array($table) ? $table : array();
        })($ddFile);
        $server = trim((string)($table['server'] ?? ''));
        if (preg_match('/\.(?:db3|sqlite|sqlite3)$/i', $server) !== 1) {
            continue;
        }
        $checked++;
        if (!str_starts_with($server, $module . '|')) {
            $errors[] = $ddRef . ' verwendet keinen releasefest qualifizierten SQLite-Server: ' . $server;
        }
    }
}

if ($checked === 0) {
    $errors[] = 'Kein migrationsbetroffener SQLite-DD-Server geprüft.';
}
if ($errors !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo 'OK migration bootstrap uses release-stable SQLite server references (' . $checked . ")\n";
