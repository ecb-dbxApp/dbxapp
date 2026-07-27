<?php
namespace dbx\dbxShop_admin;

class dbxShop_admin {

   private function unavailable(): string {
      return dbx()->get_system_obj('dbxTPL')->get_tpl('dbx|alert-warning', array(
         'msg' => 'Die Shop-Administration konnte nicht geladen werden.',
      ));
   }

   public function run() {
      $admin = dbx()->get_include_obj('dbxShopAdmin', 'dbxShop_admin');
      return is_object($admin) ? $admin->run() : $this->unavailable();
   }
}
?>
