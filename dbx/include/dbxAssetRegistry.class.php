<?php

declare(strict_types=1);

/** Registriert modul-eigene CSS- und JavaScript-Dateien fuer einen Request. */
final class dbxAssetRegistry {

   /** @var array{css:array<string,string>,js:array<string,string>} */
   private array $assets = array('css' => array(), 'js' => array());

   /** Registriert eine CSS-Datei aus tpl/css/ des Moduls. */
   public function add_css(string $module, string $file): void {
      $this->queue('css', $module, $file);
   }

   /** Registriert eine JavaScript-Datei aus js/ des Moduls. */
   public function add_js(string $module, string $file): void {
      $this->queue('js', $module, $file);
   }

   /** Liefert registrierte relative Assetpfade ohne Duplikate. */
   public function get_assets(string $type): array {
      return array_values($this->assets[$type] ?? array());
   }

   /** Validiert und merkt eine Moduldatei in Registrierreihenfolge vor. */
   private function queue(string $type, string $module, string $file): void {
      if ($type !== 'css' && $type !== 'js') {
         return;
      }
      $module = trim($module);
      $file = trim($file);
      if ($module === '' || $file === '' || preg_match('/^[A-Za-z0-9_]+$/', $module) !== 1) {
         return;
      }
      if (str_contains($file, '/') || str_contains($file, '\\') || str_contains($file, '..')) {
         return;
      }
      $extension = '.' . $type;
      if (!str_ends_with($file, $extension)) {
         $file .= $extension;
      }
      $relative_path = $type === 'js'
         ? 'modules/' . $module . '/js/' . $file
         : 'modules/' . $module . '/tpl/css/' . $file;
      if (!is_file(dbx()->get_base_dir() . 'dbx/' . $relative_path)) {
         return;
      }
      $this->assets[$type][$module . '/' . $file] = $relative_path;
   }
}
