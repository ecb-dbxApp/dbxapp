<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/dbxKernel.php';

$fail = static function (string $message, int $code): void {
   fwrite(STDERR, "FAIL: $message\n");
   exit($code);
};

$expectedOrder = array('hell', 'gelb', 'rot', 'gruen', 'blau', 'dunkel');
foreach (array('dbxapp', 'flowers', 'steal') as $design) {
   $skins = dbx()->get_design_skin_ids($design);
   if ($skins !== $expectedOrder) {
      $fail($design . ' liefert nicht seine sechs vorhandenen Skins: ' . implode(',', $skins), 1);
   }
}

if (dbx()->normalize_skin('rot', 'flowers') !== 'rot') {
   $fail('Die Flowers-Skins werden weiterhin auf Hell/Dunkel begrenzt.', 2);
}

if (dbx()->get_design_skin_ids('../flowers') !== $expectedOrder) {
   $fail('Ein unsicherer Designname verlaesst den sicheren dbxapp-Fallback.', 3);
}

dbx()->set_system_var('dbx_design', 'steal');
dbx()->set_system_var('dbx_activ_design', 'steal');
dbx()->set_system_var('dbx_color', 'gelb');
if (dbx()->get_skin_css() !== 'dbx/design/steal/css/skin-gelb.css') {
   $fail('Der Skin-CSS-Pfad folgt nicht der aktiven Design-/Skin-Kombination.', 4);
}

$menuSource = file_get_contents(dirname(__DIR__, 2) . '/modules/dbxMenu/dbxMenu.class.php');
$menuTemplate = file_get_contents(dirname(__DIR__, 2) . '/modules/dbxMenu/tpl/htm/dbx-top-main.htm');
$utilitiesSource = file_get_contents(dirname(__DIR__, 2) . '/js/lib/utilities.js');
if (!is_string($menuSource)
   || strpos($menuSource, 'render_design_skin_menu') === false
   || strpos($menuSource, 'get_design_skin_ids') === false
   || strpos($menuSource, "'dbx_design' => \$design") === false
   || strpos($menuSource, "'dbx_color'  =>") === false) {
   $fail('dbxMenu rendert die gruppierte GET-Auswahl nicht aus dem zentralen Skin-Katalog.', 5);
}
if (!is_string($menuTemplate)
   || strpos($menuTemplate, '{design_skin_menu}') === false
   || strpos($menuTemplate, '{design_menu}') !== false
   || strpos($menuTemplate, '{skin_menu}') !== false) {
   $fail('Das Hauptmenue verwendet nicht ausschliesslich die gruppierte Design-/Skin-Ausgabe.', 6);
}
if (!is_string($utilitiesSource)
   || strpos($utilitiesSource, 'DESIGN_SKINS') !== false
   || strpos($utilitiesSource, 'ALL_SKINS') !== false
   || strpos($utilitiesSource, 'dbx-design-skin-opt[data-design][data-skin]') === false
   || strpos($utilitiesSource, 'skinStoreKey()') === false) {
   $fail('utilities.js verwendet noch eine globale Skin-Liste oder keinen designspezifischen Speicher.', 7);
}

echo "OK dbx design skins\n";
