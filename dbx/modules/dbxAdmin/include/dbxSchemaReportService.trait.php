<?php
namespace dbx\dbxAdmin;

trait dbxSchemaReportServiceTrait {



   /**
    * Liest alle DD-Dateien aller Module und baut die DD-Report-Zeilen.
    *
    * @return array
    */
   private function get_dd_records() {
      $records = array();
      $o_dd = dbx()->get_system_obj('dbxDD');
      $base = str_replace('\\', '/', dbx()->get_base_dir()) . 'dbx/modules/*/dd/*.dd.php';

      foreach (glob($base) as $file) {
         $norm = str_replace('\\', '/', $file);

         if (!preg_match('#/dbx/modules/([^/]+)/dd/([^/]+)\.dd\.php$#', $norm, $match)) {
            continue;
         }

         $modul = $match[1];
         $dd = $match[2];
         if ($dd === 'new') {
            continue;
         }

         $dd_ref = $this->dd_ref($modul, $dd);
         $model = $o_dd->get_dd_model($dd_ref);
         if (!$model) {
            continue;
         }

         $server = $model['table']['server'] ?? '';
         $table  = $model['table']['table'] ?? '';
         if (str_starts_with(strtolower((string)$table), 'sqlite_')) {
            continue;
         }
         $exists = ($server && $table) ? $o_dd->get_table_exist($server, $table) : 0;
         $count = $exists ? $o_dd->count($dd_ref) : '-';
         $plan = $this->get_sync_plan($modul, $dd);
         $actions = $this->dd_actions($modul, $dd, $server, $table, $plan);

         $dd_fields = array_values((array)($model['fields'] ?? array()));
         $db_fields = ($server && $table && $exists) ? $o_dd->get_db_fields($server, $table) : array();

         $records[] = array(
            'rid'       => $modul . '|' . $dd,
            'modul'     => $modul,
            'dd'        => $dd,
            'path'      => $this->path_rel($norm),
            'server'    => $server,
            'database'  => $this->get_database_label($server),
            'table'     => $table,
            'dd_fields' => $this->field_tooltip_count($dd_fields, $this->schema_texts()->get_fd_message('dd_fields')),
            'db_fields' => $this->field_tooltip_count($db_fields, $this->schema_texts()->get_fd_message('db_fields')),
            'count'     => $count,
            'sync'      => $this->sync_label_from_plan($plan),
            'sync_info' => $this->sync_detail_from_plan($plan),
            'act_sync'  => $actions['sync'] ?? '',
            'act_map'   => $actions['map'] ?? '',
            'act_move'  => $actions['transfer'] ?? '',
            'act_flds'  => $actions['fields'] ?? '',
         );
      }

      usort($records, function($a, $b) {
         return strcmp($a['modul'] . '|' . $a['dd'], $b['modul'] . '|' . $b['dd']);
      });

      return $records;
   }



   /**
    * Indexiert DD-Records nach Server-/Tabellen-Aliassen fuer DB-Zuordnung.
    *
    * @param array $ddRecords Eingabeparameter fuer diese Methode.
    * @return array
    */


   private function get_dd_index_by_db($dd_records) {
      $index = array();

      foreach ($dd_records as $record) {
         $server = $record['server'] ?? '';
         $table  = $record['table'] ?? '';

         if (!$server || !$table) {
            continue;
         }

         $rid = (string)($record['rid'] ?? '');
         if ($rid === '') {
            $rid = (string)($record['modul'] ?? '') . '|' . (string)($record['dd'] ?? '');
         }

         foreach ($this->db_server_aliases($server) as $alias) {
            $key = $this->norm_key($alias, $table);
            if (!isset($index[$key])) {
               $index[$key] = array();
            }

            $index[$key][$rid] = $record;
         }
      }

      foreach ($index as $key => $records) {
         $index[$key] = array_values($records);
      }

      return $index;
   }



   /**
    * Sucht alle DD-Records, die zu einem konkreten DB-Server und einer Tabelle passen.
    *
    * @param array $ddIndex Eingabeparameter fuer diese Methode.
    * @param string $server Eingabeparameter fuer diese Methode.
    * @param string $table Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function get_dd_records_for_db($dd_index, $server, $table) {
      $records = array();

      if (!$server || !$table) {
         return array();
      }

      foreach ($this->db_server_aliases($server) as $alias) {
         $key = $this->norm_key($alias, $table);
         if (!isset($dd_index[$key])) {
            continue;
         }

         foreach ($dd_index[$key] as $record) {
            $rid = (string)($record['rid'] ?? '');
            if ($rid === '') {
               $rid = (string)($record['modul'] ?? '') . '|' . (string)($record['dd'] ?? '');
            }

            $records[$rid] = $record;
         }
      }

      return array_values($records);
   }



   /**
    * Erzeugt Aktionslinks fuer DD-Report-Zeilen.
    *
    * @param string $modul Eingabeparameter fuer diese Methode.
    * @param string $dd Eingabeparameter fuer diese Methode.
    * @param string $server Eingabeparameter fuer diese Methode.
    * @param string $table Eingabeparameter fuer diese Methode.
    * @param array $plan Eingabeparameter fuer diese Methode.
    * @return array
    */


   private function dd_actions($modul, $dd, $server, $table, $plan) {
      $actions = array(
         'sync'     => '',
         'map'      => '',
         'transfer' => '',
         'fields'   => '',
      );
      $mode = (!empty($plan['rebuild_needed'])) ? 'force' : 'apply';

      $sync_url = $this->build_url('dd', 'sync_dd_to_db', array(
         'modul' => $modul,
         'dd'    => $dd,
         'mode'  => $mode,
         'reset' => 1,
      ));
      $actions['sync'] = $this->openwin($sync_url, 'bi bi-arrow-repeat', $this->schema_texts()->get_fd_message('action_dd_to_db'), 1000, 700);

      $map_url = $this->build_url('dd', 'mapping', array(
         'kind'  => 'dd_to_db',
         'modul' => $modul,
         'dd'    => $dd,
      ));
      $actions['map'] = $this->openwin($map_url, 'bi bi-diagram-3', $this->schema_texts()->get_fd_message('action_mapping'), 1280, 880);

      if ($server && $table) {
         $transfer_url = $this->build_url('dd', 'transfer', array(
            'source_server' => $server,
            'source_table'  => $table,
         ));
         $actions['transfer'] = $this->openwin($transfer_url, 'bi bi-box-arrow-right', $this->schema_texts()->get_fd_message('action_transfer'), 980, 720);
      }

      $fields_url = $this->build_url('dd', 'fields', array(
         'modul' => $modul,
         'dd'    => $dd,
      ));
      $actions['fields'] = $this->openwin($fields_url, 'bi bi-list-columns', $this->schema_texts()->get_fd_message('action_dd_fields'), 1400, 860);

      return $actions;
   }



   /**
    * Erzeugt Aktionslinks fuer DB-Report-Zeilen.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @param string $table Eingabeparameter fuer diese Methode.
    * @param array $dds Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function db_actions($server, $table, $dds) {
      $actions = array(
         'sync'          => '',
         'sync_dd_to_db' => '',
         'map'           => '',
         'transfer'      => '',
      );

      if (is_array($dds) && count($dds)) {
         $first = $dds[0];
         $plan = $this->get_sync_plan($first['modul'], $first['dd']);
         $dd_to_db_mode = (!empty($plan['rebuild_needed'])) ? 'force' : 'apply';

         $sync_url = $this->build_url('db', 'sync_db_to_dd', array(
            'server' => $server,
            'table'  => $table,
            'modul'  => $first['modul'],
            'dd'     => $first['dd'],
            'mode'   => 'merge',
            'reset'  => 1,
         ));
         $actions['sync'] = $this->openwin($sync_url, 'bi bi-arrow-down-up', $this->schema_texts()->get_fd_message('action_db_to_dd'), 1000, 700);

         $dd_to_db_url = $this->build_url('db', 'sync_dd_to_db', array(
            'modul' => $first['modul'],
            'dd'    => $first['dd'],
            'mode'  => $dd_to_db_mode,
            'reset' => 1,
         ));
         $actions['sync_dd_to_db'] = $this->openwin($dd_to_db_url, 'bi bi-arrow-repeat', $this->schema_texts()->get_fd_message('action_db_from_dd'), 1000, 700);

         $map_url = $this->build_url('db', 'mapping', array(
            'kind'   => 'db_to_dd',
            'server' => $server,
            'table'  => $table,
            'modul'  => $first['modul'],
            'dd'     => $first['dd'],
         ));
         $actions['map'] = $this->openwin($map_url, 'bi bi-diagram-3', $this->schema_texts()->get_fd_message('action_mapping'), 1280, 880);
      } else {
         $sync_url = $this->build_url('db', 'sync_db_to_dd', array(
            'server' => $server,
            'table'  => $table,
         ));
         $actions['sync'] = $this->openwin($sync_url, 'bi bi-file-earmark-plus', $this->schema_texts()->get_fd_message('action_create_dd'), 980, 720);
      }

      $transfer_url = $this->build_url('db', 'transfer', array(
         'source_server' => $server,
         'source_table'  => $table,
      ));
      $actions['transfer'] = $this->openwin($transfer_url, 'bi bi-box-arrow-right', $this->schema_texts()->get_fd_message('action_transfer'), 980, 720);

      return $actions;
   }



   /**
    * Liest alle DB-Server, Tabellen und DD-Zuordnungen fuer den DB-Report.
    *
    * @param array $ddRecords Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function get_db_records($dd_records) {
      $records = array();
      $o_db = dbx()->get_system_obj('dbxDB');
      $o_dd = dbx()->get_system_obj('dbxDD');
      $dd_index = $this->get_dd_index_by_db($dd_records);
      $servers = $this->get_server_options();

      foreach ($servers as $server => $label) {
         $tables = $o_db->get_db_tables($server);
         if (!is_array($tables)) {
            $tables = array();
         }

         $has_tables = false;

         foreach ($tables as $table_rec) {
            $table = $table_rec['name'] ?? '';
            if (!$table) {
               continue;
            }

            $has_tables = true;

            $dds = $this->get_dd_records_for_db($dd_index, $server, $table);
            $dd_labels = array();
            $sync = $this->badge($this->schema_texts()->get_fd_message('no_dd'), 'secondary');
            $sync_info = '';

            foreach ($dds as $dd_rec) {
               $dd_labels[] = $this->esc($dd_rec['modul'] . '|' . $dd_rec['dd']);
            }

            if ($dds) {
               $sync = $dds[0]['sync'];
               $sync_info = $dds[0]['sync_info'] ?? '';
            }

            $db_fields = $o_dd->get_db_fields($server, $table);
            $dd_fields = array();

            if ($dds) {
               $first = $dds[0];
               $model = $o_dd->get_dd_model($this->dd_ref($first['modul'], $first['dd']));
               $dd_fields = array_values((array)($model['fields'] ?? array()));
            }

            $actions = $this->db_actions($server, $table, $dds);

            $records[] = array(
               'rid'       => $this->encode_db_rid($server, $table),
               'modul'     => $this->get_server_module($server),
               'server'    => $server,
               'database'  => $this->get_database_label($server),
               'path'      => $this->get_database_path_label($server),
               'table'     => $table,
               'db_fields' => $this->field_tooltip_count($db_fields, $this->schema_texts()->get_fd_message('db_fields')),
               'dd_fields' => $this->field_tooltip_count($dd_fields, $this->schema_texts()->get_fd_message('dd_fields')),
               'count'     => $table_rec['count'] ?? '-',
               'dd'        => implode('<br>', $dd_labels),
               'sync'      => $sync,
               'sync_info' => $sync_info,
               'act_sync'  => $actions['sync'] ?? '',
               'act_dd_sync' => $actions['sync_dd_to_db'] ?? '',
               'act_map'   => $actions['map'] ?? '',
               'act_move'  => $actions['transfer'] ?? '',
               'dd_edit_modul' => $dds[0]['modul'] ?? '',
               'dd_edit_dd'    => $dds[0]['dd'] ?? '',
            );
         }

         if (!$has_tables) {
            $records[] = array(
               'rid'       => $this->encode_db_rid($server, ''),
               'modul'     => $this->get_server_module($server),
               'server'    => $server,
               'database'  => $this->get_database_label($server),
               'path'      => $this->get_database_path_label($server),
               'table'     => $this->badge($this->schema_texts()->get_fd_message('no_tables'), 'secondary'),
               'db_fields' => $this->field_tooltip_count(array(), $this->schema_texts()->get_fd_message('db_fields')),
               'dd_fields' => $this->field_tooltip_count(array(), $this->schema_texts()->get_fd_message('dd_fields')),
               'count'     => '-',
               'dd'        => '',
               'sync'      => $this->badge($this->schema_texts()->get_fd_message('no_tables'), 'secondary'),
               'sync_info' => '',
               'act_sync'  => '',
               'act_dd_sync' => '',
               'act_map'   => '',
               'act_move'  => '',
               'dd_edit_modul' => '',
               'dd_edit_dd'    => '',
            );
         }
      }

      usort($records, function($a, $b) {
         return strcmp(strip_tags($a['modul'] . '|' . $a['server'] . '|' . $a['table']), strip_tags($b['modul'] . '|' . $b['server'] . '|' . $b['table']));
      });

      return $records;
   }



   /**
    * Filtert Report-Zeilen anhand eines Suchbegriffs.
    *
    * @param array $rows Eingabeparameter fuer diese Methode.
    * @param string $search Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function filter_rows($rows, $search) {
      $search = trim((string) $search);
      if ($search === '') {
         return $rows;
      }

      $filtered = array();
      foreach ($rows as $row) {
         $haystack = strtolower(strip_tags(implode(' ', $row)));
         if (strpos($haystack, strtolower($search)) !== false) {
            $filtered[] = $row;
         }
      }

      return $filtered;
   }



   /**
    * Prüft, ob echte Config-DB-Server fuer den sys-Filter vorhanden sind.
    *
    * @return bool
    */
   private function has_config_db_servers() {
      $config = dbx()->get_cfg('dbx', 'db');
      if (!is_array($config) || count($config) <= 0) {
         return false;
      }

      $module_files = $this->get_module_db_files();
      $module_file_index = array();

      foreach ($module_files as $server => $db) {
         $real = realpath((string)($db['file'] ?? ''));
         if ($real) {
            $module_file_index[strtolower(str_replace('\\', '/', $real))] = 1;
         }
      }

      foreach ($config as $server => $data) {
         if (isset($module_files[$server])) {
            continue;
         }

         $type = strtolower((string)($data['type'] ?? ''));
         $host = (string)($data['host'] ?? '');
         $name = (string)($data['dbname'] ?? ($data['name'] ?? ''));
         $is_sqlite = ($type == 'sqlite' || $type == 'sqlite3' || preg_match('/\.(db3|sqlite|sqlite3)$/i', $name));

         if ($is_sqlite && ($host !== '' || $name !== '')) {
            $file = dbx()->os_path($host . $name);
            $real = realpath($file);

            if ($real) {
               $real = strtolower(str_replace('\\', '/', $real));
               if (isset($module_file_index[$real])) {
                  continue;
               }
            }
         }

         return true;
      }

      return false;
   }



   /**
    * Erzeugt die Modulfilteroptionen fuer Schema-Reports.
    *
    * @param array $rows Eingabeparameter fuer diese Methode.
    * @param string $includeSys Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function get_module_filter_options($rows, $include_sys = false) {
      // Der Filter zeigt den vollstaendigen Modulbestand. Module ohne eigene
      // DB-Datei oder DD (z. B. dbxContent und dbxContent_admin) duerfen nicht
      // nur deshalb aus der Auswahl verschwinden.
      $options = $this->get_module_options();

      if ($include_sys) {
         $options['sys'] = 'sys';
      }

      foreach ($rows as $row) {
         $modul = trim((string)($row['modul'] ?? ''));
         if ($modul !== '') {
            $options[$modul] = $modul;
         }
      }

      ksort($options);
      return array('0' => $this->schema_texts()->get_fd_message('all_modules')) + $options;
   }



   /**
    * Filtert Report-Zeilen nach Modul oder sys.
    *
    * @param array $rows Eingabeparameter fuer diese Methode.
    * @param string $modul Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function filter_rows_by_module($rows, $modul) {
      $modul = trim((string)$modul);
      if ($modul === '' || $modul === '0') {
         return $rows;
      }

      $filtered = array();
      foreach ($rows as $row) {
         $row_modul = (string)($row['modul'] ?? '');
         $has_dd_module = preg_match(
            '/(?:^|\s)' . preg_quote($modul, '/') . '\|/i',
            str_replace(array('<br>', '<br/>', '<br />'), ' ', (string)($row['dd'] ?? ''))
         ) === 1;

         if (strcasecmp($row_modul, $modul) === 0 || $has_dd_module) {
            $filtered[] = $row;
         }
      }

      return $filtered;
   }



   /**
    * Rendert einen Schema-Report mit Filter, Suche, Pagination und Batch-Aktion.
    *
    * @param string $mode Eingabeparameter fuer diese Methode.
    * @param array $rows Eingabeparameter fuer diese Methode.
    * @param array $flds Eingabeparameter fuer diese Methode.
    * @param string $action Eingabeparameter fuer diese Methode.
    * @param array $batchOptions Eingabeparameter fuer diese Methode.
    * @param string $message Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function run_schema_report($mode, $rows, $flds, $action, $batch_options, $message = '') {
      $total_count = count($rows);
      $o_report = dbx()->get_system_obj('dbxReport');
      $fid_map = array(
         'dd' => 'report-dd-sync',
         'db' => 'report-db-sync',
         'backup' => 'report-db-backup',
         'restore' => 'report-db-restore',
      );
      $fid = $fid_map[$mode] ?? 'report-db-sync';
      $module_options = $this->get_module_filter_options($rows, $mode != 'dd' && $this->has_config_db_servers());
      $is_schema_list = in_array($mode, array('dd', 'db'), true);

      $o_report->init($fid, 'report-schema');
      $o_report->set_field_definition('dbxAdmin|schema-report');
      $o_report->load_fd_messages();
      $o_report->set_form_help_enabled(false);
      $o_report->set_callback_owner($this);
      $o_report->set_callback('row_action_data', 'schema_row_action_data');
      $o_report->set_action($action);
      $o_report->_rflds = $flds;
      $o_report->set_mode('table');
      $o_report->_pages = true;
      $o_report->_rrows = 25;
      $o_report->_rpos = 0;
      $o_report->_but_pagination = 7;
      $o_report->_create_row_select = true;
      $o_report->_create_row_edit = $is_schema_list;
      $o_report->_create_row_show = $is_schema_list;
      $o_report->_create_row_delete = $is_schema_list;
      $o_report->_create_sel_flds = false;
      $o_report->_fld_id = 'rid';
      $o_report->_table_buttons = 'left';
      $texts = $this->schema_texts();
      $o_report->_msg_confirm_delete = ($mode == 'dd')
         ? $texts->get_fd_message('confirm_delete_dd')
         : $texts->get_fd_message('confirm_delete_db');
      $o_report->_msg_info = (string)$message;
      $o_report->set_table_tpl('tpl_row_edit', 'modul|schema_row_edit');
      $o_report->set_table_tpl('tpl_row_show', 'modul|schema_row_show');
      $o_report->set_table_tpl('tpl_row_delete', 'modul|schema_row_delete');

      $o_report->add_action('rows_select', 'action_button_select', '&dbx_do=multi_select');
      $o_report->add_action('rows_deselect', 'action_button_deselect', '&dbx_do=multi_deselect');

      $bar_prefix = in_array($mode, array('dd', 'backup', 'restore'), true) ? $mode : 'db';
      $o_report->add_rep('bar_title', $texts->get_fd_message('bar_' . $bar_prefix . '_title'));
      $o_report->add_rep('bar_subtitle', $texts->get_fd_message('bar_' . $bar_prefix . '_subtitle'));
      $o_report->add_fld('dbx_rmodul', 'select-single-label', label: $texts->get_fd_message('label_module'), rules: 'parameter', options: $module_options);
      $o_report->add_fld('dbx_rwhere', 'dbx|search', label: $texts->get_fd_message('label_search'), rules: 'sqlsearch|max=80');
      $o_report->add_fld('dbx_rrows', 'integer-label', label: $texts->get_fd_message('label_rows'), rules: 'int');
      $o_report->add_fld('maction_select', 'select-single', label: $texts->get_fd_message('label_batch'), rules: 'parameter', options: $batch_options);
      $o_report->add_obj('maction_submit', 'dbx|button-submit', data: 'label=' . $texts->get_fd_message('action_start'));

      if ($o_report->submit()) {
         $act = $o_report->get_fld_val('maction_select', '0', 'parameter');
         if ($act && $act != '0') {
            $selects = array_keys($o_report->get_multi_selects());
            if ($selects) {
               return $this->start_batch($act, $selects);
            }

            $o_report->_msg_warning = $texts->get_fd_message('no_rows_selected');
         }
      }

      $modul  = $o_report->get_fld_val('dbx_rmodul', '0', 'parameter');
      $search = $o_report->get_fld_val('dbx_rwhere', '', 'sqlsearch|max=80');
      $rrows  = $o_report->get_fld_val('dbx_rrows', 25, 'int');
      $rpos   = $o_report->get_fld_val('dbx_rpos', 0, 'int');
      if ($rrows <= 0) {
         $rrows = 25;
      }

      $rows = $this->filter_rows_by_module($rows, $modul);
      $rows = $this->filter_rows($rows, $search);
      $o_report->_rrows = $rrows;
      $o_report->_rpos = $rpos;
      $o_report->set_report_counts(count($rows), $total_count);
      $o_report->_rdata = $o_report->data_rows($rows, $rpos, $rrows);

      return $o_report->run();
   }



   /**
    * Erweitert Report-Zeilen um Daten fuer Row-Aktions-Templates.
    *
    * @param string $report Eingabeparameter fuer diese Methode.
    * @param array $data Eingabeparameter fuer diese Methode.
    * @return array
    */
   public function schema_row_action_data($report, $data) {
      if (!is_array($data) || !isset($data['data']) || !is_array($data['data'])) {
         return $data;
      }

      $type = (string)($data['type'] ?? '');
      if (!in_array($type, array('edit', 'show', 'delete'), true)) {
         return $data;
      }

      $record = is_array($data['record'] ?? null) ? $data['record'] : array();
      $rid = (string)($data['data']['rid'] ?? ($record['rid'] ?? ''));
      $mode = str_starts_with($rid, 'db_') ? 'db' : 'dd';
      $base = $this->build_url($mode, $mode == 'dd' ? 'list_dd' : 'list_db');

      if ($type == 'edit') {
         $modul = '';
         $dd = '';

         if ($mode == 'dd') {
            [$modul, $dd] = $this->split_dd_rid($rid);
         } else {
            $modul = (string)($record['dd_edit_modul'] ?? '');
            $dd    = (string)($record['dd_edit_dd'] ?? '');
         }

         if ($modul && $dd) {
            $edit_url = '?dbx_modul=dbxAdmin&dbx_run1=edit_dd&modul=' . rawurlencode($modul) . '&dd=' . rawurlencode($dd);
            $edit_title = $this->schema_texts()->format_fd_message('edit_dd', array('module' => $modul, 'dd' => $dd));
            $data['data']['edit_url'] = $this->esc($edit_url);
            $data['data']['edit_title'] = $this->esc($edit_title);
            $data['data']['edit_data_dBx'] = 'data-url="' . $this->esc($edit_url) . '" data-title="' . $this->esc($edit_title) . '"';
         } else {
            $data['data']['edit_url'] = '#';
            $data['data']['edit_title'] = $this->esc($this->schema_texts()->get_fd_message('no_dd_assigned'));
            $data['data']['edit_data_dBx'] = '';
            $data['data']['class'] = trim((string)($data['data']['class'] ?? '') . ' disabled text-muted');
         }

         return $data;
      }

      $show_url = $this->append_url_params($base, array(
         'dbx_do' => 'row_show',
         'rid'    => $rid,
      ));
      $delete_url = $this->append_url_params($base, array(
         'dbx_do' => 'row_delete',
         'rid'    => $rid,
      ));

      $data['data']['show_url'] = $this->esc($show_url);
      $data['data']['delete_url'] = $this->esc($delete_url);

      return $data;
   }



   /**
    * Verarbeitet Zeilenaktionen des DD-Reports.
    *
    * @param string $message Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function handle_dd_row_action(&$message) {
      $do = dbx()->get_modul_var('dbx_do', '', 'parameter');
      if (!in_array($do, array('row_show', 'row_delete'), true)) {
         return '';
      }

      $rid = dbx()->get_modul_var('rid', '', 'parameter');
      [$modul, $dd] = $this->split_dd_rid($rid);
      if (!$modul || !$dd) {
         return '<div class="alert alert-danger">DD-Auswahl ist ungueltig.</div>';
      }

      $dd_ref = $this->dd_ref($modul, $dd);

      if ($do == 'row_show') {
         $params = array('rid' => $rid);
         return $this->run_data_report(
            $dd_ref,
            'DD Daten: ' . $modul . '|' . $dd,
            $this->build_url('dd', 'data_read', $params),
            $this->build_url('dd', 'data_save', $params),
            $this->build_url('dd', 'data_insert', $params),
            $this->build_url('dd', 'data_delete', $params)
         );
      }

      if ($this->delete_dd_file($modul, $dd)) {
         $message = 'DD-Datei geloescht: ' . $modul . '|' . $dd;
      } else {
         $message = 'DD-Datei konnte nicht geloescht werden: ' . $modul . '|' . $dd;
      }

      return '';
   }



   /**
    * Verarbeitet Zeilenaktionen des DB-Reports.
    *
    * @param string $message Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function handle_db_row_action(&$message) {
      $do = dbx()->get_modul_var('dbx_do', '', 'parameter');
      if (!in_array($do, array('row_show', 'row_delete'), true)) {
         return '';
      }

      $rid = dbx()->get_modul_var('rid', '', 'parameter');
      [$server, $table] = $this->decode_db_rid($rid);
      if (!$server || !$table) {
         return '<div class="alert alert-danger">DB-Auswahl ist ungueltig.</div>';
      }

      if ($do == 'row_show') {
         $dd_ref = $this->ensure_db_view_dd($server, $table);
         if (!$dd_ref) {
            return '<div class="alert alert-danger">Ansichts-DD konnte nicht erzeugt werden.</div>';
         }

          $params = array('rid' => $rid);
          return $this->run_data_report(
             $dd_ref,
             'DB Daten: ' . $server . '|' . $table,
             $this->build_url('db', 'data_read', $params),
             $this->build_url('db', 'data_save', $params),
             $this->build_url('db', 'data_insert', $params),
             $this->build_url('db', 'data_delete', $params)
          );
      }

      $o_dd = dbx()->get_system_obj('dbxDD');
      if ($o_dd->drop_db_tab($server, $table)) {
         $message = 'DB-Tabelle geloescht: ' . $server . '|' . $table;
      } else {
         $message = 'DB-Tabelle konnte nicht geloescht werden: ' . $server . '|' . $table;
      }

      return '';
   }



   /**
    * Rendert die DD-Uebersicht.
    *
    * @return string
    */
   private function report_dd() {
      $texts = $this->schema_texts();
      $message = '';
      $content = $this->handle_dd_row_action($message);
      if ($content !== '') {
         return $content;
      }

      $flds = array(
         'modul'     => $texts->get_fd_message('column_module'),
         'dd'        => $texts->get_fd_message('column_dd'),
         'sync'      => $texts->get_fd_message('column_status'),
         'sync_info' => $texts->get_fd_message('column_details'),
         'server'    => $texts->get_fd_message('column_server'),
         'database'  => $texts->get_fd_message('column_database'),
         'table'     => $texts->get_fd_message('column_table'),
         'dd_fields' => $texts->get_fd_message('column_dd_fields'),
         'db_fields' => $texts->get_fd_message('column_db_fields'),
         'count'     => $texts->get_fd_message('column_records'),
         'act_sync'  => $this->header_icon('bi bi-arrow-repeat', $texts->get_fd_message('action_dd_to_db')),
         'act_map'   => $this->header_icon('bi bi-diagram-3', $texts->get_fd_message('action_mapping')),
         'act_move'  => $this->header_icon('bi bi-box-arrow-right', $texts->get_fd_message('action_transfer')),
         'act_flds'  => $this->header_icon('bi bi-list-columns', $texts->get_fd_message('action_dd_fields')),
      );

      $batch = array(
         '0'                    => $texts->get_fd_message('batch_placeholder'),
         'batch_dd_to_db'       => $texts->get_fd_message('batch_dd_to_db'),
         'batch_dd_to_db_force' => $texts->get_fd_message('batch_dd_to_db_force'),
      );

      return $this->run_schema_report(
         'dd',
         $this->get_dd_records(),
         $flds,
         $this->build_url('dd', 'list_dd'),
         $batch,
         $message
      );
   }


   /**
    * Rendert die DB-/Tabellen-Uebersicht.
    *
    * @return string
    */
   private function report_db() {
      $texts = $this->schema_texts();
      $message = '';
      $content = $this->handle_db_row_action($message);
      if ($content !== '') {
         return $content;
      }

      $flds = array(
         'modul'     => $texts->get_fd_message('column_module'),
         'server'    => $texts->get_fd_message('column_server'),
         'table'     => $texts->get_fd_message('column_table'),
         'db_fields' => $texts->get_fd_message('column_db_fields'),
         'dd_fields' => $texts->get_fd_message('column_dd_fields'),
         'count'     => $texts->get_fd_message('column_records'),
         'dd'        => $texts->get_fd_message('column_dd'),
         'sync'      => $texts->get_fd_message('column_status'),
         'sync_info' => $texts->get_fd_message('column_details'),
         'act_sync'  => $this->header_icon('bi bi-arrow-down-up', $texts->get_fd_message('action_db_to_dd')),
         'act_dd_sync' => $this->header_icon('bi bi-arrow-repeat', $texts->get_fd_message('action_db_from_dd')),
         'act_map'   => $this->header_icon('bi bi-diagram-3', $texts->get_fd_message('action_mapping')),
         'act_move'  => $this->header_icon('bi bi-box-arrow-right', $texts->get_fd_message('action_transfer')),
      );

      $batch = array(
         '0'                    => $texts->get_fd_message('batch_placeholder'),
         'batch_db_to_dd'       => $texts->get_fd_message('batch_db_to_dd'),
         'batch_dd_to_db'       => $texts->get_fd_message('batch_dd_to_db'),
         'batch_dd_to_db_force' => $texts->get_fd_message('batch_dd_to_db_force'),
      );

      return $this->run_schema_report('db', $this->get_db_records($this->get_dd_records()), $flds, $this->build_url('db', 'list_db'), $batch, $message);
   }
}
