<?php
namespace dbx\dbxShop;

trait dbxShopRepositoryOrderServiceTrait {



   public function create_order_from_items(array $items, string $channel_key = 'shop', string $customer_name = '', string $customer_email = '', string $note = '', string $payment_provider = '', string $payment_status = 'open', string $status = 'payment_pending', string $customer_phone = '', string $shipping_address = '', string $legal_snapshot = '', string $withdrawal_snapshot = ''): ?array {
      $this->install();
      if ($items === array()) {
         return null;
      }

      $now = date('Y-m-d H:i:s');
      $order_no = 'S' . date('YmdHis') . '-' . random_int(1000, 9999);
      $uid = function_exists('dbx') ? (int)dbx()->user() : 0;
      $allowed_status = array('new', 'payment_pending', 'paid', 'processing', 'shipped', 'done', 'cancelled');
      $allowed_payment = array('open', 'created', 'pending', 'completed', 'paid', 'failed', 'cancelled', 'refunded');
      if (!in_array($status, $allowed_status, true)) {
         $status = 'payment_pending';
      }
      if (!in_array($payment_status, $allowed_payment, true)) {
         $payment_status = 'open';
      }
      $total = 0.0;
      $snapshots = array();

      foreach ($items as $sku => $qty) {
         $qty = max(1, (int)$qty);
         $product = $this->product_by_sku((string)$sku);
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
      $stock_reserved = $this->has_reservable_stock_snapshots($snapshots) ? 1 : 0;

      $db = $this->db();
      if ($db->begin($this->dd('shopOrder')) !== 1) {
         throw new \RuntimeException('Bestelltransaktion konnte nicht gestartet werden.');
      }

      try {
         $order_ok = (int)$db->insert($this->dd('shopOrder'), array(
            'create_date' => $now,
            'update_date' => $now,
            'order_no' => $order_no,
            'uid' => $uid,
            'status' => $status,
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'customer_phone' => $customer_phone,
            'shipping_address' => $shipping_address,
            'total_gross' => $total,
            'currency' => 'EUR',
            'channel_key' => $channel_key,
            'payment_provider' => $payment_provider,
            'payment_status' => $payment_status,
            'stock_reserved' => $stock_reserved,
            'legal_snapshot' => $legal_snapshot,
            'withdrawal_snapshot' => $withdrawal_snapshot,
            'note' => $note,
         ), 0);
         if ($order_ok !== 1) {
            throw new \RuntimeException('order_insert_failed');
         }
         $order_id = (int)$db->get_insert_id();
         if ($order_id <= 0) {
            throw new \RuntimeException('order_id_missing');
         }

         foreach ($snapshots as $item) {
            if ($db->insert($this->dd('shopOrderItem'), array(
               'create_date' => $now,
               'update_date' => $now,
               'order_id' => $order_id,
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

         $reserved = $stock_reserved === 1 ? $this->reserve_stock_for_snapshots($snapshots) : 0;
         if ($stock_reserved === 1 && $reserved <= 0) {
            throw new \RuntimeException('stock_reservation_failed');
         }
         if (!$this->add_order_history($order_id, 'created', '', $status, 'Bestellung wurde angelegt.')) {
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

      return $this->order_by_no($order_no);
   }


   public function import_channel_order(string $channel_key, array $payload): ?array {
      $this->install();
      $channel = $this->channel_by_key($channel_key);
      if (!$channel || (int)($channel['order_import_enabled'] ?? 0) !== 1 || (int)($channel['active'] ?? 0) !== 1) {
         throw new \RuntimeException('Order-Import fuer diesen Channel ist nicht aktiv.');
      }

      $external_id = trim((string)($payload['order_id'] ?? $payload['external_order_id'] ?? $payload['id'] ?? ''));
      if ($external_id === '') {
         throw new \RuntimeException('Payload enthaelt keine externe Bestellnummer.');
      }

      $payment_status = strtolower((string)($payload['payment_status'] ?? $payload['status'] ?? 'completed'));
      $normalized_payment = in_array($payment_status, array('paid', 'completed', 'captured'), true)
         ? 'completed'
         : (in_array($payment_status, array('cancelled', 'canceled', 'voided'), true) ? 'cancelled' : 'pending');

      $existing = $this->order_by_payment_reference($channel_key, $external_id);
      if ($existing) {
         $this->update_order_payment((int)$existing['id'], $channel_key, $normalized_payment, $external_id, $payload);
         return $this->order_by_no((string)$existing['order_no']);
      }

      $items = is_array($payload['items'] ?? null) ? $payload['items'] : array();
      if ($items === array()) {
         throw new \RuntimeException('Payload enthaelt keine Positionen.');
      }

      $now = date('Y-m-d H:i:s');
      $order_no = 'C' . date('YmdHis') . '-' . random_int(1000, 9999);
      $customer = is_array($payload['customer'] ?? null) ? $payload['customer'] : array();
      $customer_name = (string)($payload['customer_name'] ?? $customer['name'] ?? '');
      $customer_email = (string)($payload['customer_email'] ?? $customer['email'] ?? '');
      $customer_phone = (string)($payload['customer_phone'] ?? $customer['phone'] ?? '');
      $shipping = is_array($payload['shipping_address'] ?? null) ? $payload['shipping_address'] : (is_array($payload['shipping'] ?? null) ? $payload['shipping'] : array());
      $shipping_address = trim((string)($payload['shipping_address_text'] ?? $payload['address'] ?? ''));
      if ($shipping_address === '' && $shipping !== array()) {
         $shipping_address = trim(implode("\n", array_filter(array_map('strval', array(
            $shipping['name'] ?? $customer_name,
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
         $product = $sku !== '' ? $this->product_by_sku($sku, false) : null;
         $price = (float)($item['price_gross'] ?? $item['price'] ?? $item['unit_price'] ?? ($product['price_gross'] ?? 0));
         $shipping = (float)($item['shipping_gross'] ?? $item['shipping'] ?? 0);
         $line_total = (float)($item['total_gross'] ?? $item['total'] ?? (($price + $shipping) * $qty));
         $total += $line_total;
         $snapshots[] = array(
            'product_id' => (int)($product['id'] ?? 0),
            'product_type' => (string)($product['product_type'] ?? ''),
            'sku' => $sku,
            'title' => (string)($item['title'] ?? $item['name'] ?? $product['title'] ?? $sku),
            'qty' => $qty,
            'price_gross' => $price,
            'tax_rate' => (float)($item['tax_rate'] ?? $product['effective_tax_rate'] ?? 0),
            'shipping_gross' => $shipping,
            'total_gross' => $line_total,
         );
      }

      if ($snapshots === array()) {
         throw new \RuntimeException('Payload enthaelt keine verwertbaren Positionen.');
      }
      if (isset($payload['total_gross']) || isset($payload['total']) || isset($payload['amount'])) {
         $total = (float)($payload['total_gross'] ?? $payload['total'] ?? $payload['amount']);
      }
      $stock_reserved = $normalized_payment !== 'cancelled' && $this->has_reservable_stock_snapshots($snapshots) ? 1 : 0;

      $db = $this->db();
      if ($db->begin($this->dd('shopOrder')) !== 1) {
         throw new \RuntimeException('Channel-Bestelltransaktion konnte nicht gestartet werden.');
      }

      try {
         // Ein no-op-Update auf dem Channel erzeugt auf den unterstuetzten
         // relationalen Datenbanken einen Zeilen-Lock bis zum Commit. Damit
         // werden gleiche Providerreferenzen auch ausserhalb von SQLite und
         // ueber mehrere App-Prozesse pro Channel serialisiert.
         $channel_id = (int)($channel['id'] ?? 0);
         $channel_dd = $this->dd('shopChannel');
         $channel_server = $db->get_dd_server($channel_dd);
         $channel_table = $db->get_dd_table($channel_dd);
         $lock_result = $channel_id > 0
            ? $db->update_query(
               $channel_server,
               'UPDATE ' . $channel_table . ' SET id = id WHERE id = ' . $channel_id . ' AND trash = 0'
            )
            : -2;
         $locked_channel = $lock_result >= 0
            ? $db->select1($channel_dd, 'id = ' . $channel_id . ' AND trash = 0', 'id', 0)
            : array();
         if (!is_array($locked_channel) || (int)($locked_channel['id'] ?? 0) !== $channel_id) {
            throw new \RuntimeException('channel_import_lock_failed');
         }

         // Zweite Idempotenzpruefung nach dem serialisierenden Channel-Lock.
         $duplicate = $db->select1(
            $this->dd('shopOrder'),
            'payment_provider = ' . $this->sql_value($channel_key)
               . ' AND payment_reference = ' . $this->sql_value($external_id)
               . ' AND trash = 0',
            'id,order_no',
            0
         );
         if (is_array($duplicate) && (int)($duplicate['id'] ?? 0) > 0) {
            $db->rollback($this->dd('shopOrder'));
            return $this->order_by_no((string)$duplicate['order_no']);
         }

         if ($db->insert($this->dd('shopOrder'), array(
            'create_date' => $now,
            'update_date' => $now,
            'order_no' => $order_no,
            'uid' => 0,
            'status' => $normalized_payment === 'completed' ? 'paid' : ($normalized_payment === 'cancelled' ? 'cancelled' : 'payment_pending'),
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'customer_phone' => $customer_phone,
            'shipping_address' => $shipping_address,
            'total_gross' => $total,
            'currency' => $currency,
            'channel_key' => $channel_key,
            'payment_provider' => $channel_key,
            'payment_status' => $normalized_payment,
            'stock_reserved' => $stock_reserved,
            'payment_reference' => $external_id,
            'payment_payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
         ), 0) !== 1) {
            throw new \RuntimeException('channel_order_insert_failed');
         }
         $order_id = (int)$db->get_insert_id();
         if ($order_id <= 0) throw new \RuntimeException('channel_order_id_missing');

         foreach ($snapshots as $item) {
            if ($db->insert($this->dd('shopOrderItem'), array(
               'create_date' => $now,
               'update_date' => $now,
               'order_id' => $order_id,
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
         $reserved = $stock_reserved === 1 ? $this->reserve_stock_for_snapshots($snapshots) : 0;
         if ($stock_reserved === 1 && $reserved <= 0) {
            throw new \RuntimeException('channel_stock_reservation_failed');
         }
         if (!$this->add_order_history($order_id, 'channel_import', '', $normalized_payment, 'Bestellung wurde ueber Channel ' . $channel_key . ' importiert.')) {
            throw new \RuntimeException('channel_order_history_failed');
         }
         if ($db->commit($this->dd('shopOrder')) !== 1) {
            throw new \RuntimeException('channel_order_commit_failed');
         }
      } catch (\Throwable $e) {
         $db->rollback($this->dd('shopOrder'));
         dbx()->debug('#Shop channel order rollback reference=(' . $external_id . ') error=(' . $e->getMessage() . ')');
         throw $e;
      }

      return $this->order_by_no($order_no);
   }



   public function order_by_no(string $order_no): ?array {
      $this->install();
      $row = $this->db()->select1($this->dd('shopOrder'), 'order_no = ' . $this->sql_value($order_no) . ' AND trash = 0', '*', 0);
      if (!is_array($row) || (int)($row['id'] ?? 0) <= 0) return null;
      $row['items'] = $this->order_items((int)$row['id']);
      $row['history'] = $this->order_history((int)$row['id']);
      $row['withdrawals'] = $this->withdrawals_for_order((int)$row['id']);
      return $row;
   }


   public function order_by_id(int $id): ?array {
      $this->install();
      $row = $this->db()->select1($this->dd('shopOrder'), 'id = ' . (int)$id . ' AND trash = 0', '*', 0);
      if (!is_array($row) || (int)($row['id'] ?? 0) <= 0) return null;
      $row['items'] = $this->order_items((int)$row['id']);
      $row['history'] = $this->order_history((int)$row['id']);
      $row['withdrawals'] = $this->withdrawals_for_order((int)$row['id']);
      return $row;
   }


   public function orders_by_uid(int $uid, int $limit = 25): array {
      $this->install();
      if ($uid <= 0) {
         return array();
      }
      $rows = $this->db()->select($this->dd('shopOrder'), 'uid = ' . (int)$uid . ' AND trash = 0', '*', 'create_date DESC, id DESC', 'ASC', '', max(1, min(100, $limit)), 0, 0);
      $rows = is_array($rows) ? $rows : array();
      foreach ($rows as &$row) {
         $order_id = (int)($row['id'] ?? 0);
         $row['items'] = $this->order_items($order_id);
         $row['history'] = $this->order_history($order_id);
         $row['withdrawals'] = $this->withdrawals_for_order($order_id);
      }
      unset($row);
      return $rows;
   }


   public function orders(array $filters = array(), int $limit = 50, int $offset = 0, string $sort = 'create_date', string $direction = 'DESC'): array {
      $this->install();
      $where = array('trash = 0');

      $query = trim((string)($filters['query'] ?? ''));
      if ($query !== '') {
         $like = $this->sql_like_value($query);
         $where[] = '(order_no LIKE ' . $like . ' OR customer_name LIKE ' . $like . ' OR customer_email LIKE ' . $like . ' OR channel_key LIKE ' . $like . ' OR payment_reference LIKE ' . $like . ')';
      }
      foreach (array('status', 'payment_status', 'shipping_status', 'channel_key') as $field) {
         $value = trim((string)($filters[$field] ?? ''));
         if ($value !== '') {
            $where[] = $field . ' = ' . $this->sql_value($value);
         }
      }

      $allowed_sort = array('create_date', 'order_no', 'status', 'payment_status', 'shipping_status', 'customer_name', 'total_gross', 'channel_key');
      if (!in_array($sort, $allowed_sort, true)) {
         $sort = 'create_date';
      }
      $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
      $max = $limit > 0 ? max(1, $limit) : 0;
      $rows = $this->db()->select($this->dd('shopOrder'), implode(' AND ', $where), '*', $sort . ' ' . $direction . ', id DESC', 'ASC', '', $max, max(0, $offset), 0);
      $rows = is_array($rows) ? $rows : array();
      foreach ($rows as &$row) {
         $row['items'] = $this->order_items((int)$row['id']);
      }
      unset($row);
      return $rows;
   }



   public function order_count(array $filters = array()): int {
      $this->install();
      $where = array('trash = 0');
      $query = trim((string)($filters['query'] ?? ''));
      if ($query !== '') {
         $like = $this->sql_like_value($query);
         $where[] = '(order_no LIKE ' . $like . ' OR customer_name LIKE ' . $like . ' OR customer_email LIKE ' . $like . ' OR channel_key LIKE ' . $like . ' OR payment_reference LIKE ' . $like . ')';
      }
      foreach (array('status', 'payment_status', 'shipping_status', 'channel_key') as $field) {
         $value = trim((string)($filters[$field] ?? ''));
         if ($value !== '') {
            $where[] = $field . ' = ' . $this->sql_value($value);
         }
      }
      return max(0, (int)$this->db()->count($this->dd('shopOrder'), implode(' AND ', $where)));
   }



   public function order_channel_keys(): array {
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



   public function update_order_admin(int $id, array $data): bool {
      $this->install();
      $allowed_status = array('new', 'payment_pending', 'paid', 'processing', 'shipped', 'done', 'cancelled');
      $allowed_payment = array('open', 'created', 'pending', 'completed', 'paid', 'failed', 'cancelled', 'refunded');
      $allowed_shipping = array('open', 'ready', 'shipped', 'delivered', 'returned');
      $before = $this->order_by_id($id);
      $status = (string)($data['status'] ?? 'new');
      $payment_status = (string)($data['payment_status'] ?? 'open');
      $shipping_status = (string)($data['shipping_status'] ?? ($before['shipping_status'] ?? 'open'));
      if (!in_array($status, $allowed_status, true)) $status = 'new';
      if (!in_array($payment_status, $allowed_payment, true)) $payment_status = 'open';
      if (!in_array($shipping_status, $allowed_shipping, true)) $shipping_status = 'open';
      $invoice_no = trim((string)($data['invoice_no'] ?? ($before['invoice_no'] ?? '')));
      $invoice_date = trim((string)($data['invoice_date'] ?? ($before['invoice_date'] ?? '')));
      if ($invoice_no === '' && in_array($status, array('paid', 'processing', 'shipped', 'done'), true)) {
         $invoice_no = $this->next_invoice_no();
         $invoice_date = date('Y-m-d');
      }
      $shipped_date = trim((string)($data['shipped_date'] ?? ($before['shipped_date'] ?? '')));
      if ($shipping_status === 'shipped' && $shipped_date === '') {
         $shipped_date = date('Y-m-d H:i:s');
      }
      $shipping_provider = trim((string)($data['shipping_provider'] ?? ''));
      $tracking_no = trim((string)($data['tracking_no'] ?? ''));
      $tracking_url = trim((string)($data['tracking_url'] ?? ''));
      if ($tracking_url === '' && $tracking_no !== '') {
         $tracking_url = $this->tracking_url_for_provider($shipping_provider, $tracking_no);
      }

      $ok = $this->db()->update($this->dd('shopOrder'), array(
         'update_date' => date('Y-m-d H:i:s'),
         'status' => $status,
         'payment_status' => $payment_status,
         'payment_reference' => (string)($data['payment_reference'] ?? ''),
         'invoice_no' => $invoice_no,
         'invoice_date' => $invoice_date,
         'shipping_status' => $shipping_status,
         'shipping_provider' => $shipping_provider,
         'tracking_no' => $tracking_no,
         'tracking_url' => $tracking_url,
         'shipped_date' => $shipped_date,
         'note' => (string)($data['note'] ?? ''),
      ), 'id = ' . (int)$id . ' AND trash = 0', 0);
      if (is_array($before)) {
         foreach (array(
            'status' => $status,
            'payment_status' => $payment_status,
            'shipping_status' => $shipping_status,
            'invoice_no' => $invoice_no,
            'tracking_no' => $tracking_no,
         ) as $field => $new_value) {
            $old_value = (string)($before[$field] ?? '');
            if ($old_value !== (string)$new_value) {
               $this->add_order_history($id, $field, $old_value, (string)$new_value, 'Admin-Aenderung');
            }
         }
         if ($status === 'cancelled' || in_array($payment_status, array('cancelled', 'refunded'), true) || $shipping_status === 'returned') {
            $fresh = $this->order_by_id($id);
            if (is_array($fresh)) {
               $this->release_stock_for_order($fresh, 'Bestand wurde durch Statusaenderung zurueckgebucht.');
            }
         }
      }
      return $ok !== 0 || $this->order_by_id($id) !== null;
   }

   private function tracking_url_for_provider(string $provider, string $tracking_no): string {
      $tracking_no = trim($tracking_no);
      if ($tracking_no === '') {
         return '';
      }
      $provider_key = strtolower(trim($provider));
      if (str_contains($provider_key, 'dhl')) {
         return 'https://www.dhl.de/de/privatkunden/pakete-empfangen/verfolgen.html?piececode=' . rawurlencode($tracking_no);
      }
      if (str_contains($provider_key, 'ups')) {
         return 'https://www.ups.com/track?tracknum=' . rawurlencode($tracking_no);
      }
      if (str_contains($provider_key, 'dpd')) {
         return 'https://tracking.dpd.de/status/de_DE/parcel/' . rawurlencode($tracking_no);
      }
      if (str_contains($provider_key, 'hermes')) {
         return 'https://www.myhermes.de/empfangen/sendungsverfolgung/?su=' . rawurlencode($tracking_no);
      }
      return '';
   }

   public function update_order_quick_action(int $id, string $action): array {
      $this->install();
      $order = $this->order_by_id($id);
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

      $ok = $this->update_order_admin($id, $data);
      if ($ok) {
         $this->add_order_history($id, 'quick_action', '', $action, $message);
         return array(true, $message);
      }
      return array(false, 'Bestellaktion konnte nicht gespeichert werden.');
   }

   public function delete_order(int $id): bool {
      $this->install();
      return $this->db()->update($this->dd('shopOrder'), array('trash' => 1, 'update_date' => date('Y-m-d H:i:s')), 'id = ' . (int)$id . ' AND trash = 0', 0) !== 0;
   }


   public function order_by_payment_reference(string $provider, string $reference): ?array {
      $this->install();
      $row = $this->db()->select1($this->dd('shopOrder'), 'payment_provider = ' . $this->sql_value($provider) . ' AND payment_reference = ' . $this->sql_value($reference) . ' AND trash = 0', '*', 0);
      if (!is_array($row) || (int)($row['id'] ?? 0) <= 0) return null;
      $row['items'] = $this->order_items((int)$row['id']);
      return $row;
   }



   public function order_items(int $order_id): array {
      $rows = $this->db()->select($this->dd('shopOrderItem'), 'order_id = ' . (int)$order_id . ' AND trash = 0', '*', 'id ASC', 'ASC', '', 0, 0, 0);
      return is_array($rows) ? $rows : array();
   }

   private function payment_processing_retry_seconds(): int {
      $seconds = (int)dbx()->get_cfg('dbxShop', 'payment_processing_retry_seconds', 300);
      return max(60, min(3600, $seconds));
   }

   /**
    * Ein processing-Claim darf erst nach Ablauf des Lease erneut beansprucht
    * werden. Provider-POSTs verwenden dabei weiterhin denselben Idempotency-Key.
    */
   private function is_stale_payment_processing(array $order, int $retry_seconds, ?int $now = null): bool {
      if (strtolower(trim((string)($order['payment_status'] ?? ''))) !== 'processing') {
         return false;
      }
      $updated_at = strtotime((string)($order['update_date'] ?? ''));
      if ($updated_at === false || $updated_at <= 0) {
         return false;
      }
      $now = $now ?? time();
      return $updated_at <= $now - max(60, $retry_seconds);
   }

   /**
    * Beansprucht einen Provider-Abschluss atomar.
    *
    * Nur der Request, der created/open/failed oder einen abgelaufenen
    * processing-Lease nach processing ueberfuehrt, darf den externen
    * Capture-/Complete-Aufruf ausfuehren.
    */
   public function claim_order_payment(int $order_id, string $provider, string $reference): bool {
      $this->install();
      $provider = trim($provider);
      $reference = trim($reference);
      if ($order_id <= 0 || $provider === '' || $reference === '') return false;

      $db = $this->db();
      if ($db->begin($this->dd('shopOrder')) !== 1) return false;
      try {
         $order = $db->select1($this->dd('shopOrder'), 'id = ' . $order_id . ' AND trash = 0', '*', 0);
         $old_status = strtolower(trim((string)($order['payment_status'] ?? '')));
         $stale_processing = is_array($order)
            && $this->is_stale_payment_processing($order, $this->payment_processing_retry_seconds());
         if (!is_array($order)
            || !hash_equals($provider, (string)($order['payment_provider'] ?? ''))
            || !hash_equals($reference, (string)($order['payment_reference'] ?? ''))
            || (!in_array($old_status, array('open', 'created', 'failed'), true) && !$stale_processing)) {
            $db->rollback($this->dd('shopOrder'));
            return false;
         }

         $where = 'id = ' . $order_id . ' AND trash = 0'
            . ' AND payment_provider = ' . $this->sql_value($provider)
            . ' AND payment_reference = ' . $this->sql_value($reference)
            . ' AND payment_status = ' . $this->sql_value($old_status);
         if ($stale_processing) {
            // Das alte Lease-Datum macht auch den Recovery-Claim zu einem
            // Compare-and-swap. Ein paralleler Recovery-Request verliert.
            $where .= ' AND update_date = ' . $this->sql_value((string)($order['update_date'] ?? ''));
         }
         if ($db->update($this->dd('shopOrder'), array(
            'payment_status' => 'processing',
            'status' => 'payment_pending',
            'update_date' => date('Y-m-d H:i:s'),
         ), $where, 0) !== 1 || (int)$db->_update_count !== 1) {
            $db->rollback($this->dd('shopOrder'));
            return false;
         }
         $claim_message = $stale_processing
            ? 'Verwaister Zahlungsabschluss wurde mit demselben Idempotency-Key erneut beansprucht.'
            : 'Zahlungsabschluss wurde atomar beansprucht.';
         if (!$this->add_order_history($order_id, 'payment', $old_status, 'processing', $claim_message)
            || $db->commit($this->dd('shopOrder')) !== 1) {
            throw new \RuntimeException('payment_claim_commit_failed');
         }
         return true;
      } catch (\Throwable $e) {
         $db->rollback($this->dd('shopOrder'));
         dbx()->debug('#Shop payment claim rollback order=(' . $order_id . ') error=(' . $e->getMessage() . ')');
         return false;
      }
   }

   /**
    * Aktualisiert den Zahlungsstatus idempotent und verhindert Downgrades
    * terminaler Zahlungen sowie Referenz-/Providerwechsel.
    */
   public function update_order_payment(int $order_id, string $provider, string $status, string $reference = '', array $payload = array()): bool {
      $this->install();
      $provider = trim($provider);
      $status = strtolower(trim($status));
      $reference = trim($reference);
      $allowed = array('open', 'created', 'processing', 'pending', 'completed', 'paid', 'failed', 'cancelled', 'refunded');
      if ($order_id <= 0 || $provider === '' || !in_array($status, $allowed, true)) return false;

      $db = $this->db();
      if ($db->begin($this->dd('shopOrder')) !== 1) return false;
      try {
         $order = $db->select1($this->dd('shopOrder'), 'id = ' . $order_id . ' AND trash = 0', '*', 0);
         if (!is_array($order)) {
            $db->rollback($this->dd('shopOrder'));
            return false;
         }
         $old_provider = trim((string)($order['payment_provider'] ?? ''));
         $old_reference = trim((string)($order['payment_reference'] ?? ''));
         $old_status = strtolower((string)($order['payment_status'] ?? 'open'));
         if (($old_provider !== '' && !hash_equals($old_provider, $provider))
            || ($old_reference !== '' && $reference !== '' && !hash_equals($old_reference, $reference))) {
            $db->rollback($this->dd('shopOrder'));
            return false;
         }
         if ($reference === '') $reference = $old_reference;

         if ((in_array($old_status, array('completed', 'paid'), true)
               && !in_array($status, array('completed', 'paid', 'refunded'), true))
            || ($old_status === 'refunded' && $status !== 'refunded')
            || ($old_status === 'cancelled' && !in_array($status, array('cancelled', 'refunded'), true))) {
            $db->rollback($this->dd('shopOrder'));
            return false;
         }

         if ($old_status === $status && $old_provider === $provider && $old_reference === $reference) {
            $db->rollback($this->dd('shopOrder'));
            return true;
         }

         $order_status = in_array($status, array('completed', 'paid'), true)
            ? 'paid'
            : (in_array($status, array('cancelled', 'refunded'), true) ? 'cancelled' : 'payment_pending');
         $where = 'id = ' . $order_id . ' AND trash = 0 AND payment_status = ' . $this->sql_value($old_status);
         if ($db->update($this->dd('shopOrder'), array(
            'update_date' => date('Y-m-d H:i:s'),
            'payment_provider' => $provider,
            'payment_status' => $status,
            'payment_reference' => $reference,
            'payment_payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'status' => $order_status,
         ), $where, 0) !== 1 || (int)$db->_update_count !== 1) {
            $db->rollback($this->dd('shopOrder'));
            return false;
         }
         if (!$this->add_order_history($order_id, 'payment', $old_status, $status, 'Zahlungsstatus wurde aktualisiert.')
            || $db->commit($this->dd('shopOrder')) !== 1) {
            throw new \RuntimeException('payment_update_commit_failed');
         }
      } catch (\Throwable $e) {
         $db->rollback($this->dd('shopOrder'));
         dbx()->debug('#Shop payment rollback order=(' . $order_id . ') error=(' . $e->getMessage() . ')');
         return false;
      }

      if (in_array($status, array('cancelled', 'refunded'), true)) {
         $fresh = $this->order_by_id($order_id);
         if (is_array($fresh)) {
            $this->release_stock_for_order($fresh, 'Bestand wurde durch Zahlungsstatus zurueckgebucht.');
         }
      }
      return true;
   }

   public function add_order_history(int $order_id, string $event_type, string $old_value = '', string $new_value = '', string $message = ''): bool {
      if ($order_id <= 0) {
         return false;
      }
      $order = $this->db()->select1($this->dd('shopOrder'), 'id = ' . (int)$order_id . ' AND trash = 0', 'owner,uid', 0);
      $owner = is_array($order) ? (int)($order['owner'] ?? 0) : 0;
      if ($owner <= 0 && is_array($order)) {
         $owner = (int)($order['uid'] ?? 0);
      }
      $data = array(
         'order_id' => $order_id,
         'event_type' => $event_type,
         'old_value' => $old_value,
         'new_value' => $new_value,
         'message' => $message,
      );
      if ($owner > 0) {
         $data['owner'] = $owner;
      }
      return $this->db()->insert($this->dd('shopOrderHistory'), $data, 0) === 1;
   }

   public function order_history(int $order_id): array {
      $rows = $this->db()->select($this->dd('shopOrderHistory'), 'order_id = ' . (int)$order_id . ' AND trash = 0', '*', 'create_date DESC, id DESC', 'ASC', '', 0, 0, 0);
      return is_array($rows) ? $rows : array();
   }

   public function has_order_history_event(int $order_id, string $event_type, string $new_value = ''): bool {
      if ($order_id <= 0 || trim($event_type) === '') return false;
      $where = 'order_id = ' . $order_id
         . ' AND event_type = ' . $this->sql_value(trim($event_type))
         . ' AND trash = 0';
      if ($new_value !== '') {
         $where .= ' AND new_value = ' . $this->sql_value($new_value);
      }
      return $this->db()->count($this->dd('shopOrderHistory'), $where) > 0;
   }

   public function dashboard_stats(): array {
      $this->install();
      $orders_open = (int)$this->db()->count($this->dd('shopOrder'), "trash = 0 AND status IN ('new','payment_pending','paid','processing')");
      $payments_open = (int)$this->db()->count($this->dd('shopOrder'), "trash = 0 AND payment_status IN ('open','created','pending')");
      $ship_open = (int)$this->db()->count($this->dd('shopOrder'), "trash = 0 AND shipping_status IN ('open','ready')");
      $withdrawals_open = (int)$this->db()->count($this->dd('shopWithdrawal'), "trash = 0 AND status IN ('new','processing')");
      $stock_low = 0;
      if ($this->stock_enabled()) {
         $stock_low = (int)$this->db()->count($this->dd('shopProduct'), "trash = 0 AND active = 1 AND product_type = 'physical' AND stock <= 3");
      }
      return array(
         'orders_open' => $orders_open,
         'payments_open' => $payments_open,
         'shipping_open' => $ship_open,
         'withdrawals_open' => $withdrawals_open,
         'stock_low' => $stock_low,
         'products_active' => (int)$this->db()->count($this->dd('shopProduct'), 'trash = 0 AND active = 1'),
      );
   }
}
