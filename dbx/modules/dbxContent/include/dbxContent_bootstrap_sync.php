<?php
/**
 * Content-Kern plus Sync, Permalink und Home-Helfer.
 */
if (defined('DBXCONTENT_BOOTSTRAP_SYNC')) {
   return;
}
define('DBXCONTENT_BOOTSTRAP_SYNC', 1);

$dir = __DIR__;
require_once $dir . '/dbxContent_bootstrap.php';
require_once $dir . '/dbxContentTranslate.class.php';
require_once $dir . '/dbxContent_permalink.class.php';
require_once $dir . '/dbxContentLngSync.class.php';
require_once $dir . '/dbxContentHome.class.php';

?>
