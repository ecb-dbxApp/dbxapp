<?php

declare(strict_types=1);

$source = (string)file_get_contents(dirname(__DIR__) . '/build-marketplace.php');
$required = array(
    'marketplace_public_sequence($contract, $root)',
    '$sequence = max($local_sequence, $public_sequence) + 1;',
    '$contract->verify_signed_document($catalog, $keys);',
    "'catalog.json'",
);
foreach ($required as $contract) {
    if (!str_contains($source, $contract)) {
        throw new RuntimeException('Publisher-Rollbackschutz fehlt: ' . $contract);
    }
}
if (str_contains($source, '$sequence = max(0, (int)($state[\'sequence\'] ?? 0)) + 1;')) {
    throw new RuntimeException('Der Publisher darf nicht nur seinem lokalen Sequenzstand vertrauen.');
}

echo "Öffentliche Marktplatzsequenz schützt den Publisher vor Rollback: OK\n";
