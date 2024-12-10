<?php
namespace dbx\dbxUser_admin;

dbx_use_sys_class('dbxView');

Class dbxUser_view extends \dbxView {

   public function run() {
      $uid=dbx_get_CurrentUser();
      $rid=dbx_get_ModulVar('rid',$uid);
      $this->dbxView_init('view-profil');
      $this->set_property('sync','rid');
      $this->set_property('rid' ,$rid);
      $content=$this->dbxView_run();
      return $content;
   }


}

?>