<?php

namespace dbx\dbxContent;

/** Interne Komponente von dbxContentRenderer. */
trait dbxContentRendererPageTrait {

private function folder_row($db, int $folder_id): array {
      if ($folder_id <= 0) return array();
      $key = dbxContentLng::dd_folder() . ':' . $folder_id;
      if (!array_key_exists($key, $this->folder_row_cache)) {
         $row = $db->select1(dbxContentLng::dd_folder(), $folder_id, '*', 0);
         $this->folder_row_cache[$key] = is_array($row) ? $row : array();
      }
      return $this->folder_row_cache[$key];
   }

public static function optimize_content_page_images(string $html): string {
      $priority_assigned = false;
      $result = preg_replace_callback('/<img\b[^>]*>/i', static function(array $match) use (&$priority_assigned): string {
         $tag = (string)($match[0] ?? '');
         if ($tag === '') return $tag;

         if (!$priority_assigned) {
            $tag = self::with_html_image_attr($tag, 'loading', 'eager', true);
            $tag = self::with_html_image_attr($tag, 'fetchpriority', 'high', true);
            $priority_assigned = true;
         } else {
            // Pro Content-Seite genau ein moegliches LCP-Bild priorisieren.
            // Auch versehentlich gespeicherte eager-Attribute werden bei allen
            // nachfolgenden Bildern auf lazy/low normalisiert.
            $tag = self::with_html_image_attr($tag, 'loading', 'lazy', true);
            $tag = self::with_html_image_attr($tag, 'fetchpriority', 'low', true);
         }

         return self::with_html_image_attr($tag, 'decoding', 'async');
      }, $html);

      return is_string($result) ? $result : $html;
   }

private static function with_html_image_attr(string $tag, string $name, string $value, bool $replace = false): string {
      $attr_pattern = '/\s' . preg_quote($name, '/') . '\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i';
      $attribute = ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
      if (preg_match($attr_pattern, $tag)) {
         if (!$replace) return $tag;
         $updated = preg_replace($attr_pattern, $attribute, $tag, 1);
         return is_string($updated) ? $updated : $tag;
      }

      $closing = str_ends_with(rtrim($tag), '/>') ? '/>' : '>';
      $trimmed = rtrim($tag);
      return substr($trimmed, 0, -strlen($closing)) . $attribute . $closing;
   }

public function render($cid) {
      return $this->interpret_content_modules($this->render_static($cid));
   }

public function render_not_found(): string {
      $tpl = dbx()->get_system_obj('dbxTPL');
      $permalink = trim((string)dbx()->get_system_var('dbx_permalink', ''), '/');
      return $tpl->get_tpl('dbxContent|no-page', array(
         'permalink' => dbx()->esc($permalink !== '' ? $permalink : '/'),
      ));
   }

public function render_static($cid, array $options = array()) {
      $cid = (int)$cid;
      if ($cid <= 0) return 'Keine dbx_cid gesetzt!';

      $db = dbx()->get_system_obj('dbxDB');
      $tpl = dbx()->get_system_obj('dbxTPL');
      $rec = $db->select1(dbxContentLng::dd_content(), $cid, '*', 0);
      if (!is_array($rec) || (int)($rec['id'] ?? 0) <= 0) {
         return $this->render_not_found();
      }

      $rights = $this->resolve_content_rights($db, $rec);
      if (!dbx()->has_group($rights)) {
         if (empty($options['admin_help']) || !dbx()->has_group('admin')) {
            return '<div class="alert alert-warning" role="alert">Sie haben keinen Zugriff auf diese Seite.</div>';
         }
      }

      if (empty($options['skip_hits'])) {
         $this->update_hits($db, $rec);
      }

      $force_template = trim((string)($options['template'] ?? ''));
      if ($force_template !== '') {
         $template = $this->normalize_content_template($force_template);
      } else {
         $template = $this->normalize_content_template($this->resolve_content_setting($db, $rec, 'template', 'template', 'c-content'));
      }

      $this->apply_seo_meta($cid, $rec);

      $template_html = $tpl->get_tpl('dbxContent|' . $template);
      $slots = $this->detect_template_slots($template_html);
      $content_html = $this->convert_mod_placeholders((string)($rec['content'] ?? ''));
      $content_html = $this->remove_redundant_document_heading(
         $content_html,
         (string)($rec['title'] ?? '')
      );
      $parsed = $this->parse_content($content_html, $slots);
      $parsed = $this->render_inline_media_placeholders($db, $parsed);
      $cms_cols = max(1, min(3, (int)($slots['cols'] ?? 1)));
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
      // Platzhalter können erst während der finalen Templateauflösung eigene
      // Überschriften liefern. Die Seitentitel-Invariante gilt deshalb auch
      // für die vollständig zusammengesetzte Ausgabe.
      $content_html = $this->remove_redundant_document_heading(
         $content_html,
         (string)($rec['title'] ?? '')
      );
      return $content_html;
   }

private function remove_redundant_document_heading(string $html, string $page_title): string {
      $page_title = $this->normalized_heading_text($page_title);
      if ($html === '' || $page_title === '') {
         return $html;
      }

      if (preg_match('/<h([12])\b[^>]*>[\s\S]*?<\/h\1>/iu', $html, $match, PREG_OFFSET_CAPTURE) !== 1) {
         return $html;
      }

      $heading_html = (string)$match[0][0];
      $heading_offset = (int)$match[0][1];
      $heading_text = $this->normalized_heading_text(
         html_entity_decode(strip_tags($heading_html), ENT_QUOTES | ENT_HTML5, 'UTF-8')
      );
      if ($heading_text !== $page_title) {
         return $html;
      }

      return substr($html, 0, $heading_offset)
         . substr($html, $heading_offset + strlen($heading_html));
   }

private function normalized_heading_text(string $text): string {
      $text = mb_strtolower(trim($text), 'UTF-8');
      return trim((string)preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text));
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

public function interpret_content_modules($html) {
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

public function get_public_folder_rights(int $folder_id): string {
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
      $db->update(dbxContentLng::dd_content(), array('hits' => $hits + 1), (int)$rec['id'], 0, 1, 1, 0);
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
}
