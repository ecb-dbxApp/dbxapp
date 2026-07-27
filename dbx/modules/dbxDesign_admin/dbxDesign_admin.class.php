<?php
namespace dbx\dbxDesign_admin;

/**
 * Administrativer Einstieg für Design-Personalisierung und Design-Wizard.
 */
class dbxDesign_admin {

   /**
    * Übergibt den Request an den spezialisierten Admin-Controller.
    *
    * @return string Modulinhalt.
    */
   public function run() {
      $admin = dbx()->get_include_obj('dbxDesignAdmin', 'dbxDesign_admin');
      return is_object($admin) ? $admin->run() : '';
   }
}
?>
