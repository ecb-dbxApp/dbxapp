<?php
namespace dbx\dbxAdmin;

require_once __DIR__ . '/dbxSystemMessageConfig.class.php';

trait dbxDashboardSysMsgSessionServiceTrait {

   private function sys_msg_level_options(string $current): string {
      $options = array(
         'error'   => 'Nur Error',
         'warning' => 'Error + Warning',
         'all'     => 'Alles',
      );
      $html = '';

      foreach ($options as $value => $label) {
         $selected = $value === $current ? ' selected' : '';
         $html .= '<option value="' . dbx()->esc($value) . '"' . $selected . '>' . dbx()->esc($label) . '</option>';
      }

      return $html;
   }

   private function process_sys_msg_level_action(): bool {
      $run2 = dbx()->get_modul_var('dbx_run2', '', 'parameter');
      if ($run2 !== 'sysmsg_level_save') {
         return false;
      }

      $level = dbx()->get_request_var('sys_msg_level', null, 'parameter');
      if ($level === null || $level === '') {
         $level = $_POST['sys_msg_level'] ?? 'all';
      }

      return dbxSystemMessageConfig::save((string)$level);
   }

   private function sys_msg_level_control_data(): array {
      $level = dbxSystemMessageConfig::current();
      $states = array(
         'all' => array(
            'tone'  => 'on',
            'icon'  => 'bi-bell-fill',
            'label' => 'Systemmeldungen: Alles',
            'hint'  => 'Alle Systemmeldungen werden gespeichert.',
         ),
         'warning' => array(
            'tone'  => 'on',
            'icon'  => 'bi-exclamation-triangle-fill',
            'label' => 'Error + Warning',
            'hint'  => 'Nur Error und Warning werden gespeichert.',
         ),
         'error' => array(
            'tone'  => 'off',
            'icon'  => 'bi-exclamation-octagon-fill',
            'label' => 'Nur Error',
            'hint'  => 'Nur Fehlermeldungen werden gespeichert.',
         ),
      );
      $state = $states[$level] ?? $states['all'];

      return array(
         'sys_msg_level_save_base' => dbx()->esc('?dbx_modul=dbxAdmin&dbx_run1=run&dbx_run2=sysmsg_level_save'),
         'sys_msg_level_options'   => $this->sys_msg_level_options($level),
         'sysmsg_status_tone'      => dbx()->esc($state['tone']),
         'sysmsg_status_icon'      => dbx()->esc($state['icon']),
         'sysmsg_status_label'     => dbx()->esc($state['label']),
         'sysmsg_status_hint'      => dbx()->esc($state['hint']),
      );
   }

   private function sys_msg_level_control(): string {
      $o_form = new \dbxForm();
      $o_form->init('admin-dashboard-sysmsg-control', 'admin-dashboard-sysmsg-control');
      $o_form->set_form_help_enabled(false);
      $o_form->set_action('?dbx_modul=dbxAdmin&dbx_run1=run&dbx_run2=sysmsg_level_save');
      $o_form->_msg_info = '';

      foreach ($this->sys_msg_level_control_data() as $key => $value) {
         $o_form->add_rep($key, $value);
      }

      return $o_form->add_norep($o_form->run());
   }

   private function sysmsg_panel_body_html(): string {
      $o_form = new \dbxForm();
      $o_form->init('admin-dashboard-sysmsg-body', 'admin-dashboard-sysmsg-body');
      $o_form->set_form_help_enabled(false);
      $o_form->set_action('?dbx_modul=dbxAdmin&dbx_run1=run');
      $o_form->_msg_info = '';
      $o_form->add_rep('sysmsg_control', $this->sys_msg_level_control());

      return $o_form->add_norep($o_form->run());
   }

   private function sysmsg_panel() {
      $o_form = new \dbxForm();
      $o_form->init('admin-dashboard-sysmsg-panel', 'admin-dashboard-sysmsg-panel');
      $o_form->set_form_help_enabled(false);
      $o_form->set_action('?dbx_modul=dbxAdmin&dbx_run1=run');
      $o_form->_msg_info = '';
      $o_form->add_rep('sysmsg_body', $this->sysmsg_panel_body_html());

      return $o_form->add_norep($o_form->run());
   }

   private function session_db_enabled_config(): bool {
      return $this->dbx_config_bool('session_db', 1);
   }

   private function set_session_db_config(bool $enabled): bool {
      $config = dbx()->get_cfg('dbx');
      if (!is_array($config)) {
         $config = array();
      }

      $config['session_db'] = $enabled ? 1 : 0;
      return (int) dbx()->set_cfg('dbx', $config) > 0;
   }

   private function process_session_db_action(): bool {
      $run2 = dbx()->get_modul_var('dbx_run2', '', 'parameter');
      if ($run2 !== 'session_db_save') {
         return false;
      }

      return $this->set_session_db_config(isset($_POST['session_db']));
   }

   private function session_panel_body_data(): array {
      $enabled = $this->session_db_enabled_config();

      return array(
         'session_save_url' => dbx()->esc('?dbx_modul=dbxAdmin&dbx_run1=run&dbx_run2=session_db_save'),
         'session_enabled_checked' => $enabled ? 'checked' : '',
         'session_status_tone' => $enabled ? 'on' : 'off',
         'session_status_icon' => $enabled ? 'bi-check-circle-fill' : 'bi-pause-circle-fill',
         'session_status_label' => dbx()->esc($enabled ? 'Session-DB aktiv' : 'Session-DB inaktiv'),
         'session_status_hint' => dbx()->esc(
            $enabled
               ? 'Normale HTTP-Requests und HTML-AJAX-Requests schreiben ihre Session am Request-Ende in die DB.'
               : 'Sessions laufen nur ueber PHP-Session; die Session-Liste wird nicht fortgeschrieben.'
         ),
      );
   }

   private function session_panel_control_html(): string {
      $o_form = new \dbxForm();
      $o_form->init('admin-dashboard-session-control', 'admin-dashboard-session-control');
      $o_form->set_form_help_enabled(false);
      $o_form->set_action('?dbx_modul=dbxAdmin&dbx_run1=run');
      $o_form->_msg_info = '';

      foreach ($this->session_panel_body_data() as $key => $value) {
         $o_form->add_rep($key, $value);
      }

      return $o_form->add_norep($o_form->run());
   }

   private function session_panel_body_html(): string {
      $o_form = new \dbxForm();
      $o_form->init('admin-dashboard-session-body', 'admin-dashboard-session-body');
      $o_form->set_form_help_enabled(false);
      $o_form->set_action('?dbx_modul=dbxAdmin&dbx_run1=run');
      $o_form->_msg_info = '';
      $o_form->add_rep('session_control', $this->session_panel_control_html());

      return $o_form->add_norep($o_form->run());
   }

   private function session_panel() {
      $o_form = new \dbxForm();
      $o_form->init('admin-dashboard-session-panel', 'admin-dashboard-session-panel');
      $o_form->set_form_help_enabled(false);
      $o_form->set_action('?dbx_modul=dbxAdmin&dbx_run1=run');
      $o_form->_msg_info = '';
      $o_form->add_rep('session_body', $this->session_panel_body_html());

      return $o_form->add_norep($o_form->run());
   }
}
