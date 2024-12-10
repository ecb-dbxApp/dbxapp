<?php
namespace dbx\dbxContent;


class dbxContent {


  public function run($action='') {
     $uid   =dbx_get_CurrentUser();
     $mid   =dbx_get_sysVar('dbx_modul_id');
     $modul =dbx_get_SysVar('dbx_modul');

     $content="";
     if (!$action) $action=dbx_get_ModulVar('dbx_action','show');

     switch ($action) {
       case 'run':
       case 'show':
           //dbx_set_SysVar('dbx_design','default'); // eventuell nur ween aktuell 'admin' ist
           dbx_set_SysVar('dbx_page'  ,'content'); // default, wenn nicht vorhanden
           $obj=dbx_get_Modul_include_object('dbxContent_content');
           $content=$obj->run();
       break;

       default:
         $content.="<span class='warning action_msg'>Modul=($modul) Action=($action) is undef.</span>";
     } // switch
     return $content;
  } // run()

} // class

?>