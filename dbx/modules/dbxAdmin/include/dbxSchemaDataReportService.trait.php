<?php
namespace dbx\dbxAdmin;

trait dbxSchemaDataReportServiceTrait {



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
}
