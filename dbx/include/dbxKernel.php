<?php
/**
 * @file dbxKernel.php
 * Basisobjekte und Fallback-Klassen des dbXapp-Kernels.
 *
 * Diese Datei ist der kleine gemeinsame Nenner fuer DBX-Objekte. Sie bindet
 * die zentrale API ein, stellt dbxObj als gemeinsame Basisklasse bereit und
 * definiert einfache Fallback-Klassen, wenn Module oder Include-Klassen fehlen.
 *
 * Beispiel:
 * ```php
 * class myFormPart extends dbxObj {
 *    public function run(): string {
 *       $this->set_property('loaded', 1);
 *       return 'ok';
 *    }
 * }
 * ```
 */

// Direkt gestartete CLI-Tests erhalten denselben isolierten Kontext wie der
// zentrale SelfTest-Runner. So schreiben auch Einzel- und CI-Läufe niemals in
// reale Systemmeldungs- oder Diagnosedatenbanken.
$dbx_test_script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
if (PHP_SAPI === 'cli' && preg_match('~/tests?/[^/]+$~', $dbx_test_script) === 1) {
   putenv('DBX_SELFTEST=1');
}
unset($dbx_test_script);

//global $dbxGlobalObj; $dbxGlobalVar;  $dbxCacheTPL;




require_once __DIR__ .'/dbxApi.php';

/* 
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
  $log = sprintf("[%s] Fehler (%d): %s in %s Zeile %d\n", 
      date('Y-m-d H:i:s'), $errno, $errstr, $errfile, $errline
  );
  error_log($log);
});

set_exception_handler(function ($exception) {
  $log = sprintf("[%s] Ausnahme: %s in %s Zeile %d\n", 
      date('Y-m-d H:i:s'), $exception->getMessage(), 
      $exception->getFile(), $exception->getLine()
  );
  error_log($log);
});

 */


/**
 * Gemeinsame Basisklasse fuer dbXapp-System-, Modul- und Include-Objekte.
 *
 * Sinn:
 * - einfacher objektlokaler Property-Speicher
 * - optionaler Session-Property-Speicher ueber Sections
 * - einheitliches Callback-Modell fuer Form/Report/Module
 * - Einhaengepunkt fuer laufende dbxProcess-Ausgaben
 *
 * Beispiel:
 * ```php
 * $obj = new dbxObj();
 * $obj->set_property('tab', 'details');
 * echo $obj->get_property('tab', 'main');
 * ```
 */
class dbxObj {

  Public $_properties=array();
  Public $_section='';
  Public $_process='';
  protected $_callback_owner = null;
  protected $_callback_id = '';
  protected $_callbacks = array();


  /**
   * Setzt ein explizites Owner-Objekt fuer Callback-Aufrufe.
   *
   * Wird vor allem genutzt, wenn Hilfsobjekte Methoden des aufrufenden Moduls
   * aufrufen sollen, ohne selbst dessen Klasse zu kennen.
   *
   * @param object $owner Objekt, auf dem Callback-Methoden gesucht werden.
   * @return void
   */
  public function set_callback_owner($owner): void {
    if (is_object($owner)) {
      $this->_callback_owner = $owner;
    }
  }

  /**
   * Setzt den Namenspraefix fuer konventionelle Callback-Methoden.
   *
   * Beispiel: Callback-ID `user` plus Event `submit` ergibt `user_submit()`.
   *
   * @param string $id Callback-Praefix.
   * @return void
   */
  public function set_callback_id(string $id): void {
    $this->_callback_id = $this->normalize_callback_id($id);
  }

  /**
   * Ordnet einem Event einen konkreten Methodennamen zu.
   *
   * Beispiel:
   * ```php
   * $form->set_callback('run', 'render_user_form');
   * ```
   *
   * @param string $event Fachlicher Eventname.
   * @param string $callback Methodenname auf dem Owner.
   * @return void
   */
  public function set_callback(string $event, string $callback): void {
    if ($event !== '') {
      $this->_callbacks[$event] = $callback;
    }
  }

  /**
   * Normalisiert Callback-IDs auf gueltige PHP-Methodenbestandteile.
   *
   * @param string $id Roh-ID.
   * @return string Normalisierte ID.
   */
  protected function normalize_callback_id(string $id): string {
    $id = preg_replace('/[^A-Za-z0-9_]+/', '_', $id);
    $id = trim((string) $id, '_');

    if ($id !== '' && preg_match('/^[0-9]/', $id)) {
      $id = '_' . $id;
    }

    return $id;
  }

  /**
   * Liefert den Methodennamen fuer ein Event.
   *
   * Prioritaet: explizites Mapping ueber set_callback(), danach
   * Konvention aus Callback-ID und Event.
   *
   * @param string $event Eventname.
   * @return string Methodenname oder leerer String.
   */
  protected function get_callback_name(string $event): string {
    if ($event === '') {
      return '';
    }

    if (isset($this->_callbacks[$event]) && $this->_callbacks[$event] !== '') {
      return $this->_callbacks[$event];
    }

    if ($this->_callback_id === '') {
      return '';
    }

    return $this->_callback_id . '_' . $event;
  }

  /**
   * Liefert das aktuelle Owner-Objekt fuer Callback-Aufrufe.
   *
   * Falls kein expliziter Owner gesetzt ist, wird der aktuelle Owner aus
   * dbx()->get_current_owner() verwendet.
   *
   * @return object|null
   */
  protected function get_callback_owner() {
    if (is_object($this->_callback_owner)) {
      return $this->_callback_owner;
    }

    if (function_exists('dbx')) {
      return dbx()->get_current_owner();
    }

    return null;
  }

  /**
   * Führt einen Callback aus und gibt dessen Rueckgabewert zurueck.
   *
   * Falls kein Callback existiert oder nicht aufrufbar ist, bleibt der
   * Eingangswert unveraendert. Callback-Methoden erhalten immer
   * `($this, $value)`.
   *
   * @param string $event Eventname.
   * @param mixed $value Eingangswert.
   * @return mixed Callback-Ergebnis oder unveraenderter Eingangswert.
   */
  protected function callback(string $event, mixed $value): mixed {
    $callback = $this->get_callback_name($event);

    if ($callback === '') {
      return $value;
    }

    $owner = $this->get_callback_owner();

    if (!is_object($owner)) {
      return $value;
    }

    if (is_callable(array($owner, $callback))) {
      return $owner->$callback($this, $value);
    }

    if (!method_exists($owner, $callback)) {
      return $value;
    }

    try {
      $method = new ReflectionMethod($owner, $callback);
    } catch (ReflectionException $e) {
      return $value;
    }

    if ($method->isStatic()) {
      return $value;
    }

    return $method->invoke($owner, $this, $value);
  }



  /**
   * Speichert eine Objekt- oder Session-Property.
   *
   * Ohne Section bleibt der Wert am Objekt. Mit Section wird er ueber die
   * zentrale Session-API gespeichert und kann requestuebergreifend genutzt
   * werden.
   *
   * Beispiel:
   * ```php
   * $obj->set_property('open_tab', 'settings');
   * $obj->set_property('page', 2, 'report-state');
   * ```
   *
   * @param string $name Property-Name.
   * @param mixed $value Wert.
   * @param string $section Optionaler Session-Bereich.
   * @param string $modul Modulkontext oder `modul` fuer aktuelles Modul.
   * @return void
   */
  public function set_property($name,$value,$section='',$modul='modul') {
    if (!$section) $section=$this->_section;
    if ( $modul=='modul') $modul=dbx()->get_system_var('dbx_activ_modul');
    if (!$section) {
       $this->_properties[$name]=$value;
    } else {
      dbx()->set_session_var($name,$value,$section,$modul);  
    }   
    //if (!is_array($value)) dbx_debug("Set_property=($name) section ($section) modul=($modul) value=($value)");
  }

  /**
   * Liest eine Objekt- oder Session-Property.
   *
   * @param string $name Property-Name.
   * @param mixed $default Rueckgabe, wenn kein Wert existiert.
   * @param string $section Optionaler Session-Bereich.
   * @param string $modul Modulkontext oder `modul` fuer aktuelles Modul.
   * @return mixed Gespeicherter Wert oder Default.
   */
  public function get_property($name,$default='',$section='',$modul='modul') {
    if (!$section) $section=$this->_section;  
    if (!$section) { 
      if (isset($this->_properties[$name])) {
        $value=$this->_properties[$name];
      } else {
        $value=$default;
      }
    } else {
      if ($modul=='modul') $modul=dbx()->get_system_var('dbx_activ_modul');
      $value=dbx()->get_session_var($name,$default,$section,$modul);
    }
    //if (!is_array($value)) dbx_debug("Get_property=($name) section ($section) modul=($modul) value=($value)");
    return $value;
  }
   

  /**
   * Loescht eine Objekt- oder Session-Property.
   *
   * Mit `$name = '*'` werden alle objektlokalen Properties geloescht.
   *
   * @param string $name Property-Name oder `*`.
   * @param string $section Optionaler Session-Bereich.
   * @param string $modul Modulkontext oder `modul` fuer aktuelles Modul.
   * @return void
   */
  public function del_property($name,$section='',$modul='modul') {
    if (!$section) $section=$this->_section;
    if (!$section) {
      if ($name != '*') {
        if (isset($this->_properties[$name])) unset($this->_properties[$name]);
      } else {
        $this->_properties=array();
      }   
    }
    if ($section) {
      if ($modul=='modul') $modul=dbx()->get_system_var('dbx_activ_modul');
      dbx()->delete_session_var($name,$section,$modul); 
    }
  }

  /**
   * Fuegt den naechsten dbxProcess-Schritt in eine Ausgabe ein.
   *
   * Sinnvoll fuer laengere Prozessablaeufe, deren Fortschritt in mehreren
   * Requests oder UI-Aktualisierungen dargestellt wird.
   *
   * @param string $content Vorhandener Inhalt.
   * @param string $process Prozess-ID; leer liest `dbx_process` aus der Session.
   * @param string $mode append|insert|replace.
   * @return string Inhalt mit Prozessausgabe.
   */
  public function dbx_next_process($content,$process='',$mode='append') {
    $next=''; $pos=0;
    if (!$process) $process=dbx()->get_session_var('dbx_process',0);
    //dbx_debug("#GET-PROCESS=($process)");
    if ($process) {
      $o_process=dbx()->get_system_obj('dbxProcess');
      $next=$o_process->run($process);
      $pos =$o_process->get_property('stepp');
    }
  
    //$content.="<br>Process=($process) Pos=($pos)<br>$next";
    if ($mode=='append')  $content.=$next;
    if ($mode=='insert')  $content =$next.$content;
    if ($mode=='replace') $content =$next;
    
    return $content;
 }



}

// - - - - - -

/**
 * Fallback fuer nicht vorhandene Modulklassen.
 */
class dbxUndefModul {
   function run() {
      $class = dbx()->get_system_var('dbx_modul');
      return "The Modul Classfile <b>$class</b> is undef!";
   }
}

/**
 * Fallback fuer nicht vorhandene Include- oder Systemklassen.
 */
class dbxUndefClass {
   function run() {
      $url   = dbx()->get_base_url();
      $path  = dbx()->get_base_dir();
      $action= dbx()->get_modul_var('dbx_run1');
      $class = dbx()->get_system_var('dbx_modul');
      $inc   = dbx()->get_system_var('dbx_inc');
      $master= dbx()->get_system_var('dbx_master_modul');
      $mact  = dbx()->get_system_var('dbx_master_action');
      return "Aktion=($action) Modul Inc-Classfile <b>$inc</b> from <b>$class</b> is undef!<br> Master=($master) Action=($mact)<br>Base-Url=($url)";
   }
}
