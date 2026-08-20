<?php

require_once __DIR__ . '/dbxApiRequestState.trait.php';
require_once __DIR__ . '/dbxApiConfig.trait.php';
require_once __DIR__ . '/dbxApiAssets.trait.php';
require_once __DIR__ . '/dbxApiSecurity.trait.php';
require_once __DIR__ . '/dbxApiLanguage.trait.php';

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

Global $_dbx_cache;

/**
 * Zentrale Laufzeit-API von dbXapp.
 *
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

   use dbxApiRequestStateTrait;
   use dbxApiConfigTrait;
   use dbxApiAssetsTrait;
   use dbxApiSecurityTrait;
   use dbxApiLanguageTrait;

   /** Zustandsbehaftete Builder werden niemals im Service-Cache geteilt. */
   private const FACTORY_SYSTEM_CLASSES = array(
      'dbxForm',
      'dbxReport',
      'dbxView',
      'dbxProcess',
   );

   /** Interne Kernel-Services besitzen bewusst keine myX-Unterklassen. */
   private const INTERNAL_SYSTEM_CLASSES = array(
      'dbxAssetRegistry',
      'dbxConfigStore',
      'dbxPresentation',
      'dbxRequestPipeline',
      'dbxSearchDefaults',
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
    * Der Owner ist das Modul-/Include-Objekt, in dessen fachlichem Kontext
    * gerade Code laeuft (siehe push_owner()/run_owner()). dbxForm/dbxReport/
    * Templates nutzen dies, um Callback-Methoden (z. B. FD-Feld-Callbacks
    * oder Template-Platzhalter-Handler) auf dem richtigen Objekt statt auf
    * einer neuen Instanz aufzurufen.
    *
    * Beispiel:
    * ```php
    * $owner = dbx()->get_current_owner();
    * if (is_object($owner) && method_exists($owner, 'my_callback')) {
    *     return $owner->my_callback($value);
    * }
    * ```
    *
    * @return object|null Aktuelles Owner-Objekt oder null ausserhalb eines Owner-Kontexts.
    */
   public function get_current_owner() {
      if (!$this->owner_stack) {
         return null;
      }

      return $this->owner_stack[count($this->owner_stack) - 1];
   }

   /**
    * Führt eine Methode in einem Owner-Kontext aus.
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
      global $_dbx_cache;

      if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $class)) {
         throw new Exception("Invalid system class name '$class'.");
      }

      $factory = in_array($class, self::FACTORY_SYSTEM_CLASSES, true);
      if (!$factory && isset($_dbx_cache[$class])) {
         return !$use ? $_dbx_cache[$class] : null;
      }

      $base_class = '\\' . $class;
      $base_file  = $this->os_path($this->get_base_dir() . "dbx/include/" . $class . ".class.php");

      if (file_exists($base_file)) {
         require_once $base_file;
      } else {
         $base_class = "\\dbxUndefClass";
      }

      // Der Nur-Laden-Modus stellt ausschliesslich die Basisklasse fuer
      // Vererbung und Typauflösung bereit. Overrides werden erst beim
      // Erzeugen eines Systemobjekts ausgewertet.
      if ($use) {
         return null;
      }

      if (in_array($class, self::INTERNAL_SYSTEM_CLASSES, true)) {
         $object = new $base_class();
         $_dbx_cache[$class] = $object;
         return $object;
      }

      $my_class = $this->get_system_class_override_name($class);
      $my_file  = $this->ensure_system_class_override($class, $my_class, $base_class);

      if ($my_file && file_exists($my_file)) {
         $this->register_editor_file('sysclass', $my_file);
         require_once $my_file;
      }

      $my_class_full = '\\' . $my_class;
      $create_class = class_exists($my_class_full, false) ? $my_class_full : $base_class;

      if (class_exists($create_class)) {
         $object = new $create_class();
         if ($factory) {
            return $object;
         }
         $_dbx_cache[$class] = $object;
         return $object;
      }

      throw new Exception("Klasse '$create_class' konnte nicht geladen werden.");
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
   
      // Prüfen, ob die Klasse existiert, und Instanz erstellen
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
   
      // Systemvariable für das aktuell geladene Include setzen
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
   
      // Objekt erstellen und zurückgeben, falls $use nicht gesetzt ist
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
   
      // Keine Rückgabe, wenn $use gesetzt ist
      return null;
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
   public function get_base_dir(int $cut_data = 0): string {
      if (function_exists('dbx_get_base_dir')) {
         return (string) dbx_get_base_dir($cut_data);
      }

      $path = str_replace('\\', '/', dirname(__DIR__, 2)) . '/';
      if ($cut_data) {
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
      $version_file = str_replace('/', DIRECTORY_SEPARATOR, $this->get_base_dir() . 'VERSION');
      $version = is_file($version_file) && is_readable($version_file)
         ? trim((string) file_get_contents($version_file))
         : '';

      if (preg_match('/^\d+\.\d+\.\d+(?:-dev)?$/', $version) !== 1) {
         $version = '';
      }

      $this->installed_version = $version;
      return $this->installed_version;
   }

   /** Prüft, ob ein Dateipfad absolut ist. */
   private function path_is_absolute(string $path): bool {
      $path = str_replace('\\', '/', trim($path));
      if ($path === '') return false;
      if ($path[0] === '/') return true;
      return (bool)preg_match('#^[A-Za-z]:/#', $path);
   }



   /**
    * Beendet den Request mit JSON-Ausgabe.
    *
    * @param array $data Antwortdaten.
    * @param bool $withRuntime Optional Laufzeit-Metadaten und Header mitsenden.
    * @return void
    */
   public function json_response(array $data, bool $with_runtime = false): void {
      if ($with_runtime) {
         $runtime_service = $this->get_system_obj('dbxRuntime');
         $runtime = number_format($runtime_service->current_php_runtime(), 3, '.', '');
         $data['dbx_php_runtime'] = $runtime;
         $data['_dbx_runtime'] = array('php' => $runtime);
         $runtime_service->send_headers();
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
         ? \dbx\dbxContent\dbxContentLng::permalink_mode()
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

      $transaction_started = false;

      try {
         $db = $this->get_system_obj('dbxDB');
         if (!is_object($db)) {
            return 0;
         }

         if ((int)$db->begin('dbxMissing') !== 1) {
            throw new \RuntimeException('dbxMissing-Transaktion konnte nicht gestartet werden.');
         }
         $transaction_started = true;

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
            $transaction_started = false;
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
         $transaction_started = false;

         return $id;
      } catch (\Throwable $e) {
         if ($transaction_started && isset($db) && is_object($db)) {
            $db->rollback('dbxMissing');
         }
         $this->get_system_obj('dbxRuntime')->write_php_error_log(get_class($e), $e->getMessage(), $e->getFile(), $e->getLine());
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

         $sys_msg_level = strtolower(trim((string) $this->get_cfg('dbx', 'sys_msg_level', 'all')));
         if ($sys_msg_level === '') {
            $sys_msg_level = 'all';
         }

         $min_level = 0;
         if ($sys_msg_level === 'error') {
            $min_level = 40;
         } elseif ($sys_msg_level === 'warning' || $sys_msg_level === 'warn') {
            $min_level = 20;
         }

         if ($level < $min_level) {
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

         $o_db = $this->get_system_obj('dbxDB');
         $prev_report = (int)$o_db->_report_error;
         $o_db->_report_error = 0;
         $ok  = $o_db->insert('dbxSysMsg', $record, 0, 1, 0, 0);
         $o_db->_report_error = $prev_report;
         $insert_id = $ok ? $o_db->get_insert_id() : 0;

         dbx()->debug("##SYS-MSG### ok=($ok) id=($insert_id) Level=($level) Status=($status) Modul=($modul) Action=($action) Work=($work) About=($about) RID=($rid) Why=($why) What=($what_text)");

         return (int)$insert_id;
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
      // Aktuellen Zählerwert aus der "Remember"-Datenstruktur abrufen
      $i = $this->get_remember_var('dbx_next_i', 0, 'dbx');

      // Zähler um den angegebenen Wert erhöhen
      $i += $add;

      // Aktualisierten Zählerwert in die "Remember"-Datenstruktur speichern
      $this->set_remember_var('dbx_next_i', $i, 'dbx');

      // Neuen Zählerwert zurückgeben
      return $i;
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
         $debug_activ = $this->get_file_dir() . "dbxDebugActiv.txt";
         $debug_activ = $this->os_path($debug_activ);
         if (file_exists($debug_activ)) {
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
   private function get_system_class_override_name(string $class): string {
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
   private function ensure_system_class_override(string $class, string $my_class, string $base_class): string {
      if ($base_class === "\\dbxUndefClass" || !class_exists($base_class, false)) {
         return '';
      }

      $module_dir = $this->os_path($this->get_base_dir() . 'dbx/modules/myX/');
      if (!is_file($this->os_path($module_dir . 'cfg/config.php'))) {
         return '';
      }

      $sys_dir    = $this->os_path($module_dir . 'sysclass/');
      $file      = $this->os_path($sys_dir . $my_class . '.class.php');

      if (!is_dir($sys_dir)) {
         mkdir($sys_dir, 0777, true);
      }

      $cfg_dir = $this->os_path($module_dir . 'cfg/');
      if (!is_dir($cfg_dir)) {
         mkdir($cfg_dir, 0777, true);
      }

      $cfg_file = $this->os_path($cfg_dir . 'config.php');
      if (!file_exists($cfg_file)) {
         $this->set_cfg('myX', array(
            'version' => '1',
            'activ' => '1',
            'groups' => '*',
         ));
      }

      $module_file = $this->os_path($module_dir . 'myX.class.php');
      if (!file_exists($module_file)) {
         file_put_contents($module_file, "<?php\nnamespace dbx\\myX;\n\nclass myX {\n   public function run() {\n      return 'myX system module';\n   }\n}\n");
      }

      if (!file_exists($file)) {
         $content  = "<?php\n";
         $content .= "/**\n";
         $content .= " * Auto-generated DBX system class override.\n";
         $content .= " * Requested class: $class\n";
         $content .= " * Override class: $my_class\n";
         $content .= " */\n";
         $content .= "class $my_class extends $class {\n";
         $content .= "}\n";
         file_put_contents($file, $content);
      }

      return $file;
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

      $o_validator = $this->get_system_obj('dbxValidator'); // #cache for speed
      return $o_validator->validate($value, $rules, $name);
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
    * Prüft, ob ein dbxDB-Rueckgabewert (insert()/update()/delete()/count() ...)
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
    * Prüft, ob ein dbxDB-Rueckgabewert (insert()/update()/delete()/count() ...)
    * einen Datenbankfehler bedeutet (z. B. Verbindungsfehler oder gescheitertes SQL).
    *
    * Ergänzt is_access_denied(): zusammen decken beide Methoden die
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

