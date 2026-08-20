<?php

/** Internal responsibility extracted from the stable dbxApi facade. */
trait dbxApiRequestStateTrait
{
/**
    * Liefert den fluechtigen, nicht sessiongebundenen Laufzeitkontext des
    * aktuellen Requests (`dbxRequestContext`).
    *
    * Haelt System- und Modulvariablen fuer die Dauer eines einzelnen
    * Requests. Anwendungscode ruft diese Methode normalerweise nicht direkt
    * auf, sondern nutzt die dbx()-Zugriffsmethoden get_system_var()/
    * set_system_var()/get_modul_var()/set_modul_var(), die intern darauf
    * aufbauen. Direkter Zugriff ist nur fuer Kernel-/Pipeline-Code sinnvoll,
    * der den kompletten Kontext braucht statt einzelner Werte.
    *
    * Wichtig: `dbxRequestContext` wird niemals in $_SESSION geschrieben
    * (siehe Runtime-Modernisierungsvertrag).
    *
    * @return dbxRequestContext Lazy erzeugter, request-lokaler Kontext.
    */
   public function request_context(): dbxRequestContext {
      if ($this->request_context === null) {
         require_once $this->os_path(
            $this->get_base_dir() . 'dbx/include/dbxRequestContext.class.php'
         );
         $this->request_context = new dbxRequestContext();
      }
      return $this->request_context;
   }

/**
    * Liest eine globale Systemvariable des aktuellen Requests.
    *
    * Systemvariablen beschreiben den Laufkontext, z.B. aktives Modul,
    * Design, Sprache, Ajax-Status oder URLs. GET/POST kann den Wert liefern,
    * wenn noch kein Sessionwert existiert.
    *
    * Beispiel:
    * ```
    * $ajax = dbx()->get_system_var('dbx_ajax', 0, 'int');
    * ```
    *
    * @param string $varname Name der Systemvariable.
    * @param mixed $default Rueckgabe, wenn kein gueltiger Wert existiert.
    * @param string $rules Validierungsregel fuer validate_var().
    * @return mixed Validierter Wert oder Default.
    */
   public function get_system_var(string $varname, $default = '', string $rules = '*') {
      // Initialisierung der Rückgabevariable
      $value = $default;
      $danger_value = '';

      // Überprüfen, ob die Variable in der Session vorhanden ist
      $context = $this->request_context();
      if ($context->has_system($varname)) {
          $danger_value = $context->system($varname);
      } else {
          // Wenn nicht, nach der Variable in GET oder POST suchen
          if (isset($_GET[$varname])) {
              $danger_value = $_GET[$varname];
          }
          if (isset($_POST[$varname])) {
              $danger_value = $_POST[$varname];
          }
      }
   
      // Wenn ein Wert vorhanden und gültig ist, validieren und zurückgeben
      if (($danger_value !== '') && ($danger_value !== null)) {
          if ($this->validate_var($danger_value, $rules, $varname)) {
              $value = $danger_value;
          }
      }
   
      // Rückgabe des validierten Werts oder des Standardwerts
      return $value;
   }

/**
    * Setzt eine globale Systemvariable fuer den aktuellen Request.
    *
    * Beispiel:
    * ```
    * dbx()->set_system_var('dbx_design', 'admin');
    * ```
    *
    * Wirkung:
    * Veraendert den nur fuer diesen Request gueltigen Laufkontext.
    *
    * @param string $varname Name der Systemvariable.
    * @param mixed $value Neuer Wert.
    * @return void
    */
   public function set_system_var(string $varname, $value) {
      // Speichert den Wert der Systemvariable in der Session
      $this->request_context()->set_system($varname, $value);
   }

/**
    * Liest eine Variable der aktuell aktiven Modulinstanz.
    *
    * Modulvariablen sind an dbx_activ_modul und dbx_activ_modul_id gebunden.
    * Dadurch koennen mehrere Modul-/Include-Laeufe eigene Werte behalten.
    *
    * Beispiel:
    * ```
    * $action = dbx()->get_modul_var('dbx_run1', 'run');
    * ```
    *
    * @param string $varname Name der Modulvariable.
    * @param mixed $default Rueckgabe, wenn kein gueltiger Wert existiert.
    * @param string $rules Validierungsregel.
    * @return mixed Validierter Wert oder Default.
    */
   public function get_modul_var($varname, $default = '', $rules = 'alphanum') {
      // Standardwert initialisieren
      $value = $default;
   
      // Das aktive Modul und die Modul-ID holen
      $modul = $this->get_system_var('dbx_activ_modul', 'undef', '*');
      $mid   = $this->get_system_var('dbx_activ_modul_id', 88888, '*');
   
      // Versuchen, die Variable aus der Session zu holen
      $context = $this->request_context();
      if ($context->has_module($mid, $modul, $varname)) {
          $danger_value = $context->module($mid, $modul, $varname);
      } else {
          // Wenn nicht in der Session, versuche GET und POST
          $danger_value = '';
   
          if (isset($_GET[$varname])) {
              $danger_value = $_GET[$varname];
          }
   
          if (isset($_POST[$varname])) {
              $danger_value = $_POST[$varname];
          }
      }
   
      // Wenn ein Wert vorhanden ist und er gültig ist, validiere den Wert
      if ($danger_value !== '' && $danger_value !== null && $this->validate_var($danger_value, $rules, $varname)) {
          $value = $danger_value;
      }
   
      // Rückgabe des Werts (entweder der gefundene gültige Wert oder der Standardwert)
      return $value;
   }

/**
    * Setzt eine Variable der aktuell aktiven Modulinstanz.
    *
    * Beispiel:
    * ```
    * dbx()->set_modul_var('dbx_run2', 'edit');
    * ```
    *
    * Wirkung:
    * Schreibt in den modulinstanzbezogenen Sessionbereich. Geschuetzte
    * Modulvariablen werden standardmaessig nicht ueberschrieben.
    *
    * @param string $varname Name der Modulvariable.
    * @param mixed $value Neuer Wert.
    * @param bool $check_protected Schutzliste beachten.
    * @return void
    */
   public function set_modul_var($varname, $value = null, $check_protected = true) {
      // Das aktive Modul und die Modul-ID holen
      $mid   = $this->get_system_var('dbx_activ_modul_id', 0, 'int');
      $modul = $this->get_system_var('dbx_activ_modul', 'dbx', 'parameter');
   
      // Geschützte ModulVariablen prüfen.
      // Wichtig: Schutzliste nur aus der echten ModulVar-Session lesen,
      // nicht ueber $this->get_modul_var(), damit GET/POST hier niemals mitreden.
      if ($check_protected) {
         $protected = array();
         $context = $this->request_context();
         if ($context->has_module($mid, $modul, 'dbx_protected_modulvars')) {
            $protected = $context->module($mid, $modul, 'dbx_protected_modulvars');
         }
         if (is_array($protected) && array_key_exists($varname, $protected)) {
            dbx()->debug("PROTECTED ($varname)");
            return;
         }   
      }
   
      // Setze den Wert in der Session
      $this->request_context()->set_module($mid, $modul, $varname, $value);
   
      // Optional: Debugging-Informationen hinzufügen
      // dbx_debug("dbx_set_ModulVar modul=($modul) Mod=($mid) Var=($varname) val=($value)");
   }

/**
    * Liest einen einzelnen GET/POST-Parameter des aktuellen Requests direkt
    * und validiert ihn.
    *
    * Anders als get_system_var()/get_modul_var() liest diese Methode
    * ausschliesslich den rohen Request (kein Session-/Modulinstanz-Fallback)
    * und delegiert an `dbxRequest`. Geeignet fuer einmalige Auswertung von
    * Formular-/AJAX-Parametern, die nicht als System- oder Modulzustand
    * gespeichert werden sollen.
    *
    * Beispiel:
    * ```php
    * $email = dbx()->get_request_var('email', '', 'email');
    * ```
    *
    * @param string $varname Name des GET/POST-Parameters.
    * @param mixed $default Rueckgabe, wenn kein gueltiger Wert existiert.
    * @param string $rules Validierungsregel fuer validate_var(); '*' = keine Pruefung.
    * @return mixed Validierter Wert oder Default.
    */
   public function get_request_var(string $varname, $default = '', string $rules = 'parameter') {
      return $this->get_system_obj('dbxRequest')->request($varname, $default, $rules);
   }

/**
    * Liefert den zentral eingelesenen JSON-Requestbody als Array.
    *
    * Liest `php://input` genau einmal pro Request (ueber `dbxRequest`) und
    * dekodiert ihn als JSON-Objekt/-Array. Fuer klassische Formular-POSTs ist
    * stattdessen get_request_var() zustaendig.
    *
    * Beispiel:
    * ```php
    * $payload = dbx()->get_json_request();
    * $id = (int)($payload['id'] ?? 0);
    * ```
    *
    * @param bool $post_fallback Bei leerem/ungueltigem JSON-Body auf $_POST zurueckfallen.
    * @return array Dekodierter Requestbody oder leeres Array.
    */
   public function get_json_request(bool $post_fallback = false): array {
      return $this->get_system_obj('dbxRequest')->json($post_fallback);
   }

/**
    * Haengt Query-Parameter sicher an eine URL an.
    *
    * Duenne Fassade vor `dbxWebApp::append_route_params()`: normalisiert
    * bestehende Query-Strings und ersetzt gleichnamige Parameter, statt sie
    * zu duplizieren. Wird intern u.a. von action_url() genutzt, um
    * `dbx_token` an eine Ziel-URL anzuhaengen.
    *
    * Beispiel:
    * ```php
    * $url = dbx()->append_url_params($url, array('page' => 2));
    * ```
    *
    * @param string $url Bestehende Modul-/Permalink-URL.
    * @param array $params Anzuhaengende oder zu ersetzende Query-Parameter.
    * @return string URL mit zusammengefuehrtem Query-String.
    */
   public function append_url_params(string $url, array $params): string {
      return (string)$this->get_system_obj('dbxWebApp')->append_route_params($url, $params);
   }

/**
    * Liest einen Remember-Wert.
    *
    * Remember speichert laenger gueltigen Benutzer-/UI-Zustand, z.B.
    * Sprache, Design, Edit-Modus, aktive Auswahl oder Reportzustand.
    *
    * Beispiel:
    * ```
    * $lng = dbx()->get_remember_var('dbx_lng', 'de', 'dbx');
    * ```
    *
    * @param string $varname Name des Remember-Werts.
    * @param mixed $default Rueckgabe, wenn kein Wert existiert.
    * @param string $modul Modulbereich; 'modul' bedeutet aktuelles Modul.
    * @return mixed
    */
   public function get_remember_var(string $varname, $default = '', string $modul = 'modul') {
      // Initialisierung der Rückgabevariable
      $value = $default;
      $danger_value = '';

      // Wenn das Modul nicht angegeben ist, den aktiven Modulnamen aus der Session holen
      if ($modul == 'modul') {
          $modul = $this->get_system_var('dbx_activ_modul', 'dbx', '*');
      }
   
      // Überprüfen, ob die Variable in der Session unter der richtigen Modul-Sektion existiert
      if (isset($_SESSION['dbx']['remember'][$modul][$varname])) {
          $value = $_SESSION['dbx']['remember'][$modul][$varname];
      }
   
      // Rückgabe des validierten Werts oder des Standardwerts
      return $value;
   }

/**
    * Setzt einen Remember-Wert.
    *
    * Beispiel:
    * ```
    * dbx()->set_remember_var('dbx_lng', 'de', 'dbx');
    * ```
    *
    * Wirkung:
    * Merkt UI-/Benutzerzustand ueber den aktuellen Request hinaus.
    *
    * @param string $varname Name des Remember-Werts.
    * @param mixed $value Neuer Wert.
    * @param string $modul Modulbereich; 'modul' bedeutet aktuelles Modul.
    * @return void
    */
   public function set_remember_var(string $varname, $value, string $modul = 'modul') {
      // Wenn das Modul nicht angegeben ist, den aktiven Modulnamen aus der Session holen
      if ($modul == 'modul') {
          $modul = $this->get_system_var('dbx_activ_modul', 'dbx', '*');
      }
   
      // Setzt den Wert der Variable in der Session unter der "remember"-Sektion
      $_SESSION['dbx']['remember'][$modul][$varname] = $value;
   
      // Wenn das Modul 'dbx' ist, wird der Wert auch in der System-Session gespeichert
      if ($modul == 'dbx') {
          $this->set_system_var($varname, $value);
      }
   }

/**
    * Liest einen strukturierten Sessionwert.
    *
    * Beispiel:
    * ```
    * $selection = dbx()->get_session_var('selected_ids', array(), 'report', 'dbxAdmin');
    * ```
    *
    * @param string $key Schluessel oder '*' fuer komplette Ebene.
    * @param mixed $default Rueckgabe, wenn kein Wert existiert.
    * @param string $section Sessionbereich.
    * @param string $modul Modulbereich; 'modul' bedeutet aktuelles Modul.
    * @return mixed
    */
   public function get_session_var($key,$default=null,$section='sys',$modul='modul') {
     $val=$default;
     if ($modul=='modul')  $modul =$this->get_system_var('dbx_activ_modul' ,'dbx');
     if ($key != '*') {
       if (isset($_SESSION['dbx']['session'][$modul][$section][$key])) {
         $val=$_SESSION['dbx']['session'][$modul][$section][$key];
       }
     } else {
       if (isset($_SESSION['dbx']['session'][$modul][$section])) {
          $val=$_SESSION['dbx']['session'][$modul][$section];
       }
     }
     return $val;
   }

/**
    * Setzt einen strukturierten Sessionwert.
    *
    * @param string $key Schluessel.
    * @param mixed $val Neuer Wert.
    * @param string $section Sessionbereich.
    * @param string $modul Modulbereich.
    * @return void
    */
   public function set_session_var($key,$val,$section='sys',$modul='') {
     if (!$modul)   $modul =$this->get_system_var('dbx_activ_modul' ,'dbx');
     if ($key != '*') $_SESSION['dbx']['session'][$modul][$section][$key]=$val;
     if ($key == '*') $_SESSION['dbx']['session'][$modul][$section]=$val;
   }

/**
    * Loescht einen strukturierten Sessionwert oder eine ganze Ebene.
    *
    * @param string $key Schluessel oder '*' fuer komplette Ebene.
    * @param string $section Sessionbereich.
    * @param string $modul Modulbereich.
    * @return void
    */
   public function delete_session_var($key,$section='sys',$modul='modul') {
     if ($modul=='modul')  $modul =$this->get_system_var('dbx_activ_modul' ,'dbx');
     if ($key != '*') {
       if (isset($_SESSION['dbx']['session'][$modul][$section][$key])) {
           unset($_SESSION['dbx']['session'][$modul][$section][$key]);
       }
     }
     if ($key == '*') {
       if (isset($_SESSION['dbx']['session'][$modul][$section])) {
           unset($_SESSION['dbx']['session'][$modul][$section]);
       }
     }
     if ($key == '*' && $section=='*') {
      if (isset($_SESSION['dbx']['session'][$modul])) {
          unset($_SESSION['dbx']['session'][$modul]);
      }
    }
    //dbx_debug("DEL Session Store Key=($key) section=($section) Modul=($modul)"); 
   
   }
}
