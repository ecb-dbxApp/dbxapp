<?php
declare(strict_types=1);

$root = dirname(__DIR__, 4);
require_once $root . '/dbx/include/dbxApi.php';

$repo = dbx()->get_include_obj('dbxShopRepository', 'dbxShop');
$repo->seed_demo_products();

echo "dbxShop demo data updated via dbxShopRepository.\n";
