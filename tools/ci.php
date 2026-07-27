<?php

declare(strict_types=1);

$root = realpath(dirname(__DIR__));
if ($root === false) {
   fwrite(STDERR, "Projektwurzel wurde nicht gefunden.\n");
   exit(2);
}

$failures = array();
$phpFiles = array();
$testFiles = array();
$publicFiles = array();

/**
 * Normalisiert einen absoluten Projektpfad für reproduzierbare Ausgaben.
 */
function relative_path(string $root, string $path): string
{
   $relative = substr($path, strlen($root) + 1);
   return str_replace('\\', '/', $relative === false ? $path : $relative);
}

/**
 * Führt ein Kommando aus und liefert Exitcode und Ausgabe.
 *
 * @return array{code:int,output:string}
 */
function run_command(array $arguments, string $workingDirectory): array
{
   $command = implode(' ', array_map('escapeshellarg', $arguments));
   $descriptorSpec = array(
      0 => array('pipe', 'r'),
      1 => array('pipe', 'w'),
      2 => array('pipe', 'w'),
   );

   $process = proc_open($command, $descriptorSpec, $pipes, $workingDirectory);
   if (!is_resource($process)) {
      return array('code' => 127, 'output' => 'Prozess konnte nicht gestartet werden.');
   }

   fclose($pipes[0]);
   $stdout = stream_get_contents($pipes[1]);
   $stderr = stream_get_contents($pipes[2]);
   fclose($pipes[1]);
   fclose($pipes[2]);

   $code = proc_close($process);
   return array(
      'code' => (int)$code,
      'output' => trim((string)$stdout . ((string)$stderr !== '' ? PHP_EOL . (string)$stderr : '')),
   );
}

/**
 * Liefert die vorhandenen lokalen Laufzeitdatenbanken als Set.
 *
 * @return array<string,true>
 */
function runtime_databases(string $root): array
{
   $databases = array();
   $iterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
   );
   foreach ($iterator as $item) {
      if (!$item->isFile()) {
         continue;
      }
      $relative = relative_path($root, $item->getPathname());
      if (str_starts_with($relative, 'dbx/vendor/')) {
         continue;
      }
      if (in_array(strtolower($item->getExtension()), array('db3', 'sqlite', 'sqlite3'), true)) {
         $databases[$item->getPathname()] = true;
      }
   }
   return $databases;
}

/**
 * Entfernt ausschließlich Datenbanken, die ein isolierter Test neu erzeugte.
 */
function cleanup_test_databases(string $root, array $before): array
{
   $errors = array();
   foreach (runtime_databases($root) as $file => $unused) {
      if (isset($before[$file])) {
         continue;
      }
      foreach (array($file, $file . '-wal', $file . '-shm', $file . '-journal') as $candidate) {
         if (is_file($candidate) && !@unlink($candidate)) {
            $errors[] = 'Temporäre Testdatenbank konnte nicht entfernt werden: '
               . relative_path($root, $candidate);
         }
      }
   }
   return $errors;
}

$iterator = new RecursiveIteratorIterator(
   new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $item) {
   if (!$item->isFile()) {
      continue;
   }

   $absolute = $item->getPathname();
   $relative = relative_path($root, $absolute);

   if (preg_match('#^(?:\.git|dbx/vendor|dist|output|tmp)/#', $relative)) {
      continue;
   }

   $publicFiles[] = $relative;

   $base = basename($relative);
   $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
   if (in_array($base, array('.env', '.env.local', 'config.local.php'), true)
      || in_array($extension, array('db3', 'sqlite', 'sqlite3', 'log', 'pem', 'key', 'p12', 'pfx'), true)
      || preg_match('#/(?:backup|_backup|\.backup|work)/#i', '/' . $relative)) {
      $failures[] = 'Nicht veröffentlichbare Datei: ' . $relative;
   }

   if ($extension === 'php'
      && $relative !== 'dbx/modules/dbx/tpl/php/dd_file.php') {
      $phpFiles[] = $absolute;
   }

   if (preg_match('#/tests/[^/]+_test\.php$#', '/' . $relative)) {
      $testFiles[] = $absolute;
   }

   if ($item->getSize() <= 2 * 1024 * 1024
      && preg_match('/\.(?:php|js|mjs|ts|json|xml|ya?ml|md|txt|htm|html|css|scss|env|example)$/i', $relative)) {
      $content = file_get_contents($absolute);
      if (is_string($content)) {
         $secretPatterns = array(
            '/-----BEGIN (?:RSA |EC |OPENSSH |DSA )?PRIVATE KEY-----\s+[A-Za-z0-9+\/=\r\n]{80,}-----END (?:RSA |EC |OPENSSH |DSA )?PRIVATE KEY-----/s',
            '/\bgithub_pat_[A-Za-z0-9_]{40,}\b/',
            '/\bgh[pousr]_[A-Za-z0-9]{30,}\b/',
            '/\bAKIA[0-9A-Z]{16}\b/',
            '/\bsk-[A-Za-z0-9]{32,}\b/',
            '/[\'"](?:token_secret|client_secret|api_key|private_key|password|pass)[\'"]\s*\]\s*=\s*[\'"][^\'"]{12,}[\'"]/i',
         );
         foreach ($secretPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
               $failures[] = 'Mögliches Secret in: ' . $relative;
               break;
            }
         }
      }
   }
}

sort($phpFiles);
sort($testFiles);
sort($publicFiles);

echo 'Public-Hygiene: ' . count($publicFiles) . " Dateien geprüft\n";

foreach ($phpFiles as $file) {
   $result = run_command(array(PHP_BINARY, '-l', $file), $root);
   if ($result['code'] !== 0) {
      $failures[] = 'PHP-Syntax ' . relative_path($root, $file) . ': ' . $result['output'];
   }
}
echo 'PHP-Syntax: ' . count($phpFiles) . " Dateien geprüft\n";

$testFailures = 0;
foreach ($testFiles as $testFile) {
   $relative = relative_path($root, $testFile);
   $databasesBefore = runtime_databases($root);
   $result = run_command(array(PHP_BINARY, $testFile), $root);
   foreach (cleanup_test_databases($root, $databasesBefore) as $cleanupError) {
      $failures[] = $cleanupError;
      $testFailures++;
   }
   if ($result['code'] !== 0) {
      $testFailures++;
      $failures[] = 'Test ' . $relative . ': ' . $result['output'];
      echo 'FAIL ' . $relative . PHP_EOL;
   } else {
      echo 'OK   ' . $relative . PHP_EOL;
   }
}

echo 'Tests: ' . count($testFiles) . ' ausgeführt, ' . $testFailures . " fehlgeschlagen\n";

if ($failures) {
   fwrite(STDERR, PHP_EOL . "CI FEHLGESCHLAGEN\n");
   foreach ($failures as $failure) {
      fwrite(STDERR, '- ' . $failure . PHP_EOL);
   }
   exit(1);
}

echo PHP_EOL . "CI ERFOLGREICH\n";
