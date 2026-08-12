<?php

declare(strict_types=1);

$html = static fn(string $handler, bool $mutation = false): array => array(
    'handler' => $handler,
    'methods' => array('GET', 'HEAD', 'POST'),
    'groups' => array('admin'),
    'mutation' => $mutation,
    'response' => 'html',
);
$json = static fn(string $handler, bool $mutation = false): array => array(
    'handler' => $handler,
    'methods' => array('GET', 'HEAD', 'POST'),
    'groups' => array('admin'),
    'mutation' => $mutation,
    'response' => 'json',
);

return array(
    'report_dd' => $html('report_dd'), 'report_db' => $html('report_db'),
    'sync_dd_to_db' => $html('run_sync_dd_to_db', true),
    'sync_db_to_dd' => $html('run_sync_db_to_dd', true),
    'transfer' => $html('run_transfer', true),
    'backup_db_table' => $html('run_backup_db_table', true),
    'restore_db_table' => $html('run_restore_db_table', true),
    'mapping' => $html('run_mapping_editor'), 'fields' => $html('run_dd_fields_grid'),
    'data_read' => $json('run_data_read'), 'data_save' => $json('run_data_save', true),
    'data_insert' => $json('run_data_insert', true), 'data_delete' => $json('run_data_delete', true),
    'fields_read' => $json('run_dd_fields_read'), 'fields_save' => $json('run_dd_fields_save', true),
    'fields_insert' => $json('run_dd_fields_insert', true), 'fields_delete' => $json('run_dd_fields_delete', true),
    'batch' => $html('run_batch', true), 'backup_db' => $html('report_backup_db'),
    'restore_db' => $html('report_restore_db'),
);
