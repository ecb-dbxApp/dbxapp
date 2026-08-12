<?php
namespace dbx\dbxAdmin;

trait dbxDashboardCoreServiceTrait {

   /**
    * Warnt, solange der verbindliche Installationszugang admin/123456 aktiv ist.
    */
   private function default_admin_password_warning(): string {
      try {
         $db = dbx()->get_system_obj('dbxDB');
         if (!is_object($db)) {
            return '';
         }
         $admin = $db->select1(
            'dbx|dbxUser',
            array('uname' => 'admin'),
            array('id', 'pass'),
            0
         );
      } catch (\Throwable $exception) {
         return '';
      }

      return $this->default_admin_password_warning_html(
         (int)($admin['id'] ?? 0),
         (string)($admin['pass'] ?? '')
      );
   }

   private function default_admin_password_warning_html(
      int $adminId,
      string $hash
   ): string {
      if ($adminId <= 0 || $hash === '' || !password_verify('123456', $hash)) {
         return '';
      }

      $url = '?dbx_modul=dbxAdmin&amp;dbx_run1=user'
         . '&amp;dbx_run2=edit_user&amp;rid=' . $adminId
         . '&amp;dbx_page=admin';
      return '<div class="alert alert-warning dbx-admin-dashboard-password-warning" role="alert">'
         . '<div class="dbx-admin-dashboard-password-warning-icon">'
         . '<i class="bi bi-shield-exclamation" aria-hidden="true"></i></div>'
         . '<div class="dbx-admin-dashboard-password-warning-copy">'
         . '<strong>Unsicheres Installationspasswort aktiv</strong>'
         . '<span>Der Administrator verwendet noch den öffentlichen Standardzugang '
         . '<code>admin / 123456</code>. Ändern Sie das Passwort jetzt.</span></div>'
         . '<a class="btn btn-warning fw-semibold" href="' . $url . '">'
         . '<i class="bi bi-key me-1" aria-hidden="true"></i>Passwort ändern</a>'
         . '</div>';
   }

   private function fmt($value) {
      $value = (int) $value;
      return number_format($value, 0, ',', '.');
   }

   private function percent($value, $max) {
      $value = (float) $value;
      $max   = (float) $max;

      if ($max <= 0) {
         return 0;
      }

      return max(0, min(100, (int) round(($value / $max) * 100)));
   }

   private function health_reason_label(int $inventoryCount, int $existingCount, int $sysmsgRisk, int $missing): string {
      $reasons = array();

      if ($missing > 0) {
         $reasons[] = 'Missing';
      }

      if ($sysmsgRisk > 0) {
         $reasons[] = 'SysMsg';
      }

      if ($existingCount < $inventoryCount) {
         $reasons[] = 'DB';
      }

      return count($reasons) ? implode('/', $reasons) : 'OK';
   }

   /**
    * Verarbeitet ausschließlich die feste zentrale Fehlerprotokoll-Datei.
    *
    * Die aufrufende URL wird mit dbx()->action_url() erzeugt. Dadurch greift
    * die zentrale Token-Automatik für die Delete-Aktion mit gebundener RID.
    */
   private function process_error_log_action(): void {
      $work = dbx()->get_modul_var('dbx_do', '', 'parameter');
      if ($work !== 'delete_error_log') {
         return;
      }

      $sysMsg = dbx()->get_include_obj('dbxSysMsg');
      $result = $sysMsg->delete_error_log();

      if ($result === 'deleted') {
         $this->dashboardMessageKey = 'error_log_deleted';
      } elseif ($result === 'empty') {
         $this->dashboardMessageKey = 'error_log_empty';
      } else {
         $this->dashboardMessageKey = 'error_log_delete_error';
         $this->dashboardMessageError = true;
      }

      $this->metricCache = array();
   }

   /**
    * Rendert das vorhandene Fehlerprotokoll als sicher maskierten Scrollbereich.
    *
    * Logzeilen können Request-Inhalte und damit HTML enthalten. Das Escaping
    * ist hier zwingend, damit das Admin-Protokoll niemals Markup ausführt.
    */
   private function error_log_panel(\dbxForm $texts): string {
      $sysMsg = dbx()->get_include_obj('dbxSysMsg');
      if (!$sysMsg->error_log_exists()) {
         return '';
      }

      $file = $sysMsg->get_error_log_file();
      $content = @file_get_contents($file);
      if ($content === false) {
         $content = $texts->get_fd_message('error_log_read_error');
      }

      clearstatcache(true, $file);
      $size = @filesize($file);
      $size = $size === false ? strlen($content) : (int)$size;

      $action = dbx()->action_url(
         '?dbx_modul=dbxAdmin&dbx_run1=dashboard&dbx_run2=delete_error_log'
         . '&dbx_do=delete_error_log&rid=error_log'
      );

      return dbx()->get_system_obj('dbxTPL')->get_tpl(
         'dbxAdmin|admin-dashboard-error-log',
         array(
            'error_log_title'    => dbx()->esc($texts->get_fd_message('error_log_title')),
            'error_log_subtitle' => dbx()->esc($texts->get_fd_message('error_log_subtitle')),
            'file_label'         => dbx()->esc($texts->get_fd_message('error_log_file_label')),
            'file'               => 'files/dbxError.log',
            'size'               => $this->fmt($size),
            'bytes_label'        => dbx()->esc($texts->get_fd_message('error_log_bytes')),
            'content'            => htmlspecialchars(
               $content,
               ENT_QUOTES | ENT_SUBSTITUTE,
               'UTF-8'
            ),
            'delete_action'      => dbx()->esc($action),
            'delete_label'       => dbx()->esc($texts->get_fd_message('error_log_delete_label')),
            'delete_title'       => dbx()->esc($texts->get_fd_message('error_log_delete_title')),
            'delete_confirm'     => dbx()->esc($texts->get_fd_message('error_log_delete_confirm')),
            'delete_hint'        => dbx()->esc($texts->get_fd_message('error_log_delete_hint')),
         )
      );
   }

   private function request_runtime_ms() {
      $runtime = dbx()->get_system_obj('dbxRuntime');
      return max(0, (int)round($runtime->current_php_runtime() * 1000));
   }

   private function memory_peak_kb() {
      $bytes = (int)dbx()->get_system_obj('dbxRuntime')->current_memory_bytes();

      if ($bytes <= 0) {
         global $dbx_run_timer;
         $startMemory = isset($dbx_run_timer['system']['start_memory']) && is_numeric($dbx_run_timer['system']['start_memory'])
            ? (int) $dbx_run_timer['system']['start_memory']
            : 0;

         if ($startMemory > 0) {
            $bytes = max(0, (int) memory_get_peak_usage() - $startMemory);
         }
      }

      if ($bytes <= 0) {
         $bytes = (int) memory_get_usage();
      }

      return max(1, (int) ceil($bytes / 1024));
   }

   private function card_action($href, $label) {
      $tpl = dbx()->get_system_obj('dbxTPL');

      return $tpl->get_tpl('dbxAdmin|admin-dashboard-card-action', array(
         'href'  => $href,
         'label' => $label,
      ));
   }

   private function collapse_action($target, $label = 'Aufklappen', $expanded = false) {
      $tpl = dbx()->get_system_obj('dbxTPL');

      return $tpl->get_tpl('dbxAdmin|admin-dashboard-collapse-action', array(
         'target'   => $target,
         'label'    => $label,
         'expanded' => $expanded ? 'true' : 'false',
      ));
   }

   private function help_action(string $topic): string {
      try {
         $help = dbx()->get_include_obj('dbxAdminHelp', 'dbxAdmin');
         return (string) $help->button($topic);
      } catch (\Throwable $e) {
         return '';
      }
   }

   private function card_bar_data($title, $icon, $subtitle = '', $action = '') {
      return array(
         'bar_class'         => 'dbx-bar--module',
         'bar_title_class'   => 'dbx-bar-title',
         'bar_actions_class' => 'dbx-bar-actions',
         'bar_title'         => $title,
         'bar_icon'          => $icon,
         'bar_subtitle'      => $subtitle,
         'bar_title_pre'     => '',
         'bar_title_heading_attrs' => '',
         'bar_actions'       => $action,
         'bar_extra'         => '',
         'title'             => $title,
         'icon'              => $icon,
         'subtitle'          => $subtitle,
         'action'            => $action,
      );
   }

   private function card_bar($title, $icon, $subtitle = '', $action = '') {
      $tpl = dbx()->get_system_obj('dbxTPL');

      return $tpl->get_tpl('dbx|component-bar', $this->card_bar_data($title, $icon, $subtitle, $action));
   }

   private function safe_count($dd, $where = '') {
      $count = 0;

      try {
         $db = dbx()->get_system_obj('dbxDB');
         $res = $db->count($dd, $where);
         if (is_numeric($res) && (int) $res > 0) {
            $count = (int) $res;
         }
      } catch (\Throwable $e) {
         $count = 0;
      }

      return $count;
   }

   private function safe_select($dd, $where = '', $columns = '*', $orderby = '', $asc_desc = 'ASC', $groupby = '', $max = 0, $offset = 0) {
      $rows = array();

      try {
         $db = dbx()->get_system_obj('dbxDB');
         $res = $db->select($dd, $where, $columns, $orderby, $asc_desc, $groupby, $max, $offset, 0);
         if (is_array($res)) {
            $rows = $res;
         }
      } catch (\Throwable $e) {
         $rows = array();
      }

      return $rows;
   }

   private function fmt_ms($value) {
      $value = max(0, (int) $value);
      return number_format($value / 1000, 3, ',', '.') . ' Sec';
   }

   private function fmt_ms_precision($value, $precision = 3, $minSeconds = 0) {
      $value = max(0, (float) $value);
      $precision = max(0, min(6, (int) $precision));
      $seconds = $value / 1000;

      if ((float) $minSeconds > 0 && $seconds < (float) $minSeconds) {
         $seconds = (float) $minSeconds;
      }

      return number_format($seconds, $precision, ',', '.') . ' Sec';
   }

   private function dbx_config_bool(string $key, $default = 0): bool {
      $value = dbx()->get_cfg('dbx', $key);
      if ($value === 'undef' || $value === '' || $value === null) {
         $value = $default;
      }

      return (int) $value === 1;
   }

   private function is_ajax_request(): bool {
      return (int) dbx()->get_system_var('dbx_ajax', 0, 'int') === 1;
   }

   private function respond_dashboard_ajax_html(string $html): void {
      if (!headers_sent()) {
         header('Content-Type: text/html; charset=utf-8');
      }

      echo $html;

      $oSession = dbx()->get_system_obj('dbxSession');
      if (is_object($oSession) && method_exists($oSession, 'save_session')) {
         $oSession->save_session();
      }

      exit;
   }

   private function dashboard_area($area) {
      $metrics = $this->metrics();

      switch ((string) $area) {
         case 'hero':
            return $this->hero_panel($metrics);
         case 'widgets':
            return $this->widgets_panel($metrics);
         case 'quick_actions':
            return $this->quick_actions();
         case 'performance_panel':
            return $this->performance_panel($metrics);
         case 'sysmsg_panel':
            return $this->sysmsg_panel();
         case 'session_panel':
            return $this->session_panel();
         case 'content_cache_panel':
            return $this->content_cache_panel();
         case 'chart_panel':
            return $this->chart_panel($metrics);
         case 'activity_report':
            return $this->activity_report($metrics);
         case 'database_report':
            return $this->database_report($metrics);
      }

      return null;
   }
}
