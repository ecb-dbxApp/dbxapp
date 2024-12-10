<?php
namespace dbx\dbxUser_admin;

Class dbxUser_admin {


   public function run($action='') {
      $modul =dbx_get_SysVar('dbx_modul');
      $action=dbx_get_ModulVar('dbx_action');
      $work  =dbx_get_ModulVar('dbx_work'); 
      $content='';
      //dbx_set_SysVar('dbx_page','admin');
      switch ($action) {


        case 'user':
          $obj=dbx_get_Modul_include_object('dbxUser');
          $content=$obj->run();
        break;


        default:
        $oTPL=dbx_get_sys_object('dbxTPL');
        $msg['msg']="Modul=($modul) Action=($action) is undef.";
        $content=$oTPL->get_tpl('dbx','alert-warning',$msg);

      } // action
      return $content;
   }
}

?>
