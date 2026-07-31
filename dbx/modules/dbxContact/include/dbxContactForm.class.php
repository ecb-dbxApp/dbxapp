<?php
namespace dbx\dbxContact;

require_once __DIR__ . '/dbxContactConfig.class.php';
require_once __DIR__ . '/dbxContactTicket.class.php';

class dbxContactForm {

   private $dd = 'dbxContact|contactRequest';

   private function h($value) {
      return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
   }

   private function mail_profile_options(array $extra = array()) {
      $profile = trim((string) dbx()->get_config('dbxContact', 'mail_profile'));
      if ($profile !== '') {
         $extra['mail_profile'] = $profile;
      }

      return $extra;
   }

   private function mail_from_param(): array {
      $from = trim((string) dbx()->get_config('dbxContact', 'mail_from'));
      $fromName = trim((string) dbx()->get_config('dbxContact', 'mail_from_name'));
      return array('email' => $from, 'name' => $fromName);
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
      $to = trim((string) dbx()->get_config('dbxContact', 'mail_to'));
      if ($to === '') {
         return false;
      }
      $from = $this->mail_from_param();
      if (filter_var((string)($from['email'] ?? ''), FILTER_VALIDATE_EMAIL) === false) {
         return false;
      }

      $prefix = trim((string) dbx()->get_config('dbxContact', 'mail_subject_prefix'));
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

      $options = $this->mail_profile_options(array(
         'reply_to' => array('email' => (string) ($data['email'] ?? ''), 'name' => (string) ($data['name'] ?? '')),
         'text'     => $text,
      ));

      return dbx()->send_mail($from, $to, $subject, $html, 'html', array(), $options);
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

      $subject = trim((string) dbx()->get_config('dbxContact', 'mail_confirm_subject'));
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

      return dbx()->send_mail(
         $from,
         $to,
         $subject,
         $html,
         'html',
         array(),
         $this->mail_profile_options(array('text' => $text))
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

   private function snapshot_submitted_data($oForm) {
      $fields = array(
         'name'    => 'words|min=2|max=160',
         'email'   => 'email|max=180',
         'phone'   => 'varchar|max=80',
         'subject' => 'varchar|min=3|max=220',
         'message' => '*|min=8|max=5000',
      );
      $data = array();

      foreach ($fields as $name => $rules) {
         $value = trim((string) $oForm->get_post($name, '', $rules));
         $data[$name] = $value;
         $oForm->set_post($name, $value);
      }

      return $data;
   }

   private function confirm_notes(array $data, int $rid, ?bool $confirmMailOk, $form) {
      $notes = array();
      $notes[] = $form->get_fd_message('confirm_received');
      $notes[] = $form->format_fd_message(
         'confirm_number',
         array('rid' => $rid)
      );

      $email = trim((string) ($data['email'] ?? ''));
      $safeEmail = $this->h($email);
      if (dbxContactConfig::mailConfirmRequester()) {
         if ($confirmMailOk === true && $email !== '') {
            $notes[] = $form->format_fd_message(
               'confirm_mail_sent',
               array('email' => $safeEmail)
            );
         } elseif ($confirmMailOk === false && $email !== '') {
            $notes[] = $form->get_fd_message('confirm_mail_failed');
         } elseif ($email !== '') {
            $notes[] = $form->format_fd_message(
               'confirm_mail_pending',
               array('email' => $safeEmail)
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

   private function render_confirm(array $data, int $rid, ?bool $confirmMailOk, $form) {
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
            $confirmMailOk,
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
         'bar_class'           => 'dbx-module-bar',
         'bar_title_class'     => 'dbx-module-bar-titleblock',
         'bar_actions_class'   => 'dbx-module-bar-actions',
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
      $oForm = dbx()->get_system_obj('dbxForm');
      $oDB   = dbx()->get_system_obj('dbxDB');
      $successContent = '';

      $oForm->init('dbxContact_form', 'contact-form');
      $oForm->set_editor_class_file(__FILE__);
      $oForm->_dd = $this->dd;
      $oForm->_fd = 'dbxContact|contact-form';
      $oForm->load_fd_messages();
      $oForm->_action = '?dbx_modul=dbxContact&dbx_run1=form';
      $oForm->add_module_bar(
         $oForm->get_fd_message('bar_title'),
         'bi-envelope-paper',
         $oForm->get_fd_message('bar_subtitle')
      );
      $oForm->add_rep('bar_class', 'dbx-module-bar');
      $oForm->add_rep('frame_panel_class', 'dbxForm_wrapper dbx-contact');
      $oForm->prepare_form_shell(array('class' => 'dbx-contact-form'));
      $oForm->add_rep('bar_actions', '{obj:contact_bar_actions}');
      $oForm->add_obj('contact_bar_actions', 'dbxContact|contact-form-bar-actions', array(
         'bar_form_id' => 'dbx_form_' . (int) $oForm->_next_i,
      ));
      $oForm->_data = $this->current_user_defaults();
      $oForm->set_msg_info('');
      $oForm->set_msg_ok($oForm->get_fd_message('request_success'));
      $oForm->set_msg_error($oForm->get_fd_message('validation_error'));
      $oForm->set_msg_warning($oForm->get_fd_message('form_warning'));
      $oForm->_try_reset = 6;
      $oForm->_try_max = 5;
      $oForm->_try_msg = $oForm->get_fd_message('try_limit');

      $oForm->add_fld('name');
      $oForm->add_fld('email');
      $oForm->add_fld('phone');
      $oForm->add_fld('subject');
      $oForm->add_fld('message');
      if ((int) dbx()->user() > 0) {
         $oForm->add_obj('my_requests', 'dbxContact|contact-my-requests-button');
      } else {
         $oForm->add_obj('my_requests', 'obj-value', '');
      }

      if ($oForm->submit()) {
         if (!$oForm->errors()) {
            $submitData = $this->snapshot_submitted_data($oForm);
            $spamReason = $this->spam_reason($submitData);
            if ($spamReason !== '') {
               $oForm->set_msg_error(
                  $oForm->get_fd_message('spam_error')
               );
               $oForm->add_fld_error(
                  'message',
                  $oForm->get_fd_message('spam_field_error')
               );
               dbx()->sys_msg('security', 'dbxContact', 'spam_guard', 'Kontaktanfrage blockiert', $spamReason . ' email=' . ($submitData['email'] ?? ''));
            }
         }

         if (!$oForm->errors()) {
            $submitData = $this->snapshot_submitted_data($oForm);
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

            $ok = $oForm->save_post($this->dd, 0, $extra);
            $rid = (int) $oForm->_rid;

            if (!$ok || $rid <= 0) {
               $oForm->_msg_error = $oForm->get_fd_message(
                  'store_error'
               );
            } else {
               $data = $submitData;
               $record = $oDB->select1($this->dd, $rid, '*', 0);
               if (is_array($record)) {
                  foreach (array('name', 'email', 'phone', 'subject', 'message') as $field) {
                     if (trim((string) ($data[$field] ?? '')) === '' && trim((string) ($record[$field] ?? '')) !== '') {
                        $data[$field] = (string) $record[$field];
                     }
                  }
               }
               $confirmMailOk = null;

               dbxContactTicket::addMessage($oDB, $rid, array(
                  'author_uid' => (int) dbx()->user(),
                  'author_type' => 'requester',
                  'message_type' => 'request',
                  'visibility' => 'public',
                  'body' => (string) ($data['message'] ?? ''),
                  'status_to' => 'open',
               ));
               dbxContactTicket::touch($oDB, $rid, array(
                  'priority' => 'normal',
                  'user_hidden' => 0,
               ));

               if (dbxContactConfig::mailAdminOnRequest()) {
                  $adminMailOk = (bool) $this->mail_request($data, $rid);
                  if ($adminMailOk) {
                     $oDB->update($this->dd, array(
                        'mail_sent'      => 1,
                        'mail_sent_date' => date('Y-m-d H:i:s'),
                     ), $rid, 0, 1, 1, 1);
                  } else {
                     $oForm->set_msg_error(
                        $oForm->get_fd_message('admin_mail_error')
                     );
                     $oForm->add_fld_error(
                        'email',
                        $oForm->get_fd_message('email_field_error')
                     );
                  }
               }

               if (!$oForm->errors() && dbxContactConfig::mailConfirmRequester()) {
                  $confirmMailOk = (bool) $this->mail_confirm($data, $rid);
                  if ($confirmMailOk) {
                     $oDB->update($this->dd, array(
                        'confirm_mail_sent'      => 1,
                        'confirm_mail_sent_date' => date('Y-m-d H:i:s'),
                     ), $rid, 0, 1, 1, 1);
                  } else {
                     $oForm->set_msg_error(
                        $oForm->get_fd_message('confirm_mail_error')
                     );
                     $oForm->add_fld_error(
                        'email',
                        $oForm->get_fd_message(
                           'confirm_email_field_error'
                        )
                     );
                  }
               }

               if (!$oForm->errors()) {
                  $successContent = $this->render_confirm(
                     $data,
                     $rid,
                     $confirmMailOk,
                     $oForm
                  );
               }
            }
         }
      }

      if ($successContent !== '') {
         return $successContent;
      }

      return $oForm->run();
   }
}
