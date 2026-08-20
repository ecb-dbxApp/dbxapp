<?php
namespace dbx\dbxWorkflow_admin;

require_once dirname(__DIR__, 2) . '/dbxWorkflow/include/dbxWorkflowValue.class.php';

use dbx\dbxWorkflow\dbxWorkflowValue;

class dbxWorkflowAdmin {

   private $dd_definition = 'dbxWorkflow|workflowDefinition';
   private $dd_instance   = 'dbxWorkflow|workflowInstance';
   private $dd_step       = 'dbxWorkflow|workflowStep';
   private $dd_bind       = 'dbxWorkflow|workflowModuleBind';

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
      $help = dbx()->get_include_obj('dbxModuleHelp', 'dbxHelp');
      $title = $texts
         ? $texts->get_fd_message('bar_title', 'Workflow Definitionen')
         : 'Workflow Definitionen';
      $subtitle = $texts
         ? $texts->get_fd_message('bar_subtitle', 'Workflow-Definitionen')
         : 'Workflow-Definitionen';
      $module_bindings = $texts
         ? $texts->get_fd_message('module_bindings', 'Modul-Bindings')
         : 'Modul-Bindings';
      $new_binding = $texts
         ? $texts->get_fd_message('new_binding', 'Neues Binding')
         : 'Neues Binding';
      $new_workflow = $texts
         ? $texts->get_fd_message('new_workflow', 'Neuer Workflow')
         : 'Neuer Workflow';
      $bar_actions = ''
         . '<a class="btn btn-outline-secondary btn-sm" href="?dbx_modul=dbxWorkflow_admin&dbx_run1=binds"><i class="bi bi-plug"></i> ' . $this->h($module_bindings) . '</a>'
         . '<a class="btn btn-primary btn-sm" href="?dbx_modul=dbxWorkflow_admin&dbx_run1=edit_bind&rid=new"><i class="bi bi-plus-circle"></i> ' . $this->h($new_binding) . '</a>'
         . '<a class="btn btn-primary btn-sm" href="?dbx_modul=dbxWorkflow_admin&dbx_run1=edit&rid=new"><i class="bi bi-plus-circle"></i> ' . $this->h($new_workflow) . '</a>';

      return $this->tpl()->get_tpl('dbxWorkflow_admin|workflow-admin-actions', array_merge(
         $help->module_bar_template_data('', $bar_actions, '', '', '', 'dbxWorkflow_admin'),
         array(
            'bar_title' => $this->h($title),
            'bar_subtitle' => $this->h($subtitle),
            'bar_icon' => 'bi-diagram-3',
            'bar_actions' => $bar_actions,
            'content' => $content,
         )
      ));
   }

   private function install() {
      $o_dd = dbx()->get_system_obj('dbxDD');
      foreach (array('workflowDefinition', 'workflowInstance', 'workflowStep', 'workflowModuleBind') as $dd) {
         $o_dd->sync_dd_to_db('dbxWorkflow', $dd, 'reset');
         for ($i = 0; $i < 80; $i++) {
            $state = $o_dd->sync_dd_to_db('dbxWorkflow', $dd, 'apply');
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

      $rows = $this->db()->select($this->dd_definition, array('id' => (int)$rid), '*', 'id', 'DESC', '', 1, 0, 0);
      return (is_array($rows) && isset($rows[0])) ? $rows[0] : array();
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
      $row_count = max(14, count($needs) + 6);
      $rows = '';

      for ($i = 0; $i < $row_count; $i++) {
         $need = $needs[$i] ?? array();
         $is_empty = empty($need);
         $key = (string)($need['key'] ?? '');
         $label = (string)($need['label'] ?? '');
         $allowed_actions = array('form', 'select', 'create', 'module');
         $actions = array_values(array_unique(array_intersect(array_map('strval', (array)($need['actions'] ?? array('form'))), $allowed_actions)));
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
         $raw_question = trim((string)($need['question'] ?? ''));
         $question = $raw_question !== '' ? $raw_question : $this->workflow_default_question($label, $texts);
         $validation = (string)($need['validation'] ?? ($mode === 'multiple' ? 'at_least_one' : 'exactly_one'));
         if (!in_array($validation, array('exactly_one', 'at_least_one', 'not_empty', 'positive_integer', 'confirmed'), true)) {
            $validation = $mode === 'multiple' ? 'at_least_one' : 'exactly_one';
         }
         $raw_missing_message = trim((string)($need['missing_message'] ?? ''));
         $missing_message = $raw_missing_message !== '' ? $raw_missing_message : $this->workflow_default_missing_message($label, $texts);
         $resolver = is_array($need['resolver'] ?? null) ? $need['resolver'] : array();
         $resolver_label = trim((string)($resolver['label'] ?? ''));
         if ($resolver_label === '') $resolver_label = $this->workflow_resolver_label($action, $texts);
         $hint = (string)($need['hint'] ?? '');
         $event = (string)($need['event'] ?? ($need['result_event'] ?? ''));
         $depends_on = (string)($need['depends_on'] ?? '');
         $depends_value = (string)($need['depends_value'] ?? '');
         $options_text = $this->workflow_options_text($need['options'] ?? array());
         $source_json = '';
         if (!empty($need['source']) && is_array($need['source'])) {
            $source_json = json_encode($need['source'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
         }
         $bind_json = '';
         if (!empty($need['bind']) && is_array($need['bind'])) {
            $bind_json = json_encode($need['bind'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
         }
         $module_links_json = '';
         if (!empty($need['module_links']) && is_array($need['module_links'])) {
            $module_links_json = json_encode($need['module_links'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
         }
         $complete_label = (string)($need['complete_label'] ?? '');
         $original_json = $is_empty ? '' : (json_encode($need, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

         $row_class = $is_empty ? ' dbx-workflow-step-row-empty' : '';
         $row_style = $is_empty ? ' style="display:none;"' : '';
         $kind_icon = array('input' => 'bi-input-cursor-text', 'action' => 'bi-lightning-charge', 'check' => 'bi-shield-check', 'decision' => 'bi-signpost-split')[$kind] ?? 'bi-input-cursor-text';
         $kind_labels = $this->workflow_kind_labels($texts);
         $kind_label = $kind_labels[$kind] ?? $kind_labels['input'];
         $rows .= '<article class="dbx-workflow-step-row' . $row_class . '" data-workflow-step-row data-step-kind="' . $this->h($kind) . '" data-question-auto="' . ($raw_question === '' ? '1' : '0') . '" data-missing-auto="' . ($raw_missing_message === '' ? '1' : '0') . '" draggable="true"' . $row_style . '>'
            . '<input type="hidden" name="workflow_step_present[' . $i . ']" value="1">'
            . '<input type="hidden" name="workflow_step_source[' . $i . ']" value="' . $this->h($source_json) . '">'
            . '<input type="hidden" name="workflow_step_bind[' . $i . ']" value="' . $this->h($bind_json) . '">'
            . '<input type="hidden" name="workflow_step_module_links[' . $i . ']" value="' . $this->h($module_links_json) . '">'
            . '<input type="hidden" name="workflow_step_original[' . $i . ']" value="' . $this->h($original_json) . '">'
            . '<input type="hidden" name="workflow_step_original_index[' . $i . ']" value="' . ($is_empty ? '' : $i) . '">'
            . '<input type="hidden" name="workflow_step_action_contract[' . $i . ']" value="1">'
            . '<input class="visually-hidden" type="checkbox" name="workflow_step_active[' . $i . ']" value="1"' . (!$is_empty ? ' checked' : '') . '>'
            . '<span class="dbx-workflow-node-port is-in" aria-hidden="true"></span>'
            . '<details class="dbx-workflow-step-details"' . (!$is_empty ? ' open' : '') . '>'
            . '<summary class="dbx-workflow-step-summary">'
            . '<button class="dbx-workflow-drag-handle" type="button" draggable="true" data-workflow-drag-handle data-dbx-tooltip="' . $this->h($texts->get_fd_message('step_move')) . '" aria-label="' . $this->h($texts->get_fd_message('step_move')) . '"><i class="bi bi-grip-vertical"></i></button>'
            . '<span class="dbx-workflow-step-num" data-workflow-step-number>' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) . '</span>'
            . '<span class="dbx-workflow-step-kind"><i class="bi ' . $this->h($kind_icon) . '" data-workflow-kind-icon></i><span data-workflow-kind-label>' . $this->h($kind_label) . '</span></span>'
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
            . '<div class="col-xl-6 col-md-8" data-workflow-module-setting' . (!in_array('module', $actions, true) ? ' hidden' : '') . '><label class="form-label">' . $this->h($texts->get_fd_message('field_module_confirmation')) . '</label><input class="form-control form-control-sm" name="workflow_step_complete_label[' . $i . ']" value="' . $this->h($complete_label) . '" placeholder="' . $this->h($texts->get_fd_message('placeholder_module_confirmation')) . '"></div>'
            . '<div class="col-12"><span class="dbx-workflow-safety-note"><i class="bi bi-shield-lock"></i> ' . $this->h($texts->get_fd_message('external_actions_confirmed')) . '</span></div>'
            . '<div class="col-12"><section class="dbx-workflow-step-contract">'
            . '<header><i class="bi bi-clipboard2-check"></i><div><strong>' . $this->h($texts->get_fd_message('automatic_check')) . '</strong><small>' . $this->h($texts->get_fd_message('automatic_check_hint')) . '</small></div></header>'
            . '<div class="row g-2">'
            . '<div class="col-xl-5 col-md-12"><label class="form-label">' . $this->h($texts->get_fd_message('field_question')) . '</label><input class="form-control form-control-sm" name="workflow_step_question[' . $i . ']" value="' . $this->h($question) . '" placeholder="' . $this->h($texts->get_fd_message('placeholder_question')) . '"></div>'
            . '<div class="col-xl-3 col-md-6"><label class="form-label">' . $this->h($texts->get_fd_message('field_validation')) . '</label><select class="form-select form-select-sm" name="workflow_step_validation[' . $i . ']">' . $this->workflow_validation_options($validation, $texts) . '</select></div>'
            . '<div class="col-xl-4 col-md-6"><label class="form-label">' . $this->h($texts->get_fd_message('field_resolver')) . '</label><input class="form-control form-control-sm" name="workflow_step_resolver_label[' . $i . ']" value="' . $this->h($resolver_label) . '" placeholder="' . $this->h($texts->get_fd_message('placeholder_resolver')) . '"></div>'
            . '<div class="col-12"><label class="form-label">' . $this->h($texts->get_fd_message('field_missing_message')) . '</label><input class="form-control form-control-sm" name="workflow_step_missing_message[' . $i . ']" value="' . $this->h($missing_message) . '" placeholder="' . $this->h($texts->get_fd_message('placeholder_missing')) . '"></div>'
            . '</div></section></div>'
            . '<div class="col-xl-4 col-md-6"><label class="form-label">' . $this->h($texts->get_fd_message('field_depends')) . '</label><select class="form-select form-select-sm" name="workflow_step_depends_on[' . $i . ']">' . $this->workflow_depends_options($needs, $depends_on, $texts) . '</select></div>'
            . '<div class="col-xl-4 col-md-6"><label class="form-label">' . $this->h($texts->get_fd_message('field_depends_value')) . '</label><input class="form-control form-control-sm" name="workflow_step_depends_value[' . $i . ']" value="' . $this->h($depends_value) . '" placeholder="' . $this->h($texts->get_fd_message('placeholder_depends_value')) . '"></div>'
            . '<div class="col-xl-4 col-md-12"><label class="form-label">' . $this->h($texts->get_fd_message('field_options')) . '</label><textarea class="form-control form-control-sm" name="workflow_step_options[' . $i . ']" rows="3" placeholder="' . $this->h($texts->get_fd_message('placeholder_options')) . '">' . $this->h($options_text) . '</textarea></div>'
            . '<div class="col-12"><label class="form-label">' . $this->h($texts->get_fd_message('field_hint')) . '</label><textarea class="form-control form-control-sm" name="workflow_step_hint[' . $i . ']" rows="2" placeholder="' . $this->h($texts->get_fd_message('placeholder_hint')) . '">' . $this->h($hint) . '</textarea></div>'
            . '</div></div></details>'
            . '<span class="dbx-workflow-node-port is-out" aria-hidden="true"></span>'
            . '</article>';
      }

      $check_rows = '';
      foreach ($needs as $need) {
         $label = trim((string)($need['label'] ?? $need['key'] ?? $texts->get_fd_message('default_value_label')));
         $question = trim((string)($need['question'] ?? '')) ?: $this->workflow_default_question($label, $texts);
         $required = array_key_exists('required', $need) ? (bool)$need['required'] : true;
         $validation = (string)($need['validation'] ?? (($need['mode'] ?? 'single') === 'multiple' ? 'at_least_one' : 'exactly_one'));
         $action = (string)($need['preferred'] ?? (((array)($need['actions'] ?? array('form')))[0] ?? 'form'));
         $resolver = is_array($need['resolver'] ?? null) ? $need['resolver'] : array();
         $resolver_label = trim((string)($resolver['label'] ?? '')) ?: $this->workflow_resolver_label($action, $texts);
         $validation_labels = $this->workflow_validation_labels($texts);
         $check_rows .= $this->tpl()->get_tpl('dbxWorkflow_admin|workflow-check-preview-item', array(
            'question' => $this->h($question),
            'label' => $this->h($label),
            'validation' => $this->h($validation_labels[$validation] ?? $validation),
            'required' => $required ? $texts->get_fd_message('preview_required') : $texts->get_fd_message('preview_optional'),
            'resolver_label' => $this->h($resolver_label),
         ));
      }

      $finish_label = (string)($definition['finish']['label'] ?? $texts->format_fd_message(
         'finish_default',
         array('result' => ($data['result_label'] ?? 'Workflow'))
      ));
      $bind_ref = (string)($definition['bind_ref'] ?? '');
      $technical_preview = json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      $designer_message_keys = array(
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
      $designer_messages = array();
      foreach ($designer_message_keys as $message_key) {
         $designer_messages[$message_key] = $texts->get_fd_message($message_key);
      }

      return $this->tpl()->get_tpl('dbxWorkflow_admin|workflow-builder', array(
         'need_count' => count($needs),
         'check_rows' => $check_rows,
         'step_rows' => $rows,
         'finish_label' => $this->h($finish_label),
         'bind_ref' => $this->h($bind_ref),
         'technical_preview' => $this->h($technical_preview),
         'designer_messages' => $this->h(json_encode(
            $designer_messages,
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
      $has_action_contract = isset($_POST['workflow_step_action_contract'])
         && array_key_exists($index, (array)$_POST['workflow_step_action_contract']);
      $posted = $_POST['workflow_step_actions'][$index] ?? array();
      if (!is_array($posted)) $posted = array($posted);
      $selected = array_values(array_unique(array_intersect(array_map('strval', $posted), $allowed)));

      // Offene Formulare aus einer älteren Version senden noch keine
      // Mehrfachauswahl. In diesem Fall bleibt die bestehende Aktionsliste
      // vollständig erhalten.
      if (!$has_action_contract) {
         $selected = array_values(array_unique(array_intersect(array_map('strval', (array)($original['actions'] ?? array())), $allowed)));
      }
      if (!$selected) $selected = array(in_array($preferred, $allowed, true) ? $preferred : 'form');

      // Bestehende Reihenfolge erhalten; neu aktivierte Aktionen folgen in der
      // stabilen UI-Reihenfolge. Damit verändert reines Öffnen und Speichern
      // auch die Priorisierung alter Definitionen nicht.
      $selected_map = array_fill_keys($selected, true);
      $actions = array();
      foreach ((array)($original['actions'] ?? array()) as $action) {
         $action = (string)$action;
         if (isset($selected_map[$action]) && !in_array($action, $actions, true)) $actions[] = $action;
      }
      foreach ($allowed as $action) {
         if (isset($selected_map[$action]) && !in_array($action, $actions, true)) $actions[] = $action;
      }
      return $actions;
   }

   private function workflow_definition_from_post($o_form, array $base_definition = array()) {
      $steps = array();
      $check_key_renames = array();
      $base_needs = array_values((array)($base_definition['needs'] ?? array()));
      $present = $_POST['workflow_step_present'] ?? array();
      foreach ((array)$present as $idx => $unused) {
         $active = isset($_POST['workflow_step_active'][$idx]);
         $label = trim((string)($_POST['workflow_step_label'][$idx] ?? ''));
         $key = dbxWorkflowValue::key($_POST['workflow_step_key'][$idx] ?? $label);
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
         $has_action_contract = isset($_POST['workflow_step_action_contract'])
            && array_key_exists($idx, (array)$_POST['workflow_step_action_contract']);
         $original_index_raw = trim((string)($_POST['workflow_step_original_index'][$idx] ?? ''));
         $original_index = ctype_digit($original_index_raw) ? (int)$original_index_raw : null;
         if (!$need && $original_index !== null && isset($base_needs[$original_index]) && is_array($base_needs[$original_index])) {
            $need = $base_needs[$original_index];
         } elseif (!$need && !$has_action_contract && isset($base_needs[(int)$idx]) && is_array($base_needs[(int)$idx])) {
            $need = $base_needs[(int)$idx];
         }
         $original_key = dbxWorkflowValue::key($need['key'] ?? '');
         if ($original_key !== '' && $original_key !== $key) $check_key_renames[$original_key] = $key;
         $actions = $this->workflow_actions_from_post($idx, $need, $action);
         if (!in_array($action, $actions, true)) $action = (string)$actions[0];
         $resolver = is_array($need['resolver'] ?? null) ? $need['resolver'] : array();
         $resolver['type'] = $action;
         $resolver['label'] = trim((string)($_POST['workflow_step_resolver_label'][$idx] ?? '')) ?: $this->workflow_resolver_label($action, $o_form);

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
         $need['question'] = trim((string)($_POST['workflow_step_question'][$idx] ?? '')) ?: $this->workflow_default_question($label, $o_form);
         $need['validation'] = (string)($_POST['workflow_step_validation'][$idx] ?? ($mode === 'multiple' ? 'at_least_one' : 'exactly_one'));
         $need['missing_message'] = trim((string)($_POST['workflow_step_missing_message'][$idx] ?? '')) ?: $this->workflow_default_missing_message($label, $o_form);
         $need['resolver'] = $resolver;
         $need['hint'] = trim((string)($_POST['workflow_step_hint'][$idx] ?? ''));

         $event = trim((string)($_POST['workflow_step_event'][$idx] ?? ''));
         if ($event !== '') $need['event'] = $event;
         else unset($need['event'], $need['result_event']);

         $depends_on = dbxWorkflowValue::key($_POST['workflow_step_depends_on'][$idx] ?? '');
         if ($depends_on !== '') {
            $need['depends_on'] = $depends_on;
            $depends_value = trim((string)($_POST['workflow_step_depends_value'][$idx] ?? ''));
            if ($depends_value !== '') $need['depends_value'] = $depends_value;
            else unset($need['depends_value']);
         }
         else unset($need['depends_on'], $need['depends_value']);

         $options = $this->parse_workflow_options($_POST['workflow_step_options'][$idx] ?? '');
         if ($options) $need['options'] = $options;
         else unset($need['options']);

         if (isset($_POST['workflow_step_complete_label']) && array_key_exists($idx, (array)$_POST['workflow_step_complete_label'])) {
            $complete_label = trim((string)$_POST['workflow_step_complete_label'][$idx]);
            if ($complete_label !== '') $need['complete_label'] = $complete_label;
            else unset($need['complete_label']);
         }

         $source_json = trim((string)($_POST['workflow_step_source'][$idx] ?? ''));
         $source = $source_json !== '' ? json_decode($source_json, true) : null;
         if (is_array($source)) $need['source'] = $source;
         elseif ($source_json === '') unset($need['source']);

         $bind_json = trim((string)($_POST['workflow_step_bind'][$idx] ?? ''));
         $bind = $bind_json !== '' ? json_decode($bind_json, true) : null;
         if (is_array($bind)) $need['bind'] = $bind;
         elseif ($bind_json === '') unset($need['bind']);

         $module_links_json = trim((string)($_POST['workflow_step_module_links'][$idx] ?? ''));
         $module_links = $module_links_json !== '' ? json_decode($module_links_json, true) : null;
         if (is_array($module_links)) $need['module_links'] = $module_links;
         elseif ($module_links_json === '') unset($need['module_links']);

         $steps[] = $need;
      }

      $definition = $base_definition;
      $definition['workflow_key'] = $o_form->get_post('workflow_key', '', 'parameter|min=2|max=80');
      $definition['title'] = $o_form->get_post('title', '', '*|min=2|max=160');
      $definition['result'] = $o_form->get_post('result_label', '', '*|min=2|max=160');
      $definition['description'] = $o_form->get_post_data('description', '', '*');
      $definition['needs'] = $steps;
      if ($check_key_renames && !empty($definition['checks']) && is_array($definition['checks'])) {
         foreach ($definition['checks'] as &$check) {
            if (!is_array($check)) continue;
            $check_key = dbxWorkflowValue::key($check['key'] ?? '');
            if (isset($check_key_renames[$check_key])) $check['key'] = $check_key_renames[$check_key];
         }
         unset($check);
      }
      $finish = is_array($definition['finish'] ?? null) ? $definition['finish'] : array();
      $finish['label'] = trim((string)($_POST['workflow_finish_label'] ?? '')) ?: $o_form->format_fd_message(
         'finish_default',
         array('result' => $definition['result'] ?: 'Workflow')
      );
      $definition['finish'] = $finish;

      $bind_ref = trim((string)($_POST['workflow_bind_ref'] ?? ''));
      if ($bind_ref !== '') $definition['bind_ref'] = $bind_ref;
      else unset($definition['bind_ref']);

      return $definition;
   }

   private function validate_workflow_definition(array $definition, $texts): array {
      $errors = array();
      $needs = array_values((array)($definition['needs'] ?? array()));
      $positions = array();

      foreach ($needs as $index => $need) {
         $key = dbxWorkflowValue::key($need['key'] ?? '');
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
         $key = dbxWorkflowValue::key($need['key'] ?? '');
         $depends_on = dbxWorkflowValue::key($need['depends_on'] ?? '');
         if ($depends_on === '') continue;
         if (!array_key_exists($depends_on, $positions)) {
            $errors[] = $texts->format_fd_message(
               'validation_dependency_missing',
               array('dependency' => $depends_on, 'key' => $key)
            );
         } elseif ($positions[$depends_on] >= $index) {
            $errors[] = $texts->format_fd_message(
               'validation_dependency_order',
               array('dependency' => $depends_on, 'key' => $key)
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
      $is_new = ($rid === 'new' || (int)$rid <= 0);

      $o_form = dbx()->get_system_obj('dbxForm');
      $o_form->init('workflow-definition', 'workflow-definition-form');
      $o_form->set_data_source($this->dd_definition, 'dbxWorkflow|workflow-definition');
      $o_form->load_fd_messages();

      $data = $this->load_row($rid);
      if (!$data) {
         return $this->frame(
            $this->alert('warning', $o_form->get_fd_message('definition_not_found')),
            $o_form
         );
      }

      $o_form->add_rep('bar_title', $o_form->get_fd_message($is_new ? 'form_new_title' : 'form_edit_title'));
      $o_form->add_rep('bar_subtitle', $o_form->get_fd_message('form_subtitle'));
      $o_form->add_obj('bar_actions', 'obj-value',
         '<a class="btn btn-outline-secondary btn-sm" href="?dbx_modul=dbxWorkflow_admin"><i class="bi bi-list-ul"></i> ' . $this->h($o_form->get_fd_message('list_label')) . '</a>'
         . '<button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-save"></i> ' . $this->h($o_form->get_fd_message('save_label')) . '</button>'
      );
      $o_form->set_data($data);
      $o_form->set_action('?dbx_modul=dbxWorkflow_admin&dbx_run1=edit&rid=' . ($is_new ? 'new' : (int)$rid));
      $o_form->set_activ_id($is_new ? 0 : (int)$rid);
      $o_form->_msg_info = $o_form->get_fd_message($is_new ? 'form_new_info' : 'form_edit_info');

      $o_form->add_fld('workflow_key');
      $o_form->add_fld('title');
      $o_form->add_fld('result_label');
      $o_form->add_fld('description');
      $o_form->add_fld('active');
      $o_form->add_obj('workflow_builder', 'obj-value', $this->workflow_builder_html($data, $o_form));

      if ($o_form->submit()) {
         if (!$o_form->errors()) {
            $engine = dbx()->get_include_obj('dbxWorkflowEngine', 'dbxWorkflow');
            $base_definition = $this->workflow_definition_from_data($data);
            $definition = $engine->normalize_definition($this->workflow_definition_from_post($o_form, $base_definition), $o_form->get_post('workflow_key', '', 'parameter|max=80'));
            $definition_errors = $this->validate_workflow_definition($definition, $o_form);
            if ($definition_errors) {
               $error_text = implode(' ', $definition_errors);
               $o_form->add_fld_error('title', $error_text);
               $o_form->_msg_error = $error_text;
               $o_form->add_obj('workflow_builder', 'obj-value', $this->workflow_builder_html(array_merge($data, array(
                  'workflow_key' => $o_form->get_post('workflow_key', '', 'parameter|max=80'),
                  'title' => $o_form->get_post('title', '', '*'),
                  'result_label' => $o_form->get_post('result_label', '', '*'),
                  'description' => $o_form->get_post_data('description', '', '*'),
                  'definition_json' => json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
               )), $o_form));
               return $o_form->run();
            }
            $definition_text = json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $values = array(
               'workflow_key'    => $o_form->get_post('workflow_key', '', 'parameter|min=2|max=80'),
               'title'           => $o_form->get_post('title', '', '*|min=2|max=160'),
               'result_label'    => $o_form->get_post('result_label', '', '*|min=2|max=160'),
               'description'     => $o_form->get_post_data('description', '', '*'),
               'definition_json' => $definition_text,
               'active'          => $o_form->get_post('active', 0, 'int')
            );

            $db = $this->db();
            $key = $db->escape($values['workflow_key'], $db->get_dd_server($this->dd_definition));
            $where = "workflow_key='" . $key . "'";
            if (!$is_new) $where .= ' AND id <> ' . (int)$rid;

            if ($db->count($this->dd_definition, $where) > 0) {
               $o_form->add_fld_error('workflow_key', $o_form->get_fd_message('duplicate_workflow_key'));
               $o_form->_msg_error = $o_form->get_fd_message('duplicate_workflow_key');
            } else {
               $ok = $db->save($this->dd_definition, $values, $is_new ? 0 : (int)$rid, 0, 1, 1, 1);
               $o_form->_msg_success = $o_form->get_fd_message($ok ? 'save_success' : 'save_error');
               if ($ok) {
                  $o_form->merge_data($values);
                  $o_form->add_obj('workflow_builder', 'obj-value', $this->workflow_builder_html($o_form->get_data(), $o_form));
               }
            }
         } else {
            $o_form->_msg_error = $o_form->get_fd_message('validation_error');
         }
      }

      return $o_form->run();
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

      $o_report = dbx()->get_system_obj('dbxReport');
      $o_report->init('workflow-definitions');
      $o_report->set_data_source($this->dd_definition, 'dbxWorkflow_admin|rpt-workflow-definitions-selection');
      $o_report->load_fd_messages();
      $o_report->set_action('?dbx_modul=dbxWorkflow_admin');
      $o_report->_pages = true;
      $o_report->_create_row_select = false;
      $o_report->_create_row_edit = false;
      $o_report->_create_row_delete = false;
      $o_report->_but_pagination = 5;
      $o_report->set_form_help_enabled(false);
      $o_report->_msg_info = $o_report->get_fd_message('report_info');
      $o_report->add_module_bar(
         $o_report->get_fd_message('bar_title'),
         'bi-diagram-3',
         $o_report->get_fd_message('bar_subtitle'),
         true
      );
      $o_report->add_rep(
         'bar_subtitle',
         $o_report->get_fd_message('bar_subtitle')
      );
      $o_report->add_rep(
         'bar_actions',
         '<a class="btn btn-outline-secondary btn-sm" href="?dbx_modul=dbxWorkflow_admin&dbx_run1=binds"><i class="bi bi-plug"></i> '
            . $this->h($o_report->get_fd_message('module_bindings'))
            . '</a>'
            . '<a class="btn btn-primary btn-sm" href="?dbx_modul=dbxWorkflow_admin&dbx_run1=edit_bind&rid=new"><i class="bi bi-plus-circle"></i> '
            . $this->h($o_report->get_fd_message('new_binding'))
            . '</a>'
            . '<a class="btn btn-primary btn-sm" href="?dbx_modul=dbxWorkflow_admin&dbx_run1=edit&rid=new"><i class="bi bi-plus-circle"></i> '
            . $this->h($o_report->get_fd_message('new_workflow'))
            . '</a>'
      );
      $o_report->create_selection_fields('dbxWorkflow_admin|rpt-workflow-definitions-selection');

      if ($o_report->submit()) {
         if (!$o_report->errors()) {
            $o_report->_msg_info = $o_report->get_fd_message(
               'filter_applied'
            );
         } else {
            $o_report->_msg_error = $o_report->get_fd_message(
               'validation_error'
            );
         }
      }

      $rwhere = $o_report->get_fld_val('dbx_rwhere', '', 'sqlsearch|max=64');
      $rrows = $o_report->get_fld_val('dbx_rrows', 30, 'int');
      $rpos = $o_report->get_fld_val('dbx_rpos', 0, 'int');
      $rsort = $o_report->get_fld_val('dbx_rsort', 'title', 'parameter');
      $rdesc = $o_report->get_fld_val('dbx_rdesc', 'ASC', 'parameter');

      $where = $this->report_search_where($rwhere);
      $cols = array('id', 'workflow_key', 'title', 'result_label', 'active', 'update_date');

      $o_report->_rflds = array(
         'id' => 'ID',
         'workflow_key' => $o_report->get_fd_message('column_key'),
         'title' => $o_report->get_fd_message('column_title'),
         'result_label' => $o_report->get_fd_message('column_goal'),
         'active' => $o_report->get_fd_message('column_active'),
         'update_date' => $o_report->get_fd_message('column_updated'),
         'action' => $o_report->get_fd_message('column_action')
      );
      $o_report->_rpt_format = array(
         'update_date' => 'php-datetime-usr',
         'action' => 'html',
      );
      $o_report->_rrows = $rrows;
      $o_report->_rpos = $rpos;
      $o_report->_count_all = $db->count($this->dd_definition, '');
      $o_report->_rcount = $db->count($this->dd_definition, $where);
      $o_report->_rdata = $this->decorate_rows($db->select($this->dd_definition, $where, $cols, $rsort, $rdesc, '', $rrows, $rpos));

      return $this->frame($o_report->run(), $o_report);
   }

   private function list_instances() {
      $db = $this->db();
      $o_report = dbx()->get_system_obj('dbxReport');
      $o_report->init('workflow-instances');
      $o_report->set_data_source($this->dd_instance, 'dbxWorkflow_admin|rpt-workflow-instances-selection');
      $o_report->load_fd_messages();
      $o_report->set_action('?dbx_modul=dbxWorkflow_admin&dbx_run1=instances');
      $o_report->_pages = true;
      $o_report->_create_row_select = false;
      $o_report->_create_row_edit = false;
      $o_report->_create_row_delete = false;
      $o_report->_but_pagination = 5;
      $o_report->set_form_help_enabled(false);
      $o_report->_msg_info = $o_report->get_fd_message('report_info');
      $o_report->create_selection_fields('dbxWorkflow_admin|rpt-workflow-instances-selection');

      if ($o_report->submit()) {
         if (!$o_report->errors()) {
            $o_report->_msg_info = $o_report->get_fd_message(
               'filter_applied'
            );
         } else {
            $o_report->_msg_error = $o_report->get_fd_message(
               'validation_error'
            );
         }
      }

      $rwhere = $o_report->get_fld_val('dbx_rwhere', '', 'sqlsearch|max=64');
      $rrows = $o_report->get_fld_val('dbx_rrows', 50, 'int');
      $rpos = $o_report->get_fld_val('dbx_rpos', 0, 'int');
      $rsort = $o_report->get_fld_val('dbx_rsort', 'create_date', 'parameter');
      $rdesc = $o_report->get_fld_val('dbx_rdesc', 'DESC', 'parameter');

      $where = $this->report_search_where($rwhere);
      $cols = array('id', 'create_date', 'workflow_key', 'result_label', 'status', 'current_need', 'percent', 'message');

      $o_report->_rflds = array(
         'id' => 'ID',
         'create_date' => $o_report->get_fd_message('column_start'),
         'workflow_key' => $o_report->get_fd_message('column_workflow'),
         'result_label' => $o_report->get_fd_message('column_goal'),
         'status' => $o_report->get_fd_message('column_status'),
         'current_need' => $o_report->get_fd_message('column_task'),
         'percent' => '%',
         'message' => $o_report->get_fd_message('column_message'),
         'instance_action' => $o_report->get_fd_message('column_action')
      );
      $o_report->_rpt_format = array(
         'create_date' => 'php-datetime-usr',
         'status' => 'html',
         'percent' => 'html',
         'message' => 'html',
         'instance_action' => 'html'
      );
      $o_report->_rrows = $rrows;
      $o_report->_rpos = $rpos;
      $o_report->_count_all = $db->count($this->dd_instance, '');
      $o_report->_rcount = $db->count($this->dd_instance, $where);
      $o_report->_rdata = $this->decorate_instance_rows(
         $db->select($this->dd_instance, $where, $cols, $rsort, $rdesc, '', $rrows, $rpos),
         $o_report
      );

      return $this->frame($o_report->run(), $o_report);
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
      $is_finished = in_array($status, array('finished', 'canceled'), true);
      $url = '?dbx_modul=dbxWorkflow&dbx_run1=run&iid=' . $id;
      if ($status === 'paused') {
         $url .= '&dbx_token=' . rawurlencode(dbx()->action_token('dbxWorkflow.instance.' . $id)) . '&proc_cmd=resume';
      }

      $label = $is_finished
         ? $texts->get_fd_message('action_view')
         : $texts->get_fd_message('action_continue');
      $icon = $is_finished ? 'bi-search' : 'bi-play-fill';
      $class = $is_finished ? 'btn btn-outline-primary btn-sm' : 'btn btn-primary btn-sm';
      $title = $texts->format_fd_message(
         'action_title',
         array('action' => $label, 'id' => $id)
      );

      return '<a class="' . $class . ' openWin dbx-win dbx-workflow-instance-action" href="' . $this->h($url) . '"'
         . ' data-url="' . $this->h($url) . '" data-title="' . $this->h($title) . '" data-width="82%" data-height="88%"'
         . ' title="' . $this->h($title) . '"><i class="bi ' . $icon . '"></i> ' . $this->h($label) . '</a>';
   }

   private function bind_registry() {
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

      $rows = $this->db()->select($this->dd_bind, array('id' => (int)$rid), '*', 'id', 'DESC', '', 1, 0, 0);
      return (is_array($rows) && isset($rows[0])) ? $rows[0] : array();
   }

   private function edit_bind() {
      $rid = dbx()->get_modul_var('rid', 'new', 'parameter');
      $is_new = ($rid === 'new' || (int)$rid <= 0);
      $data = $this->load_bind_row($rid);

      $o_form = dbx()->get_system_obj('dbxForm');
      $o_form->init('workflow-module-bind', 'workflow-module-bind-form');
      $o_form->set_data_source($this->dd_bind, 'dbxWorkflow|workflow-module-bind');
      $o_form->load_fd_messages();
      if ($is_new) {
         $data['title'] = $o_form->get_fd_message('default_binding_title');
         $data['description'] = $o_form->get_fd_message('default_binding_description');
      }

      if (!$data) {
         return $this->frame(
            $this->alert('warning', $o_form->get_fd_message('binding_not_found')),
            $o_form
         );
      }

      $generator_data = array(
         'gen_modul' => trim((string)dbx()->get_modul_var('gen_modul', (string)($data['modul'] ?? ''), 'parameter')),
         'gen_dd' => trim((string)dbx()->get_modul_var('gen_dd', '', 'parameter')),
      );
      $generator_dds = array();
      foreach ($this->bind_registry()->list_module_dds($generator_data['gen_modul']) as $dd_ref) {
         $generator_dds[(string)$dd_ref] = (string)$dd_ref;
      }

      // Zwei Formulare werden auf derselben Seite gerendert. Ein eigenes
      // Objekt verhindert, dass init() des Hauptformulars den Zustand des
      // Generatorformulars im global gecachten dbxForm-Objekt ueberschreibt.
      dbx()->get_system_obj('dbxForm', 'use');
      $generator_form = new \dbxForm();
      $generator_form->init('workflow-bind-generator', 'workflow-bind-generator');
      $generator_form->set_field_definition('dbxWorkflow|workflow-module-bind');
      $generator_form->load_fd_messages();
      $generator_form->set_form_help_enabled(false);
      $generator_form->set_action('?dbx_modul=dbxWorkflow_admin&dbx_run1=edit_bind&rid=' . ($is_new ? 'new' : (int)$rid));
      $generator_form->merge_data($generator_data);
      $generator_form->_msg_info = '';
      $generator_form->add_fld(
         'gen_modul',
         'text-label',
         label: $generator_form->get_fd_message('generator_module_label'),
         rules: 'parameter|min=2|max=80',
         data: array('placeholder' => 'dbxContact')
      );
      $generator_form->add_fld(
         'gen_dd',
         'select-single-label',
         label: $generator_form->get_fd_message('generator_dd_label'),
         rules: 'parameter|min=2|max=160',
         options: array('' => $generator_form->get_fd_message('generator_dd_select')) + $generator_dds
      );

      if ($generator_form->submit()) {
         if (!$generator_form->errors()) {
            $generate_modul = trim((string)$generator_form->get_post('gen_modul', '', 'parameter|min=2|max=80'));
            $generate_dd = trim((string)$generator_form->get_post('gen_dd', '', 'parameter|min=2|max=160'));
            $generated = $this->bind_registry()->generate_bind_skeleton($generate_modul, $generate_dd);
            if ($generated) {
               $data = array_merge($data, $generated);
               $generator_form->_msg_success = $generator_form->get_fd_message('generator_success');
            } else {
               $generator_form->_msg_error = $generator_form->get_fd_message('generator_error');
            }
         } else {
            $generator_form->_msg_error = $generator_form->get_fd_message('generator_validation_error');
         }
      }

      $o_form->add_rep('bar_title', $o_form->get_fd_message($is_new ? 'form_new_title' : 'form_edit_title'));
      $o_form->add_rep('bar_subtitle', $o_form->get_fd_message('form_subtitle'));
      $o_form->add_obj('bar_actions', 'obj-value',
         '<a class="btn btn-outline-secondary btn-sm" href="?dbx_modul=dbxWorkflow_admin&dbx_run1=binds"><i class="bi bi-list-ul"></i> ' . $this->h($o_form->get_fd_message('list_label')) . '</a>'
         . '<button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-save"></i> ' . $this->h($o_form->get_fd_message('save_label')) . '</button>'
      );
      $o_form->merge_data($data);
      $o_form->set_action('?dbx_modul=dbxWorkflow_admin&dbx_run1=edit_bind&rid=' . ($is_new ? 'new' : (int)$rid));
      $o_form->set_activ_id($is_new ? 0 : (int)$rid);
      $o_form->_msg_info = $o_form->get_fd_message($is_new ? 'form_new_info' : 'form_edit_info');

      $o_form->add_fld('modul');
      $o_form->add_fld('bind_key');
      $o_form->add_fld('title');
      $o_form->add_fld('description');
      $o_form->add_fld('bind_json');
      $o_form->add_fld('active');

      if ($o_form->submit()) {
         if (!$o_form->errors()) {
            $bind_json = trim((string)$o_form->get_post_data('bind_json', '', '*'));
            $decoded = json_decode($bind_json, true);
            if (!is_array($decoded)) {
               $o_form->add_fld_error('bind_json', $o_form->get_fd_message('json_invalid'));
               $o_form->_msg_error = $o_form->get_fd_message('json_invalid');
            } else {
               $values = array(
                  'modul' => $o_form->get_post('modul', '', 'parameter|min=2|max=80'),
                  'bind_key' => $o_form->get_post('bind_key', '', 'parameter|min=2|max=80'),
                  'title' => $o_form->get_post('title', '', '*|min=2|max=160'),
                  'description' => $o_form->get_post_data('description', '', '*'),
                  'bind_json' => json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                  'active' => $o_form->get_post('active', 0, 'int'),
               );

               $db = $this->db();
               $where = "modul='" . $db->escape($values['modul'], $db->get_dd_server($this->dd_bind))
                     . "' AND bind_key='" . $db->escape($values['bind_key'], $db->get_dd_server($this->dd_bind)) . "'";
               if (!$is_new) {
                  $where .= ' AND id <> ' . (int)$rid;
               }

               if ($db->count($this->dd_bind, $where) > 0) {
                  $o_form->add_fld_error('bind_key', $o_form->get_fd_message('duplicate_bind_key'));
                  $o_form->_msg_error = $o_form->get_fd_message('duplicate_bind_key');
               } else {
                  $ok = $db->save($this->dd_bind, $values, $is_new ? 0 : (int)$rid, 0, 1, 1, 1);
                  $o_form->_msg_success = $o_form->get_fd_message($ok ? 'save_success' : 'save_error');
                  if ($ok) {
                     $o_form->merge_data($values);
                  }
               }
            }
         } else {
            $o_form->_msg_error = $o_form->get_fd_message('validation_error');
         }
      }

      $o_form->add_obj('bind_generator', 'obj-value', $generator_form->run());

      return $o_form->run();
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
      $o_report = dbx()->get_system_obj('dbxReport');
      $o_report->init('workflow-module-binds');
      $o_report->set_data_source($this->dd_bind, 'dbxWorkflow|workflow-module-bind');
      $o_report->load_fd_messages();
      $o_report->set_action('?dbx_modul=dbxWorkflow_admin&dbx_run1=binds');
      $o_report->_pages = true;
      $o_report->_create_row_select = false;
      $o_report->_create_row_edit = false;
      $o_report->_create_row_delete = false;
      $o_report->_but_pagination = 5;
      $o_report->set_form_help_enabled(false);
      $o_report->_msg_info = $o_report->get_fd_message('list_info');

      $cols = array('id', 'modul', 'bind_key', 'title', 'active', 'update_date');
      $o_report->_rflds = array(
         'id' => 'ID',
         'modul' => $o_report->get_fd_message('column_module'),
         'bind_key' => $o_report->get_fd_message('column_bind_key'),
         'title' => $o_report->get_fd_message('column_title'),
         'active' => $o_report->get_fd_message('column_active'),
         'update_date' => $o_report->get_fd_message('column_update'),
         'bind_ref' => $o_report->get_fd_message('column_reference'),
         'action' => $o_report->get_fd_message('column_action'),
      );
      $o_report->_rpt_format = array(
         'update_date' => 'php-datetime-usr',
         'action' => 'html',
      );
      $o_report->_rrows = 30;
      $o_report->_rpos = 0;
      $o_report->_count_all = $db->count($this->dd_bind, '');
      $o_report->_rcount = $o_report->_count_all;
      $o_report->_rdata = $this->decorate_bind_rows($db->select($this->dd_bind, '', $cols, 'modul', 'ASC', '', 30, 0));

      return $this->frame($o_report->run(), $o_report);
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
