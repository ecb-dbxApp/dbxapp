<?php

/**
 * Importiert SQL-Dateien in zeitlich begrenzten, wiederaufnehmbaren Schritten.
 */
class dbxSQLreader extends dbxObj {

  public function import() {

    $percent=0;
    $info='import';
    $o_db = dbx()->get_system_obj('dbxDB');

    $file=$this->get_property('filename','nofile');
    $error_file= $file.'.err'; // tmp file for erro
    $inf = new SplFileInfo($file);
    $ext = ($inf->getExtension());
    $max = ini_get('max_execution_time');
    $this->set_property('max_time',$max);
    $this->set_property('file_ext',$ext);

    $process    = $this->get_property('process','sqlImport');
    $server     = $this->get_property('server', dbx()->get_cfg('dbx', 'default_server'));
    $bytes      = $this->get_property('run_bytes',96000);
    $max_runtime = $this->get_property('run_time',1);  // less then your max script execution limit
    $deadline   = time()+$max_runtime;
    $file_pos    = dbx()->get_session_var('filepos',0,$process);
    $query_count = $this->get_property('querys',0);

    //dbx_debug("#IMPORT Pos=($filePos)");

    if (!$file_pos) $this->set_property('status',0);
    $query_count=dbx()->get_session_var('querys',0,$process);

    ($fp = fopen($file, 'r')) OR die('failed to open file:'.$file);
    if($file_pos) fseek($fp, $file_pos);

    $filesize=filesize($file);
    $query = '';

    while( $deadline>time() AND ( $line=fgets($fp, $bytes ) ) ) {
       if(substr($line,0,2)=='--' OR trim($line)=='') {
        continue;
       }
       $query.= $line;

       if( substr(trim($query),-1)==';' ){
         $ok=$server ? $o_db->exec($server, $query) : 0;
         if(!$ok) {
          $error = $query . $o_db->get_error_text();
          $error_file=dbx()->os_path($error_file);
          file_put_contents($error_file, $error, FILE_APPEND);
         }
         $query = ''; $query_count++;
         dbx()->set_session_var('filepos',ftell($fp),$process);// save current file position
       }

    }

    if( feof($fp) ){
       //dbx()->set_session_var($progress,0);
       $this->set_property('status',2);
       $this->set_property('done',$filesize);
       $this->set_property('percent',100);
    }else{
      $done    =ftell($fp);
      $percent =(round($done/$filesize, 2)*100);
      $this->set_property('status',1);
      $this->set_property('done',$done);
      $this->set_property('percent',$percent);
      dbx()->set_session_var('filepos',$done,$process);
    }
    $this->set_property('filesize' ,$filesize);
    $this->set_property('querys'   ,$query_count);
    dbx()->set_session_var('querys',$query_count,$process);

    $status=$this->get_property('status',0);

    //dbx_debug("#dbImport# Status=($status) QeryCount=($queryCount) Filesize=($filesize) Pos ($filePos) Prozent=($percent) ");


    if ($status==2) {
      dbx()->delete_session_var('*',$process);
    }
    return $status;
  } // import
  // - - - - - - - - - -





   public function run() {
    //$this->init();
    $ok=$this->import();

    return $ok;
   }
}


?>
