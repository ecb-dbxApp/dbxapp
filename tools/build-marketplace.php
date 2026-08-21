<?php

declare(strict_types=1);

/** @return array<string,mixed>|null */
function marketplace_fetch_json(string $url): ?array
{
    if (!extension_loaded('curl') || strtolower((string)parse_url($url, PHP_URL_SCHEME)) !== 'https') {
        return null;
    }
    $curl = curl_init($url);
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'dbxapp-marketplace-publisher/1.0',
        CURLOPT_HTTPHEADER => array('Accept: application/vnd.github+json'),
    ));
    $content = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $effective_url = (string)curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
    curl_close($curl);
    $host = strtolower((string)parse_url($effective_url, PHP_URL_HOST));
    if (!is_string($content) || $status < 200 || $status >= 300
        || !in_array($host, array(
            'api.github.com', 'github.com', 'release-assets.githubusercontent.com',
            'objects.githubusercontent.com',
        ), true)) {
        return null;
    }
    $decoded = json_decode($content, true);
    return is_array($decoded) ? $decoded : null;
}

function marketplace_public_sequence(dbxPackageContract $contract, string $root): int
{
    $releases = marketplace_fetch_json(
        'https://api.github.com/repos/ecb-dbxApp/dbxapp/releases?per_page=50'
    );
    if ($releases === null) {
        throw new RuntimeException(
            'Die öffentliche Marktplatzsequenz konnte nicht ermittelt werden; Veröffentlichung abgebrochen.'
        );
    }
    $trust = json_decode((string)file_get_contents($root . '/dbx/marketplace/trust.json'), true);
    $keys = array();
    foreach ((array)($trust['keys'] ?? array()) as $key_id => $entry) {
        $file = is_array($entry) ? (string)($entry['file'] ?? '') : '';
        if (preg_match('/^[A-Za-z0-9._-]+\.pem$/', $file) !== 1) {
            continue;
        }
        $path = $root . '/dbx/marketplace/keys/' . $file;
        if (is_file($path)) {
            $keys[(string)$key_id] = (string)file_get_contents($path);
        }
    }
    $maximum = 0;
    foreach ($releases as $release) {
        if (!is_array($release) || !empty($release['draft'])) {
            continue;
        }
        foreach ((array)($release['assets'] ?? array()) as $asset) {
            if (!is_array($asset) || (string)($asset['name'] ?? '') !== 'catalog.json') {
                continue;
            }
            $catalog = marketplace_fetch_json((string)($asset['browser_download_url'] ?? ''));
            if ($catalog === null) {
                continue;
            }
            try {
                $contract->verify_signed_document($catalog, $keys);
                $maximum = max($maximum, (int)($catalog['sequence'] ?? 0));
            } catch (Throwable) {
                // Nicht signierte oder fremd signierte Alt-Releases bilden
                // niemals die Rollback-Grenze des Produktionskatalogs.
            }
        }
    }
    return $maximum;
}

$root = realpath(dirname(__DIR__));
if ($root === false) {
    fwrite(STDERR, "Projektwurzel fehlt.\n");
    exit(1);
}
require_once $root . '/dbx/include/dbxPackageContract.class.php';
require_once $root . '/dbx/include/dbxPackageSecurityScanner.class.php';
$contract = new dbxPackageContract();
$scanner = new dbxPackageSecurityScanner();
$key_id = $argv[1] ?? 'dbxapp-market-2026';
$release_version = trim((string)file_get_contents($root . '/VERSION'));
$base_url = rtrim($argv[2] ?? ('https://github.com/ecb-dbxApp/dbxapp/releases/download/v' . $release_version), '/');
$private_file = isset($argv[3]) && trim((string)$argv[3]) !== ''
    ? (string)$argv[3]
    : $root . '/files/sys/marketplace/keys/' . $key_id . '-private.pem';
if (!is_file($private_file)) {
    fwrite(STDERR, "Privater Marktplatzschluessel fehlt. Zuerst init-marketplace-key.php ausfuehren.\n");
    exit(2);
}
$private_key = openssl_pkey_get_private((string)file_get_contents($private_file));
if ($private_key === false) {
    throw new RuntimeException('Privater Marktplatzschluessel ist ungueltig.');
}
$output = $root . '/files/update/marketplace';
if (!is_dir($output) && !mkdir($output, 0770, true) && !is_dir($output)) {
    throw new RuntimeException('Marktplatzausgabe konnte nicht angelegt werden.');
}

$manifest_files = array_merge(
    array($root . '/dbx.package.json'),
    glob($root . '/dbx/modules/*/dbx.package.json') ?: array(),
    glob($root . '/dbx/design/*/dbx.package.json') ?: array()
);
$packages = array();
foreach ($manifest_files as $manifest_file) {
    $manifest = json_decode((string)file_get_contents($manifest_file), true);
    if (!is_array($manifest)) {
        throw new RuntimeException('Ungueltiges Manifest: ' . $manifest_file);
    }
    $manifest = $contract->validate_manifest($manifest, false);
    if (empty($manifest['managed'])) {
        continue;
    }
    $files = array();
    $roots = match ($manifest['type']) {
        'module' => array($root . '/dbx/modules/' . $manifest['name']),
        'design' => array($root . '/dbx/design/' . $manifest['name']),
        'kernel' => array(
            $root . '/dbx/include', $root . '/dbx/js', $root . '/dbx/css',
            $root . '/dbx/img', $root . '/dbx/add_ons', $root . '/dbx/vendor',
            $root . '/dbx/marketplace',
        ),
        default => array(),
    };
    if ($manifest['type'] === 'kernel') {
        foreach (array('VERSION', 'index.php') as $file) {
            if (is_file($root . '/' . $file)) {
                $files[$file] = $root . '/' . $file;
            }
        }
    }
    foreach ($roots as $source_root) {
        if (!is_dir($source_root)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source_root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) {
                continue;
            }
            $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/');
            if ($relative === $contract->manifest_path($manifest['type'], $manifest['name'])
                || in_array($relative, (array)($manifest['package_excludes'] ?? array()), true)
                || !$contract->path_allowed($manifest['type'], $manifest['name'], $relative)) {
                continue;
            }
            $files[$relative] = $file->getPathname();
        }
    }
    ksort($files, SORT_STRING);
    // Interne Build-Ausschluesse gehoeren weder in den oeffentlichen Katalog
    // noch in das auslieferbare Paketmanifest.
    $manifest['package_excludes'] = array();
    $manifest['files'] = array_map(static fn(string $file): string => strtolower((string)hash_file('sha256', $file)), $files);
    $manifest = $contract->validate_manifest($manifest, true);
    $review = $scanner->scan($manifest, $root);
    if (!$review['approved']) {
        throw new RuntimeException('Sicherheitspruefung fehlgeschlagen (' . $manifest['id'] . '): ' . implode('; ', $review['errors']));
    }
    $slug = preg_replace('/[^A-Za-z0-9._-]+/', '-', $manifest['id']) . '-' . $manifest['version'];
    $archive = $output . '/' . $slug . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Paketarchiv konnte nicht erzeugt werden: ' . $manifest['id']);
    }
    foreach ($files as $relative => $file) {
        $zip->addFile($file, $relative);
    }
    $zip->addFromString(
        $contract->manifest_path($manifest['type'], $manifest['name']),
        (string)json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
    );
    $zip->close();
    $catalog_entry = $manifest;
    $catalog_entry['files'] = array();
    $catalog_entry['security'] = array(
        'status' => 'approved',
        'reviewed_at' => gmdate('c'),
        'publisher' => 'dbxApp Release Pipeline',
        'scanned_files' => $review['scanned_files'],
    );
    $catalog_entry['artifact'] = array(
        'url' => $base_url . '/' . basename($archive),
        'sha256' => strtolower((string)hash_file('sha256', $archive)),
        'size' => filesize($archive),
    );
    $packages[] = $catalog_entry;
    echo 'BUILT ' . $manifest['id'] . ' ' . $manifest['version'] . PHP_EOL;
}
usort($packages, static fn(array $a, array $b): int => strnatcasecmp($a['id'], $b['id']));
$sequence_file = $root . '/files/sys/marketplace/publisher-state.json';
$state = json_decode((string)@file_get_contents($sequence_file), true);
$local_sequence = max(0, (int)($state['sequence'] ?? 0));
$public_sequence = marketplace_public_sequence($contract, $root);
$sequence = max($local_sequence, $public_sequence) + 1;
echo 'SEQUENZBASIS lokal=' . $local_sequence . ', oeffentlich=' . $public_sequence . PHP_EOL;
$catalog = array(
    'schema' => 1,
    'channel' => 'stable',
    'sequence' => $sequence,
    'generated_at' => gmdate('c'),
    'expires_at' => gmdate('c', time() + 7 * 86400),
    'packages' => $packages,
);
if (!openssl_sign($contract->canonical_json($catalog), $signature, $private_key, OPENSSL_ALGO_SHA256)) {
    throw new RuntimeException('Marktplatzkatalog konnte nicht signiert werden.');
}
$catalog['signature'] = array(
    'key_id' => $key_id,
    'algorithm' => 'rsa-sha256',
    'value' => base64_encode($signature),
);
$catalog_file = $output . '/catalog.json';
$json = json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if (!is_string($json) || file_put_contents($catalog_file, $json . "\n", LOCK_EX) === false) {
    throw new RuntimeException('Marktplatzkatalog konnte nicht geschrieben werden.');
}
$state_dir = dirname($sequence_file);
if (!is_dir($state_dir)) {
    mkdir($state_dir, 0770, true);
}
file_put_contents($sequence_file, json_encode(array('sequence' => $sequence, 'generated_at' => gmdate('c')), JSON_PRETTY_PRINT) . "\n", LOCK_EX);
echo 'KATALOG signiert: ' . count($packages) . ' Pakete, Sequenz ' . $sequence . PHP_EOL;
