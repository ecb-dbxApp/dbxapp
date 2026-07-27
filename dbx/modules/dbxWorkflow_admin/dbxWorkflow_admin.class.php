<?php
namespace dbx\dbxWorkflow_admin;

class dbxWorkflow_admin {

   public function run() {
      $admin = dbx()->get_include_obj('dbxWorkflowAdmin', 'dbxWorkflow_admin');
      return $admin->run();
   }
}
?>
