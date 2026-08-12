<?php
/**
 * Regressionstest für die ruhigen Metallflächen des Steal-Designs.
 *
 * Verläufe in Cards dürfen nicht als kleine Kacheln wiederholt werden.
 * Der technische Hintergrund verwendet stattdessen eine einzelne,
 * viewportbreite Zahnrad-Gravur über dem Riffelblech.
 */

$root = dirname(__DIR__, 2);
$metalFile = $root . '/design/steal/css/steal-metal.css';
$riffelFile = $root . '/design/steal/css/steal-riffel-chrome.css';
$templateFile = $root . '/design/steal/htm/default.htm';
$manifestFile = $root . '/design/steal/design.json';
$gearFile = $root . '/design/steal/img/dbx-page-gears-v1.svg';

$fail = static function (string $message, int $code): void {
   fwrite(STDERR, "FAIL: $message\n");
   exit($code);
};

$read = static function (string $file) use ($fail): string {
   $content = is_file($file) ? file_get_contents($file) : false;
   if (!is_string($content)) {
      $fail('Design-Datei fehlt oder ist nicht lesbar: ' . $file, 1);
   }
   return $content;
};

$rule = static function (string $css, string $selector) use ($fail): string {
   $start = strpos($css, $selector);
   if ($start === false) {
      $fail('CSS-Vertrag fehlt: ' . $selector, 2);
   }
   $open = strpos($css, '{', $start);
   $close = $open !== false ? strpos($css, '}', $open) : false;
   if ($open === false || $close === false) {
      $fail('CSS-Regel ist unvollständig: ' . $selector, 3);
   }
   return substr($css, $open + 1, $close - $open - 1);
};

$metal = $read($metalFile);
$riffel = $read($riffelFile);
$template = $read($templateFile);
$manifest = json_decode($read($manifestFile), true);
$gears = $read($gearFile);

foreach (array(
   $rule($metal, 'body.dbx-steal .c-cms :is(.card, .alert, .list-group-item)'),
   $rule($riffel, 'body.dbx-steal-riffel :is(.card, .dbx-card, .list-group-item, .alert)'),
) as $cardRule) {
   if (strpos($cardRule, 'background-repeat: no-repeat !important') === false
       || strpos($cardRule, 'background-size: 100% 100% !important') === false
       || strpos($cardRule, 'repeating-linear-gradient') !== false
       || strpos($cardRule, 'riffelblech') !== false) {
      $fail('Eine Steal-Card kann wieder gekachelt oder mit Riffelblech gefüllt werden.', 4);
   }
}

$mainRule = $rule($riffel, 'body.dbx-steal-riffel #dbxMain');
if (strpos($mainRule, 'dbx-page-gears-v1.svg') === false
    || strpos($mainRule, 'background-repeat: no-repeat, no-repeat, repeat !important') === false
    || strpos($mainRule, 'background-size: 100vw auto') === false) {
   $fail('Die einmalige viewportbreite Zahnrad-Gravur fehlt im Seitenhintergrund.', 5);
}

$assets = is_array($manifest) ? (array)($manifest['material_system']['assets'] ?? array()) : array();
if (!in_array('img/dbx-page-gears-v1.svg', $assets, true)
    || substr_count($gears, '<use href="#engraved-gear"/>') < 5) {
   $fail('Zahnrad-Asset oder Manifest-Eintrag ist unvollständig.', 6);
}

if (strpos($template, 'steal-metal.css?v={dbx:asset_version}') === false
    || strpos($template, 'steal-riffel-chrome.css?v={dbx:asset_version}') === false) {
   $fail('Die Designschale verwendet nicht die aktuellen Cache-Versionen.', 7);
}

echo "OK Steal surface contract\n";
