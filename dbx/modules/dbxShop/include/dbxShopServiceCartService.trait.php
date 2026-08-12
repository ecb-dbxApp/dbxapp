<?php
namespace dbx\dbxShop;

trait dbxShopServiceCartServiceTrait {

   private function startSession(): void {
      if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
         session_start();
      }
      if (!isset($_SESSION['dbxShop_cart']) || !is_array($_SESSION['dbxShop_cart'])) {
         $_SESSION['dbxShop_cart'] = array();
      }
   }

   private function checkoutRequestId(): string {
      $posted = strtolower(trim((string)($_POST['checkout_request_id'] ?? '')));
      if (preg_match('/^[a-f0-9]{32,64}$/', $posted) === 1) {
         return $posted;
      }
      return bin2hex(random_bytes(24));
   }

   private function checkoutRequestOrder(string $requestId): ?array {
      $this->startSession();
      $requests = $_SESSION['dbxShop_checkout_requests'] ?? array();
      $orderNo = is_array($requests) ? (string)($requests[$requestId] ?? '') : '';
      return $orderNo !== '' ? $this->repo()->orderByNo($orderNo) : null;
   }

   private function rememberCheckoutRequest(string $requestId, array $order): void {
      $this->startSession();
      $requests = $_SESSION['dbxShop_checkout_requests'] ?? array();
      $requests = is_array($requests) ? $requests : array();
      $requests[$requestId] = (string)($order['order_no'] ?? '');
      $_SESSION['dbxShop_checkout_requests'] = array_slice($requests, -25, null, true);
   }

   private function cartItems(): array {
      $this->startSession();
      return $_SESSION['dbxShop_cart'];
   }

   private function cartQuantityTotal(): int {
      $count = 0;
      foreach ($this->cartItems() as $qty) {
         $count += max(0, (int)$qty);
      }
      return $count;
   }

   private function requestedQuantity($value, int $fallback = 1): int {
      $qty = (int)$value;
      return max(1, min(999, $qty > 0 ? $qty : $fallback));
   }

   private function addToCart(string $sku, int $qty = 1): void {
      if ($sku === '') {
         return;
      }
      $product = $this->repo()->productBySku($sku);
      if (!$product) {
         return;
      }
      $this->startSession();
      $_SESSION['dbxShop_cart'][$sku] = max(0, (int)($_SESSION['dbxShop_cart'][$sku] ?? 0)) + $this->requestedQuantity($qty);
   }

   private function updateCartQuantities(array $quantities): void {
      $this->startSession();
      foreach ($quantities as $sku => $qty) {
         $sku = (string)$sku;
         if (!isset($_SESSION['dbxShop_cart'][$sku])) {
            continue;
         }
         $_SESSION['dbxShop_cart'][$sku] = $this->requestedQuantity($qty);
      }
   }

   private function removeFromCart(string $sku): void {
      $sku = trim($sku);
      if ($sku === '') {
         return;
      }
      $this->startSession();
      unset($_SESSION['dbxShop_cart'][$sku]);
   }

   private function addedToCartDialog(array $product): string {
      $texts = $this->texts('dbxShop|shop-cart');
      $title = trim((string)($product['title'] ?? $texts->get_fd_message('product')));
      $qty = $this->requestedQuantity(dbx()->get_modul_var('qty', '1', 'parameter'));
      $body = '<div class="dbx-shop-added-dialog" role="dialog" aria-modal="true" aria-labelledby="dbx-shop-added-title">';
      $body .= '<div class="dbx-shop-added-dialog-backdrop"></div>';
      $body .= '<div class="dbx-shop-added-dialog-box">';
      $body .= '<div class="dbx-shop-added-dialog-icon"><i class="bi bi-check2"></i></div>';
      $body .= '<h3 id="dbx-shop-added-title">'
         . $this->h($texts->get_fd_message('added_title'))
         . '</h3>';
      $body .= '<p>' . $this->h($title) . ' <span class="dbx-shop-added-qty">x ' . (int)$qty . '</span></p>';
      $body .= '<div class="dbx-shop-added-dialog-actions">';
      $body .= '<a class="btn btn-outline-primary" href="?dbx_modul=dbxShop&amp;dbx_run1=cart"><i class="bi bi-cart"></i> '
         . $this->h($texts->get_fd_message('cart_title')) . '</a>';
      $body .= '<a class="btn btn-primary" href="?dbx_modul=dbxShop&amp;dbx_run1=checkout"><i class="bi bi-credit-card"></i> '
         . $this->h($texts->get_fd_message('checkout')) . '</a>';
      $body .= '<a class="btn btn-outline-secondary" href="?dbx_modul=dbxShop&amp;dbx_run1=catalog"><i class="bi bi-grid"></i> '
         . $this->h($texts->get_fd_message('continue_shopping')) . '</a>';
      $body .= '</div>';
      $body .= '</div>';
      $body .= '</div>';
      return $body;
   }

   private function cartRowsAndSum(bool $editable = false): array {
      $rows = '';
      $sum = 0.0;
      foreach ($this->cartItems() as $sku => $qty) {
         $product = $this->repo()->productBySku((string)$sku);
         if (!$product) {
            continue;
         }
         $qty = max(1, (int)$qty);
         $price = (float)($product['price_gross'] ?? 0);
         $shipping = (float)($product['effective_shipping_gross'] ?? 0);
         $line = ($price + $shipping) * $qty;
         $sum += $line;
         $qtyHtml = $editable
            ? '<input class="form-control form-control-sm dbx-shop-cart-qty" type="number" min="1" max="999" step="1" name="qty[' . $this->h($sku) . ']" value="' . (int)$qty . '">'
            : (string)(int)$qty;
         $rows .= '<tr>';
         $rows .= '<td><strong>' . $this->h($product['title'] ?? '') . '</strong><br><small>' . $this->h($sku) . '</small></td>';
         $rows .= '<td class="text-end">' . $qtyHtml . '</td>';
         $rows .= '<td class="text-end">' . $this->money($price) . '</td>';
         $rows .= '<td class="text-end">' . $this->money($shipping) . '</td>';
         $rows .= '<td class="text-end">' . $this->money($line) . '</td>';
         $rows .= '</tr>';
      }
      return array($rows, $sum);
   }

   private function cartReportDataAndSum($texts = null): array {
      $texts = $texts ?: $this->texts('dbxShop|shop-cart');
      $rows = array();
      $sum = 0.0;
      foreach ($this->cartItems() as $sku => $qty) {
         $product = $this->repo()->productBySku((string)$sku);
         if (!$product) {
            continue;
         }
         $qty = max(1, (int)$qty);
         $price = (float)($product['price_gross'] ?? 0);
         $shipping = (float)($product['effective_shipping_gross'] ?? 0);
         $line = ($price + $shipping) * $qty;
         $sum += $line;
         $skuText = (string)$sku;
         $rows[] = array(
            'id' => $skuText,
            'remove' => '<button class="btn btn-sm btn-outline-danger dbxConfirm dbx-shop-cart-remove" type="submit" name="remove" value="' . $this->h($skuText)
               . '" data-confirm-title="<i class=\'bi bi-trash\'></i> ' . $this->h($texts->get_fd_message('remove_title'))
               . '" data-confirm="' . $this->h($texts->get_fd_message('remove_question'))
               . '" data-confirm-hint="<small>' . $this->h($texts->get_fd_message('remove_hint'))
               . '</small>" data-confirm-buttons="yesno" title="' . $this->h($texts->get_fd_message('remove_title'))
               . '"><i class="bi bi-trash"></i></button>',
            'article' => '<strong>' . $this->h($product['title'] ?? '') . '</strong><br><small>' . $this->h($skuText) . '</small>',
            'qty' => '<input class="form-control form-control-sm dbx-shop-cart-qty" type="number" min="1" max="999" step="1" name="qty[' . $this->h($skuText) . ']" value="' . (int)$qty . '">',
            'price' => '<span class="dbx-shop-money">' . $this->money($price) . '</span>',
            'shipping' => '<span class="dbx-shop-money">' . $this->money($shipping) . '</span>',
            'line' => '<span class="dbx-shop-money"><strong>' . $this->money($line) . '</strong></span>',
         );
      }
      return array($rows, $sum);
   }

   /**
    * Baut den Warenkorb als zustandsbehaftetes dbxReport-Formular.
    *
    * Bei einem POST wird dieses Objekt vor der Mutation erzeugt. Dadurch
    * prüft dbxReport genau den Security-Wert, den der Warenkorb gerendert hat.
    */
   private function cartReport(array $rows, float $sum): \dbxReport {
      $report = dbx()->get_system_obj('dbxReport');
      $report->init('shop-cart-report', 'dbxShop|shop-cart-report');
      $report->_fd = 'dbxShop|shop-cart';
      $report->load_fd_messages();
      $report->set_editor_class_file(__FILE__);
      $report->_action = '?dbx_modul=dbxShop&dbx_run1=cart';
      $report->_mode = 'table';
      $report->_pages = false;
      $report->_rdata = $rows;
      $report->_rcount = count($rows);
      $report->_count_all = count($rows);
      $report->_rrows = max(1, count($rows));
      $report->_rpos = 0;
      $report->_create_row_select = false;
      $report->_create_row_edit = false;
      $report->_create_row_delete = false;
      $report->_rflds = array(
         'remove' => $report->get_fd_message('column_action'),
         'article' => $report->get_fd_message('column_article'),
         'qty' => $report->get_fd_message('column_quantity'),
         'price' => $report->get_fd_message('column_price'),
         'shipping' => $report->get_fd_message('column_shipping'),
         'line' => $report->get_fd_message('column_total'),
      );
      $report->_rpt_format = array(
         'remove' => 'html',
         'article' => 'html',
         'qty' => 'html',
         'price' => 'html',
         'shipping' => 'html',
         'line' => 'html',
      );
      $report->add_rep('bar_title', $report->get_fd_message('cart_title'));
      $report->add_rep('cart_sum', $this->money($sum));
      $report->add_rep('cart_count', (string)$this->cartQuantityTotal());
      return $report;
   }

   private function cartReportHtml(array $rows, float $sum, ?\dbxReport $report = null): string {
      if (!$report) {
         $report = $this->cartReport($rows, $sum);
      } else {
         // Nach einer gültigen Aktion nur Ergebnisdaten erneuern. Ein zweites
         // init() würde den geprüften Submit-Zustand und den rotierten Token
         // verwerfen.
         $report->_rdata = $rows;
         $report->_rcount = count($rows);
         $report->_count_all = count($rows);
         $report->_rrows = max(1, count($rows));
         $report->add_rep('cart_sum', $this->money($sum));
         $report->add_rep('cart_count', (string)$this->cartQuantityTotal());
      }
      return $report->run();
   }

   private function cartBodyHtml(?\dbxReport $report = null, $texts = null): string {
      $texts = $texts ?: $this->texts('dbxShop|shop-cart');
      [$reportRows, $sum] = $this->cartReportDataAndSum($texts);

      if ($reportRows === array()) {
         return '<div class="dbx-shop-cart-empty" data-dbx-shop-cart-count="0">'
            . $this->placeholder(
               $texts->get_fd_message('empty_title'),
               $texts->get_fd_message('empty_message')
            )
            . '</div>';
      }

      return $this->cartReportHtml($reportRows, $sum, $report);
   }
}
