<?php
namespace dbx\dbxAdmin;

dbx()->get_system_obj('dbxReport', 'use');

/**
 * DBX schema administration.
 *
 * Diese Klasse stellt die DBX-Admin-Werkzeuge fuer DDs, Datenbanken,
 * Tabellen, Felddefinitionen, Mapping, Synchronisation, Transfer,
 * Backup, Restore und Batch-Prozesse bereit.
 *
 * Zentrale Gold-Standard-Regeln in dieser Version:
 * - DDs werden aus allen Modulen gelesen.
 * - DD-zu-DB-Zuordnung erfolgt ueber table.server und table.table.
 * - Modul-DB-Dateien bleiben ihrem echten Modul zugeordnet.
 * - sys ist nur das Pseudo-Modul fuer Config-DB-Server.
 * - DB-Server ohne Tabellen bleiben sichtbar.
 * - DB-zu-DD-Suche verwendet durchgaengig dieselbe Alias-Logik.
 *
 * @package dbx\dbxAdmin
 */
class dbxSchema extends \dbxObj {

   private $schemaTexts;

   /**
    * Stabiler sprachabhängiger Textkontext für alle Schema-Reports.
    */
   private function schema_texts() {
      if ($this->schemaTexts) {
         return $this->schemaTexts;
      }
      dbx()->get_system_obj('dbxForm', 'use');
      $texts = new \dbxForm();
      $texts->set_form_help_enabled(false);
      $texts->_fd = 'dbxAdmin|schema-report';
      $texts->load_fd_messages();
      $this->schemaTexts = $texts;
      return $this->schemaTexts;
   }

   /**
    * Escaped einen Wert fuer die sichere HTML-Ausgabe in Templates, Tabellen und Attributen.
    *
    * @param mixed $value Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function esc($value) {
      return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
   }

   /**
    * Erzeugt einen normalisierten Vergleichsschluessel aus DB-Server und Tabellenname.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @param string $table Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function norm_key($server, $table) {
      return strtolower((string) $server) . '|' . strtolower((string) $table);
   }

   /**
    * Kodiert Server und Tabelle zu einer stabilen Report-RID fuer DB-Zeilen.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @param string $table Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function encode_db_rid($server, $table) {
      $json = json_encode(array((string)$server, (string)$table), JSON_UNESCAPED_SLASHES);
      return 'db_' . rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
   }

   /**
    * Dekodiert eine DB-Report-RID zurueck in Server und Tabelle.
    *
    * @param string $rid Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function decode_db_rid($rid) {
      $rid = (string)$rid;

      if (str_starts_with($rid, 'db_')) {
         $raw = substr($rid, 3);
         $pad = strlen($raw) % 4;
         if ($pad) {
            $raw .= str_repeat('=', 4 - $pad);
         }

         $json = base64_decode(strtr($raw, '-_', '+/'), true);
         $data = $json !== false ? json_decode($json, true) : null;
         if (is_array($data) && count($data) >= 2) {
            return array((string)$data[0], (string)$data[1]);
         }
      }

      $parts = explode('|', $rid, 2);
      return count($parts) == 2 ? $parts : array('', '');
   }

   /**
    * Erzeugt die DBX-DD-Referenz aus Modul und DD-Name.
    *
    * @param string $modul Eingabeparameter fuer diese Methode.
    * @param string $dd Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function dd_ref($modul, $dd) {
      return ($modul && $modul != 'dbx') ? $modul . '|' . $dd : $dd;
   }

   /**
    * Wandelt einen absoluten Pfad in eine kurze DBX-relative Anzeige um.
    *
    * @param string $path Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function path_rel($path) {
      $path = str_replace('\\', '/', (string)$path);
      $base = str_replace('\\', '/', dbx()->get_base_dir());

      if ($base !== '' && str_starts_with($path, $base)) {
         $path = substr($path, strlen($base));
      }

      $path = ltrim($path, '/');
      if (str_starts_with($path, 'dbx/modules/')) {
         $path = substr($path, strlen('dbx/modules/'));
      }

      return $path;
   }

   /**
    * Erzeugt eine DBX-Admin-URL fuer den angegebenen Laufmodus und Parameter.
    *
    * @param string $run1 Eingabeparameter fuer diese Methode.
    * @param string $run2 Eingabeparameter fuer diese Methode.
    * @param array $params Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function build_url($run1, $run2, $params = array()) {
      $url = '?dbx_modul=dbxAdmin&dbx_run1=' . rawurlencode($run1) . '&dbx_run2=' . rawurlencode($run2);

      foreach ($params as $key => $value) {
         $url .= '&' . rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
      }

      return $url;
   }

   /**
    * Erzeugt einen openWin-Link mit Icon, Titel und Fensteroptionen.
    *
    * @param string $url Eingabeparameter fuer diese Methode.
    * @param string $icon Eingabeparameter fuer diese Methode.
    * @param string $title Eingabeparameter fuer diese Methode.
    * @param int $width Eingabeparameter fuer diese Methode.
    * @param int $height Eingabeparameter fuer diese Methode.
    * @param string $class Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function openwin($url, $icon, $title, $width = 1200, $height = 780, $class = 'btn-inline') {
      $titleEsc = $this->esc($title);
      $urlEsc   = $this->esc($url);

      return '<a class="' . $class . ' dbx-win" href="' . $urlEsc . '" title="' . $titleEsc . '" '
           . 'data-url="' . $urlEsc . '" data-title="' . $titleEsc
           . '" role="button"><i class="' . $this->esc($icon) . '"></i></a>';
   }

   /**
    * Erzeugt ein Bootstrap-Badge fuer Status- und Hinweiswerte.
    *
    * @param string $label Eingabeparameter fuer diese Methode.
    * @param string $class Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function badge($label, $class = 'secondary') {
      return '<span class="badge bg-' . $this->esc($class) . '">' . $this->esc($label) . '</span>';
   }

   /**
    * Erzeugt ein Icon fuer Tabellenkoepfe mit Tooltip und ARIA-Label.
    *
    * @param string $icon Eingabeparameter fuer diese Methode.
    * @param string $title Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function header_icon($icon, $title) {
      return '<i class="' . $this->esc($icon) . '" title="' . $this->esc($title) . '" aria-label="' . $this->esc($title) . '"></i>';
   }

   /**
    * Normalisiert einen Text zu einem sicheren DD-Namen.
    *
    * @param string $name Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function sanitize_dd_name($name) {
      $name = preg_replace('/[^A-Za-z0-9_]+/', '_', (string) $name);
      $name = trim($name, '_');

      if ($name === '') {
         $name = 'new_dd';
      }

      if (preg_match('/^[0-9]/', $name)) {
         $name = 'dd_' . $name;
      }

      return $name;
   }

   /**
    * Normalisiert einen Server- oder Tabellennamen fuer automatisch erzeugte DB-View-DDs.
    *
    * @param string $name Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function sanitize_db_view_part($name) {
      $name = preg_replace('/[^A-Za-z0-9_.-]+/', '_', (string) $name);
      $name = trim($name, '._-');

      if ($name === '') {
         $name = 'db';
      }

      return $name;
   }

   /**
    * Normalisiert einen Namensteil fuer Backup-Dateinamen.
    *
    * @param string $name Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function backup_name_part($name) {
      $name = preg_replace('/[^A-Za-z0-9_.-]+/', '_', (string)$name);
      $name = trim($name, '._-');
      return $name !== '' ? $name : 'db';
   }

   /**
    * Quoted einen Datenbank-Identifier passend zum DB-Typ.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @param string $name Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function quote_db_ident($server, $name) {
      $dbType = dbx()->get_system_obj('dbxDB')->get_db_type($server);
      $name = str_replace(array('`', '"', ']'), '', (string)$name);

      if ($dbType === 'mysql') {
         return '`' . str_replace('`', '``', $name) . '`';
      }

      if ($dbType === 'sqlsrv') {
         return '[' . str_replace(']', ']]', $name) . ']';
      }

      return '"' . str_replace('"', '""', $name) . '"';
   }

   /**
    * Wandelt einen PHP-Wert in einen SQL-Literalwert fuer Restore-INSERTs um.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @param mixed $value Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function sql_db_value($server, $value) {
      if ($value === null) {
         return 'NULL';
      }

      if (is_bool($value)) {
         return $value ? '1' : '0';
      }

      if (is_array($value) || is_object($value)) {
         $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      }

      $oDB = dbx()->get_system_obj('dbxDB');
      return "'" . $oDB->escape((string)$value, $server) . "'";
   }

   /**
    * Liefert und erstellt bei Bedarf den Backup-Ordner fuer Schema-Backups.
    *
    * @return string
    */
   private function backup_dir() {
      $dir = dbx()->os_path(dbx()->get_file_dir() . 'db/backup/');
      if (!is_dir($dir)) {
         @mkdir($dir, 0775, true);
      }
      return rtrim(str_replace('\\', '/', $dir), '/') . '/';
   }

   /**
    * Prueft und liefert den sicheren absoluten Pfad zu einer Backup-Datei.
    *
    * @param string $file Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function backup_file_path($file) {
      $file = basename((string)$file);
      if ($file === '' || !preg_match('/\.json$/i', $file)) {
         return '';
      }

      $path = $this->backup_dir() . $file;
      $realDir = realpath($this->backup_dir());
      $realFile = realpath($path);

      if (!$realDir || !$realFile) {
         return '';
      }

      $realDir = rtrim(str_replace('\\', '/', $realDir), '/') . '/';
      $realFile = str_replace('\\', '/', $realFile);

      return str_starts_with($realFile, $realDir) ? $realFile : '';
   }

   /**
    * Erzeugt den relativen Anzeige- und Meldungspfad einer Backup-Datei.
    *
    * @param string $file Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function backup_relative_file($file) {
      return 'files/db/backup/' . basename((string)$file);
   }

   /**
    * Erzeugt den DD-Namen fuer eine automatisch erzeugte DB-Ansicht.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @param string $table Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function db_view_dd_name($server, $table) {
      return $this->sanitize_db_view_part($server) . '.' . $this->sanitize_db_view_part($table);
   }

   /**
    * Teilt eine DD-Report-RID in Modul und DD-Name auf.
    *
    * @param string $rid Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function split_dd_rid($rid) {
      $parts = explode('|', (string)$rid, 2);
      if (count($parts) != 2) {
         return array('', '');
      }

      $modul = trim($parts[0]);
      $dd    = trim($parts[1]);

      if (!preg_match('/^[A-Za-z0-9_.-]+$/', $modul) || !preg_match('/^[A-Za-z0-9_.-]+$/', $dd)) {
         return array('', '');
      }

      return array($modul, $dd);
   }

   /**
    * Liefert den absoluten Pfad zu einer DD-Datei.
    *
    * @param string $modul Eingabeparameter fuer diese Methode.
    * @param string $dd Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function dd_file_path($modul, $dd) {
      return dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/' . $modul . '/dd/' . $dd . '.dd.php');
   }

   /**
    * Liefert den editorfaehigen Dateipfad zu einer DD-Datei.
    *
    * @param string $modul Eingabeparameter fuer diese Methode.
    * @param string $dd Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function dd_editor_file($modul, $dd) {
      return dbx()->editor_file_path($this->dd_file_path($modul, $dd));
   }

   /**
    * Loescht eine DD-Datei sicher innerhalb des Modul-DD-Verzeichnisses.
    *
    * @param string $modul Eingabeparameter fuer diese Methode.
    * @param string $dd Eingabeparameter fuer diese Methode.
    * @return int
    */
   private function delete_dd_file($modul, $dd) {
      $file = $this->dd_file_path($modul, $dd);
      $base = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/' . $modul . '/dd/');
      $realBase = realpath($base);
      $realFile = realpath($file);

      if (!$realBase || !$realFile || !str_starts_with(str_replace('\\', '/', $realFile), rtrim(str_replace('\\', '/', $realBase), '/') . '/')) {
         return 0;
      }

      if (!is_file($realFile)) {
         return 0;
      }

      $ok = @unlink($realFile) ? 1 : 0;
      if ($ok) {
         $oDD = dbx()->get_system_obj('dbxDD');
         $oDD->clear_dd_cache($modul . '|' . $dd);
      }

      return $ok;
   }

   /**
    * Mergt bestehende DD-Feldmetadaten mit aktuell gelesenen DB-Feldern.
    *
    * @param array $oldFields Eingabeparameter fuer diese Methode.
    * @param array $dbFields Eingabeparameter fuer diese Methode.
    * @param string $server Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function merge_auto_dd_fields($oldFields, $dbFields, $server = '') {
      $oldByName = array();
      foreach ((array)$oldFields as $field) {
         $name = strtolower((string)($field['name'] ?? ''));
         if ($name !== '') {
            $oldByName[$name] = $field;
         }
      }

      $oDD = dbx()->get_system_obj('dbxDD');
      $dbType = $server ? $oDD->get_db_type($server) : '';

      $fields = array();
      foreach ((array)$dbFields as $field) {
         $name = strtolower((string)($field['name'] ?? ''));
         if ($name !== '' && isset($oldByName[$name])) {
            $field = $oDD->merge_dd_field_with_db_field($oldByName[$name], $field, $dbType);
         }

         $fields[] = $field;
      }

      return $fields;
   }

   /**
    * Stellt fuer eine DB-Tabelle eine automatisch erzeugte DD-Ansicht sicher.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @param string $table Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function ensure_db_view_dd($server, $table) {
      $oDD = dbx()->get_system_obj('dbxDD');
      if (!$server || !$table || !$oDD->get_table_exist($server, $table)) {
         return '';
      }

      $ddName = $this->db_view_dd_name($server, $table);
      $ddRef  = 'dbx|' . $ddName;
      $old    = file_exists($this->dd_file_path('dbx', $ddName)) ? $oDD->get_dd_model($ddRef) : array();

      $tableMeta = is_array($old['table'] ?? null) ? $old['table'] : array();
      $tableMeta['server'] = $server;
      $tableMeta['table']  = $table;
      $tableMeta['datadic'] = $ddName;

      $fields  = $this->merge_auto_dd_fields($old['fields'] ?? array(), $oDD->get_db_fields($server, $table), $server);
      $indexes = $oDD->get_db_indexes($server, $table);

      if (!$fields) {
         return '';
      }

      return $oDD->save_dd('dbx', $ddName, $tableMeta, $fields, $indexes) ? $ddRef : '';
   }

   /**
    * Erzeugt die Report-Feldliste aus einer DD-Definition.
    *
    * @param string $ddRef Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function data_report_fields($ddRef) {
      $oDD = dbx()->get_system_obj('dbxDD');
      $fields = $oDD->get_dd_fields($ddRef);
      $out = array();

      foreach ((array)$fields as $field) {
         $name = (string)($field['name'] ?? '');
         if ($name === '') {
            continue;
         }

         $label = trim((string)($field['label'] ?? ''));
         $out[$name] = $label !== '' ? $label : $name;
      }

      return $out;
   }

   /**
    * Erzeugt die Grid-Spaltendefinition fuer den Tabulator-Dateneditor.
    *
    * @param string $ddRef Eingabeparameter fuer diese Methode.
    * @param string $primary Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function data_grid_cols($ddRef, $primary) {
      $oDB = dbx()->get_system_obj('dbxDB');
      $fields = $oDB->get_dd_fields($ddRef);
      $cols = array();

      foreach ((array)$fields as $field) {
         $name = (string)($field['name'] ?? '');
         if ($name === '' || str_starts_with($name, '_')) {
            continue;
         }

         $label = trim((string)($field['label'] ?? ''));
         $label = str_replace(array(':', '[', ']'), '-', $label ?: $name);
         $fieldType = strtolower(trim((string)($field['type'] ?? '')));
         $gridType = $oDB->map_dd_type_to_grid_type($fieldType ?: 'text');
         $protect = isset($field['protect']) ? (string)$field['protect'] : '0';

         if ($protect === '2') {
            continue;
         }

         $flag = ($name === $primary || $protect === '1') ? 'p' : '';
         $options = array();

         if ($name === 'content' || in_array($fieldType, array('mediumtext', 'longtext'), true)) {
            $options[] = 'editor=textarea';
            $options[] = 'formatter=truncate';
            $options[] = 'bigEditor=1';
            $options[] = 'maxChars=180';
            $options[] = 'width=420';
            $options[] = 'minWidth=260';
         }

         $col = $name . '[' . $label . ']:' . $gridType;
         if ($flag !== '') {
            $col .= ':' . $flag;
         }
         if ($options) {
            $col .= ':' . implode(';', $options);
         }

         $cols[] = $col;
      }

      return implode(',', $cols);
   }

   /**
    * Ermittelt DD-Kontext, Feldliste und Primaerschluessel aus der aktuellen Anfrage.
    *
    * @return array
    */
   private function data_context_from_request() {
      $mode = dbx()->get_modul_var('dbx_run1', 'dd', 'parameter');
      $rid  = dbx()->get_modul_var('rid', '', 'parameter');
      $ddRef = '';

      if ($mode == 'db') {
         [$server, $table] = $this->decode_db_rid($rid);
         if ($server && $table) {
            $ddRef = $this->ensure_db_view_dd($server, $table);
         }
      } else {
         [$modul, $dd] = $this->split_dd_rid($rid);
         if (!$modul || !$dd) {
            [$modul, $dd] = $this->dd_params_from_request();
         }
         if ($modul && $dd) {
            $ddRef = $this->dd_ref($modul, $dd);
         }
      }

      $oDB = dbx()->get_system_obj('dbxDB');
      $flds = $ddRef ? $this->data_report_fields($ddRef) : array();
      $primary = $ddRef ? $oDB->get_dd_primary($ddRef) : 'id';

      if (!$primary || !isset($flds[$primary])) {
         $primary = isset($flds['id']) ? 'id' : '';
      }

      return array($ddRef, $flds, $primary);
   }

   /**
    * Liest einen einzelnen Datensatz anhand des Primaerschluessels.
    *
    * @param string $ddRef Eingabeparameter fuer diese Methode.
    * @param string $primary Eingabeparameter fuer diese Methode.
    * @param int $id Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function data_row_by_id($ddRef, $primary, $id) {
      if (!$ddRef || !$primary || $id === '' || $id === null) {
         return array();
      }

      $oDB = dbx()->get_system_obj('dbxDB');
      $rows = $oDB->select($ddRef, array($primary => $id), '', '', 'ASC', '', 1, 0, 0);
      if (is_array($rows) && isset($rows[0]) && is_array($rows[0])) {
         return $rows[0];
      }

      return array();
   }

   /**
    * Rendert den direkt editierbaren Datenreport fuer eine DD-Quelle.
    *
    * @param string $ddRef Eingabeparameter fuer diese Methode.
    * @param string $title Eingabeparameter fuer diese Methode.
    * @param string $readUrl Eingabeparameter fuer diese Methode.
    * @param string $saveUrl Eingabeparameter fuer diese Methode.
    * @param string $insertUrl Eingabeparameter fuer diese Methode.
    * @param string $deleteUrl Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function run_data_report($ddRef, $title, $readUrl, $saveUrl, $insertUrl, $deleteUrl) {
      $oDB = dbx()->get_system_obj('dbxDB');
      $oReport = dbx()->get_system_obj('dbxReport');
      $flds = $this->data_report_fields($ddRef);
      $primary = $oDB->get_dd_primary($ddRef);

      if (!$flds || !$primary || !isset($flds[$primary])) {
         return '<div class="alert alert-warning">Keine editierbare Felddefinition mit Primaerschluessel vorhanden.</div>';
      }

      $oReport->init('report-schema-data-' . substr(md5($ddRef), 0, 10), 'schema-data-report');
      $oReport->_rflds = $flds;
      $oReport->_mode = 'tabulurator';
      $oReport->_rrows = 'auto';
      $oReport->_grid_id = 'schema_data_' . substr(md5($ddRef), 0, 10);
      $oReport->_grid_cols = $this->data_grid_cols($ddRef, $primary);
      $oReport->_grid_layout = 'fitData';
      $oReport->_grid_read_url = $readUrl;
      $oReport->_grid_save_url = $saveUrl;
      $oReport->_grid_insert_url = $insertUrl;
      $oReport->_grid_delete_url = $deleteUrl;
      $oReport->add_obj('title', 'obj-value', $this->esc($title));
      $oReport->add_obj('subtitle', 'obj-value', $this->esc('Direkt editierbares Grid'));
      $oReport->add_rep('bar_title', $title);
      $oReport->add_rep('bar_subtitle', 'Direkt editierbares Grid');
      $oReport->add_obj('primary', 'obj-value', $this->esc($primary));

      return $oReport->run();
   }

   /**
    * Liefert Grid-Daten als JSON fuer den Dateneditor.
    *
    * @return void
    */
   private function run_data_read() {
      [$ddRef, $flds, $primary] = $this->data_context_from_request();
      if (!$ddRef || !$flds || !$primary) {
         $this->json_response(array('ok' => 0, 'count' => 0, 'rows' => array(), 'msg' => 'Keine Felddefinition vorhanden.'));
      }

      $oDB = dbx()->get_system_obj('dbxDB');
      $rows = $oDB->select($ddRef, '', array_keys($flds), '', 'ASC', '', 0, 0, 0);
      if (!is_array($rows)) {
         $rows = array();
      }

      $out = array();
      foreach (array_values($rows) as $pos => $row) {
         if (is_array($row)) {
            $row['_dbx_rownum'] = $pos + 1;
            $out[] = $row;
         }
      }

      $count = $oDB->count($ddRef, '');
      $this->json_response(array(
         'ok'          => 1,
         'count'       => $count >= 0 ? $count : count($out),
         'rows'        => $out,
         'server_time' => date('Y-m-d H:i:s'),
      ));
   }

   /**
    * Speichert geaenderte Grid-Zeilen im Dateneditor.
    *
    * @return void
    */
   private function run_data_save() {
      [$ddRef, $flds, $primary] = $this->data_context_from_request();
      $payload = $this->request_json();
      $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : array();

      if (!$ddRef || !$flds || !$primary) {
         $this->json_response(array('ok' => 0, 'msg' => 'Keine editierbare Felddefinition vorhanden.'));
      }

      $oDB = dbx()->get_system_obj('dbxDB');
      $okCount = 0;

      foreach ($rows as $row) {
         if (!is_array($row)) {
            continue;
         }

         $id = $row[$primary] ?? ($row['id'] ?? null);
         if ($id === null || $id === '') {
            continue;
         }

         $values = array();
         foreach ($flds as $field => $label) {
            if ($field === $primary || !array_key_exists($field, $row)) {
               continue;
            }
            $values[$field] = $row[$field];
         }

         if (!$values) {
            continue;
         }

         $ok = $oDB->update($ddRef, $values, array($primary => $id), 1, 1, 1, 1);
         if ($ok === 1 && $oDB->_update_count > 0) {
            $okCount++;
         }
      }

      $this->json_response(array('ok' => 1, 'success' => true, 'count' => $okCount));
   }

   /**
    * Legt einen neuen Datensatz fuer den Dateneditor an.
    *
    * @return void
    */
   private function run_data_insert() {
      [$ddRef, $flds, $primary] = $this->data_context_from_request();
      if (!$ddRef || !$flds || !$primary) {
         $this->json_response(array('ok' => 0, 'msg' => 'Keine editierbare Felddefinition vorhanden.'));
      }

      $oDB = dbx()->get_system_obj('dbxDB');
      $id = 0;
      if ($oDB->insert($ddRef, array(), 1, 1, 1, 1) === 1) {
         $id = $oDB->get_insert_id();
      }
      if ($id <= 0) {
         $this->json_response(array('ok' => 0, 'msg' => 'Datensatz konnte nicht angelegt werden.'));
      }

      $row = $this->data_row_by_id($ddRef, $primary, $id);
      $this->json_response(array(
         'ok'      => 1,
         'success' => true,
         'row'     => $row,
      ));
   }

   /**
    * Loescht einen Datensatz aus dem Dateneditor.
    *
    * @return void
    */
   private function run_data_delete() {
      [$ddRef, $flds, $primary] = $this->data_context_from_request();
      $payload = $this->request_json();
      $id = $payload['id'] ?? null;

      if (!$ddRef || !$flds || !$primary || $id === null || $id === '') {
         $this->json_response(array('ok' => 0, 'msg' => 'Datensatz-ID fehlt.'));
      }

      $oDB = dbx()->get_system_obj('dbxDB');
      $ok = $oDB->delete($ddRef, array($primary => $id), 1, 1);
      $this->json_response(array('ok' => $ok > 0 ? 1 : 0, 'success' => $ok > 0));
   }

   /**
    * Sendet eine JSON-Antwort und beendet die aktuelle Anfrage.
    *
    * @param array $data Eingabeparameter fuer diese Methode.
    * @return void
    */
   private function json_response($data) {
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode($data, JSON_UNESCAPED_UNICODE);
      exit;
   }

   /**
    * Liest und dekodiert den JSON-Request-Body.
    *
    * @return array
    */
   private function request_json() {
      $raw = file_get_contents('php://input');
      $data = $raw ? json_decode($raw, true) : array();
      return is_array($data) ? $data : array();
   }

   /**
    * Liest und validiert Modul- und DD-Parameter aus der Anfrage.
    *
    * @return array
    */
   private function dd_params_from_request() {
      $modul = dbx()->get_modul_var('modul', '', 'parameter');
      $dd    = dbx()->get_modul_var('dd', '', 'parameter');

      if ((!$modul || !$dd) && strpos((string)$dd, '|') !== false) {
         [$modul, $dd] = $this->split_dd_rid($dd);
      }

      if (!$modul && $dd) {
         $modul = 'dbx';
      }

      if (!preg_match('/^[A-Za-z0-9_.-]+$/', (string)$modul) || !preg_match('/^[A-Za-z0-9_.-]+$/', (string)$dd)) {
         return array('', '');
      }

      return array($modul, $dd);
   }

   /**
    * Liefert die erlaubten DD-Feldschluessel fuer den Feldeditor.
    *
    * @return array
    */
   private function dd_field_grid_keys() {
      $oDD = dbx()->get_system_obj('dbxDD');
      return $oDD->dd_field_schema_keys();
   }

   /**
    * Wandelt eine DD-Felddefinition in eine Grid-Zeile um.
    *
    * @param string $field Eingabeparameter fuer diese Methode.
    * @param int $id Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function dd_field_grid_row($field, $id) {
      $row = array(
         'id'   => (string)$id,
         '_pos' => (int)$id + 1,
      );

      foreach ($this->dd_field_grid_keys() as $key) {
         $value = $field[$key] ?? '';
         $row[$key] = is_array($value) ? implode(',', $value) : (string)$value;
      }

      return $row;
   }

   /**
    * Wandelt alle DD-Felddefinitionen in Grid-Zeilen um.
    *
    * @param array $fields Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function dd_field_grid_rows($fields) {
      $rows = array();
      foreach (array_values((array)$fields) as $id => $field) {
         $rows[] = $this->dd_field_grid_row((array)$field, $id);
      }

      return $rows;
   }

   /**
    * Erzeugt die Grid-Spaltendefinition fuer den DD-Feldeditor.
    *
    * @return string
    */
   private function dd_field_grid_cols() {
      $types = implode('~', array(
         'varchar', 'char', 'text', 'mediumtext', 'longtext',
         'int', 'integer', 'bigint', 'smallint', 'mediumint', 'tinyint',
         'decimal', 'float', 'double',
         'date', 'datetime', 'timestamp', 'time',
         'bool', 'boolean', 'json', 'blob',
      ));

      return implode(',', array(
         '_pos[#]:number:p:width=62;hozAlign=center;headerHozAlign=center',
         'name[Name]:text::width=150',
         'type[Type]:text::editor=list;values=' . $types . ';width=120',
         'index[Index]:text::editor=list;values==ohne~PRI=Primaer~MU=Mehrfach~INDEX=Index~UNIQUE=Unique;width=115',
         'length[Laenge]:text::width=88',
         'default[Default]:text::width=120',
         'label[Label]:text::width=160',
         'rules[Regeln]:text::width=180',
         'tooltip[Tooltip]:text::editor=textarea;width=220',
         'errormsg[Fehlermeldung]:text::editor=textarea;width=220',
         'placeholder[Platzhalter]:text::width=160',
         'convert[Convert]:text::width=120',
         'protect[Schutz]:text::editor=list;values=0=editierbar~1=geschuetzt~2=versteckt;width=120',
         'group[Gruppe]:text::width=130',
         'mask[Maske]:text::width=130',
         'data[Data]:text::editor=textarea;width=220',
         'options[Optionen]:text::editor=textarea;width=220',
         'tpl[TPL]:text::width=160',
         'js[JS]:text::editor=textarea;width=180',
         'prompt[Prompt]:text::editor=textarea;width=220',
      ));
   }

   /**
    * Liest das vollstaendige DD-Modell fuer Modul und DD.
    *
    * @param string $modul Eingabeparameter fuer diese Methode.
    * @param string $dd Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function dd_field_model($modul, $dd) {
      if (!$modul || !$dd) {
         return array();
      }

      $oDD = dbx()->get_system_obj('dbxDD');
      return $oDD->get_dd_model($this->dd_ref($modul, $dd));
   }

   /**
    * Speichert ein DD-Modell mit aktualisierten Felddefinitionen.
    *
    * @param string $modul Eingabeparameter fuer diese Methode.
    * @param string $dd Eingabeparameter fuer diese Methode.
    * @param array $model Eingabeparameter fuer diese Methode.
    * @param array $fields Eingabeparameter fuer diese Methode.
    * @return bool
    */
   private function save_dd_field_model($modul, $dd, $model, $fields) {
      $oDD = dbx()->get_system_obj('dbxDD');
      return $oDD->save_dd($modul, $dd, $model['table'] ?? array(), array_values($fields), $model['indexes'] ?? array());
   }

   /**
    * Normalisiert eine vom Feldeditor kommende Grid-Zeile.
    *
    * @param array $row Eingabeparameter fuer diese Methode.
    * @param array $old Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function normalize_dd_field_grid_post($row, $old = array()) {
      $field = is_array($old) ? $old : array();

      foreach ($this->dd_field_grid_keys() as $key) {
         if (array_key_exists($key, $row)) {
            $field[$key] = is_array($row[$key]) ? implode(',', $row[$key]) : trim((string)$row[$key]);
         }
      }

      $field['name'] = trim((string)($field['name'] ?? ''));
      $field['type'] = strtolower(trim((string)($field['type'] ?? 'varchar')));

      if ($field['type'] === '') {
         $field['type'] = 'varchar';
      }

      if (!isset($field['protect']) || !in_array((string)$field['protect'], array('0', '1', '2'), true)) {
         $field['protect'] = '0';
      }

      return $field;
   }

   /**
    * Validiert DD-Feldnamen auf Gueltigkeit und Duplikate.
    *
    * @param array $fields Eingabeparameter fuer diese Methode.
    * @param string $message Eingabeparameter fuer diese Methode.
    * @return bool
    */
   private function validate_dd_fields($fields, &$message = '') {
      $names = array();

      foreach ((array)$fields as $field) {
         $name = trim((string)($field['name'] ?? ''));
         if ($name === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            $message = 'Ungueltiger Feldname: ' . $name;
            return false;
         }

         $key = strtolower($name);
         if (isset($names[$key])) {
            $message = 'Feldname doppelt: ' . $name;
            return false;
         }

         $names[$key] = true;
      }

      return true;
   }

   /**
    * Rendert den DD-Feldeditor als Grid.
    *
    * @return string
    */
   private function run_dd_fields_grid() {
      [$modul, $dd] = $this->dd_params_from_request();
      $model = $this->dd_field_model($modul, $dd);

      if (!$model) {
         return '<div class="alert alert-danger">DD nicht gefunden.</div>';
      }

      $baseParams = array(
         'modul' => $modul,
         'dd'    => $dd,
      );

      $oReport = dbx()->get_system_obj('dbxReport');
      $oReport->init('report-dd-fields-grid', 'schema-fields-grid');
      $oReport->_mode = 'tabulurator';
      $oReport->_rrows = 620;
      $oReport->_grid_id = 'dd_fields_' . substr(md5($modul . '|' . $dd), 0, 10);
      $oReport->_grid_cols = $this->dd_field_grid_cols();
      $oReport->_grid_layout = 'fitDataStretch';
      $oReport->_grid_read_url   = $this->build_url('dd', 'fields_read', $baseParams);
      $oReport->_grid_save_url   = $this->build_url('dd', 'fields_save', $baseParams);
      $oReport->_grid_insert_url = $this->build_url('dd', 'fields_insert', $baseParams);
      $oReport->_grid_delete_url = $this->build_url('dd', 'fields_delete', $baseParams);
      $oReport->add_rep('bar_title', 'DD-Felder: ' . $modul . '|' . $dd);
      $oReport->add_rep('bar_subtitle', $this->path_rel($this->dd_file_path($modul, $dd)));

      return $oReport->run();
   }

   /**
    * Liefert DD-Felder als JSON fuer den Feldeditor.
    *
    * @return void
    */
   private function run_dd_fields_read() {
      [$modul, $dd] = $this->dd_params_from_request();
      $model = $this->dd_field_model($modul, $dd);

      if (!$model) {
         $this->json_response(array('ok' => 0, 'rows' => array(), 'msg' => 'DD nicht gefunden'));
      }

      $rows = $this->dd_field_grid_rows($model['fields'] ?? array());
      $this->json_response(array(
         'ok'          => 1,
         'count'       => count($rows),
         'rows'        => $rows,
         'server_time' => date('Y-m-d H:i:s'),
      ));
   }

   /**
    * Speichert geaenderte DD-Felder aus dem Feldeditor.
    *
    * @return void
    */
   private function run_dd_fields_save() {
      [$modul, $dd] = $this->dd_params_from_request();
      $model = $this->dd_field_model($modul, $dd);
      $payload = $this->request_json();
      $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : array();

      if (!$model) {
         $this->json_response(array('ok' => 0, 'msg' => 'DD nicht gefunden'));
      }

      $fields = array_values((array)($model['fields'] ?? array()));
      foreach ($rows as $row) {
         $id = isset($row['id']) ? (int)$row['id'] : -1;
         if ($id < 0 || !isset($fields[$id])) {
            continue;
         }

         $fields[$id] = $this->normalize_dd_field_grid_post($row, $fields[$id]);
      }

      $message = '';
      if (!$this->validate_dd_fields($fields, $message)) {
         $this->json_response(array('ok' => 0, 'msg' => $message));
      }

      $ok = $this->save_dd_field_model($modul, $dd, $model, $fields);
      $this->json_response(array('ok' => $ok ? 1 : 0, 'success' => $ok ? true : false, 'rows' => $this->dd_field_grid_rows($fields)));
   }

   /**
    * Fuegt dem DD-Feldeditor ein neues Feld hinzu.
    *
    * @return void
    */
   private function run_dd_fields_insert() {
      [$modul, $dd] = $this->dd_params_from_request();
      $model = $this->dd_field_model($modul, $dd);

      if (!$model) {
         $this->json_response(array('ok' => 0, 'msg' => 'DD nicht gefunden'));
      }

      $fields = array_values((array)($model['fields'] ?? array()));
      $used = array();
      foreach ($fields as $field) {
         $used[strtolower((string)($field['name'] ?? ''))] = true;
      }

      $name = 'new_field';
      $i = 1;
      while (isset($used[strtolower($name)])) {
         $i++;
         $name = 'new_field_' . $i;
      }

      $oDD = dbx()->get_system_obj('dbxDD');
      $field = $oDD->normalize_dd_field(array(
         'name'   => $name,
         'type'   => 'varchar',
         'length' => '255',
         'index'  => '',
         'label'  => str_replace('_', ' ', ucfirst($name)),
         'rules'  => '',
         'tpl'    => 'text-label',
      ));

      $fields[] = $field;
      $ok = $this->save_dd_field_model($modul, $dd, $model, $fields);
      $this->json_response(array(
         'ok'      => $ok ? 1 : 0,
         'success' => $ok ? true : false,
         'row'     => $this->dd_field_grid_row($field, count($fields) - 1),
      ));
   }

   /**
    * Loescht ein Feld aus dem DD-Feldeditor.
    *
    * @return void
    */
   private function run_dd_fields_delete() {
      [$modul, $dd] = $this->dd_params_from_request();
      $model = $this->dd_field_model($modul, $dd);
      $payload = $this->request_json();

      if (!$model) {
         $this->json_response(array('ok' => 0, 'msg' => 'DD nicht gefunden'));
      }

      if (!array_key_exists('id', $payload)) {
         $this->json_response(array('ok' => 0, 'msg' => 'Feld-ID fehlt'));
      }

      $id = (int)$payload['id'];
      $fields = array_values((array)($model['fields'] ?? array()));
      if ($id < 0 || !isset($fields[$id])) {
         $this->json_response(array('ok' => 0, 'msg' => 'Feld-ID ungueltig'));
      }

      array_splice($fields, $id, 1);
      $ok = $this->save_dd_field_model($modul, $dd, $model, $fields);
      $this->json_response(array('ok' => $ok ? 1 : 0, 'success' => $ok ? true : false));
   }

   /**
    * Ermittelt Moduloptionen fuer Filter und Auswahlfelder.
    *
    * @return array
    */
   private function get_module_options() {
      $options = array();
      $base = str_replace('\\', '/', dbx()->get_base_dir()) . 'dbx/modules/*';

      foreach (glob($base, GLOB_ONLYDIR) as $dir) {
         $modul = basename($dir);
         if (is_dir($dir . '/dd')) {
            $options[$modul] = $modul;
         }
      }

      if (!isset($options['dbx'])) {
         $options['dbx'] = 'dbx';
      }

      ksort($options);
      return $options;
   }

   /**
    * Ermittelt alle DB-Server und Modul-DB-Dateien fuer Schema-Reports.
    *
    * @return array
    */
   private function get_server_options() {
      $options = array();
      $config = dbx()->get_config('dbx', 'db');
      $moduleFiles = $this->get_module_db_files();
      $moduleFileIndex = array();

      foreach ($moduleFiles as $server => $db) {
         $options[$server] = $db['label'];

         $real = realpath((string)($db['file'] ?? ''));
         if ($real) {
            $moduleFileIndex[strtolower(str_replace('\\', '/', $real))] = 1;
         }
      }

      if (is_array($config)) {
         foreach ($config as $server => $data) {
            if (isset($options[$server])) {
               continue;
            }

            $type = strtolower((string)($data['type'] ?? ''));
            $host = (string)($data['host'] ?? '');
            $name = (string)($data['dbname'] ?? ($data['name'] ?? ''));
            $isSqlite = ($type == 'sqlite' || $type == 'sqlite3' || preg_match('/\.(db3|sqlite|sqlite3)$/i', $name));

            if ($isSqlite && ($host !== '' || $name !== '')) {
               $file = dbx()->os_path($host . $name);
               $real = realpath($file);

               if ($real) {
                  $real = strtolower(str_replace('\\', '/', $real));
                  if (isset($moduleFileIndex[$real])) {
                     continue;
                  }
               }
            }

            $options[$server] = $server;
         }
      }

      return $options;
   }

   /**
    * Sammelt SQLite-Modul-DB-Dateien aus allen Modulverzeichnissen.
    *
    * @return array
    */


   private function get_module_db_files() {
      $records = array();
      $base = str_replace('\\', '/', dbx()->get_base_dir()) . 'dbx/modules/*/db/*';

      foreach (glob($base) as $file) {
         if (!is_file($file) || !preg_match('/\.(db3|sqlite|sqlite3)$/i', $file)) {
            continue;
         }

         $norm = str_replace('\\', '/', $file);
         if (!preg_match('#/dbx/modules/([^/]+)/db/([^/]+)$#', $norm, $match)) {
            continue;
         }

         $modul = $match[1];
         $name  = $match[2];
         $server = ($modul == 'dbx') ? $name : $modul . '|' . $name;

         $records[$server] = array(
            'server' => $server,
            'modul'  => $modul,
            'name'   => $name,
            'file'   => $norm,
            'path'   => $this->path_rel($norm),
            'label'  => $this->path_rel($norm),
         );
      }

      ksort($records);
      return $records;
   }

   /**
    * Loest einen Modul-DB-Servernamen auf eine Datei im Modul-DB-Verzeichnis auf.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function resolve_module_db_file($server) {
      $server = (string)$server;
      if (!preg_match('/\.(db3|sqlite|sqlite3)$/i', $server)) {
         return '';
      }

      $modul = 'dbx';
      $name  = $server;

      if (strpos($server, '|') !== false) {
         $parts = explode('|', $server, 2);
         $modul = trim($parts[0]) ?: 'dbx';
         $name  = trim($parts[1]);
      }

      $file = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/' . $modul . '/db/' . $name);
      if (file_exists($file)) {
         return str_replace('\\', '/', $file);
      }

      if ($modul != 'dbx') {
         $file = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/dbx/db/' . $name);
         if (file_exists($file)) {
            return str_replace('\\', '/', $file);
         }
      }

      return '';
   }

   /**
    * Erzeugt das sichtbare Datenbanklabel fuer Server- und Modul-DB-Eintraege.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function get_database_label($server) {
      $moduleFile = $this->resolve_module_db_file($server);
      if ($moduleFile) {
         return $this->path_rel($moduleFile);
      }

      $config = dbx()->get_config('dbx', 'db');

      if (is_array($config) && isset($config[$server])) {
         $db = $config[$server];
         $name = $db['dbname'] ?? ($db['name'] ?? '');
         $host = $db['host'] ?? '';
         $type = strtolower((string)($db['type'] ?? ''));

         if ($name && $host) {
            $full = dbx()->os_path($host . $name);
            if ($type == 'sqlite' || preg_match('/[\/\\\\]/', (string)$host)) {
               return $this->path_rel($full);
            }

            return rtrim((string)$host, '/') . '/' . $name;
         }

         if ($name) {
            return $name;
         }

         if ($host) {
            return $host;
         }
      }

      return '';
   }

   /**
    * Erzeugt den optionalen Pfadhinweis fuer dateibasierte Datenbanken.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function get_database_path_label($server) {
      $moduleFile = $this->resolve_module_db_file($server);
      if ($moduleFile) {
         return $this->path_rel($moduleFile);
      }

      $config = dbx()->get_config('dbx', 'db');
      if (!is_array($config) || !isset($config[$server])) {
         return '';
      }

      $db = $config[$server];
      $type = strtolower((string)($db['type'] ?? ''));
      $host = (string)($db['host'] ?? '');
      $name = (string)($db['dbname'] ?? ($db['name'] ?? ''));

      if ($type == 'sqlite' && ($host !== '' || $name !== '')) {
         return $this->path_rel(dbx()->os_path($host . $name));
      }

      return '';
   }

   /**
    * Ordnet einen Server dem echten Modul oder dem Pseudo-Modul sys zu.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @return string
    */

   private function get_server_module($server) {
      $server = (string)$server;
      static $moduleFiles = null;
      if ($moduleFiles === null) {
         $moduleFiles = $this->get_module_db_files();
      }

      if (isset($moduleFiles[$server])) {
         return $moduleFiles[$server]['modul'] ?? '';
      }

      $config = dbx()->get_config('dbx', 'db');
      if (is_array($config) && isset($config[$server])) {
         $db = $config[$server];
         $type = strtolower((string)($db['type'] ?? ''));
         $host = (string)($db['host'] ?? '');
         $name = (string)($db['dbname'] ?? ($db['name'] ?? ''));
         $isSqlite = ($type == 'sqlite' || $type == 'sqlite3' || preg_match('/\.(db3|sqlite|sqlite3)$/i', $name));

         if ($isSqlite && ($host !== '' || $name !== '')) {
            $configFile = realpath(dbx()->os_path($host . $name));

            if ($configFile) {
               $configFile = strtolower(str_replace('\\', '/', $configFile));

               foreach ($moduleFiles as $moduleDb) {
                  $moduleFile = realpath((string)($moduleDb['file'] ?? ''));

                  if ($moduleFile && strtolower(str_replace('\\', '/', $moduleFile)) === $configFile) {
                     return $moduleDb['modul'] ?? '';
                  }
               }
            }
         }

         return 'sys';
      }

      return '';
   }

   /**
    * Erzeugt eine kompakte Feldtyp-Anzeige aus DD- oder DB-Feldmetadaten.
    *
    * @param string $field Eingabeparameter fuer diese Methode.
    * @return string
    */

   private function field_type_label($field) {
      $type = trim((string)($field['type'] ?? ''));
      $len  = trim((string)($field['length'] ?? ''));
      $idx  = trim((string)($field['index'] ?? ''));

      $label = $type;
      if ($len !== '') {
         $label .= '(' . $len . ')';
      }
      if ($idx !== '') {
         $label .= ' / ' . $idx;
      }

      return $label;
   }

   /**
    * Erzeugt eine Feldanzahl mit Standard-title und vorbereitetem HTML-Tooltip.
    *
    * @param array $fields Eingabeparameter fuer diese Methode.
    * @param string $title Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function field_tooltip_count($fields, $title = '') {
      $texts = $this->schema_texts();
      if ($title === '') {
         $title = $texts->get_fd_message('fields');
      }
      $fields = array_values((array)$fields);
      $count = count($fields);

      $plain = $title . ': ' . $count;
      $html = '<div class="dbx-tooltip-fields">';
      $html .= '<div class="dbx-tooltip-title">' . $this->esc($title) . '</div>';

      if ($count <= 0) {
         $plain .= "\n" . $texts->get_fd_message('no_fields');
         $html .= '<div class="dbx-tooltip-empty">' . $this->esc($texts->get_fd_message('no_fields')) . '</div>';
      } else {
         $html .= '<table class="dbx-tooltip-table">';
         foreach ($fields as $field) {
            $name = trim((string)($field['name'] ?? ''));
            if ($name === '') {
               continue;
            }

            $type = $this->field_type_label($field);
            $plain .= "\n" . $name . ': ' . $type;

            $html .= '<tr>';
            $html .= '<td><code>' . $this->esc($name) . '</code></td>';
            $html .= '<td>' . $this->esc($type) . '</td>';
            $html .= '</tr>';
         }
         $html .= '</table>';
      }

      $html .= '</div>';

      return '<span class="dbx-schema-field-count badge bg-light text-dark border" '
         . 'title="' . $this->esc($plain) . '" '
         . 'data-dbx-tooltip="' . $this->esc(str_replace('"', "'", $html)) . '">'
         . $this->esc($count)
         . '</span>';
   }

   /**
    * Erzeugt das Status-Badge fuer Schema-Mapping-Zeilen.
    *
    * @param string $status Eingabeparameter fuer diese Methode.
    * @return string
    */

   private function mapping_status_label($status) {
      $texts = $this->schema_texts();
      $map = array(
         'exact' => array(
            $texts->get_fd_message('mapping_status_direct'),
            'success',
         ),
         'mapped' => array(
            $texts->get_fd_message('mapping_status_mapped'),
            'primary',
         ),
         'type_conflict' => array(
            $texts->get_fd_message('mapping_status_type_check'),
            'warning text-dark',
         ),
         'new' => array(
            $texts->get_fd_message('mapping_status_new'),
            'secondary',
         ),
      );

      $item = $map[$status] ?? array($status, 'secondary');
      return '<span class="badge bg-' . $this->esc($item[1]) . '">' . $this->esc($item[0]) . '</span>';
   }

   /**
    * Erzeugt die menschenlesbare Bezeichnung einer Mapping-Art.
    *
    * @param string $kind Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function mapping_kind_label($kind) {
      $map = array(
         'dd_to_db' => 'DD -> DB',
         'db_to_dd' => 'DB -> DD',
         'transfer' => 'Transfer',
      );

      return $map[$kind] ?? 'Schema-Mapping';
   }

   /**
    * Liest und normalisiert gepostete Schema-Mapping-Zuordnungen.
    *
    * @param array $model Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function posted_mapping_from_model($model) {
      $posted = $_POST['schema_map'] ?? array();
      if (!is_array($posted)) {
         return array();
      }

      $mapping = array();
      foreach ($posted as $target => $source) {
         $target = trim((string)$target);
         $source = trim((string)$source);

         if ($target === '' || $source === '') {
            continue;
         }

         $mapping[$source] = $target;
      }

      $oDD = dbx()->get_system_obj('dbxDD');
      return $oDD->normalize_schema_mapping(
         $mapping,
         $model['source_fields'] ?? array(),
         $model['target_fields'] ?? array()
      );
   }

   /**
    * Erzeugt die Select-Optionen fuer Quellfelder im Mapping-Editor.
    *
    * @param array $sources Eingabeparameter fuer diese Methode.
    * @param string $selected Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function source_options_html($sources, $selected) {
      $html = '<option value=""></option>';

      foreach ($sources as $source) {
         $name = (string)($source['name'] ?? '');
         if ($name === '') {
            continue;
         }

         $sel = (strcasecmp($name, (string)$selected) === 0) ? ' selected' : '';
         $meta = $this->field_type_label($source);
         $html .= '<option value="' . $this->esc($name) . '"' . $sel . '>'
              . $this->esc($name . ($meta ? ' - ' . $meta : ''))
              . '</option>';
      }

      return $html;
   }

   /**
    * Rendert die draggable Quellfelder des Mapping-Editors.
    *
    * @param array $sources Eingabeparameter fuer diese Methode.
    * @param array $mapping Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function render_source_items($sources, $mapping) {
      $texts = $this->schema_texts();
      $mapped = array();
      foreach ($mapping as $source => $target) {
         $mapped[strtolower((string)$source)] = $target;
      }

      $html = '';
      foreach ($sources as $source) {
         $name = (string)($source['name'] ?? '');
         if ($name === '') {
            continue;
         }

         $target = $mapped[strtolower($name)] ?? '';
         $classes = 'dbx-mapping-source';
         if ($target) {
            $classes .= ' is-used';
         }

         $html .= '<button type="button" class="' . $classes . '" draggable="true" '
              . 'data-mapping-source="' . $this->esc($name) . '" '
              . 'title="' . $this->esc($name) . '">'
              . '<span class="dbx-mapping-field-name">' . $this->esc($name) . '</span>'
              . '<span class="dbx-mapping-field-meta">' . $this->esc($this->field_type_label($source)) . '</span>'
              . '</button>';
      }

      if ($html === '') {
         $html = '<div class="dbx-mapping-empty">'
            . $this->esc($texts->get_fd_message('mapping_no_source_fields'))
            . '</div>';
      }

      return $html;
   }

   /**
    * Rendert die Zielzeilen des Mapping-Editors.
    *
    * @param array $model Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function render_mapping_rows($model) {
      $texts = $this->schema_texts();
      $sources = $model['source_fields'] ?? array();
      $html = '';

      foreach (($model['target_rows'] ?? array()) as $row) {
         $target = $row['target'] ?? array();
         $targetName = (string)($row['target_name'] ?? ($target['name'] ?? ''));
         if ($targetName === '') {
            continue;
         }

         $sourceName = (string)($row['source_name'] ?? '');
         $status = (string)($row['status'] ?? 'new');

         $html .= '<div class="dbx-mapping-target" data-mapping-target="' . $this->esc($targetName) . '" '
              . 'data-mapping-status="' . $this->esc($status) . '">';
         $html .= '<div class="dbx-mapping-target-main">';
         $html .= '<div class="dbx-mapping-target-title">';
         $html .= '<span class="dbx-mapping-field-name">' . $this->esc($targetName) . '</span>';
         $html .= $this->mapping_status_label($status);
         $html .= '</div>';
         $html .= '<span class="dbx-mapping-field-meta">' . $this->esc($this->field_type_label($target)) . '</span>';
         $html .= '</div>';

         $html .= '<div class="dbx-mapping-drop" data-mapping-drop="' . $this->esc($targetName) . '">';
         $html .= '<select class="form-select form-select-sm" name="schema_map[' . $this->esc($targetName) . ']" '
              . 'data-mapping-select data-target="' . $this->esc($targetName) . '" '
              . 'data-auto-source="' . $this->esc($sourceName) . '">';
         $html .= $this->source_options_html($sources, $sourceName);
         $html .= '</select>';
         $html .= '<button type="button" class="btn btn-sm btn-outline-secondary" data-mapping-clear-row title="'
              . $this->esc($texts->get_fd_message('mapping_clear_assignment')) . '">'
              . '<i class="bi bi-x-lg"></i></button>';
         $html .= '</div>';
         $html .= '</div>';
      }

      if ($html === '') {
         $html = '<div class="dbx-mapping-empty">'
            . $this->esc($texts->get_fd_message('mapping_no_target_fields'))
            . '</div>';
      }

      return $html;
   }

   /**
    * Rendert das komplette Mapping-Board.
    *
    * @param array $model Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function render_mapping_board($model) {
      $texts = $this->schema_texts();
      $kind = (string)($model['kind'] ?? 'dd_to_db');
      $mapping = $model['mapping'] ?? array();
      $sourceCount = count($model['source_fields'] ?? array());
      $targetCount = count($model['target_fields'] ?? array());
      $mappedCount = count($mapping);
      $file = (string)($model['file'] ?? '');
      $fileLabel = $file !== '' ? $this->path_rel($file) : '';
      $updated = (string)($model['updated_at'] ?? '');

      $html = '<div class="dbx-schema-mapping" data-dbx="lib=mapping" data-mapping-root="1">';

      $html .= '<div class="dbx-mapping-head">';
      $html .= '<div>';
      $html .= '<h3>' . $this->esc($this->mapping_kind_label($kind)) . '</h3>';
      $html .= '<div class="dbx-mapping-subtitle">'
           . '<span>' . $this->esc($model['source_label'] ?? '') . '</span>'
           . '<i class="bi bi-arrow-right"></i>'
           . '<span>' . $this->esc($model['target_label'] ?? '') . '</span>'
           . '</div>';
      $html .= '</div>';

      $html .= '<div class="dbx-mapping-tools">';
      $html .= '<span class="badge bg-secondary" data-mapping-count="mapped">' . $this->esc($mappedCount . ' / ' . $targetCount) . '</span>';
      $html .= '<button type="button" class="btn btn-outline-primary" data-mapping-action="auto" title="'
         . $this->esc($texts->get_fd_message('mapping_auto'))
         . '"><i class="bi bi-magic"></i></button>';
      $html .= '<button type="button" class="btn btn-outline-secondary" data-mapping-action="clear" title="'
         . $this->esc($texts->get_fd_message('mapping_clear'))
         . '"><i class="bi bi-eraser"></i></button>';
      $html .= '</div>';
      $html .= '</div>';

      $html .= '<div class="dbx-mapping-meta">';
      $html .= '<span>' . $this->esc($texts->get_fd_message('mapping_source'))
         . ': ' . $this->esc((string)$sourceCount) . '</span>';
      $html .= '<span>' . $this->esc($texts->get_fd_message('mapping_target'))
         . ': ' . $this->esc((string)$targetCount) . '</span>';
      if ($updated !== '') {
         $html .= '<span>' . $this->esc($texts->get_fd_message('mapping_saved_at'))
            . ': ' . $this->esc($updated) . '</span>';
      }
      if ($fileLabel !== '') {
         $html .= '<span class="dbx-mapping-file">' . $this->esc($fileLabel) . '</span>';
      }
      $html .= '</div>';

      $html .= '<div class="dbx-mapping-workbench">';
      $html .= '<section class="dbx-mapping-panel dbx-mapping-panel-source">';
      $html .= '<header><span>'
         . $this->esc($texts->get_fd_message('mapping_source'))
         . '</span><span class="badge bg-light text-dark">'
         . $this->esc((string)$sourceCount) . '</span></header>';
      $html .= '<div class="dbx-mapping-source-list">' . $this->render_source_items($model['source_fields'] ?? array(), $mapping) . '</div>';
      $html .= '</section>';

      $html .= '<div class="dbx-mapping-canvas" aria-hidden="true"><svg data-mapping-lines></svg></div>';

      $html .= '<section class="dbx-mapping-panel dbx-mapping-panel-target">';
      $html .= '<header><span>'
         . $this->esc($texts->get_fd_message('mapping_target'))
         . '</span><span class="badge bg-light text-dark">'
         . $this->esc((string)$targetCount) . '</span></header>';
      $html .= '<div class="dbx-mapping-target-list">' . $this->render_mapping_rows($model) . '</div>';
      $html .= '</section>';
      $html .= '</div>';

      $html .= '</div>';

      return $html;
   }

   /**
    * Erzeugt das Sync-Status-Badge aus einem Sync-Plan.
    *
    * @param array $plan Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function sync_label_from_plan($plan) {
      $texts = $this->schema_texts();
      if (!is_array($plan) || empty($plan['ok'])) {
         return $this->badge($texts->get_fd_message('status_dd_error'), 'danger');
      }

      if (empty($plan['table_exists'])) {
         return $this->badge($texts->get_fd_message('status_db_missing'), 'warning');
      }

      $adds = count($plan['add_fields'] ?? array());
      $idx  = count($plan['add_indexes'] ?? array());
      $miss = count($plan['missing_in_dd'] ?? array());
      $conf = count($plan['type_conflicts'] ?? array());

      if (!empty($plan['rebuild_needed'])) {
         return $this->badge($texts->get_fd_message('status_rebuild'), 'danger');
      }

      if ($adds || $idx) {
         return $this->badge($texts->get_fd_message('status_sync_open'), 'warning');
      }

      return $this->badge($texts->get_fd_message('status_synced'), 'success');
   }

   /**
    * Erzeugt den kompakten Sync-Detailtext aus einem Sync-Plan.
    *
    * @param array $plan Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function sync_detail_from_plan($plan) {
      if (!is_array($plan) || empty($plan['ok']) || empty($plan['table_exists'])) {
         return '';
      }

      $adds = count($plan['add_fields'] ?? array());
      $idx  = count($plan['add_indexes'] ?? array());
      $miss = count($plan['missing_in_dd'] ?? array());
      $conf = count($plan['type_conflicts'] ?? array());

      if (!empty($plan['rebuild_needed'])) {
         return $this->esc(
            $this->schema_texts()->format_fd_message('detail_type', array('count' => $conf))
            . ' / '
            . $this->schema_texts()->format_fd_message('detail_extra', array('count' => $miss))
         );
      }

      if ($adds || $idx) {
         return $this->esc(
            $this->schema_texts()->format_fd_message('detail_field', array('count' => $adds))
            . ' / '
            . $this->schema_texts()->format_fd_message('detail_index', array('count' => $idx))
         );
      }

      return '';
   }

   /**
    * Liest den DD-nach-DB-Sync-Plan fuer eine DD-Datei.
    *
    * @param string $modul Eingabeparameter fuer diese Methode.
    * @param string $dd Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function get_sync_plan($modul, $dd) {
      $oDD = dbx()->get_system_obj('dbxDD');
      return $oDD->sync_dd_to_db($modul, $dd, 'plan');
   }

   /**
    * Liest alle DD-Dateien aller Module und baut die DD-Report-Zeilen.
    *
    * @return array
    */
   private function get_dd_records() {
      $records = array();
      $oDD = dbx()->get_system_obj('dbxDD');
      $base = str_replace('\\', '/', dbx()->get_base_dir()) . 'dbx/modules/*/dd/*.dd.php';

      foreach (glob($base) as $file) {
         $norm = str_replace('\\', '/', $file);

         if (!preg_match('#/dbx/modules/([^/]+)/dd/([^/]+)\.dd\.php$#', $norm, $match)) {
            continue;
         }

         $modul = $match[1];
         $dd = $match[2];
         if ($dd === 'new') {
            continue;
         }

         $ddRef = $this->dd_ref($modul, $dd);
         $model = $oDD->get_dd_model($ddRef);
         if (!$model) {
            continue;
         }

         $server = $model['table']['server'] ?? '';
         $table  = $model['table']['table'] ?? '';
         if (str_starts_with(strtolower((string)$table), 'sqlite_')) {
            continue;
         }
         $exists = ($server && $table) ? $oDD->get_table_exist($server, $table) : 0;
         $count = $exists ? $oDD->count($ddRef) : '-';
         $plan = $this->get_sync_plan($modul, $dd);
         $actions = $this->dd_actions($modul, $dd, $server, $table, $plan);

         $ddFields = array_values((array)($model['fields'] ?? array()));
         $dbFields = ($server && $table && $exists) ? $oDD->get_db_fields($server, $table) : array();

         $records[] = array(
            'rid'       => $modul . '|' . $dd,
            'modul'     => $modul,
            'dd'        => $dd,
            'path'      => $this->path_rel($norm),
            'server'    => $server,
            'database'  => $this->get_database_label($server),
            'table'     => $table,
            'dd_fields' => $this->field_tooltip_count($ddFields, $this->schema_texts()->get_fd_message('dd_fields')),
            'db_fields' => $this->field_tooltip_count($dbFields, $this->schema_texts()->get_fd_message('db_fields')),
            'count'     => $count,
            'sync'      => $this->sync_label_from_plan($plan),
            'sync_info' => $this->sync_detail_from_plan($plan),
            'act_sync'  => $actions['sync'] ?? '',
            'act_map'   => $actions['map'] ?? '',
            'act_move'  => $actions['transfer'] ?? '',
            'act_flds'  => $actions['fields'] ?? '',
         );
      }

      usort($records, function($a, $b) {
         return strcmp($a['modul'] . '|' . $a['dd'], $b['modul'] . '|' . $b['dd']);
      });

      return $records;
   }

   /**
    * Erzeugt Aliasnamen fuer DB-Server, damit DDs und DB-Dateien korrekt zusammenfinden.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function db_server_aliases($server) {
      $aliases = array();
      $server = trim((string)$server);

      if ($server === '') {
         return array();
      }

      $add = function($value) use (&$aliases) {
         $value = trim(str_replace('\\', '/', (string)$value));
         if ($value !== '') {
            $aliases[$value] = true;
         }
      };

      $add($server);

      $norm = str_replace('\\', '/', $server);
      if ($norm !== $server) {
         $add($norm);
      }

      if (strpos($norm, '/') !== false) {
         $add(basename($norm));
      }

      if (strpos($server, '|') !== false) {
         $parts = explode('|', $server, 2);
         $modul = trim((string)($parts[0] ?? ''));
         $name  = trim((string)($parts[1] ?? ''));

         if ($name !== '') {
            $add($name);
         }

         if ($modul !== '' && $name !== '') {
            $add($modul . '|' . $name);
            $add($modul . '/db/' . $name);
         }
      }

      $moduleFile = $this->resolve_module_db_file($server);
      if ($moduleFile) {
         $file = str_replace('\\', '/', $moduleFile);
         $add($file);
         $add($this->path_rel($file));
         $add(basename($file));

         if (preg_match('#/dbx/modules/([^/]+)/db/([^/]+)$#', $file, $match)) {
            $add($match[2]);
            $add($match[1] . '|' . $match[2]);
            $add($match[1] . '/db/' . $match[2]);
         }
      }

      return array_keys($aliases);
   }

   /**
    * Indexiert DD-Records nach Server-/Tabellen-Aliassen fuer DB-Zuordnung.
    *
    * @param array $ddRecords Eingabeparameter fuer diese Methode.
    * @return array
    */


   private function get_dd_index_by_db($ddRecords) {
      $index = array();

      foreach ($ddRecords as $record) {
         $server = $record['server'] ?? '';
         $table  = $record['table'] ?? '';

         if (!$server || !$table) {
            continue;
         }

         $rid = (string)($record['rid'] ?? '');
         if ($rid === '') {
            $rid = (string)($record['modul'] ?? '') . '|' . (string)($record['dd'] ?? '');
         }

         foreach ($this->db_server_aliases($server) as $alias) {
            $key = $this->norm_key($alias, $table);
            if (!isset($index[$key])) {
               $index[$key] = array();
            }

            $index[$key][$rid] = $record;
         }
      }

      foreach ($index as $key => $records) {
         $index[$key] = array_values($records);
      }

      return $index;
   }

   /**
    * Sucht alle DD-Records, die zu einem konkreten DB-Server und einer Tabelle passen.
    *
    * @param array $ddIndex Eingabeparameter fuer diese Methode.
    * @param string $server Eingabeparameter fuer diese Methode.
    * @param string $table Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function get_dd_records_for_db($ddIndex, $server, $table) {
      $records = array();

      if (!$server || !$table) {
         return array();
      }

      foreach ($this->db_server_aliases($server) as $alias) {
         $key = $this->norm_key($alias, $table);
         if (!isset($ddIndex[$key])) {
            continue;
         }

         foreach ($ddIndex[$key] as $record) {
            $rid = (string)($record['rid'] ?? '');
            if ($rid === '') {
               $rid = (string)($record['modul'] ?? '') . '|' . (string)($record['dd'] ?? '');
            }

            $records[$rid] = $record;
         }
      }

      return array_values($records);
   }

   /**
    * Erzeugt Aktionslinks fuer DD-Report-Zeilen.
    *
    * @param string $modul Eingabeparameter fuer diese Methode.
    * @param string $dd Eingabeparameter fuer diese Methode.
    * @param string $server Eingabeparameter fuer diese Methode.
    * @param string $table Eingabeparameter fuer diese Methode.
    * @param array $plan Eingabeparameter fuer diese Methode.
    * @return array
    */


   private function dd_actions($modul, $dd, $server, $table, $plan) {
      $actions = array(
         'sync'     => '',
         'map'      => '',
         'transfer' => '',
         'fields'   => '',
      );
      $mode = (!empty($plan['rebuild_needed'])) ? 'force' : 'apply';

      $syncUrl = $this->build_url('dd', 'sync_dd_to_db', array(
         'modul' => $modul,
         'dd'    => $dd,
         'mode'  => $mode,
         'reset' => 1,
      ));
      $actions['sync'] = $this->openwin($syncUrl, 'bi bi-arrow-repeat', $this->schema_texts()->get_fd_message('action_dd_to_db'), 1000, 700);

      $mapUrl = $this->build_url('dd', 'mapping', array(
         'kind'  => 'dd_to_db',
         'modul' => $modul,
         'dd'    => $dd,
      ));
      $actions['map'] = $this->openwin($mapUrl, 'bi bi-diagram-3', $this->schema_texts()->get_fd_message('action_mapping'), 1280, 880);

      if ($server && $table) {
         $transferUrl = $this->build_url('dd', 'transfer', array(
            'source_server' => $server,
            'source_table'  => $table,
         ));
         $actions['transfer'] = $this->openwin($transferUrl, 'bi bi-box-arrow-right', $this->schema_texts()->get_fd_message('action_transfer'), 980, 720);
      }

      $fieldsUrl = $this->build_url('dd', 'fields', array(
         'modul' => $modul,
         'dd'    => $dd,
      ));
      $actions['fields'] = $this->openwin($fieldsUrl, 'bi bi-list-columns', $this->schema_texts()->get_fd_message('action_dd_fields'), 1400, 860);

      return $actions;
   }

   /**
    * Erzeugt Aktionslinks fuer DB-Report-Zeilen.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @param string $table Eingabeparameter fuer diese Methode.
    * @param array $dds Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function db_actions($server, $table, $dds) {
      $actions = array(
         'sync'          => '',
         'sync_dd_to_db' => '',
         'map'           => '',
         'transfer'      => '',
      );

      if (is_array($dds) && count($dds)) {
         $first = $dds[0];
         $plan = $this->get_sync_plan($first['modul'], $first['dd']);
         $ddToDbMode = (!empty($plan['rebuild_needed'])) ? 'force' : 'apply';

         $syncUrl = $this->build_url('db', 'sync_db_to_dd', array(
            'server' => $server,
            'table'  => $table,
            'modul'  => $first['modul'],
            'dd'     => $first['dd'],
            'mode'   => 'merge',
            'reset'  => 1,
         ));
         $actions['sync'] = $this->openwin($syncUrl, 'bi bi-arrow-down-up', $this->schema_texts()->get_fd_message('action_db_to_dd'), 1000, 700);

         $ddToDbUrl = $this->build_url('db', 'sync_dd_to_db', array(
            'modul' => $first['modul'],
            'dd'    => $first['dd'],
            'mode'  => $ddToDbMode,
            'reset' => 1,
         ));
         $actions['sync_dd_to_db'] = $this->openwin($ddToDbUrl, 'bi bi-arrow-repeat', $this->schema_texts()->get_fd_message('action_db_from_dd'), 1000, 700);

         $mapUrl = $this->build_url('db', 'mapping', array(
            'kind'   => 'db_to_dd',
            'server' => $server,
            'table'  => $table,
            'modul'  => $first['modul'],
            'dd'     => $first['dd'],
         ));
         $actions['map'] = $this->openwin($mapUrl, 'bi bi-diagram-3', $this->schema_texts()->get_fd_message('action_mapping'), 1280, 880);
      } else {
         $syncUrl = $this->build_url('db', 'sync_db_to_dd', array(
            'server' => $server,
            'table'  => $table,
         ));
         $actions['sync'] = $this->openwin($syncUrl, 'bi bi-file-earmark-plus', $this->schema_texts()->get_fd_message('action_create_dd'), 980, 720);
      }

      $transferUrl = $this->build_url('db', 'transfer', array(
         'source_server' => $server,
         'source_table'  => $table,
      ));
      $actions['transfer'] = $this->openwin($transferUrl, 'bi bi-box-arrow-right', $this->schema_texts()->get_fd_message('action_transfer'), 980, 720);

      return $actions;
   }

   /**
    * Liest alle DB-Server, Tabellen und DD-Zuordnungen fuer den DB-Report.
    *
    * @param array $ddRecords Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function get_db_records($ddRecords) {
      $records = array();
      $oDB = dbx()->get_system_obj('dbxDB');
      $oDD = dbx()->get_system_obj('dbxDD');
      $ddIndex = $this->get_dd_index_by_db($ddRecords);
      $servers = $this->get_server_options();

      foreach ($servers as $server => $label) {
         $tables = $oDB->get_db_tables($server);
         if (!is_array($tables)) {
            $tables = array();
         }

         $hasTables = false;

         foreach ($tables as $tableRec) {
            $table = $tableRec['name'] ?? '';
            if (!$table) {
               continue;
            }

            $hasTables = true;

            $dds = $this->get_dd_records_for_db($ddIndex, $server, $table);
            $ddLabels = array();
            $sync = $this->badge($this->schema_texts()->get_fd_message('no_dd'), 'secondary');
            $syncInfo = '';

            foreach ($dds as $ddRec) {
               $ddLabels[] = $this->esc($ddRec['modul'] . '|' . $ddRec['dd']);
            }

            if ($dds) {
               $sync = $dds[0]['sync'];
               $syncInfo = $dds[0]['sync_info'] ?? '';
            }

            $dbFields = $oDD->get_db_fields($server, $table);
            $ddFields = array();

            if ($dds) {
               $first = $dds[0];
               $model = $oDD->get_dd_model($this->dd_ref($first['modul'], $first['dd']));
               $ddFields = array_values((array)($model['fields'] ?? array()));
            }

            $actions = $this->db_actions($server, $table, $dds);

            $records[] = array(
               'rid'       => $this->encode_db_rid($server, $table),
               'modul'     => $this->get_server_module($server),
               'server'    => $server,
               'database'  => $this->get_database_label($server),
               'path'      => $this->get_database_path_label($server),
               'table'     => $table,
               'db_fields' => $this->field_tooltip_count($dbFields, $this->schema_texts()->get_fd_message('db_fields')),
               'dd_fields' => $this->field_tooltip_count($ddFields, $this->schema_texts()->get_fd_message('dd_fields')),
               'count'     => $tableRec['count'] ?? '-',
               'dd'        => implode('<br>', $ddLabels),
               'sync'      => $sync,
               'sync_info' => $syncInfo,
               'act_sync'  => $actions['sync'] ?? '',
               'act_dd_sync' => $actions['sync_dd_to_db'] ?? '',
               'act_map'   => $actions['map'] ?? '',
               'act_move'  => $actions['transfer'] ?? '',
               'dd_edit_modul' => $dds[0]['modul'] ?? '',
               'dd_edit_dd'    => $dds[0]['dd'] ?? '',
            );
         }

         if (!$hasTables) {
            $records[] = array(
               'rid'       => $this->encode_db_rid($server, ''),
               'modul'     => $this->get_server_module($server),
               'server'    => $server,
               'database'  => $this->get_database_label($server),
               'path'      => $this->get_database_path_label($server),
               'table'     => $this->badge($this->schema_texts()->get_fd_message('no_tables'), 'secondary'),
               'db_fields' => $this->field_tooltip_count(array(), $this->schema_texts()->get_fd_message('db_fields')),
               'dd_fields' => $this->field_tooltip_count(array(), $this->schema_texts()->get_fd_message('dd_fields')),
               'count'     => '-',
               'dd'        => '',
               'sync'      => $this->badge($this->schema_texts()->get_fd_message('no_tables'), 'secondary'),
               'sync_info' => '',
               'act_sync'  => '',
               'act_dd_sync' => '',
               'act_map'   => '',
               'act_move'  => '',
               'dd_edit_modul' => '',
               'dd_edit_dd'    => '',
            );
         }
      }

      usort($records, function($a, $b) {
         return strcmp(strip_tags($a['modul'] . '|' . $a['server'] . '|' . $a['table']), strip_tags($b['modul'] . '|' . $b['server'] . '|' . $b['table']));
      });

      return $records;
   }

   /**
    * Erstellt ein JSON-Backup einer DB-Tabelle inklusive Feldern, Indizes und Daten.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @param string $table Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function backup_table($server, $table) {
      $texts = $this->schema_texts();
      $oDB = dbx()->get_system_obj('dbxDB');
      $oDD = dbx()->get_system_obj('dbxDD');

      if (!$server || !$table || !$oDD->get_table_exist($server, $table)) {
         return array('ok' => 0, 'msg' => $texts->get_fd_message('backup_table_not_found'));
      }

      $fields = $oDD->get_db_fields($server, $table);
      if (!$fields) {
         return array('ok' => 0, 'msg' => $texts->get_fd_message('backup_fields_read_error'));
      }

      $indexes = $oDD->get_db_indexes($server, $table);
      $rows = $oDB->rawQuery($server, 'SELECT * FROM ' . $this->quote_db_ident($server, $table));
      if (!is_array($rows)) {
         return array('ok' => 0, 'msg' => $texts->get_fd_message('backup_data_read_error'));
      }

      $created = date('Ymd-His');
      $file = $created . '__' . $this->backup_name_part($server) . '__' . $this->backup_name_part($table) . '.json';
      $path = $this->backup_dir() . $file;
      $tmp = $path . '.tmp';

      $payload = array(
         'version' => 1,
         'created_at' => date('Y-m-d H:i:s'),
         'server' => $server,
         'table' => $table,
         'db_type' => $oDB->get_db_type($server),
         'count' => count($rows),
         'fields' => $fields,
         'indexes' => $indexes,
         'rows' => $rows,
      );

      $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
      if ($json === false || file_put_contents($tmp, $json) === false || !@rename($tmp, $path)) {
         @unlink($tmp);
         return array('ok' => 0, 'msg' => $texts->get_fd_message('backup_write_error'));
      }

      return array('ok' => 1, 'file' => $file, 'path' => $path, 'count' => count($rows));
   }

   /**
    * Liest Metadaten und Inhalt einer Backup-Datei.
    *
    * @param string $file Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function backup_meta($file) {
      $path = $this->backup_file_path($file);
      if (!$path) {
         return array();
      }

      $raw = file_get_contents($path);
      $data = $raw !== false ? json_decode($raw, true) : null;
      if (!is_array($data)) {
         return array();
      }

      $data['_file'] = basename($path);
      $data['_path'] = $path;
      return $data;
   }

   /**
    * Liefert alle Backups fuer Server und Tabelle.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @param string $table Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function table_backups($server, $table) {
      $items = array();
      foreach (glob($this->backup_dir() . '*.json') as $file) {
         $meta = $this->backup_meta(basename($file));
         if (($meta['server'] ?? '') === $server && ($meta['table'] ?? '') === $table) {
            $items[] = $meta;
         }
      }

      usort($items, function($a, $b) {
         return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
      });

      return $items;
   }

   /**
    * Liefert das neueste Backup fuer Server und Tabelle.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @param string $table Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function latest_backup($server, $table) {
      $items = $this->table_backups($server, $table);
      return $items[0] ?? array();
   }

   /**
    * Erzeugt die Anzeige des letzten Backups fuer eine Tabelle.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @param string $table Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function backup_label($server, $table) {
      $texts = $this->schema_texts();
      $meta = $this->latest_backup($server, $table);
      if (!$meta) {
         return $this->badge($texts->get_fd_message('no_backup'), 'secondary');
      }

      $created = (string)($meta['created_at'] ?? '');
      $count = (int)($meta['count'] ?? 0);
      return $this->esc(
         $created . ' / ' . $texts->format_fd_message('record_count_short', array('count' => $count))
      );
   }

   /**
    * Stellt eine Tabelle aus einer Backup-Datei wieder her.
    *
    * @param string $file Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function restore_table_from_backup($file) {
      $texts = $this->schema_texts();
      $meta = $this->backup_meta($file);
      if (!$meta) {
         return array('ok' => 0, 'msg' => $texts->get_fd_message('backup_invalid_file'));
      }

      $server = (string)($meta['server'] ?? '');
      $table = (string)($meta['table'] ?? '');
      $fields = is_array($meta['fields'] ?? null) ? $meta['fields'] : array();
      $indexes = is_array($meta['indexes'] ?? null) ? $meta['indexes'] : array();
      $rows = is_array($meta['rows'] ?? null) ? $meta['rows'] : array();

      if (!$server || !$table || !$fields) {
         return array('ok' => 0, 'msg' => $texts->get_fd_message('backup_incomplete_metadata'));
      }

      $oDB = dbx()->get_system_obj('dbxDB');
      $oDD = dbx()->get_system_obj('dbxDD');

      $oDD->drop_db_tab($server, $table);
      if (!$oDD->create_db_tab_from_fields($server, $table, $fields, $indexes)) {
         return array('ok' => 0, 'msg' => $texts->get_fd_message('restore_create_table_error'));
      }

      if (!$rows) {
         return array('ok' => 1, 'count' => 0, 'file' => basename((string)$file));
      }

      $names = array();
      foreach ($fields as $field) {
         $name = (string)($field['name'] ?? '');
         if ($name !== '') {
            $names[] = $name;
         }
      }

      if (!$names) {
         return array('ok' => 0, 'msg' => $texts->get_fd_message('restore_no_target_fields'));
      }

      $qNames = array_map(function($name) use ($server) {
         return $this->quote_db_ident($server, $name);
      }, $names);

      foreach ($rows as $row) {
         $values = array();
         foreach ($names as $name) {
            $values[] = $this->sql_db_value($server, is_array($row) && array_key_exists($name, $row) ? $row[$name] : null);
         }

         $sql = 'INSERT INTO ' . $this->quote_db_ident($server, $table)
              . ' (' . implode(',', $qNames) . ') VALUES (' . implode(',', $values) . ')';
         $ok = $oDB->rawQuery($server, $sql);
         if (!is_array($ok) && (int)$ok === 0 && $oDB->get_error_status() !== '') {
            return array('ok' => 0, 'msg' => 'Daten konnten nicht eingetragen werden.');
         }
      }

      return array('ok' => 1, 'count' => count($rows), 'file' => basename((string)$file));
   }

   /**
    * Filtert Report-Zeilen anhand eines Suchbegriffs.
    *
    * @param array $rows Eingabeparameter fuer diese Methode.
    * @param string $search Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function filter_rows($rows, $search) {
      $search = trim((string) $search);
      if ($search === '') {
         return $rows;
      }

      $filtered = array();
      foreach ($rows as $row) {
         $haystack = strtolower(strip_tags(implode(' ', $row)));
         if (strpos($haystack, strtolower($search)) !== false) {
            $filtered[] = $row;
         }
      }

      return $filtered;
   }

   /**
    * Prueft, ob echte Config-DB-Server fuer den sys-Filter vorhanden sind.
    *
    * @return bool
    */
   private function has_config_db_servers() {
      $config = dbx()->get_config('dbx', 'db');
      if (!is_array($config) || count($config) <= 0) {
         return false;
      }

      $moduleFiles = $this->get_module_db_files();
      $moduleFileIndex = array();

      foreach ($moduleFiles as $server => $db) {
         $real = realpath((string)($db['file'] ?? ''));
         if ($real) {
            $moduleFileIndex[strtolower(str_replace('\\', '/', $real))] = 1;
         }
      }

      foreach ($config as $server => $data) {
         if (isset($moduleFiles[$server])) {
            continue;
         }

         $type = strtolower((string)($data['type'] ?? ''));
         $host = (string)($data['host'] ?? '');
         $name = (string)($data['dbname'] ?? ($data['name'] ?? ''));
         $isSqlite = ($type == 'sqlite' || $type == 'sqlite3' || preg_match('/\.(db3|sqlite|sqlite3)$/i', $name));

         if ($isSqlite && ($host !== '' || $name !== '')) {
            $file = dbx()->os_path($host . $name);
            $real = realpath($file);

            if ($real) {
               $real = strtolower(str_replace('\\', '/', $real));
               if (isset($moduleFileIndex[$real])) {
                  continue;
               }
            }
         }

         return true;
      }

      return false;
   }

   /**
    * Erzeugt die Modulfilteroptionen fuer Schema-Reports.
    *
    * @param array $rows Eingabeparameter fuer diese Methode.
    * @param string $includeSys Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function get_module_filter_options($rows, $includeSys = false) {
      $options = array('0' => $this->schema_texts()->get_fd_message('all_modules'));

      if ($includeSys) {
         $options['sys'] = 'sys';
      }

      foreach ($rows as $row) {
         $modul = trim((string)($row['modul'] ?? ''));
         if ($modul !== '') {
            $options[$modul] = $modul;
         }
      }

      ksort($options);
      return array('0' => $this->schema_texts()->get_fd_message('all_modules')) + $options;
   }

   /**
    * Filtert Report-Zeilen nach Modul oder sys.
    *
    * @param array $rows Eingabeparameter fuer diese Methode.
    * @param string $modul Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function filter_rows_by_module($rows, $modul) {
      $modul = trim((string)$modul);
      if ($modul === '' || $modul === '0') {
         return $rows;
      }

      $filtered = array();
      foreach ($rows as $row) {
         if (strcasecmp((string)($row['modul'] ?? ''), $modul) === 0) {
            $filtered[] = $row;
         }
      }

      return $filtered;
   }

   /**
    * Rendert einen Schema-Report mit Filter, Suche, Pagination und Batch-Aktion.
    *
    * @param string $mode Eingabeparameter fuer diese Methode.
    * @param array $rows Eingabeparameter fuer diese Methode.
    * @param array $flds Eingabeparameter fuer diese Methode.
    * @param string $action Eingabeparameter fuer diese Methode.
    * @param array $batchOptions Eingabeparameter fuer diese Methode.
    * @param string $message Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function run_schema_report($mode, $rows, $flds, $action, $batchOptions, $message = '') {
      $oReport = dbx()->get_system_obj('dbxReport');
      $fidMap = array(
         'dd' => 'report-dd-sync',
         'db' => 'report-db-sync',
         'backup' => 'report-db-backup',
         'restore' => 'report-db-restore',
      );
      $fid = $fidMap[$mode] ?? 'report-db-sync';
      $moduleOptions = $this->get_module_filter_options($rows, $mode != 'dd' && $this->has_config_db_servers());
      $isSchemaList = in_array($mode, array('dd', 'db'), true);

      $oReport->init($fid, 'report-schema');
      $oReport->_fd = 'dbxAdmin|schema-report';
      $oReport->load_fd_messages();
      $oReport->set_form_help_enabled(false);
      $oReport->set_callback_owner($this);
      $oReport->set_callback('row_action_data', 'schema_row_action_data');
      $oReport->_action = $action;
      $oReport->_rflds = $flds;
      $oReport->_mode = 'table';
      $oReport->_pages = true;
      $oReport->_rrows = 25;
      $oReport->_rpos = 0;
      $oReport->_but_pagination = 7;
      $oReport->_create_row_select = true;
      $oReport->_create_row_edit = $isSchemaList;
      $oReport->_create_row_show = $isSchemaList;
      $oReport->_create_row_delete = $isSchemaList;
      $oReport->_create_sel_flds = false;
      $oReport->_fld_id = 'rid';
      $oReport->_table_buttons = 'left';
      $texts = $this->schema_texts();
      $oReport->_msg_confirm_delete = ($mode == 'dd')
         ? $texts->get_fd_message('confirm_delete_dd')
         : $texts->get_fd_message('confirm_delete_db');
      $oReport->_msg_info = (string)$message;
      $oReport->_tabel_tpls['tpl_row_edit'] = 'modul|schema_row_edit';
      $oReport->_tabel_tpls['tpl_row_show'] = 'modul|schema_row_show';
      $oReport->_tabel_tpls['tpl_row_delete'] = 'modul|schema_row_delete';

      $oReport->add_action('rows_select', 'action_button_select', '&dbx_do=multi_select');
      $oReport->add_action('rows_deselect', 'action_button_deselect', '&dbx_do=multi_deselect');

      $barPrefix = in_array($mode, array('dd', 'backup', 'restore'), true) ? $mode : 'db';
      $oReport->add_rep('bar_title', $texts->get_fd_message('bar_' . $barPrefix . '_title'));
      $oReport->add_rep('bar_subtitle', $texts->get_fd_message('bar_' . $barPrefix . '_subtitle'));
      $oReport->add_fld('dbx_rmodul', 'select-single-label', label: $texts->get_fd_message('label_module'), rules: 'parameter', options: $moduleOptions);
      $oReport->add_fld('dbx_rwhere', 'dbx|search', label: $texts->get_fd_message('label_search'), rules: 'sqlsearch|max=80');
      $oReport->add_fld('dbx_rrows', 'integer-label', label: $texts->get_fd_message('label_rows'), rules: 'int');
      $oReport->add_fld('maction_select', 'select-single', label: $texts->get_fd_message('label_batch'), rules: 'parameter', options: $batchOptions);
      $oReport->add_obj('maction_submit', 'dbx|button-submit', data: 'label=' . $texts->get_fd_message('action_start'));

      if ($oReport->submit()) {
         $act = $oReport->get_fld_val('maction_select', '0', 'parameter');
         if ($act && $act != '0') {
            $selects = array_keys($oReport->get_multi_selects());
            if ($selects) {
               return $this->start_batch($act, $selects);
            }

            $oReport->_msg_warning = $texts->get_fd_message('no_rows_selected');
         }
      }

      $modul  = $oReport->get_fld_val('dbx_rmodul', '0', 'parameter');
      $search = $oReport->get_fld_val('dbx_rwhere', '', 'sqlsearch|max=80');
      $rrows  = $oReport->get_fld_val('dbx_rrows', 25, 'int');
      $rpos   = $oReport->get_fld_val('dbx_rpos', 0, 'int');
      if ($rrows <= 0) {
         $rrows = 25;
      }

      $rows = $this->filter_rows_by_module($rows, $modul);
      $rows = $this->filter_rows($rows, $search);
      $oReport->_rrows = $rrows;
      $oReport->_rpos = $rpos;
      $oReport->_rcount = count($rows);
      $oReport->_rdata = $oReport->data_rows($rows, $rpos, $rrows);

      return $oReport->run();
   }

   /**
    * Erweitert Report-Zeilen um Daten fuer Row-Aktions-Templates.
    *
    * @param string $report Eingabeparameter fuer diese Methode.
    * @param array $data Eingabeparameter fuer diese Methode.
    * @return array
    */
   public function schema_row_action_data($report, $data) {
      if (!is_array($data) || !isset($data['data']) || !is_array($data['data'])) {
         return $data;
      }

      $type = (string)($data['type'] ?? '');
      if (!in_array($type, array('edit', 'show', 'delete'), true)) {
         return $data;
      }

      $record = is_array($data['record'] ?? null) ? $data['record'] : array();
      $rid = (string)($data['data']['rid'] ?? ($record['rid'] ?? ''));
      $mode = str_starts_with($rid, 'db_') ? 'db' : 'dd';
      $base = $this->build_url($mode, $mode == 'dd' ? 'list_dd' : 'list_db');

      if ($type == 'edit') {
         $modul = '';
         $dd = '';

         if ($mode == 'dd') {
            [$modul, $dd] = $this->split_dd_rid($rid);
         } else {
            $modul = (string)($record['dd_edit_modul'] ?? '');
            $dd    = (string)($record['dd_edit_dd'] ?? '');
         }

         if ($modul && $dd) {
            $editUrl = '?dbx_modul=dbxAdmin&dbx_run1=edit_dd&modul=' . rawurlencode($modul) . '&dd=' . rawurlencode($dd);
            $editTitle = $this->schema_texts()->format_fd_message('edit_dd', array('module' => $modul, 'dd' => $dd));
            $data['data']['edit_url'] = $this->esc($editUrl);
            $data['data']['edit_title'] = $this->esc($editTitle);
            $data['data']['edit_data_dBx'] = 'data-url="' . $this->esc($editUrl) . '" data-title="' . $this->esc($editTitle) . '"';
         } else {
            $data['data']['edit_url'] = '#';
            $data['data']['edit_title'] = $this->esc($this->schema_texts()->get_fd_message('no_dd_assigned'));
            $data['data']['edit_data_dBx'] = '';
            $data['data']['class'] = trim((string)($data['data']['class'] ?? '') . ' disabled text-muted');
         }

         return $data;
      }

      $showUrl = $this->append_url_params($base, array(
         'dbx_do' => 'row_show',
         'rid'    => $rid,
      ));
      $deleteUrl = $this->append_url_params($base, array(
         'dbx_do' => 'row_delete',
         'rid'    => $rid,
      ));

      $data['data']['show_url'] = $this->esc($showUrl);
      $data['data']['delete_url'] = $this->esc($deleteUrl);

      return $data;
   }

   /**
    * Verarbeitet Zeilenaktionen des DD-Reports.
    *
    * @param string $message Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function handle_dd_row_action(&$message) {
      $do = dbx()->get_modul_var('dbx_do', '', 'parameter');
      if (!in_array($do, array('row_show', 'row_delete'), true)) {
         return '';
      }

      $rid = dbx()->get_modul_var('rid', '', 'parameter');
      [$modul, $dd] = $this->split_dd_rid($rid);
      if (!$modul || !$dd) {
         return '<div class="alert alert-danger">DD-Auswahl ist ungueltig.</div>';
      }

      $ddRef = $this->dd_ref($modul, $dd);

      if ($do == 'row_show') {
         $params = array('rid' => $rid);
         return $this->run_data_report(
            $ddRef,
            'DD Daten: ' . $modul . '|' . $dd,
            $this->build_url('dd', 'data_read', $params),
            $this->build_url('dd', 'data_save', $params),
            $this->build_url('dd', 'data_insert', $params),
            $this->build_url('dd', 'data_delete', $params)
         );
      }

      if ($this->delete_dd_file($modul, $dd)) {
         $message = 'DD-Datei geloescht: ' . $modul . '|' . $dd;
      } else {
         $message = 'DD-Datei konnte nicht geloescht werden: ' . $modul . '|' . $dd;
      }

      return '';
   }

   /**
    * Verarbeitet Zeilenaktionen des DB-Reports.
    *
    * @param string $message Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function handle_db_row_action(&$message) {
      $do = dbx()->get_modul_var('dbx_do', '', 'parameter');
      if (!in_array($do, array('row_show', 'row_delete'), true)) {
         return '';
      }

      $rid = dbx()->get_modul_var('rid', '', 'parameter');
      [$server, $table] = $this->decode_db_rid($rid);
      if (!$server || !$table) {
         return '<div class="alert alert-danger">DB-Auswahl ist ungueltig.</div>';
      }

      if ($do == 'row_show') {
         $ddRef = $this->ensure_db_view_dd($server, $table);
         if (!$ddRef) {
            return '<div class="alert alert-danger">Ansichts-DD konnte nicht erzeugt werden.</div>';
         }

          $params = array('rid' => $rid);
          return $this->run_data_report(
             $ddRef,
             'DB Daten: ' . $server . '|' . $table,
             $this->build_url('db', 'data_read', $params),
             $this->build_url('db', 'data_save', $params),
             $this->build_url('db', 'data_insert', $params),
             $this->build_url('db', 'data_delete', $params)
          );
      }

      $oDD = dbx()->get_system_obj('dbxDD');
      if ($oDD->drop_db_tab($server, $table)) {
         $message = 'DB-Tabelle geloescht: ' . $server . '|' . $table;
      } else {
         $message = 'DB-Tabelle konnte nicht geloescht werden: ' . $server . '|' . $table;
      }

      return '';
   }

   /**
    * Rendert die DD-Uebersicht.
    *
    * @return string
    */
   private function report_dd() {
      $texts = $this->schema_texts();
      $message = '';
      $content = $this->handle_dd_row_action($message);
      if ($content !== '') {
         return $content;
      }

      $flds = array(
         'modul'     => $texts->get_fd_message('column_module'),
         'dd'        => $texts->get_fd_message('column_dd'),
         'path'      => $texts->get_fd_message('column_path'),
         'server'    => $texts->get_fd_message('column_server'),
         'database'  => $texts->get_fd_message('column_database'),
         'table'     => $texts->get_fd_message('column_table'),
         'dd_fields' => $texts->get_fd_message('column_dd_fields'),
         'db_fields' => $texts->get_fd_message('column_db_fields'),
         'count'     => $texts->get_fd_message('column_records'),
         'sync'      => $texts->get_fd_message('column_status'),
         'sync_info' => $texts->get_fd_message('column_details'),
         'act_sync'  => $this->header_icon('bi bi-arrow-repeat', $texts->get_fd_message('action_dd_to_db')),
         'act_map'   => $this->header_icon('bi bi-diagram-3', $texts->get_fd_message('action_mapping')),
         'act_move'  => $this->header_icon('bi bi-box-arrow-right', $texts->get_fd_message('action_transfer')),
         'act_flds'  => $this->header_icon('bi bi-list-columns', $texts->get_fd_message('action_dd_fields')),
      );

      $batch = array(
         '0'                    => $texts->get_fd_message('batch_placeholder'),
         'batch_dd_to_db'       => $texts->get_fd_message('batch_dd_to_db'),
         'batch_dd_to_db_force' => $texts->get_fd_message('batch_dd_to_db_force'),
      );

      return $this->run_schema_report(
         'dd',
         $this->get_dd_records(),
         $flds,
         $this->build_url('dd', 'list_dd'),
         $batch,
         $message
      );
   }

   /**
    * Rendert die DB-/Tabellen-Uebersicht.
    *
    * @return string
    */
   private function report_db() {
      $texts = $this->schema_texts();
      $message = '';
      $content = $this->handle_db_row_action($message);
      if ($content !== '') {
         return $content;
      }

      $flds = array(
         'modul'     => $texts->get_fd_message('column_module'),
         'server'    => $texts->get_fd_message('column_server'),
         'table'     => $texts->get_fd_message('column_table'),
         'db_fields' => $texts->get_fd_message('column_db_fields'),
         'dd_fields' => $texts->get_fd_message('column_dd_fields'),
         'count'     => $texts->get_fd_message('column_records'),
         'dd'        => $texts->get_fd_message('column_dd'),
         'sync'      => $texts->get_fd_message('column_status'),
         'sync_info' => $texts->get_fd_message('column_details'),
         'act_sync'  => $this->header_icon('bi bi-arrow-down-up', $texts->get_fd_message('action_db_to_dd')),
         'act_dd_sync' => $this->header_icon('bi bi-arrow-repeat', $texts->get_fd_message('action_db_from_dd')),
         'act_map'   => $this->header_icon('bi bi-diagram-3', $texts->get_fd_message('action_mapping')),
         'act_move'  => $this->header_icon('bi bi-box-arrow-right', $texts->get_fd_message('action_transfer')),
      );

      $batch = array(
         '0'                    => $texts->get_fd_message('batch_placeholder'),
         'batch_db_to_dd'       => $texts->get_fd_message('batch_db_to_dd'),
         'batch_dd_to_db'       => $texts->get_fd_message('batch_dd_to_db'),
         'batch_dd_to_db_force' => $texts->get_fd_message('batch_dd_to_db_force'),
      );

      return $this->run_schema_report('db', $this->get_db_records($this->get_dd_records()), $flds, $this->build_url('db', 'list_db'), $batch, $message);
   }
   /**
    * Erweitert DB-Report-Zeilen um Backup-/Restore-Informationen.
    *
    * @return array
    */
   private function backup_restore_rows() {
      $rows = $this->get_db_records($this->get_dd_records());
      $texts = $this->schema_texts();

      foreach ($rows as $no => $row) {
         [$server, $table] = $this->decode_db_rid((string)($row['rid'] ?? ''));

         if (!$server) {
            $server = (string)($row['server'] ?? '');
         }

         if (!$table) {
            $rows[$no]['backup'] = $this->badge($texts->get_fd_message('no_table'), 'secondary');
            $rows[$no]['act_backup'] = '';
            $rows[$no]['act_restore'] = '';
            continue;
         }

         $rows[$no]['backup'] = $this->backup_label($server, $table);

         $backupUrl = $this->build_url('dd', 'backup_db_table', array(
            'server' => $server,
            'table' => $table,
            'reset' => 1,
         ));
         $rows[$no]['act_backup'] = $this->openwin(
            $backupUrl,
            'bi bi-download',
            $texts->get_fd_message('action_backup_create'),
            980,
            700
         );

         $restoreUrl = $this->build_url('dd', 'restore_db_table', array(
            'server' => $server,
            'table' => $table,
         ));
         $rows[$no]['act_restore'] = $this->openwin(
            $restoreUrl,
            'bi bi-upload',
            $texts->get_fd_message('action_restore_select'),
            980,
            760
         );
      }

      return $rows;
   }

   /**
    * Rendert die Backup-Uebersicht.
    *
    * @return string
    */
   private function report_backup_db() {
      $texts = $this->schema_texts();
      $flds = array(
         'modul'    => $texts->get_fd_message('column_module'),
         'server'   => $texts->get_fd_message('column_server'),
         'database' => $texts->get_fd_message('column_database'),
         'table'    => $texts->get_fd_message('column_table'),
         'count'    => $texts->get_fd_message('column_records'),
         'path'     => $texts->get_fd_message('column_path'),
         'backup'   => $texts->get_fd_message('column_last_backup'),
         'act_backup' => $this->header_icon('bi bi-download', $texts->get_fd_message('action_backup_create')),
      );

      $batch = array(
         '0' => $texts->get_fd_message('batch_placeholder'),
         'batch_backup_db' => $texts->get_fd_message('batch_backup_selected'),
      );

      return $this->run_schema_report('backup', $this->backup_restore_rows(), $flds, $this->build_url('dd', 'backup_db'), $batch, '');
   }

   /**
    * Rendert die Restore-Uebersicht.
    *
    * @return string
    */
   private function report_restore_db() {
      $texts = $this->schema_texts();
      $flds = array(
         'modul'    => $texts->get_fd_message('column_module'),
         'server'   => $texts->get_fd_message('column_server'),
         'database' => $texts->get_fd_message('column_database'),
         'table'    => $texts->get_fd_message('column_table'),
         'count'    => $texts->get_fd_message('column_records'),
         'path'     => $texts->get_fd_message('column_path'),
         'backup'   => $texts->get_fd_message('column_last_backup'),
         'act_restore' => $this->header_icon('bi bi-upload', $texts->get_fd_message('action_restore_choose')),
      );

      $batch = array(
         '0' => $texts->get_fd_message('batch_placeholder'),
         'batch_restore_latest_db' => $texts->get_fd_message('batch_restore_selected'),
      );

      return $this->run_schema_report('restore', $this->backup_restore_rows(), $flds, $this->build_url('dd', 'restore_db'), $batch, '');
   }

   /**
    * Haengt Parameter an eine URL an.
    *
    * @param string $url Eingabeparameter fuer diese Methode.
    * @param array $params Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function append_url_params($url, $params = array()) {
      if (!$url) {
         return '';
      }

      foreach ($params as $key => $value) {
         $url .= (strpos($url, '?') === false ? '?' : '&')
              . rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
      }

      return $url;
   }

   /**
    * Erzeugt die Bezeichnung eines Prozessstatus.
    *
    * @param string $status Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function process_status_label($status) {
      $map = array(
         'new'      => 'Neu',
         'reset'    => 'Neu',
         'running'  => 'Laeuft',
         'paused'   => 'Angehalten',
         'canceled' => 'Abgebrochen',
         'finished' => 'Fertig',
         'error'    => 'Fehler',
      );

      return $map[$status] ?? $status;
   }

   /**
    * Erzeugt die CSS-Klasse fuer einen Prozessstatus.
    *
    * @param string $status Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function process_status_class($status) {
      $map = array(
         'running'  => 'bg-primary',
         'paused'   => 'bg-warning text-dark',
         'canceled' => 'bg-danger',
         'finished' => 'bg-success',
         'error'    => 'bg-danger',
         'new'      => 'bg-secondary',
         'reset'    => 'bg-secondary',
      );

      return $map[$status] ?? 'bg-secondary';
   }

   /**
    * Erzeugt das Icon fuer einen Prozessstatus.
    *
    * @param string $status Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function process_status_icon($status) {
      $map = array(
         'running'  => 'bi bi-play-fill',
         'paused'   => 'bi bi-pause-fill',
         'canceled' => 'bi bi-x-lg',
         'finished' => 'bi bi-check-lg',
         'error'    => 'bi bi-exclamation-triangle',
         'new'      => 'bi bi-circle',
         'reset'    => 'bi bi-arrow-clockwise',
      );

      return $map[$status] ?? 'bi bi-circle';
   }

   /**
    * Erzeugt die Bezeichnung eines Prozesstyps.
    *
    * @param string $type Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function process_type_label($type) {
      $map = array(
         'sync_dd_to_db' => 'DD -> DB',
         'sync_db_to_dd' => 'DB -> DD',
         'transfer_table' => 'Transfer',
         'backup' => 'Backup',
         'restore' => 'Restore',
         'schema_batch' => 'Batch',
      );

      return $map[$type] ?? ($type ?: 'Prozess');
   }

   /**
    * Erzeugt die Bezeichnung einer Prozessaufgabe.
    *
    * @param string $phase Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function process_task_label($phase) {
      $phase = (string)$phase;

      if (strpos($phase, 'backup') !== false) return 'Aufgabe Backup';
      if (strpos($phase, 'restore') !== false) return 'Aufgabe Restore';
      if (strpos($phase, 'create') !== false || strpos($phase, 'rename') !== false || strpos($phase, 'drop') !== false) return 'Aufgabe Struktur';
      if (strpos($phase, 'add_') === 0) return 'Aufgabe Schema';
      if (strpos($phase, 'read') !== false || strpos($phase, 'merge') !== false || strpos($phase, 'write') !== false) return 'Aufgabe DD';
      if (strpos($phase, 'prepare') !== false) return 'Aufgabe Vorbereitung';
      if (strpos($phase, 'batch') !== false) return 'Aufgabe Batch';

      return 'Aufgabe';
   }

   /**
    * Erzeugt die Bezeichnung eines Prozessschritts.
    *
    * @param string $phase Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function process_step_label($phase) {
      $map = array(
         'prepare'        => 'Vorbereiten',
         'prepare_target' => 'Ziel vorbereiten',
         'create_table'   => 'Tabelle anlegen',
         'add_fields'     => 'Felder ergaenzen',
         'add_indexes'    => 'Indizes ergaenzen',
         'backup_old'     => 'Backup erstellen',
         'backup_source'  => 'Quelle sichern',
         'rename_old'     => 'Alte Tabelle umbenennen',
         'create_new'     => 'Neue Tabelle anlegen',
         'restore_new'    => 'Daten einlesen',
         'restore_target' => 'Ziel einlesen',
         'drop_old'       => 'Alte Tabelle entfernen',
         'read_schema'    => 'Schema lesen',
         'merge_meta'     => 'DD zusammenfuehren',
         'write_dd'       => 'DD schreiben',
         'batch_step'     => 'Eintrag bearbeiten',
      );

      return $map[$phase] ?? ($phase ?: 'Schritt');
   }

   /**
    * Bereitet eine Prozessmeldung fuer die Anzeige auf.
    *
    * @param string $message Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function process_message_label($message) {
      $message = (string)$message;
      $map = array(
         'process paused' => 'Prozess angehalten',
         'process resumed' => 'Prozess fortgesetzt',
         'process continued' => 'Prozess fortgesetzt',
         'process canceled' => 'Prozess abgebrochen',
         'process restarted' => 'Prozess neu gestartet',
         'sync initialized' => 'Synchronisierung vorbereitet',
         'transfer initialized' => 'Transfer vorbereitet',
         'schema loaded' => 'Schema gelesen',
         'meta merged' => 'DD-Informationen zusammengefuehrt',
         'sync dd -> db finished' => 'DD -> DB abgeschlossen',
         'sync dd -> db rebuild finished' => 'DD -> DB Rebuild abgeschlossen',
         'sync db -> dd finished' => 'DB -> DD abgeschlossen',
         'transfer finished' => 'Transfer abgeschlossen',
         'batch initialized' => 'Batch vorbereitet',
         'batch finished' => 'Batch abgeschlossen',
         'backup finished' => 'Backup abgeschlossen',
         'restore finished' => 'Restore abgeschlossen',
      );

      return $map[$message] ?? $message;
   }

   /**
    * Rendert die Prozessanzeige fuer Sync, Transfer, Backup, Restore und Batch.
    *
    * @param string $title Eingabeparameter fuer diese Methode.
    * @param array $state Eingabeparameter fuer diese Methode.
    * @param string $nextUrl Eingabeparameter fuer diese Methode.
    * @param string $backUrl Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function render_process($title, $state, $nextUrl = '', $backUrl = '') {
      $oTPL = dbx()->get_system_obj('dbxTPL');
      $status = $state['status'] ?? 'running';
      $percent = (int)($state['percent'] ?? 0);
      if ($percent < 0) $percent = 0;
      if ($percent > 100) $percent = 100;

      $stepPercent = (int)($state['step_percent'] ?? $percent);
      if ($stepPercent < 0) $stepPercent = 0;
      if ($stepPercent > 100) $stepPercent = 100;

      $barClass = 'bg-primary';
      if ($status == 'finished') $barClass = 'bg-success';
      if ($status == 'error' || $status == 'canceled') $barClass = 'bg-danger';
      if ($status == 'paused') $barClass = 'bg-warning';

      $restartUrl  = $nextUrl ? $this->append_url_params($nextUrl, array('reset' => 1, 'proc_cmd' => 'restart')) : '';
      $targetId    = 'dbx_process_' . substr(md5((string)($state['proc_key'] ?? $title)), 0, 14);
      $phase       = (string)($state['phase'] ?? '');
      $procType    = (string)($state['proc_type'] ?? ($state['act'] ?? 'schema_batch'));
      $hasControls = $nextUrl ? 1 : 0;
      $autostart   = ($nextUrl && $status == 'running') ? 1 : 0;

      $data = array(
         'target_id'        => $this->esc($targetId),
         'title'            => $this->esc($title),
         'status_key'       => $this->esc($status),
         'status_label'     => $this->esc($this->process_status_label($status)),
         'status_class'     => $this->esc($this->process_status_class($status)),
         'status_icon'      => $this->esc($this->process_status_icon($status)),
         'message'          => $this->esc($this->process_message_label($state['message'] ?? '')),
         'percent'          => $percent,
         'step_percent'     => $stepPercent,
         'bar_class'        => $this->esc($barClass),
         'task_label'       => $this->esc($this->process_task_label($phase)),
         'step_label'       => $this->esc($this->process_step_label($phase)),
         'process_label'    => $this->esc($this->process_type_label($procType)),
         'updated_at'       => $this->esc($state['updated_at'] ?? ''),
         'next_url'         => $this->esc($nextUrl),
         'pause_url'        => $this->esc($this->append_url_params($nextUrl, array('proc_cmd' => 'pause'))),
         'resume_url'       => $this->esc($this->append_url_params($nextUrl, array('proc_cmd' => 'resume'))),
         'continue_url'     => $this->esc($this->append_url_params($nextUrl, array('proc_cmd' => 'continue'))),
         'cancel_url'       => $this->esc($this->append_url_params($nextUrl, array('proc_cmd' => 'cancel'))),
         'restart_url'      => $this->esc($restartUrl),
         'autostart'        => $autostart,
         'interval'         => 800,
         'pause_visible'    => $hasControls ? 'running' : '_none',
         'resume_visible'   => $hasControls ? 'paused' : '_none',
         'continue_visible' => $hasControls ? 'canceled' : '_none',
         'restart_visible'  => $restartUrl ? 'paused,canceled,error,finished' : '_none',
         'cancel_visible'   => $hasControls ? 'running,paused' : '_none',
         'back_url'         => $this->esc($backUrl),
      );

      return $oTPL->get_tpl('dbxAdmin|schema-process', $data);
   }

   /**
    * Fuehrt den DD-nach-DB-Sync-Prozess aus.
    *
    * @return string
    */
   private function run_sync_dd_to_db() {
      $modul = dbx()->get_modul_var('modul', 'dbx', 'parameter');
      $dd    = dbx()->get_modul_var('dd', '', 'parameter');
      $mode  = dbx()->get_modul_var('mode', 'apply', 'parameter');
      $reset = dbx()->get_modul_var('reset', 0, 'int');
      $cmd   = dbx()->get_modul_var('proc_cmd', '', 'parameter');
      $oDD   = dbx()->get_system_obj('dbxDD');

      if (!$dd) {
         return '<div class="alert alert-danger">DD fehlt.</div>';
      }

      if ($reset) {
         $oDD->sync_dd_to_db($modul, $dd, 'reset');
      }

      $nextUrl = $this->build_url('dd', 'sync_dd_to_db', array(
         'modul' => $modul,
         'dd'    => $dd,
         'mode'  => $mode,
      ));

      if ($cmd && in_array($cmd, array('pause', 'resume', 'continue', 'cancel'), true)) {
         $state = $oDD->sync_dd_to_db($modul, $dd, $cmd);
         return $this->render_process('DD -> DB: ' . $modul . '|' . $dd, $state, $nextUrl, $this->build_url('dd', 'list_dd'));
      }

      $state = $oDD->sync_dd_to_db($modul, $dd, $mode);

      $content = $this->render_process('DD -> DB: ' . $modul . '|' . $dd, $state, $nextUrl, $this->build_url('dd', 'list_dd'));

      if (($state['status'] ?? '') == 'error' && strpos((string)($state['message'] ?? ''), 'rebuild needed') !== false) {
         $force = $this->build_url('dd', 'sync_dd_to_db', array(
            'modul' => $modul,
            'dd'    => $dd,
            'mode'  => 'force',
            'reset' => 1,
         ));
         $content .= '<div class="mt-3"><a class="btn btn-danger" href="' . $this->esc($force) . '">Rebuild starten</a></div>';
      }

      return $content;
   }

   /**
    * Fuehrt den DB-nach-DD-Sync-Prozess aus.
    *
    * @return string
    */
   private function run_sync_db_to_dd() {
      $server = dbx()->get_modul_var('server', '', 'parameter');
      $table  = dbx()->get_modul_var('table', '', 'parameter');
      $modul  = dbx()->get_modul_var('modul', '', 'parameter');
      $dd     = dbx()->get_modul_var('dd', '', 'parameter');
      $mode   = dbx()->get_modul_var('mode', 'merge', 'parameter');
      $reset  = dbx()->get_modul_var('reset', 0, 'int');
      $cmd    = dbx()->get_modul_var('proc_cmd', '', 'parameter');

      if (!$server || !$table) {
         return '<div class="alert alert-danger">Server oder Tabelle fehlt.</div>';
      }

      if (!$modul || !$dd) {
         return $this->form_db_to_dd($server, $table);
      }

      $oDD = dbx()->get_system_obj('dbxDD');
      if ($reset) {
         $oDD->sync_db_to_dd($modul, $dd, 'reset', $server, $table);
      }

      $nextUrl = $this->build_url('db', 'sync_db_to_dd', array(
         'server' => $server,
         'table'  => $table,
         'modul'  => $modul,
         'dd'     => $dd,
         'mode'   => $mode,
      ));

      if ($cmd && in_array($cmd, array('pause', 'resume', 'continue', 'cancel'), true)) {
         $state = $oDD->sync_db_to_dd($modul, $dd, $cmd, $server, $table);
         return $this->render_process('DB -> DD: ' . $server . '|' . $table . ' -> ' . $modul . '|' . $dd, $state, $nextUrl, $this->build_url('db', 'list_db'));
      }

      $state = $oDD->sync_db_to_dd($modul, $dd, $mode, $server, $table);

      return $this->render_process('DB -> DD: ' . $server . '|' . $table . ' -> ' . $modul . '|' . $dd, $state, $nextUrl, $this->build_url('db', 'list_db'));
   }

   /**
    * Rendert das Formular zum Erzeugen oder Mergen eines DD aus einer DB-Tabelle.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @param string $table Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function form_db_to_dd($server, $table) {
      $oForm = dbx()->get_system_obj('dbxForm');
      $oForm->init('form-schema-action');
      $oForm->_fd = 'dbxAdmin|schema-report';
      $oForm->load_fd_messages();
      $oForm->_data = array(
         'server' => $server,
         'table'  => $table,
         'modul'  => 'dbx',
         'dd'     => $this->sanitize_dd_name($table),
      );
      $oForm->_action = $this->build_url('db', 'sync_db_to_dd', array(
         'server' => $server,
         'table'  => $table,
      ));
      $oForm->_msg_info = $oForm->get_fd_message('db_to_dd_info');

      $oForm->add_fld('server', 'hidden', rules: 'parameter');
      $oForm->add_fld('table', 'hidden', rules: 'parameter');
      $oForm->add_fld(
         'modul',
         'select-single-label',
         label: $oForm->get_fd_message('label_module'),
         rules: 'parameter',
         options: $this->get_module_options()
      );
      $oForm->add_fld(
         'dd',
         'text-label',
         label: $oForm->get_fd_message('column_dd'),
         rules: 'parameter|min=1'
      );

      if ($oForm->submit() && !$oForm->errors()) {
         $modul = $oForm->get_post('modul', 'dbx', 'parameter');
         $dd = $this->sanitize_dd_name($oForm->get_post('dd', $table, 'parameter'));

         dbx()->set_modul_var('modul', $modul);
         dbx()->set_modul_var('dd', $dd);
         dbx()->set_modul_var('mode', 'merge');
         dbx()->set_modul_var('reset', 1);

         return $this->run_sync_db_to_dd();
      }

      return $oForm->run();
   }

   /**
    * Rendert und verarbeitet den Schema-Mapping-Editor.
    *
    * @return string
    */
   private function run_mapping_editor() {
      $texts = $this->schema_texts();
      $kind   = dbx()->get_modul_var('kind', 'dd_to_db', 'parameter');
      $modul  = dbx()->get_modul_var('modul', '', 'parameter');
      $dd     = dbx()->get_modul_var('dd', '', 'parameter');
      $server = dbx()->get_modul_var('server', '', 'parameter');
      $table  = dbx()->get_modul_var('table', '', 'parameter');

      if (!$dd && $kind !== 'transfer') {
         return '<div class="alert alert-danger">'
            . $this->esc($texts->get_fd_message('missing_dd'))
            . '</div>';
      }

      $oDD = dbx()->get_system_obj('dbxDD');
      $context = array(
         'modul'  => $modul,
         'dd'     => $dd,
         'server' => $server,
         'table'  => $table,
      );

      $model = $oDD->build_schema_mapping($kind, $context);

      $oForm = dbx()->get_system_obj('dbxForm');
      $oForm->init('schema-mapping');
      $oForm->_fd = 'dbxAdmin|schema-report';
      $oForm->load_fd_messages();
      $oForm->_msg_info = '';
      $oForm->_action = $this->build_url(($kind == 'db_to_dd') ? 'db' : 'dd', 'mapping', array(
         'kind'   => $kind,
         'modul'  => $modul,
         'dd'     => $dd,
         'server' => $server,
         'table'  => $table,
      ));

      $oForm->_data = array(
         'kind'   => $kind,
         'modul'  => $modul,
         'dd'     => $dd,
         'server' => $server,
         'table'  => $table,
      );

      $oForm->add_fld('kind', 'hidden', rules: 'parameter');
      $oForm->add_fld('modul', 'hidden', rules: 'parameter');
      $oForm->add_fld('dd', 'hidden', rules: 'parameter');
      $oForm->add_fld('server', 'hidden', rules: 'parameter');
      $oForm->add_fld('table', 'hidden', rules: 'parameter');

      if ($oForm->submit() && !$oForm->errors()) {
         $mapping = $this->posted_mapping_from_model($model);
         $ok = $oDD->save_schema_mapping($kind, $model['context'] ?? $context, $mapping);

         if ($ok) {
            $oForm->_msg_success = $oForm->get_fd_message('mapping_saved');
            $model = $oDD->build_schema_mapping($kind, $model['context'] ?? $context);
         } else {
            $oForm->_msg_error = $oForm->get_fd_message('mapping_save_error');
         }
      }

      $oForm->add_obj('mapping_board', 'obj-value', $this->render_mapping_board($model));
      $oForm->add_obj('save_button', 'obj-value',
         '<button type="submit" class="btn btn-primary" title="'
            . $this->esc($oForm->get_fd_message('action_save'))
            . '"><i class="bi bi-save"></i></button>'
      );
      $oForm->add_obj('back_button', 'obj-value',
         '<a class="btn btn-secondary" href="' . $this->esc($this->build_url(($kind == 'db_to_dd') ? 'db' : 'dd', ($kind == 'db_to_dd') ? 'list_db' : 'list_dd')) . '" title="'
            . $this->esc($oForm->get_fd_message('action_back'))
            . '"><i class="bi bi-arrow-left"></i></a>'
      );

      return $oForm->run();
   }

   /**
    * Fuehrt den Tabellen-Transferprozess aus.
    *
    * @return string
    */
   private function run_transfer() {
      $texts = $this->schema_texts();
      $sourceServer = dbx()->get_modul_var('source_server', '', 'parameter');
      $sourceTable  = dbx()->get_modul_var('source_table', '', 'parameter');
      $start        = dbx()->get_modul_var('start', 0, 'int');

      if (!$sourceServer || !$sourceTable) {
         return '<div class="alert alert-danger">'
            . $this->esc($texts->get_fd_message('missing_source'))
            . '</div>';
      }

      if (!$start) {
         return $this->form_transfer($sourceServer, $sourceTable);
      }

      $targetServer = dbx()->get_modul_var('target_server', '', 'parameter');
      $targetTable  = dbx()->get_modul_var('target_table', $sourceTable, 'parameter');
      $createTarget = dbx()->get_modul_var('create_target', 1, 'int');
      $truncate     = dbx()->get_modul_var('truncate_target', 1, 'int');
      $reset        = dbx()->get_modul_var('reset', 0, 'int');
      $cmd          = dbx()->get_modul_var('proc_cmd', '', 'parameter');
      $oDD          = dbx()->get_system_obj('dbxDD');

      if ($reset) {
         $oDD->transfer_table($sourceServer, $sourceTable, $targetServer, $targetTable, 'reset', $createTarget, $truncate);
      }

      $nextUrl = $this->build_url('db', 'transfer', array(
         'source_server'   => $sourceServer,
         'source_table'    => $sourceTable,
         'target_server'   => $targetServer,
         'target_table'    => $targetTable,
         'create_target'   => $createTarget,
         'truncate_target' => $truncate,
         'start'           => 1,
      ));

      if ($cmd && in_array($cmd, array('pause', 'resume', 'continue', 'cancel'), true)) {
         $state = $oDD->transfer_table($sourceServer, $sourceTable, $targetServer, $targetTable, $cmd, $createTarget, $truncate);
         return $this->render_process('Transfer: ' . $sourceServer . '|' . $sourceTable . ' -> ' . $targetServer . '|' . $targetTable, $state, $nextUrl, $this->build_url('db', 'list_db'));
      }

      $state = $oDD->transfer_table($sourceServer, $sourceTable, $targetServer, $targetTable, 'step', $createTarget, $truncate);

      return $this->render_process('Transfer: ' . $sourceServer . '|' . $sourceTable . ' -> ' . $targetServer . '|' . $targetTable, $state, $nextUrl, $this->build_url('db', 'list_db'));
   }

   /**
    * Rendert das Formular fuer Tabellen-Transfer.
    *
    * @param string $sourceServer Eingabeparameter fuer diese Methode.
    * @param string $sourceTable Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function form_transfer($sourceServer, $sourceTable) {
      $oForm = dbx()->get_system_obj('dbxForm');
      $servers = $this->get_server_options();

      $defaultTarget = '';
      foreach ($servers as $server => $label) {
         if ($server != $sourceServer) {
            $defaultTarget = $server;
            break;
         }
      }
      if (!$defaultTarget) {
         $defaultTarget = $sourceServer;
      }

      $oForm->init('form-schema-action');
      $oForm->_fd = 'dbxAdmin|schema-report';
      $oForm->load_fd_messages();
      $oForm->_data = array(
         'source_server'   => $sourceServer,
         'source_table'    => $sourceTable,
         'target_server'   => $defaultTarget,
         'target_table'    => $sourceTable,
         'create_target'   => 1,
         'truncate_target' => 1,
      );
      $oForm->_action = $this->build_url('db', 'transfer', array(
         'source_server' => $sourceServer,
         'source_table'  => $sourceTable,
      ));
      $oForm->_msg_info = $oForm->get_fd_message('transfer_info');

      $yesNo = array(
         1 => $oForm->get_fd_message('yes'),
         0 => $oForm->get_fd_message('no'),
      );
      $oForm->add_fld('source_server', 'text-label', label: $oForm->get_fd_message('label_source_server'), rules: 'parameter');
      $oForm->add_fld('source_table', 'text-label', label: $oForm->get_fd_message('label_source_table'), rules: 'parameter');
      $oForm->add_fld('target_server', 'select-single-label', label: $oForm->get_fd_message('label_target_server'), rules: 'parameter', options: $servers);
      $oForm->add_fld('target_table', 'text-label', label: $oForm->get_fd_message('label_target_table'), rules: 'parameter|min=1');
      $oForm->add_fld('create_target', 'select-single-label', label: $oForm->get_fd_message('label_create_target'), rules: 'int', options: $yesNo);
      $oForm->add_fld('truncate_target', 'select-single-label', label: $oForm->get_fd_message('label_truncate_target'), rules: 'int', options: $yesNo);

      if ($oForm->submit() && !$oForm->errors()) {
         dbx()->set_modul_var('target_server', $oForm->get_post('target_server', $defaultTarget, 'parameter'));
         dbx()->set_modul_var('target_table', $oForm->get_post('target_table', $sourceTable, 'parameter'));
         dbx()->set_modul_var('create_target', $oForm->get_post('create_target', 1, 'int'));
         dbx()->set_modul_var('truncate_target', $oForm->get_post('truncate_target', 1, 'int'));
         dbx()->set_modul_var('start', 1);
         dbx()->set_modul_var('reset', 1);

         return $this->run_transfer();
      }

      return $oForm->run();
   }

   /**
    * Fuehrt ein Backup fuer eine einzelne Tabelle aus.
    *
    * @return string
    */
   private function run_backup_db_table() {
      $texts = $this->schema_texts();
      $server = dbx()->get_modul_var('server', '', 'parameter');
      $table = dbx()->get_modul_var('table', '', 'parameter');
      if (!$server || !$table) {
         return '<div class="alert alert-danger">' . $this->esc($texts->get_fd_message('missing_server_or_table')) . '</div>';
      }

      $result = $this->backup_table($server, $table);
      $ok = !empty($result['ok']);
      $state = array(
         'proc_type' => 'backup',
         'proc_key' => 'backup_' . $server . '_' . $table,
         'status' => $ok ? 'finished' : 'error',
         'phase' => 'backup_source',
         'message' => $ok ? 'backup finished' : ($result['msg'] ?? 'backup error'),
         'percent' => $ok ? 100 : 0,
         'step_percent' => $ok ? 100 : 0,
         'updated_at' => date('Y-m-d H:i:s'),
      );

      if ($ok) {
         $state['message'] = 'backup finished: ' . $this->backup_relative_file($result['file'] ?? '');
      }

      return $this->render_process('Backup: ' . $server . '|' . $table, $state, '', $this->build_url('dd', 'backup_db'));
   }

   /**
    * Rendert die Auswahl vorhandener Backups fuer Restore.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @param string $table Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function form_restore_db_table($server, $table) {
      $texts = $this->schema_texts();
      $backups = $this->table_backups($server, $table);
      if (!$backups) {
         return '<div class="alert alert-warning">' . $this->esc(
            $texts->format_fd_message('no_backup_for_table', array('table' => $server . '|' . $table))
         ) . '</div>';
      }

      $options = array('latest' => $texts->get_fd_message('latest_backup'));
      foreach ($backups as $backup) {
         $file = (string)($backup['_file'] ?? '');
         if ($file === '') {
            continue;
         }
         $options[$file] = (string)($backup['created_at'] ?? $file) . ' / '
            . $texts->format_fd_message('record_count_short', array('count' => (int)($backup['count'] ?? 0)));
      }

      $oForm = dbx()->get_system_obj('dbxForm');
      $oForm->init('form-schema-restore');
      $oForm->_fd = 'dbxAdmin|schema-report';
      $oForm->load_fd_messages();
      $oForm->_data = array(
         'server' => $server,
         'table' => $table,
         'backup_file' => 'latest',
      );
      $oForm->_action = $this->build_url('dd', 'restore_db_table', array(
         'server' => $server,
         'table' => $table,
      ));
      $oForm->_msg_info = $texts->get_fd_message('restore_warning');

      $oForm->add_fld('server', 'text-label', label: $texts->get_fd_message('column_server'), rules: 'parameter');
      $oForm->add_fld('table', 'text-label', label: $texts->get_fd_message('column_table'), rules: 'parameter');
      $oForm->add_fld('backup_file', 'select-single-label', label: $texts->get_fd_message('label_backup'), rules: 'parameter+.-_', options: $options);
      $oForm->add_obj('restore_submit', 'dbx|button-submit', data: 'label=' . $texts->get_fd_message('action_restore_start'));

      if ($oForm->submit() && !$oForm->errors()) {
         dbx()->set_modul_var('backup_file', $oForm->get_post('backup_file', 'latest', 'parameter+.-_'));
         dbx()->set_modul_var('start', 1);
         return $this->run_restore_db_table();
      }

      return $oForm->run();
   }

   /**
    * Fuehrt den Restore einer Tabelle aus einer Backup-Datei aus.
    *
    * @return string
    */
   private function run_restore_db_table() {
      $texts = $this->schema_texts();
      $server = dbx()->get_modul_var('server', '', 'parameter');
      $table = dbx()->get_modul_var('table', '', 'parameter');
      $start = dbx()->get_modul_var('start', 0, 'int');
      if (!$server || !$table) {
         return '<div class="alert alert-danger">' . $this->esc($texts->get_fd_message('missing_server_or_table')) . '</div>';
      }

      if (!$start) {
         return $this->form_restore_db_table($server, $table);
      }

      $file = dbx()->get_modul_var('backup_file', 'latest', 'parameter+.-_');
      if ($file === 'latest') {
         $latest = $this->latest_backup($server, $table);
         $file = (string)($latest['_file'] ?? '');
      }

      $result = $file
         ? $this->restore_table_from_backup($file)
         : array('ok' => 0, 'msg' => $texts->get_fd_message('no_backup_found'));
      $ok = !empty($result['ok']);
      $state = array(
         'proc_type' => 'restore',
         'proc_key' => 'restore_' . $server . '_' . $table,
         'status' => $ok ? 'finished' : 'error',
         'phase' => 'restore_target',
         'message' => $ok ? 'restore finished' : ($result['msg'] ?? 'restore error'),
         'percent' => $ok ? 100 : 0,
         'step_percent' => $ok ? 100 : 0,
         'updated_at' => date('Y-m-d H:i:s'),
      );

      if ($ok) {
         $state['message'] = 'restore finished: ' . $this->backup_relative_file($result['file'] ?? $file);
      }

      return $this->render_process('Restore: ' . $server . '|' . $table, $state, '', $this->build_url('dd', 'restore_db'));
   }

   /**
    * Initialisiert einen Schema-Batch und startet die erste Ausfuehrung.
    *
    * @param string $act Eingabeparameter fuer diese Methode.
    * @param array $selects Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function start_batch($act, $selects) {
      $oDD = dbx()->get_system_obj('dbxDD');

      foreach ($selects as $selected) {
         if ($act == 'batch_dd_to_db' || $act == 'batch_dd_to_db_force') {
            $parts = $this->selected_dd_parts($selected);
            if (count($parts) != 2) {
               continue;
            }

            $oDD->sync_dd_to_db($parts[0], $parts[1], 'reset');
         }

         if ($act == 'batch_db_to_dd') {
            $parts = $this->decode_db_rid($selected);
            if (!$parts[0] || !$parts[1]) {
               continue;
            }

            $ddIndex = $this->get_dd_index_by_db($this->get_dd_records());
            $dds = $this->get_dd_records_for_db($ddIndex, $parts[0], $parts[1]);

            if ($dds) {
               $oDD->sync_db_to_dd($dds[0]['modul'], $dds[0]['dd'], 'reset', $parts[0], $parts[1]);
            } else {
               $oDD->sync_db_to_dd('dbx', $this->sanitize_dd_name($parts[1]), 'reset', $parts[0], $parts[1]);
            }
         }
      }

      $back = 'db';
      if ($act == 'batch_backup_db') $back = 'backup';
      if ($act == 'batch_restore_latest_db') $back = 'restore';

      $state = array(
         'proc_type' => 'schema_batch',
         'proc_key'  => 'schema_batch',
         'act'      => $act,
         'selects'  => array_values($selects),
         'pos'      => 0,
         'status'   => 'running',
         'phase'    => 'batch_step',
         'message'  => 'batch initialized',
         'percent'  => 0,
         'step_percent' => 0,
         'back'     => $back,
      );

      dbx()->set_remember_var('schema_batch', $state, 'dbxAdmin');
      return $this->run_batch();
   }

   /**
    * Liefert Modul/DD fuer eine Batch-Auswahl aus DD- oder DB-Report-Zeilen.
    *
    * @param string $selected Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function selected_dd_parts($selected) {
      $selected = (string)$selected;

      if (str_starts_with($selected, 'db_')) {
         [$server, $table] = $this->decode_db_rid($selected);
         if (!$server || !$table) {
            return array();
         }

         $ddIndex = $this->get_dd_index_by_db($this->get_dd_records());
         $dds = $this->get_dd_records_for_db($ddIndex, $server, $table);
         if (!$dds) {
            return array();
         }

         return array($dds[0]['modul'], $dds[0]['dd']);
      }

      $parts = explode('|', $selected, 2);
      return (count($parts) == 2) ? $parts : array();
   }

   /**
    * Setzt die Kindprozesse eines Batchs zurueck.
    *
    * @param array $state Eingabeparameter fuer diese Methode.
    * @return void
    */
   private function reset_batch_children($state) {
      if (!is_array($state) || empty($state['selects'])) {
         return;
      }

      $oDD = dbx()->get_system_obj('dbxDD');
      $act = $state['act'] ?? '';

      foreach ($state['selects'] as $selected) {
         if ($act == 'batch_dd_to_db' || $act == 'batch_dd_to_db_force') {
            $parts = $this->selected_dd_parts($selected);
            if (count($parts) != 2) {
               continue;
            }

            $oDD->sync_dd_to_db($parts[0], $parts[1], 'reset');
            continue;
         }

         if ($act == 'batch_db_to_dd') {
            $parts = $this->decode_db_rid($selected);
            if (!$parts[0] || !$parts[1]) {
               continue;
            }

            $ddIndex = $this->get_dd_index_by_db($this->get_dd_records());
            $dds = $this->get_dd_records_for_db($ddIndex, $parts[0], $parts[1]);
            $modul = $dds ? $dds[0]['modul'] : 'dbx';
            $dd = $dds ? $dds[0]['dd'] : $this->sanitize_dd_name($parts[1]);
            $oDD->sync_db_to_dd($modul, $dd, 'reset', $parts[0], $parts[1]);
         }
      }
   }

   /**
    * Erzeugt die Ruecksprung-URL fuer einen Batchprozess.
    *
    * @param string $backRun Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function batch_back_url($backRun) {
      if ($backRun == 'db') {
         return $this->build_url('db', 'list_db');
      }
      if ($backRun == 'backup') {
         return $this->build_url('dd', 'backup_db');
      }
      if ($backRun == 'restore') {
         return $this->build_url('dd', 'restore_db');
      }
      return $this->build_url('dd', 'list_dd');
   }

   /**
    * Verarbeitet Steuerbefehle fuer einen Batchprozess.
    *
    * @param array $state Eingabeparameter fuer diese Methode.
    * @param string $cmd Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function control_batch($state, $cmd) {
      $cmd = strtolower(trim((string)$cmd));
      $status = $state['status'] ?? 'running';

      if ($cmd == 'pause' && !in_array($status, array('finished', 'error', 'canceled'), true)) {
         $state['status'] = 'paused';
         $state['message'] = 'process paused';
      } elseif (($cmd == 'resume' || $cmd == 'continue') && in_array($status, array('paused', 'canceled'), true)) {
         $state['status'] = 'running';
         $state['message'] = ($cmd == 'continue') ? 'process continued' : 'process resumed';
      } elseif ($cmd == 'cancel' && !in_array($status, array('finished', 'error'), true)) {
         $state['status'] = 'canceled';
         $state['message'] = 'process canceled';
      } elseif ($cmd == 'restart') {
         $this->reset_batch_children($state);
         $state['pos'] = 0;
         $state['status'] = 'running';
         $state['phase'] = 'batch_step';
         $state['percent'] = 0;
         $state['step_percent'] = 0;
         $state['message'] = 'process restarted';
      }

      $state['updated_at'] = date('Y-m-d H:i:s');
      dbx()->set_remember_var('schema_batch', $state, 'dbxAdmin');
      return $state;
   }

   /**
    * Fuehrt den naechsten Schritt eines Schema-Batchprozesses aus.
    *
    * @return string
    */
   private function run_batch() {
      $state = dbx()->get_remember_var('schema_batch', array(), 'dbxAdmin');
      if (!is_array($state) || empty($state['selects'])) {
         return '<div class="alert alert-warning">Kein Batch aktiv.</div>';
      }

      $cmd = dbx()->get_modul_var('proc_cmd', '', 'parameter');
      if ($cmd && in_array($cmd, array('pause', 'resume', 'continue', 'cancel', 'restart'), true)) {
         $state = $this->control_batch($state, $cmd);
      }

      $total = count($state['selects']);
      $pos = (int)($state['pos'] ?? 0);
      $backRun = (string)($state['back'] ?? 'dd');
      if (!in_array($backRun, array('dd', 'db', 'backup', 'restore'), true)) {
         $backRun = 'dd';
      }
      $nextUrl = $this->build_url('dd', 'batch');
      $backUrl = $this->batch_back_url($backRun);

      if (in_array(($state['status'] ?? ''), array('paused', 'canceled', 'error'), true)) {
         return $this->render_process('Batch', $state, $nextUrl, $backUrl);
      }

      if ($pos >= $total) {
         $state['status'] = 'finished';
         $state['message'] = 'batch finished';
         $state['percent'] = 100;
         $state['step_percent'] = 100;
         dbx()->set_remember_var('schema_batch', array(), 'dbxAdmin');
         return $this->render_process('Batch', $state, '', $backUrl);
      }

      $current = $state['selects'][$pos];
      $oDD = dbx()->get_system_obj('dbxDD');
      $act = $state['act'] ?? '';

      $parts = in_array($act, array('batch_db_to_dd', 'batch_backup_db', 'batch_restore_latest_db'), true)
         ? $this->decode_db_rid($current)
         : $this->selected_dd_parts($current);

      if (($act == 'batch_dd_to_db' || $act == 'batch_dd_to_db_force') && count($parts) == 2) {
         $mode = ($act == 'batch_dd_to_db_force') ? 'force' : 'apply';
         $step = $oDD->sync_dd_to_db($parts[0], $parts[1], $mode);
         $state['step_percent'] = (int)($step['percent'] ?? 0);

         if (($step['status'] ?? '') == 'finished') {
            $state['pos'] = $pos + 1;
            $state['step_percent'] = 100;
         } elseif (($step['status'] ?? '') == 'error') {
            $state['status'] = 'error';
            $state['message'] = $current . ': ' . ($step['message'] ?? 'error');
         } else {
            $state['message'] = $current . ': ' . ($step['message'] ?? 'running');
         }
      } elseif ($act == 'batch_db_to_dd' && count($parts) == 2 && $parts[0] && $parts[1]) {
         $ddIndex = $this->get_dd_index_by_db($this->get_dd_records());
         $dds = $this->get_dd_records_for_db($ddIndex, $parts[0], $parts[1]);
         $modul = $dds ? $dds[0]['modul'] : 'dbx';
         $dd = $dds ? $dds[0]['dd'] : $this->sanitize_dd_name($parts[1]);
         $step = $oDD->sync_db_to_dd($modul, $dd, 'merge', $parts[0], $parts[1]);
         $state['step_percent'] = (int)($step['percent'] ?? 0);

         if (($step['status'] ?? '') == 'finished') {
            $state['pos'] = $pos + 1;
            $state['step_percent'] = 100;
         } elseif (($step['status'] ?? '') == 'error') {
            $state['status'] = 'error';
            $state['message'] = $current . ': ' . ($step['message'] ?? 'error');
         } else {
            $state['message'] = $current . ': ' . ($step['message'] ?? 'running');
         }
      } elseif ($act == 'batch_backup_db' && count($parts) == 2 && $parts[0] && $parts[1]) {
         $result = $this->backup_table($parts[0], $parts[1]);
         $state['step_percent'] = 100;
         if (!empty($result['ok'])) {
            $state['pos'] = $pos + 1;
            $state['message'] = $parts[0] . '|' . $parts[1] . ': Backup geschrieben';
         } else {
            $state['status'] = 'error';
            $state['message'] = $parts[0] . '|' . $parts[1] . ': ' . ($result['msg'] ?? 'backup error');
         }
      } elseif ($act == 'batch_restore_latest_db' && count($parts) == 2 && $parts[0] && $parts[1]) {
         $latest = $this->latest_backup($parts[0], $parts[1]);
         $file = (string)($latest['_file'] ?? '');
         $result = $file ? $this->restore_table_from_backup($file) : array('ok' => 0, 'msg' => 'Kein Backup gefunden');
         $state['step_percent'] = 100;
         if (!empty($result['ok'])) {
            $state['pos'] = $pos + 1;
            $state['message'] = $parts[0] . '|' . $parts[1] . ': Restore abgeschlossen';
         } else {
            $state['status'] = 'error';
            $state['message'] = $parts[0] . '|' . $parts[1] . ': ' . ($result['msg'] ?? 'restore error');
         }
      } elseif (in_array($act, array('batch_db_to_dd', 'batch_backup_db', 'batch_restore_latest_db'), true) && count($parts) == 2 && $parts[0] && !$parts[1]) {
         $state['pos'] = $pos + 1;
         $state['step_percent'] = 100;
         $state['message'] = $parts[0] . ': keine Tabelle, Schritt uebersprungen';
      } else {
         $state['status'] = 'error';
         $state['message'] = 'ungueltige Batch-Aktion';
      }

      $done = min($total, (int)$state['pos']);
      $partial = (($state['status'] ?? '') == 'running') ? max(0, min(100, (int)($state['step_percent'] ?? 0))) / 100 : 0;
      $state['percent'] = (int)floor((min($total, $done + $partial) / max(1, $total)) * 100);
      $state['updated_at'] = date('Y-m-d H:i:s');
      dbx()->set_remember_var('schema_batch', $state, 'dbxAdmin');

      $next = (($state['status'] ?? '') == 'running') ? $nextUrl : '';
      return $this->render_process('Batch', $state, $next, $backUrl);
   }

   /**
    * Zentraler Einstiegspunkt des dbxSchema-Moduls.
    *
    * @param string $mode Eingabeparameter fuer diese Methode.
    * @return string
    */
   public function run($mode = '') {
      $run2 = dbx()->get_modul_var('dbx_run2', '', 'parameter');
      if (!$mode) {
         $mode = dbx()->get_modul_var('dbx_run1', 'dd', 'parameter');
      }

      if ($run2 == 'sync_dd_to_db') {
         return $this->run_sync_dd_to_db();
      }

      if ($run2 == 'sync_db_to_dd') {
         return $this->run_sync_db_to_dd();
      }

      if ($run2 == 'transfer') {
         return $this->run_transfer();
      }

      if ($run2 == 'backup_db_table') {
         return $this->run_backup_db_table();
      }

      if ($run2 == 'restore_db_table') {
         return $this->run_restore_db_table();
      }

      if ($run2 == 'mapping') {
         return $this->run_mapping_editor();
      }

      if ($run2 == 'fields') {
         return $this->run_dd_fields_grid();
      }

      if ($run2 == 'data_read') {
         return $this->run_data_read();
      }

      if ($run2 == 'data_save') {
         return $this->run_data_save();
      }

      if ($run2 == 'data_insert') {
         return $this->run_data_insert();
      }

      if ($run2 == 'data_delete') {
         return $this->run_data_delete();
      }

      if ($run2 == 'fields_read') {
         return $this->run_dd_fields_read();
      }

      if ($run2 == 'fields_save') {
         return $this->run_dd_fields_save();
      }

      if ($run2 == 'fields_insert') {
         return $this->run_dd_fields_insert();
      }

      if ($run2 == 'fields_delete') {
         return $this->run_dd_fields_delete();
      }

      if ($run2 == 'batch') {
         return $this->run_batch();
      }

      if ($run2 == 'backup_db') {
         return $this->report_backup_db();
      }

      if ($run2 == 'restore_db') {
         return $this->report_restore_db();
      }

      if ($mode == 'db') {
         return $this->report_db();
      }

      return $this->report_dd();
   }
}
?>
