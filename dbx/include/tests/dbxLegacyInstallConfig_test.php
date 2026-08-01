<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/dbxKernel.php';

$fail = static function (string $message, int $code): void {
   fwrite(STDERR, "FAIL: $message\n");
   exit($code);
};

$localFile = tempnam(sys_get_temp_dir(), 'dbx-install-config-');
if (!is_string($localFile) || $localFile === '') {
   $fail('Temporäre lokale Konfiguration konnte nicht erstellt werden.', 1);
}

try {
   $method = new ReflectionMethod(dbxApi::class, 'normalize_legacy_install_config');
   $api = dbx();

   $legacy = $method->invoke($api, 'dbx', $localFile, array());
   if (($legacy['install'] ?? null) !== 0) {
      $fail('Bestehende Legacy-Installation wird nicht als installiert erkannt.', 2);
   }

   $explicitInstaller = $method->invoke($api, 'dbx', $localFile, array('install' => 1));
   if (($explicitInstaller['install'] ?? null) !== 1) {
      $fail('Expliziter lokaler Installationsmodus wird überschrieben.', 3);
   }

   $otherModule = $method->invoke($api, 'dbxLogin', $localFile, array());
   if (array_key_exists('install', $otherModule)) {
      $fail('Kompatibilitätsregel greift außerhalb des Kernmoduls.', 4);
   }

   $missingFile = $method->invoke($api, 'dbx', $localFile . '.missing', array());
   if (array_key_exists('install', $missingFile)) {
      $fail('Neuinstallation ohne lokale Konfiguration wird übersprungen.', 5);
   }
} finally {
   if (is_file($localFile)) {
      unlink($localFile);
   }
}

echo "OK legacy install configuration compatibility (4)\n";
