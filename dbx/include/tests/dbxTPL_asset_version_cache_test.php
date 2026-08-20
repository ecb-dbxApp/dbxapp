<?php

declare(strict_types=1);

/**
 * Prüft den installationsweiten Kurzzeitcache der Asset-Version ohne Quellscan.
 */

class dbxObj {}
require_once dirname(__DIR__) . '/dbxTPL.class.php';

$root = dirname(__DIR__, 3);
$cache_dir = $root . '/files/sys/cache';
$cache_file = $cache_dir . '/asset-version.json';
$previous = is_file($cache_file) ? (string)file_get_contents($cache_file) : null;
$base = 'asset-cache-test-' . bin2hex(random_bytes(4));
$mtime = 1700000123;

if (!is_dir($cache_dir) && !mkdir($cache_dir, 0775, true) && !is_dir($cache_dir)) {
    fwrite(STDERR, "FAIL Asset-Cacheverzeichnis kann nicht angelegt werden.\n");
    exit(1);
}

try {
    file_put_contents($cache_file, json_encode(array(
        'base' => $base,
        'latest_mtime' => $mtime,
        'scanned_at' => time(),
    ), JSON_UNESCAPED_SLASHES));

    $tpl = new dbxTPL();
    $method = new ReflectionMethod($tpl, 'asset_version');
    $started = hrtime(true);
    $actual = $method->invoke($tpl, $base);
    $duration_ms = (hrtime(true) - $started) / 1e6;

    // Windows-Virenscanner können den ersten Dateizugriff kurz verzögern. Die
    // Grenze bleibt deutlich unter dem gemessenen Vollscan (ca. 160 ms), ohne
    // einen korrekten Cachetreffer wegen Scheduling-Jitter abzulehnen.
    if ($actual !== $base . '.' . $mtime || $duration_ms > 75.0) {
        fwrite(STDERR, "FAIL Asset-Version nutzt den gültigen Kurzzeitcache nicht: {$actual}, {$duration_ms} ms.\n");
        exit(1);
    }
} finally {
    if ($previous === null) @unlink($cache_file);
    else file_put_contents($cache_file, $previous, LOCK_EX);
}

echo "OK Asset-Version aus installationsweitem Kurzzeitcache ohne Verzeichnis-Scan.\n";
