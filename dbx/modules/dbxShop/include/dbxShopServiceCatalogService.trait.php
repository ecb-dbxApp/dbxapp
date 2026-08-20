<?php
namespace dbx\dbxShop;

require_once __DIR__ . '/dbxShopSearch.class.php';
require_once __DIR__ . '/dbxShopMediaUrl.class.php';

trait dbxShopServiceCatalogServiceTrait {

   private function ensure_seed(): void {
      // Der oeffentliche GET-Pfad darf keine Demo- oder Wartungsdaten
      // anlegen. Seed und Migration werden im Admin explizit ausgefuehrt.
      $this->repo()->install();
   }

   private function active_channel(): string {
      return 'shop';
   }


   private function product_has_channel(array $product, string $channel): bool {
      foreach (($product['channels'] ?? array()) as $ch) {
         if ((string)($ch['channel_key'] ?? '') === $channel && (int)($ch['active'] ?? 0) === 1) {
            return true;
         }
      }
      return false;
   }

   private function groups_html(array $product): string {
      $html = '';
      foreach (($product['groups'] ?? array()) as $group) {
         $group_id = (int)($group['id'] ?? 0);
         $href = $group_id > 0 ? '?dbx_modul=dbxShop&amp;dbx_run1=catalog&amp;group=' . $group_id : '';
         $label = $this->h($group['title'] ?? '');
         $html .= $href !== ''
            ? '<a class="dbx-shop-chip" href="' . $href . '">' . $label . '</a>'
            : '<span class="dbx-shop-chip">' . $label . '</span>';
      }
      return $html;
   }

   private function catalog_group_id(): int {
      return max(0, (int)dbx()->get_modul_var('group', 0, 'int'));
   }

   private function group_image_url(array $group): string {
      $image = $this->repo()->primary_image_for_group((int)($group['id'] ?? 0));
      if (is_array($image)) {
         $url = dbxShopMediaUrl::item($image, true);
         if ($url !== '') {
            return $url;
         }
      }
      return dbxShopMediaUrl::path('files/shop/img/software-dashboard.svg');
   }

   private function catalog_group_breadcrumb(int $group_id): string {
      if ($group_id <= 0) {
         return '';
      }
      $path = $this->repo()->group_path($group_id);
      if ($path === array()) {
         return '';
      }
      $texts = $this->texts('dbxShop|shop-catalog-filter-form');
      $html = '<nav class="dbx-shop-group-breadcrumb" aria-label="'
         . $this->h($texts->get_fd_message('groups_aria')) . '">';
      $html .= '<a href="?dbx_modul=dbxShop&amp;dbx_run1=catalog">'
         . $this->h($texts->get_fd_message('all_products')) . '</a>';
      foreach ($path as $group) {
         $id = (int)($group['id'] ?? 0);
         $title = $this->h($group['title'] ?? '');
         if ($id === $group_id) {
            $html .= '<span>' . $title . '</span>';
         } else {
            $html .= '<a href="?dbx_modul=dbxShop&amp;dbx_run1=catalog&amp;group=' . $id . '">' . $title . '</a>';
         }
      }
      $html .= '</nav>';
      return $html;
   }

   private function catalog_group_navigation(int $parent_id): string {
      $groups = $this->repo()->groups_by_parent($parent_id, true);
      if ($groups === array()) {
         return '';
      }
      $texts = $this->texts('dbxShop|shop-catalog-filter-form');
      $html = '<section class="dbx-shop-group-grid" aria-label="'
         . $this->h($texts->get_fd_message('groups_aria')) . '">';
      foreach ($groups as $group) {
         $id = (int)($group['id'] ?? 0);
         if ($id <= 0) continue;
         $title = trim((string)(
            $group['title'] ?? $texts->get_fd_message('group_fallback')
         ));
         $description = trim((string)($group['description'] ?? ''));
         $html .= '<a class="dbx-shop-group-card" href="?dbx_modul=dbxShop&amp;dbx_run1=catalog&amp;group=' . $id . '">';
         $html .= '<span class="dbx-shop-group-card-image"><img src="' . $this->h($this->group_image_url($group)) . '" alt="' . $this->h($title) . '" loading="lazy"></span>';
         $html .= '<span class="dbx-shop-group-card-body"><strong>' . $this->h($title) . '</strong>';
         if ($description !== '') {
            $html .= '<small>' . $this->h($description) . '</small>';
         }
         $html .= '</span></a>';
      }
      $html .= '</section>';
      return $html;
   }

   private function product_in_catalog_group(array $product, int $group_id): bool {
      if ($group_id <= 0) {
         return true;
      }
      if ((int)($product['product_group_id'] ?? 0) === $group_id) {
         return true;
      }
      foreach (($product['groups'] ?? array()) as $group) {
         if ((int)($group['id'] ?? 0) === $group_id) {
            return true;
         }
      }
      return false;
   }



   private function group_text(array $product): string {
      $parts = array();
      foreach (($product['groups'] ?? array()) as $group) {
         $parts[] = (string)($group['title'] ?? '');
         $parts[] = (string)($group['group_key'] ?? '');
         $parts[] = (string)($group['description'] ?? '');
         $parts[] = (string)($group['attribute_notes'] ?? '');
      }
      return implode(' ', $parts);
   }




   private function product_search_score(array $product, string $query): int {
      $terms = dbxShopSearch::terms($query);
      if ($terms === array()) {
         return 1;
      }

      $primary = dbxShopSearch::normalized_text(implode(' ', array(
         (string)($product['sku'] ?? ''),
         (string)($product['title'] ?? ''),
         (string)($product['category'] ?? ''),
         (string)($product['badge'] ?? ''),
         (string)($product['product_type'] ?? ''),
      )));
      $secondary = dbxShopSearch::normalized_text(implode(' ', array(
         (string)($product['summary'] ?? ''),
         (string)($product['description'] ?? ''),
      )));
      $attributes = dbxShopSearch::normalized_text(dbxShopValue::attribute_text($product));
      $groups = dbxShopSearch::normalized_text($this->group_text($product));

      $score = 0;
      $matched = 0;
      $first_term_primary_score = 0;
      $term_count = count($terms);

      foreach ($terms as $idx => $term) {
         $primary_score = dbxShopSearch::field_score($primary, $term, 10);
         $term_score = max(
            $primary_score,
            dbxShopSearch::field_score($attributes, $term, 7),
            dbxShopSearch::field_score($secondary, $term, 4),
            dbxShopSearch::field_score($groups, $term, 3)
         );

         if ($idx === 0) {
            $first_term_primary_score = $primary_score;
         }
         if ($term_score > 0) {
            $matched++;
            $score += $term_score;
         }
      }

      if ($matched === 0) {
         return 0;
      }
      if ($term_count === 1) {
         return $score;
      }

      if ($matched === $term_count || $first_term_primary_score > 0 || $score >= 20) {
         return $score + ($matched * 3);
      }

      return 0;
   }

   private function attributes_inline_html(array $product, int $max = 4): string {
      $html = '';
      $count = 0;
      foreach (($product['attributes'] ?? array()) as $attribute) {
         $value = trim((string)($attribute['display_value'] ?? $attribute['value_text'] ?? ''));
         if ($value === '') continue;
         $html .= '<span class="dbx-shop-attribute-chip"><span>' . $this->h($attribute['title'] ?? '') . '</span><strong>' . $this->h($value) . '</strong></span>';
         $count++;
         if ($count >= $max) break;
      }
      return $html !== '' ? '<div class="dbx-shop-attribute-row">' . $html . '</div>' : '';
   }

   private function attributes_table_html(array $product): string {
      $texts = $this->texts('dbxShop|shop-catalog-filter-form');
      $rows = '';
      foreach (($product['attributes'] ?? array()) as $attribute) {
         $value = trim((string)($attribute['display_value'] ?? $attribute['value_text'] ?? ''));
         if ($value === '') continue;
         $rows .= '<tr><th>' . $this->h($attribute['title'] ?? '') . '</th><td>' . $this->h($value) . '</td></tr>';
      }
      if ($rows === '') {
         return '';
      }
      return '<div class="dbx-shop-attributes"><h4>'
         . $this->h($texts->get_fd_message('attributes_heading'))
         . '</h4><table><tbody>' . $rows . '</tbody></table></div>';
   }

   private function selected_attribute_filters(): array {
      $raw = $_GET['attr'] ?? array();
      if (!is_array($raw)) {
         return array();
      }
      $out = array();
      foreach ($raw as $id => $value) {
         $id = (int)$id;
         $value = trim((string)$value);
         if ($id > 0 && $value !== '') {
            $out[$id] = $value;
         }
      }
      return $out;
   }


   private function product_matches_attribute_filters(array $product, array $filters): bool {
      if ($filters === array()) {
         return true;
      }
      $values = array();
      foreach (($product['attributes'] ?? array()) as $attribute) {
         $id = (int)($attribute['id'] ?? 0);
         if ($id <= 0) continue;
         $values[$id] = dbxShopSearch::normalized_text((string)($attribute['value_text'] ?? ''));
      }
      foreach ($filters as $id => $value) {
         if (!isset($values[$id]) || $values[$id] !== dbxShopSearch::normalized_text((string)$value)) {
            return false;
         }
      }
      return true;
   }

   private function catalog_filters_html(string $channel, string $query, array $selected, int $group_id = 0): string {
      $texts = $this->texts('dbxShop|shop-catalog-filter-form');
      $filter_fields = '';
      foreach ($this->repo()->attribute_filter_definitions() as $definition) {
         $id = (int)($definition['id'] ?? 0);
         $values = $definition['values'] ?? array();
         if ($id <= 0 || !is_array($values) || $values === array()) continue;
         $label = trim((string)($definition['title'] ?? ''));
         $group = trim((string)($definition['group_title'] ?? ''));
         $filter_fields .= '<label><span>' . $this->h($group !== '' ? $group . ': ' . $label : $label) . '</span><select class="form-select form-select-sm" name="attr[' . $id . ']">';
         $filter_fields .= '<option value="">'
            . $this->h($texts->get_fd_message('all_option'))
            . '</option>';
         foreach ($values as $value) {
            $sel = isset($selected[$id]) && dbxShopSearch::normalized_text((string)$selected[$id]) === dbxShopSearch::normalized_text((string)$value) ? ' selected' : '';
            $filter_fields .= '<option value="' . $this->h($value) . '"' . $sel . '>' . $this->h($value) . '</option>';
         }
         $filter_fields .= '</select></label>';
      }
      $advanced_filters = '';
      if ($filter_fields !== '') {
         $open = $selected !== array() ? ' open' : '';
         $advanced_filters .= '<details class="dbx-shop-filter-advanced"' . $open . '>';
         $advanced_filters .= '<summary><i class="bi bi-sliders"></i> '
            . $this->h($texts->get_fd_message('refine_filters'))
            . '</summary>';
         $advanced_filters .= '<div class="dbx-shop-filter-row">' . $filter_fields . '</div>';
         $advanced_filters .= '</details>';
      }

      $form = dbx()->get_system_obj('dbxForm');
      $form->init('shop-catalog-filter-form', 'shop-catalog-filter-form');
      $form->set_editor_class_file(__FILE__);
      $form->set_field_definition('dbxShop|shop-catalog-filter-form');
      $form->load_fd_messages();
      $form->set_action('?dbx_modul=dbxShop&dbx_run1=catalog');
      $form->set_data(array('q' => $query));
      $form->_msg_info = '';
      $form->_msg_success = '';
      $form->_msg_error = '';
      $form->_msg_warning = '';
      $form->add_rep('bar_title', $texts->get_fd_message('bar_title'));
      $form->add_rep('frame_skip_form_wrap', '1');
      $form->add_fld('q');
      $form->add_rep('advanced_filters', $advanced_filters);
      $form->add_rep('group_hidden', $group_id > 0 ? '<input type="hidden" name="group" value="' . $group_id . '">' : '');
      return $form->run();
   }

   /**
    * Baut nur Werte, die das konkrete Karten-/Detailtemplate verwendet.
    *
    * Teure Teilrenderer wie Galerie und dbxForm werden dadurch nicht fuer
    * unsichtbare Platzhalter einer Produktkarte ausgefuehrt.
    */
   private function product_template_data(
      array $product,
      string $channel,
      bool $detail = false,
      ?array $template_fields = null
   ): array {
      $sku = (string)($product['sku'] ?? '');
      $uses = static fn(string $field): bool => $template_fields === null || isset($template_fields[$field]);
      $data = array();
      if ($uses('sku')) $data['sku'] = $this->h($sku);
      if ($uses('title')) $data['title'] = $this->h($product['title'] ?? '');
      if ($uses('summary')) $data['summary'] = $this->h($product['summary'] ?? '');
      if ($uses('description')) {
         $data['description'] = $this->h($product['description'] ?? $product['summary'] ?? '');
      }
      if ($uses('groups')) $data['groups'] = $this->groups_html($product);
      if ($uses('channels')) $data['channels'] = '';
      if ($uses('attributes')) {
         $data['attributes'] = $detail
            ? $this->attributes_table_html($product)
            : $this->attributes_inline_html($product, 4);
      }
      if ($uses('attributes_table')) {
         $data['attributes_table'] = $this->attributes_table_html($product);
      }
      if ($uses('gallery')) $data['gallery'] = $this->product_gallery($product);
      if ($uses('visual')) $data['visual'] = $this->product_visual($product);
      if ($uses('price')) $data['price'] = $this->money($product['price_gross'] ?? 0);
      if ($uses('tax_shipping')) $data['tax_shipping'] = $this->tax_shipping_html($product);
      if ($uses('shipping_info')) $data['shipping_info'] = $this->shipping_info_html($product);
      if ($uses('stock_info')) $data['stock_info'] = $this->stock_info_html($product);
      if ($uses('buy_form')) $data['buy_form'] = $this->buy_form_html($product);
      if ($uses('detail_url')) {
         $data['detail_url'] = '?dbx_modul=dbxShop&amp;dbx_run1=product&amp;sku=' . rawurlencode($sku);
      }
      if ($uses('catalog_url')) $data['catalog_url'] = '?dbx_modul=dbxShop&amp;dbx_run1=catalog';
      if ($uses('cart_url')) {
         $data['cart_url'] = '?dbx_modul=dbxShop&amp;dbx_run1=cart&amp;sku=' . rawurlencode($sku);
      }
      if ($uses('card_class')) {
         $data['card_class'] = $this->h($this->css_template_class(
            (string)$this->group_setting($product, 'card_template', 'product-card-default')
         ));
      }
      if ($uses('detail_class')) {
         $data['detail_class'] = $this->h($this->css_template_class(
            (string)$this->group_setting($product, 'detail_template', 'product-detail-default')
         ));
      }
      return $data;
   }

   private function css_template_class(string $template): string {
      $template = preg_replace('~[^a-z0-9_-]+~i', '-', trim($template));
      return $template !== '' ? 'is-template-' . strtolower($template) : '';
   }

   private function render_product_card(array $product, string $channel): string {
      $template = $this->template_name((string)$this->group_setting($product, 'card_template', 'product-card-default'), 'product-card-default', 'product-card-');
      if (!$this->shop_template_exists($template)) {
         $template = 'product-card-default';
      }
      return $this->tpl()->get_tpl(
         'dbxShop|' . $template,
         $this->product_template_data($product, $channel, false, $this->shop_template_fields($template))
      );
   }

   private function catalog_report_html(array $products, string $channel, string $query, array $attribute_filters, int $group_id, int $total_count): string {
      $report = dbx()->get_system_obj('dbxReport');
      $report->init('shop-catalog-report', 'dbxShop|shop-catalog-report');
      $report->set_field_definition('dbxShop|shop-catalog-filter-form');
      $report->load_fd_messages();
      $report->set_editor_class_file(__FILE__);
      $report->set_mode('tpl');
      $report->_pages = true;
      $report->_create_row_select = false;
      $report->_create_row_edit = false;
      $report->_create_row_delete = false;
      $report->_but_pagination = 7;
      $rows_per_page = max(6, min(48, (int)$report->get_fld_val('dbx_rrows', 12, 'int')));
      $position = max(0, (int)$report->get_fld_val('dbx_rpos', 0, 'int'));
      $filtered_count = count($products);
      if ($position >= $filtered_count && $filtered_count > 0) {
         $position = max(0, (int)(floor(($filtered_count - 1) / $rows_per_page) * $rows_per_page));
      }
      $visible_candidates = array_slice($products, $position, $rows_per_page);
      $visible = $this->repo()->products_by_ids(array_map(
         static fn($product) => (int)($product['id'] ?? 0),
         $visible_candidates
      ));
      $rows = array();
      foreach ($visible as $product) {
         $rows[] = array(
            'id' => (int)($product['id'] ?? 0),
            'card' => $this->render_product_card($product, $channel),
         );
      }

      $query_parts = array(
         'dbx_modul' => 'dbxShop',
         'dbx_run1' => 'catalog',
      );
      if ($query !== '') {
         $query_parts['q'] = $query;
      }
      if ($group_id > 0) {
         $query_parts['group'] = $group_id;
      }
      foreach ($attribute_filters as $id => $value) {
         $query_parts['attr[' . (int)$id . ']'] = (string)$value;
      }
      $report->set_action('?' . http_build_query($query_parts, '', '&'));
      $report->_rflds = array(
         'card' => $report->get_fd_message('column_products'),
      );
      $report->_rpt_format = array('card' => 'html');
      $report->_rrows = $rows_per_page;
      $report->_rpos = $position;
      $report->set_report_counts($filtered_count, $total_count);
      $report->_rdata = $rows;
      return $report->run();
   }

   private function render_product_detail(array $product, string $channel): string {
      $template = $this->template_name((string)$this->group_setting($product, 'detail_template', 'product-detail-default'), 'product-detail-default', 'product-detail-');
      if (!$this->shop_template_exists($template)) {
         $template = 'product-detail-default';
      }
      $data = $this->product_template_data(
         $product,
         $channel,
         true,
         $this->shop_template_fields($template)
      );
      return $this->tpl()->get_tpl('dbxShop|' . $template, $data);
   }

   private function tax_shipping_html(array $product): string {
      $texts = $this->texts('dbxShop|shop-catalog-filter-form');
      $tax = $this->h(number_format((float)($product['effective_tax_rate'] ?? 0), 2, ',', '.'));
      $shipping = (float)($product['effective_shipping_gross'] ?? 0);
      $shipping_text = $shipping > 0
         ? $this->money($shipping) . ' ' . $texts->get_fd_message('shipping_suffix')
         : $texts->get_fd_message('free_shipping');
      $show_tax = $this->settings_bool($this->shop_config(), 'tax_display_enabled', true);
      $parts = array();
      if ($show_tax) {
         $parts[] = $tax . '% ' . $texts->get_fd_message('tax_label');
      }
      $parts[] = $this->h($shipping_text);
      return '<small>' . implode(', ', $parts) . '</small>';
   }

   private function shipping_info_html(array $product): string {
      $texts = $this->texts('dbxShop|shop-catalog-filter-form');
      $delivery_time = trim((string)($product['effective_delivery_time'] ?? ''));
      $shipping_way = trim((string)($product['effective_shipping_way'] ?? ''));
      $shipping = (float)($product['effective_shipping_gross'] ?? 0);
      $shipping_text = $shipping > 0
         ? $this->money($shipping)
         : $texts->get_fd_message('free_shipping');
      $rows = '';

      if ($delivery_time !== '') {
         $rows .= '<div class="dbx-shop-shipping-info-row"><i class="bi bi-clock"></i><span>'
            . $this->h($texts->get_fd_message('delivery_time'))
            . ': ' . $this->h($delivery_time) . '</span></div>';
      }
      if ($shipping_way !== '') {
         $rows .= '<div class="dbx-shop-shipping-info-row"><i class="bi bi-truck"></i><span>'
            . $this->h($texts->get_fd_message('shipping_method'))
            . ': ' . $this->h($shipping_way) . '</span></div>';
      }
      $rows .= '<div class="dbx-shop-shipping-info-row"><i class="bi bi-box-seam"></i><span>'
         . $this->h($texts->get_fd_message('shipping_costs'))
         . ': ' . $this->h($shipping_text) . '</span></div>';

      return '<div class="dbx-shop-shipping-info">' . $rows . '</div>';
   }

   private function stock_info_html(array $product): string {
      $texts = $this->texts('dbxShop|shop-catalog-filter-form');
      $cfg = $this->shop_config();
      if (!$this->settings_bool($cfg, 'stock_enabled', false) || (string)($product['product_type'] ?? '') !== 'physical') {
         return '';
      }
      $stock = (int)($product['stock'] ?? 0);
      if ($stock <= 0) {
         return '<div class="alert alert-warning py-2 mb-2"><i class="bi bi-exclamation-triangle"></i> '
            . $this->h($texts->get_fd_message('stock_out')) . '</div>';
      }
      if ($stock <= 3) {
         return '<div class="alert alert-info py-2 mb-2"><i class="bi bi-box-seam"></i> '
            . $this->h($texts->format_fd_message(
               'stock_low',
               array('count' => $stock)
            )) . '</div>';
      }
      return '<div class="dbx-shop-shipping-info-row"><i class="bi bi-box-seam"></i><span>'
         . $this->h($texts->get_fd_message('stock_available'))
         . '</span></div>';
   }

   /**
    * Erstellt das Add-to-cart-Formular für ein Produkt.
    *
    * Dieselbe Factory wird beim Rendern und beim Ziel-POST benutzt. So ist
    * die Warenkorbmutation an die dbxForm-Tokenprüfung gebunden.
    */
   private function buy_form(array $product): ?\dbxForm {
      $sku = (string)($product['sku'] ?? '');
      if ($sku === '') {
         return null;
      }
      $cfg = $this->shop_config();
      if ($this->settings_bool($cfg, 'stock_enabled', false) && (string)($product['product_type'] ?? '') === 'physical' && (int)($product['stock'] ?? 0) <= 0) {
         return null;
      }
      $form = dbx()->get_system_obj('dbxForm');
      $form->init('shop-buy-' . preg_replace('~[^a-z0-9_-]+~i', '-', strtolower($sku)), 'shop-buy-form');
      $form->set_field_definition('dbxShop|shop-cart');
      $form->load_fd_messages();
      $form->set_editor_class_file(__FILE__);
      $form->set_action('?dbx_modul=dbxShop&dbx_run1=cart&sku=' . rawurlencode($sku));
      $form->merge_data(array('qty' => 1));
      $form->_msg_info = '';
      $form->_msg_success = '';
      $form->_msg_error = '';
      $form->_msg_warning = '';
      $form->add_rep('bar_title', $form->get_fd_message('buy_form_title'));
      $form->add_rep('frame_skip_form_wrap', '1');
      $form->add_rep('buy_form_id', preg_replace('~[^a-z0-9_-]+~i', '-', $sku));
      $form->add_rep('catalog_url', '?dbx_modul=dbxShop&amp;dbx_run1=catalog');
      $form->add_fld(
         'qty',
         'dbxShop|shop-field-qty',
         label: $form->get_fd_message('label_quantity'),
         rules: 'int|min=1'
      );
      return $form;
   }

   private function buy_form_html(array $product): string {
      $form = $this->buy_form($product);
      if (!$form) {
         $texts = $this->texts('dbxShop|shop-cart');
         return '<a class="btn btn-outline-secondary" href="?dbx_modul=dbxShop&amp;dbx_run1=catalog"><i class="bi bi-arrow-left"></i> '
            . $this->h($texts->get_fd_message('back_to_catalog'))
            . '</a>';
      }
      return $form->run();
   }
}
