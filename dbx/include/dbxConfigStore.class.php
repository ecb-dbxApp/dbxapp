<?php

declare(strict_types=1);

/**
 * Bereitet Modulkonfigurationen für die portable Speicherung vor.
 */
class dbxConfigStore {

   /** Liefert eine bereits geladene Modulkonfiguration aus der Session. */
   public function cached(string $module): ?array {
      $config = $_SESSION['dbx']['config'][$module] ?? null;
      return is_array($config) ? $config : null;
   }

   /** Merkt eine temporäre oder gerade persistierte Modulkonfiguration. */
   public function remember(string $module, array $config, string $file = ''): void {
      $_SESSION['dbx']['config'][$module] = $config;
      if ($file !== '') {
         $_SESSION['dbx']['config_file'][$module] = $file;
      }
   }

   /** Entfernt alle geladenen Cachewerte eines Moduls. */
   public function forget(string $module): void {
      unset(
         $_SESSION['dbx']['config'][$module],
         $_SESSION['dbx']['config_signature'][$module],
         $_SESSION['dbx']['config_file'][$module]
      );
   }

   /**
    * Exportiert ein verschachteltes Array als lesbare PHP-Zuweisungen.
    */
   public function export_php_assignments(array $values, string $prefix): string {
      $code = '';

      foreach ($values as $key => $value) {
         $key_part = is_int($key) || ctype_digit((string)$key)
            ? '[' . $key . ']'
            : "['" . addslashes((string)$key) . "']";

         if (is_array($value)) {
            $code .= $this->export_php_assignments($value, $prefix . $key_part);
            continue;
         }

         $formatted_value = match (true) {
            is_string($value) => "'" . addslashes($value) . "'",
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => 'null',
            default => (string)$value,
         };
         $code .= $prefix . $key_part . ' = ' . $formatted_value . ";\n";
      }

      return $code;
   }

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

      $db_name = (string)($entry['dbname'] ?? ($entry['name'] ?? ''));
      return strpos($key, '|') !== false
         && preg_match('/\.(db3|sqlite|sqlite3)$/i', $db_name) === 1;
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
