<?php
namespace dbx\dbxAdmin;

class dbxMissing {

   private function report_missing() {
      $content = '';
      $dd      = 'dbxMissing';

      $db      = dbx()->get_system_obj('dbxDB');
      $oReport = dbx()->get_system_obj('dbxReport');
      $oReport->init('report-missing');
      $oReport->_fd = 'dbxAdmin|rpt-missing-selection';
      $oReport->load_fd_messages();
      $oReport->_dd = $dd;

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

      $flds['id']          = 'ID';
      $flds['create_date'] = $oReport->get_fd_message('column_created');
      $flds['update_date'] = $oReport->get_fd_message('column_updated');
      $flds['count']       = $oReport->get_fd_message('column_count');
      $flds['missing']     = $oReport->get_fd_message('column_missing');
      $flds['request']     = $oReport->get_fd_message('column_request');

      $rformat['create_date'] = 'php-datetime-usr';
      $rformat['update_date'] = 'php-datetime-usr';
      $oReport->_rpt_format   = $rformat;

      $oReport->_action            = '?dbx_modul=dbxAdmin&dbx_run1=missing&dbx_run2=list_missing';
      $oReport->_pages             = true;
      $oReport->_create_row_select = true;
      $oReport->_create_row_edit   = false;
      $oReport->_create_row_delete = true;
      $oReport->_create_sel_flds   = true;
      $oReport->_but_pagination    = 5;

      $oReport->enable_delete_tab($dd);

      $oReport->add_action('rows_delete'   , 'action_button_delete'  , '&dbx_do=multi_delete');

      $oReport->create_selection_fields('dbxAdmin|rpt-missing-selection');

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
      $rsort  = $oReport->get_fld_val('dbx_rsort'  , 'update_date', 'parameter');
      $rdesc  = $oReport->get_fld_val('dbx_rdesc'  , 'DESC'       , 'parameter');
      $select = $oReport->get_fld_val('dbx_rselect', 0            , 'int');
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

         $rwhere = $oReport->add_rwhere_select($rwhere);
      }

      $oReport->_rflds     = $flds;
      $oReport->_rrows     = $rrows;
      $oReport->_rpos      = $rpos;
      $oReport->_count_all = $db->count($dd);
      $oReport->_rcount    = $db->count($dd, $rwhere);
      $oReport->_rdata     = $db->select($dd, $rwhere, $flds, $rsort, $rdesc, $rgroup, $rrows, $rpos);

      $content = $oReport->run();

      return $content;
   }

   public function run() {
      return $this->report_missing();
   }

}
?>
