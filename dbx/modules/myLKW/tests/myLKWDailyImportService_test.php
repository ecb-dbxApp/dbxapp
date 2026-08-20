<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/include/myLKWDailyImportService.class.php';

use dbx\myLKW\myLKWDailyImportService;

final class myLKWTestImporter {
   public int $calls = 0;

   public function import_data(): array {
      $this->calls++;
      return array('ok' => 1, 'inserted' => 23, 'skipped' => 2, 'message' => 'ok');
   }
}

$fail = static function(string $message): void {
   fwrite(STDERR, $message . PHP_EOL);
   exit(1);
};

$test_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dbxapp-mylkw-daily-' . bin2hex(random_bytes(6));
$state_file = $test_dir . DIRECTORY_SEPARATOR . 'state.json';
$importer = new myLKWTestImporter();
$service = new myLKWDailyImportService($importer, $state_file);

$first = $service->run_if_due(true, '2026-08-18');
if (($first['status'] ?? '') !== 'imported' || $importer->calls !== 1 || (int)$first['inserted'] !== 23) {
   $fail('Der erste Tagesaufruf muss genau einen Import ausführen.');
}

$second = $service->run_if_due(true, '2026-08-18');
if (($second['status'] ?? '') !== 'already_run' || $importer->calls !== 1) {
   $fail('Ein zweiter Aufruf am selben Tag darf keinen weiteren Import ausführen.');
}

$next_day = $service->run_if_due(true, '2026-08-19');
if (($next_day['status'] ?? '') !== 'imported' || $importer->calls !== 2) {
   $fail('Am nächsten Kalendertag muss erneut importiert werden.');
}

$disabled = $service->run_if_due(false, '2026-08-20');
if (($disabled['status'] ?? '') !== 'disabled' || $importer->calls !== 2) {
   $fail('Ein deaktivierter Tagesimport darf den Importer nicht aufrufen.');
}

$controller = (string)file_get_contents(dirname(__DIR__) . '/myLKW.class.php');
if (str_contains($controller, "get_remember_var('ist_shift'")
   || str_contains($controller, "set_remember_var('ist_shift'")) {
   $fail('Der sitzungsabhängige automatische Dayshift darf nicht mehr vorhanden sein.');
}
if (!str_contains($controller, 'run_daily_csv_import()')) {
   $fail('Der myLKW-Controller bindet den täglichen CSV-Import nicht ein.');
}
if (!str_contains($controller, 'is_dayshift_enabled()')
   || !str_contains($controller, 'Die Tagesverschiebung ist deaktiviert.')) {
   $fail('Der manuelle Dayshift-Aufruf muss durch die Installationskonfiguration gesperrt sein.');
}

$import_source = (string)file_get_contents(dirname(__DIR__) . '/include/myLKW_import.class.php');
foreach (array("->begin('lkw')", "->empty('lkw')", "->insert('lkw'", "->commit('lkw')") as $required) {
   if (!str_contains($import_source, $required)) {
      $fail('Die transaktionale dbxDB-Importgrenze fehlt: ' . $required);
   }
}
if (preg_match('/\b(?:PDO|mysqli|SQLite3)\b/', $import_source)) {
   $fail('Der CSV-Import darf keinen direkten Datenbanktreiber verwenden.');
}

foreach (array($state_file, $state_file . '.lock') as $file) {
   if (is_file($file)) unlink($file);
}
if (is_dir($test_dir)) rmdir($test_dir);

echo "myLKWDailyImportService: OK\n";
