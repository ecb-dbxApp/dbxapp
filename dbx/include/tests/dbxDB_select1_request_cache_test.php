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

class dbxDBSelect1RequestCacheTestDouble extends dbxDB
{
    public int $select_calls = 0;

    public array $records = array(
        'test|probe' => array(
            1 => array('id' => 1, 'name' => 'eins'),
            2 => array('id' => 2, 'name' => 'zwei'),
        ),
        'test|other' => array(
            1 => array('id' => 1, 'name' => 'anders'),
        ),
    );

    public function load_dd(string $dd): array
    {
        $name = str_contains($dd, '|') ? substr($dd, strrpos($dd, '|') + 1) : $dd;
        return array(
            'dd_status' => 1,
            'dd_modul' => 'test',
            'dd_name' => strtolower($name),
        );
    }

    public function get_dd_server(string $dd): string
    {
        return 'test-server';
    }

    public function select(
        string $dd = '',
        $where = '',
        $columns = '*',
        $orderby = '',
        $asc_desc = 'ASC',
        $groupby = '',
        $max = 0,
        $offset = 0,
        $verify_access = 1
    ): array {
        $this->select_calls++;
        $definition = $this->load_dd($dd);
        $dd_key = strtolower($definition['dd_modul'] . '|' . $definition['dd_name']);
        $id = is_numeric($where) ? (int)$where : 0;
        $record = $this->records[$dd_key][$id] ?? null;
        return is_array($record) ? array($record) : array();
    }

    public function empty_record(string $dd): array
    {
        return array(array('id' => 0, 'name' => ''));
    }

    public function invalidateForTest(string $dd): void
    {
        $this->invalidate_select1_cache($dd);
    }
}

function select1_cache_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
}

$db = new dbxDBSelect1RequestCacheTestDouble();

$first = $db->select1('probe', 1);
$first['name'] = 'lokal veraendert';
$second = $db->select1('test|probe', 1);
select1_cache_assert($db->select_calls === 1, 'Derselbe DD-Einzelsatz wurde zweimal gelesen.');
select1_cache_assert(($second['name'] ?? '') === 'eins', 'Ein Rueckgabearray hat den Cacheinhalt veraendert.');

$db->select1('probe', 1, 'id');
$db->select1('probe', 1, '*', 0);
select1_cache_assert($db->select_calls === 3, 'Spalten- oder Rechtekontext fehlt im Cache-Schluessel.');

$missing1 = $db->select1('probe', 999);
$missing2 = $db->select1('probe', 999);
select1_cache_assert($db->select_calls === 4, 'Ein leerer Einzelsatz wird nicht requestlokal gecacht.');
select1_cache_assert($missing1 === $missing2 && (int)($missing2['id'] ?? -1) === 0, 'Leerdatensatz ist instabil.');

$db->select1('other', 1);
$db->invalidateForTest('probe');
$db->select1('probe', 1);
$db->select1('other', 1);
select1_cache_assert($db->select_calls === 6, 'DD-Invalidierung ist zu breit oder nicht wirksam.');

$stats = $db->select1_cache_snapshot();
select1_cache_assert((int)$stats['hits'] === 3, 'Cache-Hits werden nicht korrekt erfasst.');
select1_cache_assert((int)$stats['invalidations'] === 1, 'Invalidierung wird nicht korrekt erfasst.');
select1_cache_assert((int)$stats['entries'] === 2, 'Eintragszahl nach Invalidierung ist falsch.');
select1_cache_assert((int)$stats['capacity'] === 1000, 'Cache-Obergrenze fehlt.');

$tx = new ReflectionProperty(dbxDB::class, '_tx');
$tx->setAccessible(true);
$tx->setValue($db, array('test-server' => true));
$calls_before_transaction = $db->select_calls;
$db->select1('probe', 1);
$db->select1('probe', 1);
select1_cache_assert(
    $db->select_calls === $calls_before_transaction + 2,
    'Eine aktive Transaktion verwendet den requestlokalen Cache.'
);
$tx->setValue($db, array());
$stats = $db->select1_cache_snapshot();
select1_cache_assert((int)$stats['transaction_bypass'] === 2, 'Transaktions-Bypass wird nicht erfasst.');

require_once __DIR__ . '/dbxModuleSourceBundle.php';
$source = dbx_test_module_source_bundle($root . '/dbx/include/dbxDB.class.php');
select1_cache_assert(
    substr_count($source, '$this->invalidate_select1_cache((string)$dd);') >= 3,
    'insert(), update() oder delete() invalidiert den DD-Cache nicht.'
);
select1_cache_assert(
    str_contains($source, "return \$server !== '' && empty(\$this->_tx[\$server]);"),
    'select1() umgeht den Cache innerhalb einer Transaktion nicht.'
);
select1_cache_assert(
    substr_count($source, '$this->invalidate_select1_cache_server($server);') >= 4,
    'Commit oder Rollback verwirft DD-Caches des Transaktionsservers nicht.'
);

echo "OK dbxDB cached select1 request-local and invalidates exactly one DD.\n";
