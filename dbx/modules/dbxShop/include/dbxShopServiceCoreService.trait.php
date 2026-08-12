<?php
namespace dbx\dbxShop;

trait dbxShopServiceCoreServiceTrait {

   /**
    * Lädt den sprachabhängigen Meldungsvertrag einer UI-FD ohne Formular-Init.
    */
   private function texts(string $fd): \dbxForm {
      if (isset($this->textForms[$fd])) {
         return $this->textForms[$fd];
      }

      dbx()->get_system_obj('dbxForm', 'use');
      $form = new \dbxForm();
      $form->set_form_help_enabled(false);
      $form->_fd = $fd;
      $form->load_fd_messages();
      $this->textForms[$fd] = $form;
      return $form;
   }

   private function tpl() {
      return dbx()->get_system_obj('dbxTPL');
   }

   private function repo(): dbxShopRepository {
      return dbx()->get_include_obj('dbxShopRepository', 'dbxShop');
   }

   private function paypal(): dbxShopPayPal {
      return dbx()->get_include_obj('dbxShopPayPal', 'dbxShop');
   }

   private function amazonPay(): dbxShopAmazonPay {
      return dbx()->get_include_obj('dbxShopAmazonPay', 'dbxShop');
   }

   private function h($value): string {
      return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
   }

   private function readJsonArray($value): array {
      $data = json_decode((string)$value, true);
      return is_array($data) ? $data : array();
   }

   private function money($value): string {
      $language = strtolower(
         substr((string)dbx()->get_system_var('dbx_lng', 'de'), 0, 2)
      );
      return ($language === 'en'
         ? number_format((float)$value, 2, '.', ',')
         : number_format((float)$value, 2, ',', '.')) . ' EUR';
   }

   private function shopConfig(): array {
      $cfg = dbx()->get_cfg('dbxShop');
      return is_array($cfg) ? $cfg : array();
   }

   private function settingsBool(array $cfg, string $key, bool $default = false): bool {
      if (!array_key_exists($key, $cfg)) {
         return $default;
      }
      $value = $cfg[$key];
      if (is_bool($value)) return $value;
      return in_array(strtolower(trim((string)$value)), array('1', 'true', 'yes', 'on'), true);
   }

   private function shopStyle(): string {
      $file = dirname(__DIR__) . '/design/css/shop.css';
      if (!is_file($file)) {
         return '';
      }
      return '<style>' . file_get_contents($file) . '</style>';
   }

   private function demoShopNoticeHtml(string $id = '', string $extraClass = '', $texts = null): string {
      if (!$this->settingsBool($this->shopConfig(), 'demo_notice_enabled', true)) {
         return '';
      }
      $texts = $texts ?: $this->texts('dbxShop|shop-catalog-filter-form');
      $idAttribute = $id !== '' ? ' id="' . $this->h($id) . '"' : '';
      $classAttribute = $extraClass !== '' ? ' ' . $this->h($extraClass) : '';
      return '<div' . $idAttribute . ' class="alert alert-danger dbx-shop-demo-alert' . $classAttribute . '" role="alert">'
         . '<strong><i class="bi bi-exclamation-octagon-fill"></i> '
         . $this->h($texts->get_fd_message('demo_title'))
         . '</strong><br>'
         . $this->h($texts->get_fd_message('demo_message'))
         . '</div>';
   }

   private function page(string $title, string $subtitle, string $body, string $active = 'catalog'): string {
      return $this->tpl()->get_tpl('dbxShop|start', array(
         'shop_style' => $this->shopStyle(),
         'title' => $this->h($title),
         'subtitle' => $this->h($subtitle),
         'body' => $body,
         'active_catalog' => $active === 'catalog' ? 'active' : '',
         'active_cart' => $active === 'cart' ? 'active' : '',
         'active_checkout' => $active === 'checkout' ? 'active' : '',
         'active_orders' => $active === 'orders' ? 'active' : '',
         'active_legal' => $active === 'legal' ? 'active' : '',
         'active_withdrawal' => $active === 'withdrawal' ? 'active' : '',
      ));
   }

   private function jsonResponse(array $data, int $status = 200): string {
      if (!headers_sent()) {
         http_response_code($status);
         header('Content-Type: application/json; charset=utf-8');
      }
      return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
   }
}
