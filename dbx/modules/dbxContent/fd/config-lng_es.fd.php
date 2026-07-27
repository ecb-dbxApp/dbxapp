<?php
$messages = array();
$messages['save_success'] = 'Los datos se guardaron';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Los datos no se pudieron guardar';


$lngOptions = 'de=Alemán (DE)&en=Inglés (EN)&es=Español (ES)&fr=Francais (FR)&it=Italiano (IT)';

$providerOptions = 'copy=Copiar texto (predeterminado)&none=Dejar vacío&deepl=DeepL API&openai=OpenAI API&custom=Aduanas (translate.php)';

$modelOptions = 'gpt-4o-mini=gpt-4o-mini (rápido)&gpt-4o=gpt-4o (calidad)&gpt-4.1-mini=gpt-4.1-mini';

$field = array();
$field['name'] = 'default_lng';
$field['type'] = 'varchar';
$field['length'] = '8';
$field['default'] = 'de';
$field['label'] = 'Idioma maestro';
$field['rules'] = '*';
$field['tooltip'] = 'Lenguaje de control del CMS: estructura, comisión, auto-sincronización y revisión maestra. Correspond default_lng en el sistema-config (dbx).';
$field['errormsg'] = 'Por favor, seleccione un idioma maestro válido.';
$field['placeholder'] = 'de';
$field['options'] = $lngOptions;
$field['tpl'] = 'select-single-label';
$fields[] = $field;

$field = array();
$field['name'] = 'accessible_lng';
$field['type'] = 'varchar';
$field['length'] = '128';
$field['default'] = 'de';
$field['label'] = 'Idiomas disponibles';
$field['rules'] = '*';
$field['tooltip'] = 'Todos los idiomas para árbol CMS, insignias de idiomas y conmutación UI. El idioma maestro debe ser incluido. Almacenados accessible_lng dentro dbx.';
$field['errormsg'] = 'Seleccione al menos un idioma.';
$field['placeholder'] = 'de, en, es';
$field['options'] = $lngOptions;
$field['tpl'] = 'select-multible-label';
$fields[] = $field;

$field = array();
$field['name'] = 'home_cid';
$field['type'] = 'varchar';
$field['length'] = '16';
$field['default'] = '1';
$field['label'] = 'Página de inicio (maestra)';
$field['rules'] = 'int';
$field['tooltip'] = 'Contenido ID de la página principal en el idioma principal (dbxHome/cid). Los idiomas destinatarios lng_uid en el página derecho.';
$field['errormsg'] = 'Por favor, proporcione un ID de contenido válido.';
$field['placeholder'] = 'por ejemplo 1';
$field['options'] = '';
$field['tpl'] = 'text-label';
$fields[] = $field;

$field = array();
$field['name'] = 'lng_translate_provider';
$field['type'] = 'varchar';
$field['length'] = '32';
$field['default'] = 'copy';
$field['label'] = 'Proveedor de traducción';
$field['rules'] = '*';
$field['tooltip'] = 'copia = texto maestro. ninguno = texto objetivo vacío. profunda/openai = traducción de API. personal = propio translate.php en el módulo.';
$field['errormsg'] = 'Por favor, elija un proveedor.';
$field['placeholder'] = 'copy';
$field['options'] = $providerOptions;
$field['tpl'] = 'select-single-label';
$fields[] = $field;

$field = array();
$field['name'] = 'lng_translate_api_key';
$field['type'] = 'varchar';
$field['length'] = '256';
$field['default'] = '';
$field['label'] = 'Clave API';
$field['rules'] = '';
$field['tooltip'] = 'Duty at deepl and openaiDeja en blanco en la copia / nadie. En el caso de: dbxContent-Config almacenado.';
$field['errormsg'] = '';
$field['placeholder'] = '... o DeepL-Auth-Key';
$field['options'] = '';
$field['tpl'] = 'password-label';
$fields[] = $field;

$field = array();
$field['name'] = 'lng_translate_api_url';
$field['type'] = 'varchar';
$field['length'] = '256';
$field['default'] = '';
$field['label'] = 'API URL (opcional)';
$field['rules'] = '';
$field['tooltip'] = 'Vacío = estándar del proveedor. Sólo se establece para su propio endpoint (por ejemplo, DeepL Free API) o URL de traducción personalizada.';
$field['errormsg'] = '';
$field['placeholder'] = 'p. ej.  https://api.deepl.com/v2/translate';
$field['options'] = '';
$field['tpl'] = 'text-label';
$fields[] = $field;

$field = array();
$field['name'] = 'lng_translate_model';
$field['type'] = 'varchar';
$field['length'] = '64';
$field['default'] = 'gpt-4o-mini';
$field['label'] = 'OpenAI modelo';
$field['rules'] = '';
$field['tooltip'] = 'Sólo para proveedores openai. ID modelo para las terminaciones de chat (por ejemplo, gpt-4o-mini para traducciones rápidas).';
$field['errormsg'] = '';
$field['placeholder'] = 'gpt-4o-mini';
$field['options'] = $modelOptions;
$field['tpl'] = 'select-single-label';
$fields[] = $field;
