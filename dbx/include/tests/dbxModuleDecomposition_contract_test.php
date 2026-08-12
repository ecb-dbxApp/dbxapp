<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once __DIR__ . '/dbxModuleSourceBundle.php';
$targets = array(
    array(
        'name' => 'CMS',
        'source' => $root . '/dbx/modules/dbxContent_admin/include/dbxContent_cms.class.php',
        'manifest' => $root . '/dbx/modules/dbxContent_admin/cfg/cms-actions.php',
    ),
    array(
        'name' => 'Shop',
        'source' => $root . '/dbx/modules/dbxShop_admin/include/dbxShopAdmin.class.php',
        'manifest' => $root . '/dbx/modules/dbxShop_admin/cfg/actions.php',
    ),
    array(
        'name' => 'Schema',
        'source' => $root . '/dbx/modules/dbxAdmin/include/dbxSchema.class.php',
        'manifest' => $root . '/dbx/modules/dbxAdmin/cfg/schema-actions.php',
    ),
);
$failures = array();
$routes = 0;
foreach ($targets as $target) {
    $source = dbx_test_module_source_bundle($target['source']);
    $manifest = require $target['manifest'];
    if (!is_array($manifest) || $manifest === array()) {
        $failures[] = $target['name'] . ': Manifest fehlt.';
        continue;
    }
    foreach ($manifest as $action => $definition) {
        $routes++;
        $handler = preg_quote((string)($definition['handler'] ?? ''), '/');
        if ($handler === '' || preg_match('/function\s+' . $handler . '\s*\(/', $source) !== 1) {
            $failures[] = $target['name'] . '.' . $action . ': Handler fehlt.';
        }
        foreach (array('methods', 'groups', 'mutation', 'response') as $field) {
            if (!array_key_exists($field, $definition)) {
                $failures[] = $target['name'] . '.' . $action . ': ' . $field . ' fehlt.';
            }
        }
    }
}
if ($routes < 70) $failures[] = 'Zu wenige ausgelagerte Modulrouten: ' . $routes;
$cmsOptionCatalog = $root . '/dbx/modules/dbxContent_admin/include/dbxContentCmsOptionCatalog.class.php';
if (!is_file($cmsOptionCatalog)
    || !str_contains(dbx_test_module_source_bundle($targets[0]['source']), 'cms_options()')
    || count(file($targets[0]['source'])) > 250
) {
    $failures[] = 'CMS-Formularoptionen sind nicht aus dem Ablauf-Controller ausgelagert.';
}
$cmsTraits = glob($root . '/dbx/modules/dbxContent_admin/include/dbxContentCms*Service.trait.php') ?: array();
$shopTraits = glob($root . '/dbx/modules/dbxShop_admin/include/dbxShopAdmin*Service.trait.php') ?: array();
$schemaTraits = glob($root . '/dbx/modules/dbxAdmin/include/dbxSchema*Service.trait.php') ?: array();
if (count($cmsTraits) < 10 || count($shopTraits) < 10 || count($schemaTraits) < 9) {
    $failures[] = 'CMS, Shop oder Schema ist nicht vollstaendig nach Verantwortlichkeiten zerlegt.';
}
if (count(file($root . '/dbx/modules/dbxAdmin/include/dbxSchema.class.php')) > 100) {
    $failures[] = 'dbxSchema.class.php ist wieder groesser als 100 Zeilen und damit kein schlanker Controller mehr.';
}
foreach (array_merge($cmsTraits, $shopTraits, $schemaTraits) as $traitFile) {
    if (count(file($traitFile)) > 1000) {
        $failures[] = basename($traitFile) . ': Service-Block ist wieder groesser als 1000 Zeilen.';
    }
}
if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "OK CMS/Shop/Schema-Dispatch in {$routes} validierte Routen zerlegt.\n";
