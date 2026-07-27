<?php
namespace dbx\dbxContact;

class dbxContact {

   public function run() {
      $run = dbx()->get_modul_var('dbx_run1', 'form', 'parameter');

      switch ($run) {
         case 'install':
            return dbx()->redirect('?dbx_modul=dbxContact_admin&dbx_run1=install', 1);

         case 'my':
         case 'tickets':
         case 'list':
            return dbx()->get_include_obj('dbxContactList', 'dbxContact')->run();

         case 'new':
         case 'form':
         case 'contact':
         default:
            return dbx()->get_include_obj('dbxContactForm', 'dbxContact')->run();
      }
   }
}
?>
