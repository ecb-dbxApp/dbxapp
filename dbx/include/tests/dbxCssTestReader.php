<?php

declare(strict_types=1);

/**
 * Liest eine CSS-Datei so, wie sie der Browser durch lokale @imports sieht.
 *
 * Design-Vertraege sollen das wirksame Stylesheet pruefen und nicht davon
 * abhaengen, ob gemeinsame Regeln direkt oder ueber einen lokalen Import
 * eingebunden sind. Externe URLs und data-URLs werden bewusst nicht geladen.
 */
function dbx_test_read_css(string $path, array &$visited = array()): string
{
    $real = realpath($path);
    if ($real === false || isset($visited[$real])) {
        return '';
    }
    $visited[$real] = true;

    $css = (string) file_get_contents($real);
    if (!preg_match_all('~@import\s+(?:url\()?\s*["\']?([^"\')\s;]+)~i', $css, $matches)) {
        return $css;
    }

    $resolved = $css;
    foreach ($matches[1] as $import) {
        if (preg_match('~^(?:[a-z]+:|//|data:)~i', $import)) {
            continue;
        }
        $resolved .= "\n" . dbx_test_read_css(dirname($real) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $import), $visited);
    }
    return $resolved;
}
