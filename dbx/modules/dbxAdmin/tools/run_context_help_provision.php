<?php
$base = dirname(__DIR__, 4);
chdir($base);
$_SERVER['REQUEST_URI'] = '/dbxapp/';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SCRIPT_NAME'] = '/dbxapp/index.php';

define('dbxSystem', 'dbxWebApp');
define('dbxRunAsAdmin', 1);

require $base . '/dbx/vendor/autoload.php';
require_once $base . '/dbx/include/dbxKernel.php';
require_once $base . '/dbx/modules/dbxContent/include/dbxContentContextHelpProvision.class.php';

$db = dbx()->get_system_obj('dbxDB');
$server = 'dbx|dbxContent.db3';
if (!$db->connect_db_server($server)) {
    fwrite(STDERR, "DB connect failed\n");
    exit(1);
}

$result = \dbx\dbxContent\dbxContentContextHelpProvision::provisionAll($db);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

if (empty($result['errors'])) {
    $config = dbx()->get_config('dbxContent');
    if (!is_array($config)) {
        $config = array();
    }
    $config['context_help_provision_version'] = 6;
    dbx()->set_config('dbxContent', $config);
}
