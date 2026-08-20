<?php
namespace dbx\dbxAdmin;

trait dbxSchemaDataReportServiceTrait {



   /**
    * Erzeugt die Report-Feldliste aus einer DD-Definition.
    *
    * @param string $ddRef Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function data_report_fields($dd_ref) {
      $o_dd = dbx()->get_system_obj('dbxDD');
      $fields = $o_dd->get_dd_fields($dd_ref);
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
   private function data_grid_cols($dd_ref, $primary) {
      $o_db = dbx()->get_system_obj('dbxDB');
      $fields = $o_db->get_dd_fields($dd_ref);
      $cols = array();

      foreach ((array)$fields as $field) {
         $name = (string)($field['name'] ?? '');
         if ($name === '' || str_starts_with($name, '_')) {
            continue;
         }

         $label = trim((string)($field['label'] ?? ''));
         $label = str_replace(array(':', '[', ']'), '-', $label ?: $name);
         $field_type = strtolower(trim((string)($field['type'] ?? '')));
         $grid_type = $o_db->map_dd_type_to_grid_type($field_type ?: 'text');
         $protect = isset($field['protect']) ? (string)$field['protect'] : '0';

         if ($protect === '2') {
            continue;
         }

         $flag = ($name === $primary || $protect === '1') ? 'p' : '';
         $options = array();

         if ($name === 'content' || in_array($field_type, array('mediumtext', 'longtext'), true)) {
            $options[] = 'editor=textarea';
            $options[] = 'formatter=truncate';
            $options[] = 'bigEditor=1';
            $options[] = 'maxChars=180';
            $options[] = 'width=420';
            $options[] = 'minWidth=260';
         }

         $col = $name . '[' . $label . ']:' . $grid_type;
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
      $dd_ref = '';

      if ($mode == 'db') {
         [$server, $table] = $this->decode_db_rid($rid);
         if ($server && $table) {
            $dd_ref = $this->ensure_db_view_dd($server, $table);
         }
      } else {
         [$modul, $dd] = $this->split_dd_rid($rid);
         if (!$modul || !$dd) {
            [$modul, $dd] = $this->dd_params_from_request();
         }
         if ($modul && $dd) {
            $dd_ref = $this->dd_ref($modul, $dd);
         }
      }

      $o_db = dbx()->get_system_obj('dbxDB');
      $flds = $dd_ref ? $this->data_report_fields($dd_ref) : array();
      $primary = $dd_ref ? $o_db->get_dd_primary($dd_ref) : 'id';

      if (!$primary || !isset($flds[$primary])) {
         $primary = isset($flds['id']) ? 'id' : '';
      }

      return array($dd_ref, $flds, $primary);
   }



   /**
    * Liest einen einzelnen Datensatz anhand des Primaerschluessels.
    *
    * @param string $ddRef Eingabeparameter fuer diese Methode.
    * @param string $primary Eingabeparameter fuer diese Methode.
    * @param int $id Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function data_row_by_id($dd_ref, $primary, $id) {
      if (!$dd_ref || !$primary || $id === '' || $id === null) {
         return array();
      }

      $o_db = dbx()->get_system_obj('dbxDB');
      $rows = $o_db->select($dd_ref, array($primary => $id), '', '', 'ASC', '', 1, 0, 0);
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
   private function run_data_report($dd_ref, $title, $read_url, $save_url, $insert_url, $delete_url) {
      $o_db = dbx()->get_system_obj('dbxDB');
      $o_report = dbx()->get_system_obj('dbxReport');
      $flds = $this->data_report_fields($dd_ref);
      $primary = $o_db->get_dd_primary($dd_ref);

      if (!$flds || !$primary || !isset($flds[$primary])) {
         return '<div class="alert alert-warning">Keine editierbare Felddefinition mit Primaerschluessel vorhanden.</div>';
      }

      $o_report->init('report-schema-data-' . substr(md5($dd_ref), 0, 10), 'schema-data-report');
      $o_report->_rflds = $flds;
      $o_report->set_mode('tabulator');
      $o_report->_rrows = 'auto';
      $o_report->_grid_id = 'schema_data_' . substr(md5($dd_ref), 0, 10);
      $o_report->_grid_cols = $this->data_grid_cols($dd_ref, $primary);
      $o_report->_grid_layout = 'fitData';
      $o_report->_grid_read_url = $read_url;
      $o_report->_grid_save_url = $save_url;
      $o_report->_grid_insert_url = $insert_url;
      $o_report->_grid_delete_url = $delete_url;
      $o_report->add_obj('title', 'obj-value', $this->esc($title));
      $o_report->add_obj('subtitle', 'obj-value', $this->esc('Direkt editierbares Grid'));
      $o_report->add_rep('bar_title', $title);
      $o_report->add_rep('bar_subtitle', 'Direkt editierbares Grid');
      $o_report->add_obj('primary', 'obj-value', $this->esc($primary));

      return $o_report->run();
   }



   /**
    * Liefert Grid-Daten als JSON fuer den Dateneditor.
    *
    * @return void
    */
   private function run_data_read() {
      [$dd_ref, $flds, $primary] = $this->data_context_from_request();
      if (!$dd_ref || !$flds || !$primary) {
         $this->json_response(array('ok' => 0, 'count' => 0, 'rows' => array(), 'msg' => 'Keine Felddefinition vorhanden.'));
      }

      $o_db = dbx()->get_system_obj('dbxDB');
      $rows = $o_db->select($dd_ref, '', array_keys($flds), '', 'ASC', '', 0, 0, 0);
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

      $count = $o_db->count($dd_ref, '');
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
      [$dd_ref, $flds, $primary] = $this->data_context_from_request();
      $payload = $this->request_json();
      $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : array();

      if (!$dd_ref || !$flds || !$primary) {
         $this->json_response(array('ok' => 0, 'msg' => 'Keine editierbare Felddefinition vorhanden.'));
      }

      $o_db = dbx()->get_system_obj('dbxDB');
      $ok_count = 0;

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

         $ok = $o_db->update($dd_ref, $values, array($primary => $id), 1, 1, 1, 1);
         if ($ok === 1 && $o_db->_update_count > 0) {
            $ok_count++;
         }
      }

      $this->json_response(array('ok' => 1, 'success' => true, 'count' => $ok_count));
   }



   /**
    * Legt einen neuen Datensatz fuer den Dateneditor an.
    *
    * @return void
    */
   private function run_data_insert() {
      [$dd_ref, $flds, $primary] = $this->data_context_from_request();
      if (!$dd_ref || !$flds || !$primary) {
         $this->json_response(array('ok' => 0, 'msg' => 'Keine editierbare Felddefinition vorhanden.'));
      }

      $o_db = dbx()->get_system_obj('dbxDB');
      $id = 0;
      if ($o_db->insert($dd_ref, array(), 1, 1, 1, 1) === 1) {
         $id = $o_db->get_insert_id();
      }
      if ($id <= 0) {
         $this->json_response(array('ok' => 0, 'msg' => 'Datensatz konnte nicht angelegt werden.'));
      }

      $row = $this->data_row_by_id($dd_ref, $primary, $id);
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
      [$dd_ref, $flds, $primary] = $this->data_context_from_request();
      $payload = $this->request_json();
      $id = $payload['id'] ?? null;

      if (!$dd_ref || !$flds || !$primary || $id === null || $id === '') {
         $this->json_response(array('ok' => 0, 'msg' => 'Datensatz-ID fehlt.'));
      }

      $o_db = dbx()->get_system_obj('dbxDB');
      $ok = $o_db->delete($dd_ref, array($primary => $id), 1, 1);
      $this->json_response(array('ok' => $ok > 0 ? 1 : 0, 'success' => $ok > 0));
   }
}
