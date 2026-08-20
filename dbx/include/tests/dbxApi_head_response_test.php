<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/dbxApi.php';
require_once dirname(__DIR__) . '/dbxRequestPipeline.class.php';

$pipeline = new dbxRequestPipeline();
$method = new ReflectionMethod(dbxRequestPipeline::class, 'emit_http_response_body');
$method->setAccessible(true);

$_SERVER['REQUEST_METHOD'] = 'HEAD';
ob_start();
$method->invoke($pipeline, '<html>HEAD darf keinen Body liefern</html>');
$head_body = ob_get_clean();
if ($head_body !== '') {
   fwrite(STDERR, "FAIL: HEAD gibt bei einem Cache-MISS einen Response-Body aus.\n");
   exit(1);
}

$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
$method->invoke($pipeline, '<html>GET-Body</html>');
$get_body = ob_get_clean();
if ($get_body !== '<html>GET-Body</html>') {
   fwrite(STDERR, "FAIL: GET-Body wird nicht unveraendert ausgegeben.\n");
   exit(2);
}

echo "OK dbxRequestPipeline HEAD response\n";
