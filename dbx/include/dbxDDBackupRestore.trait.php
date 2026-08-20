<?php

/** Automatisch aus der stabilen Legacy-Fassade extrahierte interne Verantwortung. */
trait dbxDDBackupRestoreTrait
{
/* =====================================================
     * BACKUP
     * ===================================================== */

    /**
     * Baut einen Backup-Dateinamen.
     *
     * @param string $server Servername
     * @param string $table Tabellenname
     * @param bool   $zip Zip-Flag
     *
     * @return string Absoluter Backup-Dateiname
     */
    protected function build_backup_file_name(string $server, string $table, bool $zip = false): string
    {
        $dir = dbx()->os_path(dbx()->get_file_dir() . 'db/dd-backup/');
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $name = $table . '_' . date('Ymd_His') . '.ddb';
        if ($zip) {
            $name .= '.zip';
        }

        return $dir . $name;
    }

/**
     * Finalisiert eine Backup-Datei optional als ZIP.
     *
     * @param string $tmpFile Temporäre Datei
     * @param string $finalFile Zieldatei
     * @param bool   $zip Zip-Flag
     *
     * @return int 1 bei Erfolg, sonst 0
     */
    protected function finalize_backup_file(string $tmp_file, string $final_file, bool $zip): int
    {
        if (!$zip) {
            @unlink($final_file);
            return @rename($tmp_file, $final_file) ? 1 : 0;
        }

        if (!class_exists('ZipArchive')) {
            $raw = preg_replace('/\.zip$/i', '', $final_file);
            @unlink($raw);
            return @rename($tmp_file, $raw) ? 1 : 0;
        }

        $zip_obj = new ZipArchive();
        if ($zip_obj->open($final_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return 0;
        }

        $inner = basename(preg_replace('/\.zip$/i', '', $final_file));
        $zip_obj->addFile($tmp_file, $inner);
        $zip_obj->close();

        @unlink($tmp_file);
        return 1;
    }

/**
     * Erstellt ein tabellenbasiertes Datenbackup.
     *
     * Format:
     * - kompakter JSON-Zeilenexport
     * - erste Zeilen enthalten Metadaten und Spaltenliste
     * - danach Records chunkweise
     *
     * Hinweis:
     * - bewusst kein SQL-INSERT-pro-Zeile-Backup
     * - für große Tabellen stepweise ausführbar
     *
     * @param string $server Servername
     * @param string $table Tabellenname
     * @param string $file Zieldatei
     * @param int    $zip Zip-Flag 0|1
     *
     * @return array Prozessstatus
     */
    public function backup($server, $table, $file = '', $zip = 0): array
    {
        $key   = $this->proc_key('backup', [$server, $table, $file, $zip]);
        $state = $this->get_proc_state($key);

        if ($state && $this->proc_is_waiting($state)) {
            return $this->proc_response($state);
        }

        if (!$state) {
            if (!$server || !$table) {
                return $this->proc_error(['proc_key' => $key], 'server or table missing');
            }

            if (!$this->get_table_exist($server, $table)) {
                return $this->proc_error(['proc_key' => $key], 'table not found');
            }

            $fields = $this->get_db_fields($server, $table);
            if (!$fields) {
                return $this->proc_error(['proc_key' => $key], 'no fields found');
            }

            $columns = [];
            $field_types = [];
            $pk      = '';
            foreach ($fields as $f) {
                $columns[] = $f['name'];
                $field_types[$f['name']] = strtolower((string)($f['type'] ?? ''));
                if (strtoupper((string)($f['index'] ?? '')) === 'PRI' && !$pk) {
                    $pk = $f['name'];
                }
            }

            $total = $this->count($table, '', $server);
            if ($total < 0) {
                return $this->proc_error(['proc_key' => $key], 'count failed');
            }

            if (!$file) {
                $file = $this->build_backup_file_name($server, $table, (bool)$zip);
            }

            $tmp_file = $file . '.part';
            @unlink($tmp_file);

            $meta = [
                'server'      => $server,
                'table'       => $table,
                'db_type'     => $this->get_db_type($server),
                'backup_date' => date('Y-m-d H:i:s'),
                'row_count'   => $total,
                'compact'     => 1,
                'zip'         => $zip ? 1 : 0,
            ];

            file_put_contents($tmp_file, json_encode(['meta' => $meta], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
            file_put_contents($tmp_file, json_encode([
                'columns' => $columns,
                'field_types' => $field_types,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);

            $state = $this->init_proc_state('backup', $key, [
                'server'   => $server,
                'table'    => $table,
                'file'     => $file,
                'tmp_file' => $tmp_file,
                'zip'      => $zip ? 1 : 0,
                'columns'  => $columns,
                'pk'       => $pk,
                'offset'   => 0,
                'done'     => 0,
                'total'    => $total,
                'message'  => 'backup initialized',
            ]);
        }

        $started = $this->step_start_time();

        while ($this->step_time_left($started, (float)$state['step_maxsec'])) {
            if ((int)$state['done'] >= (int)$state['total']) {
                $ok = $this->finalize_backup_file($state['tmp_file'], $state['file'], !empty($state['zip']));
                if (!$ok) {
                    $state = $this->proc_error($state, 'finalize backup failed');
                    $this->set_proc_state($key, $state);
                    return $state;
                }

                $state = $this->proc_finish($state, 'backup finished');
                $this->set_proc_state($key, $state);
                return $state;
            }

            $chunk = (int)$state['chunk_size'];
            $cols  = [];
            foreach ($state['columns'] as $col) {
                $cols[] = $this->quote_ident($state['server'], $col);
            }

            $order = '';
            if (!empty($state['pk'])) {
                $order = ' ORDER BY ' . $this->quote_ident($state['server'], $state['pk']);
            }

            $db_type = $this->get_db_type($state['server']);
            if ($db_type === 'mysql') {
                $limit = ' LIMIT ' . (int)$state['offset'] . ', ' . $chunk;
            } else {
                $limit = ' LIMIT ' . $chunk . ' OFFSET ' . (int)$state['offset'];
            }

            $sql  = 'SELECT ' . implode(',', $cols);
            $sql .= ' FROM ' . $this->quote_ident($state['server'], $state['table']);
            $sql .= $order . $limit;

            $rows = $this->raw_query($state['server'], $sql);
            if (!is_array($rows)) {
                $state = $this->proc_error($state, 'backup select failed');
                $this->set_proc_state($key, $state);
                return $state;
            }

            if (!$rows) {
                $state['done']    = $state['total'];
                $state['percent'] = 100;
                $state['step_percent'] = 100;
                continue;
            }

            $records = [];
            foreach ($rows as $row) {
                $rec = [];
                foreach ($state['columns'] as $col) {
                    $rec[] = $row[$col] ?? null;
                }
                $records[] = $rec;
            }

            file_put_contents(
                $state['tmp_file'],
                json_encode(['records' => $records], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
                FILE_APPEND
            );

            $count = count($rows);
            $state['offset']  += $count;
            $state['done']    += $count;
            $state['percent']  = ($state['total'] > 0) ? (int)floor(($state['done'] / $state['total']) * 100) : 100;
            $state['step_percent'] = $state['percent'];
            $state['message']  = 'backup rows ' . $state['done'] . ' / ' . $state['total'];
        }

        $this->set_proc_state($key, $state);
        return $this->proc_response($state);
    }

/* =====================================================
     * RESTORE
     * ===================================================== */

    /**
     * Bereitet eine Restore-Quelldatei vor.
     *
     * @param string $file Quelldatei
     * @param bool   $zip Zip-Flag
     *
     * @return string Lesbarer Quelldateipfad oder leer
     */
    protected function prepare_restore_source_file(string $file, bool $zip): string
    {
        if (!$zip) {
            return $file;
        }

        if (!class_exists('ZipArchive')) {
            $raw = preg_replace('/\.zip$/i', '', $file);
            return file_exists($raw) ? $raw : '';
        }

        $tmp_dir = dbx()->os_path(dbx()->get_file_dir() . 'temp/dd-restore/');
        if (!is_dir($tmp_dir)) {
            @mkdir($tmp_dir, 0777, true);
        }

        $tmp_file = $tmp_dir . md5($file) . '.ddb';

        if (file_exists($tmp_file)) {
            return $tmp_file;
        }

        $zip_obj = new ZipArchive();
        if ($zip_obj->open($file) !== true) {
            return '';
        }

        if ($zip_obj->numFiles < 1) {
            $zip_obj->close();
            return '';
        }

        $content = $zip_obj->getFromIndex(0);
        $zip_obj->close();

        if ($content === false) {
            return '';
        }

        file_put_contents($tmp_file, $content);
        return $tmp_file;
    }

/**
     * Stellt Daten aus einem kompakten Tabellenbackup wieder her.
     *
     * Mapping
     * -------
     * - Auto-Zuordnung erfolgt über identische Feldnamen
     * - zusätzlich kann ein externes Mapping übergeben werden
     * - dieses Mapping ist technische Infrastruktur, nicht Adminlogik
     *
     * Wichtiger Punkt
     * ---------------
     * Restore arbeitet tabellenbezogen und technisch.
     * Es nutzt bewusst keine DD-Workflowlogik wie Access/Trace/Validator.
     *
     * @param string $server Zielserver
     * @param string $table Zieltabelle
     * @param string $file Quelldatei
     * @param array  $mapping Optionales technisches Feldmapping
     * @param int    $zip Zip-Flag 0|1
     *
     * @return array Prozessstatus
     */
    public function restore($server, $table, $file, $mapping = [], $zip = 0): array
    {
        $key   = $this->proc_key('restore', [$server, $table, $file, $mapping, $zip]);
        $state = $this->get_proc_state($key);

        if ($state && $this->proc_is_waiting($state)) {
            return $this->proc_response($state);
        }

        if (!$state) {
            if (!$server || !$table || !$file) {
                return $this->proc_error(['proc_key' => $key], 'server, table or file missing');
            }

            if (!$this->get_table_exist($server, $table)) {
                return $this->proc_error(['proc_key' => $key], 'target table not found');
            }

            $source_file = $this->prepare_restore_source_file($file, (bool)$zip);
            if (!$source_file || !file_exists($source_file)) {
                return $this->proc_error(['proc_key' => $key], 'restore source file not readable');
            }

            $fh = fopen($source_file, 'rb');
            if (!$fh) {
                return $this->proc_error(['proc_key' => $key], 'restore open source failed');
            }

            $line1 = fgets($fh);
            $line2 = fgets($fh);

            $meta_rec = json_decode((string)$line1, true);
            $col_rec  = json_decode((string)$line2, true);

            if (!is_array($meta_rec) || !isset($meta_rec['meta']) || !is_array($col_rec) || !isset($col_rec['columns'])) {
                fclose($fh);
                return $this->proc_error(['proc_key' => $key], 'invalid backup format');
            }

            $file_pos = ftell($fh);
            fclose($fh);

            $target_fields = $this->get_db_fields($server, $table);
            $target_lookup = [];
            $target_types = [];
            foreach ($target_fields as $f) {
                $target_lookup[$f['name']] = true;
                $target_types[$f['name']] = strtolower((string)($f['type'] ?? ''));
            }

            if (!$this->empty_db_table($server, $table)) {
                return $this->proc_error(['proc_key' => $key], 'target table not found after empty');
            }

            $state = $this->init_proc_state('restore', $key, [
                'server'         => $server,
                'table'          => $table,
                'file'           => $file,
                'source_file'    => $source_file,
                'zip'            => $zip ? 1 : 0,
                'mapping'        => is_array($mapping) ? $mapping : [],
                'source_columns' => $col_rec['columns'],
                'source_types'   => is_array($col_rec['field_types'] ?? null)
                    ? $col_rec['field_types']
                    : [],
                'target_lookup'  => $target_lookup,
                'target_types'   => $target_types,
                'target_db_type' => $this->get_db_type($server),
                'file_pos'       => $file_pos,
                'done'           => 0,
                'total'          => (int)($meta_rec['meta']['row_count'] ?? 0),
                'message'        => 'restore initialized',
            ]);
        }

        if (!$this->connect_db_server($state['server'])) {
            $state = $this->proc_error($state, 'db connect failed');
            $this->set_proc_state($key, $state);
            return $state;
        }

        $started = $this->step_start_time();

        $fh = fopen($state['source_file'], 'rb');
        if (!$fh) {
            $state = $this->proc_error($state, 'restore open source failed');
            $this->set_proc_state($key, $state);
            return $state;
        }

        fseek($fh, (int)$state['file_pos']);

        while ($this->step_time_left($started, (float)$state['step_maxsec'])) {
            $line = fgets($fh);

            if ($line === false) {
                fclose($fh);

                if (!empty($state['zip']) && isset($state['source_file']) && $state['source_file'] !== $state['file']) {
                    @unlink($state['source_file']);
                }

                $state = $this->proc_finish($state, 'restore finished');
                $this->set_proc_state($key, $state);
                return $state;
            }

            $rec = json_decode(trim($line), true);
            if (!is_array($rec) || !isset($rec['records']) || !is_array($rec['records'])) {
                continue;
            }

            foreach ($rec['records'] as $row_values) {
                $assoc = [];
                foreach ($state['source_columns'] as $i => $col) {
                    $assoc[$col] = $row_values[$i] ?? null;
                }

                if (is_array($state['mapping'])) {
                    foreach ($state['mapping'] as $old => $new) {
                        if ((string)$old === (string)$new) {
                            continue;
                        }

                        if (array_key_exists($old, $assoc)) {
                            $assoc[$new] = $assoc[$old];
                            unset($assoc[$old]);
                        }
                    }
                }

                $db_rec = [];
                foreach ($assoc as $fld => $val) {
                    if (isset($state['target_lookup'][$fld])) {
                        $db_rec[$fld] = $this->normalize_restore_value(
                            (string)($state['target_db_type'] ?? ''),
                            (string)($state['target_types'][$fld] ?? ''),
                            $val,
                            (string)($state['source_types'][$fld] ?? '')
                        );
                    }
                }

                if (isset($db_rec['id']) && ($db_rec['id'] === '' || $db_rec['id'] === null || $db_rec['id'] === 0 || $db_rec['id'] === '0')) {
                    unset($db_rec['id']);
                }

                if (!$db_rec) {
                    continue;
                }

                $fields = array_keys($db_rec);
                $placeholders = array_fill(0, count($fields), '?');

                $sql = 'INSERT INTO ' . $this->quote_ident($state['server'], $state['table'])
                     . ' (' . implode(',', array_map(fn($f) => $this->quote_ident($state['server'], $f), $fields)) . ')'
                     . ' VALUES (' . implode(',', $placeholders) . ')';

                // Auch Restore-Importe laufen durch die zentrale dbxDB-Schicht.
                // Damit gelten Fehlerbehandlung, Retry-Logik und PDO-Portabilitaet
                // identisch fuer alle unterstuetzten Datenbanktreiber.
                $stmt = $this->database->query($state['server'], $sql, array_values($db_rec));
                if (!$stmt) {
                    throw new \RuntimeException('restore row insert failed');
                }

                $state['done']++;
            }

            $state['file_pos'] = ftell($fh);
            $state['percent']  = ($state['total'] > 0) ? (int)floor(($state['done'] / $state['total']) * 100) : 0;
            $state['step_percent'] = $state['percent'];
            $state['message']  = 'restore rows ' . $state['done'] . ' / ' . $state['total'];
        }

        fclose($fh);
        $this->set_proc_state($key, $state);
        return $this->proc_response($state);
    }

/**
     * Normalisiert treiberspezifisch problematische Restore-Werte.
     *
     * MySQL/MariaDB interpretiert einen leeren String in DATE-/TIME-Spalten
     * je nach SQL-Modus als Zero-Date oder Fehler. Fachlich leere Zeitwerte
     * werden deshalb als NULL gespeichert. Andere Werte bleiben bytegetreu;
     * insbesondere werden gueltige Mikrosekunden nicht beschnitten.
     */
    protected function normalize_restore_value(
        string $target_db_type,
        string $target_field_type,
        mixed $value,
        string $source_field_type = ''
    ): mixed {
        $target_db_type = strtolower(trim($target_db_type));
        $target_field_type = strtolower(trim($target_field_type));
        $source_field_type = strtolower(trim($source_field_type));
        $temporal_types = ['date', 'time', 'datetime', 'timestamp', 'year'];
        $effective_field_type = $target_field_type;

        // SQLite speichert Zeittypen technisch als TEXT. Beim Ruecktransfer
        // liefert deshalb der Quelltyp die noetige fachliche Information.
        if ($target_db_type === 'sqlite'
            && in_array($target_field_type, ['', 'text', 'varchar'], true)
            && in_array($source_field_type, $temporal_types, true)
        ) {
            $effective_field_type = $source_field_type;
        }

        if ($target_db_type === 'mysql'
            && $value === ''
            && in_array($effective_field_type, $temporal_types, true)
        ) {
            return null;
        }

        if ($target_db_type === 'sqlite'
            && is_string($value)
            && in_array($effective_field_type, $temporal_types, true)
            && preg_match('/^(.+)\.([0-9]{1,6})$/', $value, $match)
        ) {
            $fraction = rtrim($match[2], '0');
            if ($fraction !== '' && strlen($fraction) < 3) {
                $fraction = str_pad($fraction, 3, '0');
            }
            return $match[1] . ($fraction !== '' ? '.' . $fraction : '');
        }

        return $value;
    }

/**
     * Gruppiert DD-/DB-Feldtypen fachlich.
     *
     * @param string $type Typname
     * @param string $length Optionale Länge
     *
     * @return string Typgruppe
     */
    protected function schema_type_group(string $type, string $length = ''): string
    {
        $type = strtolower(trim($type));
        $length = trim($length);

        if ($type === '') {
            return 'unknown';
        }

        if (in_array($type, ['bool', 'boolean'], true)) {
            return 'bool';
        }

        if ($type === 'bit') {
            return ($length === '' || $length === '1') ? 'bool' : 'integer';
        }

        if ($type === 'tinyint' && $length === '1') {
            return 'bool';
        }

        if (in_array($type, ['int', 'integer', 'smallint', 'mediumint', 'tinyint', 'bigint', 'serial', 'number'], true)) {
            return 'integer';
        }

        if (in_array($type, ['decimal', 'numeric'], true)) {
            return 'decimal';
        }

        if (in_array($type, ['float', 'double', 'real'], true)) {
            return 'float';
        }

        if ($type === 'date' || $type === 'year') {
            return 'date';
        }

        if ($type === 'time') {
            return 'time';
        }

        if (in_array($type, ['datetime', 'timestamp'], true)) {
            return 'datetime';
        }

        if (in_array($type, ['char', 'nchar', 'varchar', 'varchar2', 'nvarchar', 'string'], true)) {
            return 'string';
        }

        if (in_array($type, ['text', 'tinytext', 'mediumtext', 'longtext', 'clob'], true)) {
            return 'text';
        }

        if (in_array($type, ['binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob'], true)) {
            return 'binary';
        }

        if ($type === 'json') {
            return 'json';
        }

        if (in_array($type, ['enum', 'set'], true)) {
            return 'enum';
        }

        return 'unknown';
    }
}
