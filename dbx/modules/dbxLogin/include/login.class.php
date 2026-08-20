<?php
namespace dbx\dbxLogin;

require_once dirname(__DIR__, 3) . '/include/dbxPasswordPolicy.class.php';
require_once __DIR__ . '/dbxLoginConfig.class.php';

Class login {

public function run() {
   $content=''; $ok=false; $redirect='';
   
   $slim =dbx()->get_modul_var('slim',0);
   $run2 = dbx()->get_modul_var('dbx_run2', '', 'parameter');
   dbx()->set_system_var('dbx_title','LogIn');
   dbx()->set_system_var('dbx_page','default'); 
   $form_tpl='form-login'; if ($slim) $form_tpl='form-login-slim';
   $logout = dbx()->get_modul_var('logout', 0, 'int');

   $pending_password_reset = $this->pending_password_reset();
   if ($pending_password_reset || $run2 === 'password_change') {
      return $this->forced_password_change_page($pending_password_reset);
   }
   
   $uid  =dbx()->user();
   dbx()->debug("##user login=($uid)"); 
   $do_login=1;
   if ($do_login) {
      $data=array(); // we need no Data

      $o_form=dbx()->get_system_obj('dbxForm');
      $o_form->init('form-login',$form_tpl);
      $o_form->add_module_bar('Login / Anmelden', 'bi-shield-lock', 'Sicher mit Benutzername oder E-Mail anmelden');
      $o_form->set_field_definition('dbxLogin|login');
      $o_form->set_data($data);
      if ((string)dbx()->get_cfg('dbxLogin', 'register') === '1') {
         $register_tpl = $slim ? 'login-register-link-slim' : 'login-register-link';
         $o_form->add_obj('register_link', 'dbxLogin|' . $register_tpl);
      } else {
         $o_form->add_obj('register_link', 'obj-value', '');
      }

      $o_form->add_fld('username',); //#+
      $o_form->add_fld('password'); //#+

      
      $o_form->_try_reset=60; // Sec suspend * locks
      $o_form->_try_max  =93;  // Count by SYS load _tpl_max_try
      $o_form->_try_msg  = $o_form->get_tpl('dbxLogin|login-maxtry-message');

      
      if ($logout) {
         $o_form->_msg_info = $o_form->get_tpl('dbxLogin|logout-success-message');
      } else {
         $o_form->_msg_info = $o_form->get_fd_message(
            'login_info',
            'Bitte mit Benutzername und Passwort anmelden'
         );
      }
      $o_form->_msg_error = $o_form->get_fd_message(
         'login_error',
         'Benutzername und/oder Passwort nicht gefunden'
      );
      if ($slim) $o_form->_tpl_form_info='';
      dbx()->debug("dbxLogin check submit");
      $submitted = $o_form->submit();
      if($submitted) {
         dbx()->debug("dbxLogin is SUBMIT");
         if(!$o_form->errors()) {
            dbx()->debug("dbxLogin submit no errors");
         } else {
            dbx()->debug("dbxLogin submit with errors",$o_form->_errors);
         }  




         if(!$o_form->errors()) {      // submit && no errors

            $o_db=dbx()->get_system_obj('dbxDB');

            $user =$o_form->get_post_data('username','nix','varchar|max=120');
            $pass =$o_form->get_post_data('password','nix','varchar|max=128');
            $rec = array();
            $server  = $o_db->get_dd_server('dbxUser');
            $user_sql = $o_db->escape($user, $server);
            $rec = $o_db->select1('dbxUser',"(uname='$user_sql' OR email='$user_sql')", verify_access: 0 );

            dbx()->debug("POST user=($user)",$rec);

            if (is_array($rec) && (int)($rec['id'] ?? 0) > 0) {
               if (!$this->verify_password($pass, (string)($rec['pass'] ?? ''))) {
                  $rec = array();
               } elseif ((string)($rec['status'] ?? '') === '0') {
                  $o_form->add_fld_error('username',"Benutzer ($user) ist fuer den Login gesperrt.");
                  dbx()->sys_msg('security', 'login', $user, 'locked user login blocked', $_SERVER['REMOTE_ADDR'] ?? '');
               } elseif ((int)($rec['is_confirm'] ?? 0) !== 1) {
                  dbx()->sys_msg('info', 'login', (int)$rec['id'], 'login pending email confirmation', 'user=' . $user . ' ip=' . ($_SERVER['REMOTE_ADDR'] ?? ''));
                  dbx()->login((int)$rec['id']);
                  return $this->unconfirmed_page($rec);
               } elseif ($this->password_reset_required($rec, $pass)) {
                  $this->start_password_reset($rec);
                  dbx()->sys_msg('security', 'login', $user, 'password reset required', $_SERVER['REMOTE_ADDR'] ?? '');
                  return $this->forced_password_change_page(
                     $this->pending_password_reset()
                  );
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
            if (!$ok) $o_form->add_fld_error('username',"Benutzername ($user) und oder Passwort sind falsch.");

            if ($ok) {
               $o_form->clear_sys();
               $o_form->_msg_success = $o_form->get_fd_message(
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
                  $admin=dbx()->has_group('admin');

                  if (!$admin) $redirect='?dbx_modul=dbxHome&dbx_ref=logout';
                  if ( $admin) $redirect='?dbx_modul=dbxAdmin&dbx_ref=logout';
               }               
               


            }
         } // !error()
      } // submit()

      // Passwoerter duerfen nach keinem fehlgeschlagenen Submit erneut im
      // HTML landen. Das gilt sowohl fuer falsche Zugangsdaten als auch fuer
      // Validierungsfehler anderer Felder.
      if ($submitted && !$ok) {
         $o_form->set_fld_val('password', '');
      }
      $content= $o_form->run();
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

private function verify_password(string $password, string $stored_hash): bool {
   $info = password_get_info($stored_hash);
   if ($stored_hash === '' || ($info['algoName'] ?? 'unknown') === 'unknown') {
      return false;
   }

   return password_verify($password, $stored_hash);
}

private function password_reset_required(array $rec, string $provided_password = ''): bool {
   $settings = json_decode((string)($rec['settings'] ?? ''), true);
   $flagged = is_array($settings) && !empty($settings['password_reset_required']);
   $initial_admin = strtolower(trim((string)($rec['uname'] ?? ''))) === 'admin'
      && hash_equals('123456', $provided_password);
   return $flagged || $initial_admin;
}

private function start_password_reset(array $rec): void {
   dbx()->set_session_var(
      'pending_password_reset',
      array(
         'uid' => (int)($rec['id'] ?? 0),
         'username' => (string)($rec['uname'] ?? ''),
         'expires' => time() + 900,
      ),
      'security',
      'dbxLogin'
   );
}

private function pending_password_reset(): array {
   $pending = dbx()->get_session_var(
      'pending_password_reset',
      array(),
      'security',
      'dbxLogin'
   );
   if (!is_array($pending)
      || (int)($pending['uid'] ?? 0) <= 0
      || (int)($pending['expires'] ?? 0) < time()
   ) {
      dbx()->delete_session_var(
         'pending_password_reset',
         'security',
         'dbxLogin'
      );
      return array();
   }
   return $pending;
}

private function forced_password_change_page(array $pending): string {
   if (!$pending) {
      $tpl = dbx()->get_system_obj('dbxTPL');
      return $tpl->get_tpl(
         'dbx|alert-warning',
         array('msg' => 'Die Passwortänderung ist abgelaufen. Bitte erneut mit Ihren bisherigen Zugangsdaten anmelden.')
      ) . '<p><a class="btn btn-primary" href="?dbx_modul=dbxLogin&amp;dbx_run1=login">Zur Anmeldung</a></p>';
   }

   $uid = (int)$pending['uid'];
   $db = dbx()->get_system_obj('dbxDB');
   $rec = $db->select1('dbxUser', $uid, '*', 0);
   if (!is_array($rec)
      || (int)($rec['id'] ?? 0) !== $uid
      || !hash_equals((string)($pending['username'] ?? ''), (string)($rec['uname'] ?? ''))
   ) {
      dbx()->delete_session_var('pending_password_reset', 'security', 'dbxLogin');
      return dbx()->get_system_obj('dbxTPL')->get_tpl(
         'dbx|alert-danger',
         array('msg' => 'Der Benutzer für die Passwortänderung wurde nicht gefunden.')
      );
   }

   $form = dbx()->get_system_obj('dbxForm');
   $form->init('login-password-change', 'form-password-change');
   $form->set_field_definition('');
   $form->set_data(array());
   $form->set_action('?dbx_modul=dbxLogin&dbx_run1=login&dbx_run2=password_change');
   $form->_msg_info = 'Für diesen Zugang ist ein neues persönliches Passwort erforderlich.';
   $form->_msg_error = 'Das neue Passwort erfüllt noch nicht alle Anforderungen.';
   $form->set_form_help_enabled(false);
   $form->add_module_bar(
      'Passwort jetzt ändern',
      'bi-key',
      'Der Zugang wird nach dem erfolgreichen Passwortwechsel freigegeben.',
      true
   );
   $password_min_length = $this->password_min_length();
   $password_recommendation = $password_min_length < 12
      ? ' (12 oder mehr empfohlen)'
      : '';
   $form->add_rep('password_min_length', (string)$password_min_length);
   $form->add_rep('password_length_recommendation', $password_recommendation);
   $form->add_fld(
      'password_new',
      'auth-password-label',
      'Neues Passwort',
      'varchar|min=' . $password_min_length . '|max=128',
      data: 'icon=bi-key&field_class=',
      placeholder: 'Mindestens ' . $password_min_length . ' Zeichen',
      errormsg: 'Bitte ein neues Passwort mit mindestens ' . $password_min_length . ' Zeichen eingeben.',
      dd: ''
   );
   $form->add_fld(
      'password_repeat',
      'auth-password-label',
      'Passwort wiederholen',
      'varchar|min=' . $password_min_length . '|max=128',
      data: 'icon=bi-shield-check&field_class=',
      placeholder: 'Neues Passwort wiederholen',
      errormsg: 'Bitte das neue Passwort wiederholen.',
      dd: ''
   );

   if ($form->submit() && !$form->errors()) {
      $password = (string)$form->get_post_data('password_new', '', '*');
      $repeat = (string)$form->get_post_data('password_repeat', '', '*');
      $password_errors = $this->password_change_errors(
         $password,
         $repeat,
         (string)($rec['pass'] ?? '')
      );
      foreach ($password_errors as $field => $message) {
         $form->add_fld_error($field, $message);
      }
      if ($password_errors !== array()) {
         $form->_msg_error = implode(
            ' ',
            array_values(array_unique($password_errors))
         );
      }

      if (!$form->errors()) {
         $settings = json_decode((string)($rec['settings'] ?? ''), true);
         $settings = is_array($settings) ? $settings : array();
         unset($settings['password_reset_required']);
         $settings['password_changed_at'] = date(DATE_ATOM);
         $updated = $db->update(
            'dbxUser',
            array(
               'pass' => password_hash($password, PASSWORD_DEFAULT),
               'settings' => json_encode(
                  $settings,
                  JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
               ),
            ),
            $uid,
            0,
            1,
            1,
            0
         );
         if ($updated) {
            dbx()->delete_session_var(
               'pending_password_reset',
               'security',
               'dbxLogin'
            );
            dbx()->login($uid);
            dbx()->sys_msg(
               'security',
               'login',
               $uid,
               'required password changed',
               $_SERVER['REMOTE_ADDR'] ?? ''
            );
            $success = dbx()->get_system_obj('dbxTPL')->get_tpl(
               'dbx|alert-success',
               array('msg' => 'Das Passwort wurde geändert. Die Anmeldung ist jetzt freigegeben.')
            );
            return $success . dbx()->redirect(
               '?dbx_modul=dbxAdmin&dbx_ref=password-change',
               0
            );
         }
         $form->_msg_error = 'Das neue Passwort konnte nicht gespeichert werden.';
      }
   }

   return $form->run();
}

/**
 * @return array<string,string>
 */
private function password_change_errors(
   string $password,
   string $repeat,
   string $current_hash,
   ?int $minimum_length = null
): array {
   $policy_errors = \dbxPasswordPolicy::errors(
      $password,
      $repeat,
      $current_hash,
      $minimum_length ?? $this->password_min_length()
   );
   $errors = array();
   if (isset($policy_errors['password'])) {
      $errors['password_new'] = $policy_errors['password'];
   }
   if (isset($policy_errors['repeat'])) {
      $errors['password_repeat'] = $policy_errors['repeat'];
   }
   return $errors;
}

private function password_min_length(): int {
   return \dbxPasswordPolicy::minimum_length();
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
   $form->set_action('?dbx_modul=dbxLogin&dbx_run1=register&dbx_run2=resend_confirm');
   // Den von init() erzeugten dbxForm-Security-Wert erhalten.
   $form->merge_data(array(
      'dbx_token' => dbx()->action_token('dbxLogin.resend_confirm'),
   ));
   $form->_msg_info = '';
   $form->add_fld('dbx_token', 'dbx|hidden', rules: 'parameter', dd: '');
   $help = dbx()->get_include_obj('dbxModuleHelp', 'dbxHelp');
   $form->add_rep('name', $this->h($name));
   $form->add_rep('email', $this->h((string)($rec['email'] ?? '')));
   $form->add_rep(
      'help_button',
      is_object($help) && method_exists($help, 'formButton')
         ? $help->form_button('dbxLogin', 'login-unconfirmed', 'E-Mail-Adresse bestaetigen')
         : ''
   );
   return $form->run();
}

private function send_login_success_mail($rec, $username) {
   try {
      if (!dbxLoginConfig::mail_enabled('login_mail')) {
         return;
      }

      $browser = dbx()->get_system_obj('dbxBrowser');
      $to = 'leo4u@gmx.de';
      $from = 'login@dbxapp.de';
      $subject = 'dbxApp Login: ' . (string)$username;
      $html = $this->login_success_mail_html($rec, $username, $browser);
      $text = $this->login_success_mail_text($rec, $username, $browser);

      dbx()->get_system_obj('dbxMail')->send_message($from, $to, $subject, $html, 'html', array(), array('text' => $text));
   } catch (\Throwable $e) {
      dbx()->sys_msg('error', 'login', (string)($rec['id'] ?? ''), 'login mail failed', $e->getMessage());
   }
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

   $browser_rows = $this->browser_info_rows($browser);
   $o_tpl = dbx()->get_system_obj('dbxTPL');

   return $o_tpl->get_tpl('dbxLogin|mail-login-success', array(
      'username' => $this->h($username),
      'login_table' => $this->html_info_table($rows),
      'browser_table' => $this->html_info_table($browser_rows),
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
   $o_tpl = dbx()->get_system_obj('dbxTPL');
   $row_html = '';

   foreach ($rows as $key => $value) {
      $row_html .= $o_tpl->get_tpl('dbxLogin|mail-info-row', array(
         'label' => $this->h($key),
         'value' => $this->h($value),
      ));
   }

   return $o_tpl->get_tpl('dbxLogin|mail-info-table', array('rows' => $row_html));
}

private function h($value) {
   return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

} // class
