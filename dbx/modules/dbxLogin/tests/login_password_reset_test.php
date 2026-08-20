<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/include/login.class.php';

use dbx\dbxLogin\login;

function login_password_reset_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$class = new ReflectionClass(login::class);
$login = $class->newInstanceWithoutConstructor();
$reset_required = $class->getMethod('password_reset_required');
$password_errors = $class->getMethod('password_change_errors');

$default_admin = array(
    'uname' => 'admin',
    'settings' => '{}',
);
login_password_reset_assert(
    $reset_required->invoke($login, $default_admin, '123456') === true,
    'admin/123456 muss unabhängig von älteren DB3-Einstellungen einen Passwortwechsel erzwingen.'
);
login_password_reset_assert(
    $reset_required->invoke($login, $default_admin, 'individuell') === false,
    'Ein individueller Admin-Zugang darf nicht als Standardpasswort gelten.'
);
login_password_reset_assert(
    $reset_required->invoke(
        $login,
        array(
            'uname' => 'user',
            'settings' => json_encode(array('password_reset_required' => 1)),
        ),
        'beliebig'
    ) === true,
    'Das vorhandene Kennzeichen password_reset_required muss weiter unterstützt werden.'
);

$current_hash = password_hash('123456', PASSWORD_DEFAULT);
login_password_reset_assert(
    isset($password_errors->invoke(
        $login,
        '123456',
        '123456',
        $current_hash
    )['password_new']),
    'Das unsichere Initialpasswort darf nicht erneut gespeichert werden.'
);
login_password_reset_assert(
    isset($password_errors->invoke(
        $login,
        'Sicher-2026!Passwort',
        'Anders-2026!Passwort',
        $current_hash
    )['password_repeat']),
    'Abweichende Passwortwiederholungen müssen abgelehnt werden.'
);
login_password_reset_assert(
    $password_errors->invoke(
        $login,
        'Sicher-2026!Passwort',
        'Sicher-2026!Passwort',
        $current_hash
    ) === array(),
    'Ein starkes, neues Passwort muss akzeptiert werden.'
);
login_password_reset_assert(
    $password_errors->invoke(
        $login,
        'Ab1!xy',
        'Ab1!xy',
        $current_hash,
        6
    ) === array(),
    'Eine konfigurierte Mindestlänge von 6 Zeichen muss bei erfüllten Qualitätsregeln akzeptiert werden.'
);
login_password_reset_assert(
    isset($password_errors->invoke(
        $login,
        'Ab1!x',
        'Ab1!x',
        $current_hash,
        6
    )['password_new']),
    'Ein Passwort unterhalb der konfigurierten Mindestlänge muss abgelehnt werden.'
);
$detailed_errors = $password_errors->invoke(
    $login,
    'abc',
    'abc',
    $current_hash,
    6
);
login_password_reset_assert(
    str_contains((string)($detailed_errors['password_new'] ?? ''), 'mindestens 6 Zeichen')
        && str_contains((string)($detailed_errors['password_new'] ?? ''), 'ein Großbuchstabe')
        && str_contains((string)($detailed_errors['password_new'] ?? ''), 'eine Zahl')
        && str_contains((string)($detailed_errors['password_new'] ?? ''), 'ein Sonderzeichen'),
    'Die Fehlermeldung muss alle noch nicht erfüllten Passwortkriterien einzeln nennen.'
);

$source = (string)file_get_contents(dirname(__DIR__) . '/include/login.class.php');
$policy_source = (string)file_get_contents(
    dirname(__DIR__, 3) . '/include/dbxPasswordPolicy.class.php'
);
$template = (string)file_get_contents(
    dirname(__DIR__) . '/tpl/htm/form-password-change.htm'
);
login_password_reset_assert(
    str_contains($source, 'pending_password_reset')
        && str_contains($source, "unset(\$settings['password_reset_required'])")
        && str_contains($source, 'password_hash($password, PASSWORD_DEFAULT)')
        && str_contains($source, 'dbx()->login($uid)'),
    'Der erzwungene Passwortwechsel ist nicht vollständig abgeschlossen.'
);
$status_check = strpos($source, "(string)(\$rec['status'] ?? '') === '0'");
$reset_check = strpos($source, '$this->password_reset_required($rec, $pass)');
login_password_reset_assert(
    $status_check !== false
        && $reset_check !== false
        && $status_check < $reset_check,
    'Gesperrte Benutzer dürfen nicht über den initialen Passwortwechsel freigeschaltet werden.'
);
login_password_reset_assert(
    str_contains($template, '{obj:password_new}')
        && str_contains($template, '{obj:password_repeat}')
        && str_contains($template, '{password_min_length}')
        && str_contains($template, 'data-password-rule="length"')
        && str_contains($template, 'data-password-rule="match"')
        && str_contains($template, 'bi-x-circle-fill')
        && str_contains($template, 'Ein neues sicheres Passwort festlegen')
        && str_contains($source, 'Für diesen Zugang ist ein neues persönliches Passwort erforderlich.')
        && str_contains($policy_source, 'Noch nicht erfüllt:')
        && str_contains($source, '\\dbxPasswordPolicy::errors(')
        && str_contains($template, 'Neues Passwort speichern'),
    'Das Passwortänderungsformular ist unvollständig.'
);

echo "OK forced password change and legacy admin compatibility\n";
