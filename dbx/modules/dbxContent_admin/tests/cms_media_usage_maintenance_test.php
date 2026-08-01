<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/dbxContentMediaUsageMaintenance.class.php';

use dbx\dbxContent_admin\dbxContentMediaUsageMaintenance;

$failures = array();
$assert = static function(bool $condition, string $message) use (&$failures): void {
   if (!$condition) $failures[] = $message;
};

$expected = array();
$inlineKey = dbxContentMediaUsageMaintenance::usageKey(10, 7, 3, 'inline');
$heroKey = dbxContentMediaUsageMaintenance::usageKey(11, 7, 3, 'hero');
$folderHeroKey = dbxContentMediaUsageMaintenance::usageKey(12, 0, 4, 'hero');
$expected[$inlineKey] = array(
   'media_id' => 10,
   'content_id' => 7,
   'folder_id' => 3,
   'slot' => 'inline',
   'template' => 'image-inline',
   'valid_folders' => array(3 => 1),
);
$expected[$heroKey] = array(
   'media_id' => 11,
   'content_id' => 7,
   'folder_id' => 3,
   'slot' => 'hero',
   'template' => 'image-hero',
   'valid_folders' => array(3 => 1),
);
$expected[$folderHeroKey] = array(
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
$assert(isset($plan['insert'][$heroKey]), 'A missing hero usage must be rebuilt from the real page field.');
$assert(!isset($plan['insert'][$inlineKey]), 'An existing inline usage must not be inserted twice.');

$base = dirname(__DIR__, 4);
$cmsSource = (string)file_get_contents($base . '/dbx/modules/dbxContent_admin/include/dbxContent_cms.class.php');
$cmsJs = (string)file_get_contents($base . '/dbx/js/lib/cms.js');
$assert(
   str_contains($cmsSource, "array('type' => 'usage_reconcile')")
      && str_contains($cmsSource, "array('type' => 'media_record_purge')")
      && str_contains($cmsSource, 'cleanup_invalid_structured_media_references'),
   'The maintenance process must schedule usage reconciliation, invalid media purging and structured reference cleanup.'
);
$assert(
   str_contains($cmsJs, 'Medien und Nutzung pruefen')
      && str_contains($cmsJs, 'Analyse &amp; Reparatur starten')
      && str_contains($cmsJs, 'Nachweislich ungueltige und deaktivierte Datenbankeintraege'),
   'The destructive maintenance workflow must be explained and confirmed in the UI.'
);
foreach (array('dbxapp', 'dbxdocs', 'flowers', 'steal') as $design) {
   $css = (string)file_get_contents($base . '/dbx/design/' . $design . '/css/c-cms.css');
   $assert(str_contains($css, '.dbx-cms-media-maintenance-report'), 'The maintenance report is not styled in design ' . $design . '.');
}

if ($failures) {
   fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
   exit(1);
}

echo "OK media usage reconciliation removes stale history and rebuilds real references.\n";

$multiExpected = array();
$deKey = dbxContentMediaUsageMaintenance::usageKey(21, 5, 2, 'inline', 'de');
$enKey = dbxContentMediaUsageMaintenance::usageKey(22, 5, 2, 'inline', 'en');
$multiExpected[$deKey] = array('media_id' => 21, 'content_id' => 5, 'folder_id' => 2, 'content_lng' => 'de', 'slot' => 'inline', 'valid_folders' => array(2 => 1));
$multiExpected[$enKey] = array('media_id' => 22, 'content_id' => 5, 'folder_id' => 2, 'content_lng' => 'en', 'slot' => 'inline', 'valid_folders' => array(2 => 1));
$multiPlan = dbxContentMediaUsageMaintenance::plan(
   array(
      array('id' => 101, 'active' => 1, 'media_id' => 21, 'content_id' => 5, 'folder_id' => 2, 'content_lng' => 'de', 'slot' => 'inline'),
      array('id' => 102, 'active' => 1, 'media_id' => 22, 'content_id' => 5, 'folder_id' => 2, 'content_lng' => 'en', 'slot' => 'inline'),
      array('id' => 103, 'active' => 1, 'media_id' => 23, 'content_id' => 5, 'folder_id' => 2, 'content_lng' => 'de', 'slot' => 'gallery'),
      array('id' => 104, 'active' => 1, 'media_id' => 23, 'content_id' => 5, 'folder_id' => 2, 'content_lng' => 'de', 'slot' => 'gallery'),
   ),
   array(21 => 1, 22 => 1, 23 => 1),
   $multiExpected,
   array('de:5' => array(2 => 1), 'en:5' => array(2 => 1)),
   array('de:2' => 1, 'en:2' => 1),
   array('hero', 'gallery', 'inline', 'header', 'teaser', 'footer', 'shop')
);
$multiFailures = array();
if (isset($multiPlan['delete'][101]) || isset($multiPlan['delete'][102])) $multiFailures[] = 'Same numeric page IDs in different languages must stay independent.';
if (($multiPlan['delete'][104] ?? '') !== 'duplicate') $multiFailures[] = 'Exact duplicates must be removed for manually managed slots too.';
if (isset($multiPlan['insert'][$deKey]) || isset($multiPlan['insert'][$enKey])) $multiFailures[] = 'Existing language-specific usages must not be inserted again.';
if ($multiFailures) {
   fwrite(STDERR, "FAIL\n- " . implode("\n- ", $multiFailures) . "\n");
   exit(1);
}
echo "OK multilingual media usage targets and all-slot duplicate cleanup.\n";
