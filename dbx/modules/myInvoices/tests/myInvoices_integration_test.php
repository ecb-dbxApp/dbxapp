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
$invoiceDd = 'myInvoices|invoice';
$itemDd = 'myInvoices|invoiceItem';

$demo = $db->select1(
    $invoiceDd,
    array('invoice_no' => 'DBX-DEMO-1001')
);
$demoId = (int)($demo['id'] ?? 0);
if ($demoId <= 0) {
    $fail('Demo-Rechnung fehlt.', 2);
}
foreach (array(
    'create_date',
    'create_uid',
    'update_date',
    'update_uid',
    'owner',
) as $systemField) {
    if ((string)($demo[$systemField] ?? '') === '') {
        $fail(
            'Automatisches dbxDB-Systemfeld fehlt: ' . $systemField,
            3
        );
    }
}

$items = $db->select(
    $itemDd,
    array('invoice_id' => $demoId),
    '*',
    'position_no',
    'ASC'
);
if (!is_array($items) || count($items) !== 2) {
    $fail('Demo-Positionen sind nicht vollständig.', 4);
}

$calculatedCents = 0;
foreach ($items as $item) {
    $calculatedCents += (int)round(
        (float)$item['quantity']
        * (float)$item['unit_price']
        * 100
    );
}
if ($calculatedCents !== 4730
    || (int)round((float)$demo['total_gross'] * 100) !== 4730
) {
    $fail('Snapshot und Positionssumme weichen ab.', 5);
}

$service = dbx()->get_include_obj(
    'myInvoicesService',
    'myInvoices'
);
dbx()->set_modul_var('invoice_id', $demoId);
$positionsHtml = $service->positions();
foreach (array(
    'Positionen zu Rechnung DBX-DEMO-1001',
    '39,80 EUR',
    '7,50 EUR',
    '47,30 EUR',
    'Endsumme der Positionen',
) as $expected) {
    if (strpos($positionsHtml, $expected) === false) {
        $fail(
            'Positionsreport fehlt: ' . $expected
            . ' | HTML: ' . substr(
                preg_replace('/\s+/', ' ', $positionsHtml),
                0,
                500
            ),
            6
        );
    }
}

$reportHtml = $service->report();
foreach (array(
    'DBX-DEMO-1001',
    'Endsumme der angezeigten Rechnungen',
    '[modul=myInvoices]dbx_run1=positions&invoice_id=',
    'dbxConfirm',
    'dbx_token=',
) as $expected) {
    if (strpos($reportHtml, $expected) === false) {
        $fail('Rechnungsreport fehlt: ' . $expected, 7);
    }
}

$webApp = dbx()->get_system_obj('dbxWebApp');
$deleteUrl = dbx()->action_url(
    '?dbx_modul=myInvoices&dbx_run1=delete&rid=' . $demoId
);
$deleteRoute = $webApp->action_policy_for_url($deleteUrl);
parse_str((string)parse_url($deleteUrl, PHP_URL_QUERY), $deleteParams);
$deleteToken = (string)($deleteParams['dbx_token'] ?? '');

if (!$deleteRoute
    || (string)($deleteRoute['source'] ?? '') !== 'automatic'
    || (string)($deleteRoute['action'] ?? '') !== 'dbxAction.delete'
    || (string)($deleteRoute['bindings']['rid'] ?? '') !== (string)$demoId
    || !dbx()->check_action_token(
        (string)($deleteRoute['scope'] ?? ''),
        $deleteToken
    )
) {
    $fail('Zentrale Action-Policy akzeptiert gültige Route nicht.', 8);
}

$tamperedRoute = $webApp->action_policy_for_url(
    '?dbx_modul=myInvoices&dbx_run1=delete&rid=' . ($demoId + 1)
);
if (!$tamperedRoute
    || dbx()->check_action_token(
        (string)($tamperedRoute['scope'] ?? ''),
        $deleteToken
    )
) {
    $fail('Zentrale Action-Policy bindet die Rechnungs-ID nicht.', 8);
}

$testNo = 'DBX-TEST-' . date('YmdHis') . '-' . random_int(1000, 9999);
$inserted = $db->insert($invoiceDd, array(
    'invoice_no' => $testNo,
    'invoice_date' => '2026-07-20',
    'customer' => 'Transaktionstest',
    'status' => 'draft',
    'total_gross' => '10.00',
));
$testId = (int)$db->_insert_id;
if ($inserted !== 1 || $testId <= 0) {
    $fail('Temporäre Rechnung konnte nicht angelegt werden.', 9);
}
$inserted = $db->insert($itemDd, array(
    'invoice_id' => $testId,
    'position_no' => 10,
    'article_no' => 'TEST-ROLLBACK',
    'description' => 'Rollback-Position',
    'quantity' => '1.00',
    'unit_price' => '10.00',
));
if ($inserted !== 1) {
    $db->delete($invoiceDd, array('id' => $testId));
    $fail('Temporäre Position konnte nicht angelegt werden.', 10);
}

if ($db->begin($invoiceDd) !== 1) {
    $fail('Rollback-Testtransaktion konnte nicht starten.', 11);
}
$firstDelete = $db->delete(
    $itemDd,
    array('invoice_id' => $testId)
);
$secondDelete = $db->delete(
    $invoiceDd,
    array('id' => -987654321)
);
if ($firstDelete !== 1 || $secondDelete !== 0) {
    $db->rollback($invoiceDd);
    $fail('Fehlerpfad der Löschtransaktion wurde nicht erreicht.', 12);
}
$db->rollback($invoiceDd);
if ((int)$db->count(
    $invoiceDd,
    array('id' => $testId)
) !== 1
    || (int)$db->count(
        $itemDd,
        array('invoice_id' => $testId)
    ) !== 1
) {
    $fail('Rollback hat Kopf oder Position nicht wiederhergestellt.', 13);
}

dbx()->set_modul_var('rid', $testId);
$deletedHtml = $service->delete();
if ((int)$db->count(
    $invoiceDd,
    array('id' => $testId)
) !== 0
    || (int)$db->count(
        $itemDd,
        array('invoice_id' => $testId)
    ) !== 0
    || strpos(
        $deletedHtml,
        'Rechnung und Positionen wurden gelöscht'
    ) === false
) {
    $fail('Gültige atomare Löschung ist fehlgeschlagen.', 14);
}

echo "OK myInvoices runtime, sums, central policy precondition and rollback\n";
