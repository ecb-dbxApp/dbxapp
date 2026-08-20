<?php
namespace dbx\dbxShop_admin;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

/**
 * Vererbte Provider-/Channel-Mappings und normalisierte Formularwerte.
 *
 * Zustandslose Quelltext-Komposition: keine zusaetzliche Instanz, kein Proxy
 * und keine zusaetzlichen Datenbank- oder Templatezugriffe.
 */
trait dbxShopAdminProductMappingServiceTrait {


   private function mapping_lines_to_map(string $lines): array {
      $map = array();
      foreach (preg_split('/\R/', $lines) ?: array() as $line) {
         $line = trim((string)$line);
         if ($line === '' || strpos($line, '=') === false) {
            continue;
         }
         [$key, $value] = array_map('trim', explode('=', $line, 2));
         if ($key !== '' && $value !== '') {
            $map[$key] = $value;
         }
      }
      return $map;
   }



   private function mapping_map_to_lines(array $map): string {
      $lines = array();
      foreach ($map as $key => $value) {
         if (is_array($value)) {
            $value = implode('|', array_map('strval', $value));
         }
         $lines[] = (string)$key . '=' . (string)$value;
      }
      return implode("\n", $lines);
   }



   private function ebay_category_options(array $mapping, array $channel, array $product, $texts): array {
      $options = array();
      $add = function(string $value, string $label) use (&$options) {
         $value = trim($value);
         if ($value === '' || isset($options[$value])) {
            return;
         }
         $options[$value] = $label;
      };

      $current = (string)($mapping['category_id'] ?? '');
      $channel_default = (string)($channel['category_id'] ?? '');
      $group_default = (string)($this->product_group_channel_mapping_defaults('ebay', $product)['category_id'] ?? '');
      $add($current, $texts->format_fd_message('mapping_current_selection', array('value' => $current)));
      $add($group_default, $texts->format_fd_message('mapping_group_default', array('value' => $group_default)));
      $add($channel_default, $texts->format_fd_message('mapping_channel_default', array('value' => $channel_default)));

      $configured = dbx()->get_cfg('dbxShop', 'ebay_category_options');
      if (is_array($configured)) {
         foreach ($configured as $value => $label) {
            $add((string)$value, (string)$label);
         }
      } else {
         foreach (preg_split('/\R/', (string)$configured) ?: array() as $line) {
            $line = trim($line);
            if ($line === '') {
               continue;
            }
            if (strpos($line, '=') !== false) {
               [$value, $label] = array_map('trim', explode('=', $line, 2));
               $add($value, $label !== '' ? $label : $value);
            } else {
               $add($line, $line);
            }
         }
      }

      $product_category = strtolower((string)($product['category'] ?? '') . ' ' . (string)($product['product_type'] ?? ''));
      if (strpos($product_category, 'software') !== false || strpos($product_category, 'digital') !== false) {
         $add('58058', '58058 - ' . $texts->get_fd_message('mapping_category_software'));
      }

      $add('58058', '58058 - ' . $texts->get_fd_message('mapping_category_software'));
      $add('11450', '11450 - ' . $texts->get_fd_message('mapping_category_clothing'));
      $add('293', '293 - ' . $texts->get_fd_message('mapping_category_electronics'));
      $add('220', '220 - ' . $texts->get_fd_message('mapping_category_home'));
      $add('12576', '12576 - ' . $texts->get_fd_message('mapping_category_business'));

      return $options;
   }



   private function ebay_category_input(array $mapping, array $channel, array $product, $texts): string {
      $value = (string)($mapping['category_id'] ?? $channel['category_id'] ?? '');
      $html = '<div class="col-md-4"><label class="form-label">' . $this->h($texts->get_fd_message('mapping_ebay_category')) . '</label>'
         . '<select class="form-select form-select-sm" name="mapping_category_id">';
      foreach ($this->ebay_category_options($mapping, $channel, $product, $texts) as $option_value => $label) {
         $html .= '<option value="' . $this->h($option_value) . '"' . ((string)$option_value === $value ? ' selected' : '') . '>' . $this->h($label) . '</option>';
      }
      $html .= '</select>'
         . '<div class="form-text">' . $this->h($texts->get_fd_message('mapping_ebay_category_hint')) . '</div>'
         . '</div>';
      return $html;
   }



   private function product_group_channel_mapping_defaults(string $platform, array $product): array {
      $group_id = (int)($product['product_group_id'] ?? 0);
      if ($group_id <= 0) {
         return array();
      }
      $group = $this->repo()->group_by_id($group_id);
      if (!is_array($group)) {
         return array();
      }

      if ($platform === 'ebay') {
         $category = trim((string)($group['ebay_category_id'] ?? ''));
         return $category !== '' ? array('category_id' => $category) : array();
      }
      if ($platform === 'amazon') {
         $product_type = trim((string)($group['amazon_product_type'] ?? ''));
         return $product_type !== '' ? array('productType' => $product_type) : array();
      }
      if ($platform === 'kleinanzeigen') {
         $category = trim((string)($group['kleinanzeigen_category_id'] ?? ''));
         return $category !== '' ? array('category_id' => $category) : array();
      }
      if ($platform === 'mobile') {
         $category = trim((string)($group['mobile_category_id'] ?? ''));
         return $category !== '' ? array('mobile_vehicle' => array('category' => $category)) : array();
      }

      return array();
   }



   private function channel_mapping_inherited_defaults(string $platform, array $channel, array $product): array {
      $defaults = array();
      if ($platform === 'ebay') {
         $defaults = array(
            'category_id' => trim((string)($channel['category_id'] ?? '')),
            'payment_policy_id' => trim((string)($channel['payment_policy_id'] ?? '')),
            'fulfillment_policy_id' => trim((string)($channel['fulfillment_policy_id'] ?? '')),
            'return_policy_id' => trim((string)($channel['return_policy_id'] ?? '')),
         );
      } elseif ($platform === 'amazon') {
         $category = trim((string)($channel['category_id'] ?? ''));
         if (stripos($category, 'productType:') === 0) {
            $category = trim(substr($category, strlen('productType:')));
         }
         if (strpos($category, '/') !== false) {
            $category = trim((string)explode('/', $category)[0]);
         }
         $defaults = array('productType' => strtoupper($category));
      } elseif ($platform === 'kleinanzeigen') {
         $defaults = array(
            'category_id' => trim((string)($channel['category_id'] ?? '')),
            'location' => trim((string)($channel['location_key'] ?? '')),
         );
      } elseif ($platform === 'mobile') {
         $category = trim((string)($channel['category_id'] ?? ''));
         $defaults = array('mobile_vehicle' => array('category' => $category));
      }

      $defaults = $this->merge_mapping_defaults($defaults, $this->product_group_channel_mapping_defaults($platform, $product));
      return $this->clean_empty_mapping_values($defaults);
   }



   private function merge_mapping_defaults(array $defaults, array $mapping): array {
      foreach ($defaults as $key => $value) {
         if (is_array($value)) {
            $current = is_array($mapping[$key] ?? null) ? $mapping[$key] : array();
            $mapping[$key] = $this->merge_mapping_defaults($value, $current);
            continue;
         }
         if (!array_key_exists($key, $mapping) || trim((string)$mapping[$key]) === '') {
            $mapping[$key] = $value;
         }
      }
      return $mapping;
   }



   private function clean_empty_mapping_values(array $mapping): array {
      foreach ($mapping as $key => $value) {
         if (is_array($value)) {
            $value = $this->clean_empty_mapping_values($value);
            if ($value === array()) {
               unset($mapping[$key]);
            } else {
               $mapping[$key] = $value;
            }
            continue;
         }
         if (trim((string)$value) === '') {
            unset($mapping[$key]);
         }
      }
      return $mapping;
   }



   private function remove_inherited_mapping_defaults(array $mapping, array $defaults): array {
      foreach ($defaults as $key => $default_value) {
         if (!array_key_exists($key, $mapping)) {
            continue;
         }
         if (is_array($default_value)) {
            $current = is_array($mapping[$key]) ? $mapping[$key] : array();
            $mapping[$key] = $this->remove_inherited_mapping_defaults($current, $default_value);
            if ($mapping[$key] === array()) {
               unset($mapping[$key]);
            }
            continue;
         }
         if (trim((string)$mapping[$key]) === trim((string)$default_value)) {
            unset($mapping[$key]);
         }
      }
      return $this->clean_empty_mapping_values($mapping);
   }



   private function provider_mapping_html(string $platform, array $mapping, array $channel, array $product, $texts): string {
      $input = function(string $name, string $label, string $value = '', string $placeholder = '', string $class = 'col-md-4'): string {
         return '<div class="' . $class . '"><label class="form-label">' . $this->h($label) . '</label><input class="form-control form-control-sm" name="mapping_' . $this->h($name) . '" value="' . $this->h($value) . '" placeholder="' . $this->h($placeholder) . '"></div>';
      };
      $textarea = function(string $name, string $label, string $value = '', string $placeholder = '', int $rows = 4, string $class = 'col-12'): string {
         return '<div class="' . $class . '"><label class="form-label">' . $this->h($label) . '</label><textarea class="form-control form-control-sm" rows="' . $rows . '" name="mapping_' . $this->h($name) . '" placeholder="' . $this->h($placeholder) . '">' . $this->h($value) . '</textarea></div>';
      };

      $html = '<div class="row g-3">';
      if ($platform === 'ebay') {
         $condition = (string)($mapping['condition'] ?? 'NEW');
         $aspects = is_array($mapping['aspects'] ?? null) ? $this->mapping_map_to_lines($mapping['aspects']) : '';
         $location_key = trim((string)($channel['location_key'] ?? ''));
         $html .= $this->ebay_category_input($mapping, $channel, $product, $texts);
         $html .= '<div class="col-md-4"><label class="form-label">' . $this->h($texts->get_fd_message('mapping_location_key')) . '</label>'
            . '<input class="form-control form-control-sm" value="' . $this->h($location_key) . '" placeholder="' . $this->h($texts->get_fd_message('mapping_location_placeholder')) . '" readonly>'
            . '<div class="form-text">' . $this->h($texts->get_fd_message('mapping_location_hint')) . '</div></div>';
         $html .= '<div class="col-md-4"><label class="form-label">' . $this->h($texts->get_fd_message('mapping_condition')) . '</label><select class="form-select form-select-sm" name="mapping_condition">'
            . '<option value="NEW"' . ($condition === 'NEW' ? ' selected' : '') . '>' . $this->h($texts->get_fd_message('mapping_condition_new')) . '</option>'
            . '<option value="USED_EXCELLENT"' . ($condition === 'USED_EXCELLENT' ? ' selected' : '') . '>' . $this->h($texts->get_fd_message('mapping_condition_used_excellent')) . '</option>'
            . '<option value="USED_GOOD"' . ($condition === 'USED_GOOD' ? ' selected' : '') . '>' . $this->h($texts->get_fd_message('mapping_condition_used_good')) . '</option>'
            . '</select></div>';
         $html .= $input('payment_policy_id', 'Payment-Policy-ID', (string)($mapping['payment_policy_id'] ?? $channel['payment_policy_id'] ?? ''), 'policy_payment_1234567890');
         $html .= $input('fulfillment_policy_id', 'Fulfillment-Policy-ID', (string)($mapping['fulfillment_policy_id'] ?? $channel['fulfillment_policy_id'] ?? ''), 'policy_fulfillment_1234567890');
         $html .= $input('return_policy_id', 'Return-Policy-ID', (string)($mapping['return_policy_id'] ?? $channel['return_policy_id'] ?? ''), 'policy_return_1234567890');
         $html .= $textarea('aspects', $texts->get_fd_message('mapping_ebay_aspects'), $aspects, "brand=dbxApp\ncolor=black\nsize=L", 5);
      } elseif ($platform === 'amazon') {
         $simple = is_array($mapping['simple_attributes'] ?? null) ? $this->mapping_map_to_lines($mapping['simple_attributes']) : '';
         $html .= $input('productType', 'Amazon Product Type', (string)($mapping['productType'] ?? $mapping['product_type'] ?? ''), 'SOFTWARE / PRODUCT / SHIRT');
         $html .= '<div class="col-md-4"><label class="form-label">' . $this->h($texts->get_fd_message('mapping_requirements')) . '</label><select class="form-select form-select-sm" name="mapping_requirements">'
            . '<option value="LISTING"' . ((string)($mapping['requirements'] ?? 'LISTING') === 'LISTING' ? ' selected' : '') . '>' . $this->h($texts->get_fd_message('mapping_requirements_listing')) . '</option>'
            . '<option value="LISTING_PRODUCT_ONLY"' . ((string)($mapping['requirements'] ?? '') === 'LISTING_PRODUCT_ONLY' ? ' selected' : '') . '>' . $this->h($texts->get_fd_message('mapping_requirements_product')) . '</option>'
            . '<option value="LISTING_OFFER_ONLY"' . ((string)($mapping['requirements'] ?? '') === 'LISTING_OFFER_ONLY' ? ' selected' : '') . '>' . $this->h($texts->get_fd_message('mapping_requirements_offer')) . '</option>'
            . '</select></div>';
         $html .= $input('brand', $texts->get_fd_message('mapping_brand'), (string)($mapping['simple_attributes']['brand'] ?? 'dbxApp'), 'dbxApp');
         $html .= $textarea('simple_attributes', $texts->get_fd_message('mapping_amazon_attributes'), $simple, "manufacturer=dbxApp\nitem_type_keyword=software\nrecommended_browse_nodes=123456", 6);
         $html .= '<div class="col-12"><div class="form-text">' . $this->h($texts->get_fd_message('mapping_amazon_hint')) . '</div></div>';
      } elseif ($platform === 'mobile') {
         $vehicle = is_array($mapping['mobile_vehicle'] ?? null) ? $mapping['mobile_vehicle'] : array();
         $html .= $input('vehicle_make', $texts->get_fd_message('mapping_brand'), (string)($vehicle['make'] ?? ''), 'Volkswagen');
         $html .= $input('vehicle_model', $texts->get_fd_message('mapping_vehicle_model'), (string)($vehicle['model'] ?? ''), 'Golf');
         $html .= $input('vehicle_first_registration', $texts->get_fd_message('mapping_vehicle_registration'), (string)($vehicle['firstRegistration'] ?? ''), '2023-05');
         $html .= $input('vehicle_mileage', $texts->get_fd_message('mapping_vehicle_mileage'), (string)($vehicle['mileage'] ?? ''), '25000');
         $html .= $input('vehicle_fuel', $texts->get_fd_message('mapping_vehicle_fuel'), (string)($vehicle['fuel'] ?? ''), 'PETROL');
         $html .= $input('vehicle_power', $texts->get_fd_message('mapping_vehicle_power'), (string)($vehicle['power'] ?? ''), '110');
         $html .= $input('vehicle_category', $texts->get_fd_message('mapping_vehicle_category'), (string)($vehicle['category'] ?? $channel['category_id'] ?? ''), 'car');
         $html .= $textarea('vehicle_extra', $texts->get_fd_message('mapping_vehicle_extra'), is_array($mapping['vehicle_extra'] ?? null) ? $this->mapping_map_to_lines($mapping['vehicle_extra']) : '', "gearbox=MANUAL\nemissionClass=EURO6", 5);
         $html .= '<div class="col-12"><div class="form-text">' . $this->h($texts->get_fd_message('mapping_mobile_hint')) . '</div></div>';
      } elseif ($platform === 'kleinanzeigen') {
         $attrs = is_array($mapping['attributes'] ?? null) ? $this->mapping_map_to_lines($mapping['attributes']) : '';
         $html .= $input('category_id', $texts->get_fd_message('mapping_classified_category'), (string)($mapping['category_id'] ?? $channel['category_id'] ?? ''), 'category_12345');
         $html .= $input('location', $texts->get_fd_message('mapping_place'), (string)($mapping['location'] ?? ''), '10115 Berlin');
         $html .= $input('contact_name', $texts->get_fd_message('mapping_contact_name'), (string)($mapping['contact_name'] ?? ''), 'Muster GmbH');
         $html .= $input('phone', $texts->get_fd_message('mapping_phone'), (string)($mapping['phone'] ?? ''), '+49...');
         $html .= $textarea('attributes', $texts->get_fd_message('mapping_classified_attributes'), $attrs, "condition=new\nshipping=yes\ncolor=black", 5);
         $html .= '<div class="col-12"><div class="form-text">' . $this->h($texts->get_fd_message('mapping_classified_hint')) . '</div></div>';
      } else {
         $attrs = is_array($mapping['attributes'] ?? null) ? $this->mapping_map_to_lines($mapping['attributes']) : '';
         $html .= $input('endpoint_action', $texts->get_fd_message('mapping_endpoint'), (string)($mapping['endpoint_action'] ?? ''), 'products.upsert');
         $html .= $textarea('attributes', $texts->get_fd_message('mapping_middleware_attributes'), $attrs, "external_category=123\nbrand=dbxApp", 6);
      }
      $html .= '</div>';
      return $html;
   }



   private function collect_product_channel_mapping(string $platform, array $defaults = array()): array {
      if ($platform === 'ebay') {
         return array(
            'category_id' => trim((string)($_POST['mapping_category_id'] ?? '')),
            'condition' => trim((string)($_POST['mapping_condition'] ?? 'NEW')),
            'payment_policy_id' => trim((string)($_POST['mapping_payment_policy_id'] ?? '')),
            'fulfillment_policy_id' => trim((string)($_POST['mapping_fulfillment_policy_id'] ?? '')),
            'return_policy_id' => trim((string)($_POST['mapping_return_policy_id'] ?? '')),
            'aspects' => $this->mapping_lines_to_map((string)($_POST['mapping_aspects'] ?? '')),
         );
      }
      if ($platform === 'amazon') {
         $simple = $this->mapping_lines_to_map((string)($_POST['mapping_simple_attributes'] ?? ''));
         $brand = trim((string)($_POST['mapping_brand'] ?? ''));
         if ($brand !== '') {
            $simple['brand'] = $brand;
         }
         return array(
            'productType' => trim((string)($_POST['mapping_productType'] ?? '')),
            'requirements' => trim((string)($_POST['mapping_requirements'] ?? 'LISTING')),
            'simple_attributes' => $simple,
         );
      }
      if ($platform === 'mobile') {
         $vehicle = array(
            'make' => trim((string)($_POST['mapping_vehicle_make'] ?? '')),
            'model' => trim((string)($_POST['mapping_vehicle_model'] ?? '')),
            'firstRegistration' => trim((string)($_POST['mapping_vehicle_first_registration'] ?? '')),
            'mileage' => trim((string)($_POST['mapping_vehicle_mileage'] ?? '')),
            'fuel' => trim((string)($_POST['mapping_vehicle_fuel'] ?? '')),
            'power' => trim((string)($_POST['mapping_vehicle_power'] ?? '')),
            'category' => trim((string)($_POST['mapping_vehicle_category'] ?? '')),
         );
         $vehicle = array_filter($vehicle, fn($value) => trim((string)$value) !== '');
         return array(
            'mobile_vehicle' => $vehicle,
            'vehicle_extra' => $this->mapping_lines_to_map((string)($_POST['mapping_vehicle_extra'] ?? '')),
         );
      }
      if ($platform === 'kleinanzeigen') {
         return array(
            'category_id' => trim((string)($_POST['mapping_category_id'] ?? '')),
            'location' => trim((string)($_POST['mapping_location'] ?? '')),
            'contact_name' => trim((string)($_POST['mapping_contact_name'] ?? '')),
            'phone' => trim((string)($_POST['mapping_phone'] ?? '')),
            'attributes' => $this->mapping_lines_to_map((string)($_POST['mapping_attributes'] ?? '')),
         );
      }
      return array(
         'endpoint_action' => trim((string)($_POST['mapping_endpoint_action'] ?? '')),
         'attributes' => $this->mapping_lines_to_map((string)($_POST['mapping_attributes'] ?? '')),
      );
   }



   private function normalize_decimal_input($value): string {
      $value = str_replace(',', '.', trim((string)$value));
      if ($value === '') {
         return '';
      }
      return number_format((float)$value, 2, '.', '');
   }



   private function channel_inherited_decimal_value(string $posted_name): string {
      $value = $this->normalize_decimal_input($_POST[$posted_name] ?? '');
      $inherited = (string)($_POST[$posted_name . '_inherited'] ?? '') === '1';
      $inherited_value = $this->normalize_decimal_input($_POST[$posted_name . '_inherited_value'] ?? '');
      if ($value === '') {
         return '-1';
      }
      if ($inherited && $inherited_value !== '' && $value === $inherited_value) {
         return '-1';
      }
      return $value;
   }



   private function channel_mapping_display_values(array $product, array $product_channel): array {
      $stored_price = (float)($product_channel['price_gross'] ?? -1);
      $stored_shipping = (float)($product_channel['shipping_gross'] ?? -1);
      $inherited_price = number_format((float)($product['price_gross'] ?? 0), 2, '.', '');
      $inherited_shipping = number_format((float)($product['effective_shipping_gross'] ?? $product['shipping_gross'] ?? 0), 2, '.', '');
      $price_inherited = $stored_price < 0;
      $shipping_inherited = $stored_shipping < 0;

      return array(
         'price_gross' => $price_inherited ? $inherited_price : number_format($stored_price, 2, '.', ''),
         'shipping_gross' => $shipping_inherited ? $inherited_shipping : number_format($stored_shipping, 2, '.', ''),
         'price_gross_inherited' => $price_inherited ? '1' : '0',
         'price_gross_inherited_value' => $inherited_price,
         'shipping_gross_inherited' => $shipping_inherited ? '1' : '0',
         'shipping_gross_inherited_value' => $inherited_shipping,
      );
   }



   private function product_channel_mapping(): string {
      $this->ensure_seed();
      dbx()->get_system_obj('dbxForm', 'use');
      $texts = new \dbxForm();
      $texts->init('shop-product-channel-mapping-texts');
      $texts->set_field_definition('dbxShop|shop-product-channel');
      $texts->load_fd_messages();
      $texts->set_form_help_enabled(false);
      $product_id = (int)dbx()->get_modul_var('id', 0, 'int');
      $channel_key = trim((string)dbx()->get_modul_var('channel', '', 'parameter'));
      if ($product_id <= 0 || $channel_key === '') {
         return $this->frame('<div class="alert alert-warning m-3">' . $this->h($texts->get_fd_message('mapping_missing')) . '</div>', $texts->get_fd_message('mapping_title'));
      }

      $state = $this->repo()->product_channel_mapping($product_id, $channel_key);
      if (!$state) {
         return $this->frame('<div class="alert alert-warning m-3">' . $this->h($texts->get_fd_message('mapping_not_found')) . '</div>', $texts->get_fd_message('mapping_title'));
      }

      $product = (array)$state['product'];
      $channel = (array)$state['channel'];
      $product_channel = (array)$state['product_channel'];
      $mapping = (array)$state['mapping'];
      $platform = (string)($channel['platform_type'] ?? 'custom');
      $mapping_defaults = $this->channel_mapping_inherited_defaults($platform, $channel, $product);
      $mapping = $this->merge_mapping_defaults($mapping_defaults, $mapping);
      $message = '';

      if ($this->posted('save_channel_mapping')) {
         $save_product_id = (int)($_POST['product_id'] ?? $product_id);
         $save_channel_key = trim((string)($_POST['channel_key_ref'] ?? $channel_key));
         if ($save_product_id === $product_id && $save_channel_key === $channel_key) {
            $this->repo()->save_product_channel_mapping($product_id, $channel_key, array(
               'active' => !empty($_POST['active']) ? 1 : 0,
               'channel_sku' => (string)($_POST['channel_sku'] ?? ''),
               'price_gross' => $this->channel_inherited_decimal_value('price_gross'),
               'shipping_gross' => $this->channel_inherited_decimal_value('shipping_gross'),
               'external_listing_id' => (string)($_POST['external_listing_id'] ?? ''),
               'external_offer_id' => (string)($_POST['external_offer_id'] ?? ''),
               'mapping' => $this->remove_inherited_mapping_defaults($this->collect_product_channel_mapping($platform, $mapping_defaults), $mapping_defaults),
            ));
            $message = $texts->get_fd_message('mapping_saved');
            $state = $this->repo()->product_channel_mapping($product_id, $channel_key) ?: $state;
            $product_channel = (array)$state['product_channel'];
            $mapping = (array)$state['mapping'];
            $mapping_defaults = $this->channel_mapping_inherited_defaults($platform, $channel, $product);
            $mapping = $this->merge_mapping_defaults($mapping_defaults, $mapping);
         }
      }

      $display_values = $this->channel_mapping_display_values($product, $product_channel);
      $form = dbx()->get_system_obj('dbxForm');
      $form->init('shop-product-channel-mapping-' . $product_id . '-' . $channel_key, 'shop-product-channel-mapping');
      $form->set_data_source('dbxShop|shopProductChannel', 'dbxShop|shop-product-channel');
      $form->load_fd_messages();
      $form->set_data(array_merge($product_channel + array(
         'product_id' => $product_id,
         'channel_key' => $channel_key,
         'active' => 1,
         'channel_sku' => (string)($product['sku'] ?? ''),
         'price_gross' => -1,
         'shipping_gross' => -1,
      ), array(
         'price_gross' => $display_values['price_gross'],
         'shipping_gross' => $display_values['shipping_gross'],
      )));
      $form->set_rid((int)($product_channel['id'] ?? 0));
      $form->set_action('?dbx_modul=dbxShop_admin&dbx_run1=product_channel_mapping&id=' . $product_id . '&channel=' . rawurlencode($channel_key));
      $form->set_activ_id((int)($product_channel['id'] ?? 0));
      $form->_msg_info = '';
      $form->add_rep('shop_admin_style', $this->shop_admin_style());
      $form->add_rep('bar_class', 'dbx-bar--module');
      $form->add_rep('bar_title_class', 'dbx-bar-title');
      $form->add_rep('bar_actions_class', 'dbx-bar-actions');
      $form->add_rep('bar_icon', 'bi-sliders2');
      $form->add_rep('bar_title', $this->h($texts->get_fd_message('mapping_title')));
      $form->add_rep('bar_subtitle', $this->h((string)($product['sku'] ?? '') . ' - ' . (string)($channel['title'] ?? $channel_key)));
      $form->add_rep('bar_actions', $this->help_button($this->shop_product_channel_mapping_help_context(), $texts->get_fd_message('mapping_help'), 'btn btn-outline-secondary btn-sm')
         . '<button class="btn btn-primary btn-sm" type="submit" data-dbx-tooltip="' . $this->h($texts->get_fd_message('mapping_save')) . '"><i class="bi bi-save"></i> ' . $this->h($texts->get_fd_message('mapping_save')) . '</button>'
         . '<a class="btn btn-outline-primary btn-sm dbxConfirm" href="' . $this->h($this->action_url('?dbx_modul=dbxShop_admin&dbx_run1=product_edit&id=' . $product_id . '&export_channel=' . rawurlencode($channel_key))) . '" data-confirm-title="<i class=\'bi bi-broadcast\'></i> ' . $this->h($texts->get_fd_message('mapping_export_title')) . '" data-confirm="' . $this->h($texts->get_fd_message('mapping_export_confirm')) . '" data-confirm-buttons="yesno" title="' . $this->h($texts->get_fd_message('mapping_export_label')) . '"><i class="bi bi-broadcast"></i></a>');
      $form->add_rep('product_id', (string)$product_id);
      $form->add_rep('channel_key', $this->h($channel_key));
      $form->add_rep('price_gross_inherited', $display_values['price_gross_inherited']);
      $form->add_rep('price_gross_inherited_value', $display_values['price_gross_inherited_value']);
      $form->add_rep('shipping_gross_inherited', $display_values['shipping_gross_inherited']);
      $form->add_rep('shipping_gross_inherited_value', $display_values['shipping_gross_inherited_value']);
      $form->add_rep('mapping_message', $message !== '' ? '<div class="alert alert-success mb-3">' . $this->h($message) . '</div>' : '');
      $form->add_rep('mapping_intro', $this->h($texts->get_fd_message('mapping_intro')));
      $form->add_rep('mapping_values_title', $this->h($texts->get_fd_message('mapping_values_title')));
      $form->add_rep('mapping_export_status_title', $this->h($texts->get_fd_message('mapping_export_status_title')));
      $form->add_rep('provider_title', $this->h(($channel['title'] ?? $channel_key) . ' Mapping'));
      $form->add_rep('export_status_view', $this->product_channel_export_status_html($product_channel, $texts));
      $form->add_fld('active');
      $form->add_fld('channel_sku', placeholder: (string)($product['sku'] ?? ''));
      $form->add_fld('price_gross', placeholder: $display_values['price_gross']);
      $form->add_fld('shipping_gross', placeholder: $display_values['shipping_gross']);
      $form->add_fld('external_listing_id');
      $form->add_fld('external_offer_id');
      $form->add_obj('provider_mapping', 'obj-value', $this->provider_mapping_html($platform, $mapping, $channel, $product, $texts));
      return $form->run();
   }
}
