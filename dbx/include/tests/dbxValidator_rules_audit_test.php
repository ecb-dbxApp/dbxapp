<?php

/**
 * Statischer Kompatibilitaetstest fuer Validator-Regeln in Modulen, DD und FD.
 *
 * Erfasst literal angegebene Regeln sowohl aus `$field['rules'] = '...'` als
 * auch aus PHP-Named-Arguments `rules: '...'`. Dynamisch erzeugte Regeln
 * koennen naturgemaess nicht statisch ausgewertet werden.
 */

$root = dirname(__DIR__, 2);
require_once $root . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'dbxValidator.class.php';

$validator = new dbxValidator();
$rules = array();
$errors = array();

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(
        $root . DIRECTORY_SEPARATOR . 'modules',
        FilesystemIterator::SKIP_DOTS
    )
);

$patterns = array(
    '/\$field\s*\[\s*([\'"])rules\1\s*\]\s*=\s*([\'"])(.*?)\2\s*;/s',
    '/\brules\s*:\s*([\'"])(.*?)\1/s',
);

foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }

    $path = str_replace('\\', '/', $file->getPathname());
    $relativePath = '/' . ltrim(str_replace(
        '\\',
        '/',
        substr($file->getPathname(), strlen($root . DIRECTORY_SEPARATOR . 'modules'))
    ), '/');
    if (strpos($relativePath, '/vendor/') !== false
        || strpos($relativePath, '/add_ons/') !== false
        || strpos($relativePath, '/work/') !== false) {
        continue;
    }

    $source = (string)file_get_contents($file->getPathname());
    foreach ($patterns as $patternNo => $pattern) {
        if (!preg_match_all($pattern, $source, $matches, PREG_SET_ORDER)) {
            continue;
        }

        foreach ($matches as $match) {
            $valueIndex = $patternNo === 0 ? 3 : 2;
            $rule = stripcslashes((string)($match[$valueIndex] ?? ''));
            if ($rule === '' || strpbrk($rule, '${}') !== false) {
                continue;
            }
            $rules[$rule] = true;

            $result = $validator->validateResult('', $rule, 'audit');
            if (($result['code'] ?? '') === 'invalid_rule') {
                $errors[] = $path . ': ' . $rule;
            }
        }
    }
}

if (!$rules) {
    fwrite(STDERR, "FAIL: Keine literal definierten Validator-Regeln gefunden.\n");
    exit(1);
}

if ($errors) {
    fwrite(STDERR, "FAIL: Unbekannte oder widerspruechliche Validator-Regeln:\n - "
        . implode("\n - ", array_unique($errors)) . "\n");
    exit(1);
}

echo 'OK: ' . count($rules) . " literal definierte Validator-Regeln sind kompatibel.\n";
