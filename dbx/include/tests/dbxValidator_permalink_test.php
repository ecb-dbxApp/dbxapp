<?php
$root = dirname(__DIR__, 3);
chdir($root);
$_SERVER['REQUEST_URI'] = '/dbxapp/';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SCRIPT_NAME'] = '/dbxapp/index.php';

if (!defined('dbxSystem')) {
    define('dbxSystem', 'dbxWebApp');
}
if (!defined('dbxRunAsAdmin')) {
    define('dbxRunAsAdmin', 1);
}

require_once $root . '/dbx/vendor/autoload.php';
require_once $root . '/dbx/include/dbxKernel.php';
require_once dirname(__DIR__) . '/dbxValidator.class.php';
require_once dirname(__DIR__, 2) . '/modules/dbxContent/include/dbxContent_permalink.class.php';
require_once dirname(__DIR__, 2) . '/modules/dbxHelp/include/dbxModuleHelp.class.php';

use dbx\dbxContent\dbxContent_permalink;

$validator = new dbxValidator();

foreach (array('home', 'help-dashboard-admin', 'seite-51', 'a1-b2') as $value) {
   if (!$validator->validate($value, 'permalink|min=1|max=254')) {
      throw new RuntimeException('Gueltiger Permalink abgelehnt: ' . $value);
   }
}

foreach (array('home/tutorial', 'zwei woerter', 'mit_unterstrich', 'datei.html', 'Gross', '-start', 'ende-', 'zwei--striche', 'äöü') as $value) {
   if ($validator->validate($value, 'permalink|min=1|max=254')) {
      throw new RuntimeException('Ungueltiger Permalink akzeptiert: ' . $value);
   }
}

if (dbxContent_permalink::normalize(' Home / Über uns ') !== 'home-ueber-uns') {
   throw new RuntimeException('Permalink-Normalisierung verwendet nicht ausschliesslich Bindestriche.');
}

$db = new class {
   public array $permalinks = array('neue-seite' => 7);

   public function select($dd, $where, $fields = '*', $order = '', $direction = '', $group = '', $limit = 0, $offset = 0, $debug = 0): array {
      if (!preg_match("/permalink = '([^']+)'/", (string)$where, $match)) {
         return array();
      }
      $permalink = str_replace("''", "'", $match[1]);
      return isset($this->permalinks[$permalink]) ? array(array('id' => $this->permalinks[$permalink])) : array();
   }
};

if (dbxContent_permalink::build($db, 'content_folder_de', 99, 'Neue Seite') !== 'neue-seite-2') {
   throw new RuntimeException('Automatische Permalinks sind nicht ordnerunabhaengig oder nicht eindeutig.');
}

if (dbxContent_permalink::unique($db, 'content_de', 'neue-seite', 7) !== 'neue-seite') {
   throw new RuntimeException('Eigener Datensatz wird bei der Eindeutigkeitspruefung nicht ausgeschlossen.');
}

echo "OK: Permalink-Regel, Normalisierung und statische Legacy-Aliase geprueft.\n";
