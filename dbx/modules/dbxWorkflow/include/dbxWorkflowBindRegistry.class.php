<?php
namespace dbx\dbxWorkflow;

require_once __DIR__ . '/dbxWorkflowValue.class.php';

/**
 * Verwaltet die Bindungen zwischen Workflow-Schritten, Modulen und DD-Feldern.
 */
class dbxWorkflowBindRegistry {

   private $dd_bind = 'dbxWorkflow|workflowModuleBind';

   private function db() {
      return dbx()->get_system_obj('dbxDB');
   }

   private function write_json($value) {
      return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
   }

   public function list_module_dds($modul) {
      $modul = trim((string)$modul);
      if ($modul === '') {
         return array();
      }

      $out = array();
      $path = dbx()->os_path(dbx()->get_base_dir() . "dbx/modules/$modul/dd/");
      if (!is_dir($path)) {
         return $out;
      }

      foreach (scandir($path) as $file) {
         if (!str_ends_with($file, '.dd.php')) {
            continue;
         }
         $dd = str_replace('.dd.php', '', $file);
         if ($dd === '' || $dd === 'new') {
            continue;
         }
         $out[] = $modul . '|' . $dd;
      }

      sort($out);
      return $out;
   }

   public function detect_context_tpl($modul) {
      $modul = trim((string)$modul);
      $path = dbx()->os_path(dbx()->get_base_dir() . "dbx/modules/$modul/tpl/htm/");
      if (!is_dir($path)) {
         return '';
      }

      foreach (scandir($path) as $file) {
         if (str_ends_with($file, '-summary.htm')) {
            return $modul . '|' . str_replace('.htm', '', $file);
         }
      }

      return '';
   }

   public function dd_fields($dd_ref) {
      $o_dd = dbx()->get_system_obj('dbxDD');
      $model = $o_dd->get_dd_model($dd_ref);
      if (!$model) {
         return array();
      }

      $fields = array();
      foreach ((array)($model['fields'] ?? array()) as $field) {
         $name = (string)($field['name'] ?? '');
         if ($name === '') {
            continue;
         }
         $fields[$name] = $field;
      }

      return $fields;
   }

   public function guess_record_need_key($dd_ref) {
      $parts = explode('|', $dd_ref);
      $dd = (string)($parts[1] ?? 'record');
      $key = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $dd));
      return trim(preg_replace('/[^a-z0-9_]+/', '_', $key), '_');
   }

   public function generate_bind_skeleton($modul, $dd_ref, $bind_key = '') {
      $modul = trim((string)$modul);
      $dd_ref = trim((string)$dd_ref);
      if ($modul === '' || $dd_ref === '') {
         return array();
      }

      $fields = $this->dd_fields($dd_ref);
      $record_need = $this->guess_record_need_key($dd_ref);
      $context_tpl = $this->detect_context_tpl($modul);
      $bind_key = trim((string)$bind_key);
      if ($bind_key === '') {
         $bind_key = $record_need;
      }

      $context_fields = array('rid' => 'id');
      $summary_candidates = array('subject', 'name', 'email', 'phone', 'message', 'title', 'label');
      foreach ($summary_candidates as $name) {
         if (isset($fields[$name])) {
            $context_fields[$name] = $name;
         }
      }

      $needs = array(
         $record_need => array(
            'type' => 'dd_select',
            'where' => array('trash' => 0),
            'label' => '#{id}',
            'order_field' => 'create_date',
            'order_dir' => 'DESC',
         ),
      );

      if (isset($fields['status']) && trim((string)($fields['status']['options'] ?? '')) !== '') {
         $needs['status'] = array('type' => 'dd_field_options', 'field' => 'status');
      }

      $finish_map = array();
      if (isset($fields['status'])) {
         $finish_map['status'] = 'status';
      }
      if (isset($fields['reply_text'])) {
         $needs['reply_text'] = array('type' => 'dd_field_value', 'field' => 'reply_text');
         $finish_map['reply_text'] = 'reply_text';
      }
      if (isset($fields['reply_date'])) {
         $finish_map['reply_date'] = '@now';
      }
      if (isset($fields['reply_uid'])) {
         $finish_map['reply_uid'] = '@uid';
      }

      $bind = array(
         'modul' => $modul,
         'record' => array(
            'dd' => $dd_ref,
            'id_need' => $record_need,
            'prefill_rid' => true,
         ),
         'needs' => $needs,
         'finish' => array(
            'type' => 'dd_update',
            'map' => $finish_map,
         ),
      );

      if ($context_tpl !== '') {
         $bind['context'] = array(
            'tpl' => $context_tpl,
            'hide_on_need' => $record_need,
            'fields' => $context_fields,
         );
      }

      if (isset($fields['email']) && isset($fields['reply_text'])) {
         $mail_tpl = $modul . '|mail-contact-reply';
         $mail_file = dbx()->os_path(dbx()->get_base_dir() . "dbx/modules/$modul/tpl/htm/mail-contact-reply.htm");
         if (file_exists($mail_file)) {
            $bind['needs']['send_mail'] = array(
               'type' => 'static_select',
               'show_if_config' => array('modul' => $modul, 'key' => 'mail_on_reply', 'has' => 'mail'),
               'options' => array(
                  array('value' => '1', 'label' => 'Ja, E-Mail senden'),
                  array('value' => '0', 'label' => 'Nein, nur speichern'),
               ),
            );
            $bind['finish']['mail'] = array(
               'when_need' => 'send_mail',
               'when_value' => '1',
               'config_modul' => $modul,
               'mode_key' => 'mail_on_reply',
               'to_field' => 'email',
               'subject_tpl' => 'Antwort: {subject}',
               'body_tpl' => $mail_tpl,
               'body_vars' => array(
                  'subject' => 'subject',
                  'reply_text' => '@need:reply_text',
               ),
               'track_fields' => array(
                  'reply_mail_sent' => 1,
                  'reply_mail_sent_date' => '@now',
               ),
            );
         }
      }

      return array(
         'modul' => $modul,
         'bind_key' => $bind_key,
         'title' => 'Workflow fuer ' . $modul . ' / ' . $dd_ref,
         'description' => 'Automatisch aus DD/TPL erzeugtes Binding. Bitte Needs und Finish pruefen.',
         'bind_json' => $this->write_json($bind),
         'active' => 1,
      );
   }

   public function contact_reply_bind() {
      $bind = dbxWorkflowValue::read_json($this->generate_bind_skeleton('dbxContact', 'dbxContact|contactRequest', 'contact_reply')['bind_json'] ?? '', array());

      $bind['record'] = array(
         'dd' => 'dbxContact|contactRequest',
         'id_need' => 'contact_request',
         'prefill_rid' => true,
      );
      $bind['needs']['contact_request'] = array(
         'type' => 'dd_select',
         'where' => array('status' => 'open', 'trash' => 0),
         'label' => '#{id} - {subject} ({name})',
         'fields' => array('id', 'subject', 'name'),
         'order_field' => 'create_date',
         'order_dir' => 'DESC',
      );
      unset($bind['needs'][$this->guess_record_need_key('dbxContact|contactRequest')]);

      $bind['needs']['status'] = array('type' => 'dd_field_options', 'field' => 'status');
      $bind['needs']['customer_reply'] = array('type' => 'dd_field_value', 'field' => 'reply_text');
      unset($bind['needs']['reply_text']);

      $bind['finish']['map'] = array(
         'status' => 'status',
         'reply_text' => 'customer_reply',
         'reply_date' => '@now',
         'reply_uid' => '@uid',
      );
      if (isset($bind['finish']['mail']['body_vars'])) {
         $bind['finish']['mail']['body_vars']['reply_text'] = '@need:customer_reply';
      }

      return array(
         'modul' => 'dbxContact',
         'bind_key' => 'contact_reply',
         'title' => 'Kontaktanfrage beantworten',
         'description' => 'Binding fuer dbxContact|contactRequest: Auswahl, Status, Antwort, optional Mail.',
         'bind_json' => $this->write_json($bind),
         'active' => 1,
      );
   }

   public function seed_bind(array $record) {
      $db = $this->db();
      $modul = (string)($record['modul'] ?? '');
      $bind_key = (string)($record['bind_key'] ?? '');
      if ($modul === '' || $bind_key === '') {
         return 0;
      }

      $rows = $db->select($this->dd_bind, array('modul' => $modul, 'bind_key' => $bind_key), array('id'), 'id', 'DESC', '', 1, 0, 0);
      if (isset($rows[0]['id'])) {
         // Mitgelieferte Bindings sind nur Startwerte. Nach der ersten
         // Installation liegt die fachliche Pflege bei dbxWorkflow_admin.
         return (int)$rows[0]['id'];
      }

      return (int)$db->insert($this->dd_bind, $record, 0, 1, 1, 1);
   }

   public function seed_default_binds() {
      return $this->seed_bind($this->contact_reply_bind());
   }
}
?>
