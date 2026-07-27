<?php
$messages = array();
$messages['save_success'] = 'Daten wurden gespeichert';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Daten konnten nicht gespeichert werden';
$messages['bar_title'] = 'Katalogfilter';
$messages['attributes_heading'] = 'Eigenschaften';
$messages['demo_title'] = 'Demo-Shop – kein tatsächlicher Kauf';
$messages['demo_message'] = 'Dieser Shop dient ausschließlich Demonstrations- und Testzwecken. Der vollständige Bestellablauf kann mit Testdaten durchlaufen werden; dabei wird lediglich ein technischer Testvorgang verarbeitet. Es erfolgen kein tatsächlicher Kauf, keine Zahlung und keine Lieferung. Ein Kaufvertrag kommt nicht zustande.';
$messages['groups_aria'] = 'Artikelgruppen';
$messages['all_products'] = 'Alle Artikel';
$messages['group_fallback'] = 'Artikelgruppe';
$messages['all_option'] = 'Alle';
$messages['refine_filters'] = 'Filter verfeinern';
$messages['column_products'] = 'Artikel';
$messages['no_products_title'] = 'Keine Artikel gefunden';
$messages['no_products_message'] = 'Für Ihre Suche wurden aktuell keine aktiven Shop-Artikel gefunden.';
$messages['catalog_group_subtitle'] = 'Artikelgruppe und passende Artikel.';
$messages['catalog_subtitle'] = 'Artikel, Merchandise, Dienstleistungen und digitale Pakete.';
$messages['product_page_title'] = 'Produkt';
$messages['product_not_found_subtitle'] = 'Das Produkt wurde nicht gefunden oder ist für diesen Channel nicht aktiv.';
$messages['product_not_found_title'] = 'Produkt nicht gefunden';
$messages['product_not_found_message'] = 'Der angeforderte Artikel existiert nicht oder ist für den gewählten Channel nicht freigegeben.';
$messages['product_fallback'] = 'Artikel';
$messages['tax_label'] = 'MwSt.';
$messages['shipping_suffix'] = 'Versand';
$messages['free_shipping'] = 'versandfrei';
$messages['delivery_time'] = 'Lieferzeit';
$messages['shipping_method'] = 'Versandweg';
$messages['shipping_costs'] = 'Versandkosten';
$messages['stock_out'] = 'Aktuell nicht auf Lager.';
$messages['stock_low'] = 'Nur noch {count} verfügbar.';
$messages['stock_available'] = 'Lagerbestand: verfügbar';


$field = array();
$field['name'] = 'q';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '128';
$field['default'] = '';
$field['label'] = 'Suche';
$field['rules'] = 'sqlsearch|max=128';
$field['tooltip'] = 'Shop durchsuchen';
$field['errormsg'] = 'Bitte den Suchbegriff prüfen.';
$field['placeholder'] = 'Suchbegriff eingeben';
$field['convert'] = '';
$field['protect'] = '0';
$field['mask'] = '';
$field['data'] = array(
   'input_class' => 'form-control-sm dbx-shop-search-input',
   'wrap_class' => 'dbx-shop-search-wrap',
   'data_role' => 'shop-search',
   'extra_attrs' => 'data-dbx-clear-submit',
);
$field['options'] = '';
$field['tpl'] = 'dbx|search';
$fields[] = $field;

?>
