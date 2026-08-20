<?php

/** Automatisch aus der stabilen Legacy-Fassade extrahierte interne Verantwortung. */
trait dbxWebAppResourcesTrait
{
/** Ermittelt die Dateiendung aus dem dekodierten Ressourcenpfad. */
  private function get_resource_extension($permalink): string {
     $path = str_replace('\\', '/', rawurldecode((string)$permalink));
     return strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
  }

/** Dynamische Systemrouten duerfen nicht als fehlende Dateien gelten. */
  private function is_dynamic_file_route($permalink): bool {
     $route = strtolower(trim(str_replace('\\', '/', rawurldecode((string)$permalink)), '/'));
     return in_array($route, self::DYNAMIC_FILE_ROUTES, true);
  }

/**
   * Nur von der eigenen Website ausgelöste Ressourcenfehler gehören in die
   * Qualitätskontrolle. Direkte Requests ohne Referer sind fast immer Bots,
   * Scanner oder Browser-Probes (z. B. swagger.json, ads.txt und RSC-Dateien).
   * `www.` wird dabei als Alias derselben Website behandelt.
   */
  private function has_internal_resource_referer(): bool {
    $referer = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
    if ($referer === '') {
      return false;
    }

    $referer_host = (string)(parse_url($referer, PHP_URL_HOST) ?? '');
    $request_authority = trim((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    $request_host = (string)(parse_url('http://' . $request_authority, PHP_URL_HOST) ?? '');

    $normalize = static function (string $host): string {
      $host = rtrim(strtolower(trim($host)), '.');
      return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    };

    $referer_host = $normalize($referer_host);
    $request_host = $normalize($request_host);
    return $referer_host !== '' && $request_host !== '' && hash_equals($request_host, $referer_host);
  }

/**
   * Löst einen Permalink sicher auf eine lokale Ressourcendatei auf.
   *
   * Sicherheitsregel:
   * Der finale Realpath muss innerhalb des dbXapp-Basisverzeichnisses liegen.
   *
   * @param string $permalink Angefragter Ressourcenpfad.
   * @return string Absoluter Dateipfad oder leerer String.
   */
  private function get_resource_file($permalink) {
    $path = str_replace('\\', '/', rawurldecode((string) $permalink));
    $path = ltrim($path, '/');

    if ($path === '' || strpos($path, "\0") !== false) {
      return '';
    }

    $parts = explode('/', $path);
    foreach ($parts as $part) {
      if ($part === '..') {
        return '';
      }
    }

    // Safari und einige Crawler fragen diese Standardnamen auch ohne expliziten
    // Link ab. Das vorhandene Favicon ist die kanonische lokale Quelle.
    if (preg_match('/^apple-touch-icon(?:-precomposed)?(?:-\d+x\d+)?\.png$/i', $path)) {
      $path = 'favicon.png';
      $parts = array($path);
    }

    $base = realpath(dbx()->get_base_dir());
    if (!$base) {
      return '';
    }

    $file = realpath($base . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts));
    if (!$file || !is_file($file)) {
      return '';
    }

    $base_check = rtrim(str_replace('\\', '/', strtolower($base)), '/') . '/';
    $file_check = str_replace('\\', '/', strtolower($file));

    if (strpos($file_check, $base_check) !== 0) {
      return '';
    }

    return $file;
  }

/**
   * Liefert den passenden Content-Type fuer statische Ressourcen.
   *
   * @param string $ext Dateiendung.
   * @return string MIME-Type.
   */
  private function get_resource_mime($ext) {
    $ext = strtolower(ltrim(trim((string)$ext), '.'));
    return self::RESOURCE_MIME_TYPES[$ext] ?? 'application/octet-stream';
  }

/**
   * Sendet eine statische Ressource und beendet den Request.
   *
   * @param string $file Absoluter Dateipfad.
   * @param string $ext Dateiendung.
   * @return void
   */
  private function send_resource_file($file, $ext) {
    if (headers_sent()) {
      return;
    }

    header('Content-Type: ' . $this->get_resource_mime($ext));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=3600');
    header('Content-Length: ' . (string)filesize($file));

    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'HEAD') {
      readfile($file);
    }
    exit;
  }
}
