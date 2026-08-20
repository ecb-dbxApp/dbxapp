<?php
/**
 * Meldet alle URLs aus der Live-Sitemap per IndexNow.
 *
 * Aufruf:
 *   php tools/submit_indexnow.php
 *   php tools/submit_indexnow.php https://dbxapp.de/sitemap.xml
 */

$sitemap_url = $argv[1] ?? 'https://dbxapp.de/sitemap.xml';
$endpoint = $argv[2] ?? 'https://api.indexnow.org/indexnow';
$key = '62c9ae385f7d410bdbxappindexnow20260708';
$host = parse_url($sitemap_url, PHP_URL_HOST) ?: 'dbxapp.de';
$scheme = parse_url($sitemap_url, PHP_URL_SCHEME) ?: 'https';
$key_file = $key . '.txt';
$key_location = $scheme . '://' . $host . '/' . $key_file;

function http_get_string(string $url): string {
   $ctx = stream_context_create(array(
      'http' => array(
         'method' => 'GET',
         'header' => "User-Agent: dbxapp-indexnow/1.0\r\n",
         'timeout' => 20,
      ),
      'ssl' => array(
         'verify_peer' => true,
         'verify_peer_name' => true,
      ),
   ));
   $data = @file_get_contents($url, false, $ctx);
   return is_string($data) ? $data : '';
}

function http_post_json(string $url, array $payload): array {
   $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
   if (!is_string($json)) {
      return array('code' => 0, 'body' => 'JSON encode failed');
   }

   $ctx = stream_context_create(array(
      'http' => array(
         'method' => 'POST',
         'header' => "Content-Type: application/json; charset=utf-8\r\nUser-Agent: dbxapp-indexnow/1.0\r\n",
         'content' => $json,
         'ignore_errors' => true,
         'timeout' => 30,
      ),
      'ssl' => array(
         'verify_peer' => true,
         'verify_peer_name' => true,
      ),
   ));

   $body = @file_get_contents($url, false, $ctx);
   $code = 0;
   foreach ($http_response_header ?? array() as $header) {
      if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $m)) {
         $code = (int) $m[1];
      }
   }

   return array('code' => $code, 'body' => is_string($body) ? $body : '');
}

/**
 * Prueft bei dynamischen Sitemap-URLs das Robots-Meta-Tag. So werden z. B.
 * Login, Warenkorb, Bestellungen und Profil nicht an IndexNow gemeldet.
 */
function url_has_noindex(string $url): bool {
   if (parse_url($url, PHP_URL_QUERY) === null) {
      return false;
   }

   $html = http_get_string($url);
   if ($html === '') {
      // Bei einem Lesefehler keine potenziell private dynamische URL melden.
      return true;
   }

   return (bool) preg_match(
      '/<meta\b(?=[^>]*\bname\s*=\s*["\']robots["\'])(?=[^>]*\bcontent\s*=\s*["\'][^"\']*\bnoindex\b)[^>]*>/i',
      $html
   );
}

$key_check = trim(http_get_string($key_location));
if ($key_check !== $key) {
   fwrite(STDERR, "IndexNow-Key ist live noch nicht erreichbar: {$key_location}\n");
   fwrite(STDERR, "Die Datei {$key_file} muss zuerst in den Webroot von {$host}.\n");
   exit(2);
}

$xml = http_get_string($sitemap_url);
if (trim($xml) === '') {
   fwrite(STDERR, "Sitemap konnte nicht gelesen werden: {$sitemap_url}\n");
   exit(3);
}

libxml_use_internal_errors(true);
$sitemap = simplexml_load_string($xml);
if (!$sitemap) {
   fwrite(STDERR, "Sitemap ist kein gueltiges XML: {$sitemap_url}\n");
   exit(4);
}

$urls = array();
$skipped_noindex = array();
foreach ($sitemap->url as $url) {
   $loc = trim((string) $url->loc);
   if ($loc === '') {
      continue;
   }
   if (url_has_noindex($loc)) {
      $skipped_noindex[$loc] = $loc;
      continue;
   }
   $urls[$loc] = $loc;
}
$urls = array_values($urls);
if (!count($urls)) {
   fwrite(STDERR, "Keine URLs in der Sitemap gefunden.\n");
   exit(5);
}

$payload = array(
   'host' => $host,
   'key' => $key,
   'keyLocation' => $key_location,
   'urlList' => $urls,
);

$result = http_post_json($endpoint, $payload);
echo "Endpoint: {$endpoint}\n";
echo "Host: {$host}\n";
echo "URLs: " . count($urls) . "\n";
echo "Noindex uebersprungen: " . count($skipped_noindex) . "\n";
echo "HTTP: " . $result['code'] . "\n";
if (trim($result['body']) !== '') {
   echo trim($result['body']) . "\n";
}

exit(in_array((int) $result['code'], array(200, 202), true) ? 0 : 6);
