<?php

define('dbxSystem', 'dbxWebApp');
define('dbxRunAsAdmin', 1);

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 3) . '/include/dbxKernel.php';
require_once dirname(__DIR__) . '/include/dbxUser.class.php';

$class = new ReflectionClass('dbx\\dbxAdmin\\dbxUser');
$user = $class->newInstanceWithoutConstructor();
$validate = $class->getMethod('validate_password_change');
$texts = new class {
   private array $messages = array(
      'password_required' => 'Bitte ein neues Passwort eingeben.',
      'password_repeat_required' => 'Bitte das neue Passwort wiederholen.',
      'password_mismatch' => 'Die Passwoerter stimmen nicht ueberein.',
      'password_too_short' => 'Das Passwort muss mindestens 6 Zeichen lang sein.',
      'settings_empty' => 'Leer',
      'option_yes' => 'Ja',
      'option_no' => 'Nein',
      'settings_not_set' => 'Nicht gesetzt',
      'settings_entries' => '{count} Einträge',
      'settings_invalid' => 'Ungültig',
      'settings_email_confirmed' => 'E-Mail bestätigt',
      'settings_link_expired' => 'Bestätigungslink abgelaufen',
      'settings_confirmation_pending' => 'Bestätigung ausstehend',
      'settings_no_confirmation' => 'Keine Bestätigung',
      'settings_registration_status' => 'Registrierung',
      'settings_mail_sent' => 'E-Mail versendet',
      'settings_link_valid_until' => 'Link gültig bis',
      'settings_password_change' => 'Passwortwechsel erforderlich',
      'settings_protected' => 'Geschützt',
      'settings_security_hint' => 'Sicherheitswerte werden nicht angezeigt.',
   );

   public function get_fd_message(string $key): string {
      return $this->messages[$key] ?? $key;
   }

   public function format_fd_message(string $key, array $values): string {
      $message = $this->get_fd_message($key);
      foreach ($values as $name => $value) {
         $message = str_replace('{' . $name . '}', (string)$value, $message);
      }
      return $message;
   }
};

$cases = array(
   array(false, '', '', false, '', ''),
   array(true, '', '', false, 'password_new', 'Bitte ein neues Passwort eingeben.'),
   array(false, 'abcdef', '', false, 'password_new2', 'Bitte das neue Passwort wiederholen.'),
   array(false, 'abcdef', 'abcdeg', false, 'password_new2', 'Die Passwoerter stimmen nicht ueberein.'),
   array(false, 'abc', 'abc', false, 'password_new', 'Das Passwort muss mindestens 6 Zeichen lang sein.'),
   array(false, 'Sicher-123', 'Sicher-123', true, '', ''),
   array(true, 'Sicher-123', 'Sicher-123', true, '', ''),
);

foreach ($cases as $index => $case) {
   [$isNew, $password, $repeat, $change, $field, $message] = $case;
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
if (strpos($settingsHtml, 'Bestätigungslink abgelaufen') === false
   || strpos($settingsHtml, 'Passwortwechsel erforderlich') === false
   || strpos($settingsHtml, 'Theme') === false
   || strpos($settingsHtml, 'dunkel') === false
   || strpos($settingsHtml, 'DARF-NICHT-SICHTBAR-SEIN') !== false
   || strpos($settingsHtml, 'DARF-AUCH-NICHT-SICHTBAR-SEIN') !== false) {
   fwrite(STDERR, "FAIL: Strukturierte Einstellungen sind unvollstaendig oder zeigen Sicherheitswerte.\n");
   exit(4);
}

echo "OK dbxAdmin user password change\n";
