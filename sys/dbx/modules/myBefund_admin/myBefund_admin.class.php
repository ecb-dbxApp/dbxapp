<?php
namespace dbx\myBefund_admin;


Class myBefund_admin {

   public function run() {
    $modul =dbx_get_SysVar('dbx_activ_modul'); 
    $action=dbx_get_ModulVar('dbx_action','','parameter');

     //dbx_debug ("myBefund_admin=($action)");

     switch ($action) {
       case 'import':
           $obj=dbx_get_Modul_include_object('myImport');
           $content=$obj->run();

       break;

       case 'send_ldtx':
         $obj=dbx_get_Modul_include_object('myLDTx');
         $content=$obj->run();
       break;

       case 'import_user':
         $obj=dbx_get_Modul_include_object('dbxUser_import','dbxUser_admin');
         $content=$obj->run();
       break;

       case 'send-rec-pdf':
         $obj=dbx_get_Modul_include_object('dbxRecPdf','dbxPdf');
         $content=$obj->run();
       break;


       default:
         $oTPL=dbx_get_sys_object('dbxTPL');
         $msg['msg']="x Modul=($modul) Action=($action) is undef.";
         $content=$oTPL->get_tpl('dbx','alert-warning',$msg);
       break;

    }
    return $content;
  }
}

?>
