<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/include/dbxUpdateService.class.php';

use dbx\dbxAdmin\dbxUpdateService;

final class UpdateDatabaseAdapterStub
{
    public array $calls = array();
    public bool $failApply = false;

    public function prepare(string $root, array $manifest, string $backup): array
    {
        $this->calls[] = 'prepare';
        return array('pending' => array('test-migration'), 'backups' => array());
    }

    public function apply(array $state): array
    {
        $this->calls[] = 'apply';
        if ($this->failApply) {
            throw new RuntimeException('erwarteter DB-Fehler');
        }
        return array('applied' => $state['pending']);
    }

    public function rollback(array $state): array
    {
        $this->calls[] = 'rollback';
        return array('rolled_back' => $state['pending']);
    }
}

function update_db_write(string $file, string $content): void
{
    if (!is_dir(dirname($file))) {
        mkdir(dirname($file), 0775, true);
    }
    file_put_contents($file, $content);
}

function update_db_remove(string $root): void
{
    if (!is_dir($root)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($root);
}

function update_db_stage(
    string $root,
    string $version,
    string $newIndex
): array {
    update_db_write($root . '/VERSION', "4.0.1\n");
    update_db_write($root . '/index.php', "<?php echo 'old';\n");
    update_db_write($root . '/dbx/include/dbxApi.php', "<?php // old api\n");
    $oldInventory = array(
        'schema' => 1,
        'product' => 'dbxapp',
        'version' => '4.0.1',
        'files' => array(
            'VERSION' => hash('sha256', "4.0.1\n"),
            'index.php' => hash('sha256', "<?php echo 'old';\n"),
            'dbx/include/dbxApi.php' => hash('sha256', "<?php // old api\n"),
            '.dbx-release-files.json' => null,
        ),
    );
    update_db_write(
        $root . '/.dbx-release-files.json',
        json_encode($oldInventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    $work = $root . '/files/update';
    $zipFile = $work . '/downloads/dbxapp-' . $version . '.zip';
    if (!is_dir(dirname($zipFile))) {
        mkdir(dirname($zipFile), 0775, true);
    }
    $contents = array(
        'VERSION' => $version . "\n",
        'index.php' => $newIndex,
        'dbx/include/dbxApi.php' => "<?php // new api\n",
    );
    $files = array();
    foreach ($contents as $path => $content) {
        $files[$path] = hash('sha256', $content);
    }
    $files['.dbx-release-files.json'] = null;
    $inventory = json_encode(array(
        'schema' => 1,
        'product' => 'dbxapp',
        'version' => $version,
        'files' => $files,
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

    $zip = new ZipArchive();
    $zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($contents as $path => $content) {
        $zip->addFromString($path, $content);
    }
    $zip->addFromString('.dbx-release-files.json', $inventory);
    $zip->close();

    $manifest = array(
        'schema' => 1,
        'product' => 'dbxapp',
        'channel' => 'stable',
        'version' => $version,
        'release_url' => 'https://github.com/ecb-dbxApp/dbxapp/releases/tag/v' . $version,
        'zip_url' => 'https://github.com/ecb-dbxApp/dbxapp/releases/download/v'
            . $version . '/dbxapp-' . $version . '.zip',
        'sha256' => hash_file('sha256', $zipFile),
        'requires' => array(
            'php' => '>=8.2',
            'extensions' => array('json', 'pdo', 'zip'),
        ),
    );

    $service = new dbxUpdateService($root);
    $package = $service->inspectPackage($zipFile, $manifest);
    $staging = $work . '/staging/' . $version;
    mkdir($staging, 0775, true);
    $zip = new ZipArchive();
    $zip->open($zipFile);
    $zip->extractTo($staging);
    $zip->close();
    update_db_write($work . '/staged.json', json_encode(array(
        'schema' => 1,
        'staged_at' => gmdate('c'),
        'zip_file' => $zipFile,
        'staging_directory' => $staging,
        'manifest' => $manifest,
        'files' => $package['files'],
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return array($manifest, $staging);
}

function update_db_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = sys_get_temp_dir() . '/dbx-update-db-adapter-' . bin2hex(random_bytes(5));
try {
    [$manifest] = update_db_stage($root, '4.0.2', "<?php echo 'new';\n");
    $adapter = new UpdateDatabaseAdapterStub();
    $service = new dbxUpdateService($root, '', 21600, $adapter);
    $service->install();
    update_db_assert(
        $adapter->calls === array('prepare', 'apply'),
        'DB-Adapter wurde bei erfolgreicher Installation nicht korrekt aufgerufen.'
    );
    $service->rollback();
    update_db_assert(
        $adapter->calls === array('prepare', 'apply', 'rollback')
            && trim((string)file_get_contents($root . '/VERSION')) === '4.0.1',
        'Expliziter Rollback hat DB und Dateien nicht gemeinsam behandelt.'
    );
} finally {
    update_db_remove($root);
}

$root = sys_get_temp_dir() . '/dbx-update-db-failure-' . bin2hex(random_bytes(5));
try {
    update_db_stage($root, '4.0.2', "<?php echo 'new';\n");
    $adapter = new UpdateDatabaseAdapterStub();
    $adapter->failApply = true;
    $service = new dbxUpdateService($root, '', 21600, $adapter);
    try {
        $service->install();
        throw new RuntimeException('Erwarteter DB-Migrationsfehler blieb aus.');
    } catch (RuntimeException $exception) {
        update_db_assert(
            str_contains($exception->getMessage(), 'zurückgerollt'),
            'DB-Migrationsfehler meldet keinen koordinierten Rollback.'
        );
    }
    update_db_assert(
        $adapter->calls === array('prepare', 'apply', 'rollback')
            && trim((string)file_get_contents($root . '/VERSION')) === '4.0.1'
            && str_contains((string)file_get_contents($root . '/index.php'), 'old'),
        'Fehlerpfad hat DB und Dateien nicht vollständig zurückgerollt.'
    );
} finally {
    update_db_remove($root);
}

echo "OK updater coordinates database prepare, apply and rollback\n";
