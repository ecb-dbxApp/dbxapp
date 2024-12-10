<?php
namespace dbx\dbxConstruct;

Class dbxConstruct {


   public function run() {
      dbx_set_SysVar('dbx_page'  ,'home');
      dbx_set_SysVar('dbx_design','_construct');
      return 'System is under Consruction';
   }
}

?>