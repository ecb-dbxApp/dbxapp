<?php
namespace dbx\dbxAdmin;

Class dbxMissing {



   Private function report_missing() {
      $content='';
      $mid   =dbx_get_sysVar('dbx_modul_id');
      $modul =dbx_get_SysVar('dbx_modul');
      $uid  = dbx_get_CurrentUser();
      $tab  ='dbx_missing';

      $options_rsort['id']          ='ID';
      $options_rsort['count']       ='Count';
      $options_rsort['update_date'] ='Update';

      $data['dbx_rrows']= 15;
      $data['dbx_rsort']='update_date';
      $data['dbx_rdesc']='DESC';


      $db     =dbx_get_sys_object('dbxDB');
      $oReport=dbx_get_sys_object('dbxReport');
      $oReport->init('report-missing');



      $oReport->_action='?dbx_modul=dbxAdmin&dbx_action=missing';
      $oReport->_data=$data;
      $oReport->_options_rsort = $options_rsort;
      $oReport->_but_pagination   =5;
      $oReport->_create_row_select=1;
      $oReport->_create_row_edit  =0;
      $oReport->_create_row_delete=1;

      $oReport->add_action('rows_delete','action_button_delete','&dbx_work=multi_delete');

      $work=$oReport->get_post('dbx_work','','parameter');
      $rid =$oReport->get_post('rid'     ,0 ,'int'      );

      if($oReport->submit()) {
        if(!$oReport->errors()) {      // submit && no errors
           if ($work == 'multi_delete') {
              $ok=$oReport->del_selected($tab,'*');
           }
           $oReport->_msg_success   = 'Daten ausgewählt und sortiert';
        } else {
           $oReport->_msg_err = 'Prüfen sie bitte ihre Eingaben';
        }
      } else { // no submit
        if ($work == 'row_delete' && $rid) {
           $ok=$oReport->del_selected($tab,$rid);
           if ( $ok) $oReport->_msg_success  = 'Zeile gelöscht';
           if (!$ok) $oReport->_msg_err = 'Zeile konnte nicht gelöscht werden';
        }
      }

      $flds['id']         ='ID';
      $flds['create_date']='Create';
      $flds['update_date']='Update';
      $flds['count']      ='Count';
      $flds['missing']    ='Missing';

      //
      $rformat['create_date']='php-datetime-usr';
      $rformat['update_date']='php-datetime-usr';
      $oReport->_rpt_format=$rformat;

      // get all selections and order

      $rwhere=$oReport->get_sel('dbx_rwhere','');
      $rrows =$oReport->get_sel('dbx_rrows',15);
      $rpos  =$oReport->get_sel('dbx_rpos',0);
      $rsort =$oReport->get_sel('dbx_rsort','id');
      $rdesc =$oReport->get_sel('dbx_rdesc','ASC');
      $rgroup='';


      if ($rwhere) $rwhere="missing  LIKE '$rwhere%' ";
      $oReport->_rcount=$db->count($tab,$rwhere);
      $oReport->_rdata =$db->select($tab,$rwhere,$flds,$rsort,$rdesc,$rgroup,$rrows,$rpos);

      $count=$oReport->_rcount;
      //dbx_debug("###Missing###  rWhere=($rwhere) count=($count)");


      $content=$oReport->run(1,$flds,'table');

      return $content;
   }



// - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

   public function run() {
      $content=$this->report_missing();
      return $content;
  }

} // class




?>
