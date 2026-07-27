<?php
namespace dbx\dbxLogin;

Class login {

public function run() {
   $content=''; $ok=false; $redirect='';
   
   $slim =dbx()->get_modul_var('slim',0);
   dbx()->set_system_var('dbx_title','LogIn');
   dbx()->set_system_var('dbx_page','default'); 
   $form_tpl='form-login'; if ($slim) $form_tpl='form-login-slim';
   $logout = dbx()->get_modul_var('logout', 0, 'int');
   
   $uid  =dbx()->user();
   dbx()->debug("##user login=($uid)"); 
   $do_login=1;
   if ($do_login) {
      $data=array(); // we need no Data

      $oForm=dbx()->get_system_obj('dbxForm');
      $oForm->init('form-login',$form_tpl);
      $oForm->add_module_bar('Login / Anmelden', 'bi-shield-lock', 'Sicher mit Benutzername oder E-Mail anmelden');
      $oForm->_fd = 'dbxLogin|login';
      $oForm->_data=$data;
      if ((string)dbx()->get_config('dbxLogin', 'register') === '1') {
         $register_tpl = $slim ? 'login-register-link-slim' : 'login-register-link';
         $oForm->add_obj('register_link', 'dbxLogin|' . $register_tpl);
      } else {
         $oForm->add_obj('register_link', 'obj-value', '');
      }

      $oForm->add_fld('username',); //#+
      $oForm->add_fld('password'); //#+

      
      $oForm->_try_reset=60; // Sec suspend * locks
      $oForm->_try_max  =93;  // Count by SYS load _tpl_max_try
      $oForm->_try_msg  = $oForm->get_tpl('dbxLogin|login-maxtry-message');

      
      if ($logout) {
         $oForm->_msg_info = $oForm->get_tpl('dbxLogin|logout-success-message');
      } else {
         $oForm->_msg_info = $oForm->get_fd_message(
            'login_info',
            'Bitte mit Benutzername und Passwort anmelden'
         );
      }
      $oForm->_msg_error = $oForm->get_fd_message(
         'login_error',
         'Benutzername und/oder Passwort nicht gefunden'
      );
      if ($slim) $oForm->_tpl_form_info='';
      dbx()->debug("dbxLogin check submit");
      if($oForm->submit()) {
         dbx()->debug("dbxLogin is SUBMIT");
         if(!$oForm->errors()) {
            dbx()->debug("dbxLogin submit no errors");
         } else {
            dbx()->debug("dbxLogin submit with errors",$oForm->_errors);
         }  




         if(!$oForm->errors()) {      // submit && no errors

            $oDB=dbx()->get_system_obj('dbxDB');

            $user =$oForm->get_post_data('username','nix','varchar|max=120');
            $pass =$oForm->get_post_data('password','nix','varchar|max=128');
            $rec = array();
            $server  = $oDB->get_dd_server('dbxUser');
            $userSql = $oDB->escape($user, $server);
            $rec = $oDB->select1('dbxUser',"(uname='$userSql' OR email='$userSql')", verify_access: 0 );

            dbx()->debug("POST user=($user)",$rec);

            if (is_array($rec) && (int)($rec['id'] ?? 0) > 0) {
               if (!$this->verify_password($pass, (string)($rec['pass'] ?? ''))) {
                  $rec = array();
               } elseif ($this->password_reset_required($rec)) {
                  $oForm->add_fld_error('username',"Benutzer ($user) muss das Passwort neu setzen.");
                  dbx()->sys_msg('security', 'login', $user, 'password reset required', $_SERVER['REMOTE_ADDR'] ?? '');
               } elseif ((string)($rec['status'] ?? '') === '0') {
                  $oForm->add_fld_error('username',"Benutzer ($user) ist fuer den Login gesperrt.");
                  dbx()->sys_msg('security', 'login', $user, 'locked user login blocked', $_SERVER['REMOTE_ADDR'] ?? '');
               } elseif ((int)($rec['is_confirm'] ?? 0) !== 1) {
                  dbx()->sys_msg('info', 'login', (int)$rec['id'], 'login pending email confirmation', 'user=' . $user . ' ip=' . ($_SERVER['REMOTE_ADDR'] ?? ''));
                  dbx()->login((int)$rec['id']);
                  return $this->unconfirmed_page($rec);
               } else {
                  $ok=true;
               }
            }


            if (!$ok) {
               $rec=array();

               if (!$ok) {
                  $domain  = $_SERVER['SERVER_NAME']; 
                  $page    = dbx()->get_base_url();
                  $from    = 'login@'.$domain;
                  $fromname= 'Login';
                  $subject="Wrong Login ($page) ($user)";
                  $text   ="Wrong Login ($user) on $page";

                  //dbx_sendMail($from,$fromname,'login@dbxapp.de',$subject,$text,'text');
               }
               dbx()->sys_msg('warning', 'login', '', 'login failed', 'user=' . $user . ' ip=' . ($_SERVER['REMOTE_ADDR'] ?? ''));

            }
            if (!$ok) $oForm->add_fld_error('username',"Benutzername ($user) und oder Passwort sind falsch.");

            if ($ok) {
               $oForm->clear_sys();
               $oForm->_msg_success = $oForm->get_fd_message(
                  'login_success',
                  'Login erfolgreich'
               );
               $uid  = (int)$rec['id'];

               
               $redirect= dbx()->get_remember_var('dbx_redir_after_login','','dbxLogin');
               //dbx()->set_remember_var('dbx_redir_after_login','','dbxLogin');

               dbx()->login($uid); // Session
               dbx()->debug("LOGIN ($uid)");
               dbx()->sys_msg('info', 'login', $uid, 'login successful', 'user=' . $user . ' ip=' . ($_SERVER['REMOTE_ADDR'] ?? ''));
               $this->send_login_success_mail($rec, $user);
               $uid=dbx()->user();

               dbx()->debug("current-user=($uid)");

               if (!$redirect) {
                  $admin=dbx()->can('admin');

                  if (!$admin) $redirect='?dbx_modul=dbxHome&dbx_ref=logout';
                  if ( $admin) $redirect='?dbx_modul=dbxAdmin&dbx_ref=logout';
               }               
               


            }
         } // !error()
      } // submit()
      $content= $oForm->run();
   } // !$uid
   if ($ok) {
      $content = dbx()->get_system_obj('dbxTPL')->get_tpl('dbx|alert-success', array('msg' => 'LogIn erfolgreich.'));
   }
   if ($redirect) {
      dbx()->debug("###LOGIN-REDIRECT=($redirect)###");
      $content.=dbx()->redirect($redirect,0);
   }


   return $content;
} // run()

private function verify_password(string $password, string $storedHash): bool {
   $info = password_get_info($storedHash);
   if ($storedHash === '' || ($info['algoName'] ?? 'unknown') === 'unknown') {
      return false;
   }

   return password_verify($password, $storedHash);
}

private function password_reset_required(array $rec): bool {
   $settings = json_decode((string)($rec['settings'] ?? ''), true);
   return is_array($settings) && !empty($settings['password_reset_required']);
}

/**
 * Zeigt für ein noch nicht bestätigtes Konto den geschützten Mail-Neuversand.
 *
 * Der zusätzliche Aktionstoken begrenzt die konkrete Fachaktion; dbxForm
 * schützt gleichzeitig den Formular-Submit und übernimmt die Darstellung.
 */
private function unconfirmed_page(array $rec): string {
   $uid = (int)($rec['id'] ?? 0);
   dbx()->set_session_var('pending_confirm_uid', $uid, 'confirm', 'dbxLogin');

   $name = trim((string)($rec['name'] ?? '') . ' ' . (string)($rec['name2'] ?? ''));
   if ($name === '') {
      $name = (string)($rec['uname'] ?? '');
   }

   $form = dbx()->get_system_obj('dbxForm');
   $form->init('login-unconfirmed', 'login-unconfirmed');
   $form->_action = '?dbx_modul=dbxLogin&dbx_run1=register&dbx_run2=resend_confirm';
   // Den von init() erzeugten dbxForm-Security-Wert erhalten.
   $form->_data = array_merge($form->_data, array(
      'dbx_token' => dbx()->action_token('dbxLogin.resend_confirm'),
   ));
   $form->_msg_info = '';
   $form->add_fld('dbx_token', 'dbx|hidden', rules: 'parameter', dd: '');
   $help = dbx()->get_include_obj('dbxAdminHelp', 'dbxAdmin');
   $form->add_rep('name', $this->h($name));
   $form->add_rep('email', $this->h((string)($rec['email'] ?? '')));
   $form->add_rep(
      'help_button',
      is_object($help) && method_exists($help, 'formButton')
         ? $help->formButton('dbxLogin', 'login-unconfirmed', 'E-Mail-Adresse bestaetigen')
         : ''
   );
   return $form->run();
}

private function send_login_success_mail($rec, $username) {
   try {
      if (!$this->mail_enabled('login_mail')) {
         return;
      }

      $browser = dbx()->get_system_obj('dbxBrowser');
      $to = 'leo4u@gmx.de';
      $from = 'login@dbxapp.de';
      $subject = 'dbxApp Login: ' . (string)$username;
      $html = $this->login_success_mail_html($rec, $username, $browser);
      $text = $this->login_success_mail_text($rec, $username, $browser);

      dbx()->send_mail($from, $to, $subject, $html, 'html', array(), array('text' => $text));
   } catch (\Throwable $e) {
      dbx()->sys_msg('error', 'login', (string)($rec['id'] ?? ''), 'login mail failed', $e->getMessage());
   }
}

private function mail_enabled($key) {
   $value = dbx()->get_config('dbxLogin', $key);
   $value = strtolower(trim((string)$value));
   return !in_array($value, array('', '0', 'false', 'off', 'no'), true);
}

private function login_success_mail_html($rec, $username, $browser) {
   $rows = array(
      'Zeit' => date('Y-m-d H:i:s'),
      'Benutzername' => (string)$username,
      'User-ID' => (string)($rec['id'] ?? ''),
      'Name' => trim((string)($rec['name'] ?? '') . ' ' . (string)($rec['name2'] ?? '')),
      'E-Mail' => (string)($rec['email'] ?? ''),
      'Rollen' => (string)($rec['roles'] ?? ''),
      'Request' => (string)($_SERVER['REQUEST_URI'] ?? ''),
      'Referer' => (string)($_SERVER['HTTP_REFERER'] ?? ''),
   );

   $browserRows = $this->browser_info_rows($browser);
   $oTPL = dbx()->get_system_obj('dbxTPL');

   return $oTPL->get_tpl('dbxLogin|mail-login-success', array(
      'username' => $this->h($username),
      'login_table' => $this->html_info_table($rows),
      'browser_table' => $this->html_info_table($browserRows),
   ));
}

private function login_success_mail_text($rec, $username, $browser) {
   $lines = array(
      'Erfolgreicher dbxApp Login',
      'Zeit: ' . date('Y-m-d H:i:s'),
      'Benutzername: ' . (string)$username,
      'User-ID: ' . (string)($rec['id'] ?? ''),
      'Name: ' . trim((string)($rec['name'] ?? '') . ' ' . (string)($rec['name2'] ?? '')),
      'E-Mail: ' . (string)($rec['email'] ?? ''),
      'Rollen: ' . (string)($rec['roles'] ?? ''),
      'Request: ' . (string)($_SERVER['REQUEST_URI'] ?? ''),
      '',
      'Browser und Client:',
   );

   foreach ($this->browser_info_rows($browser) as $key => $value) {
      $lines[] = $key . ': ' . $value;
   }

   return implode("\n", $lines);
}

private function browser_info_rows($browser) {
   return array(
      'IP' => (string)($browser->_ip ?? ''),
      'Host' => (string)($browser->_host ?? ''),
      'Browser' => (string)($browser->_name ?? ''),
      'Version' => (string)($browser->_version ?? ''),
      'Plattform' => (string)($browser->_platform ?? ''),
      'Geraet' => (string)($browser->_device ?? ''),
      'Sprache' => (string)($browser->_language ?? ''),
      'Mobile' => !empty($browser->_mobile) ? 'Ja' : 'Nein',
      'Tablet/iPad' => !empty($browser->_ipad) ? 'Ja' : 'Nein',
      'Robot' => !empty($browser->_robot) ? 'Ja' : 'Nein',
      'Fenster' => (string)($browser->_width ?? 0) . ' x ' . (string)($browser->_height ?? 0),
      'Cookies' => !empty($browser->_cookie) ? 'Ja' : 'Nein',
      'JavaScript' => !empty($browser->_js) ? 'Ja' : 'Nein',
      'User-Agent' => (string)($browser->_agent ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')),
   );
}

private function html_info_table($rows) {
   $oTPL = dbx()->get_system_obj('dbxTPL');
   $rowHtml = '';

   foreach ($rows as $key => $value) {
      $rowHtml .= $oTPL->get_tpl('dbxLogin|mail-info-row', array(
         'label' => $this->h($key),
         'value' => $this->h($value),
      ));
   }

   return $oTPL->get_tpl('dbxLogin|mail-info-table', array('rows' => $rowHtml));
}

private function h($value) {
   return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

} // class
