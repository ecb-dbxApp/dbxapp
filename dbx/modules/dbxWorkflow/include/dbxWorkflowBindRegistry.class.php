<?php
namespace dbx\dbxWorkflow;

class dbxWorkflowBindRegistry {

   private $ddBind = 'dbxWorkflow|workflowModuleBind';

   private function db() {
      return dbx()->get_system_obj('dbxDB');
   }

   private function read_json($value, $default = array()) {
      $value = trim((string)$value);
      if ($value === '') {
         return $default;
      }
      $data = json_decode($value, true);
      return is_array($data) ? $data : $default;
   }

   private function write_json($value) {
      return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
   }

   public function listModuleDds($modul) {
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

   public function detectContextTpl($modul) {
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

   public function ddFields($ddRef) {
      $oDD = dbx()->get_system_obj('dbxDD');
      $model = $oDD->get_dd_model($ddRef);
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

   public function guessRecordNeedKey($ddRef) {
      $parts = explode('|', $ddRef);
      $dd = (string)($parts[1] ?? 'record');
      $key = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $dd));
      return trim(preg_replace('/[^a-z0-9_]+/', '_', $key), '_');
   }

   public function generateBindSkeleton($modul, $ddRef, $bindKey = '') {
      $modul = trim((string)$modul);
      $ddRef = trim((string)$ddRef);
      if ($modul === '' || $ddRef === '') {
         return array();
      }

      $fields = $this->ddFields($ddRef);
      $recordNeed = $this->guessRecordNeedKey($ddRef);
      $contextTpl = $this->detectContextTpl($modul);
      $bindKey = trim((string)$bindKey);
      if ($bindKey === '') {
         $bindKey = $recordNeed;
      }

      $contextFields = array('rid' => 'id');
      $summaryCandidates = array('subject', 'name', 'email', 'phone', 'message', 'title', 'label');
      foreach ($summaryCandidates as $name) {
         if (isset($fields[$name])) {
            $contextFields[$name] = $name;
         }
      }

      $needs = array(
         $recordNeed => array(
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

      $finishMap = array();
      if (isset($fields['status'])) {
         $finishMap['status'] = 'status';
      }
      if (isset($fields['reply_text'])) {
         $needs['reply_text'] = array('type' => 'dd_field_value', 'field' => 'reply_text');
         $finishMap['reply_text'] = 'reply_text';
      }
      if (isset($fields['reply_date'])) {
         $finishMap['reply_date'] = '@now';
      }
      if (isset($fields['reply_uid'])) {
         $finishMap['reply_uid'] = '@uid';
      }

      $bind = array(
         'modul' => $modul,
         'record' => array(
            'dd' => $ddRef,
            'id_need' => $recordNeed,
            'prefill_rid' => true,
         ),
         'needs' => $needs,
         'finish' => array(
            'type' => 'dd_update',
            'map' => $finishMap,
         ),
      );

      if ($contextTpl !== '') {
         $bind['context'] = array(
            'tpl' => $contextTpl,
            'hide_on_need' => $recordNeed,
            'fields' => $contextFields,
         );
      }

      if (isset($fields['email']) && isset($fields['reply_text'])) {
         $mailTpl = $modul . '|mail-contact-reply';
         $mailFile = dbx()->os_path(dbx()->get_base_dir() . "dbx/modules/$modul/tpl/htm/mail-contact-reply.htm");
         if (file_exists($mailFile)) {
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
               'body_tpl' => $mailTpl,
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
         'bind_key' => $bindKey,
         'title' => 'Workflow fuer ' . $modul . ' / ' . $ddRef,
         'description' => 'Automatisch aus DD/TPL erzeugtes Binding. Bitte Needs und Finish pruefen.',
         'bind_json' => $this->write_json($bind),
         'active' => 1,
      );
   }

   public function contactReplyBind() {
      $bind = $this->read_json($this->generateBindSkeleton('dbxContact', 'dbxContact|contactRequest', 'contact_reply')['bind_json'] ?? '', array());

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
      unset($bind['needs'][$this->guessRecordNeedKey('dbxContact|contactRequest')]);

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

   public function seedBind(array $record) {
      $db = $this->db();
      $modul = (string)($record['modul'] ?? '');
      $bindKey = (string)($record['bind_key'] ?? '');
      if ($modul === '' || $bindKey === '') {
         return 0;
      }

      $rows = $db->select($this->ddBind, array('modul' => $modul, 'bind_key' => $bindKey), array('id'), 'id', 'DESC', '', 1, 0, 0);
      if (isset($rows[0]['id'])) {
         // Mitgelieferte Bindings sind nur Startwerte. Nach der ersten
         // Installation liegt die fachliche Pflege bei dbxWorkflow_admin.
         return (int)$rows[0]['id'];
      }

      return (int)$db->insert($this->ddBind, $record, 0, 1, 1, 1);
   }

   public function seedDefaultBinds() {
      return $this->seedBind($this->contactReplyBind());
   }
}
?>
