<?php

/** Automatisch aus der stabilen Legacy-Fassade extrahierte interne Verantwortung. */
trait dbxDDSynchronizationTrait
{
/**
     * Transferiert eine DB-Tabelle serveruebergreifend als Schrittprozess.
     *
     * Ablauf:
     * - Zielstruktur bei Bedarf aus Quellschema erzeugen
     * - Ziel leeren
     * - Quelle sichern
     * - Sicherung in Ziel wiederherstellen
     *
     * @return array Prozessstatus
     */
    public function transfer_table(
        string $source_server,
        string $source_table,
        string $target_server,
        string $target_table = '',
        string $mode = 'step',
        int $create_target = 1,
        int $truncate_target = 1
    ): array {
        $target_table = $target_table ?: $source_table;
        $mode = strtolower((string)$mode);
        if ($mode === '') {
            $mode = 'step';
        }

        $key = $this->proc_key('transfer_table', [
            $source_server,
            $source_table,
            $target_server,
            $target_table,
            $create_target,
            $truncate_target,
        ]);

        if ($mode === 'reset') {
            $this->clear_proc_state($key);
            return [
                'proc_key' => $key,
                'status'   => 'reset',
                'message'  => 'transfer state cleared',
                'percent'  => 0,
                'step_percent' => 0,
            ];
        }

        if ($mode === 'status') {
            $state = $this->get_proc_state($key);
            return $state ?: [
                'proc_key' => $key,
                'status'   => 'new',
                'message'  => 'no active state',
                'percent'  => 0,
                'step_percent' => 0,
            ];
        }

        if (in_array($mode, ['pause', 'resume', 'continue', 'cancel'], true)) {
            return $this->control_proc_state($key, $mode);
        }

        if ($mode === 'restart') {
            $this->clear_proc_state($key);
            $mode = 'step';
        }

        $state = $this->get_proc_state($key);

        if (!$state) {
            if (!$source_server || !$source_table || !$target_server || !$target_table) {
                return $this->proc_error(['proc_key' => $key], 'source or target missing');
            }

            if (!$this->get_table_exist($source_server, $source_table)) {
                return $this->proc_error(['proc_key' => $key], 'source table not found');
            }

            $state = $this->init_proc_state('transfer_table', $key, [
                'source_server'   => $source_server,
                'source_table'    => $source_table,
                'target_server'   => $target_server,
                'target_table'    => $target_table,
                'create_target'   => $create_target ? 1 : 0,
                'truncate_target' => $truncate_target ? 1 : 0,
                'backup_file'     => $this->build_backup_file_name($source_server, $source_table, true),
                'phase'           => 'prepare_target',
                'percent'         => 0,
                'message'         => 'transfer initialized',
            ]);
        }

        if ($this->proc_is_waiting($state)) {
            return $this->proc_response($state);
        }

        switch ($state['phase']) {
            case 'prepare_target':
                if (!$this->get_table_exist($state['target_server'], $state['target_table'])) {
                    if (empty($state['create_target'])) {
                        $state = $this->proc_error($state, 'target table not found');
                        $this->set_proc_state($key, $state);
                        return $state;
                    }

                    $schema  = $this->get_preferred_table_schema($state['source_server'], $state['source_table']);
                    $fields  = $schema['fields'] ?? [];
                    $indexes = $schema['indexes'] ?? [];
                    $ok = $this->create_db_tab_from_fields($state['target_server'], $state['target_table'], $fields, $indexes);

                    if (!$ok) {
                        $state = $this->proc_error($state, 'create target table failed');
                        $this->set_proc_state($key, $state);
                        return $state;
                    }
                }

                if (!empty($state['truncate_target'])) {
                    $this->empty_db_table($state['target_server'], $state['target_table']);
                }

                $state['phase']   = 'backup_source';
                $state['percent'] = 10;
                $state['step_percent'] = 100;
                $state['message'] = 'target ready';
                $this->set_proc_state($key, $state);
                return $this->proc_response($state);

            case 'backup_source':
                $backup = $this->backup($state['source_server'], $state['source_table'], $state['backup_file'], 1);

                if (($backup['status'] ?? '') === 'error') {
                    $state = $this->proc_error($state, 'backup source failed');
                    $this->set_proc_state($key, $state);
                    return $state;
                }

                if (($backup['status'] ?? '') !== 'finished') {
                    $state['percent'] = 10 + (int)floor((($backup['percent'] ?? 0) * 0.4));
                    $state['step_percent'] = (int)($backup['percent'] ?? 0);
                    $state['message'] = $backup['message'] ?? 'backup source';
                    $this->set_proc_state($key, $state);
                    return $this->proc_response($state);
                }

                $state['phase']   = 'restore_target';
                $state['percent'] = 55;
                $state['step_percent'] = 100;
                $state['message'] = 'backup finished';
                $this->set_proc_state($key, $state);
                return $this->proc_response($state);

            case 'restore_target':
                $restore = $this->restore($state['target_server'], $state['target_table'], $state['backup_file'], [], 1);

                if (($restore['status'] ?? '') === 'error') {
                    $state = $this->proc_error($state, 'restore target failed');
                    $this->set_proc_state($key, $state);
                    return $state;
                }

                if (($restore['status'] ?? '') !== 'finished') {
                    $state['percent'] = 55 + (int)floor((($restore['percent'] ?? 0) * 0.4));
                    $state['step_percent'] = (int)($restore['percent'] ?? 0);
                    $state['message'] = $restore['message'] ?? 'restore target';
                    $this->set_proc_state($key, $state);
                    return $this->proc_response($state);
                }

                $state = $this->proc_finish($state, 'transfer finished');
                $this->set_proc_state($key, $state);
                return $state;
        }

        $state = $this->proc_error($state, 'unknown transfer phase');
        $this->set_proc_state($key, $state);
        return $state;
    }

/**
     * Baut einen technischen Sync-Plan DD -> DB.
     *
     * Wichtige Regel:
     * - DD ist das Soll-Modell
     * - bei SQLite/db3 sollen textartige physische Typen nicht automatisch
     *   als Konflikt gegen `varchar`/`char` gewertet werden
     *
     * @param string $dd DD-Name
     *
     * @return array Technischer Sync-Plan
     */
    protected function build_sync_plan_dd_to_db(string $dd): array
    {
        $model = $this->get_dd_model($dd);

        $plan = [
            'ok'             => 1,
            'dd'             => $dd,
            'server'         => $model['table']['server'] ?? '',
            'table'          => $model['table']['table']  ?? '',
            'table_exists'   => 0,
            'create_table'   => 0,
            'add_fields'     => [],
            'missing_in_dd'  => [],
            'type_conflicts' => [],
            'add_indexes'    => [],
            'rebuild_needed' => 0,
            'backup_file'    => '',
        ];

        if (!$model) {
            $plan['ok'] = 0;
            return $plan;
        }

        $server    = $plan['server'];
        $table     = $plan['table'];
        $dd_fields  = $model['fields']  ?? [];
        $dd_indexes = $model['indexes'] ?? [];

        if (!$server || !$table) {
            $plan['ok'] = 0;
            return $plan;
        }

        $table_exists = $this->get_table_exist($server, $table) ? 1 : 0;
        $plan['table_exists'] = $table_exists;

        if (!$table_exists) {
            $plan['create_table'] = 1;
            return $plan;
        }

        $db_fields  = $this->get_db_fields($server, $table);
        $db_indexes = $this->get_db_indexes($server, $table);

        $db_fields_by_name = [];
        foreach ($db_fields as $field) {
            $db_fields_by_name[strtolower($field['name'])] = $field;
        }

        $dd_fields_by_name = [];
        foreach ($dd_fields as $field) {
            $dd_fields_by_name[strtolower($field['name'])] = $field;
        }

        $target_db_type = $this->get_db_type($server);

        foreach ($dd_fields as $field) {
            $name = strtolower((string)$field['name']);
            if (!isset($db_fields_by_name[$name])) {
                $plan['add_fields'][] = $field;
                continue;
            }

            $dbf = $db_fields_by_name[$name];

            $dd_type = strtolower((string)($field['type'] ?? ''));
            $db_type = strtolower((string)($dbf['type'] ?? ''));

            $dd_len = (string)($field['length'] ?? '');
            $db_len = (string)($dbf['length'] ?? '');

            $type_equal = $this->is_db_sync_field_match($target_db_type, $field, $dbf);
            $len_equal  = ($dd_len === $db_len) || ($dd_len === '' || $db_len === '');

            /**
             * SQLite/db3:
             * physisch oft TEXT/INTEGER/REAL ohne echte MySQL-Präzision.
             * Die DD-Definition bleibt kanonisch und ist hier das Soll.
             */
            if ($target_db_type === 'sqlite') {
                if ($type_equal) {
                    $len_equal  = true;
                }
            }

            if (!$type_equal || !$len_equal) {
                $plan['type_conflicts'][] = [
                    'field'      => $field['name'],
                    'dd_type'    => $dd_type,
                    'db_type'    => $db_type,
                    'dd_length'  => $dd_len,
                    'db_length'  => $db_len,
                ];
            }
        }

        foreach ($db_fields as $field) {
            $name = strtolower((string)$field['name']);
            if (!isset($dd_fields_by_name[$name])) {
                $plan['missing_in_dd'][] = $field;
            }
        }

        $db_index_names = [];
        foreach ($db_indexes as $idx) {
            $db_index_names[strtolower((string)$idx['name'])] = true;
        }

        foreach ($dd_indexes as $idx) {
            $name = strtolower((string)($idx['name'] ?? ''));
            if (!$name) {
                continue;
            }

            if (strtoupper((string)($idx['type'] ?? 'INDEX')) === 'PRIMARY') {
                continue;
            }

            if (!isset($db_index_names[$name])) {
                $plan['add_indexes'][] = $idx;
            }
        }

        $plan['rebuild_needed'] = (!empty($plan['type_conflicts']) || !empty($plan['missing_in_dd'])) ? 1 : 0;

        return $plan;
    }

/**
     * Synchronisiert ein DD technisch in Richtung DB.
     *
     * Modus
     * -----
     * - `check` / `plan`
     * - `apply`
     * - `force` / `rebuild`
     * - `status`
     * - `reset`
     *
     * Hinweis:
     * - dies ist reine Infrastruktur
     * - spätere Admin-/Mappinglogik entscheidet fachlich genauer,
     *   wie Konflikte wirklich behandelt werden
     *
     * @param string $modul Modulname
     * @param string $dd DD-Name
     * @param string $mode Modus
     *
     * @return array Prozessstatus / Plan
     */
    public function sync_dd_to_db($modul, $dd, $mode = 'step'): array
    {
        $mode = strtolower((string)$mode);
        if ($mode === '') {
            $mode = 'step';
        }
        if ($mode === 'step') {
            $mode = 'apply';
        }

        $dd_ref = ($modul && strpos((string)$dd, '|') === false) ? $modul . '|' . $dd : (string)$dd;
        $key = $this->proc_key('sync_dd_to_db', [$modul, $dd]);

        if ($mode === 'reset') {
            $this->clear_proc_state($key);
            return [
                'proc_key' => $key,
                'status'   => 'reset',
                'message'  => 'sync state cleared',
                'percent'  => 0,
                'step_percent' => 0,
            ];
        }

        if ($mode === 'status') {
            $state = $this->get_proc_state($key);
            return $state ?: [
                'proc_key' => $key,
                'status'   => 'new',
                'message'  => 'no active state',
                'percent'  => 0,
                'step_percent' => 0,
            ];
        }

        if (in_array($mode, ['pause', 'resume', 'continue', 'cancel'], true)) {
            return $this->control_proc_state($key, $mode);
        }

        if ($mode === 'restart') {
            $this->clear_proc_state($key);
            $mode = 'apply';
        }

        $plan = $this->build_sync_plan_dd_to_db($dd_ref);

        if (in_array($mode, ['check', 'plan'], true)) {
            $plan['status']  = 'finished';
            $plan['message'] = 'plan ready';
            $plan['percent'] = 100;
            $plan['step_percent'] = 100;
            return $plan;
        }

        $state = $this->get_proc_state($key);

        if (!$state) {
            $state = $this->init_proc_state('sync_dd_to_db', $key, [
                'mode'            => $mode,
                'dd'              => $dd,
                'dd_ref'          => $dd_ref,
                'modul'           => $modul,
                'server'          => $plan['server'],
                'table'           => $plan['table'],
                'phase'           => 'prepare',
                'plan'            => $plan,
                'field_pos'       => 0,
                'index_pos'       => 0,
                'percent'         => 0,
                'message'         => 'sync initialized',
                'mapping'         => $this->get_schema_mapping_values('dd_to_db', [
                    'modul'  => $modul,
                    'dd'     => $dd,
                    'server' => $plan['server'],
                    'table'  => $plan['table'],
                ]),
                'backup_started'  => 0,
                'restore_started' => 0,
            ]);
        }

        if ($this->proc_is_waiting($state)) {
            return $this->proc_response($state);
        }

        return $this->run_sync_dd_to_db_phase($state, $key, $dd_ref);
    }

    /** Führt genau eine persistierte Phase der DD-zu-DB-Synchronisation aus. */
    private function run_sync_dd_to_db_phase(array $state, string $key, string $dd_ref): array
    {
        $server = $state['server'];
        $table  = $state['table'];
        $plan   = $state['plan'];

        switch ($state['phase']) {
            case 'prepare':
                return $this->prepare_dd_to_db_phase($state, $key, $plan);

            case 'create_table':
                return $this->create_dd_to_db_table_phase($state, $key, $dd_ref);

            case 'add_fields':
                return $this->add_dd_to_db_fields_phase($state, $key, $server, $table, $plan);

            case 'add_indexes':
                return $this->add_dd_to_db_indexes_phase($state, $key, $server, $table, $plan);

            case 'backup_old':
                $backup_file = $state['plan']['backup_file'] ?? '';
                if (!$backup_file) {
                    $backup_file = $this->build_backup_file_name($server, $table, true);
                    $state['plan']['backup_file'] = $backup_file;
                }

                $bak = $this->backup($server, $table, $backup_file, 1);
                if (($bak['status'] ?? '') === 'error') {
                    $state = $this->proc_error($state, 'backup before rebuild failed');
                    $this->set_proc_state($key, $state);
                    return $state;
                }

                if (($bak['status'] ?? '') !== 'finished') {
                    $state['percent'] = 10 + (int)floor((($bak['percent'] ?? 0) * 0.3));
                    $state['step_percent'] = (int)($bak['percent'] ?? 0);
                    $state['message'] = 'backup old table';
                    $this->set_proc_state($key, $state);
                    return $this->proc_response($state);
                }

                $state['phase']   = 'rename_old';
                $state['percent'] = 45;
                $state['step_percent'] = 100;
                $state['message'] = 'rename old table';
                $this->set_proc_state($key, $state);
                return $this->proc_response($state);

            case 'rename_old':
                $tmp_old = $table . '__dbxold_' . date('YmdHis');
                $sql = 'ALTER TABLE ' . $this->quote_ident($server, $table)
                     . ' RENAME TO ' . $this->quote_ident($server, $tmp_old);

                if (!$this->exec_query($server, $sql)) {
                    $state = $this->proc_error($state, 'rename old table failed');
                    $this->set_proc_state($key, $state);
                    return $state;
                }

                $state['old_table'] = $tmp_old;
                $state['phase']     = 'create_new';
                $state['percent']   = 55;
                $state['step_percent'] = 100;
                $state['message']   = 'create new table';
                $this->set_proc_state($key, $state);
                return $this->proc_response($state);

            case 'create_new':
                if (!$this->create_db_tab($state['dd_ref'] ?? $dd_ref)) {
                    $state = $this->proc_error($state, 'create new table failed');
                    $this->set_proc_state($key, $state);
                    return $state;
                }

                $state['phase']   = 'restore_new';
                $state['percent'] = 65;
                $state['step_percent'] = 100;
                $state['message'] = 'restore data into new table';
                $this->set_proc_state($key, $state);
                return $this->proc_response($state);

            case 'restore_new':
                $mapping = is_array($state['mapping'] ?? null) ? $state['mapping'] : [];
                $restore = $this->restore($server, $table, $state['plan']['backup_file'], $mapping, 1);

                if (($restore['status'] ?? '') === 'error') {
                    $state = $this->proc_error($state, 'restore into new table failed');
                    $this->set_proc_state($key, $state);
                    return $state;
                }

                if (($restore['status'] ?? '') !== 'finished') {
                    $state['percent'] = 65 + (int)floor((($restore['percent'] ?? 0) * 0.25));
                    $state['step_percent'] = (int)($restore['percent'] ?? 0);
                    $state['message'] = 'restore data into new table';
                    $this->set_proc_state($key, $state);
                    return $this->proc_response($state);
                }

                $state['phase']   = 'drop_old';
                $state['percent'] = 92;
                $state['step_percent'] = 100;
                $state['message'] = 'drop old table';
                $this->set_proc_state($key, $state);
                return $this->proc_response($state);

            case 'drop_old':
                if (!empty($state['old_table'])) {
                    $this->drop_db_tab($server, $state['old_table']);
                }

                $state = $this->proc_finish($state, 'sync dd -> db rebuild finished');
                $this->set_proc_state($key, $state);
                return $state;
        }

        $state = $this->proc_error($state, 'unknown sync phase');
        $this->set_proc_state($key, $state);
        return $state;
    }

    /** Bestimmt, ob eine Tabelle neu erstellt, erweitert oder aufgebaut wird. */
    private function prepare_dd_to_db_phase(array $state, string $key, array $plan): array
    {
        if (!empty($plan['create_table'])) {
            $state = array_merge($state, ['phase' => 'create_table', 'message' => 'create table', 'percent' => 10, 'step_percent' => 0]);
        } elseif (!empty($plan['rebuild_needed'])) {
            if (!in_array($state['mode'], ['force', 'rebuild'], true)) {
                $state = $this->proc_error($state, 'rebuild needed; use mode force or rebuild');
                $this->set_proc_state($key, $state);
                return $state;
            }
            $state = array_merge($state, ['phase' => 'backup_old', 'message' => 'backup old table', 'percent' => 10, 'step_percent' => 0]);
        } else {
            $state = array_merge($state, ['phase' => 'add_fields', 'message' => 'add missing fields', 'percent' => 20, 'step_percent' => 0]);
        }
        $this->set_proc_state($key, $state);
        return $this->proc_response($state);
    }

    /** Erstellt die physische Tabelle für ein neues DD. */
    private function create_dd_to_db_table_phase(array $state, string $key, string $dd_ref): array
    {
        if ($this->create_db_tab($state['dd_ref'] ?? $dd_ref)) {
            $state = array_merge($state, ['phase' => 'add_indexes', 'message' => 'create indexes', 'percent' => 70, 'step_percent' => 100]);
        } else {
            $state = $this->proc_error($state, 'create table failed');
        }
        $this->set_proc_state($key, $state);
        return $this->proc_response($state);
    }

    /** Ergänzt fehlende Felder innerhalb des Zeitbudgets eines Sync-Schritts. */
    private function add_dd_to_db_fields_phase(array $state, string $key, string $server, string $table, array $plan): array
    {
        $started = $this->step_start_time();
        $fields = $plan['add_fields'] ?? [];
        while ($state['field_pos'] < count($fields) && $this->step_time_left($started, (float)$state['step_maxsec'])) {
            $field = $fields[$state['field_pos']];
            if (!$this->add_db_field_from_dd($server, $table, $field)) {
                $state = $this->proc_error($state, 'add field failed: ' . ($field['name'] ?? ''));
                $this->set_proc_state($key, $state);
                return $state;
            }
            $state['field_pos']++;
            $max = max(1, count($fields));
            $state['percent'] = 20 + (int)floor(($state['field_pos'] / $max) * 40);
            $state['step_percent'] = (int)floor(($state['field_pos'] / $max) * 100);
            $state['message'] = 'add field ' . ($field['name'] ?? '');
        }
        if ($state['field_pos'] >= count($fields)) {
            $state = array_merge($state, ['phase' => 'add_indexes', 'message' => 'add indexes', 'percent' => 70, 'step_percent' => 100]);
        }
        $this->set_proc_state($key, $state);
        return $this->proc_response($state);
    }

    /** Ergänzt fehlende Indizes innerhalb des Zeitbudgets eines Sync-Schritts. */
    private function add_dd_to_db_indexes_phase(array $state, string $key, string $server, string $table, array $plan): array
    {
        $started = $this->step_start_time();
        $indexes = $plan['add_indexes'] ?? [];
        while ($state['index_pos'] < count($indexes) && $this->step_time_left($started, (float)$state['step_maxsec'])) {
            $index = $indexes[$state['index_pos']];
            if (!$this->create_db_index($server, $table, $index)) {
                $state = $this->proc_error($state, 'add index failed: ' . ($index['name'] ?? ''));
                $this->set_proc_state($key, $state);
                return $state;
            }
            $state['index_pos']++;
            $max = max(1, count($indexes));
            $state['percent'] = 70 + (int)floor(($state['index_pos'] / $max) * 20);
            $state['step_percent'] = (int)floor(($state['index_pos'] / $max) * 100);
            $state['message'] = 'add index ' . ($index['name'] ?? '');
        }
        if ($state['index_pos'] >= count($indexes)) {
            $state = $this->proc_finish($state, 'sync dd -> db finished');
        }
        $this->set_proc_state($key, $state);
        return $this->proc_response($state);
    }

/* =====================================================
     * DB -> DD SYNC
     * ===================================================== */

    /**
     * Synchronisiert eine physische Datenbanktabelle technisch in Richtung DD.
     *
     * Der Ablauf liest Felder und Indizes aus der Datenbank, fuehrt sie mit
     * vorhandenen DD-Metadaten zusammen und schreibt daraus wieder eine
     * DD-Datei. Bestehende DD-Werte haben Vorrang: Typen, Laengen, Rechte,
     * Cache-/Trace-Flags und fachliche Metadaten werden nicht blind durch
     * physische SQLite/db3-Typen ueberschrieben. Das verhindert Struktur-Drift
     * ueber mehrere Sync-Durchlaeufe.
     *
     * Typische Modi:
     *
     * - `merge` oder `step`: schema lesen, Metadaten mergen, DD schreiben
     * - `restart`: vorhandenen Prozesszustand loeschen und neu starten
     * - `status`: aktuellen Prozesszustand lesen
     * - `reset`: Prozesszustand loeschen
     * - `pause`, `resume`, `continue`, `cancel`: Prozesssteuerung
     *
     * Beispiel:
     *
     * ```php
     * $dd = dbx()->get_system_obj('dbxDD');
     * $state = $dd->sync_db_to_dd(
     *     'dbxContact',
     *     'contactRequest',
     *     'merge',
     *     'dbXsystem',
     *     'contact_request'
     * );
     * ```
     *
     * Ergebnis:
     *
     * ```php
     * [
     *     'status' => 'running',
     *     'phase' => 'write_dd',
     *     'percent' => 70,
     * ]
     * ```
     *
     * @param string $modul Zielmodul, in dessen `dd/`-Verzeichnis geschrieben wird.
     * @param string $dd DD-Name ohne Dateiendung.
     * @param string $mode Sync-Modus oder Prozesssteuerung.
     * @param string $server Optionaler DB-Server; leer nutzt DD- oder System-Fallback.
     * @param string $table Optionaler physischer Tabellenname; leer nutzt DD- oder Namens-Fallback.
     *
     * @return array Prozessstatus mit `status`, `message`, `percent` und optional `phase`.
     */
    public function sync_db_to_dd($modul, $dd, $mode = 'step', string $server = '', string $table = ''): array
    {
        $mode = strtolower((string)$mode);
        if ($mode === '') {
            $mode = 'step';
        }
        if ($mode === 'step' || $mode === 'apply') {
            $mode = 'merge';
        }

        $server = trim($server);
        $table  = trim($table);
        $key = $this->proc_key('sync_db_to_dd', [$modul, $dd, $server, $table]);

        if ($mode === 'reset') {
            $this->clear_proc_state($key);
            return [
                'proc_key' => $key,
                'status'   => 'reset',
                'message'  => 'sync state cleared',
                'percent'  => 0,
                'step_percent' => 0,
            ];
        }

        if ($mode === 'status') {
            $state = $this->get_proc_state($key);
            return $state ?: [
                'proc_key' => $key,
                'status'   => 'new',
                'message'  => 'no active state',
                'percent'  => 0,
                'step_percent' => 0,
            ];
        }

        if (in_array($mode, ['pause', 'resume', 'continue', 'cancel'], true)) {
            return $this->control_proc_state($key, $mode);
        }

        if ($mode === 'restart') {
            $this->clear_proc_state($key);
            $mode = 'merge';
        }

        $state = $this->get_proc_state($key);

        if (!$state) {
            $old_model = [];
            $sync_server = $server ?: 'dbXsystem';
            $sync_table  = $table ?: $dd;

            if ($this->get_dd_exist($modul . '|' . $dd) || $this->get_dd_exist($dd)) {
                $old_model = $this->get_dd_model($modul . '|' . $dd);
                if (!$old_model) {
                    $old_model = $this->get_dd_model($dd);
                }

                if (!$server) {
                    $sync_server = $old_model['table']['server'] ?? $sync_server;
                }
                if (!$table) {
                    $sync_table  = $old_model['table']['table']  ?? $sync_table;
                }
            }

            if (!$this->get_table_exist($sync_server, $sync_table)) {
                return $this->proc_error(['proc_key' => $key], 'table not found');
            }

            $state = $this->init_proc_state('sync_db_to_dd', $key, [
                'mode'      => $mode,
                'modul'     => $modul,
                'dd'        => $dd,
                'server'    => $sync_server,
                'table'     => $sync_table,
                'old_model' => $old_model,
                'mapping'   => $this->get_schema_mapping_values('db_to_dd', [
                    'modul'  => $modul,
                    'dd'     => $dd,
                    'server' => $sync_server,
                    'table'  => $sync_table,
                ]),
                'phase'     => 'read_schema',
                'percent'   => 0,
                'message'   => 'sync initialized',
            ]);
        }

        if ($this->proc_is_waiting($state)) {
            return $this->proc_response($state);
        }

        return $this->run_sync_db_to_dd_phase($state, $key);
    }

    /** Führt genau eine persistierte Phase der DB-zu-DD-Synchronisation aus. */
    private function run_sync_db_to_dd_phase(array $state, string $key): array
    {
        switch ($state['phase']) {
            case 'read_schema':
                $state['db_fields']  = $this->get_db_fields($state['server'], $state['table']);
                $state['db_indexes'] = $this->get_db_indexes($state['server'], $state['table']);
                $state['phase']      = 'merge_meta';
                $state['percent']    = 30;
                $state['step_percent'] = 100;
                $state['message']    = 'schema loaded';
                $this->set_proc_state($key, $state);
                return $this->proc_response($state);

            case 'merge_meta':
                $old_model = $state['old_model'] ?? array();
                $state['new_table'] = $this->merge_db_to_dd_table($state, $old_model);
                $state['new_fields'] = $this->merge_db_to_dd_fields($state, $old_model);
                $state['new_indexes'] = $this->merge_db_to_dd_indexes($state, $old_model);
                $state['phase']       = 'write_dd';
                $state['percent']     = 70;
                $state['step_percent'] = 100;
                $state['message']     = 'meta merged';
                $this->set_proc_state($key, $state);
                return $this->proc_response($state);

            case 'write_dd':
                $ok = $this->write_dd(
                    $state['modul'],
                    $state['dd'],
                    $state['new_table'] ?? [],
                    $state['new_fields'] ?? [],
                    $state['new_indexes'] ?? []
                );

                if (!$ok) {
                    $state = $this->proc_error($state, 'write dd failed');
                    $this->set_proc_state($key, $state);
                    return $state;
                }

                $this->clear_dd_cache($state['modul'] . '|' . $state['dd']);

                $state = $this->proc_finish($state, 'sync db -> dd finished');
                $this->set_proc_state($key, $state);
                return $state;
        }

        $state = $this->proc_error($state, 'unknown sync phase');
        $this->set_proc_state($key, $state);
        return $state;
    }

    /** Übernimmt physische Tabellenwerte und bewahrt fachliche DD-Metadaten. */
    private function merge_db_to_dd_table(array $state, array $old_model): array
    {
        $table = $this->normalize_table_record($state['dd'], [
            'server' => $state['server'],
            'table' => $state['table'],
        ]);
        if ($state['mode'] !== 'merge' || !$old_model) {
            return $table;
        }
        foreach (['autosync','version','cache','trash','trace','read','create','update','delete','read_owner','create_owner','update_owner','delete_owner'] as $key) {
            if (isset($old_model['table'][$key])) {
                $table[$key] = $old_model['table'][$key];
            }
        }
        return $table;
    }

    /** Führt DB-Felder anhand des optionalen Mappings mit DD-Feldmetadaten zusammen. */
    private function merge_db_to_dd_fields(array $state, array $old_model): array
    {
        $old_fields = array();
        foreach (($old_model['fields'] ?? array()) as $field) {
            $old_fields[strtolower((string)($field['name'] ?? ''))] = $field;
        }
        $mapping = is_array($state['mapping'] ?? null) ? $state['mapping'] : array();
        $fields = array();
        foreach (($state['db_fields'] ?? array()) as $field) {
            $name = $field['name'];
            $meta_key = strtolower((string)($mapping[$name] ?? $name));
            if (!isset($old_fields[$meta_key])) {
                $meta_key = strtolower((string)$name);
            }
            $fields[] = $state['mode'] === 'merge' && isset($old_fields[$meta_key])
                ? $this->merge_dd_field_with_db_field($old_fields[$meta_key], $field, $this->get_db_type($state['server']))
                : $this->normalize_field_record($field);
        }
        return $fields;
    }

    /** Führt DB-Indizes mit den vorhandenen DD-Indexmetadaten zusammen. */
    private function merge_db_to_dd_indexes(array $state, array $old_model): array
    {
        $old_indexes = array();
        foreach (($old_model['indexes'] ?? array()) as $index) {
            $old_indexes[$index['name']] = $index;
        }
        $indexes = array();
        foreach (($state['db_indexes'] ?? array()) as $index) {
            $name = $index['name'];
            if ($state['mode'] === 'merge' && isset($old_indexes[$name])) {
                foreach ($old_indexes[$name] as $key => $value) {
                    if ($value !== '' && $value !== null) {
                        $index[$key] = $value;
                    }
                }
            }
            $indexes[] = $this->normalize_index_record($index);
        }
        return $indexes;
    }

/* =====================================================
     * LIST
     * ===================================================== */

    /**
     * Liefert DD-Übersichtsdaten aus einem DD-Verzeichnis.
     *
     * Hinweis:
     * - dient als technische Infrastruktur
     * - aktuell liest diese Funktion die DD-Dateien des angegebenen Pfads
     * - für spätere Admin-Oberflächen geeignet
     *
     * @param string $path DD-Verzeichnispfad
     *
     * @return array DD-Tabellenübersicht
     */
    public function get_dd_tables($path = ''): array
    {
        $records = [];

        if (!$path) {
            $path = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/dbx/dd/');
        }

        if (!is_dir($path)) {
            return $records;
        }

        $files = scandir($path);
        foreach ($files as $file) {
            if (!str_ends_with($file, '.dd.php')) {
                continue;
            }

            $dd = str_replace('.dd.php', '', $file);
            if ($dd === 'new') {
                continue;
            }

            $dd_sys = $this->load_dd($dd);
            if (($dd_sys['dd_status'] ?? 0) <= 0) {
                continue;
            }

            $server = $this->get_dd_server($dd);
            $table  = $this->get_dd_table($dd);
            $exist  = $this->get_table_exist($server, $table) ? 1 : 0;
            $count  = $exist ? $this->count($dd) : -1;

            $records[] = [
                'datadic' => $dd,
                'server'  => $server,
                'table'   => $table,
                'exist'   => $exist,
                'count'   => $count,
                'sync'    => $this->get_dd_autosync($dd),
            ];
        }

        return $records;
    }
}
