<?php
include_once 'dbxDB.class.php';

class dbxDD extends dbxDB {



    public function get_db_fields($server,$tableName) {
        $db_fields=0;
        $exist = $this->get_table_exist($server,$tableName); 
        if ($exist) {
           $dbType=$this->get_db_type($server);
            
     
           switch ($dbType) {
               case 'mysql':
                   $sql = "SHOW COLUMNS FROM $tableName";
                   break;
               case 'sqlite':
                   $sql = "PRAGMA table_info($tableName)";
                   break;
               case 'oci':
                   $sql = "SELECT column_name, data_type, data_length
                           FROM all_tab_columns
                           WHERE table_name = :tableName";
                   break;
               case 'sqlsrv':
                   $sql = "SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
                           FROM INFORMATION_SCHEMA.COLUMNS
                           WHERE TABLE_NAME = :tableName";
                   break;
               default:
                   throw new Exception("Unsupported database type: $dbType");
           }
     
         
           $fields=$this->rawQuery($server,$sql);
           //dbx_debug("fields of ($server $tableName)",$fields);
     
           $db_fields = [];
           foreach ($fields as $dbfield) {
             $name=''    ; $type=''    ; $index=''      ; $length='' ; $default=''; $label=''; $rules=''; 
      
             //dbx_debug("DB_FIELD of ($tableName) Type=($dbType) ",$dbfield); 
             switch ($dbType) {
               case 'mysql':
                 $name   =$dbfield['Field'];
                 $type   =$this->get_field_type($dbfield['Type']);
                 $length =$this->get_field_length($dbfield['Type']);
                 $index  =$this->get_field_index($dbfield['Key']);
                 $default=$dbfield['Default'];
                 $null   =$this->get_field_null($dbfield['Null']);
                 $label  =$name;
                 $rules  =$type;
                 /*
                 [Field] => id
                 [Type] => int(11)
                 [Null] => NO
                 [Key] => PRI
                 [Default] => 
                 [Extra] => auto_increment
                 */
                   break;
     
               case 'sqlite':
                 $name   =$dbfield['name'];
                 $type   =$dbfield['type'];
                 $length =$this->get_field_length($type);
                 $index  =$this->get_field_index($dbfield['pk']); 
                 $default=$dbfield['dflt_value'];
                 $null   =$this->get_field_null($dbfield['notnull']);
                 $label  =$name;
                 $rules  =$type;            
                 /*
                 [cid] => 0
                 [name] => id
                 [type] => INTEGER
                 [notnull] => 0
                 [dflt_value] => 
                 [pk] => 1
                 */
                   break;
                   
               case 'oci':
                   
                   break;
               case 'sqlsrv':
     
                   break;
               default:
                   //throw new Exception("Unsupported database type: $dbType");
             }      
           
     
             $field['name']       =$name;
             $field['type']       =$type;
             $field['index']      =$index;
             $field['length']     =$length;
             $field['null']       =$null;
             $field['default']    =$default;
             $field['label']      =$label;
             $field['rules']      =$rules;
             /*
             $field['tooltip']='';
             $field['errormsg']='';
             $field['placeholder']='';
             $field['convert']='';
             $field['protect']='0';
             $field['mask']='';
             $field['data']='';
             */
             $db_fields[] = $field;
           }
     
     
        }
        return $db_fields;
     }
     

     private function get_field_type($type) {
        $pos = strpos($type, '(');
        if ($pos) $type=substr($type,0, $pos-1);
        return $type;
      }
      
      private function get_field_length($type) {
        $length=0;
        $startPos = strpos($type, '(');
        $endPos   = strpos($type, ')', $startPos);
        if ($startPos !== false && $endPos !== false) {
            $length = ($endPos - $startPos - 1); // Calculate the length between '(' and ')'
            $length =substr($type, $startPos + 1, $length);
        }
        return $length;
      }
      
      private function get_field_index($index) {
        if ($index==1) $index='PRI';
        return $index;
      }
      
      private function get_field_null($null) {
        $is_null=$null; 
        if ($null=='NO')   $is_null=0;
        if ($null=='YES')  $is_null=1;
        return $is_null;
      }
      
      
     
     private function isConflictType($db_type,$dd_type) {
         $conflict=0;
         if ($db_type != $dd_type) {
            if (($dd_type=='char'     || $dd_type=='varchar') && $db_type == 'INTEGER' ) $conflict=1; 
            if (($dd_type=='datetime' || $dd_type=='date')    && $db_type == 'INTEGER' ) $conflict=1;
         }
         //dbx_debug ("#Conflict=($conflict) dd=($dd_type) db=($db_type)");
         return $conflict;
     }
     
     public function get_dd_sync($dd) {
       $sync=1; $rec=0;
       $ok=$this->load_dd($dd);
       if ($ok) {
         $server   = $this->get_dd_server($dd);
         $autosync = $this->get_dd_autosync($dd);
         $table    = $this->get_dd_table($dd);  
         $exist    = $this->get_table_exist($dd); 

          

         if (!$exist) $sync=-1;
          
         dbx_debug("get_dd_sync($dd) Server=($server) Table=($table) Exist=($exist) Sync=($sync)");
     
         if ($server && $table && $exist ) {
           $dd_fields=$this->get_dd_fields($dd);
           $db_fields=$this->get_db_fields($server,$table);
     
           $count_dd=count($dd_fields);
           $count_db=count($db_fields);
           if (!$count_dd)             $sync=-2;
           if (!$count_db)             $sync=-3; 
           if ($count_dd != $count_db) $sync=-4;
           
     
           
           if ($sync) {
             foreach ($dd_fields as $no => $dd_field) {
               $db_name='undef'; $db_type='undef'; $db_length=-1; 
               $dd_name  =$dd_field['name'];
               $dd_type  =$dd_field['type'];
               $dd_length=$dd_field['length'];
               

               if (isset($db_fields[$rec])) {
                 $db_name  =$db_fields[$rec]['name'];
                 $db_type  =$db_fields[$rec]['type'];
                 $db_length=$db_fields[$rec]['length'];

                 if ($dd_name !== $db_name) $sync=-5;

     
                 if ($db_type=='int') $db_type='INTEGER'; 
                 if ($db_length > 0)  {
                    if ($db_length != $dd_length) $sync=-6; 
                 }  
                 if ($db_type != 'TEXT') {
                    if ($this->isConflictType($db_type,$dd_type)) $sync=-7;
                 }
     
               } 
               //dbx_debug("DD Field=($dd_name)($dd_type)($dd_length) DB Field=($db_name)($db_type)($db_length)  Sync=($sync)");  
               $rec++;
               if ($sync <= 0) break;
             }
           }
     
         }   
       }

       return $sync;
      }
     
      public Function save_dd( $dd, $table, $fields ) {
        $oDD = dbx_get_sys_object('dbxDD');

        $ok=$this->write_dd($dd, $table, $fields );
        if (isset($_SESSION['dbx']['cache']['dd'][$dd])) {
          // important clear dd cache
          unset($_SESSION['dbx']['cache']['dd'][$dd]);   
        }  
        //dbx_debug("#write_dd DD=($dd)  ok=($ok)",$table, $fields);
        return $ok;
      }    


      public function get_dd_tables($path='') {
        $dd_records=0; 
        if (!$path) $path=dbx_os_path_file(dbx_get_base_dir().'dbx/modules/dbx/dd/');

        $files=scandir($path);
        $dd_records=array();
        //dbx_debug("Sacan DD",$files);
    
        foreach ($files as $no => $dd) {
          //dbx_debug("FILES DD=($dd)");
          if (dbx_strpos($dd,'.dd.php')) {     
            $dd=str_replace('.dd.php','',$dd);
            if ($dd && $dd != 'new') {
              //dbx_debug("Load dd($dd)");
              $ok=$this->load_dd($dd);
              $server= $this->get_dd_server($dd);
              $table = $this->get_dd_table($dd);  
              $exist = $this->get_table_exist($dd); 
              $count =-1;  $sync='undef';
              if ($exist) { 
                $count=$this->count($dd);
                $sync =$this->get_dd_autosync($dd);
              }
              $tab['datadic']=$dd;
              $tab['server'] =$server; 
              $tab['table']  =$table;
              $tab['exist']  =$exist;
              $tab['count']  =$count;
              $tab['sync']   =$sync;
    
              $dd_records[]=$tab;  
            }
          }
        } 
         
        //dbx_debug("DD Records=",$dd_records);
        return $dd_records;
    
    }
    


 
    function get_dd_field_pos($name,$fields) {
        $pos=-1; 
        if (is_array($fields)) {
         foreach ($fields as $no => $field) {
             if ($field['name'] == $name) return $no;
         }
        }
        return $pos;
     }
   
   
     function write_dd($dd, $table, $fields) { 
        $ok=1;
        $dd_file=dbx_os_path_file(dbx_get_base_dir().'dbx/modules/dbx/dd/'.$dd.'.dd.php');
        $def = '<?php'."\n";
        foreach ( $table as $fld => $value ) {
           $def .= '$table[\''.$fld.'\']=\''.$value.'\''.";\n";
        }
        $def .= "\n";
        if (is_array($fields)) {
          foreach ( $fields as $no => $field ) {
            foreach ( $field as $fld => $value ) {
              $def .= '$field[\''.$fld.'\']=\''.$value.'\''.";\n";
            }
            $def .= '$fields[]=$field;'."\n\n";
          }
        }
        $def .="\n";
        $dd_file=dbx_os_path_file($dd_file);
        file_put_contents( $dd_file, $def );
        return $ok;
      }
   
   
   
     function update_dd($dd) { 
       
       $dd_file=dbx_os_path_file(dbx_get_base_dir().'dbx/modules/dbx/dd/'.$dd.'.dd.php');
       $dd_old =dbx_os_path_file(dbx_get_base_dir().'dbx/modules/dbx/dd/'.$dd.'.dd.old.php');

       $struc_diff=0;
   
       if (file_exists($dd_file)) {
          $ok=$this->load_dd($dd);
          $old_table =$_SESSION['dbx']['cache']['dd'][$dd]['table'];
          $old_fields=$_SESSION['dbx']['cache']['dd'][$dd]['fields'];
          copy($dd_file,$dd_old);
          //unlink($dd_file);
          unset($_SESSION['dbx']['cache']['dd'][$dd]);
          $this->create_dd($dd);
          $ok=$this->load_dd($dd);
          $new_table =$_SESSION['dbx']['cache']['dd'][$dd]['table'];
          $new_fields=$_SESSION['dbx']['cache']['dd'][$dd]['fields'];       
          foreach ($new_table as $key => $val) {
             if (isset($old_table[$key])) $new_table[$key]=$old_table[$key]; 
          }
          
          foreach ($new_fields as $no => $field) {
              $name=$field['name'];
              $old_no=$this->get_dd_field_pos($name,$old_fields);
              //if ($tab == 'dbx_adminmsg') dbx_debug("ALT-($tab) ($name) Pos=($old_no)");
              if ($old_no != $no) $struc_diff=1;
              
              if ($old_no >= 0) {              
                foreach ($field as $key => $val) {
                   if (isset($old_fields[$old_no][$key])) $new_fields[$no][$key]=$old_fields[$old_no][$key]; 
                }
              }
          }
          $ok=$this->write_dd($dd,$new_table,$new_fields);
          if ($struc_diff) dbx_add_admin_msg('warning','dd',$dd,'structure_change','check'); 
       }
       //if ($tab == 'dbx_adminmsg') dbx_debug("ALT-($tab)",$old_fields);
       //if ($tab == 'dbx_adminmsg') dbx_debug("NEU-($tab) Diff=($struc_diff)",$new_fields);
       
       
       return $struc_diff;
     }
   


    public function delete_dd_fld($dd,$fld,$delete_db_fld=1) {   //#todo check it
      $ok=0;
      $new_fields=array();

      $dd_table =$this->get_dd_table($dd,1);
      $dd_fields=$this->get_dd_fields($dd);
      
      //dbx_debug ("Delete Field of DD($dd) FLD=($fld)",$dd_fields); 
      foreach ($dd_fields as $no => $field) {
          if ($field['name'] == $fld) $ok=1;
          if ($field['name'] != $fld) $new_fields[]=$field;
      }  
      if ($ok) {
        $this->save_dd($dd,$dd_table,$new_fields);    

        if ($delete_db_fld) {

        }

      }

      return $ok;
  
    }

    public function sync_dd_to_db($dd) {
      $oForm=dbx_get_SysVar('dbxForm');
      $server   = $this->get_dd_server($dd);
      $dbtab    = $this->get_dd_table($dd);
      $dbtype   = $this->get_db_type($server);     
      $dd_fields= $this->get_dd_fields($dd);
      $db_fields= $this->get_db_fields($server,$dbtab);
      $map=array(); // mapping with old name (rename)


      //1. Alte Datensäte speichern
      //2. DB mit def from dd neu erstellen
      //3. Alte Datensätze einlesen  ? mapping für old_fld ?? // map_record()
      //4. Mapping entfernen

      $oProcess=dbx_get_sys_object('dbxProcess');
      $oProcess->init('restruct_dd');       
      $oProcess->set_property('dd_remap',$map);                                         
      $oProcess->add("[modul=dbxAdmin]dbx_action=datadic&dbx_work=export_csv&dd=$dd&dbx_process=restruct_dd[/modul]");     // 1. write csv
      $oProcess->add("[modul=dbxAdmin]dbx_action=datadic&dbx_work=restruct_tab&dd=$dd&dbx_process=restruct_dd[/modul]");   // 2. update db
      $oProcess->add("[modul=dbxAdmin]dbx_action=datadic&dbx_work=import_csv&dd=$dd&dbx_process=restruct_dd[/modul]");     // 3. read csv
      //$oProcess->add("[modul=dbxAdmin]dbx_action=datadic&dbx_work=list[/modul]");
      //$content.=$oForm->get_js_close_modal('#dbxmodal1',1200);
      //$content.=$oForm->get_js_autosubmit('report-dd-fields',1500); 
      $content=$oProcess->run(); // first stepp of process
      $oForm->fast_response($content,1); // with interpreter;  

      
      #todo

    }

    public function map_record($record,$map) {
      if (is_array($record) && is_array($map)) {
        foreach ($map as $old => $new) {
           if (isset($record[$old])) {
            $value=$record[$old];
            unset($record[$old]);
            $record[$new]=$value;
           }
        }   
      }
      return $record;
    }


    private function get_fields($server,$dbtab) {
        // bei check sync schon vorhanen ???? ! 
        #todo
    }
     
  
    public function drop_db_tab($server,$tab) {
      $type =$this->get_db_type($server);
      $exist=$this->get_table_exist($server,$tab);
    
      switch ($type) {
        case 'mysql':  
        case 'sqlite':
            if ($exist) {
                $sql="DROP TABLE $tab; ";
                $ok=$this->rawQuery($server,$sql);
            }      
            
        break; 

        case 'oci':       
        break;
        
        case 'sqlsrv':
        break;
        
        default:

      }
    }  

   
    private function get_sql_fields($dd) {
      $sql=''; $idx='';
      $this->load_dd($dd); 
      $server= $this->get_dd_server($dd);
      $dbtab = $this->get_dd_table($dd);
      $fields= $this->get_dd_fields($dd);   
      $dbtype= $this->get_db_type($server); 

      foreach ($fields as $no => $field) {
        $extra=''; $default=''; $index='';

        $fld_name   =$field['name'];
        $fld_type   =$field['type'];
        $fld_index  =$field['index'];
        $fld_length =$field['length'];
        $fld_default=$field['default'];

        $fld_type =strtoupper($fld_type); 
        $fld_index=strtoupper($fld_index); 

        //$field['extra'];

        if ($fld_length > 0) {
          $fld_type_length=$fld_type.'('.$fld_length.')'; 
        } else {
          $fld_type_length=$fld_type;
        }


        if ($fld_index) {
          if ($fld_index == 'PRI') { 
            if ($fld_type == 'INT') $extra='AUTO_INCREMENT';
          }  
          if ($fld_index == 'PRI') $index='PRIMARY KEY'; 
          if ($fld_index == 'UNI') $index='UNIQUE'; 
        } 

        
        if ($fld_default > '') {
          $default='DEFAULT ';
          if (dbx_is_integer($fld_default) || $fld_default == 'CURRENT_TIMESTAMP') {
            $default.=$fld_default;
          } else {
            $default.="'$fld_default'";
          }   
        }



        switch ($dbtype) {
          case 'mysql':  
            $sql.=$fld_name.' '.$fld_type_length.' '.$extra.' '.$index.' '.$default.",\n";
          break; 

          case 'sqlite':
            if ($fld_type == 'INT')       $fld_type='INTEGER';
            if ($fld_type == 'VARCHAR')   $fld_type='TEXT';
            if ($fld_type == 'CHAR')      $fld_type='TEXT';
            if ($fld_type == 'DATE')      $fld_type='TEXT';
            if ($fld_type == 'DATETIME')  $fld_type='TEXT';

            $sql.='"'.$fld_name.'" '.$fld_type.' '.$default.",\n";
  
          break;

          case 'oci':       
          break;
          
          case 'sqlsrv':
          break;
          
          default:

        }
      
      }

      switch ($dbtype) { 
        case 'sylite':

        break;  

        case 'mysql':   
          $lastCommaPosition = strrpos($sql, ',');
          if ($lastCommaPosition !== false) $sql = substr($sql, 0, $lastCommaPosition);
        break;

        default:

      }

      return $sql."\n";
    }


    private function get_sql_index($dd) {
      $idx='';
      $this->load_dd($dd); 
      $server= $this->get_dd_server($dd);
      $dbtab = $this->get_dd_table($dd);
      $fields= $this->get_dd_fields($dd);   
      $dbtype= $this->get_db_type($server); 

      foreach ($fields as $no => $field) {
        $extra=''; $default=''; $index='';

        $fld_name   =$field['name'];
        $fld_index  =$field['index'];

        $fld_index=strtoupper($fld_index); 
        $idx.='DROP INDEX IF EXISTS idx_'.$fld_name.';';
        if ($fld_index) {
          if ($fld_index == 'UNI') {
            $idx.='CREATE UNIQUE INDEX idx_'.$fld_name.' ON '.$dbtab.' ('.$fld_name.')'.";\n";
          }
          if ($fld_index != 'PRI' && $fld_index != 'UNI') {
            $idx.='CREATE INDEX idx_'.$fld_name.' ON '.$dbtab.' ('.$fld_name.')'.";\n";
          }
        } 
      }
      //dbx_debug("IDX=",$idx);
      return $idx;
    }

    private function get_sql_primary_index($dd) {
      $idx='';
      $this->load_dd($dd); 
      $server= $this->get_dd_server($dd);
      $dbtab = $this->get_dd_table($dd);
      $fields= $this->get_dd_fields($dd);   
      $dbtype= $this->get_db_type($server); 

      foreach ($fields as $no => $field) {
        $extra=''; $default=''; $index='';

        $fld_name   =$field['name'];
        $fld_index  =$field['index'];

        $fld_index=strtoupper($fld_index); 

        if ($fld_index == 'PRI') {
          $idx='PRIMARY KEY("' .$fld_name .'")';
          return $idx;
        }      
      }
      return $idx;
    }



    public function create_db_tab($dd) {
      $this->load_dd($dd); 
      $server= $this->get_dd_server($dd);
      $table = $this->get_dd_table($dd);
      $type  = $this->get_db_type($server);

      $exist=$this->get_table_exist($server,$table);
   
      dbx_debug("#Create-DB-TAB=($dd)  Server=($server) Type=($type) Tab=($table) exist=($exist)");

      switch ($type) {
        case 'mysql':  
           // #todo 

        case 'sqlite':  
          $sql='';
          if ($exist) {
            $sql="DROP TABLE $table; \n";
            $ok=$this->exec_query($server,$sql);  //rawQuery
            dbx_debug("remove tab from ($server) Tab=($table) Ok=($ok)");
          }    
            
          $sql ='CREATE TABLE'.' "'.$table.'"'." (\n";
          $sql.= $this->get_sql_fields($dd);
          
          $sql.= $this->get_sql_primary_index($dd);
          $sql.=")\r\n";


          dbx_debug("---Q------------------------------------------"); 
          dbx_debug($sql); 
          dbx_debug("---Q------------------------------------------"); 

          $ok=$this->exec_query($server,$sql);

          $sql=$this->get_sql_index($dd);
          if ($sql) $oki=@$this->exec_query($server,$sql); 

            
        break; 

        case 'oci':       
        break;
        
        case 'sqlsrv':
        break;
        
        default:

      }
      if ($ok) $ok=$this->get_table_exist($server,$table);
      dbx_debug("table exist ($ok) Server=($server) Tab=($table)");
      return $ok;
    }  


    function get_fld_types() {
      $types = [
          // Zeichenfolgen-Datentypen
          'char',
          'varchar',
          'text',
          'mediumtext',
          'longtext',
          
          // Numerische Datentypen
          'tinyint',
          'smallint',
          'mediumint',
          'int',
          'bigint',
          'decimal',
          'float',
          'double',
  
          // Datums- und Zeit-Datentypen
          'date',
          'datetime',
          'timestamp',
          'time',
      ];
      return array_combine($types, $types);
  }
  



     function create_dd($tab,$server='default') {
       return;
       if ($server=='default') $server='dbXsystem'; // #todo  get it from config 
       $dbType=$this->get_db_type($server);
       //if ($type != 'mysql') return; 
   
       $dd_file=dbx_os_path_file(dbx_get_base_dir().'dbx/modules/dbx/dd/'.$tab.'.dd.php');
       if (!file_exists($dd_file))  dbx_add_admin_msg('warning','dd',$tab,'create dd','check');
       
       $def='<?php'."\n";
       $def.='$table[\'table\']=\''.$tab.'\';'."\n";
       $def.='$table[\'autosync\']=\'0\';'."\n";
       $def.='$table[\'server\']=\'default\';'."\n";
       $def.='$table[\'version\']=\'1\';'."\n";
       $def.='$table[\'cache\']=\'0\';'."\n";
       $def.='$table[\'trash\']=\'1\';'."\n";
       $def.='$table[\'trace\']=\'0\';'."\n";
   
       $def.='$table[\'read\']=\'admin\';'."\n";
       $def.='$table[\'create\']=\'admin\';'."\n";
       $def.='$table[\'update\']=\'admin\';'."\n";
       $def.='$table[\'delete\']=\'admin\';'."\n";
   
       $def.='$table[\'read_owner\']=\'admin,owner\';'."\n";
       $def.='$table[\'create_owner\']=\'admin,owner\';'."\n";
       $def.='$table[\'update_owner\']=\'admin,owner\';'."\n";
       $def.='$table[\'delete_owner\']=\'admin,owner\';'."\n";
       $def.="\n";
   
       $db_fields=$this->get_table_fields($tab);
   
       //dbx_debug("### TABLE FIELDS ($tab) ",$db_fields);
   
   
       foreach ($db_fields as $no => $field) {
         $length='-1';
         $name   =$field['Field'];
         $type   =$field['Type'];
         $default=$field['Default'];
         $index  =$field['Key'];
   
         $posA = strpos($type,'(');
         $posE = strpos($type,')');
   
         if ($posA && $posE) {
            $length=substr($type, $posA+1,($posE-$posA-1));
            $type  =substr($type, 0,$posA);
         }
         $convert='';  $tooltip='' ; $placeholder=''; $errormsg='';
         $rules=$type; $label=$name;
         $protect=0;   $mask=''   ; $data='';
         $def.='$field[\'name\']=\''       .$name.'\';'."\n";
         $def.='$field[\'type\']=\''       .$type.'\';'."\n";
         $def.='$field[\'index\']=\''      .$index.'\';'."\n";
         $def.='$field[\'length\']=\''     .$length.'\';'."\n";
         $def.='$field[\'default\']=\''    .$default.'\';'."\n";
         $def.='$field[\'label\']=\''      .$label.'\';'."\n";
         $def.='$field[\'rules\']=\''      .$rules.'\';'."\n";
         $def.='$field[\'tooltip\']=\''    .$tooltip.'\';'."\n";
         $def.='$field[\'help\']=\''       .$tooltip.'\';'."\n";
         $def.='$field[\'prompt\']=\''     .$tooltip.'\';'."\n";
         $def.='$field[\'errormsg\']=\''   .$errormsg.'\';'."\n";
         $def.='$field[\'placeholder\']=\''.$placeholder.'\';'."\n";
         $def.='$field[\'convert\']=\''    .$convert.'\';'."\n";
         $def.='$field[\'protect\']=\''    .$protect.'\';'."\n";
         $def.='$field[\'mask\']=\''       .$mask.'\';'."\n";
         $def.='$field[\'data\']=\''       .$data.'\';'."\n";
   
         $def.='$fields[]=$field;'."\n\n";
   
       }
       $def.="\n";
       file_put_contents($dd_file, $def);
   
       
     }


}
