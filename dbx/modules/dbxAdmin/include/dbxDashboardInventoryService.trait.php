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

         $dd_path = dbx()->os_path($base . $modul . '/dd/');
         if (!is_dir($dd_path)) {
            continue;
         }

         $files = scandir($dd_path);
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
               $table_def = $db->get_dd_table($key, 1);

               if (!is_array($table_def)) {
                  continue;
               }

               // Laufzeit-Test-DDs pruefen reale DB-Abläufe, besitzen ausserhalb
               // ihres kontrollierten Tests aber bewusst keine persistente DB.
               // Sie duerfen deshalb weder Dashboard-Kennzahlen verfälschen noch
               // beim normalen Admin-Aufruf falsche Systemmeldungen erzeugen.
               if (array_key_exists('system_inventory', $table_def)
                  && (int) $table_def['system_inventory'] === 0) {
                  continue;
               }

               $server = (string) ($table_def['server'] ?? '');
               $table  = (string) ($table_def['table'] ?? '');

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
                  'sync'   => (int) ($table_def['autosync'] ?? 0),
                  'trace'  => (int) ($table_def['trace'] ?? 0),
               );
            } catch (\Throwable $e) {
               continue;
            }
         }
      }

      return $records;
   }

   private function metrics() {
      if ($this->metric_cache) {
         return $this->metric_cache;
      }

      $this->ensure_history_table();

      $inventory = $this->dd_inventory();
      $unique_tables = array();
      $unique_servers = array();
      $records = 0;
      $existing = 0;
      $autosync = 0;
      $trace = 0;

      foreach ($inventory as $row) {
         $server = (string) ($row['server'] ?? '');
         $table  = (string) ($row['table'] ?? '');
         $key    = $server . '|' . $table;

         if ($server !== '') {
            $unique_servers[$server] = 1;
         }

         if ($key !== '|' && !isset($unique_tables[$key])) {
            $unique_tables[$key] = 1;
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

      $online_cutoff = date('Y-m-d H:i:s', time() - 900);
      $users = $this->safe_count('dbxUser');
      $active_users = $this->safe_count('dbxUser', "status = 'active'");
      $sessions = $this->safe_count('dbxSession');
      $online = $this->safe_count('dbxSession', "update_date >= '" . $online_cutoff . "'");
      $sysmsg = $this->safe_count('dbxSysMsg');
      $sysmsg_risk = $this->safe_count('dbxSysMsg', "LOWER(status) IN ('warning','error')");
      $missing = $this->safe_count('dbxMissing');
      $trace_count = $this->safe_count('dbxTrace');
      $error_log_exists = dbx()->get_include_obj('dbxSysMsg')->error_log_exists();

      $inventory_count = count($inventory);
      $health_percent = $this->percent($existing, max(1, $inventory_count));
      if ($sysmsg_risk > 0) {
         $health_percent = max(0, $health_percent - min(30, $sysmsg_risk * 3));
      }
      if ($missing > 0) {
         $health_percent = max(0, $health_percent - min(20, $missing * 2));
      }
      $health_reason = $this->health_reason_label($inventory_count, $existing, $sysmsg_risk, $missing);
      if ($error_log_exists) {
         $health_percent = 0;
         $health_reason = 'dbxError.log';
      }

      $this->metric_cache = array(
         'inventory'      => $inventory,
         'users'          => $users,
         'active_users'   => $active_users,
         'sessions'       => $sessions,
         'online'         => $online,
         'modules'        => $this->module_count(),
         'dd_count'       => $inventory_count,
         'records'        => $records,
         'databases'      => count($unique_servers),
         'tables'         => count($unique_tables),
         'sysmsg'         => $sysmsg,
         'sysmsg_risk'    => $sysmsg_risk,
         'missing'        => $missing,
         'trace_count'    => $trace_count,
         'existing_dd'    => $existing,
         'autosync'       => $autosync,
         'trace_enabled'  => $trace,
         'health_percent' => $health_percent,
         'health_reason'  => $health_reason,
         'health_error_log' => $error_log_exists ? 1 : 0,
         'request_runtime_ms' => $this->request_runtime_ms(),
         'memory_peak_kb'     => $this->memory_peak_kb(),
      );

      return $this->metric_cache;
   }
}
