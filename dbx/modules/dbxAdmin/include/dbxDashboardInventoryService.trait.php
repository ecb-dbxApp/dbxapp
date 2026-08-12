<?php
namespace dbx\dbxAdmin;

trait dbxDashboardInventoryServiceTrait {

   private function module_count() {
      $count = 0;
      $path = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/');

      if (!is_dir($path)) {
         return 0;
      }

      $files = scandir($path);
      foreach ($files as $file) {
         if ($file === '.' || $file === '..') {
            continue;
         }

         if (is_dir($path . $file) && $file !== 'tpl' && substr($file, 0, 1) !== '.') {
            $count++;
         }
      }

      return $count;
   }

   private function dd_inventory() {
      $records = array();
      $base = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/');

      if (!is_dir($base)) {
         return $records;
      }

      $modules = scandir($base);
      foreach ($modules as $modul) {
         if ($modul === '.' || $modul === '..' || substr($modul, 0, 1) === '.') {
            continue;
         }

         $ddPath = dbx()->os_path($base . $modul . '/dd/');
         if (!is_dir($ddPath)) {
            continue;
         }

         $files = scandir($ddPath);
         foreach ($files as $file) {
            if (!str_ends_with($file, '.dd.php')) {
               continue;
            }

            $dd = str_replace('.dd.php', '', $file);
            if ($dd === '' || $dd === 'new') {
               continue;
            }

            $key = $modul . '|' . $dd;

            try {
               $db = dbx()->get_system_obj('dbxDB');
               $tableDef = $db->get_dd_table($key, 1);

               if (!is_array($tableDef)) {
                  continue;
               }

               // Laufzeit-Test-DDs pruefen reale DB-Abläufe, besitzen ausserhalb
               // ihres kontrollierten Tests aber bewusst keine persistente DB.
               // Sie duerfen deshalb weder Dashboard-Kennzahlen verfälschen noch
               // beim normalen Admin-Aufruf falsche Systemmeldungen erzeugen.
               if (array_key_exists('system_inventory', $tableDef)
                  && (int) $tableDef['system_inventory'] === 0) {
                  continue;
               }

               $server = (string) ($tableDef['server'] ?? '');
               $table  = (string) ($tableDef['table'] ?? '');

               if ($server === '' || $table === '') {
                  continue;
               }

               $exist = $db->get_table_exist($server, $table) ? 1 : 0;
               $count = $exist ? $this->safe_count($key) : 0;

               $records[] = array(
                  'dd'     => $dd,
                  'key'    => $key,
                  'modul'  => $modul,
                  'server' => $server,
                  'table'  => $table,
                  'exist'  => $exist,
                  'count'  => $count,
                  'sync'   => (int) ($tableDef['autosync'] ?? 0),
                  'trace'  => (int) ($tableDef['trace'] ?? 0),
               );
            } catch (\Throwable $e) {
               continue;
            }
         }
      }

      return $records;
   }

   private function metrics() {
      if ($this->metricCache) {
         return $this->metricCache;
      }

      $this->ensure_history_table();

      $inventory = $this->dd_inventory();
      $uniqueTables = array();
      $uniqueServers = array();
      $records = 0;
      $existing = 0;
      $autosync = 0;
      $trace = 0;

      foreach ($inventory as $row) {
         $server = (string) ($row['server'] ?? '');
         $table  = (string) ($row['table'] ?? '');
         $key    = $server . '|' . $table;

         if ($server !== '') {
            $uniqueServers[$server] = 1;
         }

         if ($key !== '|' && !isset($uniqueTables[$key])) {
            $uniqueTables[$key] = 1;
            $records += (int) ($row['count'] ?? 0);
         }

         if (!empty($row['exist'])) {
            $existing++;
         }

         if (!empty($row['sync'])) {
            $autosync++;
         }

         if (!empty($row['trace'])) {
            $trace++;
         }
      }

      $onlineCutoff = date('Y-m-d H:i:s', time() - 900);
      $users = $this->safe_count('dbxUser');
      $activeUsers = $this->safe_count('dbxUser', "status = 'active'");
      $sessions = $this->safe_count('dbxSession');
      $online = $this->safe_count('dbxSession', "update_date >= '" . $onlineCutoff . "'");
      $sysmsg = $this->safe_count('dbxSysMsg');
      $sysmsgRisk = $this->safe_count('dbxSysMsg', "LOWER(status) IN ('warning','error')");
      $missing = $this->safe_count('dbxMissing');
      $traceCount = $this->safe_count('dbxTrace');
      $errorLogExists = dbx()->get_include_obj('dbxSysMsg')->error_log_exists();

      $inventoryCount = count($inventory);
      $healthPercent = $this->percent($existing, max(1, $inventoryCount));
      if ($sysmsgRisk > 0) {
         $healthPercent = max(0, $healthPercent - min(30, $sysmsgRisk * 3));
      }
      if ($missing > 0) {
         $healthPercent = max(0, $healthPercent - min(20, $missing * 2));
      }
      $healthReason = $this->health_reason_label($inventoryCount, $existing, $sysmsgRisk, $missing);
      if ($errorLogExists) {
         $healthPercent = 0;
         $healthReason = 'dbxError.log';
      }

      $this->metricCache = array(
         'inventory'      => $inventory,
         'users'          => $users,
         'active_users'   => $activeUsers,
         'sessions'       => $sessions,
         'online'         => $online,
         'modules'        => $this->module_count(),
         'dd_count'       => $inventoryCount,
         'records'        => $records,
         'databases'      => count($uniqueServers),
         'tables'         => count($uniqueTables),
         'sysmsg'         => $sysmsg,
         'sysmsg_risk'    => $sysmsgRisk,
         'missing'        => $missing,
         'trace_count'    => $traceCount,
         'existing_dd'    => $existing,
         'autosync'       => $autosync,
         'trace_enabled'  => $trace,
         'health_percent' => $healthPercent,
         'health_reason'  => $healthReason,
         'health_error_log' => $errorLogExists ? 1 : 0,
         'request_runtime_ms' => $this->request_runtime_ms(),
         'memory_peak_kb'     => $this->memory_peak_kb(),
      );

      return $this->metricCache;
   }
}
