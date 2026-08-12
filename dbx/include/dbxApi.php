<?php
/**
 * @file dbxApi.php
 * Zentrale Laufzeit-API und Kernel-Helfer.
 *
 * Anwendungscode laeuft ausschliesslich ueber `dbx()` und die Methoden
 * dieser Klasse. Freie globale dbx_*-Funktionen existieren bewusst nicht
 * mehr - einzige Ausnahme sind dbx_get_base_dir()/dbx_get_file_dir() in
 * index.php, die den Installationspfad liefern muessen, bevor dbxApi.php
 * ueberhaupt geladen werden kann.
 */

//include_once $this->os_path($this->get_base_dir().'dbx/add_ons/sftp/vendor/autoload.php');

Global $_dbxCache;

/**
 * Zentrale Laufzeit-API von dbXapp.
 *
 * Diese Klasse ersetzt die frueher breit genutzten globalen dbx_*-Funktionen.
 * Anwendungscode soll ueber die globale Singleton-Funktion dbx() arbeiten:
 *
 * Beispiel:
 * ```
 * $db = dbx()->get_system_obj('dbxDB');
 * $value = dbx()->get_request_var('dbx_run1', 'run');
 * dbx()->set_system_var('dbx_ajax', 1);
 * ```
 *
 * Wirkung:
 * - Systemobjekte werden zentral geladen und gecacht.
 * - Projektweite myX-Overrides werden automatisch bevorzugt.
 * - Request-, System-, Modul- und Sessionzustand bleiben an einer API gebuendelt.
 */
class dbxApi {

   /** Zustandsbehaftete Builder werden niemals im Service-Cache geteilt. */
   private const FACTORY_SYSTEM_CLASSES = array(
      'dbxForm',
      'dbxReport',
      'dbxView',
      'dbxProcess',
   );

   /** Flüchtiger, nicht sessiongebundener Laufzeitkontext. */
   private ?dbxRequestContext $request_context = null;

   /** Dateien, die im aktuellen Request fuer den Editor markiert werden. */
   public array $editor_files = array();

   /** Aktuelle Owner-Objekte fuer DBX-Callbacks. */
   private array $owner_stack = array();

   /** Schutz gegen rekursive Systemmeldungen. */
   private bool $sys_msg_running = false;

   /** Interner Status fuer wiederholte Timer-Segmente. */
   private array $timer_state = array();

   /** Administrativer Benutzerkontext fuer dbxRunAsAdmin. */
   private array $admin_bypass_user = array();

   /** Der administrative Benutzerkontext wurde bereits geladen. */
   private bool $admin_bypass_user_loaded = false;

   /** Schutz gegen rekursives Laden des administrativen Benutzerkontexts. */
   private bool $admin_bypass_user_loading = false;

   /** Installierte dbxapp-Version; wird pro Request genau einmal aus VERSION gelesen. */
   private ?string $installed_version = null;

   /** Fuer die aktuelle Seite registrierte Modul-CSS/JS-Dateien (add_css()/add_js()). */
   private array $module_asset_queue = array('css' => array(), 'js' => array());

   /** Separater, niemals als Admin-Login behandelter Nur-Lese-Demomodus. */

   /**
    * Legt ein Owner-Objekt auf den Callback-Stack.
    *
    * Der Owner ist der fachliche Kontext, in dem Form/Report/Include-Objekte
    * callbacks suchen. `run_owner()` nutzt diese Methode automatisch.
    *
    * @param object $owner Aufrufendes Modul-/Include-Objekt.
    * @return void
    */
   private function push_owner($owner): void {
      if (is_object($owner)) {
         $this->owner_stack[] = $owner;
      }
   }

   /**
    * Entfernt das oberste Owner-Objekt vom Callback-Stack.
    *
    * @return void
    */
   private function pop_owner(): void {
      if ($this->owner_stack) {
         array_pop($this->owner_stack);
      }
   }

   /**
    * Ersetzt oder setzt das aktuell aktive Owner-Objekt.
    *
    * Wird nach dem Laden von Modul- oder Include-Objekten genutzt, damit
    * nachfolgende DBX-Objekte callbacks gegen diesen Kontext aufloesen koennen.
    *
    * @param object $owner Aktuelles Fachobjekt.
    * @return void
    */
   private function set_current_owner($owner): void {
      if (is_object($owner)) {
         if ($this->owner_stack) {
            $this->owner_stack[count($this->owner_stack) - 1] = $owner;
         } else {
            $this->owner_stack[] = $owner;
         }
      }
   }

   /**
    * Liefert das aktuell aktive Owner-Objekt.
    *
    * @return object|null
    */
   public function get_current_owner() {
      if (!$this->owner_stack) {
         return null;
      }

      return $this->owner_stack[count($this->owner_stack) - 1];
   }

   /**
    * Fuehrt eine Methode in einem Owner-Kontext aus.
    *
    * Beispiel:
    * ```php
    * $modul = dbx()->get_modul_obj('dbxAdmin');
    * echo dbx()->run_owner($modul, 'run', 'dashboard');
    * ```
    *
    * @param object $owner Objekt, das ausgefuehrt wird.
    * @param string $method Methodenname, normalerweise `run`.
    * @param mixed $args Argumente fuer die Methode.
    * @return mixed|null Rueckgabe der Methode oder null.
    */
   public function run_owner($owner, string $method = 'run', ...$args) {
      if (!is_object($owner) || !is_callable(array($owner, $method))) {
         return null;
      }

      $this->push_owner($owner);

      try {
         return $owner->$method(...$args);
      } finally {
         $this->pop_owner();
      }
   }

   /**
    * Laedt ein Systemobjekt aus dbx/include und bevorzugt den myX-Override.
    *
    * Wird z.B. dbxDB angefordert, erzeugt/laedt DBX bei Bedarf
    * dbx/modules/myX/sysclass/myDB.class.php und instanziert myDB extends dbxDB.
    * Das Objekt wird unter dem Originalnamen gecacht.
    *
    * Beispiel:
    * ```
    * $db = dbx()->get_system_obj('dbxDB');
    * echo get_class($db); // myDB, wenn der Override existiert
    * ```
    *
    * @param string $class Originale Systemklasse, z.B. dbxDB oder dbxTPL.
    * @param string $use Wenn gesetzt, wird nur geladen, aber nicht instanziert.
    * @return object|null Instanz des myX-Overrides oder der Originalklasse.
    * @throws Exception Wenn die Zielklasse nicht geladen werden kann.
    */
   public function get_system_obj(string $class, string $use = ''): ?object {
      global $_dbxCache;

      if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $class)) {
         throw new Exception("Invalid system class name '$class'.");
      }

      $factory = in_array($class, self::FACTORY_SYSTEM_CLASSES, true);
      if (!$factory && isset($_dbxCache[$class])) {
         return !$use ? $_dbxCache[$class] : null;
      }

      $baseClass = '\\' . $class;
      $baseFile  = $this->os_path($this->get_base_dir() . "dbx/include/" . $class . ".class.php");

      if (file_exists($baseFile)) {
         require_once $baseFile;
      } else {
         $baseClass = "\\dbxUndefClass";
      }

      $myClass = $this->get_SysClassOverrideName($class);
      $myFile  = $this->ensure_SysClassOverride($class, $myClass, $baseClass);

      if ($myFile && file_exists($myFile)) {
         $this->register_editor_file('sysclass', $myFile);
         require_once $myFile;
      }

      $myClassFull = '\\' . $myClass;
      $createClass = class_exists($myClassFull, false) ? $myClassFull : $baseClass;

      if ($use) {
         return null;
      }

      if (class_exists($createClass)) {
         $object = new $createClass();
         if ($factory) {
            return $object;
         }
         $_dbxCache[$class] = $object;
         return $object;
      }

      throw new Exception("Klasse '$createClass' konnte nicht geladen werden.");
   }

   /** Liefert den flüchtigen Zustand des aktuellen Requests. */
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
    * Liefert einen projekt-relativen Dateipfad fuer Editor-Marker.
    *
    * @param string $file Absoluter oder relativer Dateipfad.
    * @return string Projekt-relativer Pfad.
    */
   public function editor_file_path(string $file): string {
      $file = str_replace('\\', '/', $file);
      $base = str_replace('\\', '/', $this->get_base_dir());

      if (strpos($file, $base) === 0) {
         $file = substr($file, strlen($base));
      }

      return ltrim($file, '/');
   }

   /**
    * Registriert eine im aktuellen Request genutzte Editor-Datei.
    *
    * @param string $kind Marker-Typ: fd, dd, class, sysclass oder config.
    * @param string $file Absoluter oder projekt-relativer Dateipfad.
    * @return void
    */
   public function register_editor_file(string $kind, string $file): void {
      $kind = strtolower(trim($kind));
      $file = trim($file);

      if ($kind === '' || $file === '') {
         return;
      }

      $path = $this->editor_file_path($file);
      $key  = $kind . '|' . $path;
      $this->editor_files[$key] = array('kind' => $kind, 'file' => $path);
   }

   /**
    * Liefert die im aktuellen Request registrierten Editor-Dateien.
    *
    * @return array
    */
   public function get_editor_files(): array {
      return $this->editor_files;
   }

   /**
    * Erzeugt einen HTML-Kommentar fuer den Frontend-Dateieditor.
    *
    * Die Marker werden nur im passenden dbx_edit-Modus ausgegeben:
    * - 4 = FD
    * - 5 = DD
    * - 6 = Modul-/Include-Class
    * - 7 = myX-Systemklasse
    * - 8 = config.php
    *
    * @param string $kind Marker-Typ: fd, dd, class, sysclass oder config.
    * @param string $file Absoluter oder projekt-relativer Dateipfad.
    * @return string HTML-Kommentar oder leerer String.
    */
   public function editor_marker(string $kind, string $file): string {
      $kind = strtolower(trim($kind));
      $mode = (int) $this->get_system_var('dbx_edit', 0, 'int');
      $modes = array('fd' => 4, 'dd' => 5, 'class' => 6, 'sysclass' => 7, 'config' => 8);

      if (!isset($modes[$kind]) || ($mode !== 9 && $mode !== $modes[$kind]) || $file === '') {
         return '';
      }

      $path = $this->editor_file_path($file);
      $path = str_replace('--', '-', $path);

      return "\n<!-- DBX-EDITOR|$kind|$path -->\n";
   }

   /**
    * Laedt und startet den Kontext fuer ein Hauptmodul.
    *
    * Wirkung:
    * - erhoeht die aktive Modulinstanz-ID,
    * - setzt dbx_activ_modul und dbx_modul,
    * - laedt dbx/modules/<modul>/<modul>.class.php,
    * - erzeugt die Modulklasse im Namespace \dbx\<modul>\<modul>.
    *
    * Beispiel:
    * ```
    * $modul = dbx()->get_modul_obj('dbxAdmin');
    * echo $modul->run();
    * ```
    *
    * @param string $class Modulname.
    * @return object Instanz der Modulklasse.
    * @throws Exception Wenn die Modulklasse nicht geladen werden kann.
    */
   public function get_modul_obj(string $class): object {
      // Vollqualifizierten Klassennamen vorbereiten
      $namespace_class = '\dbx\-class-\-class-';
      $namespace_class = str_replace('-class-', $class, $namespace_class);
   
      // Modul-ID auslesen und inkrementieren
      $modul_id = $this->get_system_var('dbx_activ_modul_id', 0, '*');
      $modul_id++;
      $this->set_system_var('dbx_activ_modul_id', $modul_id);
   
      // Modulname in System- und Modulvariablen speichern
      $this->set_system_var('dbx_activ_modul', $class);
      $this->set_modul_var('dbx_modul_id', $modul_id);
      $this->set_modul_var('dbx_modul', $class);
   
      // Admin-spezifisches Design anwenden, falls Modul ein Admin-Modul ist
      if (stripos($class, 'admin') !== false) {
          //$admin_design = $this->get_cfg('dbx', 'default_design_admin');
          //$this->set_system_var('dbx_design', $admin_design);
      }
   
      // Pfad zur Klassen-Datei des Moduls berechnen
      $modul_class_file = $this->os_path($this->get_base_dir() . "dbx/modules/$class/$class.class.php");
   
      // Klassen-Datei einbinden, wenn vorhanden
      if (file_exists($modul_class_file)) {
          $this->register_editor_file('class', $modul_class_file);
          require_once $modul_class_file;
      } else {
          // Fallback-Klasse verwenden, falls die Datei fehlt
          $namespace_class = "\\dbxUndefClass";
      }
   
      // PrÃ¼fen, ob die Klasse existiert, und Instanz erstellen
      if (class_exists($namespace_class)) {
          $object = new $namespace_class();
          if (method_exists($object, 'set_editor_class_file')) {
              $object->set_editor_class_file($modul_class_file);
          }
          $this->set_current_owner($object);
          return $object;
      }
   
      // Fehler werfen, falls die Klasse nicht existiert
      throw new Exception("Modul-Klasse '$namespace_class' konnte nicht geladen werden.");
   }

   /**
    * Laedt eine Include-Klasse aus einem Modul.
    *
    * Beispiel:
    * ```
    * $list = dbx()->get_include_obj('user_list', 'dbxUser_admin');
    * echo $list->run();
    * ```
    *
    * Wirkung:
    * - setzt die Systemvariable dbx_inc,
    * - nutzt ohne Modulparameter das aktuell aktive Modul,
    * - laedt dbx/modules/<modul>/include/<class>.class.php.
    *
    * @param string $class Include-Klassenname ohne .class.php.
    * @param string $modul Optionales Modul; leer bedeutet aktives Modul.
    * @param string $use Wenn gesetzt, wird nur geladen, aber nicht instanziert.
    * @return object|null Instanz der Include-Klasse oder null im Nur-Laden-Modus.
    * @throws Exception Wenn die Klasse nicht geladen werden kann.
    */
   public function get_include_obj(string $class, string $modul = '', string $use = ''): ?object {
      // Aktuelles Modul verwenden, wenn keins angegeben wurde
      if (!$modul) {
          $modul = $this->get_system_var('dbx_activ_modul', 'dbx', '*');
      }
   
      // Systemvariable fÃ¼r das aktuell geladene Include setzen
      $this->set_system_var('dbx_inc', $class);
   
      // Vollqualifizierten Klassennamen generieren
      $namespace_class = '\dbx\-modul-\-class-';
      $namespace_class = str_replace('-modul-', $modul, $namespace_class);
      $namespace_class = str_replace('-class-', $class, $namespace_class);
   
      // Pfad zur Klassen-Datei bestimmen
      $modul_class_file = $this->os_path($this->get_base_dir() . "dbx/modules/$modul/include/$class.class.php");
   
      // Klassen-Datei einbinden, wenn sie existiert
      if (file_exists($modul_class_file)) {
          $this->register_editor_file('class', $modul_class_file);
          require_once $modul_class_file;
      } else {
          // Fallback-Klasse verwenden, falls Datei fehlt
          $namespace_class = "\\dbxUndefClass";
      }
   
      // Objekt erstellen und zurÃ¼ckgeben, falls $use nicht gesetzt ist
      if (!$use) {
          if (class_exists($namespace_class)) {
              $object = new $namespace_class();
              if (method_exists($object, 'set_editor_class_file')) {
                  $object->set_editor_class_file($modul_class_file);
              }
              $this->set_current_owner($object);
              return $object;
          }
   
          // Fehler werfen, falls die Klasse nicht existiert
          throw new Exception("Klasse '$namespace_class' konnte nicht geladen werden.");
      }
   
      // Keine RÃ¼ckgabe, wenn $use gesetzt ist
      return null;
   }

   /**
    * Bindet eine Systemklasse aus dbx/include ein, ohne ein Objekt zu erzeugen.
    *
    * Beispiel:
    * ```
    * dbx()->use_system_class('dbxValidator');
    * ```
    *
    * @param string $class Systemklasse ohne .class.php.
    * @return void
    */
   public function use_system_class($class)  {
      $modul_id=0;
      $dbx_class_file=$this->get_base_dir()."dbx/include/".$class.".class.php";
      $dbx_class_file=$this->os_path($dbx_class_file);
   
   
      if (file_exists($dbx_class_file)) {
        require_once $dbx_class_file;
      }
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
      // Initialisierung der RÃ¼ckgabevariable
      $value = $default;
      $danger_value = '';
   
      // ÃœberprÃ¼fen, ob die Variable in der Session vorhanden ist
      $context = $this->request_context();
      if ($context->hasSystem($varname)) {
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
   
      // Wenn ein Wert vorhanden und gÃ¼ltig ist, validieren und zurÃ¼ckgeben
      if (($danger_value !== '') && ($danger_value !== null)) {
          if ($this->validate_var($danger_value, $rules, $varname)) {
              $value = $danger_value;
          }
      }
   
      // RÃ¼ckgabe des validierten Werts oder des Standardwerts
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
      $this->request_context()->setSystem($varname, $value);
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
      if ($context->hasModule($mid, $modul, $varname)) {
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
   
      // Wenn ein Wert vorhanden ist und er gÃ¼ltig ist, validiere den Wert
      if ($danger_value !== '' && $danger_value !== null && $this->validate_var($danger_value, $rules, $varname)) {
          $value = $danger_value;
      }
   
      // RÃ¼ckgabe des Werts (entweder der gefundene gÃ¼ltige Wert oder der Standardwert)
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
   
      // GeschÃ¼tzte ModulVariablen prÃ¼fen.
      // Wichtig: Schutzliste nur aus der echten ModulVar-Session lesen,
      // nicht ueber $this->get_modul_var(), damit GET/POST hier niemals mitreden.
      if ($check_protected) {
         $protected = array();
         $context = $this->request_context();
         if ($context->hasModule($mid, $modul, 'dbx_protected_modulvars')) {
            $protected = $context->module($mid, $modul, 'dbx_protected_modulvars');
         }
         if (is_array($protected) && array_key_exists($varname, $protected)) {
            dbx()->debug("PROTECTED ($varname)");
            return;
         }   
      }
   
      // Setze den Wert in der Session
      $this->request_context()->setModule($mid, $modul, $varname, $value);
   
      // Optional: Debugging-Informationen hinzufÃ¼gen
      // dbx_debug("dbx_set_ModulVar modul=($modul) Mod=($mid) Var=($varname) val=($value)");
   }

   /**
    * Liest GET/POST über die Request-Systemklasse und validiert den Wert.
    */
   public function get_request_var(string $varname, $default = '', string $rules = 'parameter') {
      return $this->get_system_obj('dbxRequest')->request($varname, $default, $rules);
   }

   /**
    * Laedt die Konfiguration eines Moduls.
    *
    * Beispiel:
    * ```
    * $cache = dbx()->get_cfg('dbx', 'cache');
    * $config = dbx()->get_cfg('dbx');
    * ```
    *
    * Wirkung:
    * Liest Modulkonfiguration aus Dateien/Cache und liefert entweder den
    * kompletten Config-Array oder einen einzelnen Schluessel.
    *
    * @param string $modul Modulname.
    * @param string $key Optionaler Config-Schluessel.
    * @param mixed $default Rueckgabewert, wenn der Schluessel nicht existiert.
    * @param bool $forDisplay Maskiert Geheimnisse aus config.php/config.local.php
    *                         im Demo-Modus fuer die Anzeige.
    * @return mixed
    */
   public function get_cfg(string $modul = 'dbx', string $key = '', $default = null, bool $forDisplay = false) {
      $moduleConfigFile = $this->os_path($this->get_base_dir() . "dbx/modules/$modul/cfg/config.php");
      $moduleLocalConfigFile = $this->os_path($this->get_base_dir() . "dbx/modules/$modul/cfg/config.local.php");

      if (file_exists($moduleConfigFile)) {
          $_SESSION['dbx']['config_file'][$modul] = $moduleConfigFile;
          $this->register_editor_file('config', $moduleConfigFile);
      }

      $signature = $this->config_file_signature($moduleConfigFile)
         . '|' . $this->config_file_signature($moduleLocalConfigFile);
      $cachedSignature = (string)($_SESSION['dbx']['config_signature'][$modul] ?? '');

      // Der Cache gilt nur so lange, wie Basis- und lokale Konfiguration
      // unveraendert sind. Das verhindert veraltete Werte in langen Sessions.
      if (!isset($_SESSION['dbx']['config'][$modul]) || $cachedSignature !== $signature) {
         $config = $this->read_config_file($moduleConfigFile);
         $localConfig = $this->read_config_file($moduleLocalConfigFile);
         if ($localConfig) {
            $config = array_replace_recursive($config, $localConfig);
         }

         if (!isset($config['groups'])) {
            $config['groups'] = array('admin');
         } elseif (!is_array($config['groups'])) {
            $config['groups'] = array_values(array_filter(array_map('trim', explode(',', (string)$config['groups']))));
         }

         $_SESSION['dbx']['config'][$modul] = $config;
         $_SESSION['dbx']['config_signature'][$modul] = $signature;
      }

      $config = $_SESSION['dbx']['config'][$modul];

      // Spezifischen Schlüssel zurueckgeben, falls angegeben
      if ($key) {
          if (!array_key_exists($key, $config)) {
              return $default !== null ? $default : 'undef';
          }

          $val = $config[$key];
          if (($val === 'undef' || $val === '' || $val === null) && $default !== null) {
              return $default;
          }

          return $forDisplay && $this->is_demo_mode()
             ? $this->mask_cfg_for_display($val, $key)
             : $val;
      }
   
      // Gesamte Konfiguration zurueckgeben. Nur explizite Anzeigeaufrufe
      // maskieren Geheimnisse; interne Dienste benoetigen die Originalwerte.
      return $forDisplay && $this->is_demo_mode()
         ? $this->mask_cfg_for_display($config)
         : $config;
   }

   /**
    * Prüft den benutzerbezogenen Nur-Lese-Demomodus.
    *
    * Ausschliesslich die exakte Rolle `demo` aktiviert den Modus. Globale
    * Konfigurations- oder Umgebungswerte beeinflussen normale Benutzer nicht.
    * Der Wert wird nicht gecached, weil sich der Benutzer im selben Request-
    * Prozess an- oder abmelden kann.
    */
   public function is_demo_mode(): bool {
      $roles = $_SESSION['dbx']['current_user']['roles'] ?? array();
      $roles = $this->normalize_group_list($roles, true);
      return in_array('demo', $roles, true);
   }

   /**
    * Prueft, ob der Entwicklungs-Admin-Bypass im aktuellen Request wirksam ist.
    *
    * Eine echte Anmeldung hat immer Vorrang. Dadurch werden Demo- und andere
    * Benutzer weder als Administrator ueberlagert noch mit einer irrefuehrenden
    * Bypass-Meldung dargestellt, nur weil die Entwicklungskonstante gesetzt ist.
    */
   public function is_admin_bypass_active(): bool {
      if (!defined('dbxRunAsAdmin') || (int)constant('dbxRunAsAdmin') !== 1) {
         return false;
      }

      return (int)($_SESSION['dbx']['current_user']['id'] ?? 0) <= 0;
   }

   /** Normalisiert kommaseparierte oder bereits aufgeteilte Gruppenlisten. */
   private function normalize_group_list($groups, bool $lowercase = false): array {
      if (!is_array($groups)) $groups = explode(',', (string)$groups);
      $groups = array_values(array_filter(array_map(static function($group) use ($lowercase): string {
         $group = trim((string)$group);
         return $lowercase ? strtolower($group) : $group;
      }, $groups), static fn(string $group): bool => $group !== ''));
      return array_values(array_unique($groups));
   }

   /**
    * Erkennt geheime Konfigurationsfelder ausschliesslich innerhalb der von
    * get_cfg() geladenen Config-Arrays. Zugangsdaten und API-Schluessel muessen
    * gemaess der einheitlichen Config-Konvention `pass`, `key` oder `token` im
    * Feldnamen tragen. Dadurch bleibt die Maskierung eindeutig und pruefbar.
    */
   private function is_cfg_secret_key(string $key): bool {
      return preg_match('/(?:pass|key|token)/i', $key) === 1;
   }

   /** Maskiert Geheimnisse rekursiv, ohne die intern geladene Config zu veraendern. */
   private function mask_cfg_for_display($value, string $key = '') {
      if (is_array($value)) {
         $masked = array();
         foreach ($value as $childKey => $childValue) {
            $masked[$childKey] = $this->mask_cfg_for_display($childValue, (string)$childKey);
         }
         return $masked;
      }

      if ($this->is_cfg_secret_key($key) && (is_scalar($value) || $value === null)) {
         return '******';
      }

      return $value;
   }

   /**
    * Liest Modul-Defaults aus cfg/config.php (ohne Session-Cache).
    *
    * @param string $modul Modulname.
    * @return array
    */
   private function read_module_config_defaults(string $modul): array {
      if ($modul === 'dbx') {
         $dir_file = $this->os_path($this->get_base_dir() . 'dbx/modules/dbx/cfg/config.php');
      } else {
         $dir_file = $this->os_path($this->get_base_dir() . "dbx/modules/$modul/cfg/config.php");
      }
      if (!is_file($dir_file) || !is_readable($dir_file)) {
         return array();
      }

      return $this->read_config_file($dir_file);
   }

   /**
    * Liest eine dbXapp-Konfigurationsdatei isoliert ein.
    *
    * Die Datei darf ausschliesslich $config befuellen. Fehler werden protokolliert,
    * aber nicht in die HTTP-Antwort geschrieben.
    */
   private function read_config_file(string $dir_file): array {
      if (!is_file($dir_file) || !is_readable($dir_file)) {
         return array();
      }

       $config = array();
       $loader = static function (string $__dbxConfigFile): array {
          $config = array();
          include $__dbxConfigFile;
          return is_array($config) ? $config : array();
       };
       $outputLevel = ob_get_level();
       ob_start();
       set_error_handler(function ($errno, $errstr, $errfile, $errline) {
          throw new \Exception("Fehler in config.php: $errstr in Zeile $errline");
       });

       try {
          $config = $loader($dir_file);
       } catch (\Throwable $e) {
          $this->debug('#CFG read failed file=(' . $dir_file . ') error=(' . $e->getMessage() . ')');
          $config = array();
       } finally {
          restore_error_handler();
          while (ob_get_level() > $outputLevel) ob_end_clean();
       }

      return is_array($config) ? $config : array();
   }

   /** Liefert eine kompakte Signatur fuer die Cache-Invalidierung. */
   private function config_file_signature(string $file): string {
      if (!is_file($file)) {
         return '-';
      }

      $mtime = @filemtime($file);
      $size = @filesize($file);
      return (string)($mtime === false ? 0 : $mtime) . ':' . (string)($size === false ? 0 : $size);
   }

   /**
    * Entfernt lokale Overrides aus der persistierbaren Basiskonfiguration.
    *
    * So kann ein Admin die normale config.php speichern, ohne Werte aus der
    * nicht versionierten config.local.php versehentlich hineinzukopieren.
    */
   private function strip_local_config_values(array $runtime, array $local, array $base): array {
      foreach ($local as $name => $localValue) {
         if (is_array($localValue) && isset($runtime[$name]) && is_array($runtime[$name])) {
            $baseValue = isset($base[$name]) && is_array($base[$name]) ? $base[$name] : array();
            $runtime[$name] = $this->strip_local_config_values($runtime[$name], $localValue, $baseValue);
            if ($runtime[$name] === array() && !array_key_exists($name, $base)) {
               unset($runtime[$name]);
            }
            continue;
         }

         if (array_key_exists($name, $base)) {
            $runtime[$name] = $base[$name];
         } else {
            unset($runtime[$name]);
         }
      }

      return $runtime;
   }

   /**
    * Speichert die Konfiguration eines Moduls.
    *
    * Beispiel:
    * ```
    * dbx()->set_cfg('dbx', $config);
    * ```
    *
    * @param string $modul Modulname.
    * @param array $config Zu speichernde Konfiguration.
    * @param string $scope `base` fuer config.php, `local` fuer config.local.php.
    * @return int Anzahl geschriebener Bytes oder 0 bei Fehler.
    */
   public function set_cfg(string $modul, array $config, string $scope = 'base'): int {
      if ($this->is_demo_mode()
          || preg_match('/^[A-Za-z0-9_]+$/', $modul) !== 1
          || !in_array($scope, array('base', 'local'), true)
      ) {
         return 0;
      }

      if ($scope === 'local') {
         $dir = $this->os_path($this->get_base_dir() . "dbx/modules/$modul/cfg/");
         if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            return 0;
         }

         $localFile = $this->os_path($dir . 'config.local.php');
         $content = "<?php\n" . $this->convert_array_to_php_code($config, '$config');
         $written = file_put_contents($localFile, $content, LOCK_EX);
         if ($written === false) {
            return 0;
         }

         @chmod($localFile, 0600);
         unset(
            $_SESSION['dbx']['config'][$modul],
            $_SESSION['dbx']['config_signature'][$modul]
         );
         $this->get_cfg($modul);
         return (int)$written;
      }

      $content = "<?php \n"; // PHP-Tag hinzufÃ¼gen, um die Datei als ausfÃ¼hrbaren Code zu speichern
      $dir = $this->get_base_dir() . "dbx/modules/$modul/cfg/";
      if (isset($config['form-config-edit'])) unset($config['form-config-edit']);
      $dir_file = $this->os_path($dir . 'config.php');
      $local_file = $this->os_path($dir . 'config.local.php');
      $baseConfig = $this->read_config_file($dir_file);
      $localConfig = $this->read_config_file($local_file);
      if ($localConfig) {
         $config = $this->strip_local_config_values($config, $localConfig, $baseConfig);
      }
      $configStore = $this->get_system_obj('dbxConfigStore');
      $config = $configStore->normalize_for_store($config);
      $dir = $this->os_path($dir);
   
      // Konfigurationsarray in PHP-Code konvertieren
      if (is_array($config)) {
          $content .= $this->convert_array_to_php_code($config, '$config'); // Array in PHP-Code umwandeln
      }
   
      // Verzeichnis erstellen, falls es nicht existiert
      if (!is_dir($dir)) {
          mkdir($dir, 0700, true);
      }
   
      // Dateiinhalt schreiben und Erfolg prÃ¼fen
      $ok = file_put_contents($dir_file, $content);
      dbx()->debug("#CFG write ok=($ok) file=($dir_file)");
      if ($ok) {
          $runtimeConfig = $localConfig ? array_replace_recursive($config, $localConfig) : $config;
          $_SESSION['dbx']['config'][$modul] = $runtimeConfig;
          $_SESSION['dbx']['config_signature'][$modul]
             = $this->config_file_signature($dir_file) . '|' . $this->config_file_signature($local_file);
          $_SESSION['dbx']['config_file'][$modul] = $dir_file;
          $this->register_editor_file('config', $dir_file);
      }
   
   
      return $ok ?: 0; // Gibt 0 zurÃ¼ck, falls `file_put_contents` fehlschlÃ¤gt
   }

   /**
    * Fuehrt einen lokalen Konfigurationsausschnitt rekursiv zusammen.
    *
    * Bestehende Passwoerter oder andere lokale Werte bleiben erhalten, wenn
    * der Patch nur DD-Serverbindungen ergaenzt.
    */
   public function patch_local_config(string $modul, array $patch): int {
      if (preg_match('/^[A-Za-z0-9_]+$/', $modul) !== 1) {
         return 0;
      }

      $localFile = $this->os_path(
         $this->get_base_dir() . "dbx/modules/$modul/cfg/config.local.php"
      );
      $localConfig = $this->read_config_file($localFile);
      $localConfig = array_replace_recursive($localConfig, $patch);

      return $this->set_cfg($modul, $localConfig, 'local');
   }

   /**
    * Ersetzt genau einen lokalen Konfigurationsbereich.
    *
    * Im Gegensatz zu `patch_local_config()` koennen damit entfernte
    * DD-Bindungen wirklich geloescht werden, ohne andere lokale Bereiche wie
    * Datenbankpasswoerter anzutasten.
    */
   public function set_local_config_section(
      string $modul,
      string $section,
      array $value
   ): int {
      if (preg_match('/^[A-Za-z0-9_]+$/', $modul) !== 1
          || preg_match('/^[A-Za-z0-9_]+$/', $section) !== 1
      ) {
         return 0;
      }

      $localFile = $this->os_path(
         $this->get_base_dir() . "dbx/modules/$modul/cfg/config.local.php"
      );
      $localConfig = $this->read_config_file($localFile);
      $localConfig[$section] = $value;

      return $this->set_cfg($modul, $localConfig, 'local');
   }

   /**
    * Sendet eine E-Mail ueber dbxMail/PHPMailer.
    *
    * Beispiel:
    * ```php
    * dbx()->send_mail(
    *   'sender@example.org',
    *   'kunde@example.org',
    *   'Betreff',
    *   '<p>Hallo</p>',
    *   'html',
    *   array('/tmp/rechnung.pdf')
    * );
    * ```
    *
    * @param mixed  $from        String, "Name <mail>" oder array('email'=>..., 'name'=>...)
    * @param mixed  $to          String, CSV/Semikolon-Liste oder Array
    * @param string $subject     Betreff
    * @param string $body        HTML- oder Textinhalt
    * @param string $format      html, text oder txt
    * @param mixed  $attachments Datei, Dateien oder Attachment-Arrays
    * @param array  $options     cc, bcc, reply_to, text, html, mail, mail_profile
    * @return int 1 bei Erfolg, 0 bei Fehler
    */
   public function send_mail($from, $to, string $subject = '', string $body = '', string $format = 'html', $attachments = array(), array $options = array()): int {
      $mail = $this->get_system_obj('dbxMail');
      if (!$mail) {
         return 0;
      }

      $mail->init();
      $options['from'] = $from;

      $format = strtolower(trim($format));
      if ($format === 'txt') {
         $format = 'text';
      }

      if ($format === 'html') {
         $mail->bodyhtml($body);
         if (isset($options['text'])) {
            $mail->bodytext((string) $options['text']);
         }
      } else {
         $mail->bodytext($body);
      }

      foreach ($this->normalize_mail_attachments($attachments) as $attachment) {
         if (is_array($attachment)) {
            $mail->attachfile(
               (string) ($attachment['path'] ?? $attachment['file'] ?? ''),
               (string) ($attachment['disposition'] ?? 'attachment'),
               (string) ($attachment['content_type'] ?? $attachment['type'] ?? ''),
               (string) ($attachment['cid'] ?? '')
            );
         } else {
            $mail->attachfile((string) $attachment);
         }
      }

      return (int) $mail->send($to, $subject, $options);
   }

   private function normalize_mail_attachments($attachments): array {
      if ($attachments === null || $attachments === '') {
         return array();
      }

      if (is_string($attachments)) {
         return array_filter(array_map('trim', preg_split('/[;,]+/', $attachments)));
      }

      if (is_array($attachments)) {
         if (isset($attachments['path']) || isset($attachments['file'])) {
            return array($attachments);
         }
         return $attachments;
      }

      return array();
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
      // Initialisierung der RÃ¼ckgabevariable
      $value = $default;
      $danger_value = '';
   
      // Wenn das Modul nicht angegeben ist, den aktiven Modulnamen aus der Session holen
      if ($modul == 'modul') {
          $modul = $this->get_system_var('dbx_activ_modul', 'dbx', '*');
      }
   
      // ÃœberprÃ¼fen, ob die Variable in der Session unter der richtigen Modul-Sektion existiert
      if (isset($_SESSION['dbx']['remember'][$modul][$varname])) {
          $value = $_SESSION['dbx']['remember'][$modul][$varname];
      }
   
      // RÃ¼ckgabe des validierten Werts oder des Standardwerts
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

   /**
    * Normalisiert einen Action-Scope kompatibel zur bisherigen API.
    *
    * Bestehende Modul-Scopes bleiben dadurch gueltig. Neue automatisch
    * erzeugte Routen-Scopes bestehen bereits nur aus sicheren Zeichen.
    *
    * @param string $scope Fachlicher oder kanonischer Action-Scope.
    * @return string Normalisierter Scope.
    */
   private function normalize_action_scope(string $scope): string {
     return preg_replace('/[^a-zA-Z0-9_.:-]/', '', $scope) ?: 'global';
   }

   /**
    * Liefert das einzige sessiongebundene Secret fuer alle Action-Tokens.
    *
    * Anders als die fruehere Ablage eines Zufallstokens je Scope bleibt der
    * Sessionbedarf konstant. Das Secret wird nur einmal je Session erzeugt;
    * konkrete Tokens werden danach stateless per HMAC abgeleitet.
    *
    * @return string 64-stelliges Hex-Secret.
    */
   private function action_token_secret(): string {
     $secret = (string)$this->get_session_var('action_token_secret', '', 'security', 'dbx');

     if (!preg_match('/^[a-f0-9]{64}$/', $secret)) {
       $secret = bin2hex(random_bytes(32));
       $this->set_session_var('action_token_secret', $secret, 'security', 'dbx');
     }

     return $secret;
   }

   /**
    * Liefert ein sessiongebundenes Token fuer zustandsaendernde Link-Aktionen.
    *
    * Das Token wird aus einem einzigen Session-Secret und dem Scope abgeleitet.
    * Dadurch koennen auch RID-spezifische Scopes verwendet werden, ohne fuer
    * jede RID einen weiteren Sessioneintrag anzulegen.
    *
    * Bestehende Aufrufe bleiben kompatibel:
    *
    * ```php
    * $token = dbx()->action_token('myModule.delete.17');
    * ```
    *
    * Modulcode nutzt stattdessen `action_url()`. Standardaktionen wie
    * `delete` oder `save` werden zusammen mit `rid` direkt aus der URL
    * erkannt; Scope und Transportparameter entstehen automatisch.
    *
    * @param string $scope Eindeutiger Aktionsbereich.
    * @return string Token.
    */
   public function action_token(string $scope = 'global'): string {
     $scope = $this->normalize_action_scope($scope);
     return hash_hmac('sha256', 'dbx-action-v2|' . $scope, $this->action_token_secret());
   }

   /**
    * Prueft ein sessiongebundenes Aktions-Token.
    *
    * Leere Tokens werden ohne Session-Schreibzugriff verworfen. Gueltige Tokens
    * verwenden ausschliesslich das konstante, sessiongebundene HMAC-Secret.
    *
    * @param string $scope Eindeutiger Aktionsbereich.
    * @param string $token Uebergebenes Token.
    * @return bool
    */
   public function check_action_token(string $scope = 'global', string $token = ''): bool {
     if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
       return false;
     }

     $scope = $this->normalize_action_scope($scope);

     // Eine Pruefung darf niemals erst ein Secret und damit Sessionzustand
     // erzeugen. Das Secret entsteht ausschliesslich beim Rendern eines
     // gueltigen Aktionslinks durch action_token().
     $secret = (string)$this->get_session_var('action_token_secret', '', 'security', 'dbx');
     if (!preg_match('/^[a-f0-9]{64}$/', $secret)) {
       return false;
     }

     $expected = hash_hmac('sha256', 'dbx-action-v2|' . $scope, $secret);
     return hash_equals($expected, $token);
   }

   /**
    * Verwirft alle Action-Tokens an einer Authentifizierungsgrenze.
    *
    * Login und Logout wechseln den Sicherheitskontext. Ein zuvor gerendertes
    * Gast- oder Benutzer-Token darf danach nicht weiterverwendet werden.
    * Der naechste action_token()-Aufruf erzeugt automatisch ein neues Secret.
    *
    * @return void
    */
   public function invalidate_action_tokens(): void {
     $this->delete_session_var('action_token_secret', 'security', 'dbx');
   }

   /**
    * Tokenisiert eine automatisch erkannte zustandsaendernde Route.
    *
    * Reine Navigation bleibt unveraendert. Nur wenn `dbxWebApp` fuer die URL
    * eine Action-Policy findet (z.B. `delete`/`save` plus `rid`, eine
    * Standardaktion von `dbxReport` oder eine kompatible Moduldefinition),
    * wird `dbx_token` hinzugefuegt.
    * Ein vorhandener Transportparameter wird dabei sicher ersetzt.
    *
    * Mit `$action` und `$bindings` kann Kernel-/Komponentencode einen bereits
    * bekannten Scope explizit aufbauen. Fachmodule verwenden im Normalfall
    * nur die automatische Variante ohne diese Zusatzargumente.
    *
    * @param string $url Bestehende Modul-/Permalink-URL.
    * @param string $action Optionaler kanonischer Aktionsname.
    * @param array $bindings Optional gebundene Schluessel/Werte.
    * @return string Originale oder automatisch tokenisierte URL.
    */
   public function action_url(string $url, string $action = '', array $bindings = array()): string {
     $webApp = $this->get_system_obj('dbxWebApp');
     if (!is_object($webApp)) {
       return $url;
     }

     $scope = '';
     if ($action !== '') {
       $scope = (string)$webApp->action_scope_for_url($url, $action, $bindings);
     } else {
       $policy = $webApp->action_policy_for_url($url);
       $scope = is_array($policy) ? (string)($policy['scope'] ?? '') : '';
     }

     if ($scope === '') {
       return $url;
     }

     return (string)$webApp->append_route_params($url, array(
       'dbx_token' => $this->action_token($scope),
     ));
   }

   /**
    * Liest einen Wert des aktuellen Benutzers.
    *
    * Beispiel:
    * ```
    * $uid = dbx()->user();
    * $groups = dbx()->user('groups');
    * ```
    *
    * @param string $key Benutzerfeld; Standard ist id.
    * @return mixed
    */
   public function user($key='id') {
      $current_user = $_SESSION['dbx']['current_user'] ?? array();

      if ($this->is_admin_bypass_active()) {
         $current_user = $this->get_admin_bypass_user($current_user);
      }

      if ($key === '*') {
         return $current_user;
      }

      return $current_user[$key] ?? null;
   }

   /**
    * Liefert fuer den Admin-Bypass den Benutzerkontext der administrativen ID 1.
    *
    * Der Kontext wird nur fuer den aktuellen Request ueberlagert. Die Session
    * bleibt unveraendert, damit das Abschalten des Bypasses keinen dauerhaften
    * Login hinterlaesst.
    *
    * @param array $session_user Benutzerkontext der aktuellen Session.
    * @return array Administrativer Benutzerkontext.
    */
   private function get_admin_bypass_user(array $session_user = array()): array {
      if ((int)($session_user['id'] ?? 0) === 1) {
         return $session_user;
      }

      if ($this->admin_bypass_user_loaded || $this->admin_bypass_user_loading) {
         return $this->admin_bypass_user;
      }

      $this->admin_bypass_user = array_merge($session_user, array(
         'id'       => 1,
         'uname'    => 'admin',
         'roles'    => 'admin',
         'email'    => '',
         'name'     => 'Admin',
         'design'   => $session_user['design'] ?? 'default',
         'color'    => $session_user['color'] ?? 'default',
         'language' => $session_user['language'] ?? 'de',
         'edit'     => $session_user['edit'] ?? 0,
      ));
      $this->admin_bypass_user_loading = true;

      try {
         $session = $this->get_system_obj('dbxSession');
         if (is_object($session) && method_exists($session, 'get_current_user')) {
            $admin_user = $session->get_current_user(1);
            if (is_array($admin_user) && (int)($admin_user['id'] ?? 0) === 1) {
               $this->admin_bypass_user = $admin_user;
            }
         }
      } catch (\Throwable $e) {
         // Der sichere Admin-Fallback bleibt auch ohne verfuegbare DB erhalten.
      } finally {
         $this->admin_bypass_user_loading = false;
         $this->admin_bypass_user_loaded = true;
      }

      return $this->admin_bypass_user;
   }

   /**
    * Prueft Gruppenrechte gegen den aktuellen oder uebergebenen Benutzer.
    *
    * Beispiel:
    * ```
    * if (dbx()->can('admin')) { ... }
    * ```
    *
    * @param string|array $access_groups Erforderliche Gruppen.
    * @param string|array $user_groups Optional andere Benutzergruppen.
    * @return bool
    */
   public function can($access_groups = '', $user_groups = '') {
       $access = 0;
       if ($this->is_admin_bypass_active()) return 1;
       if ($this->is_demo_mode()) return 1;
       if ($access_groups=='*') return 1;
       if (!$access_groups)     return 1;
       $current_user_groups = !$user_groups;
       if ($current_user_groups) $user_groups = $_SESSION['dbx']['current_user']['roles'] ?? '';

       $user_groups   = $this->normalize_group_list($user_groups);
       $access_groups = $this->normalize_group_list($access_groups);

       if ($current_user_groups && in_array('authenticated', $access_groups, true) && (int)$this->user() > 0) {
           return 1;
       }
   
       foreach ($user_groups as $role) {
           if ($role === 'admin') return 1; // Immer Zugriff fÃ¼r Admins
   
           foreach ($access_groups as $group) {
               if ($group === '*' || $group === $role)  $access = 1;           
               if ($access) break 2; // Beide Schleifen abbrechen
           }
       }
   
       return $access;
   }

   public function has_group($access_groups = '', $user_groups = '') {
      return $this->can($access_groups, $user_groups);   
   }

   /**
    * Prueft, ob der Frontend-Edit-Modus aktiv ist (dbx_edit > 0).
    *
    * @return bool
    */
   public function is_dbx_edit(): bool {
      return (int) $this->get_system_var('dbx_edit', 0, 'int') > 0;
   }


   /**
    * Prueft den Zugriff auf ein Modul anhand seiner Konfiguration.
    *
    * @param string $modul Modulname.
    * @return bool
    */
   public function can_modul($modul) {
   
     $access=0;
     
     $current_user= $_SESSION['dbx']['current_user'] ?? array();
     $modul_config= $this->get_cfg($modul);
     $groups =$modul_config['groups'] ?? '';
     $uid    =$current_user['id'] ?? 0;
     $install=$this->get_system_var('dbx_install',0,'int');

     if ($this->is_admin_bypass_active()) {
        return 1;
     }
     if ($this->is_demo_mode()) {
        return 1;
     }
     if ((int)$uid === 1) {
        return 1;
     }

     $access = $install ? 1 : $this->can($groups);

     if ($access==0) $this->set_system_var('dbx_noaccess_modul',"(User=$uid Modul=$modul)");
     return $access;
   }

   /**
    * Meldet einen Benutzer im DBX-Kontext an.
    *
    * Beispiel:
    * ```
    * dbx()->login($uid, 1);
    * ```
    *
    * @param int $uid Benutzer-ID.
    * @param int $remember Remember-Login setzen.
    * @return void
    */
   public function login($uid=0,$remember=0) {
     $old=$this->user();
     dbx()->debug("API dbx_login von ($old) Zu ($uid)");
     if ($uid != $old) {
       $oSession=$this->get_system_obj('dbxSession');
       $oSession->login($uid,$remember);
   
       $page     =  $this->get_base_url();
       $from     =  $this->user('email');
       $fromname =  $this->user('name');
       $subject  = 'Login ('.$from.') on ('.$page.') User=('.$uid.')';
       $text     = $subject;
       //$this->sys_msg('info','login',$uid,$subject,'ok');
       //dbx_sendMail($from,$fromname,'login@dbxapp.de',$subject,$text,'text'); // #todo 
   
     }
   }

   /**
    * Liefert die Basis-URL des aktuellen Requests.
    *
    * @return string
    */
   public function get_base_url() {
      return $this->get_system_var('dbx_base_url','','*');  
   }

   /**
    * Liefert die Self-URL des aktuellen Requests.
    *
    * @return string
    */
   public function get_self_url() {
      return $this->get_system_var('dbx_self_url','','*');
   }

   /**
    * Liefert das Basisverzeichnis der Installation.
    *
    * @param int $cutData Legacy-Parameter fuer Data-Pfad-Kuerzung.
    * @return string Absoluter Basis-Pfad mit abschliessendem Slash.
    */
   public function get_base_dir(int $cutData = 0): string {
      if (function_exists('dbx_get_base_dir')) {
         return (string) dbx_get_base_dir($cutData);
      }

      $path = str_replace('\\', '/', dirname(__DIR__, 2)) . '/';
      if ($cutData) {
         $path = str_ends_with($path, '/Data/') ? substr($path, 0, -5) : $path;
      }
      return rtrim($path, '/') . '/';
   }

   /**
    * Liefert das files/-Verzeichnis der Installation.
    *
    * @return string Absoluter Pfad mit abschliessendem Slash.
    */
   public function get_file_dir(): string {
      if (function_exists('dbx_get_file_dir')) {
         return (string) dbx_get_file_dir();
      }

      return $this->get_base_dir() . 'files/';
   }

   /**
    * Registriert eine modul-eigene CSS-Datei aus tpl/css/ fuer die aktuelle Seite.
    *
    * Templates sollen kein eingebettetes CSS enthalten. Ein Modul lagert
    * eigenes CSS stattdessen nach dbx/modules/{modul}/tpl/css/{file} aus und
    * meldet die Datei einmalig waehrend des Renderns hier an. Das
    * Design-Template laedt alle so registrierten Dateien am Seitenende
    * client-seitig ueber core.js (dbx.add_css) nach — dieselbe Ladefunktion,
    * die auch dbx.feature.register(...).css bereits verwendet.
    *
    * Beispiel:
    * ```
    * dbx()->add_css('dbxKi', 'ki-briefing.css');
    * ```
    *
    * @param string $modul Technischer Modulname (Ordner unter dbx/modules/).
    * @param string $file Dateiname innerhalb von dbx/modules/{modul}/tpl/css/.
    * @return void
    */
   public function add_css(string $modul, string $file): void {
      $this->queue_module_asset('css', $modul, $file);
   }

   /**
    * Registriert eine modul-eigene JS-Datei aus tpl/js/ fuer die aktuelle Seite.
    *
    * Wirkung und Ladeweg wie add_css(), nur fuer
    * dbx/modules/{modul}/tpl/js/{file} und dbx.add_js().
    *
    * Beispiel:
    * ```
    * dbx()->add_js('dbxKi', 'ki-briefing.js');
    * ```
    *
    * @param string $modul Technischer Modulname (Ordner unter dbx/modules/).
    * @param string $file Dateiname innerhalb von dbx/modules/{modul}/tpl/js/.
    * @return void
    */
   public function add_js(string $modul, string $file): void {
      $this->queue_module_asset('js', $modul, $file);
   }

   /**
    * Liefert die fuer die aktuelle Seite registrierten Modul-Assets.
    *
    * Wird von dbxTPL beim Ausgeben des Design-Templates gelesen ({dbx:module_assets}).
    *
    * @param string $type 'css' oder 'js'.
    * @return array<int, string> Relative Pfade in Registrierreihenfolge, ohne Duplikate.
    */
   public function get_module_assets(string $type): array {
      return array_values($this->module_asset_queue[$type] ?? array());
   }

   /**
    * Validiert und merkt eine Modul-Asset-Datei fuer add_css()/add_js() vor.
    *
    * @param string $type 'css' oder 'js'.
    * @param string $modul Technischer Modulname.
    * @param string $file Dateiname innerhalb von tpl/{type}/.
    * @return void
    */
   private function queue_module_asset(string $type, string $modul, string $file): void {
      if ($type !== 'css' && $type !== 'js') {
         return;
      }
      $modul = trim($modul);
      $file = trim($file);
      if ($modul === '' || $file === '' || !preg_match('/^[A-Za-z0-9_]+$/', $modul)) {
         return;
      }
      // Nur einfache Dateinamen innerhalb von tpl/{type}/ - keine Pfad-Traversierung.
      if (strpos($file, '/') !== false || strpos($file, '\\') !== false || strpos($file, '..') !== false) {
         return;
      }
      $ext = '.' . $type;
      if (substr($file, -strlen($ext)) !== $ext) {
         $file .= $ext;
      }
      // Relativ zu dbx/ (dbx.add_css/add_js "root" laedt bereits ab dbx/, siehe core.js rootPath).
      $relPath = 'modules/' . $modul . '/tpl/' . $type . '/' . $file;
      if (!is_file($this->get_base_dir() . 'dbx/' . $relPath)) {
         return;
      }
      $this->module_asset_queue[$type][$modul . '/' . $file] = $relPath;
   }

   /**
    * Normalisiert einen Pfad fuer das aktuelle Betriebssystem.
    *
    * @param string $path Eingabepfad.
    * @return string Normalisierter Pfad.
    */
   public function os_path(string $path): string {
      $path = str_replace(array('\\', '//', '\\\\'), '/', $path);
      $path = preg_replace('#(?<!:)//+#', '/', $path);
      if (DIRECTORY_SEPARATOR === '\\') {
         $path = str_replace('/', '\\', $path);
      }

      $separator = DIRECTORY_SEPARATOR;
      if (!str_ends_with($path, $separator) && !str_contains(basename($path), '.')) {
         $path .= $separator;
      }
      return $path;
   }

   /**
    * Liefert die Version der aktuell ausgefuehrten dbxapp-Installation.
    *
    * Die Datei VERSION im Installationsstamm ist die einzige Quelle. Dadurch
    * zeigen auch aktualisierte Kundeninstallationen stets ihren tatsaechlich
    * installierten Stand und benoetigen keine hart codierte Versionsnummer.
    *
    * @return string Semantische Version oder eine leere Zeichenfolge bei einer
    *                unvollstaendigen beziehungsweise ungueltigen Installation.
    */
   public function get_version(): string {
      if ($this->installed_version !== null) {
         return $this->installed_version;
      }

      // VERSION besitzt absichtlich keine Dateiendung; os_path() wuerde den
      // Namen deshalb wie ein Verzeichnis behandeln und einen Slash anhaengen.
      $versionFile = str_replace('/', DIRECTORY_SEPARATOR, $this->get_base_dir() . 'VERSION');
      $version = is_file($versionFile) && is_readable($versionFile)
         ? trim((string) file_get_contents($versionFile))
         : '';

      if (preg_match('/^\d+\.\d+\.\d+(?:-dev)?$/', $version) !== 1) {
         $version = '';
      }

      $this->installed_version = $version;
      return $this->installed_version;
   }

   /** Prueft, ob ein Dateipfad absolut ist. */
   private function path_is_absolute(string $path): bool {
      $path = str_replace('\\', '/', trim($path));
      if ($path === '') return false;
      if ($path[0] === '/') return true;
      return (bool)preg_match('#^[A-Za-z]:/#', $path);
   }

   /** Speichert einen Installationspfad portabel relativ zum Projektstamm. */
   public function config_path_store(string $path, bool $dirTrailingSlash = false): string {
      $path = str_replace('\\', '/', trim($path));
      if ($path === '') return '';

      $base = str_replace('\\', '/', $this->get_base_dir());
      if ($this->path_is_absolute($path) || str_starts_with($path, $base)) {
         $absolute = str_replace('\\', '/', $this->os_path($path));
         $baseNormalized = str_replace('\\', '/', $this->os_path($base));
         if (str_starts_with($absolute, $baseNormalized)) {
            $path = substr($absolute, strlen($baseNormalized));
         }
      }

      $path = ltrim($path, '/');
      if ($dirTrailingSlash && $path !== '') {
         $path = rtrim($path, '/') . '/';
      }
      return $path;
   }

   /** Loest einen gespeicherten Projektpfad in einen Betriebssystempfad auf. */
   public function config_path_resolve(string $path): string {
      $path = str_replace('\\', '/', trim($path));
      if ($path === '') return '';
      if (!$this->path_is_absolute($path)) {
         $path = $this->get_base_dir() . ltrim($path, '/');
      }
      return $this->os_path($path);
   }

   /** Liefert die zentrale PHP-Fehlerprotokolldatei. */
   public function error_log_file(): string {
      $dir = rtrim($this->get_file_dir(), '/\\');
      if (!is_dir($dir)) @mkdir($dir, 0775, true);
      return $dir . DIRECTORY_SEPARATOR . 'dbxError.log';
   }

   /** Uebersetzt eine PHP-Fehlernummer in ihre symbolische Bezeichnung. */
   public function error_type(int $errno): string {
      $types = array(
         E_ERROR => 'E_ERROR',
         E_WARNING => 'E_WARNING',
         E_PARSE => 'E_PARSE',
         E_NOTICE => 'E_NOTICE',
         E_CORE_ERROR => 'E_CORE_ERROR',
         E_CORE_WARNING => 'E_CORE_WARNING',
         E_COMPILE_ERROR => 'E_COMPILE_ERROR',
         E_COMPILE_WARNING => 'E_COMPILE_WARNING',
         E_USER_ERROR => 'E_USER_ERROR',
         E_USER_WARNING => 'E_USER_WARNING',
         E_USER_NOTICE => 'E_USER_NOTICE',
         E_STRICT => 'E_STRICT',
         E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
         E_DEPRECATED => 'E_DEPRECATED',
         E_USER_DEPRECATED => 'E_USER_DEPRECATED',
      );
      return $types[$errno] ?? 'E_UNKNOWN';
   }

   /** Schreibt einen PHP-Fehler mit Request-Kontext in das zentrale Protokoll. */
   public function write_php_error_log(string $type, string $message, string $file = '', int $line = 0): void {
      $request = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
      $uri = $_SERVER['REQUEST_URI'] ?? '';
      $ip = $_SERVER['REMOTE_ADDR'] ?? '';
      $log = sprintf(
         "[%s] %s: %s in %s:%d | %s %s | IP=%s%s",
         date('Y-m-d H:i:s'),
         $type,
         str_replace(array("\r", "\n"), ' ', $message),
         $file,
         $line,
         $request,
         $uri,
         $ip,
         PHP_EOL
      );
      error_log($log, 3, $this->error_log_file());
   }

   /**
    * Beendet den Request mit JSON-Ausgabe.
    *
    * @param array $data Antwortdaten.
    * @param bool $withRuntime Optional Laufzeit-Metadaten und Header mitsenden.
    * @return void
    */
   public function json_response(array $data, bool $withRuntime = false): void {
      if ($withRuntime) {
         $runtimeService = $this->get_system_obj('dbxRuntime');
         $runtime = number_format($runtimeService->current_php_runtime(), 3, '.', '');
         $data['dbx_php_runtime'] = $runtime;
         $data['_dbx_runtime'] = array('php' => $runtime);
         $runtimeService->send_headers();
      }

      if (!headers_sent()) {
         header('Content-Type: application/json; charset=utf-8');
      }

      echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
   }

   /**
    * Misst Laufzeiten fuer Debug/Analyse.
    *
    * Beispiel:
    * ```
    * dbx()->timer('report', 'start');
    * // ...
    * dbx()->timer('report');
    * ```
    *
    * @param string $section Messbereich.
    * @param string $info Optionaler Beschreibungstext.
    * @return void
    */
   public function timer($section,$info='') {
      global $dbx_run_timer;
   
      $empty  = array();
      $time   = microtime(true);
      $memory = memory_get_peak_usage();
       
    
      if (!isset($dbx_run_timer[$section])) { 
         //dbx_debug("#TIMER NEW SET"); 
         $dbx_run_timer[$section]['start_time']  =$time;
         $dbx_run_timer[$section]['end_time']    =-1;
         $dbx_run_timer[$section]['start_memory']=$memory;
         $dbx_run_timer[$section]['end_memory']  =-1;
         $dbx_run_timer[$section]['time']        =-1;
         $dbx_run_timer[$section]['memory']      =-1;
         $dbx_run_timer[$section]['info']        =$info;
         $this->timer_state[$section] = array(
            'running'              => 1,
            'segment_start_time'   => $time,
            'segment_start_memory' => $memory,
            'segments'             => 0,
         );
      } else {
         $state = $this->timer_state[$section] ?? array();
         $running = (int) ($state['running'] ?? (($dbx_run_timer[$section]['end_time'] ?? -1) == -1 ? 1 : 0));

         if ($running) {
            //dbx_debug("#TIMER ADD SET");
            $segment_start_time   = (float) ($state['segment_start_time'] ?? $dbx_run_timer[$section]['start_time'] ?? $time);
            $segment_start_memory = (int) ($state['segment_start_memory'] ?? $dbx_run_timer[$section]['start_memory'] ?? $memory);
            $time_sum             = max(0, (float) ($dbx_run_timer[$section]['time'] ?? 0));
            $memory_sum           = max(0, (int) ($dbx_run_timer[$section]['memory'] ?? 0));

            $dbx_run_timer[$section]['end_time']  = $time;
            $dbx_run_timer[$section]['end_memory']= $memory;
            $dbx_run_timer[$section]['time']      = $time_sum + max(0, $time - $segment_start_time);
            $dbx_run_timer[$section]['memory']    = $memory_sum + max(0, $memory - $segment_start_memory);
            $this->timer_state[$section] = array(
               'running'              => 0,
               'segment_start_time'   => $segment_start_time,
               'segment_start_memory' => $segment_start_memory,
               'segments'             => (int) ($state['segments'] ?? 0) + 1,
            );
         } elseif ($info !== '') {
            if (($dbx_run_timer[$section]['info'] ?? '') === '') {
               $dbx_run_timer[$section]['info'] = $info;
            }

            $dbx_run_timer[$section]['end_time']    = -1;
            $dbx_run_timer[$section]['end_memory']  = -1;
            $this->timer_state[$section] = array(
               'running'              => 1,
               'segment_start_time'   => $time,
               'segment_start_memory' => $memory,
               'segments'             => (int) ($state['segments'] ?? 0),
            );
         }
      } 
      //dbx_debug("#TIMER ($section)",$dbx_run_timer);
    
   }

   /**
    * Liefert die Skin-IDs, die ein Design durch eigene `skin-*.css`-Dateien
    * tatsaechlich bereitstellt.
    *
    * Die Verzeichnisstruktur ist der verbindliche Katalog. Ein neues
    * Farbschema wird dadurch ohne zusaetzliche PHP- oder JavaScript-Liste
    * verfuegbar, sobald beispielsweise
    * `dbx/design/meindesign/css/skin-petrol.css` existiert.
    *
    * @param string $design Designname; leer verwendet das aktive Design.
    * @return array<int,string> Sichere, eindeutig sortierte Skin-IDs.
    */
   public function get_design_skin_ids(string $design = ''): array {
      static $cache = array();

      $design = $this->resolve_skin_design($design);
      if (isset($cache[$design])) {
         return $cache[$design];
      }

      $skins = array();
      $pattern = $this->get_base_dir() . 'dbx/design/' . $design . '/css/skin-*.css';
      foreach (glob($pattern) ?: array() as $file) {
         $name = pathinfo($file, PATHINFO_FILENAME);
         $skin = preg_replace('/^skin-/', '', (string)$name);
         if ($skin !== '' && preg_match('/^[a-z0-9][a-z0-9_-]*$/', $skin)) {
            $skins[$skin] = $skin;
         }
      }

      $preferred = array_flip(array('hell', 'gelb', 'rot', 'gruen', 'blau', 'dunkel'));
      uasort($skins, static function (string $a, string $b) use ($preferred): int {
         $aRank = $preferred[$a] ?? 1000;
         $bRank = $preferred[$b] ?? 1000;
         return $aRank === $bRank ? strnatcasecmp($a, $b) : $aRank <=> $bRank;
      });

      $cache[$design] = array_values($skins);
      return $cache[$design];
   }

   /**
    * Loest Design-Aliase fuer die Skin-Erkennung auf und verwirft unsichere
    * Verzeichnisnamen.
    */
   private function resolve_skin_design(string $design = ''): string {
      $design = trim($design);
      if ($design === '') {
         $design = (string)$this->get_system_var(
            'dbx_activ_design',
            $this->get_system_var('dbx_design', 'dbxapp')
         );
      }

      $key = strtolower($design);
      if ($key === 'user' || $key === 'admin') {
         $config = $this->get_cfg('dbx');
         $design = (string)($config[$key === 'admin' ? 'default_design_admin' : 'default_design_user'] ?? 'dbxapp');
      } elseif ($key === 'fleurop') {
         $design = 'flowers';
      }

      return preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]*$/', $design) ? $design : 'dbxapp';
   }

   /**
    * Normalisiert Skin-/Farbnamen auf gueltige Skin-IDs.
    *
    * @param string $skin Rohwert aus URL, Session oder Profil.
    * @param string $design Optionales Design; leer verwendet das aktive Design.
    * @return string Gueltige Skin-ID des angegebenen Designs.
    */
   public function normalize_skin(string $skin = '', string $design = ''): string {
      $skin = strtolower(trim($skin));
      $skinIds = $this->get_design_skin_ids($design);

      $map = array(
         'blue'   => 'blau',
         'blau'   => 'blau',
         'green'  => 'gruen',
         'gruen'  => 'gruen',
         'grün'   => 'gruen',
         'red'    => 'rot',
         'rot'    => 'rot',
         'black'  => 'dunkel',
         'dark'   => 'dunkel',
         'dunkel' => 'dunkel',
         'yellow' => 'gelb',
         'gelb'   => 'gelb',
         'light'  => 'hell',
         'hell'   => 'hell',
         'white'  => 'hell',
      );

      if ($skin !== '' && isset($map[$skin])) {
         $skin = $map[$skin];
      }

      if ($skin === '' || !in_array($skin, $skinIds, true)) {
         $cfg = strtolower(trim((string) $this->get_cfg('dbx', 'default_color', 'blau')));
         $skin = $map[$cfg] ?? $cfg;
      }

      if (!in_array($skin, $skinIds, true)) {
         $skin = in_array('blau', $skinIds, true)
            ? 'blau'
            : (in_array('hell', $skinIds, true) ? 'hell' : (string)($skinIds[0] ?? 'blau'));
      }

      return $skin;
   }

   /**
    * Aktiver Skin aus Systemvariable `dbx_color`.
    *
    * @return string
    */
   public function get_skin(): string {
      return $this->normalize_skin((string) $this->get_system_var('dbx_color', ''));
   }

   /**
    * CSS-Pfad zum aktiven Skin relativ zum Projektroot.
    *
    * @return string
    */
   public function get_skin_css(): string {
      $design = $this->resolve_skin_design((string)$this->get_system_var(
         'dbx_activ_design',
         $this->get_system_var('dbx_design', 'dbxapp')
      ));
      $skin = $this->normalize_skin((string)$this->get_system_var('dbx_color', ''), $design);

      return 'dbx/design/' . $design . '/css/skin-' . $skin . '.css';
   }

   /**
    * Body-Klassen fuer den aktiven Skin.
    *
    * @return string
    */
   public function get_skin_class(): string {
      $skin = $this->get_skin();
      $cls  = 'skin-' . $skin;

      if ($skin === 'dunkel') {
         $cls .= ' theme-dark';
      }

      return $cls;
   }

   /**
    * Laedt die Content-Cache-Klassen einmalig fuer Frontend, CMS und Admin.
    *
    * @return void
    */
   public function load_content_cache_classes(): void {
      static $loaded = false;
      if ($loaded) {
         return;
      }

      require_once $this->os_path($this->get_base_dir() . 'dbx/modules/dbxContent/include/dbxContent_bootstrap_sync.php');
      $loaded = true;
   }

   /**
    * Liefert den Permalink-Modus von dbxContent (`content` oder `cms`).
    *
    * @return string
    */
   public function get_content_permalink_mode(): string {
      $file = $this->os_path($this->get_base_dir() . 'dbx/modules/dbxContent/include/dbxContentLng.class.php');
      if (is_file($file)) {
         require_once $file;
      }

      return class_exists('\dbx\dbxContent\dbxContentLng')
         ? \dbx\dbxContent\dbxContentLng::permalinkMode()
         : 'content';
   }

   /**
    * Zaehlt einen fehlenden Pfad oder eine fehlende Ressource in `dbxMissing`.
    *
    * @param string $missing Permalink, Dateipfad oder Request-URI.
    * @return int Update-/Insert-Ergebnis oder 0 bei leerem Schluessel.
    */
   public function log_missing($missing = '') {
      $missing = trim((string)$missing);
      if ($missing === '') {
         return 0;
      }

      if (strlen($missing) > 250) {
         $missing = substr($missing, 0, 250);
      }

      $transactionStarted = false;

      try {
         $db = $this->get_system_obj('dbxDB');
         if (!is_object($db)) {
            return 0;
         }

         if ((int)$db->begin('dbxMissing') !== 1) {
            throw new \RuntimeException('dbxMissing-Transaktion konnte nicht gestartet werden.');
         }
         $transactionStarted = true;

         $uid = (int)$this->user();
         $rec = $db->select1('dbxMissing', array('missing' => $missing), 'id,count', 0);
         $request = $this->missing_request_source();

         if (is_array($rec) && (int)($rec['id'] ?? 0) > 0) {
            $id = (int)$rec['id'];
            $values = array(
               'count' => ((int)($rec['count'] ?? 0)) + 1,
            );
            if ($request !== '') {
               $values['request'] = $request;
            }

            if ((int)$db->update('dbxMissing', $values, $id, 0, 1, 1, 0) !== 1) {
               throw new \RuntimeException('dbxMissing-Zaehler konnte nicht aktualisiert werden.');
            }
            if ((int)$db->commit('dbxMissing') !== 1) {
               throw new \RuntimeException('dbxMissing-Transaktion konnte nicht abgeschlossen werden.');
            }
            $transactionStarted = false;
            return $id;
         }

         $values = array(
             'missing' => $missing,
             'count'   => 1,
             'owner'   => $uid,
         );
         if ($request !== '') {
            $values['request'] = $request;
         }

         $ok = $db->insert('dbxMissing', $values, 0, 1, 1, 0);
         $id = $ok ? (int)$db->get_insert_id() : 0;
         if ($id <= 0) {
            throw new \RuntimeException('dbxMissing-Eintrag konnte nicht gespeichert werden.');
         }
         if ((int)$db->commit('dbxMissing') !== 1) {
            throw new \RuntimeException('dbxMissing-Transaktion konnte nicht abgeschlossen werden.');
         }
         $transactionStarted = false;

         return $id;
      } catch (\Throwable $e) {
         if ($transactionStarted && isset($db) && is_object($db)) {
            $db->rollback('dbxMissing');
         }
         $this->write_php_error_log(get_class($e), $e->getMessage(), $e->getFile(), $e->getLine());
      }

      return 0;
   }

   /** Liefert den aufrufenden Seitenpfad ohne moeglicherweise sensible Querydaten. */
   private function missing_request_source(): string {
      $referer = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
      if ($referer === '') {
         return '';
      }

      $parts = parse_url($referer);
      if (!is_array($parts)) {
         return '';
      }

      $source = '';
      if (!empty($parts['host'])) {
         $source .= (!empty($parts['scheme']) ? strtolower((string)$parts['scheme']) : 'https') . '://';
         $source .= strtolower((string)$parts['host']);
         if (!empty($parts['port'])) {
            $source .= ':' . (int)$parts['port'];
         }
      }
      $source .= (string)($parts['path'] ?? '');
      $source = trim($source);

      return strlen($source) > 250 ? substr($source, 0, 250) : $source;
   }

   /**
    * Schreibt eine strukturierte Systemmeldung.
    *
    * Beispiel:
    * ```
    * dbx()->sys_msg('info', 'Import', $rid, 'done', '42 rows');
    * ```
    *
    * @param string $status Meldungsart, z.B. info, warning, error, login, security.
    * @param string $about Bereich oder Komponente.
    * @param mixed $rid Betroffener Datensatz oder Bezug.
    * @param string $why Grund.
    * @param mixed $what Detail oder Zusatzdaten.
    * @return int Insert-ID oder 0 bei Fehler/Schutzfall.
    */
   public function sys_msg($status = '', $about = '', $rid = '', $why = '', $what = '') {
      // Automatisierte Tests dürfen niemals die reale Systemmeldungsdatenbank
      // verändern. Einzelne, ausdrücklich darauf ausgelegte Isolationstests
      // können das Schreiben über DBX_SELFTEST_ALLOW_SYSMSG=1 freigeben.
      if ((string)getenv('DBX_SELFTEST') === '1'
          && (string)getenv('DBX_SELFTEST_ALLOW_SYSMSG') !== '1') {
         return 0;
      }

      if ($this->sys_msg_running) {
         return 0;
      }

      $this->sys_msg_running = true;

      try {
         $trace  = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
         $caller = $trace[1] ?? null;
         $file   = $caller['file'] ?? '';
         $line   = (int)($caller['line'] ?? 0);

         $status = strtolower(trim((string)$status));
         if ($status === '') {
            $status = 'info';
         }

         $level_map = array(
            'debug'    => 5,
            'info'     => 10,
            'login'    => 15,
            'notice'   => 15,
            'warning'  => 20,
            'warn'     => 20,
            'security' => 30,
            'error'    => 40,
            'fatal'    => 50,
         );

         $level = $level_map[$status] ?? 10;
         if ($status === 'warn') {
            $status = 'warning';
         }

         $sysMsgLevel = strtolower(trim((string) $this->get_cfg('dbx', 'sys_msg_level', 'all')));
         if ($sysMsgLevel === '') {
            $sysMsgLevel = 'all';
         }

         $minLevel = 0;
         if ($sysMsgLevel === 'error') {
            $minLevel = 40;
         } elseif ($sysMsgLevel === 'warning' || $sysMsgLevel === 'warn') {
            $minLevel = 20;
         }

         if ($level < $minLevel) {
            return 0;
         }

         $modul  = $this->get_modul_var('dbx_modul', $this->get_system_var('dbx_activ_modul' , 'dbx', '*'), rules: '*');
         $action = $this->get_modul_var('dbx_run1' , $this->get_system_var('dbx_activ_action', ''   , '*'), rules: '*');
         $work   = $this->get_modul_var('dbx_run2' , '', '*');

         $data_json = '';
         if (is_array($what) || is_object($what)) {
            $data_json = json_encode($what, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $what_text = $data_json ?: '';
         } else {
            $what_text = (string)$what;
         }

         $message_parts = array();
         if ((string)$about !== '') $message_parts[] = (string)$about;
         if ((string)$why   !== '') $message_parts[] = 'why=' . (string)$why;
         if ($what_text     !== '') $message_parts[] = 'what=' . $what_text;
         if ((string)$rid   !== '') $message_parts[] = 'rid=' . (string)$rid;
         $message = implode(' | ', $message_parts);

         $record = array(
            'xuser'       => $this->user('id'),
            'level'       => $level,
            'status'      => $status,
            'about'       => (string)$about,
            'rid'         => (string)$rid,
            'why'         => (string)$why,
            'what'        => $what_text,
            'message'     => $message,
            'modul'       => (string)$modul,
            'action'      => (string)$action,
            'work'        => (string)$work,
            'source_file' => (string)$file,
            'source_line' => $line,
            'data_json'   => $data_json,
         );

         $oDB = $this->get_system_obj('dbxDB');
         $prevReport = (int)$oDB->_report_error;
         $oDB->_report_error = 0;
         $ok  = $oDB->insert('dbxSysMsg', $record, 0, 1, 0, 0);
         $oDB->_report_error = $prevReport;
         $insertId = $ok ? $oDB->get_insert_id() : 0;

         dbx()->debug("##SYS-MSG### ok=($ok) id=($insertId) Level=($level) Status=($status) Modul=($modul) Action=($action) Work=($work) About=($about) RID=($rid) Why=($why) What=($what_text)");

         return (int)$insertId;
      } finally {
         $this->sys_msg_running = false;
      }
   }

   /**
    * Lagert HTML/Text in einen norep-Platzhalter aus.
    *
    * Wirkung:
    * Inhalt wird vor spaeteren Ersetzungslaeufen geschuetzt und am Ende wieder
    * eingesetzt. Sinnvoll fuer HTML, Scriptbloecke oder komplexe Fragmente.
    *
    * @param string $norep Zu schuetzender Inhalt.
    * @param int $i Optionaler Zaehler.
    * @return string Platzhalter-ID.
    */
   public function norep($norep,$i=0) {
      $norep=str_replace("\r",'',$norep);
      $norep_id='norep_'.$this->next_id();
      $_SESSION['dbx']['norep'][$norep_id]=$norep;
      return '['.$norep_id.']';
   }

   /**
    * Erzeugt eine fortlaufende ID innerhalb des DBX-Kontexts.
    *
    * @param int $add Schrittweite.
    * @return int Neue ID.
    */
   public function next_id(int $add = 1): int {
      // Aktuellen ZÃ¤hlerwert aus der "Remember"-Datenstruktur abrufen
      $i = $this->get_remember_var('dbx_next_i', 0, 'dbx');
      
      // ZÃ¤hler um den angegebenen Wert erhÃ¶hen
      $i += $add;
   
      // Aktualisierten ZÃ¤hlerwert in die "Remember"-Datenstruktur speichern
      $this->set_remember_var('dbx_next_i', $i, 'dbx');
   
      // Neuen ZÃ¤hlerwert zurÃ¼ckgeben
      return $i;
   }

   /**
    * Prueft, ob ein Modulverzeichnis existiert.
    *
    * @param string $modul Modulname.
    * @return bool
    */
   public function is_modul($modul) {
       $retval=false;
       if ($modul) {
         $modul_class_file=$this->get_base_dir()."dbx/modules/$modul/".$modul.".class.php";
         if (file_exists($modul_class_file)) $retval=true;
       }
       return $retval;
   }

   /**
    * Prueft, ob ein Design fuer die angegebene Seite existiert.
    *
    * @param string $design Designname.
    * @param string $page Seiten-/Template-Name.
    * @return bool
    */
   public function is_design($design,$page='default') {
      $admin=$this->can('admin');
      $firstchar=substr($design,0,1);
      if (!$admin && $firstchar == '_') return false;
      if (!$admin && $firstchar == '-') return false;
    
      $design_tpl=$this->get_base_dir()."dbx/design/$design/htm/$page.htm";
      if (file_exists($design_tpl)) return true;
      if ($page != 'default') {
         $design_tpl=$this->get_base_dir()."dbx/design/$design/htm/default.htm";
         if (file_exists($design_tpl)) return true;      
      }
      
      return false;
   }

   /**
    * Sendet einen Redirect oder Meta-Refresh.
    *
    * @param string $redirect Ziel-URL.
    * @param int $timer Sekunden bis Weiterleitung.
    * @return void
    */
   public function redirect($redirect, $timer = 0) {
       $timer = ($timer * 1000);
       $base  = $this->get_base_url();
       $ajax  = $this->get_system_var('dbx_ajax', 0, 'int');
    
       dbx()->debug("run redirect ($redirect)");
    
       if (!str_contains($redirect, '://')) 
          $redir = $base . $redirect;
       else 
          $redir = $redirect;
    
       dbx()->debug("#dbx_redirect  Call=($redirect)  redir=($redir) Ajax=($ajax) timer=($timer)");
    
       $redir_js = json_encode($redir);
       $allow_internal = "if(window.dbx&&dbx.utilities&&dbx.utilities.leaveGuard)"
          . "{dbx.utilities.leaveGuard.allowIfInternal($redir_js);}";
   
       if (!$timer) {
          $script = "<script>$allow_internal window.location.replace($redir_js);</script>";
       } else {
          $script = "<script>setTimeout(function() { $allow_internal window.location.replace($redir_js); }, $timer);</script>";
       }
    
       return $script;
   }

   /**
    * Setzt einen Wert in einem DBX-Cookie.
    *
    * @param string $cookie Cookie-Name.
    * @param string $key Schluessel im Cookie.
    * @param mixed $val Wert.
    * @return void
    */
   public function set_cookie_var($cookie,$key,$val) {
     $_SESSION['dbx']['cookie'][$cookie][$key]=$val;
   }

   /**
    * Prueft, ob ein Text einen Suchbegriff enthaelt.
    *
    * @param string $string Text.
    * @param string $find Suchbegriff.
    * @return bool
    */
   public function has_text($string,$find) {
      return strpos('~'.$string,$find);
   }

   /**
    * Bereitet HTML fuer die Ausgabe auf.
    *
    * @param string $html Eingabe.
    * @return string
    */
   public function html($html) {
      return htmlentities($html, ENT_QUOTES);
   }

   /**
    * Escaped einen Wert fuer HTML-Text und HTML-Attribute.
    *
    * Ungueltige UTF-8-Sequenzen werden ersetzt, damit die Ausgabe nicht
    * unerwartet leer wird. Die Methode ist der zentrale Ersatz fuer lokale
    * htmlspecialchars()-Hilfsfunktionen.
    *
    * @param mixed $value Auszugebender Wert.
    * @return string HTML-sicherer Wert.
    */
   public function esc($value): string {
      return htmlspecialchars(
         (string)$value,
         ENT_QUOTES | ENT_SUBSTITUTE,
         'UTF-8'
      );
   }

   /**
    * Standard-Optionen fuer dbx|search (wie Content-Grid).
    *
    * @param array $overrides name, placeholder, title, tooltip, errormsg,
    *                        input_class, wrap_class, wrap_style, data_role,
    *                        extra_attrs, label, value, i
    * @return array
    */
   public function search_defaults(array $overrides = array()): array {
      $defaults = array(
         'name'        => '',
         'value'       => '',
         'placeholder' => '🔍',
         'title'       => 'Suchen',
         'tooltip'     => '',
         'errormsg'    => '',
         'class'       => '',
         'input_class' => 'form-control-sm dbx-grid-search',
         'data_role'   => 'search',
         'wrap_class'  => '',
         'wrap_style'  => '',
         'label'       => '',
         'style'       => '',
         'extra_attrs' => '',
         'i'           => 0,
      );

      if (array_key_exists('placeholder', $overrides) && trim((string) $overrides['placeholder']) === '') {
         unset($overrides['placeholder']);
      }

      return array_merge($defaults, $overrides);
   }

   /**
    * Liefert einen Unix-Timestamp mit optionalem Sekunden-Offset.
    *
    * @param int $add_sec Sekunden-Offset.
    * @return int
    */
   public function timestamp($add_sec=0) {
       list($usec, $sec) = explode(" ",microtime());
       $time= ((float)  $usec + (float)$sec);
       $time=  (float) ($time + ($add_sec));
       return $time;
   }

   /**
    * Berechnet eine Zeitdifferenz zwischen zwei Timestamps.
    *
    * @param int $starttime Startzeit; 0 bedeutet jetzt.
    * @param int $endtime Endzeit; 0 bedeutet jetzt.
    * @return int
    */
   public function time_diff($starttime=0,$endtime=0) {
     if (!$starttime) $starttime=$this->timestamp();
     if (!$endtime)   $endtime  =$this->timestamp();
     return ($endtime-$starttime);
   }

   /**
    * Schneidet einen Teil zwischen zwei Markern aus einem Text.
    *
    * @param string $vor Marker vor dem Teil.
    * @param string $nach Marker nach dem Teil.
    * @param string $part Eingabetext.
    * @return string
    */
   public function part_select($vor,$nach,$part) {
     $leng= strlen($vor);
     $pos1= strpos($part, $vor);
   
     if ($pos1 === false) {
       return '';
     }
   
     $part= substr($part, ($pos1+$leng));
     $pos2= strpos($part, $nach);
   
     if ($pos2 === false) {
       return '';
     }
   
     $part= substr($part, 0,$pos2);
   
     return $part;
   }

   /**
    * Zerlegt URL-/Parameterdaten in einen Array.
    *
    * @param string $data Parameterstring.
    * @return array
    */
   public function parse_url($data) {
     if (!is_array($data)) {
       $first=substr($data,0,1);
       if ($data && $first != '=') {
          if (strpos($data,'=')) {
            parse_str($data,$xdata);
            $data=$xdata;
          }
       }
     }
     return $data;
   }

   /**
    * Prueft, ob ein Wert als Integer verwendbar ist.
    *
    * @param mixed $value Eingabewert.
    * @return bool
    */
   public function is_int_value($value) {
      if (is_int($value)) return 1;
      if (is_string($value) && filter_var($value, FILTER_VALIDATE_INT) !== false) return 1;
      return 0;
   }

   /**
    * Erzeugt ein neues Passwort.
    *
    * @param int $minlength Mindestlänge.
    * @param string $special Erlaubte Sonderzeichen.
    * @return string
    */
   public function new_password($minlength, $special = '-_!') {
       $length = (int)$minlength;
       $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789' . (string)$special;
       if ($length < 1 || $alphabet === '') {
          return '';
       }

       $password = '';
       $lastIndex = strlen($alphabet) - 1;
       for ($i = 0; $i < $length; $i++) {
          $password .= $alphabet[random_int(0, $lastIndex)];
       }

       return $password;
   }

   /** Gibt bei GET den Body aus und unterdrueckt ihn bei HEAD einheitlich. */
   private function emit_http_response_body(string $response): void {
      if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'HEAD') {
         echo $response;
      }
   }

   /** Liefert eine fertige Gastseite unveraendert aus und beendet den Request. */
   private function serve_full_page_cache_hit(string $html): void {
      $session = $this->get_system_obj('dbxSession');
      $discardedEphemeralSession = is_object($session)
         && method_exists($session, 'discard_ephemeral_anonymous_session')
         && $session->discard_ephemeral_anonymous_session(true);
      if (!$discardedEphemeralSession && session_status() === PHP_SESSION_ACTIVE) {
         // check_remember()/check_lng() liefen bereits vor dem Cache-Lesen.
         // session_abort() wuerde eine gerade ausgewaehlte Sprache, Design oder
         // Farbe nur bei Cache-HITs wieder verwerfen. PHP-Session daher sauber
         // persistieren; ein Render- oder Content-DB-Lauf bleibt trotzdem aus.
         session_write_close();
      }

      if (!headers_sent()) {
         // Der PageCache ist ausschliesslich fuer unpersonalisierte Gastseiten
         // aktiv. Die PHP-Session darf diese fertige Antwort daher weder mit
         // Set-Cookie noch mit privaten/no-cache Headern entwerten.
         header_remove('Set-Cookie');
         header_remove('Expires');
         header_remove('Pragma');
         $ttl = max(0, min(3600, (int)$this->get_cfg('dbx', 'full_page_browser_ttl', 60)));
         header('Cache-Control: public, max-age=' . $ttl . ', stale-while-revalidate=30');
         $etag = '"' . hash('sha256', $html) . '"';
         header('ETag: ' . $etag);
         $ifNoneMatch = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
         if ($ifNoneMatch !== '' && hash_equals($etag, $ifNoneMatch)) {
            http_response_code(304);
            $this->get_system_obj('dbxRuntime')->send_headers();
            exit;
         }
      }

      $this->get_system_obj('dbxRuntime')->send_headers();
      $this->emit_http_response_body($html);
      exit;
   }

   /** Rendert den eigentlichen dbXapp-Request bis zur finalen Response. */
   private function render_web_app(): string {
      $sessionId = session_id();
      $pageContent = '';
      $this->debug("#### Session #### PHP-ID=($sessionId)");

      $this->timer('system', 'full-app');
      $this->timer('system-load', 'load Kernel');

      $webApp = $this->get_system_obj('dbxWebApp');
      $session = $this->get_system_obj('dbxSession');
      $interpreter = null;
      $this->timer('system-load');

      $this->timer('session-load', 'Session load');
      $session->load_session();
      $this->timer('session-load');

      $this->timer('system-check', 'System check');
      $this->set_system_var('dbx_activ_modul', 'dbx');
      $webApp->check_request();
      $webApp->check_remember();
      // Der Installationsschalter muss vor Permalink-, Datenbank- und
      // Seiten-Cache-Zugriffen bekannt sein. Eine frische Auslieferung besitzt
      // zu diesem Zeitpunkt noch keine provisionierten DD-Tabellen.
      $webApp->check_config();
      $webApp->check_lng();
      $sync = $this->get_request_var('dbx_sync', 1, 'int');
      $installMode = (int)$this->get_system_var('dbx_install', 0, 'int') === 1;

      if (!$installMode) {
         if ($webApp->apply_canonical_home_redirect()) {
            $this->timer('system-check');
            $this->timer('system');
            return '';
         }
         if ($webApp->apply_content_permalink_redirect()) {
            $this->timer('system-check');
            $this->timer('system');
            return '';
         }

         // Fehlende statische Dateien muessen vor dem Seiten-Cache erkannt
         // werden. Normale Permalinks bleiben davon unberuehrt.
         if ($webApp->check_missing()) {
            exit;
         }

         $this->load_content_cache_classes();
         if (\dbx\dbxContent\dbxContentPageCache::prepareFullPageRequest()) {
            $cachedPage = \dbx\dbxContent\dbxContentPageCache::readFullPage();
            if ($cachedPage !== null) {
               $this->serve_full_page_cache_hit($cachedPage);
            }
         }
         $webApp->check_perma();
         \dbx\dbxContent\dbxContentPageCache::attachResolvedContentRoute();
      } else {
         // Ein beliebiger angeforderter Permalink darf den Installer nicht
         // in Content-Auflösung oder eine 404-Darstellung lenken.
         $this->set_system_var('dbx_permalink', '');
      }

      $webApp->check_design();
      $webApp->check_modul();

      $self = $this->get_self_url();
      $base = $this->get_base_url();
      $uid = $this->user();
      $ajax = $this->get_system_var('dbx_ajax', 0, 'int');
      $cache = $this->get_cfg('dbx', 'cache');
      $perma = $this->get_system_var('dbx_permalink', 'undef');
      $modul = $this->get_system_var('dbx_modul', 'undef');
      $this->debug("#DBX RUN Base-URL($base) Self=($self) Ajax=($ajax) Perma ($perma) User=($uid) SYS CACHE=($cache) ");
      $this->timer('system-check');

      $this->timer($modul, 'Master-Modul');
      $moduleContent = $webApp->run();
      $this->timer($modul);

      $this->timer('page-load', 'Page-Load');
      $this->debug("#RUN-DBXWEBAPP SYNC=$sync");
      if ($sync) {
         $pageContent = $webApp->design_load($moduleContent);
         if ($interpreter === null) {
            $interpreter = $this->get_system_obj('dbxInterpreter');
         }
         $this->timer('interpreter', 'Interpreter');
         $pageContent = $interpreter->run($pageContent);
         $this->timer('interpreter');
         $pageContent = $webApp->add_norep($pageContent);
         $pageContent = $webApp->add_editor_files_data($pageContent);
         $pageContent = $webApp->out_filter($pageContent);
      } else {
         $this->debug('no sync no output');
         http_response_code(204);
      }
      $this->timer('page-load');
      $this->timer('system');
      return (string)$pageContent;
   }

   /** Fuehrt den kompletten Frontcontroller-Ablauf aus und schreibt die Response. */
   public function run_web_app_request(): void {
      $response = $this->render_web_app();
      $syncRequest = (int)$this->get_request_var('dbx_sync', 1, 'int') === 1;

      if ($syncRequest
         && class_exists('\\dbx\\dbxContent\\dbxContentPageCache', false)
         && \dbx\dbxContent\dbxContentPageCache::isPreparedFullPageRequest()) {
         $stored = \dbx\dbxContent\dbxContentPageCache::writeFullPage($response);
         $this->debug($stored
            ? '#FULL-PAGE-CACHE stored exact final guest response'
            : '#FULL-PAGE-CACHE final response not stored');
      }

      $session = $this->get_system_obj('dbxSession');
      $this->debug("call session_save($syncRequest)");
      $discardedEphemeralSession = method_exists($session, 'discard_ephemeral_anonymous_session')
         && $session->discard_ephemeral_anonymous_session(false);
      if (!$discardedEphemeralSession) {
         $session->save_session();
         $session->clean_session();
         if (!empty($GLOBALS['dbx_session_destroy_pending'])) {
            $session->destroy_php_session();
         }
      }

      if ($syncRequest) {
         $this->get_system_obj('dbxRuntime')->send_headers();
      }
      // HEAD liefert dieselben Header wie GET, aber gemaess HTTP keinen Body.
      // Das gilt auch fuer einen Cache-MISS, der oben vollstaendig gerendert
      // werden darf, um den Cache fuer den folgenden GET vorzubereiten.
      $this->emit_http_response_body($response);

      while (ob_get_level() > 0) {
         if (!@ob_end_flush()) break;
      }

      $runtimeService = $this->get_system_obj('dbxRuntime');
      $runtimeService->debug_timer(0);
      // Vollseiten-, Ajax-/openWin- und asynchrone Requests durchlaufen
      // denselben Abschluss. Die Persistenz erfolgt nach der Response und
      // schliesst ihre eigenen DB-Zugriffe zentral von der Messung aus.
      $runtimeService->store_performance_timer();
      $this->debug('#END#');
   }

   /**
    * Schreibt eine einfache Debug-Zeile in files/dbxDebug2.txt.
    *
    * @param string $line Zu schreibende Zeile.
    * @return void
    */
   public function debug2($line) {
      $file = $this->get_file_dir() . "dbxDebug2.txt";
      $file = $this->os_path($file);
      file_put_contents($file, $line, FILE_APPEND);
   }

   /**
    * Schreibt Debug-Ausgaben in files/dbxDebug.txt, wenn Debug aktiv ist.
    *
    * Debug wird ueber files/dbxDebugActiv.txt aktiviert und im Remember-Cache
    * gehalten. Arrays werden lesbar per print_r ausgegeben.
    *
    * Beispiel:
    * ```
    * dbx()->debug('Import fertig', $rows);
    * ```
    *
    * @param string $txt Hauptmeldung.
    * @param mixed $data1 Optionale Zusatzdaten.
    * @param mixed $data2 Optionale Zusatzdaten.
    * @param mixed $data3 Optionale Zusatzdaten.
    * @return void
    */
   public function debug($txt, $data1 = '', $data2 = '', $data3 = '') {
      $activ = $this->get_remember_var('dbx_debug_activ', -1, 'dbx');
      if ($activ == -1) {
         $activ = 0;
         $debugActiv = $this->get_file_dir() . "dbxDebugActiv.txt";
         $debugActiv = $this->os_path($debugActiv);
         if (file_exists($debugActiv)) {
            $activ = 1;
         }
         $this->set_remember_var('dbx_debug_activ', $activ, 'dbx');
      }

      if (!$activ) {
         return;
      }

      $vars = '';
      $file = $this->get_file_dir() . "dbxDebug.txt";
      $file = $this->os_path($file);

      foreach (array($data1, $data2, $data3) as $data) {
         if (is_array($data)) {
            $vars .= print_r($data, true);
         } else {
            if ($data > '') {
               $vars .= $data . "\n";
            }
         }
      }

      $txt .= "\n" . $vars . "\n";
      file_put_contents($file, $txt, FILE_APPEND);
   }

   /**
    * Ermittelt den myX-Klassennamen fuer einen Systemklassen-Override.
    *
    * Beispiel: `dbxDB` wird zu `myDB`, `dbxTPL` zu `myTPL`.
    *
    * @param string $class Originale Systemklasse.
    * @return string myX-Override-Klassenname.
    */
   private function get_SysClassOverrideName(string $class): string {
      if (str_starts_with($class, 'dbx')) {
         $suffix = substr($class, 3);
         return 'my' . ($suffix ?: $class);
      }
      return 'my' . $class;
   }

   /**
    * Stellt die myX-Override-Datei fuer eine Systemklasse sicher.
    *
    * Sinn:
    * Projektanpassungen koennen in `dbx/modules/myX/sysclass` erfolgen,
    * ohne die Kernelklasse in `dbx/include` zu veraendern.
    *
    * @param string $class Originale Systemklasse.
    * @param string $myClass Override-Klasse.
    * @param string $baseClass Vollqualifizierte Basisklasse.
    * @return string Dateipfad des Overrides oder leerer String.
    */
   private function ensure_SysClassOverride(string $class, string $myClass, string $baseClass): string {
      if ($baseClass === "\\dbxUndefClass" || !class_exists($baseClass, false)) {
         return '';
      }

      $moduleDir = $this->os_path($this->get_base_dir() . 'dbx/modules/myX/');
      $sysDir    = $this->os_path($moduleDir . 'sysclass/');
      $file      = $this->os_path($sysDir . $myClass . '.class.php');

      if (!is_dir($sysDir)) {
         mkdir($sysDir, 0777, true);
      }

      $cfgDir = $this->os_path($moduleDir . 'cfg/');
      if (!is_dir($cfgDir)) {
         mkdir($cfgDir, 0777, true);
      }

      $cfgFile = $this->os_path($cfgDir . 'config.php');
      if (!file_exists($cfgFile)) {
         $this->set_cfg('myX', array(
            'version' => '1',
            'activ' => '1',
            'groups' => '*',
         ));
      }

      $moduleFile = $this->os_path($moduleDir . 'myX.class.php');
      if (!file_exists($moduleFile)) {
         file_put_contents($moduleFile, "<?php\nnamespace dbx\\myX;\n\nclass myX {\n   public function run() {\n      return 'myX system module';\n   }\n}\n");
      }

      if (!file_exists($file)) {
         $content  = "<?php\n";
         $content .= "/**\n";
         $content .= " * Auto-generated DBX system class override.\n";
         $content .= " * Requested class: $class\n";
         $content .= " * Override class: $myClass\n";
         $content .= " */\n";
         $content .= "class $myClass extends $class {\n";
         $content .= "}\n";
         file_put_contents($file, $content);
      }

      return $file;
   }

   /* =====================================================
    * Sprachhilfen (lng_*)
    * ===================================================== */

   /**
    * Aktive UI-Sprache des laufenden Requests.
    *
    * Liest die Systemvariable `dbx_lng` und liefert immer einen
    * gueltigen, kleingeschriebenen Sprachcode zurueck - nie leer.
    *
    * Beispiel:
    * ```php
    * $lng = dbx()->lng_current(); // z. B. 'de', 'en', 'es'
    * ```
    *
    * @return string Aktueller Sprachcode, Fallback 'de'.
    */
   public function lng_current(): string {
      $lng = strtolower(trim((string) $this->get_system_var('dbx_lng', 'de')));
      return $lng !== '' ? $lng : 'de';
   }

   /**
    * Konfigurierte, freigeschaltete Sprachen der Installation.
    *
    * Liest `accessible_lng` aus der dbx-Basiskonfiguration (Komma-Liste
    * oder Array) und liefert eine bereinigte Liste zweistelliger
    * Sprachcodes. Ungueltige Eintraege werden verworfen; ist danach
    * nichts uebrig, wird `['de']` zurueckgegeben.
    *
    * Beispiel:
    * ```php
    * foreach (dbx()->accessible_lngs() as $lng) {
    *     // z. B. Sprachumschalter im Menue aufbauen
    * }
    * ```
    *
    * @return string[] Liste aktiver Sprachcodes, mindestens ['de'].
    */
   public function accessible_lngs(): array {
      $raw = $this->get_cfg('dbx', 'accessible_lng', 'de');
      if ($raw === 'undef' || $raw === '' || $raw === null) {
         $raw = 'de';
      }

      $candidates = is_array($raw) ? $raw : preg_split('/\s*,\s*/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);
      $out = array();
      foreach ((array) $candidates as $val) {
         $val = strtolower(trim((string) $val));
         if ($val !== '' && $val !== 'undef' && preg_match('/^[a-z]{2,3}$/', $val)) {
            $out[] = $val;
         }
      }

      return count($out) ? $out : array('de');
   }

   /**
    * Haengt ein Sprachsuffix an einen Basisnamen: content + de => content_de.
    *
    * Wird genutzt, um sprachabhaengige DD-/Tabellennamen aus einem
    * neutralen Basisnamen zu bilden (z. B. fuer dbxContent-Tabellen,
    * die je Sprache eine eigene Tabelle besitzen).
    *
    * Beispiel:
    * ```php
    * $table = dbx()->lng_name('content', 'en'); // 'content_en'
    * $table = dbx()->lng_name('content');       // aktive Sprache, z. B. 'content_de'
    * ```
    *
    * @param string $base Basisname ohne Sprachsuffix.
    * @param string $lng  Sprachcode; leer = aktive Sprache (lng_current()).
    * @return string Basisname mit Sprachsuffix, oder '' wenn $base leer ist.
    */
   public function lng_name(string $base, string $lng = ''): string {
      $base = trim($base);
      if ($base === '') {
         return '';
      }

      $lng = strtolower(trim($lng !== '' ? $lng : $this->lng_current()));
      if ($lng === '') {
         return $base;
      }

      return $base . '_' . $lng;
   }

   /**
    * Loest eine sprachabhaengige Datei auf: name_lng.ext mit Fallback name.ext.
    *
    * Sucht zuerst `{$dir}{$name}_{$lng}.{$ext}`. Existiert die Datei nicht
    * und `$fallback` ist true, wird stattdessen die sprachneutrale Datei
    * `{$dir}{$name}.{$ext}` verwendet. Existiert keine der beiden, wird
    * ein leerer String zurueckgegeben.
    *
    * Beispiel:
    * ```php
    * // sucht z. B. tpl/htm/form-help-bar_en.htm, sonst tpl/htm/form-help-bar.htm
    * $file = dbx()->lng_resolve_file($dir, 'form-help-bar', 'htm', 'en');
    * ```
    *
    * @param string $dir      Verzeichnis mit abschliessendem '/'.
    * @param string $name     Dateiname ohne Sprachsuffix und ohne Endung.
    * @param string $ext      Dateiendung ohne fuehrenden Punkt.
    * @param string $lng      Sprachcode; leer = aktive Sprache (lng_current()).
    * @param bool   $fallback Bei fehlender Sprachdatei die neutrale Datei nutzen.
    * @return string Absoluter, os-normalisierter Pfad oder '' wenn nichts gefunden wurde.
    */
   public function lng_resolve_file(string $dir, string $name, string $ext, string $lng = '', bool $fallback = true): string {
      $dir = str_replace('\\', '/', $dir);
      if ($dir !== '' && substr($dir, -1) !== '/') {
         $dir .= '/';
      }

      $name = strtolower(trim($name));
      $ext  = ltrim(strtolower(trim($ext)), '.');
      if ($name === '' || $ext === '') {
         return '';
      }

      $lng = strtolower(trim($lng !== '' ? $lng : $this->lng_current()));
      if ($lng !== '') {
         $pathLng = $dir . $name . '_' . $lng . '.' . $ext;
         if (is_file($pathLng)) {
            return $this->os_path($pathLng);
         }
      }

      if (!$fallback) {
         return '';
      }

      $pathDef = $dir . $name . '.' . $ext;
      if (is_file($pathDef)) {
         return $this->os_path($pathDef);
      }

      return '';
   }

   /* =====================================================
    * Validierung & Codegenerierung
    * ===================================================== */

   /**
    * Validiert einen Wert gegen eine dbxValidator-Regel.
    *
    * Duenner Zugang zu dbxValidator::validate() ueber die Fassade, damit
    * Aufrufer nicht selbst `dbx()->get_system_obj('dbxValidator')` holen
    * muessen. `$rules = '*'` ueberspringt die Pruefung bewusst (z. B. fuer
    * Werte, die schon anderweitig gesichert sind).
    *
    * Beispiel:
    * ```php
    * if (!dbx()->validate_var($_POST['email'] ?? '', 'email', 'email')) {
    *     // ungueltige Eingabe behandeln
    * }
    * ```
    *
    * @param mixed  $value Zu pruefender, nicht vertrauenswuerdiger Wert.
    * @param string $rules Validierungsregel(n); '*' = keine Pruefung.
    * @param string $name  Variablenname, nur fuer Fehlermeldungen/Logging.
    * @return bool true wenn gueltig oder $rules == '*', sonst false.
    */
   public function validate_var($value, string $rules = 'parameter', string $name = 'undef'): bool {
      if ($rules === '*') {
         return true;
      }

      $oValidator = $this->get_system_obj('dbxValidator'); // #cache for speed
      return $oValidator->validate($value, $rules, $name);
   }

   /**
    * Konvertiert ein (verschachteltes) Array in rekonstruierenden PHP-Code.
    *
    * Erzeugt fuer jeden Blattwert eine Zuweisungszeile der Form
    * `$prefix['key'] = wert;`, rekursiv fuer verschachtelte Arrays. Wird
    * z. B. genutzt, um Konfigurationsarrays lesbar als PHP-Quelltext in
    * `cfg/config.local.php`-Dateien zu schreiben.
    *
    * Beispiel:
    * ```php
    * $code = dbx()->convert_array_to_php_code(['a' => 1, 'b' => ['c' => 'x']], '$config');
    * // $config['a'] = 1;
    * // $config['b']['c'] = 'x';
    * ```
    *
    * @param array  $array  Quell-Array, darf verschachtelt sein.
    * @param string $prefix Variablenname/Basispfad der Zuweisung, z. B. '$config'.
    * @return string Generierter PHP-Code, eine Zuweisung pro Blattwert.
    */
   public function convert_array_to_php_code(array $array, string $prefix): string {
      $code = '';

      foreach ($array as $key => $value) {
         $keyPart = is_numeric($key) ? "[$key]" : "['" . addslashes((string) $key) . "']";

         if (is_array($value)) {
            $code .= $this->convert_array_to_php_code($value, $prefix . $keyPart);
            continue;
         }

         if (is_string($value)) {
            $formattedValue = "'" . addslashes($value) . "'";
         } elseif (is_bool($value)) {
            $formattedValue = $value ? 'true' : 'false';
         } elseif ($value === null) {
            $formattedValue = 'null';
         } else {
            $formattedValue = $value;
         }

         $code .= "$prefix$keyPart = $formattedValue;\n";
      }

      return $code;
   }

   /**
    * Loescht ein Cookie sofort (setzt es mit einem Verfallsdatum in der Vergangenheit).
    *
    * Beispiel:
    * ```php
    * dbx()->delete_cookie('dbXwebApp');
    * ```
    *
    * @param string $cookie Name des zu loeschenden Cookies.
    * @return void
    */
   public function delete_cookie(string $cookie): void {
      setcookie($cookie, '', time() - 3600, '/');
   }

   /**
    * Prueft, ob ein dbxDB-Rueckgabewert (insert()/update()/delete()/count() ...)
    * einen verweigerten Zugriff bedeutet.
    *
    * dbxDB folgt der Konvention 1 = Erfolg, 0 = kein Treffer/keine Aenderung/
    * Validierungsfehler, -1 = Zugriff verweigert, -2 = Datenbankfehler. Da 0,
    * -1 und -2 in PHP alle falsy sind, liefert ein einfaches `if (!$result)`
    * keine Unterscheidung zwischen "nichts zu tun" und "verboten". Diese
    * Methode macht genau diesen einen Fall explizit pruefbar.
    *
    * Beispiel:
    * ```php
    * $result = dbx()->get_system_obj('dbxDB')->update('dbxUser', $values, $where);
    * if (dbx()->is_access_denied($result)) {
    *     sys_msg('Kein Zugriff.', 'error');
    * } elseif (dbx()->is_db_error($result)) {
    *     sys_msg('Datenbankfehler.', 'error');
    * }
    * ```
    *
    * @param int $result Rueckgabewert von dbxDB::insert()/update()/delete()/count() u.ae.
    * @return bool true, wenn der Rueckgabewert "Zugriff verweigert" (-1) bedeutet.
    */
   public function is_access_denied(int $result): bool {
      return $result === -1;
   }

   /**
    * Prueft, ob ein dbxDB-Rueckgabewert (insert()/update()/delete()/count() ...)
    * einen Datenbankfehler bedeutet (z. B. Verbindungsfehler oder gescheitertes SQL).
    *
    * Ergaenzt is_access_denied(): zusammen decken beide Methoden die
    * negativen, sonst nicht unterscheidbaren Fehlercodes -1 und -2 ab.
    *
    * Beispiel:
    * ```php
    * $result = dbx()->get_system_obj('dbxDB')->insert('dbxUser', $values);
    * if (dbx()->is_db_error($result)) {
    *     sys_msg('Datenbankfehler.', 'error');
    * }
    * ```
    *
    * @param int $result Rueckgabewert von dbxDB::insert()/update()/delete()/count() u.ae.
    * @return bool true, wenn der Rueckgabewert "Datenbankfehler" (-2) bedeutet.
    */
   public function is_db_error(int $result): bool {
      return $result === -2;
   }
}

/**
 * Liefert die zentrale dbXapp-API als Singleton.
 *
 * Anwendungscode soll diesen Einstieg verwenden statt globaler dbx_*-Funktionen.
 *
 * Beispiel:
 * ```
 * dbx()->set_system_var('dbx_ajax', 1);
 * $tpl = dbx()->get_system_obj('dbxTPL');
 * ```
 *
 * @return dbxApi Zentrale API-Instanz.
 */
function dbx(): dbxApi {
   static $api = null;
   if ($api === null) {
      $api = new dbxApi();
   }
   return $api;
}

