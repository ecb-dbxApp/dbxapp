<?php

//global $dbxGlobalObj; $dbxGlobalVar;  $dbxCacheTPL;
require_once __DIR__ .'/dbxApi.php';

function dbx_debug($txt,$data1='',$data2='',$data3='') {
  $vars='';
  $debug_activ=dbx_get_file_dir()."dbxDebugActiv.txt";
  if (file_exists($debug_activ)) {

    $file=dbx_get_file_dir()."dbxDebug.txt";
  
    if (is_array($data1)) {
      $vars.= print_r($data1, true);
    } else {
      if ($data1 > '') $vars.=$data1."\n";
    }
    if (is_array($data2)) {
      $vars.= print_r($data2, true);
    } else {
      if ($data2 > '') $vars.=$data2."\n";
    }
    if (is_array($data3)) {
      $vars.= print_r($data3, true);
    } else {
      if ($data3 > '') $vars.=$data3."\n";
    }

    $txt.="\n".$vars."\n";
    $file=dbx_os_path_file($file);
    file_put_contents($file, $txt, FILE_APPEND);
  }
}



class dbxObj {

  Public $_properties=array();
  Public $_section='';
  Public $_process='';



  public function set_property($name,$value,$section='',$modul='modul') {
    if (!$section) $section=$this->_section;
    if ( $modul=='modul') $modul=dbx_get_SysVar('dbx_activ_modul');
    if (!$section) {
       $this->_properties[$name]=$value;
    } else {
      dbx_set_SessionVal($name,$value,$section,$modul);  
    }   
    //if (!is_array($value)) dbx_debug("Set_property=($name) section ($section) modul=($modul) value=($value)");
  }

  public function get_property($name,$default='',$section='',$modul='modul') {
    if (!$section) $section=$this->_section;  
    if (!$section) { 
      if (isset($this->_properties[$name])) {
        $value=$this->_properties[$name];
      } else {
        $value=$default;
      }
    } else {
      if ($modul=='modul') $modul=dbx_get_SysVar('dbx_activ_modul');
      $value=dbx_get_SessionVal($name,$default,$section,$modul);
    }
    //if (!is_array($value)) dbx_debug("Get_property=($name) section ($section) modul=($modul) value=($value)");
    return $value;
  }
   

  public function del_property($name,$section='',$modul='modul') {
    if (!$section) $section=$this->_section;
    if (!$section) {
      if ($name != '*') {
        if (isset($this->_properties[$name])) unset($this->_properties[$name]);
      } else {
        $this->_properties=array();
      }   
    }
    if ($section) {
      if ($modul=='modul') $modul=dbx_get_SysVar('dbx_activ_modul');
      dbx_del_SessionVal($name,$section,$modul); 
    }
  }

  public function dbx_next_process($content,$process='',$mode='append') {
    $next=''; $pos=0;
    if (!$process) $process=dbx_get_SessionVal('dbx_process',0);
    //dbx_debug("#GET-PROCESS=($process)");
    if ($process) {
      $oProcess=dbx_get_sys_object('dbxProcess');
      $next=$oProcess->run($process);
      $pos =$oProcess->get_property('stepp');
    }
  
    //$content.="<br>Process=($process) Pos=($pos)<br>$next";
    if ($mode=='append')  $content.=$next;
    if ($mode=='insert')  $content =$next.$content;
    if ($mode=='replace') $content =$next;
    
    return $content;
 }



}

// - - - - - -

class dbxUndefModul {
   function run() {
      $class = dbx_get_SysVar('dbx_modul');
      return "The Modul Classfile <b>$class</b> is undef!";
   }
}

class dbxUndefClass {
   function run() {
      $url   = dbx_get_base_url();
      $path  = dbx_get_base_dir();
      $action= dbx_get_ModulVar('dbx_action');
      $class = dbx_get_SysVar('dbx_modul');
      $inc   = dbx_get_SysVar('dbx_inc');
      $master= dbx_get_SysVar('dbx_master_modul');
      $mact  = dbx_get_SysVar('dbx_master_action');
      return "Aktion=($action) Modul Inc-Classfile <b>$inc</b> from <b>$class</b> is undef!<br> Master=($master) Action=($mact)<br>Base-Url=($url)";
   }
}
