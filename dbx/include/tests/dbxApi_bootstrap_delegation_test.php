<?php

function dbx_get_base_dir($cutData = 0): string {
   return $cutData ? 'X:/portable-data-cut/' : 'X:/portable/';
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

echo "OK dbxApi bootstrap delegation\n";
