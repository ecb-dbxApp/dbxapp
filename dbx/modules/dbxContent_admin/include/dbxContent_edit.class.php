<?php
namespace dbx\dbxContent_admin;

use dbx\dbxContent\dbxContent_permalink;

require_once dirname(__DIR__, 2) . '/dbxContent/include/dbxContent_bootstrap_sync.php';

class dbxContent_edit {

  public function run() {


     $content=''; $ok=false;
     $rid =dbx()->get_modul_var('rid',0,'int');
     $obs =dbx()->get_modul_var('dbx_obs_fld');
     $obv =dbx()->get_modul_var('dbx_obs_val');
     $view=dbx()->get_modul_var('dbx_view');


     //dbx_debug("#Observ  rid=($rid) obs=($obs) obv=($obv)");
     if (!$rid) {
        if ($obs && $obv) $rid=$obv;
     }
     //dbx_debug("EDIT-RID=($rid) ");
     $oForm=dbx()->get_system_obj('dbxForm');
     $oForm->init('dbxContent_edit','form-content');
     $oForm->_action= "?dbx_modul=dbxContent_admin&dbx_run1=content&dbx_run2=edit&dbx_view=$view&rid=$rid";
     if ($rid) {

       $db  = dbx()->get_system_obj('dbxDB');
       $lng = dbx()->get_system_var('dbx_lng','de');

       $tab_content = dbx()->lng_name('content', $lng);
       $tab_folder = $tab_content.'_folder';

       $oForm->_data=$db->select1($tab_content,$rid);
       // All fields must be def bevore save !
       $oForm->add_fld('title'   ,'text-label'          ,rules: 'words|min=1'  ,title: 'Titel'   ,errormsg: 'Der Title darf keine Sonderzeichen beinhalten. ');  // #+
       $oForm->add_fld('content' ,'textarea-label',''   ,rules: '*'            ,title: 'Content' );  // #+
       //$oForm->add_js_call('content','editor-ace'); //don´t work #todo  


       //dbx_debug("CONTENT-CONTENT");
       if ($oForm->submit()) {
         //dbx_debug("SUBMIT");
         if (!$oForm->errors()) {      // submit && no errors // we ignore warnings
            //dbx_debug("NO-ERRORS");
            $change=$oForm->changed();
            $post_values = array();
            if (dbxContent_permalink::normalize($oForm->_data['permalink'] ?? '') === '') {
              $post_values['permalink'] = dbxContent_permalink::build($db, $tab_folder, (int)($oForm->_data['folder'] ?? 0), $_POST['title'] ?? ($oForm->_data['title'] ?? ''));
              $oForm->set_post('permalink', $post_values['permalink']);
              $change = 1;
            }
            //dbx_debug("CHANGE=($change)");
            if ($change) {
              //dbx_debug("CHANGE=($change)");
              $ok=$oForm->save_post($tab_content,$rid,$post_values);
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
