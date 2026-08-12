<?php
namespace dbx\dbxShop_admin;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

/**
 * Katalogstammdaten fuer Gruppen, Attribute und Versand ueber dbxForm.
 *
 * Zustandslose Quelltext-Komposition: keine zusaetzliche Instanz, kein Proxy
 * und keine zusaetzlichen Datenbank- oder Templatezugriffe.
 */
trait dbxShopAdminCatalogServiceTrait {


   private function activeBadge(array $row, $texts = null): string {
      $texts = $texts ?: $this->catalogTexts();
      return ((int)($row['active'] ?? 0) === 1)
         ? '<span class="badge text-bg-success">' . $this->h($texts->get_fd_message('active')) . '</span>'
         : '<span class="badge text-bg-secondary">' . $this->h($texts->get_fd_message('inactive')) . '</span>';
   }



   private function groups(): string {
      $this->ensureSeed();
      $texts = $this->catalogTexts();
      if ($this->posted('delete_product_group')) {
         $this->repo()->deleteProductGroup((int)($_POST['id'] ?? 0));
      } elseif ($this->posted('save_product_group')) {
         $this->repo()->updateProductGroup((int)($_POST['id'] ?? 0), $_POST);
      }

      $cardHtml = function (array $group, bool $isNew = false) use ($texts): string {
         $id = (int)($group['id'] ?? 0);
         if ($isNew) {
            $title = '<span>' . $this->h($texts->get_fd_message('groups_new')) . '</span>';
            $subtitle = $texts->get_fd_message('groups_new_subtitle');
         } else {
            $title = '<code>' . $this->h($group['group_key'] ?? '') . '</code><span>' . $this->h($group['title'] ?? '') . '</span>';
            $subtitle = trim((string)($group['description'] ?? ''));
         }

         $form = $this->shopAdminCardForm(
            'shop-product-group-' . ($isNew ? 'new' : $id),
            'dbxShop|shopProductGroup',
            $group,
            $id,
            '?dbx_modul=dbxShop_admin&dbx_run1=groups' . ($isNew ? '&new=1' : ''),
            'save_product_group',
            'save_product_group',
            $title,
            $subtitle,
            'dbx-shop-product-group-card'
         );
         $form->add_rep('card_badges', $isNew ? '<span class="badge text-bg-primary">' . $this->h($texts->get_fd_message('new')) . '</span>' : $this->activeBadge($group, $texts));
         $form->add_rep('extra_hidden', '<input type="hidden" name="display_variant" value="' . $this->h($group['display_variant'] ?? 'gallery_grid') . '">');
         if (!$isNew) {
            $form->add_rep('delete_button', $this->shopAdminCardDeleteButton('delete_product_group', $texts->get_fd_message('groups_delete_title'), $texts->get_fd_message('groups_delete_confirm')));
         }
         if ($isNew) {
            $form->add_fld('group_key', tpl: 'text-label', placeholder: 'artikelgruppe-key', rules: '*|parameter|max=80');
         }
         $form->add_fld('parent_id', tpl: 'select-single-label', options: $this->productGroupOptions($id, true, $texts), rules: 'int');
         $form->add_fld('title', tpl: 'text-label', placeholder: $texts->get_fd_message('groups_title_placeholder'), rules: '*|max=160');
         $form->add_fld('description', tpl: 'textarea-label', placeholder: $texts->get_fd_message('description_placeholder'), data: 'rows=2');
         $form->add_fld('tax_class', tpl: 'select-single-label', options: 'mwst1=mwst1&mwst2=mwst2&mwst3=mwst3');
         $form->add_fld('card_template', tpl: 'select-single-label', options: array(
            'product-card-default' => $texts->get_fd_message('card_default'),
            'product-card-compact' => $texts->get_fd_message('card_compact'),
         ));
         $form->add_fld('detail_template', tpl: 'select-single-label', options: array(
            'product-detail-default' => $texts->get_fd_message('detail_default'),
            'product-detail-technical' => $texts->get_fd_message('detail_technical'),
         ));
         $form->add_fld('gallery_template', tpl: 'select-single-label', options: array(
            'image-gallery' => $texts->get_fd_message('gallery_images'),
            'file-gallery' => $texts->get_fd_message('gallery_files'),
         ));
         $form->add_fld('gallery_visible_count', tpl: 'text-label', rules: 'int');
         $form->add_fld('gallery_image_size', tpl: 'select-single-label', options: 'original=Original&cover=Cover&contain=Contain');
         $form->add_fld('gallery_lightbox_width', tpl: 'text-label', placeholder: '100vw');
         $form->add_fld('gallery_overflow', tpl: 'select-single-label', options: array(
            'grid' => 'Grid',
            'slider' => 'Slider',
            'scroll' => 'Scroll',
            'laufband' => $texts->get_fd_message('gallery_marquee'),
            'tutorial' => 'Tutorial',
         ));
         $form->add_fld('gallery_click', tpl: 'select-single-label', options: array(
            'lightbox' => 'Lightbox',
            'none' => $texts->get_fd_message('gallery_no_click'),
            'newtab' => $texts->get_fd_message('gallery_new_tab'),
            'viewerjs' => 'ViewerJS',
            'photoswipe' => 'PhotoSwipe',
         ));
         $form->add_fld('attribute_notes', tpl: 'textarea-label', placeholder: $texts->get_fd_message('attribute_notes_placeholder'), data: 'rows=2');
         $channelDefaults = '';
         if ($this->channelsEnabled()) {
            $form->add_fld('ebay_category_id', tpl: 'text-label', placeholder: '58058');
            $form->add_fld('amazon_product_type', tpl: 'text-label', placeholder: 'SOFTWARE / PRODUCT / SHIRT');
            $form->add_fld('kleinanzeigen_category_id', tpl: 'text-label', placeholder: 'category_12345');
            $form->add_fld('mobile_category_id', tpl: 'text-label', placeholder: 'car');
            $channelDefaults = '<div class="wide dbx-shop-channel-defaults">'
               . '<h6>' . $this->h($texts->get_fd_message('channel_defaults_title')) . '</h6>'
               . '<p>' . $this->h($texts->get_fd_message('channel_defaults_info')) . '</p>'
               . '<div class="dbx-shop-admin-card-grid dbx-shop-channel-default-grid">'
               . '<div>{obj:ebay_category_id}</div>'
               . '<div>{obj:amazon_product_type}</div>'
               . '<div>{obj:kleinanzeigen_category_id}</div>'
               . '<div>{obj:mobile_category_id}</div>'
               . '</div>'
               . '</div>';
         }
         $form->add_fld('sorter', tpl: 'text-label', rules: 'int');
         $form->add_fld('active', tpl: 'checkbox-label', rules: 'int');
         $groupImagePanel = $this->productGroupImagePanel($group, $isNew, $texts);
         $form->add_rep('form_body',
            '<div class="dbx-shop-admin-card-grid">'
            . ($isNew ? '<div>{obj:group_key}</div>' : '')
            . '<div>{obj:parent_id}</div>'
            . '<div>{obj:title}</div>'
            . '<div>{obj:tax_class}</div>'
            . '<div>{obj:sorter}</div>'
            . '<div>{obj:active}</div>'
            . '<div class="wide">' . $groupImagePanel . '</div>'
            . '<div class="wide">{obj:description}</div>'
            . '<div>{obj:card_template}</div>'
            . '<div>{obj:detail_template}</div>'
            . '<div>{obj:gallery_template}</div>'
            . '<div>{obj:gallery_visible_count}</div>'
            . '<div>{obj:gallery_image_size}</div>'
            . '<div>{obj:gallery_lightbox_width}</div>'
            . '<div>{obj:gallery_overflow}</div>'
            . '<div>{obj:gallery_click}</div>'
            . '<div class="wide">{obj:attribute_notes}</div>'
            . $channelDefaults
            . '</div>'
         );
         return $form->run();
      };

      $groups = $this->repo()->groups();
      $cards = '';
      if ((int)($_GET['new'] ?? 0) === 1) {
         $sorter = 10;
         foreach ($groups as $group) {
            $sorter = max($sorter, (int)($group['sorter'] ?? 0) + 10);
         }
         $cards .= $cardHtml(array(
            'tax_class' => (string)($this->shopConfig()['default_tax_class'] ?? 'mwst1'),
            'default_tax_rate' => 19,
            'parent_id' => 0,
            'display_variant' => 'gallery_grid',
            'card_template' => 'product-card-default',
            'detail_template' => 'product-detail-default',
            'gallery_template' => 'image-gallery',
            'gallery_visible_count' => 3,
            'gallery_image_size' => 'original',
            'gallery_lightbox_width' => '100vw',
            'gallery_overflow' => 'grid',
            'gallery_click' => 'lightbox',
            'active' => 1,
            'sorter' => $sorter,
         ), true);
      }
      foreach ($groups as $group) {
         $cards .= $cardHtml($group);
      }
      $helpButton = $this->helpButton($this->ensureShopProductGroupsHelpPage(), $texts->get_fd_message('groups_help'));
      $barActions = '<a class="btn btn-outline-primary btn-sm me-1" href="?dbx_modul=dbxShop_admin&dbx_run1=groups&new=1" data-dbx-tooltip="' . $this->h($texts->get_fd_message('groups_new_title')) . '"><i class="bi bi-plus-square"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('groups_new')) . '</span></a>' . $helpButton;
      $content = '<div class="alert alert-info mx-3 mt-3 mb-0">' . $this->h($texts->get_fd_message('groups_intro')) . '</div>'
         . '<div class="dbx-shop-admin-card-list">' . $cards . '</div>';
      if ($groups !== array()) {
         // Eine gemeinsame Medienbrowser-Vorlage genuegt fuer alle Karten.
         // Sie steht bewusst nach allen Kartenformularen.
         $content .= $this->shopMediaFormTemplates($this->shopMediaConfig());
      }
      return $this->frame($content, $texts->get_fd_message('groups_title'), $barActions);
   }



   private function attributes(): string {
      $this->ensureSeed();
      $texts = $this->catalogTexts();
      if ($this->posted('save_attribute_definition')) {
         $this->repo()->saveAttributeDefinition($_POST);
      }

      $groupOptions = array();
      foreach ($this->repo()->groups() as $group) {
         $groupOptions[(string)(int)($group['id'] ?? 0)] = (string)($group['title'] ?? '');
      }

      $cardHelpButton = $this->helpButton($this->ensureShopProductAttributesHelpPage(), $texts->get_fd_message('attributes_help'), 'btn btn-outline-secondary btn-sm me-1');
      $cardHtml = function (array $attribute, bool $isNew = false) use ($groupOptions, $cardHelpButton, $texts): string {
         $id = (int)($attribute['id'] ?? 0);
         $type = (string)($attribute['input_type'] ?? 'text');
         $title = $isNew
            ? '<span>' . $this->h($texts->get_fd_message('attributes_new')) . '</span>'
            : '<code>' . $this->h($attribute['attr_key'] ?? '') . '</code><span>' . $this->h($attribute['title'] ?? '') . '</span>';
         $subtitle = $isNew ? $texts->get_fd_message('attributes_new_subtitle') : (string)($attribute['group_title'] ?? '');

         $form = $this->shopAdminCardForm(
            'shop-attribute-definition-' . ($isNew ? 'new' : $id),
            'dbxShop|shopAttributeDefinition',
            $attribute,
            $id,
            '?dbx_modul=dbxShop_admin&dbx_run1=attributes',
            'save_attribute_definition',
            'save_attribute_definition',
            $title,
            $subtitle,
            'dbx-shop-attribute-card'
         );
         $form->add_rep('card_badges', $cardHelpButton . ($isNew ? '<span class="badge text-bg-primary">' . $this->h($texts->get_fd_message('new')) . '</span>' : $this->activeBadge($attribute, $texts)));
         $form->add_fld('group_id', tpl: 'select-single-label', options: $groupOptions, rules: 'int');
         $form->add_fld('attr_key', tpl: 'text-label', placeholder: $texts->get_fd_message('attributes_key_placeholder'), rules: '*|parameter|max=80');
         $form->add_fld('title', tpl: 'text-label', placeholder: $texts->get_fd_message('attributes_title_placeholder'), rules: '*|max=160');
         $form->add_fld('input_type', tpl: 'select-single-label', options: array(
            'text' => $texts->get_fd_message('attributes_type_text'),
            'select' => $texts->get_fd_message('attributes_type_select'),
            'number' => $texts->get_fd_message('attributes_type_number'),
         ));
         $form->add_fld('unit', tpl: 'text-label', placeholder: 'cm');
         $form->add_fld('options', tpl: 'textarea-label', placeholder: 'S|M|L|XL', data: 'rows=2');
         $form->add_fld('required', tpl: 'checkbox-label', rules: 'int');
         $form->add_fld('filterable', tpl: 'checkbox-label', rules: 'int');
         $form->add_fld('comparable', tpl: 'checkbox-label', rules: 'int');
         $form->add_fld('sorter', tpl: 'text-label', rules: 'int');
         $form->add_fld('active', tpl: 'checkbox-label', rules: 'int');
         $form->add_rep('form_body',
            '<div class="dbx-shop-admin-card-grid">'
            . '<div>{obj:group_id}</div>'
            . '<div>{obj:attr_key}</div>'
            . '<div>{obj:title}</div>'
            . '<div>{obj:input_type}</div>'
            . '<div>{obj:unit}</div>'
            . '<div>{obj:sorter}</div>'
            . '<div class="wide">{obj:options}</div>'
            . '<div class="wide dbx-shop-admin-check-grid">{obj:required}{obj:filterable}{obj:comparable}{obj:active}</div>'
            . '</div>'
         );
         return $form->run();
      };

      $cards = $cardHtml(array(
         'group_id' => (int)array_key_first($groupOptions),
         'input_type' => 'text',
         'required' => 0,
         'filterable' => 1,
         'comparable' => 0,
         'active' => 1,
         'sorter' => 100,
      ), true);
      foreach ($this->repo()->allAttributeDefinitions() as $attribute) {
         $cards .= $cardHtml($attribute);
      }

      $barActions = $this->helpButton($this->ensureShopProductAttributesHelpPage(), $texts->get_fd_message('attributes_help'));
      return $this->frame('<div class="alert alert-info mx-3 mt-3 mb-0">' . $this->h($texts->get_fd_message('attributes_intro')) . '</div><div class="dbx-shop-admin-card-list">' . $cards . '</div>', $texts->get_fd_message('attributes_title'), $barActions);
   }



   private function productAttributes(): string {
      $this->ensureSeed();
      $texts = $this->catalogTexts();
      $productId = (int)dbx()->get_modul_var('id', '0', 'int');
      if ($this->posted('save_product_attributes')) {
         $productId = (int)($_POST['product_id'] ?? $productId);
         $this->repo()->saveProductAttributeValues($productId, $_POST['attr_value'] ?? array());
      }
      $product = $this->repo()->productById($productId);
      if (!$product) {
         return $this->placeholder($texts->get_fd_message('attributes_title'), $texts->get_fd_message('attributes_product_not_found'));
      }

      $valueMap = array();
      foreach (($product['attributes'] ?? array()) as $attribute) {
         $valueMap[(int)($attribute['id'] ?? 0)] = (string)($attribute['value_text'] ?? '');
      }

      $rows = '';
      foreach ($this->repo()->attributeDefinitionsForProduct($productId, true) as $definition) {
         $id = (int)($definition['id'] ?? 0);
         $value = $valueMap[$id] ?? '';
         $type = (string)($definition['input_type'] ?? 'text');
         $input = '';
         if ($type === 'select') {
            $input = '<select class="form-select form-select-sm" name="attr_value[' . $id . ']"><option value="">-</option>';
            foreach ($this->attributeOptions((string)($definition['options'] ?? '')) as $option) {
               $input .= '<option value="' . $this->h($option) . '"' . ($option === $value ? ' selected' : '') . '>' . $this->h($option) . '</option>';
            }
            if ($value !== '' && strpos((string)($definition['options'] ?? ''), $value) === false) {
               $input .= '<option value="' . $this->h($value) . '" selected>' . $this->h($value) . '</option>';
            }
            $input .= '</select>';
         } else {
            $inputType = $type === 'number' ? 'number' : 'text';
            $step = $type === 'number' ? ' step="0.01"' : '';
            $input = '<input class="form-control form-control-sm" type="' . $inputType . '"' . $step . ' name="attr_value[' . $id . ']" value="' . $this->h($value) . '">';
         }
         $rows .= '<tr>';
         $rows .= '<td><strong>' . $this->h($definition['title'] ?? '') . '</strong><br><small><code>' . $this->h($definition['attr_key'] ?? '') . '</code></small></td>';
         $rows .= '<td>' . $input . '</td>';
         $rows .= '<td>' . $this->h($definition['unit'] ?? '') . '</td>';
         $rows .= '<td>' . ((int)($definition['required'] ?? 0) === 1 ? '<span class="badge text-bg-warning">' . $this->h($texts->get_fd_message('required')) . '</span>' : '') . ' ' . ((int)($definition['filterable'] ?? 0) === 1 ? '<span class="badge text-bg-info">' . $this->h($texts->get_fd_message('filter')) . '</span>' : '') . '</td>';
         $rows .= '</tr>';
      }

      if ($rows === '') {
         $rows = '<tr><td colspan="4" class="text-muted">' . $this->h($texts->get_fd_message('attributes_none')) . '</td></tr>';
      }

      $form = $this->shopAdminCardForm(
         'shop-product-attributes-' . $productId,
         'dbxShop|shopProductAttributeValue',
         array('id' => $productId),
         $productId,
         '?dbx_modul=dbxShop_admin&dbx_run1=product_attributes&id=' . $productId,
         'save_product_attributes',
         'save_product_attributes',
         '<code>' . $this->h($product['sku'] ?? '') . '</code><span>' . $this->h($product['title'] ?? '') . '</span>',
         $texts->get_fd_message('attributes_value_subtitle'),
         'dbx-shop-product-attributes-card'
      );
      $form->add_rep('extra_hidden', '<input type="hidden" name="product_id" value="' . $productId . '">');
      $form->add_rep('card_badges', $this->helpButton($this->ensureShopProductAttributesHelpPage(), $texts->get_fd_message('attributes_help'), 'btn btn-outline-secondary btn-sm me-1') . '<a class="btn btn-outline-secondary btn-sm" href="?dbx_modul=dbxShop_admin&dbx_run1=products">' . $this->h($texts->get_fd_message('back')) . '</a>');
      $form->add_rep('form_body', '<div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>' . $this->h($texts->get_fd_message('column_attribute')) . '</th><th>' . $this->h($texts->get_fd_message('column_value')) . '</th><th>' . $this->h($texts->get_fd_message('column_unit')) . '</th><th>' . $this->h($texts->get_fd_message('column_property')) . '</th></tr></thead><tbody>' . $rows . '</tbody></table></div>');
      return $this->frame('<div class="dbx-shop-admin-card-list">' . $form->run() . '</div>', $texts->get_fd_message('attributes_edit_title'));
   }



   private function shippingGroups(): string {
      $this->ensureSeed();
      $texts = $this->catalogTexts();
      if ($this->posted('delete_shipping_group')) {
         $this->repo()->deleteShippingGroup((int)($_POST['id'] ?? 0));
      } elseif ($this->posted('save_shipping_group')) {
         $this->repo()->updateShippingGroup((int)($_POST['id'] ?? 0), $_POST);
      }

      $cardHelpButton = $this->helpButton($this->ensureShopShippingGroupsHelpPage(), $texts->get_fd_message('shipping_help'), 'btn btn-outline-secondary btn-sm me-1');
      $cardHtml = function (array $group, bool $isNew = false) use ($cardHelpButton, $texts): string {
         $id = (int)($group['id'] ?? 0);
         if ($isNew) {
            $title = '<span>' . $this->h($texts->get_fd_message('shipping_new')) . '</span>';
            $subtitle = $texts->get_fd_message('shipping_new_subtitle');
         } else {
            $title = '<code>' . $this->h($group['group_key'] ?? '') . '</code><span>' . $this->h($group['title'] ?? '') . '</span>';
            $subtitle = trim((string)($group['description'] ?? ''));
         }

         $form = $this->shopAdminCardForm(
            'shop-shipping-group-' . ($isNew ? 'new' : $id),
            'dbxShop|shopShippingGroup',
            $group,
            $id,
            '?dbx_modul=dbxShop_admin&dbx_run1=shipping_groups' . ($isNew ? '&new=1' : ''),
            'save_shipping_group',
            'save_shipping_group',
            $title,
            $subtitle,
            'dbx-shop-shipping-group-card'
         );
         $form->add_rep('card_badges', $cardHelpButton . ($isNew ? '<span class="badge text-bg-primary">' . $this->h($texts->get_fd_message('new')) . '</span>' : $this->activeBadge($group, $texts)));
         if (!$isNew) {
            $form->add_rep('delete_button', $this->shopAdminCardDeleteButton('delete_shipping_group', $texts->get_fd_message('shipping_delete_title'), $texts->get_fd_message('shipping_delete_confirm')));
         }
         if ($isNew) {
            $form->add_fld('group_key', tpl: 'text-label', placeholder: 'versandgruppe-key', rules: '*|parameter|max=80');
         }
         $form->add_fld('title', tpl: 'text-label', placeholder: $texts->get_fd_message('shipping_new'), rules: '*|max=160');
         $form->add_fld('description', tpl: 'textarea-label', placeholder: $texts->get_fd_message('description_placeholder'), data: 'rows=2');
         $form->add_fld('shipping_way', tpl: 'text-label', placeholder: $texts->get_fd_message('shipping_way_placeholder'));
         $form->add_fld('delivery_time', tpl: 'text-label', placeholder: $texts->get_fd_message('shipping_time_placeholder'));
         $form->add_fld('shipping_gross', tpl: 'text-label', placeholder: '5.90', rules: 'decimal');
         $form->add_fld('free_from_gross', tpl: 'text-label', placeholder: '-1', rules: 'decimal');
         $form->add_fld('sorter', tpl: 'text-label', rules: 'int');
         $form->add_fld('active', tpl: 'checkbox-label', rules: 'int');
         $form->add_rep('form_body',
            '<div class="dbx-shop-admin-card-grid">'
            . ($isNew ? '<div>{obj:group_key}</div>' : '')
            . '<div>{obj:title}</div>'
            . '<div>{obj:shipping_way}</div>'
            . '<div>{obj:delivery_time}</div>'
            . '<div>{obj:shipping_gross}</div>'
            . '<div>{obj:free_from_gross}</div>'
            . '<div>{obj:sorter}</div>'
            . '<div>{obj:active}</div>'
            . '<div class="wide">{obj:description}</div>'
            . '</div>'
         );
         return $form->run();
      };

      $groups = $this->repo()->shippingGroups();
      $cards = '';
      if ((int)($_GET['new'] ?? 0) === 1) {
         $sorter = 10;
         foreach ($groups as $group) {
            $sorter = max($sorter, (int)($group['sorter'] ?? 0) + 10);
         }
         $cards .= $cardHtml(array(
            'shipping_gross' => 0,
            'free_from_gross' => -1,
            'active' => 1,
            'sorter' => $sorter,
         ), true);
      }
      foreach ($groups as $group) {
         $cards .= $cardHtml($group);
      }
      $helpButton = $this->helpButton($this->ensureShopShippingGroupsHelpPage(), $texts->get_fd_message('shipping_help'));
      $barActions = '<a class="btn btn-outline-primary btn-sm me-1" href="?dbx_modul=dbxShop_admin&dbx_run1=shipping_groups&new=1" data-dbx-tooltip="' . $this->h($texts->get_fd_message('shipping_new_title')) . '"><i class="bi bi-plus-square"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('shipping_new')) . '</span></a>' . $helpButton;
      return $this->frame('<div class="alert alert-info mx-3 mt-3 mb-0">' . $this->h($texts->get_fd_message('shipping_intro')) . '</div><div class="dbx-shop-admin-card-list">' . $cards . '</div>', $texts->get_fd_message('shipping_title'), $barActions);
   }



   private function channelGroups(): string {
      $this->ensureSeed();
      $texts = $this->catalogTexts();
      if ($this->posted('delete_channel_group')) {
         $this->repo()->deleteChannelGroup((int)($_POST['id'] ?? 0));
      } elseif ($this->posted('save_channel_group')) {
         $this->repo()->updateChannelGroup((int)($_POST['id'] ?? 0), $_POST, array_map('strval', $_POST['channels'] ?? array()));
      }
      $channels = $this->repo()->channels();
      $groups = $this->repo()->channelGroups();
      $cardHelpButton = $this->helpButton($this->ensureShopChannelHelpPage(), $texts->get_fd_message('channel_groups_help'), 'btn btn-outline-secondary btn-sm me-1');
      $cardHtml = function (array $group, bool $isNew = false) use ($channels, $cardHelpButton, $texts): string {
         $id = (int)($group['id'] ?? 0);
         $active = array();
         foreach (($group['channels'] ?? array()) as $channel) {
            if ((int)($channel['active'] ?? 0) === 1) {
               $active[] = (string)$channel['channel_key'];
            }
         }
         $checks = '<div class="dbx-shop-admin-check-grid">';
         foreach ($channels as $channel) {
            $key = (string)($channel['channel_key'] ?? '');
            if ($key === '') {
               continue;
            }
            $checks .= '<label><input type="checkbox" name="channels[]" value="' . $this->h($key) . '"' . (in_array($key, $active, true) ? ' checked' : '') . '> <span>' . $this->h($channel['title'] ?? $key) . '</span></label>';
         }
         $checks .= '</div>';

         if ($isNew) {
            $title = '<span>' . $this->h($texts->get_fd_message('channel_groups_new')) . '</span>';
            $subtitle = $texts->get_fd_message('channel_groups_new_subtitle');
         } else {
            $title = '<code>' . $this->h($group['group_key'] ?? '') . '</code><span>' . $this->h($group['title'] ?? '') . '</span>';
            $subtitle = trim((string)($group['description'] ?? ''));
         }

         $form = $this->shopAdminCardForm(
            'shop-channel-group-' . ($isNew ? 'new' : $id),
            'dbxShop|shopChannelGroup',
            $group,
            $id,
            '?dbx_modul=dbxShop_admin&dbx_run1=channel_groups' . ($isNew ? '&new=1' : ''),
            'save_channel_group',
            'save_channel_group',
            $title,
            $subtitle,
            'dbx-shop-channel-group-card'
         );
         $form->add_rep('card_badges', $cardHelpButton . ($isNew ? '<span class="badge text-bg-primary">' . $this->h($texts->get_fd_message('new')) . '</span>' : $this->activeBadge($group, $texts)));
         if (!$isNew) {
            $form->add_rep('delete_button', $this->shopAdminCardDeleteButton('delete_channel_group', $texts->get_fd_message('channel_groups_delete_title'), $texts->get_fd_message('channel_groups_delete_confirm')));
         }
         if ($isNew) {
            $form->add_fld('group_key', tpl: 'text-label', placeholder: 'neue-channel-gruppe', rules: '*|parameter|max=80');
         }
         $form->add_fld('title', tpl: 'text-label', placeholder: $texts->get_fd_message('channel_groups_new'), rules: '*|max=160');
         $form->add_fld('description', tpl: 'textarea-label', placeholder: $texts->get_fd_message('description_placeholder'), data: 'rows=2');
         $form->add_fld('sorter', tpl: 'text-label', rules: 'int');
         $form->add_fld('active', tpl: 'checkbox-label', rules: 'int');
         $form->add_obj('channel_checks', 'obj-value', $checks);
         $form->add_rep('form_body',
            '<div class="dbx-shop-admin-card-grid">'
            . ($isNew ? '<div>{obj:group_key}</div>' : '')
            . '<div>{obj:title}</div>'
            . '<div>{obj:sorter}</div>'
            . '<div>{obj:active}</div>'
            . '<div class="wide">{obj:description}</div>'
            . '<div class="wide"><label class="form-label">' . $this->h($texts->get_fd_message('channels_label')) . '</label>{obj:channel_checks}</div>'
            . '</div>'
         );
         return $form->run();
      };

      $cards = '';
      if ((int)($_GET['new'] ?? 0) === 1) {
         $sorter = 10;
         foreach ($groups as $group) {
            $sorter = max($sorter, (int)($group['sorter'] ?? 0) + 10);
         }
         $cards .= $cardHtml(array(
            'title' => '',
            'description' => '',
            'active' => 1,
            'sorter' => $sorter,
            'channels' => array(),
         ), true);
      }
      foreach ($groups as $group) {
         $cards .= $cardHtml($group);
      }
      $helpButton = $this->helpButton($this->ensureShopChannelHelpPage(), $texts->get_fd_message('channel_groups_help'));
      $barActions = '<a class="btn btn-outline-primary btn-sm me-1" href="?dbx_modul=dbxShop_admin&dbx_run1=channel_groups&new=1" data-dbx-tooltip="' . $this->h($texts->get_fd_message('channel_groups_new_title')) . '"><i class="bi bi-plus-square"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('channel_groups_new')) . '</span></a>' . $helpButton;
      return $this->frame('<div class="alert alert-info mx-3 mt-3 mb-0">' . $this->h($texts->get_fd_message('channel_groups_intro')) . '</div><div class="dbx-shop-admin-card-list">' . $cards . '</div>', $texts->get_fd_message('channel_groups_title'), $barActions);
   }
}
