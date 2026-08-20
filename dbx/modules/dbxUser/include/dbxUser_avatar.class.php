<?php
namespace dbx\dbxUser;
dbx()->get_system_obj('dbxForm', 'use');
require_once __DIR__ . '/dbxUserUpload.class.php';

class dbxUser_avatar {

   private string $dd_user = 'dbxUser';

   private function avatar_dir() {
      $dir = dbx()->os_path(dbx()->get_base_dir() . 'files/user/avatar/');
      if (!is_dir($dir)) {
         @mkdir($dir, 0777, true);
      }
      return $dir;
   }

   private function legacy_avatar_dir() {
      return dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/dbxUser/img/avatar/');
   }

   private function avatar_file($file) {
      $file = trim((string)$file);
      if ($file === '' || !preg_match('/^avatar-[0-9]+\.(?:webp|png|jpe?g|gif)$/i', $file)) {
         return 'avatar-0.png';
      }
      if (file_exists($this->avatar_dir() . $file)) {
         return $file;
      }
      if (file_exists($this->legacy_avatar_dir() . $file)) {
         return $file;
      }
      return 'avatar-0.png';
   }

   private function avatar_url($file) {
      $file = $this->avatar_file($file);
      if (file_exists($this->avatar_dir() . $file)) {
         return dbx()->get_base_url() . 'files/user/avatar/' . rawurlencode($file);
      }
      return dbx()->get_base_url() . 'dbx/modules/dbxUser/img/avatar/' . rawurlencode($file);
   }

   private function remove_old_avatar_files(int $rid, string $keep): void {
      foreach (glob($this->avatar_dir() . 'avatar-' . $rid . '.*') ?: array() as $path) {
         if (basename($path) !== $keep && is_file($path)) {
            @unlink($path);
         }
      }
   }

   public function save_upload(int $rid, $db, array &$data, $texts, string &$error): bool {
      $error = '';
      if (!dbxUserUpload::has_file('upload_file')) {
         return false;
      }

      $o_upload = dbx()->get_system_obj('dbxUpload');
      $o_upload->upload($_FILES['upload_file']);
      $o_upload->allowed = array('image/jpeg', 'image/jpg', 'image/pjpeg', 'image/png', 'image/x-png', 'image/webp', 'image/x-webp', 'image/gif');
      $o_upload->file_max_size = 2 * 1024 * 1024;
      $o_upload->file_overwrite = true;
      $o_upload->file_new_name_body = 'avatar-' . $rid;
      $o_upload->file_new_name_ext = 'webp';
      $o_upload->image_convert = 'webp';
      $o_upload->webp_quality = 86;
      $o_upload->image_resize = true;
      $o_upload->image_ratio_crop = true;
      $o_upload->image_x = 640;
      $o_upload->image_y = 640;
      $o_upload->process($this->avatar_dir());

      if ($o_upload->processed) {
         $ok = $db->update($this->dd_user, array('avatar' => $o_upload->file_dst_name), $rid);
         if ($ok) {
            $data['avatar'] = $o_upload->file_dst_name;
            $this->remove_old_avatar_files($rid, $o_upload->file_dst_name);
            $o_upload->clean();
            return true;
         }
         $error = $texts->get_fd_message('avatar_save_error');
      } else {
         $error = $texts->format_fd_message(
            'upload_error',
            array('error' => $o_upload->error)
         );
      }

      $o_upload->clean();
      return false;
   }

   public function run() {
      $uid = (int)dbx()->user();

      $rid = $uid;

      $db = dbx()->get_system_obj('dbxDB');
      $data = $db->select1($this->dd_user, $rid);
      $o_form = new \dbxForm();
      $o_form->init('dbxUser_avatar', 'form-avatar');
      $o_form->set_field_definition('dbxUser|user-profile');
      $o_form->load_fd_messages();
      if (!is_array($data)) {
         return '<div class="alert alert-warning">'
            . $o_form->get_fd_message('user_not_found')
            . '</div>';
      }
      $o_form->set_workflow_scope('self-' . $uid);
      $o_form->set_data($data);
      $o_form->set_data_definition($this->dd_user);
      $o_form->set_action('?dbx_modul=dbxUser&dbx_run1=user&dbx_run2=edit_avatar');
      $o_form->_msg_info = '';
      $o_form->set_rid((int)$uid);
      $o_form->set_state_value('rid', $uid);

      if ($o_form->submit()) {
         if (!dbxUserUpload::has_file('upload_file')) {
            $o_form->_msg_error = $o_form->get_fd_message('avatar_select_file');
         } else {
            $avatar_error = '';
            $ok = $this->save_upload($rid, $db, $data, $o_form, $avatar_error);
            $o_form->_msg_success = $ok ? $o_form->get_fd_message('avatar_saved') : '';
            $o_form->_msg_error = $ok ? '' : $avatar_error;
         }
      }

      $o_form->add_obj(
         'avatar_preview',
         'obj-value',
         '<img class="dbx-avatar-img" src="'
            . $this->avatar_url($data['avatar'] ?? '')
            . '?' . time()
            . '" alt="' . dbx()->esc($o_form->get_fd_message('avatar_alt')) . '">'
      );
      $js_messages = json_encode(
         array(
            'upload_running' => $o_form->get_fd_message('upload_running'),
            'file_selected' => $o_form->get_fd_message('upload_file_selected'),
            'upload_ready' => $o_form->get_fd_message('upload_ready'),
            'save_profile' => $o_form->get_fd_message('upload_save_profile'),
         ),
         JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
      );
      $o_form->add_js_code(str_replace('{dbx_avatar_messages}', $js_messages ?: '{}', <<<'JS'
(function () {
  var messages = {dbx_avatar_messages};
  var drop = document.getElementById('uploader_{i}');
  var panel = document.getElementById('dbx_avatar_panel_{i}');
  if (!drop || !panel || drop.dataset.ready === '1') return;
  drop.dataset.ready = '1';

  var input = panel.querySelector('input[type="file"][name="upload_file"]');
  var title = drop.querySelector('.dbx-avatar-drop-title');
  var meta = drop.querySelector('.dbx-avatar-drop-meta');
  var defaultTitle = title ? title.textContent : '';
  var defaultMeta = meta ? meta.textContent : '';
  var busy = false;

  function setBusy(active) {
    busy = active;
    drop.classList.toggle('is-active', active);
    drop.classList.toggle('is-uploading', active);
    drop.setAttribute('aria-busy', active ? 'true' : 'false');
    if (title) title.textContent = active ? messages.upload_running : (drop.dataset.fileName || defaultTitle);
    if (meta && active) meta.textContent = messages.file_selected;
  }

  function setMessage(message) {
    if (meta && message) meta.textContent = message;
  }

  function setSelected(file) {
    if (!file) return;
    drop.dataset.fileName = file.name || defaultTitle;
    drop.classList.add('has-file');
    if (title) title.textContent = file.name || defaultTitle;
    if (meta) meta.textContent = messages.upload_ready;
  }

  function syncInput(file) {
    if (!input || !file) return false;
    try {
      var transfer = new DataTransfer();
      transfer.items.add(file);
      input.files = transfer.files;
      return input.files && input.files.length === 1;
    } catch (e) {
      return false;
    }
  }

  function send(file, fileAlreadyOnInput) {
    if (!file || busy) return;

    fileAlreadyOnInput || syncInput(file);
    setSelected(file);
    setMessage(messages.save_profile);
  }

  function openPicker() {
    if (!input || busy) return;
    input.value = '';
    if (title) title.textContent = defaultTitle;
    if (meta) meta.textContent = defaultMeta;
    drop.classList.remove('has-file');
    input.click();
  }

  drop.addEventListener('click', function () {
    if (busy) return false;
    input.value = '';
    if (title) title.textContent = defaultTitle;
    if (meta) meta.textContent = defaultMeta;
    drop.classList.remove('has-file');
  });
  drop.addEventListener('keydown', function (event) {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      openPicker();
    }
  });

  if (input) {
    input.addEventListener('change', function () {
      send(input.files && input.files[0], true);
    });
  }

  var outerForm = panel.closest('form');
  if (outerForm) {
    outerForm.addEventListener('submit', function () {
      if (input && input.files && input.files[0]) setBusy(true);
    });
  }

  ['dragenter', 'dragover'].forEach(function (eventName) {
    drop.addEventListener(eventName, function (event) {
      event.preventDefault();
      setBusy(true);
    });
  });

  ['dragleave', 'drop'].forEach(function (eventName) {
    drop.addEventListener(eventName, function (event) {
      event.preventDefault();
      setBusy(false);
    });
  });

  drop.addEventListener('drop', function (event) {
    event.preventDefault();
    event.stopPropagation();
    var files = event.dataTransfer && event.dataTransfer.files;
    send(files && files[0], false);
  });
})();
JS));

      return $o_form->run();
   }
}
?>
