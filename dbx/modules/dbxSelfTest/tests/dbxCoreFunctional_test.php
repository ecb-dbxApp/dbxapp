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

$ddName = 'dbxSelfTest|coreFunctionalProbe';
$fdName = 'dbxSelfTest|core-functional';
$server = 'dbxSelfTest|dbxCoreFunctionalTest.db3';
$databaseFile = $root . '/dbx/modules/dbxSelfTest/db/dbxCoreFunctionalTest.db3';
$cacheProbeFile = $root . '/dbx/modules/dbxSelfTest/tpl/htm/core-functional-cache-probe.htm';
$checks = array();
$db = null;
$dd = null;

// Ein vorher abgebrochener Test darf den naechsten Lauf nicht beeinflussen.
foreach (array($databaseFile, $databaseFile . '-wal', $databaseFile . '-shm') as $staleFile) {
   if (is_file($staleFile)) @unlink($staleFile);
}
if (is_file($cacheProbeFile)) @unlink($cacheProbeFile);

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

   $apiA = dbx();
   $apiB = dbx();
   $assert($apiA === $apiB, 'dbx()', 'singleton', 'dbx() liefert unterschiedliche API-Objekte.', 10);
   $assert($apiA->get_version() === trim((string)file_get_contents($root . '/VERSION')), 'dbx()', 'version', 'VERSION und API weichen ab.', 11);
   $pass('dbx()', 'singleton+version', $apiA->get_version());

   $db = dbx()->get_system_obj('dbxDB');
   $sysMsgWhere = array('rid' => $server);
   $sysMsgBefore = $db->count('dbxSysMsg', $sysMsgWhere);

   $dd = dbx()->get_system_obj('dbxDD');
   $model = $dd->get_dd_model($ddName);
   $fieldNames = array_values(array_filter(array_map(
      static fn(array $field): string => (string)($field['name'] ?? ''),
      is_array($model['fields'] ?? null) ? $model['fields'] : array()
   )));
   $assert(($model['table']['table'] ?? '') === 'core_functional_probe', 'dbxDD', 'table', 'DD-Tabelle wurde nicht aufgeloest.', 20);
   $assert((int)($model['table']['system_inventory'] ?? 1) === 0, 'dbxDD', 'inventory-isolation', 'Test-DD ist nicht von produktiven Inventuren isoliert.', 24);
   foreach (array('id', 'probe_key', 'label', 'quantity', 'active') as $field) {
      $assert(in_array($field, $fieldNames, true), 'dbxDD', 'field-' . $field, 'DD-Feld fehlt: ' . $field, 21);
   }
   $assert($dd->create_db_tab($ddName) === 1, 'dbxDD', 'create', 'Isolierte DD-Tabelle konnte nicht erzeugt werden.', 22);
   $assert(
      $db->count('dbxSysMsg', $sysMsgWhere) === $sysMsgBefore,
      'dbxDD', 'expected-create-without-sysmsg',
      'Der erwartete SQLite-Testaufbau wurde als produktiver Systemfehler protokolliert.', 23
   );
   $pass('dbxDD', 'model+schema', count($fieldNames) . ' Felder');

   $rowsToInsert = array(
      array('probe_key' => 'CORE-A', 'label' => 'Alpha', 'quantity' => 2, 'sorter' => '0010', 'active' => 1),
      array('probe_key' => 'CORE-B', 'label' => 'Beta', 'quantity' => 5, 'sorter' => '0020', 'active' => 1),
   );
   foreach ($rowsToInsert as $row) {
      $assert($db->insert($ddName, $row) === 1, 'dbxDB', 'insert-' . $row['probe_key'], 'Insert fehlgeschlagen.', 30);
   }
   $rows = $db->select($ddName, array('active' => 1), '*', 'sorter', 'ASC');
   $assert(is_array($rows) && count($rows) === 2, 'dbxDB', 'select', 'Select liefert nicht exakt zwei Datensaetze.', 31);
   $firstId = (int)($rows[0]['id'] ?? 0);
   $assert($firstId > 0 && $db->update($ddName, array('quantity' => 3), $firstId) === 1, 'dbxDB', 'update', 'Update des ersten Datensatzes fehlgeschlagen.', 32);
   $updated = $db->select1($ddName, $firstId);
   $assert((int)($updated['quantity'] ?? 0) === 3, 'dbxDB', 'reread', 'Aktualisierter Wert wurde nicht gelesen.', 33);
   $assert($db->count($ddName, array('active' => 1)) === 2, 'dbxDB', 'count', 'Count ist nicht deterministisch.', 34);
   $rows[0] = $updated;
   $pass('dbxDB', 'crud', 'CORE-A=3, CORE-B=5');

   $assert(method_exists($dd, 'database') && $dd->database() === $db, 'dbxDD', 'composition', 'dbxDD nutzt nicht die zentrale dbxDB-Instanz.', 36);
   $assert(
      dbx()->get_system_obj('dbxForm') !== dbx()->get_system_obj('dbxForm')
         && dbx()->get_system_obj('dbxDB') === $db,
      'Runtime', 'service-lifetimes', 'Factory-/Singleton-Lebenszyklen sind inkonsistent.', 37
   );
   $shopActions = dbx()->get_system_obj('dbxActionManifest')->module('dbxShop_admin');
   $assert(count($shopActions) >= 20, 'Routing', 'action-manifest', 'Deklaratives Shop-Aktionsmanifest ist unvollständig.', 38);
   $pass('Runtime', 'context+lifetimes+routing', count($shopActions) . ' Shop-Routen');

   $form = dbx()->get_system_obj('dbxForm');
   $form->clear();
   $form->init('core-functional-form', 'dbxSelfTest|core-functional-form');
   $form->_dd = $ddName;
   $form->_fd = $fdName;
   $form->_data = array('probe_key' => 'CORE-A', 'label' => 'Alpha', 'quantity' => 3);
   $messages = $form->load_fd_messages();
   foreach ($messages as $messageKey => $messageValue) {
      $form->add_rep((string)$messageKey, (string)$messageValue);
   }
   $fieldCount = $form->add_flds('fd::');
   $assert($fieldCount === 3, 'dbxForm', 'field-source', 'FD lieferte ' . $fieldCount . ' statt 3 Formularfeldern.', 41);
   $formHtml = $form->add_norep($form->run());
   $assert(($messages['form_title'] ?? '') === 'Funktionaler Kern-Selbsttest', 'dbxFD', 'messages', 'FD-Meldung fehlt.', 40);
   foreach (array('name="probe_key"', 'name="label"', 'name="quantity"', 'CORE-A', 'Alpha') as $needle) {
      $assert(str_contains($formHtml, $needle), 'dbxForm', 'render-' . md5($needle), 'Formularausgabe fehlt: ' . $needle . '; Ausgabe: ' . substr(preg_replace('/\s+/', ' ', $formHtml), 0, 600), 42);
   }
   $pass('dbxFD', 'messages+fields', count($messages) . ' Meldungen');
   $pass('dbxForm', 'render+values', strlen($formHtml) . ' Bytes');

   $report = dbx()->get_system_obj('dbxReport');
   $report->init('core-functional-report', 'dbxSelfTest|core-functional-report');
   $report->_dd = $ddName;
   $report->_fd = $fdName;
   $report->load_fd_messages();
   $report->_mode = 'table';
   $report->_pages = false;
   $report->_create_row_select = false;
   $report->_create_row_edit = false;
   $report->_create_row_delete = false;
   $report->_rflds = array(
      'probe_key' => $report->get_fd_message('column_probe_key'),
      'label' => $report->get_fd_message('column_label'),
      'quantity' => $report->get_fd_message('column_quantity'),
   );
   $report->_rdata = $rows;
   $report->_rrows = 2;
   $report->_rpos = 0;
   $report->_rcount = 2;
   $report->_count_all = 2;
   $report->add_rep('report_title', $report->get_fd_message('report_title'));
   $reportHtml = $report->add_norep($report->run());
   foreach (array('data-core-functional-report="1"', 'CORE-A', 'Alpha', 'CORE-B', 'Beta') as $needle) {
      $assert(str_contains($reportHtml, $needle), 'dbxReport', 'render-' . md5($needle), 'Reportausgabe fehlt: ' . $needle, 50);
   }
   $pass('dbxReport', 'render+rows', strlen($reportHtml) . ' Bytes');

   $tpl = dbx()->get_system_obj('dbxTPL');
   $cacheProbeMtime = time() - 60;
   file_put_contents($cacheProbeFile, 'CACHE-ALPHA');
   touch($cacheProbeFile, $cacheProbeMtime);
   clearstatcache(true, $cacheProbeFile);
   $assert($tpl->read_tpl('dbxSelfTest', 'core-functional-cache-probe') === 'CACHE-ALPHA', 'dbxTPL', 'cache-first-read', 'Erster Template-Lesezugriff ist abgewichen.', 55);
   file_put_contents($cacheProbeFile, 'CACHE-BRAVO');
   touch($cacheProbeFile, $cacheProbeMtime);
   clearstatcache(true, $cacheProbeFile);
   $tpl->clear_raw_cache();
   $assert($tpl->read_tpl('dbxSelfTest', 'core-functional-cache-probe') === 'CACHE-BRAVO', 'dbxTPL', 'cache-invalidation', 'Explizit invalidierte Template-Aenderung blieb im Cache verborgen.', 56);
   $pass('dbxTPL', 'request-cache', 'explizite Invalidierung erkannt');

   $semanticResult = array(
      'api' => 'dbx', 'tpl' => 'dbxTPL', 'form' => 'dbxForm',
      'report' => 'dbxReport', 'dd' => 'dbxDD', 'fd' => 'dbxFD',
      'db' => 'dbxDB', 'rows' => 2, 'quantity_sum' => 8,
      'request_context' => true, 'dd_composition' => true,
      'action_manifest_routes' => count($shopActions),
   );
   $resultHash = hash('sha256', json_encode($semanticResult, JSON_UNESCAPED_SLASHES));
   $expectedHash = '5b2b824954958061fd247e007ffb2ab22d1259534365193f39502f25414b3928';
   $assert(hash_equals($expectedHash, $resultHash), 'Result', 'semantic-hash', 'Kontrollergebnis ist abgewichen: ' . $resultHash, 60);

   $resultHtml = $tpl->get_tpl('dbxSelfTest|core-functional-result', array(
      'title' => 'Funktionaler Kern-Selbsttest bestanden',
      'summary' => implode(', ', $checks),
      'result_hash' => $resultHash,
   ));
   $assert(str_contains($resultHtml, 'data-core-functional-result="' . $resultHash . '"'), 'dbxTPL', 'controlled-result', 'TPL hat das Kontrollergebnis nicht eingesetzt.', 61);
   $pass('dbxTPL', 'controlled-result', $resultHash);

   echo 'OK CORE-FUNCTIONAL result=' . $resultHash . ' checks=' . count($checks) . "\n";
} finally {
   foreach (array($db, $dd) as $connectionOwner) {
      if (is_object($connectionOwner) && isset($connectionOwner->db[$server])) {
         unset($connectionOwner->db[$server]);
      }
      if (is_object($connectionOwner) && property_exists($connectionOwner, 'pdo')) {
         $connectionOwner->pdo = null;
      }
   }
   clearstatcache(true, $databaseFile);
   foreach (array($databaseFile, $databaseFile . '-wal', $databaseFile . '-shm') as $file) {
      if (is_file($file)) @unlink($file);
   }
   if (is_file($cacheProbeFile)) @unlink($cacheProbeFile);
}
