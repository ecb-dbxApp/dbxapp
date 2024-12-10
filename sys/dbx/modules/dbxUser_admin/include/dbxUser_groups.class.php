<?php
namespace dbx\dbxUser_admin;


// - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
Class dbxUser_groups extends \dbxObj {


   private function report_user_groups() {
      //return 'content-flat';
      $oReport = dbx_get_sys_object('dbxReport');
      $db      = dbx_get_sys_object('dbxDB');
      $lng     = dbx_get_ModulVar('lng','de');

      $tab_groups  = 'dbx_user_groups';


      $flds['id']              ='ID';
      $flds['name']            ='Gruppe';
      $flds['description']     ='Beschreibung';
      $flds['active']          ='Activ';


      $options_rsort['id']     = 'ID';
      $options_rsort['name']   = 'Gruppe';

      $data['dbx_rrows']= 10;
      $data['dbx_rsort']='id';

      $oReport->init('report-groups');
      $oReport->_data=$data;
      $oReport->_action='?dbx_modul=dbxUser_admin&dbx_action=list_groups'; // set_action() cid 'new' or record.id
      $oReport->_options_rsort = $options_rsort;
      $oReport->_but_pagination   =9;
      $oReport->_create_row_select=1;
      $oReport->_create_row_edit  =1;
      $oReport->_create_row_delete=1;

      $oReport->_msg_info ='';

      $oReport->add_action('rows_delete'    ,'action_button_delete'    ,'&dbx_work=multi_delete');
      $oReport->add_action('rows_activate'  ,'action_button_activate'  ,'&dbx_work=multi_activate');
      $oReport->add_action('rows_deactivate','action_button_deactivate','&dbx_work=multi_deactivate');

      $work=$oReport->get_post('dbx_work');
      $sid =$oReport->get_post('id',0,'int');


      if($oReport->submit()) {
        if(!$oReport->errors()) {      // submit && no errors
           $work=$oReport->get_post('dbx_work');
           if ($work == 'multi_delete') {
              $ids=$oReport->get_post('Report-Groups_select','','array|int');
              if (is_array($ids)) {
                 foreach ($ids as $no => $id) {
                    $ok=$db->delete($tab_groups,$id);
                 }
              }
           }
           $oReport->_msg_success   = 'Daten ausgewählt und sortiert';
        } else {
           $oReport->_msg_errr = 'Prüfen sie bitte ihre Eingaben';
        }
      }  else { // no submit
        $rid=dbx_get_PostGetVar('rid',0,'int');
        if ($work == 'row_edit' && $rid) {
          return '[-modul=dbxUser_admin]dbx_action=group&id='.$rid.'[/modul]';
        }
        if ($work == 'row_delete' && $rid) {
           $ok=$db->delete($tab_groups,$rid);
           if ( $ok) $oReport->_msg_info = 'Zeile gelöscht';
           if (!$ok) $oReport->_msg_info = 'Zeile konnte nicht gelöscht werden';
        }
      }

      // get all selections and order
      $rgroup='';
      $rwhere=$oReport->get_sel('dbx_rwhere','');
      $rrows =$oReport->get_sel('dbx_rrows' ,10);
      $rpos  =$oReport->get_sel('dbx_rpos'  ,0);
      $rsort =$oReport->get_sel('dbx_rsort' ,'id');
      $rdesc =$oReport->get_sel('dbx_rdesc' ,'ASC');

      if ($rwhere) $rwhere="description  LIKE '%$rwhere%' ";
      $oReport->_rcount=$db->count($tab_groups,$rwhere);
      $oReport->_rdata =$db->select($tab_groups,$rwhere,$flds,$rsort,$rdesc,$rgroup,$rrows,$rpos);

      $content=$oReport->run(1,$flds,'table');

      return $content;
   } // report_content_flat()

  // ----------------------------------------------------

  private function edit_user_group($rid=0) {

     $content=''; $ok=false;
     $uid = dbx_get_CurrentUser();
     $dd  = 'dbx_user_groups';
     if (!$rid) $rid = dbx_get_ModulVar('rid',0);
     //if (!$rid) $rid = dbx_get_PostGetVar('rid',0,'int');

     if ($uid) {

       $db=dbx_get_sys_object('dbxDB');
       $data=$db->select1($dd,$rid);


       //return $content;
       $oForm=dbx_get_sys_object('dbxForm');
       $oForm->init('form-group');
       $oForm->_dd      = $dd;
       $oForm->_data    = $data;
       $oForm->_msg_info= 'Sie können die Benutzergruppen bearbeiten';
       $oForm->_action  = '?dbx_modul=dbxUser_admin&dbx_action=edit_group&rid='.$rid;

       $oForm->add_fld('name'       ,'text-label');      // #+
       $oForm->add_fld('description','text-label');      // #+
       $oForm->add_fld('active'     ,'checkbox-label');  // #+


       if($oForm->submit()) {
         if(!$oForm->errors() && !$oForm->warnings()) {      // submit && no errors && no warnings
            $change=$oForm->changed();
            if ($change) {
              $ok=$oForm->save_post($dd,$rid);
              if ( $ok) $oForm->_msg_success   = 'Daten gespeichert';
              if (!$ok) $oForm->_msg_success   = 'Daten konnten nicht gespeichert werden';
            } else {
              $oForm->_msg_success   = 'Keine Änderung';
            }
            $oForm->_msg_success .=" Change=($change)";
         } else {
            $oForm->_msg_errr = 'Prüfen sie bitte ihre Eingaben';
         }
       }
       $content=$oForm->run();
     }


     return $content;




  }

   // ----------------------------------------------------

   public function run($action='list_groups') {
      $content='Modul dbxUser_admin action('.dbx_html($action).') not defined';
      $work= dbx_get_PostGetVar('dbx_work');
      $gid = dbx_get_PostGetVar('id',0,'int');
      if ($work=='row_edit') $action='edit_group';



      switch ($action) {



        case 'list_groups':
            $content=$this->report_user_groups();
            break;

        case 'new_group':
            $content=$this->edit_user_group(0);
            break;

        case 'edit_group':
            //return "edit=($gid)";
            $content=$this->edit_user_group($gid);
            break;
      }
      return $content;
   } // run

} // class



?>
