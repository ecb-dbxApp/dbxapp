<?php
namespace dbx\dbxContact;

require_once __DIR__ . '/dbxContactConfig.class.php';
require_once __DIR__ . '/dbxContactTicket.class.php';
require_once __DIR__ . '/dbxContactPresentation.class.php';

class dbxContactForm {

   private $dd = 'dbxContact|contactRequest';

   private function h($value) {
      return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
   }

   private function mail_from_param(): array {
      $from = trim((string) dbx()->get_cfg('dbxContact', 'mail_from'));
      $from_name = trim((string) dbx()->get_cfg('dbxContact', 'mail_from_name'));
      return array('email' => $from, 'name' => $from_name);
   }

   private function spam_reason(array $data): string {
      $mail = dbx()->get_system_obj('dbxMail');
      if (!is_object($mail) || !method_exists($mail, 'spam_reason_for_text')) {
         return '';
      }

      $text = implode("\n", array(
         (string) ($data['name'] ?? ''),
         (string) ($data['email'] ?? ''),
         (string) ($data['phone'] ?? ''),
         (string) ($data['subject'] ?? ''),
         (string) ($data['message'] ?? ''),
      ));

      return (string) $mail->spam_reason_for_text($text);
   }

   private function mail_request(array $data, int $rid) {
      $to = trim((string) dbx()->get_cfg('dbxContact', 'mail_to'));
      if ($to === '') {
         return false;
      }
      $from = $this->mail_from_param();
      if (filter_var((string)($from['email'] ?? ''), FILTER_VALIDATE_EMAIL) === false) {
         return false;
      }

      $prefix = trim((string) dbx()->get_cfg('dbxContact', 'mail_subject_prefix'));
      $subject = trim($prefix . ': ' . ($data['subject'] ?? ''));
      if ($rid > 0) {
         $subject .= ' #' . $rid;
      }

      $tpl = dbx()->get_system_obj('dbxTPL');
      $html = $tpl->get_tpl('dbxContact|mail-contact-request', array(
         'name'    => $this->h($data['name'] ?? ''),
         'email'   => $this->h($data['email'] ?? ''),
         'phone'   => $this->h($data['phone'] ?? ''),
         'subject' => $this->h($data['subject'] ?? ''),
         'message' => $this->h($data['message'] ?? ''),
      ));

      $text = "Neue Kontaktanfrage #" . $rid . "\n\n"
            . "Name: " . ($data['name'] ?? '') . "\n"
            . "E-Mail: " . ($data['email'] ?? '') . "\n"
            . "Telefon: " . ($data['phone'] ?? '') . "\n"
            . "Betreff: " . ($data['subject'] ?? '') . "\n\n"
            . ($data['message'] ?? '');

      $options = dbxContactPresentation::mail_options(array(
         'reply_to' => array('email' => (string) ($data['email'] ?? ''), 'name' => (string) ($data['name'] ?? '')),
         'text'     => $text,
      ));

      return dbx()->get_system_obj('dbxMail')->send_message($from, $to, $subject, $html, 'html', array(), $options);
   }

   private function mail_confirm(array $data, int $rid) {
      $to = trim((string) ($data['email'] ?? ''));
      if ($to === '') {
         return false;
      }
      $from = $this->mail_from_param();
      if (filter_var((string)($from['email'] ?? ''), FILTER_VALIDATE_EMAIL) === false) {
         return false;
      }

      $subject = trim((string) dbx()->get_cfg('dbxContact', 'mail_confirm_subject'));
      if ($subject === '') {
         $subject = 'Ihre Kontaktanfrage';
      }
      if ($rid > 0) {
         $subject .= ' #' . $rid;
      }

      $phone = trim((string) ($data['phone'] ?? ''));

      $tpl = dbx()->get_system_obj('dbxTPL');
      $html = $tpl->get_tpl('dbxContact|mail-contact-confirm', array(
         'rid'     => (int) $rid,
         'name'    => $this->h($data['name'] ?? ''),
         'phone'   => $this->h($phone !== '' ? $phone : '-'),
         'subject' => $this->h($data['subject'] ?? ''),
         'message' => $this->h($data['message'] ?? ''),
      ));

      $text = "Ihre Anfrage ist eingegangen\n\n"
            . "Hallo " . ($data['name'] ?? '') . ",\n\n"
            . "vielen Dank fuer Ihre Nachricht. Wir haben Ihre Kontaktanfrage unter der Nummer #"
            . $rid . " erhalten und werden sie bearbeiten.\n\n"
            . "Betreff: " . ($data['subject'] ?? '') . "\n"
            . "Telefon: " . ($phone !== '' ? $phone : '-') . "\n\n"
            . ($data['message'] ?? '') . "\n\n"
            . "Wir melden uns so bald wie moeglich bei Ihnen. Sie muessen nichts weiter unternehmen.";

      return dbx()->get_system_obj('dbxMail')->send_message(
         $from,
         $to,
         $subject,
         $html,
         'html',
         array(),
         dbxContactPresentation::mail_options(array('text' => $text))
      );
   }

   private function current_user_defaults() {
      $uid = (int) dbx()->user();
      $data = array(
         'name'    => '',
         'email'   => '',
         'phone'   => '',
         'subject' => '',
         'message' => '',
      );

      if ($uid > 0) {
         $name = dbx()->user('name');
         $email = dbx()->user('email');

         if ($name !== 'undef') {
            $data['name'] = (string) $name;
         }

         if ($email !== 'undef') {
            $data['email'] = (string) $email;
         }
      }

      return $data;
   }

   private function my_requests_button() {
      if ((int) dbx()->user() <= 0) {
         return '';
      }

      return dbx()->get_system_obj('dbxTPL')->get_tpl('dbxContact|contact-my-requests-button');
   }

   private function snapshot_submitted_data($o_form) {
      $fields = array(
         'name'    => 'words|min=2|max=160',
         'email'   => 'email|max=180',
         'phone'   => 'varchar|max=80',
         'subject' => 'varchar|min=3|max=220',
         'message' => '*|min=8|max=5000',
      );
      $data = array();

      foreach ($fields as $name => $rules) {
         $value = trim((string) $o_form->get_post($name, '', $rules));
         $data[$name] = $value;
         $o_form->set_post($name, $value);
      }

      return $data;
   }

   private function confirm_notes(array $data, int $rid, ?bool $confirm_mail_ok, $form) {
      $notes = array();
      $notes[] = $form->get_fd_message('confirm_received');
      $notes[] = $form->format_fd_message(
         'confirm_number',
         array('rid' => $rid)
      );

      $email = trim((string) ($data['email'] ?? ''));
      $safe_email = $this->h($email);
      if (dbxContactConfig::mail_confirm_requester()) {
         if ($confirm_mail_ok === true && $email !== '') {
            $notes[] = $form->format_fd_message(
               'confirm_mail_sent',
               array('email' => $safe_email)
            );
         } elseif ($confirm_mail_ok === false && $email !== '') {
            $notes[] = $form->get_fd_message('confirm_mail_failed');
         } elseif ($email !== '') {
            $notes[] = $form->format_fd_message(
               'confirm_mail_pending',
               array('email' => $safe_email)
            );
         }
      }

      $notes[] = $form->get_fd_message('confirm_reply');

      $html = '';
      foreach ($notes as $note) {
         $html .= '<li>' . $note . '</li>';
      }

      return $html;
   }

   private function render_confirm(array $data, int $rid, ?bool $confirm_mail_ok, $form) {
      $tpl = dbx()->get_system_obj('dbxTPL');
      $phone = trim((string) ($data['phone'] ?? ''));

      $html = $tpl->get_tpl('dbxContact|contact-confirm', array(
         'rid'           => (int) $rid,
         'name'          => $this->h($data['name'] ?? ''),
         'email'         => $this->h($data['email'] ?? ''),
         'phone'         => $this->h($phone !== '' ? $phone : '-'),
         'subject'       => $this->h($data['subject'] ?? ''),
         'message'       => $this->h($data['message'] ?? ''),
         'confirm_lead'  => $form->get_fd_message('confirm_lead'),
         'confirm_notes' => $this->confirm_notes(
            $data,
            $rid,
            $confirm_mail_ok,
            $form
         ),
         'my_requests'   => $this->my_requests_button(),
         'frame_id'            => 'dbx_contact_confirm',
         'frame_panel_class'   => 'dbxForm_wrapper dbx-contact dbx-contact-confirm-panel',
         'frame_panel_attrs'   => '',
         'frame_subbar'        => '',
         'frame_form_open'     => '',
         'frame_form_close'    => '',
         'frame_body_class'    => '',
         'frame_body_head'     => '',
         'frame_body_tail'     => '',
         'bar_class'           => 'dbx-bar--module',
         'bar_title_class'     => 'dbx-bar-title',
         'bar_actions_class'   => 'dbx-bar-actions',
         'bar_title'           => $form->get_fd_message('confirm_title'),
         'bar_icon'            => 'bi-check2-circle',
         'bar_subtitle'        => $form->get_fd_message(
            'confirm_subtitle'
         ),
         'bar_title_pre'       => '',
         'bar_title_heading_attrs' => '',
         'bar_middle'          => '',
         'bar_extra'           => '',
         'bar_actions'         => $tpl->get_tpl('dbxContact|contact-confirm-bar-actions', array(
            'my_requests' => $this->my_requests_button(),
         )),
      ));

      return str_replace('[dbx:js]', '', $html);
   }

   public function run() {
      $o_form = dbx()->get_system_obj('dbxForm');
      $o_db   = dbx()->get_system_obj('dbxDB');
      $success_content = '';

      $o_form->init('dbxContact_form', 'contact-form');
      $o_form->set_editor_class_file(__FILE__);
      $o_form->set_data_source($this->dd, 'dbxContact|contact-form');
      $o_form->load_fd_messages();
      $o_form->set_action('?dbx_modul=dbxContact&dbx_run1=form');
      $o_form->add_module_bar(
         $o_form->get_fd_message('bar_title'),
         'bi-envelope-paper',
         $o_form->get_fd_message('bar_subtitle')
      );
      $o_form->add_rep('bar_class', 'dbx-bar--module');
      $o_form->add_rep('frame_panel_class', 'dbxForm_wrapper dbx-contact');
      $o_form->prepare_form_shell(array('class' => 'dbx-contact-form'));
      $o_form->add_rep('bar_actions', '{obj:contact_bar_actions}');
      $o_form->add_obj('contact_bar_actions', 'dbxContact|contact-form-bar-actions', array(
         'bar_form_id' => 'dbx_form_' . (int) $o_form->_next_i,
      ));
      $o_form->set_data($this->current_user_defaults());
      $o_form->set_msg_info('');
      $o_form->set_msg_ok($o_form->get_fd_message('request_success'));
      $o_form->set_msg_error($o_form->get_fd_message('validation_error'));
      $o_form->set_msg_warning($o_form->get_fd_message('form_warning'));
      $o_form->_try_reset = 6;
      $o_form->_try_max = 5;
      $o_form->_try_msg = $o_form->get_fd_message('try_limit');

      $o_form->add_fld('name');
      $o_form->add_fld('email');
      $o_form->add_fld('phone');
      $o_form->add_fld('subject');
      $o_form->add_fld('message');
      if ((int) dbx()->user() > 0) {
         $o_form->add_obj('my_requests', 'dbxContact|contact-my-requests-button');
      } else {
         $o_form->add_obj('my_requests', 'obj-value', '');
      }

      if ($o_form->submit()) {
         if (!$o_form->errors()) {
            $submit_data = $this->snapshot_submitted_data($o_form);
            $spam_reason = $this->spam_reason($submit_data);
            if ($spam_reason !== '') {
               $o_form->set_msg_error(
                  $o_form->get_fd_message('spam_error')
               );
               $o_form->add_fld_error(
                  'message',
                  $o_form->get_fd_message('spam_field_error')
               );
               dbx()->sys_msg('security', 'dbxContact', 'spam_guard', 'Kontaktanfrage blockiert', $spam_reason . ' email=' . ($submit_data['email'] ?? ''));
            }
         }

         if (!$o_form->errors()) {
            $submit_data = $this->snapshot_submitted_data($o_form);
            $extra = array(
               'uid'                => (int) dbx()->user(),
               'status'             => 'open',
               'request_ip'         => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64),
               'request_user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
               'mail_sent'          => 0,
               'mail_sent_date'     => '',
               'confirm_mail_sent'  => 0,
               'confirm_mail_sent_date' => '',
            );

            $ok = $o_form->save_post($this->dd, 0, $extra);
            $rid = $o_form->current_rid();

            if (!$ok || $rid <= 0) {
               $o_form->_msg_error = $o_form->get_fd_message(
                  'store_error'
               );
            } else {
               $data = $submit_data;
               $record = $o_db->select1($this->dd, $rid, '*', 0);
               if (is_array($record)) {
                  foreach (array('name', 'email', 'phone', 'subject', 'message') as $field) {
                     if (trim((string) ($data[$field] ?? '')) === '' && trim((string) ($record[$field] ?? '')) !== '') {
                        $data[$field] = (string) $record[$field];
                     }
                  }
               }
               $confirm_mail_ok = null;

               dbxContactTicket::add_message($o_db, $rid, array(
                  'author_uid' => (int) dbx()->user(),
                  'author_type' => 'requester',
                  'message_type' => 'request',
                  'visibility' => 'public',
                  'body' => (string) ($data['message'] ?? ''),
                  'status_to' => 'open',
               ));
               dbxContactTicket::touch($o_db, $rid, array(
                  'priority' => 'normal',
                  'user_hidden' => 0,
               ));

               if (dbxContactConfig::mail_admin_on_request()) {
                  $admin_mail_ok = (bool) $this->mail_request($data, $rid);
                  if ($admin_mail_ok) {
                     $o_db->update($this->dd, array(
                        'mail_sent'      => 1,
                        'mail_sent_date' => date('Y-m-d H:i:s'),
                     ), $rid, 0, 1, 1, 1);
                  } else {
                     $o_form->set_msg_error(
                        $o_form->get_fd_message('admin_mail_error')
                     );
                     $o_form->add_fld_error(
                        'email',
                        $o_form->get_fd_message('email_field_error')
                     );
                  }
               }

               if (!$o_form->errors() && dbxContactConfig::mail_confirm_requester()) {
                  $confirm_mail_ok = (bool) $this->mail_confirm($data, $rid);
                  if ($confirm_mail_ok) {
                     $o_db->update($this->dd, array(
                        'confirm_mail_sent'      => 1,
                        'confirm_mail_sent_date' => date('Y-m-d H:i:s'),
                     ), $rid, 0, 1, 1, 1);
                  } else {
                     $o_form->set_msg_error(
                        $o_form->get_fd_message('confirm_mail_error')
                     );
                     $o_form->add_fld_error(
                        'email',
                        $o_form->get_fd_message(
                           'confirm_email_field_error'
                        )
                     );
                  }
               }

               if (!$o_form->errors()) {
                  $success_content = $this->render_confirm(
                     $data,
                     $rid,
                     $confirm_mail_ok,
                     $o_form
                  );
               }
            }
         }
      }

      if ($success_content !== '') {
         return $success_content;
      }

      return $o_form->run();
   }
}
