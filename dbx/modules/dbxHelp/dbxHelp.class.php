<?php
namespace dbx\dbxHelp;


class dbxHelp {


  public function run() {
 
     $modul =dbx()->get_system_var('dbx_modul');

     $content="";
     $action =dbx()->get_modul_var('dbx_run1','dbx');
     $work   =dbx()->get_modul_var('dbx_run2','show');

     if ($action === 'context' || $action === 'help') {
        $obj = dbx()->get_include_obj('dbxModuleHelpWindow', 'dbxHelp');
        return is_object($obj) ? (string)$obj->run() : '';
     }

     switch ($work) {
       case 'show':
           dbx()->set_system_var('dbx_page','_window');
           $obj=dbx()->get_include_obj('dbxHelp_content');
           $content=$obj->run();
       break;

       default:
         $content.="<div class='warning action_msg'>Modul=($modul) Action=($action) Work ($work) is undef.</div>";
     } // switch
     return $content;
  } // run()

} // class
