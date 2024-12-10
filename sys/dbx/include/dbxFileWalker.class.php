<?php




class dbxFileWalker extends dbxObj {



  public function clear() {
    $section=$this->_section;
    //dbx_debug("#filewalker clear Section=($section)");
    $count=$this->get_property('count',-1);
    if ($section) { 
       $this->del_property('*');
    } else {
       $this->_properties=array();    
    }
    $this->set_property('count',$count);
  } 


public function init($section) {
   $this->_section=$section;
   $status=$this->get_property('status','init');
   //dbx_debug("#filewalker init  status=($status) Section=($section)") ;

   

   if ($status=='init') {
    $this->clear();
    $this->set_property('status','init');
    $this->set_property('done',0);
    $this->set_property('percent',0);
    $this->set_property('errors',0); 
    $this->set_property('count',0); 

   } 
   //dbx_debug("######init=($status) process=($process)");
   return $status;
}


  public function delete() {
    $ok=0;

    $file = $this->get_property('file','');
    $path = $this->get_property('path'  ,'');
    $path = dbx_os_path_file($path);
    $path_file=$path.$file;
    if (file_exists($path_file)) {
       $ok=unlink($path_file);
    }
    
    return $ok;
  }



  public function archiv() {
    $ok=0;

    $file  =$this->get_property('file','');
    $path  =$this->get_property('path'  ,'');
    $archiv=$this->get_property('archiv','');
    $date  =$this->get_property('date'  ,0);
    
    $path_file=$path.$file;
    if (!$file) return 0;

    //dbx_debug("#A-ARCHIV dir =($archiv) Path=($path) File=($file)");
    
    if ($archiv) {
      if (!is_dir($archiv)) return 'error'; // error wrong archiv dir
   
      if ($date) {
        $date_dir=date("Y-m-d").'/';
        $archiv.=$date_dir;
        if (!is_dir($archiv)) {
          if (!mkdir($archiv, 0777, true)) {
            return 0 ; // error wrong archiv.date dir
          }
        }
      }

      //dbx_debug("#B-ARCHIV dir =($archiv) ");
    
      
      $oldname=dbx_os_path_file($path_file);
      $newname=dbx_os_path_file($archiv.$file);
      if (file_exists($oldname)) {
        $ok= rename($oldname, $newname);
      }

      // dbx_debug("#ARCHIV ($oldname) -> ($newname) ok=($ok)"); 

    
    }
    return $ok;
  }

  public function create_que() {
     
     $path=$this->get_property('path');
     $ext =$this->get_property('ext');
     $len =strlen($ext);

     $path=dbx_os_path_file($path);
     //dbx_debug("#filewalker create_que=($path) Ext=($ext) Len=($len)"); 
      

     if (!is_dir($path)) {
        $this->set_property('status','end');
        return 0;
     }
 
     $files=$this->get_property('files',0);
     if (!$files) {        
        $files=array();
        $all_files=scandir($path);
        foreach ($all_files as $file) {
          $fext=substr($file, ($len * -1));
          if ($ext === $fext) {
            $file_path=$path.$file;  
            if (is_file($file_path)) $files[]=$file;
          } 
      }       
      $this->set_property('files',$files);
      //dbx_debug ("#filewalker create_que files=",$files);
     }
     $files=$this->get_property('files',0);
     if (is_array($files)) {
       $count=count($files);   
     } else {
       $count=0; 
     }

     //dbx_debug ("#filewalker create_que count=($count)"); 

     if ( $count) $this->set_property('status','run');
     if (!$count) $this->set_property('status','end');
     $this->set_property('count',$count);
     $this->set_property('pos',0);

     return $count;
  }


  public function filewalker() {

    $count=0; $percent=0; $file=''; $files=array();
    $info='import';

    $status =$this->get_property('status','init');
    $count  =$this->get_property('count',0);
    $pos    =$this->get_property('pos'  ,0);
    $percent=$this->get_property('percent',0);

    //dbx_debug("STATUS-A=($status)");
    if ($status=='run' && $pos > $count) $status='end';
    if ($status=='end') $status='init';
    if ($status=='init') {
       $this->clear();
       $this->set_property('status','init');
       $this->set_property('pos',0);

       //dbx_debug ("### Walker run Status=(init) ($status) count=($count) Pos=($pos) Percent=($percent)"); 

       return 'init';
    }
    

    $path   =$this->get_property('path');
    $files  =$this->get_property('files'); 
    $count  =$this->get_property('count'  ,0); 
    $pos    =$this->get_property('pos'    ,0);
    $percent=$this->get_property('percent',0);
    $path   =dbx_os_path_file($path);

    if (!$count || $pos > $count) $status='end';  
    
    
    
    if ($status=='run') {
       if ($pos >= $count ) {
        $this->set_property('status','end');
        return 'end';
       }  
    }
   
    //dbx_debug ("### Walker run Status=($status) count=($count) Pos=($pos) Percent=($percent)"); 

    if ($status=='run' && is_Array($files)) {
      if ($pos <= $count) {
        if (isset($files[$pos])) {
           $pos = ($pos + 1);
           $file    = $files[$pos-1];
           $percent =(round($pos / $count,2)*100); 
           $this->set_property('file',$file);
           $this->set_property('path_file',$path.$file);
           $this->set_property('percent',$percent);
           $this->set_property('status','run');
           $this->set_property('pos',$pos); 

        } // isset
      } // pos <= count
    } // is_array

    $this->set_property('status',$status); 
    //if ($status=='end') $this->clear();
    return $status;
  } // filewalker
  // - - - - - - - - - -



   public function run() {
     $status=$this->filewalker();
     return $status;
   }
}

