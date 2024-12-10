<?php
namespace dbx\dbxApi_admin;

class dbxApi_edit {

  private function edit_api() {

    $content=''; $ok=false;
    $rid=dbx_get_ModulVar('rid',0,'int');
    $dd='dbx_api';

    $db=dbx_get_sys_object('dbxDB');
    $data=$db->select1($dd,$rid);


    //return $content;
    $oForm=dbx_get_sys_object('dbxForm');
    $oForm->init('form-api');
    $oForm->_dd      = $dd;
    $oForm->_data    = $data;
    $oForm->_msg_info= 'Sie können die Api Aufrufe bearbeiten';
    $oForm->_action  = '?dbx_modul=dbxApi_admin&dbx_action=edit&rid='.$rid;

    $oForm->add_fld('api'       ,'text-label');  // #+
    $oForm->add_fld('apikey'    ,'text-label');  // #+
    $oForm->add_fld('modul'     ,'text-label');  // #+
    $oForm->add_fld('action'    ,'text-label');  // #+
    $oForm->add_fld('work'      ,'text-label');  // #+
    $oForm->add_fld('count'     ,'text-label');  // #+
    $oForm->add_fld('max'       ,'text-label');  // #+

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

    return $content;

  }


  public function run() {
     $uid   =dbx_get_CurrentUser();
     $mid   =dbx_get_sysVar('dbx_modul_id');
     $modul =dbx_get_SysVar('dbx_modul');
     $work  =dbx_get_ModulVar('dbx_work','','parameter');

     $content=$this->edit_api();

     return $content;
  } // run()

} // class

?>
