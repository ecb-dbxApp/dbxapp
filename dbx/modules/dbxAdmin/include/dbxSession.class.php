<?php
namespace dbx\dbxAdmin;

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
Class dbxSession {

   private function report_sessions() {
      $oDB = dbx()->get_system_obj('dbxDB');
      $form ='report-sessions';
      $dd   ='dbxSession';
      $rid  = dbx()->get_modul_var('rid'     ,0 ,'int');
      $work = dbx()->get_modul_var('dbx_do'  ,'','parameter');
      dbx()->debug("####Work=($work) RID=($rid)####");
      
      $data=array(); // additional data for the formular

      $oReport = dbx()->get_system_obj('dbxReport');
      $oReport->init($form);
      $oReport->_fd = 'dbxAdmin|rpt-sessions-selection';
      $oReport->load_fd_messages();
      $oReport->_dd = $dd;
      $oReport->add_rep('report_shell_class', 'dbx-admin-dashboard-panel dbx-admin-dashboard-session-report');
      $oReport->_msg_info    = '';
      $oReport->_msg_success = '';
      $oReport->_msg_error   = '';

      if ($work == 'row_delete' && $rid) {
           $ok=$oReport->del_selected($rid);
           $ok=$oDB->delete('dbxSession',$rid);
           dbx()->debug("##delete session id=($rid) ok=($ok)");
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
           $ok=$oDB->delete_tab($dd);
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
 
      

      $flds['id']              ='ID';
      $flds['update_date']     = $oReport->get_fd_message('column_access');
      $flds['userid']          = $oReport->get_fd_message('column_user');
      $flds['ip']              = $oReport->get_fd_message('column_ip');
      $flds['design']          = $oReport->get_fd_message('column_design');
      $flds['page']            = $oReport->get_fd_message('column_page');
      $flds['modul']           = $oReport->get_fd_message('column_module');
      $flds['run1']            ='Run1';
      $flds['run2']            ='Run2';
      $flds['edit']            = $oReport->get_fd_message('column_edit');
      $flds['color']           = $oReport->get_fd_message('column_color');
      $flds['language']        = $oReport->get_fd_message('column_language');
      $flds['request_counter'] = $oReport->get_fd_message('column_requests');

      $oReport->_data  = $data;

      $oReport->_action = '?dbx_modul=dbxAdmin&dbx_run1=session&dbx_run2=list_session';
      $oReport->_pages            = true;
      $oReport->_create_row_select= true;
      $oReport->_create_row_edit  = false;
      $oReport->_create_row_delete= true;
      $oReport->_create_sel_flds  = true;
      $oReport->_but_pagination   = 7;

      $oReport->enable_delete_tab($dd);

      $oReport->add_action('rows_delete'    ,'action_button_delete'    ,'&dbx_do=multi_delete');

      $oReport->create_selection_fields('dbxAdmin|rpt-sessions-selection');

      if ($work != 'delete_tab' && $work != 'multi_delete' && $work != 'row_delete' && $oReport->submit()) {
        dbx()->debug("###MODUL Report-submit  ### POST=", $_POST ,$_GET);
        if(!$oReport->errors()) {      // submit && no errors
            $oReport->_msg_success   = '';
        } else {
           $oReport->_msg_error = $oReport->get_fd_message(
              'validation_error'
           );
        }
      }  
      
      // get all selections and order
      $rwhere=$oReport->get_fld_val('dbx_rwhere' ,''   ,'sqlsearch|max=32');
      $rsort =$oReport->get_fld_val('dbx_rsort'  ,'update_date' ,'parameter');
      $rdesc =$oReport->get_fld_val('dbx_rdesc'  ,'DESC','parameter');
      $select=$oReport->get_fld_val('dbx_rselect', 0   ,'int');
      $rgroup=$oReport->get_fld_val('dbx_rgroup' ,''   ,'parameter');
      $rrows =$oReport->get_fld_val('dbx_rrows'  ,10   ,'int');
      $rpos  =$oReport->get_fld_val('dbx_rpos'   , 0   ,'int');

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
              $rwhere = $oDB->normalize_where($dd, $rwhere);
          }
          $rwhere=$oReport->add_rwhere_select($rwhere);
      }
      //dbx_debug("##SQL-RPT##  ($rwhere)   Sort=($rsort) UpDown=($rdesc) Group=($rgroup) Rows=($rrows) R-Pos=($rpos) Sel=($select)");
      $oReport->_rflds = $flds;
      $oReport->_rrows = $rrows;
      $oReport->_rpos  = $rpos;
      $oReport->_count_all = $oDB->count($dd);
      $oReport->_rcount=$oDB->count($dd,$rwhere);
      $oReport->_rdata =$oDB->select($dd,$rwhere,$flds,$rsort,$rdesc,$rgroup,$rrows,$rpos);
      //dbx_debug("RDATA",$oReport->_rdata);

 

      $content=$oReport->run($flds,'table');

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
