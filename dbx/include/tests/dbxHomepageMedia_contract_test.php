<?php
declare(strict_types=1);

$base = dirname(__DIR__, 3);
chdir($base);
$_SERVER['REQUEST_URI'] = '/dbxapp/';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SCRIPT_NAME'] = '/dbxapp/index.php';

define('dbxSystem', 'dbxWebApp');
define('dbxRunAsAdmin', 1);

require $base . '/dbx/vendor/autoload.php';
require_once $base . '/dbx/include/dbxKernel.php';

$db = dbx()->get_system_obj('dbxDB');
$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$hasRuntimeFixtures = is_dir($base . '/.git')
    && is_file($base . '/dbx/modules/dbx/db/dbxContent.db3')
    && filesize($base . '/dbx/modules/dbx/db/dbxContent.db3') > 0
    && is_file($base . '/dbx/modules/dbx/db/dbxMedia.db3')
    && filesize($base . '/dbx/modules/dbx/db/dbxMedia.db3') > 0;

if ($hasRuntimeFixtures) {
$pages = $db->select(
    'dbx|content_de',
    array('permalink' => 'home'),
    '*',
    'id',
    'ASC',
    '',
    1,
    0,
    0
);
$page = is_array($pages) && isset($pages[0]) ? $pages[0] : array();
$content = (string)($page['content'] ?? '');

$assert((int)($page['id'] ?? 0) > 0, 'German homepage is missing.');
$assert(
    (string)($page['template'] ?? '') === 'c-title-hero_header-body1-footer'
        && (string)($page['hero_template'] ?? '') === 'image-hero'
        && (int)($page['hero_image_id'] ?? 0) === 166,
    'Homepage does not use the CMS Hero template and Hero media #166.'
);
$heroMarkerPosition = strpos($content, 'data-dbx-marker="dbx:hero"');
$heroTextPosition = strpos($content, 'Eine Plattform. Klare Abläufe.');
$assert(
    $heroTextPosition !== false
        && $heroMarkerPosition !== false
        && $heroTextPosition < $heroMarkerPosition,
    'Homepage Hero text is not stored before the dbx:hero marker.'
);
$assert(
    !str_contains($content, 'dbx_mid=166') && !str_contains($content, 'data-cms-media-id="166"'),
    'Hero media #166 must not be embedded as an inline image.'
);
$assert(str_contains($content, 'Eine Plattform in Bewegung.'), 'Homepage video introduction is missing.');
$assert(
    str_contains($content, 'Bestehende Systeme lassen sich leicht nachbilden und optimieren.'),
    'Required statement about rebuilding and optimizing existing systems is missing.'
);
$videoIntroPosition = strpos($content, 'Eine Plattform in Bewegung.');
$videoPosition = strpos($content, 'dbx-cms-inline-video-block');
$assert(
    str_contains($content, '<div class="row g-4 mb-5">')
        && substr_count($content, '<div class="col-12">') >= 2
        && $videoIntroPosition !== false
        && $videoPosition !== false
        && $videoIntroPosition < $videoPosition,
    'Homepage video introduction and video are not arranged as editable stacked columns.'
);
foreach (array(371, 432) as $mediaId) {
    $assert(
        str_contains($content, 'dbx_mid=' . $mediaId),
        'Homepage does not reference replacement media #' . $mediaId . '.'
    );
}

$usageRows = $db->select(
    'dbx|dbxMediaUsage',
    'content_id = ' . (int)($page['id'] ?? 0) . ' AND active = 1',
    '*',
    'sorter,id',
    'ASC',
    '',
    0,
    0,
    0
);
$heroUsage = array_values(array_filter(is_array($usageRows) ? $usageRows : array(), static function (array $row): bool {
    return (string)($row['slot'] ?? '') === 'hero' && (int)($row['media_id'] ?? 0) === 166;
}));
$assert(count($heroUsage) === 1, 'Homepage must have exactly one active Hero usage for media #166.');

$expected = array(
    166 => 'media/img/hero/dbxapp-platform-hero-20260728.webp',
    371 => 'media/img/images/dbxapp-systeme-neu-denken-20260728.webp',
    432 => 'media/img/images/dbxapp-modular-wachsen-20260728.webp',
);
foreach ($expected as $mediaId => $path) {
    $rows = $db->select('dbx|dbxMedia', array('id' => $mediaId), '*', 'id', 'ASC', '', 1, 0, 0);
    $row = is_array($rows) && isset($rows[0]) ? $rows[0] : array();
    $assert((string)($row['file_path'] ?? '') === $path, 'Unexpected file path for media #' . $mediaId . '.');
    $assert(is_file($base . '/files/' . $path), 'Replacement file is missing: ' . $path);
}

$videos = $db->select(
    'dbx|dbxMedia',
    array('file_path' => 'media/video/dbxapp-tvspot-20260731-v4.mp4'),
    '*',
    'id',
    'ASC',
    '',
    1,
    0,
    0
);
$video = is_array($videos) && isset($videos[0]) ? $videos[0] : array();
$videoId = (int)($video['id'] ?? 0);
$videoFile = $base . '/files/' . (string)($video['file_path'] ?? '');
$assert($videoId > 0, 'Homepage video is not registered in dbxMedia.');
$assert(str_contains($content, 'dbx_mid=' . $videoId), 'Homepage does not reference the registered video.');
$assert(
    preg_match(
        '/<figure\b[^>]*\bdbx-cms-inline-video-block\b[^>]*\bdata-cms-media-id="' . $videoId . '"[^>]*>/i',
        $content
    ) === 1,
    'Homepage video does not use the CMS-stable inline-video placeholder.'
);

$inlineUsageIds = array_values(array_unique(array_map(
    static fn(array $row): int => (int)($row['media_id'] ?? 0),
    array_filter(is_array($usageRows) ? $usageRows : array(), static fn(array $row): bool => (string)($row['slot'] ?? '') === 'inline')
)));
sort($inlineUsageIds);
$expectedInlineUsageIds = array($videoId, 371, 432);
sort($expectedInlineUsageIds);
$assert($inlineUsageIds === $expectedInlineUsageIds, 'Homepage inline media usages do not match the actual editor content.');
$assert(is_file($videoFile), 'Homepage video file is missing.');
$assert(!is_file($videoFile) || filesize($videoFile) <= 12 * 1024 * 1024, 'Homepage video exceeds 12 MiB.');
$assert(
    (string)($video['thumb_file_path'] ?? '') === 'media/img/images/dbxapp-tvspot-poster-20260731-v4.webp',
    'Homepage video poster is not registered as the video thumbnail.'
);
$assert(
    is_file($base . '/files/media/video/dbxapp-tvspot-20260731-v4-license.txt'),
    'Homepage TV spot license provenance is missing.'
);
$assert(
    is_file($base . '/files/media/video/dbxapp-tvspot-20260731-v4-manifest.json'),
    'Homepage TV spot build manifest is missing.'
);
} else {
    // Ein öffentlicher Checkout enthält absichtlich weder Inhaltsdatenbank
    // noch Medien. Die portablen CMS-/Renderer-Verträge werden trotzdem
    // vollständig geprüft.
    $videoId = 777;
}

require_once $base . '/dbx/modules/dbxContent_admin/include/dbxContent_cms.class.php';
if ($hasRuntimeFixtures) {
    $cmsClass = new ReflectionClass(\dbx\dbxContent_admin\dbxContent_cms::class);
    $cms = $cmsClass->newInstanceWithoutConstructor();
    $normalize = $cmsClass->getMethod('normalize_content_media_urls');
    $normalize->setAccessible(true);
    $legacyVideo = '<div class="ratio ratio-16x9"><video controls>'
        . '<source src="index.php?dbx_modul=dbxContent&amp;dbx_run1=media&amp;dbx_mid=' . $videoId . '" type="video/mp4">'
        . '</video></div>';
    $normalizedVideo = (string)$normalize->invoke($cms, $legacyVideo);
    $assert(
        str_contains($normalizedVideo, 'dbx-cms-inline-video-block')
            && str_contains($normalizedVideo, 'data-cms-media-id="' . $videoId . '"'),
        'Legacy CMS video markup is not converted to a stable media placeholder.'
    );
    $placeholderWithoutWrapperId = '<figure class="dbx-cms-inline-media dbx-cms-inline-video-block">'
        . '<img src="index.php?dbx_modul=dbxContent&amp;dbx_run1=media&amp;dbx_mid=' . $videoId . '"'
        . ' data-cms-media-id="' . $videoId . '"></figure>';
    $normalizedPlaceholder = (string)$normalize->invoke($cms, $placeholderWithoutWrapperId);
    $assert(
        preg_match(
            '/<figure\b[^>]*\bdbx-cms-inline-video-block\b[^>]*\bdata-cms-media-id="' . $videoId . '"[^>]*>/i',
            $normalizedPlaceholder
        ) === 1,
        'CMS video wrapper does not recover its media ID from the contained placeholder.'
    );
} else {
    $cmsSource = (string)file_get_contents(
        $base . '/dbx/modules/dbxContent_admin/include/dbxContent_cms.class.php'
    );
    $assert(
        str_contains($cmsSource, 'normalize_content_media_urls')
            && str_contains($cmsSource, 'dbx-cms-inline-video-block')
            && str_contains($cmsSource, 'data-cms-media-id'),
        'Portable CMS video normalization contract is incomplete.'
    );
}

foreach (array('dbxapp', 'dbxdocs', 'steal') as $design) {
    $contentCss = (string)file_get_contents($base . '/dbx/design/' . $design . '/css/c-content.css');
    $cmsCss = (string)file_get_contents($base . '/dbx/design/' . $design . '/css/c-cms.css');
    $assert(
        str_contains($contentCss, '.ratio > .dbx-content-inline-video')
            && str_contains($contentCss, 'position: absolute;'),
        'Frontend video is not compatible with ratio containers in design ' . $design . '.'
    );
    $assert(
        str_contains($cmsCss, '.jodit-wysiwyg .ratio > .dbx-cms-inline-video-block')
            && str_contains($cmsCss, 'resize: none;'),
        'CMS video preview is not compatible with ratio containers in design ' . $design . '.'
    );
}

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo ($hasRuntimeFixtures
    ? 'OK homepage uses the native CMS Hero, matching inline media usages and a registered web video.'
    : 'OK portable homepage media contracts; local content/media fixtures are not part of the public checkout.')
    . PHP_EOL;
