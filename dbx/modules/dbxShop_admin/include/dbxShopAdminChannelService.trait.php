<?php
namespace dbx\dbxShop_admin;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

/**
 * Vertriebskanaele, Zugangsdaten und Providerkonfiguration ueber dbxForm.
 *
 * Zustandslose Quelltext-Komposition: keine zusaetzliche Instanz, kein Proxy
 * und keine zusaetzlichen Datenbank- oder Templatezugriffe.
 */
trait dbxShopAdminChannelServiceTrait {


   private function channels(): string {
      $this->ensureSeed();
      $texts = $this->catalogTexts();
      $notice = '';
      if ($this->posted('delete_channel')) {
         $this->repo()->deleteChannel((int)($_POST['id'] ?? 0));
      } elseif ($this->posted('save_channel')) {
         $this->repo()->updateChannel((int)($_POST['id'] ?? 0), $_POST);
      } elseif ($this->posted('test_channel')) {
         $id = (int)($_POST['id'] ?? 0);
         if ($id > 0) {
            $this->repo()->updateChannel($id, $_POST);
            $result = $this->repo()->testChannelConnection($id);
            $notice = '<div class="alert ' . (!empty($result['ok']) ? 'alert-success' : 'alert-warning') . ' m-3">' . $this->h($result['message'] ?? '') . '</div>';
         } else {
            $notice = '<div class="alert alert-warning m-3">' . $this->h($texts->get_fd_message('channels_save_first')) . '</div>';
         }
      }

      $platformOptions = array(
         'shop' => 'Shop',
         'amazon' => 'Amazon',
         'ebay' => 'eBay',
         'kleinanzeigen' => 'Kleinanzeigen',
         'mobile' => 'mobile.de',
         'custom' => $texts->get_fd_message('channels_platform_custom'),
      );
      $modeOptions = array(
         'internal' => $texts->get_fd_message('channels_mode_internal'),
         'manual' => $texts->get_fd_message('channels_mode_manual'),
         'api' => 'API',
         'feed' => 'Feed',
         'webhook' => 'Webhook',
      );

      $platformHints = array(
         'shop' => array($texts->get_fd_message('channels_hint_shop_api'), $texts->get_fd_message('channels_hint_shop_listing'), $texts->get_fd_message('channels_hint_shop_feedback')),
         'amazon' => array($texts->get_fd_message('channels_hint_amazon_api'), $texts->get_fd_message('channels_hint_amazon_listing'), $texts->get_fd_message('channels_hint_amazon_feedback')),
         'ebay' => array($texts->get_fd_message('channels_hint_ebay_api'), $texts->get_fd_message('channels_hint_ebay_listing'), $texts->get_fd_message('channels_hint_ebay_feedback')),
         'kleinanzeigen' => array($texts->get_fd_message('channels_hint_classified_api'), $texts->get_fd_message('channels_hint_classified_listing'), $texts->get_fd_message('channels_hint_classified_feedback')),
         'mobile' => array($texts->get_fd_message('channels_hint_mobile_api'), $texts->get_fd_message('channels_hint_mobile_listing'), $texts->get_fd_message('channels_hint_mobile_feedback')),
         'custom' => array($texts->get_fd_message('channels_hint_custom_api'), $texts->get_fd_message('channels_hint_custom_listing'), $texts->get_fd_message('channels_hint_custom_feedback')),
      );

      $rowHtml = function (array $channel, bool $isNew = false) use ($platformOptions, $modeOptions, $platformHints, $texts): string {
         $id = (int)($channel['id'] ?? 0);
         $key = (string)($channel['channel_key'] ?? '');
         $platform = (string)($channel['platform_type'] ?? 'custom');
         $hint = $platformHints[$platform] ?? $platformHints['custom'];
         $placeholderMap = array(
            'shop' => array(
               'channel_key' => 'shop',
               'api_base_url' => 'nicht benoetigt',
               'api_client_id' => 'nicht benoetigt',
               'api_username' => 'nicht benoetigt',
               'api_client_secret' => 'nicht benoetigt',
               'api_access_token' => 'nicht benoetigt',
               'api_refresh_token' => 'nicht benoetigt',
               'api_password' => 'nicht benoetigt',
               'marketplace_id' => 'nicht benoetigt',
               'seller_id' => 'nicht benoetigt',
               'account_id' => 'nicht benoetigt',
               'location_key' => 'nicht benoetigt',
               'category_id' => 'nicht benoetigt',
               'payment_policy_id' => 'nicht benoetigt',
               'fulfillment_policy_id' => 'nicht benoetigt',
               'return_policy_id' => 'nicht benoetigt',
               'notification_destination' => 'nicht benoetigt',
               'notification_topic' => 'nicht benoetigt',
               'webhook_secret' => 'nicht benoetigt',
               'webhook_url' => 'nicht benoetigt',
               'api_scope' => 'nicht benoetigt',
            ),
            'amazon' => array(
               'channel_key' => 'amazon',
               'api_base_url' => 'https://sellingpartnerapi-eu.amazon.com',
               'api_client_id' => 'amzn1.application-oa2-client.keyxxxx',
               'api_username' => 'nicht benoetigt bei OAuth',
               'api_client_secret' => 'amzn1.oa2-cs.v1.secretxxxx',
               'api_access_token' => 'Atza|access_token_xxxx',
               'api_refresh_token' => 'Atzr|refresh_token_xxxx',
               'api_password' => 'nicht benoetigt bei SP-API',
               'marketplace_id' => 'A1PA6795UKMFR9',
               'seller_id' => 'A1SELLERIDXXXX',
               'account_id' => 'account_xxxx',
               'location_key' => 'nicht benoetigt',
               'category_id' => 'productType: SOFTWARE / PRODUCT',
               'payment_policy_id' => 'nicht benoetigt',
               'fulfillment_policy_id' => 'nicht benoetigt',
               'return_policy_id' => 'nicht benoetigt',
               'notification_destination' => 'arn:aws:sqs:eu-central-1:123456789012:amazon-orders',
               'notification_topic' => 'ORDER_CHANGE',
               'webhook_secret' => 'secret_64zeichen_xxxx',
               'webhook_url' => 'wird von dbxShop erzeugt',
               'api_scope' => "Listings Items\nOrders\nNotifications",
            ),
            'ebay' => array(
               'channel_key' => 'ebay',
               'api_base_url' => 'https://api.ebay.com',
               'api_client_id' => 'keyxxxx-appid',
               'api_username' => 'nicht benoetigt bei OAuth',
               'api_client_secret' => 'certid-secretxxxx',
               'api_access_token' => 'v^1.1#i^1#p^3#access_token_xxxx',
               'api_refresh_token' => 'v^1.1#r^1#p^3#refresh_token_xxxx',
               'api_password' => 'nicht benoetigt bei OAuth',
               'marketplace_id' => 'EBAY_DE',
               'seller_id' => 'sellername_xxxx',
               'account_id' => 'account_xxxx',
               'location_key' => 'default',
               'category_id' => '58058',
               'payment_policy_id' => 'policy_payment_1234567890',
               'fulfillment_policy_id' => 'policy_fulfillment_1234567890',
               'return_policy_id' => 'policy_return_1234567890',
               'notification_destination' => 'https://domain.de/?dbx_modul=dbxShop&dbx_run1=channel_webhook&channel=ebay',
               'notification_topic' => 'ORDER',
               'webhook_secret' => 'verification_token_xxxx',
               'webhook_url' => 'wird von dbxShop erzeugt',
               'api_scope' => "https://api.ebay.com/oauth/api_scope/sell.inventory\nhttps://api.ebay.com/oauth/api_scope/sell.fulfillment\nhttps://api.ebay.com/oauth/api_scope/commerce.notification.subscription",
            ),
            'kleinanzeigen' => array(
               'channel_key' => 'kleinanzeigen',
               'api_base_url' => 'nur bei freigegebener Schnittstelle',
               'api_client_id' => 'partner_key_xxxx',
               'api_username' => 'api-user@example.de',
               'api_client_secret' => 'partner_secret_xxxx',
               'api_access_token' => 'access_token_xxxx',
               'api_refresh_token' => 'refresh_token_xxxx',
               'api_password' => 'password_xxxx',
               'marketplace_id' => 'DE',
               'seller_id' => 'seller_xxxx',
               'account_id' => 'account_xxxx',
               'location_key' => 'standort_10115_berlin',
               'category_id' => 'category_12345',
               'payment_policy_id' => 'nicht benoetigt',
               'fulfillment_policy_id' => 'nicht benoetigt',
               'return_policy_id' => 'nicht benoetigt',
               'notification_destination' => 'https://middleware.example.de/webhook',
               'notification_topic' => 'lead / message / sale',
               'webhook_secret' => 'middleware_secret_xxxx',
               'webhook_url' => 'wird von dbxShop erzeugt',
               'api_scope' => "laut Vertrag\nMiddleware-Berechtigung",
            ),
            'mobile' => array(
               'channel_key' => 'mobile',
               'api_base_url' => 'https://services.mobile.de/seller-api',
               'api_client_id' => 'nicht benoetigt bei Basic Auth',
               'api_username' => 'dealer_api_user_xxxx',
               'api_client_secret' => 'nicht benoetigt bei Basic Auth',
               'api_access_token' => 'nicht benoetigt bei Basic Auth',
               'api_refresh_token' => 'nicht benoetigt bei Basic Auth',
               'api_password' => 'dealer_api_password_xxxx',
               'marketplace_id' => 'DE',
               'seller_id' => 'customer_123456',
               'account_id' => 'mobileSellerId_123456',
               'location_key' => 'location_123456',
               'category_id' => 'car / motorbike / commercial',
               'payment_policy_id' => 'nicht benoetigt',
               'fulfillment_policy_id' => 'nicht benoetigt',
               'return_policy_id' => 'nicht benoetigt',
               'notification_destination' => 'https://middleware.example.de/mobile-leads',
               'notification_topic' => 'lead-api',
               'webhook_secret' => 'lead_secret_xxxx',
               'webhook_url' => 'wird von dbxShop erzeugt',
               'api_scope' => "seller-api\nbasic-auth\nlead-api",
            ),
            'custom' => array(
               'channel_key' => 'mein-channel',
               'api_base_url' => 'https://api.anbieter.de/v1',
               'api_client_id' => 'client_123456',
               'api_username' => 'api-user@example.de',
               'api_client_secret' => 'client_secret_xxxxx',
               'api_access_token' => 'access_token_xxxxx',
               'api_refresh_token' => 'refresh_token_xxxxx',
               'api_password' => 'API-Passwort',
               'marketplace_id' => 'DE',
               'seller_id' => 'seller_123456',
               'account_id' => 'account_123456',
               'location_key' => 'lager-1',
               'category_id' => '12345',
               'payment_policy_id' => 'payment_123',
               'fulfillment_policy_id' => 'shipping_123',
               'return_policy_id' => 'return_123',
               'notification_destination' => 'https://domain.de/webhook',
               'notification_topic' => 'order.created',
               'webhook_secret' => 'zufaelliges-geheimes-secret',
               'webhook_url' => 'wird von dbxShop erzeugt',
               'api_scope' => "products:write\norders:read\nwebhooks:read",
            ),
         );
         $placeholders = $placeholderMap[$platform] ?? $placeholderMap['custom'];
         $placeholderTranslations = array(
            'nicht benoetigt' => $texts->get_fd_message('channels_not_required'),
            'nicht benoetigt bei OAuth' => $texts->get_fd_message('channels_not_required_oauth'),
            'nicht benoetigt bei SP-API' => $texts->get_fd_message('channels_not_required_spapi'),
            'nicht benoetigt bei Basic Auth' => $texts->get_fd_message('channels_not_required_basic'),
            'wird von dbxShop erzeugt' => $texts->get_fd_message('channels_generated'),
            'nur bei freigegebener Schnittstelle' => $texts->get_fd_message('channels_approved_only'),
         );
         foreach ($placeholders as $placeholderField => $placeholderValue) {
            if (isset($placeholderTranslations[$placeholderValue])) {
               $placeholders[$placeholderField] = $placeholderTranslations[$placeholderValue];
            }
         }
         $ph = function (string $field) use ($placeholders): string {
            return $this->h($placeholders[$field] ?? '');
         };
         $secretPlaceholder = function (string $field) use ($placeholders): string {
            return $this->h((string)($placeholders[$field] ?? ''));
         };
         $status = (string)($channel['test_status'] ?? '');
         $statusBadge = $status === 'ok'
            ? '<span class="badge text-bg-success">' . $this->h($texts->get_fd_message('channels_status_ok')) . '</span>'
            : ($status === 'error'
               ? '<span class="badge text-bg-warning">' . $this->h($texts->get_fd_message('channels_status_open')) . '</span>'
               : '<span class="badge text-bg-secondary">' . $this->h($texts->get_fd_message('channels_status_none')) . '</span>');
         $activeBadge = (int)($channel['active'] ?? 1) === 1
            ? '<span class="badge text-bg-success">' . $this->h($texts->get_fd_message('active')) . '</span>'
            : '<span class="badge text-bg-secondary">' . $this->h($texts->get_fd_message('inactive')) . '</span>';
         $exportBadge = (int)($channel['export_enabled'] ?? 0) === 1 ? '<span class="badge text-bg-info">' . $this->h($texts->get_fd_message('channels_export')) . '</span>' : '';
         $orderBadge = (int)($channel['order_import_enabled'] ?? 0) === 1 ? '<span class="badge text-bg-primary">' . $this->h($texts->get_fd_message('channels_order_import')) . '</span>' : '';
         $webhookPath = ($key !== '' && $platform !== 'shop') ? '?dbx_modul=dbxShop&dbx_run1=channel_webhook&channel=' . rawurlencode($key) : '';
         $open = $isNew || (int)($_GET['edit'] ?? 0) === $id;
         $editUrl = '?dbx_modul=dbxShop_admin&dbx_run1=channels&edit=' . $id;
         $helpId = $this->ensureShopChannelProviderHelpPage($platform);
         $helpButton = '';
         if ($helpId > 0) {
            $helpTitle = 'Hilfe: Channel ' . ($platformOptions[$platform] ?? 'Channel');
            $helpButton = $this->openWinButton(
               '?dbx_modul=dbxContent&dbx_run1=content&cid=' . $helpId,
               $helpTitle,
               '<i class="bi bi-question-circle"></i><span class="visually-hidden"> Hilfe</span>',
               'btn btn-outline-secondary btn-sm dbx-shop-channel-help',
               '72%',
               '82%'
            );
            $helpButton = str_replace('<a ', '<a data-dbx="lib=shopAdmin" data-shop-stop-propagation ', $helpButton);
         }

         $form = dbx()->get_system_obj('dbxForm');
         $form->init('shop-channel-form-' . ($isNew ? 'new' : $id), 'shop-channel-form');
         $form->_dd = 'dbxShop|shopChannel';
         $form->_fd = 'dbxShop|shop-channel';
         $form->_data = $channel + array('id' => $id);
         $form->_rid = $isNew ? 0 : $id;
         $form->_action = '?dbx_modul=dbxShop_admin&dbx_run1=channels' . ($isNew ? '&new=1' : '&edit=' . $id);
         $form->set_activ_id($isNew ? 0 : $id);
         $form->set_form_help_enabled(false);
         $form->add_rep('frame_skip_form_wrap', '0');
         $form->add_rep('form_class', 'dbx-shop-channel-dbXForm');
         $form->add_rep('form_attrs', 'data-target="dbxForm_{i}" data-dbx="lib=confirm|class=dbxConfirm|bind=button"');
         $form->add_rep('details_open', $open ? ' open' : '');
         $form->add_rep('channel_key_view', $this->h($key !== '' ? $key : strtolower($texts->get_fd_message('new'))));
         $form->add_rep('channel_title_view', $this->h($channel['title'] ?? $texts->get_fd_message('channels_new')));
         $form->add_rep('platform_view', $this->h($platformOptions[$platform] ?? $platform));
         $form->add_rep('connection_view', $this->h($modeOptions[(string)($channel['connection_mode'] ?? 'manual')] ?? ($channel['connection_mode'] ?? 'manual')));
         $form->add_rep('active_badge', $activeBadge);
         $form->add_rep('export_badge', $exportBadge);
         $form->add_rep('order_badge', $orderBadge);
         $form->add_rep('status_badge', $statusBadge);
         $form->add_rep('last_test_date', !empty($channel['last_test_date']) ? $this->h($channel['last_test_date']) : '');
         $form->add_rep('channel_help_button', $helpButton);
         $form->add_rep('channel_edit_button', !$isNew ? '<a class="btn btn-outline-primary btn-sm dbx-shop-channel-edit" data-dbx="lib=shopAdmin" data-shop-stop-propagation href="' . $this->h($editUrl) . '" title="' . $this->h($texts->get_fd_message('channels_edit_title')) . '"><i class="bi bi-pencil-square"></i> ' . $this->h($texts->get_fd_message('channels_edit')) . '</a>' : '');
         $form->add_rep('hint_api', $this->h($hint[0]));
         $form->add_rep('hint_listing', $this->h($hint[1]));
         $form->add_rep('hint_feedback', $this->h($hint[2]));
         $form->add_rep('test_message', !empty($channel['test_message']) ? '<div class="col-12"><div class="alert alert-secondary py-2 mb-0">' . $this->h($channel['test_message']) . '</div></div>' : '');
         $form->add_rep('test_button', !$isNew ? '<button class="btn btn-outline-secondary btn-sm ms-1" name="shop_action" value="test_channel" data-dbx-tooltip="' . $this->h($texts->get_fd_message('channels_test_title')) . '"><i class="bi bi-plug"></i> ' . $this->h($texts->get_fd_message('channels_test_label')) . '</button>' : '');
         $form->add_rep('delete_button', !$isNew ? '<button type="submit" class="btn btn-outline-danger btn-sm ms-1 dbxConfirm" name="shop_action" value="delete_channel" data-dbx-tooltip="' . $this->h($texts->get_fd_message('channels_delete_title')) . '" data-confirm-title="<i class=\'bi bi-trash\'></i> ' . $this->h($texts->get_fd_message('channels_delete_title')) . '" data-confirm="' . $this->h($texts->get_fd_message('channels_delete_confirm')) . '" data-confirm-hint="<small>' . $this->h($texts->get_fd_message('channels_delete_hint')) . '</small>" data-confirm-buttons="yesno"><i class="bi bi-trash"></i></button>' : '');
         $form->_msg_info = '';

         $form->add_fld('id', tpl: 'dbx|hidden', rules: 'int', dd: 'dd::');
         $form->add_fld('channel_key', placeholder: $placeholders['channel_key'] ?? '', data: $isNew ? '' : 'readonly=readonly');
         $form->add_fld('title');
         $form->add_fld('platform_type', tpl: 'select-single-label', options: $platformOptions);
         $form->add_fld('connection_mode', tpl: 'select-single-label', options: $modeOptions);
         $form->add_fld('sorter');
         $form->add_fld('active');
         $form->add_fld('export_enabled');
         $form->add_fld('order_import_enabled');
         $form->add_fld('description', data: 'rows=2');
         $form->add_fld('api_base_url', placeholder: $placeholders['api_base_url'] ?? '');
         $form->add_fld('api_client_id', placeholder: $placeholders['api_client_id'] ?? '');
         $form->add_fld('api_username', placeholder: $placeholders['api_username'] ?? '');
         $form->add_fld('api_client_secret', placeholder: $secretPlaceholder('api_client_secret'));
         $form->add_fld('api_access_token', placeholder: $secretPlaceholder('api_access_token'));
         $form->add_fld('api_refresh_token', placeholder: $secretPlaceholder('api_refresh_token'));
         $form->add_fld('api_password', placeholder: $secretPlaceholder('api_password'));
         $form->add_fld('marketplace_id', placeholder: $placeholders['marketplace_id'] ?? '');
         $form->add_fld('seller_id', placeholder: $placeholders['seller_id'] ?? '');
         $form->add_fld('account_id', placeholder: $placeholders['account_id'] ?? '');
         $form->add_fld('location_key', placeholder: $placeholders['location_key'] ?? '');
         $form->add_fld('category_id', placeholder: $placeholders['category_id'] ?? '');
         $form->add_fld('payment_policy_id', placeholder: $placeholders['payment_policy_id'] ?? '');
         $form->add_fld('fulfillment_policy_id', placeholder: $placeholders['fulfillment_policy_id'] ?? '');
         $form->add_fld('return_policy_id', placeholder: $placeholders['return_policy_id'] ?? '');
         $form->add_fld('notification_destination', placeholder: $placeholders['notification_destination'] ?? '');
         $form->add_fld('notification_topic', placeholder: $placeholders['notification_topic'] ?? '');
         $form->add_fld('webhook_secret', placeholder: $secretPlaceholder('webhook_secret'));
         $form->add_fld('api_scope', placeholder: $placeholders['api_scope'] ?? '', data: 'rows=2');
         $form->add_obj('webhook_url', 'obj-value', '<label class="form-label">' . $this->h($texts->get_fd_message('channels_webhook_url')) . '</label><input class="form-control form-control-sm" value="' . $this->h($webhookPath) . '" placeholder="' . $ph('webhook_url') . '" readonly>');

         return $form->run();
      };

      $channels = $this->repo()->channels();
      $content = $notice . '<div class="m-3 dbx-shop-channel-list">';
      if ((int)($_GET['new'] ?? 0) === 1) {
         $sorter = 10;
         foreach ($channels as $channel) {
            $sorter = max($sorter, (int)($channel['sorter'] ?? 0) + 10);
         }
         $content .= $rowHtml(array(
            'title' => '',
            'platform_type' => 'custom',
            'connection_mode' => 'api',
            'export_enabled' => 1,
            'order_import_enabled' => 1,
            'active' => 1,
            'sorter' => $sorter,
         ), true);
      }
      foreach ($channels as $channel) {
         $content .= $rowHtml($channel);
      }
      $content .= '</div>';

      $helpId = $this->ensureShopChannelsHelpPage();
      $helpButton = $helpId > 0
         ? $this->openWinButton('?dbx_modul=dbxContent&dbx_run1=content&cid=' . $helpId, $texts->get_fd_message('channels_help'), '<i class="bi bi-question-circle"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('help')) . '</span>', 'btn btn-outline-secondary btn-sm me-1', '72%', '82%')
         : '';
      $barActions = '<a class="btn btn-outline-primary btn-sm me-1" href="?dbx_modul=dbxShop_admin&dbx_run1=channels&new=1" data-dbx-tooltip="' . $this->h($texts->get_fd_message('channels_new_title')) . '"><i class="bi bi-plus-square"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('channels_new')) . '</span></a>' . $helpButton;
      return $this->frame($content, $texts->get_fd_message('channels_title'), $barActions);
   }
}
