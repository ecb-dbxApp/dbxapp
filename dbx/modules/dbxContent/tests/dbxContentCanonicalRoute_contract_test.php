<?php

declare(strict_types=1);

$root = dirname(__DIR__, 4);
$service = (string)file_get_contents(
    $root . '/dbx/modules/dbxContent/include/dbxContentCanonicalRoute.class.php'
);
$menu = (string)file_get_contents($root . '/dbx/modules/dbxMenu/dbxMenu.class.php');
$redirect = (string)file_get_contents($root . '/dbx/include/dbxWebAppRedirect.trait.php');
$pipeline = (string)file_get_contents($root . '/dbx/include/dbxRequestPipeline.class.php');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(
    str_contains($service, "dbxContentLng::dd_content(\$target_lng)")
        && str_contains($service, "array('lng_uid' => \$lng_uid, 'activ' => 1)")
        && str_contains($service, "get_cfg('dbx', 'language_path_prefix', 0)"),
    'Kanonische Sprachrouten müssen die aktive Schwesterseite per lng_uid auflösen.'
);
$assert(
    str_contains($menu, "get_include_obj('dbxContentCanonicalRoute', 'dbxContent')")
        && str_contains($menu, "\$routes->page_url(\$cid, \$source_lng"),
    'Das allgemeine Sprachmenü verwendet nicht die kanonische Content-Sprachroute.'
);
$assert(
    str_contains($redirect, 'apply_canonical_content_redirect')
        && str_contains($redirect, "unset(\$query['dbx_lng'])")
        && str_contains($redirect, "header('Location: ' . \$target, true, 302)"),
    'Nichtkanonische Content- und Sprach-URLs werden nicht vereinheitlicht.'
);
$assert(
    str_contains($pipeline, '$web_app->apply_canonical_content_redirect()'),
    'Der kanonische Content-Redirect fehlt in der Request-Pipeline.'
);

echo "OK kanonische Content- und Sprachrouten sind zentral eingebunden.\n";
