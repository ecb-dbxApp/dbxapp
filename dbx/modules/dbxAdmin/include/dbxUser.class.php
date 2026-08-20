<?php

namespace dbx\dbxAdmin;

require_once dirname(__DIR__, 3) . '/include/dbxPasswordPolicy.class.php';

dbx()->get_system_obj('dbxReport', 'use');
require_once __DIR__ . '/dbxReport_AdminUser.class.php';

class dbxUser {

   private string $dd_user = 'dbxUser';
   private string $dd_group = 'dbxUser_groups';
   private string $action_error = '';

   private function url($run2, $params = array()) {
      return dbx()->append_url_params(
         '?dbx_modul=dbxAdmin&dbx_run1=user&dbx_run2=' . rawurlencode((string)$run2),
         $params
      );
   }

   private function action_url($run2, $params = array()) {
      $url = $this->url($run2, $params);
      $secured_url = dbx()->action_url($url);
      if ($secured_url !== $url) {
         return $secured_url;
      }

      $do = (string)($params['dbx_do'] ?? $params['dbx_run3'] ?? 'action');
      $rid = (int)($params['rid'] ?? 0);
      $params['dbx_token'] = dbx()->action_token($this->action_token_scope($do, $rid));
      return $this->url($run2, $params);
   }

   private function action_token_scope($do, $rid) {
      $do = preg_replace('/[^a-z0-9_-]+/i', '', (string)$do);
      return 'dbxAdmin.user.' . ($do !== '' ? $do : 'action') . '.' . max(0, (int)$rid);
   }

   private function check_action_token(&$message, $texts) {
      $do = dbx()->get_modul_var('dbx_do', '', 'parameter');
      if (!$do) {
         $do = dbx()->get_modul_var('dbx_run3', '', 'parameter');
      }
      $rid = dbx()->get_modul_var('rid', 0, 'int');
      $token = dbx()->get_modul_var('dbx_token', '', 'varchar|max=128');
      if (dbx()->check_action_token($this->action_token_scope($do, $rid), $token)) {
         return true;
      }

      $message = $texts->get_fd_message('token_error');
      $this->action_error = $message;
      dbx()->sys_msg('security', 'dbxAdmin', dbx()->user(), 'invalid admin user action token', $_SERVER['REQUEST_URI'] ?? '');
      return false;
   }

   private function badge($label, $class = 'secondary') {
      return '<span class="badge bg-' . dbx()->esc($class) . '">' . dbx()->esc($label) . '</span>';
   }

   private function action_button($url, $icon, $title, $class = 'btn-outline-secondary') {
      return '<a class="btn btn-sm ' . dbx()->esc($class) . ' me-1 dbxAjax" href="' . dbx()->esc($url) . '" title="' . dbx()->esc($title) . '">'
           . '<i class="' . dbx()->esc($icon) . '"></i></a>';
   }

   private function modal_button($url, $icon, $title, $class = 'btn-outline-primary', $label = '', $size = 'btn-sm') {
      $size = trim((string)$size);
      $size_class = $size !== '' ? $size . ' ' : '';
      $text = $label !== '' ? ' ' . dbx()->esc($label) : '';
      return '<a class="btn dbx-win ' . $size_class . dbx()->esc($class) . ' me-1" href="' . dbx()->esc($url) . '" '
           . 'data-url="' . dbx()->esc($url) . '" data-title="' . dbx()->esc($title) . '" role="button" title="' . dbx()->esc($title) . '">'
           . '<i class="' . dbx()->esc($icon) . '"></i>' . $text . '</a>';
   }

   private function delete_button($url, $title, $texts) {
      return '<a class="btn btn-sm btn-outline-danger me-1 dbxAjax dbxConfirm" href="' . dbx()->esc($url) . '" '
           . 'data-confirm-title="<i class=\'bi bi-trash\'></i> ' . dbx()->esc($title) . '" '
           . 'data-confirm="' . dbx()->esc($texts->get_fd_message('delete_confirm')) . '" '
           . 'data-confirm-hint="<small>' . dbx()->esc($texts->get_fd_message('delete_hint')) . '</small>" '
           . 'data-confirm-buttons="yesno" title="' . dbx()->esc($title) . '">'
           . '<i class="bi bi-trash"></i></a>';
   }

   private function role_options($texts) {
      $options = array(
         'admin'  => $texts->get_fd_message('role_admin'),
         'owner'  => $texts->get_fd_message('role_owner'),
         'member' => $texts->get_fd_message('role_member'),
         'guest'  => $texts->get_fd_message('role_guest'),
         'api'    => $texts->get_fd_message('role_api'),
      );

      $db = dbx()->get_system_obj('dbxDB');
      $groups = $db->select($this->dd_group, '', array('name', 'description', 'active'), 'name', 'ASC', '', 0, 0, 0);
      if (is_array($groups)) {
         foreach ($groups as $group) {
            $name = trim((string)($group['name'] ?? ''));
            if ($name === '' || array_key_exists($name, $options)) {
               continue;
            }
            $description = trim((string)($group['description'] ?? ''));
            $options[$name] = $description !== '' ? $description : $name;
         }
      }

      return $options;
   }

   private function status_label($status, $texts) {
      if ((string)$status === '0') {
         return $this->badge($texts->get_fd_message('status_locked'), 'danger');
      }
      if ((string)$status === '1') {
         return $this->badge($texts->get_fd_message('status_active'), 'success');
      }
      if ((string)$status === '2') {
         return $this->badge($texts->get_fd_message('status_waiting'), 'warning');
      }
      return $this->badge($texts->get_fd_message('status_unknown'), 'secondary');
   }

   private function verified_label($value, $texts) {
      return ((string)$value === '1')
         ? $this->badge($texts->get_fd_message('verified'), 'success')
         : $this->badge($texts->get_fd_message('unverified'), 'warning');
   }

   private function roles_label($roles, $texts) {
      $items = array_filter(array_map('trim', explode(',', (string)$roles)));
      if (!$items) {
         return $this->badge($texts->get_fd_message('no_roles'), 'secondary');
      }

      $html = '';
      foreach ($items as $role) {
         $class = ($role === 'admin') ? 'primary' : (($role === 'api') ? 'dark' : 'info');
         $html .= $this->badge($role, $class) . ' ';
      }
      return trim($html);
   }

   private function user_identity($row) {
      $name = trim((string)($row['name'] ?? ''));
      $uname = trim((string)($row['uname'] ?? ''));
      $email = trim((string)($row['email'] ?? ''));

      $title = $name !== '' ? $name : $uname;
      $html = '<div class="fw-semibold">' . dbx()->esc($title) . '</div>';
      $html .= '<div class="text-muted small">@' . dbx()->esc($uname) . '</div>';
      if ($email !== '') {
         $html .= '<div class="small"><a href="mailto:' . dbx()->esc($email) . '">' . dbx()->esc($email) . '</a></div>';
      }
      return $html;
   }

   private function user_actions($row, $texts) {
      $id = (int)($row['id'] ?? 0);
      if ($id <= 0) {
         return '';
      }

      $html = '';
      $protected = ($id <= 1 || $id == dbx()->user());
      $html .= $this->modal_button(
         $this->url('edit_user', array('rid' => $id)),
         'bi bi-pencil-square',
         $texts->get_fd_message('action_edit')
      );

      if ($protected) {
         $html .= '<span class="badge bg-secondary me-1">'
            . dbx()->esc($texts->get_fd_message('action_protected'))
            . '</span>';
      } else {
         if ((string)($row['is_confirm'] ?? '') === '1') {
            $html .= $this->action_button($this->action_url('list_user', array('dbx_do' => 'unverify', 'rid' => $id)), 'bi bi-patch-minus', $texts->get_fd_message('action_unverify'));
         } else {
            $html .= $this->action_button($this->action_url('list_user', array('dbx_do' => 'verify', 'rid' => $id)), 'bi bi-patch-check', $texts->get_fd_message('action_verify'), 'btn-outline-success');
         }

         if ((string)($row['status'] ?? '') === '0') {
            $html .= $this->action_button($this->action_url('list_user', array('dbx_do' => 'unlock', 'rid' => $id)), 'bi bi-unlock', $texts->get_fd_message('action_unlock'), 'btn-outline-success');
         } else {
            $html .= $this->action_button($this->action_url('list_user', array('dbx_do' => 'lock', 'rid' => $id)), 'bi bi-lock', $texts->get_fd_message('action_lock'), 'btn-outline-danger');
         }

         $html .= $this->action_button($this->action_url('list_user', array('dbx_do' => 'reset_password', 'rid' => $id)), 'bi bi-key', $texts->get_fd_message('action_reset_password'), 'btn-outline-warning');
         $html .= $this->delete_button(
            $this->action_url('list_user', array('dbx_do' => 'row_delete', 'rid' => $id)),
            $texts->get_fd_message('action_delete'),
            $texts
         );
      }
      return $html;
   }

   private function stats_html($texts) {
      $db = dbx()->get_system_obj('dbxDB');
      $all = (int)$db->count($this->dd_user);
      $active = (int)$db->count($this->dd_user, 'status = 1');
      $locked = (int)$db->count($this->dd_user, 'status = 0');
      $verified = (int)$db->count($this->dd_user, 'is_confirm = 1');

      $items = array(
         array('label' => $texts->get_fd_message('stat_users'), 'value' => $all, 'class' => 'primary', 'filter' => ''),
         array('label' => $texts->get_fd_message('stat_active'), 'value' => $active, 'class' => 'success', 'filter' => 'active'),
         array('label' => $texts->get_fd_message('stat_locked'), 'value' => $locked, 'class' => 'danger', 'filter' => 'locked'),
         array('label' => $texts->get_fd_message('stat_verified'), 'value' => $verified, 'class' => 'info', 'filter' => 'verified'),
      );

      $html = '<div class="row g-2 mb-3">';
      foreach ($items as $item) {
         $url = $item['filter'] === '' ? $this->url('list_user') : $this->url('list_user', array('filter' => $item['filter']));
         $html .= '<div class="col-6 col-lg-3"><a class="text-decoration-none" href="' . dbx()->esc($url) . '"><div class="border rounded p-3 bg-light h-100">'
               . '<div class="text-muted small">' . dbx()->esc($item['label']) . '</div>'
               . '<div class="fs-4 fw-semibold text-' . dbx()->esc($item['class']) . '">' . dbx()->esc($item['value']) . '</div>'
               . '</div></a></div>';
      }
      $html .= '</div>';
      return $html;
   }

   private function nav_html($active, $texts) {
      $tabs = array(
         'users'  => array('label' => $texts->get_fd_message('tab_users'), 'url' => $this->url('list_user')),
         'groups' => array('label' => $texts->get_fd_message('tab_groups'), 'url' => $this->url('list_groups')),
      );

      $html = '<ul class="nav nav-tabs mb-3">';
      foreach ($tabs as $key => $tab) {
         $class = ($key === $active) ? ' active' : '';
         $html .= '<li class="nav-item"><a class="nav-link' . $class . '" href="' . dbx()->esc($tab['url']) . '">' . dbx()->esc($tab['label']) . '</a></li>';
      }
      $html .= '</ul>';
      return $html;
   }

   /**
    * Prüft die beiden Passwortfelder beim Anlegen oder Bearbeiten.
    * Leere Felder sind nur bei bestehenden Benutzern erlaubt.
    *
    * @return array{change:bool,field:string,message:string}
    */
   private function validate_password_change(bool $is_new, string $password, string $repeat, $texts): array {
      if (!$is_new && $password === '' && $repeat === '') {
         return array('change' => false, 'field' => '', 'message' => '');
      }
      if ($password === '') {
         return array('change' => false, 'field' => 'password_new', 'message' => $texts->get_fd_message('password_required'));
      }
      if ($repeat === '') {
         return array('change' => false, 'field' => 'password_new2', 'message' => $texts->get_fd_message('password_repeat_required'));
      }
      if ($password !== $repeat) {
         return array('change' => false, 'field' => 'password_new2', 'message' => $texts->get_fd_message('password_mismatch'));
      }

      $length = function_exists('mb_strlen') ? mb_strlen($password) : strlen($password);
      if ($length < 6) {
         return array('change' => false, 'field' => 'password_new', 'message' => $texts->get_fd_message('password_too_short'));
      }

      return array('change' => true, 'field' => '', 'message' => '');
   }

   private function settings_after_password_change(string $raw): string {
      $settings = json_decode($raw, true);
      $settings = is_array($settings) ? $settings : array();
      unset($settings['password_reset_required']);
      $settings['password_changed_at'] = date(DATE_ATOM);
      return (string)json_encode(
         $settings,
         JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
      );
   }

   private function user_setting_date($value, $texts): string {
      if (is_numeric($value) && (int)$value > 0) {
         return date('d.m.Y H:i:s', (int)$value);
      }

      $value = trim((string)$value);
      if ($value === '') return $texts->get_fd_message('settings_empty');
      $timestamp = strtotime($value);
      return $timestamp ? date('d.m.Y H:i:s', $timestamp) : $value;
   }

   private function user_setting_row(string $label, string $value): string {
      return '<div class="row g-2 py-1 border-bottom">'
         . '<div class="col-sm-5 fw-semibold">' . dbx()->esc($label) . '</div>'
         . '<div class="col-sm-7">' . $value . '</div>'
         . '</div>';
   }

   private function user_setting_value($value, $texts): string {
      if (is_bool($value)) {
         return $value
            ? $texts->get_fd_message('option_yes')
            : $texts->get_fd_message('option_no');
      }
      if ($value === null || $value === '') {
         return '<span class="text-muted">'
            . dbx()->esc($texts->get_fd_message('settings_not_set'))
            . '</span>';
      }
      if (is_scalar($value)) return dbx()->esc((string)$value);
      return '<span class="text-muted">'
         . dbx()->esc($texts->format_fd_message(
            'settings_entries',
            array('count' => count((array)$value))
         ))
         . '</span>';
   }

   private function user_settings_view(array $user, $texts): string {
      $raw = trim((string)($user['settings'] ?? ''));
      $settings = $raw !== '' ? json_decode($raw, true) : array();
      if (!is_array($settings)) {
         return '<div class="alert alert-warning mb-0">'
            . dbx()->esc($texts->get_fd_message('settings_invalid'))
            . '</div>';
      }

      $confirm = is_array($settings['register_confirm'] ?? null) ? $settings['register_confirm'] : array();
      $confirmed = (int)($user['is_confirm'] ?? 0) === 1;
      $expires = (int)($confirm['expires'] ?? 0);
      if ($confirmed) {
         $status = '<span class="badge bg-success">' . dbx()->esc($texts->get_fd_message('settings_email_confirmed')) . '</span>';
      } elseif ($confirm && $expires > 0 && $expires < time()) {
         $status = '<span class="badge bg-danger">' . dbx()->esc($texts->get_fd_message('settings_link_expired')) . '</span>';
      } elseif ($confirm) {
         $status = '<span class="badge bg-warning text-dark">' . dbx()->esc($texts->get_fd_message('settings_confirmation_pending')) . '</span>';
      } else {
         $status = '<span class="badge bg-secondary">' . dbx()->esc($texts->get_fd_message('settings_no_confirmation')) . '</span>';
      }

      $html = '<div class="dbx-user-settings-view">';
      $html .= $this->user_setting_row($texts->get_fd_message('settings_registration_status'), $status);
      if ($confirm) {
         $html .= $this->user_setting_row(
            $texts->get_fd_message('settings_mail_sent'),
            dbx()->esc($this->user_setting_date($confirm['sent'] ?? '', $texts))
         );
         $html .= $this->user_setting_row(
            $texts->get_fd_message('settings_link_valid_until'),
            dbx()->esc($this->user_setting_date($confirm['expires'] ?? '', $texts))
         );
      }

      if (array_key_exists('password_reset_required', $settings)) {
         $required = !empty($settings['password_reset_required'])
            ? '<span class="badge bg-warning text-dark">' . dbx()->esc($texts->get_fd_message('option_yes')) . '</span>'
            : '<span class="badge bg-secondary">' . dbx()->esc($texts->get_fd_message('option_no')) . '</span>';
         $html .= $this->user_setting_row($texts->get_fd_message('settings_password_change'), $required);
      }

      foreach ($settings as $key => $value) {
         if (in_array((string)$key, array('register_confirm', 'password_reset_required'), true)) continue;
         $label = ucwords(str_replace(array('_', '-'), ' ', (string)$key));
         $display = preg_match('/(^|[_-])(pass(word)?|token|hash|secret|key)($|[_-])/i', (string)$key)
            ? '<span class="text-muted">' . dbx()->esc($texts->get_fd_message('settings_protected')) . '</span>'
            : $this->user_setting_value($value, $texts);
         $html .= $this->user_setting_row($label, $display);
      }

      $html .= '<div class="small text-muted mt-2"><i class="bi bi-shield-lock"></i> '
         . dbx()->esc($texts->get_fd_message('settings_security_hint'))
         . '</div>';
      $html .= '</div>';
      return $html;
   }

   private function handle_user_action(&$message, $texts) {
      $db = dbx()->get_system_obj('dbxDB');
      $do = dbx()->get_modul_var('dbx_do', '', 'parameter');
      if (!$do) {
         $do = dbx()->get_modul_var('dbx_run3', '', 'parameter');
      }

      $rid = dbx()->get_modul_var('rid', 0, 'int');
      if (!$do || !$rid) {
         return '';
      }

      if ($do === 'row_edit') {
         return $this->edit_user($rid);
      }

      // row_delete ist eine dbxReport-Standardaktion und wurde bereits vor
      // dem Modulstart zentral inklusive RID geprueft.
      if ($do !== 'row_delete' && !$this->check_action_token($message, $texts)) {
         return '';
      }

      if ($do === 'row_delete') {
         if ($rid <= 1 || $rid == dbx()->user()) {
            $message = $texts->get_fd_message('delete_protected');
            return '';
         }
         $ok = $db->delete($this->dd_user, $rid);
         $message = $ok
            ? $texts->get_fd_message('delete_success')
            : $texts->get_fd_message('delete_error');
         return '';
      }

      $data = array();
      if ($do === 'verify') {
         $data['is_confirm'] = 1;
      }
      if ($do === 'unverify') {
         $data['is_confirm'] = 0;
      }
      if ($do === 'lock') {
         if ($rid <= 1 || $rid == dbx()->user()) {
            $message = $texts->get_fd_message('lock_protected');
            return '';
         }
         $data['status'] = 0;
      }
      if ($do === 'unlock') {
         $data['status'] = 1;
      }
      if ($do === 'reset_password') {
         $new_password = dbxPasswordPolicy::generate(12);
         $data['pass'] = password_hash($new_password, PASSWORD_DEFAULT);
      }

      if ($data) {
         $ok = $db->update($this->dd_user, $data, $rid);
         if ($ok && $do === 'reset_password') {
            $message = $texts->format_fd_message(
               'reset_password',
               array('id' => $rid, 'password' => $new_password)
            );
         } else {
            $message = $ok
               ? $texts->get_fd_message('update_success')
               : $texts->get_fd_message('update_error');
         }
      }

      return '';
   }

   private function decorate_users($rows, $texts) {
      $out = array();
      foreach ((array)$rows as $row) {
         $row['user'] = $this->user_identity($row);
         $row['roles_view'] = $this->roles_label($row['roles'] ?? '', $texts);
         $row['verified'] = $this->verified_label($row['is_confirm'] ?? '', $texts);
         $row['login'] = $this->status_label($row['status'] ?? '', $texts);
         $row['profile'] = '<span class="small text-muted">'
                         . dbx()->esc(trim((string)($row['language'] ?? '')))
                         . ' / ' . dbx()->esc(trim((string)($row['design'] ?? '')))
                         . ' / ' . dbx()->esc(trim((string)($row['color'] ?? '')))
                         . '</span>';
         $row['ops'] = $this->user_actions($row, $texts);
         $out[] = $row;
      }
      return $out;
   }

   private function report_users() {
      $message = '';
      $db = dbx()->get_system_obj('dbxDB');
      $o_report = new dbxReport_AdminUser;
      $o_report->init('report-admin-user', 'report-admin-user');
      $o_report->set_field_definition('dbxAdmin|rpt-admin-user-selection');
      $o_report->load_fd_messages();
      $content = $this->handle_user_action($message, $o_report);
      if ($content !== '') {
         return $content;
      }
      $o_report->add_rep('frame_use_form', '0');
      $o_report->add_rep('report_shell_class', 'dbx-admin-users');
      $o_report->add_rep('report_shell_attrs', 'data-dbx="lib=report|form=0"');
      $o_report->set_data_definition($this->dd_user);
      $o_report->set_action($this->url('list_user'));
      $o_report->_pages = true;
      $o_report->_create_row_select = true;
      $o_report->_create_row_edit = false;
      $o_report->_create_row_delete = false;
      $o_report->_create_sel_flds = true;
      $o_report->_but_pagination = 7;
      $o_report->_fld_id = 'id';
      $o_report->set_data(array('dbx_rrows' => 25, 'dbx_rsort' => 'id', 'dbx_rdesc' => 'ASC'));
      $o_report->_msg_info = '';
      $o_report->_rpt_format = array(
         'id' => 'html-chars',
         'uname' => 'html-chars',
         'name' => 'html-chars',
         'email' => 'html-chars',
         'user' => 'html',
         'roles_view' => 'html',
         'verified' => 'html',
         'login' => 'html',
         'profile' => 'html',
         'ops' => 'html',
         'lastvisit' => 'php-datetime-usr',
         'update_date' => 'php-datetime-usr',
      );

      $new_user_btn = $this->modal_button(
         $this->url('edit_user', array('rid' => 'new')),
         'bi bi-person-plus',
         $o_report->get_fd_message('new_user'),
         'btn-success',
         $o_report->get_fd_message('new_user'),
         ''
      );
      $o_report->add_obj('new_user', 'obj-value', $new_user_btn);
      $o_report->add_obj('bar_actions', 'obj-value', $new_user_btn);
      $o_report->add_obj('stats', 'obj-value', $this->stats_html($o_report));
      $o_report->add_obj('tabs', 'obj-value', $this->nav_html('users', $o_report));
      $o_report->add_obj('rows_delete', 'obj-value', '');
      $o_report->create_selection_fields('dbxAdmin|rpt-admin-user-selection');

      if ($message !== '') {
         if ($this->action_error !== '') {
            $o_report->_msg_error = $message;
         } else {
            $o_report->_msg_success = $message;
         }
      }

      if ($o_report->submit()) {
         if (!$o_report->errors()) {
            $o_report->_msg_success = $message ?: '';
         } else {
            $o_report->_msg_error = $o_report->get_fd_message(
               'validation_error'
            );
         }
      }

      $search = $o_report->get_fld_val('dbx_rwhere', '', 'sqlsearch|max=64');
      $rrows  = $o_report->get_fld_val('dbx_rrows', 25, 'int');
      $rpos   = $o_report->get_fld_val('dbx_rpos', 0, 'int');
      $rsort  = $o_report->get_fld_val('dbx_rsort', 'id', 'parameter');
      $rdesc  = $o_report->get_fld_val('dbx_rdesc', 'ASC', 'parameter');
      $select = $o_report->get_fld_val('dbx_rselect', 0, 'int');
      $filter = dbx()->get_modul_var('filter', '', 'parameter');

      $where = array();
      if ($search !== '') {
         $where['search'] = array(
            'value' => $search,
            'like'  => array('uname', 'name', 'name2', 'email', 'roles', 'ort'),
            'equal' => array('id'),
            'mode'  => 'contains',
         );
      }
      if ($filter === 'locked') {
         $where['status'] = 0;
      } elseif ($filter === 'active') {
         $where['status'] = 1;
      } elseif ($filter === 'unverified') {
         $where['is_confirm'] = 0;
      } elseif ($filter === 'admin') {
         $where['roles'] = array('op' => 'LIKE', 'value' => '%admin%');
      } elseif ($filter === 'verified') {
         $where['is_confirm'] = 1;
      }
      if (!$where) {
         $where = '';
      }
      if ($select) {
         if (is_array($where)) {
            $where = $db->normalize_where($this->dd_user, $where);
         }
         $where = $o_report->add_rwhere_select($where);
      }

      $db_fields = array('id', 'uname', 'name', 'name2', 'email', 'roles', 'is_confirm', 'status', 'language', 'design', 'color', 'lastvisit', 'update_date');
      $flds = array(
         'id'         => 'ID',
         'user'       => $o_report->get_fd_message('column_user'),
         'roles_view' => $o_report->get_fd_message('column_roles'),
         'verified'   => $o_report->get_fd_message('column_verified'),
         'login'      => $o_report->get_fd_message('column_login'),
         'profile'    => $o_report->get_fd_message('column_profile'),
         'lastvisit'  => $o_report->get_fd_message('column_last_visit'),
         'update_date'=> $o_report->get_fd_message('column_updated'),
         'ops'        => $o_report->get_fd_message('column_actions'),
      );

      $o_report->_rflds = $flds;
      $o_report->_rrows = $rrows;
      $o_report->_rpos = $rpos;
      $o_report->_count_all = $db->count($this->dd_user);
      $o_report->_rcount = $db->count($this->dd_user, $where);
      $rows = $db->select($this->dd_user, $where, $db_fields, $rsort, $rdesc, '', $rrows, $rpos, 0);
      $o_report->_rdata = $this->decorate_users($rows, $o_report);

      return $o_report->run();
   }

   private function edit_user($rid = 'new') {
      $db = dbx()->get_system_obj('dbxDB');
      $is_new = ($rid === 'new' || !$rid);
      $data = array(
         'status' => 1,
         'is_confirm' => 1,
         'language' => 'de',
         'roles' => 'member',
         'edit' => 0,
         'password_new' => '',
         'password_new2' => '',
         'settings' => '{}',
      );

      if (!$is_new) {
         $record = $db->select1($this->dd_user, (int)$rid, '*', 0);
         if (is_array($record)) {
            $data = array_merge($data, $record);
         }
      }

      $o_form = dbx()->get_system_obj('dbxForm');
      $o_form->init('form-admin-user', 'form-admin-user');
      $o_form->set_field_definition('dbxAdmin|rpt-admin-user-selection');
      $o_form->load_fd_messages();
      $o_form->add_module_bar(
         $o_form->get_fd_message(
            $is_new ? 'form_new_title' : 'form_edit_title'
         ),
         'bi-person-gear',
         $o_form->get_fd_message(
            $is_new ? 'form_new_subtitle' : 'form_edit_subtitle'
         ),
         true
      );
      $o_form->add_module_bar_form_actions(array('save' => true));
      $o_form->set_data_definition($this->dd_user);
      $o_form->_fld_id = 'id';
      $o_form->set_data($data);
      $o_form->_msg_info = $o_form->get_fd_message(
         $is_new ? 'form_new_info' : 'form_edit_info'
      );
      $o_form->set_action($this->url('edit_user', array('rid' => ($is_new ? 'new' : (int)$rid))));
      $o_form->set_activ_id($is_new ? 0 : (int)$rid);

      $o_form->add_fld('uname', 'text-label', $o_form->get_fd_message('field_login'), 'alphanum|min=4|max=60');
      $password_hint = $o_form->get_fd_message(
         $is_new ? 'password_hint_new' : 'password_hint_edit'
      );
      $o_form->add_fld('password_new', 'password-label', $o_form->get_fd_message('field_password'), 'varchar|max=128', placeholder: $password_hint);
      $o_form->add_fld('password_new2', 'password-label', $o_form->get_fd_message('field_password_repeat'), 'varchar|max=128', placeholder: $o_form->get_fd_message('password_repeat_hint'));
      $o_form->add_fld('name', 'text-label', $o_form->get_fd_message('field_name'), 'parameter|max=255');
      $o_form->add_fld('name2', 'text-label', $o_form->get_fd_message('field_name2'), 'parameter|max=255');
      $o_form->add_fld('email', 'text-label', $o_form->get_fd_message('field_email'), 'email|max=255');
      $o_form->add_fld('roles', 'multi-select', $o_form->get_fd_message('field_roles'), 'array|parameter', data: array('size' => 7), options: $this->role_options($o_form));
      $o_form->add_fld('status', 'select-single-label', $o_form->get_fd_message('field_login_status'), 'int', options: array(
         '1' => $o_form->get_fd_message('option_active'),
         '0' => $o_form->get_fd_message('option_locked'),
         '2' => $o_form->get_fd_message('option_waiting'),
      ));
      $o_form->add_fld('is_confirm', 'select-single-label', $o_form->get_fd_message('field_verified'), 'int', options: array(
         '1' => $o_form->get_fd_message('option_verified'),
         '0' => $o_form->get_fd_message('option_open'),
      ));
      $o_form->add_fld('edit', 'select-single-label', $o_form->get_fd_message('field_editor'), 'int', options: array(
         '1' => $o_form->get_fd_message('option_yes'),
         '0' => $o_form->get_fd_message('option_no'),
      ));
      $o_form->add_fld('language', 'select-single-label', $o_form->get_fd_message('field_language'), 'parameter|max=3', options: array(
         'de' => $o_form->get_fd_message('language_de'),
         'en' => $o_form->get_fd_message('language_en'),
         'es' => $o_form->get_fd_message('language_es'),
      ));
      $o_form->add_fld('design', 'text-label', $o_form->get_fd_message('field_design'), 'parameter|max=32');
      $o_form->add_fld('color', 'text-label', $o_form->get_fd_message('field_color'), 'parameter|max=32');
      $o_form->add_fld('telefon', 'text-label', $o_form->get_fd_message('field_phone'), 'parameter|max=64');
      $o_form->add_fld('handy', 'text-label', $o_form->get_fd_message('field_mobile'), 'parameter|max=64');
      $o_form->add_fld('strasse', 'text-label', $o_form->get_fd_message('field_street'), 'parameter|max=255');
      $o_form->add_fld('plz', 'text-label', $o_form->get_fd_message('field_postcode'), 'parameter|max=16');
      $o_form->add_fld('ort', 'text-label', $o_form->get_fd_message('field_city'), 'parameter|max=255');
      $o_form->add_fld('land', 'text-label', $o_form->get_fd_message('field_country'), 'parameter|max=32');
      $o_form->add_obj('settings_view', 'obj-value', $this->user_settings_view($data, $o_form));

      if ($o_form->submit()) {
         $password = (string)$o_form->get_post_data('password_new', '', '*');
         $password_repeat = (string)$o_form->get_post_data('password_new2', '', '*');
         $password_validation = $this->validate_password_change($is_new, $password, $password_repeat, $o_form);
         $password_changed = (bool)$password_validation['change'];
         if ($password_validation['message'] !== '') {
            $o_form->add_fld_error($password_validation['field'], $password_validation['message']);
            $o_form->_msg_error = $password_validation['message'];
         }

         if (!$o_form->errors()) {
            $roles = $o_form->get_post('roles', array(), 'array|parameter');
            if (!is_array($roles)) {
               $roles = array_filter(array_map('trim', explode(',', (string)$roles)));
            }

            $values = array(
               'uname'      => $o_form->get_post('uname', '', 'alphanum|max=60'),
               'name'       => $o_form->get_post('name', '', 'parameter|max=255'),
               'name2'      => $o_form->get_post('name2', '', 'parameter|max=255'),
               'email'      => $o_form->get_post('email', '', 'email|max=255'),
               'roles'      => implode(',', array_filter(array_map('trim', $roles))),
               'status'     => $o_form->get_post('status', 1, 'int'),
               'is_confirm' => $o_form->get_post('is_confirm', 0, 'int'),
               'edit'       => $o_form->get_post('edit', 0, 'int'),
               'language'   => $o_form->get_post('language', 'de', 'parameter|max=3'),
               'design'     => $o_form->get_post('design', '', 'parameter|max=32'),
               'color'      => $o_form->get_post('color', '', 'parameter|max=32'),
               'telefon'    => $o_form->get_post('telefon', '', 'parameter|max=64'),
               'handy'      => $o_form->get_post('handy', '', 'parameter|max=64'),
               'strasse'    => $o_form->get_post('strasse', '', 'parameter|max=255'),
               'plz'        => $o_form->get_post('plz', '', 'parameter|max=16'),
               'ort'        => $o_form->get_post('ort', '', 'parameter|max=255'),
               'land'       => $o_form->get_post('land', '', 'parameter|max=32'),
               'settings'   => (string)($data['settings'] ?? '{}'),
            );

            if ($password_changed) {
               $values['pass'] = password_hash($password, PASSWORD_DEFAULT);
               $values['settings'] = $this->settings_after_password_change(
                  (string)($data['settings'] ?? '{}')
               );
            }

            $server = $db->get_dd_server($this->dd_user);
            $uname = $db->escape($values['uname'], $server);
            $duplicate_where = "uname='$uname'";
            if (!$is_new) {
               $duplicate_where .= ' AND id <> ' . (int)$rid;
            }
            if ($db->count($this->dd_user, $duplicate_where) > 0) {
               $o_form->add_fld_error(
                  'uname',
                  $o_form->get_fd_message('duplicate_login')
               );
               $o_form->_msg_error = $o_form->get_fd_message(
                  'duplicate_login'
               );
            } else {
               $ok = $db->save($this->dd_user, $values, $is_new ? 0 : (int)$rid);
               if ($ok) {
                  $o_form->_msg_success = $o_form->get_fd_message(
                     'user_save_success'
                  );
               } else {
                  $o_form->_msg_error = $o_form->get_fd_message(
                     'user_save_error'
                  );
               }
            }
         } else {
            if (!$o_form->_msg_error) {
               $o_form->_msg_error = $o_form->get_fd_message(
                  'validation_error'
               );
            }
         }
      }

      return $o_form->run();
   }

   private function group_actions($row, $texts) {
      $id = (int)($row['id'] ?? 0);
      if ($id <= 0) {
         return '';
      }
      $html = $this->modal_button(
         $this->url('edit_group', array('rid' => $id)),
         'bi bi-pencil-square',
         $texts->get_fd_message('action_edit')
      );
      if ((string)($row['active'] ?? '') === '1') {
         $html .= $this->action_button($this->action_url('list_groups', array('dbx_do' => 'group_deactivate', 'rid' => $id)), 'bi bi-pause-circle', $texts->get_fd_message('action_deactivate'), 'btn-outline-warning');
      } else {
         $html .= $this->action_button($this->action_url('list_groups', array('dbx_do' => 'group_activate', 'rid' => $id)), 'bi bi-check-circle', $texts->get_fd_message('action_activate'), 'btn-outline-success');
      }
      $html .= $this->delete_button(
         $this->action_url('list_groups', array('dbx_do' => 'row_delete', 'rid' => $id)),
         $texts->get_fd_message('action_delete'),
         $texts
      );
      return $html;
   }

   private function handle_group_action(&$message, $texts) {
      $db = dbx()->get_system_obj('dbxDB');
      $do = dbx()->get_modul_var('dbx_do', '', 'parameter');
      if (!$do) {
         $do = dbx()->get_modul_var('dbx_run3', '', 'parameter');
      }
      $rid = dbx()->get_modul_var('rid', 0, 'int');

      if ($do === 'row_edit' && $rid) {
         return $this->edit_group($rid);
      }
      if ($do && $rid && $do !== 'row_delete' && !$this->check_action_token($message, $texts)) {
         return '';
      }
      if ($do === 'row_delete' && $rid) {
         $ok = $db->delete($this->dd_group, $rid);
         $message = $ok
            ? $texts->get_fd_message('delete_success')
            : $texts->get_fd_message('delete_error');
      }
      if ($do === 'group_activate' && $rid) {
         $ok = $db->update($this->dd_group, array('active' => 1), $rid);
         $message = $ok
            ? $texts->get_fd_message('activate_success')
            : $texts->get_fd_message('activate_error');
      }
      if ($do === 'group_deactivate' && $rid) {
         $ok = $db->update($this->dd_group, array('active' => 0), $rid);
         $message = $ok
            ? $texts->get_fd_message('deactivate_success')
            : $texts->get_fd_message('deactivate_error');
      }

      return '';
   }

   private function decorate_groups($rows, $texts) {
      $users = dbx()->get_system_obj('dbxDB')->select($this->dd_user, '', array('roles'), 'id', 'ASC', '', 0, 0, 0);
      $out = array();
      foreach ((array)$rows as $row) {
         $name = (string)($row['name'] ?? '');
         $count = 0;
         foreach ((array)$users as $user) {
            $roles = array_filter(array_map('trim', explode(',', (string)($user['roles'] ?? ''))));
            if (in_array($name, $roles, true)) {
               $count++;
            }
         }
         $row['active_view'] = ((string)($row['active'] ?? '') === '1')
            ? $this->badge($texts->get_fd_message('status_active'), 'success')
            : $this->badge($texts->get_fd_message('status_inactive'), 'secondary');
         $row['users'] = (string)$count;
         $row['ops'] = $this->group_actions($row, $texts);
         $out[] = $row;
      }
      return $out;
   }

   private function report_groups() {
      $message = '';
      $db = dbx()->get_system_obj('dbxDB');
      $o_report = new dbxReport_AdminUser;
      $o_report->init('report-admin-groups', 'report-admin-groups');
      $o_report->set_field_definition('dbxAdmin|rpt-admin-groups-selection');
      $o_report->load_fd_messages();
      $content = $this->handle_group_action($message, $o_report);
      if ($content !== '') {
         return $content;
      }
      $o_report->add_rep('frame_use_form', '0');
      $o_report->add_rep('report_shell_class', 'dbx-admin-groups');
      $o_report->add_rep('report_shell_attrs', 'data-dbx="lib=report|form=0"');
      $o_report->set_data_definition($this->dd_group);
      $o_report->set_action($this->url('list_groups'));
      $o_report->_pages = true;
      $o_report->_create_row_select = true;
      $o_report->_create_row_edit = false;
      $o_report->_create_row_delete = false;
      $o_report->_create_sel_flds = true;
      $o_report->_but_pagination = 7;
      $o_report->_fld_id = 'id';
      $o_report->set_data(array('dbx_rrows' => 25, 'dbx_rsort' => 'name', 'dbx_rdesc' => 'ASC'));
      $o_report->_msg_info = '';
      $o_report->_rpt_format = array(
         'id' => 'html-chars',
         'name' => 'html-chars',
         'description' => 'html-chars',
         'active_view' => 'html',
         'ops' => 'html',
      );
      $new_group_btn = $this->modal_button(
         $this->url('edit_group', array('rid' => 'new')),
         'bi bi-plus-circle',
         $o_report->get_fd_message('new_group'),
         'btn-success',
         $o_report->get_fd_message('new_group'),
         ''
      );
      $o_report->add_obj('new_group', 'obj-value', $new_group_btn);
      $o_report->add_obj('bar_actions', 'obj-value', $new_group_btn);
      $o_report->add_obj('tabs', 'obj-value', $this->nav_html('groups', $o_report));
      $o_report->add_obj('rows_delete', 'obj-value', '');
      $o_report->create_selection_fields('dbxAdmin|rpt-admin-groups-selection');
      if ($message !== '') {
         if ($this->action_error !== '') {
            $o_report->_msg_error = $message;
         } else {
            $o_report->_msg_success = $message;
         }
      }

      $search = $o_report->get_fld_val('dbx_rwhere', '', 'sqlsearch|max=64');
      $rrows  = $o_report->get_fld_val('dbx_rrows', 25, 'int');
      $rpos   = $o_report->get_fld_val('dbx_rpos', 0, 'int');
      $rsort  = $o_report->get_fld_val('dbx_rsort', 'name', 'parameter');
      $rdesc  = $o_report->get_fld_val('dbx_rdesc', 'ASC', 'parameter');
      $where = '';
      if ($search !== '') {
         $where = array('search' => array('value' => $search, 'like' => array('name', 'description'), 'mode' => 'contains'));
      }

      $flds = array(
         'id' => 'ID',
         'name' => $o_report->get_fd_message('column_group'),
         'description' => $o_report->get_fd_message('column_description'),
         'active_view' => $o_report->get_fd_message('column_status'),
         'users' => $o_report->get_fd_message('column_users'),
         'ops' => $o_report->get_fd_message('column_actions'),
      );

      $o_report->_rflds = $flds;
      $o_report->_rrows = $rrows;
      $o_report->_rpos = $rpos;
      $o_report->_count_all = $db->count($this->dd_group);
      $o_report->_rcount = $db->count($this->dd_group, $where);
      $rows = $db->select($this->dd_group, $where, array('id', 'name', 'description', 'active'), $rsort, $rdesc, '', $rrows, $rpos, 0);
      $o_report->_rdata = $this->decorate_groups($rows, $o_report);

      return $o_report->run();
   }

   private function edit_group($rid = 'new') {
      $db = dbx()->get_system_obj('dbxDB');
      $is_new = ($rid === 'new' || !$rid);
      $data = array('active' => 1);
      if (!$is_new) {
         $record = $db->select1($this->dd_group, (int)$rid, '*', 0);
         if (is_array($record)) {
            $data = array_merge($data, $record);
         }
      }

      $o_form = dbx()->get_system_obj('dbxForm');
      $o_form->init('form-admin-group', 'form-admin-group');
      $o_form->set_field_definition('dbxAdmin|rpt-admin-groups-selection');
      $o_form->load_fd_messages();
      $o_form->add_module_bar(
         $o_form->get_fd_message(
            $is_new ? 'form_new_title' : 'form_edit_title'
         ),
         'bi-people',
         $o_form->get_fd_message(
            $is_new ? 'form_new_subtitle' : 'form_edit_subtitle'
         ),
         true
      );
      $action_url = $this->url('edit_group', array('rid' => ($is_new ? 'new' : (int)$rid)));
      $o_form->add_module_bar_form_actions(array(
         'save'       => true,
         'reload'     => true,
         'reload_url' => $action_url,
         'delete'     => !$is_new,
         'delete_url' => $is_new ? '' : $this->action_url('list_groups', array('dbx_do' => 'row_delete', 'rid' => (int)$rid)),
         'delete_title' => $o_form->get_fd_message('action_delete'),
      ));
      $o_form->add_obj('bar_actions', 'obj-value',
         '<a class="btn btn-outline-secondary btn-sm" href="' . dbx()->esc($this->url('list_groups')) . '" title="' . dbx()->esc($o_form->get_fd_message('group_list_title')) . '"><i class="bi bi-list-ul"></i></a>'
      );
      $o_form->set_data_definition($this->dd_group);
      $o_form->_fld_id = 'id';
      $o_form->set_data($data);
      $o_form->_msg_info = $o_form->get_fd_message(
         $is_new ? 'form_new_info' : 'form_edit_info'
      );
      $o_form->set_action($this->url('edit_group', array('rid' => ($is_new ? 'new' : (int)$rid))));

      $o_form->add_fld('name', 'text-label', $o_form->get_fd_message('field_group'), 'parameter|min=2|max=255');
      $o_form->add_fld('description', 'textarea-label', $o_form->get_fd_message('field_description'), 'parameter|max=1000', data: array('rows' => 5));
      $o_form->add_fld('active', 'select-single-label', $o_form->get_fd_message('field_active'), 'int', options: array(
         '1' => $o_form->get_fd_message('option_active'),
         '0' => $o_form->get_fd_message('option_inactive'),
      ));

      if ($o_form->submit()) {
         if (!$o_form->errors()) {
            $values = array(
               'name' => $o_form->get_post('name', '', 'parameter|max=255'),
               'description' => $o_form->get_post('description', '', 'parameter|max=1000'),
               'active' => $o_form->get_post('active', 0, 'int'),
            );
            $ok = $db->save($this->dd_group, $values, $is_new ? 0 : (int)$rid);
            if ($ok) {
               $o_form->_msg_success = $o_form->get_fd_message(
                  'group_save_success'
               );
            } else {
               $o_form->_msg_error = $o_form->get_fd_message(
                  'group_save_error'
               );
            }
         } else {
            $o_form->_msg_error = $o_form->get_fd_message(
               'validation_error'
            );
         }
      }

      return $o_form->run();
   }

   public function run($action = '') {
      $run = dbx()->get_modul_var('dbx_run2', 'list_user', 'parameter');

      switch ($run) {
         case '':
         case 'list_user':
            return $this->report_users();

         case 'edit_user':
            return $this->edit_user(dbx()->get_modul_var('rid', 'new', 'parameter'));

         case 'list_groups':
         case 'groups':
            return $this->report_groups();

         case 'edit_group':
            return $this->edit_group(dbx()->get_modul_var('rid', 'new', 'parameter'));
      }

      $o_tpl = dbx()->get_system_obj('dbxTPL');
      return $o_tpl->get_tpl('dbx|alert-warning', array('msg' => 'User Admin Work (' . dbx()->esc($run) . ') ist undefiniert.'));
   }
}
?>
