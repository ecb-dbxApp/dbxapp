<?php

declare(strict_types=1);

$root = realpath(dirname(__DIR__));
$package_root = isset($argv[1]) ? realpath($argv[1]) : false;
$manifest_file = isset($argv[2]) ? realpath($argv[2]) : false;
if ($root === false || $package_root === false || $manifest_file === false || !is_file($manifest_file)) {
    fwrite(STDERR, "Aufruf: php tools/review-marketplace-package.php <entpacktes-verzeichnis> <manifest.json>\n");
    exit(2);
}
require_once $root . '/dbx/include/dbxPackageSecurityScanner.class.php';
$manifest = json_decode((string)file_get_contents($manifest_file), true);
if (!is_array($manifest)) {
    fwrite(STDERR, "Manifest ist kein gueltiges JSON.\n");
    exit(2);
}
$result = (new dbxPackageSecurityScanner())->scan($manifest, $package_root);
echo (string)json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($result['approved'] ? 0 : 1);
