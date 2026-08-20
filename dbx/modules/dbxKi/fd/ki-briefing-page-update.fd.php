<?php
$fields = array();
$messages = array(
);

/**
 * Checkbox-Raster "Bootstrap-Komponenten" auf der Seite-aendern-Seite.
 * `ui_persist=1` aktiviert (zusammen mit dbxForm::$_ui_state_persist=1)
 * die dauerhafte Speicherung der Auswahl im Browser ueber
 * dbx.uiGet/uiSet, siehe dbx/js/lib/formUiPersist.js. `use_text` liefert
 * die Kurzbeschreibung fuer tpl/htm/ki-checkbox-component.htm.
 */
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

$add_component_field('comp_alert', 'Hinweis', 'Kurze Hinweis-, Info- oder Erfolgsbox.');
$add_component_field('comp_card', 'Cards', 'Teaser, Leistungsboxen oder Paket-/Feature-Kacheln.');
$add_component_field('comp_list_group', 'Listenbox', 'Kompakte Nutzen-, Schritt- oder Funktionslisten.');
$add_component_field('comp_badges', 'Badges', 'Status, Kategorien, kleine Hervorhebungen.');
$add_component_field('comp_buttons', 'Buttons', 'CTA-Links ohne eigenes JavaScript.');
$add_component_field('comp_table', 'Tabelle', 'Vergleichs- oder Preis-/Datenuebersichten.');
$add_component_field('comp_accordion', 'Akkordeon', 'FAQ oder aufklappbare Detailbereiche.');
$add_component_field('comp_tabs', 'Tabs', 'Alternative Sichten auf denselben Inhalt.');
?>
