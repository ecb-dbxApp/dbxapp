<?php

$base = dirname(__DIR__, 2);
require_once __DIR__ . '/dbxCssTestReader.php';
$failures = array();

$assert = static function(bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

require_once $base . '/modules/dbxContent/include/dbxContentRenderer.class.php';
$renderer_class = new ReflectionClass(\dbx\dbxContent\dbxContentRenderer::class);
$renderer = $renderer_class->newInstanceWithoutConstructor();
$video_options = $renderer_class->getMethod('inline_video_options_from_html');
$video_options->setAccessible(true);
$options = (array)$video_options->invoke(
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
$video_player = $renderer_class->getMethod('render_inline_video_player');
$video_player->setAccessible(true);
$player_html = (string)$video_player->invoke(
    $renderer,
    array('id' => 999, 'mime' => 'video/mp4', 'file_name' => 'test.mp4', 'title' => 'Testvideo'),
    ' data-cms-video-width="640px" data-cms-video-align="center"',
    ''
);
$assert(
    str_contains($player_html, 'data-video-align="center"')
        && str_contains($player_html, 'margin-left: auto')
        && str_contains($player_html, 'width: 640px'),
    'The rendered frontend player does not receive the centered video alignment.'
);

foreach (array('dbxapp', 'steal') as $design) {
    $base_css = dbx_test_read_css($base . '/design/' . $design . '/css/base.css');
    $content_css = dbx_test_read_css($base . '/design/' . $design . '/css/c-content.css');
    $cms_css = dbx_test_read_css($base . '/design/' . $design . '/css/c-cms.css');
    $assert(
        str_contains($content_css, '.ratio:has(> .dbx-content-inline-video[style*="width"])')
            && str_contains($content_css, '--bs-aspect-ratio: 0;')
            && str_contains($content_css, 'width: fit-content;'),
        'Custom frontend video sizes remain constrained by a ratio container in design ' . $design . '.'
    );
    $assert(
        str_contains($cms_css, '.ratio:has(> .dbx-cms-inline-video-block[style*="width"])')
            && str_contains($cms_css, 'resize: both;'),
        'Custom CMS video sizes remain constrained by a ratio container in design ' . $design . '.'
    );
    $assert(
        str_contains($content_css, '.dbx-content-inline-video[data-video-align="center"]')
            && str_contains($content_css, '.ratio:has(> .dbx-content-inline-video[data-video-align="center"])')
            && str_contains($cms_css, '.dbx-cms-inline-video-block[data-cms-video-align="center"]')
            && str_contains($cms_css, '.ratio:has(> .dbx-cms-inline-video-block[data-cms-video-align="center"])'),
        'Centered videos are not aligned in the editor and frontend for design ' . $design . '.'
    );
    $assert(
        str_contains($base_css, '.ratio:has(> .dbx-content-inline-video[style*="width"])')
            && str_contains($base_css, '.dbx-content-inline-video[data-video-align="center"]')
            && str_contains($base_css, '.ratio:has(> .dbx-content-inline-video[data-video-align="center"])'),
        'Critical centered-video layout is missing before async content CSS loads in design ' . $design . '.'
    );
    $assert(
        !preg_match('/\.dbx-content-inline-video\s*\{[^}]*container-type:\s*size\s*;/s', $content_css)
            && !preg_match('/\.dbx-cms-inline-video-block\s*\{[^}]*container-type:\s*size\s*;/s', $cms_css),
        'Auto-height videos collapse because of two-axis size containment in design ' . $design . '.'
    );
    $assert(
        str_contains($content_css, '#dbxContent .dbx-content-inline-video[style*="height"] .dbx-content-video-player')
            && str_contains($cms_css, '#dbxContent .dbx-cms-inline-video-block[style*="height"] .dbx-cms-inline-video-thumb'),
        'Explicit video heights do not override the generic responsive media height in design ' . $design . '.'
    );
    $assert(
        str_contains($content_css, '@media (max-width: 575.98px)')
            && str_contains($content_css, '.dbx-content-inline-video[style*="height"]')
            && str_contains($content_css, 'height: auto !important;')
            && str_contains($content_css, 'object-fit: contain;')
            && str_contains($base_css, '@media (max-width: 575.98px)')
            && str_contains($base_css, '.dbx-content-inline-video[style*="height"]'),
        'Fixed CMS video heights are not converted to a responsive mobile player in design ' . $design . '.'
    );
}

$cms_js = (string)file_get_contents($base . '/modules/dbxContent_admin/js/cms.js')
    . (string)file_get_contents($base . '/modules/dbxContent_admin/js/cms-editor.js');
$assert(
    str_contains($cms_js, 'let width = wrapperWidth || size.width;')
        && str_contains($cms_js, 'let height = wrapperHeight || size.height;'),
    'The CMS size synchronizer does not treat the saved video wrapper size as authoritative.'
);
$assert(
    str_contains($cms_js, 'data-cms-video-options-align')
        && str_contains($cms_js, 'applyInlineVideoAlignment(media, align);'),
    'The CMS video dialog does not expose and apply horizontal alignment.'
);

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo 'OK CMS video sizes are preserved in the editor and frontend.' . PHP_EOL;
