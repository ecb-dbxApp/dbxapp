<?php

/**
 * Strukturtest für übersetzbare dbxApp-Templates und Sprach-DDs.
 *
 * Sichtbarer Text darf sich zwischen den Sprachen ändern. Technische
 * Template-/Modulmarker, Platzhalter, HTML-Tagfolge und URL-Attribute müssen
 * dagegen identisch bleiben.
 */

$module_root = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'modules';
$errors = array();
$candidates = array();
$extensions = array('htm', 'html', 'tpl', 'txt', 'svg');
$german_pattern = '/[äöüÄÖÜß]'
    . '|(?:benutzer|registrier|speicher|lösch|loesch|bearbeit|beschreib|'
    . 'bezeichn|einstell|erstell|aktualis|auswähl|auswaehl|datei|deutsch|'
    . 'daten|druck|ausdruck|eintrag|erfolg|fehlermeld|formular|hinweis|'
    . 'kontaktanfrag|kontaktdaten|userdaten|zugangsdaten|hinzufüg|'
    . 'hinzufueg|einfueg|öffnen|oeffnen|workflow\s+abgeschlossen)'
    . '|\b(?:aber|abbrechen|alle|anzeigen|artikel|auswahl|bearbeiten|'
    . 'beschreibung|bitte|der|die|ein|eine|einstellungen|fehler|felder|'
    . 'für|fuer|gruppe|hilfe|ihre|inhalt|kein|keine|keiner|löschen|'
    . 'loeschen|menü|menue|neu|neue|neuer|neues|nicht|oder|ordner|seite|'
    . 'seiten|speichern|suche|suchen|titel|über|ueber|und|wählen|waehlen|'
    . 'weiter|wird|zurück|zurueck)\b/iu';
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($module_root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    $path = str_replace('\\', '/', $file->getPathname());
    $extension = strtolower($file->getExtension());
    if (
        !$file->isFile() ||
        !str_contains($path, '/tpl/') ||
        !in_array($extension, $extensions, true)
    ) {
        continue;
    }

    $stem = pathinfo($file->getFilename(), PATHINFO_FILENAME);
    if (preg_match('/_(en|es)$/i', $stem)) {
        continue;
    }
    $explicit_german = (bool)preg_match('/_de$/i', $stem);
    $base_stem = $explicit_german ? preg_replace('/_de$/i', '', $stem) : $stem;
    $key = str_replace('\\', '/', $file->getPath())
        . '/' . $base_stem . '.' . $extension;

    if (!isset($candidates[$key]) || $explicit_german) {
        $candidates[$key] = array(
            'source' => $file->getPathname(),
            'target_base' => $file->getPath()
                . DIRECTORY_SEPARATOR . $base_stem,
            'extension' => $extension,
            'explicit' => $explicit_german,
        );
    }
}

$extract = static function (string $pattern, string $content, int $group = 0): array {
    preg_match_all($pattern, $content, $matches);
    return $matches[$group] ?? array();
};
$marker_pattern = '/\[(?:\/?(?:modul|inc)\b[^\]]*|tpl[^\]]*|'
    . '(?:dbx|rpt|cms):[^\]]+)\]/i';
$placeholder_pattern = '/\{\{[A-Za-z_][A-Za-z0-9_]*\}[A-Za-z0-9_]*'
    . '|\{(?:[A-Za-z_][A-Za-z0-9_.:-]*|\$ref:[^{}]+)\}/';
$tag_pattern = '/<\/?\s*([A-Za-z][A-Za-z0-9:-]*)\b/';
$url_pattern = '/\b(?:href|src|action)\s*=\s*([\'"])(.*?)\1/is';
$module_block_pattern = '/(\[(modul)\b[^\]]*\])(.*?)(\[\/\2\])/is';
$normalized_module_blocks = static function (string $content) use ($module_block_pattern): array {
    preg_match_all($module_block_pattern, $content, $matches, PREG_SET_ORDER);
    $blocks = array();
    foreach ($matches as $match) {
        $body = preg_replace(
            '/((?:^|&)label=)[^&]*/i',
            '$1{translated-label}',
            $match[3]
        );
        $blocks[] = $match[1] . $body . $match[4];
    }
    return $blocks;
};
$checked = 0;

foreach ($candidates as $candidate) {
    $source = (string)file_get_contents($candidate['source']);
    if (!$candidate['explicit'] && !preg_match($german_pattern, $source)) {
        continue;
    }

    $checked++;
    foreach (array('en', 'es') as $language) {
        $target_path = $candidate['target_base'] . '_' . $language
            . '.' . $candidate['extension'];
        if (!is_file($target_path)) {
            // Modulhilfen besitzen bewusst einen sprachneutralen Fallback.
            // Eine nicht übersetzte Hilfe bleibt damit vollständig nutzbar,
            // ohne eine deutsche Kopie fälschlich als Übersetzung auszugeben.
            $normalized_source = str_replace('\\', '/', $candidate['source']);
            if (str_contains($normalized_source, '/tpl/help/')) {
                continue;
            }
            $errors[] = $target_path . ': Sprachdatei fehlt';
            continue;
        }

        $target = (string)file_get_contents($target_path);
        foreach (array(
            'technische Marker' => array($marker_pattern, 0),
            'Platzhalter' => array($placeholder_pattern, 0),
            'HTML-Tagfolge' => array($tag_pattern, 1),
            'URL-Attribute' => array($url_pattern, 2),
        ) as $label => $rule) {
            $source_parts = $extract($rule[0], $source, $rule[1]);
            $target_parts = $extract($rule[0], $target, $rule[1]);
            if ($label === 'technische Marker') {
                $normalize_cms_root = static fn(string $marker): string => preg_replace(
                    '/(\[cms:root=)[^&\]]+/i',
                    '$1{translated-root}',
                    $marker
                ) ?? $marker;
                $source_parts = array_map($normalize_cms_root, $source_parts);
                $target_parts = array_map($normalize_cms_root, $target_parts);
            }
            if ($source_parts !== $target_parts) {
                $errors[] = $target_path . ': ' . $label . ' verändert';
            }
        }
        if ($normalized_module_blocks($source) !== $normalized_module_blocks($target)) {
            $errors[] = $target_path . ': Modul-Block technisch verändert';
        }
    }
}

$language_dd_count = 0;
foreach ($iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($module_root, FilesystemIterator::SKIP_DOTS)
) as $file) {
    if (!$file->isFile() || !preg_match('/_(de|en|es)\.dd\.php$/', $file->getFilename(), $match)) {
        continue;
    }
    $path = str_replace('\\', '/', $file->getPathname());
    if (!str_contains($path, '/dd/')) {
        continue;
    }

    $language_dd_count++;
    $table = array();
    $fields = array();
    $indexes = array();
    include $file->getPathname();
    $language = strtolower($match[1]);
    if (
        strtolower((string)($table['language'] ?? '')) !== $language ||
        !preg_match('/_' . preg_quote($language, '/') . '$/i', (string)($table['table'] ?? ''))
    ) {
        $errors[] = $path
            . ': Sprach-DD verweist nicht auf eine echte Sprachversionstabelle';
    }
}

if ($checked === 0) {
    $errors[] = 'Keine deutschen Templatequellen gefunden';
}
if ($language_dd_count === 0) {
    $errors[] = 'Keine echten Sprach-DDs gefunden';
}
if ($errors) {
    fwrite(
        STDERR,
        "Sprachvarianten-Prüfung fehlgeschlagen:\n - "
        . implode("\n - ", array_unique($errors)) . "\n"
    );
    exit(1);
}

echo "OK: {$checked} Templategruppen sind strukturgleich übersetzt; "
    . "{$language_dd_count} Sprach-DDs verweisen auf echte Sprachtabellen.\n";
