<?php

namespace dbx\dbxContent_admin;

/**
 * Plant die stabile 10er-Sortierung eines CMS-Tree-Zweigs.
 *
 * Die Klasse kennt weder Datenbank noch Cache. Dadurch verwenden Seiten und
 * Ordner exakt dieselbe, isoliert testbare Reihenfolgelogik. Zurueckgegeben
 * werden nur Sortierwerte, die sich gegenueber dem gespeicherten Stand aendern.
 */
final class dbxContentTreeOrder {

   /**
    * @param array<int,array<string,mixed>> $rows Nach sorter und Titel sortierte Geschwister
    * @return array{ordered:array<int,array<string,mixed>>,updates:array<int,string>}
    */
   public static function plan(array $rows, int $moved_id, int $before_id = 0, int $after_id = 0): array {
      $ordered = array();
      $moved = null;

      foreach ($rows as $row) {
         if (!is_array($row)) continue;
         $rid = (int)($row['id'] ?? 0);
         if ($rid <= 0) continue;
         if ($rid === $moved_id) {
            $moved = $row;
            continue;
         }
         $ordered[] = $row;
      }

      if (!is_array($moved)) {
         return array('ordered' => $rows, 'updates' => array());
      }

      $inserted = false;
      $out = array();
      foreach ($ordered as $row) {
         $rid = (int)($row['id'] ?? 0);
         if ($before_id > 0 && $rid === $before_id) {
            $out[] = $moved;
            $inserted = true;
         }
         $out[] = $row;
         if ($after_id > 0 && $rid === $after_id) {
            $out[] = $moved;
            $inserted = true;
         }
      }
      if (!$inserted) $out[] = $moved;

      $updates = array();
      $sort = 10;
      foreach ($out as $row) {
         $rid = (int)($row['id'] ?? 0);
         if ($rid <= 0) continue;
         $sorter = sprintf('%04d', $sort);
         if ((string)($row['sorter'] ?? '') !== $sorter) {
            $updates[$rid] = $sorter;
         }
         $sort += 10;
      }

      return array('ordered' => $out, 'updates' => $updates);
   }
}
