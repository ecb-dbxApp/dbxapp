<?php

function dbx_get_base_dir($cut_data = 0): string {
   return $cut_data ? 'X:/portable-data-cut/' : 'X:/portable/';
}

function dbx_get_file_dir(): string {
   return 'X:/portable/files/';
}

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/dbxKernel.php';

if (dbx()->get_base_dir() !== 'X:/portable/'
   || dbx()->get_base_dir(1) !== 'X:/portable-data-cut/'
   || dbx()->get_file_dir() !== 'X:/portable/files/') {
   fwrite(STDERR, "FAIL: dbxApi delegiert die Bootstrap-Pfade nicht korrekt.\n");
   exit(1);
}

$api_source = (string)file_get_contents(dirname(__DIR__) . '/dbxApi.php');
$base_load = strpos($api_source, 'require_once $base_file;');
$load_only_return = strpos($api_source, 'if ($use)', $base_load === false ? 0 : $base_load);
$override_load = strpos($api_source, '$this->ensure_system_class_override', $base_load === false ? 0 : $base_load);
if ($base_load === false || $load_only_return === false || $override_load === false || $load_only_return > $override_load) {
   fwrite(STDERR, "FAIL: Nur-Laden-Modus muss vor der Override-Auswertung enden.\n");
   exit(1);
}

echo "OK dbxApi bootstrap delegation\n";
