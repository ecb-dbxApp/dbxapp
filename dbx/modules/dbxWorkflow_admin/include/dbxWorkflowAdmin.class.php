<?php
namespace dbx\dbxWorkflow_admin;

class dbxWorkflowAdmin {

   private $ddDefinition = 'dbxWorkflow|workflowDefinition';
   private $ddInstance   = 'dbxWorkflow|workflowInstance';
   private $ddStep       = 'dbxWorkflow|workflowStep';
   private $ddBind       = 'dbxWorkflow|workflowModuleBind';

   private function tpl() {
      return dbx()->get_system_obj('dbxTPL');
   }

   private function db() {
      return dbx()->get_system_obj('dbxDB');
   }

   private function h($value) {
      return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
   }

   private function alert($type, $msg) {
      return $this->tpl()->get_tpl('dbx|alert-' . $type, array('msg' => $msg));
   }

   private function frame($content, $texts = null) {
      $help = dbx()->get_include_obj('dbxAdminHelp', 'dbxAdmin');
      $topic = $help->resolveTopic('dbxWorkflow_admin');
      $title = $texts
         ? $texts->get_fd_message('bar_title', 'Workflow Definitionen')
         : 'Workflow Definitionen';
      $subtitle = $texts
         ? $texts->get_fd_message('bar_subtitle', 'Workflow-Definitionen')
         : 'Workflow-Definitionen';
      $moduleBindings = $texts
         ? $texts->get_fd_message('module_bindings', 'Modul-Bindings')
         : 'Modul-Bindings';
      $newBinding = $texts
         ? $texts->get_fd_message('new_binding', 'Neues Binding')
         : 'Neues Binding';
      $newWorkflow = $texts
         ? $texts->get_fd_message('new_workflow', 'Neuer Workflow')
         : 'Neuer Workflow';
      $barActions = ''
         . '<a class="btn btn-outline-secondary btn-sm" href="?dbx_modul=dbxWorkflow_admin&dbx_run1=binds"><i class="bi bi-plug"></i> ' . $this->h($moduleBindings) . '</a>'
         . '<a class="btn btn-primary btn-sm" href="?dbx_modul=dbxWorkflow_admin&dbx_run1=edit_bind&rid=new"><i class="bi bi-plus-circle"></i> ' . $this->h($newBinding) . '</a>'
         . '<a class="btn btn-primary btn-sm" href="?dbx_modul=dbxWorkflow_admin&dbx_run1=edit&rid=new"><i class="bi bi-plus-circle"></i> ' . $this->h($newWorkflow) . '</a>';

      return $this->tpl()->get_tpl('dbxWorkflow_admin|workflow-admin-actions', array_merge(
         $help->moduleBarTemplateData($topic, $barActions),
         array(
            'bar_title' => $this->h($title),
            'bar_subtitle' => $this->h($subtitle),
            'bar_icon' => 'bi-diagram-3',
            'bar_actions' => $barActions,
            'content' => $content,
         )
      ));
   }

   private function install() {
      $oDD = dbx()->get_system_obj('dbxDD');
      foreach (array('workflowDefinition', 'workflowInstance', 'workflowStep', 'workflowModuleBind') as $dd) {
         $oDD->sync_dd_to_db('dbxWorkflow', $dd, 'reset');
         for ($i = 0; $i < 80; $i++) {
            $state = $oDD->sync_dd_to_db('dbxWorkflow', $dd, 'apply');
            $status = (string)($state['status'] ?? '');
            if ($status === 'finished' || $status === 'error') break;
         }
      }

      $engine = dbx()->get_include_obj('dbxWorkflowEngine', 'dbxWorkflow');
      $engine->seed_demo_definitions();

      return $this->frame($this->alert('success', 'Workflow-Datenbank installiert. Definitionen, Modul-Bindings und Demo-Workflows wurden angelegt/aktualisiert.'));
   }

   private function default_definition_text() {
      return dbx()->get_include_obj('dbxWorkflowEngine', 'dbxWorkflow')->default_definition_text();
   }

   private function load_row($rid) {
      if ($rid === 'new' || (int)$rid <= 0) {
         return array(
            'workflow_key' => 'rechnung_erstellen',
            'title' => 'Rechnung erstellen',
            'result_label' => 'Rechnung',
            'description' => 'Alle für eine richtige Rechnung benötigten Angaben werden geprüft und anschließend in klaren Schritten vervollständigt.',
            'definition_json' => $this->default_definition_text(),
            'active' => 1
         );
      }

      $rows = $this->db()->select($this->ddDefinition, array('id' => (int)$rid), '*', 'id', 'DESC', '', 1, 0, 0);
      return (is_array($rows) && isset($rows[0])) ? $rows[0] : array();
   }

   private function normalize_key($value) {
      $value = strtolower(trim((string)$value));
      $value = preg_replace('/[^a-z0-9_]+/', '_', $value);
      return trim($value, '_');
   }

   private function workflow_action_options($selected, $texts) {
      $items = array(
         'form' => $texts->get_fd_message('action_form'),
         'select' => $texts->get_fd_message('action_select'),
         'create' => $texts->get_fd_message('action_create'),
         'module' => $texts->get_fd_message('action_module'),
      );
      $html = '';
      foreach ($items as $value => $label) {
         $html .= '<option value="' . $this->h($value) . '"' . ((string)$selected === $value ? ' selected' : '') . '>' . $this->h($label) . '</option>';
      }
      return $html;
   }

   private function workflow_action_choices($index, array $selected, $texts) {
      $items = array(
         'form' => $texts->get_fd_message('action_form'),
         'select' => $texts->get_fd_message('action_select'),
         'create' => $texts->get_fd_message('action_create'),
         'module' => $texts->get_fd_message('action_module_short'),
      );
      $selected = array_fill_keys(array_map('strval', $selected), true);
      $html = '';
      foreach ($items as $value => $label) {
         $id = 'workflow-step-action-' . (int)$index . '-' . $value;
         $html .= '<label class="dbx-workflow-action-choice" for="' . $id . '">'
            . '<input class="form-check-input" type="checkbox" id="' . $id . '" name="workflow_step_actions[' . (int)$index . '][]" value="' . $value . '" data-workflow-action-choice'
            . (isset($selected[$value]) ? ' checked' : '') . '>'
            . '<span>' . $this->h($label) . '</span></label>';
      }
      return $html;
   }

   private function workflow_kind_labels($texts): array {
      return array(
         'input' => $texts->get_fd_message('kind_input'),
         'action' => $texts->get_fd_message('kind_action'),
         'check' => $texts->get_fd_message('kind_check'),
         'decision' => $texts->get_fd_message('kind_decision'),
      );
   }

   private function workflow_kind_options($selected, $texts) {
      $items = $this->workflow_kind_labels($texts);
      $html = '';
      foreach ($items as $value => $label) {
         $html .= '<option value="' . $this->h($value) . '"' . ((string)$selected === $value ? ' selected' : '') . '>' . $this->h($label) . '</option>';
      }
      return $html;
   }

   private function workflow_automation_options($selected, $texts) {
      $items = array(
         'manual' => $texts->get_fd_message('automation_manual'),
         'observe' => $texts->get_fd_message('automation_observe'),
      );
      $html = '';
      foreach ($items as $value => $label) {
         $html .= '<option value="' . $this->h($value) . '"' . ((string)$selected === $value ? ' selected' : '') . '>' . $this->h($label) . '</option>';
      }
      return $html;
   }

   private function workflow_mode_options($selected, $texts) {
      $items = array(
         'single' => $texts->get_fd_message('mode_single'),
         'multiple' => $texts->get_fd_message('mode_multiple'),
      );
      $html = '';
      foreach ($items as $value => $label) {
         $html .= '<option value="' . $this->h($value) . '"' . ((string)$selected === $value ? ' selected' : '') . '>' . $this->h($label) . '</option>';
      }
      return $html;
   }

   private function workflow_validation_labels($texts): array {
      return array(
         'exactly_one' => $texts->get_fd_message('validation_exactly_one'),
         'at_least_one' => $texts->get_fd_message('validation_at_least_one'),
         'not_empty' => $texts->get_fd_message('validation_not_empty'),
         'positive_integer' => $texts->get_fd_message('validation_positive_integer'),
         'confirmed' => $texts->get_fd_message('validation_confirmed'),
      );
   }

   private function workflow_validation_options($selected, $texts) {
      $items = $this->workflow_validation_labels($texts);
      $html = '';
      foreach ($items as $value => $label) {
         $html .= '<option value="' . $this->h($value) . '"' . ((string)$selected === $value ? ' selected' : '') . '>' . $this->h($label) . '</option>';
      }
      return $html;
   }

   private function workflow_default_question(string $label, $texts): string {
      $label = trim($label) !== '' ? trim($label) : $texts->get_fd_message('default_value_label');
      return $texts->format_fd_message('default_question', array('label' => $label));
   }

   private function workflow_default_missing_message(string $label, $texts): string {
      $label = trim($label) !== '' ? trim($label) : $texts->get_fd_message('default_value_label');
      return $texts->format_fd_message('default_missing_message', array('label' => $label));
   }

   private function workflow_resolver_label(string $action, $texts): string {
      $labels = array(
         'form' => $texts->get_fd_message('resolver_form'),
         'select' => $texts->get_fd_message('resolver_select'),
         'create' => $texts->get_fd_message('resolver_create'),
         'module' => $texts->get_fd_message('resolver_module'),
      );
      return $labels[$action] ?? $labels['form'];
   }

   private function workflow_depends_options(array $needs, $selected, $texts) {
      $html = '<option value="">' . $this->h($texts->get_fd_message('depends_always')) . '</option>';
      foreach ($needs as $need) {
         $key = (string)($need['key'] ?? '');
         if ($key === '') continue;
         $html .= '<option value="' . $this->h($key) . '"' . ((string)$selected === $key ? ' selected' : '') . '>' . $this->h($need['label'] ?? $key) . '</option>';
      }
      return $html;
   }

   private function workflow_options_text($options) {
      $lines = array();
      foreach ((array)$options as $option) {
         if (is_array($option)) {
            $value = trim((string)($option['value'] ?? ''));
            $label = trim((string)($option['label'] ?? $value));
            if ($value !== '' || $label !== '') {
               $lines[] = $value !== '' && $value !== $label ? $value . '=' . $label : $label;
            }
         } else {
            $text = trim((string)$option);
            if ($text !== '') $lines[] = $text;
         }
      }
      return implode("\n", $lines);
   }

   private function parse_workflow_options($text) {
      $options = array();
      foreach (preg_split('/\r\n|\r|\n|,/', (string)$text) as $line) {
         $line = trim($line);
         if ($line === '') continue;
         if (strpos($line, '=') !== false) {
            list($value, $label) = explode('=', $line, 2);
            $value = trim($value);
            $label = trim($label);
            if ($value !== '' || $label !== '') {
               $options[] = array('value' => $value !== '' ? $value : $label, 'label' => $label !== '' ? $label : $value);
            }
         } else {
            $options[] = $line;
         }
      }
      return $options;
   }

   private function workflow_definition_from_data(array $data): array {
      $engine = dbx()->get_include_obj('dbxWorkflowEngine', 'dbxWorkflow');
      $definition = $engine->normalize_definition($data['definition_json'] ?? $this->default_definition_text(), $data['workflow_key'] ?? '');
      if (trim((string)($data['workflow_key'] ?? '')) !== '') $definition['workflow_key'] = trim((string)$data['workflow_key']);
      if (trim((string)($data['title'] ?? '')) !== '') $definition['title'] = trim((string)$data['title']);
      if (trim((string)($data['result_label'] ?? '')) !== '') $definition['result'] = trim((string)$data['result_label']);
      if (array_key_exists('description', $data)) $definition['description'] = (string)$data['description'];
      return $definition;
   }

   private function workflow_builder_html(array $data, $texts) {
      $definition = $this->workflow_definition_from_data($data);
      $needs = array_values((array)($definition['needs'] ?? array()));
      $rowCount = max(14, count($needs) + 6);
      $rows = '';

      for ($i = 0; $i < $rowCount; $i++) {
         $need = $needs[$i] ?? array();
         $isEmpty = empty($need);
         $key = (string)($need['key'] ?? '');
         $label = (string)($need['label'] ?? '');
         $allowedActions = array('form', 'select', 'create', 'module');
         $actions = array_values(array_unique(array_intersect(array_map('strval', (array)($need['actions'] ?? array('form'))), $allowedActions)));
         if (!$actions) $actions = array('form');
         $action = (string)($need['preferred'] ?? '');
         if (!in_array($action, $actions, true)) $action = (string)$actions[0];
         $kind = (string)($need['kind'] ?? '');
         if (!in_array($kind, array('input', 'action', 'check', 'decision'), true)) {
            $kind = $action === 'module' ? 'action' : 'input';
         }
         $automation = (string)($need['automation'] ?? 'manual');
         if (!in_array($automation, array('manual', 'observe'), true)) $automation = 'manual';
         $mode = (string)($need['mode'] ?? 'single');
         $required = array_key_exists('required', $need) ? (int)((bool)$need['required']) : 1;
         $rawQuestion = trim((string)($need['question'] ?? ''));
         $question = $rawQuestion !== '' ? $rawQuestion : $this->workflow_default_question($label, $texts);
         $validation = (string)($need['validation'] ?? ($mode === 'multiple' ? 'at_least_one' : 'exactly_one'));
         if (!in_array($validation, array('exactly_one', 'at_least_one', 'not_empty', 'positive_integer', 'confirmed'), true)) {
            $validation = $mode === 'multiple' ? 'at_least_one' : 'exactly_one';
         }
         $rawMissingMessage = trim((string)($need['missing_message'] ?? ''));
         $missingMessage = $rawMissingMessage !== '' ? $rawMissingMessage : $this->workflow_default_missing_message($label, $texts);
         $resolver = is_array($need['resolver'] ?? null) ? $need['resolver'] : array();
         $resolverLabel = trim((string)($resolver['label'] ?? ''));
         if ($resolverLabel === '') $resolverLabel = $this->workflow_resolver_label($action, $texts);
         $hint = (string)($need['hint'] ?? '');
         $event = (string)($need['event'] ?? ($need['result_event'] ?? ''));
         $dependsOn = (string)($need['depends_on'] ?? '');
         $dependsValue = (string)($need['depends_value'] ?? '');
         $optionsText = $this->workflow_options_text($need['options'] ?? array());
         $sourceJson = '';
         if (!empty($need['source']) && is_array($need['source'])) {
            $sourceJson = json_encode($need['source'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
         }
         $bindJson = '';
         if (!empty($need['bind']) && is_array($need['bind'])) {
            $bindJson = json_encode($need['bind'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
         }
         $moduleLinksJson = '';
         if (!empty($need['module_links']) && is_array($need['module_links'])) {
            $moduleLinksJson = json_encode($need['module_links'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
         }
         $completeLabel = (string)($need['complete_label'] ?? '');
         $originalJson = $isEmpty ? '' : (json_encode($need, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

         $rowClass = $isEmpty ? ' dbx-workflow-step-row-empty' : '';
         $rowStyle = $isEmpty ? ' style="display:none;"' : '';
         $kindIcon = array('input' => 'bi-input-cursor-text', 'action' => 'bi-lightning-charge', 'check' => 'bi-shield-check', 'decision' => 'bi-signpost-split')[$kind] ?? 'bi-input-cursor-text';
         $kindLabels = $this->workflow_kind_labels($texts);
         $kindLabel = $kindLabels[$kind] ?? $kindLabels['input'];
         $rows .= '<article class="dbx-workflow-step-row' . $rowClass . '" data-workflow-step-row data-step-kind="' . $this->h($kind) . '" data-question-auto="' . ($rawQuestion === '' ? '1' : '0') . '" data-missing-auto="' . ($rawMissingMessage === '' ? '1' : '0') . '" draggable="true"' . $rowStyle . '>'
            . '<input type="hidden" name="workflow_step_present[' . $i . ']" value="1">'
            . '<input type="hidden" name="workflow_step_source[' . $i . ']" value="' . $this->h($sourceJson) . '">'
            . '<input type="hidden" name="workflow_step_bind[' . $i . ']" value="' . $this->h($bindJson) . '">'
            . '<input type="hidden" name="workflow_step_module_links[' . $i . ']" value="' . $this->h($moduleLinksJson) . '">'
            . '<input type="hidden" name="workflow_step_original[' . $i . ']" value="' . $this->h($originalJson) . '">'
            . '<input type="hidden" name="workflow_step_original_index[' . $i . ']" value="' . ($isEmpty ? '' : $i) . '">'
            . '<input type="hidden" name="workflow_step_action_contract[' . $i . ']" value="1">'
            . '<input class="visually-hidden" type="checkbox" name="workflow_step_active[' . $i . ']" value="1"' . (!$isEmpty ? ' checked' : '') . '>'
            . '<span class="dbx-workflow-node-port is-in" aria-hidden="true"></span>'
            . '<details class="dbx-workflow-step-details"' . (!$isEmpty ? ' open' : '') . '>'
            . '<summary class="dbx-workflow-step-summary">'
            . '<button class="dbx-workflow-drag-handle" type="button" draggable="true" data-workflow-drag-handle data-dbx-tooltip="' . $this->h($texts->get_fd_message('step_move')) . '" aria-label="' . $this->h($texts->get_fd_message('step_move')) . '"><i class="bi bi-grip-vertical"></i></button>'
            . '<span class="dbx-workflow-step-num" data-workflow-step-number>' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) . '</span>'
            . '<span class="dbx-workflow-step-kind"><i class="bi ' . $this->h($kindIcon) . '" data-workflow-kind-icon></i><span data-workflow-kind-label>' . $this->h($kindLabel) . '</span></span>'
            . '<span class="dbx-workflow-step-caption"><strong data-workflow-step-title>' . $this->h($label !== '' ? $label : $texts->get_fd_message('new_task')) . '</strong><small data-workflow-step-result>' . $this->h($event !== '' ? $event : $texts->get_fd_message('no_result')) . '</small></span>'
            . '<span class="dbx-workflow-auto-badge" data-workflow-auto-badge' . ($automation === 'observe' ? '' : ' hidden') . '><i class="bi bi-stars"></i> ' . $this->h($texts->get_fd_message('automation_badge')) . '</span>'
            . '<button class="dbx-workflow-remove-step" type="button" data-workflow-remove-step data-dbx-tooltip="' . $this->h($texts->get_fd_message('step_remove')) . '" aria-label="' . $this->h($texts->get_fd_message('step_remove')) . '"><i class="bi bi-trash3"></i></button>'
            . '</summary>'
            . '<div class="dbx-workflow-step-main">'
            . '<div class="row g-2 align-items-end">'
            . '<div class="col-xl-2 col-md-4"><label class="form-label">' . $this->h($texts->get_fd_message('field_building_block')) . '</label><select class="form-select form-select-sm" name="workflow_step_kind[' . $i . ']">' . $this->workflow_kind_options($kind, $texts) . '</select></div>'
            . '<div class="col-xl-4 col-md-8"><label class="form-label">' . $this->h($texts->get_fd_message('field_label')) . '</label><input class="form-control form-control-sm" name="workflow_step_label[' . $i . ']" value="' . $this->h($label) . '" placeholder="' . $this->h($texts->get_fd_message('placeholder_label')) . '"></div>'
            . '<div class="col-xl-3 col-md-6"><label class="form-label">' . $this->h($texts->get_fd_message('field_key')) . '</label><input class="form-control form-control-sm font-monospace" name="workflow_step_key[' . $i . ']" value="' . $this->h($key) . '" placeholder="' . $this->h($texts->get_fd_message('placeholder_key')) . '"></div>'
            . '<div class="col-xl-3 col-md-6"><label class="form-label">' . $this->h($texts->get_fd_message('field_result')) . '</label><input class="form-control form-control-sm" name="workflow_step_event[' . $i . ']" value="' . $this->h($event) . '" placeholder="' . $this->h($texts->get_fd_message('placeholder_result')) . '"></div>'
            . '<div class="col-xl-5 col-md-12"><label class="form-label">' . $this->h($texts->get_fd_message('field_allowed_actions')) . '</label><div class="dbx-workflow-action-options">' . $this->workflow_action_choices($i, $actions, $texts) . '</div></div>'
            . '<div class="col-xl-3 col-md-6"><label class="form-label">' . $this->h($texts->get_fd_message('field_default_action')) . '</label><select class="form-select form-select-sm" name="workflow_step_action[' . $i . ']">' . $this->workflow_action_options($action, $texts) . '</select></div>'
            . '<div class="col-xl-4 col-md-6"><label class="form-label">' . $this->h($texts->get_fd_message('field_automation')) . '</label><select class="form-select form-select-sm" name="workflow_step_automation[' . $i . ']">' . $this->workflow_automation_options($automation, $texts) . '</select></div>'
            . '<div class="col-xl-3 col-md-4"><label class="form-label">' . $this->h($texts->get_fd_message('field_values')) . '</label><select class="form-select form-select-sm" name="workflow_step_mode[' . $i . ']">' . $this->workflow_mode_options($mode, $texts) . '</select></div>'
            . '<div class="col-xl-3 col-md-4"><label class="form-label">' . $this->h($texts->get_fd_message('field_required')) . '</label><select class="form-select form-select-sm" name="workflow_step_required[' . $i . ']"><option value="1"' . ($required ? ' selected' : '') . '>' . $this->h($texts->get_fd_message('required_yes')) . '</option><option value="0"' . (!$required ? ' selected' : '') . '>' . $this->h($texts->get_fd_message('required_no')) . '</option></select></div>'
            . '<div class="col-xl-6 col-md-8" data-workflow-module-setting' . (!in_array('module', $actions, true) ? ' hidden' : '') . '><label class="form-label">' . $this->h($texts->get_fd_message('field_module_confirmation')) . '</label><input class="form-control form-control-sm" name="workflow_step_complete_label[' . $i . ']" value="' . $this->h($completeLabel) . '" placeholder="' . $this->h($texts->get_fd_message('placeholder_module_confirmation')) . '"></div>'
            . '<div class="col-12"><span class="dbx-workflow-safety-note"><i class="bi bi-shield-lock"></i> ' . $this->h($texts->get_fd_message('external_actions_confirmed')) . '</span></div>'
            . '<div class="col-12"><section class="dbx-workflow-step-contract">'
            . '<header><i class="bi bi-clipboard2-check"></i><div><strong>' . $this->h($texts->get_fd_message('automatic_check')) . '</strong><small>' . $this->h($texts->get_fd_message('automatic_check_hint')) . '</small></div></header>'
            . '<div class="row g-2">'
            . '<div class="col-xl-5 col-md-12"><label class="form-label">' . $this->h($texts->get_fd_message('field_question')) . '</label><input class="form-control form-control-sm" name="workflow_step_question[' . $i . ']" value="' . $this->h($question) . '" placeholder="' . $this->h($texts->get_fd_message('placeholder_question')) . '"></div>'
            . '<div class="col-xl-3 col-md-6"><label class="form-label">' . $this->h($texts->get_fd_message('field_validation')) . '</label><select class="form-select form-select-sm" name="workflow_step_validation[' . $i . ']">' . $this->workflow_validation_options($validation, $texts) . '</select></div>'
            . '<div class="col-xl-4 col-md-6"><label class="form-label">' . $this->h($texts->get_fd_message('field_resolver')) . '</label><input class="form-control form-control-sm" name="workflow_step_resolver_label[' . $i . ']" value="' . $this->h($resolverLabel) . '" placeholder="' . $this->h($texts->get_fd_message('placeholder_resolver')) . '"></div>'
            . '<div class="col-12"><label class="form-label">' . $this->h($texts->get_fd_message('field_missing_message')) . '</label><input class="form-control form-control-sm" name="workflow_step_missing_message[' . $i . ']" value="' . $this->h($missingMessage) . '" placeholder="' . $this->h($texts->get_fd_message('placeholder_missing')) . '"></div>'
            . '</div></section></div>'
            . '<div class="col-xl-4 col-md-6"><label class="form-label">' . $this->h($texts->get_fd_message('field_depends')) . '</label><select class="form-select form-select-sm" name="workflow_step_depends_on[' . $i . ']">' . $this->workflow_depends_options($needs, $dependsOn, $texts) . '</select></div>'
            . '<div class="col-xl-4 col-md-6"><label class="form-label">' . $this->h($texts->get_fd_message('field_depends_value')) . '</label><input class="form-control form-control-sm" name="workflow_step_depends_value[' . $i . ']" value="' . $this->h($dependsValue) . '" placeholder="' . $this->h($texts->get_fd_message('placeholder_depends_value')) . '"></div>'
            . '<div class="col-xl-4 col-md-12"><label class="form-label">' . $this->h($texts->get_fd_message('field_options')) . '</label><textarea class="form-control form-control-sm" name="workflow_step_options[' . $i . ']" rows="3" placeholder="' . $this->h($texts->get_fd_message('placeholder_options')) . '">' . $this->h($optionsText) . '</textarea></div>'
            . '<div class="col-12"><label class="form-label">' . $this->h($texts->get_fd_message('field_hint')) . '</label><textarea class="form-control form-control-sm" name="workflow_step_hint[' . $i . ']" rows="2" placeholder="' . $this->h($texts->get_fd_message('placeholder_hint')) . '">' . $this->h($hint) . '</textarea></div>'
            . '</div></div></details>'
            . '<span class="dbx-workflow-node-port is-out" aria-hidden="true"></span>'
            . '</article>';
      }

      $checkRows = '';
      foreach ($needs as $need) {
         $label = trim((string)($need['label'] ?? $need['key'] ?? $texts->get_fd_message('default_value_label')));
         $question = trim((string)($need['question'] ?? '')) ?: $this->workflow_default_question($label, $texts);
         $required = array_key_exists('required', $need) ? (bool)$need['required'] : true;
         $validation = (string)($need['validation'] ?? (($need['mode'] ?? 'single') === 'multiple' ? 'at_least_one' : 'exactly_one'));
         $action = (string)($need['preferred'] ?? (((array)($need['actions'] ?? array('form')))[0] ?? 'form'));
         $resolver = is_array($need['resolver'] ?? null) ? $need['resolver'] : array();
         $resolverLabel = trim((string)($resolver['label'] ?? '')) ?: $this->workflow_resolver_label($action, $texts);
         $validationLabels = $this->workflow_validation_labels($texts);
         $checkRows .= $this->tpl()->get_tpl('dbxWorkflow_admin|workflow-check-preview-item', array(
            'question' => $this->h($question),
            'label' => $this->h($label),
            'validation' => $this->h($validationLabels[$validation] ?? $validation),
            'required' => $required ? $texts->get_fd_message('preview_required') : $texts->get_fd_message('preview_optional'),
            'resolver_label' => $this->h($resolverLabel),
         ));
      }

      $finishLabel = (string)($definition['finish']['label'] ?? $texts->format_fd_message(
         'finish_default',
         array('result' => ($data['result_label'] ?? 'Workflow'))
      ));
      $bindRef = (string)($definition['bind_ref'] ?? '');
      $technicalPreview = json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      $designerMessageKeys = array(
         'kind_input', 'kind_action', 'kind_check', 'kind_decision',
         'validation_exactly_one', 'validation_at_least_one', 'validation_not_empty',
         'validation_positive_integer', 'validation_confirmed',
         'resolver_form', 'resolver_select', 'resolver_create', 'resolver_module',
         'default_question', 'default_missing_message', 'new_task', 'no_result',
         'preview_required', 'preview_optional',
         'js_new_input', 'js_new_action', 'js_new_check', 'js_new_decision',
         'js_input_complete', 'js_action_complete', 'js_check_complete', 'js_decision_complete',
         'js_requirement_singular', 'js_requirement_plural', 'js_direct_after_previous',
         'js_error_label_missing', 'js_error_key_missing', 'js_error_key_duplicate',
         'js_error_question_missing', 'js_error_decision_options', 'js_warning_automation',
         'js_warning_select_source', 'js_warning_module_link',
         'js_error_dependency_missing', 'js_error_dependency_order',
         'validation_minimum_step', 'js_status_invalid', 'js_status_warning',
         'js_status_valid', 'js_status_valid_detail', 'js_no_free_step',
         'js_no_free_step_detail', 'js_default_event', 'js_yes', 'js_no',
         'js_payment_label', 'js_payment_event', 'js_payment_question',
         'js_payment_missing', 'js_payment_resolver', 'js_payment_hint',
         'js_payment_options'
      );
      $designerMessages = array();
      foreach ($designerMessageKeys as $messageKey) {
         $designerMessages[$messageKey] = $texts->get_fd_message($messageKey);
      }

      return $this->tpl()->get_tpl('dbxWorkflow_admin|workflow-builder', array(
         'need_count' => count($needs),
         'check_rows' => $checkRows,
         'step_rows' => $rows,
         'finish_label' => $this->h($finishLabel),
         'bind_ref' => $this->h($bindRef),
         'technical_preview' => $this->h($technicalPreview),
         'designer_messages' => $this->h(json_encode(
            $designerMessages,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
         )),
      ));
   }

   private function workflow_json_array($value): array {
      if (is_array($value)) return $value;
      $value = trim((string)$value);
      if ($value === '' || strlen($value) > 200000) return array();
      $decoded = json_decode($value, true);
      return is_array($decoded) ? $decoded : array();
   }

   private function workflow_actions_from_post($index, array $original, string $preferred): array {
      $allowed = array('form', 'select', 'create', 'module');
      $hasActionContract = isset($_POST['workflow_step_action_contract'])
         && array_key_exists($index, (array)$_POST['workflow_step_action_contract']);
      $posted = $_POST['workflow_step_actions'][$index] ?? array();
      if (!is_array($posted)) $posted = array($posted);
      $selected = array_values(array_unique(array_intersect(array_map('strval', $posted), $allowed)));

      // Offene Formulare aus einer älteren Version senden noch keine
      // Mehrfachauswahl. In diesem Fall bleibt die bestehende Aktionsliste
      // vollständig erhalten.
      if (!$hasActionContract) {
         $selected = array_values(array_unique(array_intersect(array_map('strval', (array)($original['actions'] ?? array())), $allowed)));
      }
      if (!$selected) $selected = array(in_array($preferred, $allowed, true) ? $preferred : 'form');

      // Bestehende Reihenfolge erhalten; neu aktivierte Aktionen folgen in der
      // stabilen UI-Reihenfolge. Damit verändert reines Öffnen und Speichern
      // auch die Priorisierung alter Definitionen nicht.
      $selectedMap = array_fill_keys($selected, true);
      $actions = array();
      foreach ((array)($original['actions'] ?? array()) as $action) {
         $action = (string)$action;
         if (isset($selectedMap[$action]) && !in_array($action, $actions, true)) $actions[] = $action;
      }
      foreach ($allowed as $action) {
         if (isset($selectedMap[$action]) && !in_array($action, $actions, true)) $actions[] = $action;
      }
      return $actions;
   }

   private function workflow_definition_from_post($oForm, array $baseDefinition = array()) {
      $steps = array();
      $checkKeyRenames = array();
      $baseNeeds = array_values((array)($baseDefinition['needs'] ?? array()));
      $present = $_POST['workflow_step_present'] ?? array();
      foreach ((array)$present as $idx => $unused) {
         $active = isset($_POST['workflow_step_active'][$idx]);
         $label = trim((string)($_POST['workflow_step_label'][$idx] ?? ''));
         $key = $this->normalize_key($_POST['workflow_step_key'][$idx] ?? $label);
         if (!$active && $label === '' && $key === '') continue;
         if (!$active) continue;
         if ($key === '') $key = 'schritt_' . ((int)$idx + 1);
         if ($label === '') $label = $key;

         $action = (string)($_POST['workflow_step_action'][$idx] ?? 'form');
         if (!in_array($action, array('form', 'select', 'create', 'module'), true)) $action = 'form';
         $kind = (string)($_POST['workflow_step_kind'][$idx] ?? 'input');
         if (!in_array($kind, array('input', 'action', 'check', 'decision'), true)) $kind = 'input';
         $automation = (string)($_POST['workflow_step_automation'][$idx] ?? 'manual');
         if (!in_array($automation, array('manual', 'observe'), true)) $automation = 'manual';
         $mode = (string)($_POST['workflow_step_mode'][$idx] ?? 'single') === 'multiple' ? 'multiple' : 'single';

         $need = $this->workflow_json_array($_POST['workflow_step_original'][$idx] ?? '');
         $hasActionContract = isset($_POST['workflow_step_action_contract'])
            && array_key_exists($idx, (array)$_POST['workflow_step_action_contract']);
         $originalIndexRaw = trim((string)($_POST['workflow_step_original_index'][$idx] ?? ''));
         $originalIndex = ctype_digit($originalIndexRaw) ? (int)$originalIndexRaw : null;
         if (!$need && $originalIndex !== null && isset($baseNeeds[$originalIndex]) && is_array($baseNeeds[$originalIndex])) {
            $need = $baseNeeds[$originalIndex];
         } elseif (!$need && !$hasActionContract && isset($baseNeeds[(int)$idx]) && is_array($baseNeeds[(int)$idx])) {
            $need = $baseNeeds[(int)$idx];
         }
         $originalKey = $this->normalize_key($need['key'] ?? '');
         if ($originalKey !== '' && $originalKey !== $key) $checkKeyRenames[$originalKey] = $key;
         $actions = $this->workflow_actions_from_post($idx, $need, $action);
         if (!in_array($action, $actions, true)) $action = (string)$actions[0];
         $resolver = is_array($need['resolver'] ?? null) ? $need['resolver'] : array();
         $resolver['type'] = $action;
         $resolver['label'] = trim((string)($_POST['workflow_step_resolver_label'][$idx] ?? '')) ?: $this->workflow_resolver_label($action, $oForm);

         // Der vorhandene Need ist die Basis. Nur Felder, die der Designer
         // tatsächlich verwaltet, werden überschrieben. Erweiterungen von
         // Fachmodulen und zukünftige Schemafelder bleiben dadurch erhalten.
         $need['key'] = $key;
         $need['label'] = $label;
         $need['kind'] = $kind;
         $need['automation'] = $automation;
         $need['mode'] = $mode;
         $need['required'] = ((string)($_POST['workflow_step_required'][$idx] ?? '1') === '1');
         $need['actions'] = $actions;
         $need['preferred'] = $action;
         $need['question'] = trim((string)($_POST['workflow_step_question'][$idx] ?? '')) ?: $this->workflow_default_question($label, $oForm);
         $need['validation'] = (string)($_POST['workflow_step_validation'][$idx] ?? ($mode === 'multiple' ? 'at_least_one' : 'exactly_one'));
         $need['missing_message'] = trim((string)($_POST['workflow_step_missing_message'][$idx] ?? '')) ?: $this->workflow_default_missing_message($label, $oForm);
         $need['resolver'] = $resolver;
         $need['hint'] = trim((string)($_POST['workflow_step_hint'][$idx] ?? ''));

         $event = trim((string)($_POST['workflow_step_event'][$idx] ?? ''));
         if ($event !== '') $need['event'] = $event;
         else unset($need['event'], $need['result_event']);

         $dependsOn = $this->normalize_key($_POST['workflow_step_depends_on'][$idx] ?? '');
         if ($dependsOn !== '') {
            $need['depends_on'] = $dependsOn;
            $dependsValue = trim((string)($_POST['workflow_step_depends_value'][$idx] ?? ''));
            if ($dependsValue !== '') $need['depends_value'] = $dependsValue;
            else unset($need['depends_value']);
         }
         else unset($need['depends_on'], $need['depends_value']);

         $options = $this->parse_workflow_options($_POST['workflow_step_options'][$idx] ?? '');
         if ($options) $need['options'] = $options;
         else unset($need['options']);

         if (isset($_POST['workflow_step_complete_label']) && array_key_exists($idx, (array)$_POST['workflow_step_complete_label'])) {
            $completeLabel = trim((string)$_POST['workflow_step_complete_label'][$idx]);
            if ($completeLabel !== '') $need['complete_label'] = $completeLabel;
            else unset($need['complete_label']);
         }

         $sourceJson = trim((string)($_POST['workflow_step_source'][$idx] ?? ''));
         $source = $sourceJson !== '' ? json_decode($sourceJson, true) : null;
         if (is_array($source)) $need['source'] = $source;
         elseif ($sourceJson === '') unset($need['source']);

         $bindJson = trim((string)($_POST['workflow_step_bind'][$idx] ?? ''));
         $bind = $bindJson !== '' ? json_decode($bindJson, true) : null;
         if (is_array($bind)) $need['bind'] = $bind;
         elseif ($bindJson === '') unset($need['bind']);

         $moduleLinksJson = trim((string)($_POST['workflow_step_module_links'][$idx] ?? ''));
         $moduleLinks = $moduleLinksJson !== '' ? json_decode($moduleLinksJson, true) : null;
         if (is_array($moduleLinks)) $need['module_links'] = $moduleLinks;
         elseif ($moduleLinksJson === '') unset($need['module_links']);

         $steps[] = $need;
      }

      $definition = $baseDefinition;
      $definition['workflow_key'] = $oForm->get_post('workflow_key', '', 'parameter|min=2|max=80');
      $definition['title'] = $oForm->get_post('title', '', '*|min=2|max=160');
      $definition['result'] = $oForm->get_post('result_label', '', '*|min=2|max=160');
      $definition['description'] = $oForm->get_post_data('description', '', '*');
      $definition['needs'] = $steps;
      if ($checkKeyRenames && !empty($definition['checks']) && is_array($definition['checks'])) {
         foreach ($definition['checks'] as &$check) {
            if (!is_array($check)) continue;
            $checkKey = $this->normalize_key($check['key'] ?? '');
            if (isset($checkKeyRenames[$checkKey])) $check['key'] = $checkKeyRenames[$checkKey];
         }
         unset($check);
      }
      $finish = is_array($definition['finish'] ?? null) ? $definition['finish'] : array();
      $finish['label'] = trim((string)($_POST['workflow_finish_label'] ?? '')) ?: $oForm->format_fd_message(
         'finish_default',
         array('result' => $definition['result'] ?: 'Workflow')
      );
      $definition['finish'] = $finish;

      $bindRef = trim((string)($_POST['workflow_bind_ref'] ?? ''));
      if ($bindRef !== '') $definition['bind_ref'] = $bindRef;
      else unset($definition['bind_ref']);

      return $definition;
   }

   private function validate_workflow_definition(array $definition, $texts): array {
      $errors = array();
      $needs = array_values((array)($definition['needs'] ?? array()));
      $positions = array();

      foreach ($needs as $index => $need) {
         $key = $this->normalize_key($need['key'] ?? '');
         $label = trim((string)($need['label'] ?? $key));
         if ($key === '') {
            $errors[] = $texts->format_fd_message(
               'validation_step_key_missing',
               array('step' => $index + 1)
            );
            continue;
         }
         if (array_key_exists($key, $positions)) {
            $errors[] = $texts->format_fd_message(
               'validation_step_key_duplicate',
               array('key' => $key)
            );
         } else {
            $positions[$key] = $index;
         }
         if ((string)($need['kind'] ?? '') === 'decision' && count((array)($need['options'] ?? array())) < 2) {
            $errors[] = $texts->format_fd_message(
               'validation_decision_options',
               array('label' => $label)
            );
         }
      }

      foreach ($needs as $index => $need) {
         $key = $this->normalize_key($need['key'] ?? '');
         $dependsOn = $this->normalize_key($need['depends_on'] ?? '');
         if ($dependsOn === '') continue;
         if (!array_key_exists($dependsOn, $positions)) {
            $errors[] = $texts->format_fd_message(
               'validation_dependency_missing',
               array('dependency' => $dependsOn, 'key' => $key)
            );
         } elseif ($positions[$dependsOn] >= $index) {
            $errors[] = $texts->format_fd_message(
               'validation_dependency_order',
               array('dependency' => $dependsOn, 'key' => $key)
            );
         }
      }

      if (!$needs) {
         $errors[] = $texts->get_fd_message('validation_minimum_step');
      }

      return array_values(array_unique($errors));
   }

   private function edit() {
      $rid = dbx()->get_modul_var('rid', 'new', 'parameter');
      $isNew = ($rid === 'new' || (int)$rid <= 0);

      $oForm = dbx()->get_system_obj('dbxForm');
      $oForm->init('workflow-definition', 'workflow-definition-form');
      $oForm->_dd = $this->ddDefinition;
      $oForm->_fd = 'dbxWorkflow|workflow-definition';
      $oForm->load_fd_messages();

      $data = $this->load_row($rid);
      if (!$data) {
         return $this->frame(
            $this->alert('warning', $oForm->get_fd_message('definition_not_found')),
            $oForm
         );
      }

      $oForm->add_rep('bar_title', $oForm->get_fd_message($isNew ? 'form_new_title' : 'form_edit_title'));
      $oForm->add_rep('bar_subtitle', $oForm->get_fd_message('form_subtitle'));
      $oForm->add_obj('bar_actions', 'obj-value',
         '<a class="btn btn-outline-secondary btn-sm" href="?dbx_modul=dbxWorkflow_admin"><i class="bi bi-list-ul"></i> ' . $this->h($oForm->get_fd_message('list_label')) . '</a>'
         . '<button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-save"></i> ' . $this->h($oForm->get_fd_message('save_label')) . '</button>'
      );
      $oForm->_data = $data;
      $oForm->_action = '?dbx_modul=dbxWorkflow_admin&dbx_run1=edit&rid=' . ($isNew ? 'new' : (int)$rid);
      $oForm->set_activ_id($isNew ? 0 : (int)$rid);
      $oForm->_msg_info = $oForm->get_fd_message($isNew ? 'form_new_info' : 'form_edit_info');

      $oForm->add_fld('workflow_key');
      $oForm->add_fld('title');
      $oForm->add_fld('result_label');
      $oForm->add_fld('description');
      $oForm->add_fld('active');
      $oForm->add_obj('workflow_builder', 'obj-value', $this->workflow_builder_html($data, $oForm));

      if ($oForm->submit()) {
         if (!$oForm->errors()) {
            $engine = dbx()->get_include_obj('dbxWorkflowEngine', 'dbxWorkflow');
            $baseDefinition = $this->workflow_definition_from_data($data);
            $definition = $engine->normalize_definition($this->workflow_definition_from_post($oForm, $baseDefinition), $oForm->get_post('workflow_key', '', 'parameter|max=80'));
            $definitionErrors = $this->validate_workflow_definition($definition, $oForm);
            if ($definitionErrors) {
               $errorText = implode(' ', $definitionErrors);
               $oForm->add_fld_error('title', $errorText);
               $oForm->_msg_error = $errorText;
               $oForm->add_obj('workflow_builder', 'obj-value', $this->workflow_builder_html(array_merge($data, array(
                  'workflow_key' => $oForm->get_post('workflow_key', '', 'parameter|max=80'),
                  'title' => $oForm->get_post('title', '', '*'),
                  'result_label' => $oForm->get_post('result_label', '', '*'),
                  'description' => $oForm->get_post_data('description', '', '*'),
                  'definition_json' => json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
               )), $oForm));
               return $oForm->run();
            }
            $definitionText = json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $values = array(
               'workflow_key'    => $oForm->get_post('workflow_key', '', 'parameter|min=2|max=80'),
               'title'           => $oForm->get_post('title', '', '*|min=2|max=160'),
               'result_label'    => $oForm->get_post('result_label', '', '*|min=2|max=160'),
               'description'     => $oForm->get_post_data('description', '', '*'),
               'definition_json' => $definitionText,
               'active'          => $oForm->get_post('active', 0, 'int')
            );

            $db = $this->db();
            $key = $db->escape($values['workflow_key'], $db->get_dd_server($this->ddDefinition));
            $where = "workflow_key='" . $key . "'";
            if (!$isNew) $where .= ' AND id <> ' . (int)$rid;

            if ($db->count($this->ddDefinition, $where) > 0) {
               $oForm->add_fld_error('workflow_key', $oForm->get_fd_message('duplicate_workflow_key'));
               $oForm->_msg_error = $oForm->get_fd_message('duplicate_workflow_key');
            } else {
               $ok = $db->save($this->ddDefinition, $values, $isNew ? 0 : (int)$rid, 0, 1, 1, 1);
               $oForm->_msg_success = $oForm->get_fd_message($ok ? 'save_success' : 'save_error');
               if ($ok) {
                  $oForm->_data = array_merge($oForm->_data, $values);
                  $oForm->add_obj('workflow_builder', 'obj-value', $this->workflow_builder_html($oForm->_data, $oForm));
               }
            }
         } else {
            $oForm->_msg_error = $oForm->get_fd_message('validation_error');
         }
      }

      return $oForm->run();
   }

   private function decorate_rows($rows) {
      $out = array();
      foreach ((array)$rows as $row) {
         $row['action'] = $this->tpl()->get_tpl('dbxWorkflow_admin|workflow-admin-row-action', array(
            'id' => (int)($row['id'] ?? 0),
            'workflow_key' => rawurlencode((string)($row['workflow_key'] ?? '')),
            'dbx_token' => rawurlencode(dbx()->action_token('dbxWorkflow.start'))
         ));
         $out[] = $row;
      }
      return $out;
   }

   private function report_search_where(string $rwhere): array|string {
      $rwhere = trim($rwhere);
      if ($rwhere === '') {
         return '';
      }

      return array(
         'search' => array(
            'value' => $rwhere,
            'like' => array('workflow_key', 'title', 'result_label', 'description', 'status', 'message', 'current_need'),
            'mode' => 'contains',
         ),
      );
   }

   private function list_definitions() {
      $db = $this->db();

      $oReport = dbx()->get_system_obj('dbxReport');
      $oReport->init('workflow-definitions');
      $oReport->_dd = $this->ddDefinition;
      $oReport->_fd = 'dbxWorkflow_admin|rpt-workflow-definitions-selection';
      $oReport->load_fd_messages();
      $oReport->_action = '?dbx_modul=dbxWorkflow_admin';
      $oReport->_pages = true;
      $oReport->_create_row_select = false;
      $oReport->_create_row_edit = false;
      $oReport->_create_row_delete = false;
      $oReport->_but_pagination = 5;
      $oReport->set_form_help_enabled(false);
      $oReport->_msg_info = $oReport->get_fd_message('report_info');
      $oReport->add_module_bar(
         $oReport->get_fd_message('bar_title'),
         'bi-diagram-3',
         $oReport->get_fd_message('bar_subtitle'),
         true
      );
      $oReport->add_rep(
         'bar_subtitle',
         $oReport->get_fd_message('bar_subtitle')
      );
      $oReport->add_rep(
         'bar_actions',
         '<a class="btn btn-outline-secondary btn-sm" href="?dbx_modul=dbxWorkflow_admin&dbx_run1=binds"><i class="bi bi-plug"></i> '
            . $this->h($oReport->get_fd_message('module_bindings'))
            . '</a>'
            . '<a class="btn btn-primary btn-sm" href="?dbx_modul=dbxWorkflow_admin&dbx_run1=edit_bind&rid=new"><i class="bi bi-plus-circle"></i> '
            . $this->h($oReport->get_fd_message('new_binding'))
            . '</a>'
            . '<a class="btn btn-primary btn-sm" href="?dbx_modul=dbxWorkflow_admin&dbx_run1=edit&rid=new"><i class="bi bi-plus-circle"></i> '
            . $this->h($oReport->get_fd_message('new_workflow'))
            . '</a>'
      );
      $oReport->create_selection_fields('dbxWorkflow_admin|rpt-workflow-definitions-selection');

      if ($oReport->submit()) {
         if (!$oReport->errors()) {
            $oReport->_msg_info = $oReport->get_fd_message(
               'filter_applied'
            );
         } else {
            $oReport->_msg_error = $oReport->get_fd_message(
               'validation_error'
            );
         }
      }

      $rwhere = $oReport->get_fld_val('dbx_rwhere', '', 'sqlsearch|max=64');
      $rrows = $oReport->get_fld_val('dbx_rrows', 30, 'int');
      $rpos = $oReport->get_fld_val('dbx_rpos', 0, 'int');
      $rsort = $oReport->get_fld_val('dbx_rsort', 'title', 'parameter');
      $rdesc = $oReport->get_fld_val('dbx_rdesc', 'ASC', 'parameter');

      $where = $this->report_search_where($rwhere);
      $cols = array('id', 'workflow_key', 'title', 'result_label', 'active', 'update_date');

      $oReport->_rflds = array(
         'id' => 'ID',
         'workflow_key' => $oReport->get_fd_message('column_key'),
         'title' => $oReport->get_fd_message('column_title'),
         'result_label' => $oReport->get_fd_message('column_goal'),
         'active' => $oReport->get_fd_message('column_active'),
         'update_date' => $oReport->get_fd_message('column_updated'),
         'action' => $oReport->get_fd_message('column_action')
      );
      $oReport->_rpt_format = array(
         'update_date' => 'php-datetime-usr',
         'action' => 'html',
      );
      $oReport->_rrows = $rrows;
      $oReport->_rpos = $rpos;
      $oReport->_count_all = $db->count($this->ddDefinition, '');
      $oReport->_rcount = $db->count($this->ddDefinition, $where);
      $oReport->_rdata = $this->decorate_rows($db->select($this->ddDefinition, $where, $cols, $rsort, $rdesc, '', $rrows, $rpos));

      return $this->frame($oReport->run(), $oReport);
   }

   private function list_instances() {
      $db = $this->db();
      $oReport = dbx()->get_system_obj('dbxReport');
      $oReport->init('workflow-instances');
      $oReport->_dd = $this->ddInstance;
      $oReport->_fd = 'dbxWorkflow_admin|rpt-workflow-instances-selection';
      $oReport->load_fd_messages();
      $oReport->_action = '?dbx_modul=dbxWorkflow_admin&dbx_run1=instances';
      $oReport->_pages = true;
      $oReport->_create_row_select = false;
      $oReport->_create_row_edit = false;
      $oReport->_create_row_delete = false;
      $oReport->_but_pagination = 5;
      $oReport->set_form_help_enabled(false);
      $oReport->_msg_info = $oReport->get_fd_message('report_info');
      $oReport->create_selection_fields('dbxWorkflow_admin|rpt-workflow-instances-selection');

      if ($oReport->submit()) {
         if (!$oReport->errors()) {
            $oReport->_msg_info = $oReport->get_fd_message(
               'filter_applied'
            );
         } else {
            $oReport->_msg_error = $oReport->get_fd_message(
               'validation_error'
            );
         }
      }

      $rwhere = $oReport->get_fld_val('dbx_rwhere', '', 'sqlsearch|max=64');
      $rrows = $oReport->get_fld_val('dbx_rrows', 50, 'int');
      $rpos = $oReport->get_fld_val('dbx_rpos', 0, 'int');
      $rsort = $oReport->get_fld_val('dbx_rsort', 'create_date', 'parameter');
      $rdesc = $oReport->get_fld_val('dbx_rdesc', 'DESC', 'parameter');

      $where = $this->report_search_where($rwhere);
      $cols = array('id', 'create_date', 'workflow_key', 'result_label', 'status', 'current_need', 'percent', 'message');

      $oReport->_rflds = array(
         'id' => 'ID',
         'create_date' => $oReport->get_fd_message('column_start'),
         'workflow_key' => $oReport->get_fd_message('column_workflow'),
         'result_label' => $oReport->get_fd_message('column_goal'),
         'status' => $oReport->get_fd_message('column_status'),
         'current_need' => $oReport->get_fd_message('column_task'),
         'percent' => '%',
         'message' => $oReport->get_fd_message('column_message'),
         'instance_action' => $oReport->get_fd_message('column_action')
      );
      $oReport->_rpt_format = array(
         'create_date' => 'php-datetime-usr',
         'status' => 'html',
         'percent' => 'html',
         'message' => 'html',
         'instance_action' => 'html'
      );
      $oReport->_rrows = $rrows;
      $oReport->_rpos = $rpos;
      $oReport->_count_all = $db->count($this->ddInstance, '');
      $oReport->_rcount = $db->count($this->ddInstance, $where);
      $oReport->_rdata = $this->decorate_instance_rows(
         $db->select($this->ddInstance, $where, $cols, $rsort, $rdesc, '', $rrows, $rpos),
         $oReport
      );

      return $this->frame($oReport->run(), $oReport);
   }

   private function decorate_instance_rows($rows, $texts) {
      $out = array();
      foreach ((array)$rows as $row) {
         $row['instance_action'] = $this->instance_action_button($row, $texts);
         $row['status'] = $this->instance_status_badge((string)($row['status'] ?? ''), $texts);
         $row['percent'] = $this->instance_percent_bar((int)($row['percent'] ?? 0));
         $row['message'] = $this->instance_message((string)($row['message'] ?? ''));
         $out[] = $row;
      }
      return $out;
   }

   private function instance_status_badge(string $status, $texts): string {
      $status = strtolower(trim($status));
      $labels = array(
         'running' => array($texts->get_fd_message('status_running'), 'primary'),
         'finishing' => array($texts->get_fd_message('status_finishing'), 'warning'),
         'paused' => array($texts->get_fd_message('status_paused'), 'warning'),
         'finished' => array($texts->get_fd_message('status_finished'), 'success'),
         'canceled' => array($texts->get_fd_message('status_canceled'), 'secondary'),
         'error' => array($texts->get_fd_message('status_error'), 'danger'),
      );
      $data = $labels[$status] ?? array(($status !== '' ? $status : $texts->get_fd_message('status_unknown')), 'secondary');
      return '<span class="badge text-bg-' . $this->h($data[1]) . '">' . $this->h($data[0]) . '</span>';
   }

   private function instance_percent_bar(int $percent): string {
      $percent = max(0, min(100, $percent));
      return '<div class="dbx-workflow-instance-progress" data-dbx-tooltip="' . $percent . '%">'
         . '<div class="dbx-workflow-instance-progress-bar" style="width:' . $percent . '%"></div>'
         . '<span>' . $percent . '%</span>'
         . '</div>';
   }

   private function instance_message(string $message): string {
      $message = trim($message);
      if ($message === '') {
         return '<span class="text-muted">-</span>';
      }
      return '<span class="dbx-workflow-instance-message">' . $this->h($message) . '</span>';
   }

   private function instance_action_button(array $row, $texts): string {
      $id = (int)($row['id'] ?? 0);
      if ($id <= 0) {
         return '';
      }

      $status = strtolower(trim((string)($row['status'] ?? '')));
      $isFinished = in_array($status, array('finished', 'canceled'), true);
      $url = '?dbx_modul=dbxWorkflow&dbx_run1=run&iid=' . $id;
      if ($status === 'paused') {
         $url .= '&dbx_token=' . rawurlencode(dbx()->action_token('dbxWorkflow.instance.' . $id)) . '&proc_cmd=resume';
      }

      $label = $isFinished
         ? $texts->get_fd_message('action_view')
         : $texts->get_fd_message('action_continue');
      $icon = $isFinished ? 'bi-search' : 'bi-play-fill';
      $class = $isFinished ? 'btn btn-outline-primary btn-sm' : 'btn btn-primary btn-sm';
      $title = $texts->format_fd_message(
         'action_title',
         array('action' => $label, 'id' => $id)
      );

      return '<a class="' . $class . ' openWin dbx-win dbx-workflow-instance-action" href="' . $this->h($url) . '"'
         . ' data-url="' . $this->h($url) . '" data-title="' . $this->h($title) . '" data-width="82%" data-height="88%"'
         . ' title="' . $this->h($title) . '"><i class="bi ' . $icon . '"></i> ' . $this->h($label) . '</a>';
   }

   private function bindRegistry() {
      return dbx()->get_include_obj('dbxWorkflowBindRegistry', 'dbxWorkflow');
   }

   private function load_bind_row($rid) {
      if ($rid === 'new' || (int)$rid <= 0) {
         return array(
            'modul' => 'dbxContact',
            'bind_key' => 'contact_reply',
            'title' => 'Neues Modul-Binding',
            'description' => 'Einheitliches Binding: dbxWorkflow nutzt DD/FD/TPL/Config des Moduls.',
            'bind_json' => '',
            'active' => 1,
         );
      }

      $rows = $this->db()->select($this->ddBind, array('id' => (int)$rid), '*', 'id', 'DESC', '', 1, 0, 0);
      return (is_array($rows) && isset($rows[0])) ? $rows[0] : array();
   }

   private function edit_bind() {
      $rid = dbx()->get_modul_var('rid', 'new', 'parameter');
      $isNew = ($rid === 'new' || (int)$rid <= 0);
      $data = $this->load_bind_row($rid);

      $oForm = dbx()->get_system_obj('dbxForm');
      $oForm->init('workflow-module-bind', 'workflow-module-bind-form');
      $oForm->_dd = $this->ddBind;
      $oForm->_fd = 'dbxWorkflow|workflow-module-bind';
      $oForm->load_fd_messages();
      if ($isNew) {
         $data['title'] = $oForm->get_fd_message('default_binding_title');
         $data['description'] = $oForm->get_fd_message('default_binding_description');
      }

      if (!$data) {
         return $this->frame(
            $this->alert('warning', $oForm->get_fd_message('binding_not_found')),
            $oForm
         );
      }

      $generatorData = array(
         'gen_modul' => trim((string)dbx()->get_modul_var('gen_modul', (string)($data['modul'] ?? ''), 'parameter')),
         'gen_dd' => trim((string)dbx()->get_modul_var('gen_dd', '', 'parameter')),
      );
      $generatorDds = array();
      foreach ($this->bindRegistry()->listModuleDds($generatorData['gen_modul']) as $ddRef) {
         $generatorDds[(string)$ddRef] = (string)$ddRef;
      }

      // Zwei Formulare werden auf derselben Seite gerendert. Ein eigenes
      // Objekt verhindert, dass init() des Hauptformulars den Zustand des
      // Generatorformulars im global gecachten dbxForm-Objekt ueberschreibt.
      dbx()->get_system_obj('dbxForm', 'use');
      $generatorForm = new \dbxForm();
      $generatorForm->init('workflow-bind-generator', 'workflow-bind-generator');
      $generatorForm->_fd = 'dbxWorkflow|workflow-module-bind';
      $generatorForm->load_fd_messages();
      $generatorForm->set_form_help_enabled(false);
      $generatorForm->_action = '?dbx_modul=dbxWorkflow_admin&dbx_run1=edit_bind&rid=' . ($isNew ? 'new' : (int)$rid);
      $generatorForm->_data = array_merge($generatorForm->_data, $generatorData);
      $generatorForm->_msg_info = '';
      $generatorForm->add_fld(
         'gen_modul',
         'text-label',
         label: $generatorForm->get_fd_message('generator_module_label'),
         rules: 'parameter|min=2|max=80',
         data: array('placeholder' => 'dbxContact')
      );
      $generatorForm->add_fld(
         'gen_dd',
         'select-single-label',
         label: $generatorForm->get_fd_message('generator_dd_label'),
         rules: 'parameter|min=2|max=160',
         options: array('' => $generatorForm->get_fd_message('generator_dd_select')) + $generatorDds
      );

      if ($generatorForm->submit()) {
         if (!$generatorForm->errors()) {
            $generateModul = trim((string)$generatorForm->get_post('gen_modul', '', 'parameter|min=2|max=80'));
            $generateDd = trim((string)$generatorForm->get_post('gen_dd', '', 'parameter|min=2|max=160'));
            $generated = $this->bindRegistry()->generateBindSkeleton($generateModul, $generateDd);
            if ($generated) {
               $data = array_merge($data, $generated);
               $generatorForm->_msg_success = $generatorForm->get_fd_message('generator_success');
            } else {
               $generatorForm->_msg_error = $generatorForm->get_fd_message('generator_error');
            }
         } else {
            $generatorForm->_msg_error = $generatorForm->get_fd_message('generator_validation_error');
         }
      }

      $oForm->add_rep('bar_title', $oForm->get_fd_message($isNew ? 'form_new_title' : 'form_edit_title'));
      $oForm->add_rep('bar_subtitle', $oForm->get_fd_message('form_subtitle'));
      $oForm->add_obj('bar_actions', 'obj-value',
         '<a class="btn btn-outline-secondary btn-sm" href="?dbx_modul=dbxWorkflow_admin&dbx_run1=binds"><i class="bi bi-list-ul"></i> ' . $this->h($oForm->get_fd_message('list_label')) . '</a>'
         . '<button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-save"></i> ' . $this->h($oForm->get_fd_message('save_label')) . '</button>'
      );
      $oForm->_data = array_merge($oForm->_data, $data);
      $oForm->_action = '?dbx_modul=dbxWorkflow_admin&dbx_run1=edit_bind&rid=' . ($isNew ? 'new' : (int)$rid);
      $oForm->set_activ_id($isNew ? 0 : (int)$rid);
      $oForm->_msg_info = $oForm->get_fd_message($isNew ? 'form_new_info' : 'form_edit_info');

      $oForm->add_fld('modul');
      $oForm->add_fld('bind_key');
      $oForm->add_fld('title');
      $oForm->add_fld('description');
      $oForm->add_fld('bind_json');
      $oForm->add_fld('active');

      if ($oForm->submit()) {
         if (!$oForm->errors()) {
            $bindJson = trim((string)$oForm->get_post_data('bind_json', '', '*'));
            $decoded = json_decode($bindJson, true);
            if (!is_array($decoded)) {
               $oForm->add_fld_error('bind_json', $oForm->get_fd_message('json_invalid'));
               $oForm->_msg_error = $oForm->get_fd_message('json_invalid');
            } else {
               $values = array(
                  'modul' => $oForm->get_post('modul', '', 'parameter|min=2|max=80'),
                  'bind_key' => $oForm->get_post('bind_key', '', 'parameter|min=2|max=80'),
                  'title' => $oForm->get_post('title', '', '*|min=2|max=160'),
                  'description' => $oForm->get_post_data('description', '', '*'),
                  'bind_json' => json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                  'active' => $oForm->get_post('active', 0, 'int'),
               );

               $db = $this->db();
               $where = "modul='" . $db->escape($values['modul'], $db->get_dd_server($this->ddBind))
                     . "' AND bind_key='" . $db->escape($values['bind_key'], $db->get_dd_server($this->ddBind)) . "'";
               if (!$isNew) {
                  $where .= ' AND id <> ' . (int)$rid;
               }

               if ($db->count($this->ddBind, $where) > 0) {
                  $oForm->add_fld_error('bind_key', $oForm->get_fd_message('duplicate_bind_key'));
                  $oForm->_msg_error = $oForm->get_fd_message('duplicate_bind_key');
               } else {
                  $ok = $db->save($this->ddBind, $values, $isNew ? 0 : (int)$rid, 0, 1, 1, 1);
                  $oForm->_msg_success = $oForm->get_fd_message($ok ? 'save_success' : 'save_error');
                  if ($ok) {
                     $oForm->_data = array_merge($oForm->_data, $values);
                  }
               }
            }
         } else {
            $oForm->_msg_error = $oForm->get_fd_message('validation_error');
         }
      }

      $oForm->add_obj('bind_generator', 'obj-value', $generatorForm->run());

      return $oForm->run();
   }

   private function decorate_bind_rows($rows) {
      $out = array();
      foreach ((array)$rows as $row) {
         $row['bind_ref'] = (string)($row['modul'] ?? '') . '|' . (string)($row['bind_key'] ?? '');
         $row['action'] = $this->tpl()->get_tpl('dbxWorkflow_admin|workflow-bind-row-action', array(
            'id' => (int)($row['id'] ?? 0),
            'bind_ref' => $this->h($row['bind_ref']),
         ));
         $out[] = $row;
      }
      return $out;
   }

   private function list_binds() {
      $db = $this->db();
      $oReport = dbx()->get_system_obj('dbxReport');
      $oReport->init('workflow-module-binds', 'dbxWorkflow_admin|workflow-module-binds');
      $oReport->_dd = $this->ddBind;
      $oReport->_fd = 'dbxWorkflow|workflow-module-bind';
      $oReport->load_fd_messages();
      $oReport->_action = '?dbx_modul=dbxWorkflow_admin&dbx_run1=binds';
      $oReport->_pages = true;
      $oReport->_create_row_select = false;
      $oReport->_create_row_edit = false;
      $oReport->_create_row_delete = false;
      $oReport->_but_pagination = 5;
      $oReport->set_form_help_enabled(false);
      $oReport->_msg_info = $oReport->get_fd_message('list_info');

      $cols = array('id', 'modul', 'bind_key', 'title', 'active', 'update_date');
      $oReport->_rflds = array(
         'id' => 'ID',
         'modul' => $oReport->get_fd_message('column_module'),
         'bind_key' => $oReport->get_fd_message('column_bind_key'),
         'title' => $oReport->get_fd_message('column_title'),
         'active' => $oReport->get_fd_message('column_active'),
         'update_date' => $oReport->get_fd_message('column_update'),
         'bind_ref' => $oReport->get_fd_message('column_reference'),
         'action' => $oReport->get_fd_message('column_action'),
      );
      $oReport->_rpt_format = array(
         'update_date' => 'php-datetime-usr',
         'action' => 'html',
      );
      $oReport->_rrows = 30;
      $oReport->_rpos = 0;
      $oReport->_count_all = $db->count($this->ddBind, '');
      $oReport->_rcount = $oReport->_count_all;
      $oReport->_rdata = $this->decorate_bind_rows($db->select($this->ddBind, '', $cols, 'modul', 'ASC', '', 30, 0));

      return $this->frame($oReport->run(), $oReport);
   }

   public function run() {
      $run = dbx()->get_modul_var('dbx_run1', 'list', 'parameter');

      if ($run === 'install') return $this->install();
      if ($run === 'edit') return $this->edit();
      if ($run === 'edit_bind') return $this->edit_bind();
      if ($run === 'binds') return $this->list_binds();
      if ($run === 'instances') return $this->list_instances();

      return $this->list_definitions();
   }
}
?>
