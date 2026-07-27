<?php
namespace dbx\dbxContact_admin;

class dbxContact_admin {

   private function unavailable(): string {
      return dbx()->get_system_obj('dbxTPL')->get_tpl('dbx|alert-warning', array(
         'msg' => 'Die Ticket-Administration konnte nicht geladen werden.',
      ));
   }

   public function run() {
      $admin = dbx()->get_include_obj('dbxContactAdmin', 'dbxContact_admin');
      return is_object($admin) ? $admin->run() : $this->unavailable();
   }
}
