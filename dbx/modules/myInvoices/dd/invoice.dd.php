<?php

/* =========================================================
   TABLE
   ========================================================= */
$table['server']='myInvoices|myInvoices.db3';
$table['table']='invoice';
$table['datadic']='invoice';
$table['primary']='id';
$table['language']='0';
$table['version']='1.0';
$table['autosync']='1';
$table['cache']='0';
$table['trash']='0';
$table['trace']='0';
$table['update_sql']='';
$table['default_sort']='invoice_date DESC, invoice_no DESC';
$table['form-dd-table']='';
$table['read']='admin';
$table['create']='admin';
$table['update']='admin';
$table['delete']='admin';
$table['read_owner']='admin,owner';
$table['create_owner']='admin,owner';
$table['update_owner']='admin,owner';
$table['delete_owner']='admin,owner';


/* =========================================================
   FIELDS
   ========================================================= */
$field['name']='id';
$field['type']='int';
$field['index']='PRI';
$field['length']='11';
$field['default']='';
$field['label']='ID';
$field['rules']='int';
$field['tooltip']='';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['protect']='0';
$field['group']='';
$field['mask']='';
$field['data']='';
$field['options']='';
$field['tpl']='hidden';
$field['js']='';
$field['prompt']='';
$fields[]=$field;

$field['name']='create_date';
$field['type']='datetime';
$field['index']='MUL';
$field['length']='-1';
$field['default']='';
$field['label']='Erstellt';
$field['rules']='datetime';
$field['tooltip']='';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='date_time';
$field['protect']='0';
$field['group']='';
$field['mask']='';
$field['data']='';
$field['options']='';
$field['tpl']='hidden';
$field['js']='';
$field['prompt']='';
$fields[]=$field;

$field['name']='create_uid';
$field['type']='int';
$field['index']='MUL';
$field['length']='11';
$field['default']='0';
$field['label']='Erstellt von';
$field['rules']='int';
$field['tooltip']='';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['protect']='0';
$field['group']='';
$field['mask']='';
$field['data']='';
$field['options']='';
$field['tpl']='hidden';
$field['js']='';
$field['prompt']='';
$fields[]=$field;

$field['name']='update_date';
$field['type']='datetime';
$field['index']='MUL';
$field['length']='-1';
$field['default']='';
$field['label']='Aktualisiert';
$field['rules']='datetime';
$field['tooltip']='';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='date_time';
$field['protect']='0';
$field['group']='';
$field['mask']='';
$field['data']='';
$field['options']='';
$field['tpl']='hidden';
$field['js']='';
$field['prompt']='';
$fields[]=$field;

$field['name']='update_uid';
$field['type']='int';
$field['index']='MUL';
$field['length']='11';
$field['default']='0';
$field['label']='Aktualisiert von';
$field['rules']='int';
$field['tooltip']='';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['protect']='0';
$field['group']='';
$field['mask']='';
$field['data']='';
$field['options']='';
$field['tpl']='hidden';
$field['js']='';
$field['prompt']='';
$fields[]=$field;

$field['name']='owner';
$field['type']='int';
$field['index']='MUL';
$field['length']='11';
$field['default']='0';
$field['label']='Owner';
$field['rules']='int';
$field['tooltip']='';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['protect']='0';
$field['group']='';
$field['mask']='';
$field['data']='';
$field['options']='';
$field['tpl']='hidden';
$field['js']='';
$field['prompt']='';
$fields[]=$field;

$field['name']='invoice_no';
$field['type']='varchar';
$field['index']='UNI';
$field['length']='40';
$field['default']='';
$field['label']='Rechnungsnummer';
$field['rules']='parameter|min=2|max=40';
$field['tooltip']='';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['protect']='0';
$field['group']='';
$field['mask']='';
$field['data']='';
$field['options']='';
$field['tpl']='text-label';
$field['js']='';
$field['prompt']='';
$fields[]=$field;

$field['name']='invoice_date';
$field['type']='date';
$field['index']='MUL';
$field['length']='-1';
$field['default']='';
$field['label']='Rechnungsdatum';
$field['rules']='date';
$field['tooltip']='';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='date';
$field['protect']='0';
$field['group']='';
$field['mask']='';
$field['data']='';
$field['options']='';
$field['tpl']='date-label';
$field['js']='';
$field['prompt']='';
$fields[]=$field;

$field['name']='customer';
$field['type']='varchar';
$field['index']='MUL';
$field['length']='180';
$field['default']='';
$field['label']='Kunde';
$field['rules']='*|min=2|max=180';
$field['tooltip']='';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['protect']='0';
$field['group']='';
$field['mask']='';
$field['data']='';
$field['options']='';
$field['tpl']='text-label';
$field['js']='';
$field['prompt']='';
$fields[]=$field;

$field['name']='status';
$field['type']='varchar';
$field['index']='MUL';
$field['length']='24';
$field['default']='draft';
$field['label']='Status';
$field['rules']='parameter|max=24';
$field['tooltip']='';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['protect']='0';
$field['group']='';
$field['mask']='';
$field['data']='';
$field['options']='draft=Entwurf&open=Offen&paid=Bezahlt';
$field['tpl']='select-single-label';
$field['js']='';
$field['prompt']='';
$fields[]=$field;

$field['name']='total_gross';
$field['type']='decimal';
$field['index']='';
$field['length']='12,2';
$field['default']='0';
$field['label']='Rechnungssumme';
$field['rules']='decimal';
$field['tooltip']='';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['protect']='0';
$field['group']='';
$field['mask']='';
$field['data']='';
$field['options']='';
$field['tpl']='hidden';
$field['js']='';
$field['prompt']='';
$fields[]=$field;


/* =========================================================
   INDEXES
   ========================================================= */
$index['name']='pk_invoice';
$index['type']='PRIMARY';
$index['fields']='id';
$index['unique']='1';
$index['comment']='from field index PRI';
$indexes[]=$index;

$index['name']='idx_invoice_create_date';
$index['type']='INDEX';
$index['fields']='create_date';
$index['unique']='0';
$index['comment']='from field index MUL';
$indexes[]=$index;

$index['name']='idx_invoice_create_uid';
$index['type']='INDEX';
$index['fields']='create_uid';
$index['unique']='0';
$index['comment']='from field index MUL';
$indexes[]=$index;

$index['name']='idx_invoice_update_date';
$index['type']='INDEX';
$index['fields']='update_date';
$index['unique']='0';
$index['comment']='from field index MUL';
$indexes[]=$index;

$index['name']='idx_invoice_update_uid';
$index['type']='INDEX';
$index['fields']='update_uid';
$index['unique']='0';
$index['comment']='from field index MUL';
$indexes[]=$index;

$index['name']='idx_invoice_owner';
$index['type']='INDEX';
$index['fields']='owner';
$index['unique']='0';
$index['comment']='from field index MUL';
$indexes[]=$index;

$index['name']='uidx_invoice_invoice_no';
$index['type']='UNIQUE';
$index['fields']='invoice_no';
$index['unique']='1';
$index['comment']='from field index UNI';
$indexes[]=$index;

$index['name']='idx_invoice_invoice_date';
$index['type']='INDEX';
$index['fields']='invoice_date';
$index['unique']='0';
$index['comment']='from field index MUL';
$indexes[]=$index;

$index['name']='idx_invoice_customer';
$index['type']='INDEX';
$index['fields']='customer';
$index['unique']='0';
$index['comment']='from field index MUL';
$indexes[]=$index;

$index['name']='idx_invoice_status';
$index['type']='INDEX';
$index['fields']='status';
$index['unique']='0';
$index['comment']='from field index MUL';
$indexes[]=$index;
