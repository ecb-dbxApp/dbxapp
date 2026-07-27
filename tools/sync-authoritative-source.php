<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/AuthoritativeSourceSync.php';

$options = getopt('', array('source:', 'target:', 'apply', 'help'));
if (isset($options['help'])) {
   echo "dbxApp-Produktcode kontrolliert in den GitHub-Spiegel übertragen\n\n";
   echo "Dry-Run:\n";
   echo "  php tools/sync-authoritative-source.php --source=C:\\xampp\\htdocs\\dbxapp"
      . " --target=C:\\xampp\\htdocs\\dbxapp-github\n\n";
   echo "Anwenden (Ziel muss einen sauberen Git-Status haben):\n";
   echo "  php tools/sync-authoritative-source.php --source=... --target=... --apply\n";
   exit(0);
}

$source = trim((string)($options['source'] ?? dirname(__DIR__)));
$target = trim((string)($options['target'] ?? (dirname(dirname(__DIR__)) . '/dbxapp-github')));
$apply = array_key_exists('apply', $options);

try {
   $plan = AuthoritativeSourceSync::plan($source, $target);
   echo 'Quelle:    ' . realpath($source) . PHP_EOL;
   echo 'Ziel:      ' . realpath($target) . PHP_EOL;
   echo 'Modus:     ' . ($apply ? 'ANWENDEN' : 'DRY-RUN') . PHP_EOL;
   echo 'Kopieren:  ' . count($plan['copy']) . PHP_EOL;
   echo 'Löschen:   ' . count($plan['delete']) . PHP_EOL;
   echo 'Unverändert: ' . $plan['unchanged'] . PHP_EOL;

   foreach (array_keys($plan['copy']) as $relative) {
      echo '  COPY   ' . $relative . PHP_EOL;
   }
   foreach ($plan['delete'] as $relative) {
      echo '  DELETE ' . $relative . PHP_EOL;
   }

   if (!$apply) {
      echo PHP_EOL . "Keine Datei verändert. Mit --apply wird dieser Plan ausgeführt.\n";
      exit(0);
   }

   $result = AuthoritativeSourceSync::apply($source, $target, $plan);
   echo PHP_EOL . 'Spiegel aktualisiert: '
      . $result['copied'] . ' kopiert, '
      . $result['deleted'] . " entfernt.\n";
} catch (Throwable $exception) {
   fwrite(STDERR, 'FEHLER: ' . $exception->getMessage() . PHP_EOL);
   exit(1);
}
