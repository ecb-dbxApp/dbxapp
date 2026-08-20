<?php

/**
 * Stellt sicher, dass Produktcode Datenbanken ausschliesslich ueber dbxDB nutzt.
 *
 * Direkte PDO-, mysqli- oder SQLite3-Verbindungen ausserhalb von dbxDB umgehen
 * die gemeinsame Fehlerbehandlung, Transaktionen und Treiber-Portabilitaet.
 * XML-XPath-Abfragen sowie Aufrufe der oeffentlichen dbxDB-API sind erlaubt.
 */

$root = dirname(__DIR__, 4);
$dbx_root = $root . DIRECTORY_SEPARATOR . 'dbx';
$allowed = array(
    realpath($dbx_root . '/include/dbxDB.class.php'),
);
$violations = array();
$patterns = array(
    '/\bnew\s+(?:\\\\?PDO|mysqli|SQLite3)\b/i' => 'direkte Datenbankverbindung',
    '/->db\s*\[[^\]]+\]\s*->\s*(?:prepare|query|exec)\s*\(/i' => 'direkter Zugriff auf interne DB-Verbindung',
    '/\bmysqli_(?:connect|query|prepare|execute)\s*\(/i' => 'direkter mysqli-Aufruf',
);

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dbx_root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }

    $path = $file->getRealPath();
    if ($path === false || in_array($path, $allowed, true)) {
        continue;
    }
    $normalized = str_replace('\\', '/', $path);
    if (preg_match('~/include/dbxDB[A-Za-z0-9_]*\.trait\.php$~', $normalized)) {
        continue;
    }
    if (str_contains($normalized, '/vendor/') || str_contains($normalized, '/tests/')) {
        continue;
    }

    $source = file_get_contents($path);
    if ($source === false) {
        fwrite(STDERR, "FAIL [dbxDB/scan]: Datei nicht lesbar: $path\n");
        exit(2);
    }
    foreach ($patterns as $pattern => $description) {
        if (preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $line = substr_count(substr($source, 0, (int)$match[1]), "\n") + 1;
                $relative = ltrim(str_replace(str_replace('\\', '/', $root), '', $normalized), '/');
                $violations[] = $relative . ':' . $line . ' (' . $description . ')';
            }
        }
    }
}

if ($violations) {
    fwrite(STDERR, "FAIL [dbxDB/abstraction]:\n - " . implode("\n - ", $violations) . "\n");
    exit(1);
}

echo "OK dbxDB abstraction: Produktcode ohne direkte PDO/mysqli/SQLite3-Operationen\n";
