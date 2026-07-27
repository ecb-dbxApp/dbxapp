<?php
$messages = array();
$messages['save_success'] = 'Data was saved';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Data could not be saved';
$messages['missing_dd_params'] = 'No DD specified. Expected parameters: modul and dd.';
$messages['dd_unreadable'] = 'DD not found or could not be read: {dd}.';
$messages['dd_not_found'] = 'DD not found: {dd}.';
$messages['bar_title'] = 'Edit DD: {dd}';
$messages['bar_subtitle'] = 'Manage table metadata and fields';
$messages['edit_info'] = 'Edit table data for DD {dd}.';
$messages['table_saved'] = 'Table data for DD {dd} was saved.';
$messages['table_save_error'] = 'Table data for DD {dd} could not be saved.';
$messages['table_check'] = 'Please check the table data for DD {dd}.';
$messages['rights_all'] = '*All*';


/**
 * =========================================================
 * DBX ADMIN DDEDIT TABLE FD
 * =========================================================
 *
 * Felddefinitionen fuer das Bearbeiten des $table-Bereichs einer DD-Datei.
 * Diese FD ist fuer dbxEdit_dd gedacht und wird von dbxForm als _fd genutzt.
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
$field['name'] = 'server';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '160';
$field['default'] = '';
$field['label'] = 'Server';
$field['rules'] = 'text';
$field['tooltip'] = 'DB server or module DB file, e.g. myLKW.db3.';
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
$field['name'] = 'table';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '160';
$field['default'] = '';
$field['label'] = 'Table';
$field['rules'] = 'parameter|min=1';
$field['tooltip'] = 'Physical table name.';
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
$field['name'] = 'datadic';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '160';
$field['default'] = '';
$field['label'] = 'Data Dictionary';
$field['rules'] = 'parameter';
$field['tooltip'] = 'DD name. Usually set from the DD filename when saved.';
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
$field['name'] = 'primary';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '80';
$field['default'] = '';
$field['label'] = 'Primary key';
$field['rules'] = 'parameter';
$field['tooltip'] = '';
$field['errormsg'] = '';
$field['placeholder'] = 'id';
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
$field['name'] = 'language';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '4';
$field['default'] = '';
$field['label'] = 'Language';
$field['rules'] = 'parameter';
$field['tooltip'] = '';
$field['errormsg'] = '';
$field['placeholder'] = '';
$field['convert'] = '';
$field['protect'] = '0';
$field['group'] = '';
$field['mask'] = '';
$field['data'] = '';
$field['options'] = array(
   '' => 'Standard',
   '1' => 'Multilingual',
);
$field['tpl'] = 'select-single-label';
$field['js'] = '';
$field['prompt'] = '';
$fields[] = $field;

$field = array();
$field['name'] = 'version';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '32';
$field['default'] = '1.0';
$field['label'] = 'Version';
$field['rules'] = 'parameter+.';
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
$field['name'] = 'autosync';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '1';
$field['default'] = '';
$field['label'] = 'Auto-Sync';
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
$field['name'] = 'cache';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '1';
$field['default'] = '';
$field['label'] = 'Cache';
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
$field['name'] = 'trash';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '1';
$field['default'] = '';
$field['label'] = 'Recycle bin';
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
$field['name'] = 'trace';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '1';
$field['default'] = '';
$field['label'] = 'Trace';
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
$field['name'] = 'update_sql';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '-1';
$field['default'] = '';
$field['label'] = 'Update SQL';
$field['rules'] = 'text';
$field['tooltip'] = 'Optional update SQL or blank.';
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
$field['name'] = 'default_sort';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '160';
$field['default'] = '';
$field['label'] = 'Default Sort';
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

$field = array();
$field['name'] = 'form-dd-table';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '160';
$field['default'] = '';
$field['label'] = 'Form DD table';
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

$field = array();
$field['name'] = 'read';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '160';
$field['default'] = '';
$field['label'] = 'Read permission';
$field['rules'] = 'array|parameter+*';
$field['tooltip'] = '';
$field['errormsg'] = '';
$field['placeholder'] = '*';
$field['convert'] = '';
$field['protect'] = '0';
$field['group'] = '';
$field['mask'] = '';
$field['data'] = '';
$field['options'] = array('*' => '*All*');
$field['tpl'] = 'dbxAdmin|ddedit-rights-select1';
$field['js'] = '';
$field['prompt'] = '';
$fields[] = $field;

$field = array();
$field['name'] = 'create';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '160';
$field['default'] = '';
$field['label'] = 'Create permission';
$field['rules'] = 'array|parameter+*';
$field['tooltip'] = '';
$field['errormsg'] = '';
$field['placeholder'] = '*';
$field['convert'] = '';
$field['protect'] = '0';
$field['group'] = '';
$field['mask'] = '';
$field['data'] = '';
$field['options'] = array('*' => '*All*');
$field['tpl'] = 'dbxAdmin|ddedit-rights-select1';
$field['js'] = '';
$field['prompt'] = '';
$fields[] = $field;

$field = array();
$field['name'] = 'update';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '160';
$field['default'] = '';
$field['label'] = 'Update permission';
$field['rules'] = 'array|parameter+*';
$field['tooltip'] = '';
$field['errormsg'] = '';
$field['placeholder'] = '*';
$field['convert'] = '';
$field['protect'] = '0';
$field['group'] = '';
$field['mask'] = '';
$field['data'] = '';
$field['options'] = array('*' => '*All*');
$field['tpl'] = 'dbxAdmin|ddedit-rights-select1';
$field['js'] = '';
$field['prompt'] = '';
$fields[] = $field;

$field = array();
$field['name'] = 'delete';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '160';
$field['default'] = '';
$field['label'] = 'Delete permission';
$field['rules'] = 'array|parameter+*';
$field['tooltip'] = '';
$field['errormsg'] = '';
$field['placeholder'] = '*';
$field['convert'] = '';
$field['protect'] = '0';
$field['group'] = '';
$field['mask'] = '';
$field['data'] = '';
$field['options'] = array('*' => '*All*');
$field['tpl'] = 'dbxAdmin|ddedit-rights-select1';
$field['js'] = '';
$field['prompt'] = '';
$fields[] = $field;

$field = array();
$field['name'] = 'read_owner';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '160';
$field['default'] = '';
$field['label'] = 'Read own records';
$field['rules'] = 'array|parameter+*';
$field['tooltip'] = '';
$field['errormsg'] = '';
$field['placeholder'] = 'owner,admin';
$field['convert'] = '';
$field['protect'] = '0';
$field['group'] = '';
$field['mask'] = '';
$field['data'] = '';
$field['options'] = array('owner' => 'owner');
$field['tpl'] = 'dbxAdmin|ddedit-rights-select1';
$field['js'] = '';
$field['prompt'] = '';
$fields[] = $field;

$field = array();
$field['name'] = 'create_owner';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '160';
$field['default'] = '';
$field['label'] = 'Create own records';
$field['rules'] = 'array|parameter+*';
$field['tooltip'] = '';
$field['errormsg'] = '';
$field['placeholder'] = 'owner,admin';
$field['convert'] = '';
$field['protect'] = '0';
$field['group'] = '';
$field['mask'] = '';
$field['data'] = '';
$field['options'] = array('owner' => 'owner');
$field['tpl'] = 'dbxAdmin|ddedit-rights-select1';
$field['js'] = '';
$field['prompt'] = '';
$fields[] = $field;

$field = array();
$field['name'] = 'update_owner';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '160';
$field['default'] = '';
$field['label'] = 'Update own records';
$field['rules'] = 'array|parameter+*';
$field['tooltip'] = '';
$field['errormsg'] = '';
$field['placeholder'] = 'owner,admin';
$field['convert'] = '';
$field['protect'] = '0';
$field['group'] = '';
$field['mask'] = '';
$field['data'] = '';
$field['options'] = array('owner' => 'owner');
$field['tpl'] = 'dbxAdmin|ddedit-rights-select1';
$field['js'] = '';
$field['prompt'] = '';
$fields[] = $field;

$field = array();
$field['name'] = 'delete_owner';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '160';
$field['default'] = '';
$field['label'] = 'Delete own records';
$field['rules'] = 'array|parameter+*';
$field['tooltip'] = '';
$field['errormsg'] = '';
$field['placeholder'] = 'owner,admin';
$field['convert'] = '';
$field['protect'] = '0';
$field['group'] = '';
$field['mask'] = '';
$field['data'] = '';
$field['options'] = array('owner' => 'owner');
$field['tpl'] = 'dbxAdmin|ddedit-rights-select1';
$field['js'] = '';
$field['prompt'] = '';
$fields[] = $field;
