<?php
namespace dbx\dbxDownLoad;

class dbxDownLoad {

   private function h($value) {
      return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
   }

   private function tpl(string $name, array $data = array()) {
      return dbx()->get_system_obj('dbxTPL')->get_tpl('dbxDownLoad|' . $name, $data);
   }

   private function config(string $key, $default = '') {
      return dbx()->get_cfg('dbxDownLoad', $key, $default);
   }

   private function mail_from_param() {
      $from = trim((string)$this->config('mail_from'));
      $from_name = trim((string)$this->config('mail_from_name'));
      return ($from !== '') ? array('email' => $from, 'name' => $from_name) : '';
   }

   private function mail_options(array $extra = array()) {
      $profile = trim((string)$this->config('mail_profile'));
      if ($profile !== '') {
         $extra['mail_profile'] = $profile;
      }
      return $extra;
   }

   private function module_url(array $params = array()): string {
      $base = rtrim((string)dbx()->get_base_url(), '/') . '/';
      $query = array_merge(array('dbx_modul' => 'dbxDownLoad'), $params);
      return $base . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
   }

   private function secret(): string {
      $secret = trim((string)$this->config('token_secret'));
      if ($secret !== '') {
         return $secret;
      }

      $config = dbx()->get_cfg('dbxDownLoad');
      $secret = bin2hex(random_bytes(32));
      $config['token_secret'] = $secret;
      dbx()->set_cfg('dbxDownLoad', $config);
      return $secret;
   }

   private function base64url(string $bytes): string {
      return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
   }

   private function base64url_decode(string $value): string {
      $value = strtr($value, '-_', '+/');
      $pad = strlen($value) % 4;
      if ($pad) {
         $value .= str_repeat('=', 4 - $pad);
      }
      $decoded = base64_decode($value, true);
      return is_string($decoded) ? $decoded : '';
   }

   private function create_token(string $name, string $email): string {
      $ttl = max(1, (int)$this->config('download_ttl_hours', 48));
      $payload = array(
         'name' => $name,
         'email' => $email,
         'iat' => time(),
         'exp' => time() + ($ttl * 3600),
         'nonce' => bin2hex(random_bytes(8)),
      );
      $body = $this->base64url(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
      $sig = $this->base64url(hash_hmac('sha256', $body, $this->secret(), true));
      return $body . '.' . $sig;
   }

   private function read_token(string $token): array {
      $parts = explode('.', trim($token), 2);
      if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
         return array();
      }

      $expected = $this->base64url(hash_hmac('sha256', $parts[0], $this->secret(), true));
      if (!hash_equals($expected, $parts[1])) {
         return array();
      }

      $payload = json_decode($this->base64url_decode($parts[0]), true);
      if (!is_array($payload) || (int)($payload['exp'] ?? 0) < time()) {
         return array();
      }

      return $payload;
   }

   private function download_file(): string {
      $file = trim((string)$this->config('download_zip', 'files/download/dbxapp-demo.zip'));
      $file = str_replace('\\', '/', $file);
      if ($file === '') {
         return '';
      }
      if (!preg_match('#^[A-Za-z]:/#', $file) && strpos($file, '/') !== 0) {
         $file = dbx()->get_base_dir() . ltrim($file, '/');
      }
      return dbx()->os_path($file);
   }

   private function build_form() {
      $form = dbx()->get_system_obj('dbxForm');
      $form->init('download-link', 'download-form');
      $form->set_field_definition('dbxDownLoad|download-link');
      $form->load_fd_messages();
      $form->set_action('?dbx_modul=dbxDownLoad&dbx_run1=send_link');
      $form->_try_reset = 6;
      $form->_try_max = 5;
      $form->_try_msg = $form->get_fd_message('try_limit');
      $form->set_msg_info('');
      $form->set_msg_error($form->get_fd_message('validation_error'));
      $form->set_msg_ok($form->get_fd_message('send_success'));
      // Den von dbxForm erzeugten Security-Wert beibehalten.
      $form->merge_data(array(
         'name' => trim((string)($_POST['name'] ?? $_REQUEST['name'] ?? '')),
         'email' => trim((string)($_POST['email'] ?? $_REQUEST['email'] ?? '')),
      ));
      $form->add_module_bar(
         $form->get_fd_message('bar_title'),
         'bi-download',
         $form->get_fd_message('bar_subtitle')
      );
      $form->prepare_form_shell(array('class' => 'dbx-download-form'));
      $form->add_fld('name');
      $form->add_fld('email');
      return $form;
   }

   private function render_form() {
      return $this->build_form()->run();
   }

   private function spam_reason(string $name, string $email, string $subject = ''): string {
      $mail = dbx()->get_system_obj('dbxMail');
      if (!is_object($mail) || !method_exists($mail, 'spam_reason_for_text')) {
         return '';
      }

      return (string)$mail->spam_reason_for_text(implode("\n", array($name, $email, $subject)));
   }

   private function save_demo_contact_request(string $name, string $email): int {
      try {
         $db = dbx()->get_system_obj('dbxDB');
         $message = "Demo-Link wurde angefordert.\n\n"
            . "Name: " . $name . "\n"
            . "E-Mail: " . $email . "\n"
            . "Quelle: dbxDownLoad\n"
            . "Zeit: " . date('Y-m-d H:i:s');

         $values = array(
            'uid' => (int)dbx()->user(),
            'status' => 'open',
            'priority' => 'normal',
            'name' => $name,
            'email' => $email,
            'phone' => '',
            'subject' => 'Demo-Link angefordert',
            'message' => $message,
            'request_ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            'request_user_agent' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'mail_sent' => 0,
            'confirm_mail_sent' => 0,
            'user_hidden' => 0,
         );

         $ok = $db->insert('dbxContact|contactRequest', $values, 0, 1, 1, 1);
         $ticket_id = $ok > 0 ? (int)$db->get_insert_id() : 0;

         if ($ticket_id > 0) {
            try {
               dbx()->get_include_obj('dbxContactTicket', 'dbxContact');
               if (class_exists('\\dbx\\dbxContact\\dbxContactTicket')) {
                  \dbx\dbxContact\dbxContactTicket::add_message($db, $ticket_id, array(
                     'author_uid' => (int)dbx()->user(),
                     'author_type' => 'requester',
                     'message_type' => 'request',
                     'visibility' => 'public',
                     'body' => $message,
                     'status_to' => 'open',
                  ));
                  \dbx\dbxContact\dbxContactTicket::touch($db, $ticket_id);
               }
            } catch (\Throwable $e) {
               dbx()->sys_msg('warning', 'dbxDownLoad', 'contact_request', 'Kontakt-Nachricht konnte nicht ergaenzt werden', $e->getMessage());
            }
         }

         return $ticket_id;
      } catch (\Throwable $e) {
         dbx()->sys_msg('error', 'dbxDownLoad', 'contact_request', 'Demo-Kontaktanfrage konnte nicht gespeichert werden', $e->getMessage());
         return 0;
      }
   }

   private function send_link() {
      $form = $this->build_form();

      if (!$form->submit()) {
         return $form->run();
      }

      if ($form->errors()) {
         return $form->run();
      }

      $name = trim((string)$form->get_post_data('name', '', 'varchar|min=2|max=160'));
      $email = trim((string)$form->get_post_data('email', '', 'email|min=6|max=180'));
      $subject = trim((string)$this->config('mail_subject', 'Ihr dbxapp Demo-Download'));

      $spam_reason = $this->spam_reason($name, $email, $subject);
      if ($spam_reason !== '') {
         $form->set_msg_error($form->get_fd_message('spam_error'));
         $form->add_fld_error(
            'name',
            $form->get_fd_message('spam_field_error')
         );
         dbx()->sys_msg('security', 'dbxDownLoad', 'spam_guard', 'Demo-Link blockiert', $spam_reason . ' email=' . $email);
         return $form->run();
      }

      $token = $this->create_token($name, $email);
      $url = $this->module_url(array('dbx_run1' => 'download', 'token' => $token));
      $contact_id = $this->save_demo_contact_request($name, $email);
      $html = $this->tpl('mail-download-link', array(
         'name' => $this->h($name),
         'download_url' => $this->h($url),
         'ttl_hours' => (int)$this->config('download_ttl_hours', 48),
      ));
      $text = "Hallo " . $name . "\n\n"
         . "hier ist Ihr dbxapp Demo-Download:\n" . $url . "\n\n"
         . "Der Link ist " . (int)$this->config('download_ttl_hours', 48) . " Stunden gueltig.\n";

      $ok = dbx()->get_system_obj('dbxMail')->send_message($this->mail_from_param(), $email, $subject, $html, 'html', array(), $this->mail_options(array('text' => $text)));
      if (!$ok) {
         dbx()->sys_msg('error', 'dbxDownLoad', 'send_link', 'Download-Mail konnte nicht gesendet werden', $email);
         $form->set_msg_error($form->get_fd_message('mail_error'));
         $form->add_fld_error(
            'email',
            $form->get_fd_message('email_field_error')
         );
         return $form->run();
      }

      dbx()->sys_msg('info', 'dbxDownLoad', 'send_link', 'Download-Link versendet', $email . ' contact=' . $contact_id);
      $form->set_msg_ok($form->format_fd_message(
         'sent_to',
         array('email' => $this->h($email))
      ));
      return $form->run();
   }

   private function download_page() {
      $token = trim((string)($_REQUEST['token'] ?? ''));
      $payload = $this->read_token($token);
      if (!$payload) {
         return $this->tpl('download-invalid', array());
      }

      $file = $this->download_file();
      $ready = is_file($file) && is_readable($file);
      return $this->tpl('download-ready', array(
         'name' => $this->h($payload['name'] ?? ''),
         'email' => $this->h($payload['email'] ?? ''),
         'expires' => date('d.m.Y H:i', (int)($payload['exp'] ?? time())),
         'download_url' => $ready ? $this->h($this->module_url(array('dbx_run1' => 'file', 'token' => $token))) : '#',
         'download_disabled' => $ready ? '' : 'disabled aria-disabled="true" tabindex="-1"',
         'download_btn_class' => $ready ? 'btn btn-primary' : 'btn btn-secondary disabled',
         'download_note' => $ready
            ? 'Die Demo-ZIP und die Hinweise zur Installation stehen bereit.'
            : 'Die Demo-ZIP ist auf diesem System noch nicht hinterlegt. Erwarteter Pfad: ' . $this->h((string)$this->config('download_zip')),
      ));
   }

   private function stream_file() {
      $payload = $this->read_token(trim((string)($_REQUEST['token'] ?? '')));
      $file = $this->download_file();
      if (!$payload || !is_file($file) || !is_readable($file)) {
         http_response_code(404);
         echo 'Download nicht verfuegbar.';
         exit;
      }

      $name = trim((string)$this->config('download_name', basename($file)));
      if ($name === '') {
         $name = basename($file);
      }

      dbx()->sys_msg('info', 'dbxDownLoad', 'file', 'Demo-ZIP geladen', (string)($payload['email'] ?? ''));
      while (ob_get_level()) {
         ob_end_clean();
      }
      header('Content-Type: application/zip');
      header('Content-Disposition: attachment; filename="' . str_replace('"', '', $name) . '"');
      header('Content-Length: ' . filesize($file));
      header('Cache-Control: private, no-store, max-age=0');
      readfile($file);
      exit;
   }

   public function run() {
      $run = (string)dbx()->get_modul_var('dbx_run1', 'form', 'parameter');
      switch ($run) {
         case 'send_link':
            return $this->send_link();
         case 'download':
            return $this->download_page();
         case 'file':
            return $this->stream_file();
         case 'form':
         default:
            return $this->render_form();
      }
   }
}
?>
