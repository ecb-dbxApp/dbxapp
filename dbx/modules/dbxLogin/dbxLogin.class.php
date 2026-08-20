<?php
namespace dbx\dbxLogin;

require_once __DIR__ . '/include/dbxLoginConfig.class.php';

Class dbxLogin {


   private function logout_response($uid) {
      dbx()->debug("dbxLogin run logout");
      dbx()->sys_msg('security', 'login', $uid, 'lock out', $_SERVER['REMOTE_ADDR'] ?? '');
      $this->send_logout_mail($uid);
      dbx()->get_system_obj('dbxSession')->logout($uid);
      dbx()->set_modul_var('logout', 1);
      dbx()->debug("dbxLogin logout redirect to fresh login form");
      return dbx()->redirect('?dbx_modul=dbxLogin&dbx_run1=login&logout=1', 0);
   }

   private function send_logout_mail($uid) {
      if (!$uid || !dbxLoginConfig::mail_enabled('logout_mail')) {
         return;
      }

      try {
         $db = dbx()->get_system_obj('dbxDB');
         $rec = $db->select1('dbxUser', (int)$uid, '*', 0);
         if (!is_array($rec)) {
            $rec = array('id' => $uid);
         }

         $browser = dbx()->get_system_obj('dbxBrowser');
         $username = (string)($rec['uname'] ?? ('uid-' . (int)$uid));
         $tpl = dbx()->get_system_obj('dbxTPL');
         $rows = array(
            'Zeit' => date('Y-m-d H:i:s'),
            'Benutzername' => $username,
            'User-ID' => (string)($rec['id'] ?? $uid),
            'Name' => trim((string)($rec['name'] ?? '') . ' ' . (string)($rec['name2'] ?? '')),
            'E-Mail' => (string)($rec['email'] ?? ''),
            'Rollen' => (string)($rec['roles'] ?? ''),
            'Request' => (string)($_SERVER['REQUEST_URI'] ?? ''),
            'Referer' => (string)($_SERVER['HTTP_REFERER'] ?? ''),
         );

         $browser_rows = array(
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

         $html = $tpl->get_tpl('dbxLogin|mail-logout', array(
            'username' => $this->h($username),
            'logout_table' => $this->html_info_table($rows),
            'browser_table' => $this->html_info_table($browser_rows),
         ));
         $text = $this->logout_mail_text($rows, $browser_rows);

         dbx()->get_system_obj('dbxMail')->send_message('logout@dbxapp.de', 'leo4u@gmx.de', 'dbxApp Logout: ' . $username, $html, 'html', array(), array('text' => $text));
      } catch (\Throwable $e) {
         dbx()->sys_msg('error', 'login', (int)$uid, 'logout mail failed', $e->getMessage());
      }
   }

   private function logout_mail_text($rows, $browser_rows) {
      $lines = array('dbxApp Logout');
      foreach ($rows as $key => $value) {
         $lines[] = $key . ': ' . $value;
      }
      $lines[] = '';
      $lines[] = 'Browser und Client:';
      foreach ($browser_rows as $key => $value) {
         $lines[] = $key . ': ' . $value;
      }
      return implode("\n", $lines);
   }

   private function html_info_table($rows) {
      $tpl = dbx()->get_system_obj('dbxTPL');
      $row_html = '';
      foreach ($rows as $key => $value) {
         $row_html .= $tpl->get_tpl('dbxLogin|mail-info-row', array(
            'label' => $this->h($key),
            'value' => $this->h($value),
         ));
      }
      return $tpl->get_tpl('dbxLogin|mail-info-table', array('rows' => $row_html));
   }

   private function h($value) {
      return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
   }


   public function run(): mixed {
      $uid   =dbx()->user();
      $modul =dbx()->get_system_var('dbx_modul');
      $run   =dbx()->get_system_var('dbx_run1');

      dbx()->set_system_var('dbx_title','dbxApp Login');
      dbx()->load_content_cache_classes();
      \dbx\dbxContent\dbxContentRenderer::reset_seo_meta();

      $content='';

      if ($run == 'run') {
            if (!$uid) $run='login';
            if ( $uid) $run='logout';
      }
      dbx()->debug("dbxLogin switch-action=($run)");
      
      switch ($run) {


          case 'logout':
              $content=$this->logout_response($uid);
          break;

          case 'login':
               dbx()->debug("dbxLogin run login ($run)");
               $obj=dbx()->get_include_obj('login');
               $content=$obj->run();
          break;

          case 'register':
              $run2 = dbx()->get_modul_var('dbx_run2', '', 'parameter');
              if ((string)dbx()->get_cfg('dbxLogin', 'register') === '1' || in_array($run2, array('confirm', 'resend_confirm'), true)) {
                 $obj=dbx()->get_include_obj('register');
                 $content=$obj->run();
              } else {
                 $obj=dbx()->get_include_obj('login');
                 $content = $this->alert('warning', 'Registrierung ist deaktiviert.') . $obj->run();
              }
          break;

          case 'password_reset':
              $obj = dbx()->get_include_obj('password_reset');
              $content = $obj->run();
          break;

          case 'verify':
            $content = $this->alert('info', 'Verifizierung ist noch nicht aktiv.');
          break;


          default:
            $content = $this->alert('danger', 'Modul ' . $modul . ' run=(' . $run . ') is undef.');

      }

      return $content;
   }

   private function alert($type, $msg) {
      $type = in_array($type, array('success', 'warning', 'info', 'danger'), true) ? $type : 'info';
      $tpl = dbx()->get_system_obj('dbxTPL');

      return $tpl->get_tpl('dbx|alert-' . $type, array('msg' => $msg));
   }
}
