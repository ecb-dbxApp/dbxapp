<?php

class dbxProcess extends dbxObj {

  protected $_process_remember_modul = 'dbxProcess';

  public function clear($section='') {
    if (!$section) $section=$this->_section;
    if ( $section) { 
        $this->del_property('*',$section);
    } else {
      $this->_properties=array();    
    }
  } 


  public function add_norep($content) {
    $oTPL = dbx()->get_system_obj('dbxTPL');
    if (is_object($oTPL) && method_exists($oTPL, 'cleanup_optional_placeholders')) {
      $content = $oTPL->cleanup_optional_placeholders((string)$content);
    }

    if (isset($_SESSION['dbx']['norep'])) {
      $xnorep=$_SESSION['dbx']['norep'];
      if (is_array($xnorep)) {
        for($i=0; $i < 2; $i++) { // noreps can include noraps
          foreach ($xnorep as $id => $norep) {
            $xid= '['.$id.']';
            $content = str_replace($xid,$norep,$content);
          }
        }
      }
    }
    return $content;
 }

  protected function job_key($key) {
    $key = preg_replace('/[^A-Za-z0-9_.:-]+/', '_', (string)$key);
    $key = trim($key, '_');
    return (strpos($key, 'dbxprocess_') === 0) ? $key : 'dbxprocess_' . $key;
  }

  public function init_job($key, $title = '', $tasks = array(), $meta = array()) {
    $jobKey = $this->job_key($key);
    $state = is_array($meta) ? $meta : array();

    $state['proc_key']     = $jobKey;
    $state['title']        = $title;
    $state['tasks']        = is_array($tasks) ? array_values($tasks) : array();
    $state['status']       = $state['status']       ?? 'running';
    $state['percent']      = $state['percent']      ?? 0;
    $state['step_percent'] = $state['step_percent'] ?? 0;
    $state['task_pos']     = $state['task_pos']     ?? 0;
    $state['step_pos']     = $state['step_pos']     ?? 0;
    $state['message']      = $state['message']      ?? '';
    $state['started_at']   = $state['started_at']   ?? date('Y-m-d H:i:s');
    $state['updated_at']   = date('Y-m-d H:i:s');

    $this->set_job($jobKey, $state);
    return $state;
  }

  public function get_job($key) {
    $state = dbx()->get_remember_var($this->job_key($key), array(), $this->_process_remember_modul);
    return is_array($state) ? $state : array();
  }

  public function set_job($key, $state) {
    if (!is_array($state)) {
      $state = array();
    }

    $jobKey = $this->job_key($key);
    $state['proc_key'] = $state['proc_key'] ?? $jobKey;
    $state['updated_at'] = date('Y-m-d H:i:s');
    dbx()->set_remember_var($jobKey, $state, $this->_process_remember_modul);
    return $state;
  }

  public function clear_job($key) {
    dbx()->set_remember_var($this->job_key($key), array(), $this->_process_remember_modul);
  }

  public function control_job($key, $cmd) {
    $jobKey = $this->job_key($key);
    $cmd = strtolower(trim((string)$cmd));

    if ($cmd == 'restart') {
      $this->clear_job($jobKey);
      return array(
        'proc_key' => $jobKey,
        'status' => 'reset',
        'message' => 'process restarted',
        'percent' => 0,
        'step_percent' => 0,
        'updated_at' => date('Y-m-d H:i:s'),
      );
    }

    $state = $this->get_job($jobKey);
    if (!$state) {
      $state = array(
        'proc_key' => $jobKey,
        'status' => 'new',
        'message' => 'no active state',
        'percent' => 0,
        'step_percent' => 0,
      );
    }

    $status = $state['status'] ?? 'new';

    if ($cmd == 'pause' && !in_array($status, array('finished', 'error', 'canceled'), true)) {
      $state['status'] = 'paused';
      $state['paused_at'] = date('Y-m-d H:i:s');
      $state['message'] = 'process paused';
    } elseif (($cmd == 'resume' || $cmd == 'continue') && in_array($status, array('paused', 'canceled', 'new'), true)) {
      $state['status'] = 'running';
      $state['resumed_at'] = date('Y-m-d H:i:s');
      $state['message'] = ($cmd == 'continue') ? 'process continued' : 'process resumed';
    } elseif ($cmd == 'cancel' && !in_array($status, array('finished', 'error'), true)) {
      $state['status'] = 'canceled';
      $state['canceled_at'] = date('Y-m-d H:i:s');
      $state['message'] = 'process canceled';
    }

    return $this->set_job($jobKey, $state);
  }


  public function fast_response($response,$interpreter=1) {
    if ($interpreter) {
       $oInterpreter=dbx()->get_system_obj("dbxInterpreter");
       $response=$oInterpreter->run($response);
       $response=$this->add_norep($response);
    }
    echo '<br><br><br>'.$response;
    exit;
  }
 
 

  public function process() {
    $empty=array();
    $process  =$this->_section;
    $status   =$this->get_property('status','next');
    $processes=$this->get_property('processes',$empty);
    $stepp    =$this->get_property('stepp',-1);
    $count    =count($processes);
    
 


    if ($status=='next') { 
        $stepp++;
        $this->set_property('stepp',$stepp);
    }    
    if ($stepp > $count) { 
        $status='end';
        $stepp=$count;
    }      
    
    //$content= "A-Count=($count) stepp=($stepp) status=($status) Process=($process)-B<br>"; 
    $content='End';
    if (isset($processes[$stepp])) $content=$processes[$stepp]; 

    if ($status=='end') {
      //dbx_debug("ENDE-Process");
      $this->clear($this->_section);
    }
    dbx()->debug("process ($process) return=($content)");

    return $content;
  } // 

  
  



  public function add($process) {
     $empty=array();
     $processes=$this->get_property('processes',$empty);
     $processes[]=$process;
     $this->set_property('processes',$processes);
   }




   public function get_status() {
    return $this->get_property('status','next');
   }


   public function init($section) {
      $this->_section=$section;    
      $this->clear($section);
   }

   public function run($process='',$content=1,$fast_response=1) {
     if (!$process) $process=$this->_section;
     $this->_section=($process);
     if ($content) {
        $content  =$this->process();
        $processes=$this->get_property('processes');
        $status   =$this->get_status();
        dbx()->debug("#Prozess Prozess=($process) Status=($status) content=($content) status=($status)",$processes);
        if ($fast_response) $this->fast_response($content);
        return $content;
     } else {
        $ok=$this->get_status();
        return $ok;
     }  
   }
}
