<?php
namespace dbx\dbxAdmin;

require_once dbx()->get_base_dir() . 'dbx/include/dbxForm.class.php';

class dbxSysMsg {

   /**
    * Liefert ausschließlich den zentral konfigurierten Fehlerprotokoll-Pfad.
    *
    * Der Pfad kommt nie aus Request-Daten. Dadurch können Dashboard und
    * Systemmeldungs-Report dieselbe sichere Dateioperation verwenden.
    */
   public function get_error_log_file(): string {
      return dbx()->get_file_dir() . 'dbxError.log';
   }

   /** Meldet, ob das zentrale Fehlerprotokoll als Datei vorhanden ist. */
   public function error_log_exists(): bool {
      return is_file($this->get_error_log_file());
   }

   /**
    * Löscht das vollständige zentrale Fehlerprotokoll.
    *
    * @return string `deleted`, `empty` oder `error`
    */
   public function delete_error_log(): string {
      $file = $this->get_error_log_file();

      if (!file_exists($file)) {
         return 'empty';
      }

      if (!is_file($file) || !@unlink($file)) {
         return 'error';
      }

      clearstatcache(true, $file);
      return file_exists($file) ? 'error' : 'deleted';
   }

   private function create_error_log_info($oReport) {
      $file = $this->get_error_log_file();

      if (!file_exists($file)) {
         $oReport->add_obj('error_log', 'dbxAdmin|sysmsg-error-log-empty');
         return;
      }

      $content = file_get_contents($file);

      if ($content === false) {
         $content = '';
      }

      $data = array(
         'file'    => 'files/dbxError.log',
         'size'    => number_format((float) filesize($file), 0, ',', '.'),
         'content' => htmlspecialchars($content, ENT_QUOTES, 'UTF-8'),
         'action'  => dbx()->action_url(
            '?dbx_modul=dbxAdmin&dbx_run1=sysmsg&dbx_run2=list_sysmsg'
            . '&dbx_do=delete_error_log&rid=error_log'
         ),
      );

      $oReport->add_obj('error_log', 'dbxAdmin|sysmsg-error-log-box', $data);
   }

   private function normalize_sys_msg_level($level): string {
      $level = strtolower(trim((string) $level));
      if ($level === 'warn') {
         $level = 'warning';
      }

      return in_array($level, array('error', 'warning', 'all'), true) ? $level : 'all';
   }

   private function sys_msg_level_config(): string {
      return $this->normalize_sys_msg_level(dbx()->get_cfg('dbx', 'sys_msg_level', 'all'));
   }

   private function sys_msg_level_options(string $current, $texts): string {
      $options = array(
         'error'   => $texts->get_fd_message('level_error_only'),
         'warning' => $texts->get_fd_message('level_error_warning'),
         'all'     => $texts->get_fd_message('level_all'),
      );
      $html = '';

      foreach ($options as $value => $label) {
         $selected = $value === $current ? ' selected' : '';
         $html .= '<option value="' . dbx()->esc($value) . '"' . $selected . '>' . dbx()->esc($label) . '</option>';
      }

      return $html;
   }

   private function set_sys_msg_level_config(string $level): bool {
      $config = dbx()->get_cfg('dbx');
      if (!is_array($config)) {
         $config = array();
      }

      $config['sys_msg_level'] = $this->normalize_sys_msg_level($level);
      return (int) dbx()->set_cfg('dbx', $config) > 0;
   }

   private function sys_msg_level_control(): string {
      $oForm = new \dbxForm();
      $oForm->init('admin-dashboard-sysmsg-level-control', 'admin-dashboard-sysmsg-level-control');
      $oForm->_fd = 'dbxAdmin|rpt-sysmsg-selection';
      $oForm->load_fd_messages();
      $oForm->_action = '?dbx_modul=dbxAdmin&dbx_run1=sysmsg&dbx_run2=sysmsg_level_save';
      $oForm->_msg_info = '';
      $current = $this->sys_msg_level_config();
      $oForm->add_rep('action', dbx()->esc($oForm->_action));
      $oForm->add_rep('sys_msg_level_save_base', dbx()->esc($oForm->_action));
      $oForm->add_rep(
         'sys_msg_level_options',
         $this->sys_msg_level_options($current, $oForm)
      );

      return $oForm->add_norep($oForm->run());
   }

   private function report_sysmsg() {
      $dd      = 'dbxSysMsg';
      $db      = dbx()->get_system_obj('dbxDB');
      $oReport = dbx()->get_system_obj('dbxReport');

      $oReport->init('report-sysmsg');
      $oReport->_fd = 'dbxAdmin|rpt-sysmsg-selection';
      $oReport->load_fd_messages();
      $oReport->_dd = $dd;
      $oReport->add_rep('report_shell_class', 'dbx-admin-dashboard-panel dbx-admin-dashboard-sysmsg-report');

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
         dbx()->debug("##delete tab dd=($dd) ok=($ok)");
         if ($ok) {
            $oReport->_msg_success = $oReport->get_fd_message(
               'delete_tab_success'
            );
         } else {
            $oReport->_msg_error = $oReport->get_fd_message(
               'delete_tab_error'
            );
         }
      }

      if ($work == 'delete_error_log') {
         $result = $this->delete_error_log();

         if ($result === 'empty') {
            $oReport->_msg_success = $oReport->get_fd_message(
               'error_log_empty'
            );
         } elseif ($result === 'deleted') {
            $oReport->_msg_success = $oReport->get_fd_message(
               'error_log_deleted'
            );
         } else {
            $oReport->_msg_error = $oReport->get_fd_message(
               'error_log_delete_error'
            );
         }
      }

      $flds['id']          = 'ID';
      $flds['create_date'] = $oReport->get_fd_message('column_created');
      $flds['level']       = $oReport->get_fd_message('column_level');
      $flds['status']      = $oReport->get_fd_message('column_status');
      $flds['about']       = $oReport->get_fd_message('column_context');
      $flds['modul']       = $oReport->get_fd_message('column_module');
      $flds['action']      = $oReport->get_fd_message('column_action');
      $flds['work']        = $oReport->get_fd_message('column_process');
      $flds['rid']         = 'RID';
      $flds['xuser']       = $oReport->get_fd_message('column_user');
      $flds['message']     = $oReport->get_fd_message('column_message');

      $rformat['create_date'] = 'php-datetime-usr';
      $oReport->_rpt_format   = $rformat;

      $oReport->_action            = '?dbx_modul=dbxAdmin&dbx_run1=sysmsg&dbx_run2=list_sysmsg';
      $oReport->_pages             = true;
      $oReport->_create_row_select = true;
      $oReport->_create_row_edit   = false;
      $oReport->_create_row_delete = true;
      $oReport->_create_sel_flds   = true;
      $oReport->_but_pagination    = 5;

      $oReport->enable_delete_tab($dd);

      $oReport->add_action('rows_delete'   , 'action_button_delete'  , '&dbx_do=multi_delete');

      $oReport->create_selection_fields('dbxAdmin|rpt-sysmsg-selection');
      $this->create_error_log_info($oReport);

      if ($work != 'delete_tab' && $work != 'delete_error_log' && $work != 'multi_delete' && $work != 'row_delete' && $oReport->submit()) {
         if (!$oReport->errors()) {
            $oReport->_msg_success = '';
         } else {
            $oReport->_msg_error = $oReport->get_fd_message(
               'validation_error'
            );
         }
      }

      $rwhere = $oReport->get_fld_val('dbx_rwhere' , ''           , 'sqlsearch|max=64');
      $rstatus= $oReport->get_fld_val('dbx_rstatus', 'all'        , 'parameter');
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
               'like'  => array('message', 'about', 'why', 'what', 'modul', 'action', 'work', 'rid'),
               'equal' => array('level', 'status', 'xuser'),
               'mode'  => 'contains',
            ),
         );
      }

      if ($rstatus != '' && $rstatus != 'all') {
         if (!is_array($rwhere)) {
            $rwhere = array();
         }

         $rwhere['status'] = $rstatus;
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

   private function process_sys_msg_level_action(): string {
      $level = dbx()->get_request_var('sys_msg_level', null, 'parameter');
      if ($level === null || $level === '') {
         $level = $_POST['sys_msg_level'] ?? 'all';
      }
      $this->set_sys_msg_level_config((string) $level);
      return $this->sys_msg_level_control();
   }

   /**
    * Client-Diagnose (JS dbx.diag) → dbxSysMsg via dbx()->sys_msg().
    * URL: ?dbx_modul=dbxAdmin&dbx_run1=sysmsg&dbx_run2=client_diag
    */
   private function client_diag() {
      header('Content-Type: application/json; charset=utf-8');

      $uid = (int) dbx()->user('id');

      $raw  = file_get_contents('php://input');
      $data = json_decode((string) $raw, true);
      if (!is_array($data)) {
         $data = $_POST;
      }

      $entries = array();
      if (isset($data['entries']) && is_array($data['entries'])) {
         $entries = $data['entries'];
      } elseif (is_array($data) && !empty($data)) {
         $entries = array($data);
      }

      $allowed = array('warning' => 1, 'error' => 1, 'security' => 1);
      $count   = 0;

      foreach ($entries as $entry) {
         if (!is_array($entry)) {
            continue;
         }

         $status = strtolower(trim((string) ($entry['level'] ?? 'warning')));
         if ($status === 'warn') {
            $status = 'warning';
         }
         if (!isset($allowed[$status])) {
            $status = 'warning';
         }

         $about   = trim((string) ($entry['lib'] ?? 'dbxClient'));
         $code    = trim((string) ($entry['code'] ?? ''));
         $message = trim((string) ($entry['message'] ?? ''));
         $element = trim((string) ($entry['element'] ?? ''));

         if ($about === '') {
            $about = 'dbxClient';
         }
         if ($message === '') {
            $message = $code !== '' ? $code : 'client diagnostic';
         }

         $rid = $code !== '' ? $code : $element;
         if ($rid === '') {
            $rid = 'client';
         }

         $detail = array(
            'element' => $element,
            'ctx'     => is_array($entry['ctx'] ?? null) ? $entry['ctx'] : array(),
            'page'    => (string) ($_SERVER['HTTP_REFERER'] ?? ($_SERVER['REQUEST_URI'] ?? '')),
            'uid'     => $uid,
         );

         dbx()->sys_msg($status, $about, $rid, $message, $detail);
         $count++;
      }

      echo json_encode(array('ok' => 1, 'count' => $count), JSON_UNESCAPED_UNICODE);
      exit;
   }

   public function run() {
      $run2 = dbx()->get_modul_var('dbx_run2', '', 'parameter');
      if ($run2 === 'client_diag') {
         return $this->client_diag();
      }
      if ($run2 === 'sysmsg_level_save') {
         if (dbx()->get_modul_var('dbx_do', '', 'parameter') !== '') {
            return $this->report_sysmsg();
         }
         return $this->process_sys_msg_level_action();
      }

      return $this->report_sysmsg();
   }
}
?>
