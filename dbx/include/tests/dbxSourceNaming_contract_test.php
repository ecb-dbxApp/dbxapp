<?php
declare(strict_types=1);

$project_root = dirname(__DIR__, 3);
$roots = array($project_root . '/dbx/include', $project_root . '/dbx/modules', $project_root . '/dbx/js', $project_root . '/tools');
$misspellings = '/(?:haeder|tabulurator|seperator|validatior|dync_db_to_dd|multible|succeass|reponsive|imput|\btabel(?=_|\b)|_tabel(?:_|\b))/i';
$errors = array();
$camel_case_methods = 0;
$camel_case_variables = 0;
$camel_case_properties = 0;
$camel_case_property_entries = array();
$external_property_names = array_fill_keys(array(
    'numFiles', // ZipArchive
    'CharSet', 'ErrorInfo', 'AltBody', // PHPMailer
    'childNodes', 'nodeValue', 'tagName', 'parentNode', 'nodeType', 'nodeName', 'textContent', // DOM
), true);
$manifest = json_decode((string)file_get_contents($project_root . '/dbx/third-party-sources.json'), true);
$foreign = array();
foreach ((array)($manifest['sources'] ?? array()) as $entry) {
    $foreign[str_replace('\\', '/', (string)($entry['path'] ?? ''))] = true;
}

foreach ($roots as $root) {
    if (!is_dir($root)) {
        continue;
    }
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        $path = str_replace('\\', '/', $file->getPathname());
        $relative = ltrim(str_replace(str_replace('\\', '/', $project_root), '', $path), '/');
        if (!$file->isFile()
            || !in_array(strtolower($file->getExtension()), array('php', 'js', 'css', 'htm', 'html', 'json', 'md'), true)
            || isset($foreign[$relative])
            || preg_match('~/(?:vendor|add_ons|myX|tests?|work|temp|_backup|backups)/~i', $path)
        ) {
            continue;
        }
        $source = (string)file_get_contents($path);
        if (strtolower($file->getExtension()) === 'php') {
            $tokens = token_get_all($source);
            $expect_method_name = false;
            $token_count = count($tokens);
            foreach ($tokens as $index => $token) {
                if (!is_array($token)) {
                    if ($expect_method_name && $token !== '&') {
                        $expect_method_name = false;
                    }
                    continue;
                }
                if ($token[0] === T_FUNCTION) {
                    $expect_method_name = true;
                    continue;
                }
                if ($token[0] === T_VARIABLE && preg_match('/[a-z][A-Z]/', substr($token[1], 1))) {
                    $camel_case_variables++;
                }
                if ($token[0] === T_STRING && preg_match('/[a-z][A-Z]/', $token[1])) {
                    $previous = $index - 1;
                    while ($previous >= 0 && is_array($tokens[$previous])
                        && in_array($tokens[$previous][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
                        $previous--;
                    }
                    $next = $index + 1;
                    while ($next < $token_count && is_array($tokens[$next])
                        && in_array($tokens[$next][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
                        $next++;
                    }
                    if (!isset($external_property_names[$token[1]])
                        && $previous >= 0 && is_array($tokens[$previous]) && $tokens[$previous][0] === T_OBJECT_OPERATOR
                        && ($next >= $token_count || $tokens[$next] !== '(')) {
                        $camel_case_properties++;
                        if (count($camel_case_property_entries) < 40) {
                            $camel_case_property_entries[] = $relative . ':' . $token[2] . ' ->' . $token[1];
                        }
                    }
                }
                if (!$expect_method_name || in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
                    continue;
                }
                if ($token[0] === T_STRING && preg_match('/[a-z][A-Z]/', $token[1])) {
                    $camel_case_methods++;
                }
                $expect_method_name = false;
            }
        }
        if (preg_match($misspellings, $source, $match, PREG_OFFSET_CAPTURE)) {
            $line = substr_count(substr($source, 0, $match[0][1]), "\n") + 1;
            $errors[] = ltrim(str_replace(str_replace('\\', '/', $project_root), '', $path), '/')
                . ':' . $line . ' (' . $match[0][0] . ')';
        }
        if (preg_match($misspellings, $file->getFilename(), $match)) {
            $errors[] = ltrim(str_replace(str_replace('\\', '/', $project_root), '', $path), '/') . ' (Dateiname)';
        }
        $misleading_group_alias = '->' . 'can(';
        if (strpos($source, $misleading_group_alias) !== false) {
            $errors[] = ltrim(str_replace(str_replace('\\', '/', $project_root), '', $path), '/')
                . ' (mehrdeutige Gruppenpruefung; has_group() verwenden)';
        }
    }
}

foreach (array($project_root . '/index.php') as $standalone_file) {
    foreach (token_get_all((string)file_get_contents($standalone_file)) as $token) {
        if (is_array($token) && $token[0] === T_VARIABLE && preg_match('/[a-z][A-Z]/', substr($token[1], 1))) {
            $camel_case_variables++;
        }
    }
}

if ($camel_case_methods > 0) {
    $errors[] = "CamelCase-Methodenbestand ist auf {$camel_case_methods} gestiegen (maximal 0)";
}
if ($camel_case_variables > 0) {
    $errors[] = "CamelCase-Variablenbestand ist auf {$camel_case_variables} gestiegen (maximal 0)";
}
if ($camel_case_properties > 0) {
    $errors[] = "CamelCase-Propertyzugriffe sind auf {$camel_case_properties} gestiegen (maximal 0): "
        . implode(', ', $camel_case_property_entries);
}

if ($errors !== array()) {
    fwrite(STDERR, "Nicht vereinheitlichte Bezeichner:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "OK Source-Bezeichner: keine bekannten Tippfehler, CamelCase-Methoden/Variablen/Properties jeweils 0.\n";
