<?php
namespace dbx\dbxShop;

trait dbxShopServiceRouteServiceTrait {

   public function catalog(): string {
      $this->ensureSeed();
      $texts = $this->texts('dbxShop|shop-catalog-filter-form');
      $channel = $this->activeChannel();
      $query = trim((string)($_GET['q'] ?? ''));
      $groupId = $this->catalogGroupId();
      $attributeFilters = $this->selectedAttributeFilters();
      $matches = array();
      $hasQuery = $this->searchTerms($query) !== array();
      $currentGroup = $groupId > 0 ? $this->repo()->groupById($groupId) : null;
      if ($groupId > 0 && !is_array($currentGroup)) {
         $groupId = 0;
      }

      foreach ($this->repo()->catalogCandidates($channel) as $product) {
         if (!$this->productHasChannel($product, $channel)) {
            continue;
         }
         if (!$this->productInCatalogGroup($product, $groupId)) {
            continue;
         }
         $score = $this->productSearchScore($product, $query);
         if ($score <= 0 || !$this->productMatchesAttributeFilters($product, $attributeFilters)) {
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

      if ($hasQuery && count($matches) > 1) {
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
      $reportHtml = $products === array()
         ? $this->placeholder(
            $texts->get_fd_message('no_products_title'),
            $texts->get_fd_message('no_products_message')
         )
         : $this->catalogReportHtml($products, $channel, $query, $attributeFilters, $groupId);

      $isPaginationAjax = (int)dbx()->get_system_var('dbx_ajax', 0, 'int') === 1
         && (array_key_exists('dbx_rpos', $_GET) || array_key_exists('dbx_rrows', $_GET));
      if ($isPaginationAjax) {
         return $reportHtml;
      }

      $navigation = $this->catalogGroupBreadcrumb($groupId) . $this->catalogGroupNavigation($groupId);
      if ($navigation === '') {
         $navigation = $this->catalogGroupNavigation(0);
      }
      $title = is_array($currentGroup) ? (string)($currentGroup['title'] ?? 'Shop') : 'Shop';
      $subtitle = $texts->get_fd_message(
         is_array($currentGroup)
            ? 'catalog_group_subtitle'
            : 'catalog_subtitle'
      );

      return $this->page(
         $title,
         $subtitle,
         $this->demoShopNoticeHtml(
            'dbx-shop-demo-catalog-notice',
            'dbx-shop-demo-alert-catalog',
            $texts
         )
            . $navigation
            . $this->catalogFiltersHtml($channel, $query, $attributeFilters, $groupId)
            . $reportHtml,
         'catalog'
      );
   }

   public function product(): string {
      $this->ensureSeed();
      $texts = $this->texts('dbxShop|shop-catalog-filter-form');
      $channel = $this->activeChannel();
      $sku = dbx()->get_modul_var('sku', '', 'parameter');
      $product = $this->repo()->productBySku((string) $sku);

      if (!$product || !$this->productHasChannel($product, $channel)) {
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

      $body = $this->renderProductDetail($product, $channel);

      return $this->page(
         $product['title'] ?? $texts->get_fd_message('product_page_title'),
         $product['summary'] ?? $texts->get_fd_message('product_fallback'),
         $body,
         'catalog'
      );
   }

   public function cart(): string {
      $this->ensureSeed();
      $texts = $this->texts('dbxShop|shop-cart');
      $channel = $this->activeChannel();
      $ajax = (int)dbx()->get_system_var('dbx_ajax', 0, 'int') === 1;
      $addSku = (string)dbx()->get_modul_var('sku', '', 'parameter');
      $cartReport = null;
      $hasCartPost = isset($_POST['shop_cart_update']) || isset($_POST['remove']) || isset($_POST['clear']);

      if ($hasCartPost) {
         [$currentRows, $currentSum] = $this->cartReportDataAndSum($texts);
         if ($currentRows !== array()) {
            $cartReport = $this->cartReport($currentRows, $currentSum);
            if ($cartReport->submit() && !$cartReport->errors()) {
               $removeSku = (string)$cartReport->get_post('remove', '', 'parameter');
               $clear = (int)$cartReport->get_post('clear', 0, 'int') === 1;
               if ($clear) {
                  $this->startSession();
                  $_SESSION['dbxShop_cart'] = array();
               } elseif ($removeSku !== '') {
                  $this->removeFromCart($removeSku);
               } elseif (isset($_POST['shop_cart_update']) && is_array($_POST['qty'] ?? null)) {
                  // qty ist ein dynamisches Report-Feld. Der rohe Arraywert
                  // wird erst nach erfolgreicher Report-Tokenprüfung gelesen;
                  // updateCartQuantities begrenzt jeden Wert auf 1..999.
                  $this->updateCartQuantities($_POST['qty']);
               }
            }
         }
      } elseif ($addSku !== '') {
         $product = $this->repo()->productBySku($addSku);
         $buyForm = is_array($product) ? $this->buyForm($product) : null;
         if ($buyForm && $buyForm->submit() && !$buyForm->errors()) {
            $qty = $this->requestedQuantity($buyForm->get_post('qty', 1, 'int|min=1'));
            $this->addToCart($addSku, $qty);
            return $this->page(
               $texts->get_fd_message('cart_title'),
               $texts->get_fd_message('added_subtitle'),
               $this->addedToCartDialog($product),
               'cart'
            );
         }
      }

      $body = $this->cartBodyHtml($cartReport, $texts);
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
      $this->ensureSeed();
      $form = dbx()->get_system_obj('dbxForm');
      $form->init('shop-checkout-form', 'shop-checkout-form');
      $form->_fd = 'dbxShop|checkout';
      $form->load_fd_messages();
      [$rows, $sum] = $this->cartRowsAndSum();
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
      $cfg = $this->shopConfig();
      if (!$this->settingsBool($cfg, 'checkout_guest_allowed', true) && (int)dbx()->user() <= 0) {
         return $this->page(
            $form->get_fd_message('page_title'),
            $form->get_fd_message('login_subtitle'),
            '<div class="alert alert-warning m-3">'
               . $form->get_fd_message('login_message')
               . '</div>',
            'checkout'
         );
      }

      $paymentOptions = $this->checkoutPaymentOptions();
      $checkoutRequestId = $this->checkoutRequestId();
      $form->_action = '?dbx_modul=dbxShop&dbx_run1=checkout';
      $form->_data = array_merge($form->_data, array(
         'customer_name' => (string)($_POST['customer_name'] ?? ''),
         'customer_email' => (string)($_POST['customer_email'] ?? ''),
         'customer_phone' => (string)($_POST['customer_phone'] ?? ''),
         'shipping_address' => (string)($_POST['shipping_address'] ?? ''),
         'note' => (string)($_POST['note'] ?? ''),
         'checkout_request_id' => $checkoutRequestId,
         'payment_method' => (string)($_POST['payment_method'] ?? array_key_first($paymentOptions)),
         'accept_legal' => !empty($_POST['accept_legal']) ? 1 : 0,
         'accept_withdrawal' => !empty($_POST['accept_withdrawal']) ? 1 : 0,
      ));
      $form->_msg_info = $form->get_fd_message('form_info');
      $form->_msg_error = $form->get_fd_message('validation_error');
      $form->add_flds();
      $form->add_obj('checkout_cart', 'obj-value', $this->checkoutTableHtml($rows, $sum));
      $form->add_rep('payment_help', $this->checkoutPaymentHelp($paymentOptions));
      $form->add_rep(
         'demo_shop_notice',
         $this->demoShopNoticeHtml('dbx-shop-demo-notice', '', $form)
      );

      if ($form->submit()) {
         if ($form->errors()) {
            $form->_msg_error = $form->get_fd_message(
               'validation_error'
            );
         } else {
            $paymentMethod = (string)$form->get_post('payment_method', '', 'parameter');
            $customerName = trim((string)$form->get_post_data('customer_name', '', '*|min=2|max=180'));
            $customerEmail = trim((string)$form->get_post('customer_email', '', 'email|max=180'));
            $shippingAddress = trim((string)$form->get_post_data('shipping_address', '', '*|min=8|max=2000'));
            $customerPhone = trim((string)$form->get_post_data('customer_phone', '', '*|max=80'));
            $note = trim((string)$form->get_post_data('note', '', '*|max=2000'));
            $acceptLegal = (int)$form->get_post('accept_legal', 0, 'int') === 1;
            $acceptWithdrawal = (int)$form->get_post('accept_withdrawal', 0, 'int') === 1;

         $checkoutError = '';
         if ($paymentOptions === array()) {
            $checkoutError = $form->get_fd_message('no_payment');
            $form->add_fld_error('payment_method', $checkoutError);
         } elseif (!isset($paymentOptions[$paymentMethod])) {
            $checkoutError = $form->get_fd_message('select_payment');
            $form->add_fld_error('payment_method', $checkoutError);
         } elseif (!$acceptLegal || !$acceptWithdrawal) {
            $checkoutError = $form->get_fd_message('confirm_legal');
            if (!$acceptLegal) {
               $form->add_fld_error(
                  'accept_legal',
                  $form->get_fd_message('legal_field_error')
               );
            }
            if (!$acceptWithdrawal) {
               $form->add_fld_error(
                  'accept_withdrawal',
                  $form->get_fd_message('withdrawal_field_error')
               );
            }
         }

         if ($checkoutError !== '') {
            $form->_msg_error = $checkoutError;
         } else {
            try {
               $requestId = $checkoutRequestId;
               $existingOrder = $this->checkoutRequestOrder($requestId);
               if (is_array($existingOrder)) {
                  return $this->continueCheckoutOrder($existingOrder, $paymentMethod);
               }

               [$legalSnapshot, $withdrawalSnapshot] = $this->legalSnapshotsForOrder();
               $order = $this->repo()->createOrderFromItems(
                  $this->cartItems(),
                  $this->activeChannel(),
                  $customerName,
                  $customerEmail,
                  $note,
                  $paymentMethod,
                  in_array($paymentMethod, array('paypal', 'amazon_pay'), true) ? 'created' : 'open',
                  'payment_pending',
                  $customerPhone,
                  $shippingAddress,
                  $legalSnapshot,
                  $withdrawalSnapshot
               );
               if (!$order) {
                  $form->_msg_error = $form->get_fd_message(
                     'order_error'
                  );
               } else {
                  $this->rememberCheckoutRequest($requestId, $order);
                  return $this->continueCheckoutOrder($order, $paymentMethod);
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

   public function paypalStart(): string {
      $this->ensureSeed();
      return $this->checkout();
   }

   /**
    * Ordnet Provider-Ruecklaeufe ausschliesslich ueber die zuvor serverseitig
    * gespeicherte Zahlungsreferenz zu. order_no ist nur ein zusaetzlicher
    * Konsistenzcheck und niemals der Zahlungsnachweis.
    */
   private function providerReturnOrder(string $provider, string $reference, string $orderNo): ?array {
      if ($reference === '') return null;
      $order = $this->repo()->orderByPaymentReference($provider, $reference);
      if (!is_array($order)) return null;
      if ($orderNo !== '' && !hash_equals((string)($order['order_no'] ?? ''), $orderNo)) {
         return null;
      }
      return $order;
   }

   private function rememberProviderOrder(array $order, bool $clearCart = true): void {
      $this->startSession();
      if ($clearCart) $_SESSION['dbxShop_cart'] = array();
      $_SESSION['dbxShop_last_order_no'] = (string)($order['order_no'] ?? '');
   }

   public function paypalReturn(): string {
      $this->ensureSeed();
      $paypalOrderId = (string)dbx()->get_modul_var('token', '', 'parameter');
      $orderNo = (string)dbx()->get_modul_var('order_no', '', 'parameter');
      $order = $this->providerReturnOrder('paypal', $paypalOrderId, $orderNo);
      if (!$order || $paypalOrderId === '') {
         return $this->page('PayPal', 'Rueckkehr konnte nicht zugeordnet werden.', '<div class="alert alert-danger m-3">Die PayPal-Rueckkehr konnte keiner Bestellung zugeordnet werden.</div>', 'checkout');
      }

      if (in_array((string)($order['payment_status'] ?? ''), array('completed', 'paid'), true)) {
         $this->rememberProviderOrder($order);
         $body = '<div class="alert alert-success m-3"><strong>Zahlung bereits abgeschlossen.</strong><br>Bestellung ' . $this->h($order['order_no'] ?? '') . ' ist bezahlt.</div>';
         return $this->page('Danke', 'PayPal-Zahlung wurde bestaetigt.', $body, 'orders');
      }
      if (!$this->repo()->claimOrderPayment((int)$order['id'], 'paypal', $paypalOrderId)) {
         $fresh = $this->repo()->orderByPaymentReference('paypal', $paypalOrderId) ?: $order;
         $body = '<div class="alert alert-info m-3"><strong>Zahlung wird bereits verarbeitet.</strong><br>Bestellung ' . $this->h($fresh['order_no'] ?? '') . ' wird nicht doppelt belastet.</div>';
         return $this->page('PayPal', 'Zahlungsstatus wird verarbeitet.', $body, 'orders');
      }

      try {
         $capture = $this->paypal()->capture($paypalOrderId);
         $this->paypal()->validateCapture($capture, $order, $paypalOrderId);
         if (!$this->repo()->updateOrderPayment((int)$order['id'], 'paypal', 'completed', $paypalOrderId, $capture)) {
            throw new \RuntimeException('PayPal-Zahlungsstatus konnte nicht atomar gespeichert werden.');
         }
         $freshOrder = $this->repo()->orderById((int)$order['id']) ?: $order;
         $this->sendOrderMails($freshOrder);
         $this->rememberProviderOrder($freshOrder);
         $body = '<div class="alert alert-success m-3"><strong>Zahlung abgeschlossen.</strong><br>Bestellung ' . $this->h($order['order_no'] ?? '') . ' wurde bezahlt.</div>';
         return $this->page('Danke', 'PayPal-Zahlung wurde bestaetigt.', $body, 'orders');
      } catch (\Throwable $e) {
         $this->repo()->updateOrderPayment((int)$order['id'], 'paypal', 'failed', $paypalOrderId, array('error' => $e->getMessage()));
         return $this->page('PayPal', 'Zahlung konnte nicht bestaetigt werden.', '<div class="alert alert-danger m-3">' . $this->h($e->getMessage()) . '</div>', 'checkout');
      }
   }

   public function paypalCancel(): string {
      return $this->page(
         'PayPal abgebrochen',
         'Die Zahlung wurde nicht ausgefuehrt.',
         '<div class="alert alert-info m-3">Die PayPal-Seite wurde verlassen. Der Warenkorb bleibt erhalten; der serverseitige Zahlungsstatus wird nicht allein aufgrund dieses Browseraufrufs veraendert.</div>',
         'checkout'
      );
   }

   public function amazonPayReturn(): string {
      $this->ensureSeed();
      $checkoutSessionId = (string)dbx()->get_modul_var('checkoutSessionId', '', 'parameter');
      if ($checkoutSessionId === '') {
         $checkoutSessionId = (string)dbx()->get_modul_var('amazonCheckoutSessionId', '', 'parameter');
      }
      $orderNo = (string)dbx()->get_modul_var('order_no', '', 'parameter');
      $order = $this->providerReturnOrder('amazon_pay', $checkoutSessionId, $orderNo);
      if (!$order || $checkoutSessionId === '') {
         return $this->page('Amazon Pay', 'Rueckkehr konnte nicht zugeordnet werden.', '<div class="alert alert-danger m-3">Die Amazon-Pay-Rueckkehr konnte keiner Bestellung zugeordnet werden.</div>', 'checkout');
      }

      if (in_array((string)($order['payment_status'] ?? ''), array('completed', 'paid', 'pending'), true)) {
         $this->rememberProviderOrder($order);
         $body = '<div class="alert alert-success m-3"><strong>Amazon-Pay-Zahlung bereits verarbeitet.</strong><br>Bestellung ' . $this->h($order['order_no'] ?? '') . ' besitzt bereits einen gueltigen Providerstatus.</div>';
         return $this->page('Danke', 'Amazon-Pay-Zahlung wurde verarbeitet.', $body, 'orders');
      }
      if (!$this->repo()->claimOrderPayment((int)$order['id'], 'amazon_pay', $checkoutSessionId)) {
         $fresh = $this->repo()->orderByPaymentReference('amazon_pay', $checkoutSessionId) ?: $order;
         $body = '<div class="alert alert-info m-3"><strong>Zahlung wird bereits verarbeitet.</strong><br>Bestellung ' . $this->h($fresh['order_no'] ?? '') . ' wird nicht doppelt abgeschlossen.</div>';
         return $this->page('Amazon Pay', 'Zahlungsstatus wird verarbeitet.', $body, 'orders');
      }

      try {
         $result = $this->amazonPay()->completeCheckoutSession($checkoutSessionId, $order);
         $paymentStatus = $this->amazonPay()->validateCompletion($result, $order, $checkoutSessionId);
         if (!$this->repo()->updateOrderPayment((int)$order['id'], 'amazon_pay', $paymentStatus, $checkoutSessionId, $result)) {
            throw new \RuntimeException('Amazon-Pay-Status konnte nicht atomar gespeichert werden.');
         }
         $freshOrder = $this->repo()->orderById((int)$order['id']) ?: $order;
         if ($paymentStatus === 'completed') {
            $this->sendOrderMails($freshOrder);
         }
         $this->rememberProviderOrder($freshOrder);
         $body = '<div class="alert alert-success m-3"><strong>Amazon-Pay-Zahlung verarbeitet.</strong><br>Bestellung ' . $this->h($order['order_no'] ?? '') . ' wurde aktualisiert.</div>';
         return $this->page('Danke', 'Amazon-Pay-Zahlung wurde bestaetigt.', $body, 'orders');
      } catch (\Throwable $e) {
         $this->repo()->updateOrderPayment((int)$order['id'], 'amazon_pay', 'failed', $checkoutSessionId, array('error' => $e->getMessage()));
         return $this->page('Amazon Pay', 'Zahlung konnte nicht bestaetigt werden.', '<div class="alert alert-danger m-3">' . $this->h($e->getMessage()) . '</div>', 'checkout');
      }
   }

   public function amazonPayCancel(): string {
      return $this->page(
         'Amazon Pay abgebrochen',
         'Die Zahlung wurde nicht ausgefuehrt.',
         '<div class="alert alert-info m-3">Die Amazon-Pay-Seite wurde verlassen. Der Warenkorb bleibt erhalten; der serverseitige Zahlungsstatus wird nicht allein aufgrund dieses Browseraufrufs veraendert.</div>',
         'checkout'
      );
   }

   public function orders(): string {
      $this->ensureSeed();
      $this->startSession();
      $texts = $this->texts('dbxShop|shop-orders');
      $cards = '';
      $seen = array();
      $lastOrderNo = (string)($_SESSION['dbxShop_last_order_no'] ?? '');
      if ($lastOrderNo !== '') {
         $order = $this->repo()->orderByNo($lastOrderNo);
         if (is_array($order)) {
            $cards .= $this->publicOrderCard($order);
            $seen[(string)($order['order_no'] ?? '')] = true;
         }
      }

      $uid = function_exists('dbx') ? (int)dbx()->user() : 0;
      foreach ($this->repo()->ordersByUid($uid, 25) as $order) {
         $orderNo = (string)($order['order_no'] ?? '');
         if ($orderNo !== '' && isset($seen[$orderNo])) {
            continue;
         }
         $cards .= $this->publicOrderCard($order);
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

   public function invoicePdf(): string {
      $this->ensureSeed();
      $this->startSession();
      $orderNo = (string)dbx()->get_modul_var('order_no', '', 'parameter');
      $order = $orderNo !== '' ? $this->repo()->orderByNo($orderNo) : null;
      if (!is_array($order) || !$this->orderIsPublicAccessible($order)) {
         return $this->page('Rechnung', 'Zugriff nicht moeglich.', '<div class="alert alert-warning m-3">Die Rechnung wurde nicht gefunden oder ist fuer diesen Benutzer nicht freigegeben.</div>', 'orders');
      }
      $order = $this->repo()->ensureOrderInvoicePdf((int)$order['id']);
      if (!is_array($order)) {
         return $this->page('Rechnung', 'PDF konnte nicht erzeugt werden.', '<div class="alert alert-danger m-3">Die Rechnungsdatei konnte nicht erzeugt werden.</div>', 'orders');
      }
      $file = $this->repo()->invoicePdfAbsolutePath($order);
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

   public function channelWebhook(): string {
      $channelKey = (string)dbx()->get_modul_var('channel', '', 'parameter');
      $raw = (string)file_get_contents('php://input');
      $payload = json_decode($raw, true);
      if (!is_array($payload)) {
         $payload = $_POST;
      }

      try {
         $channel = $this->repo()->channelByKey($channelKey);
         if (!$channel) {
            return $this->jsonResponse(array('ok' => false, 'message' => 'Channel nicht gefunden.'), 404);
         }
         if ((int)($channel['active'] ?? 0) !== 1 || (int)($channel['order_import_enabled'] ?? 0) !== 1) {
            return $this->jsonResponse(array('ok' => false, 'message' => 'Order-Import fuer diesen Channel ist nicht aktiv.'), 403);
         }

         $secret = trim((string)($channel['webhook_secret'] ?? ''));
         if ($secret === '') {
            // Der Endpunkt ist wegen externer Provider bewusst oeffentlich.
            // Modul-/DD-Rechte authentifizieren deshalb keinen Absender.
            return $this->jsonResponse(array(
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
            return $this->jsonResponse(array('ok' => false, 'message' => 'Webhook-Secret ungueltig.'), 403);
         }

         $connector = dbx()->get_include_obj('dbxShopChannelConnector', 'dbxShop');
         if (is_object($connector) && method_exists($connector, 'normalizeWebhookPayload')) {
            $payload = (array)$connector->normalizeWebhookPayload($channel, $payload);
         }

         $order = $this->repo()->importChannelOrder($channelKey, $payload);
         return $this->jsonResponse(array(
            'ok' => true,
            'order_no' => (string)($order['order_no'] ?? ''),
            'channel' => $channelKey,
         ));
      } catch (\Throwable $e) {
         return $this->jsonResponse(array('ok' => false, 'message' => $e->getMessage()), 400);
      }
   }

   public function legal(): string {
      return $this->renderCmsShopPage(
         'legal',
         'Rechtstexte',
         'AGB, Anbieterkennzeichnung, Zahlung, Versand und Datenschutz-Hinweise.',
         'legal'
      );
   }

   public function withdrawal(): string {
      $form = dbx()->get_system_obj('dbxForm');
      $form->init('shop-withdrawal-form', 'shop-withdrawal-form');
      $form->_fd = 'dbxShop|withdrawal';
      $form->load_fd_messages();
      $pages = $this->ensureShopLegalPages();
      $cid = (int)($pages['withdrawal'] ?? 0);
      $body = '';
      if ($cid > 0) {
         $renderer = dbx()->get_include_obj('dbxContentRenderer', 'dbxContent');
         $body = is_object($renderer) ? (string)$renderer->renderStatic($cid, array('template' => 'c-body1-footer')) : '';
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
         '<div class="dbx-shop-cms-page">' . $body . '</div>' . $this->withdrawalFormHtml($form),
         'withdrawal'
      );
   }
}
