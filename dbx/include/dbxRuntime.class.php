<?php

/**
 * Laufzeit-, Response-Header- und Performance-Helfer des Systemkerns.
 */
class dbxRuntime {

   /**
    * Liefert die aktuelle PHP-Laufzeit des Requests in Sekunden.
    */
   public function current_php_runtime(): float {
      global $dbx_run_timer;

      if (isset($dbx_run_timer['system']['time'])
          && is_numeric($dbx_run_timer['system']['time'])
          && (float)$dbx_run_timer['system']['time'] >= 0) {
         return (float)$dbx_run_timer['system']['time'];
      }

      if (isset($dbx_run_timer['system']['start_time'])
          && is_numeric($dbx_run_timer['system']['start_time'])) {
         return max(0.0, microtime(true) - (float)$dbx_run_timer['system']['start_time']);
      }

      return 0.0;
   }

   /**
    * Liefert den von dbx gemessenen Memory-Verbrauch des Requests in Bytes.
    */
   public function current_memory_bytes(): int {
      global $dbx_run_timer;

      if (isset($dbx_run_timer['system']['memory'])
          && is_numeric($dbx_run_timer['system']['memory'])
          && (float)$dbx_run_timer['system']['memory'] >= 0) {
         return (int)round((float)$dbx_run_timer['system']['memory']);
      }

      if (isset($dbx_run_timer['system']['start_memory'], $dbx_run_timer['system']['end_memory'])
          && is_numeric($dbx_run_timer['system']['start_memory'])
          && is_numeric($dbx_run_timer['system']['end_memory'])) {
         return max(
            0,
            (int)round(
               (float)$dbx_run_timer['system']['end_memory']
               - (float)$dbx_run_timer['system']['start_memory']
            )
         );
      }

      return 0;
   }

   /**
    * Sendet die Laufzeitdaten als Response-Header.
    */
   public function send_headers(): void {
      if (headers_sent()) {
         return;
      }

      $runtime = $this->current_php_runtime();
      header('X-Content-Type-Options: nosniff');
      header('Referrer-Policy: strict-origin-when-cross-origin');
      header('X-Frame-Options: SAMEORIGIN');
      header('X-DBX-PHP-Runtime: ' . number_format($runtime, 3, '.', ''));
      header(
         'Server-Timing: dbxphp;dur='
         . number_format($runtime * 1000, 3, '.', '')
         . ';desc="DBX PHP Runtime"',
         false
      );
   }

   /**
    * Gibt die gesammelte Laufzeitmessung für Debugzwecke aus.
    */
   public function debug_timer(float $max = 0): void {
      global $dbx_run_timer;

      if (!isset($dbx_run_timer['system']['time'])) {
         return;
      }

      $time = (float)$dbx_run_timer['system']['time'];
      if ($time > $max) {
         dbx()->debug('#RUN-TIMER', $dbx_run_timer);
         return;
      }

      dbx()->debug("dbx System run time=($time)");
   }

   /**
    * Übergibt die Persistenz an das Performance-Modul.
    */
   public function store_performance_timer(): int {
      try {
         $file = dbx()->os_path(
            dbx()->get_base_dir() . 'dbx/modules/dbx/include/dbxPerformanceTimer.class.php'
         );
         if (!is_file($file)) {
            return 0;
         }

         include_once $file;
         if (!class_exists('dbxPerformanceTimer')) {
            return 0;
         }

         $timer = new \dbxPerformanceTimer();
         return method_exists($timer, 'store') ? (int)$timer->store() : 0;
      } catch (\Throwable $e) {
         dbx()->write_php_error_log(
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
         );
         return 0;
      }
   }
}
