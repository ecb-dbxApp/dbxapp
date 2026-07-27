<?php
namespace dbx\dbxContent;

require_once __DIR__ . '/dbxContentPageCache.class.php';
require_once __DIR__ . '/dbxContentPermalinkIndex.class.php';
require_once __DIR__ . '/dbxContentRenderer.class.php';

class dbxContentSitemap {

   private const CACHE_TTL_SECONDS = 900;

   public static function cachePath(): string {
      return dbxContentPageCache::baseDir() . 'meta/sitemap.xml';
   }

   public static function invalidate(): void {
      @unlink(self::cachePath());
   }

   public static function serve(): void {
      if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
         session_write_close();
      }

      $refresh = (int) dbx()->get_request_var('refresh', 0, 'int') === 1;
      $xml = $refresh ? null : self::readCache();
      if ($xml === null) {
         $xml = self::build();
         self::writeCache($xml);
      }

      header('Content-Type: application/xml; charset=UTF-8');
      header('X-Content-Type-Options: nosniff');
      echo $xml;
      exit;
   }

   public static function rebuild(): array {
      self::invalidate();
      $xml = self::build();
      self::writeCache($xml);

      return self::statsFromXml($xml);
   }

   public static function stats(): array {
      $path = self::cachePath();
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
      $stats = self::statsFromXml(is_string($xml) ? $xml : '');
      $stats['exists'] = true;
      $stats['size'] = (int) @filesize($path);
      $stats['generated_at'] = date('d.m.Y H:i:s', (int) @filemtime($path));
      $stats['path'] = $path;
      return $stats;
   }

   public static function serveRobots(): void {
      if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
         session_write_close();
      }

      $base = rtrim((string) dbx()->get_base_url(), '/') . '/';
      header('Content-Type: text/plain; charset=UTF-8');
      header('X-Content-Type-Options: nosniff');
      echo "User-agent: *\nAllow: /\n\nSitemap: " . $base . "sitemap.xml\n";
      exit;
   }

   private static function readCache(): ?string {
      $path = self::cachePath();
      if (!is_file($path) || !is_readable($path)) {
         return null;
      }
      $mtime = (int) @filemtime($path);
      if ($mtime <= 0 || time() - $mtime > self::CACHE_TTL_SECONDS) {
         return null;
      }

      $xml = file_get_contents($path);
      return (is_string($xml) && trim($xml) !== '') ? $xml : null;
   }

   private static function writeCache(string $xml): void {
      if (trim($xml) === '') {
         return;
      }

      dbxContentPageCache::ensureDirs();
      @file_put_contents(self::cachePath(), $xml, LOCK_EX);
   }

   private static function build(): string {
      $db = dbx()->get_system_obj('dbxDB');
      $base = rtrim((string) dbx()->get_base_url(), '/') . '/';
      $lngs = function_exists('dbx_accessible_lngs') ? dbx_accessible_lngs() : array('de');
      $renderer = new dbxContentRenderer();
      $entries = array();

      foreach ($lngs as $lng) {
         $lng = strtolower(trim((string) $lng));
         if ($lng === '') {
            continue;
         }

         foreach (self::collectPublicPages($db, $renderer, $lng) as $page) {
            $permalink = trim((string) ($page['permalink'] ?? ''), '/');
            if ($permalink === '') {
               continue;
            }

            $loc = $base . $permalink;
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

      foreach (self::collectUserMenuLinks($base) as $link) {
         $loc = trim((string) ($link['loc'] ?? ''));
         if ($loc === '') {
            continue;
         }

         $key = strtolower($loc);
         if (!isset($entries[$key])) {
            $entries[$key] = array(
               'loc' => $loc,
               'lastmod' => '',
            );
         }
      }

      ksort($entries);

      $lines = array('<?xml version="1.0" encoding="UTF-8"?>');
      $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

      foreach ($entries as $entry) {
         $lines[] = '  <url>';
         $lines[] = '    <loc>' . self::xmlEsc((string) $entry['loc']) . '</loc>';
         $lastmod = trim((string) ($entry['lastmod'] ?? ''));
         if ($lastmod !== '') {
            $lines[] = '    <lastmod>' . self::xmlEsc($lastmod) . '</lastmod>';
         }
         $lines[] = '  </url>';
      }

      $lines[] = '</urlset>';
      return implode("\n", $lines) . "\n";
   }

   private static function collectPublicPages($db, dbxContentRenderer $renderer, string $lng): array {
      $pages = array();
      $seen = array();

      if (is_object($db)) {
         $rows = $db->select(
            dbxContentLng::ddContent($lng),
            'activ = 1',
            'id,permalink,folder,update_date',
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
               if (!self::isPublicRights($renderer->getPublicFolderRights((int) ($row['folder'] ?? 0)))) {
                  continue;
               }

               $seen[$cid] = 1;
               $pages[] = array(
                  'cid' => $cid,
                  'permalink' => $permalink,
                  'lastmod' => self::formatLastmod((string) ($row['update_date'] ?? '')),
               );
            }
         }
      }

      if (dbxContentPageCache::isConfigEnabled()) {
         $index = dbxContentPermalinkIndex::load($lng);
         foreach ($index as $permalink => $row) {
            if (!is_array($row)) {
               continue;
            }
            if ((int) ($row['activ'] ?? 0) !== 1) {
               continue;
            }
            if (!self::isPublicRights((string) ($row['rights'] ?? '*'))) {
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
               'permalink' => $permalink,
               'lastmod' => self::lastmodForCid($cid),
            );
         }
      }

      return $pages;
   }

   private static function collectUserMenuLinks(string $base): array {
      $hrefs = array(
         '?dbx_modul=dbxLogin&dbx_run1=run',
         '?dbx_modul=dbxShop&dbx_run1=catalog',
         '?dbx_modul=dbxShop&dbx_run1=cart',
         '?dbx_modul=dbxShop&dbx_run1=orders',
         '?dbx_modul=dbxShop&dbx_run1=withdrawal',
         '?dbx_modul=dbxContact&dbx_run1=form',
         '?dbx_modul=dbxContact&dbx_run1=my',
         '?dbx_modul=dbxUser&dbx_run1=user&dbx_run2=edit_profil',
      );

      $links = array();
      foreach ($hrefs as $href) {
         $loc = self::sitemapLocFromHref($href, $base);
         if ($loc !== '') {
            $links[] = array('loc' => $loc);
         }
      }

      return $links;
   }

   private static function sitemapLocFromHref(string $href, string $base): string {
      $href = html_entity_decode(trim($href), ENT_QUOTES | ENT_HTML5, 'UTF-8');
      if ($href === '' || $href === '#' || $href[0] === '#') {
         return '';
      }
      if (preg_match('/^(?:javascript:|mailto:|tel:)/i', $href)) {
         return '';
      }
      if (preg_match('/^https?:\/\//i', $href)) {
         return $href;
      }
      if ($href[0] === '/') {
         $parts = parse_url($base);
         $scheme = (string) ($parts['scheme'] ?? 'http');
         $host = (string) ($parts['host'] ?? '');
         $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
         return $host !== '' ? $scheme . '://' . $host . $port . $href : rtrim($base, '/') . $href;
      }
      if ($href[0] === '?') {
         return rtrim($base, '/') . '/' . $href;
      }

      return rtrim($base, '/') . '/' . ltrim($href, '/');
   }

   private static function statsFromXml(string $xml): array {
      return array(
         'exists' => trim($xml) !== '',
         'urls' => substr_count($xml, '<url>'),
         'size' => strlen($xml),
         'generated_at' => date('d.m.Y H:i:s'),
         'path' => self::cachePath(),
      );
   }

   private static function isPublicRights(string $rights): bool {
      $rights = trim($rights);
      return $rights === '' || $rights === '*';
   }

   private static function lastmodForCid(int $cid): string {
      $meta = dbxContentPageCache::readPageMeta($cid);
      if (is_array($meta)) {
         $saved = trim((string) ($meta['saved_at'] ?? ''));
         if ($saved !== '') {
            return self::formatLastmod($saved);
         }
         $seo = is_array($meta['seo'] ?? null) ? $meta['seo'] : array();
         $update = trim((string) ($seo['update_date'] ?? ''));
         if ($update !== '') {
            return self::formatLastmod($update);
         }
      }
      return '';
   }

   private static function formatLastmod(string $value): string {
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

   private static function xmlEsc(string $value): string {
      return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
   }
}

?>
