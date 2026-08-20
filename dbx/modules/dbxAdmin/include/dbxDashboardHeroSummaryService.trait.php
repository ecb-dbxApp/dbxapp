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
      $contact_dd = 'dbxContact|contactRequest';
      $contact_total = $this->safe_count($contact_dd);
      $contact_rows = array(
         array('label' => 'Offen', 'count' => $this->safe_count($contact_dd, array('status' => 'open')), 'tone' => 'blue'),
         array('label' => 'In Arbeit', 'count' => $this->safe_count($contact_dd, array('status' => 'in_progress')), 'tone' => 'cyan'),
         array('label' => 'Rueckfrage', 'count' => $this->safe_count($contact_dd, array('status' => 'waiting_customer')), 'tone' => 'amber'),
         array('label' => 'Beantwortet', 'count' => $this->safe_count($contact_dd, array('status' => 'answered')), 'tone' => 'green'),
         array('label' => 'Geschlossen', 'count' => $this->safe_count($contact_dd, array('status' => 'closed')), 'tone' => 'slate'),
      );

      $sysmsg_dd = 'dbxSysMsg';
      $sysmsg_total = $this->safe_count($sysmsg_dd);
      $sysmsg_rows = array(
         array('label' => 'Info', 'count' => $this->safe_count($sysmsg_dd, "LOWER(status) = 'info'"), 'tone' => 'blue'),
         array('label' => 'Warning', 'count' => $this->safe_count($sysmsg_dd, "LOWER(status) = 'warning'"), 'tone' => 'amber'),
         array('label' => 'Error', 'count' => $this->safe_count($sysmsg_dd, "LOWER(status) = 'error'"), 'tone' => 'red'),
         array('label' => 'Security', 'count' => $this->safe_count($sysmsg_dd, "LOWER(status) = 'security'"), 'tone' => 'purple'),
      );

      return $this->hero_summary_card('Kontakte', 'Alle Anfragen nach Status', 'bi-life-preserver', 'contact', $contact_total, $contact_rows)
         . $this->hero_summary_card('SysMsg', 'Meldungen nach Status', 'bi-bell', 'sysmsg', $sysmsg_total, $sysmsg_rows);
   }

   private function hero_panel($metrics) {
      $hero_performance = $this->hero_performance($metrics);
      $o_form = new \dbxForm();
      $o_form->init('admin-dashboard-hero', 'admin-dashboard-hero');
      $o_form->set_field_definition('dbxAdmin|admin-dashboard-status');
      $o_form->load_fd_messages();
      $o_form->_msg_info = '';
      $o_form->add_rep('bar_class', 'dbx-admin-dashboard-hero-bar');
      $o_form->add_rep('bar_title', $o_form->get_fd_message('bar_title'));
      $o_form->add_rep('bar_subtitle', $o_form->get_fd_message('bar_subtitle'));
      $o_form->add_obj(
         'bar_actions',
         'obj-value',
         '<small class="dbx-bar-meta">'
            . dbx()->esc($o_form->get_fd_message('status_timestamp'))
            . ' '
            . dbx()->esc(date('d.m.Y H:i'))
            . '</small>'
      );
      $o_form->add_rep('health_percent', (int) $metrics['health_percent']);
      $has_error_log = !empty($metrics['health_error_log']);
      $health_reason = $has_error_log
         ? $o_form->get_fd_message('health_error')
         : (string)($metrics['health_reason'] ?? $o_form->get_fd_message('health_ok'));
      if ($health_reason === 'OK') {
         $health_reason = $o_form->get_fd_message('health_ok');
      }
      $o_form->add_rep('health_reason', dbx()->esc($health_reason));
      $o_form->add_rep('health_state_class', $has_error_log ? 'is-error' : 'is-ok');
      $o_form->add_rep('health_icon', $has_error_log ? 'bi-exclamation-octagon-fill' : 'bi-shield-check');
      $o_form->add_rep('performance_aria', $o_form->get_fd_message('performance_aria'));
      $o_form->add_rep('system_status_label', $o_form->get_fd_message('system_status_label'));
      $o_form->add_obj('hero_performance_gauges', 'obj-value', $this->hero_performance_gauges($hero_performance));
      $o_form->add_obj('hero_status_summaries', 'obj-value', $this->hero_status_summaries());
      $o_form->add_obj('update_status', 'obj-value', $this->update_status_panel($o_form));
      $o_form->add_obj('error_log', 'obj-value', $has_error_log ? $this->error_log_panel($o_form) : '');

      return $o_form->run();
   }
}
