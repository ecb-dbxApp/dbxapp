<?php
namespace dbx\myArzt;

class Arzt {





  public function list() {

    $oReport = dbx_get_sys_object('dbxReport');
  
    $oDB     = dbx_get_sys_object('dbxDB');
    $dd_arzt = 'my_arzt';
    $form_id = 'report-arzt';
    $do      = dbx_get_ModulVar('dbx_do','','parameter');
    $rid     = dbx_get_ModulVar('rid',0,'int');

    if ($do == 'row_delete' && $rid) {
      $oDB->delete($dd_arzt,$rid);
    } 
    
    if ($do == 'row_edit' ) {
       
       return $this->edit($rid);
    }

    if ($do == 'multi_delete') {
        $ids=$oReport->get_post('Report-content_select','','array|int');
        if (is_array($ids)) {
          foreach ($ids as $no => $rid) {
              $ok=$oDB->delete($dd_arzt,$rid);
          }
        }
    }





    $flds['id']     ='ID';
    $flds['name']   ='Name';  
    $flds['bsnr']   ='BSNR'; 
    $flds['lanr']   ='LANR';
  

    $data['dbx_rrows']= 10;
    $data['dbx_rsort']='id';

    $oReport->init($form_id);
    $oReport->_data=$data;
    $oReport->_action='?dbx_modul=myArzt&dbx_action=arzt&dbx_work=list'; // set_action() cid 'new' or record.id
    //$oReport->_options_rsort = $options_rsort;
    $oReport->_but_pagination   =9;
    $oReport->_create_row_select=1;
    $oReport->_create_row_edit  =1;
    $oReport->_create_row_delete=1;
    $oReport->_tabel_tpls['tpl_row_edit']   = 'table_row_modal-edit';
    $oReport->_tabel_tpls['tpl_row_delete'] = 'modul|confirm_row_delete';
    //$oReport->_confirm_delete='modul|confirm-delete-arzt';
 
    //$oReport->_create_row_show  =1;

    $oReport->_msg_info ='Daten auswählen und Liste anzeigen';

    //$add['dbx_get']=dbx_get_base_url().'dbx_modul=myArzt&dbx_action=arzt&dbx_work=add';
    $add['dbx_get']='?dbx_modul=myArzt&dbx_action=arzt&dbx_work=add';
    $add['label']='Neuer Arzt';
    $oReport->add_obj('add_arzt','button-modal1',$add);
   

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

    if ($rwhere) $rwhere="name LIKE '%$rwhere%' ";
    $oReport->_rcount=$oDB->count($dd_arzt,$rwhere);
    $oReport->_rdata =$oDB->select($dd_arzt,$rwhere,$flds,$rsort,$rdesc,$rgroup,$rrows,$rpos);

    $modal1['title']     ='Ärzte';     
    $modal1['on_close']  ="dbxReSendForm('#dbx_form_{i}')"; //"dbx_reload('?');"; // JS Event close modal '?' = current self url
    $modal1['class']     ='modal-xxl';
    $modal_content=$oReport->oTPL->get_tpl('dbx','modal1',$modal1);
    $oReport->add_obj('modal1','obj-value',$modal_content);
  

    $content=$oReport->run(1,$flds,'table');
  
    return $content;

 }

  public function add() {
    return $this->edit();
  }


  public function edit($rid=0) {
    if (!$rid) $rid= dbx_get_ModulVar('rid',0,'int');

    $content=''; 
    
    $oForm  = dbx_get_sys_object('dbxForm');
    $db     = dbx_get_sys_object('dbxDB');

    $rid    = dbx_get_ModulVar('rid',0,'int');
    $dd_arzt= 'my_arzt';
 

    $oForm->init('form-arzt');

    //dbx_debug("### USER EDIT ID=($rid)  Action=($action) ####");
    $data=$db->select1($dd_arzt,$rid);

      
    $oForm->_data      = $data;
    $oForm->_msg_info  = 'Sie können die Daten bearbeiten';
    $oForm->_dd        = $dd_arzt;  // Main db-Table
    $oForm->_action    = "?dbx_modul=myArzt&dbx_action=arzt&dbx_work=edit&rid=$rid";


    $oForm->add_fld('id'             ,'text-label' );
    $oForm->add_fld('name'            ,'text-label' );
    $oForm->add_fld('lanr'            ,'text-label' );
    $oForm->add_fld('bsnr'            ,'text-label' );
 
    
    if ($data['id']) {
      $oForm->add_action('edit_anf',  'modul|button_edit_anf', '&dbx_work=edit_anf');  
    } else {
      $oForm->add_obj('edit_anf','modul|button_edit_anf_wait'); 
    }
    


    //public function add_fld($name,$tpl,$data='',$rules='dd:',$label='dd:',$tooltip='dd:',$msg='dd:',$placeholder='dd:',$class='') { //#



    if($oForm->submit()) {
        if (!$oForm->errors()) {      // submit && no errors && no warnings
          $change=$oForm->changed();
          if ($change) {
          
             $ok=$oForm->save_post($dd_arzt,$rid);
             if ( $ok) $oForm->_msg_success   = 'Daten gespeichert';
             if (!$ok) $oForm->_msg_success   = 'Daten konnten nicht gespeichert werden';

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
        // $oForm->add_obj('form_msg','obj-value','Daten bearbeiten');
    }
    dbx_debug ("###ARZT DATA",$oForm->_data);
    $rid=$oForm->_data['id']; // nach dem speichern
 

    $content=$oForm->run();

    return $content;
  }





 








  public function run() {
     $modul =dbx_get_SysVar('dbx_modul');
     $action=dbx_get_ModulVar('dbx_action'); 
     $work  =dbx_get_ModulVar('dbx_work','list','parameter');

     switch ($work) {
        case 'list':
           $content=$this->list();
        break;
        
        case 'add':
           $content=$this->add();
        break;

        case 'edit':
          $content=$this->edit();
       break;

      
        default:      
           $content="<div class='alert alert-warning' role='alert'>Modul=($modul) Inc=(myArzt) Action=($action) Work=($work) is undef.</div>";
     } // switch
     return $content;
  } // run()

} // class

?>