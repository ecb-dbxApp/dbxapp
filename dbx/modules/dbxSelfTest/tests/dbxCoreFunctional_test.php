<?php

/**
 * Funktionaler vertikaler Kern-Selbsttest.
 *
 * Der Test nutzt die echten Laufzeitkomponenten gemeinsam und erzeugt in
 * einer isolierten SQLite-Datei ein deterministisches Ergebnis. Jede Stufe
 * besitzt einen eindeutigen Fehlercode, damit ein Defekt lokalisierbar bleibt.
 */

$root = dirname(__DIR__, 4);
chdir($root);
$_SERVER['REQUEST_URI'] = '/dbxapp/?dbx_modul=dbxSelfTest&dbx_run1=dashboard';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SCRIPT_NAME'] = '/dbxapp/index.php';
$_SERVER['REQUEST_METHOD'] = 'GET';

if (!defined('dbxSystem')) define('dbxSystem', 'dbxWebApp');
if (!defined('dbxRunAsAdmin')) define('dbxRunAsAdmin', 1);

require $root . '/dbx/vendor/autoload.php';
require_once $root . '/dbx/include/dbxKernel.php';

$dd_name = 'dbxSelfTest|coreFunctionalProbe';
$fd_name = 'dbxSelfTest|core-functional';
$server = 'dbxSelfTest|dbxCoreFunctionalTest.db3';
$database_file = $root . '/dbx/modules/dbxSelfTest/db/dbxCoreFunctionalTest.db3';
$cache_probe_file = $root . '/dbx/modules/dbxSelfTest/tpl/htm/core-functional-cache-probe.htm';
$checks = array();
$db = null;
$dd = null;

// Ein vorher abgebrochener Test darf den naechsten Lauf nicht beeinflussen.
foreach (array($database_file, $database_file . '-wal', $database_file . '-shm') as $stale_file) {
   if (is_file($stale_file)) @unlink($stale_file);
}
// SQLite erwartet für normale Requests eine bereits provisionierte Datei.
// Der isolierte Test stellt den leeren Container deshalb selbst bereit, damit
// sein absichtlicher Neuaufbau keine produktive Systemfehlermeldung erzeugt.
if (!is_dir(dirname($database_file))) @mkdir(dirname($database_file), 0775, true);
touch($database_file);
if (is_file($cache_probe_file)) @unlink($cache_probe_file);

$fail = static function (string $component, string $check, string $message, int $code): void {
   fwrite(STDERR, "FAIL [$component/$check]: $message\n");
   exit($code);
};
$pass = static function (string $component, string $check, string $detail = '') use (&$checks): void {
   $checks[] = $component . ':' . $check;
   echo 'PASS [' . $component . '/' . $check . ']' . ($detail !== '' ? ': ' . $detail : '') . "\n";
};
$assert = static function (bool $condition, string $component, string $check, string $message, int $code) use ($fail): void {
   if (!$condition) $fail($component, $check, $message, $code);
};

try {
   dbx()->set_system_var('dbx_activ_modul', 'dbxSelfTest');
   dbx()->set_system_var('dbx_master_modul', 'dbxSelfTest');
   dbx()->set_system_var('dbx_activ_modul_id', 1);
   dbx()->set_system_var('dbx_lng', 'de');

   $api_a = dbx();
   $api_b = dbx();
   $assert($api_a === $api_b, 'dbx()', 'singleton', 'dbx() liefert unterschiedliche API-Objekte.', 10);
   $assert($api_a->get_version() === trim((string)file_get_contents($root . '/VERSION')), 'dbx()', 'version', 'VERSION und API weichen ab.', 11);
   $pass('dbx()', 'singleton+version', $api_a->get_version());

   $db = dbx()->get_system_obj('dbxDB');
   $sys_msg_where = array('rid' => $server);
   $sys_msg_before = $db->count('dbxSysMsg', $sys_msg_where);

   $dd = dbx()->get_system_obj('dbxDD');
   $model = $dd->get_dd_model($dd_name);
   $field_names = array_values(array_filter(array_map(
      static fn(array $field): string => (string)($field['name'] ?? ''),
      is_array($model['fields'] ?? null) ? $model['fields'] : array()
   )));
   $assert(($model['table']['table'] ?? '') === 'core_functional_probe', 'dbxDD', 'table', 'DD-Tabelle wurde nicht aufgeloest.', 20);
   $assert((int)($model['table']['system_inventory'] ?? 1) === 0, 'dbxDD', 'inventory-isolation', 'Test-DD ist nicht von produktiven Inventuren isoliert.', 24);
   foreach (array('id', 'probe_key', 'label', 'quantity', 'active') as $field) {
      $assert(in_array($field, $field_names, true), 'dbxDD', 'field-' . $field, 'DD-Feld fehlt: ' . $field, 21);
   }
   $assert($dd->create_db_tab($dd_name) === 1, 'dbxDD', 'create', 'Isolierte DD-Tabelle konnte nicht erzeugt werden.', 22);
   $assert(
      $db->count('dbxSysMsg', $sys_msg_where) === $sys_msg_before,
      'dbxDD', 'expected-create-without-sysmsg',
      'Der erwartete SQLite-Testaufbau wurde als produktiver Systemfehler protokolliert.', 23
   );
   $pass('dbxDD', 'model+schema', count($field_names) . ' Felder');

   $rows_to_insert = array(
      array('probe_key' => 'CORE-A', 'label' => 'Alpha', 'quantity' => 2, 'sorter' => '0010', 'active' => 1),
      array('probe_key' => 'CORE-B', 'label' => 'Beta', 'quantity' => 5, 'sorter' => '0020', 'active' => 1),
   );
   foreach ($rows_to_insert as $row) {
      $assert($db->insert($dd_name, $row) === 1, 'dbxDB', 'insert-' . $row['probe_key'], 'Insert fehlgeschlagen.', 30);
   }
   $rows = $db->select($dd_name, array('active' => 1), '*', 'sorter', 'ASC');
   $assert(is_array($rows) && count($rows) === 2, 'dbxDB', 'select', 'Select liefert nicht exakt zwei Datensaetze.', 31);
   $first_id = (int)($rows[0]['id'] ?? 0);
   $assert($first_id > 0 && $db->update($dd_name, array('quantity' => 3), $first_id) === 1, 'dbxDB', 'update', 'Update des ersten Datensatzes fehlgeschlagen.', 32);
   $updated = $db->select1($dd_name, $first_id);
   $assert((int)($updated['quantity'] ?? 0) === 3, 'dbxDB', 'reread', 'Aktualisierter Wert wurde nicht gelesen.', 33);
   $assert($db->count($dd_name, array('active' => 1)) === 2, 'dbxDB', 'count', 'Count ist nicht deterministisch.', 34);
   $rows[0] = $updated;
   $pass('dbxDB', 'crud', 'CORE-A=3, CORE-B=5');

   $assert(method_exists($dd, 'database') && $dd->database() === $db, 'dbxDD', 'composition', 'dbxDD nutzt nicht die zentrale dbxDB-Instanz.', 36);
   $assert(
      dbx()->get_system_obj('dbxForm') !== dbx()->get_system_obj('dbxForm')
         && dbx()->get_system_obj('dbxDB') === $db,
      'Runtime', 'service-lifetimes', 'Factory-/Singleton-Lebenszyklen sind inkonsistent.', 37
   );
   $shop_actions = dbx()->get_system_obj('dbxActionManifest')->module('dbxShop_admin');
   $assert(count($shop_actions) >= 20, 'Routing', 'action-manifest', 'Deklaratives Shop-Aktionsmanifest ist unvollständig.', 38);
   $pass('Runtime', 'context+lifetimes+routing', count($shop_actions) . ' Shop-Routen');

   $form = dbx()->get_system_obj('dbxForm');
   $form->clear();
   $form->init('core-functional-form', 'dbxSelfTest|core-functional-form');
   $form->set_data_source($dd_name, $fd_name);
   $form->set_data(array('probe_key' => 'CORE-A', 'label' => 'Alpha', 'quantity' => 3));
   $messages = $form->load_fd_messages();
   foreach ($messages as $message_key => $message_value) {
      $form->add_rep((string)$message_key, (string)$message_value);
   }
   $field_count = $form->add_flds('fd::');
   $assert($field_count === 3, 'dbxForm', 'field-source', 'FD lieferte ' . $field_count . ' statt 3 Formularfeldern.', 41);
   $form_html = $form->add_norep($form->run());
   $assert(($messages['form_title'] ?? '') === 'Funktionaler Kern-Selbsttest', 'dbxFD', 'messages', 'FD-Meldung fehlt.', 40);
   foreach (array('name="probe_key"', 'name="label"', 'name="quantity"', 'CORE-A', 'Alpha') as $needle) {
      $assert(str_contains($form_html, $needle), 'dbxForm', 'render-' . md5($needle), 'Formularausgabe fehlt: ' . $needle . '; Ausgabe: ' . substr(preg_replace('/\s+/', ' ', $form_html), 0, 600), 42);
   }
   $pass('dbxFD', 'messages+fields', count($messages) . ' Meldungen');
   $pass('dbxForm', 'render+values', strlen($form_html) . ' Bytes');

   $report = dbx()->get_system_obj('dbxReport');
   $report->init('core-functional-report', 'dbxSelfTest|core-functional-report');
   $report->set_data_source($dd_name, $fd_name);
   $report->load_fd_messages();
   $report->set_mode('table');
   $report->set_pagination(false);
   $report->set_table_actions(array());
   $report->set_report_fields(array(
      'probe_key' => $report->get_fd_message('column_probe_key'),
      'label' => $report->get_fd_message('column_label'),
      'quantity' => $report->get_fd_message('column_quantity'),
   ));
   $report->set_page_size(2);
   $report->set_report_result($rows, 0, 2);
   $report->add_rep('report_title', $report->get_fd_message('report_title'));
   $report_html = $report->add_norep($report->run());
   foreach (array('data-core-functional-report="1"', 'CORE-A', 'Alpha', 'CORE-B', 'Beta') as $needle) {
      $assert(str_contains($report_html, $needle), 'dbxReport', 'render-' . md5($needle), 'Reportausgabe fehlt: ' . $needle, 50);
   }
   $pass('dbxReport', 'render+rows', strlen($report_html) . ' Bytes');

   $tpl = dbx()->get_system_obj('dbxTPL');
   $cache_probe_mtime = time() - 60;
   file_put_contents($cache_probe_file, 'CACHE-ALPHA');
   touch($cache_probe_file, $cache_probe_mtime);
   clearstatcache(true, $cache_probe_file);
   $assert($tpl->read_tpl('dbxSelfTest', 'core-functional-cache-probe') === 'CACHE-ALPHA', 'dbxTPL', 'cache-first-read', 'Erster Template-Lesezugriff ist abgewichen.', 55);
   file_put_contents($cache_probe_file, 'CACHE-BRAVO');
   touch($cache_probe_file, $cache_probe_mtime);
   clearstatcache(true, $cache_probe_file);
   $tpl->clear_raw_cache();
   $assert($tpl->read_tpl('dbxSelfTest', 'core-functional-cache-probe') === 'CACHE-BRAVO', 'dbxTPL', 'cache-invalidation', 'Explizit invalidierte Template-Aenderung blieb im Cache verborgen.', 56);
   $pass('dbxTPL', 'request-cache', 'explizite Invalidierung erkannt');

   $semantic_result = array(
      'api' => 'dbx', 'tpl' => 'dbxTPL', 'form' => 'dbxForm',
      'report' => 'dbxReport', 'dd' => 'dbxDD', 'fd' => 'dbxFD',
      'db' => 'dbxDB', 'rows' => 2, 'quantity_sum' => 8,
      'request_context' => true, 'dd_composition' => true,
      'action_manifest_routes' => count($shop_actions),
   );
   $result_hash = hash('sha256', json_encode($semantic_result, JSON_UNESCAPED_SLASHES));
   $expected_hash = '5b2b824954958061fd247e007ffb2ab22d1259534365193f39502f25414b3928';
   $assert(hash_equals($expected_hash, $result_hash), 'Result', 'semantic-hash', 'Kontrollergebnis ist abgewichen: ' . $result_hash, 60);

   $result_html = $tpl->get_tpl('dbxSelfTest|core-functional-result', array(
      'title' => 'Funktionaler Kern-Selbsttest bestanden',
      'summary' => implode(', ', $checks),
      'result_hash' => $result_hash,
   ));
   $assert(str_contains($result_html, 'data-core-functional-result="' . $result_hash . '"'), 'dbxTPL', 'controlled-result', 'TPL hat das Kontrollergebnis nicht eingesetzt.', 61);
   $pass('dbxTPL', 'controlled-result', $result_hash);

   echo 'OK CORE-FUNCTIONAL result=' . $result_hash . ' checks=' . count($checks) . "\n";
} finally {
   foreach (array($db, $dd) as $connection_owner) {
      if (is_object($connection_owner) && isset($connection_owner->db[$server])) {
         unset($connection_owner->db[$server]);
      }
      if (is_object($connection_owner) && property_exists($connection_owner, 'pdo')) {
         $connection_owner->pdo = null;
      }
   }
   clearstatcache(true, $database_file);
   foreach (array($database_file, $database_file . '-wal', $database_file . '-shm') as $file) {
      if (is_file($file)) @unlink($file);
   }
   if (is_file($cache_probe_file)) @unlink($cache_probe_file);
}
