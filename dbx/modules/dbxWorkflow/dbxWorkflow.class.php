<?php
namespace dbx\dbxWorkflow;

class dbxWorkflow {

   public function run() {
      $run = dbx()->get_modul_var('dbx_run1', 'overview', 'parameter');
      $engine = dbx()->get_include_obj('dbxWorkflowEngine', 'dbxWorkflow');

      if ($run === 'install') {
         return dbx()->redirect('?dbx_modul=dbxWorkflow_admin&dbx_run1=install', 1);
      }

      if ($run === 'start') {
         $workflow = dbx()->get_modul_var('workflow', (string)dbx()->get_cfg('dbxWorkflow', 'default_workflow'), 'parameter');
         return $engine->start($workflow);
      }

      if ($run === 'run') {
         $iid = dbx()->get_modul_var('iid', 0, 'int');
         return $engine->render($iid);
      }

      $workflow = dbx()->get_modul_var('workflow', (string)dbx()->get_cfg('dbxWorkflow', 'default_workflow'), 'parameter');
      return $engine->overview($workflow);
   }
}
?>
