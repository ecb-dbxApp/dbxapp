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
require_once $base . '/dbx/modules/dbxKi/include/dbxKiCmsHelpProvision.class.php';

$result = \dbx\dbxKi\dbxKiCmsHelpProvision::provision();
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

if (empty($result['errors'])) {
   $config = dbx()->get_config('dbxKi');
   if (!is_array($config)) {
      $config = array();
   }
   $config[\dbx\dbxKi\dbxKiCmsHelpProvision::CONFIG_KEY] = \dbx\dbxKi\dbxKiCmsHelpProvision::PROVISION_VERSION;
   dbx()->set_config('dbxKi', $config);
   exit(0);
}

exit(1);
