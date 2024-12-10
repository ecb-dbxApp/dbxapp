<?php
namespace dbx\LabServer;
include_once dbx_get_base_dir().'dbx/add_ons/sftp/vendor/autoload.php';

class LabServer {

  public function run() {

     // Befunde aus M:\LABOR\BEA-WEB via sFTP zum Internet-Server verschieben
     // Anforderungen vom Internet-Server mit sFTP nach M:\LabCONN\Hive\out3\ verschieben

     $modul =dbx_get_SysVar('dbx_modul');
     $action=dbx_get_ModulVar('dbx_action');
     $work  =dbx_get_ModulVar('dbx_work');

     dbx_set_SysVar('dbx_page','server');


     switch ($action) {

        case 'anforderungen':

          $obj=dbx_get_Modul_include_object('anforderungen');
          $content=$obj->run();
        break;

        case 'befunde':
           $obj=dbx_get_Modul_include_object('befunde');
           $content=$obj->run();
        break;


       default:
         $oTPL=dbx_get_sys_object('dbxTPL');
         $msg['msg']="Modul=($modul) Action=($action) Work($work) is undef. y";
         $content=$oTPL->get_tpl('dbx','alert-warning',$msg);

     } // switch()
     
     return $content;
   } 
   
   
} // class
