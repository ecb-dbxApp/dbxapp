<?php
/**
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

    public $db = array();

    public $pdo = null;

    public $_connected = 0;
    public $_server = '';
    public $_dbtype = '';

    public $_insert_id = 0;

    public $_update_count = 0;

    public $_delete_count = 0;

    public $_insert_count = 0;

    public $_dbMessage = '';

    public $oValidator = null;
    public $_validation_error = 0;
    public $_validation_warning = 0;
    public $_validation_error_flds = array();
    public $_validation_warning_flds = array();

    public $_validatior_rules = 0;      // save insert update
    public $_validatior_type = 1;       // type der Daten prüfen
    public $_validatior_error = 0;      // Bei validate Fehler db error oder warning
    public $_validatior_mode = 'clean'; // Bei validate Fehler daten 'clean' oder 'unset'

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
     * @return void
     */
    public function __construct() {
        $this->oValidator = dbx()->get_system_obj('dbxValidator');
        $this->db = array();
        $this->_connected = 0;
        $this->_server = '';
        $this->_insert_id = 0;
        $this->_update_count = 0;
        $this->_delete_count = 0;
        $this->_insert_count = 0;
        $this->_dbMessage = '';
        $this->_validation_error = 0;
        $this->_validation_warning = 0;
        $this->_validation_error_flds = array();
        $this->_validation_warning_flds = array();
        $this->_fld_id = 'id';
    }

    /**
     * Aktiviert die Query-Messung nur, wenn die zentrale Performance-Erfassung
     * aktiv ist. Die Entscheidung wird pro Request einmal gecacht; das
     * Speichern der Messwerte selbst wird immer ausgeschlossen.
     */
    private function performance_query_capture_enabled(): bool {
        if ((int) dbx()->get_system_var('dbx_performance_timer_store', 0, 'int') === 1) {
            return false;
        }

        if ($this->_performance_query_capture !== null) {
            return $this->_performance_query_capture;
        }

        $level = strtolower(trim((string) dbx()->get_cfg('dbx', 'performance_timer_level')));
        if (in_array($level, array('main', 'detail', 'details'), true)) {
            return $this->_performance_query_capture = true;
        }

        $legacy = dbx()->get_cfg('dbx', 'performance_timer');
        return $this->_performance_query_capture = ((int) $legacy === 1);
    }

    /**
     * Normalisiert SQL zu einer stabilen, datenschutzfreundlichen Struktur.
     * Literale und Kommentare werden entfernt; Tabellen- und Feldnamen bleiben
     * fuer die Diagnose erhalten. Parameterwerte werden nie gespeichert.
     */
    private function normalize_performance_sql(string $sql): string {
        $sql = preg_replace('~/\\*.*?\\*/~s', ' ', $sql);
        $sql = preg_replace('/--[^\\r\\n]*/', ' ', (string) $sql);
        $sql = preg_replace("/'(?:''|\\\\.|[^'])*'/s", '?', (string) $sql);
        $sql = preg_replace('/\\b(?:0x[0-9a-f]+|\\d+(?:\\.\\d+)?)\\b/i', '?', (string) $sql);
        $sql = preg_replace('/\\s+/', ' ', (string) $sql);
        return substr(trim((string) $sql), 0, 1200);
    }

    private function performance_slow_query_ms(): int {
        if ($this->_performance_slow_query_ms !== null) {
            return $this->_performance_slow_query_ms;
        }

        $value = (int) dbx()->get_cfg('dbx', 'performance_timer_slow_query_ms');
        return $this->_performance_slow_query_ms = ($value > 0 ? $value : 100);
    }

    /** Erfasst genau eine logisch ausgefuehrte DB-Anweisung. */
    private function record_performance_query(
        string $server,
        string $sql,
        float $started,
        int $affectedRows = 0,
        bool $success = true
    ): void {
        if (!$this->performance_query_capture_enabled()) {
            return;
        }

        $elapsedMs = max(0.0, (microtime(true) - $started) * 1000);
        $normalized = $this->normalize_performance_sql($sql);
        if ($normalized === '') {
            return;
        }

        $fingerprint = substr(hash('sha256', strtolower($server) . '|' . strtolower($normalized)), 0, 16);
        $operation = strtoupper((string) strtok(ltrim($normalized), " \t\r\n"));
        if ($operation === '') {
            $operation = 'SQL';
        }

        $slowMs = $this->performance_slow_query_ms();
        $slow = $elapsedMs >= $slowMs;

        if (!isset($this->_performance_queries[$fingerprint])) {
            $this->_performance_queries[$fingerprint] = array(
                'fingerprint'   => $fingerprint,
                'server'        => substr($server, 0, 80),
                'operation'     => substr($operation, 0, 16),
                'sql'           => $normalized,
                'query_count'   => 0,
                'time_ms'       => 0.0,
                'max_time_ms'   => 0.0,
                'affected_rows' => 0,
                'slow_count'    => 0,
                'failure_count' => 0,
            );
        }

        $entry =& $this->_performance_queries[$fingerprint];
        $entry['query_count']++;
        $entry['time_ms'] += $elapsedMs;
        $entry['max_time_ms'] = max((float) $entry['max_time_ms'], $elapsedMs);
        $entry['affected_rows'] += max(0, $affectedRows);
        if ($slow) $entry['slow_count']++;
        if (!$success) $entry['failure_count']++;
        unset($entry);

        $this->_performance_query_count++;
        $this->_performance_query_time_ms += $elapsedMs;
        $this->_performance_query_affected_rows += max(0, $affectedRows);
        if ($slow) $this->_performance_query_slow_count++;
        if (!$success) $this->_performance_query_failed_count++;
    }

    /**
     * Liefert die Request-Zusammenfassung fuer dbxPerformanceTimer.
     * Detailzeilen sind nach Gesamtkosten und danach nach Wiederholungen
     * sortiert, damit die relevantesten Optimierungskandidaten zuerst kommen.
     */
    public function performance_query_snapshot(): array {
        $queries = array_values($this->_performance_queries);
        usort($queries, static function (array $a, array $b): int {
            $time = ((float) ($b['time_ms'] ?? 0)) <=> ((float) ($a['time_ms'] ?? 0));
            if ($time !== 0) return $time;
            return ((int) ($b['query_count'] ?? 0)) <=> ((int) ($a['query_count'] ?? 0));
        });

        $duplicates = 0;
        foreach ($queries as $query) {
            $duplicates += max(0, (int) ($query['query_count'] ?? 0) - 1);
        }

        return array(
            'query_count'          => $this->_performance_query_count,
            'unique_query_count'   => count($queries),
            'duplicate_query_count'=> $duplicates,
            'slow_query_count'     => $this->_performance_query_slow_count,
            'failed_query_count'   => $this->_performance_query_failed_count,
            'affected_rows'        => $this->_performance_query_affected_rows,
            'query_time_ms'        => (int) round($this->_performance_query_time_ms),
            'queries'              => $queries,
        );
    }

    /**
     * Liefert den kanonischen DD-Schluessel fuer Cache und Invalidierung.
     * Verschiedene gueltige Schreibweisen derselben DD werden dadurch nicht
     * als getrennte Datenquellen behandelt.
     */
    private function select1_cache_dd_key(string $dd): string {
        $definition = $this->load_dd($dd);
        if ((int)($definition['dd_status'] ?? 0) === 1) {
            return strtolower(
                trim((string)($definition['dd_modul'] ?? '')) . '|'
                . trim((string)($definition['dd_name'] ?? ''))
            );
        }

        return strtolower(trim($dd));
    }

    /** Erstellt einen stabilen Schluessel fuer exakt einen select1()-Aufruf. */
    private function select1_cache_key(string $ddKey, $where, $columns, int $verifyAccess): string {
        return hash('sha256', serialize(array(
            $ddKey,
            $where,
            $columns,
            $verifyAccess,
            (int)dbx()->user(),
        )));
    }

    /** Transaktionale Reads umgehen den Cache und sehen immer den PDO-Zustand. */
    private function select1_cache_allowed(string $dd): bool {
        $server = $this->get_dd_server($dd);
        return $server !== '' && empty($this->_tx[$server]);
    }

    /** Verwirft alle select1()-Ergebnisse genau einer DD. */
    protected function invalidate_select1_cache(string $dd): void {
        $ddKey = $this->select1_cache_dd_key($dd);
        if (!isset($this->_select1_cache[$ddKey])) {
            return;
        }

        $this->_select1_cache_entries -= count($this->_select1_cache[$ddKey]);
        $this->_select1_cache_entries = max(0, $this->_select1_cache_entries);
        unset($this->_select1_cache[$ddKey]);
        unset($this->_select1_cache_servers[$ddKey]);
        $this->_select1_cache_stats['invalidations']++;
    }

    /**
     * Nach Commit/Rollback werden alle DD-Caches des Transaktionsservers
     * verworfen. Das deckt auch absichtliche atomare update_query()-Pfade ab.
     */
    private function invalidate_select1_cache_server(string $server): void {
        foreach ($this->_select1_cache_servers as $ddKey => $cacheServer) {
            if ($cacheServer !== $server || !isset($this->_select1_cache[$ddKey])) {
                continue;
            }
            $this->_select1_cache_entries -= count($this->_select1_cache[$ddKey]);
            unset($this->_select1_cache[$ddKey], $this->_select1_cache_servers[$ddKey]);
            $this->_select1_cache_stats['invalidations']++;
        }
        $this->_select1_cache_entries = max(0, $this->_select1_cache_entries);
    }

    /** Diagnosewerte fuer Tests und Performanceanzeige, ohne Cacheinhalte. */
    public function select1_cache_snapshot(): array {
        return $this->_select1_cache_stats + array(
            'entries' => $this->_select1_cache_entries,
            'dds' => count($this->_select1_cache),
            'capacity' => self::SELECT1_CACHE_MAX_ENTRIES,
        );
    }

    /**
     * Gibt interne Referenzen beim Zerstören des Objekts frei.
     *
     * @return void
     */
    public function __destruct() {
        $this->db = null;
    }

    /**
     * Setzt den letzten DB-Fehlerzustand zurueck.
     *
     * @return void
     */
    public function clear_db_error(): void {
        $this->_error_status = '';
        $this->_error_text   = '';
        $this->_dbMessage    = '';
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
        $this->_dbMessage    = $text;
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
    private function report_db_error(string $status, string $rid, string $why, string $what, string $dedupKey = ''): void {
        $message = trim($why);
        if (trim($what) !== '') {
            $message .= ($message !== '' ? ': ' : '') . trim($what);
        }
        $this->set_db_error($status, $message);

        if (!(int)$this->_report_error) {
            return;
        }

        if ($dedupKey !== '') {
            if (isset($this->_reported_keys[$dedupKey])) {
                return;
            }
            $this->_reported_keys[$dedupKey] = 1;
        }

        $sysMsgStatus = $status === 'access' ? 'security' : 'error';

        try {
            dbx()->sys_msg($sysMsgStatus, 'db', $rid, $why, $what);
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
    function isSQLiteDatabaseLocked($databasePath) {
        $isLocked = 0;

        try {
            $pdo = new PDO("sqlite:$databasePath");
            $pdo->exec('PRAGMA locking_mode=NORMAL');
            $pdo->beginTransaction();
        } catch (PDOException $e) {
            $isLocked = 1;
        } finally {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }

        if ($isLocked) {
            dbx()->debug("SQLITE db ($databasePath) Lock=($isLocked)");
        }

        return $isLocked;
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
    function dbConnect($server, $dbType, $dbHost, $dbName = '', $dbUser = '', $dbPass = '', $dbPort = '') {
        $ok = 1;
        //dbx()->debug("#dbConnect Server=($server) Type=($dbType) Host=($dbHost) dbName=($dbName)", $this->db[$server] ?? null);

        if (!isset($this->db[$server])) {
            try {
                switch ($dbType) {
                    case 'sqlite':
                        $dbName = dbx()->config_path_resolve($dbHost . $dbName);
                        $this->db[$server] = new PDO("sqlite:$dbName");
                        break;

                    case 'mysql':

                        $dsn = "mysql:host=$dbHost";
                        if ($dbPort !== '') {
                            $dsn .= ";port=$dbPort";
                        }
                        if ($dbName !== '') {
                            $dsn .= ";dbname=$dbName";
                        }
                        $dsn .= ";charset=utf8mb4";
                        $this->db[$server] = new PDO($dsn, $dbUser, $dbPass, [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                            PDO::ATTR_TIMEOUT => max(1, $this->_connect_timeout),
                        ]);

                        break;

                    case 'pgsql':
                        $this->db[$server] = new PDO("pgsql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
                        break;

                    case 'sqlsrv':
                        $this->db[$server] = new PDO("sqlsrv:Server=$dbHost;Database=$dbName", $dbUser, $dbPass);
                        break;

                    case 'oci':
                        $dbtns = "(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = //$dbHost)(PORT = $dbPort)) 
                             (CONNECT_DATA = (SERVICE_NAME = $dbName) ))";

                        $this->db[$server] = new PDO("oci:dbname=$dbtns;charset=utf8", $dbUser, $dbPass, [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_EMULATE_PREPARES => false,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                        ]);
                        break;

                    case 'firebird':
                        $this->db[$server] = new PDO("firebird:dbname=$dbHost:$dbName", $dbUser, $dbPass);
                        break;

                    case 'cubrid':
                        $this->db[$server] = new PDO("cubrid:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
                        break;

                    case 'dblib':
                        $this->db[$server] = new PDO("dblib:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
                        break;

                    case 'ibm':
                        $this->db[$server] = new PDO("ibm:DRIVER={IBM DB2 ODBC DRIVER};DATABASE=$dbName;HOSTNAME=$dbHost;PORT=$dbPort;PROTOCOL=TCPIP;UID=$dbUser;PWD=$dbPass;");
                        break;

                    case 'informix':
                        $this->db[$server] = new PDO("informix:host=$dbHost;service=$dbPort;database=$dbName;server=$server;protocol=onsoctcp;UID=$dbUser;PWD=$dbPass");
                        break;

                    case 'odbc':
                        $this->db[$server] = new PDO("odbc:$dbName", $dbUser, $dbPass);
                        break;

                    default:
                        throw new PDOException("Unsupported database type: $dbType");
                }

                $this->db[$server]->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (Throwable $e) {
                $ok = 0;
                $dbMessage = $e->getMessage();
                unset($this->db[$server]);
                $this->_query = "Connect Server ($server) Type=($dbType) Host=($dbHost) dbName=($dbName) dbUser=($dbUser) Port=($dbPort)";

                $this->report_db_error(
                    'db',
                    (string)$server,
                    $this->connection_error_reason($e),
                    $dbMessage,
                    'db-connect|' . $server
                );
            }
        }

        return $ok;
    }

    private function quote_db_identifier_for_type(string $dbType, string $name): string {
        $dbType = strtolower(trim($dbType));
        if ($dbType === 'mysql') {
            return '`' . str_replace('`', '``', $name) . '`';
        }
        if ($dbType === 'sqlsrv' || $dbType === 'dblib') {
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

    public function can_connect_database_config(array $dbConfig, bool $withDatabase = false): int {
        $dbType = strtolower(trim((string)($dbConfig['type'] ?? '')));
        $dbName = trim((string)($dbConfig['dbname'] ?? ($dbConfig['name'] ?? '')));

        if ($dbType === 'sqlite') {
            $host = dbx()->config_path_resolve(rtrim((string)($dbConfig['host'] ?? ''), "/\\") . '/');
            if ($withDatabase) {
                $path = dbx()->config_path_resolve(rtrim((string)($dbConfig['host'] ?? ''), "/\\") . '/' . $dbName);
                return ($dbName !== '' && is_file($path)) ? 1 : 0;
            }
            return is_dir($host) ? 1 : 0;
        }

        if ($dbType === '' || ($withDatabase && $dbName === '')) {
            return 0;
        }

        $tmpServer = '__dbx_check_db_' . md5($dbType . $dbName . microtime(true));
        if (isset($this->db[$tmpServer])) {
            unset($this->db[$tmpServer]);
        }

        $ok = $this->dbConnect(
            $tmpServer,
            $dbType,
            $dbConfig['host'] ?? '',
            $withDatabase ? $dbName : '',
            $dbConfig['user'] ?? '',
            $dbConfig['pass'] ?? '',
            $dbConfig['port'] ?? ''
        );

        if (isset($this->db[$tmpServer])) {
            unset($this->db[$tmpServer]);
        }

        return $ok ? 1 : 0;
    }

    public function ensure_database_exists(string $server, array $dbConfig): int {
        $dbType = strtolower(trim((string)($dbConfig['type'] ?? '')));
        $dbName = (string)($dbConfig['dbname'] ?? ($dbConfig['name'] ?? ''));
        $dbName = trim($dbName);

        if ($dbName === '') {
            return 0;
        }

        if ($dbType === 'sqlite') {
            $host = dbx()->config_path_resolve(rtrim((string)($dbConfig['host'] ?? ''), "/\\") . '/');
            if ($host !== '' && !is_dir($host)) {
                @mkdir($host, 0777, true);
            }
            return is_dir($host) ? 1 : 0;
        }

        $adminDb = '';
        switch ($dbType) {
            case 'mysql':
                $adminDb = '';
                break;
            case 'pgsql':
                $adminDb = (string)($dbConfig['admin_dbname'] ?? $dbConfig['maintenance_db'] ?? 'postgres');
                break;
            case 'sqlsrv':
            case 'dblib':
                $adminDb = (string)($dbConfig['admin_dbname'] ?? $dbConfig['maintenance_db'] ?? 'master');
                break;
            default:
                return 0;
        }

        $tmpServer = '__dbx_create_db_' . md5($server . $dbType . microtime(true));
        if (isset($this->db[$tmpServer])) {
            unset($this->db[$tmpServer]);
        }

        $ok = $this->dbConnect(
            $tmpServer,
            $dbType,
            $dbConfig['host'] ?? '',
            $adminDb,
            $dbConfig['user'] ?? '',
            $dbConfig['pass'] ?? '',
            $dbConfig['port'] ?? ''
        );

        if (!$ok || !isset($this->db[$tmpServer])) {
            if (isset($this->db[$tmpServer])) {
                unset($this->db[$tmpServer]);
            }
            return 0;
        }

        try {
            $pdo = $this->db[$tmpServer];
            switch ($dbType) {
                case 'mysql':
                    $sql = 'CREATE DATABASE IF NOT EXISTS ' . $this->quote_db_identifier_for_type($dbType, $dbName)
                         . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
                    $pdo->exec($sql);
                    break;

                case 'pgsql':
                    $exists = $pdo->query('SELECT 1 FROM pg_database WHERE datname = ' . $this->pdo_quote_value($pdo, $dbName));
                    if (!$exists || !$exists->fetchColumn()) {
                        $pdo->exec('CREATE DATABASE ' . $this->quote_db_identifier_for_type($dbType, $dbName) . " ENCODING 'UTF8'");
                    }
                    break;

                case 'sqlsrv':
                case 'dblib':
                    $literal = $this->pdo_quote_value($pdo, $dbName);
                    $sql = 'IF DB_ID(N' . $literal . ') IS NULL CREATE DATABASE ' . $this->quote_db_identifier_for_type($dbType, $dbName);
                    $pdo->exec($sql);
                    break;
            }

            unset($this->db[$tmpServer]);
            dbx()->debug("database ensured Server=($server) Type=($dbType) DB=($dbName)");
            return 1;
        } catch (PDOException $e) {
            $this->_dbMessage = $e->getMessage();
            $this->report_db_error('db', (string)$server, 'Datenbank nicht vorhanden', $e->getMessage(), 'db-create|' . $server);
            unset($this->db[$tmpServer]);
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
                    $missingFile = $file2;
                    $this->report_db_error(
                        'db',
                        $server,
                        'Datenbank nicht vorhanden',
                        $missingFile,
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
            $dbConfig = $config['db'][$server];

            if (!$this->db_server_config_is_active($server, $dbConfig)) {
                $this->_dbMessage = 'Datenbankserver deaktiviert';
                $this->_error = $this->_dbMessage;
                return 0;
            }

            $_SESSION['dbx']['config']['dbx']['db'][$server] = $dbConfig;

            $dbName = $dbConfig['dbname'] ?? ($dbConfig['name'] ?? '');


            $ok = $this->dbConnect(
                $server,
                $dbConfig['type'] ?? 'sqlite',
                $dbConfig['host'] ?? '',
                $dbName,
                $dbConfig['user'] ?? '',
                $dbConfig['pass'] ?? '',
                $dbConfig['port'] ?? ''
            );

            if (!$ok
                && trim((string)$dbName) !== ''
                && $this->is_missing_database_error((string)$this->_dbMessage)
            ) {
                $firstMessage = $this->_dbMessage;
                if ($this->ensure_database_exists($server, $dbConfig)) {
                    if (isset($this->db[$server])) {
                        unset($this->db[$server]);
                    }
                    $this->_dbMessage = '';
                    $this->_error = '';
                    $ok = $this->dbConnect(
                        $server,
                        $dbConfig['type'] ?? 'mysql',
                        $dbConfig['host'] ?? '',
                        $dbName,
                        $dbConfig['user'] ?? '',
                        $dbConfig['pass'] ?? '',
                        $dbConfig['port'] ?? ''
                    );
                } elseif ($this->_dbMessage === '' && $firstMessage !== '') {
                    $this->_dbMessage = $firstMessage;
                }
            }
        }

        if ($ok) {
            //dbx()->debug("connect $server = ok");

            $this->clear_db_error();
            $this->_error = '';
            $dbType = $this->get_db_type($server);
            $this->_connected = 1;
            $this->_server = $server;
            $this->_dbtype = $dbType;
        }

        return $ok;
    }

    /**
     * Prueft den Aktivstatus eines konfigurierten SQL-Servers.
     *
     * SQLite-/db3-Dateien sind immer aktiv. Bestehende SQL-Server ohne
     * `activ`-Eintrag gelten aus Kompatibilitaetsgruenden ebenfalls als aktiv.
     */
    public function db_server_config_is_active(string $server, array $dbConfig): bool {
        $type = strtolower(trim((string)($dbConfig['type'] ?? '')));
        $name = trim((string)($dbConfig['dbname'] ?? ($dbConfig['name'] ?? '')));

        if ($type === 'sqlite' || $type === 'sqlite3' || preg_match('/\.(db3|sqlite|sqlite3)$/i', $server . ' ' . $name)) {
            return true;
        }

        if (!array_key_exists('activ', $dbConfig)) {
            return true;
        }

        $active = strtolower(trim((string)$dbConfig['activ']));
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
        $dbType = 'sqlite';
        $config = dbx()->get_cfg('dbx');

        if (isset($config['db'][$server]['type'])) {
            $dbType = $config['db'][$server]['type'];
        } elseif (preg_match('/\.(db3|sqlite|sqlite3)$/i', (string) $server)) {
            $dbType = 'sqlite';
        }

        return $dbType;
    }

    /**
     * Liefert die installationsbezogenen Serverbindungen der DDs.
     *
     * Die Bindungen liegen absichtlich in der lokalen dbx-Konfiguration.
     * Release-DDs bleiben damit portabel und ein Update kann eine lokale
     * SQLite-/MySQL-Entscheidung nicht ueberschreiben.
     *
     * @return array<string,string>
     */
    protected function get_dd_server_bindings(): array {
        $config = dbx()->get_cfg('dbx');
        $bindings = $config['dd_server_bindings'] ?? array();

        return is_array($bindings) ? $bindings : array();
    }

    /**
     * Sucht eine Bindung ohne von der Schreibweise des DD-Schluessels
     * abzuhaengen. Der verbindliche Schluessel ist `modul|dd`; der reine
     * DD-Name bleibt als Rueckwaertskompatibilitaet erhalten.
     */
    private function find_dd_server_binding(
        array $bindings,
        string $ddModule,
        string $ddName
    ): array {
        $exact = $ddModule . '|' . $ddName;
        $candidates = array($exact, $ddName);

        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $bindings)) {
                return array(
                    'key' => $candidate,
                    'server' => trim((string)$bindings[$candidate]),
                );
            }
        }

        $lowerCandidates = array_map('strtolower', $candidates);
        foreach ($bindings as $key => $server) {
            $position = array_search(strtolower(trim((string)$key)), $lowerCandidates, true);
            if ($position !== false) {
                return array(
                    'key' => (string)$key,
                    'server' => trim((string)$server),
                );
            }
        }

        return array('key' => '', 'server' => '');
    }

    /**
     * Prueft eine lokale DD-Serverbindung, ohne eine Verbindung aufzubauen.
     *
     * DB3-Bindungen duerfen nur aus optionalem Modulnamen und Dateinamen
     * bestehen. SQL-Bindungen muessen auf einen aktiven, lokal konfigurierten
     * Server zeigen. Bei einer fehlerhaften Bindung gibt es keinen stillen
     * Fallback auf die im DD genannte Datenbank.
     */
    protected function is_valid_dd_server_binding(string $server): bool {
        $server = trim($server);
        if ($server === '') {
            return false;
        }

        if (preg_match('/\.(db3|sqlite|sqlite3)$/i', $server)) {
            $parts = strpos($server, '|') !== false
                ? explode('|', $server, 2)
                : array('', $server);
            $module = trim((string)($parts[0] ?? ''));
            $file = trim((string)($parts[1] ?? ''));

            return ($module === '' || preg_match('/^[A-Za-z0-9_]+$/', $module) === 1)
                && basename($file) === $file
                && preg_match('/^[A-Za-z0-9_.-]+\.(db3|sqlite|sqlite3)$/i', $file) === 1;
        }

        $config = dbx()->get_cfg('dbx');
        $dbConfig = $config['db'][$server] ?? null;

        return is_array($dbConfig)
            && $this->db_server_config_is_active($server, $dbConfig);
    }

    /**
     * Liefert Herkunft und Ergebnis der Serveraufloesung einer DD.
     *
     * Jede DD kann unabhaengig gebunden werden. Dadurch sind auch innerhalb
     * eines Moduls beliebige Mischungen aus DB3- und SQL-Servern moeglich.
     *
     * @return array{
     *   dd:string,
     *   binding_key:string,
     *   declared_server:string,
     *   resolved_server:string,
     *   source:string,
     *   valid:bool
     * }
     */
    public function get_dd_server_binding_info(string $dd): array {
        $dd_sys    = $this->load_dd($dd);
        $dd_status = $dd_sys['dd_status'] ?? 0;
        $dd_modul  = (string)($dd_sys['dd_modul'] ?? '');
        $dd_name   = (string)($dd_sys['dd_name'] ?? '');

        if ($dd_status != 1) {
            return array(
                'dd' => $dd,
                'binding_key' => '',
                'declared_server' => '',
                'resolved_server' => '',
                'source' => 'missing-dd',
                'valid' => false,
            );
        }

        $cache = $_SESSION['dbx']['cache']['dd'][$dd_modul][$dd_name] ?? array();
        $declared = trim((string)(
            $cache['declared_server']
            ?? ($cache['table']['server'] ?? 'default')
        ));
        $binding = $this->find_dd_server_binding(
            $this->get_dd_server_bindings(),
            $dd_modul,
            $dd_name
        );
        $resolved = $binding['key'] !== '' ? $binding['server'] : $declared;
        $valid = $binding['key'] === ''
            ? $resolved !== ''
            : $this->is_valid_dd_server_binding($resolved);

        if (!$valid && $binding['key'] !== '') {
            dbx()->sys_msg(
                'error',
                'db',
                $dd_modul . '|' . $dd_name,
                'ungueltige lokale DD-Serverbindung',
                $binding['key'] . ' => ' . $resolved
            );
            $resolved = '';
        }

        return array(
            'dd' => $dd_modul . '|' . $dd_name,
            'binding_key' => $binding['key'],
            'declared_server' => $declared,
            'resolved_server' => $resolved,
            'source' => $binding['key'] !== '' ? 'local-binding' : 'dd-default',
            'valid' => $valid,
        );
    }

    /**
     * Ermittelt den lokal wirksamen Server einer Datenbeschreibung.
     *
     * Vorrang:
     * 1. `dd_server_bindings['modul|dd']` aus config.local.php
     * 2. Serverangabe des ausgelieferten DDs
     *
     * @return string Servername oder leer bei ungueltiger lokaler Bindung
     */
    public function get_dd_server(string $dd): string {
        $binding = $this->get_dd_server_binding_info($dd);
        return (string)($binding['resolved_server'] ?? '');
    }

    /**
     * Gibt das CSV-Trennzeichen für eine DD zurück.
     *
     * Falls in der DD kein CSV-Separator definiert ist,
     * wird `;` verwendet.
     *
     * @param string $dd Name der Datenbeschreibung
     *
     * @return string CSV-Trennzeichen
     */
    public function get_csv_seperator($dd) {
        $csv_seperator = ';';

        $dd_sys    = $this->load_dd($dd);
        $dd_status = $dd_sys['dd_status'] ?? 0;
        $dd_modul  = $dd_sys['dd_modul'] ?? '';
        $dd_name   = $dd_sys['dd_name'] ?? '';

        if ($dd_status == 1) {
            $csv_seperator = $_SESSION['dbx']['cache']['dd'][$dd_modul][$dd_name]['table']['csv'] ?? ';';
        }

        return $csv_seperator;
    }

    /**
     * Ermittelt den Primärschlüssel einer Datenbeschreibung (DD).
     *
     * Gibt den in der DD definierten Primärschlüssel zurück.
     * Falls keiner vorhanden ist, wird `id` verwendet.
     *
     * @param string $dd Name der Datenbeschreibung
     *
     * @return string Primärschlüssel der DD oder `id`
     */
    public function get_dd_primary(string $dd): string {
        $dd_sys    = $this->load_dd($dd);
        $dd_status = $dd_sys['dd_status'] ?? 0;
        $dd_modul  = $dd_sys['dd_modul'] ?? '';
        $dd_name   = $dd_sys['dd_name'] ?? '';

        if ($dd_status == 1) {
            $primary = $_SESSION['dbx']['cache']['dd'][$dd_modul][$dd_name]['table']['primary'] ?? '';

            if ($primary !== '') {
                return $primary;
            }
        }

        return 'id';
    }

    /**
     * Platzhalter für Sortierfelder einer DD.
     *
     * @param string $dd Name der Datenbeschreibung
     *
     * @return string Aktuell leer
     */
    public function get_dd_sort_flds($dd) {
        $fld = '';
        return $fld;
    }

    /**
     * Platzhalter für Sortierrichtung einer DD.
     *
     * @param string $dd Name der Datenbeschreibung
     *
     * @return string Standardmäßig `ASC`
     */
    public function get_dd_sort_desc($dd) {
        $desc = 'ASC';
        return $desc;
    }

    /**
     * Fügt ein neues Feld zu einer bestehenden Tabelle in der Datenbank hinzu.
     *
     * Zweck
     * -----
     * Erzeugt abhängig vom DB-Typ das passende SQL für `ALTER TABLE ... ADD COLUMN`.
     * Die Methode erzeugt aktuell das SQL und verbindet sich mit dem Server.
     * Zusätzliche Index-/Primary-SQLs werden geloggt.
     *
     * Hinweis
     * -------
     * Diese Funktion ist bewusst minimal gehalten und ändert die bestehende
     * DBX-Logik nicht grundsätzlich.
     *
     * @param string $server Der Name des Datenbankservers
     * @param string $table Der Name der Tabelle
     * @param array  $field Felddefinition:
     *                      - name
     *                      - type
     *                      - length
     *                      - default
     *                      - index
     *
     * @return bool True bei Erfolg, sonst false
     */
    public function add_db_fld($server, $table, $field) {
        $ok = $this->connect_db_server($server);

        if (!$ok) {
            return false;
        }

        $typeMap = [
            'int'     => ['mysql' => 'INT', 'sqlite' => 'INTEGER', 'pgsql' => 'INTEGER', 'sqlsrv' => 'INT', 'oci' => 'NUMBER', 'firebird' => 'INTEGER', 'cubrid' => 'INTEGER', 'dblib' => 'INT', 'ibm' => 'INTEGER', 'informix' => 'INTEGER', 'odbc' => 'INTEGER'],
            'varchar' => ['mysql' => 'VARCHAR', 'sqlite' => 'TEXT', 'pgsql' => 'VARCHAR', 'sqlsrv' => 'VARCHAR', 'oci' => 'VARCHAR2', 'firebird' => 'VARCHAR', 'cubrid' => 'VARCHAR', 'dblib' => 'VARCHAR', 'ibm' => 'VARCHAR', 'informix' => 'VARCHAR', 'odbc' => 'VARCHAR'],
            'text'    => ['mysql' => 'TEXT', 'sqlite' => 'TEXT', 'pgsql' => 'TEXT', 'sqlsrv' => 'TEXT', 'oci' => 'CLOB', 'firebird' => 'BLOB', 'cubrid' => 'STRING', 'dblib' => 'TEXT', 'ibm' => 'CLOB', 'informix' => 'TEXT', 'odbc' => 'LONGVARCHAR'],
            'bool'    => ['mysql' => 'TINYINT(1)', 'sqlite' => 'INTEGER', 'pgsql' => 'BOOLEAN', 'sqlsrv' => 'BIT', 'oci' => 'NUMBER(1)', 'firebird' => 'SMALLINT', 'cubrid' => 'SMALLINT', 'dblib' => 'BIT', 'ibm' => 'SMALLINT', 'informix' => 'BOOLEAN', 'odbc' => 'BOOLEAN'],
            'date'    => ['mysql' => 'DATE', 'sqlite' => 'TEXT', 'pgsql' => 'DATE', 'sqlsrv' => 'DATE', 'oci' => 'DATE', 'firebird' => 'DATE', 'cubrid' => 'DATE', 'dblib' => 'DATE', 'ibm' => 'DATE', 'informix' => 'DATE', 'odbc' => 'DATE']
        ];

        $dbType = $this->get_db_type($server);
        $type   = $typeMap[$field['type']][$dbType] ?? $field['type'];

        $sql = "ALTER TABLE $table ADD COLUMN {$field['name']} $type";

        if (!empty($field['length']) && in_array($field['type'], ['int', 'varchar'])) {
            $sql .= "({$field['length']})";
        }

        if (isset($field['default']) && ($field['default'] !== '' || $field['default'] === 0)) {
            $sql .= " DEFAULT '{$field['default']}'";
        }

        dbx()->debug("ADD-FLD SQL=($sql)");

        if (($field['index'] ?? '') === 'PRI') {
            $sqlIndex = "ALTER TABLE $table ADD PRIMARY KEY ({$field['name']})";
            dbx()->debug("ADD-PRIMARY-KEY SQL=($sqlIndex)");
        } elseif (($field['index'] ?? '') === 'MU') {
            $sqlIndex = "CREATE INDEX idx_{$field['name']} ON $table ({$field['name']})";
            dbx()->debug("ADD-INDEX SQL=($sqlIndex)");
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
     * Lädt eine Datenbeschreibung (DD) aus Datei und speichert sie im Session-Cache.
     *
     * Zweck
     * -----
     * Zentraler DD-Resolver für alle Folgeoperationen wie:
     * - `select`
     * - `insert`
     * - `update`
     * - `delete`
     * - `save`
     * - Tabellen-/Feld-/Server-Abfragen
     *
     * Suchlogik
     * ---------
     * - `name`       → aktives Modul, danach Fallback `dbx`
     * - `modul|name` → explizit dieses Modul
     * - `modul|...`  → Platzhalter für aktives Modul
     *
     * Wichtige Stabilitätsregel
     * -------------------------
     * Ein expliziter Modulaufruf `modulX|dd` darf nicht durch einen bereits
     * vorhandenen `dbx`-Cacheeintrag derselben DD übersteuert werden.
     * Der `dbx`-Cache-Fallback wird daher nur bei nicht explizitem Modulaufruf verwendet.
     *
     * Rückgabe
     * --------
     * - `dd_status = 1`  → DD erfolgreich geladen
     * - `dd_status = 0`  → Datei nicht gefunden
     * - `dd_status = -1` → DD-Datei ungültig
     *
     * @param string $dd Name der Datenbeschreibung, optional mit Modulpräfix
     *
     * @return array{
     *     dd_status:int,
     *     dd_modul:string,
     *     dd_name:string
     * }
     */
    public function load_dd(string $dd): array {
        $dd_sys = array();

        $activ_modul      = dbx()->get_system_var('dbx_activ_modul', 'dbx');
        $is_explicit_modul = false;
        $active_language   = strtolower(trim((string) dbx()->lng_current()));

        if ($active_language === '' || !preg_match('/^[a-z]{2,3}$/', $active_language)) {
            $active_language = 'de';
        }

        $cache_matches_language = static function (array $cache, string $language): bool {
            $table = isset($cache['table']) && is_array($cache['table']) ? $cache['table'] : array();
            $dynamic = !empty($cache['language_dynamic']) || (($table['language'] ?? '') === '*');

            if (!$dynamic) {
                return true;
            }

            return strtolower(trim((string) ($table['language'] ?? ''))) === $language;
        };

        if (strpos($dd, '|') !== false) {
            $parts             = explode('|', $dd, 2);
            $dd_modul          = trim($parts[0]);
            $dd_name           = trim($parts[1]);
            $is_explicit_modul = true;

            if ($dd_modul === 'modul' || $dd_modul === '') {
                $dd_modul = $activ_modul;
            }
        } else {
            $dd_modul = $activ_modul;
            $dd_name  = $dd;
        }

        if (isset($_SESSION['dbx']['cache']['dd'][$dd_modul][$dd_name])) {
            $cached_dd = $_SESSION['dbx']['cache']['dd'][$dd_modul][$dd_name];
            if ($cache_matches_language($cached_dd, $active_language)) {
                $dd_sys['dd_status'] = 1;
                $dd_sys['dd_modul']  = $dd_modul;
                $dd_sys['dd_name']   = $dd_name;

                return $dd_sys;
            }

            unset($_SESSION['dbx']['cache']['dd'][$dd_modul][$dd_name]);
        }

        if (!$is_explicit_modul && isset($_SESSION['dbx']['cache']['dd']['dbx'][$dd_name])) {
            $cached_dd = $_SESSION['dbx']['cache']['dd']['dbx'][$dd_name];
            if ($cache_matches_language($cached_dd, $active_language)) {
                $dd_sys['dd_status'] = 1;
                $dd_sys['dd_modul']  = 'dbx';
                $dd_sys['dd_name']   = $dd_name;

                return $dd_sys;
            }

            unset($_SESSION['dbx']['cache']['dd']['dbx'][$dd_name]);
        }

        $dd_file = '';

        $dd_file1 = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/dbx/dd/' . $dd_name . '.dd.php');
        $dd_file2 = dbx()->os_path(dbx()->get_base_dir() . "dbx/modules/$dd_modul/dd/" . $dd_name . '.dd.php');

        if (file_exists($dd_file2)) {
            $dd_file = $dd_file2;
        } elseif (file_exists($dd_file1)) {
            $dd_file  = $dd_file1;
            $dd_modul = 'dbx';
        }

        $dd_sys['dd_modul'] = $dd_modul;
        $dd_sys['dd_name']  = $dd_name;

        dbx()->debug("##dbxDB-load_dd=($dd) modul=($dd_modul) dd=($dd_name) file=($dd_file) aktiv modul=($activ_modul)");

        if (empty($dd_file)) {
            dbx()->sys_msg('error', 'dd', $dd, 'missing', 'No dd Path');
            $dd_sys['dd_status'] = 0;
            return $dd_sys;
        }

        include $dd_file;

        if (!isset($table) || !is_array($table)) {
            $dd_sys['dd_status'] = -1;
            return $dd_sys;
        }

        /*
         * Sprachabhaengige DDs kennzeichnen die neutrale Definition mit
         * language="*". In diesem Fall muss der Loader selbst die DD der
         * aktiven Sprache verwenden. Andernfalls wuerde z. B.
         * dbxContentFolder auf die nicht vorhandene Tabelle content_folder
         * statt auf content_folder_de zeigen.
         */
        $language_dynamic = (($table['language'] ?? '') === '*');
        if ($language_dynamic) {
            $language = $active_language;
            $table_base = trim((string) ($table['table'] ?? ''));
            if ($table_base !== '') {
                $language_dd_name = $table_base . '_' . $language;
                $language_dd_file1 = dbx()->os_path(
                    dbx()->get_base_dir() . 'dbx/modules/dbx/dd/' . $language_dd_name . '.dd.php'
                );
                $language_dd_file2 = dbx()->os_path(
                    dbx()->get_base_dir() . 'dbx/modules/' . $dd_modul . '/dd/' . $language_dd_name . '.dd.php'
                );
                $language_dd_file = '';

                if (file_exists($language_dd_file2)) {
                    $language_dd_file = $language_dd_file2;
                } elseif (file_exists($language_dd_file1)) {
                    $language_dd_file = $language_dd_file1;
                }

                if ($language_dd_file !== '') {
                    unset($table, $fields, $indexes);
                    include $language_dd_file;
                    $dd_file = $language_dd_file;
                } else {
                    $table['table']    = $language_dd_name;
                    $table['datadic']  = $language_dd_name;
                    $table['language'] = $language;
                }
            }
        }

        dbx()->register_editor_file('dd', $dd_file);

        if (!isset($fields) || !is_array($fields)) {
            $fields = array();
        }

        if (!isset($indexes) || !is_array($indexes)) {
            $indexes = array();
        }

        if (isset($table['server'])
            && is_string($table['server'])
            && strpos($table['server'], '|') === false
            && preg_match('/\.(db3|sqlite|sqlite3)$/i', $table['server'])
        ) {
            $sqlite_file = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/' . $dd_modul . '/db/' . $table['server']);

            if ($dd_modul !== '' && file_exists($sqlite_file)) {
                $table['server'] = $dd_modul . '|' . $table['server'];
            }
        }

        $_SESSION['dbx']['cache']['dd'][$dd_modul][$dd_name] = [
            'table'            => $table,
            'fields'           => $fields,
            'indexes'          => $indexes,
            'file'             => $this->normalize_editor_file_path($dd_file),
            'language_dynamic' => $language_dynamic,
            'declared_server'  => (string)($table['server'] ?? 'default'),
        ];

        //dbx()->debug("#session set dd ($dd) Modul=($dd_modul) Name=($dd_name)");

        $dd_sys['dd_status'] = 1;
        $dd_sys['dd_modul']  = $dd_modul;
        $dd_sys['dd_name']   = $dd_name;

        return $dd_sys;
    }

    /**
     * Liefert den Dateipfad einer geladenen DD fuer Editor-Marker.
     *
     * @param string $dd Name der Datenbeschreibung
     * @return string Projekt-relativer DD-Pfad oder leer
     */
    public function get_dd_file(string $dd): string {
        $dd_sys    = $this->load_dd($dd);
        $dd_status = $dd_sys['dd_status'] ?? 0;
        $dd_modul  = $dd_sys['dd_modul'] ?? '';
        $dd_name   = $dd_sys['dd_name'] ?? '';

        if ($dd_status != 1 || $dd_modul === '' || $dd_name === '') {
            return '';
        }

        return $_SESSION['dbx']['cache']['dd'][$dd_modul][$dd_name]['file'] ?? '';
    }

    /**
     * Normalisiert absolute Projektpfade fuer den Editor.
     *
     * @param string $file Absoluter Dateipfad
     * @return string Projekt-relativer Pfad
     */
    private function normalize_editor_file_path(string $file): string {
        return dbx()->editor_file_path($file);
    }

    /**
     * Führt eine SQL-Abfrage auf dem angegebenen Server aus.
     *
     * Stellt sicher, dass eine Verbindung besteht, bereitet die SQL-Abfrage vor
     * und führt sie aus. Bei retry-fähigen Lock-/Deadlock-Fehlern wird außerhalb
     * aktiver Transaktionen mehrfach erneut versucht.
     *
     * @param string $server Datenbankserver
     * @param string $sql SQL-Abfrage
     * @param array<int|string,mixed> $params Gebundene Parameter fuer Platzhalter
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

        $maxRetry = 5;
        $profileStarted = microtime(true);

        for ($try = 0; $try < $maxRetry; $try++) {
            try {
                $stmt = $this->db[$server]->prepare($sql);
                $stmt->execute($params);

                $this->record_performance_query(
                    $server,
                    $sql,
                    $profileStarted,
                    max(0, (int) $stmt->rowCount()),
                    true
                );

                return $stmt;
            } catch (PDOException $e) {
                if (empty($this->_tx[$server]) && $this->isRetryable($e) && $try < $maxRetry - 1) {
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

                $this->record_performance_query($server, $sql, $profileStarted, 0, false);

                return 0;
            }
        }

        return 0;
    }

    /**
     * Erzeugt den Timer-Section-Namen fuer eine DD-spezifische DB-Messung.
     *
     * Beispiel:
     * - `db-select-dbx-dbXUser`
     * - `db-save-dbxContent-content`
     *
     * Lange DD-Namen werden gekuerzt und mit Hash stabil gemacht, damit die
     * Timer-Keys in der Performance-Auswertung lesbar bleiben.
     *
     * @param string $type select|save.
     * @param string $dd DD-Name, optional mit Modulprefix.
     * @return string Timer-Section oder leerer String.
     */
    private function db_timer_dd_section(string $type, string $dd): string {
        $dd = trim($dd);

        if ($dd === '') {
            return '';
        }

        $prefix = $type === 'save' ? 'db-save-' : 'db-select-';
        $safe = preg_replace('/[^A-Za-z0-9]+/', '-', $dd);
        $safe = trim((string) $safe, '-');

        if ($safe === '') {
            return '';
        }

        $max = 80 - strlen($prefix);
        if (strlen($safe) > $max) {
            $hash = substr(md5($dd), 0, 6);
            $safe = rtrim(substr($safe, 0, max(1, $max - 7)), '-') . '-' . $hash;
        }

        return $prefix . $safe;
    }

    /**
     * Startet die DB-Performance-Timer fuer eine DB-Operation.
     *
     * Es werden bis zu drei Messpunkte geschrieben:
     * - `db-total`: Gesamtzeit aller DB-Aktionen im Request
     * - `db-select` oder `db-save`: Gesamtzeit je Operationstyp
     * - `db-select-*` oder `db-save-*`: Zeit je DD
     *
     * `save` umfasst insert und update. Verschachtelte Messungen werden
     * unterdrueckt, damit Trace- oder Hilfsqueries die Fachmessung nicht
     * doppelt verschlechtern.
     *
     * Beispiel:
     * ```php
     * $timers = $this->db_timers_start('select', 'dbx|dbxUser');
     * try {
     *    // SELECT ausfuehren
     * } finally {
     *    $this->db_timers_stop($timers);
     * }
     * ```
     *
     * @param string $type select|save.
     * @param string $dd Optionaler DD-Kontext.
     * @return array Gestartete Timer-Sections in Stop-Reihenfolge.
     */
    private function db_timers_start(string $type, string $dd = ''): array {
        if ((int) dbx()->get_system_var('dbx_performance_timer_store', 0, 'int') === 1) {
            return array();
        }

        if ($this->_db_timer_depth > 0) {
            return array();
        }

        $this->_db_timer_depth++;

        $type = $type === 'save' ? 'save' : 'select';
        $base = $type === 'save' ? 'db-save' : 'db-select';
        $sections = array(
            'db-total' => 'db total',
            $base      => $type,
        );

        $ddSection = $this->db_timer_dd_section($type, $dd);
        if ($ddSection !== '') {
            $sections[$ddSection] = $type . ' ' . $dd;
        }

        foreach ($sections as $section => $info) {
            dbx()->timer($section, $info);
        }

        return array_keys($sections);
    }

    /**
     * Stoppt zuvor gestartete DB-Performance-Timer.
     *
     * Die Sections werden rueckwaerts beendet, damit DD-, Typ- und
     * Gesamtmessung sauber ineinander liegen.
     *
     * @param array $sections Rueckgabe von db_timers_start().
     * @return void
     */
    private function db_timers_stop(array $sections): void {
        if (!$sections) {
            return;
        }

        for ($i = count($sections) - 1; $i >= 0; $i--) {
            dbx()->timer($sections[$i]);
        }

        $this->_db_timer_depth = max(0, $this->_db_timer_depth - 1);
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

        return $this->beginServer($server);
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

        return $this->rollbackServer($server);
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
    private function beginServer(string $server): int {
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
    private function rollbackServer(string $server): int {
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
    private function isRetryable(PDOException $e): bool {
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

        $maxRetry = 5;
        $profileStarted = microtime(true);

        for ($try = 0; $try < $maxRetry; $try++) {
            try {
                $affectedRows = $this->db[$server]->exec($sql);
                $this->record_performance_query(
                    $server,
                    $sql,
                    $profileStarted,
                    max(0, (int) $affectedRows),
                    true
                );
                return 1;
            } catch (PDOException $e) {
                if (empty($this->_tx[$server]) && $this->isRetryable($e) && $try < $maxRetry - 1) {
                    usleep(120000 + random_int(0, 150000));
                    continue;
                }

                $this->report_db_error('sql', $server, 'SQL-Fehler', $e->getMessage(), 'sql-exec|' . $server);
                $this->record_performance_query($server, $sql, $profileStarted, 0, false);
                return 0;
            }
        }

        return 0;
    }

    /**
     * Fuehrt eine rohe SELECT-Abfrage auf einem konfigurierten DB-Server aus.
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
        $searchTerms = array_filter(explode(' ', trim($select)));

        if (!$searchTerms) {
            return '1=1';
        }

        $server = $this->_server ?: 'default';

        $whereConditions = [];

        foreach ($searchTerms as $term) {
            $escaped = $this->escape($term, $server);

            $orParts = [];

            foreach (array_keys($flds) as $field) {
                $orParts[] = "$field LIKE '%$escaped%'";
            }

            $whereConditions[] = '(' . implode(' OR ', $orParts) . ')';
        }

        return implode($and ? ' AND ' : ' OR ', $whereConditions);
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
    public function rawQuery(string $server, string $query): mixed {
        if (!$server) {
            return 0;
        }

        $iChars    = 6;
        $connect   = $this->connect_db_server($server);
        $pos       = strpos($query, ' ') ?: $iChars;
        $queryType = strtoupper(substr(trim($query), 0, min($pos, $iChars)));

        if (!$connect) {
            if ($this->get_error_status() === '') {
                $this->report_db_error('db', $server, 'Datenbank nicht vorhanden', 'Server not connected', 'db-raw|' . $server);
            }
            $this->_query = $query;
            return 0;
        }

        try {
            if ($queryType === 'SELECT' || $queryType === 'PRAGMA' || $queryType === 'SHOW') {
                return $this->select_query($server, $query);
            }

            if ($queryType === 'INSERT') {
                return $this->insert_query($server, $query);
            }

            if ($queryType === 'UPDATE') {
                return $this->update_query($server, $query);
            }

            if ($queryType === 'DELETE') {
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

    /**
     * Führt eine SELECT-Abfrage auf der angegebenen DD aus.
     *
     * Ablauf
     * ------
     * - DD laden
     * - Tabelle/Server/Typ ermitteln
     * - Zugriff prüfen
     * - WHERE normalisieren
     * - Feldliste gegen DD validieren
     * - SQL generieren
     * - Ergebnis lesen
     *
     * @param string       $dd Datenbank-Definition
     * @param string       $where WHERE-Bedingung
     * @param string|array $columns Spaltenliste oder `*`
     * @param string       $orderby ORDER BY-Feld/Clause
     * @param string       $asc_desc Sortierreihenfolge
     * @param string       $groupby GROUP BY-Clause
     * @param int          $max Maximale Anzahl Datensätze
     * @param int          $offset Offset
     * @param int          $verify_access Zugriff prüfen
     *
     * Beispiel:
     * ```php
     * $rows = $db->select('dbxUser', "active=1", ['id', 'name'], 'name');
     * if (!$rows && $db->get_error_status() !== '') {
     *     // Zugriff verweigert oder DB-Fehler statt "keine Treffer" -
     *     // Grund steht in get_error_status()/get_error_text().
     * }
     * ```
     *
     * @return array Immer ein Array - leer bei "keine Treffer" ebenso wie bei
     *     Zugriffsfehler oder DB-Fehler. Der Grund fuer ein leeres Ergebnis
     *     steht danach in get_error_status() ('access'|'sql'|'db'|'').
     */
    public function select(string $dd = '', $where = '', $columns = '*', $orderby = '', $asc_desc = 'ASC', $groupby = '', $max = 0, $offset = 0, $verify_access = 1): array {
        $this->clear_db_error();

        $owner      = 0;
        $access     = 1;
        $fields     = '';

        $dbtab  = $this->get_dd_table($dd);
        $server = $this->get_dd_server($dd);
        $dbType = $this->get_db_type($server);

        if ($verify_access) {
            $access = $this->check_access('select', $dd);

            if ($access == 0) {
                $this->report_db_error('access', (string)$dd, 'Zugriff verweigert', 'select', 'access-select|' . $dd);
                return array();
            }

            if ($access == 2) {
                $owner = 1;
            }
        }

        $where = $this->normalize_where($dd, $where, $owner);


        if (!is_array($columns)) {
            $columns = strpos($columns, ',') !== false ? explode(',', $columns) : [$columns];
        }

        foreach ($columns as $no => $field) {
            $xfield = is_int($no) ? trim($field) : trim($no);

            if ($xfield === '*') {
                $fields = '*';
                break;
            }

            if ($this->is_fld_name($dd, $xfield)) {
                $fields .= $xfield . ',';
            }
        }

        $fields = $fields === '*' ? '*' : (rtrim($fields, ',') ?: '*');

        $query = "SELECT $fields FROM $dbtab ";

        if ($where) {
            $query .= "WHERE $where ";
        }

        if ($groupby) {
            $query .= "GROUP BY $groupby ";
        }

        if ($orderby) {
            $orderby = trim($orderby);
            $orderby = rtrim($orderby, ';');

            if (stripos($orderby, ' ASC') !== false || stripos($orderby, ' DESC') !== false) {
                $query .= "ORDER BY $orderby ";
            } else {
                $query .= "ORDER BY $orderby $asc_desc ";
            }
        }

        if ($max > 0) {
            if ($dbType === 'mysql') {
                $query .= "LIMIT $offset, $max ";
            } else {
                $query .= "LIMIT $max OFFSET $offset ";
            }
        }

        $result = $this->select_query($server, $query, $dd);

        return is_array($result) ? $result : array();
    }

    /**
     * Führt eine SELECT-Abfrage aus und gibt genau einen Datensatz zurück.
     *
     * Gibt bei leerem oder ungültigem Ergebnis einen leeren Standarddatensatz
     * der DD zurück.
     *
     * @param string       $dd Datenbank-Definition
     * @param string       $where WHERE-Bedingung
     * @param string|array $columns Spaltenliste
     * @param int          $verify_access Zugriff prüfen
     *
     * @return array Einzelner Datensatz oder Leerstruktur
     */
    public function select1(string $dd, $where = '', $columns = '*', $verify_access = 1) {
        $verifyAccess = (int)$verify_access;
        $cacheAllowed = $this->select1_cache_allowed($dd);
        $ddKey = '';
        $cacheKey = '';

        if ($cacheAllowed) {
            $ddKey = $this->select1_cache_dd_key($dd);
            $cacheKey = $this->select1_cache_key($ddKey, $where, $columns, $verifyAccess);
            if (array_key_exists($cacheKey, $this->_select1_cache[$ddKey] ?? array())) {
                $this->_select1_cache_stats['hits']++;
                return $this->_select1_cache[$ddKey][$cacheKey];
            }
            $this->_select1_cache_stats['misses']++;
        } else {
            $this->_select1_cache_stats['transaction_bypass']++;
        }

        $db_records = $this->select($dd, $where, $columns, '', 'ASC', '', 1, 0, $verify_access);

        if (!is_array($db_records)) {
            return $this->empty_record($dd)[0];
        }

        $record = null;
        if (!isset($db_records[0]) || !is_array($db_records[0])) {
            $record = $this->empty_record($dd)[0];
        } else {
            $record = $db_records[0];
        }

        if ($cacheAllowed) {
            if ($this->_select1_cache_entries < self::SELECT1_CACHE_MAX_ENTRIES) {
                $this->_select1_cache[$ddKey][$cacheKey] = $record;
                $this->_select1_cache_servers[$ddKey] = $this->get_dd_server($dd);
                $this->_select1_cache_entries++;
                $this->_select1_cache_stats['stores']++;
            } else {
                $this->_select1_cache_stats['capacity_bypass']++;
            }
        }

        return $record;
    }

    /**
     * Liefert generische Tree-Daten aus einer Parent-Tabelle und optionalen Items.
     *
     * Die Methode ist bewusst klein konfigurierbar, damit Module nur DD-Namen
     * und abweichende Feldnamen uebergeben muessen. Typischer CMS-Fall:
     * Ordner aus `dbxContentFolder`, Seiten aus `dbxContent`.
     *
     * @param string $folder_dd DD fuer die Baum-/Ordner-Tabelle
     * @param string $item_dd Optional DD fuer Kind-Items/Seiten
     * @param array $opt Feld- und Filteroptionen
     *
     * @return array{nodes:array,flat:array,folders:array,items:array}
     */
    public function select_tree(string $folder_dd, string $item_dd = '', array $opt = []): array {
        $tree_def = $this->get_dd_table_def($folder_dd);
        if (!is_array($tree_def)) $tree_def = [];

        if ($item_dd === '' && !empty($tree_def['tree_items_dd'])) {
            $item_dd = (string)$tree_def['tree_items_dd'];
        }

        $folder_id     = (string)($opt['folder_id'] ?? $tree_def['tree_id'] ?? 'id');
        $folder_parent = (string)($opt['folder_parent'] ?? $tree_def['tree_parent'] ?? 'parent_id');
        $folder_title  = (string)($opt['folder_title'] ?? $tree_def['tree_label'] ?? 'name');
        $folder_rights = (string)($opt['folder_rights'] ?? $tree_def['tree_rights'] ?? 'group_read');
        $folder_order  = (string)($opt['folder_order'] ?? $tree_def['tree_order'] ?? $folder_title);
        $folder_where  = (string)($opt['folder_where'] ?? '');

        $item_id       = (string)($opt['item_id'] ?? $tree_def['tree_items_id'] ?? 'id');
        $item_parent   = (string)($opt['item_parent'] ?? $tree_def['tree_items_parent'] ?? 'folder');
        $item_title    = (string)($opt['item_title'] ?? $tree_def['tree_items_label'] ?? 'title');
        $item_rights   = (string)($opt['item_rights'] ?? $tree_def['tree_items_rights'] ?? 'group_read');
        $item_order    = (string)($opt['item_order'] ?? $tree_def['tree_items_order'] ?? $item_title);
        $item_where    = (string)($opt['item_where'] ?? '');

        $root          = (int)($opt['root'] ?? 0);
        $verify_access = (int)($opt['verify_access'] ?? 1);

        $folder_cols = $this->tree_columns($folder_dd, [
            $folder_id,
            $folder_parent,
            $folder_title,
            $folder_rights,
            'template',
            'module',
            'sorter',
            'active',
            'activ'
        ]);

        $folders = $this->select($folder_dd, $folder_where, $folder_cols, $folder_order, 'ASC', '', 0, 0, $verify_access);
        if (!is_array($folders)) {
            $folders = [];
        }

        $item_cols = [];
        $items = [];

        if ($item_dd !== '') {
            $item_cols = $this->tree_columns($item_dd, [
                $item_id,
                $item_parent,
                $item_title,
                $item_rights,
                'permalink',
                'template',
                'description',
                'sorter',
                'active',
                'activ',
                'hits',
                'update_date'
            ]);

            $items = $this->select($item_dd, $item_where, $item_cols, $item_order, 'ASC', '', 0, 0, $verify_access);
            if (!is_array($items)) {
                $items = [];
            }
        }

        $nodes = [];
        $byParent = [];
        $flat = [];

        foreach ($folders as $row) {
            if (!is_array($row)) continue;

            $id = (int)($row[$folder_id] ?? 0);
            if ($id <= 0) continue;

            $parent = (int)($row[$folder_parent] ?? 0);
            if ($parent === $id) {
                $parent = 0;
            }

            $node = $row;
            $node['_node_id'] = 'folder-' . $id;
            $node['_type'] = 'folder';
            $node['_id'] = $id;
            $node['_parent'] = $parent;
            $node['_title'] = (string)($row[$folder_title] ?? ('Ordner ' . $id));
            $node['_rights'] = (string)($row[$folder_rights] ?? '');
            $node['_children'] = [];

            $byParent[$parent][] = $node;
        }

        foreach ($items as $row) {
            if (!is_array($row)) continue;

            $id = (int)($row[$item_id] ?? 0);
            if ($id <= 0) continue;

            $parent = (int)($row[$item_parent] ?? 0);

            $node = $row;
            $node['_node_id'] = 'page-' . $id;
            $node['_type'] = 'page';
            $node['_id'] = $id;
            $node['_parent'] = $parent;
            $node['_title'] = (string)($row[$item_title] ?? ('Seite ' . $id));
            $node['_rights'] = (string)($row[$item_rights] ?? '');
            $node['_children'] = [];

            $byParent[$parent][] = $node;
        }

        $build = function ($parent, $level) use (&$build, &$byParent, &$flat) {
            $children = $byParent[$parent] ?? [];
            $out = [];

            foreach ($children as $node) {
                $node['_level'] = $level;

                if (($node['_type'] ?? '') === 'folder') {
                    $node['_children'] = $build((int)$node['_id'], $level + 1);
                }

                $flat[] = $node;
                $out[] = $node;
            }

            return $out;
        };

        $nodes = $build($root, 0);

        return [
            'nodes' => $nodes,
            'flat' => $flat,
            'folders' => array_values($folders),
            'items' => array_values($items),
        ];
    }

    private function tree_columns(string $dd, array $columns): array {
        $out = [];

        foreach ($columns as $field) {
            $field = trim((string)$field);
            if ($field === '' || isset($out[$field])) continue;
            if ($this->is_fld_name($dd, $field)) {
                $out[$field] = $field;
            }
        }

        return array_values($out);
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

        $maxRetry = 5;
        $profileStarted = microtime(true);

        for ($try = 0; $try < $maxRetry; $try++) {
            try {
                $timers = $this->db_timers_start('save', (string) $dd);

                try {
                    $stmt = $this->db[$server]->prepare($sql);
                    $stmt->execute(array_values($field_values));

                    $id = $this->db[$server]->lastInsertId();
                    $this->record_performance_query($server, $sql, $profileStarted, 1, true);
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

                        $traceData = [
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

                        $trace_result = $this->insert('dbxTrace', $traceData, 0, 0, 0, 0);

                        if ($trace_result !== 1) {
                            dbx()->sys_msg(
                                'warning',
                                'trace',
                                'dbxTrace',
                                'trace insert failed',
                                json_encode($traceData, JSON_UNESCAPED_UNICODE)
                            );
                        }

                        // Der rekursive Trace-Insert darf die Insert-ID des
                        // eigentlichen Datensatzes nicht überschreiben.
                        $this->_insert_id = $id;
                    }
                }

                return 1;
            } catch (PDOException $e) {
                if (empty($this->_tx[$server]) && $this->isRetryable($e) && $try < $maxRetry - 1) {
                    usleep(120000 + random_int(0, 150000));
                    continue;
                }

                $this->report_db_error('sql', (string)$server, 'SQL-Fehler', $e->getMessage(), 'sql-insert|' . $server);
                $this->record_performance_query($server, $sql, $profileStarted, 0, false);

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

            if ($access == 2) {
                $owner = 1;
            }
        }

        if (!$access) {
            $this->report_db_error('access', (string)$dd, 'Zugriff verweigert', 'update', 'access-update|' . $dd);
            return -1;
        }

        $where = $this->normalize_where($dd, $where, $owner);

        $beforeRows   = [];
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
                $beforeRows = $this->select($dd, $where);
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

        $maxRetry = 5;
        $profileStarted = microtime(true);

        for ($try = 0; $try < $maxRetry; $try++) {
            try {
                $timers = $this->db_timers_start('save', (string) $dd);

                try {
                    $stmt = $this->db[$server]->prepare($sql);
                    $stmt->execute($vals);

                    $count = $stmt->rowCount();
                    $this->record_performance_query($server, $sql, $profileStarted, max(0, (int) $count), true);
                } finally {
                    $this->db_timers_stop($timers);
                }

                $this->_update_count = $count;
                $this->invalidate_select1_cache((string)$dd);

                if ($trace && $write_trace && is_array($beforeRows)) {
                    foreach ($beforeRows as $row) {
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
                if (empty($this->_tx[$server]) && $this->isRetryable($e) && $try < $maxRetry - 1) {
                    usleep(120000 + random_int(0, 150000));
                    continue;
                }

                $this->report_db_error('sql', (string)$server, 'SQL-Fehler', $e->getMessage(), 'sql-update|' . $server);
                $this->record_performance_query($server, $sql, $profileStarted, 0, false);

                return -2;
            }
        }

        return -2;
    }

    /**
     * Führt eine UPDATE-Abfrage aus und gibt die Anzahl
     * der betroffenen Zeilen zurück.
     *
     * @param string $server Datenbankserver
     * @param string $sql SQL-Update-Statement
     *
     * @return int Anzahl der betroffenen Zeilen oder -2
     */
    public function update_query($server, $sql) {
        $this->_update_count = 0;
        $retval = -2;

        $timers = $this->db_timers_start('save');

        try {
            $stmt = $this->query($server, $sql);
        } finally {
            $this->db_timers_stop($timers);
        }

        if ($stmt) {
            $retval = $stmt->rowCount();
        }

        $this->_update_count = $retval;

        return $retval;
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

            if ($access == 2) {
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

            if ($access == 2) {
                $owner = 1;
            }
        }

        $beforeRows  = [];
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
                    $beforeRows = $this->select($dd, $where);

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

            if ($trace && $write_trace && is_array($beforeRows)) {
                $pk = $this->get_dd_primary($dd);

                foreach ($beforeRows as $row) {
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
     * Zählt Datensätze einer DD oder Tabelle.
     *
     * - Bei DD-Aufruf werden Tabelle und Server aus der DD ermittelt.
     * - Bei explizitem `$server` wird `$dd` als Tabellenname behandelt.
     * - `where = 'new'` liefert direkt `0`.
     *
     * Der DB-Zugriff läuft über den zentralen Query-Pfad,
     * damit Fehlerbehandlung und Retry-Verhalten konsistent bleiben.
     *
     * @param string $dd DD-Name oder Tabellenname
     * @param string $where WHERE-Bedingung
     * @param string $server Optional expliziter Server
     *
     * @return int Anzahl, -1 oder -2
     */
    public function count($dd, $where = '', $server = '') {
        $count = -2;
        $explicitServer = $server !== '';

        if ($where == 'new') {
            return 0;
        }

        if (!$server) {
            $dbtab = $this->get_dd_table($dd);
        }

        if ($server) {
            $dbtab = $dd;
        }

        if (!$server) {
            $server = $this->get_dd_server($dd);
        }

        if (!$dbtab || !$server) {
            return $count;
        }

        if (!$explicitServer) {
            $access = $this->check_access('select', (string)$dd);
            if ($access == 0) {
                $this->report_db_error('access', (string)$dd, 'Zugriff verweigert', 'count', 'access-count|' . $dd);
                return -1;
            }

            $where = $this->normalize_where($dd, $where, $access == 2 ? 1 : 0);
        } else {
            $where = is_array($where) ? '' : $where;
        }

        $query = "SELECT COUNT(*) AS cnt FROM $dbtab";

        if (!empty($where)) {
            $query .= " WHERE $where";
        }

        $timers = $this->db_timers_start('select', $explicitServer ? '' : (string) $dd);

        try {
            $stmt = $this->query($server, $query);

            if (!is_object($stmt)) {
                if ($this->get_error_status() === '') {
                    $this->report_db_error('sql', (string)$server, 'SQL-Fehler', 'COUNT fehlgeschlagen', 'sql-count|' . $server);
                }
                return -2;
            }

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (is_array($row) && isset($row['cnt'])) {
                $count = (int) $row['cnt'];
            } else {
                $count = 0;
            }
        } catch (PDOException | Exception $e) {
            $this->report_db_error('sql', (string)$server, 'SQL-Fehler', $e->getMessage(), 'sql-count|' . $server);
            $count = -2;
        } finally {
            $this->db_timers_stop($timers);
        }

        return $count;
    }

    /**
     * Prueft ohne Schema-Warnung, ob SQLite seine AUTOINCREMENT-Tabelle hat.
     *
     * `sqlite_sequence` existiert erst, nachdem in der Datenbank mindestens
     * eine Tabelle mit `AUTOINCREMENT` angelegt wurde. Ihr Fehlen ist deshalb
     * kein Datenbankfehler.
     */
    public function sqlite_sequence_exists(string $server): bool {
        if (strtolower((string)$this->get_db_type($server)) !== 'sqlite') {
            return false;
        }

        $rows = $this->select_query(
            $server,
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'sqlite_sequence' LIMIT 1"
        );

        return is_array($rows) && count($rows) > 0;
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
        $dbType = $this->get_db_type($server);

        dbx()->debug("#empty dd=($dd) Server=($server) Tab=($dbtab) Type=($dbType)");

        if (!$dbtab || !$server || !$dbType) {
            return 0;
        }

        switch ($dbType) {
            case 'mysql':
                $sql = "TRUNCATE TABLE $dbtab";
                $ok = $this->rawQuery($server, $sql);

                if ($ok) {
                    $sql = "ALTER TABLE $dbtab AUTO_INCREMENT = 1";
                    $this->rawQuery($server, $sql);
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
                $ok = $this->rawQuery($server, $sql);
                break;

            case 'sqlsrv':
                $sql = "TRUNCATE TABLE $dbtab";
                $ok = $this->rawQuery($server, $sql);
                break;

            case 'oci':
            case 'firebird':
            case 'cubrid':
            case 'dblib':
            case 'ibm':
            case 'informix':
            case 'odbc':
                $sql = "DELETE FROM $dbtab";
                $ok = $this->rawQuery($server, $sql);
                break;

            default:
                dbx()->sys_msg(
                    'warning',
                    'dd',
                    $dd,
                    "no type def ($dbType)",
                    'check'
                );

                $ok = 0;
        }

        return $ok;
    }

    /**
     * Leert eine Datenbank-Tabelle anhand ihrer DD und setzt die ID zurueck.
     *
     * Verwendung:
     * Admin-/Wartungsfunktionen koennen damit gezielt Tabellen leeren, wenn die
     * Datenquelle wirklich dbxDB ist.
     *
     * @param string $dd Datenbeschreibung
     *
     * @return int 1 bei Erfolg, 0 bei Fehler
     */
    public function delete_tab($dd) {
        $ok = $this->empty($dd);

        if (!$ok) {
            return 0;
        }

        return $this->optimize_tab($dd);
    }

    /**
     * Optimiert eine Tabelle anhand ihrer DD nach Wartungsaktionen.
     *
     * MySQL nutzt OPTIMIZE TABLE. SQLite nutzt VACUUM fuer die ganze
     * Datenbankdatei, weil SQLite keine einzelne Tabellen-Vacuum kennt.
     *
     * @param string $dd Datenbeschreibung
     *
     * @return int 1 bei Erfolg oder wenn keine Optimierung noetig/definiert ist, 0 bei Fehler
     */
    public function optimize_tab($dd) {
        $this->load_dd($dd);
        $server = $this->get_dd_server($dd);
        $dbtab  = $this->get_dd_table($dd);
        $dbType = strtolower((string)$this->get_db_type($server));

        if (!$server || !$dbtab || !$dbType) {
            return 0;
        }

        switch ($dbType) {
            case 'mysql':
                $table = $this->quote_db_identifier_for_type($dbType, $dbtab);
                return (int)$this->exec($server, 'OPTIMIZE TABLE ' . $table);

            case 'sqlite':
                return (int)$this->exec($server, 'VACUUM');

            default:
                dbx()->debug("#optimize tab skipped dd=($dd) Server=($server) Tab=($dbtab) Type=($dbType)");
                return 1;
        }
    }

    /**
     * Ermittelt den erwarteten SQLite-Dateipfad eines Server-Eintrags.
     *
     * @param string $server Servername aus der DD
     * @return string Absoluter Pfad oder leer
     */
    private function resolve_sqlite_db_path(string $server): string {
        if (!preg_match('/\.(db3|sqlite|sqlite3)$/i', $server)) {
            return '';
        }

        $activ_modul = dbx()->get_system_var('dbx_activ_modul', 'dbx');
        $sqlite_modul = '';
        $sqlite_name  = '';

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

        $file1 = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/dbx/db/' . $sqlite_name);
        $file2 = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/' . $sqlite_modul . '/db/' . $sqlite_name);

        if (is_file($file1)) {
            return $file1;
        }
        if (is_file($file2)) {
            return $file2;
        }

        return $file2;
    }

    /**
     * Schreibt Schema-/DB-Probleme einmal pro Request in SysMsg.
     *
     * @param string $key Dedup-Schluessel
     * @param string $rid Betroffener Server oder DD
     * @param string $why Kurzgrund fuer SysMsg
     * @param string $what Detail
     * @return void
     */
    private function log_db_schema_issue(string $key, string $rid, string $why, string $what): void {
        if ($key === '') {
            return;
        }

        $this->report_db_error('db', $rid, $why, $what, $key);
    }

    /**
     * Prueft, ob eine Tabelle existiert.
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
    function get_table_exist($dd, $dbtab = '', bool $reportMissing = true) {
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

        $dbType = $this->get_db_type($server);

        if ($dbType === 'sqlite') {
            $dbFile = $this->resolve_sqlite_db_path($server);
            if ($dbFile !== '' && !is_file($dbFile)) {
                if (!$reportMissing) return 0;
                $this->log_db_schema_issue(
                    'db-missing|' . $server,
                    $rid,
                    'Datenbank nicht vorhanden',
                    $dbFile
                );
                return 0;
            }
        }

        if (!$this->connect_db_server($server)) {
            if (!$reportMissing) return 0;
            $this->log_db_schema_issue(
                'db-connect|' . $server,
                $rid,
                'Datenbank nicht vorhanden',
                trim((string)$this->_dbMessage) !== '' ? (string)$this->_dbMessage : (string)$server
            );
            return 0;
        }

        if (!isset($this->db[$server]) || !is_object($this->db[$server])) {
            if (!$reportMissing) return 0;
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

            switch ($dbType) {
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

            if (!$exists && $reportMissing) {
                $why  = 'Tabelle nicht vorhanden';
                $what = $dbtab . ' (' . $server . ')';

                if ($dbType === 'sqlite') {
                    $countStmt = $this->db[$server]->query(
                        "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"
                    );
                    $tableCount = $countStmt ? (int)$countStmt->fetchColumn() : 0;
                    if ($tableCount === 0) {
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
            if (!$reportMissing) return 0;
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
     * Liefert die Spaltennamen einer Tabelle.
     *
     * Ersetzt modulseitiges Roh-SQL wie `PRAGMA table_info(...)` oder
     * `SHOW COLUMNS FROM ...`: Fachmodule sollen Schema-Introspektion nur
     * ueber dbxDB anfordern, nicht per eigenem SQL-String je Datenbanktyp.
     *
     * @param string $server Datenbankserver
     * @param string $table Tabellenname (nur [A-Za-z0-9_])
     *
     * @return array Spaltennamen; leeres Array wenn Tabelle/Verbindung fehlt
     */
    public function get_table_columns(string $server, string $table): array {
        if (!$server || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
            return array();
        }
        if (!$this->connect_db_server($server) || !isset($this->db[$server]) || !is_object($this->db[$server])) {
            return array();
        }

        $dbType = $this->get_db_type($server);
        $columns = array();

        try {
            switch ($dbType) {
                case 'sqlite':
                    $stmt = $this->db[$server]->query('PRAGMA table_info(' . $table . ')');
                    foreach ($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array() as $row) {
                        $columns[] = (string)($row['name'] ?? '');
                    }
                    break;

                case 'mysql':
                    $stmt = $this->db[$server]->prepare('SHOW COLUMNS FROM ' . $table);
                    $stmt->execute();
                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $columns[] = (string)($row['Field'] ?? '');
                    }
                    break;

                default:
                    $stmt = $this->db[$server]->prepare(
                        'SELECT column_name FROM information_schema.columns WHERE table_name = ?'
                    );
                    $stmt->execute(array($table));
                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $columns[] = (string)($row['column_name'] ?? '');
                    }
                    break;
            }
        } catch (PDOException $e) {
            return array();
        }

        return array_values(array_filter($columns, static fn($name) => $name !== ''));
    }

    /**
     * Prueft, ob eine Tabelle die angegebene Spalte besitzt.
     *
     * @param string $server Datenbankserver
     * @param string $table Tabellenname
     * @param string $column Spaltenname
     *
     * @return bool
     */
    public function has_table_column(string $server, string $table, string $column): bool {
        if ($column === '') {
            return false;
        }
        $column = strtolower($column);
        foreach ($this->get_table_columns($server, $table) as $name) {
            if (strtolower($name) === $column) {
                return true;
            }
        }
        return false;
    }

    /**
     * Liefert alle Tabellen eines Servers mit Datensatzanzahl zurück.
     *
     * Hinweis
     * -------
     * Die Rückgabestruktur bleibt unverändert:
     * - `server`
     * - `name`
     * - `count`
     *
     * Falls das Zählen fehlschlägt, wird `count` auf `-1` gesetzt.
     *
     * @param string $server Datenbankserver
     * @param string $not Tabellenname, der ausgeschlossen werden soll
     *
     * @return array Liste der Tabellen
     */
    public function get_db_tables($server, $not = 'sqlite_sequence') {
        $tables = array();
        $ok = $this->connect_db_server($server);

        if ($ok) {
            $dbType = $this->get_db_type($server);
            $tableRows = array();

            switch ($dbType) {
                case 'mysql':
                    $sql = "SHOW TABLES";
                    $tableRows = $this->rawQuery($server, $sql);
                    break;

                case 'sqlite':
                    $sql = "SELECT name FROM sqlite_master"
                         . " WHERE type='table' AND name NOT LIKE 'sqlite_%'";
                    $tableRows = $this->rawQuery($server, $sql);
                    break;

                case 'oci':
                    $sql = "SELECT table_name FROM user_tables";
                    $tableRows = $this->rawQuery($server, $sql);
                    break;

                case 'pgsql':
                    $sql = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'";
                    $tableRows = $this->rawQuery($server, $sql);
                    break;

                case 'sqlsrv':
                    $sql = "SELECT table_name FROM information_schema.tables";
                    $tableRows = $this->rawQuery($server, $sql);
                    break;

                default:
                    $tableRows = array();
                    dbx()->sys_msg('warning', 'db', $server, "unsupported db type ($dbType)", 'get_db_tables');
                    break;
            }

            if (is_array($tableRows)) {
                foreach ($tableRows as $tableRow) {
                    $tableName = reset($tableRow);

                    if ($tableName != $not) {
                        $count = $this->count($tableName, '', $server);

                        if ($count < 0) {
                            $count = -1;
                        }

                        $tables[] = array(
                            'server' => $server,
                            'name'   => $tableName,
                            'count'  => $count
                        );
                    }
                }
            }
        }

        return $tables;
    }

    /**
     * Gibt die komplette Table-Definition einer DD zurück.
     *
     * Bei fehlender oder ungültiger DD wird `0` zurückgegeben.
     *
     * @param string $dd Name der Datenbeschreibung
     *
     * @return mixed Table-Definition oder 0
     */
    public function get_dd_table_def($dd) {
        $table = 0;
        dbx()->debug("F-Load dd=($dd)");

        $dd_sys    = $this->load_dd($dd);
        $dd_status = $dd_sys['dd_status'] ?? 0;
        $dd_modul  = $dd_sys['dd_modul'] ?? '';
        $dd_name   = $dd_sys['dd_name'] ?? '';

        if ($dd_status == 1) {
            $table = $_SESSION['dbx']['cache']['dd'][$dd_modul][$dd_name]['table'] ?? 0;
        }

        return $table;
    }

    /**
     * Gibt die Felddefinitionen einer DD zurück.
     *
     * Optional können die Felder als Key/Label-Liste zurückgegeben werden.
     *
     * - `label = 0` → komplette Felddefinitionen
     * - `label = 1` → Array `feldname => label`
     *
     * Die Funktion arbeitet defensiv:
     * - bei ungültiger DD oder fehlenden Felddefinitionen wird `0` zurückgegeben
     * - bei `label = 1` werden fehlende Labels sauber auf den Feldnamen zurückgeführt
     *
     * @param string $dd Name der Datenbeschreibung
     * @param int    $label 0 = komplette Felddefinitionen, 1 = Name => Label
     *
     * @return mixed Felddefinitionen oder 0
     */
    public function get_dd_fields($dd, $label = 0) {
        $fields = 0;

        $dd_sys    = $this->load_dd($dd);
        $dd_status = $dd_sys['dd_status'] ?? 0;
        $dd_modul  = $dd_sys['dd_modul'] ?? '';
        $dd_name   = $dd_sys['dd_name'] ?? '';

        if ($dd_status == 1) {
            $fields = $_SESSION['dbx']['cache']['dd'][$dd_modul][$dd_name]['fields'];
        }

        if ($label && $dd_status == 1) {
            $xfields = array();

            if (is_array($fields)) {
                foreach ($fields as $no => $field) {
                    $xname  = $field['name'] ?? '';
                    $xlabel = $xname;

                    if ($xname === '') {
                        continue;
                    }

                    if ($label == 1 && isset($field['label']) && $field['label'] !== '') {
                        $xlabel = $field['label'];
                    }

                    $xfields[$xname] = $xlabel;
                }
            }

            $fields = $xfields;
        }

        return $fields;
    }

    /**
     * Ermittelt die Report-Felder einer DD für eine übergebene Feldliste.
     *
     * @param string $dd Name der Datenbeschreibung
     * @param string $flds Feldliste oder `*`
     * @param int    $label 1 = Label verwenden
     *
     * @return array Feldliste für Reports
     */
    public function get_rpt_fields($dd, $flds, $label = 1) {
        $fields = $this->get_dd_fields($dd, $label);

        if ($flds != '*') {
            $cols = array();
            $flds = explode(",", $flds);

            if (is_array($flds)) {
                foreach ($flds as $no => $field) {
                    $field = trim($field);
                    $cols[$field] = $field;
                }

                $flds = $cols;
            }
        } else {
            $flds = $fields;
        }

        return $flds;
    }

    /**
     * Gibt alle DD-Spaltennamen als CSV-String zurück.
     *
     * @param string $dd Name der Datenbeschreibung
     *
     * @return string CSV-Liste der Spalten
     */
    public function get_dd_cols(string $dd): string {
        $fields = $this->get_dd_fields($dd);

        if (!$fields || !is_array($fields)) {
            return '';
        }

        $cols = [];

        foreach ($fields as $f) {
            if (empty($f['name'])) {
                continue;
            }

            $cols[] = $f['name'];
        }

        return implode(',', $cols);
    }

    /**
     * Erzeugt die neue Grid-Column-Syntax aus einer DD.
     *
     * Syntax
     * ------
     * - `name[label]:type`
     * - optional `:p` / `:!v`
     * - optional `@gruppe`
     *
     * Die Funktion arbeitet defensiv bei unvollständigen DD-Felddefinitionen.
     *
     * @param string $dd Name der Datenbeschreibung
     *
     * @return string Kommagetrennte Grid-Definition
     */
    public function get_dd_grid_cols(string $dd): string {
        $fields = $this->get_dd_fields($dd);

        if (!$fields || !is_array($fields)) {
            return '';
        }

        $cols = [];

        foreach ($fields as $f) {
            if (empty($f['name'])) {
                continue;
            }

            $name = $f['name'];

            if (str_starts_with($name, '_')) {
                continue;
            }

            $ddType   = $f['type'] ?? 'text';
            $gridType = $this->map_dd_type_to_grid_type($ddType);

            $label = '';
            if (!empty($f['label']) && is_string($f['label'])) {
                $label = trim($f['label']);
            }

            $label = str_replace([':', '[', ']'], '-', $label);

            if (!$label) {
                $label = $name;
            }

            $fieldWithLabel = $name . '[' . $label . ']';

            $protect = isset($f['protect']) ? (string) $f['protect'] : '0';

            $group = '';
            if (!empty($f['group']) && is_string($f['group'])) {
                $group = '@' . trim($f['group']);
            }

            if ($protect === '2') {
                $cols[] = $fieldWithLabel . ':' . $gridType . ':!v' . $group;
                continue;
            }

            if ($protect === '1') {
                $cols[] = $fieldWithLabel . ':' . $gridType . ':p' . $group;
                continue;
            }

            $cols[] = $fieldWithLabel . ':' . $gridType . $group;
        }

        return implode(',', $cols);
    }

    /**
     * Mappt DD-Feldtypen auf Grid-Feldtypen.
     *
     * @param string $ddType DD-Typ
     *
     * @return string Grid-Typ
     */
    public function map_dd_type_to_grid_type(string $ddType): string {
        $t = strtolower(trim($ddType));

        switch ($t) {
            case 'int':
            case 'integer':
            case 'bigint':
            case 'smallint':
            case 'mediumint':
            case 'tinyint':
            case 'float':
            case 'double':
            case 'decimal':
            case 'numeric':
            case 'real':
                return 'number';

            case 'date':
            case 'datetime':
            case 'timestamp':
                return 'date';

            case 'lookup':
            case 'select':
                return 'lookup';

            case 'varchar':
            case 'char':
            case 'text':
            case 'string':
            default:
                return 'text';
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

        if (dbx()->can('dbxRunAsAdmin')) {
            return 1;
        }

        $access = 0;
        $groups = '';
        $table  = $this->get_dd_table($dd, 1);

        if (is_array($table)) {
            $modeKey = '';
            $ownerKey = '';

            if ($mode == 'insert') {
                $modeKey = 'create';
                $ownerKey = 'create_owner';
            }

            if ($mode == 'update') {
                $modeKey = 'update';
                $ownerKey = 'update_owner';
            }

            if ($mode == 'delete') {
                $modeKey = 'delete';
                $ownerKey = 'delete_owner';
            }

            if ($mode == 'select') {
                $modeKey = 'read';
                $ownerKey = 'read_owner';
            }

            $groups = $modeKey !== '' ? (string)($table[$modeKey] ?? '') : '';
            $access = dbx()->can(access_groups: $groups);

            if (!$access && dbx()->user() > 0) {
                if (preg_match('/\bowner\b/', (string) $groups)) {
                    $access = $mode === 'insert' ? 1 : 2;
                }
            }

            if (!$access && $ownerKey !== '' && dbx()->user() > 0) {
                $ownerGroups = trim((string)($table[$ownerKey] ?? ''));
                if ($ownerGroups !== '') {
                    if ($ownerGroups === '*' || preg_match('/\bowner\b/', $ownerGroups) || dbx()->can(access_groups: $ownerGroups)) {
                        $access = $mode === 'insert' ? 1 : 2;
                        $groups = $ownerGroups;
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

        $validate_rules = $this->_validatior_rules;
        $validate_type  = $this->_validatior_type;
        $validate_error = $this->_validatior_error;
        $validate_mode  = $this->_validatior_mode;

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
                    $ok = $this->oValidator->validate($value, $rules, $name);
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
                        $ok = $this->oValidator->validate($value, $rules, $name);
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

                        $value = $this->oValidator->clean($value, $rules, $length, $name);
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

    /**
     * Prüft, ob ein Feldname in der DD existiert.
     *
     * Die Prüfung erfolgt defensiv über die DD-Felddefinitionen.
     * Bei ungültiger oder leerer DD-Feldliste wird `false` zurückgegeben.
     *
     * @param string $dd Datenstrukturdefinition
     * @param mixed  $xfield Zu prüfender Feldname
     *
     * @return bool True wenn Feld vorhanden
     */
    private function is_fld_name(string $dd, $xfield) {
        $db_fields = $this->get_dd_fields($dd);

        if (!is_array($db_fields) || empty($db_fields) || !is_string($xfield) || $xfield === '') {
            return false;
        }

        foreach ($db_fields as $field) {
            if (!isset($field['name'])) {
                continue;
            }

            if ($field['name'] === $xfield) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalisiert und erweitert eine WHERE-Bedingung für SQL-Abfragen.
     *
     * Verhalten
     * ---------
     * - `'new'` oder `'0'` → leer
     * - Integer → `<primary_key> = value`
     * - optionaler Owner-Filter wird sicher mit Klammern ergänzt
     *
     * Wichtige Stabilitätsregel
     * -------------------------
     * Für numerische WHERE-Werte wird, wenn möglich, der Primärschlüssel
     * der übergebenen DD verwendet. Dadurch bleiben Aufrufe wie
     * `select('kunden', 15)` oder `update('kunde', [...], 15)` stabil,
     * auch wenn der Primärschlüssel nicht `id` heißt.
     *
     * Rückwärtskompatibilität
     * -----------------------
     * Die DD ist optional. Ohne DD bleibt der Fallback wie bisher
     * `$this->_fld_id` bzw. `id`.
     *
     * @param mixed       $where WHERE-Bedingung
     * @param int         $owner 1 = Owner-Filter aktiv
     * @param string|null $dd Optionaler DD-Name für Primärschlüssel-Auflösung
     *
     * @return string Sichere WHERE-Bedingung
     */
    /**
     * Normalisiert WHERE-Eingaben zentral.
     *
     * Bestehende String-WHEREs bleiben kompatibel. Neue Array-WHEREs werden
     * anhand der DD-Felder gebaut und User-Suchwerte zentral validiert/escaped.
     *
     * @param string $dd Datenstrukturdefinition
     * @param mixed  $where String oder strukturierte WHERE-Definition
     * @param int    $owner Owner-Filter aktiv
     *
     * @return string SQL-WHERE
     */
    public function normalize_where(string $dd, $where = '', int $owner = 0): string {
        if (!is_array($where)) {
            return $this->check_where($where, $owner, $dd);
        }

        $conditions = array();
        $server     = $this->get_dd_server($dd);

        if (isset($where['raw'])) {
            if (!empty($where['trusted'])) {
                return $this->check_where((string) $where['raw'], $owner, $dd);
            }

            dbx()->sys_msg('warning', 'db', $dd, 'raw where blocked', (string) $where['raw']);
            return $this->check_where('', $owner, $dd);
        }

        if (isset($where['search']) && is_array($where['search'])) {
            $search = $where['search'];
            $built  = $this->build_search_where(
                $dd,
                $search['value'] ?? '',
                is_array($search['like'] ?? null) ? $search['like'] : array(),
                is_array($search['equal'] ?? null) ? $search['equal'] : array(),
                (string) ($search['mode'] ?? 'starts_with')
            );

            if ($built !== '') {
                $conditions[] = $built;
            }
        }

        foreach ($where as $field => $value) {
            if (in_array($field, array('search', 'raw', 'trusted'), true)) {
                continue;
            }

            $field = trim((string) $field);

            if (!$this->is_fld_name($dd, $field)) {
                continue;
            }

            if (is_array($value)) {
                $mode = (string) ($value['mode'] ?? 'starts_with');

                if (isset($value['like'])) {
                    $likeValue = $this->escape_like((string) $value['like'], $server);
                    $pattern   = $likeValue . '%';

                    if ($mode === 'contains') {
                        $pattern = '%' . $likeValue . '%';
                    } elseif ($mode === 'ends_with') {
                        $pattern = '%' . $likeValue;
                    } elseif ($mode === 'exact') {
                        $pattern = $likeValue;
                    }

                    $conditions[] = "$field LIKE '$pattern' ESCAPE '\\'";
                    continue;
                }

                if (array_key_exists('value', $value)) {
                    $value = $value['value'];
                } else {
                    continue;
                }
            }

            if ($value === null) {
                $conditions[] = "$field IS NULL";
                continue;
            }

            if (is_array($value) || is_object($value)) {
                continue;
            }

            if (dbx()->is_int_value($value)) {
                $conditions[] = "$field = " . (int) $value;
            } else {
                $conditions[] = "$field = '" . $this->escape((string) $value, $server) . "'";
            }
        }

        return $this->check_where(implode(' AND ', $conditions), $owner, $dd);
    }

    function check_where($where, $owner = 0, $dd = '') {
        $key = $this->_fld_id ?? 'id';

        if ($where == 'new' || $where == '0') {
            $where = '';
        }

        if (dbx()->is_int_value($where)) {
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

    /**
     * Escaped einen String für die sichere Verwendung in einer SQL-Abfrage.
     *
     * Hinweis
     * -------
     * Diese Methode dient nur zum Escapen einzelner Strings.
     * Für INSERT/UPDATE wird weiterhin mit Prepared Statements gearbeitet.
     *
     * @param string     $string Zu escapender String
     * @param string|int $server Serverindex
     *
     * @return string Escapeter String
     */
    public function escape($string, $server) {
        if (!$this->connect_db_server($server)) {
            return str_replace("'", "''", $string);
        }

        return substr($this->db[$server]->quote($string), 1, -1);
    }

    /**
     * Escaped einen User-Suchwert fuer SQL-LIKE.
     *
     * Der Wert wird zuerst als LIKE-Pattern neutralisiert (%/_/\) und danach
     * fuer den konkreten Datenbankserver quoted. Die Rueckgabe ist ohne
     * aeussere Quotes und wird von build_search_where() eingefasst.
     *
     * @param string $value User-Suchwert
     * @param string $server Datenbankserver
     *
     * @return string Escapeter LIKE-Wert
     */
    public function escape_like($value, $server) {
        $value = (string) $value;
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace('%', '\%', $value);
        $value = str_replace('_', '\_', $value);

        return $this->escape($value, $server);
    }

    /**
     * Baut eine sichere Such-WHERE fuer einfache Report-Suchen.
     *
     * - validiert den Suchtext mit `sqlsearch`
     * - validiert alle Feldnamen gegen die DD
     * - escaped LIKE- und Exact-Werte zentral
     *
     * @param string $dd Datenbeschreibung
     * @param mixed  $search User-Suchwert
     * @param array  $likeFields Felder fuer LIKE
     * @param array  $equalFields Felder fuer exakte Suche
     * @param string $mode starts_with|contains|ends_with|exact
     *
     * @return string SQL-WHERE oder leerer String
     */
    public function build_search_where(string $dd, $search, array $likeFields, array $equalFields = array(), string $mode = 'starts_with'): string {
        $search = trim((string) $search);

        if ($search === '') {
            return '';
        }

        if (!$this->oValidator->validate($search, 'sqlsearch|max=128', 'search')) {
            dbx()->sys_msg('warning', 'db', $dd, 'invalid search', $search);
            return '';
        }

        $server = $this->get_dd_server($dd);

        if (!$server) {
            return '';
        }

        $likeValue  = $this->escape_like($search, $server);
        $exactValue = $this->escape($search, $server);
        $conditions = array();

        foreach ($likeFields as $field) {
            $field = trim((string) $field);

            if (!$this->is_fld_name($dd, $field)) {
                continue;
            }

            $pattern = $likeValue;

            if ($mode === 'contains') {
                $pattern = '%' . $likeValue . '%';
            } elseif ($mode === 'ends_with') {
                $pattern = '%' . $likeValue;
            } elseif ($mode !== 'exact') {
                $pattern = $likeValue . '%';
            }

            $conditions[] = "$field LIKE '$pattern' ESCAPE '\\'";
        }

        foreach ($equalFields as $field) {
            $field = trim((string) $field);

            if (!$this->is_fld_name($dd, $field)) {
                continue;
            }

            $conditions[] = "$field = '$exactValue'";
        }

        if (!count($conditions)) {
            return '';
        }

        return '(' . implode(' OR ', $conditions) . ')';
    }

    /**
     * Erzeugt einen Zeitstempel mit Millisekunden.
     *
     * @return string Zeitstempel im Format `Y-m-d H:i:s.v`
     */
    private function now_ms(): string {
        return (new DateTime())->format('Y-m-d H:i:s.v');
    }
}
