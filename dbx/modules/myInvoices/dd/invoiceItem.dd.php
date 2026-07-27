<?php

/* =========================================================
   TABLE
   ========================================================= */
$table['server']='myInvoices|myInvoices.db3';
$table['table']='invoice_item';
$table['datadic']='invoiceItem';
$table['primary']='id';
$table['language']='0';
$table['version']='1.0';
$table['autosync']='1';
$table['cache']='0';
$table['trash']='0';
$table['trace']='0';
$table['update_sql']='';
$table['default_sort']='invoice_id ASC, position_no ASC';
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

$field['name']='invoice_id';
$field['type']='int';
$field['index']='MUL';
$field['length']='11';
$field['default']='0';
$field['label']='Rechnung';
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

$field['name']='position_no';
$field['type']='int';
$field['index']='MUL';
$field['length']='11';
$field['default']='10';
$field['label']='Pos.';
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
$field['tpl']='text-label';
$field['js']='';
$field['prompt']='';
$fields[]=$field;

$field['name']='article_no';
$field['type']='varchar';
$field['index']='MUL';
$field['length']='80';
$field['default']='';
$field['label']='Artikelnummer';
$field['rules']='parameter|max=80';
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

$field['name']='description';
$field['type']='varchar';
$field['index']='';
$field['length']='255';
$field['default']='';
$field['label']='Artikel';
$field['rules']='*|min=2|max=255';
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

$field['name']='quantity';
$field['type']='decimal';
$field['index']='';
$field['length']='10,2';
$field['default']='1';
$field['label']='Menge';
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
$field['tpl']='text-label';
$field['js']='';
$field['prompt']='';
$fields[]=$field;

$field['name']='unit_price';
$field['type']='decimal';
$field['index']='';
$field['length']='12,2';
$field['default']='0';
$field['label']='Einzelpreis';
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
$field['tpl']='text-label';
$field['js']='';
$field['prompt']='';
$fields[]=$field;


/* =========================================================
   INDEXES
   ========================================================= */
$index['name']='pk_invoice_item';
$index['type']='PRIMARY';
$index['fields']='id';
$index['unique']='1';
$index['comment']='from field index PRI';
$indexes[]=$index;

$index['name']='idx_invoice_item_create_date';
$index['type']='INDEX';
$index['fields']='create_date';
$index['unique']='0';
$index['comment']='from field index MUL';
$indexes[]=$index;

$index['name']='idx_invoice_item_create_uid';
$index['type']='INDEX';
$index['fields']='create_uid';
$index['unique']='0';
$index['comment']='from field index MUL';
$indexes[]=$index;

$index['name']='idx_invoice_item_update_date';
$index['type']='INDEX';
$index['fields']='update_date';
$index['unique']='0';
$index['comment']='from field index MUL';
$indexes[]=$index;

$index['name']='idx_invoice_item_update_uid';
$index['type']='INDEX';
$index['fields']='update_uid';
$index['unique']='0';
$index['comment']='from field index MUL';
$indexes[]=$index;

$index['name']='idx_invoice_item_owner';
$index['type']='INDEX';
$index['fields']='owner';
$index['unique']='0';
$index['comment']='from field index MUL';
$indexes[]=$index;

$index['name']='idx_invoice_item_invoice_id';
$index['type']='INDEX';
$index['fields']='invoice_id';
$index['unique']='0';
$index['comment']='from field index MUL';
$indexes[]=$index;

$index['name']='idx_invoice_item_position_no';
$index['type']='INDEX';
$index['fields']='position_no';
$index['unique']='0';
$index['comment']='from field index MUL';
$indexes[]=$index;

$index['name']='idx_invoice_item_article_no';
$index['type']='INDEX';
$index['fields']='article_no';
$index['unique']='0';
$index['comment']='from field index MUL';
$indexes[]=$index;
