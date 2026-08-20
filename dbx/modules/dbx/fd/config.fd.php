<?php
$messages = array();
$messages['module_subtitle'] = 'Modul: dbx';
$messages['config_info'] = 'dbx-Systemkonfiguration bearbeiten.';
$messages['config_save'] = 'Konfiguration speichern';
$messages['config_saved'] = 'Die Konfiguration wurde erfolgreich gespeichert.';
$messages['config_save_error'] = 'Fehler beim Speichern der Konfiguration.';
$messages['no_entries'] = 'Keine Einträge vorhanden.';
$messages['no_sql_servers'] = 'Keine SQL-Server in der Konfiguration. Modul-SQLite (*.db3) wird von dbxDB automatisch unter dbx/modules/*/db/ aufgelöst.';
$messages['edit_section'] = '{section} bearbeiten.';
$messages['save_section'] = '{section} speichern';
$messages['create_section'] = '{section} anlegen';
$messages['delete_section'] = '{section} löschen';
$messages['check_new_name'] = 'Bitte prüfen Sie den neuen Namen.';
$messages['enter_new_name'] = 'Bitte geben Sie den neuen Namen ein.';
$messages['entry_exists'] = 'Eintrag „{entry}“ ist bereits vorhanden.';
$messages['entry_create_error'] = 'Der Eintrag konnte nicht angelegt werden.';
$messages['entry_created'] = 'Eintrag „{entry}“ wurde angelegt.';
$messages['entry_delete_error'] = 'Eintrag „{entry}“ konnte nicht gelöscht werden.';
$messages['entry_deleted'] = 'Eintrag „{entry}“ wurde gelöscht.';
$messages['check_input'] = 'Bitte prüfen Sie Ihre Eingaben.';
$messages['module_sqlite_forbidden'] = 'Modul-SQLite (*.db3) gehört nicht in die Systemkonfiguration.';
$messages['entry_save_error'] = 'Der Eintrag konnte nicht gespeichert werden.';
$messages['entry_saved'] = 'Der Eintrag wurde gespeichert.';
$messages['new_entry_info'] = 'Neuen {section}-Eintrag anlegen.';
$messages['label_new_entry'] = 'Neuer Eintrag';
$messages['tooltip_new_entry'] = 'Name für den neuen Eintrag';
$messages['edit_entry_info'] = '{section} „{entry}“ bearbeiten.';
$messages['confirm_delete_entry'] = '{section} „{entry}“ wirklich löschen?';

// Alias: Formular-FD zeigt auf cfg/config.dd.php (eine Quelle).
require dirname(__DIR__) . '/cfg/config.dd.php';
