<?php
/**
 * Wendet ein page.update (dbxKiCmsService) auf eine einzelne Seite an, um
 * Felder zu setzen, die translation.apply nicht abdeckt (z.B. menu_title).
 * Nutzt dieselbe Plan-/Execute-Logik wie die dbxKi-API, nur ohne HTTP/Token,
 * da dieses Skript als vertrauenswuerdiges lokales Admin-Tool laeuft.
 *
 * Aufruf: php dbxki_page_update.php <lng> <id> <patch_json_file>
 */
declare(strict_types=1);

$base = dirname(__DIR__, 4);
$_SERVER['REQUEST_URI'] = '/dbxapp/';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/dbxapp/index.php';

define('dbxSystem', 'dbxWebApp');
define('dbxRunAsAdmin', 1);

require $base . '/dbx/vendor/autoload.php';
require_once $base . '/dbx/include/dbxKernel.php';

$lng = (string)($argv[1] ?? '');
$id = (int)($argv[2] ?? 0);
$patch_file = (string)($argv[3] ?? '');

if ($lng === '' || $id <= 0 || $patch_file === '' || !is_file($patch_file)) {
   fwrite(STDERR, "Usage: php dbxki_page_update.php <lng> <id> <patch_json_file>\n");
   exit(1);
}

$patch = json_decode((string)file_get_contents($patch_file), true);
if (!is_array($patch)) {
   fwrite(STDERR, "Ungueltiges JSON in $patch_file\n");
   exit(1);
}

$params = array(
   'lng' => $lng,
   'id' => $id,
   'patch' => $patch,
);

$service = dbx()->get_include_obj('dbxKiCmsService', 'dbxKi');

$ref = new ReflectionClass($service);
$build_plan = $ref->getMethod('build_plan');
$build_plan->setAccessible(true);
$execute_action = $ref->getMethod('execute_action');
$execute_action->setAccessible(true);

try {
   $plan = $build_plan->invoke($service, 'page.update', $params);
   $result = $execute_action->invoke($service, 'page.update', $params, $plan);
   echo json_encode(array('ok' => 1, 'result' => $result), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (\Throwable $e) {
   fwrite(STDERR, get_class($e) . ': ' . $e->getMessage() . "\n");
   echo json_encode(array('ok' => 0, 'error' => $e->getMessage()), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
   exit(1);
}
