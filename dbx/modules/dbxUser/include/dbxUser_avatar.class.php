<?php
namespace dbx\dbxUser;
dbx()->use_system_class('dbxForm');

class dbxUser_avatar {

   private string $ddUser = 'dbxUser';

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

   private function has_upload_file($key) {
      return isset($_FILES[$key])
         && is_array($_FILES[$key])
         && (int)($_FILES[$key]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
         && trim((string)($_FILES[$key]['name'] ?? '')) !== '';
   }

   public function save_upload(int $rid, $db, array &$data, $texts, string &$error): bool {
      $error = '';
      if (!$this->has_upload_file('upload_file')) {
         return false;
      }

      $oUpload = dbx()->get_system_obj('dbxUpload');
      $oUpload->upload($_FILES['upload_file']);
      $oUpload->allowed = array('image/jpeg', 'image/jpg', 'image/pjpeg', 'image/png', 'image/x-png', 'image/webp', 'image/x-webp', 'image/gif');
      $oUpload->file_max_size = 2 * 1024 * 1024;
      $oUpload->file_overwrite = true;
      $oUpload->file_new_name_body = 'avatar-' . $rid;
      $oUpload->file_new_name_ext = 'webp';
      $oUpload->image_convert = 'webp';
      $oUpload->webp_quality = 86;
      $oUpload->image_resize = true;
      $oUpload->image_ratio_crop = true;
      $oUpload->image_x = 640;
      $oUpload->image_y = 640;
      $oUpload->process($this->avatar_dir());

      if ($oUpload->processed) {
         $ok = $db->update($this->ddUser, array('avatar' => $oUpload->file_dst_name), $rid);
         if ($ok) {
            $data['avatar'] = $oUpload->file_dst_name;
            $this->remove_old_avatar_files($rid, $oUpload->file_dst_name);
            $oUpload->clean();
            return true;
         }
         $error = $texts->get_fd_message('avatar_save_error');
      } else {
         $error = $texts->format_fd_message(
            'upload_error',
            array('error' => $oUpload->error)
         );
      }

      $oUpload->clean();
      return false;
   }

   public function run() {
      $uid = (int)dbx()->user();

      $rid = $uid;

      $db = dbx()->get_system_obj('dbxDB');
      $data = $db->select1($this->ddUser, $rid);
      $oForm = new \dbxForm();
      $oForm->init('dbxUser_avatar', 'form-avatar');
      $oForm->_fd = 'dbxUser|user-profile';
      $oForm->load_fd_messages();
      if (!is_array($data)) {
         return '<div class="alert alert-warning">'
            . $oForm->get_fd_message('user_not_found')
            . '</div>';
      }
      $oForm->set_workflow_scope('self-' . $uid);
      $oForm->_data = $data;
      $oForm->_dd = $this->ddUser;
      $oForm->_action = '?dbx_modul=dbxUser&dbx_run1=user&dbx_run2=edit_avatar';
      $oForm->_msg_info = '';
      $oForm->_rid = $uid;
      $oForm->set_state_value('rid', $uid);

      if ($oForm->submit()) {
         if (!$this->has_upload_file('upload_file')) {
            $oForm->_msg_error = $oForm->get_fd_message('avatar_select_file');
         } else {
            $avatarError = '';
            $ok = $this->save_upload($rid, $db, $data, $oForm, $avatarError);
            $oForm->_msg_success = $ok ? $oForm->get_fd_message('avatar_saved') : '';
            $oForm->_msg_error = $ok ? '' : $avatarError;
         }
      }

      $oForm->add_obj(
         'avatar_preview',
         'obj-value',
         '<img class="dbx-avatar-img" src="'
            . $this->avatar_url($data['avatar'] ?? '')
            . '?' . time()
            . '" alt="' . dbx()->esc($oForm->get_fd_message('avatar_alt')) . '">'
      );
      $jsMessages = json_encode(
         array(
            'upload_running' => $oForm->get_fd_message('upload_running'),
            'file_selected' => $oForm->get_fd_message('upload_file_selected'),
            'upload_ready' => $oForm->get_fd_message('upload_ready'),
            'save_profile' => $oForm->get_fd_message('upload_save_profile'),
         ),
         JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
      );
      $oForm->add_js_code(str_replace('{dbx_avatar_messages}', $jsMessages ?: '{}', <<<'JS'
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

      return $oForm->run();
   }
}
?>
