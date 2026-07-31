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
    private int $nextId = 1;

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
        int $verifyAccess
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
        int $verifyAccess,
        int $verifyFields,
        int $verifyValues,
        int $trace
    ): int {
        $values['id'] = $this->nextId++;
        $this->rows[$dd][] = $values;
        $this->inserted[] = array('dd' => $dd, 'values' => $values);
        return (int)$values['id'];
    }

    public function update(
        string $dd,
        array $values,
        int $id,
        int $verifyAccess,
        int $verifyFields,
        int $verifyValues,
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
    public bool $allTablesExist = false;

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
        return $this->allTablesExist || $table === 'dbx_user';
    }

    public function transfer_table(
        string $sourceServer,
        string $sourceTable,
        string $targetServer,
        string $targetTable,
        string $mode,
        int $createTarget,
        int $truncateTarget
    ): array {
        $this->transfers[] = array(
            $sourceServer,
            $sourceTable,
            $targetServer,
            $targetTable,
            $mode,
            $createTarget,
            $truncateTarget,
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

$catalog = $service->discoverDDs(array('dbx'));
installation_assert(count($catalog) >= 10, 'Core-DD-Katalog ist unvollstaendig.');
installation_assert(
    in_array('dbx|dbxUser', array_column($catalog, 'dd'), true),
    'dbxUser fehlt im Installationskatalog.'
);
$userDefinition = array_values(array_filter(
    $catalog,
    static fn(array $record): bool => $record['dd'] === 'dbx|dbxUser'
))[0] ?? array();
installation_assert(
    ($userDefinition['declared_server'] ?? '') === 'dbxUser.db3'
        && ($userDefinition['table'] ?? '') === 'dbx_user',
    'Deklarierter DD-Quellspeicher wird nicht erkannt.'
);

$schema = $service->provisionSchema(array('dbx'));
installation_assert($schema['ok'] === true, 'DD-Provisionierung meldet einen Fehler.');
installation_assert(
    $schema['finished'] === $schema['total'],
    'Nicht alle DDs wurden provisioniert.'
);

$dd->allTablesExist = true;
$verification = $service->verifyBundledSchema(array('dbx'));
installation_assert(
    $verification['ok'] === true
        && $verification['verified'] === $verification['total']
        && count($dd->calls) === $schema['total'] * 2,
    'Lesende DB3-Prüfung darf keine zusätzliche Schema-Synchronisierung auslösen.'
);
$dd->allTablesExist = false;

$transfer = $service->transferDeclaredDataToServer('dbxApp', array('dbx'));
installation_assert(
    $transfer['ok'] === true
        && $transfer['transferred'] === 1
        && count($dd->transfers) === 2
        && $dd->transfers[0][4] === 'reset'
        && $dd->transfers[1][4] === 'step',
    'Optionale DD-Datenübertragung ist nicht deterministisch.'
);

$groups = $service->seedCoreGroups();
installation_assert(
    $groups['created'] === array('guest', 'member', 'admin'),
    'Core-Gruppen wurden nicht deterministisch angelegt.'
);
$groupsAgain = $service->seedCoreGroups();
installation_assert(
    $groupsAgain['created'] === array()
        && $groupsAgain['existing'] === array('guest', 'member', 'admin'),
    'Core-Seed ist nicht idempotent.'
);

$admin = $service->createAdmin(
    'Test-Passwort-2026!',
    'admin@example.test',
    'de'
);
installation_assert($admin['created'] === true, 'Admin wurde nicht angelegt.');
$adminRows = array_values(array_filter(
    $db->inserted,
    static fn(array $entry): bool => $entry['dd'] === 'dbx|dbxUser'
));
installation_assert(count($adminRows) === 1, 'Admin wurde mehrfach geschrieben.');
installation_assert(
    password_verify(
        'Test-Passwort-2026!',
        (string)$adminRows[0]['values']['pass']
    ),
    'Admin-Passwort wurde nicht mit password_hash gespeichert.'
);

$adminAgain = $service->createAdmin(
    'Anderes-Passwort-2026!',
    'other@example.test',
    'en'
);
installation_assert(
    $adminAgain['created'] === false
        && count(array_filter(
            $db->inserted,
            static fn(array $entry): bool => $entry['dd'] === 'dbx|dbxUser'
        )) === 1,
    'Vorhandener Admin wurde bei erneutem Seed veraendert.'
);

$initialDb = new InstallationDbStub();
$initialDd = new InstallationDdStub();
$initialService = new dbxInstallationService($initialDb, $initialDd);
$missingAdmin = $initialService->ensureInitialAdmin(
    false,
    'de',
    'Admin-2026!'
);
installation_assert(
    $missingAdmin['exists'] === false
        && $missingAdmin['created'] === false
        && $missingAdmin['reset'] === false
        && count($initialDb->inserted) === 0,
    'Ohne ausdrückliches Sicherstellen darf ein fehlender Admin nicht geschrieben werden.'
);
$initialAdmin = $initialService->ensureInitialAdmin(
    true,
    'de',
    'Admin-2026!'
);
$initialRows = array_values(array_filter(
    $initialDb->inserted,
    static fn(array $entry): bool => $entry['dd'] === 'dbx|dbxUser'
));
installation_assert(
    $initialAdmin['exists'] === true
        && $initialAdmin['created'] === true
        && $initialAdmin['reset'] === true
        && $initialAdmin['default_password'] === false
        && $initialAdmin['password_reset_required'] === false
        && count($initialRows) === 1
        && password_verify('Admin-2026!', (string)$initialRows[0]['values']['pass']),
    'Ein leeres SQL-Ziel benötigt den in der Installation gewählten Administratorzugang.'
);

$existingDb = new InstallationDbStub();
$existingDb->rows['dbx|dbxUser'][] = array(
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
$existingService = new dbxInstallationService(
    $existingDb,
    new InstallationDdStub()
);
$resetAdmin = $existingService->ensureInitialAdmin(
    true,
    'de',
    'Neues-Admin-2026!'
);
$resetRow = $existingDb->rows['dbx|dbxUser'][0];
$resetSettings = json_decode((string)$resetRow['settings'], true);
installation_assert(
    $resetAdmin['exists'] === true
        && $resetAdmin['created'] === false
        && $resetAdmin['reset'] === true
        && $resetAdmin['default_password'] === false
        && $resetAdmin['password_reset_required'] === false
        && password_verify('Neues-Admin-2026!', (string)$resetRow['pass'])
        && $resetRow['email'] === 'existing@example.test'
        && $resetRow['roles'] === 'admin'
        && (int)$resetRow['status'] === 1
        && (int)$resetRow['is_confirm'] === 1
        && ($resetSettings['dashboard_layout'] ?? '') === 'compact'
        && !empty($resetSettings['password_changed_at'])
        && !isset($resetSettings['password_reset_required'])
        && count($existingDb->updated) === 1,
    'Ein vorhandener Admin muss das in der Installation gewählte persönliche Passwort erhalten.'
);

echo "OK DD installation catalog, schema provisioning and idempotent seeds\n";
