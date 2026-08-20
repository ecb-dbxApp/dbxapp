<?php

/**
 * Konservative Erreichbarkeitsanalyse fuer dbxapp-Modultemplates.
 *
 * Der Audit arbeitet absichtlich mit einer Ueberdeckung: Eine Template-Familie
 * gilt bereits dann als verwendet, wenn ihr Name als Literal in einer
 * Produktdatei vorkommt. Dadurch koennen False Positives entstehen, aber keine
 * dynamisch konfigurierte Datei wird allein wegen einer zu engen PHP-Analyse
 * zur Loeschung vorgeschlagen.
 *
 * Aufruf:
 *   php tools/audit-templates.php
 *   php tools/audit-templates.php --json
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$module_root = $root . DIRECTORY_SEPARATOR . 'dbx' . DIRECTORY_SEPARATOR . 'modules';

if (!is_dir($module_root)) {
    fwrite(STDERR, "Modulverzeichnis fehlt: {$module_root}\n");
    exit(2);
}

$source_extensions = array(
    'php' => true,
    'htm' => true,
    'html' => true,
    'js' => true,
    'json' => true,
    'sql' => true,
    'md' => true,
    'txt' => true,
    'xml' => true,
    'yml' => true,
    'yaml' => true,
);

/** @var array<string, array<string, mixed>> $families */
$families = array();
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($module_root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'htm') {
        continue;
    }

    $path = str_replace('\\', '/', $file->getPathname());
    if (!preg_match('~/modules/([^/]+)/tpl/(htm|help)/([^/]+)\.htm$~i', $path, $match)) {
        continue;
    }

    $module = $match[1];
    $type = strtolower($match[2]);
    $file_stem = $match[3];
    $stem = preg_replace('/_(?:de|en|es)$/i', '', $file_stem) ?: $file_stem;
    $key = strtolower($module . '|' . $type . '|' . $stem);

    if (!isset($families[$key])) {
        $families[$key] = array(
            'module' => $module,
            'type' => $type,
            'template' => $stem,
            'files' => array(),
            'references' => array(),
            'protected_reason' => '',
        );
    }
    $families[$key]['files'][] = substr($path, strlen(str_replace('\\', '/', $root)) + 1);
}

// Hilfe wird ueber Modul/Route/Fallback dynamisch aufgeloest. Solange der
// Help-Router existiert, ist eine rein statische Einzeldatei-Loeschung unsicher.
foreach ($families as &$family) {
    if ($family['type'] === 'help') {
        $family['protected_reason'] = 'dynamischer Help-Router';
    } elseif (str_starts_with((string)$family['module'], '-')) {
        $family['protected_reason'] = 'deaktivierte Paketquelle';
    } elseif ($family['module'] === 'dbxMenu') {
        $family['protected_reason'] = 'kundenspezifische Menuekonfiguration';
    } elseif ($family['module'] === 'dbxContent'
        && preg_match('/^(?:c-|i-|media-)/i', (string)$family['template']) === 1
    ) {
        $family['protected_reason'] = 'dynamischer CMS-Templatekatalog';
    }
}
unset($family);

// Alle Produktquellen genau einmal lesen. Vendor, Laufzeitdaten und Git werden
// fuer Nutzungsentscheidungen nicht herangezogen.
$scan_roots = array($root . '/dbx', $root . '/tools', $root . '/index.php');
$excluded_parts = array('/vendor/', '/files/', '/.git/', '/include/tests/fixtures/');

foreach ($scan_roots as $scan_root) {
    if (is_file($scan_root)) {
        $files = array(new SplFileInfo($scan_root));
    } elseif (is_dir($scan_root)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($scan_root, FilesystemIterator::SKIP_DOTS)
        );
    } else {
        continue;
    }

    foreach ($files as $source_file) {
        if (!$source_file->isFile()) {
            continue;
        }
        $extension = strtolower($source_file->getExtension());
        if (!isset($source_extensions[$extension])) {
            continue;
        }

        $source_path = str_replace('\\', '/', $source_file->getPathname());
        foreach ($excluded_parts as $excluded_part) {
            if (stripos($source_path, $excluded_part) !== false) {
                continue 2;
            }
        }

        $content = @file_get_contents($source_file->getPathname());
        if (!is_string($content) || $content === '') {
            continue;
        }

        $relative = substr($source_path, strlen(str_replace('\\', '/', $root)) + 1);
        foreach ($families as &$family) {
            if ($family['type'] !== 'htm') {
                continue;
            }

            $module = preg_quote((string)$family['module'], '~');
            $template = preg_quote((string)$family['template'], '~');
            $qualified = '~(?<![A-Za-z0-9_-])(?:' . $module . '|modul)\\|' . $template . '(?![A-Za-z0-9_-])~i';
            $literal = '~([\'\"])(?:' . $template . ')\\1~i';
            $concatenated_literal = '~([\'\"])\\|' . $template . '\\1~i';
            $include = '~\\[tpl=(?:' . $module . '|modul)\\|' . $template . '(?:\\|htm)?\\]~i';

            if (preg_match($qualified, $content)
                || preg_match($literal, $content)
                || preg_match($concatenated_literal, $content)
                || preg_match($include, $content)) {
                // Die eigene Familie darf sich nicht selbst als Nutzung
                // legitimieren. Includes aus anderen Templates zaehlen.
                $own_prefix = 'dbx/modules/' . $family['module'] . '/tpl/htm/' . $family['template'];
                if (stripos($relative, $own_prefix) !== 0) {
                    $family['references'][$relative] = true;
                }
            }
        }
        unset($family);
    }
}

$unused = array();
$used = array();
$protected = array();
foreach ($families as $family) {
    $family['references'] = array_keys($family['references']);
    sort($family['files']);
    sort($family['references']);

    if ($family['protected_reason'] !== '') {
        $protected[] = $family;
    } elseif ($family['references']) {
        $used[] = $family;
    } else {
        $unused[] = $family;
    }
}

$sort = static function (array $left, array $right): int {
    return strcasecmp(
        $left['module'] . '|' . $left['template'],
        $right['module'] . '|' . $right['template']
    );
};
usort($unused, $sort);
usort($used, $sort);
usort($protected, $sort);

$result = array(
    'families_total' => count($families),
    'families_used' => count($used),
    'families_protected' => count($protected),
    'families_unused_candidates' => count($unused),
    'files_unused_candidates' => array_sum(array_map(
        static fn(array $family): int => count($family['files']),
        $unused
    )),
    'unused_candidates' => $unused,
);

if (in_array('--json', $argv, true)) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

echo 'Template-Familien: ' . $result['families_total'] . PHP_EOL;
echo 'Statisch verwendet: ' . $result['families_used'] . PHP_EOL;
echo 'Konventionsbedingt geschuetzt: ' . $result['families_protected'] . PHP_EOL;
echo 'Ungenutzte Kandidaten: ' . $result['families_unused_candidates']
    . ' Familien / ' . $result['files_unused_candidates'] . ' Dateien' . PHP_EOL;
foreach ($unused as $family) {
    echo '- ' . $family['module'] . '|' . $family['template']
        . ' (' . count($family['files']) . ')' . PHP_EOL;
}
