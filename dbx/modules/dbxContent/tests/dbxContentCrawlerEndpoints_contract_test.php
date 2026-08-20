<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$index = (string)file_get_contents(dirname($root) . '/index.php');
$sitemap = (string)file_get_contents(
    dirname(__DIR__) . '/include/dbxContentSitemap.class.php'
);

$fail = static function (string $message): void {
    fwrite(STDERR, $message . "\n");
    exit(1);
};

$endpoint_check = strpos($index, '$dbx_public_crawler_endpoint = dbx_is_public_crawler_endpoint();');
$session_start = strpos($index, 'session_start();');
if ($endpoint_check === false
    || $session_start === false
    || $endpoint_check > $session_start
    || !str_contains($index, 'if (!$dbx_public_crawler_endpoint && session_status()')) {
    $fail('Crawler-Endpunkte muessen vor dem Session-Start erkannt und ausgenommen werden.');
}

foreach (array(
    "array('sitemap.xml', 'robots.txt')",
    "array('sitemap', 'robots')",
    "header('Cache-Control: public, max-age=' . \$max_age)",
    "header('ETag: ' . \$etag)",
    "header('Last-Modified: '",
    "http_response_code(304)",
) as $required) {
    if (!str_contains($index . $sitemap, $required)) {
        $fail('Crawler-Cache-Vertrag fehlt: ' . $required);
    }
}

echo "OK sitemap.xml und robots.txt sind sessionfrei und oeffentlich cachebar.\n";
