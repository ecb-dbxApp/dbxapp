<?php
namespace dbx\dbxShop;

trait dbxShopRepositoryInstallSyncServiceTrait {

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
      if ((string)dbx()->get_cfg('dbxShop', 'schema_sync_version', '') === $version) {
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

      $cfg = dbx()->get_cfg('dbxShop', '', array());
      $cfg = is_array($cfg) ? $cfg : array();
      $cfg['schema_sync_version'] = $version;
      dbx()->set_cfg('dbxShop', $cfg);
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
}
