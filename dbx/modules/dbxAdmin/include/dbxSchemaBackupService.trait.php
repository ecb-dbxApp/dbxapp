<?php
namespace dbx\dbxAdmin;

trait dbxSchemaBackupServiceTrait {



   /**
    * Liefert und erstellt bei Bedarf den Backup-Ordner fuer Schema-Backups.
    *
    * @return string
    */
   private function backup_dir() {
      $dir = dbx()->os_path(dbx()->get_file_dir() . 'db/backup/');
      if (!is_dir($dir)) {
         @mkdir($dir, 0775, true);
      }
      return rtrim(str_replace('\\', '/', $dir), '/') . '/';
   }



   /**
    * Prueft und liefert den sicheren absoluten Pfad zu einer Backup-Datei.
    *
    * @param string $file Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function backup_file_path($file) {
      $file = basename((string)$file);
      if ($file === '' || !preg_match('/\.json$/i', $file)) {
         return '';
      }

      $path = $this->backup_dir() . $file;
      $realDir = realpath($this->backup_dir());
      $realFile = realpath($path);

      if (!$realDir || !$realFile) {
         return '';
      }

      $realDir = rtrim(str_replace('\\', '/', $realDir), '/') . '/';
      $realFile = str_replace('\\', '/', $realFile);

      return str_starts_with($realFile, $realDir) ? $realFile : '';
   }



   /**
    * Erzeugt den relativen Anzeige- und Meldungspfad einer Backup-Datei.
    *
    * @param string $file Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function backup_relative_file($file) {
      return 'files/db/backup/' . basename((string)$file);
   }



   /**
    * Erstellt ein JSON-Backup einer DB-Tabelle inklusive Feldern, Indizes und Daten.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @param string $table Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function backup_table($server, $table) {
      $texts = $this->schema_texts();
      $oDB = dbx()->get_system_obj('dbxDB');
      $oDD = dbx()->get_system_obj('dbxDD');

      if (!$server || !$table || !$oDD->get_table_exist($server, $table)) {
         return array('ok' => 0, 'msg' => $texts->get_fd_message('backup_table_not_found'));
      }

      $fields = $oDD->get_db_fields($server, $table);
      if (!$fields) {
         return array('ok' => 0, 'msg' => $texts->get_fd_message('backup_fields_read_error'));
      }

      $indexes = $oDD->get_db_indexes($server, $table);
      $rows = $oDB->rawQuery($server, 'SELECT * FROM ' . $this->quote_db_ident($server, $table));
      if (!is_array($rows)) {
         return array('ok' => 0, 'msg' => $texts->get_fd_message('backup_data_read_error'));
      }

      $created = date('Ymd-His');
      $file = $created . '__' . $this->backup_name_part($server) . '__' . $this->backup_name_part($table) . '.json';
      $path = $this->backup_dir() . $file;
      $tmp = $path . '.tmp';

      $payload = array(
         'version' => 1,
         'created_at' => date('Y-m-d H:i:s'),
         'server' => $server,
         'table' => $table,
         'db_type' => $oDB->get_db_type($server),
         'count' => count($rows),
         'fields' => $fields,
         'indexes' => $indexes,
         'rows' => $rows,
      );

      $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
      if ($json === false || file_put_contents($tmp, $json) === false || !@rename($tmp, $path)) {
         @unlink($tmp);
         return array('ok' => 0, 'msg' => $texts->get_fd_message('backup_write_error'));
      }

      return array('ok' => 1, 'file' => $file, 'path' => $path, 'count' => count($rows));
   }



   /**
    * Liest Metadaten und Inhalt einer Backup-Datei.
    *
    * @param string $file Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function backup_meta($file) {
      $path = $this->backup_file_path($file);
      if (!$path) {
         return array();
      }

      $raw = file_get_contents($path);
      $data = $raw !== false ? json_decode($raw, true) : null;
      if (!is_array($data)) {
         return array();
      }

      $data['_file'] = basename($path);
      $data['_path'] = $path;
      return $data;
   }



   /**
    * Liefert alle Backups fuer Server und Tabelle.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @param string $table Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function table_backups($server, $table) {
      $items = array();
      foreach (glob($this->backup_dir() . '*.json') as $file) {
         $meta = $this->backup_meta(basename($file));
         if (($meta['server'] ?? '') === $server && ($meta['table'] ?? '') === $table) {
            $items[] = $meta;
         }
      }

      usort($items, function($a, $b) {
         return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
      });

      return $items;
   }



   /**
    * Liefert das neueste Backup fuer Server und Tabelle.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @param string $table Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function latest_backup($server, $table) {
      $items = $this->table_backups($server, $table);
      return $items[0] ?? array();
   }



   /**
    * Erzeugt die Anzeige des letzten Backups fuer eine Tabelle.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @param string $table Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function backup_label($server, $table) {
      $texts = $this->schema_texts();
      $meta = $this->latest_backup($server, $table);
      if (!$meta) {
         return $this->badge($texts->get_fd_message('no_backup'), 'secondary');
      }

      $created = (string)($meta['created_at'] ?? '');
      $count = (int)($meta['count'] ?? 0);
      return $this->esc(
         $created . ' / ' . $texts->format_fd_message('record_count_short', array('count' => $count))
      );
   }



   /**
    * Stellt eine Tabelle aus einer Backup-Datei wieder her.
    *
    * @param string $file Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function restore_table_from_backup($file) {
      $texts = $this->schema_texts();
      $meta = $this->backup_meta($file);
      if (!$meta) {
         return array('ok' => 0, 'msg' => $texts->get_fd_message('backup_invalid_file'));
      }

      $server = (string)($meta['server'] ?? '');
      $table = (string)($meta['table'] ?? '');
      $fields = is_array($meta['fields'] ?? null) ? $meta['fields'] : array();
      $indexes = is_array($meta['indexes'] ?? null) ? $meta['indexes'] : array();
      $rows = is_array($meta['rows'] ?? null) ? $meta['rows'] : array();

      if (!$server || !$table || !$fields) {
         return array('ok' => 0, 'msg' => $texts->get_fd_message('backup_incomplete_metadata'));
      }

      $oDB = dbx()->get_system_obj('dbxDB');
      $oDD = dbx()->get_system_obj('dbxDD');

      $oDD->drop_db_tab($server, $table);
      if (!$oDD->create_db_tab_from_fields($server, $table, $fields, $indexes)) {
         return array('ok' => 0, 'msg' => $texts->get_fd_message('restore_create_table_error'));
      }

      if (!$rows) {
         return array('ok' => 1, 'count' => 0, 'file' => basename((string)$file));
      }

      $names = array();
      foreach ($fields as $field) {
         $name = (string)($field['name'] ?? '');
         if ($name !== '') {
            $names[] = $name;
         }
      }

      if (!$names) {
         return array('ok' => 0, 'msg' => $texts->get_fd_message('restore_no_target_fields'));
      }

      $qNames = array_map(function($name) use ($server) {
         return $this->quote_db_ident($server, $name);
      }, $names);

      foreach ($rows as $row) {
         $values = array();
         foreach ($names as $name) {
            $values[] = $this->sql_db_value($server, is_array($row) && array_key_exists($name, $row) ? $row[$name] : null);
         }

         $sql = 'INSERT INTO ' . $this->quote_db_ident($server, $table)
              . ' (' . implode(',', $qNames) . ') VALUES (' . implode(',', $values) . ')';
         $ok = $oDB->rawQuery($server, $sql);
         if (!is_array($ok) && (int)$ok === 0 && $oDB->get_error_status() !== '') {
            return array('ok' => 0, 'msg' => 'Daten konnten nicht eingetragen werden.');
         }
      }

      return array('ok' => 1, 'count' => count($rows), 'file' => basename((string)$file));
   }


   /**
    * Erweitert DB-Report-Zeilen um Backup-/Restore-Informationen.
    *
    * @return array
    */
   private function backup_restore_rows() {
      $rows = $this->get_db_records($this->get_dd_records());
      $texts = $this->schema_texts();

      foreach ($rows as $no => $row) {
         [$server, $table] = $this->decode_db_rid((string)($row['rid'] ?? ''));

         if (!$server) {
            $server = (string)($row['server'] ?? '');
         }

         if (!$table) {
            $rows[$no]['backup'] = $this->badge($texts->get_fd_message('no_table'), 'secondary');
            $rows[$no]['act_backup'] = '';
            $rows[$no]['act_restore'] = '';
            continue;
         }

         $rows[$no]['backup'] = $this->backup_label($server, $table);

         $backupUrl = $this->build_url('dd', 'backup_db_table', array(
            'server' => $server,
            'table' => $table,
            'reset' => 1,
         ));
         $rows[$no]['act_backup'] = $this->openwin(
            $backupUrl,
            'bi bi-download',
            $texts->get_fd_message('action_backup_create'),
            980,
            700
         );

         $restoreUrl = $this->build_url('dd', 'restore_db_table', array(
            'server' => $server,
            'table' => $table,
         ));
         $rows[$no]['act_restore'] = $this->openwin(
            $restoreUrl,
            'bi bi-upload',
            $texts->get_fd_message('action_restore_select'),
            980,
            760
         );
      }

      return $rows;
   }



   /**
    * Rendert die Backup-Uebersicht.
    *
    * @return string
    */
   private function report_backup_db() {
      $texts = $this->schema_texts();
      $flds = array(
         'modul'    => $texts->get_fd_message('column_module'),
         'server'   => $texts->get_fd_message('column_server'),
         'database' => $texts->get_fd_message('column_database'),
         'table'    => $texts->get_fd_message('column_table'),
         'count'    => $texts->get_fd_message('column_records'),
         'path'     => $texts->get_fd_message('column_path'),
         'backup'   => $texts->get_fd_message('column_last_backup'),
         'act_backup' => $this->header_icon('bi bi-download', $texts->get_fd_message('action_backup_create')),
      );

      $batch = array(
         '0' => $texts->get_fd_message('batch_placeholder'),
         'batch_backup_db' => $texts->get_fd_message('batch_backup_selected'),
      );

      return $this->run_schema_report('backup', $this->backup_restore_rows(), $flds, $this->build_url('dd', 'backup_db'), $batch, '');
   }



   /**
    * Rendert die Restore-Uebersicht.
    *
    * @return string
    */
   private function report_restore_db() {
      $texts = $this->schema_texts();
      $flds = array(
         'modul'    => $texts->get_fd_message('column_module'),
         'server'   => $texts->get_fd_message('column_server'),
         'database' => $texts->get_fd_message('column_database'),
         'table'    => $texts->get_fd_message('column_table'),
         'count'    => $texts->get_fd_message('column_records'),
         'path'     => $texts->get_fd_message('column_path'),
         'backup'   => $texts->get_fd_message('column_last_backup'),
         'act_restore' => $this->header_icon('bi bi-upload', $texts->get_fd_message('action_restore_choose')),
      );

      $batch = array(
         '0' => $texts->get_fd_message('batch_placeholder'),
         'batch_restore_latest_db' => $texts->get_fd_message('batch_restore_selected'),
      );

      return $this->run_schema_report('restore', $this->backup_restore_rows(), $flds, $this->build_url('dd', 'restore_db'), $batch, '');
   }



   /**
    * Fuehrt ein Backup fuer eine einzelne Tabelle aus.
    *
    * @return string
    */
   private function run_backup_db_table() {
      $texts = $this->schema_texts();
      $server = dbx()->get_modul_var('server', '', 'parameter');
      $table = dbx()->get_modul_var('table', '', 'parameter');
      if (!$server || !$table) {
         return '<div class="alert alert-danger">' . $this->esc($texts->get_fd_message('missing_server_or_table')) . '</div>';
      }

      $result = $this->backup_table($server, $table);
      $ok = !empty($result['ok']);
      $state = array(
         'proc_type' => 'backup',
         'proc_key' => 'backup_' . $server . '_' . $table,
         'status' => $ok ? 'finished' : 'error',
         'phase' => 'backup_source',
         'message' => $ok ? 'backup finished' : ($result['msg'] ?? 'backup error'),
         'percent' => $ok ? 100 : 0,
         'step_percent' => $ok ? 100 : 0,
         'updated_at' => date('Y-m-d H:i:s'),
      );

      if ($ok) {
         $state['message'] = 'backup finished: ' . $this->backup_relative_file($result['file'] ?? '');
      }

      return $this->render_process('Backup: ' . $server . '|' . $table, $state, '', $this->build_url('dd', 'backup_db'));
   }



   /**
    * Rendert die Auswahl vorhandener Backups fuer Restore.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @param string $table Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function form_restore_db_table($server, $table) {
      $texts = $this->schema_texts();
      $backups = $this->table_backups($server, $table);
      if (!$backups) {
         return '<div class="alert alert-warning">' . $this->esc(
            $texts->format_fd_message('no_backup_for_table', array('table' => $server . '|' . $table))
         ) . '</div>';
      }

      $options = array('latest' => $texts->get_fd_message('latest_backup'));
      foreach ($backups as $backup) {
         $file = (string)($backup['_file'] ?? '');
         if ($file === '') {
            continue;
         }
         $options[$file] = (string)($backup['created_at'] ?? $file) . ' / '
            . $texts->format_fd_message('record_count_short', array('count' => (int)($backup['count'] ?? 0)));
      }

      $oForm = dbx()->get_system_obj('dbxForm');
      $oForm->init('form-schema-restore');
      $oForm->_fd = 'dbxAdmin|schema-report';
      $oForm->load_fd_messages();
      $oForm->_data = array(
         'server' => $server,
         'table' => $table,
         'backup_file' => 'latest',
      );
      $oForm->_action = $this->build_url('dd', 'restore_db_table', array(
         'server' => $server,
         'table' => $table,
      ));
      $oForm->_msg_info = $texts->get_fd_message('restore_warning');

      $oForm->add_fld('server', 'text-label', label: $texts->get_fd_message('column_server'), rules: 'parameter');
      $oForm->add_fld('table', 'text-label', label: $texts->get_fd_message('column_table'), rules: 'parameter');
      $oForm->add_fld('backup_file', 'select-single-label', label: $texts->get_fd_message('label_backup'), rules: 'parameter+.-_', options: $options);
      $oForm->add_obj('restore_submit', 'dbx|button-submit', data: 'label=' . $texts->get_fd_message('action_restore_start'));

      if ($oForm->submit() && !$oForm->errors()) {
         dbx()->set_modul_var('backup_file', $oForm->get_post('backup_file', 'latest', 'parameter+.-_'));
         dbx()->set_modul_var('start', 1);
         return $this->run_restore_db_table();
      }

      return $oForm->run();
   }



   /**
    * Fuehrt den Restore einer Tabelle aus einer Backup-Datei aus.
    *
    * @return string
    */
   private function run_restore_db_table() {
      $texts = $this->schema_texts();
      $server = dbx()->get_modul_var('server', '', 'parameter');
      $table = dbx()->get_modul_var('table', '', 'parameter');
      $start = dbx()->get_modul_var('start', 0, 'int');
      if (!$server || !$table) {
         return '<div class="alert alert-danger">' . $this->esc($texts->get_fd_message('missing_server_or_table')) . '</div>';
      }

      if (!$start) {
         return $this->form_restore_db_table($server, $table);
      }

      $file = dbx()->get_modul_var('backup_file', 'latest', 'parameter+.-_');
      if ($file === 'latest') {
         $latest = $this->latest_backup($server, $table);
         $file = (string)($latest['_file'] ?? '');
      }

      $result = $file
         ? $this->restore_table_from_backup($file)
         : array('ok' => 0, 'msg' => $texts->get_fd_message('no_backup_found'));
      $ok = !empty($result['ok']);
      $state = array(
         'proc_type' => 'restore',
         'proc_key' => 'restore_' . $server . '_' . $table,
         'status' => $ok ? 'finished' : 'error',
         'phase' => 'restore_target',
         'message' => $ok ? 'restore finished' : ($result['msg'] ?? 'restore error'),
         'percent' => $ok ? 100 : 0,
         'step_percent' => $ok ? 100 : 0,
         'updated_at' => date('Y-m-d H:i:s'),
      );

      if ($ok) {
         $state['message'] = 'restore finished: ' . $this->backup_relative_file($result['file'] ?? $file);
      }

      return $this->render_process('Restore: ' . $server . '|' . $table, $state, '', $this->build_url('dd', 'restore_db'));
   }
}
