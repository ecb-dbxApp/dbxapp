<?php
$messages = array();
$messages['missing_dd_params'] = 'Kein DD angegeben. Erwartet: modul und dd.';
$messages['dd_unreadable'] = 'DD nicht gefunden oder nicht lesbar: {dd}.';
$messages['dd_not_found'] = 'DD nicht gefunden: {dd}.';
$messages['bar_title'] = 'DD bearbeiten: {dd}';
$messages['bar_subtitle'] = 'Tabellen-Metadaten und Felder pflegen';
$messages['edit_info'] = 'Tabellendaten des DD {dd} bearbeiten.';
$messages['table_saved'] = 'Tabellendaten des DD {dd} wurden gespeichert.';
$messages['table_save_error'] = 'Tabellendaten des DD {dd} konnten nicht gespeichert werden.';
$messages['table_check'] = 'Bitte die Tabellendaten des DD {dd} prüfen.';
$messages['rights_all'] = '*Alle*';


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
$field['name'] = 'server';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '160';
$field['default'] = '';
$field['label'] = 'Server';
$field['rules'] = 'text';
$field['tooltip'] = 'DB-Server oder Modul-DB-Datei, z. B. myLKW.db3.';
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
$field['label'] = 'Tabelle';
$field['rules'] = 'parameter|min=1';
$field['tooltip'] = 'Physischer Tabellenname.';
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
$field['tooltip'] = 'DD-Name. Wird beim Speichern normalerweise aus dem DD-Dateinamen gesetzt.';
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
$field['label'] = 'Primärschlüssel';
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
$field['label'] = 'Sprache';
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
   '1' => 'Mehrsprachig',
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
   '0' => 'Nein',
   '1' => 'Ja',
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
   '0' => 'Nein',
   '1' => 'Ja',
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
$field['label'] = 'Papierkorb';
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
   '0' => 'Nein',
   '1' => 'Ja',
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
$field['tooltip'] = 'Optionales Update-SQL oder leer.';
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
$field['label'] = 'Form-DD-Table';
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
$field['label'] = 'Leserecht';
$field['rules'] = 'array|parameter+*';
$field['tooltip'] = '';
$field['errormsg'] = '';
$field['placeholder'] = '*';
$field['convert'] = '';
$field['protect'] = '0';
$field['group'] = '';
$field['mask'] = '';
$field['data'] = '';
$field['options'] = array('*' => '*Alle*');
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
$field['label'] = 'Anlagerecht';
$field['rules'] = 'array|parameter+*';
$field['tooltip'] = '';
$field['errormsg'] = '';
$field['placeholder'] = '*';
$field['convert'] = '';
$field['protect'] = '0';
$field['group'] = '';
$field['mask'] = '';
$field['data'] = '';
$field['options'] = array('*' => '*Alle*');
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
$field['label'] = 'Änderungsrecht';
$field['rules'] = 'array|parameter+*';
$field['tooltip'] = '';
$field['errormsg'] = '';
$field['placeholder'] = '*';
$field['convert'] = '';
$field['protect'] = '0';
$field['group'] = '';
$field['mask'] = '';
$field['data'] = '';
$field['options'] = array('*' => '*Alle*');
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
$field['label'] = 'Löschrecht';
$field['rules'] = 'array|parameter+*';
$field['tooltip'] = '';
$field['errormsg'] = '';
$field['placeholder'] = '*';
$field['convert'] = '';
$field['protect'] = '0';
$field['group'] = '';
$field['mask'] = '';
$field['data'] = '';
$field['options'] = array('*' => '*Alle*');
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
$field['label'] = 'Eigene lesen';
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
$field['label'] = 'Eigene anlegen';
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
$field['label'] = 'Eigene ändern';
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
$field['label'] = 'Eigene löschen';
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
