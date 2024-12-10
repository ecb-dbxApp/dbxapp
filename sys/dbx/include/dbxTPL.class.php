<?php




class dbxTPL extends \dbxObj {

  function replaces($tpl,$replaces) {

    //dbx_debug("TPL=($tpl)",$replaces);
    if (!is_array($replaces)) $replaces=dbx_url_to_array($replaces);
    if ( is_array($replaces)) {
      foreach ($replaces as $key => $value) {
          if ($value == null) $value='';
          $xkey='{'.$key.'}';
          $tpl=str_replace($xkey,$value,$tpl);
      }
    }
    return $tpl;
  }

  public function replaces_dbx($tpl) {
    $tpl=str_replace('{dbx:design}',dbx_get_SysVar('dbx_activ_design',dbx_get_SysVar('dbx_design')),$tpl);
    $tpl=str_replace('{dbx:page}'  ,dbx_get_SysVar('dbx_activ_page'  ,dbx_get_SysVar('dbx_page'))  ,$tpl);
    $tpl=str_replace('{dbx:lng}'   ,dbx_get_SysVar('dbx_activ_lng'   ,dbx_get_SysVar('dbx_lng'))   ,$tpl);
    $tpl=str_replace('{dbx:title}' ,dbx_get_SysVar('dbx_title' ),$tpl);
    $tpl=str_replace('{dbx:perma}' ,dbx_get_SysVar('dbx_perma' ),$tpl);
    return $tpl;
  }

  function get_tpls($modul = 'dbx') {
    if ($modul != 'dbx') {
      $path = $this->get_tpl_dir($modul);
      $path.='htm/';
    } else {
      $path =dbx_get_base_dir();
      $path.='dbx/tpl/htm/';
    }  
    $path = dbx_os_path_file($path); // Wandelt Pfade ins Unix-Format um



    $files = [];
    $files['-']='-Ohne-';
    if (is_dir($path)) {
        $dir = scandir($path);
        foreach ($dir as $file) {
            // Nur Dateien mit der Endung '.htm' berücksichtigen
            if (is_file($path . '/' . $file) && pathinfo($file, PATHINFO_EXTENSION) === 'htm') {
                $files[$file] = $file;
            }
        }
    }

    return $files;
}


  function get_tpl_dir($modul='') {
     if (!$modul || $modul == 'modul')   $modul=dbx_get_SysVar('dbx_activ_modul' ,'undef');
     $dir=dbx_get_base_dir();
     $dir.='dbx/modules/'.$modul.'/tpl/';
     $dir=dbx_os_path_file($dir);
     if (!is_dir($dir)) $dir='';
     return $dir;
  }

  function get_tpl_url($modul='',$design='') {
     if (!$modul)   $modul=dbx_get_SysVar('dbx_modul','undef');
     if (!$design) $design=dbx_get_SysVar('dbx_design','default');
     $url=dbx_get_base_url();
     $url.='dbx/modules/'.$modul.'/design/'.$design.'/';
     return $url;
  }


  function get_tpl_dir_file($modul,$file,$type='htm') {
    $base_dir=dbx_get_base_dir().'dbx/modules/'.$modul.'/tpl/'.$type.'/';
    $path_file_type=$base_dir.$file.'.'.$type; 
    $path_file_type=dbx_os_path_file($path_file_type); 
    if (file_exists($path_file_type)) return $path_file_type; // found
    return false;  //  not found
  }


  function get_design_tpl_dir_file($type,$design,$file) {
      //dbx_debug("try Typ=($type) design=($design) fiel=($file)");
  
      $file_type=$file.'.'.$type;
      $dir_file=dbx_os_path_file(dbx_get_base_dir()."dbx/design/$design/$type/$file_type");  
      if (file_exists($dir_file))  return $dir_file;
      
      $dir_file=dbx_os_path_file(dbx_get_base_dir()."dbx/design/$design/$type/default.$type");  
      if (file_exists($dir_file))  return $dir_file;    
      
      return ''; // no file found ??  $dir_file = "dbxUndef"
  }





  function add_editor($content,$modul,$tpl,$type ) { 


     $editor=dbx_get_SysVar('dbx_editor',0);
     if ($editor) return $content; // no editor im editor (endless loop)!
     if ($tpl=='menu-tpl-edit') return $content;  
     
     //dbx_set_SysVar('dbx_editor',1);  // !Important!
     dbx_debug("add-editor for ($tpl) Modul($modul)");

     $dbx_get='?dbx_modul=dbxAdmin&dbx_action=_edittpl&type='.$type.'&modul='.$modul.'&tpl='.$tpl;

     $menu= $this->get_tpl('dbx','menu-tpl-edit');
     $design=dbx_get_SysVar('dbx_design');
     $lng   =dbx_get_SysVar('dbx_lng');

     $menu= str_replace('{dbx_get}',$dbx_get,$menu);
     $menu= str_replace('{href}'   ,$dbx_get,$menu);
     $menu= str_replace('{value}'  ,$tpl,$menu);
     $menu= str_replace('{title}'  ,$tpl,$menu);
     $menu= str_replace('{design}' ,$design,$menu);
     $menu= str_replace('{lng}'    ,$lng,$menu);



     $editA ='<div id="dbx_edit_tpl_{i}" class="dbx_edit_tpl">';
     $editB =$menu.$content; 
     $editE ='</div>';
     return $editB;  
     //return $editA.$editB.$editE;
  }


  public function get_inc_tpl($modul,$tpl,$type='htm') {
     $inc='<p class="red">'."INC=($modul/$tpl) not found".'</p>';
     if ($modul && $tpl) {
        $inc=$this->read_tpl($modul,$tpl,$type);
     }
     return $inc;
  }


// Beispiel: Funktion zum Testen der Nutzergruppe (entsprechend deinem Projekt anpassen)
function dbx_user_has_group($group) {
  return $group === 'admin'; // Beispielimplementierung
}

function dbx_check_access($level) {
  // Beispielimplementierung: Prüfen, ob der Zugriffslevel erreicht ist
  return $level <= 5; // Beispiel: Zugriff erlaubt, wenn Level <= 5
}

// Liste der erlaubten Funktionen




// Funktion, um [if=] Bedingungen zu verarbeiten
function processIfStatements($template) {
  $allowedFunctions[]='dbx_check_access';



  // Regulärer Ausdruck zum Finden von [if=...][/if] Blöcken
  $pattern = '/\[if=([^\]]+)\](.*?)\[\/if\]/s';

  // Ersetzen mit Callback
  $template = preg_replace_callback($pattern, function ($matches) use ($allowedFunctions) {
      $condition = $matches[1];
      $content = $matches[2];

      // Funktionsaufruf aus dem Template analysieren
      if (preg_match('/(\w+)\((.*?)\)/', $condition, $funcMatches)) {
          $functionName = $funcMatches[1];
          $arguments = array_map('trim', explode(',', $funcMatches[2]));

          // Prüfen, ob die Funktion existiert und in der Liste der erlaubten Funktionen steht
          if (in_array($functionName, $allowedFunctions) && function_exists($functionName)) {
              // Funktion aufrufen und Rückgabewert direkt verwenden
              return call_user_func_array($functionName, $arguments) ? $content : '';
          }
      }
      // Wenn Funktion nicht in der Liste erlaubt ist oder nicht existiert, Block entfernen
      return '';
  }, $template);

  return $template;
}





  public function get_tpl($modul,$file,$data='',$type='htm',$i=0) {

    //dbx_debug ("dbxTPLclass get tpl Modul=($modul) file=($file)"); 
    $editor=dbx_get_SysVar('dbx_editor',0,'int');
    $files =explode('|', $file);
    $count =count($files);
    //dbx_debug ("dbxTPLclass get tpl Modul=($modul) file=($file) count=($count) i=($i) Editor=($editor)"); 
    if ($count > 1) { 
        $file =$files[$count-1];
        $modul=$files[$count-2];
    } 
  
    $editor=dbx_get_SysVar('dbx_editor',0,'int');
    if ($modul=='modul' ) $modul=dbx_get_SysVar('dbx_activ_modul','dbx'); 
    $tpl="Modul=($modul) File=($file) Typ=($type) "; 
    
    if ($modul && $file  && $type) {
 
       $tpl=$this->read_tpl($modul,$file,$type);
       $tpl=$this->replaces($tpl,$data);
       $tpl=$this->include_tpl($tpl);

    }



     $tpl=$this->replaces($tpl,$data);
     if ($i && !$editor) $tpl=str_replace('{i}',$i,$tpl);
     if ($modul != 'dbx') $tpl =$this->replaces_dbx($tpl);


     $tpl=$this->processIfStatements($tpl);





     return $tpl;
  }


  function read_tpl($modul,$file,$type='htm') {
      Global $_dbxCache;

      //dbx_debug("read_tpl Modul=($modul) file=($file) typ=($type)");

      $file = strtolower($file);
      $edit = dbx_get_SysVar('dbx_edit',0); 
      //$edit=0;
      
      if (!isset($_dbxCache['tpl'][$modul][$file])) {
    
        if ($edit) {
          $access=dbx_check_access('admin');
          if (!$access)  $edit=0; 
        } 

        $tpl="<p class='error'>Template Modul=($modul) File=($file) Typ=($type) not found.</p>";
        $dir_file =$this->get_tpl_dir_file($modul,$file,$type);
        if (!$dir_file) $tpl="<p class='dbxTplError'>TPL ($file - $type) from Modul ($modul) not found.</p>";

        //dbx_debug("#TPL# Modul=($modul) File=($file)  DirFile=($dir_file) ");
        if ($modul=='dbx' && $file=='menu-tpl-edit') $edit=0; // No editor for editor menu

        // ++ kein edit on activ_edit
        //dbx_debug("##TPL read=($modul / $file)");


        if ($dir_file) {
          $tpl=file_get_contents($dir_file);
          $_dbxCache['tpl'][$modul][$file]=$tpl;
          $_dbxCache['tpf'][$modul][$file]=$dir_file;
          
          if ($edit==1 && $modul != 'dbx') $tpl=$this->add_editor($tpl,$modul,$file,$type);
          if ($edit==2 && $modul == 'dbx') $tpl=$this->add_editor($tpl,$modul,$file,$type);
          if ($edit==3                   ) $tpl=$this->add_editor($tpl,$modul,$file,$type);
          if ($type=='htm')   dbx_set_SysVar('dbx_tpl_dir_file',$dir_file);

        } else {
          $xModul=dbx_get_SysVar('dbx_activ_modul','undeff');
          $tpl="<div class=\"alert alert-danger\" role=\"alert\">TPL: ($modul) ($file)($type) not found Modul=($xModul)</div>";
        }
      } else {
        //dbx_debug("##TPL cache=($modul / $file)");
        $tpl      =$_dbxCache['tpl'][$modul][$file];
        $dir_file =$_dbxCache['tpf'][$modul][$file];
        if ($edit==1 && $modul != 'dbx') $tpl=$this->add_editor($tpl,$modul,$file,$type);
        if ($edit==2 && $modul == 'dbx') $tpl=$this->add_editor($tpl,$modul,$file,$type);
        if ($edit==3                   ) $tpl=$this->add_editor($tpl,$modul,$file,$type);
        if ($type=='htm')  dbx_set_SysVar('dbx_tpl_dir_file',$dir_file);
      }

      //dbx_debug("read-TPL",$tpl);
      return $tpl;
  }

  function include_tpl($content,$edi=0) {
    $patterns = "/\[inc=([^\[]*)\]([^\[]*)\[\/inc\]/i";
    for ($a=1; $a< 4; $a++) { // break  4
      $do=false;
      preg_match ($patterns, $content, $matches);
      $count=count($matches);
      if ($count) {
        $f1=$matches[1];
        $f2=$matches[2];
        $funci = $f1;
        if (substr($f1, -1) == ']')  $f1 = substr($f1, 0,-1);
        if ($f1 > "" && $f2 > '') {
          $ok=0;
          if (dbx_is_integer($f1)) {
            $ok=$f1;
          } else {
            $ok=0; // maybe need replace {varname}
          }
          if ($ok)  {
            $modul=''; $tpl='';
            $parameter= trim($f2);
            $part_select = explode ("&", $parameter); // Parameter vom Aufruf
            if (is_array($part_select)) {
              foreach ($part_select as $id => $value) {
                $pos = strpos($value,"=");
                $pos2=($pos+1);
                $paramn = substr($value,0,$pos); //
                $paramv = substr($value,$pos2);
                if ($paramn=='modul') $modul=$paramv;
                if ($paramn=='tpl')   $tpl  =$paramv;
                //$xparameter.= "Parameter($paramn)=($paramv) ";
              }
            }
            if ($modul && $tpl) {
              $replace=$this->get_inc_tpl($modul,$tpl);
            } else {
              $ok=0;
            }
          }
          if ($ok==0) $replace = ""; // Nichts einfügen

          $inc_patterns  = "[inc=".$f1."]".$f2."[/inc]";
          $pos1 = strpos ($content, $inc_patterns );
          $lang1= strlen ($inc_patterns);
          $lang2= strlen ($content);
          $vor  = substr ($content, 0 , $pos1);
          $nach = substr ($content, ($pos1+$lang1) , $lang2);

          $content = $vor.$replace.$nach;
          $do=true;
        }
      }
      if (!$do) break;
    }
    return $content;
  }


  function get_design_tpl($design,$page,$language,$type='htm',$repurl=1) {
      if (!$page) $page='default';
      if (substr($page,0,1) == "_") $design='admin';

      dbx_debug("check design=($design)");

      if ($design == 'admin' || $design == 'user') {
        $config=dbx_get_cfg('dbx');
        $user_default  = $config['default_design_user'];  
        $admin_default = $config['default_design_admin'];
        if ($design == 'admin') $design=$admin_default;
        if ($design == 'user' ) $design=$user_default;
        dbx_debug("read config design ($design) user=($user_default) admin($admin_default)");

      }
      
      

      $mobile=dbx_get_SysVar('dbx_is_mobile',0);
      $page_content="load page Template Design=($design) Page=($page) Lng=($language)";
      $dir_file=$this->get_design_tpl_dir_file($type,$design,$page);
 
      
      if (!$dir_file) {
         dbx_add_admin_msg('info',"Page ($page) not exist. Use default");
         $dir_file=$this->get_design_tpl_dir_file($type,$design,'default');
      }


      $dir_file=dbx_os_path_file($dir_file);
      $home_url=dbx_get_base_url();
      
      dbx_debug("#Design=($design) Page=($page) dirfile=($dir_file)");


      if ($dir_file) {
         if (file_exists($dir_file)) {
            $page_content=file_get_contents($dir_file);
            dbx_set_SysVar('dbx_activ_design',$design);
            dbx_set_SysVar('dbx_activ_page'  ,$page);
            dbx_set_SysVar('dbx_activ_lng'   ,$language);            
         }
         if ($repurl==1) {
            if ($type=='htm') {  // URL & SRC replace
                $url      = "dbx/design/$design/";               
                $new_url ='="'.$url;           
                $page_content = (str_replace("<head>","<head>\n <base href=\"".$home_url."\"/>",$page_content));
                $page_content = (str_replace('="../',$new_url,$page_content));
                $page_content =$this->replaces_dbx($page_content);

            }
         }
      }
      if ($type=='php') {
         $dir_file=$this->get_design_tpl_dir_file('php',$design,$page);
         $dir_file=dbx_os_path_file($dir_file);
         if ($dir_file) {
             if (file_exists($dir_file)) {
               include  $dir_file;
             }
         }
      }
      //dbx_debug("LOAD-TPL $dir_file");
      return $page_content;
  }



} // class

