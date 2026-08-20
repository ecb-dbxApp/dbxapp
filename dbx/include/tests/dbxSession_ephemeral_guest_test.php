<?php

class dbxSessionEphemeralGuestTestBrowser {
   public int $_robot = 0;
}

class dbxSessionEphemeralGuestTestApi {
   public int $uid = 0;
   public dbxSessionEphemeralGuestTestBrowser $browser;

   public function __construct() {
      $this->browser = new dbxSessionEphemeralGuestTestBrowser();
   }

   public function user(): int {
      return $this->uid;
   }

   public function get_system_obj(string $class) {
      return $class === 'dbxBrowser' ? $this->browser : null;
   }

   public function debug(...$args): void {
   }
}

$dbx_session_ephemeral_guest_test_api = new dbxSessionEphemeralGuestTestApi();

function dbx(): dbxSessionEphemeralGuestTestApi {
   global $dbx_session_ephemeral_guest_test_api;
   return $dbx_session_ephemeral_guest_test_api;
}

require_once dirname(__DIR__) . '/dbxSession.class.php';

$fail = static function (string $message, int $code): void {
   fwrite(STDERR, "FAIL: $message\n");
   exit($code);
};

$session = new dbxSession();
$_SERVER['REQUEST_METHOD'] = 'GET';
$_COOKIE = array();

dbx()->uid = 0;
dbx()->browser->_robot = 1;
if (!$session->is_ephemeral_anonymous_session_request(false)) {
   $fail('Ein anonymer Robot ohne Session-Cookie wird nicht als fluechtig erkannt.', 1);
}

dbx()->browser->_robot = 0;
if ($session->is_ephemeral_anonymous_session_request(false)) {
   $fail('Ein normaler Gast ausserhalb des Full-Page-Caches wird verworfen.', 2);
}
if (!$session->is_ephemeral_anonymous_session_request(true)) {
   $fail('Ein cookie-loser Gast auf einem sicheren Full-Page-Cache-Hit bleibt persistent.', 3);
}

$_COOKIE[session_name()] = 'existing-session-id';
session_id('existing-session-id');
if ($session->is_ephemeral_anonymous_session_request(true)) {
   $fail('Eine vorhandene Cookie-Session wird auf einem Cache-Hit verworfen.', 4);
}
$_COOKIE[session_name()] = 'invalid-or-stale-session-id';
if (!$session->is_ephemeral_anonymous_session_request(true)) {
   $fail('Ein ungueltiger Session-Cookie umgeht die fluechtige Cache-Hit-Behandlung.', 5);
}
unset($_COOKIE[session_name()]);
session_id('');

dbx()->browser->_robot = 1;
$_SERVER['REQUEST_METHOD'] = 'POST';
if ($session->is_ephemeral_anonymous_session_request(false)) {
   $fail('Ein POST-Request wird als fluechtiger Robot-Read behandelt.', 6);
}

$_SERVER['REQUEST_METHOD'] = 'GET';
dbx()->uid = 7;
if ($session->is_ephemeral_anonymous_session_request(false)) {
   $fail('Eine authentifizierte Session wird als fluechtig behandelt.', 7);
}

dbx()->uid = 0;
dbx()->browser->_robot = 1;
$tmp = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR
   . 'dbx-session-ephemeral-' . bin2hex(random_bytes(6));
if (!mkdir($tmp, 0700, true) && !is_dir($tmp)) {
   $fail('Temporaeres Session-Testverzeichnis konnte nicht erstellt werden.', 8);
}

session_save_path($tmp);
session_id('dbxrobotsessiontest20260724');
if (!session_start()) {
   $fail('PHP-Testsession konnte nicht gestartet werden.', 9);
}
$_SESSION['probe'] = 'temporary';

if (!$session->discard_ephemeral_anonymous_session(false)) {
   $fail('Die fluechtige Robot-Session wurde nicht verworfen.', 10);
}
if (session_status() === PHP_SESSION_ACTIVE) {
   $fail('Die fluechtige PHP-Session ist nach dem Verwerfen noch aktiv.', 11);
}

$files = glob($tmp . DIRECTORY_SEPARATOR . 'sess_*') ?: array();
if ($files !== array()) {
   foreach ($files as $file) {
      @unlink($file);
   }
   @rmdir($tmp);
   $fail('Die fluechtige PHP-Sessiondatei ist liegen geblieben.', 12);
}
@rmdir($tmp);

echo "OK ephemeral guest PHP sessions\n";
