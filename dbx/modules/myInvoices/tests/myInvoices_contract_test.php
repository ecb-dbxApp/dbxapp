<?php
/**
 * Statischer Architekturvertrag des ausführbaren Referenzmoduls.
 */

$module = dirname(__DIR__);
$fail = function (string $message, int $code): void {
    fwrite(STDERR, "FAIL: $message\n");
    exit($code);
};

$required = array(
    'myInvoices.class.php',
    'cfg/config.php',
    'dd/invoice.dd.php',
    'dd/invoiceItem.dd.php',
    'fd/invoice-form.fd.php',
    'fd/rpt-invoice-selection.fd.php',
    'include/myInvoicesFixtures.class.php',
    'include/myInvoicesService.class.php',
    'tpl/htm/invoice-form.htm',
    'tpl/htm/invoice-report.htm',
    'tpl/htm/invoice-items-report.htm',
    'tpl/htm/invoice-row-action.htm',
    'tpl/htm/invoice-install.htm',
    'tools/install_demo.php',
    'tests/myInvoices_integration_test.php',
    'README.md',
);
foreach ($required as $relative) {
    if (!is_file($module . '/' . $relative)) {
        $fail('Datei fehlt: ' . $relative, 1);
    }
}

foreach (array('dd/invoice.dd.php', 'dd/invoiceItem.dd.php') as $relative) {
    $source = (string)file_get_contents($module . '/' . $relative);
    if (!preg_match('/\$table\[\'trace\'\]\s*=\s*\'0\';/', $source)) {
        $fail($relative . ': produktives DD muss trace=0 verwenden.', 2);
    }
    if (strpos($source, '$addField') !== false) {
        $fail($relative . ': DD-Felder muessen explizit definiert sein.', 2);
    }
    foreach (array(
        'TABLE',
        'FIELDS',
        'INDEXES',
        "\$field['tooltip']",
        "\$field['js']",
        "\$field['prompt']",
        '$fields[]=$field;',
        '$indexes[]=$index;',
    ) as $requiredDdPart) {
        if (strpos($source, $requiredDdPart) === false) {
            $fail(
                $relative . ': expliziter DD-Bestandteil fehlt: '
                . $requiredDdPart,
                2
            );
        }
    }
}

$phpSources = '';
foreach (array(
    'myInvoices.class.php',
    'include/myInvoicesFixtures.class.php',
    'include/myInvoicesService.class.php',
) as $relative) {
    $source = (string)file_get_contents($module . '/' . $relative);
    $phpSources .= "\n" . $source;
    try {
        token_get_all($source, TOKEN_PARSE);
    } catch (ParseError $exception) {
        $fail($relative . ': ' . $exception->getMessage(), 3);
    }
}

foreach (array(
    '/\bPDO\b/',
    '/\bmysqli?_/',
    '/->(?:query|exec|prepare)\s*\(/',
    '/\b(?:SELECT|INSERT|UPDATE|DELETE)\s+(?:FROM|INTO|SET)\b/i',
) as $forbidden) {
    if (preg_match($forbidden, $phpSources)) {
        $fail('Direkter Datenbankzugriff gefunden: ' . $forbidden, 4);
    }
}

if (preg_match(
    '/private function (?:db|tpl|form|report)\s*\([^)]*\)\s*\{'
    . '\s*return\s+dbx\(\)->get_(?:system|include)_obj/s',
    $phpSources
)) {
    $fail('Unnötiger dbx-Wrapper gefunden.', 5);
}

$fixtureSource = (string)file_get_contents(
    $module . '/include/myInvoicesFixtures.class.php'
);
foreach (array(
    'create_date',
    'create_uid',
    'update_date',
    'update_uid',
    'owner',
) as $systemField) {
    if (preg_match(
        '/[\'"]' . preg_quote($systemField, '/') . '[\'"]\s*=>/',
        $fixtureSource
    )) {
        $fail(
            'Fixture setzt dbxDB-Systemfeld manuell: ' . $systemField,
            6
        );
    }
}

$service = (string)file_get_contents(
    $module . '/include/myInvoicesService.class.php'
);
foreach (array(
    "get_system_obj('dbxDB')",
    "get_system_obj('dbxForm')",
    "get_system_obj('dbxReport')",
    "get_system_obj('dbxTPL')",
    'invoice_report_next_record',
    'invoice_items_report_next_record',
    "add_rep(\n            'report_total'",
    '[modul=myInvoices]dbx_run1=positions&invoice_id=',
    'action_url',
    '->begin(self::INVOICE_DD)',
    '->commit(self::INVOICE_DD)',
    '->rollback(self::INVOICE_DD)',
) as $requiredSource) {
    if (strpos($service, $requiredSource) === false) {
        $fail('Servicevertrag fehlt: ' . $requiredSource, 7);
    }
}

$config = (string)file_get_contents($module . '/cfg/config.php');
if (strpos($config, 'action_routes') !== false) {
    $fail('Automatisch erkennbare RID-Aktion ist noch manuell konfiguriert.', 8);
}
if (strpos($service, 'check_action_token') !== false
    || strpos($service, 'action_token(') !== false
) {
    $fail('Service verwaltet Action-Tokens noch manuell.', 8);
}
foreach (array(
    'set_callback_owner(',
    'set_next_record_callback(',
    'set_footer_callback(',
    'renderInvoiceFooter',
    'renderItemFooter',
    'str_replace(',
) as $unnecessaryCallbackCode) {
    if (strpos($service, $unnecessaryCallbackCode) !== false) {
        $fail(
            'Service umgeht Callback-Defaults: ' . $unnecessaryCallbackCode,
            8
        );
    }
}

$itemTemplate = (string)file_get_contents(
    $module . '/tpl/htm/invoice-items-report.htm'
);
if (stripos($itemTemplate, '<form') !== false) {
    $fail('Eingebetteter Positionsreport enthält ein Formular.', 8);
}
foreach (array(
    '<hr class="dbx_split">',
    '{rpt:colspan}',
    '{report_total}',
) as $requiredTemplate) {
    if (strpos($itemTemplate, $requiredTemplate) === false) {
        $fail('Positionsreport-Vertrag fehlt: ' . $requiredTemplate, 9);
    }
}

$actionTemplate = (string)file_get_contents(
    $module . '/tpl/htm/invoice-row-action.htm'
);
foreach (array('dbxAjax', 'dbxConfirm', 'data-confirm-buttons="yesno"') as $part) {
    if (strpos($actionTemplate, $part) === false) {
        $fail('Action-Vertrag fehlt: ' . $part, 10);
    }
}

echo "OK myInvoices architecture contract\n";
