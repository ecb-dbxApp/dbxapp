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
      $texts->_fd = 'dbxAdmin|module-wizard';
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
      $baseNorm = str_replace('\\', '/', rtrim($base, '/\\') . '/');
      $pathNorm = str_replace('\\', '/', $path);
      if (strpos($pathNorm, $baseNorm) !== 0) {
         return '';
      }
      return $path;
   }

   private function module_options($withNew = false) {
      $options = $withNew ? array('' => $this->text('option_choose')) : array();
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

   private function dd_options($withEmpty = true) {
      $options = $withEmpty ? array('' => $this->text('option_choose_manual')) : array();
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
         if ($form && array_key_exists($name, $form->_post)) {
            return $form->_post[$name];
         }
         return dbx()->get_modul_var($name, $default, $rules);
      };

      $target = (string)$get('target_mode', 'new');
      $existingModul = trim((string)$get('existing_modul', ''));
      $modul  = trim((string)$get('xmodul', ''));
      if ($target === 'existing' && $existingModul !== '') {
         $modul = $existingModul;
      }
      if ($modul === '') {
         $modul = trim((string)$get('modul', ''));
      }

      $ddMode = (string)$get('dd_mode', 'new');
      $ddRef = trim((string)$get('dd_ref', ''));
      $title = trim((string)$get('title', $modul));
      $dd = trim((string)$get('dd_name', $modul ? $modul . 'Data' : ''));
      if ($ddRef !== '' && strpos($ddRef, '|') !== false) {
         list($refModul, $refDd) = explode('|', $ddRef, 2);
         if ($target === 'existing' && $modul === '' && $this->valid_name($refModul)) {
            $modul = $refModul;
         }
         if ($this->valid_name($refDd)) {
            $dd = $refDd;
         }
      }
      if ($dd === '' && $ddMode !== 'none' && $modul !== '') {
         $dd = $modul . 'Data';
      }
      $table = trim((string)$get('table_name', $dd));
      if ($table === '' && $ddMode !== 'none') {
         $table = $dd;
      }
      $dbFile = trim((string)$get('db_file', $modul ? $modul . '.db3' : 'modul.db3'));
      if ($modul !== '' && ($dbFile === '' || $dbFile === 'modul.db3')) {
         $dbFile = $modul . '.db3';
      }
      $defaultRun1 = trim((string)$get('default_run1', 'run'));
      if ($defaultRun1 === '') {
         $defaultRun1 = 'run';
      }

      return array(
         'target_mode'      => in_array($target, array('new', 'existing'), true) ? $target : 'new',
         'existing_modul'   => $existingModul,
         'dd_ref'           => $ddRef,
         'xmodul'           => $modul,
         'title'            => $title !== '' ? $title : $modul,
         'default_run1'     => $defaultRun1,
         'default_run2'     => trim((string)$get('default_run2', '')),
         'module_template'  => (string)$get('module_template', 'form_report'),
         'dd_mode'          => $ddMode,
         'dd_name'          => $dd,
         'table_name'       => $table,
         'db_file'          => $dbFile,
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
      if (((int)$in['create_form'] === 1 || (int)$in['create_report'] === 1) && (int)$in['create_templates'] !== 1) {
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
            list($refModul, $refDd) = explode('|', $in['dd_ref'], 2);
            if ($refModul !== $in['xmodul'] || $refDd !== $in['dd_name']) {
               $errors[] = $this->text('validation_dd_module');
            }
         }
         $ddFile = $this->module_path($in['xmodul'], 'dd/' . $in['dd_name'] . '.dd.php');
         if ($ddFile === '' || !is_file($ddFile)) {
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
      foreach (array('cfg', 'dd', 'db', 'fd', 'include', 'tpl', 'tpl/htm', 'tpl/mod', 'tpl/css', 'tpl/js', 'tpl/img') as $dir) {
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
      $zipPath = $this->backup_root() . $file;
      if (!is_file($zipPath)) {
         return '<div class="alert alert-warning">Backup-ZIP nicht gefunden.</div>';
      }
      if (!$confirm) {
         $url = '?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_new&dbx_run3=restore_backup&xmodul=' . rawurlencode($modul) . '&file=' . rawurlencode($file) . '&confirm=1';
         return '<div class="p-3"><h3>Modul wiederherstellen</h3><p>Restore ersetzt den Inhalt von <code>dbx/modules/' . $this->esc($modul) . '</code> durch das Backup <code>' . $this->esc($file) . '</code>.</p><a class="btn btn-danger" href="' . $this->esc($url) . '">Restore starten</a></div>';
      }

      $currentBackup = $this->backup_module($modul);
      $dir = $this->module_dir($modul);
      if (!is_dir($dir)) {
         @mkdir($dir, 0777, true);
      }
      $this->empty_dir($dir);
      $zip = new \ZipArchive();
      if ($zip->open($zipPath) !== true) {
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
      return '<div class="alert alert-success">Modul wiederhergestellt. Sicherheitsbackup vorher: ' . $this->esc($currentBackup) . '</div>';
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

   private function generate_form_fd(array $in) {
      $out = "<?php\n"
         . "\$messages = array();\n"
         . "\$messages['save_success'] = 'Daten wurden gespeichert';\n"
         . "\$messages['save_succeass'] = \$messages['save_success'];\n"
         . "\$messages['save_error'] = 'Daten konnten nicht gespeichert werden';\n\n";
      foreach ($this->field_defs($in['field_preset']) as $f) {
         if (in_array($f[0], array('id', 'create_date', 'create_uid', 'update_date', 'update_uid', 'owner', 'trash'), true)) {
            continue;
         }
         $out .= $this->dd_field_php($f);
      }
      return $out;
   }

   private function generate_report_fd() {
      return <<<'PHP'
<?php
$messages = array();
$messages['save_success'] = 'Daten wurden gespeichert';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Daten konnten nicht gespeichert werden';

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
   }

   private function generate_form_template() {
      return <<<'HTML'
<div id="dbxForm_{i}" class="dbx-panel dbxForm_wrapper">
 <form action="{action}" method="post" id="dbx_form_{i}" class="dbxAjax" data-target="dbxForm_{i}">
  [tpl=dbx|module-bar]
  <div class="dbx-panel-body">
   <div class="mb-3">{obj:form_msg}</div>
   <div class="row g-3">[dbx:form]</div>
  </div>
  [dbx:js]
 </form>
</div>
HTML;
   }

   private function generate_report_template() {
      return <<<'HTML'
[tpl=dbx|report-shell-head]
 [dbx:pagination]
 <div class="table-responsive">
  <table class="table table-striped table-bordered table-light table-hover align-middle">
   <thead>
    <tr class="{tr-class}">[rpt:row]</tr>
   </thead>
   <tbody>
    <hr class="dbx_split">
    <tr class="{tr-class}">[rpt:row]</tr>
    <hr class="dbx_split">
   </tbody>
  </table>
 </div>
[tpl=dbx|report-shell-foot]
HTML;
   }

   private function generate_main_class(array $in) {
      $modul = $in['xmodul'];
      $service = $modul . 'Service';
      $default = $in['default_run1'] ?: 'run';
      $startContent = $this->esc('Modul ' . $modul . ' ist bereit.');
      $startMethod = "   private function start() {\n      \$tpl = dbx()->get_system_obj('dbxTPL');\n      return \$tpl->get_tpl('$modul|start', array('content' => " . var_export($startContent, true) . "));\n   }\n";
      if ((int)$in['create_templates'] !== 1) {
         $startMethod = "   private function start() {\n      return '<div class=\"p-3\"><h2>" . $this->esc($in['title']) . "</h2><p>" . $startContent . "</p></div>';\n   }\n";
      }
      $cases = '';
      $cases .= "         case " . var_export($default, true) . ":\n            \$content = \$this->start();\n            break;\n\n";
      if ($in['create_form']) {
         $cases .= "         case 'form':\n            \$content = \$this->service()->form();\n            break;\n\n";
      }
      if ($in['create_report']) {
         $cases .= "         case 'report':\n            \$content = \$this->service()->report();\n            break;\n\n";
         $cases .= "         case 'detail':\n            \$content = \$this->service()->detail();\n            break;\n\n";
      }
      if ($in['module_template'] === 'api' || $in['create_include']) {
         $cases .= "         case 'api':\n            \$this->service()->api();\n            break;\n\n";
      }
      return "<?php\nnamespace dbx\\$modul;\n\nclass $modul {\n\n   private function service() {\n      return dbx()->get_include_obj('$service', '$modul');\n   }\n\n$startMethod\n   public function run() {\n      \$run = dbx()->get_modul_var('dbx_run1', " . var_export($default, true) . ", 'parameter');\n      \$content = '';\n      switch (\$run) {\n$cases         default:\n            \$content = '<div class=\"alert alert-warning\">Unbekannter Aufruf: ' . htmlspecialchars((string)\$run, ENT_QUOTES, 'UTF-8') . '</div>';\n      }\n      return \$content;\n   }\n}\n";
   }

   private function generate_service_class(array $in) {
      $modul = $in['xmodul'];
      $class = $modul . 'Service';
      $ddRef = $in['dd_mode'] !== 'none' ? $modul . '|' . $in['dd_name'] : '';
      $formFd = $modul . '|' . $in['dd_name'] . '-form';
      $reportFd = $modul . '|rpt-' . $in['dd_name'] . '-selection';
      $fields = array();
      foreach ($this->field_defs($in['field_preset']) as $f) {
         if (in_array($f[0], array('id', 'title', 'description', 'content', 'status', 'activ', 'update_date'), true)) {
            $fields[$f[0]] = $f[4];
         }
      }
      $fieldsExport = var_export($fields, true);
      $template = <<<'PHP'
<?php
namespace dbx\__MODUL__;

/**
 * Service-Grundgeruest aus dem dbxAdmin Modul-Wizard.
 *
 * Ziel:
 * - Mensch und KI sehen die vorgesehenen Erweiterungspunkte direkt im Modul.
 * - Formular: Save, Delete, Feldtemplates, Callbacks.
 * - Report: Multi-Select, Multi-Delete, Edit, Detail, Row-Actions, Callbacks.
 *
 * Harte Regel fuer KI-Arbeiten:
 * Nur Dateien unter dbx/modules/__MODUL__/ bearbeiten.
 */
class __CLASS__ {

   private $dd = __DD_REF__;
   private $formFd = __FORM_FD__;
   private $reportFd = __REPORT_FD__;

   private function db() {
      return dbx()->get_system_obj('dbxDB');
   }

   private function tpl() {
      return dbx()->get_system_obj('dbxTPL');
   }

   private function base_url($run1 = 'report', array $params = array()) {
      $url = '?dbx_modul=__MODUL__&dbx_run1=' . rawurlencode($run1);
      foreach ($params as $key => $value) {
         $url .= '&' . rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
      }
      return $url;
   }

   /**
    * Formular mit gaengigen dbxForm-Moeglichkeiten.
    *
    * Enthalten:
    * - Insert/Update via save_post()
    * - Delete per dbx_do=delete
    * - FD-basierte Felder aus __FORM_FD__
    * - Callback-Beispiele fuer init, submit und run
    * - Beispiele fuer Feldtemplates in configure_form_fields()
    */
   public function form($rid = null) {
      if ($this->dd === '') return '<div class="alert alert-info">Kein DD konfiguriert.</div>';

      $rid = $rid === null ? (int)dbx()->get_modul_var('rid', 0, 'int') : (int)$rid;
      $do = dbx()->get_modul_var('dbx_do', '', 'parameter');

      if ($do === 'delete' && $rid > 0) {
         return $this->delete_record($rid);
      }

      $data = $rid > 0 ? $this->db()->select1($this->dd, $rid) : array('activ' => 1);
      if (!is_array($data)) $data = array('activ' => 1);

      $form = dbx()->get_system_obj('dbxForm');
      $form->init('__MODUL__-form');
      $form->set_form_callback_owner($this);
      $form->set_init_callback('form_init_callback');
      $form->set_submit_callback('form_submit_callback');
      $form->set_run_callback('form_run_callback');

      $form->_dd = $this->dd;
      $form->_fd = $this->formFd;
      $form->_data = $data;
      $form->_rid = $rid;
      $form->_action = $this->base_url('form', $rid > 0 ? array('rid' => $rid) : array());
      $form->add_rep('bar_title', $rid > 0 ? 'Datensatz bearbeiten' : 'Neuer Datensatz');
      $form->add_rep('bar_subtitle', $this->dd);
      $form->add_obj('bar_actions', 'obj-value', $this->form_action_buttons($rid));
      $form->_msg_info = 'Felder ausfuellen und mit Speichern uebernehmen.';

      $this->configure_form_fields($form);

      if ($form->submit() && !$form->errors()) {
         $ok = $form->save_post($this->dd, $rid > 0 ? $rid : 'new', $this->form_save_defaults($rid));
         if ($ok) {
            $form->_msg_success = 'Datensatz gespeichert.';
         } else {
            $form->_msg_error = 'Datensatz konnte nicht gespeichert werden.';
         }
      }

      return $form->run();
   }

   private function form_action_buttons($rid) {
      $html = '<button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-save"></i> Speichern</button>';
      if ($rid > 0) {
         $delete = $this->base_url('form', array('rid' => $rid, 'dbx_do' => 'delete'));
         $html .= ' <a class="btn btn-outline-danger btn-sm dbxConfirm" href="' . htmlspecialchars($delete, ENT_QUOTES, 'UTF-8') . '" data-confirm-title="Datensatz loeschen" data-confirm="Diesen Datensatz wirklich loeschen?" data-confirm-buttons="yesno"><i class="bi bi-trash"></i> Loeschen</a>';
      }
      $html .= ' <a class="btn btn-outline-secondary btn-sm" href="' . htmlspecialchars($this->base_url('report'), ENT_QUOTES, 'UTF-8') . '"><i class="bi bi-table"></i> Report</a>';
      return $html;
   }

   private function configure_form_fields($form) {
      $form->add_flds();

      /*
       * Gängige Feldtemplates fuer FD/DD:
       * - text-label: einzeiliger Text
       * - textarea-label: mehrzeiliger Text / HTML je nach Regeln
       * - checkbox-label: 0/1
       * - select-single-label: einfache Auswahl mit options array(...)
       * - select-multible-label / multiselect2: Mehrfachauswahl, name[]
       * - date-label, integer-label, password-label, hidden
       *
       * Direktes Feld-Beispiel ohne FD:
       * $form->add_fld('status', 'select-single-label', label: 'Status', rules: 'parameter', options: array('open' => 'Offen', 'done' => 'Erledigt'));
       */
   }

   private function form_save_defaults($rid) {
      $uid = (int)dbx()->user();
      $now = date('Y-m-d H:i:s');
      $defaults = array('update_date' => $now, 'update_uid' => $uid);
      if ((int)$rid <= 0) {
         $defaults['create_date'] = $now;
         $defaults['create_uid'] = $uid;
         $defaults['owner'] = $uid;
         $defaults['trash'] = 0;
      }
      return $defaults;
   }

   public function delete_record($rid) {
      $rid = (int)$rid;
      if ($rid <= 0) return '<div class="alert alert-warning">Kein Datensatz gewaehlt.</div>';
      $ok = $this->db()->delete($this->dd, $rid);
      if ($ok) {
         return '<div class="alert alert-success">Datensatz geloescht.</div>' . $this->report();
      }
      return '<div class="alert alert-danger">Datensatz konnte nicht geloescht werden.</div>' . $this->report();
   }

   public function detail($rid = null) {
      $rid = $rid === null ? (int)dbx()->get_modul_var('rid', 0, 'int') : (int)$rid;
      if ($rid <= 0) return '<div class="alert alert-warning">Kein Datensatz gewaehlt.</div>';
      $record = $this->db()->select1($this->dd, $rid);
      if (!is_array($record)) return '<div class="alert alert-warning">Datensatz nicht gefunden.</div>';

      $rows = '';
      foreach ($record as $key => $value) {
         $rows .= '<tr><th>' . htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8') . '</th><td>' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '</td></tr>';
      }
      return '<div class="p-3"><h3>Detail #' . $rid . '</h3><table class="table table-sm table-bordered">' . $rows . '</table><p><a class="btn btn-primary btn-sm" href="' . htmlspecialchars($this->base_url('form', array('rid' => $rid)), ENT_QUOTES, 'UTF-8') . '">Bearbeiten</a> <a class="btn btn-outline-secondary btn-sm" href="' . htmlspecialchars($this->base_url('report'), ENT_QUOTES, 'UTF-8') . '">Zurueck</a></p></div>';
   }

   /**
    * Report mit gaengigen dbxReport-Moeglichkeiten.
    *
    * Enthalten:
    * - Multi-Select ueber Remember
    * - Multi-Delete ueber delete_multi_selected_records()
    * - Row Edit / Row Delete / Row Detail
    * - Report-Callbacks: header/body/footer/page/report/next_record/row_action_data
    */
   public function report() {
      if ($this->dd === '') return '<div class="alert alert-info">Kein DD konfiguriert.</div>';

      $report = dbx()->get_system_obj('dbxReport');
      $report->init('__MODUL__-report');
      $report->set_callback_owner($this);
      $report->set_report_header_callback('report_header_callback');
      $report->set_page_header_callback('report_page_header_callback');
      $report->set_header_callback('report_table_header_callback');
      $report->set_body_callback('report_body_callback');
      $report->set_footer_callback('report_table_footer_callback');
      $report->set_page_footer_callback('report_page_footer_callback');
      $report->set_report_footer_callback('report_footer_callback');
      $report->set_next_record_callback('report_next_record_callback');
      $report->set_callback('row_action_data', 'report_row_action_data_callback');

      $report->_dd = $this->dd;
      $report->_action = $this->base_url('report');
      $report->_pages = true;
      $report->_mode = 'table';
      $report->_but_pagination = 9;
      $report->_multi_page_select = 1;
      $report->_create_sel_flds = true;
      $report->_create_row_select = true;
      $report->_create_row_edit = true;
      $report->_create_row_show = true;
      $report->_create_row_delete = true;
      $report->_msg_confirm_delete = 'Diesen Datensatz wirklich loeschen?';
      $report->add_rep('bar_title', 'Datensaetze');
      $report->add_rep('bar_subtitle', $this->dd);
      $report->add_rep('bar_icon', 'bi-table');
      $report->add_rep('bar_class', 'dbx-module-bar');
      $report->add_rep('bar_title_class', 'dbx-module-bar-titleblock');
      $report->add_rep('bar_actions_class', 'dbx-module-bar-actions');
      $report->add_rep('bar_title_pre', '');
      $report->add_rep('bar_title_heading_attrs', '');
      $report->add_rep('bar_middle', '');
      $report->add_rep('bar_extra', '');
      $report->add_rep('bar_actions', '<a class="btn btn-primary btn-sm" href="' . htmlspecialchars($this->base_url('form'), ENT_QUOTES, 'UTF-8') . '"><i class="bi bi-plus-lg"></i> Neu</a>');

      $report->add_action('rows_select', 'action_button_select', '&dbx_do=rows_select');
      $report->add_action('rows_deselect', 'action_button_deselect', '&dbx_do=clear_selects');
      $report->add_action('rows_delete', 'action_button_delete', '&dbx_do=multi_delete');
      $report->create_selection_fields($this->reportFd);

      $actionContent = $this->handle_report_action($report);
      if ($actionContent !== '') {
         return $actionContent;
      }

      $rwhere = $report->get_fld_val('dbx_rwhere', '', 'sqlsearch|max=64');
      $rsort = $report->get_fld_val('dbx_rsort', 'title', 'parameter');
      $rdesc = $report->get_fld_val('dbx_rdesc', 'ASC', 'parameter');
      $rrows = $report->get_fld_val('dbx_rrows', 20, 'int');
      $rpos = $report->get_fld_val('dbx_rpos', 0, 'int');
      $rselect = $report->get_fld_val('dbx_rselect', 0, 'int');

      if ($rwhere !== '') {
         $rwhere = array('search' => array('value' => $rwhere, 'like' => array('title', 'description'), 'mode' => 'contains'));
      }
      if ($rselect) {
         $rwhere = $report->add_rwhere_select(is_string($rwhere) ? $rwhere : '');
      }

      $flds = $this->report_fields();
      $report->_rflds = $flds;
      $report->_rrows = $rrows;
      $report->_rpos = $rpos;
      $report->_count_all = $this->db()->count($this->dd);
      $report->_rcount = $this->db()->count($this->dd, $rwhere);
      $report->_rdata = $this->db()->select($this->dd, $rwhere, $flds, $rsort, $rdesc, '', $rrows, $rpos);

      return $report->run();
   }

   private function handle_report_action($report) {
      $do = dbx()->get_modul_var('dbx_do', '', 'parameter');
      $rid = (int)dbx()->get_modul_var('rid', 0, 'int');

      if ($do === 'row_edit' && $rid > 0) {
         return $this->form($rid);
      }
      if (($do === 'row_show' || $do === 'detail') && $rid > 0) {
         return $this->detail($rid);
      }
      if ($do === 'row_delete' && $rid > 0) {
         $ok = $this->db()->delete($this->dd, $rid);
         $report->del_selected($rid);
         $report->_msg_success = $ok ? 'Datensatz geloescht.' : '';
         $report->_msg_error = $ok ? '' : 'Datensatz konnte nicht geloescht werden.';
         return '';
      }
      if ($do === 'multi_delete') {
         $result = $report->delete_multi_selected_records($this->dd);
         $report->apply_multi_delete_result($result);
         return '';
      }
      return '';
   }

   private function report_fields() {
      return __FIELDS_EXPORT__;
   }

   public function report_header_callback($report, $content) {
      return $content;
   }

   public function report_page_header_callback($report, $content) {
      return $content;
   }

   public function report_table_header_callback($report, $content) {
      return $content;
   }

   public function report_body_callback($report, $content) {
      return $content;
   }

   public function report_table_footer_callback($report, $content) {
      return $content;
   }

   public function report_page_footer_callback($report, $content) {
      return $content;
   }

   public function report_footer_callback($report, $content) {
      return $content;
   }

   public function report_next_record_callback($report, $record) {
      if (is_array($record) && isset($record['status'])) {
         $record['status'] = strtoupper((string)$record['status']);
      }
      return $record;
   }

   public function report_row_action_data_callback($report, $payload) {
      if (is_array($payload) && isset($payload['data'], $payload['type'])) {
         if ($payload['type'] === 'show') {
            $payload['data']['tooltip'] = 'Details anzeigen';
         }
      }
      return $payload;
   }

   public function form_init_callback($form, $value) {
      return $value;
   }

   public function form_submit_callback($form, $value) {
      return $value;
   }

   public function form_run_callback($form, $content) {
      return $content;
   }

   public function api() {
      dbx()->json_response(array('ok' => 1, 'module' => '__MODUL__', 'dd' => $this->dd));
   }
}
PHP;

      return str_replace(
         array('__MODUL__', '__CLASS__', '__DD_REF__', '__FORM_FD__', '__REPORT_FD__', '__FIELDS_EXPORT__'),
         array($modul, $class, var_export($ddRef, true), var_export($formFd, true), var_export($reportFd, true), $fieldsExport),
         $template
      );
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
- Templates werden in PHP ueber `dbx()->get_system_obj('dbxTPL')->get_tpl('__MODUL__|template', $data)` gerendert.
- DD->DB Sync nur fuer `__MODUL__|__DD__`.

## Dateien

- `__MODUL__.class.php`: Einstieg und Routing nach `dbx_run1`.
- `include/__MODUL__Service.class.php`: Formular, Report, Detail, API und Callbacks.
- `dd/__DD__.dd.php`: Data Dictionary und DB-Struktur.
- `fd/__DD__-form.fd.php`: Formularfelder.
- `fd/rpt-__DD__-selection.fd.php`: Report-Filter und Report-Auswahl.
- `tpl/htm/start.htm`: Start-Template.

## Formular

Route: `?dbx_modul=__MODUL__&dbx_run1=form`

Enthaltene Muster:

- `save_post()` fuer Insert/Update.
- `dbx_do=delete` fuer Delete.
- `form_action_buttons()` fuer Save/Delete/Report Buttons.
- `configure_form_fields()` als zentraler Ort fuer Felder.
- Form-Callbacks:
  - `form_init_callback($form, $value)`
  - `form_submit_callback($form, $value)`
  - `form_run_callback($form, $content)`

Gängige Feldtemplates:

- `text-label`: einzeiliger Text.
- `textarea-label`: mehrzeiliger Text, auch fuer HTML-Felder geeignet wenn Regeln es zulassen.
- `checkbox-label`: 0/1 Checkbox.
- `select-single-label`: einfache Auswahl mit `options`.
- `select-multible-label` oder `multiselect2`: Mehrfachauswahl.
- `date-label`: Datum.
- `integer-label`: Zahlen.
- `password-label`: Passwort.
- `hidden`: verstecktes Feld.

## Report

Route: `?dbx_modul=__MODUL__&dbx_run1=report`

Enthaltene Muster:

- Multi-Select ueber dbxReport Remember.
- Multi-Delete ueber `delete_multi_selected_records()`.
- Row-Edit ueber `dbx_do=row_edit`.
- Row-Detail ueber `dbx_do=row_show` oder `dbx_do=detail`.
- Row-Delete ueber `dbx_do=row_delete`.
- Filter `dbx_rselect=1` zeigt nur ausgewaehlte IDs.

Report-Callbacks:

- `report_header_callback($report, $content)`
- `report_page_header_callback($report, $content)`
- `report_table_header_callback($report, $content)`
- `report_body_callback($report, $content)`
- `report_table_footer_callback($report, $content)`
- `report_page_footer_callback($report, $content)`
- `report_footer_callback($report, $content)`
- `report_next_record_callback($report, $record)`
- `report_row_action_data_callback($report, $payload)`

## API

Route: `?dbx_modul=__MODUL__&dbx_run1=api`

Die API-Methode ist ein Platzhalter und liefert JSON. Erweiterungen bleiben im Modul.
MD;

      return str_replace(array('__MODUL__', '__DD__'), array($modul, $dd), $template);
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

      $config = "<?php\n\$config['version']='1.0';\n\$config['activ']='1';\n\$config['dbxConfig_modul']='secure';\n\$config['groups']='admin';\n\$config['title']=" . var_export($in['title'], true) . ";\n\$config['default_run1']=" . var_export($in['default_run1'], true) . ";\n\$config['default_run2']=" . var_export($in['default_run2'], true) . ";\n";
      $write('cfg/config.php', $config);
      $write($modul . '.class.php', $this->generate_main_class($in));

      if ((int)$in['create_include'] === 1) {
         $write('include/' . $modul . 'Service.class.php', $this->generate_service_class($in));
      }
      $write('README-MODUL-WIZARD.md', $this->generate_module_readme($in));
      if ($in['dd_mode'] === 'new') {
         $write('dd/' . $in['dd_name'] . '.dd.php', $this->generate_dd($in));
      }
      if ((int)$in['create_form'] === 1 && $in['dd_mode'] !== 'none') {
         $write('fd/' . $in['dd_name'] . '-form.fd.php', $this->generate_form_fd($in));
      }
      if ((int)$in['create_report'] === 1 && $in['dd_mode'] !== 'none') {
         $write('fd/rpt-' . $in['dd_name'] . '-selection.fd.php', $this->generate_report_fd());
      }
      if ((int)$in['create_templates'] === 1) {
         $write('tpl/htm/modul-help.htm', '<p>' . $this->esc($in['title']) . '</p>');
         $write('tpl/htm/start.htm', $this->generate_result_template($in));
         if ((int)$in['create_form'] === 1) {
            $write('tpl/htm/' . strtolower($modul) . '-form.htm', $this->generate_form_template());
         }
         if ((int)$in['create_report'] === 1) {
            $write('tpl/htm/' . strtolower($modul) . '-report.htm', $this->generate_report_template());
         }
      }

      if ($in['dd_mode'] !== 'none') {
         $syncUrl = '?dbx_modul=dbxAdmin&dbx_run1=dd&dbx_run2=sync_dd_to_db&modul=' . rawurlencode($modul) . '&dd=' . rawurlencode($in['dd_name']) . '&mode=apply&reset=1';
         if ($in['sync_mode'] === 'apply') {
            $state = $this->sync_dd_to_db($modul, $in['dd_name']);
            $syncOk = (($state['status'] ?? '') === 'finished');
            $log[] = array('type' => $syncOk ? 'ok' : 'error', 'text' => 'DD->DB Sync: ' . (string)($state['message'] ?? ($state['status'] ?? '')));
            $ok = $syncOk && $ok;
         } elseif ($in['sync_mode'] === 'link') {
            $log[] = array('type' => 'link', 'text' => 'DD->DB Sync: ' . $syncUrl);
         }
      }

      $startUrl = '?dbx_modul=' . rawurlencode($modul) . '&dbx_run1=' . rawurlencode($in['default_run1'] ?: 'run');
      $log[] = array('type' => 'link', 'text' => 'Modul starten: ' . $startUrl);
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
         $kiUrl = '?dbx_modul=dbxKi&dbx_run1=briefing_module&xmodul=' . rawurlencode($modul) . '&dd_name=' . rawurlencode($in['dd_name']);
         $apiUrl = '?dbx_modul=dbxKi&dbx_run1=module_api&action=module.describe&xmodul=' . rawurlencode($modul);
         $log[] = array('type' => 'link', 'text' => 'KI-Modulauftrag in dbxKi: ' . $kiUrl);
         $log[] = array('type' => 'link', 'text' => 'dbxKi Modul-API: ' . $apiUrl);
      }
      return $ok;
   }

   private function sync_dd_to_db($modul, $dd) {
      $oDD = dbx()->get_system_obj('dbxDD');
      $oDD->sync_dd_to_db($modul, $dd, 'reset');
      $state = array('status' => 'running', 'message' => '');
      for ($i = 0; $i < 20; $i++) {
         $state = $oDD->sync_dd_to_db($modul, $dd, 'apply');
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
            'module.dd.write' => 'DD unter dd/*.dd.php schreiben',
            'module.dd.sync' => 'DD->DB Sync fuer Modul-DD anfordern',
            'module.template.get' => 'Template ueber dbxTPL/get_tpl lesen oder rendern',
            'module.template.set' => 'Template unter tpl/* ueber die dbxapp-Template-Mechanik schreiben',
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
      $oForm = dbx()->get_system_obj('dbxForm');
      $oForm->init('form-wizzard-new');
      $oForm->_fd = 'dbxAdmin|module-wizard';
      $oForm->load_fd_messages();
      $oForm->_action = '?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_new';
      $oForm->_data = array_merge(array(
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
      ), $in);
      $oForm->add_rep('bar_title', $oForm->get_fd_message('bar_title'));
      $oForm->add_rep('bar_subtitle', $oForm->get_fd_message('bar_subtitle'));
      $oForm->add_obj(
         'bar_actions',
         'obj-value',
         '<button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-magic"></i> '
            . $this->esc($oForm->get_fd_message('action_create')) . '</button>'
      );
      $oForm->_msg_info = $oForm->get_fd_message('wizard_info');

      $oForm->add_fld('target_mode', 'select-single-label', label: $oForm->get_fd_message('label_target'), rules: 'parameter', options: $this->options(array('new' => $oForm->get_fd_message('option_target_new'), 'existing' => $oForm->get_fd_message('option_target_existing'))));
      $oForm->add_fld('existing_modul', 'select-single-label', label: $oForm->get_fd_message('label_existing_module'), rules: 'parameter', options: $this->options($this->module_options(true)));
      $oForm->add_fld('xmodul', 'text-label', label: $oForm->get_fd_message('label_new_module'), rules: 'parameter|max=63', tooltip: $oForm->get_fd_message('tooltip_module_name'));
      $oForm->add_fld('title', 'text-label', label: $oForm->get_fd_message('label_title'), rules: 'varchar|max=120');
      $oForm->add_fld('module_template', 'select-single-label', label: $oForm->get_fd_message('label_template'), rules: 'parameter', options: $this->options(array('form_report' => $oForm->get_fd_message('option_template_form_report'), 'form' => $oForm->get_fd_message('option_template_form'), 'report' => $oForm->get_fd_message('option_template_report'), 'api' => $oForm->get_fd_message('option_template_api'), 'blank' => $oForm->get_fd_message('option_template_blank'))));
      $oForm->add_fld('default_run1', 'text-label', label: $oForm->get_fd_message('label_run1'), rules: 'parameter|max=32');
      $oForm->add_fld('default_run2', 'text-label', label: $oForm->get_fd_message('label_run2'), rules: 'parameter|max=32');
      $oForm->add_fld('dd_mode', 'select-single-label', label: $oForm->get_fd_message('label_dd'), rules: 'parameter', options: $this->options(array('new' => $oForm->get_fd_message('option_dd_new'), 'existing' => $oForm->get_fd_message('option_dd_existing'), 'none' => $oForm->get_fd_message('option_dd_none'))));
      $oForm->add_fld('dd_ref', 'select-single-label', label: $oForm->get_fd_message('label_existing_dd'), rules: 'parameter', options: $this->options($this->dd_options(true)));
      $oForm->add_fld('dd_name', 'text-label', label: $oForm->get_fd_message('label_dd_name'), rules: 'parameter|max=63');
      $oForm->add_fld('table_name', 'text-label', label: $oForm->get_fd_message('label_table'), rules: 'parameter|max=63');
      $oForm->add_fld('db_file', 'text-label', label: $oForm->get_fd_message('label_db_file'), rules: 'parameter+.-_|max=80');
      $oForm->add_fld('field_preset', 'select-single-label', label: $oForm->get_fd_message('label_fields'), rules: 'parameter', options: $this->options(array('basic' => $oForm->get_fd_message('option_fields_basic'), 'content' => $oForm->get_fd_message('option_fields_content'), 'status' => $oForm->get_fd_message('option_fields_status'), 'workflow' => $oForm->get_fd_message('option_fields_workflow'))));
      $oForm->add_fld('create_include', 'checkbox-label', label: $oForm->get_fd_message('label_create_include'), rules: 'int');
      $oForm->add_fld('create_form', 'checkbox-label', label: $oForm->get_fd_message('label_create_form'), rules: 'int');
      $oForm->add_fld('create_report', 'checkbox-label', label: $oForm->get_fd_message('label_create_report'), rules: 'int');
      $oForm->add_fld('create_templates', 'checkbox-label', label: $oForm->get_fd_message('label_create_templates'), rules: 'int');
      $oForm->add_fld('overwrite', 'checkbox-label', label: $oForm->get_fd_message('label_overwrite'), rules: 'int');
      $oForm->add_fld('backup', 'checkbox-label', label: $oForm->get_fd_message('label_backup'), rules: 'int');
      $oForm->add_fld('sync_mode', 'select-single-label', label: $oForm->get_fd_message('label_sync'), rules: 'parameter', options: $this->options(array('link' => $oForm->get_fd_message('option_sync_link'), 'none' => $oForm->get_fd_message('option_sync_none'), 'apply' => $oForm->get_fd_message('option_sync_apply'))));
      $oForm->add_fld('ki_package', 'checkbox-label', label: $oForm->get_fd_message('label_ki_package'), rules: 'int');
      $oForm->add_js_code(<<<'JS'
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

      if ($oForm->submit()) {
         $in = $this->collect_input($oForm);
         $errors = array();
         if ($this->validate_input($in, $errors) && !$oForm->errors()) {
            if ($this->generate_module($in, $log)) {
               $oForm->_msg_success = $oForm->get_fd_message('wizard_success');
            } else {
               $oForm->_msg_error = $oForm->get_fd_message('wizard_error');
            }
         } else {
            foreach ($errors as $err) {
               $oForm->add_fld_error('xmodul', $err);
            }
            $oForm->_msg_error = $oForm->get_fd_message('check_input');
         }
      }

      return $oForm->run() . $this->log_html($log);
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
