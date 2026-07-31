<?php

declare(strict_types=1);

$root = dirname(__DIR__, 4);
chdir($root);
$_SERVER['REQUEST_URI'] = '/dbxapp/';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SCRIPT_NAME'] = '/dbxapp/index.php';

if (!defined('dbxSystem')) {
    define('dbxSystem', 'dbxWebApp');
}
if (!defined('dbxRunAsAdmin')) {
    define('dbxRunAsAdmin', 1);
}

require_once $root . '/dbx/vendor/autoload.php';
require_once $root . '/dbx/include/dbxKernel.php';
require_once dirname(__DIR__) . '/include/dbxDatabaseMigrationService.class.php';

use dbx\dbxAdmin\dbxDatabaseMigrationService;

final class MigrationDbStub
{
    public array $rows = array();
    public array $writes = array();
    private int $nextId = 1;

    public function get_dd_server(string $dd): string
    {
        return $dd === dbxDatabaseMigrationService::LEDGER_DD
            ? 'dbxAdmin.db3'
            : 'dbxTest.db3';
    }

    public function get_dd_table(string $dd): string
    {
        return match ($dd) {
            dbxDatabaseMigrationService::LEDGER_DD => 'dbx_migration',
            'dbx|dbxUser' => 'dbx_user',
            'dbx|dbxUser_groups' => 'dbx_user_groups',
            default => '',
        };
    }

    public function get_dd_server_binding_info(string $dd): array
    {
        return array(
            'source' => 'local-binding',
            'valid' => true,
            'resolved_server' => $dd === 'dbx|dbxUser'
                ? 'mysqlUsers'
                : 'dbxUser.db3',
            'declared_server' => 'dbxUser.db3',
        );
    }

    public function select1(string $dd, array $where, mixed $columns, int $access): array
    {
        foreach ($this->rows[$dd] ?? array() as $row) {
            $matches = true;
            foreach ($where as $name => $value) {
                if (($row[$name] ?? null) !== $value) {
                    $matches = false;
                }
            }
            if ($matches) {
                return $row;
            }
        }
        return array();
    }

    public function insert(
        string $dd,
        array $values,
        int $access,
        int $fields,
        int $valueCheck,
        int $trace
    ): int {
        $values['id'] = $this->nextId++;
        $this->rows[$dd][] = $values;
        $this->writes[] = array('mode' => 'insert', 'dd' => $dd, 'values' => $values);
        return (int)$values['id'];
    }

    public function update(
        string $dd,
        array $values,
        int $id,
        int $access,
        int $fields,
        int $valueCheck,
        int $trace
    ): int {
        if (!isset($this->rows[$dd])) {
            return 0;
        }
        foreach ($this->rows[$dd] as &$row) {
            if ((int)($row['id'] ?? 0) === $id) {
                $row = array_replace($row, $values);
                break;
            }
        }
        unset($row);
        $this->writes[] = array('mode' => 'update', 'dd' => $dd, 'values' => $values);
        return 1;
    }

    public function begin(string $dd): int
    {
        return 1;
    }

    public function commit(string $dd): int
    {
        return 1;
    }

    public function rollback(string $dd): int
    {
        return 1;
    }
}

final class MigrationDdStub
{
    public bool $ledgerCreated = false;
    public array $dropped = array();

    public function get_table_exist(string $server, string $table): bool
    {
        return $table === 'dbx_migration' && $this->ledgerCreated;
    }

    public function create_db_tab(string $dd): int
    {
        $this->ledgerCreated = true;
        return 1;
    }

    public function sync_dd_to_db(string $module, string $dd, string $mode): array
    {
        return array(
            'status' => $mode === 'reset' ? 'reset' : 'finished',
            'message' => 'ok',
        );
    }

    public function drop_db_tab(string $server, string $table): int
    {
        $this->dropped[] = $server . '|' . $table;
        return 1;
    }

    public function get_db_fields(string $server, string $table): array
    {
        return array();
    }

    public function get_db_indexes(string $server, string $table): array
    {
        return array();
    }
}

function migration_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$db = new MigrationDbStub();
$dd = new MigrationDdStub();
$service = new dbxDatabaseMigrationService($db, $dd);
$backup = sys_get_temp_dir() . '/dbx-migration-test-' . bin2hex(random_bytes(4));

try {
    $discovered = $service->discover($root, '4.0.3');
    migration_assert(
        isset($discovered['core-4.0.3-user-identity']),
        'Core-Migration wurde nicht entdeckt.'
    );

    $state = $service->prepare($root, '4.0.3', $backup);
    migration_assert(
        $state['pending'] === array('core-4.0.3-user-identity'),
        'Ausstehende Migration wurde nicht geplant.'
    );
    $servers = array_column($state['backups'], 'server', 'dd');
    migration_assert(
        ($servers['dbx|dbxUser'] ?? '') === 'mysqlUsers'
            && ($servers['dbx|dbxUser_groups'] ?? '') === 'dbxUser.db3',
        'Gemischte DD-Serverbindungen wurden im Migrationsplan nicht erhalten.'
    );

    $applied = $service->apply($state);
    migration_assert(
        $applied['applied'] === array('core-4.0.3-user-identity'),
        'Migration wurde nicht ausgefuehrt.'
    );
    $ledger = $db->select1(
        dbxDatabaseMigrationService::LEDGER_DD,
        array('migration_id' => 'core-4.0.3-user-identity'),
        '*',
        0
    );
    migration_assert(
        ($ledger['status'] ?? '') === 'finished',
        'Migration wurde nicht als abgeschlossen protokolliert.'
    );

    $groups = $db->rows['dbx|dbxUser_groups'] ?? array();
    migration_assert(
        array_column($groups, 'name') === array('guest', 'member', 'admin'),
        'Core-Seeds wurden durch die Migration nicht angelegt.'
    );

    $service->rollback($state);
    migration_assert(
        in_array('mysqlUsers|dbx_user', $dd->dropped, true)
            && in_array('dbxUser.db3|dbx_user_groups', $dd->dropped, true),
        'Rollback behandelt die gemischten Server nicht einzeln.'
    );

    echo "OK migration discovery, checksum ledger, mixed bindings and rollback\n";
} finally {
    if (is_dir($backup)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($backup, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($backup);
    }
}
