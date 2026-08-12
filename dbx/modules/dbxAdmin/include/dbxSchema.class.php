<?php
namespace dbx\dbxAdmin;

dbx()->get_system_obj('dbxReport', 'use');

require_once __DIR__ . '/dbxSchemaCoreService.trait.php';
require_once __DIR__ . '/dbxSchemaDdFieldService.trait.php';
require_once __DIR__ . '/dbxSchemaDataReportService.trait.php';
require_once __DIR__ . '/dbxSchemaMetaService.trait.php';
require_once __DIR__ . '/dbxSchemaMappingService.trait.php';
require_once __DIR__ . '/dbxSchemaReportService.trait.php';
require_once __DIR__ . '/dbxSchemaBackupService.trait.php';
require_once __DIR__ . '/dbxSchemaTransferService.trait.php';
require_once __DIR__ . '/dbxSchemaBatchService.trait.php';

/**
 * DBX schema administration.
 *
 * Diese Klasse stellt die DBX-Admin-Werkzeuge fuer DDs, Datenbanken,
 * Tabellen, Felddefinitionen, Mapping, Synchronisation, Transfer,
 * Backup, Restore und Batch-Prozesse bereit.
 *
 * Zentrale Gold-Standard-Regeln in dieser Version:
 * - DDs werden aus allen Modulen gelesen.
 * - DD-zu-DB-Zuordnung erfolgt ueber table.server und table.table.
 * - Modul-DB-Dateien bleiben ihrem echten Modul zugeordnet.
 * - sys ist nur das Pseudo-Modul fuer Config-DB-Server.
 * - DB-Server ohne Tabellen bleiben sichtbar.
 * - DB-zu-DD-Suche verwendet durchgaengig dieselbe Alias-Logik.
 *
 * @package dbx\dbxAdmin
 */
class dbxSchema extends \dbxObj {

   use dbxSchemaCoreServiceTrait;
   use dbxSchemaDdFieldServiceTrait;
   use dbxSchemaDataReportServiceTrait;
   use dbxSchemaMetaServiceTrait;
   use dbxSchemaMappingServiceTrait;
   use dbxSchemaReportServiceTrait;
   use dbxSchemaBackupServiceTrait;
   use dbxSchemaTransferServiceTrait;
   use dbxSchemaBatchServiceTrait;

   private $schemaTexts;

   /**
    * Zentraler Einstiegspunkt des dbxSchema-Moduls.
    *
    * @param string $mode Eingabeparameter fuer diese Methode.
    * @return string
    */
   public function run($mode = '') {
      $run2 = dbx()->get_modul_var('dbx_run2', '', 'parameter');
      if (!$mode) {
         $mode = dbx()->get_modul_var('dbx_run1', 'dd', 'parameter');
      }
      $action = $run2 !== '' ? (string)$run2 : ($mode === 'db' ? 'report_db' : 'report_dd');
      $definition = dbx()->get_system_obj('dbxActionManifest')
         ->action('dbxAdmin', $action, 'schema-actions');
      if (!is_array($definition)) {
         return $this->report_dd();
      }
      $handler = (string)$definition['handler'];
      if (!method_exists($this, $handler)) {
         throw new \LogicException('Schema-Handler fehlt: ' . $action);
      }
      return $this->{$handler}();
   }

}
?>