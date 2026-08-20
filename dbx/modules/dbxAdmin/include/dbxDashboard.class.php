<?php
namespace dbx\dbxAdmin;

dbx()->get_system_obj('dbxReport', 'use');
dbx()->get_system_obj('dbxForm', 'use');
dbx()->get_system_obj('dbxDD', 'use');

require_once __DIR__ . '/dbxDashboardCoreService.trait.php';
require_once __DIR__ . '/dbxDashboardUpdateStatusService.trait.php';
require_once __DIR__ . '/dbxDashboardPerformanceHistoryService.trait.php';
require_once __DIR__ . '/dbxDashboardPerformanceMaintenanceService.trait.php';
require_once __DIR__ . '/dbxDashboardPerformanceReportService.trait.php';
require_once __DIR__ . '/dbxDashboardHeroSummaryService.trait.php';
require_once __DIR__ . '/dbxDashboardSysMsgSessionService.trait.php';
require_once __DIR__ . '/dbxDashboardContentCacheService.trait.php';
require_once __DIR__ . '/dbxDashboardInventoryService.trait.php';
require_once __DIR__ . '/dbxDashboardWidgetsReportService.trait.php';
require_once __DIR__ . '/dbxDashboardChangeLogService.trait.php';
require_once __DIR__ . '/dbxDashboardUiDefaultsService.trait.php';

/**
 * Erstellt das Administrations-Dashboard aus Status-, Performance- und Systemdaten.
 */
class dbxDashboard extends \dbxObj {

   private const HISTORY_DD = 'dbxAdmin|dbxAdminDashboardMetric';
   private const PERF_REQUEST_DD = 'dbx|dbxPerformanceRequest';
   private const PERF_TIMER_DD = 'dbx|dbxPerformanceTimer';
   private const HISTORY_BUCKET_MINUTES = 15;

   private $metric_cache = array();
   private $history_ready = null;
   private $performance_request_average = null;
   private $performance_timer_averages = null;
   private $performance_module_averages = null;
   private $dashboard_message_key = '';
   private $dashboard_message_error = false;
   private $update_status_cache = null;
   private $ui_defaults_message = '';
   private $ui_defaults_message_error = false;

   use dbxDashboardCoreServiceTrait;
   use dbxDashboardUpdateStatusServiceTrait;
   use dbxDashboardPerformanceHistoryServiceTrait;
   use dbxDashboardPerformanceMaintenanceServiceTrait;
   use dbxDashboardPerformanceReportServiceTrait;
   use dbxDashboardHeroSummaryServiceTrait;
   use dbxDashboardSysMsgSessionServiceTrait;
   use dbxDashboardContentCacheServiceTrait;
   use dbxDashboardInventoryServiceTrait;
   use dbxDashboardWidgetsReportServiceTrait;
   use dbxDashboardChangeLogServiceTrait;
   use dbxDashboardUiDefaultsServiceTrait;

   public function run() {
      $run2 = dbx()->get_modul_var('dbx_run2', '', 'parameter');
      if ($run2 === 'delete_error_log') {
         $this->process_error_log_action();
         $run2 = '';
      }
      if ($run2 === 'performance_compress') {
         return $this->run_performance_maintenance_process('compress');
      }
      if ($run2 === 'performance_clear') {
         return $this->run_performance_maintenance_process('clear');
      }
      if ($run2 === 'ui_defaults_save') {
         $this->process_ui_defaults_action('save');
         $run2 = 'ui_defaults_panel';
      }
      if ($run2 === 'ui_defaults_delete') {
         $this->process_ui_defaults_action('clear');
         $run2 = 'ui_defaults_panel';
      }

      if ($this->is_ajax_request()) {
         if ($run2 === 'cache_flush' || $run2 === 'cache_save' || $run2 === 'sitemap_rebuild') {
            $this->process_content_cache_action();
            $this->respond_dashboard_ajax_html($this->content_cache_panel_body_html());
         }

         if ($run2 === 'performance_save') {
            $this->process_performance_config_action();
            $target = strtolower(trim((string) ($_POST['performance_target'] ?? 'request')));
            $target = $target === 'db' ? 'db' : 'request';
            $this->respond_dashboard_ajax_html($this->performance_toggle_control($target));
         }

         if ($run2 === 'sysmsg_level_save') {
            $this->process_sys_msg_level_action();
            $this->respond_dashboard_ajax_html($this->sys_msg_level_control());
         }

         if ($run2 === 'session_db_save') {
            $this->process_session_db_action();
            $this->respond_dashboard_ajax_html($this->session_panel_control_html());
         }
      }

      if ($run2 === 'performance_save') {
         $this->process_performance_config_action();
      }
      if ($run2 === 'sysmsg_level_save') {
         $this->process_sys_msg_level_action();
      }
      if ($run2 === 'session_db_save') {
         $this->process_session_db_action();
      }
      if ($run2 === 'cache_flush' || $run2 === 'cache_save' || $run2 === 'sitemap_rebuild') {
         $this->process_content_cache_action();
      }

      if ($run2 !== '') {
         $area_content = $this->dashboard_area($run2);
         if ($area_content !== null) {
            return $area_content;
         }
      }

      $o_form = new \dbxForm();

      $o_form->init('admin-dashboard', 'admin-dashboard');
      $o_form->set_field_definition('dbxAdmin|admin-dashboard-status');
      $o_form->load_fd_messages();
      $o_form->set_action('?dbx_modul=dbxAdmin&dbx_run1=run');
      $o_form->_msg_info = '';

      if ($this->dashboard_message_key !== '') {
         $message = $o_form->get_fd_message($this->dashboard_message_key);
         if ($this->dashboard_message_error) {
            $o_form->_msg_error = $message;
         } else {
            $o_form->_msg_success = $message;
         }
      }

      $message_html = $this->default_admin_password_warning();
      if ($this->dashboard_message_key !== '') {
         $message = dbx()->esc($o_form->get_fd_message($this->dashboard_message_key));
         $tone = $this->dashboard_message_error ? 'danger' : 'success';
         $icon = $this->dashboard_message_error
            ? 'bi-exclamation-triangle-fill'
            : 'bi-check-circle-fill';
         $message_html .= '<div class="alert alert-' . $tone
            . ' d-flex align-items-center gap-2 mb-3" role="alert">'
            . '<i class="bi ' . $icon . '" aria-hidden="true"></i>'
            . '<span>' . $message . '</span></div>';
      }
      $o_form->add_obj('dashboard_message', 'obj-value', $message_html);

      $update_state = $this->update_state($this->update_status());
      $o_form->add_rep('update_nav_class', dbx()->esc($update_state['class']));
      $o_form->add_rep(
         'update_nav_badge',
         dbx()->esc($o_form->get_fd_message($update_state['nav_message']))
      );
      $o_form->add_rep('update_nav_label', dbx()->esc($o_form->get_fd_message('update_nav_title')));

      $metrics = $this->metrics();
      $this->store_history_snapshot($metrics);

      $content = $o_form->run();

      return $content;
   }
}
