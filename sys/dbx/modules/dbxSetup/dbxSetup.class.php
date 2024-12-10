<?php
namespace dbx\dbxSetup;


class dbxSetup {

  public function run($action='') {
     $uid   =dbx_get_CurrentUser();
     $mid   =dbx_get_sysVar('dbx_modul_id');
     $modul =dbx_get_SysVar('dbx_modul');

     $content="";
     if (!$action) $action=dbx_get_ModulVar('dbx_action');
     dbx_set_SysVar('dbx_design','_install');
     dbx_set_SysVar('dbx_page'  ,'install');
     dbx_set_SysVar('dbx_has_access'  , 1); 

     switch ($action) {

       case 'install':
           $obj=dbx_get_Modul_include_object('dbxInstall');
           $content=$obj->run();
           break;
       case 'update':
           $obj=dbx_get_Modul_include_object('dbxUpdate');
           $content=$obj->run();
           break;
       default:
           $content.="<span class='warning action_msg'>Install Modul=($modul) Action=($action) is undef.</span>";
     } // switch

     return $content;
  } // run()

} // class

?>
