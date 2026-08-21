<?php

declare(strict_types=1);

$source = (string)file_get_contents(dirname(__DIR__) . '/dbxPackageCatalog.class.php');
$required = array(
    '$signed_catalog = $this->fetch($this->catalog_url);',
    '$catalog = $this->validate($signed_catalog, true);',
    '$this->write_json($cache, $signed_catalog);',
);

foreach ($required as $contract) {
    if (!str_contains($source, $contract)) {
        throw new RuntimeException('Der signierte Marktplatzcache verliert sein Originalformat: ' . $contract);
    }
}
if (str_contains($source, '$this->write_json($cache, $catalog);')) {
    throw new RuntimeException('Die normalisierte Arbeitsform darf nicht als signierter Katalogcache gespeichert werden.');
}

echo "Signierter Marktplatzcache bleibt unverändert: OK\n";
