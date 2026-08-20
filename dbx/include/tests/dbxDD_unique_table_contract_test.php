<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$files = glob($root . '/dbx/modules/*/dd/*.dd.php') ?: array();
$tables = array();
$missing = array();

foreach ($files as $file) {
    $source = (string)file_get_contents($file);
    $server_match = array();
    $table_match = array();
    $has_server = preg_match('/\$table\[[\'\"]server[\'\"]\]\s*=\s*([\'\"])(.*?)\1\s*;/', $source, $server_match) === 1;
    $has_table = preg_match('/\$table\[[\'\"]table[\'\"]\]\s*=\s*([\'\"])(.*?)\1\s*;/', $source, $table_match) === 1;

    if ((!$has_server || !$has_table)
        && preg_match('/\$__dbx_lng_dd\s*=\s*([\'\"])([a-z]{2})\1\s*;/', $source, $language) === 1
    ) {
        $server_match[2] = 'dbxContent.db3';
        if (str_contains($source, 'dbxContentFolder.dd.inc.php')) {
            $table_match[2] = 'content_folder_' . $language[2];
            $has_server = $has_table = true;
        } elseif (str_contains($source, 'dbxContent.dd.inc.php')) {
            $table_match[2] = 'content_' . $language[2];
            $has_server = $has_table = true;
        }
    }

    if (!$has_server || !$has_table) {
        $missing[] = str_replace('\\', '/', substr($file, strlen($root) + 1));
        continue;
    }

    $key = strtolower(trim((string)$server_match[2]) . '|' . trim((string)$table_match[2]));
    $tables[$key][] = str_replace('\\', '/', substr($file, strlen($root) + 1));
}

$duplicates = array_filter($tables, static fn(array $definitions): bool => count($definitions) > 1);
if ($missing !== array()) {
    fwrite(STDERR, "FAIL: DD ohne statische Server-/Tabellenzuordnung:\n- " . implode("\n- ", $missing) . "\n");
    exit(1);
}
if ($duplicates !== array()) {
    $lines = array();
    foreach ($duplicates as $table => $definitions) {
        $lines[] = $table . ': ' . implode(', ', $definitions);
    }
    fwrite(STDERR, "FAIL: Eine physische Tabelle besitzt mehrere DDs:\n- " . implode("\n- ", $lines) . "\n");
    exit(1);
}

echo 'OK jede physische Tabelle besitzt genau eine eigenstaendige DD; Ansichten bleiben FD.' . PHP_EOL;
