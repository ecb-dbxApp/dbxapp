<?php
$messages = array();
$lng_options = 'de=German (DE)&en=English (EN)&es=Espanol (ES)&fr=Francais (FR)&it=Italiano (IT)';

$provider_options = 'copy=Copy text (default)&none=Leave empty&deepl=DeepL API&openai=OpenAI API&custom=Customs (translate.php)';

$model_options = 'gpt-4o-mini=gpt-4o-mini (fast)&gpt-4o=gpt-4o (quality)&gpt-4.1-mini=gpt-4.1-mini';

$field = array();
$field['name'] = 'default_lng';
$field['type'] = 'varchar';
$field['length'] = '8';
$field['default'] = 'de';
$field['label'] = 'Master language';
$field['rules'] = '*';
$field['tooltip'] = 'Control language of the CMS: structure, commission, auto-sync and master revision. Corresponds default_lng in the system-config (dbx).';
$field['errormsg'] = 'Please select a valid master language.';
$field['placeholder'] = 'de';
$field['options'] = $lng_options;
$field['tpl'] = 'select-single-label';
$fields[] = $field;

$field = array();
$field['name'] = 'accessible_lng';
$field['type'] = 'varchar';
$field['length'] = '128';
$field['default'] = 'de';
$field['label'] = 'Available languages';
$field['rules'] = '*';
$field['tooltip'] = 'All languages for CMS tree, language badges and UI switching. The master language must be included. Stored as accessible_lng in dbx.';
$field['errormsg'] = 'Select at least one language.';
$field['placeholder'] = 'de, en, es';
$field['options'] = $lng_options;
$field['tpl'] = 'select-multiple-label';
$fields[] = $field;

$field = array();
$field['name'] = 'home_cid';
$field['type'] = 'varchar';
$field['length'] = '16';
$field['default'] = '1';
$field['label'] = 'Home page (master)';
$field['rules'] = 'int';
$field['tooltip'] = 'Content ID of the homepage in the master language (dbxHome/cid). The target languages are lng_uid on the right page.';
$field['errormsg'] = 'Please provide a valid content ID.';
$field['placeholder'] = 'e.g. 1';
$field['options'] = '';
$field['tpl'] = 'text-label';
$fields[] = $field;

$field = array();
$field['name'] = 'lng_translate_provider';
$field['type'] = 'varchar';
$field['length'] = '32';
$field['default'] = 'copy';
$field['label'] = 'Translation provider';
$field['rules'] = '*';
$field['tooltip'] = 'copy = master text. none = target text empty. deepl/openai = API translation. custom = own translate.php in the module.';
$field['errormsg'] = 'Please choose a provider.';
$field['placeholder'] = 'copy';
$field['options'] = $provider_options;
$field['tpl'] = 'select-single-label';
$fields[] = $field;

$field = array();
$field['name'] = 'lng_translate_api_key';
$field['type'] = 'varchar';
$field['length'] = '256';
$field['default'] = '';
$field['label'] = 'API key';
$field['rules'] = '';
$field['tooltip'] = 'Duty at deepl and openai. Leave blank at copy/none. In the case of: dbxContent-Config stored.';
$field['errormsg'] = '';
$field['placeholder'] = 'sk-... or DeepL-Auth-Key';
$field['options'] = '';
$field['tpl'] = 'password-label';
$fields[] = $field;

$field = array();
$field['name'] = 'lng_translate_api_url';
$field['type'] = 'varchar';
$field['length'] = '256';
$field['default'] = '';
$field['label'] = 'API URL (optional)';
$field['rules'] = '';
$field['tooltip'] = 'Empty = provider standard. Only set for own endpoint (e.g. DeepL Free API) or custom translate URL.';
$field['errormsg'] = '';
$field['placeholder'] = 'e.g.  https://api.deepl.com/v2/translate';
$field['options'] = '';
$field['tpl'] = 'text-label';
$fields[] = $field;

$field = array();
$field['name'] = 'lng_translate_model';
$field['type'] = 'varchar';
$field['length'] = '64';
$field['default'] = 'gpt-4o-mini';
$field['label'] = 'OpenAI model';
$field['rules'] = '';
$field['tooltip'] = 'Only for providers openai. Model ID for chat completions (e.g. gpt-4o-mini for fast translations).';
$field['errormsg'] = '';
$field['placeholder'] = 'gpt-4o-mini';
$field['options'] = $model_options;
$field['tpl'] = 'select-single-label';
$fields[] = $field;
