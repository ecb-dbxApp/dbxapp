<?php
namespace dbx\dbxLogin;

class register {

   private string $ddUser = 'dbxUser';
   private string $ddGroup = 'dbxUser_groups';
   private string $pendingRole = 'registerd';
   private string $confirmedRole = 'member';

   private function user_exists($uname, $email = '') {
      $db = dbx()->get_system_obj('dbxDB');
      $server = $db->get_dd_server($this->ddUser);
      $parts = array();

      $uname = trim((string)$uname);
      if ($uname !== '') {
         $parts[] = "uname='" . $db->escape($uname, $server) . "'";
      }

      $email = trim((string)$email);
      if ($email !== '') {
         $parts[] = "email='" . $db->escape($email, $server) . "'";
      }

      if (!$parts) {
         return 0;
      }

      return $db->count($this->ddUser, implode(' OR ', $parts));
   }

   private function ensure_pending_group() {
      $db = dbx()->get_system_obj('dbxDB');
      $server = $db->get_dd_server($this->ddGroup);
      $name = $db->escape($this->pendingRole, $server);

      if ($db->count($this->ddGroup, "name='$name'") > 0) {
         return;
      }

      $db->insert($this->ddGroup, array(
         'name'        => $this->pendingRole,
         'description' => 'Registriert, E-Mail noch nicht bestaetigt',
         'active'      => 1,
      ), 0, 1, 1, 1);
   }

   private function new_token() {
      return bin2hex(random_bytes(32));
   }

   private function settings_with_token(array $settings, string $token) {
      $settings['register_confirm'] = array(
         'token_hash' => hash('sha256', $token),
         'expires'    => time() + (48 * 3600),
         'sent'       => date('Y-m-d H:i:s'),
      );
      return json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
   }

   private function decode_settings($settings) {
      $data = json_decode((string)$settings, true);
      return is_array($data) ? $data : array();
   }

   private function confirm_url($uid, $token) {
      return rtrim(dbx()->get_base_url(), '/') . '/?dbx_modul=dbxLogin&dbx_run1=register&dbx_run2=confirm&uid=' . (int)$uid . '&token=' . rawurlencode($token);
   }

   private function current_domain() {
      $domain = (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'dbxApp');
      $domain = preg_replace('/[^a-zA-Z0-9.\-_:]/', '', $domain);
      return $domain ?: 'dbxApp';
   }

   private function send_confirm_mail(array $user, string $token) {
      $url = $this->confirm_url((int)$user['id'], $token);
      $tpl = dbx()->get_system_obj('dbxTPL');
      $data = array(
         'name'        => htmlspecialchars((string)($user['name'] ?: $user['uname']), ENT_QUOTES, 'UTF-8'),
         'uname'       => htmlspecialchars((string)$user['uname'], ENT_QUOTES, 'UTF-8'),
         'confirm_url' => htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
      );

      $html = $tpl->get_tpl('dbxLogin|mail-register-confirm', $data);
      $text = "Hallo " . (string)($user['name'] ?: $user['uname']) . "\n\n"
         . "bitte bestaetige deine E-Mail-Adresse:\n" . $url . "\n\n"
         . "Erst danach erhaelt dein Konto die Rolle " . $this->confirmedRole . ".\n";

      $ok = dbx()->send_mail(
         'register@dbxapp.de',
         (string)$user['email'],
         'dbxApp Registrierung bestaetigen',
         $html,
         'html',
         array(),
         array('text' => $text)
      );

      if (!$ok) {
         dbx()->sys_msg('error', 'register', (int)$user['id'], 'register confirm mail failed', (string)$user['email']);
      }

      return $ok;
   }

   private function confirmed_roles($roles) {
      $parts = array_filter(array_map('trim', explode(',', (string)$roles)));
      $out = array();

      foreach ($parts as $role) {
         if ($role === $this->pendingRole) {
            continue;
         }
         $out[$role] = $role;
      }

      $out[$this->confirmedRole] = $this->confirmedRole;
      return implode(',', array_values($out));
   }

   private function h($value) {
      return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
   }

   private function confirm_page(string $status, string $title, string $message, string $detail, string $icon = 'bi-patch-check', string $detailIcon = 'bi-envelope-check') {
      $tpl = dbx()->get_system_obj('dbxTPL');
      return $tpl->get_tpl('dbxLogin|register-confirm', array(
         'status'      => $this->h($status),
         'kicker'      => $status === 'error' ? 'Bestaetigung' : 'Willkommen',
         'title'       => $title,
         'message'     => $message,
         'detail'      => $detail,
         'icon'        => $this->h($icon),
         'detail_icon' => $this->h($detailIcon),
      ));
   }

   private function confirm() {
      $uid = (int)dbx()->get_modul_var('uid', 0, 'int');
      $token = (string)dbx()->get_modul_var('token', '', 'varchar|max=128');

      if ($uid <= 0 || $token === '') {
         return $this->confirm_page('error', 'Bestaetigungslink ungueltig', 'Der Link konnte nicht eindeutig zugeordnet werden.', 'Bitte pruefe den Link aus der E-Mail oder registriere dich erneut.', 'bi-exclamation-triangle', 'bi-link-45deg');
      }

      $db = dbx()->get_system_obj('dbxDB');
      $user = $db->select1($this->ddUser, $uid, '*', 0);

      if (!is_array($user) || empty($user['id'])) {
         return $this->confirm_page('error', 'Benutzer nicht gefunden', 'Zu diesem Bestaetigungslink wurde kein Benutzerkonto gefunden.', 'Bitte registriere dich erneut oder wende dich an den Administrator.', 'bi-person-x', 'bi-search');
      }

      if ((int)($user['is_confirm'] ?? 0) === 1 && preg_match('/(^|,)' . preg_quote($this->confirmedRole, '/') . '(,|$)/', (string)($user['roles'] ?? ''))) {
         $name = (string)($user['name'] ?: $user['uname'] ?? '');
         return $this->confirm_page('success', 'Willkommen zurueck' . ($name ? ', ' . $name : ''), 'Deine E-Mail-Adresse ist bereits bestaetigt.', 'Dein Konto ist freigeschaltet. Du kannst dich jetzt anmelden.', 'bi-patch-check', 'bi-person-check');
      }

      $settings = $this->decode_settings($user['settings'] ?? '');
      $confirm = $settings['register_confirm'] ?? array();
      $hash = (string)($confirm['token_hash'] ?? '');
      $expires = (int)($confirm['expires'] ?? 0);

      if ($hash === '' || !hash_equals($hash, hash('sha256', $token))) {
         return $this->confirm_page('error', 'Bestaetigungslink ungueltig', 'Der Token passt nicht zu diesem Benutzerkonto.', 'Bitte nutze den neuesten Link aus deiner E-Mail oder registriere dich erneut.', 'bi-exclamation-triangle', 'bi-shield-x');
      }

      if ($expires > 0 && $expires < time()) {
         return $this->confirm_page('error', 'Bestaetigungslink abgelaufen', 'Dieser Link ist nicht mehr gueltig.', 'Bitte registriere dich erneut oder fordere einen neuen Link an.', 'bi-hourglass-split', 'bi-clock-history');
      }

      unset($settings['register_confirm']);
      $settingsJson = $settings ? json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '{}';
      $ok = $db->update($this->ddUser, array(
         'roles'      => $this->confirmed_roles($user['roles'] ?? ''),
         'is_confirm' => 1,
         'settings'   => $settingsJson,
      ), $uid, 0, 1, 1, 1);

      if ($ok === 1) {
         dbx()->sys_msg('info', 'register', $uid, 'user email confirmed', (string)($user['uname'] ?? ''));
         $name = (string)($user['name'] ?: $user['uname'] ?? '');
         return $this->confirm_page('success', 'Willkommen' . ($name ? ', ' . $name : ''), 'Deine E-Mail-Adresse ist bestaetigt.', 'Dein Konto ist jetzt als member freigeschaltet. Du kannst dich direkt anmelden.', 'bi-patch-check', 'bi-stars');
      }

      return $this->confirm_page('error', 'Bestaetigung nicht gespeichert', 'Die Bestaetigung konnte technisch nicht gespeichert werden.', 'Bitte versuche es erneut oder wende dich an den Administrator.', 'bi-exclamation-triangle', 'bi-database-x');
   }

   private function resend_confirm() {
      if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
         return $this->confirm_page('error', 'Aktion nicht erlaubt', 'Der Bestaetigungslink kann nur ueber das Formular erneut angefordert werden.', 'Bitte melde dich erneut an und nutze den Button zum erneuten Versand.', 'bi-exclamation-triangle', 'bi-shield-x');
      }

      // Das Ziel rekonstruiert dasselbe Formular wie die Login-Seite. Die
      // Fachaktion darf erst nach gültigem dbxForm-Submit weiterlaufen.
      $form = dbx()->get_system_obj('dbxForm');
      $form->init('login-unconfirmed', 'login-unconfirmed');
      $form->add_fld('dbx_token', 'dbx|hidden', rules: 'parameter', dd: '');
      if (!$form->submit() || $form->errors()) {
         dbx()->sys_msg('security', 'register', '', 'invalid resend confirm form', $_SERVER['REMOTE_ADDR'] ?? '');
         return $this->confirm_page('error', 'Sicherheitspruefung fehlgeschlagen', 'Der erneute Versand konnte nicht gestartet werden.', 'Bitte melde dich erneut an und versuche es noch einmal.', 'bi-exclamation-triangle', 'bi-shield-lock');
      }

      $postedToken = (string)($_POST['dbx_token'] ?? '');
      if (!dbx()->check_action_token('dbxLogin.resend_confirm', $postedToken)) {
         dbx()->sys_msg('security', 'register', '', 'invalid resend confirm token', $_SERVER['REMOTE_ADDR'] ?? '');
         return $this->confirm_page('error', 'Sicherheitspruefung fehlgeschlagen', 'Der erneute Versand konnte nicht gestartet werden.', 'Bitte melde dich erneut an und versuche es noch einmal.', 'bi-exclamation-triangle', 'bi-shield-lock');
      }

      $uid = (int)dbx()->get_session_var('pending_confirm_uid', 0, 'confirm', 'dbxLogin');
      if ($uid <= 0) {
         return $this->confirm_page('error', 'Anmeldung abgelaufen', 'Die zu bestaetigende Anmeldung wurde nicht mehr gefunden.', 'Bitte melde dich erneut mit Benutzername und Passwort an.', 'bi-hourglass-split', 'bi-person-x');
      }

      $currentUid = (int)dbx()->user();
      if ($currentUid > 0 && $currentUid !== $uid) {
         dbx()->delete_session_var('pending_confirm_uid', 'confirm', 'dbxLogin');
         dbx()->sys_msg('security', 'register', $uid, 'resend confirm user mismatch', 'current=' . $currentUid . ' ip=' . ($_SERVER['REMOTE_ADDR'] ?? ''));
         return $this->confirm_page('error', 'Anmeldung passt nicht', 'Der erneute Versand passt nicht zu deiner aktuellen Sitzung.', 'Bitte melde dich erneut mit dem betroffenen Benutzerkonto an.', 'bi-exclamation-triangle', 'bi-person-x');
      }

      $db = dbx()->get_system_obj('dbxDB');
      $user = $db->select1($this->ddUser, $uid, '*', 0);
      if (!is_array($user) || empty($user['id'])) {
         dbx()->delete_session_var('pending_confirm_uid', 'confirm', 'dbxLogin');
         return $this->confirm_page('error', 'Benutzer nicht gefunden', 'Das Benutzerkonto konnte nicht mehr geladen werden.', 'Bitte registriere dich erneut oder wende dich an den Administrator.', 'bi-person-x', 'bi-search');
      }

      if ((int)($user['is_confirm'] ?? 0) === 1) {
         dbx()->delete_session_var('pending_confirm_uid', 'confirm', 'dbxLogin');
         $name = (string)($user['name'] ?: $user['uname'] ?? '');
         return $this->confirm_page('success', 'Willkommen zurueck' . ($name ? ', ' . $name : ''), 'Deine E-Mail-Adresse ist bereits bestaetigt.', 'Dein Konto ist freigeschaltet. Du kannst dich jetzt anmelden.', 'bi-patch-check', 'bi-person-check');
      }

      if (trim((string)($user['email'] ?? '')) === '') {
         return $this->confirm_page('error', 'Keine E-Mail-Adresse hinterlegt', 'Fuer dieses Benutzerkonto ist keine E-Mail-Adresse gespeichert.', 'Bitte wende dich an den Administrator, damit die Adresse korrigiert werden kann.', 'bi-exclamation-triangle', 'bi-envelope-x');
      }

      $token = $this->new_token();
      $settings = $this->decode_settings($user['settings'] ?? '');
      $ok = $db->update($this->ddUser, array(
         'settings' => $this->settings_with_token($settings, $token),
      ), $uid, 0, 1, 1, 1);

      if ($ok <= 0) {
         return $this->confirm_page('error', 'Bestaetigungslink nicht gespeichert', 'Der neue Bestaetigungslink konnte technisch nicht gespeichert werden.', 'Bitte versuche es erneut oder wende dich an den Administrator.', 'bi-exclamation-triangle', 'bi-database-x');
      }

      $mailOk = $this->send_confirm_mail($user, $token);
      if (!$mailOk) {
         return $this->confirm_page('error', 'E-Mail nicht gesendet', 'Der Bestaetigungslink wurde erzeugt, die E-Mail konnte aber nicht versendet werden.', 'Bitte pruefe die Mail-Konfiguration oder wende dich an den Administrator.', 'bi-exclamation-triangle', 'bi-envelope-x');
      }

      dbx()->sys_msg('info', 'register', $uid, 'confirm mail resent', (string)($user['email'] ?? ''));
      return $this->confirm_page('success', 'Bestaetigungslink gesendet', 'Wir haben dir einen neuen Link zur E-Mail-Bestaetigung gesendet.', 'Bitte pruefe dein Postfach und auch den Spam-Ordner.', 'bi-envelope-check', 'bi-send-check');
   }

   public function run() {
      $run2 = dbx()->get_modul_var('dbx_run2', '', 'parameter');
      if ($run2 === 'confirm') {
         return $this->confirm();
      }
      if ($run2 === 'resend_confirm') {
         return $this->resend_confirm();
      }

      if (dbx()->user()) {
         return dbx()->redirect('?dbx_modul=dbxUser&dbx_run1=user&dbx_run2=edit_profil', 1);
      }

      $oForm = dbx()->get_system_obj('dbxForm');
      $oForm->init('form-register');
      $oForm->_fd = 'dbxLogin|register';
      $oForm->load_fd_messages();
      dbx()->set_system_var(
         'dbx_title',
         $oForm->get_fd_message('page_title')
      );
      $oForm->add_module_bar(
         $oForm->get_fd_message('bar_title'),
         'bi-person-plus',
         $oForm->get_fd_message('bar_subtitle')
      );
      // Den von init() erzeugten dbxForm-Security-Wert erhalten.
      $oForm->_data = array_merge($oForm->_data, array(
         'language' => 'de',
      ));
      $oForm->_action = '?dbx_modul=dbxLogin&dbx_run1=register';
      $oForm->_msg_info = $oForm->format_fd_message(
         'form_info',
         array('domain' => $this->current_domain())
      );
      $oForm->_msg_error = $oForm->get_fd_message('validation_error');
      $oForm->add_obj('login_link', 'obj-value', '');

      $oForm->add_fld('uname');
      $oForm->add_fld('name');
      $oForm->add_fld('email');
      $oForm->add_fld('password');
      $oForm->add_fld('password2');
      $oForm->add_fld('language');

      if ($oForm->submit()) {
         if (!$oForm->errors()) {
            $uname = $oForm->get_post('uname', '', 'parameter|max=60');
            $email = $oForm->get_post('email', '', 'email|max=60');
            $pass1 = $oForm->get_post_data('password', '', 'varchar|max=128');
            $pass2 = $oForm->get_post_data('password2', '', 'varchar|max=128');

            if ($pass1 !== $pass2) {
               $oForm->add_fld_error(
                  'password2',
                  $oForm->get_fd_message('password_mismatch')
               );
               $oForm->_msg_error = $oForm->get_fd_message(
                  'password_mismatch'
               );
            } elseif ($this->user_exists($uname, $email) > 0) {
               $oForm->add_fld_error(
                  'uname',
                  $oForm->get_fd_message('user_exists')
               );
               $oForm->_msg_error = $oForm->get_fd_message('user_exists');
            } else {
               $this->ensure_pending_group();
               $db = dbx()->get_system_obj('dbxDB');
               $token = $this->new_token();
               $values = array(
                  'uname'      => $uname,
                  'name'       => $oForm->get_post('name', '', 'words|max=60'),
                  'email'      => $email,
                  'pass'       => password_hash($pass1, PASSWORD_DEFAULT),
                  'roles'      => $this->pendingRole,
                  'status'     => 1,
                  'is_confirm' => 0,
                  'language'   => $oForm->get_post('language', 'de', 'parameter|min=2|max=3'),
                  'avatar'     => 'avatar-0.png',
                  'settings'   => $this->settings_with_token(array(), $token),
               );

               $id = ($db->insert($this->ddUser, $values, 0, 1, 1, 1) === 1) ? $db->get_insert_id() : 0;
               if ($id > 0) {
                  $values['id'] = $id;
                  $mail_ok = $this->send_confirm_mail($values, $token);
                  dbx()->sys_msg('login', 'register', $id, 'new user registered', $uname);
                  if ($mail_ok) {
                     $oForm->_msg_success = $oForm->get_fd_message(
                        'register_success'
                     );
                  } else {
                     $oForm->_msg_error = $oForm->get_fd_message(
                        'confirm_mail_error'
                     );
                  }
                  $oForm->add_obj('login_link', 'dbxLogin|register-login-button');
               } else {
                  $oForm->_msg_error = $oForm->get_fd_message(
                     'register_error'
                  );
               }
            }
         } else {
            $oForm->_msg_error = $oForm->get_fd_message(
               'validation_error'
            );
         }
      }

      return $oForm->run();
   }
}
?>
