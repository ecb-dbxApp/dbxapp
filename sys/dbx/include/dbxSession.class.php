<?php

/**
 * Summary of dbxSession
 */
class dbxSession {

  Private function get_request() {
     $request='';
     if (isset($_SERVER['REQUEST_URI']))  $request=$_SERVER['REQUEST_URI'] ;
     return $request;
  }


  Private function get_new_session_id($uid) {
    $s1=microtime(true);
    $s2=dbx_get_new_Pass(8);
    $sid=md5($uid.'|'.$s1.'|'.$s2);
    $sid=substr($sid, 0, 32);
    return $sid;
  } 

  Private function get_new_session($uid) {
    $config=dbx_get_cfg('dbx');
    $cookie=0; if (count($_COOKIE)>0) $cookie=1;
    $oBrowser =dbx_get_sys_object('dbxBrowser');
    $sid=$this->get_new_session_id($uid);

    $session['record']['id']    = 0;
    $session['record']['create_date'] = date('Y-m-d H:i:s');
    $session['record']['create_uid']  = $uid;
    $session['record']['update_date'] = date('Y-m-d H:i:s');
    $session['record']['update_uid']  = $uid;
    $session['record']['owner'] = $uid;
  	$session['record']['ip']    = $oBrowser->_ip;
  	$session['record']['host']  = $oBrowser->_host;
  	$session['record']['mobile']= $oBrowser->_mobile;
  	$session['record']['robot'] = $oBrowser->_robot;
  	$session['record']['name']  = $oBrowser->_name;
  	$session['record']['ver']   = $oBrowser->_version;
  	$session['record']['os']    = $oBrowser->_platform;
  	$session['record']['width'] = $oBrowser->_width;
  	$session['record']['height']= $oBrowser->_height;
    $session['record']['cookie']= $cookie;
    $session['record']['userid']= $uid;
    $session['record']['sessid']= $sid;

    $session['record']['request_counter']=0;
    $session['record']['request_last']   = '';
    $session['record']['request_current']= '';

    $session['current_user']=$this->get_current_user(0);
    $session['tmp']=array();
    $session['remember']=array();
    $session['cache']['tpl']=array();
    $session['cache']['obj']=array();
  
    // - - - - - - - - - - -


    return $session;
   }



   public function get_current_user($uid=0) {
       $current_user=array();
       $roles='guest'; $email=''; $name='Guest'; $uname='guest'; $lng='de'; $design='default'; $color='default';$edit=0;

       if ($uid) {
          $db=dbx_get_sys_object('dbxDB');
          $user_rec=$db->select1('dbx_user',"userid=$uid");
          if (is_array($user_rec)) {
             $name  =  $user_rec['name'];
             $uname =  $user_rec['uname'];
             $email =  $user_rec['email'];
             $roles =  $user_rec['roles'];
             $design=  $user_rec['design'];
             $color =  $user_rec['color'];
             $lng   =  $user_rec['language'];
             $edit  =  $user_rec['edit'];
          }
       }

       $current_user['userid']  = $uid;
       $current_user['uname']   = $uname;
       $current_user['roles']   = $roles;
       $current_user['email']   = $email;
       $current_user['name']    = $name ;
       $current_user['design']  = $design ;
       $current_user['color']   = $color ;
       $current_user['language']= $lng ;
       $current_user['edit']    = $edit ;
       //dbx_debug("###CURRENT-USER###",$current_user);
       return $current_user;
   }




  /**
   * Summary of load_session
   * @return void
   */
  public function load_session() {
    dbx_run_time('session-browser','Session browser');
    $sok=false;
    $oBrowser=dbx_get_sys_object('dbxBrowser');
    $ip  = $oBrowser->_ip;
    $host= $oBrowser->_host;
    if (isset($_SESSION['dbx']['record'])) {
        if (isset($_SESSION['dbx']['current_user']['userid'])) {
          $sok=1;    
          if ($_SESSION['dbx']['record']['ip']   != $ip  ) $sok=false;
          if ($_SESSION['dbx']['record']['host'] != $host) $sok=false;
       }
    }
    dbx_run_time('session-browser');



    if (!$sok) {
      dbx_run_time('session-new','Session create new');
      $_SESSION['dbx'] =$this->get_new_session(0);
      dbx_debug("#### Create new session #### ");
      dbx_run_time('session-new');
    }
    $this->clean_session(); 
     


    $_SESSION['dbx']['record']['request_counter'] = ($_SESSION['dbx']['record']['request_counter'] +1);
    $_SESSION['dbx']['record']['request_last']    =  $_SESSION['dbx']['record']['request_current'];
    $_SESSION['dbx']['record']['request_current'] =  $this->get_request();

  }

  public function clean_session() {
     $cache = dbx_get_cfg('dbx','cache');
     //$cache2 = dbx_get_SysVar('dbx_cache',0);
     //dbx_debug("##clear session cache=($cache)");
     $_SESSION['dbx']['tmp']  =array();
     $_SESSION['dbx']['norep']=array();
     if (!$cache) {
      //dbx_debug("clear cache config");
      $_SESSION['dbx']['config']=array();
      $_SESSION['dbx']['cache']=array();
     }
  }

  public function save_session($rec=1) {
    $session_db=dbx_get_cfg('dbx','session_db');
    if ($session_db) {


      $install=dbx_get_SysVar('dbx_install',0); 
    
      $design =dbx_get_SysVar('dbx_activ_design');
      $page   =dbx_get_SysVar('dbx_activ_page','default');

      $modul  =dbx_get_SysVar('dbx_master_modul');
      $action =dbx_get_SysVar('dbx_master_action');
      
    
      if (isset($_SESSION['dbx']['record'])) {
        $rec=$_SESSION['dbx']['record'];
        $id  =$rec['id'];
        $uid =$rec['userid'];
      } else {
        $rec=array();
        $id = 0;
        $uid=-1;
      }

      $rec['update_date']=date('Y-m-d H:i:s');
      $rec['update_uid'] =$uid;
      $rec['owner']      =$uid;
      $rec['design']     =$design;
      $rec['page']       =$page;
      $rec['modul']      =$modul;
      $rec['action']     =$action;
      $rec['color']      =dbx_get_SysVar('dbx_color','default');
      $rec['language']   =dbx_get_SysVar('dbx_lng'  ,'de');
      $rec['edit']       =dbx_get_SysVar('dbx_edit' ,'0');



      if (!$install && $session_db) {
        $oDB=dbx_get_sys_object('dbxDB');

        //dbx_debug("#### SESSION SAVE ID=($id) User=($uid) #####",$rec);

        if ($id) {
            $ok=$oDB->update('dbx_session',$rec,$id);
            if (!$ok) $id=0;
        }
        if (!$id) { // new session
          $ok=$oDB->insert('dbx_session',$rec);
          $id=$oDB->_insert_id;
        }
        $_SESSION['dbx']['record']['id']=$id;
      }

    //dbx_debug("#SESSIOM-PHP-ID",$_SESSION['dbx']['record']['id']);
    } // session_db
  }



   public function logout($userid,$kill=1) {
       $this->clean_session();
       //dbx_debug("#SESSION# LOGOUT",$_SESSION);
       if ($kill) session_destroy();
       //$_SESSION['dbx']=array();
      

   }

   public function login($userid,$remember=0) {
      $new_sessid  =$this->get_new_session_id($userid);
      $current_user=$this->get_current_user($userid);
      $_SESSION['dbx']['record']['owner']   = $userid;
      $_SESSION['dbx']['record']['userid']  = $userid;
      $_SESSION['dbx']['record']['sessid']  = $new_sessid;
      $_SESSION['dbx']['current_user']      = $current_user;
      //$_SESSION=array();
      if ($remember) dbx_set_cookie_val('dbXwebApp','session_id',$new_sessid); // #ToDo
   }


} // Class

?>
