<?php

/** Automatisch aus der stabilen Legacy-Fassade extrahierte interne Verantwortung. */
trait dbxWebAppRoutingTrait
{
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
     $request_root = dbx()->get_request_var('root', '', 'int');

     if ($request_root !== '') {
        return (int) $request_root;
     }

     $config_root = dbx()->get_cfg('dbxContent', 'root');
     if ($config_root === 'undef' || $config_root === '') {
        return 0;
     }

     return (int) $config_root;
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
     $cacheable_content_route = str_starts_with($source, 'permalink-');
     dbx()->set_system_var('dbx_content_permalink_request', $cacheable_content_route ? 1 : 0);

     if ($mode === 'cms') {
        dbx()->set_system_var('dbx_run1', 'cms');
        dbx()->set_system_var('dbx_run2', 'show');
        dbx()->set_system_var('root', $root);
     } else {
        dbx()->set_system_var('dbx_run1', 'show');
     }

     dbx()->debug("#PERMALINK set dbxContent mode=($mode) root=($root) cid=($cid) source=($source)");
  }

/** Normalisiert die aktive Layoutseite ohne unsichere Verzeichniswerte. */
  private function normalize_layout_page(string $page): string {
    $page = trim($page);
    if ($page === '' || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]{0,63}$/', $page)) {
      return 'default';
    }
    return $page;
  }

/**
   * Liefert die Content-ID fuer die Startseite aus dbxHome.
   *
   * @return int Content-ID oder 0.
   */
  private function get_home_content_cid(): int {
     $this->load_content_cache_classes();

     return \dbx\dbxContent\dbxContentHome::resolve_cid();
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
   * Löst Permalink, Home, Admin und explizite Modulrouten auf.
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
     $has_explicit_route = ($modul !== '' && $action !== '');

      if ($has_explicit_route) {
        dbx()->set_system_var('dbx_modul', $modul);
        dbx()->set_system_var('dbx_run1', $action);
        dbx()->set_system_var('dbx_run2', dbx()->get_request_var('dbx_run2', '', 'parameter'));
        dbx()->set_system_var('root', dbx()->get_request_var('root', '', 'int'));
      }

      $request_cid = (int) dbx()->get_request_var('cid', 0, 'int');
      if (!$request_cid) $request_cid = (int) dbx()->get_request_var('dbx_cid', 0, 'int');

      if ($has_explicit_route) {
        dbx()->set_system_var('dbx_cid', $request_cid);
        dbx()->set_system_var('cid', $request_cid);
        return;
      }

      if ($request_cid > 0 && (!$modul || !$action || $modul === 'dbxHome')) {
        $this->set_content_route($request_cid, 'request-cid');
        return;
      }

      if (!$permalink  && !$modul) $home=1;
      if ( $modul=='dbxHome')      $home=1;
      if ($permalink === 'home' && !$modul) $home=1;


     if ($home) {
      $home_cid = $this->get_home_content_cid();
      if ($home_cid > 0) {
        $home_source = 'dbxHome-config';
        if ($permalink === 'home') {
          $home_source = 'permalink-home';
        } elseif (!$modul) {
          $home_source = 'permalink-root';
        }
        $this->set_content_route($home_cid, $home_source);
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

     $missing_navigation = dbx()->get_include_obj(
       'dbxContentMissingNavigation',
       'dbxContent'
     );
     $missing_language = is_object($missing_navigation)
       && method_exists($missing_navigation, 'language_for_route')
       ? $missing_navigation->language_for_route((string)$permalink)
       : '';
     if ($missing_language !== '') {
       dbx()->set_system_var('dbx_lng', $missing_language);
       dbx()->set_remember_var('dbx_lng', $missing_language, 'dbx');
       dbx()->set_system_var('dbx_modul', 'dbxContent');
       dbx()->set_system_var('dbx_run1', 'missing_navigation');
       dbx()->set_system_var('dbx_run2', '');
       dbx()->set_system_var('dbx_cid', 0);
       dbx()->set_system_var('cid', 0);
       return;
     }




     $check_perma=dbx()->get_cfg('dbx','permalink');
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
          $accessible_lngs = dbx()->accessible_lngs();
          foreach ($accessible_lngs as $candidate_lng) {
             $candidate_lng = strtolower(trim((string)$candidate_lng));
             if ($candidate_lng === '' || $candidate_lng === $lng) {
                continue;
             }
             $candidate = \dbx\dbxContent\dbxContentPermalinkIndex::resolve(
                $permalink,
                $candidate_lng
             );
             if (!is_array($candidate) || (int)($candidate['activ'] ?? 0) !== 1) {
                continue;
             }

             $lng = $candidate_lng;
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
       $home_cid = $this->get_home_content_cid();
       if ($home_cid > 0) {
         http_response_code(404);
         dbx()->set_system_var('dbx_content_not_found', 1);
         $this->set_content_route($home_cid, 'permalink-home-fallback');
         dbx()->debug("#PERMALINK not found -> HTTP 404 with /home content permalink=($permalink) cid=($home_cid)");
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
   * Prüft das aktive Modul und setzt bei fehlendem Modul dbxHome.
   *
   * @return void
   */
  public function check_modul() {
      $modul=dbx()->get_system_var('dbx_modul');
      if (!$this->module_exists((string)$modul)) $modul='dbxHome';
      dbx()->set_system_var('dbx_modul',$modul);
  }

  /** Prueft, ob die Einstiegsklasse eines Moduls vorhanden ist. */
  private function module_exists(string $module): bool {
      if ($module === '') return false;
      return is_file(dbx()->get_base_dir() . 'dbx/modules/' . $module . '/' . $module . '.class.php');
  }
}
