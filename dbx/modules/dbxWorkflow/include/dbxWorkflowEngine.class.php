<?php

namespace dbx\dbxWorkflow;

require_once __DIR__ . '/dbxWorkflowValue.class.php';
require_once __DIR__ . '/dbxWorkflowDefinitionTrait.trait.php';
require_once __DIR__ . '/dbxWorkflowRuntimeTrait.trait.php';
require_once __DIR__ . '/dbxWorkflowRenderingTrait.trait.php';

/**
 * Führt Workflow-Definitionen aus und verwaltet Instanzen sowie deren Schritte.
 *
 * Definitionsmodell, Laufzeitmutationen und Darstellung sind intern getrennt;
 * die öffentliche Engine bleibt der gemeinsame Einstieg für alle Aufrufer.
 */
class dbxWorkflowEngine {

   use dbxWorkflowDefinitionTrait;
   use dbxWorkflowRuntimeTrait;
   use dbxWorkflowRenderingTrait;

   private $dd_definition = 'dbxWorkflow|workflowDefinition';
   private $dd_instance = 'dbxWorkflow|workflowInstance';
   private $dd_step = 'dbxWorkflow|workflowStep';

   private function tpl() {
      return dbx()->get_system_obj('dbxTPL');
   }

   private function db() {
      return dbx()->get_system_obj('dbxDB');
   }

   private function h($value) {
      return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
   }

   private function now() {
      return date('Y-m-d H:i:s');
   }

   private function read_json($value, $default = array()) {
      return dbxWorkflowValue::read_json($value, $default);
   }

   private function write_json($value) {
      return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
   }

   private function workflow_module() {
      return dbx()->get_include_obj('dbxWorkflowModule', 'dbxWorkflow');
   }

   private function enrich_definition(array $definition, array $values = array()) {
      return $this->workflow_module()->enrich_definition($definition, $values);
   }
}

?>
