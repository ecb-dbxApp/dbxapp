<?php
namespace dbx\dbxContact_admin;

require_once dirname(__DIR__, 2) . '/dbxContact/include/dbxContactConfig.class.php';
require_once dirname(__DIR__, 2) . '/dbxContact/include/dbxContactTicket.class.php';
require_once dirname(__DIR__, 2) . '/dbxContact/include/dbxContactContentProvision.class.php';
require_once dirname(__DIR__, 2) . '/dbxContact/include/dbxContactPresentation.class.php';

use dbx\dbxContact\dbxContactConfig;
use dbx\dbxContact\dbxContactContentProvision;
use dbx\dbxContact\dbxContactTicket;
use dbx\dbxContact\dbxContactPresentation;

class dbxContactAdmin {

   private function db() {
      return dbx()->get_system_obj('dbxDB');
   }

   private function tpl() {
      return dbx()->get_system_obj('dbxTPL');
   }

   private function h($value): string {
      return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
   }

   private function alert(string $type, string $message): string {
      return $this->tpl()->get_tpl('dbx|alert-' . $type, array('msg' => $message));
   }

   private function schema_ready(): bool {
      $db = $this->db();
      $server = 'dbxContact|dbxContact.db3';
      if (!$db->connect_db_server($server)) {
         return false;
      }

      if (!$db->get_table_exist($server, 'contact_message', false)) {
         return false;
      }

      return $db->has_table_column($server, 'contact_request', 'priority')
         && $db->has_table_column($server, 'contact_request', 'last_activity_date')
         && $db->has_table_column($server, 'contact_request', 'user_hidden');
   }

   private function priority_class(string $priority): string {
      return array(
         'low' => 'text-bg-secondary',
         'normal' => 'text-bg-primary',
         'high' => 'text-bg-warning',
         'urgent' => 'text-bg-danger',
      )[$priority] ?? 'text-bg-light';
   }

   private function counts(): array {
      $db = $this->db();
      return array(
         'count_open' => max(0, (int) $db->count(dbxContactTicket::DD_TICKET, array('status' => 'open'))),
         'count_progress' => max(0, (int) $db->count(dbxContactTicket::DD_TICKET, array('status' => 'in_progress'))),
         'count_waiting' => max(0, (int) $db->count(dbxContactTicket::DD_TICKET, array('status' => 'waiting_customer'))),
         'count_answered' => max(0, (int) $db->count(dbxContactTicket::DD_TICKET, array('status' => 'answered'))),
         'count_closed' => max(0, (int) $db->count(dbxContactTicket::DD_TICKET, array('status' => 'closed'))),
      );
   }

   private function report_status_stats_html($report): string {
      $counts = $this->counts();
      return ''
         . '<span class="dbx-report-bar-stat ms-3"><span class="badge text-bg-primary">' . $this->h($report->get_fd_message('stat_open')) . ': ' . (int) $counts['count_open'] . '</span></span>'
         . '<span class="dbx-report-bar-stat"><span class="badge text-bg-info">' . $this->h($report->get_fd_message('stat_progress')) . ': ' . (int) $counts['count_progress'] . '</span></span>'
         . '<span class="dbx-report-bar-stat"><span class="badge text-bg-warning">' . $this->h($report->get_fd_message('stat_waiting')) . ': ' . (int) $counts['count_waiting'] . '</span></span>'
         . '<span class="dbx-report-bar-stat"><span class="badge text-bg-success">' . $this->h($report->get_fd_message('stat_answered')) . ': ' . (int) $counts['count_answered'] . '</span></span>'
         . '<span class="dbx-report-bar-stat"><span class="badge text-bg-secondary">' . $this->h($report->get_fd_message('stat_closed')) . ': ' . (int) $counts['count_closed'] . '</span></span>';
   }

   private function frame(string $content, string $title = ''): string {
      $texts = dbx()->get_system_obj('dbxForm');
      $texts->init('contact-admin-texts', 'dbx|form');
      $texts->load_fd_messages('dbxContact_admin|rpt-ticket-selection');
      if ($title === '') {
         $title = $texts->get_fd_message('frame_title');
      }
      $actions = ''
         . '<a class="btn btn-outline-secondary btn-sm" href="?dbx_modul=dbxContact_admin&dbx_run1=install"><i class="bi bi-database-gear"></i> '
         . $this->h($texts->get_fd_message('install'))
         . '</a>'
         . '<a class="btn btn-primary btn-sm" href="?dbx_modul=dbxContact_admin&dbx_run1=list"><i class="bi bi-arrow-clockwise"></i> '
         . $this->h($texts->get_fd_message('refresh'))
         . '</a>';

      return $this->tpl()->get_tpl('dbxContact_admin|ticket-admin-shell', array_merge(
         $this->counts(),
         array(
            'bar_title' => $this->h($title),
            'bar_icon' => 'bi-life-preserver',
            'bar_subtitle' => $this->h(
               $texts->get_fd_message('frame_subtitle')
            ),
            'bar_title_pre' => '',
            'bar_title_heading_attrs' => '',
            'bar_middle' => '',
            'bar_extra' => '',
            'bar_actions' => $actions,
            'content' => $content,
         )
      ));
   }

   private function install(): string {
      $dd = dbx()->get_system_obj('dbxDD');
      $results = array();

      foreach (array('contactMessage') as $name) {
         $dd->sync_dd_to_db('dbxContact', $name, 'reset');
         $state = array();
         for ($i = 0; $i < 100; $i++) {
            $state = $dd->sync_dd_to_db('dbxContact', $name, 'apply');
            if (in_array((string) ($state['status'] ?? ''), array('finished', 'error'), true)) {
               break;
            }
         }
         $results[$name] = $state;
      }

      foreach ($results as $name => $state) {
         if ((string) ($state['status'] ?? '') !== 'finished') {
            return $this->frame($this->alert('danger', 'Installation von ' . $this->h($name) . ' fehlgeschlagen: ' . $this->h($state['message'] ?? 'unbekannt')));
         }
      }

      $name = 'contactRequest';
      $dd->sync_dd_to_db('dbxContact', $name, 'reset');
      for ($i = 0; $i < 100; $i++) {
         $state = $dd->sync_dd_to_db('dbxContact', $name, 'force');
         if (in_array((string) ($state['status'] ?? ''), array('finished', 'error'), true)) {
            break;
         }
      }
      $results[$name] = $state;

      foreach ($results as $name => $state) {
         if ((string) ($state['status'] ?? '') !== 'finished') {
            return $this->frame($this->alert('danger', 'Installation von ' . $this->h($name) . ' fehlgeschlagen: ' . $this->h($state['message'] ?? 'unbekannt')));
         }
      }

      $db = $this->db();
      $rows = $db->select(dbxContactTicket::DD_TICKET, '', '*', 'id', 'ASC', '', 100000, 0, 0);
      foreach ((array) $rows as $ticket) {
         dbxContactTicket::ensure_initial_message($db, $ticket);
         if (trim((string) ($ticket['last_activity_date'] ?? '')) === '') {
            $last = (string) ($ticket['create_date'] ?? date('Y-m-d H:i:s'));
            $db->update(dbxContactTicket::DD_TICKET, array(
               'priority' => dbxContactTicket::normalize_priority((string) ($ticket['priority'] ?? 'normal')),
               'last_activity_date' => $last,
            ), (int) ($ticket['id'] ?? 0), 0, 1, 1, 0);
         }
      }
      $content_page_id = dbxContactContentProvision::run($db);

      return $this->frame(
         $this->alert('success', 'Ticket-Datenmodell installiert.')
         . ($content_page_id > 0
            ? '<div class="alert alert-info mx-3">Die dbxContent-Seite „Meine Anfragen“ ist unter <a href="meine-anfragen">meine-anfragen</a> verfuegbar.</div>'
            : '<div class="alert alert-warning mx-3">Die dbxContent-Seite „Meine Anfragen“ konnte nicht automatisch angelegt werden.</div>')
         . '<div class="p-3"><a class="btn btn-primary" href="?dbx_modul=dbxContact_admin&dbx_run1=list"><i class="bi bi-list-ul"></i> Tickets anzeigen</a></div>'
      );
   }

   private function where_for_report(string $search, string $status, string $priority): string {
      $db = $this->db();
      $server = $db->get_dd_server(dbxContactTicket::DD_TICKET);
      $clauses = array();

      if ($status !== 'all' && array_key_exists($status, dbxContactTicket::statuses())) {
         $clauses[] = "status = '" . $db->escape($status, $server) . "'";
      }
      if ($priority !== 'all' && array_key_exists($priority, dbxContactTicket::priorities())) {
         $clauses[] = "priority = '" . $db->escape($priority, $server) . "'";
      }
      if ($search !== '') {
         $needle = $db->escape($search, $server);
         $clauses[] = "(subject LIKE '%" . $needle . "%' OR name LIKE '%" . $needle . "%' OR email LIKE '%" . $needle . "%' OR message LIKE '%" . $needle . "%')";
      }

      return implode(' AND ', $clauses);
   }

   private function decorate_rows(array $rows, $report): array {
      $out = array();
      $db = $this->db();
      foreach ($rows as $row) {
         $rid = (int) ($row['id'] ?? 0);
         $status = dbxContactTicket::normalize_status((string) ($row['status'] ?? 'open'));
         $priority = dbxContactTicket::normalize_priority((string) ($row['priority'] ?? 'normal'));
         $row['status_view'] = '<span class="badge ' . dbxContactPresentation::status_class($status) . '">' . $this->h($report->get_fd_message('status_' . $status, $status)) . '</span>';
         $row['priority_view'] = '<span class="badge ' . $this->priority_class($priority) . '">' . $this->h($report->get_fd_message('priority_' . $priority, $priority)) . '</span>';
         $row['user_view'] = (int) ($row['uid'] ?? 0) > 0
            ? '#' . (int) $row['uid']
            : $report->get_fd_message('guest');
         $row['hidden_view'] = (int) ($row['user_hidden'] ?? 0) === 1
            ? '<span class="badge text-bg-dark">'
               . $this->h($report->get_fd_message('user_hidden'))
               . '</span>'
            : '';
         $row['message_count'] = $rid > 0 ? max(0, (int) $db->count(dbxContactTicket::DD_MESSAGE, array('ticket_id' => $rid))) : 0;
         $row['action'] = $this->tpl()->get_tpl('dbxContact_admin|ticket-row-action', array('rid' => $rid));
         $out[] = $row;
      }
      return $out;
   }

   private function delete_confirm_text(int $rid, int $message_count, $source): array {
      $has_thread = $message_count > 0;

      return array(
         'title' => $source->get_fd_message('delete_title'),
         'question' => $source->format_fd_message(
            'delete_question',
            array('id' => $rid)
         ),
         'hint' => $has_thread
            ? $source->format_fd_message(
               'delete_hint_thread',
               array('count' => $message_count)
            )
            : $source->get_fd_message('delete_hint_empty'),
      );
   }

   public function contact_ticket_report_row_action_data($report, $data): array {
      if (!is_array($data) || (string) ($data['type'] ?? '') !== 'delete') {
         return $data;
      }

      $rid = (int) ($data['record']['id'] ?? $data['data']['rid'] ?? 0);
      $message_count = max(0, (int) ($data['record']['message_count'] ?? 0));
      $confirm = $this->delete_confirm_text($rid, $message_count, $report);
      $data['data']['action'] = '?dbx_modul=dbxContact_admin&dbx_run1=delete&rid=' . $rid;
      $data['data']['confirm_title'] = $confirm['title'];
      $data['data']['confirm'] = $confirm['question'];
      $data['data']['confirm_hint'] = $confirm['hint'];
      $data['data']['tooltip'] = $report->get_fd_message(
         'delete_tooltip'
      );

      return $data;
   }

   private function list_tickets(string $success = '', string $error = '', ?bool $with_frame = null): string {
      if ($with_frame === null) {
         $with_frame = (int) dbx()->get_system_var('dbx_ajax', 0, 'int') !== 1;
      }

      $db = $this->db();
      $report = dbx()->get_system_obj('dbxReport');
      $report->init('contact-ticket-report', 'dbxContact_admin|ticket-report');
      $report->set_field_definition('dbxContact_admin|rpt-ticket-selection');
      $report->load_fd_messages();
      $report->set_data_definition(dbxContactTicket::DD_TICKET);
      $report->set_action('?dbx_modul=dbxContact_admin&dbx_run1=list');
      $report->_pages = true;
      $report->_create_row_select = false;
      $report->_create_row_edit = false;
      $report->_create_row_delete = true;
      $report->_table_buttons = 'left';
      $report->_msg_confirm_delete = $report->get_fd_message(
         'confirm_delete'
      );
      $report->_but_pagination = 7;
      $report->_msg_info = '';
      $report->add_rep(
         'report_extra_stats',
         $this->report_status_stats_html($report)
      );
      if ($success !== '') {
         $report->_msg_success = $report->get_fd_message(
            $success,
            $success
         );
      }
      if ($error !== '') {
         $report->_msg_error = $report->get_fd_message($error, $error);
      }
      $report->set_callback_owner($this);
      $report->set_callback('row_action_data', 'contact_ticket_report_row_action_data');
      $report->set_table_tpl('tpl_row_delete', 'modul|ticket-row-delete');
      $report->create_selection_fields('dbxContact_admin|rpt-ticket-selection');
      $report->add_rep(
         'bar_title',
         $report->get_fd_message('report_title')
      );

      $search = trim((string) $report->get_fld_val('dbx_rwhere', '', 'sqlsearch|max=100'));
      $status = (string) $report->get_fld_val('dbx_rstatus', 'all', 'parameter');
      $priority = (string) $report->get_fld_val('dbx_rpriority', 'all', 'parameter');
      $rows_per_page = max(10, min(100, (int) $report->get_fld_val('dbx_rrows', 30, 'int')));
      $position = max(0, (int) $report->get_fld_val('dbx_rpos', 0, 'int'));
      $sort = (string) $report->get_fld_val('dbx_rsort', 'last_activity_date', 'parameter');
      $direction = strtoupper((string) $report->get_fld_val('dbx_rdesc', 'DESC', 'parameter')) === 'ASC' ? 'ASC' : 'DESC';
      if (!in_array($sort, array('last_activity_date', 'create_date', 'id', 'status', 'priority', 'subject'), true)) {
         $sort = 'last_activity_date';
      }

      $where = $this->where_for_report($search, $status, $priority);
      $cols = array('id', 'create_date', 'last_activity_date', 'status', 'priority', 'uid', 'name', 'email', 'subject', 'user_hidden');
      $report->_rflds = array(
         'id' => $report->get_fd_message('column_ticket'),
         'last_activity_date' => $report->get_fd_message(
            'column_activity'
         ),
         'status_view' => $report->get_fd_message('column_status'),
         'priority_view' => $report->get_fd_message('column_priority'),
         'user_view' => $report->get_fd_message('column_user'),
         'name' => $report->get_fd_message('column_name'),
         'email' => $report->get_fd_message('column_email'),
         'subject' => $report->get_fd_message('column_subject'),
         'hidden_view' => '',
         'action' => $report->get_fd_message('column_action'),
      );
      $report->_rpt_format = array(
         'last_activity_date' => 'php-datetime-usr',
         'status_view' => 'html',
         'priority_view' => 'html',
         'hidden_view' => 'html',
         'action' => 'html',
      );
      $report->_rrows = $rows_per_page;
      $report->_rpos = $position;
      $report->_count_all = $db->count(dbxContactTicket::DD_TICKET, '');
      $report->_rcount = $db->count(dbxContactTicket::DD_TICKET, $where);
      $rows = $db->select(dbxContactTicket::DD_TICKET, $where, $cols, $sort . ',id', $direction, '', $rows_per_page, $position, 0);
      $report->_rdata = $this->decorate_rows(
         is_array($rows) ? $rows : array(),
         $report
      );

      $content = $report->run();
      return $with_frame ? $this->frame($content) : $content;
   }

   private function delete_ticket_record(int $rid): bool {
      if ($rid <= 0 || !dbxContactTicket::ticket($this->db(), $rid)) {
         return false;
      }

      $db = $this->db();
      $ok = 0;

      if ($db->begin(dbxContactTicket::DD_TICKET) === 1) {
         $messages_ok = $db->delete(dbxContactTicket::DD_MESSAGE, array('ticket_id' => $rid), 0, 1);
         if ($messages_ok === 1 || $db->count(dbxContactTicket::DD_MESSAGE, array('ticket_id' => $rid)) === 0) {
            $ok = $db->delete(dbxContactTicket::DD_TICKET, $rid, 0, 1);
         }
         if ($ok === 1) {
            $db->commit(dbxContactTicket::DD_TICKET);
         } else {
            $db->rollback(dbxContactTicket::DD_TICKET);
         }
      }

      return $ok === 1;
   }

   private function timeline(array $ticket, $texts): string {
      $db = $this->db();
      dbxContactTicket::ensure_initial_message($db, $ticket);
      $html = '';

      foreach (dbxContactTicket::messages($db, (int) $ticket['id'], true) as $message) {
         $internal = (string) ($message['visibility'] ?? 'public') === 'internal';
         $author_type = (string) ($message['author_type'] ?? 'system');
         $status_to = trim((string) ($message['status_to'] ?? ''));
         $html .= $this->tpl()->get_tpl('dbxContact_admin|ticket-message', array(
            'message_class' => $internal ? 'border-warning bg-warning-subtle' : ($author_type === 'admin' ? 'border-primary bg-primary-subtle' : 'bg-body-tertiary'),
            'message_icon' => $internal ? 'bi-lock' : ($author_type === 'admin' ? 'bi-headset' : ($author_type === 'requester' ? 'bi-person' : 'bi-gear')),
            'author_label' => $internal
               ? $texts->get_fd_message('timeline_internal_note')
               : ($author_type === 'admin'
                  ? $texts->get_fd_message('timeline_support')
                  : ($author_type === 'requester'
                     ? $texts->get_fd_message('timeline_requester')
                     : $texts->get_fd_message('timeline_system'))),
            'create_date' => $this->h($message['create_date'] ?? ''),
            'body' => nl2br($this->h($message['body'] ?? '')),
            'visibility_badge' => $internal
               ? '<span class="badge text-bg-warning">' . $this->h($texts->get_fd_message('timeline_internal_only')) . '</span>'
               : '',
            'status_badge' => $status_to !== ''
               ? '<span class="badge ' . dbxContactPresentation::status_class($status_to) . '">'
                  . $this->h($texts->format_fd_message(
                     'timeline_status',
                     array('status' => $texts->get_fd_message('status_' . $status_to, $status_to))
                  ))
                  . '</span>'
               : '',
            'mail_badge' => (int) ($message['mail_sent'] ?? 0) === 1
               ? '<span class="badge text-bg-success"><i class="bi bi-envelope-check"></i> '
                  . $this->h($texts->get_fd_message('timeline_mail_sent'))
                  . '</span>'
               : '',
         ));
      }

      return $html !== ''
         ? $html
         : '<div class="text-muted">' . $this->h($texts->get_fd_message('timeline_empty')) . '</div>';
   }

   private function send_reply_mail(array $ticket, string $body): bool {
      $to = trim((string) ($ticket['email'] ?? ''));
      if ($to === '') {
         return false;
      }

      $from = trim((string) dbx()->get_cfg('dbxContact', 'mail_from'));
      $from_name = trim((string) dbx()->get_cfg('dbxContact', 'mail_from_name'));
      if (filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
         return false;
      }
      $from_param = array('email' => $from, 'name' => $from_name);
      $subject = 'Antwort zu Ticket #' . (int) $ticket['id'] . ': ' . (string) ($ticket['subject'] ?? 'Kontaktanfrage');
      $html = $this->tpl()->get_tpl('dbxContact|mail-contact-reply', array(
         'subject' => $this->h($ticket['subject'] ?? ''),
         'body' => nl2br($this->h($body)),
      ));
      $text = "Antwort zu Ticket #" . (int) $ticket['id'] . "\n\n" . $body;

      return (bool) dbx()->get_system_obj('dbxMail')->send_message(
         $from_param,
         $to,
         $subject,
         $html,
         'html',
         array(),
         dbxContactPresentation::mail_options(array('text' => $text))
      );
   }

   private function reply_form(array $ticket): string {
      $rid = (int) $ticket['id'];
      $form = dbx()->get_system_obj('dbxForm');
      $form->init('contact-ticket-reply', 'ticket-reply-form');
      $form->set_data_source(dbxContactTicket::DD_MESSAGE, 'dbxContact_admin|ticket-reply');
      $form->load_fd_messages();
      $form->load_fd_messages(
         'dbxContact_admin|rpt-ticket-selection'
      );
      $form->set_action('?dbx_modul=dbxContact_admin&dbx_run1=ticket&rid=' . $rid);
      $form->set_data(array(
         'status' => dbxContactTicket::normalize_status((string) ($ticket['status'] ?? 'open')),
         'priority' => dbxContactTicket::normalize_priority((string) ($ticket['priority'] ?? 'normal')),
         'visibility' => 'public',
         'body' => '',
         'send_mail' => 1,
      ));
      $form->add_module_bar(
         $form->get_fd_message('bar_title'),
         'bi-reply',
         $form->get_fd_message('bar_subtitle')
      );
      $form->add_rep('bar_title_pre', '');
      $form->add_rep('bar_title_heading_attrs', '');
      $form->add_rep('bar_middle', '');
      $form->add_rep('bar_actions', '');
      $form->add_rep('bar_extra', '');
      $form->add_rep('rid', $rid);
      $message_count = max(0, (int) $this->db()->count(dbxContactTicket::DD_MESSAGE, array('ticket_id' => $rid)));
      $confirm = $this->delete_confirm_text(
         $rid,
         $message_count,
         $form
      );
      $form->add_rep(
         'delete_action',
         dbx()->action_url(
            '?dbx_modul=dbxContact_admin&dbx_run1=delete&rid=' . $rid
         )
      );
      $form->add_rep('delete_confirm_title', $confirm['title']);
      $form->add_rep('delete_confirm', $confirm['question']);
      $form->add_rep('delete_confirm_hint', $confirm['hint']);
      $form->_msg_info = $form->get_fd_message('form_info');
      $form->_msg_error = $form->get_fd_message('validation_error');
      $form->add_fld('status');
      $form->add_fld('priority');
      $form->add_fld('visibility');
      $form->add_fld('body');
      $form->add_fld('send_mail');

      if ($form->submit() && !$form->errors()) {
         $status = dbxContactTicket::normalize_status((string) $form->get_post('status', 'answered', 'parameter|max=24'));
         $priority = dbxContactTicket::normalize_priority((string) $form->get_post('priority', 'normal', 'parameter|max=16'));
         $visibility = (string) $form->get_post('visibility', 'public', 'parameter|max=16') === 'internal' ? 'internal' : 'public';
         $body = trim((string) $form->get_post_data('body', '', '*'));
         $send_mail = (int) $form->get_post('send_mail', 0, 'int') === 1 && $visibility === 'public' && dbxContactConfig::mail_on_reply();
         $old_status = dbxContactTicket::normalize_status((string) ($ticket['status'] ?? 'open'));
         $db = $this->db();

         $message_id = dbxContactTicket::add_message($db, $rid, array(
            'author_uid' => (int) dbx()->user(),
            'author_type' => 'admin',
            'message_type' => $visibility === 'internal' ? 'note' : 'reply',
            'visibility' => $visibility,
            'body' => $body,
            'status_from' => $old_status,
            'status_to' => $status,
         ));

         if ($message_id > 0) {
            $values = array(
               'status' => $status,
               'priority' => $priority,
               'assigned_uid' => (int) dbx()->user(),
               'closed_date' => $status === 'closed' ? date('Y-m-d H:i:s') : '',
            );
            dbxContactTicket::touch($db, $rid, $values);

            $mail_ok = false;
            if ($send_mail) {
               $mail_ok = $this->send_reply_mail($ticket, $body);
               if ($mail_ok) {
                  $db->update(dbxContactTicket::DD_MESSAGE, array(
                     'mail_sent' => 1,
                     'mail_sent_date' => date('Y-m-d H:i:s'),
                  ), $message_id, 0, 1, 1, 1);
               }
            }

            if ($send_mail && !$mail_ok) {
               $form->_msg_warning = $form->get_fd_message(
                  'mail_warning'
               );
            } else {
               $form->_msg_success = $visibility === 'internal'
                  ? $form->get_fd_message('internal_success')
                  : $form->get_fd_message('reply_success')
                     . ($mail_ok
                        ? $form->get_fd_message('mail_success_suffix')
                        : '');
            }
            $form->set_data_value('body', '');
         } else {
            $form->_msg_error = $form->get_fd_message('message_error');
         }
      }

      return $form->run();
   }

   private function ticket(): string {
      $rid = (int) dbx()->get_modul_var('rid', 0, 'int');
      $texts = dbx()->get_system_obj('dbxForm');
      $texts->init('contact-ticket-texts', 'dbx|form');
      $texts->load_fd_messages('dbxContact_admin|rpt-ticket-selection');
      $ticket = dbxContactTicket::ticket($this->db(), $rid);
      if (!$ticket) {
         return $this->frame(
            $this->alert(
               'warning',
               $texts->get_fd_message('ticket_not_found')
            )
         );
      }

      $reply_form = $this->reply_form($ticket);
      $ticket = dbxContactTicket::ticket($this->db(), $rid);
      $status = dbxContactTicket::normalize_status((string) ($ticket['status'] ?? 'open'));
      $priority = dbxContactTicket::normalize_priority((string) ($ticket['priority'] ?? 'normal'));
      $content = $this->tpl()->get_tpl('dbxContact_admin|ticket-detail', array(
         'rid' => $rid,
         'subject' => $this->h($ticket['subject'] ?? ''),
         'name' => $this->h($ticket['name'] ?? ''),
         'email' => $this->h($ticket['email'] ?? ''),
         'email_attr' => $this->h($ticket['email'] ?? ''),
         'phone' => $this->h(trim((string) ($ticket['phone'] ?? '')) ?: '-'),
         'create_date' => $this->h($ticket['create_date'] ?? ''),
         'uid' => (int) ($ticket['uid'] ?? 0) > 0
            ? '#' . (int) $ticket['uid']
            : $texts->get_fd_message('guest'),
         'status_label' => $this->h($texts->get_fd_message('status_' . $status, $status)),
         'status_class' => dbxContactPresentation::status_class($status),
         'priority_label' => $this->h($texts->get_fd_message('priority_' . $priority, $priority)),
         'priority_class' => $this->priority_class($priority),
         'user_visibility' => (int) ($ticket['user_hidden'] ?? 0) === 1
            ? $texts->get_fd_message('user_hidden')
            : $texts->get_fd_message('user_visible'),
         'timeline' => $this->timeline($ticket, $texts),
         'reply_form' => $reply_form,
      ));

      return $this->frame($content, 'Ticket #' . $rid);
   }

   private function delete_ticket(): string {
      $rid = (int) dbx()->get_modul_var('rid', 0, 'int');
      $with_frame = (int) dbx()->get_system_var('dbx_ajax', 0, 'int') !== 1;
      $texts = dbx()->get_system_obj('dbxReport');
      $texts->init('contact-delete-texts', 'dbx|report');
      $texts->load_fd_messages(
         'dbxContact_admin|rpt-ticket-selection'
      );
      if ($this->delete_ticket_record($rid)) {
         return $this->list_tickets(
            $texts->format_fd_message(
               'delete_success',
               array('id' => $rid)
            ),
            '',
            $with_frame
         );
      }

      return $this->list_tickets(
         '',
         $texts->format_fd_message(
            'delete_error',
            array('id' => $rid)
         ),
         $with_frame
      );
   }

   public function run() {
      $run = dbx()->get_modul_var('dbx_run1', 'list', 'parameter');
      if ($run === 'install') {
         return $this->install();
      }
      if (!$this->schema_ready()) {
         return $this->alert('warning', 'Das Ticket-Datenmodell ist noch nicht installiert.')
            . '<div class="p-3"><a class="btn btn-primary" href="?dbx_modul=dbxContact_admin&dbx_run1=install"><i class="bi bi-database-gear"></i> Jetzt installieren</a></div>';
      }
      if ($run === 'ticket' || $run === 'reply') {
         return $this->ticket();
      }
      if ($run === 'delete') {
         return $this->delete_ticket();
      }
      return $this->list_tickets();
   }
}
