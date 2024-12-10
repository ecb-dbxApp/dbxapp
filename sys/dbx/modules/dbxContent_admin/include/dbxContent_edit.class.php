<?php
namespace dbx\dbxContent_admin;


class dbxContent_edit {

  public function run() {


     $content=''; $ok=false;
     $rid =dbx_get_ModulVar('rid',0,'int');
     $obs =dbx_get_ModulVar('dbx_obs_fld');
     $obv =dbx_get_ModulVar('dbx_obs_val');
     $view=dbx_get_ModulVar('dbx_view');


     //dbx_debug("#Observ  rid=($rid) obs=($obs) obv=($obv)");
     if (!$rid) {
        if ($obs && $obv) $rid=$obv;
     }
     //dbx_debug("EDIT-RID=($rid) ");
     $oForm=dbx_get_sys_object('dbxForm');
     $oForm->init('dbxContent_edit','form-content');
     $oForm->_action= "?dbx_modul=dbxContent_admin&dbx_action=content&dbx_work=edit&dbx_view=$view&rid=$rid";
     if ($rid) {

       $db  = dbx_get_sys_object('dbxDB');
       $lng = dbx_get_SysVar('dbx_lng','de');

       $tab_content='dbx_'.$lng.'_content';
       $tab_folder = $tab_content.'_folder';

       $oForm->_data=$db->select1($tab_content,$rid);
       // All fields must be def bevore save !
       $oForm->add_fld('title'   ,'text-label'          ,'' ,'words|min=1'  ,'Titel'   ,'Der Title darf keine Sonderzeichen beinhalten. ');  // #+
       $oForm->add_fld('content' ,'textarea-label',''   ,'*'                ,'Content' ,'HTML, alles ist erlaubt'                        );  // #+
       //$oForm->add_js_call('content','editor-ace'); //don´t work #todo  


       //dbx_debug("CONTENT-CONTENT");
       if ($oForm->submit()) {
         //dbx_debug("SUBMIT");
         if (!$oForm->errors()) {      // submit && no errors // we ignore warnings
            //dbx_debug("NO-ERRORS");
            $change=$oForm->changed();
            //dbx_debug("CHANGE=($change)");
            if ($change) {
              //dbx_debug("CHANGE=($change)");
              $ok=$oForm->save_post($tab_content,$rid);
              if ( $ok) $oForm->_msg_success   = 'Daten gespeichert';
              if (!$ok) $oForm->_msg_error     = 'Daten konnten nicht gespeichert werden';
            } else {
              $oForm->_msg_success   = 'Keine Änderung';
            }
         }
         if ($oForm->errors()) {
            $oForm->_msg_error = 'Prüfen sie bitte ihre Eingaben';
         }
       }

       $oForm->_msg_info='Content bearbeiten';

     } // rid

     if (!$rid) {
        $oForm->_msg_info='Content bearbeiten - warte -';
        $oForm->_tpl='form-content_wait';
        $oForm->add_obj('msg','dbx|alert-warning','msg=Der Content kann erst nach dem Speichern der Systemdaten vom Content bearbeitet werden.');

        $observer='obs_content_rid';
        $observ['name']   =  $observer;
        $observ['form']   =  'dbx_form_{i}';
        $observ['observ'] =  'content_rid'; // field must be dev inside one form of the view
        $observ['value']  =  $rid; //  $img_src
        $observ['old']    =  $rid;

        $oForm->add_obj($observer,'dbx|observer',$observ);
        $oForm->add_js_observe($observer,1500);   // watch rid from content->sysdata



     }

     $content= $oForm->run();

     return $content;
  } // run()



} // class

?>
