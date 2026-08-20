<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);

$response_file = $root . '/dbx/modules/dbxContent/include/dbxContentMediaResponse.class.php';
$fallback_file = $root . '/dbx/modules/dbxContent/dbxContent.class.php';
$response = file_get_contents($response_file);
$fallback = file_get_contents($fallback_file);

if (!is_string($response) || !is_string($fallback)) {
    throw new RuntimeException('Die dbxContent-Medienklassen konnten nicht gelesen werden.');
}

foreach (array(
    "header('ETag: ' . \$etag)",
    "header('Last-Modified: ' . \$last_modified)",
    "header('Cache-Control: private, no-cache')",
    'HTTP_IF_NONE_MATCH',
    'HTTP_IF_MODIFIED_SINCE',
) as $needle) {
    if (!str_contains($response, $needle)) {
        throw new RuntimeException('Der Medien-Cache-Vertrag fehlt: ' . $needle);
    }
}

if (str_contains($response, 'max-age=3600')
    || str_contains($fallback, 'max-age=31536000, immutable')
    || substr_count($fallback, "header('Cache-Control: private, no-cache')") < 2) {
    throw new RuntimeException('Aktualisierte Medien dürfen nicht unter unveränderter ID veralten.');
}

echo "OK dbxContent media responses revalidate changed files with ETag and Last-Modified\n";
