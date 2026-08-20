<?php

declare(strict_types=1);

namespace dbx\myLKW;

/** Führt den CSV-Neuimport installationsweit höchstens einmal pro Kalendertag aus. */
final class myLKWDailyImportService {

   public function __construct(
      private object $importer,
      private string $state_file
   ) {}

   /** @return array{status:string,ok:int,inserted:int,skipped:int,message:string,date:string} */
   public function run_if_due(bool $enabled = true, ?string $today = null): array {
      $today = $today ?: date('Y-m-d');
      $result = $this->result('disabled', 1, $today, 'Der tägliche CSV-Import ist deaktiviert.');

      if (!$enabled) {
         return $result;
      }

      $state_dir = dirname($this->state_file);
      if (!is_dir($state_dir) && !mkdir($state_dir, 0775, true) && !is_dir($state_dir)) {
         return $this->result('failed', 0, $today, 'Das Statusverzeichnis konnte nicht angelegt werden.');
      }

      $lock = fopen($this->state_file . '.lock', 'c+');
      if ($lock === false) {
         return $this->result('failed', 0, $today, 'Die Importsperre konnte nicht geöffnet werden.');
      }

      try {
         if (!flock($lock, LOCK_EX)) {
            return $this->result('failed', 0, $today, 'Die Importsperre konnte nicht gesetzt werden.');
         }

         $state = $this->read_state();
         if ((string)($state['last_success_date'] ?? '') === $today) {
            return $this->result('already_run', 1, $today, 'Der tägliche CSV-Import wurde heute bereits ausgeführt.');
         }

         if (!method_exists($this->importer, 'import_data')) {
            return $this->result('failed', 0, $today, 'Der CSV-Importer stellt import_data() nicht bereit.');
         }

         $import = (array)$this->importer->import_data();
         if (empty($import['ok'])) {
            return $this->result(
               'failed',
               0,
               $today,
               (string)($import['message'] ?? 'Der tägliche CSV-Import ist fehlgeschlagen.'),
               (int)($import['inserted'] ?? 0),
               (int)($import['skipped'] ?? 0)
            );
         }

         $state = array(
            'last_success_date' => $today,
            'last_success_at' => date('Y-m-d H:i:s'),
            'inserted' => (int)($import['inserted'] ?? 0),
            'skipped' => (int)($import['skipped'] ?? 0),
         );
         $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
         if ($json === false || file_put_contents($this->state_file, $json . PHP_EOL, LOCK_EX) === false) {
            return $this->result('failed', 0, $today, 'Der erfolgreiche Import konnte nicht als ausgeführt markiert werden.');
         }

         return $this->result(
            'imported',
            1,
            $today,
            (string)($import['message'] ?? 'CSV-Import abgeschlossen.'),
            (int)($import['inserted'] ?? 0),
            (int)($import['skipped'] ?? 0)
         );
      } finally {
         flock($lock, LOCK_UN);
         fclose($lock);
      }
   }

   /** @return array<string,mixed> */
   private function read_state(): array {
      if (!is_file($this->state_file)) {
         return array();
      }
      $state = json_decode((string)file_get_contents($this->state_file), true);
      return is_array($state) ? $state : array();
   }

   /** @return array{status:string,ok:int,inserted:int,skipped:int,message:string,date:string} */
   private function result(
      string $status,
      int $ok,
      string $date,
      string $message,
      int $inserted = 0,
      int $skipped = 0
   ): array {
      return array(
         'status' => $status,
         'ok' => $ok,
         'inserted' => $inserted,
         'skipped' => $skipped,
         'message' => $message,
         'date' => $date,
      );
   }
}
