<?php
require_once __DIR__ . '/dbxDB.class.php';
require_once __DIR__ . '/dbxDDModel.trait.php';
require_once __DIR__ . '/dbxDDIntrospection.trait.php';
require_once __DIR__ . '/dbxDDSqlDialect.trait.php';
require_once __DIR__ . '/dbxDDProcessState.trait.php';
require_once __DIR__ . '/dbxDDSchemaMapping.trait.php';
require_once __DIR__ . '/dbxDDBackupRestore.trait.php';
require_once __DIR__ . '/dbxDDSynchronization.trait.php';

/**
 * @brief Verwaltet Data Definitions, Schemaabgleich und DB-DD-Synchronisation.
 *
 * =========================================================
 * DBX DD SYSTEMCLASS (dbxDD)
 * =========================================================
 *
 * Zweck
 * -----
 * dbxDD ist die technische Infrastruktur für DD-Verwaltung,
 * DD-Datei-Erzeugung, Schema-Lesen, Schema-Vergleich,
 * Backup/Restore sowie DB <-> DD Synchronisationsprozesse.
 *
 * Wichtige Architekturregel
 * -------------------------
 * - dbxDD komponiert die zentrale dbxDB-Instanz.
 * - Verbindungen und DD-Cache werden dadurch nicht doppelt aufgebaut.
 * - dbxDD stellt nur die benötigte Infrastruktur bereit.
 * - Die eigentliche Prozesssteuerung, Benutzerentscheidungen,
 *   Feld-Zuordnungen und Konfliktauflösungen sollen später
 *   über eine Admin-Verwaltung und/oder externe Mapping-Dateien erfolgen.
 *
 * Mapping-Grundsatz
 * -----------------
 * - Es wird immer zuerst ein Auto-Mapping versucht.
 * - Benutzer-/Admin-Mapping oder externe Mapping-Dateien
 *   müssen dieses Auto-Mapping gezielt übersteuern können.
 * - Das DD ist das fachliche Soll-Modell und hat Vorrang.
 *
 * Typ-Grundsatz
 * -------------
 * - DD-Feldtypen sollen kanonisch und DB-unabhängig sein.
 * - Als kanonische Basis werden MySQL-nahe DD-Typen verwendet
 *   (`int`, `char`, `varchar`, `text`, `date`, `datetime`, ...)
 * - SQLite/db3-Roh-Typen wie `TEXT`, `INTEGER` usw. sollen
 *   nicht ungefiltert ins DD übernommen werden.
 * - Die konkrete Umsetzung auf die jeweilige Ziel-DB übernimmt dbxDB.
 *
 * Beispiel
 * --------
 * ```php
 * $oDD = dbx()->get_system_obj('dbxDD');
 *
 * // DD aus vorhandener Tabelle erzeugen
 * $oDD->create_dd('crm', 'kunden', 'crm|kunden.db3', 'kunden');
 *
 * // Sync-Plan prüfen
 * $plan = $oDD->sync_dd_to_db('crm', 'kunden', 'check');
 *
 * // DB -> DD synchronisieren
 * $res = $oDD->sync_db_to_dd('crm', 'kunden', 'merge');
 * ```
 */
class dbxDD
{
    use dbxDDModelTrait;
    use dbxDDIntrospectionTrait;
    use dbxDDSqlDialectTrait;
    use dbxDDProcessStateTrait;
    use dbxDDSchemaMappingTrait;
    use dbxDDBackupRestoreTrait;
    use dbxDDSynchronizationTrait;

    private dbxDB $database;

    /** Kompatible Sicht auf den zentralen Verbindungspool. */
    public $db = array();

    protected string $_remember_modul = 'dbx';
    protected float $_max_step_runtime = 3.0;
    protected int $_chunk_size = 500;

    /**
     * Initialisiert dbxDD.
     *
     */
    public function __construct()
    {
        $database = dbx()->get_system_obj('dbxDB');
        if (!$database instanceof dbxDB) {
            throw new RuntimeException('dbxDD benötigt den zentralen dbxDB-Service.');
        }
        $this->database = $database;
        $this->db =& $this->database->db;
    }

    /**
     * Delegiert DB-Operationen an genau den zentralen dbxDB-Service.
     * dbxDD selbst enthält ausschließlich Schema-/Dictionary-Verhalten.
     */
    public function __call(string $method, array $arguments): mixed
    {
        if (!is_callable(array($this->database, $method))) {
            throw new BadMethodCallException('Unbekannte dbxDD/dbxDB-Methode: ' . $method);
        }
        return $this->database->{$method}(...$arguments);
    }

    public function database(): dbxDB
    {
        return $this->database;
    }


    /* =====================================================
     * CONFIG
     * ===================================================== */

    /**
     * Setzt die maximale Laufzeit eines technischen Prozess-Schritts.
     *
     * @param float $seconds Maximale Schritt-Laufzeit in Sekunden
     *
     * @return void
     */
    public function set_step_runtime(float $seconds): void
    {
        if ($seconds > 0) {
            $this->_max_step_runtime = $seconds;
        }
    }

    /**
     * Gibt die maximale Laufzeit eines technischen Prozess-Schritts zurück.
     *
     * @return float Maximale Schritt-Laufzeit in Sekunden
     */
    public function get_step_runtime(): float
    {
        return $this->_max_step_runtime;
    }

    /**
     * Setzt die Standard-Chunk-Größe für technische Prozesse.
     *
     * @param int $chunk_size Chunk-Größe
     *
     * @return void
     */
    public function set_chunk_size(int $chunk_size): void
    {
        if ($chunk_size > 0) {
            $this->_chunk_size = $chunk_size;
        }
    }

    /**
     * Gibt die Standard-Chunk-Größe für technische Prozesse zurück.
     *
     * @return int Chunk-Größe
     */
    public function get_chunk_size(): int
    {
        return $this->_chunk_size;
    }









    /* =====================================================
     * DD -> DB SYNC
     * ===================================================== */

    /**
     * Prüft, ob zwei Typen semantisch kompatibel sind.
     *
     * Wichtig:
     * - dient nur als technische Infrastruktur
     * - fachliche Endentscheidung kann später durch Mapping/Admin kommen
     *
     * @param string $dd_type DD-Typ
     * @param string $db_type DB-/DD-Typ
     *
     * @return bool True wenn semantisch kompatibel
     */
    protected function is_semantic_type_match(string $dd_type, string $db_type): bool
    {
        $dd_group = $this->schema_type_group($dd_type);
        $db_group = $this->schema_type_group($db_type);

        if ($dd_group === $db_group) {
            return true;
        }

        if (in_array($dd_group, ['string', 'text'], true) && in_array($db_group, ['string', 'text'], true)) {
            return true;
        }

        if (in_array($dd_group, ['integer', 'bool'], true) && in_array($db_group, ['integer', 'bool'], true)) {
            return true;
        }

        return false;
    }




}
