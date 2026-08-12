<?php
declare(strict_types=1);

$dbxDir = dirname(__DIR__, 2);
$projectDir = dirname($dbxDir);

require_once $dbxDir . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/dbxKernel.php';

$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$expected = trim((string) file_get_contents($projectDir . '/VERSION'));
$api = dbx();
$assert(
    method_exists($api, 'get_version') && $api->get_version() === $expected,
    'dbx()->get_version() liefert nicht die installierte VERSION.'
);

$tpl = $api->get_system_obj('dbxTPL');
$rendered = $tpl->replaces_dbx('Installiert: {dbx:version}; Asset: {dbx:asset_version}');
$escapedVersion = htmlspecialchars($expected, ENT_QUOTES, 'UTF-8');
$assert(
    preg_match(
        '/^Installiert: ' . preg_quote($escapedVersion, '/')
        . '; Asset: ' . preg_quote($escapedVersion, '/') . '\\.\\d+$/',
        $rendered
    ) === 1,
    'Die globalen Versions-Platzhalter werden nicht korrekt ersetzt.'
);

$source = (string) file_get_contents(dirname(__DIR__) . '/dbxTPL.class.php');
$assert(
    substr_count($source, "str_replace('{dbx:version}'") === 1,
    'Die Versionsvariable muss an genau einer zentralen Stelle ersetzt werden.'
);
$assert(
    substr_count($source, "str_replace('{dbx:asset_version}'") === 1,
    'Die Asset-Version muss an genau einer zentralen Stelle ersetzt werden.'
);

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK installed version is exposed once through {dbx:version}.\n";
