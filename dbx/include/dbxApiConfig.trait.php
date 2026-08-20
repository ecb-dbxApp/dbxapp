<?php

/** Internal responsibility extracted from the stable dbxApi facade. */
trait dbxApiConfigTrait
{
/**
    * Laedt die Konfiguration eines Moduls.
    *
    * Beispiel:
    * ```
    * $cache = dbx()->get_cfg('dbx', 'cache');
    * $config = dbx()->get_cfg('dbx');
    * ```
    *
    * Wirkung:
    * Liest Modulkonfiguration aus Dateien/Cache und liefert entweder den
    * kompletten Config-Array oder einen einzelnen Schluessel.
    *
    * @param string $modul Modulname.
    * @param string $key Optionaler Config-Schluessel.
    * @param mixed $default Rueckgabewert, wenn der Schluessel nicht existiert.
    * @param bool $forDisplay Maskiert Geheimnisse aus config.php/config.local.php
    *                         im Demo-Modus fuer die Anzeige.
    * @return mixed
    */
   public function get_cfg(string $modul = 'dbx', string $key = '', $default = null, bool $for_display = false) {
      $module_config_file = $this->os_path($this->get_base_dir() . "dbx/modules/$modul/cfg/config.php");
      $module_local_config_file = $this->os_path($this->get_base_dir() . "dbx/modules/$modul/cfg/config.local.php");

      if (file_exists($module_config_file)) {
          $_SESSION['dbx']['config_file'][$modul] = $module_config_file;
          $this->register_editor_file('config', $module_config_file);
      }

      $signature = $this->config_file_signature($module_config_file)
         . '|' . $this->config_file_signature($module_local_config_file);
      $cached_signature = (string)($_SESSION['dbx']['config_signature'][$modul] ?? '');

      // Der Cache gilt nur so lange, wie Basis- und lokale Konfiguration
      // unveraendert sind. Das verhindert veraltete Werte in langen Sessions.
      if (!isset($_SESSION['dbx']['config'][$modul]) || $cached_signature !== $signature) {
         $config = $this->read_config_file($module_config_file);
         $local_config = $this->read_config_file($module_local_config_file);
         if ($local_config) {
            $config = array_replace_recursive($config, $local_config);
         }

         if (!isset($config['groups'])) {
            $config['groups'] = array('admin');
         } elseif (!is_array($config['groups'])) {
            $config['groups'] = array_values(array_filter(array_map('trim', explode(',', (string)$config['groups']))));
         }

         $_SESSION['dbx']['config'][$modul] = $config;
         $_SESSION['dbx']['config_signature'][$modul] = $signature;
      }

      $config = $_SESSION['dbx']['config'][$modul];

      // Spezifischen Schlüssel zurueckgeben, falls angegeben
      if ($key) {
          if (!array_key_exists($key, $config)) {
              return $default !== null ? $default : 'undef';
          }

          $val = $config[$key];
          if (($val === 'undef' || $val === '' || $val === null) && $default !== null) {
              return $default;
          }

          return $for_display && $this->is_demo_mode()
             ? $this->mask_cfg_for_display($val, $key)
             : $val;
      }
   
      // Gesamte Konfiguration zurueckgeben. Nur explizite Anzeigeaufrufe
      // maskieren Geheimnisse; interne Dienste benoetigen die Originalwerte.
      return $for_display && $this->is_demo_mode()
         ? $this->mask_cfg_for_display($config)
         : $config;
   }

/** Normalisiert kommaseparierte oder bereits aufgeteilte Gruppenlisten. */
   private function normalize_group_list($groups, bool $lowercase = false): array {
      if (!is_array($groups)) $groups = explode(',', (string)$groups);
      $groups = array_values(array_filter(array_map(static function($group) use ($lowercase): string {
         $group = trim((string)$group);
         return $lowercase ? strtolower($group) : $group;
      }, $groups), static fn(string $group): bool => $group !== ''));
      return array_values(array_unique($groups));
   }

/**
    * Erkennt geheime Konfigurationsfelder ausschliesslich innerhalb der von
    * get_cfg() geladenen Config-Arrays. Zugangsdaten und API-Schluessel muessen
    * gemaess der einheitlichen Config-Konvention `pass`, `key` oder `token` im
    * Feldnamen tragen. Dadurch bleibt die Maskierung eindeutig und pruefbar.
    */
   private function is_cfg_secret_key(string $key): bool {
      return preg_match('/(?:pass|key|token)/i', $key) === 1;
   }

/** Maskiert Geheimnisse rekursiv, ohne die intern geladene Config zu veraendern. */
   private function mask_cfg_for_display($value, string $key = '') {
      if (is_array($value)) {
         $masked = array();
         foreach ($value as $child_key => $child_value) {
            $masked[$child_key] = $this->mask_cfg_for_display($child_value, (string)$child_key);
         }
         return $masked;
      }

      if ($this->is_cfg_secret_key($key) && (is_scalar($value) || $value === null)) {
         return '******';
      }

      return $value;
   }

/**
    * Liest eine dbXapp-Konfigurationsdatei isoliert ein.
    *
    * Die Datei darf ausschliesslich $config befuellen. Fehler werden protokolliert,
    * aber nicht in die HTTP-Antwort geschrieben.
    */
   private function read_config_file(string $dir_file): array {
      if (!is_file($dir_file) || !is_readable($dir_file)) {
         return array();
      }

       $config = array();
       $loader = static function (string $__dbx_config_file): array {
          $config = array();
          include $__dbx_config_file;
          return is_array($config) ? $config : array();
       };
       $output_level = ob_get_level();
       ob_start();
       set_error_handler(function ($errno, $errstr, $errfile, $errline) {
          throw new \Exception("Fehler in config.php: $errstr in Zeile $errline");
       });

       try {
          $config = $loader($dir_file);
       } catch (\Throwable $e) {
          $this->debug('#CFG read failed file=(' . $dir_file . ') error=(' . $e->getMessage() . ')');
          $config = array();
       } finally {
          restore_error_handler();
          while (ob_get_level() > $output_level) ob_end_clean();
       }

      return is_array($config) ? $config : array();
   }

/** Liefert eine kompakte Signatur fuer die Cache-Invalidierung. */
   private function config_file_signature(string $file): string {
      if (!is_file($file)) {
         return '-';
      }

      $mtime = @filemtime($file);
      $size = @filesize($file);
      return (string)($mtime === false ? 0 : $mtime) . ':' . (string)($size === false ? 0 : $size);
   }

/**
    * Entfernt lokale Overrides aus der persistierbaren Basiskonfiguration.
    *
    * So kann ein Admin die normale config.php speichern, ohne Werte aus der
    * nicht versionierten config.local.php versehentlich hineinzukopieren.
    */
   private function strip_local_config_values(array $runtime, array $local, array $base): array {
      foreach ($local as $name => $local_value) {
         if (is_array($local_value) && isset($runtime[$name]) && is_array($runtime[$name])) {
            $base_value = isset($base[$name]) && is_array($base[$name]) ? $base[$name] : array();
            $runtime[$name] = $this->strip_local_config_values($runtime[$name], $local_value, $base_value);
            if ($runtime[$name] === array() && !array_key_exists($name, $base)) {
               unset($runtime[$name]);
            }
            continue;
         }

         if (array_key_exists($name, $base)) {
            $runtime[$name] = $base[$name];
         } else {
            unset($runtime[$name]);
         }
      }

      return $runtime;
   }

/**
    * Speichert die Konfiguration eines Moduls.
    *
    * Beispiel:
    * ```
    * dbx()->set_cfg('dbx', $config);
    * ```
    *
    * @param string $modul Modulname.
    * @param array $config Zu speichernde Konfiguration.
    * @param string $scope `base` fuer config.php, `local` fuer config.local.php.
    * @return int Anzahl geschriebener Bytes oder 0 bei Fehler.
    */
public function set_cfg(string $modul, array $config, string $scope = 'base'): int {
      if ($this->is_demo_mode()
          || preg_match('/^[A-Za-z0-9_]+$/', $modul) !== 1
          || !in_array($scope, array('base', 'local'), true)
      ) {
         return 0;
      }

      if ($scope === 'local') {
         $dir = $this->os_path($this->get_base_dir() . "dbx/modules/$modul/cfg/");
         if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            return 0;
         }

         $local_file = $this->os_path($dir . 'config.local.php');
         $config_store = $this->get_system_obj('dbxConfigStore');
         $content = "<?php\n" . $config_store->export_php_assignments($config, '$config');
         $written = file_put_contents($local_file, $content, LOCK_EX);
         if ($written === false) {
            return 0;
         }

         @chmod($local_file, 0600);
         unset(
            $_SESSION['dbx']['config'][$modul],
            $_SESSION['dbx']['config_signature'][$modul]
         );
         $this->get_cfg($modul);
         return (int)$written;
      }

      $content = "<?php \n"; // PHP-Tag hinzufügen, um die Datei als ausführbaren Code zu speichern
      $dir = $this->get_base_dir() . "dbx/modules/$modul/cfg/";
      if (isset($config['form-config-edit'])) unset($config['form-config-edit']);
      $dir_file = $this->os_path($dir . 'config.php');
      $local_file = $this->os_path($dir . 'config.local.php');
      $base_config = $this->read_config_file($dir_file);
      $local_config = $this->read_config_file($local_file);
      if ($local_config) {
         $config = $this->strip_local_config_values($config, $local_config, $base_config);
      }
      $config_store = $this->get_system_obj('dbxConfigStore');
      $config = $config_store->normalize_for_store($config);
      $dir = $this->os_path($dir);
   
      // Konfigurationsarray in PHP-Code konvertieren
      if (is_array($config)) {
          $content .= $config_store->export_php_assignments($config, '$config');
      }
   
      // Verzeichnis erstellen, falls es nicht existiert
      if (!is_dir($dir)) {
          mkdir($dir, 0700, true);
      }
   
      // Dateiinhalt schreiben und Erfolg prüfen
      $ok = file_put_contents($dir_file, $content);
      dbx()->debug("#CFG write ok=($ok) file=($dir_file)");
      if ($ok) {
          $runtime_config = $local_config ? array_replace_recursive($config, $local_config) : $config;
          $_SESSION['dbx']['config'][$modul] = $runtime_config;
          $_SESSION['dbx']['config_signature'][$modul]
             = $this->config_file_signature($dir_file) . '|' . $this->config_file_signature($local_file);
          $_SESSION['dbx']['config_file'][$modul] = $dir_file;
          $this->register_editor_file('config', $dir_file);
      }
   
   
      return $ok ?: 0; // Gibt 0 zurück, falls `file_put_contents` fehlschlägt
   }

/**
    * Führt einen lokalen Konfigurationsausschnitt rekursiv zusammen.
    *
    * Bestehende Passwoerter oder andere lokale Werte bleiben erhalten, wenn
    * der Patch nur DD-Serverbindungen ergaenzt.
    */
   public function patch_local_config(string $modul, array $patch): int {
      if (preg_match('/^[A-Za-z0-9_]+$/', $modul) !== 1) {
         return 0;
      }

      $local_file = $this->os_path(
         $this->get_base_dir() . "dbx/modules/$modul/cfg/config.local.php"
      );
      $local_config = $this->read_config_file($local_file);
      $local_config = array_replace_recursive($local_config, $patch);

      return $this->set_cfg($modul, $local_config, 'local');
   }

/**
    * Ersetzt genau einen lokalen Konfigurationsbereich.
    *
    * Im Gegensatz zu `patch_local_config()` koennen damit entfernte
    * DD-Bindungen wirklich geloescht werden, ohne andere lokale Bereiche wie
    * Datenbankpasswoerter anzutasten.
    */
   public function set_local_config_section(
      string $modul,
      string $section,
      array $value
   ): int {
      if (preg_match('/^[A-Za-z0-9_]+$/', $modul) !== 1
          || preg_match('/^[A-Za-z0-9_]+$/', $section) !== 1
      ) {
         return 0;
      }

      $local_file = $this->os_path(
         $this->get_base_dir() . "dbx/modules/$modul/cfg/config.local.php"
      );
      $local_config = $this->read_config_file($local_file);
      $local_config[$section] = $value;

      return $this->set_cfg($modul, $local_config, 'local');
   }

/**
    * Speichert einen Installationspfad portabel relativ zum Projektstamm.
    *
    * Sinn und Zweck: Konfigurationswerte, die auf Dateien/Verzeichnisse
    * innerhalb der Installation zeigen (z. B. Upload-Ziele), sollen nicht
    * mit dem absoluten Serverpfad in config.php/config.local.php landen,
    * damit dieselbe Konfiguration nach einem Umzug oder auf einer zweiten
    * Installation unveraendert funktioniert. Absolute Pfade innerhalb der
    * Installation werden relativ gemacht; Pfade ausserhalb bleiben absolut.
    * Gegenstueck: config_path_resolve().
    *
    * Beispiel:
    * ```php
    * $stored = dbx()->config_path_store($absolute_upload_dir, true);
    * dbx()->set_cfg('dbxShop', array('upload_dir' => $stored));
    * ```
    *
    * @param string $path Absoluter oder bereits relativer Pfad.
    * @param bool $dir_trailing_slash Abschliessenden Slash erzwingen (fuer Verzeichnisse).
    * @return string Projektrelativer (oder unveraendert absoluter) Pfad.
    */
   public function config_path_store(string $path, bool $dir_trailing_slash = false): string {
      $path = str_replace('\\', '/', trim($path));
      if ($path === '') return '';

      $base = str_replace('\\', '/', $this->get_base_dir());
      if ($this->path_is_absolute($path) || str_starts_with($path, $base)) {
         $absolute = str_replace('\\', '/', $this->os_path($path));
         $base_normalized = str_replace('\\', '/', $this->os_path($base));
         if (str_starts_with($absolute, $base_normalized)) {
            $path = substr($absolute, strlen($base_normalized));
         }
      }

      $path = ltrim($path, '/');
      if ($dir_trailing_slash && $path !== '') {
         $path = rtrim($path, '/') . '/';
      }
      return $path;
   }

/**
    * Löst einen gespeicherten Projektpfad in einen Betriebssystempfad auf.
    *
    * Gegenstueck zu config_path_store(): macht aus einem projektrelativen
    * Konfigurationswert wieder einen os_path()-normalisierten absoluten
    * Pfad. Ein bereits absoluter Wert wird unveraendert normalisiert.
    *
    * Beispiel:
    * ```php
    * $dir = dbx()->config_path_resolve(dbx()->get_cfg('dbxShop', 'upload_dir'));
    * ```
    *
    * @param string $path Projektrelativer oder absoluter Pfad.
    * @return string Absoluter, os-normalisierter Pfad.
    */
   public function config_path_resolve(string $path): string {
      $path = str_replace('\\', '/', trim($path));
      if ($path === '') return '';
      if (!$this->path_is_absolute($path)) {
         $path = $this->get_base_dir() . ltrim($path, '/');
      }
      return $this->os_path($path);
   }
}
