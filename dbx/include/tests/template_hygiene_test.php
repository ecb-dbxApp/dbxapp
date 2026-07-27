<?php

$root = dirname(__DIR__, 3);
$failures = array();
$iterator = new RecursiveIteratorIterator(
   new RecursiveDirectoryIterator($root . '/dbx', FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
   if (!$file->isFile() || strtolower($file->getExtension()) !== 'htm') {
      continue;
   }
   $path = $file->getRealPath();
   $normalized = str_replace('\\', '/', $path ?: '');
   if (strpos($normalized, '/vendor/') !== false) {
      continue;
   }
   $source = file_get_contents($path);
   if (!is_string($source)) {
      continue;
   }
   $issues = array();
   if (strncmp($source, "\xEF\xBB\xBF", 3) === 0) {
      $issues[] = 'UTF-8-BOM';
   }
   if (preg_match('/\son[a-z]+\s*=/i', $source) === 1) {
      $issues[] = 'Inline-Eventhandler';
   }
   if ($issues) {
      $failures[] = str_replace('\\', '/', substr($path, strlen($root) + 1)) . ' (' . implode(', ', $issues) . ')';
   }
}

if ($failures) {
   fwrite(STDERR, "FAIL: Template-Hygiene: " . implode(', ', $failures) . "\n");
   exit(1);
}

echo "OK template hygiene\n";
