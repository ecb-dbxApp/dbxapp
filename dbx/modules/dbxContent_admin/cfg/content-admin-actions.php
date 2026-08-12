<?php
declare(strict_types=1);

$html = static fn(string $handler, bool $mutation = false): array => array(
    'handler' => $handler,
    'methods' => array('GET', 'HEAD', 'POST'),
    'groups' => array('admin'),
    'mutation' => $mutation,
    'response' => 'html',
);

return array(
    'seo' => $html('handle_seo'),
    'seo_page' => $html('handle_seo_page'),
    'seo_save' => $html('handle_seo_save', true),
    'media_view' => $html('handle_media_view'),
    'edit_content' => $html('handle_edit_content'),
    'sysdata' => $html('handle_sysdata'),
    'images' => $html('handle_images'),
    'ibrowser' => $html('handle_ibrowser'),
    'iupload' => $html('handle_iupload'),
    'flat' => $html('handle_flat'),
    'tree' => $html('handle_tree'),
    'list_files' => $html('handle_list_files'),
    'list_folder' => $html('handle_list_folder'),
    'list_folder_files' => $html('handle_list_folder_files'),
    'folder_edit' => $html('handle_folder_edit'),
);
