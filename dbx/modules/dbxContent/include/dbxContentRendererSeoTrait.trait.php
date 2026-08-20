<?php

namespace dbx\dbxContent;

/** Interne Komponente von dbxContentRenderer. */
trait dbxContentRendererSeoTrait {

public function apply_seo_meta(int $cid, array $rec = null, array $meta = null) {
      $cid = (int)$cid;
      if ($cid <= 0) return;

      $db = dbx()->get_system_obj('dbxDB');
      if (!is_array($rec) || (int)($rec['id'] ?? 0) <= 0) {
         if (is_array($meta) && !empty($meta['seo']) && is_array($meta['seo'])) {
            $rec = array_merge(array('id' => $cid), $meta['seo']);
         } else {
            $rec = $db->select1(dbxContentLng::dd_content(), $cid, '*', 0);
            if (!is_array($rec) || (int)($rec['id'] ?? 0) <= 0) return;
         }
      }

      $page_title = trim((string)($rec['title'] ?? ''));
      $seo_title = trim((string)($rec['seo_title'] ?? ''));
      $display_title = $seo_title !== '' ? $seo_title : $page_title;
      // Der redaktionelle Seitentitel bleibt die sichtbare Überschrift. Der
      // optionale SEO-Titel ist ausschließlich für <title>, OpenGraph und
      // strukturierte Metadaten bestimmt.
      dbx()->set_system_var('dbx_title', $page_title);
      dbx()->set_system_var('dbx_seo_title', $display_title);

      $description = trim((string)($rec['description'] ?? ''));
      if ($description === '') {
         $description = $this->seo_excerpt_from_content((string)($rec['content'] ?? ''));
      }

      $keywords = trim((string)($rec['keywords'] ?? ''));
      $current_lng = strtolower(trim((string)dbx()->get_system_var('dbx_lng', 'de')));
      if ($current_lng === '') $current_lng = 'de';
      $is_home_page = dbxContentHome::resolve_cid($current_lng) === $cid;
      $canonical = $this->seo_canonical_url((string)($rec['permalink'] ?? ''), $is_home_page);
      $activ = (int)($rec['activ'] ?? 1);
      $meta_robots = trim((string)($rec['meta_robots'] ?? ''));
      if ($meta_robots === '') {
         $meta_robots = ($activ === 0) ? 'noindex,nofollow' : 'index,follow';
      } elseif ($activ === 0 && stripos($meta_robots, 'noindex') === false) {
         $meta_robots = 'noindex,nofollow';
      }

      $og_title = $display_title;
      $og_image = $this->seo_og_image_url($db, $rec);
      dbx()->set_system_var('dbx_meta_description', $description);
      dbx()->set_system_var('dbx_meta_keywords', $keywords);
      dbx()->set_system_var('dbx_canonical', $canonical);
      dbx()->set_system_var('dbx_robots', $meta_robots);
      dbx()->set_system_var('dbx_og_title', $og_title);
      dbx()->set_system_var('dbx_og_description', $description);
      dbx()->set_system_var('dbx_og_url', $canonical);
      dbx()->set_system_var('dbx_og_image', $og_image);
      dbx()->set_system_var('dbx_hreflang', $this->seo_hreflang_block($db, $rec, $current_lng));
      dbx()->set_system_var('dbx_json_ld', $this->seo_json_ld($rec, $display_title, $description, $canonical, $current_lng));
   }

public static function seo_meta_from_record(array $rec): array {
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

public static function reset_seo_meta() {
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

private function seo_excerpt_from_content($html, $max = 160) {
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

private function seo_absolute_url(string $path): string {
      return rtrim((string)dbx()->get_base_url(), '/') . '/' . ltrim($path, '/');
   }

private function seo_canonical_url($permalink, bool $is_home_page = false, string $language = '') {
      $language = strtolower(trim($language !== ''
         ? $language
         : (string)dbx()->get_system_var('dbx_lng', 'de')));
      $default_language = strtolower(trim((string)dbx()->get_cfg('dbx', 'default_lng', 'de')));
      $use_language_path = (int)dbx()->get_cfg('dbx', 'language_path_prefix', 0) === 1
         && $language !== ''
         && $language !== $default_language;
      $language_prefix = $use_language_path ? $language . '/' : '';

      if ($is_home_page) {
         return rtrim((string)dbx()->get_base_url(), '/') . '/' . $language_prefix;
      }

      $permalink = trim((string)$permalink);
      if ($permalink !== '' && preg_match('/^https?:\/\//i', $permalink)) {
         return $permalink;
      }
      return $this->seo_absolute_url($language_prefix . dbxContent_permalink::public_path($permalink));
   }

private function seo_og_image_url($db, array $rec) {
      $seo_image_id = (int)($rec['seo_image_id'] ?? 0);
      if ($seo_image_id > 0) {
         $url = $this->seo_og_image_from_media_id($db, $seo_image_id);
         if ($url !== '') return $url;
      }

      $settings = $this->content_settings($db, $rec);
      $hero_id = (int)($settings['hero_image_id'] ?? 0);
      if ($hero_id <= 0) {
         $cid = (int)($rec['id'] ?? 0);
         if ($cid > 0) {
            $usage_rows = $db->select(
               $this->dd_media_usage,
               dbxContentMediaUsageScope::with_language('content_id = ' . $cid . ' AND active = 1 AND slot = \'hero\''),
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

      return $this->seo_absolute_url('index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid=' . $hero_id);
   }

private function seo_og_image_from_media_id($db, int $media_id) {
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

      return $this->seo_absolute_url('index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid=' . $media_id);
   }

private function seo_hreflang_block($db, array $rec, string $current_lng): string {
      $lng_uid = trim((string)($rec['lng_uid'] ?? ''));
      if ($lng_uid === '') {
         return '';
      }

      $lngs = dbx()->accessible_lngs();
      if (!is_array($lngs) || count($lngs) < 2) {
         return '';
      }

      $alternates = array();
      $escaped_uid = str_replace("'", "''", $lng_uid);
      $is_home_group = dbxContentHome::resolve_cid($current_lng) === (int)($rec['id'] ?? 0);
      foreach ($lngs as $lng) {
         $lng = strtolower(trim((string)$lng));
         if ($lng === '' || !preg_match('/^[a-z]{2,3}$/', $lng)) continue;

         $permalink = $this->seo_hreflang_permalink($db, $lng, $lng_uid, $escaped_uid, $rec, $current_lng);
         if ($permalink === '') continue;

         $alternates[] = array(
            'lng' => $lng,
            'url' => $this->seo_canonical_url(
               $permalink,
               $is_home_group,
               $lng
            ),
         );
      }

      if (count($alternates) < 2) {
         return '';
      }

      $lines = array();
      $default_lng = strtolower(trim((string)dbx()->get_cfg('dbx', 'default_lng', 'de')));
      if ($default_lng === '' || $default_lng === 'undef') $default_lng = 'de';

      foreach ($alternates as $alt) {
         $lines[] = '<link rel="alternate" hreflang="' . htmlspecialchars((string)$alt['lng'], ENT_QUOTES, 'UTF-8') . '" href="' . htmlspecialchars((string)$alt['url'], ENT_QUOTES, 'UTF-8') . '">';
      }

      foreach ($alternates as $alt) {
         if ((string)$alt['lng'] === $default_lng) {
            $lines[] = '<link rel="alternate" hreflang="x-default" href="' . htmlspecialchars((string)$alt['url'], ENT_QUOTES, 'UTF-8') . '">';
            break;
         }
      }

      return count($lines) ? "\n    " . implode("\n    ", $lines) : '';
   }

private function seo_hreflang_permalink($db, string $lng, string $lng_uid, string $escaped_uid, array $rec, string $current_lng): string {
      if ($lng === $current_lng) {
         return trim((string)($rec['permalink'] ?? ''));
      }

      // lng_uid ist die fachliche Geschwister-ID. Eine direkte DD-Abfrage
      // ersetzt das fruehere Durchlaufen des gesamten Permalink-Index samt
      // einer Content-Abfrage pro Kandidat.
      $sibling = $db->select1(
         dbxContentLng::dd_content($lng),
         "lng_uid = '" . $escaped_uid . "' AND activ = 1",
         'permalink',
         0
      );
      if (!is_array($sibling)) {
         return '';
      }

      return trim((string)($sibling['permalink'] ?? ''));
   }

private function seo_json_ld(array $rec, string $title, string $description, string $canonical, string $lng): string {
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
}
