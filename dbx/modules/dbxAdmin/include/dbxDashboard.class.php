<?php
namespace dbx\dbxAdmin;

dbx()->use_system_class('dbxReport');
dbx()->use_system_class('dbxForm');
dbx()->use_system_class('dbxDD');

class dbxDashboard extends \dbxObj {

   private const HISTORY_DD = 'dbxAdmin|dbxAdminDashboardMetric';
   private const PERF_REQUEST_DD = 'dbx|dbxPerformanceRequest';
   private const PERF_TIMER_DD = 'dbx|dbxPerformanceTimer';
   private const HISTORY_BUCKET_MINUTES = 15;

   private $metricCache = array();
   private $historyReady = null;
   private $performanceRequestAverage = null;
   private $performanceTimerAverages = null;
   private $performanceModuleAverages = null;
   private $dashboardMessageKey = '';
   private $dashboardMessageError = false;

   private function fmt($value) {
      $value = (int) $value;
      return number_format($value, 0, ',', '.');
   }

   private function percent($value, $max) {
      $value = (float) $value;
      $max   = (float) $max;

      if ($max <= 0) {
         return 0;
      }

      return max(0, min(100, (int) round(($value / $max) * 100)));
   }

   private function health_reason_label(int $inventoryCount, int $existingCount, int $sysmsgRisk, int $missing): string {
      $reasons = array();

      if ($missing > 0) {
         $reasons[] = 'Missing';
      }

      if ($sysmsgRisk > 0) {
         $reasons[] = 'SysMsg';
      }

      if ($existingCount < $inventoryCount) {
         $reasons[] = 'DB';
      }

      return count($reasons) ? implode('/', $reasons) : 'OK';
   }

   /**
    * Verarbeitet ausschließlich die feste zentrale Fehlerprotokoll-Datei.
    *
    * Die aufrufende URL wird mit dbx()->action_url() erzeugt. Dadurch greift
    * die zentrale Token-Automatik für die Delete-Aktion mit gebundener RID.
    */
   private function process_error_log_action(): void {
      $work = dbx()->get_modul_var('dbx_do', '', 'parameter');
      if ($work !== 'delete_error_log') {
         return;
      }

      $sysMsg = dbx()->get_include_obj('dbxSysMsg');
      $result = $sysMsg->delete_error_log();

      if ($result === 'deleted') {
         $this->dashboardMessageKey = 'error_log_deleted';
      } elseif ($result === 'empty') {
         $this->dashboardMessageKey = 'error_log_empty';
      } else {
         $this->dashboardMessageKey = 'error_log_delete_error';
         $this->dashboardMessageError = true;
      }

      $this->metricCache = array();
   }

   /**
    * Rendert das vorhandene Fehlerprotokoll als sicher maskierten Scrollbereich.
    *
    * Logzeilen können Request-Inhalte und damit HTML enthalten. Das Escaping
    * ist hier zwingend, damit das Admin-Protokoll niemals Markup ausführt.
    */
   private function error_log_panel(\dbxForm $texts): string {
      $sysMsg = dbx()->get_include_obj('dbxSysMsg');
      if (!$sysMsg->error_log_exists()) {
         return '';
      }

      $file = $sysMsg->get_error_log_file();
      $content = @file_get_contents($file);
      if ($content === false) {
         $content = $texts->get_fd_message('error_log_read_error');
      }

      clearstatcache(true, $file);
      $size = @filesize($file);
      $size = $size === false ? strlen($content) : (int)$size;

      $action = dbx()->action_url(
         '?dbx_modul=dbxAdmin&dbx_run1=dashboard&dbx_run2=delete_error_log'
         . '&dbx_do=delete_error_log&rid=error_log'
      );

      return dbx()->get_system_obj('dbxTPL')->get_tpl(
         'dbxAdmin|admin-dashboard-error-log',
         array(
            'error_log_title'    => dbx()->esc($texts->get_fd_message('error_log_title')),
            'error_log_subtitle' => dbx()->esc($texts->get_fd_message('error_log_subtitle')),
            'file_label'         => dbx()->esc($texts->get_fd_message('error_log_file_label')),
            'file'               => 'files/dbxError.log',
            'size'               => $this->fmt($size),
            'bytes_label'        => dbx()->esc($texts->get_fd_message('error_log_bytes')),
            'content'            => htmlspecialchars(
               $content,
               ENT_QUOTES | ENT_SUBSTITUTE,
               'UTF-8'
            ),
            'delete_action'      => dbx()->esc($action),
            'delete_label'       => dbx()->esc($texts->get_fd_message('error_log_delete_label')),
            'delete_title'       => dbx()->esc($texts->get_fd_message('error_log_delete_title')),
            'delete_confirm'     => dbx()->esc($texts->get_fd_message('error_log_delete_confirm')),
            'delete_hint'        => dbx()->esc($texts->get_fd_message('error_log_delete_hint')),
         )
      );
   }

   private function request_runtime_ms() {
      $runtime = dbx()->get_system_obj('dbxRuntime');
      return max(0, (int)round($runtime->current_php_runtime() * 1000));
   }

   private function memory_peak_kb() {
      $bytes = (int)dbx()->get_system_obj('dbxRuntime')->current_memory_bytes();

      if ($bytes <= 0) {
         global $dbx_run_timer;
         $startMemory = isset($dbx_run_timer['system']['start_memory']) && is_numeric($dbx_run_timer['system']['start_memory'])
            ? (int) $dbx_run_timer['system']['start_memory']
            : 0;

         if ($startMemory > 0) {
            $bytes = max(0, (int) memory_get_peak_usage() - $startMemory);
         }
      }

      if ($bytes <= 0) {
         $bytes = (int) memory_get_usage();
      }

      return max(1, (int) ceil($bytes / 1024));
   }

   private function card_action($href, $label) {
      $tpl = dbx()->get_system_obj('dbxTPL');

      return $tpl->get_tpl('dbxAdmin|admin-dashboard-card-action', array(
         'href'  => $href,
         'label' => $label,
      ));
   }

   private function collapse_action($target, $label = 'Aufklappen', $expanded = false) {
      $tpl = dbx()->get_system_obj('dbxTPL');

      return $tpl->get_tpl('dbxAdmin|admin-dashboard-collapse-action', array(
         'target'   => $target,
         'label'    => $label,
         'expanded' => $expanded ? 'true' : 'false',
      ));
   }

   private function help_action(string $topic): string {
      try {
         $help = dbx()->get_include_obj('dbxAdminHelp', 'dbxAdmin');
         return (string) $help->button($topic);
      } catch (\Throwable $e) {
         return '';
      }
   }

   private function card_bar_data($title, $icon, $subtitle = '', $action = '') {
      return array(
         'bar_class'         => 'dbx-module-bar',
         'bar_title_class'   => 'dbx-module-bar-titleblock',
         'bar_actions_class' => 'dbx-module-bar-actions',
         'bar_title'         => $title,
         'bar_icon'          => $icon,
         'bar_subtitle'      => $subtitle,
         'bar_title_pre'     => '',
         'bar_title_heading_attrs' => '',
         'bar_actions'       => $action,
         'bar_extra'         => '',
         'title'             => $title,
         'icon'              => $icon,
         'subtitle'          => $subtitle,
         'action'            => $action,
      );
   }

   private function card_bar($title, $icon, $subtitle = '', $action = '') {
      $tpl = dbx()->get_system_obj('dbxTPL');

      return $tpl->get_tpl('dbx|component-bar', $this->card_bar_data($title, $icon, $subtitle, $action));
   }

   private function safe_count($dd, $where = '') {
      $count = 0;

      try {
         $db = dbx()->get_system_obj('dbxDB');
         $res = $db->count($dd, $where);
         if (is_numeric($res) && (int) $res > 0) {
            $count = (int) $res;
         }
      } catch (\Throwable $e) {
         $count = 0;
      }

      return $count;
   }

   private function safe_select($dd, $where = '', $columns = '*', $orderby = '', $asc_desc = 'ASC', $groupby = '', $max = 0, $offset = 0) {
      $rows = array();

      try {
         $db = dbx()->get_system_obj('dbxDB');
         $res = $db->select($dd, $where, $columns, $orderby, $asc_desc, $groupby, $max, $offset, 0);
         if (is_array($res)) {
            $rows = $res;
         }
      } catch (\Throwable $e) {
         $rows = array();
      }

      return $rows;
   }

   private function ensure_performance_tables() {
      try {
         $dd = dbx()->get_system_obj('dbxDD');
         return $dd->create_db_tab(self::PERF_REQUEST_DD) && $dd->create_db_tab(self::PERF_TIMER_DD);
      } catch (\Throwable $e) {
         return false;
      }
   }

   private function ensure_history_table() {
      if ($this->historyReady !== null) {
         return $this->historyReady;
      }

      $this->historyReady = false;

      try {
         $dd = dbx()->get_system_obj('dbxDD');
         $this->historyReady = $dd->create_db_tab(self::HISTORY_DD) ? $this->ensure_history_columns() : false;
      } catch (\Throwable $e) {
         $this->historyReady = false;
      }

      return $this->historyReady;
   }

   private function ensure_history_columns() {
      $server = 'dbxAdmin|dbxAdmin.db3';
      $table = 'dbx_admin_dashboard_metric';

      try {
         $db = dbx()->get_system_obj('dbxDB');
         $rows = $db->select_query($server, 'PRAGMA table_info(' . $table . ')');

         if (!is_array($rows)) {
            return false;
         }

         $existing = array();
         foreach ($rows as $row) {
            $name = (string) ($row['name'] ?? '');
            if ($name !== '') {
               $existing[$name] = 1;
            }
         }

         $fields = array(
            'request_runtime_ms' => array('name' => 'request_runtime_ms', 'type' => 'int', 'length' => '11', 'default' => '0', 'index' => ''),
            'memory_peak_mb'     => array('name' => 'memory_peak_mb', 'type' => 'int', 'length' => '11', 'default' => '0', 'index' => ''),
            'memory_peak_kb'     => array('name' => 'memory_peak_kb', 'type' => 'int', 'length' => '11', 'default' => '0', 'index' => ''),
         );

         $dd = dbx()->get_system_obj('dbxDD');
         foreach ($fields as $name => $field) {
            if (!isset($existing[$name]) && !$dd->add_db_field_from_dd($server, $table, $field)) {
               return false;
            }
         }

         return true;
      } catch (\Throwable $e) {
         return false;
      }
   }

   private function history_bucket_time() {
      $bucketSeconds = self::HISTORY_BUCKET_MINUTES * 60;
      return (int) (floor(time() / $bucketSeconds) * $bucketSeconds);
   }

   private function history_snapshot_record($metrics, $bucketTime) {
      $now = date('Y-m-d H:i:s');
      $uid = (int) dbx()->user();

      return array(
         'bucket_key'     => date('YmdHi', $bucketTime),
         'snapshot_date'  => date('Y-m-d H:i:s', $bucketTime),
         'create_date'    => $now,
         'create_uid'     => $uid,
         'update_date'    => $now,
         'update_uid'     => $uid,
         'owner'          => $uid,
         'users'          => (int) ($metrics['users'] ?? 0),
         'online'         => (int) ($metrics['online'] ?? 0),
         'modules'        => (int) ($metrics['modules'] ?? 0),
         'records'        => (int) ($metrics['records'] ?? 0),
         'databases'      => (int) ($metrics['databases'] ?? 0),
         'health_percent' => (int) ($metrics['health_percent'] ?? 0),
         'active_users'   => (int) ($metrics['active_users'] ?? 0),
         'sessions'       => (int) ($metrics['sessions'] ?? 0),
         'tables'         => (int) ($metrics['tables'] ?? 0),
         'sysmsg_risk'    => (int) ($metrics['sysmsg_risk'] ?? 0),
         'missing'        => (int) ($metrics['missing'] ?? 0),
         'request_runtime_ms' => (int) ($metrics['request_runtime_ms'] ?? 0),
         'memory_peak_mb'     => (int) ceil(((int) ($metrics['memory_peak_kb'] ?? 0)) / 1024),
         'memory_peak_kb'     => (int) ($metrics['memory_peak_kb'] ?? 0),
      );
   }

   private function store_history_snapshot($metrics) {
      if (!$this->ensure_history_table()) {
         return false;
      }

      try {
         $db = dbx()->get_system_obj('dbxDB');
         $bucketTime = $this->history_bucket_time();
         $record = $this->history_snapshot_record($metrics, $bucketTime);
         $rows = $db->select(self::HISTORY_DD, array('bucket_key' => $record['bucket_key']), array('id'), 'id', 'DESC', '', 1, 0, 0);
         $id = is_array($rows) ? (int) ($rows[0]['id'] ?? 0) : 0;

         if ($id > 0) {
            unset($record['create_date'], $record['create_uid'], $record['owner']);
            return $db->update(self::HISTORY_DD, $record, array('id' => $id), 0, 1, 1, 0) > 0;
         }

         return $db->insert(self::HISTORY_DD, $record, 0, 1, 1, 0) > 0;
      } catch (\Throwable $e) {
         return false;
      }
   }

   private function metric_history_rows($metrics, $max = 48) {
      if (!$this->ensure_history_table()) {
         return array($this->history_snapshot_record($metrics, $this->history_bucket_time()));
      }

      $rows = $this->safe_select(
         self::HISTORY_DD,
         '',
         array('snapshot_date', 'users', 'online', 'modules', 'records', 'databases', 'health_percent', 'request_runtime_ms', 'memory_peak_kb'),
         'snapshot_date',
         'DESC',
         '',
         $max,
         0
      );

      if (!$rows) {
         return array($this->history_snapshot_record($metrics, $this->history_bucket_time()));
      }

      return array_reverse($rows);
   }

   private function metric_series_definitions() {
      return array(
         'users'          => array('label' => 'Benutzer', 'tone' => 'teal'),
         'online'         => array('label' => 'Online', 'tone' => 'green'),
         'modules'        => array('label' => 'Module', 'tone' => 'navy'),
         'records'        => array('label' => 'Datensaetze', 'tone' => 'amber'),
         'databases'      => array('label' => 'Datenbanken', 'tone' => 'cyan'),
         'health_percent' => array('label' => 'Systemzustand', 'tone' => 'red'),
         'request_runtime_ms' => array('label' => 'Speed', 'tone' => 'purple'),
         'memory_peak_kb'     => array('label' => 'DBX Memory', 'tone' => 'slate'),
      );
   }

   private function metric_history($metrics) {
      $rows = $this->metric_history_rows($metrics);
      $labels = array();
      $series = array();

      foreach ($this->metric_series_definitions() as $key => $def) {
         $series[$key] = array(
            'key'    => $key,
            'label'  => $def['label'],
            'tone'   => $def['tone'],
            'values' => array(),
         );
      }

      foreach ($rows as $row) {
         $time = strtotime((string) ($row['snapshot_date'] ?? ''));
         $labels[] = $time ? date('d.m. H:i', $time) : '';

         foreach ($series as $key => $def) {
            $series[$key]['values'][] = (int) ($row[$key] ?? 0);
         }
      }

      return array(
         'labels' => $labels,
         'rows'   => $rows,
         'series' => array_values($series),
      );
   }

   private function history_values($history, $key, $current = 0) {
      $values = array();

      foreach (($history['rows'] ?? array()) as $row) {
         $values[] = (int) ($row[$key] ?? 0);
      }

      if (!$values) {
         $values[] = (int) $current;
      }

      if (count($values) === 1) {
         $values[] = $values[0];
      }

      return $values;
   }

   private function spark_values($history, $key, $current = 0) {
      return implode(',', $this->history_values($history, $key, $current));
   }

   private function trend_text($history, $key, $suffix = '') {
      $values = $this->history_values($history, $key, 0);

      if (count($values) < 2) {
         return '1 Messpunkt';
      }

      $first = (int) reset($values);
      $last  = (int) end($values);
      $delta = $last - $first;

      if ($delta === 0) {
         return 'unveraendert';
      }

      return ($delta > 0 ? '+' : '') . $this->fmt($delta) . $suffix . ' im Verlauf';
   }

   private function fmt_ms($value) {
      $value = max(0, (int) $value);
      return number_format($value / 1000, 3, ',', '.') . ' Sec';
   }

   private function fmt_ms_precision($value, $precision = 3, $minSeconds = 0) {
      $value = max(0, (float) $value);
      $precision = max(0, min(6, (int) $precision));
      $seconds = $value / 1000;

      if ((float) $minSeconds > 0 && $seconds < (float) $minSeconds) {
         $seconds = (float) $minSeconds;
      }

      return number_format($seconds, $precision, ',', '.') . ' Sec';
   }

   private function fmt_memory_kb($value) {
      $value = max(0, (int) $value);

      if ($value >= 1024) {
         return number_format($value / 1024, 1, ',', '.') . ' MB';
      }

      return $this->fmt($value) . ' KB';
   }

   private function fmt_memory_delta_kb($value) {
      $value = max(0, (int) $value);
      return '+' . $this->fmt_memory_kb($value);
   }

   private function performance_dd_info($dd) {
      try {
         $db = dbx()->get_system_obj('dbxDB');
         $table = (string) $db->get_dd_table($dd);
         $server = (string) $db->get_dd_server($dd);

         if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            return array('', '');
         }

         return array($server, $table);
      } catch (\Throwable $e) {
         return array('', '');
      }
   }

   private function performance_table_count(string $dd): int {
      try {
         $db = dbx()->get_system_obj('dbxDB');
         $count = $db->count($dd, '');
         return is_numeric($count) ? max(0, (int) $count) : 0;
      } catch (\Throwable $e) {
         return 0;
      }
   }

   private function performance_now_record_base(): array {
      $uid = (int) dbx()->user();
      $now = date('Y-m-d H:i:s');

      return array(
         'create_date' => $now,
         'create_uid'  => $uid,
         'update_date' => $now,
         'update_uid'  => $uid,
         'owner'       => $uid,
      );
   }

   private function optimize_performance_db(): array {
      $result = array('ok' => true, 'messages' => array());
      $db = dbx()->get_system_obj('dbxDB');
      $groups = array();

      foreach (array(self::PERF_REQUEST_DD, self::PERF_TIMER_DD) as $dd) {
         list($server, $table) = $this->performance_dd_info($dd);
         if ($server === '' || $table === '') {
            continue;
         }
         $groups[$server][] = $table;
      }

      foreach ($groups as $server => $tables) {
         $tables = array_values(array_unique($tables));
         $type = strtolower((string) $db->get_db_type($server));

         if ($type === 'mysql') {
            $quoted = array();
            foreach ($tables as $table) {
               $quoted[] = '`' . str_replace('`', '``', $table) . '`';
            }
            $ok = $db->exec($server, 'OPTIMIZE TABLE ' . implode(', ', $quoted));
            $result['ok'] = $result['ok'] && (bool) $ok;
            $result['messages'][] = $ok ? 'MySQL OPTIMIZE TABLE ausgefuehrt.' : 'MySQL OPTIMIZE TABLE fehlgeschlagen.';
            continue;
         }

         if ($type === 'sqlite') {
            $vacuum = $db->exec($server, 'VACUUM');
            $analyze = $db->exec($server, 'ANALYZE');
            $result['ok'] = $result['ok'] && (bool) $vacuum;
            $result['messages'][] = $vacuum ? 'SQLite VACUUM ausgefuehrt.' : 'SQLite VACUUM fehlgeschlagen.';
            if ($analyze) {
               $result['messages'][] = 'SQLite ANALYZE ausgefuehrt.';
            }
            continue;
         }

         $result['messages'][] = 'Keine Optimierung fuer DB-Typ ' . $type . ' definiert.';
      }

      return $result;
   }

   private function compress_performance_db(): array {
      $stats = array(
         'request_before' => $this->performance_table_count(self::PERF_REQUEST_DD),
         'timer_before'   => $this->performance_table_count(self::PERF_TIMER_DD),
         'request_after'  => 0,
         'timer_after'    => 0,
         'inserted'       => 0,
         'ok'             => false,
         'messages'       => array(),
      );

      if (!$this->ensure_performance_tables()) {
         $stats['messages'][] = 'Performance-Tabellen konnten nicht vorbereitet werden.';
         return $stats;
      }

      list($requestServer, $requestTable) = $this->performance_dd_info(self::PERF_REQUEST_DD);
      list($timerServer, $timerTable) = $this->performance_dd_info(self::PERF_TIMER_DD);
      if ($requestServer === '' || $requestTable === '' || $timerServer === '' || $timerTable === '') {
         $stats['messages'][] = 'Performance-Tabellen konnten nicht ermittelt werden.';
         return $stats;
      }

      $db = dbx()->get_system_obj('dbxDB');
      $requestRows = $db->select_query($requestServer, "SELECT COUNT(*) AS row_count, AVG(total_time_ms) AS total_time_ms, AVG(total_memory_kb) AS total_memory_kb, AVG(peak_memory_mb) AS peak_memory_mb, AVG(timer_count) AS timer_count FROM $requestTable");
      $timerRows = $db->select_query($timerServer, "SELECT section, MAX(info) AS info, MIN(sort_order) AS sort_order, COUNT(*) AS row_count, AVG(time_ms) AS time_ms, AVG(memory_kb) AS memory_kb, AVG(start_memory_kb) AS start_memory_kb, AVG(end_memory_kb) AS end_memory_kb FROM $timerTable WHERE section <> '' AND section <> 'system' GROUP BY section ORDER BY MIN(sort_order) ASC, section ASC");

      $db->empty(self::PERF_TIMER_DD);
      $db->empty(self::PERF_REQUEST_DD);

      $base = $this->performance_now_record_base();
      $requestId = 0;
      $requestDate = date('Y-m-d H:i:s');
      $requestCount = is_array($requestRows) ? (int) ($requestRows[0]['row_count'] ?? 0) : 0;

      if ($requestCount > 0) {
         $request = array_merge($base, array(
            'request_date'    => $requestDate,
            'uid'             => (int) dbx()->user(),
            'session_id'      => 'compressed',
            'modul'           => 'compressed',
            'run1'            => 'performance',
            'run2'            => 'compress',
            'ajax'            => 0,
            'sync'            => 0,
            'method'          => 'COMPRESS',
            'uri'             => 'Performance DB komprimiert aus ' . $requestCount . ' Request-Datensaetzen',
            'total_time_ms'   => (int) round((float) ($requestRows[0]['total_time_ms'] ?? 0)),
            'total_memory_kb' => (int) round((float) ($requestRows[0]['total_memory_kb'] ?? 0)),
            'peak_memory_mb'  => (int) round((float) ($requestRows[0]['peak_memory_mb'] ?? 0)),
            'timer_count'     => (int) round((float) ($requestRows[0]['timer_count'] ?? 0)),
            'sample_rate'     => max(1, $requestCount),
         ));
         if ($db->insert(self::PERF_REQUEST_DD, $request, 0, 1, 1, 0) === 1) {
            $requestId = $db->get_insert_id();
            $stats['inserted']++;
         }
      }

      if (is_array($timerRows)) {
         $sort = 0;
         foreach ($timerRows as $row) {
            $section = trim((string) ($row['section'] ?? ''));
            if ($section === '') {
               continue;
            }

            $count = max(1, (int) ($row['row_count'] ?? 0));
            $timer = array_merge($base, array(
               'request_id'      => $requestId,
               'request_date'    => $requestDate,
               'sort_order'      => $sort,
               'section'         => substr($section, 0, 80),
               'info'            => substr(trim((string) ($row['info'] ?? '')) . ' | komprimiert aus ' . $count . ' Messungen', 0, 160),
               'time_ms'         => (int) round((float) ($row['time_ms'] ?? 0)),
               'memory_kb'       => (int) round((float) ($row['memory_kb'] ?? 0)),
               'start_memory_kb' => (int) round((float) ($row['start_memory_kb'] ?? 0)),
               'end_memory_kb'   => (int) round((float) ($row['end_memory_kb'] ?? 0)),
            ));
            if ($db->insert(self::PERF_TIMER_DD, $timer, 0, 1, 1, 0) === 1) {
               $stats['inserted']++;
               $sort++;
            }
         }
      }

      $optimize = $this->optimize_performance_db();
      $stats['messages'] = array_merge($stats['messages'], $optimize['messages']);
      $stats['request_after'] = $this->performance_table_count(self::PERF_REQUEST_DD);
      $stats['timer_after'] = $this->performance_table_count(self::PERF_TIMER_DD);
      $stats['ok'] = (bool) ($optimize['ok'] ?? false);

      return $stats;
   }

   private function clear_performance_db(): array {
      $stats = array(
         'request_before' => $this->performance_table_count(self::PERF_REQUEST_DD),
         'timer_before'   => $this->performance_table_count(self::PERF_TIMER_DD),
         'request_after'  => 0,
         'timer_after'    => 0,
         'inserted'       => 0,
         'ok'             => false,
         'messages'       => array(),
      );

      if (!$this->ensure_performance_tables()) {
         $stats['messages'][] = 'Performance-Tabellen konnten nicht vorbereitet werden.';
         return $stats;
      }

      $db = dbx()->get_system_obj('dbxDB');
      $db->empty(self::PERF_TIMER_DD);
      $db->empty(self::PERF_REQUEST_DD);

      $optimize = $this->optimize_performance_db();
      $stats['messages'] = array_merge($stats['messages'], $optimize['messages']);
      $stats['request_after'] = $this->performance_table_count(self::PERF_REQUEST_DD);
      $stats['timer_after'] = $this->performance_table_count(self::PERF_TIMER_DD);
      $stats['ok'] = (bool) ($optimize['ok'] ?? false);

      return $stats;
   }

   private function render_performance_process(string $title, array $stats): string {
      $tpl = dbx()->get_system_obj('dbxTPL');
      $ok = (bool) ($stats['ok'] ?? false);
      $messages = array();
      foreach (($stats['messages'] ?? array()) as $message) {
         if (trim((string) $message) !== '') {
            $messages[] = dbx()->esc($message);
         }
      }
      $message = ($ok ? 'Prozess abgeschlossen.' : 'Prozess mit Fehler beendet.')
         . ' Request: ' . $this->fmt($stats['request_before'] ?? 0) . ' -> ' . $this->fmt($stats['request_after'] ?? 0)
         . ', Timer: ' . $this->fmt($stats['timer_before'] ?? 0) . ' -> ' . $this->fmt($stats['timer_after'] ?? 0)
         . ', neu geschrieben: ' . $this->fmt($stats['inserted'] ?? 0)
         . (count($messages) ? ' | ' . implode(' | ', $messages) : '');

      return $tpl->get_tpl('dbxAdmin|schema-process', array(
         'target_id'        => 'dbx_process_performance_' . substr(md5($title), 0, 10),
         'title'            => dbx()->esc($title),
         'status_key'       => $ok ? 'finished' : 'error',
         'status_label'     => $ok ? 'Fertig' : 'Fehler',
         'status_class'     => $ok ? 'bg-success' : 'bg-danger',
         'status_icon'      => $ok ? 'bi bi-check-circle' : 'bi bi-exclamation-triangle',
         'message'          => $message,
         'percent'          => $ok ? 100 : 0,
         'step_percent'     => $ok ? 100 : 0,
         'bar_class'        => $ok ? 'bg-success' : 'bg-danger',
         'task_label'       => 'Performance DB',
         'step_label'       => 'Optimierung',
         'process_label'    => 'Performance Wartung',
         'updated_at'       => dbx()->esc(date('d.m.Y H:i:s')),
         'next_url'         => '',
         'pause_url'        => '',
         'resume_url'       => '',
         'continue_url'     => '',
         'cancel_url'       => '',
         'restart_url'      => '',
         'autostart'        => 0,
         'interval'         => 800,
         'pause_visible'    => '_none',
         'resume_visible'   => '_none',
         'continue_visible' => '_none',
         'restart_visible'  => '_none',
         'cancel_visible'   => '_none',
         'back_url'         => '?dbx_modul=dbxAdmin',
      ));
   }

   private function run_performance_maintenance_process(string $mode): string {
      dbx()->set_system_var('dbx_performance_timer_skip', 1);

      if ($mode === 'clear') {
         return $this->render_performance_process('Performance DB leeren und optimieren', $this->clear_performance_db());
      }

      return $this->render_performance_process('Performance DB komprimieren und optimieren', $this->compress_performance_db());
   }

   private function performance_openwin_action(string $href, string $label, string $icon, string $class = 'btn-outline-secondary'): string {
      return '<a class="btn btn-sm ' . dbx()->esc($class) . ' dbx-win" href="' . dbx()->esc($href) . '" data-url="' . dbx()->esc($href) . '" data-title="' . dbx()->esc($label) . '" data-width="760" data-height="520" role="button">'
         . '<i class="bi ' . dbx()->esc($icon) . '"></i> ' . dbx()->esc($label)
         . '</a>';
   }

   private function performance_maintenance_actions(): string {
      return $this->performance_openwin_action('?dbx_modul=dbxAdmin&dbx_run1=run&dbx_run2=performance_compress', 'Komprimieren', 'bi-file-zip')
         . $this->performance_openwin_action('?dbx_modul=dbxAdmin&dbx_run1=run&dbx_run2=performance_clear', 'Performance DB leeren', 'bi-trash3', 'btn-outline-danger');
   }

   private function performance_request_average() {
      if ($this->performanceRequestAverage !== null) {
         return $this->performanceRequestAverage;
      }

      $this->performanceRequestAverage = array();

      if (!$this->ensure_performance_tables()) {
         return $this->performanceRequestAverage;
      }

      list($server, $table) = $this->performance_dd_info(self::PERF_REQUEST_DD);
      if ($server === '' || $table === '') {
         return $this->performanceRequestAverage;
      }

      try {
         $db = dbx()->get_system_obj('dbxDB');
         $rows = $db->select_query($server, "SELECT COUNT(*) AS row_count, AVG(total_time_ms) AS avg_time_ms, AVG(total_memory_kb) AS avg_memory_kb, AVG(timer_count) AS avg_timer_count FROM $table");
         $row = is_array($rows) ? ($rows[0] ?? array()) : array();

         if ((int) ($row['row_count'] ?? 0) <= 0) {
            return $this->performanceRequestAverage;
         }

         $this->performanceRequestAverage = array(
            'count'           => (int) ($row['row_count'] ?? 0),
            'avg_time_ms'     => (int) round((float) ($row['avg_time_ms'] ?? 0)),
            'avg_memory_kb'   => (int) round((float) ($row['avg_memory_kb'] ?? 0)),
            'avg_timer_count' => (int) round((float) ($row['avg_timer_count'] ?? 0)),
         );
      } catch (\Throwable $e) {
         $this->performanceRequestAverage = array();
      }

      return $this->performanceRequestAverage;
   }

   private function performance_timer_averages() {
      if ($this->performanceTimerAverages !== null) {
         return $this->performanceTimerAverages;
      }

      $this->performanceTimerAverages = array();

      if (!$this->ensure_performance_tables()) {
         return $this->performanceTimerAverages;
      }

      list($server, $table) = $this->performance_dd_info(self::PERF_TIMER_DD);
      if ($server === '' || $table === '') {
         return $this->performanceTimerAverages;
      }

      try {
         $db = dbx()->get_system_obj('dbxDB');
         $sql = "SELECT section, MAX(info) AS info, MIN(sort_order) AS sort_order, COUNT(*) AS row_count, AVG(time_ms) AS avg_time_ms, AVG(memory_kb) AS avg_memory_kb
                 FROM $table
                 WHERE section <> 'system'
                 GROUP BY section
                 ORDER BY MIN(sort_order) ASC, section ASC";
         $rows = $db->select_query($server, $sql);
         $this->performanceTimerAverages = is_array($rows) ? $rows : array();
      } catch (\Throwable $e) {
         $this->performanceTimerAverages = array();
      }

      return $this->performanceTimerAverages;
   }

   private function performance_timer_average($section) {
      $section = (string) $section;

      foreach ($this->performance_timer_averages() as $row) {
         if ((string) ($row['section'] ?? '') === $section) {
            return array(
               'count'         => (int) ($row['row_count'] ?? 0),
               'avg_time_ms'   => (int) round((float) ($row['avg_time_ms'] ?? 0)),
               'avg_memory_kb' => (int) round((float) ($row['avg_memory_kb'] ?? 0)),
            );
         }
      }

      return array();
   }

   private function performance_module_averages() {
      if ($this->performanceModuleAverages !== null) {
         return $this->performanceModuleAverages;
      }

      $this->performanceModuleAverages = array();

      if (!$this->ensure_performance_tables()) {
         return $this->performanceModuleAverages;
      }

      list($requestServer, $requestTable) = $this->performance_dd_info(self::PERF_REQUEST_DD);
      list($timerServer, $timerTable) = $this->performance_dd_info(self::PERF_TIMER_DD);
      if ($requestServer === '' || $requestTable === '' || $timerServer !== $requestServer || $timerTable === '') {
         return $this->performanceModuleAverages;
      }

      try {
         $db = dbx()->get_system_obj('dbxDB');
         $sql = "SELECT r.modul AS modul,
                        COUNT(*) AS row_count,
                        AVG(r.total_time_ms) AS avg_total_ms,
                        AVG(COALESCE(d.db_time_ms, 0)) AS avg_db_ms
                   FROM $requestTable r
                   LEFT JOIN (
                        SELECT request_id, SUM(time_ms) AS db_time_ms
                          FROM $timerTable
                         WHERE section = 'db-total'
                         GROUP BY request_id
                   ) d ON d.request_id = r.id
                  WHERE TRIM(COALESCE(r.modul, '')) <> ''
                  GROUP BY r.modul
                  ORDER BY AVG(r.total_time_ms) DESC, r.modul ASC
                  LIMIT 16";
         $rows = $db->select_query($requestServer, $sql);
         $this->performanceModuleAverages = is_array($rows) ? $rows : array();
      } catch (\Throwable $e) {
         $this->performanceModuleAverages = array();
      }

      return $this->performanceModuleAverages;
   }

   private function hero_performance($metrics) {
      $request = $this->performance_request_average();
      $dbTotal = $this->performance_timer_average('db-total');

      $requestMs = $request ? (int) ($request['avg_time_ms'] ?? 0) : (int) ($metrics['request_runtime_ms'] ?? 0);
      $dbMs = $dbTotal ? (int) ($dbTotal['avg_time_ms'] ?? 0) : 0;
      $phpMs = max(0, $requestMs - $dbMs);
      $dbShare = $requestMs > 0 ? min(999, (int) round(($dbMs / $requestMs) * 100)) : 0;
      $phpShare = $requestMs > 0 ? min(999, (int) round(($phpMs / $requestMs) * 100)) : 0;

      return array(
         'request_avg'   => $this->fmt_ms($requestMs),
         'request_raw'   => $requestMs,
         'request_count' => $this->fmt($request['count'] ?? 0),
         'php_avg'       => $this->fmt_ms($phpMs),
         'php_raw'       => $phpMs,
         'php_share'     => $this->fmt($phpShare),
         'db_avg'        => $this->fmt_ms($dbMs),
         'db_raw'        => $dbMs,
         'db_count'      => $this->fmt($dbTotal['count'] ?? 0),
         'db_share'      => $this->fmt($dbShare),
      );
   }

   private function hero_performance_gauge($label, $icon, $tone, $raw, $value, $subtitle) {
      $tpl = dbx()->get_system_obj('dbxTPL');

      return $tpl->get_tpl('dbxAdmin|admin-dashboard-hero-gauge', array(
         'label'    => $label,
         'icon'     => $icon,
         'tone'     => $tone,
         'raw'      => max(0, (int) $raw),
         'max'      => 6000,
         'value'    => $value,
         'subtitle' => $subtitle,
      ));
   }

   private function hero_performance_gauges($heroPerformance) {
      $items = '';
      $items .= $this->hero_performance_gauge(
         'Request gesamt',
         'bi-stopwatch',
         'request',
         $heroPerformance['request_raw'] ?? 0,
         $heroPerformance['request_avg'] ?? '0,000 Sec',
         'Durchschnitt aus ' . ($heroPerformance['request_count'] ?? '0') . ' Requests'
      );
      $items .= $this->hero_performance_gauge(
         'PHP gesamt',
         'bi-cpu',
         'php',
         $heroPerformance['php_raw'] ?? 0,
         $heroPerformance['php_avg'] ?? '0,000 Sec',
         'Durchschnitt ohne DB-Anteil'
      );
      $items .= $this->hero_performance_gauge(
         'DB gesamt',
         'bi-database-check',
         'db',
         $heroPerformance['db_raw'] ?? 0,
         $heroPerformance['db_avg'] ?? '0,000 Sec',
         'Durchschnitt aus DB-Timern'
      );

      return $items;
   }

   private function hero_summary_rows(array $items): string {
      $html = '';
      $max = 1;
      foreach ($items as $item) {
         $max = max($max, (int) ($item['count'] ?? 0));
      }

      foreach ($items as $item) {
         $count = (int) ($item['count'] ?? 0);
         $pct = max(3, min(100, (int) round(($count / $max) * 100)));
         $tone = dbx()->esc((string) ($item['tone'] ?? 'blue'));
         $html .= '<div class="dbx-admin-dashboard-hero-summary-row dbx-admin-dashboard-hero-summary-row-' . $tone . '">'
            . '<span>' . dbx()->esc((string) ($item['label'] ?? '')) . '</span>'
            . '<div><em style="width:' . $pct . '%"></em></div>'
            . '<strong>' . dbx()->esc($this->fmt($count)) . '</strong>'
            . '</div>';
      }

      return $html;
   }

   private function hero_summary_card(string $title, string $subtitle, string $icon, string $tone, int $total, array $rows): string {
      $tpl = dbx()->get_system_obj('dbxTPL');

      return $tpl->get_tpl('dbxAdmin|admin-dashboard-hero-summary', array(
         'title'    => $title,
         'subtitle' => $subtitle,
         'icon'     => $icon,
         'tone'     => $tone,
         'total'    => $this->fmt($total),
         'rows'     => $this->hero_summary_rows($rows),
      ));
   }

   private function hero_status_summaries(): string {
      $contactDd = 'dbxContact|contactRequest';
      $contactTotal = $this->safe_count($contactDd);
      $contactRows = array(
         array('label' => 'Offen', 'count' => $this->safe_count($contactDd, array('status' => 'open')), 'tone' => 'blue'),
         array('label' => 'In Arbeit', 'count' => $this->safe_count($contactDd, array('status' => 'in_progress')), 'tone' => 'cyan'),
         array('label' => 'Rueckfrage', 'count' => $this->safe_count($contactDd, array('status' => 'waiting_customer')), 'tone' => 'amber'),
         array('label' => 'Beantwortet', 'count' => $this->safe_count($contactDd, array('status' => 'answered')), 'tone' => 'green'),
         array('label' => 'Geschlossen', 'count' => $this->safe_count($contactDd, array('status' => 'closed')), 'tone' => 'slate'),
      );

      $sysmsgDd = 'dbxSysMsg';
      $sysmsgTotal = $this->safe_count($sysmsgDd);
      $sysmsgRows = array(
         array('label' => 'Info', 'count' => $this->safe_count($sysmsgDd, "LOWER(status) = 'info'"), 'tone' => 'blue'),
         array('label' => 'Warning', 'count' => $this->safe_count($sysmsgDd, "LOWER(status) = 'warning'"), 'tone' => 'amber'),
         array('label' => 'Error', 'count' => $this->safe_count($sysmsgDd, "LOWER(status) = 'error'"), 'tone' => 'red'),
         array('label' => 'Security', 'count' => $this->safe_count($sysmsgDd, "LOWER(status) = 'security'"), 'tone' => 'purple'),
      );

      return $this->hero_summary_card('Kontakte', 'Alle Anfragen nach Status', 'bi-life-preserver', 'contact', $contactTotal, $contactRows)
         . $this->hero_summary_card('SysMsg', 'Meldungen nach Status', 'bi-bell', 'sysmsg', $sysmsgTotal, $sysmsgRows);
   }

   private function performance_label($section, $info = '') {
      $section = trim((string) $section);
      $info = trim((string) $info);

      if ($section === 'db-total') {
         return 'DB Gesamt';
      }

      if ($section === 'js-total') {
         return 'JS Gesamt';
      }

      if ($section === 'system') {
         return 'PHP/System Gesamt';
      }

      if ($section === 'db-select') {
         return 'DB Select gesamt';
      }

      if ($section === 'db-save') {
         return 'DB Save gesamt';
      }

      if (substr($section, 0, 10) === 'db-select-') {
         return 'DB Select';
      }

      if (substr($section, 0, 8) === 'db-save-') {
         return 'DB Save';
      }

      $known = array(
         'system-load'  => 'System Load',
         'session-load' => 'Session Load',
         'system-check' => 'System Check',
         'modul-run'    => 'Modul Run',
         'page-load'    => 'Page Load',
         'interpreter'  => 'Interpreter',
         'db-select'    => 'DB Select',
         'db-save'      => 'DB Save',
      );

      if (isset($known[$section])) {
         return $known[$section];
      }

      if (substr($section, 0, 4) === 'run-') {
         $modul = substr($section, 4);
         return $modul !== '' ? $modul : 'Modul';
      }

      $label = ucwords(str_replace(array('-', '_'), ' ', $section));
      return $label !== '' ? $label : ($info !== '' ? $info : 'Timer');
   }

   private function performance_is_db_section($section) {
      $section = (string) $section;
      return $section === 'db-total'
         || $section === 'db-select'
         || $section === 'db-save'
         || substr($section, 0, 10) === 'db-select-'
         || substr($section, 0, 8) === 'db-save-';
   }

   private function performance_dd_detail($section, $info = '') {
      $section = trim((string) $section);
      $info = trim((string) $info);

      if (substr($section, 0, 10) === 'db-select-') {
         $dd = substr($info, 0, 7) === 'select ' ? trim(substr($info, 7)) : trim(substr($section, 10));
         return $dd !== '' ? $dd : 'DD';
      }

      if (substr($section, 0, 8) === 'db-save-') {
         $dd = substr($info, 0, 5) === 'save ' ? trim(substr($info, 5)) : trim(substr($section, 8));
         return $dd !== '' ? $dd : 'DD';
      }

      return null;
   }

   private function performance_db_sort($row) {
      $section = (string) ($row['section'] ?? '');

      if ($section === 'db-total') {
         return 0;
      }

      if ($section === 'db-select') {
         return 10;
      }

      if ($section === 'db-save') {
         return 20;
      }

      if (substr($section, 0, 10) === 'db-select-') {
         return 100;
      }

      if (substr($section, 0, 8) === 'db-save-') {
         return 200;
      }

      return 900;
   }

   private function performance_tone($index) {
      $tones = array('teal', 'green', 'navy', 'amber', 'cyan', 'red', 'purple', 'slate');
      return $tones[$index % count($tones)];
   }

   private function performance_rows($metrics, $mode = 'request') {
      $rows = array();

      if ($mode === 'module') {
         $index = 1;
         foreach ($this->performance_module_averages() as $module) {
            $modul = trim((string) ($module['modul'] ?? ''));
            $count = (int) ($module['row_count'] ?? 0);
            if ($modul === '' || $count <= 0) {
               continue;
            }

            $totalMs = (int) round((float) ($module['avg_total_ms'] ?? 0));
            $dbMs = (int) round((float) ($module['avg_db_ms'] ?? 0));
            $phpMs = max(0, $totalMs - $dbMs);
            $subtitle = 'Modul ' . $modul . ' · ' . $this->fmt($count) . ' Requests';

            $rows[] = array(
               'section' => 'module-' . $modul . '-php',
               'label' => $modul . ' · PHP gesamt',
               'icon' => 'bi-cpu',
               'value_ms' => $phpMs,
               'detail' => 'PHP gesamt',
               'precision' => 3,
               'min_display_sec' => 0,
               'subtitle' => $subtitle,
               'tone' => $this->performance_tone($index),
            );

            $rows[] = array(
               'section' => 'module-' . $modul . '-db',
               'label' => $modul . ' · DB gesamt',
               'icon' => 'bi-database-check',
               'value_ms' => $dbMs,
               'detail' => 'DB gesamt',
               'precision' => 4,
               'min_display_sec' => 0.0001,
               'subtitle' => $subtitle,
               'tone' => $this->performance_tone($index + 1),
            );
            $index += 2;
         }

         return array('rows' => $rows);
      }

      $segments = $this->performance_timer_averages();
      $index = 1;
      foreach ($segments as $segment) {
         $section = (string) ($segment['section'] ?? '');
         $count = (int) ($segment['row_count'] ?? 0);
         if ($section === '' || $count <= 0) {
            continue;
         }

         if ($mode === 'db' && $section === 'db-total') {
            continue;
         }

         $isDb = $this->performance_is_db_section($section);
         if (($mode === 'db') !== $isDb) {
            continue;
         }

         $rows[] = array(
            'section' => $section,
            'label' => $this->performance_label($section, $segment['info'] ?? ''),
            'icon' => 'bi-stopwatch',
            'value_ms' => (int) round((float) ($segment['avg_time_ms'] ?? 0)),
            'detail' => $this->performance_dd_detail($section, $segment['info'] ?? ''),
            'precision' => $isDb ? 4 : 3,
            'min_display_sec' => $isDb ? 0.0001 : 0,
            'subtitle' => 'Durchschnitt aus ' . $this->fmt($count) . ' Messungen, Memory ' . $this->fmt_memory_delta_kb($segment['avg_memory_kb'] ?? 0),
            'tone' => $this->performance_tone($index),
         );
         $index++;
      }

      if ($mode === 'db') {
         usort($rows, function ($a, $b) {
            $prio = $this->performance_db_sort($a) <=> $this->performance_db_sort($b);
            if ($prio !== 0) {
               return $prio;
            }

            return strcmp((string) ($a['detail'] ?? $a['label'] ?? ''), (string) ($b['detail'] ?? $b['label'] ?? ''));
         });
      }

      return array('rows' => $rows);
   }

   private function performance_module_image_url(string $modul): string {
      $modul = trim($modul);
      $safeModul = preg_match('/^[A-Za-z0-9_\\-]+$/', $modul) ? $modul : 'dbx';
      $baseDir = rtrim((string) dbx()->get_base_dir(), '/\\') . DIRECTORY_SEPARATOR;
      $rel = 'dbx/modules/' . $safeModul . '/tpl/img/' . $safeModul . '.png';
      $path = $baseDir . str_replace('/', DIRECTORY_SEPARATOR, $rel);

      if (!is_file($path)) {
         $rel = 'dbx/modules/dbx/tpl/img/dbx.png';
      }

      return rtrim((string) dbx()->get_base_url(), '/\\') . '/' . $rel;
   }

   private function performance_metric($row, int $max): string {
      $tpl = dbx()->get_system_obj('dbxTPL');
      $value = (int) ($row['value_ms'] ?? 0);

      return $tpl->get_tpl('dbxAdmin|admin-dashboard-performance-metric', array(
         'tone'   => (string) ($row['tone'] ?? 'teal'),
         'icon'   => (string) ($row['icon'] ?? 'bi-speedometer2'),
         'label'  => (string) ($row['label'] ?? 'Messung'),
         'raw'    => $value,
         'max'    => max(1, $max),
         'value'  => $this->fmt_ms_precision($value, $row['precision'] ?? 3, $row['min_display_sec'] ?? 0),
         'detail' => (string) ($row['detail'] ?? ''),
      ));
   }

   private function performance_module_list(): string {
      $tpl = dbx()->get_system_obj('dbxTPL');
      $max = 6000;
      $html = '';
      $index = 1;

      foreach ($this->performance_module_averages() as $module) {
         $modul = trim((string) ($module['modul'] ?? ''));
         $count = (int) ($module['row_count'] ?? 0);
         if ($modul === '' || $count <= 0) {
            continue;
         }

         $totalMs = (int) round((float) ($module['avg_total_ms'] ?? 0));
         $dbMs = (int) round((float) ($module['avg_db_ms'] ?? 0));
         $phpMs = max(0, $totalMs - $dbMs);

         $metrics = '';
         $metrics .= $this->performance_metric(array(
            'label' => 'PHP gesamt',
            'icon' => 'bi-cpu',
            'value_ms' => $phpMs,
            'detail' => 'PHP gesamt',
            'precision' => 3,
            'min_display_sec' => 0,
            'tone' => $this->performance_tone($index),
         ), $max);
         $metrics .= $this->performance_metric(array(
            'label' => 'DB gesamt',
            'icon' => 'bi-database-check',
            'value_ms' => $dbMs,
            'detail' => 'DB gesamt',
            'precision' => 4,
            'min_display_sec' => 0.0001,
            'tone' => $this->performance_tone($index + 1),
         ), $max);

         $html .= $tpl->get_tpl('dbxAdmin|admin-dashboard-performance-module', array(
            'module_img'    => $this->performance_module_image_url($modul),
            'module_name'   => $modul,
            'request_count' => $this->fmt($count),
            'metrics'       => $metrics,
         ));

         $index += 2;
      }

      return $html;
   }

   private function speedometer($row, $max) {
      $tpl = dbx()->get_system_obj('dbxTPL');
      $value = (int) ($row['value_ms'] ?? 0);
      $label = (string) ($row['label'] ?? 'Timer');
      $icon = (string) ($row['icon'] ?? 'bi-speedometer2');
      $tone = (string) ($row['tone'] ?? 'teal');

      return $tpl->get_tpl('dbxAdmin|admin-dashboard-speedometer', array(
         'bar'      => $this->card_bar($label, $icon),
         'tone'     => $tone,
         'raw'      => $value,
         'max'      => max(1, (int) $max),
         'value'    => $this->fmt_ms_precision($value, $row['precision'] ?? 3, $row['min_display_sec'] ?? 0),
         'detail'   => $row['detail'] ?? '',
         'subtitle' => $row['subtitle'] ?? '',
      ));
   }

   private function speedometer_panel($title, $icon, $subtitle, $rows, $panelClass = '', $panelTarget = '', $controls = '', $barActions = '') {
      $tpl = dbx()->get_system_obj('dbxTPL');
      $max = 6000;
      $items = '';
      $panelTarget = trim((string) $panelTarget);
      $actions = $barActions . ($panelTarget !== '' ? $this->collapse_action($panelTarget, 'Zuklappen', true) : '');

      foreach ($rows as $row) {
         $items .= $this->speedometer($row, $max);
      }

      return $tpl->get_tpl('dbxAdmin|admin-dashboard-performance', array(
         'panel_class'     => $panelClass,
         'panel_target'    => $panelTarget,
         'performance_bar' => $this->card_bar($title, $icon, $subtitle, $actions),
         'performance_controls' => $controls,
         'performance_items' => $items,
      ));
   }

   private function performance_panel($metrics) {
      $tpl = dbx()->get_system_obj('dbxTPL');
      $panelTarget = 'request-performance';
      $actions = $this->performance_maintenance_actions()
         . $this->collapse_action($panelTarget, 'Zuklappen', true)
         . $this->help_action('dashboard');

      return $tpl->get_tpl('dbxAdmin|admin-dashboard-performance', array(
         'panel_class' => '',
         'panel_target' => $panelTarget,
         'performance_bar' => $this->card_bar('Performance pro Modul', 'bi-speedometer2', 'Durchschnittswerte je Modul: PHP gesamt und DB gesamt', $actions),
         'performance_controls' => $this->performance_level_control(),
         'performance_items' => $this->performance_module_list(),
      ));
   }

   private function normalize_sys_msg_level($level): string {
      $level = strtolower(trim((string) $level));
      if ($level === 'warn') {
         $level = 'warning';
      }

      return in_array($level, array('error', 'warning', 'all'), true) ? $level : 'all';
   }

   private function sys_msg_level_config(): string {
      return $this->normalize_sys_msg_level(dbx()->get_config('dbx', 'sys_msg_level', 'all'));
   }

   private function sys_msg_level_options(string $current): string {
      $options = array(
         'error'   => 'Nur Error',
         'warning' => 'Error + Warning',
         'all'     => 'Alles',
      );
      $html = '';

      foreach ($options as $value => $label) {
         $selected = $value === $current ? ' selected' : '';
         $html .= '<option value="' . dbx()->esc($value) . '"' . $selected . '>' . dbx()->esc($label) . '</option>';
      }

      return $html;
   }

   private function set_sys_msg_level_config(string $level): bool {
      $config = dbx()->get_config('dbx');
      if (!is_array($config)) {
         $config = array();
      }

      $config['sys_msg_level'] = $this->normalize_sys_msg_level($level);
      return (int) dbx()->set_config('dbx', $config) > 0;
   }

   private function process_sys_msg_level_action(): bool {
      $run2 = dbx()->get_modul_var('dbx_run2', '', 'parameter');
      if ($run2 !== 'sysmsg_level_save') {
         return false;
      }

      $level = dbx()->get_request_var('sys_msg_level', null, 'parameter');
      if ($level === null || $level === '') {
         $level = $_POST['sys_msg_level'] ?? 'all';
      }

      return $this->set_sys_msg_level_config((string) $level);
   }

   private function sys_msg_level_control_data(): array {
      $level = $this->sys_msg_level_config();
      $states = array(
         'all' => array(
            'tone'  => 'on',
            'icon'  => 'bi-bell-fill',
            'label' => 'Systemmeldungen: Alles',
            'hint'  => 'Alle Systemmeldungen werden gespeichert.',
         ),
         'warning' => array(
            'tone'  => 'on',
            'icon'  => 'bi-exclamation-triangle-fill',
            'label' => 'Error + Warning',
            'hint'  => 'Nur Error und Warning werden gespeichert.',
         ),
         'error' => array(
            'tone'  => 'off',
            'icon'  => 'bi-exclamation-octagon-fill',
            'label' => 'Nur Error',
            'hint'  => 'Nur Fehlermeldungen werden gespeichert.',
         ),
      );
      $state = $states[$level] ?? $states['all'];

      return array(
         'sys_msg_level_save_base' => dbx()->esc('?dbx_modul=dbxAdmin&dbx_run1=run&dbx_run2=sysmsg_level_save'),
         'sys_msg_level_options'   => $this->sys_msg_level_options($level),
         'sysmsg_status_tone'      => dbx()->esc($state['tone']),
         'sysmsg_status_icon'      => dbx()->esc($state['icon']),
         'sysmsg_status_label'     => dbx()->esc($state['label']),
         'sysmsg_status_hint'      => dbx()->esc($state['hint']),
      );
   }

   private function sys_msg_level_control(): string {
      $oForm = new \dbxForm();
      $oForm->init('admin-dashboard-sysmsg-control', 'admin-dashboard-sysmsg-control');
      $oForm->set_form_help_enabled(false);
      $oForm->_action = '?dbx_modul=dbxAdmin&dbx_run1=run&dbx_run2=sysmsg_level_save';
      $oForm->_msg_info = '';

      foreach ($this->sys_msg_level_control_data() as $key => $value) {
         $oForm->add_rep($key, $value);
      }

      return $oForm->add_norep($oForm->run());
   }

   private function sysmsg_panel_body_html(): string {
      $oForm = new \dbxForm();
      $oForm->init('admin-dashboard-sysmsg-body', 'admin-dashboard-sysmsg-body');
      $oForm->set_form_help_enabled(false);
      $oForm->_action = '?dbx_modul=dbxAdmin&dbx_run1=run';
      $oForm->_msg_info = '';
      $oForm->add_rep('sysmsg_control', $this->sys_msg_level_control());

      return $oForm->add_norep($oForm->run());
   }

   private function sysmsg_panel() {
      $oForm = new \dbxForm();
      $oForm->init('admin-dashboard-sysmsg-panel', 'admin-dashboard-sysmsg-panel');
      $oForm->set_form_help_enabled(false);
      $oForm->_action = '?dbx_modul=dbxAdmin&dbx_run1=run';
      $oForm->_msg_info = '';
      $oForm->add_rep('sysmsg_body', $this->sysmsg_panel_body_html());

      return $oForm->add_norep($oForm->run());
   }

   private function session_db_enabled_config(): bool {
      return $this->dbx_config_bool('session_db', 1);
   }

   private function set_session_db_config(bool $enabled): bool {
      $config = dbx()->get_config('dbx');
      if (!is_array($config)) {
         $config = array();
      }

      $config['session_db'] = $enabled ? 1 : 0;
      return (int) dbx()->set_config('dbx', $config) > 0;
   }

   private function process_session_db_action(): bool {
      $run2 = dbx()->get_modul_var('dbx_run2', '', 'parameter');
      if ($run2 !== 'session_db_save') {
         return false;
      }

      return $this->set_session_db_config(isset($_POST['session_db']));
   }

   private function session_panel_body_data(): array {
      $enabled = $this->session_db_enabled_config();

      return array(
         'session_save_url' => dbx()->esc('?dbx_modul=dbxAdmin&dbx_run1=run&dbx_run2=session_db_save'),
         'session_enabled_checked' => $enabled ? 'checked' : '',
         'session_status_tone' => $enabled ? 'on' : 'off',
         'session_status_icon' => $enabled ? 'bi-check-circle-fill' : 'bi-pause-circle-fill',
         'session_status_label' => dbx()->esc($enabled ? 'Session-DB aktiv' : 'Session-DB inaktiv'),
         'session_status_hint' => dbx()->esc(
            $enabled
               ? 'Normale HTTP-Requests und HTML-AJAX-Requests schreiben ihre Session am Request-Ende in die DB.'
               : 'Sessions laufen nur ueber PHP-Session; die Session-Liste wird nicht fortgeschrieben.'
         ),
      );
   }

   private function session_panel_control_html(): string {
      $oForm = new \dbxForm();
      $oForm->init('admin-dashboard-session-control', 'admin-dashboard-session-control');
      $oForm->set_form_help_enabled(false);
      $oForm->_action = '?dbx_modul=dbxAdmin&dbx_run1=run';
      $oForm->_msg_info = '';

      foreach ($this->session_panel_body_data() as $key => $value) {
         $oForm->add_rep($key, $value);
      }

      return $oForm->add_norep($oForm->run());
   }

   private function session_panel_body_html(): string {
      $oForm = new \dbxForm();
      $oForm->init('admin-dashboard-session-body', 'admin-dashboard-session-body');
      $oForm->set_form_help_enabled(false);
      $oForm->_action = '?dbx_modul=dbxAdmin&dbx_run1=run';
      $oForm->_msg_info = '';
      $oForm->add_rep('session_control', $this->session_panel_control_html());

      return $oForm->add_norep($oForm->run());
   }

   private function session_panel() {
      $oForm = new \dbxForm();
      $oForm->init('admin-dashboard-session-panel', 'admin-dashboard-session-panel');
      $oForm->set_form_help_enabled(false);
      $oForm->_action = '?dbx_modul=dbxAdmin&dbx_run1=run';
      $oForm->_msg_info = '';
      $oForm->add_rep('session_body', $this->session_panel_body_html());

      return $oForm->add_norep($oForm->run());
   }

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

   private function widget($data) {
      $tpl = dbx()->get_system_obj('dbxTPL');

      $data['bar'] = $this->card_bar($data['label'] ?? '', $data['icon'] ?? 'bi-circle');
      $data['label'] = $data['label'] ?? '';
      $data['value'] = $data['value'] ?? '';
      $data['raw'] = (int) ($data['raw'] ?? 0);
      $data['note'] = $data['note'] ?? '';
      $data['icon'] = $data['icon'] ?? 'bi-circle';
      $data['tone'] = $data['tone'] ?? 'teal';
      $data['spark'] = $data['spark'] ?? '';
      $data['trend'] = $data['trend'] ?? '';

      return $tpl->get_tpl('dbxAdmin|admin-dashboard-widget', $data);
   }

   private function widgets($metrics, $history) {
      $widgets = array(
         array(
            'label' => 'Benutzer',
            'value' => $this->fmt($metrics['users']),
            'raw' => $metrics['users'],
            'note' => $this->fmt($metrics['active_users']) . ' aktiv',
            'icon' => 'bi-people',
            'tone' => 'teal',
            'spark' => $this->spark_values($history, 'users', $metrics['users']),
            'trend' => $this->trend_text($history, 'users'),
         ),
         array(
            'label' => 'Online',
            'value' => $this->fmt($metrics['online']),
            'raw' => $metrics['online'],
            'note' => $this->fmt($metrics['sessions']) . ' Sessions',
            'icon' => 'bi-broadcast-pin',
            'tone' => 'green',
            'spark' => $this->spark_values($history, 'online', $metrics['online']),
            'trend' => $this->trend_text($history, 'online'),
         ),
         array(
            'label' => 'Module',
            'value' => $this->fmt($metrics['modules']),
            'raw' => $metrics['modules'],
            'note' => $this->fmt($metrics['dd_count']) . ' DDs',
            'icon' => 'bi-grid-1x2',
            'tone' => 'navy',
            'spark' => $this->spark_values($history, 'modules', $metrics['modules']),
            'trend' => $this->trend_text($history, 'modules'),
         ),
         array(
            'label' => 'Datensaetze',
            'value' => $this->fmt($metrics['records']),
            'raw' => $metrics['records'],
            'note' => $this->fmt($metrics['tables']) . ' Tabellen',
            'icon' => 'bi-database-check',
            'tone' => 'amber',
            'spark' => $this->spark_values($history, 'records', $metrics['records']),
            'trend' => $this->trend_text($history, 'records'),
         ),
         array(
            'label' => 'Datenbanken',
            'value' => $this->fmt($metrics['databases']),
            'raw' => $metrics['databases'],
            'note' => $this->fmt($metrics['existing_dd']) . ' Quellen ok',
            'icon' => 'bi-hdd-stack',
            'tone' => 'cyan',
            'spark' => $this->spark_values($history, 'databases', $metrics['databases']),
            'trend' => $this->trend_text($history, 'databases'),
         ),
         array(
            'label' => 'Systemzustand',
            'value' => (int) $metrics['health_percent'] . '%',
            'raw' => $metrics['health_percent'],
            'note' => $this->fmt($metrics['sysmsg_risk']) . ' Warnungen/Fehler',
            'icon' => 'bi-shield-check',
            'tone' => 'red',
            'spark' => $this->spark_values($history, 'health_percent', $metrics['health_percent']),
            'trend' => $this->trend_text($history, 'health_percent', '%'),
         ),
         array(
            'label' => 'Speed',
            'value' => $this->fmt($metrics['request_runtime_ms']) . ' ms',
            'raw' => $metrics['request_runtime_ms'],
            'note' => 'PHP Request',
            'icon' => 'bi-speedometer2',
            'tone' => 'purple',
            'spark' => $this->spark_values($history, 'request_runtime_ms', $metrics['request_runtime_ms']),
            'trend' => $this->trend_text($history, 'request_runtime_ms', ' ms'),
         ),
         array(
            'label' => 'DBX Memory',
            'value' => $this->fmt($metrics['memory_peak_kb']) . ' KB',
            'raw' => $metrics['memory_peak_kb'],
            'note' => 'dbx Verbrauch',
            'icon' => 'bi-memory',
            'tone' => 'slate',
            'spark' => $this->spark_values($history, 'memory_peak_kb', $metrics['memory_peak_kb']),
            'trend' => $this->trend_text($history, 'memory_peak_kb', ' KB'),
         ),
      );

      $content = '';
      foreach ($widgets as $widget) {
         $content .= $this->widget($widget);
      }

      return $content;
   }

   private function database_rows($metrics) {
      $rows = array();
      $inventory = $metrics['inventory'] ?? array();

      usort($inventory, function ($a, $b) {
         return ((int) ($b['count'] ?? 0)) <=> ((int) ($a['count'] ?? 0));
      });

      $seen = array();
      foreach ($inventory as $row) {
         $server = (string) ($row['server'] ?? '');
         $table  = (string) ($row['table'] ?? '');
         $key    = $server . '|' . $table;

         if ($key === '|' || isset($seen[$key])) {
            continue;
         }

         $seen[$key] = 1;
         $count = (int) ($row['count'] ?? 0);
         $rows[] = array(
            'title' => dbx()->esc($row['dd'] ?? $table),
            'meta' => dbx()->esc($server . ' / ' . $table),
            'count' => $this->fmt($count),
            'percent' => $this->percent($count, max(1, (int) ($metrics['records'] ?? 0))),
            'status' => !empty($row['exist']) ? 'ok' : 'fehlt',
            'tone' => !empty($row['exist']) ? 'ok' : 'warn',
         );

         if (count($rows) >= 7) {
            break;
         }
      }

      if (!$rows) {
         $rows[] = array(
            'title' => 'Keine Tabellen',
            'meta' => 'Noch keine DD-Tabellen gefunden',
            'count' => '0',
            'percent' => 0,
            'status' => 'leer',
            'tone' => 'warn',
         );
      }

      return $rows;
   }

   private function database_report($metrics) {
      $oReport = new \dbxReport();
      $panelTarget = 'database-report';
      $oReport->init('admin-dashboard-db', 'admin-dashboard-db-report');
      $oReport->add_obj('database_bar', 'dbx|component-bar', $this->card_bar_data(
         'Datenbanken und Tabellen',
         'bi-hdd-stack',
         '',
         $this->card_action('?dbx_modul=dbxAdmin&dbx_run1=db&dbx_run2=list_db', 'DB Sync')
            . $this->collapse_action($panelTarget, 'Zuklappen', true)
      ));
      $oReport->add_rep('panel_target', dbx()->esc($panelTarget));
      $oReport->_mode = 'tpl';
      $oReport->_pages = 0;
      $oReport->_rdata = $this->database_rows($metrics);
      $oReport->_rcount = count($oReport->_rdata);
      $oReport->_rrows = 20;
      $oReport->_msg_info = '';

      return $oReport->run();
   }

   private function activity_rows($metrics) {
      $rows = array();
      $traceRows = $this->safe_select('dbxTrace', '', array('id', 'create_date', 'action', 'dd', 'record_id'), 'create_date', 'DESC', '', 5);

      foreach ($traceRows as $row) {
         $title = trim((string) ($row['action'] ?? 'Trace'));
         $dd = trim((string) ($row['dd'] ?? ''));
         $record = trim((string) ($row['record_id'] ?? ''));

         $rows[] = array(
            'icon' => 'bi-clock-history',
            'title' => dbx()->esc($title !== '' ? ucfirst($title) : 'Trace'),
            'meta' => dbx()->esc(($dd !== '' ? $dd : 'Datensatz') . ($record !== '' ? ' #' . $record : '')),
            'time' => dbx()->esc($row['create_date'] ?? ''),
            'tone' => 'trace',
         );
      }

      if (count($rows) < 5) {
         $msgRows = $this->safe_select('dbxSysMsg', '', array('id', 'create_date', 'status', 'modul', 'message'), 'create_date', 'DESC', '', 5 - count($rows));
         foreach ($msgRows as $row) {
            $message = trim(strip_tags((string) ($row['message'] ?? 'Systemmeldung')));
            if (strlen($message) > 92) {
               $message = substr($message, 0, 89) . '...';
            }

            $rows[] = array(
               'icon' => 'bi-info-circle',
               'title' => dbx()->esc($row['status'] ?? 'Info'),
               'meta' => dbx()->esc($message),
               'time' => dbx()->esc($row['create_date'] ?? ''),
               'tone' => 'msg',
            );
         }
      }

      if (!$rows) {
         $rows[] = array(
            'icon' => 'bi-check2-circle',
            'title' => 'Keine Aktivitaet',
            'meta' => 'Trace und Systemmeldungen sind aktuell leer.',
            'time' => '',
            'tone' => 'empty',
         );
      }

      return $rows;
   }

   private function activity_report($metrics) {
      $oReport = new \dbxReport();
      $oReport->init('admin-dashboard-activity', 'admin-dashboard-activity-report');
      $oReport->add_obj('activity_bar', 'dbx|component-bar', $this->card_bar_data('Aktivitaet', 'bi-clock-history'));
      $oReport->_mode = 'tpl';
      $oReport->_pages = 0;
      $oReport->_rdata = $this->activity_rows($metrics);
      $oReport->_rcount = count($oReport->_rdata);
      $oReport->_rrows = 10;
      $oReport->_msg_info = '';

      return $oReport->run();
   }

   private function quick_actions() {
      $oForm = new \dbxForm();
      $oForm->init('admin-dashboard-actions', 'admin-dashboard-actions');
      $oForm->_action = '?dbx_modul=dbxAdmin&dbx_run1=run';
      $oForm->_msg_info = '';
      $oForm->add_obj('actions_bar', 'dbx|component-bar', $this->card_bar_data('Quick Actions', 'bi-lightning-charge'));

      $actions = array(
         'users' => array('href' => '?dbx_modul=dbxAdmin&dbx_run1=user&dbx_run2=list_user', 'icon' => 'bi-people', 'label' => 'Benutzer'),
         'sessions' => array('href' => '?dbx_modul=dbxAdmin&dbx_run1=session&dbx_run2=list_session', 'icon' => 'bi-broadcast', 'label' => 'Sessions'),
         'modules' => array('href' => '?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_list', 'icon' => 'bi-grid', 'label' => 'Module'),
         'dd' => array('href' => '?dbx_modul=dbxAdmin&dbx_run1=dd&dbx_run2=list_dd', 'icon' => 'bi-diagram-3', 'label' => 'DD Sync'),
         'db' => array('href' => '?dbx_modul=dbxAdmin&dbx_run1=db&dbx_run2=list_db', 'icon' => 'bi-hdd-stack', 'label' => 'DB Sync'),
         'sysmsg' => array('href' => '?dbx_modul=dbxAdmin&dbx_run1=sysmsg&dbx_run2=list_sysmsg', 'icon' => 'bi-bell', 'label' => 'SysMsg'),
      );

      foreach ($actions as $key => $data) {
         $oForm->add_obj('action_' . $key, 'dbxAdmin|admin-dashboard-action-link', $data);
      }

      return $oForm->run();
   }

   private function load_content_cache_classes(): void {
      dbx()->load_content_cache_classes();
   }

   private function content_cache_language_catalog(): array {
      return array(
         'de' => array('label' => 'Deutsch',  'flag' => '🇩🇪', 'tone' => 'teal'),
         'en' => array('label' => 'English',  'flag' => '🇬🇧', 'tone' => 'navy'),
         'es' => array('label' => 'Español',  'flag' => '🇪🇸', 'tone' => 'amber'),
      );
   }

   private function content_cache_enabled_config(): bool {
      return \dbx\dbxContent\dbxContentPageCache::isConfigEnabled();
   }

   private function dbx_config_bool(string $key, $default = 0): bool {
      $value = dbx()->get_config('dbx', $key);
      if ($value === 'undef' || $value === '' || $value === null) {
         $value = $default;
      }

      return (int) $value === 1;
   }

   private function performance_request_enabled_config(): bool {
      return $this->dbx_config_bool('performance_timer_request', dbx()->get_config('dbx', 'performance_timer'));
   }

   private function performance_db_enabled_config(): bool {
      return $this->dbx_config_bool('performance_timer_db', dbx()->get_config('dbx', 'performance_timer_detail'));
   }

   private function normalize_performance_level($level): string {
      $level = strtolower(trim((string) $level));
      if ($level === 'details') {
         $level = 'detail';
      }

      return in_array($level, array('off', 'main', 'detail'), true) ? $level : 'off';
   }

   private function performance_level_config(): string {
      $level = dbx()->get_config('dbx', 'performance_timer_level');
      if ($level !== 'undef' && $level !== '' && $level !== null) {
         return $this->normalize_performance_level($level);
      }

      return ($this->performance_request_enabled_config() || $this->performance_db_enabled_config()) ? 'detail' : 'off';
   }

   private function set_performance_level_config(string $level): bool {
      $level = $this->normalize_performance_level($level);
      $config = dbx()->get_config('dbx');
      if (!is_array($config)) {
         $config = array();
      }

      $enabled = $level !== 'off';
      $detail = $level === 'detail';
      $config['performance_timer_level'] = $level;
      $config['performance_timer'] = $enabled ? 1 : 0;
      $config['performance_timer_request'] = $enabled ? 1 : 0;
      $config['performance_timer_db'] = $enabled ? 1 : 0;
      $config['performance_timer_detail'] = $detail ? 1 : 0;

      return (int) dbx()->set_config('dbx', $config) > 0;
   }

   private function set_performance_config(string $target, bool $enabled): bool {
      if (!$enabled) {
         return $this->set_performance_level_config('off');
      }

      return $this->set_performance_level_config('detail');
   }

   private function process_performance_config_action(): bool {
      $run2 = dbx()->get_modul_var('dbx_run2', '', 'parameter');
      if ($run2 !== 'performance_save') {
         return false;
      }

      if (isset($_POST['performance_level'])) {
         return $this->set_performance_level_config((string) $_POST['performance_level']);
      }

      $target = strtolower(trim((string) ($_POST['performance_target'] ?? 'request')));
      $target = $target === 'db' ? 'db' : 'request';
      $enabled = isset($_POST['performance_enabled']);
      return $this->set_performance_config($target, $enabled);
   }

   private function is_ajax_request(): bool {
      return (int) dbx()->get_system_var('dbx_ajax', 0, 'int') === 1;
   }

   private function respond_dashboard_ajax_html(string $html): void {
      if (!headers_sent()) {
         header('Content-Type: text/html; charset=utf-8');
      }

      echo $html;

      $oSession = dbx()->get_system_obj('dbxSession');
      if (is_object($oSession) && method_exists($oSession, 'save_session')) {
         $oSession->save_session();
      }

      exit;
   }

   private function performance_level_options(string $current): string {
      $options = array(
         'off'    => 'Aus',
         'main'   => 'Nur Hauptkennzahlen',
         'detail' => 'Hauptkennzahlen und Details',
      );
      $html = '';

      foreach ($options as $value => $label) {
         $selected = $value === $current ? ' selected' : '';
         $html .= '<option value="' . dbx()->esc($value) . '"' . $selected . '>' . dbx()->esc($label) . '</option>';
      }

      return $html;
   }

   private function performance_level_control(): string {
      $level = $this->performance_level_config();
      $meta = array(
         'off' => array(
            'label' => 'Performance aus',
            'hint'  => 'dbxapp schreibt aktuell keine neuen Performance-Daten.',
            'icon'  => 'bi-pause-circle-fill',
            'tone'  => 'off',
         ),
         'main' => array(
            'label' => 'Performance Hauptkennzahlen',
            'hint'  => 'dbxapp schreibt nur PHP/System, JS und DB Gesamtwerte.',
            'icon'  => 'bi-check-circle-fill',
            'tone'  => 'on',
         ),
         'detail' => array(
            'label' => 'Performance Details',
            'hint'  => 'dbxapp schreibt Hauptkennzahlen und Detail-Timer.',
            'icon'  => 'bi-check-circle-fill',
            'tone'  => 'on',
         ),
      );
      $state = $meta[$level] ?? $meta['off'];

      $oForm = new \dbxForm();
      $oForm->init('admin-dashboard-performance-config', 'admin-dashboard-performance-config');
      $oForm->set_form_help_enabled(false);
      $oForm->_action = '?dbx_modul=dbxAdmin&dbx_run1=run&dbx_run2=performance_save';
      $oForm->_msg_info = '';
      $oForm->add_rep('action', dbx()->esc('?dbx_modul=dbxAdmin&dbx_run1=run&dbx_run2=performance_save'));
      $oForm->add_rep('status_tone', dbx()->esc($state['tone']));
      $oForm->add_rep('status_icon', dbx()->esc($state['icon']));
      $oForm->add_rep('status_label', dbx()->esc($state['label']));
      $oForm->add_rep('status_hint', dbx()->esc($state['hint']));
      $oForm->add_rep('performance_level_options', $this->performance_level_options($level));

      return $oForm->add_norep($oForm->run());
   }

   private function performance_toggle_control(string $target): string {
      return $this->performance_level_control();
   }

   private function content_cache_files_for_lng(string $lng): int {
      $this->load_content_cache_classes();
      $lng = strtolower(trim($lng));
      if ($lng === '') {
         return 0;
      }

      $base = \dbx\dbxContent\dbxContentPageCache::baseDir() . 'content/';
      return count(glob($base . 'full-page/*_' . $lng . '_*.htm') ?: array());
   }

   private function content_cache_language_rows(): string {
      $this->load_content_cache_classes();
      $tpl = dbx()->get_system_obj('dbxTPL');
      $catalog = $this->content_cache_language_catalog();
      $lngs = function_exists('dbx_accessible_lngs') ? dbx_accessible_lngs() : array('de');
      $tones = array('teal', 'navy', 'amber', 'cyan', 'purple', 'green');
      $rows = '';
      $toneIndex = 0;

      foreach ($lngs as $lng) {
         $lng = strtolower(trim((string) $lng));
         if ($lng === '') {
            continue;
         }

         $meta = $catalog[$lng] ?? array(
            'label' => strtoupper($lng),
            'flag' => '🌐',
            'tone' => $tones[$toneIndex % count($tones)],
         );
         $toneIndex++;

         $pagesTotal = $this->safe_count(\dbx\dbxContent\dbxContentLng::ddContent($lng));
         $pagesActive = $this->safe_count(\dbx\dbxContent\dbxContentLng::ddContent($lng), 'activ = 1');
         $folders = $this->safe_count(\dbx\dbxContent\dbxContentLng::ddFolder($lng));

         $rows .= $tpl->get_tpl('dbxAdmin|admin-dashboard-content-cache-lng', array(
            'flag' => dbx()->esc($meta['flag'] ?? '🌐'),
            'label' => dbx()->esc($meta['label'] ?? strtoupper($lng)),
            'code' => dbx()->esc(strtoupper($lng)),
            'tone' => dbx()->esc($meta['tone'] ?? 'teal'),
            'pages_total' => $this->fmt($pagesTotal),
            'pages_active' => $this->fmt($pagesActive),
            'folders' => $this->fmt($folders),
            'cached' => $this->fmt($this->content_cache_files_for_lng($lng)),
         ));
      }

      if ($rows === '') {
         $rows = '<article class="dbx-admin-dashboard-cache-lng-card dbx-admin-dashboard-cache-lng-empty">'
            . '<div class="dbx-admin-dashboard-cache-lng-title"><strong>Keine Sprachen konfiguriert</strong></div>'
            . '</article>';
      }

      return $rows;
   }

   private function process_content_cache_action(): void {
      $run2 = dbx()->get_modul_var('dbx_run2', '', 'parameter');
      if ($run2 === '') {
         return;
      }

      $this->load_content_cache_classes();

      if ($run2 === 'cache_flush') {
         \dbx\dbxContent\dbxContentPageCache::invalidateAll();
         return;
      }

      if ($run2 === 'sitemap_rebuild') {
         \dbx\dbxContent\dbxContentSitemap::rebuild();
         return;
      }

      if ($run2 === 'cache_save') {
         $enabled = isset($_POST['cache_content']);
         \dbx\dbxContent\dbxContentPageCache::setConfigEnabled($enabled);
      }
   }

   private function content_cache_panel_body_data(): array {
      $this->load_content_cache_classes();
      $stats = \dbx\dbxContent\dbxContentPageCache::cacheStats();
      $sitemapStats = \dbx\dbxContent\dbxContentSitemap::stats();
      $enabled = $this->content_cache_enabled_config();

      return array(
         'cache_save_url' => dbx()->esc('?dbx_modul=dbxAdmin&dbx_run1=run&dbx_run2=cache_save'),
         'cache_flush_url' => dbx()->esc('?dbx_modul=dbxAdmin&dbx_run1=run&dbx_run2=cache_flush'),
         'sitemap_rebuild_url' => dbx()->esc('?dbx_modul=dbxAdmin&dbx_run1=run&dbx_run2=sitemap_rebuild'),
         'sitemap_url' => dbx()->esc(rtrim((string) dbx()->get_base_url(), '/') . '/sitemap.xml'),
         'cache_admin_url' => dbx()->esc('?dbx_modul=dbxAdmin&dbx_run1=cache'),
         'cache_enabled_checked' => $enabled ? 'checked' : '',
         'cache_status_tone' => $enabled ? 'on' : 'off',
         'cache_status_icon' => $enabled ? 'bi-check-circle-fill' : 'bi-pause-circle-fill',
         'cache_status_label' => dbx()->esc($enabled ? 'Cache-Schreiben aktiv' : 'Cache-Schreiben pausiert'),
         'cache_status_hint' => $enabled
            ? dbx()->esc('Vorhandene Gastseiten werden gelesen; Cache-Misses werden als vollstaendige Endausgabe gespeichert.')
               . '<br>'
               . dbx()->esc('Head-Metadaten, Design, Menues und Module sind fertig aufgeloest; ein HIT braucht nur den Session-Zugriff, aber keine Content-Datenbank.')
            : dbx()->esc('Vorhandene Gastseiten werden weiterhin aus dem Cache ausgegeben. Cache-Misses werden live gerendert, aber nicht neu gespeichert.'),
         'cache_content_count' => $this->fmt((int) ($stats['content'] ?? 0)),
         'sitemap_count' => $this->fmt((int) ($sitemapStats['urls'] ?? 0)),
         'sitemap_generated' => dbx()->esc((string) ($sitemapStats['generated_at'] ?? '')),
         'sitemap_state' => dbx()->esc(!empty($sitemapStats['exists']) ? 'vorhanden' : 'nicht erstellt'),
         'cache_dir' => dbx()->esc((string) ($stats['base_dir'] ?? '')),
         'lng_rows' => $this->content_cache_language_rows(),
      );
   }

   private function content_cache_panel_body_html(): string {
      $oForm = new \dbxForm();
      $oForm->init('admin-dashboard-content-cache-body', 'admin-dashboard-content-cache-body');
      $oForm->_action = '?dbx_modul=dbxAdmin&dbx_run1=run';
      $oForm->_msg_info = '';

      foreach ($this->content_cache_panel_body_data() as $key => $value) {
         $oForm->add_rep($key, $value);
      }

      return $oForm->add_norep($oForm->run());
   }

   private function content_cache_bar_actions_html(): string {
      $this->load_content_cache_classes();
      $enabled = $this->content_cache_enabled_config();
      $oForm = new \dbxForm();
      $oForm->init('admin-dashboard-content-cache-actions', 'admin-dashboard-content-cache-actions');
      $oForm->_action = '?dbx_modul=dbxAdmin&dbx_run1=run&dbx_run2=cache_save';
      $oForm->_msg_info = '';
      $oForm->add_rep('cache_save_url', dbx()->esc('?dbx_modul=dbxAdmin&dbx_run1=run&dbx_run2=cache_save'));
      $oForm->add_rep('cache_flush_url', dbx()->esc('?dbx_modul=dbxAdmin&dbx_run1=run&dbx_run2=cache_flush'));
      $oForm->add_rep('sitemap_rebuild_url', dbx()->esc('?dbx_modul=dbxAdmin&dbx_run1=run&dbx_run2=sitemap_rebuild'));
      $oForm->add_rep('cache_enabled_checked', $enabled ? 'checked' : '');
      $oForm->add_rep('bar_extra', $this->help_action('cache'));

      return $oForm->add_norep($oForm->run());
   }

   private function content_cache_panel() {
      $oForm = new \dbxForm();
      $panelTarget = 'content-cache';
      $oForm->init('admin-dashboard-content-cache', 'admin-dashboard-content-cache');
      $oForm->_action = '?dbx_modul=dbxAdmin&dbx_run1=run';
      $oForm->_msg_info = '';
      $oForm->add_obj('cache_bar', 'dbx|component-bar', $this->card_bar_data(
         'Gast-Full-Page-Cache',
         'bi-lightning-charge-fill',
         'Komplette Endausgabe gueltiger Permalinks, ausschliesslich fuer nicht angemeldete Gaeste',
         $this->content_cache_bar_actions_html()
            . $this->card_action('?dbx_modul=dbxContent_admin&dbx_run1=content&dbx_run2=edit', 'CMS')
            . $this->collapse_action($panelTarget, 'Zuklappen', true)
      ));
      $oForm->add_rep('panel_target', dbx()->esc($panelTarget));
      $oForm->add_rep('cache_body', $this->content_cache_panel_body_html());

      return $oForm->run();
   }

   private function hero_panel($metrics) {
      $heroPerformance = $this->hero_performance($metrics);
      $oForm = new \dbxForm();
      $oForm->init('admin-dashboard-hero', 'admin-dashboard-hero');
      $oForm->_fd = 'dbxAdmin|admin-dashboard-status';
      $oForm->load_fd_messages();
      $oForm->_msg_info = '';
      $oForm->add_rep('bar_class', 'dbx-admin-dashboard-hero-bar');
      $oForm->add_rep('bar_title', $oForm->get_fd_message('bar_title'));
      $oForm->add_rep('bar_subtitle', $oForm->get_fd_message('bar_subtitle'));
      $oForm->add_obj(
         'bar_actions',
         'obj-value',
         '<small class="dbx-bar-meta">'
            . dbx()->esc($oForm->get_fd_message('status_timestamp'))
            . ' '
            . dbx()->esc(date('d.m.Y H:i'))
            . '</small>'
      );
      $oForm->add_rep('health_percent', (int) $metrics['health_percent']);
      $hasErrorLog = !empty($metrics['health_error_log']);
      $healthReason = $hasErrorLog
         ? $oForm->get_fd_message('health_error')
         : (string)($metrics['health_reason'] ?? $oForm->get_fd_message('health_ok'));
      if ($healthReason === 'OK') {
         $healthReason = $oForm->get_fd_message('health_ok');
      }
      $oForm->add_rep('health_reason', dbx()->esc($healthReason));
      $oForm->add_rep('health_state_class', $hasErrorLog ? 'is-error' : 'is-ok');
      $oForm->add_rep('health_icon', $hasErrorLog ? 'bi-exclamation-octagon-fill' : 'bi-shield-check');
      $oForm->add_rep('performance_aria', $oForm->get_fd_message('performance_aria'));
      $oForm->add_rep('system_status_label', $oForm->get_fd_message('system_status_label'));
      $oForm->add_obj('hero_performance_gauges', 'obj-value', $this->hero_performance_gauges($heroPerformance));
      $oForm->add_obj('hero_status_summaries', 'obj-value', $this->hero_status_summaries());
      $oForm->add_obj('error_log', 'obj-value', $hasErrorLog ? $this->error_log_panel($oForm) : '');

      return $oForm->run();
   }

   private function widgets_panel($metrics) {
      $oForm = new \dbxForm();
      $oForm->init('admin-dashboard-widgets', 'admin-dashboard-widgets');
      $oForm->_msg_info = '';
      $oForm->add_obj('widgets', 'obj-value', $this->widgets($metrics, $this->metric_history($metrics)));

      return $oForm->run();
   }

   private function chart_json($metrics) {
      $data = array(
         array('label' => 'Benutzer', 'value' => (int) $metrics['users'], 'tone' => 'teal'),
         array('label' => 'Online', 'value' => (int) $metrics['online'], 'tone' => 'green'),
         array('label' => 'Module', 'value' => (int) $metrics['modules'], 'tone' => 'navy'),
         array('label' => 'DDs', 'value' => (int) $metrics['dd_count'], 'tone' => 'cyan'),
         array('label' => 'DBs', 'value' => (int) $metrics['databases'], 'tone' => 'amber'),
      );

      return dbx()->esc(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT));
   }

   private function chart_panel($metrics) {
      $oForm = new \dbxForm();
      $oForm->init('admin-dashboard-chart-panel', 'admin-dashboard-chart-panel');
      $oForm->_msg_info = '';
      $oForm->add_rep('chart_json', $this->chart_json($metrics));
      $oForm->add_rep('trace_count', $this->fmt($metrics['trace_count']));
      $oForm->add_rep('missing_count', $this->fmt($metrics['missing']));
      $oForm->add_rep('autosync_count', $this->fmt($metrics['autosync']));
      $oForm->add_rep('trace_enabled_count', $this->fmt($metrics['trace_enabled']));
      $oForm->add_rep('request_runtime_ms', $this->fmt($metrics['request_runtime_ms']));
      $oForm->add_rep('memory_peak_kb', $this->fmt($metrics['memory_peak_kb']));
      $oForm->add_obj('chart_bar', 'dbx|component-bar', $this->card_bar_data('Grafische Auswertung', 'bi-bar-chart-line', 'Relative Verteilung'));

      return $oForm->run();
   }

   private function dashboard_area($area) {
      $metrics = $this->metrics();

      switch ((string) $area) {
         case 'hero':
            return $this->hero_panel($metrics);
         case 'widgets':
            return $this->widgets_panel($metrics);
         case 'quick_actions':
            return $this->quick_actions();
         case 'performance_panel':
            return $this->performance_panel($metrics);
         case 'sysmsg_panel':
            return $this->sysmsg_panel();
         case 'session_panel':
            return $this->session_panel();
         case 'content_cache_panel':
            return $this->content_cache_panel();
         case 'chart_panel':
            return $this->chart_panel($metrics);
         case 'activity_report':
            return $this->activity_report($metrics);
         case 'database_report':
            return $this->database_report($metrics);
      }

      return null;
   }

   public function run() {
      $run2 = dbx()->get_modul_var('dbx_run2', '', 'parameter');
      if ($run2 === 'delete_error_log') {
         $this->process_error_log_action();
         $run2 = '';
      }
      if ($run2 === 'performance_compress') {
         return $this->run_performance_maintenance_process('compress');
      }
      if ($run2 === 'performance_clear') {
         return $this->run_performance_maintenance_process('clear');
      }

      if ($this->is_ajax_request()) {
         if ($run2 === 'cache_flush' || $run2 === 'cache_save' || $run2 === 'sitemap_rebuild') {
            $this->process_content_cache_action();
            $this->respond_dashboard_ajax_html($this->content_cache_panel_body_html());
         }

         if ($run2 === 'performance_save') {
            $this->process_performance_config_action();
            $target = strtolower(trim((string) ($_POST['performance_target'] ?? 'request')));
            $target = $target === 'db' ? 'db' : 'request';
            $this->respond_dashboard_ajax_html($this->performance_toggle_control($target));
         }

         if ($run2 === 'sysmsg_level_save') {
            $this->process_sys_msg_level_action();
            $this->respond_dashboard_ajax_html($this->sys_msg_level_control());
         }

         if ($run2 === 'session_db_save') {
            $this->process_session_db_action();
            $this->respond_dashboard_ajax_html($this->session_panel_control_html());
         }
      }

      if ($run2 === 'performance_save') {
         $this->process_performance_config_action();
      }
      if ($run2 === 'sysmsg_level_save') {
         $this->process_sys_msg_level_action();
      }
      if ($run2 === 'session_db_save') {
         $this->process_session_db_action();
      }
      if ($run2 === 'cache_flush' || $run2 === 'cache_save' || $run2 === 'sitemap_rebuild') {
         $this->process_content_cache_action();
      }

      if ($run2 !== '') {
         $areaContent = $this->dashboard_area($run2);
         if ($areaContent !== null) {
            return $areaContent;
         }
      }

      $oForm = new \dbxForm();

      $oForm->init('admin-dashboard', 'admin-dashboard');
      $oForm->_fd = 'dbxAdmin|admin-dashboard-status';
      $oForm->load_fd_messages();
      $oForm->_action = '?dbx_modul=dbxAdmin&dbx_run1=run';
      $oForm->_msg_info = '';

      if ($this->dashboardMessageKey !== '') {
         $message = $oForm->get_fd_message($this->dashboardMessageKey);
         if ($this->dashboardMessageError) {
            $oForm->_msg_error = $message;
         } else {
            $oForm->_msg_success = $message;
         }
      }

      $messageHtml = '';
      if ($this->dashboardMessageKey !== '') {
         $message = dbx()->esc($oForm->get_fd_message($this->dashboardMessageKey));
         $tone = $this->dashboardMessageError ? 'danger' : 'success';
         $icon = $this->dashboardMessageError
            ? 'bi-exclamation-triangle-fill'
            : 'bi-check-circle-fill';
         $messageHtml = '<div class="alert alert-' . $tone
            . ' d-flex align-items-center gap-2 mb-3" role="alert">'
            . '<i class="bi ' . $icon . '" aria-hidden="true"></i>'
            . '<span>' . $message . '</span></div>';
      }
      $oForm->add_obj('dashboard_message', 'obj-value', $messageHtml);

      $metrics = $this->metrics();
      $this->store_history_snapshot($metrics);

      $content = $oForm->run();

      return $content;
   }
}
