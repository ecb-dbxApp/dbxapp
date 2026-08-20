<?php
$messages = array();
$messages['bar_title'] = 'Instancias de flujos de trabajo';
$messages['bar_subtitle'] = 'Flujos de trabajo activos y completados';
$messages['module_bindings'] = 'Vinculaciones de módulos';
$messages['new_binding'] = 'Nueva vinculación';
$messages['new_workflow'] = 'Nuevo flujo de trabajo';
$messages['report_info'] = 'Vea y filtre instancias de flujos de trabajo.';
$messages['filter_applied'] = 'Se aplicó el filtro.';
$messages['validation_error'] = 'Revise los datos introducidos.';
$messages['column_start'] = 'Inicio';
$messages['column_workflow'] = 'Flujo de trabajo';
$messages['column_goal'] = 'Objetivo';
$messages['column_status'] = 'Estado';
$messages['column_task'] = 'Tarea';
$messages['column_message'] = 'Mensaje';
$messages['column_action'] = 'Acción';
$messages['status_running'] = 'En curso';
$messages['status_finishing'] = 'Finalizando';
$messages['status_paused'] = 'Pausado';
$messages['status_finished'] = 'Finalizado';
$messages['status_canceled'] = 'Cancelado';
$messages['status_error'] = 'Error';
$messages['status_unknown'] = 'Desconocido';
$messages['action_view'] = 'Ver';
$messages['action_continue'] = 'Continuar';
$messages['action_title'] = '{action}: flujo de trabajo #{id}';


$field['name']='dbx_rrows';
$field['type']='int';
$field['tpl']='select-single-label';
$field['default']='50';
$field['label']='Número';
$field['rules']='int';
$field['options']='10=10&25=25&50=50&100=100';
$fields[]=$field;

$field['name']='dbx_rsort';
$field['type']='varchar';
$field['tpl']='select-single-label';
$field['default']='create_date';
$field['label']='Orden';
$field['rules']='parameter';
$field['options']='id=ID&create_date=Comienzo&workflow_key=Flujo de trabajo&result_label=Resultado&status=Situación&current_need=Paso&percent=Porcentaje';
$fields[]=$field;

$field['name']='dbx_rdesc';
$field['type']='varchar';
$field['tpl']='select-single-label';
$field['default']='DESC';
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
