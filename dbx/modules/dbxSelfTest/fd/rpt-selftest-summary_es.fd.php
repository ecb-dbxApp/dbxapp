<?php

$messages = array();
$messages['bar_title'] = 'Resumen de pruebas';
$messages['bar_subtitle'] = 'Lista simple de todas las pruebas de la última ejecución.';
$messages['column_name'] = 'Prueba';
$messages['column_category'] = 'Área';
$messages['column_status'] = 'Estado';
$messages['column_duration'] = 'Duración';
$messages['status_passed'] = 'Superada';
$messages['status_failed'] = 'Fallida';
$messages['status_skipped'] = 'Omitida';
$messages['summary_line'] =
    'Última prueba el {date} — {passed} superadas / {failed} fallidas';
$messages['summary_line_with_skipped'] =
    'Última prueba el {date} — {passed} superadas / {failed} fallidas'
    . ' / {skipped} omitidas';
$messages['no_run'] = 'Todavía no hay ninguna ejecución de pruebas registrada.';
$messages['empty_result'] = 'No se encontraron pruebas.';
$messages['clear_history_label'] = 'Borrar historial';
$messages['clear_history_title'] = 'Borrar historial de pruebas';
$messages['clear_history_question'] = '¿Eliminar realmente todas las ejecuciones registradas?';
$messages['clear_history_hint'] =
    'Esta acción no se puede deshacer. Una prueba actualmente en ejecución se conserva.';
$messages['clear_history_success'] = 'Historial de pruebas borrado ({count} registros eliminados).';

$field = array();
$field['name'] = 'dbx_rrows';
$field['type'] = 'int';
$field['tpl'] = 'select-single-label';
$field['default'] = '100';
$field['label'] = 'Filas por página';
$field['rules'] = 'int';
$field['options'] = '50=50&100=100&200=200&500=500';
$fields[] = $field;

$field = array();
$field['name'] = 'dbx_rsort';
$field['type'] = 'varchar';
$field['tpl'] = 'select-single-label';
$field['default'] = 'status';
$field['label'] = 'Ordenar por';
$field['rules'] = 'parameter';
$field['options'] = 'name=Prueba&category=Área&status=Estado&duration_ms=Duración';
$fields[] = $field;

$field = array();
$field['name'] = 'dbx_rdesc';
$field['type'] = 'varchar';
$field['tpl'] = 'select-single-label';
$field['default'] = 'ASC';
$field['label'] = 'Dirección';
$field['rules'] = 'parameter';
$field['options'] = 'ASC=Ascendente&DESC=Descendente';
$fields[] = $field;

$field = array();
$field['name'] = 'dbx_rstatus';
$field['type'] = 'varchar';
$field['tpl'] = 'select-single-label';
$field['default'] = 'all';
$field['label'] = 'Estado';
$field['rules'] = 'parameter|max=24';
$field['options'] = 'all=Todos&passed=Superada&failed=Fallida&skipped=Omitida';
$fields[] = $field;

$field = array();
$field['name'] = 'dbx_rwhere';
$field['type'] = 'varchar';
$field['tpl'] = 'dbx|search';
$field['default'] = '';
$field['label'] = 'Buscar';
$field['rules'] = 'sqlsearch|max=64';
$fields[] = $field;

?>
