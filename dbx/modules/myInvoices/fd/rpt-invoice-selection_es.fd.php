<?php
$messages = array();
$messages['save_success'] = 'Los datos se guardaron';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Los datos no se pudieron guardar';
$messages['filter_error'] = 'Revise los datos de los filtros.';
$messages['report_title'] = 'Informe de facturas';
$messages['column_invoice_no'] = 'Factura';
$messages['column_invoice_date'] = 'Fecha';
$messages['column_customer'] = 'Cliente';
$messages['column_status'] = 'Estado';
$messages['column_action'] = 'Acción';
$messages['column_total'] = 'Total';
$messages['column_position_no'] = 'Pos.';
$messages['column_article_no'] = 'Número de artículo';
$messages['column_article'] = 'Artículo';
$messages['column_quantity'] = 'Cantidad';
$messages['column_unit_price'] = 'Precio unitario';
$messages['status_draft'] = 'Borrador';
$messages['status_open'] = 'Abierta';
$messages['status_paid'] = 'Pagada';
$messages['delete_title'] = 'Eliminar factura';
$messages['delete_question'] = '¿Eliminar la factura #{id} y todas sus posiciones?';
$messages['delete_hint'] = 'Los datos de la factura y sus posiciones se eliminarán juntos.';
$messages['delete_invalid'] = 'No se pudo identificar la factura.';
$messages['transaction_error'] = 'No se pudo iniciar la transacción.';
$messages['delete_error'] = 'No se pudo eliminar completamente la factura.';
$messages['delete_success'] = 'Se eliminaron la factura y sus posiciones.';

/**
 * Filter- und Sortierfelder des Rechnungsreports.
 */

$fields = array();

$field = array();
$field['name'] = 'dbx_rrows';
$field['type'] = 'int';
$field['tpl'] = 'select-single-label';
$field['default'] = '20';
$field['label'] = 'Por página';
$field['rules'] = 'int';
$field['options'] = '10=10&20=20&30=30&50=50';
$fields[] = $field;

$field = array();
$field['name'] = 'dbx_rsort';
$field['type'] = 'varchar';
$field['tpl'] = 'select-single-label';
$field['default'] = 'invoice_date';
$field['label'] = 'Ordenación';
$field['rules'] = 'parameter';
$field['options'] =
    'invoice_no=Número de factura&invoice_date=Fecha'
    . '&customer=Cliente&status=Estado&total_gross=Total';
$fields[] = $field;

$field = array();
$field['name'] = 'dbx_rdesc';
$field['type'] = 'varchar';
$field['tpl'] = 'select-single-label';
$field['default'] = 'DESC';
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
$field['options'] = 'all=Todos&draft=Borrador&open=Abierta&paid=Pagada';
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
