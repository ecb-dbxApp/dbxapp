<?php
namespace dbx\dbxAdmin;

trait dbxDashboardWidgetsReportServiceTrait {

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
      $o_report = new \dbxReport();
      $panel_target = 'database-report';
      $o_report->init('admin-dashboard-db', 'admin-dashboard-db-report');
      $o_report->add_obj('database_bar', 'dbx|module-bar', $this->card_bar_data(
         'Datenbanken und Tabellen',
         'bi-hdd-stack',
         '',
         $this->card_action('?dbx_modul=dbxAdmin&dbx_run1=db&dbx_run2=list_db', 'DB Sync')
            . $this->collapse_action($panel_target, 'Zuklappen', true)
      ));
      $o_report->add_rep('panel_target', dbx()->esc($panel_target));
      $o_report->set_mode('tpl');
      $o_report->_pages = 0;
      $o_report->_rdata = $this->database_rows($metrics);
      $o_report->_rcount = count($o_report->_rdata);
      $o_report->_rrows = 20;
      $o_report->_msg_info = '';

      return $o_report->run();
   }

   private function activity_rows($metrics) {
      $rows = array();
      $trace_rows = $this->safe_select('dbxTrace', '', array('id', 'create_date', 'action', 'dd', 'record_id'), 'create_date', 'DESC', '', 5);

      foreach ($trace_rows as $row) {
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
         $msg_rows = $this->safe_select('dbxSysMsg', '', array('id', 'create_date', 'status', 'modul', 'message'), 'create_date', 'DESC', '', 5 - count($rows));
         foreach ($msg_rows as $row) {
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
      $o_report = new \dbxReport();
      $o_report->init('admin-dashboard-activity', 'admin-dashboard-activity-report');
      $o_report->add_obj('activity_bar', 'dbx|module-bar', $this->card_bar_data('Aktivitaet', 'bi-clock-history'));
      $o_report->set_mode('tpl');
      $o_report->_pages = 0;
      $o_report->_rdata = $this->activity_rows($metrics);
      $o_report->_rcount = count($o_report->_rdata);
      $o_report->_rrows = 10;
      $o_report->_msg_info = '';

      return $o_report->run();
   }

   private function quick_actions() {
      $o_form = new \dbxForm();
      $o_form->init('admin-dashboard-actions', 'admin-dashboard-actions');
      $o_form->set_field_definition('dbxAdmin|admin-dashboard-status');
      $o_form->load_fd_messages();
      $o_form->set_action('?dbx_modul=dbxAdmin&dbx_run1=run');
      $o_form->_msg_info = '';
      $o_form->add_obj('actions_bar', 'dbx|module-bar', $this->card_bar_data('Quick Actions', 'bi-lightning-charge'));

      $actions = array(
         'users' => array('href' => '?dbx_modul=dbxAdmin&dbx_run1=user&dbx_run2=list_user', 'icon' => 'bi-people', 'label' => 'Benutzer', 'ajax_class' => 'dbxAjax'),
         'sessions' => array('href' => '?dbx_modul=dbxAdmin&dbx_run1=session&dbx_run2=list_session', 'icon' => 'bi-broadcast', 'label' => 'Sessions', 'ajax_class' => 'dbxAjax'),
         'modules' => array('href' => '?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_list', 'icon' => 'bi-grid', 'label' => 'Module', 'ajax_class' => 'dbxAjax'),
         'dd' => array('href' => '?dbx_modul=dbxAdmin&dbx_run1=dd&dbx_run2=list_dd', 'icon' => 'bi-diagram-3', 'label' => 'DD Sync', 'ajax_class' => 'dbxAjax'),
         'db' => array('href' => '?dbx_modul=dbxAdmin&dbx_run1=db&dbx_run2=list_db', 'icon' => 'bi-hdd-stack', 'label' => 'DB Sync', 'ajax_class' => 'dbxAjax'),
         'sysmsg' => array('href' => '?dbx_modul=dbxAdmin&dbx_run1=sysmsg&dbx_run2=list_sysmsg', 'icon' => 'bi-bell', 'label' => 'SysMsg', 'ajax_class' => 'dbxAjax'),
         'update' => array('href' => '?dbx_modul=dbxAdmin&dbx_run1=update', 'icon' => 'bi-arrow-repeat', 'label' => $o_form->get_fd_message('update_title'), 'ajax_class' => ''),
      );

      foreach ($actions as $key => $data) {
         $o_form->add_obj('action_' . $key, 'dbxAdmin|admin-dashboard-action-link', $data);
      }

      return $o_form->run();
   }

   private function widgets_panel($metrics) {
      $o_form = new \dbxForm();
      $o_form->init('admin-dashboard-widgets', 'admin-dashboard-widgets');
      $o_form->_msg_info = '';
      $o_form->add_obj('widgets', 'obj-value', $this->widgets($metrics, $this->metric_history($metrics)));

      return $o_form->run();
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
      $o_form = new \dbxForm();
      $o_form->init('admin-dashboard-chart-panel', 'admin-dashboard-chart-panel');
      $o_form->_msg_info = '';
      $o_form->add_rep('chart_json', $this->chart_json($metrics));
      $o_form->add_rep('trace_count', $this->fmt($metrics['trace_count']));
      $o_form->add_rep('missing_count', $this->fmt($metrics['missing']));
      $o_form->add_rep('autosync_count', $this->fmt($metrics['autosync']));
      $o_form->add_rep('trace_enabled_count', $this->fmt($metrics['trace_enabled']));
      $o_form->add_rep('request_runtime_ms', $this->fmt($metrics['request_runtime_ms']));
      $o_form->add_rep('memory_peak_kb', $this->fmt($metrics['memory_peak_kb']));
      $o_form->add_obj('chart_bar', 'dbx|module-bar', $this->card_bar_data('Grafische Auswertung', 'bi-bar-chart-line', 'Relative Verteilung'));

      return $o_form->run();
   }
}
