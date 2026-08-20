<?php
namespace dbx\dbxPage_admin;

class dbxPage_edit {

  private function edit_api() {

    $content=''; $ok=false;
    $rid=dbx()->get_modul_var('rid',0,'int');
    $dd='dbx_api';

    $db=dbx()->get_system_obj('dbxDB');
    $data=$db->select1($dd,$rid);


    //return $content;
    $o_form=dbx()->get_system_obj('dbxForm');
    $o_form->init('form-api', 'form-api');
    $o_form->set_data_definition($dd);
    $o_form->set_data($data);
    $o_form->_msg_info= 'Sie können die Api Aufrufe bearbeiten';
    $o_form->set_action('?dbx_modul=dbxPage_admin&dbx_run1=edit&rid='.$rid);

    $o_form->add_fld('api'       ,'text-label');  // #+
    $o_form->add_fld('apikey'    ,'text-label');  // #+
    $o_form->add_fld('modul'     ,'text-label');  // #+
    $o_form->add_fld('action'    ,'text-label');  // #+
    $o_form->add_fld('work'      ,'text-label');  // #+
    $o_form->add_fld('count'     ,'text-label');  // #+
    $o_form->add_fld('max'       ,'text-label');  // #+

    if($o_form->submit()) {
      if(!$o_form->errors() && !$o_form->warnings()) {      // submit && no errors && no warnings
         $change=$o_form->changed();
         if ($change) {
           $ok=$o_form->save_post($dd,$rid);
           if ( $ok) $o_form->_msg_success   = 'Daten gespeichert';
           if (!$ok) $o_form->_msg_success   = 'Daten konnten nicht gespeichert werden';
         } else {
           $o_form->_msg_success   = 'Keine Änderung';
         }
         $o_form->_msg_success .=" Change=($change)";
      } else {
         $o_form->_msg_errr = 'Prüfen sie bitte ihre Eingaben';
      }
    }
    $content=$o_form->run();

    return $content;

  }


  public function run() {
     $uid   =dbx()->user();
     $mid   =dbx()->get_system_var('dbx_modul_id');
     $modul =dbx()->get_system_var('dbx_modul');
     $work  =dbx()->get_modul_var('dbx_run2','','parameter');

     $content=$this->edit_api();

     return $content;
  } // run()

} // class

?>
