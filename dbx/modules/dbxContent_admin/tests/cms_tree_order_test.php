<?php

declare(strict_types=1);

/**
 * Prüft die gemeinsame CMS-Tree-Sortierung für Seiten und Ordner: stabile
 * 10er-Schritte, korrekte Zielposition und keine DB-Updates bei unveränderter
 * Reihenfolge oder unbekannter Quell-ID.
 */

require_once dirname(__DIR__) . '/include/dbxContentTreeOrder.class.php';

use dbx\dbxContent_admin\dbxContentTreeOrder;

$rows = array(
   array('id' => 1, 'sorter' => '0010', 'title' => 'A'),
   array('id' => 2, 'sorter' => '0020', 'title' => 'B'),
   array('id' => 3, 'sorter' => '0030', 'title' => 'C'),
);

$assert = static function (bool $condition, string $message): void {
   if (!$condition) throw new RuntimeException($message);
};

$no_change = dbxContentTreeOrder::plan($rows, 2, 0, 1);
$assert($no_change['updates'] === array(), 'Eine unveraenderte Position erzeugt DB-Schreibvorgaenge.');

$moved = dbxContentTreeOrder::plan($rows, 3, 1, 0);
$assert(
   $moved['updates'] === array(3 => '0010', 1 => '0020', 2 => '0030'),
   'Die stabile 10er-Sortierung vor einem Geschwister ist falsch.'
);
$assert(
   array_column($moved['ordered'], 'id') === array(3, 1, 2),
   'Die geplante Tree-Reihenfolge ist falsch.'
);

$missing = dbxContentTreeOrder::plan($rows, 99, 1, 0);
$assert($missing['updates'] === array(), 'Eine unbekannte Quell-ID darf keine Sortierung aendern.');

$irregular = dbxContentTreeOrder::plan(array(
   array('id' => 4, 'sorter' => '0015'),
   array('id' => 5, 'sorter' => '0090'),
), 5, 0, 4);
$assert(
   $irregular['updates'] === array(4 => '0010', 5 => '0020'),
   'Unregelmaessige Sortierwerte werden nicht auf 10er-Schritte normalisiert.'
);

$large_rows = array();
for ($i = 1; $i <= 1000; $i++) {
   $large_rows[] = array('id' => $i, 'sorter' => sprintf('%04d', $i * 10), 'title' => 'P' . $i);
}
$started = microtime(true);
$large_plan = dbxContentTreeOrder::plan($large_rows, 500, 0, 501);
$runtime_ms = (microtime(true) - $started) * 1000;
$assert(count($large_plan['updates']) === 2, 'Ein Nachbarwechsel schreibt mehr als die zwei betroffenen Sorter.');
$assert($runtime_ms < 50.0, 'Die Sortierplanung fuer 1000 Eintraege ist zu langsam: ' . round($runtime_ms, 2) . ' ms.');

echo 'OK CMS tree order planner: 2 statt 1000 Updates, ' . round($runtime_ms, 2) . " ms\n";
