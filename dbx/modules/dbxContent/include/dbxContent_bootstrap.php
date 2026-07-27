<?php
/**
 * Gemeinsamer Einstieg fuer den oeffentlichen Content-Kern.
 */
if (defined('DBXCONTENT_BOOTSTRAP_CORE')) {
   return;
}
define('DBXCONTENT_BOOTSTRAP_CORE', 1);

$dir = __DIR__;
require_once $dir . '/dbxContentLng.class.php';
require_once $dir . '/dbxContentPageCache.class.php';
require_once $dir . '/dbxContentRenderer.class.php';
require_once $dir . '/dbxContentSitemap.class.php';

?>
