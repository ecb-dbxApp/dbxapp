<?php

declare(strict_types=1);

/**
 * Öffentliche Defaults dürfen keine installationsbezogenen Ziele oder
 * Geheimnisse enthalten. Reale Werte gehören ausschließlich in die durch
 * .gitignore ausgeschlossenen config.local.php-Dateien.
 */
function read_public_config(string $file): array
{
   $config = array();
   require $file;
   return is_array($config) ? $config : array();
}

function value_at(array $config, array $path)
{
   $value = $config;
   foreach ($path as $part) {
      if (!is_array($value) || !array_key_exists($part, $value)) {
         return null;
      }
      $value = $value[$part];
   }
   return $value;
}

$modules = dirname(__DIR__, 2) . '/modules';
$core = read_public_config($modules . '/dbx/cfg/config.php');
$login = read_public_config($modules . '/dbxLogin/cfg/config.php');
$contact = read_public_config($modules . '/dbxContact/cfg/config.php');
$download = read_public_config($modules . '/dbxDownLoad/cfg/config.php');

$mustBeEmpty = array(
   array('core', $core, array('ftp', 'web', 'sftp_host')),
   array('core', $core, array('ftp', 'web', 'sftp_user')),
   array('core', $core, array('ftp', 'web', 'sftp_pass')),
   array('core', $core, array('mail', 'dbxApp', 'host')),
   array('core', $core, array('mail', 'dbxApp', 'user')),
   array('core', $core, array('mail', 'dbxApp', 'pass')),
   array('core', $core, array('mail', 'dbxApp', 'from_email')),
   array('core', $core, array('mail', 'dbxApp', 'sender')),
   array('login', $login, array('activity_mail_to')),
   array('login', $login, array('mail_from')),
   array('contact', $contact, array('mail_to')),
   array('contact', $contact, array('mail_from')),
   array('download', $download, array('mail_from')),
   array('download', $download, array('token_secret')),
);

foreach ($mustBeEmpty as [$module, $config, $path]) {
   if (trim((string)value_at($config, $path)) !== '') {
      fwrite(STDERR, $module . ': öffentlicher Wert muss leer sein: ' . implode('.', $path) . PHP_EOL);
      exit(1);
   }
}

$disabledFlags = array(
   array('core', $core, array('db', 'dbxRoundtrip', 'activ')),
   array('core', $core, array('mail', 'dbxApp', 'auth')),
   array('login', $login, array('login_mail')),
   array('login', $login, array('logout_mail')),
   array('contact', $contact, array('mail_admin_on_request')),
   array('contact', $contact, array('mail_confirm_requester')),
   array('contact', $contact, array('mail_on_reply')),
);

foreach ($disabledFlags as [$module, $config, $path]) {
   $value = strtolower(trim((string)value_at($config, $path)));
   if (!in_array($value, array('', '0', 'false', 'off', 'no'), true)) {
      fwrite(STDERR, $module . ': öffentlicher Schalter muss deaktiviert sein: ' . implode('.', $path) . PHP_EOL);
      exit(1);
   }
}

if (($core['mail_delivery_mode'] ?? '') !== 'internal') {
   fwrite(STDERR, 'core: öffentlicher Mailstandard muss internal sein.' . PHP_EOL);
   exit(1);
}

echo "Public config defaults: OK\n";
