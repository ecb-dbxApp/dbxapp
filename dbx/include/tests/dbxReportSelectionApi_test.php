<?php

declare(strict_types=1);

/**
 * Verhindert, dass Module erneut die entfernte Report-Methode get_sel()
 * verwenden. Reportfilter und Pagination werden ausschließlich über die
 * validierende dbxForm-Schnittstelle get_fld_val() gelesen.
 */

$root = dirname(__DIR__, 3);
$failures = array();
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/dbx', FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $path = str_replace('\\', '/', (string)$file->getRealPath());
    if ($path === '' || str_contains($path, '/vendor/') || str_contains($path, '/tests/')) {
        continue;
    }
    $source = file_get_contents($path);
    if (is_string($source) && preg_match('/->get_sel\s*\(/', $source) === 1) {
        $failures[] = substr($path, strlen(str_replace('\\', '/', $root)) + 1);
    }
}

if ($failures) {
    fwrite(STDERR, "Entfernte Report-API get_sel() wird noch verwendet: " . implode(', ', $failures) . "\n");
    exit(1);
}

echo "OK: Reportselektionen verwenden die validierende get_fld_val()-API.\n";
