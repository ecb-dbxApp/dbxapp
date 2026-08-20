<?php

declare(strict_types=1);

$filter = dirname(__DIR__, 2) . '/out_filter.php';
$source = (string)file_get_contents($filter);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$assert(str_contains($source, "preg_replace('/\\bdbx\\s*app\\b/iu', 'dbxapp'"), 'Die zentrale dbxapp-Schreibweise fehlt im Ausgabefilter.');
$assert(str_contains($source, 'script|style|code|pre'), 'Geschützte Code-, Script- und Style-Bereiche fehlen im Ausgabefilter.');

echo "dbxOutputFilterBrand_contract_test: OK\n";
