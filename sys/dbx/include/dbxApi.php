<?php

include_once dbx_os_path_file(dbx_get_base_dir().'dbx/add_ons/sftp/vendor/autoload.php');
use phpseclib3\Crypt\AES;

Global $_dbxCache;

function dbx_add_admin_msg($status='',$about='',$rid='',$why='',$what='') {
    $now    = 
    $modul  = dbx_get_ModulVar('dbx_modul');
    $action = dbx_get_ModulVar('dbx_action');
    $work   = dbx_get_ModulVar('dbx_work');
    $message="$about why=($why) what=($what) rid=($rid)";
    if (!strpos($message,'dbx_adminmsg')) { // no error trace for dbx_adminmsg. Endless loop
      $oDB=dbx_get_sys_object('dbxDB');
      $record['xuser']   = dbx_get_CurrentUser('id');
      $record['status']  = $status;
      $record['message'] = $message;
      $ok=$oDB->insert('dbx_adminmsg',$record,0,1,0,1); // validate only fields 
    }
    dbx_debug("##ADMIN-MSG### Modul=($modul) Action=($action) Work=($work) Status=($status) About=($about) RID=($rid) Why=($why) What=($what)");
}



function dbx_get_self_url() {
   return dbx_get_SysVar('dbx_self_url','','*');
}

function dbx_get_base_url() {
   return dbx_get_SysVar('dbx_base_url','','*');  
}


function dbx_run_time($section,$info='') {
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
   } else {
      //dbx_debug("#TIMER ADD SET");
      
      $dbx_run_timer[$section]['end_time']  = $time;
      $dbx_run_timer[$section]['end_memory']= $memory;
      $dbx_run_timer[$section]['time']      = ($dbx_run_timer[$section]['end_time']   - $dbx_run_timer[$section]['start_time']);
      $dbx_run_timer[$section]['memory']    = ($dbx_run_timer[$section]['end_memory'] - $dbx_run_timer[$section]['start_memory']);
   } 
   //dbx_debug("#TIMER ($section)",$dbx_run_timer);
 
}

function dbx_debug_run_timer($max=0) {
   Global $dbx_run_timer;
   if (isset($dbx_run_timer['system']['time'])) {
      $time=$dbx_run_timer['system']['time'];
      if ($time > $max) { 
         dbx_debug("#RUN-TIMER",$dbx_run_timer);
      } else {
         dbx_debug("dbx System run time=($time)");
      }  
   }
}



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




function dbx_sendMail($from,$fromname,$to,$subject,$text,$type='html',$attach='',$archiv=0) {

    return 1; //#todo send mails

    $ok=0; $pos=0; $to2='';



    $base_url=dbx_get_base_url();
    $pos=strpos($base_url, "localhost"); // aktuell kein senden von localhost
    if (!$pos && $to) {

      $subject1=mb_convert_encoding($subject,             'UTF-8', mb_list_encodings());
      $subject2=mb_convert_encoding($subject." to=($to)", 'UTF-8', mb_list_encodings());


      $oMailsys =dbx_get_sys_object('dbxMail');
      $oMailsys->init();

      if ($type=="html") $text=dbx_txt2html($text);
      if ($type!="html") $text=dbx_html2txt($text);

     	$oMailsys->set_header("From", $from);
     	if ($type == "html") $oMailsys->bodyhtml($text);
     	if ($type != "html") {
         $text=mb_convert_encoding($text, 'UTF-8', mb_list_encodings());
        
         $oMailsys->bodytext($text);
      }
     	$oMailsys->subject = $subject1;

      if ($attach) $oMailsys->attachfile($attach);
     	$ok=$oMailsys->send($to,$subject1); // fix #alb#

      if ($to2) {
        $ok=$oMailsys->send($to2,$subject2); // it is a copy for debug
      }


      //dbx_debug("#API #MAIL dbx_sendMail  ($from ) -> ($to) Sub=($subject) Pos=($pos) ok=($ok)");
    }
    if ($archiv) {
      // eMail archiv # ToDo
    }


    return $ok;
}


function dbx_strpos($string,$find) {
   return strpos('~'.$string,$find);
}

function dbx_html($html) {
   return htmlentities($html, ENT_QUOTES);
}

function dbx_timestamp($add_sec=0) {
    list($usec, $sec) = explode(" ",microtime());
    $time= ((float)  $usec + (float)$sec);
    $time=  (float) ($time + ($add_sec));
    return $time;
}

function dbx_time_diff($starttime=0,$endtime=0) {
  if (!$starttime) $starttime=dbx_timestamp();
  if (!$endtime)   $endtime  =dbx_timestamp();
  return ($endtime-$starttime);
}



function dbx_is_modul($modul) {
    $retval=false;
    if ($modul) {
      $modul_class_file=dbx_get_base_dir()."dbx/modules/$modul/".$modul.".class.php";
      if (file_exists($modul_class_file)) $retval=true;
    }
    return $retval;
}

function dbx_is_design($design,$page='default') {
   $admin=dbx_check_access('admin');
   $firstchar=substr($design,0,1);
   if (!$admin && $firstchar == '_') return false;
   if (!$admin && $firstchar == '-') return false;
 
   $design_tpl=dbx_get_base_dir()."dbx/design/$design/htm/$page.htm";
   if (file_exists($design_tpl)) return true;
   if ($page != 'default') {
      $design_tpl=dbx_get_base_dir()."dbx/design/$design/htm/default.htm";
      if (file_exists($design_tpl)) return true;      
   }
   
   return false;
}

function dbx_is_page($page,$design,$lng='') {
    $retval=false;
    $page_tpl=dbx_get_base_dir()."dbx/tpl/htm/$page.htm";
    if (file_exists($page_tpl)) $retval=true;
    return $retval;
}




function dbx_redirect($redirect,$retval=0,$timer=0) {
   $oTPL    =dbx_get_sys_object('dbxTPL');
   $oSession=dbx_get_sys_object('dbxSession');
   $base    =dbx_get_base_url();
   $ajax    =dbx_get_sysVar('dbx_ajax',0,'int');
   $sess    =dbx_get_Remember('dbx_redir_after_login','dbx','*');

   $oSession->clean_session();
   $oSession->save_session();

   if (!strpos($redirect, '://')) {
      $redir=$base.$redirect;
   } else {
      $redir=$redirect;
   }

   //dbx_debug("#dbx_redirect Session=($sess) Call=($redirect)=($redir) Ajax=($ajax) base_url=($base) retval=($retval)");
   if ($timer==0) $script="<script>window.location.href = '$redir';</script>";
   if ($timer!=0) {
      $js="window.location.href = '$redir';";
      $a='setTimeout(function() { ';
      $e=" }, $timer);";
      $script="<script>$a $js $e</script>";
   }
   
   
   if ($retval) return $script;

   if (!$ajax) {

     $data['script']=$script;
     $data['redir'] =$redir;

     $page = $oTPL->get_tpl('dbx','redirect',$data);

     if (!headers_sent()) {
        header ("location: $redir");
     } else {
        echo $page;
     }
   } else {
      echo $script;
   }
   exit;
}



   


function dbx_use_sys_class($class)  {
   $modul_id=0;
   $dbx_class_file=dbx_get_base_dir()."dbx/include/".$class.".class.php";
   $dbx_class_file=dbx_os_path_file($dbx_class_file);


   if (file_exists($dbx_class_file)) {
     require_once $dbx_class_file;
   }
}




/**
 * Lädt eine Klasse aus dem Cache oder erstellt eine neue Instanz der Klasse.
 *
 * Die Funktion versucht, ein Objekt der angegebenen Klasse zu laden:
 * 1. Wenn das Objekt bereits im Cache vorhanden ist, wird es zurückgegeben.
 * 2. Andernfalls wird die entsprechende Klassen-Datei eingebunden und ein neues Objekt erstellt.
 * 
 * @param string $class Der Name der Klasse, die geladen werden soll.
 * @param string $use (Optional) Kann verwendet werden, um andere Logiken basierend auf der Klasse zu implementieren. 
 *                    Standardmäßig leer.
 * @return object|null Gibt das Klassen-Objekt zurück oder `null`, falls ein Fehler auftritt oder `$use` gesetzt ist.
 * @throws Exception Wenn die Klasse nicht geladen werden kann und keine Fallback-Klasse definiert ist.
 */
function dbx_get_sys_object(string $class, string $use = ''): ?object {
   global $_dbxCache;

   // Vollqualifizierten Klassennamen erzeugen
   $namespace_class = '\\' . $class;

   // Überprüfen, ob die Klasse bereits im Cache existiert
   if (isset($_dbxCache[$class])) {
       // Rückgabe des Objekts, falls $use nicht gesetzt ist
       return !$use ? $_dbxCache[$class] : null;
   }

   // Pfad zur Klassen-Datei berechnen
   $dbx_class_file = dbx_os_path_file(dbx_get_base_dir() . "dbx/include/" . $class . ".class.php");

   // Klassen-Datei einbinden, wenn vorhanden
   if (file_exists($dbx_class_file)) {
       require_once $dbx_class_file;
   } else {
       // Fallback-Klasse verwenden, falls die Datei nicht existiert
       $namespace_class = "\\dbxUndefClass";
   }

   // Neues Objekt erstellen
   if (class_exists($namespace_class)) {
       $newObject = new $namespace_class();
       $_dbxCache[$class] = $newObject;

       // Rückgabe des neuen Objekts, falls $use nicht gesetzt ist
       return !$use ? $newObject : null;
   }

   // Fehler werfen, falls die Klasse nicht existiert
   throw new Exception("Klasse '$namespace_class' konnte nicht geladen werden.");
}





/**
 * Lädt ein Modul-Objekt basierend auf dem Modulnamen und aktualisiert zugehörige System- und Modulvariablen.
 *
 * Diese Funktion:
 * 1. Erzeugt den vollqualifizierten Klassennamen des Moduls.
 * 2. Aktualisiert System- und Modulvariablen wie `dbx_activ_modul_id`, `dbx_activ_modul` und Design-Informationen.
 * 3. Lädt die Klassen-Datei des Moduls und erstellt eine Instanz der Klasse.
 * 
 * @param string $class Der Name des Moduls, das geladen werden soll.
 * @return object Gibt eine Instanz des Modul-Objekts zurück.
 * @throws Exception Wenn die Klassen-Datei des Moduls fehlt oder die Klasse nicht geladen werden kann.
 */
function dbx_get_Modul_object(string $class): object {
   // Vollqualifizierten Klassennamen vorbereiten
   $namespace_class = '\dbx\-class-\-class-';
   $namespace_class = str_replace('-class-', $class, $namespace_class);

   // Modul-ID auslesen und inkrementieren
   $modul_id = dbx_get_SysVar('dbx_activ_modul_id', 0, '*');
   $modul_id++;
   dbx_set_SysVar('dbx_activ_modul_id', $modul_id);

   // Modulname in System- und Modulvariablen speichern
   dbx_set_SysVar('dbx_activ_modul', $class);
   dbx_set_ModulVar('dbx_modul_id', $modul_id);
   dbx_set_ModulVar('dbx_modul', $class);

   // Admin-spezifisches Design anwenden, falls Modul ein Admin-Modul ist
   if (stripos($class, 'admin') !== false) {
       $admin_design = dbx_get_cfg('dbx', 'default_design_admin');
       dbx_set_SysVar('dbx_design', $admin_design);
   }

   // Pfad zur Klassen-Datei des Moduls berechnen
   $modul_class_file = dbx_os_path_file(dbx_get_base_dir() . "dbx/modules/$class/$class.class.php");

   // Klassen-Datei einbinden, wenn vorhanden
   if (file_exists($modul_class_file)) {
       require_once $modul_class_file;
   } else {
       // Fallback-Klasse verwenden, falls die Datei fehlt
       $namespace_class = "\\dbxUndefClass";
   }

   // Prüfen, ob die Klasse existiert, und Instanz erstellen
   if (class_exists($namespace_class)) {
       return new $namespace_class();
   }

   // Fehler werfen, falls die Klasse nicht existiert
   throw new Exception("Modul-Klasse '$namespace_class' konnte nicht geladen werden.");
}


/**
 * Lädt eine Klasse aus dem `include`-Ordner eines Moduls und erstellt ein Objekt.
 *
 * Die Funktion sucht nach einer Klassen-Datei im `include`-Ordner des angegebenen Moduls
 * oder des aktuell aktiven Moduls, lädt diese und erstellt eine Instanz der Klasse.
 *
 * @param string $class Der Name der Klasse, die geladen werden soll.
 * @param string $modul (Optional) Der Name des Moduls, zu dem die Klasse gehört. 
 *                      Standardmäßig wird das aktuell aktive Modul verwendet.
 * @param string $use (Optional) Zusätzliche Steuerung, ob ein Objekt zurückgegeben wird. Standard: leer.
 * @return object|null Gibt eine Instanz des Klassen-Objekts zurück oder `null`, wenn `$use` gesetzt ist.
 * @throws Exception Wenn die Klassen-Datei fehlt oder die Klasse nicht geladen werden kann.
 */
function dbx_get_Modul_include_object(string $class, string $modul = '', string $use = ''): ?object {
   // Aktuelles Modul verwenden, wenn keins angegeben wurde
   if (!$modul) {
       $modul = dbx_get_SysVar('dbx_activ_modul', 'dbx', '*');
   }

   // Systemvariable für das aktuell geladene Include setzen
   dbx_set_SysVar('dbx_inc', $class);

   // Vollqualifizierten Klassennamen generieren
   $namespace_class = '\dbx\-modul-\-class-';
   $namespace_class = str_replace('-modul-', $modul, $namespace_class);
   $namespace_class = str_replace('-class-', $class, $namespace_class);

   // Pfad zur Klassen-Datei bestimmen
   $modul_class_file = dbx_os_path_file(dbx_get_base_dir() . "dbx/modules/$modul/include/$class.class.php");

   // Klassen-Datei einbinden, wenn sie existiert
   if (file_exists($modul_class_file)) {
       require_once $modul_class_file;
   } else {
       // Fallback-Klasse verwenden, falls Datei fehlt
       $namespace_class = "\\dbxUndefClass";
   }

   // Objekt erstellen und zurückgeben, falls $use nicht gesetzt ist
   if (!$use) {
       if (class_exists($namespace_class)) {
           return new $namespace_class();
       }

       // Fehler werfen, falls die Klasse nicht existiert
       throw new Exception("Klasse '$namespace_class' konnte nicht geladen werden.");
   }

   // Keine Rückgabe, wenn $use gesetzt ist
   return null;
}




function dbx_modul_translate($content,$modul='',$lng='') {
   if (!$modul) $modul=dbx_get_SysVar('dbx_activ_modul','dbx');
   if (!$lng)     $lng=dbx_get_SysVar('dbx_lng','de');
   $dir_file=dbx_get_base_dir()."dbx/modules/$modul/translate.php";
   $dir_file=dbx_os_path_file($dir_file);
   if (file_exists($dir_file)) {
      include $dir_file;
   }
   return $content;
}

/**
 * Gibt die nächste fortlaufende Zahl aus der "Remember"-Datenstruktur zurück und erhöht sie.
 *
 * Diese Funktion:
 * 1. Liest den aktuellen Wert des Zählers `dbx_next_i` aus der "Remember"-Datenstruktur.
 * 2. Erhöht den Zähler um den optional angegebenen Wert.
 * 3. Speichert den neuen Zählerwert zurück in die "Remember"-Datenstruktur.
 *
 * @param int $add (Optional) Der Wert, um den der Zähler erhöht wird. Standard ist 1.
 * @return int Der neue Zählerwert nach der Erhöhung.
 */
function dbx_get_next_i(int $add = 1): int {
   // Aktuellen Zählerwert aus der "Remember"-Datenstruktur abrufen
   $i = dbx_get_Remember('dbx_next_i', 0, '*', 'dbx');
   
   // Zähler um den angegebenen Wert erhöhen
   $i += $add;

   // Aktualisierten Zählerwert in die "Remember"-Datenstruktur speichern
   dbx_set_Remember('dbx_next_i', $i, 'dbx');

   // Neuen Zählerwert zurückgeben
   return $i;
}



function dbx_replace_first($search_str, $replacement_str, $src_str){
  return (false !== ($pos = strpos($src_str, $search_str))) ? substr_replace($src_str, $replacement_str, $pos, strlen($search_str)) : $src_str;
}


function dbx_url_to_array($data) {
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
 * Konvertiert ein Array in PHP-Code, der es rekonstruiert.
 *
 * Diese Funktion:
 * - Traversiert ein Array rekursiv.
 * - Generiert PHP-Code, um das Array durch Zuweisungen zu rekonstruieren.
 * - Unterstützt verschachtelte Arrays und escapt automatisch Strings.
 *
 * @param array $array Das Array, das konvertiert werden soll.
 * @param string $prefix Der Prefix, der die Basisvariable oder den Startpunkt angibt.
 * @return string Der generierte PHP-Code, der das Array rekonstruiert.
 */
function dbx_convertArrayToPHPCode(array $array, string $prefix): string {
   $code = "";

   foreach ($array as $key => $value) {
       // Generiere den Schlüssel (numerisch oder als String)
       $keyPart = is_numeric($key) ? "[$key]" : "['" . addslashes($key) . "']";

       if (is_array($value)) {
           // Rekursive Verarbeitung für verschachtelte Arrays
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
 * Lädt die Konfigurationsdaten eines Moduls aus einer Datei (verschlüsselt oder unverschlüsselt) und speichert sie im Session-Cache.
 *
 * Diese Funktion:
 * - Liest die Konfigurationsdatei des angegebenen Moduls.
 * - Unterstützt verschlüsselte und unverschlüsselte Konfigurationsdateien.
 * - Lädt die Konfiguration in den Session-Cache, um wiederholte Dateizugriffe zu vermeiden.
 * - Gibt einen spezifischen Konfigurationswert oder die gesamte Konfiguration zurück.
 *
 * @param string $modul Der Modulname, dessen Konfiguration geladen werden soll. Standard ist 'dbx'.
 * @param string $key (Optional) Ein spezifischer Schlüssel, dessen Wert zurückgegeben werden soll.
 * @return mixed Die gesamte Konfiguration als Array, der spezifische Wert für `$key`, oder 'undef', falls der Schlüssel nicht existiert.
 */
function dbx_get_cfg(string $modul = 'dbx', string $key = '') {
   $crypt = 1; // Aktiviert die Entschlüsselung der Datei-Inhalte

   // Prüfen, ob die Konfiguration bereits im Session-Cache vorhanden ist
   if (!isset($_SESSION['dbx']['config'][$modul])) {
       $config = [];
       $content = '';

       // Datei-Pfad basierend auf Modulname bestimmen
       if ($modul == 'dbx') {
           $dir_file = dbx_get_file_dir() . 'sys/cfg/config.cfx';
       } else {
           $dir_file = dbx_get_file_dir() . 'sys/cfg/' . $modul . '.cfx';
       }
       $dir_file = dbx_os_path_file($dir_file);

       // Verschlüsselte oder unverschlüsselte Konfiguration laden
       if (file_exists($dir_file)) {
           $content = file_get_contents($dir_file);
           if ($crypt) {
               $content = dbx_decrypt($content, 'dfht8@#734fst34gf64', 'dfuhvuerhz75&%v!');
           }
       } else { // Fallback: Konfigurationsdatei aus Modulverzeichnis laden
           $dir_file = dbx_get_base_dir() . "dbx/modules/$modul/cfg/config.php";
           if (file_exists($dir_file)) {
               $content = file_get_contents($dir_file);
           }
       }

       // Wenn Inhalt vorhanden ist, diesen ausführen
       if ($content) {
           $clean_code = str_replace(['<?php', '?>'], '', $content);

           // Benutzerdefinierte Fehlerbehandlung für eval()
           set_error_handler(function ($errno, $errstr, $errfile, $errline) {
               throw new Exception("Fehler in eval() Code: $errstr in Zeile $errline");
           });

           try {
               eval($clean_code); // Führt den bereinigten Code aus
           } catch (Exception $e) {
               echo "Fehler: " . $e->getMessage();
           } finally {
               restore_error_handler(); // Fehlerbehandlung zurücksetzen
           }
       }

       // Standardgruppe setzen, falls keine Gruppen definiert sind
       if (!isset($config['groups'])) {
           $config['groups'] = ['admin'];
       } elseif (!is_array($config['groups'])) {
           $config['groups'] = array_filter(explode(',', $config['groups']));
       }

       // Konfiguration im Session-Cache speichern
       $_SESSION['dbx']['config'][$modul] = $config;
   }

   // Konfiguration aus dem Cache laden
   $config = $_SESSION['dbx']['config'][$modul];

   // Spezifischen Schlüssel zurückgeben, falls angegeben
   if ($key) {
       return $config[$key] ?? 'undef';
   }

   // Gesamte Konfiguration zurückgeben
   return $config;
}


/**
 * Speichert die Konfiguration eines Moduls in einer Datei (verschlüsselt oder unverschlüsselt) 
 * und aktualisiert den Session-Cache.
 *
 * Diese Funktion:
 * - Akzeptiert ein Modul und die zu speichernde Konfiguration als Array.
 * - Aktualisiert die Konfiguration im Session-Cache für sofortige Verfügbarkeit.
 * - Speichert die Konfiguration in einer Datei, verschlüsselt, falls aktiviert.
 * - Erstellt das Zielverzeichnis, falls es nicht existiert.
 *
 * @param string $modul Der Modulname, dessen Konfiguration gespeichert werden soll.
 * @param array $config Die Konfigurationsdaten als assoziatives Array.
 * @return int Gibt 0 zurück, falls ein Fehler aufgetreten ist, oder die Anzahl der geschriebenen Bytes.
 */
function dbx_set_cfg(string $modul, array $config): int {
   $crypt = 1; // Aktiviert die Verschlüsselung der Datei-Inhalte
   $content = "<?php \n"; // PHP-Tag hinzufügen, um die Datei als ausführbaren Code zu speichern
   $dir = dbx_get_file_dir() . 'sys/cfg/';

   // Dateiname basierend auf Modulname bestimmen
   $file = ($modul == 'dbx') ? 'config.cfx' : $modul . '.cfx';
   $dir_file = dbx_os_path_file($dir . '/' . $file);
   $dir = dbx_os_path_file($dir);

   // Konfigurationsarray in PHP-Code konvertieren
   if (is_array($config)) {
       $_SESSION['dbx']['config'][$modul] = $config; // Cache aktualisieren
       $content .= dbx_convertArrayToPHPCode($config, '$config'); // Array in PHP-Code umwandeln
   }

   // Optional: Inhalt verschlüsseln
   if ($crypt) {
       $content = dbx_crypt($content, 'dfht8@#734fst34gf64', 'dfuhvuerhz75&%v!');
   }

   // Verzeichnis erstellen, falls es nicht existiert
   if (!is_dir($dir)) {
       mkdir($dir, 0700);
   }

   // Dateiinhalt schreiben und Erfolg prüfen
   $ok = file_put_contents($dir_file, $content);

   return $ok ?: 0; // Gibt 0 zurück, falls `file_put_contents` fehlschlägt
}


/**
 * Konvertiert eine Zeichenkette in eine angegebene Zeichenkodierung.
 *
 * Diese Funktion:
 * - Wandelt die Eingabezeichenkette in eine neue Kodierung um, wenn die Zielkodierung (`$charset`)
 *   von der Quellkodierung (`$incharset`) abweicht.
 * - Führt eine Erkennung der aktuellen Kodierung durch, falls `$incharset` als UTF-8 definiert ist.
 * - Ersetzt explizit deutsche Umlaute (ä, ö, ü, ß, Ä, Ö, Ü) durch ihre entsprechenden Zeichencodes,
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
       // Spezielle Behandlung für deutsche Umlaute und scharfes S
       $umlaute = [
           'ä' => chr(228), 'ö' => chr(246), 'ü' => chr(252), 'ß' => chr(223),
           'Ä' => chr(196), 'Ö' => chr(214), 'Ü' => chr(220)
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
 * Diese Funktion erstellt einen String, der ein Modul mit seinen zugehörigen Parametern
 * (Aktion und Arbeit) darstellt. Der resultierende String kann für Konfigurations-,
 * Protokollierungs- oder andere Zwecke verwendet werden.
 *
 * @param string $modul Der Name des Moduls.
 * @param string $action Die auszuführende Aktion im Modul.
 * @param string $work (Optional) Zusätzliche Arbeitsinformationen.
 * @return string Der generierte Modul-String im Format:
 *                `[modul=<Modul>]dbx_action=<Aktion>&dbx_work=<Arbeit>[/modul]`
 */
function dbx_add_modul(string $modul, string $action, string $work = ''): string {
   // Grundstruktur mit Modul und Aktion aufbauen
   $content = '[modul=' . $modul . ']dbx_action=' . $action;

   // Falls 'work' definiert ist, hinzufügen
   if (!empty($work)) {
       $content .= '&dbx_work=' . $work;
   }

   // Abschluss des Modul-Strings
   $content .= '[/modul]';
   return $content;
}



/**
 * Validiert eine Eingabe basierend auf angegebenen Regeln.
 *
 * Diese Funktion überprüft, ob der übergebene Wert (`$danger_value`) den festgelegten
 * Validierungsregeln entspricht. Sie verwendet ein Validator-Objekt, um die Validierung
 * durchzuführen, es sei denn, die Regeln sind auf '*' gesetzt, was bedeutet, dass keine
 * Validierung erfolgt.
 *
 * @param mixed $danger_value Der Wert, der validiert werden soll.
 * @param string $rules Die Validierungsregeln. Kann eine spezifische Regel oder '*' sein, um keine Validierung durchzuführen.
 * @param string $varname Der Name der Variablen, der nur zu Validierungszwecken verwendet wird. (Standardwert: 'undef')
 * @return bool Gibt `true` zurück, wenn die Validierung erfolgreich ist, andernfalls `false`.
 */
function dbx_validate_var($danger_value, $rules = 'parameter', $varname = 'undef'): bool {
   // Wenn keine Validierung erforderlich ist, einfach true zurückgeben
   if ($rules === '*') return true;
   // Validator-Objekt aus dem Cache holen
   $oValidator = dbx_get_sys_object('dbxValidator'); // #cache for speed
   // Validierung durchführen
   return $oValidator->validate($danger_value, $rules, $varname);
}





/**
 * Holt und validiert eine POST-Variable.
 *
 * Diese Funktion prüft, ob eine POST-Variable vorhanden ist, validiert den Wert
 * anhand der angegebenen Regeln und gibt den validierten Wert zurück. Wenn der Wert
 * nicht vorhanden ist oder die Validierung fehlschlägt, wird der Standardwert zurückgegeben.
 *
 * @param string $varname Der Name der POST-Variable, die abgerufen werden soll.
 * @param mixed $default Der Standardwert, der zurückgegeben wird, wenn die POST-Variable nicht gesetzt ist oder die Validierung fehlschlägt. (Standard: leerer String)
 * @param string $rules Die Validierungsregeln für die POST-Variable. (Standard: 'parameter')
 * @return mixed Der validierte Wert der POST-Variable oder der Standardwert, wenn die Variable nicht gesetzt oder ungültig ist.
 */
function dbx_get_PostVar(string $varname, $default = '', string $rules = 'parameter') {
   // Standardwert initialisieren
   $value = $default;

   // Prüfen, ob die POST-Variable gesetzt ist
   if (isset($_POST[$varname])) {
       $danger_value = $_POST[$varname];

       // Validierung des Werts
       if (dbx_validate_var($danger_value, $rules, $varname)) {
           $value = $danger_value;
       }
   }

   // Rückgabe des validierten Werts oder des Standardwerts
   return $value;
}



/**
 * Holt und validiert eine GET-Variable.
 *
 * Diese Funktion prüft, ob eine GET-Variable vorhanden ist, validiert den Wert
 * anhand der angegebenen Regeln und gibt den validierten Wert zurück. Wenn der Wert
 * nicht vorhanden ist oder die Validierung fehlschlägt, wird der Standardwert zurückgegeben.
 *
 * @param string $varname Der Name der GET-Variable, die abgerufen werden soll.
 * @param mixed $default Der Standardwert, der zurückgegeben wird, wenn die GET-Variable nicht gesetzt ist oder die Validierung fehlschlägt. (Standard: leerer String)
 * @param string $rules Die Validierungsregeln für die GET-Variable. (Standard: 'parameter')
 * @return mixed Der validierte Wert der GET-Variable oder der Standardwert, wenn die Variable nicht gesetzt oder ungültig ist.
 */
function dbx_get_GetVar(string $varname, $default = '', string $rules = 'parameter') {
   // Standardwert initialisieren
   $value = $default;

   // Prüfen, ob die GET-Variable gesetzt ist
   if (isset($_GET[$varname])) {
       $danger_value = $_GET[$varname];

       // Validierung des Werts
       if (dbx_validate_var($danger_value, $rules, $varname)) {
           $value = $danger_value;
       }
   }

   // Rückgabe des validierten Werts oder des Standardwerts
   return $value;
}

/**
 * Holt und validiert eine POST- oder GET-Variable.
 *
 * Diese Funktion prüft, ob eine Variable in den `$_POST`- oder `$_GET`-Daten vorhanden ist,
 * validiert den Wert anhand der angegebenen Regeln und gibt den validierten Wert zurück. 
 * Wenn der Wert nicht vorhanden ist oder die Validierung fehlschlägt, wird der Standardwert zurückgegeben.
 *
 * @param string $varname Der Name der POST- oder GET-Variable, die abgerufen werden soll.
 * @param mixed $default Der Standardwert, der zurückgegeben wird, wenn die Variable nicht gesetzt ist oder die Validierung fehlschlägt. (Standard: leerer String)
 * @param string $rules Die Validierungsregeln für die Variable. (Standard: 'parameter')
 * @return mixed Der validierte Wert der Variable oder der Standardwert, wenn die Variable nicht gesetzt oder ungültig ist.
 */
function dbx_get_PostGetVar(string $varname, $default = '', string $rules = 'parameter') {
   // Standardwert initialisieren
   $value = $default;
   $danger_value = '';

   // Prüfen, ob die Variable in GET oder POST vorhanden ist
   if (isset($_GET[$varname])) {
       $danger_value = $_GET[$varname];
   }
   if (isset($_POST[$varname])) {
       $danger_value = $_POST[$varname];
   }

   // Wenn ein Wert vorhanden ist und nicht null ist, validieren
   if (($danger_value !== '') && ($danger_value !== null)) {
       if (dbx_validate_var($danger_value, $rules, $varname)) {
           $value = $danger_value;
       }
   }

   // Rückgabe des validierten Werts oder des Standardwerts
   return $value;
}


/**
 * Holt eine Systemvariable aus der Session, POST oder GET und validiert sie.
 *
 * Diese Funktion prüft, ob eine Systemvariable in der Session, POST oder GET vorhanden ist,
 * validiert den Wert anhand der angegebenen Regeln und gibt den validierten Wert zurück. 
 * Wenn die Variable nicht vorhanden oder ungültig ist, wird der Standardwert zurückgegeben.
 *
 * @param string $varname Der Name der Systemvariable, die abgerufen werden soll.
 * @param mixed $default Der Standardwert, der zurückgegeben wird, wenn die Variable nicht gesetzt ist oder die Validierung fehlschlägt. (Standard: leerer String)
 * @param string $rules Die Validierungsregeln für die Variable. (Standard: '*' für keine Validierung)
 * @return mixed Der validierte Wert der Systemvariable oder der Standardwert, wenn die Variable nicht gesetzt oder ungültig ist.
 */
function dbx_get_SysVar(string $varname, $default = '', string $rules = '*') {
   // Initialisierung der Rückgabevariable
   $value = $default;
   $danger_value = '';

   // Überprüfen, ob die Variable in der Session vorhanden ist
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

   // Wenn ein Wert vorhanden und gültig ist, validieren und zurückgeben
   if (($danger_value !== '') && ($danger_value !== null)) {
       if (dbx_validate_var($danger_value, $rules, $varname)) {
           $value = $danger_value;
       }
   }

   // Rückgabe des validierten Werts oder des Standardwerts
   return $value;
}


/**
 * Holt eine "Remember"-Variable aus der Session und validiert sie.
 *
 * Diese Funktion sucht nach einer Variable in der Session unter der "remember"-Sektion des angegebenen Moduls,
 * validiert den Wert gemäß den angegebenen Regeln und gibt den validierten Wert zurück. 
 * Wenn die Variable nicht vorhanden oder ungültig ist, wird der Standardwert zurückgegeben.
 *
 * @param string $varname Der Name der "Remember"-Variablen, die abgerufen werden soll.
 * @param mixed $default Der Standardwert, der zurückgegeben wird, wenn die Variable nicht gesetzt ist oder ungültig ist. (Standard: leerer String)
 * @param string $rules Die Validierungsregeln für die Variable. (Standard: '*' für keine Validierung)
 * @param string $modul Das Modul, zu dem die "Remember"-Variable gehört. (Standard: 'modul')
 * @return mixed Der validierte Wert der "Remember"-Variable oder der Standardwert, wenn die Variable nicht gesetzt oder ungültig ist.
 */
function dbx_get_Remember(string $varname, $default = '', string $rules = '*', string $modul = 'modul') {
   // Initialisierung der Rückgabevariable
   $value = $default;
   $danger_value = '';

   // Wenn das Modul nicht angegeben ist, den aktiven Modulnamen aus der Session holen
   if ($modul == 'modul') {
       $modul = dbx_get_SysVar('dbx_activ_modul', 'dbx', '*');
   }

   // Überprüfen, ob die Variable in der Session unter der richtigen Modul-Sektion existiert
   if (isset($_SESSION['dbx']['remember'][$modul][$varname])) {
       $danger_value = $_SESSION['dbx']['remember'][$modul][$varname];
       // Wenn die Variable gültig ist, den Wert zurückgeben
       if (dbx_validate_var($danger_value, $rules, $varname)) {
           $value = $danger_value;
       }
   }

   // Rückgabe des validierten Werts oder des Standardwerts
   return $value;
}


/**
 * Setzt eine "Remember"-Variable in der Session und optional im System.
 *
 * Diese Funktion speichert eine Variable in der Session unter der "remember"-Sektion des angegebenen Moduls. 
 * Wenn das Modul `dbx` ist, wird die Variable zusätzlich in der System-Session gespeichert.
 *
 * @param string $varname Der Name der "Remember"-Variablen, die gesetzt werden soll.
 * @param mixed $value Der Wert, der für die Variable gespeichert werden soll.
 * @param string $modul Das Modul, zu dem die "Remember"-Variable gehört. (Standard: 'modul')
 */
function dbx_set_Remember(string $varname, $value, string $modul = 'modul') {
   // Wenn das Modul nicht angegeben ist, den aktiven Modulnamen aus der Session holen
   if ($modul == 'modul') {
       $modul = dbx_get_SysVar('dbx_activ_modul', 'dbx', '*');
   }

   // Setzt den Wert der Variable in der Session unter der "remember"-Sektion
   $_SESSION['dbx']['remember'][$modul][$varname] = $value;

   // Wenn das Modul 'dbx' ist, wird der Wert auch in der System-Session gespeichert
   if ($modul == 'dbx') {
       dbx_set_SysVar($varname, $value);
   }
}



/**
 * Setzt eine Systemvariable in der Session.
 *
 * Diese Funktion speichert eine Systemvariable in der Session unter `$_SESSION['dbx']['tmp'][0]['dbx']`,
 * sodass der Wert zwischen verschiedenen Anfragen erhalten bleibt.
 *
 * @param string $varname Der Name der Systemvariablen, die gesetzt werden soll.
 * @param mixed $value Der Wert, der für die Systemvariable gespeichert werden soll.
 */
function dbx_set_SysVar(string $varname, $value) {
   // Speichert den Wert der Systemvariable in der Session
   $_SESSION['dbx']['tmp'][0]['dbx'][$varname] = $value;
}
/**
 * Holt eine Modul-spezifische Variable aus verschiedenen Quellen (Session, GET, POST).
 * Wenn der Wert nicht vorhanden oder ungültig ist, wird der Standardwert zurückgegeben.
 * 
 * @param string $varname Der Name der zu holenden Variable.
 * @param mixed $default Der Standardwert, der zurückgegeben wird, wenn die Variable nicht gefunden wird oder ungültig ist. (Standard: '')
 * @param string $rules Die Validierungsregel für die Variable (Standard: 'alphanum').
 * 
 * @return mixed Der Wert der Variable oder der Standardwert, wenn die Variable nicht gefunden oder ungültig ist.
 */
function dbx_get_ModulVar($varname, $default = '', $rules = 'alphanum') {
   // Standardwert initialisieren
   $value = $default;

   // Das aktive Modul und die Modul-ID holen
   $modul = dbx_get_SysVar('dbx_activ_modul', 'undeff', '*');
   $mid = dbx_get_SysVar('dbx_activ_modul_id', 88888, '*');

   // Versuchen, die Variable aus der Session zu holen
   if (isset($_SESSION['dbx']['tmp'][$mid][$modul][$varname])) {
       $danger_value = $_SESSION['dbx']['tmp'][$mid][$modul][$varname];
   } else {
       // Wenn nicht in der Session, versuche GET und POST
       $danger_value = $_GET[$varname] ?? $_POST[$varname] ?? '';
   }

   // Wenn ein Wert vorhanden ist und er gültig ist, validiere den Wert
   if (!empty($danger_value) && dbx_validate_var($danger_value, $rules, $varname)) {
       $value = $danger_value;
   }

   // Rückgabe des Werts (entweder der gefundene gültige Wert oder der Standardwert)
   return $value;
}




/**
 * Setzt eine Modul-spezifische Variable in der Session.
 * 
 * Diese Funktion speichert den angegebenen Wert für eine Variable in der Session,
 * basierend auf dem aktiven Modul und der Modul-ID.
 * 
 * @param string $varname Der Name der zu setzenden Variable.
 * @param mixed $value Der Wert, der für die Variable gespeichert werden soll. (Standard: null)
 * 
 * @return void
 */
function dbx_set_ModulVar($varname, $value = null) {
   // Das aktive Modul und die Modul-ID holen
   $mid = dbx_get_SysVar('dbx_activ_modul_id', -1, '*');
   $modul = dbx_get_SysVar('dbx_activ_modul', 'undeff', '*');
   
   // Setze den Wert in der Session
   $_SESSION['dbx']['tmp'][$mid][$modul][$varname] = $value;

   // Optional: Debugging-Informationen hinzufügen
   // dbx_debug("dbx_set_ModulVar modul=($modul) Mod=($mid) Var=($varname) val=($value)");
}



// Session

function dbx_login($uid=0,$remember=0) {
  $old=dbx_get_CurrentUser();
  if ($uid != $old) {
    $oSession=dbx_get_sys_object('dbxSession');
    $oSession->login($uid,$remember);

    $page     =  dbx_get_base_url();
    $from     =  dbx_get_CurrentUser('email');
    $fromname =  dbx_get_CurrentUser('name');
    $subject  = 'Login ('.$from.') on ('.$page.') User=('.$uid.')';
    $text     = $subject;
    dbx_add_admin_msg('info','login',$uid,$subject,'ok');
    dbx_sendMail($from,$fromname,'login@dbxapp.de',$subject,$text,'text'); // #todo 

  }
}


function dbx_logout($uid=-888) {
   if ($uid==-888) $uid=dbx_get_CurrentUser();
   //dbx_debug("dbx_logout uid=($uid)"); 
   $oSession=dbx_get_sys_object('dbxSession');
   $oSession->logout($uid);
}

function dbx_set_SessionVal($key,$val,$section='sys',$modul='') {
  if (!$modul)   $modul =dbx_get_SysVar('dbx_activ_modul' ,'dbx');
  if ($key != '*') $_SESSION['dbx']['session'][$modul][$section][$key]=$val;
  if ($key == '*') $_SESSION['dbx']['session'][$modul][$section]=$val;
}

function dbx_get_SessionVal($key,$default=null,$section='sys',$modul='modul') {
  $val=$default;
  if ($modul=='modul')  $modul =dbx_get_SysVar('dbx_activ_modul' ,'dbx');
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

function dbx_del_SessionVal($key,$section='sys',$modul='modul') {
  if ($modul=='modul')  $modul =dbx_get_SysVar('dbx_activ_modul' ,'dbx');
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





//  Session - - - - - - - - - - - - - - - - - - - - - - - - -

function dbx_make_seed(){
    list($usec, $sec) = explode(' ', microtime());
    return (float) $sec + ((float) $usec * 100000);
}


function dbx_get_new_Pass($minlength,$special='-_!') {
  $passwort='';
  @mt_srand(dbx_make_seed());
  $syllabels = 'abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz0123456789'.$special;
  $len = strlen($syllabels) - 1;
  $box = "";
  for($i = 0; $i < 300; $i++) {// create box
    $ch = $syllabels[mt_rand(0, $len)];
    if (mt_rand(0, $len) % 5 == 1) {
        $ch = strtoupper($ch);// about 20% upper case letters
    }
    $box .= $ch;// filling up the box with random chars
  }
  // now collect password from box
  for($i = 0; $i < $minlength; $i++) {
    $passwort .= $box[mt_rand(0, (300 - 1))];
  }
  return $passwort;
}



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
  setcookie ($cookie, '', time() - 3600,1);
}


function dbx_get_cookie_val($cookie,$key,$default='') {
  $val=$default;
  if (isset($_SESSION['dbx']['cookie'][$cookie][$key])) {
     $val=$_SESSION['dbx']['cookie'][$cookie][$key];
  }
  return $val;
}


function dbx_set_cookie_val($cookie,$key,$val) {
  $_SESSION['dbx']['cookie'][$cookie][$key]=$val;
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
function dbx_get_Date(string $date, string $io, string $default = ''): string {
   $date = trim($date);

   // Ensure the date only contains valid characters.
   if (!preg_match('#^[0-9./-]+$#', $date)) {
       dbx_set_SysVar('dbx_validate_error', 1);
       return $default;
   }

   // Check date length.
   if (strlen($date) !== 10) {
       dbx_set_SysVar('dbx_validate_error', 1);
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
       dbx_set_SysVar('dbx_validate_error', 1);
       return $default;
   }

   $parts = explode($delimiter, $date);

   // Ensure valid parts based on delimiter.
   if (count($parts) !== 3) {
       dbx_set_SysVar('dbx_validate_error', 1);
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
       dbx_set_SysVar('dbx_validate_error', 1);
       return $default;
   }

   // Format date based on the desired output.
   if ($io === 'web') {
       return sprintf('%02d.%02d.%04d', $day, $month, $year);
   } elseif ($io === 'php') {
       return sprintf('%04d-%02d-%02d', $year, $month, $day);
   }

   // Default return in case of unsupported $io value.
   dbx_set_SysVar('dbx_validate_error', 1);
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
   return $_SESSION['dbx']['current_user']['userid'];
}


function dbx_check_access($access_groups='',$user_groups='') {
    $access=0;
    $uid=dbx_get_CurrentUser();

    if (!$user_groups) {
       $user_groups= $_SESSION['dbx']['current_user']['roles'];
    }

    if (!is_array($user_groups))   $user_groups  = explode(',',$user_groups);
    if (!is_array($access_groups)) $access_groups= explode(',',$access_groups);

    foreach ($user_groups as $no => $role) {
      if ($role == 'admin') return 1; // allways access
      foreach ($access_groups as $key => $group) {
         if ($group  == '*')   $access=1;
         if ($group  == $role) $access=1;
         //dbx_debug("Role=($role) <> Group=($group) #Access=($access)");
      }
    }


    //dbx_debug("#ACCESS=($access)",$user_groups,$access_groups);
    return $access;
}



function dbx_check_modul_access($modul) {

  $access=0;
  
  $current_user= $_SESSION['dbx']['current_user'];
  $modul_config= dbx_get_cfg($modul);
  $groups =$modul_config['groups'];
  $uid    =$current_user['userid'];
  $roles  =$current_user['roles'];
  $install=dbx_get_SysVar('dbx_install',0,'int');
  
 
  
  if (!is_array($roles))  $roles= explode(',',$roles);
  if (!is_array($groups)) $roles= explode(',',$groups);

  //dbx_debug("Modul Access ($modul) User=($uid)",$roles,$groups);
 


  if (!$install) {
    foreach ($roles as $no => $role) {
      foreach ($groups as $key => $group) {
         if ($group == '*')    $access=1;
         if ($role  == $group) $access=1;
         if ($role  =='admin') $access=1;
      }
    }
  } else {
    $access=1;
  }  
  if ($access==0) dbx_set_SysVar('dbx_noaccess_modul',"(User=$uid Modul=$modul)");
  return $access;
}


function dbx_is_integer($value) {
   if (is_int($value)) return 1;
   if (is_string($value) && filter_var($value, FILTER_VALIDATE_INT) !== false) return 1;
   return 0;
}



function dbx_set_CurrentUser($key,$value) {
   if ($key != '*') {
      $_SESSION['dbx']['current_user'][$key]=$value;
   } else {
     $_SESSION['dbx']['current_user']=$value;
   }
}
function dbx_get_CurrentUser($key='userid') {
   $value=null;
   if ($key != '*') {
     if (isset($_SESSION['dbx']['current_user'][$key])) {
        $value=$_SESSION['dbx']['current_user'][$key];
     }
   } else {
     $value=$_SESSION['dbx']['current_user'];
   }
   return $value;
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
   $oUpload=dbx_get_sys_object('dbxUpload');

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

  $html_in = str_replace ('&uuml;', 'ü', $html_in);
  $html_in = str_replace ('&ouml;', 'ö', $html_in);
  $html_in = str_replace ('&auml;', 'ä', $html_in);
  $html_in = str_replace ('&Uuml;', 'Ü', $html_in);
  $html_in = str_replace ('&Ouml;', 'Ö', $html_in);
  $html_in = str_replace ('&Auml;', 'Ä', $html_in);

  return $html_in;
}



// dbx Util


function dbx_part_select($vor,$nach,$part) {
  $leng= strlen($vor);
  $pos1= strpos($part, $vor);
  $part= substr($part, ($pos1+$leng));  // bcd
  $pos2= strpos($part, $nach);
  $part= substr($part, 0,$pos2);  // bcd
  if ($pos1=='') $part='';
  if ($pos2=='') $part='';
  return $part;
}

function dbx_add_norep($norep,$i=0) {
   $norep=str_replace("\r",'',$norep);
   $norep_id='norep_'.dbx_get_next_i();
   $_SESSION['dbx']['norep'][$norep_id]=$norep;
   return '['.$norep_id.']';
}


function dbx_interpreter($content) {
   $int=dbx_get_sys_object('dbxInterpreter');
   $content=$int->run($content);
   return $content;
}



// crypt / decrypt

 function dbx_decrypt($content,$xkey='',$master='') {
   //dbx_debug("#decrypt ($xkey)");
   if (!$master) $master=dbx_get_cfg('dbx','crypt');
   if (!$xkey)   $xkey  ='jkgj89bz7b789345%$&8t5';

   $crypt_key=md5($xkey.$master); 

   $key= substr($crypt_key, 0, 16);
   $iv = substr($crypt_key, -16);

   //dbx_debug("decrypt ($xkey) ($master) Key=($key) IV=($iv)"); 


   $aes = new AES('cbc');
   $aes->setKey($key);
   $aes->setIV($iv);
   
   $decrypt_content = $aes->decrypt($content);
  
   return $decrypt_content;
}

function dbx_crypt($content,$xkey='',$master='') {
   //dbx_debug("#crypt ($xkey)");
   if (!$master) $master=dbx_get_cfg('dbx','crypt');
   if (!$xkey)   $xkey  ='jkgj89bz7b789345%$&8t5';

   $crypt_key=md5($xkey.$master); 

   $key= substr($crypt_key, 0, 16);
   $iv = substr($crypt_key, -16);
   
   //dbx_debug("crypt ($xkey) ($master) Key=($key) IV=($iv)"); 


   $aes = new AES('cbc');
   $aes->setKey($key);
   $aes->setIV($iv);
   
   $crypt_content = $aes->encrypt($content);
  
   return $crypt_content;
}