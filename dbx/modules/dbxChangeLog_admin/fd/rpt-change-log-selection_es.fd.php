<?php
require __DIR__ . '/rpt-change-log-selection.fd.php';
$messages['report_title']='Registro de cambios';
$messages['report_subtitle']='Filtrar por cambio, recurso o actor.';
$messages['action_new']='Nueva entrada';
$messages['column_date']='Cuándo';
$messages['column_summary']='Qué';
$messages['column_details']='Por qué';
$messages['column_actor']='Actor';
$messages['column_resources']='Recursos';
$messages['delete_success']='Entrada del registro eliminada.';
$messages['delete_error']='No se pudo eliminar la entrada del registro.';
$labels=array('dbx_rrows'=>'Filas','dbx_rsort'=>'Orden','dbx_rdesc'=>'Dirección','dbx_ractor'=>'Actor','dbx_rwhere'=>'Buscar');
foreach ($fields as &$field) {
    $field['label']=$labels[$field['name']] ?? $field['label'];
    if ($field['name']==='dbx_rsort') $field['options']='change_date=Fecha&summary=Cambio&actor=Actor';
    if ($field['name']==='dbx_rdesc') $field['options']='DESC=Más recientes primero&ASC=Más antiguos primero';
}
unset($field);
