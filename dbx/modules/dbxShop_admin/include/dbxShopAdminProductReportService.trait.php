<?php
namespace dbx\dbxShop_admin;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;
use dbx\dbxShop\dbxShopSearch;

require_once dirname(__DIR__, 2) . '/dbxShop/include/dbxShopSearch.class.php';

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
      string $empty_label = 'keine Werte'
   ): string {
      $html = '';
      foreach ($items as $item) {
         $value = trim((string)($item[$key] ?? ''));
         if ($value === '') continue;
         $html .= '<span class="badge text-bg-light border">' . $this->h($value) . '</span>';
      }
      if ($html === '') {
         return '<span class="text-muted small">' . $this->h($empty_label) . '</span>';
      }
      $class = trim('dbx-shop-report-chip-grid ' . $class);
      return '<div class="' . $this->h($class) . '">' . $html . '</div>';
   }



   private function attribute_badges(
      array $product,
      string $empty_label = 'keine Werte'
   ): string {
      $html = '';
      foreach (($product['attributes'] ?? array()) as $attribute) {
         $value = trim((string)($attribute['display_value'] ?? $attribute['value_text'] ?? ''));
         if ($value === '') continue;
         $html .= '<span class="badge text-bg-info text-start text-wrap">' . $this->h($attribute['title'] ?? '') . ': ' . $this->h($value) . '</span>';
      }
      return $html !== ''
         ? '<div class="dbx-shop-report-chip-grid dbx-shop-report-chip-grid-attributes">' . $html . '</div>'
         : '<span class="text-muted small">' . $this->h($empty_label) . '</span>';
   }



   private function attribute_options(string $options): array {
      $items = preg_split('~[|;\r\n]+~', $options) ?: array();
      return array_values(array_filter(array_map('trim', $items), fn($item) => $item !== ''));
   }






   private function shop_config(): array {
      $cfg = dbx()->get_cfg('dbxShop');
      return is_array($cfg) ? $cfg : array();
   }



   private function channels_enabled(): bool {
      $value = strtolower(trim((string)dbx()->get_cfg('dbxShop', 'channels_enabled')));
      return !in_array($value, array('0', 'false', 'off', 'no', 'nein'), true);
   }



   private function tax_rates_config(): array {
      $cfg = $this->shop_config();
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














   private function product_group_text(array $product): string {
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
      $attributes = dbxShopSearch::normalized_text(\dbx\dbxShop\dbxShopValue::attribute_text($product));
      $groups = dbxShopSearch::normalized_text($this->product_group_text($product));

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



   private function product_sort_value(array $product, string $sort) {
      switch ($sort) {
         case 'sku':
         case 'title':
            return dbxShopSearch::normalized_text((string)($product[$sort] ?? ''));
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



   private function sort_products_for_report(array $products, string $query, string $sort, string $direction): array {
      $has_query = dbxShopSearch::terms($query) !== array();
      $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
      usort($products, function(array $a, array $b) use ($has_query, $sort, $direction): int {
         if ($has_query && (int)($a['_search_score'] ?? 0) !== (int)($b['_search_score'] ?? 0)) {
            return (int)($b['_search_score'] ?? 0) <=> (int)($a['_search_score'] ?? 0);
         }

         $av = $this->product_sort_value($a, $sort);
         $bv = $this->product_sort_value($b, $sort);
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
      $product_id = (int)($record['id'] ?? 0);
      $product_title = trim((string)($record['title'] ?? $sku));
      $summary = trim((string)($record['summary'] ?? ''));
      $image = $record['images'][0] ?? array();
      $img_url = is_array($image) ? \dbx\dbxShop\dbxShopMediaUrl::item($image, true) : '';
      $empty_label = $report->get_fd_message('no_values');

      $record['image_view'] = $img_url !== ''
         ? '<span class="dbx-shop-report-image"><img src="' . $this->h($img_url) . '" alt="" loading="lazy"></span>'
         : '<span class="text-muted small">' . $this->h($report->get_fd_message('no_image')) . '</span>';
      $record['article_view'] = '<div class="dbx-shop-report-article-scroll"><code class="dbx-shop-report-sku">' . $this->h($sku) . '</code>'
         . '<br><strong>' . $this->h($product_title) . '</strong>'
         . ($summary !== '' ? '<br><small class="text-muted">' . $this->h($summary) . '</small>' : '')
         . '</div>';
      $record['groups_view'] = $this->chips($record['groups'] ?? array(), 'title', '', $empty_label);
      $record['attributes_view'] = $this->attribute_badges($record, $empty_label);
      $record['shipping_groups_view'] = $this->chips($record['shipping_groups'] ?? array(), 'title', '', $empty_label);
      $record['channel_groups_view'] = $this->chips($record['channel_groups'] ?? array(), 'title', '', $empty_label);
      $record['channels_view'] = $this->chips($record['channels'] ?? array(), 'title', 'dbx-shop-report-chip-grid-channels', $empty_label);
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



   private function product_tree_panel(array $products, $texts): string {
      $groups = $this->repo()->groups();
      $groups_by_parent = array();
      foreach ($groups as $group) {
         $parent_id = (int)($group['parent_id'] ?? 0);
         $groups_by_parent[$parent_id][] = $group;
      }

      $products_by_group = array();
      foreach ($products as $product) {
         $group_id = (int)($product['product_group_id'] ?? 0);
         if ($group_id <= 0 && isset($product['groups'][0])) {
            $group_id = (int)($product['groups'][0]['id'] ?? 0);
         }
         $products_by_group[$group_id][] = $product;
      }

      $render_products = function(int $group_id, bool $as_list_item = false) use (&$products_by_group, $texts): string {
         $items = '';
         foreach (($products_by_group[$group_id] ?? array()) as $product) {
            $id = (int)($product['id'] ?? 0);
            $title = trim((string)($product['title'] ?? $texts->get_fd_message('tree_product_fallback')));
            $sku = trim((string)($product['sku'] ?? ''));
            if ($id <= 0) continue;
            $search_text = trim($title . ' ' . $sku);
            $items .= '<li class="dbx-shop-tree-product" draggable="true" data-shop-tree-node="product" data-shop-tree-product="' . $id . '" data-shop-tree-search-text="' . $this->h($search_text) . '">';
            $items .= '<span class="dbx-shop-tree-product-main"><i class="bi bi-box-seam"></i><span><strong>' . $this->h($title) . '</strong>' . ($sku !== '' ? '<small>' . $this->h($sku) . '</small>' : '') . '</span></span>';
            $items .= '<a class="btn btn-outline-primary btn-sm openWin dbx-win" href="?dbx_modul=dbxShop_admin&amp;dbx_run1=product_edit&amp;id=' . $id . '" data-dbx-tooltip="' . $this->h($texts->get_fd_message('tree_edit_product')) . '"><i class="bi bi-pencil"></i></a>';
            $items .= '</li>';
         }
         if ($items === '') {
            return '';
         }
         $html = '<ul class="dbx-shop-tree-products">' . $items . '</ul>';
         return $as_list_item ? '<li class="dbx-shop-tree-product-list">' . $html . '</li>' : $html;
      };

      $render_group = function(array $group) use (&$render_group, &$groups_by_parent, $render_products, $texts): string {
         $id = (int)($group['id'] ?? 0);
         if ($id <= 0) return '';
         $title = trim((string)($group['title'] ?? $texts->get_fd_message('tree_group_fallback')));
         $child_html = '';
         foreach (($groups_by_parent[$id] ?? array()) as $child) {
            $child_html .= $render_group($child);
         }
         $products_html = $render_products($id, true);
         $count_children = count($groups_by_parent[$id] ?? array());
         $count_products = substr_count($products_html, 'data-shop-tree-node="product"');
         $has_children = $child_html !== '' || $products_html !== '';
         $html = '<li class="dbx-shop-tree-group" data-shop-tree-group-wrap data-shop-tree-search-text="' . $this->h($title) . '">';
         $html .= '<div class="dbx-shop-tree-group-head" draggable="true" data-shop-tree-node="group" data-shop-tree-group="' . $id . '" data-shop-tree-drop="' . $id . '">';
         $html .= '<span class="dbx-shop-tree-group-main">';
         if ($has_children) {
            $html .= '<button type="button" class="dbx-shop-tree-group-toggle" data-shop-tree-group-toggle data-dbx-tooltip="' . $this->h($texts->get_fd_message('tree_toggle_group')) . '" aria-label="' . $this->h($texts->get_fd_message('tree_toggle_group')) . '" aria-expanded="true"><i class="bi bi-chevron-down"></i></button>';
         } else {
            $html .= '<span class="dbx-shop-tree-toggle-spacer"></span>';
         }
         $html .= '<i class="bi bi-folder2"></i><span><strong>' . $this->h($title) . '</strong><small>' . $this->h($texts->format_fd_message('tree_counts', array('groups' => $count_children, 'products' => $count_products))) . '</small></span></span>';
         $html .= '<a class="btn btn-outline-secondary btn-sm openWin dbx-win" href="?dbx_modul=dbxShop_admin&amp;dbx_run1=groups" data-url="?dbx_modul=dbxShop_admin&amp;dbx_run1=groups" data-title="' . $this->h($texts->get_fd_message('tree_edit_groups')) . '" data-width="54%" data-height="84%" title="' . $this->h($texts->get_fd_message('tree_edit_groups')) . '"><i class="bi bi-diagram-3"></i></a>';
         $html .= '</div>';
         if ($has_children) {
            $html .= '<ul class="dbx-shop-tree-children">' . $child_html . $products_html . '</ul>';
         }
         $html .= '</li>';
         return $html;
      };

      $root_groups = '';
      foreach (($groups_by_parent[0] ?? array()) as $group) {
         $root_groups .= $render_group($group);
      }
      $ungrouped = $render_products(0, false);
      $search = $this->tpl()->get_tpl('dbx|search', dbx()->get_system_obj('dbxSearchDefaults')->build(array(
         'name' => 'shop_tree_search',
         'placeholder' => $texts->get_fd_message('tree_search_placeholder'),
         'title' => $texts->get_fd_message('tree_search_title'),
         'wrap_class' => 'dbx-shop-tree-search-wrap',
         'extra_attrs' => 'data-shop-tree-search',
         'i' => 1,
      )));
      $tree_move_url = str_replace('&', '&amp;', $this->action_url('?dbx_modul=dbxShop_admin&dbx_run1=product_tree_move'));
      $html = '<section class="dbx-shop-product-tree-panel" data-shop-tree-panel data-shop-tree-moveurl="' . $tree_move_url . '" aria-label="' . $this->h($texts->get_fd_message('tree_aria')) . '">';
      $html .= '<div class="dbx-shop-product-tree-head"><div><h3>' . $this->h($texts->get_fd_message('tree_title')) . '</h3><p>' . $this->h($texts->get_fd_message('tree_subtitle')) . '</p></div><div class="dbx-shop-product-tree-actions"><a class="btn btn-outline-primary btn-sm openWin dbx-win" href="?dbx_modul=dbxShop_admin&amp;dbx_run1=groups" data-url="?dbx_modul=dbxShop_admin&amp;dbx_run1=groups" data-title="' . $this->h($texts->get_fd_message('tree_edit_groups')) . '" data-width="54%" data-height="84%"><i class="bi bi-diagram-3"></i> ' . $this->h($texts->get_fd_message('tree_edit_groups_button')) . '</a><button type="button" class="btn btn-outline-secondary btn-sm" data-shop-tree-close data-dbx-tooltip="' . $this->h($texts->get_fd_message('tree_close')) . '" aria-label="' . $this->h($texts->get_fd_message('tree_close')) . '"><i class="bi bi-x-lg"></i></button></div></div>';
      $html .= '<div class="dbx-shop-tree-tools">' . $search . '</div>';
      $html .= '<ul class="dbx-shop-tree-list">' . $root_groups . '</ul>';
      if ($ungrouped !== '') {
         $html .= '<div class="dbx-shop-tree-ungrouped"><strong>' . $this->h($texts->get_fd_message('tree_ungrouped')) . '</strong>' . $ungrouped . '</div>';
      }
      $html .= '</section>';
      return $html;
   }



   private function product_tree_toggle_button($texts): string {
      $label = $this->h($texts->get_fd_message('tree_open'));
      return '<button type="button" class="btn btn-outline-secondary btn-sm dbx-shop-product-tree-toggle" data-dbx="lib=shopAdmin|module=dbxShop_admin" data-shop-tree-toggle data-dbx-tooltip="' . $label . '" aria-label="' . $label . '" aria-expanded="false"><i class="bi bi-diagram-3"></i></button>';
   }



   private function selected_product_ids($report): array {
      $ids = array();
      foreach (array_keys($report->get_multi_selects()) as $id) {
         $id = (int)$id;
         if ($id > 0) {
            $ids[$id] = $id;
         }
      }
      return array_values($ids);
   }



   private function product_report_action_controls(string $base_action, $texts): string {
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

      $url = function(string $do, array $params = array()) use ($base_action): string {
         $query = $base_action . '&dbx_do=' . rawurlencode($do);
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



   private function handle_product_report_action($report): void {
      $do = (string)dbx()->get_modul_var('dbx_do', '', 'parameter');
      $mutating_actions = array(
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
          && in_array($do, $mutating_actions, true)
          && !$this->check_action_token('product_report_action')) {
         $report->_msg_error = $report->get_fd_message('token_error');
         return;
      }

      $rid = (int)dbx()->get_modul_var('rid', 0, 'int');
      if ($do === 'row_delete') {
         if ($rid <= 0) {
            $report->_msg_error = $report->get_fd_message('product_delete_error');
            return;
         }
         $count = $this->repo()->delete_products(array($rid));
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

      $ids = $this->selected_product_ids($report);
      if ($ids === array()) {
         $report->_msg_error = $report->get_fd_message('select_products');
         return;
      }

      if ($do === 'shop_products_delete') {
         $count = $this->repo()->delete_products($ids);
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
         $count = $this->repo()->add_channel_to_products($ids, $channel);
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
         $count = $this->repo()->remove_channel_from_products($ids, $channel);
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
         $summary = $this->repo()->export_products_to_channel($ids, $channel);
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
         $group_id = (int)dbx()->get_modul_var('dbx_action_group', 0, 'int');
         if ($group_id <= 0) {
            $report->_msg_error = $report->get_fd_message('choose_group');
            return;
         }
         $count = $this->repo()->set_product_group_for_products($ids, $group_id);
         $report->_msg_success = $report->format_fd_message(
            'group_set',
            array('count' => $count)
         );
      }
   }
}
