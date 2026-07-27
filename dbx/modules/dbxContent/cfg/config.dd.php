<?php

$field['name'] = 'dbxConfig_modul';
$field['type'] = 'varchar';
$field['length'] = '32';
$field['default'] = 'secure';
$field['label'] = 'Config-Schutz';
$field['rules'] = '*';
$field['tooltip'] = 'Interner Schalter fuer Modul-Config.';
$field['tpl'] = 'text-label';
$fields[] = $field;

$field['name'] = 'groups';
$field['type'] = 'varchar';
$field['length'] = '256';
$field['default'] = '*,';
$field['label'] = 'Zugriff';
$field['rules'] = '*';
$field['tooltip'] = 'Benutzergruppen mit Zugriff auf dbxContent.';
$field['options'] = 'sql:dbxUser_groups|name|description|active = 1|name ASC|88';
$field['tpl'] = 'select-multible-label';
$fields[] = $field;

$field['name'] = 'permalink_mode';
$field['type'] = 'varchar';
$field['length'] = '32';
$field['default'] = 'content';
$field['label'] = 'Permalink-Modus';
$field['rules'] = '*';
$field['tooltip'] = 'content = Permalink aus Seitentitel. cms = CMS-Steuerung.';
$field['options'] = 'content=Content&cms=CMS';
$field['tpl'] = 'select-single-label';
$fields[] = $field;

$field['name'] = 'root';
$field['type'] = 'varchar';
$field['length'] = '16';
$field['default'] = '0';
$field['label'] = 'Root-Ordner-ID';
$field['rules'] = '*';
$field['tooltip'] = 'Optionaler Startordner fuer Content (0 = Standard).';
$field['tpl'] = 'text-label';
$fields[] = $field;
