<?php
$messages = array();
$messages['save_success'] = 'Daten wurden gespeichert';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Daten konnten nicht gespeichert werden';
$messages['dd_not_found'] = 'DD nicht gefunden: {dd}.';
$messages['position_not_found'] = 'Indexposition nicht gefunden: {position}.';
$messages['edit_new'] = 'Neuen Index für DD {dd} bearbeiten.';
$messages['edit_existing'] = 'Index {index} bearbeiten.';
$messages['index_saved'] = 'Index {index} wurde gespeichert.';
$messages['index_save_error'] = 'Index {index} konnte nicht gespeichert werden.';
$messages['index_check'] = 'Bitte Index {index} prüfen.';
$messages['index_not_found'] = 'Index nicht gefunden.';
$messages['index_deleted'] = 'Index gelöscht: {index}.';
$messages['index_delete_error'] = 'Index konnte nicht gelöscht werden: {index}.';
$messages['invalid_index_name'] = 'Ungültiger Indexname: {index}.';
$messages['missing_index_field'] = 'Der Index benötigt mindestens ein Feld.';
$messages['invalid_index_type'] = 'Ungültiger Indextyp: {type}.';
$messages['duplicate_index_name'] = 'Indexname doppelt: {index}.';
$messages['invalid_order'] = 'Ungültige Reihenfolge.';
$messages['order_saved'] = 'Indexreihenfolge wurde gespeichert.';
$messages['order_save_error'] = 'Indexreihenfolge konnte nicht gespeichert werden.';


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
$field['label'] = 'Modul';
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
$field['label'] = 'Alter Indexname';
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
$field['label'] = 'Indexname';
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
$field['label'] = 'Indextyp';
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
$field['label'] = 'Felder';
$field['rules'] = 'text';
$field['tooltip'] = 'Kommagetrennte Feldnamen, z. B. id oder folder,title.';
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
   '0' => 'Nein',
   '1' => 'Ja',
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
$field['label'] = 'Kommentar';
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
