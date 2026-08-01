<?php
declare(strict_types=1);

/**
 * Erzeugt den API-Anteil der globalen Dokumentationssuche ausschließlich aus
 * der aktuell veröffentlichten Doxygen-Referenz.
 */

$base = dirname(__DIR__, 4);
$referenceDir = $base . '/reference/current';
$searchDir = $referenceDir . '/search';
$target = $base . '/dbx/modules/dbxDocs/assets/api-search-index.json';

if (!is_dir($searchDir)) {
    throw new RuntimeException('Doxygen-Suchverzeichnis fehlt: ' . $searchDir);
}

$sourceRoot = is_file($base . '/VERSION') ? $base : dirname($base) . '/dbxapp';
$versionFile = $sourceRoot . '/VERSION';
$sourceVersion = is_file($versionFile)
    ? trim((string)file_get_contents($versionFile))
    : 'unbekannt';

$groups = array(
    'classes' => array('label' => 'API-Klasse', 'weight' => 54),
    'functions' => array('label' => 'API-Methode', 'weight' => 48),
    'namespaces' => array('label' => 'Namespace', 'weight' => 42),
    'pages' => array('label' => 'API-Abschnitt', 'weight' => 38),
    'files' => array('label' => 'Quelldatei', 'weight' => 28),
);

$decodeJsString = static function (string $value): string {
    return str_replace(array("\\'", '\\\\', '\\/'), array("'", '\\', '/'), $value);
};
$isInternalNoise = static function (string $value): bool {
    return preg_match('/(?:^|[^a-z])(test|tests|testing|stub|fixture|probe|mock)(?:[^a-z]|$)/i', $value) === 1;
};

$entries = array();
$seen = array();
$pattern = "/\\['((?:\\\\.|[^'])*)'\\s*,\\s*\\['(?:\\.\\.\\/)?([^']+\\.html(?:#[^']*)?)'\\s*,\\s*1\\s*,\\s*'((?:\\\\.|[^'])*)'\\]/u";
foreach ($groups as $prefix => $group) {
    $files = glob($searchDir . '/' . $prefix . '_*.js') ?: array();
    sort($files, SORT_NATURAL | SORT_FLAG_CASE);
    foreach ($files as $file) {
        $raw = file_get_contents($file);
        if (!is_string($raw) || $raw === '') {
            continue;
        }
        if (preg_match_all($pattern, $raw, $matches, PREG_SET_ORDER) === false) {
            throw new RuntimeException('Doxygen-Suchdatei konnte nicht gelesen werden: ' . $file);
        }
        foreach ($matches as $match) {
            $title = trim($decodeJsString((string)$match[1]));
            $url = ltrim($decodeJsString((string)$match[2]), '/');
            $context = trim($decodeJsString((string)$match[3]));
            if ($title === '' || $url === '' || $isInternalNoise($title . ' ' . $context . ' ' . $url)) {
                continue;
            }
            $key = strtolower($prefix . '|' . $title . '|' . $url);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $entries[] = array(
                'title' => $title,
                'context' => $context,
                'url' => $url,
                'kind' => $prefix,
                'kind_label' => (string)$group['label'],
                'weight' => (int)$group['weight'],
            );
        }
    }
}

usort($entries, static fn(array $left, array $right): int => strnatcasecmp(
    (string)$left['title'] . ' ' . (string)$left['context'],
    (string)$right['title'] . ' ' . (string)$right['context']
));
$payload = array(
    'schema' => 1,
    'language' => 'de',
    'source' => 'reference/current/search',
    'source_version' => $sourceVersion,
    'generated_at' => date(DATE_ATOM),
    'count' => count($entries),
    'entries' => $entries,
);
$encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($encoded) || file_put_contents($target, $encoded . PHP_EOL, LOCK_EX) === false) {
    throw new RuntimeException('API-Suchindex konnte nicht geschrieben werden: ' . $target);
}

echo json_encode(array(
    'ok' => true,
    'source_version' => $sourceVersion,
    'entries' => count($entries),
    'target' => $target,
), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
