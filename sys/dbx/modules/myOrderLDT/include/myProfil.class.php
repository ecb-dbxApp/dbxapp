<?php
namespace dbx\myOrderLDT;




Class myProfil {

  private function get_count_anf($anf) {
    $count=0;
    if ($anf) {
      $anfx = explode(",", $anf);
      $count= count($anfx);
    } 
    if ($count) $count--;
    return $count;
  }


  private function get_check_anf($anf,$abk) {
    $chk=0;  $abk.=',';
    $pos=(strpos($anf,$abk));
    if ($pos != '') $chk=1; 
    //dbx_debug("chek-anf ($abk) Anf=($anf) Pos=($pos) Check=$chk");
    return $chk;
  }


  

   function add_profil() {
      dbx_set_ModulVar('rid',0);
      return $this->edit_profil();
   }

  public function edit_profil() {
    $add_data=array();
    $content=''; $options_groups=array(); $add_data=array();
    $oForm =dbx_get_sys_object('dbxForm');
    $oDB   =dbx_get_sys_object('dbxDB');
    $do    =dbx_get_ModulVar('dbx_do',0,'parameter');
    $rid   =dbx_get_ModulVar('rid',0,'parameter');
    $dd    ='my_profile';
    $praxis=dbx_get_cfg('myOrderLDT','praxis');
                         //'lda_methoden'
   // $methoden=$oDB->select('lda_methoden','pos10a > 0 or posigel > 0','abk,name','name');
    $methoden=$oDB->select('lda_methoden',"poskarte > 0 ",'abk,name,poskarte','name');
    foreach ($methoden as $no => $record) {
      $id   = $record['abk'];
      $pos  = $record['poskarte'];
      $name = $record['name'];
      
      if ($pos==null) $pos=0;
      $bez  = "($id) ".$name;

      if ($name[0] != '-' && $pos > 0) {
        if (strpos($bez, 'Profil') === false)  $options_methoden[$id]=$bez;
      }
    }



    $oForm->init('form-profil');

    //dbx_debug("### USER EDIT ID=($rid)  Action=($action) ####");
    $data=$oDB->select1($dd,$rid);

    //dbx_debug("Profile Data=",$data);

  
    $oForm->_data      = $data;
    $oForm->_msg_info  = 'Sie können ein Profildaten bearbeiten';
    $oForm->_dd        = $dd; // Main db-Table
    $oForm->_action    = '?dbx_modul=myOrderLDT&dbx_action=profil&dbx_work=edit_profil&rid='.$rid;
    
    $oForm->add_fld('profil'     ,'text-label' );
    $oForm->add_fld('bezeichnung' ,'text-label' );
    //$oForm->add_fld('parameter'   ,'text-label' );
    $oForm->add_fld('parameter'    ,'multi-select-label' ,$options_methoden,'*',class: 'dbxMultiSelect2 searchable changeSubmit');
    //dbx_debug("#PROFIEL# Option Methoden=",$options_methoden); 
    

    $count_anf=$this->get_count_anf($data['parameter']);
      

    $oForm->add_obj('count_anf'    ,'obj-value',"<b>Anzahl:</b> ($count_anf)");

    if($oForm->submit()) {
        if(!$oForm->errors()) {      // submit && no errors && no warnings
         $change=$oForm->changed();
         if ($change) {
 
           $oForm->set_post('praxis',$praxis);
           $ok=$oForm->save_post($dd,$rid);
 
           if ( $ok) $oForm->_msg_success   = 'Daten gespeichert';
           if (!$ok) $oForm->_msg_success   = 'Daten konnten nicht gespeichert werden';

           if ($ok) {  // reload list background
               $rid=$oForm->_data['id'];
               if (!$rid) $rid=1;
           }
         } else {
           $oForm->_msg_success   = 'Keine Änderung';
         }
        } else {
         $err_flds='';
         $errors=$oForm->_errors;
         foreach ($errors as $key => $value) {
           $err_flds.=$key.' ';
         }
         $oForm->_msg_error = 'Prüfen sie bitte ihre Eingaben ('.$err_flds.')';
      }
    } else {
        $oForm->add_obj('form_msg','obj-value','');
    }
    //dbx_debug ("###PATIENT DATA",$oForm->_data);
    if (!$rid) $rid=$oForm->_data['id']; // nach dem speichern
    
    if ($rid) { //$data['id']) {
      $oForm->add_action('edit_anf','modul|button_edit_anf', '&dbx_work=edit_anf&rid='.$rid);  
    } else {
      $oForm->add_obj('edit_anf','modul|button_edit_anf_wait'); 
    }
    
    $oForm->add_js_call('parameter','multiselect2');
    $oForm->add_js("autosave_multiselect('patameter_{i}','dbx_form_{i}');\n");
    $content=$oForm->run();

    return $content;
  }




  public function list_profil() {

     $oReport = dbx_get_sys_object('dbxReport');
     $oDB     = dbx_get_sys_object('dbxDB');
     $dd      = 'my_profile';
     $form_id = 'report-profile';

     $work    =dbx_get_ModulVar('dbx_work','','parameter');
     $do      =dbx_get_ModulVar('dbx_do'  ,'','parameter'); 
     $rid     =dbx_get_ModulVar('rid'     ,'','parameter');
     $praxis  =dbx_get_cfg('myOrderLDT','praxis'); 

     $flds['id']              ='';
     $flds['profil']         ='Profil';
     $flds['bezeichnung']     ='Bezeichnung';  
     $flds['parameter']       ='Anforderungen'; 
   
     
     $data['dbx_rrows']= 1000;
     $data['dbx_rsort']='profil';
    

     $oReport->init($form_id);
     $oReport->_data=$data;
     $oReport->_action='?dbx_modul=myOrderLDT&dbx_action=profil&dbx_work=list_profil'; // set_action() cid 'new' or record.id

     $oReport->_but_pagination   =1;
     $oReport->_create_row_select=0;
     $oReport->_create_row_edit  =1;
     $oReport->_create_row_delete=1;
     $oReport->_create_sel_flds  =0;    


     $oReport->_tabel_tpls['tpl_row_edit']   = 'table_row_modal-edit';
     $oReport->_tabel_tpls['tpl_row_delete'] = 'confirm_row_delete';

     $oReport->_msg_info   ='Liste der Profile für Labor-Anforderungen.';
     $oReport->_msg_success='';
  
     $oReport->_msg_confirm_delete='Wollen Sie das Profil löschen?';  


     $add_profil['dbx_get']='?dbx_modul=myOrderLDT&dbx_action=profil&dbx_work=add_profil';
     $add_profil['label']='Neues Profil';
     $oReport->add_obj('add_profil','button-modal1',$add_profil);
    
     $modal1['title']     ='Profile';     
     $modal1['on_close']  ="dbxReSendForm('#dbx_form_{i}')"; // JS Event close modal
     $modal1['class']     ='modal-xxl';
     
     $modal_content=$oReport->oTPL->get_tpl('dbx','modal1',$modal1);
     if ($do == 'row_edit') {
       $modal_content=$this->edit_profil();
       return $modal_content;
     }

     //$oReport->add_action('rows_delete'    ,'action_button_delete'    ,'&dbx_do=multi_delete');
 
  

     if($oReport->submit()) {
       if(!$oReport->errors()) {      // submit && no errors
          $work=$oReport->get_post('dbx_work');
          if ($work == 'multi_delete') {
             $ids=$oReport->get_post('Report-Order_select','','array|int');
             if (is_array($ids)) {
                foreach ($ids as $no => $id) {
                   $ok=$oDB->delete($dd,$id);
                }
             }
          }
          //$oReport->_msg_success   = 'Daten ausgewählt und sortiert';
       } else {
          $oReport->_msg_error = 'Prüfen sie bitte ihre Eingaben';
       }
     }  else { // no submit
       $rid=dbx_get_PostGetVar('rid','','parameter');
       if ($do == 'row_edit' && $rid) {
        $modal_content=$this->edit_profil();
       }
       if ($do == 'row_delete' && $rid) {
          $ok=$oDB->delete($dd,$rid);
          //if ( $ok) $oReport->_msg_info = 'Zeile gelöscht';
          if (!$ok) $oReport->_msg_info = 'Zeile konnte nicht gelöscht werden';
       }
     }

     // get all selections and order
     $rgroup=''; 
     $rwhere="";

     
     $oReport->_rcount=$oDB->count($dd,$rwhere);
     $oReport->_rdata =$oDB->select($dd,$rwhere,$flds,'profil','DESC',$rgroup,1000,0);

     $oReport->add_js_call('dbx_table','datatable1');
     //$oReport->add_js("datatable_fix('#dbx_table_{i}')",100); // work arround hack
     $oReport->add_obj('modal1','obj-value',$modal_content);

     $content=$oReport->run(1,$flds,'table');   
     
     return $content;


  }

   
  function select_profil() {
     return "select Profil";
  }
// ----------------------------------




   public function run() {
    $modul=dbx_get_SysVar('dbx_activ_modul');
    $work =dbx_get_ModulVar('dbx_work');

    switch ($work) {
       
  
 
        case 'add_profil':
          $content=$this->add_profil();     
        break; 

        case 'edit_profil':
          $content=$this->edit_profil();     
        break; 

        case 'list_profil':
          $content=$this->list_profil();     
        break; 

        case 'select_profil':
          $content=$this->select_profil();     
        break; 


       default:
        $oTPL=dbx_get_sys_object('dbxTPL');
        $msg['msg']="Modul=($modul) Work=($work) is undef.";
        $content=$oTPL->get_tpl('dbx','alert-warning',$msg);

     } // switch()


      return $content;
   } // run

} // class

