<?php

class dbxDesignServiceTestApi {
   public string $root;
   public string $files;
   public array $config = array(
      'dbx' => array('default_design_user' => 'source', 'default_design_admin' => 'source'),
   );

   public function get_base_dir(): string {
      return rtrim($this->root, '/\\') . DIRECTORY_SEPARATOR;
   }

   public function get_file_dir(): string {
      return $this->files;
   }

   public function os_path(string $path): string {
      return str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
   }

   public function get_config(string $module, string $key = '', $default = 'undef') {
      if ($key === '') {
         return $this->config[$module] ?? $default;
      }
      return $this->config[$module][$key] ?? $default;
   }

   public function set_config(string $module, array $config): void {
      $this->config[$module] = $config;
   }
}

function dbx(): dbxDesignServiceTestApi {
   static $api;
   if (!$api) {
      $api = new dbxDesignServiceTestApi();
   }
   return $api;
}

$remove = static function(string $dir) use (&$remove): void {
   if (!is_dir($dir)) return;
   foreach (new FilesystemIterator($dir, FilesystemIterator::SKIP_DOTS) as $file) {
      if ($file->isDir()) $remove($file->getPathname());
      else unlink($file->getPathname());
   }
   rmdir($dir);
};

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dbx-design-service-test-' . bin2hex(random_bytes(5));
dbx()->root = $tmp;
dbx()->files = $tmp . DIRECTORY_SEPARATOR . 'files';
$source = $tmp . DIRECTORY_SEPARATOR . 'dbx' . DIRECTORY_SEPARATOR . 'design' . DIRECTORY_SEPARATOR . 'source';
foreach (array('htm', 'css', 'js', 'img') as $sub) {
   mkdir($source . DIRECTORY_SEPARATOR . $sub, 0777, true);
}
file_put_contents($source . '/htm/default.htm', '<html><head><title>{dbx:title}</title><link href="{dbx:skin_css}"></head><body class="{dbx:skin_class}" data-dbx-design="{dbx:design}">[dbx:content]<script src="dbx/js/lib/core.js?design={dbx:design}"></script></body></html>');
file_put_contents($source . '/css/colors.css', ':root{--dbx-primary:#123456}');
file_put_contents($source . '/css/base.css', '.source{background:url("../img/a.png")}');
file_put_contents($source . '/css/theme.css', '.card{}');

require_once dirname(__DIR__) . '/include/dbxDesignService.class.php';
$service = new \dbx\dbxDesign_admin\dbxDesignService();

$result = $service->createFromWizard(array_merge($service->defaults('source'), array(
   'source_design' => 'source',
   'target_design' => 'customer-design',
   'title' => 'Customer Design',
   'brand_name' => 'Customer',
   'tagline' => 'Einfach gut',
   'layout' => 'sidebar',
   'set_default' => 1,
)));

$target = $tmp . '/dbx/design/customer-design';
$defaultHtml = (string)file_get_contents($target . '/htm/default.htm');
$metadata = json_decode((string)file_get_contents($target . '/design.json'), true);

$fail = static function(string $message, int $code) use ($remove, $tmp): void {
   fwrite(STDERR, "FAIL: $message\n");
   $remove($tmp);
   exit($code);
};

if (($result['name'] ?? '') !== 'customer-design' || !is_dir($target)) {
   $fail('Wizard hat das Zielpaket nicht angelegt.', 1);
}
if (substr_count($defaultHtml, '[dbx:content]') !== 1) {
   $fail('Content-Slot ist nicht exakt einmal vorhanden.', 2);
}
foreach (array('[dbx:logo]', '[dbx:branding]', '[dbx:footer]') as $slot) {
   if (strpos($defaultHtml, $slot) === false) {
      $fail('Design-Slot fehlt: ' . $slot, 3);
   }
}
if (($metadata['contract'] ?? '') !== \dbx\dbxDesign_admin\dbxDesignService::CONTRACT) {
   $fail('design.json hat nicht den verbindlichen Vertrag.', 4);
}
if ((dbx()->config['dbx']['default_design_user'] ?? '') !== 'customer-design') {
   $fail('Explizites Setzen als Frontend-Standard wurde nicht gespeichert.', 5);
}
$validation = $service->validateDesignDirectory($target, true);
if (!empty($validation['errors'])) {
   $fail('Erzeugtes Design ist ungueltig: ' . implode(' ', $validation['errors']), 6);
}
if ($service->isAllowedDesignFile('../escape.php') || $service->isAllowedDesignFile('htm/evil.php')) {
   $fail('Dateigrenze akzeptiert einen unzulaessigen Pfad.', 7);
}

$delta = $tmp . '/result/design';
mkdir($delta . '/htm', 0777, true);
file_put_contents($delta . '/htm/branding.htm', '<strong>Customer Update</strong>');
$evil = $delta . '/htm/evil.htm';
file_put_contents($evil, '<button onclick="fetch(\'https://evil.invalid\')">x</button>');
if ($service->validateResultFile('htm/evil.htm', $evil) === '') {
   $fail('Aktiver Inline-/Fremdcode wurde im KI-Ergebnis akzeptiert.', 8);
}
unlink($evil);
$updated = $service->applyResult('source', 'customer-design', $delta, 'update');
if (empty($updated['backup']) || !is_file($updated['backup'])) {
   $fail('Update hat kein wiederherstellbares ZIP-Backup erstellt.', 9);
}
if ((string)file_get_contents($target . '/htm/branding.htm') !== '<strong>Customer Update</strong>') {
   $fail('Validiertes Update wurde nicht atomar uebernommen.', 10);
}

$remove($tmp);
echo "OK dbxDesignService contract\n";
