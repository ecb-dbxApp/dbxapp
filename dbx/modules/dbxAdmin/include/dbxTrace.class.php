<?php
namespace dbx\dbxAdmin;

dbx()->get_system_obj('dbxReport', 'use');

class dbxTrace {

   private $_trace_sys_fields = array(
      'create_date' => 1,
      'create_uid'  => 1,
      'update_date' => 1,
      'update_uid'  => 1,
      'owner'       => 1,
   );

   private function value_to_text($value) {
      if ($value === null) {
         return '';
      }

      if (is_bool($value)) {
         return $value ? 'true' : 'false';
      }

      if (is_array($value) || is_object($value)) {
         return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      }

      return (string) $value;
   }

   private function is_empty_value($value) {
      if ($value === null) {
         return true;
      }

      if (is_string($value) && trim($value) === '') {
         return true;
      }

      return false;
   }

   private function is_trace_sys_field($key) {
      return isset($this->_trace_sys_fields[$key]);
   }

   private function get_visible_values($data, $hideEmpty = true) {
      $values = array();
      $hiddenSys = 0;

      if (!is_array($data)) {
         return array($values, $hiddenSys);
      }

      foreach ($data as $key => $value) {
         if ($this->is_trace_sys_field($key)) {
            $hiddenSys++;
            continue;
         }

         if ($hideEmpty && $this->is_empty_value($value)) {
            continue;
         }

         $values[$key] = $value;
      }

      return array($values, $hiddenSys);
   }

   private function make_trace_summary($json, $action) {
      $data = json_decode((string) $json, true);

      if (!is_array($data)) {
         return array('summary' => 'Rohdaten', 'fields' => '', 'detail' => (string) $json);
      }

      $traceAction = $data['action'] ?? $action;
      $before      = $data['before'] ?? null;
      $delta       = $data['delta'] ?? null;
      $detail      = array();

      if ($traceAction == 'delete') {
         list($values, $hiddenSys) = $this->get_visible_values($before);
         $count = count($values);

         foreach ($values as $key => $value) {
            $detail[] = $key . ': ' . $this->value_to_text($value);
         }

         return array(
            'summary' => $count ? $count . ' Werte geloescht' : ($hiddenSys ? 'nur Systemfelder' : 'keine Werte'),
            'fields'  => implode(', ', array_slice(array_keys($values), 0, 4)),
            'detail'  => implode("\n", $detail),
         );
      }

      if ($traceAction == 'insert') {
         list($values, $hiddenSys) = $this->get_visible_values($delta);
         $count = count($values);

         foreach ($values as $key => $value) {
            $detail[] = $key . ': ' . $this->value_to_text($value);
         }

         return array(
            'summary' => $count ? $count . ' Werte gesetzt' : ($hiddenSys ? 'nur Systemfelder' : 'keine Werte'),
            'fields'  => implode(', ', array_slice(array_keys($values), 0, 4)),
            'detail'  => implode("\n", $detail),
         );
      }

      if (!is_array($delta)) {
         return array('summary' => 'keine Aenderung', 'fields' => '', 'detail' => '');
      }

      if (!is_array($before)) {
         $before = array();
      }

      $fields = array();
      $hiddenSys = 0;

      foreach ($delta as $key => $newValue) {
         if ($this->is_trace_sys_field($key)) {
            $hiddenSys++;
            continue;
         }

         $fields[] = $key;
         $oldValue = array_key_exists($key, $before) ? $before[$key] : null;
         $detail[] = $key . ': ' . $this->value_to_text($oldValue) . ' -> ' . $this->value_to_text($newValue);
      }

      $count = count($fields);

      return array(
         'summary' => $count ? $count . ' Felder geaendert' : ($hiddenSys ? 'nur Systemfelder' : 'keine Aenderung'),
         'fields'  => implode(', ', array_slice($fields, 0, 4)),
         'detail'  => implode("\n", $detail),
      );
   }

   private function render_trace_json($json, $action) {
      $summary = $this->make_trace_summary($json, $action);
      $title   = $summary['detail'];
      $payload = json_decode((string) $json, true);

      if (is_array($payload)) {
         $context = array();

         if (!empty($payload['source'])) {
            $context[] = 'source: ' . $payload['source'];
         }

         if (!empty($payload['modul'])) {
            $run = $payload['modul'];

            if (!empty($payload['run1'])) $run .= '/' . $payload['run1'];
            if (!empty($payload['run2'])) $run .= '/' . $payload['run2'];
            if (!empty($payload['run3'])) $run .= '/' . $payload['run3'];

            $context[] = 'run: ' . $run;
         }

         if ($context) {
            $title = implode("\n", $context) . ($title ? "\n\n" . $title : '');
         }
      }

      if ($title === '') {
         $title = $summary['summary'];
      }

      $html  = '<span class="dbx-trace-summary" data-dbx-tooltip="' . dbx()->esc($title) . '">';
      $html .= '<strong>' . dbx()->esc($summary['summary']) . '</strong>';

      if ($summary['fields']) {
         $html .= '<span class="dbx-trace-summary-fields">' . dbx()->esc($summary['fields']) . '</span>';
      }

      $html .= '</span>';

      return $html;
   }

   public function report_trace_row_action_data($oObj, $data) {
      if (!is_array($data) || ($data['type'] ?? '') != 'show') {
         return $data;
      }

      $record = $data['record'] ?? array();

      if (!is_array($record)) {
         return $data;
      }

      $dd  = isset($record['dd']) ? urlencode((string) $record['dd']) : '';
      $rid = isset($record['record_id']) ? urlencode((string) $record['record_id']) : '';

      $data['data']['action']  = '?dbx_modul=dbxAdmin&dbx_run1=trace&dbx_run2=record_history&trace_dd=' . $dd . '&trace_rid=' . $rid;
      $data['data']['class']   = 'openWin';
      $data['data']['tooltip'] = 'Historie anzeigen';

      return $data;
   }

   public function report_trace_body($oObj, $content) {
      $record = $oObj->get_record();
      $action = $record['action'] ?? '';

      if (isset($record['action'])) {
         $badge = 'secondary';

         if ($action == 'insert') $badge = 'success';
         if ($action == 'update') $badge = 'primary';
         if ($action == 'delete') $badge = 'danger';

         $record['action'] = '<span class="badge bg-' . $badge . '">' . dbx()->esc($action) . '</span>';
      }

      if (isset($record['data_json'])) {
         $record['data_json'] = $this->render_trace_json($record['data_json'], $action);
      }

      $oObj->_class_body['data_json'] = 'dbx-trace-data';
      $oObj->set_record($record);

      return $content;
   }

   private function get_trace_payload($json) {
      $data = json_decode((string) $json, true);

      return is_array($data) ? $data : array();
   }

   private function render_history_payload($row) {
      $payload = $this->get_trace_payload($row['data_json'] ?? '');
      $before  = $payload['before'] ?? array();
      $delta   = $payload['delta'] ?? array();
      $action  = $payload['action'] ?? ($row['action'] ?? '');

      if (!is_array($before)) {
         $before = array();
      }

      if (!is_array($delta)) {
         $delta = array();
      }

      $html = '<div class="dbx-trace-history-detail">';

      if ($action == 'update') {
         foreach ($delta as $key => $newValue) {
            $oldValue = array_key_exists($key, $before) ? $before[$key] : '';
            $html .= '<div class="dbx-trace-history-row">';
            $html .= '<strong>' . dbx()->esc($key) . '</strong>';
            $html .= '<span class="dbx-trace-old">' . dbx()->esc($this->value_to_text($oldValue)) . '</span>';
            $html .= '<span class="dbx-trace-arrow">&rarr;</span>';
            $html .= '<span class="dbx-trace-new">' . dbx()->esc($this->value_to_text($newValue)) . '</span>';
            $html .= '</div>';
         }
      } else {
         $values = ($action == 'delete') ? $before : $delta;

         foreach ($values as $key => $value) {
            if ($value === null || (is_string($value) && trim($value) === '')) {
               continue;
            }

            $html .= '<div class="dbx-trace-history-row">';
            $html .= '<strong>' . dbx()->esc($key) . '</strong>';
            $html .= '<span>' . dbx()->esc($this->value_to_text($value)) . '</span>';
            $html .= '</div>';
         }
      }

      $html .= '</div>';

      return $html;
   }

   private function get_compare_fields($before, $after) {
      $fields = array();

      if (is_array($before)) {
         foreach ($before as $key => $value) {
            $fields[$key] = 1;
         }
      }

      if (is_array($after)) {
         foreach ($after as $key => $value) {
            $fields[$key] = 1;
         }
      }

      return array_keys($fields);
   }

   private function render_compare_value($value) {
      $text = $this->value_to_text($value);

      if ($text === '') {
         return '<span class="dbx-trace-empty">leer</span>';
      }

      return dbx()->esc($text);
   }

   private function trace_detail($trace_id) {
      $row = $this->get_trace_row($trace_id);

      if (!$row) {
         return '<div class="container-fluid dbxReport dbx-trace-detail"><div class="alert alert-danger">Trace-Eintrag nicht gefunden.</div></div>';
      }

      $payload = $this->get_trace_payload($row['data_json'] ?? '');
      $action  = $payload['action'] ?? ($row['action'] ?? '');
      $before  = $payload['before'] ?? array();
      $after   = $payload['delta'] ?? array();
      $source  = $payload['source'] ?? '';
      $modul   = $payload['modul'] ?? '';
      $run1    = $payload['run1'] ?? '';
      $run2    = $payload['run2'] ?? '';
      $run3    = $payload['run3'] ?? '';

      if (!is_array($before)) {
         $before = array();
      }

      if (!is_array($after)) {
         $after = array();
      }

      $fields = $this->get_compare_fields($before, $after);

      $html  = '<div class="container-fluid dbxReport dbx-trace-detail">';
      $html .= '<h3>' . dbx()->esc($row['dd'] ?? '') . ' #' . dbx()->esc($row['record_id'] ?? '') . ' - ' . dbx()->esc($action) . '</h3>';
      $runPath = $modul;
      if ($run1) $runPath .= '/' . $run1;
      if ($run2) $runPath .= '/' . $run2;
      if ($run3) $runPath .= '/' . $run3;

      $html .= '<div class="mb-2 text-muted">' . dbx()->esc($row['create_date'] ?? '');

      if ($source || $runPath) {
         $html .= ' | ';
         if ($source) $html .= 'source: ' . dbx()->esc($source);
         if ($source && $runPath) $html .= ' | ';
         if ($runPath) $html .= 'run: ' . dbx()->esc($runPath);
      }

      $html .= '</div>';
      $html .= '<table class="table table-striped table-bordered table-light table-hover">';
      $html .= '<thead><tr><th>Feld</th><th>Vorher</th><th>Nachher</th></tr></thead><tbody>';

      foreach ($fields as $field) {
         $oldValue = array_key_exists($field, $before) ? $before[$field] : '';
         $newValue = array_key_exists($field, $after) ? $after[$field] : '';

         if ($action == 'delete') {
            $newValue = '';
         }

         $html .= '<tr>';
         $html .= '<th class="text-nowrap">' . dbx()->esc($field) . '</th>';
         $html .= '<td class="dbx-trace-before">' . $this->render_compare_value($oldValue) . '</td>';
         $html .= '<td class="dbx-trace-after">' . $this->render_compare_value($newValue) . '</td>';
         $html .= '</tr>';
      }

      if (!count($fields)) {
         $html .= '<tr><td colspan="3">Keine Feldwerte vorhanden.</td></tr>';
      }

      $html .= '</tbody></table>';
      $html .= '<div class="d-flex gap-2 justify-content-end">';

      if ($action == 'delete') {
         $html .= '<a class="btn btn-primary" href="?dbx_modul=dbxAdmin&dbx_run1=trace&dbx_run2=undelete&trace_id=' . dbx()->esc($row['id'] ?? '') . '">Undelete</a>';
      } elseif ($action == 'update') {
         $html .= '<a class="btn btn-primary" href="?dbx_modul=dbxAdmin&dbx_run1=trace&dbx_run2=restore&trace_id=' . dbx()->esc($row['id'] ?? '') . '">Vorherigen Stand wiederherstellen</a>';
      }

      $html .= '</div>';
      $html .= '</div>';

      return $html;
   }

   private function get_trace_row($trace_id) {
      $db   = dbx()->get_system_obj('dbxDB');
      $rows = $db->select('dbxTrace', array('id' => $trace_id), '*', 'id', 'DESC', '', 1, 0);

      if (is_array($rows) && isset($rows[0])) {
         return $rows[0];
      }

      return array();
   }

   private function restore_trace_before($trace_id) {
      $db  = dbx()->get_system_obj('dbxDB');
      $row = $this->get_trace_row($trace_id);

      if (!$row) {
         return '<div class="alert alert-danger">Trace-Eintrag nicht gefunden.</div>';
      }

      $payload = $this->get_trace_payload($row['data_json'] ?? '');
      $before  = $payload['before'] ?? array();
      $dd      = $row['dd'] ?? '';
      $rid     = $row['record_id'] ?? 0;

      if (!$dd || !$rid || !is_array($before) || !count($before)) {
         return '<div class="alert alert-warning">Dieser Stand kann nicht wiederhergestellt werden.</div>';
      }

      $pk = $db->get_dd_primary($dd);
      if (!$pk) {
         $pk = 'id';
      }

      $ok = $db->update($dd, $before, array($pk => $rid));

      if ($ok > 0) {
         return '<div class="alert alert-success">Stand wurde wiederhergestellt.</div>' . $this->record_history($dd, $rid);
      }

      return '<div class="alert alert-danger">Stand konnte nicht wiederhergestellt werden.</div>' . $this->record_history($dd, $rid);
   }

   private function undelete_trace_row($trace_id) {
      $db  = dbx()->get_system_obj('dbxDB');
      $row = $this->get_trace_row($trace_id);

      if (!$row) {
         return '<div class="alert alert-danger">Trace-Eintrag nicht gefunden.</div>';
      }

      $payload = $this->get_trace_payload($row['data_json'] ?? '');
      $before  = $payload['before'] ?? array();
      $dd      = $row['dd'] ?? '';
      $rid     = $row['record_id'] ?? 0;

      if (($row['action'] ?? '') != 'delete' || !$dd || !is_array($before) || !count($before)) {
         return '<div class="alert alert-warning">Dieser Datensatz kann nicht per Undelete reaktiviert werden.</div>';
      }

      $ok = $db->insert($dd, $before);

      if ($ok > 0) {
         return '<div class="alert alert-success">Datensatz wurde reaktiviert.</div>' . $this->record_history($dd, $rid);
      }

      return '<div class="alert alert-danger">Datensatz konnte nicht reaktiviert werden.</div>' . $this->record_history($dd, $rid);
   }

   private function record_history($dd = '', $rid = 0) {
      $db  = dbx()->get_system_obj('dbxDB');

      if (!$dd) {
         $dd = dbx()->get_modul_var('trace_dd', '', 'parameter');
      }

      if (!$rid) {
         $rid = dbx()->get_modul_var('trace_rid', 0, 'int');
      }

      $where = array(
         'dd'        => $dd,
         'record_id' => $rid,
      );

      $rows = $db->select('dbxTrace', $where, '*', 'create_date', 'DESC', '', 200, 0);

      if (!is_array($rows)) {
         $rows = array();
      }

      $html  = '<div class="container-fluid dbxReport dbx-trace-history">';
      $html .= '<h3>Historie ' . dbx()->esc($dd) . ' #' . dbx()->esc($rid) . '</h3>';
      $html .= '<table class="table table-striped table-bordered table-light table-hover">';
      $html .= '<thead><tr><th></th><th>Zeit</th><th>Aktion</th><th>Aenderung</th></tr></thead><tbody>';

      foreach ($rows as $row) {
         $action = $row['action'] ?? '';
         $detailUrl = '?dbx_modul=dbxAdmin&dbx_run1=trace&dbx_run2=trace_detail&trace_id=' . urlencode((string) ($row['id'] ?? ''));

         $html .= '<tr>';
         $html .= '<td class="td-show text-center align-middle">';
         $html .= '<a class="btn-inline" href="' . dbx()->esc($detailUrl) . '" data-dbx-tooltip="Trace-Detail anzeigen" data-url="' . dbx()->esc($detailUrl) . '" data-title="Trace Detail" data-width="1200" role="button"><i class="bi bi-search"></i></a>';
         $html .= '</td>';
         $html .= '<td class="text-nowrap">' . dbx()->esc($row['create_date'] ?? '') . '</td>';
         $html .= '<td>' . dbx()->esc($action) . '</td>';
         $html .= '<td>' . $this->render_history_payload($row) . '</td>';
         $html .= '</tr>';
      }

      if (!count($rows)) {
         $html .= '<tr><td colspan="4">Keine Historie gefunden.</td></tr>';
      }

      $html .= '</tbody></table></div>';

      return $html;
   }

   private function report_trace() {
      $dd      = 'dbxTrace';
      $db      = dbx()->get_system_obj('dbxDB');
      $oReport = dbx()->get_system_obj('dbxReport');

      $oReport->init('report-trace');
      $oReport->_fd = 'dbxAdmin|rpt-trace-selection';
      $oReport->load_fd_messages();
      $oReport->add_module_bar(
         $oReport->get_fd_message('bar_title'),
         'bi-clock-history',
         $oReport->get_fd_message('bar_subtitle')
      );
      $oReport->_dd = $dd;
      $oReport->set_tabel_tpl('tpl_row_show', 'dbxAdmin|table_row_trace_show');

      $work = dbx()->get_modul_var('dbx_do', '', 'parameter');
      $rid  = dbx()->get_modul_var('rid', 0, 'int');

      if ($work == 'row_delete' && $rid) {
         $ok = $oReport->del_selected($rid);
         $ok = $db->delete($dd, $rid);

         if ($ok) {
            $oReport->_msg_success = $oReport->get_fd_message(
               'row_delete_success'
            );
         } else {
            $oReport->_msg_error = $oReport->get_fd_message(
               'row_delete_error'
            );
         }
      }

      if ($work == 'multi_delete') {
         $result = $oReport->delete_multi_selected_records($dd);
         $oReport->apply_multi_delete_result($result);
      }

      if ($work == 'delete_tab') {
         $ok = $db->delete_tab($dd);
         $optimizeOk = $ok ? $db->optimize_tab($dd) : 0;
         dbx()->debug("##delete tab dd=($dd) ok=($ok)");
         dbx()->debug("##optimize tab dd=($dd) ok=($optimizeOk)");
         if ($ok && $optimizeOk) {
            $oReport->_msg_success = $oReport->get_fd_message(
               'delete_tab_success'
            );
         } elseif ($ok) {
            $oReport->_msg_success = $oReport->get_fd_message(
               'delete_tab_partial'
            );
         } else {
            $oReport->_msg_error = $oReport->get_fd_message(
               'delete_tab_error'
            );
         }
      }

      $flds['id']          = 'ID';
      $flds['create_date'] = $oReport->get_fd_message('column_created');
      $flds['action']      = $oReport->get_fd_message('column_action');
      $flds['dd']          = $oReport->get_fd_message('column_dd');
      $flds['record_id']   = $oReport->get_fd_message('column_record');
      $flds['owner']       = $oReport->get_fd_message('column_owner');
      $flds['data_json']   = $oReport->get_fd_message('column_change');

      $rformat['create_date'] = 'php-datetime-usr';
      $rformat['action']      = 'html';
      $rformat['data_json']   = 'html';
      $oReport->_rpt_format   = $rformat;

      $oReport->_action            = '?dbx_modul=dbxAdmin&dbx_run1=trace&dbx_run2=list_trace';
      $oReport->_pages             = true;
      $oReport->_create_row_select = true;
      $oReport->_create_row_edit   = false;
      $oReport->_create_row_show   = true;
      $oReport->_create_row_delete = true;
      $oReport->_create_sel_flds   = true;
      $oReport->_but_pagination    = 5;
      $oReport->set_callback_owner($this);
      $oReport->set_body_callback('report_trace_body');
      $oReport->set_callback('row_action_data', 'report_trace_row_action_data');

      $oReport->enable_delete_tab($dd);

      $oReport->add_action('rows_delete'   , 'action_button_delete'  , '&dbx_do=multi_delete');

      $oReport->create_selection_fields('dbxAdmin|rpt-trace-selection');
      $oReport->add_module_bar(
         $oReport->get_fd_message('bar_title'),
         'bi-clock-history',
         $oReport->get_fd_message('bar_subtitle')
      );

      if ($work != 'delete_tab' && $work != 'multi_delete' && $work != 'row_delete' && $oReport->submit()) {
         if (!$oReport->errors()) {
            $oReport->_msg_success = '';
         } else {
            $oReport->_msg_error = $oReport->get_fd_message(
               'validation_error'
            );
         }
      }

      $rwhere = $oReport->get_fld_val('dbx_rwhere' , ''           , 'sqlsearch|max=64');
      $rrows  = $oReport->get_fld_val('dbx_rrows'  , 15           , 'int');
      $rpos   = $oReport->get_fld_val('dbx_rpos'   , 0            , 'int');
      $rsort  = $oReport->get_fld_val('dbx_rsort'  , 'create_date', 'parameter');
      $rdesc  = $oReport->get_fld_val('dbx_rdesc'  , 'DESC'       , 'parameter');
      $select = $oReport->get_fld_val('dbx_rselect', 0            , 'int');
      $rgroup = '';

      if ($rwhere != '') {
         $rwhere = array(
            'search' => array(
               'value' => $rwhere,
               'like'  => array('action', 'dd', 'data_json'),
               'equal' => array('record_id', 'owner'),
               'mode'  => 'contains',
            ),
         );
      }

      if ($select) {
         if (is_array($rwhere)) {
            $rwhere = $db->normalize_where($dd, $rwhere);
         }

         $rwhere = $oReport->add_rwhere_select($rwhere);
      }

      $oReport->_rflds     = $flds;
      $oReport->_rrows     = $rrows;
      $oReport->_rpos      = $rpos;
      $oReport->_count_all = $db->count($dd);
      $oReport->_rcount    = $db->count($dd, $rwhere);
      $oReport->_rdata     = $db->select($dd, $rwhere, $flds, $rsort, $rdesc, $rgroup, $rrows, $rpos);

      return $oReport->run();
   }

   public function run() {
      $run2 = dbx()->get_modul_var('dbx_run2', '', 'parameter');

      if ($run2 == 'record_history') {
         return $this->record_history();
      }

      if ($run2 == 'trace_detail') {
         $trace_id = dbx()->get_modul_var('trace_id', 0, 'int');
         return $this->trace_detail($trace_id);
      }

      if ($run2 == 'restore') {
         $trace_id = dbx()->get_modul_var('trace_id', 0, 'int');
         return $this->restore_trace_before($trace_id);
      }

      if ($run2 == 'undelete') {
         $trace_id = dbx()->get_modul_var('trace_id', 0, 'int');
         return $this->undelete_trace_row($trace_id);
      }

      return $this->report_trace();
   }
}
?>
