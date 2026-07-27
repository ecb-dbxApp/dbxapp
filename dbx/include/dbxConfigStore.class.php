<?php

/**
 * Bereitet Modulkonfigurationen für die portable Speicherung vor.
 */
class dbxConfigStore {

   /**
    * Erkennt dynamische SQLite-Moduldatenbanken.
    */
   public function is_module_db_entry(string $key, array $entry): bool {
      $type = strtolower(trim((string)($entry['type'] ?? '')));
      if ($type === 'sqlite' || $type === 'sqlite3') {
         return true;
      }

      if (preg_match('/\.(db3|sqlite|sqlite3)$/i', $key)) {
         return true;
      }

      $dbName = (string)($entry['dbname'] ?? ($entry['name'] ?? ''));
      return strpos($key, '|') !== false
         && preg_match('/\.(db3|sqlite|sqlite3)$/i', $dbName) === 1;
   }

   /**
    * Entfernt dynamische Moduldatenbanken aus einer Konfiguration.
    */
   public function strip_module_db_entries(array $config): array {
      if (!isset($config['db']) || !is_array($config['db'])) {
         return $config;
      }

      foreach ($config['db'] as $key => $entry) {
         if (is_array($entry) && $this->is_module_db_entry((string)$key, $entry)) {
            unset($config['db'][$key]);
         }
      }

      return $config;
   }

   /**
    * Normalisiert die Konfiguration für config.php.
    */
   public function normalize_for_store(array $config): array {
      return $this->strip_module_db_entries($config);
   }
}

