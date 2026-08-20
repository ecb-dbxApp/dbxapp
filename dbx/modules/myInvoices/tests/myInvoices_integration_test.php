<?php
/**
 * Laufzeit- und Transaktionstest des ausführbaren Referenzmoduls.
 */

$base = dirname(__DIR__, 4);
chdir($base);
$_SERVER['REQUEST_URI'] =
    '/dbxapp/?dbx_modul=myInvoices&dbx_run1=report';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SCRIPT_NAME'] = '/dbxapp/index.php';
$_SERVER['REQUEST_METHOD'] = 'GET';

if (!defined('dbxSystem')) {
    define('dbxSystem', 'dbxWebApp');
}
if (!defined('dbxRunAsAdmin')) {
    define('dbxRunAsAdmin', 1);
}

require $base . '/dbx/vendor/autoload.php';
require_once $base . '/dbx/include/dbxKernel.php';

$fail = function (string $message, int $code): void {
    fwrite(STDERR, "FAIL: $message\n");
    exit($code);
};

dbx()->set_system_var('dbx_activ_modul', 'myInvoices');
dbx()->set_system_var('dbx_master_modul', 'myInvoices');
dbx()->set_system_var('dbx_activ_modul_id', 1);
dbx()->set_system_var('dbx_activ_action', 'report');
dbx()->set_modul_var('dbx_modul', 'myInvoices');
dbx()->set_modul_var('dbx_run1', 'report');

$fixtures = dbx()->get_include_obj(
    'myInvoicesFixtures',
    'myInvoices'
);
$installed = $fixtures->install(true);
if ((int)($installed['ok'] ?? 0) !== 1) {
    $fail(
        'Installation fehlgeschlagen: '
        . (string)($installed['message'] ?? ''),
        1
    );
}

$db = dbx()->get_system_obj('dbxDB');
$invoice_dd = 'myInvoices|invoice';
$item_dd = 'myInvoices|invoiceItem';

$demo = $db->select1(
    $invoice_dd,
    array('invoice_no' => 'DBX-DEMO-1001')
);
$demo_id = (int)($demo['id'] ?? 0);
if ($demo_id <= 0) {
    $fail('Demo-Rechnung fehlt.', 2);
}
foreach (array(
    'create_date',
    'create_uid',
    'update_date',
    'update_uid',
    'owner',
) as $system_field) {
    if ((string)($demo[$system_field] ?? '') === '') {
        $fail(
            'Automatisches dbxDB-Systemfeld fehlt: ' . $system_field,
            3
        );
    }
}

$items = $db->select(
    $item_dd,
    array('invoice_id' => $demo_id),
    '*',
    'position_no',
    'ASC'
);
if (!is_array($items) || count($items) !== 2) {
    $fail('Demo-Positionen sind nicht vollständig.', 4);
}

$calculated_cents = 0;
foreach ($items as $item) {
    $calculated_cents += (int)round(
        (float)$item['quantity']
        * (float)$item['unit_price']
        * 100
    );
}
if ($calculated_cents !== 4730
    || (int)round((float)$demo['total_gross'] * 100) !== 4730
) {
    $fail('Snapshot und Positionssumme weichen ab.', 5);
}

$service = dbx()->get_include_obj(
    'myInvoicesService',
    'myInvoices'
);
dbx()->set_modul_var('invoice_id', $demo_id);
$positions_html = $service->positions();
foreach (array(
    'Positionen zu Rechnung DBX-DEMO-1001',
    '39,80 EUR',
    '7,50 EUR',
    '47,30 EUR',
    'Endsumme der Positionen',
) as $expected) {
    if (strpos($positions_html, $expected) === false) {
        $fail(
            'Positionsreport fehlt: ' . $expected
            . ' | HTML: ' . substr(
                preg_replace('/\s+/', ' ', $positions_html),
                0,
                500
            ),
            6
        );
    }
}

$report_html = $service->report();
foreach (array(
    'DBX-DEMO-1001',
    'Endsumme der angezeigten Rechnungen',
    '[modul=myInvoices]dbx_run1=positions&invoice_id=',
    'dbxConfirm',
    'dbx_token=',
) as $expected) {
    if (strpos($report_html, $expected) === false) {
        $fail('Rechnungsreport fehlt: ' . $expected, 7);
    }
}

$web_app = dbx()->get_system_obj('dbxWebApp');
$delete_url = dbx()->action_url(
    '?dbx_modul=myInvoices&dbx_run1=delete&rid=' . $demo_id
);
$delete_route = $web_app->action_policy_for_url($delete_url);
parse_str((string)parse_url($delete_url, PHP_URL_QUERY), $delete_params);
$delete_token = (string)($delete_params['dbx_token'] ?? '');

if (!$delete_route
    || (string)($delete_route['source'] ?? '') !== 'automatic'
    || (string)($delete_route['action'] ?? '') !== 'dbxAction.delete'
    || (string)($delete_route['bindings']['rid'] ?? '') !== (string)$demo_id
    || !dbx()->check_action_token(
        (string)($delete_route['scope'] ?? ''),
        $delete_token
    )
) {
    $fail('Zentrale Action-Policy akzeptiert gültige Route nicht.', 8);
}

$tampered_route = $web_app->action_policy_for_url(
    '?dbx_modul=myInvoices&dbx_run1=delete&rid=' . ($demo_id + 1)
);
if (!$tampered_route
    || dbx()->check_action_token(
        (string)($tampered_route['scope'] ?? ''),
        $delete_token
    )
) {
    $fail('Zentrale Action-Policy bindet die Rechnungs-ID nicht.', 8);
}

$test_no = 'DBX-TEST-' . date('YmdHis') . '-' . random_int(1000, 9999);
$inserted = $db->insert($invoice_dd, array(
    'invoice_no' => $test_no,
    'invoice_date' => '2026-07-20',
    'customer' => 'Transaktionstest',
    'status' => 'draft',
    'total_gross' => '10.00',
));
$test_id = (int)$db->_insert_id;
if ($inserted !== 1 || $test_id <= 0) {
    $fail('Temporäre Rechnung konnte nicht angelegt werden.', 9);
}
$inserted = $db->insert($item_dd, array(
    'invoice_id' => $test_id,
    'position_no' => 10,
    'article_no' => 'TEST-ROLLBACK',
    'description' => 'Rollback-Position',
    'quantity' => '1.00',
    'unit_price' => '10.00',
));
if ($inserted !== 1) {
    $db->delete($invoice_dd, array('id' => $test_id));
    $fail('Temporäre Position konnte nicht angelegt werden.', 10);
}

if ($db->begin($invoice_dd) !== 1) {
    $fail('Rollback-Testtransaktion konnte nicht starten.', 11);
}
$first_delete = $db->delete(
    $item_dd,
    array('invoice_id' => $test_id)
);
$second_delete = $db->delete(
    $invoice_dd,
    array('id' => -987654321)
);
if ($first_delete !== 1 || $second_delete !== 0) {
    $db->rollback($invoice_dd);
    $fail('Fehlerpfad der Löschtransaktion wurde nicht erreicht.', 12);
}
$db->rollback($invoice_dd);
if ((int)$db->count(
    $invoice_dd,
    array('id' => $test_id)
) !== 1
    || (int)$db->count(
        $item_dd,
        array('invoice_id' => $test_id)
    ) !== 1
) {
    $fail('Rollback hat Kopf oder Position nicht wiederhergestellt.', 13);
}

dbx()->set_modul_var('rid', $test_id);
$deleted_html = $service->delete();
$remaining_invoices = (int)$db->count(
    $invoice_dd,
    array('id' => $test_id)
);
$remaining_items = (int)$db->count(
        $item_dd,
        array('invoice_id' => $test_id)
    );
if ($remaining_invoices !== 0
    || $remaining_items !== 0
    || strpos(
        $deleted_html,
        'Rechnung und Positionen wurden gelöscht'
    ) === false
) {
    $fail(
        'Gültige atomare Löschung ist fehlgeschlagen. Rechnungen='
        . $remaining_invoices . ', Positionen=' . $remaining_items
        . ', HTML=' . substr((string)preg_replace('/\s+/', ' ', $deleted_html), 0, 300),
        14
    );
}

echo "OK myInvoices runtime, sums, central policy precondition and rollback\n";
