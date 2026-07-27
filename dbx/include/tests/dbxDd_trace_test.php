<?php

$root = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'modules';
$files = array();
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
   $path = $file->getPathname();
   if (!$file->isFile() || !str_ends_with(strtolower($file->getFilename()), '.dd.php')) {
      continue;
   }
   $normalized = str_replace('\\', '/', $path);
   if (strpos($normalized, '/dd/') === false || strpos($normalized, '/_backup/') !== false) {
      continue;
   }
   $files[] = $path;
}

$errors = array();
$checked = 0;
foreach ($files as $file) {
   $table = array();
   $fields = array();
   $indexes = array();
   $__dbx_lng_dd = '';
   include $file;

   if (!array_key_exists('table', $table)) {
      continue;
   }
   $checked++;
   $datadic = trim((string)($table['datadic'] ?? ''));
   $expected = $datadic === 'dbxUser' ? '1' : '0';
   $actual = (string)($table['trace'] ?? '');
   if ($actual !== $expected) {
      $errors[] = str_replace('\\', '/', substr($file, strlen(dirname(__DIR__, 2)) + 1))
         . ' datadic=' . $datadic . ' trace=' . ($actual === '' ? '<fehlt>' : $actual)
         . ' erwartet=' . $expected;
   }
}

if ($checked === 0 || $errors) {
   fwrite(STDERR, $checked === 0
      ? "Keine aktiven Tabellen-DDs gefunden.\n"
      : "Falsche Trace-Einstellung:\n - " . implode("\n - ", $errors) . "\n");
   exit(1);
}

echo "OK: {$checked} aktive Tabellen-DDs geprueft; nur dbxUser verwendet trace=1.\n";

