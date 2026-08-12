<?php
declare(strict_types=1);

/**
 * Bereitet den administrativen Zugang der Dokumentationsinstallation vor.
 *
 * Ausführung im Wurzelverzeichnis der Dokumentationsinstallation:
 * php dbx/modules/dbxDocs/tools/configure_docs_access_20260728.php
 *
 * Falls noch kein Admin existiert, müssen DBX_DOCS_ADMIN_PASSWORD und
 * DBX_DOCS_ADMIN_EMAIL in der lokalen Prozessumgebung gesetzt sein.
 * Passwörter werden weder als Argument angenommen noch ausgegeben.
 *
 * Benutzer und Gruppen werden ausschließlich über DD, dbxDD und dbxDB
 * provisioniert. Eine lokale DD-Serverbindung (DB3, MySQL oder gemischt)
 * bleibt dadurch vollständig wirksam.
 */

$base = dirname(__DIR__, 4);
chdir($base);
$_SERVER['REQUEST_URI'] = '/dbxapp-docs/';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SCRIPT_NAME'] = '/dbxapp-docs/index.php';

define('dbxSystem', 'dbxWebApp');
define('dbxRunAsAdmin', 1);

require $base . '/dbx/vendor/autoload.php';
require_once $base . '/dbx/include/dbxKernel.php';
require_once $base . '/dbx/modules/dbxSetup/include/dbxInstallationService.class.php';

use dbx\dbxSetup\dbxInstallationService;

$db = dbx()->get_system_obj('dbxDB');
$dd = dbx()->get_system_obj('dbxDD');
$schema = array();

foreach (array('dbxUser', 'dbxUser_groups') as $name) {
    $dd->sync_dd_to_db('dbx', $name, 'reset');
    $state = array();
    for ($step = 0; $step < 1000; $step++) {
        $state = $dd->sync_dd_to_db('dbx', $name, 'apply');
        if (in_array((string)($state['status'] ?? ''), array('finished', 'error', 'cancelled'), true)) {
            break;
        }
    }
    $schema[$name] = $state;
}

$failed = array_filter(
    $schema,
    static fn(array $state): bool => ($state['status'] ?? '') !== 'finished'
);
if ($failed !== array()) {
    throw new RuntimeException('Die Benutzer-DDs konnten nicht vollständig provisioniert werden.');
}

$installer = new dbxInstallationService($db, $dd);
$groups = $installer->seedCoreGroups();
$admin = $db->select1(
    'dbx|dbxUser',
    array('uname' => 'admin'),
    array('id', 'uname', 'roles', 'status', 'is_confirm'),
    0
);

if ((int)($admin['id'] ?? 0) <= 0) {
    $password = (string)getenv('DBX_DOCS_ADMIN_PASSWORD');
    $email = (string)getenv('DBX_DOCS_ADMIN_EMAIL');
    if ($password === '' || $email === '') {
        throw new RuntimeException(
            'Admin fehlt. DBX_DOCS_ADMIN_PASSWORD und DBX_DOCS_ADMIN_EMAIL müssen lokal gesetzt sein.'
        );
    }
    $installer->createAdmin($password, $email, 'de');
    $admin = $db->select1(
        'dbx|dbxUser',
        array('uname' => 'admin'),
        array('id', 'uname', 'roles', 'status', 'is_confirm'),
        0
    );
}

if (!dbx()->patch_local_config('dbxLogin', array('register' => '0'))) {
    throw new RuntimeException('Die lokale Login-Konfiguration konnte nicht geschrieben werden.');
}

$binding = $db->get_dd_server_binding_info('dbx|dbxUser');
$ok = (int)($admin['id'] ?? 0) > 0
    && (int)($admin['status'] ?? 0) === 1
    && str_contains((string)($admin['roles'] ?? ''), 'admin')
    && (string)dbx()->get_cfg('dbxLogin', 'register') === '0';

echo json_encode(array(
    'ok' => $ok,
    'schema' => $schema,
    'groups' => $groups,
    'admin' => array(
        'present' => (int)($admin['id'] ?? 0) > 0,
        'active' => (int)($admin['status'] ?? 0) === 1,
        'confirmed' => (int)($admin['is_confirm'] ?? 0) === 1,
    ),
    'registration' => 'disabled',
    'user_storage' => (string)($binding['resolved_server'] ?? ''),
), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($ok ? 0 : 1);
