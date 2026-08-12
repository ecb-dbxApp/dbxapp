<?php

$root = dirname(__DIR__, 3);
$filter = $root . '/docs/tools/doxygen_php_utf8_filter.php';

$fail = static function (string $message, int $code): void {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit($code);
};

if (!is_file($filter)) {
    $fail('Doxygen-UTF-8-Filter fehlt.', 1);
}

/*
 * Eigenstaendiges Fixture statt einer Projektdatei: der Filter arbeitet
 * dateiinhaltsunabhaengig, daher reicht ein kleines Beispiel mit genau den
 * zwei Faellen, die abgesichert werden muessen - doppelt codierte Umlaute
 * in einem Docblock (muessen repariert werden) und dieselbe Zeichenfolge
 * als String-Literal in echtem Code (darf nicht angefasst werden).
 */
$fixtureSource = <<<'PHP'
<?php
/**
 * GeschÃ¼tzte ModulVariablen werden nicht Ã¼berschrieben.
 * RÃ¼ckgabe des validierten Werts, zusÃ¤tzlich in der System-Session.
 */
function example(): array {
    $umlaute = array('Ã¤' => chr(228), 'Ã¶' => chr(246));
    return $umlaute;
}
PHP;

$sourceFile = tempnam(sys_get_temp_dir(), 'dbx_doxygen_utf8_fixture_');
if ($sourceFile === false || file_put_contents($sourceFile, $fixtureSource) === false) {
    $fail('Fixture-Datei konnte nicht angelegt werden.', 7);
}

$beforeHash = hash_file('sha256', $sourceFile);
$argv = array($filter, $sourceFile);
ob_start();
require $filter;
$filtered = (string)ob_get_clean();
$afterHash = hash_file('sha256', $sourceFile);
unlink($sourceFile);

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
