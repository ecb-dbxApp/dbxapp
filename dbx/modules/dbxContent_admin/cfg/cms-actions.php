<?php

declare(strict_types=1);

$route = static fn(string $handler, bool $mutation, bool $token = false, array $extra = array()): array => array_replace(array(
    'handler' => $handler,
    'methods' => array('GET', 'HEAD', 'POST'),
    'groups' => array('admin'),
    'mutation' => $mutation,
    'token' => $token,
    'response' => 'json',
), $extra);
$read = static fn(string $handler): array => $route($handler, false, false);
$write = static fn(string $handler): array => $route($handler, true, true);

return array(
    'cms_tree' => $read('tree_json'),
    'cms_lng_coverage' => $read('lng_coverage_json'),
    'cms_lng_preview' => $read('lng_preview_json'),
    'cms_lng_provision' => $write('lng_provision_json'),
    'cms_lng_provision_tree' => $write('lng_provision_tree_json'),
    'cms_lng_reset_sync' => $write('lng_reset_sync_json'),
    'cms_lng_delete_preview' => $read('lng_delete_preview_json'),
    'cms_page' => $read('page_json'),
    'cms_save' => $write('save_json'),
    'cms_new_page' => $write('new_page_json'),
    'cms_duplicate_page' => $write('duplicate_page_json'),
    'cms_new_folder' => $write('new_folder_json'),
    'cms_save_folder' => $write('save_folder_json'),
    'cms_delete_folder' => $write('delete_folder_json'),
    'cms_delete_page' => $write('delete_page_json'),
    'cms_move_node' => $write('move_node_json'),
    'cms_media' => $route('media_json', false, true, array('conditional_token' => true)),
    'cms_media_process' => $write('media_process'),
    'cms_upload' => $write('upload_json'),
    'cms_external_video' => $write('external_video_json'),
    'cms_media_folders' => $read('media_folders_json'),
    'cms_media_folder_create' => $write('media_folder_create_json'),
    'cms_media_folder_delete' => $write('media_folder_delete_json'),
    'cms_media_folder_rename' => $write('media_folder_rename_json'),
    'cms_media_move' => $write('media_move_json'),
    'cms_media_unused' => $write('media_unused_json'),
    'cms_remove_media' => $write('remove_media_json'),
    'cms_delete_media' => $write('delete_media_json'),
    'cms_edit_media' => $write('edit_media_json'),
    'cms_set_media_slot' => $write('set_media_slot_json'),
    'cms_assign_media' => $write('assign_media_json'),
    'cms_sort_media' => $write('sort_media_json'),
    'cms_mod_catalog' => $read('mod_catalog_json'),
    'cms_mod_modules' => $read('mod_modules_json'),
);
