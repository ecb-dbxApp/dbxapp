<?php
namespace dbx\dbxAdmin;

trait dbxSchemaDdFieldServiceTrait {



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
}
