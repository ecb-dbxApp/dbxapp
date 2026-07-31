<?php
namespace dbx\dbxUser_admin;
dbx()->use_system_class('dbxForm');
require_once dirname(__DIR__, 3) . '/include/dbxPasswordPolicy.class.php';

Class dbxUser_profil extends \dbxObj {

   public function run($action) {
      $content = '';
      $texts = new \dbxForm();
      $texts->init('dbxUser_admin_profile_texts');
      $texts->_fd = 'dbxUser_admin|user-admin';
      $texts->load_fd_messages();
      $texts->set_form_help_enabled(false);
      $oForm = dbx()->get_system_obj('dbxForm');
      $oDB   = dbx()->get_system_obj('dbxDB');
      $dd    = 'dbxUser';
      $uid   = (int) dbx()->user();
      $work  = dbx()->get_modul_var('dbx_run2', '', 'parameter');

      $requestedRid = ($work === 'new_user') ? 0 : (int) dbx()->get_request_var('rid', $uid, 'int');
      if ($requestedRid < 0) {
         $requestedRid = $uid;
      }

      $oForm->init('form-profil');
      $oForm->_fd = 'dbxUser_admin|user-admin';
      $oForm->load_fd_messages();
      $oForm->set_workflow_scope('admin-profil-' . (int) $requestedRid);
      $rid = $requestedRid;
      $oForm->_rid = (int) $rid;
      $oForm->set_state_value('rid', (int) $rid);

      $data = $rid > 0 ? $oDB->select1($dd, $rid) : array('id' => 0);

      if (!is_array($data)) {
         return '<div class="alert alert-warning">' . dbx()->esc($texts->get_fd_message('user_not_found')) . '</div>';
      }
      $currentPasswordHash = (string)($data['pass'] ?? '');
      $data['pass'] = '';
      $data['pass_repeat'] = '';
      $passwordMinLength = \dbxPasswordPolicy::minimumLength();
      $oForm->add_rep('password_min_length', (string)$passwordMinLength);
      $oForm->add_rep(
         'password_length_recommendation',
         $passwordMinLength < 12 ? ' · 12+ empfohlen' : ''
      );

      $actionUrl = $work === 'new_user'
         ? '?dbx_modul=dbxUser_admin&dbx_run1=user&dbx_run2=new_user'
         : '?dbx_modul=dbxUser_admin&dbx_run1=user&dbx_run2=edit_profil&rid=' . $rid;
      $oForm->_data      = $data;
      $oForm->_msg_info  = $texts->get_fd_message('profile_info');
      $oForm->_dd        = $dd;
      $oForm->_action    = $actionUrl;
      $oForm->add_rep('frame_skip_form_wrap', '1');
      $oForm->add_rep('frame_panel_class', 'dbxForm_wrapper dbx-user-profile-form');
      $oForm->add_rep('action_save', $texts->get_fd_message('action_save'));
      $oForm->add_module_bar(
         $texts->get_fd_message('profile_title'),
         'bi-person-badge',
         $texts->get_fd_message('profile_subtitle')
      );
      $oForm->add_module_bar_form_actions(array(
         'save' => true,
         'reload' => true,
         'reload_url' => $actionUrl,
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

      $oForm->add_fld('uname'     ,'text-label', label: $texts->get_fd_message('label_username'));
      $oForm->add_fld(
         'pass',
         'password-label',
         label: $texts->get_fd_message('label_password_new'),
         rules: 'varchar|max=128',
         placeholder: $texts->get_fd_message('password_unchanged_placeholder'),
         tooltip: 'Nur ausfüllen, wenn ein neues Passwort gesetzt werden soll.'
      );
      $oForm->add_fld(
         'pass_repeat',
         'password-label',
         label: $texts->get_fd_message('label_password_repeat'),
         rules: 'varchar|max=128',
         placeholder: $texts->get_fd_message('password_repeat_placeholder'),
         tooltip: 'Das neue Passwort zur Kontrolle noch einmal eingeben.',
         dd: ''
      );
      $oForm->add_fld('status'    ,'select-single-label', label: $texts->get_fd_message('label_status'), options: $options_status);
      $oForm->add_fld('is_confirm','select-single-label', label: $texts->get_fd_message('label_confirmed_short'), options: $options_confirm);
      $oForm->add_fld('roles'     ,'multi-select', label: $texts->get_fd_message('label_roles'), rules: 'array|parameter', class: 'dbxMultiSelect2', data: array('size' => 8));

      $oForm->add_fld('anrede'    ,'select-single-label', label: $texts->get_fd_message('label_salutation'), options: $options_anrede);
      $oForm->add_fld('geschlecht','select-single-label', label: $texts->get_fd_message('label_gender'), options: $options_gender);
      $oForm->add_fld('name'      ,'text-label', label: $texts->get_fd_message('label_first_name'));
      $oForm->add_fld('name2'     ,'text-label', label: $texts->get_fd_message('label_last_name'));
      $oForm->add_fld('geburtstag','date-label', label: $texts->get_fd_message('label_birthday'));

      $oForm->add_fld('email'     ,'text-label', label: $texts->get_fd_message('label_email'));
      $oForm->add_fld('emailbill' ,'text-label', label: $texts->get_fd_message('label_billing_email'));
      $oForm->add_fld('telefon'   ,'text-label', label: $texts->get_fd_message('label_phone'));
      $oForm->add_fld('handy'     ,'text-label', label: $texts->get_fd_message('label_mobile'));
      $oForm->add_fld('fax'       ,'text-label', label: $texts->get_fd_message('label_fax'));

      $oForm->add_fld('strasse'   ,'text-label', label: $texts->get_fd_message('label_street'));
      $oForm->add_fld('land'      ,'select-single-label', label: $texts->get_fd_message('label_country'), options: $options_land);
      $oForm->add_fld('plz'       ,'text-label', label: $texts->get_fd_message('label_zip'));
      $oForm->add_fld('ort'       ,'text-label', label: $texts->get_fd_message('label_city'));

      $oForm->add_fld('language'  ,'select-single-label', label: $texts->get_fd_message('label_language'), options: $options_language);
      $oForm->add_fld('design'    ,'text-label', label: $texts->get_fd_message('label_design'));
      $oForm->add_fld('color'     ,'text-label', label: $texts->get_fd_message('label_color'));
      $oForm->add_fld('login_pid' ,'text-label', label: $texts->get_fd_message('label_login_page'));
      $oForm->add_fld('logout_pid','text-label', label: $texts->get_fd_message('label_logout_page'));

      if($oForm->submit()) {
         $pas = (string)$oForm->get_post_data('pass', '', '*');
         $pasRepeat = (string)$oForm->get_post_data(
            'pass_repeat',
            '',
            '*'
         );
         $passwordErrorMessage = '';
         if ($pas !== '' || $pasRepeat !== '') {
            $passwordErrors = \dbxPasswordPolicy::errors(
               $pas,
               $pasRepeat,
               $currentPasswordHash,
               $passwordMinLength
            );
            if (isset($passwordErrors['password'])) {
               $oForm->add_fld_error('pass', $passwordErrors['password']);
            }
            if (isset($passwordErrors['repeat'])) {
               $oForm->add_fld_error(
                  'pass_repeat',
                  $passwordErrors['repeat']
               );
            }
            $passwordErrorMessage = implode(
               ' ',
               array_values(array_unique($passwordErrors))
            );
         }
         if(!$oForm->errors()) {
            $change = $oForm->changed();
            if ($change) {
               $saveRid = (int) $rid;

               if ($pas !== '') {
                  $oForm->_post['pass'] = password_hash(
                     $pas,
                     PASSWORD_DEFAULT
                  );
                  $settings = json_decode(
                     (string)($data['settings'] ?? ''),
                     true
                  );
                  $settings = is_array($settings) ? $settings : array();
                  unset($settings['password_reset_required']);
                  $settings['password_changed_at'] = date(DATE_ATOM);
                  $oForm->_post['settings'] = json_encode(
                     $settings,
                     JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                  );
               } else {
                  unset($oForm->_post['pass']);
               }
               unset($oForm->_post['pass_repeat']);

               if ($saveRid > 0) {
                  unset($oForm->_post['id']);
               }

               $ok = $oForm->save_post($dd, $saveRid);
               if ($ok) {
                  $rid = (int) $oForm->_rid;
                  $oForm->_action = '?dbx_modul=dbxUser_admin&dbx_run1=user&dbx_run2=edit_profil&rid=' . $rid;
               }

               if ( $ok) $oForm->_msg_success = $texts->get_fd_message('user_saved');
               if (!$ok) $oForm->_msg_error   = $texts->get_fd_message('user_save_error');
            } else {
               $oForm->_msg_success = $texts->get_fd_message('no_change');
            }
         } else {
            $err_flds = '';
            foreach ($oForm->_errors as $key => $value) {
               $err_flds .= $key . ' ';
            }
            $oForm->_msg_error = $passwordErrorMessage !== ''
               ? $passwordErrorMessage
               : $texts->get_fd_message('check_input')
                  . ' (' . trim($err_flds) . ')';
         }
      }

      $rid = $oForm->_data['id'] ?? $rid;
      $oForm->add_obj('obs_rid', 'dbx|observe', 'name=usr_rid&value=' . (int) $rid);
      $oForm->add_obj('avatar', 'obj-value', dbx()->get_include_obj('dbxUser_avatar')->run((int) $rid));

      $content = $oForm->run();
      return $content;
   }
}

?>
