<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/dbx/include/dbxPackageContract.class.php';

$contract = new dbxPackageContract();
$catalog_url = 'https://github.com/ecb-dbxApp/dbxapp/releases/latest/download/catalog.json';
$versioned_catalog = 'https://github.com/ecb-dbxApp/dbxapp/releases/download/v4.4.4/catalog.json';
$artifact_url = 'https://github.com/ecb-dbxApp/dbxapp/releases/download/v4.4.4/dbxapp-kernel-dbxapp-4.4.4.zip';

if (!$contract->trusted_catalog_source($catalog_url)
    || !$contract->trusted_catalog_source($versioned_catalog)
    || !$contract->trusted_artifact_source($artifact_url)) {
    throw new RuntimeException('Der fest begrenzte GitHub-Releasepfad wird nicht als vertrauenswürdig erkannt.');
}

foreach (array(
    'http://github.com/ecb-dbxApp/dbxapp/releases/latest/download/catalog.json',
    'https://github.com/other/dbxapp/releases/latest/download/catalog.json',
    'https://github.com/ecb-dbxApp/dbxapp/releases/latest/download/dbxapp-kernel-dbxapp-4.4.4.zip',
    'https://github.com/ecb-dbxApp/dbxapp/releases/download/v4.4.4/../secret.zip',
) as $untrusted) {
    if ($contract->trusted_catalog_source($untrusted)
        || $contract->trusted_artifact_source($untrusted)) {
        throw new RuntimeException('Ein fremder oder ungenauer Releasepfad wurde akzeptiert: ' . $untrusted);
    }
}

$config = (string)file_get_contents($root . '/dbx/modules/dbxAdmin/cfg/config.php');
if (!str_contains($config, $catalog_url)) {
    throw new RuntimeException('Die zentrale Marktplatzkonfiguration verwendet nicht den GitHub-Latest-Katalog.');
}

echo "GitHub-Release-Vertrauensgrenze: OK\n";
