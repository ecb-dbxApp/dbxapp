<?php

require_once dirname(__DIR__) . '/include/dbxKiBundleService.class.php';

$class = new ReflectionClass('dbx\\dbxKi\\dbxKiBundleService');
$service = $class->newInstanceWithoutConstructor();

$urlMethod = $class->getMethod('cmsAdminPageUrl');
$url = $urlMethod->invoke($service, 51, 'de');
$expected = '?dbx_modul=dbxContent_admin&dbx_run1=cms&cid=51&dbx_lng=de';

if ($url !== $expected || strpos($url, 'dbx_ajax') !== false) {
   fwrite(STDERR, "FAIL: CMS-URL enthaelt ein AJAX-Kennzeichen oder ist ungueltig.\n$url\n");
   exit(1);
}

$footerMethod = $class->getMethod('buildImportFooterActions');
$footer = $footerMethod->invoke($service, array(
   'lng' => 'de',
   'step_results' => array(array('page_id' => 51, 'lng' => 'de')),
), false, '', '?dbx_modul=dbxKi&dbx_run1=bundle');

if (strpos($footer, 'cid=51&amp;dbx_lng=de') === false || strpos($footer, 'dbx_ajax') !== false) {
   fwrite(STDERR, "FAIL: Schaltflaeche 'Seite im CMS' erzeugt eine ungueltige URL.\n");
   exit(2);
}

echo "OK dbxKi CMS URL\n";
