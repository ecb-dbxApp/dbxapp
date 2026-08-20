<?php
namespace dbx\dbxUser;

require_once dirname(__DIR__, 3) . '/include/dbxPasswordPolicy.class.php';

class dbxUser_profil {

   private string $dd_user = 'dbxUser';

   private function design_options(): array {
      $options = array();
      foreach (dbx()->get_system_obj('dbxPresentation')->get_design_catalog() as $name => $design) {
         $options[$name] = (string)($design['title'] ?? $name);
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
      $data = $db->select1($this->dd_user, $uid);
      $o_form = dbx()->get_system_obj('dbxForm');
      $o_form->init('dbxUser_profil', 'form-profil');
      $o_form->set_field_definition('dbxUser|user-profile');
      $o_form->load_fd_messages();
      if (!is_array($data)) {
         return '<div class="alert alert-warning">'
            . $o_form->get_fd_message('user_not_found')
            . '</div>';
      }
      $data['password_new'] = '';
      $data['password_new2'] = '';
      $password_min_length = \dbxPasswordPolicy::minimum_length();
      $o_form->add_rep('password_min_length', (string)$password_min_length);
      $o_form->add_rep(
         'password_length_recommendation',
         $password_min_length < 12 ? ' · 12+ empfohlen' : ''
      );
      $design_options = $this->design_options();
      $color_options = $this->color_options($o_form);
      if (!isset($design_options[(string)($data['design'] ?? '')])) {
         $data['design'] = array_key_first($design_options);
      }
      if (!isset($color_options[(string)($data['color'] ?? '')])) {
         $data['color'] = 'hell';
      }

      $o_form->set_workflow_scope('self-' . $uid);
      $o_form->set_data_definition($this->dd_user);
      $o_form->_fld_id = 'id';
      $o_form->set_data($data);
      $o_form->_msg_info = $o_form->get_fd_message('profile_info');
      $o_form->set_action('?dbx_modul=dbxUser&dbx_run1=user&dbx_run2=edit_profil');
      $o_form->set_activ_id($uid);
      $o_form->set_rid((int)$uid);
      $o_form->set_state_value('rid', $uid);
      $o_form->add_module_bar(
         $o_form->get_fd_message('profile_title'),
         'bi-person-circle',
         $o_form->get_fd_message('profile_subtitle')
      );
      $o_form->add_rep(
         'bar_actions',
         '<a class="btn btn-outline-primary btn-sm" href="?dbx_modul=dbxUser&amp;dbx_run1=ui_settings">'
         . '<i class="bi bi-sliders2" aria-hidden="true"></i> UI-Einstellungen</a>'
      );
      $o_form->prepare_form_shell(array(
         'class' => 'dbx-user-profile-form',
         'form_attrs' => 'enctype="multipart/form-data"',
      ));
      $o_form->add_module_bar_form_actions(array(
         'save'       => true,
         'delete'     => false,
         'reload'     => true,
         'reload_url' => '?dbx_modul=dbxUser&dbx_run1=user&dbx_run2=edit_profil',
      ));

      $avatar_obj = dbx()->get_include_obj('dbxUser_avatar');
      $o_form->add_obj('obs_rid', 'dbx|observe', 'name=usr_rid&value=' . (int)$uid);
      $o_form->add_obj('avatar', 'obj-value', $avatar_obj->run());

      $o_form->add_fld('name', 'text-label', $o_form->get_fd_message('label_name'), 'varchar|max=255');
      $o_form->add_fld('name2', 'text-label', $o_form->get_fd_message('label_name2'), 'varchar|max=255');
      $o_form->add_fld('email', 'text-label', $o_form->get_fd_message('label_email'), 'email|max=255');
      $o_form->add_fld('telefon', 'text-label', $o_form->get_fd_message('label_phone'), 'varchar|max=64');
      $o_form->add_fld('handy', 'text-label', $o_form->get_fd_message('label_mobile'), 'varchar|max=64');
      $o_form->add_fld('strasse', 'text-label', $o_form->get_fd_message('label_street'), 'varchar|max=255');
      $o_form->add_fld('plz', 'text-label', $o_form->get_fd_message('label_postcode'), 'varchar|max=16');
      $o_form->add_fld('ort', 'text-label', $o_form->get_fd_message('label_city'), 'varchar|max=255');
      $o_form->add_fld('land', 'text-label', $o_form->get_fd_message('label_country'), 'parameter|max=32');
      $o_form->add_fld('language', 'select-single-label', $o_form->get_fd_message('label_language'), 'parameter|max=3', options: array(
         'de' => $o_form->get_fd_message('language_de'),
         'en' => $o_form->get_fd_message('language_en'),
         'es' => $o_form->get_fd_message('language_es'),
      ));
      $o_form->add_fld('design', 'select-single-label', $o_form->get_fd_message('label_design'), 'parameter|max=32', options: $design_options);
      $o_form->add_fld('color', 'select-single-label', $o_form->get_fd_message('label_color'), 'parameter|max=32', options: $color_options);
      $o_form->add_fld(
         'password_new',
         'password-label',
         $o_form->get_fd_message('label_password_new'),
         'varchar|max=128',
         placeholder: $o_form->get_fd_message('password_unchanged_placeholder'),
         tooltip: 'Nur ausfüllen, wenn das Passwort geändert werden soll.'
      );
      $o_form->add_fld(
         'password_new2',
         'password-label',
         $o_form->get_fd_message('label_password_repeat'),
         'varchar|max=128',
         placeholder: $o_form->get_fd_message('password_repeat_placeholder'),
         tooltip: 'Das neue Passwort zur Kontrolle noch einmal eingeben.'
      );


      if ($o_form->submit()) {
         $password_new = (string)$o_form->get_post_data('password_new', '', '*');
         $password_new2 = (string)$o_form->get_post_data('password_new2', '', '*');
         $password_changed = false;
         $password_error_message = '';

         if ($password_new !== '' || $password_new2 !== '') {
            $password_errors = \dbxPasswordPolicy::errors(
               $password_new,
               $password_new2,
               (string)($data['pass'] ?? ''),
               $password_min_length
            );
            if (isset($password_errors['password'])) {
               $o_form->add_fld_error(
                  'password_new',
                  $password_errors['password']
               );
            }
            if (isset($password_errors['repeat'])) {
               $o_form->add_fld_error(
                  'password_new2',
                  $password_errors['repeat']
               );
            }
            $password_error_message = implode(
               ' ',
               array_values(array_unique($password_errors))
            );
            if ($password_errors === array()) {
               $password_changed = true;
            }
         }

         if (!$o_form->errors() && !$o_form->warnings()) {
            $values = array(
               'name'     => $o_form->get_post('name', '', 'varchar|max=255'),
               'name2'    => $o_form->get_post('name2', '', 'varchar|max=255'),
               'email'    => $o_form->get_post('email', '', 'email|max=255'),
               'telefon'  => $o_form->get_post('telefon', '', 'varchar|max=64'),
               'handy'    => $o_form->get_post('handy', '', 'varchar|max=64'),
               'strasse'  => $o_form->get_post('strasse', '', 'varchar|max=255'),
               'plz'      => $o_form->get_post('plz', '', 'varchar|max=16'),
               'ort'      => $o_form->get_post('ort', '', 'varchar|max=255'),
               'land'     => $o_form->get_post('land', '', 'parameter|max=32'),
               'language' => $o_form->get_post('language', 'de', 'parameter|max=3'),
               'design'   => $o_form->get_post('design', (string)array_key_first($design_options), 'parameter|max=32'),
               'color'    => $o_form->get_post('color', 'hell', 'parameter|max=32'),
               'settings' => (string)($data['settings'] ?? '{}'),
            );
            if (!isset($design_options[$values['design']])) {
               $values['design'] = (string)array_key_first($design_options);
            }
            if (!isset($color_options[$values['color']])) {
               $values['color'] = 'hell';
            }
            if ($password_changed) {
               $values['pass'] = password_hash($password_new, PASSWORD_DEFAULT);
               $settings = json_decode((string)($data['settings'] ?? ''), true);
               $settings = is_array($settings) ? $settings : array();
               unset($settings['password_reset_required']);
               $settings['password_changed_at'] = date(DATE_ATOM);
               $values['settings'] = json_encode(
                  $settings,
                  JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
               );
            }

            $ok = $db->update($this->dd_user, $values, $uid);
            $avatar_saved = false;
            $avatar_error = '';
            if ($ok && is_object($avatar_obj) && method_exists($avatar_obj, 'save_upload')) {
               $avatar_saved = $avatar_obj->save_upload($uid, $db, $data, $o_form, $avatar_error);
               if ($avatar_error !== '') {
                  $ok = false;
                  $o_form->_msg_error = $avatar_error;
               }
            }
            $o_form->_msg_success = $ok
               ? ($password_changed && $avatar_saved
                  ? $o_form->get_fd_message('profile_password_avatar_saved')
                  : ($password_changed
                     ? $o_form->get_fd_message('profile_password_saved')
                     : ($avatar_saved
                        ? $o_form->get_fd_message('profile_avatar_saved')
                        : $o_form->get_fd_message('profile_saved'))))
               : $o_form->get_fd_message('profile_save_error');
         } else {
            $o_form->_msg_error = $password_error_message !== ''
               ? $password_error_message
               : $o_form->get_fd_message('check_input');
         }
      }

      return $o_form->run();
   }
}
?>
