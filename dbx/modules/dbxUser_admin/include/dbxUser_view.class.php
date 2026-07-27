<?php
namespace dbx\dbxUser_admin;

dbx()->use_system_class('dbxView');

Class dbxUser_view extends \dbxView {

   public function run() {
      $uid=dbx()->user();
      $work=dbx()->get_modul_var('dbx_run2');
      $rid=($work == 'new_user') ? 0 : dbx()->get_modul_var('rid',$uid);
      $profile=dbx()->get_include_obj('dbxUser_profil')->run('user');

      $oTPL=dbx()->get_system_obj('dbxTPL');
      $content=$oTPL->get_tpl('dbxUser_admin|view-profil', array(
         'rid'    => $rid,
         'profile'=> $profile
      ));
      return $content;
   }


}

?>
