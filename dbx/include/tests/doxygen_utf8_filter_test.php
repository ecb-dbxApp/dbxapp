<?php

$root = dirname(__DIR__, 3);
$filter = $root . '/docs/tools/doxygen_php_utf8_filter.php';
$sourceFile = $root . '/dbx/include/dbxApi.php';

$fail = static function (string $message, int $code): void {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit($code);
};

if (!is_file($filter) || !is_file($sourceFile)) {
    $fail('Doxygen-UTF-8-Filter oder Testquelle fehlt.', 1);
}

$beforeHash = hash_file('sha256', $sourceFile);
$argv = array($filter, $sourceFile);
ob_start();
require $filter;
$filtered = (string)ob_get_clean();
$afterHash = hash_file('sha256', $sourceFile);

if ($beforeHash !== $afterHash) {
    $fail('Der Doxygen-Filter hat die PHP-Quelldatei verändert.', 2);
}
if (strpos($filtered, 'Geschützte ModulVariablen') === false
    || strpos($filtered, 'Rückgabe des validierten Werts') === false
    || strpos($filtered, 'zusätzlich in der System-Session') === false) {
    $fail('Doppelt codierte Umlaute in Docblocks wurden nicht korrigiert.', 3);
}
if (strpos($filtered, 'GeschÃ¼tzte ModulVariablen') !== false
    || strpos($filtered, 'RÃ¼ckgabe des validierten Werts') !== false) {
    $fail('Fehlerhafte UTF-8-Darstellung ist im gefilterten Docblock verblieben.', 4);
}
if (strpos($filtered, "'Ã¤' => chr(228)") === false) {
    $fail('Absichtliche PHP-Konvertierungstabelle wurde verändert.', 5);
}

try {
    token_get_all($filtered, TOKEN_PARSE);
} catch (ParseError $error) {
    $fail('Gefilterte PHP-Ausgabe ist syntaktisch ungültig: ' . $error->getMessage(), 6);
}

echo "OK Doxygen UTF-8 filter: comments repaired, PHP code preserved\n";
