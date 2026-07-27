<?php
/**
 * Direkt-Runner fuer dbxKi-Jobs.
 *
 * Zweck: Eine lokale KI wie Codex/Cursor kann einen dbxKi-Job ohne
 * Einmal-Skript ausfuehren. Der Runner nutzt dieselben sicheren Aktionen wie
 * der Bundle-Import und akzeptiert JSON aus Datei oder STDIN.
 *
 * Aufruf:
 *   php dbx/modules/dbxKi/tools/run_job.php job.json
 *   Get-Content job.json -Raw | php dbx/modules/dbxKi/tools/run_job.php -
 */
$base = dirname(__DIR__, 4);
chdir($base);
$_SERVER['REQUEST_URI'] = '/dbxapp/';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SCRIPT_NAME'] = '/dbxapp/index.php';

define('dbxSystem', 'dbxWebApp');
define('dbxRunAsAdmin', 1);

require $base . '/dbx/vendor/autoload.php';
require_once $base . '/dbx/include/dbxKernel.php';
require_once $base . '/dbx/modules/dbxKi/include/dbxKiCmsService.class.php';

function dbxki_usage(): void {
   fwrite(STDERR, "Usage: php dbx/modules/dbxKi/tools/run_job.php job.json|-\n");
}

function dbxki_read_job(array $argv): array {
   $source = (string)($argv[1] ?? '');
   if ($source === '') {
      dbxki_usage();
      exit(2);
   }

   if ($source === '-') {
      $json = stream_get_contents(STDIN);
   } else {
      $file = dbx()->os_path($source);
      if (!is_file($file)) {
         throw new RuntimeException('Job-Datei nicht gefunden: ' . $source);
      }
      $json = file_get_contents($file);
   }

   $job = json_decode((string)$json, true);
   if (!is_array($job)) {
      throw new RuntimeException('Job-JSON ist ungueltig.');
   }
   if (!is_array($job['steps'] ?? null)) {
      throw new RuntimeException('Job benoetigt steps[].');
   }
   return $job;
}

function dbxki_result_ref(string $ref, array $results) {
   $path = substr($ref, 5);
   $parts = explode('.', $path);
   $step = array_shift($parts);
   if ($step === '' || !array_key_exists($step, $results)) {
      throw new RuntimeException('Unbekannte Step-Referenz: ' . $ref);
   }
   $value = $results[$step];
   foreach ($parts as $part) {
      if (is_array($value) && array_key_exists($part, $value)) {
         $value = $value[$part];
         continue;
      }
      throw new RuntimeException('Unbekanntes Referenzfeld: ' . $ref);
   }
   return $value;
}

function dbxki_prepare_asset_file(array $params): array {
   $assetFile = trim((string)($params['asset_file'] ?? ''));
   if ($assetFile === '') {
      return $params;
   }

   $file = dbx()->os_path($assetFile);
   if (!is_file($file) || !is_readable($file)) {
      throw new RuntimeException('asset_file nicht lesbar: ' . $assetFile);
   }
   if (empty($params['file_name'])) {
      $params['file_name'] = basename($file);
   }
   $params['data_base64'] = base64_encode(file_get_contents($file));
   unset($params['asset_file']);
   return $params;
}

function dbxki_resolve_params($value, array $results) {
   if (is_array($value)) {
      $out = array();
      foreach ($value as $key => $item) {
         $out[$key] = dbxki_resolve_params($item, $results);
      }
      return dbxki_prepare_asset_file($out);
   }

   if (!is_string($value)) {
      return $value;
   }

   if (strpos($value, '$ref:') === 0) {
      return dbxki_result_ref($value, $results);
   }

   if (strpos($value, '$ref:') !== false) {
      return preg_replace_callback('/\$ref:([A-Za-z0-9_.-]+)/', function($match) use ($results) {
         return (string)dbxki_result_ref('$ref:' . $match[1], $results);
      }, $value);
   }

   return $value;
}

function dbxki_normalize_result(string $action, array $result): array {
   $out = $result;
   if (!empty($result['id'])) {
      $out['id'] = (int)$result['id'];
   }
   if (strpos($action, 'page.') === 0 && !empty($result['id'])) {
      $out['page_id'] = (int)$result['id'];
   }
   if (strpos($action, 'folder.') === 0 && !empty($result['id'])) {
      $out['folder_id'] = (int)$result['id'];
   }
   if (strpos($action, 'media.create') === 0 && !empty($result['id'])) {
      $out['media_id'] = (int)$result['id'];
   }
   if ($action === 'media.assign' && !empty($result['usage_id'])) {
      $out['usage_id'] = (int)$result['usage_id'];
   }
   return $out;
}

try {
   $job = dbxki_read_job($argv);
   $cms = new \dbx\dbxKi\dbxKiCmsService();
   $results = array();

   foreach ($job['steps'] as $pos => $step) {
      if (!is_array($step)) {
         throw new RuntimeException('Step #' . ($pos + 1) . ' ist ungueltig.');
      }
      $stepId = trim((string)($step['id'] ?? ('step_' . ($pos + 1))));
      $action = trim((string)($step['action'] ?? ''));
      if ($stepId === '' || $action === '') {
         throw new RuntimeException('Step #' . ($pos + 1) . ' benoetigt id und action.');
      }
      if (!$cms->bundleIsAllowedInPackage($action)) {
         throw new RuntimeException('Aktion ist fuer direkte Jobs nicht freigegeben: ' . $action);
      }

      $params = dbxki_resolve_params(is_array($step['params'] ?? null) ? $step['params'] : array(), $results);
      $plan = $cms->bundleBuildPlan($action, $params);
      $result = $cms->bundleExecutePlan($action, $params, $plan);
      $results[$stepId] = dbxki_normalize_result($action, $result);
   }

   echo json_encode(array('ok' => 1, 'results' => $results), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
   exit(0);
} catch (Throwable $e) {
   echo json_encode(array('ok' => 0, 'error' => $e->getMessage()), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
   exit(1);
}
