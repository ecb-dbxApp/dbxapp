<?php
$messages = array();
$messages['save_success'] = 'Los datos se guardaron';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Los datos no se pudieron guardar';
$messages['dd_not_found'] = 'No se encontró el DD: {dd}.';
$messages['position_not_found'] = 'No se encontró la posición del índice: {position}.';
$messages['edit_new'] = 'Editar un índice nuevo para el DD {dd}.';
$messages['edit_existing'] = 'Editar el índice {index}.';
$messages['index_saved'] = 'Se guardó el índice {index}.';
$messages['index_save_error'] = 'No se pudo guardar el índice {index}.';
$messages['index_check'] = 'Compruebe el índice {index}.';
$messages['index_not_found'] = 'No se encontró el índice.';
$messages['index_deleted'] = 'Índice eliminado: {index}.';
$messages['index_delete_error'] = 'No se pudo eliminar el índice: {index}.';
$messages['invalid_index_name'] = 'Nombre de índice no válido: {index}.';
$messages['missing_index_field'] = 'El índice requiere al menos un campo.';
$messages['invalid_index_type'] = 'Tipo de índice no válido: {type}.';
$messages['duplicate_index_name'] = 'Nombre de índice duplicado: {index}.';
$messages['invalid_order'] = 'El orden no es válido.';
$messages['order_saved'] = 'Se guardó el orden de los índices.';
$messages['order_save_error'] = 'No se pudo guardar el orden de los índices.';


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
$field['label'] = 'Módulo';
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
$field['label'] = 'Posición';
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
$field['label'] = 'Nombre anterior del índice';
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
$field['label'] = 'Nombre del índice';
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
$field['label'] = 'Tipo de índice';
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
$field['label'] = 'Campos';
$field['rules'] = 'text';
$field['tooltip'] = 'Nombres de campo separados, como id o carpeta,title.';
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
$field['label'] = 'Único';
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
   '1' => 'Sí',
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
$field['label'] = 'Comentario';
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
