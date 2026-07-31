<?php
declare(strict_types=1);

use dbx\dbxContent\dbxContentLng;

if (PHP_SAPI !== 'cli') {
   fwrite(STDERR, "Dieses Werkzeug darf nur auf der Kommandozeile laufen.\n");
   exit(1);
}

$write = in_array('--write', $argv, true);
$check = in_array('--check', $argv, true);
if ($write && $check) {
   fwrite(STDERR, "--write und --check dürfen nicht gemeinsam verwendet werden.\n");
   exit(1);
}

$root = dirname(__DIR__, 4);
chdir($root);

$_SERVER['REQUEST_URI'] = '/dbxapp/';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SCRIPT_NAME'] = '/dbxapp/index.php';

define('dbxSystem', 'dbxWebApp');
define('dbxRunAsAdmin', 1);

require $root . '/dbx/vendor/autoload.php';
require_once $root . '/dbx/include/dbxKernel.php';
require_once $root . '/dbx/modules/dbxContent/include/dbxContent_bootstrap_sync.php';

/**
 * Erzeugt einen stabilen Doxygen-Bezeichner aus einem Permalink.
 */
function dbxDoxygenTutorialLabel(string $permalink): string {
   $token = strtolower(trim($permalink));
   $token = preg_replace('/[^a-z0-9]+/', '_', $token);
   $token = trim((string)$token, '_');
   return 'dbxcontent_tutorial_' . ($token !== '' ? $token : 'seite');
}

/**
 * Vereinheitlicht ausschließlich die Produktbezeichnung in der Exportkopie.
 */
function dbxDoxygenNormalizeBrand(string $value): string {
   return str_replace(
      array('dbXApp', 'dbXapp', 'DBXapp', 'DBXApp'),
      'dbxapp',
      $value
   );
}

/**
 * Korrigiert bekannte alte ASCII-Umschriften in sichtbaren Metadaten. Code,
 * Routen und Dateipfade werden bewusst nicht durch diese Funktion geführt.
 */
function dbxDoxygenNormalizeDisplayText(string $value): string {
   $value = dbxDoxygenNormalizeBrand($value);
   return str_replace(
      array(' fuer ', 'Gefuehrter', 'gefuehrter'),
      array(' für ', 'Geführter', 'geführter'),
      $value
   );
}

function dbxDoxygenHtml(string $value): string {
   return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function dbxDoxygenSafeFileToken(string $value): string {
   $value = strtolower(trim($value));
   $value = preg_replace('/[^a-z0-9._-]+/', '-', $value);
   return trim((string)$value, '.-');
}

function dbxDoxygenMediaAssetName(array $row): string {
   $path = trim((string)($row['file_path'] ?? ''));
   $extension = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
   if (!preg_match('/^[a-z0-9]{2,8}$/', $extension)) {
      $extension = 'bin';
   }
   $title = dbxDoxygenSafeFileToken((string)($row['title'] ?? ''));
   if ($title === '') {
      $title = 'medium';
   }
   return 'dbxcontent-media-' . (int)($row['id'] ?? 0) . '-' . $title . '.' . $extension;
}

/**
 * Liefert einen Medien-Datensatz ausschließlich über dbxDB.
 */
function dbxDoxygenMediaRow($db, int $mediaId, array &$cache): array {
   if ($mediaId <= 0) {
      return array();
   }
   if (isset($cache[$mediaId])) {
      return $cache[$mediaId];
   }

   $row = $db->select1(
      'dbxMedia',
      $mediaId,
      'id,active,title,alt,caption,file_name,file_path,mime,size,width,height',
      0
   );
   if (!is_array($row)
      || (int)($row['id'] ?? 0) !== $mediaId
      || (int)($row['active'] ?? 0) !== 1) {
      $row = array();
   }
   $cache[$mediaId] = $row;
   return $row;
}

/**
 * Registriert eine lokale Mediendatei für den generierten Doxygen-Bestand.
 */
function dbxDoxygenRegisterMedia(
   $db,
   int $mediaId,
   array &$mediaCache,
   array &$assets,
   array &$missingMedia,
   string $fileRoot,
   string $assetDir
): string {
   $row = dbxDoxygenMediaRow($db, $mediaId, $mediaCache);
   if (!$row) {
      $missingMedia[$mediaId] = 'Datenbankeintrag fehlt oder ist inaktiv';
      return '';
   }

   $relative = str_replace('\\', '/', trim((string)($row['file_path'] ?? '')));
   $relative = ltrim($relative, '/');
   $source = rtrim($fileRoot, '/\\') . DIRECTORY_SEPARATOR
      . str_replace('/', DIRECTORY_SEPARATOR, $relative);
   $sourceReal = realpath($source);
   $fileRootReal = realpath($fileRoot);
   if ($sourceReal === false
      || $fileRootReal === false
      || strpos(strtolower($sourceReal), strtolower(rtrim($fileRootReal, '/\\') . DIRECTORY_SEPARATOR)) !== 0
      || !is_file($sourceReal)) {
      $missingMedia[$mediaId] = $relative !== '' ? $relative : 'Dateipfad fehlt';
      return '';
   }

   $assetName = dbxDoxygenMediaAssetName($row);
   $assets[$assetName] = array(
      'source' => $sourceReal,
      'target' => $assetDir . DIRECTORY_SEPARATOR . $assetName,
      'row' => $row,
   );
   return $assetName;
}

/**
 * Entfernt aktive Browserbestandteile, normalisiert Überschriften und wandelt
 * dbxContent-interne Links in Doxygen-Referenzen um.
 */
function dbxDoxygenPrepareContent(
   string $html,
   $db,
   array $labelMap,
   array &$inlineMedia,
   array &$mediaCache,
   array &$assets,
   array &$missingMedia,
   string $fileRoot,
   string $assetDir
): string {
   $html = dbxDoxygenNormalizeBrand($html);
   $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
   $html = preg_replace('#<style\b[^>]*>.*?</style>#is', '', (string)$html);
   $html = preg_replace('/\s+on[a-z]+\s*=\s*(["\']).*?\1/is', '', (string)$html);
   $html = preg_replace('#<h1\b([^>]*)>(.*?)</h1>#is', '<h2$1>$2</h2>', (string)$html);

   $html = preg_replace_callback(
      '#(<img\b[^>]*\bsrc=)(["\'])([^"\']+)\2#i',
      static function(array $match) use (
         $db,
         &$inlineMedia,
         &$mediaCache,
         &$assets,
         &$missingMedia,
         $fileRoot,
         $assetDir
      ): string {
         $src = html_entity_decode((string)$match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
         if (!preg_match('/(?:[?&]|\b)dbx_mid=([0-9]+)/i', $src, $idMatch)) {
            return $match[0];
         }
         $mediaId = (int)$idMatch[1];
         $assetName = dbxDoxygenRegisterMedia(
            $db,
            $mediaId,
            $mediaCache,
            $assets,
            $missingMedia,
            $fileRoot,
            $assetDir
         );
         if ($assetName === '') {
            return $match[0];
         }
         $inlineMedia[$mediaId] = 1;
         return $match[1] . $match[2] . $assetName . $match[2];
      },
      (string)$html
   );

   $html = preg_replace_callback(
      '#<a\b([^>]*)\bhref=(["\'])([^"\']+)\2([^>]*)>(.*?)</a>#is',
      static function(array $match) use ($labelMap): string {
         $href = html_entity_decode(trim((string)$match[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
         $permalink = trim((string)parse_url($href, PHP_URL_PATH), '/');
         if (isset($labelMap[$permalink])) {
            $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$match[5])));
            return '<a href="' . dbxDoxygenHtml($labelMap[$permalink]) . '.html">'
               . dbxDoxygenHtml(dbxDoxygenNormalizeBrand($text))
               . '</a>';
         }
         if (strpos($href, '?dbx_') === 0 || strpos($href, 'index.php?dbx_') === 0) {
            $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$match[5])));
            return '<span class="dbx-runtime-route"><strong>'
               . dbxDoxygenHtml(dbxDoxygenNormalizeBrand($text))
               . '</strong><code>' . dbxDoxygenHtml($href) . '</code></span>';
         }
         return $match[0];
      },
      (string)$html
   );

   $html = preg_replace_callback(
      '#\[modul=([^\]]+)\](.*?)\[/modul\]#is',
      static function(array $match): string {
         return '<pre class="dbx-runtime-example"><code>'
            . dbxDoxygenHtml($match[0])
            . '</code></pre>';
      },
      (string)$html
   );

   return trim((string)$html);
}

function dbxDoxygenFigure(array $media, string $assetName): string {
   $alt = trim((string)($media['alt'] ?? ''));
   if ($alt === '') {
      $alt = trim((string)($media['title'] ?? 'Screenshot'));
   }
   $caption = trim((string)($media['caption'] ?? ''));
   if ($caption === '') {
      $caption = trim((string)($media['title'] ?? ''));
   }

   $caption = dbxDoxygenNormalizeDisplayText($caption !== '' ? $caption : $alt);
   $caption = trim(preg_replace('/\s+/u', ' ', strip_tags($caption)));
   $caption = str_replace(array('\\', '"'), array('\\\\', "'"), $caption);
   return '@image html ' . $assetName . ($caption !== '' ? ' "' . $caption . '"' : '');
}

$db = dbx()->get_system_obj('dbxDB');
if (!is_object($db) || !$db->connect_db_server('dbx|dbxContent.db3')) {
   fwrite(STDERR, "Die Content-Datenbank konnte nicht über dbxDB verbunden werden.\n");
   exit(1);
}

$contentDd = dbxContentLng::ddContent('de');
$pages = $db->select(
   $contentDd,
   'folder = 15 AND activ = 1',
   'id,folder,sorter,title,permalink,description,content,update_date',
   'sorter,id',
   'ASC',
   '',
   0,
   0,
   0
);
if (!is_array($pages) || !$pages) {
   fwrite(STDERR, "Im deutschen Tutorial-Ordner wurden keine aktiven Seiten gefunden.\n");
   exit(1);
}

$outputDir = $root . '/docs/generated/tutorials';
$assetDir = $outputDir . '/assets';
$fileRoot = dbx()->get_file_dir();
$labelMap = array();
foreach ($pages as $page) {
   $permalink = trim((string)($page['permalink'] ?? ''));
   if ($permalink !== '') {
      $labelMap[$permalink] = dbxDoxygenTutorialLabel($permalink);
   }
}

$documents = array();
$assets = array();
$mediaCache = array();
$missingMedia = array();
$usageLinks = 0;
$uniqueUsageLinks = 0;
$coveredUsageLinks = 0;

foreach ($pages as $page) {
   $id = (int)($page['id'] ?? 0);
   $permalink = trim((string)($page['permalink'] ?? ''));
   if ($id <= 0 || $permalink === '' || !isset($labelMap[$permalink])) {
      continue;
   }

   $inlineMedia = array();
   $content = dbxDoxygenPrepareContent(
      (string)($page['content'] ?? ''),
      $db,
      $labelMap,
      $inlineMedia,
      $mediaCache,
      $assets,
      $missingMedia,
      $fileRoot,
      $assetDir
   );

   $usageRows = $db->select(
      'dbxMediaUsage',
      'content_id = ' . $id . ' AND active = 1',
      'id,media_id,slot,sorter',
      'slot,sorter,id',
      'ASC',
      '',
      0,
      0,
      0
   );
   $gallery = array();
   $seenUsage = array();
   foreach (is_array($usageRows) ? $usageRows : array() as $usage) {
      $usageLinks++;
      $mediaId = (int)($usage['media_id'] ?? 0);
      if ($mediaId <= 0 || isset($seenUsage[$mediaId])) {
         continue;
      }
      $seenUsage[$mediaId] = 1;
      $uniqueUsageLinks++;
      if (isset($inlineMedia[$mediaId])) {
         $coveredUsageLinks++;
         continue;
      }
      $assetName = dbxDoxygenRegisterMedia(
         $db,
         $mediaId,
         $mediaCache,
         $assets,
         $missingMedia,
         $fileRoot,
         $assetDir
      );
      $media = dbxDoxygenMediaRow($db, $mediaId, $mediaCache);
      if ($assetName === '' || !$media || strpos(strtolower((string)($media['mime'] ?? '')), 'image/') !== 0) {
         continue;
      }
      $gallery[] = dbxDoxygenFigure($media, $assetName);
      $coveredUsageLinks++;
   }

   $title = dbxDoxygenNormalizeDisplayText(trim((string)($page['title'] ?? 'Tutorial')));
   $description = dbxDoxygenNormalizeDisplayText(trim((string)($page['description'] ?? '')));
   $label = $labelMap[$permalink];
   $sorter = dbxDoxygenSafeFileToken((string)($page['sorter'] ?? '9999'));
   $fileName = ($sorter !== '' ? $sorter : '9999') . '-' . dbxDoxygenSafeFileToken($permalink) . '.dox';

   $lines = array(
      '/**',
      '@page ' . $label . ' ' . $title,
      '',
      '<!-- Automatisch aus content_de#' . $id . ' erzeugt. Nicht direkt bearbeiten. -->',
      '',
      '@htmlonly',
      '<div class="dbx-doc-meta"><span>Anwender-Tutorial</span><span>dbxContent #' . $id
         . '</span><span>Permalink: <code>' . dbxDoxygenHtml($permalink) . '</code></span></div>',
      '@endhtmlonly',
      '',
   );
   if ($description !== '') {
      $lines[] = '> **Kurzbeschreibung:** ' . dbxDoxygenHtml($description);
      $lines[] = '';
   }
   $lines[] = '@htmlonly';
   $lines[] = '<div class="dbx-tutorial-source">';
   $lines[] = $content;
   $lines[] = '</div>';
   $lines[] = '@endhtmlonly';
   $lines[] = '';

   if ($gallery) {
      $lines[] = '## Screenshots aus dbxContent (' . count($gallery) . ')';
      $lines[] = '';
      $lines[] = '@htmlonly';
      $lines[] = '<div class="dbx-tutorial-gallery">';
      $lines[] = '@endhtmlonly';
      foreach ($gallery as $figure) {
         $lines[] = $figure;
         $lines[] = '';
      }
      $lines[] = '@htmlonly';
      $lines[] = '</div>';
      $lines[] = '@endhtmlonly';
      $lines[] = '';
   }

   $lines[] = '@note Quelle: deutsche dbxContent-Seite `content_de#' . $id
      . '`. Generiert am 28. Juli 2026 über DD, dbxDB und Medienzuordnungen.';
   $lines[] = '';

   if ($inlineMedia) {
      $lines[] = '@htmlonly';
      $lines[] = '<div class="dbx-doxygen-image-deps" aria-hidden="true">';
      $lines[] = '@endhtmlonly';
      foreach (array_keys($inlineMedia) as $mediaId) {
         $media = dbxDoxygenMediaRow($db, (int)$mediaId, $mediaCache);
         if ($media) {
            $lines[] = '@image html ' . dbxDoxygenMediaAssetName($media);
         }
      }
      $lines[] = '@htmlonly';
      $lines[] = '</div>';
      $lines[] = '@endhtmlonly';
      $lines[] = '';
   }

   $lines[] = '*/';
   $lines[] = '';

   $documents[$fileName] = implode("\n", $lines);
}

$stale = array();
$written = 0;
$copiedAssets = 0;
$mode = $write ? 'write' : ($check ? 'check' : 'dry-run');

if ($write) {
   if (!is_dir($assetDir) && !mkdir($assetDir, 0775, true) && !is_dir($assetDir)) {
      fwrite(STDERR, "Das Doxygen-Tutorialverzeichnis konnte nicht angelegt werden.\n");
      exit(1);
   }
   foreach (array_merge(
      glob($outputDir . '/*.md') ?: array(),
      glob($outputDir . '/*.dox') ?: array()
   ) as $oldFile) {
      @unlink($oldFile);
   }
   foreach (glob($assetDir . '/dbxcontent-media-*') ?: array() as $oldAsset) {
      @unlink($oldAsset);
   }
   foreach ($documents as $fileName => $content) {
      if (file_put_contents($outputDir . '/' . $fileName, $content, LOCK_EX) === false) {
         fwrite(STDERR, "Tutorial konnte nicht geschrieben werden: {$fileName}\n");
         exit(1);
      }
      $written++;
   }
   foreach ($assets as $asset) {
      if (!copy((string)$asset['source'], (string)$asset['target'])) {
         fwrite(STDERR, "Tutorialmedium konnte nicht kopiert werden: {$asset['source']}\n");
         exit(1);
      }
      $copiedAssets++;
   }
}

if ($check) {
   foreach ($documents as $fileName => $content) {
      $path = $outputDir . '/' . $fileName;
      if (!is_file($path) || (string)file_get_contents($path) !== $content) {
         $stale[] = str_replace('\\', '/', substr($path, strlen($root) + 1));
      }
   }
   foreach ($assets as $assetName => $asset) {
      $target = (string)$asset['target'];
      $source = (string)$asset['source'];
      if (!is_file($target)
         || filesize($target) !== filesize($source)
         || hash_file('sha256', $target) !== hash_file('sha256', $source)) {
         $stale[] = 'docs/generated/tutorials/assets/' . $assetName;
      }
   }
}

$result = array(
   'mode' => $mode,
   'language' => 'de',
   'source_dd' => $contentDd,
   'tutorial_pages' => count($documents),
   'media_usage_links' => $usageLinks,
   'unique_page_media_links' => $uniqueUsageLinks,
   'covered_page_media_links' => $coveredUsageLinks,
   'unique_media_assets' => count($assets),
   'missing_media' => $missingMedia,
   'written_pages' => $written,
   'copied_assets' => $copiedAssets,
   'stale_files' => $stale,
);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if ($missingMedia || ($check && $stale)) {
   exit(1);
}
