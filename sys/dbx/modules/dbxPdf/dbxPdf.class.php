<?php
namespace dbx\dbxPdf;
include_once dbx_get_base_dir().'dbx/add_ons/dompdf/vendor/autoload.php';

class dbxPdf {

 
  public function run() {
     $uid   =dbx_get_CurrentUser();
     $mid   =dbx_get_sysVar('dbx_modul_id');
     $modul =dbx_get_SysVar('dbx_modul');

     $action=dbx_get_ModulVar('dbx_action');

     switch ($action) {

      case 'send-pdf':
        $obj=dbx_get_Modul_include_object('dbxPdf_RecSend');
        $content=$obj->run('rec');

        $obj=dbx_get_Modul_include_object('dbxPdf_RecSend');
        $content.=$obj->run('gkv');

        $obj=dbx_get_Modul_include_object('dbxPdf_RecSend');
        $content.=$obj->run('pkv');
        break;



       case 'send-rec':
           $obj=dbx_get_Modul_include_object('dbxPdf_RecSend');
           $content=$obj->run('rec');
           break;

       case 'send-gkv':
           $obj=dbx_get_Modul_include_object('dbxPdf_RecSend');
           $content=$obj->run('gkv');
           break;

       case 'send-pkv':
           $obj=dbx_get_Modul_include_object('dbxPdf_RecSend');
           $content=$obj->run('pkv');
           break;


       default:
         $oTPL=dbx_get_sys_object('dbxTPL');
         $msg['msg']="Modul=($modul) Action=($action) is undef.";
         $content=$oTPL->get_tpl('dbx','alert-warning',$msg);

     } // sqitch()
     
     return $content;
   } 
   
   
} // class


//require (__DIR__ . '/include/dompdf/vendor/autoload.php');

?>