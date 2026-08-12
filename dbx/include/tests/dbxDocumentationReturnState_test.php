<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
chdir($root);

$_GET = array('dbx_design' => 'dbxdocs', 'dbx_color' => 'hell');
$_POST = array();
$_SERVER['REQUEST_URI'] = '/dbxapp/dokumentation/?dbx_design=dbxdocs&dbx_color=hell';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SCRIPT_NAME'] = '/dbxapp/index.php';

if (!defined('dbxSystem')) {
    define('dbxSystem', 'dbxWebApp');
}
if (!defined('dbxRunAsAdmin')) {
    define('dbxRunAsAdmin', 1);
}

require $root . '/dbx/vendor/autoload.php';
require_once $root . '/dbx/include/dbxKernel.php';
require_once $root . '/dbx/modules/dbxContent/include/dbxContent_permalink.class.php';

if (\dbx\dbxContent\dbxContent_permalink::publicPath('dokumentation-anwender')
    !== 'dokumentation/dokumentation-anwender') {
    throw new RuntimeException('Eine Dokumentationsseite erhält keinen kanonischen Bereichspfad.');
}
if (\dbx\dbxContent\dbxContent_permalink::canonicalFromLegacy(
    'dokumentation/dokumentation-anwender'
) !== 'dokumentation-anwender') {
    throw new RuntimeException('Der Bereichspfad wird nicht auf den eindeutigen CMS-Slug aufgelöst.');
}

$web = dbx()->get_system_obj('dbxWebApp');
dbx()->set_remember_var('dbx_design', 'flowers', 'dbx');
dbx()->set_remember_var('dbx_color', 'hell', 'dbx');
dbx()->set_remember_var('dbx_page', 'intro', 'dbx');
$web->check_remember();

if (dbx()->get_remember_var('dbx_docs_return_design', '', 'dbx') !== 'flowers') {
    throw new RuntimeException('Das zuvor aktive Design wurde beim dbxdocs-Einstieg nicht gemerkt.');
}
if (dbx()->get_remember_var('dbx_docs_return_color', '', 'dbx') !== 'hell') {
    throw new RuntimeException('Der zum Design gehörende Skin wurde beim dbxdocs-Einstieg nicht gemerkt.');
}
if (dbx()->get_remember_var('dbx_docs_return_page', '', 'dbx') !== 'intro') {
    throw new RuntimeException('Die zuvor aktive Layoutseite wurde beim dbxdocs-Einstieg nicht gemerkt.');
}

$returnUrl = $web->documentation_return_url();
if (!str_contains($returnUrl, 'dbx_design=flowers')
    || !str_contains($returnUrl, 'dbx_color=hell')
    || !str_contains($returnUrl, 'dbx_page=intro')) {
    throw new RuntimeException('Der Dokumentations-Rücksprung stellt Design, Skin und Layoutseite nicht gemeinsam wieder her.');
}

$rendered = dbx()->get_system_obj('dbxTPL')->replaces_dbx('<a href="{dbx:docs_return_url}">dbXapp</a>');
if (!str_contains($rendered, 'dbx_design=flowers&amp;dbx_color=hell&amp;dbx_page=intro')) {
    throw new RuntimeException('Der Rücksprung wird im HTML nicht sicher und vollständig ausgegeben.');
}

dbx()->set_remember_var('dbx_docs_return_design', '../unsafe', 'dbx');
$safeUrl = $web->documentation_return_url();
if (str_contains($safeUrl, 'unsafe') || str_contains($safeUrl, '..')) {
    throw new RuntimeException('Ein manipuliertes Rücksprungdesign wurde nicht verworfen.');
}

echo "OK dbxdocs merkt ein validiertes Design/Skin-Paar und erzeugt einen sicheren Rücksprung\n";
