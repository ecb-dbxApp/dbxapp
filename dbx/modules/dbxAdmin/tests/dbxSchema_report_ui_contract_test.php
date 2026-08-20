<?php

declare(strict_types=1);

/**
 * UI-Vertrag fuer die Modulfilter und sichtbaren Kernspalten der Schema-Reports.
 */

function schema_report_ui_assert(bool $condition, string $message): void
{
   if (!$condition) {
      throw new RuntimeException($message);
   }
}

$root = dirname(__DIR__, 4);
require_once $root . '/dbx/include/tests/dbxModuleSourceBundle.php';

$module_root = dirname(__DIR__);
$source = dbx_test_module_source_bundle($module_root . '/include/dbxSchema.class.php');

foreach (array('dbxContent', 'dbxContent_admin') as $module) {
   schema_report_ui_assert(
      is_dir(dirname($module_root) . '/' . $module),
      'Testvoraussetzung fehlt: Modul ' . $module
   );
}

$module_options = dbx_test_module_method_source($source, 'get_module_options');
schema_report_ui_assert(
   $module_options !== '',
   'Moduloptionen des Schema-Reports wurden nicht gefunden.'
);
schema_report_ui_assert(
   str_contains($module_options, "glob(\$base, GLOB_ONLYDIR)")
      && !str_contains($module_options, "is_dir(\$dir . '/dd')"),
   'Die Modulauswahl muss alle installierten Module und nicht nur Module mit DD-Ordner enthalten.'
);

$filter_options = dbx_test_module_method_source($source, 'get_module_filter_options');
schema_report_ui_assert(
   $filter_options !== '',
   'Modulfilter des Schema-Reports wurde nicht gefunden.'
);
schema_report_ui_assert(
   str_contains($filter_options, '$options = $this->get_module_options();'),
   'DB- und DD-Filter muessen mit dem vollstaendigen Modulbestand beginnen.'
);

$report_dd = dbx_test_module_method_source($source, 'report_dd');
schema_report_ui_assert(
   $report_dd !== '',
   'DD-Report wurde nicht gefunden.'
);
$dd_column_block_start = strpos($report_dd, '$flds = array(');
$dd_column_block_end = strpos($report_dd, ');', $dd_column_block_start);
schema_report_ui_assert(
   $dd_column_block_start !== false && $dd_column_block_end !== false,
   'DD-Spaltendefinition wurde nicht gefunden.'
);
$dd_columns = substr($report_dd, $dd_column_block_start, $dd_column_block_end - $dd_column_block_start);

$dd_position = strpos($dd_columns, "'dd'");
$status_position = strpos($dd_columns, "'sync'");
schema_report_ui_assert(
   $dd_position !== false && $status_position !== false && $status_position > $dd_position,
   'Der Status muss im DD-Report direkt im sichtbaren Kernbereich stehen.'
);
schema_report_ui_assert(
   !str_contains($dd_columns, "'path'"),
   'Die Pfadspalte darf im DD-Report nicht mehr ausgegeben werden.'
);

echo "OK schema report module filters and DD columns.\n";
