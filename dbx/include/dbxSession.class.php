<?php

require_once __DIR__ . '/dbxPasswordPolicy.class.php';

/**
 * @brief Verwaltet Sitzungszustand, Persistenz und Lebenszyklus einer dbxapp-Sitzung.
 */
class dbxSession {

  Private function get_request() {
     $request='';
     if (isset($_SERVER['REQUEST_URI']))  $request=$_SERVER['REQUEST_URI'] ;
     return $request;
  }


  Private function get_new_session_id($uid) {
    $s1=microtime(true);
    $s2=dbxPasswordPolicy::generate(8);
    $sid=md5($uid.'|'.$s1.'|'.$s2);
    $sid=substr($sid, 0, 32);
    return $sid;
  } 

  Private function get_new_session($uid) {
    $config=dbx()->get_cfg('dbx');
    $cookie=0; if (count($_COOKIE)>0) $cookie=1;
    $o_browser =dbx()->get_system_obj('dbxBrowser');
    $sid=$this->get_new_session_id($uid);
    dbx()->debug("#dbxSession  new sessid for user($uid) =($sid)");

    $session['record']['id']    = 0;
    $session['record']['create_date'] = date('Y-m-d H:i:s');
    $session['record']['create_uid']  = $uid;
    $session['record']['update_date'] = date('Y-m-d H:i:s');
    $session['record']['update_uid']  = $uid;
    $session['record']['owner'] = $uid;
  	$session['record']['ip']    = $o_browser->_ip;
  	$session['record']['host']  = $o_browser->_host;
    $session['record']['device']= $o_browser->_device;
  	$session['record']['robot'] = $o_browser->_robot;
  	$session['record']['name']  = $o_browser->_name;
  	$session['record']['ver']   = $o_browser->_version;
  	$session['record']['os']    = $o_browser->_platform;
  	$session['record']['width'] = $o_browser->_width;
  	$session['record']['height']= $o_browser->_height;
    $session['record']['cookie']= $cookie;
    $session['record']['userid']= $uid;
    $session['record']['sessid']= $sid;

    $session['record']['request_counter']= '0';
    $session['record']['request_last']   = '';
    $session['record']['request_current']= '';

    $session['current_user']=$this->get_current_user($uid);
    $session['remember']=array();
    $session['cache']['obj']=array();
  
    // - - - - - - - - - - -


    return $session;
   }



   private function apply_browser_to_record(&$rec) {
      $o_browser = dbx()->get_system_obj('dbxBrowser');

      $rec['ip']     = $o_browser->_ip;
      $rec['host']   = $o_browser->_host;
      $rec['device'] = $o_browser->_device;
      $rec['mobile'] = $o_browser->_mobile;
      $rec['robot']  = $o_browser->_robot;
      $rec['name']   = $o_browser->_name;
      $rec['ver']    = $o_browser->_version;
      $rec['os']     = $o_browser->_platform;
      $rec['width']  = $o_browser->_width;
      $rec['height'] = $o_browser->_height;
      $rec['cookie'] = count($_COOKIE)>0 ? 1 : 0;
   }



   public function get_current_user($uid = 0) {
    // #todo cfg ob user 1 (guest) db oder nicht !
    $current_user = [
        'id'       => 0,
        'uname'    => 'guest',
        'roles'    => 'guest',
        'email'    => '',
        'name'     => 'Guest',
        'design'   => 'default',
        'color'    => 'default',
        'language' => 'de',
        'edit'     => 0
    ];

    if ($uid) {
        $db       = dbx()->get_system_obj('dbxDB');
        $user_rec = $db->select1('dbxUser', $uid , verify_access: 0);

        if (!is_array($user_rec) || empty($user_rec['id'])) {
            $user_rec = array();
        }

        if (is_array($user_rec) && !empty($user_rec['id'])) {
            $current_user = [
                'id'       => (int)$user_rec['id'],
                'uname'    => $user_rec['uname'],
                'roles'    => $user_rec['roles'],
                'email'    => $user_rec['email'],
                'name'     => $user_rec['name'],
                'design'   => $user_rec['design'],
                'color'    => $user_rec['color'],
                'language' => $user_rec['language'],
                'edit'     => $user_rec['edit']
            ];
        }
    }

    dbx()->debug("###CURRENT-USER###", $current_user);
    return $current_user;
}



  /**
   * Summary of load_session
   * @return void
   */
  public function load_session() {
    dbx()->timer('session-browser','Session browser');
    $sok=false;
    $o_browser=dbx()->get_system_obj('dbxBrowser');
    $ip  = $o_browser->_ip;
    $host= $o_browser->_host;
    if (isset($_SESSION['dbx']['record'])) {
        if (isset($_SESSION['dbx']['current_user']['id'])) {
          $sok=1;    
          if ($_SESSION['dbx']['record']['ip']   != $ip  ) $sok=false;
          if ($_SESSION['dbx']['record']['host'] != $host) $sok=false;
       }
    }
    

    if (!$sok) {
      dbx()->timer('session-new','Session create new');
      $_SESSION['dbx'] =$this->get_new_session(0);
      dbx()->debug("#### Create new session #### ");
      dbx()->timer('session-new');
    }
    $this->clean_session(); 
     


    dbx()->timer('session-browser');
  }
  public function clean_session() {
     $cache = dbx()->get_cfg('dbx','cache');
     //$cache2 = dbx()->get_system_var('dbx_cache',0);
     //dbx_debug("##clear session cache=($cache)");
     $_SESSION['dbx']['norep']=array();
     if (!$cache) {
      //dbx_debug("clear cache config");
      $_SESSION['dbx']['config']=array();
      $_SESSION['dbx']['cache']=array();
     }
  }

  public function save_session($rec=1) {
    $session_db=dbx()->get_cfg('dbx','session_db');
    $session_db_guest=(int)dbx()->get_cfg('dbx','session_db_guest',0) === 1;
    $uid=(int)dbx()->user();
    $existing_id=(int)($_SESSION['dbx']['record']['id'] ?? 0);
    if ($session_db && !$session_db_guest && $uid <= 0 && $existing_id <= 0) {
      // PHP-Session, Warenkorb, Sprache und Workflow-Gastbindung bleiben
      // unveraendert erhalten; lediglich der zentrale Session-DB-Datensatz
      // wird fuer anonyme Seitenaufrufe nicht pro Request geschrieben.
      dbx()->debug('#SESSION db guest write skipped');
      return;
    }
    dbx()->debug("#SESSION db write =($session_db)");
    if ($session_db) {


      $install=dbx()->get_system_var('dbx_install',0); 
    
      $design =dbx()->get_system_var('dbx_activ_design','default');
      $page   =dbx()->get_system_var('dbx_activ_page','default');

      $modul  =dbx()->get_system_var('dbx_master_modul');
      $run1   =dbx()->get_system_var('dbx_master_action');
      $run2   =dbx()->get_system_var('dbx_run2', '');
      $uid    =dbx()->user();
      
    
      if (isset($_SESSION['dbx']['record'])) {
        $_SESSION['dbx']['record']['request_counter'] = ((int)($_SESSION['dbx']['record']['request_counter'] ?? 0)) + 1;
        $_SESSION['dbx']['record']['request_last']    = (string)($_SESSION['dbx']['record']['request_current'] ?? '');
        $_SESSION['dbx']['record']['request_current'] = $this->get_request();
        $rec=$_SESSION['dbx']['record'];
        $id  =(int)($rec['id'] ?? 0);
      } else {
        $rec=array();
        $id = 0;
      }

      //$this->apply_browser_to_record($rec);

      $rec['update_date']=date('Y-m-d H:i:s');
      $rec['create_uid'] =$uid;
      $rec['update_uid'] =$uid;
      $rec['owner']      =$uid;
      $rec['userid']     =$uid;
      $rec['design']     =$design;
      $rec['page']       =$page;
      $rec['modul']      =$modul;
      $rec['run1']       =$run1;
      $rec['run2']       =$run2;
      $rec['color']      =dbx()->get_system_var('dbx_color','default');
      $rec['language']   =dbx()->get_system_var('dbx_lng'  ,'de');
      $rec['edit']       =dbx()->get_system_var('dbx_edit' ,'0');



      if (!$install && $session_db) {
        $o_db=dbx()->get_system_obj('dbxDB');

        //dbx_debug("#### SESSION SAVE ID=($id) User=($uid)  rec=($id) #####",$rec);

        $sessid = (string)($rec['sessid'] ?? '');

        if ($id) {
            $ok=$o_db->update('dbxSession',$rec,$id,0,1,0,0);
            if (!$ok) {
              $id=0;
            } elseif ((int)($o_db->_update_count ?? 0) === 0 && (int)$o_db->count('dbxSession', "id=" . (int)$id) <= 0) {
              $id=0;
            }
        }

        if (!$id && $sessid !== '') {
          $server = $o_db->get_dd_server('dbxSession');
          $where  = "sessid='" . $o_db->escape($sessid, $server) . "'";
          $old    = $o_db->select1('dbxSession', $where, array('id'), 0);
          $old_id  = is_array($old) ? (int)($old['id'] ?? 0) : 0;

          if ($old_id > 0) {
            $ok=$o_db->update('dbxSession',$rec,$old_id,0,1,0,0);
            if ($ok) $id=$old_id;
          }
        }

        if (!$id) { // new session
          $ok=$o_db->insert('dbxSession',$rec,0,1,0,0);
          $id=(int)$o_db->_insert_id;
        }
      }

      $rec['id'] = (int)$id;
      if (!$session_db_guest && (int)$uid <= 0) {
        // Nach einem Logout wird der vorhandene Datensatz einmalig auf Gast
        // gesetzt. Danach entstehen fuer diese Gast-Session keine Folgewrites.
        $rec['id'] = 0;
      }
      $_SESSION['dbx']['record']=$rec;
      dbx()->debug("#SESSIOM-PHP-ID",$_SESSION['dbx']['record']['id']);
    } // session_db
  }

  /**
   * Erkennt eine fluechtige Robot-Session ohne eingehenden Session-Cookie.
   *
   * Eine IP-Adresse ist absichtlich kein Session-Schluessel: NAT, Proxies und
   * wechselnde Mobilfunkadressen wuerden sonst getrennte Gaeste vermischen
   * oder bestehende Warenkoerbe verlieren.
   */
  public function is_ephemeral_anonymous_session_request(bool $public_cache_hit = false): bool {
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, array('GET', 'HEAD'), true)) {
      return false;
    }
    if ((int)dbx()->user() > 0) {
      return false;
    }

    $session_name = session_name();
    $incoming_session_id = $session_name !== ''
      ? trim((string)($_COOKIE[$session_name] ?? ''))
      : '';
    if ($incoming_session_id !== '') {
      $active_session_id = trim((string)session_id());
      if ($active_session_id !== '' && hash_equals($active_session_id, $incoming_session_id)) {
        return false;
      }
    }

    // Ein Full-Page-Cache-Hit ist bereits als unpersonalisiert und tokenfrei
    // verifiziert. Seine kurz gestartete PHP-Session wird niemals gebraucht.
    if ($public_cache_hit) {
      return true;
    }

    $browser = dbx()->get_system_obj('dbxBrowser');
    return is_object($browser) && (int)($browser->_robot ?? 0) === 1;
  }

  /**
   * Entfernt die nur fuer den aktuellen Robot-Request benoetigte PHP-Session.
   *
   * Rueckgabe true bedeutet, dass save_session()/clean_session() fuer diesen
   * Request nicht mehr ausgefuehrt werden duerfen.
   */
  public function discard_ephemeral_anonymous_session(bool $public_cache_hit = false): bool {
    if (!$this->is_ephemeral_anonymous_session_request($public_cache_hit)) {
      return false;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
      $_SESSION = array();
      session_destroy();
    }

    if (!headers_sent()) {
      // session_start() hat fuer den cookie-losen Bot bereits eine PHPSESSID
      // vorgemerkt. Der fluechtige Request darf sie nicht ausliefern.
      header_remove('Set-Cookie');
    }

    dbx()->debug($public_cache_hit
      ? '#SESSION ephemeral anonymous full-page-cache guest discarded'
      : '#SESSION ephemeral anonymous robot discarded');
    return true;
  }



   public function logout($userid,$kill=1) {
       dbx()->debug("#SESSION# LOGOUT Kill=($kill) userid=($userid)");
       dbx()->invalidate_action_tokens();

       if (isset($_SESSION['dbx']['record'])) {
          $_SESSION['dbx']['record']['owner']  = 0;
          $_SESSION['dbx']['record']['userid'] = 0;
       }
       $_SESSION['dbx']['current_user']=$this->get_current_user(0);

       if ($kill) $GLOBALS['dbx_session_destroy_pending'] = 1;
   }

   public function destroy_php_session() {
       $GLOBALS['dbx_session_destroyed'] = 1;

       if (session_status() === PHP_SESSION_ACTIVE) {
          $_SESSION = array();

          if (ini_get('session.use_cookies')) {
             $params = session_get_cookie_params();
             setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'] ?? '/',
                $params['domain'] ?? '',
                $params['secure'] ?? false,
                $params['httponly'] ?? false
             );
          }

          session_destroy();
       }

       dbx()->delete_cookie('dbXwebApp');
   }

   public function login($uid,$remember=0) {
      dbx()->debug("#SESSION# LOGIN");
      if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
         session_regenerate_id(true);
      }
      dbx()->invalidate_action_tokens();
      $new_sessid  =$this->get_new_session_id($uid);
      $current_user=$this->get_current_user($uid);
      $_SESSION['dbx']['record']['owner']      = $uid;
      $_SESSION['dbx']['record']['userid']     = $uid;
      $_SESSION['dbx']['record']['update_uid'] = $uid;
      $_SESSION['dbx']['record']['create_uid'] = $uid;
      $_SESSION['dbx']['record']['sessid']     = $new_sessid;
      $_SESSION['dbx']['current_user']         = $current_user;
      //$_SESSION=array();
      if ($remember) $_SESSION['dbx']['cookie']['dbXwebApp']['session_id']=$new_sessid; // #ToDo
      dbx()->debug("#SESSION# LOGIN ($uid) Sessid=($new_sessid)",);

      }


} // Class
