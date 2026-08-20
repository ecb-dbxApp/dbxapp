<?php

declare(strict_types=1);

$source = (string)file_get_contents(dirname(__DIR__) . '/include/dbxContent_menu.class.php');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(
    str_contains($source, 'dbxContentPageCache::current_lng()')
        && str_contains($source, "get_cfg('dbx', 'default_lng', 'de')")
        && str_contains($source, "get_cfg('dbx', 'language_path_prefix', 0)")
        && str_contains($source, "\$language_prefix = \$use_language_path ? \$language . '/' : ''")
        && str_contains($source, "dbx()->get_base_url() . \$language_prefix . dbxContent_permalink::public_path(\$permalink)"),
    'CMS-Menülinks müssen bei aktivierten Sprachpfaden den Präfix der Nicht-Standardsprache erhalten.'
);

echo "OK CMS-Menülinks berücksichtigen den konfigurierten Sprachpfad.\n";
