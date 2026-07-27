<?php
namespace dbx\dbxContent_admin;


class dbxContent_content {

  public function run_nix() {



     $content=''; $ok=false;
     $rid=dbx()->get_modul_var('rid',0);
     $obs=dbx()->get_modul_var('dbx_obs_fld');
     $obv=dbx()->get_modul_var('dbx_obs_val');
     //dbx_debug("#Observ  rid=($rid) obs=($obs) obv=($obv)");
     if (!$rid) {
        if ($obs && $obv) $rid=$obv;
     }
     //dbx_debug("EDIT-RID=($rid) ");
     $oForm=dbx()->get_system_obj('dbxForm');
     $oForm->init('dbxContent_edit','form-content');
     if ($rid) {

       $db  = dbx()->get_system_obj('dbxDB');
       $lng = dbx()->get_system_var('dbx_lng','de');

       $tab_content = dbx_lng_name('content', $lng);
       $tab_folder = $tab_content.'_folder';

       $oForm->_data=$db->select1($tab_content,$rid);
       // All fields must be def bevore save !
       $oForm->add_fld('title'   ,'text-label'          ,rules: 'words|min=1'  , label: 'Titel'   , errormsg: 'Der Title darf keine Sonderzeichen beinhalten. ');  // #+
       $oForm->add_fld('content' ,'textarea-label',''   ,rules: '*'            , label: 'Content' );  // #+
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

     $oForm->_action='?dbx_modul=dbxContent_admin&dbx_run1=content&rid='.$rid;

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
        $oForm->add_js_observe($observer,11500);   // watch rid from content->sysdata



     }

     $content= $oForm->run();

     return $content;
  } // run()


  function run() {
    $modul =dbx()->get_system_var('dbx_modul');
    $action=dbx()->get_modul_var('dbx_run1');
    $work  =dbx()->get_modul_var('dbx_run2');

    //return "action=($action) work=($work)";

    switch ($work) {

      case 'edit_content':
        $obj=dbx()->get_include_obj('dbxContent_view');
        $content=$obj->run();
      break;      

      case 'edit_sysdata':
        $obj=dbx()->get_include_obj('dbxContent_sysdata');
        $content=$obj->run();
      break;
    
      case 'images':
        $obj=dbx()->get_include_obj('dbxContent_images');
        $content=$obj->run();
      break;

      case 'ibrowser':
        $obj=dbx()->get_include_obj('dbxContent_ibrowser');
        $content=$obj->run();
      break;

      case 'list_flat':
        $obj=dbx()->get_include_obj('dbxContent_list');
        $content=$obj->run('flat');
      break;

      case 'list_tree':
        $obj=dbx()->get_include_obj('dbxContent_list');
        $content=$obj->run('tree');
      break;

      case 'edit':
        $obj=dbx()->get_include_obj('dbxContent_edit');
        $content=$obj->run();
      break;

      case 'list_folder':
        $obj=dbx()->get_include_obj('dbxContent_folder');
        $content=$obj->run();
      break;
      
      case 'new_folder':
        $obj=dbx()->get_include_obj('dbxContent_folder');
        $content=$obj->run();
      break;

      case 'edit_folder':
        $obj=dbx()->get_include_obj('dbxContent_folder');
        $content=$obj->run();
      break;
   
      default:
        $oTPL=dbx()->get_system_obj('dbxTPL');
        $msg['msg']="Modul=($modul) Action=($action) Work=($work) is undef.";
        $content=$oTPL->get_tpl('dbx','alert-warning',$msg);

    };


     return $content;



  } 


} // class

?>
