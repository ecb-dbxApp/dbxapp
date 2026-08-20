<?php
declare(strict_types=1);

/**
 * Mechanischer Refactoring-Helfer: verschiebt benannte Methoden bytegetreu
 * aus einer Klasse in ein Trait. Require/use werden bewusst manuell gesetzt.
 */
if ($argc < 5) {
    fwrite(STDERR, "source trait-file trait-name method...\n");
    exit(2);
}
[$script, $source_file, $trait_file, $trait_name] = array_slice($argv, 0, 4);
$wanted = array_fill_keys(array_slice($argv, 4), true);
$source = (string) file_get_contents($source_file);
$tokens = token_get_all($source);
$offsets = array();
$offset = 0;
foreach ($tokens as $index => $token) {
    $offsets[$index] = $offset;
    $offset += strlen(is_array($token) ? $token[1] : $token);
}

$ranges = array();
$methods = array();
foreach ($tokens as $index => $token) {
    if (!is_array($token) || $token[0] !== T_FUNCTION) continue;
    $name_index = $index + 1;
    while (isset($tokens[$name_index]) && is_array($tokens[$name_index])
        && in_array($tokens[$name_index][0], array(T_WHITESPACE, T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG), true)) {
        $name_index++;
    }
    if (!isset($tokens[$name_index]) || !is_array($tokens[$name_index]) || $tokens[$name_index][0] !== T_STRING) continue;
    $name = $tokens[$name_index][1];
    if (!isset($wanted[$name])) continue;

    $start_index = $index;
    for ($scan = $index - 1; $scan >= 0; $scan--) {
        $previous = $tokens[$scan];
        if (is_array($previous) && in_array($previous[0], array(
            T_WHITESPACE, T_DOC_COMMENT, T_COMMENT, T_PUBLIC, T_PROTECTED, T_PRIVATE,
            T_STATIC, T_FINAL, T_ABSTRACT, T_READONLY,
        ), true)) {
            $start_index = $scan;
            continue;
        }
        break;
    }

    $depth = 0;
    $started = false;
    $end_index = $index;
    for ($scan = $index; isset($tokens[$scan]); $scan++) {
        $text = is_array($tokens[$scan]) ? $tokens[$scan][1] : $tokens[$scan];
        if ($text === '{') {
            $started = true;
            $depth++;
        } elseif ($text === '}' && $started && --$depth === 0) {
            $end_index = $scan;
            break;
        } elseif ($text === ';' && !$started) {
            $end_index = $scan;
            break;
        }
    }
    $start = $offsets[$start_index];
    $end = $offsets[$end_index] + strlen(is_array($tokens[$end_index]) ? $tokens[$end_index][1] : $tokens[$end_index]);
    $ranges[] = array($start, $end);
    $methods[] = trim(substr($source, $start, $end - $start));
    unset($wanted[$name]);
}
if ($wanted !== array()) {
    fwrite(STDERR, 'Nicht gefunden: ' . implode(', ', array_keys($wanted)) . "\n");
    exit(3);
}
usort($ranges, static fn(array $left, array $right): int => $right[0] <=> $left[0]);
foreach ($ranges as [$start, $end]) {
    $source = substr($source, 0, $start) . "\n" . substr($source, $end);
}
$trait = "<?php\n\n/** Automatisch aus der stabilen Legacy-Fassade extrahierte interne Verantwortung. */\ntrait {$trait_name}\n{\n"
    . implode("\n\n", $methods) . "\n}\n";
file_put_contents($source_file, $source);
file_put_contents($trait_file, $trait);
echo $trait_name . ': ' . count($methods) . " Methoden\n";
