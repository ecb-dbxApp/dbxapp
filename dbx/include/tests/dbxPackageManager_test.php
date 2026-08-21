<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/dbxPackageManager.class.php';

function package_manager_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function package_manager_remove(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

$root = sys_get_temp_dir() . '/dbx-package-manager-' . bin2hex(random_bytes(5));
mkdir($root . '/dbx/modules/Demo', 0770, true);
mkdir($root . '/dbx/modules/dbxContent/include', 0770, true);
mkdir($root . '/dbx/marketplace', 0770, true);
file_put_contents(
    $root . '/dbx/modules/dbxContent/include/dbxContentPageCache.class.php',
    "<?php namespace dbx\\dbxContent; final class dbxContentPageCache { public static function invalidate_all(): array { file_put_contents(dirname(__DIR__, 4) . '/files/cache-invalidated', '1', FILE_APPEND); return array(); } }\n"
);
file_put_contents($root . '/VERSION', "4.3.0\n");
file_put_contents($root . '/dbx/modules/Demo/demo.php', "<?php return 'old';\n");
$base = array(
    'schema' => 1,
    'id' => 'demo/module/Demo',
    'type' => 'module',
    'name' => 'Demo',
    'title' => 'Demo',
    'version' => '4.3.0',
    'vendor' => array('id' => 'demo', 'name' => 'Demo Vendor'),
    'managed' => true,
    'license' => 'free',
    'requires' => array('kernel' => '^4.3.0', 'php' => '>=8.2.0', 'extensions' => array(), 'packages' => array()),
    'permissions' => array(),
    'files' => array(),
);
file_put_contents($root . '/dbx/modules/Demo/dbx.package.json', json_encode($base, JSON_PRETTY_PRINT));
file_put_contents($root . '/dbx/marketplace/catalog.json', json_encode(array(
    'schema' => 1, 'channel' => 'stable', 'sequence' => 1,
    'generated_at' => gmdate('c'), 'expires_at' => gmdate('c', time() + 3600), 'packages' => array(),
), JSON_PRETTY_PRINT));
file_put_contents($root . '/dbx/marketplace/trust.json', '{"schema":1,"keys":{}}');

try {
    $manager = new dbxPackageManager($root);
    package_manager_assert(count($manager->adopt_bundled_packages()) === 1, 'Gebundeltes Paket wurde nicht registriert.');
    file_put_contents($root . '/dbx/modules/Demo/demo.php', "<?php return 'tampered';\n");
    package_manager_assert($manager->adopt_bundled_packages() === array(), 'Unveraenderter Release-Beleg wurde kostspielig neu erzeugt.');
    $drifted = $manager->inventory();
    package_manager_assert(
        !empty($drifted['demo/module/Demo']['drift']),
        'Erneute Paketregistrierung darf lokale Aenderungen nicht maskieren.'
    );
    file_put_contents($root . '/dbx/modules/Demo/demo.php', "<?php return 'old';\n");
    $new_source = "<?php return 'new';\n";
    $manifest = $base;
    $manifest['version'] = '4.3.1';
    $manifest['files'] = array('dbx/modules/Demo/demo.php' => hash('sha256', $new_source));
    $work = $root . '/files/update/components';
    $stage = $work . '/staging/test/packages/demo';
    mkdir($stage . '/dbx/modules/Demo', 0770, true);
    file_put_contents($stage . '/dbx/modules/Demo/demo.php', $new_source);
    if (!is_dir($work)) {
        mkdir($work, 0770, true);
    }
    file_put_contents($work . '/staged.json', json_encode(array(
        'schema' => 1,
        'transaction' => 'test-transaction',
        'prepared_at' => gmdate('c'),
        'stage_root' => $work . '/staging/test',
        'packages' => array('demo/module/Demo' => array(
            'catalog' => $manifest,
            'manifest' => $manifest,
            'archive' => '',
            'staging' => $stage,
        )),
    ), JSON_PRETTY_PRINT));
    $installed = $manager->install_prepared();
    package_manager_assert(count($installed['packages']) === 1, 'Paket wurde nicht installiert.');
    package_manager_assert(file_get_contents($root . '/dbx/modules/Demo/demo.php') === $new_source, 'Neue Paketdatei fehlt.');
    $installed_manifest = json_decode((string)file_get_contents($root . '/dbx/modules/Demo/dbx.package.json'), true);
    package_manager_assert(($installed_manifest['version'] ?? '') === '4.3.1', 'Installiertes Manifest wurde nicht aktualisiert.');
    package_manager_assert(
        file_get_contents($root . '/files/cache-invalidated') === '1',
        'Installation invalidiert den Seiten-Cache nicht.'
    );
    $manager->rollback();
    package_manager_assert(file_get_contents($root . '/dbx/modules/Demo/demo.php') === "<?php return 'old';\n", 'Rollback stellt die alte Datei nicht wieder her.');
    $rolled_manifest = json_decode((string)file_get_contents($root . '/dbx/modules/Demo/dbx.package.json'), true);
    package_manager_assert(($rolled_manifest['version'] ?? '') === '4.3.0', 'Rollback stellt das alte Manifest nicht wieder her.');
    package_manager_assert(
        file_get_contents($root . '/files/cache-invalidated') === '11',
        'Rollback invalidiert den Seiten-Cache nicht.'
    );
} finally {
    package_manager_remove($root);
}

echo "dbxPackageManager: transaktionale Installation und Rollback erfolgreich.\n";
