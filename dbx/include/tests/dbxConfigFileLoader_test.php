<?php

declare(strict_types=1);

/**
 * Prüft das isolierte Laden von config.php ohne eval und ohne HTML-Ausgabe.
 */

$root = dirname(__DIR__, 3);
require_once $root . '/dbx/vendor/autoload.php';
require_once dirname(__DIR__) . '/dbxKernel.php';
require_once __DIR__ . '/dbxModuleSourceBundle.php';

$api = dbx();
$method = new ReflectionMethod($api, 'read_config_file');
$fixture = sys_get_temp_dir() . '/dbx-config-' . bin2hex(random_bytes(5)) . '.php';

try {
    file_put_contents($fixture, "<?php\necho 'DARF-NICHT-AUSGEGEBEN-WERDEN';\n\$config = ['name' => 'dbx', 'nested' => ['enabled' => 1]];\n");
    ob_start();
    $loaded = $method->invoke($api, $fixture);
    $output = (string)ob_get_clean();

    if ($loaded !== array('name' => 'dbx', 'nested' => array('enabled' => 1)) || $output !== '') {
        fwrite(STDERR, "FAIL Config-Datei wird nicht isoliert oder gibt Text aus.\n");
        exit(1);
    }

    $source = dbx_test_module_source_bundle(dirname(__DIR__) . '/dbxApi.php');
    if (str_contains($source, 'eval($clean_code)') || !str_contains($source, 'include $__dbx_config_file')) {
        fwrite(STDERR, "FAIL Config-Loader verwendet weiterhin eval oder keinen isolierten Include.\n");
        exit(1);
    }
} finally {
    if (is_file($fixture)) unlink($fixture);
}

echo "OK config.php isoliert per Datei-Include ohne eval und Ausgabe geladen.\n";
