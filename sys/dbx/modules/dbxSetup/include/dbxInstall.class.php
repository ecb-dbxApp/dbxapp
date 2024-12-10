<?php
namespace dbx\dbxSetup;


class dbxInstall {

   Public $oTPL;

   public function __construct() {
     $this->oTPL = dbx_get_sys_object('dbxTPL');
   }


private function check_write_cfg() {
return 1; // config nicht überschreiben - anders auf schreibar testen
   $ok=0;
   $test ='<?php'."\n".'$ok=1;'."\n?>";
   $file_path=dbx_get_file_dir().'sys/cfg/config.php';
   $file_path=dbx_os_path_file($file_path);
   @file_put_contents($file_path,$test);
   if (file_exists($file_path)) {
     include $file_path; // $ok=1;
   }
   return $ok;
}

    

  private function check_db_connect($config) {
    $ok=0; $db=0;
    try {
      $db = mysqli_connect($config['host'], $config['user'], $config['password']);
     } catch (\Exception $e) {
        $ok=0;
        dbx_debug("SQL-EXCEPTION",$e);
      } catch (\Error  $e) {
        $ok=0;
        dbx_debug("SQL-ERROR",$e);
      }    
    
    if ($db) $ok=1;      
    return $ok;
  }
  
  
  private function check_db_exist($config){
    $ok=0; $db=0;
    try {
      $db = mysqli_connect($config['host'], $config['user'], $config['password']);
    } catch (\Exception $e) {
    } catch (\Error  $e) { }; 
    if ($db) {
        try {  
           if (mysqli_select_db($db,$config['name'])) $ok=1;
        } catch (\Exception $e) {
        } catch (\Error  $e) { }; 
    }   
    return $ok;  
  }
  
  private function check_db_create($config) {
    $ok=0; $db=0;
    $database=$config['name'];
    $sql = "CREATE DATABASE $database";
    try {
      $db = mysqli_connect($config['host'], $config['user'], $config['password']);
    } catch (\Exception $e) {
    } catch (\Error  $e) { }; 
    if ($db) {
      $ok=$db->query($sql);
    } 
    return $ok;
  }
  
  private function write_cfg($config) {
    $ok=dbx_set_cfg('dbx',$config);
    return $ok;
  }



  private function install_1() {
  
    $content=''; $ok=false; $js='';
    $config=dbx_get_cfg('dbx');
    $oForm =dbx_get_sys_object('dbxForm');
     
    $oForm->init('install-1','form-install',);
    $oForm->_action='?dbx_modul=dbxInstall&dbx_action=install&stepp=1';
    $oForm->_data  =$config;
    $oForm->_fld_change_state='*'; // Importent write all flds to config
    $oForm->_msg_info   = 'Bitte geben Sie die Daten vom Datenbak Server ein.';
    $oForm->_msg_err    = 'Die Verbindung zum db-Server konnten nicht hergestellt werden';
    $oForm->_msg_success= 'Verbindung zum db-Server ist erfolgreich';
    //     add_fld($name       ,$tpl        ,$data,  $rules ,$label                   ,$tooltip                                                    ,$msg,$placeholder,$class) 
    $oForm->add_fld('host'     ,'text-label',''   ,'*|min=1','Datenbankserver'        ,'Host Adresse der Datenbank IP oder Domain vom db-Server');
    $oForm->add_fld('name'     ,'text-label',''   ,'*|min=1','Datenbank Name '        ,'Name der Datenbank');
    $oForm->add_fld('user'     ,'text-label',''   ,'*|min=1','Benutzername Datenbank' ,'Benutzer Name der Datenbank');
    $oForm->add_fld('password' ,'text-label',''   ,'*'      ,'Passwort der Datenbank' ,'Passwort der Datenbank');
    $oForm->add_fld('port'     ,'text-label',''   ,'int'    ,'Port der Datenbank'     ,'Port des db-Server (leer=default)'                         ,'','leer=default');

    $oForm->add_obj('button','dbx|button-submit','label=Speichern und weiter');       

    $oForm->add_obj('progress','');

    if ($oForm->submit()) {
      $config=array_merge($config,$oForm->_post);
      if ($oForm->errors()) {
        dbx_set_SessionVal('stepp',1,'dbxInstall');
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

    $config =dbx_get_cfg('dbx');
    $db_exist  =$this->check_db_exist($config);
    if (!$db_exist) return $this->install_1();  

    $oForm=dbx_get_sys_object('dbxForm');
    $oForm->init('install-2','form-install',);
    $oForm->_action='?dbx_modul=dbxInstall&dbx_action=install&stepp=2';
    $oForm->_data=$config;


    $oForm->add_obj('progress',$this->oTPL->get_tpl('dbx','progressbar-1','msg=Sql Script'));
    $oForm->add_obj('button','dbx|button-submit','label=SQL Script ausführen und Datenbank Tabellen erstellen');
     
    $oForm->_msg_info     = 'Verbindung zum Datenbankserver (' .$config['host']. ')  Datenbank (' .$config['name'] .')  erfolgreich hergestellt.';
    $oForm->_msg_success  = 'Verbindung zum Datenbankserver (' .$config['host']. ')  Datenbank (' .$config['name'] .')  Daten werden eingelesen.';
    $oForm->_msg_err      = 'Es ist ein Fehler bei der Installation aufgetreten.';

    if($oForm->submit()) {
        if(!$oForm->errors()) {
           $install_file =dbx_get_file_dir().'sys/install/data/install.sql';
           if (file_exists($install_file)) {
             $oForm->_msg_success = 'Das install script ('.$install_file.') wird ausgeführt.';
             $oImporter =dbx_get_sys_object('dbxSQLreader');
             $oImporter->set_property('filename',$install_file);
             $status=$oImporter->run();
             dbx_debug("IMPORTER-Status=($status)");
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

    $config =dbx_get_cfg('dbx');
    $db_exist  =$this->check_db_exist($config);
    if (!$db_exist) return $this->install_1();  


    if (!isset($config['secure'])) $config['secure']=dbx_get_new_Pass(32);
    $oForm=dbx_get_sys_object('dbxForm');
    $oForm->init('install-3','form-install');
    $oForm->_action='?dbx_modul=dbxInstall&dbx_action=install&stepp=3';

    $oForm->_data=$config;
    $oForm->_fld_change_state='*';
    $oForm->add_obj('progress','');
    $oForm->add_obj('button','dbx|button-submit','label=Admin Daten eintragen und weiter.');

    
    //      add_fld($name       ,$tpl             ,$data,     $rules           ,$label                         ,$tooltip                                       ,$msg           ,$placeholder,$class)
    $oForm->add_fld('secure'    ,'text-label'     ,''        ,'*|min=1'        ,'Key für Verschlüsselung'      ,'Dieser Wert ist der Key für Verschlüsselungen','security key');
    $oForm->add_fld('page'      ,'text-label'     ,''        ,'words|min=1'    ,'Name der Seite / App'         ,'Geben Sie bitte den Namen Ihrer Seite ein'    ,'name@domain.de');
    $oForm->add_fld('email'     ,'text-label'     ,''        ,'email|min=1'    ,'eMail von Benutzer admin'     ,'Geben Sie bitte ihre eMail Adresse ein'       ,'name@domain.de');
    
    $oForm->add_fld('pass1'     ,'password-label' ,''        ,'password|min=1' ,'Passwort für Benutzer admin'  ,'Geben Sie bitte ein gewünschtes Passwort ein' ,'Passwort für Benutzer admin');
    $oForm->add_fld('pass2'     ,'password-label' ,''        ,'password|min=1' ,'Passwort wiederholt eingeben' ,'Passwort wiederholt eingeben'                 ,'Passwort für Benutzer admin');

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
            $path=dbx_get_base_dir();
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
           $db=dbx_get_sys_object('dbxDB');
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
           $oForm->_action='?dbx_modul=dbxLogin&dbx_action=run&dbx_design=default&dbx_page=default';
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
    $stepp=dbx_get_ModulVar('stepp',1,'int');
      
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
