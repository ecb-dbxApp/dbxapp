<?php

declare(strict_types=1);

/**
 * Prüft die sichere Array-Konvertierung von dbxDB ohne Objekt-Deserialisierung.
 */

$root = dirname(__DIR__, 3);
require_once $root . '/dbx/vendor/autoload.php';
require_once dirname(__DIR__) . '/dbxKernel.php';

$db = dbx()->get_system_obj('dbxDB');
$flat = serialize(array('alpha', 'beta'));
$nested = serialize(array(array('id' => 1), array('id' => 2)));
$object = serialize((object)array('unsafe' => true));

if ($db->get_convert_array('tags', $flat, 'serial') !== 'alpha,beta') {
    fwrite(STDERR, "FAIL Flaches serialisiertes Array wird nicht zur Liste normalisiert.\n");
    exit(1);
}
if ($db->get_convert_array('rows', $nested, 'serial') !== $nested) {
    fwrite(STDERR, "FAIL Verschachteltes Array bleibt nicht serialisiert.\n");
    exit(1);
}
if ($db->get_convert_array('object', $object, 'serial') !== $object) {
    fwrite(STDERR, "FAIL Ein Objektwert wird unerwartet deserialisiert oder verändert.\n");
    exit(1);
}

$source = (string)file_get_contents(dirname(__DIR__) . '/dbxDB.class.php');
if (!str_contains($source, "array('allowed_classes' => false)")) {
    fwrite(STDERR, "FAIL Objekt-Deserialisierung ist nicht ausdrücklich gesperrt.\n");
    exit(1);
}

echo "OK dbxDB konvertiert Arraywerte ohne PHP-Objekte zu deserialisieren.\n";
