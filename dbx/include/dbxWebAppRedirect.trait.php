<?php

/** Automatisch aus der stabilen Legacy-Fassade extrahierte interne Verantwortung. */
trait dbxWebAppRedirectTrait
{
/**
   * Ermittelt das 301-Ziel fuer den historischen Startseiten-Permalink.
   *
   * `home` bleibt intern der Permalink des konfigurierten Content-Datensatzes,
   * ist extern aber nur ein Alias der Basis-URL. Explizite Modul- und
   * Aktionsrouten werden nicht umgedeutet.
   *
   * @return string Kanonische Basis-URL oder leer, wenn kein Redirect gilt.
   */
  public function canonical_home_redirect_target(): string {
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method !== 'GET' && $method !== 'HEAD') {
      return '';
    }

    $permalink = strtolower(trim(
      str_replace('\\', '/', (string)dbx()->get_system_var('dbx_permalink', '')),
      '/'
    ));
    if ($permalink !== 'home') {
      return '';
    }

    $route_keys = array(
      'dbx_modul',
      'dbx_run1',
      'dbx_run2',
      'dbx_action',
      'dbx_ajax',
      'dbx_window',
      'cid',
      'dbx_cid',
    );
    foreach ($route_keys as $key) {
      if (trim((string)dbx()->get_request_var($key, '', '*')) !== '') {
        return '';
      }
    }

    $base_url = rtrim((string)dbx()->get_base_url(), '/') . '/';
    if ((int)dbx()->get_cfg('dbx', 'language_path_prefix', 0) !== 1) {
      return $base_url;
    }

    $language = strtolower(trim((string)dbx()->get_system_var('dbx_lng', '')));
    $default_language = strtolower(trim((string)dbx()->get_cfg('dbx', 'default_lng', 'de')));
    if ($language === '' || $language === $default_language
        || preg_match('/^[a-z]{2,3}$/', $language) !== 1) {
      return $base_url;
    }

    return $base_url . rawurlencode($language) . '/';
  }

/**
   * Sendet den kanonischen Startseiten-Redirect, sofern er fuer den Request gilt.
   *
   * @return bool true, wenn eine Redirect-Response gesetzt wurde.
   */
  public function apply_canonical_home_redirect(): bool {
    $target = $this->canonical_home_redirect_target();
    if ($target === '' || headers_sent()) {
      return false;
    }

    header('Location: ' . $target, true, 301);
    return true;
  }

/**
   * Liefert das Ziel einer sprachabhängigen Content-Weiterleitung.
   *
   * Die Weiterleitungen werden redaktionell im Modul dbxContent gepflegt.
   * Erlaubt sind ausschließlich die Basis-URL oder gültige interne
   * Flat-Permalinks. Explizite Modul- und Aktionsrouten bleiben unberührt.
   *
   * @return string Absolute Ziel-URL oder leer.
   */
  public function content_permalink_redirect_target(): string {
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method !== 'GET' && $method !== 'HEAD') {
      return '';
    }

    foreach (array(
      'dbx_modul', 'dbx_run1', 'dbx_run2', 'dbx_run3', 'dbx_action',
      'dbx_do', 'action', 'dbx_ajax', 'dbx_window', 'cid', 'dbx_cid',
    ) as $key) {
      if (trim((string)dbx()->get_request_var($key, '', '*')) !== '') {
        return '';
      }
    }

    $permalink = strtolower(trim(
      str_replace('\\', '/', (string)dbx()->get_system_var('dbx_permalink', '')),
      '/'
    ));
    if ($permalink === '' || $permalink === 'home') {
      return '';
    }

    $lng = strtolower(trim((string)dbx()->get_system_var('dbx_lng', 'de')));
    if (!preg_match('/^[a-z]{2,3}$/', $lng)) {
      return '';
    }

    $redirects = dbx()->get_cfg('dbxContent', 'permalink_redirects', array());
    $redirects = is_array($redirects) && is_array($redirects[$lng] ?? null)
      ? $redirects[$lng]
      : array();
    if (!array_key_exists($permalink, $redirects)) {
      return '';
    }

    $target = strtolower(trim((string)$redirects[$permalink]));
    if ($target === '' || $target === '/') {
      return rtrim((string)dbx()->get_base_url(), '/') . '/';
    }
    $target = trim($target, '/');
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $target)) {
      return '';
    }

    return rtrim((string)dbx()->get_base_url(), '/') . '/' . $target;
  }

/**
   * Sendet eine konfigurierte permanente Content-Weiterleitung.
   *
   * @return bool true, wenn die Redirect-Response gesetzt wurde.
   */
  public function apply_content_permalink_redirect(): bool {
    $target = $this->content_permalink_redirect_target();
    if ($target === '' || headers_sent()) {
      return false;
    }

    header('Location: ' . $target, true, 301);
    return true;
  }

/**
   * Leitet eine bereits aufgelöste Content-Route auf ihre kanonische URL.
   * Das betrifft insbesondere Sprachwechsel per dbx_lng und versehentlich
   * verdoppelte Sprach- oder Permalink-Pfadteile.
   */
  public function apply_canonical_content_redirect(): bool {
    if (headers_sent()) {
      return false;
    }
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method !== 'GET' && $method !== 'HEAD') {
      return false;
    }
    foreach (array('dbx_modul', 'dbx_run1', 'dbx_action', 'dbx_ajax', 'dbx_window', 'cid', 'dbx_cid') as $key) {
      if (trim((string)dbx()->get_request_var($key, '', '*')) !== '') {
        return false;
      }
    }

    $routes = dbx()->get_include_obj('dbxContentCanonicalRoute', 'dbxContent');
    if (!is_object($routes) || !method_exists($routes, 'current_page_url')) {
      return false;
    }
    $target = trim((string)$routes->current_page_url());
    if ($target === '') {
      return false;
    }

    $request_uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $request_path = rawurldecode((string)(parse_url($request_uri, PHP_URL_PATH) ?? ''));
    $target_path = rawurldecode((string)(parse_url($target, PHP_URL_PATH) ?? ''));
    $query = array();
    parse_str((string)(parse_url($request_uri, PHP_URL_QUERY) ?? ''), $query);
    $had_language_query = array_key_exists('dbx_lng', $query);
    unset($query['dbx_lng']);

    if (rtrim($request_path, '/') === rtrim($target_path, '/') && !$had_language_query) {
      return false;
    }
    if ($query !== array()) {
      $target .= (str_contains($target, '?') ? '&' : '?')
        . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
    header('Location: ' . $target, true, 302);
    return true;
  }

/**
   * Leitet einen unbekannten öffentlichen Permalink auf die zentrale,
   * sprachabhängige Navigationsseite weiter.
   *
   * Die eigentliche Permalink-Auflösung setzt zuvor
   * `dbx_content_not_found`. Technische Missing-, API-, Ajax- und
   * Medienantworten erreichen diesen Schritt nicht.
   */
  public function apply_missing_permalink_redirect(): bool {
    if ((int)dbx()->get_system_var('dbx_content_not_found', 0, 'int') !== 1
        || headers_sent()) {
      return false;
    }

    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method !== 'GET' && $method !== 'HEAD') {
      return false;
    }

    $navigation = dbx()->get_include_obj(
      'dbxContentMissingNavigation',
      'dbxContent'
    );
    if (!is_object($navigation)
        || !method_exists($navigation, 'redirect_target')) {
      return false;
    }

    $target = $navigation->redirect_target(
      (string)dbx()->get_system_var('dbx_permalink', ''),
      (string)dbx()->get_system_var('dbx_lng', 'de')
    );
    if ($target === '') {
      return false;
    }

    header('Location: ' . $target, true, 302);
    return true;
  }
}
