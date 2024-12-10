<?php
namespace dbx\LabServer;



class befunde {

  Public $oTPL;

  public function __construct() {
   $this->oTPL = dbx_get_sys_object('dbxTPL');
  }




  private function put_befunde() { 
    // Anforderungen vom Web-Serbver zum LabConn einlesen 
    $dir =dbx_get_cfg('LabServer','server_path_befund');
    $path=dbx_get_cfg('LabServer','path_befunde');
    $host=dbx_get_cfg('dbx','sftp_host');
    $user=dbx_get_cfg('dbx','sftp_user');
    $pass=dbx_get_cfg('dbx','sftp_pass');
    $port=dbx_get_cfg('dbx','sftp_port');
    
    $count=0; $count_files=0;

    $files = glob($path . '*.crypt');
    //dbx_debug("Übertrage Befunde ($path) =",$files);
    
    
    if (is_array($files)) {
      $count_files=count($files);
      if ($count_files) {
        //dbx_debug("#SFTP# Login (put_bfunde)");
        $sftp = new \phpseclib3\Net\SFTP($host);
        $sftp->login($user, $pass);
        $sftp->pwd();
        $sftp->chdir($dir);

        foreach ($files as $file) {
            $count++;
            $file_remote=basename($file);
            $file_local =$file;
            $ok=$sftp->put($file_remote, $file_local, \phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE); // upload a file with the content of the file
            if ($ok) unlink($file_local);
            //$content.="<br>transfer ok=($ok)  von ($file_local) zu ($file_remote)  <br>"; // Output only the file name
        }
      } 
    }
    return $count;
  }



  public function send_befunde() {
    $today     = date('Y-m-d', time());  
    $date_time = date('d-m-Y H:i:s');

    // Definiere die Start- und Endzeiten einmalig
    $start_time = strtotime('09:00');
    $end_time   = strtotime('17:00');
    
    // Hole die aktuelle Zeit als Timestamp
    $current_time = time();
    $current_time_today = strtotime(date('H:i', $current_time));
    
    // Prüfe, ob die aktuelle Zeit im Bereich liegt
    if ($current_time_today >= $start_time && $current_time_today <= $end_time) {
        $timer = 77; // Zeit liegt zwischen 9:00 und 17:00
    } else {
        $timer = 777; // Zeit liegt außerhalb des Bereichs
    }
    
    $loop=100;  $count=0; $stepp='this';

    $oForm=dbx_get_sys_object('dbxForm');
    $oForm->init('sende_befunde','form-run-befund');
    $oForm->_action='?dbx_modul=LabServer&dbx_action=befunde&dbx_work=send_befunde';
    
  
    $oForm->_msg_info='';
    $oForm->_msg_success='';
    $oForm->_try_max=99999999;
  
    $progress=$this->oTPL->get_tpl('dbx','progressbar-1');
    $button  =$this->oTPL->get_tpl('dbx','button-countdown');
   

    $msg="LDT Befunde übertragen.";
  
    if($oForm->submit()) {
        $count=$this->put_befunde(); 
        $msg="($count) LDT Befunde übertragen";  
        $stepp='next';
     } // submit

    $data['value']  = $loop;
   
    $pdata['msg']   = $msg;
    $pdata['value'] = $loop;
    $pdata['width'] = $loop;

    $bdata['sec']   = $timer;
    $bdata['label'] = $msg;


    $oForm->add_obj('progress','obj-value' ,$progress,$pdata);
    $oForm->add_obj('button'  ,'obj-value' ,$button  ,$bdata);
    //$oForm->add_js_autosubmit('#dbx_form_{i}',$timer);
    

    if ($stepp == 'this') $content=$oForm->run();      
    if ($stepp == 'next') $content='[modul=LabServer]&dbx_action=befunde&dbx_work=crypt_befunde&dbx_page=server[/modul]';
    return $content;
  }


  private function ctypt_ldt($path,$file,$mode='crypt') {
     $ok=1;
     $today  = date('Y-m-d', time()); 
     $path=dbx_os_path_file($path);
     //dbx_debug("crypt Befund Path=($path) File ($file)");

     if ($mode=='crypt') {
        $xfile=$file;
        $crypt_file = $xfile.'.crypt';
        //dbx_debug("Crypt path=($path) File=($xfile)");
        $ldt_content  = file_get_contents($path.$file);
        $crypt_content= dbx_crypt($ldt_content,$crypt_file);
        $ok=file_put_contents($path.$crypt_file,$crypt_content);
     }

     if ($mode == 'decrypt') {
        $xfile=str_replace('.FLO.crypt', '.ldt', $file);
        $xfile=str_replace('.crypt',     ''    , $xfile);
        $decryt_file=$path.$xfile;
        $crypt_content = file_get_contents($path.$file);
        $decrypt_content = dbx_decrypt($crypt_content,);
        file_put_contents($decryt_file, $decrypt_content);
     }



     return $ok; 

  }

  private function send_ldt($path,$file) { 
    // Anforderungen vom Web-Serbver zum LabConn einlesen 
    $dir =dbx_get_cfg('LabServer','server_path_befund');
    $path=dbx_get_cfg('LabServer','path_befunde');
    $host=dbx_get_cfg('dbx','sftp_host');
    $user=dbx_get_cfg('dbx','sftp_user');
    $pass=dbx_get_cfg('dbx','sftp_pass');
    $port=dbx_get_cfg('dbx','sftp_port');
    

    //dbx_debug("#SFTP# Login (send_ldt) Path=($path) File=($file)");
    $sftp = new \phpseclib3\Net\SFTP($host);
    $sftp->login($user, $pass);
    //$sftp->pwd($dir);
    $sftp->pwd();
    $sftp->chdir($dir);


    $file_remote=$file;
    $file_local =$path.$file;
    $ok=$sftp->put($file_remote, $file_local, \phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE); // upload a file with the content of the file
    if ($ok) unlink($file_local);
    return $ok;
  }




  public function crypt_befunde() {
      $content=''; 
      

      $msg    ='Warte';
      $percent=100;
      
      $oForm=dbx_get_sys_object('dbxForm');
      $oForm->init('crypt_befunde','form-run-befund');
      //$oForm->_data    =  $data;
      $oForm->_action  = '?dbx_modul=LabServer&dbx_action=befunde&dbx_work=crypt_befunde';
      $oForm->_msg_info= 'LDT Daten werden verschlüsselt';
      $label_button    =" Warte auf Befunde";
 
      $oWalker=dbx_get_sys_object('dbxFileWalker');
      $oWalker->init('crypt_ldt'); 
 
 
      $status =$oWalker->run();
      //dbx_debug("#Befund crypt-Walker Status 1=($status)");
   
 
      if ($status == 'init') {
       //$count_download=$this->download_befunde();
       $path=dbx_get_cfg('LabServer','path_befunde');
       $oWalker->set_property('path'   ,$path);
       $oWalker->set_property('archiv' ,$path.'.done/');
       $oWalker->set_property('date'   ,1);
       $oWalker->set_property('ext'    ,'.ldt');
       $oWalker->create_que();
       $status='run';
      }
 
 
      if ($status == 'run') { // continue
        $ok1=1; $ok2=1;
        $path   =$oWalker->get_property('path');
        $file   =$oWalker->get_property('file');
        $label_button.=" Befund-Datei=($file)";
        $path_file=$path.$file;
        //dbx_debug("##IMPORT## =($file) ($path_file) ");
        if ($path && $file) {
          $ok1=$this->ctypt_ldt($path,$file,'crypt');
          $ok2=$this->send_ldt($path,$file.'.crypt');  
          if ($ok1 && $ok2) $oWalker->archiv();
        } else {
          //$oWalker->clear();
        }
      }
 
      $status =$oWalker->get_property('status' ,0);
      $count  =$oWalker->get_property('count'  ,0);
      $percent=$oWalker->get_property('percent',0);
 
      $msg="Status=($status) Es sind ($count) LDT Befunde vorhanden.  ($percent) %";
 
      $percent=100;
 
      if ($status == 'end') { // finisch import
        $count=$oWalker->get_property('count',-1);
        $oWalker->clear();
        $oForm->_action  = '?dbx_modul=LabServer&dbx_action=befunde&dbx_work=send_befunde';     
      }
 
  
     $bdata['label']   = $label_button;
     $bdata['sec']     = 91; 
     if ($status=='run') $bdata['sec']= 1; // speed it up
 
     $pdata['msg']   = $msg;
     $pdata['width'] = $percent;
     $pdata['value'] = $percent;
 
     $progress=$this->oTPL->get_tpl('dbx','progressbar-1');
     $button  =$this->oTPL->get_tpl('dbx','button-countdown');
 
     $oForm->_msg_info=''; $oForm->_msg_success='';
 

     $oForm->add_obj('progress','obj-value',$progress,$pdata);
     $oForm->add_obj('button'  ,'obj-value',$button  ,$bdata);
     $oForm->add_js_autosubmit('dbx_form_befund',1);
     $content=$oForm->run();
     return $content;
   }
 



   public function run() {
    $modul=dbx_get_SysVar('dbx_activ_modul');
    $work =dbx_get_ModulVar('dbx_work');

    //return $work;

    switch ($work) {
 
        case 'crypt_befunde':
          $content=$this->crypt_befunde();     
        break; 


        case 'send_befunde':
          $content=$this->send_befunde();     
        break; 

        default:
          $oTPL=dbx_get_sys_object('dbxTPL');
          $msg['msg']="Modul=($modul) Work=($work) is undef";
          $content=$oTPL->get_tpl('dbx','alert-warning',$msg);

     } // switch()


      return $content;
   } // run



} // class
