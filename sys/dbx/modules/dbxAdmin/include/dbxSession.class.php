<?php
namespace dbx\dbxAdmin;


dbx_use_sys_class('dbxReport');

class dbxReport_Session extends \dbxReport {
    public function __run_body($content) {
        $current_sid=dbx_get_CurrentUser('sessid');
        $record =$this->_record;
        $sid    =$record['sessid'];
        if ($sid == $current_sid) {
           $this->_record=$_SESSION['dbx']['record']; // kompletter Datensatz
        }
        return $content;
    }
}



// - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
Class dbxSession {

   private function delete_data($id) {
      $db  = dbx_get_sys_object('dbxDB');
      $ok=$db->delete('dbx_session',$id,1,1);
      return $ok;
   }


   private function report_sessions() {
      $form ='report-sessions';
      $tab  ='dbx_session';
      $oReport = new dbxReport_Session;
      $oReport->init($form);
      //if ($oReport->set_form_selects()) return $oReport->get_count_selects(); // fast retval;

      $db   = dbx_get_sys_object('dbxDB');
      $lng  = dbx_get_ModulVar('lng','de');


      $flds['id']              ='ID';
      $flds['update_date']     ='Zugriff';
      $flds['userid']          ='User';
      $flds['ip']              ='IP';
      $flds['design']          ='Design';
      $flds['page']            ='Page';
      $flds['modul']           ='Modul';
      $flds['action']          ='Action';
      $flds['edit']            ='Edit';
      $flds['color']           ='Color';
      $flds['language']        ='Lng';
      $flds['request_counter'] ='Request';


      $options_rsort['id']    ='ID';
      $options_rsort['ip']    ='IP';
      $options_rsort['userid']='User';
      $options_rsort['update_date'] ='Letzter Zugriff';

      $data['dbx_rrows']= 10;
      $data['dbx_rsort']='update_date';
      $data['dbx_rdesc']='DESC';

      $oReport->_data   = $data;
      $oReport->_action = '?dbx_modul=dbxAdmin&dbx_action=sessions';
      $oReport->_options_rsort = $options_rsort;
      $oReport->_but_pagination   =11;
      $oReport->_create_row_select=true;
      $oReport->_create_row_edit  =false;
      $oReport->_create_row_delete=true;

      $oReport->_msg_info     = '';
      $oReport->_msg_success  = '';

      $oReport->add_action('rows_select'    ,'action_button_select'    ,'&dbx_work=multi_select&submit=0');
      $oReport->add_action('rows_deselect'  ,'action_button_deselect'  ,'&dbx_work=multi_deselect');
      $oReport->add_action('rows_delete'    ,'action_button_delete'    ,'&dbx_work=multi_delete');


      $work=$oReport->get_post('dbx_work');
      $rid =$oReport->get_post('rid',0,'int');

      if($oReport->submit()) {
        if(!$oReport->errors()) {      // submit && no errors
           if ($work == 'multi_delete') {
             $ok=$oReport->del_selected($tab,'*');
           } // multi_delete
           $oReport->_msg_success   = 'Daten ausgewählt und sortiert';
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
           // Edit Formular als Rückgabewert
           $content='[modul=dbxAdmin]dbx_action=edit_session&rid='.$rid.'[/modul]';
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

      if ($rwhere) $rwhere="modul  LIKE '$rwhere%' or action LIKE '$rwhere%' ";
      if ($select) $rwhere=$oReport->add_rwhere_select($rwhere);
      //dbx_debug("##SQL-RPT##  ($rwhere)   Sort=($rsort) UpDown=($rdesc) Group=($rgroup) Rows=($rrows) R-Pos=($rpos) Sel=($select)");

      $oReport->_rcount=$db->count($tab,$rwhere);
      $oReport->_rdata =$db->select($tab,$rwhere,$flds,$rsort,$rdesc,$rgroup,$rrows,$rpos);
      //dbx_debug("RDATA",$oReport->_rdata);

      if ($work == 'multi_select')   $oReport->set_multi_select('*'); // _rdata must be set to add select all (from _rdata)
      if ($work == 'multi_deselect') $oReport->del_multi_select('*');



      $content=$oReport->run(1,$flds,'table');

      return $content;
   } // report_content_flat()







   // - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

   public function run($action='list') {
      $content='';
      switch ($action) {

        case 'list':
            $content=$this->report_sessions();
            break;

      }
      return $content;
   }

} // class




?>