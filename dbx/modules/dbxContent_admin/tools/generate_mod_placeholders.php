<?php
/**
 * Erzeugt Platzhalter-SVGs in dbx/modules/{modul}/tpl/mod/
 * Dateiname: {Modul}_{dbx_run1}.svg oder {Modul}_{dbx_run1}_{dbx_run2}.svg
 *
 * Aufruf: php dbx/modules/dbxContent_admin/tools/generate_mod_placeholders.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$modulesDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;

function mod_class_files($modulesDir, $modul) {
    $base = $modulesDir . $modul . DIRECTORY_SEPARATOR;
    $files = array();
    $main = $base . $modul . '.class.php';
    if (is_file($main)) $files[] = $main;
    $inc = $base . 'include' . DIRECTORY_SEPARATOR;
    if (is_dir($inc)) {
        foreach (glob($inc . '*.class.php') ?: array() as $path) {
            if (is_file($path)) $files[] = $path;
        }
    }
    return $files;
}

function mod_scan_runs($modulesDir, $modul) {
    $run1 = array();
    $run2 = array();
    $uses_run2 = false;
    foreach (mod_class_files($modulesDir, $modul) as $file) {
        $src = @file_get_contents($file);
        if (!is_string($src) || $src === '') continue;
        if (preg_match("/get_modul_var\s*\(\s*['\"]dbx_run2['\"]/", $src)) {
            $uses_run2 = true;
        }
        if (preg_match_all("/case\s+['\"]([^'\"]+)['\"]\s*:/", $src, $matches)) {
            foreach ($matches[1] as $case) {
                $case = trim((string)$case);
                if ($case === '' || $case === 'default') continue;
                $run1[$case] = true;
            }
        }
        if (preg_match_all('/\$(?:run|action|work)\s*===?\s*[\'"]([^\'"]+)[\'"]/', $src, $ifs)) {
            foreach ($ifs[1] as $case) {
                $case = trim((string)$case);
                if ($case === '') continue;
                $run1[$case] = true;
            }
        }
        if (preg_match_all("/get_modul_var\s*\(\s*['\"]dbx_run1['\"][^)]*['\"]([a-zA-Z0-9_]+)['\"]/", $src, $defaults)) {
            foreach ($defaults[1] as $case) {
                $case = trim((string)$case);
                if ($case === '' || $case === 'parameter') continue;
                $run1[$case] = true;
            }
        }
    }
    return array(
        'run1' => array_keys($run1),
        'uses_run2' => $uses_run2,
    );
}

function mod_is_api_action($run1) {
    if (preg_match('/^cms_(tree|page|save|new|delete|move|media|upload|external|remove|edit|set|assign|sort|mod_)/', $run1)) {
        return true;
    }
    return false;
}

function mod_run2_variants($modul, $run1) {
    $map = array(
        'dbxContent' => array(
            'cms' => array('show', 'tree', 'page'),
            'content' => array('edit'),
        ),
        'dbxUser' => array(
            'user' => array('profil', 'avatar', 'avatar_upload', 'edit_profil', 'edit_avatar'),
        ),
        'dbxLogin' => array(
            'register' => array('confirm', 'resend_confirm'),
        ),
        'dbxHelp' => array(
            'dbx' => array('show'),
        ),
    );
    if (!isset($map[$modul][$run1])) return array();
    return $map[$modul][$run1];
}

function mod_specs_for_module($modulesDir, $modul, $scan) {
    $specs = array();
    $uses_run2 = !empty($scan['uses_run2']);
    $run1_list = $scan['run1'];

    if ($uses_run2 && $modul === 'dbxHelp') {
        return array(array('dbx', 'show'));
    }

    $has_run1 = false;
    foreach ($run1_list as $run1) {
        if (mod_is_api_action($run1)) continue;
        $has_run1 = true;
        $specs[] = array($run1, '');
        foreach (mod_run2_variants($modul, $run1) as $run2) {
            $specs[] = array($run1, $run2);
        }
    }

    if (!$has_run1) {
        $specs[] = array('show', '');
    }

    $uniq = array();
    $out = array();
    foreach ($specs as $spec) {
        $key = $spec[0] . '|' . $spec[1];
        if (isset($uniq[$key])) continue;
        $uniq[$key] = true;
        $out[] = $spec;
    }
    return $out;
}

function mod_visual_type($run1, $run2) {
    $key = strtolower($run2 !== '' ? $run2 : $run1);
    if (preg_match('/(form|contact|edit|profil|register|login)/', $key)) return 'form';
    if (preg_match('/(list|tree|flat|overview|folder|files|grid)/', $key)) return 'list';
    if (preg_match('/(menu|load|nav)/', $key)) return 'menu';
    if (preg_match('/(cms|content|show|view|page|dashboard|run)/', $key)) return 'content';
    if (preg_match('/(media|image|upload|avatar)/', $key)) return 'media';
    if (preg_match('/(api|web|json|html)/', $key)) return 'api';
    return 'default';
}

function mod_palette($type) {
    $palettes = array(
        'form' => array('#f8f6f2', '#8a6d3b', '#d8cfc0'),
        'list' => array('#f2f6fa', '#2a5a8a', '#c8d8ea'),
        'menu' => array('#f2faf4', '#2a6a3a', '#c8e8d0'),
        'content' => array('#f4f6fa', '#3a4a7a', '#d0d8ea'),
        'media' => array('#faf4f8', '#7a3a6a', '#e8d0e0'),
        'api' => array('#f6f4fa', '#5a3a8a', '#ddd0ea'),
        'default' => array('#f5f5f5', '#555555', '#dddddd'),
    );
    return $palettes[$type] ?? $palettes['default'];
}

function mod_svg_content($modul, $run1, $run2, $params) {
    $type = mod_visual_type($run1, $run2);
    list($bg, $accent, $border) = mod_palette($type);
    $marker = '[modul=' . $modul . ']' . $params . '[/modul]';
    $title = $modul;
    $paramLine = $params !== '' ? $params : '(Standard-Aufruf)';
    $run2Line = $run2 !== '' ? ('dbx_run2=' . $run2) : '';

    $shapes = '';
    if ($type === 'form') {
        $shapes = '
  <rect x="40" y="50" width="280" height="14" fill="' . $accent . '" rx="3" opacity="0.35"/>
  <rect x="40" y="78" width="280" height="28" fill="#fff" stroke="' . $border . '" rx="4"/>
  <rect x="40" y="118" width="280" height="28" fill="#fff" stroke="' . $border . '" rx="4"/>
  <rect x="40" y="158" width="100" height="26" fill="' . $accent . '" rx="4"/>';
    } elseif ($type === 'list') {
        $shapes = '
  <rect x="40" y="52" width="300" height="22" fill="#fff" stroke="' . $border . '" rx="3"/>
  <rect x="40" y="82" width="300" height="22" fill="#fff" stroke="' . $border . '" rx="3"/>
  <rect x="40" y="112" width="300" height="22" fill="#fff" stroke="' . $border . '" rx="3"/>
  <rect x="40" y="142" width="300" height="22" fill="#fff" stroke="' . $border . '" rx="3"/>';
    } elseif ($type === 'menu') {
        $shapes = '
  <rect x="40" y="60" width="70" height="24" fill="' . $accent . '" rx="4" opacity="0.8"/>
  <rect x="120" y="60" width="70" height="24" fill="' . $accent . '" rx="4" opacity="0.55"/>
  <rect x="200" y="60" width="70" height="24" fill="' . $accent . '" rx="4" opacity="0.35"/>
  <rect x="40" y="110" width="320" height="60" fill="#fff" stroke="' . $border . '" rx="6"/>';
    } elseif ($type === 'media') {
        $shapes = '
  <rect x="60" y="55" width="120" height="90" fill="#fff" stroke="' . $border . '" rx="6"/>
  <circle cx="110" cy="90" r="18" fill="' . $accent . '" opacity="0.4"/>
  <polygon points="180,130 220,70 260,130" fill="' . $accent . '" opacity="0.35"/>';
    } else {
        $shapes = '
  <rect x="40" y="55" width="320" height="110" fill="#fff" stroke="' . $border . '" rx="6"/>
  <rect x="56" y="72" width="180" height="12" fill="' . $accent . '" rx="2" opacity="0.5"/>
  <rect x="56" y="94" width="260" height="10" fill="' . $accent . '" rx="2" opacity="0.25"/>
  <rect x="56" y="112" width="220" height="10" fill="' . $accent . '" rx="2" opacity="0.25"/>';
    }

    $esc = function ($s) {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    };

    return '<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 200" role="img" aria-label="' . $esc($modul . ' ' . $run1) . '">
  <rect width="800" height="200" fill="' . $bg . '" stroke="' . $border . '" stroke-width="2" rx="8"/>
  ' . $shapes . '
  <text x="420" y="58" font-family="Segoe UI,Arial,sans-serif" font-size="22" font-weight="700" fill="' . $accent . '">' . $esc($title) . '</text>
  <text x="420" y="88" font-family="Consolas,monospace" font-size="14" fill="#333">dbx_run1=' . $esc($run1) . '</text>
  ' . ($run2Line !== '' ? '<text x="420" y="112" font-family="Consolas,monospace" font-size="14" fill="#333">' . $esc($run2Line) . '</text>' : '') . '
  <text x="420" y="150" font-family="Consolas,monospace" font-size="11" fill="#666">' . $esc($paramLine) . '</text>
  <text x="400" y="182" text-anchor="middle" font-family="Consolas,monospace" font-size="10" fill="#888">' . $esc($marker) . '</text>
</svg>
';
}

function mod_filename($modul, $run1, $run2) {
    $name = $modul . '_' . $run1;
    if ($run2 !== '') $name .= '_' . $run2;
    return $name . '.svg';
}

function mod_build_params($run1, $run2) {
    $params = array();
    if ($run1 !== '') $params[] = 'dbx_run1=' . rawurlencode($run1);
    if ($run2 !== '') $params[] = 'dbx_run2=' . rawurlencode($run2);
    return implode('&', $params);
}

$created = 0;
$skipped = 0;

foreach (glob($modulesDir . '*', GLOB_ONLYDIR) ?: array() as $path) {
    $modul = basename(str_replace('\\', '/', $path));
    if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $modul)) continue;
    if (!is_file($path . DIRECTORY_SEPARATOR . $modul . '.class.php')) continue;

    $scan = mod_scan_runs($modulesDir, $modul);
    $specs = mod_specs_for_module($modulesDir, $modul, $scan);
    $outDir = $path . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'mod' . DIRECTORY_SEPARATOR;
    if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
        fwrite(STDERR, "Cannot create dir: $outDir\n");
        continue;
    }

    foreach ($specs as $spec) {
        list($run1, $run2) = $spec;
        $file = $outDir . mod_filename($modul, $run1, $run2);
        if (is_file($file)) {
            $skipped++;
            continue;
        }
        $params = mod_build_params($run1, $run2);
        $svg = mod_svg_content($modul, $run1, $run2, $params);
        file_put_contents($file, $svg);
        $created++;
        echo "created: $modul / " . basename($file) . "\n";
    }
}

echo "Done. Created: $created, skipped (exists): $skipped\n";
