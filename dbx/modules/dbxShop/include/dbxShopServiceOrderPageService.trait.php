<?php
namespace dbx\dbxShop;

trait dbxShopServiceOrderPageServiceTrait {

   private function publicOrderCard(array $order): string {
      $texts = $this->texts('dbxShop|shop-orders');
      $items = '';
      foreach ((array)($order['items'] ?? array()) as $item) {
         $items .= '<li><span>' . (int)($item['qty'] ?? 0) . 'x ' . $this->h($item['title'] ?? '') . '</span><strong>' . $this->money($item['total_gross'] ?? 0) . '</strong></li>';
      }
      if ($items === '') {
         $items = '<li><span>' . $this->h($texts->get_fd_message('no_items')) . '</span><strong></strong></li>';
      }
      $statusLabels = array(
         'new' => $texts->get_fd_message('status_new'),
         'payment_pending' => $texts->get_fd_message('status_payment_pending'),
         'paid' => $texts->get_fd_message('status_paid'),
         'processing' => $texts->get_fd_message('status_processing'),
         'shipped' => $texts->get_fd_message('status_shipped'),
         'done' => $texts->get_fd_message('status_done'),
         'cancelled' => $texts->get_fd_message('status_cancelled'),
      );
      $shippingLabels = array(
         'open' => $texts->get_fd_message('shipping_open'),
         'ready' => $texts->get_fd_message('shipping_ready'),
         'shipped' => $texts->get_fd_message('shipping_shipped'),
         'delivered' => $texts->get_fd_message('shipping_delivered'),
         'returned' => $texts->get_fd_message('shipping_returned'),
      );
      $withdrawalLabels = array(
         'new' => $texts->get_fd_message('withdrawal_new'),
         'processing' => $texts->get_fd_message('withdrawal_processing'),
         'accepted' => $texts->get_fd_message('withdrawal_accepted'),
         'rejected' => $texts->get_fd_message('withdrawal_rejected'),
         'refunded' => $texts->get_fd_message('withdrawal_refunded'),
         'closed' => $texts->get_fd_message('withdrawal_closed'),
      );
      $historyLabels = array(
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
      $paymentStatus = (string)($order['payment_status'] ?? '');
      $paymentStatusLabel = $texts->get_fd_message('payment_status_' . $paymentStatus, $paymentStatus);
      $invoice = trim((string)($order['invoice_no'] ?? ''));
      $trackingNo = trim((string)($order['tracking_no'] ?? ''));
      $trackingUrl = trim((string)($order['tracking_url'] ?? ''));
      $channel = (string)($order['channel_key'] ?? 'shop');
      $extra = '';
      $extra .= '<span>' . $this->h($texts->get_fd_message('origin')) . ': ' . $this->h($this->paymentProviderLabel($channel)) . '</span>';
      if ($invoice !== '') {
         $extra .= '<span>' . $this->h($texts->get_fd_message('invoice')) . ': ' . $this->h($invoice) . '</span>';
      }
      $extra .= '<span>' . $this->h($texts->get_fd_message('shipping')) . ': '
         . $this->h($shippingLabels[(string)($order['shipping_status'] ?? 'open')] ?? (string)($order['shipping_status'] ?? 'open')) . '</span>';
      if ($trackingNo !== '') {
         $trackingText = $this->h($texts->get_fd_message('tracking')) . ': ' . $this->h($trackingNo);
         $extra .= $trackingUrl !== ''
            ? '<span><a href="' . $this->h($trackingUrl) . '" target="_blank" rel="noopener">' . $trackingText . '</a></span>'
            : '<span>' . $trackingText . '</span>';
      }
      $withdrawalsHtml = '';
      foreach ((array)($order['withdrawals'] ?? array()) as $withdrawal) {
         $status = (string)($withdrawal['status'] ?? 'new');
         $created = trim((string)($withdrawal['create_date'] ?? ''));
         $withdrawalsHtml .= '<li><span><strong>' . $this->h($withdrawalLabels[$status] ?? $status) . '</strong>' . ($created !== '' ? '<small>' . $this->h($created) . '</small>' : '') . '</span></li>';
      }
      if ($withdrawalsHtml !== '') {
         $withdrawalsHtml = '<section class="dbx-shop-public-order-withdrawals"><h4><i class="bi bi-arrow-counterclockwise"></i> '
            . $this->h($texts->get_fd_message('withdrawals')) . '</h4><ul>' . $withdrawalsHtml . '</ul></section>';
      }
      $historyHtml = '';
      $historyCount = 0;
      foreach ((array)($order['history'] ?? array()) as $history) {
         if ($historyCount >= 6) {
            break;
         }
         $type = (string)($history['event_type'] ?? '');
         $created = trim((string)($history['create_date'] ?? ''));
         $message = trim((string)($history['message'] ?? ''));
         $old = trim((string)($history['old_value'] ?? ''));
         $new = trim((string)($history['new_value'] ?? ''));
         $detail = $message !== '' ? $message : trim($old . ($old !== '' && $new !== '' ? ' -> ' : '') . $new);
         $historyHtml .= '<li><span><strong>' . $this->h($historyLabels[$type] ?? $type) . '</strong>' . ($detail !== '' ? '<small>' . $this->h($detail) . '</small>' : '') . '</span>' . ($created !== '' ? '<time>' . $this->h($created) . '</time>' : '') . '</li>';
         $historyCount++;
      }
      if ($historyHtml !== '') {
         $historyHtml = '<section class="dbx-shop-public-order-history"><h4><i class="bi bi-clock-history"></i> '
            . $this->h($texts->get_fd_message('history')) . '</h4><ol>' . $historyHtml . '</ol></section>';
      }
      $instructions = trim($this->paymentInstructions((string)($order['payment_provider'] ?? ''), $order));
      $invoiceLink = '';
      $canInvoice = $invoice !== '' || in_array((string)($order['status'] ?? ''), array('paid', 'processing', 'shipped', 'done'), true);
      if ($canInvoice) {
         $invoiceLink = '<a class="btn btn-outline-primary btn-sm" href="?dbx_modul=dbxShop&amp;dbx_run1=invoice_pdf&amp;order_no=' . rawurlencode((string)($order['order_no'] ?? '')) . '" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf"></i> '
            . $this->h($texts->get_fd_message('invoice')) . '</a>';
      }
      return '<article class="dbx-shop-public-order">'
         . '<header><div><strong>' . $this->h($order['order_no'] ?? '') . '</strong><small>' . $this->h($order['create_date'] ?? '') . '</small></div><span class="badge text-bg-primary">' . $this->h($statusLabels[(string)($order['status'] ?? '')] ?? ($order['status'] ?? '')) . '</span></header>'
         . '<ul>' . $items . '</ul>'
         . '<footer><span>' . $this->h($texts->get_fd_message('payment')) . ': '
         . $this->h($this->paymentProviderLabel((string)($order['payment_provider'] ?? ''))) . ' / '
         . $this->h($paymentStatusLabel) . '</span><strong>' . $this->money($order['total_gross'] ?? 0) . '</strong></footer>'
         . ($extra !== '' ? '<div class="dbx-shop-public-order-extra">' . $extra . '</div>' : '')
         . ($instructions !== '' && in_array((string)($order['payment_status'] ?? ''), array('open', 'created', 'pending'), true)
            ? '<div class="alert alert-info py-2 my-2"><strong>' . $this->h($texts->get_fd_message('payment_note')) . '</strong><br>' . nl2br($this->h($instructions)) . '</div>'
            : '')
         . $withdrawalsHtml
         . $historyHtml
         . '<div class="dbx-shop-public-order-actions">' . $invoiceLink
         . '<a class="btn btn-outline-secondary btn-sm" href="?dbx_modul=dbxShop&amp;dbx_run1=withdrawal"><i class="bi bi-arrow-counterclockwise"></i> '
         . $this->h($texts->get_fd_message('withdrawal')) . '</a></div>'
         . '</article>';
   }

   private function startPayPalForOrder(array $order): string {
      $returnUrl = $this->absoluteShopUrl('paypal_return', array('order_no' => (string)$order['order_no']));
      $cancelUrl = $this->absoluteShopUrl('paypal_cancel', array('order_no' => (string)$order['order_no']));
      $paypalOrder = $this->readJsonArray($order['payment_payload'] ?? '');
      $paypalId = (string)($order['payment_reference'] ?? '');
      $approvalUrl = $paypalId !== '' ? $this->paypal()->approvalUrl($paypalOrder) : '';
      if ($paypalId === '' || $approvalUrl === '') {
         $paypalOrder = $this->paypal()->createOrder($order, $returnUrl, $cancelUrl);
      }
      $paypalId = (string)($paypalOrder['id'] ?? '');
      if ($paypalId === ''
         || !$this->repo()->updateOrderPayment((int)$order['id'], 'paypal', 'created', $paypalId, $paypalOrder)) {
         throw new \RuntimeException('PayPal-Zahlungsreferenz konnte nicht sicher gespeichert werden.');
      }
      $approvalUrl = $this->paypal()->approvalUrl($paypalOrder);
      if ($approvalUrl === '') {
         throw new \RuntimeException('PayPal hat keinen Freigabe-Link geliefert.');
      }
      if (!headers_sent()) {
         header('Location: ' . $approvalUrl, true, 302);
         exit;
      }
      return '<a class="btn btn-primary" href="' . $this->h($approvalUrl) . '">Weiter zu PayPal</a>';
   }

   private function startAmazonPayForOrder(array $order): string {
      $returnUrl = $this->absoluteShopUrl('amazon_pay_return', array('order_no' => (string)$order['order_no']));
      $cancelUrl = $this->absoluteShopUrl('amazon_pay_cancel', array('order_no' => (string)$order['order_no']));
      $checkoutSession = $this->readJsonArray($order['payment_payload'] ?? '');
      $checkoutSessionId = (string)($order['payment_reference'] ?? '');
      $redirectUrl = $checkoutSessionId !== '' ? $this->amazonPay()->redirectUrl($checkoutSession) : '';
      if ($checkoutSessionId === '' || $redirectUrl === '') {
         $checkoutSession = $this->amazonPay()->createCheckoutSession($order, $returnUrl, $cancelUrl);
      }
      $checkoutSessionId = (string)($checkoutSession['checkoutSessionId'] ?? $checkoutSession['id'] ?? '');
      if ($checkoutSessionId === ''
         || !$this->repo()->updateOrderPayment((int)$order['id'], 'amazon_pay', 'created', $checkoutSessionId, $checkoutSession)) {
         throw new \RuntimeException('Amazon-Pay-Referenz konnte nicht sicher gespeichert werden.');
      }
      $redirectUrl = $this->amazonPay()->redirectUrl($checkoutSession);
      if ($redirectUrl === '') {
         throw new \RuntimeException('Amazon Pay hat keinen Redirect-Link geliefert.');
      }
      if (!headers_sent()) {
         header('Location: ' . $redirectUrl, true, 302);
         exit;
      }
      return '<a class="btn btn-primary" href="' . $this->h($redirectUrl) . '">Weiter zu Amazon Pay</a>';
   }

   private function continueCheckoutOrder(array $order, string $paymentMethod): string {
      $storedMethod = trim((string)($order['payment_provider'] ?? ''));
      if ($storedMethod !== '') $paymentMethod = $storedMethod;
      if ($paymentMethod === 'paypal') {
         return $this->startPayPalForOrder($order);
      }
      if ($paymentMethod === 'amazon_pay') {
         return $this->startAmazonPayForOrder($order);
      }

      $this->sendOrderMails($order);
      $this->startSession();
      $_SESSION['dbxShop_cart'] = array();
      return $this->orderSuccessPage($order, $paymentMethod);
   }

   private function orderIsPublicAccessible(array $order): bool {
      $this->startSession();
      $orderNo = (string)($order['order_no'] ?? '');
      if ($orderNo !== '' && $orderNo === (string)($_SESSION['dbxShop_last_order_no'] ?? '')) {
         return true;
      }
      $uid = function_exists('dbx') ? (int)dbx()->user() : 0;
      return $uid > 0 && (int)($order['uid'] ?? 0) === $uid;
   }
}
