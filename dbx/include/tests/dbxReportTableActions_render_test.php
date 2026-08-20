<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/dbxKernel.php';

dbx()->set_system_var('dbx_modul', 'dbx');
dbx()->set_system_var('dbx_activ_modul', 'dbx');
dbx()->set_system_var('dbx_run1', 'test');
dbx()->set_system_var('dbx_run2', '');
dbx()->set_system_var('dbx_lng', 'de');
dbx()->get_system_obj('dbxReport', 'load');

class dbxReportTableActionsRenderFixture extends dbxReport {
    public function actionState(): array {
        return array(
            'select' => (int)$this->_create_row_select,
            'edit' => (int)$this->_create_row_edit,
            'delete' => (int)$this->_create_row_delete,
            'show' => (int)$this->_create_row_show,
            'print' => (int)$this->_create_row_print,
        );
    }

    public function renderAction(
        string $type,
        array $record = array('id' => 17),
        array $overrides = array()
    ): string {
        $data = $this->get_table_row_action_data($type, $record);
        $data = array_replace($data, $overrides);
        $data = $this->prepare_table_row_action_data($type, $data);
        return $this->render_simple_table_tpl('table_row_action', $data);
    }

    public function renderHeader(string $type): string {
        $data = $this->prepare_table_header_action_data($type, array());
        return $this->render_simple_table_tpl('table_header_action', $data);
    }
}

$report = new dbxReportTableActionsRenderFixture();
$failures = array();
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$profile = new dbxReportTableActionsRenderFixture();
$profile->set_table_actions(array('select', 'edit' => array('window' => true, 'width' => '900'), 'delete' => false));
$check(
    $profile->actionState() === array('select' => 1, 'edit' => 1, 'delete' => 0, 'show' => 0, 'print' => 0),
    'Deklaratives Aktionsprofil setzt oder deaktiviert Aktionen nicht korrekt.'
);

$edit = $report->renderAction('edit');
$check(str_contains($edit, 'dbx-report-action-cell'), 'Einheitliche Aktionszelle fehlt.');
$check(str_contains($edit, 'bi-pencil'), 'Edit-Icon fehlt.');
$check(str_contains($edit, 'data-dbx-tooltip='), 'Edit-Tooltip fehlt.');
$check(!str_contains($edit, 'dbx-win'), 'Normales Edit oeffnet unerwartet ein Fenster.');

$report->set_table_action_options('edit', array('window' => true, 'width' => '900'));
$edit_window = $report->renderAction('edit');
$check(str_contains($edit_window, 'dbx-win'), 'Konfiguriertes Edit-Fenster fehlt.');
$check(str_contains($edit_window, 'data-width="900"'), 'Konfigurierte Fensterbreite fehlt.');

$callback_window = (new dbxReportTableActionsRenderFixture())->renderAction(
    'show',
    array('id' => 18),
    array('class' => 'openWin dbx-win disabled')
);
$check(str_contains($callback_window, 'openWin dbx-win disabled'), 'Dynamische Callback-Klassen fehlen.');
$check(str_contains($callback_window, 'data-url='), 'Callback-Fenster erhaelt keine Fensterparameter.');

$delete = $report->renderAction('delete');
$check(str_contains($delete, 'dbxAjax dbxConfirm'), 'Loeschen ist nicht bestaetigungspflichtig.');
$check(str_contains($delete, 'data-confirm-buttons="yesno"'), 'Loeschdialog ohne Ja/Nein-Vertrag.');
$check(str_contains($delete, 'dbx_do=row_delete'), 'Loeschaktion wurde nicht zentral erzeugt.');

$export = $report->renderAction('export');
$check(str_contains($export, 'dbx-win'), 'CSV-Export oeffnet nicht im Fenster-Manager.');
$check(str_contains($export, 'data-width="700"'), 'Standardbreite des CSV-Fensters fehlt.');
$check(str_contains($report->renderHeader('print'), 'bi-printer'), 'Print-Header verwendet nicht das zentrale Icon.');

foreach (array($edit, $edit_window, $callback_window, $delete, $export) as $html) {
    $check(!preg_match('/\{(?:link_|accessible_|cell_|header_|icon|href)/', $html), 'Unaufgeloester Aktionstoken im HTML.');
}

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK universelle Reportaktionen werden vollstaendig und sicher gerendert.\n";
