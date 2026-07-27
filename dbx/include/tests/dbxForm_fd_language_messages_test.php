<?php

/**
 * Integrationstest für sprachabhängige FD-Dateien und FD-Meldungen.
 *
 * Geprüft werden:
 * - jede FD besitzt eine englische und spanische Sprachversion,
 * - jede Sprachversion liefert die passenden Speichermeldungen,
 * - dbxForm lädt Meldungen auch aus seinem FD-Cache,
 * - dbxReport erbt dieselbe Funktionalität,
 * - Erfolgs- und Fehlerpfad von save_post() verwenden die FD-Meldungen.
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/dbxKernel.php';

dbx()->get_system_obj('dbxForm', 'load');
dbx()->get_system_obj('dbxReport', 'load');

$fail = static function (string $message): void {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$moduleRoot = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'modules';
$groups = array();
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($moduleRoot, FilesystemIterator::SKIP_DOTS)
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

$expected = array(
    'de' => array(
        'save_success' => 'Daten wurden gespeichert',
        'save_error' => 'Daten konnten nicht gespeichert werden',
    ),
    'en' => array(
        'save_success' => 'Data was saved',
        'save_error' => 'Data could not be saved',
    ),
    'es' => array(
        'save_success' => 'Los datos se guardaron',
        'save_error' => 'Los datos no se pudieron guardar',
    ),
);

$readFd = static function (string $path): array {
    $fields = array();
    $messages = array();
    include $path;
    return array(
        'fields' => is_array($fields) ? array_values($fields) : array(),
        'messages' => is_array($messages) ? $messages : array(),
    );
};

$assertNoGermanUiText = static function (
    array $fields,
    array $messages,
    string $path
) use ($fail): void {
    $uiKeys = array(
        'label', 'tooltip', 'errormsg', 'placeholder',
        'prompt', 'hint', 'options', 'data', 'group',
    );
    $germanWords = '~\b(?:'
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
    ) use (&$scan, $germanWords, $fail, $path): void {
        if (is_array($value)) {
            foreach ($value as $key => $nested) {
                $scan($nested, $location . '/' . (string)$key);
            }
            return;
        }
        if (
            is_string($value) &&
            preg_match($germanWords, html_entity_decode($value, ENT_QUOTES), $match)
        ) {
            $fail(
                "{$path}: deutscher UI-Text '{$match[0]}' "
                . "in {$location}."
            );
        }
    };

    foreach ($fields as $position => $field) {
        foreach ($uiKeys as $key) {
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

    $sourcePath = $variants['de'] ?? $variants['neutral'];
    $sourceFd = $readFd($sourcePath);
    $sourceFields = $sourceFd['fields'];
    $sourceMessageKeys = array_keys($sourceFd['messages']);
    sort($sourceMessageKeys);
    foreach ($variants as $variant => $path) {
        $language = in_array($variant, array('en', 'es'), true) ? $variant : 'de';
        $fd = $readFd($path);
        $messages = $fd['messages'];
        foreach ($expected[$language] as $messageKey => $message) {
            if (($messages[$messageKey] ?? null) !== $message) {
                $fail("{$path}: {$messageKey} ist nicht korrekt lokalisiert.");
            }
        }
        if (($messages['save_succeass'] ?? null) !== $messages['save_success']) {
            $fail("{$path}: Kompatibilitätsalias save_succeass fehlt.");
        }

        if (in_array($variant, array('en', 'es'), true)) {
            $messageKeys = array_keys($messages);
            sort($messageKeys);
            if ($messageKeys !== $sourceMessageKeys) {
                $fail(
                    "{$path}: Meldungsschlüssel weichen von der "
                    . 'deutschen FD ab.'
                );
            }
            $assertNoGermanUiText($fd['fields'], $messages, $path);
            if (count($fd['fields']) !== count($sourceFields)) {
                $fail("{$path}: Feldanzahl weicht von der deutschen FD ab.");
            }
            $structuralKeys = array(
                'name', 'type', 'index', 'length', 'default', 'rules',
                'convert', 'protect', 'mask', 'tpl', 'js',
            );
            foreach ($sourceFields as $position => $sourceField) {
                $targetField = $fd['fields'][$position] ?? array();
                foreach ($structuralKeys as $fieldKey) {
                    if (
                        ($sourceField[$fieldKey] ?? null)
                        !== ($targetField[$fieldKey] ?? null)
                    ) {
                        $fail(
                            "{$path}: Feld {$position}/{$fieldKey} "
                            . 'weicht strukturell von der deutschen FD ab.'
                        );
                    }
                }
            }
        }
    }
}

$sourceLoader = new ReflectionMethod('dbxForm', 'get_dd_fields_source');
$sourceLoader->setAccessible(true);

$form = new dbxForm();
$form->_dbx_modul = 'myInvoices';
$form->_dbx_lng = 'en';
$fields = $sourceLoader->invoke($form, 'fd:myInvoices|invoice-form');
if (!$fields || $form->get_fd_message('save_success') !== $expected['en']['save_success']) {
    $fail('dbxForm lädt die englische FD oder ihre Meldungen nicht.');
}

$messageOnlyForm = new dbxForm();
$messageOnlyForm->_dbx_lng = 'es';
$messageOnlyForm->_fd = 'myInvoices|invoice-form';
$messageOnlyForm->load_fd_messages();
if (
    $messageOnlyForm->get_fd_message('save_success')
        !== $expected['es']['save_success'] ||
    $messageOnlyForm->_flds !== array()
) {
    $fail('load_fd_messages() lädt die spanische FD nicht feldfrei.');
}
$formattedMessage = new dbxForm();
$formattedMessage->_messages = array(
    'record' => 'Datensatz #{id}: {name}',
);
if (
    $formattedMessage->format_fd_message(
        'record',
        array('id' => 17, 'name' => 'Test')
    ) !== 'Datensatz #17: Test'
) {
    $fail('format_fd_message() ersetzt benannte FD-Platzhalter nicht.');
}

// Zweiter Lesevorgang prüft ausdrücklich den Runtime-/Session-Cache-Pfad.
$form->clear();
if (
    $form->_messages !== array() ||
    $form->_msg_success !== '#form_msg_success#' ||
    $form->_msg_error !== '#form_msg_error#'
) {
    $fail('dbxForm-Reset behält Meldungen einer vorherigen FD.');
}
$form->_dbx_modul = 'myInvoices';
$form->_dbx_lng = 'en';
$sourceLoader->invoke($form, 'fd:myInvoices|invoice-form');
if ($form->get_fd_message('save_succeass') !== $expected['en']['save_success']) {
    $fail('dbxForm übernimmt FD-Meldungen nicht aus dem Cache.');
}

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

$dbStub = new dbxFormFdMessageDbStub();
$form->oDB = $dbStub;
$form->_rid = 1;
if (!$form->save_post('test|record', 1, '', 0)) {
    $fail('Test-DB-Stellvertreter meldet im Erfolgspfad einen Fehler.');
}
if ($form->_msg_success !== $expected['en']['save_success']) {
    $fail('dbxForm verwendet save_success aus der englischen FD nicht.');
}

$form->clear();
$form->_dbx_modul = 'myInvoices';
$form->_dbx_lng = 'es';
$sourceLoader->invoke($form, 'fd:myInvoices|invoice-form');
$dbStub->result = 0;
$form->oDB = $dbStub;
$form->_rid = 1;
if ($form->save_post('test|record', 1, '', 0)) {
    $fail('Test-DB-Stellvertreter meldet im Fehlerpfad Erfolg.');
}
if (
    $form->_msg_error !== $expected['es']['save_error'] ||
    $form->_general_error !== $expected['es']['save_error']
) {
    $fail('dbxForm verwendet save_error aus der spanischen FD nicht.');
}

$report = new dbxReport();
$report->_dbx_modul = 'myInvoices';
$report->_dbx_lng = 'de';
$report->_fd = 'myInvoices|invoice-form';
$report->load_fd_messages();
$dbStub->result = 1;
$report->oDB = $dbStub;
$report->_rid = 1;
$report->save_post('test|record', 1, '', 0);
if ($report->_msg_success !== $expected['de']['save_success']) {
    $fail('dbxReport erbt die FD-Speichermeldungen nicht von dbxForm.');
}

echo 'OK: ' . count($groups)
    . " FD-Gruppen sind dreisprachig; dbxForm und dbxReport verwenden ihre FD-Meldungen.\n";
