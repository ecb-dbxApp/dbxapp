<?php
declare(strict_types=1);

/**
 * Bindet die externe Dokumentations-QA in dbxSelfTest ein. Die Prüfung wird
 * nur aktiviert, wenn eine Dokumentationsinstallation neben dbxapp liegt oder
 * DBX_DOCS_ROOT ausdrücklich gesetzt wurde.
 */

$product_root = dirname(__DIR__, 4);
$configured_root = trim((string)getenv('DBX_DOCS_ROOT'));
$development_root = dirname($product_root) . DIRECTORY_SEPARATOR . 'dbxapp-docs';
$production_mirror_root = dirname($product_root) . DIRECTORY_SEPARATOR . 'doku.dbxapp.de';
$docs_root = $configured_root !== ''
    ? $configured_root
    : (is_file($development_root . DIRECTORY_SEPARATOR . 'reference'
        . DIRECTORY_SEPARATOR . 'test-documentation-quality.php')
        ? $development_root
        : $production_mirror_root);
$quality_test = rtrim($docs_root, '/\\') . DIRECTORY_SEPARATOR . 'reference'
    . DIRECTORY_SEPARATOR . 'test-documentation-quality.php';

if (!is_file($quality_test)) {
    echo 'SKIP: Externe Dokumentationsinstallation wurde nicht gefunden.' . PHP_EOL;
    exit(0);
}

require $quality_test;
