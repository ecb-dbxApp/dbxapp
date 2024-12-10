<?php
namespace dbx\lda_methoden;

class ldaMethoden {


  public function list_methoden() {

    $rid = dbx_get_ModulVar('rid',0,'int');
    $do  = dbx_get_ModulVar('dbx_do','','parameter');
    $dd      = 'lda_methoden';
    $form_id = 'report-methode';

    $oReport = dbx_get_sys_object('dbxReport');
    $db      = dbx_get_sys_object('dbxDB');
 
    if ($do == 'row_edit')  return $this->edit_methode();
    if ($do == 'row_delete' && $rid)  {
        $ok=$db->delete($dd,$rid);
        if ( $ok) $oReport->_msg_info = 'Zeile gelöscht';
        if (!$ok) $oReport->_msg_info = 'Zeile konnte nicht gelöscht werden';
    }

  
  
    $db      = dbx_get_sys_object('dbxDB');
    $form_id = 'report-methode';
  

    $flds['id']         ='ID';
    $flds['abk']        ='ABK';  
    $flds['name']       ='Analyse';  
    $flds['material']   ='Material'; 
    $flds['pos10a']     ='Pos10a';
    $flds['posigel']    ='PosIgel';
    $flds['poskarte']   ='PosKarte';
    $flds['poskarte']   ='PosKarte';

    $data['dbx_rrows']= 10;
    $data['dbx_rsort']='id';

    $oReport->init($form_id);
    $oReport->_data=$data;
    $oReport->_action='?dbx_modul=lda_methoden&dbx_action=methoden&dbx_work=list'; // set_action() cid 'new' or record.id
    //$oReport->_options_rsort = $options_rsort;
    $oReport->_but_pagination   =9;
    $oReport->_create_row_select=0;
    $oReport->_create_row_edit  =1;
    $oReport->_create_row_delete=1;
    $oReport->_tabel_tpls['tpl_row_edit']   = 'table_row_modal-edit';
    $oReport->_tabel_tpls['tpl_row_delete'] = 'modul|confirm_row_delete';
    $oReport->_confirm_delete='modul|confirm-delete-methode';
 
    //$oReport->_create_row_show  =1;

    $oReport->_msg_info     = '';
    $oReport->_msg_success  = '';

    $add_methode['dbx_get']=dbx_get_base_url().'dbx_modul=lda_methoden&dbx_action=methoden&dbx_work=add&rid='.$rid;
    $add_methode['label']='Neue Methode';
    $oReport->add_obj('add_methode','button-modal1',$add_methode);
   
    $modal1['title']     ='LDA Methoden';     
    $modal1['on_close']  ="dbx_reload('?');"; // JS Event close modal 
    $modal1['class']     ='modal-xxl';
    $modal_content=$oReport->oTPL->get_tpl('dbx','modal1',$modal1);
    
 

    if($oReport->submit()) {
      if(!$oReport->errors()) {      // submit && no errors
         $work=$oReport->get_post('dbx_work');
         $oReport->_msg_success   = 'Daten ausgewählt und sortiert';
      } else {
         $oReport->_msg_errr = 'Prüfen sie bitte ihre Eingaben';
      }
    }  else { // no submit
      $rid=dbx_get_PostGetVar('rid',0,'int');

   
    }

    // get all selections and order
    $rgroup=''; $rwhere='id > 0';
    $rwhere=$oReport->get_sel('dbx_rwhere','');
    $rrows =$oReport->get_sel('dbx_rrows' ,10);
    $rpos  =$oReport->get_sel('dbx_rpos'  ,0);
    $rsort =$oReport->get_sel('dbx_rsort' ,'id');
    $rdesc =$oReport->get_sel('dbx_rdesc' ,'ASC');

    if ($rwhere) $rwhere="name  LIKE '%$rwhere%' or abk LIKE '%$rwhere%' or pos10a = '$rwhere' or posigel  = '$rwhere' or poskarte  = '$rwhere' ";
    $oReport->_rcount=$db->count($dd,$rwhere);
    $oReport->_rdata =$db->select($dd,$rwhere,$flds,$rsort,$rdesc,$rgroup,$rrows,$rpos);

    $oReport->add_obj('modal1','obj-value',$modal_content);

    $content=$oReport->run(1,$flds,'table');
  
    return $content;

 }

 public function row_edit() {
    return $this->edit_methode();
 }

 public function delete_methode() {
   // delete methode id=?
   return $this->list_methoden(); 
 }

 public function edit_methode() {
  

    $add_data=array();
    $content=''; $options_groups=array();
    $oForm=dbx_get_sys_object('dbxForm');
    $db   =dbx_get_sys_object('dbxDB');
    $work =dbx_get_ModulVar('dbx_work',0,'parameter');
    $rid  =dbx_get_ModulVar('rid',0,'int');
    $dd   ='lda_methoden';
 

    //return "ID=($rid)";

    $oForm->init('form-methode');

    //dbx_debug("### USER EDIT ID=($rid)  Action=($action) ####");
    $data=$db->select1($dd,$rid);

      
    $oForm->_data      = $data;
    $oForm->_msg_info  = 'Sie können ein Daten bearbeiten';
    $oForm->_dd        = $dd; // Main db-Table
    $oForm->_action    = '?dbx_modul=lda_methoden&dbx_action=methoden&dbx_work=edit&rid='.$rid;


    //$oForm->add_fld('id','text-label' );
    $oForm->add_fld('abk'       ,'text-label' );
    $oForm->add_fld('name'      ,'text-label' );
    $oForm->add_fld('material'  ,'text-label' );        

    $oForm->add_fld('pos10a'    ,'text-label' );        
    $oForm->add_fld('posigel'   ,'text-label' );        
    $oForm->add_fld('poskarte'  ,'text-label' );        


    //public function add_fld($name,$tpl,$data='',$rules='dd:',$label='dd:',$tooltip='dd:',$msg='dd:',$placeholder='dd:',$class='') { //#



    if($oForm->submit()) {
        if(!$oForm->errors()) {      // submit && no errors && no warnings
         $change=$oForm->changed();
         if ($change) {
    
          
           $ok=$oForm->save_post($dd,$rid);
 
           if ( $ok) $oForm->_msg_success   = 'Daten gespeichert';
           if (!$ok) $oForm->_msg_success   = 'Daten konnten nicht gespeichert werden';

           if ($ok) {  // reload list background
               if (!$rid) $rid=$oForm->_rid;
               dbx_set_ModulVar('rid',$rid);
               $oForm->_action  = '?dbx_modul=lda_methoden&dbx_action=methoden&dbx_work=edit&rid='.$rid;
               //return "Methode id=($id)";
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
    //$rid=$oForm->_data['id']; // nach dem speichern
 

    $content=$oForm->run();

    return $content;
  }





 








  public function run($work='') {
     $modul =dbx_get_SysVar('dbx_modul');
     $work  =dbx_get_ModulVar('dbx_work','list','parameter');

     $content="?";

     switch ($work) {
       case 'list':
           $content=$this->list_methoden(); 
        break;

        case 'add':
        case 'edit':        
            $content=$this->edit_methode();
        break;

        case 'del':
          $content=$this->delete_methode();
        break;



       default:
         $content="<div class='alert alert-warning' role='alert'>Modul=($modul) Inc=(ldaMethoden)  Work=($work)is undef.</div>";
     } // switch
     return $content;
  } // run()

} // class
