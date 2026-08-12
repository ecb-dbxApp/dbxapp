<?php
$fields = array();
$messages = array(
   'save_success' => 'Data was saved',
   'save_succeass' => 'Data was saved',
   'save_error' => 'Data could not be saved',
);

$addComponentField = function ($name, $label, $use) use (&$fields) {
   $field = array();
   $field['name'] = $name;
   $field['type'] = 'int';
   $field['index'] = '';
   $field['length'] = '';
   $field['default'] = '0';
   $field['label'] = $label;
   $field['rules'] = 'int';
   $field['tooltip'] = '';
   $field['errormsg'] = '';
   $field['placeholder'] = '';
   $field['convert'] = '';
   $field['protect'] = '0';
   $field['mask'] = '';
   $field['data'] = array('ui_persist' => 1, 'use_text' => $use);
   $field['options'] = '';
   $field['tpl'] = 'dbxKi|ki-checkbox-component';
   $fields[] = $field;
};

$addComponentField('comp_alert', 'Alert', 'Short hint, info or success box.');
$addComponentField('comp_card', 'Cards', 'Teasers, feature boxes or package/feature tiles.');
$addComponentField('comp_list_group', 'List group', 'Compact benefit, step or feature lists.');
$addComponentField('comp_badges', 'Badges', 'Status, categories, small highlights.');
$addComponentField('comp_buttons', 'Buttons', 'CTA links without custom JavaScript.');
$addComponentField('comp_table', 'Table', 'Comparison or price/data overviews.');
$addComponentField('comp_accordion', 'Accordion', 'FAQ or collapsible detail sections.');
$addComponentField('comp_tabs', 'Tabs', 'Alternative views of the same content.');
?>
