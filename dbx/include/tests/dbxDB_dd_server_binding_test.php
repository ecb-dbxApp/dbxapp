<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
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
require_once $root . '/dbx/include/dbxDB.class.php';

class dbxDBBindingContractTest extends dbxDB
{
    /** Test bindings keyed by the complete DD reference. */
    public array $testBindings = array();

    protected function get_dd_server_bindings(): array
    {
        return $this->testBindings;
    }

    protected function is_valid_dd_server_binding(string $server): bool
    {
        return in_array($server, array(
            'mysqlUsers',
            'mysqlGroups',
            'dbxUser.db3',
            'dbx|dbxUser.db3',
        ), true);
    }
}

function binding_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
}

$db = new dbxDBBindingContractTest();
$db->testBindings = array(
    'dbx|dbxUser' => 'mysqlUsers',
    'dbx|dbxUser_groups' => 'dbxUser.db3',
);

$user = $db->get_dd_server_binding_info('dbx|dbxUser');
$groups = $db->get_dd_server_binding_info('dbx|dbxUser_groups');

binding_assert(
    $user['resolved_server'] === 'mysqlUsers'
        && $user['source'] === 'local-binding',
    'dbxUser wurde nicht auf den eigenen MySQL-Server gebunden.'
);
binding_assert(
    $groups['resolved_server'] === 'dbxUser.db3'
        && $groups['source'] === 'local-binding',
    'dbxUser_groups wurde nicht unabhaengig auf DB3 gebunden.'
);

$db->testBindings = array(
    'DBX|DBXUSER' => 'mysqlUsers',
);
binding_assert(
    $db->get_dd_server('dbx|dbxUser') === 'mysqlUsers',
    'DD-Schluessel werden nicht robust normalisiert.'
);
binding_assert(
    preg_match(
        '/dbxUser\.db3$/i',
        $db->get_dd_server('dbx|dbxUser_groups')
    ) === 1,
    'Eine nicht gebundene DD verwendet ihren DD-Standard nicht.'
);

echo "OK dbxDB per-DD server binding and mixed storage\n";
