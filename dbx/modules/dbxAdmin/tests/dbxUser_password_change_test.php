<?php

define('dbxSystem', 'dbxWebApp');
define('dbxRunAsAdmin', 1);

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 3) . '/include/dbxKernel.php';
require_once dirname(__DIR__) . '/include/dbxUser.class.php';

$class = new ReflectionClass('dbx\\dbxAdmin\\dbxUser');
$user = $class->newInstanceWithoutConstructor();
$validate = $class->getMethod('validate_password_change');
$texts = new \dbxForm();
$texts->set_form_help_enabled(false);
$texts->_fd = 'dbxAdmin|rpt-admin-user-selection';
$texts->load_fd_messages();

$cases = array(
   array(false, '', '', false, '', ''),
   array(true, '', '', false, 'password_new', 'password_required'),
   array(false, 'abcdef', '', false, 'password_new2', 'password_repeat_required'),
   array(false, 'abcdef', 'abcdeg', false, 'password_new2', 'password_mismatch'),
   array(false, 'abc', 'abc', false, 'password_new', 'password_too_short'),
   array(false, 'Sicher-123', 'Sicher-123', true, '', ''),
   array(true, 'Sicher-123', 'Sicher-123', true, '', ''),
);

foreach ($cases as $index => $case) {
   [$isNew, $password, $repeat, $change, $field, $messageKey] = $case;
   $message = $messageKey !== '' ? $texts->get_fd_message($messageKey) : '';
   $result = $validate->invoke($user, $isNew, $password, $repeat, $texts);
   if (($result['change'] ?? null) !== $change
      || ($result['field'] ?? null) !== $field
      || ($result['message'] ?? null) !== $message) {
      fwrite(STDERR, 'FAIL: Passwortpruefung Fall ' . ($index + 1) . PHP_EOL);
      exit(1);
   }
}

$template = file_get_contents(dirname(__DIR__) . '/tpl/htm/form-admin-user.htm');
$profilePos = strpos((string)$template, '>Profil<');
$passwordPos = strpos((string)$template, '{obj:password_new}');
$repeatPos = strpos((string)$template, '{obj:password_new2}');
$contactPos = strpos((string)$template, '>Kontakt<');
if ($profilePos === false || $passwordPos === false || $repeatPos === false || $contactPos === false
   || !($profilePos < $passwordPos && $passwordPos < $repeatPos && $repeatPos < $contactPos)
   || substr_count((string)$template, '{obj:password_new}') !== 1
   || substr_count((string)$template, '{obj:password_new2}') !== 1) {
   fwrite(STDERR, "FAIL: Beide Passwortfelder muessen einmalig im rechten Profil-Block stehen.\n");
   exit(2);
}

if (strpos((string)$template, '{obj:settings_view}') === false
   || strpos((string)$template, '{obj:settings}') !== false) {
   fwrite(STDERR, "FAIL: Einstellungen muessen strukturiert statt als JSON-Feld ausgegeben werden.\n");
   exit(3);
}

$settingsView = $class->getMethod('user_settings_view');
$settingsHtml = $settingsView->invoke($user, array(
   'is_confirm' => 0,
   'settings' => json_encode(array(
      'register_confirm' => array(
         'token_hash' => 'DARF-NICHT-SICHTBAR-SEIN',
         'expires' => time() - 60,
         'sent' => '2026-05-29 17:10:56',
      ),
      'password_reset_required' => 1,
      'theme' => 'dunkel',
      'api_secret' => 'DARF-AUCH-NICHT-SICHTBAR-SEIN',
   )),
), $texts);
if (strpos($settingsHtml, $texts->get_fd_message('settings_link_expired')) === false
   || strpos($settingsHtml, $texts->get_fd_message('settings_password_change')) === false
   || strpos($settingsHtml, 'Theme') === false
   || strpos($settingsHtml, 'dunkel') === false
   || strpos($settingsHtml, 'DARF-NICHT-SICHTBAR-SEIN') !== false
   || strpos($settingsHtml, 'DARF-AUCH-NICHT-SICHTBAR-SEIN') !== false) {
   fwrite(STDERR, "FAIL: Strukturierte Einstellungen sind unvollstaendig oder zeigen Sicherheitswerte.\n");
   exit(4);
}

echo "OK dbxAdmin user password change\n";
