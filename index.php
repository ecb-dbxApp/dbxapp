<?php
/**
 * dbXapp
 *
 * @package           dbXapp
 * @author            Armin Leonard Braun
 * @copyright         2021-2026 dbXapp
 * @version           see VERSION
 * @par License
 * GPL-2.0-or-later
 * @par Systemvoraussetzung
 * PHP 8.x
 * @see               https://dbxapp.de Offizielle dbXapp Website
 */

$__dbx_request_started_at = microtime(true);

ob_start('ob_gzhandler');

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
   || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;

if (session_status() !== PHP_SESSION_ACTIVE) {
   ini_set('session.use_strict_mode', '1');
   ini_set('session.use_only_cookies', '1');
   ini_set('session.use_trans_sid', '0');
   $sessionScript = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
   $sessionPath = rtrim(str_replace('\\', '/', dirname($sessionScript)), '/');
   $sessionPath = ($sessionPath === '' || $sessionPath === '.') ? '/' : $sessionPath . '/';
   if (preg_match('#^/[A-Za-z0-9._~!$&\'()*+,;=:@%/-]*/$#', $sessionPath) !== 1) {
      $sessionPath = '/';
   }
   $sessionHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost'));
   $sessionHost = preg_replace('/:\d+$/', '', $sessionHost) ?: 'localhost';
   session_name(
      'DBXSESSID'
      . strtoupper(substr(hash('sha256', $sessionHost . '|' . $sessionPath), 0, 12))
   );
   session_set_cookie_params(array(
      'lifetime' => 0,
      'path' => $sessionPath,
      'domain' => '',
      'secure' => $https,
      'httponly' => true,
      'samesite' => 'Lax',
   ));
   session_start();
}

if (!defined('dbxSystem')) {
   define('dbxSystem', 'dbxWebApp');
}
if (!defined('dbxRunAsAdmin')) {
   define('dbxRunAsAdmin', 0);
}

/**
 * Bootstrap-Ausnahme: liefert den portablen Installationspfad, bevor dbxApi
 * geladen werden kann. Nach dem Bootstrap wird dbx()->get_base_dir() genutzt.
 */
function dbx_get_base_dir($cutData = 0): string {
   $path = str_replace('\\', '/', __DIR__) . '/';
   if ($cutData && str_ends_with($path, '/Data/')) {
      $path = substr($path, 0, -5);
   }
   return rtrim($path, '/') . '/';
}

/**
 * Bootstrap-Ausnahme: liefert den portablen Dateipfad vor dem API-Start.
 */
function dbx_get_file_dir(): string {
   return dbx_get_base_dir() . 'files/';
}

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED & ~E_NOTICE);
date_default_timezone_set('Europe/Berlin');
set_time_limit(600);
ini_set('max_execution_time', '600');
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$errorLogDir = rtrim(dbx_get_file_dir(), '/\\');
if (!is_dir($errorLogDir)) {
   @mkdir($errorLogDir, 0775, true);
}
ini_set('error_log', $errorLogDir . DIRECTORY_SEPARATOR . 'dbxError.log');

require dbx_get_base_dir() . 'dbx/vendor/autoload.php';
require_once dbx_get_base_dir() . 'dbx/include/dbxKernel.php';

ini_set('error_log', dbx()->error_log_file());

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
   if (!(error_reporting() & $errno)) {
      return false;
   }

   dbx()->write_php_error_log(
      dbx()->error_type((int)$errno),
      (string)$errstr,
      (string)$errfile,
      (int)$errline
   );
   return true;
});

set_exception_handler(function ($exception) {
   dbx()->write_php_error_log(
      get_class($exception),
      $exception->getMessage(),
      $exception->getFile(),
      $exception->getLine()
   );
   if (!headers_sent()) {
      http_response_code(500);
   }
});

register_shutdown_function(function () {
   $error = error_get_last();
   if (!$error) {
      return;
   }

   $fatalTypes = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR);
   if (!in_array($error['type'], $fatalTypes, true)) {
      return;
   }

   dbx()->write_php_error_log(
      dbx()->error_type((int)$error['type']),
      (string)($error['message'] ?? ''),
      (string)($error['file'] ?? ''),
      (int)($error['line'] ?? 0)
   );
});

if ((string)($_GET['dbx_modul'] ?? '') === 'dbxContent'
   && (string)($_GET['dbx_run1'] ?? '') === 'media'
   && (int)($_GET['dbx_mid'] ?? 0) > 0) {
   dbx()->get_include_obj('dbxContentMediaResponse', 'dbxContent')->serve_request();
}

dbx()->run_web_app_request();
exit;
