<?php
$messages = array();
$messages['filter_error'] = 'Please check the filter entries.';
$messages['report_title'] = 'Invoice report';
$messages['column_invoice_no'] = 'Invoice';
$messages['column_invoice_date'] = 'Date';
$messages['column_customer'] = 'Customer';
$messages['column_status'] = 'Status';
$messages['column_action'] = 'Action';
$messages['column_total'] = 'Total';
$messages['column_position_no'] = 'Item';
$messages['column_article_no'] = 'Article number';
$messages['column_article'] = 'Article';
$messages['column_quantity'] = 'Quantity';
$messages['column_unit_price'] = 'Unit price';
$messages['status_draft'] = 'Draft';
$messages['status_open'] = 'Open';
$messages['status_paid'] = 'Paid';
$messages['delete_title'] = 'Delete invoice';
$messages['delete_question'] = 'Delete invoice #{id} and all its items?';
$messages['delete_hint'] = 'The invoice header and its items will be deleted together.';
$messages['delete_invalid'] = 'The invoice could not be identified.';
$messages['transaction_error'] = 'The transaction could not be started.';
$messages['delete_error'] = 'The invoice could not be deleted completely.';
$messages['delete_success'] = 'The invoice and its items were deleted.';

/**
 * Filter- und Sortierfelder des Rechnungsreports.
 */

$fields = array();

$field = array();
$field['name'] = 'dbx_rrows';
$field['type'] = 'int';
$field['tpl'] = 'select-single-label';
$field['default'] = '20';
$field['label'] = 'Per page';
$field['rules'] = 'int';
$field['options'] = '10=10&20=20&30=30&50=50';
$fields[] = $field;

$field = array();
$field['name'] = 'dbx_rsort';
$field['type'] = 'varchar';
$field['tpl'] = 'select-single-label';
$field['default'] = 'invoice_date';
$field['label'] = 'Sorting';
$field['rules'] = 'parameter';
$field['options'] =
    'invoice_no=Invoice number&invoice_date=Date'
    . '&customer=Customer&status=Status&total_gross=Total';
$fields[] = $field;

$field = array();
$field['name'] = 'dbx_rdesc';
$field['type'] = 'varchar';
$field['tpl'] = 'select-single-label';
$field['default'] = 'DESC';
$field['label'] = 'Direction';
$field['rules'] = 'parameter';
$field['options'] = 'ASC=Ascending&DESC=Descending';
$fields[] = $field;

$field = array();
$field['name'] = 'dbx_rstatus';
$field['type'] = 'varchar';
$field['tpl'] = 'select-single-label';
$field['default'] = 'all';
$field['label'] = 'Status';
$field['rules'] = 'parameter|max=24';
$field['options'] = 'all=All&draft=Draft&open=Open&paid=Paid';
$fields[] = $field;

$field = array();
$field['name'] = 'dbx_rwhere';
$field['type'] = 'varchar';
$field['tpl'] = 'dbx|search';
$field['default'] = '';
$field['label'] = 'Search';
$field['rules'] = 'sqlsearch|max=64';
$fields[] = $field;

?>
