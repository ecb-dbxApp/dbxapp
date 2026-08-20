<?php
declare(strict_types=1);

/** Vertrag: Jedes Design liefert seine CSS-Systembasis ohne Fremddesign aus. */

$root = dirname(__DIR__, 3);
$design_root = $root . '/dbx/design';
$failures = array();
$system_files = array(
    'c-ace.css', 'c-admin.css', 'c-base.css', 'c-content-frame.css',
    'c-content.css', 'c-icons.css', 'c-icons_editor.css', 'c-openWin.css',
    'c-process.css', 'c-report.css', 'c-schema-mapping.css', 'c-viewport.css',
    'c-tooltip.css', 'colors.css', 'glass-3d.css', 'm-menu.css', 'theme.css',
);

if (is_dir($design_root . '/shared')) {
    $failures[] = 'Der alte designübergreifende CSS-Ordner existiert noch.';
}

foreach (array('dbxapp', 'flowers', 'steal') as $design) {
    $css_dir = $design_root . '/' . $design . '/css';
    $sources = glob($css_dir . '/*.css') ?: array();
    if (count($sources) < 20) {
        $failures[] = $design . ': CSS-Basis ist unvollständig.';
    }

    foreach ($sources as $file) {
        $source = (string)file_get_contents($file);
        if (str_contains($source, 'shared/css') || str_contains($source, '../shared/')) {
            $failures[] = $design . '/' . basename($file) . ': designübergreifender Import.';
        }

        preg_match_all('~url\(["\']?([^"\')]+)~', $source, $urls);
        foreach ($urls[1] ?? array() as $url) {
            $url = trim((string)$url);
            if ($url === '' || preg_match('~^(?:data:|https?:|#|var\()~i', $url)) continue;
            $relative = preg_split('~[?#]~', $url, 2)[0] ?? '';
            if ($relative !== '' && !is_file(dirname($file) . '/' . $relative)) {
                $failures[] = $design . '/' . basename($file) . ': CSS-Ressource fehlt: ' . $url;
            }
        }
    }

    $required_files = $design === 'flowers'
        ? array('base.css', 'components.css', 'c-cms.css', 'c-content.css', 'c-form.css', 'c-grid.css', 'c-menu.css', 'c-tooltip.css', 'theme.css')
        : $system_files;
    foreach ($required_files as $name) {
        $file = $css_dir . '/' . $name;
        $minimum_bytes = $design === 'flowers' ? 20 : 100;
        if (!is_file($file) || filesize($file) < $minimum_bytes) {
            $failures[] = $design . ': eigenständige Systemdatei fehlt oder ist leer: ' . $name;
        }
    }
}

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", array_values(array_unique($failures))) . "\n");
    exit(1);
}

echo "OK vier eigenständige Design-CSS-Basen ohne Shared-Abhängigkeit.\n";
