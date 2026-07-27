<?php

/**
 * Architekturvertrag für sprachabhängige aktive Formulare und Listen.
 *
 * Der Test ergänzt dbxForm_fd_language_messages_test.php: Dort wird die
 * strukturelle Gleichheit aller FD-Sprachen geprüft, hier die Bindung der
 * wichtigsten aktiven Oberflächen an genau diese FDs.
 */

$root = dirname(__DIR__, 2);

$fail = static function (string $message): void {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$contracts = array(
    'modules/dbxShop/include/dbxShopService.class.php' => array(
        "texts('dbxShop|shop-catalog-filter-form')",
        "texts('dbxShop|shop-cart')",
        "texts('dbxShop|checkout')",
        "texts('dbxShop|shop-orders')",
        "get_fd_message('column_product')",
        "get_fd_message('payment_bank_transfer_help')",
        "get_fd_message('empty_title')",
    ),
    'modules/dbxAdmin/include/dbxServer.class.php' => array(
        "\$texts->_fd = 'dbxAdmin|server'",
        "\$oReport->_fd = 'dbxAdmin|server'",
        "\$oForm->_fd        = 'dbxAdmin|server'",
        "get_fd_message('column_table')",
        "get_fd_message('check_input_plain')",
    ),
    'modules/dbxAdmin/include/dbxSchema.class.php' => array(
        "'dbxAdmin|schema-report'",
        "get_fd_message('mapping_saved')",
        "get_fd_message('action_save')",
    ),
    'modules/dbxAdmin/include/dbxEdit_fd.class.php' => array(
        "'dbxAdmin|ddedit-field'",
        'load_fd_messages()',
    ),
    'modules/dbxUser/include/dbxUser_profil.class.php' => array(
        "'dbxUser|user-profile'",
        'load_fd_messages()',
    ),
    'modules/dbxUser/include/dbxUser_avatar.class.php' => array(
        "'dbxUser|user-profile'",
        'load_fd_messages()',
    ),
    'modules/myInvoices/include/myInvoicesService.class.php' => array(
        'self::FORM_FD',
        'self::REPORT_FD',
        "get_fd_message('column_invoice_no')",
        "'delete_question',",
    ),
);

foreach ($contracts as $relative => $requiredFragments) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $source = is_file($path) ? file_get_contents($path) : false;
    if (!is_string($source)) {
        $fail("Datei fehlt: {$relative}");
    }
    foreach ($requiredFragments as $fragment) {
        if (!str_contains($source, $fragment)) {
            $fail("{$relative}: FD-Vertrag fehlt: {$fragment}");
        }
    }
    if (
        preg_match(
            '~_msg_(?:info|success|error|warning)\s*=\s*([\'"])(?!\1)(?:(?!\1).)+\1~',
            $source,
            $match
        )
    ) {
        $fail("{$relative}: sichtbare Meldung ist noch im Modul hart codiert: {$match[0]}");
    }
}

$shopSource = file_get_contents(
    $root . '/modules/dbxShop/include/dbxShopService.class.php'
);
foreach (array(
    'Shop Cart Report',
    'Shop Checkout Form',
    'Noch keine Bestellungen',
    'Ihre gespeicherten Shop-Bestellungen.',
    'Aktuell ist keine Zahlungsart aktiv',
) as $obsoleteText) {
    if (str_contains($shopSource, $obsoleteText)) {
        $fail("dbxShopService enthält noch statischen UI-Text: {$obsoleteText}");
    }
}

$coreJs = file_get_contents($root . '/js/lib/core.js');
$confirmJs = file_get_contents($root . '/js/lib/confirm.js');
if (
    !str_contains((string)$coreJs, 'dbx.translate') ||
    !str_contains((string)$confirmJs, 'dbx.translate')
) {
    $fail('Zentrale JavaScript-Bedientexte verwenden dbx.translate() nicht.');
}

echo 'OK: Aktive Kernformulare und -listen beziehen Fachtexte aus ihren Sprach-FDs; '
    . "generische Browsertexte verwenden dbx.translate().\n";
