<?php
namespace dbx\dbxEditor;



class dbxEditor {

  /**
   * Erzeugt den stabilen dbxForm-Sicherheitskontext des Dateieditors.
   *
   * Der ACE-Editor sendet seine Mutationen per AJAX und benötigt deshalb
   * keine sichtbaren dbxForm-Felder. Er verwendet aber dieselbe
   * sessiongebundene Submit-Prüfung und Token-Rotation wie normale Formulare.
   */
  private function editor_form(): \dbxForm {
      dbx()->get_system_obj('dbxForm');
      $form = new \dbxForm();
      $form->init('dbx-editor-file');
      $form->_msg_info = '';
      $form->set_form_help_enabled(false);
      return $form;
  }

  /**
   * Prüft eine schreibende Editor-Anfrage und liefert den Folgetoken.
   *
   * @return array{submitted:bool,security:array{name:string,value:string}}
   */
  private function mutation_state(): array {
      $form = $this->editor_form();
      return array(
          'submitted' => (bool)$form->submit(),
          'security' => $form->get_security_data(),
      );
  }

  /**
   * Beendet eine Editor-AJAX-Anfrage mit einer einheitlichen JSON-Antwort.
   *
   * @param array{name:string,value:string} $security
   */
  private function json_result(bool $ok, string $msg, array $security): void {
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(array(
          'ok' => $ok ? 1 : 0,
          'msg' => $msg,
          'security' => $security,
      ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
  }

  public function run() {

      $modul = dbx()->get_system_var('dbx_modul');
      $run1  = dbx()->get_modul_var('dbx_run1','dbx');
      $file  = dbx()->get_modul_var('file','none','*');
      $base  = dbx()->get_base_dir();
      $ajax  = dbx()->get_system_var('dbx_ajax',0,'int');
      $xedit = dbx()->get_system_var('dbx_edit',0,'int');
      dbx()->set_system_var('dbx_edit',0);

      $content='';
      $mutation = null;

      if (in_array($run1, array('save', 'delete', 'rename', 'copy'), true)) {
          $mutation = $this->mutation_state();
          if (!$mutation['submitted']) {
              dbx()->sys_msg(
                  'security',
                  'dbxEditor',
                  $run1,
                  'Editor-Mutation ohne gueltigen dbxForm-Token abgewiesen',
                  'ip=' . (string)($_SERVER['REMOTE_ADDR'] ?? '')
              );
              $this->json_result(false, 'security_check_failed', $mutation['security']);
          }
      }

      switch ($run1) {

        case 'tree':
          $content = $this->render_tree($base);
        break;      

        case 'edit':

          // 🔒 Sicherheit
          if (!$this->is_valid_file($file)) {
            return "invalid file";
          }

          $full = $base.$file;

          if (!file_exists($full)) {
            return "file not found ($file)";
          }

          $txt = file_get_contents($full);
          $data['file']=$file;
          $data['txt'] = dbx()->norep(htmlspecialchars($txt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
          $security = $this->editor_form()->get_security_data();
          $data['security_name'] = htmlspecialchars($security['name'], ENT_QUOTES, 'UTF-8');
          $data['security_value'] = htmlspecialchars($security['value'], ENT_QUOTES, 'UTF-8');

          $o_tpl = dbx()->get_system_obj('dbxTPL');
          $content = $o_tpl->get_tpl('dbxEditor|editor', $data);
          
        break;


        case 'save':

          if (!$this->is_valid_file($file)) {
            $this->json_result(false, 'invalid_file', $mutation['security']);
          }

          $full = $base.$file;
          // Der Quelltext darf PHP, HTML, JavaScript und CSS enthalten. Nur
          // der Dateipfad und der dbxForm-Token werden als Eingaben geprüft.
          $data = $_POST['content'] ?? '';

          if (file_put_contents($full, $data) === false) {
            $this->json_result(false, 'save_failed', $mutation['security']);
          }
          $this->clear_dd_file_cache($file);
          $this->json_result(true, 'saved', $mutation['security']);
        
        case 'delete': 
            $content="delete ($base) ($file) ajax=($ajax)";
            dbx()->debug($content);

            if (!$this->is_valid_file($file)) {
                $this->json_result(false, 'invalid file', $mutation['security']);
            }

            $full = $base.$file;

            if (!file_exists($full)) {
                $this->json_result(false, 'file not found', $mutation['security']);
            }

            if (!unlink($full)) {
                $this->json_result(false, 'delete failed', $mutation['security']);
            }

            $this->clear_dd_file_cache($file);
            $this->json_result(true, 'deleted', $mutation['security']);


        break;  
 
        case 'rename':

            $old = dbx()->get_modul_var('old','','alphanum+/');
            $new = dbx()->get_modul_var('new','','alphanum+/');

            if (!$this->is_valid_file($old) || !$this->is_valid_file($new)) {
                $this->json_result(false, 'invalid file', $mutation['security']);
            }

            $full_old = $base.$old;
            $full_new = $base.$new;

            if (!file_exists($full_old)) {
                $this->json_result(false, 'source not found', $mutation['security']);
            }

            if (file_exists($full_new)) {
                $this->json_result(false, 'target exists', $mutation['security']);
            }

            if (!rename($full_old, $full_new)) {
                $this->json_result(false, 'rename failed', $mutation['security']);
            }
            $this->clear_dd_file_cache($old);
            $this->clear_dd_file_cache($new);
            $this->json_result(true, 'renamed', $mutation['security']);

        break;         
        
        case 'copy':

            $old = dbx()->get_modul_var('old','','alphanum+/.');
            $new = dbx()->get_modul_var('new','','alphanum+/.');

            if (!$this->is_valid_file($old) || !$this->is_valid_file($new)) {
                $this->json_result(false, 'invalid file', $mutation['security']);
            }

            $full_old = $base . $old;
            $full_new = $base . $new;

            if (!file_exists($full_old)) {
                $this->json_result(false, 'source not found', $mutation['security']);
            }

            if (file_exists($full_new)) {
                $this->json_result(false, 'target exists', $mutation['security']);
            }

            if (!copy($full_old, $full_new)) {
                $this->json_result(false, 'copy failed', $mutation['security']);
            }

            $this->clear_dd_file_cache($new);
            $this->json_result(true, 'copied', $mutation['security']);

        break;      


        default:
          $o_tpl=dbx()->get_system_obj('dbxTPL');
          $msg['msg']="Modul=($modul) Action=($run1) is undef.";
          $content=$o_tpl->get_tpl('dbx|alert-warning',$msg);
      } // switch
      dbx()->set_system_var('dbx_edit',$xedit);
      return $content;
  }

  private function clear_dd_file_cache($file) {
    $file = str_replace('\\', '/', (string)$file);

    if (preg_match('#(?:^|/)dbx/modules/([^/]+)/dd/([^/]+)\.dd\.php$#', $file, $match)) {
      $o_dd = dbx()->get_system_obj('dbxDD');
      $o_dd->clear_dd_cache($match[1] . '|' . $match[2]);
    }
  }

  private function is_valid_file($file) {
    if (strpos($file,'..') !== false) return false;

    $ext = pathinfo($file, PATHINFO_EXTENSION);
    return in_array($ext, ['css','htm','js','php']);
  }

  private function render_tree($dir, $base = null) {

      if ($base === null) $base = $dir;

      $html = '<ul>';

      $items = scandir($dir);

      foreach ($items as $item) {

          if ($item === '.' || $item === '..') continue;

          $full = $dir . '/' . $item;
          $rel  = str_replace($base . '/', '', $full);

          if (is_dir($full)) {

              $html .= '<li>';
              $html .= '<span>' . $item . '</span>';
              $html .= $this->render_tree($full, $base);
              $html .= '</li>';

          } else {

              if (!$this->is_valid_file($item)) continue;

              $html .= '<li>';
              $html .= '<a href="?dbx_modul=dbxEditor&dbx_run1=edit&file=' . urlencode($rel) . '">';
              $html .= $item;
              $html .= '</a>';
              $html .= '</li>';
          }
      }

      $html .= '</ul>';

      return $html;
  }




}
