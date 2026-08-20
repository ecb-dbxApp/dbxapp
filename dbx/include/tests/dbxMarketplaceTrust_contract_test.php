<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$trust = json_decode((string)file_get_contents($root . '/dbx/marketplace/trust.json'), true);
if (!is_array($trust) || !is_array($trust['keys'] ?? null) || $trust['keys'] === array()) {
    throw new RuntimeException('Der Marktplatz besitzt keinen öffentlichen Vertrauensanker.');
}

foreach ($trust['keys'] as $key_id => $entry) {
    $file = is_array($entry) ? (string)($entry['file'] ?? '') : '';
    if (preg_match('/^[A-Za-z0-9._-]+\.pem$/', $file) !== 1) {
        throw new RuntimeException('Ungueltiger Schluesselverweis: ' . $key_id);
    }
    $path = $root . '/dbx/marketplace/keys/' . $file;
    $public_key = is_file($path) ? openssl_pkey_get_public((string)file_get_contents($path)) : false;
    if ($public_key === false) {
        throw new RuntimeException('Oeffentlicher Marktplatzschluessel fehlt oder ist ungueltig: ' . $key_id);
    }
}

echo "dbxMarketplaceTrust_contract_test: OK\n";
