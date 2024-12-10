<?php




class dbxSQLreader extends dbxObj {

  public function import() {

    $percent=0;
    $info='import';
    $oDB = dbx_get_sys_object('dbxDB');

    $file=$this->get_property('filename','nofile');
    $errorFile= $file.'.err'; // tmp file for erro
    $inf = new SplFileInfo($file);
    $ext = ($inf->getExtension());
    $max = ini_get('max_execution_time');
    $this->set_property('max_time',$max);
    $this->set_property('file_ext',$ext);

    $process    = $this->get_property('process','sqlImport');
    $bytes      = $this->get_property('run_bytes',96000);
    $maxRuntime = $this->get_property('run_time',1);  // less then your max script execution limit
    $deadline   = time()+$maxRuntime;
    $filePos    = dbx_get_SessionVal('filepos',0,$process);
    $queryCount = $this->get_property('querys',0);

    //dbx_debug("#IMPORT Pos=($filePos)");

    if (!$filePos) $this->set_property('status',0);
    $queryCount=dbx_get_SessionVal('querys',0,$process);

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
         $ok=$oDB->query($query,'status');
         if(!$ok) {
          $error = $query . $oDB->error();
          $errorFile=dbx_os_path_file($errorFile);
          file_put_contents($errorFile, $error, FILE_APPEND);
         }
         $query = ''; $queryCount++;
         dbx_set_SessionVal('filepos',ftell($fp),$process);// save current file position
       }

    }

    if( feof($fp) ){
       //dbx_set_SessionVal($progress,0);
       $this->set_property('status',2);
       $this->set_property('done',$filesize);
       $this->set_property('percent',100);
    }else{
      $done    =ftell($fp);
      $percent =(round($done/$filesize, 2)*100);
      $this->set_property('status',1);
      $this->set_property('done',$done);
      $this->set_property('percent',$percent);
      dbx_set_SessionVal('filepos',$done,$process);
    }
    $this->set_property('filesize' ,$filesize);
    $this->set_property('querys'   ,$queryCount);
    dbx_set_SessionVal('querys',$queryCount,$process);

    $status=$this->get_property('status',0);

    //dbx_debug("#dbImport# Status=($status) QeryCount=($queryCount) Filesize=($filesize) Pos ($filePos) Prozent=($percent) ");


    if ($status==2) {
      dbx_del_SessionVal('*',$process);
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