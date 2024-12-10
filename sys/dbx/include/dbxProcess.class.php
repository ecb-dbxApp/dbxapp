<?php

class dbxProcess extends dbxObj {

  public function clear($section='') {
    if (!$section) $section=$this->_section;
    if ( $section) { 
        $this->del_property('*',$section);
    } else {
      $this->_properties=array();    
    }
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
    $content='';
    if (isset($processes[$stepp])) $content.=$processes[$stepp]; 

    if ($status=='end') {
      //dbx_debug("ENDE-Process");
      $this->clear($this->_section);
    }
    

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

   public function run($process='') {
     if (!$process) $process=$this->_section;
     $this->_section=($process);
     $content=$this->process();
     return $content;
   }
}


?>
