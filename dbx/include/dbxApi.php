<?php
/**
 * @file dbxApi.php
 * Zentrale Laufzeit-API und Kernel-Helfer.
 *
 * Neue DBX-Entwicklung soll ueber `dbx()` und die Methoden von dbxApi laufen.
 * Allgemeine Laufzeitfunktionen werden als Methoden dieser API bereitgestellt.
 * Verbleibende globale Fachhelfer werden schrittweise auf `dbx()` umgestellt.
 */

//include_once $this->os_path($this->get_base_dir().'dbx/add_ons/sftp/vendor/autoload.php');
//use phpseclib3\Crypt\AES;
use \phpseclib3\Crypt\AES;

Global $_dbxCache;

function dbx_log_missing_entry($missing = '') {
   return function_exists('dbx') ? (int) dbx()->log_missing($missing) : 0;
}

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

      if (isset($_dbxCache[$class])) {
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
         $_dbxCache[$class] = new $createClass();
         return $_dbxCache[$class];
      }

      throw new Exception("Klasse '$createClass' konnte nicht geladen werden.");
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
          //$admin_design = $this->get_config('dbx', 'default_design_admin');
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
    * @param string $rules Validierungsregel fuer dbx_validate_var().
    * @return mixed Validierter Wert oder Default.
    */
   public function get_system_var(string $varname, $default = '', string $rules = '*') {
      // Initialisierung der RÃ¼ckgabevariable
      $value = $default;
      $danger_value = '';
   
      // ÃœberprÃ¼fen, ob die Variable in der Session vorhanden ist
      if (isset($_SESSION['dbx']['tmp'][0]['dbx'][$varname])) {
          $danger_value = $_SESSION['dbx']['tmp'][0]['dbx'][$varname];
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
          if (dbx_validate_var($danger_value, $rules, $varname)) {
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
    * Veraendert den globalen Laufkontext in $_SESSION['dbx']['tmp'][0]['dbx'].
    *
    * @param string $varname Name der Systemvariable.
    * @param mixed $value Neuer Wert.
    * @return void
    */
   public function set_system_var(string $varname, $value) {
      // Speichert den Wert der Systemvariable in der Session
      $_SESSION['dbx']['tmp'][0]['dbx'][$varname] = $value;
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
      if (isset($_SESSION['dbx']['tmp'][$mid][$modul][$varname])) {
          $danger_value = $_SESSION['dbx']['tmp'][$mid][$modul][$varname];
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
      if ($danger_value !== '' && $danger_value !== null && dbx_validate_var($danger_value, $rules, $varname)) {
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
         if (isset($_SESSION['dbx']['tmp'][$mid][$modul]['dbx_protected_modulvars'])) {
            $protected = $_SESSION['dbx']['tmp'][$mid][$modul]['dbx_protected_modulvars'];
         }
         if (is_array($protected) && array_key_exists($varname, $protected)) {
            dbx()->debug("PROTECTED ($varname)");
            return;
         }   
      }
   
      // Setze den Wert in der Session
      $_SESSION['dbx']['tmp'][$mid][$modul][$varname] = $value;
   
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
    * $cache = dbx()->get_config('dbx', 'cache');
    * $config = dbx()->get_config('dbx');
    * ```
    *
    * Wirkung:
    * Liest Modulkonfiguration aus Dateien/Cache und liefert entweder den
    * kompletten Config-Array oder einen einzelnen Schluessel.
    *
    * @param string $modul Modulname.
    * @param string $key Optionaler Config-Schluessel.
    * @param mixed $default Rueckgabewert, wenn der Schluessel nicht existiert.
    * @return mixed
    */
   public function get_config(string $modul = 'dbx', string $key = '', $default = null) {
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

          return $val;
      }
   
      // Gesamte Konfiguration zurÃ¼ckgeben
      return $config;
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
      $content = file_get_contents($dir_file);
      if (!is_string($content) || $content === '') {
         return array();
      }

      $clean_code = str_replace(array('<?php', '?>'), '', $content);
      set_error_handler(function ($errno, $errstr, $errfile, $errline) {
         throw new \Exception("Fehler in config.php: $errstr in Zeile $errline");
      });

      try {
         eval($clean_code);
      } catch (\Throwable $e) {
         $this->debug('#CFG read failed file=(' . $dir_file . ') error=(' . $e->getMessage() . ')');
         $config = array();
      } finally {
         restore_error_handler();
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
    * dbx()->set_config('dbx', $config);
    * ```
    *
    * @param string $modul Modulname.
    * @param array $config Zu speichernde Konfiguration.
    * @return int Anzahl geschriebener Bytes oder 0 bei Fehler.
    */
   public function set_config(string $modul, array $config): int {
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
          $content .= dbx_convertArrayToPHPCode($config, '$config'); // Array in PHP-Code umwandeln
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
    * Leere Tokens werden ohne Session-Schreibzugriff verworfen. Fuer bereits
    * geoeffnete Seiten einer laufenden Session werden vor der Umstellung
    * gespeicherte Scope-Tokens weiterhin akzeptiert. Neue Tokens verwenden
    * ausschliesslich die konstante HMAC-Ablage.
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

     // Kompatibilitaet fuer Links, die vor dem HMAC-Wechsel gerendert wurden.
     // Wichtig: Bei unbekannten Scopes wird hier kein neuer Eintrag erzeugt.
     $legacyTokens = $this->get_session_var('action_tokens', array(), 'security', 'dbx');
     if (is_array($legacyTokens)
         && isset($legacyTokens[$scope])
         && preg_match('/^[a-f0-9]{64}$/', (string)$legacyTokens[$scope])
         && hash_equals((string)$legacyTokens[$scope], $token)) {
       return true;
     }

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
    * Die Legacy-Ablage wird ebenfalls entfernt, damit alte geoeffnete Links
    * keinen Wechsel des angemeldeten Benutzers ueberleben.
    *
    * @return void
    */
   public function invalidate_action_tokens(): void {
     $this->delete_session_var('action_token_secret', 'security', 'dbx');
     $this->delete_session_var('action_tokens', 'security', 'dbx');
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

      if (defined('dbxRunAsAdmin') && (int) constant('dbxRunAsAdmin') === 1) {
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
       if (defined('dbxRunAsAdmin') && (int) constant('dbxRunAsAdmin') === 1) return 1;
       if ($access_groups=='*') return 1;
       if (!$access_groups)     return 1;
       $current_user_groups = !$user_groups;
       if ($current_user_groups) $user_groups = $_SESSION['dbx']['current_user']['roles'] ?? '';

       if (!is_array($user_groups))    $user_groups   = explode(',', $user_groups);
       if (!is_array($access_groups))  $access_groups = explode(',', $access_groups);
       $user_groups   = array_map('trim', $user_groups);
       $access_groups = array_map('trim', $access_groups);

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
     $modul_config= $this->get_config($modul);
     $groups =$modul_config['groups'] ?? '';
     $uid    =$current_user['id'] ?? 0;
     $install=$this->get_system_var('dbx_install',0,'int');

     if (defined('dbxRunAsAdmin') && (int) constant('dbxRunAsAdmin') === 1) {
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
         $config = $this->get_config('dbx');
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
         $cfg = strtolower(trim((string) $this->get_config('dbx', 'default_color', 'blau')));
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

         $sysMsgLevel = strtolower(trim((string) $this->get_config('dbx', 'sys_msg_level', 'all')));
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
   
       if (!$timer) {
          $script = "<script>window.location.replace($redir_js);</script>";
       } else {
          $script = "<script>setTimeout(function() { window.location.replace($redir_js); }, $timer);</script>";
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

      $data = array_merge($defaults, $overrides);
      if (trim((string)$data['tooltip']) === '') {
         $data['tooltip'] = (string)$data['title'];
      }
      return $data;
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
    * @param int $minlength Mindestlaenge.
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
         $ttl = max(0, min(3600, (int)$this->get_config('dbx', 'full_page_browser_ttl', 60)));
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
      $webApp->check_lng();

      // Fehlende statische Dateien muessen vor dem Seiten-Cache erkannt
      // werden. Normale Permalinks bleiben davon unberuehrt.
      if ($webApp->check_missing()) {
         exit;
      }

      $sync = $this->get_request_var('dbx_sync', 1, 'int');
      $this->load_content_cache_classes();
      if (\dbx\dbxContent\dbxContentPageCache::prepareFullPageRequest()) {
         $cachedPage = \dbx\dbxContent\dbxContentPageCache::readFullPage();
         if ($cachedPage !== null) {
            $this->serve_full_page_cache_hit($cachedPage);
         }
      }
      $webApp->check_perma();
      \dbx\dbxContent\dbxContentPageCache::attachResolvedContentRoute();
      $webApp->check_config();
      $webApp->check_design();
      $webApp->check_modul();

      $self = $this->get_self_url();
      $base = $this->get_base_url();
      $uid = $this->user();
      $ajax = $this->get_system_var('dbx_ajax', 0, 'int');
      $cache = $this->get_config('dbx', 'cache');
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
      if ($syncRequest && (int)$this->get_system_var('dbx_ajax', 0, 'int') !== 1) {
         $runtimeService->store_performance_timer();
      }
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
         file_put_contents($cfgFile, "<?php\n\$config['version']='1';\n\$config['activ']='1';\n\$config['groups']='*';\n");
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


/**
 * Prueft einen projektspezifischen Stichtag.
 *
 * @return bool true ab 2025-06-01.
 */
function ge_stichtag() {
    $heute  = new DateTime();
    $grenze = new DateTime('2025-06-01');
    return $heute >= $grenze;
}


/**
 * Kopiert ein komplettes Verzeichnis rekursiv.
 *
 * @param string $src Quellverzeichnis (mit / am Ende oder ohne)
 * @param string $dst Zielverzeichnis (mit / am Ende oder ohne)
 * @return bool true bei Erfolg, false bei Fehler
 */
function dbx_copy_recursive($src, $dst) {
    $src = rtrim($src, '/\\');
    $dst = rtrim($dst, '/\\');

    if (!is_dir($src)) {
        dbx()->debug("dbx_copy_recursive Error DIR");
        return 0;
    }

    if (!file_exists($dst)) {
        if (!mkdir($dst, 0777, true)) {
            dbx()->debug("dbx_copy_recursive mkdir Error($dst)");
            return 0;
        }
    }

    $items = scandir($src);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $srcPath = $src . DIRECTORY_SEPARATOR . $item;
        $dstPath = $dst . DIRECTORY_SEPARATOR . $item;

        if (is_dir($srcPath)) {
            // Rekursiv fÃ¼r Verzeichnisse
            if (!dbx_copy_recursive($srcPath, $dstPath)) {
                dbx()->debug("dbx_copy_recursive Error A");
                return 0;
            }
        } else {
            // Datei kopieren (Ã¼berschreiben erlaubt)
            if (!copy($srcPath, $dstPath)) {
                dbx()->debug("dbx_copy_recursive Error B");

                return 0;
            }
        }
    }

    return 1;
}




/**
 * Liefert ein DBX-Datum/Zeit-Format mit optionalem Offset.
 *
 * Beispiel:
 * ```php
 * echo dbx_DateTime('now', 3600); // aktuelle Zeit plus eine Stunde
 * echo dbx_DateTime('2026-06-06 10:00:00', 0, '+2 days');
 * ```
 *
 * @param string $date_time Ausgangszeit oder `now`.
 * @param int $calc Sekundenoffset.
 * @param string $special strtotime-Ausdruck relativ zur Ausgangszeit.
 * @return string Datum/Zeit im Format `Y-m-d H:i:s`.
 */
function dbx_DateTime($date_time='now',$calc=0,$special='') {
   $timezone = 'Europe/Berlin';
   $offset   = 0;  // Offset Sommer/Winter

   if ($date_time=='now') {
      $offset   = (60*60*$offset);
      $calc=($calc + $offset);
      $date_time = date("Y-m-d  H:i:s", (time()  + $calc));
   } else {
      $offset   = (60*60*$offset);
      $calc=($calc + $offset);
      $date_time = date("Y-m-d  H:i:s", (strtotime($date_time) + $calc ));
   }
    
   if ($special) {
      $time=strtotime($date_time);
      $date_time=date("Y-m-d  H:i:s", strtotime($special, $time));
   }


   $week_start  = strtotime('last Sunday', time());
   $week_end    = strtotime('next Sunday', time());
   
   $month_start = strtotime('first day of this month', time());
   $month_end   = strtotime('last day of this month', time());
   
   $year_start  = strtotime('first day of January', time());
   $year_end    = strtotime('last day of December', time());

    //$now_date = gmdate("Y-m-d  H:i:s", (time()  + $calc));
    return  $date_time;
}


/**
 * Erstellt ein Verzeichnis und alle notwendigen Unterverzeichnisse.
 *
 * @param string $path Der Pfad des zu erstellenden Verzeichnisses.
 * @return int 1 bei Erfolg oder wenn das Verzeichnis bereits existiert, 0 bei Fehler.
 *
 * Beispiel:
 * ```php
 * $path = '/var/www/html/myBefund/ldt-in/';
 * $ok   = dbx_make_dir($path);
 * echo $ok ? 'Verzeichnis erstellt oder existiert bereits' : 'Fehler beim Erstellen';
 * ```
 */
function dbx_make_dir(string $path): int {
    // Prüfen, ob das Verzeichnis bereits existiert
    if (is_dir($path)) return 1;

    // Versuchen, das Verzeichnis rekursiv zu erstellen
    if (mkdir($path, 0777, true)) {
        return 1; // Erfolgreich erstellt
    }

    return 0; // Fehler beim Erstellen
}


/**
 * Kompatibilitaetswrapper fuer dbx()->send_mail().
 *
 * @param string $from Absender-E-Mail.
 * @param string $fromname Absendername.
 * @param string|array $to Empfaenger.
 * @param string $subject Betreff.
 * @param string $text Inhalt.
 * @param string $type html|text.
 * @param mixed $attach Anhaenge.
 * @param int $archiv Historischer Parameter, aktuell nicht ausgewertet.
 * @return int 1 bei Erfolg, 0 bei Fehler.
 */
function dbx_sendMail($from,$fromname,$to,$subject,$text,$type='html',$attach='',$archiv=0) {
    $from = array('email' => $from, 'name' => $fromname);
    return dbx()->send_mail($from, $to, (string) $subject, (string) $text, (string) $type, $attach);
}


/**
 * Prueft, ob ein historisches Template unter dbx/tpl/htm existiert.
 *
 * @param string $page Template-/Seitenname.
 * @param string $design Historischer Parameter.
 * @param string $lng Historischer Parameter.
 * @return bool
 */
function dbx_is_page($page,$design,$lng='') {
    $retval=false;
    $page_tpl=dbx()->get_base_dir()."dbx/tpl/htm/$page.htm";
    if (file_exists($page_tpl)) $retval=true;
    return $retval;
}




 
   


/**
 * LÃ¤dt eine Klasse aus dem Cache oder erstellt eine neue Instanz der Klasse.
 *
 * Die Funktion versucht, ein Objekt der angegebenen Klasse zu laden:
 * 1. Wenn das Objekt bereits im Cache vorhanden ist, wird es zurÃ¼ckgegeben.
 * 2. Andernfalls wird die entsprechende Klassen-Datei eingebunden und ein neues Objekt erstellt.
 * 
 * @param string $class Der Name der Klasse, die geladen werden soll.
 * @param string $use (Optional) Kann verwendet werden, um andere Logiken basierend auf der Klasse zu implementieren. 
 *                    StandardmÃ¤ÃŸig leer.
 * @return object|null Gibt das Klassen-Objekt zurÃ¼ck oder `null`, falls ein Fehler auftritt oder `$use` gesetzt ist.
 * @throws Exception Wenn die Klasse nicht geladen werden kann und keine Fallback-Klasse definiert ist.
 */
/**
 * LÃ¤dt ein Modul-Objekt basierend auf dem Modulnamen und aktualisiert zugehÃ¶rige System- und Modulvariablen.
 *
 * Diese Funktion:
 * 1. Erzeugt den vollqualifizierten Klassennamen des Moduls.
 * 2. Aktualisiert System- und Modulvariablen wie `dbx_activ_modul_id`, `dbx_activ_modul` und Design-Informationen.
 * 3. LÃ¤dt die Klassen-Datei des Moduls und erstellt eine Instanz der Klasse.
 * 
 * @param string $class Der Name des Moduls, das geladen werden soll.
 * @return object Gibt eine Instanz des Modul-Objekts zurÃ¼ck.
 * @throws Exception Wenn die Klassen-Datei des Moduls fehlt oder die Klasse nicht geladen werden kann.
 */
/**
 * LÃ¤dt eine Klasse aus dem `include`-Ordner eines Moduls und erstellt ein Objekt.
 *
 * Die Funktion sucht nach einer Klassen-Datei im `include`-Ordner des angegebenen Moduls
 * oder des aktuell aktiven Moduls, lÃ¤dt diese und erstellt eine Instanz der Klasse.
 *
 * @param string $class Der Name der Klasse, die geladen werden soll.
 * @param string $modul (Optional) Der Name des Moduls, zu dem die Klasse gehÃ¶rt. 
 *                      StandardmÃ¤ÃŸig wird das aktuell aktive Modul verwendet.
 * @param string $use (Optional) ZusÃ¤tzliche Steuerung, ob ein Objekt zurÃ¼ckgegeben wird. Standard: leer.
 * @return object|null Gibt eine Instanz des Klassen-Objekts zurÃ¼ck oder `null`, wenn `$use` gesetzt ist.
 * @throws Exception Wenn die Klassen-Datei fehlt oder die Klasse nicht geladen werden kann.
 */
/**
 * Aktive UI-Sprache (SysVar dbx_lng).
 *
 * @return string z. B. de, en, es
 */
function dbx_lng_current(): string {
   $lng = strtolower(trim((string) dbx()->get_system_var('dbx_lng', 'de')));
   return $lng !== '' ? $lng : 'de';
}

/**
 * Konfigurierte Sprachen aus dbx-Config.
 *
 * @return string[]
 */
function dbx_accessible_lngs(): array {
   $raw = dbx()->get_config('dbx', 'accessible_lng', 'de');
   if ($raw === 'undef' || $raw === '' || $raw === null) {
      $raw = 'de';
   }
   if (is_array($raw)) {
      $out = array();
      foreach ($raw as $val) {
         $val = strtolower(trim((string) $val));
         if ($val !== '' && $val !== 'undef' && preg_match('/^[a-z]{2,3}$/', $val)) {
            $out[] = $val;
         }
      }
      return count($out) ? $out : array('de');
   }

   $parts = preg_split('/\s*,\s*/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);
   $out = array();
   if (is_array($parts)) {
      foreach ($parts as $val) {
         $val = strtolower(trim((string) $val));
         if ($val !== '' && $val !== 'undef' && preg_match('/^[a-z]{2,3}$/', $val)) {
            $out[] = $val;
         }
      }
   }

   return count($out) ? $out : array('de');
}

/**
 * Sprachsuffix an Basisnamen haengen: content + de => content_de.
 *
 * @param string $base  Basis ohne Sprachsuffix
 * @param string $lng   Sprache; leer = aktive Sprache
 * @return string
 */
function dbx_lng_name(string $base, string $lng = ''): string {
   $base = trim($base);
   if ($base === '') {
      return '';
   }

   $lng = strtolower(trim($lng !== '' ? $lng : dbx_lng_current()));
   if ($lng === '') {
      return $base;
   }

   return $base . '_' . $lng;
}

/**
 * Datei mit Sprachsuffix aufloesen: name_lng.ext mit Fallback name.ext.
 *
 * @param string $dir       Verzeichnis mit abschliessendem /
 * @param string $name      Dateiname ohne Endung
 * @param string $ext       Endung ohne Punkt
 * @param string $lng       Sprache; leer = aktive Sprache
 * @param bool   $fallback  Neutralen Namen als Fallback nutzen
 * @return string Absoluter Pfad oder leer
 */
function dbx_lng_resolve_file(string $dir, string $name, string $ext, string $lng = '', bool $fallback = true): string {
   $dir = str_replace('\\', '/', $dir);
   if ($dir !== '' && substr($dir, -1) !== '/') {
      $dir .= '/';
   }

   $name = strtolower(trim($name));
   $ext  = ltrim(strtolower(trim($ext)), '.');
   if ($name === '' || $ext === '') {
      return '';
   }

   $lng = strtolower(trim($lng !== '' ? $lng : dbx_lng_current()));
   if ($lng !== '') {
      $pathLng = $dir . $name . '_' . $lng . '.' . $ext;
      if (is_file($pathLng)) {
         return dbx()->os_path($pathLng);
      }
   }

   if (!$fallback) {
      return '';
   }

   $pathDef = $dir . $name . '.' . $ext;
   if (is_file($pathDef)) {
      return dbx()->os_path($pathDef);
   }

   return '';
}

/**
 * Wendet die modulbezogene translate.php auf Inhalt an.
 *
 * Beispiel:
 * ```php
 * $html = dbx_modul_translate($html, 'dbxContent', 'de');
 * ```
 *
 * @param string $content Zu uebersetzender Inhalt.
 * @param string $modul Modulname; leer nutzt aktives Modul.
 * @param string $lng Sprache; leer nutzt aktive Sprache.
 * @return string Uebersetzter Inhalt.
 */
function dbx_modul_translate($content,$modul='',$lng='') {
   if (!$modul) $modul=dbx()->get_system_var('dbx_activ_modul','dbx');
   if (!$lng)     $lng=dbx()->get_system_var('dbx_lng','de');
   $dir_file=dbx()->get_base_dir()."dbx/modules/$modul/translate.php";
   $dir_file=dbx()->os_path($dir_file);
   if (file_exists($dir_file)) {
      include $dir_file;
   }
   return $content;
}

/**
 * Ersetzt das erste Vorkommen einer Zeichenfolge.
 *
 * @param string $search_str Gesuchte Zeichenfolge.
 * @param string $replacement_str Ersatztext.
 * @param string $src_str Quelltext.
 * @return string Text mit hoechstens einer Ersetzung.
 */
function dbx_replace_first($search_str, $replacement_str, $src_str){
  return (false !== ($pos = strpos($src_str, $search_str))) ? substr_replace($src_str, $replacement_str, $pos, strlen($search_str)) : $src_str;
}


/**
 * Konvertiert ein Array in PHP-Code, der es rekonstruiert.
 *
 * Diese Funktion:
 * - Traversiert ein Array rekursiv.
 * - Generiert PHP-Code, um das Array durch Zuweisungen zu rekonstruieren.
 * - UnterstÃ¼tzt verschachtelte Arrays und escapt automatisch Strings.
 *
 * @param array $array Das Array, das konvertiert werden soll.
 * @param string $prefix Der Prefix, der die Basisvariable oder den Startpunkt angibt.
 * @return string Der generierte PHP-Code, der das Array rekonstruiert.
 */
function dbx_convertArrayToPHPCode(array $array, string $prefix): string {
   $code = "";

   foreach ($array as $key => $value) {
       // Generiere den SchlÃ¼ssel (numerisch oder als String)
       $keyPart = is_numeric($key) ? "[$key]" : "['" . addslashes($key) . "']";

       if (is_array($value)) {
           // Rekursive Verarbeitung fÃ¼r verschachtelte Arrays
           $code .= dbx_convertArrayToPHPCode($value, $prefix . $keyPart);
       } else {
           // Wert formatieren
           if (is_string($value)) {
               // Strings escapen (inklusive Backslashes)
               $formattedValue = "'" . addslashes($value) . "'";
           } elseif (is_bool($value)) {
               // Booleans in `true` oder `false` konvertieren
               $formattedValue = $value ? 'true' : 'false';
           } elseif ($value === null) {
               // `null` als Wert setzen
               $formattedValue = 'null';
           } else {
               // Andere Datentypen (z. B. Zahlen) direkt verwenden
               $formattedValue = $value;
           }

           // PHP-Code-Zuweisung generieren
           $code .= "$prefix$keyPart = $formattedValue;\n";
       }
   }

   return $code;
}


/**
 * LÃ¤dt die Konfigurationsdaten eines Moduls aus einer Datei (verschlÃ¼sselt oder unverschlÃ¼sselt) und speichert sie im Session-Cache.
 *
 * Diese Funktion:
 * - Liest die Konfigurationsdatei des angegebenen Moduls.
 * - UnterstÃ¼tzt verschlÃ¼sselte und unverschlÃ¼sselte Konfigurationsdateien.
 * - LÃ¤dt die Konfiguration in den Session-Cache, um wiederholte Dateizugriffe zu vermeiden.
 * - Gibt einen spezifischen Konfigurationswert oder die gesamte Konfiguration zurÃ¼ck.
 *
 * @param string $modul Der Modulname, dessen Konfiguration geladen werden soll. Standard ist 'dbx'.
 * @param string $key (Optional) Ein spezifischer SchlÃ¼ssel, dessen Wert zurÃ¼ckgegeben werden soll.
 * @return mixed Die gesamte Konfiguration als Array, der spezifische Wert fÃ¼r `$key`, oder 'undef', falls der SchlÃ¼ssel nicht existiert.
 */
/**
 * Speichert die Konfiguration eines Moduls in einer Datei (verschlÃ¼sselt oder unverschlÃ¼sselt) 
 * und aktualisiert den Session-Cache.
 *
 * Diese Funktion:
 * - Akzeptiert ein Modul und die zu speichernde Konfiguration als Array.
 * - Aktualisiert die Konfiguration im Session-Cache fÃ¼r sofortige VerfÃ¼gbarkeit.
 * - Speichert die Konfiguration in einer Datei, verschlÃ¼sselt, falls aktiviert.
 * - Erstellt das Zielverzeichnis, falls es nicht existiert.
 *
 * @param string $modul Der Modulname, dessen Konfiguration gespeichert werden soll.
 * @param array $config Die Konfigurationsdaten als assoziatives Array.
 * @return int Gibt 0 zurÃ¼ck, falls ein Fehler aufgetreten ist, oder die Anzahl der geschriebenen Bytes.
 */
/**
 * Konvertiert eine Zeichenkette in eine angegebene Zeichenkodierung.
 *
 * Diese Funktion:
 * - Wandelt die Eingabezeichenkette in eine neue Kodierung um, wenn die Zielkodierung (`$charset`)
 *   von der Quellkodierung (`$incharset`) abweicht.
 * - FÃ¼hrt eine Erkennung der aktuellen Kodierung durch, falls `$incharset` als UTF-8 definiert ist.
 * - Ersetzt explizit deutsche Umlaute (Ã¤, Ã¶, Ã¼, ÃŸ, Ã„, Ã–, Ãœ) durch ihre entsprechenden Zeichencodes,
 *   falls notwendig.
 *
 * @param string $in Die zu konvertierende Zeichenkette.
 * @param string $charset Die Zielzeichenkodierung (z. B. 'ISO-8859-1').
 * @param string $incharset Die Eingabezeichenkodierung (Standard: 'UTF-8').
 * @return string Die konvertierte Zeichenkette.
 */
function dbx_convert_charset(string $in, string $charset, string $incharset = 'UTF-8'): string {
   // Nur konvertieren, wenn Zielkodierung von Eingabekodierung abweicht
   if ($charset !== $incharset) {
       // Spezielle Behandlung fÃ¼r deutsche Umlaute und scharfes S
       $umlaute = [
           'Ã¤' => chr(228), 'Ã¶' => chr(246), 'Ã¼' => chr(252), 'ÃŸ' => chr(223),
           'Ã„' => chr(196), 'Ã–' => chr(214), 'Ãœ' => chr(220)
       ];
       $in = str_replace(array_keys($umlaute), array_values($umlaute), $in);

       // Kodierung mit automatischer Erkennung der Eingabekodierung konvertieren
       $in = mb_convert_encoding(
           $in,
           $charset,
           mb_detect_encoding($in, "UTF-8, $charset, ISO-8859-1, ISO-8859-15", true)
       );
   }

   return $in;
}


/**
 * Erzeugt einen Modul-String im spezifischen Format mit den angegebenen Parametern.
 *
 * Diese Funktion erstellt einen String, der ein Modul mit seinen zugehÃ¶rigen Parametern
 * (Aktion und Arbeit) darstellt. Der resultierende String kann fÃ¼r Konfigurations-,
 * Protokollierungs- oder andere Zwecke verwendet werden.
 *
 * @param string $modul Der Name des Moduls.
 * @param string $action Die auszufÃ¼hrende Aktion im Modul.
 * @param string $work (Optional) ZusÃ¤tzliche Arbeitsinformationen.
 * @return string Der generierte Modul-String im Format:
 *                `[modul=<Modul>]dbx_run1=<Aktion>&dbx_run2=<Arbeit>[/modul]`
 */
function dbx_add_modul(string $modul, string $action, string $work = ''): string {
   // Grundstruktur mit Modul und Aktion aufbauen
   $content = '[modul=' . $modul . ']dbx_run1=' . $action;

   // Falls 'work' definiert ist, hinzufÃ¼gen
   if (!empty($work)) {
       $content .= '&dbx_run2=' . $work;
   }

   // Abschluss des Modul-Strings
   $content .= '[/modul]';
   return $content;
}



/**
 * Validiert eine Eingabe basierend auf angegebenen Regeln.
 *
 * Diese Funktion Ã¼berprÃ¼ft, ob der Ã¼bergebene Wert (`$danger_value`) den festgelegten
 * Validierungsregeln entspricht. Sie verwendet ein Validator-Objekt, um die Validierung
 * durchzufÃ¼hren, es sei denn, die Regeln sind auf '*' gesetzt, was bedeutet, dass keine
 * Validierung erfolgt.
 *
 * @param mixed $danger_value Der Wert, der validiert werden soll.
 * @param string $rules Die Validierungsregeln. Kann eine spezifische Regel oder '*' sein, um keine Validierung durchzufÃ¼hren.
 * @param string $varname Der Name der Variablen, der nur zu Validierungszwecken verwendet wird. (Standardwert: 'undef')
 * @return bool Gibt `true` zurÃ¼ck, wenn die Validierung erfolgreich ist, andernfalls `false`.
 */
function dbx_validate_var($danger_value, $rules = 'parameter', $varname = 'undef'): bool {
   // Wenn keine Validierung erforderlich ist, einfach true zurÃ¼ckgeben
   if ($rules == '*') return true;
   // Validator-Objekt aus dem Cache holen
   $oValidator = dbx()->get_system_obj('dbxValidator'); // #cache for speed
   // Validierung durchfÃ¼hren
   return $oValidator->validate($danger_value, $rules, $varname);
}





/**
 * Holt und validiert eine POST-Variable.
 *
 * Diese Funktion prÃ¼ft, ob eine POST-Variable vorhanden ist, validiert den Wert
 * anhand der angegebenen Regeln und gibt den validierten Wert zurÃ¼ck. Wenn der Wert
 * nicht vorhanden ist oder die Validierung fehlschlÃ¤gt, wird der Standardwert zurÃ¼ckgegeben.
 *
 * @param string $varname Der Name der POST-Variable, die abgerufen werden soll.
 * @param mixed $default Der Standardwert, der zurÃ¼ckgegeben wird, wenn die POST-Variable nicht gesetzt ist oder die Validierung fehlschlÃ¤gt. (Standard: leerer String)
 * @param string $rules Die Validierungsregeln fÃ¼r die POST-Variable. (Standard: 'parameter')
 * @return mixed Der validierte Wert der POST-Variable oder der Standardwert, wenn die Variable nicht gesetzt oder ungÃ¼ltig ist.
 */
/**
 * Holt und validiert eine GET-Variable.
 *
 * Diese Funktion prÃ¼ft, ob eine GET-Variable vorhanden ist, validiert den Wert
 * anhand der angegebenen Regeln und gibt den validierten Wert zurÃ¼ck. Wenn der Wert
 * nicht vorhanden ist oder die Validierung fehlschlÃ¤gt, wird der Standardwert zurÃ¼ckgegeben.
 *
 * @param string $varname Der Name der GET-Variable, die abgerufen werden soll.
 * @param mixed $default Der Standardwert, der zurÃ¼ckgegeben wird, wenn die GET-Variable nicht gesetzt ist oder die Validierung fehlschlÃ¤gt. (Standard: leerer String)
 * @param string $rules Die Validierungsregeln fÃ¼r die GET-Variable. (Standard: 'parameter')
 * @return mixed Der validierte Wert der GET-Variable oder der Standardwert, wenn die Variable nicht gesetzt oder ungÃ¼ltig ist.
 */
/**
 * Holt und validiert eine POST- oder GET-Variable.
 *
 * Diese Funktion prÃ¼ft, ob eine Variable in den `$_POST`- oder `$_GET`-Daten vorhanden ist,
 * validiert den Wert anhand der angegebenen Regeln und gibt den validierten Wert zurÃ¼ck. 
 * Wenn der Wert nicht vorhanden ist oder die Validierung fehlschlÃ¤gt, wird der Standardwert zurÃ¼ckgegeben.
 *
 * @param string $varname Der Name der POST- oder GET-Variable, die abgerufen werden soll.
 * @param mixed $default Der Standardwert, der zurÃ¼ckgegeben wird, wenn die Variable nicht gesetzt ist oder die Validierung fehlschlÃ¤gt. (Standard: leerer String)
 * @param string $rules Die Validierungsregeln fÃ¼r die Variable. (Standard: 'parameter')
 * @return mixed Der validierte Wert der Variable oder der Standardwert, wenn die Variable nicht gesetzt oder ungÃ¼ltig ist.
 */
/**
 * Holt eine Systemvariable aus der Session, POST oder GET und validiert sie.
 *
 * Diese Funktion prÃ¼ft, ob eine Systemvariable in der Session, POST oder GET vorhanden ist,
 * validiert den Wert anhand der angegebenen Regeln und gibt den validierten Wert zurÃ¼ck. 
 * Wenn die Variable nicht vorhanden oder ungÃ¼ltig ist, wird der Standardwert zurÃ¼ckgegeben.
 *
 * @param string $varname Der Name der Systemvariable, die abgerufen werden soll.
 * @param mixed $default Der Standardwert, der zurÃ¼ckgegeben wird, wenn die Variable nicht gesetzt ist oder die Validierung fehlschlÃ¤gt. (Standard: leerer String)
 * @param string $rules Die Validierungsregeln fÃ¼r die Variable. (Standard: '*' fÃ¼r keine Validierung)
 * @return mixed Der validierte Wert der Systemvariable oder der Standardwert, wenn die Variable nicht gesetzt oder ungÃ¼ltig ist.
 */
/**
 * Holt eine "Remember"-Variable aus der Session und validiert sie.
 *
 * Diese Funktion sucht nach einer Variable in der Session unter der "remember"-Sektion des angegebenen Moduls,
 * validiert den Wert gemÃ¤ÃŸ den angegebenen Regeln und gibt den validierten Wert zurÃ¼ck. 
 * Wenn die Variable nicht vorhanden oder ungÃ¼ltig ist, wird der Standardwert zurÃ¼ckgegeben.
 *
 * @param string $varname Der Name der "Remember"-Variablen, die abgerufen werden soll.
 * @param mixed $default Der Standardwert, der zurÃ¼ckgegeben wird, wenn die Variable nicht gesetzt ist oder ungÃ¼ltig ist. (Standard: leerer String)
 * @param string $modul Das Modul, zu dem die "Remember"-Variable gehÃ¶rt. (Standard: 'modul')
 * @return mixed Der validierte Wert der "Remember"-Variable oder der Standardwert, wenn die Variable nicht gesetzt oder ungÃ¼ltig ist.
 */
/**
 * Setzt eine "Remember"-Variable in der Session und optional im System.
 *
 * Diese Funktion speichert eine Variable in der Session unter der "remember"-Sektion des angegebenen Moduls. 
 * Wenn das Modul `dbx` ist, wird die Variable zusÃ¤tzlich in der System-Session gespeichert.
 *
 * @param string $varname Der Name der "Remember"-Variablen, die gesetzt werden soll.
 * @param mixed $value Der Wert, der fÃ¼r die Variable gespeichert werden soll.
 * @param string $modul Das Modul, zu dem die "Remember"-Variable gehÃ¶rt. (Standard: 'modul')
 */
/**
 * Setzt eine Systemvariable in der Session.
 *
 * Diese Funktion speichert eine Systemvariable in der Session unter `$_SESSION['dbx']['tmp'][0]['dbx']`,
 * sodass der Wert zwischen verschiedenen Anfragen erhalten bleibt.
 *
 * @param string $varname Der Name der Systemvariablen, die gesetzt werden soll.
 * @param mixed $value Der Wert, der fÃ¼r die Systemvariable gespeichert werden soll.
 */
/**
 * Holt eine Modul-spezifische Variable aus verschiedenen Quellen (Session, GET, POST).
 * Wenn der Wert nicht vorhanden oder ungÃ¼ltig ist, wird der Standardwert zurÃ¼ckgegeben.
 *
 * Wichtige DBX-Regeln:
 * - ModulVar aus dem aktuellen Modulkontext hat Vorrang.
 * - Wenn kein ModulVar-Wert existiert, wird zuerst GET und danach POST geprÃ¼ft.
 * - POST gewinnt dadurch vor GET.
 * - Werte wie 0 oder "0" sind gÃ¼ltige Werte und dÃ¼rfen nicht auf den Default zurÃ¼ckfallen.
 * - Der Default gilt nur bei nicht vorhandenem Wert, leerem String oder ungÃ¼ltiger Validierung.
 *
 * @param string $varname Der Name der zu holenden Variable.
 * @param mixed $default Der Standardwert, der zurÃ¼ckgegeben wird, wenn die Variable nicht gefunden wird oder ungÃ¼ltig ist. (Standard: '')
 * @param string $rules Die Validierungsregel fÃ¼r die Variable (Standard: 'alphanum').
 *
 * @return mixed Der Wert der Variable oder der Standardwert, wenn die Variable nicht gefunden oder ungÃ¼ltig ist.
 */
/**
 * Setzt eine Modul-spezifische Variable in der Session.
 *
 * GeschÃ¼tzte ModulVariablen werden nicht Ã¼berschrieben, wenn sie in
 * dbx_protected_modulvars des aktuellen Modulkontexts eingetragen sind.
 *
 * Wichtig:
 * - Die Schutzliste wird direkt aus der ModulVar-Session gelesen.
 * - GET/POST dÃ¼rfen bei der SchutzprÃ¼fung niemals mitreden.
 * - Mit $check_protected = false kann bewusst intern/gezielt Ã¼berschrieben werden.
 *
 * @param string $varname Der Name der zu setzenden Variable.
 * @param mixed  $value Der Wert, der fÃ¼r die Variable gespeichert werden soll.
 * @param bool   $check_protected Ob geschÃ¼tzte ModulVariablen beachtet werden sollen.
 *
 * @return void
 */
// Session

//  Session - - - - - - - - - - - - - - - - - - - - - - - - -

/**
 * Liefert einen float-basierten Zufalls-Seed aus microtime().
 *
 * @return float Seed-Wert.
 */
function dbx_make_seed(){
    list($usec, $sec) = explode(' ', microtime());
    return (float) $sec + ((float) $usec * 100000);
}

/**
 * Generiert ein zufÃ¤lliges Passwort mit einer MindestlÃ¤nge und optionalen Sonderzeichen.
 *
 * Das Passwort enthÃ¤lt Buchstaben (Klein- und GroÃŸbuchstaben), Zahlen und optionale Sonderzeichen.
 * Etwa 20 % der Zeichen werden zufÃ¤llig in GroÃŸbuchstaben umgewandelt.
 *
 * @param int    $minlength MindestlÃ¤nge des generierten Passworts.
 * @param string $special  Zeichen, die zusÃ¤tzlich zu Buchstaben und Zahlen erlaubt sind (Standard: "-_!").
 * @return string          Das generierte Passwort.
 *
 * @example
 * echo dbx()->new_password(12, '@#$%'); // Gibt ein zufÃ¤lliges Passwort mit mindestens 12 Zeichen aus.
 */
function dbx_load_cookie($cookie) {
   $data=array();
   if (isset($_COOKIE[$cookie])) $data = json_decode($_COOKIE[$cookie], true);
   $_SESSION['dbx']['cookie'][$cookie]=$data;
}


function dbx_save_cookie($cookie,$hh=12) {
   $data=$_SESSION['dbx']['cookie'][$cookie];
   setcookie($cookie, json_encode($data), time()+3600*$hh,'/');
}

function dbx_delete_cookie($cookie) {
  setcookie($cookie, '', time() - 3600, '/');
}


function dbx_get_cookie_val($cookie,$key,$default='') {
  $val=$default;
  if (isset($_SESSION['dbx']['cookie'][$cookie][$key])) {
     $val=$_SESSION['dbx']['cookie'][$cookie][$key];
  }
  return $val;
}


// Format Value
/**
 * Validates and formats a date string based on the input and output format.
 * 
 * @param string $date The date string to validate and format.
 * @param string $io   The desired output format ('web' for DD.MM.YYYY, 'php' for YYYY-MM-DD).
 * @param string $default The default value to return if the date is invalid.
 * 
 * @return string The formatted date or the default value if validation fails.
 */
function dbx_get_Date($date, $io, $default = ''): string {
   //dbx_debug("dbx_get_Date($date) io=($io) defalut=($default)"); 
   if (!$date || $date===null) $date=''; 
   $date = trim($date);

   // Ensure the date only contains valid characters.
   if (!preg_match('#^[0-9./-]+$#', $date)) {
       dbx()->set_system_var('dbx_validate_error', 1);
       return $default;
   }

   // Check date length.
   if (strlen($date) !== 10) {
       dbx()->set_system_var('dbx_validate_error', 1);
       return $default;
   }

   // Determine delimiter and split the date.
   $delimiter = '';
   if (strpos($date, '-') !== false) {
       $delimiter = '-';
   } elseif (strpos($date, '.') !== false) {
       $delimiter = '.';
   } elseif (strpos($date, '/') !== false) {
       $delimiter = '/';
   }

   if (!$delimiter) {
       dbx()->set_system_var('dbx_validate_error', 1);
       return $default;
   }

   $parts = explode($delimiter, $date);

   // Ensure valid parts based on delimiter.
   if (count($parts) !== 3) {
       dbx()->set_system_var('dbx_validate_error', 1);
       return $default;
   }

   [$first, $second, $third] = $parts;

   // Determine the format (DD.MM.YYYY or YYYY-MM-DD).
   if ($delimiter === '-') {
       [$year, $month, $day] = [$first, $second, $third];
   } else {
       [$day, $month, $year] = [$first, $second, $third];
   }

   // Validate the extracted date.
   if (!checkdate((int)$month, (int)$day, (int)$year)) {
       dbx()->set_system_var('dbx_validate_error', 1);
       return $default;
   }

   // Format date based on the desired output.
   if ($io === 'web') {
       return sprintf('%02d.%02d.%04d', $day, $month, $year);
   } elseif ($io === 'php') {
       return sprintf('%04d-%02d-%02d', $year, $month, $day);
   }

   // Default return in case of unsupported $io value.
   dbx()->set_system_var('dbx_validate_error', 1);
   return $default;
}


function dbx_get_webDate($date,$default='') {
 $date=dbx_get_Date($date,'web');
 if (!$date) $date=$default;
 return $date;
}

function dbx_get_phpDate($date,$default='') {
 $date=dbx_get_Date($date,'php');
 if (!$date) $date=$default;
 return $date;
}



function dbx_get_webDateTime($date_time,$default='') {
 $date=substr($date_time, 0, 10);
 $time=substr($date_time,11,  8);
 $date=dbx_get_Date($date,'web');
 return $date.' '.$time;
}


// Secure

function dbx_is_Login() {
   return dbx()->user('id');
}

/**
 * Setzt einen Wert oder den gesamten aktuellen Benutzerkontext.
 *
 * @param string $key Feldname oder `*` fuer den vollstaendigen Kontext.
 * @param mixed $value Zu speichernder Feldwert oder Benutzer-Array.
 * @return void
 */
function dbx_set_CurrentUser($key,$value) {
   if ($key != '*') {
      $_SESSION['dbx']['current_user'][$key]=$value;
   } else {
     $_SESSION['dbx']['current_user']=$value;
   }
}
function dbx_is_decimal($value) {
   if (trim($value)=='') return FALSE;
   $lang   = strlen($value);
   $okcahr = '-0123456789.,';
   for ($i = 0; $i < $lang; $i++) {
     $char = $value[$i];
     $ok = strrpos($okcahr, $char);
     if ($ok === FALSE) return FALSE;
   }
   return TRUE;
}




// Time


function dbx_get_Today($days=0) {
  $today = getdate();
  $date=$today['year'].'-'.$today['mon'].'-'.$today['mday'];
  //return $date;

  $date_t = strtotime($date.' UTC');
  return gmdate('Y-m-d',$date_t + ($days*86400));


}

function dbx_get_Microtime() {
  list($usec, $sec) = explode(' ',microtime());
  return ((float)$usec + (float)$sec);
}

// File Upload

function dbx_upload() {
   $oUpload=dbx()->get_system_obj('dbxUpload');

}


// Mail






function dbx_html2txt($txt) {
   $txt = dbx_html2src($txt);
   $txt = str_replace('<br/>',"\n", $txt );
   $txt = str_replace('<br>' ,"\n", $txt );
   return $txt;
}

function dbx_txt2html($txt) {
   $txt = str_replace("\n",'<br/>',$txt);
   return $txt;
}

function dbx_html2src($html_in) {
  $html_in=stripslashes($html_in);
  $html_in = str_replace ('&nbsp;' , ' ', $html_in);
  $html_in = str_replace ('&amp;'  , '&', $html_in);
  $html_in = str_replace ('&quot;' , '"', $html_in);
  $html_in = str_replace ('&#039;' , "'", $html_in);
  $html_in = str_replace ('&lt;'   , '<', $html_in);
  $html_in = str_replace ('&gt;'   , '>', $html_in);
  $html_in = str_replace ('%7B'    , '{', $html_in);
  $html_in = str_replace ('%7D'    , '}', $html_in);

  $html_in = str_replace ('&uuml;', 'Ã¼', $html_in);
  $html_in = str_replace ('&ouml;', 'Ã¶', $html_in);
  $html_in = str_replace ('&auml;', 'Ã¤', $html_in);
  $html_in = str_replace ('&Uuml;', 'Ãœ', $html_in);
  $html_in = str_replace ('&Ouml;', 'Ã–', $html_in);
  $html_in = str_replace ('&Auml;', 'Ã„', $html_in);

  return $html_in;
}



// dbx Util


function dbx_interpreter($content) {
   $int=dbx()->get_system_obj('dbxInterpreter');
   $content=$int->run($content);
   return $content;
}



// crypt / decrypt

// use \phpseclib3\Crypt\AES; // wird am anfang gemacht.

/**
 * Decrypts content using AES-128-CBC encryption.
 *
 * @param string $content The content to decrypt.
 * @param string $xkey    Optional. The encryption key. Defaults to a predefined key.
 * @param string $master  Optional. The master key. Defaults to the value from dbx()->get_config('dbx', 'crypt').
 *
 * @return string|false The decrypted content or false on failure.
 */
function dbx_decrypt($content, $xkey = '', $master = '') {
    try {
        if (!$master) {
            $master = dbx()->get_config('dbx', 'crypt');
            if (!$master) {
                throw new Exception("Master key is not set.");
            }
        }

        if (!$xkey) {
            $xkey = 'jkgj89bz7b789345%$&8t5';
        }

        $crypt_key = md5($xkey . $master);
        $key = substr($crypt_key, 0, 16);
        $iv = substr($crypt_key, -16);

        //dbx_debug("decrypt ($xkey) ($master) Key=($key) IV=($iv)");

        $aes = new AES('cbc');
        $aes->setKey($key);
        $aes->setIV($iv);

        $decrypt_content = $aes->decrypt($content);

        return $decrypt_content;
    } catch (Exception $e) {
        dbx()->debug("Decryption error: " . $e->getMessage());
        return false;
    }
}

/**
 * Encrypts content using AES-128-CBC encryption.
 *
 * @param string $content The content to encrypt.
 * @param string $xkey    Optional. The encryption key. Defaults to a predefined key.
 * @param string $master  Optional. The master key. Defaults to the value from dbx()->get_config('dbx', 'crypt').
 *
 * @return string|false The encrypted content or false on failure.
 */
function dbx_crypt($content, $xkey = '', $master = '') {
    try {

        if (!$master) {
            $master = dbx()->get_config('dbx', 'crypt');
            if (!$master) {
                throw new Exception("Master key is not set.");
            }
        }

        if (!$xkey) {
            $xkey = 'jkgj89bz7b789345%$&8t5';
        }

        $crypt_key = md5($xkey . $master);
        $key = substr($crypt_key, 0, 16);
        $iv = substr($crypt_key, -16);

        //dbx_debug("crypt ($xkey) ($master) Key=($key) IV=($iv)");

        $aes = new AES('cbc');
        $aes->setKey($key);
        $aes->setIV($iv);

        $crypt_content = $aes->encrypt($content);

        return $crypt_content;
    } catch (Exception $e) {
        dbx()->debug("Encryption error: " . $e->getMessage());
        return false;
    }
}
