<?php
namespace dbx\dbxAdmin;

/**
 * Erkennt Modul-Metadaten aus Dateisystem und PHP-Quellen (ohne manuelle Registry).
 */
class dbxModuleRegistry {

   private $modules_root = '';
   private $group_labels = null;
   private $inspect_cache = array();
   private $images = null;

   public function __construct() {
      $this->modules_root = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/');
   }

   public function list_module_names(): array {
      $names = array();
      if (!is_dir($this->modules_root)) {
         return $names;
      }

      $dh = opendir($this->modules_root);
      if (!$dh) {
         return $names;
      }

      while (($file = readdir($dh)) !== false) {
         if ($file === '.' || $file === '..' || $file === 'tpl') {
            continue;
         }
         $path = $this->modules_root . $file;
         if (!is_dir($path)) {
            continue;
         }
         $names[] = $file;
      }
      closedir($dh);

      sort($names, SORT_NATURAL | SORT_FLAG_CASE);
      if (($key = array_search('dbx', $names, true)) !== false) {
         array_splice($names, $key, 1);
         array_unshift($names, 'dbx');
      }

      return $names;
   }

   public function inspect(string $modul): array {
      $modul = trim($modul);
      if (isset($this->inspect_cache[$modul])) {
         return $this->inspect_cache[$modul];
      }

      $base = $this->modules_root . $modul . DIRECTORY_SEPARATOR;
      $config = dbx()->get_cfg($modul);
      if (!is_array($config)) {
         $config = array();
      }

      $runs = $this->scan_runs($modul);
      $default_run1 = (string)($runs['default_run1'] ?? 'run');
      $default_run2 = (string)($runs['default_run2'] ?? '');
      $install = $this->detect_install($modul);
      $images = $this->images();
      $graphic = $images->module_symbol($modul);
      $image_items = $images->image_items($modul);
      $dd_list = $this->detect_dd_usage($modul);
      $dd_items = $this->build_dd_items($dd_list);
      $dd_count = count($dd_list);
      $description = $this->detect_description($modul, $base, $dd_list);
      $groups = $this->normalize_groups($config['groups'] ?? '*');
      $active = $this->is_active($config);
      $version = trim((string)($config['version'] ?? ''));

      $preview_modul = $modul;
      $preview_run1 = $default_run1;
      $preview_run2 = $default_run2;
      if ($modul === 'dbxHome') {
         $preview_run1 = '';
         $preview_run2 = '';
      }

      $preview_url = $this->build_modul_url($preview_modul, $preview_run1, $preview_run2);
      $install_url = $install['url'] ?? '';
      $config_url = $this->config_edit_url($modul);
      $access_url = '?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_access&dbx_run3=edit&xmodul=' . rawurlencode($modul);
      $help_url = '?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_help&xmodul=' . rawurlencode($modul);
      $avatar_url = '?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_avatar&xmodul=' . rawurlencode($modul);

      $this->inspect_cache[$modul] = array(
         'xmodul'          => $modul,
         'title'           => $modul,
         'description'     => $description,
         'version'         => $version !== '' ? $version : '—',
         'active'          => $active ? '1' : '0',
         'active_label'    => $active ? 'Aktiv' : 'Inaktiv',
         'active_class'    => $active ? 'success' : 'secondary',
         'groups'          => $groups,
         'groups_text'     => $this->groups_text($groups),
         'dd_list'         => $dd_list,
         'dd_items'        => $dd_items,
         'dd_count'        => $dd_count,
         'dd_select_id'    => 'dbx_mod_dd_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $modul),
         'dd_select_size'  => 6,
         'default_run1'    => $default_run1,
         'default_run2'    => $default_run2,
         'default_call'    => $this->format_default_call($modul, $default_run1, $default_run2),
         'preview_url'     => $preview_url,
         'install_url'     => $install_url,
         'has_install'     => !empty($install['url']) ? '1' : '0',
         'install_modul'   => (string)($install['modul'] ?? ''),
         'config_url'      => $config_url,
         'access_url'      => $access_url,
         'help_url'        => $help_url,
         'avatar_url'      => $avatar_url,
         'images_url'      => '?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_images&xmodul=' . rawurlencode($modul),
         'images_add_url'    => '?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_images_add',
         'images_upload_url' => '?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_images_upload',
         'images_remove_url' => '?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_images_remove',
         'symbol_add_url'    => '?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_symbol_add',
         'symbol_media_url'  => '?dbx_modul=dbxContent_admin&dbx_run1=cms_media&images=1&media_type=image',
         'symbol_upload_url' => '?dbx_modul=dbxContent_admin&dbx_run1=cms_upload',
         'symbol_mediafolders_url' => '?dbx_modul=dbxContent_admin&dbx_run1=cms_media_folders',
         'symbol_mediafoldercreate_url' => '?dbx_modul=dbxContent_admin&dbx_run1=cms_media_folder_create',
         'symbol_mediafolderdelete_url' => '?dbx_modul=dbxContent_admin&dbx_run1=cms_media_folder_delete',
         'symbol_deletemedia_url' => '?dbx_modul=dbxContent_admin&dbx_run1=cms_delete_media',
         'access_save_url'   => '?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_access_save',
         'active_toggle_url' => '?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_active_toggle',
         'delete_url'        => '?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_delete',
         'can_delete'        => $this->can_delete_module($modul) ? '1' : '0',
         'graphic_url'     => (string)($graphic['url'] ?? ''),
         'graphic_alt'     => (string)($graphic['alt'] ?? $modul),
         'graphic_badge'   => (string)($graphic['badge'] ?? ''),
         'placeholder_url' => $this->module_image_empty_placeholder_url(),
         'placeholder_alt' => $this->module_image_empty_placeholder_alt(),
         'module_images'   => array_values(array_map(function ($item) {
            return (string)($item['file'] ?? '');
         }, $image_items)),
         'image_items'     => $image_items,
         'image_count_cfg' => count($image_items),
         'has_class'       => is_file($base . $modul . '.class.php') ? '1' : '0',
         'run_cases'       => (array)($runs['run1'] ?? array()),
         'uses_run2'       => !empty($runs['uses_run2']) ? '1' : '0',
      );
      return $this->inspect_cache[$modul];
   }

   private function images(): dbxModuleImages {
      if (!class_exists('dbx\\dbxAdmin\\dbxModuleImages', false)) {
         require_once __DIR__ . '/dbxModuleImages.class.php';
      }
      if (!$this->images instanceof dbxModuleImages) {
         $this->images = new dbxModuleImages();
      }
      return $this->images;
   }

   public function dd_edit_url(string $modul, string $dd): string {
      return '?dbx_modul=dbxAdmin&dbx_run1=edit_dd&modul=' . rawurlencode($modul) . '&dd=' . rawurlencode($dd);
   }

   public function build_dd_items(array $dd_list): array {
      $items = array();
      foreach ($dd_list as $dd_ref) {
         $parts = explode('|', (string)$dd_ref, 2);
         $m = trim((string)($parts[0] ?? ''));
         $d = trim((string)($parts[1] ?? ''));
         if ($m === '' || $d === '') {
            continue;
         }
         $items[] = array(
            'ref'         => $dd_ref,
            'modul'       => $m,
            'dd'          => $d,
            'label'       => $d,
            'modul_label' => $m,
            'edit_url'    => $this->dd_edit_url($m, $d),
            'edit_title'  => 'DD bearbeiten: ' . $m . '/' . $d,
         );
      }
      return $items;
   }

   public function inspect_all(): array {
      $rows = array();
      foreach ($this->list_module_names() as $modul) {
         $rows[] = $this->inspect($modul);
      }
      return $rows;
   }

   public function group_options(): array {
      if (is_array($this->group_labels)) {
         return $this->group_labels;
      }

      $options = array(
         'admin'  => 'Admin',
         'guest'  => 'Gast',
         'member' => 'Mitglied',
         '*'      => 'Alle (*)',
      );

      $db = dbx()->get_system_obj('dbxDB');
      $user_groups = $db->select('dbxUser_groups', 'active = 1');
      if (is_array($user_groups)) {
         foreach ($user_groups as $record) {
            $id = trim((string)($record['name'] ?? ''));
            if ($id === '') {
               continue;
            }
            $options[$id] = trim((string)($record['description'] ?? $id));
         }
      }

      $this->group_labels = $options;
      return $this->group_labels;
   }

   private function groups_text(array $groups): string {
      if (!$groups) {
         return '—';
      }

      $labels = $this->group_options();
      $parts = array();
      foreach ($groups as $group) {
         $parts[] = $labels[$group] ?? $group;
      }

      return implode(', ', $parts);
   }

   private function normalize_groups($groups): array {
      if (is_array($groups)) {
         $out = array();
         foreach ($groups as $value) {
            $value = trim((string)$value);
            if ($value !== '') {
               $out[] = $value;
            }
         }
         return array_values(array_unique($out));
      }

      $text = trim((string)$groups);
      if ($text === '') {
         return array('*');
      }

      $parts = preg_split('/\s*,\s*/', $text, -1, PREG_SPLIT_NO_EMPTY);
      if (!is_array($parts) || !$parts) {
         return array($text);
      }

      return array_values(array_unique($parts));
   }

   private function is_active(array $config): bool {
      if (isset($config['activ'])) {
         return (string)$config['activ'] === '1';
      }
      if (isset($config['active'])) {
         return (string)$config['active'] === '1';
      }
      return true;
   }

   private function config_edit_url(string $modul): string {
      if ($modul === 'dbx') {
         return '?dbx_modul=dbxAdmin&dbx_run1=config&dbx_run2=edit&xmodul=dbx';
      }

      $file = 'dbx/modules/' . $modul . '/cfg/config.php';
      return '?dbx_modul=dbxEditor&dbx_run1=edit&file=' . rawurlencode($file);
   }

   private function module_class_files(string $modul): array {
      $base = $this->modules_root . $modul . DIRECTORY_SEPARATOR;
      $files = array();
      $main = $base . $modul . '.class.php';
      if (is_file($main)) {
         $files[] = $main;
      }
      $inc = $base . 'include' . DIRECTORY_SEPARATOR;
      if (is_dir($inc)) {
         foreach (glob($inc . '*.class.php') ?: array() as $path) {
            if (is_file($path)) {
               $files[] = $path;
            }
         }
      }
      return $files;
   }

   public function scan_runs_public(string $modul): array {
      return $this->scan_runs($modul);
   }

   private function scan_runs(string $modul): array {
      $run1 = array();
      $default_run1 = '';
      $default_run2 = '';
      $uses_run2 = false;

      foreach ($this->module_class_files($modul) as $file) {
         $src = @file_get_contents($file);
         if (!is_string($src) || $src === '') {
            continue;
         }

         if (preg_match("/get_modul_var\s*\(\s*['\"]dbx_run2['\"]/", $src)) {
            $uses_run2 = true;
         }

         if ($default_run1 === '' && preg_match("/get_modul_var\s*\(\s*['\"]dbx_run1['\"][^,]*,\s*['\"]([^'\"]+)['\"]/", $src, $match)) {
            $candidate = trim((string)$match[1]);
            if ($candidate !== '' && $candidate !== 'parameter') {
               $default_run1 = $candidate;
            }
         }

         if ($default_run2 === '' && preg_match("/get_modul_var\s*\(\s*['\"]dbx_run2['\"][^,]*,\s*['\"]([^'\"]+)['\"]/", $src, $match)) {
            $candidate = trim((string)$match[1]);
            if ($candidate !== '' && $candidate !== 'parameter') {
               $default_run2 = $candidate;
            }
         }

         if (preg_match_all("/case\s+['\"]([^'\"]+)['\"]\s*:/", $src, $matches)) {
            foreach ($matches[1] as $case) {
               $case = trim((string)$case);
               if ($case === '' || $case === 'default') {
                  continue;
               }
               $run1[$case] = true;
            }
         }

         if (preg_match_all('/\$(?:run|action|work)\s*===?\s*[\'"]([^\'"]+)[\'"]/', $src, $ifs)) {
            foreach ($ifs[1] as $case) {
               $case = trim((string)$case);
               if ($case !== '') {
                  $run1[$case] = true;
               }
            }
         }
      }

      if ($default_run1 === '') {
         $keys = array_keys($run1);
         $default_run1 = $keys ? (string)$keys[0] : 'run';
      }

      if ($modul === 'dbxAdmin' && isset($run1['run'])) {
         $default_run1 = 'run';
      }
      if ($modul === 'dbxHome') {
         $default_run1 = '';
      }

      return array(
         'run1'          => array_keys($run1),
         'default_run1'  => $default_run1,
         'default_run2'  => $default_run2,
         'uses_run2'     => $uses_run2,
      );
   }

   private function detect_install(string $modul): array {
      $candidates = array($modul);
      if (substr($modul, -6) !== '_admin') {
         $candidates[] = $modul . '_admin';
      }

      foreach ($candidates as $candidate) {
         foreach ($this->module_class_files($candidate) as $file) {
            $src = @file_get_contents($file);
            if (!is_string($src) || $src === '') {
               continue;
            }
            if (!preg_match("/case\s+['\"]install['\"]\s*:/", $src)
                && !preg_match('/\$(?:run|action|work)\s*===?\s*[\'"]install[\'"]/', $src)) {
               continue;
            }

            return array(
               'modul' => $candidate,
               'url'   => $this->build_modul_url($candidate, 'install'),
            );
         }
      }

      return array();
   }

   private function detect_dd_usage(string $modul): array {
      $found = array();

      $dd_dir = $this->modules_root . $modul . DIRECTORY_SEPARATOR . 'dd' . DIRECTORY_SEPARATOR;
      if (is_dir($dd_dir)) {
         foreach (glob($dd_dir . '*.dd.php') ?: array() as $file) {
            $name = basename($file, '.dd.php');
            if ($name === '' || $name === 'new' || str_starts_with($name, '.')) {
               continue;
            }
            $this->register_dd_ref($found, $modul, $name);
         }
      }

      foreach ($this->module_class_files($modul) as $file) {
         $src = @file_get_contents($file);
         if (!is_string($src) || $src === '') {
            continue;
         }

         if (preg_match_all("/['\"]" . preg_quote($modul, '/') . "\|([a-zA-Z][a-zA-Z0-9_]*)['\"]/", $src, $matches)) {
            foreach ($matches[1] as $dd) {
               $this->register_dd_ref($found, $modul, $dd);
            }
         }

         if (preg_match_all("/_dd\s*=\s*['\"]([a-zA-Z][a-zA-Z0-9_]*(?:\|[a-zA-Z][a-zA-Z0-9_]*)?)['\"]/", $src, $matches)) {
            foreach ($matches[1] as $dd_ref) {
               $this->register_dd_ref_string($found, $modul, $dd_ref);
            }
         }

         if (preg_match_all("/load_dd\s*\(\s*['\"]([a-zA-Z][a-zA-Z0-9_]*(?:\|[a-zA-Z][a-zA-Z0-9_]*)?)['\"]/", $src, $matches)) {
            foreach ($matches[1] as $dd_ref) {
               $this->register_dd_ref_string($found, $modul, $dd_ref);
            }
         }
      }

      $list = array_keys($found);
      sort($list, SORT_NATURAL | SORT_FLAG_CASE);
      return $list;
   }

   private function is_valid_ident(string $name): bool {
      return (bool)preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $name);
   }

   private function dd_file_exists(string $modul, string $dd): bool {
      $path = $this->modules_root . $modul . DIRECTORY_SEPARATOR . 'dd' . DIRECTORY_SEPARATOR . $dd . '.dd.php';
      return is_file($path);
   }

   private function register_dd_ref(array &$found, string $modul, string $dd): void {
      $modul = trim($modul);
      $dd = trim($dd);
      if (!$this->is_valid_ident($modul) || !$this->is_valid_ident($dd)) {
         return;
      }
      if (!$this->dd_file_exists($modul, $dd)) {
         return;
      }
      $found[$modul . '|' . $dd] = true;
   }

   private function register_dd_ref_string(array &$found, string $modul, string $dd_ref): void {
      $dd_ref = trim($dd_ref);
      if ($dd_ref === '') {
         return;
      }
      if (strpos($dd_ref, '|') !== false) {
         $parts = explode('|', $dd_ref, 2);
         $this->register_dd_ref($found, $parts[0], $parts[1]);
         return;
      }
      $this->register_dd_ref($found, $modul, $dd_ref);
   }

   private function detect_description(string $modul, string $base, array $dd_list = array()): string {
      $candidates = array(
         $base . 'cfg' . DIRECTORY_SEPARATOR . 'about.txt',
         $base . 'cfg' . DIRECTORY_SEPARATOR . 'help.txt',
         $base . $modul . '.class.php',
         $base . 'cfg' . DIRECTORY_SEPARATOR . 'config.php',
      );

      foreach ($candidates as $file) {
         if (!is_file($file)) {
            continue;
         }
         $text = $this->read_lead_comment($file);
         if ($text !== '') {
            return $this->trim_description($text);
         }
      }

      $runs = $this->scan_runs($modul);
      $parts = array();
      $parts[] = 'Standardaufruf: ' . $this->format_default_call($modul, (string)($runs['default_run1'] ?? 'run'), (string)($runs['default_run2'] ?? ''));
      if ($dd_list) {
         $parts[] = count($dd_list) . ' DataDic-Datei(en)';
      }

      return implode(' · ', $parts);
   }

   private function read_lead_comment(string $file): string {
      $src = @file_get_contents($file);
      if (!is_string($src) || $src === '') {
         return '';
      }

      if (preg_match('/\A\s*<\?php\s*/', $src)) {
         $src = preg_replace('/\A\s*<\?php\s*/', '', $src, 1);
      }

      if (preg_match('/\A\s*\/\*\*(.*?)\*\//s', $src, $match)) {
         return $this->clean_comment_block($match[1]);
      }
      if (preg_match('/\A\s*\/\*(.*?)\*\//s', $src, $match)) {
         return $this->clean_comment_block($match[1]);
      }

      return '';
   }

   private function clean_comment_block(string $text): string {
      $lines = preg_split('/\R/', $text);
      $out = array();
      foreach ($lines as $line) {
         $line = preg_replace('/^\s*\*\s?/', '', $line);
         $line = trim((string)$line);
         if ($line === '' || preg_match('/^@/', $line)) {
            continue;
         }
         $out[] = $line;
      }
      return trim(implode(' ', $out));
   }

   private function trim_description(string $text): string {
      $text = preg_replace('/\s+/', ' ', trim($text));
      if (strlen($text) > 320) {
         $text = substr($text, 0, 317) . '…';
      }
      return $text;
   }

   public function placeholder_graphic(string $modul, string $run1 = '', string $run2 = ''): array {
      return $this->resolve_mod_graphic($modul, $run1, $run2);
   }

   public function module_image_empty_placeholder_url(): string {
      static $cache = array();
      $dir = dbx()->get_base_dir() . 'dbx/modules/dbxAdmin/tpl/img/';
      $lng = dbx()->lng_current();
      if ($lng === '') {
         $lng = 'de';
      }
      if (isset($cache[$lng])) {
         return $cache[$lng];
      }

      $candidates = array(
         'modul-no-image_' . $lng . '.svg',
         'modul-no-image.svg',
         'modul-no-image_de.svg',
         'modul-no-image_en.svg',
      );

      $path = '';
      foreach ($candidates as $file) {
         $try = $dir . $file;
         if (is_file($try)) {
            $path = $try;
            break;
         }
      }

      if ($path === '') {
         $path = dbx()->lng_resolve_file($dir, 'modul-no-image', 'svg');
      }

      if ($path === '' || !is_file($path)) {
         $cache[$lng] = '';
         return '';
      }

      $file = basename(str_replace('\\', '/', $path));
      $cache[$lng] = dbx()->get_base_url() . 'dbx/modules/dbxAdmin/tpl/img/' . rawurlencode($file);
      return $cache[$lng];
   }

   public function module_image_empty_placeholder_alt(): string {
      static $cache = array();
      $lng = dbx()->lng_current();
      if (isset($cache[$lng])) {
         return $cache[$lng];
      }
      if ($lng === 'en') {
         $cache[$lng] = 'No module image selected';
         return $cache[$lng];
      }
      if ($lng === 'es') {
         $cache[$lng] = 'Sin imagen de módulo seleccionada';
         return $cache[$lng];
      }
      $cache[$lng] = 'Kein Modulbild ausgewählt';
      return $cache[$lng];
   }

   private function resolve_mod_graphic(string $modul, string $run1, string $run2): array {
      $url_base = dbx()->get_base_url() . 'dbx/modules/' . rawurlencode($modul) . '/';
      $mod_dir = $this->modules_root . $modul . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'mod' . DIRECTORY_SEPARATOR;

      $candidates = array();
      if ($run1 !== '' && $run2 !== '') {
         $candidates[] = $modul . '_' . $run1 . '_' . $run2 . '.svg';
      }
      if ($run1 !== '') {
         $candidates[] = $modul . '_' . $run1 . '.svg';
      }
      $candidates[] = $modul . '.svg';

      if (is_dir($mod_dir)) {
         foreach ($candidates as $name) {
            if (is_file($mod_dir . $name)) {
               return array(
                  'url'   => $url_base . 'tpl/mod/' . rawurlencode($name),
                  'alt'   => $modul . ' ' . $run1,
                  'badge' => $name,
               );
            }
         }

         $any = glob($mod_dir . '*.svg');
         if (is_array($any) && isset($any[0]) && is_file($any[0])) {
            $name = basename($any[0]);
            return array(
               'url'   => $url_base . 'tpl/mod/' . rawurlencode($name),
               'alt'   => $modul,
               'badge' => $name,
            );
         }
      }

      $img_path = $this->modules_root . $modul . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'modul.gif';
      if (is_file($img_path)) {
         return array(
            'url'   => $url_base . 'tpl/img/modul.gif',
            'alt'   => $modul,
            'badge' => 'modul.gif',
         );
      }

      return array(
         'url'   => dbx()->get_base_url() . 'dbx/modules/dbxAdmin/tpl/mod/dbxAdmin_modul.svg',
         'alt'   => $modul,
         'badge' => 'placeholder',
      );
   }

   public function modul_url(string $modul, string $run1 = '', string $run2 = ''): string {
      return $this->build_modul_url($modul, $run1, $run2);
   }

   public function can_delete_module(string $modul): bool {
      $modul = trim($modul);
      if ($modul === '' || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $modul)) {
         return false;
      }
      $protected = array('dbx', 'dbxAdmin', 'dbxLogin', 'dbxHome');
      if (in_array($modul, $protected, true)) {
         return false;
      }
      $dir = $this->modules_root . $modul;
      return is_dir($dir);
   }

   private function build_modul_url(string $modul, string $run1 = '', string $run2 = ''): string {
      $url = '?dbx_modul=' . rawurlencode($modul);
      if ($run1 !== '') {
         $url .= '&dbx_run1=' . rawurlencode($run1);
      }
      if ($run2 !== '') {
         $url .= '&dbx_run2=' . rawurlencode($run2);
      }
      return $url;
   }

   private function format_default_call(string $modul, string $run1, string $run2): string {
      $parts = array('dbx_modul=' . $modul);
      if ($run1 !== '') {
         $parts[] = 'dbx_run1=' . $run1;
      }
      if ($run2 !== '') {
         $parts[] = 'dbx_run2=' . $run2;
      }
      return implode('&', $parts);
   }

   public function module_help_path(string $modul): string {
      $modul = trim($modul);
      if ($modul === '') {
         return '';
      }
      $path = $this->modules_root . $modul . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'help' . DIRECTORY_SEPARATOR . 'modul.htm';
      return is_file($path) ? $path : '';
   }

   public function form_help_path(string $modul, string $form): string {
      $modul = trim($modul);
      $form = strtolower(trim($form));
      if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $modul) || $form === '') {
         return '';
      }
      $form = trim((string)preg_replace('/[^a-z0-9_-]+/', '-', $form), '-');
      if ($form === '') {
         return '';
      }
      $path = $this->modules_root . $modul . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'help'
         . DIRECTORY_SEPARATOR . 'form-' . $form . '.htm';
      return is_file($path) ? $path : '';
   }

   public function render_module_help(string $modul, array $data = array()): string {
      $modul = trim($modul);
      if ($modul === '') {
         return '';
      }

      $tpl = dbx()->get_system_obj('dbxTPL');
      if ($this->module_help_path($modul) === '') {
         if (!isset($data['modul'])) {
            $data['modul'] = dbx()->esc($modul);
         }
         return $tpl->get_tpl('dbxAdmin|modul-help-fallback', $data);
      }

      return $tpl->get_help_tpl($modul, 'modul', $data);
   }

   public function render_form_help(string $modul, string $form, array $data = array()): string {
      $path = $this->form_help_path($modul, $form);
      if ($path === '') {
         return $this->render_module_help($modul, $data);
      }

      $name = basename($path, '.htm');
      return dbx()->get_system_obj('dbxTPL')->get_help_tpl($modul, $name, $data);
   }
}
