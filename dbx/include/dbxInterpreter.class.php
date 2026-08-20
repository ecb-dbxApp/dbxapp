<?php
/**
 * @file dbxInterpreter.class.php
 * Interpreter fuer `[modul=...]...[/modul]`-Marker in Templates/Inhalten.
 */

/**
 * @brief Führt eingebettete Modulaufrufe aus und ersetzt sie durch Modul-HTML.
 *
 * Sinn:
 * Content- und Template-Dateien können kleine Modulinseln enthalten, ohne
 * selbst PHP auszuführen. Der Interpreter lädt das Modul über dbxApi,
 * setzt die Parameter als geschützte ModulVars und startet das Modul im
 * Owner-Kontext.
 *
 * Beispiel:
 * ```html
 * [modul=dbxLogin]dbx_run1=login[/modul]
 * [modul=dbxContent]dbx_run1=cms&dbx_run2=show&cid=1[/modul]
 * ```
 */
class dbxInterpreter {

  /**
   * Im Template-Editor muss der Interpreter inert bleiben. Der Editor soll
   * rohe `[modul=...]`-Marker anzeigen und speichern, nicht deren Ausgabe.
   */
  private function is_template_editor_context() {
    if ((int)dbx()->get_system_var('dbx_editor', 0, 'int') > 0) {
      return true;
    }

    if ((string)dbx()->get_system_var('dbx_page', '') === '_tpledit') {
      return true;
    }

    $modul = (string)dbx()->get_system_var('dbx_modul', '');
    $run1  = (string)dbx()->get_system_var('dbx_run1', '');

    return $modul === 'dbxAdmin' && in_array($run1, array('_edittpl', '_edittpl_file'), true);
  }

  /**
   * Ersetzt alle Modulmarker im uebergebenen Inhalt.
   *
   * @param string $content Inhalt mit optionalen `[modul=...]`-Bloecken.
   * @return string Inhalt mit gerenderten Modulausgaben.
   */
  public function run($content) {
    $content = (string)$content;
    if ($this->is_template_editor_context()) {
      return $content;
    }
    if ($content === '' || stripos($content, '[modul=') === false) {
      return $content;
    }

    $pattern = '/\[modul=([A-Za-z_][A-Za-z0-9_]*)\]([^\[]*)\[\/modul\]/i';
    for ($pass = 0; $pass < 32; $pass++) {
      $count = 0;
      $next = preg_replace_callback(
        $pattern,
        function (array $match): string {
          return (string)$this->get_modul_content((string)$match[1], (string)$match[2]);
        },
        $content,
        -1,
        $count
      );
      if (!is_string($next) || $count === 0 || $next === $content) {
        break;
      }
      $content = $next;
    }
    return $content;
  }

  /**
   * Ersetzt nur ausgewaehlte Modulmarker. Der Ersatz selbst wird voll
   * interpretiert, damit z.B. dbxHome/dbxMenu als Cache-Bestandteil fertig ist.
   *
   * @param string $content Inhalt mit optionalen `[modul=...]`-Bloecken.
   * @param array $modules Modulnamen, die vorab aufgeloest werden duerfen.
   * @return string Inhalt mit ausgewaehlten gerenderten Modulausgaben.
   */
  public function run_selected_modules($content, array $modules) {
    $content = (string)$content;
    if ($this->is_template_editor_context()) {
      return $content;
    }

    if ($content === '' || stripos($content, '[modul=') === false) {
      return $content;
    }

    $allowed = array();
    foreach ($modules as $module) {
      $module = trim((string)$module);
      if ($module !== '') {
        $allowed[strtolower($module)] = 1;
      }
    }
    if (!$allowed) {
      return $content;
    }

    $pattern = '/\[modul=([A-Za-z_][A-Za-z0-9_]*)\]([^\[]*)\[\/modul\]/i';
    $count = 0;
    $result = preg_replace_callback(
      $pattern,
      function (array $match) use ($allowed): string {
        $module = (string)$match[1];
        if (!isset($allowed[strtolower($module)])) {
          return (string)$match[0];
        }
        return $this->run((string)$this->get_modul_content($module, (string)$match[2]));
      },
      $content,
      -1,
      $count
    );
    return is_string($result) ? $result : $content;
  }

 

  /**
   * Laedt ein Modul, setzt dessen Parameter und liefert die Modulausgabe.
   *
   * Parameter aus dem Marker werden als ModulVars gesetzt und danach als
   * geschuetzt markiert, damit Requestwerte sie nicht versehentlich
   * ueberschreiben.
   *
   * @param string $modul Modulname.
   * @param string $parameter Querystring-artige Parameter.
   * @return string Gerenderte Modulausgabe oder Login-/Warnhinweis.
   */
  private function get_modul_content($modul,$parameter='') {
      $request_snapshot = dbx()->request_context()->snapshot();
      try {
      dbx()->timer('run-'.$modul,'P='.$parameter);  

      $modul_content=''; $xparameter=''; $action='undef'; $modul_id=0;
      $protected_modulvars = array();

      $access=dbx()->has_module_access($modul);
      //dbx_debug("###DBX-Interpreter###  M=($modul) Param=($parameter) Access=($access)");
      if ($access) {
        $o_modul   = dbx()->get_modul_obj($modul); 
        $modul_id = dbx()->get_system_var('dbx_activ_modul_id');
        $parameter= trim($parameter);
        $uid      = dbx()->user('id');
        $part_select = explode ("&", $parameter); // Parameter vom Aufruf
        if (is_array($part_select)) { // Zur Sicherheit
          foreach ($part_select as $id => $value) {
            $pos = strpos($value,"=");
            $pos2=($pos+1);
            $paramn = substr($value,0,$pos); //
            $paramv = substr($value,$pos2);
            if ($paramv=='?') $paramv=dbx()->get_request_var($paramn,'','parameter+|');
            if ($paramn=='dbx_run1') $action=$paramv;
            //$xparameter.= "Parameter($paramn)=($paramv) ";
            dbx()->set_modul_var($paramn,$paramv);

            if ($paramn > '') {
              $protected_modulvars[$paramn] = 1;
            }

            //dbx()->debug("##############Modul=($modul) Par=($paramn) Val=($paramv)");
            //dbx()->set_system_var($paramn,$paramv);
          }
        }

        if (count($protected_modulvars)) {
          dbx()->set_modul_var('dbx_protected_modulvars',$protected_modulvars);
        }

        if ($modul) {
          dbx()->set_system_var('dbx_modul'       ,$modul);
          dbx()->set_system_var('dbx_run1'        ,$action);
          dbx()->set_system_var('dbx_activ_modul' ,$modul);
          dbx()->set_system_var('dbx_activ_action',$action);
      

          $modul_content=dbx()->run_owner($o_modul, 'run', $action);



        } else {
          $modul_content="<p class='red'>#undef# Modul=($modul) Action=($action)</p>";
        }
        //dbx_debug("Interpreter $modul $action",$modul_content);
      } else { // no access
        $uid=dbx()->user();
        if ($uid > 1) {
          $o_tpl=dbx()->get_system_obj('dbxTPL');
          $modul_content=$o_tpl->get_tpl('dbx|alert-warning',"msg=Sie haben keinen Zugriff auf ($modul)." ); 
        }
        if ($uid == 1) {
            $master=dbx()->get_system_var('dbx_master_modul',$modul);
            $perma =dbx()->get_system_var('dbx_permalink','');
            
            $modul_content='[modul=dbxLogin]dbx_run1=login[/modul]';

            //dbx_debug("#Redir after login=($perma)");

            if ($perma) {
              dbx()->set_remember_var('dbx_redir_after_login',$perma,'dbx');
            } else {
              dbx()->set_remember_var('dbx_redir_after_login',"?dbx_modul=$modul&$parameter",'dbx');
            }
        }
      }
      $redir=dbx()->get_remember_var('dbx_redir_after_login','','dbx');
      //dbx_debug("#DBX#  Modul=($modul) Param=($parameter) Access=($access) M-id=($modul_id)  redirect=($redir)");
      dbx()->timer('run-'.$modul);
      return $modul_content;
      } finally {
        dbx()->request_context()->restore($request_snapshot);
      }
    }



} // Class
