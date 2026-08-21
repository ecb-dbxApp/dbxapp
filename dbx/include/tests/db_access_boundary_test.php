<?php

$root = dirname(__DIR__, 3);
$allowed = realpath($root . '/dbx/include/dbxDB.class.php');
$failures = array();
$patterns = array(
   '/\bnew\s+\\\\?PDO\b/i',
   '/\bPDO::/i',
   '/\bmysqli_(?:connect|query|prepare)\b/i',
   '/\bnew\s+mysqli\b/i',
);

$iterator = new RecursiveIteratorIterator(
   new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
   if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
      continue;
   }
   $path = $file->getRealPath();
   $normalized = str_replace('\\', '/', $path ?: '');
   $is_dbx_db_component = str_starts_with($normalized, str_replace('\\', '/', dirname((string)$allowed)) . '/dbxDB')
      && str_ends_with($normalized, '.trait.php');
   if ($path === $allowed
      || $is_dbx_db_component
      || strpos($normalized, '/vendor/') !== false
      || strpos($normalized, '/dbx/vendor/') !== false
      || strpos($normalized, '/files/') !== false
      || strpos($normalized, '/tmp/') !== false
      || strpos($normalized, '/reference/') !== false) {
      continue;
   }
   $source = file_get_contents($path);
   foreach ($patterns as $pattern) {
      if (is_string($source) && preg_match($pattern, $source) === 1) {
         $failures[] = str_replace('\\', '/', substr($path, strlen($root) + 1));
         break;
      }
   }
}

if ($failures) {
   fwrite(STDERR, "FAIL: Direkter DB-Treiberzugriff ausserhalb dbxDB: " . implode(', ', $failures) . "\n");
   exit(1);
}

echo "OK dbxDB access boundary\n";
