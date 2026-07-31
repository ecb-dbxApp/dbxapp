<?php

declare(strict_types=1);

$_SERVER['HTTPS'] = 'on';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_PORT'] = '443';
$_SERVER['SCRIPT_NAME'] = '/dbxapp/index.php';
$_SERVER['PHP_SELF'] = '/dbxapp/index.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/dbxapp/';

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/dbxKernel.php';
require_once dirname(__DIR__) . '/dbxTPL.class.php';
require_once dirname(__DIR__, 2) . '/modules/dbxContent/include/dbxContentRenderer.class.php';

function seoFail(string $message, int $code): void {
   fwrite(STDERR, "FAIL: $message\n");
   exit($code);
}

$base = 'https://localhost/dbxapp/';
dbx()->set_system_var('dbx_base_url', $base);
$web = dbx()->get_system_obj('dbxWebApp');

dbx()->set_system_var('dbx_permalink', 'home');
$_GET = array();
$_POST = array();
if ($web->canonical_home_redirect_target() !== $base) {
   seoFail('Der Startseitenalias home wird nicht auf die Basis-URL normalisiert.', 1);
}

$_SERVER['REQUEST_METHOD'] = 'POST';
if ($web->canonical_home_redirect_target() !== '') {
   seoFail('Schreibende Requests duerfen keinen Startseiten-Redirect erhalten.', 2);
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = array('dbx_modul' => 'dbxContent', 'dbx_run1' => 'run');
if ($web->canonical_home_redirect_target() !== '') {
   seoFail('Eine explizite Modulroute darf nicht als Startseitenalias umgedeutet werden.', 3);
}

$_GET = array('dbx_design' => 'steal');
if ($web->canonical_home_redirect_target() !== $base) {
   seoFail('Eine reine Darstellungsvariante muss auf die saubere Startseite fuehren.', 4);
}

dbx()->set_system_var('dbx_permalink', 'produkte');
$_GET = array();
if ($web->canonical_home_redirect_target() !== '') {
   seoFail('Ein normaler Content-Permalink darf nicht umgeleitet werden.', 5);
}

dbx()->set_system_var('dbx_lng', 'de');
dbx()->set_system_var('dbx_permalink', 'info-cms');
if ($web->content_permalink_redirect_target() !== $base . 'cms-website') {
   seoFail('Die deutsche Content-Weiterleitung wurde nicht erkannt.', 6);
}

dbx()->set_system_var('dbx_lng', 'en');
if ($web->content_permalink_redirect_target() !== '') {
   seoFail('Deutsche Content-Weiterleitungen dürfen andere Sprachen nicht verändern.', 7);
}

dbx()->set_system_var('dbx_lng', 'de');
$_GET = array('dbx_modul' => 'dbxContent');
if ($web->content_permalink_redirect_target() !== '') {
   seoFail('Explizite Modulrouten dürfen keine Content-Weiterleitung auslösen.', 8);
}

$tpl = new dbxTPL();
$robotsMethod = new ReflectionMethod(dbxTPL::class, 'effective_robots_meta');
$robotsMethod->setAccessible(true);
dbx()->set_system_var('dbx_robots', 'index,follow');

$_GET = array('dbx_modul' => 'dbxContent');
if ($robotsMethod->invoke($tpl) !== 'noindex,follow') {
   seoFail('Technische Routen muessen Content-SEO mit noindex,follow ueberstimmen.', 9);
}

$_GET = array('dbx_edit' => '1');
if ($robotsMethod->invoke($tpl) !== 'noindex,follow') {
   seoFail('Bearbeitungsrouten muessen noindex,follow erhalten.', 10);
}

$_GET = array('dbx_do' => 'delete');
if ($robotsMethod->invoke($tpl) !== 'noindex,follow') {
   seoFail('Aktionsrouten muessen noindex,follow erhalten.', 11);
}

$_GET = array();
if ($robotsMethod->invoke($tpl) !== 'index,follow') {
   seoFail('Saubere Content-Routen muessen ihren konfigurierten Robots-Wert behalten.', 12);
}

$renderer = new \dbx\dbxContent\dbxContentRenderer();
$canonicalMethod = new ReflectionMethod($renderer, 'seoCanonicalUrl');
$canonicalMethod->setAccessible(true);
if ($canonicalMethod->invoke($renderer, 'home', true) !== $base) {
   seoFail('Die konfigurierte Startseite muss die Basis-URL als Canonical erhalten.', 13);
}
if ($canonicalMethod->invoke($renderer, 'produkte', false) !== $base . 'produkte') {
   seoFail('Normale Inhaltsseiten muessen einen selbstreferenziellen Canonical erhalten.', 14);
}

$root = dirname(__DIR__, 2);
$sitemapSource = (string)file_get_contents(
   $root . '/modules/dbxContent/include/dbxContentSitemap.class.php'
);
if (strpos($sitemapSource, 'collectUserMenuLinks') !== false
   || strpos($sitemapSource, 'dbxContentHome::masterCid') === false
   || strpos($sitemapSource, 'isNoindex') === false
   || strpos($sitemapSource, '$lngs = array($masterLng)') === false) {
   seoFail('Die Sitemap-Regel fuer reine Content-URLs und die Startseite fehlt.', 15);
}

$htaccess = (string)file_get_contents(dirname($root) . '/.htaccess');
if (!preg_match('/RewriteRule \\^home\\/\\?\\$ https:\\/\\/dbxapp\\.de\\//', $htaccess)
   || strpos($htaccess, '%{HTTPS} !=on') === false) {
   seoFail('Die produktive Redirect-Regel fuer /home beziehungsweise HTTPS fehlt.', 16);
}

$designDir = $root . '/design';
$iterator = new RecursiveIteratorIterator(
   new RecursiveDirectoryIterator($designDir, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
   if (!$file->isFile() || strtolower($file->getExtension()) !== 'htm') {
      continue;
   }
   $html = (string)file_get_contents($file->getPathname());
   if (stripos($html, '<head') === false) {
      continue;
   }
   if (substr_count($html, '{dbx:head_meta}') !== 1) {
      seoFail('Design-HTML ohne genau einen zentralen Head-Metablock: ' . $file->getPathname(), 17);
   }
   if (preg_match("/<link\\b[^>]*rel=[\"']canonical[\"']/i", $html)) {
      seoFail('Canonical darf im Design nicht fest verdrahtet sein: ' . $file->getPathname(), 18);
   }
}

echo "OK dbx SEO canonical policy\n";
