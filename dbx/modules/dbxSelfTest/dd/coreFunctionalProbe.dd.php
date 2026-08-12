<?php

/* Isolierte DD fuer den funktionalen Kern-Selbsttest. */
$table['server'] = 'dbxSelfTest|dbxCoreFunctionalTest.db3';
$table['table'] = 'core_functional_probe';
$table['datadic'] = 'coreFunctionalProbe';
$table['primary'] = 'id';
$table['language'] = '0';
$table['version'] = '1.0';
$table['autosync'] = '0';
$table['cache'] = '0';
$table['trash'] = '0';
$table['trace'] = '0';
$table['system_inventory'] = '0';
$table['default_sort'] = 'sorter ASC, id ASC';
$table['read'] = 'admin';
$table['create'] = 'admin';
$table['update'] = 'admin';
$table['delete'] = 'admin';

$addField = static function (
   string $name,
   string $type,
   string $length,
   string $default,
   string $label,
   string $rules,
   string $index = '',
   string $tpl = 'hidden'
) use (&$fields): void {
   $fields[] = array(
      'name' => $name,
      'type' => $type,
      'index' => $index,
      'length' => $length,
      'default' => $default,
      'label' => $label,
      'rules' => $rules,
      'tooltip' => '',
      'errormsg' => '',
      'placeholder' => '',
      'convert' => '',
      'protect' => '0',
      'group' => '',
      'mask' => '',
      'data' => '',
      'options' => '',
      'tpl' => $tpl,
      'js' => '',
      'prompt' => '',
   );
};

$addField('id', 'int', '11', '', 'ID', 'int', 'PRI');
$addField('create_date', 'datetime', '-1', '', 'Erstellt', 'datetime', 'MUL');
$addField('create_uid', 'int', '11', '0', 'Erstellt von', 'int', 'MUL');
$addField('update_date', 'datetime', '-1', '', 'Aktualisiert', 'datetime', 'MUL');
$addField('update_uid', 'int', '11', '0', 'Aktualisiert von', 'int', 'MUL');
$addField('owner', 'int', '11', '0', 'Owner', 'int', 'MUL');
$addField('probe_key', 'varchar', '40', '', 'Pruefschluessel', 'parameter|min=2|max=40', 'UNI', 'text-label');
$addField('label', 'varchar', '120', '', 'Bezeichnung', '*|min=2|max=120', 'MUL', 'text-label');
$addField('quantity', 'int', '11', '0', 'Menge', 'int', '', 'integer-label');
$addField('sorter', 'varchar', '16', '0010', 'Sortierung', 'parameter|max=16', 'MUL', 'text-label');
$addField('active', 'int', '1', '1', 'Aktiv', 'int', 'MUL', 'checkbox-label');

?>
