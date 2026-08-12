<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/dbxWebApp.class.php';

$web = new dbxWebApp();
$cases = array(
   'dbxAdmin' => true,
   'dbxContent_admin' => true,
   'dbxMedia_admin' => true,
   'dbxHome' => false,
   'dbxDocs' => false,
   'dbxAdministrationGuide' => false,
);
foreach ($cases as $module => $expected) {
   if ($web->is_admin_route_module($module) !== $expected) {
      throw new RuntimeException('Admin-Design-Erkennung ist falsch für ' . $module);
   }
}
$source = (string)file_get_contents(dirname(__DIR__) . '/dbxWebApp.class.php');
if (!str_contains($source, '$this->is_admin_route_module((string)$modul)')
   || !str_contains($source, '$this->is_admin_only_module((string)$modul)')
   || !str_contains($source, "get_cfg(\$modul, 'groups')")
   || !str_contains($source, 'if ( $admin && $admin_modul)')) {
   throw new RuntimeException('check_design verwendet die zentrale Admin-Routenerkennung nicht.');
}

echo "OK admin design routing: central dbxAdmin and *_admin routes are forced into the configured admin design\n";
