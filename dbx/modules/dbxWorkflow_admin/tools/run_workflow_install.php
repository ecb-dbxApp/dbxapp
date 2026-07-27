<?php
/**
 * CLI-Runner fuer den vorgesehenen dbxWorkflow-DD-Sync.
 *
 * Aufruf:
 *   php dbx/modules/dbxWorkflow_admin/tools/run_workflow_install.php
 */
$base = dirname(__DIR__, 4);
chdir($base);
$_SERVER['REQUEST_URI'] = '/dbxapp/';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SCRIPT_NAME'] = '/dbxapp/index.php';

define('dbxSystem', 'dbxWebApp');
define('dbxRunAsAdmin', 1);

require $base . '/dbx/vendor/autoload.php';
require_once $base . '/dbx/include/dbxKernel.php';

$result = array('dds' => array(), 'seeded' => false, 'errors' => array());
$oDD = dbx()->get_system_obj('dbxDD');

foreach (array('workflowDefinition', 'workflowInstance', 'workflowStep', 'workflowModuleBind') as $dd) {
   try {
      $oDD->sync_dd_to_db('dbxWorkflow', $dd, 'reset');
      $status = '';
      for ($i = 0; $i < 80; $i++) {
         $state = $oDD->sync_dd_to_db('dbxWorkflow', $dd, 'apply');
         $status = (string) ($state['status'] ?? '');
         if ($status === 'finished' || $status === 'error') break;
      }
      $result['dds'][$dd] = $status;
      if ($status !== 'finished') {
         $result['errors'][] = $dd . ': ' . ($status !== '' ? $status : 'kein Abschlussstatus');
      }
   } catch (Throwable $e) {
      $result['errors'][] = $dd . ': ' . $e->getMessage();
   }
}

if (!$result['errors']) {
   dbx()->get_include_obj('dbxWorkflowEngine', 'dbxWorkflow')->seed_demo_definitions();
   $result['seeded'] = true;
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($result['errors'] ? 1 : 0);

