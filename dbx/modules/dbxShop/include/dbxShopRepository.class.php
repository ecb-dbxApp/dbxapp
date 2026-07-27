<?php
namespace dbx\dbxShop;

class dbxShopRepository {
   /**
    * Request-lokaler Cache fuer kleine, oft wiederverwendete Referenzlisten.
    *
    * Bewusst kein globaler dbxDB-Result-Cache: damit bleiben Transaktionen,
    * Berechtigungen und Invalidierung zentral unveraendert.
    */
   private array $requestCache = array();

   private function remember(string $key, callable $loader): array {
      if (!array_key_exists($key, $this->requestCache)) {
         $value = $loader();
         $this->requestCache[$key] = is_array($value) ? $value : array();
      }
      return $this->requestCache[$key];
   }

   private function clearRequestCache(): void {
      $this->requestCache = array();
   }

   private function db() {
      return dbx()->get_system_obj('dbxDB');
   }

   private function dd(string $name): string {
      return 'dbxShop|' . $name;
   }

   private function sqlValue($value): string {
      if ($value === null) {
         return "''";
      }
      return "'" . str_replace("'", "''", (string)$value) . "'";
   }

   private function sqlLikeValue(string $value): string {
      return $this->sqlValue('%' . $value . '%');
   }

   private function syncDdToDb(string $dd): bool {
      $oDD = dbx()->get_system_obj('dbxDD');
      $oDD->sync_dd_to_db('dbxShop', $dd, 'reset');
      for ($i = 0; $i < 40; $i++) {
         $state = $oDD->sync_dd_to_db('dbxShop', $dd, 'apply');
         $status = (string)($state['status'] ?? '');
         if ($status === 'finished') {
            return true;
         }
         if (in_array($status, array('error', 'cancelled'), true)) {
            return false;
         }
      }
      return false;
   }

   private function syncShopSchemaFromDd(): bool {
      static $done = false;
      if ($done) {
         return false;
      }

      $version = 'shop-dd-20260713-2';
      if ((string)dbx()->get_config('dbxShop', 'schema_sync_version', '') === $version) {
         $done = true;
         return false;
      }

      foreach (array(
         'shopProductGroup',
         'shopChannel',
         'shopProduct',
         'shopProductImage',
         'shopAttributeDefinition',
         'shopProductAttributeValue',
         'shopProductGroupMap',
         'shopProductChannel',
         'shopShippingGroup',
         'shopProductShippingGroupMap',
         'shopChannelGroup',
         'shopChannelGroupChannel',
         'shopProductChannelGroupMap',
         'shopOrder',
         'shopOrderItem',
         'shopOrderHistory',
         'shopWithdrawal',
      ) as $dd) {
         if (!$this->syncDdToDb($dd)) {
            throw new \RuntimeException('dbxShop-DD konnte nicht mit der Datenbank synchronisiert werden: ' . $dd);
         }
      }

      $cfg = dbx()->get_config('dbxShop', '', array());
      $cfg = is_array($cfg) ? $cfg : array();
      $cfg['schema_sync_version'] = $version;
      dbx()->set_config('dbxShop', $cfg);
      $done = true;
      return true;
   }

   /**
    * Fuehrt die explizite Schema- und Defaultpflege aus.
    *
    * Alle Repository-Methoden duerfen install() weiterhin einheitlich
    * aufrufen. Ohne $maintenance ist die Methode absichtlich schreibfrei.
    * Nur die geschuetzte Admin-Installation ruft install(true) auf.
    */
   public function install(bool $maintenance = false): void {
      static $maintenanceDone = false;
      if (!$maintenance || $maintenanceDone) {
         return;
      }

      $this->syncShopSchemaFromDd();
      $this->syncChannelDefaults();
      $this->syncPrimaryProductGroups();
      $this->syncSingleGroupImages();
      $this->clearRequestCache();
      $maintenanceDone = true;
   }

   private function syncPrimaryProductGroups(): void {
      $rows = $this->db()->select($this->dd('shopProduct'), 'trash = 0 AND (product_group_id IS NULL OR product_group_id <= 0)', 'id', '', 'ASC', '', 0, 0, 0);
      foreach ((is_array($rows) ? $rows : array()) as $row) {
         $productId = (int)($row['id'] ?? 0);
         if ($productId <= 0) continue;
         $maps = $this->db()->select($this->dd('shopProductGroupMap'), 'product_id = ' . $productId, '*', 'is_primary DESC', 'ASC', '', 0, 1, 0);
         $map = is_array($maps) && isset($maps[0]) ? $maps[0] : array();
         $groupId = (int)($map['group_id'] ?? 0);
         if ($groupId > 0) {
            $this->db()->update($this->dd('shopProduct'), array('product_group_id' => $groupId), 'id = ' . $productId, 0);
         }
      }
   }

   private function syncSingleGroupImages(): void {
      $groups = $this->db()->select($this->dd('shopProductGroup'), 'trash = 0', 'id', '', 'ASC', '', 0, 0, 0);
      foreach ((is_array($groups) ? $groups : array()) as $group) {
         $groupId = (int)($group['id'] ?? 0);
         if ($groupId <= 0) continue;
         $rows = $this->db()->select(
            $this->dd('shopProductImage'),
            'trash = 0 AND active = 1 AND product_id = 0 AND group_id = ' . $groupId,
            '*',
            'is_primary DESC, sorter ASC, title ASC',
            'ASC',
            '',
            0,
            0,
            0
         );
         $keep = 0;
         foreach ((is_array($rows) ? $rows : array()) as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) continue;
            if ($keep <= 0) {
               $keep = $id;
               $this->db()->update($this->dd('shopProductImage'), array('is_primary' => 1, 'sorter' => 10), 'id = ' . $id, 0);
               continue;
            }
            $this->db()->update($this->dd('shopProductImage'), array('active' => 0, 'trash' => 1), 'id = ' . $id, 0);
         }
      }
   }

   private function syncChannelDefaults(): void {
      $channels = array(
         array('shop', 'Shop', 'Eigener Shop ohne externe API.', 'shop', 'internal', 1, 0, '', '', '', '', 10),
         array('amazon', 'Amazon', 'Amazon Marketplace Integration fuer Artikel-Export und spaeteren Order-Import.', 'amazon', 'api', 1, 1, 'https://sellingpartnerapi-eu.amazon.com', 'A1PA6795UKMFR9', 'ORDER_CHANGE', "Listings Items\nOrders\nNotifications", 20),
         array('ebay', 'eBay', 'eBay Marketplace Integration fuer Listings und Bestellrueckmeldungen.', 'ebay', 'api', 1, 1, 'https://api.ebay.com', 'EBAY_DE', '', "https://api.ebay.com/oauth/api_scope/sell.inventory\nhttps://api.ebay.com/oauth/api_scope/sell.fulfillment\nhttps://api.ebay.com/oauth/api_scope/commerce.notification.subscription", 30),
         array('kleinanzeigen', 'Kleinanzeigen', 'Kleinanzeigen ist als Channel vorbereitet. Eine allgemein frei nutzbare offizielle Anzeigen-/Order-API ist nicht hinterlegt; nutzen Sie hier nur vertraglich freigegebene Schnittstellen oder manuelle Pflege.', 'kleinanzeigen', 'manual', 1, 0, '', '', '', '', 40),
         array('mobile', 'mobile.de', 'mobile.de Channel fuer Fahrzeug- oder Angebotsdaten ueber Seller API und Lead API.', 'mobile', 'api', 1, 1, 'https://services.mobile.de/seller-api', '', 'lead-api', "seller-api\nbasic-auth\nlead-api", 50),
      );
      foreach ($channels as $c) {
         $existing = $this->db()->select1($this->dd('shopChannel'), 'channel_key = ' . $this->sqlValue($c[0]), '*', 0);
         $existing = is_array($existing) ? $existing : array();
         $values = array(
            'channel_key' => $c[0],
            'title' => trim((string)($existing['title'] ?? '')) !== '' ? (string)$existing['title'] : $c[1],
            'description' => trim((string)($existing['description'] ?? '')) !== '' ? (string)$existing['description'] : $c[2],
            'platform_type' => trim((string)($existing['platform_type'] ?? '')) !== '' && (string)($existing['platform_type'] ?? '') !== 'custom' ? (string)$existing['platform_type'] : $c[3],
            'connection_mode' => trim((string)($existing['connection_mode'] ?? '')) !== '' && (string)($existing['connection_mode'] ?? '') !== 'manual' ? (string)$existing['connection_mode'] : $c[4],
            'export_enabled' => array_key_exists('export_enabled', $existing) ? (int)$existing['export_enabled'] : $c[5],
            'order_import_enabled' => array_key_exists('order_import_enabled', $existing) ? (int)$existing['order_import_enabled'] : $c[6],
            'api_base_url' => trim((string)($existing['api_base_url'] ?? '')) !== '' ? (string)$existing['api_base_url'] : $c[7],
            'marketplace_id' => trim((string)($existing['marketplace_id'] ?? '')) !== '' ? (string)$existing['marketplace_id'] : $c[8],
            'notification_topic' => trim((string)($existing['notification_topic'] ?? '')) !== '' ? (string)$existing['notification_topic'] : $c[9],
            'api_scope' => trim((string)($existing['api_scope'] ?? '')) !== '' ? (string)$existing['api_scope'] : $c[10],
            'active' => array_key_exists('active', $existing) ? (int)$existing['active'] : 1,
            'sorter' => array_key_exists('sorter', $existing) ? (int)$existing['sorter'] : $c[11],
         );
         $this->db()->save($this->dd('shopChannel'), $values, 'channel_key = ' . $this->sqlValue($c[0]), 0);
      }
      $oldEbayScopes = 'https://api.ebay.com/oauth/api_scope/sell.inventory https://api.ebay.com/oauth/api_scope/sell.fulfillment https://api.ebay.com/oauth/api_scope/commerce.notification.subscription';
      $newEbayScopes = "https://api.ebay.com/oauth/api_scope/sell.inventory\nhttps://api.ebay.com/oauth/api_scope/sell.fulfillment\nhttps://api.ebay.com/oauth/api_scope/commerce.notification.subscription";
      $this->db()->update(
         $this->dd('shopChannel'),
         array('api_scope' => $newEbayScopes),
         'channel_key = ' . $this->sqlValue('ebay') . ' AND api_scope = ' . $this->sqlValue($oldEbayScopes),
         0
      );
   }

   private function seedDemoProductsWithDbxDb(): void {
      if ($this->db()->count($this->dd('shopProduct'), 'trash = 0') > 0) {
         return;
      }

      $this->updateProductGroup(0, array(
         'group_key' => 'software',
         'title' => 'Software',
         'description' => 'Digitale dbXapp Pakete und Erweiterungen.',
         'tax_class' => 'mwst1',
         'display_variant' => 'gallery_grid',
         'card_template' => 'product-card-default',
         'detail_template' => 'product-detail-default',
         'gallery_template' => 'image-gallery',
         'gallery_visible_count' => 3,
         'gallery_image_size' => 'original',
         'gallery_lightbox_width' => '100vw',
         'gallery_overflow' => 'grid',
         'gallery_click' => 'lightbox',
         'attribute_notes' => 'Lizenz, Paketumfang, CMS-Funktionen und KI-Funktionen.',
         'active' => 1,
         'sorter' => 10,
      ));
      $this->updateProductGroup(0, array(
         'group_key' => 'service',
         'title' => 'Dienstleistungen',
         'description' => 'Installation, Wartung und Schulung.',
         'tax_class' => 'mwst1',
         'display_variant' => 'gallery_slider',
         'card_template' => 'product-card-default',
         'detail_template' => 'product-detail-default',
         'gallery_template' => 'image-gallery',
         'gallery_visible_count' => 1,
         'gallery_image_size' => 'original',
         'gallery_lightbox_width' => '100vw',
         'gallery_overflow' => 'slider',
         'gallery_click' => 'lightbox',
         'attribute_notes' => 'Leistungsumfang, Dauer, Termin und Uebergabe.',
         'active' => 1,
         'sorter' => 20,
      ));

      $this->updateShippingGroup(0, array(
         'group_key' => 'digital-free',
         'title' => 'Digital / kein Versand',
         'description' => 'Digitale Lieferung ohne Versandkosten.',
         'shipping_way' => 'Download / Freischaltung',
         'delivery_time' => 'Sofort nach Freischaltung',
         'shipping_gross' => 0,
         'free_from_gross' => -1,
         'active' => 1,
         'sorter' => 10,
      ));
      $this->updateShippingGroup(0, array(
         'group_key' => 'service-remote',
         'title' => 'Service / Termin',
         'description' => 'Terminleistung ohne Paketversand.',
         'shipping_way' => 'Termin / Remote / vor Ort nach Absprache',
         'delivery_time' => 'Nach Terminvereinbarung',
         'shipping_gross' => 0,
         'free_from_gross' => -1,
         'active' => 1,
         'sorter' => 20,
      ));

      $this->updateChannelGroup(0, array(
         'group_key' => 'software-shop',
         'title' => 'Software Shop-Artikel',
         'description' => 'Softwarepakete fuer Shop und Amazon.',
         'active' => 1,
         'sorter' => 20,
      ), array('shop', 'amazon'));
      $this->updateChannelGroup(0, array(
         'group_key' => 'service-local',
         'title' => 'Service lokal',
         'description' => 'Dienstleistungen fuer Shop und Kleinanzeigen.',
         'active' => 1,
         'sorter' => 30,
      ), array('shop', 'kleinanzeigen'));

      $products = array(
         array('DBX-DEMO-START', 'dbxapp-demo-paket', 'dbXapp Demo Paket', 'Software', 'digital', 'software', 'digital-free', 'software-shop', 'Kleine Website mit CMS, Medienverwaltung und direkter Frontend-Bearbeitung.', 'Testartikel fuer den Einstieg: Content-Seiten, Hero, Gallery und Medienbrowser sind vorbereitet.', 99.00, 0, 10, 'Demo', 'bi-window-sidebar', array('shop', 'amazon')),
         array('DBX-INSTALLATION', 'dbxapp-installation', 'Installation', 'Dienstleistung', 'service', 'service', 'service-remote', 'service-local', 'Einrichtung von dbXapp auf Hosting oder eigenem Server.', 'Installation, Grundkonfiguration, erste Systempruefung und Uebergabe der lauffaehigen Umgebung.', 249.00, 0, 40, 'Service', 'bi-tools', array('shop', 'kleinanzeigen')),
      );
      foreach ($products as $p) {
         $this->db()->save(
            $this->dd('shopProduct'),
            array(
               'sku' => $p[0],
               'slug' => $p[1],
               'title' => $p[2],
               'category' => $p[3],
               'product_group_id' => $this->groupIdByKey($p[5]),
               'product_type' => $p[4],
               'summary' => $p[8],
               'description' => $p[9],
               'price_gross' => $p[10],
               'currency' => 'EUR',
               'tax_mode' => 'group',
               'tax_rate' => -1,
               'shipping_mode' => 'group',
               'shipping_gross' => -1,
               'stock' => $p[11],
               'active' => 1,
               'sorter' => $p[12],
               'badge' => $p[13],
               'image_icon' => $p[14],
               'logo_variant' => '',
            ),
            'sku = ' . $this->sqlValue($p[0]),
            0
         );
         $product = $this->productBySku($p[0], false);
         $productId = (int)($product['id'] ?? 0);
         if ($productId <= 0) continue;
         $groupId = $this->groupIdByKey($p[5]);
         if ($groupId > 0) {
            $this->db()->save($this->dd('shopProductGroupMap'), array('product_id' => $productId, 'group_id' => $groupId, 'is_primary' => 1), 'product_id = ' . $productId . ' AND group_id = ' . $groupId, 0);
         }
         $shippingGroupId = $this->shippingGroupIdByKey($p[6]);
         if ($shippingGroupId > 0) {
            $this->db()->save($this->dd('shopProductShippingGroupMap'), array('product_id' => $productId, 'shipping_group_id' => $shippingGroupId, 'is_primary' => 1), 'product_id = ' . $productId . ' AND shipping_group_id = ' . $shippingGroupId, 0);
         }
         $channelGroupId = $this->channelGroupIdByKey($p[7]);
         if ($channelGroupId > 0) {
            $this->db()->save($this->dd('shopProductChannelGroupMap'), array('product_id' => $productId, 'channel_group_id' => $channelGroupId, 'is_primary' => 1), 'product_id = ' . $productId . ' AND channel_group_id = ' . $channelGroupId, 0);
         }
         foreach ($p[15] as $channelKey) {
            $this->db()->save($this->dd('shopProductChannel'), array('product_id' => $productId, 'channel_key' => $channelKey, 'active' => 1, 'channel_sku' => $p[0], 'price_gross' => -1, 'shipping_gross' => -1), 'product_id = ' . $productId . ' AND channel_key = ' . $this->sqlValue($channelKey), 0);
         }
      }

      $softwareGroupId = $this->groupIdByKey('software');
      if ($softwareGroupId > 0) {
         $this->saveImage(0, $softwareGroupId, 'files/shop/img/software-dashboard.svg', 'Software Dashboard', 'dbXapp Software Dashboard', 1, 10);
      }
      $serviceGroupId = $this->groupIdByKey('service');
      if ($serviceGroupId > 0) {
         $this->saveImage(0, $serviceGroupId, 'files/shop/img/service-support.svg', 'Service und Schulung', 'dbXapp Installation, Wartung und Schulung', 1, 10);
      }
   }

   public function seedDemoProducts(): void {
      $this->install(true);
      $this->seedDemoProductsWithDbxDb();
      $this->clearRequestCache();
      return;
   }

   /** Schneller Seed-Check ohne Produktdekoration und N+1-Abfragen. */
   public function needsDemoSeed(): bool {
      $this->install();
      return $this->db()->count($this->dd('shopProduct'), 'trash = 0') === 0
         || $this->db()->count($this->dd('shopShippingGroup'), 'trash = 0') === 0
         || $this->db()->count($this->dd('shopChannelGroup'), 'trash = 0') === 0
         || $this->db()->count($this->dd('shopProductImage'), 'trash = 0') === 0;
   }

   private function groupIdByKey(string $key): int {
      $row = $this->db()->select1($this->dd('shopProductGroup'), 'group_key = ' . $this->sqlValue($key), 'id', 0);
      return (int)($row['id'] ?? 0);
   }

   private function shippingGroupIdByKey(string $key): int {
      $row = $this->db()->select1($this->dd('shopShippingGroup'), 'group_key = ' . $this->sqlValue($key), 'id', 0);
      return (int)($row['id'] ?? 0);
   }

   private function channelGroupIdByKey(string $key): int {
      $row = $this->db()->select1($this->dd('shopChannelGroup'), 'group_key = ' . $this->sqlValue($key), 'id', 0);
      return (int)($row['id'] ?? 0);
   }

   private function valueNum(string $value): ?float {
      $clean = str_replace(',', '.', trim($value));
      return is_numeric($clean) ? (float)$clean : null;
   }

   public function attributeDefinitionsForGroup(int $groupId, bool $activeOnly = true): array {
      $this->install();
      $where = 'trash = 0 AND group_id = ' . (int)$groupId;
      if ($activeOnly) {
         $where .= ' AND active = 1';
      }
      $rows = $this->db()->select($this->dd('shopAttributeDefinition'), $where, '*', 'sorter ASC, title ASC', 'ASC', '', 0, 0, 0);
      return is_array($rows) ? $rows : array();
   }

   public function attributeDefinitionsForProduct(int $productId, bool $activeOnly = true): array {
      $defs = array();
      $seen = array();
      foreach ($this->groupsForProduct($productId) as $group) {
         foreach ($this->attributeDefinitionsForGroup((int)($group['id'] ?? 0), $activeOnly) as $definition) {
            $id = (int)($definition['id'] ?? 0);
            if ($id <= 0 || isset($seen[$id])) continue;
            $seen[$id] = true;
            $defs[] = $definition;
         }
      }
      return $defs;
   }

   public function attributesForProduct(int $productId): array {
      $this->install();
      $values = $this->db()->select($this->dd('shopProductAttributeValue'), 'product_id = ' . (int)$productId . ' AND trash = 0', '*', '', 'ASC', '', 0, 0, 0);
      $valueByAttribute = array();
      foreach ((is_array($values) ? $values : array()) as $value) {
         $valueByAttribute[(int)($value['attribute_id'] ?? 0)] = $value;
      }

      $rows = array();
      foreach ($this->attributeDefinitionsForProduct($productId, true) as $definition) {
         $attributeId = (int)($definition['id'] ?? 0);
         if ($attributeId <= 0) {
            continue;
         }
         $value = $valueByAttribute[$attributeId] ?? array();
         $definition['value_text'] = $value['value_text'] ?? '';
         $definition['value_num'] = $value['value_num'] ?? '';
         $definition['unit_override'] = $value['unit_override'] ?? '';
         $definition['value_active'] = $value['active'] ?? 0;
         $rows[] = $definition;
      }
      foreach ($rows as &$row) {
         $value = trim((string)($row['value_text'] ?? ''));
         $unit = trim((string)($row['unit_override'] ?? '')) ?: trim((string)($row['unit'] ?? ''));
         $row['display_value'] = $value !== '' && $unit !== '' ? $value . ' ' . $unit : $value;
      }
      unset($row);
      return $rows;
   }

   public function allAttributeDefinitions(): array {
      $this->install();
      return $this->remember('attribute_definitions', function(): array {
      $groups = $this->groups();
      $groupById = array();
      foreach ($groups as $group) {
         $groupById[(int)($group['id'] ?? 0)] = $group;
      }
      $defs = $this->db()->select($this->dd('shopAttributeDefinition'), 'trash = 0', '*', '', 'ASC', '', 0, 0, 0);
      $defs = is_array($defs) ? $defs : array();
      foreach ($defs as &$def) {
         $group = $groupById[(int)($def['group_id'] ?? 0)] ?? array();
         $def['group_title'] = (string)($group['title'] ?? '');
         $def['group_key'] = (string)($group['group_key'] ?? '');
         $def['_group_sorter'] = (int)($group['sorter'] ?? 9999);
      }
      unset($def);
      usort($defs, fn($a, $b) => ((int)($a['_group_sorter'] ?? 9999) <=> (int)($b['_group_sorter'] ?? 9999))
         ?: ((int)($a['sorter'] ?? 0) <=> (int)($b['sorter'] ?? 0))
         ?: strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? '')));
      foreach ($defs as &$def) {
         unset($def['_group_sorter']);
      }
      unset($def);
      return $defs;
      });
   }

   public function attributeFilterDefinitions(): array {
      $this->install();
      return $this->remember('attribute_filter_definitions', function(): array {
      $defs = array_values(array_filter(
         $this->allAttributeDefinitions(),
         fn($def) => (int)($def['active'] ?? 0) === 1 && (int)($def['filterable'] ?? 0) === 1
      ));
      $products = $this->db()->select($this->dd('shopProduct'), 'trash = 0 AND active = 1', 'id', '', 'ASC', '', 0, 0, 0);
      $activeProducts = array();
      foreach ((is_array($products) ? $products : array()) as $product) {
         $activeProducts[(int)($product['id'] ?? 0)] = true;
      }
      $definitionIds = array_values(array_filter(array_map(
         static fn($def) => (int)($def['id'] ?? 0),
         $defs
      )));
      $valuesByDefinition = array();
      if ($definitionIds && $activeProducts) {
         $rows = $this->db()->select(
            $this->dd('shopProductAttributeValue'),
            'attribute_id IN (' . implode(',', $definitionIds) . ')'
               . ' AND product_id IN (' . implode(',', array_keys($activeProducts)) . ')'
               . ' AND trash = 0 AND active = 1',
            'attribute_id,product_id,value_text',
            'value_text',
            'ASC',
            '',
            0,
            0,
            0
         );
         foreach ((is_array($rows) ? $rows : array()) as $row) {
            $attributeId = (int)($row['attribute_id'] ?? 0);
            $value = trim((string)($row['value_text'] ?? ''));
            if ($attributeId > 0 && $value !== '') {
               $valuesByDefinition[$attributeId][$value] = $value;
            }
         }
      }
      foreach ($defs as &$def) {
         $values = array_values($valuesByDefinition[(int)($def['id'] ?? 0)] ?? array());
         natcasesort($values);
         $values = array_values($values);
         if ($values === array() && trim((string)($def['options'] ?? '')) !== '') {
            $values = preg_split('~[|;\r\n]+~', (string)$def['options']) ?: array();
            $values = array_values(array_filter(array_map('trim', $values), fn($v) => $v !== ''));
         }
         $def['values'] = $values;
      }
      unset($def);
      return $defs;
      });
   }

   public function saveAttributeDefinition(array $data): void {
      $this->install();
      $id = (int)($data['id'] ?? 0);
      $groupId = (int)($data['group_id'] ?? 0);
      $key = strtolower(trim(preg_replace('~[^a-z0-9_\\-]+~i', '_', (string)($data['attr_key'] ?? ''))));
      $title = trim((string)($data['title'] ?? ''));
      if ($groupId <= 0 || $key === '' || $title === '') {
         return;
      }
      $type = (string)($data['input_type'] ?? 'text');
      if (!in_array($type, array('text', 'select', 'number'), true)) {
         $type = 'text';
      }
      $values = array(
         'group_id' => $groupId,
         'attr_key' => $key,
         'title' => $title,
         'input_type' => $type,
         'unit' => trim((string)($data['unit'] ?? '')),
         'options' => trim((string)($data['options'] ?? '')),
         'required' => !empty($data['required']) ? 1 : 0,
         'filterable' => !empty($data['filterable']) ? 1 : 0,
         'comparable' => !empty($data['comparable']) ? 1 : 0,
         'active' => !empty($data['active']) ? 1 : 0,
         'sorter' => (int)($data['sorter'] ?? 100),
      );
      if ($id > 0) {
         $this->db()->update($this->dd('shopAttributeDefinition'), $values, 'id = ' . (int)$id, 0);
         $this->clearRequestCache();
         return;
      }
      $this->db()->save(
         $this->dd('shopAttributeDefinition'),
         $values,
         'group_id = ' . (int)$groupId . ' AND attr_key = ' . $this->sqlValue($key),
         0
      );
      $this->clearRequestCache();
   }

   public function saveProductAttributeValue(int $productId, int $attributeId, string $value): void {
      $this->install();
      if ($productId <= 0 || $attributeId <= 0) {
         return;
      }
      $value = trim($value);
      $num = $this->valueNum($value);
      $this->db()->save(
         $this->dd('shopProductAttributeValue'),
         array(
            'product_id' => $productId,
            'attribute_id' => $attributeId,
            'value_text' => $value,
            'value_num' => $num,
            'active' => $value !== '' ? 1 : 0,
         ),
         'product_id = ' . (int)$productId . ' AND attribute_id = ' . (int)$attributeId,
         0
      );
      $this->clearRequestCache();
   }

   public function saveProductAttributeValues(int $productId, array $values): void {
      foreach ($values as $attributeId => $value) {
         $this->saveProductAttributeValue($productId, (int)$attributeId, (string)$value);
      }
   }

   /** Gruppiert geladene Zeilen nach einem ganzzahligen Fremdschluessel. */
   private function rowsByIntKey(array $rows, string $field): array {
      $indexed = array();
      foreach ($rows as $row) {
         $key = (int)($row[$field] ?? 0);
         if ($key > 0) {
            $indexed[$key][] = $row;
         }
      }
      return $indexed;
   }

   /** Indexiert geladene Stammdaten nach ihrer ID. */
   private function rowsById(array $rows): array {
      $indexed = array();
      foreach ($rows as $row) {
         $id = (int)($row['id'] ?? 0);
         if ($id > 0) {
            $indexed[$id] = $row;
         }
      }
      return $indexed;
   }

   /**
    * Loest eine bereits gebuendelt geladene Gruppenzuordnung auf.
    *
    * Alle drei Zuordnungsarten verwenden damit dieselbe stabile Sortierung.
    */
   private function mappedGroupRows(array $maps, array $groupById, string $groupIdField): array {
      $rows = array();
      foreach ($maps as $map) {
         $groupId = (int)($map[$groupIdField] ?? 0);
         if (!isset($groupById[$groupId])) continue;
         $row = $groupById[$groupId];
         $row['_is_primary'] = (int)($map['is_primary'] ?? 0);
         $rows[] = $row;
      }
      usort($rows, fn($a, $b) => ((int)($b['_is_primary'] ?? 0) <=> (int)($a['_is_primary'] ?? 0))
         ?: ((int)($a['sorter'] ?? 0) <=> (int)($b['sorter'] ?? 0))
         ?: strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? '')));
      foreach ($rows as &$row) {
         unset($row['_is_primary']);
      }
      unset($row);
      return $rows;
   }

   /**
    * Reichert eine Produktmenge mit einer kurzlebigen, gebuendelten Datensicht an.
    *
    * Die Sicht gilt nur fuer diesen Methodenaufruf. Dadurch bleiben Daten nach
    * Schreibzugriffen immer aktuell und es ist keine Cache-Invalidierung noetig.
    */
   private function decorateProducts(array $rows): array {
      if ($rows === array()) return array();

      $productIds = array_values(array_unique(array_filter(array_map(
         static fn($row) => (int)($row['id'] ?? 0),
         $rows
      ))));
      if ($productIds === array()) return array();
      $productIdSql = implode(',', array_map('intval', $productIds));

      // Stammdaten und Zuordnungen werden je Operation genau einmal geladen.
      $productGroups = $this->db()->select(
         $this->dd('shopProductGroup'),
         'trash = 0',
         '*',
         '',
         'ASC',
         '',
         0,
         0,
         0
      );
      $productGroupById = $this->rowsById(is_array($productGroups) ? $productGroups : array());
      $productGroupMaps = $this->db()->select(
         $this->dd('shopProductGroupMap'),
         'product_id IN (' . $productIdSql . ')',
         '*',
         '',
         'ASC',
         '',
         0,
         0,
         0
      );
      $productGroupMapsByProduct = $this->rowsByIntKey(
         is_array($productGroupMaps) ? $productGroupMaps : array(),
         'product_id'
      );

      $shippingGroups = $this->db()->select(
         $this->dd('shopShippingGroup'),
         'trash = 0',
         '*',
         '',
         'ASC',
         '',
         0,
         0,
         0
      );
      $shippingGroupById = $this->rowsById(is_array($shippingGroups) ? $shippingGroups : array());
      $shippingMaps = $this->db()->select(
         $this->dd('shopProductShippingGroupMap'),
         'product_id IN (' . $productIdSql . ')',
         '*',
         '',
         'ASC',
         '',
         0,
         0,
         0
      );
      $shippingMapsByProduct = $this->rowsByIntKey(
         is_array($shippingMaps) ? $shippingMaps : array(),
         'product_id'
      );

      $channelGroups = $this->db()->select(
         $this->dd('shopChannelGroup'),
         'trash = 0',
         '*',
         '',
         'ASC',
         '',
         0,
         0,
         0
      );
      $channelGroupById = $this->rowsById(is_array($channelGroups) ? $channelGroups : array());
      $channelGroupMaps = $this->db()->select(
         $this->dd('shopProductChannelGroupMap'),
         'product_id IN (' . $productIdSql . ')',
         '*',
         '',
         'ASC',
         '',
         0,
         0,
         0
      );
      $channelGroupMaps = is_array($channelGroupMaps) ? $channelGroupMaps : array();
      $channelGroupMapsByProduct = $this->rowsByIntKey($channelGroupMaps, 'product_id');

      $channelIndex = array();
      foreach ($this->channels() as $channel) {
         $key = (string)($channel['channel_key'] ?? '');
         if ($key !== '') $channelIndex[$key] = $channel;
      }
      $directChannels = $this->db()->select(
         $this->dd('shopProductChannel'),
         'product_id IN (' . $productIdSql . ')',
         '*',
         '',
         'ASC',
         '',
         0,
         0,
         0
      );
      $directChannelsByProduct = $this->rowsByIntKey(
         is_array($directChannels) ? $directChannels : array(),
         'product_id'
      );
      $mappedChannelGroupIds = array_values(array_unique(array_filter(array_map(
         static fn($map) => (int)($map['channel_group_id'] ?? 0),
         $channelGroupMaps
      ))));
      $channelGroupChannelsByGroup = array();
      if ($mappedChannelGroupIds !== array()) {
         $channelGroupChannels = $this->db()->select(
            $this->dd('shopChannelGroupChannel'),
            'channel_group_id IN (' . implode(',', array_map('intval', $mappedChannelGroupIds)) . ') AND active = 1',
            '*',
            '',
            'ASC',
            '',
            0,
            0,
            0
         );
         $channelGroupChannelsByGroup = $this->rowsByIntKey(
            is_array($channelGroupChannels) ? $channelGroupChannels : array(),
            'channel_group_id'
         );
      }

      $definitions = $this->db()->select(
         $this->dd('shopAttributeDefinition'),
         'active = 1 AND trash = 0',
         '*',
         'sorter ASC, title ASC',
         'ASC',
         '',
         0,
         0,
         0
      );
      $definitionsByGroup = $this->rowsByIntKey(
         is_array($definitions) ? $definitions : array(),
         'group_id'
      );
      $attributeValues = $this->db()->select(
         $this->dd('shopProductAttributeValue'),
         'product_id IN (' . $productIdSql . ') AND trash = 0',
         '*',
         '',
         'ASC',
         '',
         0,
         0,
         0
      );
      $attributeValuesByProduct = array();
      foreach ((is_array($attributeValues) ? $attributeValues : array()) as $value) {
         $productId = (int)($value['product_id'] ?? 0);
         $attributeId = (int)($value['attribute_id'] ?? 0);
         if ($productId > 0 && $attributeId > 0) {
            $attributeValuesByProduct[$productId][$attributeId] = $value;
         }
      }

      // Fuer Gruppenbilder reichen die Artikelgruppen der aktuellen Menge.
      $imageGroupIds = array();
      foreach ($rows as $row) {
         $productId = (int)($row['id'] ?? 0);
         $directGroupId = (int)($row['product_group_id'] ?? 0);
         if ($directGroupId > 0 && isset($productGroupById[$directGroupId])) {
            $imageGroupIds[$directGroupId] = $directGroupId;
            continue;
         }
         foreach ((array)($productGroupMapsByProduct[$productId] ?? array()) as $map) {
            $groupId = (int)($map['group_id'] ?? 0);
            if (isset($productGroupById[$groupId])) $imageGroupIds[$groupId] = $groupId;
         }
      }
      $imageWhere = 'trash = 0 AND active = 1 AND (product_id IN (' . $productIdSql . ')';
      if ($imageGroupIds !== array()) {
         $imageWhere .= ' OR group_id IN (' . implode(',', array_map('intval', $imageGroupIds)) . ')';
      }
      $imageWhere .= ')';
      $imageRows = $this->db()->select(
         $this->dd('shopProductImage'),
         $imageWhere,
         '*',
         'is_primary DESC, sorter ASC, title ASC',
         'ASC',
         '',
         0,
         0,
         0
      );
      $imageRows = is_array($imageRows) ? $imageRows : array();
      $imagesByProduct = $this->rowsByIntKey($imageRows, 'product_id');

      foreach ($rows as &$row) {
         $productId = (int)($row['id'] ?? 0);

         $directGroupId = (int)($row['product_group_id'] ?? 0);
         if ($directGroupId > 0 && isset($productGroupById[$directGroupId])) {
            $row['groups'] = array($productGroupById[$directGroupId]);
         } else {
            $row['groups'] = $this->mappedGroupRows(
               (array)($productGroupMapsByProduct[$productId] ?? array()),
               $productGroupById,
               'group_id'
            );
         }
         $row['shipping_groups'] = $this->mappedGroupRows(
            (array)($shippingMapsByProduct[$productId] ?? array()),
            $shippingGroupById,
            'shipping_group_id'
         );
         $row['channel_groups'] = $this->mappedGroupRows(
            (array)($channelGroupMapsByProduct[$productId] ?? array()),
            $channelGroupById,
            'channel_group_id'
         );

         // Direkte Channel-Werte haben weiterhin Vorrang vor Vererbung.
         $channels = array();
         foreach ((array)($directChannelsByProduct[$productId] ?? array()) as $direct) {
            $key = (string)($direct['channel_key'] ?? '');
            $base = $channelIndex[$key] ?? array('title' => $key, 'sorter' => 9999);
            $channels[] = array(
               'channel_key' => $key,
               'title' => (string)($base['title'] ?? $key),
               'active' => (int)($direct['active'] ?? 0),
               'channel_sku' => (string)($direct['channel_sku'] ?? ''),
               'price_gross' => (float)($direct['price_gross'] ?? -1),
               'shipping_gross' => (float)($direct['shipping_gross'] ?? -1),
               '_sorter' => (int)($base['sorter'] ?? 9999),
            );
         }
         usort($channels, fn($a, $b) => ((int)($a['_sorter'] ?? 9999) <=> (int)($b['_sorter'] ?? 9999))
            ?: strcasecmp((string)($a['channel_key'] ?? ''), (string)($b['channel_key'] ?? '')));
         foreach ((array)($channelGroupMapsByProduct[$productId] ?? array()) as $groupMap) {
            $groupId = (int)($groupMap['channel_group_id'] ?? 0);
            foreach ((array)($channelGroupChannelsByGroup[$groupId] ?? array()) as $inherited) {
               $key = (string)($inherited['channel_key'] ?? '');
               $base = $channelIndex[$key] ?? array('title' => $key, 'sorter' => 9999);
               $channels[] = array(
                  'channel_key' => $key,
                  'title' => (string)($base['title'] ?? $key),
                  'active' => 1,
                  'channel_sku' => '',
                  'price_gross' => -1,
                  'shipping_gross' => -1,
                  '_sorter' => (int)($base['sorter'] ?? 9999),
               );
            }
         }
         $row['channels'] = array();
         $seenChannels = array();
         foreach ($channels as $channel) {
            $key = (string)($channel['channel_key'] ?? '');
            if ($key === '' || isset($seenChannels[$key])) continue;
            $seenChannels[$key] = true;
            if ((int)($channel['active'] ?? 0) === 1) {
               unset($channel['_sorter']);
               $row['channels'][] = $channel;
            }
         }

         $groupIds = array();
         foreach ($row['groups'] as $group) {
            $groupId = (int)($group['id'] ?? 0);
            if ($groupId > 0) $groupIds[$groupId] = true;
         }
         $images = (array)($imagesByProduct[$productId] ?? array());
         foreach ($imageRows as $image) {
            if (isset($groupIds[(int)($image['group_id'] ?? 0)])) $images[] = $image;
         }
         $row['images'] = array();
         $seenImages = array();
         foreach ($images as $image) {
            $path = (string)($image['image_path'] ?? '');
            $mediaId = (int)($image['media_id'] ?? 0);
            $key = $mediaId > 0 ? 'm:' . $mediaId : 'p:' . $path;
            if (($mediaId <= 0 && $path === '') || isset($seenImages[$key])) continue;
            $seenImages[$key] = true;
            $row['images'][] = $image;
         }

         $row['attributes'] = array();
         $seenDefinitions = array();
         foreach ($row['groups'] as $group) {
            $groupId = (int)($group['id'] ?? 0);
            foreach ((array)($definitionsByGroup[$groupId] ?? array()) as $definition) {
               $attributeId = (int)($definition['id'] ?? 0);
               if ($attributeId <= 0 || isset($seenDefinitions[$attributeId])) continue;
               $seenDefinitions[$attributeId] = true;
               $value = $attributeValuesByProduct[$productId][$attributeId] ?? array();
               $definition['value_text'] = $value['value_text'] ?? '';
               $definition['value_num'] = $value['value_num'] ?? '';
               $definition['unit_override'] = $value['unit_override'] ?? '';
               $definition['value_active'] = $value['active'] ?? 0;
               $text = trim((string)$definition['value_text']);
               $unit = trim((string)$definition['unit_override'])
                  ?: trim((string)($definition['unit'] ?? ''));
               $definition['display_value'] = $text !== '' && $unit !== ''
                  ? $text . ' ' . $unit
                  : $text;
               $row['attributes'][] = $definition;
            }
         }

         $primary = $row['groups'][0] ?? array();
         $shipping = $row['shipping_groups'][0] ?? array();
         $row['effective_tax_rate'] = $this->taxRateForClass(
            (string)($primary['tax_class'] ?? ''),
            (float)($primary['default_tax_rate'] ?? 19)
         );
         $row['effective_shipping_gross'] = (string)($row['shipping_mode'] ?? 'group') === 'individual'
            && (float)($row['shipping_gross'] ?? -1) >= 0
            ? (float)$row['shipping_gross']
            : (float)($shipping['shipping_gross'] ?? $primary['default_shipping_gross'] ?? 0);
         $row['effective_shipping_way'] = (string)($shipping['shipping_way'] ?? '');
         $row['effective_delivery_time'] = trim((string)($row['delivery_time'] ?? '')) !== ''
            ? trim((string)$row['delivery_time'])
            : trim((string)($shipping['delivery_time'] ?? ''));
      }
      unset($row);
      return $rows;
   }

   private function decorateProduct(array $row): array {
      $row['groups'] = $this->groupsForProduct((int)$row['id']);
      $row['shipping_groups'] = $this->shippingGroupsForProduct((int)$row['id']);
      $row['channel_groups'] = $this->channelGroupsForProduct((int)$row['id']);
      $row['channels'] = $this->channelsForProduct((int)$row['id']);
      $row['images'] = $this->imagesForProduct((int)$row['id'], $row['groups']);
      $row['attributes'] = $this->attributesForProduct((int)$row['id']);
      $primary = $row['groups'][0] ?? array();
      $shipping = $row['shipping_groups'][0] ?? array();
      $row['effective_tax_rate'] = $this->taxRateForClass(
         (string)($primary['tax_class'] ?? ''),
         (float)($primary['default_tax_rate'] ?? 19)
      );
      $row['effective_shipping_gross'] = (string)($row['shipping_mode'] ?? 'group') === 'individual' && (float)($row['shipping_gross'] ?? -1) >= 0
         ? (float)$row['shipping_gross']
         : (float)($shipping['shipping_gross'] ?? $primary['default_shipping_gross'] ?? 0);
      $row['effective_shipping_way'] = (string)($shipping['shipping_way'] ?? '');
      $row['effective_delivery_time'] = trim((string)($row['delivery_time'] ?? '')) !== ''
         ? trim((string)$row['delivery_time'])
         : trim((string)($shipping['delivery_time'] ?? ''));
      return $row;
   }

   private function taxRatesConfig(): array {
      $fallback = array(
         'mwst1' => array('title' => 'MwSt. normal', 'rate' => '19'),
         'mwst2' => array('title' => 'MwSt. ermaessigt', 'rate' => '7'),
         'mwst3' => array('title' => 'MwSt. vorbereitet', 'rate' => '22'),
      );
      if (!function_exists('dbx')) {
         return $fallback;
      }
      $cfg = dbx()->get_config('dbxShop', 'tax_rates', $fallback);
      return is_array($cfg) && count($cfg) ? $cfg : $fallback;
   }

   private function taxRateForClass(string $taxClass, float $fallback): float {
      $taxClass = trim($taxClass);
      $rates = $this->taxRatesConfig();
      if ($taxClass !== '' && isset($rates[$taxClass]) && is_array($rates[$taxClass])) {
         return (float)($rates[$taxClass]['rate'] ?? $fallback);
      }
      $defaultClass = function_exists('dbx') ? (string)dbx()->get_config('dbxShop', 'default_tax_class', 'mwst1') : 'mwst1';
      if (isset($rates[$defaultClass]) && is_array($rates[$defaultClass])) {
         return (float)($rates[$defaultClass]['rate'] ?? $fallback);
      }
      return $fallback;
   }

   public function products(bool $activeOnly = true): array {
      $this->install();
      $where = $activeOnly ? 'active = 1 AND trash = 0' : 'trash = 0';
      $rows = $this->db()->select($this->dd('shopProduct'), $where, '*', 'sorter ASC, title ASC', 'ASC', '', 0, 0, 0);
      $rows = is_array($rows) ? $rows : array();
      return $this->decorateProducts($rows);
   }

   /**
    * Liefert leichte, gebuendelt angereicherte Katalogzeilen.
    *
    * Suche und Filter benoetigen Gruppen, Attribute und den aktiven Channel,
    * aber noch keine Bilder, Versand- oder Channel-Gruppen. Diese Daten werden
    * erst fuer die sichtbare Report-Seite vollstaendig dekoriert.
    */
   public function catalogCandidates(string $channelKey = 'shop'): array {
      $this->install();
      $channelKey = trim($channelKey);
      $rows = $this->db()->select(
         $this->dd('shopProduct'),
         'active = 1 AND trash = 0',
         '*',
         'sorter ASC, title ASC',
         'ASC',
         '',
         0,
         0,
         0
      );
      $rows = is_array($rows) ? $rows : array();
      if ($rows === array()) return array();

      $ids = array_values(array_filter(array_map(
         static fn($row) => (int)($row['id'] ?? 0),
         $rows
      )));
      if ($ids === array()) return array();
      $idSql = implode(',', array_map('intval', $ids));

      $groups = $this->groups();
      $groupById = array();
      foreach ($groups as $group) {
         $groupById[(int)($group['id'] ?? 0)] = $group;
      }
      $groupMaps = $this->db()->select(
         $this->dd('shopProductGroupMap'),
         'product_id IN (' . $idSql . ')',
         '*',
         'is_primary',
         'DESC',
         '',
         0,
         0,
         0
      );
      $groupMapsByProduct = array();
      foreach ((is_array($groupMaps) ? $groupMaps : array()) as $map) {
         $productId = (int)($map['product_id'] ?? 0);
         $groupId = (int)($map['group_id'] ?? 0);
         if ($productId > 0 && $groupId > 0) {
            $groupMapsByProduct[$productId][] = $map;
         }
      }

      $defs = $this->db()->select(
         $this->dd('shopAttributeDefinition'),
         'active = 1 AND trash = 0',
         '*',
         'sorter ASC, title ASC',
         'ASC',
         '',
         0,
         0,
         0
      );
      $defById = array();
      foreach ((is_array($defs) ? $defs : array()) as $def) {
         $defById[(int)($def['id'] ?? 0)] = $def;
      }
      $attributeValues = $this->db()->select(
         $this->dd('shopProductAttributeValue'),
         'product_id IN (' . $idSql . ') AND trash = 0',
         '*',
         '',
         'ASC',
         '',
         0,
         0,
         0
      );
      $attributeValuesByProduct = array();
      foreach ((is_array($attributeValues) ? $attributeValues : array()) as $value) {
         $productId = (int)($value['product_id'] ?? 0);
         $attributeId = (int)($value['attribute_id'] ?? 0);
         if ($productId > 0 && isset($defById[$attributeId])) {
            $attributeValuesByProduct[$productId][$attributeId] = $value;
         }
      }

      // Direkte Channel-Zuordnungen haben wie in channelsForProduct()
      // Vorrang, auch wenn sie den geerbten Channel explizit deaktivieren.
      $directRows = $this->db()->select(
         $this->dd('shopProductChannel'),
         'product_id IN (' . $idSql . ') AND channel_key = ' . $this->sqlValue($channelKey),
         '*',
         '',
         'ASC',
         '',
         0,
         0,
         0
      );
      $directByProduct = array();
      foreach ((is_array($directRows) ? $directRows : array()) as $direct) {
         $directByProduct[(int)($direct['product_id'] ?? 0)] = $direct;
      }
      $channelGroupMaps = $this->db()->select(
         $this->dd('shopProductChannelGroupMap'),
         'product_id IN (' . $idSql . ')',
         '*',
         '',
         'ASC',
         '',
         0,
         0,
         0
      );
      $channelGroupIds = array();
      foreach ((is_array($channelGroupMaps) ? $channelGroupMaps : array()) as $map) {
         $groupId = (int)($map['channel_group_id'] ?? 0);
         if ($groupId > 0) $channelGroupIds[$groupId] = $groupId;
      }
      $activeChannelGroups = array();
      if ($channelGroupIds) {
         $groupChannels = $this->db()->select(
            $this->dd('shopChannelGroupChannel'),
            'channel_group_id IN (' . implode(',', $channelGroupIds) . ')'
               . ' AND channel_key = ' . $this->sqlValue($channelKey)
               . ' AND active = 1',
            'channel_group_id',
            '',
            'ASC',
            '',
            0,
            0,
            0
         );
         foreach ((is_array($groupChannels) ? $groupChannels : array()) as $groupChannel) {
            $activeChannelGroups[(int)($groupChannel['channel_group_id'] ?? 0)] = true;
         }
      }
      $inheritedByProduct = array();
      foreach ((is_array($channelGroupMaps) ? $channelGroupMaps : array()) as $map) {
         $productId = (int)($map['product_id'] ?? 0);
         $groupId = (int)($map['channel_group_id'] ?? 0);
         if ($productId > 0 && isset($activeChannelGroups[$groupId])) {
            $inheritedByProduct[$productId] = true;
         }
      }

      foreach ($rows as &$row) {
         $productId = (int)($row['id'] ?? 0);
         $rowGroups = array();
         $groupIds = array();
         $directGroupId = (int)($row['product_group_id'] ?? 0);
         if ($directGroupId > 0 && isset($groupById[$directGroupId])) {
            $rowGroups[] = $groupById[$directGroupId];
            $groupIds[$directGroupId] = true;
         } else {
            foreach ((array)($groupMapsByProduct[$productId] ?? array()) as $map) {
               $groupId = (int)($map['group_id'] ?? 0);
               if (isset($groupById[$groupId])) {
                  $rowGroups[] = $groupById[$groupId];
                  $groupIds[$groupId] = true;
               }
            }
         }
         $row['groups'] = $rowGroups;

         $row['attributes'] = array();
         foreach ((array)($attributeValuesByProduct[$productId] ?? array()) as $attributeId => $value) {
            $def = $defById[(int)$attributeId] ?? array();
            if (!$def || !isset($groupIds[(int)($def['group_id'] ?? 0)])) continue;
            $def['value_text'] = $value['value_text'] ?? '';
            $def['value_num'] = $value['value_num'] ?? '';
            $def['unit_override'] = $value['unit_override'] ?? '';
            $def['value_active'] = $value['active'] ?? 0;
            $text = trim((string)$def['value_text']);
            $unit = trim((string)$def['unit_override']) ?: trim((string)($def['unit'] ?? ''));
            $def['display_value'] = $text !== '' && $unit !== '' ? $text . ' ' . $unit : $text;
            $row['attributes'][] = $def;
         }

         $direct = $directByProduct[$productId] ?? null;
         $channelActive = is_array($direct)
            ? (int)($direct['active'] ?? 0) === 1
            : isset($inheritedByProduct[$productId]);
         $row['channels'] = $channelActive
            ? array(array('channel_key' => $channelKey, 'active' => 1))
            : array();
      }
      unset($row);
      return $rows;
   }

   /** Dekoriert nur die vom Report tatsaechlich sichtbaren Artikel. */
   public function productsByIds(array $ids): array {
      $this->install();
      $ids = $this->normalizeProductIds($ids);
      if ($ids === array()) return array();
      $rows = $this->db()->select(
         $this->dd('shopProduct'),
         'id IN (' . implode(',', $ids) . ') AND active = 1 AND trash = 0',
         '*',
         '',
         'ASC',
         '',
         0,
         0,
         0
      );
      $byId = array();
      foreach ($this->decorateProducts(is_array($rows) ? $rows : array()) as $row) {
         $byId[(int)($row['id'] ?? 0)] = $row;
      }
      $result = array();
      foreach ($ids as $id) {
         if (isset($byId[$id])) $result[] = $byId[$id];
      }
      return $result;
   }

   public function productBySku(string $sku, bool $activeOnly = true): ?array {
      $this->install();
      $where = 'sku = ' . $this->sqlValue($sku) . ' AND trash = 0';
      if ($activeOnly) {
         $where .= ' AND active = 1';
      }
      $row = $this->db()->select1($this->dd('shopProduct'), $where, '*', 0);
      return is_array($row) ? $this->decorateProduct($row) : null;
   }

   public function productById(int $id): ?array {
      $this->install();
      $row = $this->db()->select1($this->dd('shopProduct'), 'id = ' . (int)$id . ' AND trash = 0', '*', 0);
      return is_array($row) ? $this->decorateProduct($row) : null;
   }

   public function groupById(int $id): ?array {
      $this->install();
      if ($id <= 0) {
         return null;
      }
      $row = $this->db()->select1($this->dd('shopProductGroup'), 'id = ' . (int)$id . ' AND trash = 0', '*', 0);
      return is_array($row) ? $row : null;
   }

   private function stockEnabled(): bool {
      $cfg = function_exists('dbx') ? dbx()->get_config('dbxShop', '', array()) : array();
      return is_array($cfg) && !empty($cfg['stock_enabled']);
   }

   private function requiresStock(array $product): bool {
      return $this->stockEnabled() && (string)($product['product_type'] ?? '') === 'physical';
   }

   private function isPhysicalProduct(array $product): bool {
      return (string)($product['product_type'] ?? '') === 'physical';
   }

   public function stockIssuesForItems(array $items): array {
      $this->install();
      $issues = array();
      foreach ($items as $sku => $qty) {
         $qty = max(1, (int)$qty);
         $product = $this->productBySku((string)$sku);
         if (!$product || !$this->requiresStock($product)) {
            continue;
         }
         $stock = (int)($product['stock'] ?? 0);
         if ($stock < $qty) {
            $issues[] = array(
               'sku' => (string)($product['sku'] ?? $sku),
               'title' => (string)($product['title'] ?? $sku),
               'requested' => $qty,
               'stock' => $stock,
            );
         }
      }
      return $issues;
   }

   private function hasReservableStockSnapshots(array $snapshots): bool {
      if (!$this->stockEnabled()) {
         return false;
      }
      foreach ($snapshots as $item) {
         if ((int)($item['product_id'] ?? 0) > 0
            && (string)($item['product_type'] ?? '') === 'physical') return true;
      }
      return false;
   }

   private function reserveStockForSnapshots(array $snapshots): int {
      if (!$this->stockEnabled()) {
         return 0;
      }
      $reserved = 0;
      $db = $this->db();
      $server = $db->get_dd_server($this->dd('shopProduct'));
      $table = $db->get_dd_table($this->dd('shopProduct'));
      $uid = function_exists('dbx') ? (int)dbx()->user() : 0;
      $now = date('Y-m-d H:i:s');
      foreach ($snapshots as $item) {
         $productId = (int)($item['product_id'] ?? 0);
         if ($productId <= 0 || (string)($item['product_type'] ?? '') !== 'physical') {
            continue;
         }
         $qty = max(1, (int)($item['qty'] ?? 1));
         $sql = 'UPDATE ' . $table
            . ' SET stock = stock - ' . $qty
            . ', update_date = ' . $this->sqlValue($now)
            . ', update_uid = ' . $uid
            . ' WHERE id = ' . $productId
            . ' AND trash = 0 AND stock >= ' . $qty;
         if ((int)$db->update_query($server, $sql) !== 1) {
            throw new \RuntimeException(
               'Nicht genuegend Lagerbestand fuer ' . (string)($item['title'] ?? $item['sku'] ?? 'Artikel') . '.'
            );
         }
         $reserved += $qty;
      }
      return $reserved;
   }

   private function releaseStockForOrder(array $order, string $reason): bool {
      $orderId = (int)($order['id'] ?? 0);
      if ($orderId <= 0 || (int)($order['stock_reserved'] ?? 0) !== 1 || (int)($order['stock_released'] ?? 0) === 1) {
         return false;
      }

      $db = $this->db();
      if ($db->begin($this->dd('shopOrder')) !== 1) {
         return false;
      }

      try {
         $now = date('Y-m-d H:i:s');
         if ($db->update($this->dd('shopOrder'), array(
            'stock_released' => 1,
            'stock_released_date' => $now,
            'update_date' => $now,
         ), 'id = ' . $orderId . ' AND trash = 0 AND stock_reserved = 1 AND stock_released = 0', 0) !== 1
            || (int)$db->_update_count !== 1) {
            $db->rollback($this->dd('shopOrder'));
            return false;
         }

         $released = 0;
         $server = $db->get_dd_server($this->dd('shopProduct'));
         $table = $db->get_dd_table($this->dd('shopProduct'));
         $uid = function_exists('dbx') ? (int)dbx()->user() : 0;
         foreach ((array)($order['items'] ?? array()) as $item) {
            $productId = (int)($item['product_id'] ?? 0);
            if ($productId <= 0) continue;
            $product = $db->select1(
               $this->dd('shopProduct'),
               'id = ' . $productId . ' AND trash = 0',
               'id,product_type',
               0
            );
            if (!is_array($product) || !$this->isPhysicalProduct($product)) continue;

            $qty = max(1, (int)($item['qty'] ?? 1));
            $sql = 'UPDATE ' . $table
               . ' SET stock = stock + ' . $qty
               . ', update_date = ' . $this->sqlValue($now)
               . ', update_uid = ' . $uid
               . ' WHERE id = ' . $productId . ' AND trash = 0';
            if ((int)$db->update_query($server, $sql) !== 1) {
               throw new \RuntimeException('stock_release_update_failed');
            }
            $released += $qty;
         }

         if ($released <= 0
            || !$this->addOrderHistory($orderId, 'stock_release', '', (string)$released, $reason)
            || $db->commit($this->dd('shopOrder')) !== 1) {
            throw new \RuntimeException('stock_release_commit_failed');
         }
         return true;
      } catch (\Throwable $e) {
         $db->rollback($this->dd('shopOrder'));
         dbx()->debug('#Shop stock release rollback order=(' . $orderId . ') error=(' . $e->getMessage() . ')');
         return false;
      }
   }

   private function normalizeProductIds(array $ids): array {
      $clean = array();
      foreach ($ids as $id) {
         $id = (int)$id;
         if ($id > 0) {
            $clean[$id] = $id;
         }
      }
      return array_values($clean);
   }

   private function channelExists(string $channelKey): bool {
      $channelKey = trim($channelKey);
      if ($channelKey === '') {
         return false;
      }
      return $this->db()->count($this->dd('shopChannel'), 'channel_key = ' . $this->sqlValue($channelKey) . ' AND trash = 0') > 0;
   }

   private function productGroupExists(int $groupId): bool {
      if ($groupId <= 0) {
         return false;
      }
      return $this->db()->count($this->dd('shopProductGroup'), 'id = ' . (int)$groupId . ' AND trash = 0') > 0;
   }

   public function deleteProducts(array $ids): int {
      $this->install();
      $ids = $this->normalizeProductIds($ids);
      if ($ids === array()) {
         return 0;
      }

      $where = 'id IN (' . implode(',', array_map('intval', $ids)) . ') AND trash = 0';
      $count = (int)$this->db()->count($this->dd('shopProduct'), $where);
      if ($count <= 0) {
         return 0;
      }
      $this->db()->update(
         $this->dd('shopProduct'),
         array('trash' => 1, 'active' => 0, 'update_date' => date('Y-m-d H:i:s')),
         $where,
         0
      );
      return $count;
   }

   public function addChannelToProducts(array $ids, string $channelKey): int {
      $this->install();
      $ids = $this->normalizeProductIds($ids);
      $channelKey = trim($channelKey);
      if ($ids === array() || !$this->channelExists($channelKey)) {
         return 0;
      }

      $count = 0;
      foreach ($ids as $id) {
         $ok = $this->db()->save(
            $this->dd('shopProductChannel'),
            array('product_id' => $id, 'channel_key' => $channelKey, 'active' => 1, 'channel_sku' => '', 'price_gross' => -1, 'shipping_gross' => -1),
            'product_id = ' . (int)$id . ' AND channel_key = ' . $this->sqlValue($channelKey),
            0
         );
         if ($ok) {
            $this->db()->update($this->dd('shopProduct'), array('update_date' => date('Y-m-d H:i:s')), 'id = ' . (int)$id . ' AND trash = 0', 0);
            $count++;
         }
      }
      return $count;
   }

   public function removeChannelFromProducts(array $ids, string $channelKey): int {
      $this->install();
      $ids = $this->normalizeProductIds($ids);
      $channelKey = trim($channelKey);
      if ($ids === array() || !$this->channelExists($channelKey)) {
         return 0;
      }

      $count = 0;
      foreach ($ids as $id) {
         $ok = $this->db()->save(
            $this->dd('shopProductChannel'),
            array('product_id' => $id, 'channel_key' => $channelKey, 'active' => 0, 'channel_sku' => '', 'price_gross' => -1, 'shipping_gross' => -1),
            'product_id = ' . (int)$id . ' AND channel_key = ' . $this->sqlValue($channelKey),
            0
         );
         if ($ok) {
            $this->db()->update($this->dd('shopProduct'), array('update_date' => date('Y-m-d H:i:s')), 'id = ' . (int)$id . ' AND trash = 0', 0);
            $count++;
         }
      }
      return $count;
   }

   public function setProductGroupForProducts(array $ids, int $groupId): int {
      $this->install();
      $ids = $this->normalizeProductIds($ids);
      if ($ids === array() || !$this->productGroupExists($groupId)) {
         return 0;
      }

      $count = 0;
      foreach ($ids as $id) {
         $this->db()->update($this->dd('shopProductGroupMap'), array('is_primary' => 0), 'product_id = ' . (int)$id, 0);
         $ok = $this->db()->save(
            $this->dd('shopProductGroupMap'),
            array('product_id' => $id, 'group_id' => $groupId, 'is_primary' => 1),
            'product_id = ' . (int)$id . ' AND group_id = ' . (int)$groupId,
            0
         );
         if ($ok) {
            $this->db()->update($this->dd('shopProduct'), array('product_group_id' => $groupId, 'update_date' => date('Y-m-d H:i:s')), 'id = ' . (int)$id . ' AND trash = 0', 0);
            $count++;
         }
      }
      return $count;
   }

   public function groupsForProduct(int $productId): array {
      $product = $this->db()->select1($this->dd('shopProduct'), 'id = ' . (int)$productId . ' AND trash = 0', 'product_group_id', 0);
      $directGroupId = (int)($product['product_group_id'] ?? 0);
      if ($directGroupId > 0) {
         $group = $this->groupById($directGroupId);
         if (is_array($group)) {
            return array($group);
         }
      }

      $maps = $this->db()->select($this->dd('shopProductGroupMap'), 'product_id = ' . (int)$productId, '*', 'is_primary DESC', 'ASC', '', 0, 0, 0);
      if (!is_array($maps) || $maps === array()) {
         return array();
      }
      $groupIds = array_values(array_unique(array_map(fn($row) => (int)($row['group_id'] ?? 0), $maps)));
      $groupIds = array_values(array_filter($groupIds, fn($id) => $id > 0));
      if ($groupIds === array()) {
         return array();
      }
      $groups = $this->db()->select($this->dd('shopProductGroup'), 'id IN (' . implode(',', $groupIds) . ') AND trash = 0', '*', '', 'ASC', '', 0, 0, 0);
      $byId = array();
      foreach ((is_array($groups) ? $groups : array()) as $group) {
         $byId[(int)($group['id'] ?? 0)] = $group;
      }
      $rows = array();
      foreach ($maps as $map) {
         $id = (int)($map['group_id'] ?? 0);
         if (isset($byId[$id])) {
            $row = $byId[$id];
            $row['_is_primary'] = (int)($map['is_primary'] ?? 0);
            $rows[] = $row;
         }
      }
      usort($rows, fn($a, $b) => ((int)($b['_is_primary'] ?? 0) <=> (int)($a['_is_primary'] ?? 0))
         ?: ((int)($a['sorter'] ?? 0) <=> (int)($b['sorter'] ?? 0))
         ?: strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? '')));
      foreach ($rows as &$row) {
         unset($row['_is_primary']);
      }
      unset($row);
      return $rows;
   }

   public function channelsForProduct(int $productId): array {
      $channelIndex = array();
      foreach ($this->channels() as $channel) {
         $channelIndex[(string)($channel['channel_key'] ?? '')] = $channel;
      }

      $channels = array();
      $direct = $this->db()->select($this->dd('shopProductChannel'), 'product_id = ' . (int)$productId, '*', '', 'ASC', '', 0, 0, 0);
      foreach ((is_array($direct) ? $direct : array()) as $row) {
         $key = (string)($row['channel_key'] ?? '');
         $base = $channelIndex[$key] ?? array('channel_key' => $key, 'title' => $key, 'sorter' => 9999);
         $channels[] = array(
            'channel_key' => $key,
            'title' => (string)($base['title'] ?? $key),
            'active' => (int)($row['active'] ?? 0),
            'channel_sku' => (string)($row['channel_sku'] ?? ''),
            'price_gross' => (float)($row['price_gross'] ?? -1),
            'shipping_gross' => (float)($row['shipping_gross'] ?? -1),
            '_sorter' => (int)($base['sorter'] ?? 9999),
         );
      }
      usort($channels, fn($a, $b) => ((int)($a['_sorter'] ?? 9999) <=> (int)($b['_sorter'] ?? 9999))
         ?: strcasecmp((string)($a['channel_key'] ?? ''), (string)($b['channel_key'] ?? '')));

      $groupMaps = $this->db()->select($this->dd('shopProductChannelGroupMap'), 'product_id = ' . (int)$productId, '*', '', 'ASC', '', 0, 0, 0);
      foreach ((is_array($groupMaps) ? $groupMaps : array()) as $groupMap) {
         $groupId = (int)($groupMap['channel_group_id'] ?? 0);
         if ($groupId <= 0) continue;
         $groupChannels = $this->db()->select($this->dd('shopChannelGroupChannel'), 'channel_group_id = ' . $groupId . ' AND active = 1', '*', '', 'ASC', '', 0, 0, 0);
         foreach ((is_array($groupChannels) ? $groupChannels : array()) as $row) {
            $key = (string)($row['channel_key'] ?? '');
            $base = $channelIndex[$key] ?? array('channel_key' => $key, 'title' => $key, 'sorter' => 9999);
            $channels[] = array(
               'channel_key' => $key,
               'title' => (string)($base['title'] ?? $key),
               'active' => 1,
               'channel_sku' => '',
               'price_gross' => -1,
               'shipping_gross' => -1,
               '_sorter' => (int)($base['sorter'] ?? 9999),
            );
         }
      }

      $seen = array();
      $clean = array();
      foreach ($channels as $channel) {
         $key = (string)($channel['channel_key'] ?? '');
         if ($key === '' || isset($seen[$key])) continue;
         $seen[$key] = true;
         if ((int)($channel['active'] ?? 0) === 1) {
            unset($channel['_sorter']);
            $clean[] = $channel;
         }
      }
      return $clean;
   }

   public function productChannelOverrides(int $productId): array {
      $this->install();
      $channelIndex = array();
      foreach ($this->channels() as $channel) {
         $channelIndex[(string)($channel['channel_key'] ?? '')] = $channel;
      }
      $rows = $this->db()->select($this->dd('shopProductChannel'), 'product_id = ' . (int)$productId, '*', '', 'ASC', '', 0, 0, 0);
      $rows = is_array($rows) ? $rows : array();
      foreach ($rows as &$row) {
         $key = (string)($row['channel_key'] ?? '');
         $row['title'] = (string)($channelIndex[$key]['title'] ?? $key);
         $row['_sorter'] = (int)($channelIndex[$key]['sorter'] ?? 9999);
      }
      unset($row);
      usort($rows, fn($a, $b) => ((int)($a['_sorter'] ?? 9999) <=> (int)($b['_sorter'] ?? 9999))
         ?: strcasecmp((string)($a['channel_key'] ?? ''), (string)($b['channel_key'] ?? '')));
      $out = array();
      foreach ($rows as $row) {
         $key = (string)($row['channel_key'] ?? '');
         if ($key !== '') {
            unset($row['_sorter']);
            $out[$key] = $row;
         }
      }
      return $out;
   }

   public function inheritedChannelsForProduct(int $productId): array {
      $this->install();
      $channelIndex = array();
      foreach ($this->channels() as $channel) {
         $channelIndex[(string)($channel['channel_key'] ?? '')] = $channel;
      }
      $groupMaps = $this->db()->select($this->dd('shopProductChannelGroupMap'), 'product_id = ' . (int)$productId, '*', '', 'ASC', '', 0, 0, 0);
      $rows = array();
      foreach ((is_array($groupMaps) ? $groupMaps : array()) as $groupMap) {
         $groupId = (int)($groupMap['channel_group_id'] ?? 0);
         if ($groupId <= 0) continue;
         $group = $this->db()->select1($this->dd('shopChannelGroup'), 'id = ' . $groupId . ' AND trash = 0 AND active = 1', '*', 0);
         if (!is_array($group)) continue;
         $groupChannels = $this->db()->select($this->dd('shopChannelGroupChannel'), 'channel_group_id = ' . $groupId . ' AND active = 1', '*', '', 'ASC', '', 0, 0, 0);
         foreach ((is_array($groupChannels) ? $groupChannels : array()) as $row) {
            $key = (string)($row['channel_key'] ?? '');
            $base = $channelIndex[$key] ?? array('channel_key' => $key, 'title' => $key, 'sorter' => 9999);
            $rows[] = array(
               'channel_key' => $key,
               'title' => (string)($base['title'] ?? $key),
               'active' => (int)($row['active'] ?? 0),
               'group_title' => (string)($group['title'] ?? ''),
               '_sorter' => (int)($base['sorter'] ?? 9999),
            );
         }
      }
      usort($rows, fn($a, $b) => ((int)($a['_sorter'] ?? 9999) <=> (int)($b['_sorter'] ?? 9999))
         ?: strcasecmp((string)($a['channel_key'] ?? ''), (string)($b['channel_key'] ?? '')));
      $out = array();
      foreach ($rows as $row) {
         $key = (string)($row['channel_key'] ?? '');
         if ($key === '') {
            continue;
         }
         if (!isset($out[$key])) {
            $out[$key] = $row;
            $out[$key]['group_titles'] = array();
         }
         $groupTitle = trim((string)($row['group_title'] ?? ''));
         if ($groupTitle !== '') {
            $out[$key]['group_titles'][$groupTitle] = $groupTitle;
         }
         unset($out[$key]['_sorter']);
      }
      return $out;
   }

   public function saveProductChannelOverrides(int $productId, array $activeChannelKeys): void {
      $this->install();
      $productId = max(0, $productId);
      if ($productId <= 0) {
         return;
      }

      $product = $this->productById($productId);
      if (!$product) {
         return;
      }

      $active = array();
      foreach ($activeChannelKeys as $key) {
         $key = trim((string)$key);
         if ($key !== '') {
            $active[$key] = true;
         }
      }

      foreach ($this->channels() as $channel) {
         $key = trim((string)($channel['channel_key'] ?? ''));
         if ($key === '') {
            continue;
         }
         $existing = $this->db()->select1(
            $this->dd('shopProductChannel'),
            'product_id = ' . (int)$productId . ' AND channel_key = ' . $this->sqlValue($key),
            '*',
            0
         );
         $existing = is_array($existing) ? $existing : array();
         $channelSku = trim((string)($existing['channel_sku'] ?? ''));
         if ($channelSku === '') {
            $channelSku = (string)($product['sku'] ?? '');
         }
         $this->db()->save(
            $this->dd('shopProductChannel'),
            array(
               'product_id' => $productId,
               'channel_key' => $key,
               'active' => isset($active[$key]) ? 1 : 0,
               'channel_sku' => $channelSku,
               'price_gross' => (float)($existing['price_gross'] ?? -1),
               'shipping_gross' => (float)($existing['shipping_gross'] ?? -1),
               'external_listing_id' => (string)($existing['external_listing_id'] ?? ''),
               'external_offer_id' => (string)($existing['external_offer_id'] ?? ''),
               'export_status' => (string)($existing['export_status'] ?? ''),
               'export_message' => (string)($existing['export_message'] ?? ''),
               'export_payload' => (string)($existing['export_payload'] ?? ''),
               'last_export_date' => (string)($existing['last_export_date'] ?? ''),
            ),
            'product_id = ' . (int)$productId . ' AND channel_key = ' . $this->sqlValue($key),
            0
         );
      }

      $this->db()->update($this->dd('shopProduct'), array('update_date' => date('Y-m-d H:i:s')), 'id = ' . (int)$productId . ' AND trash = 0', 0);
   }

   public function shippingGroupsForProduct(int $productId): array {
      $maps = $this->db()->select($this->dd('shopProductShippingGroupMap'), 'product_id = ' . (int)$productId, '*', 'is_primary DESC', 'ASC', '', 0, 0, 0);
      if (!is_array($maps) || $maps === array()) {
         return array();
      }
      $ids = array_values(array_filter(array_unique(array_map(fn($row) => (int)($row['shipping_group_id'] ?? 0), $maps)), fn($id) => $id > 0));
      if ($ids === array()) {
         return array();
      }
      $groups = $this->db()->select($this->dd('shopShippingGroup'), 'id IN (' . implode(',', $ids) . ') AND trash = 0', '*', '', 'ASC', '', 0, 0, 0);
      $byId = array();
      foreach ((is_array($groups) ? $groups : array()) as $group) {
         $byId[(int)($group['id'] ?? 0)] = $group;
      }
      $rows = array();
      foreach ($maps as $map) {
         $id = (int)($map['shipping_group_id'] ?? 0);
         if (isset($byId[$id])) {
            $row = $byId[$id];
            $row['_is_primary'] = (int)($map['is_primary'] ?? 0);
            $rows[] = $row;
         }
      }
      usort($rows, fn($a, $b) => ((int)($b['_is_primary'] ?? 0) <=> (int)($a['_is_primary'] ?? 0))
         ?: ((int)($a['sorter'] ?? 0) <=> (int)($b['sorter'] ?? 0))
         ?: strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? '')));
      foreach ($rows as &$row) {
         unset($row['_is_primary']);
      }
      unset($row);
      return $rows;
   }

   public function channelGroupsForProduct(int $productId): array {
      $maps = $this->db()->select($this->dd('shopProductChannelGroupMap'), 'product_id = ' . (int)$productId, '*', 'is_primary DESC', 'ASC', '', 0, 0, 0);
      if (!is_array($maps) || $maps === array()) {
         return array();
      }
      $ids = array_values(array_filter(array_unique(array_map(fn($row) => (int)($row['channel_group_id'] ?? 0), $maps)), fn($id) => $id > 0));
      if ($ids === array()) {
         return array();
      }
      $groups = $this->db()->select($this->dd('shopChannelGroup'), 'id IN (' . implode(',', $ids) . ') AND trash = 0', '*', '', 'ASC', '', 0, 0, 0);
      $byId = array();
      foreach ((is_array($groups) ? $groups : array()) as $group) {
         $byId[(int)($group['id'] ?? 0)] = $group;
      }
      $rows = array();
      foreach ($maps as $map) {
         $id = (int)($map['channel_group_id'] ?? 0);
         if (isset($byId[$id])) {
            $row = $byId[$id];
            $row['_is_primary'] = (int)($map['is_primary'] ?? 0);
            $rows[] = $row;
         }
      }
      usort($rows, fn($a, $b) => ((int)($b['_is_primary'] ?? 0) <=> (int)($a['_is_primary'] ?? 0))
         ?: ((int)($a['sorter'] ?? 0) <=> (int)($b['sorter'] ?? 0))
         ?: strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? '')));
      foreach ($rows as &$row) {
         unset($row['_is_primary']);
      }
      unset($row);
      return $rows;
   }

   public function groups(): array {
      $this->install();
      return $this->remember('groups', function(): array {
         $rows = $this->db()->select($this->dd('shopProductGroup'), 'trash = 0', '*', 'parent_id ASC, sorter ASC, title ASC', 'ASC', '', 0, 0, 0);
         return is_array($rows) ? $rows : array();
      });
   }

   public function groupsByParent(int $parentId = 0, bool $activeOnly = true): array {
      $this->install();
      $where = 'trash = 0 AND parent_id = ' . max(0, (int)$parentId);
      if ($activeOnly) {
         $where .= ' AND active = 1';
      }
      $rows = $this->db()->select($this->dd('shopProductGroup'), $where, '*', 'sorter ASC, title ASC', 'ASC', '', 0, 0, 0);
      return is_array($rows) ? $rows : array();
   }

   public function groupPath(int $groupId): array {
      $this->install();
      $path = array();
      $seen = array();
      $current = max(0, (int)$groupId);
      while ($current > 0 && !isset($seen[$current])) {
         $seen[$current] = true;
         $group = $this->groupById($current);
         if (!is_array($group)) {
            break;
         }
         array_unshift($path, $group);
         $current = (int)($group['parent_id'] ?? 0);
      }
      return $path;
   }

   private function wouldCreateGroupCycle(int $groupId, int $parentId): bool {
      if ($groupId <= 0 || $parentId <= 0) {
         return false;
      }
      if ($groupId === $parentId) {
         return true;
      }
      $seen = array($groupId => true);
      $current = $parentId;
      while ($current > 0 && !isset($seen[$current])) {
         $seen[$current] = true;
         $group = $this->groupById($current);
         if (!is_array($group)) {
            return false;
         }
         $current = (int)($group['parent_id'] ?? 0);
         if ($current === $groupId) {
            return true;
         }
      }
      return false;
   }

   private function nextGroupSorter(int $parentId): int {
      $rows = $this->db()->select(
         $this->dd('shopProductGroup'),
         'trash = 0 AND parent_id = ' . max(0, (int)$parentId),
         'sorter',
         'sorter',
         'DESC',
         '',
         0,
         1,
         0
      );
      $max = is_array($rows) && isset($rows[0]) ? (int)($rows[0]['sorter'] ?? 0) : 0;
      return $max + 10;
   }

   public function moveProductGroupParent(int $groupId, int $parentId): bool {
      $this->install();
      $groupId = max(0, (int)$groupId);
      $parentId = max(0, (int)$parentId);
      if ($groupId <= 0 || !is_array($this->groupById($groupId))) {
         return false;
      }
      if ($parentId > 0 && !is_array($this->groupById($parentId))) {
         return false;
      }
      if ($this->wouldCreateGroupCycle($groupId, $parentId)) {
         return false;
      }
      $this->db()->update(
         $this->dd('shopProductGroup'),
         array(
            'parent_id' => $parentId,
            'sorter' => $this->nextGroupSorter($parentId),
            'update_date' => date('Y-m-d H:i:s'),
         ),
         'id = ' . $groupId . ' AND trash = 0',
         0
      );
      $this->clearRequestCache();
      return true;
   }

   public function channels(): array {
      $this->install();
      return $this->remember('channels', function(): array {
         $rows = $this->db()->select($this->dd('shopChannel'), 'trash = 0', '*', 'sorter ASC, title ASC', 'ASC', '', 0, 0, 0);
         return is_array($rows) ? $rows : array();
      });
   }

   public function channelById(int $id): ?array {
      $this->install();
      if ($id <= 0) {
         return null;
      }
      $row = $this->db()->select1($this->dd('shopChannel'), 'id = ' . (int)$id . ' AND trash = 0', '*', 0);
      return is_array($row) ? $row : null;
   }

   public function channelByKey(string $key): ?array {
      $this->install();
      $key = trim($key);
      if ($key === '') {
         return null;
      }
      $row = $this->db()->select1($this->dd('shopChannel'), 'channel_key = ' . $this->sqlValue($key) . ' AND trash = 0', '*', 0);
      return is_array($row) ? $row : null;
   }

   public function updateChannel(int $id, array $data): void {
      $this->install();
      $secretFields = array('api_client_secret', 'api_access_token', 'api_refresh_token', 'api_password', 'webhook_secret');
      $existing = $id > 0 ? ($this->channelById($id) ?: array()) : array();
      $values = array(
         'title' => (string)($data['title'] ?? ''),
         'description' => (string)($data['description'] ?? ''),
         'platform_type' => (string)($data['platform_type'] ?? 'custom'),
         'connection_mode' => (string)($data['connection_mode'] ?? 'manual'),
         'api_base_url' => (string)($data['api_base_url'] ?? ''),
         'api_client_id' => (string)($data['api_client_id'] ?? ''),
         'api_username' => (string)($data['api_username'] ?? ''),
         'marketplace_id' => (string)($data['marketplace_id'] ?? ''),
         'seller_id' => (string)($data['seller_id'] ?? ''),
         'account_id' => (string)($data['account_id'] ?? ''),
         'location_key' => (string)($data['location_key'] ?? ''),
         'category_id' => (string)($data['category_id'] ?? ''),
         'payment_policy_id' => (string)($data['payment_policy_id'] ?? ''),
         'fulfillment_policy_id' => (string)($data['fulfillment_policy_id'] ?? ''),
         'return_policy_id' => (string)($data['return_policy_id'] ?? ''),
         'notification_destination' => (string)($data['notification_destination'] ?? ''),
         'notification_topic' => (string)($data['notification_topic'] ?? ''),
         'api_scope' => (string)($data['api_scope'] ?? ''),
         'export_enabled' => !empty($data['export_enabled']) ? 1 : 0,
         'order_import_enabled' => !empty($data['order_import_enabled']) ? 1 : 0,
         'active' => !empty($data['active']) ? 1 : 0,
         'sorter' => (int)($data['sorter'] ?? 100),
      );
      foreach ($secretFields as $field) {
         $posted = (string)($data[$field] ?? '');
         $values[$field] = ($id > 0 && $posted === '') ? (string)($existing[$field] ?? '') : $posted;
      }

      if ($id <= 0) {
         $channelKey = $this->normalizeKey((string)($data['channel_key'] ?? ''));
         if ($channelKey === '') {
            $channelKey = $this->normalizeKey((string)($data['title'] ?? 'channel'));
         }
         if ($channelKey === '') $channelKey = 'channel';
         $channelKey = $this->uniqueChannelKey($channelKey);
         $values['channel_key'] = $channelKey;
         $this->db()->insert($this->dd('shopChannel'), $values, 0);
         $this->clearRequestCache();
         return;
      }

      $this->db()->update($this->dd('shopChannel'), $values, 'id = ' . (int)$id, 0);
      $this->clearRequestCache();
   }

   public function deleteChannel(int $id): int {
      $this->install();
      if ($id <= 0) {
         return 0;
      }

      $channel = $this->channelById($id);
      if (!$channel) {
         return 0;
      }

      $updated = (int)$this->db()->update(
         $this->dd('shopChannel'),
         array('trash' => 1, 'active' => 0, 'update_date' => date('Y-m-d H:i:s')),
         'id = ' . (int)$id . ' AND trash = 0',
         0
      );
      if ($updated !== 1) {
         return 0;
      }

      $this->db()->update(
         $this->dd('shopProductChannel'),
         array('active' => 0),
         'channel_key = ' . $this->sqlValue((string)($channel['channel_key'] ?? '')),
         0
      );
      $this->clearRequestCache();
      return 1;
   }

   public function testChannelConnection(int $id): array {
      $this->install();
      $channel = $this->channelById($id);
      if (!$channel) {
         return array('ok' => false, 'message' => 'Channel wurde nicht gefunden.');
      }

      $connector = dbx()->get_include_obj('dbxShopChannelConnector', 'dbxShop');
      $result = is_object($connector) && method_exists($connector, 'test')
         ? (array)$connector->test($channel)
         : array('ok' => false, 'message' => 'Channel-Connector konnte nicht geladen werden.');

      $ok = !empty($result['ok']);
      $message = (string)($result['message'] ?? '');
      $this->saveChannelTestResult($id, $ok, $message);
      return array('ok' => $ok, 'message' => $message);
   }

   private function productHasActiveChannel(array $product, string $channelKey): bool {
      foreach ((array)($product['channels'] ?? array()) as $channel) {
         if ((string)($channel['channel_key'] ?? '') === $channelKey && (int)($channel['active'] ?? 0) === 1) {
            return true;
         }
      }
      return false;
   }

   private function productChannelRowForExport(array $product, string $channelKey): array {
      $productId = (int)($product['id'] ?? 0);
      $row = $this->db()->select1(
         $this->dd('shopProductChannel'),
         'product_id = ' . $productId . ' AND channel_key = ' . $this->sqlValue($channelKey),
         '*',
         0
      );
      if (is_array($row)) {
         return $row;
      }

      $values = array(
         'product_id' => $productId,
         'channel_key' => $channelKey,
         'active' => 1,
         'channel_sku' => (string)($product['sku'] ?? ''),
         'price_gross' => -1,
         'shipping_gross' => -1,
         'export_status' => 'ready',
      );
      $this->db()->save(
         $this->dd('shopProductChannel'),
         $values,
         'product_id = ' . $productId . ' AND channel_key = ' . $this->sqlValue($channelKey),
         0
      );
      $row = $this->db()->select1(
         $this->dd('shopProductChannel'),
         'product_id = ' . $productId . ' AND channel_key = ' . $this->sqlValue($channelKey),
         '*',
         0
      );
      return is_array($row) ? $row : $values;
   }

   public function productChannelMapping(int $productId, string $channelKey): ?array {
      $this->install();
      $product = $this->productById($productId);
      if (!$product) {
         return null;
      }
      $channel = $this->channelByKey($channelKey);
      if (!$channel) {
         return null;
      }
      $row = $this->db()->select1(
         $this->dd('shopProductChannel'),
         'product_id = ' . $productId . ' AND channel_key = ' . $this->sqlValue($channelKey),
         '*',
         0
      );
      if (!is_array($row)) {
         $row = array(
            'product_id' => $productId,
            'channel_key' => $channelKey,
            'active' => $this->productHasActiveChannel($product, $channelKey) ? 1 : 0,
            'channel_sku' => (string)($product['sku'] ?? ''),
            'price_gross' => -1,
            'shipping_gross' => -1,
            'note' => '',
         );
      }
      $note = trim((string)($row['note'] ?? ''));
      $mapping = $note !== '' ? json_decode($note, true) : array();
      if (!is_array($mapping)) {
         $mapping = array();
      }
      return array(
         'product' => $product,
         'channel' => $channel,
         'product_channel' => $row,
         'mapping' => $mapping,
      );
   }

   public function saveProductChannelMapping(int $productId, string $channelKey, array $data): void {
      $this->install();
      $product = $this->productById($productId);
      $channel = $this->channelByKey($channelKey);
      if (!$product || !$channel) {
         return;
      }

      $mapping = is_array($data['mapping'] ?? null) ? $data['mapping'] : array();
      $note = json_encode($mapping, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
      if ($note === false) {
         $note = '';
      }

      $existing = $this->productChannelRowForExport($product, $channelKey);
      $this->db()->save(
         $this->dd('shopProductChannel'),
         array(
            'product_id' => $productId,
            'channel_key' => $channelKey,
            'active' => !empty($data['active']) ? 1 : 0,
            'channel_sku' => trim((string)($data['channel_sku'] ?? $product['sku'] ?? '')),
            'price_gross' => (float)($data['price_gross'] ?? -1),
            'shipping_gross' => (float)($data['shipping_gross'] ?? -1),
            'external_listing_id' => trim((string)($data['external_listing_id'] ?? $existing['external_listing_id'] ?? '')),
            'external_offer_id' => trim((string)($data['external_offer_id'] ?? $existing['external_offer_id'] ?? '')),
            'export_status' => (string)($existing['export_status'] ?? ''),
            'export_message' => (string)($existing['export_message'] ?? ''),
            'export_payload' => (string)($existing['export_payload'] ?? ''),
            'last_export_date' => (string)($existing['last_export_date'] ?? ''),
            'note' => $note,
         ),
         'product_id = ' . $productId . ' AND channel_key = ' . $this->sqlValue($channelKey),
         0
      );
      $this->db()->update($this->dd('shopProduct'), array('update_date' => date('Y-m-d H:i:s')), 'id = ' . $productId . ' AND trash = 0', 0);
   }

   private function saveProductChannelExportResult(int $productId, string $channelKey, array $result): void {
      $payload = $result['payload'] ?? array();
      $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
      if ($payloadJson === false) {
         $payloadJson = '';
      }
      $this->db()->save(
         $this->dd('shopProductChannel'),
         array(
            'product_id' => $productId,
            'channel_key' => $channelKey,
            'active' => 1,
            'external_listing_id' => (string)($result['external_listing_id'] ?? ''),
            'external_offer_id' => (string)($result['external_offer_id'] ?? ''),
            'export_status' => (string)($result['status'] ?? (!empty($result['ok']) ? 'exported' : 'failed')),
            'export_message' => (string)($result['message'] ?? ''),
            'export_payload' => $payloadJson,
            'last_export_date' => date('Y-m-d H:i:s'),
         ),
         'product_id = ' . $productId . ' AND channel_key = ' . $this->sqlValue($channelKey),
         0
      );
      $this->db()->update($this->dd('shopProduct'), array('update_date' => date('Y-m-d H:i:s')), 'id = ' . $productId . ' AND trash = 0', 0);
   }

   public function exportProductToChannel(int $productId, string $channelKey): array {
      $this->install();
      $channelKey = trim($channelKey);
      $product = $this->productById($productId);
      if (!$product) {
         return array('ok' => false, 'status' => 'failed', 'message' => 'Artikel wurde nicht gefunden.');
      }
      $channel = $this->channelByKey($channelKey);
      if (!$channel || (int)($channel['active'] ?? 0) !== 1) {
         return array('ok' => false, 'status' => 'failed', 'message' => 'Channel ist nicht aktiv oder wurde nicht gefunden.');
      }
      if ((int)($channel['export_enabled'] ?? 0) !== 1) {
         return array('ok' => false, 'status' => 'failed', 'message' => 'Export ist fuer diesen Channel nicht aktiv.');
      }
      if (!$this->productHasActiveChannel($product, $channelKey)) {
         return array('ok' => false, 'status' => 'failed', 'message' => 'Artikel ist diesem Channel nicht aktiv zugeordnet.');
      }

      $productChannel = $this->productChannelRowForExport($product, $channelKey);
      $connector = dbx()->get_include_obj('dbxShopChannelConnector', 'dbxShop');
      $result = is_object($connector) && method_exists($connector, 'exportProduct')
         ? (array)$connector->exportProduct($channel, $product, $productChannel)
         : array('ok' => false, 'status' => 'failed', 'message' => 'Channel-Export-Connector konnte nicht geladen werden.');
      $this->saveProductChannelExportResult((int)$product['id'], $channelKey, $result);
      return $result;
   }

   public function exportProductsToChannel(array $ids, string $channelKey): array {
      $this->install();
      $ids = $this->normalizeProductIds($ids);
      $summary = array('total' => count($ids), 'ok' => 0, 'failed' => 0, 'messages' => array());
      foreach ($ids as $id) {
         $result = $this->exportProductToChannel($id, $channelKey);
         if (!empty($result['ok'])) {
            $summary['ok']++;
         } else {
            $summary['failed']++;
         }
         $message = trim((string)($result['message'] ?? ''));
         if ($message !== '') {
            $summary['messages'][] = '#' . $id . ': ' . $message;
         }
      }
      return $summary;
   }

   private function saveChannelTestResult(int $id, bool $ok, string $message): void {
      $now = date('Y-m-d H:i:s');
      $this->db()->update(
         $this->dd('shopChannel'),
         array(
            'test_status' => $ok ? 'ok' : 'error',
            'test_message' => $message,
            'last_test_date' => $now,
            'update_date' => $now,
         ),
         'id = ' . (int)$id,
         0
      );
   }

   public function shippingGroups(): array {
      $this->install();
      $rows = $this->db()->select($this->dd('shopShippingGroup'), 'trash = 0', '*', 'sorter ASC, title ASC', 'ASC', '', 0, 0, 0);
      return is_array($rows) ? $rows : array();
   }

   public function channelGroups(): array {
      $this->install();
      $groups = $this->db()->select($this->dd('shopChannelGroup'), 'trash = 0', '*', 'sorter ASC, title ASC', 'ASC', '', 0, 0, 0);
      $groups = is_array($groups) ? $groups : array();
      foreach ($groups as &$group) {
         $group['channels'] = $this->channelsForChannelGroup((int)$group['id']);
      }
      unset($group);
      return $groups;
   }

   public function channelsForChannelGroup(int $channelGroupId): array {
      $channelIndex = array();
      foreach ($this->channels() as $channel) {
         $channelIndex[(string)($channel['channel_key'] ?? '')] = $channel;
      }
      $rows = $this->db()->select($this->dd('shopChannelGroupChannel'), 'channel_group_id = ' . (int)$channelGroupId, '*', '', 'ASC', '', 0, 0, 0);
      $rows = is_array($rows) ? $rows : array();
      foreach ($rows as &$row) {
         $key = (string)($row['channel_key'] ?? '');
         $row['title'] = (string)($channelIndex[$key]['title'] ?? $key);
         $row['_sorter'] = (int)($channelIndex[$key]['sorter'] ?? 9999);
      }
      unset($row);
      usort($rows, fn($a, $b) => ((int)($a['_sorter'] ?? 9999) <=> (int)($b['_sorter'] ?? 9999))
         ?: strcasecmp((string)($a['channel_key'] ?? ''), (string)($b['channel_key'] ?? '')));
      foreach ($rows as &$row) {
         unset($row['_sorter']);
      }
      unset($row);
      return $rows;
   }

   public function updateProductGroup(int $id, array $data): void {
      $this->install();
      $parentId = max(0, (int)($data['parent_id'] ?? 0));
      if ($id > 0 && $parentId === $id) {
         $parentId = 0;
      }
      $values = array(
         'parent_id' => $parentId,
         'title' => (string)($data['title'] ?? ''),
         'description' => (string)($data['description'] ?? ''),
         'tax_class' => (string)($data['tax_class'] ?? 'mwst1'),
         'default_tax_rate' => $this->taxRateForClass((string)($data['tax_class'] ?? 'mwst1'), (float)($data['default_tax_rate'] ?? 19)),
         'display_variant' => (string)($data['display_variant'] ?? 'gallery_grid'),
         'card_template' => (string)($data['card_template'] ?? 'product-card-default'),
         'detail_template' => (string)($data['detail_template'] ?? 'product-detail-default'),
         'gallery_template' => (string)($data['gallery_template'] ?? 'image-gallery'),
         'gallery_visible_count' => max(1, (int)($data['gallery_visible_count'] ?? 3)),
         'gallery_image_size' => (string)($data['gallery_image_size'] ?? 'original'),
         'gallery_lightbox_width' => (string)($data['gallery_lightbox_width'] ?? '100vw'),
         'gallery_overflow' => (string)($data['gallery_overflow'] ?? 'grid'),
         'gallery_click' => (string)($data['gallery_click'] ?? 'lightbox'),
         'attribute_notes' => (string)($data['attribute_notes'] ?? ''),
         'ebay_category_id' => (string)($data['ebay_category_id'] ?? ''),
         'amazon_product_type' => (string)($data['amazon_product_type'] ?? ''),
         'kleinanzeigen_category_id' => (string)($data['kleinanzeigen_category_id'] ?? ''),
         'mobile_category_id' => (string)($data['mobile_category_id'] ?? ''),
         'active' => !empty($data['active']) ? 1 : 0,
         'sorter' => (int)($data['sorter'] ?? 100),
      );
      if ($id <= 0) {
         $groupKey = $this->normalizeKey((string)($data['group_key'] ?? ''));
         if ($groupKey === '') {
            $groupKey = $this->normalizeKey((string)($data['title'] ?? 'artikelgruppe'));
         }
         if ($groupKey === '') $groupKey = 'artikelgruppe';
         $groupKey = $this->uniqueProductGroupKey($groupKey);
         $values['group_key'] = $groupKey;
         $this->db()->insert($this->dd('shopProductGroup'), $values, 0);
         $this->clearRequestCache();
         return;
      }

      $this->db()->update($this->dd('shopProductGroup'), $values, 'id = ' . (int)$id, 0);
      $this->clearRequestCache();
   }

   public function updateShippingGroup(int $id, array $data): void {
      $this->install();
      $values = array(
         'title' => (string)($data['title'] ?? ''),
         'description' => (string)($data['description'] ?? ''),
         'shipping_way' => (string)($data['shipping_way'] ?? ''),
         'delivery_time' => (string)($data['delivery_time'] ?? ''),
         'shipping_gross' => (float)($data['shipping_gross'] ?? 0),
         'free_from_gross' => (float)($data['free_from_gross'] ?? -1),
         'active' => !empty($data['active']) ? 1 : 0,
         'sorter' => (int)($data['sorter'] ?? 100),
      );
      if ($id <= 0) {
         $groupKey = $this->normalizeKey((string)($data['group_key'] ?? ''));
         if ($groupKey === '') {
            $groupKey = $this->normalizeKey((string)($data['title'] ?? 'versandgruppe'));
         }
         if ($groupKey === '') $groupKey = 'versandgruppe';
         $groupKey = $this->uniqueShippingGroupKey($groupKey);
         $values['group_key'] = $groupKey;
         $this->db()->insert($this->dd('shopShippingGroup'), $values, 0);
         return;
      }

      $this->db()->update($this->dd('shopShippingGroup'), $values, 'id = ' . (int)$id, 0);
   }

   public function updateChannelGroup(int $id, array $data, array $channelKeys): void {
      $this->install();
      $values = array(
         'title' => (string)($data['title'] ?? ''),
         'description' => (string)($data['description'] ?? ''),
         'active' => !empty($data['active']) ? 1 : 0,
         'sorter' => (int)($data['sorter'] ?? 100),
      );
      if ($id <= 0) {
         $groupKey = $this->normalizeKey((string)($data['group_key'] ?? ''));
         if ($groupKey === '') {
            $groupKey = $this->normalizeKey((string)($data['title'] ?? 'channel-gruppe'));
         }
         if ($groupKey === '') $groupKey = 'channel-gruppe';
         $groupKey = $this->uniqueChannelGroupKey($groupKey);
         $values['group_key'] = $groupKey;
         if ($values['title'] === '') {
            $values['title'] = $groupKey;
         }
         $this->db()->insert($this->dd('shopChannelGroup'), $values, 0);
         $row = $this->db()->select1($this->dd('shopChannelGroup'), 'group_key = ' . $this->sqlValue($groupKey), 'id', 0);
         $id = (int)($row['id'] ?? 0);
      } else {
         $this->db()->update($this->dd('shopChannelGroup'), $values, 'id = ' . (int)$id, 0);
      }

      if ($id <= 0) {
         return;
      }
      $channels = $this->channels();
      foreach ($channels as $channel) {
         $key = (string)($channel['channel_key'] ?? '');
         if ($key === '') continue;
         $active = in_array($key, $channelKeys, true) ? 1 : 0;
         $this->db()->save(
            $this->dd('shopChannelGroupChannel'),
            array('channel_group_id' => $id, 'channel_key' => $key, 'active' => $active),
            'channel_group_id = ' . (int)$id . ' AND channel_key = ' . $this->sqlValue($key),
            0
         );
      }
   }

   public function deleteChannelGroup(int $id): int {
      $this->install();
      if ($id <= 0) {
         return 0;
      }

      $updated = (int)$this->db()->update(
         $this->dd('shopChannelGroup'),
         array('trash' => 1, 'active' => 0, 'update_date' => date('Y-m-d H:i:s')),
         'id = ' . (int)$id . ' AND trash = 0',
         0
      );
      if ($updated !== 1) {
         return 0;
      }

      $this->db()->update($this->dd('shopChannelGroupChannel'), array('active' => 0), 'channel_group_id = ' . (int)$id, 0);
      return 1;
   }

   public function deleteProductGroup(int $id): int {
      $this->install();
      if ($id <= 0) {
         return 0;
      }

      $updated = (int)$this->db()->update(
         $this->dd('shopProductGroup'),
         array('trash' => 1, 'active' => 0, 'update_date' => date('Y-m-d H:i:s')),
         'id = ' . (int)$id . ' AND trash = 0',
         0
      );
      $this->clearRequestCache();
      return $updated;
   }

   public function deleteShippingGroup(int $id): int {
      $this->install();
      if ($id <= 0) {
         return 0;
      }

      return (int)$this->db()->update(
         $this->dd('shopShippingGroup'),
         array('trash' => 1, 'active' => 0, 'update_date' => date('Y-m-d H:i:s')),
         'id = ' . (int)$id . ' AND trash = 0',
         0
      );
   }

   private function normalizeKey(string $value): string {
      $value = strtolower(trim($value));
      $value = preg_replace('~[^a-z0-9_-]+~', '-', $value);
      $value = trim((string)$value, '-_');
      return $value;
   }

   private function uniqueProductGroupKey(string $baseKey): string {
      return $this->uniqueKey('shop_product_group', $baseKey);
   }

   private function uniqueShippingGroupKey(string $baseKey): string {
      return $this->uniqueKey('shop_shipping_group', $baseKey);
   }

   private function uniqueChannelKey(string $baseKey): string {
      $key = $baseKey !== '' ? $baseKey : 'channel';
      $suffix = 2;
      while (true) {
         if ($this->db()->count($this->dd('shopChannel'), 'channel_key = ' . $this->sqlValue($key)) <= 0) return $key;
         $key = $baseKey . '-' . $suffix;
         $suffix++;
      }
   }

   private function uniqueChannelGroupKey(string $baseKey): string {
      return $this->uniqueKey('shop_channel_group', $baseKey);
   }

   private function uniqueKey(string $table, string $baseKey): string {
      $ddMap = array(
         'shop_product_group' => $this->dd('shopProductGroup'),
         'shop_shipping_group' => $this->dd('shopShippingGroup'),
         'shop_channel_group' => $this->dd('shopChannelGroup'),
      );
      if (!isset($ddMap[$table])) {
         return $baseKey;
      }
      $key = $baseKey !== '' ? $baseKey : 'gruppe';
      $suffix = 2;
      while (true) {
         if ($this->db()->count($ddMap[$table], 'group_key = ' . $this->sqlValue($key)) <= 0) return $key;
         $key = $baseKey . '-' . $suffix;
         $suffix++;
      }
   }

   public function imagesForProduct(int $productId, array $groups = array()): array {
      $this->install();
      $images = array();
      if ($productId > 0) {
         $rows = $this->db()->select(
            $this->dd('shopProductImage'),
            'trash = 0 AND active = 1 AND product_id = ' . (int)$productId,
            '*',
            'is_primary DESC, sorter ASC, title ASC',
            'ASC',
            '',
            0,
            0,
            0
         );
         $images = is_array($rows) ? $rows : array();
      }

      $groupIds = array();
      foreach ($groups as $group) {
         $id = (int)($group['id'] ?? 0);
         if ($id > 0) $groupIds[] = $id;
      }
      if ($groupIds !== array()) {
         $rows = $this->db()->select(
            $this->dd('shopProductImage'),
            'trash = 0 AND active = 1 AND group_id IN (' . implode(',', array_map('intval', $groupIds)) . ')',
            '*',
            'is_primary DESC, sorter ASC, title ASC',
            'ASC',
            '',
            0,
            0,
            0
         );
         $images = array_merge($images, is_array($rows) ? $rows : array());
      }

      $seen = array();
      $clean = array();
      foreach ($images as $image) {
         $path = (string)($image['image_path'] ?? '');
         $mediaId = (int)($image['media_id'] ?? 0);
         $key = $mediaId > 0 ? 'm:' . $mediaId : 'p:' . $path;
         if (($mediaId <= 0 && $path === '') || isset($seen[$key])) continue;
         $seen[$key] = true;
         $clean[] = $image;
      }
      return $clean;
   }

   public function productImageCounts(): array {
      $this->install();
      $counts = array();
      $products = $this->db()->select($this->dd('shopProduct'), 'trash = 0', 'id', '', 'ASC', '', 0, 0, 0);
      foreach ((is_array($products) ? $products : array()) as $product) {
         $productId = (int)($product['id'] ?? 0);
         if ($productId <= 0) continue;
         $groups = $this->groupsForProduct($productId);
         $counts[$productId] = count($this->imagesForProduct($productId, $groups));
      }
      return $counts;
   }

   public function allImages(): array {
      $this->install();
      $rows = $this->db()->select($this->dd('shopProductImage'), 'trash = 0', '*', 'active DESC, product_id DESC, group_id DESC, sorter ASC, title ASC', 'ASC', '', 0, 0, 0);
      $rows = is_array($rows) ? $rows : array();

      $productIds = array_values(array_unique(array_filter(array_map(
         static fn($row) => (int)($row['product_id'] ?? 0),
         $rows
      ))));
      $groupIds = array_values(array_unique(array_filter(array_map(
         static fn($row) => (int)($row['group_id'] ?? 0),
         $rows
      ))));
      $productById = array();
      if ($productIds !== array()) {
         $products = $this->db()->select(
            $this->dd('shopProduct'),
            'id IN (' . implode(',', array_map('intval', $productIds)) . ')',
            'id,title',
            '',
            'ASC',
            '',
            0,
            0,
            0
         );
         $productById = $this->rowsById(is_array($products) ? $products : array());
      }
      $groupById = array();
      if ($groupIds !== array()) {
         $groups = $this->db()->select(
            $this->dd('shopProductGroup'),
            'id IN (' . implode(',', array_map('intval', $groupIds)) . ')',
            'id,title',
            '',
            'ASC',
            '',
            0,
            0,
            0
         );
         $groupById = $this->rowsById(is_array($groups) ? $groups : array());
      }

      foreach ($rows as &$row) {
         $productId = (int)($row['product_id'] ?? 0);
         $groupId = (int)($row['group_id'] ?? 0);
         $row['product_title'] = (string)($productById[$productId]['title'] ?? '');
         $row['group_title'] = (string)($groupById[$groupId]['title'] ?? '');
      }
      unset($row);
      return $rows;
   }

   public function updateImageMediaReference(int $imageId, int $mediaId, string $imagePath = ''): void {
      $this->install();
      $imageId = max(0, $imageId);
      $mediaId = max(0, $mediaId);
      if ($imageId <= 0 || $mediaId <= 0) {
         return;
      }

      $imagePath = trim(str_replace('\\', '/', $imagePath));
      if ($imagePath === '') {
         $imagePath = 'dbxmedia:' . $mediaId;
      }

      $this->db()->update(
         $this->dd('shopProductImage'),
         array('media_id' => $mediaId, 'image_path' => $imagePath),
         'id = ' . (int)$imageId,
         0
      );
   }

   public function saveImage(int $productId, int $groupId, string $imagePath, string $title, string $alt, int $isPrimary = 0, int $sorter = 100): void {
      $this->install();
      $productId = max(0, $productId);
      $groupId = max(0, $groupId);
      $imagePath = trim(str_replace('\\', '/', $imagePath));
      if ($imagePath === '' || ($productId <= 0 && $groupId <= 0)) {
         return;
      }
      if ($groupId > 0 && $productId <= 0) {
         $this->db()->update($this->dd('shopProductImage'), array('active' => 0, 'trash' => 1), 'group_id = ' . (int)$groupId . ' AND product_id = 0 AND trash = 0', 0);
         $isPrimary = 1;
      }
      $this->db()->save(
         $this->dd('shopProductImage'),
         array(
            'product_id' => $productId,
            'group_id' => $groupId,
            'image_path' => $imagePath,
            'title' => $title,
            'alt' => $alt,
            'is_primary' => $isPrimary,
            'active' => 1,
            'sorter' => $sorter,
         ),
         'product_id = ' . (int)$productId . ' AND group_id = ' . (int)$groupId . ' AND image_path = ' . $this->sqlValue($imagePath),
         0
      );
   }

   public function saveMediaImage(int $productId, int $groupId, int $mediaId, string $title = '', string $alt = '', int $isPrimary = 0, int $sorter = 100): ?array {
      $this->install();
      $productId = max(0, $productId);
      $groupId = max(0, $groupId);
      $mediaId = max(0, $mediaId);
      if ($mediaId <= 0 || ($productId <= 0 && $groupId <= 0)) {
         return null;
      }
      if ($groupId > 0 && $productId <= 0) {
         $this->db()->update($this->dd('shopProductImage'), array('active' => 0, 'trash' => 1), 'group_id = ' . (int)$groupId . ' AND product_id = 0 AND trash = 0', 0);
         $isPrimary = 1;
      }

      $pathKey = 'dbxmedia:' . $mediaId;
      $this->db()->save(
         $this->dd('shopProductImage'),
         array(
            'product_id' => $productId,
            'group_id' => $groupId,
            'media_id' => $mediaId,
            'image_path' => $pathKey,
            'title' => $title,
            'alt' => $alt,
            'is_primary' => $isPrimary,
            'active' => 1,
            'sorter' => $sorter,
         ),
         'product_id = ' . (int)$productId . ' AND group_id = ' . (int)$groupId . ' AND image_path = ' . $this->sqlValue($pathKey),
         0
      );

      $data = $this->db()->select1(
         $this->dd('shopProductImage'),
         'product_id = ' . (int)$productId . ' AND group_id = ' . (int)$groupId . ' AND image_path = ' . $this->sqlValue($pathKey),
         '*',
         0
      );
      return is_array($data) ? $data : null;
   }

   public function primaryImageForGroup(int $groupId): ?array {
      $this->install();
      if ($groupId <= 0) {
         return null;
      }
      $rows = $this->db()->select(
         $this->dd('shopProductImage'),
         'trash = 0 AND active = 1 AND product_id = 0 AND group_id = ' . (int)$groupId,
         '*',
         'is_primary DESC, sorter ASC, title ASC',
         'ASC',
         '',
         0,
         1,
         0
      );
      $row = is_array($rows) && isset($rows[0]) ? $rows[0] : array();
      return is_array($row) ? $row : null;
   }

   public function removeProductImageAssociation(int $imageId, int $productId = 0): bool {
      $this->install();
      $imageId = max(0, $imageId);
      $productId = max(0, $productId);
      if ($imageId <= 0) {
         return false;
      }

      $where = 'id = ' . (int)$imageId;
      if ($productId > 0) {
         $groupMaps = $this->db()->select($this->dd('shopProductGroupMap'), 'product_id = ' . (int)$productId, 'group_id', '', 'ASC', '', 0, 0, 0);
         $groupIds = array();
         foreach ((is_array($groupMaps) ? $groupMaps : array()) as $groupMap) {
            $groupId = (int)($groupMap['group_id'] ?? 0);
            if ($groupId > 0) $groupIds[$groupId] = $groupId;
         }
         $parts = array('product_id = ' . (int)$productId);
         if ($groupIds !== array()) {
            $parts[] = 'group_id IN (' . implode(',', array_map('intval', $groupIds)) . ')';
         }
         $where .= ' AND (' . implode(' OR ', $parts) . ')';
      }

      if ($this->db()->count($this->dd('shopProductImage'), $where) <= 0) {
         return false;
      }
      $this->db()->update($this->dd('shopProductImage'), array('active' => 0, 'trash' => 1), $where, 0);
      return true;
   }

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
      $seconds = (int)dbx()->get_config('dbxShop', 'payment_processing_retry_seconds', 300);
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

   public function nextInvoiceNo(): string {
      $prefix = 'R' . date('Y');
      $rows = $this->db()->select($this->dd('shopOrder'), 'invoice_no LIKE ' . $this->sqlValue($prefix . '%'), 'invoice_no', 'invoice_no DESC', 'DESC', '', 1, 0, 0);
      $last = is_array($rows) && isset($rows[0]['invoice_no']) ? (string)$rows[0]['invoice_no'] : '';
      $next = 1;
      if (preg_match('/(\d+)$/', $last, $m)) {
         $next = ((int)$m[1]) + 1;
      }
      return $prefix . '-' . str_pad((string)$next, 5, '0', STR_PAD_LEFT);
   }

   private function invoiceArchiveDir(string $year): string {
      $base = rtrim(dbx()->get_base_dir(), '/\\');
      return $base . '/files/shop/invoices/' . $year;
   }

   private function invoiceArchiveRelPath(string $year, string $fileName): string {
      return 'files/shop/invoices/' . $year . '/' . $fileName;
   }

   private function pdfText(string $text): string {
      $text = str_replace(array("\r\n", "\r"), "\n", $text);
      $text = strtr($text, array(
         '€' => 'EUR',
         '„' => '"',
         '“' => '"',
         '’' => "'",
         '–' => '-',
         '—' => '-',
      ));
      $converted = function_exists('iconv') ? @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text) : false;
      if ($converted !== false) {
         $text = $converted;
      }
      return str_replace(array('\\', '(', ')'), array('\\\\', '\\(', '\\)'), $text);
   }

   private function createSimpleInvoicePdf(array $order, string $absFile): bool {
      $invoiceNo = trim((string)($order['invoice_no'] ?? ''));
      $invoiceDate = trim((string)($order['invoice_date'] ?? date('Y-m-d')));
      $lines = array(
         'Rechnung ' . $invoiceNo,
         'Datum: ' . $invoiceDate,
         'Bestellung: ' . (string)($order['order_no'] ?? ''),
         '',
         'Kunde:',
         (string)($order['customer_name'] ?? ''),
         (string)($order['customer_email'] ?? ''),
      );
      $address = trim((string)($order['shipping_address'] ?? ''));
      if ($address !== '') {
         $lines[] = '';
         $lines[] = 'Lieferadresse:';
         foreach (explode("\n", str_replace("\r", '', $address)) as $line) {
            $lines[] = $line;
         }
      }
      $lines[] = '';
      $lines[] = 'Positionen:';
      foreach ((array)($order['items'] ?? array()) as $item) {
         $title = trim((string)($item['title'] ?? 'Artikel'));
         $sku = trim((string)($item['sku'] ?? ''));
         $qty = (int)($item['qty'] ?? 0);
         $total = number_format((float)($item['total_gross'] ?? 0), 2, ',', '.') . ' EUR';
         $tax = number_format((float)($item['tax_rate'] ?? 0), 2, ',', '.') . ' % MwSt.';
         $lines[] = $qty . ' x ' . $title . ($sku !== '' ? ' [' . $sku . ']' : '') . ' - ' . $total . ' (' . $tax . ')';
      }
      $lines[] = '';
      $lines[] = 'Gesamtbetrag: ' . number_format((float)($order['total_gross'] ?? 0), 2, ',', '.') . ' EUR';
      $lines[] = '';
      $lines[] = 'Dieser Beleg wurde aus dem gespeicherten Bestell-Snapshot erzeugt.';

      $content = "BT\n/F1 18 Tf\n50 790 Td\n(" . $this->pdfText(array_shift($lines) ?: 'Rechnung') . ") Tj\n/F1 10 Tf\n0 -24 Td\n";
      foreach ($lines as $line) {
         foreach (explode("\n", wordwrap((string)$line, 95, "\n", true)) as $wrapped) {
            $content .= '(' . $this->pdfText($wrapped) . ") Tj\n0 -14 Td\n";
         }
      }
      $content .= "ET\n";

      $objects = array();
      $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
      $objects[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
      $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>";
      $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
      $objects[] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";

      $pdf = "%PDF-1.4\n";
      $offsets = array(0);
      foreach ($objects as $i => $object) {
         $offsets[$i + 1] = strlen($pdf);
         $pdf .= ($i + 1) . " 0 obj\n" . $object . "\nendobj\n";
      }
      $xref = strlen($pdf);
      $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
      $pdf .= "0000000000 65535 f \n";
      for ($i = 1; $i <= count($objects); $i++) {
         $pdf .= str_pad((string)$offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
      }
      $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";

      $dir = dirname($absFile);
      if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
         return false;
      }
      return @file_put_contents($absFile, $pdf) !== false;
   }

   public function ensureOrderInvoicePdf(int $id): ?array {
      $this->install();
      $order = $this->orderById($id);
      if (!is_array($order)) {
         return null;
      }

      $invoiceNo = trim((string)($order['invoice_no'] ?? ''));
      $invoiceDate = trim((string)($order['invoice_date'] ?? ''));
      $updates = array();
      if ($invoiceNo === '') {
         $invoiceNo = $this->nextInvoiceNo();
         $updates['invoice_no'] = $invoiceNo;
      }
      if ($invoiceDate === '') {
         $invoiceDate = date('Y-m-d');
         $updates['invoice_date'] = $invoiceDate;
      }
      if ($updates !== array()) {
         $updates['update_date'] = date('Y-m-d H:i:s');
         $this->db()->update($this->dd('shopOrder'), $updates, 'id = ' . (int)$id . ' AND trash = 0', 0);
         $order = $this->orderById($id) ?: $order;
      }

      $year = substr($invoiceDate, 0, 4);
      if (!preg_match('/^\d{4}$/', $year)) {
         $year = date('Y');
      }
      $safeNo = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $invoiceNo) ?: ('rechnung-' . $id);
      $fileName = $safeNo . '.pdf';
      $relPath = $this->invoiceArchiveRelPath($year, $fileName);
      $absPath = $this->invoiceArchiveDir($year) . '/' . $fileName;
      $oldRelPath = trim((string)($order['invoice_pdf_path'] ?? ''));
      $created = false;

      if (!is_file($absPath)) {
         if (!$this->createSimpleInvoicePdf($order, $absPath)) {
            return null;
         }
         $created = true;
      }

      $this->db()->update($this->dd('shopOrder'), array(
         'invoice_pdf_path' => $relPath,
         'invoice_pdf_date' => date('Y-m-d H:i:s'),
         'update_date' => date('Y-m-d H:i:s'),
      ), 'id = ' . (int)$id . ' AND trash = 0', 0);
      if ($created || $oldRelPath !== $relPath) {
         $this->addOrderHistory($id, 'invoice_pdf', '', $relPath, 'Rechnungs-PDF wurde erzeugt oder aktualisiert.');
      }
      return $this->orderById($id);
   }

   public function invoicePdfAbsolutePath(array $order): string {
      $rel = trim((string)($order['invoice_pdf_path'] ?? ''));
      if ($rel === '') {
         return '';
      }
      $base = rtrim(dbx()->get_base_dir(), '/\\');
      $path = $base . '/' . ltrim($rel, '/\\');
      return is_file($path) ? $path : '';
   }

   public function saveWithdrawal(array $data): ?array {
      $this->install();
      $orderNo = trim((string)($data['order_no'] ?? ''));
      $order = $orderNo !== '' ? $this->orderByNo($orderNo) : null;
      $orderId = is_array($order) ? (int)($order['id'] ?? 0) : 0;
      $ok = (int)$this->db()->insert($this->dd('shopWithdrawal'), array(
         'order_id' => $orderId,
         'order_no' => $orderNo,
         'customer_name' => trim((string)($data['customer_name'] ?? '')),
         'customer_email' => trim((string)($data['customer_email'] ?? '')),
         'customer_address' => trim((string)($data['customer_address'] ?? '')),
         'reason' => trim((string)($data['reason'] ?? '')),
         'status' => 'new',
      ), 0);
      if ($ok !== 1) {
         return null;
      }
      $id = (int)$this->db()->get_insert_id();
      if ($orderId > 0) {
         $this->addOrderHistory($orderId, 'withdrawal', '', 'new', 'Widerruf wurde eingereicht.');
      }
      return $this->withdrawalById($id);
   }

   public function updateWithdrawalAdmin(int $id, string $status, string $note = ''): bool {
      $this->install();
      $allowed = array('new', 'processing', 'accepted', 'rejected', 'refunded', 'closed');
      if (!in_array($status, $allowed, true)) {
         return false;
      }
      $before = $this->withdrawalById($id);
      if (!is_array($before)) {
         return false;
      }

      $ok = $this->db()->update($this->dd('shopWithdrawal'), array(
         'status' => $status,
         'admin_note' => $note !== '' ? $note : (string)($before['admin_note'] ?? ''),
         'update_date' => date('Y-m-d H:i:s'),
      ), 'id = ' . (int)$id . ' AND trash = 0', 0);

      $orderId = (int)($before['order_id'] ?? 0);
      if ($orderId > 0) {
         $old = (string)($before['status'] ?? '');
         if ($old !== $status) {
            $this->addOrderHistory($orderId, 'withdrawal_status', $old, $status, 'Widerrufsstatus wurde geaendert.');
         }
         if (in_array($status, array('accepted', 'refunded'), true)) {
            $order = $this->orderById($orderId);
            if (is_array($order)) {
               $this->releaseStockForOrder($order, 'Bestand wurde durch Widerruf zurueckgebucht.');
            }
         }
      }

      return $ok !== 0 || $this->withdrawalById($id) !== null;
   }

   public function withdrawalById(int $id): ?array {
      $row = $this->db()->select1($this->dd('shopWithdrawal'), 'id = ' . (int)$id . ' AND trash = 0', '*', 0);
      return is_array($row) ? $row : null;
   }

   public function withdrawals(array $filters = array(), int $limit = 50, int $offset = 0): array {
      $this->install();
      $where = array('trash = 0');
      $query = trim((string)($filters['query'] ?? ''));
      if ($query !== '') {
         $like = $this->sqlLikeValue($query);
         $where[] = '(order_no LIKE ' . $like . ' OR customer_name LIKE ' . $like . ' OR customer_email LIKE ' . $like . ')';
      }
      $status = trim((string)($filters['status'] ?? ''));
      if ($status !== '') {
         $where[] = 'status = ' . $this->sqlValue($status);
      }
      $rows = $this->db()->select($this->dd('shopWithdrawal'), implode(' AND ', $where), '*', 'create_date DESC, id DESC', 'ASC', '', $limit > 0 ? $limit : 0, max(0, $offset), 0);
      return is_array($rows) ? $rows : array();
   }

   public function withdrawalCount(array $filters = array()): int {
      $where = array('trash = 0');
      $query = trim((string)($filters['query'] ?? ''));
      if ($query !== '') {
         $like = $this->sqlLikeValue($query);
         $where[] = '(order_no LIKE ' . $like . ' OR customer_name LIKE ' . $like . ' OR customer_email LIKE ' . $like . ')';
      }
      $status = trim((string)($filters['status'] ?? ''));
      if ($status !== '') {
         $where[] = 'status = ' . $this->sqlValue($status);
      }
      return max(0, (int)$this->db()->count($this->dd('shopWithdrawal'), implode(' AND ', $where)));
   }

   public function withdrawalsForOrder(int $orderId): array {
      if ($orderId <= 0) {
         return array();
      }
      $rows = $this->db()->select($this->dd('shopWithdrawal'), 'order_id = ' . (int)$orderId . ' AND trash = 0', '*', 'create_date DESC, id DESC', 'ASC', '', 0, 0, 0);
      return is_array($rows) ? $rows : array();
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
?>
