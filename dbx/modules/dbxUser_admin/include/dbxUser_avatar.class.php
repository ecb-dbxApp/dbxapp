<?php
namespace dbx\dbxUser_admin;
dbx()->use_system_class('dbxForm');

Class dbxUser_avatar {

  private string $ddUser = 'dbxUser';

  private function avatar_dir() {
    $dir = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/dbxUser/img/avatar/');
    if (!is_dir($dir)) {
      @mkdir($dir, 0777, true);
    }
    return $dir;
  }

  private function avatar_file($file) {
    $file = trim((string) $file);
    if ($file === '' || !preg_match('/^[A-Za-z0-9_.-]+\.(png|jpg|jpeg|webp|gif)$/i', $file)) {
      return 'avatar-0.png';
    }

    $path = $this->avatar_dir() . $file;
    return is_file($path) ? $file : 'avatar-0.png';
  }

  private function avatar_url($file) {
    $file = $this->avatar_file($file);
    return dbx()->get_base_url() . 'dbx/modules/dbxUser/img/avatar/' . rawurlencode($file);
  }

  private function has_upload_file($key) {
    return isset($_FILES[$key])
      && is_array($_FILES[$key])
      && (int)($_FILES[$key]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
      && trim((string)($_FILES[$key]['name'] ?? '')) !== '';
  }

  public function run($rid = 0) {
    $texts = new \dbxForm();
    $texts->init('dbxUser_admin_avatar_texts');
    $texts->_fd = 'dbxUser_admin|user-admin';
    $texts->load_fd_messages();
    $texts->set_form_help_enabled(false);
    $rid = (int) $rid;
    if ($rid <= 0) {
      $rid = (int) dbx()->get_request_var('rid', 0, 'int');
    }
    $db  = dbx()->get_system_obj('dbxDB');

    $data = $rid ? $db->select1($this->ddUser, $rid, array('id', 'avatar')) : array();
    if (!is_array($data)) {
      $data = array();
    }

    $uid = (int)($data['id'] ?? 0);
    $avatar = $this->avatar_file($data['avatar'] ?? '');

    $oForm = new \dbxForm();
    $oForm->init('dbxUser_admin_avatar_' . (int) $rid, 'form-avatar');
    $oForm->_fd = 'dbxUser_admin|user-admin';
    $oForm->load_fd_messages();
    $oForm->set_workflow_scope('admin-avatar-' . (int) $rid);
    $oForm->_data = $data;
    $oForm->_dd = $this->ddUser;
    $oForm->_rid = (int) $rid;
    $oForm->_action = '?dbx_modul=dbxUser_admin&dbx_run1=user&dbx_run2=edit_avatar&rid=' . (int) $rid;
    $oForm->_msg_info = '';
    $oForm->set_state_value('rid', (int) $rid);
    $oForm->add_rep('rid', (int) $rid);
    $oForm->add_rep('can_upload', ($rid > 0 && $uid > 0) ? 1 : 0);
    $oForm->add_rep('cannot_upload', ($rid > 0 && $uid > 0) ? 0 : 1);

    if ($oForm->submit()) {
      if ($rid <= 0 || $uid <= 0) {
        $oForm->_msg_error = $texts->get_fd_message('avatar_after_save');
      } elseif (!$this->has_upload_file('upload_file')) {
        $oForm->_msg_error = $texts->get_fd_message('avatar_select_file');
      } else {
        $oUpload = dbx()->get_system_obj('dbxUpload');
        $oUpload->upload($_FILES['upload_file']);
        $oUpload->allowed            = array('image/jpeg', 'image/jpg', 'image/pjpeg', 'image/png', 'image/x-png', 'image/webp', 'image/x-webp', 'image/gif');
        $oUpload->file_max_size      = 2 * 1024 * 1024;
        $oUpload->file_overwrite     = true;
        $oUpload->file_new_name_body = 'avatar-' . $uid;
        $oUpload->image_resize       = true;
        $oUpload->image_ratio_crop   = true;
        $oUpload->image_x            = 640;
        $oUpload->image_y            = 640;
        $oUpload->process($this->avatar_dir());

        if ($oUpload->processed) {
          $ok = $db->update($this->ddUser, array('avatar' => $oUpload->file_dst_name), $rid);
          if ($ok) {
            $avatar = $oUpload->file_dst_name;
            $oForm->_msg_success = $texts->get_fd_message('avatar_saved');
          } else {
            $oForm->_msg_error = $texts->get_fd_message('avatar_save_error');
          }
        } else {
          $oForm->_msg_error = $texts->format_fd_message('upload_error', array('error' => $oUpload->error));
        }

        $oUpload->clean();
      }
    }

    if ($rid <= 0 || $uid <= 0) {
      $oForm->_msg_info = $texts->get_fd_message('avatar_after_save');
    }

    $oForm->add_obj('avatar_preview', 'obj-value', '<img src="' . $this->avatar_url($avatar) . '?' . time() . '" alt="Avatar" class="dbx-avatar-img">');
    $oForm->add_rep('avatar_upload_running_json', json_encode($texts->get_fd_message('upload_running'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $oForm->add_rep('avatar_upload_wait_json', json_encode($texts->get_fd_message('upload_wait'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $oForm->add_rep('avatar_upload_ready_json', json_encode($texts->get_fd_message('upload_ready'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $oForm->add_rep('avatar_upload_start_error_json', json_encode($texts->get_fd_message('upload_start_error'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $oForm->add_rep('avatar_select_first_json', json_encode($texts->get_fd_message('avatar_select_first'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $oForm->add_js_code(<<<'JS'
(function () {
  var drop = document.getElementById('admin_avatar_uploader_{i}');
  var form = document.getElementById('dbx_form_{i}');
  if (!drop || !form || drop.dataset.ready === '1') return;
  drop.dataset.ready = '1';

  var input = form.querySelector('input[type="file"][name="upload_file"]');
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
    form.querySelectorAll('.dbx-form-save, button[type="submit"], input[type="submit"]').forEach(function (button) {
      button.disabled = active;
    });
    if (title) title.textContent = active ? {avatar_upload_running_json} : (drop.dataset.fileName || defaultTitle);
    if (meta && active) meta.textContent = {avatar_upload_wait_json};
  }

  function setMessage(message) {
    if (meta && message) meta.textContent = message;
  }

  function setSelected(file) {
    if (!file) return;
    drop.dataset.fileName = file.name || defaultTitle;
    drop.classList.add('has-file');
    if (title) title.textContent = file.name || defaultTitle;
    if (meta) meta.textContent = {avatar_upload_ready_json};
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

  function refresh(html) {
    var targetId = drop.getAttribute('data-dbx_target');
    var target = targetId ? document.getElementById(targetId) : null;
    if (!target) {
      location.reload();
      return;
    }
    target.innerHTML = html;
    if (window.dbx && typeof window.dbx.scan === 'function') {
      window.dbx.scan(target);
    }
  }

  function requestUrl(raw) {
    try {
      var url = new URL(raw || form.getAttribute('action') || form.action || window.location.href, window.location.href);
      if (
        (url.protocol === 'http:' || url.protocol === 'https:') &&
        url.hostname === window.location.hostname &&
        url.port === window.location.port &&
        url.protocol !== window.location.protocol
      ) {
        url.protocol = window.location.protocol;
      }
      return url.href;
    } catch (e) {
      return raw || form.getAttribute('action') || form.action;
    }
  }

  function directUpload(file) {
    if (typeof window.fetch !== 'function') {
      return Promise.reject(new Error('fetch_unavailable'));
    }

    var data = new FormData(form);
    data.set('upload_file', file);
    return fetch(requestUrl(drop.getAttribute('data-dbx_get') || form.getAttribute('action') || form.action), {
      method: 'POST',
      body: data,
      credentials: 'same-origin'
    }).then(function (response) {
      if (!response.ok) throw new Error('HTTP ' + response.status);
      return response.text();
    }).then(refresh);
  }

  function nativeSubmit() {
    if (typeof HTMLFormElement !== 'undefined' && HTMLFormElement.prototype.submit) {
      HTMLFormElement.prototype.submit.call(form);
    } else {
      form.submit();
    }
  }

  function send(file, fileAlreadyOnInput) {
    if (!file || busy) return;

    var inputReady = fileAlreadyOnInput || syncInput(file);
    setSelected(file);
    setBusy(true);

    directUpload(file)
      .catch(function () {
        if (inputReady) {
          nativeSubmit();
        } else {
          setMessage({avatar_upload_start_error_json});
        }
      })
      .finally(function () { setBusy(false); });
  }

  function openPicker() {
    if (!input || busy) return;
    input.value = '';
    if (title) title.textContent = defaultTitle;
    if (meta) meta.textContent = defaultMeta;
    drop.classList.remove('has-file');
    input.click();
  }

  drop.addEventListener('click', openPicker);
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

  form.addEventListener('submit', function (event) {
    if (busy) {
      event.preventDefault();
      event.stopPropagation();
      return;
    }

    var file = input && input.files && input.files[0];
    if (!file) {
      event.preventDefault();
      event.stopPropagation();
      setMessage({avatar_select_first_json});
      drop.focus();
      return;
    }

    event.preventDefault();
    event.stopPropagation();
    send(file, true);
  });

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
JS);

    return $oForm->run();
  }
}

?>
