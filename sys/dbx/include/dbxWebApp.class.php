<?php

class dbxWebApp {
/**
 * Ermittelt die Basis-URL des Servers und gibt sie zurück.
 *
 * Diese Funktion baut eine URL mit dem Protokoll, der Server-IP-Adresse oder
 * `localhost` und dem Port auf und hängt den angegebenen URI daran an.
 *
 * @param string $uri Der URI, der an die Basis-URL angehängt wird.
 * @return string Die vollständige URL, die auf den angegebenen URI verweist.
 */

 function get_base_url($uri): string {
  // Bestimmt das Protokoll basierend auf dem HTTPS-Status
  $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";

  // Bestimmt den Host, je nachdem, wie der Server aufgerufen wird
  $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_ADDR'];

  // Verwendung der IP-Adresse oder des spezifischen Hostnamens
  if ($host === 'heserver') {
      $host = 'heserver'; // Belassen Sie 'heserver' unverändert
  } elseif ($host === 'localhost' || $host === '127.0.0.1') {
      $host = 'localhost'; // Verwenden Sie 'localhost'
  } elseif (filter_var($host, FILTER_VALIDATE_IP)) {
      // Bei gültiger IP bleibt der Host unverändert
  } else {
      // Fallback zu SERVER_ADDR, um sicherzustellen, dass wir die IP erhalten
      $host = $_SERVER['SERVER_ADDR'];
  }

  // Bestimmt den Port
  $port = $_SERVER['SERVER_PORT'];
  $xport = '';

  // Überprüfen, ob der Port angegeben werden soll
  if ($port && $port != 80 && $port != 443) {
      $xport = ':' . $port; // Fügen Sie den Port nur hinzu, wenn er nicht der Standardport ist
  }

  // Erstellen der vollständigen URL unter Verwendung des ermittelten Hosts
  $base_url = $protocol . $host . $xport . rtrim($uri, '/');

  //dbx_debug("#SYSTEM BASE URL=($base_url) Port=($port) xPort=($xport) ",$_SERVER); 

     // cache ? #todo
  if ($port >= 8080) {
     $base_href_file = dbx_get_file_dir().'base_href.cfg';
     $base_url = file_get_contents($base_href_file);
     $base_url = preg_replace('/[\x00-\x1F\x7F]/', '', $base_url);
     $base_url = trim($base_url);
     dbx_debug("# base_url from ($base_href_file) base_href.cfg ($base_url) +");
  } 


  // Fügt am Ende einen Schrägstrich hinzu, falls die URL nicht mit '/' oder '?' endet
  $base_url=rtrim($base_url,'\\');
  if (substr($base_url, -1) !== '/' && substr($base_url, -1) !== '?') {
      $base_url .= '/';
  }
 

  dbx_debug("# Output base_url#=($base_url) Port=($port)");
  return $base_url;
}




  function create_new_cfg($modul='dbx') {
     if ($modul == 'dbx') {
      $config['host']='localhost';
      $config['name']='';
      $config['user']='root';
      $config['password']='';
      $config['port']='';
      $config['default_lng']          ='de';
      $config['accessible_lng']       ='de,en';
      $config['default_color']        ='blue';
      $config['default_design_user']  ='lda';
      $config['default_design_admin'] ='_admin';
      $config['cache']=0;
      $config['intro']=0;
      $config['trace']=0;
      $config['construct']=0;
      $config['secure']='secure-key';
      $config['ok']=0;
      
     } else {
       // create Modul cfg; 
     }
     dbx_set_cfg($modul,$config);
     return $config;
  }

  function check_uri() {
    $uri='';
    $request = $_SERVER['REQUEST_URI'];

    // Zerlege die URL in ihre Komponenten
    $url_components = parse_url($request);

    // Speichere den Pfad in $_GET mit dem Schlüssel 'dbx_request_path'
    if (isset($url_components['path'])) {
        $uri = $url_components['path'];
    }

    // Zerlege die Query-Parameter und speichere sie in $_GET
    if (isset($url_components['query'])) {
        parse_str($url_components['query'], $query_params);
        foreach ($query_params as $key => $value) {
            $_GET[$key] = $value;
        }
    }
    return $uri; 
}


function get_self_url($permalink,$unwanted) {
  $self_url = '';  $query_string = '';


  // Zuerst den vollständigen Request-URI prüfen
  if (!empty($_SERVER['REQUEST_URI'])) {
      $url_components = parse_url($_SERVER['REQUEST_URI']);
  } else {
      // Alternativ: Nur den Pfad und Query-String separat prüfen
      $url_components = [];
      if (!empty($_SERVER['SCRIPT_NAME'])) {
          $url_components['path'] = $_SERVER['SCRIPT_NAME'];
      }
      if (!empty($_SERVER['QUERY_STRING'])) {
          $url_components['query'] = $_SERVER['QUERY_STRING'];
      }
  }
  
  // Überprüfe, ob eine Query-Komponente vorhanden ist
  if (isset($url_components['query'])) {
      $query_string = $url_components['query'];
      parse_str($query_string, $query_params);

      // Unerwünschte Parameter in ein Array umwandeln und entfernen
      $unwanted_params = explode(',', $unwanted);
      foreach ($unwanted_params as $param) {
          unset($query_params[$param]);
      }

      // Erstelle die neue Query-String ohne unerwünschte Parameter
      if (!empty($query_params)) {
          $self_url = '?' . http_build_query($query_params);
      }
  }
  if ($self_url) {
     $self_url=$permalink.$self_url;
  } else {
    $query='';
    if (isset($_SERVER['QUERY_STRING'])) $query=$_SERVER['QUERY_STRING'];
    $self_url=$permalink.$query;
  }
  if (!$self_url) $self_url='?';
  return $self_url;
}


  function get_base_uri() {
    // Versuche zuerst, den URI aus $_SERVER['SCRIPT_NAME'] zu holen
    if (!empty($_SERVER['SCRIPT_NAME'])) {
        $uri = $_SERVER['SCRIPT_NAME'];
    } 
    // Fallback auf $_SERVER['PHP_SELF'], falls $_SERVER['SCRIPT_NAME'] nicht verfügbar ist
    elseif (!empty($_SERVER['PHP_SELF'])) {
        $uri = $_SERVER['PHP_SELF'];
    } 
    // Als letzte Option, versuche den URI aus $_SERVER['REQUEST_URI'] ohne Query-Parameter
    elseif (!empty($_SERVER['REQUEST_URI'])) {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    } 
    // Falls keine der Optionen verfügbar ist, gib einen leeren String zurück oder eine Fehlermeldung
    else {
        $uri = ''; // Oder ein bestimmter Wert, z.B. "/unknown" oder ein Fehlerwert
    }
    
    // Entferne den Dateinamen, um nur das Verzeichnis zurückzugeben
    $uri=dirname($uri);
    if ($uri=='\\') $uri='/';
    return $uri;
  }



  function get_permalink($uri) {
    $url=$_SERVER['REQUEST_URI'];
    $permalink=parse_url($url, PHP_URL_PATH);
    if (strpos($permalink, $uri) === 0) {
      // Anfang entfernen und den Rest des Strings zurückgeben
      $permalink=substr($permalink, strlen($uri));
    }
    if (strpos($permalink, '/') === 0) {
      // Entfernt das erste Zeichen '/' und gibt den Rest zurück
      $permalink= substr($permalink, 1);
    }
    $pos = strpos($permalink, '?'); // Wenn '?' gefunden wurde, den Teil bis zur Position zurückgeben
    if ($pos !== false)  $permalink= substr($permalink, 0, $pos);
    $pos = strpos($permalink, '&'); // Wenn '&' gefunden wurde, den Teil bis zur Position zurückgeben
    if ($pos !== false)  $permalink= substr($permalink, 0, $pos);


    //dbx_debug("##PERMA=($permalink) URL=($url) URI=($uri)");
    return $permalink;
  }


  function setRequestUriToGet() {
    $requestUri=$_SERVER['REQUEST_URI'];
    // Prüfen, ob in der URI ein '?' oder '&' vorhanden ist
    $queryStart = strpos($requestUri, '?') !== false ? strpos($requestUri, '?') : strpos($requestUri, '&');
    // Wenn keine Query-Parameter vorhanden sind, beenden
    if ($queryStart === false)  return;
    // Query-Teil extrahieren (alles nach '?' oder '&')
    $queryString = substr($requestUri, $queryStart + 1);
    // GET-Parameter aus dem Query-String parsen und ins $_GET-Array schreiben
    parse_str($queryString, $_GET);
}


  function check_request()  {
    //dbx_debug("dbxApp check request");
    //dbx_debug('#INCOME-POST',$_POST);
    //dbx_debug('#INCOME-GET' ,$_GET);
    //dbx_debug("SERVER"      ,$_SERVER);
    
    $pvx=array();   $gvx=array();



    if (isset($_POST['dbx_get'])) { // Ajax Formular
      $get = $_POST['dbx_get'];
      $get = ltrim($get, '?');
      parse_str($get, $gvx);
      $_GET=array_merge($_GET, $gvx);
      unset($_POST['dbx_get']);
    }

    if (isset($_POST['dbx_post'])) { // Ajax Formular
      $post= $_POST['dbx_post'];
      $post= ltrim($post, '?');
      parse_str($post, $pvx);
      $_POST=array_merge($_POST, $pvx);
      unset($_POST['dbx_post']);
    }

    /*
    if (!empty($_GET)) {
      // Hol den ersten Schlüssel und den ersten Wert
      $firstKey = array_key_first($_GET);
      $firstValue = $_GET[$firstKey];
  
      // Prüfen, ob das erste Zeichen des Schlüssels ein '?' ist
      if (strpos($firstKey, '?') === 0) {
          // Entferne den Eintrag mit dem alten Schlüssel
          dbx_debug("FIX-GET Key ($firstKey)");
          unset($_GET[$firstKey]);
          // Entferne das '?' am Anfang des Schlüssels
          $newKey = ltrim($firstKey, '?');
          // Füge den Wert mit dem neuen Schlüssel hinzu
          $_GET[$newKey] = $firstValue;
      }
    }
    */
    
    


    if (empty($_GET)) $this->setRequestUriToGet(); // ! important für mix url  permalink && parameter

    //dbx_debug('#OUT-GET POST' ,$_GET,$_POST);
    
    $base_uri =$this->get_base_uri();
    $base_url =$this->get_base_url($base_uri);
    $permalink=$this->get_permalink($base_uri);
    $self_url =$this->get_self_url($permalink,'dbx_ajax,dbx_window,dbx_target,dbx_go');
    $go       = dbx_get_PostGetVar('dbx_go'    ,0,'parameter');
    $ajax     = dbx_get_PostGetVar('dbx_ajax'  ,0,'int');
    $window   = dbx_get_PostGetVar('dbx_window',0,'int'); 
    

    dbx_debug("#SYS Self-url=($self_url) base-Url=($base_url) base_uri=($base_uri) Perma=($permalink)");

    //dbx_debug('#-POST',$_POST);
    //dbx_debug('#-GET' ,$_GET);

   
    
    dbx_set_SysVar('dbx_permalink',$permalink);
    dbx_set_SysVar('dbx_self_url' ,$self_url);
    dbx_set_SysVar('dbx_base_url' ,$base_url);  
    dbx_set_SysVar('dbx_base_uri' ,$base_uri);  
    dbx_set_SysVar('dbx_window'   ,$window);
    dbx_set_SysVar('dbx_ajax'     ,$ajax);
    dbx_set_SysVar('dbx_go'       ,$go);


  }


  function get_is_resorce($ext) {
     $ext=strtolower($ext);
     $extensions = ['gif', 'jpg', 'png', 'jpeg', 'pdf', 'css', 'js', 'exe'];
     // Überprüfen, ob die Erweiterung im Array vorhanden ist
     return in_array($ext, $extensions) ? 1 : 0;    
  }



  function check_config() {
    $config=dbx_get_cfg('dbx');
    if (!is_array($config)) { 
      $config=$this->create_new_cfg('dbx');
    } else {
      if (!isset($config['ok'])) $config=$this->create_new_cfg('dbx');
    }  
    dbx_Set_SysVar('dbx_install'  ,$config['install']);
    dbx_Set_SysVar('dbx_construct',$config['construct']);
    If (!$config['ok']) dbx_set_SysVar('dbx_install',1);
  }



  function check_missing() {
    $ext='dbx';
    $permalink=dbx_get_SysVar('dbx_permalink','','parameter+/');

    if ($permalink > ' ') {
      $info = pathinfo($permalink);
      if (isset($info['extension'])) $ext=$info['extension'];
      $ext=strtolower($ext);

      if ($this->get_is_resorce($ext)) { // request missing ressorce
        dbx_debug("## check missing ($permalink)");

        $uid=dbx_get_CurrentUser('userid');
        $now=date('Y-m-d H:i:s');
        $where="missing = '$permalink'";

        $db=dbx_get_sys_object('dbxDB');
        $rec=$db->select1('dbx_missing',$where,'*','','ASC','',1,0,0);
        //dbx_debug("#MISSING# ($permalink) Ext=($ext) where=($where) rec=",$rec);

        if (is_array($rec)) {
          $rec['count'] = ($rec['count'] +1);
          $ok=$db->update('dbx_missing',$rec,$where,0);
        } else {
          $rec=array();
          $rec['owner']   = $uid;
          $rec['missing'] = $permalink;
          $rec['count']   = 1;
          $ok=$db->insert('dbx_missing',$rec,0);
        }
        // maybe return default resource.$ext
        dbx_Debug('###EXIT###');
        exit; // !
      }
    }
  }

  public function check_remember() {
    // #Session switch values 
    $page  =dbx_get_Remember('dbx_page'  ,'default','*'        ,'dbx');
    $design=dbx_get_Remember('dbx_design','user'   ,'*'        ,'dbx');
    $edit  =dbx_get_Remember('dbx_edit'  ,0        ,'int'      ,'dbx');
    $lng   =dbx_get_Remember('dbx_lng'   ,'de'     ,'parameter','dbx');
    $color =dbx_get_Remember('dbx_color' ,'light'  ,'parameter','dbx');

    $page  =dbx_get_PostGetVar('dbx_page'  ,$page);
    $design=dbx_get_PostGetVar('dbx_design',$design);
    $edit  =dbx_get_PostGetVar('dbx_edit'  ,$edit);
    $lng   =dbx_get_PostGetVar('dbx_lng'   ,$lng);
    $color =dbx_get_PostGetVar('dbx_color' ,$color);
   
    dbx_set_Remember('dbx_page'  ,$page  ,'dbx');
    dbx_set_Remember('dbx_design',$design,'dbx');
    dbx_set_Remember('dbx_edit'  ,$edit  ,'dbx');  
    dbx_set_Remember('dbx_lng'   ,$lng   ,'dbx');  
    dbx_set_Remember('dbx_color' ,$color ,'dbx');  

  }

  public function check_perma() {
     $cid=0; $home=0;
     $permalink= dbx_get_SysVar('dbx_permalink');
     if ($permalink=='undeff') $permalink='';
  

     $lng=dbx_get_SysVar('dbx_lng','de');
     $content_dd = 'dbx_'.$lng.'_content';
     $api_dd     = 'dbx_api';
     $modul      = dbx_get_PostGetVar('dbx_modul');
     $action     = dbx_get_PostGetVar('dbx_action');
     if ( $permalink  == 'home' ) $home=1;
     if (!$permalink  && !$modul) $home=1;
     if ( $modul=='dbxHome')      $home=1;



     if ($home) {
      dbx_set_SysVar('dbx_modul' ,'dbxHome');
      dbx_set_SysVar('dbx_action','run');
      dbx_set_SysVar('dbx_design','user');
      return;
     }

     if ($permalink =='admin') {
      dbx_set_SysVar('dbx_modul','dbxAdmin');
      dbx_set_SysVar('dbx_action','run');
      dbx_set_SysVar('dbx_design','admin');
      return;
    } 

    if ($permalink =='/')       return ;
    if ($permalink =='undeff')  return ; 



     if ($permalink > ' ') {
       //$permalink=strtolower($permalink);
       dbx_debug("##Permalink## ($permalink) Lng=($lng) check content#");
       $where="permalink = '$permalink'";
       $db=dbx_get_sys_object('dbxDB');
       $rec=$db->select1($content_dd,$where,'*','','ASC','',1,0,0);
       //dbx_debug("#PERMALINK where=($where)" );
       if (is_array($rec)) {
          $cid=$rec['id'];
          dbx_set_SysVar('dbx_modul' ,'dbxContent');
          dbx_set_SysVar('dbx_action','run');
          dbx_set_SysVar('dbx_cid'   , $cid);
          dbx_debug("#PERMALINK set ($cid)");
       } else {  // check for API-key
          $where="apikey = '$permalink'";
          $rec=$db->select1($api_dd,$where,'*','','ASC','',1,0,0);
          if (is_array($rec)) {
             dbx_debug("##Permalink ($permalink) set modul=".$rec['modul']);
             $cid=$rec['id'];
             $api=$rec['api'];
             dbx_set_SysVar('dbx_modul' ,$rec['modul']);
             dbx_set_SysVar('dbx_action',$rec['action']);
             dbx_set_SysVar('dbx_api'   ,$rec['api']);
          } else {
            if ($permalink =='admin') {
              dbx_set_SysVar('dbx_modul','dbxAdmin');
              dbx_set_SysVar('dbx_action','run');
              dbx_set_SysVar('dbx_design','admin');
            } 
            if ($permalink =='home') {
              dbx_set_SysVar('dbx_modul','dbxHome');
              dbx_set_SysVar('dbx_action','run');
              dbx_set_SysVar('dbx_design','user');
            } 
        }
         //dbx_debug("#PERMALINK no ($cid)");
       }
     }
  }
  
  public function check_modul() {
      $modul=dbx_get_SysVar('dbx_modul');
      if (!dbx_is_modul($modul)) $modul='dbxHome';
      dbx_set_SysVar('dbx_modul',$modul);
  }


  public function check_design() {
    $admin =dbx_check_access('admin');
    $config=dbx_get_cfg('dbx');
    $user_default = $config['default_design_user'];  
    $admin_default= $config['default_design_admin'];
    $construct    = $config['construct'];
    $intro        = $config['intro'];
    $ok           = $config['ok'];
 
    $ok=1; // #todo  $config ok ??//

    $design        = dbx_get_Remember('dbx_design',$user_default,'parameter','dbx');      
    $page          = dbx_get_Remember('dbx_page' , 'default'    ,'parameter','dbx');
    $modul         = dbx_get_SysVar('dbx_modul'); 
    $admin_modul   = dbx_strpos($modul,'_admin');
    
    
    if (!dbx_is_design($design)) {
       $design='user';
    } else {
      if (!$admin && $design=='admin') $design='user';
      if ( $admin && $admin_modul)     $design='admin';
    }


    if ($intro && $ok) {
        $is_intro_show=dbx_get_Remember('dbx_intro',0,'int','dbx');
        if (!$is_intro_show) {
          $design=$user_default; 
          $modul='dbxIntro';
          $page  ='intro';
          $action='run';     
        }
        dbx_set_Remember('dbx_intro',1,'dbx');
    }

    if (!$modul) {
        $design=$user_default;
        $modul='dbxHome';
        $page='home';
        $action='run';
    }

    if (!$ok) {
      dbx_set_SysVar('dbx_install',1);
      $modul ='dbxInstall';
      $design='_install';
      $page  ='install';     
      $action='run';
    }

    if ($design=='admin') $design=$admin_default;
    if ($design=='user')  $design=$user_default;

    dbx_set_SysVar('dbx_modul'  ,$modul);
    dbx_set_SysVar('dbx_design' ,$design);
    dbx_set_SysVar('dbx_page'   ,$page);
    $ajax=dbx_get_SysVar('dbx_ajax',0);
        
    //dbx_debug("##->check-design##=($design) Modul=($modul)Page=($page) Intro($intro) Ajax=($ajax)");                           
}


  public function check_lng() {
    $config=dbx_get_cfg('dbx');
    $lng_default   =$config['default_lng'];
    $lng_accessible=$config['accessible_lng'];
    $lng=dbx_get_SysVar('dbx_lng',dbx_get_Remember('dbx_lng',$lng_default,'*','dbx'));
    if ($lng != $lng_default) {
      $ok=0;
      if (!is_array($lng_accessible)) $lng_accessible = explode(",", $lng_accessible);
      foreach ($lng_accessible as $no => $val) {
        if ($lng == $val) $ok=1;               
      }
      if (!$ok) $lng=$lng_default;
    }
    dbx_set_Remember('dbx_lng',$lng,'dbx');
  }

  public function translate($content,$lng=''){
    if (!$lng) $lng=dbx_get_SysVar('dbx_lng','de');
    $dir_file=dbx_get_base_dir().'dbx/translate.php';
    if (file_exists($dir_file)) {
        include $dir_file;
    }
    return $content;
  }




  public function del_add_css($content) {
    $css_content='';
    if (isset($_SESSION['dbx']['add_css'])) {
      $xcss=$_SESSION['dbx']['add_css'];
      if (is_array($xcss)) {
        foreach ($xcss as $no => $css) {  $css_content.=$css."\n"; }
      }
    }
    $content = (str_replace('[dbx:add_css]',$css_content,$content));
    return $content;
  }

   public function del_add_js($content) {
      $js_content='';
      if (isset($_SESSION['dbx']['add_js'])) {
        $xjs=$_SESSION['dbx']['add_js'];
        if (is_array($xjs)) {
          foreach ($xjs as $no => $js) {  $js_content.=$js."\n";         }
        }
      }
      $content = (str_replace('[dbx:add_js]',$js_content,$content));
      return $content;
   }


  public function rep_icons($content) {
      //include dbx_get_base_dir().'dbx/rep_icons.php';
      return $content;
   }


   public function out_filter($content) {
       include dbx_get_base_dir().'dbx/out_filter.php';
       return $content;
    }


   public function add_norep($content) {
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


   function get_session_rec($fld,$default='') {
      $value=$default;
      if ($fld != '*') {
        if (isset($_SESSION['dbx']['record'][$fld])) {
           $value=$_SESSION['dbx']['record'][$fld];
        }
      } else {
        $value=$_SESSION['dbx']['record'];
      }
      return $value;
   }

  function design_load($modul_content) {
    $ajax  =dbx_get_SysVar('dbx_ajax');
    $window=dbx_get_SysVar('dbx_window');
    
    $design=dbx_get_SysVar('dbx_activ_design',dbx_get_SysVar('dbx_design'));
    $page  =dbx_get_SysVar('dbx_activ_page'  ,dbx_get_SysVar('dbx_page'));
    $lng   =dbx_get_SysVar('dbx_activ_lng'   ,dbx_get_SysVar('dbx_lng'));
 
    //dbx_debug ("##DBX-CAll AJAX=($ajax) Get Ajax=($get_ajax) POST/GET",$_POST,$_GET);

    if (!$ajax)  {
        if ($window) $page='_window';
        $oTPL=dbx_get_sys_object('dbxTPL');
        $content=$oTPL->get_design_tpl($design,$page,$lng,'htm',1);
        $content = (str_replace("[dbx:content]",$modul_content,$content));
    } else {
        $content=$modul_content;
    }
    return $content;
  }

   // - - - - - - - - - - - - - - - - - - - - -

   function run() {
      $content='';
      $uid     =dbx_get_CurrentUser(); 
      $self    =dbx_get_self_url();
      $design = dbx_get_SysVar('dbx_design');
      $page   = dbx_get_SysVar('dbx_page');
      $lng    = dbx_get_SysVar('dbx_lng');
      $edit   = dbx_get_SysVar('dbx_edit');
      $color  = dbx_get_SysVar('dbx_color');
      $modul  = dbx_get_SysVar('dbx_modul');
      $action = dbx_get_SysVar('dbx_action');
      $api    = dbx_get_SysVar('dbx_api');
      $cid    = dbx_get_SysVar('dbx_cid',0);
      $install= dbx_get_SysVar('dbx_install',0);

      //$oInterpreter = dbx_get_sys_object('dbxInterpreter');

      if ($api && $uid != -3) dbx_login(-3);

      dbx_debug("#WebApp run-> Design=($design) Page=($page) Modul=($modul) Action=($action) Api=($api) Install=($install) User=($uid)  Lng=($lng) Self=($self)");
      
      if ($modul=='dbxMenu') {
        $getmodul=dbx_get_PostGetVar('dbx_modul','undeff');
        //dbx_debug("#WebApp run-> Error Modul=($modul) ($getmodul)",$_SESSION['dbx']['tmp']);
      }

      if ($modul) {
        $access=dbx_check_modul_access($modul);
        if (!$access) {
            $perma=dbx_get_SysVar('dbx_permalink');
            if ( $perma && !$uid) dbx_set_Remember('dbx_redir_after_login',$perma,'dbx');
            if (!$perma && !$uid) dbx_set_Remember('dbx_redir_after_login',"?dbx_modul=$modul&dbx_action=$action",'dbx');

            $content="<div class=\"alert alert-warning\" role=\"alert\">Kein Zugriff auf Modul ($modul) !</div>";
            if (!$uid) $content.='[modul=dbxLogin]dbx_action=run[/modul]';
        }
        if ($access) {

          dbx_set_SysVar('dbx_master_modul' , $modul);  // use 4 session
          dbx_set_SysVar('dbx_master_action',$action);  // use 4 session
          dbx_set_SysVar('dbx_activ_modul'  , $modul);  // use 4 session
          dbx_set_SysVar('dbx_activ_action' ,$action);  // use 4 session

          $dbxModul=dbx_get_Modul_object($modul);
          $mid=dbx_get_SysVar('dbx_activ_modul_id',0,'*');

          dbx_set_SysVar('dbx_activ_modul_id',$mid);  
          dbx_set_ModulVar('dbx_modul_id',$mid); 
          dbx_set_ModulVar('dbx_modul'   ,$modul);
          dbx_set_ModulVar('dbx_action'  ,$action);
          dbx_set_ModulVar('dbx_cid'     ,$cid);     // come from permalink check()

          $content=$dbxModul->run();


          if ($api) dbx_set_SysVar('dbx_page','api');

        }        

      }
      $mid=dbx_get_SysVar('dbx_activ_modul_id');
      $go =dbx_get_SysVar('dbx_go',0,'parameter');
      if ($go) { 
          $js ='$(document).ready(function() { ';
          $js.= "dbxGoTo('$go');";
          $js.=' } );';
          $js.="\n";
          $content.="<br><script>$js</script><div id=dbx_end></div>";
      }  
      //dbx_debug("#WebApp load-> Modul=($modul) Action=($action) Access=($access) Modul-id=($mid) go =($go)");

      return $content;
   }


} // class
