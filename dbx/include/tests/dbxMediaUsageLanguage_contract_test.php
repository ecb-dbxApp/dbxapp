<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once __DIR__ . '/dbxModuleSourceBundle.php';
$failures = array();
$check = static function(bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$dd = (string)file_get_contents($root . '/dbx/modules/dbx/dd/dbxMediaUsage.dd.php');
$cms = dbx_test_module_source_bundle($root . '/dbx/modules/dbxContent_admin/include/dbxContent_cms.class.php');
$renderer = (string)file_get_contents($root . '/dbx/modules/dbxContent/include/dbxContentRenderer.class.php');
$scope = (string)file_get_contents($root . '/dbx/modules/dbxContent/include/dbxContentMediaUsageScope.class.php');
$shop = dbx_test_module_source_bundle($root . '/dbx/modules/dbxShop_admin/include/dbxShopAdmin.class.php');
$ki = dbx_test_module_source_bundle($root . '/dbx/modules/dbxKi/include/dbxKiCmsService.class.php');
$check(str_contains($dd, "\$field['name']='content_lng'"), 'dbxMediaUsage must persist the language of its content target.');
$check(str_contains($dd, 'idx_media_usage_lng_context'), 'The language/context lookup must be indexed.');
$check(str_contains($cms, "slot IN ('hero','gallery','header','teaser','footer')"), 'Page copying must use an explicit copy-slot allowlist.');
$check(!str_contains($cms, "active = 1 AND slot <> 'inline'"), 'Page copying must never select every non-inline slot (that copied shop data).');
$check(str_contains($cms, 'dbxContentMediaUsageScope::withLanguage'), 'CMS usage reads and writes must be language scoped.');
$check(str_contains($renderer, 'dbxContentMediaUsageScope::withLanguage'), 'Frontend rendering must be language scoped.');
$check(
    str_contains($scope, 'if (!class_exists(dbxContentMediaUsageScope::class, false))'),
    'The staged media-usage scope must be safe to include when the active release already loaded the class.'
);
$check(str_contains($shop, "'content_lng' => \$this->shopMediaUsageLng()"), 'Shop usage must be assigned to the stable master language.');
$check(str_contains($shop, "\$db->delete(") && str_contains($shop, 'sourceNeedle'), 'Shop synchronization must replace its own snapshot instead of accumulating inactive rows.');
$check(str_contains($ki, "slot IN ('hero','gallery','inline','header','teaser','footer')"), 'KI page translation/copy must exclude the shop slot.');
$check(str_contains($cms, 'RecursiveDirectoryIterator') && str_contains($cms, "'/media/_thumbs/'"), 'Thumbnail maintenance must scan the real thumbnail tree recursively.');

if ($failures) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK multilingual media-usage, copy, shop and thumbnail contracts.\n";
