<?php
namespace dbx\myArzt;
 
class myArzt {


  public function run() {
     $modul =dbx_get_SysVar('dbx_modul');
     $action=dbx_get_ModulVar('dbx_action');

     switch ($action) {


        case 'arzt':
          dbx_set_SysVar('dbx_title','Einsender Ärzte' );
          $obj=dbx_get_Modul_include_object('Arzt');
          $content=$obj->run();
        break;        


       default:
         $oTPL=dbx_get_sys_object('dbxTPL');
         $msg['msg']="Modul=($modul) Action=($action) is undef.";
         $content=$oTPL->get_tpl('dbx','alert-warning',$msg);

     } // sqitch()
     
     return $content;
   } 
   
   
} // class

?>