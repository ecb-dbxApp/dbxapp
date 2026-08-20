<?php
require_once __DIR__ . '/dbxDBPerformance.trait.php';
require_once __DIR__ . '/dbxDBConnection.trait.php';
require_once __DIR__ . '/dbxDBDataDefinition.trait.php';
require_once __DIR__ . '/dbxDBQuery.trait.php';
require_once __DIR__ . '/dbxDBCrud.trait.php';
require_once __DIR__ . '/dbxDBSchema.trait.php';
/**
 * @brief Zentrale Datenbank-, DD-, CRUD-, Rechte- und Validierungsschicht von dbxapp.
 *
 * Zentrale Datenbank- und DD-Systemklasse von DBX.
 *
 * Zweck
 * -----
 * Diese Klasse ist die zentrale Infrastruktur für:
 * - Datenbankverbindungen
 * - DD-Auflösung und DD-Cache
 * - Tabellen-/Feld-/Server-Metadaten
 * - CRUD-Operationen
 * - Access-Prüfung
 * - Feld-/Wert-Validierung
 * - Trace-/Audit-Schreibung
 * - Hilfsfunktionen für Report/Grid/Tabellen-Metadaten
 *
 * Architekturprinzip
 * ------------------
 * - DDs bleiben die fachliche Quelle für Tabellen, Felder, Rechte und Defaults.
 * - DB-Verbindungen werden lazy aufgebaut und wiederverwendet.
 * - Bestehende DBX-Mechaniken werden genutzt, nicht parallel neu erfunden.
 * - Änderungen in dieser Klasse müssen minimal, stabil und systemkonform bleiben.
 *
 * DD-Konzept
 * ----------
 * DD-Dateien liegen unter:
 * `dbx/modules/{modul}/dd/*.dd.php`
 *
 * Eine DD definiert typischerweise:
 * - `$table`
 * - optional `$fields`
 * - optional `$indexes`
 *
 * Unterstützte DD-Aufrufe
 * -----------------------
 * - `meinDD`
 * - `meinModul|meinDD`
 * - `modul|meinDD`   (`modul` = Platzhalter für aktives Modul)
 *
 * Beispiel
 * --------
 * ```php
 * $db = dbx()->get_system_obj('dbxDB');
 *
 * $rows = $db->select('kunden', "status='aktiv'");
 * $rec  = $db->select1('crm|adresse', 15);
 *
 * $ok = $db->insert('kunden', [
 *     'name'  => 'Muster GmbH',
 *     'city'  => 'Darmstadt'
 * ]);
 * $id = $ok > 0 ? $db->get_insert_id() : 0;
 *
 * $ok = $db->update('kunden', [
 *     'city' => 'Frankfurt'
 * ], 15);
 *
 * $ok = $db->save('kunden', [
 *     'name' => 'Test'
 * ], 15);
 * ```
 */
class dbxDB {

    /** Prueft ganzzahlige Werte ohne eine allgemeine Fassadenfunktion. */
    private function is_integer_value($value): bool {
        return is_int($value)
            || (is_string($value) && filter_var($value, FILTER_VALIDATE_INT) !== false);
    }

    use dbxDBPerformanceTrait;
    use dbxDBConnectionTrait;
    use dbxDBDataDefinitionTrait;
    use dbxDBQueryTrait;
    use dbxDBCrudTrait;
    use dbxDBSchemaTrait;

    public $db = array();

    public $pdo = null;

    public $_connected = 0;
    public $_server = '';
    public $_dbtype = '';

    public $_insert_id = 0;

    public $_update_count = 0;

    public $_delete_count = 0;

    public $_insert_count = 0;

    public $_db_message = '';

    public $o_validator = null;
    public $_validation_error = 0;
    public $_validation_warning = 0;
    public $_validation_error_flds = array();
    public $_validation_warning_flds = array();

    public $_validator_rules = 0;      // save insert update
    public $_validator_type = 1;       // type der Daten prüfen
    public $_validator_error = 0;      // Bei validate Fehler db error oder warning
    public $_validator_mode = 'clean'; // Bei validate Fehler daten 'clean' oder 'unset'

    public $_fld_id;

    public $_error = '';
    public $_query = '';

    /** 1 = DB-Fehler automatisch in dbxSysMsg schreiben, 0 = nicht */
    public $_report_error = 1;

    /** db|sql|access */
    public $_error_status = '';

    public $_error_text = '';

    /** Maximale Wartezeit fuer einen DB-Verbindungsaufbau in Sekunden */
    public int $_connect_timeout = 3;

    private array $_reported_keys = [];

    private array $_tx = [];

    private int $_db_timer_depth = 0;

    /** Requestlokaler Cache fuer erfolgreiche select1()-Ergebnisse, getrennt nach DD. */
    private array $_select1_cache = array();

    /** Serverzuordnung dient nur dem sicheren Abschluss DD-uebergreifender Transaktionen. */
    private array $_select1_cache_servers = array();

    private int $_select1_cache_entries = 0;

    private array $_select1_cache_stats = array(
        'hits' => 0,
        'misses' => 0,
        'stores' => 0,
        'invalidations' => 0,
        'transaction_bypass' => 0,
        'capacity_bypass' => 0,
    );

    private const SELECT1_CACHE_MAX_ENTRIES = 1000;

    /** Requestlokale, parameterfreie Query-Statistik fuer den Performance-Timer. */
    private array $_performance_queries = array();

    private int $_performance_query_count = 0;
    private int $_performance_query_slow_count = 0;
    private int $_performance_query_failed_count = 0;
    private int $_performance_query_affected_rows = 0;
    private float $_performance_query_time_ms = 0.0;
    private ?bool $_performance_query_capture = null;
    private ?int $_performance_slow_query_ms = null;

    /**
     * Initialisiert das DBX-Datenbankobjekt mit Standardwerten
     * und lädt den zentralen Validator.
     *
     */
    public function __construct() {
        $this->o_validator = dbx()->get_system_obj('dbxValidator');
        $this->db = array();
        $this->_connected = 0;
        $this->_server = '';
        $this->_insert_id = 0;
        $this->_update_count = 0;
        $this->_delete_count = 0;
        $this->_insert_count = 0;
        $this->_db_message = '';
        $this->_validation_error = 0;
        $this->_validation_warning = 0;
        $this->_validation_error_flds = array();
        $this->_validation_warning_flds = array();
        $this->_fld_id = 'id';
    }












    /**
     * Gibt interne Referenzen beim Zerstören des Objekts frei.
     *
     */
    public function __destruct() {
        $this->db = null;
    }









    /**
     * Prüft, ob eine SQLite-Datenbank aktuell gelockt ist.
     *
     * Zweck
     * -----
     * SQLite sperrt bei Schreibzugriffen die Datenbank bzw. Teile davon.
     * Diese Funktion versucht defensiv, eine Transaktion zu starten.
     * Scheitert das, wird die DB als gelockt betrachtet.
     *
     * Verhalten
     * ---------
     * - Öffnet temporäre Verbindung
     * - setzt `PRAGMA locking_mode=NORMAL`
     * - versucht `beginTransaction()`
     * - Rollback nur wenn tatsächlich eine Transaktion aktiv ist
     *
     * @param string $databasePath Vollständiger Pfad zur SQLite-Datei
     *
     * @return int 1 = Datenbank ist gelockt, 0 = frei
     */
    function is_sqlite_database_locked($database_path) {
        $is_locked = 0;

        try {
            $pdo = new PDO("sqlite:$database_path");
            $pdo->exec('PRAGMA locking_mode=NORMAL');
            $pdo->beginTransaction();
        } catch (PDOException $e) {
            $is_locked = 1;
        } finally {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }

        if ($is_locked) {
            dbx()->debug("SQLITE db ($database_path) Lock=($is_locked)");
        }

        return $is_locked;
    }

    /**
     * Stellt eine Verbindung zu einer Datenbank her und speichert sie
     * in `$this->db[$server]`.
     *
     * Unterstützte Datenbanktypen
     * ---------------------------
     * `sqlite`, `mysql`, `pgsql`, `sqlsrv`, `oci`, `firebird`,
     * `cubrid`, `dblib`, `ibm`, `informix`, `odbc`.
     *
     * Stabilitätsregel
     * ----------------
     * Bei unbekanntem Datenbanktyp wird sauber ein Fehler gesetzt und
     * protokolliert, ohne unkontrolliert nach außen abzubrechen.
     *
     * @param string $server Name des Servers (Schlüssel im `$this->db`-Array)
     * @param string $dbType Typ der Datenbank
     * @param string $dbHost Hostname oder Datei für SQLite
     * @param string $dbName Name der Datenbank
     * @param string $dbUser Benutzername
     * @param string $dbPass Passwort
     * @param string $dbPort Port
     *
     * @return int 1 bei Erfolg, 0 bei Fehler
     */
    function db_connect($server, $db_type, $db_host, $db_name = '', $db_user = '', $db_pass = '', $db_port = '') {
        $ok = 1;
        //dbx()->debug("#dbConnect Server=($server) Type=($dbType) Host=($dbHost) dbName=($dbName)", $this->db[$server] ?? null);

        if (!isset($this->db[$server])) {
            try {
                switch ($db_type) {
                    case 'sqlite':
                        $db_name = dbx()->config_path_resolve($db_host . $db_name);
                        $this->db[$server] = new PDO("sqlite:$db_name");
                        break;

                    case 'mysql':

                        $dsn = "mysql:host=$db_host";
                        if ($db_port !== '') {
                            $dsn .= ";port=$db_port";
                        }
                        if ($db_name !== '') {
                            $dsn .= ";dbname=$db_name";
                        }
                        $dsn .= ";charset=utf8mb4";
                        $this->db[$server] = new PDO($dsn, $db_user, $db_pass, [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                            PDO::ATTR_TIMEOUT => max(1, $this->_connect_timeout),
                        ]);

                        break;

                    case 'pgsql':
                        $this->db[$server] = new PDO("pgsql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
                        break;

                    case 'sqlsrv':
                        $this->db[$server] = new PDO("sqlsrv:Server=$db_host;Database=$db_name", $db_user, $db_pass);
                        break;

                    case 'oci':
                        $dbtns = "(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = //$db_host)(PORT = $db_port)) 
                             (CONNECT_DATA = (SERVICE_NAME = $db_name) ))";

                        $this->db[$server] = new PDO("oci:dbname=$dbtns;charset=utf8", $db_user, $db_pass, [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_EMULATE_PREPARES => false,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                        ]);
                        break;

                    case 'firebird':
                        $this->db[$server] = new PDO("firebird:dbname=$db_host:$db_name", $db_user, $db_pass);
                        break;

                    case 'cubrid':
                        $this->db[$server] = new PDO("cubrid:host=$db_host;dbname=$db_name", $db_user, $db_pass);
                        break;

                    case 'dblib':
                        $this->db[$server] = new PDO("dblib:host=$db_host;dbname=$db_name", $db_user, $db_pass);
                        break;

                    case 'ibm':
                        $this->db[$server] = new PDO("ibm:DRIVER={IBM DB2 ODBC DRIVER};DATABASE=$db_name;HOSTNAME=$db_host;PORT=$db_port;PROTOCOL=TCPIP;UID=$db_user;PWD=$db_pass;");
                        break;

                    case 'informix':
                        $this->db[$server] = new PDO("informix:host=$db_host;service=$db_port;database=$db_name;server=$server;protocol=onsoctcp;UID=$db_user;PWD=$db_pass");
                        break;

                    case 'odbc':
                        $this->db[$server] = new PDO("odbc:$db_name", $db_user, $db_pass);
                        break;

                    default:
                        throw new PDOException("Unsupported database type: $db_type");
                }

                $this->db[$server]->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (Throwable $e) {
                $ok = 0;
                $db_message = $e->getMessage();
                unset($this->db[$server]);
                $this->_query = "Connect Server ($server) Type=($db_type) Host=($db_host) dbName=($db_name) dbUser=($db_user) Port=($db_port)";

                $this->report_db_error(
                    'db',
                    (string)$server,
                    $this->connection_error_reason($e),
                    $db_message,
                    'db-connect|' . $server
                );
            }
        }

        return $ok;
    }











    /**
     * Gibt den Tabellennamen oder die komplette Table-Definition
     * einer DD zurück.
     *
     * - `rec = 0` → nur Tabellenname
     * - `rec = 1` → komplette Table-Definition
     *
     * Bei fehlender oder ungültiger DD wird `0` zurückgegeben.
     *
     * @param string $dd Name der Datenbeschreibung
     * @param int    $rec 0 = nur Tabellenname, 1 = komplette Table-Definition
     *
     * @return mixed Tabellenname, Table-Definition oder 0
     */
    function get_dd_table($dd, $rec = 0) {
        $dd_table = 0;

        $dd_sys    = $this->load_dd($dd);
        $dd_status = $dd_sys['dd_status'] ?? 0;
        $dd_modul  = $dd_sys['dd_modul'] ?? '';
        $dd_name   = $dd_sys['dd_name'] ?? '';

        if ($dd_status == 1 && !$rec) {
            $dd_table = $_SESSION['dbx']['cache']['dd'][$dd_modul][$dd_name]['table']['table'] ?? 0;
        }

        if ($dd_status == 1 && $rec) {
            $dd_table = $_SESSION['dbx']['cache']['dd'][$dd_modul][$dd_name]['table'] ?? 0;
            if (is_array($dd_table)) {
                $dd_table['server'] = $this->get_dd_server((string)$dd);
            }
        }

        return $dd_table;
    }

    /**
     * Gibt das Autosync-Flag einer DD zurück.
     *
     * - `rec = 0` → nur der Autosync-Wert
     * - `rec = 1` → kompletter Autosync-Bereich, falls vorhanden
     *
     * @param string $dd Name der Datenbeschreibung
     * @param int    $rec 0 = Wert, 1 = kompletter Datensatzbereich
     *
     * @return mixed Autosync-Wert oder 0
     */
    function get_dd_autosync($dd, $rec = 0) {
        $dd_sync = 0;

        $dd_sys    = $this->load_dd($dd);
        $dd_status = $dd_sys['dd_status'] ?? 0;
        $dd_modul  = $dd_sys['dd_modul'] ?? '';
        $dd_name   = $dd_sys['dd_name'] ?? '';

        if ($dd_status == 1 && !$rec) {
            $dd_sync = $_SESSION['dbx']['cache']['dd'][$dd_modul][$dd_name]['table']['autosync'] ?? 0;
        }

        if ($dd_status == 1 && $rec) {
            $dd_sync = $_SESSION['dbx']['cache']['dd'][$dd_modul][$dd_name]['autosync'] ?? 0;
        }

        return $dd_sync;
    }





    /**
     * Erstellt einen neuen Datensatz mit Standardwerten.
     *
     * Falls `field_values` nicht angegeben ist, wird der leere Datensatz
     * des DD-Objekts verwendet. Nicht gesetzte Felder erhalten Standardwerte.
     *
     * @param string $dd Datenbank-Objekt
     * @param array  $field_values Initialwerte
     *
     * @return array Neuer Datensatz mit Standardwerten
     */
    function get_new_record(string $dd, array $field_values = []): array {
        $empty = $this->empty_record($dd)[0] ?? [];

        foreach ($empty as $field => $value) {
            if (!isset($field_values[$field])) {
                $field_values[$field] = $value;
            }
        }

        if (($field_values['id'] ?? 0) <= 0) {
            unset($field_values['id']);
        }

        return $field_values;
    }

    /**
     * Fügt einen neuen Datensatz in die Datenbank ein.
     *
     * Ablauf
     * ------
     * 1. Optional: Setzt System-/Audit-Felder
     * 2. Ergänzt Standardwerte
     * 3. Prüft Zugriff
     * 4. Validiert Felder und Werte
     * 5. Führt INSERT aus
     * 6. Optional: Schreibt Trace-Eintrag
     *
     * Bei retry-fähigen Lock-/Deadlock-Fehlern wird außerhalb aktiver
     * Transaktionen mehrfach erneut versucht.
     *
     * @param string $dd Datenbank-Definition
     * @param array  $field_values Zu speichernde Werte
     * @param int    $verify_access Zugriff prüfen
     * @param int    $verify_fields Felder prüfen
     * @param int    $verify_values Werte prüfen
     * @param int    $trace Trace aktivieren
     *
     * Beispiel:
     * ```php
     * $ok = $db->insert('dbxUser', [
     *    'name' => 'Admin',
     *    'email' => 'admin@example.test'
     * ]);
     * $rid = $ok > 0 ? $db->get_insert_id() : 0;
     * ```
     *
     * @return int 1 = Erfolg (Insert-ID separat ueber get_insert_id()), 0 = Validierungsfehler, -1 = Zugriffsfehler, -2 = DB-Fehler
     */
    function insert($dd, $field_values, $verify_access = 1, $verify_fields = 1, $verify_values = 1, $trace = 1) {
        $this->clear_db_error();
        $this->_insert_id = 0;
        $this->_validation_error = 0;
        $this->_validation_warning = 0;

        $server = $this->get_dd_server($dd);
        $tab    = $this->get_dd_table($dd);

        if ($dd !== 'dbxTrace') {
            $uid = dbx()->user();
            $now = $this->now_ms();

            $field_values['update_date'] = $now;
            $field_values['update_uid']  = $uid;

            $field_values += [
                'create_date' => $now,
                'create_uid'  => $uid,
                'owner'       => $uid
            ];
        }

        if ($dd !== 'dbxTrace') {
            $field_values = $this->get_new_record($dd, $field_values);
        }

        if ($verify_access && ($this->check_access('insert', $dd) !== 1)) {
            $this->report_db_error('access', (string)$dd, 'Zugriff verweigert', 'insert', 'access-insert|' . $dd);
            return -1;
        }

        if ($verify_fields) {
            $field_values = $this->check_fields($dd, $field_values);
        }

        if ($verify_values) {
            $field_values = $this->check_values($dd, $field_values);
        }

        if ($this->_validation_error) {
            return 0;
        }

        if (isset($field_values['id']) && $field_values['id'] == 0) {
            unset($field_values['id']);
        }

        if (!$this->connect_db_server($server)) {
            if ($this->get_error_status() === '') {
                $this->report_db_error('db', (string)$server, 'Datenbank nicht vorhanden', (string)$server, 'db-insert|' . $server);
            }
            return -2;
        }

        $fields       = array_keys($field_values);
        $placeholders = array_fill(0, count($fields), '?');

        $sql = "INSERT INTO $tab (" .
            implode(',', $fields) .
            ") VALUES (" .
            implode(',', $placeholders) .
            ")";

        $max_retry = 5;
        $profile_started = microtime(true);

        for ($try = 0; $try < $max_retry; $try++) {
            try {
                $timers = $this->db_timers_start('save', (string) $dd);

                try {
                    $stmt = $this->db[$server]->prepare($sql);
                    $stmt->execute(array_values($field_values));

                    $id = $this->db[$server]->lastInsertId();
                    $this->record_performance_query($server, $sql, $profile_started, 1, true);
                } finally {
                    $this->db_timers_stop($timers);
                }

                if (!$id) {
                    $pk = $this->get_dd_primary($dd);

                    if (!$id && !empty($field_values[$pk])) {
                        $id = $field_values[$pk];
                    }
                }

                $this->_insert_id = $id;
                $this->invalidate_select1_cache((string)$dd);

                if ($id > 0 && $dd !== 'dbxTrace') {
                    $this->_insert_count++;
                }

                if ($trace && $dd !== 'dbxTrace') {
                    $table_def   = $this->get_dd_table_def($dd);
                    $write_trace = $table_def['trace'] ?? 0;

                    if ($write_trace) {
                        $uid   = dbx()->user();
                        $now   = $this->now_ms();
                        $modul = dbx()->get_system_var('dbx_activ_modul', 'dbx');
                        $run1  = dbx()->get_modul_var('dbx_run1');
                        $run2  = dbx()->get_modul_var('dbx_run2');
                        $run3  = dbx()->get_modul_var('dbx_run3');
                        $source = ($uid > 0) ? 'user' : 'system';

                        $trace_data = [
                            'create_date' => $now,
                            'create_uid'  => $uid,
                            'update_date' => $now,
                            'update_uid'  => $uid,
                            'owner'       => $uid,
                            'action'      => 'insert',
                            'dd'          => $dd,
                            'record_id'   => $id,
                            'data_json'   => json_encode([
                                'action' => 'insert',
                                'dd'     => $dd,
                                'table'  => $tab,
                                'uid'    => $uid,
                                'source' => $source,
                                'modul'  => $modul,
                                'run1'   => $run1,
                                'run2'   => $run2,
                                'run3'   => $run3,
                                'id'     => $id,
                                'before' => null,
                                'delta'  => $field_values
                            ], JSON_UNESCAPED_UNICODE)
                        ];

                        $trace_result = $this->insert('dbxTrace', $trace_data, 0, 0, 0, 0);

                        if ($trace_result !== 1) {
                            dbx()->sys_msg(
                                'warning',
                                'trace',
                                'dbxTrace',
                                'trace insert failed',
                                json_encode($trace_data, JSON_UNESCAPED_UNICODE)
                            );
                        }

                        // Der rekursive Trace-Insert darf die Insert-ID des
                        // eigentlichen Datensatzes nicht überschreiben.
                        $this->_insert_id = $id;
                    }
                }

                return 1;
            } catch (PDOException $e) {
                if (empty($this->_tx[$server]) && $this->is_retryable($e) && $try < $max_retry - 1) {
                    usleep(120000 + random_int(0, 150000));
                    continue;
                }

                $this->report_db_error('sql', (string)$server, 'SQL-Fehler', $e->getMessage(), 'sql-insert|' . $server);
                $this->record_performance_query($server, $sql, $profile_started, 0, false);

                return -2;
            }
        }

        return -2;
    }

    /**
     * Führt ein UPDATE-Statement auf einer Tabelle aus.
     *
     * Bei retry-fähigen Lock-/Deadlock-Fehlern wird außerhalb aktiver
     * Transaktionen mehrfach erneut versucht.
     *
     * Ablauf
     * ------
     * - Access prüfen
     * - WHERE normalisieren
     * - optional Before-Rows für Trace laden
     * - Felder/Werte prüfen
     * - UPDATE ausführen
     * - Delta-basiert Trace schreiben
     *
     * @param string $dd Datenbankdefinition oder Tabellenname
     * @param array  $field_values Zu aktualisierende Werte
     * @param string $where WHERE-Bedingung
     * @param int    $verify_access Zugriff prüfen
     * @param int    $verify_fields Felder prüfen
     * @param int    $verify_values Werte prüfen
     * @param int    $trace Trace aktivieren
     *
     * Beispiel:
     * ```php
     * $ok = $db->update('dbxUser', ['active' => 1], 15);
     * ```
     *
     * @return int 1 = geaendert, 0 = nichts geaendert, -1 = Zugriff, -2 = DB-Fehler
     */
    function update($dd, $field_values, $where, $verify_access = 1, $verify_fields = 1, $verify_values = 1, $trace = 1) {
        $this->clear_db_error();
        $this->_validation_error = 0;
        $this->_validation_warning = 0;

        $access = 1;
        $owner  = 0;
        $server = $this->get_dd_server($dd);
        $tab    = $this->get_dd_table($dd);
        $uid    = dbx()->user();
        $now    = $this->now_ms();
        $pk     = $this->get_dd_primary($dd);

        $field_values['update_date'] = $now;
        $field_values['update_uid']  = $uid;

        if ($verify_access) {
            $access = $this->check_access('update', $dd);

            if ($access === 2) {
                $owner = 1;
            }
        }

        if (!$access) {
            $this->report_db_error('access', (string)$dd, 'Zugriff verweigert', 'update', 'access-update|' . $dd);
            return -1;
        }

        $where = $this->normalize_where($dd, $where, $owner);

        $before_rows   = [];
        $write_trace  = 0;
        $modul        = '';
        $run1         = '';
        $run2         = '';
        $run3         = '';

        if ($trace) {
            $table_def   = $this->get_dd_table_def($dd);
            $write_trace = $table_def['trace'] ?? 0;

            if ($write_trace) {
                $modul = dbx()->get_system_var('dbx_activ_modul', 'dbx');
                $run1  = dbx()->get_modul_var('dbx_run1');
                $run2  = dbx()->get_modul_var('dbx_run2');
                $run3  = dbx()->get_modul_var('dbx_run3');
                $source = ($uid > 0) ? 'user' : 'system';
                $before_rows = $this->select($dd, $where);
            }
        }

        if ($verify_fields) {
            $field_values = $this->check_fields($dd, $field_values);
        }

        if ($verify_values) {
            $field_values = $this->check_values($dd, $field_values);
        }

        if ($this->_validation_error) {
            return 0;
        }

        if (!$this->connect_db_server($server)) {
            if ($this->get_error_status() === '') {
                $this->report_db_error('db', (string)$server, 'Datenbank nicht vorhanden', (string)$server, 'db-update|' . $server);
            }
            return -2;
        }

        $set  = [];
        $vals = [];

        foreach ($field_values as $field => $value) {
            if (is_array($value)) {
                $value = $this->get_convert_array($field, $value, 'auto');
            }

            $set[]  = "$field = ?";
            $vals[] = $value;
        }

        $sql = "UPDATE $tab SET " . implode(',', $set) . " WHERE $where";

        $max_retry = 5;
        $profile_started = microtime(true);

        for ($try = 0; $try < $max_retry; $try++) {
            try {
                $timers = $this->db_timers_start('save', (string) $dd);

                try {
                    $stmt = $this->db[$server]->prepare($sql);
                    $stmt->execute($vals);

                    $count = $stmt->rowCount();
                    $this->record_performance_query($server, $sql, $profile_started, max(0, (int) $count), true);
                } finally {
                    $this->db_timers_stop($timers);
                }

                $this->_update_count = $count;
                $this->invalidate_select1_cache((string)$dd);

                if ($trace && $write_trace && is_array($before_rows)) {
                    foreach ($before_rows as $row) {
                        $id = $row[$pk] ?? null;

                        $delta          = [];
                        $before_changed = [];

                        foreach ($field_values as $k => $v) {
                            $old = $row[$k] ?? null;

                            if ((string) $old !== (string) $v) {
                                $delta[$k] = $v;
                                $before_changed[$k] = $old;
                            }
                        }

                        if (!empty($delta)) {
                            $rec = json_encode([
                                'action' => 'update',
                                'dd'     => $dd,
                                'table'  => $tab,
                                'uid'    => $uid,
                                'source' => $source,
                                'modul'  => $modul,
                                'run1'   => $run1,
                                'run2'   => $run2,
                                'run3'   => $run3,
                                'id'     => $id,
                                'before' => $before_changed,
                                'delta'  => $delta
                            ], JSON_UNESCAPED_UNICODE);

                            $this->insert('dbxTrace', [
                                'create_date' => $now,
                                'create_uid'  => $uid,
                                'update_date' => $now,
                                'update_uid'  => $uid,
                                'owner'       => $uid,
                                'action'      => 'update',
                                'dd'          => $dd,
                                'record_id'   => $id,
                                'data_json'   => $rec
                            ], 0, 0, 0, 0);
                        }
                    }
                }

                return 1;
            } catch (PDOException $e) {
                if (empty($this->_tx[$server]) && $this->is_retryable($e) && $try < $max_retry - 1) {
                    usleep(120000 + random_int(0, 150000));
                    continue;
                }

                $this->report_db_error('sql', (string)$server, 'SQL-Fehler', $e->getMessage(), 'sql-update|' . $server);
                $this->record_performance_query($server, $sql, $profile_started, 0, false);

                return -2;
            }
        }

        return -2;
    }


    /**
     * Speichert einen Datensatz mit UPSERT-ähnlichem Verhalten.
     *
     * Ablauf
     * ------
     * - wenn WHERE vorhanden: zuerst UPDATE
     * - `UPDATE = 1`  → Erfolg
     * - `UPDATE = -1/-2` → direkt zurück
     * - `UPDATE = 0` → prüfen, ob Datensatz bereits existiert
     * - INSERT nur dann, wenn anhand der WHERE-Bedingung kein Datensatz existiert
     *
     * Zweck
     * -----
     * Verhindert das frühere Fehlverhalten:
     * - `UPDATE = 0` bedeutete früher automatisch `INSERT`
     * - das konnte bei unveränderten Datensätzen zu Doppel-Insert führen
     *
     * @param string $dd Datenbankdefinition
     * @param array  $field_values Zu speichernde Werte
     * @param string $where WHERE-Bedingung
     * @param int    $verify_access Zugriff prüfen
     * @param int    $verify_fields Felder prüfen
     * @param int    $verify_values Werte prüfen
     * @param int    $trace Trace aktivieren
     *
     * Beispiel:
     * ```php
     * $ok = $db->save('dbxConfig', ['value' => 'blue'], "name='default_color'");
     * ```
     *
     * @return int 1 = Erfolg (bei Insert-Fallback: Insert-ID separat ueber get_insert_id()), 0 = Validierungsfehler/nichts geaendert, -1 = Zugriffsfehler, -2 = DB-Fehler
     */
    function save($dd, $field_values, $where, $verify_access = 1, $verify_fields = 1, $verify_values = 1, $trace = 1) {
        $this->clear_db_error();
        $ok     = 0;
        $owner  = 0;
        $access = 1;

        if ($verify_access) {
            $access = $this->check_access('update', $dd);

            if ($access === 2) {
                $owner = 1;
            }
        }

        $where = $this->check_where($where, $owner, $dd);

        if ($where) {
            $ok = $this->update($dd, $field_values, $where, $verify_access, $verify_fields, $verify_values, $trace);

            if ($ok === 1) {
                if ($this->_update_count > 0) {
                    return 1;
                }

                $exists = $this->count($dd, $where);
                if ($this->get_error_status() !== '') {
                    return 0;
                }
                if ($exists > 0) {
                    return 1;
                }
            } else {
                return $ok;
            }
        }

        $ok = $this->insert($dd, $field_values, $verify_access, $verify_fields, $verify_values, $trace);

        if (!$ok) {
            dbx()->debug("#SAVE# ($ok) dd=($dd) W=($where)");
            dbx()->debug("#SAVE# Fields", $field_values);
        }

        return $ok;
    }

    /**
     * Löscht Datensätze aus einer Tabelle anhand einer WHERE-Bedingung.
     *
     * Ablauf
     * ------
     * - Access prüfen
     * - WHERE normalisieren
     * - optional Before-Rows für Trace lesen
     * - DELETE ausführen
     * - optional Trace mit gelöschten Vorwerten schreiben
     *
     * @param string $dd Datenbeschreibung
     * @param mixed  $where WHERE-Bedingung
     * @param int    $verify_access Zugriff prüfen
     * @param int    $trace Trace aktivieren
     *
     * @return int 1|0|-1|-2
     */
    function delete($dd, $where, $verify_access = 1, $trace = 1) {
        $ok     = -1;
        $access = 1;
        $owner  = 0;
        $server = $this->get_dd_server($dd);
        $tab    = $this->get_dd_table($dd);

        if (!$where) {
            dbx()->sys_msg(
                'warning',
                'db',
                $dd,
                'empty where',
                'delete blocked'
            );
            return 0;
        }

        if ($verify_access) {
            $access = $this->check_access('delete', $dd);

            if ($access === 2) {
                $owner = 1;
            }
        }

        $before_rows  = [];
        $write_trace = 0;
        $modul       = '';
        $run1        = '';
        $run2        = '';
        $run3        = '';
        $uid         = 0;
        $now         = '';

        if ($access) {
            $where = $this->normalize_where($dd, $where, $owner);

            if ($trace) {
                $table_def   = $this->get_dd_table_def($dd);
                $write_trace = $table_def['trace'] ?? 0;

                if (!$write_trace) {
                    $write_trace = $table_def['trash'] ?? 0;
                }

                if ($write_trace) {
                    $before_rows = $this->select($dd, $where);

                    $uid   = dbx()->user();
                    $now   = $this->now_ms();
                    $modul = dbx()->get_system_var('dbx_activ_modul', 'dbx');
                    $run1  = dbx()->get_modul_var('dbx_run1');
                    $run2  = dbx()->get_modul_var('dbx_run2');
                    $run3  = dbx()->get_modul_var('dbx_run3');
                    $source = ($uid > 0) ? 'user' : 'system';
                }
            }

            $query = 'DELETE FROM ' . $tab . ' WHERE ' . $where . ';';

            $count = $this->delete_query($server, $query);

            if ($count < 0) {
                return -2;
            }

            $this->invalidate_select1_cache((string)$dd);
            $ok = ($count > 0) ? 1 : 0;

            if ($trace && $write_trace && is_array($before_rows)) {
                $pk = $this->get_dd_primary($dd);

                foreach ($before_rows as $row) {
                    $id = $row[$pk] ?? null;

                    $rec = json_encode([
                        'action' => 'delete',
                        'dd'     => $dd,
                        'table'  => $tab,
                        'uid'    => $uid,
                        'source' => $source,
                        'modul'  => $modul,
                        'run1'   => $run1,
                        'run2'   => $run2,
                        'run3'   => $run3,
                        'id'     => $id,
                        'before' => $row,
                        'delta'  => null
                    ], JSON_UNESCAPED_UNICODE);

                    $this->insert('dbxTrace', [
                        'create_date' => $now,
                        'create_uid'  => $uid,
                        'update_date' => $now,
                        'update_uid'  => $uid,
                        'owner'       => $uid,
                        'action'      => 'delete',
                        'dd'          => $dd,
                        'record_id'   => $id,
                        'data_json'   => $rec
                    ], 0, 0, 0, 0);
                }
            }
        }

        return $ok;
    }



    /**
     * Leert eine Tabelle anhand ihrer DD.
     *
     * Je nach DB-Typ werden passende Statements verwendet.
     *
     * @param string $dd Datenbeschreibung
     *
     * @return int 1 bei Erfolg, 0 bei Fehler
     */
    public function empty($dd) {
        $ok     = $this->load_dd($dd);
        $server = $this->get_dd_server($dd);
        $dbtab  = $this->get_dd_table($dd);
        $db_type = $this->get_db_type($server);

        dbx()->debug("#empty dd=($dd) Server=($server) Tab=($dbtab) Type=($db_type)");

        if (!$dbtab || !$server || !$db_type) {
            return 0;
        }

        switch ($db_type) {
            case 'mysql':
                $sql = "TRUNCATE TABLE $dbtab";
                $ok = $this->raw_query($server, $sql);

                if ($ok) {
                    $sql = "ALTER TABLE $dbtab AUTO_INCREMENT = 1";
                    $this->raw_query($server, $sql);
                }
                break;

            case 'sqlite':
                dbx()->debug("#truncate Server=($server) Tab=($dbtab)");

                $sql = "DELETE FROM $dbtab";
                $ok = $this->exec_query($server, $sql);

                if ($ok && $this->sqlite_sequence_exists($server)) {
                    $sql = "DELETE FROM sqlite_sequence WHERE name='$dbtab'";
                    $this->exec_query($server, $sql);
                }

                dbx()->debug("truncate=($ok)");
                break;

            case 'pgsql':
                $sql = "TRUNCATE TABLE $dbtab RESTART IDENTITY";
                $ok = $this->raw_query($server, $sql);
                break;

            case 'sqlsrv':
                $sql = "TRUNCATE TABLE $dbtab";
                $ok = $this->raw_query($server, $sql);
                break;

            case 'oci':
            case 'firebird':
            case 'cubrid':
            case 'dblib':
            case 'ibm':
            case 'informix':
            case 'odbc':
                $sql = "DELETE FROM $dbtab";
                $ok = $this->raw_query($server, $sql);
                break;

            default:
                dbx()->sys_msg(
                    'warning',
                    'dd',
                    $dd,
                    "no type def ($db_type)",
                    'check'
                );

                $ok = 0;
        }

        return $ok;
    }





    /**
     * Prüft, ob eine Tabelle existiert.
     *
     * - Bei DD-Aufruf werden Tabelle und Server aus der DD ermittelt.
     * - Bei explizitem Tabellenname in `$dbtab` wird `$dd` als Servername behandelt.
     * - Fehlende Datenbanken werden als SysMsg mit klarem `why` gemeldet,
     *   nicht als SQL-Query-Fehler.
     *
     * @param string $dd DD-Name oder Servername
     * @param string $dbtab Optional expliziter Tabellenname
     * @param bool $reportMissing Fehlende DB oder Tabelle als Systemmeldung protokollieren
     *
     * @return int 1 wenn Tabelle existiert, sonst 0
     */
    function get_table_exist($dd, $dbtab = '', bool $report_missing = true) {
        $rid = (string)$dd;

        if (!$dbtab) {
            $dbtab  = $this->get_dd_table($dd);
            $server = $this->get_dd_server($dd);
            $rid    = (string)$dd;
        } else {
            $server = $dd;
            $rid    = (string)$server;
        }

        if (!$server || !$dbtab) {
            return 0;
        }

        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string)$dbtab)) {
            return 0;
        }

        $db_type = $this->get_db_type($server);

        if ($db_type === 'sqlite') {
            $db_file = $this->resolve_sqlite_db_path($server);
            if ($db_file !== '' && !is_file($db_file)) {
                if (!$report_missing) return 0;
                $this->log_db_schema_issue(
                    'db-missing|' . $server,
                    $rid,
                    'Datenbank nicht vorhanden',
                    $db_file
                );
                return 0;
            }
        }

        if (!$this->connect_db_server($server)) {
            if (!$report_missing) return 0;
            $this->log_db_schema_issue(
                'db-connect|' . $server,
                $rid,
                'Datenbank nicht vorhanden',
                trim((string)$this->_db_message) !== '' ? (string)$this->_db_message : (string)$server
            );
            return 0;
        }

        if (!isset($this->db[$server]) || !is_object($this->db[$server])) {
            if (!$report_missing) return 0;
            $this->log_db_schema_issue(
                'db-handle|' . $server,
                $rid,
                'Datenbank nicht vorhanden',
                (string)$server
            );
            return 0;
        }

        try {
            $exists = 0;

            switch ($db_type) {
                case 'sqlite':
                    $stmt = $this->db[$server]->prepare(
                        "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1"
                    );
                    $stmt->execute(array($dbtab));
                    $exists = $stmt->fetchColumn() ? 1 : 0;
                    break;

                case 'mysql':
                    $stmt = $this->db[$server]->prepare('SHOW TABLES LIKE ?');
                    $stmt->execute(array($dbtab));
                    $exists = $stmt->fetchColumn() ? 1 : 0;
                    break;

                case 'pgsql':
                    $stmt = $this->db[$server]->prepare(
                        "SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = ? LIMIT 1"
                    );
                    $stmt->execute(array($dbtab));
                    $exists = $stmt->fetchColumn() ? 1 : 0;
                    break;

                case 'oci':
                    $stmt = $this->db[$server]->prepare(
                        'SELECT 1 FROM user_tables WHERE table_name = ? AND ROWNUM = 1'
                    );
                    $stmt->execute(array(strtoupper($dbtab)));
                    $exists = $stmt->fetchColumn() ? 1 : 0;
                    break;

                case 'sqlsrv':
                    $stmt = $this->db[$server]->prepare(
                        "SELECT 1 FROM information_schema.tables WHERE table_name = ?"
                    );
                    $stmt->execute(array($dbtab));
                    $exists = $stmt->fetchColumn() ? 1 : 0;
                    break;

                default:
                    $stmt = $this->db[$server]->prepare('SELECT 1 FROM ' . $dbtab . ' WHERE 1 = 0 LIMIT 1');
                    $stmt->execute();
                    $exists = 1;
                    break;
            }

            if (!$exists && $report_missing) {
                $why  = 'Tabelle nicht vorhanden';
                $what = $dbtab . ' (' . $server . ')';

                if ($db_type === 'sqlite') {
                    $count_stmt = $this->db[$server]->query(
                        "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"
                    );
                    $table_count = $count_stmt ? (int)$count_stmt->fetchColumn() : 0;
                    if ($table_count === 0) {
                        $why  = 'Datenbank nicht vorhanden';
                        $what = $this->resolve_sqlite_db_path($server) ?: (string)$server;
                    }
                }

                $this->log_db_schema_issue(
                    'table-missing|' . $server . '|' . $dbtab,
                    $rid,
                    $why,
                    $what
                );
            }

            return $exists;
        } catch (PDOException $e) {
            if (!$report_missing) return 0;
            $this->log_db_schema_issue(
                'db-schema|' . $server . '|' . $dbtab,
                $rid,
                'Datenbank nicht vorhanden',
                trim((string)$e->getMessage()) !== '' ? (string)$e->getMessage() : ($dbtab . ' (' . $server . ')')
            );
            return 0;
        }
    }










    /**
     * Konvertiert Array-Werte automatisch in String-/Serialisierungsformate.
     *
     * Modi
     * ----
     * - `auto`
     * - `serial`
     * - `list`
     *
     * Beispiel
     * --------
     * ```php
     * $value = $this->get_convert_array('tags', ['a', 'b'], 'auto');
     * // Ergebnis: "a,b"
     * ```
     *
     * @param string $field Feldname
     * @param mixed  $array Wert oder Array
     * @param string $convert Modus: auto|serial|list
     *
     * @return mixed Konvertierter Wert
     */
    function get_convert_array($field, $array, $convert = 'auto') {
        $value = '';

        if ($convert == 'auto') {
            if (is_string($array) && str_starts_with($array, 'a:')) {
                $convert = 'serial';
            } elseif (is_array($array)) {
                foreach ($array as $val) {
                    if (is_array($val)) {
                        $convert = 'serial';
                        break;
                    }
                }

                if ($convert == 'auto') {
                    $convert = 'list';
                }
            } else {
                $convert = 'serial';
            }
        }

        if ($convert == 'serial' && is_string($array)) {
            // DD-Werte dürfen Arrays, aber niemals PHP-Objekte rekonstruieren.
            // Dadurch bleibt die historische Array-Konvertierung erhalten,
            // ohne Deserialisierungs-Hooks fremder Klassen auszuführen.
            $unserialized = @unserialize($array, array('allowed_classes' => false));

            if ($unserialized !== false && is_array($unserialized)) {
                $array = $unserialized;

                foreach ($array as $val) {
                    if (is_array($val)) {
                        return serialize($array);
                    }
                }

                $convert = 'list';
            }
        }

        if ($convert == 'list' && is_array($array)) {
            $value = implode(',', $array);
        } else {
            $value = $array;
        }

        return $value == 'a:0:{}' ? '' : $value;
    }

    /**
     * Prüft die Zugriffsrechte für eine Operation auf einer DD.
     *
     * Zugriffssystem
     * --------------
     * - 0 = kein Zugriff
     * - 1 = voller Zugriff
     * - 2 = Owner-Zugriff
     *
     * Verwendete DD-Rechte
     * --------------------
     * - insert → `create`
     * - update → `update`
     * - delete → `delete`
     * - select → `read`
     *
     * @param string $mode Operation: insert|update|delete|select
     * @param string $dd Datenbeschreibung
     *
     * @return int 0|1|2
     */
    function check_access(string $mode, string $dd) {
        dbx()->debug("check-acces Mode=($mode) Tab=($dd)");

        // Im Demo-Modus sind fachliche CRUD-Schreibvorgaenge gesperrt.
        // Dieser Pfad wird nur aufgerufen, wenn der Aufrufer die
        // Zugriffspruefung aktiviert hat. Technische Systemvorgaenge wie das
        // Speichern einer Session verwenden bewusst verify_access=0 und
        // bleiben deshalb funktionsfaehig.
        if (dbx()->is_demo_mode()
            && in_array($mode, array('insert', 'update', 'delete'), true)
        ) {
            return 0;
        }

        if (dbx()->has_group('dbxRunAsAdmin')) {
            return 1;
        }

        $access = 0;
        $groups = '';
        $table  = $this->get_dd_table($dd, 1);

        if (is_array($table)) {
            $mode_key = '';
            $owner_key = '';

            if ($mode == 'insert') {
                $mode_key = 'create';
                $owner_key = 'create_owner';
            }

            if ($mode == 'update') {
                $mode_key = 'update';
                $owner_key = 'update_owner';
            }

            if ($mode == 'delete') {
                $mode_key = 'delete';
                $owner_key = 'delete_owner';
            }

            if ($mode == 'select') {
                $mode_key = 'read';
                $owner_key = 'read_owner';
            }

            $groups = $mode_key !== '' ? (string)($table[$mode_key] ?? '') : '';
            $access = dbx()->has_group(access_groups: $groups) ? 1 : 0;

            if (!$access && dbx()->user() > 0) {
                if (preg_match('/\bowner\b/', (string) $groups)) {
                    $access = $mode === 'insert' ? 1 : 2;
                }
            }

            if (!$access && $owner_key !== '' && dbx()->user() > 0) {
                $owner_groups = trim((string)($table[$owner_key] ?? ''));
                if ($owner_groups !== '') {
                    if ($owner_groups === '*' || preg_match('/\bowner\b/', $owner_groups) || dbx()->has_group(access_groups: $owner_groups)) {
                        $access = $mode === 'insert' ? 1 : 2;
                        $groups = $owner_groups;
                    }
                }
            }
        }

        dbx()->debug("check-acces dd=($dd) Mode=($mode) Groups=($groups) Access=($access)");

        return $access;
    }

    /**
     * Überprüft und validiert übergebene Feldwerte anhand der DD-Regeln.
     *
     * Die bestehende DBX-Logik bleibt erhalten:
     * - optionale Regelprüfung
     * - optionale Typ-/Längenprüfung
     * - Fehler/Warnungen je nach Validator-Modus
     * - optionales Cleanen oder Unsetzen ungültiger Werte
     *
     * Stabilitätsverbesserungen
     * -------------------------
     * - fehlende DD-Keys werden defensiv behandelt
     * - `0` / `'0'` bleiben valide prüfbare Werte
     * - Array-Werte werden weiterhin vor Typprüfung konvertiert
     *
     * @param string $dd Datenstrukturdefinition
     * @param array  $field_values Zu überprüfende Feldwerte
     * @param int    $verify_values Gibt an, ob Werte überprüft werden sollen
     *
     * @return array Validierte und ggf. bereinigte Feldwerte
     */
    function check_values($dd, $field_values, $verify_values = 1) {
        $db_field_values = [];
        $this->_validation_error = 0;
        $this->_validation_warning = 0;
        $this->_validation_error_flds = [];
        $this->_validation_warning_flds = [];

        $validate_rules = $this->_validator_rules;
        $validate_type  = $this->_validator_type;
        $validate_error = $this->_validator_error;
        $validate_mode  = $this->_validator_mode;

        $fields = $this->get_dd_fields($dd);

        if (!is_array($fields)) {
            return $db_field_values;
        }

        foreach ($fields as $field) {
            $name   = $field['name'] ?? '';
            $length = $field['length'] ?? 0;
            $type   = $field['type'] ?? '';
            $rules  = $field['rules'] ?? '';

            if ($name !== '' && isset($field_values[$name])) {
                $ok    = true;
                $value = $field_values[$name];

                if ($validate_rules && $rules && $value !== '' && $value !== null) {
                    $ok = $this->o_validator->validate($value, $rules, $name);
                }

                if (is_array($value)) {
                    $value = $this->get_convert_array($name, $value, 'auto');
                }

                if ($ok && $validate_type && $value !== '' && $value !== null) {
                    $rules = '';

                    if ($type && $length > 0) {
                        $rules = $type . '|max=' . $length;
                    } elseif ($type) {
                        $rules = $type;
                    }

                    if ($rules) {
                        $ok = $this->o_validator->validate($value, $rules, $name);
                    }
                }

                if (!$ok) {
                    $err = [
                        'name'  => $name,
                        'rules' => $rules,
                        'value' => $value
                    ];

                    if ($validate_error) {
                        $this->_validation_error_flds[] = $err;
                        $this->_validation_error++;
                    } else {
                        $this->_validation_warning_flds[] = $err;
                        $this->_validation_warning++;
                    }

                    if ($validate_mode === 'clean') {
                        $rules = $field['rules'] ?? '';

                        if (strpos((string) $rules, 'array') !== false) {
                            $rules = $type;
                        } else {
                            if ($rules === '*') {
                                $rules = '';
                            }

                            if ($rules && $rules !== $type) {
                                $rules .= '|' . $type;
                            } else {
                                $rules = $type;
                            }
                        }

                        $value = $this->o_validator->clean($value, $rules, $length, $name);
                    }

                    if ($validate_mode === 'unset') {
                        $name = false;
                    }
                }

                if ($name) {
                    $db_field_values[$name] = $value;
                }
            }
        }

        return $db_field_values;
    }

    /**
     * Filtert ungültige Felder aus einem Eingabearray heraus.
     *
     * Es werden nur Felder übernommen, die in der DD definiert sind.
     * Bei ungültiger oder leerer DD-Feldliste wird defensiv ein leeres
     * Ergebnis zurückgegeben.
     *
     * @param string $dd Datenstrukturdefinition
     * @param array  $field_values Eingabewerte
     *
     * @return array Gefilterte Feldwerte
     */
    function check_fields($dd, $field_values) {
        $db_fields = $this->get_dd_fields($dd);

        if (!is_array($db_fields) || empty($db_fields)) {
            return array();
        }

        $valid_names = array();

        foreach ($db_fields as $field) {
            if (isset($field['name']) && $field['name'] !== '') {
                $valid_names[$field['name']] = 1;
            }
        }

        return array_filter($field_values, function ($name) use ($valid_names) {
            return isset($valid_names[$name]);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Erstellt einen leeren Datensatz mit Standardwerten basierend auf der DD.
     *
     * Beispiel
     * --------
     * ```php
     * $empty = $this->empty_record('kunden');
     * // Rückgabe: [ [ 'id' => 0, 'name' => '', ... ] ]
     * ```
     *
     * @param string $dd Datenstrukturdefinition
     *
     * @return array Leerer Datensatz als Array in Array-Hülle
     */
    function empty_record(string $dd): array {
        $dd_fields = $this->get_dd_fields($dd);
        $record    = [];

        if (!is_array($dd_fields)) {
            return [$record];
        }

        foreach ($dd_fields as $field) {
            $name = $field['name'] ?? '';

            if ($name === '') {
                continue;
            }

            $value = $field['default'] ?? '';

            if (!isset($field['default']) && in_array(($field['type'] ?? ''), ['int', 'longint'])) {
                $value = 0;
            }

            $record[$name] = $value;
        }

        return [$record];
    }


    function check_where($where, $owner = 0, $dd = '') {
        $key = $this->_fld_id ?? 'id';

        if ($where == 'new' || $where == '0') {
            $where = '';
        }

        if ($this->is_integer_value($where)) {
            if ($dd) {
                $dd_primary = $this->get_dd_primary($dd);

                if ($dd_primary) {
                    $key = $dd_primary;
                }
            }

            $where = "$key = $where";
        }

        if ($owner) {
            $uid = dbx()->user();
            $owner_field = 'owner';

            if ($dd) {
                $table_def = $this->get_dd_table_def($dd);
                $configured_owner_field = trim((string)($table_def['owner_field'] ?? ''));
                if ($configured_owner_field !== '' && $this->is_fld_name($dd, $configured_owner_field)) {
                    $owner_field = $configured_owner_field;
                }
            }

            if ($where) {
                $where = '(' . $where . ') AND ' . $owner_field . ' = ' . $uid;
            } else {
                $where = $owner_field . ' = ' . $uid;
            }
        }

        return $where;
    }




}
