<?php
declare(strict_types=1);

/** Vertrag: Fachmodule delegieren an Objekt-Services; Traits sind nur deren begrenzte interne Segmente. */
$root = dirname(__DIR__, 2);
$failures = array();
$services = array(
    'dbxContent_admin/include/dbxContent_cms.class.php' => 'dbxContent_cms',
    'dbxKi/include/dbxKiCmsService.class.php' => 'dbxKiCmsService',
    'dbxKi/include/dbxKiBriefingService.class.php' => 'dbxKiBriefingService',
    'dbxShop/include/dbxShopService.class.php' => 'dbxShopService',
    'dbxShop_admin/include/dbxShopAdmin.class.php' => 'dbxShopAdmin',
    'dbxAdmin/include/dbxDashboard.class.php' => 'dbxDashboard',
    'dbxAdmin/include/dbxSchema.class.php' => 'dbxSchema',
);
foreach ($services as $relative => $class) {
    $file = $root . '/modules/' . $relative;
    $source = is_file($file) ? (string)file_get_contents($file) : '';
    if (!preg_match('/\bclass\s+' . preg_quote($class, '/') . '\b/', $source)) {
        $failures[] = 'Expliziter Modulservice fehlt: ' . $relative;
    }
}

$controllers = array(
    'dbxContent_admin/dbxContent_admin.class.php' => "get_include_obj('dbxContent_cms'",
    'dbxKi/dbxKi.class.php' => "get_include_obj('dbxKiCmsService'",
    'dbxShop/dbxShop.class.php' => "get_include_obj('dbxShopService'",
    'dbxShop_admin/dbxShop_admin.class.php' => "get_include_obj('dbxShopAdmin'",
    'dbxAdmin/dbxAdmin.class.php' => "get_include_obj('dbxDashboard'",
);
foreach ($controllers as $relative => $delegation) {
    $source = (string)file_get_contents($root . '/modules/' . $relative);
    if (!str_contains($source, $delegation)) {
        $failures[] = 'Modulcontroller delegiert nicht an seinen Service: ' . $relative;
    }
}

$module_roots = array('dbxContent_admin', 'dbxKi', 'dbxShop', 'dbxShop_admin', 'dbxAdmin');
foreach ($module_roots as $module) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
        $root . '/modules/' . $module . '/include',
        FilesystemIterator::SKIP_DOTS
    ));
    foreach ($iterator as $file) {
        if (!$file->isFile() || !str_ends_with($file->getFilename(), '.trait.php')) continue;
        $lines = substr_count((string)file_get_contents($file->getPathname()), "\n") + 1;
        if ($lines > 1000) {
            $failures[] = 'Internes Service-Segment ist groesser als 1000 Zeilen: '
                . $module . '/include/' . $file->getFilename() . ' (' . $lines . ')';
        }
    }
}

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "OK Modulcontroller delegieren an explizite Services; interne Segmente bleiben unter 1000 Zeilen.\n";
