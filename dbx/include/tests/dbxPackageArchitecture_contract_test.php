<?php

declare(strict_types=1);

function package_arch_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__, 3);
$manager = (string)file_get_contents($root . '/dbx/include/dbxPackageManager.class.php');
$catalog = (string)file_get_contents($root . '/dbx/include/dbxPackageCatalog.class.php');
$controller = (string)file_get_contents($root . '/dbx/modules/dbxAdmin/include/dbxUpdate.class.php');
$template = (string)file_get_contents($root . '/dbx/modules/dbxAdmin/tpl/htm/admin-update.htm');
$update_js = (string)file_get_contents($root . '/dbx/modules/dbxAdmin/js/adminUpdate.js');

$product_version = trim((string)file_get_contents($root . '/VERSION'));
package_arch_assert(
    preg_match('/^\d+\.\d+\.\d+$/', $product_version) === 1,
    'Die zentrale Produktversion ist keine gueltige Releaseversion.'
);
$kernel_manifest = json_decode((string)file_get_contents($root . '/dbx.package.json'), true);
package_arch_assert(
    is_array($kernel_manifest) && ($kernel_manifest['version'] ?? '') === $product_version,
    'Kernelmanifest und zentrale Produktversion stimmen nicht ueberein.'
);
package_arch_assert(!is_file($root . '/UPDATE_BASELINE'), 'Legacy-Baseline ist noch vorhanden.');
package_arch_assert(!is_file($root . '/dbx/modules/dbxAdmin/include/dbxUpdateService.class.php'), 'Monolithischer Legacy-Updater ist noch vorhanden.');
foreach (array('dbxPackageManager', 'prepare(array $ids', 'install_prepared', 'rollback', 'resolve_dependencies') as $needle) {
    package_arch_assert(str_contains($manager, $needle), 'Paketmanager-Vertrag fehlt: ' . $needle);
}
package_arch_assert(
    str_contains($manager, "\$relative !== \$this->contract->manifest_path"),
    'Nur Manifeste am kanonischen Installationspfad duerfen als installiert gelten.'
);
foreach (array('verify_signed_document', 'guard_sequence', 'security', 'entitlement') as $needle) {
    package_arch_assert(str_contains($catalog, $needle), 'Katalog-Sicherheitsvertrag fehlt: ' . $needle);
}
foreach (array('package_ids[]', 'package_prepare', 'package_install', 'package_rollback') as $needle) {
    package_arch_assert(str_contains($controller . $template, $needle), 'Auswahl-UI-Vertrag fehlt: ' . $needle);
}
foreach (array('package_install_id', 'package_install_now', 'download_install_label', 'valid_package_id') as $needle) {
    package_arch_assert(str_contains($controller, $needle), 'Direkte Modulininstallation fehlt: ' . $needle);
}
package_arch_assert(
    str_contains($controller, "in_array(\$type, array('module', 'design'), true)"),
    'Nicht installierte Module und Designs muessen direkt in ihrem Bereich erscheinen.'
);
foreach (array('package-kernel', 'package-modules', 'package-designs', 'package-marketplace') as $needle) {
    package_arch_assert(str_contains($template, $needle), 'UI-Bereich fehlt: ' . $needle);
    package_arch_assert(
        str_contains($template, 'href="{action}#' . $needle . '"'),
        'Sprunglink muss trotz globalem HTML-base auf der Update-Route bleiben: ' . $needle
    );
}
foreach (array('package_section', 'lazy_section', 'status($force, array())') as $needle) {
    package_arch_assert(str_contains($controller, $needle), 'Lazy-Paketcontroller fehlt: ' . $needle);
}
foreach (array('lib=adminUpdate', 'data-dbx-package-lazy') as $needle) {
    package_arch_assert(str_contains($template . $controller, $needle), 'Lazy-Paket-UI fehlt: ' . $needle);
}
package_arch_assert(
    str_contains($template, '.dbx-package-lazy[data-dbx-package-state="loaded"]{display:contents}'),
    'Geladene Lazy-Bereiche muessen ihre Karten an das uebergeordnete Raster uebergeben.'
);
foreach (array('IntersectionObserver', 'requestQueue', 'dbx.ajax.request', 'dbx-package-loader', 'dbx.feature.register("adminUpdate"') as $needle) {
    package_arch_assert(str_contains($update_js, $needle), 'Lazy-Paket-JavaScript fehlt: ' . $needle);
}
package_arch_assert(
    !is_file($root . '/dbx/modules/dbxAdmin/tpl/htm/admin-update_en.htm')
        && !is_file($root . '/dbx/modules/dbxAdmin/tpl/htm/admin-update_es.htm'),
    'Sprachspezifische Update-Templates duerfen die gemeinsame Marktplatz-UI nicht duplizieren.'
);
foreach (glob($root . '/dbx/modules/*', GLOB_ONLYDIR) ?: array() as $directory) {
    package_arch_assert(is_file($directory . '/dbx.package.json'), 'Modulmanifest fehlt: ' . basename($directory));
}
foreach (glob($root . '/dbx/design/*', GLOB_ONLYDIR) ?: array() as $directory) {
    package_arch_assert(is_file($directory . '/dbx.package.json'), 'Designmanifest fehlt: ' . basename($directory));
}
$my_x_file = $root . '/dbx/modules/myX/dbx.package.json';
if (is_file($my_x_file)) {
    $my_x = json_decode((string)file_get_contents($my_x_file), true);
    package_arch_assert(is_array($my_x) && empty($my_x['managed']) && ($my_x['license'] ?? '') === 'private', 'myX ist nicht dauerhaft lokal und unveraendert geschuetzt.');
}
$my_lkw_file = $root . '/dbx/modules/myLKW/dbx.package.json';
$my_lkw = is_file($my_lkw_file)
    ? json_decode((string)file_get_contents($my_lkw_file), true)
    : null;
if (!is_array($my_lkw)) {
    $market_catalog = json_decode((string)file_get_contents($root . '/dbx/marketplace/catalog.json'), true);
    foreach ((array)($market_catalog['packages'] ?? array()) as $market_package) {
        if (($market_package['id'] ?? '') === 'dbxapp/module/myLKW') {
            $my_lkw = $market_package;
            break;
        }
    }
}
package_arch_assert(
    is_array($my_lkw)
        && !empty($my_lkw['managed'])
        && ($my_lkw['id'] ?? '') === 'dbxapp/module/myLKW'
        && ($my_lkw['license'] ?? '') === 'paid'
        && ($my_lkw['icon'] ?? '') === 'bi-truck'
        && ($my_lkw['image'] ?? '') === 'dbx/modules/myLKW/tpl/img/myLKW.png'
        && trim((string)($my_lkw['descriptions']['de'] ?? '')) !== '',
    'myLKW ist nicht als öffentliches kostenpflichtiges Marktplatzmodul beschrieben.'
);

echo "dbxPackageArchitecture: Kernel, Designs, Module, Marktplatz und No-Legacy-Vertrag vollstaendig.\n";
