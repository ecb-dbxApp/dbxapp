<?php
namespace dbx\dbxUser;

Class dbxUser_profil {


   public function run() {
      $content=''; $ok=false;
      $uid = dbx_get_CurrentUser();
      $dd  = 'dbx_user'; 

      if ($uid) {
        $db=dbx_get_sys_object('dbxDB');
        $where ="userid = $uid";
        $data=$db->select1('dbx_user',$where);
        $data['test']=''; // add the outside fld for change detection

        //return $content;
        $oForm=dbx_get_sys_object('dbxForm');
        $oForm->init('dbxUser_profil','form-profil');
        $oForm->_msg_info= 'Sie können ein Profildaten bearbeiten';
        $oForm->_dd      = $dd; // Main db-Table for dd
        $oForm->_action  = '?dbx_modul=dbxUser&dbx_action=profil';
        $oForm->_data    = $data;

        $options_land['']  ='Auswahl...';
        $options_land['de']='Deutschland';
        $options_land['us']="Unites States";


        //      add_flds($tpl,$name,$label,$rules,$msg,$tooltip,$classes)
        $oForm->add_obj('avatar','[modul=dbxUser]dbx_action=avatar_upload[/modul]');

        $oForm->add_fld('name'    ,'text-label'); // #+
        $oForm->add_fld('name2'   ,'text-label');
        $oForm->add_fld('telefon' ,'text-label');
        $oForm->add_fld('handy'   ,'text-label');
        $oForm->add_fld('email'   ,'text-label');
        $oForm->add_fld('strasse' ,'text-label');       
        $oForm->add_fld('plz'     ,'text-label');
        $oForm->add_fld('ort'     ,'text-label');
        $oForm->add_fld('land'    ,'select-single-label',$options_land);


        if($oForm->submit()) {
        	if(!$oForm->errors() && !$oForm->warnings()) {      // submit && no errors && no warnings
             $change=$oForm->changed();
             if ($change) {
               $ok=$oForm->save_post('dbx_user',$where);
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
      if (!$uid) $content='[-modul=dbxUser]dbx_action=login[/modul]';

      return $content;
   }



}




?>
