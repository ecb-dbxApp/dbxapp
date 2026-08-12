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


   private function cardTemplateOptions(string $selected): string {
      return $this->optionsHtml(array(
         'product-card-default' => 'Standardkarte',
         'product-card-compact' => 'Kompaktkarte',
      ), $selected);
   }



   private function detailTemplateOptions(string $selected): string {
      return $this->optionsHtml(array(
         'product-detail-default' => 'Standarddetail',
         'product-detail-technical' => 'Technische Ansicht',
      ), $selected);
   }



   private function galleryTemplateOptions(string $selected): string {
      return $this->optionsHtml(array(
         'image-gallery' => 'Bild Gallery',
         'file-gallery' => 'Datei Gallery',
      ), $selected);
   }



   private function galleryOverflowOptions(string $selected): string {
      return $this->optionsHtml(array(
         'grid' => 'Grid',
         'slider' => 'Slider',
         'scroll' => 'Scroll',
         'laufband' => 'Laufband',
         'tutorial' => 'Tutorial',
      ), $selected);
   }



   private function galleryClickOptions(string $selected): string {
      return $this->optionsHtml(array(
         'lightbox' => 'Lightbox',
         'none' => 'Kein Klick',
         'newtab' => 'Neuer Tab',
         'viewerjs' => 'ViewerJS',
         'photoswipe' => 'PhotoSwipe',
      ), $selected);
   }



   private function shopAdminStyle(): string {
      $file = dirname(__DIR__) . '/design/css/shop-admin.css';
      if (!is_file($file)) {
         return '';
      }
      return '<style>' . file_get_contents($file) . '</style>';
   }



   private function frame(string $content, string $title = 'Shop Administration', string $barActions = ''): string {
      if ($this->postedFormError !== '') {
         $content = '<div class="alert alert-danger mx-3 mt-3 mb-0" role="alert">'
            . $this->h($this->postedFormError)
            . '</div>'
            . $content;
         $this->postedFormError = '';
      }

      return $this->tpl()->get_tpl('dbxShop_admin|admin-shell', array(
         'shop_admin_style' => $this->shopAdminStyle(),
         'bar_title' => $this->h($title),
         'bar_icon' => 'bi-bag-check',
         'bar_subtitle' => $this->h($this->catalogTexts()->get_fd_message('admin_subtitle')),
         'bar_title_pre' => '',
         'bar_title_heading_attrs' => '',
         'bar_middle' => '',
         'bar_extra' => '',
         'bar_actions' => $barActions,
         'content' => $content,
      ));
   }



   private function productBarActions($texts = null): string {
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
         . $this->helpButton($this->ensureShopProductsHelpPage(), $help);
   }



   private function productShellActions($texts = null): string {
      return $this->productBarActions($texts);
   }



   private function productFormDefaults(int $id): array {
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



   private function newProductDefaults(): array {
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



   private function applyProductPreset(array $data): array {
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



   private function productFormActions(int $id, $texts): string {
      $html = $this->helpButton(
         $this->ensureShopProductsHelpPage(),
         $texts->get_fd_message('help_edit'),
         'btn btn-outline-secondary btn-sm ms-1'
      )
         . '<button class="btn btn-primary btn-sm" type="submit" data-dbx-tooltip="' . $this->h($texts->get_fd_message('save_title')) . '"><i class="bi bi-save"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('save_label')) . '</span></button>';

      if ($id > 0) {
         $product = $this->repo()->productById($id);
         $previewUrl = '?dbx_modul=dbxShop&dbx_run1=product&sku=' . rawurlencode((string)($product['sku'] ?? ''));
         $deleteUrl = $this->actionUrl('?dbx_modul=dbxShop_admin&dbx_run1=products&dbx_do=row_delete&rid=' . $id);
         $html .= $this->openWinButton(
            $previewUrl,
            $texts->get_fd_message('view_product'),
            '<i class="bi bi-search"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('preview')) . '</span>',
            'btn btn-outline-primary btn-sm ms-1',
            '82%',
            '82%'
         );
         $html .= $this->openWinButton(
            '?dbx_modul=dbxShop_admin&dbx_run1=product_attributes&id=' . $id,
            $texts->get_fd_message('product_attributes'),
            '<i class="bi bi-sliders"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('attributes')) . '</span>',
            'btn btn-outline-primary btn-sm ms-1',
            '76%',
            '78%'
         );
         $html .= '<a class="btn btn-outline-danger btn-sm ms-1 dbxConfirm" href="' . $this->h($deleteUrl) . '" data-confirm-title="' . $this->h($texts->get_fd_message('delete_title')) . '" data-confirm="' . $this->h($texts->get_fd_message('delete_confirm')) . '" data-confirm-buttons="yesno" title="' . $this->h($texts->get_fd_message('delete_title')) . '"><i class="bi bi-trash"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('delete_label')) . '</span></a>';
      }

      $html .= '<a class="btn btn-outline-secondary btn-sm ms-1" href="?dbx_modul=dbxShop_admin&dbx_run1=products" data-dbx-tooltip="' . $this->h($texts->get_fd_message('product_list_title')) . '"><i class="bi bi-table"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('product_list')) . '</span></a>';

      return $html;
   }



   private function productGroupOptions(int $excludeId = 0, bool $withNone = false, $texts = null): array {
      $groups = $this->repo()->groups();
      $byParent = array();
      foreach ($groups as $group) {
         $parentId = (int)($group['parent_id'] ?? 0);
         $byParent[$parentId][] = $group;
      }

      $texts = $texts ?: $this->catalogTexts();
      $options = $withNone ? array('0' => $texts->get_fd_message('groups_no_parent')) : array();
      $walk = function(int $parentId, string $prefix) use (&$walk, &$options, $byParent, $excludeId): void {
         foreach (($byParent[$parentId] ?? array()) as $group) {
            $id = (int)($group['id'] ?? 0);
            if ($id <= 0 || $id === $excludeId) {
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



   private function shopMediaConfig(): array {
      return array(
         'media' => $this->cmsEndpoint('cms_media', array('images' => 1, 'media_type' => 'image'), true),
         'uploadmediafolder' => 'img/shop',
         'upload' => $this->cmsEndpoint('cms_upload', array(), true),
         'externalvideo' => $this->cmsEndpoint('cms_external_video', array(), true),
         'mediafolders' => $this->cmsEndpoint('cms_media_folders'),
         'mediafoldercreate' => $this->cmsEndpoint('cms_media_folder_create', array(), true),
         'mediafolderdelete' => $this->cmsEndpoint('cms_media_folder_delete', array(), true),
         'mediafolderrename' => $this->cmsEndpoint('cms_media_folder_rename', array(), true),
         'mediamove' => $this->cmsEndpoint('cms_media_move', array(), true),
         'mediaunused' => $this->cmsEndpoint('cms_media_unused'),
         'mediaprocess' => $this->cmsEndpoint('cms_media_process', array(), true),
         'deletemedia' => $this->cmsEndpoint('cms_delete_media', array(), true),
         'editmedia' => $this->cmsEndpoint('cms_edit_media', array(), true),
         'assignurl' => $this->shopEndpoint('assign_media', array(), true),
      );
   }



   private function shopMediaAttrs(array $mediaCfg): string {
      $attrs = ' data-dbx="lib=shopAdmin"';
      foreach ($mediaCfg as $key => $value) {
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
   private function shopMediaFormTemplates(array $mediaCfg): string {
      return dbx()->get_include_obj('dbxContentMediaForms', 'dbxContent_admin')->renderTemplates(
         (string)($mediaCfg['upload'] ?? ''),
         'cms-media-upload',
         (string)($mediaCfg['externalvideo'] ?? ''),
         'cms-external-video'
      );
   }



   private function productImagesPanel(array $product, bool $isNew, $texts): string {
      if ($isNew) {
         return '<aside class="border rounded bg-light p-3"><h6 class="mb-3">'
            . $this->h($texts->get_fd_message('images_title'))
            . '</h6><div class="alert alert-info mb-0">'
            . $this->h($texts->get_fd_message('save_first_images'))
            . '</div></aside>';
      }

      $productId = (int)($product['id'] ?? 0);
      $mediaCfg = $this->shopMediaConfig();
      $html = '<aside class="border rounded bg-light p-3 dbx-shop-media-manager dbx-shop-product-image-panel"' . $this->shopMediaAttrs($mediaCfg) . '>';
      $html .= '<input type="hidden" value="' . $productId . '" data-shop-product-select>';
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
            $imageId = (int)($image['id'] ?? 0);
            $source = (int)($image['product_id'] ?? 0) === $productId
               ? $texts->get_fd_message('image_source_product')
               : $texts->get_fd_message('image_source_group');
            $primary = (int)($image['is_primary'] ?? 0) === 1
               ? '<span class="badge text-bg-primary ms-1">' . $this->h($texts->get_fd_message('primary')) . '</span>'
               : '';
            $title = trim((string)($image['title'] ?? ''));
            if ($title === '') {
               $title = basename((string)($image['image_path'] ?? 'Bild'));
            }
            $removeUrl = $this->actionUrl('?dbx_modul=dbxShop_admin&dbx_run1=product_edit&id=' . $productId . '&remove_image=' . $imageId);
            $html .= '<figure class="dbx-shop-image-card">';
            if ($imageId > 0) {
               $html .= '<a class="btn btn-outline-danger btn-sm dbxAjax dbxConfirm dbx-shop-image-unassign" href="' . $this->h($removeUrl) . '" data-confirm-title="<i class=\'bi bi-trash\'></i> ' . $this->h($texts->get_fd_message('unlink_image_title')) . '" data-confirm="' . $this->h($texts->get_fd_message('unlink_image_question')) . '" data-confirm-hint="<small>' . $this->h($texts->get_fd_message('unlink_image_hint')) . '</small>" data-confirm-buttons="yesno" title="' . $this->h($texts->get_fd_message('unlink_image_title')) . '"><i class="bi bi-trash"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('unlink_image_label')) . '</span></a>';
            }
            $html .= '<img src="' . $this->h($this->mediaItemUrl($image, true)) . '" alt="' . $this->h($image['alt'] ?? $title) . '">';
            $html .= '<figcaption><strong>' . $this->h($title) . '</strong><br><span class="text-muted">' . $this->h($source) . '</span>' . $primary . '</figcaption>';
            $html .= '</figure>';
         }
         $html .= '</div>';
      }

      $html .= '<div class="form-text mt-3">' . $this->h($texts->get_fd_message('media_hint')) . '</div>';
      $html .= '</aside>';
      return $html;
   }



   private function productGroupImagePanel(array $group, bool $isNew, $texts = null): string {
      $texts = $texts ?: $this->catalogTexts();
      if ($isNew) {
         return '<div class="dbx-shop-group-image-panel dbx-shop-group-image-empty"><div class="form-text">' . $this->h($texts->get_fd_message('group_image_save_first')) . '</div></div>';
      }

      $groupId = (int)($group['id'] ?? 0);
      if ($groupId <= 0) {
         return '';
      }

      $mediaCfg = $this->shopMediaConfig();
      $image = $this->repo()->primaryImageForGroup($groupId);
      $html = '<div class="dbx-shop-media-manager dbx-shop-group-image-panel"' . $this->shopMediaAttrs($mediaCfg) . '>';
      $html .= '<input type="hidden" value="0" data-shop-product-select>';
      $html .= '<input type="hidden" value="' . $groupId . '" data-shop-group-select>';
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
         $html .= '<figure class="dbx-shop-group-image-preview"><img src="' . $this->h($this->mediaItemUrl($image, true)) . '" alt="' . $this->h($image['alt'] ?? $title) . '"><figcaption>' . $this->h($title) . '</figcaption></figure>';
      } else {
         $html .= '<div class="dbx-shop-group-image-placeholder"><i class="bi bi-image"></i><span>' . $this->h($texts->get_fd_message('group_image_none')) . '</span></div>';
      }
      $html .= '<div class="form-text">' . $this->h($texts->get_fd_message('group_image_hint')) . '</div>';
      $html .= '</div>';
      return $html;
   }



   private function productChannelsPanel(array $product, bool $isNew, $texts): string {
      if (!$this->channelsEnabled()) {
         return '';
      }
      if ($isNew) {
         return '<aside class="border rounded bg-light p-3 mt-3"><h6 class="mb-3">'
            . $this->h($texts->get_fd_message('channels_title'))
            . '</h6><div class="alert alert-info mb-0">'
            . $this->h($texts->get_fd_message('save_first_channels'))
            . '</div></aside>';
      }

      $productId = (int)($product['id'] ?? 0);
      $overrides = $this->repo()->productChannelOverrides($productId);
      $inherited = $this->repo()->inheritedChannelsForProduct($productId);
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

            $hasOverride = isset($overrides[$key]);
            $isInherited = isset($inherited[$key]);
            $checked = $hasOverride
               ? (int)($overrides[$key]['active'] ?? 0) === 1
               : $isInherited;
            $source = $hasOverride
               ? ((int)($overrides[$key]['active'] ?? 0) === 1
                  ? $texts->get_fd_message('channel_direct_active')
                  : $texts->get_fd_message('channel_direct_inactive'))
               : ($isInherited
                  ? $texts->format_fd_message(
                     'channel_from_group_title',
                     array('groups' => implode(', ', array_values($inherited[$key]['group_titles'] ?? array())))
                  )
                  : $texts->get_fd_message('channel_not_set'));
            $sourceText = (!$hasOverride && $isInherited)
               ? $texts->get_fd_message('channel_from_group')
               : $source;
            $statusClass = $checked ? 'text-bg-success' : 'text-bg-secondary';
            $export = $overrides[$key] ?? array();
            $exportStatus = trim((string)($export['export_status'] ?? ''));
            $exportMessage = trim((string)($export['export_message'] ?? ''));
            $listingId = trim((string)($export['external_listing_id'] ?? ''));
            $exportBadgeClass = match ($exportStatus) {
               'published', 'exported', 'ready', 'manual_ready' => 'text-bg-info',
               'failed' => 'text-bg-danger',
               default => 'text-bg-light text-dark',
            };
            $exportText = $exportStatus !== ''
               ? $exportStatus
               : $texts->get_fd_message('channel_not_exported');
            $exportUrl = $this->actionUrl('?dbx_modul=dbxShop_admin&dbx_run1=product_edit&id=' . $productId . '&export_channel=' . rawurlencode($key));
            $mappingUrl = '?dbx_modul=dbxShop_admin&dbx_run1=product_channel_mapping&id=' . $productId . '&channel=' . rawurlencode($key);

            $html .= '<tr class="dbx-shop-product-channel-row">';
            $html .= '<td class="dbx-shop-product-channel-name">';
            $html .= '<label class="d-flex align-items-start gap-2 mb-0">';
            $html .= '<input class="form-check-input" type="checkbox" name="product_channels[]" value="' . $this->h($key) . '"' . ($checked ? ' checked' : '') . '>';
            $html .= '<span class="dbx-shop-product-channel-copy"><strong>' . $this->h($channel['title'] ?? $key) . '</strong><code>' . $this->h($key) . '</code></span>';
            $html .= '</label>';
            $html .= '</td>';
            $html .= '<td><span class="badge ' . $statusClass . '" data-dbx-tooltip="' . $this->h($source) . '">' . $this->h($sourceText) . '</span></td>';
            $html .= '<td><span class="badge ' . $exportBadgeClass . '" data-dbx-tooltip="' . $this->h($exportMessage) . '">' . $this->h($exportText) . '</span>';
            if ($listingId !== '') {
               $html .= '<code class="small d-block mt-1">' . $this->h($listingId) . '</code>';
            }
            $html .= '</td>';
            $html .= '<td class="text-end"><span class="dbx-shop-product-channel-actions">';
            $html .= $this->openWinButton(
               $mappingUrl,
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
               $html .= '<a class="btn btn-outline-primary btn-sm dbxConfirm" href="' . $this->h($exportUrl) . '" data-confirm-title="<i class=\'bi bi-broadcast\'></i> ' . $this->h($texts->get_fd_message('export_title')) . '" data-confirm="' . $this->h($texts->format_fd_message('export_question', array('channel' => (string)($channel['title'] ?? $key)))) . '" data-confirm-hint="<small>' . $this->h($texts->get_fd_message('export_hint')) . '</small>" data-confirm-buttons="yesno" title="' . $this->h($texts->get_fd_message('export_button_title')) . '"><i class="bi bi-broadcast"></i></a>';
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
