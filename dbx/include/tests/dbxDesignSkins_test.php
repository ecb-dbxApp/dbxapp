<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/dbxKernel.php';

$fail = static function (string $message, int $code): void {
   fwrite(STDERR, "FAIL: $message\n");
   exit($code);
};

$expectedByDesign = array(
   'dbxapp' => array('blau', 'dunkel'),
   'dbxdocs' => array('hell'),
   'flowers' => array('hell'),
   'steal' => array('hell'),
);
foreach ($expectedByDesign as $design => $expectedOrder) {
   $skins = dbx()->get_design_skin_ids($design);
   if ($skins !== $expectedOrder) {
      $fail($design . ' liefert nicht seine vorhandenen Skins: ' . implode(',', $skins), 1);
   }
}

if (dbx()->normalize_skin('rot', 'flowers') !== 'hell') {
   $fail('Ein nicht vorhandener Flowers-Skin fällt nicht sicher auf Hell zurück.', 2);
}

if (dbx()->get_design_skin_ids('../flowers') !== $expectedByDesign['dbxapp']) {
   $fail('Ein unsicherer Designname verlaesst den sicheren dbxapp-Fallback.', 3);
}

dbx()->set_system_var('dbx_design', 'steal');
dbx()->set_system_var('dbx_activ_design', 'steal');
dbx()->set_system_var('dbx_color', 'hell');
if (dbx()->get_skin_css() !== 'dbx/design/steal/css/skin-hell.css') {
   $fail('Der Skin-CSS-Pfad folgt nicht der aktiven Design-/Skin-Kombination.', 4);
}

$menuSource = file_get_contents(dirname(__DIR__, 2) . '/modules/dbxMenu/dbxMenu.class.php');
$utilitiesSource = file_get_contents(dirname(__DIR__, 2) . '/js/lib/utilities.js');
if (!is_string($menuSource)
   || strpos($menuSource, 'render_design_menu') === false
   || strpos($menuSource, 'frontend_design_options') === false
   || strpos($menuSource, "'dbx_design' => \$design") === false
   || strpos($menuSource, 'theme_toggle_url') === false
   || strpos($menuSource, "'dbx_color' => \$nightActive ? 'blau' : 'dunkel'") === false) {
   $fail('dbxMenu rendert Designauswahl und Tag/Nacht-Umschalter nicht über GET-Routen.', 5);
}
if (strpos($menuSource, "return 'dbxdocs';") === false
   || strpos($menuSource, "array('dbxdocs' => 'dbxapp (Blau)')") !== false
   || strpos($menuSource, 'array_intersect_key($options') !== false) {
   $fail('Das Dokumentationsmenue bietet nicht alle installierten Designs an.', 8);
}
if (!is_string($utilitiesSource)
   || strpos($utilitiesSource, 'DESIGN_SKINS') !== false
   || strpos($utilitiesSource, 'ALL_SKINS') !== false
   || strpos($utilitiesSource, 'dbx-design-skin-opt[data-design][data-skin]') === false
   || strpos($utilitiesSource, 'skinStoreKey()') === false) {
   $fail('utilities.js verwendet noch eine globale Skin-Liste oder keinen designspezifischen Speicher.', 7);
}

echo "OK dbx design skins\n";
