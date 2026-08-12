<?php
namespace dbx\dbxShop;

trait dbxShopServiceCatalogServiceTrait {

   private function ensureSeed(): void {
      // Der oeffentliche GET-Pfad darf keine Demo- oder Wartungsdaten
      // anlegen. Seed und Migration werden im Admin explizit ausgefuehrt.
      $this->repo()->install();
   }

   private function activeChannel(): string {
      return 'shop';
   }

   private function channelNav(string $active): string {
      $channels = $this->repo()->channels();
      $html = '<div class="dbx-shop-channel-nav">';
      foreach ($channels as $channel) {
         $key = (string)($channel['channel_key'] ?? '');
         if ($key === '') {
            continue;
         }
         $cls = $key === $active ? ' active' : '';
         $html .= '<a class="btn btn-outline-secondary btn-sm' . $cls . '" href="?dbx_modul=dbxShop&amp;dbx_run1=catalog&amp;channel=' . rawurlencode($key) . '">';
         $html .= $this->h($channel['title'] ?? $key);
         $html .= '</a>';
      }
      $html .= '</div>';
      return $html;
   }

   private function productHasChannel(array $product, string $channel): bool {
      foreach (($product['channels'] ?? array()) as $ch) {
         if ((string)($ch['channel_key'] ?? '') === $channel && (int)($ch['active'] ?? 0) === 1) {
            return true;
         }
      }
      return false;
   }

   private function groupsHtml(array $product): string {
      $html = '';
      foreach (($product['groups'] ?? array()) as $group) {
         $groupId = (int)($group['id'] ?? 0);
         $href = $groupId > 0 ? '?dbx_modul=dbxShop&amp;dbx_run1=catalog&amp;group=' . $groupId : '';
         $label = $this->h($group['title'] ?? '');
         $html .= $href !== ''
            ? '<a class="dbx-shop-chip" href="' . $href . '">' . $label . '</a>'
            : '<span class="dbx-shop-chip">' . $label . '</span>';
      }
      return $html;
   }

   private function catalogGroupId(): int {
      return max(0, (int)dbx()->get_modul_var('group', 0, 'int'));
   }

   private function groupImageUrl(array $group): string {
      $image = $this->repo()->primaryImageForGroup((int)($group['id'] ?? 0));
      if (is_array($image)) {
         $url = $this->mediaItemUrl($image, true);
         if ($url !== '') {
            return $url;
         }
      }
      return $this->mediaUrl('files/shop/img/software-dashboard.svg');
   }

   private function catalogGroupBreadcrumb(int $groupId): string {
      if ($groupId <= 0) {
         return '';
      }
      $path = $this->repo()->groupPath($groupId);
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
         if ($id === $groupId) {
            $html .= '<span>' . $title . '</span>';
         } else {
            $html .= '<a href="?dbx_modul=dbxShop&amp;dbx_run1=catalog&amp;group=' . $id . '">' . $title . '</a>';
         }
      }
      $html .= '</nav>';
      return $html;
   }

   private function catalogGroupNavigation(int $parentId): string {
      $groups = $this->repo()->groupsByParent($parentId, true);
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
         $html .= '<span class="dbx-shop-group-card-image"><img src="' . $this->h($this->groupImageUrl($group)) . '" alt="' . $this->h($title) . '" loading="lazy"></span>';
         $html .= '<span class="dbx-shop-group-card-body"><strong>' . $this->h($title) . '</strong>';
         if ($description !== '') {
            $html .= '<small>' . $this->h($description) . '</small>';
         }
         $html .= '</span></a>';
      }
      $html .= '</section>';
      return $html;
   }

   private function productInCatalogGroup(array $product, int $groupId): bool {
      if ($groupId <= 0) {
         return true;
      }
      if ((int)($product['product_group_id'] ?? 0) === $groupId) {
         return true;
      }
      foreach (($product['groups'] ?? array()) as $group) {
         if ((int)($group['id'] ?? 0) === $groupId) {
            return true;
         }
      }
      return false;
   }

   private function channelsHtml(array $product): string {
      $html = '';
      foreach (($product['channels'] ?? array()) as $channel) {
         if ((int)($channel['active'] ?? 0) !== 1) {
            continue;
         }
         $html .= '<span class="dbx-shop-chip dbx-shop-chip-channel">' . $this->h($channel['title'] ?? $channel['channel_key'] ?? '') . '</span>';
      }
      return $html;
   }

   private function normalizedText(string $value): string {
      $value = strtolower($value);
      $value = strtr($value, array('ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss'));
      $value = preg_replace('~[^a-z0-9]+~', ' ', $value) ?: '';
      return preg_replace('~\\s+~', ' ', trim($value)) ?: '';
   }

   private function attributeText(array $product): string {
      $parts = array();
      foreach (($product['attributes'] ?? array()) as $attribute) {
         $value = trim((string)($attribute['display_value'] ?? $attribute['value_text'] ?? ''));
         $parts[] = (string)($attribute['title'] ?? '');
         $parts[] = (string)($attribute['attr_key'] ?? '');
         if ($value !== '') {
            $parts[] = $value;
         }
      }
      return implode(' ', $parts);
   }

   private function groupText(array $product): string {
      $parts = array();
      foreach (($product['groups'] ?? array()) as $group) {
         $parts[] = (string)($group['title'] ?? '');
         $parts[] = (string)($group['group_key'] ?? '');
         $parts[] = (string)($group['description'] ?? '');
         $parts[] = (string)($group['attribute_notes'] ?? '');
      }
      return implode(' ', $parts);
   }

   private function searchTerms(string $query): array {
      $terms = preg_split('~\\s+~', $this->normalizedText($query)) ?: array();
      $stopWords = array_flip(array('der','die','das','den','dem','des','ein','eine','einer','einem','und','oder','mit','ohne','fuer','fur','von','im','in','am','an','auf','zu'));
      $out = array();
      foreach ($terms as $term) {
         $term = trim($term);
         if ($term === '' || isset($stopWords[$term])) {
            continue;
         }
         if (strlen($term) < 2 && !ctype_digit($term)) {
            continue;
         }
         $out[$term] = true;
      }
      return array_keys($out);
   }

   private function textMatchesSearchTerm(string $text, string $term): bool {
      return $this->searchFieldScore($text, $term, 1) > 0;
   }

   private function searchFieldScore(string $text, string $term, int $weight): int {
      if ($text === '' || $term === '') {
         return 0;
      }
      if ($text === $term) {
         return $weight * 8;
      }
      $termLength = strlen($term);
      $compactText = str_replace(' ', '', $text);
      $compactTerm = str_replace(' ', '', $term);
      if (strpos($text, $term) !== false || strpos($compactText, $compactTerm) !== false) {
         return $weight * 5;
      }
      $best = 0;
      foreach (preg_split('~\\s+~', $text) ?: array() as $token) {
         $token = trim($token);
         if ($token === '') {
            continue;
         }
         if ($token === $term) {
            $best = max($best, $weight * 6);
            continue;
         }
         if ($termLength < 3) {
            continue;
         }
         if (strlen($token) >= $termLength && strpos($token, $term) === 0) {
            $best = max($best, $weight * 4);
            continue;
         }
         if (
            $termLength >= 4
            && strlen($token) >= 4
            && substr($token, 0, 3) === substr($term, 0, 3)
            && abs(strlen($token) - $termLength) <= ($termLength >= 7 ? 2 : 1)
            && levenshtein($token, $term) <= ($termLength >= 7 ? 2 : 1)
         ) {
            $best = max($best, $weight * 2);
         }
      }
      return $best;
   }

   private function productSearchScore(array $product, string $query): int {
      $terms = $this->searchTerms($query);
      if ($terms === array()) {
         return 1;
      }

      $primary = $this->normalizedText(implode(' ', array(
         (string)($product['sku'] ?? ''),
         (string)($product['title'] ?? ''),
         (string)($product['category'] ?? ''),
         (string)($product['badge'] ?? ''),
         (string)($product['product_type'] ?? ''),
      )));
      $secondary = $this->normalizedText(implode(' ', array(
         (string)($product['summary'] ?? ''),
         (string)($product['description'] ?? ''),
      )));
      $attributes = $this->normalizedText($this->attributeText($product));
      $groups = $this->normalizedText($this->groupText($product));

      $score = 0;
      $matched = 0;
      $firstTermPrimaryScore = 0;
      $termCount = count($terms);

      foreach ($terms as $idx => $term) {
         $primaryScore = $this->searchFieldScore($primary, $term, 10);
         $termScore = max(
            $primaryScore,
            $this->searchFieldScore($attributes, $term, 7),
            $this->searchFieldScore($secondary, $term, 4),
            $this->searchFieldScore($groups, $term, 3)
         );

         if ($idx === 0) {
            $firstTermPrimaryScore = $primaryScore;
         }
         if ($termScore > 0) {
            $matched++;
            $score += $termScore;
         }
      }

      if ($matched === 0) {
         return 0;
      }
      if ($termCount === 1) {
         return $score;
      }

      if ($matched === $termCount || $firstTermPrimaryScore > 0 || $score >= 20) {
         return $score + ($matched * 3);
      }

      return 0;
   }

   private function attributesInlineHtml(array $product, int $max = 4): string {
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

   private function attributesTableHtml(array $product): string {
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

   private function selectedAttributeFilters(): array {
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

   private function productMatchesQuery(array $product, string $query): bool {
      return $this->productSearchScore($product, $query) > 0;
   }

   private function productMatchesAttributeFilters(array $product, array $filters): bool {
      if ($filters === array()) {
         return true;
      }
      $values = array();
      foreach (($product['attributes'] ?? array()) as $attribute) {
         $id = (int)($attribute['id'] ?? 0);
         if ($id <= 0) continue;
         $values[$id] = $this->normalizedText((string)($attribute['value_text'] ?? ''));
      }
      foreach ($filters as $id => $value) {
         if (!isset($values[$id]) || $values[$id] !== $this->normalizedText((string)$value)) {
            return false;
         }
      }
      return true;
   }

   private function catalogFiltersHtml(string $channel, string $query, array $selected, int $groupId = 0): string {
      $texts = $this->texts('dbxShop|shop-catalog-filter-form');
      $filterFields = '';
      foreach ($this->repo()->attributeFilterDefinitions() as $definition) {
         $id = (int)($definition['id'] ?? 0);
         $values = $definition['values'] ?? array();
         if ($id <= 0 || !is_array($values) || $values === array()) continue;
         $label = trim((string)($definition['title'] ?? ''));
         $group = trim((string)($definition['group_title'] ?? ''));
         $filterFields .= '<label><span>' . $this->h($group !== '' ? $group . ': ' . $label : $label) . '</span><select class="form-select form-select-sm" name="attr[' . $id . ']">';
         $filterFields .= '<option value="">'
            . $this->h($texts->get_fd_message('all_option'))
            . '</option>';
         foreach ($values as $value) {
            $sel = isset($selected[$id]) && $this->normalizedText((string)$selected[$id]) === $this->normalizedText((string)$value) ? ' selected' : '';
            $filterFields .= '<option value="' . $this->h($value) . '"' . $sel . '>' . $this->h($value) . '</option>';
         }
         $filterFields .= '</select></label>';
      }
      $advancedFilters = '';
      if ($filterFields !== '') {
         $open = $selected !== array() ? ' open' : '';
         $advancedFilters .= '<details class="dbx-shop-filter-advanced"' . $open . '>';
         $advancedFilters .= '<summary><i class="bi bi-sliders"></i> '
            . $this->h($texts->get_fd_message('refine_filters'))
            . '</summary>';
         $advancedFilters .= '<div class="dbx-shop-filter-row">' . $filterFields . '</div>';
         $advancedFilters .= '</details>';
      }

      $form = dbx()->get_system_obj('dbxForm');
      $form->init('shop-catalog-filter-form', 'shop-catalog-filter-form');
      $form->set_editor_class_file(__FILE__);
      $form->_fd = 'dbxShop|shop-catalog-filter-form';
      $form->load_fd_messages();
      $form->_action = '?dbx_modul=dbxShop&dbx_run1=catalog';
      $form->_data = array('q' => $query);
      $form->_msg_info = '';
      $form->_msg_success = '';
      $form->_msg_error = '';
      $form->_msg_warning = '';
      $form->add_rep('bar_title', $texts->get_fd_message('bar_title'));
      $form->add_rep('frame_skip_form_wrap', '1');
      $form->add_fld('q');
      $form->add_rep('advanced_filters', $advancedFilters);
      $form->add_rep('group_hidden', $groupId > 0 ? '<input type="hidden" name="group" value="' . $groupId . '">' : '');
      return $form->run();
   }

   /**
    * Baut nur Werte, die das konkrete Karten-/Detailtemplate verwendet.
    *
    * Teure Teilrenderer wie Galerie und dbxForm werden dadurch nicht fuer
    * unsichtbare Platzhalter einer Produktkarte ausgefuehrt.
    */
   private function productTemplateData(
      array $product,
      string $channel,
      bool $detail = false,
      ?array $templateFields = null
   ): array {
      $sku = (string)($product['sku'] ?? '');
      $uses = static fn(string $field): bool => $templateFields === null || isset($templateFields[$field]);
      $data = array();
      if ($uses('sku')) $data['sku'] = $this->h($sku);
      if ($uses('title')) $data['title'] = $this->h($product['title'] ?? '');
      if ($uses('summary')) $data['summary'] = $this->h($product['summary'] ?? '');
      if ($uses('description')) {
         $data['description'] = $this->h($product['description'] ?? $product['summary'] ?? '');
      }
      if ($uses('groups')) $data['groups'] = $this->groupsHtml($product);
      if ($uses('channels')) $data['channels'] = '';
      if ($uses('attributes')) {
         $data['attributes'] = $detail
            ? $this->attributesTableHtml($product)
            : $this->attributesInlineHtml($product, 4);
      }
      if ($uses('attributes_table')) {
         $data['attributes_table'] = $this->attributesTableHtml($product);
      }
      if ($uses('gallery')) $data['gallery'] = $this->productGallery($product);
      if ($uses('visual')) $data['visual'] = $this->productVisual($product);
      if ($uses('price')) $data['price'] = $this->money($product['price_gross'] ?? 0);
      if ($uses('tax_shipping')) $data['tax_shipping'] = $this->taxShippingHtml($product);
      if ($uses('shipping_info')) $data['shipping_info'] = $this->shippingInfoHtml($product);
      if ($uses('stock_info')) $data['stock_info'] = $this->stockInfoHtml($product);
      if ($uses('buy_form')) $data['buy_form'] = $this->buyFormHtml($product);
      if ($uses('detail_url')) {
         $data['detail_url'] = '?dbx_modul=dbxShop&amp;dbx_run1=product&amp;sku=' . rawurlencode($sku);
      }
      if ($uses('catalog_url')) $data['catalog_url'] = '?dbx_modul=dbxShop&amp;dbx_run1=catalog';
      if ($uses('cart_url')) {
         $data['cart_url'] = '?dbx_modul=dbxShop&amp;dbx_run1=cart&amp;sku=' . rawurlencode($sku);
      }
      if ($uses('card_class')) {
         $data['card_class'] = $this->h($this->cssTemplateClass(
            (string)$this->groupSetting($product, 'card_template', 'product-card-default')
         ));
      }
      if ($uses('detail_class')) {
         $data['detail_class'] = $this->h($this->cssTemplateClass(
            (string)$this->groupSetting($product, 'detail_template', 'product-detail-default')
         ));
      }
      return $data;
   }

   private function cssTemplateClass(string $template): string {
      $template = preg_replace('~[^a-z0-9_-]+~i', '-', trim($template));
      return $template !== '' ? 'is-template-' . strtolower($template) : '';
   }

   private function renderProductCard(array $product, string $channel): string {
      $template = $this->templateName((string)$this->groupSetting($product, 'card_template', 'product-card-default'), 'product-card-default', 'product-card-');
      if (!$this->shopTemplateExists($template)) {
         $template = 'product-card-default';
      }
      return $this->tpl()->get_tpl(
         'dbxShop|' . $template,
         $this->productTemplateData($product, $channel, false, $this->shopTemplateFields($template))
      );
   }

   private function catalogReportHtml(array $products, string $channel, string $query, array $attributeFilters, int $groupId): string {
      $report = dbx()->get_system_obj('dbxReport');
      $report->init('shop-catalog-report', 'dbxShop|shop-catalog-report');
      $report->_fd = 'dbxShop|shop-catalog-filter-form';
      $report->load_fd_messages();
      $report->set_editor_class_file(__FILE__);
      $report->_mode = 'tpl';
      $report->_pages = true;
      $report->_create_row_select = false;
      $report->_create_row_edit = false;
      $report->_create_row_delete = false;
      $report->_but_pagination = 7;
      $rowsPerPage = max(6, min(48, (int)$report->get_fld_val('dbx_rrows', 12, 'int')));
      $position = max(0, (int)$report->get_fld_val('dbx_rpos', 0, 'int'));
      $filteredCount = count($products);
      if ($position >= $filteredCount && $filteredCount > 0) {
         $position = max(0, (int)(floor(($filteredCount - 1) / $rowsPerPage) * $rowsPerPage));
      }
      $visibleCandidates = array_slice($products, $position, $rowsPerPage);
      $visible = $this->repo()->productsByIds(array_map(
         static fn($product) => (int)($product['id'] ?? 0),
         $visibleCandidates
      ));
      $rows = array();
      foreach ($visible as $product) {
         $rows[] = array(
            'id' => (int)($product['id'] ?? 0),
            'card' => $this->renderProductCard($product, $channel),
         );
      }

      $queryParts = array(
         'dbx_modul' => 'dbxShop',
         'dbx_run1' => 'catalog',
      );
      if ($query !== '') {
         $queryParts['q'] = $query;
      }
      if ($groupId > 0) {
         $queryParts['group'] = $groupId;
      }
      foreach ($attributeFilters as $id => $value) {
         $queryParts['attr[' . (int)$id . ']'] = (string)$value;
      }
      $report->_action = '?' . http_build_query($queryParts, '', '&');
      $report->_rflds = array(
         'card' => $report->get_fd_message('column_products'),
      );
      $report->_rpt_format = array('card' => 'html');
      $report->_rrows = $rowsPerPage;
      $report->_rpos = $position;
      $report->_count_all = $filteredCount;
      $report->_rcount = $filteredCount;
      $report->_rdata = $rows;
      return $report->run();
   }

   private function renderProductDetail(array $product, string $channel): string {
      $template = $this->templateName((string)$this->groupSetting($product, 'detail_template', 'product-detail-default'), 'product-detail-default', 'product-detail-');
      if (!$this->shopTemplateExists($template)) {
         $template = 'product-detail-default';
      }
      $data = $this->productTemplateData(
         $product,
         $channel,
         true,
         $this->shopTemplateFields($template)
      );
      return $this->tpl()->get_tpl('dbxShop|' . $template, $data);
   }

   private function taxShippingHtml(array $product): string {
      $texts = $this->texts('dbxShop|shop-catalog-filter-form');
      $tax = $this->h(number_format((float)($product['effective_tax_rate'] ?? 0), 2, ',', '.'));
      $shipping = (float)($product['effective_shipping_gross'] ?? 0);
      $shippingText = $shipping > 0
         ? $this->money($shipping) . ' ' . $texts->get_fd_message('shipping_suffix')
         : $texts->get_fd_message('free_shipping');
      $showTax = $this->settingsBool($this->shopConfig(), 'tax_display_enabled', true);
      $parts = array();
      if ($showTax) {
         $parts[] = $tax . '% ' . $texts->get_fd_message('tax_label');
      }
      $parts[] = $this->h($shippingText);
      return '<small>' . implode(', ', $parts) . '</small>';
   }

   private function shippingInfoHtml(array $product): string {
      $texts = $this->texts('dbxShop|shop-catalog-filter-form');
      $deliveryTime = trim((string)($product['effective_delivery_time'] ?? ''));
      $shippingWay = trim((string)($product['effective_shipping_way'] ?? ''));
      $shipping = (float)($product['effective_shipping_gross'] ?? 0);
      $shippingText = $shipping > 0
         ? $this->money($shipping)
         : $texts->get_fd_message('free_shipping');
      $rows = '';

      if ($deliveryTime !== '') {
         $rows .= '<div class="dbx-shop-shipping-info-row"><i class="bi bi-clock"></i><span>'
            . $this->h($texts->get_fd_message('delivery_time'))
            . ': ' . $this->h($deliveryTime) . '</span></div>';
      }
      if ($shippingWay !== '') {
         $rows .= '<div class="dbx-shop-shipping-info-row"><i class="bi bi-truck"></i><span>'
            . $this->h($texts->get_fd_message('shipping_method'))
            . ': ' . $this->h($shippingWay) . '</span></div>';
      }
      $rows .= '<div class="dbx-shop-shipping-info-row"><i class="bi bi-box-seam"></i><span>'
         . $this->h($texts->get_fd_message('shipping_costs'))
         . ': ' . $this->h($shippingText) . '</span></div>';

      return '<div class="dbx-shop-shipping-info">' . $rows . '</div>';
   }

   private function stockInfoHtml(array $product): string {
      $texts = $this->texts('dbxShop|shop-catalog-filter-form');
      $cfg = $this->shopConfig();
      if (!$this->settingsBool($cfg, 'stock_enabled', false) || (string)($product['product_type'] ?? '') !== 'physical') {
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
   private function buyForm(array $product): ?\dbxForm {
      $sku = (string)($product['sku'] ?? '');
      if ($sku === '') {
         return null;
      }
      $cfg = $this->shopConfig();
      if ($this->settingsBool($cfg, 'stock_enabled', false) && (string)($product['product_type'] ?? '') === 'physical' && (int)($product['stock'] ?? 0) <= 0) {
         return null;
      }
      $form = dbx()->get_system_obj('dbxForm');
      $form->init('shop-buy-' . preg_replace('~[^a-z0-9_-]+~i', '-', strtolower($sku)), 'shop-buy-form');
      $form->_fd = 'dbxShop|shop-cart';
      $form->load_fd_messages();
      $form->set_editor_class_file(__FILE__);
      $form->_action = '?dbx_modul=dbxShop&dbx_run1=cart&sku=' . rawurlencode($sku);
      $form->_data = array_merge($form->_data, array('qty' => 1));
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

   private function buyFormHtml(array $product): string {
      $form = $this->buyForm($product);
      if (!$form) {
         $texts = $this->texts('dbxShop|shop-cart');
         return '<a class="btn btn-outline-secondary" href="?dbx_modul=dbxShop&amp;dbx_run1=catalog"><i class="bi bi-arrow-left"></i> '
            . $this->h($texts->get_fd_message('back_to_catalog'))
            . '</a>';
      }
      return $form->run();
   }
}
