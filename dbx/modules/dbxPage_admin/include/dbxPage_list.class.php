<?php
namespace dbx\dbxPage_admin;

class dbxPage_list {

  private function list_api() {
     $form_id ='report-api';
     $o_report = dbx()->get_system_obj('dbxReport');
     $o_report->init($form_id, 'report-api');

     $db      = dbx()->get_system_obj('dbxDB');
     $tab     = 'dbx_api';

     $flds['id']         ='ID';
     $flds['api']        ='Api';
     $flds['apikey']     ='Key';  
     $flds['modul']      ='Modul';
     $flds['action']     ='Action';
     $flds['work']       ='Work';
     $flds['count']      ='Count';
     $flds['max']        ='Max';
     $flds['runas']      ='UID';

     $options_rsort['id']        = 'ID';
     $options_rsort['modul']     = 'Modul ';
     $options_rsort['action']    = 'Action';
     $options_rsort['last_call'] = 'Letzer Aufruf';

     $data['dbx_rrows']= 25;
     $data['dbx_rsort']='id';

     $o_report->set_data($data);
     $o_report->set_action('?dbx_modul=ddbxPage_admin&dbx_run1=list');
     $o_report->_options_rsort = $options_rsort;
     $o_report->set_pagination(true, 9);
     $o_report->set_table_actions(array('select', 'edit', 'delete'));

     $o_report->_msg_info ='';
     $o_report->add_action('rows_select'    ,'action_button_select'    ,'&dbx_run2=multi_select');
     $o_report->add_action('rows_deselect'  ,'action_button_deselect'  ,'&dbx_run2=multi_deselect');
     $o_report->add_action('rows_delete'    ,'action_button_delete'    ,'&dbx_run2=multi_delete');
     $o_report->add_action('rows_activate'  ,'action_button_activate'  ,'&dbx_run2=multi_activate');
     $o_report->add_action('rows_deactivate','action_button_deactivate','&dbx_run2=multi_deactivate');

     $work=$o_report->get_post('dbx_run2','','parameter');
     $rid =$o_report->get_post('rid'     ,0 ,'int');

     if($o_report->submit()) {
       //dbx_debug("report-user submit");
       if(!$o_report->errors()) {      // submit && no errors
          if ($work == 'multi_delete') {
             $ok=$o_report->del_selected($tab,'*');
          } // multi_delete
          $o_report->_msg_success   = '';
       } else {
          $o_report->_msg_errr = 'Prüfen sie bitte ihre Eingaben';
       }
     }  else { // no submit
       if ($work == 'row_delete' && $rid) {
          $ok=$o_report->del_selected($tab,$rid);
          if ( $ok) $o_report->_msg_info = 'Zeile gelöscht';
          if (!$ok) $o_report->_msg_info = 'Zeile konnte nicht gelöscht werden';
       }
       if ($work == 'row_edit' && $rid) {
          // Aufruf Edit Formular als Rückgabewert
          $content='[modul=dbxApi_admin]dbx_run1=edit&rid='.$rid.'[/modul]';
          return $content;
       }
     }

     // get all selections and order
     $rgroup='';
     $rwhere=$o_report->get_fld_val('dbx_rwhere' ,'','varchar|trim');
     $rrows =$o_report->get_fld_val('dbx_rrows'  ,10,'int|min=1|max=1000');
     $rpos  =$o_report->get_fld_val('dbx_rpos'   ,0,'int|min=0');
     $rsort =$o_report->get_fld_val('dbx_rsort'  ,'id','parameter');
     $rdesc =strtoupper((string)$o_report->get_fld_val('dbx_rdesc','ASC','parameter'));
     if (!in_array($rdesc, array('ASC', 'DESC'), true)) $rdesc = 'ASC';
     $select=$o_report->get_fld_val('dbx_rselect',0,'int|min=0');

     //if ($rwhere) $rwhere="modul  LIKE '$rwhere%' or action  LIKE '$rwhere%' or work LIKE '$rwhere%' ";
     if ($select) $rwhere=$o_report->add_rwhere_select($rwhere);
     //dbx_debug("##Rwhere##  ($rwhere)");

     $o_report->set_report_counts(
        $db->count($tab, $rwhere),
        $db->count($tab)
     );
     $o_report->_rdata =$db->select($tab,$rwhere,$flds,$rsort,$rdesc,$rgroup,$rrows,$rpos);
     //$oReport->_rdata =$db->select($tab,$rwhere,'*',$rsort,$rdesc,$rgroup,$rrows,$rpos);


     $content=$o_report->run(1,$flds,'table');

     return $content;

  }


  public function run() {
     $uid   =dbx()->user();
     $mid   =dbx()->get_system_var('dbx_modul_id');
     $modul =dbx()->get_system_var('dbx_modul');
     $work  =dbx()->get_modul_var('dbx_run2','','parameter');

     $content=$this->list_api();

     return $content;
  } // run()

} // class

?>
