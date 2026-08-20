<?php
namespace dbx\dbxKi;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;
use dbx\dbxContent\dbxContentHome;
use dbx\dbxContent\dbxContentPageCache;
use dbx\dbxContent\dbxContentPermalinkIndex;
use dbx\dbxContent\dbxContentRenderer;
use dbx\dbxContent\dbxContentTranslate;
use dbx\dbxContent\dbxContent_permalink;

trait dbxKiCmsDataServiceTrait {

   private function folder_data(array $params, int $parent, string $name): array {
      return array(
         'name' => $name,
         'parent_id' => $parent,
         'group_read' => $this->clean($params['group_read'] ?? ($parent > 0 ? 'parent' : '*'), 512),
         'template' => $this->clean($params['template'] ?? ($parent > 0 ? 'parent' : 'c-content'), 254),
         'hero_template' => $this->clean($params['hero_template'] ?? ($parent > 0 ? 'parent' : 'image-hero'), 80),
         'hero_image_id' => $this->clean($params['hero_image_id'] ?? 'parent', 32),
         'hero_margin_top' => $this->clean($params['hero_margin_top'] ?? 'parent', 32),
         'hero_height' => $this->clean($params['hero_height'] ?? ($parent > 0 ? 'parent' : '300px'), 32),
         'hero_variant' => $this->clean($params['hero_variant'] ?? 'parent', 32),
         'hero_sticky' => $this->clean($params['hero_sticky'] ?? 'parent', 32),
         'hero_scroll_layer' => $this->clean($params['hero_scroll_layer'] ?? 'parent', 32),
      );
   }

   private function page_data(array $params, string $lng, int $folder, string $title): array {
      $permalink = trim($this->clean($params['permalink'] ?? '', 254));
      if ($permalink === '') {
         $permalink = dbxContent_permalink::build($this->db, dbxContentLng::dd_folder($lng), $folder, $title);
      } else {
         if (!dbxContent_permalink::is_valid($permalink)) {
            throw new \InvalidArgumentException('permalink darf nur Kleinbuchstaben, Zahlen und einzelne Bindestriche enthalten.');
         }
         if (dbxContent_permalink::exists($this->db, dbxContentLng::dd_content($lng), $permalink)) {
            throw new \InvalidArgumentException('permalink wird bereits von einer anderen Seite verwendet.');
         }
      }
      return array(
         'activ' => $this->bool_value($params['activ'] ?? true) ? 1 : 0,
          'folder' => $folder,
          'title' => $title,
          'menu_title' => $this->clean($params['menu_title'] ?? '', 96),
          'seo_title' => $this->clean($params['seo_title'] ?? $title, 254),
          'permalink' => $permalink,
         'description' => $this->clean($params['description'] ?? '', 254),
         'keywords' => $this->clean($params['keywords'] ?? '', 254),
         'group_read' => $this->clean($params['group_read'] ?? 'parent', 512),
         'template' => $this->clean($params['template'] ?? 'parent', 254),
         'hero_template' => $this->clean($params['hero_template'] ?? 'parent', 80),
         'hero_image_id' => $this->clean($params['hero_image_id'] ?? 'parent', 32),
         'hero_margin_top' => $this->clean($params['hero_margin_top'] ?? 'parent', 32),
         'hero_height' => $this->clean($params['hero_height'] ?? '300px', 32),
         'hero_variant' => $this->clean($params['hero_variant'] ?? 'parent', 32),
         'hero_sticky' => $this->clean($params['hero_sticky'] ?? 'parent', 32),
         'hero_scroll_layer' => $this->clean($params['hero_scroll_layer'] ?? 'parent', 32),
         'gallery_template' => $this->clean($params['gallery_template'] ?? 'image-gallery', 80),
         'gallery_visible_count' => $this->clean($params['gallery_visible_count'] ?? '3', 32),
         'gallery_image_size' => $this->clean($params['gallery_image_size'] ?? 'original', 32),
         'gallery_lightbox_width' => $this->clean($params['gallery_lightbox_width'] ?? '100vw', 32),
         'gallery_overflow' => $this->clean($params['gallery_overflow'] ?? 'grid', 32),
         'gallery_click_behavior' => $this->clean($params['gallery_click_behavior'] ?? 'lightbox', 32),
         'sorter' => $this->clean($params['sorter'] ?? '', 32),
          'content' => $this->normalize_content_inline_media_urls((string)($params['content'] ?? '')),
       );
    }

   /**
    * Verhindert einen im Content nachgebauten Hero.
    *
    * Ein Bild mit umfangreicher absoluter Textebene im ersten Inhaltsblock
    * muss die vorhandene CMS-Hero-Logik verwenden. Kleine Badges auf Karten
    * bleiben erlaubt.
    */
   private function assert_no_fake_inline_hero(string $html): void {
      if (stripos($html, '<img') === false || stripos($html, 'position') === false) {
         return;
      }

      $doc = new \DOMDocument('1.0', 'UTF-8');
      $previous = libxml_use_internal_errors(true);
      try {
         $loaded = $doc->loadHTML(
            '<div data-dbx-ki-content-root="1">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
         );
      } finally {
         libxml_clear_errors();
         libxml_use_internal_errors($previous);
      }
      if (!$loaded) {
         return;
      }

      $xpath = new \DOMXPath($doc);
      $roots = $xpath->query('//*[@data-dbx-ki-content-root="1"]');
      $root = $roots instanceof \DOMNodeList ? $roots->item(0) : null;
      if (!$root instanceof \DOMElement) {
         return;
      }
      $first = null;
      foreach ($root->childNodes as $child) {
         if ($child instanceof \DOMElement) {
            $first = $child;
            break;
         }
      }
      if (!$first instanceof \DOMElement) {
         return;
      }

      $images = $first->getElementsByTagName('img');
      foreach ($images as $image) {
         $host = $image->parentNode;
         while ($host instanceof \DOMElement && $host !== $root) {
            $class = ' ' . strtolower($host->getAttribute('class')) . ' ';
            $style = strtolower($host->getAttribute('style'));
            $relative = str_contains($class, ' position-relative ')
               || preg_match('/position\s*:\s*relative/i', $style) === 1;
            if ($relative) {
               foreach ($host->getElementsByTagName('*') as $candidate) {
                  if ($candidate === $image || !$candidate instanceof \DOMElement) {
                     continue;
                  }
                  $candidate_class = ' ' . strtolower($candidate->getAttribute('class')) . ' ';
                  $candidate_style = strtolower($candidate->getAttribute('style'));
                  $absolute = str_contains($candidate_class, ' position-absolute ')
                     || preg_match('/position\s*:\s*absolute/i', $candidate_style) === 1;
                  $text = trim(preg_replace('/\s+/u', ' ', $candidate->textContent ?? '') ?? '');
                  $structured_text = $candidate->getElementsByTagName('h1')->length
                     + $candidate->getElementsByTagName('h2')->length
                     + $candidate->getElementsByTagName('p')->length
                     + $candidate->getElementsByTagName('a')->length;
                  if ($absolute && (mb_strlen($text) >= 80 || $structured_text >= 2)) {
                     throw new \InvalidArgumentException(
                        'dbxKi: Ein Bild mit ueberlagertem Text am Seitenanfang ist ein CMS-Hero. '
                        . 'Hero-Bild ueber hero_image_id/media.assign slot=hero setzen und den Hero-Text '
                        . 'vor den dbx:hero-Marker schreiben; kein Inline-Schein-Hero.'
                     );
                  }
               }
            }
            if ($host === $first) {
               break;
            }
            $host = $host->parentNode;
         }
      }
   }

   private function patch(array $params): array {
      $patch = is_array($params['patch'] ?? null) ? $params['patch'] : $params;
      foreach (array('id', 'lng', 'patch', 'folder_id') as $key) {
         if ($key !== 'folder_id') unset($patch[$key]);
      }
      return $patch;
   }

   private function whitelist(array $data, array $allowed): array {
      return array_intersect_key($data, array_flip($allowed));
   }

   private function lng_fields(string $prefix, string $lng): array {
      return array(
         'lng_uid' => dbxContentLngSync::new_uid($prefix),
         'lng_sync' => $lng === dbxContentLngSync::master_lng() ? 'auto' : 'manual',
         'lng_rev' => 1,
         'lng_synced_rev' => 0,
      );
   }

   private function advance_revision(string $dd, int $id, array $data, string $lng): array {
      $row = $this->db->select1($dd, $id, 'lng_uid,lng_rev', 0);
      $uid = trim((string)($row['lng_uid'] ?? ''));
      if ($uid === '') $uid = dbxContentLngSync::new_uid(strpos($dd, 'folder') !== false ? 'f' : 'p');
      $data['lng_uid'] = $uid;
      $data['lng_rev'] = max(1, (int)($row['lng_rev'] ?? 0)) + 1;
      if ($lng !== dbxContentLngSync::master_lng()) $data['lng_sync'] = 'manual';
      return $data;
   }

   private function next_sorter(string $dd, string $field, int $parent): string {
      $rows = $this->db->select($dd, $field . ' = ' . $parent, 'sorter,id', 'sorter DESC,id DESC', 'ASC', '', 1, 0, 0);
      $max = is_array($rows) && isset($rows[0]) ? (int)($rows[0]['sorter'] ?? 0) : 0;
      return sprintf('%04d', $max + 10);
   }

   private function next_usage_sorter(int $content, int $folder, string $slot, string $lng = ''): string {
      $where = "active = 1 AND slot = '" . str_replace("'", "''", $slot) . "'";
      if ($content > 0) $where .= ' AND content_id = ' . $content;
      if ($folder > 0) $where .= ' AND folder_id = ' . $folder;
      $where = dbxContentMediaUsageScope::with_language($where, $lng);
      $rows = $this->db->select('dbxMediaUsage', $where, 'sorter,id', 'sorter DESC,id DESC', 'ASC', '', 1, 0, 0);
      $max = is_array($rows) && isset($rows[0]) ? (int)($rows[0]['sorter'] ?? 0) : 0;
      return sprintf('%04d', $max + 10);
   }

   private function folder_descendant(string $dd, int $candidate, int $ancestor): bool {
      $seen = array();
      while ($candidate > 0 && !isset($seen[$candidate])) {
         if ($candidate === $ancestor) return true;
         $seen[$candidate] = 1;
         $row = $this->db->select1($dd, $candidate, 'parent_id', 0);
         if (!is_array($row)) break;
         $candidate = (int)($row['parent_id'] ?? 0);
      }
      return false;
   }

   private function slot($value): string {
      $slot = strtolower(trim((string)$value));
      $allowed = array('hero', 'gallery', 'inline', 'header', 'teaser', 'footer');
      if (!in_array($slot, $allowed, true)) throw new \InvalidArgumentException('Ungültiger Medienslot: ' . $slot);
      return $slot;
   }
}
