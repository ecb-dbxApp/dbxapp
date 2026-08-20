<?php
namespace dbx\dbxAdmin;

trait dbxDashboardPerformanceReportServiceTrait {

   private function performance_request_average() {
      if ($this->performance_request_average !== null) {
         return $this->performance_request_average;
      }

      $this->performance_request_average = array();

      if (!$this->ensure_performance_tables()) {
         return $this->performance_request_average;
      }

      list($server, $table) = $this->performance_dd_info(self::PERF_REQUEST_DD);
      if ($server === '' || $table === '') {
         return $this->performance_request_average;
      }

      try {
         $db = dbx()->get_system_obj('dbxDB');
         $rows = $db->select_query($server, "SELECT COUNT(*) AS row_count,
                  AVG(total_time_ms) AS avg_time_ms,
                  AVG(total_memory_kb) AS avg_memory_kb,
                  AVG(timer_count) AS avg_timer_count,
                  SUM(CASE WHEN query_count > 0 THEN 1 ELSE 0 END) AS query_profiled_count,
                  AVG(CASE WHEN query_count > 0 THEN query_count END) AS avg_query_count,
                  AVG(CASE WHEN query_count > 0 THEN query_unique_count END) AS avg_query_unique_count,
                  AVG(CASE WHEN query_count > 0 THEN query_duplicate_count END) AS avg_query_duplicate_count,
                  AVG(CASE WHEN query_count > 0 THEN slow_query_count END) AS avg_slow_query_count,
                  AVG(CASE WHEN query_count > 0 THEN failed_query_count END) AS avg_failed_query_count,
                  AVG(CASE WHEN query_count > 0 THEN query_time_ms END) AS avg_query_time_ms
               FROM $table");
         $row = is_array($rows) ? ($rows[0] ?? array()) : array();

         if ((int) ($row['row_count'] ?? 0) <= 0) {
            return $this->performance_request_average;
         }

         $this->performance_request_average = array(
            'count'           => (int) ($row['row_count'] ?? 0),
            'avg_time_ms'     => (int) round((float) ($row['avg_time_ms'] ?? 0)),
            'avg_memory_kb'   => (int) round((float) ($row['avg_memory_kb'] ?? 0)),
            'avg_timer_count' => (int) round((float) ($row['avg_timer_count'] ?? 0)),
            'query_profiled_count' => (int) ($row['query_profiled_count'] ?? 0),
            'avg_query_count' => (int) round((float) ($row['avg_query_count'] ?? 0)),
            'avg_query_unique_count' => (int) round((float) ($row['avg_query_unique_count'] ?? 0)),
            'avg_query_duplicate_count' => (int) round((float) ($row['avg_query_duplicate_count'] ?? 0)),
            'avg_slow_query_count' => (int) round((float) ($row['avg_slow_query_count'] ?? 0)),
            'avg_failed_query_count' => (int) round((float) ($row['avg_failed_query_count'] ?? 0)),
            'avg_query_time_ms' => (int) round((float) ($row['avg_query_time_ms'] ?? 0)),
         );
      } catch (\Throwable $e) {
         $this->performance_request_average = array();
      }

      return $this->performance_request_average;
   }

   private function performance_timer_averages() {
      if ($this->performance_timer_averages !== null) {
         return $this->performance_timer_averages;
      }

      $this->performance_timer_averages = array();

      if (!$this->ensure_performance_tables()) {
         return $this->performance_timer_averages;
      }

      list($server, $table) = $this->performance_dd_info(self::PERF_TIMER_DD);
      if ($server === '' || $table === '') {
         return $this->performance_timer_averages;
      }

      try {
         $db = dbx()->get_system_obj('dbxDB');
         $sql = "SELECT section, MAX(info) AS info, MAX(fingerprint) AS fingerprint,
                        MIN(sort_order) AS sort_order, COUNT(*) AS row_count,
                        AVG(time_ms) AS avg_time_ms, AVG(memory_kb) AS avg_memory_kb,
                        AVG(query_count) AS avg_query_count,
                        AVG(duplicate_count) AS avg_duplicate_count,
                        AVG(max_time_ms) AS avg_max_time_ms,
                        AVG(affected_rows) AS avg_affected_rows,
                        AVG(failure_count) AS avg_failure_count
                 FROM $table
                 WHERE section <> 'system'
                 GROUP BY section
                 ORDER BY MIN(sort_order) ASC, section ASC";
         $rows = $db->select_query($server, $sql);
         $this->performance_timer_averages = is_array($rows) ? $rows : array();
      } catch (\Throwable $e) {
         $this->performance_timer_averages = array();
      }

      return $this->performance_timer_averages;
   }

   private function performance_timer_average($section) {
      $section = (string) $section;

      foreach ($this->performance_timer_averages() as $row) {
         if ((string) ($row['section'] ?? '') === $section) {
            return array(
               'count'         => (int) ($row['row_count'] ?? 0),
               'avg_time_ms'   => (int) round((float) ($row['avg_time_ms'] ?? 0)),
               'avg_memory_kb' => (int) round((float) ($row['avg_memory_kb'] ?? 0)),
               'avg_query_count' => (int) round((float) ($row['avg_query_count'] ?? 0)),
               'avg_duplicate_count' => (int) round((float) ($row['avg_duplicate_count'] ?? 0)),
               'avg_max_time_ms' => (int) round((float) ($row['avg_max_time_ms'] ?? 0)),
               'avg_failure_count' => (int) round((float) ($row['avg_failure_count'] ?? 0)),
            );
         }
      }

      return array();
   }

   private function performance_module_averages() {
      if ($this->performance_module_averages !== null) {
         return $this->performance_module_averages;
      }

      $this->performance_module_averages = array();

      if (!$this->ensure_performance_tables()) {
         return $this->performance_module_averages;
      }

      list($request_server, $request_table) = $this->performance_dd_info(self::PERF_REQUEST_DD);
      list($timer_server, $timer_table) = $this->performance_dd_info(self::PERF_TIMER_DD);
      if ($request_server === '' || $request_table === '' || $timer_server !== $request_server || $timer_table === '') {
         return $this->performance_module_averages;
      }

      try {
         $db = dbx()->get_system_obj('dbxDB');
         $sql = "SELECT r.modul AS modul,
                        COUNT(*) AS row_count,
                        AVG(r.total_time_ms) AS avg_total_ms,
                        AVG(COALESCE(d.time_ms, 0)) AS avg_db_ms,
                        SUM(CASE WHEN r.query_count > 0 THEN 1 ELSE 0 END) AS query_profiled_count,
                        AVG(CASE WHEN r.query_count > 0 THEN r.query_count END) AS avg_query_count,
                        AVG(CASE WHEN r.query_count > 0 THEN r.query_duplicate_count END) AS avg_query_duplicate_count,
                        AVG(CASE WHEN r.query_count > 0 THEN r.slow_query_count END) AS avg_slow_query_count
                   FROM $request_table r
                   LEFT JOIN $timer_table d
                     ON d.request_id = r.id
                    AND d.section = 'db-total'
                  WHERE TRIM(COALESCE(r.modul, '')) <> ''
                  GROUP BY r.modul
                  ORDER BY AVG(r.total_time_ms) DESC, r.modul ASC
                  LIMIT 16";
         $rows = $db->select_query($request_server, $sql);
         $this->performance_module_averages = is_array($rows) ? $rows : array();
      } catch (\Throwable $e) {
         $this->performance_module_averages = array();
      }

      return $this->performance_module_averages;
   }

   private function hero_performance($metrics) {
      $request = $this->performance_request_average();
      $db_total = $this->performance_timer_average('db-total');

      $request_ms = $request ? (int) ($request['avg_time_ms'] ?? 0) : (int) ($metrics['request_runtime_ms'] ?? 0);
      $db_ms = $db_total ? (int) ($db_total['avg_time_ms'] ?? 0) : 0;
      $php_ms = max(0, $request_ms - $db_ms);
      $db_share = $request_ms > 0 ? min(999, (int) round(($db_ms / $request_ms) * 100)) : 0;
      $php_share = $request_ms > 0 ? min(999, (int) round(($php_ms / $request_ms) * 100)) : 0;

      return array(
         'request_avg'   => $this->fmt_ms($request_ms),
         'request_raw'   => $request_ms,
         'request_count' => $this->fmt($request['count'] ?? 0),
         'php_avg'       => $this->fmt_ms($php_ms),
         'php_raw'       => $php_ms,
         'php_share'     => $this->fmt($php_share),
         'db_avg'        => $this->fmt_ms($db_ms),
         'db_raw'        => $db_ms,
         'db_count'      => $this->fmt($db_total['count'] ?? 0),
         'db_share'      => $this->fmt($db_share),
         'query_avg'     => $this->fmt($request['avg_query_count'] ?? 0),
         'query_unique_avg' => $this->fmt($request['avg_query_unique_count'] ?? 0),
         'query_duplicate_avg' => $this->fmt($request['avg_query_duplicate_count'] ?? 0),
         'query_slow_avg'=> $this->fmt($request['avg_slow_query_count'] ?? 0),
         'query_failed_avg' => $this->fmt($request['avg_failed_query_count'] ?? 0),
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

   private function hero_performance_gauges($hero_performance) {
      $items = '';
      $items .= $this->hero_performance_gauge(
         'Request gesamt',
         'bi-stopwatch',
         'request',
         $hero_performance['request_raw'] ?? 0,
         $hero_performance['request_avg'] ?? '0,000 Sec',
         'Durchschnitt aus ' . ($hero_performance['request_count'] ?? '0') . ' Requests'
      );
      $items .= $this->hero_performance_gauge(
         'PHP gesamt',
         'bi-cpu',
         'php',
         $hero_performance['php_raw'] ?? 0,
         $hero_performance['php_avg'] ?? '0,000 Sec',
         'Durchschnitt ohne DB-Anteil'
      );
      $items .= $this->hero_performance_gauge(
         'DB gesamt',
         'bi-database-check',
         'db',
         $hero_performance['db_raw'] ?? 0,
         $hero_performance['db_avg'] ?? '0,000 Sec',
         'Ø ' . ($hero_performance['query_avg'] ?? '0') . ' Queries · '
            . ($hero_performance['query_duplicate_avg'] ?? '0') . ' doppelt · '
            . ($hero_performance['query_slow_avg'] ?? '0') . ' langsam'
      );

      return $items;
   }





   private function performance_tone($index) {
      $tones = array('teal', 'green', 'navy', 'amber', 'cyan', 'red', 'purple', 'slate');
      return $tones[$index % count($tones)];
   }


   private function performance_module_image_url(string $modul): string {
      $modul = trim($modul);
      $safe_modul = preg_match('/^[A-Za-z0-9_\\-]+$/', $modul) ? $modul : 'dbx';
      $base_dir = rtrim((string) dbx()->get_base_dir(), '/\\') . DIRECTORY_SEPARATOR;
      $rel = 'dbx/modules/' . $safe_modul . '/tpl/img/' . $safe_modul . '.png';
      $path = $base_dir . str_replace('/', DIRECTORY_SEPARATOR, $rel);

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

         $total_ms = (int) round((float) ($module['avg_total_ms'] ?? 0));
         $db_ms = (int) round((float) ($module['avg_db_ms'] ?? 0));
         $php_ms = max(0, $total_ms - $db_ms);
         $query_avg = (int) round((float) ($module['avg_query_count'] ?? 0));
         $duplicate_avg = (int) round((float) ($module['avg_query_duplicate_count'] ?? 0));
         $slow_avg = (int) round((float) ($module['avg_slow_query_count'] ?? 0));

         $metrics = '';
         $metrics .= $this->performance_metric(array(
            'label' => 'PHP gesamt',
            'icon' => 'bi-cpu',
            'value_ms' => $php_ms,
            'detail' => 'PHP gesamt',
            'precision' => 3,
            'min_display_sec' => 0,
            'tone' => $this->performance_tone($index),
         ), $max);
         $metrics .= $this->performance_metric(array(
            'label' => 'DB gesamt',
            'icon' => 'bi-database-check',
            'value_ms' => $db_ms,
            'detail' => 'Ø ' . $this->fmt($query_avg) . ' Queries · '
               . $this->fmt($duplicate_avg) . ' doppelt · '
               . $this->fmt($slow_avg) . ' langsam',
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



   private function performance_panel($metrics) {
      $tpl = dbx()->get_system_obj('dbxTPL');
      $panel_target = 'request-performance';
      $actions = $this->performance_maintenance_actions()
         . $this->collapse_action($panel_target, 'Zuklappen', true)
         . $this->help_action('dashboard');

      return $tpl->get_tpl('dbxAdmin|admin-dashboard-performance', array(
         'panel_class' => '',
         'panel_target' => $panel_target,
         'performance_bar' => $this->card_bar('Performance pro Modul', 'bi-speedometer2', 'Durchschnittswerte je Modul: PHP gesamt und DB gesamt', $actions),
         'performance_controls' => $this->performance_level_control(),
         'performance_items' => $this->performance_module_list(),
      ));
   }

   private function performance_request_enabled_config(): bool {
      return $this->dbx_config_bool('performance_timer_request', dbx()->get_cfg('dbx', 'performance_timer'));
   }

   private function performance_db_enabled_config(): bool {
      return $this->dbx_config_bool('performance_timer_db', dbx()->get_cfg('dbx', 'performance_timer_detail'));
   }

   private function normalize_performance_level($level): string {
      $level = strtolower(trim((string) $level));
      if ($level === 'details') {
         $level = 'detail';
      }

      return in_array($level, array('off', 'main', 'detail'), true) ? $level : 'off';
   }

   private function performance_level_config(): string {
      $level = dbx()->get_cfg('dbx', 'performance_timer_level');
      if ($level !== 'undef' && $level !== '' && $level !== null) {
         return $this->normalize_performance_level($level);
      }

      return ($this->performance_request_enabled_config() || $this->performance_db_enabled_config()) ? 'detail' : 'off';
   }

   private function set_performance_level_config(string $level): bool {
      $level = $this->normalize_performance_level($level);
      $config = dbx()->get_cfg('dbx');
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

      return (int) dbx()->set_cfg('dbx', $config) > 0;
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

      $o_form = new \dbxForm();
      $o_form->init('admin-dashboard-performance-config', 'admin-dashboard-performance-config');
      $o_form->set_form_help_enabled(false);
      $o_form->set_action('?dbx_modul=dbxAdmin&dbx_run1=run&dbx_run2=performance_save');
      $o_form->_msg_info = '';
      $o_form->add_rep('action', dbx()->esc('?dbx_modul=dbxAdmin&dbx_run1=run&dbx_run2=performance_save'));
      $o_form->add_rep('status_tone', dbx()->esc($state['tone']));
      $o_form->add_rep('status_icon', dbx()->esc($state['icon']));
      $o_form->add_rep('status_label', dbx()->esc($state['label']));
      $o_form->add_rep('status_hint', dbx()->esc($state['hint']));
      $o_form->add_rep('performance_level_options', $this->performance_level_options($level));

      return $o_form->add_norep($o_form->run());
   }

   private function performance_toggle_control(string $target): string {
      return $this->performance_level_control();
   }
}
