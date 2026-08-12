<?php
namespace dbx\dbxAdmin;

trait dbxDashboardHeroSummaryServiceTrait {

   private function hero_summary_rows(array $items): string {
      $html = '';
      $max = 1;
      foreach ($items as $item) {
         $max = max($max, (int) ($item['count'] ?? 0));
      }

      foreach ($items as $item) {
         $count = (int) ($item['count'] ?? 0);
         $pct = max(3, min(100, (int) round(($count / $max) * 100)));
         $tone = dbx()->esc((string) ($item['tone'] ?? 'blue'));
         $html .= '<div class="dbx-admin-dashboard-hero-summary-row dbx-admin-dashboard-hero-summary-row-' . $tone . '">'
            . '<span>' . dbx()->esc((string) ($item['label'] ?? '')) . '</span>'
            . '<div><em style="width:' . $pct . '%"></em></div>'
            . '<strong>' . dbx()->esc($this->fmt($count)) . '</strong>'
            . '</div>';
      }

      return $html;
   }

   private function hero_summary_card(string $title, string $subtitle, string $icon, string $tone, int $total, array $rows): string {
      $tpl = dbx()->get_system_obj('dbxTPL');

      return $tpl->get_tpl('dbxAdmin|admin-dashboard-hero-summary', array(
         'title'    => $title,
         'subtitle' => $subtitle,
         'icon'     => $icon,
         'tone'     => $tone,
         'total'    => $this->fmt($total),
         'rows'     => $this->hero_summary_rows($rows),
      ));
   }

   private function hero_status_summaries(): string {
      $contactDd = 'dbxContact|contactRequest';
      $contactTotal = $this->safe_count($contactDd);
      $contactRows = array(
         array('label' => 'Offen', 'count' => $this->safe_count($contactDd, array('status' => 'open')), 'tone' => 'blue'),
         array('label' => 'In Arbeit', 'count' => $this->safe_count($contactDd, array('status' => 'in_progress')), 'tone' => 'cyan'),
         array('label' => 'Rueckfrage', 'count' => $this->safe_count($contactDd, array('status' => 'waiting_customer')), 'tone' => 'amber'),
         array('label' => 'Beantwortet', 'count' => $this->safe_count($contactDd, array('status' => 'answered')), 'tone' => 'green'),
         array('label' => 'Geschlossen', 'count' => $this->safe_count($contactDd, array('status' => 'closed')), 'tone' => 'slate'),
      );

      $sysmsgDd = 'dbxSysMsg';
      $sysmsgTotal = $this->safe_count($sysmsgDd);
      $sysmsgRows = array(
         array('label' => 'Info', 'count' => $this->safe_count($sysmsgDd, "LOWER(status) = 'info'"), 'tone' => 'blue'),
         array('label' => 'Warning', 'count' => $this->safe_count($sysmsgDd, "LOWER(status) = 'warning'"), 'tone' => 'amber'),
         array('label' => 'Error', 'count' => $this->safe_count($sysmsgDd, "LOWER(status) = 'error'"), 'tone' => 'red'),
         array('label' => 'Security', 'count' => $this->safe_count($sysmsgDd, "LOWER(status) = 'security'"), 'tone' => 'purple'),
      );

      return $this->hero_summary_card('Kontakte', 'Alle Anfragen nach Status', 'bi-life-preserver', 'contact', $contactTotal, $contactRows)
         . $this->hero_summary_card('SysMsg', 'Meldungen nach Status', 'bi-bell', 'sysmsg', $sysmsgTotal, $sysmsgRows);
   }

   private function hero_panel($metrics) {
      $heroPerformance = $this->hero_performance($metrics);
      $oForm = new \dbxForm();
      $oForm->init('admin-dashboard-hero', 'admin-dashboard-hero');
      $oForm->_fd = 'dbxAdmin|admin-dashboard-status';
      $oForm->load_fd_messages();
      $oForm->_msg_info = '';
      $oForm->add_rep('bar_class', 'dbx-admin-dashboard-hero-bar');
      $oForm->add_rep('bar_title', $oForm->get_fd_message('bar_title'));
      $oForm->add_rep('bar_subtitle', $oForm->get_fd_message('bar_subtitle'));
      $oForm->add_obj(
         'bar_actions',
         'obj-value',
         '<small class="dbx-bar-meta">'
            . dbx()->esc($oForm->get_fd_message('status_timestamp'))
            . ' '
            . dbx()->esc(date('d.m.Y H:i'))
            . '</small>'
      );
      $oForm->add_rep('health_percent', (int) $metrics['health_percent']);
      $hasErrorLog = !empty($metrics['health_error_log']);
      $healthReason = $hasErrorLog
         ? $oForm->get_fd_message('health_error')
         : (string)($metrics['health_reason'] ?? $oForm->get_fd_message('health_ok'));
      if ($healthReason === 'OK') {
         $healthReason = $oForm->get_fd_message('health_ok');
      }
      $oForm->add_rep('health_reason', dbx()->esc($healthReason));
      $oForm->add_rep('health_state_class', $hasErrorLog ? 'is-error' : 'is-ok');
      $oForm->add_rep('health_icon', $hasErrorLog ? 'bi-exclamation-octagon-fill' : 'bi-shield-check');
      $oForm->add_rep('performance_aria', $oForm->get_fd_message('performance_aria'));
      $oForm->add_rep('system_status_label', $oForm->get_fd_message('system_status_label'));
      $oForm->add_obj('hero_performance_gauges', 'obj-value', $this->hero_performance_gauges($heroPerformance));
      $oForm->add_obj('hero_status_summaries', 'obj-value', $this->hero_status_summaries());
      $oForm->add_obj('update_status', 'obj-value', $this->update_status_panel($oForm));
      $oForm->add_obj('error_log', 'obj-value', $hasErrorLog ? $this->error_log_panel($oForm) : '');

      return $oForm->run();
   }
}
