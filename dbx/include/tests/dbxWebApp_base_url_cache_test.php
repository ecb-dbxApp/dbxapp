<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/dbxKernel.php';

$_SERVER['HTTPS'] = 'on';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_PORT'] = '443';

$web = dbx()->get_system_obj('dbxWebApp');

dbx()->set_remember_var('base_url', 'https://localhost/dbxapp/', 'dbx');
$base_url = $web->resolve_base_url('/dbxapp-github');
if ($base_url !== 'https://localhost/dbxapp-github/') {
   fwrite(STDERR, "FAIL: Veralteter Installationspfad blieb im Basis-URL-Cache: $base_url\n");
   exit(1);
}

dbx()->set_remember_var('base_url', 'https://localhost/dbxapp-github/', 'dbx');
$same_base_url = $web->resolve_base_url('/dbxapp-github');
if ($same_base_url !== 'https://localhost/dbxapp-github/') {
   fwrite(STDERR, "FAIL: Passender Basis-URL-Cache wurde nicht beibehalten: $same_base_url\n");
   exit(2);
}

echo "OK dbxWebApp base URL cache\n";
