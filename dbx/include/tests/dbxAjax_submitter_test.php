<?php

/**
 * Regressionstest für benannte Submit-Schaltflächen in AJAX-Formularen.
 *
 * Hintergrund:
 * FormData(form) enthält den Wert des geklickten Submit-Buttons nicht.
 * ajax.js muss deshalb SubmitEvent.submitter übernehmen. confirm.js darf die
 * AJAX-Verarbeitung außerdem nicht direkt am Button starten, weil die
 * AJAX-Konfiguration regulär am Formular hängen kann.
 */

$dbx_root = dirname(__DIR__, 2);
require_once __DIR__ . '/dbxModuleSourceBundle.php';
$ajax_file = $dbx_root . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'ajax.js';
$confirm_file = $dbx_root . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'confirm.js';
$cart_template = $dbx_root . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'dbxShop'
    . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'htm' . DIRECTORY_SEPARATOR . 'shop-cart-report.htm';
$shop_start_template = $dbx_root . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'dbxShop'
    . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'htm' . DIRECTORY_SEPARATOR . 'start.htm';
$shop_js_file = $dbx_root . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'dbxShop'
    . DIRECTORY_SEPARATOR . 'design' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'shop.js';
$shop_service_file = $dbx_root . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'dbxShop'
    . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'dbxShopService.class.php';

$ajax = is_file($ajax_file) ? (string)file_get_contents($ajax_file) : '';
$confirm = is_file($confirm_file) ? (string)file_get_contents($confirm_file) : '';
$cart = is_file($cart_template) ? (string)file_get_contents($cart_template) : '';
$shop_start = is_file($shop_start_template) ? (string)file_get_contents($shop_start_template) : '';
$shop_js = is_file($shop_js_file) ? (string)file_get_contents($shop_js_file) : '';
$shop_service = is_file($shop_service_file) ? dbx_test_module_source_bundle($shop_service_file) : '';
$errors = array();

if (strpos($ajax, 'submitSource: e.submitter || null') === false) {
    $errors[] = 'ajax.js übernimmt SubmitEvent.submitter nicht.';
}

if (strpos($ajax, 'body.set(ctx.submitName, ctx.submitValue)') === false) {
    $errors[] = 'ajax.js schreibt name/value des Submitters nicht in FormData.';
}

if (strpos($confirm, 'form.requestSubmit(source)') === false) {
    $errors[] = 'confirm.js setzt bestätigte Formularaktionen nicht mit ihrem Submitter fort.';
}

if (strpos($confirm, 'dbx.ajax.run(ajaxRoot') !== false) {
    $errors[] = 'confirm.js umgeht den regulären Formular-Submit.';
}

if (strpos($confirm, 'data-dbx-confirm-submitter') === false
    || strpos($confirm, 'form.appendChild(submitProxy)') === false) {
    $errors[] = 'confirm.js erhält name/value bestätigter Submitter nicht im Formular.';
}

if (strpos($cart, 'name="clear"') === false) {
    $errors[] = 'Warenkorb-Template enthält name="clear" nicht.';
}

if (strpos($shop_service, 'name="remove"') === false) {
    $errors[] = 'Warenkorb-Report enthält name="remove" nicht.';
}

if (strpos($cart, 'data-dbx-shop-cart-count="{cart_count}"') === false
    || strpos($shop_service, "add_rep('cart_count'") === false) {
    $errors[] = 'Warenkorb-Report veröffentlicht den aktuellen Menüzähler nicht.';
}

if (strpos($shop_start, 'dbxShop/design/js/shop.js?v={dbx:asset_version}') === false
    || strpos($shop_js, 'window.dbx.event.on("ajax:after"') === false) {
    $errors[] = 'Shop-Menüzähler wird nach AJAX-Aktionen nicht synchronisiert.';
}

if ($errors !== array()) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo "OK dbxAjax_submitter_test" . PHP_EOL;
