<?php
namespace dbx\dbxShop_admin;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

/**
 * Tokenisierte Produkt-, Medien- und Baumaktionen ueber Repository/CMS-Endpunkte.
 *
 * Zustandslose Quelltext-Komposition: keine zusaetzliche Instanz, kein Proxy
 * und keine zusaetzlichen Datenbank- oder Templatezugriffe.
 */
trait dbxShopAdminProductActionServiceTrait {


   private function productChannelExportStatusHtml(array $productChannel, $texts): string {
      $status = trim((string)($productChannel['export_status'] ?? ''));
      $message = trim((string)($productChannel['export_message'] ?? ''));
      $listing = trim((string)($productChannel['external_listing_id'] ?? ''));
      $offer = trim((string)($productChannel['external_offer_id'] ?? ''));
      $date = trim((string)($productChannel['last_export_date'] ?? ''));
      $html = '<div class="d-flex flex-wrap gap-2 align-items-center">';
      $html .= '<span class="badge text-bg-' . ($status === 'failed' ? 'danger' : ($status !== '' ? 'info' : 'secondary')) . '">' . $this->h($status !== '' ? $status : $texts->get_fd_message('mapping_not_exported')) . '</span>';
      if ($date !== '') $html .= '<span class="text-muted small">' . $this->h($date) . '</span>';
      if ($listing !== '') $html .= '<code>Listing: ' . $this->h($listing) . '</code>';
      if ($offer !== '') $html .= '<code>Offer: ' . $this->h($offer) . '</code>';
      $html .= '</div>';
      if ($message !== '') {
         $html .= '<div class="alert alert-secondary py-2 mt-2 mb-0">' . $this->h($message) . '</div>';
      }
      return $html;
   }



   /**
    * Prüft eine Shop-Admin-Kartenaktion über den zugehörigen dbxForm-Kontext.
    *
    * Mehrere Verwaltungsseiten rendern eine dbxForm-Karte je Datensatz. Die
    * Mutation wird am Seitenanfang verarbeitet; deshalb wird hier anhand von
    * Aktion und Datensatz dieselbe stabile Formular-ID rekonstruiert. Erst ein
    * gültiger, sessiongebundener Submit darf den Repository-Aufruf erreichen.
    * Für Speichern-Aktionen werden zusätzlich die im Formular sichtbaren
    * FD-Felder durch die normale dbxForm-/dbxValidator-Pipeline geprüft.
    */
   private function posted(string $action): bool {
      if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
         || (string)($_POST['shop_action'] ?? '') !== $action) {
         return false;
      }

      $id = max(0, (int)($_POST['id'] ?? 0));
      $suffix = $id > 0 ? (string)$id : 'new';
      $contract = array();

      switch ($action) {
         case 'save_channel_mapping':
            $productId = max(0, (int)($_POST['product_id'] ?? 0));
            $channelKey = trim((string)($_POST['channel_key_ref'] ?? ''));
            if ($productId <= 0 || !preg_match('/^[A-Za-z0-9_-]+$/', $channelKey)) {
               return false;
            }
            $contract = array(
               'fid' => 'shop-product-channel-mapping-' . $productId . '-' . $channelKey,
               'fd' => 'dbxShop|shop-product-channel',
               'fields' => array('active', 'channel_sku', 'price_gross', 'shipping_gross', 'external_listing_id', 'external_offer_id'),
            );
            break;

         case 'save_product_group':
            $contract = array(
               'fid' => 'shop-product-group-' . $suffix,
               'fd' => 'dbxShop|shop-product-group',
               'fields' => array('group_key', 'parent_id', 'title', 'description', 'tax_class', 'card_template', 'detail_template', 'gallery_template', 'gallery_visible_count', 'gallery_image_size', 'gallery_lightbox_width', 'gallery_overflow', 'gallery_click', 'attribute_notes', 'ebay_category_id', 'amazon_product_type', 'kleinanzeigen_category_id', 'mobile_category_id', 'sorter', 'active'),
            );
            break;

         case 'delete_product_group':
            $contract = array('fid' => 'shop-product-group-' . $suffix, 'fd' => '', 'fields' => array());
            break;

         case 'save_attribute_definition':
            $contract = array(
               'fid' => 'shop-attribute-definition-' . $suffix,
               'fd' => 'dbxShop|shop-attribute-definition',
               'fields' => array('group_id', 'attr_key', 'title', 'input_type', 'unit', 'options', 'required', 'filterable', 'comparable', 'sorter', 'active'),
            );
            break;

         case 'save_product_attributes':
            $productId = max(0, (int)($_POST['product_id'] ?? 0));
            if ($productId <= 0) return false;
            $contract = array('fid' => 'shop-product-attributes-' . $productId, 'fd' => '', 'fields' => array());
            break;

         case 'save_shipping_group':
            $contract = array(
               'fid' => 'shop-shipping-group-' . $suffix,
               'fd' => 'dbxShop|shop-shipping-group',
               'fields' => array('group_key', 'title', 'description', 'shipping_way', 'delivery_time', 'shipping_gross', 'free_from_gross', 'sorter', 'active'),
            );
            break;

         case 'delete_shipping_group':
            $contract = array('fid' => 'shop-shipping-group-' . $suffix, 'fd' => '', 'fields' => array());
            break;

         case 'save_channel_group':
            $contract = array(
               'fid' => 'shop-channel-group-' . $suffix,
               'fd' => 'dbxShop|shop-channel-group',
               'fields' => array('group_key', 'title', 'description', 'sorter', 'active'),
            );
            break;

         case 'delete_channel_group':
            $contract = array('fid' => 'shop-channel-group-' . $suffix, 'fd' => '', 'fields' => array());
            break;

         case 'save_channel':
         case 'test_channel':
            $contract = array(
               'fid' => 'shop-channel-form-' . $suffix,
               'fd' => 'dbxShop|shop-channel',
               'fields' => array(),
               'all_fields' => true,
            );
            break;

         case 'delete_channel':
            $contract = array('fid' => 'shop-channel-form-' . $suffix, 'fd' => '', 'fields' => array());
            break;

         default:
            // Nicht mehr gerenderte Legacy-Aktionen (z. B. alter Medienupload)
            // dürfen ohne expliziten dbxForm-Vertrag keine Mutation auslösen.
            return false;
      }

      // get_system_obj lädt die Klasse; eine eigene Instanz verhindert, dass
      // der Prüflauf den später gerenderten Karten-Formzustand überschreibt.
      dbx()->get_system_obj('dbxForm');
      $form = new \dbxForm();
      $form->init((string)$contract['fid']);
      if ((string)($contract['fd'] ?? '') !== '') {
         $form->_fd = (string)$contract['fd'];
      }
      if (!empty($contract['all_fields'])) {
         $form->add_flds();
      } else {
         foreach ((array)($contract['fields'] ?? array()) as $field) {
            // Nicht gerenderte optionale Felder werden nicht künstlich als
            // leer validiert. Vorhandene Werte durchlaufen ihre FD-Regeln.
            if (array_key_exists($field, $_POST)) {
               $form->add_fld($field);
            }
         }
      }

      if (!$form->submit()) {
         $this->postedFormError = 'Die Sicherheitsprüfung des Formulars ist fehlgeschlagen. Bitte laden Sie die Seite neu und versuchen Sie es erneut.';
         dbx()->sys_msg(
            'security',
            'dbxShop_admin',
            $action,
            'Shop-Admin-Formular abgewiesen',
            'fid=' . (string)$contract['fid'] . ' reason=token ip=' . (string)($_SERVER['REMOTE_ADDR'] ?? '')
         );
         return false;
      }

      if ($form->errors()) {
         $this->postedFormError = 'Bitte prüfen Sie die markierten beziehungsweise erforderlichen Eingaben.';
         dbx()->sys_msg(
            'security',
            'dbxShop_admin',
            $action,
            'Shop-Admin-Formular abgewiesen',
            'fid=' . (string)$contract['fid'] . ' reason=validation ip=' . (string)($_SERVER['REMOTE_ADDR'] ?? '')
         );
         return false;
      }

      if ($action === 'save_product_attributes') {
         foreach ((array)($_POST['attr_value'] ?? array()) as $value) {
            if (is_array($value) || mb_strlen((string)$value) > 255) {
               $this->postedFormError = 'Ein Attributwert ist ungültig oder länger als 255 Zeichen.';
               return false;
            }
         }
      }

      return true;
   }



   private function cmsEndpoint(string $run1, array $params = array(), bool $mutating = false): string {
      $url = '?dbx_modul=dbxContent_admin&dbx_run1=' . rawurlencode($run1);
      foreach ($params as $key => $value) {
         $url .= '&' . rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
      }
      if ($mutating) {
         $url .= '&dbx_token=' . rawurlencode(dbx()->action_token('dbxContent_admin.actions'));
      }
      return $url;
   }



   private function shopEndpoint(string $run1, array $params = array(), bool $mutating = false): string {
      $url = '?dbx_modul=dbxShop_admin&dbx_run1=' . rawurlencode($run1);
      foreach ($params as $key => $value) {
         $url .= '&' . rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
      }
      return $mutating ? $this->actionUrl($url) : $url;
   }



   private function readJsonPayload(): array {
      $raw = (string)file_get_contents('php://input');
      $data = $raw !== '' ? json_decode($raw, true) : null;
      if (is_array($data)) {
         return $data;
      }
      return $_POST;
   }



   private function ensureCmsShopMediaFolder(): void {
      $dir = rtrim((string)dbx()->get_file_dir(), '/\\') . '/media/img/shop';
      if (!is_dir($dir)) {
         @mkdir($dir, 0775, true);
      }
   }



   private function jsonExit(array $data): string {
      if (!headers_sent()) {
         header('Content-Type: application/json; charset=utf-8');
      }
      echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
   }



   private function assignMedia(): string {
      if (!$this->checkActionToken('assign_media')) {
         return $this->jsonExit(array('ok' => 0, 'msg' => $this->postedFormError));
      }
      $payload = $this->readJsonPayload();
      $productId = (int)($payload['product_id'] ?? 0);
      $groupId = (int)($payload['group_id'] ?? 0);
      $mediaId = (int)($payload['media_id'] ?? $payload['id'] ?? 0);
      if ($mediaId <= 0 || ($productId <= 0 && $groupId <= 0)) {
         return $this->jsonExit(array('ok' => 0, 'msg' => 'Bitte Artikel oder Artikelgruppe und ein Medium auswaehlen.'));
      }

      $row = $this->repo()->saveMediaImage(
         $productId,
         $groupId,
         $mediaId,
         (string)($payload['title'] ?? ''),
         (string)($payload['alt'] ?? $payload['title'] ?? ''),
         !empty($payload['is_primary']) ? 1 : 0,
         (int)($payload['sorter'] ?? 100)
      );
      if (!$row) {
         return $this->jsonExit(array('ok' => 0, 'msg' => 'Bild konnte nicht zugeordnet werden.'));
      }
      $this->syncShopMediaUsage();
      return $this->jsonExit(array(
         'ok' => 1,
         'image' => $row,
         'url' => $this->mediaItemUrl($row, true),
      ));
   }



   private function productTreeMove(): string {
      if (!$this->checkActionToken('product_tree_move')) {
         return $this->jsonExit(array('ok' => 0, 'msg' => $this->postedFormError));
      }
      $this->ensureSeed();
      $payload = $this->readJsonPayload();
      $type = (string)($payload['type'] ?? '');
      $targetGroupId = (int)($payload['target_group_id'] ?? 0);

      if ($type === 'product') {
         $productId = (int)($payload['product_id'] ?? 0);
         if ($productId <= 0 || $targetGroupId <= 0) {
            return $this->jsonExit(array('ok' => 0, 'msg' => 'Artikel und Zielgruppe sind erforderlich.'));
         }
         $count = $this->repo()->setProductGroupForProducts(array($productId), $targetGroupId);
         if ($count <= 0) {
            return $this->jsonExit(array('ok' => 0, 'msg' => 'Artikel konnte nicht verschoben werden.'));
         }
         return $this->jsonExit(array('ok' => 1, 'msg' => 'Artikelgruppe wurde zugeordnet.'));
      }

      if ($type === 'group') {
         $groupId = (int)($payload['group_id'] ?? 0);
         if ($groupId <= 0) {
            return $this->jsonExit(array('ok' => 0, 'msg' => 'Artikelgruppe ist erforderlich.'));
         }
         if (!$this->repo()->moveProductGroupParent($groupId, $targetGroupId)) {
            return $this->jsonExit(array('ok' => 0, 'msg' => 'Artikelgruppe konnte nicht verschoben werden. Pruefen Sie, ob dadurch ein Kreis entstehen wuerde.'));
         }
         return $this->jsonExit(array('ok' => 1, 'msg' => 'Artikelgruppe wurde verschoben.'));
      }

      return $this->jsonExit(array('ok' => 0, 'msg' => 'Unbekannte Drag-Drop-Aktion.'));
   }
}
