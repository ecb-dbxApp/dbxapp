<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/dbxKernel.php';

$fail = static function (string $message, int $code): void {
   fwrite(STDERR, "FAIL: $message\n");
   exit($code);
};

$expected_by_design = array(
   'dbxapp' => array('blau', 'dunkel'),
   'flowers' => array('hell'),
   'steal' => array('hell'),
);
foreach ($expected_by_design as $design => $expected_order) {
   $skins = dbx()->get_system_obj('dbxPresentation')->get_design_skin_ids($design);
   if ($skins !== $expected_order) {
      $fail($design . ' liefert nicht seine vorhandenen Skins: ' . implode(',', $skins), 1);
   }
}

if (dbx()->get_system_obj('dbxPresentation')->normalize_skin('rot', 'flowers') !== 'hell') {
   $fail('Ein nicht vorhandener Flowers-Skin fällt nicht sicher auf Hell zurück.', 2);
}

if (dbx()->get_system_obj('dbxPresentation')->get_design_skin_ids('../flowers') !== $expected_by_design['dbxapp']) {
   $fail('Ein unsicherer Designname verlaesst den sicheren dbxapp-Fallback.', 3);
}

dbx()->set_system_var('dbx_design', 'steal');
dbx()->set_system_var('dbx_activ_design', 'steal');
dbx()->set_system_var('dbx_color', 'hell');
if (dbx()->get_system_obj('dbxPresentation')->get_skin_css() !== 'dbx/design/steal/css/skin-hell.css') {
   $fail('Der Skin-CSS-Pfad folgt nicht der aktiven Design-/Skin-Kombination.', 4);
}

$menu_source = file_get_contents(dirname(__DIR__, 2) . '/modules/dbxMenu/dbxMenu.class.php');
$utilities_source = file_get_contents(dirname(__DIR__, 2) . '/js/lib/utilities.js');
if (!is_string($menu_source)
   || strpos($menu_source, 'render_design_menu') === false
   || strpos($menu_source, 'frontend_design_options') === false
   || strpos($menu_source, "'dbx_design' => \$design") === false
   || strpos($menu_source, 'theme_toggle_url') === false
   || strpos($menu_source, "'dbx_color' => \$night_active ? 'blau' : 'dunkel'") === false) {
   $fail('dbxMenu rendert Designauswahl und Tag/Nacht-Umschalter nicht über GET-Routen.', 5);
}
if (strpos($menu_source, 'array_intersect_key($options') !== false) {
   $fail('Das Designmenue beschränkt die installierten Frontend-Designs.', 8);
}
if (!is_string($utilities_source)
   || strpos($utilities_source, 'DESIGN_SKINS') !== false
   || strpos($utilities_source, 'ALL_SKINS') !== false
   || strpos($utilities_source, 'dbx-design-skin-opt[data-design][data-skin]') === false
   || strpos($utilities_source, 'skinStoreKey()') === false) {
   $fail('utilities.js verwendet noch eine globale Skin-Liste oder keinen designspezifischen Speicher.', 7);
}

echo "OK dbx design skins\n";
