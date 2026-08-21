<?php
declare(strict_types=1);

if (!defined('dbxRunAsAdmin')) {
   define('dbxRunAsAdmin', 1);
}

$root = dirname(__DIR__, 3);
require_once $root . '/vendor/autoload.php';
require_once $root . '/include/dbxKernel.php';

$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
   if (!$condition) {
      $failures[] = $message;
   }
};

dbx()->set_system_var('dbx_master_modul', 'dbxMenu');
dbx()->set_system_var('dbx_activ_modul', 'dbxMenu');
dbx()->set_system_var('dbx_lng', 'de');

$slots = dbx()->get_include_obj('dbxMenuSlot', 'dbxMenu');
$assert($slots->register('user', 'submenu-user'), 'Benutzer-Slot wurde nicht registriert.');
$assert($slots->register('admin', 'submenu-admin'), 'Admin-Slot wurde nicht registriert.');
$assert(
   str_contains($slots->render('user'), 'dbx_modul=dbxLogin'),
   'Benutzer-Template wurde nicht aus dem Hauptmodul gerendert.'
);
$assert(
   trim($slots->render('admin')) !== '',
   'Admin-Template wurde trotz Admin-Kontext nicht gerendert.'
);

$assert(!$slots->register('other', 'submenu-user'), 'Unbekannter Bereich wurde akzeptiert.');
$assert(!$slots->register('user', '../submenu-user'), 'Unsicherer Templatepfad wurde akzeptiert.');
$assert(
   !$slots->register('user', 'submenu-user', array('unsafe' => new stdClass())),
   'Nicht serialisierbare/komplexe Template-Daten wurden akzeptiert.'
);

dbx()->set_system_var('dbx_activ_modul', 'dbxContent');
$assert(
   !$slots->register('user', 'submenu-user'),
   'Ein verschachteltes/fremdes Modul durfte den Hauptmodul-Slot ersetzen.'
);
dbx()->set_system_var('dbx_activ_modul', 'dbxMenu');

$menu = dbx()->get_modul_obj('dbxMenu');
$replace = new ReflectionMethod($menu, 'replace_module_menu_slots');
$replace->setAccessible(true);
$rendered = (string)$replace->invoke(
   $menu,
   '<ul>{dbx:modul_menu_user}{dbx:modul_menu_admin}</ul>'
);
$assert(!str_contains($rendered, '{dbx:modul_menu_'), 'Ein Slot-Marker blieb im HTML stehen.');
$assert(str_contains($rendered, 'dbx_modul=dbxLogin'), 'Der Benutzer-Slot fehlt im Menue-HTML.');

$language_home = new ReflectionMethod($menu, 'language_home_url');
$assert(
   $language_home->invoke(
      $menu,
      'https://localhost/dbxapp/',
      'https://localhost/dbxapp/',
      'de',
      'de',
      true
   ) === 'https://localhost/dbxapp/',
   'Die Standardsprache der Startseite besitzt keine direkte kanonische URL.'
);
$assert(
   $language_home->invoke(
      $menu,
      'https://localhost/dbxapp/',
      'https://localhost/dbxapp/',
      'en',
      'de',
      true
   ) === 'https://localhost/dbxapp/en/',
   'Die Sprachvariante der Startseite besitzt keine direkte kanonische URL.'
);
$assert(
   $language_home->invoke(
      $menu,
      'https://localhost/dbxapp/index.php',
      'https://localhost/dbxapp/',
      'en',
      'de',
      true
   ) === 'https://localhost/dbxapp/en/',
   'Der interne Frontcontroller wurde nicht als kanonische Startseite erkannt.'
);
$assert(
   $language_home->invoke(
      $menu,
      'https://localhost/dbxapp/?dbx_lng=de',
      'https://localhost/dbxapp/',
      'en',
      'de',
      true
   ) === 'https://localhost/dbxapp/en/',
   'Ein vorhandener Sprachparameter wurde nicht durch die kanonische Startseiten-URL ersetzt.'
);
$assert(
   $language_home->invoke(
      $menu,
      'https://localhost/dbxapp/home?dbx_lng=de',
      'https://localhost/dbxapp/',
      'en',
      'de',
      true
   ) === 'https://localhost/dbxapp/en/',
   'Der interne Page-Cache-Alias /home wurde nicht als kanonische Startseite erkannt.'
);
$assert(
   $language_home->invoke(
      $menu,
      'home',
      'https://localhost/dbxapp/',
      'en',
      'de',
      true
   ) === 'https://localhost/dbxapp/en/',
   'Der relative Laufzeitwert des Page-Cache-Alias wurde nicht als Startseite erkannt.'
);
$assert(
   $language_home->invoke(
      $menu,
      'https://localhost/dbxapp/?dbx_modul=dbxAdmin&dbx_lng=de',
      'https://localhost/dbxapp/',
      'en',
      'de',
      true
   ) === '',
   'Eine technische Startseitenroute wurde fälschlich als Sprach-Homepage umgeschrieben.'
);

$slots->clear();
$empty = (string)$replace->invoke(
   $menu,
   '<ul>{dbx:modul_menu_user}{dbx:modul_menu_admin}</ul>'
);
$assert($empty === '<ul></ul>', 'Leere Slots wurden nicht vollstaendig entfernt.');

$slots->clear();
$_GET['dbx_module_menu_slots'] = array(
   'user' => array('module' => 'dbxMenu', 'template' => 'submenu-user'),
);
$assert(
   $slots->render('user') === '',
   'Eine Registrierung konnte aus GET statt aus dem internen Request-Speicher eingeschleust werden.'
);
unset($_GET['dbx_module_menu_slots']);

if ($failures !== array()) {
   fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
   exit(1);
}

echo "OK dbxMenu module slots\n";
