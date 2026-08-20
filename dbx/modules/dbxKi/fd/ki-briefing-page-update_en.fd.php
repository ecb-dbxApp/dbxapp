<?php
$fields = array();
$messages = array(
);

$add_component_field = function ($name, $label, $use) use (&$fields) {
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

$add_component_field('comp_alert', 'Alert', 'Short hint, info or success box.');
$add_component_field('comp_card', 'Cards', 'Teasers, feature boxes or package/feature tiles.');
$add_component_field('comp_list_group', 'List group', 'Compact benefit, step or feature lists.');
$add_component_field('comp_badges', 'Badges', 'Status, categories, small highlights.');
$add_component_field('comp_buttons', 'Buttons', 'CTA links without custom JavaScript.');
$add_component_field('comp_table', 'Table', 'Comparison or price/data overviews.');
$add_component_field('comp_accordion', 'Accordion', 'FAQ or collapsible detail sections.');
$add_component_field('comp_tabs', 'Tabs', 'Alternative views of the same content.');
?>
