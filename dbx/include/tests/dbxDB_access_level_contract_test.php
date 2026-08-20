<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$db_source = (string)file_get_contents($root . '/dbx/include/dbxDB.class.php');
$crud_source = (string)file_get_contents($root . '/dbx/include/dbxDBCrud.trait.php');
$combined = $db_source . "\n" . $crud_source;

if (!str_contains($db_source, '$access = dbx()->has_group(access_groups: $groups) ? 1 : 0;')) {
    fwrite(STDERR, "FAIL: Gruppenrechte werden nicht auf den numerischen Vollzugriff 1 normalisiert.\n");
    exit(1);
}

if (preg_match('/\$access\s*==\s*2/', $combined)) {
    fwrite(STDERR, "FAIL: Eine lose Access-Level-Prüfung kann true fälschlich als Owner-Level 2 behandeln.\n");
    exit(2);
}

echo "OK dbxDB numeric access levels\n";
