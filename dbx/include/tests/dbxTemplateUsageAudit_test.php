<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$audit = $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'audit-templates.php';
$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($audit) . ' --json';
$output = array();
$exit_code = 0;
exec($command, $output, $exit_code);

if ($exit_code !== 0) {
    fwrite(STDERR, "FAIL: Template-Audit konnte nicht ausgefuehrt werden.\n");
    exit(1);
}

$result = json_decode(implode("\n", $output), true);
if (!is_array($result)) {
    fwrite(STDERR, "FAIL: Template-Audit lieferte kein gueltiges JSON.\n");
    exit(1);
}

$unused_families = (int)($result['families_unused_candidates'] ?? -1);
$unused_files = (int)($result['files_unused_candidates'] ?? -1);
if ($unused_families !== 0 || $unused_files !== 0) {
    $names = array_map(
        static fn(array $family): string => (string)($family['module'] ?? '')
            . '|' . (string)($family['template'] ?? ''),
        is_array($result['unused_candidates'] ?? null)
            ? $result['unused_candidates']
            : array()
    );
    fwrite(
        STDERR,
        'FAIL: Ungenutzte Templates gefunden: ' . implode(', ', $names) . "\n"
    );
    exit(1);
}

echo 'OK: Keine statisch ungenutzten Template-Familien; dynamische Kataloge sind explizit geschuetzt.' . PHP_EOL;

