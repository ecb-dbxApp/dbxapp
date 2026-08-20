<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once __DIR__ . '/dbxModuleSourceBundle.php';
$api = dbx_test_module_source_bundle($root . '/dbx/include/dbxApi.php');
$db = dbx_test_module_source_bundle($root . '/dbx/include/dbxDB.class.php');
$session = (string)file_get_contents($root . '/dbx/include/dbxSession.class.php');
$cache = (string)file_get_contents($root . '/dbx/modules/dbxContent/include/dbxContentPageCache.class.php');
$demo_js = (string)file_get_contents($root . '/dbx/js/lib/demoMode.js');
$web_app = (string)file_get_contents($root . '/dbx/include/dbxWebApp.class.php');

$assert = static function (bool $condition, string $message): void {
   if (!$condition) {
      throw new RuntimeException($message);
   }
};

$assert(
   preg_match('/public function get_cfg\s*\(/', $api) === 1
      && preg_match('/public function set_cfg\s*\(/', $api) === 1,
   'Die einheitliche Config-API fehlt.'
);
$assert(
   preg_match('/\b(?:get_config|set_config|set_local_config)\s*\(/', $api) !== 1,
   'Eine alte oder parallele Config-API ist weiterhin vorhanden.'
);
$assert(
   str_contains($api, "mask_cfg_for_display")
      && str_contains($api, "return '******'")
      && str_contains($api, "if (\$this->is_demo_mode()"),
   'Config-Maskierung oder Demo-Schreibsperre fehlt.'
);
$assert(
   str_contains($db, "in_array(\$mode, array('insert', 'update', 'delete'), true)")
      && strpos($db, 'if (dbx()->is_demo_mode()') < strpos($db, "if (dbx()->has_group('dbxRunAsAdmin'))"),
   'Die Demo-Sperre muss vor dem Admin-Bypass in dbxDB greifen.'
);
$assert(
   preg_match("/->update\('dbxSession',[^;]+,0,1,0,0\)/s", $session) === 1
      && preg_match("/->insert\('dbxSession',[^;]+,0,1,0,0\)/s", $session) === 1,
   'Session-Schreibvorgaenge umgehen die fachliche Rechtepruefung nicht mehr.'
);
$assert(
   str_contains($api, 'public function set_cfg')
      && str_contains($cache, 'dbx()->is_demo_mode()')
      && str_contains($demo_js, 'isMutationForm')
      && str_contains($demo_js, 'speichern')
      && str_contains($demo_js, 'create')
      && str_contains($demo_js, 'anlegen')
      && str_contains($demo_js, 'erstellen')
      && !str_contains($demo_js, 'function isPostForm'),
   'Config-, UI- oder Cache-Grenze des Demo-Modus fehlt.'
);
$assert(
   str_contains($web_app, '$demo_mode_revision')
      && str_contains($web_app, 'filemtime($demo_mode_file)')
      && str_contains($web_app, "dbx()->get_version() . '-' . \$demo_mode_revision"),
   'Die Demo-UI wird ohne dateibasierte Cache-Kennung ausgeliefert.'
);

echo "OK unified config API and Demo Mode contracts\n";
