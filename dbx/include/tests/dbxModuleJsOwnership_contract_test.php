<?php
declare(strict_types=1);

$dbx = dirname(__DIR__, 2);
require_once __DIR__ . '/dbxModuleSourceBundle.php';
$errors = array();
$module_libraries = array(
    'dbxAdmin' => array('adminDashboard.js', 'modulesAdmin.js'),
    'dbxContent' => array('content.js', 'gallery.js'),
    'dbxContent_admin' => array(
        'cms.js', 'cms-marker.js', 'cms-context.js', 'cms-components.js', 'cms-editor.js', 'cms-page.js', 'cms-tree.js', 'cms-media.js',
        'cms-language.js', 'cms-jodit-image.js', 'seoAdmin.js',
    ),
    'dbxDesign_admin' => array('icons.js', 'icons_editor.js'),
    'dbxEditor' => array('ace.js'),
    'dbxKi' => array('kiBriefing.js', 'kiResultWindow.js'),
    'dbxShop_admin' => array('shopAdmin.js'),
);

foreach ($module_libraries as $module => $files) {
    foreach ($files as $file) {
        $module_file = $dbx . '/modules/' . $module . '/js/' . $file;
        if (!is_file($module_file) || filesize($module_file) === 0) {
            $errors[] = $module . '/js/' . $file . ' fehlt';
        }
        if (is_file($dbx . '/js/lib/' . $file)) {
            $errors[] = $file . ' liegt weiterhin im globalen Bibliotheksordner';
        }
    }
}

foreach (glob($dbx . '/modules/*/tpl/js/*.js') ?: array() as $legacy) {
    $errors[] = 'JavaScript liegt noch unter tpl/js: ' . str_replace('\\', '/', $legacy);
}

$core = (string)file_get_contents($dbx . '/js/lib/core.js');
foreach (array(
    'modules/" + module + "/js/" + libName + ".js"',
    'dbx.resolveFeature(t.lib, function (ok)',
    't.cfg && t.cfg.module',
    'if (type === "module")',
) as $needle) {
    if (!str_contains($core, $needle)) {
        $errors[] = 'Core-Modullader unvollstaendig: ' . $needle;
    }
}

$global_libraries = array('ajax.js', 'confirm.js', 'core.js', 'runtime.js', 'scheduler.js', 'form.js',
    'grid.js', 'grid-state.js', 'grid-export.js', 'grid-transport.js', 'grid-columns.js', 'grid-ui.js',
    'openWin.js', 'process.js', 'report.js', 'utilities.js');
foreach ($global_libraries as $file) {
    if (!is_file($dbx . '/js/lib/' . $file)) {
        $errors[] = 'Globale Bibliothek fehlt: ' . $file;
    }
}

$asset_registry = (string)file_get_contents($dbx . '/include/dbxAssetRegistry.class.php');
if (!str_contains($asset_registry, "'modules/' . \$module . '/js/' . \$file")) {
    $errors[] = 'dbxAssetRegistry registriert Modul-JavaScript nicht aus {Modul}/js';
}

if ($errors) {
    fwrite(STDERR, "Modul-JavaScript-Vertrag verletzt:\n - " . implode("\n - ", $errors) . "\n");
    exit(1);
}

echo "OK: Modul-JavaScript ist autonom unter {Modul}/js; globale Libraries bleiben zentral.\n";
