<?php

/**
 * Integrationstest für sprachabhängige FD-Dateien und FD-Meldungen.
 *
 * Geprüft werden:
 * - jede FD besitzt eine englische und spanische Sprachversion,
 * - globale Speichermeldungen nicht mehr in jeder FD dupliziert werden,
 * - dbxForm lädt Meldungen auch aus seinem FD-Cache,
 * - dbxReport erbt dieselbe Funktionalität,
 * - Erfolgs- und Fehlerpfad von save_post() zentrale dbx-Templates verwenden.
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/dbxKernel.php';

dbx()->get_system_obj('dbxForm', 'load');
dbx()->get_system_obj('dbxReport', 'load');

$fail = static function (string $message): void {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$module_root = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'modules';
$groups = array();
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($module_root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    $path = str_replace('\\', '/', $file->getPathname());
    if (
        !$file->isFile() ||
        !str_contains($path, '/fd/') ||
        str_contains($path, '/fd/_backup/') ||
        !str_ends_with($path, '.fd.php')
    ) {
        continue;
    }

    $name = $file->getFilename();
    $variant = 'neutral';
    if (preg_match('/_(de|en|es)\.fd\.php$/', $name, $match)) {
        $variant = $match[1];
    }
    $base = preg_replace('/_(de|en|es)\.fd\.php$/', '.fd.php', $name);
    $key = str_replace('\\', '/', $file->getPath()) . '/' . $base;
    $groups[$key][$variant] = $file->getPathname();
}

if (!$groups) {
    $fail('Keine FD-Dateien gefunden.');
}

$read_fd = static function (string $path): array {
    $fields = array();
    $messages = array();
    include $path;
    return array(
        'fields' => is_array($fields) ? array_values($fields) : array(),
        'messages' => is_array($messages) ? $messages : array(),
    );
};

$assert_no_german_ui_text = static function (
    array $fields,
    array $messages,
    string $path
) use ($fail): void {
    $ui_keys = array(
        'label', 'tooltip', 'errormsg', 'placeholder',
        'prompt', 'hint', 'options', 'data', 'group',
    );
    $german_words = '~\b(?:'
        . 'Daten|Bitte|nicht|keine?|konnten|wurde[n]?|gespeichert|'
        . 'Speichern|Löschen|gelöscht|Benutzer|Kunde|Rechnung|'
        . 'Rechnungsnummer|Artikel|Bestellung|Beschreibung|Bezeichnung|'
        . 'auswählen|ausgewählt|aufsteigend|absteigend|Rolle[n]?|'
        . 'Gruppe[n]?|verfügbar|verwenden|aktivieren|deaktivieren|'
        . 'erlauben|eingeben|ungültig|erforderlich|Seite[n]?|Sprache|'
        . 'Deutsch|Entwurf|Offen|Bezahlt|Zeilen|Sortierung|Suche|'
        . 'Erstellen|Ändern|Bearbeiten|Zurück|Nein|Ja|Vorkasse|'
        . 'Ueberweisung|Überweisung|Strasse|Straße|PLZ|Ort|Land|Summe|'
        . 'Betreff|Rueckfrage|Rückfrage|Anfrage[n]?|Nachricht|'
        . 'entfernen|ausblenden|hinzufuegen|hinzufügen'
        . ')\b~iu';

    $scan = static function (
        $value,
        string $location
    ) use (&$scan, $german_words, $fail, $path): void {
        if (is_array($value)) {
            foreach ($value as $key => $nested) {
                $scan($nested, $location . '/' . (string)$key);
            }
            return;
        }
        if (
            is_string($value) &&
            preg_match($german_words, html_entity_decode($value, ENT_QUOTES), $match)
        ) {
            $fail(
                "{$path}: deutscher UI-Text '{$match[0]}' "
                . "in {$location}."
            );
        }
    };

    foreach ($fields as $position => $field) {
        foreach ($ui_keys as $key) {
            if (array_key_exists($key, $field)) {
                $scan($field[$key], "Feld {$position}/{$key}");
            }
        }
    }
    $scan($messages, 'Meldungen');
};

foreach ($groups as $key => $variants) {
    if (!isset($variants['neutral']) && !isset($variants['de'])) {
        $fail("Deutsche/neutrale Basis-FD fehlt: {$key}");
    }
    foreach (array('en', 'es') as $language) {
        if (!isset($variants[$language])) {
            $fail("Sprachversion {$language} fehlt: {$key}");
        }
    }

    $source_path = $variants['de'] ?? $variants['neutral'];
    $source_fd = $read_fd($source_path);
    $source_fields = $source_fd['fields'];
    $source_message_keys = array_keys($source_fd['messages']);
    sort($source_message_keys);
    foreach ($variants as $variant => $path) {
        $language = in_array($variant, array('en', 'es'), true) ? $variant : 'de';
        $fd = $read_fd($path);
        $messages = $fd['messages'];
        foreach (array('save_success', 'save_error') as $global_key) {
            if (array_key_exists($global_key, $messages)) {
                $fail("{$path}: globale Meldung {$global_key} gehoert in das Modul dbx.");
            }
        }

        if (in_array($variant, array('en', 'es'), true)) {
            $message_keys = array_keys($messages);
            sort($message_keys);
            if ($message_keys !== $source_message_keys) {
                $fail(
                    "{$path}: Meldungsschlüssel weichen von der "
                    . 'deutschen FD ab.'
                );
            }
            $assert_no_german_ui_text($fd['fields'], $messages, $path);
            if (count($fd['fields']) !== count($source_fields)) {
                $fail("{$path}: Feldanzahl weicht von der deutschen FD ab.");
            }
            $structural_keys = array(
                'name', 'type', 'index', 'length', 'default', 'rules',
                'convert', 'protect', 'mask', 'tpl', 'js',
            );
            foreach ($source_fields as $position => $source_field) {
                $target_field = $fd['fields'][$position] ?? array();
                foreach ($structural_keys as $field_key) {
                    if (
                        ($source_field[$field_key] ?? null)
                        !== ($target_field[$field_key] ?? null)
                    ) {
                        $fail(
                            "{$path}: Feld {$position}/{$field_key} "
                            . 'weicht strukturell von der deutschen FD ab.'
                        );
                    }
                }
            }
        }
    }
}

$source_loader = new ReflectionMethod('dbxForm', 'get_dd_fields_source');
$source_loader->setAccessible(true);

$form = new dbxForm();
$form->_dbx_modul = 'myInvoices';
$form->_dbx_lng = 'en';
$fields = $source_loader->invoke($form, 'fd:myInvoices|invoice-form');
if (!$fields || $form->get_fd_message('save_success') !== '') {
    $fail('dbxForm lädt die englische FD nicht ohne globale Speichermeldung.');
}

$message_only_form = new dbxForm();
$message_only_form->_dbx_lng = 'es';
$message_only_form->set_field_definition('myInvoices|invoice-form');
$message_only_form->load_fd_messages();
if (
    $message_only_form->get_fd_message('save_success') !== '' ||
    $message_only_form->_flds !== array()
) {
    $fail('load_fd_messages() lädt die spanische FD nicht feldfrei.');
}
$formatted_message = new dbxForm();
$formatted_message->set_fd_messages(array(
    'record' => 'Datensatz #{id}: {name}',
));
if (
    $formatted_message->format_fd_message(
        'record',
        array('id' => 17, 'name' => 'Test')
    ) !== 'Datensatz #17: Test'
) {
    $fail('format_fd_message() ersetzt benannte FD-Platzhalter nicht.');
}

// Zweiter Lesevorgang prüft ausdrücklich den Runtime-/Session-Cache-Pfad.
$form->clear();
if (
    $form->get_fd_messages() !== array() ||
    $form->_msg_success !== '#form_msg_success#' ||
    $form->_msg_error !== '#form_msg_error#'
) {
    $fail('dbxForm-Reset behält Meldungen einer vorherigen FD.');
}
$form->_dbx_modul = 'myInvoices';
$form->_dbx_lng = 'en';
$source_loader->invoke($form, 'fd:myInvoices|invoice-form');

class dbxFormFdMessageDbStub
{
    public $_fld_id = 'id';
    public $_error = 'test-error';
    public $_query = 'test-query';
    public $_insert_id = 0;
    public $result = 1;

    public function save($dd, $post, $rid)
    {
        return $this->result;
    }
}

$db_stub = new dbxFormFdMessageDbStub();
$form->o_db = $db_stub;
$form->set_rid(1);
if (!$form->save_post('test|record', 1, '', 0)) {
    $fail('Test-DB-Stellvertreter meldet im Erfolgspfad einen Fehler.');
}
dbx()->set_system_var('dbx_lng', 'en');
if ($form->_msg_success !== '#form_msg_save_success#'
    || strpos($form->get_form_msg('success', $form->_msg_success), 'The data was saved.') === false) {
    $fail('dbxForm verwendet das englische zentrale Speicher-Template nicht.');
}

$form->clear();
$form->_dbx_modul = 'myInvoices';
$form->_dbx_lng = 'es';
$source_loader->invoke($form, 'fd:myInvoices|invoice-form');
$db_stub->result = 0;
$form->o_db = $db_stub;
$form->set_rid(1);
if ($form->save_post('test|record', 1, '', 0)) {
    $fail('Test-DB-Stellvertreter meldet im Fehlerpfad Erfolg.');
}
dbx()->set_system_var('dbx_lng', 'es');
if (
    $form->_msg_error !== '#form_msg_save_error#' ||
    $form->_general_error !== '#form_msg_save_error#' ||
    strpos($form->get_form_msg('error', $form->_msg_error), 'Los datos no se pudieron guardar.') === false
) {
    $fail('dbxForm verwendet das spanische zentrale Speicherfehler-Template nicht.');
}

$report = new dbxReport();
$report->_dbx_modul = 'myInvoices';
$report->_dbx_lng = 'de';
$report->set_field_definition('myInvoices|invoice-form');
$report->load_fd_messages();
$db_stub->result = 1;
$report->o_db = $db_stub;
$report->set_rid(1);
$report->save_post('test|record', 1, '', 0);
if ($report->_msg_success !== '#form_msg_save_success#') {
    $fail('dbxReport erbt die zentrale Speichermeldung nicht von dbxForm.');
}

echo 'OK: ' . count($groups)
    . " FD-Gruppen sind dreisprachig; globale Formmeldungen kommen aus dbx-Templates.\n";
