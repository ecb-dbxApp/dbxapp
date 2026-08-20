<?php
namespace dbx\dbxShop;

trait dbxShopServiceMailServiceTrait {

   private function shop_mail_from(array $cfg): array {
      $from = trim((string)($cfg['mail_from'] ?? ''));
      $from_name = trim((string)($cfg['mail_from_name'] ?? 'dbxShop'));
      return array('email' => $from, 'name' => $from_name);
   }

   private function shop_mail_options(array $cfg, array $extra = array()): array {
      $profile = trim((string)($cfg['mail_profile'] ?? ''));
      if ($profile !== '') {
         $extra['mail_profile'] = $profile;
      }
      return $extra;
   }

   private function order_mail_html(array $order, string $title): string {
      $items = '';
      foreach ((array)($order['items'] ?? array()) as $item) {
         $items .= '<li>' . (int)($item['qty'] ?? 0) . 'x ' . $this->h($item['title'] ?? '') . ' - ' . $this->money($item['total_gross'] ?? 0) . '</li>';
      }
      $provider = (string)($order['payment_provider'] ?? '');
      $instructions = trim($this->payment_instructions($provider, $order));
      return '<h1>' . $this->h($title) . '</h1>'
         . '<p>Bestellnummer: <strong>' . $this->h($order['order_no'] ?? '') . '</strong></p>'
         . '<ul>' . $items . '</ul>'
         . '<p>Summe: <strong>' . $this->money($order['total_gross'] ?? 0) . '</strong></p>'
         . '<p>Status: ' . $this->h($order['status'] ?? '') . ', Zahlung: ' . $this->h($this->payment_provider_label($provider)) . ' / ' . $this->h($order['payment_status'] ?? '') . '</p>'
         . ($instructions !== '' ? '<p><strong>Zahlungshinweis</strong><br>' . nl2br($this->h($instructions)) . '</p>' : '');
   }

   private function send_order_mails(array $order): bool {
      $order_id = (int)($order['id'] ?? 0);
      if ($order_id <= 0 || $this->repo()->has_order_history_event($order_id, 'notification', 'order_mail')) {
         return false;
      }

      $cfg = $this->shop_config();
      $from = $this->shop_mail_from($cfg);
      if (filter_var((string)($from['email'] ?? ''), FILTER_VALIDATE_EMAIL) === false) {
         dbx()->sys_msg(
            'error',
            'dbxShop',
            (string)$order_id,
            'order mail configuration invalid',
            'Der konfigurierte Shop-Absender ist keine gültige E-Mail-Adresse.'
         );
         return false;
      }
      $mail_options = $this->shop_mail_options($cfg);
      $subject = 'Bestellung ' . (string)($order['order_no'] ?? '');
      $sent = 0;
      try {
         if ($this->settings_bool($cfg, 'mail_customer_enabled', false)) {
            $to = trim((string)($order['customer_email'] ?? ''));
            if ($to !== '') {
               if (dbx()->get_system_obj('dbxMail')->send_message($from, $to, $subject, $this->order_mail_html($order, 'Ihre Bestellung'), 'html', array(), $mail_options)) {
                  $sent++;
               }
            }
         }
         if ($this->settings_bool($cfg, 'mail_admin_enabled', false)) {
            $to = trim((string)($cfg['mail_admin_to'] ?? ''));
            if ($to !== '') {
               if (dbx()->get_system_obj('dbxMail')->send_message($from, $to, '[Shop] ' . $subject, $this->order_mail_html($order, 'Neue Shop-Bestellung'), 'html', array(), $mail_options)) {
                  $sent++;
               }
            }
         }
      } catch (\Throwable $e) {
         dbx()->sys_msg('error', 'dbxShop', (string)($order['id'] ?? ''), 'order mail failed', $e->getMessage());
      }

      if ($sent > 0) {
         $this->repo()->add_order_history($order_id, 'notification', '', 'order_mail', $sent . ' Bestellmail(s) wurden versendet.');
         return true;
      }
      return false;
   }

   private function send_withdrawal_mails(array $withdrawal): void {
      $cfg = $this->shop_config();
      $from = $this->shop_mail_from($cfg);
      if (filter_var((string)($from['email'] ?? ''), FILTER_VALIDATE_EMAIL) === false) {
         dbx()->sys_msg(
            'error',
            'dbxShop',
            (string)($withdrawal['id'] ?? ''),
            'withdrawal mail configuration invalid',
            'Der konfigurierte Shop-Absender ist keine gültige E-Mail-Adresse.'
         );
         return;
      }
      $mail_options = $this->shop_mail_options($cfg);
      $subject = 'Widerruf ' . (string)($withdrawal['order_no'] ?? '');
      $html = '<h1>Widerruf</h1>'
         . '<p>Bestellnummer: <strong>' . $this->h($withdrawal['order_no'] ?? '') . '</strong></p>'
         . '<p>Name: ' . $this->h($withdrawal['customer_name'] ?? '') . '<br>E-Mail: ' . $this->h($withdrawal['customer_email'] ?? '') . '</p>'
         . '<p>' . nl2br($this->h($withdrawal['reason'] ?? '')) . '</p>';
      try {
         if ($this->settings_bool($cfg, 'mail_customer_enabled', false)) {
            $to = trim((string)($withdrawal['customer_email'] ?? ''));
            if ($to !== '') {
               dbx()->get_system_obj('dbxMail')->send_message($from, $to, $subject, $html, 'html', array(), $mail_options);
            }
         }
         if ($this->settings_bool($cfg, 'mail_admin_enabled', false)) {
            $to = trim((string)($cfg['mail_admin_to'] ?? ''));
            if ($to !== '') {
               dbx()->get_system_obj('dbxMail')->send_message($from, $to, '[Shop] ' . $subject, $html, 'html', array(), $mail_options);
            }
         }
      } catch (\Throwable $e) {
         dbx()->sys_msg('error', 'dbxShop', (string)($withdrawal['id'] ?? ''), 'withdrawal mail failed', $e->getMessage());
      }
   }
}
