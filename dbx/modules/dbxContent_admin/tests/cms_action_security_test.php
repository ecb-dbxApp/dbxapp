<?php

$root = dirname(__DIR__);
require_once dirname(__DIR__, 3) . '/include/tests/dbxModuleSourceBundle.php';
$cms = dbx_test_module_source_bundle($root . '/include/dbxContent_cms.class.php');
$seo = file_get_contents($root . '/include/dbxContent_seo.class.php');
$lng = file_get_contents(dirname(__DIR__, 2) . '/dbxContent/include/dbxContentLngSync.class.php');
$manifest = require $root . '/cfg/cms-actions.php';

$fail = static function (string $message, int $code): void {
   fwrite(STDERR, "FAIL: $message\n");
   exit($code);
};

if (!is_string($cms) || !is_string($seo) || !is_string($lng)) {
   $fail('Content-Quellen konnten nicht gelesen werden.', 1);
}

foreach (array($cms, $seo) as $source) {
   if (strpos($source, "private const ACTION_TOKEN_SCOPE = 'dbxContent_admin.actions'") === false
      || strpos($source, 'check_action_token(self::ACTION_TOKEN_SCOPE, $token)') === false) {
      $fail('CMS oder SEO nutzt nicht die gemeinsame Tokenbehandlung.', 2);
   }
}

$cmsActions = array(
   'cms_lng_provision', 'cms_lng_provision_tree', 'cms_lng_reset_sync',
   'cms_save', 'cms_new_page', 'cms_duplicate_page',
   'cms_new_folder', 'cms_save_folder', 'cms_delete_folder',
   'cms_delete_page', 'cms_move_node', 'cms_media_process',
   'cms_upload', 'cms_external_video', 'cms_media_folder_create',
   'cms_media_folder_delete', 'cms_media_folder_rename', 'cms_media_move',
   'cms_media_unused', 'cms_remove_media', 'cms_delete_media',
   'cms_edit_media', 'cms_set_media_slot', 'cms_assign_media',
   'cms_sort_media',
);
foreach ($cmsActions as $action) {
   if (empty($manifest[$action]['token']) || empty($manifest[$action]['mutation'])) {
      $fail('Schreibende CMS-Aktion fehlt in der Tokenliste: ' . $action, 3);
   }
}

if (strpos($cms, "->module('dbxContent_admin', 'cms-actions')") === false
   || strpos($cms, "\$this->cms_actions()[\$action]") === false
   || strpos($cms, "return \$this->{\$handler}();") === false) {
   $fail('CMS-Dispatch und Tokenpflicht verwenden nicht denselben Aktionsvertrag.', 3);
}

if (strpos($cms, "if (\$action === 'cms_media')") === false
   || strpos($cms, "get_modul_var('sync', 0, 'int') === 1") === false
   || strpos($cms, 'if ($this->action_requires_token($action) && !$this->check_action_token($action))') === false) {
   $fail('Der gemischte Medienendpunkt oder die zentrale Dispatch-Pruefung ist ungeschuetzt.', 4);
}

if (strpos($seo, "if (\$action === 'seo_save' && !\$this->check_action_token(\$action))") === false) {
   $fail('SEO-Speichern wird nicht vor dem Dispatch tokengeprueft.', 5);
}

if (preg_match('/\b(?:PRAGMA|ALTER\s+TABLE|CREATE\s+(?:TABLE|INDEX))\b/i', $cms) === 1
   || preg_match('/\b(?:PRAGMA|ALTER\s+TABLE|CREATE\s+(?:TABLE|INDEX))\b/i', $lng) === 1) {
   $fail('Ein normaler Content-Request enthaelt weiterhin Laufzeit-DDL.', 6);
}

$pageMapSource = dbx_test_module_method_source($cms, 'media_usage_page_map');
$contextSource = dbx_test_module_method_source($cms, 'media_usage_rows_for_context');
if (strpos($cms, 'private function rows_by_ids') === false
   || substr_count($pageMapSource, 'rows_by_ids(') < 2
   || substr_count($contextSource, 'rows_by_ids(') < 1
   || strpos($pageMapSource, 'select1(') !== false
   || strpos($contextSource, 'select1(') !== false) {
   $fail('Medien-, Seiten- oder Ordnerdaten werden wieder einzeln statt gebuendelt geladen.', 7);
}

$pageReadSource = dbx_test_module_method_source($cms, 'page_json');
if (!empty($manifest['cms_page']['mutation'])
   || strpos($pageReadSource, 'sync_inline_media_usage(') !== false
   || strpos($pageReadSource, '->insert(') !== false
   || strpos($pageReadSource, '->update(') !== false
   || strpos($pageReadSource, '->delete(') !== false) {
   $fail('Der lesende CMS-Seitenendpunkt fuehrt weiterhin Datenbankmutationen aus.', 8);
}

echo "OK CMS action security and DD ownership\n";
