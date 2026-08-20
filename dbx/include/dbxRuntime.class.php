<?php

/**
 * @brief Liefert Laufzeit-, Response-Header- und Performance-Helfer des Systemkerns.
 */
class dbxRuntime {

   /** Liefert die zentrale PHP-Fehlerprotokolldatei. */
   public function error_log_file(): string {
      $dir = rtrim(dbx()->get_file_dir(), '/\\');
      if (!is_dir($dir)) {
         @mkdir($dir, 0775, true);
      }
      return $dir . DIRECTORY_SEPARATOR . 'dbxError.log';
   }

   /** Übersetzt eine PHP-Fehlernummer in ihre symbolische Bezeichnung. */
   public function error_type(int $errno): string {
      $types = array(
         E_ERROR => 'E_ERROR', E_WARNING => 'E_WARNING', E_PARSE => 'E_PARSE',
         E_NOTICE => 'E_NOTICE', E_CORE_ERROR => 'E_CORE_ERROR',
         E_CORE_WARNING => 'E_CORE_WARNING', E_COMPILE_ERROR => 'E_COMPILE_ERROR',
         E_COMPILE_WARNING => 'E_COMPILE_WARNING', E_USER_ERROR => 'E_USER_ERROR',
         E_USER_WARNING => 'E_USER_WARNING', E_USER_NOTICE => 'E_USER_NOTICE',
         E_STRICT => 'E_STRICT', E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
         E_DEPRECATED => 'E_DEPRECATED', E_USER_DEPRECATED => 'E_USER_DEPRECATED',
      );
      return $types[$errno] ?? 'E_UNKNOWN';
   }

   /** Schreibt einen PHP-Fehler mit Request-Kontext in das zentrale Protokoll. */
   public function write_php_error_log(string $type, string $message, string $file = '', int $line = 0): void {
      $log = sprintf(
         "[%s] %s: %s in %s:%d | %s %s | IP=%s%s",
         date('Y-m-d H:i:s'),
         $type,
         str_replace(array("\r", "\n"), ' ', $message),
         $file,
         $line,
         $_SERVER['REQUEST_METHOD'] ?? 'CLI',
         $_SERVER['REQUEST_URI'] ?? '',
         $_SERVER['REMOTE_ADDR'] ?? '',
         PHP_EOL
      );
      error_log($log, 3, $this->error_log_file());
   }

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
         $this->write_php_error_log(
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
         );
         return 0;
      }
   }
}
