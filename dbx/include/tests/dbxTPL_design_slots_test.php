<?php

class dbxObj {}

class dbxTPLDesignSlotsTestApi {
   public string $root;
   public array $registered = array();
   public array $system = array('dbx_design' => 'demo', 'dbx_activ_design' => 'demo');

   public function get_base_dir(): string { return rtrim($this->root, '/\\') . DIRECTORY_SEPARATOR; }
   public function get_base_url(): string { return 'https://localhost/dbxapp/'; }
   public function get_system_var(string $key, $default = '', ...$rest) { return $this->system[$key] ?? $default; }
   public function get_request_var(string $key, $default = '', ...$rest) { return $default; }
   public function get_skin(): string { return 'blau'; }
   public function get_skin_css(): string { return 'skin-blau.css'; }
   public function get_skin_class(): string { return 'skin-blau'; }
   public function os_path(string $path): string { return str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path); }
   public function register_editor_file(string $kind, string $file): void { $this->registered[] = array($kind, $file); }

   public function lng_resolve_file(string $dir, string $name, string $ext, string $lng = '', bool $fallback = true): string {
      $dir = str_replace('\\', '/', $dir);
      if ($dir !== '' && substr($dir, -1) !== '/') $dir .= '/';
      $path = $dir . strtolower(trim($name)) . '.' . ltrim(strtolower(trim($ext)), '.');
      return is_file($path) ? $this->os_path($path) : '';
   }
}

function dbx(): dbxTPLDesignSlotsTestApi {
   static $api;
   if (!$api) $api = new dbxTPLDesignSlotsTestApi();
   return $api;
}

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dbx-tpl-slots-' . bin2hex(random_bytes(5));
dbx()->root = $root;
$htm = $root . DIRECTORY_SEPARATOR . 'dbx' . DIRECTORY_SEPARATOR . 'design' . DIRECTORY_SEPARATOR . 'demo' . DIRECTORY_SEPARATOR . 'htm';
mkdir($htm, 0777, true);
file_put_contents($htm . '/logo.htm', '<img src="../img/logo.png" data-design="{dbx:design}">');
file_put_contents($htm . '/branding.htm', '<strong>Demo</strong>');
file_put_contents($htm . '/footer.htm', '<footer>{dbx:color}</footer>');

require_once dirname(__DIR__) . '/dbxTPL.class.php';
$tpl = new dbxTPL();
$out = $tpl->replace_design_slots('[dbx:logo]|[dbx:branding]|[dbx:footer]', 'demo');

$expected = '<img src="dbx/design/demo/img/logo.png" data-design="demo">|<strong>Demo</strong>|<footer>blau</footer>';
if ($out !== $expected) {
   fwrite(STDERR, "FAIL: Design-Slots wurden nicht richtig eingesetzt.\n$out\n");
   exit(1);
}
$missing = $tpl->replace_design_slots('A[dbx:logo]B[dbx:footer]C', 'missing');
if ($missing !== 'ABC') {
   fwrite(STDERR, "FAIL: Fehlende Fragmente wurden nicht leer aufgeloest.\n");
   exit(2);
}
file_put_contents($htm . '/branding.htm', '<strong>Demo</strong>[dbx:content][dbx:footer]');
$guarded = $tpl->replace_design_slots('[dbx:branding]', 'demo');
if ($guarded !== '<strong>Demo</strong>') {
   fwrite(STDERR, "FAIL: Verschachtelte Design-/Content-Slots wurden nicht abgefangen.\n");
   exit(3);
}

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
   $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
}
rmdir($root);
echo "OK dbxTPL design slots\n";
