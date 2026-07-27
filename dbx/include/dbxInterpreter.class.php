<?php
/**
 * @file dbxInterpreter.class.php
 * Interpreter fuer `[modul=...]...[/modul]`-Marker in Templates/Inhalten.
 */

/**
 * Fuehrt eingebettete Modulaufrufe aus und ersetzt sie durch Modul-HTML.
 *
 * Sinn:
 * Content- und Template-Dateien koennen kleine Modulinseln enthalten, ohne
 * selbst PHP auszufuehren. Der Interpreter laedt das Modul ueber dbxApi,
 * setzt die Parameter als geschuetzte ModulVars und startet das Modul im
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
    //return $content;

    if ($this->is_template_editor_context()) {
      return $content;
    }

    //dbx_debug("#RUN-INTERPRETER#");

    $f1=''; $f2=''; $modul=''; $parameter='';



    for ($a=1; $a< 512; $a++) { // break  4
      $do=false;



      // preg_match_all("'[html](.*?)[/html]'si", $content, $match);
      // preg_match_all("'<p class=\"review\">(.*?)</p>'si", $source, $match);
      // foreach($match[1] as $norep)  {
      //   $key=dbx()->norep($norep);
      //   $rep="[html]".$norep."[/html]";
      //   $content=(str_replace($rep,$key,$content));
      // }
      // // - - - - -

      $patterns = "/\[modul=([^\[]*)\]([^\[]*)\[\/modul\]/i";
      preg_match ($patterns, $content, $matches);
      $count=count($matches);

      if ($count) {
        $funki = ($matches[1]);
        $f1=$matches[1];
        $f2=$matches[2];

        if ($f1 > "" && $f2 > "") {
          $modul    = $f1;
          $parameter= $f2;

          $replacements = "ersetzt";

          $replacements  = $this->get_modul_content($modul,$parameter);
          $inc_patterns  = "[modul=".$f1."]".$f2."[/modul]";


          $pos1   = strpos ($content, $inc_patterns );
          $lang1  = strlen ($inc_patterns);
          $lang2  = strlen ($content);
          $vor    = substr ( $content, 0 , $pos1);
          $nach   = substr ( $content, ($pos1+$lang1) , $lang2);
          $content = $vor.$replacements.$nach;

          $do=true;
        }
      }
      // - - - - -
      if (!$do) break; // Kill if Dubbel Break needed
      //echo "<br>Loops=$a";
    } // Loop a

    //dbx_debug("RETURN",$content);
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

    $patterns = "/\[modul=([^\[]*)\]([^\[]*)\[\/modul\]/i";
    for ($a = 1; $a < 512; $a++) {
      if (!preg_match_all($patterns, $content, $matches, PREG_OFFSET_CAPTURE)) {
        break;
      }

      $done = false;
      foreach ($matches[0] as $idx => $match) {
        $modul = (string)($matches[1][$idx][0] ?? '');
        if (!isset($allowed[strtolower($modul)])) {
          continue;
        }

        $parameter = (string)($matches[2][$idx][0] ?? '');
        $replacement = $this->run($this->get_modul_content($modul, $parameter));
        $pos = (int)$match[1];
        $len = strlen((string)$match[0]);
        $content = substr($content, 0, $pos) . $replacement . substr($content, $pos + $len);
        $done = true;
        break;
      }

      if (!$done) {
        break;
      }
    }

    return $content;
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
      dbx()->timer('run-'.$modul,'P='.$parameter);  

      $modul_content=''; $xparameter=''; $action='undef'; $modul_id=0;
      $protected_modulvars = array();

      $access=dbx()->can_modul($modul);
      //dbx_debug("###DBX-Interpreter###  M=($modul) Param=($parameter) Access=($access)");
      if ($access) {
        $oModul   = dbx()->get_modul_obj($modul); 
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
      

          $modul_content=dbx()->run_owner($oModul, 'run', $action);



        } else {
          $modul_content="<p class='red'>#undef# Modul=($modul) Action=($action)</p>";
        }
        //dbx_debug("Interpreter $modul $action",$modul_content);
      } else { // no access
        $uid=dbx()->user();
        if ($uid > 1) {
          $oTPL=dbx()->get_system_obj('dbxTPL');
          $modul_content=$oTPL->get_tpl('dbx|alert-warning',"msg=Sie haben keinen Zugriff auf ($modul)." ); 
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
    }



} // Class
