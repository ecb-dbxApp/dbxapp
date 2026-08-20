<?php
declare(strict_types=1);

$include_dir = dirname(__DIR__);
$files = array_merge(
    array($include_dir . '/dbxApi.php'),
    glob($include_dir . '/dbxApi*.trait.php') ?: array()
);
$methods = array();

foreach ($files as $file) {
    $tokens = token_get_all((string)file_get_contents($file));
    $visibility = null;
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (is_array($token) && in_array($token[0], array(T_PUBLIC, T_PROTECTED, T_PRIVATE), true)) {
            $visibility = $token[0];
            continue;
        }
        if (!is_array($token) || $token[0] !== T_FUNCTION) continue;
        for ($j = $i + 1; $j < $count; $j++) {
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                if ($visibility === T_PUBLIC) $methods[] = $tokens[$j][1];
                break;
            }
            if ($tokens[$j] === '(') break;
        }
        $visibility = null;
    }
}

$duplicates = array_filter(array_count_values($methods), static fn(int $count): bool => $count > 1);
$forbidden = array_intersect($methods, array(
    'can', 'can_modul', 'html', 'has_text', 'use_system_class', 'timestamp',
    'time_diff', 'part_select', 'parse_url', 'is_modul', 'set_cookie_var',
    'debug2', 'is_int_value',
));

if (count($methods) > 75 || $duplicates !== array() || $forbidden !== array()) {
    fwrite(STDERR, 'Unkontrollierte dbxApi-Oberflaeche: ' . json_encode(array(
        'count' => count($methods),
        'duplicates' => $duplicates,
        'forbidden' => array_values($forbidden),
    ), JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
}

foreach (array('has_group', 'has_module_access', 'esc', 'get_system_obj') as $required) {
    if (!in_array($required, $methods, true)) {
        fwrite(STDERR, "Erforderliche kanonische dbxApi-Methode fehlt: {$required}\n");
        exit(1);
    }
}

echo 'OK dbxApi-Oberflaeche: ' . count($methods) . " eindeutige Methoden.\n";
