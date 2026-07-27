<?php
namespace dbx\dbxConstruct;

Class dbxConstruct {


   public function run() {
      dbx()->set_system_var('dbx_page'  ,'home');
      dbx()->set_system_var('dbx_design','_construct');
      return 'System is under Consruction';
   }
}

?>