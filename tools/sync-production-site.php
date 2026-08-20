<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/ProductionSiteSync.php';

$options = getopt('', array('source:', 'target:', 'apply', 'verbose', 'help'));
if (isset($options['help'])) {
   echo "Schlanken dbxApp-Produktionsstand erzeugen\n\n";
   echo "Vorschau:\n  php tools/sync-production-site.php "
      . "--source=C:\\xampp\\htdocs\\dbxapp "
      . "--target=C:\\xampp\\htdocs\\dbxapp.de\n\n";
   echo "Anwenden: denselben Aufruf um --apply ergänzen.\n";
   exit(0);
}

$source = (string)($options['source'] ?? dirname(__DIR__));
$target = (string)($options['target'] ?? (dirname(dirname(__DIR__)) . '/dbxapp.de'));
$apply = array_key_exists('apply', $options);
$verbose = array_key_exists('verbose', $options);

try {
   $plan = ProductionSiteSync::plan($source, $target);
   echo 'Quelle:       ' . realpath($source) . PHP_EOL;
   echo 'Ziel:         ' . realpath($target) . PHP_EOL;
   echo 'Modus:        ' . ($apply ? 'ANWENDEN' : 'VORSCHAU') . PHP_EOL;
   echo 'Kopieren:     ' . count($plan['copy']) . PHP_EOL;
   echo 'Löschen:      ' . count($plan['delete']) . PHP_EOL;
   echo 'Unverändert:  ' . $plan['unchanged'] . PHP_EOL;
   echo 'Lokal bewahrt:' . $plan['preserved'] . PHP_EOL;

   if ($verbose) {
      foreach (array_keys($plan['copy']) as $relative) {
         echo '  COPY   ' . $relative . PHP_EOL;
      }
      foreach ($plan['delete'] as $relative) {
         echo '  DELETE ' . $relative . PHP_EOL;
      }
   }
   if (!$apply) {
      echo "Keine Datei verändert.\n";
      exit(0);
   }

   $result = ProductionSiteSync::apply($source, $target, $plan);
   echo 'Produktionsstand aktualisiert: ' . $result['copied']
      . ' kopiert, ' . $result['deleted'] . " entfernt.\n";
} catch (Throwable $exception) {
   fwrite(STDERR, 'FEHLER: ' . $exception->getMessage() . PHP_EOL);
   exit(1);
}
