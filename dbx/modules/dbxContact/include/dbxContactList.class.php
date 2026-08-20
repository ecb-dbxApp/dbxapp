<?php
namespace dbx\dbxContact;

require_once __DIR__ . '/dbxContactTicket.class.php';
require_once __DIR__ . '/dbxContactPresentation.class.php';

class dbxContactList {

   private function db() {
      return dbx()->get_system_obj('dbxDB');
   }

   private function tpl() {
      return dbx()->get_system_obj('dbxTPL');
   }

   private function h($value): string {
      return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
   }

   private function denied(): string {
      $form = dbx()->get_system_obj('dbxForm');
      $form->init('contact-list-messages', 'dbx|form');
      $form->load_fd_messages('dbxContact|contact-list');
      return $this->tpl()->get_tpl('dbx|alert-warning', array(
         'msg' => $form->get_fd_message('login_required'),
      ));
   }

   private function ticket_for_user(int $rid, int $uid): array {
      $ticket = dbxContactTicket::ticket($this->db(), $rid);
      if (!$ticket || !dbxContactTicket::user_owns($ticket, $uid) || (int) ($ticket['user_hidden'] ?? 0) === 1) {
         return array();
      }
      return $ticket;
   }

   private function excerpt($value, int $max = 120): string {
      $value = trim((string) preg_replace('/\s+/', ' ', (string) $value));
      if (strlen($value) > $max) {
         $value = substr($value, 0, $max - 3) . '...';
      }
      return $this->h($value);
   }

   private function decorate_rows(array $rows, $report): array {
      $out = array();
      foreach ($rows as $row) {
         $status = dbxContactTicket::normalize_status((string) ($row['status'] ?? 'open'));
         $status_label = $report->get_fd_message(
            'status_' . $status,
            $status
         );
         $row['status_view'] = '<span class="badge ' . dbxContactPresentation::status_class($status) . '">' . $this->h($status_label) . '</span>';
         $row['subject_view'] = $this->excerpt($row['subject'] ?? '', 90);
         $row['action'] = $this->tpl()->get_tpl('dbxContact|contact-ticket-row-action', array(
            'rid' => (int) ($row['id'] ?? 0),
         ));
         $out[] = $row;
      }
      return $out;
   }

   public function contact_list_report_row_action_data($report, $data): array {
      if (!is_array($data) || (string) ($data['type'] ?? '') !== 'delete') {
         return $data;
      }

      $rid = (int) ($data['record']['id'] ?? $data['data']['rid'] ?? 0);
      $data['data']['action'] = '?dbx_modul=dbxContact&dbx_run1=my&dbx_run2=delete&rid=' . $rid;
      $data['data']['tooltip'] = $report->get_fd_message(
         'delete_tooltip'
      );

      return $data;
   }

   private function list_tickets(int $uid): string {
      $db = $this->db();
      $report = dbx()->get_system_obj('dbxReport');
      $where = 'uid = ' . $uid . ' AND user_hidden = 0';

      $report->init('contact-list', 'dbxContact|contact-list');
      $report->set_field_definition('dbxContact|contact-list');
      $report->load_fd_messages();
      $report->add_rep(
         'bar_title',
         $report->get_fd_message('bar_title')
      );
      $report->add_rep('bar_icon', 'bi-life-preserver');
      $report->add_rep(
         'bar_subtitle',
         $report->get_fd_message('bar_subtitle')
      );
      $report->add_rep(
         'bar_actions',
         $this->tpl()->get_tpl('dbxContact|contact-new-request-button')
      );
      $report->set_data_definition(dbxContactTicket::DD_TICKET);
      $report->set_action('?dbx_modul=dbxContact&dbx_run1=my');
      $report->_pages = true;
      $report->_create_row_select = false;
      $report->_create_row_edit = false;
      $report->_create_row_delete = true;
      $report->_table_buttons = 'left';
      $report->_msg_confirm_delete = $report->get_fd_message(
         'delete_confirm'
      );
      $report->set_callback_owner($this);
      $report->set_callback('row_action_data', 'contact_list_report_row_action_data');
      $report->set_table_tpl('tpl_row_delete', 'modul|contact-ticket-row-delete');
      $report->_rflds = array(
         'id' => $report->get_fd_message('column_ticket'),
         'last_activity_date' => $report->get_fd_message(
            'column_activity'
         ),
         'status_view' => $report->get_fd_message('column_status'),
         'subject_view' => $report->get_fd_message('column_subject'),
         'action' => '&nbsp;',
      );
      $report->_rpt_format = array(
         'last_activity_date' => 'php-datetime-usr',
         'status_view' => 'html',
         'subject_view' => 'html',
         'action' => 'html',
      );
      $report->_rrows = 30;
      $report->_rpos = 0;
      $report->_count_all = $db->count(dbxContactTicket::DD_TICKET, $where);
      $report->_rcount = $report->_count_all;
      $rows = $db->select(
         dbxContactTicket::DD_TICKET,
         $where,
         array('id', 'last_activity_date', 'create_date', 'status', 'subject'),
         'last_activity_date,id',
         'DESC',
         '',
         30,
         0,
         0
      );
      $report->_rdata = $this->decorate_rows(
         is_array($rows) ? $rows : array(),
         $report
      );

      return $report->run();
   }

   private function timeline(array $ticket, array $messages): string {
      $db = $this->db();
      dbxContactTicket::ensure_initial_message($db, $ticket);
      $html = '';

      foreach (dbxContactTicket::messages($db, (int) $ticket['id'], false) as $message) {
         $author_type = (string) ($message['author_type'] ?? 'system');
         $status_to = trim((string) ($message['status_to'] ?? ''));
         $html .= $this->tpl()->get_tpl('dbxContact|contact-ticket-message', array(
            'message_class' => $author_type === 'admin' ? 'border-primary bg-primary-subtle' : 'bg-body-tertiary',
            'message_icon' => $author_type === 'admin' ? 'bi-headset' : ($author_type === 'requester' ? 'bi-person' : 'bi-gear'),
            'author_label' => $messages[
               $author_type === 'admin'
                  ? 'author_admin'
                  : ($author_type === 'requester'
                     ? 'author_requester'
                     : 'author_system')
            ] ?? $author_type,
            'create_date' => $this->h($message['create_date'] ?? ''),
            'body' => nl2br($this->h($message['body'] ?? '')),
            'status_badge' => $status_to !== ''
               ? '<span class="badge ' . dbxContactPresentation::status_class($status_to) . '">'
                  . $this->h($messages['status_prefix'] ?? 'Status:')
                  . ' '
                  . $this->h(
                     $messages['status_' . $status_to] ?? $status_to
                  )
                  . '</span>'
               : '',
         ));
      }

      return $html !== ''
         ? $html
         : '<div class="text-muted">'
            . $this->h($messages['no_timeline'] ?? '')
            . '</div>';
   }

   private function message_form(array $ticket): string {
      $rid = (int) $ticket['id'];
      $form = dbx()->get_system_obj('dbxForm');
      $form->init('contact-user-message', 'contact-user-message-form');
      $form->set_data_source(dbxContactTicket::DD_MESSAGE, 'dbxContact|contact-user-message');
      $form->load_fd_messages();
      $form->prepare_form_shell(array(
         'form_attrs' => 'data-target="dbx_contact_ticket_' . $rid . '"',
      ));
      if ((string) ($ticket['status'] ?? '') === 'closed') {
         return '<div class="alert alert-secondary mb-0">'
            . $this->h($form->get_fd_message('ticket_closed'))
            . '</div>';
      }

      $form->set_action('?dbx_modul=dbxContact&dbx_run1=my&dbx_run2=ticket&rid=' . $rid);
      $form->set_data(array('body' => ''));
      $form->add_module_bar(
         $form->get_fd_message('bar_title'),
         'bi-chat-dots',
         $form->get_fd_message('bar_subtitle')
      );
      $form->add_rep('bar_title_pre', '');
      $form->add_rep('bar_title_heading_attrs', '');
      $form->add_rep('bar_middle', '');
      $form->add_rep('bar_actions', '');
      $form->add_rep('bar_extra', '');
      $form->add_rep('rid', $rid);
      $form->_msg_info = $form->get_fd_message('form_info');
      $form->_msg_error = $form->get_fd_message('validation_error');
      $form->add_fld('body');

      if ($form->submit() && !$form->errors()) {
         $body = trim((string) $form->get_post_data('body', '', '*'));
         $old_status = dbxContactTicket::normalize_status((string) ($ticket['status'] ?? 'open'));
         $message_id = dbxContactTicket::add_message($this->db(), $rid, array(
            'author_uid' => (int) dbx()->user(),
            'author_type' => 'requester',
            'message_type' => 'message',
            'visibility' => 'public',
            'body' => $body,
            'status_from' => $old_status,
            'status_to' => 'open',
         ));
         if ($message_id > 0) {
            dbxContactTicket::touch($this->db(), $rid, array('status' => 'open', 'closed_date' => ''));
            $form->_msg_success = $form->get_fd_message(
               'message_success'
            );
            $form->set_data_value('body', '');
         } else {
            $form->_msg_error = $form->get_fd_message('message_error');
         }
      }

      return $form->run();
   }

   private function ticket(int $uid): string {
      $rid = (int) dbx()->get_modul_var('rid', 0, 'int');
      $view = dbx()->get_system_obj('dbxForm');
      $view->init('contact-ticket-messages', 'dbx|form');
      $messages = $view->load_fd_messages('dbxContact|contact-list');
      $ticket = $this->ticket_for_user($rid, $uid);
      if (!$ticket) {
         return $this->tpl()->get_tpl('dbx|alert-warning', array(
            'msg' => $view->get_fd_message('ticket_not_found'),
         ));
      }

      $message_form = $this->message_form($ticket);
      $ticket = $this->ticket_for_user($rid, $uid);
      $status = dbxContactTicket::normalize_status((string) ($ticket['status'] ?? 'open'));

      return $this->tpl()->get_tpl('dbxContact|contact-ticket-detail', array(
         'bar_class' => 'dbx-bar--module',
         'bar_title_class' => 'dbx-bar-title',
         'bar_actions_class' => 'dbx-bar-actions',
         'bar_title_pre' => '',
         'bar_title_heading_attrs' => '',
         'bar_icon' => 'bi-life-preserver',
         'bar_title' => $view->format_fd_message(
            'ticket_title',
            array('rid' => $rid)
         ),
         'bar_subtitle' => $view->get_fd_message('ticket_subtitle'),
         'bar_middle' => '',
         'bar_actions' => $this->tpl()->get_tpl(
            'dbxContact|contact-my-requests-button'
         ),
          'bar_extra' => '',
          'rid' => $rid,
          'delete_url' => dbx()->action_url(
             '?dbx_modul=dbxContact&dbx_run1=my&dbx_run2=delete&rid=' . $rid
          ),
          'subject' => $this->h($ticket['subject'] ?? ''),
         'status_label' => $this->h(
            $messages['status_' . $status] ?? $status
         ),
         'status_class' => dbxContactPresentation::status_class($status),
         'create_date' => $this->h($ticket['create_date'] ?? ''),
         'timeline' => $this->timeline($ticket, $messages),
         'message_form' => $message_form,
      ));
   }

   private function delete_ticket(int $uid): string {
      $rid = (int) dbx()->get_modul_var('rid', 0, 'int');
      $ticket = $this->ticket_for_user($rid, $uid);
      if (!$ticket) {
         $view = dbx()->get_system_obj('dbxForm');
         $view->init('contact-delete-messages', 'dbx|form');
         $view->load_fd_messages('dbxContact|contact-list');
         return $this->tpl()->get_tpl('dbx|alert-warning', array(
            'msg' => $view->get_fd_message('ticket_not_found'),
         ));
      }

      $form = dbx()->get_system_obj('dbxForm');
      $form->init('contact-user-delete', 'contact-user-delete-form');
      $form->set_field_definition('dbxContact|contact-user-delete');
      $form->load_fd_messages();
      $form->set_action('?dbx_modul=dbxContact&dbx_run1=my&dbx_run2=delete&rid=' . $rid);
      $form->set_data(array('confirm_delete' => 0));
      $form->add_module_bar(
         $form->get_fd_message('bar_title'),
         'bi-trash'
      );
      $form->add_rep('bar_title_pre', '');
      $form->add_rep('bar_title_heading_attrs', '');
      $form->add_rep('bar_middle', '');
      $form->add_rep('bar_actions', '');
      $form->add_rep('bar_extra', '');
      $form->add_rep('rid', $rid);
      $form->_msg_info = '';
      $form->_msg_error = $form->get_fd_message('validation_error');
      $form->add_fld('confirm_delete');

      if ($form->submit() && !$form->errors()) {
         $ok = $this->db()->update(dbxContactTicket::DD_TICKET, array(
            'user_hidden' => 1,
            'user_hidden_date' => date('Y-m-d H:i:s'),
         ), 'id = ' . $rid . ' AND uid = ' . $uid, 0, 1, 1, 1);
         if ($ok === 1) {
            return $this->tpl()->get_tpl('dbx|alert-success', array(
               'msg' => $form->get_fd_message('delete_success'),
            )) . $this->tpl()->get_tpl(
               'dbxContact|contact-delete-back-button'
            );
         }
         $form->_msg_error = $form->get_fd_message('delete_error');
      }

      return $form->run();
   }

   public function run() {
      $uid = (int) dbx()->user();
      if ($uid <= 0) {
         return $this->denied();
      }

      $run2 = dbx()->get_modul_var('dbx_run2', 'list', 'parameter');
      if ($run2 === 'ticket') {
         return $this->ticket($uid);
      }
      if ($run2 === 'delete') {
         return $this->delete_ticket($uid);
      }
      return $this->list_tickets($uid);
   }
}
