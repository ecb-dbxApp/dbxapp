<?php
namespace dbx\dbxAdmin;

trait dbxSchemaBatchServiceTrait {



   /**
    * Erzeugt die Bezeichnung eines Prozessstatus.
    *
    * @param string $status Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function process_status_label($status) {
      $map = array(
         'new'      => 'Neu',
         'reset'    => 'Neu',
         'running'  => 'Laeuft',
         'paused'   => 'Angehalten',
         'canceled' => 'Abgebrochen',
         'finished' => 'Fertig',
         'error'    => 'Fehler',
      );

      return $map[$status] ?? $status;
   }



   /**
    * Erzeugt die CSS-Klasse fuer einen Prozessstatus.
    *
    * @param string $status Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function process_status_class($status) {
      $map = array(
         'running'  => 'bg-primary',
         'paused'   => 'bg-warning text-dark',
         'canceled' => 'bg-danger',
         'finished' => 'bg-success',
         'error'    => 'bg-danger',
         'new'      => 'bg-secondary',
         'reset'    => 'bg-secondary',
      );

      return $map[$status] ?? 'bg-secondary';
   }



   /**
    * Erzeugt das Icon fuer einen Prozessstatus.
    *
    * @param string $status Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function process_status_icon($status) {
      $map = array(
         'running'  => 'bi bi-play-fill',
         'paused'   => 'bi bi-pause-fill',
         'canceled' => 'bi bi-x-lg',
         'finished' => 'bi bi-check-lg',
         'error'    => 'bi bi-exclamation-triangle',
         'new'      => 'bi bi-circle',
         'reset'    => 'bi bi-arrow-clockwise',
      );

      return $map[$status] ?? 'bi bi-circle';
   }



   /**
    * Erzeugt die Bezeichnung eines Prozesstyps.
    *
    * @param string $type Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function process_type_label($type) {
      $map = array(
         'sync_dd_to_db' => 'DD -> DB',
         'sync_db_to_dd' => 'DB -> DD',
         'transfer_table' => 'Transfer',
         'backup' => 'Backup',
         'restore' => 'Restore',
         'schema_batch' => 'Batch',
      );

      return $map[$type] ?? ($type ?: 'Prozess');
   }



   /**
    * Erzeugt die Bezeichnung einer Prozessaufgabe.
    *
    * @param string $phase Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function process_task_label($phase) {
      $phase = (string)$phase;

      if (strpos($phase, 'backup') !== false) return 'Aufgabe Backup';
      if (strpos($phase, 'restore') !== false) return 'Aufgabe Restore';
      if (strpos($phase, 'create') !== false || strpos($phase, 'rename') !== false || strpos($phase, 'drop') !== false) return 'Aufgabe Struktur';
      if (strpos($phase, 'add_') === 0) return 'Aufgabe Schema';
      if (strpos($phase, 'read') !== false || strpos($phase, 'merge') !== false || strpos($phase, 'write') !== false) return 'Aufgabe DD';
      if (strpos($phase, 'prepare') !== false) return 'Aufgabe Vorbereitung';
      if (strpos($phase, 'batch') !== false) return 'Aufgabe Batch';

      return 'Aufgabe';
   }



   /**
    * Erzeugt die Bezeichnung eines Prozessschritts.
    *
    * @param string $phase Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function process_step_label($phase) {
      $map = array(
         'prepare'        => 'Vorbereiten',
         'prepare_target' => 'Ziel vorbereiten',
         'create_table'   => 'Tabelle anlegen',
         'add_fields'     => 'Felder ergaenzen',
         'add_indexes'    => 'Indizes ergaenzen',
         'backup_old'     => 'Backup erstellen',
         'backup_source'  => 'Quelle sichern',
         'rename_old'     => 'Alte Tabelle umbenennen',
         'create_new'     => 'Neue Tabelle anlegen',
         'restore_new'    => 'Daten einlesen',
         'restore_target' => 'Ziel einlesen',
         'drop_old'       => 'Alte Tabelle entfernen',
         'read_schema'    => 'Schema lesen',
         'merge_meta'     => 'DD zusammenfuehren',
         'write_dd'       => 'DD schreiben',
         'batch_step'     => 'Eintrag bearbeiten',
      );

      return $map[$phase] ?? ($phase ?: 'Schritt');
   }



   /**
    * Bereitet eine Prozessmeldung fuer die Anzeige auf.
    *
    * @param string $message Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function process_message_label($message) {
      $message = (string)$message;
      $map = array(
         'process paused' => 'Prozess angehalten',
         'process resumed' => 'Prozess fortgesetzt',
         'process continued' => 'Prozess fortgesetzt',
         'process canceled' => 'Prozess abgebrochen',
         'process restarted' => 'Prozess neu gestartet',
         'sync initialized' => 'Synchronisierung vorbereitet',
         'transfer initialized' => 'Transfer vorbereitet',
         'schema loaded' => 'Schema gelesen',
         'meta merged' => 'DD-Informationen zusammengefuehrt',
         'sync dd -> db finished' => 'DD -> DB abgeschlossen',
         'sync dd -> db rebuild finished' => 'DD -> DB Rebuild abgeschlossen',
         'sync db -> dd finished' => 'DB -> DD abgeschlossen',
         'transfer finished' => 'Transfer abgeschlossen',
         'batch initialized' => 'Batch vorbereitet',
         'batch finished' => 'Batch abgeschlossen',
         'backup finished' => 'Backup abgeschlossen',
         'restore finished' => 'Restore abgeschlossen',
      );

      return $map[$message] ?? $message;
   }



   /**
    * Rendert die Prozessanzeige fuer Sync, Transfer, Backup, Restore und Batch.
    *
    * @param string $title Eingabeparameter fuer diese Methode.
    * @param array $state Eingabeparameter fuer diese Methode.
    * @param string $nextUrl Eingabeparameter fuer diese Methode.
    * @param string $backUrl Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function render_process($title, $state, $nextUrl = '', $backUrl = '') {
      $oTPL = dbx()->get_system_obj('dbxTPL');
      $status = $state['status'] ?? 'running';
      $percent = (int)($state['percent'] ?? 0);
      if ($percent < 0) $percent = 0;
      if ($percent > 100) $percent = 100;

      $stepPercent = (int)($state['step_percent'] ?? $percent);
      if ($stepPercent < 0) $stepPercent = 0;
      if ($stepPercent > 100) $stepPercent = 100;

      $barClass = 'bg-primary';
      if ($status == 'finished') $barClass = 'bg-success';
      if ($status == 'error' || $status == 'canceled') $barClass = 'bg-danger';
      if ($status == 'paused') $barClass = 'bg-warning';

      $restartUrl  = $nextUrl ? $this->append_url_params($nextUrl, array('reset' => 1, 'proc_cmd' => 'restart')) : '';
      $targetId    = 'dbx_process_' . substr(md5((string)($state['proc_key'] ?? $title)), 0, 14);
      $phase       = (string)($state['phase'] ?? '');
      $procType    = (string)($state['proc_type'] ?? ($state['act'] ?? 'schema_batch'));
      $hasControls = $nextUrl ? 1 : 0;
      $autostart   = ($nextUrl && $status == 'running') ? 1 : 0;

      $data = array(
         'target_id'        => $this->esc($targetId),
         'title'            => $this->esc($title),
         'status_key'       => $this->esc($status),
         'status_label'     => $this->esc($this->process_status_label($status)),
         'status_class'     => $this->esc($this->process_status_class($status)),
         'status_icon'      => $this->esc($this->process_status_icon($status)),
         'message'          => $this->esc($this->process_message_label($state['message'] ?? '')),
         'percent'          => $percent,
         'step_percent'     => $stepPercent,
         'bar_class'        => $this->esc($barClass),
         'task_label'       => $this->esc($this->process_task_label($phase)),
         'step_label'       => $this->esc($this->process_step_label($phase)),
         'process_label'    => $this->esc($this->process_type_label($procType)),
         'updated_at'       => $this->esc($state['updated_at'] ?? ''),
         'next_url'         => $this->esc($nextUrl),
         'pause_url'        => $this->esc($this->append_url_params($nextUrl, array('proc_cmd' => 'pause'))),
         'resume_url'       => $this->esc($this->append_url_params($nextUrl, array('proc_cmd' => 'resume'))),
         'continue_url'     => $this->esc($this->append_url_params($nextUrl, array('proc_cmd' => 'continue'))),
         'cancel_url'       => $this->esc($this->append_url_params($nextUrl, array('proc_cmd' => 'cancel'))),
         'restart_url'      => $this->esc($restartUrl),
         'autostart'        => $autostart,
         'interval'         => 800,
         'pause_visible'    => $hasControls ? 'running' : '_none',
         'resume_visible'   => $hasControls ? 'paused' : '_none',
         'continue_visible' => $hasControls ? 'canceled' : '_none',
         'restart_visible'  => $restartUrl ? 'paused,canceled,error,finished' : '_none',
         'cancel_visible'   => $hasControls ? 'running,paused' : '_none',
         'back_url'         => $this->esc($backUrl),
      );

      return $oTPL->get_tpl('dbxAdmin|schema-process', $data);
   }



   /**
    * Initialisiert einen Schema-Batch und startet die erste Ausfuehrung.
    *
    * @param string $act Eingabeparameter fuer diese Methode.
    * @param array $selects Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function start_batch($act, $selects) {
      $oDD = dbx()->get_system_obj('dbxDD');

      foreach ($selects as $selected) {
         if ($act == 'batch_dd_to_db' || $act == 'batch_dd_to_db_force') {
            $parts = $this->selected_dd_parts($selected);
            if (count($parts) != 2) {
               continue;
            }

            $oDD->sync_dd_to_db($parts[0], $parts[1], 'reset');
         }

         if ($act == 'batch_db_to_dd') {
            $parts = $this->decode_db_rid($selected);
            if (!$parts[0] || !$parts[1]) {
               continue;
            }

            $ddIndex = $this->get_dd_index_by_db($this->get_dd_records());
            $dds = $this->get_dd_records_for_db($ddIndex, $parts[0], $parts[1]);

            if ($dds) {
               $oDD->sync_db_to_dd($dds[0]['modul'], $dds[0]['dd'], 'reset', $parts[0], $parts[1]);
            } else {
               $oDD->sync_db_to_dd('dbx', $this->sanitize_dd_name($parts[1]), 'reset', $parts[0], $parts[1]);
            }
         }
      }

      $back = 'db';
      if ($act == 'batch_backup_db') $back = 'backup';
      if ($act == 'batch_restore_latest_db') $back = 'restore';

      $state = array(
         'proc_type' => 'schema_batch',
         'proc_key'  => 'schema_batch',
         'act'      => $act,
         'selects'  => array_values($selects),
         'pos'      => 0,
         'status'   => 'running',
         'phase'    => 'batch_step',
         'message'  => 'batch initialized',
         'percent'  => 0,
         'step_percent' => 0,
         'back'     => $back,
      );

      dbx()->set_remember_var('schema_batch', $state, 'dbxAdmin');
      return $this->run_batch();
   }



   /**
    * Liefert Modul/DD fuer eine Batch-Auswahl aus DD- oder DB-Report-Zeilen.
    *
    * @param string $selected Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function selected_dd_parts($selected) {
      $selected = (string)$selected;

      if (str_starts_with($selected, 'db_')) {
         [$server, $table] = $this->decode_db_rid($selected);
         if (!$server || !$table) {
            return array();
         }

         $ddIndex = $this->get_dd_index_by_db($this->get_dd_records());
         $dds = $this->get_dd_records_for_db($ddIndex, $server, $table);
         if (!$dds) {
            return array();
         }

         return array($dds[0]['modul'], $dds[0]['dd']);
      }

      $parts = explode('|', $selected, 2);
      return (count($parts) == 2) ? $parts : array();
   }



   /**
    * Setzt die Kindprozesse eines Batchs zurueck.
    *
    * @param array $state Eingabeparameter fuer diese Methode.
    * @return void
    */
   private function reset_batch_children($state) {
      if (!is_array($state) || empty($state['selects'])) {
         return;
      }

      $oDD = dbx()->get_system_obj('dbxDD');
      $act = $state['act'] ?? '';

      foreach ($state['selects'] as $selected) {
         if ($act == 'batch_dd_to_db' || $act == 'batch_dd_to_db_force') {
            $parts = $this->selected_dd_parts($selected);
            if (count($parts) != 2) {
               continue;
            }

            $oDD->sync_dd_to_db($parts[0], $parts[1], 'reset');
            continue;
         }

         if ($act == 'batch_db_to_dd') {
            $parts = $this->decode_db_rid($selected);
            if (!$parts[0] || !$parts[1]) {
               continue;
            }

            $ddIndex = $this->get_dd_index_by_db($this->get_dd_records());
            $dds = $this->get_dd_records_for_db($ddIndex, $parts[0], $parts[1]);
            $modul = $dds ? $dds[0]['modul'] : 'dbx';
            $dd = $dds ? $dds[0]['dd'] : $this->sanitize_dd_name($parts[1]);
            $oDD->sync_db_to_dd($modul, $dd, 'reset', $parts[0], $parts[1]);
         }
      }
   }



   /**
    * Erzeugt die Ruecksprung-URL fuer einen Batchprozess.
    *
    * @param string $backRun Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function batch_back_url($backRun) {
      if ($backRun == 'db') {
         return $this->build_url('db', 'list_db');
      }
      if ($backRun == 'backup') {
         return $this->build_url('dd', 'backup_db');
      }
      if ($backRun == 'restore') {
         return $this->build_url('dd', 'restore_db');
      }
      return $this->build_url('dd', 'list_dd');
   }



   /**
    * Verarbeitet Steuerbefehle fuer einen Batchprozess.
    *
    * @param array $state Eingabeparameter fuer diese Methode.
    * @param string $cmd Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function control_batch($state, $cmd) {
      $cmd = strtolower(trim((string)$cmd));
      $status = $state['status'] ?? 'running';

      if ($cmd == 'pause' && !in_array($status, array('finished', 'error', 'canceled'), true)) {
         $state['status'] = 'paused';
         $state['message'] = 'process paused';
      } elseif (($cmd == 'resume' || $cmd == 'continue') && in_array($status, array('paused', 'canceled'), true)) {
         $state['status'] = 'running';
         $state['message'] = ($cmd == 'continue') ? 'process continued' : 'process resumed';
      } elseif ($cmd == 'cancel' && !in_array($status, array('finished', 'error'), true)) {
         $state['status'] = 'canceled';
         $state['message'] = 'process canceled';
      } elseif ($cmd == 'restart') {
         $this->reset_batch_children($state);
         $state['pos'] = 0;
         $state['status'] = 'running';
         $state['phase'] = 'batch_step';
         $state['percent'] = 0;
         $state['step_percent'] = 0;
         $state['message'] = 'process restarted';
      }

      $state['updated_at'] = date('Y-m-d H:i:s');
      dbx()->set_remember_var('schema_batch', $state, 'dbxAdmin');
      return $state;
   }



   /**
    * Fuehrt den naechsten Schritt eines Schema-Batchprozesses aus.
    *
    * @return string
    */
   private function run_batch() {
      $state = dbx()->get_remember_var('schema_batch', array(), 'dbxAdmin');
      if (!is_array($state) || empty($state['selects'])) {
         return '<div class="alert alert-warning">Kein Batch aktiv.</div>';
      }

      $cmd = dbx()->get_modul_var('proc_cmd', '', 'parameter');
      if ($cmd && in_array($cmd, array('pause', 'resume', 'continue', 'cancel', 'restart'), true)) {
         $state = $this->control_batch($state, $cmd);
      }

      $total = count($state['selects']);
      $pos = (int)($state['pos'] ?? 0);
      $backRun = (string)($state['back'] ?? 'dd');
      if (!in_array($backRun, array('dd', 'db', 'backup', 'restore'), true)) {
         $backRun = 'dd';
      }
      $nextUrl = $this->build_url('dd', 'batch');
      $backUrl = $this->batch_back_url($backRun);

      if (in_array(($state['status'] ?? ''), array('paused', 'canceled', 'error'), true)) {
         return $this->render_process('Batch', $state, $nextUrl, $backUrl);
      }

      if ($pos >= $total) {
         $state['status'] = 'finished';
         $state['message'] = 'batch finished';
         $state['percent'] = 100;
         $state['step_percent'] = 100;
         dbx()->set_remember_var('schema_batch', array(), 'dbxAdmin');
         return $this->render_process('Batch', $state, '', $backUrl);
      }

      $current = $state['selects'][$pos];
      $oDD = dbx()->get_system_obj('dbxDD');
      $act = $state['act'] ?? '';

      $parts = in_array($act, array('batch_db_to_dd', 'batch_backup_db', 'batch_restore_latest_db'), true)
         ? $this->decode_db_rid($current)
         : $this->selected_dd_parts($current);

      if (($act == 'batch_dd_to_db' || $act == 'batch_dd_to_db_force') && count($parts) == 2) {
         $mode = ($act == 'batch_dd_to_db_force') ? 'force' : 'apply';
         $step = $oDD->sync_dd_to_db($parts[0], $parts[1], $mode);
         $state['step_percent'] = (int)($step['percent'] ?? 0);

         if (($step['status'] ?? '') == 'finished') {
            $state['pos'] = $pos + 1;
            $state['step_percent'] = 100;
         } elseif (($step['status'] ?? '') == 'error') {
            $state['status'] = 'error';
            $state['message'] = $current . ': ' . ($step['message'] ?? 'error');
         } else {
            $state['message'] = $current . ': ' . ($step['message'] ?? 'running');
         }
      } elseif ($act == 'batch_db_to_dd' && count($parts) == 2 && $parts[0] && $parts[1]) {
         $ddIndex = $this->get_dd_index_by_db($this->get_dd_records());
         $dds = $this->get_dd_records_for_db($ddIndex, $parts[0], $parts[1]);
         $modul = $dds ? $dds[0]['modul'] : 'dbx';
         $dd = $dds ? $dds[0]['dd'] : $this->sanitize_dd_name($parts[1]);
         $step = $oDD->sync_db_to_dd($modul, $dd, 'merge', $parts[0], $parts[1]);
         $state['step_percent'] = (int)($step['percent'] ?? 0);

         if (($step['status'] ?? '') == 'finished') {
            $state['pos'] = $pos + 1;
            $state['step_percent'] = 100;
         } elseif (($step['status'] ?? '') == 'error') {
            $state['status'] = 'error';
            $state['message'] = $current . ': ' . ($step['message'] ?? 'error');
         } else {
            $state['message'] = $current . ': ' . ($step['message'] ?? 'running');
         }
      } elseif ($act == 'batch_backup_db' && count($parts) == 2 && $parts[0] && $parts[1]) {
         $result = $this->backup_table($parts[0], $parts[1]);
         $state['step_percent'] = 100;
         if (!empty($result['ok'])) {
            $state['pos'] = $pos + 1;
            $state['message'] = $parts[0] . '|' . $parts[1] . ': Backup geschrieben';
         } else {
            $state['status'] = 'error';
            $state['message'] = $parts[0] . '|' . $parts[1] . ': ' . ($result['msg'] ?? 'backup error');
         }
      } elseif ($act == 'batch_restore_latest_db' && count($parts) == 2 && $parts[0] && $parts[1]) {
         $latest = $this->latest_backup($parts[0], $parts[1]);
         $file = (string)($latest['_file'] ?? '');
         $result = $file ? $this->restore_table_from_backup($file) : array('ok' => 0, 'msg' => 'Kein Backup gefunden');
         $state['step_percent'] = 100;
         if (!empty($result['ok'])) {
            $state['pos'] = $pos + 1;
            $state['message'] = $parts[0] . '|' . $parts[1] . ': Restore abgeschlossen';
         } else {
            $state['status'] = 'error';
            $state['message'] = $parts[0] . '|' . $parts[1] . ': ' . ($result['msg'] ?? 'restore error');
         }
      } elseif (in_array($act, array('batch_db_to_dd', 'batch_backup_db', 'batch_restore_latest_db'), true) && count($parts) == 2 && $parts[0] && !$parts[1]) {
         $state['pos'] = $pos + 1;
         $state['step_percent'] = 100;
         $state['message'] = $parts[0] . ': keine Tabelle, Schritt uebersprungen';
      } else {
         $state['status'] = 'error';
         $state['message'] = 'ungueltige Batch-Aktion';
      }

      $done = min($total, (int)$state['pos']);
      $partial = (($state['status'] ?? '') == 'running') ? max(0, min(100, (int)($state['step_percent'] ?? 0))) / 100 : 0;
      $state['percent'] = (int)floor((min($total, $done + $partial) / max(1, $total)) * 100);
      $state['updated_at'] = date('Y-m-d H:i:s');
      dbx()->set_remember_var('schema_batch', $state, 'dbxAdmin');

      $next = (($state['status'] ?? '') == 'running') ? $nextUrl : '';
      return $this->render_process('Batch', $state, $next, $backUrl);
   }
}
