<?php
namespace dbx\dbxDesign_admin;

/**
 * Dateibasierte Design-Domäne für Wizard, Backup und KI-Import.
 *
 * Designs liegen ausschließlich unter `dbx/design/{name}`. Die Klasse nimmt
 * keine Datenbankzugriffe vor. Alle Schreibvorgänge werden auf einen zuvor
 * validierten Designnamen begrenzt und neue Designs zunächst in einem
 * Staging-Verzeichnis aufgebaut.
 */
class dbxDesignService {

   public const CONTRACT = 'dbx.design.v1';

   /**
    * Liefert den absoluten Design-Root.
    */
   public function design_root(): string {
      return rtrim(dbx()->os_path(dbx()->get_base_dir() . 'dbx/design'), '/\\');
   }

   /**
    * Normalisiert einen technischen Designnamen.
    */
   public function normalize_name(string $name): string {
      $name = strtolower(trim($name));
      $name = preg_replace('/[^a-z0-9_-]+/', '-', $name);
      return trim((string)$name, '-_');
   }

   /**
    * Prüft den technischen Designnamen.
    */
   public function is_valid_name(string $name): bool {
      return preg_match('/^[a-z0-9][a-z0-9_-]{1,62}$/', $name) === 1;
   }

   /**
    * Liefert den absoluten Pfad eines validierten Designs.
    */
   public function design_dir(string $name): string {
      $name = $this->normalize_name($name);
      if (!$this->is_valid_name($name)) {
         throw new \InvalidArgumentException('Ungueltiger Designname.');
      }
      return $this->design_root() . DIRECTORY_SEPARATOR . $name;
   }

   /**
    * Listet alle öffentlichen Designs mit Metadaten und Vertragsstatus.
    */
   public function list_designs(): array {
      $out = array();
      foreach (dbx()->get_system_obj('dbxPresentation')->get_design_catalog() as $name => $catalog_entry) {
         if (!$this->is_valid_name($name)) {
            continue;
         }
         $dir = (string)$catalog_entry['dir'];
         $meta = (array)$catalog_entry['meta'];
         $validation = $this->validate_design_directory($dir, false);
         $out[$name] = array(
            'name' => $name,
            'title' => (string)$catalog_entry['title'],
            'description' => (string)($meta['description'] ?? ''),
            'source_design' => (string)($meta['source_design'] ?? ''),
            'layout' => (string)($meta['layout'] ?? $this->detect_layout($dir)),
            'managed' => (bool)$catalog_entry['managed'],
            'valid' => empty($validation['errors']),
            'warnings' => $validation['warnings'],
         );
      }
      uasort($out, static function(array $a, array $b): int {
         return strnatcasecmp($a['title'], $b['title']);
      });
      return $out;
   }

   /**
    * Liest optionale Wizard-Metadaten.
    */
   public function read_metadata(string $name): array {
      $file = $this->design_dir($name) . DIRECTORY_SEPARATOR . 'design.json';
      if (!is_file($file)) {
         return array();
      }
      $data = json_decode((string)file_get_contents($file), true);
      return is_array($data) ? $data : array();
   }

   /**
    * Voreinstellungen für einen komfortablen Wizard.
    */
   public function defaults(string $source = 'dbxapp'): array {
      return array(
         'source_design' => $source,
         'target_design' => '',
         'title' => '',
         'description' => '',
         'layout' => 'top',
         'menu_style' => 'tabs',
         'content_width' => 'wide',
         'footer_mode' => 'full',
         'brand_name' => 'Meine Website',
         'tagline' => 'Einfach. Klar. Persoenlich.',
         'logo_mode' => 'icon',
         'logo_icon' => 'bi-stars',
         'primary_color' => '#2563eb',
         'secondary_color' => '#0f172a',
         'accent_color' => '#14b8a6',
         'background_color' => '#f4f7fb',
         'surface_color' => '#ffffff',
         'text_color' => '#172033',
         'font_family' => 'modern',
         'radius' => 'soft',
         'footer_text' => 'Mit dbXapp erstellt',
         'legal_links' => 1,
         'set_default' => 0,
      );
   }

   /**
    * Erzeugt aus einem vorhandenen kompatiblen Design ein unabhängiges Paket.
    *
    * @param array $input Normalisierte Wizardwerte.
    * @param array $logoUpload Optionaler Eintrag aus $_FILES.
    *
    * @return array Ergebnis mit Designname, Backup und Prüfstatus.
    */
   public function create_from_wizard(array $input, array $logo_upload = array()): array {
      $source = $this->normalize_name((string)($input['source_design'] ?? 'dbxapp'));
      $target = $this->normalize_name((string)($input['target_design'] ?? ''));
      if (!$this->is_valid_name($source) || !is_dir($this->design_dir($source))) {
         throw new \InvalidArgumentException('Das Ausgangsdesign existiert nicht.');
      }
      if (!$this->is_valid_name($target)) {
         throw new \InvalidArgumentException('Der technische Name muss 2 bis 63 Zeichen aus a-z, 0-9, - oder _ enthalten.');
      }
      if ($target === $source) {
         throw new \InvalidArgumentException('Die Personalisierung wird als eigenes Design angelegt. Bitte einen neuen Namen verwenden.');
      }
      $target_dir = $this->design_dir($target);
      if (is_dir($target_dir)) {
         throw new \RuntimeException('Das Zieldesign existiert bereits.');
      }

      $stage = $this->stage_dir($target);
      $this->remove_directory($stage);
      if (!mkdir($stage, 0777, true) && !is_dir($stage)) {
         throw new \RuntimeException('Staging-Verzeichnis konnte nicht angelegt werden.');
      }

      try {
         $this->copy_directory($this->design_dir($source), $stage);
         $this->rewrite_design_paths($stage, $source, $target);
         $this->write_wizard_files($stage, $target, $input, $logo_upload);
         $validation = $this->validate_design_directory($stage, true);
         if ($validation['errors']) {
            throw new \RuntimeException(implode(' ', $validation['errors']));
         }
         if (!rename($stage, $target_dir)) {
            throw new \RuntimeException('Das fertige Design konnte nicht aktiviert werden.');
         }
      } catch (\Throwable $e) {
         $this->remove_directory($stage);
         throw $e;
      }

      if (!empty($input['set_default'])) {
         $config = dbx()->get_cfg('dbx');
         if (!is_array($config)) {
            $config = array();
         }
         $config['default_design_user'] = $target;
         dbx()->set_cfg('dbx', $config);
      }

      return array(
         'name' => $target,
         'dir' => $target_dir,
         'validation' => $validation,
         'default_changed' => !empty($input['set_default']),
      );
   }

   /**
    * Erstellt eine wiederherstellbare ZIP-Sicherung eines Designs.
    */
   public function backup_design(string $name): string {
      $dir = $this->design_dir($name);
      if (!is_dir($dir)) {
         throw new \RuntimeException('Design fuer Backup nicht gefunden.');
      }
      if (!class_exists('ZipArchive')) {
         throw new \RuntimeException('ZipArchive ist nicht verfuegbar.');
      }
      $backup_dir = dbx()->os_path(rtrim(dbx()->get_file_dir(), '/\\') . '/sys/design-backups');
      if (!is_dir($backup_dir) && !mkdir($backup_dir, 0777, true) && !is_dir($backup_dir)) {
         throw new \RuntimeException('Backup-Verzeichnis konnte nicht angelegt werden.');
      }
      $file = $backup_dir . DIRECTORY_SEPARATOR . date('Ymd-His') . '-' . $name . '.zip';
      $zip = new \ZipArchive();
      if ($zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
         throw new \RuntimeException('Design-Backup konnte nicht erstellt werden.');
      }
      $this->add_directory_to_zip($zip, $dir, '');
      $zip->close();
      return $file;
   }

   /**
    * Validiert den Designvertrag und sicherheitsrelevante Dateien.
    */
   public function validate_design_directory(string $dir, bool $strict = true): array {
      $errors = array();
      $warnings = array();
      $default = $dir . DIRECTORY_SEPARATOR . 'htm' . DIRECTORY_SEPARATOR . 'default.htm';
      if (!is_file($default)) {
         $errors[] = 'htm/default.htm fehlt.';
         return array('errors' => $errors, 'warnings' => $warnings);
      }
      $html = (string)file_get_contents($default);
      if (substr_count($html, '[dbx:content]') !== 1) {
         $errors[] = 'htm/default.htm muss [dbx:content] genau einmal enthalten.';
      }
      foreach (array('{dbx:design}', '{dbx:skin_css}', '{dbx:skin_class}', 'dbx/js/lib/core.js', 'dbx/js/lib/uiSettings.js', 'dbx/js/lib/runtime.js', 'dbx/js/lib/scheduler.js', '{dbx:module_assets}') as $required) {
         if (strpos($html, $required) === false) {
            if ($strict) {
               $errors[] = 'Template-Marker fehlt: ' . $required;
            } else {
               $warnings[] = 'Template-Marker fehlt: ' . $required;
            }
         }
      }
      // {dbx:document_title} loeste {dbx:title} im <title>-Tag ab; dbxTPL::replaces_dbx()
      // ersetzt beide, daher genuegt einer der beiden Marker.
      if (strpos($html, '{dbx:title}') === false && strpos($html, '{dbx:document_title}') === false) {
         $message = 'Template-Marker fehlt: {dbx:title} oder {dbx:document_title}';
         if ($strict) {
            $errors[] = $message;
         } else {
            $warnings[] = $message;
         }
      }
      foreach (array('logo', 'branding', 'footer') as $slot) {
         $marker = '[dbx:' . $slot . ']';
         $fragment_file = $dir . DIRECTORY_SEPARATOR . 'htm' . DIRECTORY_SEPARATOR . $slot . '.htm';
         if (substr_count($html, $marker) > 1) {
            $errors[] = $marker . ' darf in der Designschale hoechstens einmal vorkommen.';
         }
         if (strpos($html, $marker) !== false && !is_file($fragment_file)) {
            $errors[] = 'Fragment fuer ' . $marker . ' fehlt.';
         }
         if (is_file($fragment_file)) {
            $fragment_content = (string)file_get_contents($fragment_file);
            if (strpos($fragment_content, '[dbx:content]') !== false) {
               $errors[] = 'Fragment ' . $slot . '.htm darf keinen Content-Slot enthalten.';
            }
            foreach (array('[dbx:logo]', '[dbx:branding]', '[dbx:footer]') as $nested_slot) {
               if (strpos($fragment_content, $nested_slot) !== false) {
                  $errors[] = 'Fragment ' . $slot . '.htm darf keine verschachtelten Design-Slots enthalten.';
                  break;
               }
            }
         }
      }
      foreach ($this->relative_files($dir) as $relative => $absolute) {
         if (!$this->is_allowed_design_file($relative)) {
            $errors[] = 'Nicht erlaubte Designdatei: ' . $relative;
         }
         if (is_link($absolute)) {
            $errors[] = 'Symbolische Links sind in Designpaketen nicht erlaubt: ' . $relative;
         }
      }
      return array('errors' => array_values(array_unique($errors)), 'warnings' => array_values(array_unique($warnings)));
   }

   /**
    * Liefert alle Paketdateien als relative, slash-normalisierte Pfade.
    */
   public function relative_files(string $dir): array {
      $files = array();
      if (!is_dir($dir)) {
         return $files;
      }
      $root = rtrim(str_replace('\\', '/', $dir), '/');
      $it = new \RecursiveIteratorIterator(
         new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
      );
      foreach ($it as $file) {
         if (!$file->isFile() && !$file->isLink()) {
            continue;
         }
         $absolute = $file->getPathname();
         $relative = ltrim(substr(str_replace('\\', '/', $absolute), strlen($root)), '/');
         $files[$relative] = $absolute;
      }
      ksort($files);
      return $files;
   }

   /**
    * Prüft Dateitypen, die eine KI in einem Designpaket liefern darf.
    */
   public function is_allowed_design_file(string $relative): bool {
      $relative = str_replace('\\', '/', $relative);
      if ($relative === '' || $relative[0] === '/' || strpos($relative, '..') !== false || strpos($relative, "\0") !== false) {
         return false;
      }
      $ext = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
      return in_array($ext, array(
         'htm', 'html', 'css', 'js', 'json', 'txt', 'md',
         'png', 'jpg', 'jpeg', 'webp', 'gif', 'svg', 'ico',
         'woff', 'woff2', 'ttf', 'otf',
      ), true);
   }

   /**
    * Prüft aktive Inhalte einer von außen gelieferten Ergebnisdatei.
    *
    * Design-JavaScript bleibt grundsätzlich möglich, darf aber keinen eigenen
    * Netzwerktransport einführen. HTML darf nur lokale Script-/Stylesheet-
    * Dateien referenzieren und keine Inline-Handler enthalten. Damit bleibt
    * der KI-Vertrag bei Darstellung und Layout und wird nicht zu einer
    * zweiten API- oder Tracking-Pipeline.
    *
    * @return string Leerer String bei Erfolg, sonst verständlicher Fehler.
    */
   public function validate_result_file(string $relative, string $absolute): string {
      if (!$this->is_allowed_design_file($relative) || !is_file($absolute) || is_link($absolute)) {
         return 'Nicht erlaubte Ergebnisdatei: ' . $relative;
      }
      $ext = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
      if (!in_array($ext, array('htm', 'html', 'css', 'js', 'json', 'svg'), true)) {
         return '';
      }
      $content = (string)file_get_contents($absolute);
      if (strpos($content, '<?') !== false) {
         return 'Servercode ist in Designdateien nicht erlaubt: ' . $relative;
      }
      if ($ext === 'json' && json_decode($content, true) === null && trim($content) !== 'null') {
         return 'Ungueltiges JSON im Design-Ergebnis: ' . $relative;
      }
      if ($ext === 'htm' || $ext === 'html') {
         if (preg_match('/\son[a-z]+\s*=/i', $content)
            || preg_match('/javascript\s*:/i', $content)
            || preg_match('/<script\b(?![^>]*\bsrc\s*=)[^>]*>/i', $content)
            || preg_match('/<(?:script|link)\b[^>]*(?:src|href)\s*=\s*["\']?\s*(?:https?:)?\/\//i', $content)) {
            return 'Aktiver Inline- oder Fremdcode ist im Design-HTML nicht erlaubt: ' . $relative;
         }
      }
      if ($ext === 'css' && preg_match('/(?:@import|url\s*\()\s*["\']?\s*(?:https?:)?\/\//i', $content)) {
         return 'Externe CSS-Ressourcen sind im eigenstaendigen Design nicht erlaubt: ' . $relative;
      }
      if ($ext === 'js' && preg_match('/\b(?:fetch|XMLHttpRequest|WebSocket|EventSource|sendBeacon)\s*(?:\(|\.)/i', $content)) {
         return 'Eigener Netzwerktransport ist im Design-JavaScript nicht erlaubt: ' . $relative;
      }
      if ($ext === 'svg' && preg_match('/<(?:script|foreignObject)\b|\son[a-z]+\s*=|javascript\s*:|<!ENTITY|<\?xml-stylesheet/i', $content)) {
         return 'Aktiver SVG-Inhalt ist im Design-Ergebnis nicht erlaubt: ' . $relative;
      }
      return '';
   }

   /**
    * Kopiert ein Design als sichere Grundlage und überschreibt es mit
    * validierten Ergebnisdateien einer KI.
    */
   public function apply_result(string $source, string $target, string $result_dir, string $mode): array {
      $source = $this->normalize_name($source);
      $target = $this->normalize_name($target);
      $mode = $mode === 'update' ? 'update' : 'create';
      if (!$this->is_valid_name($target)) {
         throw new \InvalidArgumentException('Ungueltiges Zieldesign.');
      }
      $target_dir = $this->design_dir($target);
      $backup = '';
      if ($mode === 'update') {
         if (!is_dir($target_dir)) {
            throw new \RuntimeException('Das zu aktualisierende Design existiert nicht.');
         }
         $backup = $this->backup_design($target);
      } elseif (is_dir($target_dir)) {
         throw new \RuntimeException('Das Zieldesign existiert bereits.');
      }

      $stage = $this->stage_dir($target);
      $this->remove_directory($stage);
      if (!mkdir($stage, 0777, true) && !is_dir($stage)) {
         throw new \RuntimeException('Staging-Verzeichnis konnte nicht angelegt werden.');
      }

      try {
         if ($mode === 'update') {
            $this->copy_directory($target_dir, $stage);
         } else {
            if (!$this->is_valid_name($source) || !is_dir($this->design_dir($source))) {
               throw new \RuntimeException('Ausgangsdesign fuer die Neuanlage fehlt.');
            }
            $this->copy_directory($this->design_dir($source), $stage);
            $this->rewrite_design_paths($stage, $source, $target);
         }
         foreach ($this->relative_files($result_dir) as $relative => $absolute) {
            $file_error = $this->validate_result_file($relative, $absolute);
            if ($file_error !== '') {
               throw new \RuntimeException($file_error);
            }
            $dest = $stage . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $parent = dirname($dest);
            if (!is_dir($parent) && !mkdir($parent, 0777, true) && !is_dir($parent)) {
               throw new \RuntimeException('Zielordner konnte nicht angelegt werden: ' . $relative);
            }
            if (!copy($absolute, $dest)) {
               throw new \RuntimeException('Ergebnisdatei konnte nicht uebernommen werden: ' . $relative);
            }
         }
         $validation = $this->validate_design_directory($stage, true);
         if ($validation['errors']) {
            throw new \RuntimeException(implode(' ', $validation['errors']));
         }
         if ($mode === 'update') {
            $old = $target_dir . '.dbx-old-' . bin2hex(random_bytes(4));
            if (!rename($target_dir, $old)) {
               throw new \RuntimeException('Bestehendes Design konnte nicht fuer den Austausch vorbereitet werden.');
            }
            if (!rename($stage, $target_dir)) {
               rename($old, $target_dir);
               throw new \RuntimeException('Neues Design konnte nicht aktiviert werden.');
            }
            $this->remove_directory($old);
         } elseif (!rename($stage, $target_dir)) {
            throw new \RuntimeException('Neues Design konnte nicht aktiviert werden.');
         }
      } catch (\Throwable $e) {
         $this->remove_directory($stage);
         throw $e;
      }

      return array('name' => $target, 'backup' => $backup, 'validation' => $validation);
   }

   private function stage_dir(string $target): string {
      $root = dbx()->os_path(rtrim(dbx()->get_file_dir(), '/\\') . '/tmp/design-admin');
      if (!is_dir($root) && !mkdir($root, 0777, true) && !is_dir($root)) {
         throw new \RuntimeException('Design-Staging-Root konnte nicht angelegt werden.');
      }
      return $root . DIRECTORY_SEPARATOR . $target . '-' . bin2hex(random_bytes(5));
   }

   private function detect_layout(string $dir): string {
      $html = is_file($dir . '/htm/default.htm') ? (string)file_get_contents($dir . '/htm/default.htm') : '';
      return strpos($html, '<aside') !== false ? 'sidebar' : 'top';
   }

   private function copy_directory(string $source, string $target): void {
      $it = new \RecursiveIteratorIterator(
         new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
         \RecursiveIteratorIterator::SELF_FIRST
      );
      foreach ($it as $file) {
         if ($file->isLink()) {
            continue;
         }
         $relative = substr($file->getPathname(), strlen(rtrim($source, '/\\')) + 1);
         $dest = $target . DIRECTORY_SEPARATOR . $relative;
         if ($file->isDir()) {
            if (!is_dir($dest) && !mkdir($dest, 0777, true) && !is_dir($dest)) {
               throw new \RuntimeException('Ordner konnte nicht kopiert werden: ' . $relative);
            }
         } elseif (!copy($file->getPathname(), $dest)) {
            throw new \RuntimeException('Datei konnte nicht kopiert werden: ' . $relative);
         }
      }
   }

   private function remove_directory(string $dir): void {
      if (!is_dir($dir)) {
         return;
      }
      $it = new \RecursiveIteratorIterator(
         new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
         \RecursiveIteratorIterator::CHILD_FIRST
      );
      foreach ($it as $file) {
         if ($file->isDir()) {
            @rmdir($file->getPathname());
         } else {
            @unlink($file->getPathname());
         }
      }
      @rmdir($dir);
   }

   private function rewrite_design_paths(string $dir, string $source, string $target): void {
      foreach ($this->relative_files($dir) as $relative => $absolute) {
         $ext = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
         if (!in_array($ext, array('htm', 'html', 'css', 'js', 'json', 'txt', 'md'), true)) {
            continue;
         }
         $content = (string)file_get_contents($absolute);
         $new = str_replace(
            array('dbx/design/' . $source . '/', 'design/' . $source . '/'),
            array('dbx/design/' . $target . '/', 'design/' . $target . '/'),
            $content
         );
         if ($new !== $content) {
            file_put_contents($absolute, $new);
         }
      }
   }

   private function write_wizard_files(string $dir, string $target, array $input, array $logo_upload): void {
      foreach (array('htm', 'css', 'img', 'js') as $sub) {
         if (!is_dir($dir . DIRECTORY_SEPARATOR . $sub)) {
            mkdir($dir . DIRECTORY_SEPARATOR . $sub, 0777, true);
         }
      }
      $input = array_merge($this->defaults((string)($input['source_design'] ?? 'dbxapp')), $input);
      $logo_asset = $this->store_logo_upload($dir, $logo_upload);
      file_put_contents($dir . '/htm/default.htm', $this->build_default_template($target, $input));
      file_put_contents($dir . '/htm/logo.htm', $this->build_logo_fragment($input, $logo_asset));
      file_put_contents($dir . '/htm/branding.htm', $this->build_branding_fragment($input));
      file_put_contents($dir . '/htm/footer.htm', $this->build_footer_fragment($input));
      file_put_contents($dir . '/css/design-custom.css', $this->build_custom_css($input));
      $metadata = array_merge($input, array(
         'contract' => self::CONTRACT,
         'name' => $target,
         'created_at' => date(DATE_ATOM),
         'managed_by' => 'dbxDesign_admin',
         'slots' => array('logo', 'branding', 'footer'),
         'logo_asset' => $logo_asset,
      ));
      unset($metadata['set_default']);
      file_put_contents(
         $dir . '/design.json',
         json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
      );
   }

   private function store_logo_upload(string $dir, array $file): string {
      if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
         return '';
      }
      if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
         throw new \InvalidArgumentException('Logo-Upload fehlgeschlagen.');
      }
      if ((int)($file['size'] ?? 0) > 5 * 1024 * 1024) {
         throw new \InvalidArgumentException('Das Logo darf maximal 5 MB gross sein.');
      }
      $tmp = (string)($file['tmp_name'] ?? '');
      $mime = $tmp !== '' && is_file($tmp) && function_exists('mime_content_type') ? (string)mime_content_type($tmp) : '';
      $extensions = array(
         'image/png' => 'png',
         'image/jpeg' => 'jpg',
         'image/webp' => 'webp',
         'image/gif' => 'gif',
      );
      if (!isset($extensions[$mime])) {
         throw new \InvalidArgumentException('Logo bitte als PNG, JPG, WEBP oder GIF hochladen.');
      }
      $name = 'logo.' . $extensions[$mime];
      $dest = $dir . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . $name;
      if (!copy($tmp, $dest)) {
         throw new \RuntimeException('Logo konnte nicht gespeichert werden.');
      }
      return 'img/' . $name;
   }

   private function build_default_template(string $target, array $in): string {
      $layout = in_array($in['layout'], array('top', 'sidebar', 'hybrid'), true) ? $in['layout'] : 'top';
      $extra_css = is_file($this->design_dir((string)$in['source_design']) . '/css/glass-3d.css')
         ? '    <link rel="stylesheet" href="dbx/design/{dbx:design}/css/glass-3d.css">' . "\n"
         : '';
      $admin_menu = <<<'HTML'
[inc=has_group('admin')]
<nav id="dbx_admin_menu" class="dbx-menu dbx-menu-admin" data-dbx='lib=menu|id=admin|label=<i class="bi bi-shield-lock"></i> Admin' style="visibility:hidden">
 [modul=dbxMenu]dbx_run1=load&tpl=modul|dbx-top-admin[/modul]
</nav>
[/inc]
HTML;
      $main_menu = <<<'HTML'
<nav id="dbx_main_menu" class="dbx-menu dbx-menu-main" data-dbx='lib=menu|id=user|label=<i class="bi bi-list"></i> Menue' style="visibility:hidden">
 [modul=dbxMenu]dbx_run1=load&tpl=modul|dbx-top-main[/modul]
</nav>
HTML;
      if ($layout === 'sidebar') {
         $shell = '<div id="dbxApp" class="dbx-design-shell dbx-layout-sidebar">'
            . '<aside id="dbxHeader" class="dbx-design-side"><div class="dbx-brand-row">[dbx:logo][dbx:branding]</div>'
            . $main_menu . '<div class="dbx-admin-side">' . $admin_menu . '</div></aside>'
            . '<div class="dbx-design-stage"><main id="dbxMain"><div class="dbx-content"><div id="dbxContent" class="dbx-content-inner">[dbx:content]</div></div></main>[dbx:footer]</div></div>';
      } elseif ($layout === 'hybrid') {
         $shell = '<div id="dbxApp" class="dbx-design-shell dbx-layout-hybrid">'
            . '<header id="dbxHeader"><div class="dbx-brand-row">[dbx:logo][dbx:branding]</div>' . $admin_menu . '</header>'
            . '<div class="dbx-hybrid-grid"><aside class="dbx-design-side">' . $main_menu . '</aside>'
            . '<div class="dbx-design-stage"><main id="dbxMain"><div class="dbx-content"><div id="dbxContent" class="dbx-content-inner">[dbx:content]</div></div></main>[dbx:footer]</div></div></div>';
      } else {
         $shell = '<div id="dbxApp" class="dbx-design-shell dbx-layout-top">'
            . '<header id="dbxHeader"><div class="dbx-brand-row">[dbx:logo][dbx:branding]</div><div class="dbx-menu-stack">'
            . $admin_menu . $main_menu . '</div></header>'
            . '<main id="dbxMain"><div class="dbx-content"><div id="dbxContent" class="dbx-content-inner">[dbx:content]</div></div></main>[dbx:footer]</div>';
      }
      return '<!DOCTYPE html>' . "\n"
         . '<html lang="{dbx:lng}"><head>' . "\n"
         . '    <meta charset="utf-8">' . "\n"
         . '    <meta name="viewport" content="width=device-width, initial-scale=1">' . "\n"
         . '    <title>{dbx:title}</title>{dbx:head_meta}' . "\n"
         . '    <link rel="stylesheet" href="dbx/vendor/twbs/bootstrap/dist/css/bootstrap.min.css">' . "\n"
         . '    <link rel="stylesheet" href="dbx/vendor/twbs/bootstrap-icons/font/bootstrap-icons.css">' . "\n"
         . '    <link rel="stylesheet" href="dbx/design/{dbx:design}/css/colors.css">' . "\n"
         . '    <link rel="stylesheet" href="{dbx:skin_css}">' . "\n"
         . '    <link rel="stylesheet" href="dbx/design/{dbx:design}/css/base.css">' . "\n"
         . '    <link rel="stylesheet" href="dbx/design/{dbx:design}/css/theme.css">' . "\n"
         . $extra_css
         . '    <link rel="stylesheet" href="dbx/design/{dbx:design}/css/design-custom.css">' . "\n"
         . '</head>' . "\n"
         . '<body class="dbx-app dbx-design-generated dbx-layout-' . $layout . ' {dbx:skin_class}"'
         . '[inc=is_dbx_edit()] data-dbx="lib=icons_editor|module=dbxDesign_admin|mode={dbx:edit}"[/inc] data-dbx-design="{dbx:design}" data-dbx-skin="{dbx:color}">' . "\n"
         . $shell . "\n"
         . '<div id="dbxPreloader" class="dbx-preloader" style="display:none!important;pointer-events:none!important"></div>' . "\n"
         . '<button id="dbxBackToTop" class="btn btn-primary" type="button" aria-label="Nach oben"><i class="bi bi-arrow-up"></i></button>' . "\n"
         . '<script src="dbx/vendor/components/jquery/jquery.min.js"></script>' . "\n"
         . '<script src="dbx/js/lib/core.js?design={dbx:design}"></script>' . "\n"
         . '<script src="dbx/js/lib/uiSettings.js?v={dbx:asset_version}"></script>' . "\n"
         . '<script src="dbx/js/lib/runtime.js?v={dbx:asset_version}"></script>' . "\n"
         . '<script src="dbx/js/lib/scheduler.js?v={dbx:asset_version}"></script>' . "\n"
         . '{dbx:module_assets}' . "\n"
         . '</body></html>';
   }

   private function build_logo_fragment(array $in, string $asset): string {
      $name = htmlspecialchars((string)$in['brand_name'], ENT_QUOTES, 'UTF-8');
      if ($asset !== '') {
         return '<a class="dbx-design-logo" href="{base_url}" aria-label="' . $name . '"><img src="dbx/design/{dbx:design}/' . $asset . '" alt="' . $name . '"></a>';
      }
      if (($in['logo_mode'] ?? 'icon') === 'none') {
         return '';
      }
      $icon = preg_replace('/[^a-z0-9_-]+/i', '', (string)($in['logo_icon'] ?? 'bi-stars')) ?: 'bi-stars';
      return '<a class="dbx-design-logo dbx-design-logo-icon" href="{base_url}" aria-label="' . $name . '"><i class="bi ' . $icon . '" aria-hidden="true"></i></a>';
   }

   private function build_branding_fragment(array $in): string {
      $name = htmlspecialchars((string)$in['brand_name'], ENT_QUOTES, 'UTF-8');
      $tagline = htmlspecialchars((string)$in['tagline'], ENT_QUOTES, 'UTF-8');
      return '<a class="dbx-design-branding" href="{base_url}"><strong>' . $name . '</strong>'
         . ($tagline !== '' ? '<small>' . $tagline . '</small>' : '') . '</a>';
   }

   private function build_footer_fragment(array $in): string {
      $mode = in_array($in['footer_mode'], array('full', 'minimal', 'none'), true) ? $in['footer_mode'] : 'full';
      $dock = '<div class="dbx-footer-dockbar" aria-label="Fensterleiste"><div id="windrop" class="dbx-window-dock" aria-label="Minimierte Fenster"></div>'
         . '<button id="dbxWindowCloseAll" class="dbx-footer-window-close-all" type="button" data-dbx-tooltip="Alle Fenster schliessen" aria-label="Alle Fenster schliessen"><i class="bi bi-x-square"></i></button></div>';
      if ($mode === 'none') {
         return '<footer id="dbxFooter" class="dbx-design-footer dbx-footer-dock-only">' . $dock . '</footer>';
      }
      $brand = htmlspecialchars((string)$in['brand_name'], ENT_QUOTES, 'UTF-8');
      $text = htmlspecialchars((string)$in['footer_text'], ENT_QUOTES, 'UTF-8');
      $legal = !empty($in['legal_links'])
         ? '<a href="datenschutz">Datenschutz</a><a href="impressum">Impressum</a>'
         : '';
      $details = $mode === 'full' && $text !== '' ? '<span class="dbx-design-footer-text">' . $text . '</span>' : '';
      return '<footer id="dbxFooter" class="dbx-design-footer">' . $dock
         . '<div class="dbx-design-footer-info">' . $details . '<span class="dbx-design-footer-links">' . $legal . '</span>'
         . '<span>© ' . date('Y') . ' ' . $brand . '</span><span data-dbx-runtime data-dbx-php-runtime class="visually-hidden">...</span></div></footer>';
   }

   private function build_custom_css(array $in): string {
      $colors = array(
         'primary_color' => '#2563eb',
         'secondary_color' => '#0f172a',
         'accent_color' => '#14b8a6',
         'background_color' => '#f4f7fb',
         'surface_color' => '#ffffff',
         'text_color' => '#172033',
      );
      foreach ($colors as $key => $fallback) {
         if (preg_match('/^#[0-9a-f]{6}$/i', (string)($in[$key] ?? '')) !== 1) {
            $in[$key] = $fallback;
         }
      }
      $fonts = array(
         'system' => 'system-ui, -apple-system, "Segoe UI", sans-serif',
         'modern' => '"Inter", "Segoe UI", system-ui, sans-serif',
         'editorial' => 'Georgia, "Times New Roman", serif',
         'rounded' => '"Trebuchet MS", "Segoe UI", sans-serif',
      );
      $radius = array('square' => '0.25rem', 'soft' => '0.85rem', 'round' => '1.5rem');
      $width = array('compact' => '960px', 'wide' => '1440px', 'full' => '100%');
      $font = $fonts[$in['font_family']] ?? $fonts['modern'];
      $radius_value = $radius[$in['radius']] ?? $radius['soft'];
      $max_width = $width[$in['content_width']] ?? $width['wide'];
      $menu_style = in_array($in['menu_style'], array('tabs', 'pills', 'compact'), true) ? $in['menu_style'] : 'tabs';
      return '/* Durch dbxDesign_admin erzeugte, bewusst kleine Anpassungsschicht. */' . "\n"
         . ':root{' . "\n"
         . ' --design-primary:' . $in['primary_color'] . ';--design-secondary:' . $in['secondary_color'] . ';--design-accent:' . $in['accent_color'] . ';' . "\n"
         . ' --design-bg:' . $in['background_color'] . ';--design-surface:' . $in['surface_color'] . ';--design-text:' . $in['text_color'] . ';' . "\n"
         . ' --design-radius:' . $radius_value . ';--design-content-width:' . $max_width . ';' . "\n"
         . ' --dbx-primary:var(--design-primary);--bs-primary:var(--design-primary);' . "\n"
         . '}' . "\n"
         . 'html,body{min-height:100%;}body.dbx-design-generated{margin:0;background:var(--design-bg);color:var(--design-text);font-family:' . $font . ';}' . "\n"
         . '.dbx-design-shell{min-height:100vh;}.dbx-design-stage{min-width:0;display:flex;flex-direction:column;min-height:100vh;}' . "\n"
         . '#dbxHeader{background:var(--design-surface);border-bottom:1px solid color-mix(in srgb,var(--design-secondary) 18%,transparent);box-shadow:0 8px 30px color-mix(in srgb,var(--design-secondary) 10%,transparent);}' . "\n"
         . '.dbx-brand-row{display:flex;align-items:center;gap:.8rem;padding:.8rem 1rem;}.dbx-design-logo{display:grid;place-items:center;width:2.8rem;height:2.8rem;border-radius:var(--design-radius);background:var(--design-primary);color:#fff;font-size:1.35rem;overflow:hidden;flex:0 0 auto;}.dbx-design-logo img{width:100%;height:100%;object-fit:contain;background:#fff;}.dbx-design-branding{display:grid;color:var(--design-text);text-decoration:none;line-height:1.15;}.dbx-design-branding strong{font-size:1.05rem}.dbx-design-branding small{margin-top:.2rem;color:color-mix(in srgb,var(--design-text) 68%,transparent);}' . "\n"
         . '.dbx-menu-stack{display:flex;flex-wrap:wrap;align-items:center;gap:.5rem;padding:0 1rem .8rem}.dbx-layout-top #dbxHeader{display:flex;align-items:center;justify-content:space-between;gap:1rem;position:sticky;top:0;z-index:1030}.dbx-layout-top .dbx-menu-stack{padding:.7rem 1rem;}' . "\n"
         . '.dbx-layout-sidebar{display:grid;grid-template-columns:minmax(230px,290px) minmax(0,1fr)}.dbx-layout-sidebar>.dbx-design-side,.dbx-hybrid-grid>.dbx-design-side{min-height:100vh;border-right:1px solid color-mix(in srgb,var(--design-secondary) 16%,transparent);background:var(--design-surface);}.dbx-layout-sidebar>.dbx-design-side{position:sticky;top:0;height:100vh;overflow:auto}.dbx-layout-sidebar .dbx-menu,.dbx-hybrid-grid>.dbx-design-side .dbx-menu{display:block;padding:.5rem 1rem}.dbx-admin-side{margin-top:auto;padding-top:1rem}' . "\n"
         . '.dbx-hybrid-grid{display:grid;grid-template-columns:minmax(210px,260px) minmax(0,1fr)}.dbx-layout-hybrid>#dbxHeader{display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:1030}.dbx-hybrid-grid>.dbx-design-side{min-height:calc(100vh - 70px);}' . "\n"
         . '#dbxMain{flex:1;min-width:0}.dbx-content-inner{width:min(100%,var(--design-content-width));margin-inline:auto;padding:clamp(.75rem,2vw,1.5rem)}' . "\n"
         . '.dbx-design-footer{margin-top:auto;background:var(--design-surface);border-top:1px solid color-mix(in srgb,var(--design-secondary) 16%,transparent)}.dbx-design-footer-info{display:flex;flex-wrap:wrap;justify-content:space-between;gap:.75rem;padding:.8rem 1rem;font-size:.85rem}.dbx-design-footer-links{display:flex;gap:1rem}.dbx-footer-dock-only .dbx-footer-dockbar:empty{display:none}' . "\n"
         . '.dbx-design-generated .btn-primary{--bs-btn-bg:var(--design-primary);--bs-btn-border-color:var(--design-primary)}.dbx-design-generated .card,.dbx-design-generated .dbx-panel{border-radius:var(--design-radius)}' . "\n"
         . ($menu_style === 'pills' ? '.dbx-design-generated .dbx-menu a{border-radius:999px}' : ($menu_style === 'compact' ? '.dbx-design-generated .dbx-menu a{padding:.3rem .55rem;font-size:.88rem}' : '.dbx-design-generated .dbx-menu a{border-radius:var(--design-radius)}')) . "\n"
         . '@media(max-width:900px){.dbx-layout-top #dbxHeader{position:relative;display:block}.dbx-layout-sidebar,.dbx-hybrid-grid{grid-template-columns:1fr}.dbx-layout-sidebar>.dbx-design-side,.dbx-hybrid-grid>.dbx-design-side{position:relative;height:auto;min-height:0;border-right:0;border-bottom:1px solid color-mix(in srgb,var(--design-secondary) 16%,transparent)}.dbx-menu-stack{display:block}.dbx-design-stage{min-height:auto}}' . "\n";
   }

   private function add_directory_to_zip(\ZipArchive $zip, string $dir, string $prefix): void {
      foreach ($this->relative_files($dir) as $relative => $absolute) {
         $zip->addFile($absolute, ltrim($prefix . '/' . $relative, '/'));
      }
   }
}
?>
