<?php

declare(strict_types=1);

$dbx_root = dirname(__DIR__, 2);
$project_root = dirname($dbx_root);
$manifest_path = $dbx_root . '/third-party-sources.json';
$manifest = is_file($manifest_path)
    ? json_decode((string)file_get_contents($manifest_path), true)
    : array();
$foreign_paths = array();
foreach ((array)($manifest['sources'] ?? array()) as $entry) {
    $path = str_replace('\\', '/', (string)($entry['path'] ?? ''));
    if ($path !== '') {
        $foreign_paths[$path] = true;
    }
}

$skip_path = static function (string $path) use ($project_root, $foreign_paths): bool {
    $normalized = str_replace('\\', '/', $path);
    $relative = ltrim(str_replace(str_replace('\\', '/', $project_root), '', $normalized), '/');
    return isset($foreign_paths[$relative])
        || (bool)preg_match('~/(?:vendor|work|tests?|db|dd|fd|_backup|backups|temp)/~i', '/' . $relative);
};

$metrics = array(
    'php_files' => 0,
    'php_lines' => 0,
    'methods' => 0,
    'largest_file' => '',
    'largest_file_lines' => 0,
);
$files = array();
foreach (array($dbx_root . '/include', $dbx_root . '/modules') as $root) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') continue;
        $path = str_replace('\\', '/', $file->getPathname());
        if ($skip_path($path)) continue;
        $source = (string)file_get_contents($path);
        $lines = substr_count($source, "\n") + 1;
        $relative = ltrim(str_replace(str_replace('\\', '/', $project_root), '', $path), '/');
        $files[$relative] = array(
            'lines' => $lines,
            'methods' => preg_match_all('/\bfunction\s+[A-Za-z_][A-Za-z0-9_]*\s*\(/', $source),
        );
        $metrics['php_files']++;
        $metrics['php_lines'] += $lines;
        $metrics['methods'] += $files[$relative]['methods'];
        if ($lines > $metrics['largest_file_lines']) {
            $metrics['largest_file'] = $relative;
            $metrics['largest_file_lines'] = $lines;
        }
    }
}

$limits = array(
    'dbx/include/dbxForm.class.php' => 1510,
    'dbx/include/dbxDB.class.php' => 1750,
    'dbx/include/dbxDD.class.php' => 220,
    'dbx/include/dbxReport.class.php' => 1420,
    'dbx/include/dbxApi.php' => 1835,
    'dbx/include/dbxWebApp.class.php' => 1170,
);
$errors = array();
foreach ($limits as $relative => $limit) {
    $lines = (int)($files[$relative]['lines'] ?? 0);
    if ($lines <= 0) {
        $errors[] = 'Kernquelle fehlt im Inventar: ' . $relative;
    } elseif ($lines > $limit) {
        $errors[] = $relative . ' ist von maximal ' . $limit . ' auf ' . $lines . ' Zeilen gewachsen.';
    }
}
if ($metrics['largest_file_lines'] > 2300) {
    $errors[] = 'Groesste eigene PHP-Datei ueberschreitet 2300 Zeilen: '
        . $metrics['largest_file'] . ' (' . $metrics['largest_file_lines'] . ').';
}

if ($errors !== array()) {
    fwrite(STDERR, "Source-Komplexitaet verschlechtert:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo 'OK Source-Komplexitaet: ' . json_encode(
    array('summary' => $metrics, 'guarded_files' => $limits),
    JSON_UNESCAPED_SLASHES
) . "\n";
