<?php
namespace dbx\dbxAdmin;

trait dbxDashboardPerformanceHistoryServiceTrait {

   private function ensure_performance_tables() {
      try {
         $timer = dbx()->get_system_obj('dbxPerformanceTimer');
         if (is_object($timer) && method_exists($timer, 'ensure_schema')) {
            return $timer->ensure_schema();
         }
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
         $columns = $db->get_table_columns($server, $table);

         if (!$columns) {
            return false;
         }

         $existing = array_fill_keys($columns, 1);

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
}
