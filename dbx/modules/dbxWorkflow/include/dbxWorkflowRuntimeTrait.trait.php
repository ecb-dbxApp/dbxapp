<?php

namespace dbx\dbxWorkflow;

/** Interne Komponente von dbxWorkflowEngine. */
trait dbxWorkflowRuntimeTrait {

private function instance_action_scope(int $iid): string {
      return 'dbxWorkflow.instance.' . max(0, $iid);
   }

private function action_url(string $url, string $scope): string {
      $separator = strpos($url, '?') === false ? '?' : '&';
      return $url . $separator . 'dbx_token=' . rawurlencode(dbx()->action_token($scope));
   }

private function instance_action_url(string $url, int $iid): string {
      return $this->action_url($url, $this->instance_action_scope($iid));
   }

private function has_instance_action_token(int $iid): bool {
      $token = (string)dbx()->get_modul_var('dbx_token', '', 'varchar|max=128');
      return dbx()->check_action_token($this->instance_action_scope($iid), $token);
   }

private function register_guest_instance(int $iid): void {
      if ($iid <= 0 || (int)dbx()->user() > 0) return;
      $ids = dbx()->get_session_var('instance_ids', array(), 'access', 'dbxWorkflow');
      $ids = is_array($ids) ? array_map('intval', $ids) : array();
      $ids[] = $iid;
      $ids = array_slice(array_values(array_unique(array_filter($ids))), -100);
      dbx()->set_session_var('instance_ids', $ids, 'access', 'dbxWorkflow');
   }

private function guest_can_access_instance(int $iid): bool {
      if ($iid <= 0 || (int)dbx()->user() > 0) return false;
      $ids = dbx()->get_session_var('instance_ids', array(), 'access', 'dbxWorkflow');
      return is_array($ids) && in_array($iid, array_map('intval', $ids), true);
   }

private function instance_write_access(): int {
      return (int)dbx()->user() > 0 ? 1 : 0;
   }

public function start($workflow_key) {
      $definition = $this->load_definition($workflow_key);
      if (!$definition) return $this->unavailable_definition_message((string)$workflow_key);
      $start_token = (string)dbx()->get_modul_var('dbx_token', '', 'varchar|max=128');
      if (!dbx()->check_action_token('dbxWorkflow.start', $start_token)) {
         // Alte Direktlinks bleiben aufrufbar, mutieren aber nicht mehr blind:
         // Die Uebersicht liefert einen frischen, bestaetigbaren Startlink.
         return $this->overview((string)$workflow_key);
      }

      $uid = (int)dbx()->user();
      $values = $this->workflow_module()->prefill_start(
         $definition,
         (int)dbx()->get_modul_var('rid', 0, 'int')
      );

      $record = array(
         'workflow_key' => $definition['workflow_key'],
         'result_label' => $definition['result'],
         'status' => 'running',
         'current_need' => '',
         'percent' => 0,
         'step_percent' => 0,
         'message' => 'Workflow gestartet.',
         'definition_json' => $this->write_json($definition),
         'data_json' => $this->write_json($values),
         'owner' => $uid,
      );

      $db = $this->db();
      $iid = ($db->insert($this->dd_instance, $record, 1, 1, 1, 1) === 1) ? (int)$db->get_insert_id() : 0;
      if ($iid <= 0) {
         return $this->tpl()->get_tpl('dbx|alert-warning', array('msg' => 'Workflow konnte nicht gestartet werden.'));
      }
      $this->register_guest_instance($iid);
      return $this->render($iid, true);
   }

private function load_instance($iid) {
      $iid = (int)$iid;
      if ($iid <= 0) return array();

      if ((int)dbx()->user() > 0) {
         // DD liefert fuer normale Nutzer automatisch den Owner-Filter und
         // fuer Administratoren den vollen, konfigurierten Zugriff.
         $rows = $this->db()->select($this->dd_instance, array('id' => $iid), '*', 'id', 'DESC', '', 1, 0, 1);
      } else {
         if (!$this->guest_can_access_instance($iid)) return array();
         $rows = $this->db()->select($this->dd_instance, array('id' => $iid, 'owner' => 0), '*', 'id', 'DESC', '', 1, 0, 0);
      }

      return (is_array($rows) && isset($rows[0])) ? $rows[0] : array();
   }

private function values_from_instance($instance) {
      return $this->read_json($instance['data_json'] ?? '', array());
   }

private function validate_need_value($need, $value): bool {
      if (is_array($value) && !empty($value['skipped'])) return empty($need['required']);

      $rule = $this->normalize_validation_rule($need['validation'] ?? '', $need['mode'] ?? 'single');
      $items = is_array($value)
         ? array_values(array_filter($value, fn($item) => trim((string)$item) !== ''))
         : (trim((string)$value) !== '' ? array($value) : array());

      $allowed = array();
      foreach ((array)($need['options'] ?? array()) as $option) {
         $option_value = is_array($option) ? (string)($option['value'] ?? '') : (string)$option;
         if ($option_value !== '') $allowed[] = $option_value;
      }
      $only_allowed_values = function(array $selected) use ($allowed): bool {
         if (!$allowed) return true;
         foreach ($selected as $item) {
            if (!in_array((string)$item, $allowed, true)) return false;
         }
         return true;
      };
      $actions = (array)($need['actions'] ?? array());
      $enforce_allowed = in_array('select', $actions, true)
         && !array_intersect($actions, array('create', 'form', 'module'));

      if ($rule === 'positive_integer') {
         return count($items) === 1 && filter_var($items[0], FILTER_VALIDATE_INT) !== false && (int)$items[0] > 0;
      }
      if ($rule === 'confirmed') {
         if (count($items) !== 1) return false;
         return in_array(strtolower(trim((string)$items[0])), array('1', 'true', 'yes', 'ja', 'ok', 'confirmed', 'bestaetigt', 'bestätigt'), true);
      }
      if ($rule === 'exactly_one') return count($items) === 1 && (!$enforce_allowed || $only_allowed_values($items));
      if ($rule === 'at_least_one') return count($items) >= 1 && (!$enforce_allowed || $only_allowed_values($items));
      return count($items) >= 1;
   }

private function is_done($values, $need) {
      $key = $need['key'];
      if (!array_key_exists($key, $values)) return false;
      return $this->validate_need_value($need, $values[$key]);
   }

private function value_matches($actual, $expected) {
      $expected = trim((string)$expected);
      if ($expected === '') return true;
      if (is_array($actual) && !empty($actual['skipped'])) return false;
      if (is_array($actual)) {
         foreach ($actual as $item) {
            if (trim((string)$item) === $expected) return true;
         }
         return false;
      }
      return trim((string)$actual) === $expected;
   }

private function dependency_has_value(string $key, array $values): bool {
      if ($key === '' || !array_key_exists($key, $values)) return false;
      $value = $values[$key];
      if (is_array($value) && !empty($value['skipped'])) return false;
      if (is_array($value)) {
         return count(array_filter($value, fn($item) => trim((string)$item) !== '')) > 0;
      }
      return trim((string)$value) !== '';
   }

private function need_is_applicable($need, $values) {
      $depends_on = $this->normalize_key($need['depends_on'] ?? '');
      if ($depends_on === '') return true;
      if (!$this->dependency_has_value($depends_on, $values)) return false;
      return $this->value_matches($values[$depends_on], $need['depends_value'] ?? '');
   }

private function next_need($definition, $values) {
      foreach ((array)$definition['needs'] as $need) {
         if (!$this->need_is_applicable($need, $values)) continue;
         if (!$this->is_done($values, $need)) return $need;
      }
      return array();
   }

private function completed_count($definition, $values) {
      $count = 0;
      foreach ((array)$definition['needs'] as $need) {
         if (!$this->need_is_applicable($need, $values)) continue;
         if ($this->is_done($values, $need)) $count++;
      }
      return $count;
   }

private function applicable_count($definition, $values) {
      $count = 0;
      foreach ((array)$definition['needs'] as $need) {
         if ($this->need_is_applicable($need, $values)) $count++;
      }
      return $count;
   }

private function progress_total_count($definition, $values) {
      $count = 0;
      foreach ((array)$definition['needs'] as $need) {
         $depends_on = $this->normalize_key($need['depends_on'] ?? '');
         if ($depends_on === '') {
            $count++;
            continue;
         }
         if (!$this->dependency_has_value($depends_on, $values)) {
            $count++;
            continue;
         }
         if ($this->value_matches($values[$depends_on], $need['depends_value'] ?? '')) $count++;
      }
      return $count;
   }

private function apply_command($instance, $cmd) {
      $cmd = strtolower(trim((string)$cmd));
      if (!$cmd) return $instance;

      $iid = (int)($instance['id'] ?? 0);
      if (!$this->has_instance_action_token($iid)) {
         $instance['_transient_message'] = 'Aktion nicht ausgefuehrt: ungueltiges oder abgelaufenes Aktionstoken.';
         return $instance;
      }

      $values = $this->values_from_instance($instance);
      $update = array();

      if ($cmd === 'pause') {
         $update = array('status' => 'paused', 'message' => 'Workflow angehalten.');
      } elseif ($cmd === 'resume' || $cmd === 'continue') {
         $update = array('status' => 'running', 'message' => 'Workflow fortgesetzt.');
      } elseif ($cmd === 'cancel') {
         $update = array('status' => 'canceled', 'message' => 'Workflow abgebrochen.');
      } elseif ($cmd === 'restart') {
         $values = array();
         $update = array('status' => 'running', 'message' => 'Workflow neu gestartet.', 'data_json' => $this->write_json($values), 'percent' => 0, 'step_percent' => 0);
      }

      if ($update) {
         $ok = $this->db()->update(
            $this->dd_instance,
            $update,
            array('id' => $iid),
            $this->instance_write_access(),
            1,
            1,
            1
         );
         if ($ok === 1) {
            $instance = array_merge($instance, $update);
         } else {
            $instance['message'] = 'Workflow-Aktion konnte nicht gespeichert werden.';
         }
      }

      return $instance;
   }

private function post_value($need) {
      $value = '';

      if (isset($_POST['workflow_skip']) && !$need['required']) {
         return array('skipped' => 1, 'label' => 'Uebersprungen');
      }

      $created = trim((string)($_POST['created_value'] ?? ''));
      if ($created !== '') return $created;

      if (isset($_POST['form_value'])) {
         return trim((string)$_POST['form_value']);
      }

      if (isset($_POST['selected_value'])) {
         $value = $_POST['selected_value'];
         if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value), 'strlen'));
         }
         return trim((string)$value);
      }

      return '';
   }

private function apply_submit($instance, $definition) {
      if (empty($_POST['workflow_submit']) && empty($_POST['workflow_finish'])) return $instance;
      if (($instance['status'] ?? '') !== 'running') return $instance;

      $iid = (int)($instance['id'] ?? 0);
      $is_finish = !empty($_POST['workflow_finish']);
      $form = $this->runtime_form(
         $iid,
         $is_finish ? 'review' : 'step',
         $is_finish ? 'workflow-review' : 'workflow-step-choice'
      );
      if (!$form->submit()) {
         $instance['message'] = 'Ungueltiger oder abgelaufener Formular-Token.';
         return $instance;
      }
      if (!$this->has_instance_action_token($iid)) {
         $instance['message'] = 'Ungueltiger oder abgelaufener Aktions-Token.';
         return $instance;
      }

      $values = $this->values_from_instance($instance);

      if (!empty($_POST['workflow_finish'])) {
         $missing = $this->missing_required_labels($definition, $values);
         if ($missing) {
            $instance['message'] = 'Workflow unvollständig. Es fehlen: ' . implode(', ', $missing) . '.';
            return $instance;
         }

         // Der Abschluss kann Modulupdates oder E-Mail-Versand ausloesen.
         // Vor dem externen Effekt wird die Instanz daher atomar beansprucht.
         // Ein paralleler oder wiederholter Request sieht "finishing" und
         // fuehrt applyFinish() nicht ein zweites Mal aus.
         $db = $this->db();
         if ($db->begin($this->dd_instance) !== 1) {
            $instance['message'] = 'Workflow-Abschluss konnte nicht atomar gestartet werden.';
            return $instance;
         }
         $claim = $db->update(
            $this->dd_instance,
            array('status' => 'finishing', 'message' => 'Workflow wird abgeschlossen.'),
            array('id' => $iid, 'status' => 'running'),
            $this->instance_write_access(),
            1,
            1,
            1
         );
         if ($claim !== 1 || (int)$db->_update_count !== 1 || $db->commit($this->dd_instance) !== 1) {
            $db->rollback($this->dd_instance);
            $current = $this->load_instance($iid);
            return $current ?: array_merge($instance, array('message' => 'Workflow wird bereits abgeschlossen.'));
         }
         $instance['status'] = 'finishing';
         $instance['message'] = 'Workflow wird abgeschlossen.';

         $finish_message = 'Workflow abgeschlossen.';
         try {
            $module_result = $this->workflow_module()->apply_finish($definition, $values);
         } catch (\Throwable $e) {
            dbx()->debug('#Workflow finish failed iid=(' . $iid . ') error=(' . $e->getMessage() . ')');
            $error_update = array(
               'status' => 'error',
               'message' => 'Workflow-Abschluss ist fehlgeschlagen und muss geprueft werden.',
            );
            $db->update($this->dd_instance, $error_update, array('id' => $iid), $this->instance_write_access(), 1, 1, 1);
            return array_merge($instance, $error_update);
         }
         if (is_array($module_result)) {
            if (empty($module_result['ok'])) {
               $failed_update = array(
                  'status' => 'running',
                  'message' => $module_result['message'] ?? 'Workflow konnte nicht abgeschlossen werden.',
               );
               $db->update($this->dd_instance, $failed_update, array('id' => $iid), $this->instance_write_access(), 1, 1, 1);
               return array_merge($instance, $failed_update);
            }
            $finish_message = (string)($module_result['message'] ?? $finish_message);
         }

         $update = array(
            'status' => 'finished',
            'percent' => 100,
            'step_percent' => 100,
            'current_need' => '',
            'message' => $finish_message,
            'data_json' => $this->write_json($values)
         );
         if ($db->update($this->dd_instance, $update, array('id' => $iid), $this->instance_write_access(), 1, 1, 1) !== 1) {
            $instance['message'] = 'Abschluss wurde ausgefuehrt; der Instanzstatus muss administrativ geprueft werden.';
            return $instance;
         }
         return array_merge($instance, $update);
      }

      $need_key = $this->normalize_key($_POST['need_key'] ?? '');
      $need = array();
      foreach ((array)$definition['needs'] as $candidate) {
         if ($candidate['key'] === $need_key) {
            $need = $candidate;
            break;
         }
      }

      if (!$need) return $instance;
      $value = $this->post_value($need);

      if (!$this->validate_need_value($need, $value)) {
         $instance['message'] = (string)($need['missing_message'] ?? ('Bitte einen gültigen Wert für „' . $need['label'] . '“ eintragen.'));
         return $instance;
      }

      $values[$need['key']] = $value;
      $step_no = $this->completed_count($definition, $values);
      $step_record = array(
         'instance_id' => (int)$instance['id'],
         'step_pos' => $step_no,
         'need_key' => $need['key'],
         'action' => !empty($_POST['workflow_skip']) ? 'skip' : 'set',
         'status' => 'finished',
         'value_json' => $this->write_json($value),
         'message' => $need['label'] . ' erfasst.',
         'owner' => (int)dbx()->user()
      );

      $total = max(1, $this->progress_total_count($definition, $values));
      $percent = (int)floor(($step_no / $total) * 100);
      $update = array(
         'data_json' => $this->write_json($values),
         'percent' => $percent,
         'step_percent' => 100,
         'message' => $need['label'] . ' wurde uebernommen.'
      );

      $db = $this->db();
      if ($db->begin($this->dd_instance) !== 1) {
         $instance['message'] = 'Workflow-Schritt konnte nicht atomar gespeichert werden.';
         return $instance;
      }

      try {
         // Browser-Wiederholung desselben POSTs erzeugt keinen zweiten Step.
         $step_access = (int)dbx()->user() > 0 ? 1 : 0;
         $previous = $db->select(
            $this->dd_step,
            array('instance_id' => $iid, 'need_key' => $need['key'], 'status' => 'finished'),
            '*',
            'id',
            'DESC',
            '',
            1,
            0,
            $step_access
         );
         $same_step = is_array($previous)
            && isset($previous[0])
            && (string)($previous[0]['value_json'] ?? '') === (string)$step_record['value_json'];

         if (!$same_step && $db->insert($this->dd_step, $step_record, 1, 1, 1, 1) !== 1) {
            throw new \RuntimeException('step_insert_failed');
         }
         if ($db->update(
            $this->dd_instance,
            $update,
            array('id' => $iid, 'status' => 'running'),
            $this->instance_write_access(),
            1,
            1,
            1
         ) !== 1) {
            throw new \RuntimeException('instance_update_failed');
         }
         if ($db->commit($this->dd_instance) !== 1) {
            throw new \RuntimeException('commit_failed');
         }
      } catch (\Throwable $e) {
         $db->rollback($this->dd_instance);
         dbx()->debug('#Workflow step failed iid=(' . $iid . ') error=(' . $e->getMessage() . ')');
         $instance['message'] = 'Workflow-Schritt konnte nicht gespeichert werden.';
         return $instance;
      }

      return array_merge($instance, $update);
   }

private function apply_automations(array $instance, array $definition): array {
      if (($instance['status'] ?? '') !== 'running') return $instance;

      $values = $this->values_from_instance($instance);
      $messages = array();
      $step_records = array();
      $limit = max(1, count((array)($definition['needs'] ?? array())));

      for ($i = 0; $i < $limit; $i++) {
         $need = $this->next_need($definition, $values);
         if (!$need || (string)($need['automation'] ?? 'manual') !== 'observe') break;

         $result = $this->workflow_module()->automate_need($definition, $need, $values);
         if (!is_array($result) || !array_key_exists('value', $result)) break;
         $value = $result['value'];
         if ((is_array($value) && !$value) || (!is_array($value) && trim((string)$value) === '')) break;

         $values[$need['key']] = $value;
         $step_no = $this->completed_count($definition, $values);
         $message = trim((string)($result['message'] ?? ($need['label'] . ' automatisch geprüft.')));
         $messages[] = $message;
         $step_records[] = array(
            'instance_id' => (int)$instance['id'],
            'step_pos' => $step_no,
            'need_key' => $need['key'],
            'action' => 'automation',
            'status' => 'finished',
            'value_json' => $this->write_json($value),
            'message' => $message,
            'owner' => (int)dbx()->user()
         );
      }

      if (!$messages) return $instance;

      $completed = $this->completed_count($definition, $values);
      $total = max(1, $this->progress_total_count($definition, $values));
      $update = array(
         'data_json' => $this->write_json($values),
         'percent' => (int)floor(($completed / $total) * 100),
         'step_percent' => 100,
         'message' => implode(' ', $messages),
      );

      $db = $this->db();
      if ($db->begin($this->dd_instance) !== 1) {
         $instance['message'] = 'Automatische Workflow-Schritte konnten nicht atomar gespeichert werden.';
         return $instance;
      }
      try {
         foreach ($step_records as $step_record) {
            if ($db->insert($this->dd_step, $step_record, 1, 1, 1, 1) !== 1) {
               throw new \RuntimeException('automation_step_insert_failed');
            }
         }
         if ($db->update(
            $this->dd_instance,
            $update,
            array('id' => (int)$instance['id'], 'status' => 'running'),
            $this->instance_write_access(),
            1,
            1,
            1
         ) !== 1) {
            throw new \RuntimeException('automation_instance_update_failed');
         }
         if ($db->commit($this->dd_instance) !== 1) {
            throw new \RuntimeException('automation_commit_failed');
         }
      } catch (\Throwable $e) {
         $db->rollback($this->dd_instance);
         dbx()->debug('#Workflow automation failed iid=(' . (int)$instance['id'] . ') error=(' . $e->getMessage() . ')');
         $instance['message'] = 'Automatische Workflow-Schritte konnten nicht gespeichert werden.';
         return $instance;
      }

      return array_merge($instance, $update);
   }
}
