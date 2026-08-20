<?php

/**
 * Liest große CSV-Dateien schrittweise und speichert den Importfortschritt.
 */
class dbxCSVreader extends dbxObj {

  /** Schreibt die optionale zeilenweise Importdiagnose. */
  private function write_import_debug(string $line): void {
     $file = dbx()->os_path(dbx()->get_file_dir() . 'dbxDebug2.txt');
     file_put_contents($file, $line, FILE_APPEND);
  }

   
   public function clear() {
      $section=$this->_section;
      if ($section) { 
         $this->del_property('*');
      } else {
         $this->_properties=array();    
      }
    } 


  public function init($process) {
     $this->_section=$process;
     $status=$this->get_property('status','init');
     //dbx_debug("#get status=($status) Process=($process)") ;

     if ($status=='end') $status='init';
     if ($status=='init') {
      $this->clear();
      $this->set_property('status','run');
      $this->set_property('done',0);
      $this->set_property('percent',0);
      $this->set_property('errors',0);  
  
     } 
     //dbx_debug("######init=($status) process=($process)");
     return $status;
  }


  private function make_fildset($line,$remap=0) {
     If(!$remap) $remap=$this->get_property('remap',-1);
     $sep  = $this->get_property('separator',';'); 
     $fieldset_clean=array();
     $fieldset = explode($sep, $line);
     foreach ($fieldset as $no => $fld) {
        if (strpos($fld,'(')) $fld=substr($fld,0,strpos($fld,'(',0));
        if (strpos($fld,'/')) $fld=substr($fld,0,strpos($fld,'/',0));
        if (strpos($fld,' ')) $fld=substr($fld,0,strpos($fld,' ',0));
        if (is_array($remap)) {
           foreach ($remap as $old => $new) {
              If ($fld==$old) $fld=$new;
           }    
        } 
        if ($fld) $fieldset_clean[]=$fld;
     }

     //dbx_debug ("#make fieldset FIELDSET clean mit remap ($line)",$fieldset_clean);
     $this->set_property('fieldset',$fieldset_clean);

  }

  private function get_fildset() {
     $fieldset=array();
     $fieldset=$this->get_property('fieldset',$fieldset);
     return $fieldset;
  }

  private function sql_literal($value, $o_db, $server) {
     if ($value === null || strtoupper((string)$value) === 'NULL') {
        return 'NULL';
     }

     if (is_int($value) || (is_string($value) && filter_var($value, FILTER_VALIDATE_INT) !== false)) {
        return (string)(int)$value;
     }

     if ($o_db) {
        return "'" . $o_db->escape((string)$value, $server) . "'";
     }

     return "'" . str_replace("'", "''", (string)$value) . "'";
  }

  private function make_where($where,$record,$o_db=null,$dd='') {
   if (!$o_db && $dd) {
      $o_db = dbx()->get_system_obj('dbxDB');
   }
   $server = ($o_db && $dd) ? $o_db->get_dd_server($dd) : 'default';

   foreach ($record as $name => $value) {
        $xkey='{'.$name.'}';
        if (strpos((string)$where, "'" . $xkey . "'") !== false) {
            $where=str_replace("'" . $xkey . "'", $this->sql_literal($value, $o_db, $server), $where);
        }
        if (strpos((string)$where, '"' . $xkey . '"') !== false) {
            $where=str_replace('"' . $xkey . '"', $this->sql_literal($value, $o_db, $server), $where);
        }
        $where=str_replace($xkey, $this->sql_literal($value, $o_db, $server), $where);
        if (!(strpos($where, '}'))) break;
     }
     return $where;
  }


  private function encode_f_l_e_x($str) {
     $str=str_replace("„","ä",$str);
     $str=str_replace("","ü",$str);
     $str=str_replace("”","ö",$str);
     $str=str_replace("á","ß",$str);
     $str=str_replace("¯","Ä",$str);
     $str=str_replace("�","Ü",$str);
     $str=str_replace('³','ü',$str); 
     $str=str_replace('õ','ö',$str);
     return $str;
  }


  private function encode_to_utf8($string) {
     //$string = $this->encode_f_l_e_x($string);
     //$string = @iconv( "cp437", "ISO-8859-15",$string);

     $string=mb_convert_encoding($string, "UTF-8", mb_detect_encoding($string, "Windows-1252, ISO-8859-15, ISO-8859-1, ISO-8859-2, CP850, ISO-8859-15, UTF-8", true));

     //$string = urlencode(mb_convert_encoding ($string, "UTF-8", 'Windows-1252'));

     $string = $this->encode_f_l_e_x($string);
     return $string;
   }


  public function import() {

    $percent=0;
 

    //$properties=$this->_properties;

    $file=$this->get_property('path_file','nofile');
    $error_file= $file.'.err'; // tmp file for error
    if (!file_exists($file)) return 'end'; 

    $inf = new SplFileInfo($file);
    $ext = ($inf->getExtension());
    $max = ini_get('max_execution_time');
    $this->set_property('max_time',$max);
    $this->set_property('file_ext',$ext);
    
    $max_quick=dbx()->get_cfg('dbxAdmin','max_quick');
    
    

    $dd = $this->get_property('dd');

    $bytes      = $this->get_property('run_bytes',9600);  // line max bytes
    $max_runtime = $this->get_property('run_time',10);     // less then your max script execution limit
    $max_lines   = $this->get_property('run_lines',$max_quick);  // Read in Loop max Lines
    $owner      = $this->get_property('owner',null);      // owner im Datensatz setzen
    $pass       = $this->get_property('pass',0);          // passwort 
    $utf8       = $this->get_property('utf8',1);          // umwandeln 
    $all        = $this->get_property('records_all',0);   // bei 0 werden alle Datensätze, be 1 nur MaxLines Zeilen pro Loop  
    $sep        = $this->get_property('separator',';'); 
    $where      = $this->get_property('dd_where',''); 

    //$max        = $this->get_property('max',100); 


    $deadline   = time()+$max_runtime;
    $filesize   = filesize($file);
    $file_pos    = $this->get_property('filepos',0);
    $query_count = $this->get_property('querys' ,0);
    $line_count  = $this->get_property('lines'  ,0);
    $errors     = $this->get_property('errors' ,0);
    $remap      = $this->get_property('remap'  ,0);
   
    $empty=array();
    $this->set_property('records',$empty);

   
    if ($dd) { 
      $o_db = dbx()->get_system_obj('dbxDB');
    } else {
      $max_lines=1; // return every line
    }
    dbx()->debug("#IMPORT File=($file) Pos=($file_pos) von ($filesize) Lines=($line_count) Max=($max_lines) where =($where)");

    if (!$file_pos) $this->set_property('status','init');

    ($fp = fopen($file, 'r')) OR die('failed to open file:'.$file);
    if($file_pos) fseek($fp, $file_pos);

    while( $deadline >= time() AND ( $line=fgets($fp, $bytes ) ) ) {
       $line=str_replace (array("\r\n","\n","\r"),'',$line);
       if ($utf8) $line=$this->encode_to_utf8($line);

       $this->write_import_debug($line."\n");

       if(trim($line)=='') { continue; }

       if (!$line_count) {
          $this->make_fildset($line,$remap);
       } else {
          if (!$all) $max_lines--;
          $fieldset=$this->get_fildset();
          $record=array();
          $data  =explode($sep, $line);
          
          //dbx_debug("fieldset=",$fieldset,$data);
          

          foreach ($fieldset as $no => $name) {
             $record[$name]=$data[$no];
          }

         //  $records=$this->get_property('records');
         //  $records[]=$record;
         //  if (!$dd) {
         //    $this->set_property('record' ,$record); 
         //    $this->set_property('records',$records);
         //  }

          if ($dd) {
            if ($owner != null) {
               if (!isset($record['owner'])) {
                  $record['owner']=$owner;
                  if (isset($record['userid'])) $record['owner']=$record['userid'];
               }
            }
            if ($pass) {
               if (isset($record['pass'])) $record['pass']=password_hash((string)$record['pass'], PASSWORD_DEFAULT);
            }
            
            if ($where) {
                $row_where = $this->make_where($where,$record,$o_db,$dd);
                $ok=$o_db->save($dd,$record,$row_where,0,1,0,0); //  save($dd,$field_values,$where,$verify_access=1,$verify_fields=1,$verify_values=1,$trace=1)
            } else {
               $ok=$o_db->insert($dd,$record,0,1,0,0);       //insert($dd,$field_values,$verify_access=1,$verify_fields=1,$verify_values=1,$trace=1) {
            }    

            if (!$ok) $errors++;
            if ( $ok) $query_count++;

            dbx()->debug("IMPORT CSV ($dd) OK=($ok) Where=($row_where ?? $where) Querys=($query_count) errors=($errors) Record=",$record);


          } 
       }
       $line_count++;
       $this->set_property('lines' ,$line_count);
       if (!$max_lines) break; // Zeilenweise
    } // read line

    if( feof($fp) ){
       $done=$filesize;
       $this->set_property('status','end');
       $this->set_property('percent',100);
       $this->set_property('errors',$errors);
    }else{
      $done    =ftell($fp);
      $percent =(round($done/$filesize, 2)*100);
      $this->set_property('status','run');
      $this->set_property('percent',$percent);
    }

    $this->set_property('filesize',$filesize);
    $this->set_property('querys'  ,$query_count);
    $this->set_property('lines'   ,$line_count);
    $this->set_property('filepos' ,$done);
    $this->set_property('errors'  ,$errors);

    $status=$this->get_property('status','init');

    //dbx_debug("#dbImport# Status=($status) QeryCount=($queryCount) Line=($lineCount) Filesize=($filesize) Done ($done) Prozent=($percent) ");


    //if ($status=='end')  $this->clear();
    return $status;
  } // import
  // - - - - - - - - - -





   public function run() {
    //$this->init();
    $status=$this->import();
    return $status;
   }
}
?>
