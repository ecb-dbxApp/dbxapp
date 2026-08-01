<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);

$fail = static function (string $message, int $code): void {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit($code);
};

$read = static function (string $relative) use ($root, $fail): string {
    $file = $root . '/' . ltrim($relative, '/');
    if (!is_file($file)) {
        $fail('Dokumentationsquelle fehlt: ' . $relative, 1);
    }
    $content = file_get_contents($file);
    if (!is_string($content) || trim($content) === '') {
        $fail('Dokumentationsquelle ist leer: ' . $relative, 2);
    }
    return $content;
};

$version = trim($read('VERSION'));
$main = $read('00_Doxygen_Mainpage.md');
$installationReference = $read('27_Installation_Updates_DD_Serverbindungen.md');
$portalReference = $read('29_Dokumentationsportal.md');
$shopReference = $read('17_Shop_Leitfaden.md');
$installation = $read('dbx/modules/dbxDocs/content/dbxapp_user_installation.html');
$installationTutorial = $read('dbx/modules/dbxDocs/content/tutorial_installation.html');
$selfTest = $read('dbx/modules/dbxDocs/content/dbxapp_user_selftest.html');
$provisioner = $read('dbx/modules/dbxDocs/include/dbxDocsContentProvision.class.php');
$selfTestRunner = $read('dbx/modules/dbxSelfTest/include/dbxSelfTestRunner.class.php');
$doxyfile = $read('Doxyfile');

if (!str_contains($main, '**Version:** ' . $version)) {
    $fail('Die redaktionelle Hauptseite nennt nicht die Release-Version ' . $version . '.', 3);
}
if (!str_contains($doxyfile, 'PROJECT_NUMBER         = "' . $version . '"')) {
    $fail('Doxygen PROJECT_NUMBER stimmt nicht mit VERSION überein.', 4);
}
if (preg_match('#`admin`\s*/\s*`admin`#u', $installationReference) === 1) {
    $fail('Die Installationsreferenz dokumentiert noch den entfernten Standardzugang admin/admin.', 5);
}
foreach (array(
    'persönlichen Passwort',
    'Schritt 5',
    'dbxSelfTest',
) as $needle) {
    if (!str_contains($installationReference, $needle)) {
        $fail('Aktueller Installationsvertrag fehlt: ' . $needle, 6);
    }
}
foreach (array(
    'PHP 8.2 oder neuer',
    'Die sieben Installationsschritte',
    'DB3 oder PDO?',
    'config.local.php',
    'persönlichen Passwort',
    'dokumentation-selbsttest',
    'dokumentation-system-update',
) as $needle) {
    if (!str_contains($installation, $needle)) {
        $fail('Installationsanleitung ist unvollständig: ' . $needle, 7);
    }
}
foreach (array(
    'dbxapp Schritt für Schritt installieren',
    'Systemvoraussetzungen prüfen',
    'Die Datenbankverbindung schlägt fehl',
    'Abnahmeprotokoll',
    'dokumentation-selbsttest',
) as $needle) {
    if (!str_contains($installationTutorial, $needle)) {
        $fail('Installations-Tutorial ist unvollständig: ' . $needle, 14);
    }
}
foreach (array(
    'Schnelltest',
    'Kompletttest',
    'Auswahl testen',
    'Einzeltest',
    'JavaScript ohne Node.js auf dem Webserver',
    'files/sys/selftest',
    '--profile=quick',
    '--profile=full',
    '--test=&lt;test-id&gt;',
    'letzten 20 Läufe',
) as $needle) {
    if (!str_contains($selfTest, $needle)) {
        $fail('SelfTest-Anleitung ist unvollständig: ' . $needle, 8);
    }
}
foreach (array(
    '2026-08-01-installation-3',
    '2026-08-01-tutorial-installation-3',
    '2026-08-01-selftest-3',
    "'permalink' => 'dokumentation-installation'",
    "'permalink' => 'tutorial-installation'",
    "'permalink' => 'dokumentation-selbsttest'",
    "'folder_key' => 'operations'",
) as $needle) {
    if (!str_contains($provisioner, $needle)) {
        $fail('Reproduzierbare CMS-Provisionierung fehlt: ' . $needle, 9);
    }
}
if (!str_contains($selfTestRunner, "version_compare(PHP_VERSION, '8.2.0', '<')")) {
    $fail('dbxSelfTest und Installer verwenden nicht dieselbe PHP-Mindestversion.', 10);
}
$designTemplate = $read('dbx/design/dbxapp/htm/default.htm');
if (preg_match('/core\.js\?[^"\']*v=(\d+)/', $designTemplate, $assetMatch) !== 1
    || !str_contains($shopReference, 'dbxapp-Asset-Version ' . $assetMatch[1])) {
    $fail('Der Shop-Leitfaden nennt nicht die aktuelle JavaScript-Asset-Version.', 13);
}
foreach (array('reference\\archive', 'provision_docs_content.php', 'dbxSelfTest') as $needle) {
    if (!str_contains($portalReference, $needle)) {
        $fail('Betriebsanleitung des Dokumentationsportals ist unvollständig: ' . $needle, 11);
    }
}

foreach (array('Installation' => $installation, 'Installations-Tutorial' => $installationTutorial, 'SelfTest' => $selfTest) as $label => $html) {
    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $loaded = $dom->loadHTML(
        '<?xml encoding="UTF-8"><!doctype html><html><body>' . $html . '</body></html>',
        LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded
        || $dom->getElementsByTagName('h1')->length !== 1
        || $dom->getElementsByTagName('h2')->length < 5
        || $dom->getElementsByTagName('h3')->length < 1) {
        $fail($label . '-Anleitung ist kein vollständiges, parsebares HTML-Dokument.', 12);
    }
}

echo "OK installation and dbxSelfTest guides match release {$version}\n";
