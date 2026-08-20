<?php

/** Automatisch aus der stabilen Legacy-Fassade extrahierte interne Verantwortung. */
trait dbxWebAppPresentationTrait
{
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
    $admin =dbx()->has_group('admin');
    $config=dbx()->get_cfg('dbx');
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
    $admin_modul   = $this->is_admin_route_module((string)$modul)
      || $this->is_admin_only_module((string)$modul);
    
    
    if (!dbx()->get_system_obj('dbxPresentation')->is_design($design)) {
       $design='user';
    } else {
      if (!$admin && $design=='admin') $design='user';
      if ( $admin && $admin_modul)     $design='admin';
    }


    $ajax = (int) dbx()->get_system_var('dbx_ajax', 0, 'int');
    if ($intro && $ok && !$ajax) {
        $intro_shown = (int) dbx()->get_session_var('intro_shown', 0, 'ui', 'dbx');
        $request_modul = trim((string) dbx()->get_request_var('dbx_modul', '', 'parameter'));
        if (!$intro_shown && $request_modul === '') {
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
   * Erkennt Systemrouten, die zwingend im konfigurierten Admin-Design laufen.
   *
   * Das zentrale Modul heißt historisch `dbxAdmin` und trägt deshalb – anders
   * als die fachlichen Verwaltungsvarianten (`*_admin`) – kein Suffix. Eine
   * zentrale Erkennung verhindert, dass ein Wechsel aus einem Frontend- oder
   * Dokumentationsdesign die Admin-Oberfläche im falschen Design rendert.
   */
  public function is_admin_route_module(string $modul): bool {
    $modul = strtolower(trim($modul));
    return $modul === 'dbxadmin' || str_ends_with($modul, '_admin');
  }

/**
   * Erkennt Module, deren Konfiguration ausschließlich die Gruppe `admin`
   * zulässt. Das deckt bewusst Module wie dbxSelfTest ab, die Teil der
   * Administration sind, aber aus historischen Gründen kein `_admin`-Suffix
   * besitzen.
   */
  public function is_admin_only_module(string $modul): bool {
    $modul = trim($modul);
    if ($modul === '') return false;
    $groups = dbx()->get_cfg($modul, 'groups');
    if (is_string($groups)) {
      $groups = preg_split('/\s*,\s*/', strtolower(trim($groups)), -1, PREG_SPLIT_NO_EMPTY);
    }
    if (!is_array($groups)) return false;
    $groups = array_values(array_unique(array_map(
      static fn($group): string => strtolower(trim((string)$group)),
      $groups
    )));
    return $groups === array('admin');
  }

/**
   * Prüft und speichert die aktive Sprache.
   *
   * @return void
   */
  public function check_lng() {
    $config=dbx()->get_cfg('dbx');
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
    * Führt den zentralen Ausgabe-Filter aus.
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
      $o_tpl = dbx()->get_system_obj('dbxTPL');
      if (is_object($o_tpl) && method_exists($o_tpl, 'cleanup_optional_placeholders')) {
        $content = $o_tpl->cleanup_optional_placeholders((string)$content);
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
}
