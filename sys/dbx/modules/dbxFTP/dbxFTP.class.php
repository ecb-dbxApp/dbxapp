<?php
namespace dbx\dbxFTP;
 
class dbxFTP {

  

  public function run() {
     $uid   =dbx_get_CurrentUser();
     $mid   =dbx_get_sysVar('dbx_modul_id');
     $modul =dbx_get_SysVar('dbx_modul');

     $action=dbx_get_ModulVar('dbx_action');

     switch ($action) {
       case 'sftp':
           $oSFTP = dbx_get_Modul_include_object('dbxSFTP');
           $content=$oSFTP->run();
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