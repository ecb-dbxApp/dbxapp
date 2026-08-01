<?php
/**
 * @file dbxWebApp.class.php
 * Request-, Routing-, Design- und Ausgabevorbereitung des dbXapp-Frontcontrollers.
 */

/**
 * Bereitet den aktuellen HTTP-Request fuer dbXapp vor.
 *
 * Die Klasse ist kein Router im SPA-Sinn. Sie normalisiert den klassischen
 * PHP-Request fuer den Single Entry Point `index.php`:
 * - Basis-URL und Self-URL bestimmen
 * - Permalink und Query-Parameter aufloesen
 * - Modul, Design, Sprache und Content-Kontext setzen
 * - statische Ressourcen ausliefern
 * - Design-Templates und Outputfilter vorbereiten
 *
 * Beispiel aus dem Frontcontroller-Kontext:
 * ```php
 * $web = dbx()->get_system_obj('dbxWebApp');
 * $web->check_request();
 * $web->check_modul();
 * $web->check_design();
 * ```
 */
class dbxWebApp {

  /** MIME-Typen statischer Ressourcen, die dbxMissing ueberwachen soll. */
  private const RESOURCE_MIME_TYPES = array(
    // Styles, Skripte und Browser-Daten
    'css'         => 'text/css; charset=UTF-8',
    'js'          => 'application/javascript; charset=UTF-8',
    'mjs'         => 'application/javascript; charset=UTF-8',
    'cjs'         => 'application/javascript; charset=UTF-8',
    'map'         => 'application/json; charset=UTF-8',
    'json'        => 'application/json; charset=UTF-8',
    'webmanifest' => 'application/manifest+json; charset=UTF-8',
    'wasm'        => 'application/wasm',

    // Bilder
    'avif' => 'image/avif',
    'bmp'  => 'image/bmp',
    'gif'  => 'image/gif',
    'ico'  => 'image/x-icon',
    'jfif' => 'image/jpeg',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'svg'  => 'image/svg+xml',
    'svgz' => 'image/svg+xml',
    'tif'  => 'image/tiff',
    'tiff' => 'image/tiff',
    'webp' => 'image/webp',

    // Schriften
    'eot'   => 'application/vnd.ms-fontobject',
    'otf'   => 'font/otf',
    'ttf'   => 'font/ttf',
    'woff'  => 'font/woff',
    'woff2' => 'font/woff2',

    // Dokumente und Downloads
    'csv'  => 'text/csv; charset=UTF-8',
    'pdf'  => 'application/pdf',
    'txt'  => 'text/plain; charset=UTF-8',
    'xml'  => 'application/xml; charset=UTF-8',
    '7z'   => 'application/x-7z-compressed',
    'gz'   => 'application/gzip',
    'rar'  => 'application/vnd.rar',
    'tar'  => 'application/x-tar',
    'zip'  => 'application/zip',
    'exe'  => 'application/octet-stream',

    // Audio und Video
    'aac'  => 'audio/aac',
    'flac' => 'audio/flac',
    'm4a'  => 'audio/mp4',
    'mp3'  => 'audio/mpeg',
    'oga'  => 'audio/ogg',
    'ogg'  => 'audio/ogg',
    'opus' => 'audio/opus',
    'wav'  => 'audio/wav',
    'avi'  => 'video/x-msvideo',
    'm4v'  => 'video/mp4',
    'mov'  => 'video/quicktime',
    'mp4'  => 'video/mp4',
    'mpeg' => 'video/mpeg',
    'mpg'  => 'video/mpeg',
    'ogv'  => 'video/ogg',
    'webm' => 'video/webm',
  );

  /** Routen, die trotz Dateiendung von dbXapp dynamisch erzeugt werden. */
  private const DYNAMIC_FILE_ROUTES = array(
    'robots.txt',
    'sitemap.xml',
  );

  /**
   * Systemweit bekannte zustandsaendernde dbxReport-Aktionen.
   *
   * Der Parametername ist absichtlich nicht Bestandteil der Definition:
   * Legacy-Reports transportieren den Aktionscode je nach Generation ueber
   * `dbx_do`, `dbx_run2` oder `dbx_run3`. Der kanonische Aktionsname bleibt
   * fuer alle Varianten identisch.
   */
  private const REPORT_ACTION_POLICIES = array(
    'row_delete'       => array('action' => 'dbxReport.row_delete',       'bind' => array('rid')),
    'delete_tab'       => array('action' => 'dbxReport.delete_tab',       'bind' => array()),
    'multi_delete'     => array('action' => 'dbxReport.multi_delete',     'bind' => array()),
    'multi_activate'   => array('action' => 'dbxReport.multi_activate',   'bind' => array()),
    'multi_deactivate' => array('action' => 'dbxReport.multi_deactivate', 'bind' => array()),
    'rows_delete'      => array('action' => 'dbxReport.multi_delete',     'bind' => array()),
    'rows_activate'    => array('action' => 'dbxReport.multi_activate',   'bind' => array()),
    'rows_deactivate'  => array('action' => 'dbxReport.multi_deactivate', 'bind' => array()),
  );

  /**
   * Mutierende Standardaktionen, die zusammen mit `rid` automatisch sicher sind.
   *
   * Die Erkennung betrachtet ausschließlich die dbx-Aktionsparameter und
   * eigenständige Aktionsbestandteile. `invoice_delete` wird erkannt,
   * `undelete`, Freitext, Filter oder eine bloße RID dagegen nicht.
   */
  private const AUTOMATIC_RID_ACTIONS = array(
    'delete' => 'dbxAction.delete',
    'save'   => 'dbxAction.save',
  );

  /**
   * Bereits geladene Modul-Policies des aktuellen Requests.
   *
   * Ein Report kann fuer jede Zeile action_url() aufrufen. Deshalb darf die
   * Modulkonfiguration nicht fuer jede URL erneut geladen und signiert werden.
   */
  private $actionRouteCache = array();


    /**
     * Ermittelt die Basis-URL des Servers und gibt sie zurück.
     *
     * Diese Funktion bestimmt das Protokoll, die Host-Adresse und den Port,
     * um eine vollständige URL zu generieren. Falls der Port 4221 ist,
     * wird die Basis-URL aus einer Konfigurationsdatei gelesen.
     * 
     * Die URL wird für Performance-Zwecke zwischengespeichert und
     * kann über `dbx_get_Remember` aus dem Cache abgerufen werden.
     *
     * @param string $uri Der URI, der an die Basis-URL angehängt wird.
     * @return string Die vollständige URL mit angehängtem URI.
     */
    function get_base_url(string $uri): string {
        $base_url = dbx()->get_remember_var('base_url', 0, 'dbx');

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_ADDR'] ?? 'localhost');
        $host = preg_replace('/:\d+$/', '', (string) $host);

        if ($host === 'heserver') {
            $host = 'heserver';
        } elseif ($host === 'localhost' || $host === '127.0.0.1') {
            $host = 'localhost';
        }

        $port = (int) ($_SERVER['SERVER_PORT'] ?? 0);
        $xport = ($port && $port != 80 && $port != 443) ? ":$port" : '';
        $request_origin = $protocol . $host . $xport;

        if ($base_url && $port != 4221) {
            $cached = parse_url((string) $base_url);
            $cached_scheme = strtolower((string) ($cached['scheme'] ?? ''));
            $cached_host = strtolower((string) ($cached['host'] ?? ''));
            $cached_port = (int) ($cached['port'] ?? ($cached_scheme === 'https' ? 443 : 80));
            $cached_path = '/' . trim(str_replace('\\', '/', (string) ($cached['path'] ?? '')), '/');
            $request_path = '/' . trim(str_replace('\\', '/', $uri), '/');
            $request_port = $port ?: ($protocol === 'https://' ? 443 : 80);

            if (
                $cached_scheme !== rtrim($protocol, ':/') ||
                $cached_host !== strtolower($host) ||
                $cached_port !== $request_port ||
                $cached_path !== $request_path
            ) {
                $base_url = 0;
            }
        }
        
        if (!$base_url) {
            // Erstellt die Basis-URL
            $base_url = $request_origin . rtrim($uri, '/');

            // Falls der Port 4221 ist, wird die URL aus einer Datei gelesen
            if ($port == 4221) {
                $base_href_file = dbx()->os_path(dbx()->get_file_dir() . 'base_href.cfg');
                
                if (file_exists($base_href_file)) {
                    $base_url = trim(preg_replace('/[\x00-\x1F\x7F]/', '', file_get_contents($base_href_file)));
                }
            }

            // Ersetzt Backslashes durch Slashes und sorgt für einen abschließenden Slash
            $base_url = str_replace('\\', '/', rtrim($base_url, '\\'));
            if (!in_array(substr($base_url, -1), ['/', '?'])) {
                $base_url .= '/';
            }

            dbx()->set_remember_var('base_url', $base_url, 'dbx');
        }

        return $base_url;
    }




  /**
   * Erstellt eine minimale Default-Konfiguration fuer ein Modul.
   *
   * @param string $modul Modulname.
   * @return array Erzeugte Konfiguration.
   */
  function create_new_cfg($modul='dbx') {
     if ($modul == 'dbx') {
      $config['host']='localhost';
      $config['name']='';
      $config['user']='root';
      $config['password']='';
      $config['port']='';
      $config['default_lng']          ='de';
      $config['accessible_lng']       ='de,en,es';
      $config['default_color']        ='blau';
      $config['default_design_user']  ='lda';
      $config['default_design_admin'] ='_admin';
      $config['cache']=0;
      $config['intro']=0;
      $config['trace']=0;
      $config['construct']=0;
      $config['secure']='secure-key';
      $config['ok']=0;
      
     } else {
       // create Modul cfg; 
     }
     dbx()->set_config($modul,$config);
     return $config;
  }

  /**
   * Zerlegt REQUEST_URI in Pfad und Query-Parameter.
   *
   * @return string Request-Pfad ohne Querystring.
   */
  function check_uri() {
    $uri='';
    $request = $_SERVER['REQUEST_URI'];

    // Zerlege die URL in ihre Komponenten
    $url_components = parse_url($request);

    // Speichere den Pfad in $_GET mit dem Schlüssel 'dbx_request_path'
    if (isset($url_components['path'])) {
        $uri = $url_components['path'];
    }

    // Zerlege die Query-Parameter und speichere sie in $_GET
    if (isset($url_components['query'])) {
        parse_str($url_components['query'], $query_params);
        foreach ($query_params as $key => $value) {
            $_GET[$key] = $value;
        }
    }
    return $uri; 
}


/**
 * Parst Permalink- und Standard-URLs in Pfad + Query-Parameter.
 *
 * Unterstuetzt:
 * - home/seite?dbx_modul=x
 * - home/seite&dbx_design=test
 * - ?dbx_modul=x
 * - angeklebte dbx_* / key_value-Parameter im Pfad
 *
 * @param string $url
 * @return array{permalink:string,params:array<string,string>,fragment:string}
 */
function parse_route_url($url) {
  $url = trim(str_replace('&amp;', '&', (string) $url));

  $fragment = '';
  $hashPos = strpos($url, '#');
  if ($hashPos !== false) {
     $fragment = substr($url, $hashPos);
     $url = substr($url, 0, $hashPos);
  }

  $path = $url;
  $params = array();

  if (substr($url, 0, 1) === '?') {
     $query = substr($url, 1);
     if ($query !== '') {
        parse_str($query, $params);
     }
     return array(
        'permalink' => '',
        'params' => $this->normalize_route_params($params),
        'fragment' => $fragment,
     );
  }

  $delimPos = false;
  foreach (array('?', '&') as $delimiter) {
     $pos = strpos($url, $delimiter);
     if ($pos !== false && ($delimPos === false || $pos < $delimPos)) {
        $delimPos = $pos;
     }
  }

  if ($delimPos !== false) {
     $path = substr($url, 0, $delimPos);
     $query = substr($url, $delimPos + 1);
     if ($query !== '') {
        parse_str($query, $params);
     }
  }

  $path = $this->peel_glued_params_from_path($path, $params);

  return array(
     'permalink' => str_replace('\\', '/', $path),
     'params' => $this->normalize_route_params($params),
     'fragment' => $fragment,
  );
}

  /**
   * Baut eine kanonische Route-URL aus Permalink, Query-Parametern und Fragment.
   *
   * Beispiel:
   *
   * ```php
   * $url = dbx()->build_route_url('kontakt', ['dbx_lng' => 'de'], '#form');
   * ```
   *
   * Ergebnis: `kontakt?dbx_lng=de#form`
   *
   * @param string $permalink Relativer Permalink ohne fuehrenden Slash.
   * @param array $params Query-Parameter als Key/Value-Array.
   * @param string $fragment Optionales Fragment inklusive `#`.
   * @return string Zusammengesetzte Route-URL.
   */
  function build_route_url($permalink, $params = array(), $fragment = '') {
     $permalink = str_replace('\\', '/', trim((string) $permalink));
     $permalink = trim($permalink, '/');
     $params = $this->normalize_route_params($params);

     if ($permalink === '') {
        if (empty($params)) {
           return '?' . $fragment;
        }
        return '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986) . $fragment;
     }

     if (empty($params)) {
        return $permalink . $fragment;
     }

     return $permalink . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986) . $fragment;
  }

  /**
   * Haengt Parameter an eine vorhandene Route-URL an.
   *
   * Vorhandene Query-Parameter bleiben erhalten und werden mit den neuen
   * Werten ueberschrieben, wenn derselbe Key erneut gesetzt wird.
   *
   * Beispiel:
   *
   * ```php
   * $url = dbx()->append_route_params('kontakt?dbx_lng=de', ['dbx_edit' => 1]);
   * ```
   *
   * Ergebnis: `kontakt?dbx_lng=de&dbx_edit=1`
   *
   * @param string $url Vorhandene Route-URL.
   * @param array $params Zusaetzliche Query-Parameter.
   * @return string Normalisierte Route-URL.
   */
  function append_route_params($url, $params = array()) {
     $route = $this->parse_route_url($url);
     foreach ($params as $key => $value) {
        $route['params'][(string) $key] = (string) $value;
     }
     return $this->build_route_url($route['permalink'], $route['params'], $route['fragment']);
  }

  /**
   * Ergaenzt fehlende Routenwerte aus dem aktuellen Requestkontext.
   *
   * Relative Action-Suffixe wie `&dbx_do=row_delete` erhalten so denselben
   * Modul-/Run-Kontext wie eine vollstaendige URL. Bei einem explizit anderen
   * Zielmodul werden keine Run-Werte des aktuellen Moduls uebernommen.
   *
   * @param array $params URL-/Requestparameter.
   * @return array Effektive Parameter.
   */
  private function effective_action_params(array $params): array {
     $currentModul = trim((string)dbx()->get_system_var('dbx_modul', '', 'parameter'));
     $modul = trim((string)($params['dbx_modul'] ?? ''));

     if ($modul === '') {
        $modul = $currentModul;
        if ($modul !== '') {
           $params['dbx_modul'] = $modul;
        }
     }

     if ($modul === $currentModul || $currentModul === '') {
        foreach (array('dbx_run1', 'dbx_run2', 'dbx_run3') as $key) {
           if (!array_key_exists($key, $params) || (string)$params[$key] === '') {
              $value = dbx()->get_system_var($key, '', 'parameter');
              if ((string)$value !== '') {
                 $params[$key] = $value;
              }
           }
        }
     }

     return $params;
  }

  /**
   * Normalisiert Werte fuer den kanonischen Action-Scope.
   *
   * @param mixed $value Skalarer oder verschachtelter Binding-Wert.
   * @return mixed JSON-stabiler Wert.
   */
  private function normalize_action_scope_value($value) {
     if (!is_array($value)) {
        if ($value === null) {
           return '';
        }
        if (is_bool($value)) {
           return $value ? '1' : '0';
        }
        return (string)$value;
     }

     $normalized = array();
     foreach ($value as $key => $item) {
        $normalized[(string)$key] = $this->normalize_action_scope_value($item);
     }
     ksort($normalized, SORT_STRING);
     return $normalized;
  }

  /**
   * Loest Binding-Definitionen gegen die effektiven URL-Parameter auf.
   *
   * Numerische Eintraege benennen einen Parameter (`['rid']`), assoziative
   * Eintraege koennen einen bereits bekannten Wert direkt binden
   * (`['rid' => 17]`).
   *
   * @param array $bindings Binding-Definition.
   * @param array $params Effektive Parameter.
   * @return array Aufgeloeste Bindings.
   */
  private function resolve_action_bindings(array $bindings, array $params): array {
     $resolved = array();

     foreach ($bindings as $key => $value) {
        if (is_int($key)) {
           $name = trim((string)$value);
           if ($name === '') {
              continue;
           }
           $resolved[$name] = $params[$name] ?? '';
           continue;
        }

        $name = trim((string)$key);
        if ($name !== '') {
           $resolved[$name] = $value;
        }
     }

     ksort($resolved, SORT_STRING);
     return $resolved;
  }

  /**
   * Baut den kanonischen Scope einer Link-Aktion.
   *
   * Der Scope bindet Modul, Run-Kontext, Aktionsname und deklarierte IDs.
   * Transportparameter wie Ajax, Fensterziel oder das Token selbst sind
   * absichtlich nicht enthalten. Dadurch funktioniert derselbe Link als
   * normaler GET und als Ajax-POST innerhalb eines Reports.
   *
   * @param string $url Ziel-URL.
   * @param string $action Kanonischer Aktionsname.
   * @param array $bindings Parameterliste oder konkrete Binding-Werte.
   * @return string Kompakter Scope fuer dbxApi::action_token().
   */
  public function action_scope_for_url($url, $action, $bindings = array()): string {
     $route = $this->parse_route_url((string)$url);
     $params = $this->effective_action_params((array)($route['params'] ?? array()));
     return $this->action_scope_from_params($params, (string)$action, (array)$bindings);
  }

  /**
   * Interne Scope-Erzeugung aus bereits normalisierten Requestparametern.
   *
   * @param array $params Effektive Parameter.
   * @param string $action Kanonischer Aktionsname.
   * @param array $bindings Binding-Definition.
   * @return string
   */
  private function action_scope_from_params(array $params, string $action, array $bindings): string {
     $params = $this->effective_action_params($params);
     $route = array();
     foreach (array('dbx_modul', 'dbx_run1', 'dbx_run2', 'dbx_run3') as $key) {
        $route[$key] = $this->normalize_action_scope_value($params[$key] ?? '');
     }

     $payload = array(
        'version' => '1',
        'action' => trim($action),
        'route' => $route,
        'bind' => $this->normalize_action_scope_value(
           $this->resolve_action_bindings($bindings, $params)
        ),
     );

     $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
     if (!is_string($json)) {
        $json = serialize($payload);
     }

     return 'route-v1:' . hash('sha256', $json);
  }

  /**
   * Prueft eine einzelne Match-Definition gegen Routenparameter.
   *
   * Ein Arraywert bedeutet "einer dieser Werte"; `*` verlangt nur, dass der
   * Parameter vorhanden und nicht leer ist.
   *
   * @param array $match Match-Definition.
   * @param array $params Effektive Parameter.
   * @return bool
   */
  private function action_route_matches(array $match, array $params): bool {
     foreach ($match as $key => $expected) {
        $actual = $params[(string)$key] ?? '';
        $allowed = is_array($expected) ? $expected : array($expected);
        $matched = false;

        foreach ($allowed as $candidate) {
           if ((string)$candidate === '*') {
              $matched = (string)$actual !== '';
           } elseif ((string)$actual === (string)$candidate) {
              $matched = true;
           }

           if ($matched) {
              break;
           }
        }

        if (!$matched) {
           return false;
        }
     }

     return true;
  }

  /**
   * Ermittelt eine optionale Policy fuer ungewoehnliche Legacy-Aktionen.
   *
   * Konfigurationsformat:
   *
   * ```php
   * $config['action_routes']['job_cancel'] = array(
   *    'match' => array('dbx_run1' => 'control', 'dbx_do' => 'cancel'),
   *    'bind'  => array('job_id'),
   * );
   * ```
   *
   * `delete`/`save` plus `rid` und dbxReport-Standardaktionen benoetigen
   * diese Kompatibilitaetskonfiguration nicht.
   *
   * @param array $params Effektive Parameter.
   * @return array Leeres Array oder Policy.
   */
  private function configured_action_policy(array $params): array {
     $modul = trim((string)($params['dbx_modul'] ?? ''));
     if ($modul === '') {
        return array();
     }

     if (!array_key_exists($modul, $this->actionRouteCache)) {
        $routes = dbx()->get_config($modul, 'action_routes', array());
        $this->actionRouteCache[$modul] = is_array($routes) ? $routes : array();
     }

     $routes = $this->actionRouteCache[$modul];
     if (!is_array($routes)) {
        return array();
     }

     foreach ($routes as $name => $definition) {
        if (!is_array($definition)
            || (array_key_exists('enabled', $definition) && !$definition['enabled'])) {
           continue;
        }

        $match = $definition['match'] ?? array();
        if (!is_array($match) || !$match || !$this->action_route_matches($match, $params)) {
           continue;
        }

        $policyName = trim((string)($definition['action'] ?? $name));
        if ($policyName === '') {
           $policyName = 'action';
        }

        return array(
           'source' => 'module',
           'action' => $modul . '.' . $policyName,
           'bind' => is_array($definition['bind'] ?? null)
              ? array_values($definition['bind'])
              : array(),
           'match' => $match,
        );
     }

     return array();
  }

  /**
   * Ermittelt eine systemweit bekannte dbxReport-Policy.
   *
   * @param array $params Effektive Parameter.
   * @return array Leeres Array oder Policy.
   */
  private function report_action_policy(array $params): array {
     foreach (array('dbx_do', 'dbx_run3', 'dbx_run2') as $parameter) {
        $code = strtolower(trim((string)($params[$parameter] ?? '')));
        if ($code === '' || !isset(self::REPORT_ACTION_POLICIES[$code])) {
           continue;
        }

        $definition = self::REPORT_ACTION_POLICIES[$code];
        return array(
           'source' => 'dbxReport',
           'action' => (string)$definition['action'],
           'bind' => (array)$definition['bind'],
           'match' => array($parameter => $code),
        );
     }

     return array();
  }

  /**
   * Erkennt mutierende Standardaktionen direkt aus der dbx-URL.
   *
   * `delete` oder `save` werden nur zusammen mit einer nicht leeren `rid`
   * als Mutation behandelt. Die RID wird automatisch an den Token-Scope
   * gebunden. Damit benötigen normale Navigation und neue Formulare keine
   * zusätzliche Modulkonfiguration.
   *
   * @param array $params Effektive Parameter.
   * @return array Leeres Array oder automatisch erkannte Policy.
   */
  private function automatic_rid_action_policy(array $params): array {
     if (trim((string)($params['rid'] ?? '')) === '') {
        return array();
     }

     foreach (array('dbx_do', 'dbx_run3', 'dbx_run2', 'dbx_run1') as $parameter) {
        $code = strtolower(trim((string)($params[$parameter] ?? '')));
        if ($code === '') {
           continue;
        }

        $parts = preg_split('/[^a-z0-9]+/', $code, -1, PREG_SPLIT_NO_EMPTY);
        foreach (self::AUTOMATIC_RID_ACTIONS as $keyword => $action) {
           if (!in_array($keyword, is_array($parts) ? $parts : array(), true)) {
              continue;
           }

           return array(
              'source' => 'automatic',
              'action' => $action,
              'bind' => array('rid'),
              'match' => array(
                 $parameter => $code,
                 'rid' => '*',
              ),
           );
        }
     }

     return array();
  }

  /**
   * Erkennt die schreibenden JSON-Endpunkte des dbxReport-Grid-Modus.
   *
   * Der Marker wird aus der eigentlichen Route abgeleitet und ist deshalb
   * nicht durch Weglassen eines zusaetzlichen Queryparameters umgehbar.
   * Gueltige Konventionen sind `*_grid_<aktion>` sowie die historischen
   * dbxSchema-Endpunkte `data_<aktion>` und `fields_<aktion>`.
   *
   * @param array $params Effektive URL-/Requestparameter.
   * @return array Leeres Array oder Grid-Policy.
   */
  private function automatic_grid_action_policy(array $params): array {
     $actions = array('save', 'insert', 'delete', 'sort', 'sync');
     $contextBindings = array('rid', 'id', 'cid', 'iid', 'modul', 'dd', 'fd', 'xmodul');

     foreach (array('dbx_run3', 'dbx_run2', 'dbx_run1') as $parameter) {
        $code = strtolower(trim((string)($params[$parameter] ?? '')));
        if ($code === '') {
           continue;
        }

        $parts = preg_split('/[^a-z0-9]+/', $code, -1, PREG_SPLIT_NO_EMPTY);
        $parts = is_array($parts) ? $parts : array();
        $gridLike = in_array('grid', $parts, true)
           || (isset($parts[0]) && in_array($parts[0], array('data', 'fields'), true));
        if (!$gridLike) {
           continue;
        }

        foreach ($actions as $action) {
           if (!in_array($action, $parts, true)) {
              continue;
           }

           $bindings = array();
           foreach ($contextBindings as $binding) {
              if (array_key_exists($binding, $params)
                  && trim((string)$params[$binding]) !== '') {
                 $bindings[] = $binding;
              }
           }

           return array(
              'source' => 'dbxReport',
              'action' => 'dbxReport.grid_' . $action,
              'bind' => $bindings,
              'match' => array($parameter => $code),
           );
        }
     }

     return array();
  }

  /**
   * Loest die gueltige Action-Policy fuer einen Parametersatz auf.
   *
   * Explizite Moduldefinitionen haben Vorrang vor den kompatiblen
   * dbxReport-Standardregeln. Danach werden `delete` und `save` zusammen mit
   * `rid` automatisch erkannt. Ungewoehnliche Alt-Routen koennen weiterhin
   * ueber `action_routes` beschrieben werden.
   *
   * @param array $params URL-/Requestparameter.
   * @return array Leeres Array oder vollstaendige Policy inklusive Scope.
   */
  private function resolve_action_policy(array $params): array {
     $params = $this->effective_action_params($params);
     $policy = $this->configured_action_policy($params);
     if (!$policy) {
        $policy = $this->report_action_policy($params);
     }
     if (!$policy) {
        $policy = $this->automatic_grid_action_policy($params);
     }
     if (!$policy) {
        $policy = $this->automatic_rid_action_policy($params);
     }
     if (!$policy) {
        return array();
     }

     $policy['bindings'] = $this->resolve_action_bindings((array)$policy['bind'], $params);
     $policy['scope'] = $this->action_scope_from_params(
        $params,
        (string)$policy['action'],
        (array)$policy['bindings']
     );
     $policy['params'] = $params;
     return $policy;
  }

  /**
   * Liefert die Action-Policy einer URL.
   *
   * Reine Navigation ergibt ein leeres Array und wird von
   * dbxApi::action_url() unveraendert zurueckgegeben.
   *
   * @param string $url Zu pruefende URL.
   * @return array
   */
  public function action_policy_for_url($url): array {
     $route = $this->parse_route_url((string)$url);
     return $this->resolve_action_policy((array)($route['params'] ?? array()));
  }

  /**
   * Sammelt den aktuellen Request mit derselben GET-/POST-Prioritaet wie dbxRequest.
   *
   * @return array
   */
  private function current_action_request_params(): array {
     $params = is_array($_GET ?? null) ? $_GET : array();
     if (is_array($_POST ?? null)) {
        $params = array_replace($params, $_POST);
     }

     foreach (array('dbx_modul', 'dbx_run1', 'dbx_run2', 'dbx_run3') as $key) {
        $value = dbx()->get_system_var($key, '', 'parameter');
        if ((string)$value !== '') {
           $params[$key] = $value;
        }
     }

     return $this->effective_action_params($params);
  }

  /**
   * Liefert die automatisch erkannte Policy des aktuellen Requests.
   *
   * @return array
   */
  public function current_action_policy(): array {
     return $this->resolve_action_policy($this->current_action_request_params());
  }

  /**
   * Prueft den Action-Token des aktuellen Requests ohne Modulcode auszufuehren.
   *
   * Requests ohne Action-Policy sind normale Navigation bzw. normale
   * dbxForm-POSTs und gelten hier als gueltig. Deren eigener Formularschutz
   * wird weiterhin ausschliesslich von dbxForm ausgewertet.
   *
   * @param array $policy Optional bereits ermittelte Policy.
   * @return bool
   */
  public function current_action_request_is_valid(array $policy = array()): bool {
     if (!$policy) {
        $policy = $this->current_action_policy();
     }
     if (!$policy) {
        return true;
     }

     $token = '';
     if (isset($_GET['dbx_token'])) {
        $token = (string)$_GET['dbx_token'];
     }
     if (isset($_POST['dbx_token'])) {
        $token = (string)$_POST['dbx_token'];
     }

     return dbx()->check_action_token((string)($policy['scope'] ?? ''), $token);
  }

  /**
   * Prefix fuer Template-Links wie {self}dbx_design=desktop.
   *
   * @param string $url
   * @return string
   */
  function route_param_prefix($url) {
     $route = $this->parse_route_url($url);
     if ($route['permalink'] === '') {
        return empty($route['params'])
           ? '?'
           : $this->build_route_url('', $route['params']) . '&';
     }

     if (empty($route['params'])) {
        return $route['permalink'] . '?';
     }

     return $this->build_route_url($route['permalink'], $route['params']) . '&';
  }

  /**
   * Liefert den Routen-Teil des aktuellen Requests (Permalink + Query).
   *
   * @param string $baseUri
   * @return string
   */
  function get_request_route_string($baseUri = '') {
     $requestUri = $_SERVER['REQUEST_URI'] ?? '';
     if ($requestUri === '') {
        return '';
     }

     if ($baseUri === '') {
        $baseUri = $this->get_base_uri();
     }

     $path = parse_url($requestUri, PHP_URL_PATH) ?? '';
     $path = str_replace('\\', '/', $path);
     $baseUri = str_replace('\\', '/', $baseUri);

     if ($baseUri !== '/' && $baseUri !== '' && strpos($path, $baseUri) === 0) {
        $path = substr($path, strlen($baseUri));
     }

     $route = ltrim($path, '/');
     $query = parse_url($requestUri, PHP_URL_QUERY);

     if ($query !== null && $query !== '') {
        if ($route === '') {
           return '?' . $query;
        }
        if (strpos($route, '?') === false && strpos($route, '&') === false) {
           return $route . '?' . $query;
        }
        return $route . '&' . $query;
     }

     return $route;
  }

  /**
   * Entfernt unerwuenschte Parameter aus einem Param-Array.
   *
   * @param array $params Query-Parameter als Key/Value-Array.
   * @param string $unwanted Kommagetrennte Zusatzparameter, die entfernt werden.
   * @return array Bereinigte Query-Parameter.
   */
  function strip_unwanted_route_params($params, $unwanted = '') {
     $remove = array_merge(
        explode(',', (string) $unwanted),
        $this->memory_switch_params(),
        array('dbx_token')
     );

     foreach ($remove as $key) {
        $key = trim($key);
        if ($key === '') {
           continue;
        }
        unset($params[$key]);
     }

     return $params;
  }

  /**
   * Baut die aktuelle Self-URL ohne unerwuenschte DBX-Steuerparameter.
   *
   * @param string $permalink Aktueller Permalink/Pfad.
   * @param string $unwanted Kommagetrennte Zusatzparameter, die entfernt werden.
   * @return string Bereinigte Self-URL.
   */
function get_self_url($permalink,$unwanted) {
  $route = $this->parse_route_url($this->get_request_route_string($this->get_base_uri()));
  $params = $this->strip_unwanted_route_params($route['params'], $unwanted);

  if ($permalink !== '') {
     $route['permalink'] = str_replace('\\', '/', trim((string) $permalink, '/'));
  }

  return $this->build_route_url($route['permalink'], $params, $route['fragment']);
}

  /**
   * Liefert die Remember-/Memory-Schalter, die nicht in Permalinks haengen duerfen.
   *
   * @return array<int,string>
   */
  function memory_switch_params() {
     return array('dbx_edit', 'dbx_design', 'dbx_color', 'dbx_lng');
  }

  /**
   * Bereinigt eine Self-/Menu-URL von Memory-Schaltern in Pfad und Query.
   *
   * @param string $url
   * @return string
   */
  function normalize_self_url($url) {
     $route = $this->parse_route_url($url);
     $route['params'] = $this->strip_unwanted_route_params($route['params']);
     return $this->build_route_url($route['permalink'], $route['params'], $route['fragment']);
  }

  /**
   * Entfernt angeklebte Query-Parameter aus einem Permalink-Pfad.
   *
   * @param string $path Permalink-Pfad, der angeklebte Parameter enthalten kann.
   * @param array $params Zielarray, in das gefundene Parameter geschrieben werden.
   * @return string Bereinigter Pfad ohne angeklebte Query-Parameter.
   */
  function peel_glued_params_from_path($path, &$params = array()) {
     $path = str_replace('\\', '/', (string) $path);
     $changed = true;

     while ($changed) {
        $changed = false;

        if (preg_match('/^(.*)[?&]([a-zA-Z_][a-zA-Z0-9_]*)=([^\/&?#]+)$/', $path, $match)) {
           $path = $match[1];
           $params[$match[2]] = rawurldecode($match[3]);
           $changed = true;
           continue;
        }

        if (preg_match('/^(.*?)([a-zA-Z_][a-zA-Z0-9_]*)=([^\/&?#]+)$/', $path, $match)) {
           if (preg_match('/^dbx_/', $match[2]) || strpos($match[2], '_') !== false) {
              $path = $match[1];
              $params[$match[2]] = rawurldecode($match[3]);
              $changed = true;
           }
        }
     }

     return rtrim($path, '-&?/');
  }

  /**
   * Normalisiert Query-Parameter auf string-Werte.
   *
   * @param array $params Unnormalisierte Query-Parameter.
   * @return array Query-Parameter mit string-Werten.
   */
  function normalize_route_params($params) {
     $normalized = array();

     if (!is_array($params)) {
        return $normalized;
     }

     foreach ($params as $key => $value) {
        if ($key === '' || $value === null) {
           continue;
        }
        if (is_array($value)) {
           $normalized[(string) $key] = implode(',', $value);
        } else {
           $normalized[(string) $key] = (string) $value;
        }
     }

     return $normalized;
  }


  /**
   * Liefert das Basisverzeichnis des Entry Points als URI.
   *
   * @return string URI-Pfad, z.B. `/dbxapp/`.
   */
  function get_base_uri() {
    // Versuche zuerst, den URI aus $_SERVER['SCRIPT_NAME'] zu holen
    if (!empty($_SERVER['SCRIPT_NAME'])) {
        $uri = $_SERVER['SCRIPT_NAME'];
    } 
    // Fallback auf $_SERVER['PHP_SELF'], falls $_SERVER['SCRIPT_NAME'] nicht verfügbar ist
    elseif (!empty($_SERVER['PHP_SELF'])) {
        $uri = $_SERVER['PHP_SELF'];
    } 
    // Als letzte Option, versuche den URI aus $_SERVER['REQUEST_URI'] ohne Query-Parameter
    elseif (!empty($_SERVER['REQUEST_URI'])) {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    } 
    // Falls keine der Optionen verfügbar ist, gib einen leeren String zurück oder eine Fehlermeldung
    else {
        $uri = ''; // Oder ein bestimmter Wert, z.B. "/unknown" oder ein Fehlerwert
    }
    
    // Entferne den Dateinamen, um nur das Verzeichnis zurückzugeben
    $uri=dirname($uri);
    if ($uri=='\\') $uri='/';
    $uri = str_replace('\\', '/', $uri);
    return $uri;
  }



  /**
   * Ermittelt den Permalink-Anteil hinter der Basis-URI.
   *
   * @param string $uri Basis-URI.
   * @return string Permalink ohne fuehrenden Slash.
   */
  function get_permalink($uri) {
    $route = $this->parse_route_url($this->get_request_route_string($uri));
    return $route['permalink'];
  }


  /**
   * Spiegelt Query-Parameter aus REQUEST_URI nach $_GET.
   *
   * Sinnvoll fuer Permalink-/Rewrite-Situationen, in denen PHP die Parameter
   * nicht bereits normal in $_GET bereitstellt.
   *
   * @return void
   */
  function setRequestUriToGet() {
    $route = $this->parse_route_url($this->get_request_route_string($this->get_base_uri()));
    if (!empty($route['params'])) {
       $_GET = array_merge($_GET, $route['params']);
    }
  }


  /**
   * Normalisiert den aktuellen HTTP-Request fuer den Kernel.
   *
   * Diese Methode ist einer der ersten Frontcontroller-Schritte. Sie liest
   * JSON-Body, klassische GET/POST-Parameter, DBX-Ajax-Hilfsfelder und
   * Permalink-Kontext ein und schreibt daraus die zentralen Systemvariablen:
   * `dbx_permalink`, `dbx_self_url`, `dbx_base_url`, `dbx_base_uri`,
   * `dbx_window`, `dbx_ajax` und `dbx_go`.
   *
   * Beispiel:
   * ```php
   * $web = dbx()->get_system_obj('dbxWebApp');
   * $web->check_request();
   * ```
   *
   * @return void
   */
  function check_request()  {
    //dbx_debug("dbxApp check request");
    //dbx_debug('#INCOME-POST',$_POST);
    //dbx_debug('#INCOME-GET' ,$_GET);
    //dbx_debug("SERVER"      ,$_SERVER);

    // JSON-Body einlesen (z. B. von fetch)
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    // Falls gültiges JSON → $_POST damit befüllen
    if (is_array($data)) {
        $_POST = array_merge($_POST, $data);
    }


    
    $pvx=array();   $gvx=array();



    if (isset($_POST['dbx_get'])) { // Ajax Formular
      $get = $_POST['dbx_get'];
      $get = ltrim($get, '?');
      parse_str($get, $gvx);
      $_GET=array_merge($_GET, $gvx);
      unset($_POST['dbx_get']);
    }

    if (isset($_POST['dbx_post'])) { // Ajax Formular
      $post= $_POST['dbx_post'];
      $post= ltrim($post, '?');
      parse_str($post, $pvx);
      $_POST=array_merge($_POST, $pvx);
      unset($_POST['dbx_post']);
    }

    /*
    if (!empty($_GET)) {
      // Hol den ersten Schlüssel und den ersten Wert
      $firstKey = array_key_first($_GET);
      $firstValue = $_GET[$firstKey];
  
      // Prüfen, ob das erste Zeichen des Schlüssels ein '?' ist
      if (strpos($firstKey, '?') === 0) {
          // Entferne den Eintrag mit dem alten Schlüssel
          dbx()->debug("FIX-GET Key ($firstKey)");
          unset($_GET[$firstKey]);
          // Entferne das '?' am Anfang des Schlüssels
          $newKey = ltrim($firstKey, '?');
          // Füge den Wert mit dem neuen Schlüssel hinzu
          $_GET[$newKey] = $firstValue;
      }
    }
    */
    
    


    $base_uri =$this->get_base_uri();
    $route = $this->parse_route_url($this->get_request_route_string($base_uri));
    if (!empty($route['params'])) {
       $_GET = array_merge($route['params'], $_GET);
    }

    //dbx_debug('#OUT-GET POST' ,$_GET,$_POST);
    
    $base_url =$this->get_base_url($base_uri);
    $permalink=$this->get_permalink($base_uri);
    $self_url =$this->get_self_url($permalink,'dbx_ajax,dbx_window,dbx_target,dbx_go');
    $go       = dbx()->get_request_var('dbx_go'    ,0,'parameter');
    $ajax     = dbx()->get_request_var('dbx_ajax'  ,0,'int');
    $window   = dbx()->get_request_var('dbx_window',0,'int'); 
    

    dbx()->debug("#SYS Self-url=($self_url) base-Url=($base_url) base_uri=($base_uri) Perma=($permalink)");

    //dbx_debug('#-POST',$_POST);
    //dbx_debug('#-GET' ,$_GET);

   
    
    dbx()->set_system_var('dbx_permalink',$permalink);
    dbx()->set_system_var('dbx_self_url' ,$self_url);
    dbx()->set_system_var('dbx_base_url' ,$base_url);  
    dbx()->set_system_var('dbx_base_uri' ,$base_uri);  
    dbx()->set_system_var('dbx_window'   ,$window);
    dbx()->set_system_var('dbx_ajax'     ,$ajax);
    dbx()->set_system_var('dbx_go'       ,$go);


  }

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

    $routeKeys = array(
      'dbx_modul',
      'dbx_run1',
      'dbx_run2',
      'dbx_action',
      'dbx_ajax',
      'dbx_window',
      'cid',
      'dbx_cid',
    );
    foreach ($routeKeys as $key) {
      if (trim((string)dbx()->get_request_var($key, '', '*')) !== '') {
        return '';
      }
    }

    return rtrim((string)dbx()->get_base_url(), '/') . '/';
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

    $redirects = dbx()->get_config('dbxContent', 'permalink_redirects', array());
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
   * Prueft, ob eine Dateiendung als direkt auslieferbare Ressource gilt.
   *
   * @param string $ext Dateiendung ohne Punkt.
   * @return int 1 = Ressource, 0 = keine Ressource.
   */
  function get_is_resorce($ext) {
     $ext = strtolower(ltrim(trim((string)$ext), '.'));
     return isset(self::RESOURCE_MIME_TYPES[$ext]) ? 1 : 0;
  }

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

    $refererHost = (string)(parse_url($referer, PHP_URL_HOST) ?? '');
    $requestAuthority = trim((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    $requestHost = (string)(parse_url('http://' . $requestAuthority, PHP_URL_HOST) ?? '');

    $normalize = static function (string $host): string {
      $host = rtrim(strtolower(trim($host)), '.');
      return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    };

    $refererHost = $normalize($refererHost);
    $requestHost = $normalize($requestHost);
    return $refererHost !== '' && $requestHost !== '' && hash_equals($requestHost, $refererHost);
  }

  /**
   * Loest einen Permalink sicher auf eine lokale Ressourcendatei auf.
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

    $baseCheck = rtrim(str_replace('\\', '/', strtolower($base)), '/') . '/';
    $fileCheck = str_replace('\\', '/', strtolower($file));

    if (strpos($fileCheck, $baseCheck) !== 0) {
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



  /**
   * Stellt die zentrale dbx-Konfiguration sicher.
   *
   * @return void
   */
  function check_config() {
    $config=dbx()->get_config('dbx');
    if (!is_array($config)) { 
      $config=$this->create_new_cfg('dbx');
    } else {
      if (!isset($config['ok'])) $config=$this->create_new_cfg('dbx');
    }

    $install = (int)($config['install'] ?? 1);
    $ok = (int)($config['ok'] ?? 0);
    $timezone = trim((string)($config['timezone'] ?? 'Europe/Berlin'));
    if ($timezone !== '' && in_array($timezone, timezone_identifiers_list(), true)) {
      date_default_timezone_set($timezone);
    }

    dbx()->set_system_var('dbx_install', $install);
    dbx()->set_system_var('dbx_construct', (int)($config['construct'] ?? 0));
    if (!$ok) {
      dbx()->set_system_var('dbx_install', 1);
    }
  }



  /**
   * Behandelt fehlende statische Ressourcen.
   *
   * Existiert die Ressource doch im Projektpfad, wird sie direkt ausgeliefert.
   * Andernfalls wird der Fehlzugriff in `dbxMissing` gezaehlt.
   *
   * @return bool True, wenn der Request als fehlende Ressource behandelt wurde.
   */
  function check_missing(): bool {
    // Der Wert wurde bereits von check_request() aus dem Request-Pfad
    // abgeleitet. Hier nicht erneut als "parameter+/" filtern, weil sonst
    // gueltige URL-Kodierung wie %2E (Punkt) verloren geht.
    $permalink=dbx()->get_system_var('dbx_permalink','','*');

    if (trim((string)$permalink) === '' || $this->is_dynamic_file_route($permalink)) {
      return false;
    }

    $ext = $this->get_resource_extension($permalink);
    if (!$this->get_is_resorce($ext)) {
      return false;
    }

    $resourceFile = $this->get_resource_file($permalink);
    if ($resourceFile) {
      dbx()->debug("## resource exists, not missing ($permalink) file=($resourceFile)");
      $this->send_resource_file($resourceFile, $ext);
      return false;
    }

    http_response_code(404);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');

    dbx()->debug("## check missing ($permalink)");
    $ok = 0;
    if ($this->has_internal_resource_referer()) {
      $ok = dbx()->log_missing($permalink);
    } else {
      dbx()->debug("## missing resource not logged: no internal referer ($permalink)");
    }
    dbx()->debug("#MISSING# ($permalink) Ext=($ext) ok=($ok)");
    return true;
  }

  /**
   * Uebernimmt persistente UI-/Systemwerte aus Remember in den Request.
   *
   * Betroffen sind Design, Edit-Modus, Sprache und Farbschema.
   *
   * @return void
   */
  public function check_remember() {
    // #Session switch values 
    //$page  =dbx()->get_remember_var('dbx_page'  ,'default','dbx');
    $design=dbx()->get_remember_var('dbx_design','user'   ,'dbx');
    $edit  =dbx()->get_remember_var('dbx_edit'  ,0        ,'dbx');
    $lng   =dbx()->get_remember_var('dbx_lng'   ,'de'     ,'dbx');
    $defaultColor = dbx()->normalize_skin(
      (string)dbx()->get_config('dbx', 'default_color', 'blau'),
      (string)$design
    );
    $color = dbx()->get_remember_var('dbx_color', $defaultColor, 'dbx');

    //$page  =dbx()->get_request_var('dbx_page'  ,$page);
    $design=dbx()->get_request_var('dbx_design',$design);
    $edit  =dbx()->get_request_var('dbx_edit'  ,$edit);
    $lng   =dbx()->get_request_var('dbx_lng'   ,$lng);
    $color = dbx()->normalize_skin(
      (string)dbx()->get_request_var('dbx_color', $color),
      (string)$design
    );

    // Kompatibilitaet fuer vor der Umbenennung gespeicherte Designwerte und
    // alte Links: fleurop wurde in flowers umbenannt.
    if (strtolower(trim((string)$design)) === 'fleurop') {
      $design = 'flowers';
    }
   
    //dbx()->set_remember_var('dbx_page'  ,$page  ,'dbx');
    dbx()->set_remember_var('dbx_design',$design,'dbx');
    dbx()->set_remember_var('dbx_edit'  ,$edit  ,'dbx');  
    dbx()->set_remember_var('dbx_lng'   ,$lng   ,'dbx');  
    dbx()->set_remember_var('dbx_color' ,$color ,'dbx');  

    dbx()->set_system_var('dbx_design', $design);
    dbx()->set_system_var('dbx_edit'  , $edit);
    dbx()->set_system_var('dbx_lng'   , $lng);
    dbx()->set_system_var('dbx_color' , $color);
    dbx()->set_system_var('dbx_last_editor_tpl_paths', array());

  }

  /**
   * Liefert den Content-Permalink-Modus.
   *
   * @return string `cms` oder `content`.
   */
  private function get_content_permalink_mode(): string {
     return dbx()->get_content_permalink_mode();
  }

  /**
   * Liefert den aktiven Content-Root aus Request oder Konfiguration.
   *
   * @return int Root-ID.
   */
  private function get_content_root(): int {
     $requestRoot = dbx()->get_request_var('root', '', 'int');

     if ($requestRoot !== '') {
        return (int) $requestRoot;
     }

     $configRoot = dbx()->get_config('dbxContent', 'root');
     if ($configRoot === 'undef' || $configRoot === '') {
        return 0;
     }

     return (int) $configRoot;
  }

  /**
   * Setzt Systemvariablen fuer eine Content-Route.
   *
   * @param int $cid Content-ID.
   * @param string $source Debug-/Analysehinweis zur Quelle.
   * @return void
   */
  private function set_content_route(int $cid, string $source = ''): void {
     if ($cid <= 0) {
        return;
     }

     $mode = $this->get_content_permalink_mode();
     $root = $this->get_content_root();

     dbx()->set_system_var('dbx_modul', 'dbxContent');
     dbx()->set_system_var('dbx_cid', $cid);
     dbx()->set_system_var('cid', $cid);
     dbx()->set_system_var('dbx_content_route_cid', $cid);
     $source = (string) $source;
     $cacheableContentRoute = str_starts_with($source, 'permalink-');
     dbx()->set_system_var('dbx_content_permalink_request', $cacheableContentRoute ? 1 : 0);

     if ($mode === 'cms') {
        dbx()->set_system_var('dbx_run1', 'cms');
        dbx()->set_system_var('dbx_run2', 'show');
        dbx()->set_system_var('root', $root);
     } else {
        dbx()->set_system_var('dbx_run1', 'show');
     }

     $this->apply_docs_page_layout($cid, 'dbxContent', 'show', $source);

     dbx()->debug("#PERMALINK set dbxContent mode=($mode) root=($root) cid=($cid) source=($source)");
  }

  /** Wählt im dbxdocs-Design das serverseitige dbx_page des Bereichs. */
  private function apply_docs_page_layout(
     int $cid = 0,
     string $module = '',
     string $action = '',
     string $source = ''
  ): void {
     $design = strtolower(trim((string)dbx()->get_system_var('dbx_design', '')));
     if ($design === '' || $design === 'user') {
        $design = strtolower(trim((string)dbx()->get_config('dbx', 'default_design_user', 'dbxapp')));
     }
     if ($design !== 'dbxdocs') {
        return;
     }

     try {
        $resolver = dbx()->get_include_obj('dbxDocsPageResolver', 'dbxDocs');
        if (!is_object($resolver)) {
           return;
        }
        $page = $cid > 0 && method_exists($resolver, 'forContent')
           ? $resolver->forContent($cid, (string)dbx()->get_system_var('dbx_lng', 'de'), $source)
           : (method_exists($resolver, 'forModule') ? $resolver->forModule($module, $action) : '');
        $page = strtolower(trim((string)$page));
        if (preg_match('/^[a-z0-9_-]+$/', $page) === 1) {
           dbx()->set_system_var('dbx_page', $page);
        }
     } catch (\Throwable $exception) {
        dbx()->debug('#dbxdocs page resolver failed', $exception->getMessage());
     }
  }

  /**
   * Liefert die Content-ID fuer die Startseite aus dbxHome.
   *
   * @return int Content-ID oder 0.
   */
  private function get_home_content_cid(): int {
     $this->load_content_cache_classes();

     return \dbx\dbxContent\dbxContentHome::resolveCid();
  }

  /**
   * Laedt die Content-Cache-Klassen einmalig.
   *
   * @return void
   */
  private function load_content_cache_classes(): void {
     dbx()->load_content_cache_classes();
  }

  /**
   * Loest Permalink, Home, Admin und explizite Modulrouten auf.
   *
   * Reihenfolge:
   * - explizite `dbx_modul`/`dbx_run1` Route gewinnt
   * - `cid`/`dbx_cid` kann direkt Content setzen
   * - Home-Route nutzt dbxHome-Konfiguration
   * - `admin` schaltet auf dbxAdmin
   * - sonst live im Modul dbxContent aufloesen
   * - unbekannte Permalinks mit HTTP 404 auf der konfigurierten Home-Seite zeigen
   *
   * @return void
   */
  public function check_perma() {
     $cid=0; $home=0;
     $permalink= dbx()->get_system_var('dbx_permalink');
     dbx()->set_system_var('dbx_content_permalink_request', 0);
     dbx()->set_system_var('dbx_content_route_cid', 0);
     dbx()->debug("check perma ($permalink)");

     if ($permalink =='undef') $permalink='';
     if ($permalink =='/')     $permalink='';
  

     $lng=dbx()->get_system_var('dbx_lng','de');
  
     $modul      = dbx()->get_request_var('dbx_modul');
     $action     = dbx()->get_request_var('dbx_run1');
     $hasExplicitRoute = ($modul !== '' && $action !== '');

      if ($hasExplicitRoute) {
        dbx()->set_system_var('dbx_modul', $modul);
        dbx()->set_system_var('dbx_run1', $action);
        dbx()->set_system_var('dbx_run2', dbx()->get_request_var('dbx_run2', '', 'parameter'));
        dbx()->set_system_var('root', dbx()->get_request_var('root', '', 'int'));
      }

      $requestCid = (int) dbx()->get_request_var('cid', 0, 'int');
      if (!$requestCid) $requestCid = (int) dbx()->get_request_var('dbx_cid', 0, 'int');

      if ($hasExplicitRoute) {
        dbx()->set_system_var('dbx_cid', $requestCid);
        dbx()->set_system_var('cid', $requestCid);
        $this->apply_docs_page_layout($requestCid, (string)$modul, (string)$action, 'explicit-route');
        return;
      }

      if ($requestCid > 0 && (!$modul || !$action || $modul === 'dbxHome')) {
        $this->set_content_route($requestCid, 'request-cid');
        return;
      }

      if (!$permalink  && !$modul) $home=1;
      if ( $modul=='dbxHome')      $home=1;
      if ($permalink === 'home' && !$modul) $home=1;


     if ($home) {
      $homeCid = $this->get_home_content_cid();
      if ($homeCid > 0) {
        $homeSource = 'dbxHome-config';
        if ($permalink === 'home') {
          $homeSource = 'permalink-home';
        } elseif (!$modul) {
          $homeSource = 'permalink-root';
        }
        $this->set_content_route($homeCid, $homeSource);
        return;
      }
      dbx()->set_system_var('dbx_modul' ,'dbxContent');
      dbx()->set_system_var('dbx_run1','show');
      dbx()->set_system_var('dbx_run2','');
      dbx()->set_system_var('dbx_cid', 0);
      dbx()->set_system_var('cid', 0);
      dbx()->set_system_var('dbx_design','user');
      return;
     }

     if ($permalink =='admin') {
      dbx()->set_system_var('dbx_modul','dbxAdmin');
      dbx()->set_system_var('dbx_run1','run');
      dbx()->set_system_var('dbx_design','admin');
      return;
    }

     if ($permalink === 'sitemap.xml' || $permalink === 'sitemap') {
      dbx()->set_system_var('dbx_modul', 'dbxContent');
      dbx()->set_system_var('dbx_run1', 'sitemap');
      return;
     }

     if ($permalink === 'robots.txt') {
      dbx()->set_system_var('dbx_modul', 'dbxContent');
      dbx()->set_system_var('dbx_run1', 'robots');
      return;
     } 




     $check_perma=dbx()->get_config('dbx','permalink');
     if ($permalink > ' ' && $check_perma) {
       //$permalink=strtolower($permalink);
       dbx()->debug("##Permalink## ($permalink) Lng=($lng) check content#");
       $permalink=trim((string)$permalink, '/');

       $this->load_content_cache_classes();
       $resolved = \dbx\dbxContent\dbxContentPermalinkIndex::resolve($permalink, $lng);
       if (!is_array($resolved) || (int)($resolved['activ'] ?? 0) !== 1) {
          // Sprachspezifische Permalinks tragen ihre Sprache bereits eindeutig
          // in der Content-Tabelle. Dadurch bleiben saubere URLs ohne
          // dbx_lng-Query auch fuer Suchmaschinen direkt erreichbar.
          $accessibleLngs = function_exists('dbx_accessible_lngs')
             ? dbx_accessible_lngs()
             : array($lng);
          foreach ($accessibleLngs as $candidateLng) {
             $candidateLng = strtolower(trim((string)$candidateLng));
             if ($candidateLng === '' || $candidateLng === $lng) {
                continue;
             }
             $candidate = \dbx\dbxContent\dbxContentPermalinkIndex::resolve(
                $permalink,
                $candidateLng
             );
             if (!is_array($candidate) || (int)($candidate['activ'] ?? 0) !== 1) {
                continue;
             }

             $lng = $candidateLng;
             $resolved = $candidate;
             dbx()->set_remember_var('dbx_lng', $lng, 'dbx');
             dbx()->set_system_var('dbx_lng', $lng);
             dbx()->debug("##Permalink## Sprache aus sauberer URL erkannt: ($lng)");
             break;
          }
       }
       if (is_array($resolved) && (int)($resolved['activ'] ?? 0) === 1) {
          $cid = (int)($resolved['cid'] ?? 0);
          if ($cid > 0) {
             $this->set_content_route($cid, 'permalink-content');
             return;
          }
       }

       // Die Home-Seite bleibt als kompatible Darstellung erhalten. Der
       // angeforderte Permalink darf dabei weder umgeschrieben noch als
       // erfolgreicher Inhalt (Soft-404) ausgeliefert/gecached werden.
       $homeCid = $this->get_home_content_cid();
       if ($homeCid > 0) {
         http_response_code(404);
         dbx()->set_system_var('dbx_content_not_found', 1);
         $this->set_content_route($homeCid, 'permalink-home-fallback');
         dbx()->debug("#PERMALINK not found -> HTTP 404 with /home content permalink=($permalink) cid=($homeCid)");
         return;
       }

       http_response_code(404);
       dbx()->set_system_var('dbx_content_not_found', 1);
       dbx()->set_system_var('dbx_modul', 'dbxContent');
       dbx()->set_system_var('dbx_run1', 'show');
       dbx()->set_system_var('dbx_run2', '');
       dbx()->set_system_var('dbx_cid', 0);
       dbx()->set_system_var('cid', 0);
       dbx()->debug("#PERMALINK not found and no /home content configured permalink=($permalink)");
       return;
      }
  }
  
  /**
   * Prueft das aktive Modul und setzt bei fehlendem Modul dbxHome.
   *
   * @return void
   */
  public function check_modul() {
      $modul=dbx()->get_system_var('dbx_modul');
      if (!dbx()->is_modul($modul)) $modul='dbxHome';
      dbx()->set_system_var('dbx_modul',$modul);
  }


  /**
   * Bestimmt das aktive Design fuer den Request.
   *
   * Admin-Module duerfen ins Admin-Design wechseln, normale Benutzer bleiben
   * im User-Design. Installations-/Intro-Zustaende koennen das Zielmodul und
   * Design explizit umschalten.
   *
   * @return void
   */
  public function check_design() {
    $admin =dbx()->can('admin');
    $config=dbx()->get_config('dbx');
    $user_default = (string)($config['default_design_user'] ?? 'dbxapp');
    $admin_default= (string)($config['default_design_admin'] ?? 'dbxapp');
    $construct    = (int)($config['construct'] ?? 0);
    $install      = (int)($config['install'] ?? 1);
    $intro        = (int) ($config['intro'] ?? 0);
    $ok           = (int) ($config['ok'] ?? 0);

    $design        = dbx()->get_remember_var('dbx_design',$user_default,'dbx');
    if (strtolower(trim((string)$design)) === 'fleurop') {
      $design = 'flowers';
      dbx()->set_remember_var('dbx_design', $design, 'dbx');
    }
    //$page          = dbx()->get_remember_var('dbx_page' , 'default'    ,'dbx');
    $page          = dbx()->get_system_var('dbx_page' , 'default');
    $modul         = dbx()->get_system_var('dbx_modul'); 
    $admin_modul   = dbx()->has_text($modul,'_admin');
    
    
    if (!dbx()->is_design($design)) {
       $design='user';
    } else {
      if (!$admin && $design=='admin') $design='user';
      if ( $admin && $admin_modul)     $design='admin';
    }


    $ajax = (int) dbx()->get_system_var('dbx_ajax', 0, 'int');
    if ($intro && $ok && !$ajax) {
        $introShown = (int) dbx()->get_session_var('intro_shown', 0, 'ui', 'dbx');
        $requestModul = trim((string) dbx()->get_request_var('dbx_modul', '', 'parameter'));
        if (!$introShown && $requestModul === '') {
          $design = $user_default;
          $modul  = 'dbxHome';
          $page   = 'intro';
          dbx()->set_session_var('intro_shown', 1, 'ui', 'dbx');
        }
    }

    if (!$modul) {
        $design=$user_default;
        $modul='dbxHome';
        $page='home';
        $action='run';
    }

    if ($install || !$ok) {
      dbx()->set_system_var('dbx_install', 1);
      $modul = 'dbxSetup';
      // Der Installer verwendet bewusst ein festes, datenbankunabhaengiges
      // Design. So kann eine defekte oder noch nicht konfigurierte
      // Kundendesign-Auswahl den Erststart nicht blockieren.
      $design = 'dbxapp';
      $page   = 'install';
      dbx()->set_system_var('dbx_run1', 'install');
    }

    if ($design=='admin') $design=$admin_default;
    if ($design=='user')  $design=$user_default;

    dbx()->set_system_var('dbx_modul'  ,$modul);
    dbx()->set_system_var('dbx_design' ,$design);
    dbx()->set_system_var('dbx_page'   ,$page);
    $ajax=dbx()->get_system_var('dbx_ajax',0);
        
    //dbx_debug("##->check-design##=($design) Modul=($modul)Page=($page) Intro($intro) Ajax=($ajax)");                           
}


  /**
   * Prueft und speichert die aktive Sprache.
   *
   * @return void
   */
  public function check_lng() {
    $config=dbx()->get_config('dbx');
    $lng_default   =$config['default_lng'];
    $lng_accessible=$config['accessible_lng'];
    $lng=dbx()->get_system_var('dbx_lng',dbx()->get_remember_var('dbx_lng',$lng_default,'dbx'));
    if ($lng != $lng_default) {
      $ok=0;
      if (!is_array($lng_accessible)) $lng_accessible = explode(",", $lng_accessible);
      foreach ($lng_accessible as $no => $val) {
        if ($lng == $val) $ok=1;               
      }
      if (!$ok) $lng=$lng_default;
    }
    dbx()->set_remember_var('dbx_lng',$lng,'dbx');
    dbx()->set_system_var('dbx_lng', $lng);
  }

  /**
   * Wendet die globale dbx/translate.php auf Inhalt an.
   *
   * @param string $content Inhalt.
   * @param string $lng Sprache; leer nutzt aktive Sprache.
   * @return string Uebersetzter Inhalt.
   */
  public function translate($content,$lng=''){
    if (!$lng) $lng=dbx()->get_system_var('dbx_lng','de');
    $dir_file=dbx()->get_base_dir().'dbx/translate.php';
    if (file_exists($dir_file)) {
        include $dir_file;
    }
    return $content;
  }




  /**
   * Ersetzt `[dbx:add_css]` durch im Request gesammelte CSS-Fragmente.
   *
   * @param string $content Ausgabeinhalt.
   * @return string Inhalt mit eingesetztem CSS.
   */
  public function del_add_css($content) {
    $css_content='';
    if (isset($_SESSION['dbx']['add_css'])) {
      $xcss=$_SESSION['dbx']['add_css'];
      if (is_array($xcss)) {
        foreach ($xcss as $no => $css) {  $css_content.=$css."\n"; }
      }
    }
    $content = (str_replace('[dbx:add_css]',$css_content,$content));
    return $content;
  }

   /**
    * Ersetzt `[dbx:add_js]` durch im Request gesammelte JS-Fragmente.
    *
    * @param string $content Ausgabeinhalt.
    * @return string Inhalt mit eingesetztem JavaScript.
    */
   public function del_add_js($content) {
      $js_content='';
      if (isset($_SESSION['dbx']['add_js'])) {
        $xjs=$_SESSION['dbx']['add_js'];
        if (is_array($xjs)) {
          foreach ($xjs as $no => $js) {  $js_content.=$js."\n";         }
        }
      }
      $content = (str_replace('[dbx:add_js]',$js_content,$content));
      return $content;
   }



   /**
    * Fuehrt den zentralen Ausgabe-Filter aus.
    *
    * @param string $content Ausgabeinhalt.
    * @return string Gefilterter Inhalt.
    */
   public function out_filter($content) {
       include dbx()->get_base_dir().'dbx/out_filter.php';
       return $content;
    }


   /**
    * Setzt geschuetzte norep-Platzhalter am Ende wieder ein.
    *
    * @param string $content Ausgabeinhalt mit `[norep_*]`-Markern.
    * @return string Inhalt mit wieder eingesetzten Fragmenten.
    */
   public function add_norep($content) {
      $oTPL = dbx()->get_system_obj('dbxTPL');
      if (is_object($oTPL) && method_exists($oTPL, 'cleanup_optional_placeholders')) {
        $content = $oTPL->cleanup_optional_placeholders((string)$content);
      }

      if (isset($_SESSION['dbx']['norep'])) {
        $xnorep=$_SESSION['dbx']['norep'];
        if (is_array($xnorep)) {
          for($i=0; $i < 2; $i++) { // noreps can include noraps
            foreach ($xnorep as $id => $norep) {
              $xid= '['.$id.']';
              $content = str_replace($xid,$norep,$content);
            }
          }
        }
      }
      return $content;
   }


   function get_session_rec($fld,$default='') {
      $value=$default;
      if ($fld != '*') {
        if (isset($_SESSION['dbx']['record'][$fld])) {
           $value=$_SESSION['dbx']['record'][$fld];
        }
      } else {
        $value=$_SESSION['dbx']['record'];
      }
      return $value;
   }

  function design_load($modul_content) {
    $ajax  =dbx()->get_system_var('dbx_ajax');
    $window=dbx()->get_system_var('dbx_window');
    
    $design=dbx()->get_system_var('dbx_activ_design',dbx()->get_system_var('dbx_design'));
    $page  =dbx()->get_system_var('dbx_activ_page'  ,dbx()->get_system_var('dbx_page'));
    $lng   =dbx()->get_system_var('dbx_activ_lng'   ,dbx()->get_system_var('dbx_lng'));
 
    //dbx_debug ("##DBX-CAll AJAX=($ajax) Get Ajax=($get_ajax) POST/GET",$_POST,$_GET);

    if (!$ajax)  {
        if ($window) $page='_window';
        $oTPL=dbx()->get_system_obj('dbxTPL');
        $content=$oTPL->get_design_tpl($design,$page,$lng,'htm',1);

        if (defined('dbxRunAsAdmin') && (int) constant('dbxRunAsAdmin') === 1) {
          $admin_bypass_alert=$oTPL->get_tpl('dbx|alert-warning', array(
            'msg' => 'Admin Bypass ist aktiv'
          ));
          $modul_content=$admin_bypass_alert.'<br>'.$modul_content;
        }

        $content = (str_replace("[dbx:content]",$modul_content,$content));
    } else {
        $content=$modul_content;
    }
    return $content;
  }

  function add_editor_files_data($content) {
    $edit = (int) dbx()->get_system_var('dbx_edit', 0, 'int');

    if (($edit < 4 || $edit > 8) && $edit !== 9) {
      return $content;
    }

    $all_files = dbx()->get_editor_files();
    $files = array();

    foreach ($all_files as $file) {
      if (!isset($file['kind'], $file['file'])) {
        continue;
      }

      if ($edit == 4 && $file['kind'] !== 'fd') {
        continue;
      }

      if ($edit == 5 && $file['kind'] !== 'dd') {
        continue;
      }

      if ($edit == 6 && $file['kind'] !== 'class') {
        continue;
      }

      if ($edit == 7 && $file['kind'] !== 'sysclass') {
        continue;
      }

      if ($edit == 8 && $file['kind'] !== 'config') {
        continue;
      }

      $key = $file['kind'] . '|' . $file['file'];
      $files[$key] = $file;
    }

    $payload = array(
      'mode'  => $edit,
      'files' => array_values($files),
    );

    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if (!$json) {
      return $content;
    }

    $data = '<script type="application/json" class="dbx-editor-files-data">' .
            htmlspecialchars($json, ENT_NOQUOTES, 'UTF-8') .
            '</script>';

    if (strpos($content, '</body>') !== false) {
      return str_replace('</body>', $data . "\n</body>", $content);
    }

    return $data . $content;
  }

  private function render_editor_files_menu($edit, $files) {
    if (!is_array($files) || !count($files)) {
      return '';
    }

    $title = 'Editor Dateien';
    if ($edit == 4) $title = 'FD Dateien';
    if ($edit == 5) $title = 'DD Dateien';
    if ($edit == 6) $title = 'Modul PHP Dateien';
    if ($edit == 7) $title = 'myX SysClass Dateien';
    if ($edit == 8) $title = 'Config Dateien';
    if ($edit == 9) $title = 'Alle Editor Dateien';

    $html  = '<div id="dbxEditorFilesMenu" class="dbx-editor-files-menu" ';
    $html .= 'style="display:none;">';
    $html .= '<button type="button" class="dbx-editor-files-count" title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '" ';
    $html .= 'onclick="this.parentElement.classList.toggle(\'is-open\')" ';
    $html .= 'style="display:inline-flex;align-items:center;gap:6px;min-height:30px;padding:4px 8px;border:1px solid rgba(0,0,0,.18);border-radius:4px;background:#212529;color:#fff;box-shadow:0 4px 12px rgba(0,0,0,.18);">';
    $html .= '<i class="bi bi-pencil-square"></i><span>' . count($files) . '</span></button>';
    $html .= '<div class="dbx-editor-files-list" style="max-height:min(520px,calc(100vh - 96px));overflow:auto;padding:8px;border:1px solid rgba(0,0,0,.16);border-radius:4px;background:#fff;color:#212529;box-shadow:0 10px 28px rgba(0,0,0,.2);">';
    $html .= '<div class="dbx-editor-files-title" style="padding:4px 6px;font-weight:600;color:#495057;">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</div>';

    foreach ($files as $file) {
      if (!isset($file['file'])) {
        continue;
      }

      $path = $file['file'];
      $kind = isset($file['kind']) ? (string)$file['kind'] : '';
      $url  = $this->editor_file_url($kind, $path);
      $label = $this->short_editor_file_label($path);

      $html .= '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" ';
      $html .= 'data-url="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" data-title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '" ';
      $html .= 'style="display:grid;grid-template-columns:22px minmax(0,1fr);align-items:center;gap:6px;padding:5px 6px;border-radius:4px;color:#212529;text-decoration:none;" class="dbx-win">';
      $html .= '<i class="bi bi-filetype-php"></i>';
      $html .= '<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
      $html .= '</a>';
    }

    $html .= '</div>';
    $html .= '</div>';

    return $html;
  }

  private function short_editor_file_label($path) {
    $path = str_replace('\\', '/', $path);
    $prefix = 'dbx/modules/';

    if (strpos($path, $prefix) === 0) {
      return substr($path, strlen($prefix));
    }

    return $path;
  }

  private function editor_file_url($kind, $path) {
    $kind = strtolower(trim((string)$kind));
    $path = str_replace('\\', '/', (string)$path);

    if ($kind === 'dd' && preg_match('#^dbx/modules/([^/]+)/dd/([^/]+)\.dd\.php$#', $path, $m)) {
      return '?dbx_modul=dbxAdmin&dbx_run1=edit_dd&modul=' . rawurlencode($m[1]) . '&dd=' . rawurlencode($m[2]);
    }

    if ($kind === 'fd' && preg_match('#^dbx/modules/([^/]+)/fd/([^/]+)\.fd\.php$#', $path, $m)) {
      return '?dbx_modul=dbxAdmin&dbx_run1=edit_fd&modul=' . rawurlencode($m[1]) . '&fd=' . rawurlencode($m[2]);
    }

    return '?dbx_modul=dbxEditor&dbx_run1=edit&file=' . rawurlencode($path);
  }

  /**
   * Baut die zentrale Ablehnung fuer einen ungueltigen Action-Request.
   *
   * Der Modulcode wird in diesem Fall nicht ausgefuehrt. Die Meldung enthaelt
   * bewusst niemals den uebergebenen Token. HTML-Ausgaben verwenden dbxTPL;
   * API-Aufrufe erhalten eine kleine JSON-Fehlerstruktur.
   *
   * @param array $policy Erkannte Action-Policy.
   * @return string Antwortinhalt.
   */
  private function reject_action_request(array $policy): string {
    http_response_code(403);

    $action = (string)($policy['action'] ?? 'action');
    $params = (array)($policy['params'] ?? array());
    $modul = (string)($params['dbx_modul'] ?? dbx()->get_system_var('dbx_modul', '', 'parameter'));

    try {
      dbx()->sys_msg(
        'security',
        'Action-Token abgewiesen',
        $action,
        'ungueltiger oder fehlender dbx_token',
        array(
          'modul' => $modul,
          'bindings' => (array)($policy['bindings'] ?? array()),
          'source' => (string)($policy['source'] ?? ''),
        )
      );
    } catch (Throwable $e) {
      dbx()->debug('Action-Token-Ablehnung konnte nicht protokolliert werden: ' . $e->getMessage());
    }

    $message = 'Die Aktion wurde aus Sicherheitsgründen nicht ausgeführt. Bitte laden Sie die Ausgangsseite neu und verwenden Sie den dort angebotenen Aktionslink.';
    $api = (int)dbx()->get_system_var('dbx_api', 0, 'int') === 1;

    if ($api) {
      if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
      }
      return (string)json_encode(array(
        'ok' => false,
        'status' => 403,
        'error' => 'invalid_action_token',
        'message' => $message,
      ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $tpl = dbx()->get_system_obj('dbxTPL');
    return $tpl->get_tpl('dbx|alert-warning', array('msg' => $message));
  }

   // - - - - - - - - - - - - - - - - - - - - -

   function run() {
      $content='';
      $uid     =dbx()->user(); 
      $self    =dbx()->get_self_url();
      $design = dbx()->get_system_var('dbx_design');
      $page   = dbx()->get_system_var('dbx_page');
      $lng    = dbx()->get_system_var('dbx_lng');
      $edit   = dbx()->get_system_var('dbx_edit');
      $color  = dbx()->get_system_var('dbx_color');
      $modul  = dbx()->get_system_var('dbx_modul');
      $action = dbx()->get_system_var('dbx_run1');
      $api    = dbx()->get_system_var('dbx_api');
      $cid    = dbx()->get_system_var('dbx_cid',0);
      $run2   = dbx()->get_system_var('dbx_run2','');
      $root   = dbx()->get_system_var('root','');
      $install= dbx()->get_system_var('dbx_install',0);

      //$oInterpreter = dbx()->get_system_obj('dbxInterpreter');

      // Ein Request-Parameter darf niemals eine Benutzeridentitaet erzeugen.
      // API-Endpunkte verwenden dieselbe Modul- und Rechtepruefung wie jeder
      // andere Request; eine erforderliche Anmeldung muss explizit erfolgen.

      //dbx_debug("#WebApp run-> Design=($design) Page=($page) Modul=($modul) Action=($action) Api=($api) Install=($install) User=($uid)  Lng=($lng) Self=($self)");
      

      if ($modul) {
        $access=dbx()->can_modul($modul);
        if (!$access) {
            $perma=dbx()->get_system_var('dbx_permalink');
            if ( $perma && !$uid) dbx()->set_remember_var('dbx_redir_after_login',$perma,'dbx');
            if (!$perma && !$uid) dbx()->set_remember_var('dbx_redir_after_login',"?dbx_modul=$modul&dbx_run1=$action",'dbx');

            $content='[modul=dbxLogin]dbx_run1=login[/modul]';
        }
        if ($access) {

          dbx()->set_system_var('dbx_master_modul' , $modul);  // use 4 session
          dbx()->set_system_var('dbx_master_action',$action);  // use 4 session
          dbx()->set_system_var('dbx_activ_modul'  , $modul);  // use 4 session
          dbx()->set_system_var('dbx_activ_action' ,$action);  // use 4 session

          $actionPolicy = $this->current_action_policy();
          if ($actionPolicy && !$this->current_action_request_is_valid($actionPolicy)) {
            $content = $this->reject_action_request($actionPolicy);
          } else {
            $dbxModul=dbx()->get_modul_obj($modul);
            $mid=dbx()->get_system_var('dbx_activ_modul_id',0,'*');

            dbx()->set_system_var('dbx_activ_modul_id',$mid);
            dbx()->set_modul_var('dbx_modul_id',$mid);
            dbx()->set_modul_var('dbx_modul'   ,$modul);
            dbx()->set_modul_var('dbx_run1'  ,$action);
            if ($run2 !== '') dbx()->set_modul_var('dbx_run2', $run2);
            dbx()->set_modul_var('dbx_cid'     ,$cid);     // come from permalink check()
            if ($cid) dbx()->set_modul_var('cid', $cid);
            if ($root !== '') dbx()->set_modul_var('root', $root);

            $content=dbx()->run_owner($dbxModul);
          }


          if ($api) dbx()->set_system_var('dbx_page','api');

        }        

      }
      $mid=dbx()->get_system_var('dbx_activ_modul_id');
      $go =dbx()->get_system_var('dbx_go',0,'parameter');
      if ($go) { 
          $js ='$(document).ready(function() { ';
          $js.= "dbxGoTo('$go');";
          $js.=' } );';
          $js.="\n";
          $content.="<br><script>$js</script><div id=dbx_end></div>";
      }  
      //dbx_debug("#WebApp load-> Modul=($modul) Action=($action) Access=($access) Modul-id=($mid) go =($go)");

      return $content;
   }


} // class
