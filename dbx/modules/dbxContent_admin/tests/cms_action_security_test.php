<?php

$root = dirname(__DIR__);
$cms = file_get_contents($root . '/include/dbxContent_cms.class.php');
$seo = file_get_contents($root . '/include/dbxContent_seo.class.php');
$lng = file_get_contents(dirname(__DIR__, 2) . '/dbxContent/include/dbxContentLngSync.class.php');

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
   'cms_save',
   'cms_new_page',
   'cms_duplicate_page',
   'cms_delete_page',
   'cms_move_node',
   'cms_upload',
   'cms_delete_media',
   'cms_assign_media',
   'cms_sort_media',
);
foreach ($cmsActions as $action) {
   if (strpos($cms, "'" . $action . "'") === false) {
      $fail('Schreibende CMS-Aktion fehlt in der Tokenliste: ' . $action, 3);
   }
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

echo "OK CMS action security and DD ownership\n";
