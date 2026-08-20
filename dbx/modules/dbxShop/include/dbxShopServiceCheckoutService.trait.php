<?php
namespace dbx\dbxShop;

trait dbxShopServiceCheckoutServiceTrait {

   private function absolute_shop_url(string $run, array $params = array()): string {
      $query = array_merge(array(
         'dbx_modul' => 'dbxShop',
         'dbx_run1' => $run,
      ), $params);
      return dbx()->get_base_url() . '?' . http_build_query($query, '', '&');
   }

   private function checkout_payment_options(): array {
      $cfg = $this->shop_config();
      $texts = $this->texts('dbxShop|checkout');
      $options = array();
      if ($this->settings_bool($cfg, 'payment_bank_transfer_enabled', true)) {
         $options['bank_transfer'] = $texts->get_fd_message('payment_bank_transfer');
      }
      if ($this->settings_bool($cfg, 'payment_invoice_enabled', false)) {
         $options['invoice'] = $texts->get_fd_message('payment_invoice');
      }
      if ($this->settings_bool($cfg, 'payment_paypal_enabled', false) && $this->paypal()->is_configured()) {
         $options['paypal'] = 'PayPal';
      }
      if ($this->settings_bool($cfg, 'payment_amazon_pay_enabled', false) && $this->amazon_pay()->is_configured()) {
         $options['amazon_pay'] = 'Amazon Pay';
      }
      return $options;
   }

   private function payment_method_labels(): array {
      $texts = $this->texts('dbxShop|checkout');
      return array(
         'bank_transfer' => $texts->get_fd_message('payment_bank_transfer'),
         'invoice' => $texts->get_fd_message('payment_invoice'),
         'paypal' => 'PayPal',
         'amazon_pay' => 'Amazon Pay',
      );
   }

   private function payment_provider_label(string $provider): string {
      $labels = $this->payment_method_labels();
      $texts = $this->texts('dbxShop|shop-orders');
      $channel_labels = array(
         'shop' => $texts->get_fd_message('provider_shop'),
         'amazon' => $texts->get_fd_message('provider_amazon'),
         'ebay' => $texts->get_fd_message('provider_ebay'),
         'kleinanzeigen' => $texts->get_fd_message('provider_kleinanzeigen'),
         'mobile' => $texts->get_fd_message('provider_mobile'),
      );
      return $labels[$provider] ?? $channel_labels[$provider] ?? $provider;
   }

   private function payment_instructions(string $method, array $order = array()): string {
      $cfg = $this->shop_config();
      $texts = $this->texts('dbxShop|checkout');
      if ($method === 'bank_transfer') {
         $lines = array();
         $intro = trim((string)($cfg['payment_bank_transfer_instructions'] ?? ''));
         if ($intro === '') {
            $intro = $texts->get_fd_message('bank_transfer_default');
         }
         $lines[] = $intro;
         foreach (array(
            $texts->get_fd_message('account_owner') => 'payment_bank_transfer_account_owner',
            'IBAN' => 'payment_bank_transfer_iban',
            'BIC' => 'payment_bank_transfer_bic',
            'Bank' => 'payment_bank_transfer_bank_name',
         ) as $label => $key) {
            $value = trim((string)($cfg[$key] ?? ''));
            if ($value !== '') {
               $lines[] = $label . ': ' . $value;
            }
         }
         if (trim((string)($order['order_no'] ?? '')) !== '') {
            $lines[] = $texts->get_fd_message('purpose') . ': ' . (string)$order['order_no'];
         }
         return implode("\n", $lines);
      }
      if ($method === 'invoice') {
         $text = trim((string)($cfg['payment_invoice_instructions'] ?? ''));
         return $text !== '' ? $text : $texts->get_fd_message('invoice_default');
      }
      if ($method === 'amazon_pay') {
         return $texts->get_fd_message('amazon_default');
      }
      if ($method === 'paypal') {
         return $texts->get_fd_message('paypal_default');
      }
      return '';
   }

   private function checkout_payment_help(array $options): string {
      $texts = $this->texts('dbxShop|checkout');
      if ($options === array()) {
         return '<div class="alert alert-warning mb-0">' . $this->h($texts->get_fd_message('payment_none_help')) . '</div>';
      }
      $parts = array();
      if (isset($options['bank_transfer'])) {
         $parts[] = '<div><strong>' . $this->h($options['bank_transfer']) . '</strong><span>'
            . $this->h($texts->get_fd_message('payment_bank_transfer_help')) . '</span></div>';
      }
      if (isset($options['invoice'])) {
         $parts[] = '<div><strong>' . $this->h($options['invoice']) . '</strong><span>'
            . $this->h($texts->get_fd_message('payment_invoice_help')) . '</span></div>';
      }
      if (isset($options['paypal'])) {
         $parts[] = '<div><strong>PayPal</strong><span>'
            . $this->h($texts->get_fd_message('payment_paypal_help')) . '</span></div>';
      }
      if (isset($options['amazon_pay'])) {
         $parts[] = '<div><strong>Amazon Pay</strong><span>'
            . $this->h($texts->get_fd_message('payment_amazon_help')) . '</span></div>';
      }
      return '<div class="dbx-shop-payment-method-help">' . implode('', $parts) . '</div>';
   }

   private function checkout_table_html(string $rows, float $sum): string {
      $texts = $this->texts('dbxShop|checkout');
      return '<div class="dbx-shop-cart table-responsive">'
         . '<table class="table table-sm align-middle">'
         . '<thead><tr><th>' . $this->h($texts->get_fd_message('column_product')) . '</th>'
         . '<th class="text-end">' . $this->h($texts->get_fd_message('column_quantity')) . '</th>'
         . '<th class="text-end">' . $this->h($texts->get_fd_message('column_price')) . '</th>'
         . '<th class="text-end">' . $this->h($texts->get_fd_message('column_shipping')) . '</th>'
         . '<th class="text-end">' . $this->h($texts->get_fd_message('column_total')) . '</th></tr></thead>'
         . '<tbody>' . $rows . '</tbody>'
         . '<tfoot><tr><th colspan="4" class="text-end">' . $this->h($texts->get_fd_message('amount_due'))
         . '</th><th class="text-end">' . $this->money($sum) . '</th></tr></tfoot>'
         . '</table></div>';
   }

   private function legal_snapshots_for_order(): array {
      $cfg = $this->shop_config();
      if (!$this->settings_bool($cfg, 'legal_snapshot_enabled', true)) {
         return array('', '');
      }
      $db = $this->content_db();
      if (!is_object($db)) {
         return array('', '');
      }
      $pages = $this->ensure_shop_legal_pages();
      $dd = \dbx\dbxContent\dbxContentLng::dd_content();
      $snapshot = function(string $key) use ($db, $pages, $dd): string {
         $cid = (int)($pages[$key] ?? 0);
         if ($cid <= 0) {
            return '';
         }
         $row = $db->select1($dd, $cid, 'title,permalink,content,update_date', 0);
         if (!is_array($row)) {
            return '';
         }
         return json_encode(array(
            'captured_at' => date('Y-m-d H:i:s'),
            'title' => (string)($row['title'] ?? ''),
            'permalink' => (string)($row['permalink'] ?? ''),
            'update_date' => (string)($row['update_date'] ?? ''),
            'content' => (string)($row['content'] ?? ''),
         ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
      };
      return array($snapshot('legal'), $snapshot('withdrawal'));
   }

   private function order_success_page(array $order, string $payment_method): string {
      dbxShopSessionState::set_last_order_no((string)($order['order_no'] ?? ''));
      $texts = $this->texts('dbxShop|checkout');
      $method_labels = $this->payment_method_labels();
      $instructions = trim($this->payment_instructions($payment_method, $order));
      $body = '<section class="dbx-shop-order-success">'
         . '<div class="dbx-shop-order-success-icon"><i class="bi bi-check2-circle"></i></div>'
         . '<h2>' . $this->h($texts->get_fd_message('order_saved_title')) . '</h2>'
         . '<p>' . $this->h($texts->get_fd_message('order_number_text')) . ' <strong>' . $this->h($order['order_no'] ?? '') . '</strong>.</p>'
         . '<dl>'
         . '<dt>' . $this->h($texts->get_fd_message('payment_method_label')) . '</dt><dd>' . $this->h($method_labels[$payment_method] ?? $payment_method) . '</dd>'
         . '<dt>' . $this->h($texts->get_fd_message('status_label')) . '</dt><dd>' . $this->h($texts->get_fd_message('order_waiting')) . '</dd>'
         . '<dt>' . $this->h($texts->get_fd_message('total_label')) . '</dt><dd>' . $this->money($order['total_gross'] ?? 0) . '</dd>'
         . '</dl>'
         . ($instructions !== '' ? '<div class="alert alert-info text-start"><strong>' . $this->h($texts->get_fd_message('payment_note')) . '</strong><br>' . nl2br($this->h($instructions)) . '</div>' : '')
         . '<div class="dbx-shop-order-success-actions">'
         . '<a class="btn btn-primary" href="?dbx_modul=dbxShop&amp;dbx_run1=orders"><i class="bi bi-receipt"></i> ' . $this->h($texts->get_fd_message('view_orders')) . '</a>'
         . '<a class="btn btn-outline-secondary" href="?dbx_modul=dbxShop&amp;dbx_run1=catalog"><i class="bi bi-grid"></i> ' . $this->h($texts->get_fd_message('continue_shopping')) . '</a>'
         . '</div>'
         . '</section>';
      return $this->page(
         $texts->get_fd_message('thanks_title'),
         $texts->get_fd_message('saved_snapshot_subtitle'),
         $body,
         'orders'
      );
   }
}
