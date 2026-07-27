<?php
namespace dbx\dbxPage_admin;

class dbxPage_list {

  private function list_api() {
     $form_id ='report-api';
     $oReport = dbx()->get_system_obj('dbxReport');
     $oReport->init($form_id);

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

     $oReport->_data   =$data;
     $oReport->_action ='?dbx_modul=ddbxPage_admin&dbx_run1=list';
     $oReport->_options_rsort = $options_rsort;
     $oReport->_but_pagination   =9;
     $oReport->_create_row_select=true;
     $oReport->_create_row_edit  =true;
     $oReport->_create_row_delete=true;

     $oReport->_msg_info ='';
     $oReport->add_action('rows_select'    ,'action_button_select'    ,'&dbx_run2=multi_select');
     $oReport->add_action('rows_deselect'  ,'action_button_deselect'  ,'&dbx_run2=multi_deselect');
     $oReport->add_action('rows_delete'    ,'action_button_delete'    ,'&dbx_run2=multi_delete');
     $oReport->add_action('rows_activate'  ,'action_button_activate'  ,'&dbx_run2=multi_activate');
     $oReport->add_action('rows_deactivate','action_button_deactivate','&dbx_run2=multi_deactivate');

     $work=$oReport->get_post('dbx_run2','','parameter');
     $rid =$oReport->get_post('rid'     ,0 ,'int');

     if($oReport->submit()) {
       //dbx_debug("report-user submit");
       if(!$oReport->errors()) {      // submit && no errors
          if ($work == 'multi_delete') {
             $ok=$oReport->del_selected($tab,'*');
          } // multi_delete
          $oReport->_msg_success   = '';
       } else {
          $oReport->_msg_errr = 'Prüfen sie bitte ihre Eingaben';
       }
     }  else { // no submit
       if ($work == 'row_delete' && $rid) {
          $ok=$oReport->del_selected($tab,$rid);
          if ( $ok) $oReport->_msg_info = 'Zeile gelöscht';
          if (!$ok) $oReport->_msg_info = 'Zeile konnte nicht gelöscht werden';
       }
       if ($work == 'row_edit' && $rid) {
          // Aufruf Edit Formular als Rückgabewert
          $content='[modul=dbxApi_admin]dbx_run1=edit&rid='.$rid.'[/modul]';
          return $content;
       }
     }

     // get all selections and order
     $rgroup='';
     $rwhere=$oReport->get_sel('dbx_rwhere' ,'');
     $rrows =$oReport->get_sel('dbx_rrows'  ,10);
     $rpos  =$oReport->get_sel('dbx_rpos'   ,0);
     $rsort =$oReport->get_sel('dbx_rsort'  ,'id');
     $rdesc =$oReport->get_sel('dbx_rdesc'  ,'ASC');
     $select=$oReport->get_sel('dbx_rselect',0);

     //if ($rwhere) $rwhere="modul  LIKE '$rwhere%' or action  LIKE '$rwhere%' or work LIKE '$rwhere%' ";
     if ($select) $rwhere=$oReport->add_rwhere_select($rwhere);
     //dbx_debug("##Rwhere##  ($rwhere)");

     $oReport->_rcount=$db->count($tab,$rwhere);
     $oReport->_rdata =$db->select($tab,$rwhere,$flds,$rsort,$rdesc,$rgroup,$rrows,$rpos);
     //$oReport->_rdata =$db->select($tab,$rwhere,'*',$rsort,$rdesc,$rgroup,$rrows,$rpos);


     $content=$oReport->run(1,$flds,'table');

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
