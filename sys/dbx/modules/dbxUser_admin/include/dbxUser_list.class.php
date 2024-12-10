<?php
namespace dbx\dbxUser_admin;
dbx_use_sys_class('dbxReport');


class dbxReport_User extends \dbxReport {

  public function run_body($content) {
    $record =$this->_record;
    //if (isset($record['roles'])) $record['roles'] =$this->get_group_name($record['roles']);
    $this->_record=$record;
    return $content;
  }
}




// - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
Class dbxUser_list {

   private function delete_data($id) {
      $oDB  = dbx_get_sys_object('dbxDB');
      $ok=$oDB->delete('dbx_user',$id,1,1);
      return $ok;
   }

   public function edit() {
      $obj=dbx_get_Modul_include_object('dbxUser_view');
      $content=$obj->run();
      return $content;
   }

   public function list() {

      //$oReport = dbx_get_sys_object('dbxReport');
      $oReport = new dbxReport_User;
      $oDB     = dbx_get_sys_object('dbxDB');
      $dd      = 'dbx_user';
      $form_id = 'report-user';
  
      $do      =dbx_get_ModulVar('dbx_do');
      $rid     =dbx_get_ModulVar('rid',0,'int');


      $oReport->_msg_info ='Daten auswählen und Liste anzeigen';
  
      if ($do == 'row_edit') {
        $modal_content=$this->edit();
        return $modal_content;
      }
      if ($do == 'row_delete' && $rid) {
        $ok=$oDB->delete($dd,$rid);
        if ( $ok) $oReport->_msg_info = 'Zeile gelöscht';
        if (!$ok) $oReport->_msg_info = 'Zeile konnte nicht gelöscht werden';
      }
      if ($do == 'multi_delete') {
         $ok=$oReport->del_selected($dd,'*');
         if ( $ok) $oReport->_msg_info = 'Ausgewählte Benutzer gelöscht';
         if (!$ok) $oReport->_msg_info = 'Benutzer konnten nicht gelöscht werden';
      }
     


      $flds['id']         ='ID';
      $flds['userid']     ='User-ID';
      $flds['uname']      ='Benutzer';
      $flds['name']       ='Name';
      $flds['email']      ='eMail';
      $flds['roles']      ='Gruppen';
      $flds['plz']        ='PLZ';
      $flds['ort']        ='Ort';
      $flds['status']     ='Status';
    
  
      $data['dbx_rrows']= 10;
      $data['dbx_rsort']='id';

      $options_rsort['id']     = 'ID';
      $options_rsort['userid'] = 'User-ID';
      $options_rsort['uname']  = 'Benutzer';
      $options_rsort['name']   = 'Name';

  
      $oReport->init($form_id);
      $oReport->_data=$data;
      $oReport->_action='?dbx_modul=dbxUser_admin&dbx_action=user&dbx_work=list_user'; // set_action() cid 'new' or record.id
   
      $oReport->_but_pagination   =9;
      $oReport->_create_row_select=1;
      $oReport->_create_row_edit  =1;
      $oReport->_create_row_delete=1;
      $oReport->_tabel_tpls['tpl_row_edit']   = 'table_row_modal'; 
      $oReport->_tabel_tpls['tpl_row_delete'] = 'modul|confirm_row_delete';

    
      $oReport->_options_rsort = $options_rsort;


      $add['dbx_get']=$oReport->_action.'&dbx_do=add_user';
      $add['label']='Neuer Benutzer';
      $oReport->add_obj('add_user','button-modal1',$add);
     
  
      if($oReport->submit()) {
        if(!$oReport->errors()) {      // submit && no errors
           $oReport->_msg_success   = 'Daten ausgewählt und sortiert';
        } else {
           $oReport->_msg_errr = 'Prüfen sie bitte ihre Eingaben';
        }
      }  
  
      // get all selections and order
      $rgroup=''; $rwhere='id > 0';
      $rwhere=$oReport->get_sel('dbx_rwhere','');
      $rrows =$oReport->get_sel('dbx_rrows' ,10);
      $rpos  =$oReport->get_sel('dbx_rpos'  ,0);
      $rsort =$oReport->get_sel('dbx_rsort' ,'id');
      $rdesc =$oReport->get_sel('dbx_rdesc' ,'ASC');
  
      if ($rwhere) $rwhere="uname  LIKE '$rwhere%' or name  LIKE '$rwhere%' or email LIKE '$rwhere%' ";
      $oReport->_rcount=$oDB->count($dd,$rwhere);
      $oReport->_rdata =$oDB->select($dd,$rwhere,$flds,$rsort,$rdesc,$rgroup,$rrows,$rpos);
  
      $oReport->add_action('rows_delete','dbx|action_button_delete' ,'&dbx_do=multi_delete');

      $modal1['title']     ='Benutzer';     
      $modal1['on_close']  ="dbx_reload('?');"; // JS Event close modal '?' = current self url
      $modal1['class']     ='modal-xxl';
      $modal_content=$oReport->oTPL->get_tpl('dbx','modal1',$modal1);
      $oReport->add_obj('modal1','obj-value',$modal_content);
    
  
      $content=$oReport->run(1,$flds,'table');
    
      return $content;
  
   }
  



   private function report_user() {
      $form_id ='report-user';
      $oReport = new dbxReport_User;
      $oReport->init($form_id);

      $oDB     = dbx_get_sys_object('dbxDB');
      $do      = dbx_get_ModulVar('dbx_do');
      $rid     = dbx_get_ModulVar('rid',0,'int');
      $dd      = 'dbx_user';

      if ($do == 'row_edit') {
         $modal_content=$this->edit($rid);
         return $modal_content;
       }
       if ($do == 'row_delete' && $rid) {
         $ok=$oDB->delete($dd,$rid);
         if ( $ok) $oReport->_msg_info = 'Benutzer gelöscht';
         if (!$ok) $oReport->_msg_info = 'Benutzer konnte nicht gelöscht werden';
       }


      $flds['id']         ='ID';
      $flds['userid']     ='User-ID';
      $flds['uname']      ='Benutzer';
      $flds['name']       ='Name';
      $flds['email']      ='eMail';
      $flds['roles']      ='Gruppen';
      $flds['plz']        ='PLZ';
      $flds['ort']        ='Ort';
      $flds['status']     ='Status';

      $options_rsort['id']     = 'ID';
      $options_rsort['userid'] = 'User-ID';
      $options_rsort['uname']  = 'Benutzer';
      $options_rsort['name']   = 'Name';

      $data['dbx_rrows']= 10;
      $data['dbx_rsort']='id';

      $oReport->_data   =$data;
      $oReport->_action ='?dbx_modul=dbxUser_admin&dbx_action=user&dbx_work=list_user';
      $oReport->_options_rsort = $options_rsort;
      $oReport->_but_pagination   =7;
      $oReport->_create_row_select=1;
      $oReport->_create_row_edit  =1;
      $oReport->_create_row_delete=1;
      
      //$oReport->_msg_info ='';

   //   $oReport->add_action('rows_select'    ,'action_button_select'    ,'&dbx_do=multi_select');
   //   $oReport->add_action('rows_deselect'  ,'action_button_deselect'  ,'&dbx_do=multi_deselect');
   //   $oReport->add_action('rows_delete'    ,'action_button_delete'    ,'&dbx_do=multi_delete');
   //   $oReport->add_action('rows_activate'  ,'action_button_activate'  ,'&dbx_do=multi_activate');
   //   $oReport->add_action('rows_deactivate','action_button_deactivate','&dbx_do=multi_deactivate');

   
      if($oReport->submit()) {
        //dbx_debug("report-user submit");
        if(!$oReport->errors()) {      // submit && no errors
           if ($do == 'multi_delete') {
              $ok=$oReport->del_selected($dd,'*');
           } // multi_delete
           $oReport->_msg_success   = 'Daten ausgewählt und sortiert';
        } else {
           $oReport->_msg_errr = 'Prüfen sie bitte ihre Eingaben';
        }
      }  else { // no submit
  
      }

      // get all selections and order
      $rgroup='';
      $rwhere=$oReport->get_sel('dbx_rwhere' ,'');
      $rrows =$oReport->get_sel('dbx_rrows'  ,10);
      $rpos  =$oReport->get_sel('dbx_rpos'   ,0);
      $rsort =$oReport->get_sel('dbx_rsort'  ,'id');
      $rdesc =$oReport->get_sel('dbx_rdesc'  ,'ASC');
      $select=$oReport->get_sel('dbx_rselect',0);

      if ($rwhere) $rwhere="uname  LIKE '$rwhere%' or name  LIKE '$rwhere%' or email LIKE '$rwhere%' ";
      if ($select) $rwhere=$oReport->add_rwhere_select($rwhere);
      //dbx_debug("##Rwhere##  ($rwhere)");

      //$sel=8; $all=99;

      
      $all=$oDB->count($dd);
      $oReport->_rcount=$oDB->count($dd,$rwhere);
      $oReport->_rdata =$oDB->select($dd,$rwhere,$flds,$rsort,$rdesc,$rgroup,$rrows,$rpos);
      
      $oReport->_data_table=1;
      $oReport->_create_sel_flds=1;
      $oReport->_msg_success="($oReport->_rcount) Benutzer von ($all) ";
      
      $modal1['title']     ='Benutzer';     
      $modal1['on_close']  ="dbx_reload('?');"; // JS Event close modal '?' = current self url
      $modal1['class']     ='modal-xxl';
      $modal_content=$oReport->oTPL->get_tpl('dbx','modal1',$modal1);
      $oReport->add_obj('modal1','obj-value',$modal_content);


      $content=$oReport->run(1,$flds,'table');
  
        

      return $content;
   }






   // ----------------------------------------------------

   public function run() {
      $content=$this->list();
      return $content;
   } // run

} // class



?>