<?php
namespace dbx\dbxLogin;

require_once dirname(__DIR__, 3) . '/include/dbxPasswordPolicy.class.php';

/**
 * Öffentlicher, tokenbasierter Passwort-Reset.
 *
 * Antworten auf die Anforderung sind absichtlich identisch, damit weder
 * Benutzername noch E-Mail-Adresse auf ihre Existenz geprüft werden können.
 * In der Datenbank liegt ausschließlich der SHA-256-Hash des Einmal-Tokens.
 */
class password_reset {
   private string $ddUser = 'dbxUser';
   private int $tokenLifetime = 3600;
   private int $requestCooldown = 60;

   public function run(): string {
      $developmentBypass = dbx()->is_admin_bypass_active();
      if (dbx()->user() && !$developmentBypass) {
         return dbx()->redirect('?dbx_modul=dbxUser&dbx_run1=user&dbx_run2=edit_profil', 1);
      }

      dbx()->set_system_var('dbx_title', 'Passwort zurücksetzen');
      $run2 = (string)dbx()->get_modul_var('dbx_run2', 'request', 'parameter');
      return $run2 === 'reset' ? $this->resetPage() : $this->requestPage();
   }

   private function requestPage(): string {
      $form = dbx()->get_system_obj('dbxForm');
      $form->init('password-reset-request', 'form-password-reset-request');
      $form->_fd = '';
      $form->_action = '?dbx_modul=dbxLogin&dbx_run1=password_reset&dbx_run2=request';
      $form->_msg_info = 'Gib deinen Benutzernamen oder deine E-Mail-Adresse ein.';
      $form->_msg_error = 'Die Anfrage konnte nicht geprüft werden.';
      $form->set_form_help_enabled(false);
      $form->add_module_bar('Passwort vergessen', 'bi-key', 'Sicheren Einmal-Link anfordern', true);
      $form->add_fld(
         'identity',
         'auth-text-label',
         'Benutzername oder E-Mail',
         'varchar|min=1|max=190',
         data: 'icon=bi-person&field_class=',
         placeholder: 'Benutzername oder E-Mail-Adresse',
         errormsg: 'Bitte Benutzername oder E-Mail-Adresse eingeben.',
         dd: ''
      );

      if ($form->submit() && !$form->errors()) {
         $identity = trim((string)$form->get_post_data('identity', '', 'varchar|max=190'));
         $this->issueTokenIfEligible($identity);
         // Diese Meldung darf unabhängig vom Ergebnis nicht variieren.
         $form->_msg_success = 'Falls ein aktives, bestätigtes Konto passt, wurde ein zeitlich begrenzter Link versendet.';
         $form->_msg_info = 'Prüfe dein Postfach und gegebenenfalls den Spam-Ordner.';
      }

      return $form->run();
   }

   private function issueTokenIfEligible(string $identity): void {
      if ($identity === '') {
         return;
      }

      $db = dbx()->get_system_obj('dbxDB');
      $server = $db->get_dd_server($this->ddUser);
      $safe = $db->escape($identity, $server);
      $user = $db->select1(
         $this->ddUser,
         "(uname='$safe' OR email='$safe') AND status=1 AND is_confirm=1",
         verify_access: 0
      );
      if (!is_array($user) || (int)($user['id'] ?? 0) <= 0 || trim((string)($user['email'] ?? '')) === '') {
         dbx()->sys_msg('security', 'password_reset', '', 'password reset requested for unknown account', $this->requestContext());
         return;
      }

      $settings = $this->decodeSettings($user['settings'] ?? '');
      $previous = is_array($settings['password_reset'] ?? null) ? $settings['password_reset'] : array();
      if ((int)($previous['requested_at'] ?? 0) > time() - $this->requestCooldown) {
         dbx()->sys_msg('security', 'password_reset', (int)$user['id'], 'password reset rate limited', $this->requestContext());
         return;
      }

      $token = bin2hex(random_bytes(32));
      $settings['password_reset'] = array(
         'token_hash' => hash('sha256', $token),
         'expires' => time() + $this->tokenLifetime,
         'requested_at' => time(),
      );
      $updated = $db->update($this->ddUser, array(
         'settings' => $this->encodeSettings($settings),
      ), (int)$user['id'], 0, 1, 1, 0);
      if (!$updated) {
         dbx()->sys_msg('error', 'password_reset', (int)$user['id'], 'password reset token not stored', '');
         return;
      }

      if (!$this->sendResetMail($user, $token)) {
         dbx()->sys_msg('error', 'password_reset', (int)$user['id'], 'password reset mail failed', (string)$user['email']);
         return;
      }
      dbx()->sys_msg('security', 'password_reset', (int)$user['id'], 'password reset mail sent', $this->requestContext());
   }

   private function resetPage(): string {
      $uid = (int)dbx()->get_modul_var('uid', 0, 'int');
      $token = (string)dbx()->get_modul_var('token', '', 'varchar|max=128');
      $user = $this->validTokenUser($uid, $token);
      if (!$user) {
         dbx()->sys_msg('security', 'password_reset', $uid, 'invalid or expired password reset token', $this->requestContext());
         return $this->invalidTokenPage();
      }

      $form = dbx()->get_system_obj('dbxForm');
      $form->init('password-reset', 'form-password-reset');
      $form->_fd = '';
      $form->_action = '?dbx_modul=dbxLogin&dbx_run1=password_reset&dbx_run2=reset&uid=' . $uid . '&token=' . rawurlencode($token);
      $form->_msg_info = 'Lege jetzt ein neues persönliches Passwort fest.';
      $form->_msg_error = 'Das neue Passwort erfüllt noch nicht alle Anforderungen.';
      $form->set_form_help_enabled(false);
      $form->add_module_bar('Neues Passwort', 'bi-shield-lock', 'Der Link kann nur einmal verwendet werden', true);
      $minimum = \dbxPasswordPolicy::minimumLength();
      $form->add_rep('password_min_length', (string)$minimum);
      $form->add_rep('password_length_recommendation', $minimum < 12 ? ' (12 oder mehr empfohlen)' : '');
      $form->add_fld('password_new', 'auth-password-label', 'Neues Passwort', 'varchar|min=' . $minimum . '|max=128', data: 'icon=bi-key&field_class=', placeholder: 'Neues Passwort', errormsg: 'Bitte ein gültiges neues Passwort eingeben.', dd: '');
      $form->add_fld('password_repeat', 'auth-password-label', 'Passwort wiederholen', 'varchar|min=' . $minimum . '|max=128', data: 'icon=bi-shield-check&field_class=', placeholder: 'Passwort wiederholen', errormsg: 'Bitte das Passwort wiederholen.', dd: '');

      if ($form->submit() && !$form->errors()) {
         // Token unmittelbar vor der Mutation erneut prüfen, damit parallele
         // oder wiederholte Requests den Einmal-Vertrag nicht umgehen.
         $user = $this->validTokenUser($uid, $token);
         if (!$user) {
            return $this->invalidTokenPage();
         }
         $password = (string)$form->get_post_data('password_new', '', '*');
         $repeat = (string)$form->get_post_data('password_repeat', '', '*');
         $errors = \dbxPasswordPolicy::errors($password, $repeat, (string)($user['pass'] ?? ''), $minimum);
         if (isset($errors['password'])) {
            $form->add_fld_error('password_new', $errors['password']);
         }
         if (isset($errors['repeat'])) {
            $form->add_fld_error('password_repeat', $errors['repeat']);
         }

         if (!$form->errors()) {
            $settings = $this->decodeSettings($user['settings'] ?? '');
            unset($settings['password_reset'], $settings['password_reset_required']);
            $settings['password_changed_at'] = date(DATE_ATOM);
            $db = dbx()->get_system_obj('dbxDB');
            $ok = $db->update($this->ddUser, array(
               'pass' => password_hash($password, PASSWORD_DEFAULT),
               'settings' => $this->encodeSettings($settings),
            ), $uid, 0, 1, 1, 0);
            if ($ok) {
               // Alle bestehenden angemeldeten Sitzungen dieses Kontos werden
               // widerrufen; der anonyme Reset-Request bleibt unberührt.
               $db->delete('dbxSession', 'userid=' . $uid, 0, 0);
               dbx()->sys_msg('security', 'password_reset', $uid, 'password reset completed; sessions revoked', $this->requestContext());
               return dbx()->get_system_obj('dbxTPL')->get_tpl('dbx|alert-success', array(
                  'msg' => 'Das Passwort wurde geändert. Alle bisherigen Sitzungen wurden beendet.',
               )) . '<p><a class="btn btn-primary" href="?dbx_modul=dbxLogin&amp;dbx_run1=login">Jetzt anmelden</a></p>';
            }
            $form->_msg_error = 'Das neue Passwort konnte nicht gespeichert werden.';
         }
      }

      return $form->run();
   }

   private function validTokenUser(int $uid, string $token): array {
      if ($uid <= 0 || $token === '') {
         return array();
      }
      $db = dbx()->get_system_obj('dbxDB');
      $user = $db->select1($this->ddUser, $uid, '*', 0);
      if (!is_array($user) || (int)($user['status'] ?? 0) !== 1 || (int)($user['is_confirm'] ?? 0) !== 1) {
         return array();
      }
      $settings = $this->decodeSettings($user['settings'] ?? '');
      $reset = is_array($settings['password_reset'] ?? null) ? $settings['password_reset'] : array();
      $hash = (string)($reset['token_hash'] ?? '');
      $expires = (int)($reset['expires'] ?? 0);
      if ($hash === '' || $expires < time() || !hash_equals($hash, hash('sha256', $token))) {
         return array();
      }
      return $user;
   }

   private function sendResetMail(array $user, string $token): bool {
      $url = rtrim(dbx()->get_base_url(), '/') . '/?dbx_modul=dbxLogin&dbx_run1=password_reset&dbx_run2=reset&uid=' . (int)$user['id'] . '&token=' . rawurlencode($token);
      $name = trim((string)($user['name'] ?? '')) ?: (string)($user['uname'] ?? '');
      $tpl = dbx()->get_system_obj('dbxTPL');
      $html = $tpl->get_tpl('dbxLogin|mail-password-reset', array(
         'name' => $this->h($name),
         'reset_url' => $this->h($url),
         'valid_minutes' => (string)intdiv($this->tokenLifetime, 60),
      ));
      $text = "Hallo {$name}\n\nPasswort zurücksetzen (" . intdiv($this->tokenLifetime, 60) . " Minuten gültig):\n{$url}\n\nWenn du die Anfrage nicht gestellt hast, ignoriere diese E-Mail.";
      return (bool)dbx()->send_mail('security@dbxapp.de', (string)$user['email'], 'dbxapp Passwort zurücksetzen', $html, 'html', array(), array('text' => $text));
   }

   private function invalidTokenPage(): string {
      return dbx()->get_system_obj('dbxTPL')->get_tpl('dbx|alert-danger', array(
         'msg' => 'Dieser Passwort-Link ist ungültig, abgelaufen oder wurde bereits verwendet.',
      )) . '<p><a class="btn btn-primary" href="?dbx_modul=dbxLogin&amp;dbx_run1=password_reset">Neuen Link anfordern</a></p>';
   }

   private function decodeSettings(mixed $raw): array {
      $settings = json_decode((string)$raw, true);
      return is_array($settings) ? $settings : array();
   }

   private function encodeSettings(array $settings): string {
      return $settings ? (string)json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '{}';
   }

   private function requestContext(): string {
      return 'ip=' . (string)($_SERVER['REMOTE_ADDR'] ?? '') . ' ua=' . substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 180);
   }

   private function h(mixed $value): string {
      return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
   }
}
