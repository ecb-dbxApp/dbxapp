<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$manifest = json_decode((string)file_get_contents($root . '/dbx/third-party-sources.json'), true);
$errors = array();

foreach ((array)($manifest['sources'] ?? array()) as $source) {
    $path = (string)($source['path'] ?? '');
    if ($path === '' || !is_file($root . '/' . $path)) {
        $errors[] = "Fremdquelle fehlt: {$path}";
    }
    $adapter = (string)($source['adapter'] ?? '');
    if ($adapter !== '' && !is_file($root . '/' . $adapter)) {
        $errors[] = "Fremdcode-Adapter fehlt: {$adapter}";
    }
    if (str_starts_with((string)($source['mode'] ?? ''), 'isolated-')
        && !str_starts_with($path, 'dbx/add_ons/')) {
        $errors[] = "Isolierte Fremdquelle liegt nicht unter add_ons: {$path}";
    }
}

require_once $root . '/dbx/include/dbxUpload.class.php';
require_once $root . '/dbx/include/dbxBarcode.class.php';
if (!class_exists('dbxUpload', false) || !class_exists('dbxBarcode', false)) {
    $errors[] = 'Upload-/Barcode-Adapter laden ihre Systemklasse nicht.';
}

if ($errors !== array()) {
    fwrite(STDERR, "Fremdcode-Isolation fehlerhaft:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "OK Fremdcodequellen sind manifestiert, isoliert und ueber stabile Adapter erreichbar.\n";
