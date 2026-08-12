<?php
namespace dbx\dbxShop;

trait dbxShopRepositoryOrderServiceTrait {



   public function createOrderFromItems(array $items, string $channelKey = 'shop', string $customerName = '', string $customerEmail = '', string $note = '', string $paymentProvider = '', string $paymentStatus = 'open', string $status = 'payment_pending', string $customerPhone = '', string $shippingAddress = '', string $legalSnapshot = '', string $withdrawalSnapshot = ''): ?array {
      $this->install();
      if ($items === array()) {
         return null;
      }

      $now = date('Y-m-d H:i:s');
      $orderNo = 'S' . date('YmdHis') . '-' . random_int(1000, 9999);
      $uid = function_exists('dbx') ? (int)dbx()->user() : 0;
      $allowedStatus = array('new', 'payment_pending', 'paid', 'processing', 'shipped', 'done', 'cancelled');
      $allowedPayment = array('open', 'created', 'pending', 'completed', 'paid', 'failed', 'cancelled', 'refunded');
      if (!in_array($status, $allowedStatus, true)) {
         $status = 'payment_pending';
      }
      if (!in_array($paymentStatus, $allowedPayment, true)) {
         $paymentStatus = 'open';
      }
      $total = 0.0;
      $snapshots = array();

      foreach ($items as $sku => $qty) {
         $qty = max(1, (int)$qty);
         $product = $this->productBySku((string)$sku);
         if (!$product) continue;
         $price = (float)($product['price_gross'] ?? 0);
         $shipping = (float)($product['effective_shipping_gross'] ?? 0);
         $line = ($price + $shipping) * $qty;
         $total += $line;
         $snapshots[] = array(
            'product_id' => (int)($product['id'] ?? 0),
            'product_type' => (string)($product['product_type'] ?? ''),
            'sku' => (string)($product['sku'] ?? $sku),
            'title' => (string)($product['title'] ?? ''),
            'qty' => $qty,
            'price_gross' => $price,
            'tax_rate' => (float)($product['effective_tax_rate'] ?? 0),
            'shipping_gross' => $shipping,
            'total_gross' => $line,
         );
      }

      if ($snapshots === array()) {
         return null;
      }
      $stockReserved = $this->hasReservableStockSnapshots($snapshots) ? 1 : 0;

      $db = $this->db();
      if ($db->begin($this->dd('shopOrder')) !== 1) {
         throw new \RuntimeException('Bestelltransaktion konnte nicht gestartet werden.');
      }

      try {
         $orderOk = (int)$db->insert($this->dd('shopOrder'), array(
            'create_date' => $now,
            'update_date' => $now,
            'order_no' => $orderNo,
            'uid' => $uid,
            'status' => $status,
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'customer_phone' => $customerPhone,
            'shipping_address' => $shippingAddress,
            'total_gross' => $total,
            'currency' => 'EUR',
            'channel_key' => $channelKey,
            'payment_provider' => $paymentProvider,
            'payment_status' => $paymentStatus,
            'stock_reserved' => $stockReserved,
            'legal_snapshot' => $legalSnapshot,
            'withdrawal_snapshot' => $withdrawalSnapshot,
            'note' => $note,
         ), 0);
         if ($orderOk !== 1) {
            throw new \RuntimeException('order_insert_failed');
         }
         $orderId = (int)$db->get_insert_id();
         if ($orderId <= 0) {
            throw new \RuntimeException('order_id_missing');
         }

         foreach ($snapshots as $item) {
            if ($db->insert($this->dd('shopOrderItem'), array(
               'create_date' => $now,
               'update_date' => $now,
               'order_id' => $orderId,
               'product_id' => $item['product_id'],
               'sku' => $item['sku'],
               'title' => $item['title'],
               'qty' => $item['qty'],
               'price_gross' => $item['price_gross'],
               'tax_rate' => $item['tax_rate'],
               'shipping_gross' => $item['shipping_gross'],
               'total_gross' => $item['total_gross'],
            ), 0) !== 1) {
               throw new \RuntimeException('order_item_insert_failed');
            }
         }

         $reserved = $stockReserved === 1 ? $this->reserveStockForSnapshots($snapshots) : 0;
         if ($stockReserved === 1 && $reserved <= 0) {
            throw new \RuntimeException('stock_reservation_failed');
         }
         if (!$this->addOrderHistory($orderId, 'created', '', $status, 'Bestellung wurde angelegt.')) {
            throw new \RuntimeException('order_history_insert_failed');
         }
         if ($db->commit($this->dd('shopOrder')) !== 1) {
            throw new \RuntimeException('order_commit_failed');
         }
      } catch (\Throwable $e) {
         $db->rollback($this->dd('shopOrder'));
         if (str_starts_with($e->getMessage(), 'Nicht genuegend Lagerbestand')) {
            throw $e;
         }
         dbx()->debug('#Shop order rollback error=(' . $e->getMessage() . ')');
         return null;
      }

      return $this->orderByNo($orderNo);
   }


   public function importChannelOrder(string $channelKey, array $payload): ?array {
      $this->install();
      $channel = $this->channelByKey($channelKey);
      if (!$channel || (int)($channel['order_import_enabled'] ?? 0) !== 1 || (int)($channel['active'] ?? 0) !== 1) {
         throw new \RuntimeException('Order-Import fuer diesen Channel ist nicht aktiv.');
      }

      $externalId = trim((string)($payload['order_id'] ?? $payload['external_order_id'] ?? $payload['id'] ?? ''));
      if ($externalId === '') {
         throw new \RuntimeException('Payload enthaelt keine externe Bestellnummer.');
      }

      $paymentStatus = strtolower((string)($payload['payment_status'] ?? $payload['status'] ?? 'completed'));
      $normalizedPayment = in_array($paymentStatus, array('paid', 'completed', 'captured'), true)
         ? 'completed'
         : (in_array($paymentStatus, array('cancelled', 'canceled', 'voided'), true) ? 'cancelled' : 'pending');

      $existing = $this->orderByPaymentReference($channelKey, $externalId);
      if ($existing) {
         $this->updateOrderPayment((int)$existing['id'], $channelKey, $normalizedPayment, $externalId, $payload);
         return $this->orderByNo((string)$existing['order_no']);
      }

      $items = is_array($payload['items'] ?? null) ? $payload['items'] : array();
      if ($items === array()) {
         throw new \RuntimeException('Payload enthaelt keine Positionen.');
      }

      $now = date('Y-m-d H:i:s');
      $orderNo = 'C' . date('YmdHis') . '-' . random_int(1000, 9999);
      $customer = is_array($payload['customer'] ?? null) ? $payload['customer'] : array();
      $customerName = (string)($payload['customer_name'] ?? $customer['name'] ?? '');
      $customerEmail = (string)($payload['customer_email'] ?? $customer['email'] ?? '');
      $customerPhone = (string)($payload['customer_phone'] ?? $customer['phone'] ?? '');
      $shipping = is_array($payload['shipping_address'] ?? null) ? $payload['shipping_address'] : (is_array($payload['shipping'] ?? null) ? $payload['shipping'] : array());
      $shippingAddress = trim((string)($payload['shipping_address_text'] ?? $payload['address'] ?? ''));
      if ($shippingAddress === '' && $shipping !== array()) {
         $shippingAddress = trim(implode("\n", array_filter(array_map('strval', array(
            $shipping['name'] ?? $customerName,
            $shipping['street'] ?? $shipping['address1'] ?? '',
            $shipping['address2'] ?? '',
            trim((string)($shipping['zip'] ?? $shipping['postal_code'] ?? '') . ' ' . (string)($shipping['city'] ?? '')),
            $shipping['country'] ?? $shipping['country_code'] ?? '',
         )))));
      }
      $currency = (string)($payload['currency'] ?? 'EUR');
      $snapshots = array();
      $total = 0.0;

      foreach ($items as $item) {
         if (!is_array($item)) {
            continue;
         }
         $sku = (string)($item['sku'] ?? $item['seller_sku'] ?? $item['item_sku'] ?? '');
         $qty = max(1, (int)($item['qty'] ?? $item['quantity'] ?? 1));
         $product = $sku !== '' ? $this->productBySku($sku, false) : null;
         $price = (float)($item['price_gross'] ?? $item['price'] ?? $item['unit_price'] ?? ($product['price_gross'] ?? 0));
         $shipping = (float)($item['shipping_gross'] ?? $item['shipping'] ?? 0);
         $lineTotal = (float)($item['total_gross'] ?? $item['total'] ?? (($price + $shipping) * $qty));
         $total += $lineTotal;
         $snapshots[] = array(
            'product_id' => (int)($product['id'] ?? 0),
            'product_type' => (string)($product['product_type'] ?? ''),
            'sku' => $sku,
            'title' => (string)($item['title'] ?? $item['name'] ?? $product['title'] ?? $sku),
            'qty' => $qty,
            'price_gross' => $price,
            'tax_rate' => (float)($item['tax_rate'] ?? $product['effective_tax_rate'] ?? 0),
            'shipping_gross' => $shipping,
            'total_gross' => $lineTotal,
         );
      }

      if ($snapshots === array()) {
         throw new \RuntimeException('Payload enthaelt keine verwertbaren Positionen.');
      }
      if (isset($payload['total_gross']) || isset($payload['total']) || isset($payload['amount'])) {
         $total = (float)($payload['total_gross'] ?? $payload['total'] ?? $payload['amount']);
      }
      $stockReserved = $normalizedPayment !== 'cancelled' && $this->hasReservableStockSnapshots($snapshots) ? 1 : 0;

      $db = $this->db();
      if ($db->begin($this->dd('shopOrder')) !== 1) {
         throw new \RuntimeException('Channel-Bestelltransaktion konnte nicht gestartet werden.');
      }

      try {
         // Ein no-op-Update auf dem Channel erzeugt auf den unterstuetzten
         // relationalen Datenbanken einen Zeilen-Lock bis zum Commit. Damit
         // werden gleiche Providerreferenzen auch ausserhalb von SQLite und
         // ueber mehrere App-Prozesse pro Channel serialisiert.
         $channelId = (int)($channel['id'] ?? 0);
         $channelDd = $this->dd('shopChannel');
         $channelServer = $db->get_dd_server($channelDd);
         $channelTable = $db->get_dd_table($channelDd);
         $lockResult = $channelId > 0
            ? $db->update_query(
               $channelServer,
               'UPDATE ' . $channelTable . ' SET id = id WHERE id = ' . $channelId . ' AND trash = 0'
            )
            : -2;
         $lockedChannel = $lockResult >= 0
            ? $db->select1($channelDd, 'id = ' . $channelId . ' AND trash = 0', 'id', 0)
            : array();
         if (!is_array($lockedChannel) || (int)($lockedChannel['id'] ?? 0) !== $channelId) {
            throw new \RuntimeException('channel_import_lock_failed');
         }

         // Zweite Idempotenzpruefung nach dem serialisierenden Channel-Lock.
         $duplicate = $db->select1(
            $this->dd('shopOrder'),
            'payment_provider = ' . $this->sqlValue($channelKey)
               . ' AND payment_reference = ' . $this->sqlValue($externalId)
               . ' AND trash = 0',
            'id,order_no',
            0
         );
         if (is_array($duplicate) && (int)($duplicate['id'] ?? 0) > 0) {
            $db->rollback($this->dd('shopOrder'));
            return $this->orderByNo((string)$duplicate['order_no']);
         }

         if ($db->insert($this->dd('shopOrder'), array(
            'create_date' => $now,
            'update_date' => $now,
            'order_no' => $orderNo,
            'uid' => 0,
            'status' => $normalizedPayment === 'completed' ? 'paid' : ($normalizedPayment === 'cancelled' ? 'cancelled' : 'payment_pending'),
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'customer_phone' => $customerPhone,
            'shipping_address' => $shippingAddress,
            'total_gross' => $total,
            'currency' => $currency,
            'channel_key' => $channelKey,
            'payment_provider' => $channelKey,
            'payment_status' => $normalizedPayment,
            'stock_reserved' => $stockReserved,
            'payment_reference' => $externalId,
            'payment_payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
         ), 0) !== 1) {
            throw new \RuntimeException('channel_order_insert_failed');
         }
         $orderId = (int)$db->get_insert_id();
         if ($orderId <= 0) throw new \RuntimeException('channel_order_id_missing');

         foreach ($snapshots as $item) {
            if ($db->insert($this->dd('shopOrderItem'), array(
               'create_date' => $now,
               'update_date' => $now,
               'order_id' => $orderId,
               'product_id' => $item['product_id'],
               'sku' => $item['sku'],
               'title' => $item['title'],
               'qty' => $item['qty'],
               'price_gross' => $item['price_gross'],
               'tax_rate' => $item['tax_rate'],
               'shipping_gross' => $item['shipping_gross'],
               'total_gross' => $item['total_gross'],
            ), 0) !== 1) {
               throw new \RuntimeException('channel_order_item_insert_failed');
            }
         }
         $reserved = $stockReserved === 1 ? $this->reserveStockForSnapshots($snapshots) : 0;
         if ($stockReserved === 1 && $reserved <= 0) {
            throw new \RuntimeException('channel_stock_reservation_failed');
         }
         if (!$this->addOrderHistory($orderId, 'channel_import', '', $normalizedPayment, 'Bestellung wurde ueber Channel ' . $channelKey . ' importiert.')) {
            throw new \RuntimeException('channel_order_history_failed');
         }
         if ($db->commit($this->dd('shopOrder')) !== 1) {
            throw new \RuntimeException('channel_order_commit_failed');
         }
      } catch (\Throwable $e) {
         $db->rollback($this->dd('shopOrder'));
         dbx()->debug('#Shop channel order rollback reference=(' . $externalId . ') error=(' . $e->getMessage() . ')');
         throw $e;
      }

      return $this->orderByNo($orderNo);
   }



   public function orderByNo(string $orderNo): ?array {
      $this->install();
      $row = $this->db()->select1($this->dd('shopOrder'), 'order_no = ' . $this->sqlValue($orderNo) . ' AND trash = 0', '*', 0);
      if (!is_array($row) || (int)($row['id'] ?? 0) <= 0) return null;
      $row['items'] = $this->orderItems((int)$row['id']);
      $row['history'] = $this->orderHistory((int)$row['id']);
      $row['withdrawals'] = $this->withdrawalsForOrder((int)$row['id']);
      return $row;
   }


   public function orderById(int $id): ?array {
      $this->install();
      $row = $this->db()->select1($this->dd('shopOrder'), 'id = ' . (int)$id . ' AND trash = 0', '*', 0);
      if (!is_array($row) || (int)($row['id'] ?? 0) <= 0) return null;
      $row['items'] = $this->orderItems((int)$row['id']);
      $row['history'] = $this->orderHistory((int)$row['id']);
      $row['withdrawals'] = $this->withdrawalsForOrder((int)$row['id']);
      return $row;
   }


   public function ordersByUid(int $uid, int $limit = 25): array {
      $this->install();
      if ($uid <= 0) {
         return array();
      }
      $rows = $this->db()->select($this->dd('shopOrder'), 'uid = ' . (int)$uid . ' AND trash = 0', '*', 'create_date DESC, id DESC', 'ASC', '', max(1, min(100, $limit)), 0, 0);
      $rows = is_array($rows) ? $rows : array();
      foreach ($rows as &$row) {
         $orderId = (int)($row['id'] ?? 0);
         $row['items'] = $this->orderItems($orderId);
         $row['history'] = $this->orderHistory($orderId);
         $row['withdrawals'] = $this->withdrawalsForOrder($orderId);
      }
      unset($row);
      return $rows;
   }


   public function orders(array $filters = array(), int $limit = 50, int $offset = 0, string $sort = 'create_date', string $direction = 'DESC'): array {
      $this->install();
      $where = array('trash = 0');

      $query = trim((string)($filters['query'] ?? ''));
      if ($query !== '') {
         $like = $this->sqlLikeValue($query);
         $where[] = '(order_no LIKE ' . $like . ' OR customer_name LIKE ' . $like . ' OR customer_email LIKE ' . $like . ' OR channel_key LIKE ' . $like . ' OR payment_reference LIKE ' . $like . ')';
      }
      foreach (array('status', 'payment_status', 'shipping_status', 'channel_key') as $field) {
         $value = trim((string)($filters[$field] ?? ''));
         if ($value !== '') {
            $where[] = $field . ' = ' . $this->sqlValue($value);
         }
      }

      $allowedSort = array('create_date', 'order_no', 'status', 'payment_status', 'shipping_status', 'customer_name', 'total_gross', 'channel_key');
      if (!in_array($sort, $allowedSort, true)) {
         $sort = 'create_date';
      }
      $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
      $max = $limit > 0 ? max(1, $limit) : 0;
      $rows = $this->db()->select($this->dd('shopOrder'), implode(' AND ', $where), '*', $sort . ' ' . $direction . ', id DESC', 'ASC', '', $max, max(0, $offset), 0);
      $rows = is_array($rows) ? $rows : array();
      foreach ($rows as &$row) {
         $row['items'] = $this->orderItems((int)$row['id']);
      }
      unset($row);
      return $rows;
   }



   public function orderCount(array $filters = array()): int {
      $this->install();
      $where = array('trash = 0');
      $query = trim((string)($filters['query'] ?? ''));
      if ($query !== '') {
         $like = $this->sqlLikeValue($query);
         $where[] = '(order_no LIKE ' . $like . ' OR customer_name LIKE ' . $like . ' OR customer_email LIKE ' . $like . ' OR channel_key LIKE ' . $like . ' OR payment_reference LIKE ' . $like . ')';
      }
      foreach (array('status', 'payment_status', 'shipping_status', 'channel_key') as $field) {
         $value = trim((string)($filters[$field] ?? ''));
         if ($value !== '') {
            $where[] = $field . ' = ' . $this->sqlValue($value);
         }
      }
      return max(0, (int)$this->db()->count($this->dd('shopOrder'), implode(' AND ', $where)));
   }



   public function orderChannelKeys(): array {
      $this->install();
      $rows = $this->db()->select($this->dd('shopOrder'), "trash = 0 AND channel_key <> ''", 'channel_key', 'channel_key ASC', 'ASC', '', 0, 0, 0);
      $keys = array();
      foreach (is_array($rows) ? $rows : array() as $row) {
         $key = (string)($row['channel_key'] ?? '');
         if ($key !== '') {
            $keys[$key] = $key;
         }
      }
      ksort($keys);
      return array_values($keys);
   }



   public function updateOrderAdmin(int $id, array $data): bool {
      $this->install();
      $allowedStatus = array('new', 'payment_pending', 'paid', 'processing', 'shipped', 'done', 'cancelled');
      $allowedPayment = array('open', 'created', 'pending', 'completed', 'paid', 'failed', 'cancelled', 'refunded');
      $allowedShipping = array('open', 'ready', 'shipped', 'delivered', 'returned');
      $before = $this->orderById($id);
      $status = (string)($data['status'] ?? 'new');
      $paymentStatus = (string)($data['payment_status'] ?? 'open');
      $shippingStatus = (string)($data['shipping_status'] ?? ($before['shipping_status'] ?? 'open'));
      if (!in_array($status, $allowedStatus, true)) $status = 'new';
      if (!in_array($paymentStatus, $allowedPayment, true)) $paymentStatus = 'open';
      if (!in_array($shippingStatus, $allowedShipping, true)) $shippingStatus = 'open';
      $invoiceNo = trim((string)($data['invoice_no'] ?? ($before['invoice_no'] ?? '')));
      $invoiceDate = trim((string)($data['invoice_date'] ?? ($before['invoice_date'] ?? '')));
      if ($invoiceNo === '' && in_array($status, array('paid', 'processing', 'shipped', 'done'), true)) {
         $invoiceNo = $this->nextInvoiceNo();
         $invoiceDate = date('Y-m-d');
      }
      $shippedDate = trim((string)($data['shipped_date'] ?? ($before['shipped_date'] ?? '')));
      if ($shippingStatus === 'shipped' && $shippedDate === '') {
         $shippedDate = date('Y-m-d H:i:s');
      }
      $shippingProvider = trim((string)($data['shipping_provider'] ?? ''));
      $trackingNo = trim((string)($data['tracking_no'] ?? ''));
      $trackingUrl = trim((string)($data['tracking_url'] ?? ''));
      if ($trackingUrl === '' && $trackingNo !== '') {
         $trackingUrl = $this->trackingUrlForProvider($shippingProvider, $trackingNo);
      }

      $ok = $this->db()->update($this->dd('shopOrder'), array(
         'update_date' => date('Y-m-d H:i:s'),
         'status' => $status,
         'payment_status' => $paymentStatus,
         'payment_reference' => (string)($data['payment_reference'] ?? ''),
         'invoice_no' => $invoiceNo,
         'invoice_date' => $invoiceDate,
         'shipping_status' => $shippingStatus,
         'shipping_provider' => $shippingProvider,
         'tracking_no' => $trackingNo,
         'tracking_url' => $trackingUrl,
         'shipped_date' => $shippedDate,
         'note' => (string)($data['note'] ?? ''),
      ), 'id = ' . (int)$id . ' AND trash = 0', 0);
      if (is_array($before)) {
         foreach (array(
            'status' => $status,
            'payment_status' => $paymentStatus,
            'shipping_status' => $shippingStatus,
            'invoice_no' => $invoiceNo,
            'tracking_no' => $trackingNo,
         ) as $field => $newValue) {
            $oldValue = (string)($before[$field] ?? '');
            if ($oldValue !== (string)$newValue) {
               $this->addOrderHistory($id, $field, $oldValue, (string)$newValue, 'Admin-Aenderung');
            }
         }
         if ($status === 'cancelled' || in_array($paymentStatus, array('cancelled', 'refunded'), true) || $shippingStatus === 'returned') {
            $fresh = $this->orderById($id);
            if (is_array($fresh)) {
               $this->releaseStockForOrder($fresh, 'Bestand wurde durch Statusaenderung zurueckgebucht.');
            }
         }
      }
      return $ok !== 0 || $this->orderById($id) !== null;
   }

   private function trackingUrlForProvider(string $provider, string $trackingNo): string {
      $trackingNo = trim($trackingNo);
      if ($trackingNo === '') {
         return '';
      }
      $providerKey = strtolower(trim($provider));
      if (str_contains($providerKey, 'dhl')) {
         return 'https://www.dhl.de/de/privatkunden/pakete-empfangen/verfolgen.html?piececode=' . rawurlencode($trackingNo);
      }
      if (str_contains($providerKey, 'ups')) {
         return 'https://www.ups.com/track?tracknum=' . rawurlencode($trackingNo);
      }
      if (str_contains($providerKey, 'dpd')) {
         return 'https://tracking.dpd.de/status/de_DE/parcel/' . rawurlencode($trackingNo);
      }
      if (str_contains($providerKey, 'hermes')) {
         return 'https://www.myhermes.de/empfangen/sendungsverfolgung/?su=' . rawurlencode($trackingNo);
      }
      return '';
   }

   public function updateOrderQuickAction(int $id, string $action): array {
      $this->install();
      $order = $this->orderById($id);
      if (!is_array($order)) {
         return array(false, 'Bestellung nicht gefunden.');
      }

      $data = $order;
      $message = '';
      switch ($action) {
         case 'mark_paid':
            $data['payment_status'] = 'paid';
            if (in_array((string)($data['status'] ?? ''), array('new', 'payment_pending'), true)) {
               $data['status'] = 'paid';
            }
            $message = 'Bestellung wurde als bezahlt markiert.';
            break;

         case 'processing':
            $data['status'] = 'processing';
            $message = 'Bestellung wurde in Bearbeitung gesetzt.';
            break;

         case 'ready':
            $data['shipping_status'] = 'ready';
            if (in_array((string)($data['status'] ?? ''), array('new', 'payment_pending', 'paid'), true)) {
               $data['status'] = 'processing';
            }
            $message = 'Bestellung wurde als versandbereit markiert.';
            break;

         case 'shipped':
            $data['shipping_status'] = 'shipped';
            $data['status'] = 'shipped';
            if (trim((string)($data['shipped_date'] ?? '')) === '') {
               $data['shipped_date'] = date('Y-m-d H:i:s');
            }
            $message = 'Bestellung wurde als versendet markiert.';
            break;

         case 'delivered':
            $data['shipping_status'] = 'delivered';
            $data['status'] = 'done';
            $message = 'Bestellung wurde als zugestellt und abgeschlossen markiert.';
            break;

         case 'cancel':
            $data['status'] = 'cancelled';
            if (!in_array((string)($data['payment_status'] ?? ''), array('completed', 'paid', 'refunded'), true)) {
               $data['payment_status'] = 'cancelled';
            }
            $message = 'Bestellung wurde storniert.';
            break;

         case 'refund':
            $data['payment_status'] = 'refunded';
            $data['status'] = 'cancelled';
            if ((string)($data['shipping_status'] ?? '') !== 'delivered') {
               $data['shipping_status'] = 'returned';
            }
            $message = 'Bestellung wurde als erstattet markiert.';
            break;

         default:
            return array(false, 'Unbekannte Bestellaktion.');
      }

      $ok = $this->updateOrderAdmin($id, $data);
      if ($ok) {
         $this->addOrderHistory($id, 'quick_action', '', $action, $message);
         return array(true, $message);
      }
      return array(false, 'Bestellaktion konnte nicht gespeichert werden.');
   }

   public function deleteOrder(int $id): bool {
      $this->install();
      return $this->db()->update($this->dd('shopOrder'), array('trash' => 1, 'update_date' => date('Y-m-d H:i:s')), 'id = ' . (int)$id . ' AND trash = 0', 0) !== 0;
   }


   public function orderByPaymentReference(string $provider, string $reference): ?array {
      $this->install();
      $row = $this->db()->select1($this->dd('shopOrder'), 'payment_provider = ' . $this->sqlValue($provider) . ' AND payment_reference = ' . $this->sqlValue($reference) . ' AND trash = 0', '*', 0);
      if (!is_array($row) || (int)($row['id'] ?? 0) <= 0) return null;
      $row['items'] = $this->orderItems((int)$row['id']);
      return $row;
   }



   public function orderItems(int $orderId): array {
      $rows = $this->db()->select($this->dd('shopOrderItem'), 'order_id = ' . (int)$orderId . ' AND trash = 0', '*', 'id ASC', 'ASC', '', 0, 0, 0);
      return is_array($rows) ? $rows : array();
   }

   private function paymentProcessingRetrySeconds(): int {
      $seconds = (int)dbx()->get_cfg('dbxShop', 'payment_processing_retry_seconds', 300);
      return max(60, min(3600, $seconds));
   }

   /**
    * Ein processing-Claim darf erst nach Ablauf des Lease erneut beansprucht
    * werden. Provider-POSTs verwenden dabei weiterhin denselben Idempotency-Key.
    */
   private function isStalePaymentProcessing(array $order, int $retrySeconds, ?int $now = null): bool {
      if (strtolower(trim((string)($order['payment_status'] ?? ''))) !== 'processing') {
         return false;
      }
      $updatedAt = strtotime((string)($order['update_date'] ?? ''));
      if ($updatedAt === false || $updatedAt <= 0) {
         return false;
      }
      $now = $now ?? time();
      return $updatedAt <= $now - max(60, $retrySeconds);
   }

   /**
    * Beansprucht einen Provider-Abschluss atomar.
    *
    * Nur der Request, der created/open/failed oder einen abgelaufenen
    * processing-Lease nach processing ueberfuehrt, darf den externen
    * Capture-/Complete-Aufruf ausfuehren.
    */
   public function claimOrderPayment(int $orderId, string $provider, string $reference): bool {
      $this->install();
      $provider = trim($provider);
      $reference = trim($reference);
      if ($orderId <= 0 || $provider === '' || $reference === '') return false;

      $db = $this->db();
      if ($db->begin($this->dd('shopOrder')) !== 1) return false;
      try {
         $order = $db->select1($this->dd('shopOrder'), 'id = ' . $orderId . ' AND trash = 0', '*', 0);
         $oldStatus = strtolower(trim((string)($order['payment_status'] ?? '')));
         $staleProcessing = is_array($order)
            && $this->isStalePaymentProcessing($order, $this->paymentProcessingRetrySeconds());
         if (!is_array($order)
            || !hash_equals($provider, (string)($order['payment_provider'] ?? ''))
            || !hash_equals($reference, (string)($order['payment_reference'] ?? ''))
            || (!in_array($oldStatus, array('open', 'created', 'failed'), true) && !$staleProcessing)) {
            $db->rollback($this->dd('shopOrder'));
            return false;
         }

         $where = 'id = ' . $orderId . ' AND trash = 0'
            . ' AND payment_provider = ' . $this->sqlValue($provider)
            . ' AND payment_reference = ' . $this->sqlValue($reference)
            . ' AND payment_status = ' . $this->sqlValue($oldStatus);
         if ($staleProcessing) {
            // Das alte Lease-Datum macht auch den Recovery-Claim zu einem
            // Compare-and-swap. Ein paralleler Recovery-Request verliert.
            $where .= ' AND update_date = ' . $this->sqlValue((string)($order['update_date'] ?? ''));
         }
         if ($db->update($this->dd('shopOrder'), array(
            'payment_status' => 'processing',
            'status' => 'payment_pending',
            'update_date' => date('Y-m-d H:i:s'),
         ), $where, 0) !== 1 || (int)$db->_update_count !== 1) {
            $db->rollback($this->dd('shopOrder'));
            return false;
         }
         $claimMessage = $staleProcessing
            ? 'Verwaister Zahlungsabschluss wurde mit demselben Idempotency-Key erneut beansprucht.'
            : 'Zahlungsabschluss wurde atomar beansprucht.';
         if (!$this->addOrderHistory($orderId, 'payment', $oldStatus, 'processing', $claimMessage)
            || $db->commit($this->dd('shopOrder')) !== 1) {
            throw new \RuntimeException('payment_claim_commit_failed');
         }
         return true;
      } catch (\Throwable $e) {
         $db->rollback($this->dd('shopOrder'));
         dbx()->debug('#Shop payment claim rollback order=(' . $orderId . ') error=(' . $e->getMessage() . ')');
         return false;
      }
   }

   /**
    * Aktualisiert den Zahlungsstatus idempotent und verhindert Downgrades
    * terminaler Zahlungen sowie Referenz-/Providerwechsel.
    */
   public function updateOrderPayment(int $orderId, string $provider, string $status, string $reference = '', array $payload = array()): bool {
      $this->install();
      $provider = trim($provider);
      $status = strtolower(trim($status));
      $reference = trim($reference);
      $allowed = array('open', 'created', 'processing', 'pending', 'completed', 'paid', 'failed', 'cancelled', 'refunded');
      if ($orderId <= 0 || $provider === '' || !in_array($status, $allowed, true)) return false;

      $db = $this->db();
      if ($db->begin($this->dd('shopOrder')) !== 1) return false;
      try {
         $order = $db->select1($this->dd('shopOrder'), 'id = ' . $orderId . ' AND trash = 0', '*', 0);
         if (!is_array($order)) {
            $db->rollback($this->dd('shopOrder'));
            return false;
         }
         $oldProvider = trim((string)($order['payment_provider'] ?? ''));
         $oldReference = trim((string)($order['payment_reference'] ?? ''));
         $oldStatus = strtolower((string)($order['payment_status'] ?? 'open'));
         if (($oldProvider !== '' && !hash_equals($oldProvider, $provider))
            || ($oldReference !== '' && $reference !== '' && !hash_equals($oldReference, $reference))) {
            $db->rollback($this->dd('shopOrder'));
            return false;
         }
         if ($reference === '') $reference = $oldReference;

         if ((in_array($oldStatus, array('completed', 'paid'), true)
               && !in_array($status, array('completed', 'paid', 'refunded'), true))
            || ($oldStatus === 'refunded' && $status !== 'refunded')
            || ($oldStatus === 'cancelled' && !in_array($status, array('cancelled', 'refunded'), true))) {
            $db->rollback($this->dd('shopOrder'));
            return false;
         }

         if ($oldStatus === $status && $oldProvider === $provider && $oldReference === $reference) {
            $db->rollback($this->dd('shopOrder'));
            return true;
         }

         $orderStatus = in_array($status, array('completed', 'paid'), true)
            ? 'paid'
            : (in_array($status, array('cancelled', 'refunded'), true) ? 'cancelled' : 'payment_pending');
         $where = 'id = ' . $orderId . ' AND trash = 0 AND payment_status = ' . $this->sqlValue($oldStatus);
         if ($db->update($this->dd('shopOrder'), array(
            'update_date' => date('Y-m-d H:i:s'),
            'payment_provider' => $provider,
            'payment_status' => $status,
            'payment_reference' => $reference,
            'payment_payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'status' => $orderStatus,
         ), $where, 0) !== 1 || (int)$db->_update_count !== 1) {
            $db->rollback($this->dd('shopOrder'));
            return false;
         }
         if (!$this->addOrderHistory($orderId, 'payment', $oldStatus, $status, 'Zahlungsstatus wurde aktualisiert.')
            || $db->commit($this->dd('shopOrder')) !== 1) {
            throw new \RuntimeException('payment_update_commit_failed');
         }
      } catch (\Throwable $e) {
         $db->rollback($this->dd('shopOrder'));
         dbx()->debug('#Shop payment rollback order=(' . $orderId . ') error=(' . $e->getMessage() . ')');
         return false;
      }

      if (in_array($status, array('cancelled', 'refunded'), true)) {
         $fresh = $this->orderById($orderId);
         if (is_array($fresh)) {
            $this->releaseStockForOrder($fresh, 'Bestand wurde durch Zahlungsstatus zurueckgebucht.');
         }
      }
      return true;
   }

   public function addOrderHistory(int $orderId, string $eventType, string $oldValue = '', string $newValue = '', string $message = ''): bool {
      if ($orderId <= 0) {
         return false;
      }
      $order = $this->db()->select1($this->dd('shopOrder'), 'id = ' . (int)$orderId . ' AND trash = 0', 'owner,uid', 0);
      $owner = is_array($order) ? (int)($order['owner'] ?? 0) : 0;
      if ($owner <= 0 && is_array($order)) {
         $owner = (int)($order['uid'] ?? 0);
      }
      $data = array(
         'order_id' => $orderId,
         'event_type' => $eventType,
         'old_value' => $oldValue,
         'new_value' => $newValue,
         'message' => $message,
      );
      if ($owner > 0) {
         $data['owner'] = $owner;
      }
      return $this->db()->insert($this->dd('shopOrderHistory'), $data, 0) === 1;
   }

   public function orderHistory(int $orderId): array {
      $rows = $this->db()->select($this->dd('shopOrderHistory'), 'order_id = ' . (int)$orderId . ' AND trash = 0', '*', 'create_date DESC, id DESC', 'ASC', '', 0, 0, 0);
      return is_array($rows) ? $rows : array();
   }

   public function hasOrderHistoryEvent(int $orderId, string $eventType, string $newValue = ''): bool {
      if ($orderId <= 0 || trim($eventType) === '') return false;
      $where = 'order_id = ' . $orderId
         . ' AND event_type = ' . $this->sqlValue(trim($eventType))
         . ' AND trash = 0';
      if ($newValue !== '') {
         $where .= ' AND new_value = ' . $this->sqlValue($newValue);
      }
      return $this->db()->count($this->dd('shopOrderHistory'), $where) > 0;
   }

   public function dashboardStats(): array {
      $this->install();
      $ordersOpen = (int)$this->db()->count($this->dd('shopOrder'), "trash = 0 AND status IN ('new','payment_pending','paid','processing')");
      $paymentsOpen = (int)$this->db()->count($this->dd('shopOrder'), "trash = 0 AND payment_status IN ('open','created','pending')");
      $shipOpen = (int)$this->db()->count($this->dd('shopOrder'), "trash = 0 AND shipping_status IN ('open','ready')");
      $withdrawalsOpen = (int)$this->db()->count($this->dd('shopWithdrawal'), "trash = 0 AND status IN ('new','processing')");
      $stockLow = 0;
      if ($this->stockEnabled()) {
         $stockLow = (int)$this->db()->count($this->dd('shopProduct'), "trash = 0 AND active = 1 AND product_type = 'physical' AND stock <= 3");
      }
      return array(
         'orders_open' => $ordersOpen,
         'payments_open' => $paymentsOpen,
         'shipping_open' => $shipOpen,
         'withdrawals_open' => $withdrawalsOpen,
         'stock_low' => $stockLow,
         'products_active' => (int)$this->db()->count($this->dd('shopProduct'), 'trash = 0 AND active = 1'),
      );
   }
}
