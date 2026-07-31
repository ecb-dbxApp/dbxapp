<?php

/**
 * Strukturtest für übersetzbare dbxApp-Templates und Sprach-DDs.
 *
 * Sichtbarer Text darf sich zwischen den Sprachen ändern. Technische
 * Template-/Modulmarker, Platzhalter, HTML-Tagfolge und URL-Attribute müssen
 * dagegen identisch bleiben.
 */

$moduleRoot = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'modules';
$errors = array();
$candidates = array();
$extensions = array('htm', 'html', 'tpl', 'txt', 'svg');
$germanPattern = '/[äöüÄÖÜß]'
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
    new RecursiveDirectoryIterator($moduleRoot, FilesystemIterator::SKIP_DOTS)
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
    $explicitGerman = (bool)preg_match('/_de$/i', $stem);
    $baseStem = $explicitGerman ? preg_replace('/_de$/i', '', $stem) : $stem;
    $key = str_replace('\\', '/', $file->getPath())
        . '/' . $baseStem . '.' . $extension;

    if (!isset($candidates[$key]) || $explicitGerman) {
        $candidates[$key] = array(
            'source' => $file->getPathname(),
            'target_base' => $file->getPath()
                . DIRECTORY_SEPARATOR . $baseStem,
            'extension' => $extension,
            'explicit' => $explicitGerman,
        );
    }
}

$extract = static function (string $pattern, string $content, int $group = 0): array {
    preg_match_all($pattern, $content, $matches);
    return $matches[$group] ?? array();
};
$markerPattern = '/\[(?:\/?(?:modul|inc)\b[^\]]*|tpl[^\]]*|'
    . '(?:dbx|rpt|cms):[^\]]+)\]/i';
$placeholderPattern = '/\{\{[A-Za-z_][A-Za-z0-9_]*\}[A-Za-z0-9_]*'
    . '|\{(?:[A-Za-z_][A-Za-z0-9_.:-]*|\$ref:[^{}]+)\}/';
$tagPattern = '/<\/?\s*([A-Za-z][A-Za-z0-9:-]*)\b/';
$urlPattern = '/\b(?:href|src|action)\s*=\s*([\'"])(.*?)\1/is';
$moduleBlockPattern = '/(\[(modul)\b[^\]]*\])(.*?)(\[\/\2\])/is';
$normalizedModuleBlocks = static function (string $content) use ($moduleBlockPattern): array {
    preg_match_all($moduleBlockPattern, $content, $matches, PREG_SET_ORDER);
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
    if (!$candidate['explicit'] && !preg_match($germanPattern, $source)) {
        continue;
    }

    $checked++;
    foreach (array('en', 'es') as $language) {
        $targetPath = $candidate['target_base'] . '_' . $language
            . '.' . $candidate['extension'];
        if (!is_file($targetPath)) {
            $errors[] = $targetPath . ': Sprachdatei fehlt';
            continue;
        }

        $target = (string)file_get_contents($targetPath);
        foreach (array(
            'technische Marker' => array($markerPattern, 0),
            'Platzhalter' => array($placeholderPattern, 0),
            'HTML-Tagfolge' => array($tagPattern, 1),
            'URL-Attribute' => array($urlPattern, 2),
        ) as $label => $rule) {
            $sourceParts = $extract($rule[0], $source, $rule[1]);
            $targetParts = $extract($rule[0], $target, $rule[1]);
            if ($label === 'technische Marker') {
                $normalizeCmsRoot = static fn(string $marker): string => preg_replace(
                    '/(\[cms:root=)[^&\]]+/i',
                    '$1{translated-root}',
                    $marker
                ) ?? $marker;
                $sourceParts = array_map($normalizeCmsRoot, $sourceParts);
                $targetParts = array_map($normalizeCmsRoot, $targetParts);
            }
            if ($sourceParts !== $targetParts) {
                $errors[] = $targetPath . ': ' . $label . ' verändert';
            }
        }
        if ($normalizedModuleBlocks($source) !== $normalizedModuleBlocks($target)) {
            $errors[] = $targetPath . ': Modul-Block technisch verändert';
        }
    }
}

$languageDdCount = 0;
foreach ($iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($moduleRoot, FilesystemIterator::SKIP_DOTS)
) as $file) {
    if (!$file->isFile() || !preg_match('/_(de|en|es)\.dd\.php$/', $file->getFilename(), $match)) {
        continue;
    }
    $path = str_replace('\\', '/', $file->getPathname());
    if (!str_contains($path, '/dd/')) {
        continue;
    }

    $languageDdCount++;
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
if ($languageDdCount === 0) {
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
    . "{$languageDdCount} Sprach-DDs verweisen auf echte Sprachtabellen.\n";
