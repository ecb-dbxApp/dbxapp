<?php
declare(strict_types=1);

/**
 * Liest einen schlanken Controller zusammen mit seinen explizit eingebundenen
 * Service-Traits. Quelltextvertraege pruefen dadurch die echte Komposition und
 * zwingen Implementierung nicht zurueck in eine monolithische Datei.
 */
function dbx_test_module_source_bundle(string $entryFile): string
{
    if (!is_file($entryFile)) return '';
    $source = (string)file_get_contents($entryFile);
    $bundle = $source;
    if (!preg_match_all("~require_once __DIR__ \\. '/([^']+\\.trait\\.php)'~", $source, $matches)) {
        return $bundle;
    }
    $directory = dirname($entryFile);
    foreach (array_values(array_unique($matches[1])) as $relative) {
        $file = $directory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (is_file($file)) $bundle .= "\n" . (string)file_get_contents($file);
    }
    return $bundle;
}

/**
 * Extrahiert genau eine Methode aus einer zusammengesetzten PHP-Quelle.
 * Tokenisierung vermeidet Reihenfolgeannahmen zwischen Service-Traits und
 * bleibt auch bei verschachtelten Closures, Strings und Kommentaren stabil.
 */
function dbx_test_module_method_source(string $source, string $method): string
{
    $tokens = token_get_all($source);
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) continue;
        $name = '';
        $open = -1;
        for ($j = $i + 1; $j < $count; $j++) {
            $token = $tokens[$j];
            if (is_array($token) && $token[0] === T_STRING && $name === '') {
                $name = (string)$token[1];
            }
            if ($token === '{') {
                $open = $j;
                break;
            }
            if ($token === ';') break;
        }
        if ($name !== $method || $open < 0) continue;

        $start = $i;
        while ($start > 0) {
            $previous = $tokens[$start - 1];
            if (!is_array($previous) || !in_array($previous[0], array(T_WHITESPACE, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_FINAL, T_ABSTRACT), true)) {
                break;
            }
            $start--;
        }
        $depth = 0;
        $result = '';
        for ($j = $start; $j < $count; $j++) {
            $token = $tokens[$j];
            $text = is_array($token) ? (string)$token[1] : (string)$token;
            $result .= $text;
            if ($j < $open) continue;
            if ($token === '{') $depth++;
            if ($token === '}') {
                $depth--;
                if ($depth === 0) return $result;
            }
        }
    }
    return '';
}
