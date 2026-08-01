<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);

function docs_quality_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function docs_quality_read(string $file): string
{
    $content = file_get_contents($file);
    if (!is_string($content)) {
        throw new RuntimeException('Dokumentationsdatei kann nicht gelesen werden: ' . $file);
    }
    return $content;
}

$contentRoot = $root . '/dbx/modules/dbxDocs/content';
$home = docs_quality_read($contentRoot . '/dbxapp_home.html');
$template = docs_quality_read($root . '/dbx/design/dbxdocs/htm/default.htm');
$doxyfile = is_file($root . '/Doxyfile') ? docs_quality_read($root . '/Doxyfile') : '';
$provision = docs_quality_read($root . '/dbx/modules/dbxDocs/include/dbxDocsContentProvision.class.php');

docs_quality_assert(
    substr_count($home, '<h1>') === 1
        && substr_count($home, 'dbxdocs-home-card is-') === 5
        && substr_count($home, 'dbxdocs-home-quicklinks') === 1
        && str_contains($home, 'name="dbx_run1" value="search"'),
    'Die Startseite muss genau eine H1, fünf Einstiege, vier Empfehlungen und eine Suche besitzen.'
);
docs_quality_assert(
    !preg_match('/(?:cinematic|dbx-cinema|84\s+Sekunden|Animation starten)/i', $home . $template)
        && !is_file($contentRoot . '/dbxapp_home_cinematic.html')
        && !is_file($root . '/dbx/design/dbxdocs/css/docs-cinematic.css')
        && !is_file($root . '/dbx/design/dbxdocs/js/dbxdocs-cinematic.js'),
    'Die entfernte Startseitenanimation darf weder eingebunden noch als Altdatei vorhanden sein.'
);

$curated = glob($contentRoot . '/*.html') ?: array();
foreach ($curated as $file) {
    $content = docs_quality_read($file);
    docs_quality_assert(
        preg_match('/C:\\\\xampp\\\\htdocs\\\\dbxapp/i', $content) !== 1,
        'Öffentlicher CMS-Inhalt enthält einen lokalen Windows-Pfad: ' . basename($file)
    );
    docs_quality_assert(
        substr_count($content, '<h1>') <= 1,
        'Kuratierter CMS-Inhalt enthält mehr als eine H1: ' . basename($file)
    );
}

$editorial = glob($root . '/??_*.md') ?: array();
foreach ($editorial as $file) {
    $content = docs_quality_read($file);
    docs_quality_assert(
        preg_match('/^#\s+.+$/m', $content) === 1,
        'Redaktionelle Quelle besitzt keine eindeutige H1: ' . basename($file)
    );
    docs_quality_assert(
        preg_match('/\b(?:Fuer|fuer|gehoert|Oeffentliche?|Aenderungen?|Qualitaetsziel)\b/', $content) !== 1,
        'Redaktionelle Quelle enthält vermeidbare ASCII-Umschreibung: ' . basename($file)
    );
    docs_quality_assert(
        preg_match('/C:\\\\xampp\\\\htdocs\\\\dbxapp/i', $content) !== 1,
        'Redaktionelle Quelle veröffentlicht einen lokalen Installationspfad: ' . basename($file)
    );
}

docs_quality_assert(
    str_contains($provision, 'synchronizeDocumentationMetadata()')
        && str_contains($provision, '<small>Seitentyp</small>')
        && str_contains($provision, '<small>Zielgruppe</small>')
        && str_contains($provision, '<small>Gültig für</small>')
        && str_contains($provision, '<small>Stand</small>')
        && str_contains($provision, 'rel="canonical"'),
    'Automatische Seitentyp-, Zielgruppen-, Versions- oder Quellenmetadaten fehlen.'
);
docs_quality_assert(
    $doxyfile === '' || str_contains($doxyfile, 'AUTOLINK_SUPPORT       = NO'),
    'Automatische Doxygen-Symbolverlinkung darf redaktionelle Beispiele nicht beschädigen.'
);

echo "OK documentation quality: concise entry, UTF-8 prose, metadata, canonical source, no video or local paths\n";
