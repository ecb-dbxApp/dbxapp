<?php
namespace dbx\dbxAdmin;

/**
 * Erkennt Modul-Metadaten aus Dateisystem und PHP-Quellen (ohne manuelle Registry).
 */
class dbxModuleRegistry {

   private $modulesRoot = '';
   private $groupLabels = null;
   private $inspectCache = array();
   private $images = null;

   public function __construct() {
      $this->modulesRoot = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/');
   }

   public function listModuleNames(): array {
      $names = array();
      if (!is_dir($this->modulesRoot)) {
         return $names;
      }

      $dh = opendir($this->modulesRoot);
      if (!$dh) {
         return $names;
      }

      while (($file = readdir($dh)) !== false) {
         if ($file === '.' || $file === '..' || $file === 'tpl') {
            continue;
         }
         $path = $this->modulesRoot . $file;
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
      if (isset($this->inspectCache[$modul])) {
         return $this->inspectCache[$modul];
      }

      $base = $this->modulesRoot . $modul . DIRECTORY_SEPARATOR;
      $config = dbx()->get_config($modul);
      if (!is_array($config)) {
         $config = array();
      }

      $runs = $this->scanRuns($modul);
      $defaultRun1 = (string)($runs['default_run1'] ?? 'run');
      $defaultRun2 = (string)($runs['default_run2'] ?? '');
      $install = $this->detectInstall($modul);
      $images = $this->images();
      $graphic = $images->moduleSymbol($modul);
      $imageItems = $images->imageItems($modul);
      $ddList = $this->detectDdUsage($modul);
      $ddItems = $this->buildDdItems($ddList);
      $ddCount = count($ddList);
      $description = $this->detectDescription($modul, $base, $ddList);
      $groups = $this->normalizeGroups($config['groups'] ?? '*');
      $active = $this->isActive($config);
      $version = trim((string)($config['version'] ?? ''));

      $previewModul = $modul;
      $previewRun1 = $defaultRun1;
      $previewRun2 = $defaultRun2;
      if ($modul === 'dbxHome') {
         $previewRun1 = '';
         $previewRun2 = '';
      }

      $previewUrl = $this->buildModulUrl($previewModul, $previewRun1, $previewRun2);
      $installUrl = $install['url'] ?? '';
      $configUrl = $this->configEditUrl($modul);
      $accessUrl = '?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_access&dbx_run3=edit&xmodul=' . rawurlencode($modul);
      $helpUrl = '?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_help&xmodul=' . rawurlencode($modul);
      $avatarUrl = '?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_avatar&xmodul=' . rawurlencode($modul);

      $this->inspectCache[$modul] = array(
         'xmodul'          => $modul,
         'title'           => $modul,
         'description'     => $description,
         'version'         => $version !== '' ? $version : '—',
         'active'          => $active ? '1' : '0',
         'active_label'    => $active ? 'Aktiv' : 'Inaktiv',
         'active_class'    => $active ? 'success' : 'secondary',
         'groups'          => $groups,
         'groups_text'     => $this->groupsText($groups),
         'dd_list'         => $ddList,
         'dd_items'        => $ddItems,
         'dd_count'        => $ddCount,
         'dd_select_id'    => 'dbx_mod_dd_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $modul),
         'dd_select_size'  => 6,
         'default_run1'    => $defaultRun1,
         'default_run2'    => $defaultRun2,
         'default_call'    => $this->formatDefaultCall($modul, $defaultRun1, $defaultRun2),
         'preview_url'     => $previewUrl,
         'install_url'     => $installUrl,
         'has_install'     => !empty($install['url']) ? '1' : '0',
         'install_modul'   => (string)($install['modul'] ?? ''),
         'config_url'      => $configUrl,
         'access_url'      => $accessUrl,
         'help_url'        => $helpUrl,
         'avatar_url'      => $avatarUrl,
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
         'can_delete'        => $this->canDeleteModule($modul) ? '1' : '0',
         'graphic_url'     => (string)($graphic['url'] ?? ''),
         'graphic_alt'     => (string)($graphic['alt'] ?? $modul),
         'graphic_badge'   => (string)($graphic['badge'] ?? ''),
         'placeholder_url' => $this->moduleImageEmptyPlaceholderUrl(),
         'placeholder_alt' => $this->moduleImageEmptyPlaceholderAlt(),
         'module_images'   => array_values(array_map(function ($item) {
            return (string)($item['file'] ?? '');
         }, $imageItems)),
         'image_items'     => $imageItems,
         'image_count_cfg' => count($imageItems),
         'has_class'       => is_file($base . $modul . '.class.php') ? '1' : '0',
         'run_cases'       => (array)($runs['run1'] ?? array()),
         'uses_run2'       => !empty($runs['uses_run2']) ? '1' : '0',
      );
      return $this->inspectCache[$modul];
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

   public function ddEditUrl(string $modul, string $dd): string {
      return '?dbx_modul=dbxAdmin&dbx_run1=edit_dd&modul=' . rawurlencode($modul) . '&dd=' . rawurlencode($dd);
   }

   public function buildDdItems(array $ddList): array {
      $items = array();
      foreach ($ddList as $ddRef) {
         $parts = explode('|', (string)$ddRef, 2);
         $m = trim((string)($parts[0] ?? ''));
         $d = trim((string)($parts[1] ?? ''));
         if ($m === '' || $d === '') {
            continue;
         }
         $items[] = array(
            'ref'         => $ddRef,
            'modul'       => $m,
            'dd'          => $d,
            'label'       => $d,
            'modul_label' => $m,
            'edit_url'    => $this->ddEditUrl($m, $d),
            'edit_title'  => 'DD bearbeiten: ' . $m . '/' . $d,
         );
      }
      return $items;
   }

   public function inspectAll(): array {
      $rows = array();
      foreach ($this->listModuleNames() as $modul) {
         $rows[] = $this->inspect($modul);
      }
      return $rows;
   }

   public function groupOptions(): array {
      if (is_array($this->groupLabels)) {
         return $this->groupLabels;
      }

      $options = array(
         'admin'  => 'Admin',
         'guest'  => 'Gast',
         'member' => 'Mitglied',
         '*'      => 'Alle (*)',
      );

      $db = dbx()->get_system_obj('dbxDB');
      $userGroups = $db->select('dbxUser_groups', 'active = 1');
      if (is_array($userGroups)) {
         foreach ($userGroups as $record) {
            $id = trim((string)($record['name'] ?? ''));
            if ($id === '') {
               continue;
            }
            $options[$id] = trim((string)($record['description'] ?? $id));
         }
      }

      $this->groupLabels = $options;
      return $this->groupLabels;
   }

   private function groupsText(array $groups): string {
      if (!$groups) {
         return '—';
      }

      $labels = $this->groupOptions();
      $parts = array();
      foreach ($groups as $group) {
         $parts[] = $labels[$group] ?? $group;
      }

      return implode(', ', $parts);
   }

   private function normalizeGroups($groups): array {
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

   private function isActive(array $config): bool {
      if (isset($config['activ'])) {
         return (string)$config['activ'] === '1';
      }
      if (isset($config['active'])) {
         return (string)$config['active'] === '1';
      }
      return true;
   }

   private function configEditUrl(string $modul): string {
      if ($modul === 'dbx') {
         return '?dbx_modul=dbxAdmin&dbx_run1=config&dbx_run2=edit&xmodul=dbx';
      }

      $file = 'dbx/modules/' . $modul . '/cfg/config.php';
      return '?dbx_modul=dbxEditor&dbx_run1=edit&file=' . rawurlencode($file);
   }

   private function moduleClassFiles(string $modul): array {
      $base = $this->modulesRoot . $modul . DIRECTORY_SEPARATOR;
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

   public function scanRunsPublic(string $modul): array {
      return $this->scanRuns($modul);
   }

   private function scanRuns(string $modul): array {
      $run1 = array();
      $defaultRun1 = '';
      $defaultRun2 = '';
      $usesRun2 = false;

      foreach ($this->moduleClassFiles($modul) as $file) {
         $src = @file_get_contents($file);
         if (!is_string($src) || $src === '') {
            continue;
         }

         if (preg_match("/get_modul_var\s*\(\s*['\"]dbx_run2['\"]/", $src)) {
            $usesRun2 = true;
         }

         if ($defaultRun1 === '' && preg_match("/get_modul_var\s*\(\s*['\"]dbx_run1['\"][^,]*,\s*['\"]([^'\"]+)['\"]/", $src, $match)) {
            $candidate = trim((string)$match[1]);
            if ($candidate !== '' && $candidate !== 'parameter') {
               $defaultRun1 = $candidate;
            }
         }

         if ($defaultRun2 === '' && preg_match("/get_modul_var\s*\(\s*['\"]dbx_run2['\"][^,]*,\s*['\"]([^'\"]+)['\"]/", $src, $match)) {
            $candidate = trim((string)$match[1]);
            if ($candidate !== '' && $candidate !== 'parameter') {
               $defaultRun2 = $candidate;
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

      if ($defaultRun1 === '') {
         $keys = array_keys($run1);
         $defaultRun1 = $keys ? (string)$keys[0] : 'run';
      }

      if ($modul === 'dbxAdmin' && isset($run1['run'])) {
         $defaultRun1 = 'run';
      }
      if ($modul === 'dbxHome') {
         $defaultRun1 = '';
      }

      return array(
         'run1'          => array_keys($run1),
         'default_run1'  => $defaultRun1,
         'default_run2'  => $defaultRun2,
         'uses_run2'     => $usesRun2,
      );
   }

   private function detectInstall(string $modul): array {
      $candidates = array($modul);
      if (substr($modul, -6) !== '_admin') {
         $candidates[] = $modul . '_admin';
      }

      foreach ($candidates as $candidate) {
         foreach ($this->moduleClassFiles($candidate) as $file) {
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
               'url'   => $this->buildModulUrl($candidate, 'install'),
            );
         }
      }

      return array();
   }

   private function detectDdUsage(string $modul): array {
      $found = array();

      $ddDir = $this->modulesRoot . $modul . DIRECTORY_SEPARATOR . 'dd' . DIRECTORY_SEPARATOR;
      if (is_dir($ddDir)) {
         foreach (glob($ddDir . '*.dd.php') ?: array() as $file) {
            $name = basename($file, '.dd.php');
            if ($name === '' || $name === 'new' || str_starts_with($name, '.')) {
               continue;
            }
            $this->registerDdRef($found, $modul, $name);
         }
      }

      foreach ($this->moduleClassFiles($modul) as $file) {
         $src = @file_get_contents($file);
         if (!is_string($src) || $src === '') {
            continue;
         }

         if (preg_match_all("/['\"]" . preg_quote($modul, '/') . "\|([a-zA-Z][a-zA-Z0-9_]*)['\"]/", $src, $matches)) {
            foreach ($matches[1] as $dd) {
               $this->registerDdRef($found, $modul, $dd);
            }
         }

         if (preg_match_all("/_dd\s*=\s*['\"]([a-zA-Z][a-zA-Z0-9_]*(?:\|[a-zA-Z][a-zA-Z0-9_]*)?)['\"]/", $src, $matches)) {
            foreach ($matches[1] as $ddRef) {
               $this->registerDdRefString($found, $modul, $ddRef);
            }
         }

         if (preg_match_all("/load_dd\s*\(\s*['\"]([a-zA-Z][a-zA-Z0-9_]*(?:\|[a-zA-Z][a-zA-Z0-9_]*)?)['\"]/", $src, $matches)) {
            foreach ($matches[1] as $ddRef) {
               $this->registerDdRefString($found, $modul, $ddRef);
            }
         }
      }

      $list = array_keys($found);
      sort($list, SORT_NATURAL | SORT_FLAG_CASE);
      return $list;
   }

   private function isValidIdent(string $name): bool {
      return (bool)preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $name);
   }

   private function ddFileExists(string $modul, string $dd): bool {
      $path = $this->modulesRoot . $modul . DIRECTORY_SEPARATOR . 'dd' . DIRECTORY_SEPARATOR . $dd . '.dd.php';
      return is_file($path);
   }

   private function registerDdRef(array &$found, string $modul, string $dd): void {
      $modul = trim($modul);
      $dd = trim($dd);
      if (!$this->isValidIdent($modul) || !$this->isValidIdent($dd)) {
         return;
      }
      if (!$this->ddFileExists($modul, $dd)) {
         return;
      }
      $found[$modul . '|' . $dd] = true;
   }

   private function registerDdRefString(array &$found, string $modul, string $ddRef): void {
      $ddRef = trim($ddRef);
      if ($ddRef === '') {
         return;
      }
      if (strpos($ddRef, '|') !== false) {
         $parts = explode('|', $ddRef, 2);
         $this->registerDdRef($found, $parts[0], $parts[1]);
         return;
      }
      $this->registerDdRef($found, $modul, $ddRef);
   }

   private function detectDescription(string $modul, string $base, array $ddList = array()): string {
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
         $text = $this->readLeadComment($file);
         if ($text !== '') {
            return $this->trimDescription($text);
         }
      }

      $runs = $this->scanRuns($modul);
      $parts = array();
      $parts[] = 'Standardaufruf: ' . $this->formatDefaultCall($modul, (string)($runs['default_run1'] ?? 'run'), (string)($runs['default_run2'] ?? ''));
      if ($ddList) {
         $parts[] = count($ddList) . ' DataDic-Datei(en)';
      }

      return implode(' · ', $parts);
   }

   private function readLeadComment(string $file): string {
      $src = @file_get_contents($file);
      if (!is_string($src) || $src === '') {
         return '';
      }

      if (preg_match('/\A\s*<\?php\s*/', $src)) {
         $src = preg_replace('/\A\s*<\?php\s*/', '', $src, 1);
      }

      if (preg_match('/\A\s*\/\*\*(.*?)\*\//s', $src, $match)) {
         return $this->cleanCommentBlock($match[1]);
      }
      if (preg_match('/\A\s*\/\*(.*?)\*\//s', $src, $match)) {
         return $this->cleanCommentBlock($match[1]);
      }

      return '';
   }

   private function cleanCommentBlock(string $text): string {
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

   private function trimDescription(string $text): string {
      $text = preg_replace('/\s+/', ' ', trim($text));
      if (strlen($text) > 320) {
         $text = substr($text, 0, 317) . '…';
      }
      return $text;
   }

   public function placeholderGraphic(string $modul, string $run1 = '', string $run2 = ''): array {
      return $this->resolveModGraphic($modul, $run1, $run2);
   }

   public function moduleImageEmptyPlaceholderUrl(): string {
      static $cache = array();
      $dir = dbx()->get_base_dir() . 'dbx/modules/dbxAdmin/tpl/img/';
      $lng = function_exists('dbx_lng_current')
         ? dbx_lng_current()
         : strtolower(trim((string) dbx()->get_system_var('dbx_lng', 'de')));
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
         $path = function_exists('dbx_lng_resolve_file')
            ? dbx_lng_resolve_file($dir, 'modul-no-image', 'svg')
            : '';
      }

      if ($path === '' || !is_file($path)) {
         $cache[$lng] = '';
         return '';
      }

      $file = basename(str_replace('\\', '/', $path));
      $cache[$lng] = dbx()->get_base_url() . 'dbx/modules/dbxAdmin/tpl/img/' . rawurlencode($file);
      return $cache[$lng];
   }

   public function moduleImageEmptyPlaceholderAlt(): string {
      static $cache = array();
      $lng = function_exists('dbx_lng_current')
         ? dbx_lng_current()
         : strtolower(trim((string) dbx()->get_system_var('dbx_lng', 'de')));
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

   private function resolveModGraphic(string $modul, string $run1, string $run2): array {
      $urlBase = dbx()->get_base_url() . 'dbx/modules/' . rawurlencode($modul) . '/';
      $modDir = $this->modulesRoot . $modul . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'mod' . DIRECTORY_SEPARATOR;

      $candidates = array();
      if ($run1 !== '' && $run2 !== '') {
         $candidates[] = $modul . '_' . $run1 . '_' . $run2 . '.svg';
      }
      if ($run1 !== '') {
         $candidates[] = $modul . '_' . $run1 . '.svg';
      }
      $candidates[] = $modul . '.svg';

      if (is_dir($modDir)) {
         foreach ($candidates as $name) {
            if (is_file($modDir . $name)) {
               return array(
                  'url'   => $urlBase . 'tpl/mod/' . rawurlencode($name),
                  'alt'   => $modul . ' ' . $run1,
                  'badge' => $name,
               );
            }
         }

         $any = glob($modDir . '*.svg');
         if (is_array($any) && isset($any[0]) && is_file($any[0])) {
            $name = basename($any[0]);
            return array(
               'url'   => $urlBase . 'tpl/mod/' . rawurlencode($name),
               'alt'   => $modul,
               'badge' => $name,
            );
         }
      }

      $imgPath = $this->modulesRoot . $modul . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'modul.gif';
      if (is_file($imgPath)) {
         return array(
            'url'   => $urlBase . 'tpl/img/modul.gif',
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

   public function modulUrl(string $modul, string $run1 = '', string $run2 = ''): string {
      return $this->buildModulUrl($modul, $run1, $run2);
   }

   public function canDeleteModule(string $modul): bool {
      $modul = trim($modul);
      if ($modul === '' || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $modul)) {
         return false;
      }
      $protected = array('dbx', 'dbxAdmin', 'dbxLogin', 'dbxHome');
      if (in_array($modul, $protected, true)) {
         return false;
      }
      $dir = $this->modulesRoot . $modul;
      return is_dir($dir);
   }

   private function buildModulUrl(string $modul, string $run1 = '', string $run2 = ''): string {
      $url = '?dbx_modul=' . rawurlencode($modul);
      if ($run1 !== '') {
         $url .= '&dbx_run1=' . rawurlencode($run1);
      }
      if ($run2 !== '') {
         $url .= '&dbx_run2=' . rawurlencode($run2);
      }
      return $url;
   }

   private function formatDefaultCall(string $modul, string $run1, string $run2): string {
      $parts = array('dbx_modul=' . $modul);
      if ($run1 !== '') {
         $parts[] = 'dbx_run1=' . $run1;
      }
      if ($run2 !== '') {
         $parts[] = 'dbx_run2=' . $run2;
      }
      return implode('&', $parts);
   }

   public function moduleHelpPath(string $modul): string {
      $modul = trim($modul);
      if ($modul === '') {
         return '';
      }
      $path = $this->modulesRoot . $modul . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'htm' . DIRECTORY_SEPARATOR . 'modul-help.htm';
      return is_file($path) ? $path : '';
   }

   public function formHelpPath(string $modul, string $form): string {
      $modul = trim($modul);
      $form = strtolower(trim($form));
      if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $modul) || $form === '') {
         return '';
      }
      $form = trim((string)preg_replace('/[^a-z0-9_-]+/', '-', $form), '-');
      if ($form === '') {
         return '';
      }
      $path = $this->modulesRoot . $modul . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'htm'
         . DIRECTORY_SEPARATOR . 'form-help-' . $form . '.htm';
      return is_file($path) ? $path : '';
   }

   public function renderModuleHelp(string $modul, array $data = array()): string {
      $modul = trim($modul);
      if ($modul === '') {
         return '';
      }

      $tpl = dbx()->get_system_obj('dbxTPL');
      if ($this->moduleHelpPath($modul) === '') {
         if (!isset($data['modul'])) {
            $data['modul'] = dbx()->esc($modul);
         }
         return $tpl->get_tpl('dbxAdmin|modul-help-fallback', $data);
      }

      return $tpl->get_tpl($modul . '|modul-help', $data);
   }

   public function renderFormHelp(string $modul, string $form, array $data = array()): string {
      $path = $this->formHelpPath($modul, $form);
      if ($path === '') {
         return $this->renderModuleHelp($modul, $data);
      }

      $name = basename($path, '.htm');
      return dbx()->get_system_obj('dbxTPL')->get_tpl($modul . '|' . $name, $data);
   }
}
