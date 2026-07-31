<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/include/dbxPasswordPolicy.class.php';

function profile_password_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

profile_password_assert(
    dbxPasswordPolicy::minimumLength(4) === 6
        && dbxPasswordPolicy::minimumLength(12) === 12
        && dbxPasswordPolicy::minimumLength(999) === 128,
    'Die zentrale Passwort-Mindestlänge wird nicht korrekt begrenzt.'
);

$weakErrors = dbxPasswordPolicy::errors('abc', 'xyz', '', 6);
profile_password_assert(
    str_contains((string)($weakErrors['password'] ?? ''), 'mindestens 6 Zeichen')
        && str_contains((string)($weakErrors['password'] ?? ''), 'ein Großbuchstabe')
        && str_contains((string)($weakErrors['password'] ?? ''), 'eine Zahl')
        && str_contains((string)($weakErrors['password'] ?? ''), 'ein Sonderzeichen')
        && isset($weakErrors['repeat']),
    'Die Profilprüfung muss alle fehlenden Kriterien und Abweichungen nennen.'
);

$strongPassword = 'Ab1!xy';
profile_password_assert(
    dbxPasswordPolicy::errors(
        $strongPassword,
        $strongPassword,
        '',
        6
    ) === array(),
    'Ein gültiges Profilpasswort wird abgelehnt.'
);
profile_password_assert(
    isset(dbxPasswordPolicy::errors(
        $strongPassword,
        $strongPassword,
        password_hash($strongPassword, PASSWORD_DEFAULT),
        6
    )['password']),
    'Das bisherige Passwort darf nicht unverändert erneut gespeichert werden.'
);

$profileSource = (string)file_get_contents(
    dirname(__DIR__) . '/include/dbxUser_profil.class.php'
);
$adminSource = (string)file_get_contents(
    $root . '/modules/dbxUser_admin/include/dbxUser_profil.class.php'
);
$profileTemplate = (string)file_get_contents(
    dirname(__DIR__) . '/tpl/htm/form-profil.htm'
);
$adminTemplate = (string)file_get_contents(
    $root . '/modules/dbxUser_admin/tpl/htm/form-profil.htm'
);
$utilities = (string)file_get_contents($root . '/js/lib/utilities.js');
$formCss = (string)file_get_contents(
    $root . '/design/dbxapp/css/c-form.css'
);

foreach (array($profileSource, $adminSource) as $source) {
    profile_password_assert(
        str_contains($source, '\\dbxPasswordPolicy::errors(')
            && str_contains($source, 'password_hash(')
            && str_contains($source, 'password_changed_at')
            && str_contains($source, "unset(\$settings['password_reset_required'])"),
        'Profil und Admin-Profil müssen dieselbe moderne Passwortpolicy verwenden.'
    );
}
profile_password_assert(
    !str_contains($adminSource, 'md5($pas)')
        && str_contains($adminSource, "'pass_repeat'")
        && str_contains($adminSource, "'varchar|max=128'"),
    'Das Admin-Profil muss Passwortwiederholung und moderne Hashes verwenden.'
);

foreach (array($profileTemplate, $adminTemplate) as $template) {
    profile_password_assert(
        str_contains($template, 'class="dbx-password-rules--compact"')
            && str_contains($template, 'data-dbx-password-rules')
            && str_contains($template, 'data-password-rule="length"')
            && str_contains($template, 'data-password-rule="letters"')
            && str_contains($template, 'data-password-rule="number"')
            && str_contains($template, 'data-password-rule="special"')
            && str_contains($template, 'data-password-rule="match"'),
        'Die kompakte Profilanzeige enthält nicht alle Passwortkriterien.'
    );
}
profile_password_assert(
    str_contains($utilities, 'function initPasswordCriteria(')
        && str_contains($utilities, 'passwordRules:')
        && str_contains($formCss, '.dbx-password-rules--compact'),
    'Die wiederverwendbare Live-Prüfung oder ihre kompakte Darstellung fehlt.'
);

echo "OK compact profile password policy and modern hashes\n";
