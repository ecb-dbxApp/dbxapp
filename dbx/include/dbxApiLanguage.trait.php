<?php

/** Internal responsibility extracted from the stable dbxApi facade. */
trait dbxApiLanguageTrait
{
/* =====================================================
    * Sprachhilfen (lng_*)
    * ===================================================== */

   /**
    * Aktive UI-Sprache des laufenden Requests.
    *
    * Liest die Systemvariable `dbx_lng` und liefert immer einen
    * gueltigen, kleingeschriebenen Sprachcode zurueck - nie leer.
    *
    * Beispiel:
    * ```php
    * $lng = dbx()->lng_current(); // z. B. 'de', 'en', 'es'
    * ```
    *
    * @return string Aktueller Sprachcode, Fallback 'de'.
    */
   public function lng_current(): string {
      $lng = strtolower(trim((string) $this->get_system_var('dbx_lng', 'de')));
      return $lng !== '' ? $lng : 'de';
   }

/**
    * Konfigurierte, freigeschaltete Sprachen der Installation.
    *
    * Liest `accessible_lng` aus der dbx-Basiskonfiguration (Komma-Liste
    * oder Array) und liefert eine bereinigte Liste zweistelliger
    * Sprachcodes. Ungueltige Eintraege werden verworfen; ist danach
    * nichts uebrig, wird `['de']` zurueckgegeben.
    *
    * Beispiel:
    * ```php
    * foreach (dbx()->accessible_lngs() as $lng) {
    *     // z. B. Sprachumschalter im Menue aufbauen
    * }
    * ```
    *
    * @return string[] Liste aktiver Sprachcodes, mindestens ['de'].
    */
   public function accessible_lngs(): array {
      $raw = $this->get_cfg('dbx', 'accessible_lng', 'de');
      if ($raw === 'undef' || $raw === '' || $raw === null) {
         $raw = 'de';
      }

      $candidates = is_array($raw) ? $raw : preg_split('/\s*,\s*/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);
      $out = array();
      foreach ((array) $candidates as $val) {
         $val = strtolower(trim((string) $val));
         if ($val !== '' && $val !== 'undef' && preg_match('/^[a-z]{2,3}$/', $val)) {
            $out[] = $val;
         }
      }

      return count($out) ? $out : array('de');
   }

/**
    * Haengt ein Sprachsuffix an einen Basisnamen: content + de => content_de.
    *
    * Wird genutzt, um sprachabhaengige DD-/Tabellennamen aus einem
    * neutralen Basisnamen zu bilden (z. B. fuer dbxContent-Tabellen,
    * die je Sprache eine eigene Tabelle besitzen).
    *
    * Beispiel:
    * ```php
    * $table = dbx()->lng_name('content', 'en'); // 'content_en'
    * $table = dbx()->lng_name('content');       // aktive Sprache, z. B. 'content_de'
    * ```
    *
    * @param string $base Basisname ohne Sprachsuffix.
    * @param string $lng  Sprachcode; leer = aktive Sprache (lng_current()).
    * @return string Basisname mit Sprachsuffix, oder '' wenn $base leer ist.
    */
   public function lng_name(string $base, string $lng = ''): string {
      $base = trim($base);
      if ($base === '') {
         return '';
      }

      $lng = strtolower(trim($lng !== '' ? $lng : $this->lng_current()));
      if ($lng === '') {
         return $base;
      }

      return $base . '_' . $lng;
   }

/**
    * Löst eine sprachabhaengige Datei auf: name_lng.ext mit Fallback name.ext.
    *
    * Sucht zuerst `{$dir}{$name}_{$lng}.{$ext}`. Existiert die Datei nicht
    * und `$fallback` ist true, wird stattdessen die sprachneutrale Datei
    * `{$dir}{$name}.{$ext}` verwendet. Existiert keine der beiden, wird
    * ein leerer String zurueckgegeben.
    *
    * Beispiel:
    * ```php
    * // sucht z. B. tpl/htm/form-help-bar_en.htm, sonst tpl/htm/form-help-bar.htm
    * $file = dbx()->lng_resolve_file($dir, 'form-help-bar', 'htm', 'en');
    * ```
    *
    * @param string $dir      Verzeichnis mit abschliessendem '/'.
    * @param string $name     Dateiname ohne Sprachsuffix und ohne Endung.
    * @param string $ext      Dateiendung ohne fuehrenden Punkt.
    * @param string $lng      Sprachcode; leer = aktive Sprache (lng_current()).
    * @param bool   $fallback Bei fehlender Sprachdatei die neutrale Datei nutzen.
    * @return string Absoluter, os-normalisierter Pfad oder '' wenn nichts gefunden wurde.
    */
   public function lng_resolve_file(string $dir, string $name, string $ext, string $lng = '', bool $fallback = true): string {
      $dir = str_replace('\\', '/', $dir);
      if ($dir !== '' && substr($dir, -1) !== '/') {
         $dir .= '/';
      }

      $name = strtolower(trim($name));
      $ext  = ltrim(strtolower(trim($ext)), '.');
      if ($name === '' || $ext === '') {
         return '';
      }

      $lng = strtolower(trim($lng !== '' ? $lng : $this->lng_current()));
      if ($lng !== '') {
         $path_lng = $dir . $name . '_' . $lng . '.' . $ext;
         if (is_file($path_lng)) {
            return $this->os_path($path_lng);
         }
      }

      if (!$fallback) {
         return '';
      }

      $path_def = $dir . $name . '.' . $ext;
      if (is_file($path_def)) {
         return $this->os_path($path_def);
      }

      return '';
   }
}
