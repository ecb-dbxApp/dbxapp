<?php

$base = dirname(__DIR__, 2);
$failures = array();

$assert = static function(bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

require_once $base . '/modules/dbxContent/include/dbxContentRenderer.class.php';
$rendererClass = new ReflectionClass(\dbx\dbxContent\dbxContentRenderer::class);
$renderer = $rendererClass->newInstanceWithoutConstructor();
$videoOptions = $rendererClass->getMethod('inline_video_options_from_html');
$videoOptions->setAccessible(true);
$options = (array)$videoOptions->invoke(
    $renderer,
    ' class="dbx-cms-inline-video-block" data-cms-video-width="1024" data-cms-video-height="800" data-cms-video-align="center"',
    ''
);

$assert(
    str_contains((string)($options['style'] ?? ''), 'width: 1024px')
        && str_contains((string)($options['style'] ?? ''), 'height: 800px'),
    'The frontend renderer does not preserve a CMS video size of 1024 x 800.'
);
$assert(
    ($options['align'] ?? '') === 'center'
        && str_contains((string)($options['style'] ?? ''), 'margin-left: auto')
        && str_contains((string)($options['style'] ?? ''), 'margin-right: auto'),
    'The frontend renderer does not preserve horizontal video centering.'
);
$videoPlayer = $rendererClass->getMethod('render_inline_video_player');
$videoPlayer->setAccessible(true);
$playerHtml = (string)$videoPlayer->invoke(
    $renderer,
    array('id' => 999, 'mime' => 'video/mp4', 'file_name' => 'test.mp4', 'title' => 'Testvideo'),
    ' data-cms-video-width="640px" data-cms-video-align="center"',
    ''
);
$assert(
    str_contains($playerHtml, 'data-video-align="center"')
        && str_contains($playerHtml, 'margin-left: auto')
        && str_contains($playerHtml, 'width: 640px'),
    'The rendered frontend player does not receive the centered video alignment.'
);

foreach (array('dbxapp', 'dbxdocs', 'steal') as $design) {
    $baseCss = (string)file_get_contents($base . '/design/' . $design . '/css/base.css');
    $contentCss = (string)file_get_contents($base . '/design/' . $design . '/css/c-content.css');
    $cmsCss = (string)file_get_contents($base . '/design/' . $design . '/css/c-cms.css');
    $assert(
        str_contains($contentCss, '.ratio:has(> .dbx-content-inline-video[style*="width"])')
            && str_contains($contentCss, '--bs-aspect-ratio: 0;')
            && str_contains($contentCss, 'width: fit-content;'),
        'Custom frontend video sizes remain constrained by a ratio container in design ' . $design . '.'
    );
    $assert(
        str_contains($cmsCss, '.ratio:has(> .dbx-cms-inline-video-block[style*="width"])')
            && str_contains($cmsCss, 'resize: both;'),
        'Custom CMS video sizes remain constrained by a ratio container in design ' . $design . '.'
    );
    $assert(
        str_contains($contentCss, '.dbx-content-inline-video[data-video-align="center"]')
            && str_contains($contentCss, '.ratio:has(> .dbx-content-inline-video[data-video-align="center"])')
            && str_contains($cmsCss, '.dbx-cms-inline-video-block[data-cms-video-align="center"]')
            && str_contains($cmsCss, '.ratio:has(> .dbx-cms-inline-video-block[data-cms-video-align="center"])'),
        'Centered videos are not aligned in the editor and frontend for design ' . $design . '.'
    );
    $assert(
        str_contains($baseCss, '.ratio:has(> .dbx-content-inline-video[style*="width"])')
            && str_contains($baseCss, '.dbx-content-inline-video[data-video-align="center"]')
            && str_contains($baseCss, '.ratio:has(> .dbx-content-inline-video[data-video-align="center"])'),
        'Critical centered-video layout is missing before async content CSS loads in design ' . $design . '.'
    );
    $assert(
        !preg_match('/\.dbx-content-inline-video\s*\{[^}]*container-type:\s*size\s*;/s', $contentCss)
            && !preg_match('/\.dbx-cms-inline-video-block\s*\{[^}]*container-type:\s*size\s*;/s', $cmsCss),
        'Auto-height videos collapse because of two-axis size containment in design ' . $design . '.'
    );
    $assert(
        str_contains($contentCss, '#dbxContent .dbx-content-inline-video[style*="height"] .dbx-content-video-player')
            && str_contains($cmsCss, '#dbxContent .dbx-cms-inline-video-block[style*="height"] .dbx-cms-inline-video-thumb'),
        'Explicit video heights do not override the generic responsive media height in design ' . $design . '.'
    );
}

$cmsJs = (string)file_get_contents($base . '/js/lib/cms.js');
$assert(
    str_contains($cmsJs, 'let width = wrapperWidth || size.width;')
        && str_contains($cmsJs, 'let height = wrapperHeight || size.height;'),
    'The CMS size synchronizer does not treat the saved video wrapper size as authoritative.'
);
$assert(
    str_contains($cmsJs, 'data-cms-video-options-align')
        && str_contains($cmsJs, 'applyInlineVideoAlignment(media, align);'),
    'The CMS video dialog does not expose and apply horizontal alignment.'
);

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo 'OK CMS video sizes are preserved in the editor and frontend.' . PHP_EOL;
