<?php

declare(strict_types=1);

namespace dbx\dbxContent;

/**
 * Bedient sichere Gast-Page-Cache-Treffer vor dem dbxApp-Bootstrap.
 *
 * Die Klasse ist absichtlich frei von dbx(), Session- und Datenbankzugriffen.
 * Unklare Requests werden nicht interpretiert, sondern an die normale
 * Request-Pipeline weitergereicht.
 */
final class dbxEarlyPageCache {

   private const CACHE_VERSION = 'v3';
   private const GENERATION_FILE = '.generation';

   /** Liefert einen sicheren Treffer aus und beendet nur dann den Request. */
   public static function try_serve(string $application_root): bool {
      $response = self::find_response($application_root);
      if ($response === null) {
         return false;
      }

      if (!headers_sent()) {
         header_remove('Set-Cookie');
         header_remove('Expires');
         header_remove('Pragma');
         header('Content-Type: text/html; charset=UTF-8');
         header('Cache-Control: public, max-age=' . $response['ttl'] . ', stale-while-revalidate=30');
         // Browser duerfen die zuvor anonyme HTML-Antwort nicht wiederverwenden,
         // sobald ein Sprach-, Design- oder Farbschalter eine Session angelegt
         // hat. Die serverseitige Cache-Datei bleibt davon unberuehrt.
         header('Vary: Cookie', false);
         header('ETag: ' . $response['etag']);
         header('X-Dbx-Page-Cache: HIT-EARLY');
         header('Server-Timing: dbx-page-cache;desc="early-hit"');

         $if_none_match = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
         if ($if_none_match !== '' && hash_equals($response['etag'], $if_none_match)) {
            http_response_code(304);
            exit;
         }
      }

      if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'HEAD') {
         echo $response['html'];
      }
      exit;
   }

   /**
    * @return array{html:string,etag:string,ttl:int}|null
    */
   public static function find_response(string $application_root): ?array {
      if (!self::is_safe_guest_request()) {
         return null;
      }

      $application_root = rtrim(str_replace('\\', '/', $application_root), '/');
      $cache_root = $application_root . '/files/cache/';
      $generation_file = $cache_root . 'content/full-page/' . self::GENERATION_FILE;
      $generation = self::read_generation($generation_file);
      if ($generation === '') {
         return null;
      }

      $route_key = self::request_key();
      if ($route_key === '') {
         return null;
      }

      $route_file = $cache_root . 'meta/page-routes/' . $route_key . '.json';
      $route = is_file($route_file) && is_readable($route_file)
         ? json_decode((string)@file_get_contents($route_file), true)
         : null;
      if (!is_array($route) || !hash_equals($generation, (string)($route['generation'] ?? ''))) {
         return null;
      }

      $file = (string)($route['file'] ?? '');
      if (!preg_match('/^[a-z0-9_-]+_[a-f0-9]{24}_' . self::CACHE_VERSION . '\\.htm$/i', $file)) {
         return null;
      }

      $path = $cache_root . 'content/full-page/' . $file;
      if (!is_file($path) || !is_readable($path)) {
         return null;
      }

      $html = @file_get_contents($path);
      if (!is_string($html)
          || !preg_match('/^\s*<!doctype\s+html\b/i', $html)
          || stripos($html, '</html>') === false
          || !hash_equals($generation, self::read_generation($generation_file))) {
         return null;
      }

      $config = self::read_config($application_root . '/dbx/modules/dbx/cfg/config.php');
      $ttl = max(0, min(3600, (int)($config['full_page_browser_ttl'] ?? 60)));
      return array(
         'html' => $html,
         'etag' => '"' . hash('sha256', $html) . '"',
         'ttl' => $ttl,
      );
   }

   /** Registriert die aktuelle URL atomar als frueh lesbare Cache-Route. */
   public static function register(string $cache_path, string $generation): bool {
      if (!self::is_safe_guest_request()
          || !preg_match('/^[a-f0-9]{24}$/', $generation)
          || !is_file($cache_path)) {
         return false;
      }

      $route_key = self::request_key();
      if ($route_key === '') {
         return false;
      }

      $cache_root = dirname(dirname(dirname($cache_path)));
      $route_dir = $cache_root . DIRECTORY_SEPARATOR . 'meta' . DIRECTORY_SEPARATOR . 'page-routes';
      if (!is_dir($route_dir) && !@mkdir($route_dir, 0755, true) && !is_dir($route_dir)) {
         return false;
      }

      $payload = json_encode(array(
         'generation' => $generation,
         'file' => basename($cache_path),
      ), JSON_UNESCAPED_SLASHES);
      if (!is_string($payload)) {
         return false;
      }

      return self::atomic_write($route_dir . DIRECTORY_SEPARATOR . $route_key . '.json', $payload);
   }

   private static function is_safe_guest_request(): bool {
      $method = strtoupper(trim((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')));
      if ($method !== 'GET' && $method !== 'HEAD') {
         return false;
      }

      foreach (array_keys($_COOKIE) as $cookie_name) {
         if (str_starts_with(strtoupper((string)$cookie_name), 'DBXSESSID')) {
            return false;
         }
      }
      if (trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? '')) !== '') {
         return false;
      }

      // Sprach-, Design- und Farbschalter schreiben den neuen Zustand beim
      // regulaeren Bootstrap in die Session. Ein Early-Hit wuerde zwar die
      // passende Variante anzeigen, den Schalter aber nicht speichern; beim
      // naechsten parameterlosen Aufruf erschiene wieder der alte Zustand.
      // Deshalb sind ausschliesslich vollstaendig parameterlose Content-URLs
      // fuer den Bootstrap-freien Cache zulaessig.
      if ($_GET !== array()) {
         return false;
      }

      $route = self::request_route();
      return $route !== ''
         && !in_array(strtolower($route), array('admin', 'sitemap', 'sitemap.xml', 'robots.txt'), true);
   }

   private static function request_key(): string {
      $route = self::request_route();
      if ($route === '') {
         return '';
      }

      $query = array();
      parse_str((string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY) ?? ''), $request_query);
      foreach ($request_query as $name => $value) {
         if (!is_scalar($value)) {
            return '';
         }
         $query[(string)$name] = (string)$value;
      }
      ksort($query, SORT_STRING);

      return hash('sha256', self::request_origin() . '|' . $route . '|' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
   }

   private static function request_route(): string {
      $path = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
      $path = str_replace('\\', '/', $path);
      $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
      $base = rtrim(str_replace('\\', '/', dirname($script)), '/.');
      if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
         $path = substr($path, strlen($base));
      }

      $route = trim($path, '/');

      // Die Installationswurzel ist die meistbesuchte Content-Seite. Ein
      // leerer String darf intern nicht zugleich "keine sichere Route"
      // bedeuten, sonst erreicht ausgerechnet die Startseite nie den
      // Bootstrap-freien Cache. Der reservierte Schlüssel kann mit keinem
      // normalen Permalink kollidieren.
      return $route !== '' ? $route : '@dbx-home';
   }

   private static function request_origin(): string {
      $forwarded_proto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
      if ($forwarded_proto === 'http' || $forwarded_proto === 'https') {
         $scheme = $forwarded_proto;
      } else {
         $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
         $scheme = ($https !== '' && $https !== 'off') || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443
            ? 'https'
            : 'http';
      }

      $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'local')));
      $host = preg_replace('/[^a-z0-9.:[\]-]+/i', '', $host) ?: 'local';
      $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
      $install_path = trim(str_replace('\\', '/', dirname($script)), '/.');
      return $scheme . '://' . $host . '/' . $install_path;
   }

   private static function read_generation(string $path): string {
      $generation = is_file($path) && is_readable($path) ? trim((string)@file_get_contents($path)) : '';
      return preg_match('/^[a-f0-9]{24}$/', $generation) ? $generation : '';
   }

   private static function read_config(string $path): array {
      if (!is_file($path) || !is_readable($path)) {
         return array();
      }
      $reader = static function(string $config_path): array {
         $config = array();
         include $config_path;
         return is_array($config) ? $config : array();
      };
      return $reader($path);
   }

   private static function atomic_write(string $path, string $contents): bool {
      try {
         $suffix = bin2hex(random_bytes(6));
      } catch (\Throwable $e) {
         $suffix = str_replace('.', '', uniqid('', true));
      }
      $temporary = $path . '.tmp-' . $suffix;
      $bytes = @file_put_contents($temporary, $contents, LOCK_EX);
      if ($bytes !== strlen($contents) || !@rename($temporary, $path)) {
         @unlink($temporary);
         return false;
      }
      return true;
   }
}
