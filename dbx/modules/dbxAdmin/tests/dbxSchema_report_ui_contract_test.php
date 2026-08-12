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

$moduleRoot = dirname(__DIR__);
$source = dbx_test_module_source_bundle($moduleRoot . '/include/dbxSchema.class.php');

foreach (array('dbxContent', 'dbxContent_admin') as $module) {
   schema_report_ui_assert(
      is_dir(dirname($moduleRoot) . '/' . $module),
      'Testvoraussetzung fehlt: Modul ' . $module
   );
}

$moduleOptions = dbx_test_module_method_source($source, 'get_module_options');
schema_report_ui_assert(
   $moduleOptions !== '',
   'Moduloptionen des Schema-Reports wurden nicht gefunden.'
);
schema_report_ui_assert(
   str_contains($moduleOptions, "glob(\$base, GLOB_ONLYDIR)")
      && !str_contains($moduleOptions, "is_dir(\$dir . '/dd')"),
   'Die Modulauswahl muss alle installierten Module und nicht nur Module mit DD-Ordner enthalten.'
);

$filterOptions = dbx_test_module_method_source($source, 'get_module_filter_options');
schema_report_ui_assert(
   $filterOptions !== '',
   'Modulfilter des Schema-Reports wurde nicht gefunden.'
);
schema_report_ui_assert(
   str_contains($filterOptions, '$options = $this->get_module_options();'),
   'DB- und DD-Filter muessen mit dem vollstaendigen Modulbestand beginnen.'
);

$reportDd = dbx_test_module_method_source($source, 'report_dd');
schema_report_ui_assert(
   $reportDd !== '',
   'DD-Report wurde nicht gefunden.'
);
$ddColumnBlockStart = strpos($reportDd, '$flds = array(');
$ddColumnBlockEnd = strpos($reportDd, ');', $ddColumnBlockStart);
schema_report_ui_assert(
   $ddColumnBlockStart !== false && $ddColumnBlockEnd !== false,
   'DD-Spaltendefinition wurde nicht gefunden.'
);
$ddColumns = substr($reportDd, $ddColumnBlockStart, $ddColumnBlockEnd - $ddColumnBlockStart);

$ddPosition = strpos($ddColumns, "'dd'");
$statusPosition = strpos($ddColumns, "'sync'");
schema_report_ui_assert(
   $ddPosition !== false && $statusPosition !== false && $statusPosition > $ddPosition,
   'Der Status muss im DD-Report direkt im sichtbaren Kernbereich stehen.'
);
schema_report_ui_assert(
   !str_contains($ddColumns, "'path'"),
   'Die Pfadspalte darf im DD-Report nicht mehr ausgegeben werden.'
);

echo "OK schema report module filters and DD columns.\n";
