<?php

declare(strict_types=1);

$dbx_root = dirname(__DIR__, 2);
$module_root = $dbx_root . '/modules';
$errors = array();
$metrics = array(
    'direct_form_report_state' => 0,
    'direct_module_session' => 0,
    'multi_class_files' => 0,
    'extra_classes' => 0,
    'class_files' => 0,
    'class_files_without_strict_types' => 0,
    'class_filename_mismatches' => 0,
    'module_namespace_mismatches' => 0,
);

$skip_path = static function (string $path): bool {
    $path = str_replace('\\', '/', $path);
    return (bool)preg_match('~/(?:vendor|work|tests?|db|dd|fd|_backup|backups|temp)/~i', $path);
};

$php_files = array();
foreach (array($dbx_root . '/include', $module_root) as $root) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') continue;
        $path = str_replace('\\', '/', $file->getPathname());
        if ($skip_path($path)) continue;
        $php_files[] = $path;
    }
}

foreach ($php_files as $path) {
    $source = (string)file_get_contents($path);
    if (str_starts_with($path, str_replace('\\', '/', $module_root) . '/')) {
        $metrics['direct_form_report_state'] += preg_match_all(
            '/->\_(?:tpl|mode|action|rid|dd|fd|create_row|table_tpls|messages|post|data)\b/',
            $source
        );
        $metrics['direct_module_session'] += preg_match_all('/\$_SESSION\s*\[/', $source);
    }

    if (!str_ends_with($path, '.class.php')) continue;
    $metrics['class_files']++;
    if (!preg_match('/declare\s*\(\s*strict_types\s*=\s*1\s*\)/', $source)) {
        $metrics['class_files_without_strict_types']++;
    }
    $classes = 0;
    $primary_class = '';
    $tokens = token_get_all($source);
    foreach ($tokens as $index => $token) {
        if (!is_array($token) || $token[0] !== T_CLASS) continue;
        for ($next = $index + 1, $count = count($tokens); $next < $count; $next++) {
            $candidate = $tokens[$next];
            if (is_array($candidate) && in_array($candidate[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
                continue;
            }
            if (is_array($candidate) && $candidate[0] === T_STRING) {
                $classes++;
                if ($primary_class === '') $primary_class = $candidate[1];
            }
            break;
        }
    }
    if ($classes > 1) {
        $metrics['multi_class_files']++;
        $metrics['extra_classes'] += $classes - 1;
    }
    $file_class = substr(basename($path), 0, -strlen('.class.php'));
    if ($primary_class !== '' && strcasecmp($primary_class, $file_class) !== 0) {
        $metrics['class_filename_mismatches']++;
    }
    $normalized_module_root = str_replace('\\', '/', $module_root) . '/';
    if (str_starts_with($path, $normalized_module_root)
        && !str_starts_with($path, $normalized_module_root . 'myX/')
        && !str_ends_with($path, '/dbx/include/dbxPerformanceTimer.class.php')) {
        $relative = substr($path, strlen($normalized_module_root));
        $module = strtok($relative, '/');
        $expected_namespace = 'dbx\\' . $module;
        $namespace = preg_match('/\bnamespace\s+([^;]+);/', $source, $match)
            ? trim($match[1])
            : '';
        if ($namespace !== $expected_namespace) {
            $metrics['module_namespace_mismatches']++;
        }
    }
}

$maximum = array(
    'direct_form_report_state' => 0,
    'direct_module_session' => 0,
    'multi_class_files' => 0,
    'extra_classes' => 0,
    'class_files_without_strict_types' => 180,
    'class_filename_mismatches' => 0,
    'module_namespace_mismatches' => 0,
);
foreach ($maximum as $name => $limit) {
    if ($metrics[$name] > $limit) {
        $errors[] = $name . ' ist von ' . $limit . ' auf ' . $metrics[$name] . ' gestiegen.';
    }
}

$large_js_limits = array(
    'modules/dbxContent_admin/js/cms.js' => 5040,
    'modules/dbxContent_admin/js/cms-marker.js' => 240,
    'modules/dbxContent_admin/js/cms-context.js' => 830,
    'modules/dbxContent_admin/js/cms-components.js' => 420,
    'modules/dbxContent_admin/js/cms-editor.js' => 830,
    'js/lib/grid.js' => 1840,
    'js/lib/grid-state.js' => 680,
    'js/lib/grid-export.js' => 190,
    'js/lib/grid-transport.js' => 160,
    'js/lib/grid-columns.js' => 690,
    'js/lib/grid-ui.js' => 620,
    'js/lib/core.js' => 2942,
    'js/lib/runtime.js' => 500,
    'js/lib/scheduler.js' => 250,
);
foreach ($large_js_limits as $relative => $limit) {
    $path = $dbx_root . '/' . $relative;
    $lines = is_file($path) ? count(file($path)) : 0;
    if ($lines > $limit) {
        $errors[] = $relative . ' ist von ' . $limit . ' auf ' . $lines . ' Zeilen gewachsen.';
    }
}

$foreign_manifest = $dbx_root . '/third-party-sources.json';
$foreign = is_file($foreign_manifest)
    ? json_decode((string)file_get_contents($foreign_manifest), true)
    : null;
if (!is_array($foreign) || !is_array($foreign['sources'] ?? null)) {
    $errors[] = 'Maschinenlesbares Fremdcode-Inventar fehlt oder ist ungueltig.';
} else {
    foreach ($foreign['sources'] as $entry) {
        foreach (array('path', 'name', 'upstream', 'version', 'license', 'mode') as $key) {
            if (trim((string)($entry[$key] ?? '')) === '') {
                $errors[] = 'Fremdcode-Eintrag ohne ' . $key . '.';
            }
        }
    }
}

if ($errors !== array()) {
    fwrite(STDERR, "Source-Architektur verschlechtert:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo 'OK Source-Architektur: ' . json_encode($metrics, JSON_UNESCAPED_SLASHES) . "\n";
