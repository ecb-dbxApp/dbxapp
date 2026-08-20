<?php

/** Internal responsibility extracted from the stable dbxApi facade. */
trait dbxApiAssetsTrait
{
/**
    * Liefert einen projekt-relativen Dateipfad fuer Editor-Marker.
    *
    * Entfernt das Installationsbasisverzeichnis (get_base_dir()) vom Anfang
    * eines absoluten Pfads. Wird intern von register_editor_file() und
    * editor_marker() genutzt, damit der RAD-Editor im Frontend niemals den
    * absoluten Serverpfad der Installation sieht.
    *
    * Beispiel:
    * ```php
    * $rel = dbx()->editor_file_path('/var/www/dbxapp/dbx/modules/dbx/dbx.class.php');
    * // 'dbx/modules/dbx/dbx.class.php'
    * ```
    *
    * @param string $file Absoluter oder bereits relativer Dateipfad.
    * @return string Projekt-relativer Pfad ohne fuehrenden Slash.
    */
   public function editor_file_path(string $file): string {
      $file = str_replace('\\', '/', $file);
      $base = str_replace('\\', '/', $this->get_base_dir());

      if (strpos($file, $base) === 0) {
         $file = substr($file, strlen($base));
      }

      return ltrim($file, '/');
   }

/**
    * Registriert eine im aktuellen Request genutzte Editor-Datei.
    *
    * Sinn und Zweck: Der RAD-Runtime-Editor muss am Ende eines Requests
    * wissen, welche FD-, DD-, Klassen-, Sysclass- oder Config-Dateien fuer
    * die aktuell dargestellte Seite relevant waren, um im entsprechenden
    * `dbx_edit`-Modus die passenden Bearbeiten-Links anzuzeigen. Kernel-Code
    * (u.a. get_system_obj(), get_modul_obj(), get_include_obj(), get_cfg())
    * ruft diese Methode bereits automatisch beim Laden auf; Modulcode nutzt
    * sie nur fuer zusaetzliche eigene Dateien.
    *
    * Beispiel:
    * ```php
    * dbx()->register_editor_file('fd', $fd_file);
    * ```
    *
    * @param string $kind Marker-Typ: fd, dd, class, sysclass oder config.
    * @param string $file Absoluter oder projekt-relativer Dateipfad.
    * @return void
    */
   public function register_editor_file(string $kind, string $file): void {
      $kind = strtolower(trim($kind));
      $file = trim($file);

      if ($kind === '' || $file === '') {
         return;
      }

      $path = $this->editor_file_path($file);
      $key  = $kind . '|' . $path;
      $this->editor_files[$key] = array('kind' => $kind, 'file' => $path);
   }

/**
    * Liefert alle im aktuellen Request registrierten Editor-Dateien.
    *
    * Wird am Ende der Request-Pipeline gelesen, um die Editor-Marker-Links
    * fuer den aktiven `dbx_edit`-Modus aufzubauen (siehe register_editor_file()
    * und editor_marker()). Modulcode ruft diese Methode normalerweise nicht
    * selbst auf.
    *
    * Beispiel:
    * ```php
    * foreach (dbx()->get_editor_files() as $entry) {
    *     // $entry = array('kind' => 'class', 'file' => 'dbx/modules/.../x.class.php')
    * }
    * ```
    *
    * @return array<string,array{kind:string,file:string}> Registrierte Dateien, keyed nach "kind|pfad".
    */
   public function get_editor_files(): array {
      return $this->editor_files;
   }

/**
    * Erzeugt einen HTML-Kommentar fuer den Frontend-Dateieditor.
    *
    * Die Marker werden nur im passenden dbx_edit-Modus ausgegeben:
    * - 4 = FD
    * - 5 = DD
    * - 6 = Modul-/Include-Class
    * - 7 = myX-Systemklasse
    * - 8 = config.php
    *
    * @param string $kind Marker-Typ: fd, dd, class, sysclass oder config.
    * @param string $file Absoluter oder projekt-relativer Dateipfad.
    * @return string HTML-Kommentar oder leerer String.
    */
   public function editor_marker(string $kind, string $file): string {
      $kind = strtolower(trim($kind));
      $mode = (int) $this->get_system_var('dbx_edit', 0, 'int');
      $modes = array('fd' => 4, 'dd' => 5, 'class' => 6, 'sysclass' => 7, 'config' => 8);

      if (!isset($modes[$kind]) || ($mode !== 9 && $mode !== $modes[$kind]) || $file === '') {
         return '';
      }

      $path = $this->editor_file_path($file);
      $path = str_replace('--', '-', $path);

      return "\n<!-- DBX-EDITOR|$kind|$path -->\n";
   }

}
