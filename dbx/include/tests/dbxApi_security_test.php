<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/dbxKernel.php';

$fail = static function (string $message, int $code): void {
   fwrite(STDERR, "FAIL: $message\n");
   exit($code);
};

$password = dbx()->new_password(96, '-_!');
if (strlen($password) !== 96 || preg_match('/^[a-zA-Z0-9_!\-]+$/', $password) !== 1) {
   $fail('new_password() liefert keine gueltige Laenge oder Zeichenmenge.', 1);
}

$webAppFile = dirname(__DIR__) . '/dbxWebApp.class.php';
$webAppSource = file_get_contents($webAppFile);
if (!is_string($webAppSource) || preg_match('/\\blogin\\s*\\(\\s*2\\s*\\)/', $webAppSource) === 1) {
   $fail('Ein Request-Parameter kann weiterhin den API-Benutzer anmelden.', 2);
}

$loadConfig = static function (string $file): array {
   $config = array();
   require $file;
   return is_array($config) ? $config : array();
};
$baseFile = dirname(__DIR__, 2) . '/modules/dbx/cfg/config.php';
$localFile = dirname(__DIR__, 2) . '/modules/dbx/cfg/config.local.php';
$base = $loadConfig($baseFile);
$local = is_file($localFile) ? $loadConfig($localFile) : array();

$secretPaths = array(
   array('db', 'dbxApp', 'pass'),
   array('db', 'dbxTestCodex', 'pass'),
   array('ftp', 'web', 'sftp_pass'),
   array('mail', 'dbxApp', 'pass'),
);
$readPath = static function (array $data, array $path) {
   foreach ($path as $part) {
      if (!is_array($data) || !array_key_exists($part, $data)) {
         return null;
      }
      $data = $data[$part];
   }
   return $data;
};

foreach ($secretPaths as $path) {
   if ((string)$readPath($base, $path) !== '') {
      $fail('Ein Geheimnis steht weiterhin in der versionierten config.php.', 3);
   }
}

if ($local) {
   $runtime = dbx()->get_config('dbx');
   foreach ($secretPaths as $path) {
      $localValue = $readPath($local, $path);
      if ($localValue !== null && $readPath($runtime, $path) !== $localValue) {
         $fail('config.local.php wird nicht korrekt ueber die Basis gelegt.', 4);
      }
   }
}

echo "OK dbxApi security\n";
