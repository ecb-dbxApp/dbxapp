<?php
namespace dbx\dbxUser_admin;

Class dbxUser_admin {

   private function unavailable(): string {
      return dbx()->get_system_obj('dbxTPL')->get_tpl('dbx|alert-warning', array(
         'msg' => 'Die Benutzer-Administration konnte nicht geladen werden.',
      ));
   }

   public function run($action='') {
      $modul =dbx()->get_system_var('dbx_modul');
      $action=dbx()->get_modul_var('dbx_run1');
      $work  =dbx()->get_modul_var('dbx_run2');
      $content='';
      switch ($action) {


        case 'user':
          $obj=dbx()->get_include_obj('dbxUser');
          $content=is_object($obj) ? $obj->run() : $this->unavailable();
        break;

        case 'user_list':
          $obj=dbx()->get_include_obj('user_list');
          $content=is_object($obj) ? $obj->run() : $this->unavailable();
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
