<?php

declare(strict_types=1);

$module = dirname(__DIR__);
$dbxRoot = dirname(__DIR__, 3);

if (!defined('dbxSystem')) {
    define('dbxSystem', 'dbxWebApp');
}
if (!defined('dbxRunAsAdmin')) {
    define('dbxRunAsAdmin', 1);
}

require_once $dbxRoot . '/vendor/autoload.php';
require_once $dbxRoot . '/include/dbxKernel.php';
require_once $module . '/include/dbxDashboard.class.php';
require_once $dbxRoot . '/include/tests/dbxModuleSourceBundle.php';

$source = dbx_test_module_source_bundle($module . '/include/dbxDashboard.class.php');
$template = (string)file_get_contents(
    $module . '/tpl/htm/admin-dashboard.htm'
);
$style = (string)file_get_contents(
    $module . '/tpl/css/admin-dashboard.css'
);

function dashboard_admin_warning_assert(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

dashboard_admin_warning_assert(
    str_contains($source, 'default_admin_password_warning')
        && str_contains($source, "array('uname' => 'admin')")
        && str_contains($source, "password_verify('123456', \$hash)")
        && str_contains($source, 'Unsicheres Installationspasswort aktiv')
        && str_contains($source, 'dbx_run2=edit_user')
        && str_contains($source, 'Passwort ändern'),
    'Das Admin-Dashboard erkennt oder verlinkt admin/123456 nicht vollständig.'
);
dashboard_admin_warning_assert(
    str_contains($template, '{obj:dashboard_message}'),
    'Die Sicherheitswarnung besitzt keinen Platz im Dashboard.'
);
dashboard_admin_warning_assert(
    str_contains($style, '.dbx-admin-dashboard-password-warning')
        && str_contains($style, '@media (max-width: 720px)'),
    'Die Sicherheitswarnung ist nicht responsiv gestaltet.'
);

$class = new ReflectionClass('dbx\\dbxAdmin\\dbxDashboard');
$dashboard = $class->newInstanceWithoutConstructor();
$renderWarning = $class->getMethod('default_admin_password_warning_html');
$warning = $renderWarning->invoke(
    $dashboard,
    27,
    password_hash('123456', PASSWORD_DEFAULT)
);
dashboard_admin_warning_assert(
    str_contains($warning, 'Unsicheres Installationspasswort aktiv')
        && str_contains($warning, 'rid=27')
        && str_contains($warning, 'dbx_run2=edit_user')
        && str_contains($warning, 'Passwort ändern'),
    'Ein aktives Standardpasswort erzeugt keine vollständige Dashboard-Warnung.'
);
dashboard_admin_warning_assert(
    $renderWarning->invoke(
        $dashboard,
        27,
        password_hash('Persoenlich-2026!', PASSWORD_DEFAULT)
    ) === '',
    'Die Dashboard-Warnung bleibt nach einem persönlichen Passwort sichtbar.'
);

echo "OK admin dashboard default password warning\n";
