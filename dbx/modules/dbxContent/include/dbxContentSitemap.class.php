<?php
namespace dbx\dbxContent;

require_once __DIR__ . '/dbxContentPageCache.class.php';
require_once __DIR__ . '/dbxContentPermalinkIndex.class.php';
require_once __DIR__ . '/dbxContentRenderer.class.php';
require_once __DIR__ . '/dbxContentHome.class.php';

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
      $renderer = new dbxContentRenderer();
      $entries = array();
      $homeCid = dbxContentHome::masterCid();
      $masterLng = dbxContentLngSync::masterLng();
      // Die öffentliche URL enthält aktuell kein Sprachsegment. Deshalb darf
      // jede flache URL nur einmal und in der maßgeblichen Sprache erscheinen.
      // Weitere Sprachen werden erst mit eigenen kanonischen URLs aufgenommen.
      $lngs = array($masterLng);

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

            $loc = $lng === $masterLng && (int) ($page['cid'] ?? 0) === $homeCid
               ? $base
               : $base . $permalink;
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
            'id,permalink,folder,update_date,meta_robots',
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
               if (self::isNoindex((string) ($row['meta_robots'] ?? ''))) {
                  continue;
               }
               if (!self::isPublicRights($renderer->getPublicFolderRights((int) ($row['folder'] ?? 0)))) {
                  continue;
               }

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
            if (self::isNoindex((string) ($row['meta_robots'] ?? ''))) {
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

   private static function isNoindex(string $robots): bool {
      return in_array('noindex', array_map('trim', explode(',', strtolower($robots))), true);
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
