<?php
$messages = array();
$messages['dd_not_found'] = 'DD not found: {dd}.';
$messages['position_not_found'] = 'Index position not found: {position}.';
$messages['edit_new'] = 'Edit a new index for DD {dd}.';
$messages['edit_existing'] = 'Edit index {index}.';
$messages['index_saved'] = 'Index {index} was saved.';
$messages['index_save_error'] = 'Index {index} could not be saved.';
$messages['index_check'] = 'Please check index {index}.';
$messages['index_not_found'] = 'Index not found.';
$messages['index_deleted'] = 'Index deleted: {index}.';
$messages['index_delete_error'] = 'Index could not be deleted: {index}.';
$messages['invalid_index_name'] = 'Invalid index name: {index}.';
$messages['missing_index_field'] = 'The index requires at least one field.';
$messages['invalid_index_type'] = 'Invalid index type: {type}.';
$messages['duplicate_index_name'] = 'Duplicate index name: {index}.';
$messages['invalid_order'] = 'Invalid order.';
$messages['order_saved'] = 'Index order was saved.';
$messages['order_save_error'] = 'Index order could not be saved.';


/**
 * =========================================================
 * DBX ADMIN DDEDIT INDEX FD
 * =========================================================
 *
 * Felddefinitionen fuer das Bearbeiten einzelner $indexes[]-Eintraege einer DD-Datei.
 * Index-Felder werden als kommagetrennte Feldliste gepflegt.
 *
 * Verwendung:
 *   $oForm->_fd = 'dbxAdmin|<name-ohne-.fd.php>'; 
 *   $oForm->add_flds();
 *
 * Diese Datei liefert ausschliesslich Felddefinitionen fuer dbxForm.
 * Sie ist keine DB-DD-Datei und erzeugt keine DB-Struktur.
 */

$fields = array();

$field = array();
$field['name'] = 'modul';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '80';
$field['default'] = '';
$field['label'] = 'Module';
$field['rules'] = 'parameter|min=1';
$field['tooltip'] = '';
$field['errormsg'] = '';
$field['placeholder'] = '';
$field['convert'] = '';
$field['protect'] = '0';
$field['group'] = '';
$field['mask'] = '';
$field['data'] = '';
$field['options'] = '';
$field['tpl'] = 'hidden';
$field['js'] = '';
$field['prompt'] = '';
$fields[] = $field;

$field = array();
$field['name'] = 'dd';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '80';
$field['default'] = '';
$field['label'] = 'DD';
$field['rules'] = 'parameter|min=1';
$field['tooltip'] = '';
$field['errormsg'] = '';
$field['placeholder'] = '';
$field['convert'] = '';
$field['protect'] = '0';
$field['group'] = '';
$field['mask'] = '';
$field['data'] = '';
$field['options'] = '';
$field['tpl'] = 'hidden';
$field['js'] = '';
$field['prompt'] = '';
$fields[] = $field;

$field = array();
$field['name'] = 'index_pos';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '32';
$field['default'] = '';
$field['label'] = 'Position';
$field['rules'] = 'parameter';
$field['tooltip'] = '';
$field['errormsg'] = '';
$field['placeholder'] = '';
$field['convert'] = '';
$field['protect'] = '0';
$field['group'] = '';
$field['mask'] = '';
$field['data'] = '';
$field['options'] = '';
$field['tpl'] = 'hidden';
$field['js'] = '';
$field['prompt'] = '';
$fields[] = $field;

$field = array();
$field['name'] = 'old_name';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '160';
$field['default'] = '';
$field['label'] = 'Previous index name';
$field['rules'] = 'text';
$field['tooltip'] = '';
$field['errormsg'] = '';
$field['placeholder'] = '';
$field['convert'] = '';
$field['protect'] = '0';
$field['group'] = '';
$field['mask'] = '';
$field['data'] = '';
$field['options'] = '';
$field['tpl'] = 'hidden';
$field['js'] = '';
$field['prompt'] = '';
$fields[] = $field;

$field = array();
$field['name'] = 'name';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '160';
$field['default'] = '';
$field['label'] = 'Index name';
$field['rules'] = 'parameter|min=1';
$field['tooltip'] = '';
$field['errormsg'] = '';
$field['placeholder'] = '';
$field['convert'] = '';
$field['protect'] = '0';
$field['group'] = '';
$field['mask'] = '';
$field['data'] = '';
$field['options'] = '';
$field['tpl'] = 'text-label';
$field['js'] = '';
$field['prompt'] = '';
$fields[] = $field;

$field = array();
$field['name'] = 'type';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '40';
$field['default'] = 'INDEX';
$field['label'] = 'Index type';
$field['rules'] = 'parameter|min=1';
$field['tooltip'] = '';
$field['errormsg'] = '';
$field['placeholder'] = '';
$field['convert'] = '';
$field['protect'] = '0';
$field['group'] = '';
$field['mask'] = '';
$field['data'] = '';
$field['options'] = array(
   'PRIMARY' => 'PRIMARY',
   'INDEX' => 'INDEX',
   'UNIQUE' => 'UNIQUE',
   'FULLTEXT' => 'FULLTEXT',
);
$field['tpl'] = 'select-single-label';
$field['js'] = '';
$field['prompt'] = '';
$fields[] = $field;

$field = array();
$field['name'] = 'fields';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '255';
$field['default'] = '';
$field['label'] = 'Fields';
$field['rules'] = 'text';
$field['tooltip'] = 'Comage-separated field names, such as id or folder,title.';
$field['errormsg'] = '';
$field['placeholder'] = '';
$field['convert'] = '';
$field['protect'] = '0';
$field['group'] = '';
$field['mask'] = '';
$field['data'] = '';
$field['options'] = '';
$field['tpl'] = 'text-label';
$field['js'] = '';
$field['prompt'] = '';
$fields[] = $field;

$field = array();
$field['name'] = 'unique';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '1';
$field['default'] = '0';
$field['label'] = 'Unique';
$field['rules'] = 'int';
$field['tooltip'] = '';
$field['errormsg'] = '';
$field['placeholder'] = '';
$field['convert'] = '';
$field['protect'] = '0';
$field['group'] = '';
$field['mask'] = '';
$field['data'] = '';
$field['options'] = array(
   '0' => 'No',
   '1' => 'Yes',
);
$field['tpl'] = 'select-single-label';
$field['js'] = '';
$field['prompt'] = '';
$fields[] = $field;

$field = array();
$field['name'] = 'comment';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '255';
$field['default'] = '';
$field['label'] = 'Comment';
$field['rules'] = 'text';
$field['tooltip'] = '';
$field['errormsg'] = '';
$field['placeholder'] = '';
$field['convert'] = '';
$field['protect'] = '0';
$field['group'] = '';
$field['mask'] = '';
$field['data'] = '';
$field['options'] = '';
$field['tpl'] = 'text-label';
$field['js'] = '';
$field['prompt'] = '';
$fields[] = $field;
