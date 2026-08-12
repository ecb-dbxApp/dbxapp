<?php
namespace dbx\dbxShop_admin;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

/**
 * Dashboard, Einstellungen, Installation und Produktansichten ueber dbxTPL/dbxForm.
 *
 * Zustandslose Quelltext-Komposition: keine zusaetzliche Instanz, kein Proxy
 * und keine zusaetzlichen Datenbank- oder Templatezugriffe.
 */
trait dbxShopAdminDashboardServiceTrait {


   private function dashboard(): string {
      $stats = $this->repo()->dashboardStats();
      return $this->frame($this->tpl()->get_tpl('dbxShop_admin|admin-dashboard', array(
         'orders_open' => (string)($stats['orders_open'] ?? 0),
         'payments_open' => (string)($stats['payments_open'] ?? 0),
         'shipping_open' => (string)($stats['shipping_open'] ?? 0),
         'withdrawals_open' => (string)($stats['withdrawals_open'] ?? 0),
         'stock_low' => (string)($stats['stock_low'] ?? 0),
         'products_active' => (string)($stats['products_active'] ?? 0),
         'product_url' => '?dbx_modul=dbxShop_admin&dbx_run1=products',
         'order_url' => '?dbx_modul=dbxShop_admin&dbx_run1=orders',
         'group_url' => '?dbx_modul=dbxShop_admin&dbx_run1=groups',
         'attribute_url' => '?dbx_modul=dbxShop_admin&dbx_run1=attributes',
         'shipping_group_url' => '?dbx_modul=dbxShop_admin&dbx_run1=shipping_groups',
         'channel_group_url' => '?dbx_modul=dbxShop_admin&dbx_run1=channel_groups',
         'channel_url' => '?dbx_modul=dbxShop_admin&dbx_run1=channels',
         'media_url' => '?dbx_modul=dbxShop_admin&dbx_run1=media',
         'legal_url' => '?dbx_modul=dbxShop_admin&dbx_run1=legal',
         'return_url' => '?dbx_modul=dbxShop_admin&dbx_run1=returns',
         'install_url' => $this->actionUrl('?dbx_modul=dbxShop_admin&dbx_run1=install'),
      )));
   }



   private function placeholder(string $title, string $text): string {
      return $this->frame(
         '<div class="alert alert-info m-3"><strong>' . $this->h($title) . '</strong><br>' . $this->h($text) . '</div>',
         $title
      );
   }



   private function shopLegalCmsPage(string $key, string $title, string $intro, string $shopRun): string {
      $service = dbx()->get_include_obj('dbxShopService', 'dbxShop');
      $pages = is_object($service) && method_exists($service, 'ensureShopLegalPages')
         ? $service->ensureShopLegalPages()
         : array();
      $cid = (int)($pages[$key] ?? 0);
      if ($cid <= 0) {
         return $this->placeholder($title, 'Die CMS-Seite konnte nicht angelegt oder gefunden werden.');
      }

      $row = $this->db()->select1($this->contentDd(), $cid, 'id,title,permalink,content,group_read,template,activ', 0);
      if (!is_array($row) || (int)($row['id'] ?? 0) <= 0) {
         return $this->placeholder($title, 'Die CMS-Seite konnte nicht geladen werden.');
      }

      $editUrl = '?dbx_modul=dbxContent_admin&dbx_run1=cms&cid=' . $cid;
      $cmsViewUrl = '?dbx_modul=dbxContent&dbx_run1=content&cid=' . $cid;
      $shopViewUrl = '?dbx_modul=dbxShop&dbx_run1=' . rawurlencode($shopRun);
      $permalink = (string)($row['permalink'] ?? '');

      $actions = $this->openWinButton($editUrl, $title . ' bearbeiten', '<i class="bi bi-pencil-square"></i><span> Bearbeiten</span>', 'btn btn-primary btn-sm me-1', '94%', '92%')
         . $this->openWinButton($cmsViewUrl, $title . ' CMS-Ansicht', '<i class="bi bi-file-richtext"></i><span class="visually-hidden"> CMS-Ansicht</span>', 'btn btn-outline-primary btn-sm me-1', '82%', '86%')
         . $this->openWinButton($shopViewUrl, $title . ' im Shop ansehen', '<i class="bi bi-box-arrow-up-right"></i><span class="visually-hidden"> Shop-Ansicht</span>', 'btn btn-outline-primary btn-sm me-1', '82%', '86%');

      $renderer = dbx()->get_include_obj('dbxContentRenderer', 'dbxContent');
      $preview = is_object($renderer) && method_exists($renderer, 'renderStatic')
         ? (string)$renderer->renderStatic($cid, array('template' => 'c-body1-footer', 'skip_hits' => true))
         : trim((string)($row['content'] ?? ''));
      if (trim($preview) === '') {
         $preview = '<div class="alert alert-warning">Die CMS-Seite ist leer.</div>';
      }

      $meta = '<dl class="dbx-shop-admin-cms-meta">'
         . '<dt>CMS-ID</dt><dd>' . $cid . '</dd>'
         . '<dt>Permalink</dt><dd><code>' . $this->h($permalink) . '</code></dd>'
         . '<dt>Status</dt><dd>' . ((int)($row['activ'] ?? 0) === 1 ? 'Aktiv' : 'Inaktiv') . '</dd>'
         . '<dt>Leserechte</dt><dd><code>' . $this->h((string)($row['group_read'] ?? '')) . '</code></dd>'
         . '<dt>Template</dt><dd><code>' . $this->h((string)($row['template'] ?? '')) . '</code></dd>'
         . '</dl>';

      $html = '<section class="dbx-shop-admin-cms-page">'
         . '<div class="alert alert-info"><strong>' . $this->h($title) . '</strong><br>' . $this->h($intro) . '</div>'
         . $meta
         . '<div class="dbx-shop-admin-cms-preview">' . $preview . '</div>'
         . '</section>';

      return $this->frame($html, $title, $actions);
   }



   private function settingsBool(array $cfg, string $key, bool $default = false): bool {
      if (!array_key_exists($key, $cfg)) {
         return $default;
      }
      $value = $cfg[$key];
      if (is_bool($value)) return $value;
      return in_array(strtolower(trim((string)$value)), array('1', 'true', 'yes', 'on'), true);
   }



   private function saveSettings(): void {
      $cfg = $this->shopConfig();
      $cfg['enabled'] = !empty($_POST['enabled']);
      $cfg['activ'] = $cfg['enabled'] ? '1' : '0';
      $cfg['default_channel'] = trim((string)($_POST['default_channel'] ?? 'shop')) ?: 'shop';
      $cfg['default_currency'] = strtoupper(substr(preg_replace('~[^A-Z]~i', '', (string)($_POST['default_currency'] ?? 'EUR')) ?: 'EUR', 0, 3));
      $cfg['price_display'] = in_array((string)($_POST['price_display'] ?? 'gross'), array('gross', 'net'), true) ? (string)$_POST['price_display'] : 'gross';
      $cfg['default_tax_class'] = in_array((string)($_POST['default_tax_class'] ?? 'mwst1'), array('mwst1', 'mwst2', 'mwst3'), true) ? (string)$_POST['default_tax_class'] : 'mwst1';
      $cfg['tax_display_enabled'] = (string)($_POST['tax_display_enabled'] ?? '1') !== '0';

      $rates = array();
      foreach (array('mwst1', 'mwst2', 'mwst3') as $key) {
         $title = trim((string)($_POST['tax_title_' . $key] ?? $key));
         $rate = str_replace(',', '.', trim((string)($_POST['tax_rate_' . $key] ?? '0')));
         $rates[$key] = array(
            'title' => $title !== '' ? $title : $key,
            'rate' => number_format((float)$rate, 2, '.', ''),
         );
      }
      $cfg['tax_rates'] = $rates;

      foreach (array(
         'b2b_mode',
         'stock_enabled',
         'channels_enabled',
         'checkout_guest_allowed',
         'demo_notice_enabled',
         'legal_snapshot_enabled',
         'withdrawal_button_enabled',
         'mail_customer_enabled',
         'mail_admin_enabled',
         'payment_bank_transfer_enabled',
         'payment_invoice_enabled',
         'payment_paypal_enabled',
         'payment_amazon_pay_enabled',
         'delivery_digital_download_enabled',
         'delivery_flat_shipping_enabled',
      ) as $key) {
         $cfg[$key] = !empty($_POST[$key]);
      }

      $cfg['payment_bank_transfer_account_owner'] = trim((string)($_POST['payment_bank_transfer_account_owner'] ?? ''));
      $cfg['payment_bank_transfer_iban'] = trim((string)($_POST['payment_bank_transfer_iban'] ?? ''));
      $cfg['payment_bank_transfer_bic'] = trim((string)($_POST['payment_bank_transfer_bic'] ?? ''));
      $cfg['payment_bank_transfer_bank_name'] = trim((string)($_POST['payment_bank_transfer_bank_name'] ?? ''));
      $cfg['payment_bank_transfer_instructions'] = trim((string)($_POST['payment_bank_transfer_instructions'] ?? ''));
      $cfg['payment_invoice_instructions'] = trim((string)($_POST['payment_invoice_instructions'] ?? ''));
      $cfg['payment_paypal_mode'] = (string)($_POST['payment_paypal_mode'] ?? 'sandbox') === 'live' ? 'live' : 'sandbox';
      $cfg['payment_paypal_client_id'] = trim((string)($_POST['payment_paypal_client_id'] ?? ''));
      $cfg['payment_paypal_client_secret'] = trim((string)($_POST['payment_paypal_client_secret'] ?? ''));
      $cfg['payment_paypal_brand_name'] = trim((string)($_POST['payment_paypal_brand_name'] ?? 'dbXapp')) ?: 'dbXapp';
      $cfg['payment_paypal_currency'] = $cfg['default_currency'];
      $cfg['payment_amazon_pay_mode'] = (string)($_POST['payment_amazon_pay_mode'] ?? 'sandbox') === 'live' ? 'live' : 'sandbox';
      $cfg['payment_amazon_pay_region'] = in_array((string)($_POST['payment_amazon_pay_region'] ?? 'EU'), array('EU', 'UK', 'US'), true) ? (string)$_POST['payment_amazon_pay_region'] : 'EU';
      $cfg['payment_amazon_pay_merchant_id'] = trim((string)($_POST['payment_amazon_pay_merchant_id'] ?? ''));
      $cfg['payment_amazon_pay_store_id'] = trim((string)($_POST['payment_amazon_pay_store_id'] ?? ''));
      $cfg['payment_amazon_pay_public_key_id'] = trim((string)($_POST['payment_amazon_pay_public_key_id'] ?? ''));
      $cfg['payment_amazon_pay_private_key'] = trim((string)($_POST['payment_amazon_pay_private_key'] ?? ''));
      $cfg['payment_amazon_pay_sandbox_simulation_code'] = trim((string)($_POST['payment_amazon_pay_sandbox_simulation_code'] ?? ''));
      $cfg['mail_from'] = trim((string)($_POST['mail_from'] ?? ''));
      $cfg['mail_admin_to'] = trim((string)($_POST['mail_admin_to'] ?? ''));

      $flatShipping = str_replace(',', '.', trim((string)($_POST['delivery_flat_shipping_gross_price'] ?? '0')));
      $cfg['delivery_flat_shipping_gross_price'] = number_format((float)$flatShipping, 2, '.', '');
      $cfg['media_usage_slot'] = preg_replace('~[^a-z0-9_-]+~i', '', (string)($_POST['media_usage_slot'] ?? 'shop')) ?: 'shop';

      $mailLocal = array(
         'mail_profile' => trim((string)($cfg['mail_profile'] ?? 'dbxApp')) ?: 'dbxApp',
         'mail_from' => $cfg['mail_from'],
         'mail_from_name' => trim((string)($cfg['mail_from_name'] ?? 'dbxShop')) ?: 'dbxShop',
         'mail_admin_to' => $cfg['mail_admin_to'],
      );
      dbx()->patch_local_config('dbxShop', $mailLocal);
      dbx()->set_cfg('dbxShop', $cfg);
      $this->loadContentCacheSupport();
      if (class_exists('\\dbx\\dbxContent\\dbxContentPageCache')) {
         \dbx\dbxContent\dbxContentPageCache::invalidateAllFullPages();
      }
   }



   private function settingsFormData(array $cfg): array {
      $rates = $this->taxRatesConfig();
      $data = $cfg;
      foreach (array('mwst1', 'mwst2', 'mwst3') as $key) {
         $rate = is_array($rates[$key] ?? null) ? $rates[$key] : array();
         $data['tax_title_' . $key] = (string)($rate['title'] ?? $key);
         $data['tax_rate_' . $key] = (string)($rate['rate'] ?? '0');
      }

      foreach (array(
         'enabled',
         'b2b_mode',
         'stock_enabled',
         'channels_enabled',
         'checkout_guest_allowed',
         'demo_notice_enabled',
         'legal_snapshot_enabled',
         'withdrawal_button_enabled',
         'mail_customer_enabled',
         'mail_admin_enabled',
         'payment_bank_transfer_enabled',
         'payment_invoice_enabled',
         'payment_paypal_enabled',
         'payment_amazon_pay_enabled',
         'delivery_digital_download_enabled',
         'delivery_flat_shipping_enabled',
      ) as $key) {
         $data[$key] = $this->settingsBool($cfg, $key, in_array($key, array(
            'enabled',
            'checkout_guest_allowed',
            'demo_notice_enabled',
            'legal_snapshot_enabled',
            'withdrawal_button_enabled',
            'mail_customer_enabled',
            'mail_admin_enabled',
            'payment_bank_transfer_enabled',
            'delivery_digital_download_enabled',
            'delivery_flat_shipping_enabled',
         ), true)) ? 1 : 0;
      }

      $data['default_channel'] = (string)($cfg['default_channel'] ?? 'shop');
      $data['channels_enabled'] = array_key_exists('channels_enabled', $cfg) ? (int)((bool)$cfg['channels_enabled']) : 1;
      $data['default_currency'] = (string)($cfg['default_currency'] ?? 'EUR');
      $data['price_display'] = (string)($cfg['price_display'] ?? 'gross');
      $data['default_tax_class'] = (string)($cfg['default_tax_class'] ?? 'mwst1');
      $data['tax_display_enabled'] = $this->settingsBool($cfg, 'tax_display_enabled', true) ? 1 : 0;
      $data['payment_paypal_mode'] = (string)($cfg['payment_paypal_mode'] ?? 'sandbox');
      $data['payment_paypal_brand_name'] = (string)($cfg['payment_paypal_brand_name'] ?? 'dbXapp');
      $data['payment_paypal_client_id'] = (string)($cfg['payment_paypal_client_id'] ?? '');
      $data['payment_paypal_client_secret'] = (string)($cfg['payment_paypal_client_secret'] ?? '');
      $data['payment_bank_transfer_account_owner'] = (string)($cfg['payment_bank_transfer_account_owner'] ?? '');
      $data['payment_bank_transfer_iban'] = (string)($cfg['payment_bank_transfer_iban'] ?? '');
      $data['payment_bank_transfer_bic'] = (string)($cfg['payment_bank_transfer_bic'] ?? '');
      $data['payment_bank_transfer_bank_name'] = (string)($cfg['payment_bank_transfer_bank_name'] ?? '');
      $data['payment_bank_transfer_instructions'] = (string)($cfg['payment_bank_transfer_instructions'] ?? 'Bitte ueberweisen Sie den Rechnungsbetrag unter Angabe der Bestellnummer.');
      $data['payment_invoice_instructions'] = (string)($cfg['payment_invoice_instructions'] ?? 'Sie erhalten eine Rechnung. Bitte zahlen Sie innerhalb der angegebenen Frist.');
      $data['payment_amazon_pay_mode'] = (string)($cfg['payment_amazon_pay_mode'] ?? 'sandbox');
      $data['payment_amazon_pay_region'] = (string)($cfg['payment_amazon_pay_region'] ?? 'EU');
      $data['payment_amazon_pay_merchant_id'] = (string)($cfg['payment_amazon_pay_merchant_id'] ?? '');
      $data['payment_amazon_pay_store_id'] = (string)($cfg['payment_amazon_pay_store_id'] ?? '');
      $data['payment_amazon_pay_public_key_id'] = (string)($cfg['payment_amazon_pay_public_key_id'] ?? '');
      $data['payment_amazon_pay_private_key'] = (string)($cfg['payment_amazon_pay_private_key'] ?? '');
      $data['payment_amazon_pay_sandbox_simulation_code'] = (string)($cfg['payment_amazon_pay_sandbox_simulation_code'] ?? '');
      $data['mail_from'] = (string)($cfg['mail_from'] ?? '');
      $data['mail_admin_to'] = (string)($cfg['mail_admin_to'] ?? '');
      $data['delivery_flat_shipping_gross_price'] = (string)($cfg['delivery_flat_shipping_gross_price'] ?? '5.90');
      $data['media_usage_slot'] = (string)($cfg['media_usage_slot'] ?? 'shop');

      return $data;
   }



   private function settingsChannelsStatusHtml(array $cfg, $texts): string {
      $channelsEnabled = $this->settingsBool($cfg, 'channels_enabled', true);
      $channels = $this->repo()->channels();
      $external = array();
      foreach ($channels as $channel) {
         $key = strtolower(trim((string)($channel['channel_key'] ?? '')));
         if ($key === '' || $key === 'shop') {
            continue;
         }
         if ((int)($channel['active'] ?? 0) !== 1) {
            continue;
         }
         $external[] = $channel;
      }

      $html = '<div class="dbx-shop-settings-channel-status">';
      $html .= '<div class="dbx-shop-settings-channel-head">';
      $html .= '<div><strong>' . $this->h($texts->get_fd_message('channels_external')) . '</strong><span>' . $this->h($channelsEnabled ? $texts->get_fd_message('channels_global_active') : $texts->get_fd_message('channels_global_inactive')) . '</span></div>';
      $html .= $this->openWinButton('?dbx_modul=dbxShop_admin&dbx_run1=channels', $texts->get_fd_message('channels_edit'), '<i class="bi bi-broadcast"></i> ' . $this->h($texts->get_fd_message('column_channel')), 'btn btn-outline-primary btn-sm', '92%', '88%');
      $html .= '</div>';

      if (!$channelsEnabled) {
         $html .= '<div class="alert alert-warning py-2 mb-0">' . $this->h($texts->get_fd_message('channels_disabled')) . '</div>';
      }

      if ($external === array()) {
         $html .= '<div class="dbx-shop-settings-channel-empty">' . $this->h($texts->get_fd_message('channels_none')) . '</div>';
         return $html . '</div>';
      }

      $html .= '<div class="dbx-shop-settings-channel-grid">';
      $html .= '<div class="dbx-shop-settings-channel-grid-head"><span>' . $this->h($texts->get_fd_message('column_channel')) . '</span><span>' . $this->h($texts->get_fd_message('column_platform')) . '</span><span>' . $this->h($texts->get_fd_message('column_connection')) . '</span><span>' . $this->h($texts->get_fd_message('column_export')) . '</span><span>' . $this->h($texts->get_fd_message('column_import')) . '</span><span>' . $this->h($texts->get_fd_message('column_test')) . '</span></div>';
      foreach ($external as $channel) {
         $test = trim((string)($channel['test_status'] ?? ''));
         $testClass = $test === 'ok' ? 'success' : ($test !== '' ? 'warning' : 'secondary');
         $html .= '<div class="dbx-shop-settings-channel-grid-row">';
         $html .= '<span class="dbx-shop-settings-channel-name"><strong>' . $this->h((string)($channel['title'] ?? $channel['channel_key'] ?? '')) . '</strong><code>' . $this->h((string)($channel['channel_key'] ?? '')) . '</code></span>';
         $html .= '<span>' . $this->h((string)($channel['platform_type'] ?? '')) . '</span>';
         $html .= '<span>' . $this->h((string)($channel['connection_mode'] ?? '')) . '</span>';
         $html .= ((int)($channel['export_enabled'] ?? 0) === 1 ? '<span class="badge text-bg-success">' . $this->h($texts->get_fd_message('column_export')) . '</span>' : '<span class="badge text-bg-secondary">' . $this->h($texts->get_fd_message('export_off')) . '</span>');
         $html .= ((int)($channel['order_import_enabled'] ?? 0) === 1 ? '<span class="badge text-bg-primary">' . $this->h($texts->get_fd_message('column_import')) . '</span>' : '<span class="badge text-bg-secondary">' . $this->h($texts->get_fd_message('import_off')) . '</span>');
         $html .= '<span class="badge text-bg-' . $testClass . '">' . $this->h($test !== '' ? $test : $texts->get_fd_message('not_tested')) . '</span>';
         $html .= '</div>';
      }
      $html .= '</div>';
      return $html . '</div>';
   }



   private function settings(): string {
      $form = dbx()->get_system_obj('dbxForm');
      $form->init('shop-settings-form', 'shop-settings-form');
      $form->_fd = 'dbxShop_admin|shop-settings';
      $form->load_fd_messages();
      $helpId = $this->ensureShopSettingsHelpPage();
      $helpButton = $helpId > 0
         ? $this->openWinButton('?dbx_modul=dbxContent&dbx_run1=content&cid=' . $helpId, $form->get_fd_message('settings_help'), '<i class="bi bi-question-circle"></i><span class="visually-hidden"> ' . $this->h($form->get_fd_message('settings_help')) . '</span>', 'btn btn-outline-secondary btn-sm me-1', '72%', '82%')
         : '';
      $form->_action = '?dbx_modul=dbxShop_admin&dbx_run1=settings';
      $form->_data = $this->settingsFormData($this->shopConfig());
      $form->add_rep('shop_admin_style', $this->shopAdminStyle());
      $form->add_rep('form_class', 'dbx-shop-settings-dbXForm');
      $form->add_rep('bar_title', $this->h($form->get_fd_message('settings_title')));
      $form->add_rep('bar_icon', 'bi-sliders');
      $form->add_rep('bar_subtitle', $this->h($form->get_fd_message('settings_subtitle')));
      $form->add_rep('bar_class', 'dbx-bar--module');
      $form->add_rep('bar_title_class', 'dbx-bar-title');
      $form->add_rep('bar_title_pre', '');
      $form->add_rep('bar_title_heading_attrs', '');
      $form->add_rep('bar_middle', '');
      $form->add_rep('bar_actions_class', 'dbx-bar-actions');
      $form->add_rep('bar_extra', '');
      $form->add_obj('channels_status', 'obj-value', $this->settingsChannelsStatusHtml($this->shopConfig(), $form));
      $paymentTestButton = $this->openWinButton('?dbx_modul=dbxShop_admin&dbx_run1=payment_test', $form->get_fd_message('settings_payment_test'), '<i class="bi bi-plug"></i><span class="visually-hidden"> ' . $this->h($form->get_fd_message('settings_payment_test')) . '</span>', 'btn btn-outline-primary btn-sm me-1', '64%', '58%');
      $form->add_rep('bar_actions', $paymentTestButton . '<button class="btn btn-primary btn-sm" type="submit" name="shop_action" value="save_settings" data-dbx-tooltip="' . $this->h($form->get_fd_message('settings_save')) . '"><i class="bi bi-save"></i><span class="visually-hidden"> ' . $this->h($form->get_fd_message('settings_save')) . '</span></button>' . $helpButton);
      $form->_msg_info = '';
      $form->add_flds();

      if ($form->submit()) {
         if (!$form->errors() && !$form->warnings()) {
            $this->saveSettings();
            $form->_data = $this->settingsFormData($this->shopConfig());
            $form->add_obj('channels_status', 'obj-value', $this->settingsChannelsStatusHtml($this->shopConfig(), $form));
         }
      }

      return $form->run();
   }



   private function paymentTest(): string {
      $paypal = dbx()->get_include_obj('dbxShopPayPal', 'dbxShop');
      $amazonPay = dbx()->get_include_obj('dbxShopAmazonPay', 'dbxShop');
      $paypalResult = is_object($paypal) && method_exists($paypal, 'testConnection')
         ? $paypal->testConnection()
         : array('ok' => false, 'mode' => '', 'message' => 'PayPal-Connector konnte nicht geladen werden.');
      $amazonResult = is_object($amazonPay) && method_exists($amazonPay, 'testConnection')
         ? $amazonPay->testConnection()
         : array('ok' => false, 'mode' => '', 'region' => '', 'message' => 'Amazon-Pay-Connector konnte nicht geladen werden.');

      $card = function(string $title, string $icon, array $result): string {
         $ok = !empty($result['ok']);
         $meta = array();
         if (trim((string)($result['mode'] ?? '')) !== '') {
            $meta[] = 'Modus: ' . $this->h((string)$result['mode']);
         }
         if (trim((string)($result['region'] ?? '')) !== '') {
            $meta[] = 'Region: ' . $this->h((string)$result['region']);
         }
         return $this->tpl()->get_tpl('dbxShop_admin|payment-test-card', array(
            'icon' => $this->h($icon),
            'title' => $this->h($title),
            'badge_class' => $ok ? 'text-bg-success' : 'text-bg-warning',
            'badge_text' => $ok ? 'OK' : 'Pruefen',
            'meta' => $meta !== array() ? '<p class="dbx-shop-payment-test-meta">' . implode(' · ', $meta) . '</p>' : '',
            'message' => $this->h((string)($result['message'] ?? 'Keine Rueckmeldung.')),
         ));
      };

      $body = $this->tpl()->get_tpl('dbxShop_admin|payment-test', array(
         'cards' => $card('PayPal', 'bi-paypal', $paypalResult)
            . $card('Amazon Pay', 'bi-amazon', $amazonResult),
      ));
      return $this->frame($body, 'Payment testen');
   }



   private function install(): string {
      if (!$this->checkActionToken('install')) {
         return $this->placeholder('Shop-Installation abgewiesen', $this->postedFormError);
      }
      $this->maintenanceMode = true;
      try {
         $this->repo()->seedDemoProducts();
         $this->maintainShopAdminContent();
      } finally {
         $this->maintenanceMode = false;
      }
      return $this->placeholder(
         'Shop-Installation ausgefuehrt',
         'dbxShop.db3 wurde angelegt bzw. aktualisiert. Deutsche Testartikel, Gruppen und Channel-Zuordnungen sind vorhanden.'
      );
   }



   private function products(): string {
      $this->ensureSeed();
      $report = dbx()->get_system_obj('dbxReport');
      $report->init('shop-products-report');
      $report->_fd = 'dbxShop_admin|rpt-products-selection';
      $report->load_fd_messages();
      // dbxReport haengt die konkreten Schreibaktionen an diese Basis-URL an.
      // Der zusaetzliche Token ist fuer reine Filter-/Navigationsaufrufe
      // unschaedlich und sichert gleichzeitig die kompatiblen GET-Aktionen.
      $report->_action = $this->actionUrl('?dbx_modul=dbxShop_admin&dbx_run1=products');
      $report->_mode = 'table';
      $report->_pages = true;
      $report->_multi_page_select = 1;
      $report->_create_row_select = true;
      $report->_create_row_delete = true;
      $report->_create_row_edit = true;
      $report->_create_row_show = true;
      $report->_create_sel_flds = true;
      $report->_but_pagination = 7;
      $report->_fld_id = 'id';
      $report->_msg_confirm_delete = $report->get_fd_message('delete_confirm');
      $report->_msg_info = $report->get_fd_message('report_info');
      $report->add_rep('bar_title', $report->get_fd_message('bar_title'));
      $report->add_rep('bar_icon', 'bi-bag-check');
      $report->add_rep('bar_subtitle', $report->get_fd_message('bar_subtitle'));
      $report->add_rep('bar_class', 'dbx-bar--module');
      $report->add_rep('bar_title_class', 'dbx-bar-title');
      $report->add_rep('bar_actions_class', 'dbx-bar-actions');
      $report->add_rep('bar_title_pre', $this->productTreeToggleButton($report));
      $report->add_rep('bar_title_heading_attrs', '');
      $report->add_rep('bar_middle', '');
      $report->add_rep('bar_extra', '');
      $report->add_rep('bar_actions', $this->productShellActions($report));
      $report->add_rep('shop_admin_style', $this->shopAdminStyle());
      $report->add_rep('report_form_class', 'dbx-shop-products-form is-shop-tree-collapsed');
      $report->add_rep('report_form_attrs', 'data-dbx="lib=shopAdmin" data-shop-tree-shell');
      $channelsEnabled = $this->channelsEnabled();
      $columnClasses = array(
         'image_view' => 'dbx-shop-col-image',
         'article_view' => 'dbx-shop-col-article',
         'groups_view' => 'dbx-shop-col-groups',
         'attributes_view' => 'dbx-shop-col-attributes',
         'shipping_groups_view' => 'dbx-shop-col-shipping-groups',
         'price_view' => 'dbx-shop-col-money',
         'tax_view' => 'dbx-shop-col-tax',
         'shipping_view' => 'dbx-shop-col-money',
         'status_view' => 'dbx-shop-col-status',
      );
      if ($channelsEnabled) {
         $columnClasses['channel_groups_view'] = 'dbx-shop-col-channel-groups';
         $columnClasses['channels_view'] = 'dbx-shop-col-channels';
      }
      foreach ($columnClasses as $field => $class) {
         $report->set_class_haeder($field, $class);
         $report->_class_body[$field] = $class;
      }
      $report->set_callback_owner($this);
      $report->set_callback('next_record', 'product_report_next_record');
      $report->set_callback('row_action_data', 'product_report_row_action_data');
      $report->add_rep('report_products_actions', $this->productReportActionControls($report->_action, $report));
      $report->create_selection_fields('dbxShop_admin|rpt-products-selection');
      $this->handleProductReportAction($report);

      $query = trim((string)$report->get_fld_val('dbx_rwhere', '', 'parameter|max=100'));
      $requestedRowsPerPage = (int)$report->get_fld_val('dbx_rrows', 30, 'int');
      $rowsPerPage = $requestedRowsPerPage === 0 ? 0 : max(10, min(100, $requestedRowsPerPage));
      $position = $rowsPerPage === 0 ? 0 : max(0, (int)$report->get_fld_val('dbx_rpos', 0, 'int'));
      $sort = (string)$report->get_fld_val('dbx_rsort', 'sorter', 'parameter');
      $direction = strtoupper((string)$report->get_fld_val('dbx_rdesc', 'ASC', 'parameter')) === 'DESC' ? 'DESC' : 'ASC';
      $selectedOnly = (int)$report->get_fld_val('dbx_rselect', 0, 'int') === 1;
      if (!in_array($sort, array('sorter', 'sku', 'title', 'price_gross', 'effective_tax_rate', 'effective_shipping_gross', 'active'), true)) {
         $sort = 'sorter';
      }

      $allProducts = $this->repo()->products(false);
      $matchedProducts = array();
      $selectedIds = $selectedOnly ? $report->get_multi_selects() : array();
      foreach ($allProducts as $product) {
         if ($selectedOnly && !isset($selectedIds[(string)(int)($product['id'] ?? 0)])) {
            continue;
         }
         $score = $this->productSearchScore($product, $query);
         if ($score <= 0) {
            continue;
         }
         $product['_search_score'] = $score;
         $matchedProducts[] = $product;
      }
      $matchedProducts = $this->sortProductsForReport($matchedProducts, $query, $sort, $direction);
      $filteredCount = count($matchedProducts);
      if ($rowsPerPage > 0 && $position >= $filteredCount && $filteredCount > 0) {
         $position = max(0, (int)(floor(($filteredCount - 1) / $rowsPerPage) * $rowsPerPage));
      }
      $visibleProducts = $rowsPerPage === 0
         ? $matchedProducts
         : array_slice($matchedProducts, $position, $rowsPerPage);

      $report->_rflds = array(
         'image_view' => $report->get_fd_message('column_image'),
         'article_view' => $report->get_fd_message('column_product'),
         'groups_view' => $report->get_fd_message('column_product_groups'),
         'attributes_view' => $report->get_fd_message('column_attributes'),
         'shipping_groups_view' => $report->get_fd_message('column_shipping_groups'),
         'price_view' => $report->get_fd_message('column_price'),
         'tax_view' => $report->get_fd_message('column_tax'),
         'shipping_view' => $report->get_fd_message('column_shipping'),
         'status_view' => $report->get_fd_message('column_status'),
      );
      if ($channelsEnabled) {
         $report->_rflds = array_slice($report->_rflds, 0, 5, true)
            + array(
               'channel_groups_view' => $report->get_fd_message('column_channel_groups'),
               'channels_view' => $report->get_fd_message('column_channels'),
            )
            + array_slice($report->_rflds, 5, null, true);
      }
      $report->_rpt_format = array(
         'image_view' => 'html',
         'article_view' => 'html',
         'groups_view' => 'html',
         'attributes_view' => 'html',
         'shipping_groups_view' => 'html',
         'price_view' => 'html',
         'tax_view' => 'html',
         'shipping_view' => 'html',
         'status_view' => 'html',
      );
      if ($channelsEnabled) {
         $report->_rpt_format['channel_groups_view'] = 'html';
         $report->_rpt_format['channels_view'] = 'html';
      }
      $report->_rrows = $rowsPerPage;
      $report->_rpos = $position;
      $report->_count_all = count($allProducts);
      $report->_rcount = $filteredCount;
      $report->_rdata = $visibleProducts;

      $report->add_rep('product_tree_panel', $this->productTreePanel($allProducts, $report));
      $content = $report->run();
      if ($filteredCount === 0) {
         $content .= '<div class="alert alert-info mx-3">'
            . $this->h($report->get_fd_message('no_results'))
            . '</div>';
      }

      return $content;
   }



   private function productEdit(): string {
      $this->ensureSeed();
      $id = (int)dbx()->get_modul_var('id', 0, 'int');
      $isNew = $id <= 0;
      $exportNotice = '';
      $exportOk = false;
      $removeImageId = (int)dbx()->get_modul_var('remove_image', 0, 'int');
      if (!$isNew && $removeImageId > 0) {
         if ($this->checkActionToken('remove_image')) {
            $this->repo()->removeProductImageAssociation($removeImageId, $id);
            $this->syncShopMediaUsage();
         }
      }
      $exportChannel = trim((string)dbx()->get_modul_var('export_channel', '', 'parameter'));
      if (!$isNew && $exportChannel !== '') {
         if ($this->checkActionToken('export_channel')) {
            $result = $this->repo()->exportProductToChannel($id, $exportChannel);
            $exportOk = !empty($result['ok']);
            $exportNotice = (string)($result['message'] ?? '');
         } else {
            $exportNotice = $this->postedFormError;
         }
      }
      $data = $isNew ? $this->applyProductPreset($this->newProductDefaults()) : $this->repo()->productById($id);

      if (!$isNew && !is_array($data)) {
         return $this->frame('<div class="alert alert-warning m-3">Artikel nicht gefunden.</div>', 'Artikel bearbeiten', $this->productBarActions());
      }

      $form = dbx()->get_system_obj('dbxForm');
      $form->init('shop-product-form', 'shop-product-form');
      $form->_dd = 'dbxShop|shopProduct';
      $form->_fd = 'dbxShop|shop-product';
      $form->load_fd_messages();
      $form->_data = $data;
      $form->_rid = $isNew ? 0 : $id;
      $form->_action = '?dbx_modul=dbxShop_admin&dbx_run1=product_edit&id=' . ($isNew ? 0 : $id);
      $form->set_activ_id($isNew ? 0 : $id);
      $form->add_rep(
         'bar_title',
         $form->get_fd_message(
            $isNew ? 'form_new_title' : 'form_edit_title'
         )
      );
      $form->add_rep('bar_icon', 'bi-bag-check');
      $form->add_rep(
         'bar_subtitle',
         $isNew
            ? $form->get_fd_message('form_new_subtitle')
            : trim((string)($data['sku'] ?? '') . ' - ' . (string)($data['title'] ?? ''))
      );
      $form->add_rep('bar_class', 'dbx-bar--module');
      $form->add_rep('bar_title_class', 'dbx-bar-title');
      $form->add_rep('bar_title_pre', '');
      $form->add_rep('bar_title_heading_attrs', '');
      $form->add_rep('bar_middle', '');
      $form->add_rep('bar_actions_class', 'dbx-bar-actions');
      $form->add_rep('bar_actions', $this->productFormActions($id, $form));
      $form->add_rep('bar_extra', '');
      $form->add_rep('shop_admin_style', $this->shopAdminStyle());
      $form->_msg_info = $form->get_fd_message('form_info');
      if ($exportNotice !== '') {
         if ($exportOk) {
            $form->_msg_success = $exportNotice;
         } else {
            $form->_msg_error = $exportNotice;
         }
      }
      $form->add_flds();
      $form->add_fld(
         'product_group_id',
         tpl: 'select-single-label',
         label: $form->get_fd_message('field_product_group'),
         options: $this->productGroupOptions(0, false),
         rules: 'int'
      );

      if ($form->submit()) {
         if (!$form->errors()) {
            $ok = $form->save_post('dbxShop|shopProduct', $isNew ? 'new' : $id, $this->productFormDefaults($id));
            if ($ok) {
               $savedId = (int)$form->_rid;
               $groupId = (int)($_POST['product_group_id'] ?? 0);
               if ($savedId > 0 && $groupId > 0) {
                  $this->repo()->setProductGroupForProducts(array($savedId), $groupId);
               }
               if ($savedId > 0 && isset($_POST['product_channel_editor'])) {
                  $this->repo()->saveProductChannelOverrides($savedId, (array)($_POST['product_channels'] ?? array()));
               }
               $form->_msg_success = $form->get_fd_message(
                  'product_save_success'
               );
               if ($savedId > 0) {
                  $id = $savedId;
                  $isNew = false;
                  $data = $this->repo()->productById($savedId) ?: $form->_data;
                  $form->_action = '?dbx_modul=dbxShop_admin&dbx_run1=product_edit&id=' . $savedId;
               }
            } else {
               $form->_msg_error = $form->get_fd_message(
                  'product_save_error'
               );
            }
         } else {
            $form->_msg_error = $form->get_fd_message(
               'validation_error'
            );
         }
      }

      $form->add_obj(
         'product_images',
         'obj-value',
         $this->productImagesPanel(
            is_array($data) ? $data : array(),
            $isNew,
            $form
         )
      );
      $form->add_obj(
         'product_channels',
         'obj-value',
         $this->productChannelsPanel(
            is_array($data) ? $data : array(),
            $isNew,
            $form
         )
      );

      $content = $form->run();
      if (!$isNew) {
         // Eigene Upload-/Video-Formulare erst nach dem Artikel-Formular
         // einbetten, damit der Browser keine verschachtelte Form erzeugt.
         $content .= $this->shopMediaFormTemplates($this->shopMediaConfig());
      }
      return $content;
   }



   private function productsHelp(): string {
      $helpId = $this->ensureShopProductsHelpPage();
      if ($helpId > 0) {
         return $this->frame('<div class="m-3">' . $this->productsHelpHtml() . '</div>', 'Produkte Hilfe', $this->productBarActions());
      }
      return $this->frame('<div class="alert alert-warning m-3">Hilfe konnte nicht angelegt werden.</div>', 'Produkte Hilfe', $this->productBarActions());
   }
}
