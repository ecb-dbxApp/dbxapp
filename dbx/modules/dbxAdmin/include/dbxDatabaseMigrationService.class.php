<?php

declare(strict_types=1);

namespace dbx\dbxAdmin;

use RuntimeException;
use Throwable;

/**
 * Engine-unabhaengiger, DD-basierter Datenbank-Migrationsrunner.
 *
 * Migrationen duerfen Daten nur ueber das uebergebene dbxDB/dbxDD-Objekt
 * bearbeiten. Der Runner loest fuer jede DD ihre lokale Serverbindung auf,
 * sichert betroffene Tabellen und protokolliert jede Migrations-ID.
 */
class dbxDatabaseMigrationService
{
    public const LEDGER_DD = 'dbxAdmin|dbxMigration';

    private object $db;
    private object $dd;

    public function __construct(?object $db = null, ?object $dd = null)
    {
        $this->db = $db ?? dbx()->get_system_obj('dbxDB');
        $this->dd = $dd ?? dbx()->get_system_obj('dbxDD');
        if (!is_object($this->db) || !is_object($this->dd)) {
            throw new RuntimeException('dbxDB/dbxDD stehen fuer Migrationen nicht zur Verfuegung.');
        }
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function discover(string $releaseRoot, string $targetVersion = ''): array
    {
        $root = realpath($releaseRoot);
        if ($root === false || !is_dir($root)) {
            throw new RuntimeException('Releasewurzel fuer DB-Migrationen ist ungueltig.');
        }

        $migrations = array();
        $pattern = rtrim($root, '\\/') . '/dbx/modules/*/migrations/*.migration.php';
        foreach (glob($pattern) ?: array() as $file) {
            $record = (static function (string $migrationFile): mixed {
                return include $migrationFile;
            })($file);
            if (!is_array($record)) {
                throw new RuntimeException('Migration liefert keine Definition: ' . basename($file));
            }

            $id = trim((string)($record['id'] ?? ''));
            $version = trim((string)($record['version'] ?? ''));
            if ($id === ''
                || preg_match('/^[A-Za-z0-9._-]{1,128}$/', $id) !== 1
                || $version === ''
                || preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][0-9A-Za-z.-]+)?$/', $version) !== 1
            ) {
                throw new RuntimeException('Migration besitzt ID oder Version im ungueltigen Format: ' . basename($file));
            }
            if ($targetVersion !== '' && version_compare($version, $targetVersion, '>')) {
                continue;
            }
            if (isset($migrations[$id])) {
                throw new RuntimeException('Doppelte Migrations-ID: ' . $id);
            }

            $operations = is_array($record['operations'] ?? null)
                ? $record['operations']
                : array();
            $affected = is_array($record['affected_dd'] ?? null)
                ? $record['affected_dd']
                : array();
            foreach ($operations as $operation) {
                if (is_array($operation)
                    && ($operation['type'] ?? '') === 'sync_dd'
                    && trim((string)($operation['dd'] ?? '')) !== ''
                ) {
                    $affected[] = trim((string)$operation['dd']);
                }
            }

            $record['id'] = $id;
            $record['version'] = $version;
            $record['module'] = $this->moduleFromMigrationFile($file);
            $record['file'] = $file;
            $record['checksum'] = hash_file('sha256', $file);
            $record['operations'] = $operations;
            $record['affected_dd'] = array_values(array_unique(array_filter(
                array_map('strval', $affected)
            )));
            $migrations[$id] = $record;
        }

        uasort($migrations, static function (array $left, array $right): int {
            return version_compare((string)$left['version'], (string)$right['version'])
                ?: strcmp((string)$left['id'], (string)$right['id']);
        });
        return $migrations;
    }

    private function moduleFromMigrationFile(string $file): string
    {
        $normalized = str_replace('\\', '/', $file);
        return preg_match('#/dbx/modules/([^/]+)/migrations/#', $normalized, $match) === 1
            ? (string)$match[1]
            : '';
    }

    private function ledgerExists(): bool
    {
        $server = (string)$this->db->get_dd_server(self::LEDGER_DD);
        $table = (string)$this->db->get_dd_table(self::LEDGER_DD);
        return $server !== ''
            && $table !== ''
            && (bool)$this->dd->get_table_exist($server, $table);
    }

    private function ledgerRecord(string $id): array
    {
        if (!$this->ledgerExists()) {
            return array();
        }
        $record = $this->db->select1(
            self::LEDGER_DD,
            array('migration_id' => $id),
            '*',
            0
        );
        return is_array($record) ? $record : array();
    }

    /**
     * @return array{migrations:array,pending:array,applied:array}
     */
    public function plan(string $releaseRoot, string $targetVersion): array
    {
        $migrations = $this->discover($releaseRoot, $targetVersion);
        $pending = array();
        $applied = array();

        foreach ($migrations as $id => $migration) {
            $ledger = $this->ledgerRecord($id);
            if (($ledger['status'] ?? '') === 'finished') {
                if (!hash_equals(
                    strtolower((string)($ledger['checksum'] ?? '')),
                    strtolower((string)$migration['checksum'])
                )) {
                    throw new RuntimeException('Bereits ausgefuehrte Migration wurde veraendert: ' . $id);
                }
                $applied[] = $id;
                continue;
            }
            $pending[] = $id;
        }

        return array(
            'migrations' => $migrations,
            'pending' => $pending,
            'applied' => $applied,
        );
    }

    /**
     * Erstellt konsistente Tabellenbackups fuer alle ausstehenden Migrationen.
     *
     * @return array<string,mixed>
     */
    public function prepare(
        string $releaseRoot,
        string $targetVersion,
        string $backupDirectory
    ): array {
        $plan = $this->plan($releaseRoot, $targetVersion);
        $root = realpath($releaseRoot);
        if ($root === false) {
            throw new RuntimeException('Releasewurzel ist nicht mehr vorhanden.');
        }
        if ($plan['pending'] === array()) {
            return array(
                'schema' => 1,
                'release_root' => $root,
                'target_version' => $targetVersion,
                'pending' => array(),
                'checksums' => array(),
                'backups' => array(),
            );
        }

        $dbBackupDir = rtrim($backupDirectory, '\\/')
            . DIRECTORY_SEPARATOR . 'database';
        if (!is_dir($dbBackupDir)
            && !mkdir($dbBackupDir, 0700, true)
            && !is_dir($dbBackupDir)
        ) {
            throw new RuntimeException('DB-Backupverzeichnis konnte nicht erstellt werden.');
        }

        $checksums = array();
        $affected = array();
        foreach ($plan['pending'] as $id) {
            $migration = $plan['migrations'][$id];
            $checksums[$id] = (string)$migration['checksum'];
            foreach ($migration['affected_dd'] as $ddRef) {
                $affected[$ddRef] = true;
            }
        }

        $backupsByServer = array();
        $backups = array();
        foreach (array_keys($affected) as $ddRef) {
            $binding = $this->db->get_dd_server_binding_info($ddRef);
            if (($binding['source'] ?? '') === 'missing-dd') {
                $backups[] = array(
                    'dd' => $ddRef,
                    'existed' => false,
                    'server' => '',
                    'table' => '',
                    'file' => '',
                );
                continue;
            }
            if (empty($binding['valid']) || ($binding['resolved_server'] ?? '') === '') {
                throw new RuntimeException('Ungueltige Serverbindung fuer Migration: ' . $ddRef);
            }

            $this->assertStagedServerCompatible($root, $ddRef, $binding);
            $server = (string)$binding['resolved_server'];
            $table = (string)$this->db->get_dd_table($ddRef);
            $entry = array(
                'dd' => $ddRef,
                'existed' => false,
                'server' => $server,
                'table' => $table,
                'file' => '',
                'fields' => array(),
                'indexes' => array(),
            );
            if ($table === '' || !$this->dd->get_table_exist($server, $table)) {
                $backups[] = $entry;
                continue;
            }

            $entry['existed'] = true;
            $entry['fields'] = $this->dd->get_db_fields($server, $table);
            $entry['indexes'] = $this->dd->get_db_indexes($server, $table);
            $entry['file'] = $dbBackupDir . DIRECTORY_SEPARATOR
                . preg_replace('/[^A-Za-z0-9._-]+/', '-', $ddRef) . '.ddb.zip';
            $backups[] = $entry;
            $backupsByServer[$server][] = count($backups) - 1;
        }

        foreach ($backupsByServer as $indexes) {
            $representative = $backups[$indexes[0]]['dd'];
            if ($this->db->begin($representative) !== 1) {
                throw new RuntimeException('DB-Backuptransaktion konnte nicht gestartet werden: ' . $representative);
            }
            try {
                foreach ($indexes as $index) {
                    $entry = $backups[$index];
                    $state = array();
                    for ($step = 0; $step < 100000; $step++) {
                        $state = $this->dd->backup(
                            $entry['server'],
                            $entry['table'],
                            $entry['file'],
                            1
                        );
                        if (in_array(
                            (string)($state['status'] ?? ''),
                            array('finished', 'error', 'cancelled'),
                            true
                        )) {
                            break;
                        }
                    }
                    if (($state['status'] ?? '') !== 'finished') {
                        throw new RuntimeException('Tabellenbackup fehlgeschlagen: ' . $entry['dd']);
                    }
                }
                if ($this->db->commit($representative) !== 1) {
                    throw new RuntimeException('DB-Backuptransaktion konnte nicht abgeschlossen werden.');
                }
            } catch (Throwable $exception) {
                $this->db->rollback($representative);
                throw $exception;
            }
        }

        return array(
            'schema' => 1,
            'release_root' => $root,
            'target_version' => $targetVersion,
            'pending' => $plan['pending'],
            'checksums' => $checksums,
            'backups' => $backups,
        );
    }

    /**
     * Ein neuer DD-Default darf ein vorhandenes physisches Ziel nicht
     * unbemerkt wechseln. Eine lokale Bindung macht die Zielwahl explizit.
     */
    private function assertStagedServerCompatible(
        string $releaseRoot,
        string $ddRef,
        array $binding
    ): void {
        if (($binding['source'] ?? '') === 'local-binding') {
            return;
        }
        $parts = explode('|', $ddRef, 2);
        if (count($parts) !== 2) {
            return;
        }
        $file = rtrim($releaseRoot, '\\/')
            . '/dbx/modules/' . $parts[0] . '/dd/' . $parts[1] . '.dd.php';
        if (!is_file($file)) {
            return;
        }

        $stagedTable = (static function (string $ddFile): array {
            $table = array();
            $fields = array();
            $indexes = array();
            include $ddFile;
            return is_array($table) ? $table : array();
        })($file);
        $stagedServer = trim((string)($stagedTable['server'] ?? ''));
        $declared = trim((string)($binding['declared_server'] ?? ''));
        $module = trim((string)$parts[0]);
        if ($stagedServer !== ''
            && $declared !== ''
            && $this->canonicalServerReference($stagedServer, $module)
                !== $this->canonicalServerReference($declared, $module)
        ) {
            throw new RuntimeException(
                'DD-Serverwechsel benoetigt eine explizite lokale Bindung: '
                . $ddRef . ' (' . $declared . ' -> ' . $stagedServer . ')'
            );
        }
    }

    /**
     * SQLite-Server ohne Modulpräfix sind relativ zum DD-Modul. Der laufende
     * DD-Loader ergänzt dieses Präfix, sobald die lokale DB-Datei existiert;
     * im DB-freien Release-Staging bleibt dagegen der kurze Name erhalten.
     * Beide Schreibweisen bezeichnen dasselbe physische Ziel.
     */
    private function canonicalServerReference(string $server, string $module): string
    {
        $server = trim($server);
        if ($server === '' || $module === '') {
            return $server;
        }
        if (strpos($server, '|') !== false) {
            return $server;
        }
        if (preg_match('/\.(?:db3|sqlite|sqlite3)$/i', $server)) {
            return $module . '|' . $server;
        }
        return $server;
    }

    public function apply(array $state): array
    {
        $releaseRoot = (string)($state['release_root'] ?? '');
        $targetVersion = (string)($state['target_version'] ?? '');
        $pending = is_array($state['pending'] ?? null) ? $state['pending'] : array();
        if ($pending === array()) {
            return array('applied' => array());
        }

        $migrations = $this->discover($releaseRoot, $targetVersion);
        if (!$this->dd->create_db_tab(self::LEDGER_DD)) {
            throw new RuntimeException('Migration-Ledger konnte nicht erstellt werden.');
        }

        $applied = array();
        foreach ($pending as $id) {
            if (!isset($migrations[$id])) {
                throw new RuntimeException('Vorbereitete Migration fehlt: ' . $id);
            }
            $migration = $migrations[$id];
            $expected = strtolower((string)($state['checksums'][$id] ?? ''));
            if ($expected === ''
                || !hash_equals($expected, strtolower((string)$migration['checksum']))
            ) {
                throw new RuntimeException('Migration wurde nach der Vorbereitung veraendert: ' . $id);
            }

            $this->writeLedger($migration, 'running', '', $state);
            try {
                $this->executeMigration($migration);
                $this->writeLedger($migration, 'finished', '', $state);
                $applied[] = $id;
            } catch (Throwable $exception) {
                $this->writeLedger($migration, 'error', $exception->getMessage(), $state);
                throw new RuntimeException(
                    'DB-Migration fehlgeschlagen (' . $id . '): ' . $exception->getMessage(),
                    0,
                    $exception
                );
            }
        }

        return array('applied' => $applied);
    }

    private function executeMigration(array $migration): void
    {
        foreach ($migration['operations'] as $operation) {
            if (!is_array($operation)) {
                throw new RuntimeException('Ungueltige Migrationsoperation.');
            }
            $type = (string)($operation['type'] ?? '');
            if ($type === 'sync_dd') {
                $ddRef = trim((string)($operation['dd'] ?? ''));
                $syncMode = strtolower(trim((string)(
                    $operation['mode'] ?? 'apply'
                )));
                $parts = explode('|', $ddRef, 2);
                if (count($parts) !== 2) {
                    throw new RuntimeException('sync_dd benoetigt modul|dd.');
                }
                if (!in_array($syncMode, array('apply', 'rebuild'), true)) {
                    throw new RuntimeException(
                        'sync_dd erlaubt nur apply oder rebuild: ' . $ddRef
                    );
                }
                $this->dd->sync_dd_to_db($parts[0], $parts[1], 'reset');
                $sync = array();
                for ($step = 0; $step < 1000; $step++) {
                    $sync = $this->dd->sync_dd_to_db(
                        $parts[0],
                        $parts[1],
                        $syncMode
                    );
                    if (in_array(
                        (string)($sync['status'] ?? ''),
                        array('finished', 'error', 'cancelled'),
                        true
                    )) {
                        break;
                    }
                }
                if (($sync['status'] ?? '') !== 'finished') {
                    throw new RuntimeException(
                        $ddRef . ': ' . (string)($sync['message'] ?? 'DD-Sync fehlgeschlagen')
                    );
                }
                continue;
            }
            if ($type === 'seed_core') {
                dbx()->get_include_obj('dbxInstallationService', 'dbxSetup');
                $installerClass = '\\dbx\\dbxSetup\\dbxInstallationService';
                $installer = new $installerClass($this->db, $this->dd);
                $installer->seedCoreGroups();
                continue;
            }
            throw new RuntimeException('Nicht erlaubter Migrationstyp: ' . $type);
        }

        if (isset($migration['up'])) {
            if (!is_callable($migration['up'])) {
                throw new RuntimeException('Migration-up ist nicht aufrufbar.');
            }
            ($migration['up'])($this->db, $this->dd);
        }
    }

    private function writeLedger(
        array $migration,
        string $status,
        string $error,
        array $state
    ): void {
        $existing = $this->ledgerRecord((string)$migration['id']);
        $values = array(
            'migration_id' => (string)$migration['id'],
            'release_version' => (string)$migration['version'],
            'module' => (string)$migration['module'],
            'checksum' => (string)$migration['checksum'],
            'status' => $status,
            'phase' => 'apply',
            'affected_servers' => json_encode(
                array_values(array_unique(array_map(
                    static fn(array $entry): string => (string)($entry['server'] ?? ''),
                    is_array($state['backups'] ?? null) ? $state['backups'] : array()
                ))),
                JSON_UNESCAPED_SLASHES
            ),
            'backup_reference' => json_encode(
                array_values(array_filter(array_map(
                    static fn(array $entry): string => (string)($entry['file'] ?? ''),
                    is_array($state['backups'] ?? null) ? $state['backups'] : array()
                ))),
                JSON_UNESCAPED_SLASHES
            ),
            'error_text' => $error,
        );
        if ($status === 'running') {
            $values['started_at'] = date('Y-m-d H:i:s.u');
            $values['finished_at'] = '';
        } else {
            $values['finished_at'] = date('Y-m-d H:i:s.u');
        }

        if ((int)($existing['id'] ?? 0) > 0) {
            $ok = $this->db->update(
                self::LEDGER_DD,
                $values,
                (int)$existing['id'],
                0,
                1,
                1,
                0
            );
        } else {
            $ok = $this->db->insert(
                self::LEDGER_DD,
                $values,
                0,
                1,
                1,
                0
            );
        }
        if ((int)$ok < 0 || $ok === false) {
            throw new RuntimeException('Migration-Ledger konnte nicht geschrieben werden.');
        }
    }

    /**
     * Stellt Schema und Daten aller vorbereiteten Tabellen wieder her.
     */
    public function rollback(array $state): array
    {
        $backups = is_array($state['backups'] ?? null) ? $state['backups'] : array();
        foreach (array_reverse($backups) as $entry) {
            $ddRef = (string)($entry['dd'] ?? '');
            $server = (string)($entry['server'] ?? '');
            $table = (string)($entry['table'] ?? '');

            if ($server === '' || $table === '') {
                if ($ddRef !== '') {
                    $server = (string)$this->db->get_dd_server($ddRef);
                    $table = (string)$this->db->get_dd_table($ddRef);
                }
            }
            if ($server === '' || $table === '') {
                continue;
            }

            if (empty($entry['existed'])) {
                $this->dd->drop_db_tab($server, $table);
                continue;
            }

            $this->dd->drop_db_tab($server, $table);
            if (!$this->dd->create_db_tab_from_fields(
                $server,
                $table,
                is_array($entry['fields'] ?? null) ? $entry['fields'] : array(),
                is_array($entry['indexes'] ?? null) ? $entry['indexes'] : array()
            )) {
                throw new RuntimeException('Rollback-Schema konnte nicht erstellt werden: ' . $ddRef);
            }

            $restore = array();
            for ($step = 0; $step < 100000; $step++) {
                $restore = $this->dd->restore(
                    $server,
                    $table,
                    (string)$entry['file'],
                    array(),
                    1
                );
                if (in_array(
                    (string)($restore['status'] ?? ''),
                    array('finished', 'error', 'cancelled'),
                    true
                )) {
                    break;
                }
            }
            if (($restore['status'] ?? '') !== 'finished') {
                throw new RuntimeException('Rollback-Daten konnten nicht wiederhergestellt werden: ' . $ddRef);
            }
        }

        if ($this->ledgerExists()) {
            foreach ((array)($state['pending'] ?? array()) as $id) {
                $record = $this->ledgerRecord((string)$id);
                if ((int)($record['id'] ?? 0) > 0) {
                    $this->db->update(
                        self::LEDGER_DD,
                        array(
                            'status' => 'rolled_back',
                            'finished_at' => date('Y-m-d H:i:s.u'),
                        ),
                        (int)$record['id'],
                        0,
                        1,
                        1,
                        0
                    );
                }
            }
        }

        return array('rolled_back' => array_values((array)($state['pending'] ?? array())));
    }
}
