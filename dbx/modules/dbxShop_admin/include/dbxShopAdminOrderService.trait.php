<?php
namespace dbx\dbxShop_admin;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

/**
 * Bestell-, Retouren-, Rechnungs- und Statusmailablaeufe ueber Repository und dbxReport.
 *
 * Zustandslose Quelltext-Komposition: keine zusaetzliche Instanz, kein Proxy
 * und keine zusaetzlichen Datenbank- oder Templatezugriffe.
 */
trait dbxShopAdminOrderServiceTrait {


   private function orderStatusOptions($texts = null): array {
      return array(
         'new' => $texts ? $texts->get_fd_message('order_status_new', 'Neu') : 'Neu',
         'payment_pending' => $texts ? $texts->get_fd_message('order_status_payment_pending', 'Zahlung offen') : 'Zahlung offen',
         'paid' => $texts ? $texts->get_fd_message('order_status_paid', 'Bezahlt') : 'Bezahlt',
         'processing' => $texts ? $texts->get_fd_message('order_status_processing', 'In Bearbeitung') : 'In Bearbeitung',
         'shipped' => $texts ? $texts->get_fd_message('order_status_shipped', 'Versendet') : 'Versendet',
         'done' => $texts ? $texts->get_fd_message('order_status_done', 'Abgeschlossen') : 'Abgeschlossen',
         'cancelled' => $texts ? $texts->get_fd_message('order_status_cancelled', 'Storniert') : 'Storniert',
      );
   }



   private function paymentStatusOptions($texts = null): array {
      return array(
         'open' => $texts ? $texts->get_fd_message('payment_status_open', 'Offen') : 'Offen',
         'created' => $texts ? $texts->get_fd_message('payment_status_created', 'Erstellt') : 'Erstellt',
         'pending' => $texts ? $texts->get_fd_message('payment_status_pending', 'In Prüfung') : 'In Prüfung',
         'completed' => $texts ? $texts->get_fd_message('payment_status_completed', 'Abgeschlossen') : 'Abgeschlossen',
         'paid' => $texts ? $texts->get_fd_message('payment_status_paid', 'Bezahlt') : 'Bezahlt',
         'failed' => $texts ? $texts->get_fd_message('payment_status_failed', 'Fehlgeschlagen') : 'Fehlgeschlagen',
         'cancelled' => $texts ? $texts->get_fd_message('payment_status_cancelled', 'Abgebrochen') : 'Abgebrochen',
         'refunded' => $texts ? $texts->get_fd_message('payment_status_refunded', 'Erstattet') : 'Erstattet',
      );
   }



   private function shippingStatusOptions($texts = null): array {
      return array(
         'open' => $texts ? $texts->get_fd_message('shipping_status_open', 'Offen') : 'Offen',
         'ready' => $texts ? $texts->get_fd_message('shipping_status_ready', 'Bereit') : 'Bereit',
         'shipped' => $texts ? $texts->get_fd_message('shipping_status_shipped', 'Versendet') : 'Versendet',
         'delivered' => $texts ? $texts->get_fd_message('shipping_status_delivered', 'Zugestellt') : 'Zugestellt',
         'returned' => $texts ? $texts->get_fd_message('shipping_status_returned', 'Retoure') : 'Retoure',
      );
   }



   private function orderStatusBadge(string $status, $texts = null): string {
      $labels = $this->orderStatusOptions($texts);
      $classes = array(
         'new' => 'text-bg-secondary',
         'payment_pending' => 'text-bg-warning',
         'paid' => 'text-bg-success',
         'processing' => 'text-bg-info',
         'shipped' => 'text-bg-primary',
         'done' => 'text-bg-success',
         'cancelled' => 'text-bg-danger',
      );
      return '<span class="badge ' . $this->h($classes[$status] ?? 'text-bg-secondary') . '">' . $this->h($labels[$status] ?? $status) . '</span>';
   }



   private function paymentStatusBadge(string $status, $texts = null): string {
      $labels = $this->paymentStatusOptions($texts);
      $classes = array(
         'open' => 'text-bg-secondary',
         'created' => 'text-bg-info',
         'pending' => 'text-bg-warning',
         'completed' => 'text-bg-success',
         'paid' => 'text-bg-success',
         'failed' => 'text-bg-danger',
         'cancelled' => 'text-bg-danger',
         'refunded' => 'text-bg-dark',
      );
      return '<span class="badge ' . $this->h($classes[$status] ?? 'text-bg-secondary') . '">' . $this->h($labels[$status] ?? $status) . '</span>';
   }



   private function shippingStatusBadge(string $status, $texts = null): string {
      $labels = $this->shippingStatusOptions($texts);
      $classes = array(
         'open' => 'text-bg-secondary',
         'ready' => 'text-bg-info',
         'shipped' => 'text-bg-primary',
         'delivered' => 'text-bg-success',
         'returned' => 'text-bg-warning',
      );
      return '<span class="badge ' . $this->h($classes[$status] ?? 'text-bg-secondary') . '">' . $this->h($labels[$status] ?? $status) . '</span>';
   }



   private function channelLabel(string $channel): string {
      $labels = array(
         'shop' => 'Shop',
         'web' => 'Web',
         'amazon' => 'Amazon',
         'ebay' => 'eBay',
         'kleinanzeigen' => 'Kleinanzeigen',
         'mobile' => 'mobile.de',
      );
      return $labels[$channel] ?? $channel;
   }



   private function paymentProviderLabel(string $provider, $texts = null): string {
      $labels = array(
         'bank_transfer' => $texts ? $texts->get_fd_message('payment_bank_transfer', 'Vorkasse / Überweisung') : 'Vorkasse / Überweisung',
         'invoice' => $texts ? $texts->get_fd_message('payment_invoice', 'Rechnung') : 'Rechnung',
         'paypal' => 'PayPal',
         'amazon_pay' => 'Amazon Pay',
      );
      return $labels[$provider] ?? $this->channelLabel($provider);
   }



   private function channelBadge(string $channel): string {
      $class = in_array($channel, array('shop', 'web', ''), true) ? 'text-bg-secondary' : 'text-bg-info';
      $text = $channel === '' ? 'Shop' : $this->channelLabel($channel);
      return '<span class="badge ' . $class . '">' . $this->h($text) . '</span>';
   }



   private function orderActions($texts = null): string {
      $helpId = $this->ensureShopOrdersHelpPage();
      $helpTitle = $texts
         ? $texts->get_fd_message('help_orders', 'Hilfe: Bestellungen')
         : 'Hilfe: Bestellungen';
      $helpLabel = $texts
         ? $texts->get_fd_message('help_label', 'Hilfe')
         : 'Hilfe';
      $helpButton = $helpId > 0
         ? $this->openWinButton('?dbx_modul=dbxContent&dbx_run1=content&cid=' . $helpId, $helpTitle, '<i class="bi bi-question-circle"></i><span class="visually-hidden"> ' . $this->h($helpLabel) . '</span>', 'btn btn-outline-secondary btn-sm me-1', '72%', '82%')
         : '';
      return $helpButton;
   }



   private function handleOrderAction($report): void {
      $deleteId = (int)dbx()->get_modul_var('delete_order', 0, 'int');
      if ($deleteId <= 0) {
         return;
      }
      if (!$this->checkActionToken('delete_order')) {
         $report->_msg_error = $report->get_fd_message('token_error');
         return;
      }
      if ($this->repo()->deleteOrder($deleteId)) {
         $report->_msg_success = $report->get_fd_message('delete_success');
      } else {
         $report->_msg_error = $report->get_fd_message('delete_error');
      }
   }



   public function order_report_next_record($report, $record) {
      $id = (int)($record['id'] ?? 0);
      $orderNo = (string)($record['order_no'] ?? '');
      $created = (string)($record['create_date'] ?? '');
      $channel = (string)($record['channel_key'] ?? 'shop');
      $items = (array)($record['items'] ?? array());
      $itemLines = '';
      foreach ($items as $item) {
         $itemLines .= '<div>' . (int)($item['qty'] ?? 0) . 'x <strong>' . $this->h($item['title'] ?? '') . '</strong> <code>' . $this->h($item['sku'] ?? '') . '</code></div>';
      }
      if ($itemLines === '') {
         $itemLines = '<span class="text-muted">'
            . $this->h($report->get_fd_message('no_items'))
            . '</span>';
      }
      $detailUrl = '?dbx_modul=dbxShop_admin&dbx_run1=order_detail&id=' . $id;
      $deleteUrl = $this->actionUrl('?dbx_modul=dbxShop_admin&dbx_run1=orders&delete_order=' . $id);

      $externalId = trim((string)($record['payment_reference'] ?? ''));
      $sourceText = in_array($channel, array('', 'shop', 'web'), true)
         ? $report->get_fd_message('source_shop_order')
         : $report->get_fd_message('source_channel_order');
      $externalText = $externalId !== ''
         ? '<small>' . $this->h($report->format_fd_message('external_reference', array('reference' => $externalId))) . '</small>'
         : '';
      $record['order_view'] = '<div class="dbx-shop-order-main"><strong>' . $this->h($orderNo) . '</strong><small>' . $this->h($created) . '</small><span class="dbx-shop-order-source">' . $this->channelBadge($channel) . '<small>' . $this->h($sourceText) . '</small></span>' . $externalText . '</div>';
      $record['customer_view'] = '<div class="dbx-shop-order-customer"><strong>' . $this->h($record['customer_name'] ?? '') . '</strong><small>' . $this->h($record['customer_email'] ?? '') . '</small></div>';
      $record['items_view'] = '<div class="dbx-shop-order-items-small">' . $itemLines . '</div>';
      $record['status_view'] = '<div class="dbx-shop-order-status-stack">'
         . '<span><small>' . $this->h($report->get_fd_message('label_order')) . '</small>' . $this->orderStatusBadge((string)($record['status'] ?? 'new'), $report) . '</span>'
         . '<span><small>' . $this->h($report->get_fd_message('label_shipping')) . '</small>' . $this->shippingStatusBadge((string)($record['shipping_status'] ?? 'open'), $report) . '</span>'
         . '</div>';
      $record['payment_view'] = '<div class="dbx-shop-order-payment">' . $this->paymentStatusBadge((string)($record['payment_status'] ?? 'open'), $report) . '<small>' . $this->h($this->paymentProviderLabel((string)($record['payment_provider'] ?? ''), $report)) . '</small><small>' . $this->h($record['payment_reference'] ?? '') . '</small></div>';
      $record['total_view'] = $this->money($record['total_gross'] ?? 0);
      $record['actions_view'] = '<span class="dbx-shop-order-actions">'
         . $this->openWinButton($detailUrl, $report->format_fd_message('action_edit_title', array('number' => $orderNo)), '<i class="bi bi-pencil-square"></i><span class="visually-hidden"> ' . $this->h($report->get_fd_message('action_edit')) . '</span>', 'btn btn-outline-primary btn-sm', '86%', '88%')
         . '<a class="btn btn-outline-danger btn-sm dbxConfirm" href="' . $this->h($deleteUrl) . '" data-confirm-title="<i class=\'bi bi-trash\'></i> ' . $this->h($report->get_fd_message('delete_title')) . '" data-confirm="' . $this->h($report->get_fd_message('delete_question')) . '" data-confirm-hint="<small>' . $this->h($report->get_fd_message('delete_hint')) . '</small>" data-confirm-buttons="yesno" title="' . $this->h($report->get_fd_message('delete_title')) . '"><i class="bi bi-trash"></i><span class="visually-hidden"> ' . $this->h($report->get_fd_message('delete_label')) . '</span></a>'
         . '</span>';
      return $record;
   }



   private function orders(): string {
      $this->ensureSeed();
      $report = dbx()->get_system_obj('dbxReport');
      $report->init('shop-orders-report');
      $report->_fd = 'dbxShop_admin|rpt-orders-selection';
      $report->load_fd_messages();
      $report->_action = '?dbx_modul=dbxShop_admin&dbx_run1=orders';
      $report->_mode = 'table';
      $report->_pages = true;
      $report->_create_sel_flds = true;
      $report->_but_pagination = 7;
      $report->_fld_id = 'id';
      $report->_msg_info = $report->get_fd_message('report_info');
      $report->add_rep('bar_title', $report->get_fd_message('bar_title'));
      $report->add_rep('bar_icon', 'bi-receipt');
      $report->add_rep('bar_subtitle', $report->get_fd_message('bar_subtitle'));
      $report->add_rep('bar_class', 'dbx-bar--module');
      $report->add_rep('bar_title_class', 'dbx-bar-title');
      $report->add_rep('bar_actions_class', 'dbx-bar-actions');
      $report->add_rep('bar_title_pre', '');
      $report->add_rep('bar_title_heading_attrs', '');
      $report->add_rep('bar_middle', '');
      $report->add_rep('bar_extra', '');
      $report->add_rep('bar_actions', $this->orderActions($report));
      $report->add_rep('shop_admin_style', $this->shopAdminStyle());
      foreach (array(
         'order_view' => 'dbx-shop-col-order',
         'customer_view' => 'dbx-shop-col-customer',
         'items_view' => 'dbx-shop-col-items',
         'status_view' => 'dbx-shop-col-status',
         'payment_view' => 'dbx-shop-col-payment',
         'total_view' => 'dbx-shop-col-total',
         'actions_view' => 'dbx-shop-col-actions',
      ) as $field => $class) {
         $report->set_class_haeder($field, $class);
         $report->_class_body[$field] = $class;
      }
      $report->set_callback_owner($this);
      $report->set_callback('next_record', 'order_report_next_record');
      $report->create_selection_fields('dbxShop_admin|rpt-orders-selection');
      $this->handleOrderAction($report);

      $query = trim((string)$report->get_fld_val('dbx_rwhere', '', 'parameter|max=120'));
      $requestedRowsPerPage = (int)$report->get_fld_val('dbx_rrows', 30, 'int');
      $rowsPerPage = $requestedRowsPerPage === 0 ? 0 : max(10, min(100, $requestedRowsPerPage));
      $position = $rowsPerPage === 0 ? 0 : max(0, (int)$report->get_fld_val('dbx_rpos', 0, 'int'));
      $sort = (string)$report->get_fld_val('dbx_rsort', 'create_date', 'parameter');
      $direction = strtoupper((string)$report->get_fld_val('dbx_rdesc', 'DESC', 'parameter')) === 'ASC' ? 'ASC' : 'DESC';
      $filters = array(
         'query' => $query,
         'status' => trim((string)$report->get_fld_val('status', '', 'parameter')),
         'payment_status' => trim((string)$report->get_fld_val('payment_status', '', 'parameter')),
         'shipping_status' => trim((string)$report->get_fld_val('shipping_status', '', 'parameter')),
         'channel_key' => trim((string)$report->get_fld_val('channel_key', '', 'parameter')),
      );

      $filteredCount = $this->repo()->orderCount($filters);
      if ($rowsPerPage > 0 && $position >= $filteredCount && $filteredCount > 0) {
         $position = max(0, (int)(floor(($filteredCount - 1) / $rowsPerPage) * $rowsPerPage));
      }
      $orders = $this->repo()->orders($filters, $rowsPerPage, $position, $sort, $direction);

      $report->_rflds = array(
         'order_view' => $report->get_fd_message('column_order'),
         'customer_view' => $report->get_fd_message('column_customer'),
         'items_view' => $report->get_fd_message('column_items'),
         'status_view' => $report->get_fd_message('column_status'),
         'payment_view' => $report->get_fd_message('column_payment'),
         'total_view' => $report->get_fd_message('column_total'),
         'actions_view' => $report->get_fd_message('column_action'),
      );
      $report->_rpt_format = array(
         'order_view' => 'html',
         'customer_view' => 'html',
         'items_view' => 'html',
         'status_view' => 'html',
         'payment_view' => 'html',
         'total_view' => 'html',
         'actions_view' => 'html',
      );
      $report->_rrows = $rowsPerPage;
      $report->_rpos = $position;
      $report->_count_all = $this->repo()->orderCount(array());
      $report->_rcount = $filteredCount;
      $report->_rdata = $orders;

      $content = $report->run();
      if ($filteredCount === 0) {
         $content .= '<div class="alert alert-info mx-3">'
            . $this->h($report->get_fd_message('no_results'))
            . '</div>';
      }
      return $content;
   }



   public function withdrawal_report_next_record($report, $record) {
      if (!is_array($record)) {
         return $record;
      }
      $record['request_view'] = '<div><strong>' . $this->h($record['order_no'] ?? $report->get_fd_message('without_order_no')) . '</strong><br><small>' . $this->h($record['create_date'] ?? '') . '</small></div>';
      $record['customer_view'] = '<div><strong>' . $this->h($record['customer_name'] ?? '') . '</strong><br><small>' . $this->h($record['customer_email'] ?? '') . '</small></div>';
      $record['message_view'] = '<div class="small">' . nl2br($this->h($record['reason'] ?? '')) . '</div>';
      $status = (string)($record['status'] ?? '');
      $badge = in_array($status, array('accepted', 'refunded', 'closed'), true) ? 'text-bg-success' : (in_array($status, array('rejected'), true) ? 'text-bg-danger' : 'text-bg-warning');
      $statusLabel = $report->get_fd_message('status_' . $status, $status);
      $record['status_view'] = '<span class="badge ' . $badge . '">' . $this->h($statusLabel) . '</span>';
      $id = (int)($record['id'] ?? 0);
      $base = '?dbx_modul=dbxShop_admin&dbx_run1=returns&withdrawal_id=' . $id . '&withdrawal_status=';
      $record['actions_view'] =
         '<div class="btn-group btn-group-sm" role="group">'
         . '<a class="btn btn-outline-secondary" href="' . $this->h($this->actionUrl($base . 'processing')) . '" title="' . $this->h($report->get_fd_message('action_processing')) . '"><i class="bi bi-hourglass-split"></i></a>'
         . '<a class="btn btn-outline-success dbxConfirm" href="' . $this->h($this->actionUrl($base . 'accepted')) . '" data-confirm-title="' . $this->h($report->get_fd_message('action_accept_title')) . '" data-confirm="' . $this->h($report->get_fd_message('action_accept_question')) . '" data-confirm-buttons="yesno" title="' . $this->h($report->get_fd_message('action_accept')) . '"><i class="bi bi-check2"></i></a>'
         . '<a class="btn btn-outline-primary dbxConfirm" href="' . $this->h($this->actionUrl($base . 'refunded')) . '" data-confirm-title="' . $this->h($report->get_fd_message('action_refund_title')) . '" data-confirm="' . $this->h($report->get_fd_message('action_refund_question')) . '" data-confirm-buttons="yesno" title="' . $this->h($report->get_fd_message('action_refunded')) . '"><i class="bi bi-cash-coin"></i></a>'
         . '<a class="btn btn-outline-danger" href="' . $this->h($this->actionUrl($base . 'rejected')) . '" title="' . $this->h($report->get_fd_message('action_reject')) . '"><i class="bi bi-x-lg"></i></a>'
         . '<a class="btn btn-outline-secondary" href="' . $this->h($this->actionUrl($base . 'closed')) . '" title="' . $this->h($report->get_fd_message('action_close')) . '"><i class="bi bi-archive"></i></a>'
         . '</div>';
      return $record;
   }



   private function returns(): string {
      $this->ensureSeed();
      $withdrawalId = (int)dbx()->get_modul_var('withdrawal_id', 0, 'int');
      $withdrawalStatus = (string)dbx()->get_modul_var('withdrawal_status', '', 'parameter');
      if ($withdrawalId > 0 && $withdrawalStatus !== '') {
         if ($this->checkActionToken('withdrawal_status')) {
            $this->repo()->updateWithdrawalAdmin($withdrawalId, $withdrawalStatus);
         }
      }
      $report = dbx()->get_system_obj('dbxReport');
      $report->init('shop-orders-report');
      $report->_fd = 'dbxShop_admin|rpt-withdrawals-selection';
      $report->load_fd_messages();
      $report->_action = '?dbx_modul=dbxShop_admin&dbx_run1=returns';
      $report->_mode = 'table';
      $report->_pages = true;
      $report->_create_sel_flds = true;
      $report->_but_pagination = 7;
      $report->_fld_id = 'id';
      $report->_msg_info = $report->get_fd_message('report_info');
      if ($this->postedFormError !== '') {
         $report->_msg_error = $report->get_fd_message('token_error');
         $this->postedFormError = '';
      }
      $report->add_rep('bar_title', $report->get_fd_message('bar_title'));
      $report->add_rep('bar_icon', 'bi-arrow-counterclockwise');
      $report->add_rep('bar_subtitle', $report->get_fd_message('bar_subtitle'));
      $report->add_rep('bar_class', 'dbx-bar--module');
      $report->add_rep('bar_title_class', 'dbx-bar-title');
      $report->add_rep('bar_actions_class', 'dbx-bar-actions');
      $report->add_rep('bar_title_pre', '');
      $report->add_rep('bar_title_heading_attrs', '');
      $report->add_rep('bar_middle', '');
      $report->add_rep('bar_extra', '');
      $shopService = dbx()->get_include_obj('dbxShopService', 'dbxShop');
      $pages = is_object($shopService) && method_exists($shopService, 'ensureShopLegalPages') ? $shopService->ensureShopLegalPages() : array();
      $withdrawalCid = (int)($pages['withdrawal'] ?? 0);
      $cmsButton = $withdrawalCid > 0
         ? $this->openWinButton('?dbx_modul=dbxContent_admin&dbx_run1=cms&cid=' . $withdrawalCid, $report->get_fd_message('edit_legal_title'), '<i class="bi bi-pencil-square"></i><span class="visually-hidden"> ' . $this->h($report->get_fd_message('edit_cms')) . '</span>', 'btn btn-outline-primary btn-sm me-1', '94%', '92%')
         : '';
      $report->add_rep('bar_actions',
         $cmsButton
         . $this->openWinButton('?dbx_modul=dbxShop&dbx_run1=withdrawal', $report->get_fd_message('view_page_title'), '<i class="bi bi-box-arrow-up-right"></i><span class="visually-hidden"> ' . $this->h($report->get_fd_message('shop_view')) . '</span>', 'btn btn-outline-primary btn-sm me-1', '82%', '86%')
      );
      $report->add_rep('shop_admin_style', $this->shopAdminStyle());
      $report->set_callback_owner($this);
      $report->set_callback('next_record', 'withdrawal_report_next_record');
      $report->create_selection_fields('dbxShop_admin|rpt-withdrawals-selection');

      $query = trim((string)$report->get_fld_val('dbx_rwhere', '', 'parameter|max=120'));
      $rowsPerPage = (int)$report->get_fld_val('dbx_rrows', 30, 'int');
      $rowsPerPage = $rowsPerPage === 0 ? 0 : max(10, min(100, $rowsPerPage));
      $position = $rowsPerPage === 0 ? 0 : max(0, (int)$report->get_fld_val('dbx_rpos', 0, 'int'));
      $filters = array(
         'query' => $query,
         'status' => trim((string)$report->get_fld_val('status', '', 'parameter')),
      );
      $filteredCount = $this->repo()->withdrawalCount($filters);
      $rows = $this->repo()->withdrawals($filters, $rowsPerPage, $position);

      $report->_rflds = array(
         'request_view' => $report->get_fd_message('column_withdrawal'),
         'customer_view' => $report->get_fd_message('column_customer'),
         'message_view' => $report->get_fd_message('column_message'),
         'status_view' => $report->get_fd_message('column_status'),
         'actions_view' => $report->get_fd_message('column_action'),
      );
      $report->_rpt_format = array(
         'request_view' => 'html',
         'customer_view' => 'html',
         'message_view' => 'html',
         'status_view' => 'html',
         'actions_view' => 'html',
      );
      $report->_rrows = $rowsPerPage;
      $report->_rpos = $position;
      $report->_count_all = $this->repo()->withdrawalCount(array());
      $report->_rcount = $filteredCount;
      $report->_rdata = $rows;

      $content = $report->run();
      if ($filteredCount === 0) {
         $content .= '<div class="alert alert-info mx-3">'
            . $this->h($report->get_fd_message('no_results'))
            . '</div>';
      }
      return $content;
   }



   private function orderMetaHtml(array $order, $texts): string {
      $channel = (string)($order['channel_key'] ?? 'shop');
      $source = $this->channelLabel($channel) . ' '
         . (in_array($channel, array('shop', 'web', ''), true)
            ? $texts->get_fd_message('source_order_suffix')
            : $texts->get_fd_message('source_channel_order'));
      $paymentStatuses = $this->paymentStatusOptions($texts);
      $shippingStatuses = $this->shippingStatusOptions($texts);
      $rows = array(
         $texts->get_fd_message('meta_order_no') => $order['order_no'] ?? '',
         $texts->get_fd_message('meta_created') => $order['create_date'] ?? '',
         $texts->get_fd_message('meta_customer') => trim((string)($order['customer_name'] ?? '') . ' <' . (string)($order['customer_email'] ?? '') . '>'),
         $texts->get_fd_message('meta_phone') => $order['customer_phone'] ?? '',
         $texts->get_fd_message('meta_shipping_address') => $order['shipping_address'] ?? '',
         $texts->get_fd_message('meta_source') => $source,
         $texts->get_fd_message('meta_external_order') => $order['payment_reference'] ?? '',
         $texts->get_fd_message('meta_payment_method') => $this->paymentProviderLabel((string)($order['payment_provider'] ?? ''), $texts),
         $texts->get_fd_message('meta_payment_status') => $paymentStatuses[(string)($order['payment_status'] ?? '')] ?? ($order['payment_status'] ?? ''),
         $texts->get_fd_message('meta_invoice_no') => $order['invoice_no'] ?? '',
         $texts->get_fd_message('meta_invoice_date') => $order['invoice_date'] ?? '',
         $texts->get_fd_message('meta_invoice_pdf') => trim((string)($order['invoice_pdf_path'] ?? '')) !== '' ? (string)$order['invoice_pdf_path'] : $texts->get_fd_message('not_generated'),
         $texts->get_fd_message('meta_stock_reserved') => !empty($order['stock_reserved']) ? $texts->get_fd_message('yes') : $texts->get_fd_message('no'),
         $texts->get_fd_message('meta_stock_released') => !empty($order['stock_released']) ? $texts->get_fd_message('yes') . ', ' . (string)($order['stock_released_date'] ?? '') : $texts->get_fd_message('no'),
         $texts->get_fd_message('meta_shipping_status') => $shippingStatuses[(string)($order['shipping_status'] ?? '')] ?? ($order['shipping_status'] ?? ''),
         $texts->get_fd_message('meta_shipping_provider') => $order['shipping_provider'] ?? '',
         $texts->get_fd_message('meta_tracking_no') => $order['tracking_no'] ?? '',
         $texts->get_fd_message('meta_total') => $this->money($order['total_gross'] ?? 0),
      );
      $html = '<dl class="dbx-shop-order-meta">';
      foreach ($rows as $label => $value) {
         $html .= '<dt>' . $this->h($label) . '</dt><dd>' . $this->h($value) . '</dd>';
      }
      $html .= '</dl>';
      return $html;
   }



   private function orderHistoryHtml(array $order, $texts): string {
      $rows = (array)($order['history'] ?? array());
      if ($rows === array()) {
         return '<div class="text-muted small">' . $this->h($texts->get_fd_message('history_empty')) . '</div>';
      }
      $html = '<div class="dbx-shop-order-history">';
      foreach ($rows as $row) {
         $event = (string)($row['event_type'] ?? '');
         $old = (string)($row['old_value'] ?? '');
         $new = (string)($row['new_value'] ?? '');
         $msg = (string)($row['message'] ?? '');
         $html .= '<div class="dbx-shop-order-history-item">'
            . '<strong>' . $this->h($event) . '</strong>'
            . '<small>' . $this->h($row['create_date'] ?? '') . '</small>'
            . ($old !== '' || $new !== '' ? '<code>' . $this->h($old) . ' -> ' . $this->h($new) . '</code>' : '')
            . ($msg !== '' ? '<span>' . $this->h($msg) . '</span>' : '')
            . '</div>';
      }
      $html .= '</div>';
      return $html;
   }



   private function orderWithdrawalsHtml(array $order, $texts): string {
      $rows = (array)($order['withdrawals'] ?? array());
      if ($rows === array()) {
         return '<div class="text-muted small">' . $this->h($texts->get_fd_message('withdrawals_empty')) . '</div>';
      }
      $html = '<div class="dbx-shop-order-withdrawals">';
      foreach ($rows as $row) {
         $html .= '<div class="alert alert-warning py-2 mb-2">'
            . '<strong>' . $this->h($row['customer_name'] ?? '') . '</strong> '
            . '<span class="badge text-bg-warning">' . $this->h($row['status'] ?? '') . '</span>'
            . '<br><small>' . $this->h($row['create_date'] ?? '') . ' · ' . $this->h($row['customer_email'] ?? '') . '</small>'
            . '<div class="mt-1">' . nl2br($this->h($row['reason'] ?? '')) . '</div>'
            . '</div>';
      }
      $html .= '</div>';
      return $html;
   }



   private function orderItemsHtml(array $order, $texts): string {
      $items = (array)($order['items'] ?? array());
      if ($items === array()) {
         return '<div class="text-muted small">' . $this->h($texts->get_fd_message('items_empty')) . '</div>';
      }
      $html = '<div class="table-responsive"><table class="table table-sm table-bordered dbx-shop-order-items-table"><thead><tr>'
         . '<th>' . $this->h($texts->get_fd_message('items_product')) . '</th><th class="text-end">' . $this->h($texts->get_fd_message('items_quantity')) . '</th><th class="text-end">' . $this->h($texts->get_fd_message('items_unit_price')) . '</th><th class="text-end">' . $this->h($texts->get_fd_message('items_tax')) . '</th><th class="text-end">' . $this->h($texts->get_fd_message('items_shipping')) . '</th><th class="text-end">' . $this->h($texts->get_fd_message('items_total')) . '</th>'
         . '</tr></thead><tbody>';
      foreach ($items as $item) {
         $html .= '<tr>'
            . '<td><strong>' . $this->h($item['title'] ?? '') . '</strong><br><code>' . $this->h($item['sku'] ?? '') . '</code></td>'
            . '<td class="text-end">' . (int)($item['qty'] ?? 0) . '</td>'
            . '<td class="text-end">' . $this->money($item['price_gross'] ?? 0) . '</td>'
            . '<td class="text-end">' . $this->h(number_format((float)($item['tax_rate'] ?? 0), 2, ',', '.')) . ' %</td>'
            . '<td class="text-end">' . $this->money($item['shipping_gross'] ?? 0) . '</td>'
            . '<td class="text-end">' . $this->money($item['total_gross'] ?? 0) . '</td>'
            . '</tr>';
      }
      $html .= '</tbody></table></div>';
      return $html;
   }



   private function orderPayloadHtml(array $order, $texts): string {
      $blocks = array(
         $texts->get_fd_message('payload_payment') => trim((string)($order['payment_payload'] ?? '')),
         $texts->get_fd_message('payload_legal') => trim((string)($order['legal_snapshot'] ?? '')),
         $texts->get_fd_message('payload_withdrawal') => trim((string)($order['withdrawal_snapshot'] ?? '')),
      );
      $html = '';
      foreach ($blocks as $title => $payload) {
         if ($payload === '') {
            continue;
         }
         $decoded = json_decode($payload, true);
         if (is_array($decoded)) {
            $payload = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
         }
         $html .= '<details class="mb-2"><summary>' . $this->h($title) . '</summary><pre>' . $this->h($payload) . '</pre></details>';
      }
      if ($html === '') {
         return '<div class="text-muted small">' . $this->h($texts->get_fd_message('payload_empty')) . '</div>';
      }
      return $html;
   }



   private function statusMailChangesHtml(array $before, array $order): string {
      $rows = array(
         'Bestellstatus' => array($this->orderStatusOptions(), 'status'),
         'Zahlungsstatus' => array($this->paymentStatusOptions(), 'payment_status'),
         'Versandstatus' => array($this->shippingStatusOptions(), 'shipping_status'),
      );
      $html = '';
      foreach ($rows as $label => $cfg) {
         [$options, $field] = $cfg;
         $old = (string)($before[$field] ?? '');
         $new = (string)($order[$field] ?? '');
         if ($old === $new) {
            continue;
         }
         $html .= '<tr><th align="left">' . $this->h($label) . '</th><td>' . $this->h($options[$old] ?? $old) . '</td><td>' . $this->h($options[$new] ?? $new) . '</td></tr>';
      }
      if ($html === '') {
         return '<dl>'
            . '<dt>Bestellstatus</dt><dd>' . $this->h($this->orderStatusOptions()[(string)($order['status'] ?? '')] ?? (string)($order['status'] ?? '')) . '</dd>'
            . '<dt>Zahlungsstatus</dt><dd>' . $this->h($this->paymentStatusOptions()[(string)($order['payment_status'] ?? '')] ?? (string)($order['payment_status'] ?? '')) . '</dd>'
            . '<dt>Versandstatus</dt><dd>' . $this->h($this->shippingStatusOptions()[(string)($order['shipping_status'] ?? '')] ?? (string)($order['shipping_status'] ?? '')) . '</dd>'
            . '</dl>';
      }
      return '<table border="0" cellpadding="6" cellspacing="0">'
         . '<thead><tr><th align="left">Feld</th><th align="left">Vorher</th><th align="left">Jetzt</th></tr></thead>'
         . '<tbody>' . $html . '</tbody></table>';
   }



   private function sendOrderStatusMail(array $before, array $order): array {
      $cfg = $this->shopConfig();
      $from = trim((string)($cfg['mail_from'] ?? ''));
      $fromName = trim((string)($cfg['mail_from_name'] ?? 'dbxShop'));
      $profile = trim((string)($cfg['mail_profile'] ?? ''));
      $to = trim((string)($order['customer_email'] ?? ''));
      if (filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
         return array(false, 'Kundenmail wurde nicht gesendet: Der Mail-Absender in den Shop-Einstellungen ist ungültig.');
      }
      if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
         return array(false, 'Kundenmail wurde nicht gesendet: Die Bestellung hat keine gueltige Kunden-E-Mail.');
      }

      $orderNo = (string)($order['order_no'] ?? '');
      $subject = 'Aktualisierung Ihrer Bestellung ' . $orderNo;
      $trackingNo = trim((string)($order['tracking_no'] ?? ''));
      $trackingUrl = trim((string)($order['tracking_url'] ?? ''));
      $invoiceNo = trim((string)($order['invoice_no'] ?? ''));
      $extra = '';
      if ($trackingNo !== '') {
         $extra .= '<p><strong>Trackingnummer:</strong> ' . $this->h($trackingNo) . '</p>';
      }
      if ($trackingUrl !== '') {
         $extra .= '<p><a href="' . $this->h($trackingUrl) . '">Sendung verfolgen</a></p>';
      }
      if ($invoiceNo !== '') {
         $extra .= '<p><strong>Rechnung:</strong> ' . $this->h($invoiceNo) . '</p>';
      }
      $html = '<h2>Ihre Bestellung wurde aktualisiert</h2>'
         . '<p>Bestellnummer: <strong>' . $this->h($orderNo) . '</strong></p>'
         . $this->statusMailChangesHtml($before, $order)
         . $extra
         . '<p>Viele Gruesse<br>Ihr Shop-Team</p>';

      try {
         $options = $profile !== '' ? array('mail_profile' => $profile) : array();
         $sent = dbx()->send_mail(
            array('email' => $from, 'name' => $fromName),
            $to,
            $subject,
            $html,
            'html',
            array(),
            $options
         );
         if (!$sent) {
            $mail = dbx()->get_system_obj('dbxMail');
            $reason = is_object($mail) ? trim((string)$mail->get_error()) : '';
            return array(
               false,
               'Kundenmail konnte nicht gesendet werden'
                  . ($reason !== '' ? ': ' . $reason : '.')
            );
         }
         $this->repo()->addOrderHistory((int)($order['id'] ?? 0), 'customer_mail', '', $to, 'Statusbenachrichtigung wurde an den Kunden gesendet.');
         return array(true, 'Kundenmail wurde gesendet.');
      } catch (\Throwable $e) {
         dbx()->sys_msg('error', 'dbxShop_admin', (string)($order['id'] ?? ''), 'order status mail failed', $e->getMessage());
         return array(false, 'Kundenmail konnte nicht gesendet werden: ' . $e->getMessage());
      }
   }



   private function notifyCustomerHint(array $order, $texts): string {
      $email = trim((string)($order['customer_email'] ?? ''));
      return $email === ''
         ? $texts->get_fd_message('notify_no_email')
         : $texts->format_fd_message(
            'notify_hint',
            array('email' => $email)
         );
   }



   private function orderQuickActionsHtml(array $order, $texts): string {
      $id = (int)($order['id'] ?? 0);
      if ($id <= 0) {
         return '';
      }
      $base = '?dbx_modul=dbxShop_admin&dbx_run1=order_detail&dbx_run2=quick_action&id=' . $id . '&order_action=';
      $actions = array(
         'mark_paid' => array('bi-cash-coin', $texts->get_fd_message('quick_paid'), $texts->get_fd_message('quick_paid_confirm')),
         'processing' => array('bi-tools', $texts->get_fd_message('quick_processing'), $texts->get_fd_message('quick_processing_confirm')),
         'ready' => array('bi-box-seam', $texts->get_fd_message('quick_ready'), $texts->get_fd_message('quick_ready_confirm')),
         'shipped' => array('bi-truck', $texts->get_fd_message('quick_shipped'), $texts->get_fd_message('quick_shipped_confirm')),
         'delivered' => array('bi-check2-circle', $texts->get_fd_message('quick_delivered'), $texts->get_fd_message('quick_delivered_confirm')),
         'cancel' => array('bi-x-circle', $texts->get_fd_message('quick_cancel'), $texts->get_fd_message('quick_cancel_confirm')),
         'refund' => array('bi-arrow-counterclockwise', $texts->get_fd_message('quick_refund'), $texts->get_fd_message('quick_refund_confirm')),
      );
      $html = '<div class="dbx-shop-order-quick-actions" data-dbx="lib=confirm|class=dbxConfirm|bind=link">'
         . '<strong>' . $this->h($texts->get_fd_message('quick_actions')) . '</strong><div class="dbx-shop-order-quick-action-buttons">';
      foreach ($actions as $action => $cfg) {
         [$icon, $label, $confirm] = $cfg;
         $btnClass = in_array($action, array('cancel', 'refund'), true) ? 'btn-outline-danger' : 'btn-outline-primary';
         $html .= '<a class="btn btn-sm ' . $btnClass . ' dbxConfirm" href="' . $this->h($this->actionUrl($base . rawurlencode($action))) . '"'
            . ' data-confirm-title="<i class=\'bi ' . $this->h($icon) . '\'></i> ' . $this->h($label) . '"'
            . ' data-confirm="' . $this->h($confirm) . '"'
            . ' data-confirm-buttons="yesno"'
            . ' title="' . $this->h($label) . '">'
            . '<i class="bi ' . $this->h($icon) . '"></i> ' . $this->h($label) . '</a>';
      }
      $html .= '</div></div>';
      return $html;
   }



   private function orderDetailActions(int $id, $texts): string {
      $helpId = $this->ensureShopOrdersHelpPage();
      $helpButton = $helpId > 0
         ? $this->openWinButton('?dbx_modul=dbxContent&dbx_run1=content&cid=' . $helpId, $texts->get_fd_message('help_orders'), '<i class="bi bi-question-circle"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('help_label')) . '</span>', 'btn btn-outline-secondary btn-sm ms-1', '72%', '82%')
         : '';
      $deleteUrl = $this->actionUrl('?dbx_modul=dbxShop_admin&dbx_run1=orders&delete_order=' . $id);
      $mailUrl = $this->actionUrl('?dbx_modul=dbxShop_admin&dbx_run1=order_detail&dbx_run2=send_status_mail&id=' . $id);
      $invoicePdfUrl = $this->actionUrl('?dbx_modul=dbxShop_admin&dbx_run1=order_invoice_pdf&id=' . $id);
      return '<button class="btn btn-primary btn-sm" type="submit" data-dbx-tooltip="' . $this->h($texts->get_fd_message('save_order_title')) . '"><i class="bi bi-save"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('save_label')) . '</span></button>'
         . $this->openWinButton('?dbx_modul=dbxShop_admin&dbx_run1=order_invoice&id=' . $id, $texts->get_fd_message('invoice_view'), '<i class="bi bi-file-earmark-text"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('invoice_label')) . '</span>', 'btn btn-outline-primary btn-sm ms-1', '82%', '86%')
         . '<a class="btn btn-outline-danger btn-sm ms-1" href="' . $this->h($invoicePdfUrl) . '" target="_blank" rel="noopener" title="' . $this->h($texts->get_fd_message('invoice_pdf_title')) . '"><i class="bi bi-file-earmark-pdf"></i><span class="visually-hidden"> PDF</span></a>'
         . '<a class="btn btn-outline-primary btn-sm ms-1 dbxConfirm" href="' . $this->h($mailUrl) . '" data-confirm-title="<i class=\'bi bi-envelope\'></i> ' . $this->h($texts->get_fd_message('customer_mail_title')) . '" data-confirm="' . $this->h($texts->get_fd_message('customer_mail_question')) . '" data-confirm-buttons="yesno" title="' . $this->h($texts->get_fd_message('customer_mail_title')) . '"><i class="bi bi-envelope"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('customer_mail_label')) . '</span></a>'
         . '<a class="btn btn-outline-danger btn-sm ms-1 dbxConfirm" href="' . $this->h($deleteUrl) . '" data-confirm-title="<i class=\'bi bi-trash\'></i> ' . $this->h($texts->get_fd_message('delete_title')) . '" data-confirm="' . $this->h($texts->get_fd_message('delete_question')) . '" data-confirm-buttons="yesno" title="' . $this->h($texts->get_fd_message('delete_title')) . '"><i class="bi bi-trash"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('delete_label')) . '</span></a>'
         . $helpButton
         . $this->openWinButton('?dbx_modul=dbxShop_admin&dbx_run1=orders', $texts->get_fd_message('order_list_title'), '<i class="bi bi-table"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('order_list_label')) . '</span>', 'btn btn-outline-secondary btn-sm ms-1', '92%', '88%');
   }



   private function orderDetail(): string {
      $id = (int)dbx()->get_modul_var('id', 0, 'int');
      $order = $id > 0 ? $this->repo()->orderById($id) : null;
      if (!is_array($order)) {
         return $this->frame('<div class="alert alert-warning m-3">Bestellung nicht gefunden.</div>', 'Bestellung bearbeiten', $this->orderActions());
      }
      $quickMessage = '';
      $quickError = '';
      $quickAction = (string)dbx()->get_modul_var('order_action', '', 'parameter');
      if ((string)dbx()->get_modul_var('dbx_run2', '', 'parameter') === 'quick_action' && $quickAction !== '') {
         if (!$this->checkActionToken('order_quick_action')) {
            $quickOk = false;
            $quickMsg = $this->postedFormError;
         } else {
            [$quickOk, $quickMsg] = $this->repo()->updateOrderQuickAction($id, $quickAction);
         }
         if ($quickOk) {
            $quickMessage = $quickMsg;
            $order = $this->repo()->orderById($id) ?: $order;
         } else {
            $quickError = $quickMsg;
         }
      }
      $mailMessage = '';
      $mailError = '';
      $sendStatusMail = (string)dbx()->get_modul_var('dbx_run2', '', 'parameter') === 'send_status_mail'
         || (string)($_GET['dbx_run2'] ?? '') === 'send_status_mail';
      if ($sendStatusMail) {
         if (!$this->checkActionToken('send_status_mail')) {
            $mailOk = false;
            $mailMsg = $this->postedFormError;
         } else {
            [$mailOk, $mailMsg] = $this->sendOrderStatusMail($order, $order);
         }
         if ($mailOk) {
            $mailMessage = $mailMsg;
            $order = $this->repo()->orderById($id) ?: $order;
         } else {
            $mailError = $mailMsg;
         }
      }

      $form = dbx()->get_system_obj('dbxForm');
      $form->init('shop-order-form', 'shop-order-form');
      $form->_dd = 'dbxShop|shopOrder';
      $form->_fd = 'dbxShop_admin|rpt-orders-selection';
      $form->load_fd_messages();
      $form->_data = $order;
      $form->_rid = $id;
      $form->_action = '?dbx_modul=dbxShop_admin&dbx_run1=order_detail&id=' . $id;
      $form->set_activ_id($id);
      $form->add_rep('bar_title', $form->get_fd_message('detail_title'));
      $form->add_rep('bar_icon', 'bi-receipt');
      $form->add_rep('bar_subtitle', (string)($order['order_no'] ?? ''));
      $form->add_rep('bar_class', 'dbx-bar--module');
      $form->add_rep('bar_title_class', 'dbx-bar-title');
      $form->add_rep('bar_title_pre', '');
      $form->add_rep('bar_title_heading_attrs', '');
      $form->add_rep('bar_middle', '');
      $form->add_rep('bar_actions_class', 'dbx-bar-actions');
      $form->add_rep('bar_actions', $this->orderDetailActions($id, $form));
      $form->add_rep('bar_extra', '');
      $form->add_rep('shop_admin_style', $this->shopAdminStyle());
      $form->_msg_info = $form->get_fd_message('detail_info');
      $form->add_fld('status', tpl: 'select-single-label', label: $form->get_fd_message('field_order_status'), options: $this->orderStatusOptions($form));
      $form->add_fld('payment_status', tpl: 'select-single-label', label: $form->get_fd_message('field_payment_status'), options: $this->paymentStatusOptions($form));
      $form->add_fld('payment_reference', tpl: 'text-label', label: $form->get_fd_message('field_payment_reference'), rules: '*|max=180', placeholder: 'PAYID-xxxx / Channel-Order-ID');
      $form->add_fld('invoice_no', tpl: 'text-label', label: $form->get_fd_message('field_invoice_no'), rules: '*|max=60', placeholder: 'R2026-00001');
      $form->add_fld('invoice_date', tpl: 'text-label', label: $form->get_fd_message('field_invoice_date'), rules: '*|date', placeholder: date('Y-m-d'));
      $form->add_fld('shipping_status', tpl: 'select-single-label', label: $form->get_fd_message('field_shipping_status'), options: $this->shippingStatusOptions($form));
      $form->add_fld('shipping_provider', tpl: 'text-label', label: $form->get_fd_message('field_shipping_provider'), rules: '*|max=120', placeholder: 'DHL, UPS');
      $form->add_fld('tracking_no', tpl: 'text-label', label: $form->get_fd_message('field_tracking_no'), rules: '*|max=180', placeholder: '00340434123456789012');
      $form->add_fld('tracking_url', tpl: 'text-label', label: $form->get_fd_message('field_tracking_url'), rules: '*|max=255', placeholder: 'https://...');
      $form->add_fld('shipped_date', tpl: 'text-label', label: $form->get_fd_message('field_shipped_date'), rules: '*|datetime', placeholder: date('Y-m-d H:i:s'));
      $form->add_fld('note', tpl: 'textarea-label', label: $form->get_fd_message('field_note'), rules: '*|max=5000', data: 'rows=5', placeholder: $form->get_fd_message('note_placeholder'));
      $form->add_rep('order_notice', '');

      if ($form->submit()) {
         if (!$form->errors()) {
            if ($this->repo()->updateOrderAdmin($id, $_POST)) {
               $form->_msg_success = $form->get_fd_message(
                  'order_save_success'
               );
               $order = $this->repo()->orderById($id) ?: $order;
               $form->_data = $order;
            } else {
               $form->_msg_error = $form->get_fd_message(
                  'order_save_error'
               );
            }
         } else {
            $form->_msg_error = $form->get_fd_message(
               'validation_error'
            );
         }
      }

      $actionMessage = $quickMessage !== '' ? $quickMessage : $mailMessage;
      $actionError = $quickError !== '' ? $quickError : $mailError;
      if ($actionMessage !== '' || $actionError !== '') {
         $form->_form_submit = 1;
         $form->_msg_info = '';
         if ($actionError !== '') {
            $form->_msg_error = $actionError;
            $form->add_fld_error('general', $actionError);
         } else {
            $form->_msg_success = $actionMessage;
         }
      }

      $form->add_rep('notify_customer_hint', $this->h($this->notifyCustomerHint($order, $form)));
      $form->add_rep('order_quick_actions', $this->orderQuickActionsHtml($order, $form));
      $form->add_obj('order_meta', 'obj-value', $this->orderMetaHtml($order, $form));
      $form->add_obj('order_items', 'obj-value', $this->orderItemsHtml($order, $form));
      $form->add_obj('order_payload', 'obj-value', $this->orderPayloadHtml($order, $form));
      $form->add_obj('order_history', 'obj-value', $this->orderHistoryHtml($order, $form));
      $form->add_obj('order_withdrawals', 'obj-value', $this->orderWithdrawalsHtml($order, $form));
      return $form->run();
   }



   private function orderInvoice(): string {
      $id = (int)dbx()->get_modul_var('id', 0, 'int');
      $order = $id > 0 ? $this->repo()->orderById($id) : null;
      if (!is_array($order)) {
         return $this->frame('<div class="alert alert-warning m-3">Bestellung nicht gefunden.</div>', 'Rechnung');
      }
      $invoiceNo = trim((string)($order['invoice_no'] ?? ''));
      if ($invoiceNo === '') {
         $invoiceNo = 'Entwurf';
      }
      $rows = '';
      foreach ((array)($order['items'] ?? array()) as $item) {
         $rows .= $this->tpl()->get_tpl('dbxShop_admin|order-invoice-row', array(
            'title' => $this->h($item['title'] ?? ''),
            'sku' => $this->h($item['sku'] ?? ''),
            'qty' => (string)(int)($item['qty'] ?? 0),
            'price_gross' => $this->money($item['price_gross'] ?? 0),
            'tax_rate' => $this->h(number_format((float)($item['tax_rate'] ?? 0), 2, ',', '.')),
            'total_gross' => $this->money($item['total_gross'] ?? 0),
         ));
      }
      $html = $this->tpl()->get_tpl('dbxShop_admin|order-invoice', array(
         'invoice_no' => $this->h($invoiceNo),
         'invoice_date' => $this->h($order['invoice_date'] ?? date('Y-m-d')),
         'order_no' => $this->h($order['order_no'] ?? ''),
         'customer_name' => $this->h($order['customer_name'] ?? ''),
         'customer_email' => $this->h($order['customer_email'] ?? ''),
         'shipping_address' => nl2br($this->h($order['shipping_address'] ?? '')),
         'rows' => $rows,
         'total_gross' => $this->money($order['total_gross'] ?? 0),
      ));
      return $this->frame($html, 'Rechnung ' . $invoiceNo, '');
   }



   private function orderInvoicePdf(): string {
      $id = (int)dbx()->get_modul_var('id', 0, 'int');
      if (!$this->checkActionToken('order_invoice_pdf')) {
         return $this->frame('<div class="alert alert-danger m-3">' . $this->h($this->postedFormError) . '</div>', 'Rechnung');
      }
      $order = $id > 0 ? $this->repo()->ensureOrderInvoicePdf($id) : null;
      if (!is_array($order)) {
         return $this->frame('<div class="alert alert-warning m-3">Rechnungs-PDF konnte nicht erzeugt werden.</div>', 'Rechnung');
      }
      $file = $this->repo()->invoicePdfAbsolutePath($order);
      if ($file === '') {
         return $this->frame('<div class="alert alert-warning m-3">Rechnungs-PDF ist nicht verfuegbar.</div>', 'Rechnung');
      }
      if (!headers_sent()) {
         header('Content-Type: application/pdf');
         header('Content-Disposition: inline; filename="' . basename($file) . '"');
         header('Content-Length: ' . filesize($file));
      }
      readfile($file);
      exit;
   }



   private function shopAdminCardForm(string $fid, string $dd, array $data, int $id, string $action, string $shopAction, string $saveAction, string $titleHtml, string $subtitle = '', string $cardClass = '') {
      $form = dbx()->get_system_obj('dbxForm');
      $form->init($fid, 'shop-admin-card-form');
      $form->_dd = $dd;
      $form->_fd = array(
         'dbxShop|shopProductGroup' => 'dbxShop|shop-product-group',
         'dbxShop|shopAttributeDefinition' => 'dbxShop|shop-attribute-definition',
         'dbxShop|shopProductAttributeValue' => 'dbxShop|shop-product-attribute-value',
         'dbxShop|shopShippingGroup' => 'dbxShop|shop-shipping-group',
         'dbxShop|shopChannelGroup' => 'dbxShop|shop-channel-group',
      )[$dd] ?? '';
      if ($form->_fd !== '') {
         $form->load_fd_messages();
      }
      $form->set_form_help_enabled(false);
      $form->_data = $data + array('id' => $id);
      $form->_rid = $id;
      $form->_action = $action;
      $form->set_activ_id($id);
      $form->add_rep('form_class', 'dbx-shop-admin-card-dbXForm');
      $form->add_rep('form_attrs', 'data-target="dbxForm_{i}" data-dbx="lib=confirm|class=dbxConfirm|bind=button"');
      $form->add_rep('shop_action', $this->h($shopAction));
      $form->add_rep('save_action', $this->h($saveAction));
      $form->add_rep('record_id', (string)$id);
      $form->add_rep('extra_hidden', '');
      $form->add_rep('card_title', $titleHtml);
      $form->add_rep('card_subtitle', $this->h($subtitle));
      $form->add_rep('card_badges', '');
      $form->add_rep('card_class', $this->h($cardClass));
      $form->add_rep('form_body', '');
      $form->add_rep('delete_button', '');
      $form->_msg_info = '';
      return $form;
   }



   private function shopAdminCardDeleteButton(string $action, string $title, string $message): string {
      return '<button class="btn btn-outline-danger btn-sm dbxConfirm" name="shop_action" value="' . $this->h($action) . '" title="' . $this->h($title) . '" data-confirm-title="' . $this->h($title) . '" data-confirm="' . $this->h($message) . '" data-confirm-buttons="yesno"><i class="bi bi-trash"></i></button>';
   }
}
