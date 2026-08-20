<?php

$root = dirname(__DIR__, 3);
chdir($root);
$_SERVER['REQUEST_URI'] = '/dbxapp/?dbx_modul=dbxAdmin&dbx_run1=dashboard&dbx_run2=ui_defaults_panel';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/dbxapp/index.php';
$_SERVER['REQUEST_METHOD'] = 'GET';

if (!defined('dbxSystem')) define('dbxSystem', 'dbxWebApp');
if (!defined('dbxRunAsAdmin')) define('dbxRunAsAdmin', 1);

require $root . '/dbx/vendor/autoload.php';
require_once $root . '/dbx/include/dbxKernel.php';

$fail = static function (string $message, int $code): void {
    fwrite(STDERR, "FAIL: $message\n");
    exit($code);
};

$service = dbx()->get_system_obj('dbxUiSettingsService');
if (!$service->ensure_schema()) {
    $fail('dbxUiDefault wurde nicht per DD synchronisiert.', 1);
}

$input = array(
    'dbx.UI.grid.orders.PAGE.SIZE' => '50',
    'dbx.UI.grid.orders.COLUMNS.SIZE.title' => '240',
    'dbx.UI.grid.orders.HEIGHT' => '600',
    'dbx.UI.utilities.global.skin:dbxapp' => 'dunkel',
    'dbx.UI.collapse.change-log-resources-1.state' => 'collapsed',
    'dbx.UI.menu.admin.branches' => array('0', '0.2'),
    'dbx.UI.adminDashboard.admin-dashboard.section' => 'performance_panel',
    'dbx.UI.grid.orders.PAGE.NO' => 4,
    'dbx.UI.menu.history.items' => array('intern'),
    'dbx.UI.formUiPersist.contact.password' => 'secret',
);

$desktop = $service->normalize_settings($input, 'desktop');
$mobile = $service->normalize_settings($input, 'mobile');
if (count($desktop) !== 7) {
    $fail('Desktop-Whitelist übernimmt nicht genau die freigegebenen UI-Werte.', 2);
}
if (isset($mobile['dbx.UI.grid.orders.HEIGHT']) || count($mobile) !== 6) {
    $fail('Mobile übernimmt eine Desktop-Höhe oder verliert freigegebene Werte.', 3);
}
foreach (array(
    'dbx.UI.grid.orders.PAGE.NO',
    'dbx.UI.menu.history.items',
    'dbx.UI.formUiPersist.contact.password',
) as $forbidden) {
    if (isset($desktop[$forbidden]) || isset($mobile[$forbidden])) {
        $fail('Temporärer oder sensibler Wert wurde als Admin-Standard zugelassen: ' . $forbidden, 4);
    }
}

$db = dbx()->get_system_obj('dbxDB');
$before = $service->load_defaults();
if ($db->begin(dbxUiSettingsService::DD) !== 1) {
    $fail('Transaktion für den UI-Settings-Vertrag konnte nicht gestartet werden.', 5);
}
try {
    $saved_count = $service->save_defaults('desktop', $desktop, 1);
    $during = $service->load_defaults();
    if ($saved_count !== count($desktop) || ($during['desktop'] ?? array()) !== $desktop) {
        $fail('DD-basierte Speicherung und Auflösung liefern nicht denselben UI-Standard.', 6);
    }
} finally {
    $db->rollback(dbxUiSettingsService::DD);
}
if ($service->load_defaults() !== $before) {
    $fail('Der isolierte UI-Settings-Test hat Installationswerte verändert.', 7);
}

if (!$service->ensure_user_schema()) {
    $fail('dbxUiProfile wurde nicht per DD synchronisiert.', 8);
}
$profile_before = $service->load_user_profiles(1);
if ($db->begin(dbxUiSettingsService::USER_DD) !== 1) {
    $fail('Transaktion für das sessionübergreifende Benutzerprofil konnte nicht gestartet werden.', 9);
}
try {
    $profile_count = $service->save_user_profile('desktop', $desktop, 1);
    $profile_during = $service->load_user_profiles(1);
    if ($profile_count !== count($desktop) || ($profile_during['desktop'] ?? array()) !== $desktop) {
        $fail('Sessionübergreifendes Benutzerprofil wird nicht identisch gespeichert und geladen.', 10);
    }
} finally {
    $db->rollback(dbxUiSettingsService::USER_DD);
}
if ($service->load_user_profiles(1) !== $profile_before) {
    $fail('Der isolierte UI-Profil-Test hat Benutzerwerte verändert.', 11);
}

$ui_js = (string)file_get_contents($root . '/dbx/js/lib/uiSettings.js');
$tpl = (string)file_get_contents($root . '/dbx/include/dbxTPL.class.php');
$dashboard = (string)file_get_contents($root . '/dbx/modules/dbxAdmin/tpl/htm/admin-dashboard.htm');
$user_route = (string)file_get_contents($root . '/dbx/modules/dbxUser/dbxUser.class.php');
if (!str_contains($ui_js, 'personal !== null')
    || !str_contains($ui_js, 'dbx.uiDefaultPayload[context()]')
    || !str_contains($ui_js, 'dbx.uiApplySnapshot')
    || !str_contains($tpl, 'dbx.uiDefaultPayload=')
    || !str_contains($dashboard, 'data-admin-nav="ui_defaults_panel"')
    || !str_contains($user_route, "case 'ui_settings':")
) {
    $fail('Die dreistufige Client-Vererbung oder UI-Profil-Navigation ist unvollständig.', 12);
}

foreach (glob($root . '/dbx/design/*/htm/*.htm') ?: array() as $design_template) {
    $source = (string)file_get_contents($design_template);
    if (str_contains($source, 'dbx/js/lib/core.js')
        && !str_contains($source, 'dbx/js/lib/uiSettings.js')
    ) {
        $fail('Design lädt core.js ohne uiSettings.js: ' . $design_template, 13);
    }
}

echo "OK dbx UI settings inheritance\n";
