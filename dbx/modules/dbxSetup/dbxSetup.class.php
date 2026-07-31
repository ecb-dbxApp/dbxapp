<?php
namespace dbx\dbxSetup;


class dbxSetup {

  public function run($action='') {
     $uid   =dbx()->user();
     $mid   =dbx()->get_system_var('dbx_modul_id');
     $modul =dbx()->get_system_var('dbx_modul');

     $content="";
     if (!$action) $action=dbx()->get_modul_var('dbx_run1');
     dbx()->set_system_var('dbx_design','dbxapp');
     dbx()->set_system_var('dbx_page'  ,'install');
     dbx()->set_system_var('dbx_title' ,'dbxapp installieren');
     dbx()->set_system_var('dbx_has_access'  , 1); 

     switch ($action) {

       case 'install':
           $obj=dbx()->get_include_obj('dbxInstall');
           $content=$obj->run();
           break;
       case 'update':
           $obj=dbx()->get_include_obj('dbxUpdate');
           $content=$obj->run();
           break;
       default:
           $content.="<span class='warning action_msg'>Install Modul=($modul) Action=($action) is undef.</span>";
     } // switch

     return $content;
  } // run()

} // class

?>
