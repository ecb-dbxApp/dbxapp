<?php
namespace dbx\dbxUser_admin;
dbx()->get_system_obj('dbxForm', 'use');
require_once __DIR__ . '/dbxUserAdminGrid.trait.php';

Class dbxUser_groups extends \dbxObj {

   use dbxUserAdminGridTrait;

   private $dd = 'dbxUser_groups';
   private $texts;

   private function texts() {
      if ($this->texts) {
         return $this->texts;
      }
      $texts = new \dbxForm();
      $texts->init('dbxUser_groups_texts');
      $texts->set_field_definition('dbxUser_admin|user-admin');
      $texts->load_fd_messages();
      $texts->set_form_help_enabled(false);
      $this->texts = $texts;
      return $this->texts;
   }

   private function normalize_row($row, $is_new = false) {
      $out = array();
      foreach (array('name', 'description', 'active') as $key) {
         if (array_key_exists($key, (array) $row)) {
            $out[$key] = is_array($row[$key]) ? implode(',', $row[$key]) : trim((string) $row[$key]);
         }
      }
      if (isset($out['active'])) {
         $out['active'] = (int) $out['active'];
      }
      if ($is_new) {
         $out['name'] = $out['name'] ?? 'neue_gruppe_' . date('YmdHis');
         $out['description'] = $out['description'] ?? 'Neue Gruppe';
         $out['active'] = 1;
      }
      return $out;
   }

   private function grid_cols() {
      $texts = $this->texts();
      return implode(',', array(
         'id[' . $texts->get_fd_message('label_id') . ']:number:p:width=72;hozAlign=center;headerHozAlign=center',
         'name[' . $texts->get_fd_message('label_group') . ']:text::width=180',
         'description[' . $texts->get_fd_message('label_description') . ']:text::editor=textarea;width=360',
         'active[' . $texts->get_fd_message('status_active') . ']:text::editor=list;values=0=' . $texts->get_fd_message('no') . '~1=' . $texts->get_fd_message('yes') . ';width=100',
         'update_date[' . $texts->get_fd_message('label_updated') . ']:text:p:width=170'
      ));
   }

   private function grid_read() {
      $o_db = dbx()->get_system_obj('dbxDB');
      $search = dbx()->get_request_var('dbx_search', '', 'sqlsearch|max=128');
      $where = $o_db->build_search_where($this->dd, $search, array('name', 'description'), array('id'), 'contains');
      $rows = $o_db->select($this->dd, $where, array('id', 'name', 'description', 'active', 'update_date'), 'name', 'ASC', '', 500, 0);
      if (!is_array($rows)) {
         $rows = array();
      }
      dbx()->json_response(array('ok' => 1, 'count' => count($rows), 'rows' => array_values($rows), 'server_time' => date('Y-m-d H:i:s')));
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
         $data = $this->normalize_row($row);
         if (!$data) {
            continue;
         }
         $ok = $o_db->update($this->dd, $data, $id);
         if ($ok >= 0) {
            $saved[] = $o_db->select1($this->dd, $id);
         }
      }

      dbx()->json_response(array('ok' => 1, 'success' => true, 'rows' => $saved));
   }

   private function grid_insert() {
      $o_db = dbx()->get_system_obj('dbxDB');
      $data = $this->normalize_row(array(), true);
      $id = ($o_db->insert($this->dd, $data) === 1) ? $o_db->get_insert_id() : 0;
      if ($id > 0) {
         dbx()->json_response(array('ok' => 1, 'success' => true, 'row' => $o_db->select1($this->dd, $id)));
      }
      dbx()->json_response(array('ok' => 0, 'success' => false, 'msg' => $this->texts()->get_fd_message('group_create_error')));
   }

   private function report_user_groups() {
      $texts = $this->texts();
      $o_report = dbx()->get_system_obj('dbxReport');
      $o_db = dbx()->get_system_obj('dbxDB');
      $all = $o_db->count($this->dd);
      $active = $o_db->count($this->dd, 'active = 1');

      $o_report->init('report-groups-grid', 'group-admin-grid');
      $o_report->set_field_definition('dbxUser_admin|user-admin');
      $o_report->load_fd_messages();
      $o_report->set_form_help_enabled(false);
      $o_report->add_rep('shell_panel_class', 'dbx-grid dbx-user-groups dbx-ajax-root');
      $o_report->add_rep('bar_title', $texts->get_fd_message('groups_title'));
      $o_report->add_rep('bar_subtitle', $texts->get_fd_message('groups_subtitle'));
      $o_report->set_mode('tabulator');
      $o_report->_rrows = 520;
      $o_report->_grid_id = 'dbxUser_groups_grid';
      $o_report->_grid_cols = $this->grid_cols();
      $o_report->_grid_layout = 'fitDataStretch';
      $o_report->_grid_read_url   = $this->base_url('group_grid_read');
      $o_report->_grid_save_url   = $this->base_url('group_grid_save');
      $o_report->_grid_insert_url = $this->base_url('group_grid_insert');
      $o_report->_grid_delete_url = $this->base_url('group_grid_delete');
      $o_report->add_grid_stats(array(
         array('label' => $texts->get_fd_message('stats_groups'), 'value' => (string)$all),
         array('label' => $texts->get_fd_message('status_active'), 'value' => (string)$active, 'tone' => 'ok'),
      ), $texts->get_fd_message('groups_stats'));
      $o_report->add_obj('users_url', 'obj-value', $this->base_url('list_user'));
      $o_report->add_obj('bar_actions', 'obj-value',
         '<a class="btn btn-outline-secondary btn-sm" href="' . dbx()->esc($this->base_url('list_user')) . '" title="' . dbx()->esc($texts->get_fd_message('action_user_list')) . '"><i class="bi bi-person-lines-fill"></i></a>'
      );

      return $o_report->run();
   }

   private function delete_user_group($rid = 0) {
      $rid = (int)$rid;
      if ($rid <= 0) {
         $rid = (int)dbx()->get_modul_var('rid', 0);
      }
      if ($rid <= 0) {
         return dbx()->redirect($this->base_url('list_groups'), 1);
      }

      $db = dbx()->get_system_obj('dbxDB');
      $db->delete($this->dd, $rid);
      return dbx()->redirect($this->base_url('list_groups'), 1);
   }

   private function edit_user_group($rid = 0) {
      $content = '';
      $texts = $this->texts();
      $rid = (int)$rid;
      if ($rid <= 0) {
         $rid = (int)dbx()->get_modul_var('rid', 0);
      }
      $is_new = ($rid <= 0);

      $db = dbx()->get_system_obj('dbxDB');
      $data = array('active' => 1);
      if (!$is_new) {
         $record = $db->select1($this->dd, $rid, '*', 0);
         if (!is_array($record)) {
            return '<div class="alert alert-warning">' . dbx()->esc($texts->get_fd_message('group_not_found')) . '</div>';
         }
         $data = $record;
      }

      $action_url = $is_new
         ? $this->base_url('new_group')
         : $this->base_url('edit_group', array('rid' => $rid));

      $o_form = dbx()->get_system_obj('dbxForm');
      $o_form->init('form-group', 'form-group');
      $o_form->set_field_definition('dbxUser_admin|user-admin');
      $o_form->load_fd_messages();
      $o_form->prepare_form_shell(array('class' => 'dbx-user-group-form'));
      $o_form->set_data_definition($this->dd);
      $o_form->_fld_id = 'id';
      $o_form->set_data($data);
      $o_form->_msg_info = $texts->get_fd_message($is_new ? 'group_create_info' : 'group_edit_info');
      $o_form->set_action($action_url);
      if (!$is_new) {
         $o_form->set_activ_id($rid);
         $o_form->set_rid((int)$rid);
      }

      $o_form->add_module_bar(
         $texts->get_fd_message($is_new ? 'group_create_title' : 'group_edit_title'),
         'bi-people',
         $texts->get_fd_message('group_form_subtitle')
      );

      $form_actions = array(
         'save'       => true,
         'reload'     => true,
         'reload_url' => $action_url,
         'delete'     => !$is_new,
      );
      if (!$is_new) {
         $form_actions['delete_url'] = $this->base_url('delete_group', array('rid' => $rid));
         $form_actions['delete_title'] = $texts->get_fd_message('group_delete_title');
      }
      $o_form->add_module_bar_form_actions($form_actions);

      $o_form->add_obj('bar_actions', 'obj-value',
         '<a class="btn btn-outline-secondary btn-sm" href="' . dbx()->esc($this->base_url('list_groups')) . '" title="' . dbx()->esc($texts->get_fd_message('action_group_list')) . '"><i class="bi bi-list-ul"></i></a>'
      );

      $o_form->add_fld('name', 'text-label', $texts->get_fd_message('label_group'), 'parameter|min=2|max=255');
      $o_form->add_fld('description', 'textarea-label', $texts->get_fd_message('label_description'), 'parameter|max=1000', data: array('rows' => 4));
      $o_form->add_fld('active', 'checkbox-label', $texts->get_fd_message('status_active'), 'int');

      if ($o_form->submit()) {
         if (!$o_form->errors() && !$o_form->warnings()) {
            if ($is_new) {
               $values = array(
                  'name'        => $o_form->get_post('name', '', 'parameter|max=255'),
                  'description' => $o_form->get_post('description', '', 'parameter|max=1000'),
                  'active'      => $o_form->get_post('active', 1, 'int'),
               );
               $ok = $db->save($this->dd, $values, 0);
               if ($ok) {
                  $new_id = (int)$db->get_insert_id();
                  if ($new_id > 0) {
                     return dbx()->redirect($this->base_url('edit_group', array('rid' => $new_id)), 1);
                  }
                  $o_form->_msg_success = $texts->get_fd_message('group_created');
               } else {
                  $o_form->_msg_error = $texts->get_fd_message('group_create_error');
               }
            } else {
               $change = $o_form->changed();
               if ($change) {
                  $ok = $o_form->save_post($this->dd, $rid);
                  $o_form->_msg_success = $texts->get_fd_message($ok ? 'group_saved' : 'group_save_error');
               } else {
                  $o_form->_msg_success = $texts->get_fd_message('no_change');
               }
            }
         } else {
            $o_form->_msg_error = $texts->get_fd_message('check_input');
         }
      }

      return $o_form->run();
   }

   public function run($action='list_groups') {
      $work = dbx()->get_request_var('dbx_run2');
      $gid = dbx()->get_request_var('id',0,'int');
      if (!$gid) {
         $gid = dbx()->get_request_var('rid',0,'int');
      }

      switch ($action) {
         case 'group_grid_read':
            $this->grid_read();
            break;
         case 'group_grid_save':
            $this->grid_save();
            break;
         case 'group_grid_insert':
            $this->grid_insert();
            break;
         case 'group_grid_delete':
            $this->grid_delete();
            break;
         case 'list_groups':
            return $this->report_user_groups();
         case 'new_group':
            return $this->edit_user_group(0);
         case 'edit_group':
            return $this->edit_user_group($gid);
         case 'delete_group':
            return $this->delete_user_group($gid);
      }

      return 'Modul dbxUser_admin action('.dbx()->esc($action).') not defined';
   }
}

?>
