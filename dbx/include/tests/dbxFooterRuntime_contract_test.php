<?php

/**
 * Regressionstest für die Laufzeitanzeige im Seitenfuß.
 */
$runtime = (string)file_get_contents(dirname(__DIR__, 2) . '/js/lib/runtime.js');

$assert = static function (bool $condition, string $message): void {
   if (!$condition) {
      fwrite(STDERR, "FAIL: {$message}\n");
      exit(1);
   }
};

$assert(
   str_contains($runtime, 'Number(nav.domContentLoadedEventEnd)'),
   'Die Browserlaufzeit endet nicht bei DOMContentLoaded.'
);
$assert(
   !str_contains($runtime, 'return nav.duration / 1000'),
   'Die Browserlaufzeit wartet weiterhin auf langsame Subressourcen und das load-Event.'
);
$assert(
   str_contains($runtime, 'document.addEventListener("DOMContentLoaded", update, { once: true })')
      && !str_contains($runtime, 'window.addEventListener("load", update, { once: true })'),
   'Die Anzeige wird nicht exakt einmal an der dokumentierten DOM-Grenze aktualisiert.'
);
$assert(
   str_contains($runtime, 'DOM- und JavaScript-Bereitschaft / PHP-Laufzeit'),
   'Die Bezeichnung erklärt die neue Messgrenze nicht.'
);

echo "OK footer runtime uses DOM/JavaScript readiness instead of window load.\n";
