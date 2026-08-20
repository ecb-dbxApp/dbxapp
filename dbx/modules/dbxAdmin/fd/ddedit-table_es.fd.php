<?php
$messages = array();
$messages['missing_dd_params'] = 'No se ha indicado ningún DD. Se esperan los parámetros modul y dd.';
$messages['dd_unreadable'] = 'No se encontró el DD o no se pudo leer: {dd}.';
$messages['dd_not_found'] = 'No se encontró el DD: {dd}.';
$messages['bar_title'] = 'Editar DD: {dd}';
$messages['bar_subtitle'] = 'Administrar metadatos y campos de la tabla';
$messages['edit_info'] = 'Editar los datos de tabla del DD {dd}.';
$messages['table_saved'] = 'Se guardaron los datos de tabla del DD {dd}.';
$messages['table_save_error'] = 'No se pudieron guardar los datos de tabla del DD {dd}.';
$messages['table_check'] = 'Compruebe los datos de tabla del DD {dd}.';
$messages['rights_all'] = '*Todos*';


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
$field['name'] = 'server';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '160';
$field['default'] = '';
$field['label'] = 'Servidor';
$field['rules'] = 'text';
$field['tooltip'] = 'Servidor DB o archivo DB módulo, por ejemplo. myLKW.db3.';
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
$field['label'] = 'Tabla';
$field['rules'] = 'parameter|min=1';
$field['tooltip'] = 'Nombre de la mesa física.';
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
$field['label'] = 'Diccionario de datos';
$field['rules'] = 'parameter';
$field['tooltip'] = 'Nombre DD. Normalmente se establece desde el nombre de archivo DD cuando se guarda.';
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
$field['label'] = 'Clave primaria';
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
$field['label'] = 'Idioma';
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
   '1' => 'Multilingüe',
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
$field['label'] = 'Versión';
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
   '1' => 'Sí',
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
   '1' => 'Sí',
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
$field['label'] = 'Papelera de reciclaje';
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
   '1' => 'Sí',
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
$field['label'] = 'SQL de actualización';
$field['rules'] = 'text';
$field['tooltip'] = 'Actualización opcional SQL o en blanco.';
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
$field['label'] = 'Orden predeterminado';
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
$field['label'] = 'Formulario DD de tabla';
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
$field['label'] = 'Permiso de lectura';
$field['rules'] = 'array|parameter+*';
$field['tooltip'] = '';
$field['errormsg'] = '';
$field['placeholder'] = '*';
$field['convert'] = '';
$field['protect'] = '0';
$field['group'] = '';
$field['mask'] = '';
$field['data'] = '';
$field['options'] = array('*' => '*Todo*');
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
$field['label'] = 'Permiso de creación';
$field['rules'] = 'array|parameter+*';
$field['tooltip'] = '';
$field['errormsg'] = '';
$field['placeholder'] = '*';
$field['convert'] = '';
$field['protect'] = '0';
$field['group'] = '';
$field['mask'] = '';
$field['data'] = '';
$field['options'] = array('*' => '*Todo*');
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
$field['label'] = 'Permiso de modificación';
$field['rules'] = 'array|parameter+*';
$field['tooltip'] = '';
$field['errormsg'] = '';
$field['placeholder'] = '*';
$field['convert'] = '';
$field['protect'] = '0';
$field['group'] = '';
$field['mask'] = '';
$field['data'] = '';
$field['options'] = array('*' => '*Todo*');
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
$field['label'] = 'Permiso de eliminación';
$field['rules'] = 'array|parameter+*';
$field['tooltip'] = '';
$field['errormsg'] = '';
$field['placeholder'] = '*';
$field['convert'] = '';
$field['protect'] = '0';
$field['group'] = '';
$field['mask'] = '';
$field['data'] = '';
$field['options'] = array('*' => '*Todo*');
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
$field['label'] = 'Leer registros propios';
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
$field['label'] = 'Crear registros propios';
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
$field['label'] = 'Modificar registros propios';
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
$field['label'] = 'Eliminar registros propios';
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
