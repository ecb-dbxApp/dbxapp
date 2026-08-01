<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$failures = array();
$check = static function(bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$dd = (string)file_get_contents($root . '/dbx/modules/dbx/dd/dbxMediaUsage.dd.php');
$cms = (string)file_get_contents($root . '/dbx/modules/dbxContent_admin/include/dbxContent_cms.class.php');
$renderer = (string)file_get_contents($root . '/dbx/modules/dbxContent/include/dbxContentRenderer.class.php');
$scope = (string)file_get_contents($root . '/dbx/modules/dbxContent/include/dbxContentMediaUsageScope.class.php');
$shop = (string)file_get_contents($root . '/dbx/modules/dbxShop_admin/include/dbxShopAdmin.class.php');
$ki = (string)file_get_contents($root . '/dbx/modules/dbxKi/include/dbxKiCmsService.class.php');
$migrationPath = $root . '/dbx/modules/dbx/migrations/4.1.0-001-media-usage-language.migration.php';
$migration = is_file($migrationPath) ? (string)file_get_contents($migrationPath) : '';
$repairMigrationPath = $root . '/dbx/modules/dbx/migrations/4.1.2-001-media-usage-schema-repair.migration.php';
$repairMigration = is_file($repairMigrationPath)
    ? (string)file_get_contents($repairMigrationPath)
    : '';

$check(str_contains($dd, "\$field['name']='content_lng'"), 'dbxMediaUsage must persist the language of its content target.');
$check(str_contains($dd, 'idx_media_usage_lng_context'), 'The language/context lookup must be indexed.');
$check(is_file($migrationPath), 'The 4.1.0 media-usage migration is missing.');
$check(
    str_contains($migration, "require_once dirname(__DIR__, 2) . '/dbxContent/include/dbxContentMediaUsageScope.class.php'"),
    'The media-usage migration must load new release dependencies from its own staged tree.'
);
$check(is_file($repairMigrationPath), 'The 4.1.2 media-usage schema repair migration is missing.');
$check(
    str_contains($repairMigration, "'core-4.1.2-media-usage-schema-repair'")
        && str_contains($repairMigration, 'content_lng wurde physisch nicht angelegt')
        && str_contains($repairMigration, "sync_dd_to_db('dbx', 'dbxMediaUsage', 'apply')"),
    'The repair migration must enforce and verify the physical language schema.'
);
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
