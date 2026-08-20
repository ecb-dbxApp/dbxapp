<?php
namespace dbx\dbxUser_admin;

dbx()->get_system_obj('dbxView', 'use');

Class dbxUser_view extends \dbxView {

   public function run() {
      $uid=dbx()->user();
      $work=dbx()->get_modul_var('dbx_run2');
      $rid=($work == 'new_user') ? 0 : dbx()->get_modul_var('rid',$uid);
      $profile=dbx()->get_include_obj('dbxUser_profil')->run('user');

      $o_tpl=dbx()->get_system_obj('dbxTPL');
      $content=$o_tpl->get_tpl('dbxUser_admin|view-profil', array(
         'rid'    => $rid,
         'profile'=> $profile
      ));
      return $content;
   }


}

?>
