<?php
namespace dbx\dbxUser;

require_once dirname(__DIR__, 3) . '/include/dbxPasswordPolicy.class.php';

class dbxUser_profil {

   private string $ddUser = 'dbxUser';

   private function design_options(): array {
      $options = array();
      foreach (glob(dbx()->get_base_dir() . 'dbx/design/*', GLOB_ONLYDIR) ?: array() as $dir) {
         $name = basename($dir);
         if ($name !== '') {
            $options[$name] = $name;
         }
      }

      return $options ?: array('dbxapp' => 'dbxapp');
   }

   private function color_options($texts): array {
      return array(
         'hell' => $texts->get_fd_message('color_light'),
         'gelb' => $texts->get_fd_message('color_yellow'),
         'rot' => $texts->get_fd_message('color_red'),
         'gruen' => $texts->get_fd_message('color_green'),
         'blau' => $texts->get_fd_message('color_blue'),
         'dunkel' => $texts->get_fd_message('color_dark'),
      );
   }

   public function run() {
      $uid = (int)dbx()->user();

      $db = dbx()->get_system_obj('dbxDB');
      $data = $db->select1($this->ddUser, $uid);
      $oForm = dbx()->get_system_obj('dbxForm');
      $oForm->init('dbxUser_profil', 'form-profil');
      $oForm->_fd = 'dbxUser|user-profile';
      $oForm->load_fd_messages();
      if (!is_array($data)) {
         return '<div class="alert alert-warning">'
            . $oForm->get_fd_message('user_not_found')
            . '</div>';
      }
      $data['password_new'] = '';
      $data['password_new2'] = '';
      $passwordMinLength = \dbxPasswordPolicy::minimumLength();
      $oForm->add_rep('password_min_length', (string)$passwordMinLength);
      $oForm->add_rep(
         'password_length_recommendation',
         $passwordMinLength < 12 ? ' · 12+ empfohlen' : ''
      );
      $designOptions = $this->design_options();
      $colorOptions = $this->color_options($oForm);
      if (!isset($designOptions[(string)($data['design'] ?? '')])) {
         $data['design'] = array_key_first($designOptions);
      }
      if (!isset($colorOptions[(string)($data['color'] ?? '')])) {
         $data['color'] = 'hell';
      }

      $oForm->set_workflow_scope('self-' . $uid);
      $oForm->_dd = $this->ddUser;
      $oForm->_fld_id = 'id';
      $oForm->_data = $data;
      $oForm->_msg_info = $oForm->get_fd_message('profile_info');
      $oForm->_action = '?dbx_modul=dbxUser&dbx_run1=user&dbx_run2=edit_profil';
      $oForm->set_activ_id($uid);
      $oForm->_rid = $uid;
      $oForm->set_state_value('rid', $uid);
      $oForm->add_module_bar(
         $oForm->get_fd_message('profile_title'),
         'bi-person-circle',
         $oForm->get_fd_message('profile_subtitle')
      );
      $oForm->prepare_form_shell(array(
         'class' => 'dbx-user-profile-form',
         'form_attrs' => 'enctype="multipart/form-data"',
      ));
      $oForm->add_module_bar_form_actions(array(
         'save'       => true,
         'delete'     => false,
         'reload'     => true,
         'reload_url' => '?dbx_modul=dbxUser&dbx_run1=user&dbx_run2=edit_profil',
      ));

      $avatarObj = dbx()->get_include_obj('dbxUser_avatar');
      $oForm->add_obj('obs_rid', 'dbx|observe', 'name=usr_rid&value=' . (int)$uid);
      $oForm->add_obj('avatar', 'obj-value', $avatarObj->run());

      $oForm->add_fld('name', 'text-label', $oForm->get_fd_message('label_name'), 'varchar|max=255');
      $oForm->add_fld('name2', 'text-label', $oForm->get_fd_message('label_name2'), 'varchar|max=255');
      $oForm->add_fld('email', 'text-label', $oForm->get_fd_message('label_email'), 'email|max=255');
      $oForm->add_fld('telefon', 'text-label', $oForm->get_fd_message('label_phone'), 'varchar|max=64');
      $oForm->add_fld('handy', 'text-label', $oForm->get_fd_message('label_mobile'), 'varchar|max=64');
      $oForm->add_fld('strasse', 'text-label', $oForm->get_fd_message('label_street'), 'varchar|max=255');
      $oForm->add_fld('plz', 'text-label', $oForm->get_fd_message('label_postcode'), 'varchar|max=16');
      $oForm->add_fld('ort', 'text-label', $oForm->get_fd_message('label_city'), 'varchar|max=255');
      $oForm->add_fld('land', 'text-label', $oForm->get_fd_message('label_country'), 'parameter|max=32');
      $oForm->add_fld('language', 'select-single-label', $oForm->get_fd_message('label_language'), 'parameter|max=3', options: array(
         'de' => $oForm->get_fd_message('language_de'),
         'en' => $oForm->get_fd_message('language_en'),
         'es' => $oForm->get_fd_message('language_es'),
      ));
      $oForm->add_fld('design', 'select-single-label', $oForm->get_fd_message('label_design'), 'parameter|max=32', options: $designOptions);
      $oForm->add_fld('color', 'select-single-label', $oForm->get_fd_message('label_color'), 'parameter|max=32', options: $colorOptions);
      $oForm->add_fld(
         'password_new',
         'password-label',
         $oForm->get_fd_message('label_password_new'),
         'varchar|max=128',
         placeholder: $oForm->get_fd_message('password_unchanged_placeholder'),
         tooltip: 'Nur ausfüllen, wenn das Passwort geändert werden soll.'
      );
      $oForm->add_fld(
         'password_new2',
         'password-label',
         $oForm->get_fd_message('label_password_repeat'),
         'varchar|max=128',
         placeholder: $oForm->get_fd_message('password_repeat_placeholder'),
         tooltip: 'Das neue Passwort zur Kontrolle noch einmal eingeben.'
      );


      if ($oForm->submit()) {
         $passwordNew = (string)$oForm->get_post_data('password_new', '', '*');
         $passwordNew2 = (string)$oForm->get_post_data('password_new2', '', '*');
         $passwordChanged = false;
         $passwordErrorMessage = '';

         if ($passwordNew !== '' || $passwordNew2 !== '') {
            $passwordErrors = \dbxPasswordPolicy::errors(
               $passwordNew,
               $passwordNew2,
               (string)($data['pass'] ?? ''),
               $passwordMinLength
            );
            if (isset($passwordErrors['password'])) {
               $oForm->add_fld_error(
                  'password_new',
                  $passwordErrors['password']
               );
            }
            if (isset($passwordErrors['repeat'])) {
               $oForm->add_fld_error(
                  'password_new2',
                  $passwordErrors['repeat']
               );
            }
            $passwordErrorMessage = implode(
               ' ',
               array_values(array_unique($passwordErrors))
            );
            if ($passwordErrors === array()) {
               $passwordChanged = true;
            }
         }

         if (!$oForm->errors() && !$oForm->warnings()) {
            $values = array(
               'name'     => $oForm->get_post('name', '', 'varchar|max=255'),
               'name2'    => $oForm->get_post('name2', '', 'varchar|max=255'),
               'email'    => $oForm->get_post('email', '', 'email|max=255'),
               'telefon'  => $oForm->get_post('telefon', '', 'varchar|max=64'),
               'handy'    => $oForm->get_post('handy', '', 'varchar|max=64'),
               'strasse'  => $oForm->get_post('strasse', '', 'varchar|max=255'),
               'plz'      => $oForm->get_post('plz', '', 'varchar|max=16'),
               'ort'      => $oForm->get_post('ort', '', 'varchar|max=255'),
               'land'     => $oForm->get_post('land', '', 'parameter|max=32'),
               'language' => $oForm->get_post('language', 'de', 'parameter|max=3'),
               'design'   => $oForm->get_post('design', (string)array_key_first($designOptions), 'parameter|max=32'),
               'color'    => $oForm->get_post('color', 'hell', 'parameter|max=32'),
               'settings' => (string)($data['settings'] ?? '{}'),
            );
            if (!isset($designOptions[$values['design']])) {
               $values['design'] = (string)array_key_first($designOptions);
            }
            if (!isset($colorOptions[$values['color']])) {
               $values['color'] = 'hell';
            }
            if ($passwordChanged) {
               $values['pass'] = password_hash($passwordNew, PASSWORD_DEFAULT);
               $settings = json_decode((string)($data['settings'] ?? ''), true);
               $settings = is_array($settings) ? $settings : array();
               unset($settings['password_reset_required']);
               $settings['password_changed_at'] = date(DATE_ATOM);
               $values['settings'] = json_encode(
                  $settings,
                  JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
               );
            }

            $ok = $db->update($this->ddUser, $values, $uid);
            $avatarSaved = false;
            $avatarError = '';
            if ($ok && is_object($avatarObj) && method_exists($avatarObj, 'save_upload')) {
               $avatarSaved = $avatarObj->save_upload($uid, $db, $data, $oForm, $avatarError);
               if ($avatarError !== '') {
                  $ok = false;
                  $oForm->_msg_error = $avatarError;
               }
            }
            $oForm->_msg_success = $ok
               ? ($passwordChanged && $avatarSaved
                  ? $oForm->get_fd_message('profile_password_avatar_saved')
                  : ($passwordChanged
                     ? $oForm->get_fd_message('profile_password_saved')
                     : ($avatarSaved
                        ? $oForm->get_fd_message('profile_avatar_saved')
                        : $oForm->get_fd_message('profile_saved'))))
               : $oForm->get_fd_message('profile_save_error');
         } else {
            $oForm->_msg_error = $passwordErrorMessage !== ''
               ? $passwordErrorMessage
               : $oForm->get_fd_message('check_input');
         }
      }

      return $oForm->run();
   }
}
?>
