<?php

namespace dbx\dbxWorkflow;

/** Interne Komponente von dbxWorkflowEngine. */
trait dbxWorkflowRenderingTrait {

private function workflow_bar_data(string $run1, string $actions, string $title, string $subtitle = ''): array {
      $help = dbx()->get_include_obj('dbxModuleHelp', 'dbxHelp');
      if (is_object($help) && method_exists($help, 'module_bar_template_data')) {
         $data = $help->module_bar_template_data($run1, $actions, $title, 'bi-diagram-3', $subtitle, 'dbxWorkflow');
         if (is_array($data) && $data) return $data;
      }

      return array(
         'bar_class' => 'dbx-bar--module',
         'bar_title_class' => 'dbx-bar-title',
         'bar_actions_class' => 'dbx-bar-actions',
         'bar_title' => $title,
         'bar_icon' => 'bi-diagram-3',
         'bar_subtitle' => $subtitle,
         'bar_title_pre' => '',
         'bar_title_heading_attrs' => '',
         'bar_middle' => '',
         'bar_extra' => '',
         'bar_actions' => $actions,
      );
   }

private function runtime_form(int $iid, string $kind, string $template, array $replacements = array()) {
      $form = dbx()->get_system_obj('dbxForm');
      $form->init('workflow-' . $kind . '-' . max(0, $iid), $template);
      $form->set_action($this->instance_action_url(
         '?dbx_modul=dbxWorkflow&dbx_run1=run&iid=' . max(0, $iid),
         $iid
      ));
      $form->_msg_info = '';
      foreach ($replacements as $key => $value) {
         $form->add_rep((string)$key, $value);
      }
      return $form;
   }

private function unavailable_definition_message(string $workflow_key): string {
      $workflow_key = trim($workflow_key);
      $detail = $workflow_key !== '' ? ' <code>' . $this->h($workflow_key) . '</code>' : '';
      return $this->tpl()->get_tpl('dbx|alert-warning', array(
         'msg' => 'Workflow' . $detail . ' wurde nicht gefunden oder ist nicht aktiv.',
      ));
   }

private function render_options($need, $current_value = null) {
      $html = '';
      $current_values = is_array($current_value) ? array_map('strval', $current_value) : array((string)$current_value);
      foreach ((array)$need['options'] as $opt) {
         if (is_array($opt)) {
            $value = (string)($opt['value'] ?? '');
            $label = (string)($opt['label'] ?? $value);
         } else {
            $value = $label = (string)$opt;
         }
         if ($value === '') {
            continue;
         }
         $selected = in_array($value, $current_values, true) ? ' selected' : '';
         $html .= $this->tpl()->get_tpl('dbxWorkflow|workflow-option', array(
            'value' => $this->h($value),
            'label' => $this->h($label),
            'selected' => $selected
         ));
      }
      return $html;
   }

private function render_step_context($definition, $need, $values) {
      return $this->workflow_module()->render_step_context($definition, $need, $values);
   }

private function render_form_value($definition, $need, $values) {
      if (!in_array('form', (array)($need['actions'] ?? array()), true)) {
         return '';
      }

      if (array_key_exists($need['key'], $values)) {
         return $this->h($values[$need['key']]);
      }

      return $this->workflow_module()->render_form_value($definition, $need, $values);
   }

private function workflow_value_for_token($key, array $values) {
      $key = $this->normalize_key($key);
      if ($key === '' || !array_key_exists($key, $values)) {
         return '';
      }
      $value = $values[$key];
      if (is_array($value) && !empty($value['skipped'])) {
         return '';
      }
      if (is_array($value)) {
         return implode(',', array_map('strval', $value));
      }
      return (string)$value;
   }

private function resolve_workflow_text($text, array $values, $url_encode = false) {
      return preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function($match) use ($values, $url_encode) {
         $value = $this->workflow_value_for_token($match[1], $values);
         return $url_encode ? rawurlencode($value) : $value;
      }, (string)$text);
   }

private function render_module_links($need, array $values) {
      $links = '';
      foreach ((array)($need['module_links'] ?? array()) as $link) {
         if (!is_array($link)) {
            continue;
         }
         $url = trim((string)($link['url'] ?? ''));
         if ($url === '') {
            continue;
         }
         $url = $this->resolve_workflow_text($url, $values, true);
         $label = trim((string)($link['label'] ?? 'Oeffnen'));
         $title = trim((string)($link['title'] ?? $label));
         $icon = trim((string)($link['icon'] ?? 'bi-box-arrow-up-right'));
         $width = trim((string)($link['width'] ?? '86%'));
         $height = trim((string)($link['height'] ?? '86%'));
         $links .= '<a class="btn btn-outline-primary btn-sm openWin dbx-win" href="' . $this->h($url)
            . '" data-url="' . $this->h($url)
            . '" data-title="' . $this->h($this->resolve_workflow_text($title, $values, false))
            . '" data-width="' . $this->h($width)
            . '" data-height="' . $this->h($height)
            . '" title="' . $this->h($this->resolve_workflow_text($title, $values, false))
            . '"><i class="' . $this->h($icon) . '"></i> ' . $this->h($label) . '</a>';
      }
      return $links;
   }

private function render_module_block($definition, $need, array $values) {
      if (!in_array('module', (array)($need['actions'] ?? array()), true)) {
         return '';
      }

      $links = $this->render_module_links($need, $values);
      if ($links === '') {
         $links = '<div class="alert alert-warning mb-0">Fuer diese Aufgabe ist noch kein Modulformular hinterlegt.</div>';
      }

      return $this->tpl()->get_tpl('dbxWorkflow|workflow-module-block', array(
         'label' => $this->h($need['label']),
         'module_links' => $links,
         'complete_value' => $this->h($need['event'] ?: ($need['label'] . ' erledigt')),
         'complete_label' => $this->h((string)($need['complete_label'] ?? 'Aufgabe wurde im geoeffneten Formular erledigt.'))
      ));
   }

private function render_step($iid, $definition, $need, $values, $target_id) {
      $actions = (array)$need['actions'];
      $select_block = '';
      $create_block = '';
      $form_block = '';
      $module_block = '';
      $step_no = $this->completed_count($definition, $values) + 1;
      $step_count = max(1, $this->progress_total_count($definition, $values));
      $hint = (string)($need['hint'] ?? '');
      if (!empty($need['event'])) {
         $hint .= ($hint !== '' ? ' ' : '') . 'Zwischenergebnis: ' . (string)$need['event'];
      }

      if (in_array('select', $actions, true)) {
         $select_block = $this->tpl()->get_tpl('dbxWorkflow|workflow-select-block', array(
            'label' => $this->h($need['label']),
            'options' => $this->render_options($need, $values[$need['key']] ?? null),
            'select_hint' => $need['mode'] === 'multiple' ? 'Mehrfachauswahl moeglich.' : 'Eine Auswahl ist notwendig.',
            'name_suffix' => $need['mode'] === 'multiple' ? '[]' : '',
            'select_attrs' => $need['mode'] === 'multiple' ? 'multiple size="5"' : ''
         ));
      }

      if (in_array('create', $actions, true)) {
         $create_block = $this->tpl()->get_tpl('dbxWorkflow|workflow-create-block', array(
            'label' => $this->h($need['label']),
            'create_placeholder' => $this->h($need['label'] . ' neu erfassen')
         ));
      }

      if (in_array('form', $actions, true)) {
         $form_block = $this->tpl()->get_tpl('dbxWorkflow|workflow-form-block', array(
            'label' => $this->h($need['label']),
            'form_placeholder' => $this->h($need['hint'] ?: $need['label']),
            'form_value' => $this->render_form_value($definition, $need, $values)
         ));
      }

      if (in_array('module', $actions, true)) {
         $module_block = $this->render_module_block($definition, $need, $values);
      }

      $skip = !$need['required'] ? $this->tpl()->get_tpl('dbxWorkflow|workflow-skip-button') : '';
      $context_block = $this->render_step_context($definition, $need, $values);

      $data = array(
         'action' => $this->h($this->instance_action_url(
            '?dbx_modul=dbxWorkflow&dbx_run1=run&iid=' . (int)$iid,
            (int)$iid
         )),
         'target_id' => $this->h($target_id),
         'need_key' => $this->h($need['key']),
         'step_no' => $step_no,
         'step_count' => $step_count,
          'label' => $this->h($need['label']),
          'question' => $this->h($this->requirement_question($need)),
          'requirement_badge' => !empty($need['required']) ? 'Pflichtangabe' : 'Optional',
          'validation_hint' => $this->h((string)($need['missing_message'] ?? '')),
          'hint' => $this->h($hint),
         'context_block' => $context_block,
         'select_block' => $select_block,
         'create_block' => $create_block,
         'form_block' => $form_block,
         'module_block' => $module_block,
         'skip_button' => $skip
      );

      return $this->runtime_form(
         (int)$iid,
         'step',
         'workflow-step-choice',
         $data
      )->run();
   }

private function render_requirements_check($iid, $definition, $values, string $status): string {
      $items = '';
      $required_total = 0;
      $required_done = 0;

      foreach ((array)($definition['needs'] ?? array()) as $need) {
         $key = (string)($need['key'] ?? '');
         if ($key === '') continue;
         $required = !empty($need['required']);
         if ($required) $required_total++;
         $applicable = $this->need_is_applicable($need, $values);
         $done = $applicable && $this->is_done($values, $need);
         if ($required && $done) $required_done++;

         $state = 'locked';
         $state_label = 'Wartet auf Voraussetzung';
         $icon = 'bi-lock';
         if ($done) {
            $state = 'done';
            $state_label = 'Vollständig';
            $icon = 'bi-check2-circle';
         } elseif ($applicable && $required) {
            $state = 'missing';
            $state_label = 'Unvollständig';
            $icon = 'bi-exclamation-circle';
         } elseif ($applicable) {
            $state = 'optional';
            $state_label = 'Optional';
            $icon = 'bi-circle';
         }

         $resolver = $this->requirement_resolver($need);
         $action = '';
         if ($applicable && !$done && $status === 'running') {
            $url = '?dbx_modul=dbxWorkflow&dbx_run1=run&iid=' . (int)$iid . '&need=' . rawurlencode($key);
            $action = '<a class="btn btn-sm btn-outline-primary dbx-workflow-requirement-action" href="' . $this->h($url) . '"><i class="bi bi-pencil-square"></i> ' . $this->h((string)$resolver['label']) . '</a>';
         }

         $items .= '<article class="dbx-workflow-requirement is-' . $state . '">'
            . '<span class="dbx-workflow-requirement-icon"><i class="bi ' . $icon . '"></i></span>'
            . '<div class="dbx-workflow-requirement-copy"><strong>' . $this->h($this->requirement_question($need)) . '</strong>'
            . '<small>' . $this->h((string)($need['label'] ?? $key)) . ' · ' . $this->h((string)($need['missing_message'] ?? '')) . '</small></div>'
            . '<span class="dbx-workflow-requirement-state">' . $this->h($state_label) . '</span>'
            . $action
            . '</article>';
      }

      $complete = $required_total > 0 && $required_done >= $required_total;
      $summary = $complete
         ? 'Alle ' . $required_total . ' Pflichtangaben sind vollständig.'
         : $required_done . ' von ' . $required_total . ' Pflichtangaben sind vollständig.';

      return '<section class="dbx-workflow-requirements' . ($complete ? ' is-complete' : '') . '">'
         . '<header><span class="dbx-workflow-stage-number">2</span><div><small>Automatisch aus den Schritten abgeleitet</small><h3>Prüfung</h3><p>' . $this->h($summary) . '</p></div></header>'
         . '<div class="dbx-workflow-requirement-list">' . $items . '</div>'
         . '</section>';
   }

private function value_label($definition, $need, $value) {
      $formatted = $this->workflow_module()->format_value_label($definition, $need, $value);
      if ($formatted !== null) {
         return $formatted;
      }

      if (is_array($value) && !empty($value['skipped'])) return '<em>Uebersprungen</em>';
      if (is_array($value)) return $this->h(implode(', ', $value));
      return nl2br($this->h($value));
   }

private function render_review_rows($definition, $values) {
      $rows = '';
      foreach ((array)$definition['needs'] as $need) {
         if (!$this->need_is_applicable($need, $values)) continue;
         $rows .= $this->tpl()->get_tpl('dbxWorkflow|workflow-review-row', array(
            'label' => $this->h($need['label']),
            'value' => $this->value_label($definition, $need, $values[$need['key']] ?? '')
         ));
      }
      return $rows;
   }

private function missing_required_labels($definition, $values): array {
      $missing = array();
      foreach ((array)$definition['needs'] as $need) {
         if (!$this->need_is_applicable($need, $values)) {
            continue;
         }
         if (empty($need['required'])) {
            continue;
         }
         if (!$this->is_done($values, $need)) {
            $missing[] = (string)($need['label'] ?? $need['key'] ?? '');
         }
      }
      return array_values(array_filter($missing, fn($value) => trim((string)$value) !== ''));
   }

private function render_final_status($definition, $values, string $status): string {
      $completed = $this->completed_count($definition, $values);
      $total = max(1, $this->progress_total_count($definition, $values));
      $missing = $this->missing_required_labels($definition, $values);
      return $this->workflow_module()->render_final_status($definition, $values, $status, $completed, $total, $missing);
   }

private function render_review($iid, $definition, $values, $target_id) {
      return $this->runtime_form((int)$iid, 'review', 'workflow-review', array(
         'action' => $this->h($this->instance_action_url(
            '?dbx_modul=dbxWorkflow&dbx_run1=run&iid=' . (int)$iid,
            (int)$iid
         )),
         'target_id' => $this->h($target_id),
         'result_label' => $this->h($definition['result']),
         'finish_label' => $this->h($definition['finish']['label'] ?? ($definition['result'] . ' erzeugen')),
         'rows' => $this->render_review_rows($definition, $values),
         'final_status' => $this->render_final_status($definition, $values, 'running')
      ))->run();
   }

private function status_label($status) {
      $map = array(
         'running'=>'Laeuft',
         'finishing'=>'Wird abgeschlossen',
         'paused'=>'Angehalten',
         'canceled'=>'Abgebrochen',
         'finished'=>'Fertig',
         'error'=>'Fehler'
      );
      return $map[$status] ?? $status;
   }

private function status_class($status) {
      if ($status === 'finished') return 'bg-success';
      if ($status === 'paused' || $status === 'finishing') return 'bg-warning';
      if ($status === 'canceled' || $status === 'error') return 'bg-danger';
      return 'bg-primary';
   }

private function status_icon($status) {
      if ($status === 'finished') return 'bi bi-check2-circle';
      if ($status === 'paused') return 'bi bi-pause-fill';
      if ($status === 'finishing') return 'bi bi-hourglass-split';
      if ($status === 'canceled' || $status === 'error') return 'bi bi-exclamation-triangle';
      return 'bi bi-play-fill';
   }

public function render($iid, bool $allow_automations = false) {
      $iid = (int)$iid;
      $instance = $this->load_instance($iid);
      if (!$instance) {
         return $this->tpl()->get_tpl('dbx|alert-warning', array('msg' => 'Workflow-Instanz nicht gefunden.'));
      }

      $workflow_key = (string)($instance['workflow_key'] ?? '');
      // Neue Instanzen besitzen einen Definitions-Snapshot. Dadurch wirken
      // Admin-Aenderungen nur auf neue Starts. Alte Instanzen ohne Snapshot
      // verwenden fuer die Abwaertskompatibilitaet weiterhin den Datensatz.
      $snapshot = $this->read_json($instance['definition_json'] ?? '', array());
      if ($snapshot) {
         $base_definition = $this->normalize_definition($snapshot, $workflow_key);
         // Das beim Start eingebettete Binding gehoert zum Snapshot. Eine
         // spaetere Aenderung desselben bind_ref darf es nicht ersetzen.
         $base_definition['bind_ref'] = '';
      } else {
         $base_definition = $this->load_definition($workflow_key, false);
      }
      if (!$base_definition) return $this->unavailable_definition_message($workflow_key);
      $definition = $this->enrich_definition($base_definition, $this->values_from_instance($instance));
      $instance = $this->apply_command($instance, dbx()->get_modul_var('proc_cmd', '', 'parameter'));
      $instance = $this->apply_submit($instance, $definition);
      $values = $this->values_from_instance($instance);
      $definition = $this->enrich_definition($base_definition, $values);
      if ($allow_automations || $this->has_instance_action_token($iid)) {
         $instance = $this->apply_automations($instance, $definition);
      }
      $values = $this->values_from_instance($instance);
      $definition = $this->enrich_definition($base_definition, $values);
      $status = (string)($instance['status'] ?? 'running');
      $target_id = 'dbx_workflow_' . $iid;

      $step_content = '';
      if ($status === 'finished') {
         $step_content = $this->tpl()->get_tpl('dbxWorkflow|workflow-finished', array(
            'result_label' => $this->h($definition['result']),
            'rows' => $this->render_review_rows($definition, $values),
            'final_status' => $this->render_final_status($definition, $values, $status),
            'new_url' => $this->h($this->action_url(
               '?dbx_modul=dbxWorkflow&dbx_run1=start&workflow=' . rawurlencode($definition['workflow_key']),
               'dbxWorkflow.start'
            ))
         ));
      } elseif ($status === 'paused' || $status === 'finishing' || $status === 'canceled' || $status === 'error') {
         $step_content = $this->tpl()->get_tpl('dbx|alert-info', array('msg' => $instance['message'] ?? $this->status_label($status)));
      } else {
         $requested = $this->normalize_key(dbx()->get_modul_var('need', '', 'parameter'));
         $can_review = $this->completed_count($definition, $values) >= max(1, $this->applicable_count($definition, $values));
         $need = ($requested === 'review') ? array() : $this->requested_need($definition, $values);
         if (!$need && $requested !== 'review') {
            $need = $this->next_need($definition, $values);
         }
         if ($need) {
            $step_content = $this->render_step($iid, $definition, $need, $values, $target_id);
            $instance['current_need'] = $need['key'];
         } elseif ($can_review) {
            $step_content = $this->render_review($iid, $definition, $values, $target_id);
            $instance['current_need'] = 'review';
         } else {
            $need = $this->next_need($definition, $values);
            if ($need) {
               $step_content = $this->render_step($iid, $definition, $need, $values, $target_id);
               $instance['current_need'] = $need['key'];
            }
         }
      }

      $completed = $this->completed_count($definition, $values);
      $total = max(1, $this->progress_total_count($definition, $values));
      $percent = ($status === 'finished') ? 100 : (int)floor(($completed / $total) * 100);
      $message = $instance['_transient_message'] ?? ($instance['message'] ?? '');
      if ($status === 'running' && $message === '') $message = 'Naechsten Schritt ausfuellen.';
      $steps_nav = $this->render_steps_nav($iid, $definition, $values, (string)($instance['current_need'] ?? ''), $status);
      $requirements_check = $this->render_requirements_check($iid, $definition, $values, $status);

      $state_update = array(
         'current_need' => $instance['current_need'] ?? '',
         'percent' => $percent,
         'step_percent' => ($status === 'finished') ? 100 : 0,
         'message' => $message
      );
      if (isset($instance['_transient_message'])) {
         unset($state_update['message']);
      }
      foreach ($state_update as $field => $value) {
         if ((string)($instance[$field] ?? '') === (string)$value) {
            unset($state_update[$field]);
         }
      }
      if ($state_update) {
         $this->db()->update(
            $this->dd_instance,
            $state_update,
            array('id' => $iid),
            $this->instance_write_access(),
            1,
            1,
            0
         );
         $instance = array_merge($instance, $state_update);
      }

      $next_url = '?dbx_modul=dbxWorkflow&dbx_run1=run&iid=' . $iid;
      $instance_action_url = $this->instance_action_url($next_url, $iid);
      $restart_url = $instance_action_url . '&proc_cmd=restart';
      $autostart = 0;
      $interval = (int)dbx()->get_cfg('dbxWorkflow', 'step_interval');
      $step_percent = ($status === 'finished') ? 100 : 0;
      $status_badge = '<span class="badge ' . $this->h($this->status_class($status)) . '"><i class="' . $this->h($this->status_icon($status)) . '"></i> ' . $this->h($this->status_label($status)) . '</span>';
      $process_attrs = 'data-dbx="lib=process|id=' . $this->h($target_id)
         . '|url=' . $this->h($next_url)
         . '|interval=' . $interval
         . '|autostart=' . $autostart . '"'
         . ' data-process-status="' . $this->h($status) . '"'
         . ' data-process-percent="' . $percent . '"'
         . ' data-process-step-percent="' . $step_percent . '"'
         . ' data-process-next-url="' . $this->h($next_url) . '"'
         . ' data-process-pause-url="' . $this->h($instance_action_url . '&proc_cmd=pause') . '"'
         . ' data-process-resume-url="' . $this->h($instance_action_url . '&proc_cmd=resume') . '"'
         . ' data-process-continue-url="' . $this->h($instance_action_url . '&proc_cmd=continue') . '"'
         . ' data-process-cancel-url="' . $this->h($instance_action_url . '&proc_cmd=cancel') . '"'
         . ' data-process-restart-url="' . $this->h($restart_url) . '"'
         . ' data-process-autostart="' . $autostart . '"'
         . ' data-process-interval="' . $interval . '"';

      return $this->tpl()->get_tpl('dbxWorkflow|workflow-frame', array_merge(
         $this->workflow_bar_data('run', $status_badge, $this->h($definition['title']), $this->h($definition['result'])),
         array(
         'target_id' => $this->h($target_id),
         'title' => $this->h($definition['title']),
         'result_label' => $this->h($definition['result']),
         'status' => $this->h($status),
         'status_label' => $this->h($this->status_label($status)),
         'status_class' => $this->h($this->status_class($status)),
         'status_icon' => $this->h($this->status_icon($status)),
         'process_bar_class' => $this->h($status === 'finished' ? 'bg-success' : 'bg-primary'),
         'percent' => $percent,
         'step_percent' => $step_percent,
          'message' => $this->h($message),
          'requirements_check' => $requirements_check,
          'steps_nav' => $steps_nav,
         'step_content' => $step_content,
         'next_url' => $this->h($next_url),
         'pause_url' => $this->h($instance_action_url . '&proc_cmd=pause'),
         'resume_url' => $this->h($instance_action_url . '&proc_cmd=resume'),
         'continue_url' => $this->h($instance_action_url . '&proc_cmd=continue'),
         'cancel_url' => $this->h($instance_action_url . '&proc_cmd=cancel'),
         'restart_url' => $this->h($restart_url),
         'autostart' => $autostart,
         'interval' => $interval,
         'frame_id' => $this->h($target_id),
         'frame_panel_class' => 'py-3 dbx-workflow dbx-process',
         'frame_panel_attrs' => $process_attrs,
         'frame_subbar' => '',
         'frame_form_open' => '',
         'frame_form_close' => '',
         'frame_body_class' => '',
         'frame_body_head' => '',
         'frame_body_tail' => '',
         )
      ));
   }

private function requested_need($definition, $values) {
      $requested = $this->normalize_key(dbx()->get_modul_var('need', '', 'parameter'));
      if ($requested === '') {
         return array();
      }

      foreach ((array)$definition['needs'] as $need) {
         if (($need['key'] ?? '') !== $requested) {
            continue;
         }
         if (!$this->need_is_applicable($need, $values)) {
            return array();
         }
         return $need;
      }

      return array();
   }

private function render_steps_nav($iid, $definition, $values, $active_key, $status) {
      $html = '<div class="dbx-workflow-step-nav" aria-label="Workflow Schritte">';
      $pos = 0;
      $all_done = true;

      foreach ((array)$definition['needs'] as $need) {
         $pos++;
         $key = (string)($need['key'] ?? '');
         $applicable = $this->need_is_applicable($need, $values);
         $done = $applicable && $this->is_done($values, $need);
         if ($applicable && !$done) {
            $all_done = false;
         }

         $state = 'locked';
         $icon = 'bi-lock';
         $state_label = 'Gesperrt';
         if ($applicable) {
            if ($key === $active_key) {
               $state = 'active';
               $icon = 'bi-pencil-square';
               $state_label = 'Aktuell';
            } elseif ($done) {
               $state = 'done';
               $icon = 'bi-check2';
               $state_label = 'Erledigt';
            } else {
               $state = 'open';
               $icon = 'bi-circle';
               $state_label = 'Offen';
            }
         }

         $depends_on = $this->normalize_key($need['depends_on'] ?? '');
         $title = (string)($need['label'] ?? $key);
         if (!$applicable && $depends_on !== '') {
            $title .= ' - erst nach "' . $depends_on . '" moeglich';
         }

         $inner = '<span class="dbx-workflow-step-nav-no">' . $pos . '</span>'
            . '<span class="dbx-workflow-step-nav-text">'
            . '<strong>' . $this->h((string)($need['label'] ?? $key)) . '</strong>'
            . '<small><i class="bi ' . $icon . '"></i> ' . $this->h($state_label) . '</small>'
            . '</span>';

         if ($applicable && $status === 'running') {
            $url = '?dbx_modul=dbxWorkflow&dbx_run1=run&iid=' . (int)$iid . '&need=' . rawurlencode($key);
            $html .= '<a class="dbx-workflow-step-nav-item is-' . $state . '" href="' . $this->h($url) . '" title="' . $this->h($title) . '">' . $inner . '</a>';
         } else {
            $html .= '<span class="dbx-workflow-step-nav-item is-' . $state . '" data-dbx-tooltip="' . $this->h($title) . '">' . $inner . '</span>';
         }
      }

      $review_state = ($active_key === 'review') ? 'active' : ($all_done ? 'open' : 'locked');
      $review_icon = ($active_key === 'review') ? 'bi-pencil-square' : ($all_done ? 'bi-check2-circle' : 'bi-lock');
      $review_label = ($active_key === 'review') ? 'Aktuell' : ($all_done ? 'Pruefen' : 'Gesperrt');
      $review_inner = '<span class="dbx-workflow-step-nav-no"><i class="bi bi-flag"></i></span>'
         . '<span class="dbx-workflow-step-nav-text"><strong>Abschluss</strong><small><i class="bi ' . $review_icon . '"></i> ' . $this->h($review_label) . '</small></span>';
      if ($all_done && $status === 'running') {
         $url = '?dbx_modul=dbxWorkflow&dbx_run1=run&iid=' . (int)$iid . '&need=review';
         $html .= '<a class="dbx-workflow-step-nav-item is-' . $review_state . '" href="' . $this->h($url) . '" title="Workflow pruefen und abschliessen">' . $review_inner . '</a>';
      } else {
         $html .= '<span class="dbx-workflow-step-nav-item is-' . $review_state . '" data-dbx-tooltip="Workflow pruefen und abschliessen">' . $review_inner . '</span>';
      }

      return $html . '</div>';
   }

public function overview($workflow_key) {
      $definition = $this->load_definition($workflow_key);
      if (!$definition) return $this->unavailable_definition_message((string)$workflow_key);
      return $this->tpl()->get_tpl('dbxWorkflow|workflow-overview', array_merge(
         $this->workflow_bar_data('use', '', $this->h($definition['title']), 'Workflow starten'),
         array(
         'title' => $this->h($definition['title']),
         'description' => $this->h($definition['description']),
         'result_label' => $this->h($definition['result']),
         'start_url' => $this->h($this->action_url(
            '?dbx_modul=dbxWorkflow&dbx_run1=start&workflow=' . rawurlencode($definition['workflow_key']),
            'dbxWorkflow.start'
         )),
         'frame_id' => 'dbx_workflow_overview',
         'frame_panel_class' => 'dbx-workflow',
         'frame_panel_attrs' => '',
         'frame_subbar' => '',
         'frame_form_open' => '',
         'frame_form_close' => '',
         'frame_body_class' => '',
         'frame_body_head' => '',
         'frame_body_tail' => '',
         )
      ));
   }
}
