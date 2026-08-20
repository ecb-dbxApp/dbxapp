<?php
$messages = array();
$messages['bar_title'] = 'Definiciones de flujos de trabajo';
$messages['bar_subtitle'] = 'Administrar definiciones de flujos de trabajo';
$messages['module_bindings'] = 'Vinculaciones de módulos';
$messages['new_binding'] = 'Nueva vinculación';
$messages['new_workflow'] = 'Nuevo flujo de trabajo';
$messages['report_info'] = 'Administre, filtre y edite definiciones de flujos de trabajo.';
$messages['filter_applied'] = 'Se aplicó el filtro.';
$messages['validation_error'] = 'Revise los datos introducidos.';
$messages['column_key'] = 'Clave';
$messages['column_title'] = 'Título';
$messages['column_goal'] = 'Objetivo';
$messages['column_active'] = 'Activo';
$messages['column_updated'] = 'Actualizado';
$messages['column_action'] = 'Acción';


$field['name']='dbx_rrows';
$field['type']='int';
$field['tpl']='select-single-label';
$field['default']='30';
$field['label']='Número';
$field['rules']='int';
$field['options']='10=10&15=15&20=20&30=30&50=50&100=100';
$fields[]=$field;

$field['name']='dbx_rsort';
$field['type']='varchar';
$field['tpl']='select-single-label';
$field['default']='title';
$field['label']='Orden';
$field['rules']='parameter';
$field['options']='id=ID&workflow_key=Clave&title=Título&result_label=Resultado&active=Activo&update_date=Actualización';
$fields[]=$field;

$field['name']='dbx_rdesc';
$field['type']='varchar';
$field['tpl']='select-single-label';
$field['default']='ASC';
$field['label']='Dirección';
$field['rules']='parameter';
$field['options']='ASC=Ascendente&DESC=Descendente';
$fields[]=$field;

$field['name']='dbx_rwhere';
$field['type']='varchar';
$field['tpl']='dbx|search';
$field['default']='';
$field['label']='Búsqueda';
$field['rules']='parameter';
$field['options']='';
$fields[]=$field;

$field['name']='dbx_rselect';
$field['type']='int';
$field['tpl']='select-single-label';
$field['default']='0';
$field['label']='Seleccionado';
$field['rules']='parameter';
$field['options']='0=Todos';
$fields[]=$field;
