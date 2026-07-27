<?php
$messages = array();
$messages['save_success'] = 'Daten wurden gespeichert';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Daten konnten nicht gespeichert werden';


$lngOptions = 'de=Deutsch (DE)&en=English (EN)&es=Espanol (ES)&fr=Francais (FR)&it=Italiano (IT)';

$providerOptions = 'copy=Text kopieren (Standard)&none=Leer lassen&deepl=DeepL API&openai=OpenAI API&custom=Custom (translate.php)';

$modelOptions = 'gpt-4o-mini=gpt-4o-mini (schnell)&gpt-4o=gpt-4o (qualitaet)&gpt-4.1-mini=gpt-4.1-mini';

$field = array();
$field['name'] = 'default_lng';
$field['type'] = 'varchar';
$field['length'] = '8';
$field['default'] = 'de';
$field['label'] = 'Master-Sprache';
$field['rules'] = '*';
$field['tooltip'] = 'Steuersprache des CMS: Struktur, Provision, Auto-Sync und Master-Revision. Entspricht default_lng in der System-Config (dbx).';
$field['errormsg'] = 'Bitte eine gueltige Master-Sprache waehlen.';
$field['placeholder'] = 'de';
$field['options'] = $lngOptions;
$field['tpl'] = 'select-single-label';
$fields[] = $field;

$field = array();
$field['name'] = 'accessible_lng';
$field['type'] = 'varchar';
$field['length'] = '128';
$field['default'] = 'de';
$field['label'] = 'Verfuegbare Sprachen';
$field['rules'] = '*';
$field['tooltip'] = 'Alle Sprachen fuer CMS-Tree, Sprach-Badges und UI-Umschaltung. Die Master-Sprache muss enthalten sein. Gespeichert als accessible_lng in dbx.';
$field['errormsg'] = 'Mindestens eine Sprache auswaehlen.';
$field['placeholder'] = 'de, en, es';
$field['options'] = $lngOptions;
$field['tpl'] = 'select-multible-label';
$fields[] = $field;

$field = array();
$field['name'] = 'home_cid';
$field['type'] = 'varchar';
$field['length'] = '16';
$field['default'] = '1';
$field['label'] = 'Startseite (Master)';
$field['rules'] = 'int';
$field['tooltip'] = 'Content-ID der Startseite in der Master-Sprache (dbxHome/cid). Zielsprachen werden per lng_uid auf die passende Seite aufgeloest.';
$field['errormsg'] = 'Bitte eine gueltige Content-ID angeben.';
$field['placeholder'] = 'z. B. 1';
$field['options'] = '';
$field['tpl'] = 'text-label';
$fields[] = $field;

$field = array();
$field['name'] = 'lng_translate_provider';
$field['type'] = 'varchar';
$field['length'] = '32';
$field['default'] = 'copy';
$field['label'] = 'Uebersetzungs-Provider';
$field['rules'] = '*';
$field['tooltip'] = 'copy = Master-Text uebernehmen. none = Zieltext leer. deepl/openai = API-Uebersetzung. custom = eigenes translate.php im Modul.';
$field['errormsg'] = 'Bitte einen Provider waehlen.';
$field['placeholder'] = 'copy';
$field['options'] = $providerOptions;
$field['tpl'] = 'select-single-label';
$fields[] = $field;

$field = array();
$field['name'] = 'lng_translate_api_key';
$field['type'] = 'varchar';
$field['length'] = '256';
$field['default'] = '';
$field['label'] = 'API-Schluessel';
$field['rules'] = '';
$field['tooltip'] = 'Pflicht bei deepl und openai. Bei copy/none leer lassen. Wird in der dbxContent-Config gespeichert.';
$field['errormsg'] = '';
$field['placeholder'] = 'sk-... oder DeepL-Auth-Key';
$field['options'] = '';
$field['tpl'] = 'password-label';
$fields[] = $field;

$field = array();
$field['name'] = 'lng_translate_api_url';
$field['type'] = 'varchar';
$field['length'] = '256';
$field['default'] = '';
$field['label'] = 'API-URL (optional)';
$field['rules'] = '';
$field['tooltip'] = 'Leer = Provider-Standard. Nur setzen bei eigenem Endpoint (z. B. DeepL Free API) oder Custom-Translate-URL.';
$field['errormsg'] = '';
$field['placeholder'] = 'z. B. https://api.deepl.com/v2/translate';
$field['options'] = '';
$field['tpl'] = 'text-label';
$fields[] = $field;

$field = array();
$field['name'] = 'lng_translate_model';
$field['type'] = 'varchar';
$field['length'] = '64';
$field['default'] = 'gpt-4o-mini';
$field['label'] = 'OpenAI-Modell';
$field['rules'] = '';
$field['tooltip'] = 'Nur bei Provider openai. Modell-ID fuer Chat-Completions (z. B. gpt-4o-mini fuer schnelle Uebersetzungen).';
$field['errormsg'] = '';
$field['placeholder'] = 'gpt-4o-mini';
$field['options'] = $modelOptions;
$field['tpl'] = 'select-single-label';
$fields[] = $field;
