<?php

class dbxDB  {


  
  public $db=array();

  Public $pdo=null;

  public $_connected=0;
  public $_server='';
  
  Public $_insert_id =  0;

  Public $_update_count=0;
  
  Public $_delete_count=0;
    
  public $_dbMessage='';

  public $oValidator               = '';
  public $_validation_error       = 0;
  public $_validation_warning     = 0;
  public $_validation_error_flds  = array();
  public $_validation_warning_flds= array();


  public $_validatior_rules=0;   // save insert update 
  public $_validatior_type =1;   // type der Daten prüfen
  public $_validatior_error=0;   // Bei validate Fehler db error oder warning
  public $_validatior_mode ='clean'; // Bei validate Fehler daten 'clean' oder 'unset'



  public $_error='';
  public $_query='';

  public function __construct() {   
    $this->oValidator=dbx_get_sys_object('dbxValidator');
    $this->db=array();
    $this->_connected=0;
    $this->_server='';
    $this->_insert_id=0;
    $this->_update_count=0;
    $this->_delete_count=0;
    $this->_dbMessage='';
    $this->_validation_error       = 0;
    $this->_validation_warning     = 0;
    $this->_validation_error_flds  = array();
    $this->_validation_warning_flds= array();
  
  }


  public function __destruct() {
    $this->db=null;
 }

 function isSQLiteDatabaseLocked($databasePath) {
  $isLocked = 0;

  try {
      $pdo = new PDO("sqlite:$databasePath");
      $pdo->exec('PRAGMA locking_mode=NORMAL');
      $pdo->beginTransaction();
  } catch (PDOException $e) {
      // If an exception occurs, it means the database is locked
      $isLocked = 1;
  } finally {
      if (isset($pdo)) {
          $pdo->rollBack();
      }
  }
  if ($isLocked) dbx_debug("SQLITE db ($databasePath) Lock=($isLocked)");
  return $isLocked;
}

  function dbConnect($server,$dbType, $dbHost, $dbName, $dbUser, $dbPassword, $dbPort='') {
    $ok=1;
    
    if (!isset($this->db[$server])) {
      //dbx_debug("#dbConnect Server=($server) Type=($dbType) Host=($dbHost) db=($dbName)");
      try {
          switch ($dbType) {
              case 'sqlite':
                  if ($dbHost) {
                    $path=$dbHost;
                  } else {
                    $path=dbx_get_file_dir().'sys/SQLite/';
                  }
                  $path=dbx_os_path_file($path);
                  $dbName = $path.$dbName.'.db3';

                  //dbx_debug("#dbConnect Server=($server) Type=($dbType) Host=($dbHost) db=($dbName)");

                  $this->db[$server] = new PDO("sqlite:$dbName");
                  break;
              case 'mysql':
                  $this->db[$server] = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPassword);
                  break;
              case 'pgsql':
                  $this->db[$server] = new PDO("pgsql:host=$dbHost;dbname=$dbName", $dbUser, $dbPassword);
                  break;
              case 'sqlsrv':
                  $this->db[$server] = new PDO("sqlsrv:Server=$dbHost;Database=$dbName", $dbUser, $dbPassword);
                  break;
              case 'oci':

                $dbtns = "(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = //$dbHost)(PORT = $dbPort)) (CONNECT_DATA = (SERVICE_NAME = $dbName) ))";
          
                $this->db[$server] =new PDO("oci:dbname=" . $dbtns . ";charset=utf8", $dbUser, $dbPassword, array(
                  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                  PDO::ATTR_EMULATE_PREPARES => false,
                  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC));


                  break;
              default:
                  throw new Exception("Unsupported database type: $dbType");
          }
          $this->db[$server]->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

      } catch (PDOException $e) {
          $ok=0;
          $dbMessage=$e->getMessage();
          dbx_add_admin_msg('error','db',$dbName,"db server=$server",$dbMessage);
          $this->_dbMessage=$dbMessage;
          $this->_error   ="Error Connecting DB Server=($server) DB=($dbName)";
          $this->_query   ="Connect Server ($server) Type=($dbType) Host=($dbHost) dbName=($dbName) dbUser=($dbUser) dbPass=($dbPassword) Port=($dbPort)";
      }
    } else {
      //dbx_debug("DD cached Server=($server)");
    }
  
    //dbx_debug("Connect ($server)=($ok)");
    return $ok;
}




public function connect_db_server($server) {
  $ok=0;
  $this->_server='try:'.$server;
  $this->_connected=0;  
  if ($this->_server == $server) $ok=1; 
  if ($this->_server != $server) {

    if (!isset($this->db[$server])) {
      $config=dbx_get_cfg('dbx');
      if (!isset($config['db'][$server])) {
        dbx_debug("ERROR connect_db_server Server=($server)",$config);  
      } 
      if (isset($config['db'][$server])) {
        $dbType= $config['db'][$server]['type'];
        $dbHost= $config['db'][$server]['host']; 
        $dbName= $config['db'][$server]['name'];
        $dbUser= $config['db'][$server]['user'];
        $dbPass= $config['db'][$server]['pass'];
        $dbPort= $config['db'][$server]['port'];

        //dbx_debug("connect to ($server)=",$config['db'][$server]); 

        $ok=$this->dbConnect($server, $dbType, $dbHost, $dbName, $dbUser, $dbPass, $dbPort); 
      }  
    } 
    if (isset($this->db[$server])) {
      $ok=1;
      $this->_connected=1;
      $this->_server=$server;
    }  
  }

  return $ok;
}

public function get_db_type($server) {
  $dbType='undef';
  $config=dbx_get_cfg('dbx');
  if (isset($config['db'][$server]['type'])) $dbType=$config['db'][$server]['type'];
  return $dbType; 
}


function get_dd_server($dd) {
   $dd_server=0;
   if (!isset($_SESSION['dbx']['cache']['dd'][$dd])) {
     $ok=$this->load_dd($dd);
   }

   if (isset($_SESSION['dbx']['cache']['dd'][$dd])) {
    $dd_config=$_SESSION['dbx']['cache']['dd'][$dd]['table']; 
    $dd_server=$dd_config['server'];   
   }
   if ($dd_server=='default') {
      $config=dbx_get_cfg();  
      $dd_server=$config['default_server'];
   }  
  
   return $dd_server;
}


function get_dd_table($dd,$rec=0) {
  //dbx_debug("#get_dd_table DD=($dd)");
  $dd_table=0;
  $ok=$this->load_dd($dd);

  //dbx_debug("#get_dd_table DD=($dd) OK=($ok) def=",$_SESSION['dbx']['cache']['dd'][$dd]);
  if ($ok && !$rec) $dd_table=$_SESSION['dbx']['cache']['dd'][$dd]['table']['table'];
  if ($ok &&  $rec) $dd_table=$_SESSION['dbx']['cache']['dd'][$dd]['table']; 
  //dbx_debug("DD=($dd) Table=",$dd_table);
  return $dd_table;
}

function get_dd_autosync($dd,$rec=0) {
  //dbx_debug("#get_dd_table DD=($dd)");
  $dd_sync=0;
  $ok=$this->load_dd($dd);

  //dbx_debug("#get_dd_table DD=($dd) OK=($ok) Rec=($rec) def=",$_SESSION['dbx']['cache']['dd'][$dd]);
  if ($ok && !$rec) $dd_sync=$_SESSION['dbx']['cache']['dd'][$dd]['table']['autosync'];
  if ($ok &&  $rec) $dd_sync=$_SESSION['dbx']['cache']['dd'][$dd]['autosync']; 
  //dbx_debug("DD=($dd) Sync=($dd_sync)");
  return $dd_sync;
}




public function load_dd($dd) {
  $ok     = -1; 
  $table  =array(); 
  $fields =array();
  $dd_file=dbx_os_path_file(dbx_get_base_dir().'dbx/modules/dbx/dd/'.$dd.'.dd.php');

  //dbx_debug("dd file for ($dd) =($dd_file)");

  if (!isset($_SESSION['dbx']['cache']['dd'][$dd])) {
    if (!file_exists($dd_file)) { 
      dbx_add_admin_msg('error','dd',$dd,'missing',''); 
      //$this->create_dd($ddTab);
    }
    if (file_exists($dd_file)) {
       include $dd_file;
       //dbx_debug ("Load dd ($dd)");
       $_SESSION['dbx']['cache']['dd'][$dd]['table'] =$table;
       $_SESSION['dbx']['cache']['dd'][$dd]['fields']=$fields;
       $ok=1;
    } else {
      $ok=0; // no dd exist
    }
  } else {
    //dbx_debug("CACED DD $dd");
    $ok=1; // dd is cached 
  }
  return $ok;
}



public function query($server, $sql) {
  $ok = -1;
  $dbtype  = $this->get_db_type($server);        // Holen des DB-Typs 
  $connect = $this->connect_db_server($server);  // Verbindung zum Server 

  if ($connect) {
      $ok = 1;
      try {
          // Vorbereiten der SQL-Abfrage
          //dbx_debug("try=($sql)");
          

          $stmt = $this->db[$server]->prepare($sql);
          // Ausführen der Abfrage
          $stmt->execute();

          return $stmt;
      } catch (PDOException $e) {
          $dbMessage = $e->getMessage();
          dbx_add_admin_msg('error', 'db', $server, "Query error ($sql)", $dbMessage);
          $this->_dbMessage = $dbMessage;
          $ok = 0;
      }
  }
  return $ok;
}





public function exec($server,$sql) {
  $ok= -1;
  $connect=$this->connect_db_server($server);
  if ($connect) {
    $ok=1;
    try {
      $this->db[$server]->exec($sql);
    } catch (PDOException $e) {
      $dbMessage=$e->getMessage();
      dbx_add_admin_msg('error','db',$server,"Exec Query error ($sql)",$dbMessage);
      $this->_dbMessage=$dbMessage;
      $ok=0;
    }
  }
  return $ok;
}



public function select_query($server, $sql) {
  $retval = -2;    
  $stmt = $this->query($server, $sql);  

  if (is_object($stmt)) {
      try {
          $retval = $stmt->fetchAll(PDO::FETCH_ASSOC);
          //$retval = $this->array_stripslashes($retval); // Rekursive stripslashes-Anwendung
      } catch (PDOException $e) {
          $dbMessage = $e->getMessage();
          dbx_add_admin_msg('error', 'db', $server, "Exec Query error ($sql)", $dbMessage);
          $this->_dbMessage = $dbMessage;
          $retval = -2;
      } catch (Exception $e) {
          // Allgemeine Ausnahmen behandeln
          $dbMessage = $e->getMessage();
          $this->_dbMessage = $dbMessage;
          $retval = -2;
      }
  } else {
      $this->_dbMessage = "Exec Query error ($sql)";
      $retval = -2;
  }
  return $retval;
}

private function array_stripslashes($data) {
  if (is_array($data)) {
      foreach ($data as $key => $value) {
          $data[$key] = $this->array_stripslashes($value);
      }
  } elseif (is_string($data)) {
      $data = stripslashes($data);
  }
  return $data;
}


public function insert_query($server,$sql) {
  $retval= -2;
  $this->_insert_id=0;
  $this->query($server,$sql);
  $retval=$this->db[$server]->lastInsertId();
  $this->_insert_id=$retval;
  //dbx_debug("#### INSERT-ID=($retval)");
  return $retval;
}

public function update_query($server,$sql) {
  $retval= -2;
  $this->_update_count=0;
  $stmt  =$this->query($server,$sql);
  if ($stmt) $retval=$stmt->rowCount();
  $this->_update_count=$retval;
  return $retval;
}

public function delete_query($server,$sql) {
  $retval=-2;
  $this->_delete_count=0;
  $stmt = $this->query($server,$sql);
  if ($stmt) $retval= $stmt->rowCount();
  $this->_delete_count=$retval;
  return $retval;
}




public function exec_query($server,$sql) {
  $ok = $this->exec($server,$sql);
  return $ok;
}




public function rawQuery($server,$query) {
 


    $ok=1; $db_records=0; // no array if error 
    if ($server) {
      $iChars=6;
      $connect   = $this->connect_db_server($server);
      $pos=strpos($query,' ');
      if ($pos < $iChars) $iChars=$pos;
      $queryType = strtoupper(substr(trim($query), 0, $iChars));

      //dbx_debug("rawQuery Server=($server) Ok=($connect) QT=($queryType)  Query=($query) ");
  
      if ($connect) {
        
        try {
        
          switch ($queryType) {
              case 'SELECT':
              case 'PRAGMA':
              case 'SHOW':    
                  return $this->select_query($server,$query);
              case 'INSERT':
                  return $this->insert_query($server,$query);
              case 'UPDATE':
                  return $this->update_query($server,$query);
              case 'DELETE':
                  return $this->delete_query($server,$query);
                  
              default:
                  //dbx_debug("#EXEC QueryType=($queryType) Q=($query)");
                  return $this->exec_query($server,$query);
                  //$pdo->exec($resetAutoIncrementSql);
          }

        }  catch (PDOException $e) {
            $dbMessage=$e->getMessage();
            dbx_add_admin_msg('error','db',$server,"Query error ($query)",$dbMessage);
            $this->_dbMessage=$dbMessage;
            $this->_error   ="Error DB Server=($server) QeryType=($queryType) MSG=($dbMessage)";
            $this->_query   =$query;
            $ok=0;
        }    

      } else {
        dbx_add_admin_msg('error','db',$server,"not connect server ($server)",'Server not connected');
        $this->_error   ="Error DB Server=($server) not Connected";
        $this->_query   =$query;
      }
    }
    return $ok;
  }


public function select($dd,$where='',$columns='*',$orderby='',$asc_desc='ASC',$groupby='',$max=0,$offset=0,$verify_access=1) {
    $access=1; $fields=''; $count=0; $db_records=-1;

    $dbtab =$this->get_dd_table($dd);
    $server=$this->get_dd_server($dd);

    //dbx_debug ("####SELECT dd=($dd) Tab=($dbtab) Server=($server)  ");


    $query='SELECT ';
    if ($verify_access) $access=$this->check_access('select',$dd,'',$where);
    if ($access) {
      if (!is_array($columns)) {
        if (strpos($columns, ',')) {
          $columns=explode(",", $columns);
        }
      }
      if (is_array($columns)) {
         foreach ($columns as $no => $field) {
            if (dbx_is_integer($no)) {
              $xfield=$field;
            } else {
              $xfield=$no;
            }
            if ($this->is_fld_name($dbtab,$xfield)) {
               $fields.=$xfield.',';
            }
         }
         $fields = substr($fields, 0, -1);
      } else { $fields=$columns; }

      if (!$fields) $fields='*';

      $query.= $fields.' FROM '.$dbtab.' ';
      if ( $where)           $query.='WHERE '   .$where  .' ';
      if ( $groupby)         $query.='GROUP BY '.$groupby.' ';
      if ( $orderby)         $query.='ORDER BY '.$orderby.' '.$asc_desc.' ';
      if ( $offset && $max)  $query.='LIMIT '.$offset.  ', '.$max.' ';
      if (!$offset && $max)  $query.='LIMIT '.$max.' ';
      $query.=';';
      //dbx_debug ("#SELECT Server=($server) QUERY# =($query)");
      //$db_records=$this->rawQuery($server,$query);
      $db_records=$this->select_query($server,$query);
    }
    return $db_records;
  }

  
  public function select1($dd,$where='',$columns='*',$orderby='',$asc_desc='ASC',$groupby='',$max=1,$offset=0,$verify_access=1) {
    $where=$this->check_where($where);
    //dbx_debug("SELECT1 dd=($dd) where=($where)");
    if ($where != 'new') {
      $db_record=$this->select($dd,$where,$columns,$orderby,$asc_desc,$groupby,$max,$offset,$verify_access);
    } else {
      $db_record=$this->empty_record($dd);
    }

    
    if (is_array($db_record)) {
      if (isset($db_record[0])) {
        $db_record=$db_record[0];
      } else { // new empty Record
        $db_record=0; //'not-found';
      }
    }
    //dbx_debug("select1 ($dd) ($where) ",$db_record);
    return $db_record;
  }

  function get_new_record($dd,$field_values='') {
     $empty_rec=0;
     $empty=$this->empty_record($dd);
     if (isset($empty[0])) $empty_rec=$empty[0];
     if (is_array($empty_rec)) {
        foreach ($empty_rec as $field => $value) {
           if (!isset($field_values[$field])) $field_values[$field]=$value; 
        }
     }
     if (isset($field_values['id'])) { 
        if ($field_values['id'] <= 0) unset($field_values['id']);
     } 
     return $field_values;
  }


  function insert($dd,$field_values,$verify_access=1,$verify_fields=1,$verify_values=1,$trace=1) {
    //dbx_debug("#db# INSERT ($tab)",$field_values);
    $access=1; $fields=''; $values=''; $ok=-1;

    $this->_insert_id         =0;
    $this->_validation_error  =0;
    $this->_validation_warning=0;

    $server=$this->get_dd_server($dd);
    $tab   =$this->get_dd_table($dd); 

    if ($trace) {
      $uid  =dbx_get_CurrentUser();
      $now  =date('Y-m-d H:i:s');

      if (!isset($field_values['create_date'])) $field_values['create_date'] =$now;
      if (!isset($field_values['create_uid']))  $field_values['create_uid']  =$uid;
      if (!isset($field_values['update_date'])) $field_values['update_date'] =$now;
      if (!isset($field_values['update_uid']))  $field_values['update_uid']  =$uid;
      if (!isset($field_values['owner']))       $field_values['owner']       =$uid;

    }

   

    $field_values=$this->get_new_record($dd,$field_values);


    $query="INSERT INTO $tab ".'( ';

    if ($verify_access) $access=$this->check_access('insert',$dd,$field_values);
    if ($verify_fields) $field_values=$this->check_fields($dd,$field_values);
    if ($verify_values) $field_values=$this->check_values($dd,$field_values);
    $validation_error=$this->_validation_error;

    //dbx_debug("#db# insert Access=($access) Err=($validation_error)",$field_values);


    if ($validation_error === 0) { // Überprüfe auf keinen Validierungsfehler
      if ($access) { // Überprüfe, ob Zugriff erlaubt ist
          if (is_array($field_values)) { // Verarbeite nur, wenn $field_values ein Array ist
              // Entferne das 'id'-Feld, falls es vorhanden ist und der Wert 0 ist
              if (isset($field_values['id']) && $field_values['id'] == 0) {
                  unset($field_values['id']);
              }
  
              // Initialisiere Variablen für Felder und Werte
              $fields = '';
              $values = '';
  
              foreach ($field_values as $field => $value) {
                  // Konvertiere Array-Werte (falls vorhanden) in ein Format
                  if (is_array($value)) {
                      $value = $this->get_convert_array($field, $value, 'auto');
                  }
  
                  // Baue die Felder- und Werte-Strings auf
                  $fields .= $field . ', ';
                  $values .= "'" . $this->escape($value, $server) . "', ";
              }
  
              // Entferne das letzte Komma und Leerzeichen von Felder und Werte
              $fields = rtrim($fields, ', ');
              $values = rtrim($values, ', ');
  
              // Baue die vollständige SQL-Query
              $query .= $fields . ') VALUES (' . $values . ')';
  
              // Führe die Query aus
              $retval = $this->rawQuery($server, $query);
  
              // Setze den Rückgabewert basierend auf dem Erfolg der Query
              $ok = $retval ? 1 : 0;
          }
      }
  } else {
      $ok = 0; // Validierungsfehler vorhanden
  }
  
    return $ok; // -1=access error; 0=validation error; 1=ok;
    //dbx_debug("###INSERT OK=($ok)");
 }




 function update($dd,$field_values,$where,$verify_access=1,$verify_fields=1,$verify_values=1,$trace=1) {
  //dbx_debug("UPDATE ($tab)");
  $access=1; $work='';  $ok=-1;
  $this->_validation_error=0;
  $this->_validation_warning=0;
  
  $server=$this->get_dd_server($dd);
  $tab   =$this->get_dd_table($dd); 

  if ($trace) {
   $uid  =dbx_get_CurrentUser();
   $now  =date('Y-m-d H:i:s'); 

   if (!isset($field_values['update_date'])) $field_values['update_date'] =$now;
   if (!isset($field_values['update_uid']))  $field_values['update_uid']  =$uid; 
   if (!isset($field_values['owner']))       $field_values['owner']=$uid;
   if (!isset($field_values['owner']))       $field_values['owner']=$uid;
   
  } 
  $query='UPDATE '.$tab.' SET ';
  if ($where) $where=$this->check_where($where);
  if ($verify_access) $access=$this->check_access('update',$dd,$field_values,$where);
  if ($verify_fields) $field_values=$this->check_fields($dd,$field_values);
  if ($verify_values) $field_values=$this->check_values($dd,$field_values);

  if ($this->_validation_error==0) {
    if ($access) {
      if (is_array($field_values)) {
         foreach ($field_values as $field => $value) {
           if (is_array($value)) $value=$this->get_convert_array($field,$value,'auto'); 
           $work .= $field . " = '" . $this->escape($value, $server) . "', ";
         }
         $work  = rtrim($work, ', ');

         $query.= $work .' WHERE '.$where.';';
         $retval=$this->rawQuery($server,$query);
         $ok=1;
      }
    }
  } else { // has _validation_error
    $ok=0;
  }
  return $ok;  // 0= error 1=ok -1=access error
}



function save($dd,$field_values,$where,$verify_access=1,$verify_fields=1,$verify_values=1,$trace=1) {
  $ok=-1; $count=0;
  if ($where)  $where=$this->check_where($where);
  if ($where)  $count=$this->count($dd,$where);
  if ( $count) $ok=$this->update($dd,$field_values,$where,$verify_access,$verify_fields,$verify_values,$trace);
  if (!$count) $ok=$this->insert($dd,$field_values,       $verify_access,$verify_fields,$verify_values,$trace);
  if (!$ok) { 
    dbx_debug("#SAVE# ($ok) dd=($dd) W=($where) Count=($count)",$this->_validation_error_flds);
    dbx_debug("#SAVE# Fields",$field_values);
  }
  return $ok;
}


function delete($dd,$where,$limit=0,$verify_access=1) {
  $ok=-1;
  $server=$this->get_dd_server($dd);
  $tab   =$this->get_dd_table($dd);
  if ($where) $where=$this->check_where($where);
  $query='DELETE FROM '.$tab.' WHERE '.$where;

  //dbx_debug ("#DELETE-Query=($query) server=($server) tab=($tab)"); 

  if (!$verify_access) $access=1;
  if ( $verify_access) $access=$this->check_access('delete',$dd,$limit,$where);

  //dbx_debug("#delete-access=($access)");

  if ($access) {
     if ($limit) $query.= ' limit '.$limit ;
     $query.=';';
     $ok=$this->delete_query($server,$query);
     if ($ok) $ok=1;
  }
  //dbx_debug ("#DELETE ok=($ok)");
  return $ok;
}




  public function count($dd,$where='',$server='') {
    $count = -1;
    if ($where=='new') return 0;
    if (!$server)  $dbtab =$this->get_dd_table($dd);
    if ( $server)  $dbtab =$dd;
    if (!$server)  $server=$this->get_dd_server($dd);
    if ($dbtab && $server) {
        // ##########################################################
        $count = -2;
        $ok=$this->connect_db_server($server);
        if ($ok) {
          $count = 0;
          $query = "SELECT COUNT(*) as count FROM $dbtab";
          if (!empty($where))  $query .= " WHERE $where";
          $smtd=$this->query($server,$query);
          $data=$this->rawQuery($server,$query);
          if (isset($data[0]['count'])) $count=$data[0]['count'];
        }
    }
    //dbx_debug("#COUNT# dd=($dd) Tab=($dbtab) Query=($query)  count=($count) SQL=($query)");
    return $count;
}


public function empty($dd) {
  $ok=0;
  $server=$this->get_dd_server($dd);
  $dbtab =$this->get_dd_table($dd);
  $dbType=$this->get_db_type($server);

  //dbx_debug("#empty dd=($dd) Server=($server) Tab=($dbtab) Type=($dbType)");


  if ($dbtab && $server && $dbType && $ok) {
    switch ($dbType) {
      case 'mysql':
          $sql = "TRUNCATE TABLE $dbtab";
          $ok= $this->rawQuery($server,$sql);
          if ($ok) {
            $sql = "ALTER TABLE $dbtab AUTO_INCREMENT = 0";
            $ok= $this->rawQuery($server,$sql);
          }
          break;
      case 'sqlite':
          //dbx_debug("#truncate Server=($server) Tab=($dbtab)");
          $sql = "DELETE FROM $dbtab";
          $ok  =  $this->rawQuery($server,$sql);
          //dbx_debug("truncate=($ok)");
          break;
      case 'oci':
          // #todo
          break;
      case 'pgsql':
          // #todo
          break;
      case 'sqlsrv':
          // #todo
          break;
      default:
          dbx_add_admin_msg('warning','dd',$dd,"no type def ($dbType)",'check'); 
    }  

  }
  return $ok; 
}


function get_table_exist($dd,$dbtab='') {
    $exist=0;    
    if (!$dbtab) {
      $dbtab =$this->get_dd_table($dd);
      $server=$this->get_dd_server($dd);
    } else {
      $server=$dd;
    }
    if ($server && $dbtab) {
        $sql = "SELECT 1 FROM $dbtab LIMIT 1";
        try {
            $data = $this->rawQuery($server,$sql);
            //dbx_debug ("SQL get_table_exist Server=($server) Tab=($dbtab)  SQL=($sql)",$data);
            if (is_array($data)) $exist=1;

        } catch (PDOException $e) {
            $exist=0;
        }
    }
    //dbx_debug("##EXIST=($dd) Tab=($dbtab) Server=($server) Exist=($exist)" );
    return $exist;
 }

 function get_dd_exist($dd) { 
     $dd_file=dbx_os_path_file(dbx_get_base_dir().'dbx/modules/dbx/dd/'.$dd.'.dd.php');
     $exist=file_exists($dd_file);
     return $exist;
 }


 public function get_db_tables($server,$not='sqlite_sequence') {
  $tables =array();
  $ok=$this->connect_db_server($server);
  if ($ok) {
    $dbType=$this->get_db_type($server);

    //dbx_debug("SHOW Tables of ($server) Type=($dbType)"); 

    switch ($dbType) {
        case 'mysql':
            $sql = "SHOW TABLES";
            $tableRows = $this->rawQuery($server,$sql);
            break;
        case 'sqlite':
            $sql = "SELECT name FROM sqlite_master WHERE type='table'";
            $tableRows = $this->rawQuery($server,$sql);
            break;
        case 'oci':
            $sql = "SELECT table_name FROM user_tables";
            $tableRows = $this->rawQuery($server,$sql);
            break;
        case 'pgsql':
            $sql = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'";
            $tableRows = $this->rawQuery($server,$sql);
            break;
        case 'sqlsrv':
            $sql = "SELECT table_name FROM information_schema.tables";
            $tableRows = $this->rawQuery($server,$sql);
            break;
        default:
            throw new Exception("Unsupported database Server=($server) type: ($dbType)");
    }

    foreach ($tableRows as $tableRow) {
        $tableName = reset($tableRow);
        if ($tableName != $not) {
          $count = $this->count($tableName,'',$server);
  
          $tables[] = array(
              'server' => $server,
              'name'   => $tableName,
              'count'  => $count
          );
        } 
    }
  }
  //dbx_debug("ShOW TABELS of ($server",$tables);
  return $tables;
}


public function get_dd_table_def($dd) {
  $table=0;
  $ok=$this->load_dd($dd) ;
  if ($ok) $table=$_SESSION['dbx']['cache']['dd'][$dd]['table'];
  return $table;
}

public function get_dd_fields($dd,$label=0) {
  $fields=0;
  $ok=$this->load_dd($dd);
  if ($ok) $fields=$_SESSION['dbx']['cache']['dd'][$dd]['fields'];
  if ($label && $ok) {
    $xfields=array();
    foreach ($fields as $no => $field) { 
       $xname =$field['name'];
       $xlabel=$field['name'];
       if ($label == 1) $xlabel=$field['label'];
       $xfields[$xname]=$xlabel;
    } 
    $fields=$xfields;
  }
  return $fields;
}

public function get_rpt_fields($dd,$flds,$label=1) {
  $fields=$this->get_dd_fields($dd,$label);
  if ($flds != '*') {
    $cols=array();
    $flds=explode(",", $flds);
    if (is_array($flds)) {
        foreach ($flds as $no => $field) {
           $field=trim($field);
           $cols[$field]=$field;
        }
        $flds=$cols;
    } 
  } else {
    $flds=$fields;
  }
  return $flds;
}




  function get_convert_array($field,$array,$convert='serial') {
      $value='';
      if ($convert=='auto') {
        // todo list or serial from $fields[$field]
        if ($convert=='auto') $convert='serial'; // default
      }


      if (!is_array($array)) $value=$array;
      if ( is_array($array)) {
        foreach ($array as $field => $val) {
           if ($field==0) {
             if (!is_array($val)) $convert='list';
           }
           break;
        }
        if ($convert=='serial') $value=@serialize($array);
        if ($convert=='list')   {
           foreach ($array as $field => $val) {
             $value.=$val.',';
           }
           $value  = substr($value, 0, -1);
        }
      }
      return $value;
  }


/*
  public function get_dd_fields($dd) {  // a
      $fields=0;
      if (!isset($_SESSION['dbx']['cache']['dd'][$dd]['fields'])) $ok=$this->load_dd($dd);
      if ( isset($_SESSION['dbx']['cache']['dd'][$dd]['fields'])) $fields= $_SESSION['dbx']['cache']['dd'][$dd]['fields'];
      return $fields;
  }
*/

  function check_access($mode,$dd,$field_values,$where='') {
    //dbx_debug("check-acces Mode=($mode) Tab=($dd) where=($where)");  #todo
    if ($mode=='select') {
       
    }
    // #todo 

    return 1;
  }




  function check_values($dd, $field_values,$verify_values=1) {
     $db_field_values=array();
     $this->_validation_error       =0;
     $this->_validation_warning     =0;
     $this->_validation_error_flds  =array();
     $this->_validation_warning_flds=array();
     $validate_rules=$this->_validatior_rules;  // check value type 
     $validate_type =$this->_validatior_type;   // check value rules
     $validate_error=$this->_validatior_error;  // 1= error 0=warning
     $validate_mode =$this->_validatior_mode;   // 'claen' 'unset' oder nichts
 
     $fields=$this->get_dd_fields($dd);

     foreach ($fields as $no => $field) {
         $name  =$field['name'];
         $length=$field['length'];
         $type  =$field['type'];
         $rules =$field['rules'];

         if (isset($field_values[$name])) {
            $ok=1;
            $value=$field_values[$name];
            if ($validate_rules && $rules && $value) {
               $ok=$this->oValidator->validate($value,$rules,$name);
            }
            if (is_array($value)) $value=$this->get_convert_array($name,$value,'auto');
            if ($ok && $validate_type && $value) {
              $rules='';
              if ($type && $length > 0) $rules=$type.'|max='.$length; 
              $ok=$this->oValidator->validate($value,$rules,$name);
            }
            
            if (!$ok) {
              $err['name']  =$name;
              $err['rules'] =$rules;
              $err['value'] =$value;
              if ($validate_error) {
                $this->_validation_error_flds[]=$err;
                $this->_validation_error++;                
              } else {
                $this->_validation_warning_flds[]=$err;
                $this->_validation_warning++;   
              }
              if ($validate_mode=='clean') {
                $rules=$field['rules'];
                if (strpos($rules, 'array') === true) {
                  $rules=$type; // arrays sind hier schon in string umgewandelt, siehe get_convert_array()
                } else {
                  if ($rules == '*') $rules='';
                  if ($rules  && $rules != $type) {
                     $rules.='|'.$type;
                  } else {
                     $rules=$type;
                  }   
                } 
                $value=$this->oValidator->clean($value,$rules,$length,$name);
              }
              if ($validate_mode=='unset') {
                 $name=false; // unset field
              }
            }  

            if ($name) $db_field_values[$name]=$value;
         }
     }
     return $db_field_values;
  }

  function check_fields($dd, $field_values) {
    $db_fields = $this->get_dd_fields($dd);

    // Erzeuge ein assoziatives Array mit den Feldnamen als Schlüsseln
    $valid_names = array_column($db_fields, 'type', 'name');

    // Filtere $field_values basierend auf vorhandenen Schlüsseln in $valid_names
    return array_filter($field_values, function ($name) use ($valid_names) {
        return isset($valid_names[$name]);
    }, ARRAY_FILTER_USE_KEY);
}


function empty_record($dd) {
  $dd_fields = $this->get_dd_fields($dd);
  $record = [];

  foreach ($dd_fields as $field) {
      $name  = $field['name'];
      $value = $field['default'] ?? ''; // Nutze 'default', falls vorhanden
      
      // Überschreibe Standardwerte je nach Typ (optional)
      //if (in_array($field['type'], ['int', 'longint'])) {
      //    $value = $value ?? 0; // Falls kein Standardwert, nutze 0
      //}

      $record[$name] = $value;
  }

  return [$record];
}



  // main functions


  // check is valid field name
  private function is_fld_name($tab,$xfield) {
     $retval=0;
     $db_fields=$this->get_dd_fields($tab);
     if (is_array($db_fields)) {
      foreach ($db_fields as $no => $field) {
        if ($field['name'] == $xfield) {
            $retval=1;
            break;
        }
      }
     }
     // dbx_debug("##Field=($xfield) ret=($retval)",$db_fields);
     return $retval ;
  }





  function check_where($where) {
     if (!$where)       $where='new';
     if (dbx_is_integer($where)) $where="id = $where ";
     return $where;
  }

  public function escape($string,$server) {
    $ok=1;
    if (!isset($this->db[$server])) $ok=$this->connect_db_server($server);
    if ( $ok) $string= substr($this->db[$server]->quote($string),1, -1);
    if (!$ok) $string= str_replace("'", "''", $string);
    return $string;
  }


}
