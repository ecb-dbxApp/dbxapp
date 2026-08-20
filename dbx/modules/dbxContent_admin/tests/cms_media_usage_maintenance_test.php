<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/dbxContentMediaUsageMaintenance.class.php';
require_once dirname(__DIR__, 3) . '/include/tests/dbxModuleSourceBundle.php';

use dbx\dbxContent_admin\dbxContentMediaUsageMaintenance;

$failures = array();
$assert = static function(bool $condition, string $message) use (&$failures): void {
   if (!$condition) $failures[] = $message;
};

$expected = array();
$inline_key = dbxContentMediaUsageMaintenance::usage_key(10, 7, 3, 'inline');
$hero_key = dbxContentMediaUsageMaintenance::usage_key(11, 7, 3, 'hero');
$folder_hero_key = dbxContentMediaUsageMaintenance::usage_key(12, 0, 4, 'hero');
$expected[$inline_key] = array(
   'media_id' => 10,
   'content_id' => 7,
   'folder_id' => 3,
   'slot' => 'inline',
   'template' => 'image-inline',
   'valid_folders' => array(3 => 1),
);
$expected[$hero_key] = array(
   'media_id' => 11,
   'content_id' => 7,
   'folder_id' => 3,
   'slot' => 'hero',
   'template' => 'image-hero',
   'valid_folders' => array(3 => 1),
);
$expected[$folder_hero_key] = array(
   'media_id' => 12,
   'content_id' => 0,
   'folder_id' => 4,
   'slot' => 'hero',
   'template' => 'image-hero',
   'valid_folders' => array(),
);

$rows = array(
   array('id' => 1, 'active' => 0, 'media_id' => 10, 'content_id' => 7, 'folder_id' => 3, 'slot' => 'inline'),
   array('id' => 2, 'active' => 1, 'media_id' => 10, 'content_id' => 7, 'folder_id' => 0, 'slot' => 'inline'),
   array('id' => 3, 'active' => 1, 'media_id' => 10, 'content_id' => 7, 'folder_id' => 3, 'slot' => 'inline'),
   array('id' => 4, 'active' => 1, 'media_id' => 13, 'content_id' => 7, 'folder_id' => 3, 'slot' => 'inline'),
   array('id' => 5, 'active' => 1, 'media_id' => 13, 'content_id' => 7, 'folder_id' => 3, 'slot' => 'gallery'),
   array('id' => 6, 'active' => 1, 'media_id' => 99, 'content_id' => 7, 'folder_id' => 3, 'slot' => 'gallery'),
   array('id' => 7, 'active' => 1, 'media_id' => 13, 'content_id' => 7, 'folder_id' => 3, 'slot' => 'unknown'),
   array('id' => 8, 'active' => 1, 'media_id' => 13, 'content_id' => 999, 'folder_id' => 3, 'slot' => 'gallery'),
   array('id' => 9, 'active' => 1, 'media_id' => 12, 'content_id' => 0, 'folder_id' => 4, 'slot' => 'hero'),
);

$plan = dbxContentMediaUsageMaintenance::plan(
   $rows,
   array(10 => 1, 11 => 1, 12 => 1, 13 => 1),
   $expected,
   array(7 => array(3 => 1)),
   array(3 => 1, 4 => 1),
   array('hero', 'gallery', 'inline', 'header', 'teaser', 'footer', 'shop')
);

$assert(($plan['delete'][1] ?? '') === 'inactive', 'Inactive history must be deleted physically.');
$assert(($plan['delete'][3] ?? '') === 'duplicate', 'A second controlled usage must be removed as duplicate.');
$assert(($plan['delete'][4] ?? '') === 'not_in_content', 'Stale inline usage must be removed.');
$assert(($plan['delete'][6] ?? '') === 'media_invalid', 'Usage of an invalid medium must be removed.');
$assert(($plan['delete'][7] ?? '') === 'slot_invalid', 'Unknown usage slots must be removed.');
$assert(($plan['delete'][8] ?? '') === 'content_missing', 'Usage with a missing target must be removed.');
$assert(($plan['update'][2]['folder_id'] ?? 0) === 3, 'The page folder of a controlled usage must be corrected.');
$assert(!isset($plan['delete'][5]), 'A valid manually managed gallery usage must be preserved.');
$assert(!isset($plan['delete'][9]), 'A real folder hero usage must be preserved.');
$assert(isset($plan['insert'][$hero_key]), 'A missing hero usage must be rebuilt from the real page field.');
$assert(!isset($plan['insert'][$inline_key]), 'An existing inline usage must not be inserted twice.');

$base = dirname(__DIR__, 4);
$cms_source = dbx_test_module_source_bundle($base . '/dbx/modules/dbxContent_admin/include/dbxContent_cms.class.php');
$cms_js = (string)file_get_contents($base . '/dbx/modules/dbxContent_admin/js/cms.js')
   . (string)file_get_contents($base . '/dbx/modules/dbxContent_admin/js/cms-media.js');
$assert(
   str_contains($cms_source, "array('type' => 'usage_reconcile')")
      && str_contains($cms_source, "array('type' => 'media_record_purge')")
      && str_contains($cms_source, "array('type' => 'folder_sort_normalize')")
      && str_contains($cms_source, 'cleanup_invalid_structured_media_references'),
   'The maintenance process must schedule usage reconciliation, invalid media purging, folder sorting and structured reference cleanup.'
);
$assert(
   str_contains($cms_source, 'private function normalize_content_folder_sorters($db): array')
      && str_contains($cms_source, "sprintf('%04d', \$position)")
      && str_contains($cms_source, 'dbxContentLngSync::accessible_lngs()')
      && str_contains($cms_source, '$db->rollback($dd)'),
   'Folder sorting must normalize every language per parent in atomic 10-step values.'
);
$assert(
   str_contains($cms_js, 'Medien und Nutzung pruefen')
      && str_contains($cms_js, 'Analyse &amp; Reparatur starten')
      && str_contains($cms_js, 'Ordner je Parent auf 10er-Sortierwerte normalisiert')
      && str_contains($cms_js, 'Nachweislich ungueltige und deaktivierte Datenbankeintraege'),
   'The destructive maintenance workflow must be explained and confirmed in the UI.'
);
$assert(
   str_contains($cms_js, 'id: "cms-delete-media-" + id')
      && str_contains($cms_js, 'callerEl: source')
      && str_contains($cms_js, 'Das Loeschen ist nur moeglich, wenn das Medium nicht mehr verwendet wird.')
      && str_contains($cms_js, '.then(deleted => deleted ? openMediaBrowser')
      && str_contains($cms_js, '.catch(() => null);'),
   'Single media deletion must be confirmed and must not hide a rejected deletion by reopening the browser.'
);
foreach (array('dbxapp', 'flowers', 'steal') as $design) {
   $css = (string)file_get_contents($base . '/dbx/design/' . $design . '/css/c-cms.css');
   $assert(str_contains($css, '.dbx-cms-media-maintenance-report'), 'The maintenance report is not styled in design ' . $design . '.');
}

if ($failures) {
   fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
   exit(1);
}

echo "OK media usage reconciliation removes stale history and rebuilds real references.\n";

$multi_expected = array();
$de_key = dbxContentMediaUsageMaintenance::usage_key(21, 5, 2, 'inline', 'de');
$en_key = dbxContentMediaUsageMaintenance::usage_key(22, 5, 2, 'inline', 'en');
$multi_expected[$de_key] = array('media_id' => 21, 'content_id' => 5, 'folder_id' => 2, 'content_lng' => 'de', 'slot' => 'inline', 'valid_folders' => array(2 => 1));
$multi_expected[$en_key] = array('media_id' => 22, 'content_id' => 5, 'folder_id' => 2, 'content_lng' => 'en', 'slot' => 'inline', 'valid_folders' => array(2 => 1));
$multi_plan = dbxContentMediaUsageMaintenance::plan(
   array(
      array('id' => 101, 'active' => 1, 'media_id' => 21, 'content_id' => 5, 'folder_id' => 2, 'content_lng' => 'de', 'slot' => 'inline'),
      array('id' => 102, 'active' => 1, 'media_id' => 22, 'content_id' => 5, 'folder_id' => 2, 'content_lng' => 'en', 'slot' => 'inline'),
      array('id' => 103, 'active' => 1, 'media_id' => 23, 'content_id' => 5, 'folder_id' => 2, 'content_lng' => 'de', 'slot' => 'gallery'),
      array('id' => 104, 'active' => 1, 'media_id' => 23, 'content_id' => 5, 'folder_id' => 2, 'content_lng' => 'de', 'slot' => 'gallery'),
   ),
   array(21 => 1, 22 => 1, 23 => 1),
   $multi_expected,
   array('de:5' => array(2 => 1), 'en:5' => array(2 => 1)),
   array('de:2' => 1, 'en:2' => 1),
   array('hero', 'gallery', 'inline', 'header', 'teaser', 'footer', 'shop')
);
$multi_failures = array();
if (isset($multi_plan['delete'][101]) || isset($multi_plan['delete'][102])) $multi_failures[] = 'Same numeric page IDs in different languages must stay independent.';
if (($multi_plan['delete'][104] ?? '') !== 'duplicate') $multi_failures[] = 'Exact duplicates must be removed for manually managed slots too.';
if (isset($multi_plan['insert'][$de_key]) || isset($multi_plan['insert'][$en_key])) $multi_failures[] = 'Existing language-specific usages must not be inserted again.';
if ($multi_failures) {
   fwrite(STDERR, "FAIL\n- " . implode("\n- ", $multi_failures) . "\n");
   exit(1);
}
echo "OK multilingual media usage targets and all-slot duplicate cleanup.\n";
