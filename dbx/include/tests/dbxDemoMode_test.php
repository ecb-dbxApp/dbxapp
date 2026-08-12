<?php

declare(strict_types=1);

putenv('DBX_DEMO_MODE=1'); // Darf ohne Benutzerrolle keine Wirkung haben.
if (!defined('dbxRunAsAdmin')) {
   define('dbxRunAsAdmin', 1);
}

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/dbxKernel.php';

$fail = static function (string $message, int $code): void {
   fwrite(STDERR, "FAIL: $message\n");
   exit($code);
};

$api = dbx();
$_SESSION['dbx']['current_user'] = array('id' => 27, 'roles' => 'authenticated,demo');
if (!$api->is_demo_mode()) {
   $fail('Die Benutzergruppe demo aktiviert den Demo-Modus nicht.', 1);
}
if ($api->is_admin_bypass_active() || (int)$api->user() !== 27) {
   $fail('Eine echte Demo-Anmeldung wird vom Entwicklungs-Admin-Bypass ueberlagert.', 13);
}
if ($api->can('admin') !== 1 || $api->can_modul('dbxAdmin') !== 1) {
   $fail('Admin-Menue oder Admin-Modul sind im Demo-Modus nicht sichtbar.', 2);
}

$raw = $api->get_cfg('dbx', 'db', array());
$display = $api->get_cfg('dbx', 'db', array(), true);
if (!is_array($raw) || !is_array($display)) {
   $fail('Die Kernkonfiguration ist nicht lesbar.', 3);
}
if (($display['dbxApp']['pass'] ?? null) !== '******') {
   $fail('Ein Passwort wird in der Demo-Anzeige nicht maskiert.', 4);
}
if (($display['dbxApp']['host'] ?? null) !== ($raw['dbxApp']['host'] ?? null)) {
   $fail('Ein normaler Konfigurationswert wurde unnoetig maskiert.', 5);
}

$secretKeyMethod = new ReflectionMethod($api, 'is_cfg_secret_key');
foreach (array('db_pass', 'smtpPassword', 'api_key', 'accessToken') as $secretName) {
   if ($secretKeyMethod->invoke($api, $secretName) !== true) {
      $fail("Geheimer Config-Schluessel ($secretName) wird nicht erkannt.", 10);
   }
}
foreach (array('host', 'port', 'database', 'username', 'cache', 'client_secret', 'legacy_pwd') as $publicName) {
   if ($secretKeyMethod->invoke($api, $publicName) !== false) {
      $fail("Normaler Config-Schluessel ($publicName) wird unnoetig maskiert.", 11);
   }
}

$configFile = dirname(__DIR__, 2) . '/modules/dbx/cfg/config.php';
$before = hash_file('sha256', $configFile);
$write = $api->set_cfg('dbx', $api->get_cfg('dbx'));
$after = hash_file('sha256', $configFile);
if ($write !== 0 || $before !== $after) {
   $fail('set_cfg() konnte im Demo-Modus eine Konfiguration veraendern.', 6);
}

$db = $api->get_system_obj('dbxDB');
foreach (array('insert', 'update', 'delete') as $mode) {
   if ($db->check_access($mode, 'dbxSession') !== 0) {
      $fail('dbxDB erlaubt im Demo-Modus die Operation ' . $mode . '.', 7);
   }
}

// Nicht nur die Rechteabfrage, sondern auch der regulaere Schreibpfad muss
// abbrechen. Der vorhandene Wert wird bewusst unveraendert angeboten, sodass
// selbst ein fehlerhafter Testlauf keine fachlichen Daten veraendert.
$demoUser = $db->select1('dbxUser', array('id' => 10), 'id,uname', 0);
if (is_array($demoUser) && (int)($demoUser['id'] ?? 0) === 10) {
   $writeResult = $db->update(
      'dbxUser',
      array('uname' => (string)($demoUser['uname'] ?? 'demo')),
      array('id' => 10)
   );
   if ($writeResult >= 1) {
      $fail('Der regulaere dbxDB-Updatepfad wurde im Demo-Modus nicht gesperrt.', 12);
   }
}

$_SESSION['dbx']['current_user'] = array('id' => 28, 'roles' => 'authenticated,editor');
if ($api->is_demo_mode()) {
   $fail('Ein Benutzer ohne Gruppe demo befindet sich im Demo-Modus.', 8);
}

$_SESSION['dbx']['current_user'] = array('id' => 29, 'roles' => 'demonstration');
if ($api->is_demo_mode()) {
   $fail('Eine aehnlich benannte Gruppe aktiviert den Demo-Modus.', 9);
}

$_SESSION['dbx']['current_user'] = array();
if (!$api->is_admin_bypass_active() || (int)$api->user() !== 1) {
   $fail('Der Entwicklungs-Admin-Bypass funktioniert fuer Gaeste nicht mehr.', 14);
}

echo "OK Demo Mode runtime boundaries\n";
