<?php
namespace dbx\dbxUser_admin;
dbx()->get_system_obj('dbxForm', 'use');
require_once dirname(__DIR__, 3) . '/include/dbxPasswordPolicy.class.php';

Class dbxUser_profil extends \dbxObj {

   public function run($action) {
      $content = '';
      $texts = new \dbxForm();
      $texts->init('dbxUser_admin_profile_texts');
      $texts->set_field_definition('dbxUser_admin|user-admin');
      $texts->load_fd_messages();
      $texts->set_form_help_enabled(false);
      $o_form = dbx()->get_system_obj('dbxForm');
      $o_db   = dbx()->get_system_obj('dbxDB');
      $dd    = 'dbxUser';
      $uid   = (int) dbx()->user();
      $work  = dbx()->get_modul_var('dbx_run2', '', 'parameter');

      $requested_rid = ($work === 'new_user') ? 0 : (int) dbx()->get_request_var('rid', $uid, 'int');
      if ($requested_rid < 0) {
         $requested_rid = $uid;
      }

      $o_form->init('form-profil', 'form-profil');
      $o_form->set_field_definition('dbxUser_admin|user-admin');
      $o_form->load_fd_messages();
      $o_form->set_workflow_scope('admin-profil-' . (int) $requested_rid);
      $rid = $requested_rid;
      $o_form->set_rid((int)$rid);
      $o_form->set_state_value('rid', (int) $rid);

      $data = $rid > 0 ? $o_db->select1($dd, $rid) : array('id' => 0);

      if (!is_array($data)) {
         return '<div class="alert alert-warning">' . dbx()->esc($texts->get_fd_message('user_not_found')) . '</div>';
      }
      $current_password_hash = (string)($data['pass'] ?? '');
      $data['pass'] = '';
      $data['pass_repeat'] = '';
      $password_min_length = \dbxPasswordPolicy::minimum_length();
      $o_form->add_rep('password_min_length', (string)$password_min_length);
      $o_form->add_rep(
         'password_length_recommendation',
         $password_min_length < 12 ? ' · 12+ empfohlen' : ''
      );

      $action_url = $work === 'new_user'
         ? '?dbx_modul=dbxUser_admin&dbx_run1=user&dbx_run2=new_user'
         : '?dbx_modul=dbxUser_admin&dbx_run1=user&dbx_run2=edit_profil&rid=' . $rid;
      $o_form->set_data($data);
      $o_form->_msg_info  = $texts->get_fd_message('profile_info');
      $o_form->set_data_definition($dd);
      $o_form->set_action($action_url);
      $o_form->add_rep('frame_skip_form_wrap', '1');
      $o_form->add_rep('frame_panel_class', 'dbxForm_wrapper dbx-user-profile-form');
      $o_form->add_rep('action_save', $texts->get_fd_message('action_save'));
      $o_form->add_module_bar(
         $texts->get_fd_message('profile_title'),
         'bi-person-badge',
         $texts->get_fd_message('profile_subtitle')
      );
      $o_form->add_module_bar_form_actions(array(
         'save' => true,
         'reload' => true,
         'reload_url' => $action_url,
         'delete' => false,
      ));

      $options_land = array(
         ''   => $texts->get_fd_message('selection'),
         'de' => $texts->get_fd_message('country_de'),
         'at' => $texts->get_fd_message('country_at'),
         'ch' => $texts->get_fd_message('country_ch'),
         'us' => $texts->get_fd_message('country_us')
      );

      $options_language = array(
         'de' => $texts->get_fd_message('language_de'),
         'en' => $texts->get_fd_message('language_en'),
         'es' => $texts->get_fd_message('language_es')
      );

      $options_status = array(
         0 => $texts->get_fd_message('status_new'),
         1 => $texts->get_fd_message('status_active'),
         2 => $texts->get_fd_message('status_locked'),
         3 => $texts->get_fd_message('status_archive')
      );

      $options_confirm = array(
         0 => $texts->get_fd_message('no'),
         1 => $texts->get_fd_message('yes')
      );

      $options_gender = array(
         '' => $texts->get_fd_message('selection'),
         'm' => $texts->get_fd_message('gender_m'),
         'w' => $texts->get_fd_message('gender_f'),
         'd' => $texts->get_fd_message('gender_d')
      );

      $options_anrede = array(
         '' => $texts->get_fd_message('selection'),
         'Herr' => $texts->get_fd_message('salutation_mr'),
         'Frau' => $texts->get_fd_message('salutation_ms'),
         'Divers' => $texts->get_fd_message('salutation_diverse')
      );

      $o_form->add_fld('uname'     ,'text-label', label: $texts->get_fd_message('label_username'));
      $o_form->add_fld(
         'pass',
         'password-label',
         label: $texts->get_fd_message('label_password_new'),
         rules: 'varchar|max=128',
         placeholder: $texts->get_fd_message('password_unchanged_placeholder'),
         tooltip: 'Nur ausfüllen, wenn ein neues Passwort gesetzt werden soll.'
      );
      $o_form->add_fld(
         'pass_repeat',
         'password-label',
         label: $texts->get_fd_message('label_password_repeat'),
         rules: 'varchar|max=128',
         placeholder: $texts->get_fd_message('password_repeat_placeholder'),
         tooltip: 'Das neue Passwort zur Kontrolle noch einmal eingeben.',
         dd: ''
      );
      $o_form->add_fld('status'    ,'select-single-label', label: $texts->get_fd_message('label_status'), options: $options_status);
      $o_form->add_fld('is_confirm','select-single-label', label: $texts->get_fd_message('label_confirmed_short'), options: $options_confirm);
      $o_form->add_fld('roles'     ,'multi-select', label: $texts->get_fd_message('label_roles'), rules: 'array|parameter', class: 'dbxMultiSelect2', data: array('size' => 8));

      $o_form->add_fld('anrede'    ,'select-single-label', label: $texts->get_fd_message('label_salutation'), options: $options_anrede);
      $o_form->add_fld('geschlecht','select-single-label', label: $texts->get_fd_message('label_gender'), options: $options_gender);
      $o_form->add_fld('name'      ,'text-label', label: $texts->get_fd_message('label_first_name'));
      $o_form->add_fld('name2'     ,'text-label', label: $texts->get_fd_message('label_last_name'));
      $o_form->add_fld('geburtstag','date-label', label: $texts->get_fd_message('label_birthday'));

      $o_form->add_fld('email'     ,'text-label', label: $texts->get_fd_message('label_email'));
      $o_form->add_fld('emailbill' ,'text-label', label: $texts->get_fd_message('label_billing_email'));
      $o_form->add_fld('telefon'   ,'text-label', label: $texts->get_fd_message('label_phone'));
      $o_form->add_fld('handy'     ,'text-label', label: $texts->get_fd_message('label_mobile'));
      $o_form->add_fld('fax'       ,'text-label', label: $texts->get_fd_message('label_fax'));

      $o_form->add_fld('strasse'   ,'text-label', label: $texts->get_fd_message('label_street'));
      $o_form->add_fld('land'      ,'select-single-label', label: $texts->get_fd_message('label_country'), options: $options_land);
      $o_form->add_fld('plz'       ,'text-label', label: $texts->get_fd_message('label_zip'));
      $o_form->add_fld('ort'       ,'text-label', label: $texts->get_fd_message('label_city'));

      $o_form->add_fld('language'  ,'select-single-label', label: $texts->get_fd_message('label_language'), options: $options_language);
      $o_form->add_fld('design'    ,'text-label', label: $texts->get_fd_message('label_design'));
      $o_form->add_fld('color'     ,'text-label', label: $texts->get_fd_message('label_color'));
      $o_form->add_fld('login_pid' ,'text-label', label: $texts->get_fd_message('label_login_page'));
      $o_form->add_fld('logout_pid','text-label', label: $texts->get_fd_message('label_logout_page'));

      if($o_form->submit()) {
         $pas = (string)$o_form->get_post_data('pass', '', '*');
         $pas_repeat = (string)$o_form->get_post_data(
            'pass_repeat',
            '',
            '*'
         );
         $password_error_message = '';
         if ($pas !== '' || $pas_repeat !== '') {
            $password_errors = \dbxPasswordPolicy::errors(
               $pas,
               $pas_repeat,
               $current_password_hash,
               $password_min_length
            );
            if (isset($password_errors['password'])) {
               $o_form->add_fld_error('pass', $password_errors['password']);
            }
            if (isset($password_errors['repeat'])) {
               $o_form->add_fld_error(
                  'pass_repeat',
                  $password_errors['repeat']
               );
            }
            $password_error_message = implode(
               ' ',
               array_values(array_unique($password_errors))
            );
         }
         if(!$o_form->errors()) {
            $change = $o_form->changed();
            if ($change) {
               $save_rid = (int) $rid;

               if ($pas !== '') {
                  $o_form->set_post_value('pass', password_hash(
                     $pas,
                     PASSWORD_DEFAULT
                  ));
                  $settings = json_decode(
                     (string)($data['settings'] ?? ''),
                     true
                  );
                  $settings = is_array($settings) ? $settings : array();
                  unset($settings['password_reset_required']);
                  $settings['password_changed_at'] = date(DATE_ATOM);
                  $o_form->set_post_value('settings', json_encode(
                     $settings,
                     JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                  ));
               } else {
                  $o_form->unset_post_value('pass');
               }
               $o_form->unset_post_value('pass_repeat');

               if ($save_rid > 0) {
                  $o_form->unset_post_value('id');
               }

               $ok = $o_form->save_post($dd, $save_rid);
               if ($ok) {
                  $rid = $o_form->current_rid();
                  $o_form->set_action('?dbx_modul=dbxUser_admin&dbx_run1=user&dbx_run2=edit_profil&rid=' . $rid);
               }

               if ( $ok) $o_form->_msg_success = $texts->get_fd_message('user_saved');
               if (!$ok) $o_form->_msg_error   = $texts->get_fd_message('user_save_error');
            } else {
               $o_form->_msg_success = $texts->get_fd_message('no_change');
            }
         } else {
            $err_flds = '';
            foreach ($o_form->_errors as $key => $value) {
               $err_flds .= $key . ' ';
            }
            $o_form->_msg_error = $password_error_message !== ''
               ? $password_error_message
               : $texts->get_fd_message('check_input')
                  . ' (' . trim($err_flds) . ')';
         }
      }

      $rid = $o_form->get_data('id', $rid);
      $o_form->add_obj('obs_rid', 'dbx|observe', 'name=usr_rid&value=' . (int) $rid);
      $o_form->add_obj('avatar', 'obj-value', dbx()->get_include_obj('dbxUser_avatar')->run((int) $rid));

      $content = $o_form->run();
      return $content;
   }
}

?>
