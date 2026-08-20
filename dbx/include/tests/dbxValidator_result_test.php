<?php

/**
 * Verhaltens- und Regressionstest fuer strukturierte Validator-Ergebnisse.
 *
 * Der Test benoetigt weder Kernel noch Datenbank. Er prueft insbesondere die
 * frueher global akzeptierten Werte 0/1, vollstaendige Zeitstempel,
 * Unicode-Laengen, BIGINT-Grenzen sowie required/trim und Array-Fehlerdetails.
 */

$root = dirname(__DIR__, 2);
require_once $root . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'dbxValidator.class.php';

$validator = new dbxValidator();

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$result = $validator->validate_result('invalid', 'email|required', 'email');
$assert($result['valid'] === false, 'ungueltige E-Mail wurde akzeptiert');
$assert($result['code'] === 'invalid_format', 'falscher E-Mail-Fehlercode');
$assert($result['name'] === 'email', 'Feldname fehlt im Ergebnis');
$assert($validator->get_last_result() === $result, 'getLastResult liefert nicht das letzte Ergebnis');
$assert(count($validator->get_errors()) === 1, 'strukturierter Fehler wurde nicht gespeichert');
$assert(count($validator->get_error_messages()) === 1, 'lesbare Fehlermeldung wurde nicht gespeichert');

$required = $validator->validate_result('', 'required|email', 'email');
$assert($required['valid'] === false && $required['code'] === 'required', 'required erkennt leeren Wert nicht');
$assert($required['message'] === 'Bitte füllen Sie dieses Feld aus.', 'deutsche Benutzermeldung fehlt');

$validator->set_language('en');
$required_en = $validator->validate_result('', 'required|email', 'email');
$assert($required_en['message'] === 'Please fill in this field.', 'englische Benutzermeldung fehlt');

$validator->set_language('es');
$format_es = $validator->validate_result('invalid', 'email', 'email');
$assert($format_es['message'] === 'Introduzca un valor válido.', 'spanische Benutzermeldung fehlt');

$validator->set_language('');
$assert($validator->get_language() === 'de', 'automatische Fallback-Sprache ist nicht Deutsch');

$legacy_required = $validator->validate_result('', 'email|min=1', 'email');
$assert(
    $legacy_required['valid'] === false && $legacy_required['code'] === 'min_length',
    'bestehende min=1-Pflichtregel funktioniert nicht'
);

$optional = $validator->validate_result('', 'email', 'email');
$assert($optional['valid'] === true, 'optionales leeres Feld ist nicht mehr rueckwaertskompatibel');
$assert($validator->get_errors() === [], 'Fehler des vorherigen Laufs wurden nicht geloescht');

$assert(!$validator->validate('0', 'email'), '0 umgeht weiterhin die E-Mail-Regel');
$assert(!$validator->validate('1', 'date'), '1 umgeht weiterhin die Datumsregel');
$assert(!$validator->validate(false, 'email'), 'boolean false umgeht weiterhin die E-Mail-Regel');
$assert($validator->validate('0', 'boolean'), '0 wurde als Boolean abgelehnt');
$assert($validator->validate('1', 'boolean'), '1 wurde als Boolean abgelehnt');
$assert($validator->validate(false, 'boolean'), 'boolean false wurde als Boolean abgelehnt');
$assert($validator->validate(true, 'boolean'), 'boolean true wurde als Boolean abgelehnt');

$trimmed = $validator->validate_result('  name@example.org  ', 'trim|required|email', 'email');
$assert($trimmed['valid'] === true, 'trim-Regel wird nicht vor der E-Mail-Pruefung angewendet');
$assert($trimmed['normalized'] === 'name@example.org', 'normalisierter trim-Wert ist falsch');
$assert(!$validator->validate('  name@example.org  ', 'required|email'), 'E-Mail wurde ohne trim still bereinigt');

$assert($validator->validate('2026-07-23 12:34:56', 'datetime'), 'gueltiger Zeitstempel wurde abgelehnt');
$assert($validator->validate('2026-07-23 12:34:56.123456', 'timestamp'), 'Mikrosekunden wurden abgelehnt');
$assert(!$validator->validate('2026-07-23 12:34:56abc', 'datetime'), 'Anhang am Zeitstempel wurde akzeptiert');
$assert(!$validator->validate('2026-07-23 24:00:00', 'datetime'), 'Stunde 24 wurde akzeptiert');
$assert(!$validator->validate('2026-07-23 23:60:00', 'datetime'), 'Minute 60 wurde akzeptiert');
$assert(!$validator->validate('2026-07-23 23:59:60', 'datetime'), 'Sekunde 60 wurde akzeptiert');
$assert(!$validator->validate('2026-02-30 12:00:00', 'datetime'), 'ungueltiges Kalenderdatum wurde akzeptiert');
$assert($validator->validate('2026-07-23', '*|date'), 'historische *|date-Regel wurde gebrochen');
$assert(!$validator->validate('kein-datum', '*|date'), '*|date wertet die konkrete Datumsregel nicht aus');

$assert($validator->validate('äöü', 'varchar|max=3'), 'Unicode-Laenge wird weiterhin als Byte-Laenge geprueft');
$unicode = $validator->validate_result('äöü', 'varchar|max=2', 'text');
$assert($unicode['valid'] === false && $unicode['code'] === 'max_length', 'Unicode-Maximallaenge ist falsch');
$assert(($unicode['details']['actual'] ?? null) === 3, 'Unicode-Zeichenanzahl im Ergebnis ist falsch');

$assert($validator->validate('9223372036854775807', 'bigint'), 'groesster BIGINT wurde abgelehnt');
$assert($validator->validate('-9223372036854775808', 'bigint'), 'kleinster BIGINT wurde abgelehnt');
$bigint = $validator->validate_result('9223372036854775808', 'bigint', 'id');
$assert($bigint['valid'] === false && $bigint['code'] === 'invalid_range', 'BIGINT-Ueberlauf wurde akzeptiert');
$assert(!$validator->validate('-9223372036854775809', 'bigint'), 'negativer BIGINT-Ueberlauf wurde akzeptiert');
$assert($validator->validate('Sicher! 123', 'password|min=8|max=32'), 'Passwort-Regel lehnt erlaubte Zeichen ab');

$array = $validator->validate_result(['ok', 'nicht ok'], 'array|required|parameter', 'selection');
$assert($array['valid'] === false && $array['code'] === 'invalid_array_item', 'Array-Fehler wurde nicht erkannt');
$assert(($array['details']['index'] ?? null) === 1, 'Index des fehlerhaften Array-Eintrags fehlt');
$assert(
    ($array['details']['item']['code'] ?? '') === 'invalid_format',
    'Fehlerdetails des Array-Eintrags fehlen'
);

$assert($validator->validate('*', 'parameter+*'), 'bestehende Extra-Zeichen-Regel wurde gebrochen');
$assert($validator->validate(['raw' => ['nested']], '*'), 'historische Vollpass-Regel akzeptiert nicht mehr jeden Wert');

$invalid_rule = $validator->validate_result('value', 'unknown-rule', 'field');
$assert($invalid_rule['valid'] === false && $invalid_rule['code'] === 'invalid_rule', 'unbekannte Regel wurde akzeptiert');
$assert(!empty($invalid_rule['details']['invalid']), 'ungueltiger Regelteil fehlt im Ergebnis');

echo "OK: Strukturierte Validator-Ergebnisse, Typgrenzen, required/trim und Unicode sind korrekt.\n";
