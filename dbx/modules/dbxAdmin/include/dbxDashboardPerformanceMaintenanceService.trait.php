<?php
namespace dbx\dbxAdmin;

trait dbxDashboardPerformanceMaintenanceServiceTrait {

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

      list($request_server, $request_table) = $this->performance_dd_info(self::PERF_REQUEST_DD);
      list($timer_server, $timer_table) = $this->performance_dd_info(self::PERF_TIMER_DD);
      if ($request_server === '' || $request_table === '' || $timer_server === '' || $timer_table === '') {
         $stats['messages'][] = 'Performance-Tabellen konnten nicht ermittelt werden.';
         return $stats;
      }

      $db = dbx()->get_system_obj('dbxDB');
      $request_rows = $db->select_query($request_server, "SELECT COUNT(*) AS row_count, AVG(total_time_ms) AS total_time_ms, AVG(total_memory_kb) AS total_memory_kb, AVG(peak_memory_mb) AS peak_memory_mb, AVG(timer_count) AS timer_count FROM $request_table");
      $timer_rows = $db->select_query($timer_server, "SELECT section, MAX(info) AS info, MIN(sort_order) AS sort_order, COUNT(*) AS row_count, AVG(time_ms) AS time_ms, AVG(memory_kb) AS memory_kb, AVG(start_memory_kb) AS start_memory_kb, AVG(end_memory_kb) AS end_memory_kb FROM $timer_table WHERE section <> '' AND section <> 'system' GROUP BY section ORDER BY MIN(sort_order) ASC, section ASC");

      $db->empty(self::PERF_TIMER_DD);
      $db->empty(self::PERF_REQUEST_DD);

      $base = $this->performance_now_record_base();
      $request_id = 0;
      $request_date = date('Y-m-d H:i:s');
      $request_count = is_array($request_rows) ? (int) ($request_rows[0]['row_count'] ?? 0) : 0;

      if ($request_count > 0) {
         $request = array_merge($base, array(
            'request_date'    => $request_date,
            'uid'             => (int) dbx()->user(),
            'session_id'      => 'compressed',
            'modul'           => 'compressed',
            'run1'            => 'performance',
            'run2'            => 'compress',
            'ajax'            => 0,
            'sync'            => 0,
            'method'          => 'COMPRESS',
            'uri'             => 'Performance DB komprimiert aus ' . $request_count . ' Request-Datensaetzen',
            'total_time_ms'   => (int) round((float) ($request_rows[0]['total_time_ms'] ?? 0)),
            'total_memory_kb' => (int) round((float) ($request_rows[0]['total_memory_kb'] ?? 0)),
            'peak_memory_mb'  => (int) round((float) ($request_rows[0]['peak_memory_mb'] ?? 0)),
            'timer_count'     => (int) round((float) ($request_rows[0]['timer_count'] ?? 0)),
            'sample_rate'     => max(1, $request_count),
         ));
         if ($db->insert(self::PERF_REQUEST_DD, $request, 0, 1, 1, 0) === 1) {
            $request_id = $db->get_insert_id();
            $stats['inserted']++;
         }
      }

      if (is_array($timer_rows)) {
         $sort = 0;
         foreach ($timer_rows as $row) {
            $section = trim((string) ($row['section'] ?? ''));
            if ($section === '') {
               continue;
            }

            $count = max(1, (int) ($row['row_count'] ?? 0));
            $timer = array_merge($base, array(
               'request_id'      => $request_id,
               'request_date'    => $request_date,
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
}
