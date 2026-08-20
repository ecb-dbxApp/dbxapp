<?php
namespace dbx\dbxAdmin;

class dbxMissing {

   private function report_missing() {
      $content = '';
      $dd      = 'dbxMissing';

      $db      = dbx()->get_system_obj('dbxDB');
      $o_report = dbx()->get_system_obj('dbxReport');
      $o_report->init('report-missing');
      $o_report->set_field_definition('dbxAdmin|rpt-missing-selection');
      $o_report->load_fd_messages();
      $o_report->set_data_definition($dd);

      $work = dbx()->get_modul_var('dbx_do', '', 'parameter');
      $rid  = dbx()->get_modul_var('rid', 0, 'int');

      if ($work == 'row_delete' && $rid) {
         $ok = $o_report->del_selected($rid);
         $ok = $db->delete($dd, $rid);

         if ($ok) {
            $o_report->_msg_success = $o_report->get_fd_message(
               'row_delete_success'
            );
         } else {
            $o_report->_msg_error = $o_report->get_fd_message(
               'row_delete_error'
            );
         }
      }

      if ($work == 'multi_delete') {
         $result = $o_report->delete_multi_selected_records($dd);
         $o_report->apply_multi_delete_result($result);
      }

      if ($work == 'delete_tab') {
         $ok = $db->delete_tab($dd);
         dbx()->debug("##delete tab dd=($dd) ok=($ok)");
         if ($ok) {
            $o_report->_msg_success = $o_report->get_fd_message(
               'delete_tab_success'
            );
         } else {
            $o_report->_msg_error = $o_report->get_fd_message(
               'delete_tab_error'
            );
         }
      }

      $flds['id']          = 'ID';
      $flds['create_date'] = $o_report->get_fd_message('column_created');
      $flds['update_date'] = $o_report->get_fd_message('column_updated');
      $flds['count']       = $o_report->get_fd_message('column_count');
      $flds['missing']     = $o_report->get_fd_message('column_missing');
      $flds['request']     = $o_report->get_fd_message('column_request');

      $rformat['create_date'] = 'php-datetime-usr';
      $rformat['update_date'] = 'php-datetime-usr';
      $o_report->_rpt_format   = $rformat;

      $o_report->set_action('?dbx_modul=dbxAdmin&dbx_run1=missing&dbx_run2=list_missing');
      $o_report->_pages             = true;
      $o_report->_create_row_select = true;
      $o_report->_create_row_edit   = false;
      $o_report->_create_row_delete = true;
      $o_report->_create_sel_flds   = true;
      $o_report->_but_pagination    = 5;

      $o_report->enable_delete_tab($dd);

      $o_report->add_action('rows_delete'   , 'action_button_delete'  , '&dbx_do=multi_delete');

      $o_report->create_selection_fields('dbxAdmin|rpt-missing-selection');

      if ($work != 'delete_tab' && $work != 'multi_delete' && $work != 'row_delete' && $o_report->submit()) {
         if (!$o_report->errors()) {
            $o_report->_msg_success = '';
         } else {
            $o_report->_msg_error = $o_report->get_fd_message(
               'validation_error'
            );
         }
      }

      $rwhere = $o_report->get_fld_val('dbx_rwhere' , ''           , 'sqlsearch|max=64');
      $rrows  = $o_report->get_fld_val('dbx_rrows'  , 15           , 'int');
      $rpos   = $o_report->get_fld_val('dbx_rpos'   , 0            , 'int');
      $rsort  = $o_report->get_fld_val('dbx_rsort'  , 'update_date', 'parameter');
      $rdesc  = $o_report->get_fld_val('dbx_rdesc'  , 'DESC'       , 'parameter');
      $select = $o_report->get_fld_val('dbx_rselect', 0            , 'int');
      $rgroup = '';

      if ($rwhere != '') {
         $rwhere = array(
            'search' => array(
               'value' => $rwhere,
               'like'  => array('missing'),
               'mode'  => 'starts_with',
            ),
         );
      }

      if ($select) {
         if (is_array($rwhere)) {
            $rwhere = $db->normalize_where($dd, $rwhere);
         }

         $rwhere = $o_report->add_rwhere_select($rwhere);
      }

      $o_report->_rflds     = $flds;
      $o_report->_rrows     = $rrows;
      $o_report->_rpos      = $rpos;
      $o_report->_count_all = $db->count($dd);
      $o_report->_rcount    = $db->count($dd, $rwhere);
      $o_report->_rdata     = $db->select($dd, $rwhere, $flds, $rsort, $rdesc, $rgroup, $rrows, $rpos);

      $content = $o_report->run();

      return $content;
   }

   public function run() {
      return $this->report_missing();
   }

}
?>
