<?php
namespace dbx\dbxShop;

trait dbxShopServiceCartServiceTrait {

   private function start_session(): void {
      dbxShopSessionState::ensure();
   }

   private function checkout_request_id(): string {
      $posted = strtolower(trim((string)($_POST['checkout_request_id'] ?? '')));
      if (preg_match('/^[a-f0-9]{32,64}$/', $posted) === 1) {
         return $posted;
      }
      return bin2hex(random_bytes(24));
   }

   private function checkout_request_order(string $request_id): ?array {
      $this->start_session();
      $order_no = dbxShopSessionState::checkout_order_no($request_id);
      return $order_no !== '' ? $this->repo()->order_by_no($order_no) : null;
   }

   private function remember_checkout_request(string $request_id, array $order): void {
      $this->start_session();
      dbxShopSessionState::remember_checkout($request_id, (string)($order['order_no'] ?? ''));
   }

   private function cart_items(): array {
      $this->start_session();
      return dbxShopSessionState::cart();
   }

   private function cart_quantity_total(): int {
      $count = 0;
      foreach ($this->cart_items() as $qty) {
         $count += max(0, (int)$qty);
      }
      return $count;
   }

   private function requested_quantity($value, int $fallback = 1): int {
      $qty = (int)$value;
      return max(1, min(999, $qty > 0 ? $qty : $fallback));
   }

   private function add_to_cart(string $sku, int $qty = 1): void {
      if ($sku === '') {
         return;
      }
      $product = $this->repo()->product_by_sku($sku);
      if (!$product) {
         return;
      }
      $this->start_session();
      dbxShopSessionState::add_quantity($sku, $this->requested_quantity($qty));
   }

   private function update_cart_quantities(array $quantities): void {
      $this->start_session();
      foreach ($quantities as $sku => $qty) {
         $sku = (string)$sku;
         if (!dbxShopSessionState::has_sku($sku)) {
            continue;
         }
         dbxShopSessionState::set_quantity($sku, $this->requested_quantity($qty));
      }
   }

   private function remove_from_cart(string $sku): void {
      $sku = trim($sku);
      if ($sku === '') {
         return;
      }
      $this->start_session();
      dbxShopSessionState::remove_sku($sku);
   }

   private function added_to_cart_dialog(array $product): string {
      $texts = $this->texts('dbxShop|shop-cart');
      $title = trim((string)($product['title'] ?? $texts->get_fd_message('product')));
      $qty = $this->requested_quantity(dbx()->get_modul_var('qty', '1', 'parameter'));
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

   private function cart_rows_and_sum(bool $editable = false): array {
      $rows = '';
      $sum = 0.0;
      foreach ($this->cart_items() as $sku => $qty) {
         $product = $this->repo()->product_by_sku((string)$sku);
         if (!$product) {
            continue;
         }
         $qty = max(1, (int)$qty);
         $price = (float)($product['price_gross'] ?? 0);
         $shipping = (float)($product['effective_shipping_gross'] ?? 0);
         $line = ($price + $shipping) * $qty;
         $sum += $line;
         $qty_html = $editable
            ? '<input class="form-control form-control-sm dbx-shop-cart-qty" type="number" min="1" max="999" step="1" name="qty[' . $this->h($sku) . ']" value="' . (int)$qty . '">'
            : (string)(int)$qty;
         $rows .= '<tr>';
         $rows .= '<td><strong>' . $this->h($product['title'] ?? '') . '</strong><br><small>' . $this->h($sku) . '</small></td>';
         $rows .= '<td class="text-end">' . $qty_html . '</td>';
         $rows .= '<td class="text-end">' . $this->money($price) . '</td>';
         $rows .= '<td class="text-end">' . $this->money($shipping) . '</td>';
         $rows .= '<td class="text-end">' . $this->money($line) . '</td>';
         $rows .= '</tr>';
      }
      return array($rows, $sum);
   }

   private function cart_report_data_and_sum($texts = null): array {
      $texts = $texts ?: $this->texts('dbxShop|shop-cart');
      $rows = array();
      $sum = 0.0;
      foreach ($this->cart_items() as $sku => $qty) {
         $product = $this->repo()->product_by_sku((string)$sku);
         if (!$product) {
            continue;
         }
         $qty = max(1, (int)$qty);
         $price = (float)($product['price_gross'] ?? 0);
         $shipping = (float)($product['effective_shipping_gross'] ?? 0);
         $line = ($price + $shipping) * $qty;
         $sum += $line;
         $sku_text = (string)$sku;
         $rows[] = array(
            'id' => $sku_text,
            'remove' => '<button class="btn btn-sm btn-outline-danger dbxConfirm dbx-shop-cart-remove" type="submit" name="remove" value="' . $this->h($sku_text)
               . '" data-confirm-title="<i class=\'bi bi-trash\'></i> ' . $this->h($texts->get_fd_message('remove_title'))
               . '" data-confirm="' . $this->h($texts->get_fd_message('remove_question'))
               . '" data-confirm-hint="<small>' . $this->h($texts->get_fd_message('remove_hint'))
               . '</small>" data-confirm-buttons="yesno" title="' . $this->h($texts->get_fd_message('remove_title'))
               . '"><i class="bi bi-trash"></i></button>',
            'article' => '<strong>' . $this->h($product['title'] ?? '') . '</strong><br><small>' . $this->h($sku_text) . '</small>',
            'qty' => '<input class="form-control form-control-sm dbx-shop-cart-qty" type="number" min="1" max="999" step="1" name="qty[' . $this->h($sku_text) . ']" value="' . (int)$qty . '">',
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
   private function cart_report(array $rows, float $sum): \dbxReport {
      $report = dbx()->get_system_obj('dbxReport');
      $report->init('shop-cart-report', 'dbxShop|shop-cart-report');
      $report->set_field_definition('dbxShop|shop-cart');
      $report->load_fd_messages();
      $report->set_editor_class_file(__FILE__);
      $report->set_action('?dbx_modul=dbxShop&dbx_run1=cart');
      $report->set_mode('table');
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
      $report->add_rep('cart_count', (string)$this->cart_quantity_total());
      return $report;
   }

   private function cart_report_html(array $rows, float $sum, ?\dbxReport $report = null): string {
      if (!$report) {
         $report = $this->cart_report($rows, $sum);
      } else {
         // Nach einer gültigen Aktion nur Ergebnisdaten erneuern. Ein zweites
         // init() würde den geprüften Submit-Zustand und den rotierten Token
         // verwerfen.
         $report->_rdata = $rows;
         $report->_rcount = count($rows);
         $report->_count_all = count($rows);
         $report->_rrows = max(1, count($rows));
         $report->add_rep('cart_sum', $this->money($sum));
         $report->add_rep('cart_count', (string)$this->cart_quantity_total());
      }
      return $report->run();
   }

   private function cart_body_html(?\dbxReport $report = null, $texts = null): string {
      $texts = $texts ?: $this->texts('dbxShop|shop-cart');
      [$report_rows, $sum] = $this->cart_report_data_and_sum($texts);

      if ($report_rows === array()) {
         return '<div class="dbx-shop-cart-empty" data-dbx-shop-cart-count="0">'
            . $this->placeholder(
               $texts->get_fd_message('empty_title'),
               $texts->get_fd_message('empty_message')
            )
            . '</div>';
      }

      return $this->cart_report_html($report_rows, $sum, $report);
   }
}
