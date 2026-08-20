<?php
namespace dbx\dbxAdmin;

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
Class dbxSession {

   private function report_sessions() {
      $o_db = dbx()->get_system_obj('dbxDB');
      $form ='report-sessions';
      $dd   ='dbxSession';
      $rid  = dbx()->get_modul_var('rid'     ,0 ,'int');
      $work = dbx()->get_modul_var('dbx_do'  ,'','parameter');
      dbx()->debug("####Work=($work) RID=($rid)####");
      
      $data=array(); // additional data for the formular

      $o_report = dbx()->get_system_obj('dbxReport');
      $o_report->init($form);
      $o_report->set_field_definition('dbxAdmin|rpt-sessions-selection');
      $o_report->load_fd_messages();
      $o_report->set_data_definition($dd);
      $o_report->add_rep('report_shell_class', 'dbx-admin-dashboard-panel dbx-admin-dashboard-session-report');
      $o_report->_msg_info    = '';
      $o_report->_msg_success = '';
      $o_report->_msg_error   = '';

      if ($work == 'row_delete' && $rid) {
           $ok=$o_report->del_selected($rid);
           $ok=$o_db->delete('dbxSession',$rid);
           dbx()->debug("##delete session id=($rid) ok=($ok)");
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
           $ok=$o_db->delete_tab($dd);
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
 
      

      $flds['id']              ='ID';
      $flds['update_date']     = $o_report->get_fd_message('column_access');
      $flds['userid']          = $o_report->get_fd_message('column_user');
      $flds['ip']              = $o_report->get_fd_message('column_ip');
      $flds['design']          = $o_report->get_fd_message('column_design');
      $flds['page']            = $o_report->get_fd_message('column_page');
      $flds['modul']           = $o_report->get_fd_message('column_module');
      $flds['run1']            ='Run1';
      $flds['run2']            ='Run2';
      $flds['edit']            = $o_report->get_fd_message('column_edit');
      $flds['color']           = $o_report->get_fd_message('column_color');
      $flds['language']        = $o_report->get_fd_message('column_language');
      $flds['request_counter'] = $o_report->get_fd_message('column_requests');

      $o_report->set_data($data);

      $o_report->set_action('?dbx_modul=dbxAdmin&dbx_run1=session&dbx_run2=list_session');
      $o_report->_pages            = true;
      $o_report->_create_row_select= true;
      $o_report->_create_row_edit  = false;
      $o_report->_create_row_delete= true;
      $o_report->_create_sel_flds  = true;
      $o_report->_but_pagination   = 7;

      $o_report->enable_delete_tab($dd);

      $o_report->add_action('rows_delete'    ,'action_button_delete'    ,'&dbx_do=multi_delete');

      $o_report->create_selection_fields('dbxAdmin|rpt-sessions-selection');

      if ($work != 'delete_tab' && $work != 'multi_delete' && $work != 'row_delete' && $o_report->submit()) {
        dbx()->debug("###MODUL Report-submit  ### POST=", $_POST ,$_GET);
        if(!$o_report->errors()) {      // submit && no errors
            $o_report->_msg_success   = '';
        } else {
           $o_report->_msg_error = $o_report->get_fd_message(
              'validation_error'
           );
        }
      }  
      
      // get all selections and order
      $rwhere=$o_report->get_fld_val('dbx_rwhere' ,''   ,'sqlsearch|max=32');
      $rsort =$o_report->get_fld_val('dbx_rsort'  ,'update_date' ,'parameter');
      $rdesc =$o_report->get_fld_val('dbx_rdesc'  ,'DESC','parameter');
      $select=$o_report->get_fld_val('dbx_rselect', 0   ,'int');
      $rgroup=$o_report->get_fld_val('dbx_rgroup' ,''   ,'parameter');
      $rrows =$o_report->get_fld_val('dbx_rrows'  ,10   ,'int');
      $rpos  =$o_report->get_fld_val('dbx_rpos'   , 0   ,'int');

      if ($rwhere!='') {
          $rwhere = array(
              'search' => array(
                  'value' => $rwhere,
                  'like'  => array('modul', 'run1', 'run2'),
                  'equal' => array('userid'),
                  'mode'  => 'starts_with',
              ),
          );
      }
      if ($select) {
          if (is_array($rwhere)) {
              $rwhere = $o_db->normalize_where($dd, $rwhere);
          }
          $rwhere=$o_report->add_rwhere_select($rwhere);
      }
      //dbx_debug("##SQL-RPT##  ($rwhere)   Sort=($rsort) UpDown=($rdesc) Group=($rgroup) Rows=($rrows) R-Pos=($rpos) Sel=($select)");
      $o_report->_rflds = $flds;
      $o_report->_rrows = $rrows;
      $o_report->_rpos  = $rpos;
      $o_report->_count_all = $o_db->count($dd);
      $o_report->_rcount=$o_db->count($dd,$rwhere);
      $o_report->_rdata =$o_db->select($dd,$rwhere,$flds,$rsort,$rdesc,$rgroup,$rrows,$rpos);
      //dbx_debug("RDATA",$oReport->_rdata);

 

      $content=$o_report->run($flds,'table');

      return $content;
   } // report_content_flat()

   // - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

   public function run() {
      $run=dbx()->get_modul_var('dbx_run2', 'list_session', 'parameter');
      $content='undefined run';
      switch ($run) {

        case '':
        case 'list_session':
            $content=$this->report_sessions();
            break;

      }
      return $content;
   }

} // class




?>
