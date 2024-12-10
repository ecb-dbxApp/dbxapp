<?php
namespace dbx\lda_methoden ;
 
class lda_methoden {


  public function run() {
     $uid   =dbx_get_CurrentUser();
     $mid   =dbx_get_sysVar('dbx_modul_id');
     $modul =dbx_get_SysVar('dbx_modul');

     $action=dbx_get_ModulVar('dbx_action');

     switch ($action) {


        case 'methoden':
          dbx_set_SysVar('dbx_title','Methoden' );
          $obj=dbx_get_Modul_include_object('ldaMethoden');
          $content=$obj->run();
        break;
              
        case 'import':
          $obj=dbx_get_Modul_include_object('MethodenImport');
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