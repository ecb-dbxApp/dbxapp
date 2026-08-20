<?php

declare(strict_types=1);

$module = dirname(__DIR__);
$root = dirname(__DIR__, 4);
$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

foreach (array(
    'dbxChangeLog_admin.class.php',
    'include/dbxChangeLogService.class.php',
    'include/dbxChangeLogWriter.class.php',
    'dd/dbxChangeLog.dd.php',
    'fd/change-log-form.fd.php',
    'fd/rpt-change-log-selection.fd.php',
    'tpl/htm/change-log-form.htm',
    'tpl/htm/change-log-report.htm',
    'tpl/htm/change-log-resources-report.htm',
    'tools/write-change-log.php',
) as $relative) {
    $assert(is_file($module . '/' . $relative), 'Modulressource fehlt: ' . $relative);
}

$dd = (string)file_get_contents($module . '/dd/dbxChangeLog.dd.php');
foreach (array(
    "\$table['server']='dbxChangeLog_admin|dbxChangeLog.db3'",
    "\$table['table']='dbxChangeLog'",
    "\$field['name']='change_date'",
    "\$field['name']='summary'",
    "\$field['name']='resources'",
) as $needle) {
    $assert(str_contains($dd, $needle), 'DD-Vertrag fehlt: ' . $needle);
}
$assert(str_contains($dd, "\$table['read']='*'"), 'Change-Log-Lesen ist nicht öffentlich freigegeben.');
$assert(str_contains($dd, "\$table['read_owner']='*'"), 'Change-Log-Lesen für Besitzer ist nicht öffentlich freigegeben.');
foreach (array('create', 'update', 'delete', 'create_owner', 'update_owner', 'delete_owner') as $mode) {
    $assert(str_contains($dd, "\$table['" . $mode . "']='admin'"), 'Admin-Schreibrecht fehlt: ' . $mode);
}

$config = (string)file_get_contents($module . '/cfg/config.php');
$assert(str_contains($config, "\$config['groups']='*'"), 'Der Modulaufruf ist nicht öffentlich freigegeben.');

$service = (string)file_get_contents($module . '/include/dbxChangeLogService.class.php');
$assert(str_contains($service, "->set_pagination(true, 7)"), 'Report verwendet nicht die einheitliche Pagination.');
$assert(str_contains($service, "dbx()->has_group('admin')"), 'Admin-Prüfung für Änderungen fehlt.');
$assert(str_contains($service, "->set_table_actions(\$can_manage ? array('edit', 'delete') : array())"), 'Bearbeiten/Löschen ist nicht auf Admins begrenzt.');
$assert(substr_count($service, "if (!\$this->can_manage())") >= 2, 'Direkte Formular- oder Aktionsaufrufe sind nicht abgesichert.');
$assert(str_contains($service, "->set_report_counts(\$filtered_count, \$total_count)"), 'Gesamt- und Filterzahl sind nicht getrennt.');
$assert(str_contains($service, "'dbxChangeLog_admin|change-log-report'"), 'Zweistufiges Reporttemplate fehlt.');
$assert(str_contains($service, 'change_log_report_next_record'), 'Hauptreport erzeugt keinen Ressourcenaufruf.');
$assert(str_contains($service, 'public function resources('), 'Ressourcen-Subreport fehlt.');
$assert(str_contains($service, "'change-log-resources-' . \$change_log_id"), 'Ressourcen-Subreport besitzt keinen stabilen UI-State-Schluessel.');
$resource_template = (string)file_get_contents($module . '/tpl/htm/change-log-resources-report.htm');
$assert(str_contains($resource_template, 'is-collapsed'), 'Ressourcen-Subreport ist nicht standardmaessig geschlossen.');
$assert(str_contains($resource_template, 'data-collapse-toggle="{resource_panel_key}"'), 'Ressourcen-Subreport verwendet nicht den zentralen Klappmechanismus.');
$assert(str_contains($resource_template, 'data-collapse-state-key="{resource_panel_key}"'), 'Ressourcen-Subreport merkt seinen UI-State nicht.');
$row_template = (string)file_get_contents($root . '/dbx/modules/dbx/tpl/htm/table_row_col.htm');
$assert(str_contains($row_template, 'data-report-label="{label}"'), 'dbxReport stellt keine Feldlabels fuer mobile Karten bereit.');
$main_template = (string)file_get_contents($module . '/tpl/htm/change-log-report.htm');
$assert(str_contains($main_template, 'dbx-report-mobile-cards'), 'Change Log aktiviert keine mobile Kartenansicht.');

$report_css = (string)file_get_contents($root . '/dbx/design/dbxapp/css/c-report.css');
$assert(
    preg_match('/\.dbxReport \.dbx_pagination\s*\{[^}]*justify-content:\s*flex-end/s', $report_css) === 1,
    'Standardpagination ist nicht rechtsbündig.'
);

$ki = (string)file_get_contents($root . '/dbx/modules/dbxKi/include/dbxKiModuleBriefingService.class.php');
$ki_writer = (string)file_get_contents($root . '/dbx/modules/dbxKi/include/dbxKiChangeLogService.class.php');
$assert(str_contains($ki, "'change_log' => array('type' => 'array', 'required' => true)"), 'dbxKi-Antwortvertrag verlangt kein Change Log.');
$assert(str_contains($ki_writer, 'write_change_log'), 'Zentrale dbxKi-Schreibfunktion fehlt.');
$assert(str_contains((string)file_get_contents($root . '/AGENTS.md'), 'write-change-log.php'), 'Verbindliche Codex-Regel fehlt.');
$tool = (string)file_get_contents($module . '/tools/write-change-log.php');
$assert(str_contains($tool, "get_include_obj('dbxChangeLogWriter'"), 'Change-Log-Tool delegiert nicht an den dbxDB-basierten Writer.');

if ($failures) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK dbxChangeLog contract\n";
