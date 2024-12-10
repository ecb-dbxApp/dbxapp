<?php
namespace dbx\dbxHelp;


class dbxHelp {


  public function run() {
 
     $modul =dbx_get_SysVar('dbx_modul');

     $content="";
     $action =dbx_get_ModulVar('dbx_action','dbx');
     $work   =dbx_get_ModulVar('dbx_work','show');

     switch ($work) {
       case 'show':
           //dbx_set_SysVar('dbx_design','default'); // eventuell nur ween aktuell 'admin' ist
           dbx_set_SysVar('dbx_page'  ,'content'); // default, wenn nicht vorhanden
           $obj=dbx_get_Modul_include_object('dbxHelp_content');
           $content=$obj->run();
       break;

       default:
         $content.="<div class='warning action_msg'>Modul=($modul) Action=($action) Work ($work) is undef.</div>";
     } // switch
     return $content;
  } // run()

} // class
