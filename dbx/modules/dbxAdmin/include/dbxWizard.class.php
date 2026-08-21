<?php
namespace dbx\dbxAdmin;

class dbxWizard {

   private $texts;

   private function texts() {
      if ($this->texts) {
         return $this->texts;
      }
      $texts = new \dbxForm();
      $texts->set_form_help_enabled(false);
      $texts->set_field_definition('dbxAdmin|module-wizard');
      $texts->load_fd_messages();
      $this->texts = $texts;
      return $this->texts;
   }

   private function text($key, $default = '') {
      return $this->texts()->get_fd_message((string)$key, (string)$default);
   }

   private function esc($value) {
      return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
   }

   private function tpl() {
      return dbx()->get_system_obj('dbxTPL');
   }

   private function ki_module_url(string $modul, string $dd_name): string {
      return '?dbx_modul=dbxKi&dbx_run1=briefing_module_edit&xmodul=' . rawurlencode($modul) . '&dd_name=' . rawurlencode($dd_name);
   }

   private function modules_root() {
      return dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/');
   }

   private function backup_root() {
      $dir = dbx()->os_path(dbx()->get_file_dir() . 'module-backup/');
      if (!is_dir($dir)) {
         @mkdir($dir, 0777, true);
      }
      return $dir;
   }

   private function valid_name($name) {
      return is_string($name) && preg_match('/^[A-Za-z][A-Za-z0-9_]{1,63}$/', $name);
   }

   private function valid_file_name($name) {
      return is_string($name) && preg_match('/^[A-Za-z0-9_.-]+$/', $name) && strpos($name, '..') === false;
   }

   private function module_dir($modul) {
      return dbx()->os_path($this->modules_root() . $modul . DIRECTORY_SEPARATOR);
   }

   private function module_path($modul, $rel) {
      $rel = str_replace(array('\\', "\0"), array('/', ''), (string)$rel);
      $rel = ltrim($rel, '/');
      if ($rel === '' || strpos($rel, '../') !== false || strpos($rel, '..\\') !== false) {
         return '';
      }
      $base = $this->module_dir($modul);
      $path = dbx()->os_path($base . $rel);
      $base_norm = str_replace('\\', '/', rtrim($base, '/\\') . '/');
      $path_norm = str_replace('\\', '/', $path);
      if (strpos($path_norm, $base_norm) !== 0) {
         return '';
      }
      return $path;
   }

   private function module_options($with_new = false) {
      $options = $with_new ? array('' => $this->text('option_choose')) : array();
      $root = $this->modules_root();
      foreach (glob($root . '*', GLOB_ONLYDIR) ?: array() as $dir) {
         $name = basename($dir);
         if ($this->valid_name($name)) {
            $options[$name] = $name;
         }
      }
      ksort($options);
      return $options;
   }

   private function dd_options($with_empty = true) {
      $options = $with_empty ? array('' => $this->text('option_choose_manual')) : array();
      foreach (glob($this->modules_root() . '*', GLOB_ONLYDIR) ?: array() as $dir) {
         $modul = basename($dir);
         if (!$this->valid_name($modul)) {
            continue;
         }
         foreach (glob(dbx()->os_path($dir . '/dd/') . '*.dd.php') ?: array() as $file) {
            $dd = basename($file, '.dd.php');
            if ($this->valid_name($dd)) {
               $options[$modul . '|' . $dd] = $modul . ' | ' . $dd;
            }
         }
      }
      ksort($options);
      return $options;
   }

   private function options($items) {
      return is_array($items) ? $items : array();
   }

   private function collect_input($form = null) {
      $get = function($name, $default = '', $rules = 'parameter') use ($form) {
         if ($form && $form->has_post_value($name)) {
            return $form->post_value($name);
         }
         return dbx()->get_modul_var($name, $default, $rules);
      };

      $target = (string)$get('target_mode', 'new');
      $existing_modul = trim((string)$get('existing_modul', ''));
      $modul  = trim((string)$get('xmodul', ''));
      if ($target === 'existing' && $existing_modul !== '') {
         $modul = $existing_modul;
      }
      if ($modul === '') {
         $modul = trim((string)$get('modul', ''));
      }

      $dd_mode = (string)$get('dd_mode', 'new');
      $dd_ref = trim((string)$get('dd_ref', ''));
      $title = trim((string)$get('title', $modul));
      $dd = trim((string)$get('dd_name', $modul ? $modul . 'Data' : ''));
      if ($dd_ref !== '' && strpos($dd_ref, '|') !== false) {
         list($ref_modul, $ref_dd) = explode('|', $dd_ref, 2);
         if ($target === 'existing' && $modul === '' && $this->valid_name($ref_modul)) {
            $modul = $ref_modul;
         }
         if ($this->valid_name($ref_dd)) {
            $dd = $ref_dd;
         }
      }
      if ($dd === '' && $dd_mode !== 'none' && $modul !== '') {
         $dd = $modul . 'Data';
      }
      $table = trim((string)$get('table_name', $dd));
      if ($table === '' && $dd_mode !== 'none') {
         $table = $dd;
      }
      $db_file = trim((string)$get('db_file', $modul ? $modul . '.db3' : 'modul.db3'));
      if ($modul !== '' && ($db_file === '' || $db_file === 'modul.db3')) {
         $db_file = $modul . '.db3';
      }
      $default_run1 = trim((string)$get('default_run1', 'run'));
      if ($default_run1 === '') {
         $default_run1 = 'run';
      }

      return array(
         'target_mode'      => in_array($target, array('new', 'existing'), true) ? $target : 'new',
         'existing_modul'   => $existing_modul,
         'dd_ref'           => $dd_ref,
         'xmodul'           => $modul,
         'title'            => $title !== '' ? $title : $modul,
         'default_run1'     => $default_run1,
         'default_run2'     => trim((string)$get('default_run2', '')),
         'module_template'  => (string)$get('module_template', 'form_report'),
         'dd_mode'          => $dd_mode,
         'dd_name'          => $dd,
         'table_name'       => $table,
         'db_file'          => $db_file,
         'field_preset'     => (string)$get('field_preset', 'basic'),
         'create_include'   => (int)$get('create_include', 1, 'int'),
         'create_form'      => (int)$get('create_form', 1, 'int'),
         'create_report'    => (int)$get('create_report', 1, 'int'),
         'create_templates' => (int)$get('create_templates', 1, 'int'),
         'overwrite'        => (int)$get('overwrite', 0, 'int'),
         'backup'           => (int)$get('backup', 1, 'int'),
         'sync_mode'        => (string)$get('sync_mode', 'link'),
         'ki_package'       => (int)$get('ki_package', 1, 'int'),
      );
   }

   private function validate_input(array $in, &$errors) {
      $errors = array();
      if (!$this->valid_name($in['xmodul'])) {
         $errors[] = $this->text('validation_module_name');
      }
      if (!in_array($in['module_template'], array('blank', 'form', 'report', 'form_report', 'api'), true)) {
         $errors[] = $this->text('validation_template');
      }
      if (!in_array($in['dd_mode'], array('none', 'new', 'existing'), true)) {
         $errors[] = $this->text('validation_dd_mode');
      }
      if (((int)$in['create_form'] === 1 || (int)$in['create_report'] === 1) && $in['dd_mode'] === 'none') {
         $errors[] = $this->text('validation_dd_required');
      }
      if (($in['module_template'] === 'api' || (int)$in['create_form'] === 1 || (int)$in['create_report'] === 1) && (int)$in['create_include'] !== 1) {
         $errors[] = $this->text('validation_service_required');
      }
      if ((int)$in['create_form'] === 1 && (int)$in['create_templates'] !== 1) {
         $errors[] = $this->text('validation_templates_required');
      }
      if ($in['dd_mode'] !== 'none' && !$this->valid_name($in['dd_name'])) {
         $errors[] = $this->text('validation_dd_name');
      }
      if ($in['dd_mode'] !== 'none' && !$this->valid_name($in['table_name'])) {
         $errors[] = $this->text('validation_table_name');
      }
      if ($in['dd_mode'] !== 'none' && (!$this->valid_file_name($in['db_file']) || !preg_match('/\.db3$/i', $in['db_file']))) {
         $errors[] = $this->text('validation_db_file');
      }
      if ($in['dd_mode'] === 'existing') {
         if ($in['dd_ref'] !== '' && strpos($in['dd_ref'], '|') !== false) {
            list($ref_modul, $ref_dd) = explode('|', $in['dd_ref'], 2);
            if ($ref_modul !== $in['xmodul'] || $ref_dd !== $in['dd_name']) {
               $errors[] = $this->text('validation_dd_module');
            }
         }
         $dd_file = $this->module_path($in['xmodul'], 'dd/' . $in['dd_name'] . '.dd.php');
         if ($dd_file === '' || !is_file($dd_file)) {
            $errors[] = $this->text('validation_dd_missing');
         }
      }

      $dir = $this->module_dir($in['xmodul']);
      if ($in['target_mode'] === 'new' && is_dir($dir)) {
         $errors[] = $this->text('validation_module_exists');
      }
      if ($in['target_mode'] === 'existing' && !is_dir($dir)) {
         $errors[] = $this->text('validation_module_missing');
      }

      return !count($errors);
   }

   private function ensure_dirs($modul, array &$log) {
      $ok = true;
      $root = $this->module_dir($modul);
      if (!is_dir($root) && !@mkdir($root, 0777, true) && !is_dir($root)) {
         $log[] = array('type' => 'error', 'text' => 'Modulverzeichnis konnte nicht erstellt werden.');
         return false;
      }
      foreach (array('cfg', 'dd', 'db', 'fd', 'include', 'js', 'tpl', 'tpl/htm', 'tpl/help', 'tpl/mod', 'tpl/css', 'tpl/img') as $dir) {
         $path = $this->module_path($modul, $dir);
         if ($path === '' || (!is_dir($path) && !@mkdir($path, 0777, true) && !is_dir($path))) {
            $log[] = array('type' => 'error', 'text' => 'Verzeichnis konnte nicht erstellt werden: ' . $dir);
            $ok = false;
         }
      }
      return $ok;
   }

   private function write_module_file($modul, $rel, $content, $overwrite, array &$log) {
      $path = $this->module_path($modul, $rel);
      if ($path === '') {
         $log[] = array('type' => 'error', 'text' => 'Pfad blockiert: ' . $rel);
         return false;
      }
      $dir = dirname($path);
      if (!is_dir($dir)) {
         @mkdir($dir, 0777, true);
      }
      if (is_file($path) && !$overwrite) {
         $log[] = array('type' => 'skip', 'text' => $rel . ' existiert bereits.');
         return true;
      }
      $ok = file_put_contents($path, $content) !== false;
      $log[] = array('type' => $ok ? 'ok' : 'error', 'text' => ($ok ? 'geschrieben: ' : 'Fehler: ') . $rel);
      return $ok;
   }

   private function backup_module($modul) {
      if (!class_exists('\\ZipArchive')) {
         return '';
      }
      $dir = $this->module_dir($modul);
      if (!is_dir($dir)) {
         return '';
      }
      $file = date('Ymd-His') . '__' . $modul . '.zip';
      $path = $this->backup_root() . $file;
      $zip = new \ZipArchive();
      if ($zip->open($path, \ZipArchive::OVERWRITE) !== true) {
         return '';
      }
      $it = new \RecursiveIteratorIterator(
         new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
         \RecursiveIteratorIterator::SELF_FIRST
      );
      foreach ($it as $item) {
         $full = $item->getPathname();
         $rel = str_replace('\\', '/', substr($full, strlen(rtrim($dir, '/\\')) + 1));
         if ($item->isDir()) {
            $zip->addEmptyDir($rel);
         } else {
            $zip->addFile($full, $rel);
         }
      }
      $zip->close();
      return $file;
   }

   private function restore_module_backup() {
      $modul = dbx()->get_modul_var('xmodul', '', 'parameter');
      $file = basename((string)dbx()->get_modul_var('file', '', 'parameter+.-_'));
      $confirm = (int)dbx()->get_modul_var('confirm', 0, 'int');
      if (!$this->valid_name($modul) || !$this->valid_file_name($file)) {
         return '<div class="alert alert-danger">Restore nicht moeglich: ungueltige Parameter.</div>';
      }
      $zip_path = $this->backup_root() . $file;
      if (!is_file($zip_path)) {
         return '<div class="alert alert-warning">Backup-ZIP nicht gefunden.</div>';
      }
      if (!$confirm) {
         $url = '?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_new&dbx_run3=restore_backup&xmodul=' . rawurlencode($modul) . '&file=' . rawurlencode($file) . '&confirm=1';
         return '<div class="p-3"><h3>Modul wiederherstellen</h3><p>Restore ersetzt den Inhalt von <code>dbx/modules/' . $this->esc($modul) . '</code> durch das Backup <code>' . $this->esc($file) . '</code>.</p><a class="btn btn-danger" href="' . $this->esc($url) . '">Restore starten</a></div>';
      }

      $current_backup = $this->backup_module($modul);
      $dir = $this->module_dir($modul);
      if (!is_dir($dir)) {
         @mkdir($dir, 0777, true);
      }
      $this->empty_dir($dir);
      $zip = new \ZipArchive();
      if ($zip->open($zip_path) !== true) {
         return '<div class="alert alert-danger">Backup konnte nicht geoeffnet werden.</div>';
      }
      for ($i = 0; $i < $zip->numFiles; $i++) {
         $name = str_replace('\\', '/', (string)$zip->getNameIndex($i));
         if ($name === '' || strpos($name, '../') !== false || strpos($name, '..\\') !== false || $name[0] === '/') {
            $zip->close();
            return '<div class="alert alert-danger">Backup enthaelt ungueltige Pfade.</div>';
         }
      }
      $zip->extractTo($dir);
      $zip->close();
      return '<div class="alert alert-success">Modul wiederhergestellt. Sicherheitsbackup vorher: ' . $this->esc($current_backup) . '</div>';
   }

   private function empty_dir($dir) {
      if (!is_dir($dir)) return;
      $it = new \RecursiveIteratorIterator(
         new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
         \RecursiveIteratorIterator::CHILD_FIRST
      );
      foreach ($it as $item) {
         if ($item->isDir()) {
            @rmdir($item->getPathname());
         } else {
            @unlink($item->getPathname());
         }
      }
   }

   private function field_defs($preset) {
      $fields = array(
         array('id', 'int', '11', '', 'ID', 'int', 'hidden', 'PRI', ''),
         array('create_date', 'datetime', '-1', '', 'Erstellt', 'datetime', 'hidden', '', ''),
         array('create_uid', 'int', '11', '0', 'Erstellt von', 'int', 'hidden', 'MUL', ''),
         array('update_date', 'datetime', '-1', '', 'Aktualisiert', 'datetime', 'hidden', '', ''),
         array('update_uid', 'int', '11', '0', 'Aktualisiert von', 'int', 'hidden', 'MUL', ''),
         array('owner', 'int', '11', '0', 'Owner', 'int', 'hidden', 'MUL', ''),
         array('trash', 'int', '1', '0', 'Trash', 'int', 'hidden', 'MUL', ''),
         array('activ', 'int', '1', '1', 'Aktiv', 'int', 'checkbox-label', 'MUL', ''),
         array('sorter', 'varchar', '16', '', 'Sortierung', 'varchar', 'text-label', '', ''),
         array('title', 'varchar', '254', '', 'Titel', 'varchar|min=1|max=254', 'text-label', 'MUL', ''),
         array('description', 'text', '-1', '', 'Beschreibung', 'text', 'textarea-label', '', ''),
      );
      if (in_array($preset, array('content', 'workflow'), true)) {
         $fields[] = array('content', 'text', '-1', '', 'Inhalt', 'text', 'textarea-label', '', '');
      }
      if (in_array($preset, array('status', 'workflow'), true)) {
         $fields[] = array('status', 'varchar', '32', 'open', 'Status', 'parameter|max=32', 'select-single-label', 'MUL', array('open' => 'Offen', 'review' => 'Pruefung', 'done' => 'Erledigt', 'archived' => 'Archiv'));
      }
      return $fields;
   }

   /** Übersetzt die sichtbaren Standardfelder des Wizards ohne die DD-Semantik zu verändern. */
   private function localized_field_defs($preset, $language = 'de') {
      $fields = $this->field_defs($preset);
      $labels = array(
         'en' => array(
            'id' => 'ID', 'create_date' => 'Created', 'create_uid' => 'Created by',
            'update_date' => 'Updated', 'update_uid' => 'Updated by', 'owner' => 'Owner',
            'trash' => 'Trash', 'activ' => 'Active', 'sorter' => 'Sort order',
            'title' => 'Title', 'description' => 'Description', 'content' => 'Content',
            'status' => 'Status',
         ),
         'es' => array(
            'id' => 'ID', 'create_date' => 'Creado', 'create_uid' => 'Creado por',
            'update_date' => 'Actualizado', 'update_uid' => 'Actualizado por', 'owner' => 'Propietario',
            'trash' => 'Papelera', 'activ' => 'Activo', 'sorter' => 'Orden',
            'title' => 'Título', 'description' => 'Descripción', 'content' => 'Contenido',
            'status' => 'Estado',
         ),
      );
      foreach ($fields as &$field) {
         $name = (string)($field[0] ?? '');
         if (isset($labels[$language][$name])) {
            $field[4] = $labels[$language][$name];
         }
         if ($name === 'status' && is_array($field[8] ?? null)) {
            $field[8] = $language === 'en'
               ? array('open' => 'Open', 'review' => 'Review', 'done' => 'Done', 'archived' => 'Archived')
               : ($language === 'es'
                  ? array('open' => 'Abierto', 'review' => 'Revisión', 'done' => 'Finalizado', 'archived' => 'Archivado')
                  : $field[8]);
         }
      }
      unset($field);
      return $fields;
   }

   /** Sprachabhängige Fachtexte der erzeugten Formular- und Report-FDs. */
   private function generated_messages($kind, $language = 'de') {
      $messages = array(
         'de' => array(
            'form_title_new' => 'Neuer Datensatz', 'form_title_edit' => 'Datensatz bearbeiten',
            'form_subtitle' => 'Daten erfassen und sicher speichern.',
            'form_info' => 'Felder ausfüllen und mit Speichern übernehmen.',
            'action_report' => 'Zur Übersicht', 'report_title' => 'Datensätze',
            'report_subtitle' => 'Suchen, auswählen und bearbeiten.', 'action_new' => 'Neu',
            'delete_success' => 'Datensatz gelöscht.',
            'delete_error' => 'Datensatz konnte nicht gelöscht werden.',
         ),
         'en' => array(
            'form_title_new' => 'New record', 'form_title_edit' => 'Edit record',
            'form_subtitle' => 'Enter data and save it securely.',
            'form_info' => 'Complete the fields and select Save.',
            'action_report' => 'Back to overview', 'report_title' => 'Records',
            'report_subtitle' => 'Search, select and edit.', 'action_new' => 'New',
            'delete_success' => 'Record deleted.', 'delete_error' => 'The record could not be deleted.',
         ),
         'es' => array(
            'form_title_new' => 'Nuevo registro', 'form_title_edit' => 'Editar registro',
            'form_subtitle' => 'Introducir datos y guardarlos de forma segura.',
            'form_info' => 'Complete los campos y seleccione Guardar.',
            'action_report' => 'Volver al resumen', 'report_title' => 'Registros',
            'report_subtitle' => 'Buscar, seleccionar y editar.', 'action_new' => 'Nuevo',
            'delete_success' => 'Registro eliminado.', 'delete_error' => 'No se pudo eliminar el registro.',
         ),
      );
      $all = $messages[$language] ?? $messages['de'];
      $keys = $kind === 'report'
         ? array('report_title', 'report_subtitle', 'action_new', 'delete_success', 'delete_error')
         : array('form_title_new', 'form_title_edit', 'form_subtitle', 'form_info', 'action_report');
      return array_intersect_key($all, array_flip($keys));
   }

   private function messages_php(array $messages) {
      $out = "\$messages = array();\n";
      foreach ($messages as $key => $value) {
         $out .= '$messages[' . var_export($key, true) . ']=' . var_export($value, true) . ";\n";
      }
      return $out . "\n";
   }

   private function dd_field_php(array $f) {
      list($name, $type, $length, $default, $label, $rules, $tpl, $index) = array_pad($f, 8, '');
      $options = $f[8] ?? '';
      return "\$field['name']=" . var_export($name, true) . ";\n"
         . "\$field['type']=" . var_export($type, true) . ";\n"
         . "\$field['index']=" . var_export($index, true) . ";\n"
         . "\$field['length']=" . var_export($length, true) . ";\n"
         . "\$field['default']=" . var_export($default, true) . ";\n"
         . "\$field['label']=" . var_export($label, true) . ";\n"
         . "\$field['rules']=" . var_export($rules, true) . ";\n"
         . "\$field['tooltip']='';\n\$field['errormsg']='';\n\$field['placeholder']='';\n\$field['convert']='';\n\$field['protect']='0';\n\$field['group']='';\n\$field['mask']='';\n\$field['data']='';\n"
         . "\$field['options']=" . var_export($options, true) . ";\n"
         . "\$field['tpl']=" . var_export($tpl, true) . ";\n"
         . "\$field['js']='';\n\$field['prompt']='';\n\$fields[]=\$field;\n\n";
   }

   private function generate_dd(array $in) {
      $out = "<?php\n\n";
      $out .= "/* =========================================================\n   TABLE\n   ========================================================= */\n";
      $out .= "\$table['server']=" . var_export($in['xmodul'] . '|' . $in['db_file'], true) . ";\n";
      $out .= "\$table['table']=" . var_export($in['table_name'], true) . ";\n";
      $out .= "\$table['datadic']=" . var_export($in['dd_name'], true) . ";\n";
      $out .= "\$table['primary']='id';\n\$table['language']='0';\n\$table['version']='1.0';\n\$table['autosync']='0';\n\$table['cache']='0';\n\$table['trash']='1';\n\$table['trace']='0';\n\$table['update_sql']='';\n\$table['default_sort']='title ASC';\n\$table['form-dd-table']='';\n\$table['read']='admin';\n\$table['create']='admin';\n\$table['update']='admin';\n\$table['delete']='admin';\n\$table['read_owner']='owner,admin';\n\$table['create_owner']='owner,admin';\n\$table['update_owner']='owner,admin';\n\$table['delete_owner']='owner,admin';\n\n";
      $out .= "/* =========================================================\n   FIELDS\n   ========================================================= */\n";
      foreach ($this->field_defs($in['field_preset']) as $field) {
         $out .= $this->dd_field_php($field);
      }
      $out .= "/* =========================================================\n   INDEXES\n   ========================================================= */\n";
      $indexes = array(
         array('idx_' . $in['table_name'] . '_title', 'title'),
         array('idx_' . $in['table_name'] . '_activ', 'activ'),
      );
      if (in_array($in['field_preset'], array('status', 'workflow'), true)) {
         $indexes[] = array('idx_' . $in['table_name'] . '_status', 'status');
      }
      foreach ($indexes as $idx) {
         $out .= "\$index['name']=" . var_export($idx[0], true) . ";\n\$index['type']='INDEX';\n\$index['fields']=" . var_export($idx[1], true) . ";\n\$index['unique']='0';\n\$index['comment']='module wizard';\n\$indexes[]=\$index;\n\n";
      }
      return $out;
   }

   private function generate_form_fd(array $in, $language = 'de') {
      $out = "<?php\n" . $this->messages_php(
         $this->generated_messages('form', (string)$language)
      );
      foreach ($this->localized_field_defs($in['field_preset'], (string)$language) as $f) {
         if (in_array($f[0], array('id', 'create_date', 'create_uid', 'update_date', 'update_uid', 'owner', 'trash'), true)) {
            continue;
         }
         $out .= $this->dd_field_php($f);
      }
      return $out;
   }

   private function generate_report_fd($language = 'de') {
      $out = "<?php\n" . $this->messages_php(
         $this->generated_messages('report', (string)$language)
      );
      $out .= <<<'PHP'

$field['name']='dbx_rrows';
$field['type']='int';
$field['tpl']='select-single-label';
$field['default']='20';
$field['label']='Anz.Seite';
$field['rules']='int';
$field['options']='10=10&20=20&50=50&100=100&0=alle';
$fields[]=$field;

$field['name']='dbx_rsort';
$field['type']='varchar';
$field['tpl']='select-single-label';
$field['default']='title';
$field['label']='Sortierung';
$field['rules']='sqlsearch';
$field['options']='id=id&title=Titel&update_date=Update&sorter=Sortierung&activ=Aktiv';
$fields[]=$field;

$field['name']='dbx_rdesc';
$field['type']='varchar';
$field['tpl']='select-single-label';
$field['default']='ASC';
$field['label']='Auf/Ab';
$field['rules']='parameter';
$field['options']='ASC=Aufsteigend&DESC=Absteigend';
$fields[]=$field;

$field['name']='dbx_rwhere';
$field['type']='varchar';
$field['tpl']='dbx|search';
$field['default']='';
$field['label']='Suchen';
$field['rules']='parameter';
$field['options']='';
$fields[]=$field;

$field['name']='dbx_rselect';
$field['type']='int';
$field['tpl']='select-single-label';
$field['default']='0';
$field['label']='Auswahl';
$field['rules']='int';
$field['options']='0=Alle&1=Ausgewaehlte';
$fields[]=$field;
PHP;
      if ($language === 'en') {
         $out = str_replace(
            array("'Anz.Seite'", "'Sortierung'", "'Auf/Ab'", "'Suchen'", "'Auswahl'", "'Aufsteigend'", "'Absteigend'", "'Alle'", "'Ausgewaehlte'", "'Titel'", "'Update'", "'Sortierung'", "'Aktiv'"),
            array("'Per page'", "'Sort'", "'Direction'", "'Search'", "'Selection'", "'Ascending'", "'Descending'", "'All'", "'Selected'", "'Title'", "'Updated'", "'Sort order'", "'Active'"),
            $out
         );
      } elseif ($language === 'es') {
         $out = str_replace(
            array("'Anz.Seite'", "'Sortierung'", "'Auf/Ab'", "'Suchen'", "'Auswahl'", "'Aufsteigend'", "'Absteigend'", "'Alle'", "'Ausgewaehlte'", "'Titel'", "'Update'", "'Sortierung'", "'Aktiv'"),
            array("'Por página'", "'Orden'", "'Dirección'", "'Buscar'", "'Selección'", "'Ascendente'", "'Descendente'", "'Todos'", "'Seleccionados'", "'Título'", "'Actualizado'", "'Orden'", "'Activo'"),
            $out
         );
      }
      return $out;
   }

   private function generate_form_template() {
      return <<<'HTML'
<div id="dbxForm_{i}" class="dbx-panel dbxForm_wrapper dbx-ajax-root">
 {form:bar}
 <form action="{action}" method="post" id="dbx_form_{i}" class="dbxAjax" data-target="dbxForm_{i}">
  <div class="dbx-panel-body">
   {form:message}
   <div class="row g-3">[dbx:form]</div>
  </div>
  {form:footer}
  [dbx:js]
 </form>
</div>
HTML;
   }

   private function generate_main_class(array $in) {
      $modul = $in['xmodul'];
      $service = $modul . 'Service';
      $default = $in['default_run1'] ?: 'run';
      $start_content = $this->esc('Modul ' . $modul . ' ist bereit.');
      $start_method = "   private function start() {\n      \$tpl = dbx()->get_system_obj('dbxTPL');\n      return \$tpl->get_tpl('$modul|start', array('content' => " . var_export($start_content, true) . "));\n   }\n";
      if ((int)$in['create_templates'] !== 1) {
         $start_method = "   private function start() {\n      return '<div class=\"p-3\"><h2>" . $this->esc($in['title']) . "</h2><p>" . $start_content . "</p></div>';\n   }\n";
      }
      return "<?php\n\ndeclare(strict_types=1);\n\nnamespace dbx\\$modul;\n\nfinal class $modul\n{\n   private function service()\n   {\n      return dbx()->get_include_obj('$service', '$modul');\n   }\n\n$start_method\n   public function run()\n   {\n      \$action = (string)dbx()->get_modul_var('dbx_run1', " . var_export($default, true) . ", 'parameter');\n      \$definition = dbx()->get_system_obj('dbxActionManifest')->action('$modul', \$action);\n      if (!is_array(\$definition)) {\n         return dbx()->get_system_obj('dbxTPL')->get_tpl('dbx|alert-warning', array(\n            'msg' => 'Unbekannter Modulaufruf: ' . \$action,\n         ));\n      }\n\n      \$target = (string)(\$definition['target'] ?? 'service');\n      \$handler = (string)\$definition['handler'];\n      \$object = \$target === 'module' ? \$this : \$this->service();\n      if (!is_object(\$object) || !method_exists(\$object, \$handler)) {\n         throw new \\LogicException('Modul-Handler fehlt: ' . \$action);\n      }\n      return \$object->{\$handler}();\n   }\n}\n";
   }

   /** Erzeugt den einzigen deklarativen Routingvertrag des neuen Moduls. */
   private function generate_actions_manifest(array $in) {
      $actions = array(
         ($in['default_run1'] ?: 'run') => array(
            'handler' => 'start', 'target' => 'module', 'methods' => array('GET', 'HEAD'),
            'groups' => array('admin'), 'mutation' => false, 'response' => 'html',
         ),
      );
      if ((int)$in['create_form'] === 1) {
         $actions['form'] = array('handler' => 'form', 'methods' => array('GET', 'HEAD', 'POST'), 'groups' => array('admin'), 'mutation' => false, 'response' => 'html');
      }
      if ((int)$in['create_report'] === 1) {
         $actions['report'] = array('handler' => 'report', 'methods' => array('GET', 'HEAD', 'POST'), 'groups' => array('admin'), 'mutation' => false, 'response' => 'html');
         $actions['detail'] = array('handler' => 'detail', 'methods' => array('GET', 'HEAD'), 'groups' => array('admin'), 'mutation' => false, 'response' => 'html');
      }
      if ($in['module_template'] === 'api' || (int)$in['create_include'] === 1) {
         $actions['api'] = array('handler' => 'api', 'methods' => array('GET', 'HEAD'), 'groups' => array('admin'), 'mutation' => false, 'response' => 'json');
      }
      return "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($actions, true) . ";\n";
   }

   /** Erzeugt die lokale Paketbeschreibung für Marktplatz und Updates. */
   private function generate_package_manifest(array $in) {
      $version = $this->product_version();
      $version_parts = array_map('intval', explode('.', $version));
      $release_line = $version_parts[0] . '.' . $version_parts[1] . '.0';
      $kernel_constraint = '>=' . $release_line . ' <' . ($version_parts[0] + 1) . '.0.0';
      $permissions = $in['dd_mode'] === 'none' ? array() : array('database');
      $package = array(
         'schema' => 1,
         'id' => 'local/module/' . $in['xmodul'],
         'type' => 'module',
         'name' => $in['xmodul'],
         'title' => $in['title'],
         'description' => 'Mit dem dbxAdmin Modul-Wizard erzeugtes Funktionsmodul.',
         'descriptions' => array(
            'de' => 'Mit dem dbxAdmin Modul-Wizard erzeugtes Funktionsmodul.',
            'en' => 'Feature module generated with the dbxAdmin module wizard.',
            'es' => 'Módulo funcional generado con el asistente de módulos dbxAdmin.',
         ),
         'icon' => 'bi-box-seam', 'image' => '', 'package_excludes' => array(),
         'version' => $version,
         'vendor' => array('id' => 'local', 'name' => 'Lokaler Hersteller'),
         'license' => 'private', 'managed' => false,
         'requires' => array(
            'kernel' => $kernel_constraint, 'php' => '>=8.2.0',
            'extensions' => array('json'), 'packages' => array(),
         ),
         'permissions' => $permissions, 'migrations' => array(), 'files' => array(),
      );
      return json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
   }

   private function product_version(): string {
      $version = trim((string)@file_get_contents(dirname(__DIR__, 4) . '/VERSION'));
      return preg_match('/^\d+\.\d+\.\d+$/', $version) ? $version : '0.0.0';
   }

   private function generate_service_class(array $in) {
      $modul = $in['xmodul'];
      $class = $modul . 'Service';
      $dd_ref = $in['dd_mode'] !== 'none' ? $modul . '|' . $in['dd_name'] : '';
      $form_fd = $modul . '|' . $in['dd_name'] . '-form';
      $report_fd = $modul . '|rpt-' . $in['dd_name'] . '-selection';
      $form_template = strtolower($modul) . '-form';
      $fields = array();
      foreach ($this->field_defs($in['field_preset']) as $f) {
         if (in_array($f[0], array('id', 'title', 'description', 'content', 'status', 'activ', 'update_date'), true)) {
            $fields[$f[0]] = $f[4];
         }
      }
      $fields_export = var_export($fields, true);

      return str_replace(
         array('__MODUL__', '__CLASS__', '__DD_REF__', '__FORM_FD__', '__REPORT_FD__', '__FORM_TEMPLATE__', '__FIELDS_EXPORT__'),
         array($modul, $class, var_export($dd_ref, true), var_export($form_fd, true), var_export($report_fd, true), $form_template, $fields_export),
         $this->service_class_template()
      );
   }

   /** Lädt die versionierte Codevorlage des Modul-Wizards. */
   private function service_class_template(): string {
      $file = __DIR__ . '/templates/module-service.template.php';
      if (!is_file($file)) {
         throw new \RuntimeException('Wizard-Codevorlage fehlt: ' . $file);
      }
      $template = require $file;
      if (!is_string($template) || trim($template) === '') {
         throw new \RuntimeException('Wizard-Codevorlage ist ungültig: ' . $file);
      }
      return $template;
   }
   private function generate_result_template(array $in) {
      return '<div class="p-3"><h2>' . $this->esc($in['title']) . '</h2><p>{content}</p></div>';
   }

   private function generate_module_readme(array $in) {
      $modul = $in['xmodul'];
      $dd = $in['dd_name'];
      $template = <<<'MD'
# __MODUL__ Modul-Wizard Dokumentation

Diese Datei wurde vom dbxAdmin Modul-Wizard erzeugt. Sie ist absichtlich knapp und technisch, damit Mensch und KI die Struktur direkt verstehen.

## Grenzen

- Modulwurzel: `dbx/modules/__MODUL__/`
- KI darf nur innerhalb dieser Modulwurzel arbeiten.
- DD-Dateien muessen vollstaendig sein, keine DD-Includes.
- Individuelle Formularlayouts bleiben unter `tpl/htm`; gemeinsame Bars, Meldungen, Footer und Tabellenreports kommen aus `dbx`.
- Modul-JavaScript gehoert nach `js/`, Modul-CSS nach `tpl/css/` und wird ueber `dbxAssetRegistry` registriert.
- Hilfetexte gehoeren ausschliesslich nach `tpl/help/`.
- DD->DB Sync nur fuer `__MODUL__|__DD__`.

## Dateien

- `__MODUL__.class.php`: Schlanker Einstieg; Routing erfolgt ueber das Aktionsmanifest.
- `cfg/actions.php`: Einziger deklarativer Vertrag fuer die Modulrouten.
- `dbx.package.json`: Lokale Paketbeschreibung fuer Marktplatz und Updates.
- `include/__MODUL__Service.class.php`: Formular, Standardreport, Detail und API.
- `dd/__DD__.dd.php`: Data Dictionary und DB-Struktur.
- `fd/<name>_en.fd.php` und `fd/<name>_es.fd.php`: Vollstaendige sprachabhaengige Varianten.
- `tpl/htm/start.htm`: Start-Template.
- `tpl/htm/__FORM_TEMPLATE__.htm`: Individuelle fachliche Formularanordnung.
- `tpl/help/modul.htm`: Modulinterne Kontexthilfe.

## Formular

Route: `?dbx_modul=__MODUL__&dbx_run1=form`

Enthaltene Muster:

- Die Form-ID `__MODUL__-form` bleibt die Identitaet des UI-State.
- Das individuelle Modul-Template enthaelt `{form:bar}`, `{form:message}` und `{form:footer}`.
- `add_module_bar_form_actions()` nutzt die gemeinsamen Save/Delete/Reload-Komponenten.
- `save_post()` liefert die sprachabhaengigen Standardmeldungen des Kernels.
- Delete-Links werden durch `action_url()` und die zentrale Request-Policy abgesichert.

Gängige Feldtemplates:

- `text-label`: einzeiliger Text.
- `textarea-label`: mehrzeiliger Text, auch fuer HTML-Felder geeignet wenn Regeln es zulassen.
- `checkbox-label`: 0/1 Checkbox.
- `select-single-label`: einfache Auswahl mit `options`.
- `select-multiple-label` oder `multiselect2`: Mehrfachauswahl.
- `date-label`: Datum.
- `integer-label`: Zahlen.
- `password-label`: Passwort.
- `hidden`: verstecktes Feld.

## Report

Route: `?dbx_modul=__MODUL__&dbx_run1=report`

Enthaltene Muster:

- Haupttemplate `dbx|report-default`; kein kopiertes Standard-Reporttemplate im Modul.
- Deklarative Aktionen ueber `set_table_actions()`.
- Multi-Select ueber dbxReport Remember.
- Multi-Delete ueber `delete_multi_selected_records()`.
- Row-Edit ueber `dbx_do=row_edit`.
- Row-Detail ueber `dbx_do=row_show` oder `dbx_do=detail`.
- Row-Delete ueber `dbx_do=row_delete`.
- Filter `dbx_rselect=1` zeigt nur ausgewaehlte IDs.

## API

Route: `?dbx_modul=__MODUL__&dbx_run1=api`

Die API-Methode ist ein Platzhalter und liefert JSON. Erweiterungen bleiben im Modul.
MD;

      return str_replace(
         array('__MODUL__', '__DD__', '__FORM_TEMPLATE__'),
         array($modul, $dd, strtolower($modul) . '-form'),
         $template
      );
   }

   private function generate_module(array $in, array &$log) {
      $modul = $in['xmodul'];
      $ok = $this->ensure_dirs($modul, $log);
      $overwrite = (int)$in['overwrite'] === 1;
      $write = function($rel, $content) use ($modul, $overwrite, $in, &$log, &$ok) {
         $written = $this->write_module_file($modul, $rel, $content, $overwrite || $in['target_mode'] === 'new', $log);
         $ok = $written && $ok;
      };

      if ((int)$in['backup'] === 1 && $in['target_mode'] === 'existing') {
         $backup = $this->backup_module($modul);
         if ($backup) {
            $log[] = array('type' => 'ok', 'text' => 'Backup erstellt: ' . $backup);
            $restore = '?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_new&dbx_run3=restore_backup&xmodul=' . rawurlencode($modul) . '&file=' . rawurlencode($backup);
            $log[] = array('type' => 'link', 'text' => 'Restore-Link: ' . $restore);
         } else {
            $log[] = array('type' => 'skip', 'text' => 'Backup nicht erstellt (ZipArchive nicht verfuegbar oder Modul leer).');
         }
      }

      $config = "<?php\n\$config['version']=" . var_export($this->product_version(), true) . ";\n\$config['activ']='1';\n\$config['dbxConfig_modul']='secure';\n\$config['groups']='admin';\n\$config['title']=" . var_export($in['title'], true) . ";\n\$config['default_run1']=" . var_export($in['default_run1'], true) . ";\n\$config['default_run2']=" . var_export($in['default_run2'], true) . ";\n";
      $write('cfg/config.php', $config);
      $write('cfg/actions.php', $this->generate_actions_manifest($in));
      $write($modul . '.class.php', $this->generate_main_class($in));
      $write('dbx.package.json', $this->generate_package_manifest($in));

      if ((int)$in['create_include'] === 1) {
         $write('include/' . $modul . 'Service.class.php', $this->generate_service_class($in));
      }
      $write('README-MODUL-WIZARD.md', $this->generate_module_readme($in));
      if ($in['dd_mode'] === 'new') {
         $write('dd/' . $in['dd_name'] . '.dd.php', $this->generate_dd($in));
      }
      if ((int)$in['create_form'] === 1 && $in['dd_mode'] !== 'none') {
         $write('fd/' . $in['dd_name'] . '-form.fd.php', $this->generate_form_fd($in));
         $write('fd/' . $in['dd_name'] . '-form_en.fd.php', $this->generate_form_fd($in, 'en'));
         $write('fd/' . $in['dd_name'] . '-form_es.fd.php', $this->generate_form_fd($in, 'es'));
      }
      if ((int)$in['create_report'] === 1 && $in['dd_mode'] !== 'none') {
         $write('fd/rpt-' . $in['dd_name'] . '-selection.fd.php', $this->generate_report_fd());
         $write('fd/rpt-' . $in['dd_name'] . '-selection_en.fd.php', $this->generate_report_fd('en'));
         $write('fd/rpt-' . $in['dd_name'] . '-selection_es.fd.php', $this->generate_report_fd('es'));
      }
      if ((int)$in['create_templates'] === 1) {
         $write('tpl/help/modul.htm', '<p>' . $this->esc($in['title']) . '</p>');
         $write('tpl/help/modul_en.htm', '<p>' . $this->esc($in['title']) . '</p>');
         $write('tpl/help/modul_es.htm', '<p>' . $this->esc($in['title']) . '</p>');
         $write('tpl/htm/start.htm', $this->generate_result_template($in));
         if ((int)$in['create_form'] === 1) {
            $write('tpl/htm/' . strtolower($modul) . '-form.htm', $this->generate_form_template());
         }
      }

      if ($in['dd_mode'] !== 'none') {
         $sync_url = '?dbx_modul=dbxAdmin&dbx_run1=dd&dbx_run2=sync_dd_to_db&modul=' . rawurlencode($modul) . '&dd=' . rawurlencode($in['dd_name']) . '&mode=apply&reset=1';
         if ($in['sync_mode'] === 'apply') {
            $state = $this->sync_dd_to_db($modul, $in['dd_name']);
            $sync_ok = (($state['status'] ?? '') === 'finished');
            $log[] = array('type' => $sync_ok ? 'ok' : 'error', 'text' => 'DD->DB Sync: ' . (string)($state['message'] ?? ($state['status'] ?? '')));
            $ok = $sync_ok && $ok;
         } elseif ($in['sync_mode'] === 'link') {
            $log[] = array('type' => 'link', 'text' => 'DD->DB Sync: ' . $sync_url);
         }
      }

      $start_url = '?dbx_modul=' . rawurlencode($modul) . '&dbx_run1=' . rawurlencode($in['default_run1'] ?: 'run');
      $log[] = array('type' => 'link', 'text' => 'Modul starten: ' . $start_url);
      if ((int)$in['create_form'] === 1) {
         $log[] = array('type' => 'link', 'text' => 'Formular oeffnen: ?dbx_modul=' . rawurlencode($modul) . '&dbx_run1=form');
      }
      if ((int)$in['create_report'] === 1) {
         $log[] = array('type' => 'link', 'text' => 'Report oeffnen: ?dbx_modul=' . rawurlencode($modul) . '&dbx_run1=report');
      }
      if ((int)$in['create_include'] === 1) {
         $log[] = array('type' => 'link', 'text' => 'API pruefen: ?dbx_modul=' . rawurlencode($modul) . '&dbx_run1=api');
      }
      if ($in['dd_mode'] !== 'none') {
         $log[] = array('type' => 'link', 'text' => 'DD bearbeiten: ?dbx_modul=dbxAdmin&dbx_run1=edit_dd&modul=' . rawurlencode($modul) . '&dd=' . rawurlencode($in['dd_name']));
         if ((int)$in['create_form'] === 1) {
            $log[] = array('type' => 'link', 'text' => 'Form-FD bearbeiten: ?dbx_modul=dbxAdmin&dbx_run1=edit_fd&modul=' . rawurlencode($modul) . '&fd=' . rawurlencode($in['dd_name'] . '-form'));
         }
         if ((int)$in['create_report'] === 1) {
            $log[] = array('type' => 'link', 'text' => 'Report-FD bearbeiten: ?dbx_modul=dbxAdmin&dbx_run1=edit_fd&modul=' . rawurlencode($modul) . '&fd=' . rawurlencode('rpt-' . $in['dd_name'] . '-selection'));
         }
      }

      if ((int)$in['ki_package'] === 1) {
         $api_url = '?dbx_modul=dbxKi&dbx_run1=module_api&action=module.describe&xmodul=' . rawurlencode($modul);
         $log[] = array('type' => 'link', 'text' => 'dbxKi Modul-API: ' . $api_url);
      }
      return $ok;
   }

   private function sync_dd_to_db($modul, $dd) {
      $o_dd = dbx()->get_system_obj('dbxDD');
      $o_dd->sync_dd_to_db($modul, $dd, 'reset');
      $state = array('status' => 'running', 'message' => '');
      for ($i = 0; $i < 20; $i++) {
         $state = $o_dd->sync_dd_to_db($modul, $dd, 'apply');
         if (in_array((string)($state['status'] ?? ''), array('finished', 'error', 'cancelled'), true)) {
            break;
         }
      }
      return is_array($state) ? $state : array('status' => 'error', 'message' => 'Sync ohne Status');
   }

   private function log_html(array $log) {
      if (!$log) return '';
      $html = '<div class="dbx-admin-dashboard-panel mt-3"><div class="p-3"><h3>Ergebnis</h3><ul class="list-unstyled mb-0">';
      foreach ($log as $row) {
         $type = (string)($row['type'] ?? 'info');
         $text = (string)($row['text'] ?? '');
         $class = $type === 'error' ? 'text-danger' : ($type === 'skip' ? 'text-muted' : 'text-success');
         if ($type === 'link' && strpos($text, ': ?') !== false) {
            list($label, $url) = explode(': ', $text, 2);
            $html .= '<li class="' . $class . '"><a href="' . $this->esc($url) . '">' . $this->esc($label) . '</a></li>';
         } else {
            $html .= '<li class="' . $class . '">' . $this->esc($text) . '</li>';
         }
      }
      return $html . '</ul></div></div>';
   }

   private function ki_api() {
      $modul = dbx()->get_modul_var('xmodul', '', 'parameter');
      $dd = dbx()->get_modul_var('dd_name', '', 'parameter');
      if (!$this->valid_name($modul)) {
         dbx()->json_response(array('ok' => 0, 'error' => 'ungueltiges Modul'), true);
      }
      dbx()->json_response($this->ki_manifest($modul, $dd), true);
   }

   private function ki_manifest($modul, $dd) {
      return array(
         'ok' => 1,
         'bundle_version' => 'module-wizard.v1',
         'area' => 'module',
         'module' => $modul,
         'dd' => $dd,
         'root' => 'dbx/modules/' . $modul . '/',
         'hard_rules' => array(
            'Nur Dateien unter dbx/modules/' . $modul . '/ bearbeiten.',
            'Keine globalen Configs, keine anderen Module, keine Dateien in dbx/include aendern.',
            'Templates ueber dbxapp-Template-APIs lesen/rendern, z.B. dbx()->get_system_obj(\'dbxTPL\')->get_tpl(...).',
            'Template-Aenderungen nur als Modul-Template-Aktion liefern; keine freien Schreibzugriffe ausserhalb von dbx/modules/' . $modul . '/tpl/.',
            'Vor destruktiven Aenderungen ein Modul-ZIP erzeugen.',
            'Antwort als ZIP mit manifest.json und job.json liefern.',
         ),
         'allowed_actions' => array(
            'module.file.write' => 'Datei unter dem Modul schreiben',
            'module.file.delete' => 'Datei unter dem Modul entfernen',
            'module.dd.write' => 'DD unter dd/<name>.dd.php schreiben',
            'module.dd.sync' => 'DD->DB Sync fuer Modul-DD anfordern',
            'module.template.get' => 'Template ueber dbxTPL/get_tpl lesen oder rendern',
            'module.template.set' => 'Template unter tpl/<path> ueber die dbxapp-Template-Mechanik schreiben',
            'module.php.write' => 'PHP unter Modulroot oder include schreiben',
            'module.form.extend' => 'Formular mit Save/Delete/Feldtemplates erweitern',
            'module.report.extend' => 'Report mit Callbacks, Multi-Select, Multi-Delete, Edit und Detail erweitern',
         ),
      );
   }

   private function send_ki_zip() {
      if (!class_exists('\\ZipArchive')) {
         dbx()->json_response(array('ok' => 0, 'error' => 'ZipArchive nicht verfuegbar.'), true);
      }
      $modul = dbx()->get_modul_var('xmodul', '', 'parameter');
      $dd = dbx()->get_modul_var('dd_name', '', 'parameter');
      if (!$this->valid_name($modul)) {
         dbx()->json_response(array('ok' => 0, 'error' => 'ungueltiges Modul'), true);
      }
      $manifest = $this->ki_manifest($modul, $dd);
      $job = array(
         'job_version' => 'module-wizard.v1',
         'module' => $modul,
         'steps' => array(
            array('id' => 'backup', 'action' => 'module.backup', 'params' => array('module' => $modul)),
            array('id' => 'plan', 'action' => 'module.plan', 'params' => array('module' => $modul, 'dd' => $dd)),
         ),
      );
      $auftrag = "# KI-Auftrag Modulprogrammierung\n\n"
         . "Arbeite ausschliesslich im Modul `dbx/modules/$modul/`.\n\n"
         . "## Harte Regeln\n\n"
         . "- Keine Aenderungen ausserhalb von `dbx/modules/$modul/`.\n"
         . "- Kein Umbau von Core, dbx/include, globaler config.php oder anderen Modulen.\n"
         . "- DD-Dateien vollstaendig schreiben, keine include-basierten DDs.\n"
         . "- Templates ueber die dbxapp-Template-Schicht lesen/rendern, z.B. `dbx()->get_system_obj('dbxTPL')->get_tpl(...)`.\n"
         . "- Template-Aenderungen als Modul-Template-Set-Aktion liefern; keine Pfade ausserhalb von `dbx/modules/$modul/tpl/`.\n"
         . "- Formular-Muster mit Save/Delete, Feldtemplates und Callback-Kommentaren erhalten oder sauber erweitern.\n"
         . "- Report-Muster mit Multi-Select, Multi-Delete, Edit, Detail und Callbacks erhalten oder sauber erweitern.\n"
         . "- README-MODUL-WIZARD.md aktualisieren, wenn Formular-, Report-, DD- oder API-Verhalten geaendert wird.\n"
         . "- Wenn DB-Sync noetig ist, nur fuer `$modul|$dd` anfordern.\n"
         . "- Liefere `antwort.zip` mit `manifest.json`, `job.json`, geaenderten Dateien und README.\n\n"
         . "## Aufgabe\n\nBeschreibe im Chat die gewuenschte Modulaufgabe und nutze diese ZIP als Arbeitskontext.\n";
      $tmp = tempnam(sys_get_temp_dir(), 'dbxmodki');
      $zip = new \ZipArchive();
      $zip->open($tmp, \ZipArchive::OVERWRITE);
      $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
      $zip->addFromString('job.vorlage.json', json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
      $zip->addFromString('KI-AUFTRAG.md', $auftrag);
      $zip->addFromString('README.md', "Modul-KI-Auftrag fuer $modul.\n");
      $zip->close();
      $name = 'dbxki-modul-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $modul) . '.zip';
      header('Content-Type: application/zip');
      header('Content-Disposition: attachment; filename="' . $name . '"');
      header('Content-Length: ' . filesize($tmp));
      readfile($tmp);
      @unlink($tmp);
      exit;
   }

   public function new_modul() {
      $in = $this->collect_input();
      $log = array();
      $o_form = dbx()->get_system_obj('dbxForm');
      $o_form->init('form-wizzard-new', 'form-wizzard-new');
      $o_form->set_field_definition('dbxAdmin|module-wizard');
      $o_form->load_fd_messages();
      $o_form->set_action('?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_new');
      $o_form->set_data(array_merge(array(
         'target_mode' => 'new',
         'module_template' => 'form_report',
         'dd_mode' => 'new',
         'field_preset' => 'basic',
         'create_include' => 1,
         'create_form' => 1,
         'create_report' => 1,
         'create_templates' => 1,
         'overwrite' => 0,
         'backup' => 1,
         'sync_mode' => 'link',
         'ki_package' => 1,
      ), $in));
      $o_form->add_rep('bar_title', $o_form->get_fd_message('bar_title'));
      $o_form->add_rep('bar_subtitle', $o_form->get_fd_message('bar_subtitle'));
      $o_form->add_obj(
         'bar_actions',
         'obj-value',
         '<button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-magic"></i> '
            . $this->esc($o_form->get_fd_message('action_create')) . '</button>'
      );
      $o_form->_msg_info = $o_form->get_fd_message('wizard_info');

      $o_form->add_fld('target_mode', 'select-single-label', label: $o_form->get_fd_message('label_target'), rules: 'parameter', options: $this->options(array('new' => $o_form->get_fd_message('option_target_new'), 'existing' => $o_form->get_fd_message('option_target_existing'))));
      $o_form->add_fld('existing_modul', 'select-single-label', label: $o_form->get_fd_message('label_existing_module'), rules: 'parameter', options: $this->options($this->module_options(true)));
      $o_form->add_fld('xmodul', 'text-label', label: $o_form->get_fd_message('label_new_module'), rules: 'parameter|max=63', tooltip: $o_form->get_fd_message('tooltip_module_name'));
      $o_form->add_fld('title', 'text-label', label: $o_form->get_fd_message('label_title'), rules: 'varchar|max=120');
      $o_form->add_fld('module_template', 'select-single-label', label: $o_form->get_fd_message('label_template'), rules: 'parameter', options: $this->options(array('form_report' => $o_form->get_fd_message('option_template_form_report'), 'form' => $o_form->get_fd_message('option_template_form'), 'report' => $o_form->get_fd_message('option_template_report'), 'api' => $o_form->get_fd_message('option_template_api'), 'blank' => $o_form->get_fd_message('option_template_blank'))));
      $o_form->add_fld('default_run1', 'text-label', label: $o_form->get_fd_message('label_run1'), rules: 'parameter|max=32');
      $o_form->add_fld('default_run2', 'text-label', label: $o_form->get_fd_message('label_run2'), rules: 'parameter|max=32');
      $o_form->add_fld('dd_mode', 'select-single-label', label: $o_form->get_fd_message('label_dd'), rules: 'parameter', options: $this->options(array('new' => $o_form->get_fd_message('option_dd_new'), 'existing' => $o_form->get_fd_message('option_dd_existing'), 'none' => $o_form->get_fd_message('option_dd_none'))));
      $o_form->add_fld('dd_ref', 'select-single-label', label: $o_form->get_fd_message('label_existing_dd'), rules: 'parameter', options: $this->options($this->dd_options(true)));
      $o_form->add_fld('dd_name', 'text-label', label: $o_form->get_fd_message('label_dd_name'), rules: 'parameter|max=63');
      $o_form->add_fld('table_name', 'text-label', label: $o_form->get_fd_message('label_table'), rules: 'parameter|max=63');
      $o_form->add_fld('db_file', 'text-label', label: $o_form->get_fd_message('label_db_file'), rules: 'parameter+.-_|max=80');
      $o_form->add_fld('field_preset', 'select-single-label', label: $o_form->get_fd_message('label_fields'), rules: 'parameter', options: $this->options(array('basic' => $o_form->get_fd_message('option_fields_basic'), 'content' => $o_form->get_fd_message('option_fields_content'), 'status' => $o_form->get_fd_message('option_fields_status'), 'workflow' => $o_form->get_fd_message('option_fields_workflow'))));
      $o_form->add_fld('create_include', 'checkbox-label', label: $o_form->get_fd_message('label_create_include'), rules: 'int');
      $o_form->add_fld('create_form', 'checkbox-label', label: $o_form->get_fd_message('label_create_form'), rules: 'int');
      $o_form->add_fld('create_report', 'checkbox-label', label: $o_form->get_fd_message('label_create_report'), rules: 'int');
      $o_form->add_fld('create_templates', 'checkbox-label', label: $o_form->get_fd_message('label_create_templates'), rules: 'int');
      $o_form->add_fld('overwrite', 'checkbox-label', label: $o_form->get_fd_message('label_overwrite'), rules: 'int');
      $o_form->add_fld('backup', 'checkbox-label', label: $o_form->get_fd_message('label_backup'), rules: 'int');
      $o_form->add_fld('sync_mode', 'select-single-label', label: $o_form->get_fd_message('label_sync'), rules: 'parameter', options: $this->options(array('link' => $o_form->get_fd_message('option_sync_link'), 'none' => $o_form->get_fd_message('option_sync_none'), 'apply' => $o_form->get_fd_message('option_sync_apply'))));
      $o_form->add_fld('ki_package', 'checkbox-label', label: $o_form->get_fd_message('label_ki_package'), rules: 'int');
      $o_form->add_js_code(<<<'JS'
(function () {
   var byName = function (name) { return document.querySelector('[name="' + name + '"]'); };
   var modul = byName('xmodul');
   var dd = byName('dd_name');
   var table = byName('table_name');
   var dbFile = byName('db_file');
   var template = byName('module_template');
   var previousModule = '';

   var syncNames = function () {
      if (!modul || !dd || !table || !dbFile) return;
      var name = String(modul.value || '').trim();
      var oldDd = previousModule ? previousModule + 'Data' : '';
      var oldDb = previousModule ? previousModule + '.db3' : 'modul.db3';
      if (!dd.value || dd.value === oldDd) dd.value = name ? name + 'Data' : '';
      if (!table.value || table.value === oldDd) table.value = name ? name + 'Data' : '';
      if (!dbFile.value || dbFile.value === oldDb || dbFile.value === 'modul.db3') dbFile.value = name ? name + '.db3' : 'modul.db3';
      previousModule = name;
   };

   var applyTemplate = function () {
      if (!template) return;
      var presets = {
         form_report: [1, 1, 1],
         form: [1, 1, 0],
         report: [1, 0, 1],
         api: [1, 0, 0],
         blank: [0, 0, 0]
      };
      var preset = presets[template.value];
      if (!preset) return;
      ['create_include', 'create_form', 'create_report'].forEach(function (name, index) {
         var checkbox = byName(name);
         if (checkbox) checkbox.checked = preset[index] === 1;
      });
   };

   if (modul) {
      modul.addEventListener('input', syncNames);
      syncNames();
   }
   if (template) template.addEventListener('change', applyTemplate);
})();
JS
      );

      $ki_cta = '';
      if ($o_form->submit()) {
         $in = $this->collect_input($o_form);
         $errors = array();
         if ($this->validate_input($in, $errors) && !$o_form->errors()) {
            if ($this->generate_module($in, $log)) {
               $o_form->_msg_success = $o_form->get_fd_message('wizard_success');
               if ((int)$in['ki_package'] === 1) {
                  $ki_cta = $this->tpl()->get_tpl('dbxAdmin|wizard-ki-cta', array(
                     'cta_title' => $this->esc($o_form->get_fd_message('ki_cta_title')),
                     'cta_action' => $this->esc($o_form->get_fd_message('ki_cta_action')),
                     'ki_url' => $this->esc($this->ki_module_url($in['xmodul'], $in['dd_name'])),
                  ));
               }
            } else {
               $o_form->_msg_error = $o_form->get_fd_message('wizard_error');
            }
         } else {
            foreach ($errors as $err) {
               $o_form->add_fld_error('xmodul', $err);
            }
            $o_form->_msg_error = $o_form->get_fd_message('check_input');
         }
      }

      return $o_form->run() . $ki_cta . $this->log_html($log);
   }

   public function run($run = '') {
      $run3 = dbx()->get_modul_var('dbx_run3', '', 'parameter');
      if ($run3 === 'restore_backup') {
         return $this->restore_module_backup();
      }
      if ($run3 === 'ki_zip') {
         $this->send_ki_zip();
      }
      if ($run3 === 'ki_api') {
         $this->ki_api();
      }
      return $this->new_modul();
   }
}
