<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/dbxApi.php';

$api = new dbxApi();
$method = new ReflectionMethod(dbxApi::class, 'emit_http_response_body');
$method->setAccessible(true);

$_SERVER['REQUEST_METHOD'] = 'HEAD';
ob_start();
$method->invoke($api, '<html>HEAD darf keinen Body liefern</html>');
$headBody = ob_get_clean();
if ($headBody !== '') {
   fwrite(STDERR, "FAIL: HEAD gibt bei einem Cache-MISS einen Response-Body aus.\n");
   exit(1);
}

$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
$method->invoke($api, '<html>GET-Body</html>');
$getBody = ob_get_clean();
if ($getBody !== '<html>GET-Body</html>') {
   fwrite(STDERR, "FAIL: GET-Body wird nicht unveraendert ausgegeben.\n");
   exit(2);
}

echo "OK dbxApi HEAD response\n";

