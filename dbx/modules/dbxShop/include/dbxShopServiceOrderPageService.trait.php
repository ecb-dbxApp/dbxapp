<?php
namespace dbx\dbxShop;

trait dbxShopServiceOrderPageServiceTrait {

   private function public_order_card(array $order): string {
      $texts = $this->texts('dbxShop|shop-orders');
      $items = '';
      foreach ((array)($order['items'] ?? array()) as $item) {
         $items .= '<li><span>' . (int)($item['qty'] ?? 0) . 'x ' . $this->h($item['title'] ?? '') . '</span><strong>' . $this->money($item['total_gross'] ?? 0) . '</strong></li>';
      }
      if ($items === '') {
         $items = '<li><span>' . $this->h($texts->get_fd_message('no_items')) . '</span><strong></strong></li>';
      }
      $status_labels = array(
         'new' => $texts->get_fd_message('status_new'),
         'payment_pending' => $texts->get_fd_message('status_payment_pending'),
         'paid' => $texts->get_fd_message('status_paid'),
         'processing' => $texts->get_fd_message('status_processing'),
         'shipped' => $texts->get_fd_message('status_shipped'),
         'done' => $texts->get_fd_message('status_done'),
         'cancelled' => $texts->get_fd_message('status_cancelled'),
      );
      $shipping_labels = array(
         'open' => $texts->get_fd_message('shipping_open'),
         'ready' => $texts->get_fd_message('shipping_ready'),
         'shipped' => $texts->get_fd_message('shipping_shipped'),
         'delivered' => $texts->get_fd_message('shipping_delivered'),
         'returned' => $texts->get_fd_message('shipping_returned'),
      );
      $withdrawal_labels = array(
         'new' => $texts->get_fd_message('withdrawal_new'),
         'processing' => $texts->get_fd_message('withdrawal_processing'),
         'accepted' => $texts->get_fd_message('withdrawal_accepted'),
         'rejected' => $texts->get_fd_message('withdrawal_rejected'),
         'refunded' => $texts->get_fd_message('withdrawal_refunded'),
         'closed' => $texts->get_fd_message('withdrawal_closed'),
      );
      $history_labels = array(
         'created' => $texts->get_fd_message('history_created'),
         'status' => $texts->get_fd_message('history_status'),
         'payment_status' => $texts->get_fd_message('history_payment_status'),
         'shipping_status' => $texts->get_fd_message('history_shipping_status'),
         'invoice_no' => $texts->get_fd_message('history_invoice_no'),
         'tracking_no' => $texts->get_fd_message('history_tracking_no'),
         'payment' => $texts->get_fd_message('history_payment'),
         'customer_mail' => $texts->get_fd_message('history_customer_mail'),
         'withdrawal' => $texts->get_fd_message('history_withdrawal'),
         'withdrawal_status' => $texts->get_fd_message('history_withdrawal_status'),
         'stock_release' => $texts->get_fd_message('history_stock_release'),
         'invoice_pdf' => $texts->get_fd_message('history_invoice_pdf'),
      );
      $payment_status = (string)($order['payment_status'] ?? '');
      $payment_status_label = $texts->get_fd_message('payment_status_' . $payment_status, $payment_status);
      $invoice = trim((string)($order['invoice_no'] ?? ''));
      $tracking_no = trim((string)($order['tracking_no'] ?? ''));
      $tracking_url = trim((string)($order['tracking_url'] ?? ''));
      $channel = (string)($order['channel_key'] ?? 'shop');
      $extra = '';
      $extra .= '<span>' . $this->h($texts->get_fd_message('origin')) . ': ' . $this->h($this->payment_provider_label($channel)) . '</span>';
      if ($invoice !== '') {
         $extra .= '<span>' . $this->h($texts->get_fd_message('invoice')) . ': ' . $this->h($invoice) . '</span>';
      }
      $extra .= '<span>' . $this->h($texts->get_fd_message('shipping')) . ': '
         . $this->h($shipping_labels[(string)($order['shipping_status'] ?? 'open')] ?? (string)($order['shipping_status'] ?? 'open')) . '</span>';
      if ($tracking_no !== '') {
         $tracking_text = $this->h($texts->get_fd_message('tracking')) . ': ' . $this->h($tracking_no);
         $extra .= $tracking_url !== ''
            ? '<span><a href="' . $this->h($tracking_url) . '" target="_blank" rel="noopener">' . $tracking_text . '</a></span>'
            : '<span>' . $tracking_text . '</span>';
      }
      $withdrawals_html = '';
      foreach ((array)($order['withdrawals'] ?? array()) as $withdrawal) {
         $status = (string)($withdrawal['status'] ?? 'new');
         $created = trim((string)($withdrawal['create_date'] ?? ''));
         $withdrawals_html .= '<li><span><strong>' . $this->h($withdrawal_labels[$status] ?? $status) . '</strong>' . ($created !== '' ? '<small>' . $this->h($created) . '</small>' : '') . '</span></li>';
      }
      if ($withdrawals_html !== '') {
         $withdrawals_html = '<section class="dbx-shop-public-order-withdrawals"><h4><i class="bi bi-arrow-counterclockwise"></i> '
            . $this->h($texts->get_fd_message('withdrawals')) . '</h4><ul>' . $withdrawals_html . '</ul></section>';
      }
      $history_html = '';
      $history_count = 0;
      foreach ((array)($order['history'] ?? array()) as $history) {
         if ($history_count >= 6) {
            break;
         }
         $type = (string)($history['event_type'] ?? '');
         $created = trim((string)($history['create_date'] ?? ''));
         $message = trim((string)($history['message'] ?? ''));
         $old = trim((string)($history['old_value'] ?? ''));
         $new = trim((string)($history['new_value'] ?? ''));
         $detail = $message !== '' ? $message : trim($old . ($old !== '' && $new !== '' ? ' -> ' : '') . $new);
         $history_html .= '<li><span><strong>' . $this->h($history_labels[$type] ?? $type) . '</strong>' . ($detail !== '' ? '<small>' . $this->h($detail) . '</small>' : '') . '</span>' . ($created !== '' ? '<time>' . $this->h($created) . '</time>' : '') . '</li>';
         $history_count++;
      }
      if ($history_html !== '') {
         $history_html = '<section class="dbx-shop-public-order-history"><h4><i class="bi bi-clock-history"></i> '
            . $this->h($texts->get_fd_message('history')) . '</h4><ol>' . $history_html . '</ol></section>';
      }
      $instructions = trim($this->payment_instructions((string)($order['payment_provider'] ?? ''), $order));
      $invoice_link = '';
      $can_invoice = $invoice !== '' || in_array((string)($order['status'] ?? ''), array('paid', 'processing', 'shipped', 'done'), true);
      if ($can_invoice) {
         $invoice_link = '<a class="btn btn-outline-primary btn-sm" href="?dbx_modul=dbxShop&amp;dbx_run1=invoice_pdf&amp;order_no=' . rawurlencode((string)($order['order_no'] ?? '')) . '" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf"></i> '
            . $this->h($texts->get_fd_message('invoice')) . '</a>';
      }
      return '<article class="dbx-shop-public-order">'
         . '<header><div><strong>' . $this->h($order['order_no'] ?? '') . '</strong><small>' . $this->h($order['create_date'] ?? '') . '</small></div><span class="badge text-bg-primary">' . $this->h($status_labels[(string)($order['status'] ?? '')] ?? ($order['status'] ?? '')) . '</span></header>'
         . '<ul>' . $items . '</ul>'
         . '<footer><span>' . $this->h($texts->get_fd_message('payment')) . ': '
         . $this->h($this->payment_provider_label((string)($order['payment_provider'] ?? ''))) . ' / '
         . $this->h($payment_status_label) . '</span><strong>' . $this->money($order['total_gross'] ?? 0) . '</strong></footer>'
         . ($extra !== '' ? '<div class="dbx-shop-public-order-extra">' . $extra . '</div>' : '')
         . ($instructions !== '' && in_array((string)($order['payment_status'] ?? ''), array('open', 'created', 'pending'), true)
            ? '<div class="alert alert-info py-2 my-2"><strong>' . $this->h($texts->get_fd_message('payment_note')) . '</strong><br>' . nl2br($this->h($instructions)) . '</div>'
            : '')
         . $withdrawals_html
         . $history_html
         . '<div class="dbx-shop-public-order-actions">' . $invoice_link
         . '<a class="btn btn-outline-secondary btn-sm" href="?dbx_modul=dbxShop&amp;dbx_run1=withdrawal"><i class="bi bi-arrow-counterclockwise"></i> '
         . $this->h($texts->get_fd_message('withdrawal')) . '</a></div>'
         . '</article>';
   }

   private function start_pay_pal_for_order(array $order): string {
      $return_url = $this->absolute_shop_url('paypal_return', array('order_no' => (string)$order['order_no']));
      $cancel_url = $this->absolute_shop_url('paypal_cancel', array('order_no' => (string)$order['order_no']));
      $paypal_order = $this->read_json_array($order['payment_payload'] ?? '');
      $paypal_id = (string)($order['payment_reference'] ?? '');
      $approval_url = $paypal_id !== '' ? $this->paypal()->approval_url($paypal_order) : '';
      if ($paypal_id === '' || $approval_url === '') {
         $paypal_order = $this->paypal()->create_order($order, $return_url, $cancel_url);
      }
      $paypal_id = (string)($paypal_order['id'] ?? '');
      if ($paypal_id === ''
         || !$this->repo()->update_order_payment((int)$order['id'], 'paypal', 'created', $paypal_id, $paypal_order)) {
         throw new \RuntimeException('PayPal-Zahlungsreferenz konnte nicht sicher gespeichert werden.');
      }
      $approval_url = $this->paypal()->approval_url($paypal_order);
      if ($approval_url === '') {
         throw new \RuntimeException('PayPal hat keinen Freigabe-Link geliefert.');
      }
      if (!headers_sent()) {
         header('Location: ' . $approval_url, true, 302);
         exit;
      }
      return '<a class="btn btn-primary" href="' . $this->h($approval_url) . '">Weiter zu PayPal</a>';
   }

   private function start_amazon_pay_for_order(array $order): string {
      $return_url = $this->absolute_shop_url('amazon_pay_return', array('order_no' => (string)$order['order_no']));
      $cancel_url = $this->absolute_shop_url('amazon_pay_cancel', array('order_no' => (string)$order['order_no']));
      $checkout_session = $this->read_json_array($order['payment_payload'] ?? '');
      $checkout_session_id = (string)($order['payment_reference'] ?? '');
      $redirect_url = $checkout_session_id !== '' ? $this->amazon_pay()->redirect_url($checkout_session) : '';
      if ($checkout_session_id === '' || $redirect_url === '') {
         $checkout_session = $this->amazon_pay()->create_checkout_session($order, $return_url, $cancel_url);
      }
      $checkout_session_id = (string)($checkout_session['checkoutSessionId'] ?? $checkout_session['id'] ?? '');
      if ($checkout_session_id === ''
         || !$this->repo()->update_order_payment((int)$order['id'], 'amazon_pay', 'created', $checkout_session_id, $checkout_session)) {
         throw new \RuntimeException('Amazon-Pay-Referenz konnte nicht sicher gespeichert werden.');
      }
      $redirect_url = $this->amazon_pay()->redirect_url($checkout_session);
      if ($redirect_url === '') {
         throw new \RuntimeException('Amazon Pay hat keinen Redirect-Link geliefert.');
      }
      if (!headers_sent()) {
         header('Location: ' . $redirect_url, true, 302);
         exit;
      }
      return '<a class="btn btn-primary" href="' . $this->h($redirect_url) . '">Weiter zu Amazon Pay</a>';
   }

   private function continue_checkout_order(array $order, string $payment_method): string {
      $stored_method = trim((string)($order['payment_provider'] ?? ''));
      if ($stored_method !== '') $payment_method = $stored_method;
      if ($payment_method === 'paypal') {
         return $this->start_pay_pal_for_order($order);
      }
      if ($payment_method === 'amazon_pay') {
         return $this->start_amazon_pay_for_order($order);
      }

      $this->send_order_mails($order);
      dbxShopSessionState::clear_cart();
      return $this->order_success_page($order, $payment_method);
   }

   private function order_is_public_accessible(array $order): bool {
      $order_no = (string)($order['order_no'] ?? '');
      if ($order_no !== '' && $order_no === dbxShopSessionState::last_order_no()) {
         return true;
      }
      $uid = function_exists('dbx') ? (int)dbx()->user() : 0;
      return $uid > 0 && (int)($order['uid'] ?? 0) === $uid;
   }
}
