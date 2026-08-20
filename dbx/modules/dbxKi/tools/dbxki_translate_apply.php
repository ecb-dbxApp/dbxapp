<?php
/**
 * Wendet eine von der KI gelieferte Uebersetzung ueber die echte dbxKi-Modul-
 * Logik (dbxKiCmsService: translation.apply) auf eine Zielseite an. Ruft
 * exakt dieselben plan_translation_apply()/execute_translation_apply()-
 * Methoden auf, die auch ueber die dbxKi-API (?dbx_modul=dbxKi&dbx_run1=execute)
 * ausgefuehrt wuerden - nur ohne den HTTP/Token-Umweg, da dieses Skript
 * bereits als vertrauenswuerdiges lokales Admin-Tool laeuft (dbxRunAsAdmin).
 *
 * Aufruf: php dbxki_translate_apply.php <source_lng> <target_lng> <source_id> <translation_json_file>
 * translation_json_file enthaelt: {"title":"...","description":"...","keywords":"...","content":"..."}
 * optional zusaetzlich: seo_title, img_alt_1..3, img_des_1..3, copy_media (0/1, Standard 1)
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

$source_lng = (string)($argv[1] ?? '');
$target_lng = (string)($argv[2] ?? '');
$source_id = (int)($argv[3] ?? 0);
$translation_file = (string)($argv[4] ?? '');

if ($source_lng === '' || $target_lng === '' || $source_id <= 0 || $translation_file === '' || !is_file($translation_file)) {
   fwrite(STDERR, "Usage: php dbxki_translate_apply.php <source_lng> <target_lng> <source_id> <translation_json_file>\n");
   exit(1);
}

$translation = json_decode((string)file_get_contents($translation_file), true);
if (!is_array($translation)) {
   fwrite(STDERR, "Ungueltiges JSON in $translation_file\n");
   exit(1);
}

$copy_media = array_key_exists('copy_media', $translation) ? (int)$translation['copy_media'] : 1;
unset($translation['copy_media']);

$params = array(
   'source_lng' => $source_lng,
   'target_lng' => $target_lng,
   'source_id' => $source_id,
   'copy_media' => $copy_media,
   'translation' => $translation,
);

$service = dbx()->get_include_obj('dbxKiCmsService', 'dbxKi');

$ref = new ReflectionClass($service);
$build_plan = $ref->getMethod('build_plan');
$build_plan->setAccessible(true);
$execute_action = $ref->getMethod('execute_action');
$execute_action->setAccessible(true);

try {
   $plan = $build_plan->invoke($service, 'translation.apply', $params);
   $result = $execute_action->invoke($service, 'translation.apply', $params, $plan);
   echo json_encode(array('ok' => 1, 'result' => $result), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (\Throwable $e) {
   fwrite(STDERR, get_class($e) . ': ' . $e->getMessage() . "\n");
   echo json_encode(array('ok' => 0, 'error' => $e->getMessage()), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
   exit(1);
}
