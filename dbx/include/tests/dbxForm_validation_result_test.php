<?php

/**
 * Integrationstest zwischen dbxForm und dem strukturierten Validator.
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/dbxKernel.php';

$form = dbx()->get_system_obj('dbxForm');
$form->clear();

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$oldPost = $_POST;
$oldGet = $_GET;
$oldLanguage = (string)dbx()->get_system_var('dbx_lng', 'de');

try {
    $_GET = [];
    $_POST = ['email' => '0'];

    $value = $form->get_post('email', '', 'email');
    $result = $form->get_validation_result('email');

    $assert($value === '', 'ungueltige Formulareingabe wurde nicht durch den Default ersetzt');
    $assert(($result['valid'] ?? true) === false, 'dbxForm speichert kein ungueltiges Validator-Ergebnis');
    $assert(($result['code'] ?? '') === 'invalid_format', 'dbxForm verliert den Validator-Fehlercode');
    $assert(isset($form->_errors['email']), 'dbxForm markiert das ungueltige Feld nicht');

    $_POST = ['title' => '  Hallo Welt  '];
    $value = $form->get_post('title', '', 'trim|required|words');
    $result = $form->get_validation_result('title');

    $assert($value === 'Hallo Welt', 'explizite trim-Normalisierung wird von dbxForm nicht uebernommen');
    $assert(($result['valid'] ?? false) === true, 'gueltige normalisierte Formulareingabe wurde abgelehnt');
    $assert(($result['normalized'] ?? '') === 'Hallo Welt', 'normalisierter Wert fehlt im Formularergebnis');

    $all = $form->get_validation_result();
    $assert(isset($all['email'], $all['title']), 'Gesamtliste der Formularergebnisse ist unvollstaendig');

    dbx()->set_system_var('dbx_lng', 'en');
    $form->oValidator->setLanguage('');
    $_POST = ['language_probe' => ''];
    $form->get_post('language_probe', '', 'required|email');
    $languageResult = $form->get_validation_result('language_probe');
    $assert(
        ($languageResult['message'] ?? '') === 'Please fill in this field.',
        'dbxForm verwendet nicht automatisch die aktive UI-Sprache'
    );

    $form->clear();
    $assert($form->get_validation_result() === [], 'Formular-Reset behaelt alte Validator-Ergebnisse');
} finally {
    $_POST = $oldPost;
    $_GET = $oldGet;
    dbx()->set_system_var('dbx_lng', $oldLanguage);
    $form->oValidator->setLanguage('');
}

echo "OK: dbxForm uebernimmt strukturierte Validator-Ergebnisse und opt-in-Normalisierung.\n";
