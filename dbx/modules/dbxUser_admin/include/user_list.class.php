<?php
namespace dbx\dbxUser_admin;
dbx()->use_system_class('dbxReport');

class dbxReport_User extends \dbxReport {
  public function run_body($content) {
    $this->_record = is_array($this->_record) ? $this->_record : array();
    return $content;
  }
}

Class user_list {

   private $dd = 'dbxUser';
   private $texts;

   private function texts() {
      if ($this->texts) {
         return $this->texts;
      }
      $texts = new \dbxForm();
      $texts->init('dbxUser_admin_texts');
      $texts->_fd = 'dbxUser_admin|user-admin';
      $texts->load_fd_messages();
      $texts->set_form_help_enabled(false);
      $this->texts = $texts;
      return $this->texts;
   }

   private function base_url($run2, $params = array()) {
      return dbx()->append_url_params(
         '?dbx_modul=dbxUser_admin&dbx_run1=user&dbx_run2=' . rawurlencode((string)$run2),
         $params
      );
   }

   private function request_json() {
      return dbx()->get_json_request();
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

      foreach (array('status', 'is_confirm') as $intField) {
         if (isset($out[$intField])) {
            $out[$intField] = (int) $out[$intField];
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
      $statusValues = '0=' . $texts->get_fd_message('status_new')
         . '~1=' . $texts->get_fd_message('status_active')
         . '~2=' . $texts->get_fd_message('status_locked')
         . '~3=' . $texts->get_fd_message('status_archive');
      $languageValues = 'de=' . $texts->get_fd_message('language_de')
         . '~en=' . $texts->get_fd_message('language_en')
         . '~es=' . $texts->get_fd_message('language_es');
      $confirmValues = '0=' . $texts->get_fd_message('no')
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
         'status[' . $texts->get_fd_message('label_status') . ']:text::editor=list;values=' . $statusValues . ';width=110',
         'language[' . $texts->get_fd_message('label_language') . ']:text::editor=list;values=' . $languageValues . ';width=105',
         'telefon[' . $texts->get_fd_message('label_phone') . ']:text::width=140',
         'handy[' . $texts->get_fd_message('label_mobile') . ']:text::width=140',
         'plz[' . $texts->get_fd_message('label_zip') . ']:text::width=90',
         'ort[' . $texts->get_fd_message('label_city') . ']:text::width=150',
         'lastvisit[' . $texts->get_fd_message('label_last_login') . ']:text:p:width=170',
         'is_confirm[' . $texts->get_fd_message('label_confirmed_short') . ']:text::editor=list;values=' . $confirmValues . ';width=90'
      ));
   }

   private function grid_read() {
      $oDB = dbx()->get_system_obj('dbxDB');
      $search = dbx()->get_request_var('dbx_search', '', 'sqlsearch|max=128');
      $where = $oDB->build_search_where($this->dd, $search, array('uname', 'name', 'name2', 'email', 'roles', 'ort'), array('id'), 'contains');
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
      $rows = $oDB->select($this->dd, $where, $flds, $rsort, $rdesc, '', 1000, 0);
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
      $oDB = dbx()->get_system_obj('dbxDB');
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
         $ok = $oDB->update($this->dd, $data, $id);
         if ($ok >= 0) {
            $saved[] = $this->user_row($oDB->select1($this->dd, $id));
         }
      }

      dbx()->json_response(array('ok' => 1, 'success' => true, 'rows' => $saved));
   }

   private function grid_insert() {
      $oDB = dbx()->get_system_obj('dbxDB');
      $data = $this->normalize_grid_row(array(), true);
      $id = ($oDB->insert($this->dd, $data) === 1) ? $oDB->get_insert_id() : 0;

      if ($id > 0) {
         $row = $oDB->select1($this->dd, $id);
         dbx()->json_response(array('ok' => 1, 'success' => true, 'row' => $this->user_row($row)));
      }

      dbx()->json_response(array('ok' => 0, 'success' => false, 'msg' => $this->texts()->get_fd_message('user_create_error')));
   }

   private function grid_delete() {
      $payload = $this->request_json();
      $id = (int)($payload['id'] ?? 0);
      if ($id <= 0) {
         dbx()->json_response(array('ok' => 0, 'success' => false, 'msg' => $this->texts()->get_fd_message('id_missing')));
      }

      $oDB = dbx()->get_system_obj('dbxDB');
      $ok = $oDB->delete($this->dd, $id);
      dbx()->json_response(array('ok' => $ok ? 1 : 0, 'success' => $ok ? true : false));
   }

   public function edit($rid=0) {
      $obj=dbx()->get_include_obj('dbxUser_view');
      $content=$obj->run();
      return $content;
   }

   public function list() {
      $texts = $this->texts();
      $oReport = dbx()->get_system_obj('dbxReport');
      $oDB     = dbx()->get_system_obj('dbxDB');
      $all     = $oDB->count($this->dd);
      $active  = $oDB->count($this->dd, 'status = 1');
      $locked  = $oDB->count($this->dd, 'status = 2');

      $oReport->init('report-user-grid', 'user-admin-grid');
      $oReport->_fd = 'dbxUser_admin|user-admin';
      $oReport->load_fd_messages();
      $oReport->set_form_help_enabled(false);
      $oReport->add_rep('shell_panel_class', 'dbx-grid dbx-user-admin dbx-ajax-root');
      $oReport->add_rep('bar_title', $texts->get_fd_message('users_title'));
      $oReport->add_rep('bar_subtitle', $texts->get_fd_message('users_subtitle'));
      $oReport->_mode = 'tabulurator';
      $oReport->_rrows = 620;
      $oReport->_grid_id = 'dbx_user_admin_grid';
      $oReport->_grid_cols = $this->grid_cols();
      $oReport->_grid_layout = 'fitDataStretch';
      $oReport->_grid_read_url   = $this->base_url('user_grid_read');
      $oReport->_grid_save_url   = $this->base_url('user_grid_save');
      $oReport->_grid_insert_url = $this->base_url('user_grid_insert');
      $oReport->_grid_delete_url = $this->base_url('user_grid_delete');
      $oReport->add_grid_stats(array(
         array('label' => $texts->get_fd_message('stats_total'), 'value' => (string)$all),
         array('label' => $texts->get_fd_message('status_active'), 'value' => (string)$active, 'tone' => 'ok'),
         array('label' => $texts->get_fd_message('status_locked'), 'value' => (string)$locked, 'tone' => 'lock'),
      ), $texts->get_fd_message('users_stats'));
      $oReport->add_obj('new_user_url', 'obj-value', $this->base_url('new_user'));
      $oReport->add_obj('groups_url', 'obj-value', $this->base_url('list_groups'));
      $oReport->add_obj('bar_actions', 'obj-value',
         '<a class="btn btn-primary btn-sm dbx-win" href="' . dbx()->esc($this->base_url('new_user')) . '" data-url="' . dbx()->esc($this->base_url('new_user')) . '" data-title="' . dbx()->esc($texts->get_fd_message('profile_title')) . '" title="' . dbx()->esc($texts->get_fd_message('action_new_user')) . '"><i class="bi bi-person-plus"></i></a>'
         . '<a class="btn btn-outline-secondary btn-sm" href="' . dbx()->esc($this->base_url('list_groups')) . '" title="' . dbx()->esc($texts->get_fd_message('action_manage_groups')) . '"><i class="bi bi-people"></i></a>'
      );

      return $oReport->run();
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
