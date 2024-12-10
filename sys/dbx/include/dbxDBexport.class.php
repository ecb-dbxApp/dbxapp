<?php

class dbxDBexport extends dbxObj {
  public $_records=array();

  public function clear() {
    $section=$this->_section;
    if ( $section) { 
        $this->del_property('*',$section);
    } else {
      $this->_properties=array();    
    }
  } 

  private function get_fields($records) {
    $fields='';  
    $split =';';
    if (is_Array($records)) {
       if (isset($records[0])) {
       if (is_Array($records[0])) {
          $rec=$records[0];
          foreach ($rec as $name => $val) {
             $fields.=$name.$split; 
          } 
        }
      }     
    }
    return $fields;
  }

  public function write_csv_haeder() {
    $haeder_line='';
    $charset=$this->get_property('charset','UTF-8');
    $seper  =$this->get_property('seperator',';');
    $path   =$this->get_property('path');
    $file   =$this->get_property('file');
    $flds   =$this->get_property('fields');

    $path   =dbx_os_path_file($path);

    $haeder_flds=explode(';',$flds);
    foreach ($haeder_flds as $no => $name) {
      $haeder_line.=$name.$seper;
    } 
    $haeder_line=dbx_convert_charset($haeder_line,$charset);
    $haeder_line.="\n";
    $ok=@file_put_contents($path.$file,$haeder_line);
    if (!$ok) $this->set_property('status','error');
  }
 
  public function write_csv_line() {
    $csv_line='';
    $charset=$this->get_property('charset','UTF-8');
    $seper  =$this->get_property('seperator');
    $path   =$this->get_property('path');
    $file   =$this->get_property('file');
    $path   = dbx_os_path_file($path);
    
    foreach ($this->_records as $no => $record) {
      foreach ($record as $name => $value) { 
         $csv_line.=$value.$seper;
      }
      $csv_line=dbx_convert_charset($csv_line,$charset);
      $csv_line.="\n";
    } 
    $ok=@file_put_contents($path.$file,$csv_line, FILE_APPEND); 
    if (!$ok) $this->set_property('status','error');
  }

  public function dbExport() {

    $count=0; $percent=0; $records=array();
    $verify_access=0;


    $max_quick=dbx_get_cfg('dbxAdmin','max_quick');
  
    $path    =$this->get_property('path','');
    $file    =$this->get_property('file','');
    
    $dd      =$this->get_property('dd','undef');
    $where   =$this->get_property('where','');
    $columns =$this->get_property('columns','*');
    $orderby =$this->get_property('orderby','');
    $asc_desc=$this->get_property('asc_desc','ASC');
    $groupby =$this->get_property('groupby','');
    $max     =$this->get_property('max',$max_quick);
    $offset  =$this->get_property('offset',0);
    $count   =$this->get_property('count',-1);

    $status  =$this->get_property('status','init');


    //dbx_debug("############# dbExport() dd=($dd) Offset=($offset) max=($max) count=($count) Status=($status) ##########################");
   
    $oDB = dbx_get_sys_object('dbxDB'); 

    
    if ($status=='init') { 
      $section=$this->_section; 
      //$this->del_property('*',$section);
      $count  =$oDB->count($dd,$where);
      $records=$oDB->select($dd,$where,$columns,$orderby,$asc_desc,$groupby,1,$offset,$verify_access);
      $fields =$this->get_fields($records);
      $this->set_property('fields',$fields);
      $this->set_property('count',$count);
      if ( $count) $status='run';
      if (!$count) $status='end';
      $this->set_property('offset',0); 
      $this->set_property('percent',0);
      $this->set_property('status',$status);
      //dbx_debug("#Export init Status=($status) Count=($count)");
      return 'init';
    }

    if ($offset >= $count) {
      $status='end';
      $percent=100;
    }        

    if ($status =='run') { 
      //dbx_debug("RUN GET-Records from ($offset) max=($max)");
      $this->_records=$oDB->select($dd,$where,$columns,$orderby,$asc_desc,$groupby,$max,$offset,$verify_access);
      $offset=($offset+$max);
      $percent =(round($offset / $count,2)*100);  
      if ($percent > 100) $percent=100;  
    }


    $this->set_property('status',$status); 
    $this->set_property('offset',$offset); 
    $this->set_property('percent',$percent);

    if ($status=='end') {
      //dbx_debug("ENDE-EXPORT");
      $count=$this->get_property('count',0); 
      $this->clear();
      $this->set_property('count',$count); // remember it
    }
    

    //dbx_debug("Status=($status) set-offset=($offset) count=($count) Prozent=($percent)"); 

    return $status;
  } // 
  

  public function get_records() {
    return $this->get_property('records',0);
  }


   public function get_status() {
    return $this->get_property('status','init');
   }


   public function init($section) {
      $this->_section=$section; 
      $status=$this->get_status();
      if ($status=="error") $status='init';
      if ($status=='init') {
         $this->clear();
      }   
      return $status;
   }

   public function run() {
     $status=$this->dbExport();
     return $status;
   }
}


?>
