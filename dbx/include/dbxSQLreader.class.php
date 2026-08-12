<?php




class dbxSQLreader extends dbxObj {

  public function import() {

    $percent=0;
    $info='import';
    $oDB = dbx()->get_system_obj('dbxDB');

    $file=$this->get_property('filename','nofile');
    $errorFile= $file.'.err'; // tmp file for erro
    $inf = new SplFileInfo($file);
    $ext = ($inf->getExtension());
    $max = ini_get('max_execution_time');
    $this->set_property('max_time',$max);
    $this->set_property('file_ext',$ext);

    $process    = $this->get_property('process','sqlImport');
    $server     = $this->get_property('server', dbx()->get_cfg('dbx', 'default_server'));
    $bytes      = $this->get_property('run_bytes',96000);
    $maxRuntime = $this->get_property('run_time',1);  // less then your max script execution limit
    $deadline   = time()+$maxRuntime;
    $filePos    = dbx()->get_session_var('filepos',0,$process);
    $queryCount = $this->get_property('querys',0);

    //dbx_debug("#IMPORT Pos=($filePos)");

    if (!$filePos) $this->set_property('status',0);
    $queryCount=dbx()->get_session_var('querys',0,$process);

    ($fp = fopen($file, 'r')) OR die('failed to open file:'.$file);
    if($filePos) fseek($fp, $filePos);

    $filesize=filesize($file);
    $query = '';

    while( $deadline>time() AND ( $line=fgets($fp, $bytes ) ) ) {
       if(substr($line,0,2)=='--' OR trim($line)=='') {
        continue;
       }
       $query.= $line;

       if( substr(trim($query),-1)==';' ){
         $ok=$server ? $oDB->exec($server, $query) : 0;
         if(!$ok) {
          $error = $query . $oDB->get_error_text();
          $errorFile=dbx()->os_path($errorFile);
          file_put_contents($errorFile, $error, FILE_APPEND);
         }
         $query = ''; $queryCount++;
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
    $this->set_property('querys'   ,$queryCount);
    dbx()->set_session_var('querys',$queryCount,$process);

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
