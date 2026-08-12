<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$api = (string)file_get_contents($root . '/dbx/include/dbxApi.php');
$db = (string)file_get_contents($root . '/dbx/include/dbxDB.class.php');
$session = (string)file_get_contents($root . '/dbx/include/dbxSession.class.php');
$cache = (string)file_get_contents($root . '/dbx/modules/dbxContent/include/dbxContentPageCache.class.php');
$demoJs = (string)file_get_contents($root . '/dbx/js/lib/demoMode.js');
$webApp = (string)file_get_contents($root . '/dbx/include/dbxWebApp.class.php');

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
      && strpos($db, 'if (dbx()->is_demo_mode()') < strpos($db, "if (dbx()->can('dbxRunAsAdmin'))"),
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
      && str_contains($demoJs, 'isMutationForm')
      && str_contains($demoJs, 'speichern')
      && str_contains($demoJs, 'create')
      && str_contains($demoJs, 'anlegen')
      && str_contains($demoJs, 'erstellen')
      && !str_contains($demoJs, 'function isPostForm'),
   'Config-, UI- oder Cache-Grenze des Demo-Modus fehlt.'
);
$assert(
   str_contains($webApp, '$demoModeRevision')
      && str_contains($webApp, 'filemtime($demoModeFile)')
      && str_contains($webApp, "dbx()->get_version() . '-' . \$demoModeRevision"),
   'Die Demo-UI wird ohne dateibasierte Cache-Kennung ausgeliefert.'
);

echo "OK unified config API and Demo Mode contracts\n";
