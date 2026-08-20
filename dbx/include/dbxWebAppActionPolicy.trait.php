<?php

/** Automatisch aus der stabilen Legacy-Fassade extrahierte interne Verantwortung. */
trait dbxWebAppActionPolicyTrait
{
/**
   * Ergänzt fehlende Routenwerte aus dem aktuellen Requestkontext.
   *
   * Relative Action-Suffixe wie `&dbx_do=row_delete` erhalten so denselben
   * Modul-/Run-Kontext wie eine vollstaendige URL. Bei einem explizit anderen
   * Zielmodul werden keine Run-Werte des aktuellen Moduls uebernommen.
   *
   * @param array $params URL-/Requestparameter.
   * @return array Effektive Parameter.
   */
  private function effective_action_params(array $params): array {
     $current_modul = trim((string)dbx()->get_system_var('dbx_modul', '', 'parameter'));
     $modul = trim((string)($params['dbx_modul'] ?? ''));

     if ($modul === '') {
        $modul = $current_modul;
        if ($modul !== '') {
           $params['dbx_modul'] = $modul;
        }
     }

     if ($modul === $current_modul || $current_modul === '') {
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
   * Löst Binding-Definitionen gegen die effektiven URL-Parameter auf.
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
   * Prüft eine einzelne Match-Definition gegen Routenparameter.
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

     if (!array_key_exists($modul, $this->action_route_cache)) {
        $routes = dbx()->get_cfg($modul, 'action_routes', array());
        $this->action_route_cache[$modul] = is_array($routes) ? $routes : array();
     }

     $routes = $this->action_route_cache[$modul];
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

        $policy_name = trim((string)($definition['action'] ?? $name));
        if ($policy_name === '') {
           $policy_name = 'action';
        }

        return array(
           'source' => 'module',
           'action' => $modul . '.' . $policy_name,
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
     $context_bindings = array('rid', 'id', 'cid', 'iid', 'modul', 'dd', 'fd', 'xmodul');

     foreach (array('dbx_run3', 'dbx_run2', 'dbx_run1') as $parameter) {
        $code = strtolower(trim((string)($params[$parameter] ?? '')));
        if ($code === '') {
           continue;
        }

        $parts = preg_split('/[^a-z0-9]+/', $code, -1, PREG_SPLIT_NO_EMPTY);
        $parts = is_array($parts) ? $parts : array();
        $grid_like = in_array('grid', $parts, true)
           || (isset($parts[0]) && in_array($parts[0], array('data', 'fields'), true));
        if (!$grid_like) {
           continue;
        }

        foreach ($actions as $action) {
           if (!in_array($action, $parts, true)) {
              continue;
           }

           $bindings = array();
           foreach ($context_bindings as $binding) {
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
   * Löst die gueltige Action-Policy fuer einen Parametersatz auf.
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
   * Prüft den Action-Token des aktuellen Requests ohne Modulcode auszufuehren.
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
}
