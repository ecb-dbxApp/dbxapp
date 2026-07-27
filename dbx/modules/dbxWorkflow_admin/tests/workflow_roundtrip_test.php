<?php

require_once dirname(__DIR__) . '/include/dbxWorkflowAdmin.class.php';
require_once dirname(__DIR__, 2) . '/dbxWorkflow/include/dbxWorkflowEngine.class.php';

class WorkflowDefinitionDbStub {
   public function select($dd, $where, $fields = '*', $order = '', $dir = '', $group = '', $limit = 0, $pos = 0, $flag = 0) {
      $key = (string)($where['workflow_key'] ?? '');
      $rows = array(
         'active_flow' => array(
            'id' => 1,
            'workflow_key' => 'active_flow',
            'title' => 'Aktiver Ablauf',
            'result_label' => 'Aktives Ergebnis',
            'description' => 'Test',
            'active' => 1,
            'definition_json' => '{"needs":[{"key":"value","label":"Wert","actions":["form"]}]}',
         ),
         'inactive_flow' => array(
            'id' => 2,
            'workflow_key' => 'inactive_flow',
            'title' => 'Inaktiver Ablauf',
            'result_label' => 'Inaktives Ergebnis',
            'description' => 'Test',
            'active' => 0,
            'definition_json' => '{"needs":[{"key":"value","label":"Wert","actions":["form"]}]}',
         ),
      );
      if (!isset($rows[$key])) return array();
      if (isset($where['active']) && (int)$where['active'] !== (int)$rows[$key]['active']) return array();
      return array($rows[$key]);
   }
}

class WorkflowDefinitionModuleStub {
   public function enrichDefinition(array $definition, array $values = array()): array {
      return $definition;
   }
}

class WorkflowDefinitionApiStub {
   public $db;
   public $module;

   public function __construct() {
      $this->db = new WorkflowDefinitionDbStub();
      $this->module = new WorkflowDefinitionModuleStub();
   }

   public function get_config($modul = 'dbx', $key = '', $default = null) {
      return $key === 'default_workflow' ? 'active_flow' : $default;
   }

   public function get_system_obj($name) {
      return $name === 'dbxDB' ? $this->db : null;
   }

   public function get_include_obj($name, $modul = '') {
      return $name === 'dbxWorkflowModule' ? $this->module : null;
   }
}

if (!function_exists('dbx')) {
   function dbx() {
      static $api;
      if (!$api) $api = new WorkflowDefinitionApiStub();
      return $api;
   }
}

class WorkflowRoundTripFormStub {
   private $values;

   public function __construct(array $values) {
      $this->values = $values;
   }

   public function get_post($name, $default = '', $rules = '') {
      return $this->values[$name] ?? $default;
   }

   public function get_post_data($name, $default = '', $rules = '') {
      return $this->values[$name] ?? $default;
   }
}

function workflow_test_assert($condition, string $message): void {
   if ($condition) return;
   fwrite(STDERR, "FEHLER: " . $message . PHP_EOL);
   exit(1);
}

$originalNeed = array(
   'key' => 'customer',
   'label' => 'Kunde',
   'kind' => 'input',
   'mode' => 'single',
   'required' => true,
   'actions' => array('create', 'select'),
   'preferred' => 'select',
   'event' => 'Alter Ereignistext',
   'resolver' => array(
      'type' => 'select',
      'label' => 'Kunde auswählen',
      'adapter' => 'customer_v2',
   ),
   'source' => array('dd' => 'dbxContact|contact'),
   'complete_label' => 'Kunde wurde im Fachformular gespeichert.',
   'future_extension' => array('keep' => true),
);

$baseDefinition = array(
   'workflow_key' => 'customer_flow',
   'title' => 'Kundenablauf',
   'result' => 'Kunde bearbeitet',
   'description' => 'Ausgangsbeschreibung',
   'schema_version' => 3,
   'custom_root' => array('owner_module' => 'dbxContact'),
   'bind_ref' => 'dbxContact|customer_flow',
   'needs' => array($originalNeed),
   'checks' => array(array(
      'key' => 'customer',
      'severity' => 'blocking',
   )),
   'finish' => array(
      'label' => 'Kunde abschließen',
      'strategy' => 'atomic',
      'custom_finish' => array('keep' => true),
   ),
);

$_POST = array(
   'workflow_step_present' => array(0 => '1'),
   'workflow_step_active' => array(0 => '1'),
   'workflow_step_original' => array(0 => json_encode($originalNeed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
   'workflow_step_action_contract' => array(0 => '1'),
   // Die Checkbox-Reihenfolge entspricht der UI, nicht der Definition. Der
   // Merge muss die bestehende Reihenfolge create/select dennoch erhalten.
   'workflow_step_actions' => array(0 => array('select', 'create')),
   'workflow_step_action' => array(0 => 'select'),
   'workflow_step_kind' => array(0 => 'input'),
   'workflow_step_automation' => array(0 => 'manual'),
   'workflow_step_mode' => array(0 => 'single'),
   'workflow_step_required' => array(0 => '1'),
   'workflow_step_label' => array(0 => 'Kunde auswählen'),
   'workflow_step_key' => array(0 => 'primary_customer'),
   'workflow_step_question' => array(0 => 'Welcher Kunde wird verwendet?'),
   'workflow_step_validation' => array(0 => 'exactly_one'),
   'workflow_step_missing_message' => array(0 => 'Kunde fehlt.'),
   'workflow_step_resolver_label' => array(0 => 'Kundenauswahl öffnen'),
   'workflow_step_hint' => array(0 => 'Vorhandenen Kunden auswählen oder neu anlegen.'),
   'workflow_step_event' => array(0 => ''),
   'workflow_step_depends_on' => array(0 => ''),
   'workflow_step_depends_value' => array(0 => ''),
   'workflow_step_options' => array(0 => ''),
   'workflow_step_complete_label' => array(0 => 'Kunde wurde im Fachformular gespeichert.'),
   'workflow_step_source' => array(0 => json_encode($originalNeed['source'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
   'workflow_step_bind' => array(0 => ''),
   'workflow_step_module_links' => array(0 => ''),
   'workflow_finish_label' => 'Kunde abschließen',
   'workflow_bind_ref' => '',
);

$form = new WorkflowRoundTripFormStub(array(
   'workflow_key' => 'customer_flow',
   'title' => 'Kundenablauf aktualisiert',
   'result_label' => 'Kunde vollständig bearbeitet',
   'description' => 'Aktualisierte Beschreibung',
));
$admin = new \dbx\dbxWorkflow_admin\dbxWorkflowAdmin();
$merge = new ReflectionMethod($admin, 'workflow_definition_from_post');
$definition = $merge->invoke($admin, $form, $baseDefinition);
$need = $definition['needs'][0];

workflow_test_assert(($definition['schema_version'] ?? null) === 3, 'Unbekanntes Top-Level-Feld wurde entfernt.');
workflow_test_assert(($definition['custom_root']['owner_module'] ?? '') === 'dbxContact', 'Top-Level-Erweiterung wurde verändert.');
workflow_test_assert(($definition['finish']['strategy'] ?? '') === 'atomic', 'Finish-Erweiterung wurde entfernt.');
workflow_test_assert(!isset($definition['bind_ref']), 'Ein sichtbar geleertes bind_ref wurde nicht entfernt.');
workflow_test_assert(($definition['checks'][0]['key'] ?? '') === 'primary_customer', 'Check-Erweiterung wurde bei einer Key-Umbenennung nicht mitgeführt.');
workflow_test_assert(($need['future_extension']['keep'] ?? false) === true, 'Unbekannte Need-Erweiterung wurde entfernt.');
workflow_test_assert(($need['resolver']['adapter'] ?? '') === 'customer_v2', 'Resolver-Erweiterung wurde entfernt.');
workflow_test_assert(($need['source']['dd'] ?? '') === 'dbxContact|contact', 'Datenquelle wurde nicht erhalten.');
workflow_test_assert(($need['complete_label'] ?? '') === 'Kunde wurde im Fachformular gespeichert.', 'complete_label wurde nicht erhalten.');
workflow_test_assert(($need['actions'] ?? array()) === array('create', 'select'), 'Mehrfachaktionen oder deren Reihenfolge wurden verändert.');
workflow_test_assert(($need['preferred'] ?? '') === 'select', 'Standardaktion wurde nicht erhalten.');
workflow_test_assert(!isset($need['event']), 'Ein sichtbar geleertes Ereignis wurde nicht entfernt.');

// Wird eine entfernte Zeile im Designer als neuer Schritt wiederverwendet,
// darf sie keine unbekannten Eigenschaften des früheren Needs erben.
$_POST['workflow_step_original'][0] = '';
$_POST['workflow_step_actions'][0] = array('form');
$_POST['workflow_step_action'][0] = 'form';
$_POST['workflow_step_label'][0] = 'Neue Notiz';
$_POST['workflow_step_key'][0] = 'new_note';
$_POST['workflow_step_complete_label'][0] = '';
$_POST['workflow_step_source'][0] = '';
$newDefinition = $merge->invoke($admin, $form, $baseDefinition);
$newNeed = $newDefinition['needs'][0];
workflow_test_assert(!isset($newNeed['future_extension']), 'Ein neuer Schritt hat Erweiterungen der wiederverwendeten alten Zeile geerbt.');
workflow_test_assert(!isset($newNeed['source']), 'Ein neuer Schritt hat die Datenquelle der wiederverwendeten alten Zeile geerbt.');
workflow_test_assert(($newNeed['actions'] ?? array()) === array('form'), 'Aktionen eines neuen Schritts wurden nicht sauber initialisiert.');

// Falls das transportierte Original-JSON beschädigt wird, dient der stabile
// Ursprungsindex als serverseitiger Fallback. Leere neue Zeilen besitzen ihn
// absichtlich nicht.
$_POST['workflow_step_original'][0] = '{ungueltig';
$_POST['workflow_step_original_index'][0] = '0';
$fallbackDefinition = $merge->invoke($admin, $form, $baseDefinition);
$fallbackNeed = $fallbackDefinition['needs'][0];
workflow_test_assert(($fallbackNeed['future_extension']['keep'] ?? false) === true, 'Serverseitiger Round-Trip-Fallback hat Need-Erweiterungen verloren.');

$engine = new \dbx\dbxWorkflow\dbxWorkflowEngine();
$normalized = $engine->normalize_definition(array(
   'workflow_key' => 'check_extensions',
   'title' => 'Check-Erweiterungen',
   'result' => 'Geprüft',
   'needs' => array(array(
      'key' => 'readiness',
      'label' => 'Bereitschaft',
      'actions' => array('form'),
      'resolver' => array('type' => 'form', 'label' => 'Prüfung öffnen'),
   )),
   'checks' => array(array(
      'key' => 'readiness',
      'severity' => 'blocking',
      'resolver' => array('telemetry_key' => 'ready-v2'),
   )),
));
$check = $normalized['checks'][0];
workflow_test_assert(($check['severity'] ?? '') === 'blocking', 'Check-Erweiterung wurde beim Ableiten entfernt.');
workflow_test_assert(($check['resolver']['telemetry_key'] ?? '') === 'ready-v2', 'Resolver-Erweiterung des Checks wurde entfernt.');
workflow_test_assert(($check['label'] ?? '') === 'Bereitschaft', 'Abgeleitetes Check-Feld wurde nicht aktualisiert.');

$applicable = new ReflectionMethod($engine, 'need_is_applicable');
workflow_test_assert($applicable->invoke($engine, array(
   'depends_on' => 'channels',
   'depends_value' => 'ebay',
), array(
   'channels' => array('shop', 'ebay'),
)) === true, 'Ein Schritt hinter einer gültigen Mehrfachauswahl bleibt gesperrt.');
workflow_test_assert($applicable->invoke($engine, array(
   'depends_on' => 'optional_note',
), array(
   'optional_note' => array('skipped' => 1),
)) === false, 'Ein übersprungener Abhängigkeitsschritt wurde als Wert behandelt.');

workflow_test_assert(($engine->load_definition('active_flow')['workflow_key'] ?? '') === 'active_flow', 'Aktive Admin-Definition wurde nicht geladen.');
workflow_test_assert($engine->load_definition('inactive_flow') === array(), 'Inaktive Definition kann einen neuen Lauf starten.');
workflow_test_assert(($engine->load_definition('inactive_flow', false)['workflow_key'] ?? '') === 'inactive_flow', 'Inaktive Definition kann für eine bestehende Instanz nicht geladen werden.');
workflow_test_assert($engine->load_definition('unknown_flow') === array(), 'Unbekannter Key wurde durch eine Fallback-Definition ersetzt.');

echo "OK: Workflow-Definitionen bleiben round-trip-sicher." . PHP_EOL;

?>
