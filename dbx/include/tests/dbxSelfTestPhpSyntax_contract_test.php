<?php
declare(strict_types=1);

$runnerFile = dirname(__DIR__, 2) . '/modules/dbxSelfTest/include/dbxSelfTestRunner.class.php';
$source = (string) file_get_contents($runnerFile);
$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$assert(
    str_contains($source, 'token_get_all($source, TOKEN_PARSE)'),
    'Der Gesamtsyntaxtest verwendet nicht den eingebauten PHP-Parser.'
);
$assert(
    !str_contains($source, "runProcess(array($php, '-n', '-l', $file)"),
    'Der Gesamtsyntaxtest startet weiterhin fuer jede Datei einen PHP-Prozess.'
);
$assert(
    str_contains($source, 'catch (\\ParseError $exception)'),
    'Syntaxfehler werden nicht als ParseError erfasst.'
);
$assert(
    str_contains($source, "'/node_modules/'") && str_contains($source, "'/tmp/'"),
    'Temporäre Abhängigkeiten werden in den Gesamtsyntaxtests nicht ausgeschlossen.'
);

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK PHP syntax self-test uses the in-process parser.\n";
