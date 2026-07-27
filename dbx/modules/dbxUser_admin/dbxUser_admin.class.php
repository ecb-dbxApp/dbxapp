<?php
namespace dbx\dbxUser_admin;

Class dbxUser_admin {


   public function run($action='') {
      $modul =dbx()->get_system_var('dbx_modul');
      $action=dbx()->get_modul_var('dbx_run1');
      $work  =dbx()->get_modul_var('dbx_run2'); 
      $content='';
      switch ($action) {


        case 'user':
          $obj=dbx()->get_include_obj('dbxUser');
          $content=$obj->run();
        break;

        case 'user_list':
          $obj=dbx()->get_include_obj('user_list');
          $content=$obj->run();
        break;


        default:
        $oTPL=dbx()->get_system_obj('dbxTPL');
        $msg['msg']="Modul=($modul) Action=($action) is undef.";
        $content=$oTPL->get_tpl('dbx','alert-warning',$msg);

      } // action
      return $content;
   }
}

?>
