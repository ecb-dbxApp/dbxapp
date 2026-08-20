<?php
namespace dbx\dbxUser_admin;
dbx()->get_system_obj('dbxReport', 'use');
require_once __DIR__ . '/dbxReport_User.class.php';
require_once __DIR__ . '/dbxUserAdminGrid.trait.php';

Class user_list {

   use dbxUserAdminGridTrait;

   private $dd = 'dbxUser';
   private $texts;

   private function texts() {
      if ($this->texts) {
         return $this->texts;
      }
      $texts = new \dbxForm();
      $texts->init('dbxUser_admin_texts');
      $texts->set_field_definition('dbxUser_admin|user-admin');
      $texts->load_fd_messages();
      $texts->set_form_help_enabled(false);
      $this->texts = $texts;
      return $this->texts;
   }

   private function avatar_file($file) {
      $file = trim((string) $file);
      if ($file === '' || !preg_match('/^[A-Za-z0-9_.-]+\.(png|jpg|jpeg|webp|gif)$/i', $file)) {
         return 'avatar-0.png';
      }

      $path = dbx()->get_base_dir() . 'dbx/modules/dbxUser/img/avatar/' . $file;
      return is_file($path) ? $file : 'avatar-0.png';
   }

   private function status_label($status) {
      $status = (string) $status;
      if ($status === '1') return $this->texts()->get_fd_message('status_active');
      if ($status === '2') return $this->texts()->get_fd_message('status_locked');
      if ($status === '3') return $this->texts()->get_fd_message('status_archive');
      return $this->texts()->get_fd_message('status_new');
   }

   private function user_row($row) {
      $row = is_array($row) ? $row : array();
      $avatar = $this->avatar_file($row['avatar'] ?? '');
      $name = trim((string)($row['name'] ?? '') . ' ' . (string)($row['name2'] ?? ''));

      $row['avatar'] = $avatar;
      $row['avatar_img'] = dbx()->get_base_url() . 'dbx/modules/dbxUser/img/avatar/' . $avatar;
      $row['display_name'] = $name !== '' ? $name : (string)($row['uname'] ?? '');
      $row['status_label'] = $this->status_label($row['status'] ?? 0);
      $row['profile_link'] = $this->base_url('edit_profil', array('rid' => (int)($row['id'] ?? 0)));

      return $row;
   }

   private function allowed_grid_fields() {
      return array(
         'uname', 'name', 'name2', 'email', 'roles', 'status', 'language',
         'telefon', 'handy', 'strasse', 'plz', 'ort', 'land', 'BSNR', 'LANR',
         'emailbill', 'is_confirm', 'login_pid', 'logout_pid', 'design', 'color'
      );
   }

   private function normalize_grid_row($row, $is_new = false) {
      $allowed = array_flip($this->allowed_grid_fields());
      $out = array();

      foreach ((array) $row as $key => $value) {
         if (!isset($allowed[$key])) {
            continue;
         }
         if (is_array($value)) {
            $value = implode(',', $value);
         }
         $out[$key] = trim((string) $value);
      }

      foreach (array('status', 'is_confirm') as $int_field) {
         if (isset($out[$int_field])) {
            $out[$int_field] = (int) $out[$int_field];
         }
      }

      if ($is_new) {
         if (empty($out['uname'])) {
            $out['uname'] = 'neuer.benutzer.' . date('YmdHis');
         }
         if (empty($out['status'])) {
            $out['status'] = 1;
         }
         if (empty($out['language'])) {
            $out['language'] = 'de';
         }
         if (empty($out['name'])) {
            $out['name'] = 'Neuer';
         }
         if (empty($out['name2'])) {
            $out['name2'] = 'Benutzer';
         }
         $out['pass'] = md5(bin2hex(random_bytes(8)));
      }

      return $out;
   }

   private function grid_cols() {
      $texts = $this->texts();
      $status_values = '0=' . $texts->get_fd_message('status_new')
         . '~1=' . $texts->get_fd_message('status_active')
         . '~2=' . $texts->get_fd_message('status_locked')
         . '~3=' . $texts->get_fd_message('status_archive');
      $language_values = 'de=' . $texts->get_fd_message('language_de')
         . '~en=' . $texts->get_fd_message('language_en')
         . '~es=' . $texts->get_fd_message('language_es');
      $confirm_values = '0=' . $texts->get_fd_message('no')
         . '~1=' . $texts->get_fd_message('yes');
      return implode(',', array(
         'id[' . $texts->get_fd_message('label_id') . ']:number:p:width=72;hozAlign=center;headerHozAlign=center',
         'avatar_img[' . $texts->get_fd_message('label_avatar') . ']:image:p:width=78;imgWidth=38px;imgHeight=38px',
         'uname[' . $texts->get_fd_message('label_login') . ']:text::width=160',
         'display_name[' . $texts->get_fd_message('label_name') . ']:text:p:width=210',
         'name[' . $texts->get_fd_message('label_first_name') . ']:text::width=150',
         'name2[' . $texts->get_fd_message('label_last_name') . ']:text::width=150',
         'email[' . $texts->get_fd_message('label_email') . ']:text::width=230',
         'roles[' . $texts->get_fd_message('label_groups') . ']:text::width=180',
         'status[' . $texts->get_fd_message('label_status') . ']:text::editor=list;values=' . $status_values . ';width=110',
         'language[' . $texts->get_fd_message('label_language') . ']:text::editor=list;values=' . $language_values . ';width=105',
         'telefon[' . $texts->get_fd_message('label_phone') . ']:text::width=140',
         'handy[' . $texts->get_fd_message('label_mobile') . ']:text::width=140',
         'plz[' . $texts->get_fd_message('label_zip') . ']:text::width=90',
         'ort[' . $texts->get_fd_message('label_city') . ']:text::width=150',
         'lastvisit[' . $texts->get_fd_message('label_last_login') . ']:text:p:width=170',
         'is_confirm[' . $texts->get_fd_message('label_confirmed_short') . ']:text::editor=list;values=' . $confirm_values . ';width=90'
      ));
   }

   private function grid_read() {
      $o_db = dbx()->get_system_obj('dbxDB');
      $search = dbx()->get_request_var('dbx_search', '', 'sqlsearch|max=128');
      $where = $o_db->build_search_where($this->dd, $search, array('uname', 'name', 'name2', 'email', 'roles', 'ort'), array('id'), 'contains');
      $sort = dbx()->get_request_var('dbx_sorters', '', '*');
      $rsort = 'id';
      $rdesc = 'ASC';

      if ($sort) {
         $sorters = json_decode($sort, true);
         if (is_array($sorters) && isset($sorters[0]['field'])) {
            $field = (string) $sorters[0]['field'];
            if (in_array($field, array('id', 'uname', 'name', 'name2', 'email', 'status', 'lastvisit'), true)) {
               $rsort = $field;
               $rdesc = strtolower((string)($sorters[0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
            }
         }
      }

      $flds = array('id', 'uname', 'name', 'name2', 'email', 'roles', 'status', 'language', 'telefon', 'handy', 'plz', 'ort', 'lastvisit', 'is_confirm', 'avatar');
      $rows = $o_db->select($this->dd, $where, $flds, $rsort, $rdesc, '', 1000, 0);
      if (!is_array($rows)) {
         $rows = array();
      }

      $out = array();
      foreach ($rows as $row) {
         $out[] = $this->user_row($row);
      }

      dbx()->json_response(array(
         'ok' => 1,
         'count' => count($out),
         'rows' => $out,
         'server_time' => date('Y-m-d H:i:s')
      ));
   }

   private function grid_save() {
      $o_db = dbx()->get_system_obj('dbxDB');
      $payload = $this->request_json();
      $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : array();
      $saved = array();

      foreach ($rows as $row) {
         $id = (int)($row['id'] ?? 0);
         if ($id <= 0) {
            continue;
         }
         $data = $this->normalize_grid_row($row);
         if (!$data) {
            continue;
         }
         $ok = $o_db->update($this->dd, $data, $id);
         if ($ok >= 0) {
            $saved[] = $this->user_row($o_db->select1($this->dd, $id));
         }
      }

      dbx()->json_response(array('ok' => 1, 'success' => true, 'rows' => $saved));
   }

   private function grid_insert() {
      $o_db = dbx()->get_system_obj('dbxDB');
      $data = $this->normalize_grid_row(array(), true);
      $id = ($o_db->insert($this->dd, $data) === 1) ? $o_db->get_insert_id() : 0;

      if ($id > 0) {
         $row = $o_db->select1($this->dd, $id);
         dbx()->json_response(array('ok' => 1, 'success' => true, 'row' => $this->user_row($row)));
      }

      dbx()->json_response(array('ok' => 0, 'success' => false, 'msg' => $this->texts()->get_fd_message('user_create_error')));
   }

   public function edit($rid=0) {
      $obj=dbx()->get_include_obj('dbxUser_view');
      $content=$obj->run();
      return $content;
   }

   public function list() {
      $texts = $this->texts();
      $o_report = dbx()->get_system_obj('dbxReport');
      $o_db     = dbx()->get_system_obj('dbxDB');
      $all     = $o_db->count($this->dd);
      $active  = $o_db->count($this->dd, 'status = 1');
      $locked  = $o_db->count($this->dd, 'status = 2');

      $o_report->init('report-user-grid', 'user-admin-grid');
      $o_report->set_field_definition('dbxUser_admin|user-admin');
      $o_report->load_fd_messages();
      $o_report->set_form_help_enabled(false);
      $o_report->add_rep('shell_panel_class', 'dbx-grid dbx-user-admin dbx-ajax-root');
      $o_report->add_rep('bar_title', $texts->get_fd_message('users_title'));
      $o_report->add_rep('bar_subtitle', $texts->get_fd_message('users_subtitle'));
      $o_report->set_mode('tabulator');
      $o_report->_rrows = 620;
      $o_report->_grid_id = 'dbx_user_admin_grid';
      $o_report->_grid_cols = $this->grid_cols();
      $o_report->_grid_layout = 'fitDataStretch';
      $o_report->_grid_read_url   = $this->base_url('user_grid_read');
      $o_report->_grid_save_url   = $this->base_url('user_grid_save');
      $o_report->_grid_insert_url = $this->base_url('user_grid_insert');
      $o_report->_grid_delete_url = $this->base_url('user_grid_delete');
      $o_report->add_grid_stats(array(
         array('label' => $texts->get_fd_message('stats_total'), 'value' => (string)$all),
         array('label' => $texts->get_fd_message('status_active'), 'value' => (string)$active, 'tone' => 'ok'),
         array('label' => $texts->get_fd_message('status_locked'), 'value' => (string)$locked, 'tone' => 'lock'),
      ), $texts->get_fd_message('users_stats'));
      $o_report->add_obj('new_user_url', 'obj-value', $this->base_url('new_user'));
      $o_report->add_obj('groups_url', 'obj-value', $this->base_url('list_groups'));
      $o_report->add_obj('bar_actions', 'obj-value',
         '<a class="btn btn-primary btn-sm dbx-win" href="' . dbx()->esc($this->base_url('new_user')) . '" data-url="' . dbx()->esc($this->base_url('new_user')) . '" data-title="' . dbx()->esc($texts->get_fd_message('profile_title')) . '" title="' . dbx()->esc($texts->get_fd_message('action_new_user')) . '"><i class="bi bi-person-plus"></i></a>'
         . '<a class="btn btn-outline-secondary btn-sm" href="' . dbx()->esc($this->base_url('list_groups')) . '" title="' . dbx()->esc($texts->get_fd_message('action_manage_groups')) . '"><i class="bi bi-people"></i></a>'
      );

      return $o_report->run();
   }

   public function run() {
      $work = dbx()->get_modul_var('dbx_run2', 'list_user', 'parameter');
      $content = '';

      switch ($work) {
         case 'user_grid_read':
            $this->grid_read();
            break;
         case 'user_grid_save':
            $this->grid_save();
            break;
         case 'user_grid_insert':
            $this->grid_insert();
            break;
         case 'user_grid_delete':
            $this->grid_delete();
            break;
         default:
            $content=$this->list();
            break;
      }

      return $content;
   }
}

?>
