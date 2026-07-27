<?php
$messages = array();
$messages['save_success'] = 'Los datos se guardaron';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Los datos no se pudieron guardar';
$messages['validation_error'] = 'Revise los datos introducidos.';
$messages['bar_title'] = 'Vinculaciones de módulos de flujo de trabajo';
$messages['bar_subtitle'] = 'Conexiones de módulos basadas en DD';
$messages['module_bindings'] = 'Vinculaciones de módulos';
$messages['new_binding'] = 'Nueva vinculación';
$messages['new_workflow'] = 'Nuevo flujo de trabajo';
$messages['list_info'] = 'Administre las vinculaciones de módulos. Los flujos de trabajo referencian bind_ref (modul|bind_key); los módulos no conocen el flujo de trabajo.';
$messages['column_module'] = 'Módulo';
$messages['column_bind_key'] = 'Clave de vinculación';
$messages['column_title'] = 'Título';
$messages['column_active'] = 'Activo';
$messages['column_update'] = 'Actualizado';
$messages['column_reference'] = 'Referencia';
$messages['column_action'] = 'Acción';
$messages['form_new_title'] = 'Nueva vinculación de módulo';
$messages['form_edit_title'] = 'Editar vinculación de módulo';
$messages['form_subtitle'] = 'Conexión de módulo del flujo de trabajo';
$messages['form_new_info'] = 'Cree una nueva vinculación o genérela a partir de una DD del módulo.';
$messages['form_edit_info'] = 'Edite la vinculación de módulo. Referencia en los flujos de trabajo: bind_ref = modul|bind_key';
$messages['list_label'] = 'Lista';
$messages['save_label'] = 'Guardar';
$messages['generator_module_label'] = 'Módulo';
$messages['generator_dd_label'] = 'Datadic (DD)';
$messages['generator_dd_select'] = '-- Seleccionar DD --';
$messages['generator_success'] = 'Se generó una propuesta de vinculación. Revísela y guárdela.';
$messages['generator_error'] = 'No se pudo generar ninguna vinculación a partir de la DD seleccionada.';
$messages['generator_validation_error'] = 'Seleccione un módulo y un Datadic.';
$messages['json_invalid'] = 'El JSON de la vinculación no es válido.';
$messages['duplicate_bind_key'] = 'La combinación de módulo y clave de vinculación ya existe.';
$messages['binding_not_found'] = 'No se encontró la vinculación de módulo.';
$messages['default_binding_title'] = 'Nueva vinculación de módulo';
$messages['default_binding_description'] = 'Vinculación unificada: dbxWorkflow utiliza la DD, FD, TPL y configuración del módulo.';


$addField = function($name, $type, $label, $rules, $tpl, $extra = array()) use (&$fields) {
   $field=array();
   $field['name']=$name;
   $field['type']=$type;
   $field['index']='';
   $field['length']=$extra['length'] ?? '';
   $field['default']=$extra['default'] ?? '';
   $field['label']=$label;
   $field['rules']=$rules;
   $field['tooltip']=$extra['tooltip'] ?? '';
   $field['errormsg']=$extra['errormsg'] ?? '';
   $field['placeholder']=$extra['placeholder'] ?? '';
   $field['convert']='';
   $field['protect']='0';
   $field['mask']='';
   $field['data']=$extra['data'] ?? '';
   $field['options']=$extra['options'] ?? '';
   $field['tpl']=$tpl;
   $fields[]=$field;
};

$addField('modul','varchar','Módulo','parameter|min=2|max=80','text-label',array('placeholder'=>'dbxContact'));
$addField('bind_key','varchar','La clave de unión','parameter|min=2|max=80','text-label',array('placeholder'=>'contact_reply'));
$addField('title','varchar','Título','*|min=2|max=160','text-label');
$addField('description','mediumtext','Descripción','*|max=3000','textarea-label',array('data'=>'rows=3'));
$addField('bind_json','mediumtext','Binding JSON','*|min=2|max=30000','textarea-label',array('data'=>'rows=18'));
$addField('active','int','Activo','int','checkbox-label',array('default'=>'1'));

?>
