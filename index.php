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

// Cookie-lose Gastseiten koennen aus dem Page-Cache bedient werden, bevor
// Composer, Kernel und Session geladen werden. Jeder unklare Request laeuft
// unveraendert durch den regulaeren Bootstrap.
require_once __DIR__ . '/dbx/modules/dbxContent/include/dbxEarlyPageCache.class.php';
\dbx\dbxContent\dbxEarlyPageCache::try_serve(__DIR__);

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
   || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;

/**
 * Oeffentliche Crawler-Endpunkte benoetigen weder Benutzerzustand noch ein
 * Session-Cookie. Die Erkennung muss vor session_start() erfolgen, damit PHP
 * keine privaten No-Cache-Header und keine ungenutzte DBXSESSID ausliefert.
 */
function dbx_is_public_crawler_endpoint(): bool {
   $request_path = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
   $request_file = strtolower((string)basename(rtrim($request_path, '/')));
   if (in_array($request_file, array('sitemap.xml', 'robots.txt'), true)) {
      return true;
   }

   $module = strtolower(trim((string)($_GET['dbx_modul'] ?? '')));
   $action = strtolower(trim((string)($_GET['dbx_run1'] ?? '')));
   return $module === 'dbxcontent' && in_array($action, array('sitemap', 'robots'), true);
}

$dbx_public_crawler_endpoint = dbx_is_public_crawler_endpoint();

if (!$dbx_public_crawler_endpoint && session_status() !== PHP_SESSION_ACTIVE) {
   ini_set('session.use_strict_mode', '1');
   ini_set('session.use_only_cookies', '1');
   ini_set('session.use_trans_sid', '0');
   $session_script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
   $session_path = rtrim(str_replace('\\', '/', dirname($session_script)), '/');
   $session_path = ($session_path === '' || $session_path === '.') ? '/' : $session_path . '/';
   if (preg_match('#^/[A-Za-z0-9._~!$&\'()*+,;=:@%/-]*/$#', $session_path) !== 1) {
      $session_path = '/';
   }
   $session_host = strtolower((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost'));
   $session_host = preg_replace('/:\d+$/', '', $session_host) ?: 'localhost';
   session_name(
      'DBXSESSID'
      . strtoupper(substr(hash('sha256', $session_host . '|' . $session_path), 0, 12))
   );
   session_set_cookie_params(array(
      'lifetime' => 0,
      'path' => $session_path,
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
function dbx_get_base_dir($cut_data = 0): string {
   $path = str_replace('\\', '/', __DIR__) . '/';
   if ($cut_data && str_ends_with($path, '/Data/')) {
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

$error_log_dir = rtrim(dbx_get_file_dir(), '/\\');
if (!is_dir($error_log_dir)) {
   @mkdir($error_log_dir, 0775, true);
}
ini_set('error_log', $error_log_dir . DIRECTORY_SEPARATOR . 'dbxError.log');

require dbx_get_base_dir() . 'dbx/vendor/autoload.php';
require_once dbx_get_base_dir() . 'dbx/include/dbxKernel.php';

$runtime = dbx()->get_system_obj('dbxRuntime');
ini_set('error_log', $runtime->error_log_file());

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
   if (!(error_reporting() & $errno)) {
      return false;
   }

   $runtime = dbx()->get_system_obj('dbxRuntime');
   $runtime->write_php_error_log(
      $runtime->error_type((int)$errno),
      (string)$errstr,
      (string)$errfile,
      (int)$errline
   );
   return true;
});

set_exception_handler(function ($exception) {
   dbx()->get_system_obj('dbxRuntime')->write_php_error_log(
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

   $fatal_types = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR);
   if (!in_array($error['type'], $fatal_types, true)) {
      return;
   }

   $runtime = dbx()->get_system_obj('dbxRuntime');
   $runtime->write_php_error_log(
      $runtime->error_type((int)$error['type']),
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

dbx()->get_system_obj('dbxRequestPipeline')->run();
exit;
