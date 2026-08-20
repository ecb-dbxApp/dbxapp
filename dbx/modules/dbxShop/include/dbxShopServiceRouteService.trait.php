<?php
namespace dbx\dbxShop;

require_once __DIR__ . '/dbxShopSearch.class.php';

trait dbxShopServiceRouteServiceTrait {

   public function catalog(): string {
      $this->ensure_seed();
      $texts = $this->texts('dbxShop|shop-catalog-filter-form');
      $channel = $this->active_channel();
      $query = trim((string)($_GET['q'] ?? ''));
      $group_id = $this->catalog_group_id();
      $attribute_filters = $this->selected_attribute_filters();
      $matches = array();
      $total_count = 0;
      $has_query = dbxShopSearch::terms($query) !== array();
      $current_group = $group_id > 0 ? $this->repo()->group_by_id($group_id) : null;
      if ($group_id > 0 && !is_array($current_group)) {
         $group_id = 0;
      }

      foreach ($this->repo()->catalog_candidates($channel) as $product) {
         if (!$this->product_has_channel($product, $channel)) {
            continue;
         }
         if (!$this->product_in_catalog_group($product, $group_id)) {
            continue;
         }
         $total_count++;
         $score = $this->product_search_score($product, $query);
         if ($score <= 0 || !$this->product_matches_attribute_filters($product, $attribute_filters)) {
            continue;
         }

         $sku = (string)($product['sku'] ?? '');
         if ($sku === '') {
            continue;
         }
         $matches[] = array(
            'product' => $product,
            'score' => $score,
            'sorter' => (int)($product['sorter'] ?? 100),
            'title' => (string)($product['title'] ?? ''),
         );
      }

      if ($has_query && count($matches) > 1) {
         usort($matches, static function(array $a, array $b): int {
            if ($a['score'] !== $b['score']) {
               return $b['score'] <=> $a['score'];
            }
            if ($a['sorter'] !== $b['sorter']) {
               return $a['sorter'] <=> $b['sorter'];
            }
            return strcasecmp($a['title'], $b['title']);
         });
      }

      $products = array_map(static fn($match) => $match['product'], $matches);
      $report_html = $products === array()
         ? $this->placeholder(
            $texts->get_fd_message('no_products_title'),
            $texts->get_fd_message('no_products_message')
         )
         : $this->catalog_report_html($products, $channel, $query, $attribute_filters, $group_id, $total_count);

      $is_pagination_ajax = (int)dbx()->get_system_var('dbx_ajax', 0, 'int') === 1
         && (array_key_exists('dbx_rpos', $_GET) || array_key_exists('dbx_rrows', $_GET));
      if ($is_pagination_ajax) {
         return $report_html;
      }

      $navigation = $this->catalog_group_breadcrumb($group_id) . $this->catalog_group_navigation($group_id);
      if ($navigation === '') {
         $navigation = $this->catalog_group_navigation(0);
      }
      $title = is_array($current_group) ? (string)($current_group['title'] ?? 'Shop') : 'Shop';
      $subtitle = $texts->get_fd_message(
         is_array($current_group)
            ? 'catalog_group_subtitle'
            : 'catalog_subtitle'
      );

      return $this->page(
         $title,
         $subtitle,
         $this->demo_shop_notice_html(
            'dbx-shop-demo-catalog-notice',
            'dbx-shop-demo-alert-catalog',
            $texts
         )
            . $navigation
            . $this->catalog_filters_html($channel, $query, $attribute_filters, $group_id)
            . $report_html,
         'catalog'
      );
   }

   public function product(): string {
      $this->ensure_seed();
      $texts = $this->texts('dbxShop|shop-catalog-filter-form');
      $channel = $this->active_channel();
      $sku = dbx()->get_modul_var('sku', '', 'parameter');
      $product = $this->repo()->product_by_sku((string) $sku);

      if (!$product || !$this->product_has_channel($product, $channel)) {
         return $this->page(
            $texts->get_fd_message('product_page_title'),
            $texts->get_fd_message('product_not_found_subtitle'),
            $this->placeholder(
               $texts->get_fd_message('product_not_found_title'),
               $texts->get_fd_message('product_not_found_message')
            ),
            'catalog'
         );
      }

      $body = $this->render_product_detail($product, $channel);

      return $this->page(
         $product['title'] ?? $texts->get_fd_message('product_page_title'),
         $product['summary'] ?? $texts->get_fd_message('product_fallback'),
         $body,
         'catalog'
      );
   }

   public function cart(): string {
      $this->ensure_seed();
      $texts = $this->texts('dbxShop|shop-cart');
      $channel = $this->active_channel();
      $ajax = (int)dbx()->get_system_var('dbx_ajax', 0, 'int') === 1;
      $add_sku = (string)dbx()->get_modul_var('sku', '', 'parameter');
      $cart_report = null;
      $has_cart_post = isset($_POST['shop_cart_update']) || isset($_POST['remove']) || isset($_POST['clear']);

      if ($has_cart_post) {
         [$current_rows, $current_sum] = $this->cart_report_data_and_sum($texts);
         if ($current_rows !== array()) {
            $cart_report = $this->cart_report($current_rows, $current_sum);
            if ($cart_report->submit() && !$cart_report->errors()) {
               $remove_sku = (string)$cart_report->get_post('remove', '', 'parameter');
               $clear = (int)$cart_report->get_post('clear', 0, 'int') === 1;
               if ($clear) {
                  dbxShopSessionState::clear_cart();
               } elseif ($remove_sku !== '') {
                  $this->remove_from_cart($remove_sku);
               } elseif (isset($_POST['shop_cart_update']) && is_array($_POST['qty'] ?? null)) {
                  // qty ist ein dynamisches Report-Feld. Der rohe Arraywert
                  // wird erst nach erfolgreicher Report-Tokenprüfung gelesen;
                  // updateCartQuantities begrenzt jeden Wert auf 1..999.
                  $this->update_cart_quantities($_POST['qty']);
               }
            }
         }
      } elseif ($add_sku !== '') {
         $product = $this->repo()->product_by_sku($add_sku);
         $buy_form = is_array($product) ? $this->buy_form($product) : null;
         if ($buy_form && $buy_form->submit() && !$buy_form->errors()) {
            $qty = $this->requested_quantity($buy_form->get_post('qty', 1, 'int|min=1'));
            $this->add_to_cart($add_sku, $qty);
            return $this->page(
               $texts->get_fd_message('cart_title'),
               $texts->get_fd_message('added_subtitle'),
               $this->added_to_cart_dialog($product),
               'cart'
            );
         }
      }

      $body = $this->cart_body_html($cart_report, $texts);
      if ($ajax) {
         return $body;
      }

      return $this->page(
         $texts->get_fd_message('cart_title'),
         $texts->get_fd_message('cart_subtitle'),
         $body,
         'cart'
      );
   }

   public function checkout(): string {
      $this->ensure_seed();
      $form = dbx()->get_system_obj('dbxForm');
      $form->init('shop-checkout-form', 'shop-checkout-form');
      $form->set_field_definition('dbxShop|checkout');
      $form->load_fd_messages();
      [$rows, $sum] = $this->cart_rows_and_sum();
      if ($rows === '') {
         return $this->page(
            $form->get_fd_message('page_title'),
            $form->get_fd_message('empty_subtitle'),
            $this->placeholder(
               $form->get_fd_message('empty_title'),
               $form->get_fd_message('empty_message')
            ),
            'checkout'
         );
      }
      $cfg = $this->shop_config();
      if (!$this->settings_bool($cfg, 'checkout_guest_allowed', true) && (int)dbx()->user() <= 0) {
         return $this->page(
            $form->get_fd_message('page_title'),
            $form->get_fd_message('login_subtitle'),
            '<div class="alert alert-warning m-3">'
               . $form->get_fd_message('login_message')
               . '</div>',
            'checkout'
         );
      }

      $payment_options = $this->checkout_payment_options();
      $checkout_request_id = $this->checkout_request_id();
      $form->set_action('?dbx_modul=dbxShop&dbx_run1=checkout');
      $form->merge_data(array(
         'customer_name' => (string)($_POST['customer_name'] ?? ''),
         'customer_email' => (string)($_POST['customer_email'] ?? ''),
         'customer_phone' => (string)($_POST['customer_phone'] ?? ''),
         'shipping_address' => (string)($_POST['shipping_address'] ?? ''),
         'note' => (string)($_POST['note'] ?? ''),
         'checkout_request_id' => $checkout_request_id,
         'payment_method' => (string)($_POST['payment_method'] ?? array_key_first($payment_options)),
         'accept_legal' => !empty($_POST['accept_legal']) ? 1 : 0,
         'accept_withdrawal' => !empty($_POST['accept_withdrawal']) ? 1 : 0,
      ));
      $form->_msg_info = $form->get_fd_message('form_info');
      $form->_msg_error = $form->get_fd_message('validation_error');
      $form->add_flds();
      $form->add_obj('checkout_cart', 'obj-value', $this->checkout_table_html($rows, $sum));
      $form->add_rep('payment_help', $this->checkout_payment_help($payment_options));
      $form->add_rep(
         'demo_shop_notice',
         $this->demo_shop_notice_html('dbx-shop-demo-notice', '', $form)
      );

      if ($form->submit()) {
         if ($form->errors()) {
            $form->_msg_error = $form->get_fd_message(
               'validation_error'
            );
         } else {
            $payment_method = (string)$form->get_post('payment_method', '', 'parameter');
            $customer_name = trim((string)$form->get_post_data('customer_name', '', '*|min=2|max=180'));
            $customer_email = trim((string)$form->get_post('customer_email', '', 'email|max=180'));
            $shipping_address = trim((string)$form->get_post_data('shipping_address', '', '*|min=8|max=2000'));
            $customer_phone = trim((string)$form->get_post_data('customer_phone', '', '*|max=80'));
            $note = trim((string)$form->get_post_data('note', '', '*|max=2000'));
            $accept_legal = (int)$form->get_post('accept_legal', 0, 'int') === 1;
            $accept_withdrawal = (int)$form->get_post('accept_withdrawal', 0, 'int') === 1;

         $checkout_error = '';
         if ($payment_options === array()) {
            $checkout_error = $form->get_fd_message('no_payment');
            $form->add_fld_error('payment_method', $checkout_error);
         } elseif (!isset($payment_options[$payment_method])) {
            $checkout_error = $form->get_fd_message('select_payment');
            $form->add_fld_error('payment_method', $checkout_error);
         } elseif (!$accept_legal || !$accept_withdrawal) {
            $checkout_error = $form->get_fd_message('confirm_legal');
            if (!$accept_legal) {
               $form->add_fld_error(
                  'accept_legal',
                  $form->get_fd_message('legal_field_error')
               );
            }
            if (!$accept_withdrawal) {
               $form->add_fld_error(
                  'accept_withdrawal',
                  $form->get_fd_message('withdrawal_field_error')
               );
            }
         }

         if ($checkout_error !== '') {
            $form->_msg_error = $checkout_error;
         } else {
            try {
               $request_id = $checkout_request_id;
               $existing_order = $this->checkout_request_order($request_id);
               if (is_array($existing_order)) {
                  return $this->continue_checkout_order($existing_order, $payment_method);
               }

               [$legal_snapshot, $withdrawal_snapshot] = $this->legal_snapshots_for_order();
               $order = $this->repo()->create_order_from_items(
                  $this->cart_items(),
                  $this->active_channel(),
                  $customer_name,
                  $customer_email,
                  $note,
                  $payment_method,
                  in_array($payment_method, array('paypal', 'amazon_pay'), true) ? 'created' : 'open',
                  'payment_pending',
                  $customer_phone,
                  $shipping_address,
                  $legal_snapshot,
                  $withdrawal_snapshot
               );
               if (!$order) {
                  $form->_msg_error = $form->get_fd_message(
                     'order_error'
                  );
               } else {
                  $this->remember_checkout_request($request_id, $order);
                  return $this->continue_checkout_order($order, $payment_method);
               }
            } catch (\Throwable $e) {
               dbx()->sys_msg('error', 'dbxShop', 'checkout', 'checkout failed', $e->getMessage());
               $form->_msg_error = $form->get_fd_message(
                  'technical_error'
               );
            }
         }
         }
      }

      return $this->page(
         $form->get_fd_message('page_title'),
         $form->get_fd_message('page_subtitle'),
         $form->run(),
         'checkout'
      );
   }

   public function paypal_start(): string {
      $this->ensure_seed();
      return $this->checkout();
   }

   /**
    * Ordnet Provider-Ruecklaeufe ausschliesslich ueber die zuvor serverseitig
    * gespeicherte Zahlungsreferenz zu. order_no ist nur ein zusaetzlicher
    * Konsistenzcheck und niemals der Zahlungsnachweis.
    */
   private function provider_return_order(string $provider, string $reference, string $order_no): ?array {
      if ($reference === '') return null;
      $order = $this->repo()->order_by_payment_reference($provider, $reference);
      if (!is_array($order)) return null;
      if ($order_no !== '' && !hash_equals((string)($order['order_no'] ?? ''), $order_no)) {
         return null;
      }
      return $order;
   }

   private function remember_provider_order(array $order, bool $clear_cart = true): void {
      if ($clear_cart) dbxShopSessionState::clear_cart();
      dbxShopSessionState::set_last_order_no((string)($order['order_no'] ?? ''));
   }

   public function paypal_return(): string {
      $this->ensure_seed();
      $paypal_order_id = (string)dbx()->get_modul_var('token', '', 'parameter');
      $order_no = (string)dbx()->get_modul_var('order_no', '', 'parameter');
      $order = $this->provider_return_order('paypal', $paypal_order_id, $order_no);
      if (!$order || $paypal_order_id === '') {
         return $this->page('PayPal', 'Rueckkehr konnte nicht zugeordnet werden.', '<div class="alert alert-danger m-3">Die PayPal-Rueckkehr konnte keiner Bestellung zugeordnet werden.</div>', 'checkout');
      }

      if (in_array((string)($order['payment_status'] ?? ''), array('completed', 'paid'), true)) {
         $this->remember_provider_order($order);
         $body = '<div class="alert alert-success m-3"><strong>Zahlung bereits abgeschlossen.</strong><br>Bestellung ' . $this->h($order['order_no'] ?? '') . ' ist bezahlt.</div>';
         return $this->page('Danke', 'PayPal-Zahlung wurde bestaetigt.', $body, 'orders');
      }
      if (!$this->repo()->claim_order_payment((int)$order['id'], 'paypal', $paypal_order_id)) {
         $fresh = $this->repo()->order_by_payment_reference('paypal', $paypal_order_id) ?: $order;
         $body = '<div class="alert alert-info m-3"><strong>Zahlung wird bereits verarbeitet.</strong><br>Bestellung ' . $this->h($fresh['order_no'] ?? '') . ' wird nicht doppelt belastet.</div>';
         return $this->page('PayPal', 'Zahlungsstatus wird verarbeitet.', $body, 'orders');
      }

      try {
         $capture = $this->paypal()->capture($paypal_order_id);
         $this->paypal()->validate_capture($capture, $order, $paypal_order_id);
         if (!$this->repo()->update_order_payment((int)$order['id'], 'paypal', 'completed', $paypal_order_id, $capture)) {
            throw new \RuntimeException('PayPal-Zahlungsstatus konnte nicht atomar gespeichert werden.');
         }
         $fresh_order = $this->repo()->order_by_id((int)$order['id']) ?: $order;
         $this->send_order_mails($fresh_order);
         $this->remember_provider_order($fresh_order);
         $body = '<div class="alert alert-success m-3"><strong>Zahlung abgeschlossen.</strong><br>Bestellung ' . $this->h($order['order_no'] ?? '') . ' wurde bezahlt.</div>';
         return $this->page('Danke', 'PayPal-Zahlung wurde bestaetigt.', $body, 'orders');
      } catch (\Throwable $e) {
         $this->repo()->update_order_payment((int)$order['id'], 'paypal', 'failed', $paypal_order_id, array('error' => $e->getMessage()));
         return $this->page('PayPal', 'Zahlung konnte nicht bestaetigt werden.', '<div class="alert alert-danger m-3">' . $this->h($e->getMessage()) . '</div>', 'checkout');
      }
   }

   public function paypal_cancel(): string {
      return $this->page(
         'PayPal abgebrochen',
         'Die Zahlung wurde nicht ausgefuehrt.',
         '<div class="alert alert-info m-3">Die PayPal-Seite wurde verlassen. Der Warenkorb bleibt erhalten; der serverseitige Zahlungsstatus wird nicht allein aufgrund dieses Browseraufrufs veraendert.</div>',
         'checkout'
      );
   }

   public function amazon_pay_return(): string {
      $this->ensure_seed();
      $checkout_session_id = (string)dbx()->get_modul_var('checkoutSessionId', '', 'parameter');
      if ($checkout_session_id === '') {
         $checkout_session_id = (string)dbx()->get_modul_var('amazonCheckoutSessionId', '', 'parameter');
      }
      $order_no = (string)dbx()->get_modul_var('order_no', '', 'parameter');
      $order = $this->provider_return_order('amazon_pay', $checkout_session_id, $order_no);
      if (!$order || $checkout_session_id === '') {
         return $this->page('Amazon Pay', 'Rueckkehr konnte nicht zugeordnet werden.', '<div class="alert alert-danger m-3">Die Amazon-Pay-Rueckkehr konnte keiner Bestellung zugeordnet werden.</div>', 'checkout');
      }

      if (in_array((string)($order['payment_status'] ?? ''), array('completed', 'paid', 'pending'), true)) {
         $this->remember_provider_order($order);
         $body = '<div class="alert alert-success m-3"><strong>Amazon-Pay-Zahlung bereits verarbeitet.</strong><br>Bestellung ' . $this->h($order['order_no'] ?? '') . ' besitzt bereits einen gueltigen Providerstatus.</div>';
         return $this->page('Danke', 'Amazon-Pay-Zahlung wurde verarbeitet.', $body, 'orders');
      }
      if (!$this->repo()->claim_order_payment((int)$order['id'], 'amazon_pay', $checkout_session_id)) {
         $fresh = $this->repo()->order_by_payment_reference('amazon_pay', $checkout_session_id) ?: $order;
         $body = '<div class="alert alert-info m-3"><strong>Zahlung wird bereits verarbeitet.</strong><br>Bestellung ' . $this->h($fresh['order_no'] ?? '') . ' wird nicht doppelt abgeschlossen.</div>';
         return $this->page('Amazon Pay', 'Zahlungsstatus wird verarbeitet.', $body, 'orders');
      }

      try {
         $result = $this->amazon_pay()->complete_checkout_session($checkout_session_id, $order);
         $payment_status = $this->amazon_pay()->validate_completion($result, $order, $checkout_session_id);
         if (!$this->repo()->update_order_payment((int)$order['id'], 'amazon_pay', $payment_status, $checkout_session_id, $result)) {
            throw new \RuntimeException('Amazon-Pay-Status konnte nicht atomar gespeichert werden.');
         }
         $fresh_order = $this->repo()->order_by_id((int)$order['id']) ?: $order;
         if ($payment_status === 'completed') {
            $this->send_order_mails($fresh_order);
         }
         $this->remember_provider_order($fresh_order);
         $body = '<div class="alert alert-success m-3"><strong>Amazon-Pay-Zahlung verarbeitet.</strong><br>Bestellung ' . $this->h($order['order_no'] ?? '') . ' wurde aktualisiert.</div>';
         return $this->page('Danke', 'Amazon-Pay-Zahlung wurde bestaetigt.', $body, 'orders');
      } catch (\Throwable $e) {
         $this->repo()->update_order_payment((int)$order['id'], 'amazon_pay', 'failed', $checkout_session_id, array('error' => $e->getMessage()));
         return $this->page('Amazon Pay', 'Zahlung konnte nicht bestaetigt werden.', '<div class="alert alert-danger m-3">' . $this->h($e->getMessage()) . '</div>', 'checkout');
      }
   }

   public function amazon_pay_cancel(): string {
      return $this->page(
         'Amazon Pay abgebrochen',
         'Die Zahlung wurde nicht ausgefuehrt.',
         '<div class="alert alert-info m-3">Die Amazon-Pay-Seite wurde verlassen. Der Warenkorb bleibt erhalten; der serverseitige Zahlungsstatus wird nicht allein aufgrund dieses Browseraufrufs veraendert.</div>',
         'checkout'
      );
   }

   public function orders(): string {
      $this->ensure_seed();
      $texts = $this->texts('dbxShop|shop-orders');
      $cards = '';
      $seen = array();
      $last_order_no = dbxShopSessionState::last_order_no();
      if ($last_order_no !== '') {
         $order = $this->repo()->order_by_no($last_order_no);
         if (is_array($order)) {
            $cards .= $this->public_order_card($order);
            $seen[(string)($order['order_no'] ?? '')] = true;
         }
      }

      $uid = function_exists('dbx') ? (int)dbx()->user() : 0;
      foreach ($this->repo()->orders_by_uid($uid, 25) as $order) {
         $order_no = (string)($order['order_no'] ?? '');
         if ($order_no !== '' && isset($seen[$order_no])) {
            continue;
         }
         $cards .= $this->public_order_card($order);
      }

      if ($cards === '') {
         $cards = $this->placeholder(
            $texts->get_fd_message('empty_title'),
            $texts->get_fd_message('empty_message')
         );
      } else {
         $cards = '<section class="dbx-shop-public-orders">' . $cards . '</section>';
      }

      return $this->page(
         $texts->get_fd_message('page_title'),
         $texts->get_fd_message('page_subtitle'),
         $cards,
         'orders'
      );
   }

   public function invoice_pdf(): string {
      $this->ensure_seed();
      $this->start_session();
      $order_no = (string)dbx()->get_modul_var('order_no', '', 'parameter');
      $order = $order_no !== '' ? $this->repo()->order_by_no($order_no) : null;
      if (!is_array($order) || !$this->order_is_public_accessible($order)) {
         return $this->page('Rechnung', 'Zugriff nicht moeglich.', '<div class="alert alert-warning m-3">Die Rechnung wurde nicht gefunden oder ist fuer diesen Benutzer nicht freigegeben.</div>', 'orders');
      }
      $order = $this->repo()->ensure_order_invoice_pdf((int)$order['id']);
      if (!is_array($order)) {
         return $this->page('Rechnung', 'PDF konnte nicht erzeugt werden.', '<div class="alert alert-danger m-3">Die Rechnungsdatei konnte nicht erzeugt werden.</div>', 'orders');
      }
      $file = $this->repo()->invoice_pdf_absolute_path($order);
      if ($file === '') {
         return $this->page('Rechnung', 'PDF konnte nicht geladen werden.', '<div class="alert alert-danger m-3">Die Rechnungsdatei ist nicht verfuegbar.</div>', 'orders');
      }
      if (!headers_sent()) {
         header('Content-Type: application/pdf');
         header('Content-Disposition: inline; filename="' . basename($file) . '"');
         header('Content-Length: ' . filesize($file));
      }
      readfile($file);
      exit;
   }

   public function channel_webhook(): string {
      $channel_key = (string)dbx()->get_modul_var('channel', '', 'parameter');
      $payload = dbx()->get_json_request(true);

      try {
         $channel = $this->repo()->channel_by_key($channel_key);
         if (!$channel) {
            return $this->json_response(array('ok' => false, 'message' => 'Channel nicht gefunden.'), 404);
         }
         if ((int)($channel['active'] ?? 0) !== 1 || (int)($channel['order_import_enabled'] ?? 0) !== 1) {
            return $this->json_response(array('ok' => false, 'message' => 'Order-Import fuer diesen Channel ist nicht aktiv.'), 403);
         }

         $secret = trim((string)($channel['webhook_secret'] ?? ''));
         if ($secret === '') {
            // Der Endpunkt ist wegen externer Provider bewusst oeffentlich.
            // Modul-/DD-Rechte authentifizieren deshalb keinen Absender.
            return $this->json_response(array(
               'ok' => false,
               'message' => 'Webhook-Authentifizierung ist nicht konfiguriert.',
            ), 503);
         }
         // Keine Secrets in GET-URLs: Query-Strings landen regelmaessig in
         // Access-Logs, Browser-Historien und Referrer-Headern.
         $given = trim((string)(
            $_SERVER['HTTP_X_DBX_SHOP_SECRET']
            ?? $_SERVER['HTTP_X_CHANNEL_SECRET']
            ?? $_POST['secret']
            ?? $payload['secret']
            ?? ''
         ));
         if ($given === '' || !hash_equals($secret, $given)) {
            return $this->json_response(array('ok' => false, 'message' => 'Webhook-Secret ungueltig.'), 403);
         }

         $connector = dbx()->get_include_obj('dbxShopChannelConnector', 'dbxShop');
         if (is_object($connector) && method_exists($connector, 'normalizeWebhookPayload')) {
            $payload = (array)$connector->normalize_webhook_payload($channel, $payload);
         }

         $order = $this->repo()->import_channel_order($channel_key, $payload);
         return $this->json_response(array(
            'ok' => true,
            'order_no' => (string)($order['order_no'] ?? ''),
            'channel' => $channel_key,
         ));
      } catch (\Throwable $e) {
         return $this->json_response(array('ok' => false, 'message' => $e->getMessage()), 400);
      }
   }

   public function legal(): string {
      return $this->render_cms_shop_page(
         'legal',
         'Rechtstexte',
         'AGB, Anbieterkennzeichnung, Zahlung, Versand und Datenschutz-Hinweise.',
         'legal'
      );
   }

   public function withdrawal(): string {
      $form = dbx()->get_system_obj('dbxForm');
      $form->init('shop-withdrawal-form', 'shop-withdrawal-form');
      $form->set_field_definition('dbxShop|withdrawal');
      $form->load_fd_messages();
      $pages = $this->ensure_shop_legal_pages();
      $cid = (int)($pages['withdrawal'] ?? 0);
      $body = '';
      if ($cid > 0) {
         $renderer = dbx()->get_include_obj('dbxContentRenderer', 'dbxContent');
         $body = is_object($renderer) ? (string)$renderer->render_static($cid, array('template' => 'c-body1-footer')) : '';
      }
      if (trim($body) === '') {
         $body = $this->placeholder(
            $form->get_fd_message('page_title'),
            $form->get_fd_message('empty_content')
         );
      }
      return $this->page(
         $form->get_fd_message('page_title'),
         $form->get_fd_message('page_subtitle'),
         '<div class="dbx-shop-cms-page">' . $body . '</div>' . $this->withdrawal_form_html($form),
         'withdrawal'
      );
   }
}
