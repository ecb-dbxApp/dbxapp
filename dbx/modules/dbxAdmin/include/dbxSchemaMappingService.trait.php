<?php
namespace dbx\dbxAdmin;

trait dbxSchemaMappingServiceTrait {



   /**
    * Liest und normalisiert gepostete Schema-Mapping-Zuordnungen.
    *
    * @param array $model Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function posted_mapping_from_model($model) {
      $posted = $_POST['schema_map'] ?? array();
      if (!is_array($posted)) {
         return array();
      }

      $mapping = array();
      foreach ($posted as $target => $source) {
         $target = trim((string)$target);
         $source = trim((string)$source);

         if ($target === '' || $source === '') {
            continue;
         }

         $mapping[$source] = $target;
      }

      $o_dd = dbx()->get_system_obj('dbxDD');
      return $o_dd->normalize_schema_mapping(
         $mapping,
         $model['source_fields'] ?? array(),
         $model['target_fields'] ?? array()
      );
   }



   /**
    * Erzeugt die Select-Optionen fuer Quellfelder im Mapping-Editor.
    *
    * @param array $sources Eingabeparameter fuer diese Methode.
    * @param string $selected Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function source_options_html($sources, $selected) {
      $html = '<option value=""></option>';

      foreach ($sources as $source) {
         $name = (string)($source['name'] ?? '');
         if ($name === '') {
            continue;
         }

         $sel = (strcasecmp($name, (string)$selected) === 0) ? ' selected' : '';
         $meta = $this->field_type_label($source);
         $html .= '<option value="' . $this->esc($name) . '"' . $sel . '>'
              . $this->esc($name . ($meta ? ' - ' . $meta : ''))
              . '</option>';
      }

      return $html;
   }



   /**
    * Rendert die draggable Quellfelder des Mapping-Editors.
    *
    * @param array $sources Eingabeparameter fuer diese Methode.
    * @param array $mapping Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function render_source_items($sources, $mapping) {
      $texts = $this->schema_texts();
      $mapped = array();
      foreach ($mapping as $source => $target) {
         $mapped[strtolower((string)$source)] = $target;
      }

      $html = '';
      foreach ($sources as $source) {
         $name = (string)($source['name'] ?? '');
         if ($name === '') {
            continue;
         }

         $target = $mapped[strtolower($name)] ?? '';
         $classes = 'dbx-mapping-source';
         if ($target) {
            $classes .= ' is-used';
         }

         $html .= '<button type="button" class="' . $classes . '" draggable="true" '
              . 'data-mapping-source="' . $this->esc($name) . '" '
              . 'title="' . $this->esc($name) . '">'
              . '<span class="dbx-mapping-field-name">' . $this->esc($name) . '</span>'
              . '<span class="dbx-mapping-field-meta">' . $this->esc($this->field_type_label($source)) . '</span>'
              . '</button>';
      }

      if ($html === '') {
         $html = '<div class="dbx-mapping-empty">'
            . $this->esc($texts->get_fd_message('mapping_no_source_fields'))
            . '</div>';
      }

      return $html;
   }



   /**
    * Rendert die Zielzeilen des Mapping-Editors.
    *
    * @param array $model Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function render_mapping_rows($model) {
      $texts = $this->schema_texts();
      $sources = $model['source_fields'] ?? array();
      $html = '';

      foreach (($model['target_rows'] ?? array()) as $row) {
         $target = $row['target'] ?? array();
         $target_name = (string)($row['target_name'] ?? ($target['name'] ?? ''));
         if ($target_name === '') {
            continue;
         }

         $source_name = (string)($row['source_name'] ?? '');
         $status = (string)($row['status'] ?? 'new');

         $html .= '<div class="dbx-mapping-target" data-mapping-target="' . $this->esc($target_name) . '" '
              . 'data-mapping-status="' . $this->esc($status) . '">';
         $html .= '<div class="dbx-mapping-target-main">';
         $html .= '<div class="dbx-mapping-target-title">';
         $html .= '<span class="dbx-mapping-field-name">' . $this->esc($target_name) . '</span>';
         $html .= $this->mapping_status_label($status);
         $html .= '</div>';
         $html .= '<span class="dbx-mapping-field-meta">' . $this->esc($this->field_type_label($target)) . '</span>';
         $html .= '</div>';

         $html .= '<div class="dbx-mapping-drop" data-mapping-drop="' . $this->esc($target_name) . '">';
         $html .= '<select class="form-select form-select-sm" name="schema_map[' . $this->esc($target_name) . ']" '
              . 'data-mapping-select data-target="' . $this->esc($target_name) . '" '
              . 'data-auto-source="' . $this->esc($source_name) . '">';
         $html .= $this->source_options_html($sources, $source_name);
         $html .= '</select>';
         $html .= '<button type="button" class="btn btn-sm btn-outline-secondary" data-mapping-clear-row data-dbx-tooltip="'
              . $this->esc($texts->get_fd_message('mapping_clear_assignment')) . '">'
              . '<i class="bi bi-x-lg"></i></button>';
         $html .= '</div>';
         $html .= '</div>';
      }

      if ($html === '') {
         $html = '<div class="dbx-mapping-empty">'
            . $this->esc($texts->get_fd_message('mapping_no_target_fields'))
            . '</div>';
      }

      return $html;
   }



   /**
    * Rendert das komplette Mapping-Board.
    *
    * @param array $model Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function render_mapping_board($model) {
      $texts = $this->schema_texts();
      $kind = (string)($model['kind'] ?? 'dd_to_db');
      $mapping = $model['mapping'] ?? array();
      $source_count = count($model['source_fields'] ?? array());
      $target_count = count($model['target_fields'] ?? array());
      $mapped_count = count($mapping);
      $file = (string)($model['file'] ?? '');
      $file_label = $file !== '' ? $this->path_rel($file) : '';
      $updated = (string)($model['updated_at'] ?? '');

      $html = '<div class="dbx-schema-mapping" data-dbx="lib=mapping" data-mapping-root="1">';

      $html .= '<div class="dbx-mapping-head">';
      $html .= '<div>';
      $html .= '<h3>' . $this->esc($this->mapping_kind_label($kind)) . '</h3>';
      $html .= '<div class="dbx-mapping-subtitle">'
           . '<span>' . $this->esc($model['source_label'] ?? '') . '</span>'
           . '<i class="bi bi-arrow-right"></i>'
           . '<span>' . $this->esc($model['target_label'] ?? '') . '</span>'
           . '</div>';
      $html .= '</div>';

      $html .= '<div class="dbx-mapping-tools">';
      $html .= '<span class="badge bg-secondary" data-mapping-count="mapped">' . $this->esc($mapped_count . ' / ' . $target_count) . '</span>';
      $html .= '<button type="button" class="btn btn-outline-primary" data-mapping-action="auto" data-dbx-tooltip="'
         . $this->esc($texts->get_fd_message('mapping_auto'))
         . '"><i class="bi bi-magic"></i></button>';
      $html .= '<button type="button" class="btn btn-outline-secondary" data-mapping-action="clear" data-dbx-tooltip="'
         . $this->esc($texts->get_fd_message('mapping_clear'))
         . '"><i class="bi bi-eraser"></i></button>';
      $html .= '</div>';
      $html .= '</div>';

      $html .= '<div class="dbx-mapping-meta">';
      $html .= '<span>' . $this->esc($texts->get_fd_message('mapping_source'))
         . ': ' . $this->esc((string)$source_count) . '</span>';
      $html .= '<span>' . $this->esc($texts->get_fd_message('mapping_target'))
         . ': ' . $this->esc((string)$target_count) . '</span>';
      if ($updated !== '') {
         $html .= '<span>' . $this->esc($texts->get_fd_message('mapping_saved_at'))
            . ': ' . $this->esc($updated) . '</span>';
      }
      if ($file_label !== '') {
         $html .= '<span class="dbx-mapping-file">' . $this->esc($file_label) . '</span>';
      }
      $html .= '</div>';

      $html .= '<div class="dbx-mapping-workbench">';
      $html .= '<section class="dbx-mapping-panel dbx-mapping-panel-source">';
      $html .= '<header><span>'
         . $this->esc($texts->get_fd_message('mapping_source'))
         . '</span><span class="badge bg-light text-dark">'
         . $this->esc((string)$source_count) . '</span></header>';
      $html .= '<div class="dbx-mapping-source-list">' . $this->render_source_items($model['source_fields'] ?? array(), $mapping) . '</div>';
      $html .= '</section>';

      $html .= '<div class="dbx-mapping-canvas" aria-hidden="true"><svg data-mapping-lines></svg></div>';

      $html .= '<section class="dbx-mapping-panel dbx-mapping-panel-target">';
      $html .= '<header><span>'
         . $this->esc($texts->get_fd_message('mapping_target'))
         . '</span><span class="badge bg-light text-dark">'
         . $this->esc((string)$target_count) . '</span></header>';
      $html .= '<div class="dbx-mapping-target-list">' . $this->render_mapping_rows($model) . '</div>';
      $html .= '</section>';
      $html .= '</div>';

      $html .= '</div>';

      return $html;
   }



   /**
    * Erzeugt das Sync-Status-Badge aus einem Sync-Plan.
    *
    * @param array $plan Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function sync_label_from_plan($plan) {
      $texts = $this->schema_texts();
      if (!is_array($plan) || empty($plan['ok'])) {
         return $this->badge($texts->get_fd_message('status_dd_error'), 'danger');
      }

      if (empty($plan['table_exists'])) {
         return $this->badge($texts->get_fd_message('status_db_missing'), 'warning');
      }

      $adds = count($plan['add_fields'] ?? array());
      $idx  = count($plan['add_indexes'] ?? array());
      $miss = count($plan['missing_in_dd'] ?? array());
      $conf = count($plan['type_conflicts'] ?? array());

      if (!empty($plan['rebuild_needed'])) {
         return $this->badge($texts->get_fd_message('status_rebuild'), 'danger');
      }

      if ($adds || $idx) {
         return $this->badge($texts->get_fd_message('status_sync_open'), 'warning');
      }

      return $this->badge($texts->get_fd_message('status_synced'), 'success');
   }



   /**
    * Erzeugt den kompakten Sync-Detailtext aus einem Sync-Plan.
    *
    * @param array $plan Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function sync_detail_from_plan($plan) {
      if (!is_array($plan) || empty($plan['ok']) || empty($plan['table_exists'])) {
         return '';
      }

      $adds = count($plan['add_fields'] ?? array());
      $idx  = count($plan['add_indexes'] ?? array());
      $miss = count($plan['missing_in_dd'] ?? array());
      $conf = count($plan['type_conflicts'] ?? array());

      if (!empty($plan['rebuild_needed'])) {
         return $this->esc(
            $this->schema_texts()->format_fd_message('detail_type', array('count' => $conf))
            . ' / '
            . $this->schema_texts()->format_fd_message('detail_extra', array('count' => $miss))
         );
      }

      if ($adds || $idx) {
         return $this->esc(
            $this->schema_texts()->format_fd_message('detail_field', array('count' => $adds))
            . ' / '
            . $this->schema_texts()->format_fd_message('detail_index', array('count' => $idx))
         );
      }

      return '';
   }



   /**
    * Liest den DD-nach-DB-Sync-Plan fuer eine DD-Datei.
    *
    * @param string $modul Eingabeparameter fuer diese Methode.
    * @param string $dd Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function get_sync_plan($modul, $dd) {
      $o_dd = dbx()->get_system_obj('dbxDD');
      return $o_dd->sync_dd_to_db($modul, $dd, 'plan');
   }



   /**
    * Führt den DD-nach-DB-Sync-Prozess aus.
    *
    * @return string
    */
   private function run_sync_dd_to_db() {
      $modul = dbx()->get_modul_var('modul', 'dbx', 'parameter');
      $dd    = dbx()->get_modul_var('dd', '', 'parameter');
      $mode  = dbx()->get_modul_var('mode', 'apply', 'parameter');
      $reset = dbx()->get_modul_var('reset', 0, 'int');
      $cmd   = dbx()->get_modul_var('proc_cmd', '', 'parameter');
      $o_dd   = dbx()->get_system_obj('dbxDD');

      if (!$dd) {
         return '<div class="alert alert-danger">DD fehlt.</div>';
      }

      if ($reset) {
         $o_dd->sync_dd_to_db($modul, $dd, 'reset');
      }

      $next_url = $this->build_url('dd', 'sync_dd_to_db', array(
         'modul' => $modul,
         'dd'    => $dd,
         'mode'  => $mode,
      ));

      if ($cmd && in_array($cmd, array('pause', 'resume', 'continue', 'cancel'), true)) {
         $state = $o_dd->sync_dd_to_db($modul, $dd, $cmd);
         return $this->render_process('DD -> DB: ' . $modul . '|' . $dd, $state, $next_url, $this->build_url('dd', 'list_dd'));
      }

      $state = $o_dd->sync_dd_to_db($modul, $dd, $mode);

      $content = $this->render_process('DD -> DB: ' . $modul . '|' . $dd, $state, $next_url, $this->build_url('dd', 'list_dd'));

      if (($state['status'] ?? '') == 'error' && strpos((string)($state['message'] ?? ''), 'rebuild needed') !== false) {
         $force = $this->build_url('dd', 'sync_dd_to_db', array(
            'modul' => $modul,
            'dd'    => $dd,
            'mode'  => 'force',
            'reset' => 1,
         ));
         $content .= '<div class="mt-3"><a class="btn btn-danger" href="' . $this->esc($force) . '">Rebuild starten</a></div>';
      }

      return $content;
   }



   /**
    * Führt den DB-nach-DD-Sync-Prozess aus.
    *
    * @return string
    */
   private function run_sync_db_to_dd() {
      $server = dbx()->get_modul_var('server', '', 'parameter');
      $table  = dbx()->get_modul_var('table', '', 'parameter');
      $modul  = dbx()->get_modul_var('modul', '', 'parameter');
      $dd     = dbx()->get_modul_var('dd', '', 'parameter');
      $mode   = dbx()->get_modul_var('mode', 'merge', 'parameter');
      $reset  = dbx()->get_modul_var('reset', 0, 'int');
      $cmd    = dbx()->get_modul_var('proc_cmd', '', 'parameter');

      if (!$server || !$table) {
         return '<div class="alert alert-danger">Server oder Tabelle fehlt.</div>';
      }

      if (!$modul || !$dd) {
         return $this->form_db_to_dd($server, $table);
      }

      $o_dd = dbx()->get_system_obj('dbxDD');
      if ($reset) {
         $o_dd->sync_db_to_dd($modul, $dd, 'reset', $server, $table);
      }

      $next_url = $this->build_url('db', 'sync_db_to_dd', array(
         'server' => $server,
         'table'  => $table,
         'modul'  => $modul,
         'dd'     => $dd,
         'mode'   => $mode,
      ));

      if ($cmd && in_array($cmd, array('pause', 'resume', 'continue', 'cancel'), true)) {
         $state = $o_dd->sync_db_to_dd($modul, $dd, $cmd, $server, $table);
         return $this->render_process('DB -> DD: ' . $server . '|' . $table . ' -> ' . $modul . '|' . $dd, $state, $next_url, $this->build_url('db', 'list_db'));
      }

      $state = $o_dd->sync_db_to_dd($modul, $dd, $mode, $server, $table);

      return $this->render_process('DB -> DD: ' . $server . '|' . $table . ' -> ' . $modul . '|' . $dd, $state, $next_url, $this->build_url('db', 'list_db'));
   }



   /**
    * Rendert das Formular zum Erzeugen oder Mergen eines DD aus einer DB-Tabelle.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @param string $table Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function form_db_to_dd($server, $table) {
      $o_form = dbx()->get_system_obj('dbxForm');
      $o_form->init('form-schema-action', 'form-schema-action');
      $o_form->set_field_definition('dbxAdmin|schema-report');
      $o_form->load_fd_messages();
      $o_form->set_data(array(
         'server' => $server,
         'table'  => $table,
         'modul'  => 'dbx',
         'dd'     => $this->sanitize_dd_name($table),
      ));
      $o_form->set_action($this->build_url('db', 'sync_db_to_dd', array(
         'server' => $server,
         'table'  => $table,
      )));
      $o_form->_msg_info = $o_form->get_fd_message('db_to_dd_info');

      $o_form->add_fld('server', 'hidden', rules: 'parameter');
      $o_form->add_fld('table', 'hidden', rules: 'parameter');
      $o_form->add_fld(
         'modul',
         'select-single-label',
         label: $o_form->get_fd_message('label_module'),
         rules: 'parameter',
         options: $this->get_module_options()
      );
      $o_form->add_fld(
         'dd',
         'text-label',
         label: $o_form->get_fd_message('column_dd'),
         rules: 'parameter|min=1'
      );

      if ($o_form->submit() && !$o_form->errors()) {
         $modul = $o_form->get_post('modul', 'dbx', 'parameter');
         $dd = $this->sanitize_dd_name($o_form->get_post('dd', $table, 'parameter'));

         dbx()->set_modul_var('modul', $modul);
         dbx()->set_modul_var('dd', $dd);
         dbx()->set_modul_var('mode', 'merge');
         dbx()->set_modul_var('reset', 1);

         return $this->run_sync_db_to_dd();
      }

      return $o_form->run();
   }



   /**
    * Rendert und verarbeitet den Schema-Mapping-Editor.
    *
    * @return string
    */
   private function run_mapping_editor() {
      $texts = $this->schema_texts();
      $kind   = dbx()->get_modul_var('kind', 'dd_to_db', 'parameter');
      $modul  = dbx()->get_modul_var('modul', '', 'parameter');
      $dd     = dbx()->get_modul_var('dd', '', 'parameter');
      $server = dbx()->get_modul_var('server', '', 'parameter');
      $table  = dbx()->get_modul_var('table', '', 'parameter');

      if (!$dd && $kind !== 'transfer') {
         return '<div class="alert alert-danger">'
            . $this->esc($texts->get_fd_message('missing_dd'))
            . '</div>';
      }

      $o_dd = dbx()->get_system_obj('dbxDD');
      $context = array(
         'modul'  => $modul,
         'dd'     => $dd,
         'server' => $server,
         'table'  => $table,
      );

      $model = $o_dd->build_schema_mapping($kind, $context);

      $o_form = dbx()->get_system_obj('dbxForm');
      $o_form->init('schema-mapping', 'schema-mapping');
      $o_form->set_field_definition('dbxAdmin|schema-report');
      $o_form->load_fd_messages();
      $o_form->_msg_info = '';
      $o_form->set_action($this->build_url(($kind == 'db_to_dd') ? 'db' : 'dd', 'mapping', array(
         'kind'   => $kind,
         'modul'  => $modul,
         'dd'     => $dd,
         'server' => $server,
         'table'  => $table,
      )));

      $o_form->set_data(array(
         'kind'   => $kind,
         'modul'  => $modul,
         'dd'     => $dd,
         'server' => $server,
         'table'  => $table,
      ));

      $o_form->add_fld('kind', 'hidden', rules: 'parameter');
      $o_form->add_fld('modul', 'hidden', rules: 'parameter');
      $o_form->add_fld('dd', 'hidden', rules: 'parameter');
      $o_form->add_fld('server', 'hidden', rules: 'parameter');
      $o_form->add_fld('table', 'hidden', rules: 'parameter');

      if ($o_form->submit() && !$o_form->errors()) {
         $mapping = $this->posted_mapping_from_model($model);
         $ok = $o_dd->save_schema_mapping($kind, $model['context'] ?? $context, $mapping);

         if ($ok) {
            $o_form->_msg_success = $o_form->get_fd_message('mapping_saved');
            $model = $o_dd->build_schema_mapping($kind, $model['context'] ?? $context);
         } else {
            $o_form->_msg_error = $o_form->get_fd_message('mapping_save_error');
         }
      }

      $o_form->add_obj('mapping_board', 'obj-value', $this->render_mapping_board($model));
      $o_form->add_obj('save_button', 'obj-value',
         '<button type="submit" class="btn btn-primary" data-dbx-tooltip="'
            . $this->esc($o_form->get_fd_message('action_save'))
            . '"><i class="bi bi-save"></i></button>'
      );
      $o_form->add_obj('back_button', 'obj-value',
         '<a class="btn btn-secondary" href="' . $this->esc($this->build_url(($kind == 'db_to_dd') ? 'db' : 'dd', ($kind == 'db_to_dd') ? 'list_db' : 'list_dd')) . '" title="'
            . $this->esc($o_form->get_fd_message('action_back'))
            . '"><i class="bi bi-arrow-left"></i></a>'
      );

      return $o_form->run();
   }
}
