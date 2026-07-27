<?php
namespace dbx\dbxSetup;


class dbxInstall {

   Public $oTPL;

   public function __construct() {
     $this->oTPL = dbx()->get_system_obj('dbxTPL');
   }


private function check_write_cfg() {
return 1; // config nicht überschreiben - anders auf schreibar testen
   $ok=0;
   $test ='<?php'."\n".'$ok=1;'."\n?>";
   $file_path=dbx()->get_file_dir().'sys/cfg/config.php';
   $file_path=dbx()->os_path($file_path);
   @file_put_contents($file_path,$test);
   if (file_exists($file_path)) {
     include $file_path; // $ok=1;
   }
   return $ok;
}

    

  private function install_db_config($config) {
    return array(
      'type'   => $config['type'] ?? 'mysql',
      'host'   => $config['host'] ?? '',
      'dbname' => $config['dbname'] ?? ($config['name'] ?? ''),
      'user'   => $config['user'] ?? '',
      'pass'   => $config['pass'] ?? ($config['password'] ?? ''),
      'port'   => $config['port'] ?? '',
    );
  }

  private function check_db_connect($config) {
    $db = dbx()->get_system_obj('dbxDB');
    return is_object($db) ? $db->can_connect_database_config($this->install_db_config($config), false) : 0;
  }
  
  
  private function check_db_exist($config){
    $db = dbx()->get_system_obj('dbxDB');
    return is_object($db) ? $db->can_connect_database_config($this->install_db_config($config), true) : 0;
  }
  
  private function check_db_create($config) {
    $dbConfig = $this->install_db_config($config);

    $db = dbx()->get_system_obj('dbxDB');
    return is_object($db) ? $db->ensure_database_exists('dbxInstall', $dbConfig) : 0;
  }
  
  private function write_cfg($config) {
    $ok=dbx()->set_config('dbx',$config);
    return $ok;
  }



  private function install_1() {
  
    $content=''; $ok=false; $js='';
    $config=dbx()->get_config('dbx');
    $oForm =dbx()->get_system_obj('dbxForm');
     
    $oForm->init('install-1','form-install',);
    $oForm->_action='?dbx_modul=dbxSetup&dbx_run1=install&stepp=1';
    $oForm->_data  =$config;
    $oForm->_fld_change_state='*'; // Importent write all flds to config
    $oForm->_msg_info   = 'Bitte geben Sie die Daten vom Datenbak Server ein.';
    $oForm->_msg_err    = 'Die Verbindung zum db-Server konnten nicht hergestellt werden';
    $oForm->_msg_success= 'Verbindung zum db-Server ist erfolgreich';

    $oForm->add_fld('host'     ,'text-label' ,rules: '*|min=1',label: 'Datenbankserver'        ,tooltip: 'Host Adresse der Datenbank IP oder Domain vom db-Server');
    $oForm->add_fld('name'     ,'text-label' ,rules: '*|min=1',label: 'Datenbank Name '        ,tooltip: 'Name der Datenbank');
    $oForm->add_fld('user'     ,'text-label' ,rules: '*|min=1',label: 'Benutzername Datenbank' ,tooltip: 'Benutzer Name der Datenbank');
    $oForm->add_fld('password' ,'text-label' ,rules: '*'      ,label: 'Passwort der Datenbank' ,tooltip: 'Passwort der Datenbank');
    $oForm->add_fld('port'     ,'text-label' ,label: 'Port der Datenbank'     ,rules: 'int'    ,data: ''     ,options: 'leer=default' ,tooltip: 'Port des db-Server (leer=default)');

    $oForm->add_obj('button','dbx|button-submit','label=Speichern und weiter');       
    $oForm->add_obj('progress','');

    if ($oForm->submit()) {
      $config=array_merge($config,$oForm->_post);
      if ($oForm->errors()) {
        dbx()->set_session_var('stepp',1,'dbxInstall');
      }
      if(!$oForm->errors()) {      // submit && no errors
        // 1. check write cfg
        $ok=$this->check_write_cfg();
        if (!$ok) $oForm->_msg_error='System Datei cfg/config.php konnte nicht erstellt werden';
        // 2. check db connection
        if ($ok) {
           $host=$config['host'];
           $name=$config['name']; 
           $db_connect= $this->check_db_connect($config);
           if (!$db_connect) {
              $oForm->add_fld_error('-host','!'); 
              $oForm->_msg_error="System konnte keine Verbindung zum db-Server ($host) aufgebaut werden (db-Server / User / Passwort)";
           } 
           if ($db_connect) {
              // 3. check db exist/create
              $db_exist=$this->check_db_exist($config);
              if (!$db_exist) $db_exist=$this->check_db_create($config);
              if (!$db_exist) {
                 $oForm->add_fld_error('-name','!'); 
                 $oForm->_msg_error="System konnte die Datenbank nicht erstellen.<br><br>Erstellen Sie bitte die auf Ihrem Datenbank-Server (<b>$host</b>) die Datenbank (<b>$name</b>) über die Administration Ihres SQL-Servers.<br><br>Das Anlegen einer Datenbank ist bei ihrem Server nur manuell möglich.<br><br>Nach dem Anlegen der Datenbank, oder Auswahl einer bestehenden Datenbank, wiederholen Sie bitte die Installation von dbXapp.";
              }
           }
           if  ($db_connect && $db_exist) {
             $this->write_cfg($config);
             return $this->install_2();
           }
      	} // ok
      } // !errors
    } // submit
    return $oForm->run();
  } // install_1



  public function install_2() {
    //global dbxGlobalVar;
    $content=''; $status=0;

    $config =dbx()->get_config('dbx');
    $db_exist  =$this->check_db_exist($config);
    if (!$db_exist) return $this->install_1();  

    $oForm=dbx()->get_system_obj('dbxForm');
    $oForm->init('install-2','form-install',);
    $oForm->_action='?dbx_modul=dbxSetup&dbx_run1=install&stepp=2';
    $oForm->_data=$config;


    $oForm->add_obj('progress',$this->oTPL->get_tpl('dbx','progressbar-1','msg=Sql Script'));
    $oForm->add_obj('button','dbx|button-submit','label=SQL Script ausführen und Datenbank Tabellen erstellen');
     
    $oForm->_msg_info     = 'Verbindung zum Datenbankserver (' .$config['host']. ')  Datenbank (' .$config['name'] .')  erfolgreich hergestellt.';
    $oForm->_msg_success  = 'Verbindung zum Datenbankserver (' .$config['host']. ')  Datenbank (' .$config['name'] .')  Daten werden eingelesen.';
    $oForm->_msg_err      = 'Es ist ein Fehler bei der Installation aufgetreten.';

    if($oForm->submit()) {
        if(!$oForm->errors()) {
           $install_file =dbx()->get_file_dir().'sys/install/data/install.sql';
           if (file_exists($install_file)) {
             $oForm->_msg_success = 'Das install script ('.$install_file.') wird ausgeführt.';
             $oImporter =dbx()->get_system_obj('dbxSQLreader');
             $oImporter->set_property('filename',$install_file);
             $status=$oImporter->run();
             dbx()->debug("IMPORTER-Status=($status)");
             if (!$status) {
                $oForm->add_obj('import',$info.' Ein Fehler ist aufgetreten');// some error ??
             }
             if ($status == 1) { // continue

                $filesize=$oImporter->get_property('filesize');
                $done    =$oImporter->get_property('done');
                $percent =$oImporter->get_property('percent');
                $querys  =$oImporter->get_property('querys');

                $progress=$this->oTPL->get_tpl('dbx','progressbar-1');
                $msg="Querys=($querys) ($percent %)";

                $pdata['msg']   = $msg;
                $pdata['width'] = $percent;
                $oForm->add_obj('progress',$progress,$pdata,'*');
                $oForm->add_js_autosubmit('dbx_form_{i}',2);
                
                
             }
             if ($status == 2) { // finisch import and process data
                $msg=$this->oTPL->get_tpl('dbx','alert-success','msg=Datenbank und Tabellen wurden erstellt');
                $oForm->add_obj('import',$msg);
                return $this->install_3();
             }
          } else {
             $oForm->add_fld_error('x','!'); // make an error
             $oForm->_msg_err = 'Das SQL install script ('.$install_file.') ist nicht vorhanden';
          }
        }
    } // submit
    return $oForm->run();
  } // install_3







  public function install_3() {
    //global dbxGlobalVar;
    $content=''; $status=0;

    $config =dbx()->get_config('dbx');
    $db_exist  =$this->check_db_exist($config);
    if (!$db_exist) return $this->install_1();  


    if (!isset($config['secure'])) $config['secure']=dbx()->new_password(32);
    $oForm=dbx()->get_system_obj('dbxForm');
    $oForm->init('install-3','form-install');
    $oForm->_action='?dbx_modul=dbxSetup&dbx_run1=install&stepp=3';

    $oForm->_data=$config;
    $oForm->_fld_change_state='*';
    $oForm->add_obj('progress','');
    $oForm->add_obj('button','dbx|button-submit','label=Admin Daten eintragen und weiter.');

    
    $oForm->add_fld('secure'    ,'text-label'     ,label: 'Key für Verschlüsselung' ,rules: '*|min=1'         ,tooltip: 'Dieser Wert ist der Key für Verschlüsselungen');
    $oForm->add_fld('page'      ,'text-label'     ,label: 'Name der Seite / App' ,rules: 'words|min=1'     ,tooltip: 'Geben Sie bitte den Namen Ihrer Seite ein'    );
    $oForm->add_fld('email'     ,'text-label'     ,label: 'eMail von Benutzer admin' ,rules: 'email|min=1'     ,tooltip: 'Geben Sie bitte ihre eMail Adresse ein'       );
    
    $oForm->add_fld('pass1'     ,'password-label' ,label: 'Passwort für Benutzer admin' ,rules: 'password|min=1'  ,tooltip: 'Geben Sie bitte ein gewünschtes Passwort ein' );
    $oForm->add_fld('pass2'     ,'password-label' ,label: 'Passwort wiederholt eingeben' ,rules: 'password|min=1'  ,tooltip: 'Passwort wiederholt eingeben'                 );

    $oForm->_msg_info     = 'Datenbank Tabellen wurden erstellt und Daten eingelesen.';
    $oForm->_msg_success  = 'Passwort und eMail für den Benutzer admin eingetragen.';
    $oForm->_msg_err      = 'Es ist ein Fehler bei der Installation aufgetreten.';


    if($oForm->submit()) {
        $email=$oForm->_post['email'];
        $pass1=$oForm->_post['pass1'];
        $pass2=$oForm->_post['pass2'];
        if(!$oForm->errors()) {      // submit && no errors
          $oForm->_msg_success = 'Datenbank Tabellen werden neu erstellt und Passwort für Benutzer <b>admin</b> eingetragen.';
          if ($pass1 != $pass2 || $pass2 == '') {
             $oForm->_msg_error = 'Das Passwort muss zweimal gleich eingegen werden';
             $oForm->add_fld_error('x','');
          }
        }
        
        if (!$oForm->errors()) {
            $path=dbx()->get_base_dir();
            $to=$path.'.htaccess';
            if ($this->is_intranet()) {
               $from=$path.'xamp.htaccess';
            } else {
               $from=$path.'web.htaccess';
            }
            if (!copy($from, $to)) {
               $oForm->add_fld_error('x','');
               $oForm->_msg_error = 'Das System konnte die .htaccess Datei nicht kopieren !';
            }           
        }
        
        if(!$oForm->errors()) {        
           $data['pass']=md5($pass1);
           $db=dbx()->get_system_obj('dbxDB');
           $ok=$db->save('dbx_user',$data,'uname = "admin"',0);
           if (!$ok) {
               $oForm->add_fld_error('x','');
               $oForm->_msg_error = 'Das System konnte den Benutzer admin nicht speichern.';
           }
        }
        
        if(!$oForm->errors()) {
           if (isset($config['install-1']))    unset($config['install-1']);
           if (isset($config['dbxInstall-1'])) unset($config['dbxInstall-1']); 
           $config['page']=$oForm->_post['page'];
           $config['ok']  =1; // system install success 
           $this->write_cfg($config);
           $oForm->_action='?dbx_modul=dbxLogin&dbx_run1=run&dbx_design=default&dbx_page=default';
           $oForm->_msg_success = 'System wurde erfolgreich installiert.';
           $oForm->add_obj('button','dbx|button-submit','label=dbXapp System starten.');
        }
    } // submit
    return $oForm->run();
  } // install_3


  private function is_intranet() {
     $intranet=0;
     if ($_SERVER['SERVER_NAME'] == 'localhost') $intranet=1;
     if ($_SERVER['SERVER_ADDR'] == '127.0.0.1') $intranet=1;
     return $intranet;
  }


 



  public function run() {
    $stepp=dbx()->get_modul_var('stepp',1,'int');
      
    switch ($stepp) {
        case '1':
          $content=$this->install_1();
        break;
        case '2':
          $content=$this->install_2();
        break;
        case '3':
          $content=$this->install_3();
        break;
        case '4':
          $content=$this->install_4();
        break;        
        default:   
          $content=$this->install_1();   
    }   
    return $content;
  } // run()

} // class

?>
