<?php
namespace dbx\dbxShop_admin;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

/**
 * Produktsuche, Sortierung, Baum und dbxReport-Aktionen.
 *
 * Zustandslose Quelltext-Komposition: keine zusaetzliche Instanz, kein Proxy
 * und keine zusaetzlichen Datenbank- oder Templatezugriffe.
 */
trait dbxShopAdminProductReportServiceTrait {


   private function chips(
      array $items,
      string $key = 'title',
      string $class = '',
      string $emptyLabel = 'keine Werte'
   ): string {
      $html = '';
      foreach ($items as $item) {
         $value = trim((string)($item[$key] ?? ''));
         if ($value === '') continue;
         $html .= '<span class="badge text-bg-light border">' . $this->h($value) . '</span>';
      }
      if ($html === '') {
         return '<span class="text-muted small">' . $this->h($emptyLabel) . '</span>';
      }
      $class = trim('dbx-shop-report-chip-grid ' . $class);
      return '<div class="' . $this->h($class) . '">' . $html . '</div>';
   }



   private function attributeBadges(
      array $product,
      string $emptyLabel = 'keine Werte'
   ): string {
      $html = '';
      foreach (($product['attributes'] ?? array()) as $attribute) {
         $value = trim((string)($attribute['display_value'] ?? $attribute['value_text'] ?? ''));
         if ($value === '') continue;
         $html .= '<span class="badge text-bg-info text-start text-wrap">' . $this->h($attribute['title'] ?? '') . ': ' . $this->h($value) . '</span>';
      }
      return $html !== ''
         ? '<div class="dbx-shop-report-chip-grid dbx-shop-report-chip-grid-attributes">' . $html . '</div>'
         : '<span class="text-muted small">' . $this->h($emptyLabel) . '</span>';
   }



   private function attributeOptions(string $options): array {
      $items = preg_split('~[|;\r\n]+~', $options) ?: array();
      return array_values(array_filter(array_map('trim', $items), fn($item) => $item !== ''));
   }






   private function shopConfig(): array {
      $cfg = dbx()->get_cfg('dbxShop');
      return is_array($cfg) ? $cfg : array();
   }



   private function channelsEnabled(): bool {
      $value = strtolower(trim((string)dbx()->get_cfg('dbxShop', 'channels_enabled')));
      return !in_array($value, array('0', 'false', 'off', 'no', 'nein'), true);
   }



   private function taxRatesConfig(): array {
      $cfg = $this->shopConfig();
      $rates = $cfg['tax_rates'] ?? array();
      if (!is_array($rates) || !count($rates)) {
         $rates = array(
            'mwst1' => array('title' => 'MwSt. normal', 'rate' => '19'),
            'mwst2' => array('title' => 'MwSt. ermaessigt', 'rate' => '7'),
            'mwst3' => array('title' => 'MwSt. vorbereitet', 'rate' => '22'),
         );
      }
      foreach (array('mwst1' => 'MwSt. normal', 'mwst2' => 'MwSt. ermaessigt', 'mwst3' => 'MwSt. vorbereitet') as $key => $title) {
         if (!isset($rates[$key]) || !is_array($rates[$key])) {
            $rates[$key] = array('title' => $title, 'rate' => $key === 'mwst2' ? '7' : ($key === 'mwst3' ? '22' : '19'));
         }
      }
      return $rates;
   }






   private function normalizedText(string $value): string {
      $value = strtolower($value);
      $value = strtr($value, array('ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss'));
      $value = preg_replace('~[^a-z0-9]+~', ' ', $value) ?: '';
      return preg_replace('~\\s+~', ' ', trim($value)) ?: '';
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



   private function productAttributeText(array $product): string {
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



   private function productGroupText(array $product): string {
      $parts = array();
      foreach (($product['groups'] ?? array()) as $group) {
         $parts[] = (string)($group['title'] ?? '');
         $parts[] = (string)($group['group_key'] ?? '');
         $parts[] = (string)($group['description'] ?? '');
         $parts[] = (string)($group['attribute_notes'] ?? '');
      }
      foreach (($product['shipping_groups'] ?? array()) as $group) {
         $parts[] = (string)($group['title'] ?? '');
         $parts[] = (string)($group['group_key'] ?? '');
      }
      foreach (($product['channel_groups'] ?? array()) as $group) {
         $parts[] = (string)($group['title'] ?? '');
         $parts[] = (string)($group['group_key'] ?? '');
      }
      foreach (($product['channels'] ?? array()) as $channel) {
         $parts[] = (string)($channel['title'] ?? '');
         $parts[] = (string)($channel['channel_key'] ?? '');
      }
      return implode(' ', $parts);
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
      $attributes = $this->normalizedText($this->productAttributeText($product));
      $groups = $this->normalizedText($this->productGroupText($product));

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



   private function productSortValue(array $product, string $sort) {
      switch ($sort) {
         case 'sku':
         case 'title':
            return $this->normalizedText((string)($product[$sort] ?? ''));
         case 'price_gross':
         case 'effective_tax_rate':
         case 'effective_shipping_gross':
            return (float)($product[$sort] ?? 0);
         case 'active':
            return (int)($product['active'] ?? 0);
         case 'sorter':
         default:
            return (int)($product['sorter'] ?? 100);
      }
   }



   private function sortProductsForReport(array $products, string $query, string $sort, string $direction): array {
      $hasQuery = $this->searchTerms($query) !== array();
      $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
      usort($products, function(array $a, array $b) use ($hasQuery, $sort, $direction): int {
         if ($hasQuery && (int)($a['_search_score'] ?? 0) !== (int)($b['_search_score'] ?? 0)) {
            return (int)($b['_search_score'] ?? 0) <=> (int)($a['_search_score'] ?? 0);
         }

         $av = $this->productSortValue($a, $sort);
         $bv = $this->productSortValue($b, $sort);
         if ($av == $bv) {
            return strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
         }
         $cmp = is_numeric($av) && is_numeric($bv) ? ($av <=> $bv) : strcasecmp((string)$av, (string)$bv);
         return $direction === 'DESC' ? -$cmp : $cmp;
      });
      return $products;
   }



   public function product_report_next_record($report, $record) {
      if (!is_array($record)) {
         return $record;
      }

      $sku = (string)($record['sku'] ?? '');
      $productId = (int)($record['id'] ?? 0);
      $productTitle = trim((string)($record['title'] ?? $sku));
      $summary = trim((string)($record['summary'] ?? ''));
      $image = $record['images'][0] ?? array();
      $imgUrl = is_array($image) ? $this->mediaItemUrl($image, true) : '';
      $emptyLabel = $report->get_fd_message('no_values');

      $record['image_view'] = $imgUrl !== ''
         ? '<span class="dbx-shop-report-image"><img src="' . $this->h($imgUrl) . '" alt="" loading="lazy"></span>'
         : '<span class="text-muted small">' . $this->h($report->get_fd_message('no_image')) . '</span>';
      $record['article_view'] = '<div class="dbx-shop-report-article-scroll"><code class="dbx-shop-report-sku">' . $this->h($sku) . '</code>'
         . '<br><strong>' . $this->h($productTitle) . '</strong>'
         . ($summary !== '' ? '<br><small class="text-muted">' . $this->h($summary) . '</small>' : '')
         . '</div>';
      $record['groups_view'] = $this->chips($record['groups'] ?? array(), 'title', '', $emptyLabel);
      $record['attributes_view'] = $this->attributeBadges($record, $emptyLabel);
      $record['shipping_groups_view'] = $this->chips($record['shipping_groups'] ?? array(), 'title', '', $emptyLabel);
      $record['channel_groups_view'] = $this->chips($record['channel_groups'] ?? array(), 'title', '', $emptyLabel);
      $record['channels_view'] = $this->chips($record['channels'] ?? array(), 'title', 'dbx-shop-report-chip-grid-channels', $emptyLabel);
      $record['price_view'] = '<span class="text-nowrap">' . $this->money($record['price_gross'] ?? 0) . '</span>';
      $record['tax_view'] = number_format((float)($record['effective_tax_rate'] ?? 0), 2, ',', '.') . '%';
      $record['shipping_view'] = '<span class="text-nowrap">' . $this->money($record['effective_shipping_gross'] ?? 0) . '</span>';
      $record['status_view'] = ((int)($record['active'] ?? 0) === 1)
         ? '<span class="badge text-bg-success">' . $this->h($report->get_fd_message('status_active')) . '</span>'
         : '<span class="badge text-bg-secondary">' . $this->h($report->get_fd_message('status_inactive')) . '</span>';

      return $record;
   }



   public function product_report_row_action_data($report, $data) {
      if (!is_array($data) || !isset($data['data']) || !is_array($data['data'])) {
         return $data;
      }

      $type = (string)($data['type'] ?? '');
      $record = is_array($data['record'] ?? null) ? $data['record'] : array();
      $rid = (int)($data['data']['rid'] ?? $record['id'] ?? 0);
      $sku = (string)($record['sku'] ?? '');
      $title = trim((string)($record['title'] ?? $sku));

      if ($type === 'edit') {
         $url = '?dbx_modul=dbxShop_admin&dbx_run1=product_edit&id=' . $rid;
         $data['data']['action'] = $url;
         $data['data']['class'] = 'openWin dbx-win';
         $data['data']['tooltip'] = $report->format_fd_message(
            'action_edit',
            array('title' => $title)
         );
      } elseif ($type === 'show') {
         $url = '?dbx_modul=dbxShop&dbx_run1=product&sku=' . rawurlencode($sku);
         $data['data']['action'] = $url;
         $data['data']['class'] = 'openWin dbx-win';
         $data['data']['tooltip'] = $report->format_fd_message(
            'action_view',
            array('title' => $title)
         );
      } elseif ($type === 'delete') {
         $data['data']['action'] = '?dbx_modul=dbxShop_admin&dbx_run1=products';
         $data['data']['confirm'] = $report->format_fd_message(
            'action_delete_confirm',
            array('title' => $title)
         );
      }

      return $data;
   }



   private function productTreePanel(array $products, $texts): string {
      $groups = $this->repo()->groups();
      $groupsByParent = array();
      foreach ($groups as $group) {
         $parentId = (int)($group['parent_id'] ?? 0);
         $groupsByParent[$parentId][] = $group;
      }

      $productsByGroup = array();
      foreach ($products as $product) {
         $groupId = (int)($product['product_group_id'] ?? 0);
         if ($groupId <= 0 && isset($product['groups'][0])) {
            $groupId = (int)($product['groups'][0]['id'] ?? 0);
         }
         $productsByGroup[$groupId][] = $product;
      }

      $renderProducts = function(int $groupId, bool $asListItem = false) use (&$productsByGroup, $texts): string {
         $items = '';
         foreach (($productsByGroup[$groupId] ?? array()) as $product) {
            $id = (int)($product['id'] ?? 0);
            $title = trim((string)($product['title'] ?? $texts->get_fd_message('tree_product_fallback')));
            $sku = trim((string)($product['sku'] ?? ''));
            if ($id <= 0) continue;
            $searchText = trim($title . ' ' . $sku);
            $items .= '<li class="dbx-shop-tree-product" draggable="true" data-shop-tree-node="product" data-shop-tree-product="' . $id . '" data-shop-tree-search-text="' . $this->h($searchText) . '">';
            $items .= '<span class="dbx-shop-tree-product-main"><i class="bi bi-box-seam"></i><span><strong>' . $this->h($title) . '</strong>' . ($sku !== '' ? '<small>' . $this->h($sku) . '</small>' : '') . '</span></span>';
            $items .= '<a class="btn btn-outline-primary btn-sm openWin dbx-win" href="?dbx_modul=dbxShop_admin&amp;dbx_run1=product_edit&amp;id=' . $id . '" data-dbx-tooltip="' . $this->h($texts->get_fd_message('tree_edit_product')) . '"><i class="bi bi-pencil"></i></a>';
            $items .= '</li>';
         }
         if ($items === '') {
            return '';
         }
         $html = '<ul class="dbx-shop-tree-products">' . $items . '</ul>';
         return $asListItem ? '<li class="dbx-shop-tree-product-list">' . $html . '</li>' : $html;
      };

      $renderGroup = function(array $group) use (&$renderGroup, &$groupsByParent, $renderProducts, $texts): string {
         $id = (int)($group['id'] ?? 0);
         if ($id <= 0) return '';
         $title = trim((string)($group['title'] ?? $texts->get_fd_message('tree_group_fallback')));
         $childHtml = '';
         foreach (($groupsByParent[$id] ?? array()) as $child) {
            $childHtml .= $renderGroup($child);
         }
         $productsHtml = $renderProducts($id, true);
         $countChildren = count($groupsByParent[$id] ?? array());
         $countProducts = substr_count($productsHtml, 'data-shop-tree-node="product"');
         $hasChildren = $childHtml !== '' || $productsHtml !== '';
         $html = '<li class="dbx-shop-tree-group" data-shop-tree-group-wrap data-shop-tree-search-text="' . $this->h($title) . '">';
         $html .= '<div class="dbx-shop-tree-group-head" draggable="true" data-shop-tree-node="group" data-shop-tree-group="' . $id . '" data-shop-tree-drop="' . $id . '">';
         $html .= '<span class="dbx-shop-tree-group-main">';
         if ($hasChildren) {
            $html .= '<button type="button" class="dbx-shop-tree-group-toggle" data-shop-tree-group-toggle data-dbx-tooltip="' . $this->h($texts->get_fd_message('tree_toggle_group')) . '" aria-label="' . $this->h($texts->get_fd_message('tree_toggle_group')) . '" aria-expanded="true"><i class="bi bi-chevron-down"></i></button>';
         } else {
            $html .= '<span class="dbx-shop-tree-toggle-spacer"></span>';
         }
         $html .= '<i class="bi bi-folder2"></i><span><strong>' . $this->h($title) . '</strong><small>' . $this->h($texts->format_fd_message('tree_counts', array('groups' => $countChildren, 'products' => $countProducts))) . '</small></span></span>';
         $html .= '<a class="btn btn-outline-secondary btn-sm openWin dbx-win" href="?dbx_modul=dbxShop_admin&amp;dbx_run1=groups" data-url="?dbx_modul=dbxShop_admin&amp;dbx_run1=groups" data-title="' . $this->h($texts->get_fd_message('tree_edit_groups')) . '" data-width="54%" data-height="84%" title="' . $this->h($texts->get_fd_message('tree_edit_groups')) . '"><i class="bi bi-diagram-3"></i></a>';
         $html .= '</div>';
         if ($hasChildren) {
            $html .= '<ul class="dbx-shop-tree-children">' . $childHtml . $productsHtml . '</ul>';
         }
         $html .= '</li>';
         return $html;
      };

      $rootGroups = '';
      foreach (($groupsByParent[0] ?? array()) as $group) {
         $rootGroups .= $renderGroup($group);
      }
      $ungrouped = $renderProducts(0, false);
      $search = $this->tpl()->get_tpl('dbx|search', dbx()->search_defaults(array(
         'name' => 'shop_tree_search',
         'placeholder' => $texts->get_fd_message('tree_search_placeholder'),
         'title' => $texts->get_fd_message('tree_search_title'),
         'wrap_class' => 'dbx-shop-tree-search-wrap',
         'extra_attrs' => 'data-shop-tree-search',
         'i' => 1,
      )));
      $treeMoveUrl = str_replace('&', '&amp;', $this->actionUrl('?dbx_modul=dbxShop_admin&dbx_run1=product_tree_move'));
      $html = '<section class="dbx-shop-product-tree-panel" data-shop-tree-panel data-shop-tree-moveurl="' . $treeMoveUrl . '" aria-label="' . $this->h($texts->get_fd_message('tree_aria')) . '">';
      $html .= '<div class="dbx-shop-product-tree-head"><div><h3>' . $this->h($texts->get_fd_message('tree_title')) . '</h3><p>' . $this->h($texts->get_fd_message('tree_subtitle')) . '</p></div><div class="dbx-shop-product-tree-actions"><a class="btn btn-outline-primary btn-sm openWin dbx-win" href="?dbx_modul=dbxShop_admin&amp;dbx_run1=groups" data-url="?dbx_modul=dbxShop_admin&amp;dbx_run1=groups" data-title="' . $this->h($texts->get_fd_message('tree_edit_groups')) . '" data-width="54%" data-height="84%"><i class="bi bi-diagram-3"></i> ' . $this->h($texts->get_fd_message('tree_edit_groups_button')) . '</a><button type="button" class="btn btn-outline-secondary btn-sm" data-shop-tree-close data-dbx-tooltip="' . $this->h($texts->get_fd_message('tree_close')) . '" aria-label="' . $this->h($texts->get_fd_message('tree_close')) . '"><i class="bi bi-x-lg"></i></button></div></div>';
      $html .= '<div class="dbx-shop-tree-tools">' . $search . '</div>';
      $html .= '<ul class="dbx-shop-tree-list">' . $rootGroups . '</ul>';
      if ($ungrouped !== '') {
         $html .= '<div class="dbx-shop-tree-ungrouped"><strong>' . $this->h($texts->get_fd_message('tree_ungrouped')) . '</strong>' . $ungrouped . '</div>';
      }
      $html .= '</section>';
      return $html;
   }



   private function productTreeToggleButton($texts): string {
      $label = $this->h($texts->get_fd_message('tree_open'));
      return '<button type="button" class="btn btn-outline-secondary btn-sm dbx-shop-product-tree-toggle" data-dbx="lib=shopAdmin" data-shop-tree-toggle data-dbx-tooltip="' . $label . '" aria-label="' . $label . '" aria-expanded="false"><i class="bi bi-diagram-3"></i></button>';
   }



   private function selectedProductIds($report): array {
      $ids = array();
      foreach (array_keys($report->get_multi_selects()) as $id) {
         $id = (int)$id;
         if ($id > 0) {
            $ids[$id] = $id;
         }
      }
      return array_values($ids);
   }



   private function productReportActionControls(string $baseAction, $texts): string {
      $channels = '<option value="">' . $this->h($texts->get_fd_message('bulk_channel_placeholder')) . '</option>';
      foreach ($this->repo()->channels() as $channel) {
         $key = (string)($channel['channel_key'] ?? '');
         if ($key === '') {
            continue;
         }
         $channels .= '<option value="' . $this->h($key) . '">' . $this->h($channel['title'] ?? $key) . '</option>';
      }

      $groups = '<option value="0">' . $this->h($texts->get_fd_message('bulk_group_placeholder')) . '</option>';
      foreach ($this->repo()->groups() as $group) {
         $id = (int)($group['id'] ?? 0);
         if ($id <= 0) {
            continue;
         }
         $groups .= '<option value="' . $id . '">' . $this->h($group['title'] ?? '') . '</option>';
      }

      $url = function(string $do, array $params = array()) use ($baseAction): string {
         $query = $baseAction . '&dbx_do=' . rawurlencode($do);
         return $this->h(dbx()->append_url_params($query, $params));
      };

      $actions = '<option value="">' . $this->h($texts->get_fd_message('bulk_action_placeholder')) . '</option>'
         . '<option value="shop_products_delete">' . $this->h($texts->get_fd_message('bulk_delete')) . '</option>'
         . '<option value="shop_products_channel_add">' . $this->h($texts->get_fd_message('bulk_channel_add')) . '</option>'
         . '<option value="shop_products_channel_remove">' . $this->h($texts->get_fd_message('bulk_channel_remove')) . '</option>'
         . '<option value="shop_products_channel_export">' . $this->h($texts->get_fd_message('bulk_channel_export')) . '</option>'
         . '<option value="shop_products_group_set">' . $this->h($texts->get_fd_message('bulk_group_set')) . '</option>';

      return '<div class="dbx-shop-products-bulk-actions">'
         . '<select class="form-select form-select-sm" name="dbx_products_bulk_action" data-dbx-tooltip="' . $this->h($texts->get_fd_message('bulk_title')) . '">' . $actions . '</select>'
         . '<select class="form-select form-select-sm" name="dbx_action_channel" data-dbx-tooltip="' . $this->h($texts->get_fd_message('bulk_channel_title')) . '">' . $channels . '</select>'
         . '<select class="form-select form-select-sm" name="dbx_action_group" data-dbx-tooltip="' . $this->h($texts->get_fd_message('bulk_group_title')) . '">' . $groups . '</select>'
         . '<a class="btn btn-primary btn-sm dbxAjaxFormAction dbxConfirm" href="' . $url('shop_products_apply') . '" data-confirm-title="<i class=\'bi bi-lightning-fill\'></i> ' . $this->h($texts->get_fd_message('bulk_confirm_title')) . '" data-confirm="' . $this->h($texts->get_fd_message('bulk_confirm_question')) . '" data-confirm-hint="<small>' . $this->h($texts->get_fd_message('bulk_confirm_hint')) . '</small>" data-confirm-buttons="yesno" role="button"><i class="bi bi-check2-circle"></i> ' . $this->h($texts->get_fd_message('bulk_execute')) . '</a>'
         . '</div>';
   }



   private function handleProductReportAction($report): void {
      $do = (string)dbx()->get_modul_var('dbx_do', '', 'parameter');
      $mutatingActions = array(
         'row_delete',
         'shop_products_apply',
         'shop_products_delete',
         'shop_products_channel_add',
         'shop_products_channel_remove',
         'shop_products_channel_export',
         'shop_products_group_set',
      );
      // row_delete ist bereits als dbxReport-Standardaktion zentral geprueft.
      // Die Shop-spezifischen Sammelaktionen behalten ihren fachlichen Scope.
      if ($do !== 'row_delete'
          && in_array($do, $mutatingActions, true)
          && !$this->checkActionToken('product_report_action')) {
         $report->_msg_error = $report->get_fd_message('token_error');
         return;
      }

      $rid = (int)dbx()->get_modul_var('rid', 0, 'int');
      if ($do === 'row_delete') {
         if ($rid <= 0) {
            $report->_msg_error = $report->get_fd_message('product_delete_error');
            return;
         }
         $count = $this->repo()->deleteProducts(array($rid));
         $report->del_multi_select($rid);
         $report->_msg_success = $count === 1
            ? $report->get_fd_message('product_delete_success')
            : '';
         $report->_msg_error = $count === 1
            ? ''
            : $report->get_fd_message('product_delete_error');
         return;
      }

      if ($do === 'shop_products_apply') {
         $do = (string)dbx()->get_modul_var('dbx_products_bulk_action', '', 'parameter');
      }

      if (!in_array($do, array('shop_products_delete', 'shop_products_channel_add', 'shop_products_channel_remove', 'shop_products_channel_export', 'shop_products_group_set'), true)) {
         if ((string)dbx()->get_modul_var('dbx_do', '', 'parameter') === 'shop_products_apply') {
            $report->_msg_error = $report->get_fd_message('choose_action');
         }
         return;
      }

      $ids = $this->selectedProductIds($report);
      if ($ids === array()) {
         $report->_msg_error = $report->get_fd_message('select_products');
         return;
      }

      if ($do === 'shop_products_delete') {
         $count = $this->repo()->deleteProducts($ids);
         foreach ($ids as $id) {
            $report->del_multi_select($id);
         }
         $report->_msg_success = $count === 1
            ? $report->get_fd_message('multi_deleted_one')
            : $report->format_fd_message(
               'multi_deleted_many',
               array('count' => $count)
            );
         return;
      }

      if ($do === 'shop_products_channel_add') {
         $channel = trim((string)dbx()->get_modul_var('dbx_action_channel', '', 'parameter'));
         if ($channel === '') {
            $report->_msg_error = $report->get_fd_message('choose_channel');
            return;
         }
         $count = $this->repo()->addChannelToProducts($ids, $channel);
         $report->_msg_success = $report->format_fd_message(
            'channel_added',
            array('count' => $count, 'channel' => $channel)
         );
         return;
      }

      if ($do === 'shop_products_channel_remove') {
         $channel = trim((string)dbx()->get_modul_var('dbx_action_channel', '', 'parameter'));
         if ($channel === '') {
            $report->_msg_error = $report->get_fd_message('choose_channel');
            return;
         }
         $count = $this->repo()->removeChannelFromProducts($ids, $channel);
         $report->_msg_success = $report->format_fd_message(
            'channel_removed',
            array('count' => $count, 'channel' => $channel)
         );
         return;
      }

      if ($do === 'shop_products_channel_export') {
         $channel = trim((string)dbx()->get_modul_var('dbx_action_channel', '', 'parameter'));
         if ($channel === '') {
            $report->_msg_error = $report->get_fd_message('choose_channel');
            return;
         }
         $summary = $this->repo()->exportProductsToChannel($ids, $channel);
         $report->_msg_success = $report->format_fd_message(
            'export_summary',
            array(
               'ok' => (int)($summary['ok'] ?? 0),
               'failed' => (int)($summary['failed'] ?? 0),
            )
         );
         if (!empty($summary['messages'])) {
            $report->_msg_info = implode('<br>', array_map(fn($msg) => $this->h($msg), array_slice((array)$summary['messages'], 0, 8)));
         }
         return;
      }

      if ($do === 'shop_products_group_set') {
         $groupId = (int)dbx()->get_modul_var('dbx_action_group', 0, 'int');
         if ($groupId <= 0) {
            $report->_msg_error = $report->get_fd_message('choose_group');
            return;
         }
         $count = $this->repo()->setProductGroupForProducts($ids, $groupId);
         $report->_msg_success = $report->format_fd_message(
            'group_set',
            array('count' => $count)
         );
      }
   }
}
