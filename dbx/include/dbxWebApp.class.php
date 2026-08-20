<?php
require_once __DIR__ . '/dbxWebAppActionPolicy.trait.php';
require_once __DIR__ . '/dbxWebAppRedirect.trait.php';
require_once __DIR__ . '/dbxWebAppResources.trait.php';
require_once __DIR__ . '/dbxWebAppRouting.trait.php';
require_once __DIR__ . '/dbxWebAppPresentation.trait.php';
/**
 * @file dbxWebApp.class.php
 * Request-, Routing-, Design- und Ausgabevorbereitung des dbXapp-Frontcontrollers.
 */

/**
 * @brief Bereitet Request, Routing, Design und Ausgabe des dbxapp-Frontcontrollers vor.
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

  use dbxWebAppActionPolicyTrait;
  use dbxWebAppRedirectTrait;
  use dbxWebAppResourcesTrait;
  use dbxWebAppRoutingTrait;
  use dbxWebAppPresentationTrait;

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
  private $action_route_cache = array();


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
     * Bewusst nicht `get_base_url()` genannt: `dbx()->get_base_url()` (dbxApi.php)
     * ist die weitverbreitete, argumentlose Fassaden-Methode, die nur den bereits
     * berechneten Systemvar-Wert liest. Diese Methode hier berechnet ihn erst
     * (und wird intern von `dbx()->get_base_url()` befüllt) — gleicher Name auf
     * zwei Klassen hätte zu Verwechslungen geführt.
     *
     * @param string $uri Der URI, der an die Basis-URL angehängt wird.
     * @return string Die vollständige URL mit angehängtem URI.
     */
    function resolve_base_url(string $uri): string {
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
     dbx()->set_cfg($modul,$config);
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
  $hash_pos = strpos($url, '#');
  if ($hash_pos !== false) {
     $fragment = substr($url, $hash_pos);
     $url = substr($url, 0, $hash_pos);
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

  $delim_pos = false;
  foreach (array('?', '&') as $delimiter) {
     $pos = strpos($url, $delimiter);
     if ($pos !== false && ($delim_pos === false || $pos < $delim_pos)) {
        $delim_pos = $pos;
     }
  }

  if ($delim_pos !== false) {
     $path = substr($url, 0, $delim_pos);
     $query = substr($url, $delim_pos + 1);
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
  function get_request_route_string($base_uri = '') {
     $request_uri = $_SERVER['REQUEST_URI'] ?? '';
     if ($request_uri === '') {
        return '';
     }

     if ($base_uri === '') {
        $base_uri = $this->get_base_uri();
     }

     $path = parse_url($request_uri, PHP_URL_PATH) ?? '';
     $path = str_replace('\\', '/', $path);
     $base_uri = str_replace('\\', '/', $base_uri);

     if ($base_uri !== '/' && $base_uri !== '' && strpos($path, $base_uri) === 0) {
        $path = substr($path, strlen($base_uri));
     }

     $route = ltrim($path, '/');
     $query = parse_url($request_uri, PHP_URL_QUERY);

     // Optionaler, dauerhafter Sprachpfad fuer oeffentliche Websites. Die
     // eigentliche dbxapp-Route bleibt unveraendert; die Sprache wird wie ein
     // normaler Requestparameter an den bestehenden Sprachmechanismus gegeben.
     // Installationen aktivieren dies bewusst per language_path_prefix.
     if ((int)dbx()->get_cfg('dbx', 'language_path_prefix', 0) === 1
         && preg_match('#^(de|en|es)(?:/(.*))?$#i', $route, $language_match) === 1) {
        $route = trim((string)($language_match[2] ?? ''), '/');
        $language_query = 'dbx_lng=' . rawurlencode(strtolower((string)$language_match[1]));
        $query = $query !== null && $query !== ''
           ? $language_query . '&' . $query
           : $language_query;
     }

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
  function set_request_uri_to_get() {
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
    $data = dbx()->get_json_request();

    // Falls gültiges JSON → $_POST damit befüllen
    if ($data) {
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
    
    $base_url =$this->resolve_base_url($base_uri);
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
   * Prüft, ob eine Dateiendung als direkt auslieferbare Ressource gilt.
   *
   * @param string $ext Dateiendung ohne Punkt.
   * @return int 1 = Ressource, 0 = keine Ressource.
   */
  function get_is_resorce($ext) {
     $ext = strtolower(ltrim(trim((string)$ext), '.'));
     return isset(self::RESOURCE_MIME_TYPES[$ext]) ? 1 : 0;
  }









  /**
   * Stellt die zentrale dbx-Konfiguration sicher.
   *
   * @return void
   */
  function check_config() {
    $config=dbx()->get_cfg('dbx');
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

    $resource_file = $this->get_resource_file($permalink);
    if ($resource_file) {
      dbx()->debug("## resource exists, not missing ($permalink) file=($resource_file)");
      $this->send_resource_file($resource_file, $ext);
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
   * Übernimmt persistente UI-/Systemwerte aus Remember in den Request.
   *
   * Betroffen sind Design, Edit-Modus, Sprache und Farbschema.
   *
   * @return void
   */
  public function check_remember() {
    // #Session switch values 
    $page  =dbx()->get_remember_var('dbx_page'  ,'default','dbx');
    $design=dbx()->get_remember_var('dbx_design','user'   ,'dbx');
    $edit  =dbx()->get_remember_var('dbx_edit'  ,0        ,'dbx');
    $lng   =dbx()->get_remember_var('dbx_lng'   ,'de'     ,'dbx');
    $presentation = dbx()->get_system_obj('dbxPresentation');
    $default_color = $presentation->normalize_skin(
      (string)dbx()->get_cfg('dbx', 'default_color', 'blau'),
      (string)$design
    );
    $color = dbx()->get_remember_var('dbx_color', $default_color, 'dbx');

    $page  =dbx()->get_request_var('dbx_page'  ,$page);
    $design=dbx()->get_request_var('dbx_design',$design);
    $edit  =max(0, min(9, (int)dbx()->get_request_var('dbx_edit', $edit, 'int')));
    if (!dbx()->has_group('admin')) {
      $edit = 0;
    }
    $lng   =dbx()->get_request_var('dbx_lng'   ,$lng);
    $color = $presentation->normalize_skin(
      (string)dbx()->get_request_var('dbx_color', $color),
      (string)$design
    );

    // Kompatibilitaet fuer vor der Umbenennung gespeicherte Designwerte und
    // alte Links: fleurop wurde in flowers umbenannt.
    if (strtolower(trim((string)$design)) === 'fleurop') {
      $design = 'flowers';
    }
   
    $page = $this->normalize_layout_page((string)$page);
    dbx()->set_remember_var('dbx_page', $page, 'dbx');
    dbx()->set_remember_var('dbx_design',$design,'dbx');
    dbx()->set_remember_var('dbx_edit'  ,$edit  ,'dbx');  
    dbx()->set_remember_var('dbx_lng'   ,$lng   ,'dbx');  
    dbx()->set_remember_var('dbx_color' ,$color ,'dbx');  

    dbx()->set_system_var('dbx_design', $design);
    dbx()->set_system_var('dbx_edit'  , $edit);
    dbx()->set_system_var('dbx_lng'   , $lng);
    dbx()->set_system_var('dbx_color' , $color);
    dbx()->set_system_var('dbx_page'  , $page);
    dbx()->set_system_var('dbx_last_editor_tpl_paths', array());

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
        $o_tpl=dbx()->get_system_obj('dbxTPL');
        $content=$o_tpl->get_design_tpl($design,$page,$lng,'htm',1);

        if (dbx()->is_admin_bypass_active()) {
          $admin_bypass_alert=$o_tpl->get_tpl('dbx|alert-warning', array(
            'msg' => 'Admin Bypass ist aktiv'
          ));
          $modul_content=$admin_bypass_alert.'<br>'.$modul_content;
        }

        if (dbx()->is_demo_mode()) {
          $demo_alert=$o_tpl->get_tpl('dbx|alert-warning', array(
            'msg' => 'Demo-Modus: Nur Ansicht. Änderungen sind gesperrt und Geheimnisse werden als ****** angezeigt.'
          ));
          $modul_content=$demo_alert.'<br>'.$modul_content;
        }

        $content = (str_replace("[dbx:content]",$modul_content,$content));
        if (dbx()->is_demo_mode()) {
          $content=(string)preg_replace('/<body\b/i', '<body data-dbx-demo-mode="read-only"', $content, 1);
          if (stripos($content, 'dbx/js/lib/demoMode.js') === false) {
            $demo_mode_file = dirname(__DIR__) . '/js/lib/demoMode.js';
            $demo_mode_revision = is_file($demo_mode_file) ? (string) filemtime($demo_mode_file) : '0';
            $script='<script src="dbx/js/lib/demoMode.js?v=' . rawurlencode(dbx()->get_version() . '-' . $demo_mode_revision) . '"></script>';
            $content=(string)preg_replace('/<\/body>/i', $script.'</body>', $content, 1);
          }
        }
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
        $access=dbx()->has_module_access($modul);
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

          $action_policy = $this->current_action_policy();
          if ($action_policy && !$this->current_action_request_is_valid($action_policy)) {
            $content = $this->reject_action_request($action_policy);
          } else {
            $dbx_modul=dbx()->get_modul_obj($modul);
            $mid=dbx()->get_system_var('dbx_activ_modul_id',0,'*');

            dbx()->set_system_var('dbx_activ_modul_id',$mid);
            dbx()->set_modul_var('dbx_modul_id',$mid);
            dbx()->set_modul_var('dbx_modul'   ,$modul);
            dbx()->set_modul_var('dbx_run1'  ,$action);
            if ($run2 !== '') dbx()->set_modul_var('dbx_run2', $run2);
            dbx()->set_modul_var('dbx_cid'     ,$cid);     // come from permalink check()
            if ($cid) dbx()->set_modul_var('cid', $cid);
            if ($root !== '') dbx()->set_modul_var('root', $root);

            $content=dbx()->run_owner($dbx_modul);
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
