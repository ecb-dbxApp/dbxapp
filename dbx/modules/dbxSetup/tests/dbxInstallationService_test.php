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
require_once dirname(__DIR__) . '/include/dbxInstallationService.class.php';

use dbx\dbxSetup\dbxInstallationService;

final class InstallationDbStub
{
    public array $rows = array();
    public array $inserted = array();
    public array $updated = array();
    private int $next_id = 1;

    public function get_dd_server_binding_info(string $dd): array
    {
        return array(
            'valid' => true,
            'resolved_server' => str_ends_with($dd, 'dbxUser')
                ? 'mysqlUsers'
                : 'dbxTest.db3',
        );
    }

    public function select1(
        string $dd,
        array $where,
        array $columns,
        int $verify_access
    ): array {
        foreach ($this->rows[$dd] ?? array() as $row) {
            $match = true;
            foreach ($where as $name => $value) {
                if (($row[$name] ?? null) !== $value) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                return $row;
            }
        }
        return array();
    }

    public function insert(
        string $dd,
        array $values,
        int $verify_access,
        int $verify_fields,
        int $verify_values,
        int $trace
    ): int {
        $values['id'] = $this->next_id++;
        $this->rows[$dd][] = $values;
        $this->inserted[] = array('dd' => $dd, 'values' => $values);
        return (int)$values['id'];
    }

    public function update(
        string $dd,
        array $values,
        int $id,
        int $verify_access,
        int $verify_fields,
        int $verify_values,
        int $trace
    ): int {
        foreach ($this->rows[$dd] ?? array() as $index => $row) {
            if ((int)($row['id'] ?? 0) !== $id) {
                continue;
            }
            $this->rows[$dd][$index] = array_replace($row, $values);
            $this->updated[] = array(
                'dd' => $dd,
                'id' => $id,
                'values' => $values,
            );
            return 1;
        }
        return 0;
    }
}

final class InstallationDdStub
{
    public array $calls = array();
    public array $transfers = array();
    public bool $all_tables_exist = false;

    public function sync_dd_to_db(string $module, string $dd, string $mode): array
    {
        $this->calls[] = array($module, $dd, $mode);
        if ($mode === 'reset') {
            return array('status' => 'reset');
        }
        return array('status' => 'finished', 'message' => 'ok');
    }

    public function get_table_exist(string $server, string $table): bool
    {
        return $this->all_tables_exist || $table === 'dbx_user';
    }

    public function transfer_table(
        string $source_server,
        string $source_table,
        string $target_server,
        string $target_table,
        string $mode,
        int $create_target,
        int $truncate_target
    ): array {
        $this->transfers[] = array(
            $source_server,
            $source_table,
            $target_server,
            $target_table,
            $mode,
            $create_target,
            $truncate_target,
        );
        return array(
            'status' => $mode === 'reset' ? 'reset' : 'finished',
            'message' => 'ok',
        );
    }
}

function installation_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$db = new InstallationDbStub();
$dd = new InstallationDdStub();
$service = new dbxInstallationService($db, $dd);

$catalog = $service->discover_dds(array('dbx'));
installation_assert(count($catalog) >= 10, 'Core-DD-Katalog ist unvollstaendig.');
installation_assert(
    in_array('dbx|dbxUser', array_column($catalog, 'dd'), true),
    'dbxUser fehlt im Installationskatalog.'
);
$user_definition = array_values(array_filter(
    $catalog,
    static fn(array $record): bool => $record['dd'] === 'dbx|dbxUser'
))[0] ?? array();
installation_assert(
    ($user_definition['declared_server'] ?? '') === 'dbx|dbxUser.db3'
        && ($user_definition['table'] ?? '') === 'dbx_user',
    'Deklarierter DD-Quellspeicher wird nicht erkannt.'
);

$schema = $service->provision_schema(array('dbx'));
installation_assert($schema['ok'] === true, 'DD-Provisionierung meldet einen Fehler.');
installation_assert(
    $schema['finished'] === $schema['total'],
    'Nicht alle DDs wurden provisioniert.'
);

$dd->all_tables_exist = true;
$verification = $service->verify_bundled_schema(array('dbx'));
installation_assert(
    $verification['ok'] === true
        && $verification['verified'] === $verification['total']
        && count($dd->calls) === $schema['total'] * 2,
    'Lesende DB3-Prüfung darf keine zusätzliche Schema-Synchronisierung auslösen.'
);
$dd->all_tables_exist = false;

$transfer = $service->transfer_declared_data_to_server('dbxApp', array('dbx'));
installation_assert(
    $transfer['ok'] === true
        && $transfer['transferred'] === 1
        && count($dd->transfers) === 2
        && $dd->transfers[0][4] === 'reset'
        && $dd->transfers[1][4] === 'step',
    'Optionale DD-Datenübertragung ist nicht deterministisch.'
);

$groups = $service->seed_core_groups();
installation_assert(
    $groups['created'] === array('guest', 'member', 'admin'),
    'Core-Gruppen wurden nicht deterministisch angelegt.'
);
$groups_again = $service->seed_core_groups();
installation_assert(
    $groups_again['created'] === array()
        && $groups_again['existing'] === array('guest', 'member', 'admin'),
    'Core-Seed ist nicht idempotent.'
);

$admin = $service->create_admin(
    'Test-Passwort-2026!',
    'admin@example.test',
    'de'
);
installation_assert($admin['created'] === true, 'Admin wurde nicht angelegt.');
$admin_rows = array_values(array_filter(
    $db->inserted,
    static fn(array $entry): bool => $entry['dd'] === 'dbx|dbxUser'
));
installation_assert(count($admin_rows) === 1, 'Admin wurde mehrfach geschrieben.');
installation_assert(
    password_verify(
        'Test-Passwort-2026!',
        (string)$admin_rows[0]['values']['pass']
    ),
    'Admin-Passwort wurde nicht mit password_hash gespeichert.'
);

$admin_again = $service->create_admin(
    'Anderes-Passwort-2026!',
    'other@example.test',
    'en'
);
installation_assert(
    $admin_again['created'] === false
        && count(array_filter(
            $db->inserted,
            static fn(array $entry): bool => $entry['dd'] === 'dbx|dbxUser'
        )) === 1,
    'Vorhandener Admin wurde bei erneutem Seed veraendert.'
);

$initial_db = new InstallationDbStub();
$initial_dd = new InstallationDdStub();
$initial_service = new dbxInstallationService($initial_db, $initial_dd);
$missing_admin = $initial_service->ensure_initial_admin(
    false,
    'de',
    'Admin-2026!'
);
installation_assert(
    $missing_admin['exists'] === false
        && $missing_admin['created'] === false
        && $missing_admin['reset'] === false
        && count($initial_db->inserted) === 0,
    'Ohne ausdrückliches Sicherstellen darf ein fehlender Admin nicht geschrieben werden.'
);
$initial_admin = $initial_service->ensure_initial_admin(
    true,
    'de',
    'Admin-2026!'
);
$initial_rows = array_values(array_filter(
    $initial_db->inserted,
    static fn(array $entry): bool => $entry['dd'] === 'dbx|dbxUser'
));
installation_assert(
    $initial_admin['exists'] === true
        && $initial_admin['created'] === true
        && $initial_admin['reset'] === true
        && $initial_admin['default_password'] === false
        && $initial_admin['password_reset_required'] === false
        && count($initial_rows) === 1
        && password_verify('Admin-2026!', (string)$initial_rows[0]['values']['pass']),
    'Ein leeres SQL-Ziel benötigt den in der Installation gewählten Administratorzugang.'
);

$existing_db = new InstallationDbStub();
$existing_db->rows['dbx|dbxUser'][] = array(
    'id' => 17,
    'uname' => 'admin',
    'pass' => password_hash('Individuell-2026!', PASSWORD_DEFAULT),
    'email' => 'existing@example.test',
    'roles' => 'member',
    'language' => 'de',
    'status' => 0,
    'is_confirm' => 0,
    'settings' => json_encode(array(
        'dashboard_layout' => 'compact',
        'password_changed_at' => '2026-01-01T00:00:00+00:00',
    )),
);
$existing_service = new dbxInstallationService(
    $existing_db,
    new InstallationDdStub()
);
$reset_admin = $existing_service->ensure_initial_admin(
    true,
    'de',
    'Neues-Admin-2026!'
);
$reset_row = $existing_db->rows['dbx|dbxUser'][0];
$reset_settings = json_decode((string)$reset_row['settings'], true);
installation_assert(
    $reset_admin['exists'] === true
        && $reset_admin['created'] === false
        && $reset_admin['reset'] === true
        && $reset_admin['default_password'] === false
        && $reset_admin['password_reset_required'] === false
        && password_verify('Neues-Admin-2026!', (string)$reset_row['pass'])
        && $reset_row['email'] === 'existing@example.test'
        && $reset_row['roles'] === 'admin'
        && (int)$reset_row['status'] === 1
        && (int)$reset_row['is_confirm'] === 1
        && ($reset_settings['dashboard_layout'] ?? '') === 'compact'
        && !empty($reset_settings['password_changed_at'])
        && !isset($reset_settings['password_reset_required'])
        && count($existing_db->updated) === 1,
    'Ein vorhandener Admin muss das in der Installation gewählte persönliche Passwort erhalten.'
);

echo "OK DD installation catalog, schema provisioning and idempotent seeds\n";
