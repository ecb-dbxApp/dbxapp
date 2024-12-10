<?php
namespace dbx\LabServer;


class anforderungen {

  Public $oTPL;

  public function __construct() {
   $this->oTPL = dbx_get_sys_object('dbxTPL');
  }


 

  private function get_anforderungen() { 
    // Anforderungen vom Web-Serbver zum LabConn einlesen 
    $dir =dbx_get_cfg('LabServer','server_path_order');
    $path=dbx_get_cfg('LabServer','path_anforderungen');
    $host=dbx_get_cfg('dbx','sftp_host');
    $user=dbx_get_cfg('dbx','sftp_user');
    $pass=dbx_get_cfg('dbx','sftp_pass');
    $port=dbx_get_cfg('dbx','sftp_port');
    
    $path=dbx_os_path_file($path);
    //dbx_debug("#SFTP# Login (get_anforderungen)");
    
    $sftp = new \phpseclib3\Net\SFTP($host);
    $sftp->login($user, $pass);
    $sftp->pwd();
    $sftp->chdir($dir); 
    $files=$sftp->nlist();
    $count=0;

    //dbx_debug("Server FILES xChange order ($dir) -> ($path)",$files); 


    foreach ($files as $file) {
      $ext=substr($file,strrpos($file,'.'));
      dbx_debug("File on Server file=($file) Ext=($ext)");
      if ($ext == '.crypt') {
          $count++;
          $file_remote=$file;
          $file_local =$path.$file;
          $sftp->get($file_remote,$file_local); 
          $sftp->delete($file_remote); 
          $ldt=file_get_contents($file_local);
          $ldt=dbx_decrypt($ldt,$file);
          if ($ldt != -1) {  // -1 decrypt error ! 
            $file_locale_decrypt=substr($file_local, 0, -6);
            file_put_contents($file_locale_decrypt,$ldt);
            if (file_exists($file_local)) unlink($file_local);
          }     
      } 
    }    
    

    return $count;
  }



  public function run_order() {
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
        $timer = 88; // Zeit liegt zwischen 9:00 und 17:00
    } else {
        $timer = 888; // Zeit liegt außerhalb des Bereichs
    }
    $count=0;    $loop=100;  

    $oForm=dbx_get_sys_object('dbxForm');
    $oForm->init('form-run-order');
    $oForm->_action='?dbx_modul=LabServer&dbx_action=anforderungen&dbx_work=get_anforderungen';
    


    //$oForm->_data=$data;
    $oForm->_fld_change_state='all';
    $oForm->_msg_info='';
    $oForm->_msg_success='';
  
    $oForm->_try_max=99999999;
   
    $msg="Anforderungen übertragen.";
  
    if($oForm->submit()) {
        $loop=$oForm->get_post('loop',0,'int');
        $loop=($loop + 10); if ($loop > 100) $loop=10;
        $count=$this->get_anforderungen(); 
        $msg="($count) Patienten ans Labor übertragen";  
    } // submit

    $data['value']=$loop;
   
    $pdata['msg']  =$msg;
    $pdata['value']=100;
    $pdata['width']=100;

    $bdata['sec']     = $timer;
    $bdata['label']   = $msg;
   

    $progress=$this->oTPL->get_tpl('dbx','progressbar-1');
    $button  =$this->oTPL->get_tpl('dbx','button-countdown');

    $oForm->_msg_info=''; $oForm->_msg_success='';

    $oForm->add_obj('progress','obj-value',$progress,$pdata);
    $oForm->add_obj('button'  ,'obj-value',$button  ,$bdata);
    
    $content=$oForm->run();      
    
    return $content;
  }






  public function run() {
     $content=$this->run_order();
     return $content;
  } // run()

} // class
