<?php
namespace dbx\dbxContent;

require_once __DIR__ . '/dbxContentPageCache.class.php';
require_once __DIR__ . '/dbxContentPermalinkIndex.class.php';
require_once __DIR__ . '/dbxContentRenderer.class.php';
require_once __DIR__ . '/dbxContentHome.class.php';

/**
 * Erzeugt und cached sitemap.xml sowie robots.txt für öffentliche Content-Seiten.
 */
class dbxContentSitemap {

   private const CACHE_TTL_SECONDS = 900;
   private const ROBOTS_CACHE_TTL_SECONDS = 3600;

   public static function cache_path(): string {
      return dbxContentPageCache::base_dir() . 'meta/sitemap.xml';
   }

   public static function invalidate(): void {
      @unlink(self::cache_path());
   }

   public static function serve(): void {
      if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
         session_write_close();
      }

      $refresh = (int) dbx()->get_request_var('refresh', 0, 'int') === 1;
      $xml = $refresh ? null : self::read_cache();
      if ($xml === null) {
         $xml = self::build();
         self::write_cache($xml);
      }

      header('Content-Type: application/xml; charset=UTF-8');
      header('X-Content-Type-Options: nosniff');
      if (self::send_public_cache_headers(
         $xml,
         self::CACHE_TTL_SECONDS,
         (int)@filemtime(self::cache_path())
      )) {
         exit;
      }
      echo $xml;
      exit;
   }

   public static function rebuild(): array {
      self::invalidate();
      $xml = self::build();
      self::write_cache($xml);

      return self::stats_from_xml($xml);
   }

   public static function stats(): array {
      $path = self::cache_path();
      if (!is_file($path) || !is_readable($path)) {
         return array(
            'exists' => false,
            'urls' => 0,
            'size' => 0,
            'generated_at' => '',
            'path' => $path,
         );
      }

      $xml = file_get_contents($path);
      $stats = self::stats_from_xml(is_string($xml) ? $xml : '');
      $stats['exists'] = true;
      $stats['size'] = (int) @filesize($path);
      $stats['generated_at'] = date('d.m.Y H:i:s', (int) @filemtime($path));
      $stats['path'] = $path;
      return $stats;
   }

   public static function serve_robots(): void {
      if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
         session_write_close();
      }

      $base = rtrim((string) dbx()->get_base_url(), '/') . '/';
      $robots = "User-agent: *\nAllow: /\n\nSitemap: " . $base . "sitemap.xml\n";
      header('Content-Type: text/plain; charset=UTF-8');
      header('X-Content-Type-Options: nosniff');
      if (self::send_public_cache_headers($robots, self::ROBOTS_CACHE_TTL_SECONDS)) {
         exit;
      }
      echo $robots;
      exit;
   }

   /**
    * Liefert die öffentlichen Sitemap-Seiten einer Sprache für die sichtbare
    * Navigationsseite. Die Auswahl entspricht den Regeln von sitemap.xml.
    *
    * @return array<int,array{title:string,permalink:string,url:string}>
    */
   public static function navigation_pages(string $lng): array {
      $lng = strtolower(trim($lng));
      if (!in_array($lng, dbxContentLngSync::accessible_lngs(), true)) {
         $lng = dbxContentLngSync::master_lng();
      }

      $db = dbx()->get_system_obj('dbxDB');
      $renderer = new dbxContentRenderer();
      $base = rtrim((string)dbx()->get_base_url(), '/') . '/';
      $master_lng = dbxContentLngSync::master_lng();
      $use_language_paths = (int)dbx()->get_cfg(
         'dbx',
         'language_path_prefix',
         0
      ) === 1;
      $language_prefix = $use_language_paths && $lng !== $master_lng
         ? rawurlencode($lng) . '/'
         : '';
      $pages = array();

      foreach (self::collect_public_pages($db, $renderer, $lng) as $page) {
         $permalink = dbxContent_permalink::public_path(
            (string)($page['permalink'] ?? '')
         );
         if ($permalink === '') {
            continue;
         }
         $title = trim((string)($page['title'] ?? ''));
         $pages[] = array(
            'title' => $title !== '' ? $title : $permalink,
            'permalink' => $permalink,
            'url' => $base . $language_prefix . $permalink,
         );
      }

      usort($pages, static function (array $left, array $right): int {
         return strnatcasecmp(
            (string)($left['title'] ?? ''),
            (string)($right['title'] ?? '')
         );
      });

      return $pages;
   }

   /**
    * Sendet cachebare Header und beantwortet gueltige Validatoren mit 304.
    *
    * @return bool true, wenn die Antwort bereits als 304 abgeschlossen ist.
    */
   private static function send_public_cache_headers(
      string $content,
      int $max_age,
      int $last_modified = 0
   ): bool {
      $max_age = max(0, $max_age);
      $etag = '"' . hash('sha256', $content) . '"';

      header_remove('Pragma');
      header_remove('Expires');
      header('Cache-Control: public, max-age=' . $max_age);
      header('ETag: ' . $etag);
      if ($last_modified > 0) {
         header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $last_modified) . ' GMT');
      }

      $if_none_match = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
      if ($if_none_match !== '' && $if_none_match === $etag) {
         http_response_code(304);
         return true;
      }

      if ($if_none_match === '' && $last_modified > 0) {
         $if_modified_since = strtotime((string)($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? ''));
         if ($if_modified_since !== false && $if_modified_since >= $last_modified) {
            http_response_code(304);
            return true;
         }
      }

      return false;
   }

   private static function read_cache(): ?string {
      $path = self::cache_path();
      if (!is_file($path) || !is_readable($path)) {
         return null;
      }
      $mtime = (int) @filemtime($path);
      if ($mtime <= 0 || time() - $mtime > self::CACHE_TTL_SECONDS) {
         return null;
      }

      $xml = file_get_contents($path);
      if (!is_string($xml) || trim($xml) === '') {
         return null;
      }

      // Cache aus Versionen vor der SEO-Bereinigung nicht weiter ausliefern.
      if (strpos($xml, '?dbx_') !== false
         || preg_match('#<loc>[^<]+/home/?</loc>#i', $xml)) {
         return null;
      }

      return $xml;
   }

   private static function write_cache(string $xml): void {
      if (trim($xml) === '') {
         return;
      }

      dbxContentPageCache::ensure_dirs();
      @file_put_contents(self::cache_path(), $xml, LOCK_EX);
   }

   private static function build(): string {
      $db = dbx()->get_system_obj('dbxDB');
      $base = rtrim((string) dbx()->get_base_url(), '/') . '/';
      $renderer = new dbxContentRenderer();
      $entries = array();
      $master_lng = dbxContentLngSync::master_lng();
      $use_language_paths = (int)dbx()->get_cfg('dbx', 'language_path_prefix', 0) === 1;
      // Ohne Sprachpfade darf jede flache URL nur einmal erscheinen. Sobald
      // die Installation stabile /en/-, /es/-...-Pfade aktiviert, gehören
      // dagegen alle erreichbaren Sprachen mit ihren kanonischen URLs hinein.
      $lngs = $use_language_paths ? dbxContentLngSync::accessible_lngs() : array($master_lng);

      foreach ($lngs as $lng) {
         $lng = strtolower(trim((string) $lng));
         if ($lng === '') {
            continue;
         }
         foreach (self::collect_public_pages($db, $renderer, $lng) as $page) {
            $permalink = dbxContent_permalink::public_path((string)($page['permalink'] ?? ''));
            if ($permalink === '') {
               continue;
            }

            $is_home = (int)($page['cid'] ?? 0) === dbxContentHome::resolve_cid($lng);
            $language_prefix = $use_language_paths && $lng !== $master_lng ? $lng . '/' : '';
            $loc = $is_home ? $base . $language_prefix : $base . $language_prefix . $permalink;
            $key = strtolower($loc);
            if (!isset($entries[$key])) {
               $entries[$key] = array(
                  'loc' => $loc,
                  'lastmod' => (string) ($page['lastmod'] ?? ''),
               );
            } elseif (($page['lastmod'] ?? '') > ($entries[$key]['lastmod'] ?? '')) {
               $entries[$key]['lastmod'] = (string) ($page['lastmod'] ?? '');
            }
         }
      }

      ksort($entries);

      $lines = array('<?xml version="1.0" encoding="UTF-8"?>');
      $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

      foreach ($entries as $entry) {
         $lines[] = '  <url>';
         $lines[] = '    <loc>' . self::xml_esc((string) $entry['loc']) . '</loc>';
         $lastmod = trim((string) ($entry['lastmod'] ?? ''));
         if ($lastmod !== '') {
            $lines[] = '    <lastmod>' . self::xml_esc($lastmod) . '</lastmod>';
         }
         $lines[] = '  </url>';
      }

      $lines[] = '</urlset>';
      return implode("\n", $lines) . "\n";
   }

   private static function collect_public_pages($db, dbxContentRenderer $renderer, string $lng): array {
      $pages = array();
      $seen = array();

      if (is_object($db)) {
         $rows = $db->select(
            dbxContentLng::dd_content($lng),
            'activ = 1',
            'id,title,permalink,folder,update_date,meta_robots',
            'id',
            'ASC',
            '',
            0,
            0,
            0
         );
         if (is_array($rows)) {
            foreach ($rows as $row) {
               if (!is_array($row)) {
                  continue;
               }
               $cid = (int) ($row['id'] ?? 0);
               $permalink = trim((string) ($row['permalink'] ?? ''), '/');
               if ($cid <= 0 || $permalink === '' || isset($seen[$cid])) {
                  continue;
               }
               $seen[$cid] = 1;
               if (self::is_noindex((string) ($row['meta_robots'] ?? ''))) {
                  continue;
               }
               if (!self::is_public_rights($renderer->get_public_folder_rights((int) ($row['folder'] ?? 0)))) {
                  continue;
               }

               $pages[] = array(
                  'cid' => $cid,
                  'title' => trim((string)($row['title'] ?? '')),
                  'permalink' => $permalink,
                  'lastmod' => self::format_lastmod((string) ($row['update_date'] ?? '')),
               );
            }
         }
      }

      if (dbxContentPageCache::is_config_enabled()) {
         $index = dbxContentPermalinkIndex::load($lng);
         foreach ($index as $permalink => $row) {
            if (!is_array($row)) {
               continue;
            }
            if ((int) ($row['activ'] ?? 0) !== 1) {
               continue;
            }
            if (self::is_noindex((string) ($row['meta_robots'] ?? ''))) {
               continue;
            }
            if (!self::is_public_rights((string) ($row['rights'] ?? '*'))) {
               continue;
            }

            $cid = (int) ($row['cid'] ?? 0);
            $permalink = trim((string) $permalink, '/');
            if ($permalink === '' || $cid <= 0 || isset($seen[$cid])) {
               continue;
            }

            $seen[$cid] = 1;
            $pages[] = array(
               'cid' => $cid,
               'title' => trim((string)($row['title'] ?? '')),
               'permalink' => $permalink,
               'lastmod' => self::lastmod_for_cid($cid),
            );
         }
      }

      return $pages;
   }

   private static function stats_from_xml(string $xml): array {
      return array(
         'exists' => trim($xml) !== '',
         'urls' => substr_count($xml, '<url>'),
         'size' => strlen($xml),
         'generated_at' => date('d.m.Y H:i:s'),
         'path' => self::cache_path(),
      );
   }

   private static function is_public_rights(string $rights): bool {
      $rights = trim($rights);
      return $rights === '' || $rights === '*';
   }

   private static function is_noindex(string $robots): bool {
      return in_array('noindex', array_map('trim', explode(',', strtolower($robots))), true);
   }

   private static function lastmod_for_cid(int $cid): string {
      $meta = dbxContentPageCache::read_page_meta($cid);
      if (is_array($meta)) {
         $saved = trim((string) ($meta['saved_at'] ?? ''));
         if ($saved !== '') {
            return self::format_lastmod($saved);
         }
         $seo = is_array($meta['seo'] ?? null) ? $meta['seo'] : array();
         $update = trim((string) ($seo['update_date'] ?? ''));
         if ($update !== '') {
            return self::format_lastmod($update);
         }
      }
      return '';
   }

   private static function format_lastmod(string $value): string {
      $value = trim($value);
      if ($value === '') {
         return '';
      }

      $ts = strtotime($value);
      if ($ts === false) {
         return '';
      }

      return gmdate('Y-m-d', $ts);
   }

   private static function xml_esc(string $value): string {
      return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
   }
}

?>
