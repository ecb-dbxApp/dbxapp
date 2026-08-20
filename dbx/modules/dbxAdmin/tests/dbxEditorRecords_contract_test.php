<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/dbxEditorRecords.class.php';

use dbx\dbxAdmin\dbxEditorRecords;

$records = array(array('name' => 'a'), array('name' => 'b'), array('name' => 'c'));

if (dbxEditorRecords::reorder($records, array(2, 0, 1)) !== array($records[2], $records[0], $records[1])) {
    fwrite(STDERR, "Record-Reihenfolge ist fehlerhaft.\n");
    exit(1);
}

foreach (array(array(0, 0, 2), array(0, 1), array(0, 1, 3)) as $invalid_order) {
    if (dbxEditorRecords::reorder($records, $invalid_order) !== false) {
        fwrite(STDERR, "Ungueltige Record-Reihenfolge wurde akzeptiert.\n");
        exit(1);
    }
}

$default = dbxEditorRecords::default_field();
foreach (array('name', 'type', 'length', 'rules', 'protect', 'tpl') as $required_key) {
    if (!array_key_exists($required_key, $default)) {
        fwrite(STDERR, "Default-Feld fehlt: {$required_key}\n");
        exit(1);
    }
}
if ($default['type'] !== 'varchar' || $default['length'] !== '255' || $default['tpl'] !== 'text-label') {
    fwrite(STDERR, "Default-Feldwerte sind fehlerhaft.\n");
    exit(1);
}

echo "OK gemeinsame DD-/FD-Editor-Records\n";

