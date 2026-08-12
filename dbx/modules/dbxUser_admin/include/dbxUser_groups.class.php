<?php
namespace dbx\dbxUser_admin;
dbx()->use_system_class('dbxForm');

Class dbxUser_groups extends \dbxObj {

   private $dd = 'dbxUser_groups';
   private $texts;

   private function texts() {
      if ($this->texts) {
         return $this->texts;
      }
      $texts = new \dbxForm();
      $texts->init('dbxUser_groups_texts');
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
      $oDB = dbx()->get_system_obj('dbxDB');
      $search = dbx()->get_request_var('dbx_search', '', 'sqlsearch|max=128');
      $where = $oDB->build_search_where($this->dd, $search, array('name', 'description'), array('id'), 'contains');
      $rows = $oDB->select($this->dd, $where, array('id', 'name', 'description', 'active', 'update_date'), 'name', 'ASC', '', 500, 0);
      if (!is_array($rows)) {
         $rows = array();
      }
      dbx()->json_response(array('ok' => 1, 'count' => count($rows), 'rows' => array_values($rows), 'server_time' => date('Y-m-d H:i:s')));
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
         $data = $this->normalize_row($row);
         if (!$data) {
            continue;
         }
         $ok = $oDB->update($this->dd, $data, $id);
         if ($ok >= 0) {
            $saved[] = $oDB->select1($this->dd, $id);
         }
      }

      dbx()->json_response(array('ok' => 1, 'success' => true, 'rows' => $saved));
   }

   private function grid_insert() {
      $oDB = dbx()->get_system_obj('dbxDB');
      $data = $this->normalize_row(array(), true);
      $id = ($oDB->insert($this->dd, $data) === 1) ? $oDB->get_insert_id() : 0;
      if ($id > 0) {
         dbx()->json_response(array('ok' => 1, 'success' => true, 'row' => $oDB->select1($this->dd, $id)));
      }
      dbx()->json_response(array('ok' => 0, 'success' => false, 'msg' => $this->texts()->get_fd_message('group_create_error')));
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

   private function report_user_groups() {
      $texts = $this->texts();
      $oReport = dbx()->get_system_obj('dbxReport');
      $oDB = dbx()->get_system_obj('dbxDB');
      $all = $oDB->count($this->dd);
      $active = $oDB->count($this->dd, 'active = 1');

      $oReport->init('report-groups-grid', 'group-admin-grid');
      $oReport->_fd = 'dbxUser_admin|user-admin';
      $oReport->load_fd_messages();
      $oReport->set_form_help_enabled(false);
      $oReport->add_rep('shell_panel_class', 'dbx-grid dbx-user-groups dbx-ajax-root');
      $oReport->add_rep('bar_title', $texts->get_fd_message('groups_title'));
      $oReport->add_rep('bar_subtitle', $texts->get_fd_message('groups_subtitle'));
      $oReport->_mode = 'tabulurator';
      $oReport->_rrows = 520;
      $oReport->_grid_id = 'dbxUser_groups_grid';
      $oReport->_grid_cols = $this->grid_cols();
      $oReport->_grid_layout = 'fitDataStretch';
      $oReport->_grid_read_url   = $this->base_url('group_grid_read');
      $oReport->_grid_save_url   = $this->base_url('group_grid_save');
      $oReport->_grid_insert_url = $this->base_url('group_grid_insert');
      $oReport->_grid_delete_url = $this->base_url('group_grid_delete');
      $oReport->add_grid_stats(array(
         array('label' => $texts->get_fd_message('stats_groups'), 'value' => (string)$all),
         array('label' => $texts->get_fd_message('status_active'), 'value' => (string)$active, 'tone' => 'ok'),
      ), $texts->get_fd_message('groups_stats'));
      $oReport->add_obj('users_url', 'obj-value', $this->base_url('list_user'));
      $oReport->add_obj('bar_actions', 'obj-value',
         '<a class="btn btn-outline-secondary btn-sm" href="' . dbx()->esc($this->base_url('list_user')) . '" title="' . dbx()->esc($texts->get_fd_message('action_user_list')) . '"><i class="bi bi-person-lines-fill"></i></a>'
      );

      return $oReport->run();
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
      $isNew = ($rid <= 0);

      $db = dbx()->get_system_obj('dbxDB');
      $data = array('active' => 1);
      if (!$isNew) {
         $record = $db->select1($this->dd, $rid, '*', 0);
         if (!is_array($record)) {
            return '<div class="alert alert-warning">' . dbx()->esc($texts->get_fd_message('group_not_found')) . '</div>';
         }
         $data = $record;
      }

      $actionUrl = $isNew
         ? $this->base_url('new_group')
         : $this->base_url('edit_group', array('rid' => $rid));

      $oForm = dbx()->get_system_obj('dbxForm');
      $oForm->init('form-group');
      $oForm->_fd = 'dbxUser_admin|user-admin';
      $oForm->load_fd_messages();
      $oForm->prepare_form_shell(array('class' => 'dbx-user-group-form'));
      $oForm->_dd = $this->dd;
      $oForm->_fld_id = 'id';
      $oForm->_data = $data;
      $oForm->_msg_info = $texts->get_fd_message($isNew ? 'group_create_info' : 'group_edit_info');
      $oForm->_action = $actionUrl;
      if (!$isNew) {
         $oForm->set_activ_id($rid);
         $oForm->_rid = $rid;
      }

      $oForm->add_module_bar(
         $texts->get_fd_message($isNew ? 'group_create_title' : 'group_edit_title'),
         'bi-people',
         $texts->get_fd_message('group_form_subtitle')
      );

      $formActions = array(
         'save'       => true,
         'reload'     => true,
         'reload_url' => $actionUrl,
         'delete'     => !$isNew,
      );
      if (!$isNew) {
         $formActions['delete_url'] = $this->base_url('delete_group', array('rid' => $rid));
         $formActions['delete_title'] = $texts->get_fd_message('group_delete_title');
      }
      $oForm->add_module_bar_form_actions($formActions);

      $oForm->add_obj('bar_actions', 'obj-value',
         '<a class="btn btn-outline-secondary btn-sm" href="' . dbx()->esc($this->base_url('list_groups')) . '" title="' . dbx()->esc($texts->get_fd_message('action_group_list')) . '"><i class="bi bi-list-ul"></i></a>'
      );

      $oForm->add_fld('name', 'text-label', $texts->get_fd_message('label_group'), 'parameter|min=2|max=255');
      $oForm->add_fld('description', 'textarea-label', $texts->get_fd_message('label_description'), 'parameter|max=1000', data: array('rows' => 4));
      $oForm->add_fld('active', 'checkbox-label', $texts->get_fd_message('status_active'), 'int');

      if ($oForm->submit()) {
         if (!$oForm->errors() && !$oForm->warnings()) {
            if ($isNew) {
               $values = array(
                  'name'        => $oForm->get_post('name', '', 'parameter|max=255'),
                  'description' => $oForm->get_post('description', '', 'parameter|max=1000'),
                  'active'      => $oForm->get_post('active', 1, 'int'),
               );
               $ok = $db->save($this->dd, $values, 0);
               if ($ok) {
                  $newId = (int)$db->get_insert_id();
                  if ($newId > 0) {
                     return dbx()->redirect($this->base_url('edit_group', array('rid' => $newId)), 1);
                  }
                  $oForm->_msg_success = $texts->get_fd_message('group_created');
               } else {
                  $oForm->_msg_error = $texts->get_fd_message('group_create_error');
               }
            } else {
               $change = $oForm->changed();
               if ($change) {
                  $ok = $oForm->save_post($this->dd, $rid);
                  $oForm->_msg_success = $texts->get_fd_message($ok ? 'group_saved' : 'group_save_error');
               } else {
                  $oForm->_msg_success = $texts->get_fd_message('no_change');
               }
            }
         } else {
            $oForm->_msg_error = $texts->get_fd_message('check_input');
         }
      }

      return $oForm->run();
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

      return 'Modul dbxUser_admin action('.dbx()->html($action).') not defined';
   }
}

?>
