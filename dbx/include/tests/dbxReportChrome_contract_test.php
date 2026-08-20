<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once __DIR__ . '/dbxModuleSourceBundle.php';
$report_source = dbx_test_module_source_bundle($root . '/dbx/include/dbxReport.class.php');
$default_tpl = (string)file_get_contents($root . '/dbx/modules/dbx/tpl/htm/report-default.htm');
$default_chrome = $default_tpl . (string)file_get_contents(
    $root . '/dbx/modules/dbx/tpl/htm/frame-report-head.htm'
);
$failures = array();
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

foreach (array('_tpl_report', '_tpl_report_bar', '_tpl_report_footer') as $property) {
    $check(str_contains($report_source, $property), 'Report-Property fehlt: ' . $property);
}
foreach (array('set_report_tpl', 'set_report_bar_tpl', 'set_report_bar_fields', 'set_report_footer_tpl', 'prepare_report_chrome_replaces') as $method) {
    $check(str_contains($report_source, 'function ' . $method), 'Report-API fehlt: ' . $method);
}

$bar_tpl = (string)file_get_contents($root . '/dbx/modules/dbx/tpl/htm/report-bar-default.htm');
$pagination_tpl = (string)file_get_contents($root . '/dbx/modules/dbx/tpl/htm/pagination.htm');
$check(str_contains($bar_tpl, '{report:filters}'), 'Dynamischer Filter-Platzhalter der Reportleiste fehlt.');
$check(str_contains($bar_tpl, '{report_filter_action}'), 'Dynamischer Aktions-Platzhalter der Reportleiste fehlt.');
$check(!str_contains($bar_tpl, '{obj:dbx_rwhere}'), 'Standardleiste verdrahtet die Suche weiterhin fest.');
$check(!str_contains($pagination_tpl, 'data-report-count='), 'Pagination wiederholt die zentralen Report-Zaehler.');
$check(
    str_contains($report_source, 'return $this->_tpl_report;'),
    'Eine Report-ID ohne Template verwendet nicht das Standard-Reporttemplate.'
);
$check(
    str_contains($default_tpl, 'dbx-report-mobile-cards'),
    'Das Standard-Reporttemplate aktiviert keine mobile Kartenansicht.'
);
$row_tpl = (string)file_get_contents($root . '/dbx/modules/dbx/tpl/htm/table_row_col.htm');
$check(
    str_contains($row_tpl, 'data-report-label="{label}"'),
    'Standard-Reportzellen stellen das mobile Feldlabel nicht bereit.'
);

foreach (array('dbxapp', 'steal', 'flowers') as $design) {
    $report_css = (string)file_get_contents($root . '/dbx/design/' . $design . '/css/c-report.css');
    $check(
        str_contains($report_css, '.dbx-report-mobile-cards'),
        $design . ': mobile Kartenregeln fuer Standardreports fehlen.'
    );
    $check(
        str_contains($report_css, '.dbx-report-bar-stats'),
        $design . ': mobile Anordnung der Reportleiste fehlt.'
    );
}

foreach (array('{report:bar}', '{report:message}', '{report:footer}') as $placeholder) {
    $check(
        str_contains($default_chrome, $placeholder),
        'Zentraler Report-Platzhalter fehlt: ' . $placeholder
    );
}

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
    $root . '/dbx/modules',
    FilesystemIterator::SKIP_DOTS
));
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'htm') continue;
    $content = (string)file_get_contents($file->getPathname());
    $check(!str_contains($content, '[tpl=dbx|report-form-select]'), 'Veralteter Report-Wrapper: ' . $file->getPathname());
    $check(!str_contains($content, '[tpl=dbx|report_bar]'), 'Veraltete Report-Bar: ' . $file->getPathname());
}

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK einheitlicher Report-Vertrag mit austauschbarem Standardtemplate, Bar, Message und Footer.\n";
