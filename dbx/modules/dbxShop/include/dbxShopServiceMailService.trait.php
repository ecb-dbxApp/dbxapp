<?php
namespace dbx\dbxShop;

trait dbxShopServiceMailServiceTrait {

   private function shopMailFrom(array $cfg): array {
      $from = trim((string)($cfg['mail_from'] ?? ''));
      $fromName = trim((string)($cfg['mail_from_name'] ?? 'dbxShop'));
      return array('email' => $from, 'name' => $fromName);
   }

   private function shopMailOptions(array $cfg, array $extra = array()): array {
      $profile = trim((string)($cfg['mail_profile'] ?? ''));
      if ($profile !== '') {
         $extra['mail_profile'] = $profile;
      }
      return $extra;
   }

   private function orderMailHtml(array $order, string $title): string {
      $items = '';
      foreach ((array)($order['items'] ?? array()) as $item) {
         $items .= '<li>' . (int)($item['qty'] ?? 0) . 'x ' . $this->h($item['title'] ?? '') . ' - ' . $this->money($item['total_gross'] ?? 0) . '</li>';
      }
      $provider = (string)($order['payment_provider'] ?? '');
      $instructions = trim($this->paymentInstructions($provider, $order));
      return '<h1>' . $this->h($title) . '</h1>'
         . '<p>Bestellnummer: <strong>' . $this->h($order['order_no'] ?? '') . '</strong></p>'
         . '<ul>' . $items . '</ul>'
         . '<p>Summe: <strong>' . $this->money($order['total_gross'] ?? 0) . '</strong></p>'
         . '<p>Status: ' . $this->h($order['status'] ?? '') . ', Zahlung: ' . $this->h($this->paymentProviderLabel($provider)) . ' / ' . $this->h($order['payment_status'] ?? '') . '</p>'
         . ($instructions !== '' ? '<p><strong>Zahlungshinweis</strong><br>' . nl2br($this->h($instructions)) . '</p>' : '');
   }

   private function sendOrderMails(array $order): bool {
      $orderId = (int)($order['id'] ?? 0);
      if ($orderId <= 0 || $this->repo()->hasOrderHistoryEvent($orderId, 'notification', 'order_mail')) {
         return false;
      }

      $cfg = $this->shopConfig();
      $from = $this->shopMailFrom($cfg);
      if (filter_var((string)($from['email'] ?? ''), FILTER_VALIDATE_EMAIL) === false) {
         dbx()->sys_msg(
            'error',
            'dbxShop',
            (string)$orderId,
            'order mail configuration invalid',
            'Der konfigurierte Shop-Absender ist keine gültige E-Mail-Adresse.'
         );
         return false;
      }
      $mailOptions = $this->shopMailOptions($cfg);
      $subject = 'Bestellung ' . (string)($order['order_no'] ?? '');
      $sent = 0;
      try {
         if ($this->settingsBool($cfg, 'mail_customer_enabled', false)) {
            $to = trim((string)($order['customer_email'] ?? ''));
            if ($to !== '') {
               if (dbx()->send_mail($from, $to, $subject, $this->orderMailHtml($order, 'Ihre Bestellung'), 'html', array(), $mailOptions)) {
                  $sent++;
               }
            }
         }
         if ($this->settingsBool($cfg, 'mail_admin_enabled', false)) {
            $to = trim((string)($cfg['mail_admin_to'] ?? ''));
            if ($to !== '') {
               if (dbx()->send_mail($from, $to, '[Shop] ' . $subject, $this->orderMailHtml($order, 'Neue Shop-Bestellung'), 'html', array(), $mailOptions)) {
                  $sent++;
               }
            }
         }
      } catch (\Throwable $e) {
         dbx()->sys_msg('error', 'dbxShop', (string)($order['id'] ?? ''), 'order mail failed', $e->getMessage());
      }

      if ($sent > 0) {
         $this->repo()->addOrderHistory($orderId, 'notification', '', 'order_mail', $sent . ' Bestellmail(s) wurden versendet.');
         return true;
      }
      return false;
   }

   private function sendWithdrawalMails(array $withdrawal): void {
      $cfg = $this->shopConfig();
      $from = $this->shopMailFrom($cfg);
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
      $mailOptions = $this->shopMailOptions($cfg);
      $subject = 'Widerruf ' . (string)($withdrawal['order_no'] ?? '');
      $html = '<h1>Widerruf</h1>'
         . '<p>Bestellnummer: <strong>' . $this->h($withdrawal['order_no'] ?? '') . '</strong></p>'
         . '<p>Name: ' . $this->h($withdrawal['customer_name'] ?? '') . '<br>E-Mail: ' . $this->h($withdrawal['customer_email'] ?? '') . '</p>'
         . '<p>' . nl2br($this->h($withdrawal['reason'] ?? '')) . '</p>';
      try {
         if ($this->settingsBool($cfg, 'mail_customer_enabled', false)) {
            $to = trim((string)($withdrawal['customer_email'] ?? ''));
            if ($to !== '') {
               dbx()->send_mail($from, $to, $subject, $html, 'html', array(), $mailOptions);
            }
         }
         if ($this->settingsBool($cfg, 'mail_admin_enabled', false)) {
            $to = trim((string)($cfg['mail_admin_to'] ?? ''));
            if ($to !== '') {
               dbx()->send_mail($from, $to, '[Shop] ' . $subject, $html, 'html', array(), $mailOptions);
            }
         }
      } catch (\Throwable $e) {
         dbx()->sys_msg('error', 'dbxShop', (string)($withdrawal['id'] ?? ''), 'withdrawal mail failed', $e->getMessage());
      }
   }
}
