<?php

/** Automatisch aus der stabilen Legacy-Fassade extrahierte interne Verantwortung. */
trait dbxDBConnectionTrait
{
/**
     * Setzt den letzten DB-Fehlerzustand zurueck.
     *
     * @return void
     */
    public function clear_db_error(): void {
        $this->_error_status = '';
        $this->_error_text   = '';
        $this->_db_message    = '';
    }

/**
     * Liefert die Fehlerart des letzten DB-Aufrufs.
     *
     * @return string db|sql|access oder leer bei Erfolg
     */
    public function get_error_status(): string {
        return (string)$this->_error_status;
    }

/**
     * Liefert den Fehlertext des letzten DB-Aufrufs.
     *
     * @return string
     */
    public function get_error_text(): string {
        return (string)$this->_error_text;
    }

/**
     * Liefert die letzte Insert-ID nach erfolgreichem insert().
     *
     * @return int
     */
    public function get_insert_id(): int {
        return (int)$this->_insert_id;
    }

/**
     * Merkt den letzten Fehler intern.
     *
     * @param string $status db|sql|access
     * @param string $text Fehlertext
     * @return void
     */
    private function set_db_error(string $status, string $text): void {
        $this->_error_status = $status;
        $this->_error_text   = $text;
        $this->_db_message    = $text;
        $this->_error        = $text;
    }

/**
     * Setzt Fehlerzustand und schreibt optional nach dbxSysMsg.
     *
     * @param string $status db|sql|access
     * @param string $rid Betroffener Server oder DD
     * @param string $why Kurzgrund
     * @param string $what Detail
     * @param string $dedupKey Optionaler Dedup-Schluessel pro Request
     * @return void
     */
    private function report_db_error(string $status, string $rid, string $why, string $what, string $dedup_key = ''): void {
        $message = trim($why);
        if (trim($what) !== '') {
            $message .= ($message !== '' ? ': ' : '') . trim($what);
        }
        $this->set_db_error($status, $message);

        if (!(int)$this->_report_error) {
            return;
        }

        if ($dedup_key !== '') {
            if (isset($this->_reported_keys[$dedup_key])) {
                return;
            }
            $this->_reported_keys[$dedup_key] = 1;
        }

        $sys_msg_status = $status === 'access' ? 'security' : 'error';

        try {
            dbx()->sys_msg($sys_msg_status, 'db', $rid, $why, $what);
        } catch (Throwable $e) {
            $fallback = sprintf(
                'DBX database error [%s] %s (%s): %s; reporting failed: %s',
                $status,
                $why,
                $rid,
                $what,
                $e->getMessage()
            );
            error_log($fallback);
        } finally {
            // sys_msg() schreibt ueber dasselbe dbxDB-Objekt. Dessen eigener
            // erfolgreicher Insert darf den urspruenglichen DB-Fehler nicht
            // ueberschreiben, sonst bleibt die eigentliche Ursache unsichtbar.
            $this->set_db_error($status, $message);
        }
    }

/**
     * Liefert einen kurzen, benutzerlesbaren Grund fuer einen Connect-Fehler.
     */
    private function connection_error_reason(Throwable $e): string {
        $message = strtolower($e->getMessage());

        if (str_contains($message, 'unknown database')
            || str_contains($message, 'database does not exist')
            || str_contains($message, 'does not exist')
        ) {
            return 'Datenbank nicht vorhanden';
        }

        if (str_contains($message, 'access denied')
            || str_contains($message, 'authentication failed')
            || str_contains($message, 'password authentication failed')
        ) {
            return 'Datenbank-Anmeldung fehlgeschlagen';
        }

        if (str_contains($message, 'connection refused')
            || str_contains($message, 'server has gone away')
            || str_contains($message, 'lost connection')
            || str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'no connection could be made')
            || str_contains($message, 'actively refused')
            || str_contains($message, '2002')
            || str_contains($message, '2003')
        ) {
            return 'Datenbankserver nicht erreichbar';
        }

        return 'Datenbankverbindung fehlgeschlagen';
    }

/**
     * Nur bei einem tatsaechlich fehlenden Datenbank-Schema darf die
     * automatische Anlage versucht werden. Serverausfaelle und defekte
     * Systemtabellen duerfen keinen zweiten Connect-Versuch ausloesen.
     */
    private function is_missing_database_error(string $message): bool {
        $message = strtolower($message);

        return str_contains($message, 'unknown database')
            || str_contains($message, 'database does not exist')
            || str_contains($message, 'cannot open database')
            || preg_match('/(?:sqlstate\[)?3d000/', $message) === 1
            || preg_match('/(?:error|code)[^0-9]*1049/', $message) === 1;
    }

private function quote_db_identifier_for_type(string $db_type, string $name): string {
        $db_type = strtolower(trim($db_type));
        if ($db_type === 'mysql') {
            return '`' . str_replace('`', '``', $name) . '`';
        }
        if ($db_type === 'sqlsrv' || $db_type === 'dblib') {
            return '[' . str_replace(']', ']]', $name) . ']';
        }
        return '"' . str_replace('"', '""', $name) . '"';
    }

private function pdo_quote_value(PDO $pdo, string $value): string {
        $quoted = $pdo->quote($value);
        if ($quoted === false) {
            return "'" . str_replace("'", "''", $value) . "'";
        }
        return $quoted;
    }

public function can_connect_database_config(array $db_config, bool $with_database = false): int {
        $db_type = strtolower(trim((string)($db_config['type'] ?? '')));
        $db_name = trim((string)($db_config['dbname'] ?? ($db_config['name'] ?? '')));

        if ($db_type === 'sqlite') {
            $host = dbx()->config_path_resolve(rtrim((string)($db_config['host'] ?? ''), "/\\") . '/');
            if ($with_database) {
                $path = dbx()->config_path_resolve(rtrim((string)($db_config['host'] ?? ''), "/\\") . '/' . $db_name);
                return ($db_name !== '' && is_file($path)) ? 1 : 0;
            }
            return is_dir($host) ? 1 : 0;
        }

        if ($db_type === '' || ($with_database && $db_name === '')) {
            return 0;
        }

        $tmp_server = '__dbx_check_db_' . md5($db_type . $db_name . microtime(true));
        if (isset($this->db[$tmp_server])) {
            unset($this->db[$tmp_server]);
        }

        $ok = $this->db_connect(
            $tmp_server,
            $db_type,
            $db_config['host'] ?? '',
            $with_database ? $db_name : '',
            $db_config['user'] ?? '',
            $db_config['pass'] ?? '',
            $db_config['port'] ?? ''
        );

        if (isset($this->db[$tmp_server])) {
            unset($this->db[$tmp_server]);
        }

        return $ok ? 1 : 0;
    }

public function ensure_database_exists(string $server, array $db_config): int {
        $db_type = strtolower(trim((string)($db_config['type'] ?? '')));
        $db_name = (string)($db_config['dbname'] ?? ($db_config['name'] ?? ''));
        $db_name = trim($db_name);

        if ($db_name === '') {
            return 0;
        }

        if ($db_type === 'sqlite') {
            $host = dbx()->config_path_resolve(rtrim((string)($db_config['host'] ?? ''), "/\\") . '/');
            if ($host !== '' && !is_dir($host)) {
                @mkdir($host, 0777, true);
            }
            return is_dir($host) ? 1 : 0;
        }

        $admin_db = '';
        switch ($db_type) {
            case 'mysql':
                $admin_db = '';
                break;
            case 'pgsql':
                $admin_db = (string)($db_config['admin_dbname'] ?? $db_config['maintenance_db'] ?? 'postgres');
                break;
            case 'sqlsrv':
            case 'dblib':
                $admin_db = (string)($db_config['admin_dbname'] ?? $db_config['maintenance_db'] ?? 'master');
                break;
            default:
                return 0;
        }

        $tmp_server = '__dbx_create_db_' . md5($server . $db_type . microtime(true));
        if (isset($this->db[$tmp_server])) {
            unset($this->db[$tmp_server]);
        }

        $ok = $this->db_connect(
            $tmp_server,
            $db_type,
            $db_config['host'] ?? '',
            $admin_db,
            $db_config['user'] ?? '',
            $db_config['pass'] ?? '',
            $db_config['port'] ?? ''
        );

        if (!$ok || !isset($this->db[$tmp_server])) {
            if (isset($this->db[$tmp_server])) {
                unset($this->db[$tmp_server]);
            }
            return 0;
        }

        try {
            $pdo = $this->db[$tmp_server];
            switch ($db_type) {
                case 'mysql':
                    $sql = 'CREATE DATABASE IF NOT EXISTS ' . $this->quote_db_identifier_for_type($db_type, $db_name)
                         . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
                    $pdo->exec($sql);
                    break;

                case 'pgsql':
                    $exists = $pdo->query('SELECT 1 FROM pg_database WHERE datname = ' . $this->pdo_quote_value($pdo, $db_name));
                    if (!$exists || !$exists->fetchColumn()) {
                        $pdo->exec('CREATE DATABASE ' . $this->quote_db_identifier_for_type($db_type, $db_name) . " ENCODING 'UTF8'");
                    }
                    break;

                case 'sqlsrv':
                case 'dblib':
                    $literal = $this->pdo_quote_value($pdo, $db_name);
                    $sql = 'IF DB_ID(N' . $literal . ') IS NULL CREATE DATABASE ' . $this->quote_db_identifier_for_type($db_type, $db_name);
                    $pdo->exec($sql);
                    break;
            }

            unset($this->db[$tmp_server]);
            dbx()->debug("database ensured Server=($server) Type=($db_type) DB=($db_name)");
            return 1;
        } catch (PDOException $e) {
            $this->_db_message = $e->getMessage();
            $this->report_db_error('db', (string)$server, 'Datenbank nicht vorhanden', $e->getMessage(), 'db-create|' . $server);
            unset($this->db[$tmp_server]);
            return 0;
        }
    }

/**
     * Verbindet sich mit einem angegebenen Datenbankserver.
     *
     * Zweck
     * -----
     * - verbindet normale Config-Server
     * - erkennt SQLite-Dateien im Modul-/dbx-Kontext automatisch
     * - ergänzt temporär eine Laufzeit-DB-Konfiguration für `.db3`
     *
     * Unterstützte Serverangaben
     * --------------------------
     * - normaler Config-Servername
     * - `meinedb.db3`
     * - `modul|meinedb.db3`
     *
     * Suchlogik bei `.db3`
     * --------------------
     * - mit Modulpräfix: zuerst angegebenes Modul, dann Fallback `dbx`
     * - ohne Modulpräfix: zuerst aktives Modul, dann Fallback `dbx`
     * - `modul|...` nutzt das aktuell aktive Modul als Platzhalter
     *
     * Beispiel
     * --------
     * ```php
     * $this->connect_db_server('default');
     * $this->connect_db_server('crm|kunden.db3');
     * ```
     *
     * @param string $server Name des Datenbankservers
     *
     * @return int 1 bei erfolgreicher Verbindung, 0 bei Fehlschlag
     */
    public function connect_db_server(string $server): int {

        $ok = 0;
        $this->_server = 'try:' . $server;
        $this->_connected = 0;
        $this->_dbtype = '';

        // Eine bereits geoeffnete dynamische Modul-DB besitzt absichtlich
        // keinen dauerhaften Eintrag in config.php. Der bisherige erneute
        // Config-Lookup konnte deshalb beim zweiten Aufruf fehlschlagen,
        // obwohl die PDO-Verbindung weiterhin gueltig war.
        if (isset($this->db[$server]) && $this->db[$server] instanceof PDO) {
            $this->clear_db_error();
            $this->_error = '';
            $this->_connected = 1;
            $this->_server = $server;
            $this->_dbtype = $this->get_db_type($server);
            return 1;
        }

        $config = [];

        if (!isset($this->db[$server])) {
            $sqlite_modul = '';
            $sqlite_name  = '';

            if (preg_match('/\.(db3|sqlite|sqlite3)$/i', $server)) {
                $activ_modul = dbx()->get_system_var('dbx_activ_modul', 'dbx');

                if (strpos($server, '|') !== false) {
                    $parts        = explode('|', $server, 2);
                    $sqlite_modul = trim($parts[0]);
                    $sqlite_name  = trim($parts[1]);

                    if ($sqlite_modul === 'modul' || $sqlite_modul === '') {
                        $sqlite_modul = $activ_modul;
                    }
                } else {
                    $sqlite_modul = $activ_modul;
                    $sqlite_name  = $server;
                }

                $file = '';

                $file1 = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/dbx/db/' . $sqlite_name);
                $file2 = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/' . $sqlite_modul . '/db/' . $sqlite_name);

                if (file_exists($file2)) {
                    $file = $file2;
                } elseif (file_exists($file1)) {
                    $file         = $file1;
                    $sqlite_modul = 'dbx';
                } else {
                    $missing_file = $file2;
                    $this->report_db_error(
                        'db',
                        $server,
                        'Datenbank nicht vorhanden',
                        $missing_file,
                        'db-missing|' . $server
                    );
                    return 0;
                }

                if ($file) {
                    $dir = dirname($file);
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0777, true);
                    }
                    $config['db'][$server] = [
                        'type'   => 'sqlite',
                        'host'   => dbx()->config_path_store($dir . '/', true),
                        'dbname' => basename($file),
                        'user'   => '',
                        'pass'   => '',
                        'port'   => ''
                    ];

                    //dbx()->debug("sqlite server resolved Server=($server) Modul=($sqlite_modul) Name=($sqlite_name) File=($file)");
                }
            }
        }

        if (!isset($config['db'][$server])) {
            //dbx()->debug("read cfg dbx for db");
            $config = dbx()->get_cfg('dbx');
        }

        if (!isset($config['db'][$server])) {
            dbx()->debug("## no config ERROR connect_db_server Server=($server)", $config);
            $this->report_db_error(
                'db',
                $server,
                'Datenbank nicht vorhanden',
                'Keine Server-Konfiguration',
                'db-config|' . $server
            );
        } else {
            $db_config = $config['db'][$server];

            if (!$this->db_server_config_is_active($server, $db_config)) {
                $this->_db_message = 'Datenbankserver deaktiviert';
                $this->_error = $this->_db_message;
                return 0;
            }

            $_SESSION['dbx']['config']['dbx']['db'][$server] = $db_config;

            $db_name = $db_config['dbname'] ?? ($db_config['name'] ?? '');


            $ok = $this->db_connect(
                $server,
                $db_config['type'] ?? 'sqlite',
                $db_config['host'] ?? '',
                $db_name,
                $db_config['user'] ?? '',
                $db_config['pass'] ?? '',
                $db_config['port'] ?? ''
            );

            if (!$ok
                && trim((string)$db_name) !== ''
                && $this->is_missing_database_error((string)$this->_db_message)
            ) {
                $first_message = $this->_db_message;
                if ($this->ensure_database_exists($server, $db_config)) {
                    if (isset($this->db[$server])) {
                        unset($this->db[$server]);
                    }
                    $this->_db_message = '';
                    $this->_error = '';
                    $ok = $this->db_connect(
                        $server,
                        $db_config['type'] ?? 'mysql',
                        $db_config['host'] ?? '',
                        $db_name,
                        $db_config['user'] ?? '',
                        $db_config['pass'] ?? '',
                        $db_config['port'] ?? ''
                    );
                } elseif ($this->_db_message === '' && $first_message !== '') {
                    $this->_db_message = $first_message;
                }
            }
        }

        if ($ok) {
            //dbx()->debug("connect $server = ok");

            $this->clear_db_error();
            $this->_error = '';
            $db_type = $this->get_db_type($server);
            $this->_connected = 1;
            $this->_server = $server;
            $this->_dbtype = $db_type;
        }

        return $ok;
    }

/**
     * Prüft den Aktivstatus eines konfigurierten SQL-Servers.
     *
     * SQLite-/db3-Dateien sind immer aktiv. Bestehende SQL-Server ohne
     * `activ`-Eintrag gelten aus Kompatibilitaetsgruenden ebenfalls als aktiv.
     */
    public function db_server_config_is_active(string $server, array $db_config): bool {
        $type = strtolower(trim((string)($db_config['type'] ?? '')));
        $name = trim((string)($db_config['dbname'] ?? ($db_config['name'] ?? '')));

        if ($type === 'sqlite' || $type === 'sqlite3' || preg_match('/\.(db3|sqlite|sqlite3)$/i', $server . ' ' . $name)) {
            return true;
        }

        if (!array_key_exists('activ', $db_config)) {
            return true;
        }

        $active = strtolower(trim((string)$db_config['activ']));
        return !in_array($active, array('', '0', 'false', 'no', 'nein', 'off', 'deaktiv', 'inactive', 'disabled'), true);
    }

/**
     * Ermittelt den Datenbanktyp für einen Server.
     *
     * Liest die Konfiguration aus `dbx`-Config und gibt den Typ zurück.
     * Fallback ist immer `sqlite`.
     *
     * Unterstützt zusätzlich SQLite-Dateiangaben:
     * - `meinedb.db3`
     * - `modul|meinedb.db3`
     *
     * @param string $server Name des DB-Servers
     *
     * @return string Datenbanktyp (z.B. mysql, sqlite, pgsql, ...)
     */
    public function get_db_type($server) {
        $db_type = 'sqlite';
        $config = dbx()->get_cfg('dbx');

        if (isset($config['db'][$server]['type'])) {
            $db_type = $config['db'][$server]['type'];
        } elseif (preg_match('/\.(db3|sqlite|sqlite3)$/i', (string) $server)) {
            $db_type = 'sqlite';
        }

        return $db_type;
    }
}
