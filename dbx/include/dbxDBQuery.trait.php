<?php

/** Automatisch aus der stabilen Legacy-Fassade extrahierte interne Verantwortung. */
trait dbxDBQueryTrait
{
/**
     * Führt eine SQL-Abfrage auf dem angegebenen Server aus.
     *
     * Stellt sicher, dass eine Verbindung besteht, bereitet die SQL-Abfrage vor
     * und führt sie aus. Bei retry-fähigen Lock-/Deadlock-Fehlern wird außerhalb
     * aktiver Transaktionen mehrfach erneut versucht.
     *
     * @param string $server Datenbankserver
     * @param string $sql SQL-Abfrage
     * @param array $params Gebundene Parameter fuer Platzhalter
     *
     * @return PDOStatement|int PDO-Statement bei Erfolg, `0` bei Fehler
     */
    public function query(string $server, string $sql, array $params = array()): PDOStatement|int {
        if (!$sql) {
            return 0;
        }

        if (!$this->connect_db_server($server)) {
            return 0;
        }

        $max_retry = 5;
        $profile_started = microtime(true);

        for ($try = 0; $try < $max_retry; $try++) {
            try {
                $stmt = $this->db[$server]->prepare($sql);
                $stmt->execute($params);

                $this->record_performance_query(
                    $server,
                    $sql,
                    $profile_started,
                    max(0, (int) $stmt->rowCount()),
                    true
                );

                return $stmt;
            } catch (PDOException $e) {
                if (empty($this->_tx[$server]) && $this->is_retryable($e) && $try < $max_retry - 1) {
                    usleep(120000 + random_int(0, 150000));
                    continue;
                }

                $this->report_db_error(
                    'sql',
                    $server,
                    'SQL-Fehler',
                    $e->getMessage(),
                    'sql|' . $server . '|' . md5($sql)
                );

                $this->record_performance_query($server, $sql, $profile_started, 0, false);

                return 0;
            }
        }

        return 0;
    }

/**
     * Startet eine Transaktion für die Datenbank der angegebenen DD.
     *
     * @param string $dd Name der Datenbeschreibung
     *
     * @return int 1 bei Erfolg, 0 bei Fehler
     */
    public function begin(string $dd): int {
        $server = $this->get_dd_server($dd);

        if (!$server) {
            return 0;
        }

        return $this->begin_server($server);
    }

/**
     * Führt ein Rollback für die Datenbank der angegebenen DD aus.
     *
     * @param string $dd Name der Datenbeschreibung
     *
     * @return int 1 bei Erfolg, 0 bei Fehler
     */
    public function rollback(string $dd): int {
        $server = $this->get_dd_server($dd);

        if (!$server) {
            return 0;
        }

        return $this->rollback_server($server);
    }

/**
     * Startet serverabhängig eine Transaktion.
     *
     * - SQLite: `BEGIN IMMEDIATE TRANSACTION`
     * - MySQL: klassische PDO-Transaktion
     * - andere DBs: klassische PDO-Transaktion
     *
     * Wichtige Regel
     * --------------
     * - keine globale Tabelleninventur
     * - keine pauschalen WRITE-Locks auf alle Tabellen
     * - TX-Status nur über PDO + interne TX-Markierung
     *
     * @param string $server Datenbankserver
     *
     * @return int 1 bei Erfolg, 0 bei Fehler
     */
    private function begin_server(string $server): int {
        if (!$this->connect_db_server($server)) {
            return 0;
        }

        if (!empty($this->_tx[$server])) {
            return 1;
        }

        $type = $this->get_db_type($server);
        $pdo  = $this->db[$server];

        try {
            switch ($type) {
                case 'sqlite':
                    $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
                    break;

                case 'mysql':
                    $pdo->beginTransaction();
                    break;

                default:
                    $pdo->beginTransaction();
            }

            $this->_tx[$server] = true;
            return 1;
        } catch (PDOException $e) {
            $this->report_db_error('sql', $server, 'SQL-Fehler', $e->getMessage(), 'sql-tx-begin|' . $server);
            return 0;
        }
    }

/**
     * Führt serverabhängig ein Rollback aus.
     *
     * - MySQL: normales PDO-Rollback
     * - SQLite: `ROLLBACK`
     * - andere DBs: normales PDO-Rollback
     *
     * @param string $server Datenbankserver
     *
     * @return int 1 bei Erfolg, 0 bei Fehler
     */
    private function rollback_server(string $server): int {
        if (!$this->connect_db_server($server)) {
            return 0;
        }

        if (empty($this->_tx[$server])) {
            return 1;
        }

        $type = $this->get_db_type($server);
        $pdo  = $this->db[$server];

        try {
            if ($type === 'sqlite') {
                $pdo->exec('ROLLBACK');
            } else {
                $pdo->rollBack();
            }

            unset($this->_tx[$server]);
            $this->invalidate_select1_cache_server($server);
            return 1;
        } catch (PDOException $e) {
            $this->report_db_error('sql', $server, 'SQL-Fehler', $e->getMessage(), 'sql-tx-rollback|' . $server);

            unset($this->_tx[$server]);
            $this->invalidate_select1_cache_server($server);
            return 0;
        }
    }

/**
     * Committet eine laufende Transaktion der Datenbank einer DD.
     *
     * - MySQL: normales PDO-Commit
     * - SQLite: `COMMIT`
     * - andere DBs: normales PDO-Commit
     *
     * @param string $dd Name der Datenbeschreibung
     *
     * @return int 1 bei Erfolg, 0 bei Fehler
     */
    public function commit($dd) {
        $server = $this->get_dd_server($dd);
        dbx()->debug("commit($server)");

        if (!$this->connect_db_server($server)) {
            return 0;
        }

        if (empty($this->_tx[$server])) {
            return 1;
        }

        $type = $this->get_db_type($server);
        $pdo  = $this->db[$server];

        try {
            if ($type === 'sqlite') {
                $pdo->exec('COMMIT');
            } else {
                $pdo->commit();
            }

            unset($this->_tx[$server]);
            $this->invalidate_select1_cache_server($server);
            return 1;
        } catch (PDOException $e) {
            $this->report_db_error('sql', $server, 'SQL-Fehler', $e->getMessage(), 'sql-tx-commit|' . $server);

            unset($this->_tx[$server]);
            $this->invalidate_select1_cache_server($server);
            return 0;
        }
    }

/**
     * Prüft, ob eine PDO-Exception retry-fähig ist.
     *
     * @param PDOException $e Exception-Objekt
     *
     * @return bool True bei retry-fähigem Fehler
     */
    private function is_retryable(PDOException $e): bool {
        $msg = strtolower($e->getMessage());

        return
            str_contains($msg, 'deadlock') ||
            str_contains($msg, 'locked') ||
            str_contains($msg, 'lock wait timeout') ||
            str_contains($msg, 'database is locked') ||
            str_contains($msg, 'busy');
    }

/**
     * Führt eine SQL-Anweisung aus, die keine Daten zurückgibt
     * (z. B. `UPDATE`, `DELETE`, DDL).
     *
     * @param string $server Datenbankserver
     * @param string $sql SQL-Abfrage
     *
     * @return int 1 bei Erfolg, 0 bei Fehler
     */
    public function exec(string $server, string $sql): int {
        if (!$this->connect_db_server($server)) {
            return 0;
        }

        $max_retry = 5;
        $profile_started = microtime(true);

        for ($try = 0; $try < $max_retry; $try++) {
            try {
                $affected_rows = $this->db[$server]->exec($sql);
                $this->record_performance_query(
                    $server,
                    $sql,
                    $profile_started,
                    max(0, (int) $affected_rows),
                    true
                );
                return 1;
            } catch (PDOException $e) {
                if (empty($this->_tx[$server]) && $this->is_retryable($e) && $try < $max_retry - 1) {
                    usleep(120000 + random_int(0, 150000));
                    continue;
                }

                $this->report_db_error('sql', $server, 'SQL-Fehler', $e->getMessage(), 'sql-exec|' . $server);
                $this->record_performance_query($server, $sql, $profile_started, 0, false);
                return 0;
            }
        }

        return 0;
    }

/**
     * Führt eine rohe SELECT-Abfrage auf einem konfigurierten DB-Server aus.
     *
     * Diese Methode ist die Infrastrukturvariante fuer Faelle, in denen ein
     * normaler DD-basierter `select()` nicht passt. Der optionale DD-Name wird
     * nur fuer Performance-Messung und Kontextprotokollierung verwendet; die
     * SQL-Anweisung selbst muss bereits vollstaendig und gueltig sein.
     *
     * Beispiel:
     *
     * ```php
     * $db = dbx()->get_system_obj('dbxDB');
     * $rows = $db->select_query(
     *     'dbXsystem',
     *     'SELECT id, uname FROM user ORDER BY uname',
     *     'dbxUser|user'
     * );
     * ```
     *
     * Ergebnis:
     *
     * ```php
     * [
     *     ['id' => 1, 'uname' => 'admin'],
     * ]
     * ```
     *
     * @param string $server Logischer DB-Server aus der dbxDB-Konfiguration.
     * @param string $sql Vollstaendige SELECT-Anweisung.
     * @param string $dd Optionaler DD-Kontext fuer Timer, Logging und Diagnose.
     *
     * @return array|int Liste assoziativer Zeilen oder `-2` bei Fehler.
     */
    public function select_query(string $server, string $sql, string $dd = ''): array|int {
        $timers = $this->db_timers_start('select', $dd);

        try {
            $stmt = $this->query($server, $sql);

            if (!is_object($stmt)) {
                if ($this->get_error_status() === '') {
                    $this->report_db_error('sql', $server, 'SQL-Fehler', 'SELECT fehlgeschlagen', 'sql-select|' . $server);
                }
                return -2;
            }

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException | Exception $e) {
            $this->report_db_error('sql', $server, 'SQL-Fehler', $e->getMessage(), 'sql-select|' . $server);
            return -2;
        } finally {
            $this->db_timers_stop($timers);
        }
    }

/**
     * Entfernt rekursiv Slashes aus einem Array oder String.
     *
     * @param array|string $data Eingabedaten
     *
     * @return array|string Bereinigte Daten
     */
    private function array_stripslashes(array|string $data): array|string {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->array_stripslashes($value);
            }
        }

        if (is_string($data)) {
            $data = stripslashes($data);
        }

        return $data;
    }

/**
     * Erzeugt eine SQL-WHERE-Bedingung für eine Volltextsuche
     * über mehrere Felder.
     *
     * Beispiel
     * --------
     * ```php
     * $where = $this->create_select('max mustermann', [
     *     'name' => 'name',
     *     'city' => 'city'
     * ]);
     * ```
     *
     * @param string $select Suchbegriff(e), getrennt durch Leerzeichen
     * @param array  $flds Array der zu durchsuchenden Spalten
     * @param int    $and 1 = AND, 0 = OR
     *
     * @return string SQL-WHERE-Bedingung
     */
    public function create_select(string $select, array $flds, int $and = 1): string {
        $search_terms = array_filter(explode(' ', trim($select)));

        if (!$search_terms) {
            return '1=1';
        }

        $server = $this->_server ?: 'default';

        $where_conditions = [];

        foreach ($search_terms as $term) {
            $escaped = $this->escape($term, $server);

            $or_parts = [];

            foreach (array_keys($flds) as $field) {
                $or_parts[] = "$field LIKE '%$escaped%'";
            }

            $where_conditions[] = '(' . implode(' OR ', $or_parts) . ')';
        }

        return implode($and ? ' AND ' : ' OR ', $where_conditions);
    }

/**
     * Führt eine INSERT-Abfrage aus und gibt die zuletzt eingefügte ID zurück.
     *
     * @param string $server Name des Datenbankservers
     * @param string $sql SQL-Insert-Statement
     *
     * @return int Insert-ID oder -2 im Fehlerfall
     */
    public function insert_query($server, $sql) {
        $this->_insert_id = 0;
        $retval = -2;

        $timers = $this->db_timers_start('save');

        try {
            $stmt = $this->query($server, $sql);
        } finally {
            $this->db_timers_stop($timers);
        }

        if ($stmt) {
            $retval = $this->db[$server]->lastInsertId();
        }

        if ($retval > 0) {
            $this->_insert_count++;
        }

        $this->_insert_id = $retval;

        return $retval;
    }

/**
     * Führt eine DELETE-Abfrage aus und gibt die Anzahl
     * der betroffenen Zeilen zurück.
     *
     * @param string $server Datenbankserver
     * @param string $sql SQL-Delete-Statement
     *
     * @return int Anzahl betroffener Zeilen oder -2
     */
    public function delete_query($server, $sql) {
        $count = -2;
        $this->_delete_count = 0;

        $stmt = $this->query($server, $sql);

        if ($stmt) {
            $count = $stmt->rowCount();
        }

        dbx()->debug("delete count server=($server) count=($count) sql=($sql)");

        $this->_delete_count = $count;

        return $count;
    }

/**
     * Führt eine generische SQL-Exec-Anweisung aus.
     *
     * @param string $server Datenbankserver
     * @param string $sql SQL-Anweisung
     *
     * @return int 1 bei Erfolg, 0 bei Fehler
     */
    public function exec_query($server, $sql) {
        $ok = $this->exec($server, $sql);
        return $ok;
    }

/**
     * Führt eine rohe SQL-Abfrage auf dem angegebenen Server aus.
     *
     * Unterstützt verschiedene SQL-Abfragetypen (`SELECT`, `INSERT`,
     * `UPDATE`, `DELETE`, DDL usw.) und delegiert intern an die passende
     * Methode.
     *
     * Wichtige Stabilitätsregel
     * -------------------------
     * Der interne Transaktionsstatus (`$_tx`) wird hier nicht verändert.
     * Transaktionen werden ausschließlich über `begin()`, `commit()`
     * und `rollback()` gesteuert.
     *
     * @param string $server Datenbankserver
     * @param string $query SQL-Abfrage
     *
     * @return mixed Ergebnis der Abfrage oder Fehlercode
     */
    public function raw_query(string $server, string $query): mixed {
        if (!$server) {
            return 0;
        }

        $i_chars    = 6;
        $connect   = $this->connect_db_server($server);
        $pos       = strpos($query, ' ') ?: $i_chars;
        $query_type = strtoupper(substr(trim($query), 0, min($pos, $i_chars)));

        if (!$connect) {
            if ($this->get_error_status() === '') {
                $this->report_db_error('db', $server, 'Datenbank nicht vorhanden', 'Server not connected', 'db-raw|' . $server);
            }
            $this->_query = $query;
            return 0;
        }

        try {
            if ($query_type === 'SELECT' || $query_type === 'PRAGMA' || $query_type === 'SHOW') {
                return $this->select_query($server, $query);
            }

            if ($query_type === 'INSERT') {
                return $this->insert_query($server, $query);
            }

            if ($query_type === 'UPDATE') {
                return $this->update_query($server, $query);
            }

            if ($query_type === 'DELETE') {
                return $this->delete_query($server, $query);
            }

            $result = $this->exec_query($server, $query);

            return $result;
        } catch (PDOException $e) {
            $this->_query = $query;
            $this->report_db_error('sql', $server, 'SQL-Fehler', $e->getMessage(), 'sql-raw|' . $server);

            return 0;
        }
    }
}
