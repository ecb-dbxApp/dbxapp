<?php
namespace dbx\dbxContent;

require_once __DIR__ . '/dbxContent_permalink.class.php';

require_once __DIR__ . '/dbxContentLng.class.php';
require_once __DIR__ . '/dbxContentMediaUsageScope.class.php';

class dbxContentRenderer {

   private $dd_media   = 'dbxMedia';
   private $dd_media_usage = 'dbxMediaUsage';
   private array $folderRowCache = array();

   /** Request-lokaler Folder-Cache fuer Rechte und vererbte Einstellungen. */
   private function folder_row($db, int $folderId): array {
      if ($folderId <= 0) return array();
      $key = dbxContentLng::ddFolder() . ':' . $folderId;
      if (!array_key_exists($key, $this->folderRowCache)) {
         $row = $db->select1(dbxContentLng::ddFolder(), $folderId, '*', 0);
         $this->folderRowCache[$key] = is_array($row) ? $row : array();
      }
      return $this->folderRowCache[$key];
   }

   /**
    * Ergaenzt performante Browser-Hinweise in der final gerenderten
    * Content-Seite. Das erste Bild wird als moegliches LCP-Bild priorisiert,
    * alle weiteren Bilder werden nativ lazy geladen.
    */
   public static function optimizeContentPageImages(string $html): string {
      $priorityAssigned = false;
      $result = preg_replace_callback('/<img\b[^>]*>/i', static function(array $match) use (&$priorityAssigned): string {
         $tag = (string)($match[0] ?? '');
         if ($tag === '') return $tag;

         if (!$priorityAssigned) {
            $tag = self::withHtmlImageAttr($tag, 'loading', 'eager', true);
            $tag = self::withHtmlImageAttr($tag, 'fetchpriority', 'high', true);
            $priorityAssigned = true;
         } else {
            // Pro Content-Seite genau ein moegliches LCP-Bild priorisieren.
            // Auch versehentlich gespeicherte eager-Attribute werden bei allen
            // nachfolgenden Bildern auf lazy/low normalisiert.
            $tag = self::withHtmlImageAttr($tag, 'loading', 'lazy', true);
            $tag = self::withHtmlImageAttr($tag, 'fetchpriority', 'low', true);
         }

         return self::withHtmlImageAttr($tag, 'decoding', 'async');
      }, $html);

      return is_string($result) ? $result : $html;
   }

   private static function withHtmlImageAttr(string $tag, string $name, string $value, bool $replace = false): string {
      $attrPattern = '/\s' . preg_quote($name, '/') . '\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i';
      $attribute = ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
      if (preg_match($attrPattern, $tag)) {
         if (!$replace) return $tag;
         $updated = preg_replace($attrPattern, $attribute, $tag, 1);
         return is_string($updated) ? $updated : $tag;
      }

      $closing = str_ends_with(rtrim($tag), '/>') ? '/>' : '>';
      $trimmed = rtrim($tag);
      return substr($trimmed, 0, -strlen($closing)) . $attribute . $closing;
   }

   public function render($cid) {
      return $this->interpretContentModules($this->renderStatic($cid));
   }

   public function renderNotFound(): string {
      $tpl = dbx()->get_system_obj('dbxTPL');
      $permalink = trim((string)dbx()->get_system_var('dbx_permalink', ''), '/');
      return $tpl->get_tpl('dbxContent|no-page', array(
         'permalink' => dbx()->esc($permalink !== '' ? $permalink : '/'),
      ));
   }

   public function renderStatic($cid, array $options = array()) {
      $cid = (int)$cid;
      if ($cid <= 0) return 'Keine dbx_cid gesetzt!';

      $db = dbx()->get_system_obj('dbxDB');
      $tpl = dbx()->get_system_obj('dbxTPL');
      $rec = $db->select1(dbxContentLng::ddContent(), $cid, '*', 0);
      if (!is_array($rec) || (int)($rec['id'] ?? 0) <= 0) {
         return $this->renderNotFound();
      }

      $rights = $this->resolve_content_rights($db, $rec);
      if (!dbx()->can($rights)) {
         if (empty($options['admin_help']) || !dbx()->can('admin')) {
            return '<div class="alert alert-warning" role="alert">Sie haben keinen Zugriff auf diese Seite.</div>';
         }
      }

      if (empty($options['skip_hits'])) {
         $this->update_hits($db, $rec);
      }

      $forceTemplate = trim((string)($options['template'] ?? ''));
      if ($forceTemplate !== '') {
         $template = $this->normalize_content_template($forceTemplate);
      } else {
         $template = $this->normalize_content_template($this->resolve_content_setting($db, $rec, 'template', 'template', 'c-content'));
      }

      $this->applySeoMeta($cid, $rec);

      $template_html = $tpl->get_tpl('dbxContent|' . $template);
      $slots = $this->detect_template_slots($template_html);
      $content_html = $this->convert_mod_placeholders((string)($rec['content'] ?? ''));
      $parsed = $this->parse_content($content_html, $slots);
      $parsed = $this->render_inline_media_placeholders($db, $parsed);
      $cms_cols = max(1, min(3, (int)($slots['cols'] ?? 1)));
      $documentationMetadata = $this->documentationTemplateMetadata((string)($rec['permalink'] ?? ''));

      $settings = $this->content_settings($db, $rec);
      $hero_classes = array(
         $this->css_class_token('hero-template', $settings['hero_template'] ?? 'image-hero'),
         $this->css_class_token('hero-variant', $settings['hero_variant'] ?? 'original'),
         $this->is_enabled_value($settings['hero_sticky'] ?? '0') ? 'hero-sticky' : 'hero-not-sticky',
         $this->css_class_token('hero-scroll', $settings['hero_scroll_layer'] ?? 'under'),
      );
      $gallery_classes = array(
         $this->css_class_token('gallery-template', $settings['gallery_template'] ?? 'image-gallery'),
         $this->css_class_token('gallery-overflow', $settings['gallery_overflow'] ?? 'grid'),
         $this->css_class_token('gallery-click', $settings['gallery_click_behavior'] ?? 'lightbox'),
         $this->css_class_token('gallery-count', $settings['gallery_visible_count'] ?? '3'),
         $this->css_class_token('gallery-size', $settings['gallery_image_size'] ?? 'original'),
         $this->css_class_token('gallery-lightbox-width', $settings['gallery_lightbox_width'] ?? '100vw'),
      );
      $hero_style = $this->css_custom_properties(array(
         'cms-hero-margin-top' => $this->css_length_value($settings['hero_margin_top'] ?? '0', '0'),
         'cms-hero-height' => $this->css_length_value($settings['hero_height'] ?? 'auto', 'auto'),
      ) + $this->hero_content_custom_properties($settings['hero_height'] ?? 'auto'));
      $gallery_style = $this->css_custom_properties(array(
         'dbx-gallery-visible-count' => (string)max(1, min(12, (int)($settings['gallery_visible_count'] ?? 3))),
         'dbx-gallery-aspect-ratio' => $this->gallery_aspect_ratio($settings['gallery_image_size'] ?? 'original'),
         'dbx-gallery-lightbox-width' => $this->css_length_value($settings['gallery_lightbox_width'] ?? '100vw', '100vw'),
      ));

      $merge = array(
         'i' => dbx()->next_id(),
         'id' => (string)($rec['id'] ?? ''),
         'title' => (string)($rec['title'] ?? ''),
         'description' => (string)($rec['description'] ?? ''),
         'keywords'    => (string)($rec['keywords'] ?? ''),
         'permalink' => (string)($rec['permalink'] ?? ''),
         'template' => $template,
         'doc:type' => $documentationMetadata['type'],
         'doc:audience' => $documentationMetadata['audience'],
         'doc:date' => $documentationMetadata['date'],
         'content' => $parsed['content'],
         'body' => $parsed['body'],
         'h1' => $parsed['h1'],
         'header' => $parsed['header'],
         'teaser' => $parsed['teaser'],
         'thesar' => (string)($rec['thesar'] ?? ''),
         'footer' => $parsed['footer'],
         'col_1' => $parsed['col1'],
         'col_2' => $parsed['col2'],
         'col_3' => $parsed['col3'],
         'content:h1' => $parsed['h1'],
         'content:header' => $parsed['header'],
         'content:hero' => $parsed['hero'],
         'content:teaser' => $parsed['teaser'],
         'content:thesar' => $parsed['teaser'],
         'content:footer' => $parsed['footer'],
         'content:body' => $parsed['body'],
         'content:col1' => $parsed['col1'],
         'content:col2' => $parsed['col2'],
         'content:col3' => $parsed['col3'],
         'cms:title' => $this->render_title_slot($tpl, $template_html, (string)($rec['title'] ?? '')),
         'cms:header' => $this->render_inline($tpl, 'header', $parsed['header']),
         'cms:teaser' => $this->render_inline($tpl, 'header', $parsed['header'], 'header'),
         'cms:content' => $this->render_inline($tpl, 'content', $parsed['content']),
         'cms:cols' => (string)$cms_cols,
         'cms:hero_class' => 'no-hero',
         'cms:gallery_class' => 'no-gallery',
         'cms:hero' => '',
         'cms:gallery' => '',
         'cms:footer' => $this->render_inline($tpl, 'footer', $parsed['footer']),
         'cms:col1' => $cms_cols === 1 ? $parsed['body'] : $this->render_inline($tpl, 'col', $parsed['col1'], 'col'),
         'cms:col2' => $this->render_inline($tpl, 'col', $parsed['col2'], 'col'),
         'cms:col3' => $this->render_inline($tpl, 'col', $parsed['col3'], 'col'),
      );

      $merge = array_merge($merge, array(
         'hero:template' => htmlspecialchars((string)($settings['hero_template'] ?? 'image-hero'), ENT_QUOTES, 'UTF-8'),
         'hero:image_id' => htmlspecialchars((string)($settings['hero_image_id'] ?? ''), ENT_QUOTES, 'UTF-8'),
         'hero:margin_top' => htmlspecialchars((string)($settings['hero_margin_top'] ?? '0'), ENT_QUOTES, 'UTF-8'),
         'hero:height' => htmlspecialchars((string)($settings['hero_height'] ?? 'auto'), ENT_QUOTES, 'UTF-8'),
         'hero:variant' => htmlspecialchars((string)($settings['hero_variant'] ?? 'original'), ENT_QUOTES, 'UTF-8'),
         'hero:sticky' => htmlspecialchars((string)($settings['hero_sticky'] ?? '0'), ENT_QUOTES, 'UTF-8'),
         'hero:scroll_layer' => htmlspecialchars((string)($settings['hero_scroll_layer'] ?? 'under'), ENT_QUOTES, 'UTF-8'),
         'hero:class' => implode(' ', array_filter($hero_classes)),
         'hero:style' => $hero_style,
         'gallery:template' => htmlspecialchars((string)($settings['gallery_template'] ?? 'image-gallery'), ENT_QUOTES, 'UTF-8'),
         'gallery:visible_count' => htmlspecialchars((string)($settings['gallery_visible_count'] ?? '3'), ENT_QUOTES, 'UTF-8'),
         'gallery:image_size' => htmlspecialchars((string)($settings['gallery_image_size'] ?? 'original'), ENT_QUOTES, 'UTF-8'),
         'gallery:lightbox_width' => htmlspecialchars($this->css_length_value($settings['gallery_lightbox_width'] ?? '100vw', '100vw'), ENT_QUOTES, 'UTF-8'),
         'gallery:overflow' => htmlspecialchars((string)($settings['gallery_overflow'] ?? 'grid'), ENT_QUOTES, 'UTF-8'),
         'gallery:click_behavior' => htmlspecialchars((string)($settings['gallery_click_behavior'] ?? 'lightbox'), ENT_QUOTES, 'UTF-8'),
         'gallery:class' => implode(' ', array_filter($gallery_classes)),
         'gallery:style' => $gallery_style,
      ));
      $merge = array_merge($merge, $this->render_media_merge($db, $tpl, $cid, $slots, $rec, $settings));
      $hero_media = trim((string)($merge['media:hero'] ?? ''));
      $hero_content = $this->render_inline($tpl, 'hero', $parsed['hero'], 'hero');
      $hero_content = trim((string)$hero_content);
      $merge['cms:hero'] = $hero_media
         . ($hero_content !== '' ? '<div class="hero-content">' . $hero_content . '</div>' : '');
      $merge['cms:gallery'] = $merge['media:gallery'] ?? '';
      $hero_state_classes = array(
         ($hero_media !== '' || $hero_content !== '') ? 'has-hero' : 'no-hero',
         $hero_media !== '' ? 'has-hero-media' : '',
         $hero_content !== '' ? 'has-hero-content' : '',
      );
      $merge['cms:hero_class'] = trim(implode(' ', array_filter($hero_state_classes)) . ' ' . ($merge['hero:class'] ?? ''));
      $merge['cms:gallery_class'] = trim((trim($merge['cms:gallery']) !== '' ? 'has-gallery ' : 'no-gallery ') . ($merge['gallery:class'] ?? ''));
      $has_gallery_slot = !empty($slots['media']['gallery']) || !empty($slots['cms']['gallery']);
      $has_gallery_media = trim((string)($merge['cms:gallery'] ?? '')) !== '';
      $merge['gallery:data_dbx'] = $this->gallery_data_dbx_attrs($settings, $has_gallery_slot, $has_gallery_media);
      $content_html = $this->replace_content_markers($template_html, $merge);
      $content_html = $this->cleanup_empty_sections($content_html);
      $content_html = $this->decorate_brand_text($content_html);
      $content_html = $tpl->replaces($content_html, $merge);
      return $content_html;
   }

   /**
    * Liefert ausschließlich die variablen Werte des Dokumentations-Templates.
    * Die sichtbare Struktur bleibt dadurch im Content-Template und wird nicht
    * mehr in jeden redaktionellen Seiteninhalt kopiert.
    */
   private function documentationTemplateMetadata(string $permalink): array {
      $type = 'Handbuch';
      $audience = 'Anwender';

      if (str_starts_with($permalink, 'tutorial-')) {
         $type = 'Tutorial';
      } elseif (preg_match('/(?:installation|selbsttest|betrieb|sicherheit|db3-mysql|system-update)/', $permalink) === 1) {
         $type = 'Betriebshandbuch';
         $audience = 'Administration';
      } elseif (preg_match('/(?:(?:^|-)ki(?:-|$)|codex|prompt|dbxki)/', $permalink) === 1) {
         $type = 'Arbeitsanweisung';
         $audience = 'KI-Agenten';
      } elseif (preg_match('/(?:entwickler|entwickeln|architektur|runtime|routing|dbxtpl|dbxdb|dbxform|dbxreport|modul|javascript|core|interpreter|rad|lifecycle|stecknorm)/', $permalink) === 1) {
         $type = str_contains($permalink, 'schnellstart') ? 'Schnellstart' : 'Entwicklerhandbuch';
         $audience = 'Entwickler';
      }

      $language = dbxContentLng::current();
      $translations = array(
         'en' => array(
            'Handbuch' => 'Manual',
            'Tutorial' => 'Tutorial',
            'Betriebshandbuch' => 'Operations manual',
            'Arbeitsanweisung' => 'Work instruction',
            'Entwicklerhandbuch' => 'Developer manual',
            'Schnellstart' => 'Quickstart',
            'Anwender' => 'Users',
            'Administration' => 'Administrators',
            'KI-Agenten' => 'AI agents',
            'Entwickler' => 'Developers',
         ),
         'es' => array(
            'Handbuch' => 'Manual',
            'Tutorial' => 'Tutorial',
            'Betriebshandbuch' => 'Manual de operaciones',
            'Arbeitsanweisung' => 'Instrucción de trabajo',
            'Entwicklerhandbuch' => 'Manual para desarrolladores',
            'Schnellstart' => 'Inicio rápido',
            'Anwender' => 'Usuarios',
            'Administration' => 'Administradores',
            'KI-Agenten' => 'Agentes de IA',
            'Entwickler' => 'Desarrolladores',
         ),
      );
      if (isset($translations[$language])) {
         $type = $translations[$language][$type] ?? $type;
         $audience = $translations[$language][$audience] ?? $audience;
      }

      return array(
         'type' => $type,
         'audience' => $audience,
         'date' => match ($language) {
            'en' => 'August 1, 2026',
            'es' => '1 de agosto de 2026',
            default => '1. August 2026',
         },
      );
   }

   private function decorate_brand_text(string $html): string {
      if ($html === '' || strpos($html, 'dbXapp') === false) {
         return $html;
      }

      return $this->replace_text_segments($html, function(string $text): string {
         return str_replace('dbXapp', 'db<span style="color:red">X</span>app', $text);
      });
   }

   private function replace_text_segments(string $html, callable $callback): string {
      $parts = preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
      if (!is_array($parts)) {
         return (string)$callback($html);
      }

      foreach ($parts as $idx => $part) {
         if ($part === '' || $part[0] === '<') {
            continue;
         }
         $parts[$idx] = (string)$callback($part);
      }

      return implode('', $parts);
   }

   public function applySeoMeta(int $cid, array $rec = null, array $meta = null) {
      $cid = (int)$cid;
      if ($cid <= 0) return;

      $db = dbx()->get_system_obj('dbxDB');
      if (!is_array($rec) || (int)($rec['id'] ?? 0) <= 0) {
         if (is_array($meta) && !empty($meta['seo']) && is_array($meta['seo'])) {
            $rec = array_merge(array('id' => $cid), $meta['seo']);
         } else {
            $rec = $db->select1(dbxContentLng::ddContent(), $cid, '*', 0);
            if (!is_array($rec) || (int)($rec['id'] ?? 0) <= 0) return;
         }
      }

      $pageTitle = trim((string)($rec['title'] ?? ''));
      $seoTitle = trim((string)($rec['seo_title'] ?? ''));
      $displayTitle = $seoTitle !== '' ? $seoTitle : $pageTitle;
      // Der redaktionelle Seitentitel bleibt die sichtbare Überschrift. Der
      // optionale SEO-Titel ist ausschließlich für <title>, OpenGraph und
      // strukturierte Metadaten bestimmt.
      dbx()->set_system_var('dbx_title', $pageTitle);
      dbx()->set_system_var('dbx_seo_title', $displayTitle);

      $description = trim((string)($rec['description'] ?? ''));
      if ($description === '') {
         $description = $this->seoExcerptFromContent((string)($rec['content'] ?? ''));
      }

      $keywords = trim((string)($rec['keywords'] ?? ''));
      $currentLng = strtolower(trim((string)dbx()->get_system_var('dbx_lng', 'de')));
      if ($currentLng === '') $currentLng = 'de';
      $isHomePage = $currentLng === dbxContentLngSync::masterLng()
         && dbxContentHome::masterCid() === $cid;
      $canonical = $this->seoCanonicalUrl((string)($rec['permalink'] ?? ''), $isHomePage);
      $activ = (int)($rec['activ'] ?? 1);
      $metaRobots = trim((string)($rec['meta_robots'] ?? ''));
      if ($metaRobots === '') {
         $metaRobots = ($activ === 0) ? 'noindex,nofollow' : 'index,follow';
      } elseif ($activ === 0 && stripos($metaRobots, 'noindex') === false) {
         $metaRobots = 'noindex,nofollow';
      }

      $og_title = $displayTitle;
      $og_image = $this->seoOgImageUrl($db, $rec);
      dbx()->set_system_var('dbx_meta_description', $description);
      dbx()->set_system_var('dbx_meta_keywords', $keywords);
      dbx()->set_system_var('dbx_canonical', $canonical);
      dbx()->set_system_var('dbx_robots', $metaRobots);
      dbx()->set_system_var('dbx_og_title', $og_title);
      dbx()->set_system_var('dbx_og_description', $description);
      dbx()->set_system_var('dbx_og_url', $canonical);
      dbx()->set_system_var('dbx_og_image', $og_image);
      dbx()->set_system_var('dbx_hreflang', $this->seoHreflangBlock($db, $rec, $currentLng));
      dbx()->set_system_var('dbx_json_ld', $this->seoJsonLd($rec, $displayTitle, $description, $canonical, $currentLng));
   }

   public static function seoMetaFromRecord(array $rec): array {
      return array(
         'title' => (string)($rec['title'] ?? ''),
         'seo_title' => (string)($rec['seo_title'] ?? ''),
         'description' => (string)($rec['description'] ?? ''),
         'keywords' => (string)($rec['keywords'] ?? ''),
         'meta_robots' => (string)($rec['meta_robots'] ?? ''),
         'seo_image_id' => (int)($rec['seo_image_id'] ?? 0),
         'permalink' => (string)($rec['permalink'] ?? ''),
         'activ' => (int)($rec['activ'] ?? 1),
         'update_date' => (string)($rec['update_date'] ?? ''),
         'lng_uid' => (string)($rec['lng_uid'] ?? ''),
      );
   }

   public static function resetSeoMeta() {
      foreach (array(
         'dbx_meta_description',
         'dbx_seo_title',
         'dbx_meta_keywords',
         'dbx_canonical',
         'dbx_robots',
         'dbx_og_title',
         'dbx_og_description',
         'dbx_og_url',
         'dbx_og_image',
         'dbx_hreflang',
         'dbx_json_ld',
      ) as $key) {
         dbx()->set_system_var($key, '');
      }
   }

   public function interpretContentModules($html) {
      return $this->interpret_content_modules($html);
   }

   private function interpret_content_modules($html) {
      $html = (string)$html;
      if ($html === '' || stripos($html, '[modul=') === false) {
         return $html;
      }
      $interpreter = dbx()->get_system_obj('dbxInterpreter');
      if (!is_object($interpreter) || !method_exists($interpreter, 'run')) {
         return $html;
      }
      return $interpreter->run($html);
   }

   public function getPublicFolderRights(int $folder_id): string {
      $db = dbx()->get_system_obj('dbxDB');
      return $this->resolve_folder_rights($db, $folder_id);
   }

   private function resolve_content_rights($db, array $rec): string {
      // Content-Seiten besitzen keine eigenen Leserechte. Ausschliesslich die
      // effektiven, gegebenenfalls vererbten Rechte ihres Ordners entscheiden.
      return $this->resolve_folder_rights($db, (int)($rec['folder'] ?? 0));
   }

   private function resolve_folder_rights($db, $folder_id, array $visited = array()) {
      $folder_id = (int)$folder_id;
      if ($folder_id <= 0) return '*';
      if (isset($visited[$folder_id])) return '*';
      $visited[$folder_id] = 1;

      $rec = $this->folder_row($db, $folder_id);
      if (!is_array($rec) || (int)($rec['id'] ?? 0) <= 0) return '*';

      $parent_id = (int)($rec['parent_id'] ?? 0);
      $raw = trim((string)($rec['group_read'] ?? ''));
      if ($raw === '') return '*';

      $parts = preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY);
      $out = array();
      $uses_parent = false;

      foreach ($parts as $part) {
         $part = trim((string)$part);
         if ($part === '') continue;
         if (strtolower($part) === 'parent') {
            $uses_parent = true;
            continue;
         }
         $out[$part] = 1;
      }

      if ($uses_parent) {
         $parent_rights = $this->resolve_folder_rights($db, $parent_id, $visited);
         foreach (preg_split('/\s*,\s*/', $parent_rights, -1, PREG_SPLIT_NO_EMPTY) as $part) {
            $part = trim((string)$part);
            if ($part !== '' && strtolower($part) !== 'parent') $out[$part] = 1;
         }
      }

      if (!count($out)) return '*';
      return implode(',', array_keys($out));
   }

   private function is_parent_value($value) {
      $value = trim((string)$value);
      return $value === '' || strtolower($value) === 'parent';
   }

   private function is_no_hero_value($value) {
      $value = strtolower(trim((string)$value));
      return in_array($value, array('none', 'no-hero', '0', 'off'), true);
   }

   private function is_enabled_value($value) {
      $value = strtolower(trim((string)$value));
      return in_array($value, array('1', 'true', 'on', 'yes', 'sticky'), true);
   }

   private function resolve_folder_setting($db, $folder_id, $field, $fallback, array $visited = array()) {
      $folder_id = (int)$folder_id;
      if ($folder_id <= 0 || isset($visited[$folder_id])) return $fallback;
      $visited[$folder_id] = 1;

      $rec = $this->folder_row($db, $folder_id);
      if (!is_array($rec) || (int)($rec['id'] ?? 0) <= 0) return $fallback;

      $value = trim((string)($rec[$field] ?? ''));
      if (!$this->is_parent_value($value)) return $value;
      return $this->resolve_folder_setting($db, (int)($rec['parent_id'] ?? 0), $field, $fallback, $visited);
   }

   private function resolve_content_setting($db, array $rec, $content_field, $folder_field, $fallback) {
      $value = trim((string)($rec[$content_field] ?? ''));
      if (!$this->is_parent_value($value)) return $value;
      return $this->resolve_folder_setting($db, (int)($rec['folder'] ?? 0), $folder_field, $fallback);
   }

   private function local_content_setting(array $rec, $field, $fallback) {
      $value = trim((string)($rec[$field] ?? ''));
      return $this->is_parent_value($value) ? $fallback : $value;
   }

   private function content_settings($db, array $rec) {
      return array(
         'hero_template' => $this->resolve_content_setting($db, $rec, 'hero_template', 'hero_template', 'image-hero'),
         'hero_image_id' => $this->resolve_content_setting($db, $rec, 'hero_image_id', 'hero_image_id', ''),
         'hero_margin_top' => $this->resolve_content_setting($db, $rec, 'hero_margin_top', 'hero_margin_top', '0'),
         'hero_height' => $this->resolve_content_setting($db, $rec, 'hero_height', 'hero_height', 'auto'),
         'hero_variant' => $this->resolve_content_setting($db, $rec, 'hero_variant', 'hero_variant', 'original'),
         'hero_sticky' => $this->resolve_content_setting($db, $rec, 'hero_sticky', 'hero_sticky', '0'),
         'hero_scroll_layer' => $this->resolve_content_setting($db, $rec, 'hero_scroll_layer', 'hero_scroll_layer', 'under'),
         'gallery_template' => 'image-gallery',
         'gallery_visible_count' => '3',
         'gallery_image_size' => $this->local_content_setting($rec, 'gallery_image_size', 'original'),
         'gallery_lightbox_width' => $this->local_content_setting($rec, 'gallery_lightbox_width', '100vw'),
         'gallery_overflow' => $this->local_content_setting($rec, 'gallery_overflow', 'grid'),
         'gallery_click_behavior' => $this->local_content_setting($rec, 'gallery_click_behavior', 'lightbox'),
      );
   }

   private function update_hits($db, array $rec) {
      $hits = (int)($rec['hits'] ?? 0);
      if ($hits < 0 || empty($rec['id'])) return;
      $db->update(dbxContentLng::ddContent(), array('hits' => $hits + 1), (int)$rec['id'], 0, 1, 1, 0);
   }

   private function get_content_tpl_from_folder($folder = 0) {
      $folder = (int)$folder;
      $db = dbx()->get_system_obj('dbxDB');
      while ($folder > 0) {
         $rec = $this->folder_row($db, $folder);
         if (!is_array($rec) || (int)($rec['id'] ?? 0) <= 0) break;
         $tpl = trim((string)($rec['template'] ?? ''));
         if ($tpl !== '') return $tpl;
         $folder = (int)($rec['parent_id'] ?? 0);
      }
      return 'c-content';
   }

   private function content_template_exists($template) {
      $template = trim((string)$template);
      if ($template === '') return false;
      $path = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/dbxContent/tpl/htm/' . $template . '.htm');
      return is_file($path);
   }

   private function normalize_content_template($template) {
      $template = trim((string)$template);
      if ($template === '' || strtolower($template) === 'parent') $template = 'c-content';
      if ($this->content_template_exists($template)) return $template;

      $aliases = array(
         'c-hero_header-gallery-body3-footer' => 'c-title-hero_header-gallery-body3-footer',
      );
      if (isset($aliases[$template]) && $this->content_template_exists($aliases[$template])) {
         return $aliases[$template];
      }

      $template_key = strtolower($template);
      $cols = strpos($template_key, 'col3') !== false ? '3' : (strpos($template_key, 'col2') !== false ? '2' : '');
      $has_hero = strpos($template_key, 'hero') !== false;
      $has_gallery = strpos($template_key, 'gallery') !== false;
      $has_header = strpos($template_key, 'teaser') !== false || strpos($template_key, 'thesar') !== false;

      if ($has_hero && $has_gallery && $cols === '3' && $this->content_template_exists('c-title-hero_header-gallery-body3-footer')) {
         return 'c-title-hero_header-gallery-body3-footer';
      }
      if ($has_hero && $has_gallery) return 'c-hero-header-gallery-body' . $cols . '-footer';
      if ($has_hero) return 'c-hero-header-body' . $cols . '-footer';
      if ($has_gallery) return 'c-header-gallery-body' . $cols . '-footer';
      if ($has_header || strpos($template_key, 'footer') !== false || $cols !== '') return 'c-header-body' . $cols . '-footer';
      return 'c-content';
   }

   private function detect_template_slots($template_html) {
      $template_html = (string)$template_html;
      $slots = array(
         'content' => array(),
         'media' => array(),
         'cols' => 1,
         'cms' => array(),
      );

      if (preg_match_all('/\{content:([a-z0-9_]+)\}/i', $template_html, $m)) {
         foreach ($m[1] as $slot) {
            $slots['content'][strtolower($slot)] = 1;
         }
      }
      if (preg_match_all('/\{media:([a-z0-9_]+)\}/i', $template_html, $m)) {
         foreach ($m[1] as $slot) {
            $slots['media'][strtolower($slot)] = 1;
         }
      }
      if (preg_match_all('/\{cms:([a-z0-9_]+)\}/i', $template_html, $m)) {
         foreach ($m[1] as $slot) {
            $slot = strtolower($slot);
            $slots['cms'][$slot] = 1;
            if (in_array($slot, array('hero', 'gallery'), true)) $slots['media'][$slot] = 1;
            if ($slot === 'hero') $slots['content']['hero'] = 1;
            if ($slot === 'header') $slots['content']['header'] = 1;
            if ($slot === 'teaser') $slots['content']['teaser'] = 1;
            if ($slot === 'content') $slots['content']['content'] = 1;
            if ($slot === 'footer') $slots['content']['footer'] = 1;
            if (in_array($slot, array('col1', 'col2', 'col3'), true)) $slots['content'][$slot] = 1;
         }
      }

      if (strpos($template_html, '{content}') !== false || strpos($template_html, '{content:body}') !== false) {
         $slots['content']['content'] = 1;
      }

      if (isset($slots['content']['col3']) || strpos($template_html, '{col_3}') !== false || strpos($template_html, '{cms:col3}') !== false) $slots['cols'] = 3;
      elseif (isset($slots['content']['col2']) || strpos($template_html, '{col_2}') !== false || strpos($template_html, '{cms:col2}') !== false) $slots['cols'] = 2;
      elseif (isset($slots['content']['col1']) || strpos($template_html, '{col_1}') !== false || strpos($template_html, '{cms:col1}') !== false) $slots['cols'] = 1;
      else $slots['cols'] = 1;

      return $slots;
   }

   private function parse_content($html, array $slots) {
      $blocks = $this->html_blocks($html);
      $segments = $this->segment_content_blocks($blocks);

      $hero_blocks = $segments['hero'];
      $header_blocks = $segments['header'];
      $footer_blocks = $segments['footer'];
      $body_blocks = $segments['content'];

      $has_hero_slot = !empty($slots['content']['hero']) || !empty($slots['cms']['hero']);
      $has_header_slot = !empty($slots['content']['header']) || !empty($slots['content']['teaser'])
         || !empty($slots['content']['thesar']) || !empty($slots['cms']['header']);
      $has_footer_slot = !empty($slots['content']['footer']) || !empty($slots['cms']['footer']);

      if (!$has_hero_slot) {
         $body_blocks = array_merge($hero_blocks, $body_blocks);
         $hero_blocks = array();
      }
      if (!$has_header_slot) {
         $body_blocks = array_merge($header_blocks, $body_blocks);
         $header_blocks = array();
      }
      if (!$has_footer_slot) {
         $body_blocks = array_merge($body_blocks, $footer_blocks);
         $footer_blocks = array();
      }

      $cols = max(1, min(3, (int)($slots['cols'] ?? 1)));
      $col_blocks = $this->split_content_for_columns($body_blocks, $cols);
      $all_content = $this->strip_dbx_markers($this->join_blocks($blocks));
      $body_html = $this->strip_dbx_markers($this->join_blocks($body_blocks));

      return array(
         'h1' => '',
         'hero' => $this->strip_dbx_markers($this->join_blocks($hero_blocks)),
         'header' => $this->strip_dbx_markers($this->join_blocks($header_blocks)),
         'teaser' => $this->strip_dbx_markers($this->join_blocks($header_blocks)),
         'footer' => $this->strip_dbx_markers($this->join_blocks($footer_blocks)),
         'body' => $body_html,
         'content' => (isset($slots['content']['content']) || $cols === 1) ? $body_html : $all_content,
         'col1' => $cols === 1 ? $body_html : $this->strip_dbx_markers($this->join_blocks($col_blocks[0] ?? array())),
         'col2' => $this->strip_dbx_markers($this->join_blocks($col_blocks[1] ?? array())),
         'col3' => $this->strip_dbx_markers($this->join_blocks($col_blocks[2] ?? array())),
      );
   }

   private function html_blocks($html) {
      $html = trim((string)$html);
      if ($html === '') return array();

      $pattern = '/(<!--\s*dbx:[^>]*?-->|<hr\b[^>]*>|<(h1|h2|h3|h4|h5|h6|p|ul|ol|blockquote|figure|table|div)\b[^>]*>.*?<\/\2>|<img\b[^>]*>)/is';
      preg_match_all($pattern, $html, $matches, PREG_OFFSET_CAPTURE);

      $blocks = array();
      $pos = 0;
      foreach ($matches[0] as $idx => $match) {
         $before = trim(substr($html, $pos, $match[1] - $pos));
         if ($before !== '') $blocks[] = array('type' => 'html', 'html' => $before);

         $chunk = $match[0];
         $type = strtolower((string)($matches[2][$idx][0] ?? 'html'));
         if (preg_match('/^<!--\s*dbx:/i', $chunk)) $type = 'marker';
         elseif (preg_match('/^<hr/i', $chunk)) $type = $this->marker_name_from_html($chunk) !== '' ? 'marker' : 'break';
         elseif (preg_match('/^<img/i', $chunk)) $type = 'img';

         $blocks[] = array('type' => $type, 'html' => $chunk);
         $pos = $match[1] + strlen($chunk);
      }

      $rest = trim(substr($html, $pos));
      if ($rest !== '') $blocks[] = array('type' => 'html', 'html' => $rest);
      return $blocks;
   }

   private function segment_content_blocks(array $blocks) {
      $hero_idx = null;
      $header_idx = null;
      $footer_idx = null;
      $footer_hr_idx = null;

      foreach ($blocks as $idx => $block) {
         if ($hero_idx === null && $this->is_dbx_marker($block, array('hero'))) {
            $hero_idx = $idx;
         }
         if ($header_idx === null && $this->is_dbx_marker($block, array('header', 'teaser', 'thesar'))) {
            $header_idx = $idx;
         }
         if ($this->is_dbx_marker($block, array('footer'))) {
            if ($footer_idx === null) $footer_idx = $idx;
            if ($footer_hr_idx === null && $this->is_hr_marker($block)) $footer_hr_idx = $idx;
         }
      }
      if ($footer_hr_idx !== null) $footer_idx = $footer_hr_idx;

      $result = array(
         'hero' => array(),
         'header' => array(),
         'content' => array(),
         'footer' => array(),
      );
      $n = count($blocks);
      $has_hero_marker = $hero_idx !== null;

      $markers = array();
      if ($hero_idx !== null) $markers[] = array('type' => 'hero', 'idx' => $hero_idx);
      if ($header_idx !== null) $markers[] = array('type' => 'header', 'idx' => $header_idx);
      if ($footer_idx !== null) $markers[] = array('type' => 'footer', 'idx' => $footer_idx);
      usort($markers, function($a, $b) {
         return (int)$a['idx'] <=> (int)$b['idx'];
      });

      $pos = 0;
      foreach ($markers as $marker) {
         $idx = (int)$marker['idx'];
         $gap = $idx > $pos ? array_slice($blocks, $pos, $idx - $pos) : array();
         if ($marker['type'] === 'hero') {
            $result['hero'] = $gap;
            $pos = $idx + 1;
            continue;
         }
         if ($marker['type'] === 'header') {
            if ($has_hero_marker && $hero_idx < $idx) {
               $result['header'] = $gap;
            } else {
               $result['content'] = array_merge($result['content'], $gap);
            }
            $pos = $idx + 1;
            continue;
         }
         if ($marker['type'] === 'footer') {
            $result['content'] = array_merge($result['content'], $gap);
            $result['footer'] = array_slice($blocks, $idx + 1);
            return $result;
         }
      }

      if ($pos < $n) {
         $result['content'] = array_merge($result['content'], array_slice($blocks, $pos));
      }
      return $result;
   }

   private function split_content_for_columns(array $blocks, $cols) {
      $cols = max(1, min(3, (int)$cols));
      $result = array_fill(0, $cols, array());
      if ($cols === 1) {
         $result[0] = $blocks;
         return $result;
      }

      $marker_split = $this->split_by_column_markers($blocks, $cols);
      if ($marker_split !== null) return $marker_split;

      return $this->split_by_text_weight($blocks, $cols);
   }

   private function split_by_column_markers(array $blocks, $cols) {
      $result = array_fill(0, $cols, array());
      $current = 0;
      $has = false;
      foreach ($blocks as $block) {
         if ($cols === 2 && $this->is_dbx_marker($block, array('col2', 'split'))) {
            $has = true;
            $current = 1;
            continue;
         }
         if ($cols === 3 && $this->is_dbx_marker($block, array('col2', 'col3a'))) {
            $has = true;
            $current = 1;
            continue;
         }
         if ($cols === 3 && $this->is_dbx_marker($block, array('col3', 'col3b'))) {
            $has = true;
            $current = 2;
            continue;
         }
         $result[$current][] = $block;
      }
      return $has ? $result : null;
   }

   private function split_by_text_weight(array $blocks, $cols) {
      $units = $this->column_units_from_blocks($blocks, $cols);
      $result = array_fill(0, $cols, array());
      if (empty($units)) return $result;

      $parts = $this->partition_column_units($units, $cols);
      $parts = $this->keep_leading_blanks_with_previous_column($parts);
      foreach ($parts as $idx => $part) {
         if ($idx >= $cols) break;
         $result[$idx] = $this->column_units_to_blocks($part);
      }
      return $result;
   }

   private function column_units_from_blocks(array $blocks, $cols) {
      $units = array();
      $list_index = 0;
      $paragraph_index = 0;

      foreach ($blocks as $block) {
         $type = strtolower((string)($block['type'] ?? 'html'));
         if ($type === 'ul' || $type === 'ol') {
            $list_units = $this->list_units_from_block($block, 'list-' . $list_index, $cols);
            $list_index++;
            if ($list_units !== null) {
               $units = array_merge($units, $list_units);
               continue;
            }
         }
         if ($type === 'p') {
            $paragraph_units = $this->paragraph_units_from_block($block, 'p-' . $paragraph_index, $cols);
            $paragraph_index++;
            if ($paragraph_units !== null) {
               $units = array_merge($units, $paragraph_units);
               continue;
            }
         }

         $units[] = array(
            'kind' => 'block',
            'block' => $block,
            'weight' => $this->estimate_block_weight($block, $cols),
         );
      }

      return $units;
   }

   private function paragraph_units_from_block(array $block, $paragraph_key, $cols) {
      $html = trim((string)($block['html'] ?? ''));
      if ($html === '' || $this->is_blank_flow_html($html)) return null;
      if (preg_match('/<(img|video|table|ul|ol|figure|blockquote|div|section|article|aside|header|footer|h[1-6]|pre)\b/i', $html)) return null;
      if (!preg_match('/^<\s*p\b([^>]*)>([\s\S]*)<\/\s*p\s*>$/i', $html, $m)) return null;

      $attrs = (string)$m[1];
      $chunks = $this->inline_safe_chunks((string)$m[2], $cols);
      if (count($chunks) < 5) return null;

      $weights = array();
      $total = 0;
      foreach ($chunks as $chunk) {
         $weight = $this->estimate_inline_chunk_weight($chunk, $cols);
         $weights[] = $weight;
         $total += $weight;
      }

      $threshold = max(260, ((int)$cols === 3 ? 240 : 320));
      if ($total <= $threshold) return null;

      $units = array();
      foreach ($chunks as $idx => $chunk) {
         if ($chunk === '') continue;
         $units[] = array(
            'kind' => 'paragraph_piece',
            'paragraph_key' => $paragraph_key,
            'attrs' => $attrs,
            'html' => $chunk,
            'weight' => max(0, (int)($weights[$idx] ?? 0)),
         );
      }

      return count($units) > 1 ? $units : null;
   }

   private function inline_safe_chunks($inner_html, $cols) {
      $tokens = preg_split('/(<[^>]+>)/', (string)$inner_html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
      if (!is_array($tokens) || empty($tokens)) return array();

      $chunks = array();
      $count = count($tokens);
      $chunk_limit = $this->column_chars_per_line($cols);
      for ($i = 0; $i < $count; $i++) {
         $token = $tokens[$i];
         if (preg_match('/^<[^>]+>$/', $token)) {
            $tag = $this->tag_name($token);
            if ($tag !== '' && $this->is_opening_tag($token) && !$this->is_void_tag($tag) && !preg_match('/\/\s*>$/', $token)) {
               $chunk = $token;
               $depth = 1;
               while ($depth > 0 && $i + 1 < $count) {
                  $i++;
                  $next = $tokens[$i];
                  $chunk .= $next;
                  if (!preg_match('/^<[^>]+>$/', $next)) continue;
                  $next_tag = $this->tag_name($next);
                  if ($next_tag !== $tag) continue;
                  if ($this->is_closing_tag($next)) {
                     $depth--;
                  } elseif ($this->is_opening_tag($next) && !preg_match('/\/\s*>$/', $next)) {
                     $depth++;
                  }
               }
               $chunks[] = $chunk;
               continue;
            }
            $chunks[] = $token;
            continue;
         }

         if (preg_match_all('/\S+\s*/u', $token, $words)) {
            $buffer = '';
            foreach ($words[0] as $word) {
               $candidate = $buffer . $word;
               if ($buffer !== '' && $this->text_length(strip_tags($candidate)) > $chunk_limit) {
                  $chunks[] = $buffer;
                  $buffer = $word;
               } else {
                  $buffer = $candidate;
               }
            }
            if ($buffer !== '') $chunks[] = $buffer;
         }
      }

      return $chunks;
   }

   private function tag_name($tag) {
      return preg_match('/^<\s*\/?\s*([a-z0-9]+)/i', (string)$tag, $m) ? strtolower($m[1]) : '';
   }

   private function is_opening_tag($tag) {
      return preg_match('/^<\s*[a-z0-9]/i', (string)$tag) === 1;
   }

   private function is_closing_tag($tag) {
      return preg_match('/^<\s*\//', (string)$tag) === 1;
   }

   private function is_void_tag($tag) {
      return in_array(strtolower((string)$tag), array('area','base','br','col','embed','hr','img','input','link','meta','param','source','track','wbr'), true);
   }

   private function estimate_inline_chunk_weight($html, $cols) {
      $html = (string)$html;
      $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')));
      $chars = $this->text_length($text);
      $weight = (int)ceil(($chars / max(1, $this->column_chars_per_line($cols))) * 100);
      $weight += substr_count(strtolower($html), '<br') * 100;
      return max(1, $weight);
   }

   private function list_units_from_block(array $block, $list_key, $cols) {
      $html = trim((string)($block['html'] ?? ''));
      if (!preg_match('/^<\s*(ul|ol)\b([^>]*)>([\s\S]*)<\/\s*\1\s*>$/i', $html, $m)) return null;

      $tag = strtolower($m[1]);
      $attrs = (string)$m[2];
      $items = $this->extract_top_level_list_items((string)$m[3]);
      if (count($items) < 2) return null;

      $units = array();
      foreach ($items as $item_html) {
         $units[] = array(
            'kind' => 'list_item',
            'list_key' => $list_key,
            'tag' => $tag,
            'attrs' => $attrs,
            'html' => $item_html,
            'weight' => $this->estimate_list_item_weight($item_html, $cols),
         );
      }
      return $units;
   }

   private function extract_top_level_list_items($inner_html) {
      $inner_html = (string)$inner_html;
      if (!preg_match_all('/<li\b[^>]*>|<\/li\s*>/i', $inner_html, $matches, PREG_OFFSET_CAPTURE)) {
         return array();
      }

      $items = array();
      $depth = 0;
      $start = null;

      foreach ($matches[0] as $match) {
         $tag = $match[0];
         $offset = $match[1];
         if (preg_match('/^<li\b/i', $tag)) {
            if ($depth === 0) $start = $offset;
            $depth++;
            continue;
         }

         if ($depth === 0) continue;
         $depth--;
         if ($depth === 0 && $start !== null) {
            $items[] = substr($inner_html, $start, $offset + strlen($tag) - $start);
            $start = null;
         }
      }

      return $depth === 0 ? $items : array();
   }

   private function estimate_list_item_weight($html, $cols) {
      $nested_items = max(0, substr_count(strtolower((string)$html), '<li') - 1);
      return max(100, $this->estimate_text_weight((string)$html, $cols, 1) + ($nested_items * 120));
   }

   private function partition_column_units(array $units, $cols) {
      $cols = max(1, min(3, (int)$cols));
      $n = count($units);
      if ($cols === 1 || $n === 0) return array($units);

      if ($n < $cols) {
         $parts = array_fill(0, $cols, array());
         foreach ($units as $idx => $unit) $parts[$idx][] = $unit;
         return $parts;
      }

      $prefix = array(0);
      foreach ($units as $unit) {
         $prefix[] = end($prefix) + max(0, (int)($unit['weight'] ?? 0));
      }

      $total = $prefix[$n];
      $target = $total > 0 ? $total / $cols : 1;
      $inf = PHP_INT_MAX;
      $dp = array();
      $prev = array();

      for ($c = 0; $c <= $cols; $c++) {
         $dp[$c] = array_fill(0, $n + 1, $inf);
         $prev[$c] = array_fill(0, $n + 1, -1);
      }

      for ($i = 1; $i <= $n; $i++) {
         $dp[1][$i] = $this->column_partition_cost($prefix, 0, $i, $target);
         $prev[1][$i] = 0;
      }

      for ($c = 2; $c <= $cols; $c++) {
         for ($i = $c; $i <= $n; $i++) {
            for ($j = $c - 1; $j < $i; $j++) {
               if ($dp[$c - 1][$j] === $inf) continue;
               $cost = $dp[$c - 1][$j] + $this->column_partition_cost($prefix, $j, $i, $target);
               if ($cost < $dp[$c][$i]) {
                  $dp[$c][$i] = $cost;
                  $prev[$c][$i] = $j;
               }
            }
         }
      }

      $parts = array_fill(0, $cols, array());
      $i = $n;
      for ($c = $cols; $c >= 1; $c--) {
         $j = $prev[$c][$i];
         if ($j < 0) $j = max(0, $i - 1);
         $parts[$c - 1] = array_slice($units, $j, $i - $j);
         $i = $j;
      }

      return $parts;
   }

   private function keep_leading_blanks_with_previous_column(array $parts) {
      for ($i = 1; $i < count($parts); $i++) {
         while (!empty($parts[$i]) && $this->is_blank_column_unit($parts[$i][0])) {
            $parts[$i - 1][] = array_shift($parts[$i]);
         }
      }
      return $parts;
   }

   private function is_blank_column_unit(array $unit) {
      if (($unit['kind'] ?? '') !== 'block' || !isset($unit['block']) || !is_array($unit['block'])) return false;
      $block = $unit['block'];
      return strtolower((string)($block['type'] ?? '')) === 'p' && $this->is_blank_flow_html((string)($block['html'] ?? ''));
   }

   private function column_partition_cost(array $prefix, $start, $end, $target) {
      $sum = $prefix[$end] - $prefix[$start];
      $diff = $sum - $target;
      return $diff * $diff;
   }

   private function column_units_to_blocks(array $units) {
      $blocks = array();
      $pending = null;

      foreach ($units as $unit) {
         if (($unit['kind'] ?? '') === 'list_item') {
            $key = (string)($unit['list_key'] ?? '');
            if ($pending === null || ($pending['kind'] ?? '') !== 'list' || $pending['list_key'] !== $key) {
               $this->flush_pending_column_group($pending, $blocks);
               $pending = array(
                  'kind' => 'list',
                  'list_key' => $key,
                  'tag' => (string)($unit['tag'] ?? 'ul'),
                  'attrs' => (string)($unit['attrs'] ?? ''),
                  'items' => array(),
               );
            }
            $pending['items'][] = (string)($unit['html'] ?? '');
            continue;
         }
         if (($unit['kind'] ?? '') === 'paragraph_piece') {
            $key = (string)($unit['paragraph_key'] ?? '');
            if ($pending === null || ($pending['kind'] ?? '') !== 'paragraph' || $pending['paragraph_key'] !== $key) {
               $this->flush_pending_column_group($pending, $blocks);
               $pending = array(
                  'kind' => 'paragraph',
                  'paragraph_key' => $key,
                  'attrs' => (string)($unit['attrs'] ?? ''),
                  'pieces' => array(),
               );
            }
            $pending['pieces'][] = (string)($unit['html'] ?? '');
            continue;
         }
         $this->flush_pending_column_group($pending, $blocks);
         if (isset($unit['block']) && is_array($unit['block'])) $blocks[] = $unit['block'];
      }

      $this->flush_pending_column_group($pending, $blocks);
      return $blocks;
   }

   private function flush_pending_column_group(&$pending, array &$blocks) {
      if ($pending === null) return;
      $kind = (string)($pending['kind'] ?? '');
      $tag = (string)($pending['tag'] ?? '');
      if ($kind === 'list') {
         $tag = preg_match('/^(ul|ol)$/i', $tag) ? strtolower($tag) : 'ul';
         $html = '<' . $tag . (string)$pending['attrs'] . '>' . implode('', $pending['items']) . '</' . $tag . '>';
      } elseif ($kind === 'paragraph') {
         $tag = 'p';
         $html = '<p' . (string)$pending['attrs'] . '>' . implode('', $pending['pieces']) . '</p>';
      } else {
         $pending = null;
         return;
      }
      $blocks[] = array(
         'type' => $tag,
         'html' => $html,
      );
      $pending = null;
   }

   private function estimate_block_weight(array $block, $cols) {
      $html = (string)($block['html'] ?? '');
      $type = strtolower((string)($block['type'] ?? 'html'));

      if ($type === 'marker') return 0;
      if ($type === 'img') return $this->estimate_media_weight($html, $cols);
      if ($type === 'figure') return $this->estimate_media_weight($html, $cols) + min(300, $this->estimate_text_weight($html, $cols, 0));
      if ($type === 'table') return 500 + substr_count(strtolower($html), '<tr') * 100;
      if ($type === 'ul' || $type === 'ol') return max(200, substr_count(strtolower($html), '<li') * 100 + $this->estimate_text_weight($html, $cols, 0));
      if ($type === 'p' && preg_match('/<(img|video)\b/i', $html)) return $this->estimate_media_weight($html, $cols) + min(300, $this->estimate_text_weight($html, $cols, 0));
      if ($type === 'p' && $this->is_blank_flow_html($html)) return 0;
      if (in_array($type, array('h1', 'h2', 'h3', 'h4', 'h5', 'h6'), true)) return max(130, $this->estimate_text_weight($html, $cols, 1) + 60);
      if ($type === 'blockquote') return max(180, $this->estimate_text_weight($html, $cols, 1) + 80);

      return max(60, $this->estimate_text_weight($html, $cols, 1));
   }

   private function estimate_text_weight($html, $cols, $min_lines = 0) {
      $html = (string)$html;
      $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')));
      $chars = $this->text_length($text);
      $lines = $chars > 0 ? ($chars / max(1, $this->column_chars_per_line($cols))) : 0;
      $lines += substr_count(strtolower($html), '<br');
      $lines = max((float)$min_lines, $lines);
      return (int)ceil($lines * 100);
   }

   private function estimate_media_weight($html, $cols) {
      if (!preg_match_all('/<(img|video)\b[^>]*>/i', (string)$html, $matches)) {
         return $this->default_media_lines($cols) * 100;
      }

      $weight = 0;
      foreach ($matches[0] as $tag) {
         $weight += $this->estimate_media_lines_from_tag($tag, $cols) * 100;
      }
      return max($this->default_media_lines($cols) * 100, $weight);
   }

   private function estimate_media_lines_from_tag($tag, $cols) {
      $default = $this->default_media_lines($cols);
      $width = 0;
      $height = 0;
      if (preg_match('/\bwidth=["\']?([0-9.]+)/i', (string)$tag, $m)) $width = (float)$m[1];
      if (preg_match('/\bheight=["\']?([0-9.]+)/i', (string)$tag, $m)) $height = (float)$m[1];
      if ($width <= 0 || $height <= 0) return $default;

      $ratio = $height / $width;
      $base = ((int)$cols === 3) ? 14 : 19;
      return max(8, min(24, (int)round($ratio * $base)));
   }

   private function default_media_lines($cols) {
      return ((int)$cols === 3) ? 10 : 14;
   }

   private function column_chars_per_line($cols) {
      if ((int)$cols === 3) return 42;
      if ((int)$cols === 2) return 58;
      return 78;
   }

   private function text_length($text) {
      $text = (string)$text;
      return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
   }

   private function is_blank_flow_html($html) {
      $html = (string)$html;
      if (preg_match('/<(img|video|picture|source|iframe|embed|object|svg|canvas)\b/i', $html)) return false;
      $html = preg_replace('/<\/?p\b[^>]*>/i', '', $html);
      $html = preg_replace('/<br\s*\/?>/i', '', $html);
      $html = str_replace('&nbsp;', '', $html);
      return trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')) === '';
   }

   private function join_blocks(array $blocks) {
      $html = '';
      foreach ($blocks as $block) {
         if ($this->is_dbx_marker($block, array('pagebreak'))) {
            $html .= '<div class="dbx-content-pagebreak" aria-hidden="true"></div>' . "\n";
            continue;
         }
         if (($block['type'] ?? '') === 'marker') continue;
         $html .= ($block['html'] ?? '') . "\n";
      }
      return trim($html);
   }

   private function is_dbx_marker(array $block, array $names) {
      if (($block['type'] ?? '') !== 'marker') return false;
      $name = $this->marker_name_from_html((string)($block['html'] ?? ''));
      return $name !== '' && in_array($name, $names, true);
   }

   private function is_hr_marker(array $block) {
      if (($block['type'] ?? '') !== 'marker') return false;
      return preg_match('/^\s*<hr\b/i', (string)($block['html'] ?? '')) === 1;
   }

   private function marker_name_from_html($html) {
      $html = (string)$html;
      if (preg_match('/<!--\s*dbx:([a-z0-9_-]+)/i', $html, $m)) return $this->canonical_marker_name($m[1]);
      if (preg_match('/\bdata-dbx-marker=["\'](?:dbx:)?([a-z0-9_-]+)["\']/i', $html, $m)) return $this->canonical_marker_name($m[1]);
      if (preg_match('/\bclass=["\'][^"\']*\bdbx-cms-marker-([a-z0-9_-]+)\b/i', $html, $m)) return $this->canonical_marker_name($m[1]);
      if (preg_match('/\bclass=["\'][^"\']*\bdbx_marker_([a-z0-9_-]+)\b/i', $html, $m)) return $this->canonical_marker_name($m[1]);
      if (preg_match('/\bclass=["\'][^"\']*\bdbx_split\b/i', $html)) return 'col2';
      return '';
   }

   private function canonical_marker_name($name) {
      $name = strtolower(trim((string)$name));
      if (in_array($name, array('hero_text', 'hero-text', 'herotext'), true)) return 'hero';
      return $name;
   }

   private function convert_mod_placeholders($html) {
      $html = (string)$html;
      if ($html === '' || stripos($html, 'dbx-cms-mod-placeholder') === false) return $html;

      return preg_replace_callback(
         '/<(p|figure|div)\b([^>]*\bdbx-cms-mod-placeholder\b[^>]*)>([\s\S]*?)<\/\1>/i',
         function($m) {
            $attrs = (string)($m[2] ?? '');
            $inner = (string)($m[3] ?? '');
            $modul = '';
            $params = '';
            $data_dbx = '';
            $alt = '';

            if (preg_match('/\bdata-cms-mod-modul=["\']([^"\']*)["\']/i', $attrs, $mm)) {
               $modul = trim((string)$mm[1]);
            }
            if (preg_match('/\bdata-cms-mod-params=["\']([^"\']*)["\']/i', $attrs, $mm)) {
               $params = trim((string)$mm[1]);
            }
            if ($modul === '' && preg_match('/\bdata-cms-mod-modul=["\']([^"\']*)["\']/i', $inner, $mm)) {
               $modul = trim((string)$mm[1]);
            }
            if ($params === '' && preg_match('/\bdata-cms-mod-params=["\']([^"\']*)["\']/i', $inner, $mm)) {
               $params = trim((string)$mm[1]);
            }
            if ($modul === '' && preg_match('/<img\b[^>]*\bdata-cms-mod-modul=["\']([^"\']*)["\']/i', $inner, $mm)) {
               $modul = trim((string)$mm[1]);
            }
            if ($params === '' && preg_match('/<img\b[^>]*\bdata-cms-mod-params=["\']([^"\']*)["\']/i', $inner, $mm)) {
               $params = trim((string)$mm[1]);
            }
            if (preg_match('/<img\b[^>]*\bdata-dbx=["\']([^"\']*)["\']/i', $inner, $mm)) {
               $data_dbx = trim((string)$mm[1]);
            }
            if (preg_match('/<img\b[^>]*\balt=["\']([^"\']*)["\']/i', $inner, $mm)) {
               $alt = trim((string)$mm[1]);
            }

            if (($modul === '' || $params === '') && preg_match('/modul_image[^"\']*file=([^"\'&]+)/i', $inner . $attrs, $fileMatch)) {
               $base = dirname(__DIR__, 3) . '/dbxAdmin/include/dbxModuleImages.class.php';
               if (is_file($base)) {
                  require_once $base;
                  $resolved = (new \dbx\dbxAdmin\dbxModuleImages())->resolveFromFilename(rawurldecode((string)$fileMatch[1]));
                  if (is_array($resolved) && $resolved) {
                     if ($modul === '') {
                        $modul = (string)($resolved['default_modul'] ?? '');
                     }
                     if ($params === '') {
                        $params = (string)($resolved['default_params'] ?? '');
                     }
                  }
               }
            }

            $modul = preg_replace('/[^A-Za-z0-9_]+/', '', $modul);
            if ($modul === '') return $m[0];

            $params = str_replace(array("\r", "\n"), '', $params);
            $marker = '[modul=' . $modul . ']' . $params . '[/modul]';
            $data_dbx = htmlspecialchars($data_dbx, ENT_QUOTES, 'UTF-8');
            $alt_attr = $alt !== '' ? ' title="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '"' : '';

            if ($data_dbx !== '') {
               return '<div class="dbx-cms-mod-output" data-dbx="' . $data_dbx . '"' . $alt_attr . '>' . $marker . '</div>';
            }
            return $marker;
         },
         $html
      );
   }

   private function strip_dbx_markers($html) {
      $html = preg_replace('/<!--\s*dbx:[\s\S]*?-->/i', '', (string)$html);
      $html = preg_replace('/<hr\b[^>]*(?:data-dbx-marker|dbx-cms-marker|dbx_marker_|dbx_split)[^>]*>/i', '', $html);
      return trim($html);
   }

   private function render_inline_media_placeholders($db, array $parsed) {
      foreach ($parsed as $key => $html) {
         if (!is_string($html)) continue;
         if (stripos($html, 'dbx-cms-inline-video-block') !== false) {
            $html = $this->render_inline_video_placeholders($db, $html);
         }
         if (stripos($html, 'dbx_mid=') !== false || stripos($html, 'data-cms-media-id') !== false) {
            $html = $this->render_inline_image_sanitize($db, $html);
         }
         $parsed[$key] = $html;
      }
      return $parsed;
   }

   private function render_inline_image_sanitize($db, $html) {
      return preg_replace_callback('/<(img)\b([^>]*?)>/i', function($m) use ($db) {
         $attrs = (string)($m[2] ?? '');
         $id = 0;
         if (preg_match('/data-cms-media-id=["\']?([0-9]+)/i', $attrs, $id_match)) {
            $id = (int)$id_match[1];
         } elseif (preg_match('/dbx_mid=([0-9]+)/i', $attrs, $id_match)) {
            $id = (int)$id_match[1];
         }
         if ($id <= 0) return $m[0];
         $row = $db->select1($this->dd_media, $id);
         if (!is_array($row) || (int)($row['active'] ?? 0) !== 1 || !$this->media_file_exists($row)) return '';
         $tag = (string)$m[0];
         $width = max(0, (int)($row['width'] ?? 0));
         $height = max(0, (int)($row['height'] ?? 0));
         if ($width > 0 && $height > 0) {
            $tag = self::withHtmlImageAttr($tag, 'width', (string)$width);
            $tag = self::withHtmlImageAttr($tag, 'height', (string)$height);
         }
         return $tag;
      }, (string)$html);
   }

   private function render_inline_video_placeholders($db, $html) {
      return preg_replace_callback('/<figure\b([^>]*\bdbx-cms-inline-video-block\b[^>]*)>[\s\S]*?<\/figure>/i', function($m) use ($db) {
         $attrs = $m[1];
         if (!preg_match('/data-cms-media-id=["\']?([0-9]+)/i', $attrs, $id_match)) return $m[0];
         $id = (int)$id_match[1];
         $row = $db->select1($this->dd_media, $id);
         if (!is_array($row) || (int)($row['active'] ?? 0) !== 1 || !$this->media_file_exists($row)) return '';
         return $this->render_inline_video_player($row, $attrs, $m[0]);
      }, (string)$html);
   }

   private function inline_media_type(array $row) {
      if ((string)($row['media_type'] ?? '') === 'external_video') return 'external_video';
      $mime = (string)($row['mime'] ?? '');
      if (strpos($mime, 'video/') === 0 || preg_match('/\.(mp4|webm|ogv|ogg|mov|m4v)$/i', (string)($row['file_name'] ?? $row['file_path'] ?? ''))) return 'video';
      if (strtolower((string)($row['storage_type'] ?? '')) === 'external') return 'external_video';
      return 'file';
   }

   private function render_inline_video_player(array $row, $source_attrs = '', $source_html = '') {
      $id = (int)($row['id'] ?? 0);
      $type = $this->inline_media_type($row);
      if ($id <= 0 || !in_array($type, array('video', 'external_video'), true)) return '';

      $source_attrs = (string)$source_attrs;
      $source_html = (string)$source_html;
      $video_options = $this->inline_video_options_from_html($source_attrs, $source_html);
      $style = $video_options['style'] !== '' ? ' style="' . htmlspecialchars($video_options['style'], ENT_QUOTES, 'UTF-8') . '"' : '';
      $autoplay = !empty($video_options['autoplay']);
      $loop = !empty($video_options['loop']);
      $muted = !empty($video_options['muted']);
      $align = (string)($video_options['align'] ?? 'left');
      if ($autoplay) {
         $muted = true;
      }

      $title = htmlspecialchars((string)($row['title'] ?? $row['alt'] ?? $row['file_name'] ?? 'Video'), ENT_QUOTES, 'UTF-8');
      $caption = trim((string)($row['caption'] ?? ''));
      $caption_html = $caption !== '' ? '<figcaption>' . htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') . '</figcaption>' : '';
      $figure_attr = ' class="dbx-content-inline-media dbx-content-inline-video" data-media-id="' . $id . '" data-video-align="' . htmlspecialchars($align, ENT_QUOTES, 'UTF-8') . '"' . $style;

      if ($type === 'external_video') {
         $url = (string)($row['embed_url'] ?? $row['external_url'] ?? '');
         if ($url === '') return '';
         $url = $this->external_video_option_url($url, $autoplay, $muted, $loop);
         $loading = $autoplay ? 'eager' : 'lazy';
         if ($this->is_youtube_media($row, $url)) {
            return '<figure' . $figure_attr . '>' . $this->render_youtube_consent_block($url, (string)($row['title'] ?? $row['alt'] ?? $row['file_name'] ?? 'Video'), $loading) . $caption_html . '</figure>';
         }
         return '<figure' . $figure_attr . '><iframe class="dbx-content-video-player" src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" title="' . $title . '" loading="' . $loading . '" allowfullscreen></iframe>' . $caption_html . '</figure>';
      }

      $url = 'index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid=' . $id;
      $poster = !empty($row['thumb_file_path']) ? $url . '&dbx_thumb=1' : '';
      $poster_attr = $poster !== '' ? ' poster="' . htmlspecialchars($poster, ENT_QUOTES, 'UTF-8') . '"' : '';
      $mime_attr = trim((string)($row['mime'] ?? '')) !== '' ? ' type="' . htmlspecialchars((string)$row['mime'], ENT_QUOTES, 'UTF-8') . '"' : '';
      $autoplay_attr = $autoplay ? ' autoplay' : '';
      $loop_attr = $loop ? ' loop' : '';
      $muted_attr = $muted ? ' muted' : '';
      return '<figure' . $figure_attr . '><video class="dbx-content-video-player" controls preload="metadata" playsinline' . $autoplay_attr . $loop_attr . $muted_attr . $poster_attr . '><source src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"' . $mime_attr . '></video>' . $caption_html . '</figure>';
   }

   private function inline_video_options_from_html($source_attrs, $source_html) {
      $style = '';
      if (preg_match('/\sstyle=(["\'])(.*?)\1/i', (string)$source_attrs, $m)) {
         $style = $this->safe_inline_video_style($m[2]);
      }

      $width = $this->attr_value_from_html($source_attrs, 'data-cms-video-width');
      $height = $this->attr_value_from_html($source_attrs, 'data-cms-video-height');
      $align_raw = $this->attr_value_from_html($source_attrs, 'data-cms-video-align');
      if ($width === '') $width = $this->attr_value_from_html($source_attrs, 'width');
      if ($height === '') $height = $this->attr_value_from_html($source_attrs, 'height');

      if ((string)$source_html !== '' && preg_match('/<(?:img|video|iframe)\b([^>]*)>/i', (string)$source_html, $media_match)) {
         $media_attrs = $media_match[1];
         if ($width === '') $width = $this->attr_value_from_html($media_attrs, 'data-cms-video-width');
         if ($height === '') $height = $this->attr_value_from_html($media_attrs, 'data-cms-video-height');
         if ($width === '') $width = $this->attr_value_from_html($media_attrs, 'width');
         if ($height === '') $height = $this->attr_value_from_html($media_attrs, 'height');
         if ($align_raw === '') $align_raw = $this->attr_value_from_html($media_attrs, 'data-cms-video-align');
         if ($style === '' && preg_match('/\sstyle=(["\'])(.*?)\1/i', $media_attrs, $style_match)) {
            $style = $this->safe_inline_video_style($style_match[2]);
         }
      }

      $width = $this->css_size_value($width);
      $height = $this->css_size_value($height);
      $style_parts = $this->style_to_map($style);
      if ($width !== '') $style_parts['width'] = $width;
      if ($height !== '') $style_parts['height'] = $height;
      $align = $this->inline_video_align_value($align_raw, $style_parts);
      if ($align === 'center') {
         unset($style_parts['float']);
         $style_parts['margin-left'] = 'auto';
         $style_parts['margin-right'] = 'auto';
      } elseif ($align === 'right') {
         unset($style_parts['float']);
         $style_parts['margin-left'] = 'auto';
         $style_parts['margin-right'] = '0px';
      } elseif ($align_raw !== '') {
         unset($style_parts['float'], $style_parts['margin-left'], $style_parts['margin-right']);
      }

      $autoplay_raw = $this->attr_value_from_html($source_attrs, 'data-cms-video-autoplay');
      if ($autoplay_raw === '' && (string)$source_html !== '' && preg_match('/<(?:img|video|iframe)\b([^>]*)>/i', (string)$source_html, $media_match)) {
         $autoplay_raw = $this->attr_value_from_html($media_match[1], 'data-cms-video-autoplay');
      }
      $muted_raw = $this->attr_value_from_html($source_attrs, 'data-cms-video-muted');
      if ($muted_raw === '' && (string)$source_html !== '' && preg_match('/<(?:img|video|iframe)\b([^>]*)>/i', (string)$source_html, $media_match)) {
         $muted_raw = $this->attr_value_from_html($media_match[1], 'data-cms-video-muted');
      }

      $loop_raw = $this->attr_value_from_html($source_attrs, 'data-cms-video-loop');
      if ($loop_raw === '' && (string)$source_html !== '' && preg_match('/<(?:img|video|iframe)\b([^>]*)>/i', (string)$source_html, $media_match)) {
         $loop_raw = $this->attr_value_from_html($media_match[1], 'data-cms-video-loop');
      }

      return array(
         'style' => $this->style_map_to_string($style_parts),
         'autoplay' => preg_match('/^(1|true|yes|ja|on)$/i', trim((string)$autoplay_raw)) === 1,
         'loop' => preg_match('/^(1|true|yes|ja|on)$/i', trim((string)$loop_raw)) === 1,
         'muted' => preg_match('/^(1|true|yes|ja|on)$/i', trim((string)$muted_raw)) === 1,
         'align' => $align,
      );
   }

   private function inline_video_align_value($value, array $style = array()) {
      $value = strtolower(trim((string)$value));
      if (in_array($value, array('left', 'center', 'right'), true)) return $value;
      $margin_left = strtolower(trim((string)($style['margin-left'] ?? '')));
      $margin_right = strtolower(trim((string)($style['margin-right'] ?? '')));
      if ($margin_left === 'auto' && $margin_right === 'auto') return 'center';
      if ($margin_left === 'auto') return 'right';
      return 'left';
   }

   private function attr_value_from_html($attrs, $name) {
      $name = preg_quote((string)$name, '/');
      if (preg_match('/\s' . $name . '\s*=\s*(["\'])(.*?)\1/i', (string)$attrs, $m)) return trim((string)$m[2]);
      if (preg_match('/\s' . $name . '\s*=\s*([^\s>]+)/i', (string)$attrs, $m)) return trim((string)$m[1], "\"' ");
      return '';
   }

   private function css_size_value($value) {
      $value = trim((string)$value);
      if ($value === '') return '';
      if (preg_match('/^[0-9]+(?:\.[0-9]+)?$/', $value)) return $value . 'px';
      if (preg_match('/^[0-9]+(?:\.[0-9]+)?(?:px|%|vw|vh|rem|em)$/i', $value)) return $value;
      if (strtolower($value) === 'auto') return 'auto';
      return '';
   }

   private function safe_inline_video_style($style) {
      $out = array();
      foreach ($this->style_to_map($style) as $key => $value) {
         if (!in_array($key, array('width', 'height', 'max-width', 'float', 'margin-left', 'margin-right', 'display'), true)) continue;
         if (in_array($key, array('width', 'height', 'max-width', 'margin-left', 'margin-right'), true)) {
            $value = $this->css_size_value($value);
            if ($value === '') continue;
         } elseif ($key === 'float') {
            if (!in_array(strtolower($value), array('left', 'right', 'none'), true)) continue;
            $value = strtolower($value);
         } elseif ($key === 'display') {
            if (!in_array(strtolower($value), array('block', 'inline-block'), true)) continue;
            $value = strtolower($value);
         }
         $out[$key] = $value;
      }
      return $this->style_map_to_string($out);
   }

   private function style_to_map($style) {
      $out = array();
      foreach (explode(';', (string)$style) as $part) {
         if (strpos($part, ':') === false) continue;
         list($key, $value) = array_map('trim', explode(':', $part, 2));
         $key = strtolower($key);
         if ($key === '' || $value === '') continue;
         $out[$key] = $value;
      }
      return $out;
   }

   private function style_map_to_string(array $style) {
      $out = array();
      foreach ($style as $key => $value) {
         if ((string)$value === '') continue;
         $out[] = $key . ': ' . $value;
      }
      return implode('; ', $out);
   }

   private function external_video_option_url($url, $autoplay = false, $muted = false, $loop = false) {
      $url = trim((string)$url);
      if ($url === '') return '';
      $join = (strpos($url, '?') === false) ? '?' : '&';
      $params = array('playsinline' => '1', 'rel' => '0');
      if ($autoplay) $params['autoplay'] = '1';
      $params['mute'] = $muted ? '1' : '0';
      if ($loop) {
         $params['loop'] = '1';
         $video_id = $this->youtube_video_id_from_url($url);
         if ($video_id !== '') $params['playlist'] = $video_id;
      }
      foreach ($params as $key => $value) {
         if (preg_match('/(?:[?&])' . preg_quote($key, '/') . '=/i', $url)) continue;
         $url .= $join . rawurlencode($key) . '=' . rawurlencode($value);
         $join = '&';
      }
      return $url;
   }

   private function youtube_video_id_from_url($url) {
      $url = trim((string)$url);
      if ($url === '') return '';
      if (preg_match('~(?:embed/|v=|youtu\.be/)([A-Za-z0-9_-]{11})~i', $url, $m)) {
         return (string)$m[1];
      }
      return '';
   }

   private function is_youtube_media(array $row, $url) {
      $provider = strtolower(trim((string)($row['provider'] ?? '')));
      if ($provider === 'youtube') return true;
      $url = trim((string)$url);
      if ($url !== '' && preg_match('~(?:youtube\.com|youtu\.be)~i', $url)) return true;
      $embed = trim((string)($row['embed_url'] ?? $row['external_url'] ?? ''));
      if ($embed !== '' && preg_match('~(?:youtube\.com|youtu\.be)~i', $embed)) return true;
      $provider_id = trim((string)($row['provider_id'] ?? ''));
      return $provider_id !== '' && preg_match('~^[A-Za-z0-9_-]{11}$~', $provider_id) && $provider !== 'vimeo';
   }

   private function render_youtube_consent_block($embed_url, $title, $loading = 'lazy') {
      $embed_url = trim((string)$embed_url);
      if ($embed_url === '') return '';
      $title = htmlspecialchars(trim((string)$title), ENT_QUOTES, 'UTF-8');
      $loading = ($loading === 'eager') ? 'eager' : 'lazy';
      $video_id = $this->youtube_video_id_from_url($embed_url);
      $thumb = $video_id !== '' ? 'https://img.youtube.com/vi/' . $video_id . '/hqdefault.jpg' : '';
      $thumb_html = $thumb !== ''
         ? '<img class="dbx-youtube-consent-thumb" src="' . htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8') . '" alt="" loading="lazy">'
         : '';
      return '<div class="dbx-youtube-consent-placeholder"'
         . ' data-youtube-embed-url="' . htmlspecialchars($embed_url, ENT_QUOTES, 'UTF-8') . '"'
         . ' data-youtube-title="' . $title . '"'
         . ' data-youtube-loading="' . $loading . '">'
         . $thumb_html
         . '<button type="button" class="dbx-youtube-consent-play" aria-label="Video abspielen">'
         . '<i class="bi bi-play-fill"></i></button>'
         . '</div>';
   }

   private function render_youtube_hero_figure(array $row, $url, array $settings) {
      $url = $this->external_video_option_url($url, false, false, false);
      $title = (string)($row['title'] ?? $row['alt'] ?? $row['file_name'] ?? 'Video');
      $caption = trim((string)($row['caption'] ?? ''));
      $caption_html = $caption !== '' ? '<figcaption>' . htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') . '</figcaption>' : '';
      $hero_variant = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower((string)($settings['hero_variant'] ?? 'original')));
      $hero_scroll = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower((string)($settings['hero_scroll_layer'] ?? 'under')));
      $hero_classes = array(
         'dbx-media',
         'dbx-media-video',
         'dbx-media-external-video',
         'dbx-media-hero',
         'dbx-media-hero-' . ($hero_variant !== '' ? $hero_variant : 'original'),
         $this->is_enabled_value($settings['hero_sticky'] ?? '0') ? 'dbx-media-hero-sticky' : '',
         'dbx-media-hero-scroll-' . ($hero_scroll !== '' ? $hero_scroll : 'under'),
      );
      $style = $this->css_custom_properties(array(
         'cms-hero-margin-top' => $this->css_length_value($settings['hero_margin_top'] ?? '0', '0'),
         'cms-hero-height' => $this->css_length_value($settings['hero_height'] ?? 'auto', 'auto'),
      ));
      return '<figure class="' . htmlspecialchars(implode(' ', array_filter($hero_classes)), ENT_QUOTES, 'UTF-8') . '"'
         . ($style !== '' ? ' style="' . $style . '"' : '')
         . '>' . $this->render_youtube_consent_block($url, $title, 'lazy') . $caption_html . '</figure>';
   }

   private function render_media_merge($db, $tpl, $cid, array $slots, array $rec, array $settings = array()) {
      $merge = array(
         'media:hero' => '',
         'media:gallery' => '',
         'media:header' => '',
         'media:teaser' => '',
         'media:footer' => '',
         'media_hero' => '',
         'media_gallery' => '',
         'media_header' => '',
         'media_teaser' => '',
         'media_footer' => '',
      );

      $usage_rows = $db->select(
         $this->dd_media_usage,
         dbxContentMediaUsageScope::withLanguage('content_id = ' . (int)$cid . ' AND active = 1'),
         '*',
         'slot,sorter,id',
         'ASC',
         '',
         0,
         0,
         0
      );
      $rows = array();
      if (is_array($usage_rows) && !empty($usage_rows)) {
         $mediaIds = array_values(array_filter(array_unique(array_map(
            static fn($usage) => (int)($usage['media_id'] ?? 0),
            $usage_rows
         ))));
         $mediaById = array();
         if ($mediaIds) {
            $mediaRows = $db->select(
               $this->dd_media,
               'id IN (' . implode(',', $mediaIds) . ') AND active = 1',
               '*',
               '',
               'ASC',
               '',
               0,
               0,
               0
            );
            foreach ((is_array($mediaRows) ? $mediaRows : array()) as $mediaRow) {
               $mediaById[(int)($mediaRow['id'] ?? 0)] = $mediaRow;
            }
         }
         foreach ($usage_rows as $usage) {
            if (!is_array($usage)) continue;
            $media_id = (int)($usage['media_id'] ?? 0);
            if ($media_id <= 0) continue;
            $row = $mediaById[$media_id] ?? array();
            if (!$row) continue;
            $row['slot'] = (string)($usage['slot'] ?? 'gallery');
            $row['sorter'] = (string)($usage['sorter'] ?? '');
            if (!empty($usage['template'])) $row['template'] = $usage['template'];
            if (!empty($usage['caption'])) $row['caption'] = $usage['caption'];
            $row['usage_id'] = (int)($usage['id'] ?? 0);
            $rows[] = $row;
         }
      } else {
         $rows = $db->select($this->dd_media, 'content_id = ' . (int)$cid . ' AND active = 1', '*', 'slot,sorter,title,id', 'ASC', '', 0, 0, 0);
      }
      if (!is_array($rows)) return $merge;

      $by_slot = array();
      foreach ($rows as $row) {
         if (!is_array($row)) continue;
         if (!$this->media_file_exists($row)) continue;
         $slot = trim((string)($row['slot'] ?? 'gallery')) ?: 'gallery';
         if (!isset($by_slot[$slot])) $by_slot[$slot] = array();
         $by_slot[$slot][] = $row;
      }

      if (!$settings) $settings = $this->content_settings($db, $rec);
      $needed = array_keys($slots['media'] ?? array());
      foreach (array('hero', 'gallery', 'header', 'teaser', 'footer') as $slot) {
         if (!in_array($slot, $needed, true)) continue;
         $rows_for_slot = $by_slot[$slot] ?? array();
         if ($slot === 'hero') {
            if ($this->is_no_hero_value($settings['hero_template'] ?? '') || $this->is_no_hero_value($settings['hero_image_id'] ?? '')) continue;
            $rows_for_slot = $this->one_hero_row($db, $rows_for_slot, $settings['hero_image_id']);
         }
         if (empty($rows_for_slot)) continue;
         $html = $this->render_media_slot($tpl, $rows_for_slot, $slot, $settings);
         $merge['media:' . $slot] = $html;
         $merge['media_' . $slot] = $html;
      }
      return $merge;
   }

   private function one_hero_row($db, array $rows, $hero_id) {
      $hero_id = (int)$hero_id;
      if ($hero_id > 0) {
         $row = $db->select1($this->dd_media, $hero_id);
         if (is_array($row) && (int)($row['active'] ?? 0) === 1 && $this->media_file_exists($row)) return array($row);
      }
      return isset($rows[0]) ? array($rows[0]) : array();
   }

   private function render_media_slot($tpl, array $rows, $slot, array $settings) {
      if (!$rows) return '';
      $html = '';
      foreach ($rows as $row) {
         $mime = (string)($row['mime'] ?? '');
         $type = ((string)($row['media_type'] ?? '') === 'external_video')
            ? 'external_video'
            : ((strpos($mime, 'video/') === 0) ? 'video' : ((strpos($mime, 'image/') === 0 || preg_match('/\.(jpg|jpeg|png|gif|webp|svg)$/i', (string)($row['file_name'] ?? ''))) ? 'image' : 'file'));
         $template = trim((string)($row['template'] ?? ''));
         if ($slot === 'hero' && !empty($settings['hero_template'])) $template = $settings['hero_template'];
         if ($slot === 'gallery' && !empty($settings['gallery_template'])) {
            $template = $settings['gallery_template'];
            if ($type === 'image' && $template === 'video-gallery') $template = 'image-gallery';
            if (($type === 'video' || $type === 'external_video') && in_array($template, array('image-gallery', 'carousel3', 'cols3'), true)) $template = $type . '-gallery';
            if ($type === 'file' && in_array($template, array('image-gallery', 'video-gallery', 'carousel3', 'cols3'), true)) $template = 'file-gallery';
         }
         if ($template === '' || strtolower($template) === 'parent') $template = $type . '-' . ($slot === 'gallery' ? 'gallery' : $slot);
         if (!$this->media_template_exists($template)) $template = $type . '-' . ($slot === 'gallery' ? 'gallery' : $slot);
         if (!$this->media_template_exists($template)) $template = 'file-' . ($slot === 'gallery' ? 'gallery' : $slot);

         $url = $type === 'external_video'
            ? (string)($row['embed_url'] ?? $row['external_url'] ?? '')
            : 'index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid=' . (int)($row['id'] ?? 0);
         if ($type === 'external_video' && $slot === 'hero' && $this->is_youtube_media($row, $url)) {
            $html .= $this->render_youtube_hero_figure($row, $url, $settings);
            continue;
         }
         if ($type === 'external_video') {
            $thumb_url = $this->external_video_thumb_url($row);
            $poster_url = $thumb_url;
         } else {
            $thumb_url = !empty($row['thumb_file_path']) ? $url . '&dbx_thumb=1' : $url;
            $poster_url = !empty($row['thumb_file_path']) ? $url . '&dbx_thumb=1' : '';
            if ($slot === 'gallery'
               && $type === 'image'
               && strtolower(trim((string)($settings['gallery_overflow'] ?? ''))) === 'tutorial') {
               $thumb_url = $url;
            }
         }
         $data = array(
            'id' => (string)($row['id'] ?? ''),
            'url' => htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
            'thumb_url' => htmlspecialchars($thumb_url, ENT_QUOTES, 'UTF-8'),
            'poster_url' => htmlspecialchars($poster_url, ENT_QUOTES, 'UTF-8'),
            'media_type' => htmlspecialchars($type, ENT_QUOTES, 'UTF-8'),
            'title' => htmlspecialchars((string)($row['title'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'alt' => htmlspecialchars((string)($row['alt'] ?? $row['title'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'caption' => htmlspecialchars((string)($row['caption'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'slot' => htmlspecialchars($slot, ENT_QUOTES, 'UTF-8'),
            'mime' => htmlspecialchars($mime, ENT_QUOTES, 'UTF-8'),
            'hero_margin_top' => htmlspecialchars($this->css_length_value($settings['hero_margin_top'] ?? '0', '0'), ENT_QUOTES, 'UTF-8'),
            'hero_height' => htmlspecialchars($this->css_length_value($settings['hero_height'] ?? 'auto', 'auto'), ENT_QUOTES, 'UTF-8'),
            'hero_variant' => htmlspecialchars((string)($settings['hero_variant'] ?? 'original'), ENT_QUOTES, 'UTF-8'),
            'hero_sticky_class' => $this->is_enabled_value($settings['hero_sticky'] ?? '0') ? 'dbx-media-hero-sticky' : '',
            'hero_scroll_class' => 'dbx-media-hero-scroll-' . htmlspecialchars((string)($settings['hero_scroll_layer'] ?? 'under'), ENT_QUOTES, 'UTF-8'),
         );
         $width = max(0, (int)($row['width'] ?? 0));
         $height = max(0, (int)($row['height'] ?? 0));
         $thumbWidth = max(0, (int)($row['thumb_width'] ?? 0));
         $thumbHeight = max(0, (int)($row['thumb_height'] ?? 0));
         if ($thumbWidth <= 0 || $thumbHeight <= 0) {
            $thumbWidth = $width;
            $thumbHeight = $height;
         }
         $visibleCount = max(1, min(12, (int)($settings['gallery_visible_count'] ?? 3)));
         $data['image_dimensions'] = ($width > 0 && $height > 0)
            ? ' width="' . $width . '" height="' . $height . '"'
            : '';
         $data['thumb_dimensions'] = ($thumbWidth > 0 && $thumbHeight > 0)
            ? ' width="' . $thumbWidth . '" height="' . $thumbHeight . '"'
            : '';
         $data['image_sizes'] = $slot === 'gallery'
            ? '(max-width: 767px) 100vw, ' . (int)ceil(100 / $visibleCount) . 'vw'
            : '100vw';
         $html .= $tpl->get_tpl('dbxContent|media-' . $template, $data);
      }
      return $html;
   }

   private function external_video_thumb_url(array $row) {
      $provider = strtolower(trim((string)($row['provider'] ?? '')));
      $provider_id = trim((string)($row['provider_id'] ?? ''));
      if ($provider === 'youtube' && preg_match('~^[A-Za-z0-9_-]{11}$~', $provider_id)) {
         return 'https://img.youtube.com/vi/' . $provider_id . '/hqdefault.jpg';
      }
      return '';
   }

   private function media_template_exists($template) {
      $template = preg_replace('/[^a-z0-9_-]+/i', '', (string)$template);
      if ($template === '') return false;
      $path = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/dbxContent/tpl/htm/media-' . strtolower($template) . '.htm');
      return is_file($path);
   }

   private function media_file_exists(array $row) {
      if (strtolower((string)($row['storage_type'] ?? '')) === 'external') {
         return trim((string)($row['embed_url'] ?? $row['external_url'] ?? '')) !== '';
      }
      $rel = ltrim(str_replace('\\', '/', (string)($row['file_path'] ?? '')), '/');
      if ($rel === '' || strpos($rel, '..') !== false) return false;

      $root = dbx()->os_path(rtrim(dbx()->get_file_dir(), '/\\') . '/');
      $file = dbx()->os_path($root . $rel);

      $base = realpath($root);
      $real = realpath($file);
      return $base && $real && strpos($real, $base) === 0 && is_file($real) && is_readable($real);
   }

   private function replace_content_markers($html, array $merge) {
      foreach ($merge as $key => $value) {
         if (strpos($key, ':') !== false) {
            $html = str_replace('{' . $key . '}', $value, $html);
         }
      }
      return $html;
   }

   private function render_inline($tpl, $slot, $value, $obj_key = '') {
      if (!$this->html_has_visible_content($value)) return '';
      $file = 'i-' . $slot;
      $data_key = $obj_key !== '' ? $obj_key : $slot;
      $path = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/dbxContent/tpl/htm/' . strtolower($file) . '.htm');
      if (!is_file($path)) return $value;
      $html = $tpl->get_tpl('dbxContent|' . $file, array('obj:' . $data_key => $value, $data_key => $value));
      if (strpos($html, 'not found') !== false || trim($html) === '') return $value;
      $html = str_replace('{obj:' . $data_key . '}', $value, $html);
      return $html;
   }

   private function html_has_visible_content($html) {
      $html = $this->strip_dbx_markers((string)$html);
      if (trim($html) === '') return false;
      if (stripos($html, '[modul=') !== false) return true;
      if (preg_match('/<(img|video|iframe|source|object|embed|table|ul|ol|li|figure)\b/i', $html)) return true;
      $text = preg_replace('/<!--[\s\S]*?-->/u', '', $html);
      $text = preg_replace('/<br\s*\/?>/iu', '', $text);
      $text = strip_tags($text);
      $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
      $text = str_replace("\xC2\xA0", ' ', $text);
      return trim($text) !== '';
   }

   private function render_title_slot($tpl, $template_html, $title) {
      $title = htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8');
      if (preg_match('/<h[1-6]\b[^>]*>[^<]*\{cms:title\}/i', (string)$template_html)) {
         return $title;
      }
      return $this->render_inline($tpl, 'title', $title);
   }

   private function css_class_token($prefix, $value) {
      $prefix = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower((string)$prefix));
      $value = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower((string)$value));
      $value = trim($value, '-');
      if ($value === '') $value = 'default';
      return trim($prefix . '-' . $value, '-');
   }

   private function css_style_value($value, $fallback = '') {
      $value = trim((string)$value);
      if ($value === '' || strtolower($value) === 'parent') $value = (string)$fallback;
      $value = preg_replace('/[;"<>]+/', '', $value);
      $value = preg_replace('/[^a-z0-9\s#.,%()+\-\/_]+/i', '', $value);
      return trim($value);
   }

   private function css_length_value($value, $fallback = '') {
      $value = $this->css_style_value($value, $fallback);
      if ($value === '') return '';
      if (preg_match('/^-?(?:[0-9]+|[0-9]*\.[0-9]+)$/', $value)) {
         return $value === '0' ? '0' : $value . 'px';
      }
      return $value;
   }

   private function hero_content_custom_properties($height) {
      $scale = $this->hero_content_scale($height);
      return array(
         'cms-hero-content-scale' => $this->css_number($scale),
         'cms-hero-content-size' => $this->css_px(18 * $scale),
         'cms-hero-title-size' => $this->css_px(46 * $scale),
         'cms-hero-subtitle-size' => $this->css_px(30 * $scale),
         'cms-hero-content-padding' => $this->css_px(10 * $scale),
         'cms-hero-content-offset' => $this->css_px(34 * $scale),
      );
   }

   private function hero_content_scale($height) {
      $px = $this->css_length_to_px($height);
      if ($px <= 0) return 1.0;

      $scale = $px / 420.0;
      if ($scale < 0.72) $scale = 0.72;
      if ($scale > 1.85) $scale = 1.85;
      return $scale;
   }

   private function css_length_to_px($value) {
      $value = strtolower(trim($this->css_length_value($value, 'auto')));
      if ($value === '' || $value === 'auto' || $value === 'parent') return 0.0;

      if (preg_match('/^([0-9]+(?:\.[0-9]+)?)px$/', $value, $m)) return (float)$m[1];
      if (preg_match('/^([0-9]+(?:\.[0-9]+)?)$/', $value, $m)) return (float)$m[1];
      if (preg_match('/^([0-9]+(?:\.[0-9]+)?)(rem|em)$/', $value, $m)) return (float)$m[1] * 16.0;
      if (preg_match('/^([0-9]+(?:\.[0-9]+)?)(vh|svh|lvh|dvh)$/', $value, $m)) return (float)$m[1] * 7.2;
      if (preg_match('/^([0-9]+(?:\.[0-9]+)?)vw$/', $value, $m)) return (float)$m[1] * 12.8;

      return 0.0;
   }

   private function css_px($value) {
      return $this->css_number($value) . 'px';
   }

   private function css_number($value) {
      $value = (float)$value;
      $out = number_format($value, 3, '.', '');
      return rtrim(rtrim($out, '0'), '.');
   }

   private function css_custom_properties(array $props) {
      $out = array();
      foreach ($props as $name => $value) {
         $name = preg_replace('/[^a-z0-9_-]+/i', '-', (string)$name);
         $value = trim((string)$value);
         if ($name === '' || $value === '') continue;
         $out[] = '--' . strtolower($name) . ':' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
      }
      return implode(';', $out);
   }

   private function gallery_aspect_ratio($size) {
      if (preg_match('/^\s*([0-9]{1,5})\s*x\s*([0-9]{1,5})\s*$/i', (string)$size, $m)) {
         $w = max(1, (int)$m[1]);
         $h = max(1, (int)$m[2]);
         return $w . ' / ' . $h;
      }
      return '4 / 3';
   }

   private function gallery_data_dbx_attrs(array $settings, bool $has_slot, bool $has_media): string {
      if (!$has_slot || !$has_media) {
         return '';
      }

      $parts = array(
         'lib=gallery',
         'img-count=' . max(1, min(12, (int)($settings['gallery_visible_count'] ?? 3))),
         'img-size=' . trim((string)($settings['gallery_image_size'] ?? 'original')),
         'lightbox-width=' . $this->css_length_value($settings['gallery_lightbox_width'] ?? '100vw', '100vw'),
         'overflow=' . trim((string)($settings['gallery_overflow'] ?? 'grid')),
         'click=' . trim((string)($settings['gallery_click_behavior'] ?? 'lightbox')),
      );

      return ' data-dbx="' . htmlspecialchars(implode('|', $parts), ENT_QUOTES, 'UTF-8') . '"';
   }

   private function seoExcerptFromContent($html, $max = 160) {
      $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string)$html), ENT_QUOTES, 'UTF-8')));
      if ($text === '') return '';

      $len = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
      if ($len <= $max) return $text;

      $excerpt = function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
      if (function_exists('mb_strrpos')) {
         $cut = mb_strrpos($excerpt, ' ', 0, 'UTF-8');
         if ($cut !== false && $cut > 40) {
            $excerpt = mb_substr($excerpt, 0, $cut, 'UTF-8');
         }
      } elseif (($cut = strrpos($excerpt, ' ')) !== false && $cut > 40) {
         $excerpt = substr($excerpt, 0, $cut);
      }

      return rtrim($excerpt, ".,;:- \t") . '…';
   }

   /** Absolute URL from the same base as <base href> (dbx()->get_base_url()). */
   private function seoAbsoluteUrl(string $path): string {
      return rtrim((string)dbx()->get_base_url(), '/') . '/' . ltrim($path, '/');
   }

   /**
    * Liefert die kanonische URL einer Inhaltsseite.
    *
    * Die konfigurierte Startseite ist unter der Basis-URL kanonisch. Ihr
    * interner Permalink bleibt zur Inhaltsauflösung erhalten, erzeugt aber
    * keine zweite indexierbare Startseiten-URL.
    */
   private function seoCanonicalUrl($permalink, bool $isHomePage = false) {
      if ($isHomePage) {
         return rtrim((string)dbx()->get_base_url(), '/') . '/';
      }

      $permalink = trim((string)$permalink);
      if ($permalink !== '' && preg_match('/^https?:\/\//i', $permalink)) {
         return $permalink;
      }
      return $this->seoAbsoluteUrl(dbxContent_permalink::publicPath($permalink));
   }

   private function seoOgImageUrl($db, array $rec) {
      $seo_image_id = (int)($rec['seo_image_id'] ?? 0);
      if ($seo_image_id > 0) {
         $url = $this->seoOgImageFromMediaId($db, $seo_image_id);
         if ($url !== '') return $url;
      }

      $settings = $this->content_settings($db, $rec);
      $hero_id = (int)($settings['hero_image_id'] ?? 0);
      if ($hero_id <= 0) {
         $cid = (int)($rec['id'] ?? 0);
         if ($cid > 0) {
            $usage_rows = $db->select(
               $this->dd_media_usage,
               dbxContentMediaUsageScope::withLanguage('content_id = ' . $cid . ' AND active = 1 AND slot = \'hero\''),
               'media_id',
               'sorter,id',
               'ASC',
               '',
               1,
               0,
               0
            );
            if (is_array($usage_rows) && !empty($usage_rows[0]['media_id'])) {
               $hero_id = (int)$usage_rows[0]['media_id'];
            }
         }
      }
      if ($hero_id <= 0) return '';

      $row = $db->select1($this->dd_media, $hero_id);
      if (!is_array($row) || (int)($row['active'] ?? 0) !== 1 || !$this->media_file_exists($row)) {
         return '';
      }

      $mime = (string)($row['mime'] ?? '');
      $file_name = (string)($row['file_name'] ?? $row['file_path'] ?? '');
      if (strpos($mime, 'image/') !== 0 && !preg_match('/\.(jpg|jpeg|png|gif|webp|svg)$/i', $file_name)) {
         return '';
      }

      return $this->seoAbsoluteUrl('index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid=' . $hero_id);
   }

   private function seoOgImageFromMediaId($db, int $media_id) {
      $media_id = (int)$media_id;
      if ($media_id <= 0) return '';

      $row = $db->select1($this->dd_media, $media_id);
      if (!is_array($row) || (int)($row['active'] ?? 0) !== 1 || !$this->media_file_exists($row)) {
         return '';
      }

      $mime = (string)($row['mime'] ?? '');
      $file_name = (string)($row['file_name'] ?? $row['file_path'] ?? '');
      if (strpos($mime, 'image/') !== 0 && !preg_match('/\.(jpg|jpeg|png|gif|webp|svg)$/i', $file_name)) {
         return '';
      }

      return $this->seoAbsoluteUrl('index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid=' . $media_id);
   }

   private function seoHreflangBlock($db, array $rec, string $currentLng): string {
      $lngUid = trim((string)($rec['lng_uid'] ?? ''));
      if ($lngUid === '') {
         return '';
      }

      $lngs = dbx()->accessible_lngs();
      if (!is_array($lngs) || count($lngs) < 2) {
         return '';
      }

      $alternates = array();
      $escapedUid = str_replace("'", "''", $lngUid);
      $isHomeGroup = dbxContentHome::resolveCid($currentLng) === (int)($rec['id'] ?? 0);
      $masterLng = dbxContentLngSync::masterLng();

      foreach ($lngs as $lng) {
         $lng = strtolower(trim((string)$lng));
         if ($lng === '' || !preg_match('/^[a-z]{2,3}$/', $lng)) continue;

         $permalink = $this->seoHreflangPermalink($db, $lng, $lngUid, $escapedUid, $rec, $currentLng);
         if ($permalink === '') continue;

         $alternates[] = array(
            'lng' => $lng,
            'url' => $this->seoCanonicalUrl(
               $permalink,
               $isHomeGroup && $lng === $masterLng
            ),
         );
      }

      if (count($alternates) < 2) {
         return '';
      }

      $lines = array();
      $defaultLng = strtolower(trim((string)dbx()->get_cfg('dbx', 'default_lng', 'de')));
      if ($defaultLng === '' || $defaultLng === 'undef') $defaultLng = 'de';

      foreach ($alternates as $alt) {
         $lines[] = '<link rel="alternate" hreflang="' . htmlspecialchars((string)$alt['lng'], ENT_QUOTES, 'UTF-8') . '" href="' . htmlspecialchars((string)$alt['url'], ENT_QUOTES, 'UTF-8') . '">';
      }

      foreach ($alternates as $alt) {
         if ((string)$alt['lng'] === $defaultLng) {
            $lines[] = '<link rel="alternate" hreflang="x-default" href="' . htmlspecialchars((string)$alt['url'], ENT_QUOTES, 'UTF-8') . '">';
            break;
         }
      }

      return count($lines) ? "\n    " . implode("\n    ", $lines) : '';
   }

   private function seoHreflangPermalink($db, string $lng, string $lngUid, string $escapedUid, array $rec, string $currentLng): string {
      if ($lng === $currentLng) {
         return trim((string)($rec['permalink'] ?? ''));
      }

      // lng_uid ist die fachliche Geschwister-ID. Eine direkte DD-Abfrage
      // ersetzt das fruehere Durchlaufen des gesamten Permalink-Index samt
      // einer Content-Abfrage pro Kandidat.
      $sibling = $db->select1(
         dbxContentLng::ddContent($lng),
         "lng_uid = '" . $escapedUid . "' AND activ = 1",
         'permalink',
         0
      );
      if (!is_array($sibling)) {
         return '';
      }

      return trim((string)($sibling['permalink'] ?? ''));
   }

   private function seoJsonLd(array $rec, string $title, string $description, string $canonical, string $lng): string {
      if ($title === '' && $description === '' && $canonical === '') {
         return '';
      }

      $data = array(
         '@context' => 'https://schema.org',
         '@type' => 'WebPage',
         'name' => $title,
         'description' => $description,
         'url' => $canonical,
         'inLanguage' => $lng,
      );

      $modified = trim((string)($rec['update_date'] ?? ''));
      if ($modified !== '') {
         $ts = strtotime($modified);
         if ($ts !== false) {
            $data['dateModified'] = gmdate('c', $ts);
         }
      }

      $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      if (!is_string($json) || $json === '') {
         return '';
      }

      return '<script type="application/ld+json">' . $json . '</script>';
   }

   private function cleanup_empty_sections($html) {
      $html = preg_replace('/<section\b[^>]*class="[^"]*\bcms-header\b[^"]*"[^>]*>\s*<\/section>/i', '', (string)$html);
      $html = preg_replace('/<section\b[^>]*class="[^"]*\bgallery\b[^"]*\bno-gallery\b[^"]*"[^>]*>[\s\S]*?<\/section>/i', '', (string)$html);
      $html = preg_replace('/<section\b([^>]*class="[^"]*\bdbx-content-(?:gallery-section|teaser-section|header-text)\b[^"]*"[^>]*)>\s*<\/section>/i', '', (string)$html);
      $html = preg_replace('/<footer\b([^>]*class="[^"]*\bdbx-content-footer\b[^"]*"[^>]*)>\s*<\/footer>/i', '', $html);
      $html = preg_replace('/<div\b([^>]*class="[^"]*\bdbx-content-teaser\b[^"]*"[^>]*)>\s*<\/div>/i', '', $html);
      $html = preg_replace('/<section\b([^>]*class="[^"]*\bdbx-content-hero\b[^"]*"[^>]*)>\s*<\/section>/i', '', $html);
      $html = preg_replace('/<div\b([^>]*class="[^"]*\bhero-content\b[^"]*"[^>]*)>\s*<\/div>/i', '', $html);
      return $html;
   }
}

?>
