<?php

dbx()->use_system_class('dbxDD');

class dbxPerformanceTimer {

   private const REQUEST_DD = 'dbx|dbxPerformanceRequest';
   private const TIMER_DD = 'dbx|dbxPerformanceTimer';
   private const SCHEMA_MARKER_VERSION = 'v2-contract2';

   private array $configFileCache = array();

   private function config_value(string $key, $default = '') {
      $value = dbx()->get_cfg('dbx', $key);

      if ($value !== 'undef') {
         return $value;
      }

      $cfg = $this->config_file_values();
      return $cfg[$key] ?? $default;
   }

   private function config_file_values(): array {
      if ($this->configFileCache) {
         return $this->configFileCache;
      }

      $file = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/dbx/cfg/config.php');
      if (!is_file($file)) {
         return array();
      }

      $loader = static function ($file) {
         $config = array();
         include $file;
         return is_array($config) ? $config : array();
      };

      $this->configFileCache = $loader($file);
      return $this->configFileCache;
   }

   private function config_bool(string $key, $default = 0): bool {
      $value = $this->config_value($key, $default);
      return (int) $value === 1;
   }

   private function normalize_level($value): string {
      $level = strtolower(trim((string) $value));
      if ($level === 'details') {
         $level = 'detail';
      }

      return in_array($level, array('off', 'main', 'detail'), true) ? $level : '';
   }

   private function performance_level(): string {
      $level = $this->normalize_level($this->config_value('performance_timer_level', ''));
      if ($level !== '') {
         return $level;
      }

      $request = $this->config_bool('performance_timer_request', $this->config_value('performance_timer', 0));
      $db = $this->config_bool('performance_timer_db', $this->config_value('performance_timer_detail', 0));

      return ($request || $db) ? 'detail' : 'off';
   }

   private function is_enabled(): bool {
      return $this->performance_level() !== 'off';
   }


   private function is_main_section(string $section): bool {
      return in_array($section, array('system', 'js-total', 'db-total'), true);
   }

   private function sample_rate(): int {
      return max(1, (int) $this->config_value('performance_timer_sample_rate', 1));
   }

   private function should_sample(int $sampleRate): bool {
      if ($sampleRate <= 1) {
         return true;
      }

      try {
         return random_int(1, $sampleRate) === 1;
      } catch (\Throwable $e) {
         return mt_rand(1, $sampleRate) === 1;
      }
   }

   private function ensure_table_contract(string $table, array $fields, array $indexes = array()): bool {
      $server = 'dbx|dbxPerformance.db3';
      $db = dbx()->get_system_obj('dbxDB');
      $dd = dbx()->get_system_obj('dbxDD');
      $columns = $db->select_query($server, 'PRAGMA table_info(' . $table . ')');
      if (!is_array($columns)) return false;

      $existing = array();
      foreach ($columns as $column) {
         $name = (string)($column['name'] ?? '');
         if ($name !== '') $existing[$name] = 1;
      }
      foreach ($fields as $field) {
         $name = (string)($field['name'] ?? '');
         if ($name === '' || isset($existing[$name])) continue;
         if ($dd->add_db_field_from_dd($server, $table, $field) !== 1) return false;
         $existing[$name] = 1;
      }

      $indexRows = $db->select_query($server, 'PRAGMA index_list(' . $table . ')');
      if (!is_array($indexRows)) return false;
      $existingIndexes = array();
      foreach ($indexRows as $indexRow) {
         $name = (string)($indexRow['name'] ?? '');
         if ($name !== '') $existingIndexes[$name] = 1;
      }
      foreach ($indexes as $index) {
         $name = (string)($index['name'] ?? '');
         if ($name === '' || isset($existingIndexes[$name])) continue;
         if ($dd->create_db_index($server, $table, $index) !== 1) return false;
         $existingIndexes[$name] = 1;
      }

      return true;
   }

   private function ensure_tables(): bool {
      $marker = $this->runtime_dir() . '/schema-' . self::SCHEMA_MARKER_VERSION . '.ready';
      if (is_file($marker) && (int)@filemtime($marker) >= time() - 86400) {
         return true;
      }

      try {
         $dd = dbx()->get_system_obj('dbxDD');
         $ok = $dd->create_db_tab(self::REQUEST_DD) && $dd->create_db_tab(self::TIMER_DD);
         if ($ok) {
            $ok = $this->ensure_table_contract('dbx_performance_request', array(
               array('name' => 'query_count', 'type' => 'int', 'length' => '11', 'default' => '0', 'index' => ''),
               array('name' => 'query_unique_count', 'type' => 'int', 'length' => '11', 'default' => '0', 'index' => ''),
               array('name' => 'query_duplicate_count', 'type' => 'int', 'length' => '11', 'default' => '0', 'index' => ''),
               array('name' => 'slow_query_count', 'type' => 'int', 'length' => '11', 'default' => '0', 'index' => ''),
               array('name' => 'failed_query_count', 'type' => 'int', 'length' => '11', 'default' => '0', 'index' => ''),
               array('name' => 'query_time_ms', 'type' => 'int', 'length' => '11', 'default' => '0', 'index' => ''),
               array('name' => 'query_affected_rows', 'type' => 'int', 'length' => '11', 'default' => '0', 'index' => ''),
            ));
         }
         if ($ok) {
            $ok = $this->ensure_table_contract('dbx_performance_timer', array(
               array('name' => 'fingerprint', 'type' => 'varchar', 'length' => '32', 'default' => '', 'index' => ''),
               array('name' => 'query_count', 'type' => 'int', 'length' => '11', 'default' => '0', 'index' => ''),
               array('name' => 'duplicate_count', 'type' => 'int', 'length' => '11', 'default' => '0', 'index' => ''),
               array('name' => 'max_time_ms', 'type' => 'int', 'length' => '11', 'default' => '0', 'index' => ''),
               array('name' => 'affected_rows', 'type' => 'int', 'length' => '11', 'default' => '0', 'index' => ''),
               array('name' => 'failure_count', 'type' => 'int', 'length' => '11', 'default' => '0', 'index' => ''),
            ), array(
               array('name' => 'idx_dbx_performance_timer_request_section', 'type' => 'INDEX', 'fields' => 'request_id,section', 'unique' => '0'),
               array('name' => 'idx_dbx_performance_timer_fingerprint', 'type' => 'INDEX', 'fields' => 'fingerprint', 'unique' => '0'),
            ));
         }
         if ($ok) @file_put_contents($marker, date('c'), LOCK_EX);
         return $ok;
      } catch (\Throwable $e) {
         return false;
      }
   }

   public function ensure_schema(): bool {
      return $this->ensure_tables();
   }

   private function runtime_dir(): string {
      $dir = rtrim((string)dbx()->get_file_dir(), '/\\') . '/cache/performance';
      $dir = dbx()->os_path($dir);
      if (!is_dir($dir)) @mkdir($dir, 0755, true);
      return $dir;
   }

   private function ms($seconds): int {
      return max(0, (int) round(((float) $seconds) * 1000));
   }

   private function kb($bytes): int {
      return (int) round(((float) $bytes) / 1024);
   }

   private function timer_ms(array $timer): int {
      if (isset($timer['time']) && is_numeric($timer['time']) && (float) $timer['time'] >= 0) {
         return $this->ms($timer['time']);
      }

      if (isset($timer['start_time'], $timer['end_time']) && is_numeric($timer['start_time']) && is_numeric($timer['end_time'])) {
         return $this->ms(max(0, (float) $timer['end_time'] - (float) $timer['start_time']));
      }

      return 0;
   }

   private function timer_memory_kb(array $timer): int {
      if (isset($timer['memory']) && is_numeric($timer['memory']) && (float) $timer['memory'] >= 0) {
         return $this->kb($timer['memory']);
      }

      if (isset($timer['start_memory'], $timer['end_memory']) && is_numeric($timer['start_memory']) && is_numeric($timer['end_memory'])) {
         return $this->kb(max(0, (float) $timer['end_memory'] - (float) $timer['start_memory']));
      }

      return 0;
   }

   private function timer_end_memory_mb(array $timer): int {
      if (isset($timer['end_memory']) && is_numeric($timer['end_memory']) && (float) $timer['end_memory'] >= 0) {
         return max(0, (int) ceil(((float) $timer['end_memory']) / 1048576));
      }

      if (isset($timer['start_memory']) && is_numeric($timer['start_memory']) && (float) $timer['start_memory'] >= 0) {
         return max(0, (int) ceil(((float) $timer['start_memory']) / 1048576));
      }

      return 0;
   }

   private function trim_text($value, int $length): string {
      $value = str_replace(array("\r", "\n", "\t"), ' ', (string) $value);
      return substr($value, 0, $length);
   }

   private function request_record(array $timers, int $sampleRate, array $queries = array()): array {
      $system = $timers['system'] ?? array();
      $uid = (int) dbx()->user();
      $modul = (string) dbx()->get_system_var('dbx_modul', dbx()->get_system_var('dbx_activ_modul', 'dbx'));
      $now = date('Y-m-d H:i:s');

      return array(
         'request_date'    => $now,
         'create_date'     => $now,
         'create_uid'      => $uid,
         'update_date'     => $now,
         'update_uid'      => $uid,
         'owner'           => $uid,
         'uid'             => $uid,
         'session_id'      => $this->trim_text(session_id(), 128),
         'modul'           => $this->trim_text($modul, 80),
         'run1'            => $this->trim_text(dbx()->get_system_var('dbx_run1', ''), 80),
         'run2'            => $this->trim_text(dbx()->get_system_var('dbx_run2', ''), 80),
         'ajax'            => (int) dbx()->get_system_var('dbx_ajax', 0, 'int'),
         'sync'            => (int) dbx()->get_request_var('dbx_sync', 1, 'int'),
         'method'          => $this->trim_text($_SERVER['REQUEST_METHOD'] ?? '', 12),
         'uri'             => $this->trim_text($_SERVER['REQUEST_URI'] ?? '', 1200),
         'total_time_ms'   => $this->timer_ms($system),
         'total_memory_kb' => $this->timer_memory_kb($system),
         'peak_memory_mb'  => $this->timer_end_memory_mb($system),
         'timer_count'     => count($timers) + count((array) ($queries['queries'] ?? array())),
         'query_count'     => (int) ($queries['query_count'] ?? 0),
         'query_unique_count' => (int) ($queries['unique_query_count'] ?? 0),
         'query_duplicate_count' => (int) ($queries['duplicate_query_count'] ?? 0),
         'slow_query_count'=> (int) ($queries['slow_query_count'] ?? 0),
         'failed_query_count' => (int) ($queries['failed_query_count'] ?? 0),
         'query_time_ms'   => (int) ($queries['query_time_ms'] ?? 0),
         'query_affected_rows' => (int) ($queries['affected_rows'] ?? 0),
         'sample_rate'     => $sampleRate,
      );
   }

   private function timer_record(int $requestId, string $requestDate, string $section, array $timer, int $sortOrder): array {
      $uid = (int) dbx()->user();
      $now = date('Y-m-d H:i:s');

      return array(
         'request_id'      => $requestId,
         'request_date'    => $requestDate,
         'create_date'     => $now,
         'create_uid'      => $uid,
         'update_date'     => $now,
         'update_uid'      => $uid,
         'owner'           => $uid,
         'sort_order'      => $sortOrder,
         'section'         => $this->trim_text($section, 80),
         'fingerprint'     => $this->trim_text($timer['fingerprint'] ?? '', 32),
         'info'            => $this->trim_text($timer['info'] ?? '', 160),
         'time_ms'         => $this->timer_ms($timer),
         'memory_kb'       => $this->timer_memory_kb($timer),
         'start_memory_kb' => isset($timer['start_memory']) && is_numeric($timer['start_memory']) ? $this->kb($timer['start_memory']) : 0,
         'end_memory_kb'   => isset($timer['end_memory']) && is_numeric($timer['end_memory']) ? $this->kb($timer['end_memory']) : 0,
         'query_count'     => (int) ($timer['query_count'] ?? 0),
         'duplicate_count' => (int) ($timer['duplicate_count'] ?? 0),
         'max_time_ms'     => (int) round((float) ($timer['max_time_ms'] ?? 0)),
         'affected_rows'   => (int) ($timer['affected_rows'] ?? 0),
         'failure_count'   => (int) ($timer['failure_count'] ?? 0),
      );
   }

   /** Wandelt die dbxDB-Fingerprints in persistierbare Detail-Timer um. */
   private function query_timers(array $snapshot): array {
      $out = array();
      $queries = array_slice((array) ($snapshot['queries'] ?? array()), 0, 40);

      foreach ($queries as $query) {
         $fingerprint = preg_replace('/[^a-f0-9]/i', '', (string) ($query['fingerprint'] ?? ''));
         if ($fingerprint === '') continue;

         $count = max(0, (int) ($query['query_count'] ?? 0));
         $operation = strtoupper(trim((string) ($query['operation'] ?? 'SQL')));
         $server = trim((string) ($query['server'] ?? ''));
         $sql = trim((string) ($query['sql'] ?? ''));
         $label = trim($operation . ($server !== '' ? ' [' . $server . ']' : '') . ($sql !== '' ? ' ' . $sql : ''));

         $out['db-query-' . substr($fingerprint, 0, 16)] = array(
            'info'            => $label,
            'time'            => max(0.0, ((float) ($query['time_ms'] ?? 0)) / 1000),
            'memory'          => 0,
            'fingerprint'     => substr($fingerprint, 0, 32),
            'query_count'     => $count,
            'duplicate_count' => max(0, $count - 1),
            'max_time_ms'     => max(0, (float) ($query['max_time_ms'] ?? 0)),
            'affected_rows'   => max(0, (int) ($query['affected_rows'] ?? 0)),
            'failure_count'   => max(0, (int) ($query['failure_count'] ?? 0)),
         );
      }

      return $out;
   }

   private function cleanup_old_rows(): void {
      $days = (int) $this->config_value('performance_timer_keep_days', 14);
      if ($days <= 0) {
         return;
      }

      $marker = $this->runtime_dir() . '/cleanup.timestamp';
      $handle = @fopen($marker, 'c+b');
      if (!is_resource($handle) || !@flock($handle, LOCK_EX | LOCK_NB)) {
         if (is_resource($handle)) @fclose($handle);
         return;
      }

      @rewind($handle);
      $lastCleanup = (int)trim((string)stream_get_contents($handle));
      if ($lastCleanup >= time() - 86400) {
         @flock($handle, LOCK_UN);
         @fclose($handle);
         return;
      }

      $cutoff = date('Y-m-d H:i:s', time() - ($days * 86400));

      try {
         $db = dbx()->get_system_obj('dbxDB');
         $db->delete(self::TIMER_DD, "request_date < '" . $cutoff . "'", 0, 0);
         $db->delete(self::REQUEST_DD, "request_date < '" . $cutoff . "'", 0, 0);
         @ftruncate($handle, 0);
         @rewind($handle);
         @fwrite($handle, (string)time());
         @fflush($handle);
      } catch (\Throwable $e) {
         // Marker bleibt unveraendert; ein spaeterer Request darf erneut
         // versuchen aufzuraeumen.
      } finally {
         @flock($handle, LOCK_UN);
         @fclose($handle);
      }
   }

   public function store(): int {
      if ((int) dbx()->get_system_var('dbx_performance_timer_skip', 0, 'int') === 1) {
         return 0;
      }

      if (!$this->is_enabled()) {
         return 0;
      }

      $sampleRate = $this->sample_rate();
      if (!$this->should_sample($sampleRate)) {
         return 0;
      }

      global $dbx_run_timer;
      if (!isset($dbx_run_timer) || !is_array($dbx_run_timer) || !$dbx_run_timer) {
         return 0;
      }

      $db = dbx()->get_system_obj('dbxDB');
      $querySnapshot = method_exists($db, 'performance_query_snapshot')
         ? (array) $db->performance_query_snapshot()
         : array();

      dbx()->set_system_var('dbx_performance_timer_store', 1);
      try {
         if (!$this->ensure_tables()) {
            return 0;
         }

         $performanceLevel = $this->performance_level();
         $detailEnabled = $performanceLevel === 'detail';
         $request = $this->request_record($dbx_run_timer, $sampleRate, $querySnapshot);
         $requestId = 0;

         if ($db->begin(self::REQUEST_DD) !== 1) {
            return 0;
         }

         try {
            $requestId = ($db->insert(self::REQUEST_DD, $request, 0, 1, 1, 0) === 1) ? $db->get_insert_id() : 0;

            if ($requestId <= 0) {
               throw new \RuntimeException('performance_request_insert_failed');
            }

            $sort = 0;
            foreach ($dbx_run_timer as $section => $timer) {
               if (!is_array($timer)) {
                  continue;
               }

               $section = (string) $section;
               if (!$detailEnabled && !$this->is_main_section($section)) {
                  continue;
               }

               if ($db->insert(self::TIMER_DD, $this->timer_record($requestId, $request['request_date'], $section, $timer, $sort), 0, 1, 1, 0) !== 1) {
                  throw new \RuntimeException('performance_timer_insert_failed');
               }
               $sort++;
            }

            if ($detailEnabled) {
               foreach ($this->query_timers($querySnapshot) as $section => $timer) {
                  if ($db->insert(self::TIMER_DD, $this->timer_record($requestId, $request['request_date'], $section, $timer, $sort), 0, 1, 1, 0) !== 1) {
                     throw new \RuntimeException('performance_query_timer_insert_failed');
                  }
                  $sort++;
               }
            }

            if ($db->commit(self::REQUEST_DD) !== 1) {
               throw new \RuntimeException('performance_commit_failed');
            }
         } catch (\Throwable $e) {
            $db->rollback(self::REQUEST_DD);
            return 0;
         }

         $this->cleanup_old_rows();
         return $requestId;
      } catch (\Throwable $e) {
         return 0;
      } finally {
         dbx()->set_system_var('dbx_performance_timer_store', 0);
      }
   }
}
