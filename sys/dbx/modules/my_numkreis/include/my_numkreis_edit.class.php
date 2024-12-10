<?php
namespace dbx\my_numkreis;

class my_numkreis_edit {

 

  public function edit() {
  
    $add_data=array();
    $content=''; 
    
    $oForm =dbx_get_sys_object('dbxForm');
    $db    =dbx_get_sys_object('dbxDB');
    $rid   = 1;
    $dd    ='my_numkreis';
 

    //return "ID=($rid)";

    $oForm->init('form-numkreis');

    //dbx_debug("### USER EDIT ID=($rid)  Action=($action) ####");
    $data=$db->select1($dd,$rid);

      
    $oForm->_data      = $data;
    $oForm->_msg_info  = 'Sie können den Nummernkreis für die Proben-IDs bearbeiten';
    $oForm->_dd        = $dd;  // Main db-Table
    $oForm->_action    = "?dbx_modul=my_numkreis&dbx_action=edit";


    $oForm->add_fld('id_von'    ,'text-label' );
    $oForm->add_fld('id_bis'    ,'text-label' );
    $oForm->add_fld('next_probe','text-label' );
    $oForm->add_fld('id_lang'   ,'text-label' );
 
    



    if($oForm->submit()) {
        if (!$oForm->errors()) {      // submit && no errors && no warnings
          $change=$oForm->changed();
          if ($change) {
          
             $ok=$oForm->save_post($dd,$rid);
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
 

    $content=$oForm->run();

    return $content;
  }









  public function run($action='') {
     $uid   =dbx_get_CurrentUser();
     $mid   =dbx_get_sysVar('dbx_modul_id');
     $modul =dbx_get_SysVar('dbx_modul');

     $content=$this->edit();

     return $content;
  } // run()

} // class

?>