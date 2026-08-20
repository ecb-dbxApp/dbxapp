<?php
namespace dbx\dbxShop_admin;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

/**
 * Produktformulare, Bilder und Channel-Panels ueber dbxForm/dbxTPL.
 *
 * Zustandslose Quelltext-Komposition: keine zusaetzliche Instanz, kein Proxy
 * und keine zusaetzlichen Datenbank- oder Templatezugriffe.
 */
trait dbxShopAdminProductFormServiceTrait {

















   private function shop_admin_style(): string {
      $file = dirname(__DIR__) . '/design/css/shop-admin.css';
      if (!is_file($file)) {
         return '';
      }
      return '<style>' . file_get_contents($file) . '</style>';
   }



   private function frame(string $content, string $title = 'Shop Administration', string $bar_actions = ''): string {
      if ($this->posted_form_error !== '') {
         $content = '<div class="alert alert-danger mx-3 mt-3 mb-0" role="alert">'
            . $this->h($this->posted_form_error)
            . '</div>'
            . $content;
         $this->posted_form_error = '';
      }

      return $this->tpl()->get_tpl('dbxShop_admin|admin-shell', array(
         'shop_admin_style' => $this->shop_admin_style(),
         'bar_title' => $this->h($title),
         'bar_icon' => 'bi-bag-check',
         'bar_subtitle' => $this->h($this->catalog_texts()->get_fd_message('admin_subtitle')),
         'bar_title_pre' => '',
         'bar_title_heading_attrs' => '',
         'bar_middle' => '',
         'bar_extra' => '',
         'bar_actions' => $bar_actions,
         'content' => $content,
      ));
   }



   private function product_bar_actions($texts = null): string {
      $title = $texts && method_exists($texts, 'get_fd_message')
         ? $texts->get_fd_message('new_product_title', 'Neuen Artikel anlegen')
         : 'Neuen Artikel anlegen';
      $label = $texts && method_exists($texts, 'get_fd_message')
         ? $texts->get_fd_message('new_product', 'Neuer Artikel')
         : 'Neuer Artikel';
      $help = $texts && method_exists($texts, 'get_fd_message')
         ? $texts->get_fd_message('products_help', 'Hilfe: Produkte')
         : 'Hilfe: Produkte';

      return '<a class="btn btn-outline-primary btn-sm me-1" href="?dbx_modul=dbxShop_admin&dbx_run1=product_edit&id=0" data-dbx-tooltip="' . $this->h($title) . '"><i class="bi bi-plus-square"></i><span class="visually-hidden"> ' . $this->h($label) . '</span></a>'
         . $this->help_button($this->shop_products_help_context(), $help);
   }



   private function product_shell_actions($texts = null): string {
      return $this->product_bar_actions($texts);
   }



   private function product_form_defaults(int $id): array {
      $now = date('Y-m-d H:i:s');
      $uid = (int)dbx()->user();
      $defaults = array(
         'update_date' => $now,
         'update_uid' => $uid,
      );
      if ($id <= 0) {
         $defaults['create_date'] = $now;
         $defaults['create_uid'] = $uid;
         $defaults['owner'] = $uid;
         $defaults['trash'] = 0;
         $defaults['currency'] = 'EUR';
      }
      return $defaults;
   }



   private function new_product_defaults(): array {
      return array(
         'sku' => '',
         'slug' => '',
         'title' => '',
         'category' => 'Merchandise',
         'product_type' => 'physical',
         'summary' => '',
         'description' => '',
         'price_gross' => '0.00',
         'currency' => 'EUR',
         'tax_mode' => 'group',
         'tax_rate' => '-1',
         'shipping_mode' => 'group',
         'shipping_gross' => '-1',
         'stock' => '0',
         'active' => 1,
         'sorter' => 100,
         'badge' => '',
         'image_icon' => 'bi-box-seam',
         'logo_variant' => '',
      );
   }



   private function apply_product_preset(array $data): array {
      $preset = trim((string)dbx()->get_modul_var('workflow_preset', '', 'parameter'));
      if ($preset === 'shop_article_publish') {
         $data['category'] = $data['category'] ?: 'Merchandise';
         $data['product_type'] = $data['product_type'] ?: 'physical';
         $data['active'] = 1;
      }

      $map = array(
         'preset_sku' => 'sku',
         'preset_slug' => 'slug',
         'preset_title' => 'title',
         'preset_category' => 'category',
         'preset_product_type' => 'product_type',
         'preset_summary' => 'summary',
         'preset_price_gross' => 'price_gross',
         'preset_group_id' => 'product_group_id',
      );
      foreach ($map as $param => $field) {
         $value = trim((string)dbx()->get_modul_var($param, '', '*'));
         if ($value !== '') {
            $data[$field] = $value;
         }
      }

      return $data;
   }



   private function product_form_actions(int $id, $texts): string {
      $html = $this->help_button(
         $this->shop_products_help_context(),
         $texts->get_fd_message('help_edit'),
         'btn btn-outline-secondary btn-sm ms-1'
      )
         . '<button class="btn btn-primary btn-sm" type="submit" data-dbx-tooltip="' . $this->h($texts->get_fd_message('save_title')) . '"><i class="bi bi-save"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('save_label')) . '</span></button>';

      if ($id > 0) {
         $product = $this->repo()->product_by_id($id);
         $preview_url = '?dbx_modul=dbxShop&dbx_run1=product&sku=' . rawurlencode((string)($product['sku'] ?? ''));
         $delete_url = $this->action_url('?dbx_modul=dbxShop_admin&dbx_run1=products&dbx_do=row_delete&rid=' . $id);
         $html .= $this->open_win_button(
            $preview_url,
            $texts->get_fd_message('view_product'),
            '<i class="bi bi-search"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('preview')) . '</span>',
            'btn btn-outline-primary btn-sm ms-1',
            '82%',
            '82%'
         );
         $html .= $this->open_win_button(
            '?dbx_modul=dbxShop_admin&dbx_run1=product_attributes&id=' . $id,
            $texts->get_fd_message('product_attributes'),
            '<i class="bi bi-sliders"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('attributes')) . '</span>',
            'btn btn-outline-primary btn-sm ms-1',
            '76%',
            '78%'
         );
         $html .= '<a class="btn btn-outline-danger btn-sm ms-1 dbxConfirm" href="' . $this->h($delete_url) . '" data-confirm-title="' . $this->h($texts->get_fd_message('delete_title')) . '" data-confirm="' . $this->h($texts->get_fd_message('delete_confirm')) . '" data-confirm-buttons="yesno" title="' . $this->h($texts->get_fd_message('delete_title')) . '"><i class="bi bi-trash"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('delete_label')) . '</span></a>';
      }

      $html .= '<a class="btn btn-outline-secondary btn-sm ms-1" href="?dbx_modul=dbxShop_admin&dbx_run1=products" data-dbx-tooltip="' . $this->h($texts->get_fd_message('product_list_title')) . '"><i class="bi bi-table"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('product_list')) . '</span></a>';

      return $html;
   }



   private function product_group_options(int $exclude_id = 0, bool $with_none = false, $texts = null): array {
      $groups = $this->repo()->groups();
      $by_parent = array();
      foreach ($groups as $group) {
         $parent_id = (int)($group['parent_id'] ?? 0);
         $by_parent[$parent_id][] = $group;
      }

      $texts = $texts ?: $this->catalog_texts();
      $options = $with_none ? array('0' => $texts->get_fd_message('groups_no_parent')) : array();
      $walk = function(int $parent_id, string $prefix) use (&$walk, &$options, $by_parent, $exclude_id): void {
         foreach (($by_parent[$parent_id] ?? array()) as $group) {
            $id = (int)($group['id'] ?? 0);
            if ($id <= 0 || $id === $exclude_id) {
               continue;
            }
            $title = trim((string)($group['title'] ?? ''));
            if ($title === '') {
               $title = (string)($group['group_key'] ?? $id);
            }
            $label = $prefix !== '' ? $prefix . ' / ' . $title : $title;
            $options[(string)$id] = $label;
            $walk($id, $label);
         }
      };
      $walk(0, '');
      return $options;
   }



   private function shop_media_config(): array {
      return array(
         'media' => $this->cms_endpoint('cms_media', array('images' => 1, 'media_type' => 'image'), true),
         'uploadmediafolder' => 'img/shop',
         'upload' => $this->cms_endpoint('cms_upload', array(), true),
         'externalvideo' => $this->cms_endpoint('cms_external_video', array(), true),
         'mediafolders' => $this->cms_endpoint('cms_media_folders'),
         'mediafoldercreate' => $this->cms_endpoint('cms_media_folder_create', array(), true),
         'mediafolderdelete' => $this->cms_endpoint('cms_media_folder_delete', array(), true),
         'mediafolderrename' => $this->cms_endpoint('cms_media_folder_rename', array(), true),
         'mediamove' => $this->cms_endpoint('cms_media_move', array(), true),
         'mediaunused' => $this->cms_endpoint('cms_media_unused'),
         'mediaprocess' => $this->cms_endpoint('cms_media_process', array(), true),
         'deletemedia' => $this->cms_endpoint('cms_delete_media', array(), true),
         'editmedia' => $this->cms_endpoint('cms_edit_media', array(), true),
         'assignurl' => $this->shop_endpoint('assign_media', array(), true),
      );
   }



   private function shop_media_attrs(array $media_cfg): string {
      $attrs = ' data-dbx="lib=shopAdmin|module=dbxShop_admin"';
      foreach ($media_cfg as $key => $value) {
         $attrs .= ' data-shop-' . $this->h($key) . '="' . $this->h($value) . '"';
      }
      return $attrs;
   }



   /**
    * Rendert die durch dbxForm geschuetzten Medienbrowser-Formulare.
    *
    * Shop und CMS verwenden dieselben Upload-Endpunkte und deshalb bewusst
    * dieselben stabilen Formular-IDs. Der Rueckgabewert muss ausserhalb einer
    * bereits offenen dbxForm eingefuegt werden: Die inerten DOM-Templates
    * enthalten eigene Formulare und duerfen die umgebende Kartenform nicht
    * verschachteln oder vorzeitig schliessen.
    */
   private function shop_media_form_templates(array $media_cfg): string {
      return dbx()->get_include_obj('dbxContentMediaForms', 'dbxContent_admin')->renderTemplates(
         (string)($media_cfg['upload'] ?? ''),
         'cms-media-upload',
         (string)($media_cfg['externalvideo'] ?? ''),
         'cms-external-video'
      );
   }



   private function product_images_panel(array $product, bool $is_new, $texts): string {
      if ($is_new) {
         return '<aside class="border rounded bg-light p-3"><h6 class="mb-3">'
            . $this->h($texts->get_fd_message('images_title'))
            . '</h6><div class="alert alert-info mb-0">'
            . $this->h($texts->get_fd_message('save_first_images'))
            . '</div></aside>';
      }

      $product_id = (int)($product['id'] ?? 0);
      $media_cfg = $this->shop_media_config();
      $html = '<aside class="border rounded bg-light p-3 dbx-shop-media-manager dbx-shop-product-image-panel"' . $this->shop_media_attrs($media_cfg) . '>';
      $html .= '<input type="hidden" value="' . $product_id . '" data-shop-product-select>';
      $html .= '<input type="hidden" value="0" data-shop-group-select>';
      $html .= '<input type="hidden" value="100" data-shop-sorter>';
      $html .= '<div class="d-flex justify-content-between align-items-center gap-2 mb-3">';
      $html .= '<h6 class="mb-0">' . $this->h($texts->get_fd_message('images_title')) . '</h6>';
      $html .= '<button type="button" class="btn btn-outline-primary btn-sm dbx-shop-media-pick" data-shop-media-folder="img/shop" data-dbx-tooltip="' . $this->h($texts->get_fd_message('select_images_title')) . '"><i class="bi bi-images"></i><i class="bi bi-camera-video"></i><i class="bi bi-upload"></i><span>' . $this->h($texts->get_fd_message('selection')) . '</span></button>';
      $html .= '</div>';
      $html .= '<label class="form-check mb-3"><input class="form-check-input" type="checkbox" value="1" data-shop-primary> <span class="form-check-label">' . $this->h($texts->get_fd_message('new_primary')) . '</span></label>';

      $images = (array)($product['images'] ?? array());
      if ($images === array()) {
         $html .= '<div class="text-muted small">' . $this->h($texts->get_fd_message('no_images')) . '</div>';
      } else {
         $html .= '<div class="dbx-shop-image-list">';
         foreach ($images as $image) {
            $image_id = (int)($image['id'] ?? 0);
            $source = (int)($image['product_id'] ?? 0) === $product_id
               ? $texts->get_fd_message('image_source_product')
               : $texts->get_fd_message('image_source_group');
            $primary = (int)($image['is_primary'] ?? 0) === 1
               ? '<span class="badge text-bg-primary ms-1">' . $this->h($texts->get_fd_message('primary')) . '</span>'
               : '';
            $title = trim((string)($image['title'] ?? ''));
            if ($title === '') {
               $title = basename((string)($image['image_path'] ?? 'Bild'));
            }
            $remove_url = $this->action_url('?dbx_modul=dbxShop_admin&dbx_run1=product_edit&id=' . $product_id . '&remove_image=' . $image_id);
            $html .= '<figure class="dbx-shop-image-card">';
            if ($image_id > 0) {
               $html .= '<a class="btn btn-outline-danger btn-sm dbxAjax dbxConfirm dbx-shop-image-unassign" href="' . $this->h($remove_url) . '" data-confirm-title="<i class=\'bi bi-trash\'></i> ' . $this->h($texts->get_fd_message('unlink_image_title')) . '" data-confirm="' . $this->h($texts->get_fd_message('unlink_image_question')) . '" data-confirm-hint="<small>' . $this->h($texts->get_fd_message('unlink_image_hint')) . '</small>" data-confirm-buttons="yesno" title="' . $this->h($texts->get_fd_message('unlink_image_title')) . '"><i class="bi bi-trash"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('unlink_image_label')) . '</span></a>';
            }
            $html .= '<img src="' . $this->h(\dbx\dbxShop\dbxShopMediaUrl::item($image, true)) . '" alt="' . $this->h($image['alt'] ?? $title) . '">';
            $html .= '<figcaption><strong>' . $this->h($title) . '</strong><br><span class="text-muted">' . $this->h($source) . '</span>' . $primary . '</figcaption>';
            $html .= '</figure>';
         }
         $html .= '</div>';
      }

      $html .= '<div class="form-text mt-3">' . $this->h($texts->get_fd_message('media_hint')) . '</div>';
      $html .= '</aside>';
      return $html;
   }



   private function product_group_image_panel(array $group, bool $is_new, $texts = null): string {
      $texts = $texts ?: $this->catalog_texts();
      if ($is_new) {
         return '<div class="dbx-shop-group-image-panel dbx-shop-group-image-empty"><div class="form-text">' . $this->h($texts->get_fd_message('group_image_save_first')) . '</div></div>';
      }

      $group_id = (int)($group['id'] ?? 0);
      if ($group_id <= 0) {
         return '';
      }

      $media_cfg = $this->shop_media_config();
      $image = $this->repo()->primary_image_for_group($group_id);
      $html = '<div class="dbx-shop-media-manager dbx-shop-group-image-panel"' . $this->shop_media_attrs($media_cfg) . '>';
      $html .= '<input type="hidden" value="0" data-shop-product-select>';
      $html .= '<input type="hidden" value="' . $group_id . '" data-shop-group-select>';
      $html .= '<input type="hidden" value="10" data-shop-sorter>';
      $html .= '<input type="hidden" value="1" data-shop-primary>';
      $html .= '<div class="dbx-shop-group-image-head">';
      $html .= '<span>' . $this->h($texts->get_fd_message('group_image_title')) . '</span>';
      $html .= '<button type="button" class="btn btn-outline-primary btn-sm dbx-shop-media-pick" data-shop-media-folder="img/shop" data-dbx-tooltip="' . $this->h($texts->get_fd_message('group_image_select_title')) . '"><i class="bi bi-images"></i><span>' . $this->h($texts->get_fd_message('selection')) . '</span></button>';
      $html .= '</div>';
      if (is_array($image)) {
         $title = trim((string)($image['title'] ?? ''));
         if ($title === '') {
            $title = basename((string)($image['image_path'] ?? 'Gruppenbild'));
         }
         $html .= '<figure class="dbx-shop-group-image-preview"><img src="' . $this->h(\dbx\dbxShop\dbxShopMediaUrl::item($image, true)) . '" alt="' . $this->h($image['alt'] ?? $title) . '"><figcaption>' . $this->h($title) . '</figcaption></figure>';
      } else {
         $html .= '<div class="dbx-shop-group-image-placeholder"><i class="bi bi-image"></i><span>' . $this->h($texts->get_fd_message('group_image_none')) . '</span></div>';
      }
      $html .= '<div class="form-text">' . $this->h($texts->get_fd_message('group_image_hint')) . '</div>';
      $html .= '</div>';
      return $html;
   }



   private function product_channels_panel(array $product, bool $is_new, $texts): string {
      if (!$this->channels_enabled()) {
         return '';
      }
      if ($is_new) {
         return '<aside class="border rounded bg-light p-3 mt-3"><h6 class="mb-3">'
            . $this->h($texts->get_fd_message('channels_title'))
            . '</h6><div class="alert alert-info mb-0">'
            . $this->h($texts->get_fd_message('save_first_channels'))
            . '</div></aside>';
      }

      $product_id = (int)($product['id'] ?? 0);
      $overrides = $this->repo()->product_channel_overrides($product_id);
      $inherited = $this->repo()->inherited_channels_for_product($product_id);
      $html = '<aside class="border rounded bg-light p-3 mt-3 dbx-shop-product-channel-panel">';
      $html .= '<h6 class="mb-2">' . $this->h($texts->get_fd_message('channels_title')) . '</h6>';
      $html .= '<p class="form-text mb-3">' . $this->h($texts->get_fd_message('channels_info')) . '</p>';
      $html .= '<input type="hidden" name="product_channel_editor" value="1">';

      $channels = $this->repo()->channels();
      if ($channels === array()) {
         $html .= '<div class="text-muted small">' . $this->h($texts->get_fd_message('no_channels')) . '</div>';
      } else {
         $html .= '<div class="table-responsive dbx-shop-product-channel-table-wrap">';
         $html .= '<table class="table table-sm align-middle mb-0 dbx-shop-product-channel-table">';
         $html .= '<thead><tr><th>' . $this->h($texts->get_fd_message('table_channel')) . '</th><th>' . $this->h($texts->get_fd_message('table_status')) . '</th><th>' . $this->h($texts->get_fd_message('table_export')) . '</th><th class="text-end">' . $this->h($texts->get_fd_message('table_action')) . '</th></tr></thead><tbody>';
         foreach ($channels as $channel) {
            $key = trim((string)($channel['channel_key'] ?? ''));
            if ($key === '') {
               continue;
            }

            $has_override = isset($overrides[$key]);
            $is_inherited = isset($inherited[$key]);
            $checked = $has_override
               ? (int)($overrides[$key]['active'] ?? 0) === 1
               : $is_inherited;
            $source = $has_override
               ? ((int)($overrides[$key]['active'] ?? 0) === 1
                  ? $texts->get_fd_message('channel_direct_active')
                  : $texts->get_fd_message('channel_direct_inactive'))
               : ($is_inherited
                  ? $texts->format_fd_message(
                     'channel_from_group_title',
                     array('groups' => implode(', ', array_values($inherited[$key]['group_titles'] ?? array())))
                  )
                  : $texts->get_fd_message('channel_not_set'));
            $source_text = (!$has_override && $is_inherited)
               ? $texts->get_fd_message('channel_from_group')
               : $source;
            $status_class = $checked ? 'text-bg-success' : 'text-bg-secondary';
            $export = $overrides[$key] ?? array();
            $export_status = trim((string)($export['export_status'] ?? ''));
            $export_message = trim((string)($export['export_message'] ?? ''));
            $listing_id = trim((string)($export['external_listing_id'] ?? ''));
            $export_badge_class = match ($export_status) {
               'published', 'exported', 'ready', 'manual_ready' => 'text-bg-info',
               'failed' => 'text-bg-danger',
               default => 'text-bg-light text-dark',
            };
            $export_text = $export_status !== ''
               ? $export_status
               : $texts->get_fd_message('channel_not_exported');
            $export_url = $this->action_url('?dbx_modul=dbxShop_admin&dbx_run1=product_edit&id=' . $product_id . '&export_channel=' . rawurlencode($key));
            $mapping_url = '?dbx_modul=dbxShop_admin&dbx_run1=product_channel_mapping&id=' . $product_id . '&channel=' . rawurlencode($key);

            $html .= '<tr class="dbx-shop-product-channel-row">';
            $html .= '<td class="dbx-shop-product-channel-name">';
            $html .= '<label class="d-flex align-items-start gap-2 mb-0">';
            $html .= '<input class="form-check-input" type="checkbox" name="product_channels[]" value="' . $this->h($key) . '"' . ($checked ? ' checked' : '') . '>';
            $html .= '<span class="dbx-shop-product-channel-copy"><strong>' . $this->h($channel['title'] ?? $key) . '</strong><code>' . $this->h($key) . '</code></span>';
            $html .= '</label>';
            $html .= '</td>';
            $html .= '<td><span class="badge ' . $status_class . '" data-dbx-tooltip="' . $this->h($source) . '">' . $this->h($source_text) . '</span></td>';
            $html .= '<td><span class="badge ' . $export_badge_class . '" data-dbx-tooltip="' . $this->h($export_message) . '">' . $this->h($export_text) . '</span>';
            if ($listing_id !== '') {
               $html .= '<code class="small d-block mt-1">' . $this->h($listing_id) . '</code>';
            }
            $html .= '</td>';
            $html .= '<td class="text-end"><span class="dbx-shop-product-channel-actions">';
            $html .= $this->open_win_button(
               $mapping_url,
               $texts->format_fd_message(
                  'mapping_title',
                  array('channel' => (string)($channel['title'] ?? $key))
               ),
               '<i class="bi bi-sliders2"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('mapping_label')) . '</span>',
               'btn btn-outline-secondary btn-sm',
               '68%',
               '84%'
            );
            if ($checked && (int)($channel['export_enabled'] ?? 0) === 1) {
               $html .= '<a class="btn btn-outline-primary btn-sm dbxConfirm" href="' . $this->h($export_url) . '" data-confirm-title="<i class=\'bi bi-broadcast\'></i> ' . $this->h($texts->get_fd_message('export_title')) . '" data-confirm="' . $this->h($texts->format_fd_message('export_question', array('channel' => (string)($channel['title'] ?? $key)))) . '" data-confirm-hint="<small>' . $this->h($texts->get_fd_message('export_hint')) . '</small>" data-confirm-buttons="yesno" title="' . $this->h($texts->get_fd_message('export_button_title')) . '"><i class="bi bi-broadcast"></i></a>';
            }
            $html .= '</span></td>';
            $html .= '</tr>';
         }
         $html .= '</tbody></table></div>';
      }

      $html .= '</aside>';
      return $html;
   }
}
