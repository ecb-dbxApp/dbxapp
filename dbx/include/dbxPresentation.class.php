<?php

declare(strict_types=1);

/** Zentrale Kernel-Darstellung fuer Designs, Skins und ihre Metadaten. */
final class dbxPresentation {

   /** @var array<string,array<int,string>> */
   private array $skin_ids = array();

   /** @var array<string,array{name:string,title:string,dir:string,meta:array,managed:bool}>|null */
   private ?array $design_catalog = null;

   /** Liefert die durch skin-*.css bereitgestellten Skin-IDs eines Designs. */
   public function get_design_skin_ids(string $design = ''): array {
      $design = $this->resolve_skin_design($design);
      if (isset($this->skin_ids[$design])) {
         return $this->skin_ids[$design];
      }

      $skins = array();
      $pattern = dbx()->get_base_dir() . 'dbx/design/' . $design . '/css/skin-*.css';
      foreach (glob($pattern) ?: array() as $file) {
         $name = pathinfo($file, PATHINFO_FILENAME);
         $skin = preg_replace('/^skin-/', '', (string)$name);
         if ($skin !== '' && preg_match('/^[a-z0-9][a-z0-9_-]*$/', $skin)) {
            $skins[$skin] = $skin;
         }
      }

      $preferred = array_flip(array('hell', 'gelb', 'rot', 'gruen', 'blau', 'dunkel'));
      uasort($skins, static function(string $a, string $b) use ($preferred): int {
         $a_rank = $preferred[$a] ?? 1000;
         $b_rank = $preferred[$b] ?? 1000;
         return $a_rank === $b_rank ? strnatcasecmp($a, $b) : $a_rank <=> $b_rank;
      });

      $this->skin_ids[$design] = array_values($skins);
      return $this->skin_ids[$design];
   }

   /** Normalisiert Skin-/Farbnamen auf die vom Design angebotenen IDs. */
   public function normalize_skin(string $skin = '', string $design = ''): string {
      $skin = strtolower(trim($skin));
      $skin_ids = $this->get_design_skin_ids($design);
      $map = array(
         'blue' => 'blau', 'blau' => 'blau',
         'green' => 'gruen', 'gruen' => 'gruen', 'grün' => 'gruen',
         'red' => 'rot', 'rot' => 'rot',
         'black' => 'dunkel', 'dark' => 'dunkel', 'dunkel' => 'dunkel',
         'yellow' => 'gelb', 'gelb' => 'gelb',
         'light' => 'hell', 'hell' => 'hell', 'white' => 'hell',
      );

      if ($skin !== '' && isset($map[$skin])) {
         $skin = $map[$skin];
      }
      if ($skin === '' || !in_array($skin, $skin_ids, true)) {
         $configured = strtolower(trim((string)dbx()->get_cfg('dbx', 'default_color', 'blau')));
         $skin = $map[$configured] ?? $configured;
      }
      if (!in_array($skin, $skin_ids, true)) {
         $skin = in_array('blau', $skin_ids, true)
            ? 'blau'
            : (in_array('hell', $skin_ids, true) ? 'hell' : (string)($skin_ids[0] ?? 'blau'));
      }
      return $skin;
   }

   /** Liefert den aktiven Skin. */
   public function get_skin(): string {
      return $this->normalize_skin((string)dbx()->get_system_var('dbx_color', ''));
   }

   /** Liefert den CSS-Pfad des aktiven Skins relativ zum Projektroot. */
   public function get_skin_css(): string {
      $design = $this->resolve_skin_design((string)dbx()->get_system_var(
         'dbx_activ_design',
         dbx()->get_system_var('dbx_design', 'dbxapp')
      ));
      $skin = $this->normalize_skin((string)dbx()->get_system_var('dbx_color', ''), $design);
      return 'dbx/design/' . $design . '/css/skin-' . $skin . '.css';
   }

   /** Liefert die Body-Klassen des aktiven Skins. */
   public function get_skin_class(): string {
      $skin = $this->get_skin();
      return 'skin-' . $skin . ($skin === 'dunkel' ? ' theme-dark' : '');
   }

   /** Liefert alle verwendbaren Designs aus einem request-lokalen Katalog. */
   public function get_design_catalog(bool $refresh = false): array {
      if ($this->design_catalog !== null && !$refresh) {
         return $this->design_catalog;
      }

      $catalog = array();
      $root = rtrim(dbx()->os_path(dbx()->get_base_dir() . 'dbx/design'), '/\\');
      foreach (glob($root . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: array() as $dir) {
         $name = basename($dir);
         if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]{1,62}$/', $name)
             || !is_file($dir . DIRECTORY_SEPARATOR . 'htm' . DIRECTORY_SEPARATOR . 'default.htm')) {
            continue;
         }
         $meta_file = $dir . DIRECTORY_SEPARATOR . 'design.json';
         $meta = array();
         if (is_file($meta_file)) {
            $decoded = json_decode((string)file_get_contents($meta_file), true);
            $meta = is_array($decoded) ? $decoded : array();
         }
         $title = trim((string)($meta['title'] ?? ''));
         if ($title === '') {
            $title = strtolower($name) === 'dbxapp'
               ? 'dbXapp'
               : ucfirst(str_replace(array('-', '_'), ' ', $name));
         }
         $catalog[$name] = array(
            'name' => $name,
            'title' => $title,
            'dir' => $dir,
            'meta' => $meta,
            'managed' => is_file($meta_file),
         );
      }
      uasort($catalog, static fn(array $a, array $b): int => strnatcasecmp($a['title'], $b['title']));
      $this->design_catalog = $catalog;
      return $catalog;
   }

   /** Prüft, ob ein Design fuer die angegebene Seite existiert. */
   public function is_design(string $design, string $page = 'default'): bool {
      $first_char = substr($design, 0, 1);
      if (!dbx()->has_group('admin') && ($first_char === '_' || $first_char === '-')) {
         return false;
      }
      $template = dbx()->get_base_dir() . "dbx/design/$design/htm/$page.htm";
      if (is_file($template)) {
         return true;
      }
      return $page !== 'default'
         && is_file(dbx()->get_base_dir() . "dbx/design/$design/htm/default.htm");
   }

   /** Löst Design-Aliase auf und verwirft unsichere Verzeichnisnamen. */
   private function resolve_skin_design(string $design = ''): string {
      $design = trim($design);
      if ($design === '') {
         $design = (string)dbx()->get_system_var(
            'dbx_activ_design',
            dbx()->get_system_var('dbx_design', 'dbxapp')
         );
      }
      $key = strtolower($design);
      if ($key === 'user' || $key === 'admin') {
         $config = dbx()->get_cfg('dbx');
         $design = (string)($config[$key === 'admin' ? 'default_design_admin' : 'default_design_user'] ?? 'dbxapp');
      } elseif ($key === 'fleurop') {
         $design = 'flowers';
      }
      return preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]*$/', $design) ? $design : 'dbxapp';
   }
}
