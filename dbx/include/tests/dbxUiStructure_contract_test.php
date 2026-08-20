<?php
declare(strict_types=1);

/**
 * Vertrag: Systemkomponenten teilen eine semantische Panel-/Bar-Struktur.
 * Designs bleiben in ihrer CSS-Ausgestaltung vollstaendig eigenstaendig.
 */

$base = dirname(__DIR__, 2);
require_once __DIR__ . '/dbxModuleSourceBundle.php';
$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};
$read = static function (string $file): string {
    return is_file($file) ? (string)file_get_contents($file) : '';
};

$form = dbx_test_module_source_bundle($base . '/include/dbxForm.class.php');
$report = dbx_test_module_source_bundle($base . '/include/dbxReport.class.php');
$bar = $read($base . '/modules/dbx/tpl/htm/bar.htm');
$title = $read($base . '/modules/dbx/tpl/htm/bar-title-module.htm');

$assert(
    str_contains($form, "'bar_class'               => 'dbx-bar--module'")
        && str_contains($form, "'bar_title_class'         => 'dbx-bar-title'")
        && str_contains($form, "'bar_actions_class'       => 'dbx-bar-actions'"),
    'dbxForm liefert nicht den kanonischen Modul-Bar-Vertrag.'
);
$assert(
    str_contains($report, 'class dbxReport extends dbxForm'),
    'dbxReport verwendet die zentrale dbxForm-Struktur nicht.'
);
$assert(
    str_contains($bar, 'class="dbx-bar {bar_class}"')
        && str_contains($title, 'class="dbx-bar-copy"')
        && str_contains($title, 'class="dbx-bar-heading"')
        && str_contains($title, 'class="dbx-bar-subtitle"'),
    'Die zentralen dbxTPL-Bar-Templates sind strukturell unvollstaendig.'
);

foreach (array('dbxapp', 'flowers', 'steal') as $design) {
    $theme = '';
    foreach (glob($base . '/design/' . $design . '/css/*.css') ?: array() as $css_file) {
        $theme .= "\n" . $read($css_file);
    }
    $assert(
        str_contains($theme, '.dbx-panel')
            && str_contains($theme, '.dbx-panel-body')
            && str_contains($theme, '.dbx-bar')
            && str_contains($theme, '.dbx-bar-title')
            && str_contains($theme, '.dbx-bar-heading')
            && str_contains($theme, '.dbx-bar-actions'),
        'Das eigenstaendige Design ' . $design . ' unterstuetzt den Strukturvertrag nicht.'
    );
}

$extensions = '~\.(?:php|js|css|html?|tpl)$~i';
$forbidden = array(
    'dbx-' . 'module-bar',
    'dbx-' . 'editor-bar',
    'dbx-' . 'toolbar',
);
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile() || !preg_match($extensions, $file->getFilename())) continue;

    $path = str_replace('\\', '/', $file->getPathname());
    if (
        str_contains($path, '/vendor/')
        || str_contains($path, '/add_ons/')
        || str_contains($path, '/generated/')
        || str_contains($path, '/_backup/')
        || str_contains($path, '/work/')
        || str_contains($path, '/tests/')
    ) {
        continue;
    }

    $source = (string)file_get_contents($file->getPathname());
    foreach ($forbidden as $legacy) {
        if (str_contains($source, $legacy)) {
            $failures[] = 'Veraltete Strukturklasse ' . $legacy . ': ' . $path;
        }
    }

    if (preg_match_all('~(?<![A-Za-z0-9_$])class\s*=\s*(["\'])(.*?)\1~is', $source, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $tokens = preg_split('/\s+/', trim((string)$match[2])) ?: array();
            $literal_tokens = array_values(array_filter($tokens, static fn(string $token): bool => $token !== '' && !str_contains($token, '{')));
            $is_static_markup = preg_match('~\.(?:html?|tpl)$~i', $path) === 1;
            if ($is_static_markup && count($literal_tokens) !== count(array_unique($literal_tokens))) {
                $failures[] = 'Doppelte Klasse in einem class-Attribut: ' . $path;
                break;
            }
            foreach (array('dbx-bar--module', 'dbx-bar--editor', 'dbx-bar--toolbar') as $modifier) {
                if (in_array($modifier, $literal_tokens, true) && !in_array('dbx-bar', $literal_tokens, true)) {
                    $failures[] = 'Bar-Variante ohne Basisklasse ' . $modifier . ': ' . $path;
                    break 2;
                }
            }
        }
    }

    if (str_ends_with(strtolower($path), '.css')) {
        $lines = preg_split('/\R/', $source) ?: array();
        for ($i = 1, $count = count($lines); $i < $count; $i++) {
            $previous = trim((string)$lines[$i - 1]);
            $current = trim((string)$lines[$i]);
            if ($current !== '' && str_ends_with($current, ',') && $current === $previous && str_contains($current, '.dbx-')) {
                $failures[] = 'Direkt doppelter dbx-Selektor: ' . $path;
                break;
            }
        }
    }
}

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", array_values(array_unique($failures))) . "\n");
    exit(1);
}

echo "OK canonical dbx panel/bar structure is shared without coupling designs.\n";
