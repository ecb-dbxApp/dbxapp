<?php
namespace dbx\dbxAdmin;

dbx()->use_system_class('dbxReport');
dbx()->use_system_class('dbxForm');
dbx()->use_system_class('dbxDD');

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

class dbxDashboard extends \dbxObj {

   private const HISTORY_DD = 'dbxAdmin|dbxAdminDashboardMetric';
   private const PERF_REQUEST_DD = 'dbx|dbxPerformanceRequest';
   private const PERF_TIMER_DD = 'dbx|dbxPerformanceTimer';
   private const HISTORY_BUCKET_MINUTES = 15;

   private $metricCache = array();
   private $historyReady = null;
   private $performanceRequestAverage = null;
   private $performanceTimerAverages = null;
   private $performanceModuleAverages = null;
   private $dashboardMessageKey = '';
   private $dashboardMessageError = false;
   private $updateStatusCache = null;

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
         $areaContent = $this->dashboard_area($run2);
         if ($areaContent !== null) {
            return $areaContent;
         }
      }

      $oForm = new \dbxForm();

      $oForm->init('admin-dashboard', 'admin-dashboard');
      $oForm->_fd = 'dbxAdmin|admin-dashboard-status';
      $oForm->load_fd_messages();
      $oForm->_action = '?dbx_modul=dbxAdmin&dbx_run1=run';
      $oForm->_msg_info = '';

      if ($this->dashboardMessageKey !== '') {
         $message = $oForm->get_fd_message($this->dashboardMessageKey);
         if ($this->dashboardMessageError) {
            $oForm->_msg_error = $message;
         } else {
            $oForm->_msg_success = $message;
         }
      }

      $messageHtml = $this->default_admin_password_warning();
      if ($this->dashboardMessageKey !== '') {
         $message = dbx()->esc($oForm->get_fd_message($this->dashboardMessageKey));
         $tone = $this->dashboardMessageError ? 'danger' : 'success';
         $icon = $this->dashboardMessageError
            ? 'bi-exclamation-triangle-fill'
            : 'bi-check-circle-fill';
         $messageHtml .= '<div class="alert alert-' . $tone
            . ' d-flex align-items-center gap-2 mb-3" role="alert">'
            . '<i class="bi ' . $icon . '" aria-hidden="true"></i>'
            . '<span>' . $message . '</span></div>';
      }
      $oForm->add_obj('dashboard_message', 'obj-value', $messageHtml);

      $updateState = $this->update_state($this->update_status());
      $oForm->add_rep('update_nav_class', dbx()->esc($updateState['class']));
      $oForm->add_rep(
         'update_nav_badge',
         dbx()->esc($oForm->get_fd_message($updateState['nav_message']))
      );
      $oForm->add_rep('update_nav_label', dbx()->esc($oForm->get_fd_message('update_nav_title')));

      $metrics = $this->metrics();
      $this->store_history_snapshot($metrics);

      $content = $oForm->run();

      return $content;
   }
}
